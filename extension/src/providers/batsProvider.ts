/**
 * BATS Test Provider
 * 
 * Discovers and executes BATS tests with proper multi-root workspace support.
 * Uses Docker for cross-platform consistency.
 */

import * as vscode from 'vscode';
import * as path from 'path';
import { spawn, SpawnOptions } from 'child_process';
import { Logger } from '../utils/logger';
import { getWorkspaceFolder, getRelativeToWorkspaceFolder, toDockerPath, isDockerAvailable } from '../utils/paths';

interface TestCase {
    name: string;
    line: number;
}

export class BatsTestProvider implements vscode.Disposable {
    private controller: vscode.TestController;
    private logger: Logger;
    private disposables: vscode.Disposable[] = [];

    constructor(context: vscode.ExtensionContext, logger: Logger) {
        this.logger = logger;
        this.controller = vscode.tests.createTestController('pluginTests.bats', 'BATS Tests');
        
        // Set up the controller
        this.controller.resolveHandler = this.resolveHandler.bind(this);
        this.controller.refreshHandler = this.refreshHandler.bind(this);

        // Create run profile
        const runProfile = this.controller.createRunProfile(
            'Run BATS Tests',
            vscode.TestRunProfileKind.Run,
            this.runHandler.bind(this),
            true
        );

        this.disposables.push(this.controller, runProfile);

        // Watch for file changes
        this.setupFileWatchers();

        // Initial discovery (async, errors handled internally)
        this.discoverTests().catch(error => {
            this.logger.error('Failed during initial BATS test discovery', error);
        });
    }

    private setupFileWatchers(): void {
        const pattern = this.getTestPattern();
        const watcher = vscode.workspace.createFileSystemWatcher(pattern);

        watcher.onDidCreate(uri => this.onTestFileCreated(uri).catch(e => this.logger.error('File watcher create error', e)));
        watcher.onDidChange(uri => this.onTestFileChanged(uri).catch(e => this.logger.error('File watcher change error', e)));
        watcher.onDidDelete(uri => this.onTestFileDeleted(uri));

        this.disposables.push(watcher);
    }

    private getTestPattern(): string {
        return vscode.workspace.getConfiguration('pluginTests').get<string>('bats.pattern', '**/*.bats');
    }

    private getExcludePattern(): string {
        return vscode.workspace.getConfiguration('pluginTests').get<string>('bats.exclude', '**/node_modules/**');
    }

    private getAllowedWorkspaceFolders(): string[] {
        return vscode.workspace.getConfiguration('pluginTests').get<string[]>('workspaceFolders', []);
    }

    private isAllowedWorkspaceFolder(uri: vscode.Uri): boolean {
        const allowed = this.getAllowedWorkspaceFolders();
        if (allowed.length === 0) {
            return true; // No filter = allow all
        }
        const folder = getWorkspaceFolder(uri);
        return folder ? allowed.includes(folder.name) : false;
    }

    private async discoverTests(): Promise<void> {
        this.logger.info('Discovering BATS tests...');
        const pattern = this.getTestPattern();
        const excludePatterns = this.getExcludePattern().split(',').map(p => p.trim()).filter(p => p);
        
        // findFiles only takes one exclude pattern, so we'll use the first one and filter the rest
        const primaryExclude = excludePatterns[0] || '**/node_modules/**';
        const files = await vscode.workspace.findFiles(pattern, primaryExclude);
        
        // Apply additional exclude patterns manually
        let filteredFiles = files;
        for (const excludePattern of excludePatterns.slice(1)) {
            filteredFiles = filteredFiles.filter(f => {
                const relativePath = vscode.workspace.asRelativePath(f);
                // Simple glob matching for **/ patterns
                const pattern = excludePattern.replace(/\*\*/g, '.*').replace(/\*/g, '[^/]*');
                const regex = new RegExp(pattern);
                return !regex.test(relativePath);
            });
        }
        
        // Filter by allowed workspace folders
        filteredFiles = filteredFiles.filter(f => this.isAllowedWorkspaceFolder(f));
        
        this.logger.info(`Found ${filteredFiles.length} BATS test files (${files.length} before filtering)`);
        
        for (const file of filteredFiles) {
            await this.createTestItem(file);
        }
    }

    private async createTestItem(uri: vscode.Uri): Promise<vscode.TestItem | undefined> {
        const workspaceFolder = getWorkspaceFolder(uri);
        if (!workspaceFolder) {
            this.logger.warn(`File ${uri.fsPath} is not in a workspace folder`);
            return undefined;
        }

        // Use absolute path as ID to ensure uniqueness across multi-root workspaces
        const id = uri.fsPath;
        const label = path.basename(uri.fsPath);
        
        const testItem = this.controller.createTestItem(id, label, uri);
        testItem.canResolveChildren = true;
        
        // Store workspace folder for later use
        testItem.description = workspaceFolder.name;
        
        this.controller.items.add(testItem);
        this.logger.debug(`Added test file: ${label} (${workspaceFolder.name})`);
        
        return testItem;
    }

    private async resolveHandler(item: vscode.TestItem | undefined): Promise<void> {
        try {
            if (!item) {
                // Resolve all - trigger full discovery
                await this.discoverTests();
                return;
            }

            if (!item.uri) {
                return;
            }

            // Only parse file-level items (no :: in ID)
            if (item.id.includes('::')) {
                return; // Already a child item, don't re-resolve
            }

            // Skip if already has children
            if (item.children.size > 0) {
                return;
            }

            // Parse the file to find individual test cases
            await this.parseTestFile(item);
        } catch (error) {
            this.logger.error('Error in resolveHandler', error);
        }
    }

    private async refreshHandler(token: vscode.CancellationToken): Promise<void> {
        try {
            this.logger.info('Refreshing BATS tests...');
            
            // Clear existing items
            this.controller.items.replace([]);
            
            // Re-discover
            await this.discoverTests();
        } catch (error) {
            this.logger.error('Error in refreshHandler', error);
        }
    }

    private async parseTestFile(fileItem: vscode.TestItem): Promise<void> {
        if (!fileItem.uri) {
            return;
        }

        try {
            const document = await vscode.workspace.openTextDocument(fileItem.uri);
            const content = document.getText();
            const tests = this.extractTestCases(content);

            // Clear existing children
            fileItem.children.replace([]);

            for (const test of tests) {
                const testId = `${fileItem.id}::${test.name}`;
                const testItem = this.controller.createTestItem(testId, test.name, fileItem.uri);
                testItem.range = new vscode.Range(test.line, 0, test.line, 0);
                fileItem.children.add(testItem);
            }

            this.logger.debug(`Parsed ${tests.length} tests from ${path.basename(fileItem.uri.fsPath)}`);
        } catch (error) {
            this.logger.error(`Failed to parse test file: ${fileItem.uri.fsPath}`, error);
        }
    }

    private extractTestCases(content: string): TestCase[] {
        const tests: TestCase[] = [];
        const lines = content.split('\n');
        
        // Match @test "test name" { or @test 'test name' {
        const testRegex = /@test\s+["'](.+?)["']\s*\{/;

        for (let i = 0; i < lines.length; i++) {
            const match = testRegex.exec(lines[i]);
            if (match) {
                tests.push({
                    name: match[1],
                    line: i
                });
            }
        }

        return tests;
    }

    private async runHandler(
        request: vscode.TestRunRequest,
        token: vscode.CancellationToken
    ): Promise<void> {
        const run = this.controller.createTestRun(request);
        const testsToRun = request.include ?? this.getAllTests();

        for (const test of testsToRun) {
            if (token.isCancellationRequested) {
                run.skipped(test);
                continue;
            }

            await this.runTest(run, test, token);
        }

        run.end();
    }

    private getAllTests(): vscode.TestItem[] {
        const tests: vscode.TestItem[] = [];
        this.controller.items.forEach(item => tests.push(item));
        return tests;
    }

    private async runTest(
        run: vscode.TestRun,
        test: vscode.TestItem,
        token: vscode.CancellationToken
    ): Promise<void> {
        run.started(test);

        // Ensure children are resolved before running
        if (test.children.size === 0 && test.canResolveChildren) {
            await this.parseTestFile(test);
        }

        if (!test.uri) {
            run.errored(test, new vscode.TestMessage('Test has no associated file'));
            return;
        }

        const workspaceFolder = getWorkspaceFolder(test.uri);
        if (!workspaceFolder) {
            run.errored(test, new vscode.TestMessage('Test file is not in a workspace folder'));
            return;
        }

        const config = vscode.workspace.getConfiguration('pluginTests');
        const useDocker = config.get<boolean>('bats.useDocker', true);
        const timeout = config.get<number>('timeout', 30000);

        try {
            const startTime = Date.now();
            
            if (useDocker && await isDockerAvailable()) {
                await this.runTestInDocker(run, test, workspaceFolder, token);
            } else {
                await this.runTestNatively(run, test, workspaceFolder, token);
            }

            const duration = Date.now() - startTime;
            
            // If we haven't already set a state, mark as passed
            // (The run methods will set failed/errored states)
            
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);
            run.errored(test, new vscode.TestMessage(message));
        }
    }

    private async runTestInDocker(
        run: vscode.TestRun,
        test: vscode.TestItem,
        workspaceFolder: vscode.WorkspaceFolder,
        token: vscode.CancellationToken
    ): Promise<void> {
        const config = vscode.workspace.getConfiguration('pluginTests');
        const dockerImage = config.get<string>('bats.dockerImage', 'bats/bats:latest');
        const timeout = config.get<number>('timeout', 30000);

        // CRITICAL: Use path relative to the workspace folder, not workspace root
        const relativePath = getRelativeToWorkspaceFolder(test.uri!);
        const workspacePath = workspaceFolder.uri.fsPath;
        const dockerMount = toDockerPath(workspacePath);

        // Build the command
        const args = ['run', '--rm', '-v', `${dockerMount}:/code`, '-w', '/code', dockerImage];
        
        // Add test file path (relative to workspace folder)
        const testPath = relativePath.replace(/\\/g, '/');
        args.push(testPath);
        
        // Add formatter for parsing
        args.push('--formatter', 'tap', '--timing');

        // If this is a specific test case (has parent), add filter
        if (test.parent) {
            args.push('--filter', test.label);
        }

        this.logger.info(`Running: docker ${args.join(' ')}`);
        run.appendOutput(`Running: docker ${args.join(' ')}\r\n`);

        return new Promise<void>((resolve, reject) => {
            const proc = spawn('docker', args, {
                cwd: workspacePath,
                timeout,
                shell: true
            });

            let stdout = '';
            let stderr = '';

            proc.stdout?.on('data', (data: Buffer) => {
                const text = data.toString();
                stdout += text;
                run.appendOutput(text.replace(/\n/g, '\r\n'));
            });

            proc.stderr?.on('data', (data: Buffer) => {
                const text = data.toString();
                stderr += text;
                run.appendOutput(`[stderr] ${text.replace(/\n/g, '\r\n')}`);
            });

            token.onCancellationRequested(() => {
                proc.kill();
                run.skipped(test);
                resolve();
            });

            proc.on('close', (code) => {
                if (code === 0) {
                    this.parseResults(run, test, stdout);
                    if (!test.children.size) {
                        run.passed(test);
                    }
                } else if (code === 1) {
                    // BATS returns 1 for test failures
                    this.parseResults(run, test, stdout);
                    if (!test.children.size) {
                        run.failed(test, new vscode.TestMessage('One or more tests failed'));
                    }
                } else {
                    run.errored(test, new vscode.TestMessage(`BATS exited with code ${code}\n${stderr}`));
                }
                resolve();
            });

            proc.on('error', (error) => {
                run.errored(test, new vscode.TestMessage(error.message));
                reject(error);
            });
        });
    }

    private async runTestNatively(
        run: vscode.TestRun,
        test: vscode.TestItem,
        workspaceFolder: vscode.WorkspaceFolder,
        token: vscode.CancellationToken
    ): Promise<void> {
        const timeout = vscode.workspace.getConfiguration('pluginTests').get<number>('timeout', 30000);
        const relativePath = getRelativeToWorkspaceFolder(test.uri!);
        
        const args = [relativePath.replace(/\\/g, '/'), '--formatter', 'tap', '--timing'];
        
        if (test.parent) {
            args.push('--filter', test.label);
        }

        this.logger.info(`Running: bats ${args.join(' ')}`);
        run.appendOutput(`Running: bats ${args.join(' ')}\r\n`);

        const spawnOptions: SpawnOptions = {
            cwd: workspaceFolder.uri.fsPath,
            timeout,
            shell: true
        };

        return new Promise<void>((resolve, reject) => {
            const proc = spawn('bats', args, spawnOptions);

            let stdout = '';
            let stderr = '';

            proc.stdout?.on('data', (data: Buffer) => {
                const text = data.toString();
                stdout += text;
                run.appendOutput(text.replace(/\n/g, '\r\n'));
            });

            proc.stderr?.on('data', (data: Buffer) => {
                const text = data.toString();
                stderr += text;
                run.appendOutput(`[stderr] ${text.replace(/\n/g, '\r\n')}`);
            });

            token.onCancellationRequested(() => {
                proc.kill();
                run.skipped(test);
                resolve();
            });

            proc.on('close', (code) => {
                if (code === 0) {
                    this.parseResults(run, test, stdout);
                    if (!test.children.size) {
                        run.passed(test);
                    }
                } else if (code === 1) {
                    this.parseResults(run, test, stdout);
                    if (!test.children.size) {
                        run.failed(test, new vscode.TestMessage('One or more tests failed'));
                    }
                } else {
                    run.errored(test, new vscode.TestMessage(`BATS exited with code ${code}\n${stderr}`));
                }
                resolve();
            });

            proc.on('error', (error) => {
                run.errored(test, new vscode.TestMessage(error.message));
                reject(error);
            });
        });
    }

    private parseResults(run: vscode.TestRun, test: vscode.TestItem, output: string): void {
        // Parse TAP output: ok 1 test name in 0ms / not ok 1 test name in 0ms
        const lines = output.split('\n');
        const resultRegex = /^(ok|not ok)\s+\d+\s+(.+?)\s+in\s+(\d+)(ms|sec)$/;

        for (const line of lines) {
            const match = resultRegex.exec(line.trim());
            if (match) {
                const status = match[1];
                const testName = match[2];
                let duration = parseInt(match[3], 10);
                if (match[4] === 'sec') {
                    duration *= 1000;
                }

                // Find the child test item
                let childTest: vscode.TestItem | undefined;
                test.children.forEach(child => {
                    if (child.label === testName) {
                        childTest = child;
                    }
                });

                if (childTest) {
                    run.started(childTest);
                    if (status === 'ok') {
                        run.passed(childTest, duration);
                    } else {
                        run.failed(childTest, new vscode.TestMessage(`Test failed`), duration);
                    }
                }
            }
        }
    }

    private async onTestFileCreated(uri: vscode.Uri): Promise<void> {
        this.logger.debug(`Test file created: ${uri.fsPath}`);
        await this.createTestItem(uri);
    }

    private async onTestFileChanged(uri: vscode.Uri): Promise<void> {
        const existing = this.controller.items.get(uri.fsPath);
        if (existing) {
            await this.parseTestFile(existing);
        }
    }

    private onTestFileDeleted(uri: vscode.Uri): void {
        this.logger.debug(`Test file deleted: ${uri.fsPath}`);
        this.controller.items.delete(uri.fsPath);
    }

    dispose(): void {
        for (const disposable of this.disposables) {
            disposable.dispose();
        }
    }
}

/**
 * PHPUnit Test Provider
 * 
 * Discovers and executes PHPUnit tests with proper multi-root workspace support.
 */

import * as vscode from 'vscode';
import * as path from 'path';
import { spawn, SpawnOptions } from 'child_process';
import { Logger } from '../utils/logger';
import { getWorkspaceFolder, getRelativeToWorkspaceFolder } from '../utils/paths';

interface TestMethod {
    name: string;
    line: number;
}

interface TestClass {
    name: string;
    line: number;
    methods: TestMethod[];
}

export class PhpUnitTestProvider implements vscode.Disposable {
    private controller: vscode.TestController;
    private logger: Logger;
    private disposables: vscode.Disposable[] = [];

    constructor(context: vscode.ExtensionContext, logger: Logger) {
        this.logger = logger;
        this.controller = vscode.tests.createTestController('pluginTests.phpunit', 'PHPUnit Tests');
        
        this.controller.resolveHandler = this.resolveHandler.bind(this);
        this.controller.refreshHandler = this.refreshHandler.bind(this);

        const runProfile = this.controller.createRunProfile(
            'Run PHPUnit Tests',
            vscode.TestRunProfileKind.Run,
            this.runHandler.bind(this),
            true
        );

        this.disposables.push(this.controller, runProfile);

        this.setupFileWatchers();
        this.discoverTests().catch(error => {
            this.logger.error('Failed during initial PHPUnit test discovery', error);
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
        return vscode.workspace.getConfiguration('pluginTests').get<string>('phpunit.pattern', '**/*Test.php');
    }

    private getExcludePattern(): string {
        return vscode.workspace.getConfiguration('pluginTests').get<string>('phpunit.exclude', '**/vendor/**');
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
        this.logger.info('Discovering PHPUnit tests...');
        const pattern = this.getTestPattern();
        const excludePatterns = this.getExcludePattern().split(',').map(p => p.trim()).filter(p => p);
        
        // findFiles only takes one exclude pattern, so we'll use the first one and filter the rest
        const primaryExclude = excludePatterns[0] || '**/vendor/**';
        const files = await vscode.workspace.findFiles(pattern, primaryExclude);
        
        // Apply additional exclude patterns manually
        let filteredFiles = files;
        for (const excludePattern of excludePatterns.slice(1)) {
            const excludeGlob = new vscode.RelativePattern('', excludePattern);
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
        
        this.logger.info(`Found ${filteredFiles.length} PHPUnit test files (${files.length} before filtering)`);
        
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

        const id = uri.fsPath;
        const label = path.basename(uri.fsPath);
        
        const testItem = this.controller.createTestItem(id, label, uri);
        testItem.canResolveChildren = true;
        testItem.description = workspaceFolder.name;
        
        this.controller.items.add(testItem);
        this.logger.debug(`Added test file: ${label} (${workspaceFolder.name})`);
        
        return testItem;
    }

    private async resolveHandler(item: vscode.TestItem | undefined): Promise<void> {
        try {
            if (!item) {
                await this.discoverTests();
                return;
            }

            if (!item.uri) {
                return;
            }

            // Only parse file-level items, not class or method items
            // Check if this is a file item (no :: in the ID)
            if (item.id.includes('::')) {
                return; // Already a child item, don't re-resolve
            }

            // Skip if already has children
            if (item.children.size > 0) {
                return;
            }

            await this.parseTestFile(item);
        } catch (error) {
            this.logger.error('Error in resolveHandler', error);
        }
    }

    private async refreshHandler(token: vscode.CancellationToken): Promise<void> {
        try {
            this.logger.info('Refreshing PHPUnit tests...');
            this.controller.items.replace([]);
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
            const testClass = this.extractTestClass(content);

            if (!testClass) {
                return;
            }

            fileItem.children.replace([]);

            // Add class-level item
            const classId = `${fileItem.id}::${testClass.name}`;
            const classItem = this.controller.createTestItem(classId, testClass.name, fileItem.uri);
            classItem.range = new vscode.Range(testClass.line, 0, testClass.line, 0);
            // Don't set canResolveChildren - methods are already added below

            // Add method-level items
            for (const method of testClass.methods) {
                const methodId = `${classId}::${method.name}`;
                const methodItem = this.controller.createTestItem(methodId, method.name, fileItem.uri);
                methodItem.range = new vscode.Range(method.line, 0, method.line, 0);
                classItem.children.add(methodItem);
            }

            fileItem.children.add(classItem);

            this.logger.debug(`Parsed ${testClass.methods.length} tests from ${path.basename(fileItem.uri.fsPath)}`);
        } catch (error) {
            this.logger.error(`Failed to parse test file: ${fileItem.uri.fsPath}`, error);
        }
    }

    private extractTestClass(content: string): TestClass | null {
        const lines = content.split('\n');
        
        // Find class declaration
        const classRegex = /class\s+(\w+Test)\s+extends/;
        let className = '';
        let classLine = 0;

        for (let i = 0; i < lines.length; i++) {
            const match = classRegex.exec(lines[i]);
            if (match) {
                className = match[1];
                classLine = i;
                break;
            }
        }

        if (!className) {
            return null;
        }

        // Find test methods
        const methods: TestMethod[] = [];
        const methodRegex = /^\s*(?:public\s+)?function\s+(test\w+)\s*\(/;
        const annotationRegex = /@test/;

        for (let i = 0; i < lines.length; i++) {
            const methodMatch = methodRegex.exec(lines[i]);
            if (methodMatch) {
                methods.push({
                    name: methodMatch[1],
                    line: i
                });
                continue;
            }

            // Check for @test annotation on previous line
            if (annotationRegex.test(lines[i])) {
                // Look for the next function
                for (let j = i + 1; j < lines.length && j < i + 5; j++) {
                    const funcMatch = /^\s*(?:public\s+)?function\s+(\w+)\s*\(/.exec(lines[j]);
                    if (funcMatch) {
                        methods.push({
                            name: funcMatch[1],
                            line: j
                        });
                        break;
                    }
                }
            }
        }

        return {
            name: className,
            line: classLine,
            methods
        };
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

        // Find the file-level test item
        let fileTest = test;
        while (fileTest.parent) {
            fileTest = fileTest.parent;
        }

        if (!fileTest.uri) {
            run.errored(test, new vscode.TestMessage('Test has no associated file'));
            return;
        }

        const workspaceFolder = getWorkspaceFolder(fileTest.uri);
        if (!workspaceFolder) {
            run.errored(test, new vscode.TestMessage('Test file is not in a workspace folder'));
            return;
        }

        const config = vscode.workspace.getConfiguration('pluginTests');
        const phpunitPath = config.get<string>('phpunit.executable', 'vendor/bin/phpunit');
        const timeout = config.get<number>('timeout', 30000);

        // Build the filter based on test level
        let filter = '';
        if (test.id.includes('::')) {
            const parts = test.id.split('::');
            if (parts.length === 3) {
                // Method level: Class::method
                filter = `--filter="${parts[1]}::${parts[2]}"`;
            } else if (parts.length === 2 && !test.uri?.fsPath.endsWith(parts[1])) {
                // Class level
                filter = `--filter="${parts[1]}"`;
            }
        }

        const relativePath = getRelativeToWorkspaceFolder(fileTest.uri);
        const args = [phpunitPath, relativePath.replace(/\\/g, '/'), '--testdox'];
        if (filter) {
            args.push(filter);
        }

        this.logger.info(`Running: php ${args.join(' ')}`);
        run.appendOutput(`Running: php ${args.join(' ')}\r\n`);

        const spawnOptions: SpawnOptions = {
            cwd: workspaceFolder.uri.fsPath,
            timeout,
            shell: true
        };

        return new Promise<void>((resolve) => {
            const proc = spawn('php', args, spawnOptions);

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
                // Parse testdox output to update individual test results
                this.parseResults(run, test, stdout, code === 0);
                resolve();
            });

            proc.on('error', (error) => {
                run.errored(test, new vscode.TestMessage(error.message));
                resolve();
            });
        });
    }

    private parseResults(run: vscode.TestRun, test: vscode.TestItem, output: string, allPassed: boolean): void {
        // Parse testdox output format:
        // ✔ Test name here
        // ✘ Failed test name
        const lines = output.split('\n');
        
        // More flexible regex - match various checkmark/cross characters
        const passRegex = /^\s*[✔✓√]\s+(.+)$/;
        const failRegex = /^\s*[✘✗×]\s+(.+)$/;
        
        // Build a map of testdox names to their test items
        const testMap = new Map<string, vscode.TestItem>();
        
        const methodToTestdox = (methodName: string): string => {
            // testSanitizeStrRemovesDots -> sanitize str removes dots
            return methodName
                .replace(/^test_?/i, '')  // Remove test prefix
                .replace(/_/g, ' ')        // Replace underscores with spaces
                .replace(/([a-z])([A-Z])/g, '$1 $2')  // Add space before capitals
                .toLowerCase()
                .trim();
        };
        
        const collectTests = (item: vscode.TestItem) => {
            item.children.forEach(child => {
                const testdoxName = methodToTestdox(child.label);
                testMap.set(testdoxName, child);
                this.logger.debug(`Mapped '${testdoxName}' -> ${child.label}`);
                collectTests(child);
            });
        };
        collectTests(test);

        this.logger.debug(`Test map has ${testMap.size} entries`);
        
        // Debug: show the lines we're checking
        for (const line of lines) {
            if (line.trim().length > 0 && !line.includes('PHPUnit') && !line.includes('Runtime') && !line.includes('Configuration') && !line.includes('Time:') && !line.includes('OK (')) {
                this.logger.debug(`Checking line: '${line}'`);
            }
        }

        let foundResults = false;
        for (const line of lines) {
            const trimmedLine = line.trim();
            const passMatch = passRegex.exec(trimmedLine);
            if (passMatch) {
                const testName = passMatch[1].trim().toLowerCase();
                this.logger.debug(`Looking for passed test: '${testName}'`);
                const childTest = testMap.get(testName);
                if (childTest) {
                    run.started(childTest);
                    run.passed(childTest);
                    foundResults = true;
                    this.logger.debug(`Marked ${childTest.label} as passed`);
                }
                continue;
            }

            const failMatch = failRegex.exec(trimmedLine);
            if (failMatch) {
                const testName = failMatch[1].trim().toLowerCase();
                this.logger.debug(`Looking for failed test: '${testName}'`);
                const childTest = testMap.get(testName);
                if (childTest) {
                    run.started(childTest);
                    run.failed(childTest, new vscode.TestMessage('Test failed'));
                    foundResults = true;
                }
            }
        }

        this.logger.debug(`Found results: ${foundResults}`);

        // If we couldn't parse individual results, mark the whole test
        if (!foundResults) {
            if (allPassed) {
                run.passed(test);
            } else {
                run.failed(test, new vscode.TestMessage('Test failed'));
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

/**
 * Plugin Tests - VS Code Extension
 * 
 * Test runner for Unraid plugin development with proper multi-root workspace support.
 * Supports BATS (Bash Automated Testing System) and PHPUnit tests.
 */

import * as vscode from 'vscode';
import { BatsTestProvider } from './providers/batsProvider';
import { PhpUnitTestProvider } from './providers/phpunitProvider';
import { Logger } from './utils/logger';

let batsProvider: BatsTestProvider | undefined;
let phpunitProvider: PhpUnitTestProvider | undefined;

export function activate(context: vscode.ExtensionContext): void {
    const logger = new Logger('Plugin Tests');
    logger.info('Activating Plugin Tests extension');

    const config = vscode.workspace.getConfiguration('pluginTests');

    // Initialize BATS test provider
    if (config.get<boolean>('bats.enabled', true)) {
        try {
            batsProvider = new BatsTestProvider(context, logger);
            context.subscriptions.push(batsProvider);
            logger.info('BATS test provider initialized');
        } catch (error) {
            logger.error('Failed to initialize BATS test provider', error);
        }
    }

    // Initialize PHPUnit test provider
    if (config.get<boolean>('phpunit.enabled', true)) {
        try {
            phpunitProvider = new PhpUnitTestProvider(context, logger);
            context.subscriptions.push(phpunitProvider);
            logger.info('PHPUnit test provider initialized');
        } catch (error) {
            logger.error('Failed to initialize PHPUnit test provider', error);
        }
    }

    // Listen for configuration changes
    context.subscriptions.push(
        vscode.workspace.onDidChangeConfiguration(e => {
            if (e.affectsConfiguration('pluginTests')) {
                logger.info('Configuration changed, reloading providers');
                // Reload providers as needed
            }
        })
    );

    logger.info('Plugin Tests extension activated');
}

export function deactivate(): void {
    batsProvider?.dispose();
    phpunitProvider?.dispose();
}

/**
 * Logger utility for the Plugin Tests extension
 */

import * as vscode from 'vscode';

export class Logger {
    private outputChannel: vscode.OutputChannel;
    private prefix: string;

    constructor(name: string) {
        this.outputChannel = vscode.window.createOutputChannel(name);
        this.prefix = `[${name}]`;
    }

    info(message: string, ...args: unknown[]): void {
        this.log('INFO', message, ...args);
    }

    warn(message: string, ...args: unknown[]): void {
        this.log('WARN', message, ...args);
    }

    error(message: string, ...args: unknown[]): void {
        this.log('ERROR', message, ...args);
    }

    debug(message: string, ...args: unknown[]): void {
        this.log('DEBUG', message, ...args);
    }

    private log(level: string, message: string, ...args: unknown[]): void {
        const timestamp = new Date().toISOString();
        const formatted = args.length > 0 
            ? `${timestamp} ${this.prefix} [${level}] ${message} ${JSON.stringify(args)}`
            : `${timestamp} ${this.prefix} [${level}] ${message}`;
        this.outputChannel.appendLine(formatted);
    }

    show(): void {
        this.outputChannel.show();
    }

    dispose(): void {
        this.outputChannel.dispose();
    }
}

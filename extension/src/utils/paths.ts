/**
 * Path utilities for handling multi-root workspaces correctly
 */

import * as vscode from 'vscode';
import * as path from 'path';
import * as os from 'os';

/**
 * Get the workspace folder containing a given URI
 */
export function getWorkspaceFolder(uri: vscode.Uri): vscode.WorkspaceFolder | undefined {
    return vscode.workspace.getWorkspaceFolder(uri);
}

/**
 * Get the absolute path for a file, handling all workspace scenarios
 */
export function getAbsolutePath(uri: vscode.Uri): string {
    return uri.fsPath;
}

/**
 * Get a path relative to its workspace folder (not the workspace root)
 * This is the key fix for multi-root workspace support
 */
export function getRelativeToWorkspaceFolder(uri: vscode.Uri): string {
    const workspaceFolder = getWorkspaceFolder(uri);
    if (workspaceFolder) {
        return path.relative(workspaceFolder.uri.fsPath, uri.fsPath);
    }
    return uri.fsPath;
}

/**
 * Convert a Windows path to Docker mount format
 * e.g., C:\Users\nick\project -> /c/Users/nick/project
 */
export function toDockerPath(windowsPath: string): string {
    if (os.platform() !== 'win32') {
        return windowsPath;
    }
    
    // Convert backslashes to forward slashes
    let dockerPath = windowsPath.replace(/\\/g, '/');
    
    // Convert drive letter (C: -> /c)
    if (/^[A-Za-z]:/.test(dockerPath)) {
        dockerPath = '/' + dockerPath[0].toLowerCase() + dockerPath.substring(2);
    }
    
    return dockerPath;
}

/**
 * Check if Docker is available on the system
 */
export async function isDockerAvailable(): Promise<boolean> {
    const { exec } = await import('child_process');
    return new Promise((resolve) => {
        exec('docker --version', (error) => {
            resolve(!error);
        });
    });
}

/**
 * Normalize path separators to forward slashes (for display/Docker)
 */
export function normalizePath(p: string): string {
    return p.replace(/\\/g, '/');
}

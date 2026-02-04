<?php

/**
 * Plugin Tests Framework - Helper Functions
 * 
 * Utility functions for testing Unraid plugins.
 */

declare(strict_types=1);

/**
 * Safely include a PHP file that may have executable code (like switch($_POST['action'])).
 * 
 * Many Unraid plugin files have this pattern:
 * 
 *   function myFunction() { ... }
 *   switch($_POST['action']) {
 *     case 'doSomething': myFunction(); break;
 *   }
 * 
 * This function:
 * 1. Sets $_POST['action'] to a non-matching value so switch statements fall through
 * 2. Uses output buffering to capture any accidental echo/print
 * 3. Includes the file so functions/classes become available
 * 4. Restores the original $_POST state
 * 
 * @param string $path The file path (Unraid path or local path)
 * @return string Any output captured during inclusion (usually empty)
 * 
 * @example
 *   // Load exec.php which has switch($_POST['action'])
 *   includeWithSwitch('/usr/local/emhttp/plugins/myplugin/php/exec.php');
 *   
 *   // Now you can call functions defined in exec.php
 *   $result = myFunction();
 */
function includeWithSwitch(string $path): string
{
    // Save original POST state
    $originalPost = $_POST;
    
    // Set action to something that won't match any case
    $_POST['action'] = '__PHPUNIT_SAFE_INCLUDE__';
    
    // Capture any output
    ob_start();
    
    // Include the file - functions get defined, switch falls through
    require_once $path;
    
    $output = ob_get_clean();
    
    // Restore original POST
    $_POST = $originalPost;
    
    return $output ?: '';
}

/**
 * Safely include a PHP file that may have executable code with specific POST vars.
 * 
 * Like includeWithSwitch() but allows setting custom POST variables during include.
 * 
 * @param string $path The file path
 * @param array<string, mixed> $postVars POST variables to set during include
 * @return string Any output captured during inclusion
 * 
 * @example
 *   // Include with specific action (will execute that case)
 *   $output = includeWithPost('/path/to/exec.php', ['action' => 'getStatus']);
 */
function includeWithPost(string $path, array $postVars = []): string
{
    // Save original POST state
    $originalPost = $_POST;
    
    // Set POST vars
    $_POST = $postVars;
    
    // Capture any output
    ob_start();
    
    // Include the file
    require_once $path;
    
    $output = ob_get_clean();
    
    // Restore original POST
    $_POST = $originalPost;
    
    return $output ?: '';
}

/**
 * Execute a specific action case in an already-included file.
 * 
 * If the file is already included and you want to trigger a specific switch case,
 * this won't work because require_once won't re-execute. Use this pattern instead:
 * 
 * @param callable $action The code to execute with specific POST vars
 * @param array<string, mixed> $postVars POST variables to set
 * @return string Any output captured
 * 
 * @example
 *   $output = withPost(['action' => 'getStatus', 'id' => '123'], function() {
 *       // This code runs with $_POST['action'] = 'getStatus'
 *       include '/path/to/exec.php'; // Use include, not require_once
 *   });
 */
function withPost(array $postVars, callable $action): string
{
    $originalPost = $_POST;
    $_POST = $postVars;
    
    ob_start();
    $action();
    $output = ob_get_clean();
    
    $_POST = $originalPost;
    
    return $output ?: '';
}

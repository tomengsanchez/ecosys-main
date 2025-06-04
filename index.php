<?php
// Main entry point of the application

// Include configuration
require_once 'config.php';

// Autoload controllers and models (simple autoloader)
spl_autoload_register(function ($className) {
    $paths = [
        'app/controllers/',
        'app/models/'
    ];
    foreach ($paths as $path) {
        $file = __DIR__ . '/' . $path . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// --- Basic Routing ---
// Get the requested path from the URL
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$basePath = trim(BASE_URL, '/'); // BASE_URL is defined in config.php

// Remove base path from request path if present
if (!empty($basePath) && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$requestPath = trim($requestPath, '/');

$segments = $requestPath ? explode('/', $requestPath) : [];

// --- Determine Controller and Action ---
$controllerName = '';
$actionName = '';
$params = [];

if (empty($segments)) {
    // No segments in URL (e.g., accessing the root like /mainsystem/)
    if (isLoggedIn()) {
        $controllerName = 'DashboardController'; // Default for logged-in users
        $actionName = 'index';
    } else {
        $controllerName = 'AuthController'; // Default for logged-out users
        $actionName = 'login';
    }
} else {
    // At least one segment (controller specified)
    // Ensure the first letter of the segment is capitalized for the controller name.
    $controllerName = ucfirst($segments[0]) . 'Controller'; 
    
    $actionName = !empty($segments[1]) ? $segments[1] : 'index'; // Preserve original case for action

    // Collect remaining segments as parameters
    if (count($segments) > 2) {
        $params = array_slice($segments, 2);
    }
}

error_log("Router determined: Controller='{$controllerName}', Action='{$actionName}' from Segments: " . implode('/', $segments));

// --- Controller Instantiation and Action Execution ---
$controllerFile = __DIR__ . '/app/controllers/' . $controllerName . '.php';
error_log("Router attempting to load controller file: '{$controllerFile}'");

if (file_exists($controllerFile)) {
    error_log("Controller file '{$controllerFile}' exists.");
    // require_once $controllerFile; // Autoloader should handle this. Explicit require can be a fallback.

    if (class_exists($controllerName)) {
        error_log("Class '{$controllerName}' exists.");
        $controller = new $controllerName($pdo); 
        
        $resolvedActionName = '';
        $actionExists = false;

        $availableMethods = get_class_methods($controller);
        error_log("Methods available in '{$controllerName}': " . implode(', ', $availableMethods));

        error_log("Checking for method '{$actionName}' (original case)...");
        if (method_exists($controller, $actionName)) {
            $resolvedActionName = $actionName;
            $actionExists = true;
            error_log("Found method '{$resolvedActionName}' with original case.");
        } else {
            error_log("Method '{$actionName}' (original case) NOT found. Checking lowercase...");
            $lowercaseActionName = strtolower($actionName);
            if (method_exists($controller, $lowercaseActionName)) { 
                $resolvedActionName = $lowercaseActionName;
                $actionExists = true;
                error_log("Found method '{$resolvedActionName}' with lowercase.");
            } else {
                error_log("Method '{$lowercaseActionName}' (lowercase) also NOT found.");
            }
        }

        if ($actionExists) {
            error_log("Dispatching to '{$controllerName}->{$resolvedActionName}'.");
            call_user_func_array([$controller, $resolvedActionName], $params);
        } else {
            error_log("Action '{$actionName}' (and variants) not found in controller '{$controllerName}'.");
            header("HTTP/1.0 404 Not Found");
            echo "<h1>404 Not Found</h1><p>The page you requested could not be found (action method missing).</p>";
            echo "<p>Controller: {$controllerName}, Attempted Action: {$actionName}</p>";
            echo "<p>Please check server error logs for more details.</p>";
        }
    } else {
        error_log("Controller class '{$controllerName}' NOT found in file '{$controllerFile}' after checking existence.");
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller class missing).</p>";
        echo "<p>Controller: {$controllerName}</p>";
        echo "<p>Please check server error logs for more details.</p>";
    }
} else {
    error_log("Controller file not found: '{$controllerFile}'. Requested Path: '{$requestPath}'");
    if (!isLoggedIn() && strtolower($controllerName) !== 'authcontroller') { // Ensure AuthController itself isn't caught here if file is missing
        redirect('auth/login');
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller file missing).</p>";
        echo "<p>Requested Path: {$requestPath}</p>";
        echo "<p>Attempted Controller File: {$controllerFile}</p>";
        echo "<p>Please check server error logs for more details.</p>";
    }
}

?>

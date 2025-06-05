<?php
// Main entry point of the application

// Include configuration FIRST
require_once 'config.php';

// --- Maintenance Mode Check ---
$showMaintenancePage = false;

// 1. Check the primary override constant from config.php
if (defined('MAINTENANCE_MODE_ENABLED') && MAINTENANCE_MODE_ENABLED === true) {
    $showMaintenancePage = true;
} else {
    // 2. If constant is not true, check the database option, but only if $pdo is available
    if (isset($pdo) && $pdo !== null) { // Check if DB connection was attempted and successful
        // It's important that OptionModel can be loaded here.
        // The autoloader is registered later, so we might need to explicitly include it
        // if it's not already handled by config.php or an early include.
        // For this structure, assuming spl_autoload_register in config.php handles models.
        if (class_exists('OptionModel')) {
            try {
                $optionModel = new OptionModel($pdo);
                $dbMaintenanceMode = $optionModel->getOption('maintenance_mode');
                if ($dbMaintenanceMode === 'on') {
                    $showMaintenancePage = true;
                }
            } catch (Exception $e) {
                // Log error if OptionModel or DB access fails, but don't break if we can't check DB option
                error_log("Maintenance Mode Check: Could not access OptionModel or DB. Error: " . $e->getMessage());
                // In this case, site operation continues unless MAINTENANCE_MODE_ENABLED was true.
            }
        } else {
            error_log("Maintenance Mode Check: OptionModel class not found. Cannot check DB 'maintenance_mode' option. Ensure autoloader in config.php covers models or include OptionModel.php before this check if necessary.");
        }
    } else {
        // $pdo is null, meaning DB connection wasn't successful OR 
        // MAINTENANCE_MODE_ENABLED was true and config.php skipped DB connection.
        // If MAINTENANCE_MODE_ENABLED was false, this implies a DB connection issue.
        // The critical error for $pdo === null is handled further down before controller instantiation.
        // For this maintenance check, if $pdo is null here, we rely on the constant only.
    }
}

// 3. Admin Bypass for Maintenance Mode
// This check happens after $showMaintenancePage might have been set to true by either the constant or DB option.
if ($showMaintenancePage) {
    // Session should be started by config.php
    // isLoggedIn() and $_SESSION['user_role'] are dependent on session state
    if (isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $showMaintenancePage = false; // Admin bypasses maintenance mode
        error_log("Maintenance mode bypassed for admin user: " . ($_SESSION['user_login'] ?? 'ID: ' . $_SESSION['user_id']));
    }
}


if ($showMaintenancePage) {
    $maintenancePagePath = __DIR__ . '/app/views/maintenance_page.php';
    if (file_exists($maintenancePagePath)) {
        require_once $maintenancePagePath;
    } else {
        // Basic fallback if maintenance_page.php is missing
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 3600'); // 1 hour
        echo "<h1>Site Under Maintenance</h1><p>We are currently performing scheduled maintenance. Please check back soon.</p>";
    }
    exit; // Stop further script execution
}

// Autoload controllers and models (simple autoloader)
// This autoloader is crucial for class_exists('OptionModel') to work reliably above if not included earlier.
// Consider moving a generic autoloader to be included very early in config.php or ensure OptionModel is explicitly required in config.php before its use.
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

// --- Controller Instantiation and Action Execution ---
$controllerFile = __DIR__ . '/app/controllers/' . $controllerName . '.php';

if (file_exists($controllerFile)) {
    // Autoloader should handle the require_once for $controllerFile

    if (class_exists($controllerName)) {
        // Ensure $pdo is passed to the controller constructor
        // $pdo is initialized in config.php
        if ($pdo === null && !$showMaintenancePage) { // Ensure we are not already in (bypassed) maintenance mode
            // This case means DB connection failed in config.php and we are NOT in explicit maintenance mode
            // AND the DB option for maintenance wasn't checked or was 'off', and admin didn't bypass.
            header('HTTP/1.1 503 Service Temporarily Unavailable');
            echo "<h1>Service Unavailable</h1><p>The website is currently experiencing technical difficulties. Please try again later.</p>";
            error_log("Critical Error: PDO object is null in index.php, but not in maintenance mode. DB connection likely failed in config.php.");
            exit;
        }
        $controller = new $controllerName($pdo); 
        
        $resolvedActionName = '';
        $actionExists = false;

        // Check for method with original case and then lowercase
        if (method_exists($controller, $actionName)) {
            $resolvedActionName = $actionName;
            $actionExists = true;
        } else {
            $lowercaseActionName = strtolower($actionName);
            if (method_exists($controller, $lowercaseActionName)) { 
                $resolvedActionName = $lowercaseActionName;
                $actionExists = true;
            }
        }

        if ($actionExists) {
            call_user_func_array([$controller, $resolvedActionName], $params);
        } else {
            error_log("Action '{$actionName}' (and variants) not found in controller '{$controllerName}'. Requested Path: '{$requestPath}'");
            header("HTTP/1.0 404 Not Found");
            echo "<h1>404 Not Found</h1><p>The page you requested could not be found (action method missing).</p>";
            // Optionally, load a dedicated 404 view
            // include __DIR__ . '/app/views/errors/404.php';
        }
    } else {
        error_log("Controller class '{$controllerName}' NOT found in file '{$controllerFile}' after checking existence. Requested Path: '{$requestPath}'");
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller class missing).</p>";
        // Optionally, load a dedicated 404 view
    }
} else {
    error_log("Controller file not found: '{$controllerFile}'. Requested Path: '{$requestPath}'");
    if (!isLoggedIn() && strtolower($controllerName) !== 'authcontroller') {
        // Before redirecting to login, ensure AuthController itself is not the one being requested to avoid loops
        // This check might need refinement based on your routing for AuthController
        redirect('auth/login');
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller file missing).</p>";
        // Optionally, load a dedicated 404 view
    }
}

?>

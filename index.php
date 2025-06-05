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
    if (isset($pdo) && $pdo !== null) { 
        if (class_exists('OptionModel')) {
            try {
                $optionModel = new OptionModel($pdo);
                $dbMaintenanceMode = $optionModel->getOption('maintenance_mode');
                if ($dbMaintenanceMode === 'on') {
                    $showMaintenancePage = true;
                }
            } catch (Exception $e) {
                error_log("Maintenance Mode Check: Could not access OptionModel or DB. Error: " . $e->getMessage());
            }
        } else {
            error_log("Maintenance Mode Check: OptionModel class not found. Cannot check DB 'maintenance_mode' option.");
        }
    }
}

// 3. Admin Bypass for Maintenance Mode
if ($showMaintenancePage) {
    if (isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $showMaintenancePage = false; 
        // Optional: Log admin bypass
        // error_log("Maintenance mode bypassed for admin user: " . ($_SESSION['user_login'] ?? 'ID: ' . $_SESSION['user_id']));
    }
}

if ($showMaintenancePage) {
    $maintenancePagePath = __DIR__ . '/app/views/maintenance_page.php';
    if (file_exists($maintenancePagePath)) {
        require_once $maintenancePagePath;
    } else {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 3600'); 
        echo "<h1>Site Under Maintenance</h1><p>We are currently performing scheduled maintenance. Please check back soon.</p>";
    }
    exit; 
}

// --- Debug Mode Check (after maintenance and admin bypass) ---
if (isset($pdo) && $pdo !== null) { 
    if (class_exists('OptionModel')) {
        try {
            $optionModel = new OptionModel($pdo);
            $debugModeOptionKey = defined('SITE_DEBUG_MODE_OPTION_KEY') ? SITE_DEBUG_MODE_OPTION_KEY : 'site_debug_mode';
            $defaultDebugMode = defined('DEFAULT_SITE_DEBUG_MODE') ? DEFAULT_SITE_DEBUG_MODE : 'off';
            
            $debugModeSetting = $optionModel->getOption($debugModeOptionKey, $defaultDebugMode);

            if ($debugModeSetting === 'on') {
                if (!defined('SYSTEM_DEBUG_MONITOR_ENABLED')) {
                    define('SYSTEM_DEBUG_MONITOR_ENABLED', true);
                }
                 error_reporting(E_ALL);
                 ini_set('display_errors', 1);
                 // Optional: Log that debug mode is on
                 // error_log("System Debug Monitor ENABLED via database setting.");
            }
        } catch (Exception $e) {
            error_log("Debug Mode Check: Could not access OptionModel or DB for debug setting. Error: " . $e->getMessage());
        }
    } else {
        error_log("Debug Mode Check: OptionModel class not found. Cannot check DB 'site_debug_mode' option.");
    }
}


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
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$basePath = trim(BASE_URL, '/'); 

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
    if (isLoggedIn()) {
        $controllerName = 'DashboardController'; 
        $actionName = 'index';
    } else {
        $controllerName = 'AuthController'; 
        $actionName = 'login';
    }
} else {
    $controllerName = ucfirst($segments[0]) . 'Controller'; 
    $actionName = !empty($segments[1]) ? $segments[1] : 'index'; 
    if (count($segments) > 2) {
        $params = array_slice($segments, 2);
    }
}

// --- Controller Instantiation and Action Execution ---
$controllerFile = __DIR__ . '/app/controllers/' . $controllerName . '.php';

if (file_exists($controllerFile)) {
    if (class_exists($controllerName)) {
        if ($pdo === null && !$showMaintenancePage && !(defined('SYSTEM_DEBUG_MONITOR_ENABLED') && SYSTEM_DEBUG_MONITOR_ENABLED === true && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ) { 
            header('HTTP/1.1 503 Service Temporarily Unavailable');
            echo "<h1>Service Unavailable</h1><p>The website is currently experiencing technical difficulties. Please try again later.</p>";
            error_log("Critical Error: PDO object is null in index.php, but not in maintenance or (admin) debug mode. DB connection likely failed in config.php.");
            exit;
        }
        $controller = new $controllerName($pdo); 
        
        $resolvedActionName = '';
        $actionExists = false;

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
        }
    } else {
        error_log("Controller class '{$controllerName}' NOT found in file '{$controllerFile}' after checking existence. Requested Path: '{$requestPath}'");
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller class missing).</p>";
    }
} else {
    error_log("Controller file not found: '{$controllerFile}'. Requested Path: '{$requestPath}'");
    if (!isLoggedIn() && strtolower($controllerName) !== 'authcontroller') {
        redirect('auth/login');
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found (controller file missing).</p>";
    }
}

?>

<?php
/**
 * Database Configuration, Session Management, and Role/Capability Definitions
 */

// ** MySQL settings ** //
define('DB_NAME', 'mainsystem');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('BASE_URL', '/mainsystem/'); 

// Default Timezone (PHP requires this to be set for date/time functions)
// You might want to make this a site setting as well in the future.
date_default_timezone_set('Asia/Manila'); // Example: Philippines timezone

// Default Time Format if not set in options
define('DEFAULT_TIME_FORMAT', 'Y-m-d H:i'); // Example: 2025-06-02 15:30

$pdo = null; 
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (\PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Could not connect to the database. Please check your configuration. Error: " . $e->getMessage());
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

spl_autoload_register(function ($className) {
    $modelPath = __DIR__ . '/app/models/' . $className . '.php';
    if (file_exists($modelPath)) {
        require_once $modelPath;
    }
});


function redirect($url) {
    header("Location: " . BASE_URL . ltrim($url, '/'));
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function generateBreadcrumbs($breadcrumbs = []) {
    if (empty($breadcrumbs)) return '';
    $html = '<nav aria-label="breadcrumb" class="mt-3 mb-3"><ol class="breadcrumb">';
    $count = count($breadcrumbs);
    foreach ($breadcrumbs as $index => $crumb) {
        $isActive = ($index === $count - 1);
        $label = htmlspecialchars($crumb['label']);
        if ($isActive) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
        } else {
            $url = isset($crumb['url']) ? BASE_URL . ltrim($crumb['url'], '/') : '#';
            $html .= '<li class="breadcrumb-item"><a href="' . $url . '">' . $label . '</a></li>';
        }
    }
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Get the site-wide configured time format.
 *
 * @global PDO $pdo
 * @return string The time format string.
 */
function get_site_time_format() {
    global $pdo;
    static $siteTimeFormat = null;

    if ($siteTimeFormat !== null) {
        return $siteTimeFormat;
    }

    if (!class_exists('OptionModel')) {
        $modelPath = __DIR__ . '/app/models/OptionModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { 
            error_log("OptionModel class not found in get_site_time_format(). Using default.");
            $siteTimeFormat = DEFAULT_TIME_FORMAT;
            return $siteTimeFormat;
        }
    }
    
    $optionModel = new OptionModel($pdo);
    $formatFromDb = $optionModel->getOption('site_time_format');

    if ($formatFromDb) {
        $siteTimeFormat = $formatFromDb;
    } else {
        $siteTimeFormat = DEFAULT_TIME_FORMAT;
    }
    return $siteTimeFormat;
}

/**
 * Format a datetime string for display using the site's configured time format.
 *
 * @param string $datetimeString The datetime string to format (e.g., from database).
 * @param string|null $customFormat Optional custom format string to override site setting.
 * @return string The formatted datetime string, or the original string if input is invalid.
 */
function format_datetime_for_display($datetimeString, $customFormat = null) {
    if (empty($datetimeString) || $datetimeString === '0000-00-00 00:00:00') {
        return 'N/A'; // Or an empty string, or however you want to handle invalid/empty dates
    }
    try {
        $formatToUse = $customFormat ?: get_site_time_format();
        $date = new DateTime($datetimeString); // Assumes $datetimeString is in a format DateTime can parse
        return $date->format($formatToUse);
    } catch (Exception $e) {
        error_log("Error formatting date '{$datetimeString}': " . $e->getMessage());
        return htmlspecialchars($datetimeString); // Return original on error, safely escaped
    }
}


// --- Role and Capability Management ---

define('CAPABILITIES', [
    'ACCESS_ADMIN_PANEL' => 'Access Admin Panel',
    'MANAGE_USERS' => 'Manage Users (Add, Edit, Delete)',
    'MANAGE_ROLES' => 'Manage Roles (Add, Edit, Delete)', 
    'MANAGE_ROLES_PERMISSIONS' => 'Manage Roles & Permissions',
    'MANAGE_DEPARTMENTS' => 'Manage Departments',
    'MANAGE_SITE_SETTINGS' => 'Manage Site Settings',
    'VIEW_REPORTS' => 'View Reports (Example)',
    'MANAGE_OPEN_OFFICE_RESERVATIONS' => 'Manage Open Office Reservations', 
    'MANAGE_ROOMS' => 'Manage Rooms (CRUD)', 
    'MANAGE_IT_REQUESTS' => 'Manage IT Requests',
    'MANAGE_RAP_CALENDAR' => 'Manage Rap Calendar',
    'MANAGE_SES_DATA' => 'Manage SES Data',
    'MANAGE_DTR' => 'Manage DTR Records',
    'MANAGE_ASSETS' => 'Manage Assets',
]);

function getDefinedRoles() {
    global $pdo; 
    static $rolesCache = null; 

    if ($rolesCache !== null) {
        return $rolesCache;
    }

    if (!class_exists('RoleModel')) {
        $modelPath = __DIR__ . '/app/models/RoleModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { error_log("RoleModel class not found in getDefinedRoles()."); return []; }
    }
    
    $roleModel = new RoleModel($pdo);
    $dbRoles = $roleModel->getAllRoles('role_key', 'ASC'); 
    
    $formattedRoles = [];
    if ($dbRoles) {
        foreach ($dbRoles as $role) {
            $formattedRoles[$role['role_key']] = $role['role_name'];
        }
    }
    $rolesCache = $formattedRoles;
    return $rolesCache;
}

function userHasCapability($capability) {
    global $pdo; 

    if (!isLoggedIn()) {
        return false;
    }
    $userRole = $_SESSION['user_role'] ?? 'user'; 

    if (!class_exists('RolePermissionModel')) {
        $modelPath = __DIR__ . '/app/models/RolePermissionModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { error_log("RolePermissionModel class not found in userHasCapability()."); return false; }
    }
    
    if (!class_exists('RoleModel')) {
        $modelPathRole = __DIR__ . '/app/models/RoleModel.php';
        if (file_exists($modelPathRole)) require_once $modelPathRole;
    }
    
    if (class_exists('RolePermissionModel')) {
        $rolePermissionModel = new RolePermissionModel($pdo);
        return $rolePermissionModel->roleHasCapability($userRole, $capability);
    }
    
    error_log("RolePermissionModel class does not exist after attempting to load for userHasCapability().");
    return false; 
}

?>

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

$pdo = null; // This will be our global PDO object
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

// Autoloader for models (if not already in index.php or a central bootstrap file)
// This is important for functions in this config file that might need models.
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

// --- Role and Capability Management ---

/**
 * Define all available capabilities in the system.
 * This list remains the master definition of what capabilities *can* exist.
 */
define('CAPABILITIES', [
    'ACCESS_ADMIN_PANEL' => 'Access Admin Panel',
    'MANAGE_USERS' => 'Manage Users (Add, Edit, Delete)',
    'MANAGE_ROLES' => 'Manage Roles (Add, Edit, Delete)', // New capability for managing roles themselves
    'MANAGE_ROLES_PERMISSIONS' => 'Manage Roles & Permissions',
    'MANAGE_DEPARTMENTS' => 'Manage Departments',
    'MANAGE_SITE_SETTINGS' => 'Manage Site Settings',
    'VIEW_REPORTS' => 'View Reports (Example)',
    'MANAGE_OPEN_OFFICE_RESERVATIONS' => 'Manage Open Office Reservations',
    'MANAGE_IT_REQUESTS' => 'Manage IT Requests',
    'MANAGE_RAP_CALENDAR' => 'Manage Rap Calendar',
    'MANAGE_SES_DATA' => 'Manage SES Data',
    'MANAGE_DTR' => 'Manage DTR Records',
    'MANAGE_ASSETS' => 'Manage Assets',

]);

// REMOVED: define('DEFINED_ROLES', [...]); // Roles are now fetched from the database.

/**
 * Get all defined roles from the database.
 * Returns an associative array [role_key => role_name]
 * @return array
 */
function getDefinedRoles() {
    global $pdo; // Access the global PDO object
    static $rolesCache = null; // Simple static cache for the request

    if ($rolesCache !== null) {
        return $rolesCache;
    }

    if (!class_exists('RoleModel')) {
        // Autoloader should handle this, but as a fallback for config context:
        $modelPath = __DIR__ . '/app/models/RoleModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { error_log("RoleModel class not found in getDefinedRoles()."); return []; }
    }
    
    $roleModel = new RoleModel($pdo);
    $dbRoles = $roleModel->getAllRoles('role_key', 'ASC'); // Get all roles, ordered by key
    
    $formattedRoles = [];
    if ($dbRoles) {
        foreach ($dbRoles as $role) {
            $formattedRoles[$role['role_key']] = $role['role_name'];
        }
    }
    $rolesCache = $formattedRoles;
    return $rolesCache;
}


/**
 * Check if the currently logged-in user has a specific capability.
 * This function now queries the database via RolePermissionModel.
 *
 * @param string $capability The capability key (e.g., 'MANAGE_USERS').
 * @return bool True if the user has the capability, false otherwise.
 */
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
    
    if (class_exists('RolePermissionModel')) {
        $rolePermissionModel = new RolePermissionModel($pdo);
        return $rolePermissionModel->roleHasCapability($userRole, $capability);
    }
    
    error_log("RolePermissionModel class does not exist after attempting to load for userHasCapability().");
    return false; 
}

?>

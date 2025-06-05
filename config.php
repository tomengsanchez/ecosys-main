<?php
// sleep(3);
/**
 * Database Configuration, Session Management, Role/Capability, Email Definitions, and PHPMailer Setup
 */

// --- PHPMailer ---
// Make sure you have run 'composer install' in your project root.
// This will create a 'vendor' directory with PHPMailer.
require_once __DIR__ . '/vendor/autoload.php'; // Loads PHPMailer and other Composer packages

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ** MySQL settings ** //
define('DB_NAME', 'maindb2');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('BASE_URL', '/mainsystem/'); 
define('VIEWS_DIR', __DIR__ . '/app/views/');

// Define a basic site name for use if DB is down during maintenance check
define('SITE_NAME_BASIC', 'Mainsystem');

// Default Timezone
date_default_timezone_set('Asia/Manila'); 

// Default Time Format if not set in options
define('DEFAULT_TIME_FORMAT', 'Y-m-d H:i');

// --- Email Configuration (General & PHPMailer SMTP for Gmail) ---
define('DEFAULT_SITE_EMAIL_FROM', 'your-email@gmail.com'); // Your "From" email address
define('DEFAULT_ADMIN_EMAIL_NOTIFICATIONS', 'your-admin-email@example.com'); // Where admin notifications go
define('DEFAULT_EMAIL_NOTIFICATIONS_ENABLED', 'on'); 

// PHPMailer SMTP Settings
// Example for Microsoft Outlook / Microsoft 365:
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_USERNAME', 'your-email@yourdomain.com'); // Your Microsoft 365 email
define('SMTP_PASSWORD', 'your-m365-password-or-app-password'); // Your M365 password or App Password if MFA is enabled
define('SMTP_PORT', 587);
define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS);
define('SMTP_AUTH', true);
define('SMTP_DEBUG_LEVEL', SMTP::DEBUG_OFF); // Set to SMTP::DEBUG_SERVER for troubleshooting


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
    // If DB connection fails here, we might want to show a generic error or a maintenance page
    // For now, let the script die, or handle specifically if maintenance mode logic needs DB for admin bypass.
    error_log("Critical Database Connection Error in config.php: " . $e->getMessage());
    // Display a very basic error if DB is down, can't even check maintenance mode without it if admin bypass relies on DB.
    // However, maintenance mode check below might need OptionModel which needs $pdo.
    // If $pdo is null, OptionModel instantiation will fail.
    // The maintenance page itself should be standalone.
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

spl_autoload_register(function ($className) {
    $modelPath = __DIR__ . '/app/models/' . $className . '.php';
    if (file_exists($modelPath)) {
        require_once $modelPath;
    }
    // Add controller path as well if not already handled by index.php autoloader (it is)
    // $controllerPath = __DIR__ . '/app/controllers/' . $className . '.php';
    // if (file_exists($controllerPath)) {
    //     require_once $controllerPath;
    // }
});

// --- MAINTENANCE MODE CHECK ---
// This check should run early.
// Ensure $pdo is available if OptionModel is used.
if (isset($pdo) && $pdo !== null) { // Only proceed if DB connection was successful
    try {
        $optionModelForMaintenance = new OptionModel($pdo);
        $maintenanceMode = $optionModelForMaintenance->getOption('maintenance_mode', 'off');

        if (strtolower($maintenanceMode) === 'on') {
            // Check if user is admin (simple check, can be enhanced with a specific capability)
            $isAdmin = false;
            if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                $isAdmin = true;
            }
            
            // Allow access for logged-in admins.
            // Also, allow access to the login page so admins can log in.
            // And allow access to the admin panel for admins to turn off maintenance mode.
            $isLoginPage = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], BASE_URL . 'auth/login') !== false);
            $isAdminAccessingAdminArea = ($isAdmin && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], BASE_URL . 'admin') !== false);


            if (!$isAdmin || (!$isLoginPage && !$isAdminAccessingAdminArea && $isAdmin)) { // If maintenance is on AND (user is not admin OR (admin is not on login/admin page))
                 // A more refined check for admin access:
                 // if (!$isAdmin || ($isAdmin && !( $isLoginPage || $isAdminAccessingAdminArea ) ) ) {
                 // Simpler for now: if maintenance mode on, only allow admin for any page.
                 // Non-admins see maintenance page.
                 // Admins can browse freely (including admin panel to turn it off, and login page to login).
                 // However, if an admin is trying to access a non-admin, non-login page, should they see maintenance?
                 // Usually, admins bypass maintenance mode entirely for the whole site.
                 // Let's adjust: if it's maintenance mode, and you're NOT an admin, you see the page.
                 // Exception: if it's the login page, anyone can see it.

                $allowedDuringMaintenance = false;
                if ($isLoginPage) {
                    $allowedDuringMaintenance = true; // Everyone can see login page
                } elseif ($isAdmin) {
                    $allowedDuringMaintenance = true; // Admins can see everything
                }


                if (!$allowedDuringMaintenance) {
                    // Load the maintenance view.
                    // Ensure $GLOBALS['pdo'] is available if maintenance_page.php tries to use it.
                    $GLOBALS['pdo_for_maintenance'] = $pdo; // Make pdo available to maintenance page if it tries to get site name
                    
                    // To prevent issues if header.php is included by mistake by a view that tries to load it,
                    // we define a constant.
                    define('MAINTENANCE_MODE_ACTIVE', true);

                    require_once VIEWS_DIR . 'maintenance_page.php';
                    exit;
                }
            }
        }
    } catch (PDOException $e) {
        // If DB error occurs while checking maintenance mode, log it but don't break site entirely yet,
        // unless it's critical. The main routing might still work for static pages or if DB comes back.
        // However, this means maintenance mode check might fail to engage.
        error_log("Error checking maintenance mode: " . $e->getMessage());
        // Fallback: If DB is down during this check, perhaps show maintenance page by default?
        // For now, let it proceed, other parts of app will fail if DB is truly down.
    }
} else if ($pdo === null) {
    // If $pdo is null from the initial connection attempt, it means DB is down.
    // It's probably best to show a generic error or a simplified maintenance message.
    // For now, this case means the maintenance mode *check* itself cannot run robustly.
    // The main application routing will likely fail later if it needs the DB.
    // Let's consider a very basic fallback if DB connection itself failed earlier.
    // This is a bit of a catch-22 if the maintenance mode *setting* is in the DB.
    // For this scenario, we'll assume if $pdo isn't set, the app will fail later anyway.
    // The `maintenance_page.php` itself doesn't strictly need $pdo.
}


// --- Helper Functions (isLoggedIn, userHasCapability, etc.) ---

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

function get_site_time_format() {
    global $pdo;
    static $siteTimeFormat = null;

    if ($siteTimeFormat !== null) {
        return $siteTimeFormat;
    }
     if (!$pdo) { // Check if $pdo is null
        error_log("PDO object is null in get_site_time_format(). Using default time format.");
        $siteTimeFormat = DEFAULT_TIME_FORMAT;
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
    
    try {
        $optionModel = new OptionModel($pdo);
        $formatFromDb = $optionModel->getOption('site_time_format');

        if ($formatFromDb) {
            $siteTimeFormat = $formatFromDb;
        } else {
            $siteTimeFormat = DEFAULT_TIME_FORMAT;
        }
    } catch (PDOException $e) {
        error_log("Database error in get_site_time_format(): " . $e->getMessage() . ". Using default time format.");
        $siteTimeFormat = DEFAULT_TIME_FORMAT;
    }
    return $siteTimeFormat;
}

function format_datetime_for_display($datetimeString, $customFormat = null) {
    if (empty($datetimeString) || $datetimeString === '0000-00-00 00:00:00') {
        return 'N/A'; 
    }
    try {
        $formatToUse = $customFormat ?: get_site_time_format();
        $date = new DateTime($datetimeString); 
        return $date->format($formatToUse);
    } catch (Exception $e) {
        error_log("Error formatting date '{$datetimeString}': " . $e->getMessage());
        return htmlspecialchars($datetimeString); 
    }
}

/**
 * Sends an email using PHPMailer with SMTP configuration.
 * (Function body remains the same as previously defined)
 */
function send_system_email($to, $subject, $message, $isHtml = false, $attachments = [], $ccAddresses = [], $bccAddresses = []) {
    global $pdo; 

    if (!$pdo) { // Check if $pdo is null
        error_log("PDO object is null in send_system_email(). Email not sent.");
        return false;
    }


    if (!class_exists('OptionModel')) {
        $modelPath = __DIR__ . '/app/models/OptionModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { 
            error_log("OptionModel class not found in send_system_email(). Email not sent.");
            return false;
        }
    }
    
    $optionModel = new OptionModel($pdo); // $pdo should be available here

    $notificationsEnabled = $optionModel->getOption('site_email_notifications_enabled', DEFAULT_EMAIL_NOTIFICATIONS_ENABLED);
    if (strtolower($notificationsEnabled) !== 'on') {
        error_log("Email notifications are disabled. Email to {$to} with subject '{$subject}' not sent.");
        return false; 
    }

    $siteName = $optionModel->getOption('site_name', 'Mainsystem'); 
    $fromEmail = SMTP_USERNAME; 
    $fromName = $siteName; 

    $mail = new PHPMailer(true); 

    try {
        $mail->SMTPDebug = SMTP_DEBUG_LEVEL;          
        $mail->isSMTP();                              
        $mail->Host       = SMTP_HOST;                
        $mail->SMTPAuth   = SMTP_AUTH;                
        $mail->Username   = SMTP_USERNAME;            
        $mail->Password   = SMTP_PASSWORD;            
        $mail->SMTPSecure = SMTP_SECURE;              
        $mail->Port       = SMTP_PORT;                

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to); 
        
        if (!empty($ccAddresses)) {
            foreach ($ccAddresses as $cc) {
                $mail->addCC($cc);
            }
        }
        if (!empty($bccAddresses)) {
            foreach ($bccAddresses as $bcc) {
                $mail->addBCC($bcc);
            }
        }

        if (!empty($attachments)) {
            foreach ($attachments as $attachmentPath) {
                if (file_exists($attachmentPath)) {
                    $mail->addAttachment($attachmentPath); 
                } else {
                    error_log("PHPMailer: Attachment file not found - {$attachmentPath}");
                }
            }
        }

        $mail->isHTML($isHtml); 
        $mail->Subject = "[{$siteName}] " . $subject; 
        $mail->Body    = $message;
        if (!$isHtml) {
            $mail->AltBody = strip_tags($message); 
        }

        $mail->send();
        error_log("PHPMailer: Email successfully sent to {$to} with subject '[{$siteName}] {$subject}'.");
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer: Message could not be sent. Mailer Error: {$mail->ErrorInfo}. Exception: {$e->getMessage()}");
        return false;
    }
}


// --- Role and Capability Management ---
define('CAPABILITIES', [
    // General Admin
    'ACCESS_ADMIN_PANEL' => 'Access Admin Panel',
    'MANAGE_USERS' => 'Manage Users (Add, Edit, Delete)',
    'MANAGE_ROLES' => 'Manage Roles (Add, Edit, Delete)', 
    'MANAGE_ROLES_PERMISSIONS' => 'Manage Roles & Permissions',
    'MANAGE_DEPARTMENTS' => 'Manage Departments',
    'MANAGE_SITE_SETTINGS' => 'Manage Site Settings',
    'VIEW_REPORTS' => 'View Reports (Example)',
    
    // Open Office - Rooms (Physical Entities)
    'MANAGE_ROOMS' => 'Manage All Aspects of Rooms (Legacy/Super)',
    'VIEW_ROOMS'   => 'View Rooms List',
    'CREATE_ROOMS' => 'Create New Rooms',
    'EDIT_ROOMS'   => 'Edit Existing Rooms',
    'DELETE_ROOMS' => 'Delete Rooms',
    
    // Open Office - Room Reservations
    'CREATE_ROOM_RESERVATIONS' => 'Create Own Room Reservations',
    'EDIT_OWN_ROOM_RESERVATIONS' => 'Edit Own Pending Room Reservations', 
    'CANCEL_OWN_ROOM_RESERVATIONS' => 'Cancel Own Pending Room Reservations',
    'VIEW_ALL_ROOM_RESERVATIONS' => 'View All Room Reservations', 
    'APPROVE_DENY_ROOM_RESERVATIONS' => 'Approve/Deny Room Reservations', 
    'EDIT_ANY_ROOM_RESERVATION' => 'Edit Any Room Reservation', 
    'DELETE_ANY_ROOM_RESERVATION' => 'Delete Any Room Reservation Record', 

    // Open Office - Vehicles
    'VIEW_VEHICLES'   => 'View Vehicles List',
    'CREATE_VEHICLES' => 'Create New Vehicles',
    'EDIT_VEHICLES'   => 'Edit Existing Vehicles',
    'DELETE_VEHICLES' => 'Delete Vehicles',
    
    // --- NEW: Vehicle Reservation Capabilities ---
    'CREATE_VEHICLE_RESERVATIONS' => 'Create Own Vehicle Reservations',
    'EDIT_OWN_VEHICLE_RESERVATIONS' => 'Edit Own Pending Vehicle Reservations', 
    'CANCEL_OWN_VEHICLE_RESERVATIONS' => 'Cancel Own Pending Vehicle Reservations',
    'VIEW_ALL_VEHICLE_RESERVATIONS' => 'View All Vehicle Reservations', 
    'APPROVE_DENY_VEHICLE_RESERVATIONS' => 'Approve/Deny Vehicle Reservations', 
    'EDIT_ANY_VEHICLE_RESERVATION' => 'Edit Any Vehicle Reservation', 
    'DELETE_ANY_VEHICLE_RESERVATION' => 'Delete Any Vehicle Reservation Record', 
    // --- END NEW ---

    // Other Modules (examples, can be expanded)
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
     if (!$pdo) { // Check if $pdo is null
        error_log("PDO object is null in getDefinedRoles(). Returning empty roles array.");
        return [];
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

     if (!$pdo) { // Check if $pdo is null
        error_log("PDO object is null in userHasCapability(). Denying capability.");
        return false;
    }


    if (!class_exists('RolePermissionModel')) {
        $modelPath = __DIR__ . '/app/models/RolePermissionModel.php';
        if (file_exists($modelPath)) require_once $modelPath;
        else { error_log("RolePermissionModel class not found in userHasCapability()."); return false; }
    }
    
    if (!class_exists('RoleModel')) {
        $modelPathRole = __DIR__ . '/app/models/RoleModel.php';
        if (file_exists($modelPathRole)) require_once $modelPathRole;
    }
    
    // If the user is an 'admin', grant all capabilities by default for now.
    // This is a common approach, but for stricter control, every capability should be explicitly assigned.
    if ($userRole === 'admin') {
        // error_log("User '{$_SESSION['user_login']}' has role 'admin', granting capability '{$capability}'.");
        return true; // Admins have all capabilities by default in this simplified check.
    }


    if (class_exists('RolePermissionModel')) {
        $rolePermissionModel = new RolePermissionModel($pdo);
        return $rolePermissionModel->roleHasCapability($userRole, $capability);
    }
    
    error_log("RolePermissionModel class does not exist after attempting to load for userHasCapability().");
    return false; 
}

?>

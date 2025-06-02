<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mainsystem/');
}
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePathStripped = rtrim(BASE_URL, '/');

// Helper function to check if a nav link is active
function isActive($linkPath, $currentPath, $basePathStripped) {
    $fullLinkPath = $basePathStripped . '/' . ltrim($linkPath, '/');
    if ($linkPath === '' && ($currentPath === $basePathStripped . '/' || $currentPath === $basePathStripped)) { // Home/Base URL
        return true;
    }
    if ($linkPath !== '' && strpos($currentPath, $fullLinkPath) === 0) {
        if ($currentPath === $fullLinkPath || strpos($currentPath, $fullLinkPath . '?') === 0 || strpos($currentPath, $fullLinkPath . '/') === 0) {
            return true;
        }
    }
    return false;
}

// Helper function to check if a dropdown section is active
function isDropdownSectionActive($sectionPrefix, $currentPath, $basePathStripped) {
    $fullSectionPrefix = $basePathStripped . '/' . ltrim($sectionPrefix, '/');
     if (strpos($currentPath, $fullSectionPrefix) === 0) {
        return true;
    }
    return false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'My Application'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" xintegrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { padding-top: 70px; /* Adjusted for potentially taller navbar */ }
        .breadcrumb { background-color: #f8f9fa; padding: 0.75rem 1rem; border-radius: 0.25rem; margin-bottom: 1rem; }
        .main-content { min-height: calc(100vh - 70px - 70px - 3rem); /* Full viewport - navbar - footer - margins */ }
        footer { line-height: 1; }
        .navbar-nav .dropdown-menu { min-width: 200px; } /* Ensure dropdowns have enough width */
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>"><i class="fas fa-cogs me-2"></i>Mainsystem</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('dashboard', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'dashboard'; ?>">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>

                    <?php 
                    // Determine if Open Office dropdown should be shown
                    // Users can see "Manage Rooms" if they have MANAGE_ROOMS
                    // Users can see "Room Reservations" (admin view) if they have MANAGE_OPEN_OFFICE_RESERVATIONS
                    // All logged-in users can see "My Reservations"
                    $showOpenOfficeDropdown = userHasCapability('MANAGE_ROOMS') || 
                                              userHasCapability('MANAGE_OPEN_OFFICE_RESERVATIONS') ||
                                              isLoggedIn(); // Simplified: if logged in, "My Reservations" is available.
                                              
                    if ($showOpenOfficeDropdown):
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('openoffice', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="openOfficeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-door-open me-1"></i>Open Office
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="openOfficeDropdown">
                            <?php if (userHasCapability('MANAGE_ROOMS')): ?>
                                <li><a class="dropdown-item <?php echo isActive('openoffice/rooms', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'openoffice/rooms'; ?>">Manage Rooms</a></li>
                            <?php endif; ?>
                            
                            <?php if (userHasCapability('MANAGE_OPEN_OFFICE_RESERVATIONS')): ?>
                                <li><a class="dropdown-item <?php echo isActive('openoffice/roomreservations', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'openoffice/roomreservations'; ?>">Room Reservations (Admin)</a></li>
                            <?php endif; ?>
                            
                            <?php if (isLoggedIn()): // All logged-in users can see their own reservations ?>
                                <li><a class="dropdown-item <?php echo isActive('openoffice/myreservations', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'openoffice/myreservations'; ?>">My Reservations</a></li>
                            <?php endif; ?>
                            
                            <?php /* Example for future items
                            <?php if (userHasCapability('MANAGE_VEHICLE_RESERVATIONS')): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?php echo isActive('openoffice/vehicles', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'openoffice/vehicles'; ?>">Vehicle Reservation</a></li>
                            <?php endif; ?>
                             <?php if (userHasCapability('MANAGE_SERVICE_REQUESTS')): ?>
                                <li><a class="dropdown-item <?php echo isActive('openoffice/services', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'openoffice/services'; ?>">Service Request</a></li>
                            <?php endif; ?>
                            */ ?>
                        </ul>
                    </li>
                    <?php endif; ?>


                    <?php if (userHasCapability('MANAGE_IT_REQUESTS')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('it', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="itDepartmentDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-desktop me-1"></i>IT Department
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="itDepartmentDropdown">
                            <li><a class="dropdown-item <?php echo isActive('it/requests', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'it/requests'; ?>">Requests</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if (userHasCapability('MANAGE_RAP_CALENDAR')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('rap', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="rapDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-calendar-alt me-1"></i>Rap
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="rapDropdown">
                            <li><a class="dropdown-item <?php echo isActive('rap/calendar', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'rap/calendar'; ?>">Calendar Of Activities</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if (userHasCapability('MANAGE_SES_DATA')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('ses', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="sesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chart-bar me-1"></i>SES
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="sesDropdown">
                            <li><a class="dropdown-item <?php echo isActive('ses/data', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'ses/data'; ?>">SES Data</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                 <?php if (isLoggedIn()): ?>
                    <?php 
                    if (userHasCapability('ACCESS_ADMIN_PANEL')): 
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('admin', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-shield me-1"></i>Admin
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item <?php echo isActive('admin', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/users', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/departments', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/siteSettings', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/roleAccessSettings', $currentPath, $basePathStripped) &&
                                                                    !isDropdownSectionActive('admin/listRoles', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/dtr', $currentPath, $basePathStripped) && 
                                                                    !isDropdownSectionActive('admin/assets', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin'; ?>">Admin Dashboard</a></li>
                                
                                <?php if (userHasCapability('MANAGE_USERS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/users', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/users'; ?>">Employees</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_DEPARTMENTS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/departments', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/departments'; ?>">Departments</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_ROLES')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/listRoles', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/listRoles'; ?>">Manage Roles</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_ROLES_PERMISSIONS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/roleAccessSettings', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/roleAccessSettings'; ?>">Role Permissions</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_SITE_SETTINGS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/siteSettings', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/siteSettings'; ?>">Site Settings</a></li>
                                <?php endif; ?>
                                
                                <?php if (userHasCapability('MANAGE_DTR') || userHasCapability('MANAGE_ASSETS')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_DTR')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/dtr', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/dtr'; ?>">DTR</a></li>
                                <?php endif; ?>
                                <?php if (userHasCapability('MANAGE_ASSETS')): ?>
                                    <li><a class="dropdown-item <?php echo isActive('admin/assets', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/assets'; ?>">Assets</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userProfileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userProfileDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-edit me-1"></i>Profile (Soon)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL . 'auth/logout'; ?>"><i class="fas fa-sign-out-alt me-1"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('auth/login', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'auth/login'; ?>">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5"> 
    <?php
    if (isset($breadcrumbs) && is_array($breadcrumbs)) {
        echo generateBreadcrumbs($breadcrumbs);
    }
    ?>
    <div class="main-content">

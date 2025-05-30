<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mainsystem/');
}
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePathStripped = rtrim(BASE_URL, '/');

// Helper function to check if a nav link is active
function isActive($linkPath, $currentPath, $basePathStripped) {
    $fullLinkPath = $basePathStripped . '/' . ltrim($linkPath, '/');
    if ($linkPath === '' && $currentPath === $basePathStripped . '/') { // Home/Base URL
        return true;
    }
    if ($linkPath !== '' && strpos($currentPath, $fullLinkPath) === 0) {
        return true;
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
    <title><?php echo $pageTitle ?? 'My Application'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" xintegrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>">Mainsystem</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('dashboard', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'dashboard'; ?>">Dashboard</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('openoffice', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="openOfficeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Open Office
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="openOfficeDropdown">
                            <li><a class="dropdown-item <?php echo isActive('openoffice/rooms', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'openoffice/rooms'; ?>">Rooms Reservation</a></li>
                            <li><a class="dropdown-item <?php echo isActive('openoffice/vehicles', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'openoffice/vehicles'; ?>">Vehicle Reservation</a></li>
                            <li><a class="dropdown-item <?php echo isActive('openoffice/services', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'openoffice/services'; ?>">Service Request</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('it', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="itDepartmentDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            IT Department
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="itDepartmentDropdown">
                            <li><a class="dropdown-item <?php echo isActive('it/requests', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'it/requests'; ?>">Requests</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('rap', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="rapDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Rap
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="rapDropdown">
                            <li><a class="dropdown-item <?php echo isActive('rap/calendar', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'rap/calendar'; ?>">Calendar Of Activities</a></li>
                        </ul>
                    </li>

                     <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('ses', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="sesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            SES
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="sesDropdown">
                            <li><a class="dropdown-item <?php echo isActive('ses/data', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'ses/data'; ?>">SES Data</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                 <?php if (isLoggedIn()): ?>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo isDropdownSectionActive('admin', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Admin
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item <?php echo isActive('admin', $currentPath, $basePathStripped) && !strpos($currentPath, '/users') && !strpos($currentPath, '/departments') && !strpos($currentPath, '/dtr') && !strpos($currentPath, '/assets') && !strpos($currentPath, '/siteSettings') ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin'; ?>">Admin Dashboard</a></li>
                                <li><a class="dropdown-item <?php echo isActive('admin/users', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/users'; ?>">Employees</a></li>
                                <li><a class="dropdown-item <?php echo isActive('admin/departments', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/departments'; ?>">Departments</a></li>
                                <li><a class="dropdown-item <?php echo isActive('admin/siteSettings', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/siteSettings'; ?>">Site Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?php echo isActive('admin/dtr', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/dtr'; ?>">DTR</a></li>
                                <li><a class="dropdown-item <?php echo isActive('admin/assets', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin/assets'; ?>">Assets</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userProfileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userProfileDropdown">
                            <li><a class="dropdown-item" href="#">Profile (Soon)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL . 'auth/logout'; ?>"><i class="fas fa-sign-out-alt me-1"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('auth/login', $currentPath, $basePathStripped) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'auth/login'; ?>">Login</a>
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
        
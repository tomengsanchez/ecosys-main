<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/mainsystem/');
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

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>">Mainsystem</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) == rtrim(BASE_URL, '/')) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>">Home</a>
                </li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), BASE_URL . 'dashboard') === 0) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'dashboard'; ?>">Dashboard</a>
                    </li>
                    <?php 
                    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): 
                    ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), BASE_URL . 'admin') === 0) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin'; ?>">Admin Panel</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                            <li><a class="dropdown-item" href="#">Profile (Soon)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL . 'auth/logout'; ?>">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), BASE_URL . 'auth/login') === 0) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'auth/login'; ?>">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5"> 
    <?php
    // Display breadcrumbs if the $breadcrumbs variable is set
    if (isset($breadcrumbs) && is_array($breadcrumbs)) {
        echo generateBreadcrumbs($breadcrumbs);
    }
    ?>
    <div class="main-content">
        
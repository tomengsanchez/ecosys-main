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
                    // Check if the logged-in user is an admin (user_id == 1 for now)
                    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1): 
                    ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), BASE_URL . 'admin') === 0) ? 'active' : ''; ?>" href="<?php echo BASE_URL . 'admin'; ?>">Admin Panel</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL . 'auth/logout'; ?>">Logout (<?php echo htmlspecialchars($_SESSION['display_name'] ?? ''); ?>)</a>
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

<div class="container mt-4">
    <div class="main-content">
        
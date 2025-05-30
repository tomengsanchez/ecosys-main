<?php
// Secure BASE_URL by ensuring it's defined. If not, default to a safe value or handle error.
if (!defined('BASE_URL')) {
    // Fallback or error handling if BASE_URL is not defined in config.php
    // This is a security measure, though config.php should always be included first.
    define('BASE_URL', '/mainsystem/'); // Default, but should match your config.php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'My Application'; // Page title can be passed from controller ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        .container { width: 80%; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        header nav { background-color: #333; color: #fff; padding: 10px 0; text-align: center; }
        header nav ul { list-style-type: none; padding: 0; margin: 0; }
        header nav ul li { display: inline; margin-right: 20px; }
        header nav ul li a { color: #fff; text-decoration: none; font-weight: bold; }
        header nav ul li a:hover { text-decoration: underline; }
        .main-content { padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="email"] { width: calc(100% - 22px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .form-group .error-message { color: red; font-size: 0.9em; margin-top: 5px; }
        .btn { background-color: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background-color: #4cae4c; }
        .btn-danger { background-color: #d9534f; }
        .btn-danger:hover { background-color: #c9302c; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-danger { color: #a94442; background-color: #f2dede; border-color: #ebccd1; }
        .alert-success { color: #3c763d; background-color: #dff0d8; border-color: #d6e9c6; }
        footer { text-align: center; padding: 20px; margin-top: 30px; background-color: #333; color: #fff; }
    </style>
</head>
<body>

<header>
    <nav>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
            <?php if (isLoggedIn()): ?>
                <li><a href="<?php echo BASE_URL . 'dashboard'; ?>">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL . 'auth/logout'; ?>">Logout (<?php echo htmlspecialchars($_SESSION['display_name'] ?? ''); ?>)</a></li>
            <?php else: ?>
                <li><a href="<?php echo BASE_URL . 'auth/login'; ?>">Login</a></li>
                <?php endif; ?>
        </ul>
    </nav>
</header>

<div class="container">
    <div class="main-content">
        
<?php
// pageTitle and welcomeMessage are passed from DashboardController's $data array
// and extracted into variables by the view() method.

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="dashboard-container">
    <h2><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h2>

    <?php if (isset($welcomeMessage)): ?>
        <p class="alert alert-success"><?php echo htmlspecialchars($welcomeMessage); ?></p>
    <?php endif; ?>

    <p>This is your main dashboard area. You are successfully logged in!</p>

    <p>From here, you could:</p>
    <ul>
        <li>Manage your profile (if a profile page is created).</li>
        <li>View site statistics.</li>
        <li>Access administrative tools (if applicable).</li>
        <li>Create or manage content (if this is a CMS).</li>
    </ul>

    <h4>Session Information (for demonstration):</h4>
    <pre style="background-color: #eee; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
<?php
// Display some session information for debugging/demonstration
if (isset($_SESSION)) {
    print_r($_SESSION);
}
?>
    </pre>

    <p>
        <a href="<?php echo BASE_URL . 'auth/logout'; ?>" class="btn btn-danger">Logout</a>
    </p>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

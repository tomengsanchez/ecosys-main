<?php
// pageTitle and welcomeMessage are passed from AdminController's $data array

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-dashboard-container">
    <h1 class="mb-4"><?php echo htmlspecialchars($pageTitle ?? 'Admin Dashboard'); ?></h1>

    <?php
    // Display any session messages (e.g., success/error after an action)
    if (isset($_SESSION['admin_message'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_SESSION['admin_message']) . '</div>';
        unset($_SESSION['admin_message']); // Clear message after displaying
    }
    ?>

    <?php if (isset($welcomeMessage)): ?>
        <p class="lead"><?php echo htmlspecialchars($welcomeMessage); ?></p>
    <?php endif; ?>

    <p>This is the main administration area. From here you can manage various aspects of the site.</p>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Manage Users</h5>
                    <p class="card-text">View, edit, and manage user accounts.</p>
                    <a href="<?php echo BASE_URL . 'admin/users'; ?>" class="btn btn-primary">Go to Users</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Content Management</h5>
                    <p class="card-text">Manage site content, posts, pages, etc.</p>
                    <a href="#" class="btn btn-primary disabled">Manage Content (Soon)</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Site Settings</h5>
                    <p class="card-text">Configure global site settings.</p>
                    <a href="#" class="btn btn-primary disabled">Site Settings (Soon)</a>
                </div>
            </div>
        </div>
    </div>

    <h4 class="mt-5">Session Information (for Admin demonstration):</h4>
    <pre class="bg-light p-3 border rounded">
<?php
// Display some session information for debugging/demonstration
if (isset($_SESSION)) {
    print_r($_SESSION);
}
?>
    </pre>

</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

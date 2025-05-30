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
        $alertType = (strpos(strtolower($_SESSION['admin_message']), 'error') === false && strpos(strtolower($_SESSION['admin_message']), 'fail') === false && strpos(strtolower($_SESSION['admin_message']), 'cannot') === false) ? 'success' : 'danger';
        echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['admin_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['admin_message']); 
    }
     if (isset($_SESSION['error_message'])) { // For general errors from controller constructor, like permission denied
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['error_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['error_message']); 
    }
    ?>

    <?php if (isset($welcomeMessage)): ?>
        <p class="lead"><?php echo htmlspecialchars($welcomeMessage); ?></p>
    <?php endif; ?>

    <p>This is the main administration area. From here you can manage various aspects of the site.</p>

    <div class="row mt-4 g-3">
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="fas fa-users-cog me-2"></i>Manage Users</h5>
                    <p class="card-text">View, edit, add, and manage user accounts, roles, and departments.</p>
                    <a href="<?php echo BASE_URL . 'admin/users'; ?>" class="btn btn-primary mt-auto">Go to Users</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="fas fa-building me-2"></i>Manage Departments</h5>
                    <p class="card-text">Create, edit, and manage departments for user assignment.</p>
                    <a href="<?php echo BASE_URL . 'admin/departments'; ?>" class="btn btn-primary mt-auto">Go to Departments</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="fas fa-file-alt me-2"></i>Content Management</h5>
                    <p class="card-text">Manage site content, posts, pages, etc. (Coming Soon)</p>
                    <a href="#" class="btn btn-secondary disabled mt-auto">Manage Content</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="fas fa-cogs me-2"></i>Site Settings</h5>
                    <p class="card-text">Configure global site settings. (Coming Soon)</p>
                    <a href="#" class="btn btn-secondary disabled mt-auto">Site Settings</a>
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

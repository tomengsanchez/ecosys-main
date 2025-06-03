<?php
// This view is used by AdminController::users()
// Expected variables:
// - $pageTitle (string)
// - $breadcrumbs (array)
// User data is now loaded via AJAX by DataTables

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-users-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Users'); ?></h1>
        <?php if (userHasCapability('MANAGE_USERS')): // Ensure add button also checks capability ?>
        <a href="<?php echo BASE_URL . 'admin/addUser'; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New User
        </a>
        <?php endif; ?>
    </div>

    <?php
    // Session messages display
    if (isset($_SESSION['admin_message'])) {
        $alertType = (strpos(strtolower($_SESSION['admin_message']), 'error') === false && 
                      strpos(strtolower($_SESSION['admin_message']), 'fail') === false && 
                      strpos(strtolower($_SESSION['admin_message']), 'cannot') === false) ? 'success' : 'danger';
        echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['admin_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['admin_message']); 
    }
    if (isset($_SESSION['error_message'])) { 
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['error_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['error_message']); 
    }
    ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover" id="usersTable"> <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Display Name</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Registered</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        "processing": true, 
        "serverSide": false, // Client-side processing for now
        "ajax": {
            "url": "<?php echo BASE_URL . 'admin/ajaxGetUsers'; ?>",
            "type": "GET",
            "dataSrc": "data" 
        },
        "columns": [
            { "data": "id" },
            { "data": "username" },
            { "data": "email" },
            { "data": "display_name" },
            { "data": "role" },
            { "data": "department" },
            { "data": "registered" },
            { "data": "status" },
            { 
                "data": "actions",
                "orderable": false,
                "searchable": false
            }
        ],
        "order": [[ 0, "desc" ]] // Default sort by ID descending
    });
});
</script>

<?php
// pageTitle and roles array are passed from AdminController's listRoles() method

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-roles-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Roles'); ?></h1>
        <a href="<?php echo BASE_URL . 'admin/addRole'; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Role
        </a>
    </div>

    <?php
    // Display any session messages
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
    ?>

    <?php if (!empty($roles) && is_array($roles)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Role Key</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>System Role?</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($role['role_id']); ?></td>
                            <td><code><?php echo htmlspecialchars($role['role_key']); ?></code></td>
                            <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($role['role_description'] ?? 'N/A')); ?></td>
                            <td>
                                <?php if ($role['is_system_role']): ?>
                                    <span class="badge bg-info text-dark">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(format_datetime_for_display($role['created_at'])); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL . 'admin/editRole/' . htmlspecialchars($role['role_id']); ?>" class="btn btn-sm btn-primary me-1" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php if (!$role['is_system_role']): // Prevent deleting system roles ?>
                                    <a href="<?php echo BASE_URL . 'admin/deleteRole/' . htmlspecialchars($role['role_id']); ?>" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete the role &quot;<?php echo htmlspecialchars(addslashes($role['role_name'])); ?>&quot;? This will also remove its permissions and reassign users to the default role.');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-danger" disabled title="System roles cannot be deleted">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No roles found. You can add one using the button above.</div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

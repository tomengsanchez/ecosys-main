<?php
// pageTitle and departments array (with user_count) are passed from AdminController's departments() method

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-departments-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Manage Departments'); ?></h1>
        <a href="<?php echo BASE_URL . 'admin/addDepartment'; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Department
        </a>
    </div>

    <?php
    // Display any session messages
    if (isset($_SESSION['admin_message'])) {
        $alertType = (strpos(strtolower($_SESSION['admin_message']), 'error') === false && strpos(strtolower($_SESSION['admin_message']), 'fail') === false && strpos(strtolower($_SESSION['admin_message']), 'cannot') === false) ? 'success' : 'danger';
        echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['admin_message']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
        unset($_SESSION['admin_message']); 
    }
    ?>

    <?php if (!empty($departments) && is_array($departments)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Department Name</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $department): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($department['department_id']); ?></td>
                            <td><?php echo htmlspecialchars($department['department_name']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($department['department_description'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars($department['user_count'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($department['created_at']))); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL . 'admin/editDepartment/' . htmlspecialchars($department['department_id']); ?>" class="btn btn-sm btn-primary me-1" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php 
                                // Optionally, disable delete if users are in the department,
                                // though ON DELETE SET NULL handles this at DB level.
                                // This is more of a UI hint.
                                $deleteDisabled = ($department['user_count'] > 0) ? 'disabled' : '';
                                $deleteTitle = ($department['user_count'] > 0) ? 'Cannot delete: department has users. Unassign users first or they will be set to NULL.' : 'Delete';
                                if ($department['user_count'] > 0 && false) { // Change 'false' to 'true' to enable this stricter UI check
                                     // This part is an example if you want stricter UI control
                                     // For now, we rely on ON DELETE SET NULL
                                }
                                ?>
                                <a href="<?php echo BASE_URL . 'admin/deleteDepartment/' . htmlspecialchars($department['department_id']); ?>" 
                                   class="btn btn-sm btn-danger <?php // echo $deleteDisabled; // Uncomment to visually disable if users exist ?>" 
                                   title="<?php // echo $deleteTitle; // Uncomment for dynamic title ?>"
                                   onclick="return confirm('Are you sure you want to delete the department &quot;<?php echo htmlspecialchars(addslashes($department['department_name'])); ?>&quot;? Users in this department will be unassigned.');">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No departments found. You can add one using the button above.</div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

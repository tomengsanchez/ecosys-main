<?php
// pageTitle, definedRoles, allCapabilities, currentRoleCapabilities, and breadcrumbs
// are passed from AdminController's roleAccessSettings() method.

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-role-access-settings-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Role Access Settings'); ?></h1>
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

    <form action="<?php echo BASE_URL . 'admin/roleAccessSettings'; ?>" method="POST">
        <?php if (!empty($definedRoles) && is_array($definedRoles)): ?>
            <?php foreach ($definedRoles as $roleKey => $roleLabel): ?>
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Role: <?php echo htmlspecialchars($roleLabel); ?> (<code><?php echo htmlspecialchars($roleKey); ?></code>)</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($roleKey === 'admin' && isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1): ?>
                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle"></i> The 'Admin' role (especially for User ID 1) has full system access by default. Permissions displayed here reflect defined capabilities, but the primary admin inherently has all rights. Modifying these checkboxes for the 'admin' role might not restrict the primary admin if core logic grants them full access. For safety, the system may enforce that the 'admin' role always has all capabilities.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($allCapabilities) && is_array($allCapabilities)): ?>
                            <div class="row">
                                <?php foreach ($allCapabilities as $capabilityKey => $capabilityLabel): ?>
                                    <?php
                                    $isChecked = isset($currentRoleCapabilities[$roleKey]) && in_array($capabilityKey, $currentRoleCapabilities[$roleKey]);
                                    // For the 'admin' role, especially if it's the primary admin, consider always checking them
                                    // or disabling the checkboxes to indicate they are non-modifiable here.
                                    $isDisabled = ($roleKey === 'admin'); // Example: Disable editing for 'admin' role via this UI
                                    
                                    // More refined: if current user is primary admin, they can edit other roles but not cripple their own 'admin' role.
                                    // $isDisabled = ($roleKey === 'admin' && !($_SESSION['user_id'] == 1 && $_SESSION['user_role'] === 'admin'));
                                    ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="permissions[<?php echo htmlspecialchars($roleKey); ?>][]" 
                                                   value="<?php echo htmlspecialchars($capabilityKey); ?>" 
                                                   id="cap_<?php echo htmlspecialchars($roleKey) . '_' . htmlspecialchars($capabilityKey); ?>"
                                                   <?php echo $isChecked ? 'checked' : ''; ?>
                                                   <?php // echo $isDisabled ? 'disabled' : ''; // Uncomment to disable for admin role ?>
                                                   >
                                            <label class="form-check-label" for="cap_<?php echo htmlspecialchars($roleKey) . '_' . htmlspecialchars($capabilityKey); ?>">
                                                <?php echo htmlspecialchars($capabilityLabel); ?>
                                                <small class="text-muted">(<code><?php echo htmlspecialchars($capabilityKey); ?></code>)</small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No capabilities defined in the system.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="mt-4 d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Save All Permissions</button>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No roles are currently defined.</div>
        <?php endif; ?>
    </form>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

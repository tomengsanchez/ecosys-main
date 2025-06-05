<?php
// pageTitle, definedRoles, categorizedCapabilities, currentRoleCapabilities, and breadcrumbs
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
            <div class="accordion" id="rolesAccordion">
                <?php $firstRole = true; ?>
                <?php foreach ($definedRoles as $roleKey => $roleLabel): ?>
                    <?php $accordionItemId = 'role_accordion_item_' . htmlspecialchars($roleKey); ?>
                    <?php $accordionCollapseId = 'collapse_' . htmlspecialchars($roleKey); ?>
                    <div class="accordion-item mb-2 shadow-sm">
                        <h2 class="accordion-header" id="heading_<?php echo htmlspecialchars($roleKey); ?>">
                            <button class="accordion-button <?php echo !$firstRole ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $accordionCollapseId; ?>" aria-expanded="<?php echo $firstRole ? 'true' : 'false'; ?>" aria-controls="<?php echo $accordionCollapseId; ?>">
                                <strong><?php echo htmlspecialchars($roleLabel); ?></strong> (<code><?php echo htmlspecialchars($roleKey); ?></code>)
                            </button>
                        </h2>
                        <div id="<?php echo $accordionCollapseId; ?>" class="accordion-collapse collapse <?php echo $firstRole ? 'show' : ''; ?>" aria-labelledby="heading_<?php echo htmlspecialchars($roleKey); ?>" data-bs-parent="#rolesAccordion">
                            <div class="accordion-body">
                                <?php if ($roleKey === 'admin' && isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1): ?>
                                    <div class="alert alert-info small p-2 mb-3">
                                        <i class="fas fa-info-circle"></i> The 'Admin' role has full system access. Permissions displayed reflect defined capabilities, but the primary admin inherently has all rights. For safety, the system enforces that the 'admin' role always has all capabilities.
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($categorizedCapabilities) && is_array($categorizedCapabilities)): ?>
                                    <?php $categoryIndex = 0; ?>
                                    <?php foreach ($categorizedCapabilities as $categoryName => $capabilitiesInCategory): ?>
                                        <div class="mb-3">
                                            <h6 class="text-primary"><?php echo htmlspecialchars($categoryName); ?></h6>
                                            <div class="row">
                                                <?php foreach ($capabilitiesInCategory as $capabilityKey => $capabilityLabel): ?>
                                                    <?php
                                                    $isChecked = isset($currentRoleCapabilities[$roleKey]) && in_array($capabilityKey, $currentRoleCapabilities[$roleKey]);
                                                    $isDisabled = ($roleKey === 'admin'); 
                                                    if ($isDisabled) {
                                                        $isChecked = true; 
                                                    }
                                                    ?>
                                                    <div class="col-md-6 col-lg-4">
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input" type="checkbox" 
                                                                   name="permissions[<?php echo htmlspecialchars($roleKey); ?>][]" 
                                                                   value="<?php echo htmlspecialchars($capabilityKey); ?>" 
                                                                   id="cap_<?php echo htmlspecialchars($roleKey) . '_' . htmlspecialchars($capabilityKey); ?>"
                                                                   <?php echo $isChecked ? 'checked' : ''; ?>
                                                                   <?php echo $isDisabled ? 'disabled' : ''; ?>
                                                                   >
                                                            <label class="form-check-label" for="cap_<?php echo htmlspecialchars($roleKey) . '_' . htmlspecialchars($capabilityKey); ?>">
                                                                <?php echo htmlspecialchars($capabilityLabel); ?>
                                                                <small class="text-muted">(<code><?php echo htmlspecialchars($capabilityKey); ?></code>)</small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php $categoryIndex++; ?>
                                            <?php if ($categoryIndex < count($categorizedCapabilities)): ?>
                                                <hr class="my-2">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No capabilities defined in the system.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php $firstRole = false; ?>
                <?php endforeach; ?>
            </div>
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

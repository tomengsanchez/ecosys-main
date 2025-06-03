<?php
// pageTitle, errors, and role data (role_id, role_key, role_name, role_description, is_system_role)
// are passed from AdminController's addRole() or editRole() methods.

// Determine if we are editing or adding
$isEditing = isset($role_id) && !empty($role_id);
$formAction = $isEditing ? BASE_URL . 'admin/editRole/' . $role_id : BASE_URL . 'admin/addRole';

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7 col-xl-6">
        <div class="card shadow-sm mt-4 mb-5">
            <div class="card-body p-4">
                <h1 class="card-title text-center mb-4"><?php echo htmlspecialchars($pageTitle ?? ($isEditing ? 'Edit Role' : 'Add New Role')); ?></h1>

                <?php
                // Display general form errors if any
                if (!empty($errors['form_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($errors['form_err']) . '</div>';
                }
                ?>

                <form action="<?php echo $formAction; ?>" method="POST">
                    
                    <div class="mb-3">
                        <label for="role_key" class="form-label">Role Key <span class="text-danger">*</span></label>
                        <input type="text" name="role_key" id="role_key" class="form-control <?php echo (!empty($errors['role_key_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($role_key ?? ''); ?>" 
                               <?php echo $isEditing ? 'readonly' : 'required'; // Role key is not editable after creation ?>>
                        <?php if ($isEditing): ?>
                            <small class="form-text text-muted">The Role Key cannot be changed after creation.</small>
                        <?php else: ?>
                             <small class="form-text text-muted">Unique identifier (e.g., 'new_manager', 'support_level_1'). Lowercase letters, numbers, and underscores only.</small>
                        <?php endif; ?>
                        <?php if (!empty($errors['role_key_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['role_key_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="role_name" class="form-label">Role Name (Display Name) <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" id="role_name" class="form-control <?php echo (!empty($errors['role_name_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($role_name ?? ''); ?>" required>
                        <?php if (!empty($errors['role_name_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['role_name_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="role_description" class="form-label">Description</label>
                        <textarea name="role_description" id="role_description" class="form-control <?php echo (!empty($errors['role_description_err'])) ? 'is-invalid' : ''; ?>"
                                  rows="3"><?php echo htmlspecialchars($role_description ?? ''); ?></textarea>
                        <?php if (!empty($errors['role_description_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['role_description_err']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_system_role" id="is_system_role" class="form-check-input <?php echo (!empty($errors['is_system_role_err'])) ? 'is-invalid' : ''; ?>"
                               value="1" <?php echo (isset($is_system_role) && $is_system_role) ? 'checked' : ''; ?>
                               <?php echo ($isEditing && ($role_key ?? '') === 'admin') ? 'disabled' : ''; // Prevent changing 'admin' from system role ?>
                               >
                        <label class="form-check-label" for="is_system_role">Is System Role?</label>
                        <?php if ($isEditing && ($role_key ?? '') === 'admin'): ?>
                            <small class="form-text text-muted d-block">The 'admin' role must remain a system role.</small>
                        <?php else: ?>
                            <small class="form-text text-muted d-block">System roles (like 'admin') are critical and cannot be deleted through the UI.</small>
                        <?php endif; ?>
                        <?php if (!empty($errors['is_system_role_err'])): ?>
                            <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['is_system_role_err']); ?></div>
                        <?php endif; ?>
                    </div>


                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?php echo $isEditing ? 'Update Role' : 'Create Role'; ?>
                        </button>
                        <a href="<?php echo BASE_URL . 'admin/listRoles'; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

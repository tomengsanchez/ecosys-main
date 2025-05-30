<?php
// pageTitle, errors, and user data (user_id, user_login, user_email, display_name, user_role, user_status)
// are passed from AdminController's addUser() or editUser() methods.

// Determine if we are editing or adding
$isEditing = isset($user_id) && !empty($user_id);
$formAction = $isEditing ? BASE_URL . 'admin/editUser/' . $user_id : BASE_URL . 'admin/addUser';

// Define available roles
$availableRoles = ['user' => 'User', 'editor' => 'Editor', 'admin' => 'Admin'];

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7 col-xl-6">
        <div class="card shadow-sm mt-4 mb-5">
            <div class="card-body p-4">
                <h1 class="card-title text-center mb-4"><?php echo htmlspecialchars($pageTitle ?? ($isEditing ? 'Edit User' : 'Add New User')); ?></h1>

                <?php
                // Display general form errors if any
                if (!empty($errors['form_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($errors['form_err']) . '</div>';
                }
                ?>

                <form action="<?php echo $formAction; ?>" method="POST">
                    
                    <div class="mb-3">
                        <label for="user_login" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="user_login" id="user_login" class="form-control <?php echo (!empty($errors['user_login_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($user_login ?? ''); ?>" required>
                        <?php if (!empty($errors['user_login_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['user_login_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="user_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="user_email" id="user_email" class="form-control <?php echo (!empty($errors['user_email_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($user_email ?? ''); ?>" required>
                        <?php if (!empty($errors['user_email_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['user_email_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="display_name" class="form-label">Display Name <span class="text-danger">*</span></label>
                        <input type="text" name="display_name" id="display_name" class="form-control <?php echo (!empty($errors['display_name_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($display_name ?? ''); ?>" required>
                        <?php if (!empty($errors['display_name_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['display_name_err']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    <p class="text-muted"><?php echo $isEditing ? 'Leave password fields blank to keep current password.' : 'Set a password for the new user.'; ?></p>

                    <div class="mb-3">
                        <label for="user_pass" class="form-label">Password <?php echo !$isEditing ? '<span class="text-danger">*</span>' : ''; ?></label>
                        <input type="password" name="user_pass" id="user_pass" class="form-control <?php echo (!empty($errors['user_pass_err'])) ? 'is-invalid' : ''; ?>"
                               <?php echo !$isEditing ? 'required' : ''; ?>>
                        <?php if (!empty($errors['user_pass_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['user_pass_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_pass" class="form-label">Confirm Password <?php echo !$isEditing ? '<span class="text-danger">*</span>' : ''; ?></label>
                        <input type="password" name="confirm_pass" id="confirm_pass" class="form-control <?php echo (!empty($errors['confirm_pass_err'])) ? 'is-invalid' : ''; ?>"
                               <?php echo !$isEditing ? 'required' : ''; ?>>
                        <?php if (!empty($errors['confirm_pass_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_pass_err']); ?></div>
                        <?php endif; ?>
                    </div>
                     <hr>

                    <div class="mb-3">
                        <label for="user_role" class="form-label">User Role <span class="text-danger">*</span></label>
                        <select name="user_role" id="user_role" class="form-select <?php echo (!empty($errors['user_role_err'])) ? 'is-invalid' : ''; ?>" required>
                            <?php
                            $currentRole = $user_role ?? 'user'; // Default to 'user' if not set
                            foreach ($availableRoles as $roleValue => $roleLabel):
                                // Prevent changing user_id 1 from 'admin' if editing that user
                                $disabled = ($isEditing && isset($user_id) && $user_id == 1 && $roleValue !== 'admin') ? 'disabled' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($roleValue); ?>" <?php echo ($currentRole == $roleValue) ? 'selected' : ''; ?> <?php echo $disabled; ?>>
                                    <?php echo htmlspecialchars($roleLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['user_role_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['user_role_err']); ?></div>
                        <?php endif; ?>
                         <?php if ($isEditing && isset($user_id) && $user_id == 1): ?>
                            <small class="form-text text-muted">The role of the super administrator (ID 1) cannot be changed from 'Admin'.</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="user_status" class="form-label">User Status</label>
                        <select name="user_status" id="user_status" class="form-select <?php echo (!empty($errors['user_status_err'])) ? 'is-invalid' : ''; ?>">
                            <option value="0" <?php echo (isset($user_status) && $user_status == 0) ? 'selected' : ''; ?>>Active</option>
                            <option value="1" <?php echo (isset($user_status) && $user_status == 1) ? 'selected' : ''; ?>>Inactive/Banned</option>
                        </select>
                        <?php if (!empty($errors['user_status_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['user_status_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?php echo $isEditing ? 'Update User' : 'Create User'; ?>
                        </button>
                        <a href="<?php echo BASE_URL . 'admin/users'; ?>" class="btn btn-secondary">Cancel</a>
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

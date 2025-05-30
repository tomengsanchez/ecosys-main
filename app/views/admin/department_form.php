<?php
// pageTitle, errors, and department data (department_id, department_name, department_description)
// are passed from AdminController's addDepartment() or editDepartment() methods.

// Determine if we are editing or adding
$isEditing = isset($department_id) && !empty($department_id);
$formAction = $isEditing ? BASE_URL . 'admin/editDepartment/' . $department_id : BASE_URL . 'admin/addDepartment';

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7 col-xl-6">
        <div class="card shadow-sm mt-4 mb-5">
            <div class="card-body p-4">
                <h1 class="card-title text-center mb-4"><?php echo htmlspecialchars($pageTitle ?? ($isEditing ? 'Edit Department' : 'Add New Department')); ?></h1>

                <?php
                // Display general form errors if any
                if (!empty($errors['form_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($errors['form_err']) . '</div>';
                }
                ?>

                <form action="<?php echo $formAction; ?>" method="POST">
                    
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="department_name" id="department_name" class="form-control <?php echo (!empty($errors['department_name_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($department_name ?? ''); ?>" required>
                        <?php if (!empty($errors['department_name_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['department_name_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="department_description" class="form-label">Description</label>
                        <textarea name="department_description" id="department_description" class="form-control <?php echo (!empty($errors['department_description_err'])) ? 'is-invalid' : ''; ?>"
                                  rows="4"><?php echo htmlspecialchars($department_description ?? ''); ?></textarea>
                        <?php if (!empty($errors['department_description_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['department_description_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?php echo $isEditing ? 'Update Department' : 'Create Department'; ?>
                        </button>
                        <a href="<?php echo BASE_URL . 'admin/departments'; ?>" class="btn btn-secondary">Cancel</a>
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

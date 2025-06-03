<?php
// pageTitle, settings (array of current values), manageableOptions (array of definitions), errors, and breadcrumbs
// are passed from AdminController's siteSettings() method.

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-site-settings-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Site Settings'); ?></h1>
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

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="<?php echo BASE_URL . 'admin/siteSettings'; ?>" method="POST">
                <?php if (!empty($manageableOptions) && is_array($manageableOptions)): ?>
                    <?php foreach ($manageableOptions as $optionKey => $optionDetails): ?>
                        <?php
                        $label = $optionDetails['label'] ?? ucwords(str_replace('_', ' ', $optionKey));
                        // Use submitted value if an error occurred, otherwise use current setting
                        $currentValue = isset($errors) && !empty($errors) && isset($settings[$optionKey]) ? $settings[$optionKey] : ($settings[$optionKey] ?? ($optionDetails['default'] ?? ''));
                        $inputType = $optionDetails['type'] ?? 'text';
                        $optionsForSelect = $optionDetails['options'] ?? []; 
                        $helpText = $optionDetails['help'] ?? '';
                        $errorMsg = $errors[$optionKey . '_err'] ?? '';
                        ?>
                        <div class="mb-3">
                            <label for="<?php echo htmlspecialchars($optionKey); ?>" class="form-label"><?php echo htmlspecialchars($label); ?>:</label>
                            
                            <?php if ($inputType === 'textarea'): ?>
                                <textarea name="<?php echo htmlspecialchars($optionKey); ?>" id="<?php echo htmlspecialchars($optionKey); ?>" class="form-control <?php echo !empty($errorMsg) ? 'is-invalid' : ''; ?>" rows="3"><?php echo htmlspecialchars($currentValue); ?></textarea>
                            <?php elseif ($inputType === 'select' && !empty($optionsForSelect)): ?>
                                <select name="<?php echo htmlspecialchars($optionKey); ?>" id="<?php echo htmlspecialchars($optionKey); ?>" class="form-select <?php echo !empty($errorMsg) ? 'is-invalid' : ''; ?>">
                                    <?php foreach ($optionsForSelect as $value => $display): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($currentValue == $value) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($display); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($inputType === 'number'): ?>
                                 <input type="number" name="<?php echo htmlspecialchars($optionKey); ?>" id="<?php echo htmlspecialchars($optionKey); ?>" class="form-control <?php echo !empty($errorMsg) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($currentValue); ?>">
                            <?php else: // Default to text input, supports 'email', 'text' etc. ?>
                                <input type="<?php echo htmlspecialchars($inputType); ?>" name="<?php echo htmlspecialchars($optionKey); ?>" id="<?php echo htmlspecialchars($optionKey); ?>" class="form-control <?php echo !empty($errorMsg) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($currentValue); ?>">
                            <?php endif; ?>

                            <?php if ($helpText): ?>
                                <small class="form-text text-muted"><?php echo htmlspecialchars($helpText); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($errorMsg)): ?>
                                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errorMsg); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No settings are currently manageable.</p>
                <?php endif; ?>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

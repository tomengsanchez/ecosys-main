<?php
// pageTitle, settings (array of current values), manageableOptions (array of definitions), and breadcrumbs
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
                    <?php foreach ($manageableOptions as $optionKey => $defaultValueOrLabel): ?>
                        <?php
                        // Determine label: if $defaultValueOrLabel is an array, it might contain more info like 'label', 'type'.
                        // For simplicity, we'll assume $defaultValueOrLabel is the label if it's a string,
                        // or we can derive a label from the key.
                        $label = is_array($defaultValueOrLabel) && isset($defaultValueOrLabel['label']) ? $defaultValueOrLabel['label'] : ucwords(str_replace('_', ' ', $optionKey));
                        $currentValue = $settings[$optionKey] ?? (is_array($defaultValueOrLabel) ? ($defaultValueOrLabel['default'] ?? '') : $defaultValueOrLabel);
                        $inputType = is_array($defaultValueOrLabel) && isset($defaultValueOrLabel['type']) ? $defaultValueOrLabel['type'] : 'text';
                        $options = is_array($defaultValueOrLabel) && isset($defaultValueOrLabel['options']) ? $defaultValueOrLabel['options'] : []; // For select/radio
                        $helpText = is_array($defaultValueOrLabel) && isset($defaultValueOrLabel['help']) ? $defaultValueOrLabel['help'] : '';

                        ?>
                        <div class="mb-3">
                            <label for="<?php echo htmlspecialchars($optionKey); ?>" class="form-label"><?php echo htmlspecialchars($label); ?>:</label>
                            
                            <?php if ($inputType === 'textarea'): ?>
                                <textarea name="<?php echo htmlspecialchars($optionKey); ?>" id="<?php echo htmlspecialchars($optionKey); ?>" class="form-control" rows="3"><?php echo htmlspecialchars($currentValue); ?></textarea>
                            <?php elseif ($inputType === 'select' && !empty($options)): ?>
                                <select name="<?php echo htmlspecialchars($optionKey); ?>" id="<?php echo htmlspecialchars($optionKey); ?>" class="form-select">
                                    <?php foreach ($options as $value => $display): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($currentValue == $value) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($display); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($inputType === 'number'): ?>
                                 <input type="number" name="<?php echo htmlspecialchars($optionKey); ?>" id="<?php echo htmlspecialchars($optionKey); ?>" class="form-control"
                                       value="<?php echo htmlspecialchars($currentValue); ?>">
                            <?php else: // Default to text input ?>
                                <input type="text" name="<?php echo htmlspecialchars($optionKey); ?>" id="<?php echo htmlspecialchars($optionKey); ?>" class="form-control"
                                       value="<?php echo htmlspecialchars($currentValue); ?>">
                            <?php endif; ?>
                            <?php if ($helpText): ?>
                                <small class="form-text text-muted"><?php echo htmlspecialchars($helpText); ?></small>
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

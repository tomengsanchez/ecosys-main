<?php
// Define page title for the header
$pageTitle = 'Login';

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="login-container" style="max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="text-align: center; margin-bottom: 20px;">User Login</h2>

    <?php
    // Display general login errors if any (e.g., "Invalid credentials")
    if (!empty($data['errors']['login_err'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($data['errors']['login_err']) . '</div>';
    }
    ?>

    <form action="<?php echo BASE_URL . 'auth/processlogin'; ?>" method="POST">
        <div class="form-group">
            <label for="username_or_email">Username or Email:</label>
            <input type="text" name="username_or_email" id="username_or_email" 
                   value="<?php echo htmlspecialchars($data['username_or_email'] ?? ''); ?>" required>
            <?php if (!empty($data['errors']['username_or_email_err'])): ?>
                <p class="error-message"><?php echo htmlspecialchars($data['errors']['username_or_email_err']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
            <?php if (!empty($data['errors']['password_err'])): ?>
                <p class="error-message"><?php echo htmlspecialchars($data['errors']['password_err']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <button type="submit" class="btn" style="width: 100%;">Login</button>
        </div>
    </form>

    <!--
    <p style="text-align: center; margin-top: 15px;">
        Don't have an account? <a href="<?php echo BASE_URL . 'auth/register'; ?>">Register here</a>
    </p>
    -->
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

<?php
// Define page title for the header
$pageTitle = 'Login';

// Include header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card shadow-sm mt-5">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">User Login</h2>

                <?php
                // Display general login errors if any (e.g., "Invalid credentials")
                if (!empty($data['errors']['login_err'])) {
                    echo '<div class="alert alert-danger text-center">' . htmlspecialchars($data['errors']['login_err']) . '</div>';
                }
                ?>

                <form action="<?php echo BASE_URL . 'auth/processlogin'; ?>" method="POST">
                    <div class="mb-3">
                        <label for="username_or_email" class="form-label">Username or Email:</label>
                        <input type="text" name="username_or_email" id="username_or_email" class="form-control <?php echo (!empty($data['errors']['username_or_email_err'])) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($data['username_or_email'] ?? ''); ?>" required>
                        <?php if (!empty($data['errors']['username_or_email_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($data['errors']['username_or_email_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password:</label>
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($data['errors']['password_err'])) ? 'is-invalid' : ''; ?>" required>
                        <?php if (!empty($data['errors']['password_err'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($data['errors']['password_err']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>

                <!--
                <p class="text-center mt-3">
                    Don't have an account? <a href="<?php echo BASE_URL . 'auth/register'; ?>">Register here</a>
                </p>
                -->
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>

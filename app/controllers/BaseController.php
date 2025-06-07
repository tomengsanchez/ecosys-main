<?php

/**
 * BaseController
 *
 * Provides common functionality for other controllers,
 * such as view loading and session message handling.
 */
class BaseController {

    /**
     * Load a view file.
     *
     * @param string $view The path to the view file (e.g., 'admin/index').
     * @param array $data Data to extract and make available to the view.
     */
    protected function view($view, $data = []) {
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            extract($data); // Make $data keys available as variables in the view
            require_once $viewFile;
        } else {
            // Log the error and show a generic error message
            error_log(get_class($this) . ": View file not found: {$viewFile}");
            // Consider a more user-friendly error page for production
            die('Error: View not found (' . htmlspecialchars($view) . '). Please contact support.');
        }
    }

    /**
     * Set a flash message in the session.
     *
     * @param string $name The name of the flash message (e.g., 'success', 'error', 'info').
     * @param string $message The message content.
     */
    protected function setFlashMessage($name, $message) {
        $_SESSION['flash_' . $name] = $message;
    }

    /**
     * Get and clear a flash message from the session.
     *
     * @param string $name The name of the flash message.
     * @return string|null The message if it exists, otherwise null.
     */
    protected function getFlashMessage($name) {
        if (isset($_SESSION['flash_' . $name])) {
            $message = $_SESSION['flash_' . $name];
            unset($_SESSION['flash_' . $name]);
            return $message;
        }
        return null;
    }

    /**
     * Display a flash message if it exists.
     *
     * @param string $name The name of the flash message.
     * @param string $type The Bootstrap alert type (e.g., 'success', 'danger', 'info', 'warning').
     * @return string HTML for the alert, or empty string if no message.
     */
    protected function displayFlashMessage($name, $type = 'info') {
        $message = $this->getFlashMessage($name);
        if ($message) {
            return '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">' .
                   htmlspecialchars($message) .
                   '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
                   '</div>';
        }
        return '';
    }
}
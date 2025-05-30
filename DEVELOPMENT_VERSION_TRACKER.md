# Development Version Tracker - Mainsystem PHP Project

This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

---

## Version 0.3.0 - Admin User Management (YYYY-MM-DD)

**Date:** 2025-05-30 (Please update with the actual date)

**Features Implemented:**

* **`UserModel` Enhancements for Admin:**
    * `getAllUsers()`: Method to fetch all users for listing.
    * `createUser()`: Enhanced to be more flexible for admin use (e.g., setting status).
    * `updateUser()`: Method to update user details, including optional password change (hashed).
    * `deleteUser()`: Method to delete users, with a safeguard for the super admin (user_id 1) and self-deletion.
* **`AdminController` User Management Actions:**
    * `users()`: Displays a list of all users.
    * `addUser()`: Handles both displaying the form and processing the creation of new users, including validation.
    * `editUser($userId)`: Handles displaying a prefilled form and processing updates for existing users, including validation and optional password change.
    * `deleteUser($userId)`: Handles deletion of users, with safeguards.
* **Admin User Management Views:**
    * `app/views/admin/users.php`: View to display a table of users with links/buttons for add, edit, and delete actions. Includes session feedback messages and a confirmation dialog for deletion.
    * `app/views/admin/user_form.php`: Reusable form for both adding and editing users, dynamically adjusting its title, action, and prefilled data. Includes Bootstrap styling and validation feedback.
* **General Admin UI:**
    * Session messages (`$_SESSION['admin_message']`, `$_SESSION['error_message']`) used for feedback in the admin user management section.
    * Basic Font Awesome icons added to buttons in `users.php`.

**Key Changes & Fixes:**

* Implemented core CRUD (Create, Read, Update, Delete) operations for users within the admin panel.
* Added basic safeguards against deleting the primary admin account or an admin deleting their own account via the user list.

**To-Do / Next Steps (Examples for User Management):**

* Implement more robust CSRF protection for delete/update actions.
* Add pagination to the user list for larger numbers of users.
* Implement search and filtering for the user list.
* Enhance role management beyond the simple `user_id == 1` check.

---

## Version 0.2.0 - Administrator Module (YYYY-MM-DD)

**Date:** 2025-05-30 (Please update with the actual date)

**Features Implemented:**

* **Admin Controller (`AdminController.php`):**
    * Created to handle admin-specific logic.
    * Constructor includes access control:
        * Checks if user is logged in.
        * Checks if `$_SESSION['user_id'] == 1` for admin privileges (initial implementation).
        * Redirects non-admins with an error message.
    * `index()` method for the main admin dashboard.
    * `users()` method stub for future user management.
    * `view()` helper method for loading admin views.
* **Admin Views:**
    * Created `app/views/admin/` directory.
    * `app/views/admin/index.php`: Basic admin dashboard view with placeholder cards for common admin tasks.
* **Navigation:**
    * Updated `app/views/layouts/header.php` to display an "Admin Panel" link in the navigation bar.
    * Admin link is only visible if `$_SESSION['user_id'] == 1`.
    * Added basic "active" state styling to navigation links.

**Key Changes & Fixes:**

* Simplified admin role check to `user_id == 1` for now.

**To-Do / Next Steps (Examples for Admin Module):**

* Implement a proper role-based access control (RBAC) system (e.g., add `user_role` column to `users` table).
* Implement content management features.
* Add site settings management.

---

## Version 0.1.0 - Initial Setup & Login System (YYYY-MM-DD)

**Date:** 2025-05-30 (Please update with the actual date you started/completed these)

**Features Implemented:**

* **Core Application Structure:**
    * Basic MVC (Model-View-Controller) pattern established.
    * `index.php` as the main entry point and basic router.
    * `config.php` for database credentials and session management.
    * `.htaccess` for clean URLs.
* **Database:**
    * Initial database schema defined in `database_structure.sql` (objects, objectmeta, users, usermeta, terms, term_taxonomy, term_relationships, options).
    * PDO connection established.
* **User Authentication:**
    * `UserModel.php`: Handles user data interaction (finding users).
    * `AuthController.php`: Manages login form display, login processing, and logout.
    * Login view (`app/views/auth/login.php`).
    * Session-based login persistence.
    * Password hashing (`password_hash()`) and verification (`password_verify()`).
* **Basic Dashboard:**
    * `DashboardController.php`: Displays a simple dashboard for logged-in users.
    * Dashboard view (`app/views/dashboard/index.php`).
    * Route protection (redirects to login if not authenticated).
* **Layout & Styling:**
    * Basic HTML layout with `header.php` and `footer.php`.
    * Integrated Bootstrap 5.3.6 for styling.
    * Integrated jQuery 3.7.1.
    * Login form styled with Bootstrap.

**Key Changes & Fixes:**

* Resolved `SQLSTATE[HY093]: Invalid parameter number` error in `UserModel` by using distinct named placeholders in the SQL query for `findUserByUsernameOrEmail`.
* Corrected routing logic in `index.php` to properly handle default actions (e.g., `/dashboard` now correctly maps to `DashboardController::index()`).
* Fixed typos in `integrity` attributes for Bootstrap CSS/JS CDN links.

**To-Do / Next Steps (Examples):**

* Implement user registration.
* Add password recovery functionality.
* Develop user profile management.
* Expand dashboard features.
* Implement content management (CRUD for `objects` table).
* Refine error handling and user feedback.
* Write unit/integration tests.

---

## Version X.Y.Z (Future Version)

**Date:**

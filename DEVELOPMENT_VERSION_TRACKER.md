# Development Version Tracker - Mainsystem PHP Project

This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

---

## Version 0.5.0 - Department Module & User Assignment (2025-05-30)

**Date:** 2025-05-30

**Features Implemented:**

* **Database Schema for Departments:**
    * Created `departments` table (`department_id`, `department_name`, `department_description`, `created_at`).
    * Added `department_id` (nullable, foreign key) to the `users` table.
    * Set `ON DELETE SET NULL` for the foreign key to handle department deletion.
* **`DepartmentModel.php`:**
    * Created to handle CRUD operations for departments (`createDepartment`, `getDepartmentById`, `getAllDepartments`, `updateDepartment`, `deleteDepartment`).
    * Added `getUserCountByDepartment()` to count users in a department.
* **`AdminController.php` Enhancements for Departments:**
    * Instantiated `DepartmentModel`.
    * Added `departments()` action to list departments (with user counts).
    * Added `addDepartment()` action for creating new departments (with form display and processing).
    * Added `editDepartment()` action for updating existing departments (with form display and processing).
    * Added `deleteDepartment()` action.
    * Modified `addUser()` and `editUser()` to fetch departments and pass them to the user form.
* **`UserModel.php` Enhancements for Departments:**
    * `findUserByUsernameOrEmail()`, `findUserById()`: Now fetch `department_id`.
    * `getAllUsers()`: Now fetches `department_id` and `department_name` (via LEFT JOIN).
    * `createUser()`: Accepts `department_id` and stores it.
    * `updateUser()`: Allows updating `department_id` (including setting to NULL).
* **Admin Views for Department Management:**
    * `app/views/admin/departments.php`: View to list departments, showing name, description, user count, and actions (add, edit, delete).
    * `app/views/admin/department_form.php`: Reusable form for adding and editing departments.
* **Admin View Updates for User-Department Assignment:**
    * `app/views/admin/user_form.php`: Added a dropdown to select/assign a department to a user.
    * `app/views/admin/users.php`: Displays the assigned department name in the user list.
* **Admin Dashboard UI (`app/views/admin/index.php`):**
    * Added a card/link for "Manage Departments".

**Key Changes & Fixes:**

* Integrated department management into the admin panel.
* Users can now be assigned to departments.

**To-Do / Next Steps (Examples for Department Module):**

* Filter users by department in the user list.
* Add more detailed views for departments (e.g., list users within a specific department).

---

## Version 0.4.0 - Role-Based Access Control (RBAC) - Basic (2025-05-30)

**Date:** 2025-05-30

**Features Implemented:**

* **Database Schema Update:**
    * Added `user_role` column (VARCHAR(50), default 'user') to the `users` table.
    * Updated existing admin user (`user_id = 1`) to have the 'admin' role.
* **`UserModel` Enhancements for Roles:**
    * SELECT queries (`findUserByUsernameOrEmail`, `findUserById`, `getAllUsers`) now fetch the `user_role`.
    * `createUser()`: Accepts a `user_role` parameter (defaults to 'user') and stores it.
    * `updateUser()`: Allows updating the `user_role`.
* **`AuthController` Update:**
    * `createUserSession()`: Stores the `user_role` in `$_SESSION['user_role']` upon successful login.
* **`AdminController` Updates for Role-Based Access:**
    * Constructor access control now checks `$_SESSION['user_role'] === 'admin'` instead of `user_id == 1`.
    * `addUser()`: Allows setting `user_role` via form, validates against allowed roles.
    * `editUser()`: Allows editing `user_role` via form, validates, and prevents changing the role of `user_id = 1` from 'admin'.
    * `deleteUser()`: Protects the primary super admin (`user_id = 1` with 'admin' role) from deletion.
* **Admin View Updates for Roles:**
    * `app/views/admin/users.php`: Displays the `user_role` in the user list table. Delete button logic updated to protect primary admin based on role and ID.
    * `app/views/admin/user_form.php`: Includes a dropdown menu to select/display `user_role` (admin, editor, user). Safeguards prevent changing the primary admin's role from 'admin'.
* **Layout (`header.php`) Update:**
    * "Admin Panel" navigation link is now displayed based on `$_SESSION['user_role'] === 'admin'`.
    * Added Font Awesome CDN link.
    * User display name and logout link converted to a Bootstrap dropdown.

**Key Changes & Fixes:**

* Shifted from `user_id`-based admin access to a basic role-based system ('admin', 'editor', 'user').
* Corrected `integrity` attributes for CDN links in `header.php`.

**To-Do / Next Steps (Examples for RBAC):**

* Implement more granular permissions beyond just 'admin' access (e.g., what 'editor' can do).
* Create middleware or helper functions for checking permissions for specific actions/controllers.
* Potentially create a separate table for roles and permissions for a more scalable RBAC system.

---

## Version 0.3.0 - Admin User Management (2025-05-30)

**Date:** 2025-05-30

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

---

## Version 0.2.0 - Administrator Module (2025-05-30)

**Date:** 2025-05-30

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

## Version 0.1.0 - Initial Setup & Login System (2025-05-30)

**Date:** 2025-05-30

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

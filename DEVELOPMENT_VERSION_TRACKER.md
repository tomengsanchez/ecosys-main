Development Version Tracker - Mainsystem PHP Project
This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

Version 0.10.0 - Dynamic Role Management (CRUD) (2025-05-30)
Date: 2025-05-30

Features Implemented:

Database Schema for Roles:

Created roles table (role_id, role_key, role_name, role_description, is_system_role, created_at).

Seeded initial roles ('admin', 'editor', 'user'), with 'admin' as a system role.

RoleModel.php:

Created to handle CRUD operations for the roles table.

Methods include createRole(), getRoleById(), getRoleByKey(), getAllRoles(), updateRole(), deleteRole().

deleteRole() handles deleting associated permissions and reassigning users to a default role.

Prevents deletion/modification of system roles (e.g., 'admin' cannot be made non-system or deleted).

config.php Updates:

Removed hardcoded DEFINED_ROLES constant.

getDefinedRoles() function now fetches roles (key-name pairs) from the database using RoleModel.

Added 'MANAGE_ROLES' to the CAPABILITIES constant and seeded it for the 'admin' role in role_permissions.

AdminController.php - Role CRUD Actions:

Instantiated RoleModel.

Added listRoles() action to display all roles.

Added addRole() action for creating new roles (with form display and processing, validation for unique role_key).

Added editRole() action for updating existing roles (role_key not editable, safeguards for system roles).

Added deleteRole() action with safeguards for system roles.

All new role management actions are protected by the MANAGE_ROLES capability.

Admin Views for Role Management:

app/views/admin/roles_list.php: View to list roles with details and action buttons.

app/views/admin/role_form.php: Reusable form for adding and editing roles.

UI Updates:

app/views/admin/index.php: Added a card/link for "Manage Roles".

app/views/layouts/header.php: Added "Manage Roles" to the "Admin" dropdown menu.

user_form.php and role_access_settings.php now use getDefinedRoles() which fetches from DB.

Key Changes & Fixes:

Roles are now fully dynamic and managed via the database and admin UI.

Enhanced the role system with the ability to create, edit, and delete roles (with protections for system roles).

To-Do / Next Steps (Examples for Role Management):

When deleting a role, provide an option for the admin to choose which role users should be reassigned to, instead of hardcoding 'user'.

More robust UI for indicating system roles and their non-deletable/non-modifiable nature.

Version 0.9.1 - Restricted Admin Role Permissions Editing (2025-05-30)
Date: 2025-05-30

Features Implemented:

AdminController.php - roleAccessSettings() Safeguard:

Modifications to the 'admin' role's capabilities are skipped in POST processing.

app/views/admin/role_access_settings.php - UI Restriction for 'Admin' Role:

Checkboxes for 'admin' role capabilities are disabled and always checked.

Key Changes & Fixes:

Enhanced safeguards for the 'admin' role's permissions.

Version 0.9.0 - Editable Role Permissions (2025-05-30)
Date: 2025-05-30

Features Implemented:

Database-driven role-permission mapping (role_permissions table).

RolePermissionModel.php for managing these mappings.

config.php updated to use database for userHasCapability().

AdminController::roleAccessSettings() updated to save changes.

role_access_settings.php view made into an editable form.

Version 0.8.0 - Role Access Settings Display (2025-05-30)
Date: 2025-05-30

Features Implemented:

Capability Definitions (config.php).

AdminController.php capability checks & roleAccessSettings() (read-only).

role_access_settings.php view (read-only).

UI Links for Role Access Settings.

Version 0.7.0 - Site Settings Module (2025-05-30)
Date: 2025-05-30

Features Implemented:

OptionModel.php, AdminController::siteSettings(), site_settings.php view, UI links.

Version 0.6.0 - Breadcrumb Navigation (2025-05-30)
Date: 2025-05-30

Features Implemented:

Breadcrumb helper function and integration.

Version 0.5.0 - Department Module & User Assignment (2025-05-30)
Date: 2025-05-30

Features Implemented:

Database schema & DepartmentModel.php for departments.

Controller & Model updates for department management and user assignment.

Admin views for department CRUD and user-department assignment.

Version 0.4.0 - Role-Based Access Control (RBAC) - Basic (2025-05-30)
Date: 2025-05-30

Features Implemented:

user_role column in users table.

Model, Controller, and View updates for basic role management.

Version 0.3.0 - Admin User Management (2025-05-30)
Date: 2025-05-30

Features Implemented:

Full CRUD for users in Admin Panel.

Version 0.2.0 - Administrator Module (2025-05-30)
Date: 2025-05-30

Features Implemented:

Basic AdminController and admin dashboard view.

Version 0.1.0 - Initial Setup & Login System (2025-05-30)
Date: 2025-05-30

Features Implemented:

Core MVC structure, DB connection, routing, login system, basic dashboard, Bootstrap & jQuery.

Version X.Y.Z (Future Version)
Date:
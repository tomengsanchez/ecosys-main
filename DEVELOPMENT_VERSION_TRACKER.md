Development Version Tracker - Mainsystem PHP Project
This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

Version 0.9.0 - Editable Role Permissions (2025-05-30)
Date: 2025-05-30

Features Implemented:

AdminController.php - Enhanced roleAccessSettings():

Handles POST requests to save updated role-capability mappings.

Iterates through defined roles and submitted capabilities.

Uses RolePermissionModel::setRoleCapabilities() to update permissions in the database.

Includes a basic safeguard consideration for the 'admin' role's permissions.

Fetches current role-capability mappings from the database for display.

app/views/admin/role_access_settings.php - Editable Form:

Transformed the read-only view into an editable form.

Generates checkboxes for each capability under each role.

Checkbox names are structured as permissions[role_key][] for easy processing in PHP.

Pre-selects checkboxes based on current permissions fetched from the database.

Includes a "Save All Permissions" button.

Added an informational note regarding the special status of the 'admin' role.

Key Changes & Fixes:

Administrators can now modify which capabilities are assigned to each role (except potentially the primary 'admin' role, depending on safeguards).

Role permissions are now fully managed via the database and UI.

To-Do / Next Steps (Examples for Role Management):

Implement more robust safeguards for editing the primary 'admin' role's permissions.

Add UI for creating/deleting roles themselves (currently roles are hardcoded in config.php).

Consider logging changes to role permissions for auditing.

Add CSRF protection to the permissions form.

Version 0.8.0 - Role Access Settings Display (2025-05-30)
Date: 2025-05-30

Features Implemented:

Capability Definitions (config.php):

Defined CAPABILITIES and ROLE_CAPABILITIES (hardcoded initial mapping).

Added getDefinedRoles() and userHasCapability() helpers.

AdminController.php Enhancements:

Constructor checks ACCESS_ADMIN_PANEL capability.

Individual admin actions use specific capability checks.

Added roleAccessSettings() action to display role-capability mappings (read-only initially).

Admin View for Role Access Settings:

Created app/views/admin/role_access_settings.php (read-only display).

Admin UI Updates:

Links to "Role Access Settings" in admin dashboard and header.

Version 0.7.0 - Site Settings Module (2025-05-30)
Date: 2025-05-30

Features Implemented:

OptionModel.php for options table CRUD.

AdminController.php siteSettings() action.

Admin view site_settings.php for the form.

UI links for Site Settings.

Version 0.6.0 - Breadcrumb Navigation (2025-05-30)
Date: 2025-05-30

Features Implemented:

Breadcrumb helper function and integration in layout and controllers.

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
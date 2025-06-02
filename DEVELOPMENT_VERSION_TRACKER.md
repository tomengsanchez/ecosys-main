Development Version Tracker - Mainsystem PHP Project
This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

Version 0.12.0 - Dashboard Calendar for Room Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

Layout Updates (Header & Footer):
app/views/layouts/header.php:
Added FullCalendar library (JS and CSS) via CDN.
app/views/layouts/footer.php:
No changes needed for FullCalendar's JS as the global bundle was included in the header.

DashboardController.php (index() method):
Instantiated ObjectModel and UserModel.
Fetched room reservations with 'pending' or 'approved' status using a new getObjectsByConditions() method in ObjectModel.
Processed reservations into a JSON array suitable for FullCalendar events. Each event includes:
title: "Room Name (User Name)"
start: Reservation start datetime.
end: Reservation end datetime.
color: Green for 'approved', Yellow for 'pending'.
extendedProps: Additional data like purpose, status, roomName, userName for tooltips.
Passed the JSON encoded event data to the dashboard view.

ObjectModel.php:
Added new method getObjectsByConditions($objectType, array $conditions, array $args):
Allows fetching objects based on specific field conditions (e.g., object_status IN ('pending', 'approved')).
Supports multiple values for a condition (generates SQL IN clause).
Includes standard arguments for ordering and metadata inclusion.

Dashboard View (app/views/dashboard/index.php):
Added a <div> with id="reservationCalendar" to serve as the calendar container.
Included JavaScript to initialize FullCalendar:
Sets initialView to dayGridMonth.
Configures headerToolbar for navigation and view switching.
Loads events from the JSON data provided by the controller.
Implements eventDidMount to add Bootstrap tooltips on event hover, showing reservation details (Room, User, Status, Purpose).
Added a simple legend below the calendar for event colors (Approved, Pending).

Key Changes & Fixes:
Enhanced dashboard with a visual calendar for room reservations.
Improved data fetching in ObjectModel with a more flexible querying method.

To-Do / Next Steps (Examples for Calendar):
Make calendar events clickable to view/edit reservation details.
Implement filtering options for the calendar (e.g., by room, by status).
Integrate other types of requests (IT, Service) into the calendar if desired.
Refine styling and responsiveness of the calendar.

Version 0.11.0 - Room Reservation System (Basic) (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config Updates:
Ensured 'MANAGE_ROOMS' capability is present for clarity.

OpenOfficeController.php - Reservation Logic:
Instantiated UserModel to fetch user details for reservations.
Added roomreservations(): Displays a list of all room reservations for users with 'MANAGE_OPEN_OFFICE_RESERVATIONS' capability. Fetches associated room names and user display names.
Added createreservation($roomId): Allows any logged-in user to submit a reservation request for an 'available' room. Reservations are created with 'pending' status and linked to the room via object_parent. Basic validation for dates and purpose.
Added myreservations(): Allows logged-in users to view a list of their own reservation requests and their statuses.
Added cancelreservation($reservationId): Allows users to cancel their own 'pending' reservations.
Added approvereservation($reservationId): Allows users with 'MANAGE_OPEN_OFFICE_RESERVATIONS' to approve a 'pending' reservation.
Added denyreservation($reservationId): Allows users with 'MANAGE_OPEN_OFFICE_RESERVATIONS' to deny a 'pending' reservation.

New Views for Reservations:
app/views/openoffice/reservation_form.php: Form for users to create a new room reservation request. Displays room details and includes fields for start/end datetime and purpose.
app/views/openoffice/reservations_list.php: Admin view to list all room reservations with details (room, user, purpose, times, status) and action buttons (Approve, Deny, Revoke).
app/views/openoffice/my_reservations_list.php: User view to list their own reservations with status and an option to cancel pending requests.

Layout and View Updates:
app/views/layouts/header.php:
Added "Room Reservations (Admin)" link to "Open Office" dropdown for users with 'MANAGE_OPEN_OFFICE_RESERVATIONS' capability.
Added "My Reservations" link to "Open Office" dropdown for all logged-in users.
Included Font Awesome icons for navigation links.
Adjusted isActive helper and navbar styling for better UX.
app/views/openoffice/rooms_list.php:
Added a "Book" button for each 'available' room, linking to the createreservation action.
Conditional display of admin-specific columns (ID, Last Modified) and actions (Edit, Delete room) based on 'MANAGE_ROOMS' capability.
Conditional display of "Add New Room" button based on 'MANAGE_ROOMS' capability.

Object Model Usage:
Utilized the existing ObjectModel for all CRUD operations related to 'reservation' objects.
Reservations are stored as objects with object_type = 'reservation'.
The object_parent field of a reservation object stores the object_id of the reserved room.
Reservation details (start/end times, purpose) are stored in object_content and objectmeta.

To-Do / Next Steps (Examples for Reservation System):
Implement robust conflict checking for reservations to prevent double bookings.
Add email notifications for reservation status changes (pending, approved, denied, cancelled).
Implement a calendar view for room availability and existing bookings.
Allow editing of pending reservations by users or admins.
More detailed user feedback and error handling.

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
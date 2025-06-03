Development Version Tracker - Mainsystem PHP Project
This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

Version 0.21.0 - Model Refactoring & UI Fixes (2025-06-03)
Date: 2025-06-03

Features Implemented & Key Changes:

Security & Quality Review:
Conducted a comprehensive security and quality review of the application.
Identified areas for improvement, including CSRF protection, consistent authorization, and XSS prevention. (Note: CSRF and other major security fixes are pending implementation based on review).

UI and UX Fixes:
Corrected capability check in app/views/layouts/header.php to ensure administrators can see the "Room Reservations (Admin)" link. Changed check from MANAGE_OPEN_OFFICE_RESERVATIONS to the correct VIEW_ALL_ROOM_RESERVATIONS.
Improved calendar event display in app/views/layouts/header.php by adding CSS rules to allow event titles to wrap, preventing text cutoff.

Navigation Refactoring:
Refactored the main left-aligned navigation menu in app/views/layouts/header.php to be data-driven.
Introduced a $navigationConfig array and a renderNavigationItems() helper function to dynamically generate menu items based on configuration and user capabilities. This improves maintainability and scalability of the navigation.

Model Layer Refactoring (Separation of Concerns for Objects):
Initiated refactoring of ObjectModel.php to separate concerns by object_type.
1.  BaseObjectModel.php Created:
* Contains generic database operations applicable to all object types (CRUD on objects table, objectmeta handling, slug generation).
* The original ObjectModel.php content was moved here.
2.  ReservationModel.php Created:
* Extends BaseObjectModel.php.
* Houses methods specific to 'reservation' objects, such as getConflictingReservations().
* Includes convenience methods like getAllReservations(), getReservationsByUserId(), and getReservationsByRoomId().
3.  RoomModel.php Created:
* Extends BaseObjectModel.php.
* Includes methods specific to 'room' objects, such as getRoomById(), getAllRooms(), createRoom(), updateRoom(), and deleteRoom().
4.  ObjectModel.php Updated:
* Modified to extend BaseObjectModel.php for backward compatibility, with the intention to phase out its direct use in favor of more specific models.
5.  Controller Updates:
* OpenOfficeController.php updated to instantiate and use ReservationModel.php for reservation-related logic and RoomModel.php for room-related logic.
* DashboardController.php updated to use ReservationModel.php for fetching reservation data for the calendar and RoomModel.php for associated room details.

Key Benefits of Model Refactoring:
Clearer separation of concerns, making each model focus on a single entity type.
Improved code readability, maintainability, and testability.
Reduced class sizes and better scalability for future object types.

To-Do / Next Steps:
Continue implementing recommendations from the security review (e.g., CSRF protection).
Further refine specific models (RoomModel, ReservationModel) with any additional type-specific logic.
Consider creating specific models for other object_types if they emerge.
Ensure comprehensive testing of all functionalities affected by the model refactoring.

Version 0.20.0 - Granular Role Permissions for Room CRUD (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config.php Updates:
Defined new, more specific capability constants for room entity management within the CAPABILITIES array:
VIEW_ROOMS: To view the list of rooms.
CREATE_ROOMS: To add new rooms.
EDIT_ROOMS: To edit existing rooms.
DELETE_ROOMS: To delete rooms.
The existing MANAGE_ROOMS capability is retained, potentially as a legacy/super capability.
The userHasCapability() function was slightly adjusted to allow users with MANAGE_ROOMS to implicitly have all granular room permissions, facilitating a smoother transition.

OpenOfficeController.php Modifications:
Updated the room CRUD methods to use the new granular capabilities for access control:
rooms(): Now protected by VIEW_ROOMS.
addRoom(): Now protected by CREATE_ROOMS.
editRoom(): Now protected by EDIT_ROOMS.
deleteRoom(): Now protected by DELETE_ROOMS.
Reservation-related methods continue to use their own specific granular permissions.

Database Seeding (Conceptual):
Noted the requirement to update the role_permissions table in the database to assign these new room CRUD capabilities to appropriate roles (e.g., 'admin', 'office_manager'). Example SQL INSERT statements were provided for guidance.

Key Changes & Fixes:
Implemented a more detailed and flexible permission system for managing room entities.
Allows for finer control over which roles can view, create, edit, or delete rooms.
The MANAGE_ROOMS capability can still be used as an overarching permission for full room control if desired, while specific actions are checked against the new granular capabilities.

To-Do / Next Steps:
Update the admin UI for role permission management (role_access_settings.php) to include these new room CRUD capabilities so they can be assigned to roles dynamically.
Adjust UI elements in the room management views (e.g., show/hide "Add New Room", "Edit", "Delete" buttons) based on these new granular permissions for a more refined user experience. (Partial implementation already exists in rooms_list.php for Add/Edit/Delete based on MANAGE_ROOMS, this would be refined).

Version 0.19.0 - Granular Role Permissions for Room Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config.php Updates:
Defined new, more specific capability constants for room reservation management.

OpenOfficeController.php Modifications:
Updated existing room reservation methods to use the new granular capabilities for access control.
Added placeholder methods for future edit/delete reservation functionalities, protected by their new specific capabilities.

Database Seeding (Conceptual):
Noted the requirement to update the role_permissions table to assign new reservation capabilities.

Key Changes & Fixes:
Implemented a more detailed permission system for room reservations. Prepared for future edit/delete functionalities.

Version 0.18.0 - Dynamic Time Slot Filtering in Reservation Form (2025-06-02)
Date: 2025-06-02

Features Implemented:

OpenOfficeController.php (createreservation() method - GET request part):
Passed JSON encoded approved reservation times for the specific room to the view.

Reservation Form View (app/views/openoffice/reservation_form.php):
Added JavaScript to dynamically update the time slot dropdown, disabling/marking slots that are already approved or are in the past for the selected date.

Key Changes & Fixes:
Improved UX by preventing selection of unavailable time slots.

Version 0.17.0 - Email Notifications for Room Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config.php Enhancements:
Added email configuration constants and send_system_email() helper function.

Admin Site Settings (AdminController.php & site_settings.php view):
Added manageable options for email configuration.

OpenOfficeController.php - Email Integration:
Integrated send_system_email() for new reservations, approvals, denials, and cancellations.

Key Changes & Fixes:
Implemented email notifications for room reservation events. Made email sending configurable.

Version 0.16.0 - Server-Side Handling for 1-Hour Time Slot Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

OpenOfficeController.php (createreservation() method):
Modified to correctly process form submissions using the new date input and 1-hour time slot dropdown.

Key Changes & Fixes:
Enabled server-side logic for 1-hour time slot selections.

Version 0.15.0 - Reservation Form Time Intervals (UI Update to 1-Hour Slots & 00/30 Min Enforcement) (2025-06-02)
Date: 2025-06-02

Features Implemented:

Reservation Form (app/views/openoffice/reservation_form.php):
Replaced datetime-local inputs with a date input and a 1-hour time slot select dropdown.

Key Changes & Fixes:
Changed UI for time selection to predefined 1-hour slots.

Version 0.14.0 - Site-Wide Time Formatting (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config.php Enhancements:
Set default PHP timezone. Defined DEFAULT_TIME_FORMAT. Added get_site_time_format() and format_datetime_for_display() helper functions.

Admin Site Settings (AdminController.php & site_settings.php view):
Added 'site_time_format' to manageable options with a dropdown of common PHP date formats.

View Updates for Consistent Time Display:
Applied format_datetime_for_display() across admin and Open Office views.

Dashboard Calendar (DashboardController.php & dashboard/index.php view):
Updated to use format_datetime_for_display() for calendar event tooltips.

Key Changes & Fixes:
Introduced a global site setting for time display. Ensured consistent formatting.

Version 0.13.0 - Room Reservation Conflict Resolution (2025-06-02)
Date: 2025-06-02

Features Implemented:

ObjectModel.php Enhancements:
Added getConflictingReservations() method for finding overlapping reservations.

OpenOfficeController.php Updates for Conflict Handling:
createreservation() now checks for conflicts with 'approved' reservations.
approvereservation() now checks for conflicts with other 'approved' slots and auto-denies overlapping 'pending' requests.
deleteRoom() now prevents deletion if a room has existing reservations.

Key Changes & Fixes:
Implemented double-booking prevention and auto-denial of conflicting pending requests.

Version 0.12.0 - Dashboard Calendar for Room Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

Layout Updates: Added FullCalendar library.
DashboardController.php: Fetched and processed reservations for calendar.
ObjectModel.php: Added getObjectsByConditions().
Dashboard View: Added calendar display with tooltips.

Key Changes & Fixes:
Enhanced dashboard with a visual calendar for room reservations.

Version 0.11.0 - Room Reservation System (Basic) (2025-06-02)
Date: 2025-06-02

Features Implemented:
Config Updates, OpenOfficeController.php (Reservation Logic), New Views for Reservations, Layout and View Updates, Object Model Usage for reservations.

Version 0.10.0 - Dynamic Role Management (CRUD) (2025-05-30)
Date: 2025-05-30

Features Implemented:
Database Schema for Roles, RoleModel.php, config.php Updates, AdminController.php (Role CRUD Actions), Admin Views for Role Management, UI Updates for role management.

Version 0.9.1 - Restricted Admin Role Permissions Editing (2025-05-30)
Date: 2025-05-30

Features Implemented:
AdminController.php - roleAccessSettings() Safeguard, app/views/admin/role_access_settings.php - UI Restriction for 'Admin' Role.

Version 0.9.0 - Editable Role Permissions (2025-05-30)
Date: 2025-05-30

Features Implemented:
Database-driven role-permission mapping, RolePermissionModel.php, config.php updated for userHasCapability(), AdminController::roleAccessSettings() updated to save changes, role_access_settings.php view made into an editable form.

Version 0.8.0 - Role Access Settings Display (2025-05-30)
Date: 2025-05-30

Features Implemented:
Capability Definitions (config.php), AdminController.php capability checks & roleAccessSettings() (read-only), role_access_settings.php view (read-only), UI Links for Role Access Settings.

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
Database schema & DepartmentModel.php for departments, Controller & Model updates for department management and user assignment, Admin views for department CRUD and user-department assignment.

Version 0.4.0 - Role-Based Access Control (RBAC) - Basic (2025-05-30)
Date: 2025-05-30

Features Implemented:
user_role column in users table, Model, Controller, and View updates for basic role management.

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
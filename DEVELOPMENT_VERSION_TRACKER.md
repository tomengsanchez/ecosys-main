Development Version Tracker - Mainsystem PHP Project
This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

Version 0.19.0 - Granular Role Permissions for Room Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config.php Updates:
Defined new, more specific capability constants for room reservation management within the CAPABILITIES array:
CREATE_ROOM_RESERVATIONS: To create own reservation requests.
EDIT_OWN_ROOM_RESERVATIONS: (Future Use) To edit own pending reservations.
CANCEL_OWN_ROOM_RESERVATIONS: To cancel own pending reservations.
VIEW_ALL_ROOM_RESERVATIONS: To view all room reservations (admin/manager).
APPROVE_DENY_ROOM_RESERVATIONS: To approve/deny pending reservations (admin/manager).
EDIT_ANY_ROOM_RESERVATION: (Future Use) To edit any reservation (super admin).
DELETE_ANY_ROOM_RESERVATION: (Future Use) To delete any reservation record (super admin).
The previously broader MANAGE_OPEN_OFFICE_RESERVATIONS capability was commented out as it's superseded by these more granular ones for room bookings.

OpenOfficeController.php Modifications:
Updated existing room reservation methods to use the new granular capabilities for access control:
roomreservations(): Now protected by VIEW_ALL_ROOM_RESERVATIONS.
createreservation(): Now protected by CREATE_ROOM_RESERVATIONS.
cancelreservation(): Now protected by CANCEL_OWN_ROOM_RESERVATIONS.
approvereservation(): Now protected by APPROVE_DENY_ROOM_RESERVATIONS.
denyreservation(): Now protected by APPROVE_DENY_ROOM_RESERVATIONS.
Added placeholder methods for future functionalities, each protected by its new specific capability:
editMyReservation($reservationId): Protected by EDIT_OWN_ROOM_RESERVATIONS.
editAnyReservation($reservationId): Protected by EDIT_ANY_ROOM_RESERVATION.
deleteAnyReservation($reservationId): Protected by DELETE_ANY_ROOM_RESERVATION.
Room entity management methods (rooms, addRoom, editRoom, deleteRoom) remain protected by MANAGE_ROOMS.

Database Seeding (Conceptual):
Noted the requirement to update the role_permissions table in the database to assign these new capabilities to appropriate roles (e.g., 'admin', 'user'). Example SQL INSERT statements were provided for guidance.

Key Changes & Fixes:
Implemented a more detailed and flexible permission system for room reservations.
Allows for finer control over which roles can perform specific actions related to bookings.
Prepared the groundwork for future edit/delete functionalities for reservations by defining and protecting their respective capabilities.

To-Do / Next Steps:
Implement the actual logic for editMyReservation, editAnyReservation, and deleteAnyReservation.
Update the admin UI for role permission management (role_access_settings.php) to include these new capabilities so they can be assigned to roles dynamically.
Adjust UI elements (e.g., show/hide buttons) based on these new granular permissions.

Version 0.18.0 - Dynamic Time Slot Filtering in Reservation Form (2025-06-02)
Date: 2025-06-02

Features Implemented:

OpenOfficeController.php (createreservation() method - GET request part):
Modified to fetch all 'approved' reservations for the specific room being booked.
Extracted and passed an array of these approved time ranges (start and end datetimes) to the view as a JSON string (approved_reservations_json). This data is used by client-side JavaScript.

Reservation Form View (app/views/openoffice/reservation_form.php):
Added JavaScript logic to dynamically update the time slot dropdown based on the selected date and pre-existing approved reservations.
On page load and when the date input changes:
The script parses the approved_reservations_json data.
It filters approved reservations relevant to the selected date.
It iterates through the base 1-hour time slots.
For each slot, it constructs the full start and end datetime.
It checks if the slot is in the past or if it conflicts with any approved reservations for that day.
Conflicting or past time slots are disabled in the dropdown, and their display text is updated (e.g., "9:00 AM - 10:00 AM (Booked)" or "(Past)").
Available slots remain enabled.
The script attempts to preserve the user's previous time slot selection if it remains valid after the date change.

Key Changes & Fixes:
Significantly improved user experience by preventing users from selecting time slots that are already booked (approved) or are in the past for a given room and date.
Provided real-time feedback in the UI about slot availability.

Version 0.17.0 - Email Notifications for Room Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config.php Enhancements:
Added email configuration constants and send_system_email() helper function.

Admin Site Settings (AdminController.php & site_settings.php view):
Added manageable options for email configuration (enable/disable, from address, admin recipient).

OpenOfficeController.php - Email Integration:
Integrated send_system_email() for new reservations, approvals, denials (manual & auto), and user cancellations.

Key Changes & Fixes:
Implemented email notifications for room reservation events. Made email sending configurable.

Version 0.16.0 - Server-Side Handling for 1-Hour Time Slot Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

OpenOfficeController.php (createreservation() method):
Modified to correctly process form submissions using the new date input and 1-hour time slot dropdown.
Parses time slot, combines with date, validates, checks conflicts, and stores full datetime strings.

Key Changes & Fixes:
Enabled server-side logic for 1-hour time slot selections.

Version 0.15.0 - Reservation Form Time Intervals (UI Update to 1-Hour Slots & 00/30 Min Enforcement) (2025-06-02)
Date: 2025-06-02

Features Implemented:

Reservation Form (app/views/openoffice/reservation_form.php):
Replaced datetime-local inputs with a date input and a 1-hour time slot select dropdown.
Previous JS for 30-min interval enforcement on datetime-local became inapplicable and was removed in favor of the new slot selection method.

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
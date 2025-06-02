Development Version Tracker - Mainsystem PHP Project
This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

Version 0.17.0 - Email Notifications for Room Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config.php Enhancements:
Added email configuration constants: DEFAULT_SITE_EMAIL_FROM, DEFAULT_ADMIN_EMAIL_NOTIFICATIONS, DEFAULT_EMAIL_NOTIFICATIONS_ENABLED.
Created send_system_email($to, $subject, $message, $additional_headers = null) helper function:
Checks a global 'site_email_notifications_enabled' option (from database via OptionModel) before sending.
Uses 'site_name' and 'site_email_from' options for email construction.
Sets basic headers (From, Reply-To, Content-Type: text/plain).
Uses PHP's mail() function and includes basic logging.

Admin Site Settings (AdminController.php & site_settings.php view):
AdminController::siteSettings():
Added new manageable options for email configuration:
site_email_notifications_enabled (Select: On/Off).
site_email_from (Input: email, for system "From" address).
site_admin_email_notifications (Input: email, for admin-specific notifications).
Added basic email validation for email input fields.
app/views/admin/site_settings.php:
Updated to render the new email configuration fields with appropriate input types and error message display.

OpenOfficeController.php - Email Integration:
Instantiated OptionModel to access email settings.
Integrated send_system_email() calls into the reservation workflow:
createreservation():
Notifies the user that their request is pending.
Notifies the admin (using 'site_admin_email_notifications' email) of the new pending request.
approvereservation():
Notifies the user that their reservation has been approved.
If conflicting pending reservations are auto-denied, notifies the respective users of the denial and the reason (conflict with newly approved booking).
denyreservation():
Notifies the user that their reservation request has been denied or revoked by an administrator.
cancelreservation():
Notifies the admin (using 'site_admin_email_notifications' email) that a user has cancelled their pending reservation.
All email messages include relevant details (room name, user name, times, purpose) and use format_datetime_for_display().

Key Changes & Fixes:
Implemented a system for sending email notifications for various room reservation events.
Made email sending configurable through admin site settings (enable/disable, from address, admin recipient).
Improved user and admin communication regarding reservation status changes.

To-Do / Next Steps (Examples for Email Feature):
Implement more robust email sending using a library like PHPMailer for SMTP support, HTML emails, and better error handling.
Allow customization of email templates.
Add options for users to manage their notification preferences.
Extend email notifications to other modules (e.g., user registration, IT requests).

Version 0.16.0 - Server-Side Handling for 1-Hour Time Slot Reservations (2025-06-02)
Date: 2025-06-02

Features Implemented:

OpenOfficeController.php (createreservation() method):
Modified to correctly process form submissions using the new date input and time slot dropdown.
Retrieves reservation_date and reservation_time_slot from $_POST.
Validates that both reservation_date and reservation_time_slot are provided.
Parses the reservation_time_slot string (e.g., "08:00-09:00") to extract individual start and end times.
Combines the selected reservation_date with the parsed start and end times to construct full datetime strings (e.g., "YYYY-MM-DD HH:MM:SS") for reservation_start_datetime and reservation_end_datetime.
Includes validation to ensure the constructed start datetime is not in the past.
Uses these full datetime strings for conflict checking against existing 'approved' reservations.
Stores the full reservation_start_datetime and reservation_end_datetime in the meta_fields when creating the 'reservation' object.
If validation errors occur, the submitted reservation_date and reservation_time_slot are passed back to the view to repopulate the form.

Key Changes & Fixes:
Enabled the server-side logic to support the new 1-hour time slot selection UI for room reservations.
Ensured that date and time slot selections are correctly processed and stored for conflict checking and record-keeping.

Version 0.15.0 - Reservation Form Time Intervals (UI Update to 1-Hour Slots & 00/30 Min Enforcement) (2025-06-02)
Date: 2025-06-02

Features Implemented:

Reservation Form (app/views/openoffice/reservation_form.php):
Replaced the two datetime-local inputs with:
A single <input type="date"> for selecting the reservation date.
A <select> dropdown for choosing predefined 1-hour time slots (e.g., "8:00 AM - 9:00 AM").
Time slots are generated in PHP from 8 AM to 5 PM.
The step="1800" attribute was kept on datetime-local inputs (though these inputs were then replaced by the date/select combo in a subsequent iteration, this note reflects the state if datetime-local were still used with 30-min steps).
Added JavaScript to datetime-local inputs (before they were replaced) to enforce minute selection to be 00 or 30 by adjusting the value on the change event. This JS was removed when datetime-local was replaced by the date/select combination.

Key Changes & Fixes:
Changed the UI for time selection in the room reservation form to use predefined 1-hour slots, aiming for a simpler user experience.
Initially enhanced datetime-local inputs with step="1800" and JavaScript for strict 00/30 minute UI enforcement, which became moot when the input method was changed to a date picker and a fixed time-slot dropdown.

Version 0.14.0 - Site-Wide Time Formatting (2025-06-02)
Date: 2025-06-02

Features Implemented:

Config.php Enhancements:
Set a default PHP timezone. Defined DEFAULT_TIME_FORMAT. Added get_site_time_format() and format_datetime_for_display() helper functions.

Admin Site Settings (AdminController.php & site_settings.php view):
Added 'site_time_format' to manageable options with a dropdown of common PHP date formats.

View Updates for Consistent Time Display:
Applied format_datetime_for_display() to user_registered, created_at, object_modified, reservation start/end times, and request dates across admin and Open Office views.

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
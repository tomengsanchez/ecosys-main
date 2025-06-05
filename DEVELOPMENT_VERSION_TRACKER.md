Development Version Tracker - Mainsystem PHP Project
This document tracks the development progress, versions, and notable changes for the Mainsystem PHP project.

Version 0.22.0 - Comprehensive Email Notifications with PHPMailer (2025-06-05)
Date: 2025-06-05

Features Implemented & Key Changes:

Email System Overhaul:
Integrated PHPMailer: Replaced the basic mail() function usage with PHPMailer for more robust and reliable email sending.
SMTP Configuration: Added SMTP configuration to config.php, specifically tailored for sending emails via Gmail using App Passwords for enhanced security.
Updated send_system_email() Function: Rewrote the global email sending function in config.php to utilize PHPMailer, supporting HTML emails, attachments, CC, and BCC.

Room Reservation Notifications (OpenOfficeController.php):
Request Submission: Users now receive an HTML-formatted email confirmation upon submitting a room reservation request. Administrators also receive an HTML-formatted notification of the new pending request.
Approval/Denial: Users receive an HTML-formatted email when their room reservation request is approved or denied by an administrator.
Cancellation: Users receive an HTML-formatted email confirmation when they cancel their own pending room reservation.

Vehicle Request Notifications (VehicleRequestController.php):
Request Submission: Users receive an HTML-formatted email confirmation upon submitting a vehicle request. Administrators also receive an HTML-formatted notification of the new pending request.
Approval/Denial: Users receive an HTML-formatted email when their vehicle request is approved or denied by an administrator.
Cancellation: Users receive an HTML-formatted email confirmation when they cancel their own pending vehicle request.

Enhanced Email Content:
All system-generated email notifications for room and vehicle reservations now use HTML for improved formatting, readability, and a more professional appearance. Details are presented clearly, often using lists and bold text.

Key Benefits:
Improved reliability of email delivery using SMTP and PHPMailer.
Enhanced user experience with comprehensive and well-formatted email notifications for key actions.
Better administrative awareness of pending requests.
More secure email sending practice by encouraging the use of App Passwords for Gmail.

To-Do / Next Steps:
Continue testing email deliverability and content across different email clients.
Consider adding email notifications for other relevant system events (e.g., user registration, password resets if implemented).
Further refine HTML email templates if more sophisticated branding or styling is required.

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
Updated URLs in $navigationConfig to use PascalCase for controller segments (e.g., OpenOffice/rooms) to align with router logic and controller class name prefixes, while ensuring router correctly handles them.

Model Layer Refactoring (Separation of Concerns for Objects):
Initiated refactoring of ObjectModel.php to separate concerns by object_type.

BaseObjectModel.php Created:

Contains generic database operations applicable to all object types (CRUD on objects table, objectmeta handling, slug generation).

The original ObjectModel.php content was moved here.

ReservationModel.php Created:

Extends BaseObjectModel.php.

Houses methods specific to 'reservation' objects, such as getConflictingReservations().

Includes convenience methods like getAllReservations(), getReservationsByUserId(), and getReservationsByRoomId().

RoomModel.php Created:

Extends BaseObjectModel.php.

Includes methods specific to 'room' objects, such as getRoomById(), getAllRooms(), createRoom(), updateRoom(), and deleteRoom().

ObjectModel.php Updated:

Modified to extend BaseObjectModel.php for backward compatibility, with the intention to phase out its direct use in favor of more specific models.

Controller Updates & Naming Convention:

Filename Standardization: Advised renaming mainsystem/app/controllers/OpenOfficeController.php to mainsystem/app/controllers/OpenofficeController.php (lowercase 'o' in "office") to align with the router's generated name (ucfirst(strtolower($segment))Controller) and prevent 404 errors on case-sensitive servers. User to perform actual file rename.

The openoffice controller (conceptually OpenofficeController.php after rename) updated to instantiate and use ReservationModel.php for reservation-related logic and RoomModel.php for room-related logic.

The dashboard controller (DashboardController.php) updated to use ReservationModel.php for fetching reservation data for the calendar and RoomModel.php for associated room details.

Key Benefits of Model Refactoring:
Clearer separation of concerns, making each model focus on a single entity type.
Improved code readability, maintainability, and testability.
Reduced class sizes and better scalability for future object types.

To-Do / Next Steps:
User to rename OpenOfficeController.php to OpenofficeController.php on their file system.
Continue implementing recommendations from the security review (e.g., CSRF protection).
Further refine specific models (RoomModel, ReservationModel) with any additional type-specific logic.
Consider creating specific models for other object_types if they emerge.
Ensure comprehensive testing of all functionalities affected by the model refactoring and controller renaming.

Version 0.20.0 - Granular Role Permissions for Room CRUD (2025-06-02)
Date: 2025-06-02
... (rest of the tracker remains the same) ...
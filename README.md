ecosys-main
Mainsystem is a PHP-based application for managing internal office resources and requests, including room and vehicle reservations, user management, and more.

Features (based on recent development)
User authentication and role-based access control.

Dashboard with a calendar view of room reservations.

Open Office Module:

Room Management (CRUD operations).

Room Reservations with approval workflow and email notifications.

Vehicle Management (CRUD operations).

Vehicle Requests with approval workflow and email notifications.

Admin Panel:

User Management (CRUD, role assignment, department assignment).

Department Management (CRUD).

Role Management (CRUD).

Role Permission Management.

Site Settings Management.

Email Notifications: Uses PHPMailer for sending emails via SMTP (configured for Gmail by default) for various actions like new reservations, approvals, denials, and cancellations.

Installation Instructions
Follow these steps to set up the Mainsystem application on your local development environment or server.

1. Prerequisites
Web Server: Apache or Nginx with PHP support.

PHP: Version 7.4 or higher (ideally 8.0+) with the following extensions:

pdo_mysql (for database connectivity)

mbstring

openssl (for PHPMailer if using SSL/TLS)

json

date

MySQL/MariaDB: Database server.

Composer: For managing PHP dependencies (specifically PHPMailer). Download Composer

2. Download or Clone the Project
Clone the repository or download the project files to your web server's document root (or a subdirectory).

git clone <repository_url> mainsystem
cd mainsystem

If you downloaded a ZIP file, extract it to your desired location.

3. Install Dependencies
Navigate to the project root directory (mainsystem) in your terminal and run Composer to install PHPMailer:

composer install

This will create a vendor directory containing PHPMailer and its dependencies.

4. Database Setup
Create a Database:
Using a MySQL client (like phpMyAdmin, MySQL Workbench, or command line), create a new database. For example, maindb2 (as used in config.php).

CREATE DATABASE maindb2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

Import Database Structure:
Import the install_table.sql file into your newly created database. This file contains the SQL statements to create all necessary tables.

# Using MySQL command line
mysql -u your_db_user -p maindb2 < install_table.sql

(Replace your_db_user with your MySQL username. You will be prompted for the password.)

Import Initial Data:
Import the install_data.sql file into your database. This file populates essential data like default roles, the admin user, and site options.

# Using MySQL command line
mysql -u your_db_user -p maindb2 < install_data.sql

Important: The install_data.sql script includes a default admin user with a pre-hashed password.
The default admin credentials are:

Username: admin

Password: password (The hash for this is $2y$10$DcqROgHtfmKP96yjbg1VGeEnWa8APzrpxFcGHf5SoZZ4iEAf5bNSe which is provided in install_data.sql. If you wish to change this, use the hash_password.php script in the project root to generate a new hash and update the install_data.sql file before importing it).

5. Configure the Application
Copy config.php (if a template is provided):
If you have a config-sample.php, rename or copy it to config.php. (Your current project structure has config.php directly).

Edit config.php:
Open config.php in a text editor and update the following settings:

Database Credentials:

define('DB_NAME', 'maindb2'); // Your database name
define('DB_USER', 'root');    // Your database username
define('DB_PASSWORD', '');    // Your database password
define('DB_HOST', 'localhost'); // Your database host

Base URL:
Ensure BASE_URL is correctly set for your environment. If the application is in a subdirectory named mainsystem under your web root, then /mainsystem/ is correct. If it's at the root, it would be /.

define('BASE_URL', '/mainsystem/');

SMTP Settings (for Email Notifications):
Configure these settings if you want email notifications to work.

define('SMTP_HOST', 'smtp.gmail.com'); // Or your GoDaddy/other provider's SMTP host
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your Gmail/GoDaddy email address
define('SMTP_PASSWORD', 'your-app-password');   // Your App Password (recommended)
define('SMTP_PORT', 587);
define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS);
// Also update DEFAULT_SITE_EMAIL_FROM and DEFAULT_ADMIN_EMAIL_NOTIFICATIONS if needed.

Refer to previous discussions or PHPMailer documentation for setting up App Passwords with Gmail or Google Workspace, or for settings with other email providers like GoDaddy Professional Email.

6. Web Server Configuration
Document Root: Ensure your web server's document root (or virtual host configuration) points to the directory where your index.php file is located (e.g., the mainsystem directory).

URL Rewriting (Apache):
If you want cleaner URLs (without index.php in them), ensure mod_rewrite is enabled in Apache. You might need an .htaccess file in the project root (mainsystem) with rules similar to this (if not already present):

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [L,QSA]

(Note: Your current routing in index.php seems to parse $_SERVER['REQUEST_URI'] directly, which might work without explicit rewrite rules if the BASE_URL is handled correctly, but an .htaccess is standard for routing all requests through index.php.)

URL Rewriting (Nginx):
For Nginx, you would add a location block to your server configuration:

location /mainsystem/ { # Adjust if your BASE_URL is different
    try_files $uri $uri/ /mainsystem/index.php?$query_string;
}

7. File Permissions
Typically, PHP applications run by the web server user (e.g., www-data, apache) do not require special writable permissions for the application files themselves. However, if you implement features like file uploads or caching to the filesystem, ensure those specific directories are writable by the web server user. For this current system, no specific writable directories are immediately apparent beyond standard session storage (handled by PHP).

8. Accessing the Application
Open your web browser and navigate to the BASE_URL you configured (e.g., http://localhost/mainsystem/). You should be redirected to the login page.

Log in with the default admin credentials:

Username: admin

Password: password (or whatever you set if you changed the hash in install_data.sql)

If you encounter any issues, check your web server error logs and PHP error logs for more details.
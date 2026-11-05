# Event-Pro
### Seminar Registration and QR Code Attendance Management System

**From Code to Cloud – Technical Elective III**  
**BSCPE 4B**

**Live Website:** https://event-pro.free.nf/  
**GitHub Repository:** https://github.com/princedoble/Event-Pro  
**Hosting Provider:** InfinityFree

---

## 1. Project Overview

Event-Pro is a web-based seminar registration and attendance management system developed to simplify the process of organizing seminars, registering participants, monitoring attendance, and generating participation certificates.

The system has two types of users:

- **Administrator** – Manages seminars, monitors participants, scans QR codes, manages user accounts, and generates attendance reports.
- **Participant** – Creates an account, registers for seminars, receives QR tickets, views registration history, and accesses participation certificates.

Instead of using paper registration forms and manually recording attendance, Event-Pro uses online registration and QR code scanning to make the process more organized and efficient.

The system is deployed online through InfinityFree and can be accessed using compatible desktop and mobile browsers.

---

## 2. How to Use Event-Pro

### A. For Participants

**Step 1: Create an Account**

1. Open the Event-Pro website: https://event-pro.free.nf/
2. Click the registration option.
3. Enter the required account information.
4. Submit the registration form.
5. Log in using your registered credentials.

**Step 2: View Available Seminars**

1. After logging in, open the User Dashboard.
2. Browse the available seminars.
3. Check the seminar name, schedule, and availability.
4. Choose the seminar you want to attend.

**Step 3: Register for a Seminar**

1. Click the registration button for your chosen seminar.
2. Complete the registration process.
3. The system records your registration in the database.
4. A unique QR code is generated for your seminar registration.

Each participant receives a QR ticket associated with their registration.

**Step 4: View Your QR Ticket**

1. Go to the **My Registrations** section of the User Dashboard.
2. Find the seminar you registered for.
3. Click **View QR Code**.
4. Your QR ticket will appear.
5. You can also download the QR code for use during the seminar.

Keep your QR ticket available because it will be used to verify your attendance.

**Step 5: Attend the Seminar**

1. Go to the seminar venue on the scheduled date.
2. Present your QR code to the administrator.
3. The administrator scans your QR code.
4. Event-Pro verifies the QR code and records your attendance.

A successfully verified QR code allows the administrator to confirm the participant's attendance.

**Step 6: Access Your Certificate**

1. After attending the seminar, wait for the administrator to end the event.
2. Log in to your Event-Pro account.
3. Open the User Dashboard.
4. Find the completed seminar in your event history.
5. If you have a verified attendance record, access your participation certificate.
6. View or print the certificate.

Certificates are intended for participants with verified attendance at completed seminars.

---

### B. For Administrators

**Step 1: Log In as Administrator**

1. Open the Event-Pro website.
2. Enter your administrator login credentials.
3. Log in to access the Admin Dashboard.

Administrator accounts are created by the system owner and are not available through public registration.

**Step 2: Create a Seminar**

1. From the Admin Dashboard, open the seminar creation section.
2. Enter the seminar name.
3. Select the event date.
4. Set the event end date, if applicable.
5. Enter the maximum participant capacity.
6. Save the seminar.

The seminar information is stored in the database and becomes available for registration according to its status.

**Step 3: Monitor Seminar Registrations**

1. Open the Admin Dashboard.
2. Select or view a seminar.
3. Check the number of registered participants.
4. Review participant and seminar information.
5. Monitor the seminar's registration and attendance records.

This helps the administrator manage participant capacity and monitor seminar activity.

**Step 4: Scan Participant QR Codes**

1. Open the QR Scanner from the administrator interface.
2. Select the correct seminar.
3. Choose **Live Camera** or **Upload Image**.
4. For live scanning, allow the browser to access your camera.
5. Ask the participant to present their QR ticket, or upload its original image.
6. Wait for Event-Pro to detect and verify the QR code.

The system uses the unique QR token to identify the participant and process attendance verification.

The verification process helps prevent invalid QR codes and duplicate attendance recording.

For mobile camera scanning, use the HTTPS version of the website and grant camera permission.

**Step 5: Monitor Attendance**

1. Return to the Admin Dashboard.
2. Review the participant attendance information.
3. Check which participants have completed the attendance verification process.
4. Use the attendance records to monitor seminar participation.

**Step 6: End a Seminar**

1. Open the Admin Dashboard.
2. Locate the seminar that has finished.
3. Use the available option to end the event.
4. Confirm the action when required.

Ending a seminar changes its status and allows eligible participants to access their participation certificates.

Seminars can also be finalized automatically after their configured end date when the administrator opens the dashboard.

**Step 7: Generate Attendance Reports**

1. Open the administrator's reporting function.
2. Select the relevant seminar or attendance records.
3. View the report.
4. Print the report when needed.

Reports help organizers document seminar participation and attendance.

**Step 8: Manage User Accounts**

1. Open **Account Access** in the Admin Dashboard.
2. Review account statuses and failed login attempts.
3. Unlock an account when appropriate.
4. Delete user accounts when necessary.

---

## 3. Example of the Complete System Workflow

Here is a typical example of how Event-Pro is used:

1. The administrator creates a seminar called **Introduction to Cloud Computing**.
2. The seminar becomes available in the participant dashboard.
3. A student creates an account and logs in.
4. The student registers for **Introduction to Cloud Computing**.
5. Event-Pro generates the student's unique QR ticket.
6. On the seminar date, the student presents the QR code.
7. The administrator scans the code using Event-Pro's QR Scanner.
8. The system verifies the registration and records the student's attendance.
9. After the seminar, the administrator ends the event.
10. The student logs in again and accesses the participation certificate.
11. The administrator reviews and prints the attendance report.

This process demonstrates how Event-Pro connects seminar management, registration, QR verification, attendance monitoring, and certificates in one web-based system.

---

## 4. Main System Features

| Feature | Description |
|---|---|
| User Registration and Login | Allows participants to create accounts and access their dashboard |
| Admin Dashboard | Provides seminar management and monitoring functions |
| Seminar Creation | Allows administrators to create seminars and configure schedules |
| Seminar Registration | Allows participants to register for available events |
| QR Code Generation | Generates a unique, secure QR ticket for each registration |
| QR Code Verification | Verifies QR tickets during attendance scanning |
| Live Camera Scanning | Detects QR tickets using a supported device camera |
| QR Image Upload | Allows administrators to scan QR codes from uploaded images |
| Attendance Monitoring | Stores and displays attendance records |
| Event Ending | Allows administrators to finalize seminars |
| Participation Certificates | Provides certificates to eligible participants |
| Attendance Reports | Allows administrators to view and print attendance reports |
| Account Management | Allows administrators to manage locked and existing accounts |
| Responsive Design | Provides mobile-friendly layouts for users and administrators |

---

## 5. CRUD Functionality

Event-Pro implements the four fundamental database operations: **Create, Read, Update, and Delete (CRUD)**.

CRUD operations allow the system to dynamically manage seminars, participant registrations, user accounts, and attendance information stored in the MySQL database.

| CRUD Operation | SQL Command | Implementation in Event-Pro |
|---|---|---|
| **Create** | `INSERT` | Creates user accounts, seminar records, participant registrations, and attendance records |
| **Read** | `SELECT` | Retrieves seminars, participants, user information, registration history, attendance records, and reports |
| **Update** | `UPDATE` | Updates participant attendance status, finalizes seminars, and unlocks deactivated user accounts |
| **Delete** | `DELETE` | Deletes seminar records, removes participants, and deletes user accounts |

### Create

The system allows users to register accounts and register for seminars. Administrators can also create new seminar records.

### Read

Event-Pro retrieves records from the database to display information on the User Dashboard and Admin Dashboard, including seminar schedules, participant registrations, event history, and attendance reports.

### Update

The system updates participant statuses after successful QR verification, changes seminar statuses when events end, and allows administrators to reactivate locked user accounts.

### Delete

Administrators can delete seminars, remove registered participants, and delete user accounts. Related seminar and attendance records are handled by the system's database operations.

**Note:** Event-Pro demonstrates all four CRUD operations across its database entities. It does not currently include a general Edit Seminar form; its Update functionality is implemented through status and account-management operations.

---

## 6. Technologies Used

| Technology | Purpose |
|---|---|
| HTML | Website structure |
| CSS | Website design and responsive layouts |
| JavaScript | Interactive functions, mobile navigation, and QR scanning |
| PHP | Server-side application logic |
| MySQL / MariaDB | Database storage |
| XAMPP | Local development and testing environment |
| html5-qrcode | Camera-based and image-based QR code scanning |
| PHP QR Code | QR code image generation |
| Git | Local version control and branch management |
| GitHub | Source code repository and development history |
| InfinityFree | Live PHP and MySQL website hosting |

---

## 7. How to Install and Run Event-Pro Locally

**Step 1: Install XAMPP**

Install XAMPP on your computer and open its Control Panel.

Start the following services:

- Apache
- MySQL

**Step 2: Download the Project**

Clone or download the repository:

https://github.com/princedoble/Event-Pro

Place the project folder inside:

`C:\xampp\htdocs\elec_proj`

**Step 3: Create the Database**

1. Open `http://localhost/phpmyadmin`.
2. Create a new database named `event_pro`.
3. Select the database.
4. Open the **Import** tab.
5. Choose the `event_pro.sql` file included in the project.
6. Click **Import**.

The SQL file contains the database structure needed by the system.

**Step 4: Configure the Database Connection**

Open:

`config/db.php`

Update the database connection settings to match your local MySQL configuration.

For a typical local XAMPP setup, the connection uses `localhost` as the host, `root` as the username, and the local database name `event_pro`.

Use the credentials configured in your own XAMPP installation.

**Step 5: Set Up an Administrator Account**

The shared SQL file does not contain administrator credentials or personal user data.

A new installation requires the system owner to create the initial administrator account securely, using a password hash generated by PHP's `password_hash()` function.

The account must use the `admin` role.

Do not publish administrator passwords, password hashes, or private hosting credentials in the GitHub repository.

**Step 6: Open Event-Pro**

In your browser, visit:

`http://localhost/elec_proj/home.php`

The system can now be used for participant registration and seminar management after the required account setup is complete.

---

## 8. Database Structure

Event-Pro uses the `event_pro` database containing four main tables:

| Table | Purpose |
|---|---|
| `registration` | Stores user accounts, password hashes, status, and roles |
| `events` | Stores seminar details, dates, capacity, and status |
| `participants` | Stores seminar registrations, participant information, and QR tokens |
| `attendance` | Stores participant attendance records and check-in timestamps |

The system uses unique secure QR tokens to verify participant registrations.

---

## 9. Online Deployment

**Hosting Provider:** InfinityFree  
**Live Website:** https://event-pro.free.nf/  
**Source Code:** https://github.com/princedoble/Event-Pro

Event-Pro has been deployed online using InfinityFree's PHP hosting and MySQL database services.

### Deployment Process

1. Create an InfinityFree hosting account and select a domain.
2. Create a MySQL database through the InfinityFree hosting control panel.
3. Upload the Event-Pro project files into the website's `htdocs` directory.
4. Preserve the original project folder structure, including `pages`, `config`, `assets`, `libs`, and `qrcodes`.
5. Import `event_pro.sql` into the hosted MySQL database using phpMyAdmin.
6. Update `config/db.php` using the correct hosting database hostname, username, password, and database name.
7. Securely create the initial administrator account.
8. Configure `index.php` to open or redirect to the Event-Pro homepage.
9. Access the website through HTTPS.
10. Test login, registration, seminar creation, QR code generation, QR scanning, attendance verification, and certificates.

The website uses HTTPS so supported mobile browsers can request camera access for QR scanning.

**Security Reminder:** Database passwords, private account credentials, and QR token information should not be publicly shared or committed to GitHub.

---

## 10. Using Event-Pro on Mobile Devices

Event-Pro is designed as a browser-based application accessible through supported desktop and mobile browsers.

The system includes responsive improvements for smaller screens.

### Mobile Features

- Responsive homepage layout
- Mobile-friendly registration and login forms
- Collapsible navigation menus in the User and Admin Dashboards
- Mobile-friendly overview statistics and dashboard cards
- Improved display of seminar registrations and event history
- Responsive administrative record views
- Touch-friendly buttons and navigation
- QR scanning through supported mobile browsers

Participants can use a phone to register for seminars, view event history, and display their QR tickets.

Administrators can use a phone's camera to scan QR tickets when the website is accessed over HTTPS and camera access is granted.

The scanner also supports QR detection from uploaded image files.

---

## 11. GitHub Branching and Version Control

Event-Pro uses Git and GitHub to track source-code changes, organize feature development, and maintain the project's main version.

### Main Branch — `main`

The `main` branch serves as the primary branch of Event-Pro.

It contains the application's core features, PHP backend, database integration, QR attendance functionality, and completed responsive improvements.

### Mobile Feature Branch — `feature/mobile-responsive`

A dedicated branch named `feature/mobile-responsive` was used to organize the mobile-responsiveness improvements.

**Purpose of the branch:**

- Develop mobile improvements separately from the main project branch.
- Improve the website's appearance on smartphones and tablets.
- Add mobile-friendly navigation for participant and administrator dashboards.
- Improve registration, login, and dashboard layouts.
- Make wide data tables easier to read on smaller screens.
- Preserve the system's existing PHP and database functionality while improving its interface.

Separating the responsive changes into a feature branch helped organize development and maintain a clear Git history.

### Feature Branch Integration

After the mobile improvements were developed and committed, `feature/mobile-responsive` was merged into `main`.

The integration used a **fast-forward merge**, meaning Git moved the `main` branch forward to the feature branch's latest commit without creating a separate merge commit.

The mobile-responsive development commit was:

`6fd3d6d` — **Make Event-Pro responsive across mobile devices**

### QR Scanner Bug Fix

After the responsive changes, the QR scanner was updated to improve QR detection and image-upload scanning.

The fix addressed scanner configuration and improved handling of live-camera and uploaded-image QR detection.

The existing QR verification and attendance-recording functionality was preserved.

### Development Workflow

1. Create the initial project repository.
2. Develop and commit the core seminar management functionality.
3. Implement QR registration and secure verification.
4. Add QR ticket viewing and participation certificates.
5. Document the project in the README.
6. Create and work on the `feature/mobile-responsive` branch.
7. Test and commit responsive improvements.
8. Merge the feature branch into `main`.
9. Fix and test QR scanner detection.
10. Deploy the application to InfinityFree.
11. Update documentation with the live website and development details.

This process demonstrates meaningful commits, feature branching, integration into the main branch, and live application deployment.

---

## 12. Project Purpose

Event-Pro was developed to demonstrate the use of frontend development, backend programming, database management, CRUD operations, QR code technology, responsive design, Git version control, and web deployment in a functional seminar management application.

The project aims to reduce manual registration work, improve attendance recording, and provide an organized way to manage seminar participants and their certificates.

---

## 13. GitHub Repository and Live Website

**GitHub Repository:** https://github.com/princedoble/Event-Pro

**Live Website:** https://event-pro.free.nf/

The repository contains the system's source code, database structure, development history, and project documentation.

The live website demonstrates Event-Pro running on an online PHP and MySQL hosting environment.

---

**Event-Pro — From Code to Cloud | Technical Elective III | BSCPE 4B**
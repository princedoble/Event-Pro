# Event-Pro
### Seminar Registration and QR Code Attendance Management System

**From Code to Cloud – Technical Elective III**  
**BSCPE 4B**

## 1. Project Overview

Event-Pro is a web-based seminar registration and attendance management system developed to simplify the process of organizing seminars, registering participants, monitoring attendance, and generating participation certificates.

The system has two types of users:

- **Administrator** – Manages seminars, monitors participants, scans QR codes, and generates attendance reports.
- **Participant** – Creates an account, registers for seminars, receives QR tickets, and accesses participation certificates.

Instead of using paper registration forms and manually recording attendance, Event-Pro uses online registration and QR code scanning to make the process more organized and efficient.

## 2. How to Use Event-Pro

### A. For Participants

**Step 1: Create an Account**

1. Open the Event-Pro website.
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
4. Find the completed seminar in your event history or registration records.
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
2. Allow the browser to access your camera.
3. Ask the participant to present their QR ticket.
4. Position the QR code in front of the camera.
5. Wait for Event-Pro to verify the QR code.

The system uses the unique QR token to identify the participant and process attendance verification.

The verification process helps prevent invalid QR codes and duplicate attendance recording.

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

**Step 7: Generate Attendance Reports**

1. Open the administrator's reporting function.
2. Select the relevant seminar or attendance records.
3. View the report.
4. Print the report when needed.

Reports help organizers document seminar participation and attendance.

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

## 4. Main System Features

| Feature | Description |
|---|---|
| User Registration and Login | Allows participants to create accounts and access their dashboard |
| Admin Dashboard | Provides seminar management and monitoring functions |
| Seminar Creation | Allows administrators to create seminars and configure schedules |
| Seminar Registration | Allows participants to register for available events |
| QR Code Generation | Generates a unique QR ticket for each registration |
| QR Code Verification | Verifies QR tickets during attendance scanning |
| Attendance Monitoring | Stores and displays attendance records |
| Event Ending | Allows administrators to mark seminars as completed |
| Participation Certificates | Provides certificates to eligible participants |
| Attendance Reports | Allows administrators to view and print attendance records |

## 5. Technologies Used

| Technology | Purpose |
|---|---|
| HTML | Website structure |
| CSS | Website design and layout |
| JavaScript | Interactive functions and QR scanning |
| PHP | Server-side application logic |
| MySQL / MariaDB | Database storage |
| XAMPP | Local development environment |
| html5-qrcode | Camera-based QR code scanner |
| PHP QR Code | QR code image generation |
| Git and GitHub | Version control and source code repository |
| InfinityFree | Planned live website hosting |

## 6. How to Install and Run Event-Pro Locally

**Step 1: Install XAMPP**

Install XAMPP on your computer and open its Control Panel.

Start the following services:

- Apache
- MySQL

**Step 2: Download the Project**

Clone or download the repository:

https://github.com/princedoble/From-Code-to-Cloud

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

**Step 5: Set Up an Administrator Account**

The shared SQL file does not contain administrator credentials or personal user data.

A new installation requires the system owner to create the initial administrator account securely, using a password hash generated by PHP's `password_hash()` function.

The account must use the `admin` role.

**Step 6: Open Event-Pro**

In your browser, visit:

`http://localhost/elec_proj/home.php`

The system can now be used for participant registration and seminar management after the required account setup is complete.

## 7. Database Structure

Event-Pro uses the `event_pro` database containing four main tables:

| Table | Purpose |
|---|---|
| `registration` | Stores user accounts, password hashes, status, and roles |
| `events` | Stores seminar details, dates, capacity, and status |
| `participants` | Stores seminar registrations, participant information, and QR tokens |
| `attendance` | Stores participant attendance records and check-in timestamps |

The system uses unique QR tokens for verifying participant registrations.

## 8. Online Deployment

**Hosting Provider:** InfinityFree  
**Live Website:** To be added after deployment.

For online deployment:

1. Create an InfinityFree hosting account.
2. Create a MySQL database through the hosting control panel.
3. Upload the Event-Pro project files to the website's `htdocs` directory.
4. Import `event_pro.sql` into the hosting database.
5. Update `config/db.php` using the hosting provider's database hostname and credentials.
6. Securely create the initial administrator account.
7. Configure the website's homepage and enable HTTPS.
8. Test login, seminar registration, QR code generation, QR scanning, attendance verification, and certificates.
9. Add the final live website URL to this README.

The live website should use HTTPS to allow supported mobile browsers to request camera access for QR scanning.

## 9. Using Event-Pro on Mobile Devices

Event-Pro is designed as a browser-based application, so participants and administrators can access it from supported mobile browsers.

Participants can use a phone to register for seminars and display their QR tickets.

Administrators can use a phone's camera to scan QR tickets, provided that the website uses HTTPS and camera permission is granted.

Mobile layout, camera scanning, and certificate printing should be tested after deployment to confirm compatibility.

## 10. Project Purpose

Event-Pro was developed to demonstrate the use of frontend development, backend programming, database management, QR code technology, and web deployment in a functional seminar management application.

The project aims to reduce manual registration work, improve attendance recording, and provide an organized way to manage seminar participants and their certificates.

## 11. GitHub Repository

**Source Code:** https://github.com/princedoble/From-Code-to-Cloud

The repository contains the system's source code, database structure, and project documentation.
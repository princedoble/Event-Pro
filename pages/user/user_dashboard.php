
<?php
session_start();
require_once '../../config/db.php';

if (
    empty($_SESSION['username']) ||
    ($_SESSION['role'] ?? '') !== 'user'
) {
    header('Location: ../auth/login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function dash_h($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$username = (string) $_SESSION['username'];

// Get the logged-in user's email.
$stmt = $conn->prepare(
    "SELECT registration_email
     FROM registration
     WHERE username = ?
       AND role = 'user'
       AND status = 'active'
     LIMIT 1"
);

$stmt->bind_param('s', $username);
$stmt->execute();

$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account) {
    header('Location: ../auth/login.php');
    exit;
}

$email = (string) ($account['registration_email'] ?? '');

// Prepare the ownership filter for dashboard queries.
$eu = mysqli_real_escape_string($conn, $username);
$ee = mysqli_real_escape_string($conn, $email);

$ownership = "(
    p.account_username = '$eu'
    OR (
        (p.account_username = '' OR p.account_username IS NULL)
        AND p.participant_email = '$ee'
    )
)";

/*
|--------------------------------------------------------------------------
| FETCH AN OWNED REGISTRATION
|--------------------------------------------------------------------------
| This function prevents users from accessing another user's
| QR code or certificate by changing the participant ID.
|--------------------------------------------------------------------------
*/

function owned_registration(
    mysqli $conn,
    int $id,
    string $username,
    string $email
): ?array {

    $stmt = $conn->prepare(
        "SELECT
            p.participant_id,
            p.participant_name,
            p.participant_status,
            p.participant_qr,
            p.qr_token,
            e.event_id,
            e.event_name,
            e.event_date,
            e.event_status
         FROM participants p
         INNER JOIN events e
             ON e.event_id = p.event_id
         WHERE p.participant_id = ?
           AND (
               p.account_username = ?
               OR (
                   (
                       p.account_username = ''
                       OR p.account_username IS NULL
                   )
                   AND p.participant_email = ?
               )
           )
         LIMIT 1"
    );

    $stmt->bind_param(
        'iss',
        $id,
        $username,
        $email
    );

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

/*
|--------------------------------------------------------------------------
| READ A QR IMAGE SAFELY
|--------------------------------------------------------------------------
| Returns a base64 data URI so the QR image can be displayed
| without making a second request to PHP.
|--------------------------------------------------------------------------
*/

function get_qr_data_uri(string $relativePath): string
{
    if (!preg_match(
        '~^qrcodes/[a-zA-Z0-9_-]+\.png$~D',
        $relativePath
    )) {
        return '';
    }

    $projectRoot = dirname(__DIR__, 2);

    $qrFolder = realpath(
        $projectRoot . '/qrcodes'
    );

    $fullPath = realpath(
        $projectRoot . '/' . $relativePath
    );

    if (
        $qrFolder === false ||
        $fullPath === false ||
        !str_starts_with(
            $fullPath,
            $qrFolder . DIRECTORY_SEPARATOR
        ) ||
        !is_file($fullPath) ||
        !is_readable($fullPath)
    ) {
        return '';
    }

    $imageData = file_get_contents($fullPath);

    if ($imageData === false) {
        return '';
    }

    // Confirm that this is actually a PNG image.
    $imageInfo = @getimagesizefromstring($imageData);

    if (
        $imageInfo === false ||
        ($imageInfo['mime'] ?? '') !== 'image/png'
    ) {
        return '';
    }

    return 'data:image/png;base64,' .
        base64_encode($imageData);
}

/*
|--------------------------------------------------------------------------
| VIEW QR TICKET
|--------------------------------------------------------------------------
| Displays the QR code from the registration database.
| This is part of user_dashboard.php, not a separate file.
|--------------------------------------------------------------------------
*/

if (isset($_GET['view_qr'])) {

    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    $id = filter_var(
        $_GET['view_qr'],
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Invalid QR ticket ID.');
    }

    $ticket = owned_registration(
        $conn,
        $id,
        $username,
        $email
    );

    if (
        !$ticket ||
        $ticket['participant_status'] !== 'Pending' ||
        $ticket['event_status'] !== 'Active'
    ) {
        http_response_code(403);

        exit(
            'This QR ticket is unavailable. ' .
            'The registration may have already been processed.'
        );
    }

    $qrDataUri = '';

    if (
        !empty($ticket['qr_token']) &&
        preg_match(
            '/^[a-f0-9]{64}$/D',
            (string) $ticket['qr_token']
        )
    ) {
        $qrDataUri = get_qr_data_uri(
            (string) $ticket['participant_qr']
        );
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>My QR Ticket | Event-Pro</title>

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                padding: 20px;
                min-height: 100vh;
                background: #080808;
                color: #f5f5f7;
                font-family: Arial, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .card {
                width: 100%;
                max-width: 450px;
                padding: 35px;
                background: #1c1c1e;
                border: 1px solid #444;
                border-radius: 20px;
                text-align: center;
            }

            h1 {
                color: #f8f32b;
                font-size: 25px;
            }

            h2 {
                font-size: 24px;
            }

            p {
                color: #aaa;
                line-height: 1.6;
            }

            .qr-image {
                display: block;
                width: 250px;
                max-width: 100%;
                margin: 25px auto;
                padding: 12px;
                background: white;
                border-radius: 12px;
            }

            .btn {
                display: block;
                width: 100%;
                padding: 15px;
                margin-top: 12px;
                background: #f8f32b;
                color: black;
                border-radius: 11px;
                font-weight: bold;
                text-decoration: none;
            }

            .btn:hover {
                opacity: .9;
            }

            .back {
                display: block;
                margin-top: 25px;
                color: #aaa;
                text-decoration: none;
            }

            .error {
                color: #ff453a;
                background: rgba(255,69,58,.1);
                border: 1px solid rgba(255,69,58,.3);
                padding: 15px;
                border-radius: 10px;
                margin-top: 20px;
                line-height: 1.6;
            }
        </style>
    </head>

    <body>

        <main class="card">

            <h1>My QR Attendance Ticket</h1>

            <h2>
                <?= dash_h($ticket['event_name']) ?>
            </h2>

            <p>
                Participant:
                <?= dash_h($ticket['participant_name']) ?>
            </p>

            <?php if ($qrDataUri !== ''): ?>

                <img
                    class="qr-image"
                    src="<?= dash_h($qrDataUri) ?>"
                    alt="My Secure QR Attendance Ticket"
                >

                <p>
                    Present this QR code to the administrator
                    when attending your seminar.
                </p>

                <a
                    href="<?= dash_h($qrDataUri) ?>"
                    download="eventpro-qr.png"
                    class="btn"
                >
                    Download QR Code
                </a>

            <?php else: ?>

                <div class="error">
                    Your QR image could not be found or read.

                    Please return to the registration page
                    to regenerate your secure QR ticket.
                </div>

                <a
                    href="generate_qr.php?event_id=<?= (int)$ticket['event_id'] ?>"
                    class="btn"
                >
                    Regenerate QR Ticket
                </a>

            <?php endif; ?>

            <a
                href="user_dashboard.php?tab=upcoming"
                class="back"
            >
                ← Back to My Registrations
            </a>

        </main>

    </body>
    </html>
    <?php

    exit;
}

/*
|--------------------------------------------------------------------------
| CERTIFICATE VIEW
|--------------------------------------------------------------------------
| Completed participants may view and print certificates.
| No separate certificate.php is required.
|--------------------------------------------------------------------------
*/

if (isset($_GET['certificate'])) {

    header('Cache-Control: private, no-store');

    $id = filter_var(
        $_GET['certificate'],
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Invalid certificate ID.');
    }

    $cert = owned_registration(
        $conn,
        $id,
        $username,
        $email
    );

    if (
        !$cert ||
        $cert['participant_status'] !== 'Completed' ||
        $cert['event_status'] !== 'Ended'
    ) {
        http_response_code(403);

        exit(
            'Certificate unavailable. ' .
            'Only completed seminars are eligible.'
        );
    }

    $certificateNumber = sprintf(
        'EP-%06d',
        (int) $cert['participant_id']
    );

    $eventDate = date(
        'F j, Y',
        strtotime($cert['event_date'])
    );

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >

        <title>Certificate | Event-Pro</title>

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                background: #141414;
                font-family: Georgia, serif;
                color: #222;
            }

            .toolbar {
                padding: 20px;
                text-align: center;
                font-family: Arial, sans-serif;
            }

            .toolbar a,
            .toolbar button {
                display: inline-block;
                margin: 5px;
                padding: 12px 20px;
                background: #f8f32b;
                color: #000;
                border: 0;
                border-radius: 9px;
                text-decoration: none;
                font-weight: bold;
                cursor: pointer;
            }

            .certificate {
                width: 1120px;
                max-width: calc(100% - 35px);
                min-height: 790px;
                margin: 12px auto 35px;
                padding: 70px;
                background: #fffdf3;
                text-align: center;
                border: 14px solid #191919;
                outline: 5px solid #c5ad34;
                outline-offset: -32px;
                box-shadow: 0 25px 65px #0007;
            }

            .brand {
                font: bold 25px Arial, sans-serif;
                letter-spacing: 5px;
                margin-bottom: 55px;
            }

            .brand span {
                color: #c5ad34;
            }

            h1 {
                font-size: clamp(32px,5vw,60px);
                text-transform: uppercase;
                letter-spacing: 4px;
                margin: 0;
            }

            .subtitle {
                color: #8c7b2a;
                letter-spacing: 5px;
                text-transform: uppercase;
                font-size: 23px;
            }

            .presented {
                margin-top: 60px;
                font-size: 20px;
            }

            .recipient {
                display: inline-block;
                min-width: 60%;
                max-width: 100%;
                overflow-wrap: anywhere;
                border-bottom: 2px solid #c5ad34;
                padding: 12px;
                font-size: clamp(28px,4vw,46px);
                font-weight: bold;
            }

            .description {
                max-width: 800px;
                margin: 35px auto 0;
                font-size: 21px;
                line-height: 1.8;
            }

            .event-name {
                font-size: 26px;
                font-weight: bold;
                overflow-wrap: anywhere;
            }

            .footer {
                display: flex;
                justify-content: space-between;
                gap: 20px;
                margin-top: 80px;
                font: 14px Arial, sans-serif;
                color: #666;
                text-align: left;
            }

            @page {
                size: A4 landscape;
                margin: 8mm;
            }

            @media print {
                body {
                    background: white;
                    print-color-adjust: exact;
                    -webkit-print-color-adjust: exact;
                }

                .toolbar {
                    display: none;
                }

                .certificate {
                    width: 100%;
                    max-width: none;
                    min-height: 180mm;
                    margin: 0;
                    box-shadow: none;
                    break-inside: avoid;
                }
            }

            @media (max-width: 650px) {
                .certificate {
                    padding: 42px 25px;
                    min-height: 650px;
                }

                .footer {
                    flex-direction: column;
                }
            }
        </style>
    </head>

    <body>

        <div class="toolbar">

            <button
                type="button"
                onclick="window.print()"
            >
                Print / Save as PDF
            </button>

            <a href="user_dashboard.php?tab=history">
                Back to Event History
            </a>

        </div>

        <main class="certificate">

            <div class="brand">
                EVENT<span>–</span>PRO
            </div>

            <h1>Certificate</h1>

            <div class="subtitle">
                of Participation
            </div>

            <p class="presented">
                This certificate is proudly presented to
            </p>

            <div class="recipient">
                <?= dash_h($cert['participant_name']) ?>
            </div>

            <p class="description">
                In recognition of successfully completing
                and participating in the seminar

                <br>

                <span class="event-name">
                    <?= dash_h($cert['event_name']) ?>
                </span>

                <br>

                held on <?= dash_h($eventDate) ?>.
            </p>

            <div class="footer">

                <div>
                    Certificate Reference:
                    <br>
                    <?= dash_h($certificateNumber) ?>
                </div>

                <div>
                    Issued by Event-Pro
                    <br>
                    Digital Participation Certificate
                </div>

            </div>

        </main>

    </body>
    </html>
    <?php

    exit;
}

/*
|--------------------------------------------------------------------------
| DASHBOARD DATA
|--------------------------------------------------------------------------
*/

$upcomingResult = mysqli_query(
    $conn,
    "SELECT
        p.participant_id,
        p.participant_status,
        p.qr_token,
        e.event_id,
        e.event_name,
        e.event_date
     FROM participants p
     JOIN events e
         ON e.event_id = p.event_id
     WHERE $ownership
       AND p.participant_status = 'Pending'
       AND e.event_status = 'Active'
     ORDER BY e.event_date ASC"
);

$upcomingRows = mysqli_fetch_all(
    $upcomingResult,
    MYSQLI_ASSOC
);

$historyResult = mysqli_query(
    $conn,
    "SELECT
        p.participant_id,
        p.participant_status,
        e.event_name,
        e.event_date,
        e.event_status
     FROM participants p
     JOIN events e
         ON e.event_id = p.event_id
     WHERE $ownership
       AND p.participant_status
           IN ('Attended', 'Completed', 'Absent')
     ORDER BY e.event_date DESC"
);

$historyRows = mysqli_fetch_all(
    $historyResult,
    MYSQLI_ASSOC
);

$availableResult = mysqli_query(
    $conn,
    "SELECT
        e.event_id,
        e.event_name,
        e.event_date,
        e.max_capacity,
        (
            SELECT COUNT(*)
            FROM participants pc
            WHERE pc.event_id = e.event_id
        ) AS registered_count
     FROM events e
     WHERE e.event_status = 'Active'
       AND e.event_date >= CURDATE()
       AND NOT EXISTS (
           SELECT 1
           FROM participants p
           WHERE p.event_id = e.event_id
             AND $ownership
       )
     ORDER BY e.event_date ASC"
);

$availableRows = mysqli_fetch_all(
    $availableResult,
    MYSQLI_ASSOC
);

$attendedCount = 0;

foreach ($historyRows as $row) {
    if (in_array(
        $row['participant_status'],
        ['Attended', 'Completed'],
        true
    )) {
        $attendedCount++;
    }
}

// Recent activity.
$recentResult = mysqli_query(
    $conn,
    "SELECT
        p.participant_name,
        p.participant_status,
        e.event_name
     FROM participants p
     JOIN events e
         ON e.event_id = p.event_id
     WHERE $ownership
     ORDER BY p.participant_id DESC
     LIMIT 6"
);

$recentRows = mysqli_fetch_all(
    $recentResult,
    MYSQLI_ASSOC
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>User Dashboard | Event-Pro</title>

    <style>
        :root {
            --bg: #000000;
            --sidebar: #171719;
            --content: #1c1c1e;
            --card: rgba(30,30,32,.75);
            --text: #f5f5f7;
            --muted: #86868b;
            --accent: #f8f32b;
            --danger: #ff453a;
            --border: rgba(255,255,255,.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont,
                         "Segoe UI", Arial, sans-serif;
        }

        .sidebar {
            width: 260px;
            flex-shrink: 0;
            padding: 25px 16px;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .profile {
            padding-bottom: 25px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .profile h2 {
            margin: 0 0 7px;
            font-size: 20px;
        }

        .profile p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .nav-item {
            width: 100%;
            padding: 15px 16px;
            margin-bottom: 9px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--text);
            text-align: left;
            font-size: 15px;
            cursor: pointer;
        }

        .nav-item:hover {
            background: #29292b;
        }

        .nav-item.active {
            color: var(--accent);
            background: rgba(248,243,43,.15);
            font-weight: bold;
        }

        .logout {
            margin-top: auto;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 9px;
            color: var(--muted);
        }

        .logout:hover {
            color: var(--danger);
        }

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 45px 40px;
            background: var(--content);
            overflow-y: auto;
        }

        .tab-content {
            display: none;
            max-width: 1100px;
            margin: 0 auto;
        }

        .tab-content.active {
            display: block;
        }

        h3 {
            font-size: 27px;
            margin: 0 0 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }

        .overview-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card,
        .info-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            padding: 25px;
        }

        .metric-card {
            text-align: center;
        }

        .metric-card h2 {
            margin: 0 0 10px;
            color: var(--accent);
            font-size: 36px;
        }

        .metric-card p {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            margin: 0;
        }

        .overview-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .info-card h4 {
            color: var(--accent);
            margin: 0 0 20px;
            font-size: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .event-item,
        .activity-item {
            border-bottom: 1px solid var(--border);
            padding: 15px 0;
        }

        .event-item:last-child,
        .activity-item:last-child {
            border-bottom: none;
        }

        .event-name {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 7px;
        }

        .event-date {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 13px;
        }

        .progress-track {
            height: 8px;
            border-radius: 8px;
            background: #333;
            overflow: hidden;
            margin: 9px 0;
        }

        .progress-fill {
            height: 100%;
            background: var(--accent);
        }

        .register-btn,
        .action-btn {
            display: inline-block;
            background: var(--accent);
            color: #000;
            text-decoration: none;
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 9px;
            white-space: nowrap;
        }

        .register-btn {
            display: block;
            text-align: center;
            margin-top: 15px;
        }

        .register-btn:hover,
        .action-btn:hover {
            opacity: .9;
        }

        .disabled {
            background: #555;
            color: #ccc;
            pointer-events: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 13px;
            background: #000;
            overflow: hidden;
        }

        th {
            text-align: left;
            padding: 19px 20px;
            border-bottom: 1px solid var(--border);
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
        }

        td {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        tbody tr:hover {
            background: #222;
        }

        .badge {
            display: inline-block;
            border-radius: 22px;
            padding: 6px 12px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .pending {
            background: #3a3a3c;
            color: white;
        }

        .attended {
            background: var(--accent);
            color: #000;
        }

        .completed {
            background: #3a3a3c;
            color: #aaa;
        }

        .absent {
            color: var(--danger);
            background: rgba(255,69,58,.13);
        }

        .empty {
            color: var(--muted);
            text-align: center;
            padding: 30px;
        }

        .small-note {
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 850px) {
            .overview-split {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 25px 20px;
            }
        }

        @media (max-width: 650px) {
            body {
                display: block;
                height: auto;
                overflow: auto;
            }

            .sidebar {
                width: 100%;
            }

            .nav-item {
                width: auto;
                display: inline-block;
            }

            .main-content {
                min-height: 70vh;
            }

            table {
                min-width: 650px;
            }
        }
    </style>
</head>

<body>

    <!-- USER SIDEBAR -->
    <nav class="sidebar">

        <div class="profile">
            <h2>Dashboard</h2>

            <p>
                Welcome,
                <strong>
                    <?= dash_h($username) ?>
                </strong>
            </p>
        </div>

        <button
            type="button"
            class="nav-item active"
            data-tab="overview"
            onclick="openTab(event, 'overview')"
        >
            🧭 Overview
        </button>

        <button
            type="button"
            class="nav-item"
            data-tab="upcoming"
            onclick="openTab(event, 'upcoming')"
        >
            🗓️ My Registrations
        </button>

        <button
            type="button"
            class="nav-item"
            data-tab="history"
            onclick="openTab(event, 'history')"
        >
            ✅ Event History
        </button>

        <a
            href="../auth/logout.php"
            class="logout"
        >
            Logout
        </a>

    </nav>

    <!-- MAIN DASHBOARD -->
    <main class="main-content">

        <!-- OVERVIEW -->
        <section
            id="overview"
            class="tab-content active"
        >
            <h3>System Overview</h3>

            <div class="overview-grid">

                <div class="metric-card">
                    <h2><?= count($availableRows) ?></h2>
                    <p>Available Seminars</p>
                </div>

                <div class="metric-card">
                    <h2><?= count($upcomingRows) ?></h2>
                    <p>My Registrations</p>
                </div>

                <div class="metric-card">
                    <h2><?= $attendedCount ?></h2>
                    <p>Seminars Attended</p>
                </div>

            </div>

            <div class="overview-split">

                <!-- AVAILABLE SEMINARS -->
                <div class="info-card">

                    <h4>Available Seminars</h4>

                    <?php if (!$availableRows): ?>

                        <p class="empty">
                            No available seminars at the moment.
                        </p>

                    <?php endif; ?>

                    <?php foreach ($availableRows as $event): ?>

                        <?php
                        $eventId = (int) $event['event_id'];

                        $registered = (int) $event['registered_count'];
                        $capacity = (int) $event['max_capacity'];

                        $full = (
                            $capacity <= 0 ||
                            $registered >= $capacity
                        );

                        $percent = $capacity > 0
                            ? min(
                                100,
                                round($registered / $capacity * 100)
                            )
                            : 100;
                        ?>

                        <div class="event-item">

                            <div class="event-name">
                                <?= dash_h($event['event_name']) ?>
                            </div>

                            <div class="event-date">
                                <?= date(
                                    'F d, Y',
                                    strtotime($event['event_date'])
                                ) ?>
                            </div>

                            <div style="
                                display:flex;
                                justify-content:space-between;
                                font-size:12px;
                            ">
                                <span>Capacity Filled</span>

                                <strong>
                                    <?= $registered ?> / <?= $capacity ?>
                                </strong>
                            </div>

                            <div class="progress-track">
                                <div
                                    class="progress-fill"
                                    style="width:<?= $percent ?>%"
                                ></div>
                            </div>

                            <?php if ($full): ?>

                                <span class="register-btn disabled">
                                    Seminar Full
                                </span>

                            <?php else: ?>

                                <a
                                    class="register-btn"
                                    href="generate_qr.php?event_id=<?= $eventId ?>"
                                >
                                    Register Now
                                </a>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- RECENT ACTIVITY -->
                <div class="info-card">

                    <h4>Recent Activity</h4>

                    <?php if (!$recentRows): ?>

                        <p class="empty">
                            No recent activity.
                        </p>

                    <?php endif; ?>

                    <?php foreach ($recentRows as $activity): ?>

                        <div class="activity-item">

                            You

                            <?php
                            $status = $activity['participant_status'];

                            if ($status === 'Attended') {
                                echo 'checked into';
                            } elseif ($status === 'Completed') {
                                echo 'completed';
                            } elseif ($status === 'Absent') {
                                echo 'were absent from';
                            } else {
                                echo 'registered for';
                            }
                            ?>

                            <strong>
                                <?= dash_h($activity['event_name']) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>
        </section>

        <!-- MY REGISTRATIONS -->
        <section
            id="upcoming"
            class="tab-content"
        >
            <h3>My Upcoming Registrations</h3>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Seminar Name</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>QR Code</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!$upcomingRows): ?>

                            <tr>
                                <td colspan="4" class="empty">
                                    No upcoming registrations.
                                </td>
                            </tr>

                        <?php endif; ?>

                        <?php foreach ($upcomingRows as $row): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= dash_h($row['event_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= date(
                                        'M d, Y',
                                        strtotime($row['event_date'])
                                    ) ?>
                                </td>

                                <td>
                                    <span class="badge pending">
                                        Registered
                                    </span>
                                </td>

                                <td>
                                    <a
                                        class="action-btn"
                                        href="user_dashboard.php?view_qr=<?= (int)$row['participant_id'] ?>"
                                    >
                                        View QR Code
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>
        </section>

        <!-- EVENT HISTORY -->
        <section
            id="history"
            class="tab-content"
        >
            <h3>My Event History</h3>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Seminar Name</th>
                            <th>Event Date</th>
                            <th>Status</th>
                            <th>Certificate</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!$historyRows): ?>

                            <tr>
                                <td colspan="4" class="empty">
                                    You have no past event history.
                                </td>
                            </tr>

                        <?php endif; ?>

                        <?php foreach ($historyRows as $row): ?>

                            <?php
                            $status = (string) $row['participant_status'];

                            $eligible = (
                                $status === 'Completed' &&
                                $row['event_status'] === 'Ended'
                            );

                            $badgeClass = match ($status) {
                                'Attended' => 'attended',
                                'Completed' => 'completed',
                                'Absent' => 'absent',
                                default => 'pending'
                            };
                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= dash_h($row['event_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= date(
                                        'M d, Y',
                                        strtotime($row['event_date'])
                                    ) ?>
                                </td>

                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= dash_h($status) ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($eligible): ?>

                                        <a
                                            class="action-btn"
                                            href="user_dashboard.php?certificate=<?= (int)$row['participant_id'] ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            View Certificate
                                        </a>

                                    <?php elseif ($status === 'Attended'): ?>

                                        <span class="small-note">
                                            Available after event ends
                                        </span>

                                    <?php else: ?>

                                        <span class="small-note">
                                            Not eligible
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>
        </section>

    </main>

    <script>
        function openTab(evt, tabId) {

            const allowed = [
                'overview',
                'upcoming',
                'history'
            ];

            if (!allowed.includes(tabId)) {
                tabId = 'overview';
            }

            document.querySelectorAll('.tab-content')
                .forEach(tab => {
                    tab.classList.remove('active');
                });

            document.querySelectorAll('.nav-item')
                .forEach(button => {
                    button.classList.remove('active');
                });

            document.getElementById(tabId)
                .classList.add('active');

            const button = document.querySelector(
                '.nav-item[data-tab="' + tabId + '"]'
            );

            if (button) {
                button.classList.add('active');
            }

            const url = new URL(window.location.href);

            url.searchParams.set('tab', tabId);

            window.history.replaceState({}, '', url);
        }

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const params = new URLSearchParams(
                    window.location.search
                );

                openTab(
                    null,
                    params.get('tab') || 'overview'
                );
            }
        );
    </script>

</body>
</html>

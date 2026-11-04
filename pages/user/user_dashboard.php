
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
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$username = (string)$_SESSION['username'];

/* GET LOGGED-IN USER DETAILS */

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

$email = (string)($account['registration_email'] ?? '');

/* OWNERSHIP FILTER */

$eu = mysqli_real_escape_string($conn, $username);
$ee = mysqli_real_escape_string($conn, $email);

$ownership = "(
    p.account_username = '$eu'
    OR (
        (p.account_username = '' OR p.account_username IS NULL)
        AND p.participant_email = '$ee'
    )
)";

/* SECURELY FETCH A USER'S OWN REGISTRATION */

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

/* READ QR FILE SAFELY */

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

/* =========================================
   SECURE QR TICKET VIEW
   ========================================= */

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
            (string)$ticket['qr_token']
        )
    ) {
        $qrDataUri = get_qr_data_uri(
            (string)$ticket['participant_qr']
        );
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My QR Ticket | Event-Pro</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #080808;
            color: #f5f5f7;
            font-family: -apple-system, BlinkMacSystemFont,
                "Segoe UI", Arial, sans-serif;
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
            font-size: 23px;
            overflow-wrap: anywhere;
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

        @media (max-width: 480px) {
            .card {
                padding: 25px 18px;
            }

            h1 {
                font-size: 22px;
            }

            h2 {
                font-size: 20px;
            }
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
            Please regenerate your secure QR ticket.
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

/* =========================================
   CERTIFICATE VIEW
   ========================================= */

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
        (int)$cert['participant_id']
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
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

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

        .certificate h1 {
            font-size: clamp(32px, 5vw, 60px);
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
            font-size: clamp(28px, 4vw, 46px);
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

        @media screen and (max-width: 650px) {
            .certificate {
                min-height: 0;
                padding: 42px 22px;
            }

            .brand {
                font-size: 18px;
                letter-spacing: 3px;
                margin-bottom: 35px;
            }

            .subtitle {
                font-size: 15px;
                letter-spacing: 2px;
            }

            .presented {
                margin-top: 35px;
                font-size: 16px;
            }

            .description {
                font-size: 16px;
            }

            .event-name {
                font-size: 20px;
            }

            .footer {
                flex-direction: column;
                margin-top: 40px;
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

/* =========================================
   FETCH DASHBOARD DATA
   ========================================= */

/* MY UPCOMING REGISTRATIONS */

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

/* EVENT HISTORY */

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

/* AVAILABLE SEMINARS */

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

/* ATTENDANCE STATISTIC */

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

/* RECENT ACTIVITY */

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
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>User Dashboard | Event-Pro</title>

    <style>
        :root {
            --bg: #000000;
            --sidebar: #171719;
            --content: #1c1c1e;
            --card: rgba(30, 30, 32, .75);
            --text: #f5f5f7;
            --muted: #86868b;
            --accent: #f8f32b;
            --danger: #ff453a;
            --border: rgba(255, 255, 255, .1);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
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

        /* DESKTOP SIDEBAR */

        .sidebar {
            width: 260px;
            flex-shrink: 0;
            padding: 25px 16px;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 100;
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

        .nav-links {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .nav-item {
            display: block;
            width: 100%;
            padding: 15px 16px;
            margin-bottom: 9px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--text);
            text-align: left;
            font-family: inherit;
            font-size: 15px;
            cursor: pointer;
        }

        .nav-item:hover {
            background: #29292b;
        }

        .nav-item.active {
            color: var(--accent);
            background: rgba(248, 243, 43, .15);
            font-weight: bold;
        }

        .logout {
            display: block;
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
            border-color: var(--danger);
        }

        /* MOBILE MENU BUTTON */

        .mobile-menu-btn {
            display: none;
        }

        /* MAIN CONTENT */

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

        /* OVERVIEW STATISTICS */

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
            line-height: 1.5;
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
            overflow-wrap: anywhere;
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

        /* BUTTONS */

        .register-btn,
        .action-btn {
            display: inline-block;
            background: var(--accent);
            color: #000;
            text-decoration: none;
            padding: 11px 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 9px;
            text-align: center;
        }

        .register-btn {
            display: block;
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

        /* DESKTOP TABLES */

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
            background: rgba(255, 69, 58, .13);
        }

        .empty {
            color: var(--muted);
            text-align: center;
            padding: 30px;
            line-height: 1.6;
        }

        .small-note {
            color: var(--muted);
            font-size: 12px;
        }

        /* MOBILE CARDS ARE HIDDEN ON DESKTOP */

        .mobile-records {
            display: none;
        }

        @media (max-width: 850px) {
            .overview-split {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 25px 20px;
            }
        }

        /* =====================================
           MOBILE RESPONSIVE DASHBOARD
           ===================================== */

        @media (max-width: 768px) {

            html {
                width: 100%;
                overflow-x: hidden;
            }

            body {
                display: block;
                width: 100%;
                height: auto;
                min-height: 100vh;
                min-height: 100dvh;
                overflow-x: hidden;
                overflow-y: auto;
            }

            /* COMPACT HEADER */

            .sidebar {
                position: sticky;
                top: 0;
                z-index: 1000;
                width: 100%;
                height: auto;
                min-height: 75px;
                padding: 14px 16px;
                border-right: none;
                border-bottom: 1px solid var(--border);
                background: rgba(23, 23, 25, .98);
                display: flex;
                flex-direction: column;
            }

            .profile {
                margin: 0;
                padding: 0 60px 0 0;
                border: none;
            }

            .profile h2 {
                font-size: 20px;
                margin-bottom: 4px;
                color: var(--accent);
            }

            .profile p {
                font-size: 12px;
            }

            /* HAMBURGER BUTTON */

            .mobile-menu-btn {
                display: flex;
                position: absolute;
                top: 15px;
                right: 16px;
                align-items: center;
                justify-content: center;
                width: 44px;
                height: 44px;
                padding: 0;
                border: 1px solid var(--border);
                border-radius: 10px;
                background: #29292b;
                color: var(--accent);
                font-size: 23px;
                line-height: 1;
                cursor: pointer;
            }

            /* HIDDEN MOBILE NAVIGATION */

            .nav-links {
                display: none;
                width: 100%;
                flex-grow: 0;
                margin-top: 16px;
                padding-top: 14px;
                border-top: 1px solid var(--border);
            }

            .nav-links.is-open {
                display: flex;
            }

            .nav-item {
                width: 100%;
                min-height: 46px;
                padding: 13px 15px;
                margin-bottom: 6px;
                font-size: 15px;
            }

            /* LOGOUT INSIDE THE MENU */

            .logout {
                display: block;
                width: 100%;
                min-height: 46px;
                margin-top: 12px;
                padding: 13px;
                font-size: 15px;
            }

            /* CONTENT */

            .main-content {
                width: 100%;
                min-width: 0;
                min-height: calc(100dvh - 75px);
                padding: 24px 16px 45px;
                overflow: visible;
            }

            .tab-content {
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }

            h3 {
                font-size: 24px;
                line-height: 1.3;
                margin-bottom: 22px;
                padding-bottom: 14px;
            }

            /* TWO-COLUMN OVERVIEW */

            .overview-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 20px;
            }

            .metric-card {
                min-width: 0;
                padding: 23px 12px;
            }

            .metric-card h2 {
                font-size: 32px;
                margin-bottom: 8px;
            }

            .metric-card p {
                font-size: 11px;
                letter-spacing: .2px;
            }

            .overview-split {
                grid-template-columns: minmax(0, 1fr);
                gap: 16px;
            }

            .info-card {
                width: 100%;
                min-width: 0;
                padding: 23px 18px;
            }

            .event-item,
            .activity-item {
                overflow-wrap: anywhere;
            }

            /* HIDE DESKTOP TABLES ON MOBILE */

            .desktop-records {
                display: none;
            }

            /* SHOW MOBILE EVENT CARDS */

            .mobile-records {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 15px;
            }

            .record-card {
                width: 100%;
                min-width: 0;
                padding: 19px;
                border: 1px solid var(--border);
                border-radius: 16px;
                background: #242426;
            }

            .record-card h4 {
                font-size: 18px;
                line-height: 1.4;
                margin: 0 0 15px;
                overflow-wrap: anywhere;
            }

            .record-line {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px;
                padding: 12px 0;
                border-top: 1px solid var(--border);
                font-size: 14px;
            }

            .record-label {
                color: var(--muted);
                font-size: 12px;
                text-transform: uppercase;
                font-weight: 600;
            }

            .record-value {
                text-align: right;
                overflow-wrap: anywhere;
            }

            .record-action {
                margin-top: 14px;
            }

            .record-action .action-btn {
                display: block;
                width: 100%;
                min-height: 46px;
                padding: 13px 15px;
                font-size: 14px;
            }

            .record-action .small-note {
                display: block;
                padding: 12px;
                text-align: center;
            }

            .mobile-empty {
                padding: 28px 18px;
                border: 1px solid var(--border);
                border-radius: 15px;
                background: #242426;
                color: var(--muted);
                text-align: center;
                font-size: 14px;
                line-height: 1.6;
            }
        }

        @media (max-width: 380px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 20px 12px 35px;
            }
        }
    </style>
</head>

<body>

<!-- USER NAVIGATION -->

<nav class="sidebar">

    <div class="profile">
        <h2>Dashboard</h2>

        <p>
            Welcome,
            <strong><?= dash_h($username) ?></strong>
        </p>
    </div>

    <button
        type="button"
        class="mobile-menu-btn"
        id="mobileMenuBtn"
        aria-label="Open dashboard menu"
        aria-controls="userNavLinks"
        aria-expanded="false"
    >
        ☰
    </button>

    <div class="nav-links" id="userNavLinks">

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

    </div>

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
                        $eventId = (int)$event['event_id'];
                        $registered = (int)$event['registered_count'];
                        $capacity = (int)$event['max_capacity'];

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
                            gap:10px;
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

        <!-- DESKTOP TABLE -->

        <div class="table-wrap desktop-records">

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

        <!-- MOBILE REGISTRATION CARDS -->

        <div class="mobile-records">

            <?php if (!$upcomingRows): ?>

                <div class="mobile-empty">
                    You have no upcoming registrations.
                </div>

            <?php endif; ?>

            <?php foreach ($upcomingRows as $row): ?>

                <article class="record-card">

                    <h4>
                        <?= dash_h($row['event_name']) ?>
                    </h4>

                    <div class="record-line">
                        <span class="record-label">
                            Event Date
                        </span>

                        <span class="record-value">
                            <?= date(
                                'M d, Y',
                                strtotime($row['event_date'])
                            ) ?>
                        </span>
                    </div>

                    <div class="record-line">
                        <span class="record-label">
                            Status
                        </span>

                        <span class="badge pending">
                            Registered
                        </span>
                    </div>

                    <div class="record-action">

                        <a
                            class="action-btn"
                            href="user_dashboard.php?view_qr=<?= (int)$row['participant_id'] ?>"
                        >
                            View QR Code
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </section>

    <!-- EVENT HISTORY -->

    <section
        id="history"
        class="tab-content"
    >

        <h3>My Event History</h3>

        <!-- DESKTOP TABLE -->

        <div class="table-wrap desktop-records">

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
                            $status = (string)$row['participant_status'];

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

        <!-- MOBILE EVENT HISTORY CARDS -->

        <div class="mobile-records">

            <?php if (!$historyRows): ?>

                <div class="mobile-empty">
                    You have no past event history.
                </div>

            <?php endif; ?>

            <?php foreach ($historyRows as $row): ?>

                <?php
                    $status = (string)$row['participant_status'];

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

                <article class="record-card">

                    <h4>
                        <?= dash_h($row['event_name']) ?>
                    </h4>

                    <div class="record-line">

                        <span class="record-label">
                            Event Date
                        </span>

                        <span class="record-value">
                            <?= date(
                                'M d, Y',
                                strtotime($row['event_date'])
                            ) ?>
                        </span>

                    </div>

                    <div class="record-line">

                        <span class="record-label">
                            Status
                        </span>

                        <span class="badge <?= $badgeClass ?>">
                            <?= dash_h($status) ?>
                        </span>

                    </div>

                    <div class="record-action">

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
                                Certificate available after event ends.
                            </span>

                        <?php else: ?>

                            <span class="small-note">
                                Certificate not eligible.
                            </span>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </section>

</main>

<!-- JAVASCRIPT -->

<script>
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const userNavLinks = document.getElementById('userNavLinks');

    function closeMobileMenu() {
        userNavLinks.classList.remove('is-open');

        mobileMenuBtn.textContent = '☰';

        mobileMenuBtn.setAttribute(
            'aria-expanded',
            'false'
        );

        mobileMenuBtn.setAttribute(
            'aria-label',
            'Open dashboard menu'
        );
    }

    mobileMenuBtn.addEventListener('click', function () {

        const isOpen = userNavLinks.classList.toggle('is-open');

        mobileMenuBtn.textContent = isOpen ? '✕' : '☰';

        mobileMenuBtn.setAttribute(
            'aria-expanded',
            String(isOpen)
        );

        mobileMenuBtn.setAttribute(
            'aria-label',
            isOpen
                ? 'Close dashboard menu'
                : 'Open dashboard menu'
        );
    });

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
            .forEach(function (tab) {
                tab.classList.remove('active');
            });

        document.querySelectorAll('.nav-item')
            .forEach(function (button) {
                button.classList.remove('active');
            });

        const selectedTab = document.getElementById(tabId);

        if (selectedTab) {
            selectedTab.classList.add('active');
        }

        const button = document.querySelector(
            '.nav-item[data-tab="' + tabId + '"]'
        );

        if (button) {
            button.classList.add('active');
        }

        const url = new URL(window.location.href);

        url.searchParams.set('tab', tabId);

        window.history.replaceState({}, '', url);

        closeMobileMenu();

        if (window.innerWidth <= 768) {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
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

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMobileMenu();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });
</script>

</body>
</html>

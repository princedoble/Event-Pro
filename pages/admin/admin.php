
<?php
session_start();
require_once '../../config/db.php';

if (
    !isset($_SESSION['username']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' .
        h($_SESSION['admin_csrf']) . '">';
}

function go_back($tab, $message) {
    header(
        'Location: admin.php?tab=' . urlencode($tab) .
        '&msg=' . urlencode($message),
        true,
        303
    );
    exit();
}

function delete_qr_file($path) {
    if (!preg_match(
        '~^qrcodes/[a-zA-Z0-9_-]+\.png$~D',
        (string)$path
    )) {
        return;
    }

    $full_path = dirname(__DIR__, 2) . '/' . $path;

    if (is_file($full_path)) {
        if (!unlink($full_path)) {
            error_log("Could not delete QR image: " . $full_path);
        }
    }
}

function end_seminar($conn, $event_id) {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE events
         SET event_status = 'Ended'
         WHERE event_id = ? AND event_status = 'Active'"
    );
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE participants
         SET participant_status = 'Completed'
         WHERE event_id = ?
           AND participant_status = 'Attended'"
    );
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE participants
         SET participant_status = 'Absent'
         WHERE event_id = ?
           AND participant_status = 'Pending'"
    );
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Automatically end events AFTER their scheduled end date.
// Example: an event ending May 10 is finalized on May 11.
// This runs whenever an administrator opens the dashboard.
try {
    mysqli_begin_transaction($conn);

    mysqli_query(
        $conn,
        "UPDATE events
         SET event_status = 'Ended'
         WHERE event_status = 'Active'
           AND event_end_date IS NOT NULL
           AND event_end_date < CURDATE()"
    );

    mysqli_query(
        $conn,
        "UPDATE participants p
         JOIN events e ON p.event_id = e.event_id
         SET p.participant_status = 'Completed'
         WHERE e.event_status = 'Ended'
           AND p.participant_status = 'Attended'"
    );

    mysqli_query(
        $conn,
        "UPDATE participants p
         JOIN events e ON p.event_id = e.event_id
         SET p.participant_status = 'Absent'
         WHERE e.event_status = 'Ended'
           AND p.participant_status = 'Pending'"
    );

    mysqli_commit($conn);
} catch (Throwable $error) {
    mysqli_rollback($conn);
    error_log('Automatic event ending failed: ' . $error->getMessage());
}

// Handle admin actions using POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['admin_csrf'], $token)
    ) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }

    $action = (string)($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    $tab = match ($action) {
        'end_event', 'delete_event' => 'seminars',
        'delete_participant' => 'participants',
        'delete_user', 'reactivate_user' => 'accounts',
        default => 'overview'
    };

    if (!$id || $id < 1) {
        go_back($tab, 'Invalid record selected.');
    }

    $qr_files = [];

    try {
        mysqli_begin_transaction($conn);

        switch ($action) {

            case 'end_event':
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT event_id FROM events
                     WHERE event_id = ? FOR UPDATE"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $found = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmt)
                );
                mysqli_stmt_close($stmt);

                if (!$found) {
                    throw new RuntimeException('Seminar not found.');
                }

                end_seminar($conn, $id);
                $message = 'Seminar ended. Attendance finalized.';
                break;

            case 'delete_event':
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT event_id FROM events
                     WHERE event_id = ? FOR UPDATE"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $event_exists = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmt)
                );
                mysqli_stmt_close($stmt);

                if (!$event_exists) {
                    throw new RuntimeException('Seminar not found.');
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT participant_qr FROM participants
                     WHERE event_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $qr_result = mysqli_stmt_get_result($stmt);

                while ($qr = mysqli_fetch_assoc($qr_result)) {
                    $qr_files[] = $qr['participant_qr'];
                }
                mysqli_stmt_close($stmt);

                // Delete attendance records before participants.
                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE a FROM attendance a
                     INNER JOIN participants p
                     ON a.participant_id = p.participant_id
                     WHERE p.event_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM participants WHERE event_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM events WHERE event_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $message = 'Seminar and related records deleted.';
                break;

            case 'delete_participant':
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT participant_qr FROM participants
                     WHERE participant_id = ? FOR UPDATE"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $participant = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmt)
                );
                mysqli_stmt_close($stmt);

                if (!$participant) {
                    throw new RuntimeException('Participant not found.');
                }

                $qr_files[] = $participant['participant_qr'];

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM attendance
                     WHERE participant_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM participants
                     WHERE participant_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $message = 'Participant removed successfully.';
                break;

            case 'reactivate_user':
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE registration
                     SET status = 'active', attempts = 0
                     WHERE id = ? AND role = 'user'"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $message = 'User account unlocked.';
                break;

            case 'delete_user':
                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM registration
                     WHERE id = ? AND role = 'user'"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) === 0) {
                    throw new RuntimeException('User account not found.');
                }

                mysqli_stmt_close($stmt);

                // Historical seminar attendance remains intact.
                $message = 'User account deleted.';
                break;

            default:
                throw new RuntimeException('Unknown admin action.');
        }

        mysqli_commit($conn);

        foreach ($qr_files as $path) {
            delete_qr_file($path);
        }

        go_back($tab, $message);

    } catch (Throwable $error) {
        mysqli_rollback($conn);
        error_log('Admin action failed: ' . $error->getMessage());

        go_back(
            $tab,
            $error instanceof RuntimeException
                ? $error->getMessage()
                : 'Unable to complete the action.'
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Event-Pro</title>

    <style>
        :root {
            --bg-color: #000000;
            --sidebar-bg: rgba(30, 30, 32, 0.75);
            --content-bg: #1c1c1e;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --border-color: rgba(255, 255, 255, 0.1);
            --accent-color: #f8f32b;
            --accent-text: #000000;
            --row-bg: rgba(255, 255, 255, 0.04);
            --row-hover: rgba(255, 255, 255, 0.08);
            --danger-color: #ff453a;
        }

        * { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont,
                "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background: var(--sidebar-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 24px 16px;
            z-index: 10;
        }

        .profile-section {
            padding-bottom: 24px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-section h2 {
            font-size: 18px;
            margin: 0 0 4px;
            color: var(--accent-color);
        }

        .profile-section p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .nav-item {
            width: 100%;
            background: transparent;
            border: none;
            color: var(--text-main);
            text-align: left;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-item:hover {
            background: var(--row-bg);
        }

        .nav-item.active {
            background: rgba(248, 243, 43, 0.15);
            color: var(--accent-color);
            font-weight: 600;
        }

        .admin-actions {
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
            margin-bottom: 16px;
        }

        .btn-logout {
            text-align: center;
            padding: 10px;
            background: transparent;
            border: 1px solid var(--text-muted);
            color: var(--text-muted);
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: block;
        }

        .btn-logout:hover {
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        .main-content {
            flex-grow: 1;
            background: var(--content-bg);
            padding: 40px;
            overflow-y: auto;
            min-width: 0;
        }

        .tab-content {
            display: none;
            max-width: 1000px;
            margin: 0 auto;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h3 {
            font-size: 24px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
            color: var(--accent-color);
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: rgba(30, 30, 32, 0.6);
            backdrop-filter: blur(25px);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .metric-card h2 {
            font-size: 36px;
            margin: 0 0 5px;
            color: var(--text-main);
        }

        .metric-card.alert h2 {
            color: var(--danger-color);
        }

        .metric-card.success h2 {
            color: var(--accent-color);
        }

        .metric-card p {
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 1px;
            color: var(--text-muted);
        }

        .overview-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-card {
            background: rgba(30, 30, 32, 0.6);
            backdrop-filter: blur(25px);
            padding: 30px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-card h4 {
            margin: 0 0 20px;
            font-size: 18px;
            color: var(--accent-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .events-scroll-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .event-item {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .event-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .action-btn-large {
            display: block;
            width: 100%;
            padding: 12px;
            background: var(--accent-color);
            color: var(--accent-text);
            text-align: center;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin-top: 15px;
            transition: 0.2s;
        }

        .action-btn-large:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 14px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--bg-color);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        th {
            text-align: left;
            padding: 14px 16px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
            background: rgba(255, 255, 255, 0.02);
        }

        td {
            padding: 16px;
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: var(--row-hover);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .bg-success {
            background: var(--accent-color);
            color: var(--accent-text);
        }

        .bg-warning {
            background: #8d99ae;
            color: #000000;
        }

        .bg-danger {
            background: var(--danger-color);
            color: white;
        }

        .qr-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid transparent;
            background: #fff;
        }

        .qr-thumb:hover {
            border-color: var(--accent-color);
        }

        .text-action {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }

        .text-delete {
            color: var(--danger-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }

        .text-action:hover,
        .text-delete:hover {
            text-decoration: underline;
        }

        .inline-form {
            display: inline-block;
            margin: 0 12px 0 0;
        }

        .inline-form button {
            padding: 0;
            background: none;
            border: 0;
            cursor: pointer;
            font: inherit;
        }

        .notice {
            max-width: 1000px;
            margin: 0 auto 22px;
            padding: 14px 18px;
            border-radius: 10px;
            background: rgba(248, 243, 43, 0.1);
            border: 1px solid rgba(248, 243, 43, 0.3);
            color: var(--accent-color);
            font-size: 14px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        #modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        #fullImg {
            border: 4px solid var(--accent-color);
            border-radius: 12px;
            max-width: 90%;
            max-height: 80vh;
            background: white;
            padding: 15px;
        }

        @media (max-width: 768px) {
            body {
                display: block;
                height: auto;
                overflow: auto;
            }

            .sidebar {
                width: 100%;
                padding: 16px;
            }

            .nav-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }

            .nav-item {
                width: auto;
            }

            .main-content {
                padding: 24px 16px;
            }

            .overview-split {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="profile-section">
        <h2>Admin Portal</h2>
        <p>
            Welcome,
            <strong><?= h($_SESSION['username']) ?></strong>
        </p>
    </div>

    <ul class="nav-menu">
        <li>
            <button class="nav-item active"
                    data-tab="overview"
                    onclick="openTab(event, 'overview')">
                🧭 System Overview
            </button>
        </li>
        <li>
            <button class="nav-item"
                    data-tab="seminars"
                    onclick="openTab(event, 'seminars')">
                🏢 Manage Seminars
            </button>
        </li>
        <li>
            <button class="nav-item"
                    data-tab="participants"
                    onclick="openTab(event, 'participants')">
                👥 Participants
            </button>
        </li>
        <li>
            <button class="nav-item"
                    data-tab="reports"
                    onclick="openTab(event, 'reports')">
                📊 Reports
            </button>
        </li>
        <li>
            <button class="nav-item"
                    data-tab="accounts"
                    onclick="openTab(event, 'accounts')">
                🔐 Account Access
            </button>
        </li>
    </ul>

    <div class="admin-actions">
        <a href="scanner.php" class="nav-item"
           style="color: var(--accent-color);">
            📷 Open Scanner
        </a>
        <a href="create_event.php" class="nav-item">
            ➕ Create Event
        </a>
    </div>

    <a href="../auth/logout.php" class="btn-logout">
        Logout
    </a>
</nav>

<main class="main-content">

    <?php if (!empty($_GET['msg'])): ?>
        <div class="notice">
            <?= h($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <!-- SYSTEM OVERVIEW -->
    <section id="overview" class="tab-content active">
        <h3>System Overview</h3>

        <?php
        $query_users = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total_count
             FROM registration WHERE role != 'admin'"
        );
        $total_users = (int)mysqli_fetch_assoc(
            $query_users
        )['total_count'];

        $query_sems = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total_count
             FROM events WHERE event_status != 'Ended'"
        );
        $active_seminars = (int)mysqli_fetch_assoc(
            $query_sems
        )['total_count'];

        $query_locked = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total_count
             FROM registration
             WHERE status = 'deactivated'"
        );
        $locked_users = (int)mysqli_fetch_assoc(
            $query_locked
        )['total_count'];

        $q_att = mysqli_query(
            $conn,
            "SELECT
                (SELECT COUNT(*)
                 FROM participants p
                 JOIN events e ON p.event_id = e.event_id
                 WHERE e.event_status = 'Ended') AS total_reg,
                (SELECT COUNT(*)
                 FROM participants p
                 JOIN events e ON p.event_id = e.event_id
                 WHERE e.event_status = 'Ended'
                   AND p.participant_status
                       IN ('Attended', 'Completed')) AS total_att"
        );

        $att_stats = mysqli_fetch_assoc($q_att);

        $avg_attendance = 0;

        if ($att_stats['total_reg'] > 0) {
            $avg_attendance = round(
                ($att_stats['total_att'] /
                $att_stats['total_reg']) * 100
            );
        }
        ?>

        <div class="overview-grid">
            <div class="metric-card success">
                <h2><?= $active_seminars ?></h2>
                <p>Active Seminars</p>
            </div>

            <div class="metric-card">
                <h2><?= $total_users ?></h2>
                <p>Total Ecosystem Users</p>
            </div>

            <div class="metric-card">
                <h2><?= $avg_attendance ?>%</h2>
                <p>Avg. Attendance Rate</p>
            </div>

            <div class="metric-card alert">
                <h2><?= $locked_users ?></h2>
                <p>Locked Accounts</p>
            </div>
        </div>

        <div class="overview-split">

            <div class="info-card">
                <h4>Upcoming Events</h4>

                <div class="events-scroll-container">
                    <?php
                    $q_next = mysqli_query(
                        $conn,
                        "SELECT * FROM events
                         WHERE event_status != 'Ended'
                           AND event_date >= CURDATE()
                         ORDER BY event_date ASC"
                    );

                    if (mysqli_num_rows($q_next) > 0):
                        while ($next_event = mysqli_fetch_assoc($q_next)):

                            $e_id = (int)$next_event['event_id'];

                            $stmt = mysqli_prepare(
                                $conn,
                                "SELECT COUNT(*) AS total_count
                                 FROM participants WHERE event_id = ?"
                            );
                            mysqli_stmt_bind_param($stmt, 'i', $e_id);
                            mysqli_stmt_execute($stmt);

                            $row_capacity = mysqli_fetch_assoc(
                                mysqli_stmt_get_result($stmt)
                            );
                            mysqli_stmt_close($stmt);

                            $current_reg = (int)$row_capacity['total_count'];
                            $max_cap = (int)$next_event['max_capacity'];

                            $percentage = $max_cap > 0
                                ? min(
                                    100,
                                    round(($current_reg / $max_cap) * 100)
                                )
                                : 0;
                    ?>
                        <div class="event-item">
                            <h2 style="margin:0; font-size:24px;">
                                <?= h($next_event['event_name']) ?>
                            </h2>

                            <p style="color:var(--text-muted);
                                      margin-top:5px;font-size:14px;">
                                <?= date(
                                    'l, F jS, Y',
                                    strtotime($next_event['event_date'])
                                ) ?>
                            </p>

                            <div style="margin-top:15px;">
                                <div style="display:flex;
                                            justify-content:space-between;
                                            font-size:13px;
                                            margin-bottom:8px;">
                                    <span>Capacity Filled</span>
                                    <strong style="color:var(--accent-color);">
                                        <?= $current_reg ?> / <?= $max_cap ?>
                                    </strong>
                                </div>

                                <div style="width:100%;
                                            background:rgba(255,255,255,.1);
                                            border-radius:10px;
                                            height:8px;overflow:hidden;">
                                    <div style="width:<?= $percentage ?>%;
                                                background:var(--accent-color);
                                                height:100%;
                                                border-radius:10px;">
                                    </div>
                                </div>
                            </div>

                            <a href="scanner.php?event_id=<?= $e_id ?>"
                               class="action-btn-large">
                                Open Scanner
                            </a>
                        </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <p style="color:var(--text-muted);">
                            No upcoming events scheduled.
                        </p>

                        <a href="create_event.php"
                           class="action-btn-large"
                           style="background:var(--text-main);">
                            ➕ Schedule an Event
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card">
                <h4>Recent Registration Activity</h4>

                <?php
                $q_recent = mysqli_query(
                    $conn,
                    "SELECT p.participant_name,
                            e.event_name,
                            p.participant_status
                     FROM participants p
                     JOIN events e ON p.event_id = e.event_id
                     ORDER BY p.participant_id DESC
                     LIMIT 5"
                );

                if (mysqli_num_rows($q_recent) > 0):
                    while ($activity = mysqli_fetch_assoc($q_recent)):

                        $action = 'registered for';

                        if ($activity['participant_status'] === 'Attended') {
                            $action = 'checked into';
                        } elseif (
                            $activity['participant_status'] === 'Completed'
                        ) {
                            $action = 'completed';
                        } elseif (
                            $activity['participant_status'] === 'Absent'
                        ) {
                            $action = 'was absent from';
                        }
                ?>
                    <div class="activity-item">
                        <strong>
                            <?= h($activity['participant_name']) ?>
                        </strong>
                        <?= h($action) ?>
                        <?= h($activity['event_name']) ?>
                    </div>
                <?php
                    endwhile;
                else:
                ?>
                    <p style="color:var(--text-muted);">
                        No recent activity on the platform.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- MANAGE SEMINARS -->
    <section id="seminars" class="tab-content">
        <h3>Manage Seminars</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Seminar Name</th>
                        <th>Event Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $event_result = mysqli_query(
                        $conn,
                        "SELECT * FROM events
                         ORDER BY event_date DESC"
                    );

                    if (mysqli_num_rows($event_result) > 0):
                        while ($event = mysqli_fetch_assoc($event_result)):
                            $id = (int)$event['event_id'];
                            $ended = $event['event_status'] === 'Ended';
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= h($event['event_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= date(
                                    'F d, Y',
                                    strtotime($event['event_date'])
                                ) ?>
                            </td>

                            <td>
                                <?php if (!empty($event['event_end_date'])): ?>
                                    <?= date(
                                        'F d, Y',
                                        strtotime($event['event_end_date'])
                                    ) ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">
                                        Not scheduled
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($ended): ?>
                                    <span class="badge bg-danger">
                                        Ended
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        Active
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!$ended): ?>
                                    <form method="POST"
                                          class="inline-form"
                                          onsubmit="return confirm(
                                            'End this seminar? Pending participants will become Absent.'
                                          );">
                                        <?= csrf_input() ?>

                                        <input type="hidden"
                                               name="action"
                                               value="end_event">

                                        <input type="hidden"
                                               name="id"
                                               value="<?= $id ?>">

                                        <button type="submit"
                                                class="text-action">
                                            End Seminar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);
                                                 margin-right:15px;">
                                        Finalized
                                    </span>
                                <?php endif; ?>

                                <form method="POST"
                                      class="inline-form"
                                      onsubmit="return confirm(
                                        'Delete this seminar and all its participants and attendance records?'
                                      );">
                                    <?= csrf_input() ?>

                                    <input type="hidden"
                                           name="action"
                                           value="delete_event">

                                    <input type="hidden"
                                           name="id"
                                           value="<?= $id ?>">

                                    <button type="submit"
                                            class="text-delete">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="5"
                                style="text-align:center;
                                       color:var(--text-muted);">
                                No seminars listed.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- REGISTERED PARTICIPANTS -->
    <section id="participants" class="tab-content">
        <h3>Registered Participants</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Seminar</th>
                        <th>QR Code</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $query = "
                        SELECT p.*, e.event_name
                        FROM participants p
                        JOIN events e ON p.event_id = e.event_id
                        ORDER BY p.participant_id DESC
                    ";

                    $result = mysqli_query($conn, $query);

                    if (mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)):

                            $status_class = 'bg-warning';

                            if (in_array(
                                $row['participant_status'],
                                ['Attended', 'Completed'],
                                true
                            )) {
                                $status_class = 'bg-success';
                            }

                            if ($row['participant_status'] === 'Absent') {
                                $status_class = 'bg-danger';
                            }

                            $participant_id = (int)$row['participant_id'];
                    ?>
                        <tr>
                            <td><?= h($row['participant_name']) ?></td>

                            <td><?= h($row['participant_email']) ?></td>

                            <td style="color:var(--accent-color);
                                       font-size:13px;font-weight:500;">
                                <?= h($row['event_name']) ?>
                            </td>

                            <td>
                                <?php
                                $image_path = (string)$row['participant_qr'];

                                if (preg_match(
                                    '~^qrcodes/[a-zA-Z0-9_-]+\.png$~D',
                                    $image_path
                                )):
                                ?>
                                    <img
                                        src="../../<?= h($image_path) ?>"
                                        class="qr-thumb"
                                        onclick="showFull(this.src)"
                                        alt="Participant QR"
                                    >
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">
                                        No QR
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge <?= $status_class ?>">
                                    <?= h($row['participant_status']) ?>
                                </span>
                            </td>

                            <td>
                                <form method="POST"
                                      class="inline-form"
                                      onsubmit="return confirm(
                                        'Remove this participant?'
                                      );">
                                    <?= csrf_input() ?>

                                    <input type="hidden"
                                           name="action"
                                           value="delete_participant">

                                    <input type="hidden"
                                           name="id"
                                           value="<?= $participant_id ?>">

                                    <button type="submit"
                                            class="text-delete">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="6"
                                style="text-align:center;
                                       color:var(--text-muted);">
                                No registrations found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- REPORTS -->
    <section id="reports" class="tab-content">
        <h3>Event Reports</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Seminar Name</th>
                        <th>Date Ended</th>
                        <th>Total Registered</th>
                        <th>Total Attended</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $report_query = mysqli_query(
                        $conn,
                        "SELECT
                            e.event_id,
                            e.event_name,
                            e.event_date,
                            e.event_end_date,
                            (SELECT COUNT(*)
                             FROM participants p
                             WHERE p.event_id = e.event_id)
                                AS total_reg,
                            (SELECT COUNT(*)
                             FROM participants p
                             WHERE p.event_id = e.event_id
                               AND p.participant_status
                                   IN ('Attended','Completed'))
                                AS total_att
                         FROM events e
                         WHERE e.event_status = 'Ended'
                         ORDER BY e.event_date DESC"
                    );

                    if (mysqli_num_rows($report_query) > 0):
                        while ($rep = mysqli_fetch_assoc($report_query)):
                            $report_id = (int)$rep['event_id'];
                            $display_date = $rep['event_end_date']
                                ?: $rep['event_date'];
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= h($rep['event_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= date(
                                    'F d, Y',
                                    strtotime($display_date)
                                ) ?>
                            </td>

                            <td>
                                <?= (int)$rep['total_reg'] ?>
                            </td>

                            <td style="color:var(--accent-color);
                                       font-weight:600;">
                                <?= (int)$rep['total_att'] ?>
                            </td>

                            <td>
                                <a href="print_report.php?event_id=<?= $report_id ?>"
                                   target="_blank"
                                   rel="noopener"
                                   class="text-action"
                                   style="background:rgba(248,243,43,.1);
                                          padding:8px 12px;
                                          border-radius:8px;">
                                    🖨️ Print Report
                                </a>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="5"
                                style="text-align:center;
                                       color:var(--text-muted);
                                       padding:30px;">
                                No ended seminars available for reporting yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ACCOUNT ACCESS -->
    <section id="accounts" class="tab-content">
        <h3>Account Lockout Management</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Failed Attempts</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $user_result = mysqli_query(
                        $conn,
                        "SELECT * FROM registration
                         WHERE role != 'admin'
                         ORDER BY id DESC"
                    );

                    if (mysqli_num_rows($user_result) > 0):
                        while ($users = mysqli_fetch_assoc($user_result)):
                            $user_id = (int)$users['id'];
                            $is_active = $users['status'] === 'active';

                            $account_class = $is_active
                                ? 'bg-success'
                                : 'bg-danger';
                    ?>
                        <tr>
                            <td><?= h($users['username']) ?></td>

                            <td>
                                <span class="badge <?= $account_class ?>">
                                    <?= h($users['status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= (int)$users['attempts'] ?> / 3
                            </td>

                            <td>
                                <?php if (!$is_active): ?>
                                    <form method="POST"
                                          class="inline-form">
                                        <?= csrf_input() ?>

                                        <input type="hidden"
                                               name="action"
                                               value="reactivate_user">

                                        <input type="hidden"
                                               name="id"
                                               value="<?= $user_id ?>">

                                        <button type="submit"
                                                class="text-action">
                                            Unlock
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);
                                                 margin-right:15px;">
                                        Active
                                    </span>
                                <?php endif; ?>

                                <form method="POST"
                                      class="inline-form"
                                      onsubmit="return confirm(
                                        'Permanently delete this user account?'
                                      );">
                                    <?= csrf_input() ?>

                                    <input type="hidden"
                                           name="action"
                                           value="delete_user">

                                    <input type="hidden"
                                           name="id"
                                           value="<?= $user_id ?>">

                                    <button type="submit"
                                            class="text-delete">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="4"
                                style="text-align:center;
                                       color:var(--text-muted);">
                                No users found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<div id="modal" onclick="this.style.display='none'">
    <img id="fullImg" alt="Full size QR Code">
</div>

<script>
    function openTab(evt, tabId) {
        const validTabs = [
            'overview',
            'seminars',
            'participants',
            'reports',
            'accounts'
        ];

        if (!validTabs.includes(tabId)) {
            tabId = 'overview';
        }

        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
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

        history.replaceState({}, '', url);
    }

    function showFull(src) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('fullImg').src = src;
    }

    // Restore the same page after delete, unlock,
    // refresh, or manual end-seminar actions.
    document.addEventListener('DOMContentLoaded', function () {
        const params = new URLSearchParams(window.location.search);
        const selected = params.get('tab') || 'overview';

        openTab(null, selected);
    });
    (function () {
    const mobileCSS = `
        /* Mobile navigation button */

        .admin-mobile-toggle {
            display: none;
        }

        @media (max-width: 768px) {

            html {
                width: 100%;
                overflow-x: hidden;
            }

            body {
                display: block;
                width: 100%;
                min-height: 100vh;
                min-height: 100dvh;
                height: auto;
                overflow-x: hidden;
                overflow-y: auto;
            }

            /* Compact admin header */

            .sidebar {
                position: sticky;
                top: 0;
                left: 0;
                z-index: 1000;

                display: flex;
                flex-direction: column;

                width: 100%;
                height: auto;
                min-height: 74px;
                padding: 14px 16px;

                background: rgba(22, 22, 24, 0.98);
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }

            .profile-section {
                padding: 0 60px 0 0;
                margin: 0;
                border: none;
            }

            .profile-section h2 {
                font-size: 19px;
                margin: 0 0 4px;
            }

            .profile-section p {
                font-size: 12px;
            }

            /* Hamburger menu */

            .admin-mobile-toggle {
                display: flex;
                position: absolute;

                top: 15px;
                right: 16px;

                align-items: center;
                justify-content: center;

                width: 44px;
                height: 44px;

                padding: 0;
                margin: 0;

                background: #29292b;
                color: var(--accent-color);

                border: 1px solid var(--border-color);
                border-radius: 10px;

                font-size: 23px;
                line-height: 1;
                cursor: pointer;
                z-index: 1002;
            }

            /* Hide navigation until menu is opened */

            .sidebar .nav-menu,
            .sidebar .admin-actions,
            .sidebar .btn-logout {
                display: none;
            }

            /* Open navigation */

            .sidebar.mobile-open .nav-menu {
                display: flex;
                flex-direction: column;
                flex-wrap: nowrap;

                width: 100%;
                gap: 5px;

                margin-top: 20px;
                padding-top: 15px;

                border-top: 1px solid var(--border-color);
            }

            .sidebar.mobile-open .nav-menu li {
                width: 100%;
                list-style: none;
            }

            .sidebar .nav-item {
                display: flex;
                align-items: center;

                width: 100%;
                min-height: 45px;

                padding: 12px 14px;
                margin-bottom: 5px;

                font-size: 15px;
                border-radius: 10px;
                text-align: left;
            }

            .sidebar.mobile-open .admin-actions {
                display: block;

                width: 100%;
                margin: 10px 0;
                padding: 14px 0 0;

                border-top: 1px solid var(--border-color);
            }

            /* Logout belongs at bottom of menu */

            .sidebar.mobile-open .btn-logout {
                display: block;

                width: 100%;
                min-height: 45px;

                margin-top: 12px;
                padding: 12px;

                text-align: center;
                border-radius: 10px;
            }

            /* Dashboard content */

            .main-content {
                width: 100%;
                min-width: 0;
                height: auto;

                padding: 24px 16px 40px;

                overflow: visible;
            }

            .tab-content {
                width: 100%;
                min-width: 0;
                max-width: 100%;
            }

            h3 {
                font-size: 23px;
                line-height: 1.35;
            }

            /* Statistics cards */

            .overview-grid {
                display: grid;
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

                gap: 12px;
                margin-bottom: 20px;
            }

            .metric-card {
                min-width: 0;
                padding: 22px 12px;
                border-radius: 14px;
            }

            .metric-card h2 {
                font-size: 30px;
            }

            .metric-card p {
                font-size: 11px;
                letter-spacing: 0.3px;
                line-height: 1.5;
            }

            /* Upcoming events and activity */

            .overview-split {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 16px;
            }

            .info-card {
                width: 100%;
                min-width: 0;

                padding: 22px 16px;
                border-radius: 16px;
            }

            .info-card h4 {
                font-size: 17px;
            }

            .event-item {
                overflow-wrap: anywhere;
            }

            /* Scroll large tables horizontally */

            .table-wrap {
                width: 100%;
                max-width: 100%;

                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table-wrap table {
                min-width: 620px;
            }

            th,
            td {
                padding: 12px;
                font-size: 13px;
            }

            /* QR code popup */

            #fullImg {
                max-width: 90vw;
                max-height: 75dvh;
                object-fit: contain;
            }
        }

        /* Small phones */

        @media (max-width: 360px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 20px 12px 32px;
            }
        }

        /* Desktop remains unchanged */

        @media (min-width: 769px) {
            .admin-mobile-toggle {
                display: none;
            }
        }
    `;

    // Add responsive CSS inside the document head.
    const style = document.createElement('style');
    style.textContent = mobileCSS;
    document.head.appendChild(style);

    // Find the original admin navigation.
    const sidebar = document.querySelector('.sidebar');

    if (!sidebar) {
        return;
    }

    // Create a mobile hamburger button.
    const toggle = document.createElement('button');

    toggle.type = 'button';
    toggle.className = 'admin-mobile-toggle';
    toggle.textContent = '☰';

    toggle.setAttribute('aria-label', 'Open admin menu');
    toggle.setAttribute('aria-expanded', 'false');

    sidebar.appendChild(toggle);

    // Close mobile navigation.
    function closeMobileMenu() {
        sidebar.classList.remove('mobile-open');

        toggle.textContent = '☰';

        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open admin menu');
    }

    // Open and close hamburger navigation.
    toggle.addEventListener('click', function () {
        const opened = sidebar.classList.toggle('mobile-open');

        toggle.textContent = opened ? '✕' : '☰';

        toggle.setAttribute(
            'aria-expanded',
            String(opened)
        );

        toggle.setAttribute(
            'aria-label',
            opened ? 'Close admin menu' : 'Open admin menu'
        );
    });

    // Close menu when admin selects a dashboard section.
    sidebar.querySelectorAll('[data-tab]').forEach(function (button) {
        button.addEventListener('click', closeMobileMenu);
    });

    // Close menu with Escape.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMobileMenu();
        }
    });

    // Reset menu when moving back to desktop.
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });

})();
</script>

<!-- EVENT-PRO MOBILE ADMIN TABLE CARDS -->

<style>
@media (max-width: 768px) {

    /* Prevent sideways page overflow */

    .main-content,
    .tab-content,
    .table-wrap {
        width: 100%;
        min-width: 0;
        max-width: 100%;
    }

    .table-wrap {
        overflow: visible;
    }

    /* Convert wide tables into mobile cards */

    #seminars table,
    #participants table,
    #reports table,
    #accounts table {
        display: block;
        width: 100%;
        min-width: 0 !important;
        margin: 0;
        background: transparent;
        border: none;
        border-radius: 0;
        overflow: visible;
    }

    #seminars thead,
    #participants thead,
    #reports thead,
    #accounts thead {
        display: none;
    }

    #seminars tbody,
    #participants tbody,
    #reports tbody,
    #accounts tbody {
        display: block;
        width: 100%;
    }

    /* Each database row becomes its own card */

    #seminars tbody tr,
    #participants tbody tr,
    #reports tbody tr,
    #accounts tbody tr {
        display: block;
        width: 100%;
        min-width: 0;
        margin-bottom: 16px;
        padding: 10px 17px;
        background: #242426;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
    }

    #seminars tbody tr:hover,
    #participants tbody tr:hover,
    #reports tbody tr:hover,
    #accounts tbody tr:hover {
        background: #29292b;
    }

    /* Stack the data inside each card */

    #seminars tbody td,
    #participants tbody td,
    #reports tbody td,
    #accounts tbody td {
        display: block;
        width: 100%;
        min-width: 0;
        padding: 13px 0;
        border-bottom: 1px solid var(--border-color);
        overflow-wrap: anywhere;
        word-break: normal;
        font-size: 14px;
        line-height: 1.5;
    }

    #seminars tbody td:last-child,
    #participants tbody td:last-child,
    #reports tbody td:last-child,
    #accounts tbody td:last-child {
        border-bottom: none;
    }

    /* Add the column name above each value */

    #seminars tbody td::before,
    #participants tbody td::before,
    #reports tbody td::before,
    #accounts tbody td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 8px;
        color: var(--text-muted);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .6px;
        text-transform: uppercase;
    }

    /* Make the first field stand out */

    #seminars tbody td:first-child,
    #participants tbody td:first-child,
    #reports tbody td:first-child,
    #accounts tbody td:first-child {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-main);
    }

    /* Keep status badges neat */

    #seminars .badge,
    #participants .badge,
    #accounts .badge {
        display: inline-block;
        padding: 7px 13px;
        border-radius: 20px;
        font-size: 12px;
    }

    /* Show the participant QR clearly */

    #participants .qr-thumb {
        width: 85px;
        height: 85px;
        padding: 4px;
        object-fit: contain;
        background: white;
        border-radius: 10px;
    }

    /* Make admin actions easy to tap */

    #seminars .inline-form,
    #participants .inline-form,
    #accounts .inline-form {
        display: inline-block;
        max-width: 100%;
        margin: 5px 8px 5px 0;
        vertical-align: middle;
    }

    #seminars .inline-form button,
    #participants .inline-form button,
    #accounts .inline-form button {
        min-height: 43px;
        padding: 10px 13px;
        border-radius: 9px;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.3;
        cursor: pointer;
    }

    #seminars .inline-form .text-action,
    #accounts .inline-form .text-action {
        background: var(--accent-color);
        color: #000;
    }

    #seminars .inline-form .text-delete,
    #participants .inline-form .text-delete,
    #accounts .inline-form .text-delete {
        background: rgba(255, 69, 58, .1);
        border: 1px solid rgba(255, 69, 58, .35);
        color: var(--danger-color);
    }

    /* Report printing button */

    #reports .text-action {
        display: inline-block;
        min-height: 44px;
        padding: 13px 16px !important;
        text-align: center;
        font-size: 14px;
    }

    /* Empty database messages */

    #seminars .mobile-empty-row,
    #participants .mobile-empty-row,
    #reports .mobile-empty-row,
    #accounts .mobile-empty-row {
        padding: 10px 15px;
    }

    #seminars .mobile-empty-row td,
    #participants .mobile-empty-row td,
    #reports .mobile-empty-row td,
    #accounts .mobile-empty-row td {
        padding: 24px 5px;
        text-align: center !important;
        color: var(--text-muted);
        font-weight: 400;
    }

    #seminars .mobile-empty-row td::before,
    #participants .mobile-empty-row td::before,
    #reports .mobile-empty-row td::before,
    #accounts .mobile-empty-row td::before {
        display: none;
    }

    /* Long headings fit smaller phones */

    #seminars h3,
    #participants h3,
    #reports h3,
    #accounts h3 {
        font-size: clamp(21px, 6vw, 25px);
        overflow-wrap: anywhere;
    }
}
</style>

<script>
/*
 * Add labels to the existing table cells.
 * No PHP queries or admin actions are changed.
 */

(function () {

    function setupAdminMobileCards() {

        const selectors = [
            '#seminars table',
            '#participants table',
            '#reports table',
            '#accounts table'
        ];

        selectors.forEach(function (selector) {

            const table = document.querySelector(selector);

            if (!table) {
                return;
            }

            const headings = Array.from(
                table.querySelectorAll('thead th')
            ).map(function (heading) {
                return heading.textContent.trim();
            });

            table.querySelectorAll('tbody tr').forEach(
                function (row) {

                    const cells = row.querySelectorAll('td');

                    /* Handle empty database messages */

                    if (
                        cells.length === 1 &&
                        cells[0].hasAttribute('colspan')
                    ) {
                        row.classList.add('mobile-empty-row');
                        return;
                    }

                    /* Give each cell its matching column label */

                    cells.forEach(function (cell, index) {

                        cell.setAttribute(
                            'data-label',
                            headings[index] || ''
                        );

                    });
                }
            );
        });
    }

    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            setupAdminMobileCards
        );

    } else {

        setupAdminMobileCards();

    }

})();
</script>

</body>
</html>

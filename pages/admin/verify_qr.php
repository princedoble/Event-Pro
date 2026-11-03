
<?php
session_start();

require_once '../../config/db.php';

// Admin only
if (
    !isset($_SESSION['username']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$msg = 'No QR scan was received.';
$status_type = 'error';

$admin_event_id = filter_var(
    $_GET['event_id'] ?? null,
    FILTER_VALIDATE_INT
) ?: 0;

$qr_data = trim((string) ($_GET['qr_data'] ?? ''));

// New QR format from generate_qr.php:
// EVENTPRO: followed by a 64-character secure token.
if ($admin_event_id < 1) {

    $msg = 'Please select a seminar before scanning.';

} elseif (
    !preg_match(
        '/^EVENTPRO:([a-f0-9]{64})$/D',
        $qr_data,
        $matches
    )
) {

    $msg = 'Invalid QR code format. Please use a new '
         . 'Event-Pro secure QR pass.';

} else {

    $qr_token = $matches[1];

    try {
        mysqli_begin_transaction($conn);

        // Locate the secure ticket without trusting an event ID
        // written into the QR code.
        $sql = "
            SELECT
                p.participant_id,
                p.participant_name,
                p.participant_status,
                p.event_id,
                e.event_name,
                e.event_status
            FROM participants p
            INNER JOIN events e
                ON e.event_id = p.event_id
            WHERE p.qr_token = ?
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            's',
            $qr_token
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $participant = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if (!$participant) {

            $msg = 'QR ticket not found. The ticket may be '
                 . 'invalid or may have been replaced.';

        } elseif (
            (int) $participant['event_id'] !== $admin_event_id
        ) {

            $msg = 'SECURITY ALERT: This QR code belongs '
                 . 'to a different seminar!';

        } elseif (
            $participant['event_status'] !== 'Active'
        ) {

            $msg = 'This seminar has already ended. '
                 . 'Attendance is no longer available.';

        } elseif (
            $participant['participant_status'] !== 'Pending'
        ) {

            $status_type = 'warning';

            $msg = 'Duplicate Scan: This participant has '
                 . 'already checked in or their '
                 . 'registration is no longer pending.';

        } else {

            $participant_id = (int)
                $participant['participant_id'];

            // Prevent simultaneous scans from creating
            // multiple successful check-ins.
            $update_sql = "
                UPDATE participants
                SET participant_status = 'Attended'
                WHERE participant_id = ?
                  AND participant_status = 'Pending'
            ";

            $update_stmt = mysqli_prepare(
                $conn,
                $update_sql
            );

            mysqli_stmt_bind_param(
                $update_stmt,
                'i',
                $participant_id
            );

            mysqli_stmt_execute($update_stmt);

            if (
                mysqli_stmt_affected_rows($update_stmt) !== 1
            ) {
                throw new RuntimeException(
                    'The participant could not be checked in.'
                );
            }

            mysqli_stmt_close($update_stmt);

            // Save attendance time in the existing table.
            $attendance_sql = "
                INSERT INTO attendance
                    (
                        participant_id,
                        time_in,
                        attendee_status
                    )
                VALUES
                    (?, NOW(), 'Present')
            ";

            $attendance_stmt = mysqli_prepare(
                $conn,
                $attendance_sql
            );

            mysqli_stmt_bind_param(
                $attendance_stmt,
                'i',
                $participant_id
            );

            mysqli_stmt_execute($attendance_stmt);
            mysqli_stmt_close($attendance_stmt);

            $status_type = 'success';

            $msg = 'Access Granted: '
                 . $participant['participant_name']
                 . ' is checked in for '
                 . $participant['event_name']
                 . '!';
        }

        mysqli_commit($conn);

    } catch (Throwable $exception) {

        mysqli_rollback($conn);

        error_log(
            'Event-Pro QR verification error: '
            . $exception->getMessage()
        );

        $status_type = 'error';

        $msg = 'Unable to complete check-in. '
             . 'Please try scanning again.';
    }
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

    <title>Scan Result | Event-Pro</title>

    <style>
        :root {
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --success: #32d74b;
            --warning: #ffd60a;
            --error: #ff453a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont,
                "Segoe UI", Roboto, Helvetica, Arial, sans-serif;

            background: var(--bg-color);

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;
            overflow: hidden;
            position: relative;
            padding: 20px;
        }

        /* Original animated background */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 10s infinite ease-in-out alternate;
            z-index: 0;
            pointer-events: none;
        }

        .orb-1 {
            width: 350px;
            height: 350px;
            top: 15%;
            right: 20%;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 300px;
            height: 300px;
            bottom: 15%;
            left: 15%;
            animation-delay: -3s;
        }

        .bg-success .orb {
            background: var(--success);
        }

        .bg-warning .orb {
            background: var(--warning);
        }

        .bg-error .orb {
            background: var(--error);
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(-30px, 40px) scale(1.1);
            }
        }

        /* Original glass-style result card */
        .result-card {
            background: rgba(30, 30, 32, 0.6);

            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);

            padding: 40px 30px;
            border-radius: 24px;

            width: 100%;
            max-width: 440px;

            text-align: center;

            border: 1px solid rgba(255, 255, 255, 0.1);

            box-shadow: 0 25px 50px rgba(0,0,0,0.5);

            animation: popIn 0.4s cubic-bezier(
                0.175, 0.885, 0.32, 1.275
            );

            z-index: 1;
            transition: all 0.3s ease;
        }

        @keyframes popIn {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Green success result */
        .result-card.success {
            border-color: rgba(50, 215, 75, 0.4);
            box-shadow: 0 10px 40px rgba(50, 215, 75, 0.15);
        }

        .result-card.success .icon {
            color: var(--success);
            text-shadow: 0 0 20px rgba(50, 215, 75, 0.5);
        }

        .result-card.success .btn-return {
            background: var(--success);
            color: #000;
        }

        /* Yellow duplicate result */
        .result-card.warning {
            border-color: rgba(255, 214, 10, 0.4);
            box-shadow: 0 10px 40px rgba(255, 214, 10, 0.15);
        }

        .result-card.warning .icon {
            color: var(--warning);
            text-shadow: 0 0 20px rgba(255, 214, 10, 0.5);
        }

        .result-card.warning .btn-return {
            background: var(--warning);
            color: #000;
        }

        /* Red error result */
        .result-card.error {
            border-color: rgba(255, 69, 58, 0.4);
            box-shadow: 0 10px 40px rgba(255, 69, 58, 0.15);
        }

        .result-card.error .icon {
            color: var(--error);
            text-shadow: 0 0 20px rgba(255, 69, 58, 0.5);
        }

        .result-card.error .btn-return {
            background: var(--error);
            color: #fff;
        }

        .icon {
            font-size: 70px;
            margin-bottom: 15px;
        }

        h2 {
            color: var(--text-main);
            margin: 0 0 10px;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        p {
            color: #a1a1a6;
            margin: 0 0 35px;
            font-size: 15px;
            line-height: 1.5;
            font-weight: 500;
        }

        .btn-return {
            display: block;
            width: 100%;
            padding: 16px;
            text-decoration: none;
            border-radius: 14px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, opacity 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-return:hover {
            opacity: 0.9;
            transform: scale(0.98);
        }
    </style>
</head>

<body class="bg-<?= h($status_type) ?>">

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="result-card <?= h($status_type) ?>">

        <div class="icon">
            <?php if ($status_type === 'success'): ?>
                ✅
            <?php elseif ($status_type === 'warning'): ?>
                ⚠️
            <?php else: ?>
                ❌
            <?php endif; ?>
        </div>

        <h2>
            <?= strtoupper(h($status_type)) ?>
        </h2>

        <p><?= h($msg) ?></p>

        <a
            href="scanner.php?event_id=<?= $admin_event_id ?>"
            class="btn-return"
        >
            Scan Next Person
        </a>

    </div>

</body>
</html>

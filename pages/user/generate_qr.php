
<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';
require_once '../../libs/phpqrcode/qrlib.php';

if (
    empty($_SESSION['username']) ||
    ($_SESSION['role'] ?? '') !== 'user'
) {
    header('Location: ../auth/login.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function reg_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalize_name(string $name): string {
    return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
}

$username = (string)$_SESSION['username'];
$eventId = filter_var(
    $_GET['event_id'] ?? null,
    FILTER_VALIDATE_INT
) ?: 0;

$error = '';
$success = '';
$qrImage = '';
$event = null;
$existing = null;
$userEmail = '';
$fullName = '';

try {
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
        http_response_code(403);
        exit('Account is not active.');
    }

    $userEmail = (string)$account['registration_email'];

    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException(
            'Your account does not have a valid email.'
        );
    }

    if ($eventId < 1) {
        throw new RuntimeException('Select a valid seminar.');
    }

    $stmt = $conn->prepare(
        "SELECT event_id, event_name, event_date,
                event_status, max_capacity
         FROM events
         WHERE event_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        throw new RuntimeException('Seminar not found.');
    }

    $stmt = $conn->prepare(
        "SELECT participant_id, participant_name,
                participant_status, participant_qr,
                qr_token
         FROM participants
         WHERE event_id = ?
           AND (
               account_username = ?
               OR participant_email = ?
           )
         LIMIT 1"
    );
    $stmt->bind_param(
        'iss',
        $eventId,
        $username,
        $userEmail
    );
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $fullName = (string)$existing['participant_name'];

        $savedPath = (string)$existing['participant_qr'];

        if (
            $existing['participant_status'] === 'Pending' &&
            preg_match(
                '~^qrcodes/[a-zA-Z0-9_-]+\.png$~D',
                $savedPath
            ) &&
            !empty($existing['qr_token']) &&
            is_file('../../' . $savedPath)
        ) {
            $qrImage = '../../' . $savedPath;
            $success = 'Your existing QR ticket is ready.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();

        $fullName = normalize_name(
            (string)($_POST['real_name'] ?? '')
        );

        if (
            $fullName === '' ||
            strlen($fullName) > 255 ||
            preg_match('/[\x00-\x1F\x7F]/', $fullName)
        ) {
            throw new RuntimeException(
                'Enter a valid full name.'
            );
        }

        $newFile = '';
        $conn->begin_transaction();

        try {
            // Lock the seminar to serialize registrations.
            $stmt = $conn->prepare(
                "SELECT event_id, event_date,
                        event_status, max_capacity
                 FROM events
                 WHERE event_id = ?
                 FOR UPDATE"
            );
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $lockedEvent = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                !$lockedEvent ||
                $lockedEvent['event_status'] !== 'Active' ||
                $lockedEvent['event_date'] < date('Y-m-d')
            ) {
                throw new RuntimeException(
                    'Registration for this seminar is closed.'
                );
            }

            // Recheck the logged-in account inside the lock.
            $stmt = $conn->prepare(
                "SELECT participant_id, participant_name,
                        participant_status, participant_qr,
                        qr_token
                 FROM participants
                 WHERE event_id = ?
                   AND (
                       account_username = ?
                       OR participant_email = ?
                   )
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->bind_param(
                'iss',
                $eventId,
                $username,
                $userEmail
            );
            $stmt->execute();
            $registered = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (
                $registered &&
                $registered['participant_status'] !== 'Pending'
            ) {
                throw new RuntimeException(
                    'Your registration has already been '
                    . 'processed for attendance.'
                );
            }

            if (!$registered) {
                /*
                 * NEW SECURITY CHECK
                 *
                 * Compare names inside the same event.
                 * Ignore letter case and repeated spaces.
                 *
                 * MariaDB 10.4 / MySQL 8:
                 * REGEXP_REPLACE is supported.
                 */
                $stmt = $conn->prepare(
                    "SELECT participant_id
                     FROM participants
                     WHERE event_id = ?
                       AND LOWER(
                           TRIM(
                               REGEXP_REPLACE(
                                   participant_name,
                                   '[[:space:]]+',
                                   ' '
                               )
                           )
                       ) = LOWER(?)
                     LIMIT 1"
                );
                $stmt->bind_param(
                    'is',
                    $eventId,
                    $fullName
                );
                $stmt->execute();
                $sameName = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($sameName) {
                    throw new RuntimeException(
                        'This full name is already registered '
                        . 'for this seminar. If this is another '
                        . 'person with the same name, please '
                        . 'contact the administrator.'
                    );
                }

                // Capacity check.
                $stmt = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM participants
                     WHERE event_id = ?"
                );
                $stmt->bind_param('i', $eventId);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (
                    (int)$count['total'] >=
                    (int)$lockedEvent['max_capacity']
                ) {
                    throw new RuntimeException(
                        'Sorry, this seminar is fully booked.'
                    );
                }
            }

            // If a secure ticket already exists, reuse it.
            $reuse = false;

            if ($registered) {
                $oldPath = (string)$registered['participant_qr'];

                if (
                    preg_match(
                        '/^[a-f0-9]{64}$/D',
                        (string)$registered['qr_token']
                    ) &&
                    preg_match(
                        '~^qrcodes/[a-zA-Z0-9_-]+\.png$~D',
                        $oldPath
                    ) &&
                    is_file('../../' . $oldPath)
                ) {
                    $reuse = true;
                    $qrImage = '../../' . $oldPath;
                }
            }

            if (!$reuse) {
                $token = bin2hex(random_bytes(32));
                $fileId = bin2hex(random_bytes(16));

                $directory = '../../qrcodes/';

                if (
                    !is_dir($directory) &&
                    !mkdir($directory, 0755, true) &&
                    !is_dir($directory)
                ) {
                    throw new RuntimeException(
                        'Unable to create the QR folder.'
                    );
                }

                $filename = 'ticket_' . $fileId . '.png';
                $newFile = $directory . $filename;
                $dbPath = 'qrcodes/' . $filename;

                QRcode::png(
                    'EVENTPRO:' . $token,
                    $newFile,
                    QR_ECLEVEL_M,
                    5,
                    2
                );

                if (!is_file($newFile)) {
                    throw new RuntimeException(
                        'Unable to create QR image.'
                    );
                }

                if ($registered) {
                    // Reissue an older Pending ticket.
                    $participantId = (int)$registered['participant_id'];

                    $stmt = $conn->prepare(
                        "UPDATE participants
                         SET qr_token = ?,
                             participant_qr = ?
                         WHERE participant_id = ?
                           AND participant_status = 'Pending'"
                    );
                    $stmt->bind_param(
                        'ssi',
                        $token,
                        $dbPath,
                        $participantId
                    );
                } else {
                    // New registration.
                    $stmt = $conn->prepare(
                        "INSERT INTO participants
                         (
                             participant_name,
                             participant_email,
                             event_id,
                             participant_qr,
                             participant_status,
                             account_username,
                             qr_token
                         )
                         VALUES (?, ?, ?, ?, 'Pending', ?, ?)"
                    );
                    $stmt->bind_param(
                        'ssisss',
                        $fullName,
                        $userEmail,
                        $eventId,
                        $dbPath,
                        $username,
                        $token
                    );
                }

                $stmt->execute();
                $stmt->close();

                $qrImage = $newFile;
            }

            $conn->commit();

            $success = $registered
                ? 'Your secure QR ticket is ready.'
                : 'Registration successful!';

        } catch (Throwable $exception) {
            $conn->rollback();

            if ($newFile !== '' && is_file($newFile)) {
                unlink($newFile);
            }

            throw $exception;
        }
    }

} catch (Throwable $exception) {
    error_log(
        'Event-Pro registration error: ' .
        $exception->getMessage()
    );

    if (
        $exception instanceof RuntimeException
    ) {
        $error = $exception->getMessage();
    } elseif (
        $exception instanceof mysqli_sql_exception &&
        $exception->getCode() === 1062
    ) {
        $error = 'A registration already exists '
               . 'for this account or email.';
    } else {
        $error = 'Unable to complete registration. '
               . 'Please try again.';
    }

    $qrImage = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Registration | Event-Pro</title>
<style>
    :root {
        --bg: #000;
        --card: rgba(30,30,32,.85);
        --accent: #f8f32b;
        --muted: #a1a1a6;
        --danger: #ff453a;
        --success: #32d74b;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        padding: 25px 15px;
        display: flex;
        justify-content: center;
        align-items: center;
        background: var(--bg);
        color: #f5f5f7;
        font-family: -apple-system, BlinkMacSystemFont,
                     "Segoe UI", Arial, sans-serif;
    }
    .container {
        width: 100%;
        max-width: 450px;
        padding: 36px;
        background: var(--card);
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 22px;
        text-align: center;
        box-shadow: 0 24px 55px rgba(0,0,0,.5);
    }
    h2 { margin: 0 0 12px; font-size: 27px; }
    .subtitle {
        color: var(--muted);
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 28px;
    }
    .field { text-align: left; margin-bottom: 20px; }
    label {
        display: block;
        color: var(--muted);
        font-size: 13px;
        margin-bottom: 8px;
    }
    input {
        width: 100%;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,.15);
        background: #101010;
        color: white;
        font-size: 15px;
    }
    input[readonly] { color: var(--accent); }
    input:focus {
        outline: none;
        border-color: var(--accent);
    }
    button, .action {
        display: inline-block;
        width: 100%;
        background: var(--accent);
        color: black;
        padding: 15px;
        border: 0;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }
    button:hover, .action:hover { opacity: .9; }
    button:disabled { opacity: .5; cursor: not-allowed; }
    .alert {
        padding: 14px;
        border-radius: 12px;
        margin: 18px 0;
        font-size: 14px;
        line-height: 1.6;
    }
    .error {
        color: var(--danger);
        background: rgba(255,69,58,.1);
        border: 1px solid rgba(255,69,58,.4);
    }
    .success {
        color: var(--success);
        background: rgba(50,215,75,.1);
        border: 1px solid rgba(50,215,75,.4);
    }
    .qr {
        display: block;
        width: 225px;
        max-width: 100%;
        margin: 22px auto;
        padding: 12px;
        background: white;
        border-radius: 12px;
    }
    .back {
        display: block;
        color: var(--muted);
        text-decoration: none;
        margin-top: 30px;
        font-size: 14px;
    }
    .back:hover { color: var(--accent); }
</style>
</head>
<body>
<main class="container">
    <h2>Event Registration</h2>
    <p class="subtitle">
        Confirm your details to receive your
        secure Event-Pro QR attendance pass.
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert error"><?= reg_h($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert success"><?= reg_h($success) ?></div>
    <?php endif; ?>

    <?php if ($qrImage !== ''): ?>
        <h3>Your QR Attendance Pass</h3>
        <p class="subtitle">
            <?= reg_h($event['event_name'] ?? '') ?>
        </p>

        <img
            class="qr"
            src="<?= reg_h($qrImage) ?>"
            alt="Your seminar QR ticket"
        >

        <a
            class="action"
            href="<?= reg_h($qrImage) ?>"
            download
        >
            Download QR Code
        </a>

    <?php elseif ($event && $userEmail !== ''): ?>

        <?php if (
            $existing &&
            $existing['participant_status'] !== 'Pending'
        ): ?>
            <div class="alert success">
                Your attendance status is
                <?= reg_h($existing['participant_status']) ?>.
            </div>
        <?php else: ?>

            <form
                method="POST"
                action="generate_qr.php?event_id=<?= $eventId ?>"
            >
                <?= csrf_field() ?>

                <div class="field">
                    <label>Selected Seminar</label>
                    <input
                        value="<?= reg_h($event['event_name']) ?>"
                        readonly
                    >
                </div>

                <div class="field">
                    <label for="real_name">Full Name</label>
                    <input
                        type="text"
                        id="real_name"
                        name="real_name"
                        maxlength="255"
                        value="<?= reg_h($fullName) ?>"
                        placeholder="Enter your full name"
                        required
                    >
                </div>

                <div class="field">
                    <label>Registered Email</label>
                    <input
                        value="<?= reg_h($userEmail) ?>"
                        readonly
                    >
                </div>

                <button
                    type="submit"
                    name="register"
                    value="1"
                    <?php if (
                        $event['event_status'] !== 'Active' ||
                        $event['event_date'] < date('Y-m-d')
                    ): ?>
                        disabled
                    <?php endif; ?>
                >
                    <?= $existing
                        ? 'Generate Secure QR Pass'
                        : 'Confirm & Get QR Code' ?>
                </button>
            </form>

        <?php endif; ?>
    <?php endif; ?>

    <a href="user_dashboard.php?tab=upcoming" class="back">
        ← Back to My Registrations
    </a>
</main>
</body>
</html>

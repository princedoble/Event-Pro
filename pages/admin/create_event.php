
<?php
session_start();
require_once '../../config/db.php';

// Only administrators can create seminars.
if (
    !isset($_SESSION['username']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$msg = '';
$error = '';

if (empty($_SESSION['create_event_csrf'])) {
    $_SESSION['create_event_csrf'] = bin2hex(random_bytes(32));
}

function event_escape($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Keep entered values when validation fails.
$name = '';
$date = '';
$end_date = '';
$max = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted_token) ||
        !hash_equals(
            $_SESSION['create_event_csrf'],
            $submitted_token
        )
    ) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }

    $name = trim((string)($_POST['event_name'] ?? ''));
    $date = trim((string)($_POST['event_date'] ?? ''));
    $end_date = trim((string)($_POST['event_end_date'] ?? ''));
    $max = trim((string)($_POST['max_capacity'] ?? ''));

    $capacity = filter_var(
        $max,
        FILTER_VALIDATE_INT
    );

    $start_date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    $finish_date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $end_date
    );

    if ($name === '' || strlen($name) > 255) {
        $error = 'Please enter a valid seminar name.';

    } elseif (
        !$start_date ||
        $start_date->format('Y-m-d') !== $date
    ) {
        $error = 'Please select a valid event date.';

    } elseif (
        !$finish_date ||
        $finish_date->format('Y-m-d') !== $end_date
    ) {
        $error = 'Please select a valid event end date.';

    } elseif ($finish_date < $start_date) {
        $error = 'Event end date cannot be before the event date.';

    } elseif ($capacity === false || $capacity < 1) {
        $error = 'Maximum participants must be at least 1.';

    } else {
        try {
            $query = "
                INSERT INTO events (
                    event_name,
                    event_date,
                    event_end_date,
                    max_capacity,
                    event_status
                )
                VALUES (?, ?, ?, ?, 'Active')
            ";

            $stmt = mysqli_prepare($conn, $query);

            mysqli_stmt_bind_param(
                $stmt,
                'sssi',
                $name,
                $date,
                $end_date,
                $capacity
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $msg = "Seminar '{$name}' created with a limit of "
                 . "{$capacity} participants!";

            // Clear the fields after successful insertion.
            $name = '';
            $date = '';
            $end_date = '';
            $max = '';

            // Rotate CSRF token after successful submission.
            $_SESSION['create_event_csrf'] =
                bin2hex(random_bytes(32));

        } catch (Throwable $exception) {
            error_log(
                'Event-Pro create seminar error: '
                . $exception->getMessage()
            );

            $error = 'Unable to create the seminar. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Event | Event-Pro</title>

    <style>
        :root {
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --accent-color: #f8f32b;
            --accent-text: #000000;
            --danger-color: #ff453a;
            --success-bg: rgba(248, 243, 43, 0.1);
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont,
                "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-main);
            overflow-x: hidden;
            position: relative;
            padding: 30px 15px;
            box-sizing: border-box;
        }

        /* --- Animated Background Orbs --- */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: float 10s infinite ease-in-out alternate;
            z-index: 0;
            pointer-events: none;
        }

        .orb-1 {
            width: 300px;
            height: 300px;
            background: var(--text-muted);
            top: 15%;
            left: 20%;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 350px;
            height: 350px;
            background: var(--accent-color);
            bottom: 10%;
            right: 25%;
            animation-delay: -4s;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(30px, -50px) scale(1.1);
            }
        }

        /* --- Glassmorphism Form --- */
        .create-card {
            background: rgba(30, 30, 32, 0.6);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 40px;
            border-radius: 20px;
            width: 350px;
            max-width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1;
        }

        h2 {
            color: var(--text-main);
            margin-top: 0;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 24px;
            letter-spacing: 0.5px;
        }

        /* --- Inputs --- */
        .input-group {
            position: relative;
            margin-bottom: 16px;
            text-align: left;
        }

        .input-label {
            display: block;
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 6px;
            margin-left: 4px;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-sizing: border-box;
            color: var(--text-main);
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
        }

        input[type="date"] {
            color-scheme: dark;
        }

        input::placeholder {
            color: var(--text-muted);
        }

        input:focus {
            border-color: var(--accent-color);
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 0 0 4px rgba(248, 243, 43, 0.1);
        }

        /* --- Buttons & Links --- */
        .submit_btn {
            background: var(--accent-color);
            color: var(--accent-text);
            width: 100%;
            padding: 14px;
            margin-top: 10px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: transform 0.2s, opacity 0.2s;
        }

        .submit_btn:hover {
            opacity: 0.9;
            transform: scale(0.98);
        }

        .success_msg {
            color: var(--accent-color);
            background: var(--success-bg);
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            border: 1px solid rgba(248, 243, 43, 0.3);
            font-weight: 500;
            line-height: 1.5;
        }

        .error_msg {
            color: var(--danger-color);
            background: rgba(255, 69, 58, 0.1);
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            border: 1px solid rgba(255, 69, 58, 0.3);
            line-height: 1.5;
        }

        .back-link {
            display: inline-block;
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--accent-color);
        }

        .field-note {
            display: block;
            margin: 6px 4px 0;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 480px) {
            .create-card {
                padding: 28px 22px;
            }
        }
    </style>
</head>

<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="create-card">
        <form method="POST" id="create_event">

            <h2>Create Seminar</h2>

            <input
                type="hidden"
                name="csrf_token"
                value="<?= event_escape(
                    $_SESSION['create_event_csrf']
                ) ?>"
            >

            <!-- Seminar Name -->
            <div class="input-group">
                <label class="input-label" for="event_name">
                    Seminar Name
                </label>

                <input
                    type="text"
                    id="event_name"
                    name="event_name"
                    placeholder="Seminar Name"
                    maxlength="255"
                    value="<?= event_escape($name) ?>"
                    required
                >
            </div>

            <!-- Event Date -->
            <div class="input-group">
                <label class="input-label" for="event_date">
                    Event Date
                </label>

                <input
                    type="date"
                    id="event_date"
                    name="event_date"
                    value="<?= event_escape($date) ?>"
                    required
                >
            </div>

            <!-- Event End Date -->
            <div class="input-group">
                <label class="input-label" for="event_end_date">
                    Event End Date
                </label>

                <input
                    type="date"
                    id="event_end_date"
                    name="event_end_date"
                    value="<?= event_escape($end_date) ?>"
                    required
                >

                <span class="field-note">
                    After this date, pending participants will be marked
                    Absent, and checked-in participants will be marked
                    Completed when the admin dashboard runs its event check.
                </span>
            </div>

            <!-- Maximum Capacity -->
            <div class="input-group">
                <label class="input-label" for="max_capacity">
                    Maximum Participants
                </label>

                <input
                    type="number"
                    id="max_capacity"
                    name="max_capacity"
                    placeholder="Max Participants (e.g. 100)"
                    min="1"
                    max="1000000"
                    value="<?= event_escape($max) ?>"
                    required
                >
            </div>

            <button
                type="submit"
                class="submit_btn"
                name="add_event"
                value="1"
            >
                Create Event
            </button>

            <?php if ($msg !== ''): ?>
                <div class="success_msg">
                    <?= event_escape($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="error_msg">
                    <?= event_escape($error) ?>
                </div>
            <?php endif; ?>

            <a href="admin.php?tab=seminars" class="back-link">
                ← Back to Manage Seminars
            </a>

        </form>
    </div>

    <script>
        const eventDate = document.getElementById('event_date');
        const endDate = document.getElementById('event_end_date');

        function updateEndDateMinimum() {
            endDate.min = eventDate.value;

            if (
                eventDate.value &&
                endDate.value &&
                endDate.value < eventDate.value
            ) {
                endDate.value = eventDate.value;
            }
        }

        eventDate.addEventListener(
            'change',
            updateEndDateMinimum
        );

        updateEndDateMinimum();
    </script>

</body>
</html>

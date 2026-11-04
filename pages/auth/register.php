
<?php
require_once '../../config/db.php';

$error = '';

if (isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rawPassword = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $rawPassword === '') {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT username, registration_email
             FROM registration
             WHERE registration_email = ? OR username = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 'ss', $email, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($existing) {
            if ($existing['registration_email'] === $email) {
                $error = "This email address is already in use!";
            } else {
                $error = "This username is already taken!";
            }
        } else {
            $passwordHash = password_hash(
                $rawPassword,
                PASSWORD_DEFAULT
            );

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO registration
                (username, registration_email, password,
                 status, role, attempts)
                 VALUES (?, ?, ?, 'active', 'user', 0)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                'sss',
                $username,
                $email,
                $passwordHash
            );

            try {
                mysqli_stmt_execute($stmt);
                header('Location: login.php?msg=Account%20Created');
                exit();
            } catch (mysqli_sql_exception $e) {
                error_log($e->getMessage());
                $error = "Unable to create account. Please try again.";
            } finally {
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Event-Pro</title>

    <style>
        :root {
            --bg-color: #000;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --accent-color: #f8f32b;
            --danger-color: #ff453a;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            padding: 85px 20px 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: var(--bg-color);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont,
                "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
            position: relative;
            isolation: isolate;
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .5;
            z-index: -1;
            pointer-events: none;
            animation: float 10s infinite ease-in-out alternate;
        }

        .orb-1 {
            width: 350px;
            height: 350px;
            background: #8d99ae;
            top: 15%;
            right: 20%;
        }

        .orb-2 {
            width: 300px;
            height: 300px;
            background: #f8f32b;
            bottom: 15%;
            left: 20%;
            animation-delay: -3s;
        }

        @keyframes float {
            from {
                transform: translate(0, 0);
            }
            to {
                transform: translate(-30px, 50px);
            }
        }

        .back-home {
            position: absolute;
            top: 25px;
            left: 25px;
            font-size: 15px;
            color: var(--text-muted);
            text-decoration: none;
            z-index: 10;
        }

        .back-home:hover {
            color: var(--accent-color);
        }

        .register-card {
            width: 100%;
            max-width: 420px;
            padding: 40px 35px;
            background: rgba(30, 30, 32, .72);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, .5);
            text-align: center;
        }

        h2 {
            color: var(--text-main);
            margin: 0 0 26px;
            font-size: 27px;
            font-weight: 600;
        }

        .input-group {
            margin-bottom: 16px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            display: block;
            width: 100%;
            min-width: 0;
            min-height: 50px;
            padding: 14px 16px;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 12px;
            outline: none;
            background: rgba(0, 0, 0, .45);
            color: var(--text-main);
            font-family: inherit;
            font-size: 16px;
        }

        input::placeholder {
            color: var(--text-muted);
        }

        input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(248, 243, 43, .1);
        }

        .submit_btn {
            display: block;
            width: 100%;
            min-height: 50px;
            margin-top: 10px;
            padding: 14px;
            background: var(--accent-color);
            color: #000;
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .submit_btn:hover {
            opacity: .9;
        }

        .error-msg {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: rgba(255, 69, 58, .1);
            border: 1px solid rgba(255, 69, 58, .3);
            color: var(--danger-color);
            font-size: 14px;
        }

        .login-link {
            display: inline-block;
            margin-top: 24px;
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.5;
            text-decoration: none;
        }

        .login-link:hover {
            color: var(--accent-color);
        }

        @media (max-width: 600px) {
            body {
                padding: 75px 16px 28px;
            }

            .back-home {
                top: 20px;
                left: 16px;
                font-size: 14px;
            }

            .register-card {
                max-width: 100%;
                padding: 30px 22px;
                border-radius: 18px;
            }

            h2 {
                font-size: 26px;
            }

            .orb-1,
            .orb-2 {
                width: 220px;
                height: 220px;
            }
        }

        @media (max-width: 360px) {
            .register-card {
                padding: 26px 16px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .orb {
                animation: none;
            }
        }
    </style>
</head>
<body>

    <a href="../../home.php" class="back-home">
        ← Back to Home
    </a>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <form method="POST" class="register-card">
        <h2>Join Event-Pro</h2>

        <?php if ($error !== ''): ?>
            <div class="error-msg" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="input-group">
            <input
                type="text"
                name="username"
                placeholder="Choose a Username"
                autocomplete="username"
                required
            >
        </div>

        <div class="input-group">
            <input
                type="email"
                name="email"
                placeholder="Your Email Address"
                autocomplete="email"
                required
            >
        </div>

        <div class="input-group">
            <input
                type="password"
                name="password"
                placeholder="Create a Password"
                autocomplete="new-password"
                required
            >
        </div>

        <input
            type="submit"
            class="submit_btn"
            name="register"
            value="Create Account"
        >

        <a href="login.php" class="login-link">
            Already have an account? Sign in
        </a>
    </form>

</body>
</html>

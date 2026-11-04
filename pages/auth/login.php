
<?php
session_start();
require_once '../../config/db.php';

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please fill in all fields.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM registration WHERE username = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = "User not found!";
        } elseif ($user['status'] === 'deactivated') {
            $error = "Account Deactivated! Please contact the admin.";
        } elseif (password_verify($password, $user['password'])) {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE registration SET attempts = 0 WHERE id = ?"
            );
            $userId = (int)$user['id'];
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            session_regenerate_id(true);

            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_id'] = $userId;

            if ($user['role'] === 'admin') {
                header('Location: ../admin/admin.php');
            } else {
                header('Location: ../user/user_dashboard.php');
            }
            exit();

        } else {
            $newAttempts = (int)$user['attempts'] + 1;
            $userId = (int)$user['id'];

            if ($newAttempts >= 3) {
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE registration
                     SET attempts = 3, status = 'deactivated'
                     WHERE id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $error = "Too many attempts! Account deactivated.";
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE registration SET attempts = ? WHERE id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'ii', $newAttempts, $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $error = "Invalid password! " .
                    (3 - $newAttempts) . " attempts left.";
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
    <title>Login - Event-Pro</title>

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
            align-items: center;
            justify-content: center;
            background: var(--bg-color);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont,
                "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            position: relative;
            overflow-x: hidden;
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
            width: 300px;
            height: 300px;
            background: #f8f32b;
            top: 20%;
            left: 20%;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: #8d99ae;
            right: 10%;
            bottom: 10%;
            animation-delay: -5s;
        }

        @keyframes float {
            from {
                transform: translate(0, 0);
            }
            to {
                transform: translate(30px, -50px);
            }
        }

        .back-home {
            position: absolute;
            top: 25px;
            left: 25px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 15px;
            z-index: 10;
        }

        .back-home:hover {
            color: var(--accent-color);
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(30, 30, 32, .72);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, .5);
            text-align: center;
        }

        h2 {
            font-size: 27px;
            margin: 0 0 28px;
            font-weight: 600;
        }

        .input-group {
            margin-bottom: 16px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            min-width: 0;
            min-height: 50px;
            padding: 14px 16px;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 12px;
            outline: none;
            background: rgba(0, 0, 0, .45);
            color: var(--text-main);
            font-size: 16px;
            font-family: inherit;
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
            padding: 14px;
            margin-top: 10px;
            border: none;
            border-radius: 12px;
            background: var(--accent-color);
            color: #000;
            font-family: inherit;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .submit_btn:hover {
            opacity: .9;
        }

        .error {
            margin-top: 16px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 69, 58, .3);
            background: rgba(255, 69, 58, .1);
            color: var(--danger-color);
            font-size: 14px;
        }

        .reg-link {
            display: inline-block;
            margin-top: 24px;
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.5;
            text-decoration: none;
        }

        .reg-link:hover {
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

            .login-card {
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
            .login-card {
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

    <div class="login-card">
        <form method="POST">
            <h2>Welcome Back</h2>

            <div class="input-group">
                <input
                    type="text"
                    name="username"
                    placeholder="Username"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="input-group">
                <input
                    type="password"
                    name="password"
                    placeholder="Password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <input
                type="submit"
                class="submit_btn"
                name="login"
                value="Sign In"
            >

            <?php if ($error !== ''): ?>
                <div class="error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <a href="register.php" class="reg-link">
                Don't have an account? Register
            </a>
        </form>
    </div>

</body>
</html>

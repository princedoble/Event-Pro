<?php
// 1. START SESSION & CONNECT TO DB
// session_start() is required so we can save the user's login state across all pages.
session_start();

// ROUTING FIX 1: Step back twice to reach the config folder
include '../../config/db.php'; 

// 2. CHECK IF LOGIN BUTTON WAS CLICKED
if (isset($_POST['login'])) {
    
    // 3. CAPTURE & SECURE INPUT
    // mysqli_real_escape_string prevents hackers from typing SQL commands into the username box.
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password']; // Password isn't escaped because password_verify() handles it safely.

    // 4. CHECK FOR EMPTY FIELDS
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        
        // 5. FIND THE USER IN THE DATABASE
        $sql = "SELECT * FROM registration WHERE username = '$username'";
        $result = mysqli_query($conn, $sql);
        $user = mysqli_fetch_assoc($result);

        // If the user exists in the database...
        if ($user) {
            
            // 6. CHECK ACCOUNT STATUS (Lockout Protection)
            if ($user['status'] === 'deactivated') { 
                // If they failed 3 times previously, block them immediately without checking the password.
                $error = "Account Deactivated! Please contact the admin.";
            } 
            // 7. VERIFY THE PASSWORD
            // password_verify compares the plain text password they typed with the scrambled hash in the DB.
            elseif (password_verify($password, $user['password'])) {
                
                // SUCCESS! Reset their failed attempts back to 0.
                $reset = "UPDATE registration SET attempts = 0 WHERE username = '$username'";
                mysqli_query($conn, $reset);
                
                // 8. CREATE THE USER SESSION
                // Save their info in the server's memory so other pages know who is logged in.
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_id'] = $user['username_id'];

                // 9. ROLE-BASED ROUTING
                // ROUTING FIX 2: Step back one folder (../) to enter either the admin or user folders
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/admin.php');
                } else {
                    header('Location: ../user/user_dashboard.php');
                }
                exit();

            } else {
                // 10. FAILED LOGIN LOGIC (Wrong Password)
                // Add +1 to their current failed attempt count.
                $new_attempts = $user['attempts'] + 1;
                
                if ($new_attempts >= 3) {
                    // If they hit 3 strikes, update the DB to lock their account ('deactivated').
                    $lock = "UPDATE registration SET attempts = 3, status = 'deactivated' WHERE username = '$username'";
                    mysqli_query($conn, $lock);
                    $error = "Too many attempts! Account deactivated.";
                } else {
                    // Otherwise, just update the attempts counter in the DB and warn them.
                    $update = "UPDATE registration SET attempts = $new_attempts WHERE username = '$username'";
                    mysqli_query($conn, $update);
                    $error = "Invalid password! " . (3 - $new_attempts) . " attempts left.";
                }
            }
        } else {
            // The username doesn't exist in the database at all.
            $error = "User not found!";
        }
    }
}
?>
<title>Login - Event-Pro</title>
<style>
        /* --- CSS VARIABLES --- */
        :root {
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --accent-color: #f8f32b;
            --accent-text: #000000;
            --danger-color: #ff453a;
        }

        /* --- MAIN PAGE LAYOUT --- */
        body { 
            margin: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color);
            height: 100vh; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            color: var(--text-main);
            overflow: hidden;
            position: relative;
        }

        /* --- ANIMATED BACKGROUND ORBS --- */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: float 10s infinite ease-in-out alternate;
            z-index: 0;
        }

        .orb-1 {
            width: 300px;
            height: 300px;
            background: #f8f32b;
            top: 20%;
            left: 25%;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: #8d99ae;
            bottom: 10%;
            right: 20%;
            animation-delay: -5s;
        }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, -50px) scale(1.1); }
        }

        /* --- GLASSMORPHISM LOGIN CARD --- */
        .login-card { 
            background: rgba(30, 30, 32, 0.6);
            backdrop-filter: blur(25px); /* iOS Frosted Glass Effect */
            -webkit-backdrop-filter: blur(25px);
            padding: 40px; 
            border-radius: 20px; 
            width: 340px; 
            text-align: center; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1; /* Keeps the card in front of the floating orbs */
        }

        h2 { 
            color: var(--text-main); 
            margin-top: 0;
            margin-bottom: 30px; 
            font-weight: 600;
            font-size: 24px;
            letter-spacing: 0.5px;
        }

        /* --- INPUT FIELDS --- */
        .input-group {
            position: relative;
            margin-bottom: 16px;
        }

        input[type="text"], input[type="password"] { 
            width: 100%; 
            padding: 14px 16px; 
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; 
            box-sizing: border-box; 
            color: var(--text-main);
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
        }

        input::placeholder {
            color: var(--text-muted);
        }

        input:focus { 
            border-color: var(--accent-color); 
            background: rgba(0,0,0,0.6);
            box-shadow: 0 0 0 4px rgba(248, 243, 43, 0.1); /* Yellow glow on click */
        }

        /* --- BUTTONS & LINKS --- */
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

        /* Dynamic Error Box (Only shows if $error isn't empty) */
        .error { 
            color: var(--danger-color); 
            background: rgba(255, 69, 58, 0.1); 
            padding: 12px; 
            border-radius: 8px; 
            margin-top: 16px; 
            font-size: 13px; 
            font-weight: 500;
            border: 1px solid rgba(255, 69, 58, 0.3);
        }

        .reg-link { 
            display: inline-block;
            margin-top: 24px;
            font-size: 14px; 
            color: var(--text-muted);
            text-decoration: none; 
            transition: color 0.2s;
        }
        
        .reg-link:hover { 
            color: var(--accent-color); 
        }

        /* --- BACK TO HOME BUTTON --- */
        .back-home {
            position: absolute;
            top: 30px;
            left: 30px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: color 0.2s;
            z-index: 10;
        }

        .back-home:hover {
            color: var(--accent-color);
        }
    </style>

<body>
    
    <a href="../../home.php" class="back-home">← Back to Home</a>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="login-card">
        <form method="POST">
            <h2>Welcome Back</h2>
            
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            
            <input type="submit" class="submit_btn" name="login" value="Sign In">
            
            <?php if(!empty($error)) echo "<div class='error'>$error</div>"; ?>
            
            <a href="register.php" class="reg-link">Don't have an account? Register</a>
        </form>
    </div>

</body>

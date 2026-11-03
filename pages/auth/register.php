<?php
// 1. DATABASE CONNECTION
include '../../config/db.php'; 

$error = ""; // Variable to hold our new error messages

// 2. FORM SUBMISSION CHECK
if(isset($_POST['register'])){
    
    // 3. CAPTURE & SECURE USER INPUT (SQL Injection Patch applied!)
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']); 
    $raw_password = $_POST['password'];
    
    // 4. THE DUPLICATE CHECK (The Bug Fix!)
    // We check the database to see if this email OR username already exists
    $check_query = mysqli_query($conn, "SELECT * FROM registration WHERE registration_email = '$email' OR username = '$username'");
    
    if(mysqli_num_rows($check_query) > 0) {
        // If a match is found, figure out if it was the email or the username that caused the error
        $existing_user = mysqli_fetch_assoc($check_query);
        if($existing_user['registration_email'] === $email) {
            $error = "This email address is already in use! Please use a different one.";
        } else {
            $error = "This username is already taken! Please choose another one.";
        }
    } else {
        // 5. NO DUPLICATES FOUND? CREATE THE ACCOUNT!
        $password = password_hash($raw_password, PASSWORD_DEFAULT);

        mysqli_query($conn, "INSERT INTO registration(username, registration_email, password, status, role, attempts) 
                             VALUES('$username', '$email', '$password', 'active', 'user', 0)");
        
        header("Location: login.php?msg=Account Created");
        exit();  
    }
}
?>
<title>Register - Event-Pro</title>
<style> 
        :root {
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --accent-color: #f8f32b;
            --accent-text: #000000;
            --danger-color: #ff453a;
        }

        /* --- MAIN PAGE SETUP --- */
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

        .orb-1 { width: 350px; height: 350px; background: #8d99ae; top: 15%; right: 25%; animation-delay: 0s; }
        .orb-2 { width: 300px; height: 300px; background: #f8f32b; bottom: 15%; left: 20%; animation-delay: -3s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(-30px, 50px) scale(1.1); }
        }

        /* --- GLASSMORPHISM FORM CONTAINER --- */
        form { 
            background: rgba(30, 30, 32, 0.6); 
            backdrop-filter: blur(25px);       
            -webkit-backdrop-filter: blur(25px); 
            padding: 40px; 
            border-radius: 20px; 
            width: 340px; 
            text-align: center; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.5); 
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1; 
        }

        h2 { 
            color: var(--text-main); 
            margin-top: 0;
            margin-bottom: 20px; 
            font-weight: 600;
            font-size: 24px;
            letter-spacing: 0.5px;
        }

        /* --- ERROR MESSAGE --- */
        .error-msg {
            background: rgba(255, 69, 58, 0.1);
            color: var(--danger-color);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border: 1px solid rgba(255, 69, 58, 0.3);
            font-weight: 500;
        }

        /* --- FORM INPUT FIELDS --- */
        .input-group {
            position: relative;
            margin-bottom: 16px;
        }

        input[type="text"], input[type="email"], input[type="password"] { 
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

        input::placeholder { color: var(--text-muted); }

        input:focus { 
            border-color: var(--accent-color); 
            background: rgba(0,0,0,0.6);
            box-shadow: 0 0 0 4px rgba(248, 243, 43, 0.1); 
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

        .login-link { 
            display: inline-block;
            margin-top: 24px;
            font-size: 14px; 
            color: var(--text-muted);
            text-decoration: none; 
            transition: color 0.2s;
        }
        
        .login-link:hover { color: var(--accent-color); }

        /* --- BACK TO HOME LINK --- */
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

        .back-home:hover { color: var(--accent-color); }
    </style>

<body>

     <a href="../../home.php" class="back-home">← Back to Home</a>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <form method="POST">
        <h2>Join Event-Pro</h2>
        
        <?php if(!empty($error)) echo "<div class='error-msg'>$error</div>"; ?>
        
        <div class="input-group">
            <input type="text" name="username" placeholder="Choose a Username" required>
        </div>
        
        <div class="input-group">
            <input type="email" name="email" placeholder="Your Email Address" required>
        </div>
        
        <div class="input-group">
            <input type="password" name="password" placeholder="Create a Password" required>
        </div>
        
        <input type="submit" class="submit_btn" name="register" value="Create Account">
        
        <a href="login.php" class="login-link">Already have an account? Sign in</a>
    </form>
</body>

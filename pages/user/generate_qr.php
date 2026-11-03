<?php
// 1. START SESSION & INCLUDES
session_start();

// ROUTING FIX 1: Step back twice to reach the config folder
include '../../config/db.php'; 

// ROUTING FIX 2: Step back twice to reach the libs folder
include '../../libs/phpqrcode/qrlib.php'; 

// 2. SECURITY CHECK
if(!isset($_SESSION['username'])) { 
    // ROUTING FIX 3: Point to the login page inside the auth folder
    header("Location: ../auth/login.php"); 
    exit(); 
}

// 3. AUTO-PATCH: DATABASE UPGRADE
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM participants LIKE 'account_username'");
if(mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE participants ADD COLUMN account_username VARCHAR(100) DEFAULT ''");
}

$show_results = false; 
$error = ""; 

$logged_user = $_SESSION['username'];

// 4. FETCH USER'S REGISTERED EMAIL
$email_query = mysqli_query($conn, "SELECT registration_email FROM registration WHERE username = '$logged_user'");
$email_data = mysqli_fetch_assoc($email_query);

$user_email = "";
if (isset($email_data['registration_email'])) {
    $user_email = $email_data['registration_email'];
}

// 5. GET THE EVENT ID FROM THE URL
$target_event_id = 0;
if (isset($_GET['event_id'])) {
    $target_event_id = intval($_GET['event_id']); 
}

$event_name_display = "Unknown Event";

// 6. FETCH EVENT DETAILS
if ($target_event_id > 0) {
    $evt_query = mysqli_query($conn, "SELECT event_name FROM events WHERE event_id = '$target_event_id'");
    
    if ($evt_row = mysqli_fetch_assoc($evt_query)) {
        $event_name_display = $evt_row['event_name'];
    } else {
        $error = "Invalid seminar selected. Please return to the dashboard.";
    }
} else {
    $error = "No seminar selected! Please go back to the dashboard and choose a seminar.";
}

// ==========================================
// 7. MAIN REGISTRATION LOGIC
// ==========================================
if (isset($_POST['register'])) {
    
    $name = mysqli_real_escape_string($conn, $_POST['real_name']); 
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $event_id = intval($_POST['event_id']); 
    
    // A. CAPACITY CHECK
    $event_check = mysqli_query($conn, "SELECT max_capacity FROM events WHERE event_id = '$event_id'");
    $event_data = mysqli_fetch_assoc($event_check);
    
    $max = 0; 
    if (isset($event_data['max_capacity'])) {
        $max = intval($event_data['max_capacity']);
    }

    $count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM participants WHERE event_id = '$event_id'");
    $count_data = mysqli_fetch_assoc($count_res);
    
    if ($count_data['total'] >= $max) {
        $error = "Sorry, this seminar is fully booked!";
    } else {
        
        // B. DUPLICATE CHECK
        $check = mysqli_query($conn, "SELECT * FROM participants WHERE account_username = '$logged_user' AND event_id = '$event_id'");

        if (mysqli_num_rows($check) > 0) {
            $error = "You are already registered for this event!";
        } else {
            
            // C. GENERATE THE QR CODE IMAGE
            // ROUTING FIX 4: Step back twice to save the images in the root qrcodes folder
            $path = '../../qrcodes/'; 
            
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $fileName = $path . 'user_' . time() . '.png';
            $qrContent = "Event_ID: " . $event_id . "\nName: " . $name . "\nEmail: " . $email;
            
            QRcode::png($qrContent, $fileName, QR_ECLEVEL_L, 4, 2);

            // ROUTING FIX 5: Save a clean path to the database (removes the ../../)
            $db_path = str_replace("../../", "", $fileName);

            // D. SAVE TO THE DATABASE
            mysqli_query($conn, "INSERT INTO participants 
                (participant_name, participant_email, event_id, participant_qr, participant_status, account_username) 
                VALUES ('$name', '$email', '$event_id', '$db_path', 'Pending', '$logged_user')");

            // E. SHOW SUCCESS SCREEN
            $show_results = true;
            $qrImage = $fileName;
        }
    }
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
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --accent-color: #f8f32b;
            --accent-text: #000000;
            --danger-color: #ff453a;
        }

        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: var(--bg-color); 
            margin: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            color: var(--text-main); 
            overflow: hidden; 
            position: relative; 
        }

        .orb { 
            position: absolute; 
            border-radius: 50%; 
            filter: blur(80px); 
            opacity: 0.5; 
            animation: float 10s infinite ease-in-out alternate; 
            z-index: 0; 
        }
        
        .orb-1 { 
            width: 350px; 
            height: 350px; 
            background: #8d99ae; 
            top: 10%; 
            right: 20%; 
            animation-delay: 0s; 
        }
        
        .orb-2 { 
            width: 300px; 
            height: 300px; 
            background: #f8f32b; 
            bottom: 10%; 
            left: 15%; 
            animation-delay: -3s; 
        }

        @keyframes float { 
            0% { transform: translate(0, 0) scale(1); } 
            100% { transform: translate(-30px, 40px) scale(1.1); } 
        }

        .container { 
            background: rgba(30, 30, 32, 0.6); 
            backdrop-filter: blur(25px); 
            -webkit-backdrop-filter: blur(25px); 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.5); 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            width: 100%; 
            max-width: 420px; 
            text-align: center; 
            z-index: 1; 
        }

        h2 { 
            color: var(--text-main); 
            margin-top: 0; 
            margin-bottom: 5px; 
            font-weight: 600; 
            font-size: 24px; 
        }
        
        .welcome-text { 
            color: var(--text-muted); 
            font-size: 14px; 
            margin-top: 0; 
            margin-bottom: 25px; 
        }
        
        .reg-form { 
            display: <?php 
                if ($show_results == true) { 
                    echo 'none'; 
                } else { 
                    echo 'block'; 
                } 
            ?>; 
        }
        
        .input-group { 
            text-align: left; 
            margin-bottom: 15px; 
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
            display: block; 
            padding: 14px 16px; 
            width: 100%; 
            border-radius: 12px; 
            border: 1px solid rgba(255,255,255,0.1); 
            background: rgba(0,0,0,0.4); 
            box-sizing: border-box; 
            font-size: 15px; 
            color: var(--text-main); 
            transition: all 0.3s ease; 
            outline: none; 
        }
        
        input:focus { 
            border-color: var(--accent-color); 
            background: rgba(0,0,0,0.6); 
            box-shadow: 0 0 0 4px rgba(248, 243, 43, 0.1); 
        }
        
        input[readonly] { 
            color: var(--accent-color); 
            font-weight: 600; 
            background: rgba(0, 0, 0, 0.2); 
            border-color: rgba(248, 243, 43, 0.3); 
            cursor: default; 
        }
        
        input[readonly]:focus { 
            box-shadow: none; 
            border-color: rgba(248, 243, 43, 0.3); 
        }

        .btn-generate { 
            width: 100%; 
            padding: 14px; 
            background: var(--accent-color); 
            color: var(--accent-text); 
            border: none; 
            cursor: pointer; 
            border-radius: 12px; 
            font-weight: 600; 
            font-size: 15px; 
            transition: transform 0.2s, opacity 0.2s; 
            margin-top: 10px; 
        }
        
        .btn-generate:hover:not(:disabled) { 
            opacity: 0.9; 
            transform: scale(0.98); 
        }
        
        .btn-generate:disabled { 
            background: var(--text-muted); 
            cursor: not-allowed; 
        }

        .error-msg { 
            background: rgba(255, 69, 58, 0.1); 
            color: var(--danger-color); 
            padding: 12px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-size: 14px; 
            border: 1px solid rgba(255, 69, 58, 0.3); 
            font-weight: 500; 
        }

        .result-card { 
            margin-top: 10px; 
            padding: 24px; 
            border-radius: 16px; 
            background: rgba(0,0,0,0.4); 
            border: 1px solid rgba(255,255,255,0.05); 
        }
        
        .result-card img { 
            border: 4px solid var(--accent-color); 
            margin: 15px 0; 
            border-radius: 12px; 
            max-width: 180px; 
            background: white; 
            padding: 10px; 
        }

        .btn-group { 
            display: flex; 
            gap: 15px; 
            justify-content: center; 
            margin-top: 15px; 
        }
        
        .action-link { 
            text-decoration: none; 
            color: var(--accent-color); 
            font-weight: 600; 
            font-size: 15px; 
        }
        
        .action-link:hover { 
            text-decoration: underline; 
        }

        .userdashboard-link { 
            display: inline-block; 
            margin-top: 25px; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 14px; 
            transition: color 0.2s; 
        }
        
        .userdashboard-link:hover { 
            color: var(--accent-color); 
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="container">
        <h2>Complete Registration</h2>
        <p class="welcome-text">Confirm your details below to generate your pass.</p>
        
        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="reg-form">
            <form method="POST">
                
                <div class="input-group">
                    <label class="input-label">Seminar Selected:</label>
                    <input type="text" value="<?php echo htmlspecialchars($event_name_display); ?>" readonly>
                    <input type="hidden" name="event_id" value="<?php echo $target_event_id; ?>">
                </div>
                
                <div class="input-group">
                    <label class="input-label">Full Name (For Certificate/Admin):</label>
                    <input type="text" name="real_name" placeholder="Enter your full real name" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Registered Email (Locked):</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                </div>

                <button type="submit" name="register" class="btn-generate" 
                    <?php 
                        if ($target_event_id == 0) { 
                            echo 'disabled'; 
                        } 
                    ?>>
                    Confirm & Get QR Code
                </button>
            </form>
        </div>

        <?php if ($show_results): ?>
            <div class="result-card">
                <h3 style="color: var(--accent-color); margin-top: 0; font-weight: 600;">Success!</h3>
                <img src="<?php echo htmlspecialchars($qrImage); ?>" alt="Your QR Code"><br>
                <div class="btn-group">
                    <a href="<?php echo htmlspecialchars($qrImage); ?>" download class="action-link">💾 Download QR Pass</a>
                </div>
            </div>
        <?php endif; ?>
        
        <a href="user_dashboard.php" class="userdashboard-link">← Back to Dashboard</a>
    </div>
</body>
</html>
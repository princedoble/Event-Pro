<?php
// 1. START SESSION & CONNECT TO DB
session_start();

// ROUTING FIX 1: Step back twice to reach the config folder
include '../../config/db.php';

// 2. SECURITY PROTECTION
// If the user is not logged in, or if they are just a regular 'user' instead of 'admin', kick them out.
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    // ROUTING FIX 2: Point to the login page inside the auth folder
    header('Location: ../auth/login.php');
    exit();
}

// Set up default variables for the screen display
$msg = "";
$status_type = "error"; // Controls if the screen flashes green (success), yellow (warning), or red (error)
$admin_event_id = 0; // Default fallback in case no event ID is provided

// 3. CHECK IF WE RECEIVED SCAN DATA
// Ensure the scanner actually sent both the QR text data and the Event ID in the URL
if(isset($_GET['qr_data']) && isset($_GET['event_id'])) {
    
    // Save the incoming data to variables
    $qr_data = $_GET['qr_data'];
    $admin_event_id = intval($_GET['event_id']); // Make sure it's a safe number

    // 4. THE SECURITY CHECK (Preventing the Cross-Event Loophole)
    // We strictly check if the exact phrase "Event_ID: [Current_Event_Number]" exists inside the scanned QR code
    $expected_tag = "Event_ID: " . $admin_event_id;
    
    // strpos() searches for text. If it does NOT return 'false', it means it found a match!
    if(strpos($qr_data, $expected_tag) !== false) {
        
        // 5. EXTRACT THE EMAIL FROM THE QR CODE
        // The QR code text has multiple lines. We use explode() to chop it into an array of separate lines.
        $lines = explode("\n", $qr_data);
        $email_line = "";
        
        // Loop through each line and look for the one that starts with "Email: "
        foreach($lines as $line) {
            if(strpos($line, "Email: ") === 0) {
                // Remove the word "Email: " so we are only left with "name@example.com"
                $email_line = str_replace("Email: ", "", $line);
                // trim() cleans up any accidental invisible spaces at the start or end
                $email_line = trim($email_line); 
            }
        }

        // 6. DATABASE VERIFICATION
        // If we successfully grabbed an email out of the QR code...
        if(!empty($email_line)) {
            
            // Clean the email to prevent SQL Injection
            $email_safe = mysqli_real_escape_string($conn, $email_line);
            
            // Look up this exact participant in this specific event
            $check_q = mysqli_query($conn, "SELECT * FROM participants WHERE participant_email = '$email_safe' AND event_id = '$admin_event_id'");
            
            // Fetch the row first, then check if it exists
            $row = mysqli_fetch_assoc($check_q);
            
            // If the database found a match...
            if($row) {
                
                // 7. PROCESS THE CHECK-IN
                // See if their ticket is still fresh ("Pending")
                if($row['participant_status'] == 'Pending') {
                    $p_id = $row['participant_id'];
                    
                    // ✅ SUCCESS STEP 1: Update the main participants table to say they attended
                    mysqli_query($conn, "UPDATE participants SET participant_status = 'Attended' WHERE participant_id = $p_id");
                    
                    // ✅ SUCCESS STEP 2: Log the exact time into the attendance table!
                    // We use MySQL's NOW() function to grab the exact server time they scanned in.
                    mysqli_query($conn, "INSERT INTO attendance (participant_id, time_in, attendee_status) VALUES ($p_id, NOW(), 'Present')");
                    
                    // Set the screen to show a green Success message
                    $msg = "Access Granted: " . htmlspecialchars($row['participant_name']) . " is checked in!";
                    $status_type = "success";
                } else {
                    // ⚠️ WARNING: If their status is NOT pending, they already scanned this ticket earlier!
                    // Set the screen to show a yellow Warning message
                    $msg = "Duplicate Scan: This user is already checked in!";
                    $status_type = "warning";
                }

            } else {
                // Error: We couldn't find them in the database for this event
                $msg = "User not found in this specific event's registration list.";
                $status_type = "error";
            }
        } else {
            // Error: The QR code was formatted wrong and had no email
            $msg = "Invalid QR Format: No email found.";
            $status_type = "error";
        }
    } else {
        // 🚨 CRITICAL ERROR: The Event ID in the QR code didn't match the Admin's current event!
        $msg = "SECURITY ALERT: This QR code belongs to a different seminar!";
        $status_type = "error";
    }
} else {
    // Error: Someone loaded this page without scanning anything
    $msg = "Missing QR scan data or Event ID.";
    $status_type = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Result | Event-Pro</title>
    <style>
        :root {
            --bg-color: #000000;
            --text-main: #f5f5f7;
            --success: #32d74b; 
            --warning: #ffd60a; 
            --error: #ff453a;   
        }

        body { 
            margin: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            overflow: hidden;
            position: relative;
        }

        /* --- Animated Background Orbs --- */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 10s infinite ease-in-out alternate;
            z-index: 0;
        }

        .orb-1 { width: 350px; height: 350px; top: 15%; right: 20%; animation-delay: 0s; }
        .orb-2 { width: 300px; height: 300px; bottom: 15%; left: 15%; animation-delay: -3s; }

        /* The orbs change color based on the $status_type PHP variable! */
        .bg-success .orb { background: var(--success); }
        .bg-warning .orb { background: var(--warning); }
        .bg-error .orb { background: var(--error); }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(-30px, 40px) scale(1.1); }
        }

        /* --- Glassmorphism Result Card --- */
        .result-card {
            background: rgba(30, 30, 32, 0.6);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 40px 30px;
            border-radius: 24px;
            width: 100%;
            max-width: 380px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1;
            transition: all 0.3s ease;
        }

        @keyframes popIn {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* --- Dynamic Colors based on PHP Status --- */
        
        /* If Success (Green) */
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
        
        /* If Warning (Yellow) */
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
        
        /* If Error (Red) */
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
            margin: 0 0 10px 0; 
            font-size: 26px; 
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        p { 
            color: #a1a1a6; 
            margin: 0 0 35px 0; 
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
            box-sizing: border-box;
            border: none;
            cursor: pointer;
        }
        
        .btn-return:hover { 
            opacity: 0.9;
            transform: scale(0.98); 
        }

    </style>
</head>

<body class="bg-<?php echo $status_type; ?>">

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="result-card <?php echo $status_type; ?>">
        
        <div class="icon">
            <?php 
                // Print an emoji based on the scan result
                if($status_type == 'success') {
                    echo "✅";
                }
                
                if($status_type == 'warning') {
                    echo "⚠️";
                }
                
                if($status_type == 'error') {
                    echo "❌";
                }
            ?>
        </div>
        
        <h2><?php echo strtoupper($status_type); ?></h2>
        
        <p><?php echo $msg; ?></p>
        
        <a href="scanner.php?event_id=<?php echo $admin_event_id; ?>" class="btn-return">Scan Next Person</a>
        
    </div>

</body>
</html>
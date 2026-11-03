<?php
// 1. START SESSION & DATABASE
// Start the session to check who is currently logged in, and connect to the database.
session_start();

// ROUTING FIX 1: Step back twice to reach the config folder
include '../../config/db.php'; 

// 2. SECURITY PROTECTION
// Check if the user is logged in. If they aren't, or if they are a standard 'user' 
// instead of an 'admin', immediately redirect them to the login page.
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') { 
    // ROUTING FIX 2: Point to the login page inside the auth folder
    header('Location: ../auth/login.php'); 
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner | Event-Pro</title>
    
    <script src="../../assets/html5-qrcode.js"></script>
    
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

        /* --- MAIN PAGE SETUP --- */
        body { 
            margin: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color); 
            color: var(--text-main);
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
            overflow: hidden; 
            position: relative;
        }

        /* --- ANIMATED BACKGROUND ORBS --- */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            animation: float 10s infinite ease-in-out alternate;
            z-index: 0;
        }

        .orb-1 { width: 350px; height: 350px; background: #8d99ae; top: 10%; right: 20%; animation-delay: 0s; }
        .orb-2 { width: 300px; height: 300px; background: var(--accent-color); bottom: 10%; left: 15%; animation-delay: -3s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(-30px, 40px) scale(1.1); }
        }

        /* --- GLASSMORPHISM SCANNER CONTAINER --- */
        .scanner-card {
            background: rgba(30, 30, 32, 0.6);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 30px; 
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            width: 100%; 
            max-width: 420px; 
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1;
        }

        h1 { 
            color: var(--text-main); 
            margin-top: 0; 
            margin-bottom: 5px;
            font-size: 24px; 
            font-weight: 600;
        }

        /* --- DROPDOWN MENU --- */
        select { 
            width: 100%; 
            padding: 14px 16px; 
            margin-bottom: 20px; 
            border-radius: 12px; 
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.4);
            color: var(--text-main);
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
        }
        
        select option {
            background: #1c1c1e; 
            color: var(--text-main);          
        }

        select:focus { 
            border-color: var(--accent-color); 
            background: rgba(0,0,0,0.6);
            box-shadow: 0 0 0 4px rgba(248, 243, 43, 0.1);
        }

        /* --- TAB BUTTONS (Camera vs Upload) --- */
        .tab-container {
            display: flex; 
            background: rgba(0,0,0,0.4); 
            padding: 6px;
            border-radius: 12px; 
            margin-bottom: 20px; 
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .tab-btn {
            flex: 1; 
            padding: 10px; 
            border: none; 
            background: transparent;
            color: var(--text-muted); 
            cursor: pointer; 
            border-radius: 8px; 
            font-weight: 600;
            transition: all 0.3s ease; 
            font-size: 14px;
        }

        /* Highlights the active tab button */
        .tab-btn.active { 
            background: var(--accent-color); 
            color: var(--accent-text);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* --- CAMERA READER AREA --- */
        #reader { 
            width: 100% !important; 
            max-height: 350px; 
            border-radius: 16px; 
            overflow: hidden; 
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
        }

        #reader video {
            max-height: 250px !important;
            object-fit: cover !important;
        }

        /* --- FILE UPLOAD UI --- */
        #file-ui { 
            display: none; 
            padding: 40px 20px; 
            border: 2px dashed rgba(255,255,255,0.2); 
            border-radius: 16px; 
            background: rgba(0,0,0,0.2);
        }

        .btn-action {
            background: var(--accent-color); 
            color: var(--accent-text); 
            border: none;
            padding: 14px 25px; 
            border-radius: 12px; 
            cursor: pointer;
            font-weight: 600; 
            margin-top: 10px; 
            width: 100%;
            transition: transform 0.2s, opacity 0.2s;
            font-size: 15px;
        }
        
        .btn-action:hover { 
            opacity: 0.9; 
            transform: scale(0.98); 
        }

        .status-msg { margin-top: 15px; font-size: 14px; color: var(--text-muted); }
        
        /* --- BACK LINK --- */
        .back-link { 
            display: inline-block; 
            margin-top: 25px; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 14px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--accent-color); }
    </style>
</head>
<body>

<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="scanner-card">
    <h1>Entrance Scanner</h1>
    <p style="color: var(--text-muted); font-size: 14px; margin-top: 0; margin-bottom: 25px;">Fast-track verification for Event-Pro</p>

    <select id="event_select">
        <option value="">-- Select Event to Scan For --</option>
        <?php
        
        // Check the URL to see if the admin clicked a specific event to scan
        $target_event_id = 0;
        if (isset($_GET['event_id'])) {
            $target_event_id = intval($_GET['event_id']);
        }

        // Fetch all events from the database, newest first
        $events = mysqli_query($conn, "SELECT * FROM events ORDER BY event_date DESC");
        
        // Loop through each event to build the dropdown options
        while($ev = mysqli_fetch_assoc($events)) {
            
            // Check if the event has ended, and if so, add a tag
            $status_label = "";
            if (isset($ev['event_status'])) {
                if ($ev['event_status'] == 'Ended') {
                    $status_label = " (Ended)";
                }
            }
            
            // Check if this event matches the one the admin clicked on from the dashboard
            $isSelected = "";
            if ($ev['event_id'] == $target_event_id) {
                $isSelected = "selected"; // This pre-selects the dropdown option for them
            }
            
            // Print the HTML option tag
            echo "<option value='{$ev['event_id']}' {$isSelected}>" . htmlspecialchars($ev['event_name']) . " " . $status_label . "</option>";
        }
        ?>
    </select>

    <div class="tab-container">
        <button class="tab-btn active" id="tab-cam" onclick="switchTab('camera')">Live Camera</button>
        <button class="tab-btn" id="tab-file" onclick="switchTab('file')">Upload Image</button>
    </div>

    <div id="reader"></div>

    <div id="file-ui">
        <input type="file" id="file-input" accept="image/*" style="display:none;">
        <button class="btn-action" onclick="document.getElementById('file-input').click()">Select QR Photo</button>
        <p class="status-msg" id="file-status">Select a photo from your device</p>
    </div>

    <a href="admin.php" class="back-link">← Back to Dashboard</a>
</div>

<script>
    // Initialize the scanner object using the library
    const html5QrCode = new Html5Qrcode("reader");
    let isCameraRunning = false;

    // 1. THE SUCCESS FUNCTION
    // This runs the exact millisecond the camera (or file) successfully reads a QR code
    function handleSuccess(decodedText) {
        
        // Grab the ID of the event currently selected in the dropdown menu
        const eventId = document.getElementById('event_select').value;
        
        // If the admin forgot to pick an event, stop and show a warning
        if (!eventId) {
            alert("⚠️ Please select an event from the dropdown before scanning!");
            return;
        }

        // Build the URL to send the data to our PHP processor
        // ROUTING: verify_qr.php is in the exact same pages/admin/ folder, so no ../ is needed!
        const targetUrl = "verify_qr.php?qr_data=" + encodeURIComponent(decodedText) + "&event_id=" + eventId;

        // Turn off the camera to save battery, then redirect to the verification page
        if (isCameraRunning) {
            html5QrCode.stop().then(() => {
                window.location.href = targetUrl;
            });
        } else {
            window.location.href = targetUrl; // Redirect immediately if using the file upload
        }
    }

    // 2. START THE CAMERA
    function startCamera() {
        // Hide the file UI and show the camera box
        document.getElementById('file-ui').style.display = 'none';
        document.getElementById('reader').style.display = 'block';
        
        // Set the camera properties (frames per second, scan box size)
        const config = { 
            fps: 10, 
            qrbox: { width: 200, height: 200 },
            aspectRatio: 1.0 
        };

        // Tell the library to use the back camera ("environment") and start watching
        html5QrCode.start({ facingMode: "environment" }, config, handleSuccess)
            .then(() => isCameraRunning = true)
            .catch(err => {
                // If the user didn't grant camera permissions, show an error
                document.getElementById('reader').innerHTML = `<p style="color:var(--danger-color); padding:20px;">Camera access denied or unavailable.</p>`;
            });
    }

    // 3. TAB SWITCHING LOGIC
    // This handles moving between Live Camera and File Upload modes
    async function switchTab(mode) {
        
        // Update the CSS to highlight the correct button
        document.getElementById('tab-cam').classList.toggle('active', mode === 'camera');
        document.getElementById('tab-file').classList.toggle('active', mode === 'file');

        // Always stop the camera first if we are switching tabs
        if (isCameraRunning) {
            await html5QrCode.stop();
            isCameraRunning = false;
        }
        html5QrCode.clear();

        // Boot up the requested mode
        if (mode === 'camera') {
            startCamera();
        } else {
            // Hide the camera and show the file upload button
            document.getElementById('reader').style.display = 'none';
            document.getElementById('file-ui').style.display = 'block';
        }
    }

    // 4. FILE UPLOAD LOGIC
    // This watches for when the admin selects an image from their phone/computer
    document.getElementById('file-input').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        
        const status = document.getElementById('file-status');
        status.innerText = "Scanning...";
        status.style.color = "var(--accent-color)";

        // Hand the image file to the scanner library to process
        html5QrCode.scanFile(file, true)
            .then(handleSuccess) // If successful, run the exact same function the camera uses
            .catch(err => {
                status.innerText = "Error: QR not detected.";
                status.style.color = "var(--danger-color)";
            });
    });

    // 5. AUTO-START
    // Turn the camera on automatically as soon as the page loads
    window.onload = startCamera;
</script>

</body>
</html>
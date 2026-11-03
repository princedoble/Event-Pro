<?php
session_start();
include '../../config/db.php'; 

// Protection: Ensure only admins can create events
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$msg = "";

if (isset($_POST['add_event'])) {
    $name = $_POST['event_name'];
    $date = $_POST['event_date'];
    // Capture the dynamic capacity input from the admin
    $max = intval($_POST['max_capacity']); 

    // Insert the event with the specified max participants
    $query = "INSERT INTO events (event_name, event_date, max_capacity) VALUES ('$name', '$date', $max)";
    
    if(mysqli_query($conn, $query)) {
        $msg = "Seminar '$name' created with a limit of $max participants!";
    }
}
?>
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

        /* --- Animated Background Orbs --- */
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
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, -50px) scale(1.1); }
        }

        /* --- Glassmorphism Form --- */
        .create-card { 
            background: rgba(30, 30, 32, 0.6);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 40px; 
            border-radius: 20px; 
            width: 350px; 
            text-align: center; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
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
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; 
            box-sizing: border-box; 
            color: var(--text-main);
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
        }

        input[type="date"] {
            color-scheme: dark; /* Forces native date picker to be dark mode */
        }

        input::placeholder {
            color: var(--text-muted);
        }

        input:focus { 
            border-color: var(--accent-color); 
            background: rgba(0,0,0,0.6);
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
    </style>

<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="create-card">
        <form method="POST" id="create_event">
            <h2>Create Seminar</h2>
            
            <div class="input-group">
                <input type="text" name="event_name" placeholder="Seminar Name" required>
            </div>
            
            <div class="input-group">
                <label class="input-label">Event Date</label>
                <input type="date" name="event_date" required title="Event Date">
            </div>
            
            <div class="input-group">
                <input type="number" name="max_capacity" placeholder="Max Participants (e.g. 100)" required min="1">
            </div>
            
            <button type="submit" class="submit_btn" name="add_event">Create Event</button>

            <?php if($msg) echo "<div class='success_msg'>$msg</div>"; ?>
            
            <a href="admin.php" class="back-link">← Back to Dashboard</a>
        </form>
    </div>
</body>
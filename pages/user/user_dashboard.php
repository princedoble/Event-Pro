<?php
// 1. START SESSION & DATABASE
// Start the session to remember who is logged in.
session_start();

// ROUTING FIX 1: Step back twice to reach the config folder
include '../../config/db.php';

// 2. SECURITY GATE
// If there is no username saved in the session, or the user is not a 'user' (e.g. they are an admin),
// kick them out and redirect them back to the login page inside the auth folder.
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    // ROUTING FIX 2: Point to the login page inside the auth folder
    header('Location: ../auth/login.php');
    exit();
}

// 3. AUTO-PATCH: DATABASE UPGRADE
// This checks if the 'account_username' column exists in the 'participants' table.
// If it finds 0 matches (it's missing), it automatically creates it so the system doesn't crash.
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM participants LIKE 'account_username'");
if(mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE participants ADD COLUMN account_username VARCHAR(100) DEFAULT ''");
}

// Save the logged-in username into a simple variable to use later
$logged_user = $_SESSION['username'];

// 4. FETCH USER'S REAL EMAIL
// We look into the 'registration' table to find the exact email linked to this username.
$user_query = mysqli_query($conn, "SELECT registration_email FROM registration WHERE username = '$logged_user'");
$user_info = mysqli_fetch_assoc($user_query);

// Safely extract the email. If the email exists in the database result, save it to $user_email.
$user_email = "";
if (isset($user_info['registration_email'])) {
    $user_email = $user_info['registration_email'];
}

// 5. BUILD THE "CATCH-ALL" FILTER
// This filter ensures the dashboard finds the user's tickets whether they were saved using their username, real name, or email.

// First, if the user has an email, prepare a snippet of SQL code to search for it.
$email_check = "";
if (!empty($user_email)) {
    $email_check = " OR participant_email = '$user_email'";
}

// Next, combine everything into one massive SQL rule wrapped in parentheses.
$user_filter = "(account_username = '$logged_user' OR participant_name = '$logged_user'" . $email_check . ")";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Event-Pro</title>
    <style>
        /* --- CSS VARIABLES --- */
        :root {
            --bg-color: #000000;
            --sidebar-bg: rgba(30, 30, 32, 0.75);
            --content-bg: #1c1c1e;
            --text-main: #f5f5f7;
            --text-muted: #86868b;
            --border-color: rgba(255, 255, 255, 0.1);
            --accent-color: #f8f32b; 
            --accent-text: #000000;
            --row-bg: rgba(255, 255, 255, 0.04);
            --row-hover: rgba(255, 255, 255, 0.08);
            --danger-color: #ff453a;
        }

        /* --- MAIN LAYOUT --- */
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: var(--bg-color); 
            color: var(--text-main); 
            margin: 0; 
            height: 100vh; 
            display: flex; 
            overflow: hidden; 
        }
        
        /* --- SIDEBAR --- */
        .sidebar { 
            width: 260px; 
            background: var(--sidebar-bg); 
            backdrop-filter: blur(20px); 
            border-right: 1px solid var(--border-color); 
            display: flex; 
            flex-direction: column; 
            padding: 24px 16px; 
            box-sizing: border-box; 
            z-index: 10; 
        }
        
        .profile-section { 
            padding-bottom: 24px; 
            margin-bottom: 16px; 
            border-bottom: 1px solid var(--border-color); 
        }
        
        .profile-section h2 { 
            font-size: 18px; 
            margin: 0 0 4px 0; 
            color: var(--text-main); 
        }
        
        .profile-section p { 
            font-size: 13px; 
            color: var(--text-muted); 
            margin: 0; 
        }
        
        .nav-menu { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
            flex-grow: 1; 
        }
        
        .nav-item { 
            width: 100%; 
            background: transparent; 
            border: none; 
            color: var(--text-main); 
            text-align: left; 
            padding: 10px 14px; 
            border-radius: 8px; 
            font-size: 14px; 
            margin-bottom: 8px; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            box-sizing: border-box; 
        }
        
        .nav-item:hover { 
            background: var(--row-bg); 
        }
        
        .nav-item.active { 
            background: rgba(248, 243, 43, 0.15); 
            color: var(--accent-color); 
            font-weight: 600; 
        }
        
        .btn-logout { 
            margin-top: auto; 
            text-align: center; 
            padding: 10px; 
            background: var(--row-bg); 
            color: var(--danger-color); 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 500; 
            transition: 0.2s; 
        }
        
        .btn-logout:hover { 
            background: var(--danger-color); 
            color: #fff; 
        }

        /* --- MAIN CONTENT AREA --- */
        .main-content { 
            flex-grow: 1; 
            background: var(--content-bg); 
            padding: 40px; 
            overflow-y: auto; 
        }
        
        /* Tab System Logic */
        .tab-content { 
            display: none; 
            max-width: 1000px; 
            margin: 0 auto; 
            animation: fadeIn 0.3s ease; 
        }
        
        .tab-content.active { 
            display: block; 
        }
        
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(5px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        h3 { 
            font-size: 24px; 
            font-weight: 600; 
            margin-top: 0; 
            margin-bottom: 20px; 
            border-bottom: 1px solid var(--border-color); 
            padding-bottom: 12px; 
        }

        /* --- OVERVIEW CARDS --- */
        .overview-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        
        .metric-card { 
            background: rgba(30, 30, 32, 0.6); 
            backdrop-filter: blur(25px); 
            padding: 25px; 
            border-radius: 16px; 
            border: 1px solid rgba(255,255,255,0.05); 
            text-align: center; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
        }
        
        .metric-card h2 { 
            font-size: 36px; 
            margin: 0 0 5px 0; 
            color: var(--accent-color); 
        }
        
        .metric-card p { 
            margin: 0; 
            font-size: 12px; 
            text-transform: uppercase; 
            font-weight: 600; 
            letter-spacing: 1px; 
            color: var(--text-muted); 
        }
        
        .overview-split { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }
        
        .info-card { 
            background: rgba(30, 30, 32, 0.6); 
            backdrop-filter: blur(25px); 
            padding: 30px; 
            border-radius: 20px; 
            border: 1px solid rgba(255,255,255,0.05); 
        }
        
        .info-card h4 { 
            margin: 0 0 20px 0; 
            font-size: 18px; 
            color: var(--text-main); 
            border-bottom: 1px solid var(--border-color); 
            padding-bottom: 10px; 
        }
        
        .events-scroll-container { 
            max-height: 450px; 
            overflow-y: auto; 
            padding-right: 10px; 
        }
        
        .events-scroll-container::-webkit-scrollbar { 
            width: 6px; 
        }
        
        .events-scroll-container::-webkit-scrollbar-thumb { 
            background: rgba(255, 255, 255, 0.2); 
            border-radius: 10px; 
        }
        
        .event-item { 
            margin-bottom: 25px; 
            padding-bottom: 25px; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
        }
        
        .event-item:last-child { 
            margin-bottom: 0; 
            padding-bottom: 0; 
            border-bottom: none; 
        }
        
        .action-btn-large { 
            display: block; 
            width: 100%; 
            padding: 12px; 
            background: var(--accent-color); 
            color: var(--accent-text); 
            text-align: center; 
            border-radius: 12px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 14px; 
            margin-top: 15px; 
            transition: 0.2s; 
            box-sizing: border-box; 
        }
        
        .action-btn-large:hover { 
            opacity: 0.9; 
            transform: translateY(-2px); 
        }
        
        .activity-item { 
            padding: 12px 0; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
            font-size: 14px; 
            color: var(--text-muted); 
        }
        
        .activity-item:last-child { 
            border-bottom: none; 
        }
        
        .activity-item strong { 
            color: var(--text-main); 
        }

        /* --- TABLES --- */
        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0; 
            background: var(--bg-color); 
            border-radius: 12px; 
            overflow: hidden; 
            border: 1px solid var(--border-color); 
            margin-bottom: 30px; 
        }
        
        th { 
            text-align: left; 
            padding: 14px 16px; 
            color: var(--text-muted); 
            font-size: 12px; 
            font-weight: 500; 
            text-transform: uppercase; 
            border-bottom: 1px solid var(--border-color); 
            background: rgba(255,255,255,0.02); 
            letter-spacing: 0.5px; 
        }
        
        td { 
            padding: 16px; 
            font-size: 14px; 
            border-bottom: 1px solid var(--border-color); 
            vertical-align: middle; 
        }
        
        tr:last-child td { 
            border-bottom: none; 
        }
        
        tr:hover { 
            background: var(--row-hover); 
        }

        .badge { 
            padding: 4px 10px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 600; 
            letter-spacing: 0.5px; 
            text-transform: uppercase; 
        }
        
        .bg-pending { 
            background: #3a3a3c; 
            color: var(--text-main); 
        }
        
        .empty-msg { 
            color: var(--text-muted); 
            text-align: center; 
            padding: 30px !important; 
        }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="profile-section">
            <h2>Dashboard</h2>
            <p>Welcome, <strong><?php echo htmlspecialchars($logged_user); ?></strong></p>
        </div>
        <ul class="nav-menu">
            <li><button class="nav-item active" onclick="openTab(event, 'overview')">🧭 Overview</button></li>
            <li><button class="nav-item" onclick="openTab(event, 'upcoming')">📅 My Registrations</button></li>
            <li><button class="nav-item" onclick="openTab(event, 'history')">✅ Event History</button></li>
        </ul>
        <a href="../auth/logout.php" class="btn-logout">Logout</a>
    </nav>

    <main class="main-content">

        <section id="overview" class="tab-content active">
            <h3>Welcome back, <?php echo htmlspecialchars($logged_user); ?>!</h3>
            
            <?php
            // STEP 1: CALCULATE UPCOMING REGISTRATIONS
            $query_upcoming = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM participants JOIN events ON participants.event_id = events.event_id WHERE $user_filter AND participant_status = 'Pending' AND events.event_status != 'Ended'");
            $row_upcoming = mysqli_fetch_assoc($query_upcoming);
            $my_upcoming = $row_upcoming['total_count'];

            // STEP 2: CALCULATE SEMINARS ATTENDED
            $query_attended = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM participants WHERE $user_filter AND participant_status IN ('Attended', 'Completed')");
            $row_attended = mysqli_fetch_assoc($query_attended);
            $my_attended = $row_attended['total_count'];

            // STEP 3: CALCULATE AVAILABLE EVENTS
            $query_available = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM events WHERE event_date >= CURDATE() AND event_status != 'Ended' AND event_id NOT IN (SELECT event_id FROM participants WHERE $user_filter)");
            $row_available = mysqli_fetch_assoc($query_available);
            $available_count = $row_available['total_count'];
            ?>

            <div class="overview-grid">
                <div class="metric-card">
                    <h2 style="color: var(--text-main);"><?php echo $my_upcoming; ?></h2>
                    <p>Upcoming Registrations</p>
                </div>
                <div class="metric-card">
                    <h2><?php echo $my_attended; ?></h2>
                    <p>Seminars Attended</p>
                </div>
                <div class="metric-card">
                    <h2 style="color: var(--text-main);"><?php echo $available_count; ?></h2>
                    <p>Available to Join</p>
                </div>
            </div>

            <div class="overview-split">
                
                <div class="info-card">
                    <h4 style="color: var(--accent-color);">🌟 Seminars Available to Join</h4>
                    <div class="events-scroll-container">
                        <?php
                        $q_next = mysqli_query($conn, "SELECT * FROM events WHERE event_date >= CURDATE() AND event_status != 'Ended' AND event_id NOT IN (SELECT event_id FROM participants WHERE $user_filter) ORDER BY event_date ASC");
                        
                        if(mysqli_num_rows($q_next) > 0) {
                            while($next_event = mysqli_fetch_assoc($q_next)) {
                                
                                $e_id = $next_event['event_id'];
                                
                                // CHECK CAPACITY
                                $query_capacity = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM participants WHERE event_id = $e_id");
                                $row_capacity = mysqli_fetch_assoc($query_capacity);
                                $current_reg = $row_capacity['total_count']; 
                                
                                $max_cap = $next_event['max_capacity']; 
                                
                                // CALCULATE PERCENTAGE 
                                $percentage = 0;
                                if ($max_cap > 0) {
                                    $percentage = round(($current_reg / $max_cap) * 100);
                                }
                                
                                // CHECK IF FULL
                                $is_full = false;
                                if ($current_reg >= $max_cap) {
                                    $is_full = true;
                                }

                                echo "<div class='event-item'>";
                                echo "<h2 style='margin: 0; font-size: 20px; color: var(--text-main);'>" . htmlspecialchars($next_event['event_name']) . "</h2>";
                                echo "<p style='color: var(--text-muted); margin-top: 5px; font-size: 13px;'>" . date('l, F jS, Y', strtotime($next_event['event_date'])) . "</p>";
                                
                                echo "<div style='margin-top: 15px;'>";
                                echo "<div style='display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;'><span>Capacity</span> <strong style='color: var(--text-main);'>$current_reg / $max_cap</strong></div>";
                                echo "<div style='width: 100%; background: rgba(255,255,255,0.1); border-radius: 10px; height: 6px; overflow: hidden;'>";
                                
                                // Progress bar color logic
                                $bar_color = "";
                                if ($is_full == true) {
                                    $bar_color = "var(--danger-color)";
                                } else {
                                    $bar_color = "var(--accent-color)";
                                }
                                
                                echo "<div style='width: {$percentage}%; background: {$bar_color}; height: 100%; border-radius: 10px;'></div>";
                                echo "</div></div>";

                                if($is_full == true) {
                                    echo "<div class='action-btn-large' style='background: rgba(255,255,255,0.05); color: var(--text-muted); cursor: not-allowed;'>Fully Booked</div>";
                                } else {
                                    // ROUTING FIX 4: Keep generate_qr.php exactly as is, because they are both inside the 'pages/user/' folder!
                                    echo "<a href='generate_qr.php?event_id={$e_id}' class='action-btn-large'>Register Now</a>";
                                }
                                echo "</div>";
                            }
                        } else {
                            echo "<p style='color: var(--text-muted);'>No new seminars available at this time. Check back later!</p>";
                        }
                        ?>
                    </div>
                </div>

                <div class="info-card">
                    <h4>My Recent Activity</h4>
                    <?php
                    $q_recent = mysqli_query($conn, "
                        SELECT e.event_name, p.participant_status 
                        FROM participants p 
                        JOIN events e ON p.event_id = e.event_id 
                        WHERE $user_filter
                        ORDER BY p.participant_id DESC LIMIT 6
                    ");

                    if(mysqli_num_rows($q_recent) > 0) {
                        while($activity = mysqli_fetch_assoc($q_recent)) {
                            
                            $action = "You registered for";
                            
                            if ($activity['participant_status'] == 'Attended') {
                                $action = "You <span style='color: var(--accent-color);'>checked into</span>";
                            }
                            
                            if ($activity['participant_status'] == 'Completed') {
                                $action = "You successfully completed";
                            }
                            
                            if ($activity['participant_status'] == 'Absent') {
                                $action = "You <span style='color: var(--danger-color);'>missed</span>";
                            }
                            
                            echo "<div class='activity-item'>";
                            echo "$action <strong>" . htmlspecialchars($activity['event_name']) . "</strong>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p style='color: var(--text-muted);'>You haven't interacted with any seminars yet.</p>";
                    }
                    ?>
                </div>
            </div>
        </section>

        <section id="upcoming" class="tab-content">
            <h3>My Upcoming Registrations</h3>
            <table>
                <thead>
                    <tr>
                        <th>Seminar Name</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $upcoming = mysqli_query($conn, "SELECT events.event_name, events.event_date, participants.participant_status 
                        FROM participants 
                        JOIN events ON participants.event_id = events.event_id 
                        WHERE $user_filter AND participants.participant_status = 'Pending' AND events.event_status != 'Ended'
                        ORDER BY events.event_date ASC");
                    
                    if(mysqli_num_rows($upcoming) > 0) {
                        while($row = mysqli_fetch_assoc($upcoming)) {
                            echo "<tr>
                                <td><strong>" . htmlspecialchars($row['event_name']) . "</strong></td>
                                <td>" . date('M d, Y', strtotime($row['event_date'])) . "</td>
                                <td><span class='badge bg-pending'>REGISTERED</span></td>
                            </tr>";
                        }
                    } else { 
                        echo "<tr><td colspan='3' class='empty-msg'>No upcoming registrations.</td></tr>"; 
                    }
                    ?>
                </tbody>
            </table>
        </section>

        <section id="history" class="tab-content">
            <h3>My Event History</h3>
            <table>
                <thead>
                    <tr>
                        <th>Seminar Name</th>
                        <th>Event Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $attended = mysqli_query($conn, "SELECT events.event_name, events.event_date, participants.participant_status 
                        FROM participants 
                        JOIN events ON participants.event_id = events.event_id 
                        WHERE $user_filter
                        AND participants.participant_status IN ('Attended', 'Completed', 'Absent')
                        ORDER BY events.event_date DESC");
                    
                    if(mysqli_num_rows($attended) > 0) {
                        while($row = mysqli_fetch_assoc($attended)) {
                            
                            $status_text = "";
                            $badge_style = "";
                            
                            if ($row['participant_status'] === 'Completed') {
                                $status_text = "COMPLETED";
                                $badge_style = "background: #3a3a3c; color: var(--text-muted);"; 
                            } elseif ($row['participant_status'] === 'Absent') {
                                $status_text = "MISSED";
                                $badge_style = "background: rgba(255, 69, 58, 0.1); color: var(--danger-color); border: 1px solid var(--danger-color);"; 
                            } else {
                                $status_text = "CHECKED IN";
                                $badge_style = "background: var(--accent-color); color: var(--accent-text);"; 
                            }

                            echo "<tr>
                                <td><strong>" . htmlspecialchars($row['event_name']) . "</strong></td>
                                <td>" . date('M d, Y', strtotime($row['event_date'])) . "</td>
                                <td><span class='badge' style='$badge_style'>$status_text</span></td>
                            </tr>";
                        }
                    } else { 
                        echo "<tr><td colspan='3' class='empty-msg'>You have no past event history.</td></tr>"; 
                    }
                    ?>
                </tbody>
            </table>
        </section>

    </main>

    <script>
        function openTab(evt, tabId) {
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => { tab.classList.remove('active'); });
            
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => { item.classList.remove('active'); });
            
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>
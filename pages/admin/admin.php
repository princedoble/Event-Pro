<?php
// 1. START SESSION & CONNECT TO DATABASE
session_start();

// ROUTING FIX 1: Step back twice to reach the config folder
include '../../config/db.php'; 

// 2. SECURITY PROTECTION
// If the user is not logged in, OR if their role is not 'admin', kick them back to login.
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    // ROUTING FIX 2: Point to the login page inside the auth folder
    header('Location: ../auth/login.php');
    exit();
}

// ==========================================
// 3. DATABASE AUTO-PATCHES
// ==========================================

// AUTO-PATCH 1: Check if the 'event_status' column exists. If missing, create it.
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM events LIKE 'event_status'");
if(mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE events ADD COLUMN event_status VARCHAR(20) DEFAULT 'Active'");
}

// AUTO-PATCH 2: The Ghost-Ticket Cleaner
// Automatically forces any stuck 'Pending' tickets in 'Ended' events to become 'Absent'
mysqli_query($conn, "UPDATE participants p 
                     INNER JOIN events e ON p.event_id = e.event_id 
                     SET p.participant_status = 'Absent' 
                     WHERE p.participant_status = 'Pending' AND e.event_status = 'Ended'");


// ==========================================
// 4. ACTION HANDLERS (Runs when Admin clicks buttons)
// ==========================================

// ACTION: End an event
if(isset($_GET['end_event'])){
    $event_id = intval($_GET['end_event']); // Safely get the event ID
    
    // Step 1: Mark those who checked in as 'Completed'
    mysqli_query($conn, "UPDATE participants SET participant_status = 'Completed' WHERE event_id = $event_id AND participant_status = 'Attended'");
    
    // Step 2: Mark those who never showed up as 'Absent'
    mysqli_query($conn, "UPDATE participants SET participant_status = 'Absent' WHERE event_id = $event_id AND participant_status = 'Pending'");
    
    // Step 3: Mark the event itself as 'Ended'
    mysqli_query($conn, "UPDATE events SET event_status = 'Ended' WHERE event_id = $event_id");
    
    header("Location: admin.php?msg=Event Completed");
    exit();
}

// ACTION: Reactivate a locked user account
if(isset($_GET['reactivate_id'])){
    $id = intval($_GET['reactivate_id']);
    
    // Set status back to active and reset failed attempts to 0
    mysqli_query($conn, "UPDATE registration SET status='active', attempts=0 WHERE id = $id");
    
    header("Location: admin.php?msg=User Unlocked");
    exit();
}

// ACTION: Delete a user account permanently
if(isset($_GET['delete_user'])){
    $id = intval($_GET['delete_user']);
    mysqli_query($conn, "DELETE FROM registration WHERE id = $id");
    header("Location: admin.php?msg=User Account Deleted");
    exit();
}

// ACTION: Delete an event (Cascading Delete)
if(isset($_GET['delete_event'])){
    $event_id = intval($_GET['delete_event']);
    
    // Step 1: Find all participants for this specific event
    $get_qrs = mysqli_query($conn, "SELECT participant_qr FROM participants WHERE event_id = $event_id");
    
    // Step 2: Loop through them and delete their QR code image files from your computer/server
    while($row = mysqli_fetch_assoc($get_qrs)) {
        
        // ROUTING FIX 3: Prepend ../../ so the script can find the qrcodes folder in the root directory
        $file_path = "../../" . $row['participant_qr'];
        
        // If the QR image path exists and the file is real, delete the physical file
        if(!empty($row['participant_qr']) && file_exists($file_path)) {
            unlink($file_path); 
        }
    }
    
    // Step 3: Delete all those participants from the database so they don't become orphans
    mysqli_query($conn, "DELETE FROM participants WHERE event_id = $event_id");
    
    // Step 4: Finally, delete the event itself
    mysqli_query($conn, "DELETE FROM events WHERE event_id = $event_id");
    
    header("Location: admin.php?msg=Event and all related data deleted");
    exit();
}

// ACTION: Delete a specific participant
if(isset($_GET['delete_participant'])){
    $participant_id = intval($_GET['delete_participant']);
    
    // Find their specific QR code file
    $result = mysqli_query($conn, "SELECT participant_qr FROM participants WHERE participant_id = $participant_id");
    $participant_data = mysqli_fetch_assoc($result);
    
    // ROUTING FIX 4: Prepend ../../ so the script can find the qrcodes folder in the root directory
    $file_path = "../../" . $participant_data['participant_qr'];
    
    // Delete the file from the computer
    if($participant_data && !empty($participant_data['participant_qr']) && file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete their record from the database
    mysqli_query($conn, "DELETE FROM participants WHERE participant_id = $participant_id");
    
    header("Location: admin.php?msg=Participant Removed");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Event-Pro</title>
    <style>
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

        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* --- Sidebar Styles --- */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
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

        .profile-section h2 { font-size: 18px; margin: 0 0 4px 0; color: var(--accent-color); }
        .profile-section p { font-size: 13px; color: var(--text-muted); margin: 0; }

        .nav-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; }

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
            text-decoration: none;
            box-sizing: border-box;
        }

        .nav-item:hover { background: var(--row-bg); }
        .nav-item.active {
            background: rgba(248, 243, 43, 0.15); 
            color: var(--accent-color);
            font-weight: 600;
        }

        .admin-actions {
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
            margin-bottom: 16px;
        }

        .btn-logout {
            text-align: center;
            padding: 10px;
            background: transparent;
            border: 1px solid var(--text-muted);
            color: var(--text-muted);
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s;
            display: block;
        }
        .btn-logout:hover { border-color: var(--danger-color); color: var(--danger-color); }

        /* --- Main Content Styles --- */
        .main-content {
            flex-grow: 1;
            background: var(--content-bg);
            padding: 40px;
            overflow-y: auto;
        }

        .tab-content {
            display: none; 
            max-width: 1000px;
            margin: 0 auto;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active { display: block; }

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
            color: var(--accent-color);
        }

        /* --- Overview / Landing Board Styles --- */
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
            color: var(--text-main);
        }

        .metric-card.alert h2 { color: var(--danger-color); }
        .metric-card.success h2 { color: var(--accent-color); }

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
            color: var(--accent-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        /* Custom Scrollbar for the Events List */
        .events-scroll-container {
            max-height: 400px;
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

        .action-btn-large:hover { opacity: 0.9; transform: translateY(-2px); }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 14px;
        }
        .activity-item:last-child { border-bottom: none; }

        /* --- Table Styles --- */
        table { 
            width: 100%; border-collapse: separate; border-spacing: 0; 
            background: var(--bg-color); border-radius: 12px; overflow: hidden;
            border: 1px solid var(--border-color); margin-bottom: 30px;
        }
        th { 
            text-align: left; padding: 14px 16px; color: var(--text-muted); 
            font-size: 12px; font-weight: 500; text-transform: uppercase; 
            border-bottom: 1px solid var(--border-color); background: rgba(255,255,255,0.02);
        }
        td { padding: 16px; font-size: 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: var(--row-hover); }

        /* --- Badges & Elements --- */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .bg-success { background: var(--accent-color); color: var(--accent-text); }
        .bg-warning { background: #8d99ae; color: #000000; }
        .bg-danger { background: var(--danger-color); color: white; }

        .qr-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid transparent; background: #fff; }
        .qr-thumb:hover { border-color: var(--accent-color); }

        .text-action { color: var(--accent-color); text-decoration: none; font-weight: 600; font-size: 13px; }
        .text-delete { color: var(--danger-color); text-decoration: none; font-weight: 600; font-size: 13px; }
        .text-delete:hover, .text-action:hover { text-decoration: underline; }

        #modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); backdrop-filter: blur(5px); justify-content:center; align-items:center; z-index: 9999; }
        #fullImg { border: 4px solid var(--accent-color); border-radius: 12px; max-width: 90%; max-height: 80vh; background: white; padding: 15px; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="profile-section">
            <h2>Admin Portal</h2>
            <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
        </div>

        <ul class="nav-menu">
            <li>
                <button class="nav-item active" onclick="openTab(event, 'overview')">
                    🧭 System Overview
                </button>
            </li>
            <li>
                <button class="nav-item" onclick="openTab(event, 'seminars')">
                    🏢 Manage Seminars
                </button>
            </li>
            <li>
                <button class="nav-item" onclick="openTab(event, 'participants')">
                    👥 Participants
                </button>
            </li>
            <li>
                <button class="nav-item" onclick="openTab(event, 'reports')">
                    📊 Reports
                </button>
            </li>
            <li>
                <button class="nav-item" onclick="openTab(event, 'accounts')">
                    🔐 Account Access
                </button>
            </li>
        </ul>

        <div class="admin-actions">
            <a href="scanner.php" class="nav-item" style="color: var(--accent-color);">📷 Open Scanner</a>
            <a href="create_event.php" class="nav-item">➕ Create Event</a>
        </div>

        <a href="../auth/logout.php" class="btn-logout">Logout</a>
    </nav>

    <main class="main-content">

        <section id="overview" class="tab-content active">
            <h3>System Overview</h3>
            
            <?php
            // GET TOTAL USERS
            $query_users = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM registration WHERE role != 'admin'");
            $row_users = mysqli_fetch_assoc($query_users);
            $total_users = $row_users['total_count'];

            // GET ACTIVE SEMINARS
            $query_sems = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM events WHERE event_status != 'Ended'");
            $row_sems = mysqli_fetch_assoc($query_sems);
            $active_seminars = $row_sems['total_count'];

            // GET LOCKED USERS (Accounts deactivated for 3 failed login attempts)
            $query_locked = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM registration WHERE status = 'deactivated'");
            $row_locked = mysqli_fetch_assoc($query_locked);
            $locked_users = $row_locked['total_count'];

            // GET ATTENDANCE STATISTICS
            // This query grabs the total registrations and total attendees for all 'Ended' events.
            $q_att = mysqli_query($conn, "
                SELECT 
                    (SELECT COUNT(*) FROM participants p JOIN events e ON p.event_id = e.event_id WHERE e.event_status = 'Ended') as total_reg,
                    (SELECT COUNT(*) FROM participants p JOIN events e ON p.event_id = e.event_id WHERE e.event_status = 'Ended' AND p.participant_status IN ('Attended', 'Completed')) as total_att
            ");
            $att_stats = mysqli_fetch_assoc($q_att);
            
            // Calculate the average attendance rate safely
            $avg_attendance = 0;
            if ($att_stats['total_reg'] > 0) {
                $avg_attendance = round(($att_stats['total_att'] / $att_stats['total_reg']) * 100);
            }
            ?>

            <div class="overview-grid">
                <div class="metric-card success">
                    <h2><?php echo $active_seminars; ?></h2>
                    <p>Active Seminars</p>
                </div>
                <div class="metric-card">
                    <h2><?php echo $total_users; ?></h2>
                    <p>Total Ecosystem Users</p>
                </div>
                <div class="metric-card">
                    <h2><?php echo $avg_attendance; ?>%</h2>
                    <p>Avg. Attendance Rate</p>
                </div>
                
                <?php
                // Turn the 4th card Red if there is a security lockout
                $alert_class = "";
                if ($locked_users > 0) {
                    $alert_class = "alert";
                }
                ?>
                <div class="metric-card <?php echo $alert_class; ?>">
                    <h2><?php echo $locked_users; ?></h2>
                    <p>Security Lockouts</p>
                </div>
            </div>

            <div class="overview-split">
                
                <div class="info-card">
                    <h4>Upcoming Events</h4>
                    <div class="events-scroll-container">
                        <?php
                        // Fetch all events that are active and in the future
                        $q_next = mysqli_query($conn, "SELECT * FROM events WHERE event_status != 'Ended' AND event_date >= CURDATE() ORDER BY event_date ASC");
                        
                        if(mysqli_num_rows($q_next) > 0) {
                            while($next_event = mysqli_fetch_assoc($q_next)) {
                                
                                $e_id = $next_event['event_id'];
                                
                                // Check current capacity for this event
                                $query_capacity = mysqli_query($conn, "SELECT COUNT(*) AS total_count FROM participants WHERE event_id = $e_id");
                                $row_capacity = mysqli_fetch_assoc($query_capacity);
                                $current_reg = $row_capacity['total_count'];
                                
                                $max_cap = $next_event['max_capacity'];
                                
                                // Calculate capacity percentage for the progress bar
                                $percentage = 0;
                                if ($max_cap > 0) {
                                    $percentage = round(($current_reg / $max_cap) * 100);
                                }

                                echo "<div class='event-item'>";
                                echo "<h2 style='margin: 0; font-size: 24px; color: var(--text-main);'>" . htmlspecialchars($next_event['event_name']) . "</h2>";
                                echo "<p style='color: var(--text-muted); margin-top: 5px; font-size: 14px;'>" . date('l, F jS, Y', strtotime($next_event['event_date'])) . "</p>";
                                
                                echo "<div style='margin-top: 15px;'>";
                                echo "<div style='display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;'><span>Capacity Filled</span> <strong style='color: var(--accent-color);'>$current_reg / $max_cap</strong></div>";
                                echo "<div style='width: 100%; background: rgba(255,255,255,0.1); border-radius: 10px; height: 8px; overflow: hidden;'>";
                                echo "<div style='width: {$percentage}%; background: var(--accent-color); height: 100%; border-radius: 10px;'></div>";
                                echo "</div></div>";

                                // Scanner Button pre-linked to this specific event
                                echo "<a href='scanner.php?event_id={$e_id}' class='action-btn-large'>Open Scanner</a>";
                                echo "</div>";
                            }
                        } else {
                            echo "<p style='color: var(--text-muted);'>No upcoming events scheduled.</p>";
                            echo "<a href='create_event.php' class='action-btn-large' style='background: var(--text-main);'>➕ Schedule an Event</a>";
                        }
                        ?>
                    </div>
                </div>

                <div class="info-card">
                    <h4>Recent Registration Activity</h4>
                    <?php
                    // Fetch the 5 most recent registrations
                    $q_recent = mysqli_query($conn, "
                        SELECT p.participant_name, e.event_name, p.participant_status 
                        FROM participants p 
                        JOIN events e ON p.event_id = e.event_id 
                        ORDER BY p.participant_id DESC LIMIT 5
                    ");

                    if(mysqli_num_rows($q_recent) > 0) {
                        while($activity = mysqli_fetch_assoc($q_recent)) {
                            
                            // Change the wording based on their status
                            $action = "registered for";
                            if ($activity['participant_status'] == 'Attended') {
                                $action = "<span style='color: var(--accent-color);'>checked into</span>";
                            }
                            
                            echo "<div class='activity-item'>";
                            echo "<strong>" . htmlspecialchars($activity['participant_name']) . "</strong> $action " . htmlspecialchars($activity['event_name']);
                            echo "</div>";
                        }
                    } else {
                        echo "<p style='color: var(--text-muted);'>No recent activity on the platform.</p>";
                    }
                    ?>
                </div>
            </div>
        </section>

        <section id="seminars" class="tab-content">
            <h3>Current Seminars</h3>
            <table>
                <thead>
                    <tr>
                        <th>Seminar Name</th>
                        <th>Event Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch all events for the admin to manage
                    $event_result = mysqli_query($conn, "SELECT * FROM events ORDER BY event_date DESC");
                    
                    if(mysqli_num_rows($event_result) > 0) {
                        while($event = mysqli_fetch_assoc($event_result)) {
                            
                            // Check if the event has been marked as Ended
                            $status = "Active";
                            if (isset($event['event_status'])) {
                                $status = $event['event_status'];
                            }

                            echo "<tr>
                                <td><strong>" . htmlspecialchars($event['event_name']) . "</strong>";
                                
                            // Add a gray badge if the event has ended
                            if ($status === 'Ended') {
                                echo " <span class='badge' style='background: #3a3a3c; color: var(--text-muted); font-size: 9px; margin-left: 8px;'>ENDED</span>";
                            }
                            
                            echo "</td>
                                <td>" . date('F d, Y', strtotime($event['event_date'])) . "</td>
                                <td>";
                            
                            // Disable the "End Seminar" button if it is already over
                            if ($status === 'Ended') {
                                echo "<span style='color: var(--text-muted); font-weight: 600; margin-right: 15px; cursor: not-allowed;'>Ended</span>";
                            } else {
                                echo "<a href='admin.php?end_event={$event['event_id']}' class='text-action' onclick=\"return confirm('End this seminar? This will mark all checked-in attendees as Completed.')\" style='margin-right: 15px;'>End Seminar</a>";
                            }

                            // Delete button (Cascading delete)
                            echo "<a href='admin.php?delete_event={$event['event_id']}' class='text-delete' onclick=\"return confirm('Are you sure you want to delete this seminar?')\">🗑️ Delete</a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3' style='text-align:center; color: var(--text-muted);'>No seminars listed.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </section>

        <section id="participants" class="tab-content">
            <h3>Registered Participants</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Seminar</th> 
                        <th>QR Code</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch every participant and join the event name to display in the table
                    $query = "SELECT participants.*, events.event_name 
                            FROM participants 
                            JOIN events ON participants.event_id = events.event_id 
                            ORDER BY participants.participant_id DESC";
                    
                    $result = mysqli_query($conn, $query);
                    if(mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)){
                            
                            // Color code the status badge
                            $reg_participants = "bg-warning"; // Yellow for pending/absent
                            if ($row['participant_status'] == 'Attended' || $row['participant_status'] == 'Completed') {
                                $reg_participants = "bg-success"; // Green for checked-in
                            }
                            
                            echo "<tr>
                                <td>" . htmlspecialchars($row['participant_name']) . "</td>
                                <td>" . htmlspecialchars($row['participant_email']) . "</td>
                                <td style='color: var(--accent-color); font-size: 13px; font-weight: 500;'>" . htmlspecialchars($row['event_name']) . "</td>";
                                
                                // ROUTING FIX 7: Prepend ../../ so the dashboard can find the images
                                echo "<td><img src='../../" . htmlspecialchars($row['participant_qr']) . "' class='qr-thumb' onclick='showFull(this.src)' alt='QR'></td>";
                                
                            echo "<td><span class='badge $reg_participants'>{$row['participant_status']}</span></td>
                                <td>
                                    <a href='admin.php?delete_participant={$row['participant_id']}' class='text-delete' onclick=\"return confirm('Remove this participant?')\">Remove</a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center; color: var(--text-muted);'>No registrations found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </section>

        <section id="reports" class="tab-content">
            <h3>Event Reports</h3>
            <table>
                <thead>
                    <tr>
                        <th>Seminar Name</th>
                        <th>Date Ended</th>
                        <th>Total Registered</th>
                        <th>Total Attended</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Only fetch events that have officially "Ended" to generate final reports
                    $report_query = mysqli_query($conn, "
                        SELECT e.event_id, e.event_name, e.event_date,
                               (SELECT COUNT(*) FROM participants p WHERE p.event_id = e.event_id) as total_reg,
                               (SELECT COUNT(*) FROM participants p WHERE p.event_id = e.event_id AND p.participant_status IN ('Attended', 'Completed')) as total_att
                        FROM events e 
                        WHERE e.event_status = 'Ended' 
                        ORDER BY e.event_date DESC
                    ");

                    if(mysqli_num_rows($report_query) > 0) {
                        while($rep = mysqli_fetch_assoc($report_query)) {
                            echo "<tr>
                                <td><strong>" . htmlspecialchars($rep['event_name']) . "</strong></td>
                                <td>" . date('F d, Y', strtotime($rep['event_date'])) . "</td>
                                <td>{$rep['total_reg']}</td>
                                <td style='color: var(--accent-color); font-weight: 600;'>{$rep['total_att']}</td>
                                <td>
                                    <a href='print_report.php?event_id={$rep['event_id']}' target='_blank' class='text-action' style='background: rgba(248, 243, 43, 0.1); padding: 8px 12px; border-radius: 8px;'>🖨️ Print Report</a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center; color: var(--text-muted); padding: 30px;'>No ended seminars available for reporting yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </section>

        <section id="accounts" class="tab-content">
            <h3>Account Lockout Management</h3>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Failed Attempts</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch all registered users (ignoring other admins)
                    $user_result = mysqli_query($conn, "SELECT * FROM registration WHERE role != 'admin'");
                    if(mysqli_num_rows($user_result) > 0) {
                        while($users = mysqli_fetch_assoc($user_result)){
                            
                            // Color code the status badge based on account state
                            $accounts = "bg-danger"; // Red for deactivated
                            if ($users['status'] == 'active') {
                                $accounts = "bg-success"; // Green for active
                            }
                            
                            echo "<tr>
                                <td>" . htmlspecialchars($users['username']) . "</td>
                                <td><span class='badge $accounts'>{$users['status']}</span></td>
                                <td>{$users['attempts']} / 3</td>
                                <td>";
                                
                                // Unlock button logic: Only show "Unlock" if they are deactivated
                                if($users['status'] == 'deactivated') {
                                    echo "<a href='admin.php?reactivate_id={$users['id']}' class='text-action' style='margin-right: 15px;'>Unlock</a>";
                                } else {
                                    echo "<span style='color: var(--text-muted); margin-right: 15px;'>Active</span>";
                                }

                                // Permanent account deletion button
                                echo "<a href='admin.php?delete_user={$users['id']}' class='text-delete' onclick=\"return confirm('Are you sure you want to completely delete this user account? This cannot be undone.')\">🗑️ Delete</a>";

                            echo "</td></tr>";
                        }
                    } else {
                         echo "<tr><td colspan='4' style='text-align:center; color: var(--text-muted);'>No users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </section>

    </main>

    <div id="modal" onclick="this.style.display='none'">
        <img id="fullImg" alt="Full size QR Code">
    </div>

    <script>
        function openTab(evt, tabId) {
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.classList.remove('active');
            });

            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.classList.remove('active');
            });

            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        // Enlarges the QR code thumbnail
        function showFull(src){
            document.getElementById('modal').style.display = 'flex';
            document.getElementById('fullImg').src = src;
        }
    </script>
</body>
</html>
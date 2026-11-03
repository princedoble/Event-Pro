<?php
session_start();
include '../../config/db.php'; 

// Protection
if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}
//check if event is selected
if(!isset($_GET['event_id'])) {
    die("No event selected.");
}

$event_id = intval($_GET['event_id']);

// Get Event Details
$event_query = mysqli_query($conn, "SELECT * FROM events WHERE event_id = $event_id");
$event = mysqli_fetch_assoc($event_query);

// if no event
if(!$event) {
    die("Event not found.");
}

// Get Attendance Stats
// get the total registered
$reg_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM participants WHERE event_id = $event_id");
$reg_data = mysqli_fetch_assoc($reg_query);
$total_registered = $reg_data['total'];

//get the total attended participants
$att_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM participants WHERE event_id = $event_id AND participant_status IN ('Attended', 'Completed')");
$att_data = mysqli_fetch_assoc($att_query);
$total_attended = $att_data['total'];

// 1. Start by assuming the attendance rate is 0
$attendance_rate = 0;

// 2. Check if there is at least 1 person registered to prevent a math error (dividing by zero)
if ($total_registered > 0) {
    
    // 3. Calculate the raw percentage (e.g., 40 / 50 = 0.8 * 100 = 80)
    $raw_percentage = ($total_attended / $total_registered) * 100;
    
    // 4. Round it to 1 decimal place (e.g., 80.5%)
    $attendance_rate = round($raw_percentage, 1);
}
?>
<title>Report - <?php echo htmlspecialchars($event['event_name']); ?></title>
<style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 40px;
            background: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 { margin: 0; font-size: 28px; }
        .header p { margin: 5px 0 0 0; color: #555; }
        
        .stats-box {
            display: flex;
            justify-content: space-around;
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .stat { text-align: center; }
        .stat h2 { margin: 0; font-size: 24px; color: #000; }
        .stat span { font-size: 14px; color: #666; text-transform: uppercase; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th { background: #f2f2f2; font-weight: bold; }
        
        .print-btn {
            display: block;
            width: 200px;
            margin: 0 auto;
            padding: 15px;
            background: #000;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
        
        @media print {
            .print-btn { display: none; } /* Hides the print button when actually printing */
            body { padding: 0; }
        }
</style>

<body>

    <div class="header">
        <h1>Event-Pro Seminar Report</h1>
        <p><strong><?php echo htmlspecialchars($event['event_name']); ?></strong></p>
        <p>Date: <?php echo date('F d, Y', strtotime($event['event_date'])); ?></p>
    </div>

    <div class="stats-box">
        <div class="stat">
            <h2><?php echo $total_registered; ?></h2>
            <span>Total Registered</span>
        </div>
        <div class="stat">
            <h2><?php echo $total_attended; ?></h2>
            <span>Total Attended</span>
        </div>
        <div class="stat">
            <h2><?php echo $attendance_rate; ?>%</h2>
            <span>Attendance Rate</span>
        </div>
    </div>

    <h3>Attendee List</h3>
    <table>
        <thead>
            <tr>
                <th>Participant Name</th>
                <th>Email Address</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $participants = mysqli_query($conn, "SELECT * FROM participants WHERE event_id = $event_id ORDER BY participant_name ASC");
            if(mysqli_num_rows($participants) > 0) {
                while($p = mysqli_fetch_assoc($participants)) {
                    $status_text = ($p['participant_status'] == 'Attended' || $p['participant_status'] == 'Completed') ? "Present" : "Absent";
                    echo "<tr>
                        <td>" . htmlspecialchars($p['participant_name']) . "</td>
                        <td>" . htmlspecialchars($p['participant_email']) . "</td>
                        <td><strong>{$status_text}</strong></td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='3'>No participants registered.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <button class="print-btn" onclick="window.print()">🖨️ Print Document</button>

</body>

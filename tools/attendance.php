<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$dbname = 'rhr';
$username = 'root';
$password = ''; // Adjust to your actual password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch workers, assignments, and shifts related to the currently assigned sites
$queryAssigned = "
    SELECT 
        sa.assignment_guid,
        sa.shift_start_date,
        sa.shift_end_date,
        u.first_name,
        u.last_name,
        u.user_guid,
        si.site_name,
        si.site_guid,
        s.shift_guid,
        s.shift_start,
        s.shift_end,
        sa.description
    FROM 
        shift_assignments sa
    JOIN 
        shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
    JOIN 
        users u ON saw.user_guid = u.user_guid
    JOIN 
        sites si ON sa.site_guid = si.site_guid
    LEFT JOIN 
        shifts s ON s.user_guid = u.user_guid 
            AND s.site_guid = sa.site_guid 
            AND DATE(s.shift_start) BETWEEN sa.shift_start_date AND sa.shift_end_date
    ORDER BY 
        u.user_guid, sa.shift_start_date
";
$stmtAssigned = $pdo->prepare($queryAssigned);
$stmtAssigned->execute();
$assignments = $stmtAssigned->fetchAll(PDO::FETCH_ASSOC);

// Fetch unassigned shifts for users
$queryUnassigned = "
    SELECT 
        s.shift_guid,
        s.shift_start,
        s.shift_end,
        u.first_name,
        u.last_name,
        u.user_guid,
        si.site_name,
        si.site_guid,
        sa.shift_start_date AS assignment_start_date,
        sa.shift_end_date AS assignment_end_date
    FROM 
        shifts s
    JOIN 
        users u ON s.user_guid = u.user_guid
    JOIN 
        sites si ON s.site_guid = si.site_guid
    LEFT JOIN 
        shift_assignment_workers saw ON s.user_guid = saw.user_guid 
            AND saw.assignment_guid IN (
                SELECT sa.assignment_guid 
                FROM shift_assignments sa 
                WHERE sa.site_guid = s.site_guid
            )
    LEFT JOIN
        shift_assignments sa ON sa.site_guid = s.site_guid
    WHERE 
        saw.user_guid IS NULL
        AND DATE(s.shift_start) BETWEEN sa.shift_start_date AND sa.shift_end_date
    ORDER BY 
        u.user_guid, s.shift_start

";
$stmtUnassigned = $pdo->prepare($queryUnassigned);
$stmtUnassigned->execute();
$unassignedShifts = $stmtUnassigned->fetchAll(PDO::FETCH_ASSOC);

// Function to generate all dates in range
function getDateRange($start, $end) {
    $period = new DatePeriod(
        new DateTime($start),
        new DateInterval('P1D'),
        (new DateTime($end))->modify('+1 day') // End inclusive
    );
    $dates = [];
    foreach ($period as $date) {
        $dates[] = $date->format('Y-m-d');
    }
    return $dates;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Tracking - Assigned and Unassigned Shifts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 30%;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: center;
        }
        h1 {
            text-align: center;
            color: #333;
            font-size: 2rem;
        }
        .section-title {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            font-weight: bolder;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #5c9ae1;
            color: white;
        }
        .attended {
            background-color: #4caf50;
            color: white;
        }
        .missed {
            background-color: #f44336;
            color: white;
        }
        .no-shifts {
            color: #999;
            font-style: italic;
        }
        .summary-box {
            margin-top: 30px;
            padding: 15px;
            background-color: #eef7f9;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            text-align: center;
        }
        .total-box {
            margin-top: 20px;
            padding: 10px;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            color: #0c5460;
            text-align: center;
        }
        .back-btn {
            display: inline-block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #5c9ae1;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .back-btn:hover {
            background-color: #428bca;
        }
        footer {
            text-align: center;
            margin-top: 50px;
            color: #888;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Assigned Shifts Tracking</h1>
    <?php
    // Track processed workers to avoid duplications
    $processedWorkers = [];

    foreach ($assignments as $assignment):
        if (in_array($assignment['user_guid'] . $assignment['assignment_guid'], $processedWorkers)) {
            continue; // Skip if already processed
        }
        $processedWorkers[] = $assignment['user_guid'] . $assignment['assignment_guid'];

        ?>
        <div class="summary-box">
            <h2>Assignment: <?= $assignment['description']; ?> (GUID: <?= $assignment['assignment_guid']; ?>)</h2>
            <table>
                <tr>
                    <th>Worker Name</th>
                    <th>Site Name</th>
                    <th>Assignment Period</th>
                </tr>
                <tr>
                    <td><?= $assignment['first_name'] . ' ' . $assignment['last_name']; ?> (GUID: <?= $assignment['user_guid']; ?>)</td>
                    <td><?= $assignment['site_name']; ?> (GUID: <?= $assignment['site_guid']; ?>)</td>
                    <td><?= $assignment['shift_start_date']; ?> to <?= $assignment['shift_end_date']; ?></td>
                </tr>
            </table>
        </div>

        <?php
        // Get the full date range for the assignment
        $assignmentDays = getDateRange($assignment['shift_start_date'], $assignment['shift_end_date']);

        // Get shifts for the worker within the assignment period
        $shifts = [];
        foreach ($assignments as $shift) {
            if ($shift['user_guid'] == $assignment['user_guid'] && !is_null($shift['shift_start'])) {
                $shifts[] = [
                    'shift_guid' => $shift['shift_guid'],
                    'shift_start' => date('Y-m-d H:i:s', strtotime($shift['shift_start'])),
                    'shift_end' => !empty($shift['shift_end']) ? date('Y-m-d H:i:s', strtotime($shift['shift_end'])) : 'N/A',
                    'date' => date('Y-m-d', strtotime($shift['shift_start']))
                ];
            }
        }
        ?>

        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Status</th>
                <th>Shift GUID</th>
                <th>Shift Start</th>
                <th>Shift End</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($assignmentDays as $day): ?>
                <?php
                // Check if a shift occurred on the current day
                $shiftInfo = array_filter($shifts, function($shift) use ($day) {
                    return $shift['date'] === $day;
                });
                $shiftInfo = reset($shiftInfo); // Get the first matching shift for the day
                ?>
                <tr>
                    <td><?= $day; ?></td>
                    <td class="<?= $shiftInfo ? 'attended' : 'missed'; ?>">
                        <?= $shiftInfo ? 'Attended' : 'Missed'; ?>
                    </td>
                    <td><?= $shiftInfo ? $shiftInfo['shift_guid'] : 'N/A'; ?></td>
                    <td><?= $shiftInfo ? $shiftInfo['shift_start'] : 'N/A'; ?></td>
                    <td><?= $shiftInfo ? $shiftInfo['shift_end'] : 'N/A'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // Fetch unassigned shifts for this user
        $userUnassignedShifts = array_filter($unassignedShifts, function($shift) use ($assignment) {
            return $shift['user_guid'] == $assignment['user_guid'];
        });

        if (count($userUnassignedShifts) > 0): ?>
            <!-- Display Unassigned Shifts below the assigned shifts for the same user -->
            <h2>Unassigned Shifts for <?= $assignment['first_name'] . ' ' . $assignment['last_name']; ?></h2>
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Shift GUID</th>
                    <th>Shift Start</th>
                    <th>Shift End</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $shifts_output = [];
                foreach ($userUnassignedShifts as $shift):

                    $unassignedDays = getDateRange($shift['assignment_start_date'], $shift['assignment_end_date']);
                    foreach ($unassignedDays as $day):
                        // Check if a shift occurred on the current day
                        $shiftOccurred = (date('Y-m-d', strtotime($shift['shift_start'])) == $day);
                        if(!isset($shifts_output[$day]) || !$shifts_output[$day]["occurred"]) {
                            $shifts_output[$day] = ["day" => $day, "occurred" => $shiftOccurred, "shift" => $shift];
                        }

                    endforeach;
                endforeach;

                foreach ($shifts_output as $day => $data)
                {
                    ?>
                    <tr>
                        <td><?= $day; ?></td>
                        <td class="<?= $data['occurred'] ? 'attended' : 'missed'; ?>">
                            <?= $data['occurred'] ? 'Attended' : 'Missed'; ?>
                        </td>
                        <td><?= $data['occurred'] ? $data['shift']['shift_guid'] : 'N/A'; ?></td>
                        <td><?= date('d/m H:i', strtotime($data['shift']['shift_start'])); ?></td>
                        <td><?= $data['occurred'] ? ($data['shift']['shift_end'] ? date('d/m H:i', strtotime($data['shift']['shift_end'])) : 'N/A') : 'N/A'; ?></td>
                    </tr>
                    <?php
                }
                ?>

                </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="center">
        <a href="index.php" class="back-btn">Back to Tools</a>
    </div>
</div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>
</body>
</html>

<!--
Date	Status	Shift GUID	Shift Start	Shift End
2024-10-02	Missed	N/A	2024-10-03 16:30:00	N/A
2024-10-03	Attended	2	2024-10-03 16:30:00	2024-10-04 03:15:00
2024-10-04	Attended	4	2024-10-04 04:00:00	2024-10-04 04:10:00
2024-10-05	Attended	11	2024-10-05 18:00:00	2024-10-06 03:15:00
2024-10-04	Missed	N/A	N/A N/A	N/A
2024-10-05	Missed	N/A	N/A N/A	N/A
2024-10-06	Missed	N/A	N/A N/A	N/A
2024-10-07	Missed	N/A	N/A N/A	N/A
2024-10-08	Missed	N/A	N/A N/A	N/A
2024-10-09	Missed	N/A	N/A N/A	N/A
-->











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

// Fetch workers, assignments, shifts, and hourly rate based on the selected month and year
if (isset($_GET['month']) && isset($_GET['year'])) {
    $selectedMonth = $_GET['month'];
    $selectedYear = $_GET['year'];
} else {
    $selectedMonth = date('m');  // Current month by default
    $selectedYear = date('Y');   // Current year by default
}

$query = "
    SELECT 
        u.first_name,
        u.last_name,
        u.user_guid,
        w.worker_id,
        w.hourly_rate,
        s.shift_guid,
        s.shift_start,
        s.shift_end
    FROM 
        users u
    LEFT JOIN 
        workers w ON w.user_guid = u.user_guid
    LEFT JOIN 
        shifts s ON s.user_guid = u.user_guid 
            AND MONTH(s.shift_start) = :month 
            AND YEAR(s.shift_start) = :year
            AND s.shift_start != s.shift_end
            AND u.user_guid != 0
    ORDER BY 
        u.user_guid, s.shift_start
";

$stmt = $pdo->prepare($query);
$stmt->execute(['month' => $selectedMonth, 'year' => $selectedYear]);
$workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to calculate total hours
function calculateHoursWorked($shifts) {
    $totalHours = 0;
    $totalMinutes = 0;

    foreach ($shifts as $shift) {
        if (!empty($shift['shift_end']) && $shift['shift_end'] !== 'N/A') {
            $start = new DateTime($shift['shift_start']);
            $end = new DateTime($shift['shift_end']);
            $interval = $start->diff($end);
            $hours = $interval->h;
            $minutes = $interval->i;
            $totalHours += $hours;
            $totalMinutes += $minutes;
        }
    }

    // Convert total minutes to hours and return formatted time
    $totalHours += floor($totalMinutes / 60);
    $totalMinutes = $totalMinutes % 60;
    return ['hours' => $totalHours, 'minutes' => $totalMinutes];
}

// Function to format total time in hours and minutes
function formatTime($hours, $minutes) {
    return sprintf('%02d:%02d', $hours, $minutes);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Summary</title>
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
            background-color: #c5d7f2;
            color: black;
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

        /* Custom Styles for Select Box */
        select {
            padding: 10px;
            font-size: 1rem;
            margin: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f4f4f4;
            cursor: pointer;
            outline: none;
        }

        .center {
            text-align: center;
        }

        button[type="submit"] {
            padding: 10px 20px;
            background-color: #5c9ae1;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }

        button[type="submit"]:hover {
            background-color: #428bca;
        }

        form {
            text-align: center;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Attendance Summary</h1>

    <form method="get">
        <select name="month">
            <option value="01" <?= $selectedMonth == '01' ? 'selected' : ''; ?>>January</option>
            <option value="02" <?= $selectedMonth == '02' ? 'selected' : ''; ?>>February</option>
            <option value="03" <?= $selectedMonth == '03' ? 'selected' : ''; ?>>March</option>
            <option value="04" <?= $selectedMonth == '04' ? 'selected' : ''; ?>>April</option>
            <option value="05" <?= $selectedMonth == '05' ? 'selected' : ''; ?>>May</option>
            <option value="06" <?= $selectedMonth == '06' ? 'selected' : ''; ?>>June</option>
            <option value="07" <?= $selectedMonth == '07' ? 'selected' : ''; ?>>July</option>
            <option value="08" <?= $selectedMonth == '08' ? 'selected' : ''; ?>>August</option>
            <option value="09" <?= $selectedMonth == '09' ? 'selected' : ''; ?>>September</option>
            <option value="10" <?= $selectedMonth == '10' ? 'selected' : ''; ?>>October</option>
            <option value="11" <?= $selectedMonth == '11' ? 'selected' : ''; ?>>November</option>
            <option value="12" <?= $selectedMonth == '12' ? 'selected' : ''; ?>>December</option>
        </select>

        <select name="year">
            <option value="2023" <?= $selectedYear == '2023' ? 'selected' : ''; ?>>2023</option>
            <option value="2024" <?= $selectedYear == '2024' ? 'selected' : ''; ?>>2024</option>
            <!-- Add more years if needed -->
        </select>

        <button type="submit">Generate Report</button> <a style="margin-left: 10px;" href="index.php" class="back-btn">Back to Tools</a>
    </form>

    <?php
    // Group shifts by workers
    $workersGrouped = [];
    foreach ($workers as $worker) {
        if (!isset($workersGrouped[$worker['user_guid']])) {
            $workersGrouped[$worker['user_guid']] = [
                'info' => $worker,
                'shifts' => []
            ];
        }
        if ($worker['shift_guid']) {
            $workersGrouped[$worker['user_guid']]['shifts'][] = $worker;
        }
    }

    foreach ($workersGrouped as $worker):
        if(!$worker['info']['worker_id'])
            continue;
        ?>

        <div class="summary-box">
            <h2><?= '#' . $worker['info']['worker_id'] . ' ' .$worker['info']['first_name'] . ' ' . $worker['info']['last_name']; ?></h2>

            <?php if (!empty($worker['shifts'])): ?>
                <table>
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shift GUID</th>
                        <th>Shift Start</th>
                        <th>Shift End</th>
                        <th>Total Time</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($worker['shifts'] as $shift): ?>
                        <?php
                        // Calculate shift duration
                        $start = new DateTime($shift['shift_start']);
                        $end = new DateTime($shift['shift_end']);
                        $interval = $start->diff($end);
                        $totalTime = formatTime($interval->h, $interval->i);
                        ?>
                        <tr>
                            <td><?= date('Y-m-d', strtotime($shift['shift_start'])); ?></td>
                            <td><?= $shift['shift_guid']; ?></td>
                            <td><?= date('Y-m-d H:i:s', strtotime($shift['shift_start'])); ?></td>
                            <td><?= date('Y-m-d H:i:s', strtotime($shift['shift_end'])); ?></td>
                            <td><?= $totalTime; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                // Calculate total hours and minutes worked
                $totalTimeWorked = calculateHoursWorked($worker['shifts']);
                $totalWage = ($totalTimeWorked['hours'] + $totalTimeWorked['minutes'] / 60) * $worker['info']['hourly_rate'];
                ?>
                <div class="total-box">
                    <p><strong>Total Hours Worked:</strong> <?= formatTime($totalTimeWorked['hours'], $totalTimeWorked['minutes']); ?> hours *
                    <strong>Hourly Rate:</strong> <?= number_format($worker['info']['hourly_rate'] ?? 0, 2); ?> per hour =
                    <strong>Total Wage:</strong> <?= number_format($totalWage, 2); ?> &#8362;</p>
                </div>
            <?php else: ?>
                <p class="no-shifts">No shifts for this worker during the selected period.</p>
            <?php endif; ?>
        </div>

    <?php endforeach; ?>
</div>

<footer class="center">
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>
</body>
</html>

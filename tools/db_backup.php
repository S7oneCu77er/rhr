<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters
$host = 'localhost';
$dbname = 'rhr';
$username = 'root';
$password = ''; // Adjust this to your actual password

// Connect to the database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to generate the MySQL data dump only
function backupDatabaseData($pdo) {
    $backupDir = __DIR__ . "/backups"; // Use a folder inside the current directory
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true); // Create the backups directory if it doesn't exist
    }

    $backupFile = $backupDir . '/data_backup_' . date('Y-m-d_H-i-s') . '.sql';

    // Initialize SQL string
    $sqlDump = "-- Data Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";

    // Get all tables in the database
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Get all data from the table
        $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $sqlDump .= "-- Data for table `$table`\n";
            foreach ($rows as $row) {
                $columns = array_map(fn($col) => "`" . addslashes($col) . "`", array_keys($row));
                $values = array_map(function($val) use ($pdo) {
                    return ($val === null) ? 'NULL' : $pdo->quote($val);
                }, array_values($row));
                $sqlDump .= "INSERT IGNORE INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
            }
            $sqlDump .= "\n";
        }
    }

    // Write dump to file
    file_put_contents($backupFile, $sqlDump);

    return $backupFile;
}

// Handle backup request
if (isset($_POST['backup'])) {
    $backupFile = backupDatabaseData($pdo);

    if ($backupFile) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($backupFile));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backupFile));
        flush(); // Flush system output buffer
        readfile($backupFile);
        unlink($backupFile); // Remove the file after download
        exit;
    } else {
        $error = "Failed to create the database backup.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup</title>
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
            color: #333;
            font-size: 2rem;
            margin-bottom: 20px;
        }

        p {
            font-size: 1rem;
            color: #555;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            background-color: #5c9ae1;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #428bca;
        }

        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .error {
            color: #f44336;
            font-weight: bold;
        }

        a.back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #5c9ae1;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        a.back-btn:hover {
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
    <h1>Database Backup</h1>
    <p>Click the button below to create a backup of the database.<br>A download of the MySQL dump file will start automatically.</p>

    <?php if (isset($error)): ?>
        <p class="error"><?= $error; ?></p>
    <?php endif; ?>

    <form method="post" action="db_backup.php">
        <button type="submit" class="btn" name="backup">Backup Database</button>
    </form>

    <a href="index.php" class="back-btn">Back to Tools</a>
</div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>

</body>
</html>

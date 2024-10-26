<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/**
 * Function to translate country names from Hebrew to English.
 *
 * @param string $countryName The country name in Hebrew.
 * @return string The translated country name in English.
 */
function translateCountry($countryName) {
    $translations = [
        'הודו' => 'India',
        'סרי לנקה' => 'Sri Lanka',
        'מולדובה' => 'Moldova',
        'ישראל' => 'Israel',
        // Add more translations as needed
    ];
    return isset($translations[$countryName]) ? $translations[$countryName] : $countryName;
}

// Database connection parameters
$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = ''; // Updated to match your actual password

try {
    // Connect to the database using PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set PDO error mode to exception for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure that house_address is unique in the houses table
    try {
        $pdo->exec("ALTER TABLE houses ADD UNIQUE (house_address)");
    } catch (PDOException $e) {
        if ($e->getCode() != '42000') { // SQLSTATE code for syntax error or access violation
            die("Error altering houses table: " . $e->getMessage());
        }
        // Else, assume the unique constraint already exists
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to process workers CSV
function processWorkers($pdo, $workersFilename) {
    $workersData = [];

    if (($handle = fopen($workersFilename, 'r')) !== false) {
        if (($headers = fgetcsv($handle, 1000, ',')) !== false) {
            $headers = array_map('trim', $headers);
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($row) == count($headers)) {
                    $record = array_combine($headers, $row);
                    $workersData[] = $record;
                }
            }
        }
        fclose($handle);
    } else {
        die("Unable to open the file $workersFilename");
    }

    $pdo->beginTransaction();

    try {
        $houseStmt = $pdo->prepare("
            INSERT INTO houses (
                house_address, address_description, house_size_sqm, number_of_rooms, number_of_toilets, contract_number,
                contract_start, contract_end, security_deed, monthly_rent, monthly_arnona, monthly_water, monthly_electric,
                monthly_gas, monthly_vaad, landlord_name, landlord_id, landlord_phone, landlord_email, vaad_name, vaad_phone, max_tenants
            ) VALUES (
                :house_address, :address_description, :house_size_sqm, :number_of_rooms, :number_of_toilets, :contract_number,
                :contract_start, :contract_end, :security_deed, :monthly_rent, :monthly_arnona, :monthly_water, :monthly_electric,
                :monthly_gas, :monthly_vaad, :landlord_name, :landlord_id, :landlord_phone, :landlord_email, :vaad_name, :vaad_phone, :max_tenants
            ) ON DUPLICATE KEY UPDATE house_guid = LAST_INSERT_ID(house_guid)
        ");

        $userStmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, passport_id, password, email, phone_number, country, description, `group`)
            VALUES (:first_name, :last_name, :passport_id, '123456789', :email, :phone_number, :country, :description, 'workers')
            ON DUPLICATE KEY UPDATE user_guid = LAST_INSERT_ID(user_guid)
        ");

        $workerStmt = $pdo->prepare("
            INSERT INTO workers (user_guid, andromeda_guid, worker_id, profession, hourly_rate, account, foreign_phone,
                height_training, house_guid, health_insurance, on_relief, relief_end_date, description)
            VALUES (:user_guid, :andromeda_guid, :worker_id, :profession, :hourly_rate, :account, :foreign_phone,
                :height_training, :house_guid, :health_insurance, :on_relief, :relief_end_date, :description)
            ON DUPLICATE KEY UPDATE user_guid = user_guid
        ");

        $passportCheckStmt = $pdo->prepare("
            SELECT user_guid FROM users WHERE passport_id = :passport_id
        ");

        foreach ($workersData as $record) {
            $passport_id = isset($record['דרכון']) ? trim($record['דרכון']) : '';
            $account = isset($record['חשבון']) ? trim($record['חשבון']) : '';
            $phone_number = isset($record['טלפון נייד']) ? trim($record['טלפון נייד']) : '0';
            $foreign_phone = isset($record['טלפון חול']) ? trim($record['טלפון חול']) : null;
            $last_name = isset($record['שם משפחה']) ? trim($record['שם משפחה']) : '';
            $first_name = isset($record['שם פרטי']) ? trim($record['שם פרטי']) : '';
            $worker_id = isset($record["מס' עובד"]) ? trim($record["מס' עובד"]) : '';
            $landing_date = isset($record['תאריך נחיתה']) ? trim($record['תאריך נחיתה']) : '';
            $profession = isset($record['מקצוע']) ? trim($record['מקצוע']) : '';
            $andromeda_guid = isset($record["מס' עובד באנדרומדה"]) ? trim($record["מס' עובד באנדרומדה"]) : 0;
            $country = isset($record['מדינה']) ? translateCountry(trim($record['מדינה'])) : 'Israel';
            $height_training = (isset($record['ההדרכה בגובה']) && strtolower(trim($record['ההדרכה בגובה'])) === 'כן') ? 1 : 0;
            $health_insurance = (isset($record['ביטוח בריאות']) && trim($record['ביטוח בריאות']) === 'בתוקף') ? date('Y-m-d') : null;
            $apartment = isset($record['דירה']) ? trim($record['דירה']) : 'No Assigned House';
            $contractor = isset($record['קבלן']) ? trim($record['קבלן']) : 'No Description';
            $birth_date = isset($record['תאריך לידה']) ? trim($record['תאריך לידה']) : '1970-01-01';
            $status = isset($record['סטטוס']) ? trim($record['סטטוס']) : 'Unknown';

            // Handle invalid or duplicate passport_id
            if ($passport_id === '' || $passport_id === '0' || strtolower($passport_id) === 'מעבר') {
                $unique_suffix = uniqid();
                $passport_id = 'unknown_' . $unique_suffix;
            }

            // Check if passport_id already exists
            $passportCheckStmt->execute([':passport_id' => $passport_id]);
            $existingUser = $passportCheckStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                $user_guid = $existingUser['user_guid'];
            } else {
                $userStmt->execute([
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':passport_id' => $passport_id,
                    ':email' => null,
                    ':phone_number' => $phone_number,
                    ':country' => $country,
                    ':description' => "Landing Date: $landing_date; Birth Date: $birth_date; Status: $status"
                ]);
                $user_guid = $pdo->lastInsertId();
            }

            // Insert or get house_guid
            $houseStmt->execute([
                ':house_address' => $apartment,
                ':address_description' => 'No Description',
                ':house_size_sqm' => 0,
                ':number_of_rooms' => 0,
                ':number_of_toilets' => 0,
                ':contract_number' => '0',
                ':contract_start' => '1970-01-01',
                ':contract_end' => '1970-01-01',
                ':security_deed' => '0',
                ':monthly_rent' => 0,
                ':monthly_arnona' => 0,
                ':monthly_water' => 0,
                ':monthly_electric' => 0,
                ':monthly_gas' => 0,
                ':monthly_vaad' => 0,
                ':landlord_name' => '0',
                ':landlord_id' => '0',
                ':landlord_phone' => '0',
                ':landlord_email' => '0',
                ':vaad_name' => '0',
                ':vaad_phone' => '0',
                ':max_tenants' => 0
            ]);
            $house_guid = $pdo->lastInsertId();

            // Insert into workers
            $workerStmt->execute([
                ':user_guid' => $user_guid,
                ':andromeda_guid' => $andromeda_guid,
                ':worker_id' => $worker_id,
                ':profession' => $profession,
                ':hourly_rate' => 0.00,
                ':account' => is_numeric($account) ? (int)$account : 0,
                ':foreign_phone' => $foreign_phone,
                ':height_training' => $height_training,
                ':house_guid' => $house_guid,
                ':health_insurance' => $health_insurance,
                ':on_relief' => 0,
                ':relief_end_date' => date('Y-m-d'),
                ':description' => $contractor
            ]);
        }

        $pdo->commit();
        echo "<p class='success'>Workers data inserted successfully.</p>";
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Failed to insert workers data: " . $e->getMessage());
    }
}

// Function to process sites CSV
function processSites($pdo, $sitesFilename) {
    $sitesData = [];

    if (($handle = fopen($sitesFilename, 'r')) !== false) {
        if (($headers = fgetcsv($handle, 1000, ',')) !== false) {
            $headers = array_map('trim', $headers);
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($row) == count($headers)) {
                    $record = array_combine($headers, $row);

                    // Skip special rows like '---לא עבדו---', '---אישור מחלה---'
                    if (strpos($record['קבלן'], '---') !== false) {
                        continue;
                    }

                    $sitesData[] = $record;
                }
            }
        }
        fclose($handle);
    } else {
        die("Unable to open the file $sitesFilename");
    }

    $pdo->beginTransaction();

    try {
        $siteStmt = $pdo->prepare("
            INSERT INTO sites (
                site_name, 
                site_address, 
                phone_number, 
                shiftStart_time, 
                shiftEnd_time, 
                site_owner_guid
            ) VALUES (
                :site_name, 
                :site_address, 
                :phone_number, 
                :shiftStart_time, 
                :shiftEnd_time, 
                NULL
            )
            ON DUPLICATE KEY UPDATE site_guid = site_guid
        ");

        foreach ($sitesData as $record) {
            $contractor = isset($record['קבלן']) ? trim($record['קבלן']) : '';
            $site = isset($record['אתר']) ? trim($record['אתר']) : '';
            $site_manager = isset($record['מנהל עבודה']) ? trim($record['מנהל עבודה']) : '';
            $phone = isset($record['טל.']) ? trim($record['טל.']) : '0';

            $site_name = !empty($site) ? trim($site) : trim($contractor);
            $site_address = !empty($site) ? trim($contractor) : '';
            $shiftStart_time = '07:00:00';
            $shiftEnd_time = '18:00:00';

            $siteStmt->execute([
                ':site_name' => $site_name,
                ':site_address' => $site_address,
                ':phone_number' => $phone,
                ':shiftStart_time' => $shiftStart_time,
                ':shiftEnd_time' => $shiftEnd_time
            ]);
        }

        $pdo->commit();
        echo "<p class='success'>Sites data inserted successfully.</p>";
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Failed to insert sites data: " . $e->getMessage());
    }
}

// Function to upload CSV file
function uploadCSV() {
    $uploadDir = __DIR__;
    $targetFile = $uploadDir . '/' . basename($_FILES['csvFile']['name']);
    $fileType = pathinfo($targetFile, PATHINFO_EXTENSION); // Get the file extension

    // Check if the file is a valid CSV file
    if ($fileType !== 'csv') {
        $_SESSION['upload_message'] = "<p class='error'>Invalid file type. Only CSV files are allowed.</p>";
        return;
    }

    if (move_uploaded_file($_FILES['csvFile']['tmp_name'], $targetFile)) {
        // Store the success message in a session variable so it can be shown later
        $_SESSION['upload_message'] = "<p class='success'>File uploaded successfully: " . htmlspecialchars(basename($_FILES['csvFile']['name'])) . "</p>";
    } else {
        $_SESSION['upload_message'] = "<p class='error'>File upload failed.</p>";
    }
}

function truncateAllTables($pdo) {
    // Query to get all table names
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    try {
        // Disable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Loop through each table and truncate it
        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE `$table`");
        }

        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $e) {
        $_SESSION['upload_message'] =  "<p class='error'>" . $e->getMessage() . "</p>";
        return;
    }

    $_SESSION['upload_message'] =  "<p class='success'>All tables truncated successfully.</p>";
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    uploadCSV();
}

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    $filename = $_GET['filename'] ?? null;

    if(!$type || ( $type != 'truncate' && (!$filename || $filename == "") ) ) {
        echo "Invalid request".$filename;
        return;
    }

    try {
        if ($type === 'workers') {
            processWorkers($pdo, $filename);
        } elseif ($type === 'sites') {
            processSites($pdo, $filename);
        } elseif ($type === 'truncate') {
            truncateAllTables($pdo);
        } else {
            echo "Invalid type specified.";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert CSV Tools</title>
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
        }

        .form-group {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-group input[type="file"],
        .form-group input[type="text"] {
            padding: 10px;
            width: 35%;
            margin-right: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .btn {
            padding: 10px 20px;
            color: white;
            background-color: #5c9ae1;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            white-space: nowrap;
            width: 35%;
        }

        .btn:hover {
            background-color: #428bca;
        }

        .btn:disabled {
            background-color: #ccc;
        }

        .red-btn {
            background-color: #e74c3c; /* Nice red color */
        }

        .red-btn:hover {
            background-color: #c0392b; /* Darker red on hover */
        }

        #progress {
            margin-top: 20px;
            font-weight: bold;
            font-size: 1.2rem;
            color: #5c9ae1;
        }

        .success {
            font-size: 1.2rem;
            color: #4caf50;
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

        /* Flex styling for a more aligned layout */
        form {
            margin: 0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        input {
            text-align: center;
        }

        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            text-align: center;
        }

        .modal h2 {
            color: #e74c3c;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .modal p {
            color: #333;
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        .modal-buttons {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }

        .modal-buttons .btn {
            width: 45%;
            padding: 10px;
        }

        .red-btn {
            background-color: #e74c3c;
        }

        .red-btn:hover {
            background-color: #c0392b;
        }

    </style>
</head>
<body>

<div class="container">
    <h1>CSV Data Conversion</h1>

    <!-- File upload form -->
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <input type="file" name="csvFile" required>
            <button type="submit" class="btn">Upload CSV</button>
        </div>
    </form>

    <!-- Workers CSV Processing -->
    <form method="get" id="workers-form">
        <div class="form-group">
            <input type="text" name="workers_filename" id="workers-filename" placeholder="Workers CSV filename" required>
            <button type="button" class="btn" id="workers-btn" onclick="processCSV('workers')">Process Workers CSV</button>
        </div>
    </form>

    <!-- Sites CSV Processing -->
    <form method="get" id="sites-form">
        <div class="form-group">
            <input type="text" name="sites_filename" id="sites-filename" placeholder="Sites CSV filename" required>
            <button type="button" class="btn" id="sites-btn" onclick="processCSV('sites')">Process Sites CSV</button>
        </div>
    </form>

    <!-- Truncate Button -->
    <div class="form-group">
        <button class="btn red-btn" id="truncate-btn" onclick="showWarningModal()">Truncate All Tables</button>
    </div>

    <!-- Modal for Confirmation -->
    <div id="warningModal" class="modal">
        <div class="modal-content">
            <h2>WARNING</h2>
            <p>This action will DELETE ALL DATA.<br>This CANNOT BE UNDONE!<br>Are you SURE you want to continue?</p>
            <div class="modal-buttons">
                <button class="btn red-btn" onclick="truncateTables()">Yes, Delete All Data</button>
                <button class="btn" onclick="closeWarningModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Progress/Error/Success messages will be shown here -->
    <div id="progress">
        <?php
        // Display upload message if set in session
        if (isset($_SESSION['upload_message'])) {
            echo $_SESSION['upload_message'];
            unset($_SESSION['upload_message']); // Clear message after displaying
        }
        ?>
    </div>

    <a href="index.php" class="back-btn">Back to Tools</a>
</div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>

<script>

        // Show the warning modal
        function showWarningModal() {
        document.getElementById('warningModal').style.display = 'flex';
    }

        // Close the modal without doing anything
        function closeWarningModal() {
        document.getElementById('warningModal').style.display = 'none';
    }

    function processCSV(type) {
        var xhr = new XMLHttpRequest();
        var btn = type === 'workers' ? document.getElementById('workers-btn') : document.getElementById('sites-btn');
        var progress = document.getElementById('progress');
        var filename = document.querySelector(`input[name='` + type + `_filename']`).value;

        btn.disabled = true;
        progress.textContent = 'Processing ' + type + ' CSV...';

        xhr.open('GET', 'convert_csv.php?type=' + type + '&filename=' + filename, true);
        xhr.onload = function () {
            if (xhr.status === 200) {
                progress.innerHTML = xhr.responseText;
            } else {
                progress.textContent = 'Error occurred: ' + xhr.status;
            }
            btn.disabled = false;
        };
        xhr.send();
    }

    // Proceed with truncating tables if confirmed
    function truncateTables() {
        closeWarningModal(); // Close the modal before proceeding

        var xhr = new XMLHttpRequest();
        var btn = document.getElementById('truncate-btn');
        var progress = document.getElementById('progress');

        btn.disabled = true;
        progress.textContent = 'Truncating all tables...';

        xhr.open('GET', 'convert_csv.php?type=truncate', true); // This calls the backend truncate function
        xhr.onload = function () {
            if (xhr.status === 200) {
                progress.innerHTML = xhr.responseText;
                location.href = "convert_csv.php";
            } else {
                progress.textContent = 'Error occurred: ' + xhr.status;
            }
            btn.disabled = false;
        };
        xhr.send();
    }
</script>
</body>
</html>


<?php
// pages/admin/workers/add.php

// Include necessary configurations and handlers
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php'; // Ensure language handler is included

global $lang_data, $selectedLanguage, $MySQL;

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Handle form submission for adding a new worker
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {


            if(isset($_FILES['workers_csv']))
            {
                try {
                    processWorkers($MySQL->getPDO(), $_FILES['workers_csv']);
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['add_workers_file_success'] ?? 'Workers successfully imported from file') . "', true);</script>";
                    return;
                } catch (Exception $e) {
                    echo "<script>showError('" . $e->getMessage() . "', true);</script>";
                    return;
                }
            }

            // Retrieve and sanitize form inputs
            $user_guid = intval($_POST['user_guid']);
            $andromeda_guid = intval($_POST['andromeda_guid']);
            $profession = trim($_POST['profession']);
            $hourly_rate = floatval($_POST['hourly_rate']);
            $account = intval($_POST['account']);
            $foreign_phone = trim($_POST['foreign_phone']);
            $height_training = isset($_POST['height_training']) && $_POST['height_training'] === '1' ? 1 : 0;
            $house_guid = intval($_POST['house_guid']);
            $health_insurance = !empty($_POST['health_insurance']) ? $_POST['health_insurance'] : null;
            $description = trim($_POST['description']);
            $on_relief = isset($_POST['on_relief']) && $_POST['on_relief'] === '1' ? 1 : 0;
            $relief_end_date = !empty($_POST['relief_end_date']) ? $_POST['relief_end_date'] : null;
            $driver_license_date = !empty($_POST['driver_license']) ? $_POST['driver_license'] : null;
            $languages = isset($_POST['languages']) ? $_POST['languages'] : [];

            // Generate the next available worker_id
            $worker_id_stmt = $MySQL->getConnection()->prepare("SELECT IFNULL(MAX(worker_id), 0) + 1 AS next_id FROM workers");
            $worker_id_stmt->execute();
            $worker_id_result = $worker_id_stmt->get_result();
            $worker_id_row = $worker_id_result->fetch_assoc();
            $worker_id = $worker_id_row['next_id'];
            $worker_id_stmt->close();

            // Begin transaction
            $MySQL->getConnection()->begin_transaction();

            // Prepare SQL statement to insert a new worker, including drivers_license
            $sql = "INSERT INTO workers (user_guid, andromeda_guid, worker_id, profession, hourly_rate, account, foreign_phone, height_training, house_guid, health_insurance, drivers_license, description, on_relief, relief_end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $insertStmt = $MySQL->getConnection()->prepare($sql);

            if ($insertStmt) {
                // Bind parameters, including drivers_license
                $insertStmt->bind_param(
                    "iiisdisiisssis",
                    $user_guid,
                    $andromeda_guid,
                    $worker_id,
                    $profession,
                    $hourly_rate,
                    $account,
                    $foreign_phone,
                    $height_training,
                    $house_guid,
                    $health_insurance,
                    $driver_license_date,
                    $description,
                    $on_relief,
                    $relief_end_date
                );

                // Execute the statement
                if ($insertStmt->execute()) {
                    // Insert spoken languages into worker_languages table
                    if (!empty($languages)) {
                        $languageStmt = $MySQL->getConnection()->prepare("INSERT INTO worker_languages (user_guid, language) VALUES (?, ?)");
                        foreach ($languages as $language) {
                            $language = trim($language);
                            $languageStmt->bind_param("is", $user_guid, $language);
                            $languageStmt->execute();
                        }
                        $languageStmt->close();
                    }

                    // Update the user's group to 'drivers' if driver's license date is provided
                    $group_stmt = $MySQL->getConnection()->prepare("SELECT `group` FROM users WHERE user_guid = ?");
                    $users = [];
                    if ($group_stmt) {
                        $group_stmt->bind_param("i", $user_guid);
                        $group_stmt->execute();
                        $user_result = $group_stmt->get_result();
                        $user_row = $user_result->fetch_assoc();
                        $group = $user_row['group'];
                        $group_stmt->close();
                    }
                    if (!empty($driver_license_date) && $group == 'workers') {
                        // Update users group
                        $updateUserGroupStmt = $MySQL->getConnection()->prepare("UPDATE users SET `group` = 'drivers' WHERE user_guid = ?");
                        $updateUserGroupStmt->bind_param("i", $user_guid);
                        $updateUserGroupStmt->execute();
                        $updateUserGroupStmt->close();
                    }


                    $details =
                        "[andromeda: ".$andromeda_guid."]-
                         [workerID: ".$worker_id."]-
                         [profession: ".$profession."]-
                         [rate: ".$hourly_rate."]-
                         [account: ".$account."]-
                         [phone: ".$foreign_phone."]-
                         [height: ".$height_training."]-
                         [house: ".$house_guid."]-
                         [insurance: ".$health_insurance."]-
                         [license: ".$driver_license_date."]-
                         [description: ".$description."]-
                         [relief: ".$on_relief."]-
                         [relief_date: ".$relief_end_date."]";


                    // Commit transaction
                    $MySQL->getConnection()->commit();
                    //logAction($_SESSION['loggedIn']['user_guid'], $user_guid, 'Worker added', 'Worker details: '.$details );
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['add_worker_success'] ?? 'Worker added successfully with #'.$worker_id) . "', true);</script>";
                } else {
                    // Rollback transaction
                    $MySQL->getConnection()->rollback();

                    // Log the error for debugging purposes
                    error_log("Error adding worker: " . $insertStmt->error);
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['add_error'] ?? 'Error adding worker.') . "', true);</script>";
                }

                // Close the statement
                $insertStmt->close();
            } else {
                // Rollback transaction
                $MySQL->getConnection()->rollback();

                // Log the error for debugging purposes
                error_log("Prepare failed: " . $MySQL->getConnection()->error);
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
            }
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['csrf_error'] ?? 'Invalid CSRF token.') . "', true);</script>";
        }
    }
} catch (mysqli_sql_exception $e) {
    // Handle duplicate entry error
    if ($e->getCode() == 1062) {
        $params = $user_guid . " - " .
            $andromeda_guid . " - " .
            $worker_id . " - " .
            $profession . " - " .
            $hourly_rate . " - " .
            $account . " - " .
            $foreign_phone . " - " .
            $height_training . " - " .
            $house_guid . " - " .
            $health_insurance . " - " .
            $on_relief . " - " .
            $relief_end_date . " - " .
            $description;
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found.') . ":" . $params . "', true);</script>";
    } else {
        // Log the error and show a general error message
        print_r("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch all users to link to a user account
$user_stmt = $MySQL->getConnection()->prepare("SELECT user_guid, first_name, last_name FROM users WHERE user_guid > 0 AND `group` != 'disabled' AND user_guid NOT IN (SELECT user_guid FROM workers) ORDER BY first_name ASC, last_name ASC");
$users = [];
if ($user_stmt) {
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    while ($user_row = $user_result->fetch_assoc()) {
        $users[] = $user_row;
    }
    $user_stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

// Fetch all houses to link to a house_guid
$house_stmt = $MySQL->getConnection()->prepare("SELECT house_guid, house_address FROM houses ORDER BY house_guid ASC");
$houses = [];
if ($house_stmt) {
    $house_stmt->execute();
    $house_result = $house_stmt->get_result();
    while ($house_row = $house_result->fetch_assoc()) {
        $houses[] = $house_row;
    }
    $house_stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

// Display the add worker form
echo '
<div class="page">
    <h2 style="margin-top: 5px; margin-bottom: -5px;">' . htmlspecialchars($lang_data[$selectedLanguage]["add_worker"] ?? "Add Worker") . '</h2>
    <div class="edit-user-page">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table>
                <tbody>
                    <tr>
                        <td>
                            <label for="user_guid">' . htmlspecialchars($lang_data[$selectedLanguage]["user_account"] ?? "User Account") . '</label>
                            <select id="user_guid" name="user_guid" required>
                                <option disabled selected value="">' . htmlspecialchars($lang_data[$selectedLanguage]["select_user"] ?? "Select a user") . '</option>';
foreach ($users as $user) {
    echo '                      <option value="' . htmlspecialchars($user['user_guid']) . '">' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</option>';
}
echo '                      </select>
                        </td>
                        <td>
                            <label for="andromeda_guid">' . htmlspecialchars($lang_data[$selectedLanguage]["andromeda_guid"] ?? "Andromeda GUID") . '</label>
                            <input type="text" id="andromeda_guid" name="andromeda_guid" value="0" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="profession">' . htmlspecialchars($lang_data[$selectedLanguage]["profession"] ?? "Profession") . '</label>
                            <input type="text" id="profession" name="profession" value="" required>
                        </td>
                        <td>
                            <label for="hourly_rate">' . htmlspecialchars($lang_data[$selectedLanguage]["hourly_rate"] ?? "Hourly Rate") . '</label>
                            <input type="number" step="1" id="hourly_rate" name="hourly_rate" value="0" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="account">' . htmlspecialchars($lang_data[$selectedLanguage]["account"] ?? "Account") . '</label>
                            <input type="text" id="account" name="account" value="0" required>
                        </td>
                        <td>
                            <label for="foreign_phone">' . htmlspecialchars($lang_data[$selectedLanguage]["foreign_phone"] ?? "Foreign Phone") . '</label>
                            <input type="text" id="foreign_phone" name="foreign_phone" value="">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="height_training">' . htmlspecialchars($lang_data[$selectedLanguage]["height_training"] ?? "Height Training") . '</label>
                            <select id="height_training" name="height_training" required>
                                <option value="1">' . htmlspecialchars($lang_data[$selectedLanguage]["yes"] ?? "Yes") . '</option>
                                <option value="0" selected>' . htmlspecialchars($lang_data[$selectedLanguage]["no"] ?? "No") . '</option>
                            </select>
                        </td>
                        <td>
                            <label for="house_guid">' . htmlspecialchars($lang_data[$selectedLanguage]["house"] ?? "House") . '</label>
                            <select id="house_guid" name="house_guid" required>
                                <option disabled selected value="">' . htmlspecialchars($lang_data[$selectedLanguage]["select_house"] ?? "Select a house") . '</option>';
foreach ($houses as $house) {
    echo '<option value="' . htmlspecialchars($house['house_guid']) . '">' . htmlspecialchars($house['house_address']) . '</option>';
}
echo '                       </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="health_insurance">' . htmlspecialchars($lang_data[$selectedLanguage]["health_insurance"] ?? "Health Insurance") . '</label>
                            <input type="date" id="health_insurance" name="health_insurance" value="">
                        </td>
                        <td>
                            <label for="on_relief">' . htmlspecialchars($lang_data[$selectedLanguage]["on_relief"] ?? "On Relief") . '</label>
                            <select id="on_relief" name="on_relief" required onchange="handleReliefChange()">
                                <option value="1">' . htmlspecialchars($lang_data[$selectedLanguage]["yes"] ?? "Yes") . '</option>
                                <option value="0" selected>' . htmlspecialchars($lang_data[$selectedLanguage]["no"] ?? "No") . '</option>
                            </select>
                            <input type="date" id="relief_end_date" name="relief_end_date" value="" onchange="checkDate()">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="driver_license">' . htmlspecialchars($lang_data[$selectedLanguage]["driver_license"] ?? "Driver License") . '</label>
                            <input type="date" id="driver_license" name="driver_license" value="">
                        </td>
                        <td>
                            <label for="description">' . htmlspecialchars($lang_data[$selectedLanguage]["description"] ?? "Description") . '</label>
                            <input type="text" id="description" name="description" value="">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <label>' . htmlspecialchars($lang_data[$selectedLanguage]["spoken_languages"] ?? "Spoken Languages") . '</label>
                            <div class="languages-container">
                                <label><input type="checkbox" name="languages[]" value="Hebrew">' . htmlspecialchars($lang_data[$selectedLanguage]["hebrew"] ?? "Hebrew") . '</label>
                                <label><input type="checkbox" name="languages[]" value="English">' . htmlspecialchars($lang_data[$selectedLanguage]["english"] ?? "English") . '</label>
                                <label><input type="checkbox" name="languages[]" value="Russian">' . htmlspecialchars($lang_data[$selectedLanguage]["russian"] ?? "Russian") . '</label>
                                <label><input type="checkbox" name="languages[]" value="Hindi">' . htmlspecialchars($lang_data[$selectedLanguage]["hindi"] ?? "Hindi") . '</label>
                                <label><input type="checkbox" name="languages[]" value="Sinhala">' . htmlspecialchars($lang_data[$selectedLanguage]["sinhala"] ?? "Sinhala") . '</label>
                                <label><input type="checkbox" name="languages[]" value="Arabic">' . htmlspecialchars($lang_data[$selectedLanguage]["arabic"] ?? "Arabic") . '</label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="margin-top: 20px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["add"] ?? "Add") . '</button>
        </form>
    </div>
    </div>
<script>
let today = new Date();

let tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);

document.getElementById("relief_end_date").value = formatDate(tomorrow);

document.getElementById("health_insurance").value = formatDate(today);
document.getElementById("driver_license").value = formatDate(today);

// Initialize the relief_end_date display based on the current selection
document.addEventListener("DOMContentLoaded", function() {
    handleReliefChange();
});


</script>';
?>

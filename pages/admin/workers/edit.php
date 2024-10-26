<?php
// pages/admin/workers/edit.php

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

// Validate and sanitize the user_guid from GET
if (isset($_GET['user_guid'])) {
    $user_guid = intval($_GET['user_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Handle form submission for updating worker details
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            // Retrieve and sanitize form inputs
            $updated_andromeda_guid = intval($_POST['andromeda_guid']);
            $updated_workerID = intval($_POST['workerid']);
            $updated_profession = trim($_POST['profession']);
            $updated_hourly_rate = floatval($_POST['hourly_rate']);
            $updated_account = intval($_POST['account']);
            $updated_phone_number = trim($_POST['phone_number']);
            $updated_foreign_phone = trim($_POST['foreign_phone']);
            $updated_height_training = isset($_POST['height_training']) && $_POST['height_training'] === '1' ? 1 : 0;
            $updated_house_guid = intval($_POST['house_guid']);
            $updated_health_insurance = !empty($_POST['health_insurance']) ? $_POST['health_insurance'] : null;
            $updated_drivers_license = !empty($_POST['drivers_license']) ? $_POST['drivers_license'] : null;
            $updated_description = trim($_POST['description']);
            $updated_on_relief = isset($_POST['on_relief']) && $_POST['on_relief'] === '1' ? 1 : 0;
            $updated_relief_end_date = !empty($_POST['relief_end_date']) ? $_POST['relief_end_date'] : null;
            $submitted_languages = isset($_POST['languages']) ? $_POST['languages'] : [];

            // Begin transaction
            $MySQL->getConnection()->begin_transaction();

            // Update workers table
            $sql = "UPDATE workers 
                    SET andromeda_guid = ?, 
                        worker_id = ?, 
                        profession = ?, 
                        hourly_rate = ?, 
                        account = ?, 
                        foreign_phone = ?, 
                        height_training = ?, 
                        house_guid = ?, 
                        health_insurance = ?, 
                        drivers_license = ?, 
                        description = ?, 
                        on_relief = ?, 
                        relief_end_date = ?
                    WHERE user_guid = ?";
            $updateStmt = $MySQL->getConnection()->prepare($sql);
            if ($updateStmt) {
                $updateStmt->bind_param(
                    "iisdisiisssisi",
                    $updated_andromeda_guid,
                    $updated_workerID,
                    $updated_profession,
                    $updated_hourly_rate,
                    $updated_account,
                    $updated_foreign_phone,
                    $updated_height_training,
                    $updated_house_guid,
                    $updated_health_insurance,
                    $updated_drivers_license,
                    $updated_description,
                    $updated_on_relief,
                    $updated_relief_end_date,
                    $user_guid
                );

                if ($updateStmt->execute()) {
                    $updateStmt->close();

                    // Compare relief end date with today and unassign the worker if necessary
                    $today = new DateTime();
                    $reliefEndDate = new DateTime($updated_relief_end_date);

                    if ($updated_on_relief && $reliefEndDate > $today) {
                        // Unassign the worker from shift assignments (shift_assignment_workers)
                        $unassignShiftStmt = $MySQL->getConnection()->prepare("DELETE FROM shift_assignment_workers WHERE user_guid = ?");
                        if ($unassignShiftStmt) {
                            $unassignShiftStmt->bind_param("i", $user_guid);
                            $unassignShiftStmt->execute();
                            $unassignShiftStmt->close();
                        }

                        // Unassign the worker from cars (cars.driver_guid)
                        $unassignCarStmt = $MySQL->getConnection()->prepare("UPDATE cars SET driver_guid = NULL WHERE driver_guid = ?");
                        if ($unassignCarStmt) {
                            $unassignCarStmt->bind_param("i", $user_guid);
                            $unassignCarStmt->execute();
                            $unassignCarStmt->close();
                        }
                    }


                    // Update user's phone_number in users table
                    $updateUserPhoneStmt = $MySQL->getConnection()->prepare("UPDATE users SET phone_number = ? WHERE user_guid = ?");
                    if ($updateUserPhoneStmt) {
                        $updateUserPhoneStmt->bind_param("si", $updated_phone_number, $user_guid);
                        $updateUserPhoneStmt->execute();
                        $updateUserPhoneStmt->close();
                    }

                    // Handle languages
                    // First, fetch existing languages
                    $existing_languages = [];
                    $fetchLangStmt = $MySQL->getConnection()->prepare("SELECT language FROM worker_languages WHERE user_guid = ?");
                    if ($fetchLangStmt) {
                        $fetchLangStmt->bind_param("i", $user_guid);
                        $fetchLangStmt->execute();
                        $fetchLangStmt->bind_result($language);
                        while ($fetchLangStmt->fetch()) {
                            $existing_languages[] = $language;
                        }
                        $fetchLangStmt->close();
                    }

                    // Determine languages to add and remove
                    $languages_to_add = array_diff($submitted_languages, $existing_languages);
                    $languages_to_remove = array_diff($existing_languages, $submitted_languages);

                    // Remove languages
                    if (!empty($languages_to_remove)) {
                        $removeLangStmt = $MySQL->getConnection()->prepare("DELETE FROM worker_languages WHERE user_guid = ? AND language = ?");
                        foreach ($languages_to_remove as $lang) {
                            $removeLangStmt->bind_param("is", $user_guid, $lang);
                            $removeLangStmt->execute();
                        }
                        $removeLangStmt->close();
                    }

                    // Add new languages
                    if (!empty($languages_to_add)) {
                        $addLangStmt = $MySQL->getConnection()->prepare("INSERT INTO worker_languages (user_guid, language) VALUES (?, ?)");
                        foreach ($languages_to_add as $lang) {
                            $lang = trim($lang);
                            $addLangStmt->bind_param("is", $user_guid, $lang);
                            $addLangStmt->execute();
                        }
                        $addLangStmt->close();
                    }

                    // Update user's group based on drivers_license
                    // Fetch current drivers_license
                    $current_drivers_license = null;
                    $fetchLicenseStmt = $MySQL->getConnection()->prepare("SELECT drivers_license FROM workers WHERE user_guid = ?");
                    if ($fetchLicenseStmt) {
                        $fetchLicenseStmt->bind_param("i", $user_guid);
                        $fetchLicenseStmt->execute();
                        $fetchLicenseStmt->bind_result($current_drivers_license);
                        $fetchLicenseStmt->fetch();
                        $fetchLicenseStmt->close();
                    }

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
                    if($group != 'admins' && $group != 'site_managers') {
                        if (!empty($current_drivers_license)) {
                            // Set group to 'drivers'
                            $updateUserGroupStmt = $MySQL->getConnection()->prepare("UPDATE users SET `group` = 'drivers' WHERE user_guid = ?");
                            if ($updateUserGroupStmt) {
                                $updateUserGroupStmt->bind_param("i", $user_guid);
                                $updateUserGroupStmt->execute();
                                $updateUserGroupStmt->close();
                            }
                        } else {
                            // Set group to 'workers'
                            $updateUserGroupStmt = $MySQL->getConnection()->prepare("UPDATE users SET `group` = 'workers' WHERE user_guid = ?");
                            if ($updateUserGroupStmt) {
                                $updateUserGroupStmt->bind_param("i", $user_guid);
                                $updateUserGroupStmt->execute();
                                $updateUserGroupStmt->close();
                            }
                        }
                    }

                    $details =
                        "[andromeda: ".$updated_andromeda_guid."]-
                         [workerID: ".$updated_workerID."]-
                         [profession: ".$updated_profession."]-
                         [rate: ".$updated_hourly_rate."]-
                         [account: ".$updated_account."]-
                         [phone: ".$updated_foreign_phone."]-
                         [height: ".$updated_height_training."]-
                         [house: ".$updated_house_guid."]-
                         [insurance: ".$updated_health_insurance."]-
                         [license: ".$updated_drivers_license."]-
                         [description: ".$updated_description."]-
                         [relief: ".$updated_on_relief."]-
                         [relief_date: ".$updated_relief_end_date."]";

                    // Commit transaction
                    $MySQL->getConnection()->commit();
                    //logAction($_SESSION['loggedIn']['user_guid'], $user_guid, 'Worker details changed', 'New details: '.$details );
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['worker_update_success'] ?? 'Worker updated successfully.') . "', true);</script>";
                } else {
                    // Rollback transaction on error
                    $MySQL->getConnection()->rollback();

                    // Check for duplicate entry error (MySQL error code 1062)
                    if ($updateStmt->errno == 1062) {
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found. Please check the unique fields.') . "', true);</script>";
                    } else {
                        // Log the error for debugging purposes
                        error_log("Error updating worker: " . $updateStmt->error);
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['update_error'] ?? 'Error updating worker.') . "', true);</script>";
                    }

                    $updateStmt->close();
                }
            } else {
                // Rollback transaction if prepare failed
                $MySQL->getConnection()->rollback();

                // Log the error
                error_log("Prepare failed for updating worker: " . $MySQL->getConnection()->error);
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
            }
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['csrf_error'] ?? 'Invalid CSRF token.') . "', true);</script>";
        }
    }
} catch (mysqli_sql_exception $e) {
    // Rollback transaction on exception
    if ($MySQL->getConnection()->in_transaction) {
        $MySQL->getConnection()->rollback();
    }

    // Handle duplicate entry error
    if ($e->getCode() == 1062) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found.') . "', true);</script>";
    } else {
        // Log the error and show a general error message
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

// Fetch worker details from workers table
$worker_stmt = $MySQL->getConnection()->prepare("SELECT 
    andromeda_guid, 
    worker_id, 
    profession, 
    hourly_rate, 
    account, 
    foreign_phone, 
    height_training, 
    house_guid, 
    health_insurance, 
    drivers_license, 
    description, 
    on_relief, 
    relief_end_date 
    FROM workers WHERE user_guid = ?");
if ($worker_stmt) {
    $worker_stmt->bind_param("i", $user_guid);
    $worker_stmt->execute();
    $worker_stmt->bind_result(
        $andromeda_guid,
        $workerid,
        $profession,
        $hourly_rate,
        $account,
        $foreign_phone,
        $height_training,
        $house_guid,
        $health_insurance,
        $drivers_license,
        $description,
        $on_relief,
        $relief_end_date
    );
    if ($worker_stmt->fetch()) {
        // Worker exists, proceed
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['worker_not_found'] ?? 'Worker not found.') . "', true);</script>";
        $worker_stmt->close();
        exit();
    }
    $worker_stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

// Fetch phone_number from users table
$user_stmt = $MySQL->getConnection()->prepare("SELECT phone_number FROM users WHERE user_guid = ?");
$phone_number = '';
if ($user_stmt) {
    $user_stmt->bind_param("i", $user_guid);
    $user_stmt->execute();
    $user_stmt->bind_result($phone_number);
    if ($user_stmt->fetch()) {
        // Retrieved phone_number
    }
    $user_stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

// Fetch worker's current languages
$current_languages = [];
$lang_stmt = $MySQL->getConnection()->prepare("SELECT language FROM worker_languages WHERE user_guid = ?");
if ($lang_stmt) {
    $lang_stmt->bind_param("i", $user_guid);
    $lang_stmt->execute();
    $lang_stmt->bind_result($language);
    while ($lang_stmt->fetch()) {
        $current_languages[] = $language;
    }
    $lang_stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

// Fetch car details if the worker is a driver
$carDetails = null;
$car_stmt = $MySQL->getConnection()->prepare("SELECT car_guid, car_model, car_number_plate FROM cars WHERE driver_guid = ?");
if ($car_stmt) {
    $car_stmt->bind_param("i", $user_guid);
    $car_stmt->execute();
    $car_stmt->bind_result($car_guid, $car_model, $car_number_plate);
    if ($car_stmt->fetch()) {
        $carDetails = [
            'car_guid' => $car_guid,
            'car_model' => $car_model,
            'car_number_plate' => $car_number_plate
        ];
    }
    $car_stmt->close();
}

// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch all houses to populate the house_guid dropdown
$houses = [];
$house_stmt = $MySQL->getConnection()->prepare("SELECT house_guid, house_address FROM houses ORDER BY house_guid ASC");
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

// Display the edit worker form
$margin_dir = in_array($selectedLanguage, ["Hebrew", "Arabic"]) ? "right" : "left"; // Adjust if needed
$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=workers';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";
echo '
<div class="page">
    <h2 style="margin-top: 2px; margin-bottom: -5px;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["edit_worker"] ?? "Edit Worker") . ' #' . htmlspecialchars($workerid) . '
    </h2>
    <div class="edit-user-page" style="padding-top: 55px;">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <input type="hidden" name="workerid" value="' . htmlspecialchars($workerid) . '" required readonly>
            <table>
                <tbody>
                    <tr>
                        <td>
                            <label for="andromeda_guid">' . htmlspecialchars($lang_data[$selectedLanguage]["andromeda_guid"] ?? "Andromeda GUID") . '</label>
                            <input style="width: 84.5%;" type="text" id="andromeda_guid" name="andromeda_guid" value="' . htmlspecialchars($andromeda_guid) . '" required>
                        </td>
                        <td>
                            <label for="profession">' . htmlspecialchars($lang_data[$selectedLanguage]["profession"] ?? "Profession") . '</label>
                            <input type="text" id="profession" name="profession" value="' . htmlspecialchars($profession) . '" required>
                        </td>
                        <td>
                            <label for="hourly_rate">' . htmlspecialchars($lang_data[$selectedLanguage]["hourly_rate"] ?? "Hourly Rate") . '</label>
                            <input type="number" step="0.01" id="hourly_rate" name="hourly_rate" value="' . htmlspecialchars($hourly_rate) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="height_training">' . htmlspecialchars($lang_data[$selectedLanguage]["height_training"] ?? "Height Trained") . '</label>
                            <select id="height_training" name="height_training" required>
                                <option value="1" ' . ($height_training == '1' ? 'selected' : '') . '>' . htmlspecialchars($lang_data[$selectedLanguage]['yes'] ?? 'Yes') . '</option>
                                <option value="0" ' . ($height_training == '0' ? 'selected' : '') . '>' . htmlspecialchars($lang_data[$selectedLanguage]['no'] ?? 'No') . '</option>
                            </select>
                        </td>
                        <td>
                            <label for="account">' . htmlspecialchars($lang_data[$selectedLanguage]["account"] ?? "Account") . '</label>
                            <input type="text" id="account" name="account" value="' . htmlspecialchars($account) . '" required>
                        </td>
                        <td>                            
                            <label for="foreign_phone">' . htmlspecialchars($lang_data[$selectedLanguage]["foreign_phone"] ?? "Foreign Phone") . '</label>
                            <div class="phone-input-wrapper">
                                <input type="tel" id="foreign_phone" name="foreign_phone" value="' . htmlspecialchars($foreign_phone) . '">
                                <a href="https://wa.me/'.$foreign_phone.'" class="whatsapp-icon" id="sendWhatsApp">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp">
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="house_guid">' . htmlspecialchars($lang_data[$selectedLanguage]["house_guid"] ?? "House") . '</label>
                            <select id="house_guid" name="house_guid" required>
                                <option disabled selected value="">' . htmlspecialchars($lang_data[$selectedLanguage]["select_house"] ?? "Select a house") . '</option>';
foreach ($houses as $house) {
    $houseGuid = htmlspecialchars($house['house_guid']);
    $houseAddress = htmlspecialchars($house['house_address']);
    $selected = ($houseGuid == $house_guid) ? 'selected' : '';
    echo '<option value="' . $houseGuid . '" ' . $selected . '>' . $houseAddress . '</option>';
}
$role = strtolower(getGroup($user_guid));
echo '
                            </select>
                        </td>
                        <td>
                            <label for="health_insurance">' . htmlspecialchars($lang_data[$selectedLanguage]["health_insurance"] ?? "Health Insurance") . '</label>
                            <input type="date" id="health_insurance" name="health_insurance" value="' . htmlspecialchars($health_insurance) . '" required>
                        </td>
                        <td>
                            <label for="phone_number">' . htmlspecialchars($lang_data[$selectedLanguage]["phone_number"] ?? "Phone Number") . '</label>
                            <div class="phone-input-wrapper">
                                <input type="tel" id="phone_number" name="phone_number" value="' . htmlspecialchars($phone_number) . '" required>
                                <a href="https://wa.me/+972'.$phone_number.'" class="whatsapp-icon" id="sendWhatsApp">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp">
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr>    
                        <td>
                            <label for="on_relief">' . htmlspecialchars($lang_data[$selectedLanguage]["on_relief"] ?? "On Relief") . '</label>
                            <select id="on_relief" name="on_relief" required onchange="handleReliefChange()">
                                <option value="1" ' . ($on_relief == '1' ? 'selected' : '') . '>' . htmlspecialchars($lang_data[$selectedLanguage]['yes'] ?? 'Yes') . '</option>
                                <option value="0" ' . ($on_relief == '0' ? 'selected' : '') . '>' . htmlspecialchars($lang_data[$selectedLanguage]['no'] ?? 'No') . '</option>
                            </select>
                            <input type="date" id="relief_end_date" name="relief_end_date" value="' . htmlspecialchars($relief_end_date) . '" style="display:none;" onchange="checkDate()" />
                        </td>
                        <td>
                            <label for="drivers_license">' . htmlspecialchars($lang_data[$selectedLanguage]["driver_license"] ?? "Driver License Date") . '</label>
                            <input type="date" id="drivers_license" name="drivers_license" value="' . htmlspecialchars($drivers_license) . '">
                        </td>
                        <td>
                            <label for="description">' . htmlspecialchars($lang_data[$selectedLanguage]["description"] ?? "Description") . '</label>
                            <input type="text" id="description" name="description" value="' . htmlspecialchars($description) . '">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <label>' . htmlspecialchars($lang_data[$selectedLanguage]["spoken_languages"] ?? "Spoken Languages") . '</label>
                            <div class="languages-container">
                                <label><input type="checkbox" name="languages[]" value="Hebrew" ' . (in_array("Hebrew", $current_languages) ? 'checked' : '') . '> ' . htmlspecialchars($lang_data[$selectedLanguage]["hebrew"] ?? "Hebrew") . '</label>
                                <label><input type="checkbox" name="languages[]" value="English" ' . (in_array("English", $current_languages) ? 'checked' : '') . '> ' . htmlspecialchars($lang_data[$selectedLanguage]["english"] ?? "English") . '</label>
                                <label><input type="checkbox" name="languages[]" value="Russian" ' . (in_array("Russian", $current_languages) ? 'checked' : '') . '> ' . htmlspecialchars($lang_data[$selectedLanguage]["russian"] ?? "Russian") . '</label>
                                <label><input type="checkbox" name="languages[]" value="Hindi" ' . (in_array("Hindi", $current_languages) ? 'checked' : '') . '> ' . htmlspecialchars($lang_data[$selectedLanguage]["hindi"] ?? "Hindi") . '</label>
                                <label><input type="checkbox" name="languages[]" value="Sinhala" ' . (in_array("Sinhala", $current_languages) ? 'checked' : '') . '> ' . htmlspecialchars($lang_data[$selectedLanguage]["sinhala"] ?? "Sinhala") . '</label>
                                <label><input type="checkbox" name="languages[]" value="Arabic" ' . (in_array("Arabic", $current_languages) ? 'checked' : '') . '> ' . htmlspecialchars($lang_data[$selectedLanguage]["arabic"] ?? "Arabic") . '</label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 70vw; margin-top: 20px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["update"] ?? "Update") . '</button>
            
            <button style="width: 20vw; margin-top: 20px;" onclick=\'location.href= "index.php?lang=' . $selectedLanguage . '&page=admin&sub_page=workers&action=assign&user_guid='. $user_guid .'"\' type="button" id="btn-update">
                ' . htmlspecialchars($lang_data[$selectedLanguage]['assign'] ?? 'Assign') . '
            </button>
        </form>
    </div>
    <div class="user-list" style="margin-top: 0; height: 100px;">
        <table style="margin: 0 2.5vw 0 2.5vw; width: 95vw">
            <thead>
                <tr>
                    <th style="font-weight: bolder; width: 50%;">' . htmlspecialchars($lang_data[$selectedLanguage]["first_name"] ?? 'First name') . '</th>
                    <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]["passport_id"] ?? 'Passport/ID') . '</th>
                    <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]["role"] ?? 'Role') . '</th>
                    <th style="font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? 'Actions') . '</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>' . getWorkerName($user_guid) . '</td>
                    <td>' . getPassportID($user_guid) . '</td>
                    <td>' . htmlspecialchars($lang_data[$selectedLanguage]["_{$role}"] ?? getGroup($user_guid)) . '</td>
                    <td style="white-space: nowrap; text-decoration: none; text-align: '.($margin_dir=='left'?'right':'left').';">
                    
                        <a style="text-decoration: none; color: black;" href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=users&action=edit&user_guid=' . urlencode($user_guid) . '">
                            <img class="manage_shift_btn" src="img/edit.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . '">
                        </a>
                        <div style="text-decoration: none; display: inline-flex;" class="manage_shift_btn"></div>
                    </td>
                </tr>
            </tbody>';

// Show the currently assigned work, if any
$isAssignedToWork = isAssignedToWork($user_guid);
if ($isAssignedToWork != 0) {
    $assignment_guid = getAssignmentForWorker($user_guid);
    echo '
            <thead>
                <tr>
                    <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]['site_name'] ?? 'Site') . '</th>
                    <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]['assignment'] ?? 'Assignment') . '</th>
                    <th style="font-weight: bolder;"></th>
                    <th style="font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]['actions'] ?? 'Actions') . '</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <a style="color: black; text-decoration: underline;" href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=sites&action=edit&site_guid=' . urlencode($isAssignedToWork) . '">
                            ' . getSiteName($isAssignedToWork) . '
                        </a>
                    </td>
                    <td>
                        <a style="color: black; text-decoration: underline;" href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=assignments&action=edit&assignment_guid=' . urlencode($assignment_guid) . '">
                            ' . getAssignmentDescription($assignment_guid) . '
                        </a>
                    </td>
                    <td></td>
                    <td style="text-align: '.($margin_dir=='left'?'right':'left').'; white-space: nowrap; width: 1%; max-width: max-content;">
                        <a style="color: black; text-decoration: underline;" href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=assignments&action=unassign&assignment_guid=' . urlencode($assignment_guid) . '&user_guid=' . urlencode($user_guid) . '">
                            <img class="manage_shift_btn" src="img/unassign.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['unassign'] ?? 'Unassign') . '">
                        </a>
                    </td>
                </tr>
            </tbody>';
} else {
    echo '<tr><td colspan="4" style="height: 22px;"><h3 style="margin: 0;">' . htmlspecialchars($lang_data[$selectedLanguage]["worker_not_assigned"] ?? "Worker not Assigned") . '</h3></td></tr>';
}

// Display car details if the worker is a driver
if ($carDetails) {
    echo '
            <thead>
                <tr>
                    <th style="font-weight: bolder; width: 50%;">' . htmlspecialchars($lang_data[$selectedLanguage]["car_model"] ?? 'Car Model') . '</th>
                    <th style="font-weight: bolder;"></th>
                    <th style="font-weight: bolder; white-space: nowrap;">' . htmlspecialchars($lang_data[$selectedLanguage]["number_plate"] ?? 'Number Plate') . '</th>
                    <th style="font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? 'Actions') . '</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="height: 25px;">' . htmlspecialchars($carDetails['car_model']) . '</td>
                    <td style="height: 25px; white-space: nowrap;"></td>
                    <td style="height: 25px; letter-spacing: 1px;">' . htmlspecialchars($carDetails['car_number_plate']) . '</td>
                    <td style="height: 25px; text-align: '.($margin_dir=='left'?'right':'left').'; white-space: nowrap;">
                        <a style="color: black; href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=workers&action=assign&to=car&user_guid=' . urlencode($user_guid) . '">
                            <img class="manage_shift_btn" src="img/edit.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . '">
                        </a>
                        <a style="color: black; href="index.php?lang=' . urlencode($_GET['lang']) . '&page=admin&sub_page=cars&action=unassign_driver&car_guid=' . urlencode($carDetails['car_guid']) . '">
                            <img class="manage_shift_btn" src="img/unassign.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['unassign'] ?? 'Unassign') . '">
                        </a>
                    </td>
                </tr>
            </tbody>';
}

echo '</table>
    </div>
</div>
    <script>
        // Handle the display of relief_end_date based on on_relief selection

        // Initialize the relief_end_date display based on the current selection
        document.addEventListener("DOMContentLoaded", function() {
            handleReliefChange();
        });
    </script>';
?>

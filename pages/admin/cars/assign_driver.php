<?php
// pages/admin/cars/assign_driver.php

// Include necessary configurations and handlers
global $lang_data, $selectedLanguage, $MySQL;
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php'; // Ensure language handler is included

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

// Validate and sanitize the car_guid from GET
if (isset($_GET['car_guid'])) {
    $car_guid = intval($_GET['car_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Fetch the current driver assigned to this car, if any
    $stmt_current_driver = $MySQL->getConnection()->prepare("
        SELECT u.user_guid, u.first_name, u.last_name 
        FROM users u 
        JOIN cars c ON c.driver_guid = u.user_guid 
        WHERE c.car_guid = ?
    ");
    $stmt_current_driver->bind_param("i", $car_guid);
    $stmt_current_driver->execute();
    $stmt_current_driver->bind_result($current_driver_guid, $current_driver_first_name, $current_driver_last_name);
    $current_driver_assigned = $stmt_current_driver->fetch();
    $stmt_current_driver->close();

    // Fetch available drivers
    $sql_available_drivers = "
            SELECT u.*
            FROM users u
            LEFT JOIN workers w ON w.user_guid = u.user_guid
                AND u.group = 'drivers'  -- Only join workers if group is 'drivers'
            WHERE u.group IN ('admins','drivers')
                AND (u.group != 'drivers' OR w.drivers_license > CURDATE())  -- Check license only for drivers
                AND u.user_guid NOT IN (
                    SELECT c.driver_guid
                    FROM cars c
                    WHERE c.driver_guid IS NOT NULL
                )
            ";
    $stmt_available_drivers = $MySQL->getConnection()->prepare($sql_available_drivers);
    $stmt_available_drivers->execute();
    $available_drivers = $stmt_available_drivers->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_available_drivers->close();

} catch (mysqli_sql_exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
}

// Generate CSRF token for form submission
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_driver'])) {
    $selected_driver = intval($_POST['selected_driver']);
    try {
        // Assign the new driver to the car
        $sql_insert = "UPDATE cars SET driver_guid = ? WHERE car_guid = ?";
        $stmt_insert = $MySQL->getConnection()->prepare($sql_insert);
        $stmt_insert->bind_param("ii", $selected_driver, $car_guid);
        $stmt_insert->execute();
        $stmt_insert->close();

        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['driver_assigned_success'] ?? 'Driver assigned successfully.') . "', true);</script>";
    } catch (mysqli_sql_exception $e) {
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}
$car_details = getCarDetails($car_guid);
$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";
// Display the form and current driver
echo '
<div class="page">
    <h2 style="margin: 10px;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["assign_driver"] ?? "Assign Driver to Car") . ' ' . $car_details[0]['car_model'] .'
    </h2>
    <div class="edit-user-page">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';

    // Show available drivers for selection
    echo '
            <div class="available-drivers-section">
                <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["available_drivers"] ?? "Available Drivers") . '</h3>
                <label for="selected_driver">' . htmlspecialchars($lang_data[$selectedLanguage]["select_driver"] ?? "Select Driver") . '</label>
                <select style="width: 92vw;" id="selected_driver" name="selected_driver" required>';

    if (count($available_drivers) > 0) {
        echo '<option disabled selected value="">' . htmlspecialchars($lang_data[$selectedLanguage]["select_driver"] ?? "Select a driver") . '</option>';
        foreach ($available_drivers as $driver) {
            echo '<option value="' . htmlspecialchars($driver['user_guid']) . '">' . htmlspecialchars($driver['first_name'] . " " . $driver['last_name']) . '</option>';
        }
    } else {
        echo '<option disabled>' . htmlspecialchars($lang_data[$selectedLanguage]['no_drivers_available'] ?? "No drivers available") . '</option>';
    }

    echo '
                </select>
            </div>
            <button style="width: 92vw; margin-top: 15px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["assign_driver"] ?? "Assign Driver") . '</button>
        </form>
        </div>';

    // Show the currently assigned driver, if any
    if ($current_driver_guid) {
        $del_url = 'index.php?lang='.$selectedLanguage.'&page=admin&sub_page=cars&action=unassign_driver&car_guid=' . urlencode($car_guid);
        echo '
        <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["assigned_driver"] ?? "Assigned Driver") . '</h3>
        <div class="user-list" style="height: 40%;">
            <table>
                <thead>
                    <tr>
                        <th>' . htmlspecialchars($lang_data[$selectedLanguage]["name"] ?? "Name") . '</th>
                        <th style="white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? "Actions") . '</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><a style="color: black; text-decoration: underline;" href="index.php?lang=' . $selectedLanguage . '&page=admin&sub_page=workers&action=edit&user_guid=' . $current_driver_guid . '">' . htmlspecialchars($current_driver_first_name . " " . $current_driver_last_name) . '</a></td>
                        <td style="white-space: nowrap; width: 1%; max-width: max-content;"><a href="' . $del_url . '"><img class="manage_shift_btn" src="img/unassign.png" alt="Unassign"></a></td>
                    </tr>
                </tbody>
            </table>
        </div>';
    } else echo '
        <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["assigned_driver"] ?? "No driver Assigned") . '</h3>';

    echo '
</div>

';
?>

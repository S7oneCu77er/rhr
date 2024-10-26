<?php
// pages/admin/assignments/assign_car.php

// Include necessary configurations and handlers
global $selectedLanguage, $lang_data, $MySQL;
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

// Ensure the user has the admin or site_manager role
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

// Get the assignment_guid from the URL
if (isset($_GET['assignment_guid'])) {
    $assignment_guid = intval($_GET['assignment_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Fetch the current car assigned to this assignment, if any
    $stmt_current_car = $MySQL->getConnection()->prepare("
        SELECT c.car_guid, c.car_model, c.car_number_plate, u.first_name, u.last_name, u.user_guid, w.worker_id
        FROM cars c 
        LEFT JOIN users u ON c.driver_guid = u.user_guid 
        LEFT JOIN workers w ON w.user_guid = c.driver_guid 
        WHERE c.assignment_guid = ?
    ");
    $stmt_current_car->bind_param("i", $assignment_guid);
    $stmt_current_car->execute();
    $stmt_current_car->bind_result($current_car_guid, $current_car_model, $current_car_number_plate, $current_driver_first_name, $current_driver_last_name, $userGuid, $worker_id);
    $current_car_assigned = $stmt_current_car->fetch();
    $stmt_current_car->close();

    // Fetch available cars (cars not already assigned to another assignment)
    $sql_available_cars = "
        SELECT c.car_guid, c.car_model, c.car_number_plate, u.first_name, u.last_name, u.user_guid 
        FROM cars c 
        LEFT JOIN users u ON c.driver_guid = u.user_guid 
        WHERE (c.assignment_guid = 0)
        AND c.car_guid != 0
        ORDER BY c.car_model, c.car_number_plate
    ";
    $stmt_available_cars = $MySQL->getConnection()->prepare($sql_available_cars);
    $stmt_available_cars->execute();
    $available_cars = $stmt_available_cars->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_available_cars->close();

} catch (mysqli_sql_exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
}

// Generate CSRF token for form submission
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_car'])) {
    $selected_car = intval($_POST['selected_car']);
    try {

        /* Allow several cars
        // Unassign any previous car from this assignment
        $sql_unassign = "UPDATE cars SET assignment_guid = 0 WHERE assignment_guid = ?";
        $stmt_unassign = $MySQL->getConnection()->prepare($sql_unassign);
        $stmt_unassign->bind_param("i", $assignment_guid);
        $stmt_unassign->execute();
        $stmt_unassign->close();

        */

        // Assign the selected car to the assignment
        $sql_assign = "UPDATE cars SET assignment_guid = ? WHERE car_guid = ?";
        $stmt_assign = $MySQL->getConnection()->prepare($sql_assign);
        $stmt_assign->bind_param("ii", $assignment_guid, $selected_car);
        $stmt_assign->execute();
        $stmt_assign->close();

        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_assigned_success'] ?? 'Car assigned successfully.') . "', true);</script>";
    } catch (mysqli_sql_exception $e) {
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

$margin_dir = in_array($selectedLanguage, ["Hebrew", "Arabic"]) ? "right" : "left"; // Adjust if needed
$back = 'javascript:window.history.go(-1);';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";

// Display the form and currently assigned car
echo '
<div class="page">
    <h2 style="margin: 10px;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["assign_car"] ?? "Assign Car to Assignment") . '
    </h2>
    <div style="height: 24vh;" class="edit-user-page">
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';

    // Show available cars for selection
    echo '
            <div class="available-cars-section" style="width: 92vw">
                <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["available_cars"] ?? "Available Cars") . '</h3>
                <label for="selected_car">' . htmlspecialchars($lang_data[$selectedLanguage]["select_car"] ?? "Select Car") . '</label>
                <select style="width: 92%;" id="selected_car" name="selected_car" required>';

    if (count($available_cars) > 0) {
        echo '<option disabled selected value="">' . htmlspecialchars($lang_data[$selectedLanguage]["select_car"] ?? "Select a car") . '</option>';
        foreach ($available_cars as $car) {
            $selected = ($car['car_guid'] == $current_car_guid) ? 'selected' : '';
            $driver_info = $car['first_name'] ? " (" . htmlspecialchars($car['first_name'] . " " . $car['last_name']) . ")" : " (No Driver)";
            echo '<option value="' . htmlspecialchars($car['car_guid']) . '" ' . $selected . '>' . htmlspecialchars($car['car_model'] . " - " . $car['car_number_plate'] . $driver_info) . '</option>';
        }
    } else {
        echo '<option disabled>' . htmlspecialchars($lang_data[$selectedLanguage]['no_cars_available'] ?? "No cars available") . '</option>';
    }

    echo '
                </select>
            </div>
            <button style="width: 92%; margin-top: 15px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["assign_car"] ?? "Assign Car") . '</button>
        </form>
    </div>';

    // Show the currently assigned car, if any
    if ($current_car_assigned) {
        if($userGuid)
            $driver = '<a style="text-decoration: underline; color: black;" href="index.php?lang=' . $selectedLanguage . '&page=admin&sub_page=workers&action=edit&user_guid=' . $userGuid . '">#'.$worker_id . ' - ' . htmlspecialchars($current_driver_first_name . " " . $current_driver_last_name) . '</a>';
        else
            $driver = '<a style="text-decoration: underline; color: black;" href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars&action=assign_driver&car_guid=' . $current_car_guid . '">No driver assigned</a>';
        echo '
    <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["assigned_car"] ?? "Assigned Car") . '</h3>
    <div class="user-list" style="height: 17%;">
        <table style="width: 92%;">
            <thead>
                <tr>
                    <th>Car</th>
                    <th>Driver</th>
                    <th style="white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? "Actions") . '</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><a style="text-decoration: underline; color: black;" href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars&action=edit&car_guid=' . $current_car_guid . '">' . $current_car_model . ' (' . $current_car_number_plate . ')</a></td>
                    <td>' . $driver . '</td>
                    <td style="white-space: nowrap; width: 1%; max-width: max-content;">
                        <a href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars&action=assign_driver&car_guid=' . $current_car_guid . '"><img class="manage_shift_btn" src="img/driver.png" alt="driver"></a>
                        <a href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=assignments&action=unassign_car&car_guid=' . $current_car_guid . '"><img class="manage_shift_btn" src="img/unassign.png" alt="Unassign"></a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>';
    }

echo '
</div>

';
?>

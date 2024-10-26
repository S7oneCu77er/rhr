<?php
// pages/admin/cars/delete.php

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

// Fetch car details
$stmt_car = $MySQL->getConnection()->prepare("SELECT car_model, car_number_plate FROM cars WHERE car_guid = ?");
$stmt_car->bind_param("i", $car_guid);
$stmt_car->execute();
$stmt_car->bind_result($car_model, $car_number_plate);
if (!$stmt_car->fetch()) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_not_found'] ?? 'Car not found.') . "', true);</script>";
    $stmt_car->close();
    exit();
}
$stmt_car->close();

// Confirmation step
if (!isset($_GET['confirm']) || $_GET['confirm'] != 'true') {
    $url = 'index.php?lang=' . urlencode($selectedLanguage);
    foreach ($_GET as $key => $value) {
        if ($key == 'lang') continue;
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    $url .= '&confirm=true';

    echo "
    <div class='page'>
        <div style='margin-top: 50px; font-size: 1.15rem; color:red; font-weight: 500;
        text-shadow: 0.5px 0 currentColor, -0.5px 0 currentColor, 0 0.5px currentColor, 0 -0.5px currentColor;'>
            " . htmlspecialchars($lang_data[$selectedLanguage]['car_delete_confirm'] ?? 'Are you sure you want to delete this car?') . "
        </div>
        <br>
        <h2 style='margin-top: 100px;'>" . htmlspecialchars($lang_data[$selectedLanguage]['car_model'] ?? 'Car Model') . ": " . htmlspecialchars($car_model) . " | " . htmlspecialchars($lang_data[$selectedLanguage]['car_number_plate'] ?? 'Number Plate') . ": " . htmlspecialchars($car_number_plate) . "</h2>
    </div>
    <div class='hours_page' style='margin-bottom: 20px;'>
        <div id='shift_start'>
            <button onclick=\"location.href='{$url}'\">" . htmlspecialchars($lang_data[$selectedLanguage]['yes_proceed'] ?? 'YES - PROCEED') . "</button>
        </div>
        <div id='shift_end'>
            <button onclick='location.href=\"index.php?lang={$selectedLanguage}&page=admin&sub_page=cars\"'>" . htmlspecialchars($lang_data[$selectedLanguage]['no_go_back'] ?? 'NO - GO BACK') . "</button>
        </div>
    </div>";
} else if (isset($_GET['confirm']) && $_GET['confirm'] == 'true') {
    // Attempt to delete the car
    try {
        $stmt_delete = $MySQL->getConnection()->prepare("DELETE FROM cars WHERE car_guid = ? LIMIT 1");
        $stmt_delete->bind_param("i", $car_guid);
        if ($stmt_delete->execute()) {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_delete_success'] ?? 'Car deleted successfully.') . "', true);</script>";
            header("Location: index.php?lang=" . urlencode($selectedLanguage) . "&page=admin&sub_page=cars");
            exit();
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_delete_error'] ?? 'Error deleting car.') . "', true);</script>";
        }
        $stmt_delete->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Error deleting car: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
} else {
    header("Location: index.php");
}
?>

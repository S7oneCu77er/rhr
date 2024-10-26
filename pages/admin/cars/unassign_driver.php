<?php
// pages/admin/cars/unassign_driver.php

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

echo "<div class='page'></div>";

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Remove the driver assignment for the car
    $sql_remove = "UPDATE cars SET driver_guid = 0 WHERE car_guid = ?";
    $stmt_remove = $MySQL->getConnection()->prepare($sql_remove);
    $stmt_remove->bind_param("i", $car_guid);
    $stmt_remove->execute();
    $stmt_remove->close();

    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['driver_unassigned_success'] ?? 'Driver unassigned successfully.') . "', true);</script>";

} catch (mysqli_sql_exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
}

// Use JavaScript to go back to the previous page
echo "<script>
    setTimeout(function() {
        window.history.back();
    }, 1500); // Optional delay to show the success message
</script>";
?>

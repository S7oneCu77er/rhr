<?php
// pages/admin/sites/unassign_manager.php

// Include necessary configurations and handlers
global $lang_data, $selectedLanguage, $MySQL;

require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php'; // Ensure language handler is included

echo '<div class="page"></div>';

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
if (isset($_GET['site_guid']) && isset($_GET['user_guid'])) {
    $site_guid = intval($_GET['site_guid']);
    $user_guid = intval($_GET['user_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    return;
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Remove the driver assignment for the car
    $sql_remove = "DELETE FROM site_managers WHERE user_guid = ? AND site_guid = ?";
    $stmt_remove = $MySQL->getConnection()->prepare($sql_remove);
    $stmt_remove->bind_param("ii", $user_guid, $site_guid);
    $stmt_remove->execute();
    $stmt_remove->close();

    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['manager_unassigned_success'] ?? 'Manager unassigned successfully.') . "');
    setTimeout(function() {
            window.history.back();
        }, 1500); // Optional delay to show the success message</script>";

} catch (mysqli_sql_exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
}

?>

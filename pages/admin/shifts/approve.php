<?php
// pages/admin/assignments/unassign_car.php

// Include necessary configurations and handlers
global $lang_data, $selectedLanguage, $MySQL;
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    header("Location: index.php");
    exit();
}

// Validate and sanitize the shift_guid from GET
if (isset($_GET['shift_guid']) && isset($_GET['user_guid'])) {
    $shift_guid = intval($_GET['shift_guid']);
    $user_guid = intval($_GET['user_guid']);

    if($_GET['shift_guid'] == 'all')
    {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        // Unassign the car by setting assignment_guid = 0
        $stmt = $MySQL->getConnection()->prepare("
            UPDATE shifts 
            SET status = 'approved' 
            WHERE user_guid = ? 
            AND YEAR(shift_start) = ? 
            AND MONTH(shift_start) = ?");
        if ($stmt) {
            $stmt->bind_param("iii", $user_guid, $year, $month);
            $stmt->execute();
            $stmt->close();
        }
    }
    else
    {
        // Unassign the car by setting assignment_guid = 0
        $stmt = $MySQL->getConnection()->prepare("UPDATE shifts SET status = 'approved' WHERE shift_guid = ? AND user_guid = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $shift_guid, $user_guid);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Redirect back to the previous page
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    } else {
        echo '<script>window.history.back();</script>';
        exit();
    }

} else {
    // If no shift_guid or user_guid is provided, redirect to back 1 page in history
    echo "<div class='page'><script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['missing_guid'] ?? 'Missing GUID') . "', true);</script></div>";
    return;
}
?>
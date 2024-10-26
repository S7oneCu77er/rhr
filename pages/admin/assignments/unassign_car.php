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
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    header("Location: index.php");
    exit();
}

// Validate and sanitize the car_guid from GET
if (isset($_GET['car_guid'])) {
    $car_guid = intval($_GET['car_guid']);

    // Unassign the car by setting assignment_guid = 0
    $stmt = $MySQL->getConnection()->prepare("UPDATE cars SET assignment_guid = 0 WHERE car_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $car_guid);
        $stmt->execute();
        $stmt->close();
    }

    // Redirect back to the previous page
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    } else {
        header("Location: index.php?lang=" . urlencode($selectedLanguage) . "&page=admin&sub_page=assignments");
        exit();
    }

} else {
    // If no car_guid is provided, redirect to assignments page
    header("Location: index.php?lang=" . urlencode($selectedLanguage) . "&page=admin&sub_page=assignments");
    exit();
}
?>
<?php
// pages/admin/assignments/unassign.php

// Include necessary configurations and handlers
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php'; // Ensure language handler is included

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $lang_data, $selectedLanguage, $MySQL;

// Ensure the user is logged in
if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin or site_manager role
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

// Validate and sanitize user_guid and assignment_guid from GET
if (isset($_GET['user_guid']) && isset($_GET['assignment_guid'])) {
    $user_guid = intval($_GET['user_guid']);
    $assignment_guid = intval($_GET['assignment_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

// Execute unassignment without confirmation or message
unassignWorker($user_guid, $assignment_guid);

// Redirect back to the previous page
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>
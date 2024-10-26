<?php
// pages/admin/assignments/delete.php

// Include necessary configurations and handlers
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php'; // Ensure language handler is included

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $lang_data, $selectedLanguage, $MySQL;

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

// Validate and sanitize the assignment_guid from GET
if (isset($_GET['assignment_guid'])) {
    $assignment_guid = intval($_GET['assignment_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

if (!isset($_GET['confirm']) || $_GET['confirm'] != 'true') {
    $url = 'index.php?lang=' . urlencode($selectedLanguage);
    foreach ($_GET as $key => $value) {
        if ($key == 'lang') continue;
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    $url .= '&confirm=true';

    echo '
    <div class="page">
        <div style="margin-top: 50px; font-size: 1.15rem; color:red; font-weight: 500;
        text-shadow: 0.5px 0 currentColor, -0.5px 0 currentColor, 0 0.5px currentColor, 0 -0.5px currentColor;">
            ' . htmlspecialchars($lang_data[$selectedLanguage]['confirm_delete_assignment'] ?? 'Are you sure you want to delete this assignment?') . '
        </div>
        <br>
        <h2 style="margin-top: 100px;">' . getAssignmentInformation($assignment_guid) . '</h2>
    </div>
    <div class="hours_page" style="margin-bottom: 20px;">
        <div id="shift_start">
            <button onclick="location.href=\'' . $url . '\'">' . htmlspecialchars($lang_data[$selectedLanguage]['yes_proceed'] ?? 'YES - PROCEED') . '</button>
        </div>
        <div id="shift_end">
            <button onclick="window.history.go(-1)">' . htmlspecialchars($lang_data[$selectedLanguage]['no_go_back'] ?? 'NO - GO BACK') . '</button>
        </div>
    </div>';
} else if (isset($_GET['confirm']) && $_GET['confirm'] == 'true') {
    $url = 'index.php?lang=' . urlencode($selectedLanguage);
    $excluded_keys = ['lang', 'action', 'assignment_guid', 'confirm'];
    foreach ($_GET as $key => $value) {
        if (in_array($key, $excluded_keys)) {
            continue;
        }

        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    // Attempt to delete the assignment
    if (deleteAssignment($assignment_guid)) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['assignment_delete_success'] ?? 'Assignment deleted successfully.') . "', true);</script>";
        header("Location: {$url}");
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['assignment_delete_error'] ?? 'Error deleting assignment.') . "', true);</script>";
        header("Location: {$url}");
    }
} else {
    header("Location: index.php");
}
?>

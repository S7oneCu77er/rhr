<?php
// pages/admin/users/delete.php

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

// Validate and sanitize the worker_id from GET
if (isset($_GET['user_guid'])) {
    $user_guid = intval($_GET['user_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

// Prevent admins from deleting themselves
if ($_SESSION['loggedIn']['user_guid'] == $user_guid) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['self_delete_error'] ?? 'You cannot delete your own account.') . "', true);</script>";
    exit();
}

if(!isset($_GET['confirm']) || $_GET['confirm'] != 'true') {
    $url  = 'index.php?lang=' . urlencode($selectedLanguage);
    foreach($_GET as $key => $value) {
        if($key == 'lang') continue;
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    $url .= '&confirm=true';
    $user_url = preg_replace('/(sub_page=)[^&]+/', 'sub_page=workers', $url);
    $user_url = preg_replace('/(action=)[^&]+/', 'action=edit', $user_url);
    $user_url .= '&user_guid=' . urlencode($user_guid);
    echo '
    <div class="page">
        <div style="margin-top: 50px; font-size: 1.15rem; color:red; font-weight: 500;
        text-shadow: 0.5px 0 currentColor, -0.5px 0 currentColor, 0 0.5px currentColor, 0 -0.5px currentColor;">
            ' . htmlspecialchars($lang_data[$selectedLanguage]['confirm_delete_user'] ?? 'Are you sure you want to delete this user?') . '<br>['.htmlspecialchars($lang_data[$selectedLanguage]['linked_to_worker'] ?? 'linked to worker').' <a href="'.$user_url.'">#' . getWorkerID($user_guid) . '</a>]
        </div>
        <br>
        <h2 style="margin-top: 100px;">' . getUserName($user_guid) . '</h2>
    </div>
    <div class="hours_page" style="margin-bottom: 20px;">
        <div id="shift_start">
            <button onclick="location.href=\''.$url.'\'">' . htmlspecialchars($lang_data[$selectedLanguage]['yes_proceed'] ?? 'YES - PROCEED') . '</button>
        </div>
        <div id="shift_end">
            <button onclick="location.href=\'index.php?lang='.$selectedLanguage.'&page=admin&sub_page=users\'">' . htmlspecialchars($lang_data[$selectedLanguage]['no_go_back'] ?? 'NO - GO BACK') . '</button>
        </div>
    </div>';
}
else if(isset($_GET['confirm']) && $_GET['confirm'] == 'true')
{

    $url = 'index.php?lang=' . urlencode($selectedLanguage);
    $excluded_keys = ['lang', 'action', 'house_guid', 'confirm'];
    foreach ($_GET as $key => $value) {
        // Skip the excluded keys
        if (in_array($key, $excluded_keys)) {
            continue;
        }

        // Append the key-value pair to the URL
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    // Attempt to delete the user
    if (deleteUser($user_guid)) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['user_delete_success'] ?? 'User deleted successfully.') . "', true);</script>";
        header("Location: {$url}");
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['user_delete_error'] ?? 'Error deleting user.') . "', true);</script>";
        header("Location: {$url}");
    }
} else {
    header("Location: index.php");
}
?>

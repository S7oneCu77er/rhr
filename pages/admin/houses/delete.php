<?php
// pages/admin/houses/delete.php


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


// Validate and sanitize the house_guid from GET
if (isset($_GET['house_guid'])) {
    $house_guid = intval($_GET['house_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.', true) . "');</script>";
    exit();
}

if(!isset($_GET['confirm']) || $_GET['confirm'] != 'true') {
    $url = 'index.php?lang=' . urlencode($selectedLanguage);
    foreach($_GET as $key => $value) {
        if($key == 'lang') continue;
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    $url .= '&confirm=true';

    echo '
    <div class="page">
        <div style="margin-top: 50px; font-size: 1.15rem; color:red; font-weight: 500;
        text-shadow: 0.5px 0 currentColor, -0.5px 0 currentColor, 0 0.5px currentColor, 0 -0.5px currentColor;">
            Are you sure you want to delete this house?
        </div>
        <br>
        <h2 style="margin-top: 100px;">' . getHouseAddressDescription($house_guid) . '</h2>
    </div>
    <div class="hours_page" style="margin-bottom: 20px;">
        <div id="shift_start">
            <button onclick="location.href=\''.$url.'\'">YES - PROCEED</button>
        </div>
        <div id="shift_end">
            <button onclick="location.href=\'index.php?lang='.$selectedLanguage.'&page=admin&sub_page=houses\'">NO - GO BACK</button>
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
    if (deleteHouse($house_guid)) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['house_delete_success'] ?? 'House deleted successfully.') . "', true);</script>";
        header("Location: {$url}");
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['house_delete_error'] ?? 'Error deleting house.') . "', true;</script>";
        header("Location: {$url}");
    }
} else {
    header("Location: index.php");
}
?>

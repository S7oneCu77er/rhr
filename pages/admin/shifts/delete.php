<?php
// pages/admin/shifts/delete.php


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
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

// Validate and sanitize the shift_guid from GET
if (isset($_GET['shift_guid']) && isset($_GET['user_guid'])) {
    $shift_guid = intval($_GET['shift_guid']);
    $user_guid = intval($_GET['user_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

// Function to delete a shift
function deleteShift($shift_guid) {
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("DELETE FROM shifts WHERE shift_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $shift_guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }
    return true;
}

// Fetch shift details for confirmation
$stmt = $MySQL->getConnection()->prepare("
    SELECT s.shift_guid, s.shift_start, s.shift_end, s.status, u.first_name, u.last_name, w.worker_id 
    FROM shifts s
    JOIN users u ON s.user_guid = u.user_guid
    JOIN workers w ON u.user_guid = w.user_guid
    WHERE s.shift_guid = ?
");
$stmt->bind_param("i", $shift_guid);
$stmt->execute();
$stmt->bind_result($shift_guid, $shift_start, $shift_end, $status, $first_name, $last_name, $worker_id);
$stmt->fetch();
$stmt->close();

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
        Are you sure you want to delete this shift?</div>
        <div style="margin-top: 100px;">
            '.($lang_data[$selectedLanguage]["worker"] ?? "Worker").': #' . htmlspecialchars($worker_id) . ' - ' . htmlspecialchars($first_name . " " . $last_name) . '<br>
            '.($lang_data[$selectedLanguage]["shift_start_time"] ?? "Shift Start").': ' . htmlspecialchars($shift_start) . '<br>
            '.($lang_data[$selectedLanguage]["shift_end_time"] ?? "Shift End").': ' . htmlspecialchars($shift_end ?? 'Not Ended') . '<br>
            '.($lang_data[$selectedLanguage]["shift_status"] ?? "Shift Status").': ' . htmlspecialchars(ucfirst($status ? ($lang_data[$selectedLanguage][$status] ?? "Unknown") : 'Unknown')) . '
        </div>
    </div>
    <div class="hours_page" style="margin-bottom: 20px;">
        <div id="shift_start">
            <button onclick="location.href=\''.$url.'\'">' . htmlspecialchars($lang_data[$selectedLanguage]['yes_proceed'] ?? 'YES - PROCEED') . '</button>
        </div>
        <div id="shift_end">
            <button onclick="window.history.go(-1);">' . htmlspecialchars($lang_data[$selectedLanguage]['no_go_back'] ?? 'NO - GO BACK') . '</button>
        </div>
    </div>';

} elseif (isset($_GET['confirm']) && $_GET['confirm'] == 'true') {
    $url = 'index.php?lang=' . urlencode($selectedLanguage);
    $excluded_keys = ['lang', 'action', 'shift_guid', 'confirm'];
    foreach ($_GET as $key => $value) {
        if (in_array($key, $excluded_keys)) {
            continue;
        }
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    echo '<div class="page"></div>';
    // Attempt to delete the shift
    if (deleteShift($shift_guid)) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['shift_delete_success'] ?? 'Shift deleted successfully.') . "', true);</script>";
        header("Location: {$url}");
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['shift_delete_error'] ?? 'Error deleting shift.') . "', true);</script>";
        header("Location: {$url}");
    }
} else {
    header("Location: index.php");
}


?>

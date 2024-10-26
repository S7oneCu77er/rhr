<?php
// pages/admin/sites/add.php

// Include necessary configurations and handlers
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php'; // Ensure language handler is included

global $lang_data, $selectedLanguage, $MySQL;

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['loggedIn'])) {
    header("Location: ../index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Handle form submission for adding a new site
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            // Retrieve and sanitize form inputs
            $site_name = trim($_POST['site_name']);
            $site_address = trim($_POST['site_address']);
            $phone_number = trim($_POST['phone_number']);
            $shiftStart_time = trim($_POST['shiftStart_time']);
            $shiftEnd_time = trim($_POST['shiftEnd_time']);
            $site_owner_guid = intval($_POST['site_owner_guid']);

            // Prepare SQL statement to insert a new site
            $sql = "INSERT INTO sites (site_name, site_address, phone_number, shiftStart_time, shiftEnd_time, site_owner_guid) VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $MySQL->getConnection()->prepare($sql);

            if ($insertStmt) {
                // Bind parameters
                $insertStmt->bind_param("sssssi", $site_name, $site_address, $phone_number, $shiftStart_time, $shiftEnd_time, $site_owner_guid);

                // Execute the statement
                if ($insertStmt->execute()) {
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['add_success'] ?? 'Site added successfully.') . "', true);</script>";
                } else {
                    // Log the error for debugging purposes
                    error_log("Error adding site: " . $insertStmt->error);
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['add_error'] ?? 'Error adding site.') . "', true);</script>";
                }

                // Close the statement
                $insertStmt->close();
            } else {
                // Log the error for debugging purposes
                error_log("Prepare failed: " . $MySQL->getConnection()->error);
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
            }
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['csrf_error'] ?? 'Invalid CSRF token.') . "', true);</script>";
        }
    }
} catch (mysqli_sql_exception $e) {
    // Handle duplicate entry error
    if ($e->getCode() == 1062) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found.') . "', true);</script>";
    } else {
        // Log the error and show a general error message
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch all potential site owners (site managers)
$user_stmt = $MySQL->getConnection()->prepare("SELECT user_guid, first_name, last_name FROM users WHERE `group` IN ('admins','site_managers') ORDER BY first_name ASC, last_name ASC");
$users = [];
if ($user_stmt) {
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    while ($user_row = $user_result->fetch_assoc()) {
        $users[] = $user_row;
    }
    $user_stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=sites';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";

// Display the add site form
echo '
<div class="page">
    <h2 style="margin: 15px;"><a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
    ' . htmlspecialchars($lang_data[$selectedLanguage]["add_site"] ?? "Add Site") . '</h2>
    <div class="edit-user-page">
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table>
                <tbody>
                    <tr>
                        <td style="width: 30%;" colspan="1">
                            <label for="site_name">' . htmlspecialchars($lang_data[$selectedLanguage]["site_name"] ?? "Site Name") . '</label>
                            <input type="text" id="site_name" name="site_name" value="" required>
                        </td>
                        <td style="width: 70%;" colspan="2">
                            <label for="site_address">' . htmlspecialchars($lang_data[$selectedLanguage]["site_address"] ?? "Site Address") . '</label>
                            <input  style="width: 90%;" type="text" id="site_address" name="site_address" value="" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="phone_number">' . htmlspecialchars($lang_data[$selectedLanguage]["phone_number"] ?? "Phone Number") . '</label>
                            <input type="text" id="phone_number" name="phone_number" value="" required>
                        </td>
                        <td>
                            <label for="shiftStart_time">' . htmlspecialchars($lang_data[$selectedLanguage]["shiftStart_time"] ?? "Clock In Time") . '</label>
                            <input type="time" id="shiftStart_time" name="shiftStart_time" value="07:00:00" required>
                        </td>
                        <td>
                            <label for="shiftEnd_time">' . htmlspecialchars($lang_data[$selectedLanguage]["shiftEnd_time"] ?? "Clock Out Time") . '</label>
                            <input type="time" id="shiftEnd_time" name="shiftEnd_time" value="18:00:00" required>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <label for="site_owner_guid">' . htmlspecialchars($lang_data[$selectedLanguage]["site_owner_guid"] ?? "Site Owner") . '</label>
                            <select style="width: 96%;" id="site_owner_guid" name="site_owner_guid" required>
                                <option value="0" disabled selected>' . htmlspecialchars($lang_data[$selectedLanguage]["select_site_owner"] ?? "Select Site Owner") . '</option>';
foreach ($users as $user) {
    echo '<option value="' . htmlspecialchars($user['user_guid']) . '">' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</option>';
}
echo '                      </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 95%; margin-top: 20px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["add"] ?? "Add") . '</button>
        </form>
    </div>
</div>
';
?>

<?php
// pages/admin/sites/edit.php

// Include necessary configurations and handlers
global $lang_data, $selectedLanguage, $MySQL;require_once './inc/functions.php';
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

// Ensure the user has the correct role
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

// Validate and sanitize the worker_id from GET
if (isset($_GET['site_guid'])) {
    $site_guid = intval($_GET['site_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

try {
// Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Handle form submission for updating user details
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            // Retrieve and sanitize form inputs
            $updated_site_name = trim($_POST['site_name']);
            $updated_site_address = trim($_POST['site_address']);
            $updated_phone_number = trim($_POST['phone_number']);
            $updated_shiftStart_Time = trim($_POST['shiftStart_time']);
            $updated_shiftEnd_Time = trim($_POST['shiftEnd_time']);
            $updated_site_owner_guid = trim($_POST['site_owner_guid']);

            // Update user details in the database
            $sql = "UPDATE sites SET site_name = ?, site_address = ?, phone_number = ?, shiftStart_Time = ?, shiftEnd_Time = ?, site_owner_guid = ? WHERE site_guid = ?";
            $updateStmt = $MySQL->getConnection()->prepare($sql);
            if ($updateStmt) {
                $updateStmt->bind_param("sssssii", $updated_site_name, $updated_site_address, $updated_phone_number, $updated_shiftStart_Time, $updated_shiftEnd_Time, $updated_site_owner_guid, $site_guid);
                if ($updateStmt->execute()) {
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['site_update_success'] ?? 'Site updated successfully.') . "', true);</script>";
                } else {

                    // Check if the error is a duplicate entry error (MySQL error code 1062)
                    if ($updateStmt->errno == 1062) {
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found. Please check the unique fields.') . "', true);</script>";
                    } else {
                        // Log the error for debugging purposes
                        error_log("Error updating user: " . $updateStmt->error);
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['update_error'] ?? 'Error updating user.') . "', true);</script>";
                    }
                }
                $updateStmt->close();
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
    // Check for duplicate entry error
    if ($e->getCode() == 1062) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found.') . "', true);</script>";
    } else {
        // Log the error and show a general error message
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

// Fetch user details
$stmt = $MySQL->getConnection()->prepare("SELECT * FROM sites WHERE site_guid = ?");
if ($stmt) {
    $stmt->bind_param("i", $site_guid);
    $stmt->execute();
    $stmt->bind_result($site_guid, $site_name, $site_address, $phone_number, $shiftStart_time, $shiftEnd_time, $site_owner_guid);
    if ($stmt->fetch()) {
        // User exists, display the edit form
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['site_not_found'] ?? 'Site not found.') . "', true);</script>";
        $stmt->close();
        exit();
    }
    $stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}


// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isAdmin = $_SESSION['loggedIn']['group'] == 'admins' ?? false;
$disabled_bg = $isAdmin ? "" : " background: linear-gradient(180deg, gray, steelblue)";
// Display the edit form
echo '
<div class="page">
    <h2 style="margin-top: 0; margin-bottom: 5px;">' . htmlspecialchars($lang_data[$selectedLanguage]["edit_site"] ?? "Edit Site") . '</h2>
    <div class="edit-user-page" style="height: 50px; padding-top: 45px; margin-top: 0; margin-bottom: 0;">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table style="margin-top: -5px; width: 100vw;">
                <tbody>
                    <tr>
                        <td>
                            <label for="site_name">' . htmlspecialchars($lang_data[$selectedLanguage]["site_name"] ?? "Site name") . '</label>
                            <input style="width: 85%;" type="text" id="site_name" name="site_name" value="' . htmlspecialchars($site_name) . '" required' . ( $isAdmin ? "" : " readonly" ) . '>
                            
                        </td>
                        <td colspan="2">
                            <label for="site_address">' . htmlspecialchars($lang_data[$selectedLanguage]["site_address"] ?? "Site address") . '</label>
                            <input style="width: 87%;" type="text" id="site_address" name="site_address" value="' . htmlspecialchars($site_address) . '" required' . ( $isAdmin ? "" : " readonly" ) . '>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="phone_number">' . htmlspecialchars($lang_data[$selectedLanguage]["phone_number"] ?? "Phone number") . '</label>
                            <input type="tel" id="phone_number" name="phone_number" value="' . htmlspecialchars($phone_number) . '" required>
                        </td>
                        <td>
                            <label for="shiftStart_time">' . htmlspecialchars($lang_data[$selectedLanguage]["shiftStart_time"] ?? "Clock in time") . '</label>
                            <input style="height: 15px; width: 73%;" type="time" id="shiftStart_time" name="shiftStart_time" value="' . htmlspecialchars($shiftStart_time) . '" required>
                        </td>
                        <td>
                            <label for="shiftEnd_time">' . htmlspecialchars($lang_data[$selectedLanguage]["shiftEnd_time"] ?? "Clock out time") . '</label>
                            <input style="height: 15px; width: 74.5%;" type="time" id="shiftEnd_time" name="shiftEnd_time" value="' . htmlspecialchars($shiftEnd_time) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <label for="site_owner_guid">' . htmlspecialchars($lang_data[$selectedLanguage]["site_owner_guid"] ?? "Site Owner") . '</label>
                            <select style="width: 93.5%;'.$disabled_bg.'" id="site_owner_guid" name="site_owner_guid" required>';
                                $sql = "
                                                    SELECT *
                                                    FROM users
                                                    WHERE `group` IN ('admins','site_managers')
                                                    ORDER BY user_guid
                                                ";


                                $stmt = $MySQL->getConnection()->prepare($sql);

                                if ($stmt) {
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result) {
                                        $found = false;
                                        if($site_owner_guid == 0)
                                            echo '<option value="0" selected disabled>No site owner found</option>';
                                        while ($row = $result->fetch_assoc()) {
                                            if($_SESSION['loggedIn']['group'] != 'admins') break;
                                            $user_guid = htmlspecialchars($row['user_guid']);
                                            $full_name = htmlspecialchars($row['first_name']) . " " . htmlspecialchars($row['last_name']);
                                            $phone_number = htmlspecialchars($row['phone_number']);
                                            $selected = ($user_guid == $site_owner_guid) ? 'selected' : '';
                                            if($selected != '') $found = true;
                                            echo '<option value="' . $user_guid . '" ' . $selected .'>' . $full_name . '</option>';
                                        }
                                        if(!$found) {
                                            if($site_owner_guid != 0)
                                                echo '<option value="' . $site_owner_guid . '" selected>' . getOwnerName($site_owner_guid) . '</option>';
                                        }
                                        $stmt->close();
                                    } else {
                                        echo '<option disabled>Error fetching managers</option>';
                                        $stmt->close();
                                    }
                                } else {
                                    echo '<option disabled>Database error</option>';
                                }
    echo '
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 92vw; margin-left: 2px; margin-bottom: 0;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["update"] ?? "Update") . '</button>
        </form>
        
    </div>
    <div class="history_page" style="height: 280px; margin-top: 0;">
            ';
        getAllAssignments($site_guid, 0, true);
    echo '
    </div>
</div>
<script>


</script>
';
?>

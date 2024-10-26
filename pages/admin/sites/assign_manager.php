<?php
// pages/admin/sites/assign_manager.php

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

// Get the site_guid from the URL
if (isset($_GET['site_guid'])) {
    $site_guid = intval($_GET['site_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Fetch current site details
    $stmt_site = $MySQL->getConnection()->prepare("SELECT site_name FROM sites WHERE site_guid = ?");
    $stmt_site->bind_param("i", $site_guid);
    $stmt_site->execute();
    $stmt_site->bind_result($site_name);
    $stmt_site->fetch();
    $stmt_site->close();

    // Fetch the current managers, if any
    $sql = "SELECT u.user_guid, u.first_name, u.last_name, u.phone_number 
            FROM site_managers sm 
            JOIN users u ON sm.user_guid = u.user_guid 
            WHERE sm.site_guid = ?";
    $stmt = $MySQL->getConnection()->prepare($sql);
    $stmt->bind_param("i", $site_guid);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned_managers = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch available managers (admins and site_managers) not already assigned
    $sql_available_managers = "SELECT user_guid, first_name, last_name 
                               FROM users 
                               WHERE `group` IN ('admins', 'site_managers') 
                               AND user_guid NOT IN (SELECT user_guid FROM site_managers WHERE site_guid = ?)
                               ORDER BY first_name, last_name";
    $stmt = $MySQL->getConnection()->prepare($sql_available_managers);
    $stmt->bind_param("i", $site_guid);
    $stmt->execute();
    $available_managers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
}

// Generate CSRF token for form submission
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_manager'])) {
    $selected_manager = intval($_POST['selected_manager']);
    try {
        // Insert the new manager
        $sql_insert = "INSERT INTO site_managers (site_guid, user_guid) VALUES (?, ?)";
        $stmt_insert = $MySQL->getConnection()->prepare($sql_insert);
        $stmt_insert->bind_param("ii", $site_guid, $selected_manager);
        $stmt_insert->execute();
        $stmt_insert->close();

        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['manager_assigned_success'] ?? 'Manager assigned successfully.') . "', true);</script>";

        // Refresh the list of assigned managers after assignment
        $sql = "SELECT u.user_guid, u.first_name, u.last_name, u.phone_number  
                FROM site_managers sm 
                JOIN users u ON sm.user_guid = u.user_guid 
                WHERE sm.site_guid = ?";
        $stmt = $MySQL->getConnection()->prepare($sql);
        $stmt->bind_param("i", $site_guid);
        $stmt->execute();
        $result = $stmt->get_result();
        $assigned_managers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Refresh the list of available managers
        $sql_available_managers = "SELECT user_guid, first_name, last_name 
                                   FROM users 
                                   WHERE `group` IN ('admins', 'site_managers') 
                                   AND user_guid NOT IN (SELECT user_guid FROM site_managers WHERE site_guid = ?)
                                   ORDER BY first_name, last_name";
        $stmt = $MySQL->getConnection()->prepare($sql_available_managers);
        $stmt->bind_param("i", $site_guid);
        $stmt->execute();
        $available_managers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } catch (mysqli_sql_exception $e) {
        // Handle duplicate entry error gracefully
        if ($e->getCode() == 1062) {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['manager_already_assigned'] ?? 'Manager is already assigned to this site.') . "', true);</script>";
        } else {
            error_log("Error: " . $e->getMessage());
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'].$e->getMessage() ?? 'Database error occurred.'. $e->getMessage()) . "', true);</script>";
        }
    }
}

// Display the form and current managers
echo '
<div class="page">
    <h2 style="margin: 2px;">' . htmlspecialchars($lang_data[$selectedLanguage]["assign_manager"] ?? "Assign Manager to Site: " . $site_name) . '</h2>
    <div class="edit-user-page">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';

    echo '
            <div class="available-managers-section" style="width: 92vw">
                <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["available_managers"] ?? "Available Managers") . '</h3>';

    if (count($available_managers) > 0) {
        echo '<label for="selected_manager">' . htmlspecialchars($lang_data[$selectedLanguage]["select_manager"] ?? "Select Manager") . '</label>
              <select style="width: 92vw" id="selected_manager" name="selected_manager" required>';
        echo '<option disabled selected value="">' . htmlspecialchars($lang_data[$selectedLanguage]["select_manager"] ?? "Select a manager") . '</option>';
        foreach ($available_managers as $manager) {
            $manager_guid = htmlspecialchars($manager['user_guid']);
            $manager_name = htmlspecialchars($manager['first_name'] . " " . $manager['last_name']);
            echo '<option value="' . $manager_guid . '">' . $manager_name . '</option>';
        }
        echo '</select>';
        echo '<button style="width: 92vw; margin-top: 20px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["assign_manager"] ?? "Assign Manager") . '</button>';
    } else {
        echo '<p>' . htmlspecialchars($lang_data[$selectedLanguage]['no_managers_available'] ?? "No managers available") . '</p>';
    }

    echo '
            </div>
        </form>
    </div>';

    // Show the currently assigned managers, if any
    if (count($assigned_managers) > 0) {
        echo '
    <h3 style="margin: 2px;">' . htmlspecialchars($lang_data[$selectedLanguage]["assigned_managers"] ?? "Assigned Managers") . '</h3>
    <div class="user-list" style="height: 20%;">
        <table>
            <thead>
                <tr>
                    <th>' . htmlspecialchars($lang_data[$selectedLanguage]["name"] ?? "Name") . '</th>
                    <th>' . htmlspecialchars($lang_data[$selectedLanguage]["phone_number"] ?? "Phone Number") . '</th>
                    <th style="white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? "Actions") . '</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($assigned_managers as $manager) {
            $current_manager_guid = htmlspecialchars($manager['user_guid']);
            $current_manager_first_name = htmlspecialchars($manager['first_name']);
            $current_manager_last_name = htmlspecialchars($manager['last_name']);
            $current_manager_phone_number = htmlspecialchars($manager['phone_number']);

            echo '
                <tr>
                    <td style="height: 25px;"><a style="text-decoration: underline; color: black;" href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=users&action=edit&user_guid=' . $current_manager_guid . '">' . htmlspecialchars($current_manager_first_name . " " . $current_manager_last_name) . '</a></td>
                    <td style="height: 25px;">
                        <a style="right: 0; position: relative; margin: 0; color: black; text-decoration: underline;" href="https://wa.me/'.$current_manager_phone_number.'" class="whatsapp-icon" id="sendWhatsApp">
                            '. $current_manager_phone_number . '
                            <img style="width: 15px; height: 15px;" src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp">
                        </a>
                    </td>
                    <td style="white-space: nowrap; width: 1%; max-width: max-content;"><a href="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=sites&action=unassign_manager&user_guid=' . $current_manager_guid . '&site_guid=' . $site_guid . '"><img class="manage_shift_btn" src="img/unassign.png" alt="Unassign"></a></td>
                </tr>';
        }
        echo '
            </tbody>
        </table>
    </div>';
    } else {
        echo '
    <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["no_manager_assigned"] ?? "No site manager assigned") . '</h3>';
    }

    echo '
</div>
';
?>

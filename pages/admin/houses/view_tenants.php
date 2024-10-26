<?php
// pages/admin/houses/view_tenants.php

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
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Fetch tenants (workers) living in the house
    $stmt = $MySQL->getConnection()->prepare("
        SELECT users.first_name, users.user_guid, workers.worker_id
        FROM workers 
        JOIN users ON workers.user_guid = users.user_guid
        WHERE workers.house_guid = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $house_guid);
        $stmt->execute();
        $result = $stmt->get_result();

        // Display the tenants page content
        echo '
        <div class="page" style="width: 100%; justify-content: center;">
            <h2>' . htmlspecialchars($lang_data[$selectedLanguage]["tenants_list"] ?? "Tenants List") . ' For House '.getHouseAddressDescription($house_guid).'</h2>
            <div class="document-list" style="width: 90%; margin-right: 5%; margin-left: 5%; justify-content: center;">
                
                <table>
                    <thead>
                        <tr>
                            <th style="white-space: nowrap; width: 1%; max-width: max-content; font-weight: bolder;">#</th>
                            <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]["name"] ?? "Name") . '</th>
                        </tr>
                    </thead>
                    <tbody>';

        // Check if there are results
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $first_name = htmlspecialchars($row['first_name']);
                $worker_id = htmlspecialchars($row['worker_id']);
                echo '
                <tr>
                    <td>' . $worker_id . '</td>
                    <td><a style="text-decoration: underline; color: black;" href="index.php?lang='.$selectedLanguage.'&page=admin&sub_page=workers&action=edit&user_guid='.$row['user_guid'].'">' . $first_name . '</a></td>
                </tr>';
            }
        } else {
            echo '<tr><td colspan="2">' . htmlspecialchars($lang_data[$selectedLanguage]["no_tenants_found"] ?? "No tenants found") . '</td></tr>';
        }

        echo '
                    </tbody>
                </table>
            </div>
        </div>';

        $stmt->close();
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    }
} catch (mysqli_sql_exception $e) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    error_log("Error: " . $e->getMessage());
}

?>

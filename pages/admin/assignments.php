<?php
// pages/admin/assignments.php


// Include necessary configurations and handlers
global $selectedLanguage;
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

$user_guid = $_SESSION['loggedIn']['user_guid'];

global $lang_data, $selectedLanguage, $MySQL;
/*
try {
// Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Handle form submission for updating user details
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            // Retrieve and sanitize form inputs
            $site_guid = trim($_POST['site_guid']);
            $workers = trim($_POST['workers']);
            $description = trim($_POST['description']);
            $shift_start_date = trim($_POST['shift_start_date']);
            $shift_end_date = trim($_POST['shift_end_date']);

            // Update user details in the database
            $sql = "INSERT INTO shift_assignments (`site_guid`, `workers`, `description`, `shift_start_date`, `shift_end_date`, `shift_created_date`) VALUES (?, ?, ?, ?, ?, NOW())";
            $updateStmt = $MySQL->getConnection()->prepare($sql);
            if ($updateStmt) {
                $updateStmt->bind_param("iisss", $site_guid, $workers, $description, $shift_start_date, $shift_end_date);
                if ($updateStmt->execute()) {
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['request_create_success'] ?? 'Request created successfully.') . "', true);</script>";
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
*/

// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}



// Display the edit form
echo '
<div class="page">   
    <div class="history_page">';
        getAllAssignments(0,0,true);
echo '
    </div>
</div>
';
?>

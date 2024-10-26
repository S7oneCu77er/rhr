<?php
// pages/admin/assignments/edit.php

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

// Validate and sanitize the worker_id from GET
if (isset($_GET['assignment_guid'])) {
    $assignment_guid = intval($_GET['assignment_guid']);
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
            $updated_workers_plain = $_POST['workers'];
            $updated_description_plain = $_POST['description'];

            $updated_workers = json_encode($updated_workers_plain);
            $updated_description = json_encode($updated_description_plain, JSON_UNESCAPED_UNICODE);
            $updated_shift_start_date = trim($_POST['shift_start_date']);
            $updated_shift_end_date = trim($_POST['shift_end_date']);

            // Update user details in the database
            $sql = "UPDATE shift_assignments SET workers = ?, description = ?, shift_start_date = ?, shift_end_date = ? WHERE assignment_guid = ?";
            $updateStmt = $MySQL->getConnection()->prepare($sql);
            if ($updateStmt) {
                $updateStmt->bind_param("ssssi", $updated_workers, $updated_description, $updated_shift_start_date, $updated_shift_end_date, $assignment_guid);
                if ($updateStmt->execute()) {
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['assignment_update_success'] ?? 'Assignment updated successfully.') . "', true);</script>";
                } else {

                    // Check if the error is a duplicate entry error (MySQL error code 1062)
                    if ($updateStmt->errno == 1062) {
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found. Please check the unique fields.') . "', true);</script>";
                    } else {
                        // Log the error for debugging purposes
                        error_log("Error updating user: " . $updateStmt->error);
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['update_assignment_error'] ?? 'Error updating assignment.') . "', true);</script>";
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

// Fetch assignment details
$stmt = $MySQL->getConnection()->prepare("SELECT * FROM shift_assignments WHERE assignment_guid = ?");
if ($stmt) {
    $stmt->bind_param("i", $assignment_guid);
    $stmt->execute();
    $stmt->bind_result($assignment_guid, $site_guid, $workers, $description, $shift_start_date, $shift_end_date, $assignment_create_date);

    if ($stmt->fetch()) {
        // User exists, display the edit form
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['assignment_not_found'] ?? 'Assignment not found.') . "', true);</script>";
        $stmt->close();
        exit();
    }
    $stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

$workers_array = json_decode($workers, true);
$tmp_workers = $workers;
$description_array = json_decode($description, true);
$workers = array_sum(array_map('intval', (is_array($workers_array) ? $workers_array : $workers)));


// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$can_edit = $_SESSION['loggedIn']['group'] === 'admins' ? '' : ' readonly';
$margin_dir = in_array($selectedLanguage, ["Hebrew", "Arabic"]) ? "right" : "left"; // Adjust if needed
$back = 'javascript:window.history.go(-1);';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";

// Display the edit form
echo '
<div class="page">
    <h2 style="margin-top: 0; margin-bottom: 0;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["edit_assignment"] ?? "Edit assignment") . '
    </h2>
    <div class="edit-house-page" style="align-items: start; height: 1px; !important;">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table>
                <tbody>
                    <tr>
                        <td colspan="2">
                            <label for="assignment_create_date">' . htmlspecialchars($lang_data[$selectedLanguage]["assignment_create_date"] ?? "Created on date") . '</label>
                            <input style="width: 90%; font-size: 0.80rem;" type="text" id="assignment_create_date" name="assignment_create_date" value="'. $assignment_create_date .'" readonly>
                        </td>
                        <td style="width: 33.3%;">
                            <label for="shift_start_date">' . htmlspecialchars($lang_data[$selectedLanguage]["shift_start_date"] ?? "Start Date") . '</label>
                            <input style="font-size: 0.80rem;" type="date" id="shift_start_date" name="shift_start_date" value="' . $shift_start_date . '" required'.$can_edit.'>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 33.3%;">
                            <label for="site_guid">' . htmlspecialchars($lang_data[$selectedLanguage]["select_site"] ?? "Site name") . '</label>
                            <input style="font-size: 0.80rem;" type="text" id="site_guid" name="site_guid" value="'. getSiteName( $site_guid ) .'" readonly>
                        </td>
                        <td style="width: 33.3%;">
                            <label for="site_address">' . htmlspecialchars($lang_data[$selectedLanguage]["site_address"] ?? "Site address") . '</label>
                            <input style="font-size: 0.80rem;" type="text" id="site_address" name="site_address" value="'. getSiteAddress( $site_guid ) .'" readonly>
                        </td>
                        <td style="width: 33.3%;">
                            <label for="shift_end_date">' . htmlspecialchars($lang_data[$selectedLanguage]["shift_end_date"] ?? "End date") . '</label>
                            <input style="font-size: 0.80rem;" type="date" id="shift_end_date" name="shift_end_date" value="' . $shift_end_date . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <table style="width: 100%;" id="workers-container">';
                                if(is_array($description_array))
                                {
                                    foreach($description_array as $key => $desc)
                                    {
                                        $button = $key == 0 ? '<button type="button" id="add-worker-set" class="add-worker-set" style="padding: 4px 4px; font-size: 16px;">+</button>' : '<button type="button" id="remove-worker-set" class="remove-worker-set" style="padding: 4px 4px; font-size: 16px;">-</button>';
                                        echo '                          
                                <tr class="worker-set">
                                    <td>
                                        <label for="workers">' . htmlspecialchars($lang_data[$selectedLanguage]["workers"] ?? "Workers") . '</label>
                                        <input style="width: 87%; font-size: 0.80rem;" type="number" id="workers" name="workers[]" value="' . $workers_array[$key] . '" required>
                                    </td> 
                                    <td style="width: 1%;">
                                        '. $button .'
                                    </td>
                                    <td>
                                        <label for="description">' . htmlspecialchars($lang_data[$selectedLanguage]["description"] ?? "Description") . '</label>
                                        <input style="width: 87%; font-size: 0.80rem;" type="text" id="description" name="description[]" value="' . $desc . '" required>
                                    </td>
                                </tr>
                                        ';
                                    }
                                }
                            echo '
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 92%; margin-top: 5px; margin-bottom: 0;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["update"] ?? "Update") . '</button>
        </form>
    </div>
    <div class="history_page" style="height: 70px; margin-bottom: 0; margin-top: 0;">
        ';
        getAllAssignments(0, $assignment_guid, true);
echo "
    
    </div>
</div>
<script>
    var workers_description_pair_count = 1;
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('workers-container');

        function addWorkerSet() {
            const firstSet = container.querySelector('.worker-set');
            const newWorkerSet = firstSet.cloneNode(true);

            newWorkerSet.querySelector('input[id=\"workers\"]').value = '';
            newWorkerSet.querySelector('input[id=\"description\"]').value = '';

            // Remove + button and add - button for removal
            newWorkerSet.querySelector('button').outerHTML = '<button type=\"button\" id=\"remove-worker-set\" class=\"remove-worker-set\" style=\"padding: 4px 8px; font-size: 16px;\">-</button>';

            container.appendChild(newWorkerSet);
            workers_description_pair_count++;
            if(workers_description_pair_count >= 5 )
                document.querySelector('.add-worker-set').style.display = 'none';

            // Add event listener to remove button
            newWorkerSet.querySelector('.remove-worker-set').addEventListener('click', function() {
                newWorkerSet.remove();
            });
        }

        // Event listener for initial + button
        document.querySelector('.add-worker-set').addEventListener('click', addWorkerSet);

        // Event listeners for dynamically added remove buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-worker-set')) {
                e.target.closest('.worker-set').remove();
                workers_description_pair_count--;
                if(workers_description_pair_count <= 5 )
                    document.querySelector('.add-worker-set').style.display = '';
            }
        });
    });
</script>
";
?>

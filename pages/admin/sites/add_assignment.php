<?php
// pages/admin/sites/add_assignment.php

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

// Validate and sanitize the site_guid from GET
if (isset($_GET['site_guid'])) {
    $site_guid = intval($_GET['site_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

// Ensure the user has the admin role or is a site manager for this site
if ($_SESSION['loggedIn']['group'] !== 'admins' && (!isSiteManagerForSite($site_guid, $_SESSION['loggedIn']['user_guid']) || $_SESSION['loggedIn']['group'] !== 'site_managers')) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Handle form submission for shift assignment
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            // Retrieve and sanitize form inputs
            $site_guid = trim($_POST['site_guid']);
            $workers = $_POST['workers']; // Array of workers
            $descriptions = $_POST['description']; // Array of descriptions
            $shift_start_date = trim($_POST['shift_start_date']);
            $shift_end_date = trim($_POST['shift_end_date']);


            // Encode workers and descriptions as JSON to store in the database
            $workers_json = json_encode($workers);
            $descriptions_json = json_encode($descriptions, JSON_UNESCAPED_UNICODE);

            // Get current date and time
            $now = new DateTime();
            $shiftStart = new DateTime($shift_start_date);

            // Check if the shift start is tomorrow and past 15:00 today
            $tomorrow = new DateTime();
            $tomorrow->modify('+1 day');

            if (($shiftStart->format('Y-m-d') === $tomorrow->format('Y-m-d') && $now->format('H') >= 15) && $_SESSION['loggedIn']['group'] !== 'admins') {
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['late_request_error'] ?? 'You are past the last time to make new worker requests for tomorrow.') . "', true);</script>";
                exit();
            }

            // Insert the shift assignment with workers and descriptions encoded as JSON
            $sql = "INSERT INTO shift_assignments (site_guid, workers, description, shift_start_date, shift_end_date, shift_created_date) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $MySQL->getConnection()->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("issss", $site_guid, $workers_json, $descriptions_json, $shift_start_date, $shift_end_date);
                $stmt->execute();
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['request_create_success'] ?? 'Request created successfully.') . "', true);</script>";
            } else {
                error_log("Prepare failed: " . $MySQL->getConnection()->error);
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
            }
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['csrf_error'] ?? 'Invalid CSRF token.') . "', true);</script>";
        }
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found.') . "', true);</script>";
    } else {
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Display the form (same as previous form)
echo '
<div class="page">
    <h2 style="margin: 2px;">' . htmlspecialchars($lang_data[$selectedLanguage]["request_workers"] ?? "Request workers") . '</h2>
    <div class="worker_request_page">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table style="margin-top: 10px; border-collapse: collapse;">
                <tbody id="workers-container">
                    <tr>
                        <td>
                            <label for="site_name">' . htmlspecialchars($lang_data[$selectedLanguage]["select_site"] ?? "Site name") . '</label>
                            <input type="text" id="site_name" name="site_name" value="' . getSiteName($site_guid) . '" readonly>
                        </td>
                        <td></td>
                        <td>
                            <label for="site_address">' . htmlspecialchars($lang_data[$selectedLanguage]["site_address"] ?? "Site address") . '</label>
                            <input type="text" id="site_address" name="site_address" value="' . getSiteAddress($site_guid) . '" readonly>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="shift_start_date">' . htmlspecialchars($lang_data[$selectedLanguage]["shift_start_date"] ?? "Start Date") . '</label>
                            <input type="date" id="shift_start_date" name="shift_start_date" required>
                        </td>
                        <td></td>
                        <td>
                            <label for="shift_end_date">' . htmlspecialchars($lang_data[$selectedLanguage]["shift_end_date"] ?? "End date") . '</label>
                            <input type="date" id="shift_end_date" name="shift_end_date" required>
                        </td>
                    </tr>
                    <tr class="worker-set">
                        <td>
                            <label for="workers">' . htmlspecialchars($lang_data[$selectedLanguage]["workers"] ?? "Workers Amount") . '</label>
                            <input type="number" id="workers" name="workers[]" value="1" required>
                        </td>
                        <td style="width: 1%;">
                            <button type="button" id="add-worker-set" class="add-worker-set" style="padding: 4px 4px; font-size: 16px;">+</button>
                        </td>
                        <td>
                            <label for="description">' . htmlspecialchars($lang_data[$selectedLanguage]["workers_description"] ?? "Workers Description") . '</label>
                            <input type="text" id="description" name="description[]" value="" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <input type="text" id="site_guid" name="site_guid" value="' . $site_guid . '" hidden required>
            <button style="width: 92%; margin: 20px 0 10px 0;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["make_request"] ?? "Make Request") . '</button>
        </form>
    </div>
    </div>
    ';

// Add script for dynamically adding/removing worker sets (same as before)
echo "
<script>
    // Script for adding/removing workers dynamically
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

    // Function to format date to YYYY-MM-DD
    function formatDate(date) {
        let d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    // Set shift_start_date to today's date
    document.getElementById('shift_start_date').value = formatDate(new Date());

    // Set shift_end_date to tomorrow's date
    let tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('shift_end_date').value = formatDate(tomorrow);

    document.getElementById('btn-update').addEventListener('click', function(e) {
        let shiftStartDate = new Date(document.getElementById('shift_start_date').value);
        let now = new Date();

        let tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);

        ";
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "
        // Check if shift start is tomorrow and current time is past 15:00
        if (shiftStartDate.toDateString() === tomorrow.toDateString() && now.getHours() >= 15) {
            e.preventDefault();
            showError('You are past the last time to make new worker requests for tomorrow.');
        }
";
}
echo "
    });
</script>
";

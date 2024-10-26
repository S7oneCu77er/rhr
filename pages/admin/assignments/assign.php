<?php
// pages/admin/assignments/assign.php

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

// Ensure the user has the admin or site_manager role
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

// Get the assignment_guid from the URL
if (isset($_GET['assignment_guid'])) {
    $assignment_guid = intval($_GET['assignment_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}


try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Fetch the total number of workers allowed and currently assigned workers for this assignment
    $sql = "
        SELECT sa.workers, COUNT(saw.user_guid) AS assigned_count
        FROM shift_assignments sa
        LEFT JOIN shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
        WHERE sa.assignment_guid = ?
        GROUP BY sa.assignment_guid
    ";
    $stmt = $MySQL->getConnection()->prepare($sql);
    $stmt->bind_param("i", $assignment_guid);
    $stmt->execute();
    $stmt->bind_result($total_workers, $assigned_count);
    $stmt->fetch();
    $stmt->close();
    $workers_array = json_decode($total_workers, true);
    $total_workers = array_sum(array_map('intval', $workers_array));

    // Fetch available workers (not assigned, not on relief, and not disabled)
    $sql = "
        SELECT w.user_guid, u.first_name, u.last_name, w.worker_id
        FROM workers w
        JOIN users u ON w.user_guid = u.user_guid
        LEFT JOIN shift_assignment_workers saw ON w.user_guid = saw.user_guid
        WHERE saw.user_guid IS NULL
        AND u.group != 'disabled'
        AND (w.on_relief = 0 OR w.relief_end_date < CURDATE())
        ORDER BY w.worker_id
    ";
    $stmt = $MySQL->getConnection()->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $available_workers = [];
    while ($row = $result->fetch_assoc()) {
        $available_workers[] = $row;
    }
    $stmt->close();

    // Fetch currently assigned workers
    $sql = "
        SELECT w.user_guid, w.profession, u.first_name, u.last_name, w.worker_id
        FROM workers w
        JOIN users u ON w.user_guid = u.user_guid
        JOIN shift_assignment_workers saw ON w.user_guid = saw.user_guid
        WHERE saw.assignment_guid = ?
        ORDER BY w.worker_id
    ";
    $stmt = $MySQL->getConnection()->prepare($sql);
    $stmt->bind_param("i", $assignment_guid);
    $stmt->execute();
    $assigned_workers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $full_text = "";
    // Check if the assignment is full
    if ($assigned_count >= $total_workers) {
        $full_text= "
            <div style='margin-top: 50px;'>
                    " . htmlspecialchars($lang_data[$selectedLanguage]['assignment_full'] ?? "The assignment is already full.") . "
            </div>";
    }

} catch (mysqli_sql_exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
}

// Generate CSRF token for form submission
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_worker'])) {
    $selected_worker = intval($_POST['selected_worker']);
    try {

        // Fetch the total number of workers allowed and currently assigned workers for this assignment
        $sql = "
        SELECT sa.workers, COUNT(saw.user_guid) AS assigned_count
        FROM shift_assignments sa
        LEFT JOIN shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
        WHERE sa.assignment_guid = ?
        GROUP BY sa.assignment_guid
    ";
        $stmt = $MySQL->getConnection()->prepare($sql);
        $stmt->bind_param("i", $assignment_guid);
        $stmt->execute();
        $stmt->bind_result($total_workers, $assigned_count);
        $stmt->fetch();
        $stmt->close();
        $workers_array = json_decode($total_workers, true);
        $total_workers = array_sum(array_map('intval', (is_array($workers_array) ? $workers_array : [$total_workers])));

        // There is room for more workers in this assignment
        if ($assigned_count < $total_workers) {
            // Delete the previous shift assignment for the worker
            $deleteOldAssignmentSql = "DELETE FROM shift_assignment_workers WHERE user_guid = ?";
            $deleteStmt = $MySQL->getConnection()->prepare($deleteOldAssignmentSql);
            $deleteStmt->bind_param("i", $selected_worker);
            $deleteStmt->execute();
            $deleteStmt->close();

            if($assignment_guid != 0) {
                $insertSql = "INSERT INTO shift_assignment_workers (assignment_guid, user_guid) VALUES (?, ?)";
                $insertStmt = $MySQL->getConnection()->prepare($insertSql);
                $insertStmt->bind_param("ii", $assignment_guid, $selected_worker);
                $insertStmt->execute();
                $insertStmt->close();
            }
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['worker_assigned_successfully'] ?? 'Worker assigned successfully.') . "', true);</script>";
        } else {
            // No room left for more workers
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['no_available_slots'] ?? 'No available slots for this assignment.') . "-".$assigned_count."', true);</script>";
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

$margin_dir = in_array($selectedLanguage, ["Hebrew", "Arabic"]) ? "right" : "left"; // Adjust if needed
$back = 'javascript:window.history.go(-1);';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";

// Display the form and workers list
echo '
<div class="page">
    ';
echo $full_text;
if ($assigned_count < $total_workers) {
    echo '
    <h2 style="margin: 10px;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["assign_worker"] ?? "Assign Worker") . '
    </h2>
    <div class="edit-user-page">
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["available_workers"] ?? "Available Workers") . '</h3>
            <div class="available-workers-section" style="width: 92vw; display: flex; justify-content: center; align-items: center; flex-direction: row;">
            
                
                
                <!-- Input field for entering worker ID -->
                
                
                <!-- Select dropdown for available workers -->
                
                <input style="width: 10%;" type="text" id="worker_id_input" placeholder="' . htmlspecialchars($lang_data[$selectedLanguage]["worker_id"] ?? "Enter Worker ID") . '" style="margin-right: 15px;" />
                <select style="width: 74%;" id="selected_worker" name="selected_worker" required>
                ';

    if (count($available_workers) > 0) {
        echo '
                    <option disabled selected value="">' . htmlspecialchars($lang_data[$selectedLanguage]["select_worker"] ?? "Select a worker") . '</option>';
        foreach ($available_workers as $worker) {
            echo '
                    <option value="' . htmlspecialchars($worker['user_guid']) . '" data-worker-id="' . htmlspecialchars($worker['worker_id']) . '">' . htmlspecialchars($worker['first_name'] . " " . $worker['last_name'] . " (ID: " . $worker['worker_id']) . '</option>';
        }
    } else {
        echo '
                    <option disabled>' . htmlspecialchars($lang_data[$selectedLanguage]['no_workers_available'] ?? "No workers available") . '</option>';
    }
    echo '
                </select>
                
            </div>
            <button style="width: 85%; margin-top: 20px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["assign_worker"] ?? "Assign Worker") . '</button>
            
        </form>
    </div>';
}
echo '
    <h3 style="margin-top: 50px; margin-bottom: 1px;">' . htmlspecialchars($lang_data[$selectedLanguage]["assigned_workers"] ?? "Assigned Workers") . '</h3>
    <div class="user-list" style="height: 55%;">
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>' . htmlspecialchars($lang_data[$selectedLanguage]["name"] ?? "Name") . '</th>
                    <th>' . htmlspecialchars($lang_data[$selectedLanguage]["profession"] ?? "Profession") . '</th>
                    <th style="white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? "Actions") . '</th>
                </tr>
            </thead>
            <tbody>';
$url = 'index.php?lang=' . urlencode($selectedLanguage);
foreach($_GET as $key => $value) {
    if($key == 'lang' || $key == 'action' || $key == 'site_guid') continue;
    $url .= '&' . urlencode($key) . '=' . urlencode($value);
}
$editurl = preg_replace('/(sub_page=)[^&]+/', 'sub_page=workers&action=assign&to=assignment', $url);
$del_url = preg_replace('/(sub_page=)[^&]+/', 'sub_page=assignments&action=unassign', $url);
$workerurl  = preg_replace('/(sub_page=)[^&]+/', 'sub_page=workers&action=edit', $url);
if (count($assigned_workers) > 0) {
    foreach ($assigned_workers as $worker) {
        $edit_url = preg_replace('/(assignment_guid=)[^&]+/', 'user_guid=' . $worker['user_guid'], $editurl);
        $delete_url = preg_replace('/(assignment_guid=)[^&]+/', 'assignment_guid='. $assignment_guid .'&user_guid=' . $worker['user_guid'], $del_url);
        $worker_url = preg_replace('/(assignment_guid=)[^&]+/', 'user_guid=' . $worker['user_guid'], $workerurl);

        echo '
                                    <tr>
                                        <td><a style="color: black; text-decoration: underline;" href='.$worker_url.'>' . htmlspecialchars($worker['worker_id']) . '</a></td>
                                        <td><a style="color: black; text-decoration: underline;" href='.$worker_url.'>' . htmlspecialchars($worker['first_name']) . '</a></td>
                                        <td>' . htmlspecialchars($worker['profession']) . '</td>
                                        <td style="white-space: nowrap; width: 1%; max-width: max-content;">
                                            <a href="' . $edit_url . '"><img class="manage_shift_btn" src="img/exchange.png" alt=""></a>
                                            <a href="' . $delete_url . '"><img class="manage_shift_btn" src="img/unassign.png" alt=""></a>
                                        </td>
                                    </tr>';
    }
} else {
    echo '<tr><td colspan="4">' . htmlspecialchars($lang_data[$selectedLanguage]['no_workers_assigned'] ?? "No workers assigned") . '</td></tr>';
}
echo '
            </tbody>
        </table>
    </div>
</div>
';

// JavaScript to filter workers by worker_id in the select box
echo '
<script>
    document.getElementById("worker_id_input").addEventListener("input", function() {
        var inputVal = this.value.trim();
        var select = document.getElementById("selected_worker");
        var options = select.options;

        for (var i = 0; i < options.length; i++) {
            var workerId = options[i].getAttribute("data-worker-id");
            if (workerId && (workerId.includes(inputVal) || inputVal === "")) {
                options[i].disabled = false;  // Enable matching workers
                options[i].hidden = false;    // Show matching workers
            } else {
                options[i].disabled = true;   // Disable non-matching workers
                options[i].hidden = true;     // Hide non-matching workers
            }
        }
    });
</script>
';
?>

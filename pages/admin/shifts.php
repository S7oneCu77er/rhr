<?php
// pages/admin/shifts.php

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

// Define sorting parameters (default is by date, ascending)
$sort_by = $_GET['sort_by'] ?? 'date';
$sort_order = $_GET['sort_order'] ?? 'asc'; // Default to ascending

// Toggle the sorting order for each column header
function toggleOrder($current_order) {
    return ($current_order == 'asc') ? 'desc' : 'asc';
}

// Display the shifts management interface
echo '
    <div class="page">
        <div class="edit-user-page" id="shifts_table" style="display: none; align-items: start; height: 20px !important; margin-bottom: 0;">
        <form id="shift-filter-form" method="GET" action="" style="width: 90vw;">
            <input id="lang" name="lang" value="' . htmlspecialchars($selectedLanguage ?? "English") . '" hidden>
            <input id="page" name="page" value="' . htmlspecialchars($_GET['page'] ?? "admin") . '" hidden>
            <input id="sub_page" name="sub_page" value="' . htmlspecialchars($_GET['sub_page'] ?? "shifts") . '" hidden>
            <input id="sort_by" name="sort_by" value="' . htmlspecialchars($sort_by) . '" hidden>
            <input id="sort_order" name="sort_order" value="' . htmlspecialchars($sort_order) . '" hidden>
            <input style="width: 12.5%; margin-bottom: 8px !important;" type="text" id="worker_id_input" placeholder="' . htmlspecialchars($lang_data[$selectedLanguage]["worker_id"] ?? "Enter Worker ID") . '" style="margin-right: 15px;" />
            <select style="width: 77%; margin-bottom: 8px !important;" id="worker_id" name="user_guid" required>';
$selected_worker_id = isset($_GET['user_guid']) ? $_GET['user_guid'] : '';

echo '
                <option value="" disabled ' . ($selected_worker_id ? "" : "selected") . '>' . htmlspecialchars($lang_data[$selectedLanguage]['select_user'] ?? "Select user") . '</option>';

$sql = "
                    SELECT users.user_guid, users.first_name, users.last_name, workers.worker_id
                    FROM users
                    JOIN workers ON users.user_guid = workers.user_guid
                    ORDER BY workers.worker_id
                ";

$stmt = $MySQL->getConnection()->prepare($sql);

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $user_guid = htmlspecialchars($row['user_guid']);
            $worker_id = htmlspecialchars($row['worker_id']);
            $full_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
            $selected = ($user_guid == $selected_worker_id) ? 'selected' : '';
            echo '<option data-worker-id="' . $worker_id . '" value="' . $user_guid . '" ' . $selected . '>#' . $worker_id . ' - ' . $row['first_name'] . '</option>';
        }
        $stmt->close();
    } else {
        echo '<option disabled>' . htmlspecialchars($lang_data[$selectedLanguage]['error_fetching_workers'] ?? 'Error fetching workers') . '</option>';
        $stmt->close();
    }
} else {
    echo '<option disabled>' . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error') . '</option>';
}
echo '
            </select>
            <select style="width: 44.8%; margin-bottom: 6px !important;" id="month" name="month">';

// Generate month options
for ($m = 1; $m <= 12; $m++) {
    $monthValue = str_pad($m, 2, '0', STR_PAD_LEFT);
    $selected = (isset($_GET['month']) && $_GET['month'] == $monthValue) ? 'selected' : ((!isset($_GET['month']) && $monthValue == date("m")) ? 'selected' : '');
    echo '<option value="' . $monthValue . '" ' . $selected . '>' . htmlspecialchars($lang_data[$selectedLanguage]["months"][$monthValue]) . '</option>';
}

echo '
            </select>
            
            <select style="width: 44.8%;" id="year" name="year">';

// Generate year options
$currentYear = date('Y');
for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
    $selected = (isset($_GET['year']) && $_GET['year'] == $y) ? 'selected' : (($y == $currentYear && !isset($_GET['year'])) ? 'selected' : '');
    echo '<option value="' . $y . '" ' . $selected . '>' . $y . '</option>';
}

echo '
            </select><br>
            <button id="btn-update" type="submit" style="width: 56.7%; cursor: pointer; font-size: 0.85rem;">
                ' . htmlspecialchars($lang_data[$selectedLanguage]['view_shifts'] ?? 'View Shifts') . '
            </button>';
if($_SESSION['loggedIn']['group'] === 'admins')
{
    echo '
                <button onclick=\'location.href= "index.php?lang=' . $selectedLanguage . '&page=admin&sub_page=shifts&action=add"\' type="button" class="view_shifts" id="btn-update" style="width: 33%; cursor: pointer; font-size: 0.80rem;">
                    ' . htmlspecialchars($lang_data[$selectedLanguage]['add_shift'] ?? 'Add shift') . '
                </button>
                ';
}
$url  = 'index.php?lang=' . urlencode($selectedLanguage);
foreach($_GET as $key => $value) {
    if($key == 'lang' || $key == 'sort_by'|| $key == 'sort_order') continue;
    $url .= '&' . urlencode($key) . '=' . urlencode($value);
}

$approve_all_url = preg_replace('/(sub_page=)[^&]+/', 'sub_page=shifts&action=approve&shift_guid=all', $url);
echo '
        </form>
        </div>
        <div class="edit_view_shifts" style="align-items: start;">
            <table>
                <thead>
                    <tr>
                        <th><a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'date', 'sort_order' => toggleOrder($sort_order)])) . '">' . htmlspecialchars($lang_data[$selectedLanguage]["date"] ?? 'Date') . '</a></th>
                        <th><a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'start_end', 'sort_order' => toggleOrder($sort_order)])) . '">' . htmlspecialchars($lang_data[$selectedLanguage]["start_end"] ?? 'Start | End') . '</a></th>
                        <th><a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'total', 'sort_order' => toggleOrder($sort_order)])) . '">' . htmlspecialchars($lang_data[$selectedLanguage]["total"] ?? 'Total') . '</a></th>
                        <th><a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'site_name', 'sort_order' => toggleOrder($sort_order)])) . '">' . htmlspecialchars($lang_data[$selectedLanguage]["site_name"] ?? 'Site') . '</a></th>
                        <th style="white-space: nowrap; width: 1%; max-width: max-content;">';
                            if(isset($_GET['user_guid']))
                                echo '<a href="'.$approve_all_url.'"><img class="manage_shift_btn" src="img/approve_all.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['approve_all'] ?? 'Approve All') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['approve_all'] ?? 'Approve All') . '"></a><div style="display: inline-flex;" id="btn_placeholder"></div><div style="display: inline-flex;" id="btn_placeholder"></div>';
                            else
                                echo '
                                    <div style="visibility: visible; border: 0 solid black; display: inline-flex;" id="btn_placeholder"></div>
                                    <div style="visibility: visible; border: 0 solid black; display: inline-flex;" id="btn_placeholder"></div>
                                    <div style="visibility: visible; border: 0 solid black; display: inline-flex;" id="btn_placeholder"></div>
                                    ';
                            echo '
                        </th>
                    </tr>
                </thead>';

// Call showShifts with sorting parameters
if (isset($_GET['user_guid'])) {
    echo showShifts($_GET['sort_by'] ?? 'date', $_GET['sort_order'] ?? 'asc');
}

echo '
            </table>
        </div>
    </div>
    <script>
    document.getElementById("worker_id_input").addEventListener("input", function() {
        var inputVal = this.value.trim();
        var select = document.getElementById("worker_id");
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
    
    // Show the table after the bottom menu has rendered
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("shifts_table").style.display = "";
    });
    </script>
    
    ';
?>

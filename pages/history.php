<?php
// pages/history.php

global $selectedLanguage, $lang_data, $MySQL;
if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Set default month and year if not provided
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Define sorting parameters (default is by date, ascending)
$sort_by = $_GET['sort_by'] ?? 'guid';
$sort_order = $_GET['sort_order'] ?? 'desc'; // Default to ascending

// Function to toggle the sorting order for each column
function toggleOrder($current_order) {
    return ($current_order == 'asc') ? 'desc' : 'asc';
}

// Display the history page content
echo '
<div class="page">
    <form id="shift-filter-form" method="GET" action="">
        <input name="lang" value="' . htmlspecialchars($selectedLanguage) . '" type="hidden">
        <input name="page" value="' . htmlspecialchars($_GET['page']) . '" type="hidden">
        <input name="sort_by" value="' . htmlspecialchars($sort_by) . '" type="hidden">
        <input name="sort_order" value="' . htmlspecialchars($sort_order) . '" type="hidden">
        <select id="month" name="month" onchange="document.getElementById(\'shift-filter-form\').submit();">';

// Generate month options
for ($m = 1; $m <= 12; $m++) {
    $monthValue = str_pad($m, 2, '0', STR_PAD_LEFT);
    $selected = ($monthValue == $month) ? 'selected' : '';
    echo '<option value="' . $monthValue . '" ' . $selected . '>' . htmlspecialchars($lang_data[$selectedLanguage]["months"][$monthValue]) . '</option>';
}

echo '
        </select>
        <select id="year" name="year" onchange="document.getElementById(\'shift-filter-form\').submit();">';

// Generate year options
$currentYear = date('Y');
for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
    $selected = ($y == $year) ? 'selected' : '';
    echo '<option value="' . $y . '" ' . $selected . '>' . $y . '</option>';
}

echo '
        </select>
    </form>
    <div class="history_page" style="height: 43.5vh; display: none" id="history_table">
        <table>
            <thead>
                <tr>
                    <th style="font-size: 0.76rem;">
                        <a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'date', 'sort_order' => toggleOrder($sort_order)])) . '">
                            ' . htmlspecialchars($lang_data[$selectedLanguage]["date"] ?? "Date") . '
                        </a>
                    </th>
                    <th style="font-size: 0.76rem;">
                        <a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'start_end', 'sort_order' => toggleOrder($sort_order)])) . '">
                            ' . htmlspecialchars($lang_data[$selectedLanguage]["start_end"] ?? "Start | End") . '
                        </a>
                    </th>
                    <th style="font-size: 0.76rem;">
                        <a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'total', 'sort_order' => toggleOrder($sort_order)])) . '">
                            ' . htmlspecialchars($lang_data[$selectedLanguage]["total"] ?? "Total") . '
                        </a>
                    </th>
                    <th style="font-size: 0.76rem;">
                        <a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'site_name', 'sort_order' => toggleOrder($sort_order)])) . '">
                            ' . htmlspecialchars($lang_data[$selectedLanguage]["site_name"] ?? "Site") . '
                        </a>
                    </th>';
if ($_SESSION['loggedIn']['group'] == 'admins' || $_SESSION['loggedIn']['group'] == 'site_managers') {
    if (($_GET['page'] == 'history' || $_GET['sub_page'] == 'shifts') && !isset($_GET['worker_id'])) {
        echo '
                        <th>
                            <a href="index.php?' . http_build_query(array_merge($_GET, ['sort_by' => 'worker_name', 'sort_order' => toggleOrder($sort_order)])) . '">
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["worker_name"] ?? "Worker Name") . '
                            </a>
                        </th>';
    }
    echo '
                        <th style="white-space: nowrap; width: 1%; max-width: max-content;">
                            ' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? "Actions") . '
                        </th>';
}
echo '
                </tr>
            </thead>
            <tbody>';
echo showShifts($sort_by, $sort_order);
echo '
            </tbody>
        </table>
    </div>
</div>

<script>

// Show the table after the bottom menu has rendered
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("history_table").style.display = "";
    });
    
</script>';
?>

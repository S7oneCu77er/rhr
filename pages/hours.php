<?php
// pages/hours.php

global $lang_data, $MySQL, $selectedLanguage;

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

if ($_SESSION['loggedIn']['group'] !== 'workers' && $_SESSION['loggedIn']['group'] !== 'drivers') {
    echo "<script>showError('You are not supposed to be here!');</script>";
}

$userGuid = $_SESSION['loggedIn']['user_guid'];

// Display the hours page content

echo '
<div class="page">
    <div class="history_page" style="height: 47.5vh; display: none; margin-bottom: 6px;" id="hours_tables">
        <table>
            <thead>
                <tr>';
                    switch($_SESSION['loggedIn']['group'])
                    {
                        case "workers":
                        case "drivers":
                        case "site_managers":
                        default:
                        {
                            echo '
                            <th>' . htmlspecialchars($lang_data[$selectedLanguage]["date"]) . '</th>
                            <th>' . htmlspecialchars($lang_data[$selectedLanguage]["start_end"] ?? 'Start | End') . '</th>
                            <th>' . htmlspecialchars($lang_data[$selectedLanguage]["total"] ?? 'Total') . '</th>
                            <th>' . htmlspecialchars($lang_data[$selectedLanguage]["site"] ?? 'Site') . '</th>
                            ';
                        }
                        break;
                    }
echo           '</tr>
            </thead>
            <tbody>';
//if ($_SESSION['loggedIn']['group'] === 'workers' || $_SESSION['loggedIn']['group'] === 'drivers')
    echo showShifts();

echo '
            </tbody>
        </table>
    </div>';

//if($_SESSION['loggedIn']['group'] === "workers") {
//if(isWorker($_SESSION['loggedIn']['user_guid']) && ( $_SESSION['loggedIn']['group'] === "workers" || $_SESSION['loggedIn']['group'] === "drivers" ) ) {
if(true) {
    echo '
    <div class="hours_page">
        <div id="shift_start">
            <button onclick="startShiftProcess()">' . htmlspecialchars($lang_data[$selectedLanguage]['start_work']) . '</button>
        </div>
        <div id="shift_end">
            <button onclick="endShiftProcess()">' . htmlspecialchars($lang_data[$selectedLanguage]['end_work']) . '</button>
        </div>
    </div>';
}
echo '</div>
<script>

// Show the table after the bottom menu has rendered
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("hours_table").style.display = "";
    });
    
</script>';
?>
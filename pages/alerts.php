<?php
// pages/alerts.php

global $lang_data, $selectedLanguage;
if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Make sure user is logged in and is admin
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access Denied') . "', true);</script>";
    exit();
}

$alerts = getAllAlerts();

// Display the alerts on the page
echo "
<div class='page'>
    <div class='alert-list' id='alerts_table' style='display: none'>
        <table>
            <thead>
                <tr>
                    <th style='font-weight: bolder; font-size: 0.85rem;'>
                        " . htmlspecialchars($lang_data[$selectedLanguage]["alerts"] ?? "Alerts") . '
                    </th>
                    <th style="white-space: nowrap; width: 1%; max-width: max-content; font-weight: bolder; font-size: 0.85rem;">
                        ' . htmlspecialchars($lang_data[$selectedLanguage]["action"] ?? "Action") . "
                    </th>
                </tr>
            </thead>
            <tbody>
            <tr>
                <td colspan='2' style='font-weight: bolder; height: 10px; padding: 8px; font-size: 0.80rem; width: 100%; background: linear-gradient(180deg, lightcoral, indianred);'>
                    " . htmlspecialchars($lang_data[$selectedLanguage]["found_alerts"] ?? "Found") . " " . count($alerts) . " " . htmlspecialchars($lang_data[$selectedLanguage]["alerts"] ?? "alerts") . ".
                </td>
            </tr>";

if (count($alerts) > 0) {
    foreach ($alerts as $alert) {
        $td = "<td>";
        $action_td = "<td style='white-space: nowrap;' id='action_td'>";
        if ($alert["level"] == "warning") {
            $td = "<td id='warning_td'>";
            $action_td = "<td style='white-space: nowrap;' id='action_warning_td'>";
        }
        if (isset($alert['image'])) {
            $display = $alert['image'];
            $link_style = "";
        } else {
            $display = htmlspecialchars($lang_data[$selectedLanguage]["view_details"] ?? "Details");
            $link_style = "style='margin-top: 13px'";
        }

        echo "
                <tr>
                    {$td}
                        " . htmlspecialchars($alert['description']) . "
                    </td>
                    {$action_td}
                        <a {$link_style} href='" . htmlspecialchars($alert['action']) . "'>{$display}</a>
                    </td>
                </tr>";
    }
} else {
    echo "
                <tr>
                    <td colspan='2'>
                        " . htmlspecialchars($lang_data[$selectedLanguage]["no_alerts"] ?? "No alerts found") . "
                    </td>
                </tr>";
}

echo "
            </tbody>
        </table>
    </div>
</div>
<script>

// Show the table after the bottom menu has rendered
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('alerts_table').style.display = '';
});

</script>
";

?>

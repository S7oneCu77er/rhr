<?php
// pages/dashboard.php

global $lang_data, $selectedLanguage, $MySQL;

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('Access denied.', true);</script>";
    exit();
}

// Function to get the total available workers (not on relief or relief period ended)
function countAvailableWorkers()
{
    global $MySQL;
    $total_available_workers = 0;
    $query = "
        SELECT COUNT(*) AS total_available_workers
        FROM workers
        WHERE on_relief = 0
        OR (on_relief = 1 AND relief_end_date < CURDATE())
    ";

    $stmt = $MySQL->getConnection()->prepare($query);
    $stmt->execute();
    $stmt->bind_result($total_available_workers);
    $stmt->fetch();
    $stmt->close();

    return $total_available_workers;
}

// Function to get the total assigned workers (assigned to a valid assignment)
function countAssignedWorkers()
{
    global $MySQL;
    $total_workers_with_valid_assignment = 0;
    $query = "
        SELECT COUNT(DISTINCT w.worker_id) AS total_workers_with_valid_assignment
        FROM workers w
        JOIN shift_assignment_workers saw ON w.user_guid = saw.user_guid
        JOIN shift_assignments sa ON saw.assignment_guid = sa.assignment_guid
        WHERE (w.on_relief = 0 OR (w.on_relief = 1 AND w.relief_end_date < CURDATE()))
        AND CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date
    ";

    $stmt = $MySQL->getConnection()->prepare($query);
    $stmt->execute();
    $stmt->bind_result($total_workers_with_valid_assignment);
    $stmt->fetch();
    $stmt->close();

    return $total_workers_with_valid_assignment;
}

// Function to count all sites with a site manager assigned and/or site owner set
function countSitesWithManagerOrOwner() {
    global $MySQL;
    $total_sites_with_manager_or_owner = 0;
    $query = "
        SELECT COUNT(DISTINCT s.site_guid) AS total_sites_with_manager_or_owner
        FROM sites s
        LEFT JOIN site_managers sm ON s.site_guid = sm.site_guid
        WHERE sm.user_guid IS NOT NULL OR s.site_owner_guid IS NOT NULL
        AND s.site_address != ''
        AND s.site_guid > 0
    ";

    $stmt = $MySQL->getConnection()->prepare($query);
    $stmt->execute();
    $stmt->bind_result($total_sites_with_manager_or_owner);
    $stmt->fetch();
    $stmt->close();

    return $total_sites_with_manager_or_owner;
}

// Function to count sites with a valid shift assignment and a site manager assigned and/or site owner set
function countSitesWithValidShiftAssignment() {
    global $MySQL;
    $total_sites_with_valid_assignment = 0;
    $query = "
        SELECT COUNT(DISTINCT s.site_guid) AS total_sites_with_valid_assignment
        FROM sites s
        JOIN site_managers sm ON s.site_guid = sm.site_guid
        JOIN shift_assignments sa ON s.site_guid = sa.site_guid
        WHERE (sm.user_guid IS NOT NULL OR s.site_owner_guid IS NOT NULL)
        AND s.site_address != ''
        AND CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date
        AND s.site_guid > 0
    ";

    $stmt = $MySQL->getConnection()->prepare($query);
    $stmt->execute();
    $stmt->bind_result($total_sites_with_valid_assignment);
    $stmt->fetch();
    $stmt->close();

    return $total_sites_with_valid_assignment;
}

function countTotalWorkersRequired()
{
    global $MySQL;
    $total_required_workers = 0;

    $query = "
        SELECT sa.workers
        FROM shift_assignments sa
        WHERE CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date
    ";

    $stmt = $MySQL->getConnection()->prepare($query);
    $stmt->execute();
    $stmt->bind_result($workers_json);

    while ($stmt->fetch()) {
        $workers_array = json_decode($workers_json, true);
        if (is_array($workers_array)) {
            $total_required_workers += array_sum(array_map('intval', $workers_array));
        }
    }

    $stmt->close();

    return $total_required_workers;
}

// Function to count the number of workers assigned to valid shift assignments
function countAssignedWorkersForValidShifts()
{
    global $MySQL;
    $total_assigned_workers = 0;

    $query = "
        SELECT COUNT(DISTINCT saw.user_guid) AS total_assigned_workers
        FROM shift_assignment_workers saw
        JOIN shift_assignments sa ON saw.assignment_guid = sa.assignment_guid
        WHERE CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date
    ";

    $stmt = $MySQL->getConnection()->prepare($query);
    $stmt->execute();
    $stmt->bind_result($total_assigned_workers);
    $stmt->fetch();
    $stmt->close();

    return $total_assigned_workers;
}

// Get total available and assigned workers
$total_available_workers = countAvailableWorkers();
$total_assigned_workers = countAssignedWorkers();

// Calculate percentage
if ($total_available_workers > 0) {
    $percentage_assigned = (($total_assigned_workers / $total_available_workers) * 100);
} else {
    $percentage_assigned = 0;
}

// Get total sites with manager or owner and total sites with valid shift assignments
$total_sites_with_manager_or_owner = countSitesWithManagerOrOwner();
$total_sites_with_valid_assignment = countSitesWithValidShiftAssignment();

// Calculate percentage
if ($total_sites_with_manager_or_owner > 0) {
    $percentage_valid_assignments = ($total_sites_with_valid_assignment / $total_sites_with_manager_or_owner) * 100;
} else {
    $percentage_valid_assignments = 0;
}


$countTotalWorkersRequired = countTotalWorkersRequired();
$countAssignedWorkersForValidShifts = countAssignedWorkersForValidShifts();

// Calculate percentage
if ($countTotalWorkersRequired > 0) {
    $percentage_assigned_workers = ($countAssignedWorkersForValidShifts / $countTotalWorkersRequired) * 100;
} else {
    $percentage_assigned_workers = 0;
}

$workers_percent        = number_format($percentage_assigned, 0);
$sites_percent          = number_format($percentage_valid_assignments, 0);
$assignments_percent    = number_format($percentage_assigned_workers, 0);
?>

<style>
    table {
        width: 100%;
        height: 100%;
    }

    td {
        text-align: center; /* Center horizontally */
        vertical-align: middle; /* Center vertically */
    }
</style>

<div class="page">
    <div class="site-list">
        <table style="height: 95%; margin-top: 8px;">
            <tr>
                <td style="border: 0;" colspan="3">
                    <?php echo htmlspecialchars($lang_data[$selectedLanguage]['dashboard'] ?? 'Dashboard'); ?><br><br>
                </td>
            </tr>
            <tr>
                <td style="border: 0">
                    <div class="gauge-wrapper">
                        <div class="gauge-container" id="container1">
                            <div class="gauge">
                                <div class="gauge-fill" id="gaugeFill1"></div>
                                <div class="gauge-mask"></div>
                                <div class="marker-container">
                                    <div class="marker" data-position="0"></div>
                                    <div class="marker" data-position="10"></div>
                                    <div class="marker" data-position="20"></div>
                                    <div class="marker" data-position="30"></div>
                                    <div class="marker" data-position="40"></div>
                                    <div class="marker" data-position="50"></div>
                                    <div class="marker" data-position="60"></div>
                                    <div class="marker" data-position="70"></div>
                                    <div class="marker" data-position="80"></div>
                                    <div class="marker" data-position="90"></div>
                                    <div class="marker" data-position="100"></div>
                                </div>
                                <div class="gauge-overlay"></div>

                            </div>
                            <div class="gauge-value" id="gaugeValue1">0%</div>
                            <div class="needle" id="needle1"></div>
                        </div>
                    </div>
                    <br>
                    <?php echo htmlspecialchars($lang_data[$selectedLanguage]['assignments'] ?? 'Assignments'); ?>
                </td>
                <td style="border: 0">
                    <div class="gauge-wrapper">
                        <div class="gauge-container" id="container2">
                            <div class="gauge">
                                <div class="gauge-fill" id="gaugeFill2"></div>
                                <div class="gauge-mask"></div>
                                <div class="marker-container">
                                    <div class="marker" data-position="0"></div>
                                    <div class="marker" data-position="10"></div>
                                    <div class="marker" data-position="20"></div>
                                    <div class="marker" data-position="30"></div>
                                    <div class="marker" data-position="40"></div>
                                    <div class="marker" data-position="50"></div>
                                    <div class="marker" data-position="60"></div>
                                    <div class="marker" data-position="70"></div>
                                    <div class="marker" data-position="80"></div>
                                    <div class="marker" data-position="90"></div>
                                    <div class="marker" data-position="100"></div>
                                </div>
                                <div class="gauge-overlay"></div>

                            </div>
                            <div class="gauge-value" id="gaugeValue2">0%</div>
                            <div class="needle" id="needle2"></div>
                        </div>
                    </div>
                    <br>
                    <?php echo htmlspecialchars($lang_data[$selectedLanguage]['sites'] ?? 'Sites'); ?>
                </td>

                <td style="border: 0">
                    <div class="gauge-wrapper">
                        <div class="gauge-container" id="container3">
                            <div class="gauge">
                                <div class="gauge-fill" id="gaugeFill3"></div>
                                <div class="gauge-mask"></div>
                                <div class="marker-container">
                                    <div class="marker" data-position="0"></div>
                                    <div class="marker" data-position="10"></div>
                                    <div class="marker" data-position="20"></div>
                                    <div class="marker" data-position="30"></div>
                                    <div class="marker" data-position="40"></div>
                                    <div class="marker" data-position="50"></div>
                                    <div class="marker" data-position="60"></div>
                                    <div class="marker" data-position="70"></div>
                                    <div class="marker" data-position="80"></div>
                                    <div class="marker" data-position="90"></div>
                                    <div class="marker" data-position="100"></div>
                                </div>
                                <div class="gauge-overlay"></div>

                            </div>
                            <div class="gauge-value" id="gaugeValue3">0%</div>
                            <div class="needle" id="needle3"></div>
                        </div>
                    </div>
                    <br>
                    <?php echo htmlspecialchars($lang_data[$selectedLanguage]['workers'] ?? 'Workers'); ?>
                </td>
            </tr>
            <?php
            $align  = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "left" : "right";
            echo '
            
            <tr style="height: 50%;">
                <td colspan="3" style="border: 0;">
                    <table style="border-collapse: collapse;">
                        <tr>
                            <td colspan="6" style="border: 0;">
                                <table style="border-collapse: collapse; height: 1px; margin: 0; padding: 0;">
                                    <tr>
                                        <td>
                                            <div style="display: flex; flex-direction: row; justify-content: space-around; align-items: center;">
                                                <div style="border-radius:8px; background: radial-gradient(circle, rgba(255, 255, 255, 1), rgba(50, 50, 50, 0.7));">
                                                    <div id="smart_report_buttons" style="margin: 0;">
                                                        Report #1
                                                    </div>
                                                </div>
                                                <div style="border-radius:8px; background: radial-gradient(circle, rgba(255, 255, 255, 1), rgba(50, 50, 50, 0.7));">
                                                    <div id="smart_report_buttons" style="margin: 0;">
                                                        Report #2
                                                    </div>
                                                </div>
                                                <div style="border-radius:8px; background: radial-gradient(circle, rgba(255, 255, 255, 1), rgba(50, 50, 50, 0.7));">
                                                    <div id="smart_report_buttons" style="margin: 0;">
                                                        Report #3
                                                    </div>
                                                </div>
                                            </div>
                                            <br>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="height: 20px; width:33%; text-align: center; border-'.$align.': 2px solid red; border-bottom: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0; white-space: nowrap;">
                                            Cars required
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0; white-space: nowrap;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td colspan="2" style="height: 20px; width:34%; text-align: center; border: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0; white-space: nowrap;">
                                            Missing owner/manager
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0; white-space: nowrap;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td colspan="2" style="height: 20px; width:33%; text-align: center; border-'.($align=="left" ? "right" : "left").': 2px solid red; border-bottom: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0; white-space: nowrap;">
                                            Workers on relief
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0; white-space: nowrap;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="height: 20px; width:33%; text-align: left; border-'.$align.': 2px solid red; border-bottom: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0;">
                                            Drivers required
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td colspan="2" style="height: 20px; width:34%; text-align: left; border: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0;">
                                            Missing address
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td colspan="2" style="height: 20px; width:33%; text-align: left; border-'.($align=="left" ? "right" : "left").': 2px solid red; border-bottom: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0;">
                                            Assigned workers
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="height: 20px; width:33%; text-align: left; border-'.$align.': 2px solid red; border-bottom: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0;">
                                            Missing license
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td colspan="2" style="height: 20px; width:34%; text-align: left; border: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0;">
                                            Open Assignments
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td colspan="2" style="height: 20px; width:33%; text-align: left; border-'.($align=="left" ? "right" : "left").': 2px solid red; border-bottom: 0; padding: 0 10px 0 10px;">
                                <table style="width:100%; margin: 0;">
                                    <tr>
                                        <td style=" text-align: '.($align=="left" ? "right" : "left").'; border: 0;">
                                            Unassigned workers
                                        </td>
                                        <td style="text-align: '.$align.'; border: 0;">
                                            DATA
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>';
            ?>
                        <tr>
                            <td colspan="6" style="z-index: 4; padding: 20px; border: 0; background-image: url('../img/ai.png'); background-size: 122px 122px; background-position: center; background-repeat: no-repeat;">
                                <div>
                                    <img src="../img/ai_assign.png" style="cursor: pointer; z-index: 4; position: relative; left: 4px; top: -2.5px; width: 50px; height: 50px;">
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</div>



<script>
    function getColorForPercentage(percentage) {
        if (percentage <= 10) {
            return '#8B0000'; // Dark red for 0% to 10%
        } else if (percentage <= 20) {
            return '#c0392b'; // #c0392b for 10% to 20%
        } else if (percentage <= 30) {
            return '#e74c3c'; // #e74c3c for 20% to 30%
        } else if (percentage <= 40) {
            return '#FF6000'; // #FF6000 for 30% to 40%
        } else if (percentage <= 50) {
            return '#FFA500'; // #FFA500 for 40% to 50%
        } else if (percentage <= 60) {
            return '#FFc900'; // #FFc900 for 50% to 60%
        } else if (percentage <= 70) {
            return 'yellow';  // Yellow for 60% to 70%
        } else if (percentage <= 80) {
            return '#ADFF2F'; // #ADFF2F for 70% to 80%
        } else if (percentage <= 90) {
            return '#4caf50'; // #4caf50 for 80% to 90%
        } else if (percentage <= 100) {
            return 'green';   // Green for 90% to 100%
        }
    }

    function updateGauge(percentage, gaugeFillId, gaugeValueId, needleId, containerId) {
        const gaugeFill = document.getElementById(gaugeFillId);
        const gaugeValue = document.getElementById(gaugeValueId);
        const needle = document.getElementById(needleId);
        const container = document.getElementById(containerId);

        // Rotate the gauge fill based on percentage (180 degrees max for half-circle)
        let angle = (percentage / 100) * 180 - 90;
        gaugeFill.style.transform = `rotate(${angle}deg)`;
        gaugeValue.textContent = `${percentage}%`;

        // Move the needle
        needle.style.transform = `rotate(${angle}deg)`;

        // Get the color based on the percentage
        let color = getColorForPercentage(percentage);

        // Apply the color to the border and box shadow
        container.style.borderColor = color;
        container.style.boxShadow = `0 0 20px ${color}`;
    }

    // Update all gauges
    const percent_1 = <?php echo $assignments_percent; ?>;
    const percent_2 = <?php echo $sites_percent; ?>;
    const percent_3 = <?php echo $workers_percent; ?>;

    updateGauge(percent_1, 'gaugeFill1', 'gaugeValue1', 'needle1', 'container1');
    updateGauge(percent_2, 'gaugeFill2', 'gaugeValue2', 'needle2', 'container2');
    updateGauge(percent_3, 'gaugeFill3', 'gaugeValue3', 'needle3', 'container3');
</script>

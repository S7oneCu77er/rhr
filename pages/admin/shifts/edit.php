<?php
// pages/admin/shifts/edit.php

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

// Ensure the user has the correct role
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<div class='page'><script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script></div>";
    exit();
}

// Validate and sanitize the shift_guid from GET
if (isset($_GET['shift_guid']) || isset($_GET['user_guid'])) {
    $shift_guid = intval($_GET['shift_guid']);
    $user_guid  = intval($_GET['user_guid']);
} else {
    echo "<div class='page'><script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "');</script></div>";
    exit();
}

// Fetch the shift details from the database
$stmt = $MySQL->getConnection()->prepare("
    SELECT s.shift_guid, s.shift_start, s.shift_end, s.status, s.site_guid, s.status, u.user_guid, si.site_name
    FROM shifts s
    JOIN users u ON s.user_guid = u.user_guid
    JOIN sites si ON s.site_guid = si.site_guid
    WHERE s.shift_guid = ?
");
$stmt->bind_param("i", $shift_guid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='page'><script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['shift_not_found'] ?? 'Shift not found.').$shift_guid . "', true);</script></div>";
    return;
}

$shift = $result->fetch_assoc();

if ($_SESSION['loggedIn']['group'] === 'site_managers' && (!isSiteManagerForSite($shift['site_guid'], $_SESSION['loggedIn']['user_guid']) || $shift['status'] != 'pending')) {
    echo "<div class='page'>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script></div>";
    return;
}


$stmt->close();

// Handle form submission to update the shift
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shift_start_date   = $_POST['shift_start_date'] ?? '';
    $shift_start_time   = $_POST['shift_start_time'] ?? '';
    $shift_end_date     = $_POST['shift_end_date'] ?? null;
    $shift_end_time     = $_POST['shift_end_time'] ?? null;
    $status             = $_POST['status'] ?? "pending";
    $site_guid          = intval($_POST['site_guid']);

    // Combine date and time inputs for start and end
    $shift_start = $shift_start_date . ' ' . $shift_start_time;
    $shift_end = ($shift_end_date && $shift_end_time) ? ($shift_end_date . ' ' . $shift_end_time) : null;

    // Update the shift in the database
    $stmt = $MySQL->getConnection()->prepare("
        UPDATE shifts
        SET shift_start = ?, shift_end = ?, site_guid = ?, status = ?
        WHERE shift_guid = ?
    ");
    $stmt->bind_param("ssisi", $shift_start, $shift_end, $site_guid, $status, $shift_guid);

    if ($stmt->execute()) {
        echo "<div class='page'><script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['shift_updated_success'] ?? 'Shift updated successfully.') . "', true);</script></div>";
        //echo '<script>window.history.back();</script>';
        return;
    } else {
        echo "<div class='page'><script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script></div>";
    }

    $stmt->close();
}

// Prepare the values for start and end date-time fields
$shift_start_date = date('Y-m-d', strtotime($shift['shift_start']));
$shift_start_time = date('H:i', strtotime($shift['shift_start']));
$shift_end_date = $shift['shift_end'] ? date('Y-m-d', strtotime($shift['shift_end'])) : '';
$shift_end_time = $shift['shift_end'] ? date('H:i', strtotime($shift['shift_end'])) : '';
$status = $shift['status'];

// Display the edit form
echo '
<div class="page">
    <h2 style="margin: 0;">' . htmlspecialchars($lang_data[$selectedLanguage]['edit_shift'] ?? 'Edit Shift') . '</h2>
    <div class="edit-house-page">
    <form method="POST">
     <table style="margin-top: 0;">
                <tbody>
                    <tr>
                        <td colspan="2">
                            <label for="status">' . htmlspecialchars($lang_data[$selectedLanguage]['status'] ?? 'Status') . ':</label>
                            <select onchange="this.style.background=(this.value===\'approved\' ? \'lightgreen\' : \'lightyellow\');" style="height: 30px; background: '.($status == "pending" ? "lightyellow" : "lightgreen").'; width: 90%;" id="status" name="status">
                                <option value="pending"'.($status == "pending" ? " selected" : "").' style="background-color: lightyellow;">' . htmlspecialchars($lang_data[$selectedLanguage]['pending'] ?? 'Pending') . '</option>
                                <option value="approved"'.($status == "approved" ? " selected" : "").' style="background-color: lightgreen;">' . htmlspecialchars($lang_data[$selectedLanguage]['approved'] ?? 'Approved') . '</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="1" style="width: 50%;">
                            <label for="shift_start_date">' . htmlspecialchars($lang_data[$selectedLanguage]['shift_start_date'] ?? 'Shift Start Date') . ':</label>
                            <input type="date" id="shift_start_date" name="shift_start_date" value="' . htmlspecialchars($shift_start_date) . '" required>
                        </td>
                        <td colspan="1" style="width: 50%;">
                            <label for="shift_start_time">' . htmlspecialchars($lang_data[$selectedLanguage]['shift_start_time'] ?? 'Shift Start Time') . ':</label>
                            <input type="time" id="shift_start_time" name="shift_start_time" value="' . htmlspecialchars($shift_start_time) . '" required><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="shift_end_date">' . htmlspecialchars($lang_data[$selectedLanguage]['shift_end_date'] ?? 'Shift End Date') . ':</label>
                            <input type="date" id="shift_end_date" name="shift_end_date" value="' . htmlspecialchars($shift_end_date) . '"><br>
                            <small>' . htmlspecialchars($lang_data[$selectedLanguage]['leave_blank_ongoing'] ?? 'Leave blank if shift is ongoing') . '</small>
                        </td>
                        <td>
                            <label for="shift_end_time">' . htmlspecialchars($lang_data[$selectedLanguage]['shift_end_time'] ?? 'Shift End Time') . ':</label>
                            <input type="time" id="shift_end_time" name="shift_end_time" value="' . htmlspecialchars($shift_end_time) . '"><br>
                            <small>' . htmlspecialchars($lang_data[$selectedLanguage]['leave_blank_ongoing'] ?? 'Leave blank if shift is ongoing') . '</small>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <label for="total_hours">' . htmlspecialchars($lang_data[$selectedLanguage]['total_shift_time'] ?? 'Total shift time') . ':</label>
                            <input style="width: 90%;" type="number" id="total_hours" name="total_hours" value="" disabled><br>

                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <label for="site_guid">' . htmlspecialchars($lang_data[$selectedLanguage]['site_name'] ?? 'Site') . ':</label>
                            <select style="width: 90%;" id="site_guid" name="site_guid" required>';

                    $sql_sites = "SELECT site_guid, site_name FROM sites";
                    $result_sites = $MySQL->getConnection()->query($sql_sites);

                    while ($site = $result_sites->fetch_assoc()) {
                        $selected = ($site['site_guid'] == $shift['site_guid']) ? 'selected' : '';
                        echo '
                                <option value="' . htmlspecialchars($site['site_guid']) . '" ' . $selected . '>' . htmlspecialchars($site['site_name']) . '</option>';
                    }

                    echo '
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        <button style="width: 90%; height: 30px; margin: 0;" id="btn-update" type="submit">' . htmlspecialchars($lang_data[$selectedLanguage]['update_shift'] ?? 'Update Shift') . '</button>
    </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const shiftStartDateInput = document.getElementById("shift_start_date");
    const shiftStartTimeInput = document.getElementById("shift_start_time");
    const shiftEndDateInput = document.getElementById("shift_end_date");
    const shiftEndTimeInput = document.getElementById("shift_end_time");
    const totalHoursInput = document.getElementById("total_hours");

    function calculateTotalTime() {
        const shiftStartDate = shiftStartDateInput.value;
        const shiftStartTime = shiftStartTimeInput.value;
        const shiftEndDate = shiftEndDateInput.value;
        const shiftEndTime = shiftEndTimeInput.value;

        if (shiftStartDate && shiftStartTime && shiftEndDate && shiftEndTime) {
            const shiftStart = new Date(shiftStartDate + "T" + shiftStartTime);
            const shiftEnd = new Date(shiftEndDate + "T" + shiftEndTime);

            const diffInMs = shiftEnd - shiftStart;

            if (diffInMs > 0) {
                const totalMinutes = Math.floor(diffInMs / (1000 * 60)); // Convert ms to minutes
                var hours = Math.floor(totalMinutes / 60).toString(); // Calculate whole hours
                var minutes = (totalMinutes % 60).toString(); // Remaining minutes
                if(minutes.length === 1)
                    minutes = "0" + minutes;

                // Display in numeric format for input fields
                totalHoursInput.value = `${hours}.${minutes}`;
            } else {
                totalHoursInput.value = "0"; // Default to 0 if the calculation is invalid
            }
        } else {
            totalHoursInput.value = "0"; // Default to 0 if end time/date is not set
        }
    }

    // Calculate total time on load
    calculateTotalTime();

    // Add event listeners to the shift end inputs
    shiftEndDateInput.addEventListener("change", calculateTotalTime);
    shiftEndTimeInput.addEventListener("change", calculateTotalTime);
});

</script>
';
?>

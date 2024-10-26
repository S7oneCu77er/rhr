<?php
// pages/admin/shifts/add.php

global $MySQL, $lang_data, $selectedLanguage;

// Make sure the user is logged in and has admin privileges
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn']['group'] !== 'admins') {
    header("Location: index.php");
    exit();
}

// Handle form submission to add a new shift
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shift_start_date = $_POST['shift_start_date'] ?? '';
    $shift_start_time = $_POST['shift_start_time'] ?? '';
    $shift_end_date = $_POST['shift_end_date'] ?? null;
    $shift_end_time = $_POST['shift_end_time'] ?? null;
    $site_guid = intval($_POST['site_guid']);
    $user_guid = intval($_POST['user_guid']);

    // Combine date and time inputs for start and end
    $shift_start = $shift_start_date . ' ' . $shift_start_time;
    $shift_end = ($shift_end_date && $shift_end_time) ? ($shift_end_date . ' ' . $shift_end_time) : null;

    // Insert the new shift into the database
    $stmt = $MySQL->getConnection()->prepare("
        INSERT INTO shifts (user_guid, site_guid, shift_start, shift_end) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiss", $user_guid, $site_guid, $shift_start, $shift_end);

    if ($stmt->execute()) {
        echo "<script>showSuccess('" . htmlspecialchars($lang_data[$selectedLanguage]['shift_added_success'] ?? 'Shift added successfully.') . "');</script>";
        header("Location: index.php?lang=" . urlencode($selectedLanguage) . "&page=admin&sub_page=shifts&user_guid={$user_guid}");
        exit();
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "');</script>";
    }

    $stmt->close();
}

// Prepare default values for form inputs
$shift_start_date = date('Y-m-d');
$shift_start_time = date('H:i');
$shift_end_date = '';
$shift_end_time = '';

// Fetch the list of users and sites
$sql_users = "SELECT user_guid, first_name, last_name FROM users WHERE user_guid > 0 ORDER BY user_guid";
$result_users = $MySQL->getConnection()->query($sql_users);

$sql_sites = "SELECT site_guid, site_name FROM sites WHERE site_guid > 0 ORDER BY site_guid";
$result_sites = $MySQL->getConnection()->query($sql_sites);

// Display the form for adding a new shift
echo '
<div class="page">
    <h2>' . htmlspecialchars($lang_data[$selectedLanguage]['add_shift'] ?? 'Add Shift') . '</h2>
    <div class="edit-house-page">
        <form method="POST" onsubmit="return validateForm();">
            <table style="margin-top: -5px;">
                <tbody>
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
                            <input type="date" id="shift_end_date" name="shift_end_date" value="' . htmlspecialchars($shift_end_date) . '">
                        </td>
                        <td>
                            <label for="shift_end_time">' . htmlspecialchars($lang_data[$selectedLanguage]['shift_end_time'] ?? 'Shift End Time') . ':</label>
                            <input type="time" id="shift_end_time" name="shift_end_time" value="' . htmlspecialchars($shift_end_time) . '">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <label for="user_guid">' . htmlspecialchars($lang_data[$selectedLanguage]['worker'] ?? 'Worker') . ':</label>
                            <select style="width: 91%;" id="user_guid" name="user_guid" required>
                                <option value="" selected disabled>Select a worker</option>';
                                while ($user = $result_users->fetch_assoc()) {
                                    echo '
                                    <option value="' . htmlspecialchars($user['user_guid']) . '">
                                        ' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '
                                    </option>';
                                }
                                echo '
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <label for="site_guid">' . htmlspecialchars($lang_data[$selectedLanguage]['site_name'] ?? 'Site') . ':</label>
                            <select style="width: 91%;" id="site_guid" name="site_guid" required>
                                <option value="" selected disabled>Select a site</option>';
                                while ($site = $result_sites->fetch_assoc()) {
                                    echo '
                                    <option value="' . htmlspecialchars($site['site_guid']) . '">
                                        ' . htmlspecialchars($site['site_name']) . '
                                    </option>';
                                }
                                echo '
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 90%; margin-top: 50px;" id="btn-update" type="submit">' . htmlspecialchars($lang_data[$selectedLanguage]['add_shift'] ?? 'Add Shift') . '</button>
        </form>
        
        <script>
        function validateForm() {
            var worker = document.getElementById("user_guid").value;
            var site = document.getElementById("site_guid").value;
            if (worker === "" || site === "") {
                showError("Please select both a worker and a site.");
                return false; // Prevent form submission
            }
            return true; // Allow form submission
        }
        </script>

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
                const hours = Math.floor(totalMinutes / 60); // Calculate whole hours
                const minutes = totalMinutes % 60; // Remaining minutes

                // Display in numeric format for input fields
                totalHoursInput.value = `${hours}.${minutes}`;
            } else {
                totalHoursInput.value = "0"; // Default to 0 if the calculation is invalid
            }
        } else {
            totalHoursInput.value = "0"; // Default to 0 if end time/date is not set
        }
    }

    // Add event listeners to the shift end inputs
    shiftEndDateInput.addEventListener("change", calculateTotalTime);
    shiftEndTimeInput.addEventListener("change", calculateTotalTime);
});
</script>
';
?>

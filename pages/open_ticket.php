<?php
global $lang_data, $selectedLanguage, $MySQL;

require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and is a worker, driver, or site manager
if (!isset($_SESSION['loggedIn']) || !in_array($_SESSION['loggedIn']['group'], ['workers', 'drivers', 'site_managers'])) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "');</script>";
    exit();
}

$user_guid = $_SESSION['loggedIn']['user_guid'];


// Fetch optional data (assignments, sites, shifts, cars)
$assignments = fetchAssignments($user_guid);
$sites = fetchSites($user_guid);
$shifts = fetchShifts($user_guid);
$cars = fetchCars($user_guid);

// Support reasons/types
$support_types = [
    "Forgot to clock in/out",
    "Problem at work",
    "Problem at house",
    "Vehicle issue",
    "Request for support",
    "General Inquiry",
    "Other"
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $site_guid = $_POST['site_guid'] ?? null;
    $shift_guid = $_POST['shift_guid'] ?? null;
    $assignment_guid = $_POST['assignment_guid'] ?? null;
    $car_guid = $_POST['car_guid'] ?? null;
    $support_type = $_POST['support_type'];

    // Insert the support request into the database
    $stmt = $MySQL->getConnection()->prepare(
        "INSERT INTO support (user_guid, site_guid, shift_guid, assignment_guid, car_guid, support_type) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param("iiiiis", $user_guid, $site_guid, $shift_guid, $assignment_guid, $car_guid, $support_type);
        $stmt->execute();
        echo "<p>Your support ticket has been submitted successfully!</p>";
    } else {
        echo "<p>Error submitting your support ticket. Please try again later.</p>";
    }
}
?>

<div class="page">
    <div class="edit-house-page">
        <h2>Submit a Support Ticket</h2>

        <form method="POST">
            <!-- Support Type -->
            <label for="support_type">Support Reason</label>
            <select name="support_type" id="support_type" required>
                <option value="">Select Reason</option>
                <?php foreach ($support_types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                <?php endforeach; ?>
            </select>
            <br><br>

            <!-- Optional Assignment -->
            <label for="assignment_guid">Related Assignment (Optional)</label>
            <select name="assignment_guid" id="assignment_guid">
                <option value="0">None</option>
                <?php foreach ($assignments as $assignment): ?>
                    <option value="<?php echo htmlspecialchars($assignment['assignment_guid']); ?>">
                        <?php echo htmlspecialchars($assignment['description']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>

            <!-- Optional Site -->
            <label for="site_guid">Related Site (Optional)</label>
            <select name="site_guid" id="site_guid">
                <option value="0">None</option>
                <?php foreach ($sites as $site): ?>
                    <option value="<?php echo htmlspecialchars($site['site_guid']); ?>">
                        <?php echo htmlspecialchars($site['site_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>

            <!-- Optional Shift -->
            <label for="shift_guid">Related Shift (Optional)</label>
            <select name="shift_guid" id="shift_guid">
                <option value="0">None</option>
                <?php foreach ($shifts as $shift): ?>
                    <option value="<?php echo htmlspecialchars($shift['shift_guid']); ?>">
                        <?php echo htmlspecialchars($shift['shift_start'] . ' - ' . $shift['shift_end']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>

            <!-- Optional Car -->
            <label for="car_guid">Related Car (Optional)</label>
            <select name="car_guid" id="car_guid">
                <option value="0">None</option>
                <?php foreach ($cars as $car): ?>
                    <option value="<?php echo htmlspecialchars($car['car_guid']); ?>">
                        <?php echo htmlspecialchars($car['car_model'] . ' (' . $car['car_number_plate'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>

            <button style="width: 89%;" id="btn-update" type="submit">Submit Ticket</button>
        </form>
    </div>
</div>


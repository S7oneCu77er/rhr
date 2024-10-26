<?php
// pages/admin/workers/assign.php

// Include necessary configurations and handlers
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php'; // Ensure language handler is included

global $lang_data, $selectedLanguage, $MySQL;

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

// Get the user GUID from the URL
if (isset($_GET['user_guid'])) {
    $user_guid = intval($_GET['user_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "');</script>";
    exit();
}

// Determine what the assignment is (car, house, or shift assignment)
$assignmentType = $_GET['to'] ?? '';

// If no assignment type, show the default buttons to select assignment type
if (empty($assignmentType)) {
    $link = "index.php?lang=" . urlencode($selectedLanguage) . "&page=admin&sub_page=workers&action=assign&user_guid=$user_guid&to";
    // Check if the worker is on relief
    $onReliefQuery = "
    SELECT on_relief, relief_end_date 
    FROM workers 
    WHERE user_guid = ? 
    AND (on_relief = 1 AND relief_end_date >= CURDATE())
";
    $stmt = $MySQL->getConnection()->prepare($onReliefQuery);
    $stmt->bind_param("i", $user_guid);
    $stmt->execute();
    $reliefStatusResult = $stmt->get_result();

    $isOnRelief = $reliefStatusResult->num_rows > 0;

    if ($isOnRelief) {
        $row = $reliefStatusResult->fetch_assoc();
        $relief_end_date = $row['relief_end_date'] ?? ""; // Assign the relief_end_date to $relief_end_date
    }
    $stmt->close();


    $back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=workers';
    $flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";


    echo '
    <div class="page">
        <div style="margin-top: 25px;">
            <h3 style="margin-bottom: 0px;">
                <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
                ' . htmlspecialchars($lang_data[$selectedLanguage]['select_assignment'] ?? 'Select assignment for worker') . ' #' . getWorkerID($user_guid) . '
            </h3><br>' . getWorkerName($user_guid) . '
            ' . ($isOnRelief ? "<br><span style='color:darkred;'>" . htmlspecialchars($lang_data[$selectedLanguage]['on_relief_until'] ?? 'On Relief until:') . " [{$relief_end_date}]</span>" : "") . '</div>
            <div class="admin_page" style=" height: 40vh;">
                <table>
                    <tbody>
                        <tr>
                            <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]['assign_house'] ?? 'Assign house') . '<a href="' . $link . '=house"><img src="img/houses.png" alt=""></a></div></td>
                            <td>';

                            // Check if worker is on relief
                            $disabledClass = $isOnRelief ? 'admin_page_disabled_div' : ''; // Apply disabled class if on relief
                            $disabledAttr = $isOnRelief ? 'disabled' : ''; // Disable the link if on relief
                            $assign_car_btn = isDriver($user_guid) ? '<div>' . htmlspecialchars($lang_data[$selectedLanguage]['assign_to_car'] ?? 'Assign car') . '<a href="' . $link . '=car"><img src="img/cars.png" alt=""></a></div>' : "";

                            // Optionally show a disabled version for visual indication
                            if ($isOnRelief) {
                                echo '<div id="admin_page_disabled_div">' . htmlspecialchars($lang_data[$selectedLanguage]['on_relief'] ?? 'On relief') . '<a><img src="img/work.png" alt=""></a></div></td><td>' . ($assign_car_btn != "" ? '<div id="admin_page_disabled_div">' . htmlspecialchars($lang_data[$selectedLanguage]['on_relief'] ?? 'On relief') . '<a><img src="img/cars.png" alt=""></a></div>' : '');
                            } else {
                                echo '<div>' . htmlspecialchars($lang_data[$selectedLanguage]['assign_work'] ?? 'Assign work') . '<a href="' . $link . '=assignment" ' . $disabledAttr . '><img src="img/work.png" alt=""></a></div></td><td> ' . $assign_car_btn;
                            }

        echo '              </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>';
    return; // Stop further processing to avoid rendering the form when the buttons are shown
}


$validAssignmentTypes = ['car', 'house', 'assignment'];
if (!in_array($assignmentType, $validAssignmentTypes)) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "');</script>";
    exit();
}

// Fetch currently assigned car, house, or assignment (if any) for this user
$currentAssignment = null;
switch ($assignmentType) {
    case 'car':
        // Fetch current car assigned to the user
        $sql = "SELECT car_guid 
        FROM cars 
        WHERE driver_guid = ?";

        $stmt = $MySQL->getConnection()->prepare($sql);
        $stmt->bind_param("i", $user_guid);
        $stmt->execute();
        $stmt->bind_result($currentAssignment);
        $stmt->fetch();
        $stmt->close();
        break;

    case 'house':
        // Fetch current house assigned to the user
        $sql = "SELECT house_guid FROM workers WHERE user_guid = ?";
        $stmt = $MySQL->getConnection()->prepare($sql);
        $stmt->bind_param("i", $user_guid);
        $stmt->execute();
        $stmt->bind_result($currentAssignment);
        $stmt->fetch();
        $stmt->close();
        break;

    case 'assignment':
        // Fetch current shift assignment for this user (if any)
        $sql = "SELECT assignment_guid FROM shift_assignment_workers WHERE user_guid = ?";
        $stmt = $MySQL->getConnection()->prepare($sql);
        $stmt->bind_param("i", $user_guid);
        $stmt->execute();
        $stmt->bind_result($currentAssignment);
        $stmt->fetch();
        $stmt->close();
        break;
}


// Handle form submission for assigning the worker to a car, house, or shift
try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            $newAssignment = intval($_POST[$assignmentType . '_guid']); // car_guid, house_guid, or assignment_guid

            switch ($assignmentType) {
                case 'car':
                    // Unassign from old car
                    $sql = "UPDATE cars SET driver_guid = 0 WHERE driver_guid = ?";
                    $insertStmt = $MySQL->getConnection()->prepare($sql);
                    $insertStmt->bind_param("i", $user_guid);
                    $insertStmt->execute();
                    $insertStmt->close();

                    // Assign driver to a car
                    $sql = "UPDATE cars SET driver_guid = ? WHERE car_guid = ?";
                    $insertStmt = $MySQL->getConnection()->prepare($sql);
                    $insertStmt->bind_param("ii", $user_guid, $newAssignment);
                    $insertStmt->execute();
                    $insertStmt->close();
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_assigned_success'] ?? 'Car assigned successfully.') . "', true);</script>";
                    break;

                case 'house':
                    // Assign worker to a house
                    $sql = "UPDATE workers SET house_guid = ? WHERE user_guid = ?";
                    $insertStmt = $MySQL->getConnection()->prepare($sql);
                    $insertStmt->bind_param("ii", $newAssignment, $user_guid);
                    $insertStmt->execute();
                    $insertStmt->close();
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['house_assigned_success'] ?? 'House assigned successfully.') . "', true);</script>";
                    break;

                case 'assignment':
                    $checkSql = "
                        SELECT sa.workers, COUNT(saw.user_guid) AS assigned_workers
                        FROM shift_assignments sa
                        LEFT JOIN shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
                        WHERE sa.assignment_guid = ?
                    ";

                    $checkStmt = $MySQL->getConnection()->prepare($checkSql);
                    $checkStmt->bind_param("i", $newAssignment);
                    $checkStmt->execute();

                    $checkStmt->bind_result($required_workers, $count_assigned_workers);
                    $checkStmt->fetch();
                    $checkStmt->close();

                    $required_workers_array = json_decode($required_workers, true);
                    $required_workers = array_sum(array_map('intval', (is_array($required_workers_array) ? $required_workers_array : [$required_workers])));
                    // There is room for more workers in this assignment
                    if (($count_assigned_workers < $required_workers) || $newAssignment == 0) {
                        if ($currentAssignment) {
                            // Delete the previous shift assignment for the worker
                            $deleteOldAssignmentSql = "DELETE FROM shift_assignment_workers WHERE user_guid = ?";
                            $deleteStmt = $MySQL->getConnection()->prepare($deleteOldAssignmentSql);
                            $deleteStmt->bind_param("i", $user_guid);
                            $deleteStmt->execute();
                            $deleteStmt->close();
                        }

                        if($newAssignment != 0) {
                            $insertSql = "INSERT INTO shift_assignment_workers (assignment_guid, user_guid) VALUES (?, ?)";
                            $insertStmt = $MySQL->getConnection()->prepare($insertSql);
                            $insertStmt->bind_param("ii", $newAssignment, $user_guid);
                            $insertStmt->execute();
                            $insertStmt->close();
                        }
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['worker_assigned_success'] ?? 'Worker assigned successfully.') . "', true);</script>";
                    } else {
                        // No room left for more workers
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['no_slots_available'] ?? 'No available slots for this assignment.') . "', true);</script>";
                        return;
                    }
            }

            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['worker_assigned_success'] ?? 'Worker assigned successfully.') . "', true);</script>";
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['csrf_error'] ?? 'Invalid CSRF token.') . "', true);</script>";
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
}

// Fetch the list of available options based on the assignment type
$assignments = [];

switch ($assignmentType) {
    case 'car':
        // Fetch cars
        $sql = "
            SELECT *
            FROM cars 
            WHERE driver_guid = 0 AND car_guid > 0
        ";
        $result = $MySQL->getConnection()->query($sql);
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
        break;

    case 'house':
        // Fetch houses
        $sql = "SELECT house_guid, house_address FROM houses ORDER BY house_guid";
        $result = $MySQL->getConnection()->query($sql);
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
        break;

    case 'assignment':
        // Fetch assignments where workers are less than the required amount
        $sql = "
            SELECT sa.assignment_guid, sa.description, sa.workers, si.site_name
            FROM shift_assignments sa
            JOIN sites si ON sa.site_guid = si.site_guid
            WHERE (CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date) OR sa.assignment_guid = 0

            ORDER BY assignment_guid ASC
            ";
        $result = $MySQL->getConnection()->query($sql);
        while ($row = $result->fetch_assoc()) {
            $count_sql = "
                SELECT COUNT(saw.user_guid) AS assigned_workers
                FROM shift_assignments sa
                LEFT JOIN shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
                WHERE sa.assignment_guid = {$row['assignment_guid']}
                ";
            $count_result = $MySQL->getConnection()->query($count_sql);
            $count_row = $count_result->fetch_assoc();
            $workers_array = json_decode($row['workers'], true);
            $total_workers = array_sum(array_map('intval', (is_array($workers_array) ? $workers_array : [$row['workers']])));
            if($count_row['assigned_workers'] >= $total_workers && $row['assignment_guid'] != 0)
                continue;

            $row['assigned_workers'] = $count_row['assigned_workers'];
            $assignments[] = $row;
        }
        break;
}
// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Display the form for assignment

$assign_worker_to_string = $lang_data[$selectedLanguage]['assign_worker_to'] ?? 'Assign worker to';
$assign_worker_to_string = str_replace($lang_data[$selectedLanguage]["_worker"] ?? "worker", $lang_data[$selectedLanguage]["_worker"] . ' #'.getWorkerID($user_guid), $assign_worker_to_string);

$back = 'javascript:window.history.go(-1);';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";
echo '
<div class="page">
    <h2>
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($assign_worker_to_string) . ' ' .htmlspecialchars($lang_data[$selectedLanguage][$assignmentType] ?? ucfirst($assignmentType)). '
    </h2>
        <div class="edit-user-page">
            <form action="" method="post" style="margin-top: 0;">
                <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                <label for="assignment_select">' . htmlspecialchars($lang_data[$selectedLanguage][$assignmentType] ?? ucfirst($assignmentType)) . '</label>
                <select style="width: 92vw;" id="assignment_select" name="' . htmlspecialchars($assignmentType) . '_guid" required>';
// Check if there's a current assignment or if we need to show the default option
if (empty($currentAssignment) || $currentAssignment == 0) {
    echo '          <option disabled selected value="">' . htmlspecialchars($lang_data[$selectedLanguage]["select_" . $assignmentType] ?? "Please select a " . ucfirst($assignmentType)) . '</option>';
}
$count = 0;
foreach ($assignments as $assignment) {
    $count++;
    // Check if the current assignment matches, and set the selected attribute accordingly
    $selected = (!empty($currentAssignment) && $currentAssignment == $assignment[$assignmentType . '_guid']) ? 'selected' : '';

    if ($assignmentType === 'car') {
        echo '<option value="' . htmlspecialchars($assignment['car_guid']) . '" ' . $selected . '>' . htmlspecialchars($assignment['car_model'] . ' (' . $assignment['car_number_plate'] . ')') . '</option>';
    } elseif ($assignmentType === 'house') {
        echo '<option value="' . htmlspecialchars($assignment['house_guid']) . '" ' . $selected . '>' . htmlspecialchars($assignment['house_address']) . '</option>';
    } elseif ($assignmentType === 'assignment') {
        if ($assignment['assignment_guid'] == 0) {
            if (!empty($currentAssignment))
                echo '<option value="' . htmlspecialchars($assignment['assignment_guid']) . '" ' . $selected . '>' . htmlspecialchars($lang_data[$selectedLanguage]['unassign_from_work'] ?? 'Unassign from work') . '</option>';
        } else {
            $assigned_workers = $assignment['assigned_workers'];
            $workers_array = json_decode($assignment['workers'], true);
            $description_array = json_decode($assignment['description'], true);
            $tmp_description = $assignment['description'];
            $descriptions = "";
            foreach ((is_array($description_array) ? $description_array : [$assignment['description']]) as $key => $desc) {
                if($key == 0)
                    $descriptions .= $workers_array[$key]." {$desc}";
                else
                    $descriptions .= ", ".$workers_array[$key]." {$desc}";
            }

            echo '<option value="' . htmlspecialchars($assignment['assignment_guid']) . '" ' . $selected . '>' . htmlspecialchars($assignment['site_name']) . ' - ' . htmlspecialchars($descriptions) . ' (' . $assigned_workers . '/' . array_sum(array_map('intval', json_decode($assignment['workers'], true))) . ' ' . htmlspecialchars($lang_data[$selectedLanguage]['workers'] ?? 'workers') . ')</option>';

        }
    }
}

if($count == 0)
    echo "<option value='0' disabled>No {$assignmentType}s available</option>";

echo '      </select>
                <button id="btn-update" style="width: 92vw; margin-top: 25px;" type="submit">' . htmlspecialchars($lang_data[$selectedLanguage]["assign"] ?? "Assign") . '</button>
            </form>
        </div>';
// Show the currently assigned work, if any
$isAssignedToWork = isAssignedToWork($user_guid);
if ($assignmentType == 'assignment' && $isAssignedToWork != 0) {
    $assignment_guid = getAssignmentForWorker($user_guid);
    echo '
        <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["current_assignment"] ?? "Current assignment") . '</h3>
        <div class="user-list" style="height: 20vh;">
            <table>
                <thead>
                    <tr>
                        <th>' . htmlspecialchars($lang_data[$selectedLanguage]['site_name'] ?? 'Site Name') . '</th>
                        <th>' . htmlspecialchars($lang_data[$selectedLanguage]['assignment'] ?? 'Assignment') . '</th>
                        <th>' . htmlspecialchars($lang_data[$selectedLanguage]['actions'] ?? 'Actions') . '</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="height: 25px;">
                            <a style="color: black; text-decoration: underline;" href="index.php?lang=' . $selectedLanguage . '&page=admin&sub_page=sites&action=edit&site_guid=' . $isAssignedToWork . '">
                                ' . getSiteName($isAssignedToWork) . '
                            </a>
                        </td>
                        <td style="height: 25px;">
                            <a style="color: black; text-decoration: underline;" href="index.php?lang=' . $selectedLanguage . '&page=admin&sub_page=assignments&action=edit&assignment_guid=' . $assignment_guid . '">
                                ' . getAssignmentDescription($assignment_guid) . '
                            </a>
                        </td>
                        <td style="height: 25px;">
                            <a style="color: black; text-decoration: underline;" href="index.php?lang=' . $selectedLanguage . '&page=admin&sub_page=assignments&action=unassign&assignment_guid=' . $assignment_guid . '&user_guid='.$user_guid.'">
                                <img class="manage_shift_btn" src="img/unassign.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['unassign'] ?? 'Unassign') . '">
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>';
} else if ($assignmentType == 'assignment') echo '
        <h3>' . htmlspecialchars($lang_data[$selectedLanguage]["worker_not_assigned"] ?? "Worker not Assigned") . '</h3>
        <div class="user-list" style="height: 20vh;">
        </div>';

echo '
    </div>
';
?>

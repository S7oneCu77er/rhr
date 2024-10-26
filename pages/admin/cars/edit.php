<?php
// pages/admin/cars/edit.php

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

// Validate and sanitize the car_guid from GET
if (isset($_GET['car_guid'])) {
    $car_guid = intval($_GET['car_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}



try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Fetch car details
    $stmt = $MySQL->getConnection()->prepare("
        SELECT car_model, car_model_year, car_number_plate, max_passengers, insurance_end, license_end 
        FROM cars 
        WHERE car_guid = ?
    ");
    $stmt->bind_param("i", $car_guid);
    $stmt->execute();
    $stmt->bind_result($car_model, $car_model_year, $car_number_plate, $max_passengers, $insurance_end, $license_end);
    if (!$stmt->fetch()) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_not_found'] ?? 'Car not found.') . "', true);</script>";
        $stmt->close();
        exit();
    }
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
}

// Generate a CSRF token for form submission (if required in future)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission for updating car details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        // Retrieve and sanitize form inputs
        $updated_car_model          = htmlspecialchars(trim($_POST['car_model']));
        $updated_car_model_year     = intval(trim($_POST['car_model_year']));
        $updated_car_number_plate   = htmlspecialchars(trim($_POST['car_number_plate']));
        $updated_max_passengers     = intval(trim($_POST['max_passengers']));
        $updated_insurance_end      = htmlspecialchars($_POST['insurance_end']);
        $updated_license_end        = htmlspecialchars($_POST['license_end']);

        // Update car details in the database
        $sql = "
            UPDATE cars
            SET car_model = ?, car_model_year = ?, car_number_plate = ?, max_passengers = ?, insurance_end = ?, license_end = ?
            WHERE car_guid = ?
        ";
        $updateStmt = $MySQL->getConnection()->prepare($sql);
        if ($updateStmt) {
            $updateStmt->bind_param("sisissi", $updated_car_model, $updated_car_model_year, $updated_car_number_plate, $updated_max_passengers, $updated_insurance_end, $updated_license_end, $car_guid);

            if ($updateStmt->execute()) {
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_update_success'] ?? 'Car details updated successfully.') . "', true);</script>";
            } else {
                // Log the error for debugging purposes
                error_log("Error updating car: " . $updateStmt->error);
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['update_car_error'] ?? 'Error updating car details.') . "', true);</script>";
            }
            $updateStmt->close();
        } else {
            // Log the error for debugging purposes
            error_log("Prepare failed: " . $MySQL->getConnection()->error);
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
        }
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['csrf_error'] ?? 'Invalid CSRF token.') . "', true);</script>";
    }
}

$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";

// Display the car information
echo '
<div class="page">
    <h2 style="margin: 0;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["edit"] ?? "Edit") . ' ' . htmlspecialchars($lang_data[$selectedLanguage]["view_car"] ?? "Car Information") . '
    </h2>
    <div class="edit-user-page" style="width: 100vw;">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table style="margin-top: -5px;">
                <tbody>
                    <tr>
                        <td>
                            <label for="car_model">' . htmlspecialchars($lang_data[$selectedLanguage]["car_model"] ?? "Car Model") . '</label>
                            <input style="width: 95%;" type="text" id="car_model" name="car_model" value="' . htmlspecialchars($car_model) . '" required>
                        </td>
                        <td>
                            <label for="car_model_year">' . htmlspecialchars($lang_data[$selectedLanguage]["car_model_year"] ?? "Model Year") . '</label>
                            <input style="width: 95%;" type="text" id="car_model_year" name="car_model_year" value="' . htmlspecialchars($car_model_year) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="car_number_plate">' . htmlspecialchars($lang_data[$selectedLanguage]["car_number_plate"] ?? "Number Plate") . '</label>
                            <input style="width: 95%;" type="text" id="car_number_plate" name="car_number_plate" value="' . htmlspecialchars($car_number_plate) . '" required>
                        </td>
                        <td>
                            <label for="max_passengers">' . htmlspecialchars($lang_data[$selectedLanguage]["max_passengers"] ?? "Max Passengers") . '</label>
                            <input style="width: 95%;" type="text" id="max_passengers" name="max_passengers" value="' . htmlspecialchars($max_passengers) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="insurance_end">' . htmlspecialchars($lang_data[$selectedLanguage]["insurance_end"] ?? "Insurance End Date") . '</label>
                            <input style="width: 95%;" type="date" id="insurance_end" name="insurance_end" required>
                        </td>
                        <td>
                            <label for="license_end">' . htmlspecialchars($lang_data[$selectedLanguage]["license_end"] ?? "License End Date") . '</label>
                            <input style="width: 95%;" type="date" id="license_end" name="license_end" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 92vw; margin-top: 20px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["update"] ?? "Update") . '</button>
        </form>
    </div>
</div>
<script>';
echo "
    // Function to format date to YYYY-MM-DD
    function formatDate(date) {
        let d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    // Fetch the insurance_end and license_end values if available, else set default dates
    let insuranceEnd = '" . htmlspecialchars($insurance_end) . "';
    let licenseEnd = '" . htmlspecialchars($license_end) . "';

    // If no value is provided for insurance_end, set it to today's date
    if (!insuranceEnd) {
        insuranceEnd = formatDate(new Date());
    } else { 
        insuranceEnd = formatDate(insuranceEnd);
    }

    // Set insurance_end date
    document.getElementById('insurance_end').value = insuranceEnd;

    // If no value is provided for license_end, set it to today's date
    if (!licenseEnd) {
        licenseEnd = formatDate(new Date());
    } else { 
        licenseEnd = formatDate(licenseEnd);
    }

    // Set license_end date
    document.getElementById('license_end').value = licenseEnd;
    </script>";
?>

<?php
// pages/admin/cars/add.php

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



// Generate CSRF token for form submission
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission for adding a new car
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        // Retrieve and sanitize form inputs
        $car_model         = htmlspecialchars(trim($_POST['car_model']));
        $car_model_year    = intval($_POST['car_model_year']);
        $car_number_plate  = htmlspecialchars(trim($_POST['car_number_plate']));
        $max_passengers    = intval($_POST['max_passengers']);
        $insurance_end     = htmlspecialchars($_POST['insurance_end']);
        $license_end       = htmlspecialchars($_POST['license_end']);

        // Insert new car details into the database
        try {
            $sql = "INSERT INTO cars (car_model, car_model_year, car_number_plate, max_passengers, insurance_end, license_end) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $MySQL->getConnection()->prepare($sql);
            $stmt->bind_param("sisiis", $car_model, $car_model_year, $car_number_plate, $max_passengers, $insurance_end, $license_end);

            if ($stmt->execute()) {
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_add_success'] ?? 'Car added successfully.') . "', true);</script>";
                header("Location: index.php?page=admin&sub_page=cars");
                exit();
            } else {
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['car_add_error'] ?? 'Error adding car.') . "', true);</script>";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            error_log("Error: " . $e->getMessage());
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
        }
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['csrf_error'] ?? 'Invalid CSRF token.') . "', true);</script>";
    }
}

$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";

// Display the form to add a new car
echo '
<div class="page">
    <h2 style="margin: 15px;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["add_car"] ?? "Add New Car") . '
    </h2>
    <div class="edit-user-page">
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table style="margin-top: -5px;">
                <tbody>
                    <tr>
                        <td>
                            <label for="car_model">' . htmlspecialchars($lang_data[$selectedLanguage]["car_model"] ?? "Car Model") . '</label>
                            <input style="width: 80%;" type="text" id="car_model" name="car_model" value="" required>
                        </td>
                        <td>
                            <label for="car_model_year">' . htmlspecialchars($lang_data[$selectedLanguage]["car_model_year"] ?? "Model Year") . '</label>
                            <input style="width: 80%;" type="number" id="car_model_year" name="car_model_year" value="" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="car_number_plate">' . htmlspecialchars($lang_data[$selectedLanguage]["car_number_plate"] ?? "Number Plate") . '</label>
                            <input style="width: 80%;" type="text" id="car_number_plate" name="car_number_plate" value="" required>
                        </td>
                        <td>
                            <label for="max_passengers">' . htmlspecialchars($lang_data[$selectedLanguage]["max_passengers"] ?? "Max Passengers") . '</label>
                            <input style="width: 80%;" type="number" id="max_passengers" name="max_passengers" value="" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="insurance_end">' . htmlspecialchars($lang_data[$selectedLanguage]["insurance_end"] ?? "Insurance End Date") . '</label>
                            <input style="width: 80%;" type="date" id="insurance_end" name="insurance_end" value="" required>
                        </td>
                        <td>
                            <label for="license_end">' . htmlspecialchars($lang_data[$selectedLanguage]["license_end"] ?? "License End Date") . '</label>
                            <input style="width: 80%;" type="date" id="license_end" name="license_end" value="" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 91%; margin-top: 20px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["add_car"] ?? "Add Car") . '</button>
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

    // Set shift_start_date to today's date
    document.getElementById('insurance_end').value = formatDate(new Date());

    // Set shift_end_date to tomorrow's date
    let license_end = new Date();
    document.getElementById('license_end').value = formatDate(license_end);
    
</script>";
?>

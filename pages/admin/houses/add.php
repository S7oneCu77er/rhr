<?php
// pages/admin/houses/add.php

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
    header("Location: ../index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}

try {
    // Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Handle form submission for adding a new house
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            // Retrieve and sanitize form inputs
            $house_address = htmlspecialchars(trim($_POST['house_address']));
            $address_description = htmlspecialchars(trim($_POST['address_description']));
            $house_size_sqm = intval($_POST['house_size_sqm']);
            $number_of_rooms = intval($_POST['number_of_rooms']);
            $number_of_toilets = intval($_POST['number_of_toilets']);
            $contract_number = trim($_POST['contract_number']);
            $contract_start = trim($_POST['contract_start']);
            $contract_end = trim($_POST['contract_end']);
            $security_deed = trim($_POST['security_deed']);
            $monthly_rent = intval($_POST['monthly_rent']);
            $monthly_arnona = intval($_POST['monthly_arnona']);
            $monthly_water = intval($_POST['monthly_water']);
            $monthly_electric = intval($_POST['monthly_electric']);
            $monthly_gas = intval($_POST['monthly_gas']);
            $monthly_vaad = intval($_POST['monthly_vaad']);
            $landlord_name = htmlspecialchars(trim($_POST['landlord_name']));
            $landlord_id = htmlspecialchars(trim($_POST['landlord_id']));
            $landlord_phone = htmlspecialchars(trim($_POST['landlord_phone']));
            $landlord_email = htmlspecialchars(trim($_POST['landlord_email']));
            $vaad_name = htmlspecialchars(trim($_POST['vaad_name']));
            $vaad_phone = htmlspecialchars(trim($_POST['vaad_phone']));
            $max_tenants = intval($_POST['max_tenants']);

            // Prepare SQL statement to insert a new house
            $sql = "
                INSERT INTO houses 
                    (house_address, address_description, house_size_sqm, number_of_rooms, number_of_toilets, contract_number, contract_start, contract_end, security_deed, monthly_rent, monthly_arnona, monthly_water, monthly_electric, monthly_gas, monthly_vaad, landlord_name, landlord_id, landlord_phone, landlord_email, vaad_name, vaad_phone, max_tenants) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $MySQL->getConnection()->prepare($sql);

            if ($insertStmt) {
                // Bind parameters
                $insertStmt->bind_param("ssiiissssiiiiissssssi",
                    $house_address, $address_description, $house_size_sqm, $number_of_rooms, $number_of_toilets,
                    $contract_number, $contract_start, $contract_end, $security_deed, $monthly_rent,
                    $monthly_arnona, $monthly_water, $monthly_electric, $monthly_gas, $monthly_vaad,
                    $landlord_name, $landlord_id, $landlord_phone, $landlord_email, $vaad_name,
                    $vaad_phone, $max_tenants
                );

                // Execute the statement
                if ($insertStmt->execute()) {
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['add_success'] ?? 'House added successfully.') . "', true);</script>";
                } else {
                    // Log the error for debugging purposes
                    error_log("Error adding house: " . $insertStmt->error);
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['add_error'] ?? 'Error adding house.') . "', true);</script>";
                }

                // Close the statement
                $insertStmt->close();
            } else {
                // Log the error for debugging purposes
                error_log("Prepare failed: " . $MySQL->getConnection()->error);
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
            }
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['csrf_error'] ?? 'Invalid CSRF token.') . "', true);</script>";
        }
    }
} catch (mysqli_sql_exception $e) {
    // Handle duplicate entry error
    if ($e->getCode() == 1062) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found.') . "', true);</script>";
    } else {
        // Log the error and show a general error message
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Display the add house form
echo '
<div class="page">
    <h2 style="margin: 15px;">' . htmlspecialchars($lang_data[$selectedLanguage]["add_house"] ?? "Add House") . '</h2>
    <div class="edit-house-page">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table style="margin-top: -5px;">
                <tbody>
                    <tr>
                        <td colspan="1">
                            <label for="house_address">' . htmlspecialchars($lang_data[$selectedLanguage]["house_address"] ?? "House Address") . '</label>
                            <input style="width: 80%;"  type="text" id="house_address" name="house_address" value="" required>
                        </td>
                        <td colspan="2">
                            <label for="address_description">' . htmlspecialchars($lang_data[$selectedLanguage]["address_description"] ?? "Address Description") . '</label>
                            <input style="width: 90%;"  type="text" id="address_description" name="address_description" value="" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="house_size_sqm">' . htmlspecialchars($lang_data[$selectedLanguage]["house_size_sqm"] ?? "House Size (sqm)") . '</label>
                            <input type="number" id="house_size_sqm" name="house_size_sqm" value="0" required>
                        </td>
                        <td>
                            <label for="number_of_rooms">' . htmlspecialchars($lang_data[$selectedLanguage]["number_of_rooms"] ?? "Number of Rooms") . '</label>
                            <input type="number" id="number_of_rooms" name="number_of_rooms" value="0" required>
                        </td>
                        <td>
                            <label for="number_of_toilets">' . htmlspecialchars($lang_data[$selectedLanguage]["number_of_toilets"] ?? "Number of Toilets") . '</label>
                            <input type="number" id="number_of_toilets" name="number_of_toilets" value="0" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="contract_number">' . htmlspecialchars($lang_data[$selectedLanguage]["contract_number"] ?? "Contract Number") . '</label>
                            <input type="text" id="contract_number" name="contract_number" value="" required>
                        </td>
                        <td>
                            <label for="contract_start">' . htmlspecialchars($lang_data[$selectedLanguage]["contract_start"] ?? "Contract Start") . '</label>
                            <input type="date" id="contract_start" name="contract_start" value="" required>
                        </td>
                        <td>
                            <label for="contract_end">' . htmlspecialchars($lang_data[$selectedLanguage]["contract_end"] ?? "Contract End") . '</label>
                            <input type="date" id="contract_end" name="contract_end" value="" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="security_deed">' . htmlspecialchars($lang_data[$selectedLanguage]["security_deed"] ?? "Security Deed") . '</label>
                            <input type="text" id="security_deed" name="security_deed" value="" required>
                        </td>
                        <td>
                            <label for="monthly_rent">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_rent"] ?? "Monthly Rent") . '</label>
                            <input type="number" id="monthly_rent" name="monthly_rent" value="0" required>
                        </td>
                        <td>
                            <label for="monthly_arnona">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_arnona"] ?? "Monthly Arnona") . '</label>
                            <input type="number" id="monthly_arnona" name="monthly_arnona" value="0" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="monthly_water">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_water"] ?? "Monthly Water") . '</label>
                            <input type="number" id="monthly_water" name="monthly_water" value="0">
                        </td>
                        <td>
                            <label for="monthly_electric">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_electric"] ?? "Monthly Electric") . '</label>
                            <input type="number" id="monthly_electric" name="monthly_electric" value="0" required>
                        </td>
                        <td>
                            <label for="monthly_gas">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_gas"] ?? "Monthly Gas") . '</label>
                            <input type="number" id="monthly_gas" name="monthly_gas" value="0" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="monthly_vaad">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_vaad"] ?? "Monthly Vaad") . '</label>
                            <input type="number" id="monthly_vaad" name="monthly_vaad" value="0" required>
                        </td>
                        <td>
                            <label for="landlord_name">' . htmlspecialchars($lang_data[$selectedLanguage]["landlord_name"] ?? "Landlord Name") . '</label>
                            <input type="text" id="landlord_name" name="landlord_name" value="" required>
                        </td>    
                        <td>
                            <label for="landlord_id">' . htmlspecialchars($lang_data[$selectedLanguage]["landlord_id"] ?? "Landlord ID") . '</label>
                            <input type="text" id="landlord_id" name="landlord_id" value="" required>
                        </td>
                    </tr>
                    <tr>    
                        <td>
                            <label for="landlord_phone">' . htmlspecialchars($lang_data[$selectedLanguage]["landlord_phone"] ?? "Landlord Phone") . '</label>
                            <input type="text" id="landlord_phone" name="landlord_phone" value="" required>
                        </td>
                        <td>
                            <label for="vaad_name">' . htmlspecialchars($lang_data[$selectedLanguage]["vaad_name"] ?? "Vaad Name") . '</label>
                            <input type="text" id="vaad_name" name="vaad_name" value="" required>
                        </td>
                        <td>
                            <label for="vaad_phone">' . htmlspecialchars($lang_data[$selectedLanguage]["vaad_phone"] ?? "Vaad Phone") . '</label>
                            <input type="text" id="vaad_phone" name="vaad_phone" value="" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 95%;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["add"] ?? "Add") . '</button>
        </form>
    </div>
</div>
';
?>

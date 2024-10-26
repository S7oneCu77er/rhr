<?php
// pages/admin/houses/edit.php

// Include necessary configurations and handlers
global $selectedLanguage;
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php'; // Ensure language handler is included

global $lang_data, $selectedLanguage, $MySQL;

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
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "');</script>";
    exit();
}

// Validate and sanitize the worker_id from GET
if (isset($_GET['house_guid'])) {
    $house_guid = intval($_GET['house_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "');</script>";
    exit();
}

// Handle form submission for updating user details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        // Retrieve and sanitize form inputs
        $updated_house_address          = htmlspecialchars($_POST['house_address']);
        $updated_address_description    = htmlspecialchars($_POST['address_description']);
        $updated_house_size_sqm         = trim($_POST['house_size_sqm']);
        $updated_number_of_rooms        = trim($_POST['number_of_rooms']);
        $updated_number_of_toilets      = trim($_POST['number_of_toilets']);
        $updated_contract_number        = trim($_POST['contract_number']);
        $updated_contract_start         = trim($_POST['contract_start']);
        $updated_contract_end           = trim($_POST['contract_end']);
        $updated_security_deed          = trim($_POST['security_deed']);
        $updated_monthly_rent           = trim($_POST['monthly_rent']);
        $updated_monthly_arnona         = trim($_POST['monthly_arnona']);
        $updated_monthly_water          = trim($_POST['monthly_water']);
        $updated_monthly_electric       = trim($_POST['monthly_electric']);
        $updated_monthly_gas            = trim($_POST['monthly_gas']);
        $updated_monthly_vaad           = trim($_POST['monthly_vaad']);
        $updated_landlord_name          = trim($_POST['landlord_name']);
        $updated_landlord_id            = trim($_POST['landlord_id']);
        $updated_landlord_phone         = trim($_POST['landlord_phone']);
        $updated_vaad_name              = trim($_POST['vaad_name']);
        $updated_vaad_phone             = trim($_POST['vaad_phone']);

        // Update user details in the database
        $sql = "
            UPDATE 
                houses 
            SET 
                house_address = ?,
                address_description = ?, 
                house_size_sqm = ?, 
                number_of_rooms = ?, 
                number_of_toilets = ?, 
                contract_number = ?, 
                contract_start = ?, 
                contract_end = ?, 
                security_deed = ?, 
                monthly_rent = ?, 
                monthly_arnona = ?, 
                monthly_water = ?, 
                monthly_electric = ?, 
                monthly_gas = ?, 
                monthly_vaad = ?, 
                landlord_name = ?, 
                landlord_id = ?, 
                landlord_phone = ?, 
                vaad_name = ?, 
                vaad_phone = ? 
            WHERE 
                house_guid = ?";
        $updateStmt = $MySQL->getConnection()->prepare($sql);
        if ($updateStmt) {
            $updateStmt->bind_param("ssisssssssssssssssssi", $updated_house_address, $updated_address_description, $updated_house_size_sqm, $updated_number_of_rooms, $updated_number_of_toilets, $updated_contract_number, $updated_contract_start, $updated_contract_end, $updated_security_deed,
                $updated_monthly_rent, $updated_monthly_arnona, $updated_monthly_water, $updated_monthly_electric, $updated_monthly_gas, $updated_monthly_vaad, $updated_landlord_name, $updated_landlord_id, $updated_landlord_phone, $updated_vaad_name, $updated_vaad_phone, $house_guid);

            if ($updateStmt->execute()) {
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['house_update_success'] ?? 'House data updated successfully.') . "', true);</script>";
            } else {
                // Log the error for debugging purposes
                error_log("Error updating user: " . $updateStmt->error);
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['update_house_error'] ?? 'Error updating house data.') . "', true);</script>";
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

// Fetch user details
$stmt = $MySQL->getConnection()->prepare("SELECT * FROM houses WHERE house_guid = ?");
if ($stmt) {
    $stmt->bind_param("i", $house_guid);
    $stmt->execute();
    $stmt->bind_result($house_guid, $house_address, $address_description, $house_size_sqm, $number_of_rooms, $number_of_toilets, $contract_number, $contract_start, $contract_end, $security_deed, $monthly_rent, $monthly_arnona, $monthly_water, $monthly_electric, $monthly_gas, $monthly_vaad, $landlord_name, $landlord_id, $landlord_phone, $landlord_email, $vaad_name, $vaad_phone, $max_tenants);
    if (!$stmt->fetch()) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['house_data_not_found'] ?? 'House data not found.') . "', true);</script>";
        $stmt->close();
        exit();
    }
    $stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}


// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$margin_dir = in_array($selectedLanguage, ["Hebrew", "Arabic"]) ? "right" : "left"; // Adjust if needed
$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=houses';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";

// Display the edit form
echo '
<div class="page">
    <h2 style="margin: 5px;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["edit_house"] ?? "Edit House") . '
    </h2>
    <div class="edit-house-page" style="width: 100vw;">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table style="width:100vw; margin-top: -5px;">
                <tbody>
                    <tr>
                        <td colspan="1" style="width: 33%">
                            <label for="address_description">' . htmlspecialchars($lang_data[$selectedLanguage]["address_description"] ?? "Description") . '</label>
                            <input type="text" id="address_description" name="address_description" value="' . htmlspecialchars($address_description) . '" required>
                        </td>
                        <td colspan="2" style="width: 67%">
                            <label for="house_address">' . htmlspecialchars($lang_data[$selectedLanguage]["house_address"] ?? "Address") . '</label>
                            <input style="width: 90%" type="text" id="house_address" name="house_address" value="' . htmlspecialchars($house_address) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 33%">
                            <label for="house_size_sqm">' . htmlspecialchars($lang_data[$selectedLanguage]["house_size_sqm"] ?? "House Size") . '</label>
                            <input type="text" id="house_size_sqm" name="house_size_sqm" value="' . htmlspecialchars($house_size_sqm) . '" required>
                        </td>
                        <td style="width: 34%">
                            <label for="number_of_rooms">' . htmlspecialchars($lang_data[$selectedLanguage]["number_of_rooms"] ?? "Rooms") . '</label>
                            <input type="text" id="number_of_rooms" name="number_of_rooms" value="' . htmlspecialchars($number_of_rooms) . '" required>
                        </td>
                        <td style="width: 33%">
                            <label for="number_of_toilets">' . htmlspecialchars($lang_data[$selectedLanguage]["number_of_toilets"] ?? "Toilets") . '</label>
                            <input type="text" id="number_of_toilets" name="number_of_toilets" value="' . htmlspecialchars($number_of_toilets) . '" required">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="contract_number">' . htmlspecialchars($lang_data[$selectedLanguage]["contract_number"] ?? "Contract Number") . '</label>
                            <input type="text" id="contract_number" name="contract_number" value="' . htmlspecialchars($contract_number) . '" required">
                        </td>
                        <td>
                            <label for="contract_start">' . htmlspecialchars($lang_data[$selectedLanguage]["contract_start"] ?? "Contract Start") . '</label>
                            <input type="text" id="contract_start" name="contract_start" value="' . htmlspecialchars($contract_start) . '" required>
                        </td>
                        <td>
                            <label for="contract_end">' . htmlspecialchars($lang_data[$selectedLanguage]["contract_end"] ?? "Contract End") . '</label>
                            <input type="text" id="contract_end" name="contract_end" value="' . htmlspecialchars($contract_end) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="security_deed">' . htmlspecialchars($lang_data[$selectedLanguage]["security_deed"] ?? "Security Deed") . '</label>
                            <input type="text" id="security_deed" name="security_deed" value="' . htmlspecialchars($security_deed) . '" required">
                        </td>
                        <td>
                            <label for="monthly_rent">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_rent"] ?? "Monthly Rent") . '</label>
                            <input type="text" id="monthly_rent" name="monthly_rent" value="' . htmlspecialchars($monthly_rent) . '" required>
                        </td>
                        <td>
                            <label for="monthly_arnona">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_arnona"] ?? "Monthly Arnona") . '</label>
                            <input type="text" id="monthly_arnona" name="monthly_arnona" value="' . htmlspecialchars($monthly_arnona) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="monthly_water">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_water"] ?? "Water") . '</label>
                            <input type="text" id="monthly_water" name="monthly_water" value="' . htmlspecialchars($monthly_water) . '" required>
                        </td>
                        <td>
                            <label for="monthly_electric">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_electric"] ?? "Electric") . '</label>
                            <input type="text" id="monthly_electric" name="monthly_electric" value="' . htmlspecialchars($monthly_electric) . '" required>
                        </td>
                        <td>
                            <label for="monthly_gas">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_gas"] ?? "Gas") . '</label>
                            <input type="text" id="monthly_gas" name="monthly_gas" value="' . htmlspecialchars($monthly_gas) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="monthly_vaad">' . htmlspecialchars($lang_data[$selectedLanguage]["monthly_vaad"] ?? "Monthly Vaad") . '</label>
                            <input type="text" id="monthly_vaad" name="monthly_vaad" value="' . htmlspecialchars($monthly_vaad) . '" required>
                        </td>
                        <td>
                            <label for="vaad_name">' . htmlspecialchars($lang_data[$selectedLanguage]["vaad_name"] ?? "Vaad") . '</label>
                            <input type="text" id="vaad_name" name="vaad_name" value="' . htmlspecialchars($vaad_name) . '" required>
                        </td>
                        <td>
                            <label for="vaad_phone">' . htmlspecialchars($lang_data[$selectedLanguage]["vaad_phone"] ?? "Vaad Phone") . '</label>
                            <input type="text" id="vaad_phone" name="vaad_phone" value="' . htmlspecialchars($vaad_phone) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="landlord_name">' . htmlspecialchars($lang_data[$selectedLanguage]["landlord_name"] ?? "Landlord") . '</label>
                            <input type="text" id="landlord_name" name="landlord_name" value="' . htmlspecialchars($landlord_name) . '" required>
                        </td>
                        <td>
                            <label for="landlord_id">' . htmlspecialchars($lang_data[$selectedLanguage]["landlord_id"] ?? "Landlord ID") . '</label>
                            <input type="text" id="landlord_id" name="landlord_id" value="' . htmlspecialchars($landlord_id) . '" required>
                        </td>
                        <td>
                            <label for="landlord_phone">' . htmlspecialchars($lang_data[$selectedLanguage]["landlord_phone"] ?? "Landlord Phone") . '</label>
                            <input type="text" id="landlord_phone" name="landlord_phone" value="' . htmlspecialchars($landlord_phone) . '" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 92vw;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["update"] ?? "Update") . '</button>
        </form>
    </div>
</div>
';
?>

<?php
// pages/admin/users/edit.php

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

// Validate and sanitize the user_guid from GET
if (isset($_GET['user_guid'])) {
    $user_guid = intval($_GET['user_guid']);
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_request'] ?? 'Invalid request.') . "', true);</script>";
    exit();
}

try {
// Enable MySQLi exception mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Handle form submission for updating user details
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            // Retrieve and sanitize form inputs
            $updatedFirstName = trim($_POST['first_name']);
            $updatedLastName = trim($_POST['last_name']);
            $updatedEmail = trim($_POST['email']);
            $updatedPassword = trim($_POST['password']);
            $updatedPassportID = trim($_POST['passport_id']);
            $updatedPhoneNumber = trim($_POST['phone_number']);
            $updatedCountry = trim($_POST['country']);
            $updatedDescription = trim($_POST['description']);
            $updatedRole = trim($_POST['group']);

            // Update user details in the database
            $sql = "UPDATE users SET first_name = ?, last_name = ?, passport_id = ?, password = ?, email = ?, phone_number = ?, country = ?, description = ?, `group` = ? WHERE user_guid = ?";
            $updateStmt = $MySQL->getConnection()->prepare($sql);
            if ($updateStmt) {
                $updateStmt->bind_param("sssssssssi", $updatedFirstName, $updatedLastName, $updatedPassportID, $updatedPassword, $updatedEmail, $updatedPhoneNumber, $updatedCountry, $updatedDescription, $updatedRole, $user_guid);
                if ($updateStmt->execute()) {
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['update_success'] ?? 'User updated successfully.') . "', true);</script>";
                } else {
                    // Log the error for debugging purposes
                    error_log("Error updating user: " . $updateStmt->error);
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['update_error'] ?? 'Error updating user.') . "', true);</script>";
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
} catch (mysqli_sql_exception $e) {
    // Check for duplicate entry error
    if ($e->getCode() == 1062) {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['duplicate_error'] ?? 'Duplicate entry found.') . "', true);</script>";
    } else {
        // Log the error and show a general error message
        error_log("Error: " . $e->getMessage());
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error occurred.') . "', true);</script>";
    }
}

// Fetch user details
$stmt = $MySQL->getConnection()->prepare("SELECT * FROM users WHERE user_guid = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_guid);
    $stmt->execute();
    $stmt->bind_result($user_guid, $firstName, $lastName, $passport_id, $password, $email, $phone_number, $country, $description, $role);
    if ($stmt->fetch()) {
        // User exists, display the edit form
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['user_not_found'] ?? 'User not found.') . "');</script>";
        $stmt->close();
        exit();
    }
    $stmt->close();
} else {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "');</script>";
    exit();
}


// Generate a CSRF token for the form if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$role = $role != "" ? $role : "workers";
$margin_dir = in_array($selectedLanguage, ["Hebrew", "Arabic"]) ? "right" : "left"; // Adjust if needed
$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=users';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";
// Display the edit form
echo '
<div class="page">
    <h2 style="margin: 0px;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["edit_user"] ?? "Edit User") . '
    </h2>
    <div class="edit-user-page">
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table style="width: 100vw;">
                <tbody>
                    <tr>
                        <td colspan="2">
                            <label for="description">' . htmlspecialchars($lang_data[$selectedLanguage]["description"] ?? "Description") . '</label>
                            <input style="width: 92.5vw;" type="text" id="description" name="description" value="' . htmlspecialchars($description) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="first_name">' . htmlspecialchars($lang_data[$selectedLanguage]["first_name"] ?? "First Name") . '</label>
                            <input type="text" id="first_name" name="first_name" value="' . htmlspecialchars($firstName) . '" required>
                        </td>
                        <td>
                            <label for="last_name">' . htmlspecialchars($lang_data[$selectedLanguage]["last_name"] ?? "Last Name") . '</label>
                            <input type="text" id="last_name" name="last_name" value="' . htmlspecialchars($lastName) . '" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="email">' . htmlspecialchars($lang_data[$selectedLanguage]["email"] ?? "Email") . '</label>
                            <input type="email" id="email" name="email" value="' . htmlspecialchars($email) . '">
                        </td>
                        <td>
                            <label for="password">' . htmlspecialchars($lang_data[$selectedLanguage]["password"] ?? "Password") . '</label>
                            <input class="password" type="text" id="password2" name="password" value="' . htmlspecialchars($password) . '" required">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="passport_id">' . htmlspecialchars($lang_data[$selectedLanguage]["passport_id"] ?? "Passport/ID") . '</label>
                            <input type="text" id="passport_id" name="passport_id" value="' . htmlspecialchars($passport_id) . '" required>
                        </td>
                        <td>
                            <label for="phone_number">' . htmlspecialchars($lang_data[$selectedLanguage]["phone_number"] ?? "Phone Number") . '</label>
                            <div class="phone-input-wrapper">
                                <input type="tel" id="phone_number" name="phone_number" value="' . htmlspecialchars($phone_number) . '" required>
                                <a href="https://wa.me/+972'.$phone_number.'" class="whatsapp-icon" id="sendWhatsApp" style="right: 3.9vw !important;">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp">
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="country">' . htmlspecialchars($lang_data[$selectedLanguage]["country"] ?? "Country") . '</label>
                            <input type="text" id="country" name="country" value="' . htmlspecialchars($country) . '" required>
                        </td>
                        <td>
                            <label for="group">' . htmlspecialchars($lang_data[$selectedLanguage]["role"] ?? "Role") . '</label>
                            <select style="width: 85%;" id="group" name="group" required>
                                <option value="admins" ' . ($role === 'admins' ? 'selected' : '') . '>Admins</option>
                                <option value="site_managers" ' . ($role === 'site_managers' ? 'selected' : '') . '>Site Managers</option>
                                <option value="drivers" ' . ($role === 'drivers' ? 'selected' : '') . '>Drivers</option>
                                <option value="workers" ' . ($role === 'workers' ? 'selected' : '') . '>Workers</option>
                                <option value="workers" ' . ($role === 'workers' ? 'selected' : '') . '>Workers</option>
                                <option value="disabled" ' . ($role === 'disabled' ? 'selected' : '') . '>Disabled</option>                                
                                <!-- Add more roles as needed -->
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 92%; margin-top: 20px;" type="submit" id="btn-update"">' . htmlspecialchars($lang_data[$selectedLanguage]["update"] ?? "Update") . '</button>
        </form>
    </div>
</div>
    <script>
    
    </script>
';
?>

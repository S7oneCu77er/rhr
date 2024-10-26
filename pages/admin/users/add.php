<?php
// pages/admin/users/add.php

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

    // Handle form submission for adding a new user
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            // Retrieve and sanitize form inputs
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);
            $passportID = trim($_POST['passport_id']);
            $phoneNumber = trim($_POST['phone_number']);
            $country = trim($_POST['country']);
            $description = trim($_POST['description']);
            $group = trim($_POST['group']);

            // Prepare SQL statement to insert a new user
            $sql = "INSERT INTO users (first_name, last_name, passport_id, password, email, phone_number, country, description, `group`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $MySQL->getConnection()->prepare($sql);

            if ($insertStmt) {
                // Bind parameters
                $insertStmt->bind_param("sssssssss", $firstName, $lastName, $passportID, $password, $email, $phoneNumber, $country, $description, $group);

                // Execute the statement
                if ($insertStmt->execute()) {
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['user_add_success'] ?? 'User added successfully.') . "', true);</script>";
                } else {
                    // Log the error for debugging purposes
                    error_log("Error adding user: " . $insertStmt->error);
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['add_error'] ?? 'Error adding user.') . "', true);</script>";
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

$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=users';
$flip   = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? " transform: scaleX(-1);" : "";

// Display the add user form
echo '
<div class="page">
    <h2 style="margin: 15px;">
        <a href="'.$back.'"><img style="width: 18px; height: 18px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '"></a>
        ' . htmlspecialchars($lang_data[$selectedLanguage]["add_user"] ?? "Add User") . '
    </h2>
    <div class="edit-user-page">
        
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
            <table>
                <tbody>
                    <tr>
                        <td>
                            <label for="first_name">' . htmlspecialchars($lang_data[$selectedLanguage]["first_name"] ?? "First Name") . '</label>
                            <input type="text" id="first_name" name="first_name" value="" required>
                        </td>
                        <td>
                            <label for="last_name">' . htmlspecialchars($lang_data[$selectedLanguage]["last_name"] ?? "Last Name") . '</label>
                            <input type="text" id="last_name" name="last_name" value="" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="email">' . htmlspecialchars($lang_data[$selectedLanguage]["email"] ?? "Email") . '</label>
                            <input type="email" id="email" name="email" value="">
                        </td>
                        <td>
                            <label for="password">' . htmlspecialchars($lang_data[$selectedLanguage]["password"] ?? "Password") . '</label>
                            <input class="password" type="text" id="password2" name="password" value="" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="passport_id">' . htmlspecialchars($lang_data[$selectedLanguage]["passport_id"] ?? "Passport/ID") . '</label>
                            <input type="text" id="passport_id" name="passport_id" value="" required>
                        </td>
                        <td>
                            <label for="phone_number">' . htmlspecialchars($lang_data[$selectedLanguage]["phone_number"] ?? "Phone Number") . '</label>
                            <input type="text" id="phone_number" name="phone_number" value="" required>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="country">' . htmlspecialchars($lang_data[$selectedLanguage]["country"] ?? "Country") . '</label>
                            <input type="text" id="country" name="country" value="Israel" required>
                        </td>
                        <td>
                            <label for="group">' . htmlspecialchars($lang_data[$selectedLanguage]["role"] ?? "Role") . '</label>
                            <select style="width: 85%;" id="group" name="group" required>
                                <option value="admins">Admins</option>
                                <option value="site_managers">Site Managers</option>
                                <option value="drivers">Drivers</option>
                                <option value="workers" selected>Workers</option>
                                <option value="disabled">Disabled</option>
                                <!-- Add more roles as needed -->
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <label for="description">' . htmlspecialchars($lang_data[$selectedLanguage]["description"] ?? "Description") . '</label>
                            <input style="width: 92%;" type="text" id="description" name="description" value="">
                        </td>
                    </tr>
                </tbody>
            </table>
            <button style="width: 92%; margin-top: 20px;" type="submit" id="btn-update">' . htmlspecialchars($lang_data[$selectedLanguage]["add"] ?? "Add") . '</button>
        </form>
    </div>
</div>
';
?>

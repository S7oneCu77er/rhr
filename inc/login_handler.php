<?php
// inc/login_handler.php

global $MySQL, $selectedLanguage, $lang_data;
if(isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    return;
}

// Check if the user is already logged in, and verify the user session
if(isset($_SESSION['loggedIn']))
{
    if(!verifyUser($_SESSION['loggedIn']['user_guid']))
    {
        $_SESSION = array();
        session_destroy();
        return;
    }
}


// Sanity checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

if (empty($_POST['passport']) || empty($_POST['password'])) {
    return;
}

$passport = trim($_POST['passport'] ?? '');

// Optional: Additional sanitization
$passport = htmlspecialchars($passport, ENT_QUOTES, 'UTF-8');
$password = $_POST['password']; // Passwords should not be sanitized to preserve characters


// Step 2: Prepare your SQL query using prepared statements
$stmt = $MySQL->getConnection()->prepare("SELECT * FROM users WHERE passport_id = ?");
if ($stmt) {
    // Bind parameters (s = string)
    $stmt->bind_param("s", $passport);

    // Execute the statement
    $stmt->execute();

    // Get the result
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Fetch user data
        $row = $result->fetch_assoc();

        // Directly compare the password (assuming passwords are stored in plain text)
        if ($password === $row['password']) {
            $_SESSION['loggedIn'] = $row;
            header("location: index.php");
        } else {
            // Password is incorrect
            echo "<script>showError('".  htmlspecialchars($lang_data[$selectedLanguage]["incorrect_password"] ?? 'Incorrect password - Please try again') . "');</script>";
        }
    } else {
        // User not found
        echo "<script>showError('".  htmlspecialchars($lang_data[$selectedLanguage]["account_not_found"] ?? 'Account not found') . "');</script>";
    }

    // Close the statement
    $stmt->close();
} else {
    // Handle SQL preparation error
    echo "<script>showError('".  htmlspecialchars($lang_data[$selectedLanguage]["database_error"] ?? 'Database Error') . "');</script>";
}

?>

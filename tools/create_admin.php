<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters
$host = 'localhost';
$dbname = 'rhr';
$username = 'root';
$password = ''; // Update with your actual password

$success = false; // Flag to determine if the user was created successfully
$error = ''; // Variable to hold any error message

try {
    // Connect to the database using PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $passportId = $_POST['passport_id'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $phoneNumber = $_POST['phone_number'];
    $country = $_POST['country'];
    $description = $_POST['description'];
    $group = 'admins';  // Forcefully set to admin

    // Insert new admin user into the users table
    try {
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, passport_id, password, email, phone_number, country, description, `group`) 
                               VALUES (:first_name, :last_name, :passport_id, :password, :email, :phone_number, :country, :description, :group)");

        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':passport_id' => $passportId,
            ':password' => $password,
            ':email' => $email,
            ':phone_number' => $phoneNumber,
            ':country' => $country,
            ':description' => $description,
            ':group' => $group
        ]);

        $success = true; // Set the success flag to true

    } catch (PDOException $e) {
        $error = "Error creating user: " . $e->getMessage(); // Store error message
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            text-align: center;
        }

        .container {
            width: 100%;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: center;
        }

        h1 {
            text-align: center;
            color: #333;
            font-size: 2rem;
            margin-bottom: 20px;
        }

        form {
            margin-top: 20px;
            text-align: center;
        }

        label {
            font-weight: bold;
            margin-top: 15px;
            text-align: left;
            font-size: 1rem;
            color: #333;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 1rem;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        input[type="submit"] {
            padding: 10px 20px;
            margin: 20px 0;
            background-color: #5c9ae1;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: #428bca;
        }

        .success, .error {
            font-size: 1.2rem;
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .success {
            background-color: #eef7f9;
            color: #4caf50;
            border: 1px solid #bee5eb;
        }

        .error {
            background-color: #fbe9e7;
            color: #f44336;
            border: 1px solid #f44336;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #5c9ae1;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .back-btn:hover {
            background-color: #428bca;
        }

        footer {
            text-align: center;
            margin-top: 50px;
            color: #888;
        }

        .form-group {
            margin-bottom: 20px;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus {
            border-color: #5c9ae1;
            outline: none;
            box-shadow: 0 0 10px rgba(92, 154, 225, 0.2);
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Create Admin User</h1>

    <?php if ($success): ?>
        <p class="success">Admin user created successfully.</p>
    <?php elseif ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php else: ?>
        <form method="post" action="create_admin.php">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>

            <div class="form-group">
                <label for="passport_id">Passport ID</label>
                <input type="text" id="passport_id" name="passport_id" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="text" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email">
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" value="0">
            </div>

            <div class="form-group">
                <label for="country">Country</label>
                <input type="text" id="country" name="country" value="Israel">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" value="">
            </div>

            <input type="submit" value="Create Admin User">
        </form>
    <?php endif; ?>

    <a href="index.php" class="back-btn">Back to Tools</a>
</div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>

</body>
</html>


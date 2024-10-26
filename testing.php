<?php
// index.php

// Start session
session_start();

// Include necessary files
require_once './inc/language_handler.php';
global $languages, $lang_data;

// Handle language selection with default to English
$selectedLanguage = isset($_GET['lang']) && in_array($_GET['lang'], $languages) ? $_GET['lang'] : 'English';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Simple Page</title>
    <style>
        /* Embedded CSS styles */
        /* The container should always be same as the screen size, with 5px margin off each side */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            text-align: center;
        }
        body {
            font-family: Arial, sans-serif;
        }
        .container {
            box-sizing: border-box;
            width: calc(100% - 50px);
            height: calc(100% - 25px);
            background-color: #fff;
            border-radius: 15px;
            margin: 25px;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 4px 10px rgba(0,0,0,0.3);

        }
        .header {
            flex: 0 0 auto;
            text-align: center;
        }
        .header img.logo {
            max-width: 100%;
            height: auto;
        }
        .language-selection {
            flex: 0 0 auto;
            text-align: center;
            margin: 5px 0;
        }
        .language-selection .flag_img {
            width: 35px;
            height: 35px;
            margin: 0 5px;
        }
        .user-info {
            flex: 0 0 auto;
            text-align: center;
            margin: 5px 0;
        }
        .page-content {
            flex: 1 1 auto;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .menu {
            flex: 0 0 auto;
            text-align: center;
            margin: 5px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <a href="index.php?lang=<?php echo urlencode($selectedLanguage); ?>">
            <img class="logo" src="img/aia_corp.png" alt="AIA ROSHAL" loading="eager">
        </a>
    </div>
    <!-- Language Flags -->
    <div class="language-selection">
        <?php
        foreach ($languages as $language) {
            $url = 'index.php?lang=' . urlencode($language);
            echo '<a href="' . $url . '">';
            echo '<img alt="' . htmlspecialchars($language) . '" class="flag_img" src="./img/' . htmlspecialchars($language) . '.png" title="' . htmlspecialchars($lang_data[$language]["display"] ?? $language) . '">';
            echo '</a> ';
        }
        ?>
    </div>
    <!-- User and Location Placeholders -->
    <div class="user-info">
        <div class="user">
            <!-- Placeholder for logged in user -->
            <?php
            if (isset($_SESSION['loggedIn'])) {
                echo 'Welcome, ' . htmlspecialchars($_SESSION['loggedIn']['first_name'] . ' ' . $_SESSION['loggedIn']['last_name']);
            } else {
                echo 'Not logged in';
            }
            ?>
        </div>
        <div class="location">
            <!-- Placeholder for location -->
            Location: [Your Location]
        </div>
    </div>
    <!-- Main Page Content -->
    <div class="page-content">
        <!-- Placeholder for page content -->
        <p>Main Page Content Goes Here</p>
    </div>
    <!-- Menu at the Bottom -->
    <div class="menu">
        <!-- Menu items -->
        <p>Menu</p>
    </div>
</div>
</body>
</html>

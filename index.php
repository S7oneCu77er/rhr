<?php
// index.php

//ini_set('display_errors', 1);
//error_reporting(E_ALL);

// Set the default timezone

date_default_timezone_set("Asia/Tel_Aviv");
//header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
//header("Cache-Control: post-check=0, pre-check=0", false);
//header("Pragma: no-cache");

// Start the session
session_start();

// Include configuration and required classes
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php';
require_once './inc/hours_handler.php';
require_once './inc/functions.php';
require_once './inc/action_logs.php';
global $languages, $geo_keys, $lang_data;

// Initialize MySQL handler
$MySQL = new mysql_handler();
$isMobile = isMobileDevice();


// Handle AJAX request for starting & ending shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action'])
    {
        case 'start_shift':
        {
            if (!isset($_SESSION['loggedIn'])) {
                echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                exit();
            }

            $userGuid = $_SESSION['loggedIn']['user_guid'];

            // Get latitude and longitude from POST
            $lat = $_POST['lat'] ?? null;
            $lng = $_POST['lng'] ?? null;

            if ($lat === null || $lng === null) {
                // Return error response
                echo json_encode(['success' => false, 'message' => 'Location data not provided.']);
                exit();
            }

            // Call the startShift function with location data
            $message = startShift($userGuid, $MySQL, $lat, $lng);

            if ($message === null) {
                // Shift started successfully
                echo json_encode(['success' => true, 'message' => 'Shift started successfully']);
            } else {
                // Return error message
                echo json_encode(['success' => false, 'message' => $message]);
            }
            exit();
        }
        break;
        case 'end_shift':
        {
            if (!isset($_SESSION['loggedIn'])) {
                echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                exit();
            }

            $userGuid = $_SESSION['loggedIn']['user_guid'];

            // Call the endShift function
            $message = endShift($userGuid, $MySQL);

            if ($message === null) {
                // Shift started successfully
                echo json_encode(['success' => true, 'message' => '']);
            } else {
                // Return error message
                echo json_encode(['success' => false, 'message' => $message]);
            }
            exit();
        }
        break;
        default:
            exit();
    }
}

// Handle language selection with default to English
$selectedLanguage = isset($_GET['lang']) && in_array($_GET['lang'], $languages) ? $_GET['lang'] : getDefaultLanguage();
$direction  = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "rtl" : "ltr";
$align  = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "right" : "left";

// Determine HTML direction based on selected language
$htmlAttributes = ($selectedLanguage === "Hebrew" || $selectedLanguage === "Arabic") ? 'lang="he" dir="rtl"' : 'lang="en" dir="ltr"';
$scale = $isMobile ? '0.9' : '1';
?>
<!DOCTYPE html>
<html <?php echo $htmlAttributes; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, height=device-height, initial-scale=<?php echo $scale; ?>, user-scalable=yes">
    <title>Human Resources System</title>
    <link rel="stylesheet" href="css/styles.css">
    <!-- Include Google Maps API script -->
    <script async src="https://maps.googleapis.com/maps/api/js?language=<?php echo $geo_keys[$selectedLanguage]; ?>&loading=async&key=AIzaSyAiis9wACSZkGmA05MbavxRr1Zmy0XM_W0"></script>
    <!-- Include main JavaScript file -->
    <script src="js/main.js"></script>
    <script>
        function saveLogin()
        {
            let passport = document.getElementById('passport').value;
            let password = document.getElementById('password').value;
            let rememberMeChecked = document.getElementById('remember_me').checked;

            if (rememberMeChecked) {
                // Save to localStorage if "Remember Me" is checked
                localStorage.setItem('passport', passport);
                localStorage.setItem('password', password);
                localStorage.setItem('remember_me', true);
            } else {
                // Remove items from localStorage if "Remember Me" is not checked
                localStorage.removeItem('passport');
                localStorage.removeItem('password');
                localStorage.setItem('remember_me', false);
            }
        }
    </script>
</head>
<?php if(isset($_SESSION['loggedIn']) && !isset($_GET['logout'])) { ?>
    <body onload="initializePage();">
<?php    } else { ?>
    <body>
<?php } ?>
<div class="container">
    <!-- Error Frame -->
    <div id="errorFrame" class="error" style="display: none;">
        <div class="error-content">
            <div id="errorText">An error occurred, please reload</div>
            <span id="errorClose" class="close" onclick="closeError()">&times;</span>
        </div>
    </div>

    <!-- Logo -->
    <div class="logo-container">
        <a href="index.php?lang=<?php echo $selectedLanguage; ?>"><img class="logo" src="img/aia_corp.png" alt="AIA ROSHAL" loading="eager"></a>
    </div>

    <!-- Language Selection -->
    <div class="language" style="text-align: center;">
        <?php
        foreach ($languages as $language) {
            $url = 'index.php?lang=' . urlencode($language);
            foreach($_GET as $key => $value) {
                if($key == 'lang') continue;
                $url .= '&' . urlencode($key) . '=' . urlencode($value);
            }
            echo '<a href="' . $url . '">';
            echo '<img alt="' . htmlspecialchars($language) . '" class="flag_img" src="./img/' . htmlspecialchars($language) . '.png" title="' . htmlspecialchars($lang_data[$language]["display"]) . '">';
            echo '</a> ';
        }
        ?>
    </div>

    <?php
    // Include login handler
    include './inc/login_handler.php';

    if (!isset($_SESSION['loggedIn']))
    {
        // User is not logged in, show login form
        ?>
        <div class="page">
            <form action="index.php?lang=<?php echo urlencode($selectedLanguage); ?>" onsubmit="saveLogin()" method="post">
                <div class="login_page">
                    <label for="passport"><?php echo htmlspecialchars($lang_data[$selectedLanguage]["passport_id"]); ?></label>
                    <input type="text" class="passport" id="passport" name="passport" required autocomplete="on">

                    <label for="password"><?php echo htmlspecialchars($lang_data[$selectedLanguage]["password"]); ?></label>
                    <div class="password-group">
                        <input class="password" type="password" id="password" name="password" required autocomplete="current-password">
                        <button  class="toggle" type="button" id="toggle" onclick="togglePassword()" title="Show/Hide Password">👁️</button>
                    </div>
                    <div style="text-align: <?php echo $align; ?>; font-size: 0.85rem; margin-top: -8px; margin-bottom: 8vh;">
                        <label dir="<?php echo $direction; ?>" style="width: calc(90vw - 85px) !important; position: relative; text-align: <?php echo $align; ?>; <?php echo $align; ?>: -35px;" for="remember_me" style="font-weight: normal;">
                            <input type="checkbox" id="remember_me" name="remember_me" style="position: relative; top:  8px; width: 12px; height: 12px;">
                            <?php echo htmlspecialchars($lang_data[$selectedLanguage]["remember_me"] ?? "Remember Me"); ?>
                        </label>
                    </div>
                </div>
                <div class="form-login">
                    <button class="login" id="login" type="submit" title="<?php echo htmlspecialchars($lang_data[$selectedLanguage]["login"]); ?>">
                        <?php echo htmlspecialchars($lang_data[$selectedLanguage]["login"]); ?>
                    </button>
                </div>
            </form>
        </div>
        <script>
            // Load values from localStorage if available
            document.addEventListener('DOMContentLoaded', function() {
                const storedPassport = localStorage.getItem('passport');
                const storedPassword = localStorage.getItem('password');
                const rememberMe     = localStorage.getItem('remember_me') === 'true';

                if (storedPassport) {
                    document.getElementById('passport').value = storedPassport;
                }
                if (storedPassword) {
                    document.getElementById('password').value = storedPassword;
                }
                document.getElementById('remember_me').checked = rememberMe;
            });
        </script>
    <?php
    } else {
        // User is logged in
        ?>
        <div class="welcome">
            <span id="clock">[TIME]</span>
            <?php echo htmlspecialchars($lang_data[$selectedLanguage]["welcome"]); ?>
            [<?php echo htmlspecialchars($_SESSION['loggedIn']['first_name'] . " " . $_SESSION['loggedIn']['last_name']); ?>]
            <a href="index.php?lang=<?php echo urlencode($selectedLanguage); ?>&logout=true">
                <?php echo htmlspecialchars($lang_data[$selectedLanguage]["logout"]); ?>
            </a>
        </div>
        <div id="location" class="location"><?php echo htmlspecialchars($lang_data[$selectedLanguage]["loading_location"]); ?></div>

        <?php
        $default = '';
        // Determine the default page to include
        if(isset($_GET['page']))
            $default = $_GET['page'];
        else {
            if($_SESSION['loggedIn']['group'] === 'admins')
                $default = 'dashboard';
            else if($_SESSION['loggedIn']['group'] === 'site_managers')
                $default = 'site_management';
            else $default = 'hours';
        }
        $page = $default;
        // Initialize output variable
        $output = '';

        switch ($page) {
            case 'hours':
                include './pages/hours.php';
                break;
            case 'history':
                include './pages/history.php';
                break;
            case 'docs_data':
                include './pages/docs_data.php';
                break;
            case 'view':
                include './pages/view.php';
                break;
            case 'alerts':
                include './pages/alerts.php';
                break;
            case 'assignments':
                include './pages/assignments.php';
                break;
            case 'open_ticket':
                include './pages/open_ticket.php';
                break;
            case 'admin':
                if ($_SESSION['loggedIn']['group'] !== 'admins') {
                    $output = "<div class='page'><script>showError('Unauthorized access', true);</script></div>";
                } else {
                    include './pages/admin.php';
                }
                break;
            case 'site_management':
                if ($_SESSION['loggedIn']['group'] !== 'site_managers') {
                    $output = "<div class='page'><script>showError('Unauthorized access', true);</script></div>";
                } else {
                    include './pages/site_management.php';
                }
                break;
            case 'dashboard':
                include './pages/dashboard.php';
                break;
            default:
                $output = "<br><br><br><br><div class='page'>Page not found</div>";
                break;
        }

        // Display the output
        echo $output;
        ?>

        <!-- Top Menu -->
        <?php
        $menu = [
                "admins" => [
                    'bottom_menu' => [
                        //'assignments' => '',
                        'history' => '_big',
                        'docs_data' => '_big',
                        //'settings' => ''
                    ],
                    'top_menu' => [
                        'admin' => '_big',
                        'alerts'  => '_big'
                    ]
                ],
                "site_managers" => [
                    'bottom_menu' => [
                        'history' => '_big',
                        'docs_data' => '_big',
                    ],
                    'top_menu' => [
                        'site_management' => '_big',
                        'open_ticket' => '_big'
                    ]
                ],
                "drivers" => [
                    'bottom_menu' => [
                        'open_ticket' => '',
                        'history' => '',
                        'docs_data' => '',
                        'settings' => ''
                    ],
                    'top_menu' => [
                        'hours' => '_big',
                        'assignments'  => '_big'
                    ]
                ],
                "workers" => [
                    'bottom_menu' => [
                        'open_ticket' => '',
                        'history' => '',
                        'docs_data' => '',
                        'day_off' => ''
                    ],
                    'top_menu' => [
                        'hours' => '_big',
                        'assignments'  => '_big'
                    ]
                ]
        ];
        echo '
        <div class="menu_top">';
        foreach ($menu[$_SESSION['loggedIn']['group']]['top_menu'] as $key => $size) {
            $isSelected = ((isset($_GET['page']) && $_GET['page'] === $key ) || $default === $key) ? ' id="selected' . $size .'"' : ($size == '_big' ? ' id="big_menu_button"' : '');
            echo '
            <div' . $isSelected . '><a href="index.php?lang=' . urlencode($selectedLanguage) . '&page=' . $key . '">' . nl2br(htmlspecialchars($lang_data[$selectedLanguage][$key])) . '</a>
            ';
            if($key == 'alerts')
            {
                echo '
                <div id="notification-badge">
                    <span id="notification-number">'.count(getAllAlerts()).'</span> <!-- Number of notifications -->
                </div>';
            }
            if($key == 'admin')
            {
                $dir  = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "left" : "right";
                $link = "index.php?lang=" . urlencode($selectedLanguage) . "&page=admin&sub_page";
                $assignments_attention_count = checkAssignmentsNeedAttention();
                if($assignments_attention_count == 0) {
                    $bg_color = 'rgba(130, 255, 0, 0.9)';
                    $font_color = 'black';
                    $border_color = 'black';
                }
                else {
                    $bg_color = 'rgba(255, 0, 0, 0.8)';
                    $font_color = 'white';
                    $border_color = 'yellow';
                }

                echo '
                <div id="admin-menu-list" style="'.$dir.': calc(50% - 45px) !important; background: transparent !important; opacity: 0 !important;">
                    <span>
                        <a style="all: unset !important;" href="' . $link . '=users" data-menu="users">
                            <img id="admin_menu_item" src="img/users.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["users"]) . '">
                        </a>
                        <div class="submenu" data-submenu="users" style="position: absolute; top: -1.5px;">
                            <a style="all: unset !important;" href="' . $link . '=users">
                                <img style="border: 1px solid black;" id="admin_menu_item" src="img/add.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["users"]) . '">
                            </a>
                            <a style="all: unset !important;" href="' . $link . '=users">
                                <img style="border: 1px solid black; position: relative; '.($dir=='left'?'right':'left').': 8px !important;" class="admin_menu_item" id="admin_menu_item" src="img/edit.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["users"]) . '">
                            </a>
                        </div>
                        <a style="all: unset !important;" href="' . $link . '=workers" data-menu="workers">
                            <img id="admin_menu_item" src="img/workers.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["workers"]) . '">
                        </a>
                        <div class="submenu" data-submenu="workers" style="position: absolute; top: 51px;">
                            <a style="all: unset !important;" href="' . $link . '=workers$action=add">
                                <img style="border: 1px solid black;" id="admin_menu_item" src="img/add.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["add"]) . '">
                            </a>
                        </div>
                        <a style="all: unset !important;" href="' . $link . '=houses" data-menu="houses">
                            <img id="admin_menu_item" src="img/houses.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["houses"]) . '">
                        </a>
                        <div class="submenu" data-submenu="houses" style="position: absolute; top: 102px;">
                            <a style="all: unset !important;" href="' . $link . '=workers$action=add">
                                <img style="border: 1px solid black;" id="admin_menu_item" src="img/add.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["add"]) . '">
                            </a>
                        </div>
                        <a style="all: unset !important;" href="' . $link . '=cars" data-menu="cars">
                            <img id="admin_menu_item" src="img/cars.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["cars"]) . '">
                        </a>
                        <div class="submenu" data-submenu="cars" style="position: absolute; top: 153px;">
                            <a style="all: unset !important;" href="' . $link . '=workers$action=add">
                                <img style="border: 1px solid black;" id="admin_menu_item" src="img/add.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["add"]) . '">
                            </a>
                        </div>
                        <a style="all: unset !important;" href="' . $link . '=sites" data-menu="sites">
                            <img id="admin_menu_item" src="img/sites.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["sites"]) . '">
                        </a>  
                        <div class="submenu" data-submenu="sites" style="position: absolute; top: 204px;">
                            <a style="all: unset !important;" href="' . $link . '=workers$action=add">
                                <img style="border: 1px solid black;" id="admin_menu_item" src="img/add.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["add"]) . '">
                            </a>
                        </div>                      
                        <a style="all: unset !important;" href="' . $link . '=assignments" data-menu="assignments">
                            <img id="admin_menu_item" src="img/assignments.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["assignments"]) . '">
                        </a>
                        <div id="notification-badge_menu" style="bottom: 200px; border: 1px solid '.$border_color.' !important; color: '.$font_color.' !important; background: '.$bg_color.' !important;">
                            <span id="notification-number">'.$assignments_attention_count.'</span> <!-- Number of notifications -->
                        </div>
                        <a style="all: unset !important;" href="' . $link . '=shifts" data-menu="shifts">
                            <img id="admin_menu_item" src="img/shifts.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["shifts"]) . '">
                        </a>
                        <a style="all: unset !important;" href="' . $link . '=documents" data-menu="documents">
                            <img id="admin_menu_item" src="img/documents.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["documents"]) . '">
                        </a>
                        <a style="all: unset !important;" href="' . $link . '=complaints" data-menu="complaints">
                            <img id="admin_menu_item" src="img/complaints.png" title="' . htmlspecialchars($lang_data[$selectedLanguage]["complaints"]) . '">
                        </a>
                        <div id="notification-badge_menu" style="bottom: 45px; border: 1px solid yellow !important; color: white !important; background: red !important;">
                            <span id="notification-number">0</span> <!-- Number of notifications -->
                        </div>
                    </span>
                </div>
                <div onclick="toggleMainMenu();" id="admin-menu-badge" style="'.$dir.': calc(50% + 1px) !important;">
                    <span id="admin_menu_btn"></span>
                </div>';
            }
            echo '
            </div>';

        }
        echo
        '</div>';
        if(isset($menu[$_SESSION['loggedIn']['group']]['bottom_menu']))
        {
            echo '
            <div class="menu_bottom">';
            foreach ($menu[$_SESSION['loggedIn']['group']]['bottom_menu'] as $key => $size) {
                $isSelected = ((isset($_GET['page']) && $_GET['page'] === $key ) || $default === $key) ? ' id="selected' . $size .'"' : ($size == '_big' ? ' id="big_menu_button"' : '');
                echo '
                <div' . $isSelected . '><a href="index.php?lang=' . urlencode($selectedLanguage) . '&page=' . $key . '">' . nl2br(htmlspecialchars($lang_data[$selectedLanguage][$key])) . '</a></div>';
            }
            echo
            '</div>';
        }
    }
    ?>

        <!-- Landscape Warning for Mobile Devices -->
        <div id="warning" class="landscape-warning">
            <?php echo htmlspecialchars($lang_data[$selectedLanguage]["rotate_device"]); ?>
        </div>
        <script>
            if (!isMobileDevice()) {
                document.getElementById('warning').style.display = 'none';
            }


        </script>
    </div>
</div>
<script>
    <?php if(isset($_SESSION['loggedIn'])) { ?>
    function toggleMainMenu()
    {
        const menu      = document.getElementById("admin-menu-list");
        const menuBtn   = document.getElementById("admin-menu-badge");
        if (menu.style.opacity === "0" || menu.style.opacity === "") {
            menuBtn.style.backgroundImage = "url('../img/down.png')";

            menu.style.height = "510px";
            menu.style.maxHeight = "510px";
            menu.style.transform = 'scaleY(1)';
            menu.style.opacity = '1';
            menu.classList.add('open');
        } else {
            menu.style.height = '0';
            menu.style.maxHeight = '0';
            menu.style.transform = 'scaleY(0)';
            menu.style.opacity = '0';
            menu.classList.remove('open');
            menuBtn.style.backgroundImage = "url('../img/up.png')";
        }
    }

    // Function to close the menu if clicked outside
    function closeMenu_onClick(event) {
        const menu = document.getElementById("admin-menu-list");
        const menuBtn = document.getElementById("admin-menu-badge");
        // Check if the clicked element is NOT the menu or the toggle button
        if (!menu.contains(event.target) && !menuBtn.contains(event.target)) {
            // If the menu is open, close it
            if (menu.classList.contains("open")) {
                menu.style.height = '0';
                menu.style.maxHeight = '0';
                menu.style.transform = 'scaleY(0)';
                menu.style.opacity = '0';
                menu.classList.remove('open');
                menuBtn.style.backgroundImage = "url('../img/up.png')";
            }
        }
    }

    // Add event listener to the entire document
    document.addEventListener('click', closeMenu_onClick);

    document.addEventListener('DOMContentLoaded', function() {
        // Select all menu items with the "data-menu" attribute
        const menuItems = document.querySelectorAll('[data-menu]');

        menuItems.forEach(menuItem => {
            const menuName = menuItem.getAttribute('data-menu');
            const submenu = document.querySelector(`[data-submenu="${menuName}"]`);

            if (submenu) {
                submenu.style.display = 'none';

                // Show the submenu when hovering over the menu item
                menuItem.addEventListener('mouseenter', function() {
                    submenu.style.display = 'flex';
                });

                // Keep the submenu visible when hovering over the submenu
                submenu.addEventListener('mouseenter', function() {
                    submenu.style.display = 'flex';
                });

                // Hide the submenu when the mouse leaves both the menu item and submenu
                menuItem.addEventListener('mouseleave', function() {
                    setTimeout(() => {
                        // Delay to ensure mouseenter for submenu is triggered
                        if (!submenu.matches(':hover')) {
                            submenu.style.display = 'none';
                        }
                    }, 150);
                });

                submenu.addEventListener('mouseleave', function() {
                    setTimeout(() => {
                        // Delay to ensure mouseenter for submenu is triggered
                        if (!menuItem.matches(':hover')) {
                            submenu.style.display = 'none';
                        }
                    }, 150);
                });
            }
        });
    });
    <?php } ?>

</script>
<br>
<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>
    <style>
        /* Submenu - initially hidden */
        .submenu {
            position: absolute !important; /* Position it below the parent menu item */
            <?php echo $dir; ?>: -10px;
            cursor: pointer;
            background: rgba(0,0,0,0) !important;
            height: 0 !important;
            width: 0 !important;
            border: none !important;
            flex-direction: row !important;
            justify-content: flex-start !important;
            align-items: flex-start !important;
        }

    </style>
</body>
</html>

<?php
// Clean up
unset($MySQL);
?>

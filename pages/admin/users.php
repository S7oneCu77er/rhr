<?php
// pages/admin/users.php

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
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "');</script>";
    exit();
}

// Get sorting options from URL
$sort_by = $_GET['sort_by'] ?? 'user_guid'; // Default sorting column
$sort_order = $_GET['sort_order'] ?? 'asc'; // Default sorting order

// Fetch all users with sorting
$users = fetchAllUsers($sort_by, $sort_order);

// Handle potential errors
if ($users === false) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "');</script>";
    exit();
}

$url = 'index.php?lang=' . urlencode($selectedLanguage);
foreach($_GET as $key => $value) {
    if($key == 'lang' || $key == 'sort_by' || $key == 'sort_order') continue;
    $url .= '&' . urlencode($key) . '=' . urlencode($value);
}

$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin';

$align  = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "left" : "right";
$dir    = $align == "left" ? "right" : "left";
$flip   = $align == "left" ? " transform: scaleX(-1);" : "";

// Determine next sorting order (toggle between 'asc' and 'desc')
function toggleSortOrder($current_order) {
    return $current_order === 'asc' ? 'desc' : 'asc';
}

// Display the users management interface
echo '
    <div class="page">
        <div class="user-list" id="users_table" style="display: none;">
            <table>
                <thead>
                    <tr>
                        <td colspan="5" style="padding: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin: 0; padding: 0;">
                                <a href="' . $back . '" style="display: block; flex-grow: 1; text-align: '.$dir.'; margin: 0; padding: 0;">
                                    <img style="height: 50px; width: 50px;'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '">
                                </a>
                                <a href="" style="display: block; flex-grow: 1; text-align: '.$dir.'; margin: 0; padding: 0;">
                                    <img style="height: 50px; width: 50px;" class="manage_shift_btn" src="img/download.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['download'] ?? 'Download') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['download'] ?? 'Download') . '">
                                </a>
                                <a href="" style="display: block; flex-grow: 1; text-align: center; margin: 0; padding: 0;" id="filter-btn">
                                    <img style="height: 50px; width: 50px;" class="manage_shift_btn" src="img/search.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['search'] ?? 'Search') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['search'] ?? 'Search') . '">
                                </a>
                                <!-- Floating menu -->
                                <div id="floating-menu" style="display: none; position: absolute; top: 60px; left: 50%; transform: translateX(-50%); background-color: white; border: 1px solid #ccc; padding: 20px; z-index: 100;">
                                    <h3>Filter Users</h3>
                                    <form id="filter-form">
                                        <!-- Make sure to pass the language and other necessary parameters -->
                                        <input type="hidden" name="lang" value="'.htmlspecialchars($selectedLanguage).'">
                                        
                                        <!-- Filter Form -->
                                        <div class="filter-form" id="filter-form">
                                    
                                            <!-- Group Filter -->
                                            <label for="group-filter">By Group</label>
                                            <select id="group-filter" name="group">
                                                <option value="">-- Select Group --</option>
                                                <option value="admins"'.(isset($_GET['search_group'] ) && $_GET['search_group'] === "admins" ? " selected" : "").'>Admins</option>
                                                <option value="site_managers"'.(isset($_GET['search_group'] ) && $_GET['search_group'] === "site_managers" ? " selected" : "").'>Site Managers</option>
                                                <option value="drivers"'.(isset($_GET['search_group'] ) && $_GET['search_group'] === "drivers" ? " selected" : "").'>Drivers</option>
                                                <option value="workers"'.(isset($_GET['search_group'] ) && $_GET['search_group'] === "workers" ? " selected" : "").'>Workers</option>
                                            </select>
                                    
                                            <br><br>
                                    
                                            <!-- Country Filter -->
                                            <label for="country-filter">By Country</label>
                                            <select id="country-filter" name="country">
                                                <option value="">-- Select Country --</option>
                                                <option value="India"'.(isset($_GET['search_country'] ) && $_GET['search_country'] === "India" ? "selected" : "").'>India</option>
                                                <option value="Sri Lanka"'.(isset($_GET['search_country'] ) && $_GET['search_country'] === "Sri Lanka" ? "selected" : "").'>Sri Lanka</option>
                                                <option value="Israel"'.(isset($_GET['search_country'] ) && $_GET['search_country'] === "Israel" ? "selected" : "").'>Israel</option>
                                                <option value="Moldova"'.(isset($_GET['search_country'] ) && $_GET['search_country'] === "Moldova" ? "selected" : "").'>Moldova</option>
                                            </select>
                                    
                                            <br><br>
                                    
                                            <!-- Fragmented Data Filter -->
                                            <div style="display: flex; align-items: center; justify-content: center; width: 100%;">
                                                <label style="width: 90%; display: flex; justify-content: flex-start;" for="fragmented-filter" style="margin-right: auto; white-space: nowrap">Fragmented Data:</label>
                                                <input style="width: 10%;" type="checkbox" id="fragmented-filter" name="fragmented-filter" value="1"'.(isset($_GET["filter_fragmented"]) && $_GET["filter_fragmented"] == 1 ? "checked" : "").'>
                                            </div>
                                            
                                            <!-- Missing Data Filter -->
                                            <div style="margin-top:5px; display: flex; align-items: center; justify-content: center; width: 100%;">
                                                <label style="width: 90%; display: flex; justify-content: flex-start;" for="missing-data-filter" style="margin-right: auto; white-space: nowrap">Missing Data:</label>
                                                <input style="width: 10%;" type="checkbox" id="missing-data-filter" name="missing-data-filter" value="1"'.(isset($_GET["filter_missing_data"]) && $_GET["filter_missing_data"] == 1 ? "checked" : "").'>
                                            </div>
                                    
                                            <br>
                                    
                                            <!-- Apply and Clear Filters -->
                                            
                                        </div>
                                        <button type="submit" style="background-color: green; color: white;">Apply</button>
                                        <button type="button" onclick="clearFilters();" style="background-color: red; color: white;">Clear</button>
                                    </form>
                                </div>
                                <a href="" style="display: block; flex-grow: 1; text-align: '.$align.'; margin: 0; padding: 0;">
                                    <img style="height: 50px; width: 50px;" class="manage_shift_btn" src="img/add-list.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['import'] ?? 'Import') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['import'] ?? 'Import') . '">
                                </a>
                                <a href="' . $url . '&action=add" style="display: block; flex-grow: 1; text-align: '.$align.'; margin: 0; padding: 0;">
                                    <img style="height: 50px; width: 50px;" class="manage_shift_btn" src="img/add.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['add'] ?? 'Add') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['add'] ?? 'Add') . '">
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <a style="color: black; text-decoration: underline; white-space: nowrap;" href="' . $url . '&sort_by=first_name&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["name"] ?? "Name") . '
                            </a>';
if(!isset($_GET['search_name']) || $_GET['search_name'] == "")
{
    echo '
                                <img src="img/search-icon.png" class="search-icon" data-column="name">';
}
else
{
    echo '
                                <img src="img/search_undo.png" onclick="undoSearch(\'name\');" class="search-undo">';
}
echo '
                        </th>
                        <th style="font-weight: bolder; font-size: 0.75rem; white-space: nowrap;">
                            <a style="color: black; text-decoration: underline;" href="' . $url . '&sort_by=phone_number&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["phone"] ?? "Phone") . '
                            </a>';
if(!isset($_GET['search_phone_number']) || $_GET['search_phone_number'] == "")
{
    echo '
                                <img src="img/search-icon.png" class="search-icon" data-column="phone_number">';
}
else
{
    echo '
                                <img src="img/search_undo.png" onclick="undoSearch(\'phone_number\');" class="search-undo">';
}
echo '
                        </th>
                        <th style="font-weight: bolder; font-size: 0.75rem;">
                            ' . htmlspecialchars($lang_data[$selectedLanguage]["role"] ?? "Role") . '
                        </th>
                        <th style="white-space: nowrap; width: 1%; font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? "Actions") . '</th>
                    </tr>
                </thead>
                <tbody>';

if (count($users) > 0) {
    foreach ($users as $user) {
        $guid = htmlspecialchars($user['user_guid']);
        $name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $phone = htmlspecialchars($user['phone_number']);
        $role = htmlspecialchars($user['group']);
        $group = htmlspecialchars(ucfirst($lang_data[$selectedLanguage]["_{$role}"] ?? $user['group']));

        echo "
            <tr>
                <td style='white-space: wrap; font-size: 0.60rem;'>{$name}</td>
                <td style='white-space: nowrap; width: 1%; max-width: max-content; font-size: 0.72rem;'>";
                    if($phone && $phone != '' && $phone != 0)
                        echo "<a href='https://wa.me/+972{$phone}' id='sendWhatsApp'>{$phone} <img id='small_whatsapp_icon' src='https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg' alt='WhatsApp'></a>";
                    else
                        echo htmlspecialchars($lang_data[$selectedLanguage]["missing_data"] ?? "Missing Data");
                echo "
                </td>
                <td style='font-size: 0.72rem;'>{$group}</td>
                <td style='white-space: nowrap; width: 1%; max-width: max-content;'>
                    <a href='{$url}&action=edit&user_guid={$guid}'><img class='manage_shift_btn' src='img/edit.png' alt='".htmlspecialchars($lang_data[$selectedLanguage]["edit"] ?? "Edit")."' title='".htmlspecialchars($lang_data[$selectedLanguage]["edit"] ?? "Edit")."'></a>
                    <a href='{$url}&action=delete&user_guid={$guid}'><img class='manage_shift_btn' src='img/delete.png' alt='".htmlspecialchars($lang_data[$selectedLanguage]["delete"] ?? "Delete")."' title='".htmlspecialchars($lang_data[$selectedLanguage]["delete"] ?? "Delete")."'></a>
                </td>
            </tr>
        ";
    }
} else {
    echo '<tr><td colspan="7">' . htmlspecialchars($lang_data[$selectedLanguage]["no_users_found"] ?? "No users found.") . '</td></tr>';
}

echo '
                </tbody>
            </table>
        </div>
    </div>';

?>

<script>
    document.querySelectorAll(".search-icon").forEach(function(icon) {
        icon.addEventListener("click", function() {
            const column = this.getAttribute("data-column");
            let inputBox = document.querySelector("#search-input-" + column);

            // Clear the input value each time search is opened
            if (inputBox) {
                document.body.removeChild(inputBox);
            }

            const urlParams = new URLSearchParams(window.location.search);
            const search_box_direction = (urlParams.get('lang') === 'Hebrew' || urlParams.get('lang') === 'Arabic' ? 'plus' : 'minus');
            const search_box_distance = window.innerWidth > 2200 ? 136 : (window.innerWidth > 2050 ? 115 : (window.innerWidth > 1850 ? 105 : (window.innerWidth > 1550 ? 90 : (window.innerWidth > 1050 ? 65 : (window.innerWidth > 900 ? 40 : (window.innerWidth > 650 ? 25 : (window.innerWidth > 550 ? 15 : 8)))))));

            inputBox = document.createElement("input");
            inputBox.setAttribute("type", "text");
            inputBox.setAttribute("class", "search_box");
            inputBox.setAttribute("id", "search-input-" + column);
            inputBox.style.position = "absolute";
            inputBox.style.top = (this.getBoundingClientRect().bottom + window.scrollY) + 5 + "px";

            // Calculate left position to center the search box under the icon
            const iconWidth = this.getBoundingClientRect().width; // Get the width of the search icon
            const searchBoxWidth = 15; // Set the width of the search box
            inputBox.style.left = (this.getBoundingClientRect().left + window.scrollX + (iconWidth / 2) - (search_box_direction === 'minus' ? ((((window. innerWidth/100)*searchBoxWidth) / 2) - search_box_distance) : (((window. innerWidth/100)*searchBoxWidth) / 2) + search_box_distance)) + "px";

            inputBox.style.width = searchBoxWidth + "%"; // Set the width of the search box

            // Restore previous search param if it exists
            if (sessionStorage.getItem("search-" + column)) {
                inputBox.value = sessionStorage.getItem("search-" + column);
            }

            document.body.appendChild(inputBox);

            inputBox.focus();
            inputBox.addEventListener("blur", function() {
                // Remove the input box after search
                document.body.removeChild(inputBox);
            });

            // Trigger search on Enter key press
            inputBox.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    // Trigger search on blur
                    performSearch(column, inputBox.value);

                    // Remove the input box after search
                    document.body.removeChild(inputBox);
                }
            });
        });
    });

    function undoSearch(column)
    {
        performSearch(column, "");
    }

    function performSearch(column, searchTerm) {
        // Save the search param in session storage
        sessionStorage.setItem("search-" + column, searchTerm);

        // Add the search term to the URL parameters and reload the page
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('search_' + column, searchTerm);

        // Navigate to the new URL with the search terms included
        window.location.search = urlParams.toString();
    }

    // Hide menu if clicked outside
    window.addEventListener('click', function(event) {
        const menu = document.getElementById('floating-menu');
        const button = document.getElementById('filter-btn');
        if (event.target !== menu && event.target !== button && !menu.contains(event.target) && menu.style.display === 'none') {
            menu.style.display = 'none';
        }
    });

    document.getElementById('filter-btn').addEventListener('click', function(e) {
        e.preventDefault();
        const menu = document.getElementById('floating-menu');
        if (menu.style.display === 'none') {
            menu.style.display = '';
        } else {
            menu.style.display = 'none';
        }
    });



    // Handle form submission and filter application
    document.getElementById('filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const group = document.getElementById('group-filter').value;
        const country = document.getElementById('country-filter').value;
        const fragmented = document.getElementById('fragmented-filter').checked;
        const missing = document.getElementById('missing-data-filter').checked;

        // Modify URL parameters with filters
        const urlParams = new URLSearchParams(window.location.search);

        if (group) {
            urlParams.set('search_group', group);
        } else {
            urlParams.delete('search_group');
        }

        if (country) {
            urlParams.set('search_country', country);
        } else {
            urlParams.delete('search_country');
        }

        if (fragmented) {
            urlParams.set('filter_fragmented', '1');
        } else {
            urlParams.delete('filter_fragmented');
        }

        if (missing) {
            urlParams.set('filter_missing_data', '1');
        } else {
            urlParams.delete('filter_missing_data');
        }

        // Apply filters by reloading the page with the updated URL
        window.location.search = urlParams.toString();
    });

    function clearFilters() {
        // Redirect to the same page without any query parameters
        window.location.href = "index.php?lang=<?php echo htmlspecialchars($selectedLanguage); ?>&page=admin&sub_page=users";
    }

    // Show the table after the bottom menu has rendered
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('users_table').style.display = '';
    });
</script>

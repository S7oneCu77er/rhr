<?php
// pages/admin/workers.php

global $lang_data, $selectedLanguage, $MySQL;

require_once './inc/functions.php';
require_once './inc/mysql_handler.php';
require_once './inc/language_handler.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "');</script>";
    exit();
}

// Get sorting options from URL
$sort_by = $_GET['sort_by'] ?? 'worker_id'; // Default sorting column
$sort_order = $_GET['sort_order'] ?? 'asc'; // Default sorting order

// Fetch all users with sorting
$users = fetchAllWorkers($sort_by, $sort_order);

// Handle potential errors
if ($users === false) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

$url = 'index.php?lang=' . urlencode($selectedLanguage);
foreach($_GET as $key => $value) {
    if ($key == 'lang' || $key == 'sort_by' || $key == 'sort_order') continue;
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

echo '
    <div class="page">
        <div class="user-list" id="workers_table" style="display: none;">
            <table style="width: 100vw;">
                <thead>
                    <tr>
                        <td colspan="6" style="padding: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin: 0; padding: 0;">
                                <a href="' . $back . '" style="display: block; flex-grow: 1; text-align: '.$dir.'; margin: 0; padding: 0;">
                                    <img id="top_menu_button" style="'.$flip.'" class="manage_shift_btn" src="img/back.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['go_back'] ?? 'Go Back') . '">
                                </a>
                                <a href="" style="display: block; flex-grow: 1; text-align: '.$dir.'; margin: 0; padding: 0;">
                                    <img id="top_menu_button" class="manage_shift_btn" src="img/download.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['download'] ?? 'Download') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['download'] ?? 'Download') . '">
                                </a>
                                <a href="" style="display: block; flex-grow: 1; text-align: center; margin: 0; padding: 0;" id="filter-btn">
                                    <img id="top_menu_button_search" class="manage_shift_btn" src="img/search.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['search'] ?? 'Search') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['search'] ?? 'Search') . '">
                                </a>
                                <!-- Floating menu -->
                                <div id="floating-menu" style="display: none; position: absolute; top: 60px; left: 50%; transform: translateX(-50%); background-color: white; border: 1px solid #ccc; padding: 20px; z-index: 100;">
                                    <h3>Filter Workers</h3>
                                    <form id="filter-form">
                                        <!-- Make sure to pass the language and other necessary parameters -->
                                        <input type="hidden" name="lang" value="'.htmlspecialchars($selectedLanguage).'">
                                        
                                        <!-- Filter Form -->
                                        <div class="filter-form" id="filter-form">
                                        
                                            <!-- Relief Status Filter -->
                                            <label for="relief-filter">By Relief Status</label>
                                            <select id="relief-filter" name="relief_status">
                                                <option value="">-- Select --</option>
                                                <option value="on_relief"'.(isset($_GET['relief_status'] ) && $_GET['relief_status'] === "on_relief" ? " selected" : "").'>On Relief</option>
                                                <option value="not_on_relief"'.(isset($_GET['relief_status'] ) && $_GET['relief_status'] === "not_on_relief" ? " selected" : "").'>Not on Relief</option>
                                            </select>
                                        
                                            <br><br>
                                        
                                            <!-- Assignment Status Filter -->
                                            <label for="assignment-filter"> By Assignment Status</label>
                                            <select id="assignment-filter" name="assignment_status">
                                                <option value="">-- Select --</option>
                                                <option value="assigned"'.(isset($_GET['assignment_status'] ) && $_GET['assignment_status'] === "assigned" ? " selected" : "").'>Assigned</option>
                                                <option value="not_assigned"'.(isset($_GET['assignment_status'] ) && $_GET['assignment_status'] === "not_assigned" ? " selected" : "").'>Not Assigned</option>
                                            </select>
                                        
                                            <br><br>
                                        
                                            <!-- Height Training Filter -->
                                            <label for="height-training-filter">By Height Training</label>
                                            <select id="height-training-filter" name="height_training">
                                                <option value="">-- Select --</option>
                                                <option value="yes"'.(isset($_GET['height_training'] ) && $_GET['height_training'] === "yes" ? " selected" : "").'>Yes</option>
                                                <option value="no"'.(isset($_GET['height_training'] ) && $_GET['height_training'] === "no" ? " selected" : "").'>No</option>
                                            </select>
                                        
                                            <br><br>
                                        
                                            <!-- Fragmented Data Filter -->
                                            <div style="display: flex; align-items: center; justify-content: center; width: 100%;">
                                                <label style="width: 90%; display: flex; justify-content: flex-start;" for="fragmented-filter" style="margin-right: auto; white-space: nowrap">Fragmented Data:</label>
                                                <input style="position: relative; bottom: 3px; width: 10%;" type="checkbox" id="fragmented-filter" name="fragmented" value="1"'.(isset($_GET["filter_fragmented"]) && $_GET["filter_fragmented"] == 1 ? "checked" : "").'>
                                            </div>
                                        
                                            <br>
                                        
                                            <!-- Apply and Clear Filters -->
                                        </div>
                                        <button type="submit" style="background-color: green; color: white;">Apply Filters</button>
                                        <button type="button" onclick="clearFilters();" style="background-color: red; color: white;">Clear Filters</button>
                                    </form>
                                </div>
                                <a href="" style="display: block; flex-grow: 1; text-align: '.$align.'; margin: 0; padding: 0;">
                                    <img id="top_menu_button" class="manage_shift_btn" src="img/add-list.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['import'] ?? 'Import') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['import'] ?? 'Import') . '">
                                </a>
                                <a href="' . $url . '&action=add" style="display: block; flex-grow: 1; text-align: '.$align.'; margin: 0; padding: 0;">
                                    <img id="top_menu_button" class="manage_shift_btn" src="img/add.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['add'] ?? 'Add') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['add'] ?? 'Add') . '">
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th style="white-space: nowrap; width: 1%; font-weight: bolder;">
                            <a style="color: black; text-decoration: underline; font-size: 0.78rem; white-space: nowrap;" href="' . $url . '&sort_by=worker_id&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                #
                            </a>';
                            if(!isset($_GET['search_worker_id']) || $_GET['search_worker_id'] == "")
                            {
                                echo '
                                <img src="img/search-icon.png" class="search-icon" data-column="worker_id">';
                            }
                            else
                            {
                                echo '
                                <img src="img/search_undo.png" onclick="undoSearch(\'worker_id\');" class="search-undo">';
                            }

echo '
                        </th>
                        <th style="font-weight: bolder; font-size: 0.78rem; white-space: nowrap; width: 1%; max-width: max-content;">
                            ' . htmlspecialchars($lang_data[$selectedLanguage]["passport"] ?? "Passport/ID") . '
                        </th>

                        <th id="hide_when_small" style="font-weight: bolder; font-size: 0.75rem; white-space: nowrap;">
                            <a style="color: black; text-decoration: underline;" href="' . $url . '&sort_by=first_name&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["name"] ?? "Name") . '
                            </a>';
                                if(!isset($_GET['search_first_name']) || $_GET['search_first_name'] == "")
                                {
                                    echo '
                                    <img src="img/search-icon.png" class="search-icon" data-column="first_name">';
                                }
                                else
                                {
                                    echo '
                                    <img src="img/search_undo.png" onclick="undoSearch(\'first_name\');" class="search-undo">';
                                }
echo '
                        </th>
                        <th style="font-weight: bolder; font-size: 0.75rem; white-space: nowrap;">
                            <a style="color: black; text-decoration: underline;" href="' . $url . '&sort_by=profession&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["profession"] ?? "Profession") . '
                            </a>';
if(!isset($_GET['search_profession']) || $_GET['search_profession'] == "")
{
    echo '
                                <img src="img/search-icon.png" class="search-icon" data-column="profession">';
}
else
{
    echo '
                                <img src="img/search_undo.png" onclick="undoSearch(\'profession\');" class="search-undo">';
}
echo '
                        </th>                        
                        <th style="font-weight: bolder; font-size: 0.78rem; white-space: nowrap;">
                            <a style="color: black; text-decoration: underline;" href="' . $url . '&sort_by=assigned&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["assigned"] ?? "Assigned") . '
                            </a>
                        </th>
                        <th style="font-weight: bolder; font-size: 0.78rem; white-space: nowrap; width: 1%; max-width: max-content;">
                            ' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? "Actions") . '
                        </th>
                    </tr>
                </thead>
                <tbody>';

if (count($users) > 0) {
    foreach ($users as $user) {
        $workerId = htmlspecialchars($user['worker_id']);
        $guid = htmlspecialchars($user['user_guid']);
        $profession = htmlspecialchars($user['profession']);
        $passport_id = htmlspecialchars($user['passport_id']);
        $short_name = htmlspecialchars(explode(" ", $user['first_name'])[0]);
        $name = '';
        for($i=0;$i<=7;$i++)
        {
            if(!isset($short_name[$i]))
                continue;

            $name .= $short_name[$i];
            if($i == 7 && strlen($short_name) > 8)
                $name .= "...";
        }
        $short_name = $name.(strlen($short_name) > 8 ? "" : ". ".htmlspecialchars($user['last_name'][0])).".";
        $assignment = isAssigned($guid);
        $is_on_relief = isOnRelief($guid);
        $link = (!$assignment ?
            ($is_on_relief > 0 ?
                htmlspecialchars($lang_data[$selectedLanguage]["on_relief"] ?? "On relief") :
                "<a style='color:black; text-decoration: underline;' href='index.php?lang={$selectedLanguage}&page=admin&sub_page=workers&user_guid={$guid}&action=assign&to=assignment'>" . htmlspecialchars($lang_data[$selectedLanguage]["available"] ?? "Available") . "</a>") :
            "<a style='color:black; text-decoration: underline;' href='index.php?lang={$selectedLanguage}&page=admin&sub_page=assignments&action=edit&assignment_guid=" . $assignment[1] . "'>" . $assignment[0] . "</a>");

        echo "
            <tr>
                <td style='font-size: 0.73rem;'>{$workerId}</td>
                <td style='font-size: 0.64rem;'>{$passport_id}</td>
                <td id='hide_when_small' style='font-size: 0.64rem;' dir='auto'>{$short_name}</td>
                <td style='font-size: 0.73rem;'>{$profession}</td>
                
                
                <td style='white-space: nowrap; font-size: 0.73rem;'>{$link}</td>
                <td style='white-space: nowrap;'>
                    <a href='{$url}&action=assign&user_guid={$guid}'><img class='manage_shift_btn' src='img/assign.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['assign'] ?? 'Assign') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['assign'] ?? 'Assign') . "'></a>
                    <a href='{$url}&action=edit&user_guid={$guid}'><img class='manage_shift_btn' src='img/edit.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . "'></a>
                    <a href='{$url}&action=delete&user_guid={$guid}'><img class='manage_shift_btn' src='img/delete.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['delete'] ?? 'Delete') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['delete'] ?? 'Delete') . "'></a>
                </td>
            </tr>";
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
        const buttonImg = document.getElementById('top_menu_button_search');
        if (event.target !== buttonImg && event.target !== menu && event.target !== button && !menu.contains(event.target) && menu.style.display !== 'none') {
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
        const relief = document.getElementById('relief-filter').value;
        const assignment = document.getElementById('assignment-filter').value;
        const training = document.getElementById('height-training-filter').value;
        const fragmented = document.getElementById('fragmented-filter').checked;

        // Modify URL parameters with filters
        const urlParams = new URLSearchParams(window.location.search);

        if (relief) {
            urlParams.set('relief_status', relief);
        } else {
            urlParams.delete('relief_status');
        }

        if (assignment) {
            urlParams.set('assignment_status', assignment);
        } else {
            urlParams.delete('assignment_status');
        }

        if (training) {
            urlParams.set('height_training', training);
        } else {
            urlParams.delete('height_training');
        }

        if (fragmented) {
            urlParams.set('filter_fragmented', '1');
        } else {
            urlParams.delete('filter_fragmented');
        }

        // Apply filters by reloading the page with the updated URL
        window.location.search = urlParams.toString();
    });

    function clearFilters() {
        // Redirect to the same page without any query parameters
        window.location.href = "index.php?lang=<?php echo htmlspecialchars($selectedLanguage); ?>&page=admin&sub_page=workers";
    }

    // Show the table after the bottom menu has rendered
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('workers_table').style.display = '';
    });
</script>

<?php
// pages/admin/sites.php

// Include necessary configurations and handlers
global $selectedLanguage, $geo_keys;
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
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "');</script>";
    exit();
}

global $lang_data, $selectedLanguage, $MySQL;

// Get sorting options from URL
$sort_by = $_GET['sort_by'] ?? 'site_guid'; // Default sorting column
$sort_order = $_GET['sort_order'] ?? 'asc'; // Default sorting order

// Fetch all users
$sites = fetchAllSites($sort_by, $sort_order);
function toggleSortOrder($current_order) {
    return $current_order === 'asc' ? 'desc' : 'asc';
}

// Handle potential errors
if ($sites === false) {
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

// Display the users management interface
echo "
    <div class='page'>
        <div class='site-list' id='sites_table' style='display: none;'>
            <table>
                <thead>
                ";
                if($_SESSION['loggedIn']['group'] === 'admins') {
                    echo '
                    <tr>
                        <td colspan="4" style="padding: 10px;">
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
                                    <h3>Filter Sites</h3>
                                    <form id="filter-form">
                                        <!-- Make sure to pass the language and other necessary parameters -->
                                        <input type="hidden" name="lang" value="'.htmlspecialchars($selectedLanguage).'">
                                        
                                        <!-- Filter Form -->
                                        <div class="filter-form" id="filter-form">
                                    
                                            <!-- Filter -->
                                            <label for="site-name-filter">By Site Name</label>
                                            <input id="site-name-filter" name="site-name">
                                    
                                            <br><br>
                                    
                                            <!-- Filter -->
                                            <label for="site-address-filter">By Site Address</label>
                                            <input id="site-address-filter" name="site-address">
                                    
                                            <br><br>
                                            
                                            <!-- Filter -->
                                            <label for="owner-name-filter">By Site Owner</label>
                                            <input id="owner-name-filter" name="owner-name">
                                    
                                            <br><br>
                                            
                                            <!-- Filter -->
                                            <label for="assignment-filter">Has Assignment</label>
                                            <select style="width: 85%;" id="assignment-filter" name="assignment">
                                                <option value="">-- Select --</option>
                                                <option value="yes"'.(isset($_GET['assignment_filter'] ) && $_GET['assignment_filter'] === "yes" ? " selected" : "").'>Yes</option>
                                                <option value="no"'.(isset($_GET['assignment_filter'] ) && $_GET['assignment_filter'] === "no" ? " selected" : "").'>No</option>
                                            </select>
                                    
                                            <br><br>
                                   
                                            <!-- Missing Data Filter -->
                                            <div style="display: flex; align-items: center; justify-content: center; width: 85%; margin: 5px 7.5%;">
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
                    </tr>';
                }

                echo "
                    <tr>".'
                        <th>
                            <a style="color: black; text-decoration: underline; white-space: nowrap;" href="' . $url . '&sort_by=site_name&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["site_name"] ?? "Site Name") . '
                            </a>';
                            if(!isset($_GET['search_site_name']) || $_GET['search_site_name'] == "")
                            {
                                echo '
                            <img src="img/search-icon.png" class="search-icon" data-column="site_name">';
                            }
                            else
                            {
                                echo '
                            <img src="img/search_undo.png" onclick="undoSearch(\'site_name\');" class="search-undo">';
                            }
                            echo '
                        </th>
                        <th>
                            <a style="color: black; text-decoration: underline; white-space: nowrap;" href="' . $url . '&sort_by=site_address&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["site_address"] ?? "Site Address") . '
                            </a>';
                            if(!isset($_GET['search_site_address']) || $_GET['search_site_address'] == "")
                            {
                                echo '
                            <img src="img/search-icon.png" class="search-icon" data-column="site_address">';
                            }
                            else
                            {
                                echo '
                            <img src="img/search_undo.png" onclick="undoSearch(\'site_address\');" class="search-undo">';
                            }
                            echo '
                        </th>
                        <th>
                            <a style="color: black; text-decoration: underline; white-space: nowrap;" href="' . $url . '&sort_by=site_owner_guid&sort_order=' . toggleSortOrder($sort_order) . '">
                                <svg class="sort-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M3 10.5l5 5 5-5H3zm0-5l5-5 5 5H3z"/>
                                </svg>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["owner"] ?? "Owner") . '
                            </a>';
                            if(!isset($_GET['search_site_owner']) || $_GET['search_site_owner'] == "")
                            {
                                echo '
                            <img src="img/search-icon.png" class="search-icon" data-column="site_owner">';
                            }
                            else
                            {
                                echo '
                            <img src="img/search_undo.png" onclick="undoSearch(\'site_owner\');" class="search-undo">';
                            }
                            echo "
                        </th>
                        <th style='white-space: nowrap; width: 1%; max-width: max-content;'>" . htmlspecialchars($lang_data[$selectedLanguage]['actions'] ?? 'Actions') . "</th>
                    </tr>
                </thead>
                <tbody>";
                    

if (count($sites) > 0)
{
    $user_url = preg_replace('/(sub_page=)[^&]+/', 'sub_page=users', $url);
    foreach ($sites as $site) {
        $site_guid = htmlspecialchars($site['site_guid']);
        $site_name = htmlspecialchars($site['site_name']);
        $address = htmlspecialchars($site['site_address']);
        $owner_guid = htmlspecialchars($site['site_owner_guid']);
        $phone_number = htmlspecialchars($site['phone_number'] == '0' ? "" : $site['phone_number']);
        if($owner_guid)
        {
            if ($_SESSION['loggedIn']['group'] === 'admins') {
                $get_owner_name = "<a style='color: black; text-decoration: underline;' href='{$user_url}&action=edit&user_guid={$owner_guid}'>" . getOwnerName($owner_guid, false) . "</a>";
            } else {
                $get_owner_name = getOwnerName($owner_guid, false);
            }
        } else $get_owner_name = $lang_data[$selectedLanguage]["no_owner_assigned"] ?? "No site owner assigned";
        echo "
            <tr>
                <td>{$site_name}</td>
                <td class='geo-address' data-address='{$address}'>Loading...</td>
                <td style='font-size: 0.65rem;'>{$get_owner_name}</td>
                <td style='white-space: nowrap;'>
                    <a href='{$url}&action=add_assignment&site_guid={$site_guid}'><img class='manage_shift_btn' src='img/new.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]["new_assignment"] ?? "New Assignment") . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]["new_assignment"] ?? "New Assignment") . "'></a>
                    <a href='{$url}&action=edit&site_guid={$site_guid}'><img class='manage_shift_btn' src='img/edit.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]["edit"] ?? "Edit") . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]["edit"] ?? "Edit") . "'></a>";
                    if($_SESSION['loggedIn']['group'] == 'admins') {
                        echo "
                        <a href='{$url}&action=assign_manager&site_guid={$site_guid}'><img class='manage_shift_btn' src='img/manager.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]["assign_manager"] ?? "Assign Manager") . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]["assign_manager"] ?? "Assign Manager") . "'></a>
                        <a href='{$url}&action=delete&site_guid={$site_guid}'><img class='manage_shift_btn' src='img/delete.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]["delete"] ?? "Delete") . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]["delete"] ?? "Delete") . "'></a>";
                    }
                    echo "
                </td>
            </tr>
        ";
    }
} else {
    echo '<tr><td colspan="4">' . htmlspecialchars($lang_data[$selectedLanguage]["no_sites_found"] ?? "No sites found.") . '</td></tr>';
}

echo "
                </tbody>
            </table>
        </div>
    </div>

    <script>
    
        document.querySelectorAll('.search-icon').forEach(function(icon) {
            icon.addEventListener('click', function() {
                const column = this.getAttribute('data-column');
                let inputBox = document.querySelector('#search-input-' + column);
    
                // Clear the input value each time search is opened
                if (inputBox) {
                    document.body.removeChild(inputBox);
                }
    
                const urlParams = new URLSearchParams(window.location.search);
                const search_box_direction = (urlParams.get('lang') === 'Hebrew' || urlParams.get('lang') === 'Arabic' ? 'plus' : 'minus');
                const search_box_distance = window.innerWidth > 2200 ? 136 : (window.innerWidth > 2050 ? 115 : (window.innerWidth > 1850 ? 105 : (window.innerWidth > 1550 ? 90 : (window.innerWidth > 1050 ? 65 : (window.innerWidth > 900 ? 40 : (window.innerWidth > 650 ? 25 : (window.innerWidth > 550 ? 15 : 8)))))));
    
                inputBox = document.createElement('input');
                inputBox.setAttribute('type', 'text');
                inputBox.setAttribute('class', 'search_box');
                inputBox.setAttribute('id', 'search-input-' + column);
                inputBox.style.position = 'absolute';
                inputBox.style.top = (this.getBoundingClientRect().bottom + window.scrollY) + 5 + 'px';
    
                // Calculate left position to center the search box under the icon
                const iconWidth = this.getBoundingClientRect().width; // Get the width of the search icon
                const searchBoxWidth = 15; // Set the width of the search box
                inputBox.style.left = (this.getBoundingClientRect().left + window.scrollX + (iconWidth / 2) - (search_box_direction === 'minus' ? ((((window. innerWidth/100)*searchBoxWidth) / 2) - search_box_distance) : (((window. innerWidth/100)*searchBoxWidth) / 2) + search_box_distance)) + 'px';
    
                inputBox.style.width = searchBoxWidth + '%'; // Set the width of the search box
    
                // Restore previous search param if it exists
                if (sessionStorage.getItem('search-' + column)) {
                    inputBox.value = sessionStorage.getItem('search-' + column);
                }
    
                document.body.appendChild(inputBox);
    
                inputBox.focus();
                inputBox.addEventListener('blur', function() {
                    // Remove the input box after search
                    document.body.removeChild(inputBox);
                });
    
                // Trigger search on Enter key press
                inputBox.addEventListener('keypress', function(event) {
                    if (event.key === 'Enter') {
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
            performSearch(column, '');
        }
        
        function performSearch(column, searchTerm) {
            // Save the search param in session storage
            sessionStorage.setItem('search-' + column, searchTerm);
    
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
            const site_name         = document.getElementById('site-name-filter').value;
            const site_address      = document.getElementById('site-address-filter').value;
            const owner_name        = document.getElementById('owner-name-filter').value;
            const has_assignment    = document.getElementById('assignment-filter').value;
            const fragmented        = document.getElementById('fragmented-filter').checked;
            const missing           = document.getElementById('missing-data-filter').checked;
    
            // Modify URL parameters with filters
            const urlParams = new URLSearchParams(window.location.search);
    
            if (site_name) {
                urlParams.set('search_site_name', site_name);
            } else {
                urlParams.delete('search_site_name');
            }
            
            if (site_address) {
                urlParams.set('search_site_address', site_address);
            } else {
                urlParams.delete('search_site_address');
            }
    
            if (owner_name) {
                urlParams.set('search_owner_name', owner_name);
            } else {
                urlParams.delete('search_owner_name');
            }
            
            if (has_assignment) {
                urlParams.set('assignment_filter', has_assignment);
            } else {
                urlParams.delete('assignment_filter');
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
            window.location.href = 'index.php?lang={$selectedLanguage}&page=admin&sub_page=sites';
        }
        
 
        function initGeocode() {
            const geoAddressCells = document.querySelectorAll('.geo-address');
        
            geoAddressCells.forEach(async function (cell) {
                const address = cell.getAttribute('data-address');
        
                try {
                    if(address !== '')
                    {
                        const geoData = await getGeoAddress(address);
                        if (geoData && geoData.name && geoData.name !== '') {
                            cell.innerHTML = '<a style=\"color:black; text-decoration: underline;\" href=\"https://maps.google.com/?q=' + address + '\">' + geoData.name + '</a>';
                        } else {
                            cell.textContent = '{$lang_data[$selectedLanguage]['no_address_found']}';
                        }
                    } else cell.textContent = '{$lang_data[$selectedLanguage]['no_address_found']}';
        
                } catch (error) {
                    cell.textContent = '{$lang_data[$selectedLanguage]['error_loading_address']}';
                }
            });
        }
        
        function waitForGoogleMaps() {
            // Check if the Google Maps API is ready
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.Geocoder === 'function') {
                initGeocode(); // Call the function once the API is ready
            } else {
                // Retry after 500 milliseconds if the API isn't ready
                setTimeout(waitForGoogleMaps, 200);
            }
        }


        waitForGoogleMaps();
        
        
        
        
        
        // Show the table after the bottom menu has rendered
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('sites_table').style.display = '';
        });
    </script>
";
?>

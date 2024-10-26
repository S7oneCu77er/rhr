<?php
// pages/admin/houses.php

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
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "', true);</script>";
    exit();
}


// Fetch all users
$houses = fetchAllHouses();

// Handle potential errors
if ($houses === false) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

$url = 'index.php?lang=' . urlencode($selectedLanguage);
foreach($_GET as $key => $value) {
    if($key == 'lang') continue;
    $url .= '&' . urlencode($key) . '=' . urlencode($value);
}

$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin';

$align  = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "left" : "right";
$dir    = $align == "left" ? "right" : "left";
$flip   = $align == "left" ? " transform: scaleX(-1);" : "";

// Display the users management interface
echo "
    <div class='page'>
        <div class='user-list' style='display: none;' id='houses_table'>
            <table>
                <thead>                
                    <tr>".'
                        <td colspan="3" style="padding: 10px;">
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
                                <a href="" style="display: block; flex-grow: 1; text-align: '.$align.'; margin: 0; padding: 0;">
                                    <img style="height: 50px; width: 50px;" class="manage_shift_btn" src="img/add-list.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['import'] ?? 'Import') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['import'] ?? 'Import') . '">
                                </a>
                                <a href="' . $url . '&action=add" style="display: block; flex-grow: 1; text-align: '.$align.'; margin: 0; padding: 0;">
                                    <img style="height: 50px; width: 50px;" class="manage_shift_btn" src="img/add.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['add'] ?? 'Add') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['add'] ?? 'Add') . '">
                                </a>
                            </div>
                        </td>
                    </tr>'."
                    <tr>
                        <th>" . htmlspecialchars($lang_data[$selectedLanguage]['house_address'] ?? 'House Address') . "</th>
                        <th style='white-space: nowrap; width: 1%; max-width: max-content;'>" . htmlspecialchars($lang_data[$selectedLanguage]['tenants'] ?? 'Tenants') . "</th>
                        <th style='white-space: nowrap; width: 1%; max-width: max-content;'>" . htmlspecialchars($lang_data[$selectedLanguage]['actions'] ?? 'Actions') . "</th>
                    </tr>
                </thead>
                <tbody>";

            if (count($houses) > 0)
            {
                foreach ($houses as $house) {
                    $house_guid = htmlspecialchars($house['house_guid']);
                    $address = htmlspecialchars($house['house_address']);
                    $count_tenants = countTenants($house_guid);
                    echo "
                        <tr>
                            <td style='padding: 5px;' class='geo-address' data-address='{$address}'>Loading...</td>
                            <td style='white-space: nowrap;'><a href='{$url}&action=view_tenants&house_guid={$house_guid}'>{$count_tenants}</a></td>
                            <td style='white-space: nowrap;'>
                                <a href='{$url}&action=edit&house_guid={$house_guid}'><img class='manage_shift_btn' src='img/edit.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . "'></a>
                                <a href='{$url}&action=delete&house_guid={$house_guid}'><img class='manage_shift_btn' src='img/delete.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]["delete"] ?? "Delete") . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]["delete"] ?? "Delete") . "'></a>
                            </td>
                        </tr>
                    ";
                }
            } else {
                echo '<tr><td colspan="7">' . htmlspecialchars($lang_data[$selectedLanguage]["no_houses_found"] ?? "No houses found.") . '</td></tr>';
            }

echo "
            </tbody>
        </table>
    </div>
</div>

<script>
    function initGeocode() {
            const geoAddressCells = document.querySelectorAll('.geo-address');
        
            geoAddressCells.forEach(async function (cell) {
                const address = cell.getAttribute('data-address');
        
                try {
                    if(address !== '')
                    {
                        const geoData = await getGeoAddress(address);
                        if (geoData && geoData.name && geoData.name !== '') {
                            cell.innerHTML = '<a style=\"color:black; text-decoration: underline;\" href=\"https://maps.google.com/?q=' + geoData.name + '\">' + geoData.name + '</a>';
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
        document.getElementById('houses_table').style.display = '';
    });
    
</script>
";
?>

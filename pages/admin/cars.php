<?php
// pages/admin/cars.php

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

// Fetch all cars
$cars = fetchAllCars();

// Handle potential errors
if ($cars === false) {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error.') . "', true);</script>";
    exit();
}

$url = 'index.php?lang=' . urlencode($selectedLanguage);
foreach($_GET as $key => $value) {
    if ($key == 'lang') continue;
    $url .= '&' . urlencode($key) . '=' . urlencode($value);
}

$back = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin';

$align  = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "left" : "right";
$dir    = $align == "left" ? "right" : "left";
$flip   = $align == "left" ? " transform: scaleX(-1);" : "";

// Display the cars management interface
echo "
    <div class='page'>
        <div class='user-list'>
            <table>
                <thead>               
                    <tr>".'
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
                                <a href="" style="display: block; flex-grow: 1; text-align: '.$align.'; margin: 0; padding: 0;">
                                    <img style="height: 50px; width: 50px;" class="manage_shift_btn" src="img/add-list.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['import'] ?? 'Import') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['import'] ?? 'Import') . '">
                                </a>
                                <a href="' . $url . '&action=add" style="display: block; flex-grow: 1; text-align: '.$align.'; margin: 0; padding: 0;">
                                    <img style="height: 50px; width: 50px;" class="manage_shift_btn" src="img/add.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['add'] ?? 'Add') . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['add'] ?? 'Add') . '">
                                </a>
                            </div>
                        </td>'."
                    </tr>
                    <tr>
                        <th>" . htmlspecialchars($lang_data[$selectedLanguage]['car_model'] ?? 'Car Model') . "</th>
                        <th>" . htmlspecialchars($lang_data[$selectedLanguage]['car_number_plate'] ?? 'Car Number Plate') . "</th>
                        <th>" . htmlspecialchars($lang_data[$selectedLanguage]['driver'] ?? 'Driver') . "</th>
                        <th style='white-space: nowrap; width: 1%; max-width: max-content;'>" . htmlspecialchars($lang_data[$selectedLanguage]['actions'] ?? 'Actions') . "</th>
                    </tr>
                </thead>
                <tbody>";

if (count($cars) > 0) {
    foreach ($cars as $car) {
        $car_guid = htmlspecialchars($car['car_guid']);
        $car_model = htmlspecialchars($car['car_model']);
        $car_number_plate = htmlspecialchars($car['car_number_plate']);
        $driver = $car['driver_guid'] ? "<a style='color: black; text-decoration: underline;' href='index.php?lang={$selectedLanguage}&page=admin&sub_page=workers&action=edit&user_guid={$car['driver_guid']}'>" . getWorkerName(htmlspecialchars($car['driver_guid'])) . "</a>" : htmlspecialchars($lang_data[$selectedLanguage]['no_driver_assigned'] ?? 'No driver assigned');


        echo "
            <tr>
                <td>{$car_model}</td>
                <td>{$car_number_plate}</td>
                <td>{$driver}</td>
                <td style='white-space: nowrap; text-align: {$align};'>
                    <a href='{$url}&action=assign_driver&car_guid={$car_guid}'><img class='manage_shift_btn' src='img/driver.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['assign_driver'] ?? 'Assign Driver') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['assign_driver'] ?? 'Assign Driver') . "'></a>
                    " . ( $car['driver_guid'] ? "<a href='{$url}&action=unassign_driver&car_guid={$car_guid}'><img class='manage_shift_btn' src='img/unassign.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['unassign_driver'] ?? 'Unassign Driver') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['unassign_driver'] ?? 'Unassign Driver') . "'></a>" : "<div style='display: inline-flex;' class='manage_shift_btn'></div>" ) . "
                    <a href='{$url}&action=edit&car_guid={$car_guid}'><img class='manage_shift_btn' src='img/edit.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . "'></a>
                    <a href='{$url}&action=delete&car_guid={$car_guid}'><img class='manage_shift_btn' src='img/delete.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]["delete"] ?? "Delete") . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]["delete"] ?? "Delete") . "'></a>
                </td>
            </tr>
        ";
    }
} else {
    echo '<tr><td colspan="4">' . htmlspecialchars($lang_data[$selectedLanguage]["no_cars_found"] ?? "No cars found.") . '</td></tr>';
}

echo "
                </tbody>
            </table>
        </div>
    </div>
";
?>

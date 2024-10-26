<?php
// inc/hours_handler.php

// Function to start a new shift
global $MySQL, $lang_data, $selectedLanguage;

function startShift($userGuid, $MySQL, $lat, $lng) {
    // Check if there's an open shift for the user
    $stmt = $MySQL->getConnection()->prepare("SELECT * FROM shifts WHERE user_guid = ? AND shift_end IS NULL");
    if ($stmt) {
        $stmt->bind_param("s", $userGuid);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        return 'Database error.';
    }

    if ($result && $result->num_rows > 0) {
        return 'There is already an open shift!';
    } else {
        // Get the site's latitude and longitude for the user's current shift assignment
        $siteLocationSql = "
        SELECT si.site_address
        FROM shift_assignments sa
        JOIN shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
        JOIN sites si ON sa.site_guid = si.site_guid
        WHERE saw.user_guid = ?
        AND CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date
        LIMIT 1;";

        $stmt = $MySQL->getConnection()->prepare($siteLocationSql);
        $stmt->bind_param("s", $userGuid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $site = $result->fetch_assoc();
            $siteAddress = $site['site_address'];
            $stmt->close();
        } else {
            return 'No valid site found for your shift assignment today.';
        }

        // Geocode the site address to get latitude and longitude
        $siteCoords = geocodeAddress($siteAddress);
        if (!$siteCoords) {
            return 'Error retrieving site coordinates.';
        }

        $siteLat = $siteCoords['lat'];
        $siteLng = $siteCoords['lng'];

        // Calculate the distance between the user's location and the site's location
        $distance = distance($lat, $lng, $siteLat, $siteLng);

        // Check if the user is within 50 meters of the site
        if ($distance > 1000 && $distance < 200000 && isMobileDevice()) {
            return 'You are not at the site location to start your shift.';
        }

        // Proceed to start the shift (as in the original code)
        $currentDateTime = new DateTime();
        $minutes = (int) $currentDateTime->format('i');
        $roundedMinutes = round($minutes / 30) * 30;

        if ($roundedMinutes == 60) {
            $currentDateTime->modify('+1 hour');
            $roundedMinutes = 0;
        }

        $currentDateTime->setTime($currentDateTime->format('H'), $roundedMinutes);
        $roundedDateTime = $currentDateTime->format('Y-m-d H:i:s');

        $insertShiftSql = "    
        INSERT INTO shifts (user_guid, site_guid, shift_start)
        SELECT ?, sa.site_guid, ?
        FROM shift_assignments sa
        JOIN shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
        WHERE saw.user_guid = ?
        AND CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date;";

        $stmt = $MySQL->getConnection()->prepare($insertShiftSql);
        $stmt->bind_param("sss", $userGuid, $roundedDateTime, $userGuid);
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            $stmt->close();
            if($affected == 0)
                return 'Error starting shift, no work today';

            return null; // Shift started successfully
        } else {
            $error = $stmt->error;
            $stmt->close();
            return 'Error starting shift: ' . $error;
        }
    }
}

// Function to end an open shift
function endShift($userGuid, $MySQL) {
    // Check if there is an open shift
    $stmt = $MySQL->getConnection()->prepare("SELECT * FROM shifts WHERE user_guid = ? AND shift_end IS NULL");
    if ($stmt) {
        $stmt->bind_param("s", $userGuid);
        $stmt->execute();
        $result = $stmt->get_result();
        // Proceed with logic
        $stmt->close();
    } else {
        return 'Database error.';
    }

    if ($result && $result->num_rows > 0) {
        $currentDateTime = new DateTime();

        // Get minutes and round to the nearest half hour
        $minutes = (int) $currentDateTime->format('i');
        $roundedMinutes = round($minutes / 30) * 30;

        // If rounding goes to 60, adjust the hour and reset minutes
        if ($roundedMinutes == 60) {
            $currentDateTime->modify('+1 hour');
            $roundedMinutes = 0;
        }

        // Set the rounded minutes
        $currentDateTime->setTime($currentDateTime->format('H'), $roundedMinutes);

        // Output the rounded date and time
        $roundedDateTime = $currentDateTime->format('Y-m-d H:i:s');
        $shift = $result->fetch_assoc();
        $shiftId = $shift['shift_guid'];
        $updateShiftSql = "UPDATE shifts SET shift_end = '$roundedDateTime' WHERE shift_guid = '$shiftId'";
        if ($MySQL->Query($updateShiftSql)) {
            return null;
        } else {
            return 'Error ending shift!';
        }
    } else {
        return 'No open shift to close!';
    }
}

function showShifts($sort_by = 'guid', $sort_order = 'desc') {
    global $MySQL, $lang_data, $selectedLanguage;

    // Mapping column names to actual database fields
    $allowed_sort_columns = [
        'guid' => 'shift_guid',
        'date' => 'shift_start',
        'start_end' => 'shift_start', // Adjust if different column names are used
        'total' => 'total_time',     // Replace with actual field for total time
        'site_name' => 'site_guid',
        'worker_name' => 'first_name'
    ];

    // Use default sorting by date if invalid sort_by is passed
    $sort_by = $allowed_sort_columns[$sort_by] ?? 'shift_guid';
    $sort_order = ($sort_order == 'asc') ? 'ASC' : 'DESC'; // Default to descending

    $include_actions = '';
    $colspan = 4;

    $month = $_GET['month'] ?? null;
    $year  = $_GET['year'] ?? null;
    $currentMonth = ($month != null ) ? $month : date('m'); // Current month (01-12)
    $currentYear = ($year != null ) ? $year : date('Y');  // Current year (e.g., 2024)

    // Initialize userGuid with session guid, default for workers
    $userGuid = $_SESSION['loggedIn'] ? $_SESSION['loggedIn']['user_guid'] : null;
    $userGroup= $_SESSION['loggedIn'] ? $_SESSION['loggedIn']['group'] : null;
    $get_page =     $_GET['page'] ?? "hours";
    $get_subpage =  $_GET['sub_page'] ?? "";

    // Initialize variables to track parameters and their types
    $paramTypes = ''; // Will hold the types of the parameters (e.g., "iii")
    $params = []; // Will hold the actual parameters to bind

    $url = 'index.php?lang=' . urlencode($selectedLanguage ?? "English");
    $page = ( $_SESSION['loggedIn']['group'] === 'admins' ? "admin" : "site_management" );
    $url .= "&page={$page}&sub_page=shifts";
    foreach($_GET as $key => $value) {
        if($key == 'lang' || $key == 'user_guid' || $key == 'page' || $key == 'sub_page' || $key == 'worker_name' || $key == 'month' || $key == 'year') continue;
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    $actions = '
    <td style="white-space: nowrap;">
        <a href="' . $url . '&action=approve"><img class="manage_shift_btn" src="img/confirm.png" alt=""></a>
        <a href="' . $url . '&action=edit"><img class="manage_shift_btn" src="img/edit.png" alt=""></a>
        <a href="' . $url . '&action=delete"><img class="manage_shift_btn" src="img/delete.png" alt=""></a>
    </td>';
    switch($get_page)
    {
        case "hours":
        {
            // Prepare default SQL statements
            $shiftSql = "
                SELECT *,TIME_FORMAT(total_time, '%H:%i') AS total_time FROM shifts 
                WHERE user_guid = ? 
                AND YEAR(shift_start) = ? 
                AND MONTH(shift_start) = ? 
                ORDER BY {$sort_by} {$sort_order} LIMIT 100";
            $paramTypes = 'iii'; // 'i' for integer (user_guid, year, month)
            $params = [$userGuid, $currentYear, $currentMonth]; // Replace with actual values
        }
        break;
        case "history":
        {
            switch($userGroup)
            {
                case "admins":
                {
                    // Prepare default SQL statements
                    $shiftSql = "
                        SELECT s.*, TIME_FORMAT(s.total_time, '%H:%i') AS total_time, u.first_name AS first_name
                        FROM shifts s
                        JOIN users u ON u.user_guid = s.user_guid
                        WHERE YEAR(s.shift_start) = ? 
                        AND MONTH(s.shift_start) = ? 
                        AND s.shift_guid > 0
                        ORDER BY {$sort_by} {$sort_order}";
                    $paramTypes = 'ii'; // 'i' for integer (user_guid, year, month)
                    $params = [$currentYear, $currentMonth]; // Replace with actual values
                    $include_actions = $actions;
                    $colspan = 6;
                }
                break;
                case "site_managers":
                {
                    $shiftSql = "
                    SELECT 
                        s.shift_guid,
                        s.site_guid,
                        s.user_guid,
                        s.shift_start,
                        s.shift_end,
                        s.total_time AS total_time_seconds,
                        TIME_FORMAT(s.total_time, '%H:%i') AS total_time,
                        s.status,
                        u.first_name,
                        u.last_name,
                        si.site_name
                    FROM 
                        shifts s
                    INNER JOIN 
                        site_managers sm ON s.site_guid = sm.site_guid
                    INNER JOIN 
                        users u ON s.user_guid = u.user_guid
                    INNER JOIN 
                        sites si ON s.site_guid = si.site_guid
                    WHERE 
                        sm.user_guid = ? OR si.site_owner_guid = ?-- The logged-in site manager's user_guid
                    AND 
                        s.shift_guid > 0
                    AND 
                        YEAR(s.shift_start) = ? 
                    AND 
                        MONTH(s.shift_start) = ? 
                    
                    UNION
                    
                    SELECT 
                        s.shift_guid,
                        s.site_guid,
                        s.user_guid,
                        s.shift_start,
                        s.shift_end,
                        s.total_time AS total_time_seconds,
                        TIME_FORMAT(s.total_time, '%H:%i') AS total_time,
                        s.status,
                        u.first_name,
                        u.last_name,
                        si.site_name
                    FROM 
                        shifts s
                    INNER JOIN 
                        users u ON s.user_guid = u.user_guid
                    INNER JOIN 
                        sites si ON s.site_guid = si.site_guid
                    WHERE 
                        s.user_guid = ? -- The logged-in site manager's user_guid
                    AND 
                        s.shift_guid > 0
                    AND 
                        YEAR(s.shift_start) = ? 
                    AND 
                        MONTH(s.shift_start) = ? 
                    
                    ORDER BY 
                        {$sort_by} {$sort_order}";
                    $paramTypes = 'iiiiiii'; // 'i' for integer (user_guid, year, month)
                    $params = [$userGuid, $userGuid, $currentYear, $currentMonth, $userGuid, $currentYear, $currentMonth]; // Replace with actual values
                    $include_actions = $actions;
                    $colspan = 6;
                }
                break;
                case "drivers":
                case "workers":
                default:
                {
                    // Prepare default SQL statements
                    $shiftSql = "
                        SELECT *,TIME_FORMAT(total_time, '%H:%i') AS total_time FROM shifts 
                        WHERE user_guid = ? 
                        AND shift_guid > 0
                        AND YEAR(shift_start) = ? 
                        AND MONTH(shift_start) = ? 
                        ORDER BY {$sort_by} {$sort_order} LIMIT 100";
                    $paramTypes = 'iii'; // 'i' for integer (user_guid, year, month)
                    $params = [$userGuid, $currentYear, $currentMonth]; // Replace with actual values
                }
                break;
            }
        }
        break;
        case "admin":
        {
            if($get_subpage == 'shifts' && $userGroup == 'admins')
            {
                $include_actions = $actions;
                $colspan = 5;
                if(isset($_GET['user_guid']) && $_GET['user_guid'] != "")
                {
                    $workerGuid = $_GET['user_guid'];
                    $shiftSql = "
                        SELECT *,TIME_FORMAT(total_time, '%H:%i') AS total_time FROM shifts 
                        WHERE user_guid = ? 
                        AND shift_guid > 0  
                        AND YEAR(shift_start) = ? 
                        AND MONTH(shift_start) = ? 
                        ORDER BY {$sort_by} {$sort_order} LIMIT 100";
                    $paramTypes = 'iii'; // 'i' for integer (user_guid, year, month)
                    $params = [$workerGuid, $currentYear, $currentMonth]; // Replace with actual values
                }
                else
                {
                    $shiftSql = "
                        SELECT *,TIME_FORMAT(total_time, '%H:%i') AS total_time FROM shifts 
                        WHERE YEAR(shift_start) = ? 
                        AND shift_guid > 0
                        AND MONTH(shift_start) = ? 
                        ORDER BY {$sort_by} {$sort_order}";
                    $paramTypes = 'ii'; // 'i' for integer (user_guid, year, month)
                    $params = [$currentYear, $currentMonth]; // Replace with actual values
                    $include_actions = $actions;
                    $colspan = 6;
                }

            } else exit();
        }
        break;
    }
    // Prepare and execute the SQL query
    $stmt = $MySQL->getConnection()->prepare($shiftSql);

    if($stmt)
    {
        // Dynamically bind parameters
        if ($paramTypes && $params) {
            $stmt->bind_param($paramTypes, ...$params); // Spread operator to pass params
        }

        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error']) . "');</script>";
        $result = false;
    }

    $output = '<tbody>';
    $align  = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "left" : "right";
    $tmp_actions = $include_actions;
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $user = htmlspecialchars($row['user_guid']);
            $shiftStart = htmlspecialchars($row['shift_start']);
            $shiftEnd = htmlspecialchars($row['shift_end'] ?? '--:--');
            $totalTime = htmlspecialchars($row['total_time'] ?? '--:--');
            $status = htmlspecialchars($row['status']);
            $status_color = $status == "pending" ? "lightyellow" : "lightgreen";
            $date = date('d-m', strtotime($shiftStart));
            $startTime = date('H:i', strtotime($shiftStart));
            $endTimeFormatted = ($shiftEnd !== '--:--') ? date('H:i', strtotime($shiftEnd)) : '--:--';
            $totalTimeFormatted = ($totalTime !== '--:--') ? $totalTime : '--:--';

            $output .= "
                <tr style='background-color: $status_color;'>
                    <td>{$date}</td>
                    <td>{$startTime} | {$endTimeFormatted}</td>
                    <td>{$totalTimeFormatted}</td>
                    ";
            if($_SESSION['loggedIn']['group'] === 'admins' || $_SESSION['loggedIn']['group'] === 'site_managers')
            {
                $output .= "
                    <td><a href='index.php?lang={$selectedLanguage}&page=admin&sub_page=sites&action=edit&site_guid={$row['site_guid']}'>" . getSiteName(htmlspecialchars($row['site_guid'])) . "</a></td>
                    ";
            } else {
                $output .= "
                    <td>" . getSiteName($row['site_guid']) . "</td>
                    ";
            }


                    if($colspan == 6) {
                        if($_SESSION['loggedIn']['group'] == 'admins')
                            $output .= "<td style='font-size: 0.7rem;'><a href='index.php?lang={$selectedLanguage}&page=admin&sub_page=workers&action=edit&user_guid={$row['user_guid']}'>" . getWorkerName($row['user_guid'], false) . "</a></td>";
                        else
                            $output .= "<td style='font-size: 0.7rem;'>" . getWorkerName($row['user_guid'], false) . "</td>";
                    }
            $include_actions = $tmp_actions;
            if($status == "approved") {
                if($_SESSION['loggedIn']['group'] == 'admins')
                {
                    $include_actions = '
                    <td style="text-align: '.$align.'; white-space: nowrap;">
                        <a href="' . $url . '&action=edit"><img class="manage_shift_btn" src="img/edit.png" alt=""></a>
                        <a href="' . $url . '&action=delete"><img class="manage_shift_btn" src="img/delete.png" alt=""></a>
                    </td>';
                } elseif($_SESSION['loggedIn']['group'] == 'site_managers')
                {
                    $include_actions = '<td class="manage_shift_btn"style="white-space: nowrap;"></td>';
                }

            }

            $user_actions = str_replace('&action=approve', '&action=approve&shift_guid=' . htmlspecialchars($row['shift_guid']) . '&user_guid=' . $user, $include_actions);
            $user_actions = str_replace('&action=edit', '&action=edit&shift_guid=' . htmlspecialchars($row['shift_guid']) . '&user_guid=' . $user, $user_actions);
            $user_actions = str_replace('&action=delete', '&action=delete&shift_guid=' . htmlspecialchars($row['shift_guid']) . '&user_guid=' . $user, $user_actions);

            $output .= "" . $user_actions ?? '' . "
                </tr>
            ";
        }
    } else {
        $output .= '<tr><td colspan="'.$colspan.'">' . htmlspecialchars($lang_data[$selectedLanguage]["no_shifts_found"]) . '</td></tr>';
    }
    $output .= '</tbody>';
    return $output;
}

function distance($lat1, $lon1, $lat2, $lon2) {
    // Haversine formula to calculate the distance between two points on the Earth's surface
    $earth_radius = 6371000; // in meters

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    $distance = $earth_radius * $c;

    return $distance; // in meters
}
?>

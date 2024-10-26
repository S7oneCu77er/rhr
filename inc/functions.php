<?php
// inc/functions.php

global $MySQL, $geo_keys, $default_lang;

function verifyUser($guid)
{
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT * FROM users WHERE user_guid = ?");
    if ($stmt) {
        // Bind parameters (s = string, i = integer, etc.)
        $stmt->bind_param("i", $guid);

        // Execute the statement
        $stmt->execute();

        // Get the result
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Fetch user data
            $row = $result->fetch_assoc();
            if ($_SESSION['loggedIn'] == $row)
                return true;
        }
        // Close the statement
        $stmt->close();
    }

    return false;
}
function getOwnerName($owner_guid, $full_name = true)
{
    return getWorkerName($owner_guid, $full_name);
}

function getWorkerName($guid, $full_name = true, $short_name = false)
{
    return getUserName($guid, $full_name, $short_name);
}

function getUserName($guid, $full_name = true, $short_name = false)
{

    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT first_name, last_name FROM users WHERE user_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        $stmt->execute();
        $stmt->bind_result($worker_fname, $worker_lname);
        if(!$stmt->fetch()) return $guid;
        if($short_name)
        {
            $worker_fname = (isset($worker_fname[0]) ? $worker_fname[0] : $worker_fname).".";
            $worker_lname = (isset($worker_lname[0]) ? $worker_lname[0] : $worker_lname);

        }
        return $worker_fname . ($full_name ? " " . $worker_lname : "" );
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return $guid;
    }
}

function getGroup($guid, $to_upper = true)
{

    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT `group` FROM users WHERE user_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        $stmt->execute();
        $stmt->bind_result($group);
        if(!$stmt->fetch()) return $guid;
        $group[0] = $to_upper ? strtoupper($group[0]) : $group[0];
        return $group;
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return $guid;
    }
}

function getPassportID($guid)
{

    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT passport_id FROM users WHERE user_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        $stmt->execute();
        $stmt->bind_result($pass_id);
        if(!$stmt->fetch()) return $guid;
        return $pass_id;
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return $guid;
    }
}

function getSiteName($guid)
{

    global $MySQL, $selectedLanguage;
    $stmt = $MySQL->getConnection()->prepare("SELECT site_name FROM sites WHERE site_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        $stmt->execute();
        $stmt->bind_result($site_name);
        if(!$stmt->fetch()) return $guid;
        return $site_name != "" ? $site_name : htmlspecialchars($lang_data[$selectedLanguage]['no_name_found'] ?? 'No Name Found');
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return $guid;
    }
}

function getSiteAddress($guid)
{

    global $MySQL, $selectedLanguage;
    $stmt = $MySQL->getConnection()->prepare("SELECT site_address FROM sites WHERE site_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        $stmt->execute();
        $stmt->bind_result($site_address);
        if(!$stmt->fetch()) return $guid;
        return $site_address != "" ? $site_address : htmlspecialchars($lang_data[$selectedLanguage]['no_address_found'] ?? 'No Address Found');
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return $guid;
    }
}

function getWorkerID($guid)
{
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT worker_id FROM workers WHERE user_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        $stmt->execute();
        $stmt->bind_result($worker_id);
        if(!$stmt->fetch()) return $guid;
        return $worker_id;
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return $guid;
    }
}

function getHouseAddressDescription($house_guid) : string
{
    global $MySQL;

    // First Query: Attempt to get house_address and address_description from house_data
    $stmt = $MySQL->getConnection()->prepare("SELECT house_address, address_description FROM houses WHERE house_guid = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("i", $house_guid);
        $stmt->execute();
        $stmt->bind_result($address, $description);

        if ($stmt->fetch()) {
            // If a result is found, return address and description
            $stmt->close();
            return htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . "<br>" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        }

        // No result found in house_data, close the first statement
        $stmt->close();
    } else {
        // Log prepare error for the first query
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
    }

    // If no results found in both tables, return the house_guid
    return htmlspecialchars((string)$house_guid, ENT_QUOTES, 'UTF-8');
}


function fetchAllUsers($sort_by = 'user_guid', $sort_order = 'asc', $inc_disabled = true): bool|array
{
    global $MySQL;

    // Define allowed columns for sorting
    $valid_sort_columns = ['user_guid', 'first_name', 'phone_number', 'group'];
    $sort_by = in_array($sort_by, $valid_sort_columns) ? ($sort_by == 'group' ? '`group`' : $sort_by) : 'user_guid'; // Validate the column
    $sort_order = ($sort_order === 'desc') ? 'DESC' : 'ASC'; // Validate order

    // Prepare the base SQL query
    $query = "SELECT * FROM users WHERE user_guid > 0";

    // Add search filters if they are set in $_GET
    if (!empty($_GET['search_name'])) {
        $query .= " AND (first_name LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_name']) . "%' OR last_name LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_name']) . "%')";
    }
    if (!empty($_GET['search_phone_number'])) {
        $query .= " AND phone_number LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_phone_number']) . "%'";
    }
    if (!empty($_GET['search_group'])) {
        $query .= " AND `group` LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_group']) . "%'";
    }
    if (!empty($_GET['search_country'])) {
        $query .= " AND `country` LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_country']) . "%'";
    }
    if (!empty($_GET['filter_fragmented'])) {
        $query .= " AND ((`group` = 'workers' OR `group` = 'drivers') AND user_guid NOT IN (SELECT user_guid FROM workers) OR";
        $query .= "  (`group` = 'site_managers') AND user_guid NOT IN (SELECT user_guid FROM site_managers) AND user_guid NOT IN (SELECT site_owner_guid FROM sites))";
    }
    if (!empty($_GET['filter_missing_data'])) {
        $query .= " AND ((phone_number = '' OR phone_number = '0') OR (description = '' OR description = ' '))";
    }
    if(!$inc_disabled) {
        $query .= " AND `group` != 'disabled'";
    }

    // Add sorting to the query
    $query .= " ORDER BY $sort_by $sort_order";

    // Prepare and execute the SQL statement
    $stmt = $MySQL->getConnection()->prepare($query);

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        return $users;
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return false;
    }
}
function fetchAllWorkers($sort_by = 'worker_id', $sort_order = 'asc'): bool|array
{
    global $MySQL;

    // Define allowed columns for sorting
    $valid_sort_columns = ['worker_id', 'profession', 'first_name', 'assigned'];
    $sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'worker_id'; // Validate the column
    $sort_order = ($sort_order === 'desc') ? 'DESC' : 'ASC'; // Validate order

    // Adjust the column mapping for 'assigned' since it is derived from logic
    if ($sort_by === 'assigned') {
        // You might need to implement a specific logic for sorting by assigned.
        // Here I'm using a placeholder example
        $sort_by = '(SELECT COUNT(saw.user_guid) FROM shift_assignment_workers saw WHERE saw.user_guid = w.user_guid)';
    }

    // Prepare the base SQL query with dynamic sorting
    $query = "
        SELECT w.*, u.* 
        FROM users u
        JOIN workers w ON u.user_guid = w.user_guid 
        WHERE u.user_guid != 0";

    // Add search filters if they are set in $_GET
    if (!empty($_GET['search_worker_id'])) {
        $query .= " AND w.worker_id = '" . $MySQL->getConnection()->real_escape_string($_GET['search_worker_id']) . "'";
    }
    if (!empty($_GET['search_profession'])) {
        $query .= " AND w.profession LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_profession']) . "%'";
    }
    if (!empty($_GET['search_first_name'])) {
        $query .= " AND u.first_name LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_first_name']) . "%'";
    }
    if (!empty($_GET['search_passport_id'])) {
        $query .= " AND u.passport_id LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_passport_id']) . "%'";
    }
    if (!empty($_GET['search_assigned'])) {
        // Add logic for filtering 'assigned' if necessary
        $query .= " AND (SELECT COUNT(saw.user_guid) FROM shift_assignment_workers saw WHERE saw.user_guid = workers.user_guid) > 0"; // Example for filtering by assigned
    }

    if (!empty($_GET['filter_fragmented'])) {
        $query .= " AND w.user_guid NOT IN (SELECT user_guid FROM users)";
    }

    if (!empty($_GET['relief_status'])) {
        if ($_GET['relief_status'] == 'on_relief') {
            $query .= " AND w.on_relief = 1 AND w.relief_end_date > CURDATE()";
        } else if ($_GET['relief_status'] == 'not_on_relief') {
            $query .= " AND (w.on_relief = 0 OR w.relief_end_date < CURDATE())";
        }
    }

    if (!empty($_GET['assignment_status'])) {
        if ($_GET['assignment_status'] == 'assigned') {
            $query .= " AND EXISTS (SELECT 1 FROM shift_assignment_workers saw JOIN shift_assignments sa ON saw.assignment_guid = sa.assignment_guid WHERE saw.user_guid = w.user_guid AND CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date)";
        } else if ($_GET['assignment_status'] == 'not_assigned') {
            $query .= " AND NOT EXISTS (SELECT 1 FROM shift_assignment_workers saw JOIN shift_assignments sa ON saw.assignment_guid = sa.assignment_guid WHERE saw.user_guid = w.user_guid AND CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date)";
        }
    }

    if (!empty($_GET['height_training'])) {
        if ($_GET['height_training'] == 'yes') {
            $query .= " AND w.height_training = 1";
        } else if ($_GET['height_training'] == 'no') {
            $query .= " AND w.height_training = 0";
        }
    }

    // Add sorting to the query
    $query .= " ORDER BY $sort_by $sort_order";

    // Prepare and execute the SQL statement
    $stmt = $MySQL->getConnection()->prepare($query);

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        return $users;
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return false;
    }
}



function fetchAllHouses() {

    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT * FROM houses WHERE house_guid != 0 ORDER BY house_guid ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        return $users;
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return false;
    }
}

function fetchAllSites($sort_by = 'site_guid', $sort_order = 'asc') {
    global $MySQL;

    // Check if the user is logged in
    if (!isset($_SESSION['loggedIn'])) {
        return false;
    }

    // Get the user group and user GUID from session
    $user_guid = $_SESSION['loggedIn']['user_guid'];
    $user_group = $_SESSION['loggedIn']['group'];

    // Define allowed columns for sorting
    $valid_sort_columns = ['site_guid', 'site_name', 'site_address', 'site_owner_guid'];
    $sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'site_guid'; // Validate the column
    $sort_order = ($sort_order === 'desc') ? 'DESC' : 'ASC'; // Validate order

    // SQL query for fetching all sites if the user is an admin
    if ($user_group === 'admins') {

        $sql = "
        SELECT s.*, u.* 
        FROM sites s
        JOIN users u ON u.user_guid = s.site_owner_guid 
        WHERE s.site_guid != 0";

        // Add search filters if they are set in $_GET
        if (!empty($_GET['search_owner_name'])) {
            $sql .= " AND (u.first_name LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_owner_name']) . "%' OR u.last_name LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_owner_name']) . "%')";
        }

        if (!empty($_GET['search_site_address'])) {
            $sql .= " AND s.site_address LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_site_address']) . "%'";
        }

        if (!empty($_GET['search_site_name'])) {
            $sql .= " AND s.site_name LIKE '%" . $MySQL->getConnection()->real_escape_string($_GET['search_site_name']) . "%'";
        }

        if (!empty($_GET['assignment_filter'])) {
            $sql .= " AND s.site_guid IN (SELECT site_guid FROM shift_assignments)";
        }

        if (!empty($_GET['filter_missing_data'])) {
            $sql .= " AND ((s.site_owner_guid = '' OR s.site_owner_guid = '0') OR (s.site_address = '' OR s.site_address = ' ') OR s.site_guid NOT IN (SELECT site_guid FROM site_managers))";
        }

        $sql .= " ORDER BY {$sort_by} {$sort_order}";
    }
    // SQL query for fetching only the sites managed by the current site manager
    elseif ($user_group === 'site_managers') {
        $sql = "
            SELECT s.*
            FROM sites s
            INNER JOIN site_managers sm ON s.site_guid = sm.site_guid
            WHERE sm.user_guid = ?
            GROUP BY s.site_guid
            
            UNION
            
            SELECT s.*
            FROM sites s
            WHERE S.site_owner_guid = ?
            GROUP BY s.site_guid
        ";
    } else {
        return false; // Invalid user group
    }

    // Prepare the SQL statement
    $stmt = $MySQL->getConnection()->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return false;
    }

    // If the user is a site manager, bind the user_guid parameter
    if ($user_group === 'site_managers') {
        $stmt->bind_param('ii', $user_guid, $user_guid);
    }

    // Execute the query and fetch results
    $stmt->execute();
    $result = $stmt->get_result();
    $sites = [];
    while ($row = $result->fetch_assoc()) {
        $sites[] = $row;
    }

    // Close the statement and return the result
    $stmt->close();
    return $sites;
}

function isOnRelief( $user_guid )
{
    global $MySQL;
    $onReliefQuery = "
                        SELECT on_relief, relief_end_date 
                        FROM workers 
                        WHERE user_guid = ? 
                        AND (on_relief = 1 AND relief_end_date >= CURDATE())
                    ";
    $stmt = $MySQL->getConnection()->prepare($onReliefQuery);
    $stmt->bind_param("i", $user_guid);
    $stmt->execute();
    $reliefStatusResult = $stmt->get_result();
    return $reliefStatusResult->num_rows > 0;
}

function isDriver( $user_guid )
{
    global $MySQL;
    $driver_query = "
                        SELECT * 
                        FROM users u
                        JOIN workers w ON w.user_guid = u.user_guid
                        WHERE u.user_guid = ?
                        AND u.`group` IN ('admins', 'site_managers', 'drivers')
                        AND w.drivers_license IS NOT NULL
                    ";
    $stmt = $MySQL->getConnection()->prepare($driver_query);
    $stmt->bind_param("i", $user_guid);
    $stmt->execute();
    $driver_status = $stmt->get_result();
    return $driver_status->num_rows > 0;
}

function isAssigned($worker_guid)
{
    global $MySQL, $selectedLanguage;
    // Prepare the SQL statement with placeholders
    $sql = "SELECT sites.site_name, shift_assignment_workers.assignment_guid
            FROM shift_assignment_workers
            JOIN shift_assignments ON shift_assignment_workers.assignment_guid = shift_assignments.assignment_guid
            JOIN sites ON shift_assignments.site_guid = sites.site_guid
            WHERE shift_assignment_workers.user_guid = ?
            AND CURDATE() BETWEEN shift_assignments.shift_start_date AND shift_assignments.shift_end_date
            LIMIT 1;";
    $stmt = $MySQL->getConnection()->prepare($sql);
    if (!$stmt) {
        return htmlspecialchars($lang_data[$selectedLanguage]['assign'] ?? 'Assign');
    }

    // Bind the parameter (assuming $worker_guid is an integer)
    $stmt->bind_param('i', $worker_guid);

    // Execute the statement
    $stmt->execute();

    // Bind the result variable
    $stmt->bind_result($site_name, $assignment_guid);

    // Fetch the result
    $exists = $stmt->fetch();


    // Close the statement
    $stmt->close();

    // Check if a site name was fetched
    if (!$exists) {
        return "";
    }

    if(!$site_name)
        return "";

    return [$site_name, $assignment_guid];
}

function getWorkSiteGuid($worker_guid)
{
    global $MySQL;
    // Prepare the SQL statement with placeholders
    $sql = "SELECT sites.site_guid
            FROM shift_assignment_workers
            JOIN shift_assignments ON shift_assignment_workers.assignment_guid = shift_assignments.assignment_guid
            JOIN sites ON shift_assignments.site_guid = sites.site_guid
            WHERE shift_assignment_workers.user_guid = ?
            AND CURDATE() BETWEEN shift_assignments.shift_start_date AND shift_assignments.shift_end_date
            LIMIT 1;";
    $stmt = $MySQL->getConnection()->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    // Bind the parameter (assuming $worker_guid is an integer)
    $stmt->bind_param('i', $worker_guid);

    // Execute the statement
    $stmt->execute();

    // Bind the result variable
    $stmt->bind_result($site_guid);

    // Fetch the result
    $exists = $stmt->fetch();


    // Close the statement
    $stmt->close();

    // Check if a site name was fetched
    if (!$exists) {
        return 0;
    }

    if(!$site_guid)
        return 0;

    return $site_guid;
}

// Function to delete house
function deleteHouse($guid): bool
{
    global $MySQL;

    if($guid == 0 || !$guid)
        return false;

    $stmt = $MySQL->getConnection()->prepare("UPDATE workers SET house_guid = 0 WHERE house_guid = ?");
    if (!$stmt)
        return false;

    $stmt->bind_param("i", $guid);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $stmt->close();

    $stmt = $MySQL->getConnection()->prepare("DELETE FROM houses WHERE house_guid = ? LIMIT 1");
    if (!$stmt)
        return false;

    $stmt->bind_param("i", $guid);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $stmt->close();

    return true;
}

// Function to delete worker
function deleteWorker($guid) {
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("DELETE FROM shift_assignment_workers WHERE user_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    $stmt = $MySQL->getConnection()->prepare("DELETE FROM shifts WHERE user_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    $stmt = $MySQL->getConnection()->prepare("DELETE FROM documents WHERE uploaded_for = ? OR uploaded_by = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $guid, $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    $stmt = $MySQL->getConnection()->prepare("UPDATE cars SET driver_guid = 0 WHERE driver_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    $stmt = $MySQL->getConnection()->prepare("DELETE FROM worker_languages WHERE user_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    $stmt = $MySQL->getConnection()->prepare("DELETE FROM support WHERE user_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    $stmt = $MySQL->getConnection()->prepare("DELETE FROM workers WHERE user_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }
    return true;
}

// Function to delete user
function deleteUser($guid) {
    global $MySQL;

    if(!deleteWorker($guid))
        return false;

    $stmt = $MySQL->getConnection()->prepare("DELETE FROM site_managers WHERE user_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    $stmt = $MySQL->getConnection()->prepare("UPDATE sites SET site_owner_guid = 0 WHERE site_owner_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }


    $stmt = $MySQL->getConnection()->prepare("DELETE FROM users WHERE user_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    return true;
}

// Function to delete assignment and related workers
function deleteAssignment($guid) {
    global $MySQL;

    // Delete all workers from the assignment
    $stmt = $MySQL->getConnection()->prepare("DELETE FROM shift_assignment_workers WHERE assignment_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    // Remove any car from the assignment
    $stmt = $MySQL->getConnection()->prepare("UPDATE cars SET assignment_guid = 0 WHERE assignment_guid = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    // Delete the assignment itself
    $stmt = $MySQL->getConnection()->prepare("DELETE FROM shift_assignments WHERE assignment_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }

    return true;
}

// Function to delete site
function deleteSite($guid) {
    global $MySQL;

    // Begin transaction to ensure all queries succeed or fail together
    $MySQL->getConnection()->begin_transaction();

    try {
        // 1. Reset `assignment_guid` to 0 in `cars` where the site is related
        $stmt = $MySQL->getConnection()->prepare("UPDATE cars SET assignment_guid = 0 WHERE assignment_guid IN (SELECT assignment_guid FROM shift_assignments WHERE site_guid = ?)");
        if ($stmt) {
            $stmt->bind_param("i", $guid);
            $stmt->execute();
            $stmt->close();
        }

        // 2. Delete `shift_assignment_workers` related to `shift_assignments` for this site
        $stmt = $MySQL->getConnection()->prepare("DELETE FROM shift_assignment_workers WHERE assignment_guid IN (SELECT assignment_guid FROM shift_assignments WHERE site_guid = ?)");
        if ($stmt) {
            $stmt->bind_param("i", $guid);
            $stmt->execute();
            $stmt->close();
        }

        // 3. Delete `shift_assignments` related to the site
        $stmt = $MySQL->getConnection()->prepare("DELETE FROM shift_assignments WHERE site_guid = ?");
        if ($stmt) {
            $stmt->bind_param("i", $guid);
            $stmt->execute();
            $stmt->close();
        }

        // 4. Update all shifts related to the site to set `site_guid = 0`
        $stmt = $MySQL->getConnection()->prepare("UPDATE shifts SET site_guid = 0 WHERE site_guid = ?");
        if ($stmt) {
            $stmt->bind_param("i", $guid);
            $stmt->execute();
            $stmt->close();
        }

        // 5. Remove related `site_managers` records
        $stmt = $MySQL->getConnection()->prepare("DELETE FROM site_managers WHERE site_guid = ?");
        if ($stmt) {
            $stmt->bind_param("i", $guid);
            $stmt->execute();
            $stmt->close();
        }

        // 6. Finally, delete the site itself
        $stmt = $MySQL->getConnection()->prepare("DELETE FROM sites WHERE site_guid = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $guid);
            if ($stmt->execute()) {
                $stmt->close();

                // Commit the transaction since all operations were successful
                $MySQL->getConnection()->commit();
                return true;
            }
            $stmt->close();
        }

        // Rollback transaction if any step fails
        $MySQL->getConnection()->rollback();
        return false;

    } catch (mysqli_sql_exception $e) {
        // Rollback transaction if an exception occurs
        $MySQL->getConnection()->rollback();
        error_log("Error deleting site: " . $e->getMessage());
        return false;
    }
}

function countTenants($house_guid) {

    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT user_guid FROM workers WHERE house_guid = {$house_guid}");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $houses = [];
        while ($row = $result->fetch_assoc()) {
            $houses[] = $row;
        }
        $stmt->close();
        return count($houses);
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return false;
    }
}



function geocodeAddress($address) {
    global $geo_keys;
    $address = urlencode($address);
    $selectedLanguage = $selectedLanguage ?? 'English';
    $url = "https://maps.googleapis.com/maps/api/geocode/json?language={$geo_keys[$selectedLanguage]}&address={$address}&key=AIzaSyAiis9wACSZkGmA05MbavxRr1Zmy0XM_W0";

    $response = file_get_contents($url);
    $json = json_decode($response, true);

    if ($json['status'] === 'OK') {
        $lat = $json['results'][0]['geometry']['location']['lat'];
        $lng = $json['results'][0]['geometry']['location']['lng'];
        return ['lat' => $lat, 'lng' => $lng, 'address' => $address];
    } else {
        return false;
    }
}

function isWorker($guid): ?bool
{
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT worker_id FROM workers WHERE user_guid = ?");
    if ($stmt) {
        $stmt->bind_param("s", $guid);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        if ($result && $result->num_rows > 0)
            return true;
    } else {
        return null;
    }

    return false;
}

function isMobileDevice(): bool {
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }
    $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $mobileAgents = ['iphone', 'ipod', 'android', 'blackberry', 'windows phone', 'opera mini', 'iemobile', 'mobile', 'silk'];
    foreach ($mobileAgents as $device) {
        if (strpos($userAgent, $device) !== false) {
            return true;
        }
    }
    return false;
}

function getSitesForUser($user_guid)
{
    global $MySQL, $selectedLanguage;
    $sql = "
        SELECT sites.site_guid, sites.site_name 
        FROM sites
        LEFT JOIN site_managers ON sites.site_guid = site_managers.site_guid AND site_managers.user_guid = ?
        WHERE sites.site_owner_guid = ? OR site_managers.user_guid = ?;
            ";

    $stmt = $MySQL->getConnection()->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("iii", $user_guid, $user_guid, $user_guid);
        $stmt->execute();
        $stmt->bind_result($site_guid, $site_name);

        $sites = [];
        while ($stmt->fetch()) {
            $sites[] = ["site_guid" => $site_guid, "site_name" => $site_name];
        }

        $stmt->close();
        return !empty($sites) ? $sites : [["site_guid" => 0, "site_name" => htmlspecialchars($lang_data[$selectedLanguage]['no_sites_found'] ?? 'No sites found')]];
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return [["site_guid" => 0, "site_name" => htmlspecialchars($lang_data[$selectedLanguage]['error_fetching_sites'] ?? 'Error fetching sites')]];
    }
}

function old_getAllAssignments($site_guid = 0, $assignment = 0, $show_assigned_workers = false)
{
    global $MySQL, $selectedLanguage, $lang_data;
    $user_guid = $_SESSION['loggedIn']['user_guid'];
    echo '
            <table style="margin-top: 0px; width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <td colspan="4" style="text-decoration: underline; height: 25px;">' .
        (
        $assignment > 0 ?
            htmlspecialchars($lang_data[$selectedLanguage]['assignment_summary'] ?? "Assignment Summary") :
            ( ( $selectedLanguage != 'Arabic' && $selectedLanguage != 'Hebrew' ) ?
                htmlspecialchars($lang_data[$selectedLanguage]['assignments'] ?? "Assignments") .
                ( $show_assigned_workers == true ?
                    " " . htmlspecialchars($lang_data[$selectedLanguage]['summary'] ?? "Summary") :
                    " " . htmlspecialchars($lang_data[$selectedLanguage]['details'] ?? "Details")
                ) :
                ( $show_assigned_workers == true ?
                    htmlspecialchars($lang_data[$selectedLanguage]['summary'] ?? "Summary") . " " :
                    htmlspecialchars($lang_data[$selectedLanguage]['details'] ?? "Details") . " "
                ) . htmlspecialchars($lang_data[$selectedLanguage]['assignments'] ?? "Assignments")
            )
        ) . '
                        </td>
                    </tr>
                </thead>
           
            ';
    $shiftSql   = "";
    if ($_SESSION['loggedIn']['group'] === 'admins') {
        if($_GET['page'] != 'admin')
        {
            $shiftSql = "
                            SELECT 
                                shift_assignments.*
                            FROM 
                                shift_assignments
                            LEFT JOIN 
                                site_managers ON shift_assignments.site_guid = site_managers.site_guid AND site_managers.user_guid = ?
                            LEFT JOIN 
                                sites ON shift_assignments.site_guid = sites.site_guid AND sites.site_owner_guid = ?
                            WHERE 
                                shift_assignments.shift_end_date >= CURDATE()
                            AND 
                                (site_managers.site_guid IS NOT NULL OR sites.site_guid IS NOT NULL)
                        ";
            $stmt = $MySQL->getConnection()->prepare($shiftSql);
            $stmt->bind_param("ii", $user_guid, $user_guid);
        } else {
            if ($site_guid > 0)
                $shiftSql = "SELECT * FROM shift_assignments WHERE shift_assignments.assignment_guid > 0 AND shift_assignments.site_guid = ? AND shift_assignments.shift_end_date >= CURDATE()";
            else
                $shiftSql = "SELECT * FROM shift_assignments WHERE shift_assignments.assignment_guid > 0 AND shift_assignments.shift_end_date >= CURDATE()";
            $stmt = $MySQL->getConnection()->prepare($shiftSql);
            if ($site_guid > 0)
                $stmt->bind_param("i", $site_guid);
        }
    }
    elseif ($_SESSION['loggedIn']['group'] === 'site_managers')
    {
        $shiftSql = "
                            SELECT 
                                shift_assignments.*
                            FROM 
                                shift_assignments
                            LEFT JOIN 
                                site_managers ON shift_assignments.site_guid = site_managers.site_guid AND site_managers.user_guid = ?
                            LEFT JOIN 
                                sites ON shift_assignments.site_guid = sites.site_guid AND sites.site_owner_guid = ?
                            WHERE 
                                shift_assignments.shift_end_date >= CURDATE()
                            AND 
                                (site_managers.site_guid IS NOT NULL OR sites.site_guid IS NOT NULL)
                        ";
        $stmt = $MySQL->getConnection()->prepare($shiftSql);
        $stmt->bind_param("ii", $user_guid, $user_guid);
        if ($site_guid > 0)
            $shiftSql .= " AND shift_assignments.site_guid = ?";
    }

    // Append additional conditions
    if($assignment > 0)
        $shiftSql .= " AND shift_assignments.assignment_guid = ?";
    else
        $shiftSql .= " AND shift_assignments.assignment_guid > 0";

    // Re-prepare the statement with the final query
    if ($_SESSION['loggedIn']['group'] === 'admins' && $_GET['page'] != 'admin') {
        // Already prepared above
    } else {
        $stmt = $MySQL->getConnection()->prepare($shiftSql);
        if ($_SESSION['loggedIn']['group'] === 'site_managers' && $site_guid > 0) {
            // Bind site_guid and assignment_guid
            if($assignment > 0)
                $stmt->bind_param("iii", $user_guid, $user_guid, $site_guid, $assignment);
            else
                $stmt->bind_param("iii", $user_guid, $user_guid, $site_guid);
        } elseif ($_SESSION['loggedIn']['group'] === 'admins') {
            if($_GET['page'] != 'admin') {
                if($assignment > 0)
                    $stmt->bind_param("ii", $user_guid, $user_guid, $assignment);
                else
                    $stmt->bind_param("ii", $user_guid, $user_guid);
            }
        }
    }

    // Execute the prepared statement
    $url = 'index.php?lang=' . urlencode($selectedLanguage);
    foreach($_GET as $key => $value) {
        if($key == 'lang' || $key == 'action' || $key == 'site_guid') continue;
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    $url = preg_replace('/(sub_page=)[^&]+/', 'sub_page=assignments', $url);
    if($stmt)
    {
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error') . "');</script>";
        $result = false;
    }
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $str_time_start = strtotime($row['shift_start_date']);
            $str_time_end   = strtotime($row['shift_end_date']);
            if(date('m', $str_time_start) == date('m', $str_time_end) )
                $dates = date('j', $str_time_start) . '-' . date('j', $str_time_end) . '/' . date('n', $str_time_end);
            else
                $dates = date('j/n', $str_time_start) . ' - ' . date('j/n', $str_time_end);

            $assignment_guid = htmlspecialchars($row['assignment_guid']);
            $site_guid = htmlspecialchars($row['site_guid']);
            $workers = htmlspecialchars($row['workers']);
            $description = htmlspecialchars($row['description']);

            $countStmt = $MySQL->getConnection()->prepare("SELECT COUNT(user_guid) AS assigned_workers FROM shift_assignment_workers WHERE assignment_guid = ?");
            if($countStmt)
            {
                $countStmt->bind_param("i", $assignment_guid);
                $countStmt->execute();
                $count_result = $countStmt->get_result();
                $count_result = $count_result->fetch_assoc();
            }
            $assign_workers = $count_result['assigned_workers'];
            $countStmt->close();

            // Assign a unique ID for the collapsible section
            $collapse_id = "collapse_" . $assignment_guid;

            // Main Header Row with Clickable Functionality
            echo '
                                    <thead>
                                        <tr class="assignment-header" data-collapse-id="' . $collapse_id . '" style="border-bottom: 2px solid black; border-top: 2px solid black; cursor: pointer;">
                                            <th style="font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]['workers'] ?? "Workers") . '</th>
                                            <th style="font-weight: bolder; width: 32%;">' . htmlspecialchars($lang_data[$selectedLanguage]['site'] ?? "Site") . '</th>
                                            <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]['dates'] ?? "Dates") . '</th>
                                            <th style="font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]['actions'] ?? "Actions") . '</th>
                                        </tr>
                                    </thead>'."
                                    <tbody>
                                        <tr style='border-bottom: 0;' class='main-row'>
                                            <td style='border-bottom: 0; font-size: 0.75rem; width: 80px;'>{$assign_workers}/{$workers}</td>
                                            <td style='border-bottom: 0; font-size: 0.75rem;'>";
            if($_SESSION['loggedIn']['group'] === 'admins' || isSiteManagerForSite($site_guid, $user_guid)) {
                $page = ( $_SESSION['loggedIn']['group'] === 'admins' ? "admin" : "site_management" );
                echo "
                                                <a style='text-decoration: underline; color: black;' href='index.php?lang={$selectedLanguage}&page={$page}&sub_page=sites&action=edit&site_guid={$site_guid}'>" . getSiteName($site_guid) . "</a>";
            } else {
                echo
                getSiteName($site_guid);
            }
            echo "
                                            </td>
                                            
                                            <td style='border-bottom: 0; font-size: 0.75rem;'>{$dates}</td>
                                            <td style='border-bottom: 0; white-space: nowrap;'>";
            if($_SESSION['loggedIn']['group'] === 'admins') {
                echo "
                                                    <a href='{$url}&action=assign_car&assignment_guid={$assignment_guid}'><img class='manage_shift_btn' src='img/car.png' alt=''></a>
                                                    <a href='{$url}&action=assign&assignment_guid={$assignment_guid}'><img class='manage_shift_btn' src='img/assign_workers.png' alt=''></a>
                                                    ";
            }
            echo
                ($assignment > 0 || $site_guid > 0 ? "" : "<a href='{$url}&action=edit&assignment_guid={$assignment_guid}'><img class='manage_shift_btn' src='img/edit.png' alt=''></a>" ) . "
                                                <a href='{$url}&action=delete&assignment_guid={$assignment_guid}'><img class='manage_shift_btn' src='img/delete.png' alt=''></a>
                                            </td>
                                        </tr>
                                        ";

            // =================== Collapsible Section Starts Here ===================
            echo '
                                        <tr id="' . $collapse_id . '" class="assignment-details" style="display: none;">
                                            <td colspan="4">
                                                <table style="width: 100%; border-collapse: collapse;">
                                                    <tr>
                                                        <td style="padding: 10px; border: 1px solid #ddd;">
            ';

            // === Workers Section ===
            if ($show_assigned_workers) {
                // Fetch currently assigned workers
                $sql_workers = "
                                        SELECT w.user_guid, w.profession, u.first_name, u.last_name, w.worker_id
                                        FROM workers w
                                        JOIN users u ON w.user_guid = u.user_guid
                                        JOIN shift_assignment_workers saw ON w.user_guid = saw.user_guid
                                        WHERE saw.assignment_guid = ?
                                        ORDER BY w.worker_id
                                    ";
                $stmt_workers = $MySQL->getConnection()->prepare($sql_workers);
                $stmt_workers->bind_param("i", $assignment_guid);
                $stmt_workers->execute();
                $assigned_workers = $stmt_workers->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_workers->close();

                echo '
                                                            <strong>' . htmlspecialchars($lang_data[$selectedLanguage]['workers'] ?? "Workers") . ':</strong>
                                                            <ul style="list-style-type: none; padding-left: 0;">
                ';
                if (count($assigned_workers) > 0) {
                    foreach ($assigned_workers as $worker) {
                        $edit_worker_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&sub_page=workers&action=edit&user_guid=' . $worker['user_guid'];
                        $unassign_worker_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&sub_page=assignments&action=unassign&assignment_guid=' . $assignment_guid . '&user_guid=' . $worker['user_guid'];
                        echo '<li>' . htmlspecialchars($worker['worker_id']) . ' - <a href="' . $edit_worker_url . '">' . htmlspecialchars($worker['first_name'] . ' ' . $worker['last_name']) . '</a> (' . htmlspecialchars($worker['profession']) . ')';
                        if($_SESSION['loggedIn']['group'] === 'admins') {
                            echo '
                                                                    <a href="' . $edit_worker_url . '"><img class="manage_shift_btn" src="img/exchange.png" alt="Exchange"></a>
                                                                    <a href="' . $unassign_worker_url . '"><img class="manage_shift_btn" src="img/unassign.png" alt="Unassign"></a>
                                                                ';
                        }
                        echo '</li>';
                    }
                } else {
                    echo htmlspecialchars($lang_data[$selectedLanguage]['no_workers_assigned'] ?? "No workers assigned");
                }
                echo '</ul>';
            }

            echo '
                                                        </td>
                                                        <td style="padding: 10px; border: 1px solid #ddd;">
            ';

            // === Cars Section ===
            // Fetch all cars assigned to this assignment
            $stmt_cars = $MySQL->getConnection()->prepare("SELECT car_guid, car_model, car_number_plate, driver_guid FROM cars WHERE assignment_guid = ?");
            $stmt_cars->bind_param("i", $assignment_guid);
            $stmt_cars->execute();
            $result_cars = $stmt_cars->get_result();
            $cars = $result_cars->fetch_all(MYSQLI_ASSOC);
            $stmt_cars->close();

            if(count($cars) > 0)
            {
                echo '
                                                            <strong>' . htmlspecialchars($lang_data[$selectedLanguage]['cars'] ?? "Cars") . ':</strong>
                                                            <ul style="list-style-type: none; padding-left: 0;">
                ';
                foreach($cars as $car)
                {
                    $car_guid = htmlspecialchars($car['car_guid']);
                    $car_model = htmlspecialchars($car['car_model']);
                    $car_number_plate = htmlspecialchars($car['car_number_plate']);
                    $driver_guid = htmlspecialchars($car['driver_guid']);

                    // Driver Info
                    if($driver_guid)
                    {
                        $worker_id = getWorkerID($driver_guid);
                        $worker_name = getWorkerName($driver_guid, false);
                        $driver_info = '#' . htmlspecialchars($worker_id) . ' - <a href="index.php?lang=' . urlencode($selectedLanguage) . '&sub_page=workers&action=edit&user_guid=' . $driver_guid . '">' . htmlspecialchars($worker_name) . '</a>';
                        $assign_driver_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars&action=assign_driver&car_guid=' . $car_guid;
                    }
                    else
                    {
                        $driver_info = htmlspecialchars($lang_data[$selectedLanguage]['no_driver_assigned'] ?? 'No Driver Assigned');
                        $assign_driver_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars&action=assign_driver&car_guid=' . $car_guid;
                    }

                    $unassign_car_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&sub_page=assignments&action=unassign_car&car_guid=' . $car_guid;

                    echo '<li><strong>' . $car_model . '</strong> (' . $car_number_plate . ')<br>' . $driver_info;
                    if($_SESSION['loggedIn']['group'] === 'admins') {
                        echo '
                                                                    <a href="' . $assign_driver_url . '"><img class="manage_shift_btn" src="img/driver.png" alt="Assign/Change Driver"></a>
                                                                    <a href="' . $unassign_car_url . '"><img class="manage_shift_btn" src="img/unassign.png" alt="Unassign Car"></a>
                                                                ';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            else
            {
                echo htmlspecialchars($lang_data[$selectedLanguage]['no_car_or_driver'] ?? 'No car or driver assigned to this assignment');
            }

            echo '
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
            ';
            // =================== Collapsible Section Ends Here ===================
        }
    } else {
        echo '<tr><td colspan="4">' . htmlspecialchars($lang_data[$selectedLanguage]["no_requests_found"] ?? "No assignments found") . '</td></tr>';
    }
    echo '
            </tbody>
        </table>
    ';

    // === JavaScript for Toggle Functionality ===
    echo '
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const headers = document.querySelectorAll(".assignment-header");
            headers.forEach(function(header) {
                header.addEventListener("click", function() {
                    const collapseId = this.getAttribute("data-collapse-id");
                    const collapseRow = document.getElementById(collapseId);
                    if (collapseRow.style.display === "none" || collapseRow.style.display === "") {
                        collapseRow.style.display = "table-row";
                        localStorage.setItem(collapseId, "open");
                    } else {
                        collapseRow.style.display = "none";
                        localStorage.setItem(collapseId, "closed");
                    }
                });
                
                    alert();
                    const collapseId = this.getAttribute("data-collapse-id");
                    const collapseRow = document.getElementById(collapseId);
                    collapseRow.style.display = localStorage.getItem(collapseId) === "open" ? "table-row" : "none";
            });
            
        });
    </script>
    ';

    // === Optional CSS for Better UX ===
    echo '
    <style>
        .assignment-header {
            cursor: pointer;
        }
        .assignment-details td {
            background-color: #f9f9f9;
        }
        .assignment-details ul {
            margin: 0;
            padding: 0;
        }
        .assignment-details li {
            margin-bottom: 10px;
        }
    </style>
    ';
}


function getAllAssignments($site_guid = 0, $assignment = 0, $show_assigned_workers = false)
{
    global $MySQL, $selectedLanguage, $lang_data;
    $user_guid = $_SESSION['loggedIn']['user_guid'];
    echo '
            <table style="margin-top: 0;">
                <thead>
                    <tr>
                        <td colspan="4" style="text-decoration: underline; height: 25px;">' .
                            (
                                $assignment > 0 ?
                                    htmlspecialchars($lang_data[$selectedLanguage]['assignment_summary'] ?? "Assignment Summary") :
                                    ( ( $selectedLanguage != 'Arabic' && $selectedLanguage != 'Hebrew' ) ?
                                        htmlspecialchars($lang_data[$selectedLanguage]['assignments'] ?? "Assignments") .
                                        ( $show_assigned_workers == true ?
                                            " " . htmlspecialchars($lang_data[$selectedLanguage]['summary'] ?? "Summary") :

                                            " " . htmlspecialchars($lang_data[$selectedLanguage]['details'] ?? "Details")
                                        ) :
                                        ( $show_assigned_workers == true ?
                                            htmlspecialchars($lang_data[$selectedLanguage]['summary'] ?? "Summary") . " " :

                                            htmlspecialchars($lang_data[$selectedLanguage]['details'] ?? "Details") . " "
                                        ) . htmlspecialchars($lang_data[$selectedLanguage]['assignments'] ?? "Assignments")
                                    )
                            ) . '
                        </td>
                    </tr>
                </thead>
           
            ';
    $shiftSql   = "";
    if ($_SESSION['loggedIn']['group'] === 'admins') {
        if ($site_guid > 0)
            $shiftSql = "SELECT * FROM shift_assignments WHERE shift_assignments.assignment_guid > 0 AND shift_assignments.site_guid = {$site_guid} AND shift_assignments.shift_end_date >= CURDATE()";
        else
            $shiftSql = "SELECT * FROM shift_assignments WHERE shift_assignments.assignment_guid > 0 AND shift_assignments.shift_end_date >= CURDATE()";
    }
    elseif ($_SESSION['loggedIn']['group'] === 'site_managers')
    {
        $shiftSql = "
                            SELECT 
                                shift_assignments.*
                            FROM 
                                shift_assignments
                            LEFT JOIN 
                                site_managers ON shift_assignments.site_guid = site_managers.site_guid AND site_managers.user_guid = {$user_guid}
                            LEFT JOIN 
                                sites ON shift_assignments.site_guid = sites.site_guid AND sites.site_owner_guid = {$user_guid}
                            WHERE 
                                shift_assignments.shift_end_date >= CURDATE()
                            AND 
                                (site_managers.site_guid IS NOT NULL OR sites.site_guid IS NOT NULL)
                        ";

        if ($site_guid > 0)
            $shiftSql .= " AND shift_assignments.site_guid = {$site_guid}";
    }

    if($assignment > 0)
        $shiftSql .= " AND shift_assignments.assignment_guid = {$assignment}";
    else
        $shiftSql .= " AND shift_assignments.assignment_guid > 0";

    $shiftSql .= " ORDER BY site_guid ASC, shift_start_date DESC";

    $stmt = $MySQL->getConnection()->prepare($shiftSql);
    $page = $_SESSION['loggedIn']['group'] === 'admins' ? "admin" : "site_management";
    $url = 'index.php?lang=' . urlencode($selectedLanguage) . '&page='.$page;
    if($stmt)
    {
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['database_error'] ?? 'Database error') . "');</script>";
        $result = false;
    }
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $str_time_start = strtotime($row['shift_start_date']);
            $str_time_end   = strtotime($row['shift_end_date']);
            if(date('m', $str_time_start) == date('m', $str_time_end) )
                $dates = date('j', $str_time_start) . '-' . date('j', $str_time_end) . '/' . date('n', $str_time_end);
            else
                $dates = date('j/n', $str_time_start) . ' - ' . date('j/n', $str_time_end);

            $assignment_guid = htmlspecialchars($row['assignment_guid']);
            $siteGuid = htmlspecialchars($row['site_guid']);
            $workers = $row['workers'];
            $description  = $row['description'];
            $description_array = json_decode($description, true);
            $descriptions = "";
            foreach ((is_array($description_array) ? $description_array : [$row['description']]) as $key => $desc) {
                if($key == 0)
                    $descriptions .= "{$desc}";
                else
                    $descriptions .= ", {$desc}";
            }
            $description = $descriptions;
            $workers_array = json_decode($workers, true);
            $workers = array_sum(array_map('intval', (is_array($workers_array) ? $workers_array : [$workers]) ));

            $countStmt = $MySQL->getConnection()->prepare("SELECT COUNT(user_guid) AS assigned_workers FROM shift_assignment_workers WHERE assignment_guid = {$assignment_guid}");
            if($countStmt)
            {
                $countStmt->execute();
                $count_result = $countStmt->get_result();
                $count_result = $count_result->fetch_assoc();
            }
            $assign_workers = $count_result['assigned_workers'];
            $countStmt->close();
            // Assign a unique ID for the collapsible section
            $collapse_id = "collapse_" . $assignment_guid;
            $display = "style='display: none;'";
            $align = ($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "left" : "right";
            $px = 0;
            $bg_color = '';
            if($assign_workers == $workers)
            {
                $bg_color = ' background-color: #c3f6c3;';
            } elseif($assign_workers > $workers) {
                $display = "style='display: ;'";
                $bg_color = ' background-color: lightcoral;';
            }
            echo '
                                    <thead>
                                        <tr class="assignment-header" data-collapse-id="' . $collapse_id . '" style="border-bottom: 2px solid black; border-top: 2px solid black; height: 28px;">
                                            <th style="font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]['workers'] ?? "Workers") . '</th>
                                            <th style="font-weight: bolder; width: 32%;">' . htmlspecialchars($lang_data[$selectedLanguage]['site'] ?? "Site") . '</th>
                                            <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]['dates'] ?? "Dates") . '</th>
                                            <th style="font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content;">
                                                '."<a data-collapse-id='{$collapse_id}' href='#' onclick='event.preventDefault(); event.stopPropagation(); toggleMenu(\"collapse_\"+{$assignment_guid});'>
                                                    <img class='manage_shift_btn' src='img/menu.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['show_hide_menu'] ?? 'Show/Hide Menu') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['show_hide_menu'] ?? 'Show/Hide Menu') . "'>
                                                </a>".'
                                            </th>
                                        </tr>
                                    </thead>'."
                                    <tbody>
                                        <tr style='border-bottom: 1px solid #dddddd;{$bg_color}'>
                                            <td style='border-bottom: 0; font-size: 0.75rem; width: 80px;'>{$assign_workers}/{$workers}</td>
                                            <td style='border-bottom: 0; font-size: 0.75rem;'>";
            if( ( $_SESSION['loggedIn']['group'] === 'admins' || isSiteManagerForSite($siteGuid, $user_guid) ) && !$site_guid ) {
                $page = ( $_SESSION['loggedIn']['group'] === 'admins' ? "admin" : "site_management" );
                echo "
                                                <a style='text-decoration: underline; color: black;' href='index.php?lang={$selectedLanguage}&page={$page}&sub_page=sites&action=edit&site_guid={$siteGuid}'>" . getSiteName($siteGuid) . "</a>";
            } else {
                echo
                getSiteName($siteGuid);
            }
            echo "
                                            </td>
                                            
                                            <td style='border-bottom: 0; font-size: 0.75rem;'>{$dates}</td>
                                            <td style='text-align: {$align}; border-bottom: 0; white-space: nowrap; display: none;' id='{$collapse_id}_sub_menu'>";
            if($_SESSION['loggedIn']['group'] === 'admins') {
                echo "
                                                    <a href='{$url}&sub_page=assignments&action=assign_car&assignment_guid={$assignment_guid}'><img class='manage_shift_btn' src='img/car.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['assign_car'] ?? 'Assign Car') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['assign_car'] ?? 'Assign Car') . "'></a> 
                                                    <a href='{$url}&sub_page=assignments&action=assign&assignment_guid={$assignment_guid}'><img class='manage_shift_btn' src='img/assign_workers.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['assign_workers'] ?? 'Assign Workers') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['assign_workers'] ?? 'Assign Workers') . "'></a> 
                                                    ";
                                                    $px += 2;
            }
            if($assignment == 0)
            {
                echo " <a href='{$url}&sub_page=assignments&action=edit&assignment_guid={$assignment_guid}'><img class='manage_shift_btn' src='img/edit.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['edit'] ?? 'Edit') . "'></a> ";
                $px += 1;
            }
            echo " <a href='{$url}&sub_page=assignments&action=delete&assignment_guid={$assignment_guid}'><img class='manage_shift_btn' src='img/delete.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['delete'] ?? 'Delete') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['delete'] ?? 'Delete') . "'></a> ";
            $px += 1;
            echo "
                                            </td>
                                            <td style='text-align: {$align}; white-space: nowrap;' id='{$collapse_id}_menu'>";
                                            for($count = 1; $count <= $px; $count++)
                                                echo "<div style='display: inline-flex;' id='btn_placeholder'></div>";
            echo "
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody {$display} id='{$collapse_id}'>";

            if($show_assigned_workers)
            {
                // Fetch currently assigned workers
                $sql = "
                                            SELECT w.user_guid, w.profession, u.first_name, u.last_name, w.worker_id
                                            FROM workers w
                                            JOIN users u ON w.user_guid = u.user_guid
                                            JOIN shift_assignment_workers saw ON w.user_guid = saw.user_guid
                                            WHERE saw.assignment_guid = ?
                                            ORDER BY w.worker_id
                                        ";
                $stmt = $MySQL->getConnection()->prepare($sql);
                $stmt->bind_param("i", $assignment_guid);
                $stmt->execute();
                $assigned_workers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                $editurl                = $url.'&sub_page=workers&action=assign&to=assignment';
                $del_url                = $url.'&sub_page=assignments&action=unassign';
                $workerurl              = $url.'&sub_page=workers&action=edit';

                if (count($assigned_workers) > 0) {
                    echo '
                                                <tr style="border-top: 1px solid #ddd;">
                                                
                                                            <th style="font-weight: bolder;"">#</th>
                                                            <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]['worker'] ?? "Worker") . '</th>
                                                            <th style="font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]['profession'] ?? "Profession") . '</th>';
                    if($_SESSION['loggedIn']['group'] === 'admins')
                        echo '<th style="font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content; font-size: 0.75rem;"></th>';
                    else
                        echo '<th></th>';

                    echo '
                                                        </tr>';
                    $url = 'index.php?lang=' . urlencode($selectedLanguage);
                    foreach($_GET as $key => $value) {
                        if($key == 'lang' || $key == 'action' || $key == 'site_guid' || $key == 'sub_page') continue;
                        $url .= '&' . urlencode($key) . '=' . urlencode($value);
                    }


                        foreach ($assigned_workers as $worker) {
                        $edit_url           = preg_replace('/(to=)[^&]+/', 'to=assignment&user_guid=' . $worker['user_guid'], $editurl);
                        $delete_url         = preg_replace('/(action=)[^&]+/', 'action=unassign&assignment_guid='. $assignment_guid .'&user_guid=' . $worker['user_guid'], $del_url);
                        $worker_url         = preg_replace('/(action=)[^&]+/', 'action=edit&user_guid=' . $worker['user_guid'], $workerurl);

                        echo '
                                                                            <tr style="height: 25px;">';
                        if($_SESSION['loggedIn']['group'] === 'admins')
                        {
                            echo '                                              <td style="border-bottom: 0; border-top: 1px solid #ddd; font-size: 0.7rem;"><a style="color: black; text-decoration: underline;" href='.$worker_url.'>' . htmlspecialchars($worker['worker_id']) . '</a></td>
                                                                                <td style="border-bottom: 0; border-top: 1px solid #ddd; font-size: 0.7rem;"><a style="color: black; text-decoration: underline;" href='.$worker_url.'>' . getWorkerName($worker['user_guid'], true, true) . '</a></td>';
                        } else {
                            echo '                                              <td style="border-bottom: 0; border-top: 1px solid #ddd; font-size: 0.7rem;">' . htmlspecialchars($worker['worker_id']) . '</td>
                                                                                <td style="border-bottom: 0; border-top: 1px solid #ddd; font-size: 0.7rem;">' . htmlspecialchars($worker['first_name']) . '</td>';
                        }
                        echo '

                                                                                <td style="border-bottom: 0; border-top: 1px solid #ddd; font-size: 0.7rem;">' . htmlspecialchars($worker['profession']) . '</td>';
                        if($_SESSION['loggedIn']['group'] === 'admins') {
                            echo '
                                                                                <td style="border-bottom: 0; border-top: 1px solid #ddd; text-align: '.$align.'; font-size: 0.75rem; white-space: nowrap;">
                                                                                    <a href="' . $edit_url . '"><img class="manage_shift_btn" src="img/exchange.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['change_assignment'] ?? "Change Assignment") . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['change_assignment'] ?? "Change Assignment") . '"></a>
                                                                                    <a href="' . $delete_url . '"><img class="manage_shift_btn" src="img/unassign.png" alt="' . htmlspecialchars($lang_data[$selectedLanguage]['unassign'] ?? "Unassign") . '" title="' . htmlspecialchars($lang_data[$selectedLanguage]['unassign'] ?? "Unassign") . '"></a>
                                                                                </td>
                                                                            </tr>';
                        } else {
                            echo '<td style="border-bottom: 0; border-top: 1px solid #ddd;"></td></tr>';
                        }
                    }
                } else {
                    echo '<tr><td colspan="4" style="font-size: 0.85rem; border-top: 1px solid #ddd; border-bottom: 0; height: 20px;">' . htmlspecialchars($lang_data[$selectedLanguage]['no_workers_assigned'] ?? "No workers assigned") . '</td></tr>';
                }

            }
            // =================== Modified Section Starts Here ===================
            // Fetch all cars assigned to this assignment
            $stmt_cars = $MySQL->getConnection()->prepare("SELECT car_guid, car_model, car_number_plate, driver_guid, max_passengers FROM cars WHERE assignment_guid = ?");
            $stmt_cars->bind_param("i", $assignment_guid);
            $stmt_cars->execute();
            $result_cars = $stmt_cars->get_result();
            $cars = $result_cars->fetch_all(MYSQLI_ASSOC);
            $stmt_cars->close();
            $needed_seats=$assign_workers;
            if(count($cars) > 0)
            {
                echo "
                                    <tr style='border-top: 1px solid #ddd;'>
                                       <th colspan='1' style='font-weight: bolder;'>
                                            " . htmlspecialchars($lang_data[$selectedLanguage]['seats'] ?? 'Seats') . "
                                       </th>
                                       <th colspan='1' style='font-weight: bolder;'>
                                           " . htmlspecialchars($lang_data[$selectedLanguage]['assigned_car'] ?? 'Assigned Car') . "
                                       </th>
                                       <th colspan='1' style='font-weight: bolder';>
                                           " . htmlspecialchars($lang_data[$selectedLanguage]['assigned_driver'] ?? 'Assigned Driver') . "
                                       </th>
                                       <th colspan='1' style='font-weight: bolder;'>
                                       </th>
                                   </tr>";
                $total_seats = 0;

                foreach($cars as $car)
                {
                    $car_guid = htmlspecialchars($car['car_guid']);
                    $car_model = htmlspecialchars($car['car_model']);
                    $car_number_plate = htmlspecialchars($car['car_number_plate']);
                    $driver_guid = htmlspecialchars($car['driver_guid']);
                    $max_seats = htmlspecialchars($car['max_passengers']);
                    $total_seats += $max_seats;
                    $unassign_car_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=assignments&action=unassign_car&car_guid=' . $car_guid;

                    // Fetch driver info if assigned
                    if($driver_guid)
                    {
                        $worker_id = getWorkerID($driver_guid);
                        $worker_name = getWorkerName($driver_guid, false);
                        $driver_info = "#" . $worker_id . " - " . htmlspecialchars($worker_name);
                        $driver_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=workers&action=edit&user_guid=' . $driver_guid;
                        $edit_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars&action=assign_driver&car_guid=' . $car_guid;

                    }
                    else
                    {
                        $driver_info = htmlspecialchars($lang_data[$selectedLanguage]['no_driver_assigned'] ?? 'No Driver Assigned');
                        $driver_url = $edit_url = 'index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=cars&action=assign_driver&car_guid=' . $car_guid;
                    }

                    echo "
                                   
                                   <tr style='height: 25px;'>
                                       <td>".($needed_seats>$max_seats ? $max_seats : $needed_seats)."/{$max_seats}</td>
                                       <td colspan='1' style='white-space: nowrap; font-size: 0.7rem;'>";
                    $needed_seats = ($needed_seats-$max_seats);
                    if($_SESSION['loggedIn']['group'] === 'admins') {
                        echo "
                                           <a style='text-decoration: underline; color: black;' href='{$edit_url}'>" . $car_model . " (" . $car_number_plate . ")</a>";
                    } else {
                        echo "
                                           " . $car_model . " (" . $car_number_plate . ")";
                    }
                    echo "
                                       </td>
                                       <td colspan='1' style='font-size: 0.7rem;'>";
                    if($_SESSION['loggedIn']['group'] === 'admins') {
                        echo "
                                           <a style='text-decoration: underline; color: black;' href='{$driver_url}'>" . $driver_info . "</a>";
                    } else {
                        echo
                        htmlspecialchars($driver_info);
                    }

                    echo "
                                       </td>
                                       <td style='text-align: " . (($selectedLanguage == "Hebrew" || $selectedLanguage == "Arabic") ? "left" : "right") . ";'>";
                    if($_SESSION['loggedIn']['group'] === 'admins') {
                        echo "
                                           <a href='{$edit_url}'><img class='manage_shift_btn' src='img/driver.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['assign_driver'] ?? 'Assign Driver') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['assign_driver'] ?? 'Assign Driver') . "'></a>
                                           <a href='{$unassign_car_url}'><img class='manage_shift_btn' src='img/unassign.png' alt='" . htmlspecialchars($lang_data[$selectedLanguage]['unassign_car'] ?? 'Unassign Car') . "' title='" . htmlspecialchars($lang_data[$selectedLanguage]['unassign_car'] ?? 'Unassign Car') . "'></a>
                                       ";
                    }
                    echo "
                                       </td>
                                   </tr>
                                   ";
                }

                if($needed_seats > 0) {
                    echo "
                    <tr>
                        <td colspan='4' style='font-size: 0.85rem; border-top: 1px solid #ddd; height: 20px;'>
                            " . htmlspecialchars($lang_data[$selectedLanguage]['another_car_is_needed_for_assignment'] ?? 'Another car is needed for this assignment') . "
                        </td>
                    </tr>
                    ";
                }
            }
            else
            {
                echo "
                                    <tr>
                                        <td colspan='4' style='font-size: 0.85rem; border-top: 1px solid #ddd; height: 20px;'>
                                            " . htmlspecialchars($lang_data[$selectedLanguage]['no_car_or_driver'] ?? 'No car or driver assigned to this assignment') . "
                                        </td>
                                    </tr>
                                    ";
            }
            echo "                    </tbody>
                                    <tr>
                                        <td style='border-bottom: 0; height: 20px;' colspan='4'>
                                        
                                        </td>
                                    </tr>
                                    ";
// =================== Modified Section Ends Here ===================
        }
    } else {
        echo '<tr><td colspan="4">' . htmlspecialchars($lang_data[$selectedLanguage]["no_requests_found"] ?? "No assignments found") . '</td></tr>';
    }
    echo '
            
        </table>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const headers = document.querySelectorAll(".assignment-header");
                headers.forEach(function(header) {
                    
                    header.addEventListener("click", function() {
                        const collapseId = this.getAttribute("data-collapse-id");
                        
                        const collapseRow = document.getElementById(collapseId);
                        if (collapseRow.style.display === "none") {
                            collapseRow.style.display = "";
                            localStorage.setItem(collapseId, "open");
                        } else {
                            collapseRow.style.display = "none";
                            localStorage.setItem(collapseId, "closed");
                        }
                    });
                    
                    const collapseId = header.getAttribute("data-collapse-id");
                    const collapseRow = document.getElementById(collapseId);
                    collapseRow.style.display = localStorage.getItem(collapseId) === "closed" ? "none" : "";
                    
                    const MenuElement = document.getElementById(collapseId+"_menu");
                    const subMenuElement = document.getElementById(collapseId+"_sub_menu");

                    if(localStorage.getItem(collapseId+"_menu") === "closed")
                        {
                            MenuElement.style.display = "block";
                            subMenuElement.style.display = "none";
                        } else {
                            MenuElement.style.display = "none";
                            subMenuElement.style.display = "block";
                        }
                });
            });

            
            function toggleMenu(element_ID) {      
            let MenuElement = document.getElementById(element_ID+"_menu");
            let subMenuElement = document.getElementById(element_ID+"_sub_menu");
        
            if (subMenuElement.style.display === "none") {
                MenuElement.style.display = "none";
                subMenuElement.style.display = "block";
                localStorage.setItem(element_ID+"_menu", "open"); // Save the state as open
            } else {
                MenuElement.style.display = "block";
                subMenuElement.style.display = "none";
                localStorage.setItem(element_ID+"_menu", "closed"); // Save the state as closed
            }
        }
        </script>';
}

/**
 * Get assignment description including assigned workers count and site details.
 *
 * @param int $assignment_guid The assignment GUID.
 * @return string The formatted description of the assignment.
 */
function getAssignmentInformation($assignment_guid) {
    global $MySQL, $selectedLanguage;

    // Fetch the number of workers assigned to this assignment
    $stmt = $MySQL->getConnection()->prepare("
        SELECT COUNT(saw.user_guid) AS assigned_workers, sa.site_guid 
        FROM shift_assignment_workers saw
        JOIN shift_assignments sa ON saw.assignment_guid = sa.assignment_guid
        WHERE sa.assignment_guid = ?
    ");
    $stmt->bind_param("i", $assignment_guid);
    $stmt->execute();
    $stmt->bind_result($assigned_workers, $site_guid);
    if (!$stmt->fetch()) {
        $stmt->close();
        return htmlspecialchars($lang_data[$selectedLanguage]['no_assignment_found'] ?? "No assignment found.");
    }
    $stmt->close();

    $stmt = $MySQL->getConnection()->prepare("
        SELECT sa.site_guid 
        FROM shift_assignments sa
        JOIN sites si ON si.site_guid = sa.site_guid
        WHERE sa.assignment_guid = ?
    ");
    $stmt->bind_param("i", $assignment_guid);
    $stmt->execute();
    $stmt->bind_result($site_guid);
    if (!$stmt->fetch()) {
        $stmt->close();
        return htmlspecialchars($lang_data[$selectedLanguage]['no_assignment_found'] ?? "No assignment found.");
    }
    $stmt->close();

    // Fetch site information based on site_guid
    $stmt = $MySQL->getConnection()->prepare("
        SELECT site_name, site_address 
        FROM sites 
        WHERE site_guid = ?
    ");
    $stmt->bind_param("i", $site_guid);
    $stmt->execute();
    $stmt->bind_result($site_name, $site_address);
    if (!$stmt->fetch()) {
        $stmt->close();
        return htmlspecialchars($lang_data[$selectedLanguage]['site_information_not_found'] ?? "Site information not found.");
    }
    $stmt->close();

    // Create the description string
    $description = htmlspecialchars($lang_data[$selectedLanguage]['assignment_for_site'] ?? 'Assignment for site') . ": " . htmlspecialchars($site_name) . " (" . htmlspecialchars($site_address) . "). ";
    $description .= htmlspecialchars($lang_data[$selectedLanguage]['assigned_workers'] ?? 'Assigned workers') . ": " . htmlspecialchars($assigned_workers) . ".";

    return $description;
}

function getAssignmentDescription($assignment_guid) {
    global $MySQL, $selectedLanguage;

    // Fetch assignment information based on assignment_guid
    $stmt = $MySQL->getConnection()->prepare("
        SELECT description
        FROM shift_assignments 
        WHERE assignment_guid = ?
    ");
    $stmt->bind_param("i", $assignment_guid);
    $stmt->execute();
    $stmt->bind_result($description);
    if (!$stmt->fetch()) {
        $stmt->close();
        return htmlspecialchars($lang_data[$selectedLanguage]['site_information_not_found'] ?? "Assignment information not found.");
    }
    $stmt->close();
    $description_array = json_decode($description, true);
    $descriptions = "";
    foreach ((is_array($description_array) ? $description_array : [$description]) as $key => $desc) {
        if($key == 0)
            $descriptions .= "{$desc}";
        else
            $descriptions .= ", {$desc}";
    }
    return $descriptions;
}

// Function to unassign a worker from the assignment
function unassignWorker($user_guid, $assignment_guid) {
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("DELETE FROM shift_assignment_workers WHERE user_guid = ? AND assignment_guid = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $user_guid, $assignment_guid);
        $stmt->execute();
        $stmt->close();
    }
}

function fetchAllCars() {
    global $MySQL;

    try {
        $sql = "SELECT * FROM cars WHERE car_guid > 0";
        $stmt = $MySQL->getConnection()->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $cars = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $cars;
    } catch (mysqli_sql_exception $e) {
        error_log("Error fetching cars: " . $e->getMessage());
        return false;
    }
}

function isAssignedToWork($user_guid) : int {
    global $MySQL;

    // Prepare the SQL query
    $sql = "
        SELECT sa.site_guid
        FROM shift_assignment_workers saw
        JOIN shift_assignments sa ON saw.assignment_guid = sa.assignment_guid
        WHERE saw.user_guid = ? AND CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date
        LIMIT 1
    ";

    // Prepare the statement
    $stmt = $MySQL->getConnection()->prepare($sql);
    if (!$stmt) {
        error_log("MySQL Error: " . $MySQL->getConnection()->error);
        return 0;
    }

    // Bind the parameter
    $stmt->bind_param("i", $user_guid);

    // Execute the query
    $stmt->execute();

    // Bind the result
    $stmt->bind_result($site_guid);

    // Fetch the result
    if ($stmt->fetch()) {
        $stmt->close();
        return $site_guid;
    } else {
        $stmt->close();
        return 0; // Return 0 if no assignment is found
    }
}


function getAssignmentForWorker($user_guid) : int {
    global $MySQL;

    // Prepare the SQL query
    $sql = "
        SELECT sa.assignment_guid
        FROM shift_assignment_workers saw
        JOIN shift_assignments sa ON saw.assignment_guid = sa.assignment_guid
        WHERE saw.user_guid = ?
        LIMIT 1
    ";

    // Prepare the statement
    $stmt = $MySQL->getConnection()->prepare($sql);
    if (!$stmt) {
        error_log("MySQL Error: " . $MySQL->getConnection()->error);
        return 0;
    }

    // Bind the parameter
    $stmt->bind_param("i", $user_guid);

    // Execute the query
    $stmt->execute();

    // Bind the result
    $stmt->bind_result($assignment_guid);

    // Fetch the result
    if ($stmt->fetch()) {
        $stmt->close();
        return $assignment_guid;
    } else {
        $stmt->close();
        return 0; // Return 0 if no assignment is found
    }
}

function isOwnerForSite($site_guid, $user_guid) {
    global $MySQL;

    // Ensure site_guid and user_guid are valid integers
    $site_guid = intval($site_guid);
    $user_guid = intval($user_guid);

    // Prepare SQL to check if the user is the manager for the site
    $sql = "
        SELECT COUNT(*) AS is_manager
        FROM sites
        WHERE site_guid = ? AND site_owner_guid = ?
        LIMIT 1
    ";

    $stmt = $MySQL->getConnection()->prepare($sql);

    if ($stmt) {
        // Bind the parameters (site_guid and user_guid)
        $stmt->bind_param('ii', $site_guid, $user_guid);
        $stmt->execute();

        // Get the result
        $stmt->bind_result($is_owner);
        $stmt->fetch();
        $stmt->close();

        // Return true if the user is the manager, false otherwise
        return $is_owner > 0;
    } else {
        error_log("Database error: " . $MySQL->getConnection()->error);
        return false;
    }
}

function isSiteManagerForSite($site_guid, $user_guid) {
    global $MySQL;

    if(isOwnerForSite($site_guid, $user_guid)) {
        return 1;
    }

    // Ensure site_guid and user_guid are valid integers
    $site_guid = intval($site_guid);
    $user_guid = intval($user_guid);

    // Prepare SQL to check if the user is the manager for the site
    $sql = "
        SELECT COUNT(*) AS is_manager
        FROM site_managers
        WHERE site_guid = ? AND user_guid = ?
        LIMIT 1
    ";

    $stmt = $MySQL->getConnection()->prepare($sql);

    if ($stmt) {
        // Bind the parameters (site_guid and user_guid)
        $stmt->bind_param('ii', $site_guid, $user_guid);
        $stmt->execute();

        // Get the result
        $stmt->bind_result($is_manager);
        $stmt->fetch();
        $stmt->close();

        // Return true if the user is the manager, false otherwise
        return $is_manager > 0;
    } else {
        error_log("Database error: " . $MySQL->getConnection()->error);
        return false;
    }
}

function getCarDetails($car_guid) : array
{
    global $MySQL;

    try {
        $sql = "SELECT * FROM cars WHERE car_guid = ?";
        $stmt = $MySQL->getConnection()->prepare($sql);
        $stmt->bind_param("i", $car_guid);
        $stmt->execute();
        $result = $stmt->get_result();
        $cars = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $cars;
    } catch (mysqli_sql_exception $e) {
        error_log("Error fetching cars: " . $e->getMessage());
        return [];
    }
}

function fetchAssignments($user_guid) {
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT assignment_guid, description FROM shift_assignments WHERE site_guid > 0 AND ( site_guid IN (SELECT site_guid FROM shift_assignment_workers WHERE user_guid = ?) OR site_guid IN (SELECT site_guid FROM shifts WHERE user_guid = ?) )");
    $stmt->bind_param("ii", $user_guid, $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * @param $user_guid
 * @return array
 */
function fetchSites($user_guid) {
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT site_guid, site_name FROM sites WHERE site_guid > 0 AND ( site_owner_guid = ? OR site_guid IN (SELECT site_guid FROM site_managers WHERE user_guid = ?) OR site_guid IN (SELECT site_guid FROM shifts WHERE user_guid = ?) )");
    $stmt->bind_param("iii", $user_guid, $user_guid, $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchShifts($user_guid) {
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT shift_guid, shift_start, shift_end FROM shifts WHERE user_guid = ?");
    $stmt->bind_param("i", $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchCars($user_guid) {
    global $MySQL;
    $stmt = $MySQL->getConnection()->prepare("SELECT car_guid, car_model, car_number_plate FROM cars WHERE driver_guid = ? AND car_guid > 0");
    $stmt->bind_param("i", $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getDefaultLanguage()
{
    global $MySQL, $default_lang;
    $guid = isset($_SESSION['loggedIn']) ? $_SESSION['loggedIn']['user_guid'] : 0;
    if(!$guid) return "English";
    $stmt = $MySQL->getConnection()->prepare("SELECT country FROM users WHERE user_guid = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $guid);
        $stmt->execute();
        $stmt->bind_result($country);
        if(!$stmt->fetch()) return $guid;
        return isset($default_lang[$country]) ? $default_lang[$country] : "English";
    } else {
        error_log("Prepare failed: " . $MySQL->getConnection()->error);
        return "English";
    }
}

/**
 * @return array
 */
function getAllAlerts() : array
{
    global $MySQL, $selectedLanguage, $lang_data;
    $alerts = [];

    // 1. Shift assignments without fully assigned workers
    $sql = "
        SELECT sa.site_guid, sa.assignment_guid, sa.description, sa.workers, COUNT(saw.user_guid) AS assigned_workers
        FROM shift_assignments sa
        LEFT JOIN shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
        WHERE sa.assignment_guid > 0
        GROUP BY sa.assignment_guid
    ";
    $stmt = $MySQL->getConnection()->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_workers = 0;
    $description = "";
    foreach ($result as $row) {
        $site_name = getSiteName($row['site_guid']);
        $workers_array = json_decode($row['workers'], true);
        $assigned_count = $row['assigned_workers'];
        $total_workers = array_sum(array_map('intval', is_array($workers_array) ? $workers_array : [$row['workers']]));
        $description_array = json_decode($row['description'], true);
        $descriptions = "";
        foreach ((is_array($description_array) ? $description_array : [$row['description']]) as $key => $desc) {
            if($key == 0)
                $descriptions .= "{$desc}";
            else
                $descriptions .= ", {$desc}";
        }
        $description = $descriptions;
        if($assigned_count < $total_workers) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['assignment_requires'] ?? "Assignment") . " '{$description}' " . htmlspecialchars($lang_data[$selectedLanguage]['at_site'] ?? "at site") . " '{$site_name}' " . htmlspecialchars($lang_data[$selectedLanguage]['requires_workers'] ?? "requires") . " {$total_workers} " . htmlspecialchars($lang_data[$selectedLanguage]['workers'] ?? "workers") . ", " . htmlspecialchars($lang_data[$selectedLanguage]['but_assigned'] ?? "but") . " {$assigned_count} " . htmlspecialchars($lang_data[$selectedLanguage]['assigned'] ?? "assigned") . ".",
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=assignments&action=assign&assignment_guid={$row['assignment_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/worker.png' alt=''>"
                ];
        }
    }

    // 11. Shift assignments without a car assigned (assignment_guid not in cars table)
    $sql = "
    SELECT assignment_guid, site_guid, description, workers 
    FROM shift_assignments 
    WHERE assignment_guid NOT IN (SELECT assignment_guid FROM cars)
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        $description = "";
        while ($row = $result->fetch_assoc()) {
            $site_name = getSiteName($row['site_guid']);
            $description_array = json_decode($row['description'], true);
            $descriptions = "";
            foreach ((is_array($description_array) ? $description_array : [$row['description']]) as $key => $desc) {
                if($key == 0)
                    $descriptions .= "{$desc}";
                else
                    $descriptions .= ", {$desc}";
            }
            $description = $descriptions;
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['assignment_requires'] ?? "Assignment") . " '{$description}' " . htmlspecialchars($lang_data[$selectedLanguage]['at_site'] ?? "at site") . " '{$site_name}' " . htmlspecialchars($lang_data[$selectedLanguage]['no_car_assigned'] ?? "does not have a car assigned."),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=assignments&action=assign_car&assignment_guid={$row['assignment_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/car_act.png' alt=''>"
                ];
        }
    }


    // 2. Shifts that started and not ended, and current time passed `shiftEnd_time`
    $currentTime = date("H:i:s");
    $currentDate = date("Y-m-d");

    $sql = "
    SELECT s.shift_guid, u.user_guid, u.first_name, si.site_name, DATE(s.shift_start) AS shift_start_date, TIME(s.shift_start) AS shift_start_time, si.shiftEnd_time, w.worker_id
    FROM shifts s
    JOIN users u ON s.user_guid = u.user_guid
    JOIN sites si ON s.site_guid = si.site_guid
    JOIN workers w ON u.user_guid = w.user_guid
    WHERE s.shift_end IS NULL
    AND (
        DATE(s.shift_start) < '{$currentDate}' -- Shift started on a previous day
        OR (DATE(s.shift_start) = '{$currentDate}' AND TIME(NOW()) > TIME(si.shiftEnd_time)) -- Shift started today but should have ended by now
    )
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['worker'] ?? "Worker") . " #{$row['worker_id']} {$row['first_name']} " . htmlspecialchars($lang_data[$selectedLanguage]['at_site'] ?? "at site") . " '{$row['site_name']}' " . htmlspecialchars($lang_data[$selectedLanguage]['started_at'] ?? "started at") . " {$row['shift_start_time']} " . htmlspecialchars($lang_data[$selectedLanguage]['should_have_ended'] ?? "and should have ended by") . " {$row['shiftEnd_time']}",
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=shifts&action=edit&shift_guid={$row['shift_guid']}&user_guid={$row['user_guid']}",
                    "level"         => "warning",
                    "image"         => "<img class='manage_shift_btn' src='img/over-time.png' alt=''>"
                ];
        }
    }

    // 14. Shifts with status 'pending' and passed 1 day or more from shift_end_date
    $sql = "
    SELECT s.shift_guid, u.user_guid, u.first_name, w.worker_id, si.site_name, s.shift_end, DATEDIFF(CURDATE(), DATE(s.shift_end)) AS days_passed
    FROM shifts s
    JOIN users u ON s.user_guid = u.user_guid
    JOIN sites si ON s.site_guid = si.site_guid
    JOIN workers w ON u.user_guid = w.user_guid
    WHERE s.status = 'pending'
    AND DATEDIFF(CURDATE(), DATE(s.shift_end)) >= 1 -- Shift has passed 1 or more days
    ";

    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['_workers'] ?? "Worker") . " #{$row['worker_id']}  " . htmlspecialchars($lang_data[$selectedLanguage]['pending_shift_at'] ?? "has a pending shift at site") . " '{$row['site_name']}' " . htmlspecialchars($lang_data[$selectedLanguage]['ended_on'] ?? "which ended on ") . date('j/n/y', strtotime($row['shift_end'])) . " ({$row['days_passed']} " . htmlspecialchars($lang_data[$selectedLanguage]['days_passed'] ?? " days ago") . ").",
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=shifts&action=edit&shift_guid={$row['shift_guid']}&user_guid={$row['user_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/deadline.png' alt=''>"
                ];
        }
    }

    // 3. Sites without a manager associated in `site_managers`
    $sql = "
    SELECT si.site_name, si.site_guid 
    FROM sites si
    LEFT JOIN site_managers sm ON si.site_guid = sm.site_guid
    WHERE sm.user_guid IS NULL AND si.site_guid > 0
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['site'] ?? "Site") . " '{$row['site_name']}' " . htmlspecialchars($lang_data[$selectedLanguage]['no_manager_assigned'] ?? "has no manager assigned."),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=sites&action=assign_manager&site_guid={$row['site_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/site_manager.png' alt=''>"
                ];
        }
    }

    // 4. Sites with a manager associated and the user group is not `admins` or `site_managers`
    $sql = "
    SELECT si.site_name, u.first_name, u.last_name, u.user_guid
    FROM sites si
    LEFT JOIN site_managers sm ON si.site_guid = sm.site_guid
    LEFT JOIN users u ON sm.user_guid = u.user_guid
    WHERE sm.user_guid != 0
    AND (u.`group` NOT IN ('admins', 'site_managers'))
    AND si.site_guid > 0
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['site'] ?? "Site") . " '{$row['site_name']}' " . htmlspecialchars($lang_data[$selectedLanguage]['manager_not_admin'] ?? "has a manager assigned who is not an admin or site manager (Manager: {$row['first_name']} {$row['last_name']})."),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=users&action=edit&user_guid={$row['user_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/edit_profile.png' alt=''>"
                ];
        }
    }

    // 5. Sites without a site owner associated
    $sql = "
    SELECT site_name, site_guid
    FROM sites 
    WHERE site_owner_guid = 0 AND site_guid > 0
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['site'] ?? "Site") . " '{$row['site_name']}' " . htmlspecialchars($lang_data[$selectedLanguage]['no_owner_assigned'] ?? "has no site owner associated."),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=sites&action=edit&site_guid={$row['site_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/site_owner.png' alt=''>"
                ];
        }
    }

    // 6. Sites with a site owner associated and the user group is not `admins` or `site_managers`
    $sql = "
    SELECT si.site_name, u.first_name, u.last_name, u.user_guid
    FROM sites si
    LEFT JOIN users u ON si.site_owner_guid = u.user_guid
    WHERE si.site_owner_guid != 0
    AND (u.`group` NOT IN ('admins', 'site_managers'))
    AND (si.site_guid > 0)
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['site'] ?? "Site") . " '{$row['site_name']}' " . htmlspecialchars($lang_data[$selectedLanguage]['owner_not_admin'] ?? "has an owner who is not an admin or site manager (Owner: {$row['first_name']} {$row['last_name']})."),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=users&action=edit&user_guid={$row['user_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/edit_profile.png' alt=''>"
                ];
        }
    }

    // 7. Sites without an address associated
    $sql = "
    SELECT site_name, site_guid 
    FROM sites 
    WHERE site_address = ''
    AND site_guid > 0
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['site'] ?? "Site") . " '{$row['site_name']}' " . htmlspecialchars($lang_data[$selectedLanguage]['no_address_assigned'] ?? "has no address associated."),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=sites&action=edit&site_guid={$row['site_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/address.png' alt=''>"
                ];
        }
    }

    // 8. Additional alerts (e.g., expired drivers licenses)
    $sql = "
    SELECT u.first_name, u.last_name, w.drivers_license, w.worker_id
    FROM workers w
    JOIN users u ON w.user_guid = u.user_guid
    WHERE w.drivers_license < CURDATE() AND u.group = 'drivers'
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['worker'] ?? "Worker") . " #{$row['worker_id']} - {$row['first_name']}'s " . htmlspecialchars($lang_data[$selectedLanguage]['driver_license_expired'] ?? "Driver license has expired."),
                    "action"        => "#",
                    "level"         => "warning",
                    "image"         => "<img class='manage_shift_btn' src='img/driver_license.png' alt=''>"
                ];
        }
    }

    // 9. Expired health insurances
    $sql = "
    SELECT u.user_guid, u.first_name, u.last_name, w.worker_id, w.health_insurance
    FROM workers w
    JOIN users u ON w.user_guid = u.user_guid
    WHERE w.health_insurance < CURDATE()
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['worker'] ?? "Worker") . " #{$row['worker_id']} - {$row['first_name']}'s " . htmlspecialchars($lang_data[$selectedLanguage]['health_insurance_expired'] ?? "health insurance expired at") . " {$row['health_insurance']}",
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=workers&action=edit&user_guid={$row['user_guid']}",
                    "level"         => "warning",
                    "image"         => "<img class='manage_shift_btn' src='img/health_insurance.png' alt=''>"
                ];
        }
    }

    // 10. Workers without a house assigned
    $sql = "
    SELECT u.user_guid, u.first_name, u.last_name, w.worker_id, w.house_guid
    FROM workers w
    JOIN users u ON w.user_guid = u.user_guid
    WHERE w.house_guid = 0
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['worker'] ?? "Worker") . " #{$row['worker_id']} - {$row['first_name']} " . htmlspecialchars($lang_data[$selectedLanguage]['no_house_assigned'] ?? "has no house associated"),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=workers&action=edit&user_guid={$row['user_guid']}",
                    "level"         => "warning",
                    "image"         => "<img class='manage_shift_btn' src='img/house.png' alt=''>"
                ];
        }
    }

    // 12. Assigned cars that don't have a driver assigned (car_guid not in drivers table)
    $sql = "
    SELECT c.car_guid, c.car_model, c.car_number_plate 
    FROM cars c
    WHERE c.driver_guid IS NULL AND c.assignment_guid > 0
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['car'] ?? "Car") . " '{$row['car_model']} ({$row['car_number_plate']})' " . htmlspecialchars($lang_data[$selectedLanguage]['no_driver_assigned'] ?? "is assigned but has no driver."),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=cars&action=assign_driver&car_guid={$row['car_guid']}",
                    "level"         => "warning",
                    "image"         => "<img class='manage_shift_btn' src='img/edit_driver.png' alt=''>"
                ];
        }
    }

    // 13. cars Assigned with drivers that have an incorrect group set
    $sql = "
    SELECT c.car_guid, c.car_model, c.car_number_plate, u.user_guid, u.first_name, u.last_name, u.group
    FROM cars c
    JOIN users u ON c.driver_guid = u.user_guid
    WHERE u.`group` = 'workers'
    ";
    $result = $MySQL->getConnection()->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] =
                [
                    "description"   => htmlspecialchars($lang_data[$selectedLanguage]['car'] ?? "Car") . " '{$row['car_model']} ({$row['car_number_plate']})' " . htmlspecialchars($lang_data[$selectedLanguage]['driver_not_driver_group'] ?? "has driver '{$row['first_name']}' who is not set as driver (Group: {$row['group']})"),
                    "action"        => "index.php?lang={$selectedLanguage}&page=admin&sub_page=users&action=edit&user_guid={$row['user_guid']}",
                    "level"         => "error",
                    "image"         => "<img class='manage_shift_btn' src='img/edit_profile.png' alt=''>"
                ];
        }
    }

    return $alerts;
}

function checkAssignmentsNeedAttention() {
    global $MySQL;
    $query = "
        SELECT DISTINCT sa.assignment_guid
        FROM shift_assignments sa
        WHERE CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date
        AND sa.assignment_guid > 0
    ";

    $guidStmt = $MySQL->getConnection()->prepare($query);
    $guidStmt->execute();
    $guidStmt->bind_result($assignment_guid);
    $result = $guidStmt->get_result();
    $attentionCount = 0;
    foreach ($result as $row) {
        $query = "
            SELECT COUNT(DISTINCT saw.user_guid)
            FROM shift_assignment_workers saw
            WHERE saw.assignment_guid = ?
        ";

        $stmt = $MySQL->getConnection()->prepare($query);
        $stmt->bind_param("i", $row['assignment_guid']);
        $stmt->execute();
        $stmt->bind_result($assigned_workers);
        $stmt->fetch();
        $stmt->close();

        $query = "
            SELECT sa.workers
            FROM shift_assignments sa
            WHERE sa.assignment_guid = ?
        ";

        $stmt = $MySQL->getConnection()->prepare($query);
        $stmt->bind_param("i", $row['assignment_guid']);
        $stmt->execute();
        $stmt->bind_result($required_workers);
        $stmt->fetch();
        $stmt->close();

        $workers_array = json_decode($required_workers, true);
        $required_workers = array_sum(array_map('intval', (is_array($workers_array) ? $workers_array : [$required_workers]) ));
        if($required_workers > $assigned_workers) {
            $attentionCount++;
        }

    }

    return $attentionCount;
}

function processWorkers($pdo, $workersFile) {
    global $selectedLanguage;
    $workersData = [];

    // Check if the file was uploaded successfully
    if (is_uploaded_file($workersFile['tmp_name'])) {
        if (($handle = fopen($workersFile['tmp_name'], 'r')) !== false) { // Use 'tmp_name' to access uploaded file
            if (($headers = fgetcsv($handle, 1000, ',')) !== false) {
                $headers = array_map('trim', $headers);
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($row) == count($headers)) {
                        $record = array_combine($headers, $row);
                        $workersData[] = $record;
                    }
                }
            }
            fclose($handle);
        } else {
            die(htmlspecialchars($lang_data[$selectedLanguage]['file_open_failed'] ?? "Unable to open the uploaded file."));
        }
    } else {
        die(htmlspecialchars($lang_data[$selectedLanguage]['file_upload_failed'] ?? "File upload failed."));
    }

    $pdo->beginTransaction();

    try {
        $houseStmt = $pdo->prepare("
            INSERT INTO houses (
                house_address, address_description, house_size_sqm, number_of_rooms, number_of_toilets, contract_number,
                contract_start, contract_end, security_deed, monthly_rent, monthly_arnona, monthly_water, monthly_electric,
                monthly_gas, monthly_vaad, landlord_name, landlord_id, landlord_phone, landlord_email, vaad_name, vaad_phone, max_tenants
            ) VALUES (
                :house_address, :address_description, :house_size_sqm, :number_of_rooms, :number_of_toilets, :contract_number,
                :contract_start, :contract_end, :security_deed, :monthly_rent, :monthly_arnona, :monthly_water, :monthly_electric,
                :monthly_gas, :monthly_vaad, :landlord_name, :landlord_id, :landlord_phone, :landlord_email, :vaad_name, :vaad_phone, :max_tenants
            ) ON DUPLICATE KEY UPDATE house_guid = LAST_INSERT_ID(house_guid)
        ");

        $userStmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, passport_id, password, email, phone_number, country, description, `group`)
            VALUES (:first_name, :last_name, :passport_id, '123456789', :email, :phone_number, :country, :description, 'workers')
            ON DUPLICATE KEY UPDATE user_guid = LAST_INSERT_ID(user_guid)
        ");

        $workerStmt = $pdo->prepare("
            INSERT INTO workers (user_guid, andromeda_guid, worker_id, profession, hourly_rate, account, foreign_phone,
                height_training, house_guid, health_insurance, on_relief, relief_end_date, description)
            VALUES (:user_guid, :andromeda_guid, :worker_id, :profession, :hourly_rate, :account, :foreign_phone,
                :height_training, :house_guid, :health_insurance, :on_relief, :relief_end_date, :description)
            ON DUPLICATE KEY UPDATE user_guid = user_guid
        ");

        $passportCheckStmt = $pdo->prepare("
            SELECT user_guid FROM users WHERE passport_id = :passport_id
        ");

        foreach ($workersData as $record) {
            $passport_id = isset($record['דרכון']) ? trim($record['דרכון']) : '';
            $account = isset($record['חשבון']) ? trim($record['חשבון']) : '';
            $phone_number = isset($record['טלפון נייד']) ? trim($record['טלפון נייד']) : '0';
            $foreign_phone = isset($record['טלפון חול']) ? trim($record['טלפון חול']) : null;
            $last_name = isset($record['שם משפחה']) ? trim($record['שם משפחה']) : '';
            $first_name = isset($record['שם פרטי']) ? trim($record['שם פרטי']) : '';
            $worker_id = isset($record["מס' עובד"]) ? trim($record["מס' עובד"]) : '';
            $landing_date = isset($record['תאריך נחיתה']) ? trim($record['תאריך נחיתה']) : '';
            $profession = isset($record['מקצוע']) ? trim($record['מקצוע']) : '';
            $andromeda_guid = isset($record["מס' עובד באנדרומדה"]) ? trim($record["מס' עובד באנדרומדה"]) : 0;
            $country = isset($record['מדינה']) ? translateCountry(trim($record['מדינה'])) : 'Israel';
            $height_training = (isset($record['ההדרכה בגובה']) && strtolower(trim($record['ההדרכה בגובה'])) === 'כן') ? 1 : 0;
            $health_insurance = (isset($record['ביטוח בריאות']) && trim($record['ביטוח בריאות']) === 'בתוקף') ? date('Y-m-d') : null;
            $apartment = isset($record['דירה']) ? trim($record['דירה']) : 'No Assigned House';
            $contractor = isset($record['קבלן']) ? trim($record['קבלן']) : 'No Description';
            $birth_date = isset($record['תאריך לידה']) ? trim($record['תאריך לידה']) : '1970-01-01';
            $status = isset($record['סטטוס']) ? trim($record['סטטוס']) : 'Unknown';

            // Handle invalid or duplicate passport_id
            if ($passport_id === '' || $passport_id === '0' || strtolower($passport_id) === 'מעבר') {
                $unique_suffix = uniqid();
                $passport_id = 'unknown_' . $unique_suffix;
            }

            // Check if passport_id already exists
            $passportCheckStmt->execute([':passport_id' => $passport_id]);
            $existingUser = $passportCheckStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                $user_guid = $existingUser['user_guid'];
            } else {
                $userStmt->execute([
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':passport_id' => $passport_id,
                    ':email' => null,
                    ':phone_number' => $phone_number,
                    ':country' => $country,
                    ':description' => "Landing Date: $landing_date; Birth Date: $birth_date; Status: $status"
                ]);
                $user_guid = $pdo->lastInsertId();
            }

            // Insert or get house_guid
            $houseStmt->execute([
                ':house_address' => $apartment,
                ':address_description' => 'No Description',
                ':house_size_sqm' => 0,
                ':number_of_rooms' => 0,
                ':number_of_toilets' => 0,
                ':contract_number' => '0',
                ':contract_start' => '1970-01-01',
                ':contract_end' => '1970-01-01',
                ':security_deed' => '0',
                ':monthly_rent' => 0,
                ':monthly_arnona' => 0,
                ':monthly_water' => 0,
                ':monthly_electric' => 0,
                ':monthly_gas' => 0,
                ':monthly_vaad' => 0,
                ':landlord_name' => '0',
                ':landlord_id' => '0',
                ':landlord_phone' => '0',
                ':landlord_email' => '0',
                ':vaad_name' => '0',
                ':vaad_phone' => '0',
                ':max_tenants' => 0
            ]);
            $house_guid = $pdo->lastInsertId();

            // Insert into workers
            $workerStmt->execute([
                ':user_guid' => $user_guid,
                ':andromeda_guid' => $andromeda_guid,
                ':worker_id' => $worker_id,
                ':profession' => $profession,
                ':hourly_rate' => 0.00,
                ':account' => is_numeric($account) ? (int)$account : 0,
                ':foreign_phone' => $foreign_phone,
                ':height_training' => $height_training,
                ':house_guid' => $house_guid,
                ':health_insurance' => $health_insurance,
                ':on_relief' => 0,
                ':relief_end_date' => date('Y-m-d'),
                ':description' => $contractor
            ]);
        }

        $pdo->commit();
        echo "<p class='success'>Workers data inserted successfully.</p>";
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Failed to insert workers data: " . $e->getMessage());
    }
}
?>

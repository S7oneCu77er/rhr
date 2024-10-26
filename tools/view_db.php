<?php

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters
$host = 'localhost';
$dbname = 'rhr'; // Update your database name
$username = 'root';
$password = ''; // Update with your actual password

try {
    // Connect to the database using PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set PDO error mode to exception for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<h2>Database Connection Failed</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

/**
 * Fetches all tables and their data from the database.
 *
 * @param PDO $pdo The PDO instance for database connection.
 * @return array An associative array where keys are table names and values are arrays of table rows.
 */
function fetchTablesAndContent($pdo) {
    // Get all table names
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $allTablesData = [];

    foreach ($tables as $table) {
        // Fetch table content using prepared statements to prevent SQL injection
        // Note: Table names cannot be parameterized, so ensure $table is from a trusted source
        $stmt = $pdo->prepare("SELECT * FROM `$table`");
        $stmt->execute();
        $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add table name and its data to the result
        $allTablesData[$table] = $tableData;
    }

    return $allTablesData;
}

/**
 * Fetches column types for all tables in the database.
 *
 * @param PDO $pdo The PDO instance for database connection.
 * @return array An associative array where keys are table names and values are arrays of columnName => columnType.
 */
function fetchColumnTypes($pdo) {
    // Get all table names
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $columnTypes = [];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("DESCRIBE `$table`");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            $columnName = $column['Field'];
            $columnType = $column['Type']; // e.g., 'int(11)', 'varchar(255)', 'datetime', etc.
            $columnTypes[$table][$columnName] = $columnType;
        }
    }

    return $columnTypes;
}

/**
 * Counts the number of entries in each table.
 *
 * @param array $tablesData The associative array containing table data.
 * @return array An associative array where keys are table names and values are entry counts.
 */
function countTableEntries($tablesData) {
    $counts = [];
    foreach ($tablesData as $tableName => $tableRows) {
        $counts[$tableName] = count($tableRows);
    }
    return $counts;
}

// ======= Step 1: Define Primary Keys =======

// Define the primary key(s) for each table
$primaryKeys = [
    'users' => ['user_guid'],
    'sites' => ['site_guid'],
    'cars' => ['car_guid'],
    'houses' => ['house_guid'],
    'site_managers' => ['user_guid', 'site_guid'],
    'documents' => ['doc_guid'],
    'shifts' => ['shift_guid'],
    'workers' => ['user_guid'],
    'worker_languages' => ['user_guid', 'language'],
    'support' => ['support_guid'],
    'shift_assignments' => ['assignment_guid'],
    'shift_assignment_workers' => ['assignment_guid', 'user_guid'],
    // Add more tables and their primary keys as needed
];

// ======= Step 2: Define Foreign Key Mappings =======

// Define which columns in which tables are foreign keys and their corresponding display fields
$foreignKeyMappings = [
    'cars' => [
        'driver_guid' => ['table' => 'users', 'field' => 'first_name'],
        'assignment_guid' => ['table' => 'shift_assignments', 'field' => 'description']
    ],
    'site_managers' => [
        'user_guid' => ['table' => 'users', 'field' => 'first_name'],
        'site_guid' => ['table' => 'sites', 'field' => 'site_name']
    ],
    'documents' => [
        'uploaded_by' => ['table' => 'users', 'field' => 'first_name'],
        'uploaded_for' => ['table' => 'users', 'field' => 'first_name']
    ],
    'shifts' => [
        'user_guid' => ['table' => 'users', 'field' => 'first_name'],
        'site_guid' => ['table' => 'sites', 'field' => 'site_name']
    ],
    'workers' => [
        'user_guid' => ['table' => 'users', 'field' => 'first_name'],
        'house_guid' => ['table' => 'houses', 'field' => 'house_address']
    ],
    'shift_assignments' => [
        'site_guid' => ['table' => 'sites', 'field' => 'site_name']
    ],
    'shift_assignment_workers' => [
        'assignment_guid' => ['table' => 'shift_assignments', 'field' => 'description'],
        'user_guid' => ['table' => 'users', 'field' => 'first_name']
    ],
    'support' => [
        'assignment_guid' => ['table' => 'shift_assignments', 'field' => 'description'],
        'user_guid' => ['table' => 'users', 'field' => 'first_name'],
        'site_guid' => ['table' => 'sites', 'field' => 'site_name'],
        'car_guid' => ['table' => 'cars', 'field' => 'car_model'],
        'shift_guid' => ['table' => 'shifts', 'field' => 'shift_start'] // You can choose 'shift_end' or another field if preferred
    ],
    'sites' => [
        'site_owner_guid' => ['table' => 'users', 'field' => 'first_name']
    ],
    'worker_languages' => [
        'user_guid' => ['table' => 'users', 'field' => 'first_name']
    ],
    // Add more mappings as needed
];

// ======= Step 3: Prepare Lookup Arrays =======

// Initialize an empty array to hold lookup data
$lookup = [];

// Fetch all tables and their content
$tablesData = fetchTablesAndContent($pdo);

// Fetch column types for all tables
$columnTypes = fetchColumnTypes($pdo);

// Identify all related tables and the fields to be fetched
$relatedTables = [];
foreach ($foreignKeyMappings as $table => $columns) {
    foreach ($columns as $column => $mapping) {
        $relatedTables[$mapping['table']] = $mapping['field'];
    }
}

// Remove duplicate entries
$relatedTables = array_unique($relatedTables);

// Populate the lookup arrays
foreach ($relatedTables as $relatedTable => $relatedField) {
    if (isset($tablesData[$relatedTable])) {
        foreach ($tablesData[$relatedTable] as $row) {
            // Determine the primary key for the related table
            // Assumption: Primary key follows the pattern table_guid (e.g., users.user_guid)
            $primaryKey = '';
            switch ($relatedTable) {
                case 'users':
                    $primaryKey = 'user_guid';
                    break;
                case 'sites':
                    $primaryKey = 'site_guid';
                    break;
                case 'shift_assignments':
                    $primaryKey = 'assignment_guid';
                    break;
                case 'houses':
                    $primaryKey = 'house_guid';
                    break;
                case 'cars':
                    $primaryKey = 'car_guid';
                    break;
                case 'shifts':
                    $primaryKey = 'shift_guid';
                    break;
                // Add more cases as needed
                default:
                    // If the primary key doesn't follow the pattern, skip or handle accordingly
                    continue 2; // Skip to next table
            }

            if (isset($row[$primaryKey])) {
                // Store both the descriptive field and the GUID
                $lookup[$relatedTable][$row[$primaryKey]] = [
                    'display' => $row[$relatedField],
                    'guid' => $row[$primaryKey]
                ];
            }
        }
    }
}

// ======= Step 4: Function to Get Display Value =======

/**
 * Returns the display value for a given cell.
 *
 * If the column is a foreign key, it returns "descriptive_field (GUID)".
 * If the value is NULL, it returns "N/A" or the original value based on context.
 *
 * @param string $currentTable The name of the current table being displayed.
 * @param string $currentColumn The name of the current column being displayed.
 * @param mixed $value The original value from the database (typically a GUID).
 * @param array $lookup The lookup arrays containing related table data.
 * @param array $foreignKeyMappings The mappings defining which columns are foreign keys.
 * @return string The display value (either the related field with GUID or the original value).
 */
function getDisplayValue($currentTable, $currentColumn, $value, $lookup, $foreignKeyMappings) {
    // Check if the current column is a foreign key
    if (isset($foreignKeyMappings[$currentTable]) && isset($foreignKeyMappings[$currentTable][$currentColumn])) {
        $relatedTable = $foreignKeyMappings[$currentTable][$currentColumn]['table'];
        // Retrieve the display field and GUID from the lookup array
        if (isset($lookup[$relatedTable][$value])) {
            $displayText = $lookup[$relatedTable][$value]['display'];
            $guid = $lookup[$relatedTable][$value]['guid'];
            return htmlspecialchars($displayText) . " (" . htmlspecialchars($guid) . ")";
        } else {
            // If the GUID doesn't exist in the lookup, display "Unknown (GUID)" or just "GUID"
            return "Unknown (" . htmlspecialchars($value) . ")";
        }
    }
    // If not a foreign key, handle NULL values and return the original value
    return isset($value) ? htmlspecialchars($value) : 'N/A';
}

// ======= Step 5: Handle AJAX Requests for Delete and Update =======

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid request.'];

    // Retrieve and sanitize the action
    $action = $_POST['action'];

    // Retrieve and sanitize the table name
    $table = isset($_POST['table']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table']) : '';

    // Fetch updated tablesData
    $tablesData = fetchTablesAndContent($pdo);

    if (!array_key_exists($table, $tablesData)) {
        $response['message'] = 'Invalid table.';
        echo json_encode($response);
        exit;
    }

    // Retrieve primary key(s) for the table
    if (!isset($primaryKeys[$table])) {
        $response['message'] = 'Primary key not defined for the table.';
        echo json_encode($response);
        exit;
    }

    $pkColumns = $primaryKeys[$table];
    $pkValues = [];
    foreach ($pkColumns as $pk) {
        if (!isset($_POST[$pk])) {
            $response['message'] = 'Primary key value missing.';
            echo json_encode($response);
            exit;
        }
        $pkValues[$pk] = $_POST[$pk];
    }

    if ($action === 'delete') {
        // Build the WHERE clause
        $whereClause = '';
        $params = [];
        foreach ($pkValues as $pk => $val) {
            $whereClause .= "`$pk` = :$pk AND ";
            $params[":$pk"] = $val;
        }
        $whereClause = rtrim($whereClause, ' AND ');

        // Prepare and execute the DELETE statement
        try {
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE $whereClause");
            $stmt->execute($params);
            $response['success'] = true;
            $response['message'] = 'Entry deleted successfully.';
        } catch (PDOException $e) {
            $response['message'] = 'Delete failed: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit;
    } elseif ($action === 'update') {
        // Retrieve the new values
        if (!isset($_POST['new_values'])) {
            $response['message'] = 'No new values provided.';
            echo json_encode($response);
            exit;
        }

        // Decode JSON new_values
        $newValuesJson = $_POST['new_values'];
        $newValues = json_decode($newValuesJson, true);
        if (!is_array($newValues)) {
            $response['message'] = 'Invalid new values format.';
            echo json_encode($response);
            exit;
        }

        // Remove primary key columns from newValues to prevent updating them
        foreach ($pkColumns as $pk) {
            unset($newValues[$pk]);
        }

        // Build the SET clause
        $setClause = '';
        $params = [];
        foreach ($newValues as $column => $value) {
            $setClause .= "`$column` = :$column, ";
            $params[":$column"] = $value;
        }
        $setClause = rtrim($setClause, ', ');

        // Build the WHERE clause
        $whereClause = '';
        foreach ($pkValues as $pk => $val) {
            $whereClause .= "`$pk` = :pk_$pk AND ";
            $params[":pk_$pk"] = $val;
        }
        $whereClause = rtrim($whereClause, ' AND ');

        // Prepare and execute the UPDATE statement
        try {
            $stmt = $pdo->prepare("UPDATE `$table` SET $setClause WHERE $whereClause");
            $stmt->execute($params);
            $response['success'] = true;
            $response['message'] = 'Entry updated successfully.';
        } catch (PDOException $e) {
            $response['message'] = 'Update failed: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit;
    }

    // If action is not recognized
    echo json_encode($response);
    exit;
}

// ======= Step 6: Fetch the tables and their content =======

// Fetch the tables and their content after handling any potential updates or deletes
$tablesData = fetchTablesAndContent($pdo);

// Get counts for each table
$tableCounts = countTableEntries($tablesData);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Tables Overview</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Base Styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            font-size: 0.75rem;
            text-align: center;
        }

        .container {
            width: fit-content;
            max-width: 100%;
            margin: 25px auto;
            padding: 10px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: center;
        }

        h1 {
            color: #333;
            font-size: 2rem;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            text-align: left;
        }

        input {
            text-align: center;
        }

        /* Summary Section Styles */
        .summary-container {
            margin-bottom: 40px;
        }

        .summary-title {
            font-size: 1.5rem;
            color: #ff9800;
            margin-bottom: 20px;
            text-align: center;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }

        .summary-table th, .summary-table td {
            padding: 10px;
            border: 1px solid #ccc;
        }

        .summary-table th {
            background-color: #ff9800;
            color: white;
            font-size: 1.1rem;
        }

        .summary-table td {
            background-color: #fff;
            color: #333;
            font-size: 0.9rem;
        }

        /* Highlighting for better readability */
        .summary-table tr:nth-child(odd) td {
            background-color: #f9f9f9;
        }

        /* Hover Effect for Summary Table Links */
        .summary-table a {
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .summary-table a:hover {
            text-decoration: underline;
        }

        /* Tables Section Styles */
        .table-container {
            margin-bottom: 40px;
        }

        .table-title {
            font-size: 1.6rem;
            color: #4caf50;
            margin-bottom: 8px;
            text-align: center;
        }

        table, th, td {
            border: 1px solid #ccc;
            text-align: center;
        }

        th {
            padding: 8px;
        }

        td {
            padding: 3px;
        }

        th {
            background-color: #5c9ae1;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        /* Back Button Styles */
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

        /* Footer Styles */
        footer {
            text-align: center;
            margin-top: 50px;
            color: #888;
            font-size: 0.8rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .summary-table, .summary-table th, .summary-table td, table, th, td {
                font-size: 0.6rem;
            }

            .back-btn {
                padding: 8px 16px;
                font-size: 0.8rem;
            }
        }

        /* Optional: Smooth Scroll Behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Inline styles for Edit and Delete buttons to match existing styles */
        .action-btn {
            padding: 5px 0px;
            width: 25px;
            margin: 0 1px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .edit-btn {
            background-color: #4caf50;
            color: white;
        }

        .delete-btn {
            background-color: #f44336;
            color: white;
        }

        .confirm-btn {
            background-color: #2196F3;
            color: white;
        }

        .cancel-btn {
            background-color: #9E9E9E;
            color: white;
        }

        /* Input field styles */
        .edit-input {
            width: 90%;
            padding: 2px;
            box-sizing: border-box;
        }
    </style>
    <!-- Optional: Include Font Awesome for icons in summary cards -->
    <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
            integrity="sha512-p4N5rFWjaeDzJ9OG2mKDnTJDkPNyFsARQ8Bi+XOlBhB1YK+GgpVFEfQmIuoyrQKnsNkfYHzAfiS3Gk6mKMdEOg=="
            crossorigin="anonymous"
            referrerpolicy="no-referrer"
    />
    <!-- Embed Column Types as JavaScript Variable -->
    <script>
        const columnTypes = <?php echo json_encode($columnTypes); ?>;
    </script>
</head>
<body>

<div class="container">
    <h1>Database Tables Overview</h1>

    <!-- Summary Section -->
    <div class="summary-container">
        <h2 class="summary-title">Tables Overview</h2>
        <?php if (!empty($tableCounts)): ?>
            <table class="summary-table">
                <thead>
                <tr>
                    <?php foreach ($tableCounts as $tableName => $count): ?>
                        <th>
                            <!-- Clickable Table Name Linking to Detailed Section -->
                            <a href="#<?php echo htmlspecialchars($tableName); ?>">
                                <?php echo htmlspecialchars(ucfirst($tableName)); ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <?php foreach ($tableCounts as $tableName => $count): ?>
                        <td><?php echo htmlspecialchars($count); ?></td>
                    <?php endforeach; ?>
                </tr>
                </tbody>
            </table>
        <?php else: ?>
            <p>No tables found in the database.</p>
        <?php endif; ?>
    </div>
    <!-- End of Summary Section -->

    <?php if (!empty($tablesData)): ?>
        <?php foreach ($tablesData as $tableName => $tableRows): ?>
            <?php
            // Determine the primary key columns for the current table
            if (!isset($primaryKeys[$tableName])) {
                // Skip tables without defined primary keys
                continue;
            }
            $pkColumns = $primaryKeys[$tableName];
            ?>
            <div class="table-container" id="<?php echo htmlspecialchars($tableName); ?>">
                <h2 class="table-title"><?php echo ucfirst($tableName); ?></h2>
                <?php if (!empty($tableRows)): ?>
                    <table>
                        <thead>
                        <tr>
                            <?php foreach (array_keys($tableRows[0]) as $columnName): ?>
                                <th><?php echo htmlspecialchars($columnName); ?></th>
                            <?php endforeach; ?>
                            <th style='white-space: nowrap; width: 1%; max-width: max-content;'>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tableRows as $row): ?>
                            <tr>
                                <?php foreach ($row as $columnName => $cell): ?>
                                    <?php
                                    // Determine if this column is a foreign key
                                    $isForeignKey = isset($foreignKeyMappings[$tableName][$columnName]);
                                    $guid = '';
                                    if ($isForeignKey) {
                                        $relatedTable = $foreignKeyMappings[$tableName][$columnName]['table'];
                                        if (isset($lookup[$relatedTable][$cell]['guid'])) {
                                            $guid = $lookup[$relatedTable][$cell]['guid'];
                                        }
                                    }
                                    ?>
                                    <td <?php echo $isForeignKey && $guid ? 'data-guid="' . htmlspecialchars($guid) . '"' : ''; ?>>
                                        <?php
                                        // Use the getDisplayValue function to determine what to display
                                        echo getDisplayValue($tableName, $columnName, $cell, $lookup, $foreignKeyMappings);
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td style="white-space: nowrap;">
                                    <?php
                                    // Prepare primary key data as JSON
                                    $pkData = [];
                                    foreach ($pkColumns as $pk) {
                                        $pkData[$pk] = $row[$pk];
                                    }
                                    $dataPkJson = htmlspecialchars(json_encode($pkData));
                                    ?>
                                    <button class="action-btn edit-btn" data-table="<?php echo htmlspecialchars($tableName); ?>" data-pk='<?php echo $dataPkJson; ?>' data-action="edit">E</button>
                                    <button class="action-btn delete-btn" data-table="<?php echo htmlspecialchars($tableName); ?>" data-pk='<?php echo $dataPkJson; ?>' data-action="delete">X</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No data available in this table.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No tables found in the database.</p>
    <?php endif; ?>

    <a href="index.php" class="back-btn">Back to Tools</a>
</div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>

<!-- JavaScript for Edit and Delete Functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('.container');

        container.addEventListener('click', function (e) {
            const target = e.target;

            if (target.tagName.toLowerCase() === 'button') {
                const action = target.getAttribute('data-action');
                const table = target.getAttribute('data-table');
                let pk;
                try {
                    pk = JSON.parse(target.getAttribute('data-pk'));
                } catch (err) {
                    console.error('Invalid primary key JSON:', err);
                    alert('An error occurred. Please try again.');
                    return;
                }

                if (action === 'delete') {
                    // Handle Delete
                    const confirmation = confirm('Are you sure you want to delete this entry?');
                    if (confirmation) {
                        // Send AJAX request to delete the entry
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'delete',
                                table: table,
                                ...pk
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.message);
                                    // Remove the row from the table
                                    const row = target.closest('tr');
                                    row.parentNode.removeChild(row);
                                } else {
                                    alert(data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while deleting the entry.');
                            });
                    }
                } else if (action === 'edit') {
                    // Handle Edit
                    const row = target.closest('tr');
                    row.classList.add('editing');

                    // Change Edit button to Confirm (V)
                    target.textContent = 'V';
                    target.setAttribute('data-action', 'confirm');
                    target.classList.remove('edit-btn');
                    target.classList.add('confirm-btn');

                    // Change Delete button to Cancel (C)
                    const deleteBtn = row.querySelector('.delete-btn');
                    deleteBtn.textContent = 'C';
                    deleteBtn.setAttribute('data-action', 'cancel');
                    deleteBtn.classList.remove('delete-btn');
                    deleteBtn.classList.add('cancel-btn');

                    // Replace cells with input fields except for Actions column
                    const cells = row.querySelectorAll('td');
                    // Assuming last cell is Actions
                    cells.forEach((cell, index) => {
                        if (index < cells.length - 1) {
                            const isForeignKey = cell.hasAttribute('data-guid');
                            const originalText = cell.textContent.trim();
                            let inputValue = '';

                            if (isForeignKey) {
                                // For foreign keys, use the GUID from data-guid attribute
                                inputValue = cell.getAttribute('data-guid') || '';
                            } else {
                                // For regular fields, use the original text
                                inputValue = originalText !== 'Unknown' ? originalText : '';
                            }

                            // Create input field
                            const input = document.createElement('input');
                            input.value = inputValue;
                            input.classList.add('edit-input');

                            // Determine the input type based on column type
                            const columnName = row.parentNode.parentNode.querySelector('thead tr').children[index].textContent.trim().toLowerCase();
                            const columnType = columnTypes[table][columnName] || '';

                            if (columnType.includes('datetime') || columnType.includes('timestamp')) {
                                // Use text input for datetime fields to preserve both date and time
                                input.type = 'text';
                                // Optionally, you can use 'datetime-local' for better UI
                                // input.type = 'datetime-local';
                            } else if (columnType.includes('date')) {
                                // Use date input for date fields
                                input.type = 'date';
                                // Attempt to parse date if necessary
                                if (input.value && input.type === 'date') {
                                    const date = new Date(input.value);
                                    if (!isNaN(date)) {
                                        // Format to YYYY-MM-DD
                                        input.value = date.toISOString().split('T')[0];
                                    }
                                }
                            } else if (columnType.includes('time')) {
                                // Use time input for time fields
                                input.type = 'time';
                                // Attempt to parse time if necessary
                                if (input.value && input.type === 'time') {
                                    const time = input.value.split(':').slice(0, 2).join(':');
                                    input.value = time;
                                }
                            } else if (columnName.includes('email')) {
                                input.type = 'email';
                            } else if (columnName.includes('phone')) {
                                input.type = 'tel';
                            } else if (columnName.includes('rate') || columnName.includes('price') || columnName.includes('number')) {
                                input.type = 'number';
                            } else {
                                input.type = 'text';
                            }

                            cell.innerHTML = '';
                            cell.appendChild(input);
                        }
                    });
                } else if (action === 'confirm') {
                    // Handle Confirm Update
                    const row = target.closest('tr');
                    const editInputs = row.querySelectorAll('.edit-input');
                    const newValues = {};
                    const headers = Array.from(row.parentNode.parentNode.querySelectorAll('thead th'));

                    headers.forEach((header, index) => {
                        if (index < headers.length - 1) { // Exclude Actions column
                            const columnName = header.textContent.trim();
                            const input = editInputs[index];
                            if (input) {
                                newValues[columnName] = input.value.trim();
                            }
                        }
                    });

                    // Send AJAX request to update the entry
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'update',
                            table: table,
                            ...pk,
                            new_values: JSON.stringify(newValues)
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                // Reload the page to reflect changes
                                location.reload();
                            } else {
                                alert(data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while updating the entry.');
                        });
                } else if (action === 'cancel') {
                    // Handle Cancel Edit
                    const row = target.closest('tr');
                    row.classList.remove('editing');

                    // Change Confirm button back to Edit
                    const confirmBtn = row.querySelector('.confirm-btn');
                    if (confirmBtn) {
                        confirmBtn.textContent = 'E';
                        confirmBtn.setAttribute('data-action', 'edit');
                        confirmBtn.classList.remove('confirm-btn');
                        confirmBtn.classList.add('edit-btn');
                    }

                    // Change Cancel button back to Delete (X)
                    target.textContent = 'X';
                    target.setAttribute('data-action', 'delete');
                    target.classList.remove('cancel-btn');
                    target.classList.add('delete-btn');

                    // Reload the row to its original state
                    location.reload();
                }
            }
        });
    });
</script>

</body>
</html>

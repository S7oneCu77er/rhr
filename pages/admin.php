<?php
// pages/admin.php

global $lang_data, $selectedLanguage, $MySQL;

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('Access denied.', true);</script>";
    exit();
}
$assignments_attention_count = checkAssignmentsNeedAttention();
if (!isset($_GET['sub_page'])) {
    $link = "index.php?lang=" . urlencode($selectedLanguage) . "&page=admin&sub_page";
    echo '
    <div class="page">
        <h2 style="margin-top: 0; margin-bottom: 5px;">' . htmlspecialchars($lang_data[$selectedLanguage]["admin_tools"]) . '</h2>
        <div class="admin_page">
            <table>
                <tbody>
                    <tr>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["cars"]) . '<a style="margin-top: 5px;" href="' . $link . '=cars"><img id="admin_menu_img" src="img/cars.png" alt=""></a></div></td>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["users"]) . '<a style="margin-top: 5px;" href="' . $link . '=users"><img id="admin_menu_img" src="img/users.png" alt=""></a></div></td>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["houses"]) . '<a style="margin-top: 5px;" href="' . $link . '=houses"><img id="admin_menu_img" src="img/houses.png" alt=""></a>
                        </div></td>
                    </tr>
                    <tr>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["sites"]) . '<a style="margin-top: 5px;" href="' . $link . '=sites"><img id="admin_menu_img" src="img/sites.png" alt=""></a></div></td>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["workers"]) . '<a style="margin-top: 5px;" href="' . $link . '=workers"><img id="admin_menu_img" src="img/workers.png" alt=""></a></div></td>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["complaints"]) . '<a style="margin-top: 5px;" href="' . $link . '=complaints"><img id="admin_menu_img" src="img/complaints.png" alt=""></a></div></td>
                    </tr>
                    <tr>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["documents"]) . '<a style="margin-top: 5px;" href="' . $link . '=documents"><img id="admin_menu_img" src="img/documents.png" alt=""></a></div></td>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["shifts"]) . '<a style="margin-top: 5px;" href="' . $link . '=shifts"><img id="admin_menu_img" src="img/shifts.png" alt=""></a></div></td>
                        <td>
                            <div>
                                ' . htmlspecialchars($lang_data[$selectedLanguage]["assignments"] ?? "Assignments") . '
                                <a style="margin-top: 5px;" href="' . $link . '=assignments"><img id="admin_menu_img" src="img/assignments.png" alt=""></a>
                                <div id="notification-badge_admin">
                                    <span id="notification-number">'.$assignments_attention_count.'</span> <!-- Number of notifications -->
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>';
} else {
    $subPage = $_GET['sub_page'];
    $action = $_GET['action'] ?? null;

    $subPageFile = "";

    // Define a whitelist of allowed sub_pages to prevent unauthorized access
    $allowedSubPages = ['documents', 'shifts', 'users', 'workers', 'houses', 'sites','assignments', 'cars']; // Add other sub_pages here as needed
    $allowedSubActions = [
        'users' => [
            'assign', 'add', 'edit', 'delete'
        ],
        'workers' => [
            'add', 'edit', 'delete','assign'
        ],
        'shifts' => [
            'add', 'edit', 'delete', 'approve'
        ],
        'houses' => [
            'add', 'edit', 'delete', 'view_tenants'
        ],
        'sites' => [
            'add_assignment', 'assign_manager', 'unassign_manager', 'add', 'edit', 'delete'
        ],
        'assignments' => [
            'add', 'assign', 'unassign', 'assign_car', 'unassign_car', 'edit', 'delete'
        ],
        'cars' => [
            'add', 'assign_driver', 'unassign_driver', 'edit', 'delete'
        ]
    ];

    if (in_array($subPage, $allowedSubPages)) {
        if(!isset($action)) {
            // Construct the path to the sub_page file
            $subPageFile = __DIR__ . '/admin/' . basename($subPage) . '.php';
        } else {
            if(isset($allowedSubActions[$subPage]) && in_array($action, $allowedSubActions[$subPage])) {
                $subPageFile = __DIR__ . '/admin/' . $subPage . '/' . $action . '.php';
            }
        }

        // Check if the file exists before including
        if (file_exists($subPageFile)) {
            include $subPageFile;
        } else {
            echo "<div class='page'>Sub-page action not found.</div>";
        }
    } else {
        echo "<div class='page'>Invalid sub-page requested.</div>";
    }
}
?>
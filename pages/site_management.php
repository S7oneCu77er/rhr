<?php
// pages/site_management.php

global $lang_data, $selectedLanguage, $MySQL;

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins' && $_SESSION['loggedIn']['group'] !== 'site_managers') {
    echo "<script>showError('Access denied.', true);</script>";
    return;
}

if (!isset($_GET['sub_page'])) {
    $link = "index.php?lang=" . urlencode($selectedLanguage) . "&page=site_management&sub_page";
    echo '
    <div class="page">
        <h2>Site Managment</h2>
        <div class="admin_page">
            <table>
                <tbody>
                    <tr>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["assignments"] ?? "Assignments") . '<a href="' . $link . '=assignments"><img src="img/assignments.png" alt=""></a></div></td>
                        <td><div>' . htmlspecialchars($lang_data[$selectedLanguage]["sites"] ?? "Sites") . '<a href="' . $link . '=sites"><img src="img/sites.png" alt=""></a></div></td>
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
    $allowedSubPages = ['assignments', 'sites', 'shifts']; // Add other sub_pages here as needed
    $allowedSubActions = [
        'assignments' => [
            'add', 'edit', 'delete'
        ],
        'sites' => [
            'edit', 'add_assignment'
        ],
        'shifts' => [
            'add', 'edit', 'delete', 'approve'
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
            echo "<div class='page'>Sub-page action not found.</div>" . $action;
        }
    } else {
        echo "<div class='page'>Invalid sub-page requested.</div>";
    }
}
?>
<?php

// Recursive function to build the file tree
function buildFileTree($dir, $prefix = '') {
    $items = scandir($dir);
    $tree = '<ul>';

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.idea' || $item === 'uploads') {
            continue;
        }

        $filePath = realpath($dir . '/' . $item);
        $relativePath = '.' . str_replace(realpath(__DIR__ . '/../'), '', $filePath);

        // Display item (either file or directory)
        if (is_dir($filePath)) {
            $tree .= "<li><strong>$prefix$item/</strong>" . buildFileTree($filePath, $prefix . '&nbsp;&nbsp;&nbsp;') . "</li>";
        } else {
            $tree .= "<li>$prefix<a href='?file=" . urlencode($relativePath) . "' class='file-link'>$item</a></li>";
        }
    }

    $tree .= '</ul>';
    return $tree;
}

// Directory to scan
$directory = realpath(__DIR__ . '/../');

// Build the file tree
$fileTree = buildFileTree($directory);

// Check if a file is requested to view
$fileContent = '';
if (isset($_GET['file'])) {
    $requestedFile = realpath(__DIR__ . '/../' . $_GET['file']);
    // Make sure the requested file is inside the base directory and is a file
    if (strpos($requestedFile, realpath(__DIR__ . '/../')) === 0 && is_file($requestedFile)) {
        // Get file contents
        $fileContent = htmlspecialchars(file_get_contents($requestedFile)); // Prevent HTML from rendering
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View File Tree</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            min-width: 30%;
            width: fit-content;
            max-width: 100%;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: left;
        }

        h1 {
            color: #333;
            font-size: 2rem;
        }

        ul {
            list-style-type: none;
            padding-left: 20px;
        }

        li {
            margin: 5px 0;
            font-size: 1.1rem;
            color: #333;
        }

        li a {
            color: #5c9ae1;
            text-decoration: none;
        }

        li a:hover {
            text-decoration: underline;
        }

        li strong {
            color: #4caf50;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            color: white;
            background-color: #5c9ae1;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .center {
            text-align: center;
        }

        .btn:hover {
            background-color: #428bca;
        }

        a.back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #5c9ae1;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        a.back-btn:hover {
            background-color: #428bca;
        }

        footer {
            text-align: center;
            margin-top: 50px;
            color: #888;
        }

        pre {
            background-color: #f4f4f4;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: Consolas, monospace;
            margin-top: 20px;
        }

        .file-content {
            margin-top: 30px;
            font-size: 0.75rem;
            max-width: 100%;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>File Tree View</h1>
    <?php
    if(!$fileContent)
    {
        echo $fileTree;
    }
    else
    {
        echo '
        <div class="file-content">
            <h2>File Content: ' . htmlspecialchars(basename($requestedFile)) . '</h2>
            <pre>' . $fileContent . '</pre>
        </div>
        ';
    }
    ?>

    <div class="center">
        <a href="index.php" class="back-btn">Back to File-Tree</a>
    </div></div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>

</body>
</html>

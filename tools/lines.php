<?php
// Directory path where files are stored
$directoryPath = '../';

// Initialize line counter
$totalLines = 0;

// Supported file extensions
$supportedExtensions = ['php', 'js', 'css'];

// Function to count lines in a file
function countLinesInFile($filePath) {
    $lineCount = 0;
    $file = fopen($filePath, "r");
    while (!feof($file)) {
        $line = fgets($file);
        $lineCount++;
    }
    fclose($file);
    return $lineCount;
}

// Function to recursively scan directories and count lines in supported files
function scanDirectory($dir) {
    global $totalLines, $supportedExtensions;

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                // Recursively scan subdirectories
                scanDirectory($filePath);
            } else {
                // Get file extension
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);

                // Check if the file extension is supported
                if (in_array($extension, $supportedExtensions)) {
                    $linesInFile = countLinesInFile($filePath);
                    $totalLines += $linesInFile;
                }
            }
        }
    }
}

// Start scanning the directory
scanDirectory($directoryPath);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Line Counter</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: center;
        }

        h1 {
            text-align: center;
            color: #333;
            font-size: 2rem;
        }

        .result {
            margin-top: 30px;
            padding: 15px;
            background-color: #eef7f9;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            text-align: center;
            font-size: 1.5rem;
            color: #5c9ae1;
        }

        a.back-link {
            display: inline-block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #5c9ae1;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        a.back-link:hover {
            background-color: #428bca;
        }

        footer {
            text-align: center;
            margin-top: 50px;
            color: #888;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Code Line Counter</h1>
    <div class="result">
        <?php echo "Total number of lines in PHP, JS, and CSS files: " . $totalLines; ?>
    </div>

    <a href="index.php" class="back-link">Back to Tools</a>
</div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>

</body>
</html>


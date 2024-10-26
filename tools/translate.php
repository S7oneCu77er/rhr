<?php
// Include the language handler to access the lang_data array
require_once '../inc/language_handler.php';

// Recursive function to scan directories for PHP files
function scanDirectory($dir, &$phpFiles = [], $selectedLanguage) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $dir . '/' . $file;

        if (is_dir($filePath)) {
            scanDirectory($filePath, $phpFiles, $selectedLanguage);
        } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
            $phpFiles[] = $filePath;
        }
    }
}

// Function to scan PHP files for missing language keys
function scanFilesForMissingLanguageKeys($phpFiles, &$missingKeys = [], $selectedLanguage) {
    global $lang_data;
    $TargetLanguage = $selectedLanguage;

    // Define patterns for different ways the language keys could be accessed
    $patterns = [
        "/\\\$lang_data\\[['\"]English['\"]\\]\\[['\"](.*?)['\"]\\]/", // Match $lang_data['English']['key']
        "/\\\$lang_data\\[\\\$selectedLanguage\\]\\[['\"](.*?)['\"]\\]/",  // Match $lang_data[$selectedLanguage]['key']
        "/htmlspecialchars\\(\\\$lang_data\\[\\\$selectedLanguage\\]\\[['\"](.*?)['\"](.*?)\\)/", // Match htmlspecialchars wrapping
        "/\\\$lang_data\\[['\"]([a-zA-Z]+)['\"]\\]\\[['\"](.*?)['\"](.*?)\\]/" // With null coalescing
    ];

    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);

        if ($content === false) {
            continue;
        }

        $seenKeys = []; // Track seen keys for the current file to avoid duplicates

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $key) {
                    if (!isset($lang_data[$TargetLanguage][$key]) && !in_array($key, $seenKeys)) {
                        // Only add missing key if not already seen for the current file
                        $missingKeys[$file][] = $key;
                        $seenKeys[] = $key; // Mark key as seen
                    }
                }
            }
        }
    }
}

// Directory to scan
$directory = realpath(__DIR__ . '/../') . '/';


// Initialize the list of PHP files and missing keys
$phpFiles = [];
$missingKeys = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Missing Translation Keys</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: center;
        }

        h1 {
            color: #333;
            font-size: 2rem;
        }

        h2 {
            color: #5c9ae1;
            font-size: 1.8rem;
            margin-top: 20px;
        }

        h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 10px;
            text-align: left;
        }

        ul {
            list-style-type: none;
            padding-left: 0;
        }

        li {
            text-align: left;
        }

        .missing-key {
            font-size: 1rem;
            color: #ff4c4c;
            font-weight: bold;
            margin-left: 35px;
        }

        .success {
            font-size: 1.2rem;
            color: #4caf50;
            font-weight: bold;
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

        .btn:hover {
            background-color: #428bca;
        }

        /* Updated select styling */
        select {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            color: #333;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s ease, background-color 0.3s ease;
        }

        select:hover {
            border-color: #5c9ae1;
            background-color: #fff;
        }

        select:focus {
            outline: none;
            border-color: #428bca;
            background-color: #fff;
        }

        option {
            color: #333;
            background-color: white;
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
    </style>
</head>
<body>
<?php
// If the form is not submitted
if (!isset($_POST['language'])) {
    ?>
    <div class="container">
        <h1>Check Missing Translation Keys</h1>
        <form method="post">
            <label for="language">Select a Language:</label>
            <select name="language" id="language">
                <option value="English">English</option>
                <option value="Hebrew">Hebrew</option>
                <option value="Russian">Russian</option>
                <option value="Hindi">Hindi</option>
                <option value="Sinhala">Sinhala</option>
                <option value="Arabic">Arabic</option>
            </select>
            <br>
            <button type="submit" class="btn">Check Translation</button>
        </form>
        <a href="index.php" class="back-btn">Back to Tools</a>
    </div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>
<?php

}
else
{
    $TargetLanguage = $selectedLanguage = $_POST['language']; // Set target language from the form

    // Scan the directory for PHP files
    scanDirectory($directory, $phpFiles, $TargetLanguage);

    // Scan the found files for missing language keys
    scanFilesForMissingLanguageKeys($phpFiles, $missingKeys, $TargetLanguage);

    // Display the result after the form is submitted
    echo "<div class='container'>";
    if (!empty($missingKeys)) {
        echo "<h2>Missing Language Keys:</h2>";
        foreach ($missingKeys as $file => $keys) {
            // Make the path relative to the base directory
            $relativePath = str_replace(realpath(__DIR__ . '/../'), '.', $file);

            // Normalize to remove any double slashes (e.g., .// to ./)
            $relativePath = preg_replace('/\/+/', '/', $relativePath);

            echo "<h3>File: $relativePath</h3><ul>";
            foreach ($keys as $key) {
                echo "<li><span class='missing-key'>- Missing key: $key</span></li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p class='success'>No missing language keys found for the selected language.</p>";
    }
    echo "<a href='index.php' class='back-btn'>Back to Tools</a>";
    echo "</div>";
    exit;
}
?>



</body>
</html>

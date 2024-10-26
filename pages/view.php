<?php
// Start output buffering at the very beginning of the script
ob_start();
ini_set('zlib.output_compression', 'Off');
ini_set('display_errors', 0);
error_reporting(0);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

if (isset($_GET['doc'])) {
    $docId = $_GET['doc'];

    // Validate document ID to prevent invalid input
    if (!preg_match('/^\d+$/', $docId)) {
        ob_end_clean();
        die("Invalid document ID.");
    }

    // Fetch document path from the database
    $stmt = $MySQL->getConnection()->prepare("SELECT document_name, document_file FROM documents WHERE id = ?");
    $stmt->bind_param("i", $docId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($documentName, $documentPath);
        $stmt->fetch();

        // Ensure no output before this point
        ob_clean(); // Clean the output buffer to prevent any extra output

        // Check if the file exists on the server
        if (file_exists($documentPath)) {
            // Flush output buffer and read the file
            flush();
            echo '<a href="'.$documentPath.'">link</a>';
            exit(); // Ensure script ends after the file is sent
        } else {
            ob_end_clean();
            die("File not found on the server.");
        }
    } else {
        ob_end_clean();
        die("Document not found or access denied.");
    }
    $stmt->close();
} else {
    ob_end_clean();
    die("No document specified.");
}
?>

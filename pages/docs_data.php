<?php
// pages/docs_data.php

global $selectedLanguage, $lang_data, $MySQL;

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

$userGuid = $_SESSION['loggedIn']['user_guid'];
$colspan = 2;

if($_SESSION['loggedIn']['group'] !== 'admins') {


    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate form fields
        if (isset($_FILES['pdfFile']) && isset($_POST['displayName'])) {
            $displayName = trim($_POST['displayName']);

            // Validate the uploaded file
            $fileError = $_FILES['pdfFile']['error'];
            if ($fileError !== UPLOAD_ERR_OK) {
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_file_type_or_size'] ?? 'Invalid file type or size exceeds the 5MB limit.') . "');</script>";
                return;
            }

            $fileTmpPath = $_FILES['pdfFile']['tmp_name'];
            $fileSize = $_FILES['pdfFile']['size'];
            $fileType = mime_content_type($fileTmpPath);

            // Check file type and size
            $allowedMimeTypes = ['application/pdf'];
            $maxFileSize = 5 * 1024 * 1024; // 5 MB
            if (!in_array($fileType, $allowedMimeTypes) || $fileSize > $maxFileSize) {
                echo "<script>showError('Invalid file type or size exceeds the 5MB limit.');</script>";
                return;
            }

            // Create the user's folder if it doesn't exist
            $uploadDir = './uploads/' . $userGuid . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $cleanFileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $displayName);
            $targetFilePath = $uploadDir . $cleanFileName;

            if (move_uploaded_file($fileTmpPath, $targetFilePath)) {
                chmod($targetFilePath, 0644);
                // Save the file path and metadata in the database
                $stmt = $MySQL->getConnection()->prepare("INSERT INTO documents (uploaded_for, document_name, document_file, document_type, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $uploadedBy = $_SESSION['loggedIn']['user_guid'];
                    $stmt->bind_param("isssi", $userGuid, $displayName, $targetFilePath, $fileType, $uploadedBy);
                    if ($stmt->execute()) {
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['file_uploaded_successfully'] ?? 'File uploaded successfully.') . "');</script>";
                    } else {
                        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['error_saving_metadata'] ?? 'Error saving file metadata.') . "');</script>";
                    }
                    $stmt->close();
                } else {
                    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['datebase_error'] ?? 'Database Error.') . "');</script>";
                }
            } else {
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['failed_to_save_file'] ?? 'Failed to save the file on the server.') . "');</script>";
            }
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_form_submission'] ?? 'Invalid form submission.') . "');</script>";
        }
    }
}

// Prepare the SQL statement using prepared statements
if($_SESSION['loggedIn']['group'] !== 'admins')
    $stmt = $MySQL->getConnection()->prepare("SELECT * FROM documents WHERE uploaded_for = ? OR uploaded_by = ? OR uploaded_for = '0'");
else
    $stmt = $MySQL->getConnection()->prepare("SELECT * FROM documents");
if ($stmt) {
    if($_SESSION['loggedIn']['group'] !== 'admins')
        $stmt->bind_param("ii", $userGuid, $userGuid);

    $stmt->execute();
    $result = $stmt->get_result();

    // Display the documents page content
    echo '
    <div class="page">
        <div class="document_page" style="align-items: start;">
            <div class="document-list">
                <table>
                    <thead>
                        <tr>
                            <td colspan="4">';
                                if($_SESSION['loggedIn']['group'] !== 'admins') {
                                    echo '
                                    <!-- Upload form for the user -->
                                    <form action="" method="post" enctype="multipart/form-data">
                                        <label class="" for="pdfFile">' . htmlspecialchars($lang_data[$selectedLanguage]['select_pdf_upload'] ?? 'Select PDF to upload') . '</label>
                                        <input style="border: 3px solid silver; width:88%; height:22px; padding: 4px;" class="up_file" type="file" name="pdfFile" accept="application/pdf" required>
                                        
                                        <label class="" for="displayName">' . htmlspecialchars($lang_data[$selectedLanguage]['display_name'] ?? 'Display name') . '</label>
                                        <input style="width:88%; height:22px; padding: 4px;" class="up_file" type="text" name="displayName" required>
                                        
                                        <input id="btn-update" style="border: 3px solid silver;" type="submit" value="' . htmlspecialchars($lang_data[$selectedLanguage]['upload_pdf'] ?? 'Upload PDF') . '">
                                    </form>';
                                }
                            echo '
                            </td>
                        </tr>
                        <tr>';
    if($_SESSION['loggedIn']['group'] === 'admins'){
        $colspan = 4;
        echo '
                            <th style="font-size: 0.80rem; font-weight: bolder; width: 25%;">' . htmlspecialchars($lang_data[$selectedLanguage]["uploaded_by"] ?? "Uploaded by") . '</th>
                            <th style="font-size: 0.80rem; font-weight: bolder; width: 25%;">' . htmlspecialchars($lang_data[$selectedLanguage]["uploaded_for"] ?? "Uploaded for") . '</th>';
    }
    echo '
                            <th style="font-size: 0.80rem; font-weight: bolder;">' . htmlspecialchars($lang_data[$selectedLanguage]["doc_name"] ?? "Document Name") . '</th>
                            <th style="font-size: 0.80rem; font-weight: bolder; white-space: nowrap; width: 1%; max-width: max-content;">' . htmlspecialchars($lang_data[$selectedLanguage]["actions"] ?? "Actions") . '</th>
                        </tr>
                    </thead>
                    <tbody>';

    // Check if there are results
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $docName = htmlspecialchars($row['document_name']);
            $docID = htmlspecialchars($row['doc_guid']);
            $uploader = htmlspecialchars($row['uploaded_by']);
            $target = htmlspecialchars($row['uploaded_for']);
            $docPath = $row['document_file'] ?? 0;
            $fileLink = $docPath;
            echo '
            <tr>';
            if($_SESSION['loggedIn']['group'] === 'admins'){
                echo '
                <td style="font-size: 0.75rem;"><a style="text-decoration: underline; color: black;" href="index.php?lang='.$selectedLanguage.'&page=admin&sub_page=users&action=edit&user_guid='.$uploader.'">' . getUserName($uploader, false) . '</a></td>
                <td style="font-size: 0.75rem;">' . ($target != 0 ? '<a style="text-decoration: underline; color: black;" href="index.php?lang='.$selectedLanguage.'&page=admin&sub_page=users&action=edit&user_guid='.$uploader.'">' . getUserName($target, false) . '</a>' : "All users") . '</td>';
            }
            echo '
                <td style="font-size: 0.75rem;">' . $docName . '</td>
                <td style="font-size: 0.75rem; white-space: nowrap;">
                    <a href="' . $fileLink . '" target="_blank"><img class="manage_shift_btn" src="img/open-file.png" alt=""></a> ';
            if($_SESSION['loggedIn']['user_guid'] === $row['uploaded_by'] || $_SESSION['loggedIn']['group'] =='admins')
                echo '<a href="index.php?lang='.$selectedLanguage.'&page=docs_data&action=delete&file='.$docID.'"><img class="manage_shift_btn" src="img/delete-file.png" alt=""></a>';

            echo '
                </td>
            </tr>';
        }
    } else {
        echo '<tr><td colspan="' . $colspan . '">' . htmlspecialchars($lang_data[$selectedLanguage]["no_documents_found"] ?? "No documents found") . '</td></tr>';
    }

    echo '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';
    $stmt->close();
} else {
    echo "<script>showError('Database error.');</script>";
}
?>

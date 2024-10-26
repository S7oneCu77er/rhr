<?php
// pages/admin/documents.php

// Include necessary configurations and handlers
require_once './inc/functions.php';
require_once './inc/mysql_handler.php';

global $lang_data, $selectedLanguage, $MySQL;

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

// Ensure the user has the admin role
if ($_SESSION['loggedIn']['group'] !== 'admins') {
    echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['access_denied'] ?? 'Access denied.') . "');</script>";
    return;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form fields
    if (isset($_FILES['pdfFile']) && isset($_POST['displayName']) && isset($_POST['user_guid'])) {
        $displayName    = trim($_POST['displayName']);
        $userGuid       = intval($_POST['user_guid']);
        if($userGuid == 'ALL')
            $userGuid = '0';

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
        $uploadDir ='./uploads/' . $userGuid . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $cleanFileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $displayName.".pdf");
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
                echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['datebase_error'] ?? 'Databas Error.') . "');</script>";
            }
        } else {
            echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['failed_to_save_file'] ?? 'Failed to save the file on the server.') . "');</script>";
        }
    } else {
        echo "<script>showError('" . htmlspecialchars($lang_data[$selectedLanguage]['invalid_form_submission'] ?? 'Invalid form submission.') . "');</script>";
    }
}

// Display the upload form
echo '
    <div class="page">
        <div class="document_page">
            <form action="index.php?lang=' . urlencode($selectedLanguage) . '&page=admin&sub_page=documents" method="post" enctype="multipart/form-data">
                <label class="up_file_label" for="pdfFile">' . htmlspecialchars($lang_data[$selectedLanguage]['select_pdf_upload'] ?? 'Select PDF to upload') . '</label>
                <input class="up_file" type="file" name="pdfFile" accept="application/pdf" required>

                <label class="up_file_label" for="displayName">' . htmlspecialchars($lang_data[$selectedLanguage]['display_name'] ?? 'Display name') . '</label>
                <input style="width:92.5%;" class="up_file" type="text" name="displayName" required>

                <label class="up_file_label" for="user_guid">' . htmlspecialchars($lang_data[$selectedLanguage]['upload_to_user'] ?? 'Upload to user') . '</label>
                <select class="up_file" name="user_guid" required>
                    <option value="ALL" disabled selected>' . htmlspecialchars($lang_data[$selectedLanguage]['select_user'] ?? 'Select user') . '</option>
                    <option value="ALL">ALL</option>';
                    $sql = "
                        SELECT users.user_guid, users.first_name, users.last_name, workers.worker_id
                        FROM users
                        JOIN workers ON users.user_guid = workers.user_guid
                        ORDER BY users.user_guid
                    ";

                    // Prepare the statement
                    $stmt = $MySQL->getConnection()->prepare($sql);

                    if ($stmt) {
                        // Execute the statement
                        $stmt->execute();

                        // Get the result set
                        $result = $stmt->get_result();

                        if ($result) {
                            // Loop over the result set
                            while ($row = $result->fetch_assoc()) {
                                $worker_id = htmlspecialchars($row['worker_id']);
                                $userGuid = htmlspecialchars($row['user_guid']);
                                $full_name = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);
                                echo '<option value="' . $userGuid . '">#' . $worker_id . ' - ' . $full_name . '</option>';
                            }
                            $stmt->close();
                        }
                    }

                    echo '
                </select>

                <input style="margin-top: 50px;" class="up_file_button" id="btn-update" type="submit" value="' . htmlspecialchars($lang_data[$selectedLanguage]['upload_pdf'] ?? 'Upload PDF') . '">
            </form>
        </div>
    </div>';
?>

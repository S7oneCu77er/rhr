<?php

global $MySQL, $lang_data, $selectedLanguage;
    function logAction($user_guid, $target_guid, $action, $description)
    {
        global $MySQL;

        $stmt = $MySQL->getConnection()->prepare(
        "INSERT INTO support (user_guid, action, target_guid, details) 
               VALUES (?, ?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param("iiiiis", $user_guid, $action, $target_guid, $description);
            $stmt->execute();
        }
    }
?>
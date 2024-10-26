<?php
// pages/assignments.php

global $MySQL, $lang_data, $selectedLanguage;

if (!isset($_SESSION['loggedIn'])) {
    header("Location: index.php");
    exit();
}

$user_guid = $_SESSION['loggedIn']['user_guid'];

$sql = "
    SELECT
        sa.assignment_guid,
        sa.shift_start_date,
        sa.shift_end_date,
        s.site_name,
        s.site_address
    FROM
        shift_assignments sa
    INNER JOIN
        shift_assignment_workers saw ON sa.assignment_guid = saw.assignment_guid
    INNER JOIN
        sites s ON sa.site_guid = s.site_guid
    WHERE
        saw.user_guid = ?
        AND CURDATE() BETWEEN sa.shift_start_date AND sa.shift_end_date LIMIT 1
";

// Prepare the SQL statement using prepared statements
$stmt = $MySQL->getConnection()->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<div class="page">
    <div class="assign-list">
        <table>
            <thead>
            <tr>
                <th style="width: 15%;"><?php echo htmlspecialchars($lang_data[$selectedLanguage]['assignment'] ?? "Assignment"); ?></th>
                <th style="width: 70%;"><?php echo htmlspecialchars($lang_data[$selectedLanguage]['information'] ?? "Information"); ?></th>
                <th style="white-space: nowrap;"><?php echo htmlspecialchars($lang_data[$selectedLanguage]['end_date'] ?? "End Date"); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            if ($result && $result->num_rows > 0)
            {
                while ($row = $result->fetch_assoc()) {
                    echo '
                    <tr>
                        <td style="font-size: 0.75rem;" data-label="' . htmlspecialchars($lang_data[$selectedLanguage]['assignment_type'] ?? "Assignments Type") . '">' . htmlspecialchars($lang_data[$selectedLanguage]['work'] ?? "Work") . '</td>
                        <td style="font-size: 0.72rem;" data-label="' . htmlspecialchars($lang_data[$selectedLanguage]['assignment_address'] ?? "Assignment Address") . '" class="geo-address" data-address="' . htmlspecialchars($row['site_address']) . '">Loading...</td>
                        <td style="font-size: 0.75rem; white-space: nowrap;" data-label="' . htmlspecialchars($lang_data[$selectedLanguage]['assignment_end_date'] ?? "Assignment End Date") . '">' . htmlspecialchars($row['shift_end_date']) . '</td>
                    </tr>';
                }
            } else {
                echo '
                <tr>
                    <td colspan="3" class="alert" style="text-align: center;">' . htmlspecialchars($lang_data[$selectedLanguage]['no_active_assignments'] ?? "No active work assignments found.") . '</td>
                </tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    function initGeocode() {
        const geoAddressCells = document.querySelectorAll('.geo-address');

        geoAddressCells.forEach(async function (cell) {
            const address = cell.getAttribute('data-address');

            try {
                const geoData = await getGeoAddress(address + ' Netanya');
                if (geoData && geoData.name) {
                    cell.innerHTML = '<a style=\"color:black; text-decoration: underline;\" href=\"https://maps.google.com/?q=' + geoData.name + '\">' + geoData.name + '</a>';
                } else {
                    cell.textContent = '<?php echo htmlspecialchars($lang_data[$selectedLanguage]['address_not_found'] ?? "Address not found"); ?>';
                }

            } catch (error) {
                cell.textContent = '<?php echo htmlspecialchars($lang_data[$selectedLanguage]['error_loading_address'] ?? "Error loading address"); ?>';
            }
        });
    }

    function waitForGoogleMaps() {
        // Check if the Google Maps API is ready
        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.Geocoder === 'function') {
            initGeocode(); // Call the function once the API is ready
        } else {
            // Retry after 500 milliseconds if the API isn't ready
            setTimeout(waitForGoogleMaps, 200);
        }
    }

    waitForGoogleMaps();
</script>

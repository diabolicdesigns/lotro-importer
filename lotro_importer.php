<?php
//Version: lotro_importer 2.2.1
// Enable error reporting and display errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enable or disable error logging to a file
$logErrorsToFile = false; // Set to true to log errors to a file

// Include EQdkpPlus configuration file (assuming it's in the same directory)
require_once('config.php');

// Function to log errors to a file
function logError($message) {
    global $logErrorsToFile;

    if ($logErrorsToFile) {
        $logFile = 'xmllog/error_log.txt'; // Adjust the path to your log file
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
    }
}

// Check if the EQdkpPlus configuration file exists
if (!file_exists('config.php')) {
    $errorMessage = 'EQdkpPlus configuration file (config.php) not found.';
    logError($errorMessage); // Log the error
    die('<span style="color: red;">' . $errorMessage . '</span>');
}

// Check if the XML file exists
$xml_file = 'lotro_import/roster.xml'; // Adjust the path to your XML file
if (!file_exists($xml_file)) {
    $errorMessage = 'XML file not found: ' . $xml_file;
    logError($errorMessage); // Log the error
    die('<span style="color: red;">' . $errorMessage . '</span>');
}

// Connect to the EQdkpPlus database
$db = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

// Check if the connection was successful
if ($db->connect_errno) {
    $errorMessage = 'Database connection failed: ' . $db->connect_error;
    logError($errorMessage); // Log the error
    die('<span style="color: red;">' . $errorMessage . '</span>');
}

// Path to your XML file (assuming it's in the same directory)
$xml_file = 'lotro_import/roster.xml'; // Adjust the path to your XML file

// Load the XML file
$xml = simplexml_load_file($xml_file);

$updates_made = false; // Flag to track if any updates were made

// Mapping of class names to numeric values
$class_mapping = [
    'Minstrel' => 1,
    'Lore-master' => 4,
    'Hunter' => 3,
    'Beorning' => 10,
    'Brawler' => 11,
    'Burglar' => 5,
    'Captain' => 2,
    'Champion' => 7,
    'Guardian' => 6,
    'Rune-keeper' => 8,
    'Warden' => 9,
    'Mariner' => 12,
    'Corsair' => 12,
];

// Mapping of race names to numeric values
$race_mapping = [
    'Highelf' => 6,
    'Man' => 1,
    'Beorning' => 5,
    'Dwarf' => 4,
    'Elf' => 3,
    'Hobbit' => 2,
    'Riverhobbit' => 8,
    'Stoutaxedwarf' => 7,
];

// Get the list of character names from the XML file
$characterNamesInXML = [];

foreach ($xml->member as $member) {
    $characterNamesInXML[] = (string)$member['name'];
}

// Fetch all character names from the database
$characterNamesInDB = [];
$query = "SELECT member_name FROM {$table_prefix}members";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $characterNamesInDB[] = $row['member_name'];
    }
}

// Compare character names in the database with the XML
$charactersToRemove = array_diff($characterNamesInDB, $characterNamesInXML);

// Remove characters that are not in the XML
if (!empty($charactersToRemove)) {
    $charactersToRemoveList = implode("', '", $charactersToRemove);
    $deleteQuery = "DELETE FROM {$table_prefix}members WHERE member_name IN ('$charactersToRemoveList')";
    if ($db->query($deleteQuery)) {
        echo '<span style="color: green;">Characters not in XML file removed from the database.</span><br>';
    } else {
        $errorMessage = 'Error removing characters not in XML file: ' . $db->error;
        logError($errorMessage); // Log the error
        echo '<span style="color: red; font-style: italic;">' . $errorMessage . '</span><br>';
    }

    // Echo the removed character names in red italic
    if (!empty($charactersToRemove)) {
        echo '<span style="color: red; font-style: italic;">Characters removed from the database: ' . implode(', ', $charactersToRemove) . '</span><br>';
    }
}

// Process the member data
foreach ($xml->member as $member) {
    $name = (string)$member['name'];
    $class_name = strtolower((string)$member['class']);
    $race_name = strtolower((string)$member['race']);
    $level = (int)$member['level'];
    $rank = (int)$member['rank'];

    // Convert class and race names to numeric values
    $class = isset($class_mapping[$class_name]) ? $class_mapping[$class_name] : null;
    $race = isset($race_mapping[$race_name]) ? $race_mapping[$race_name] : null;

    // Build the JSON data for 'profiledata' column
    $profiledata = json_encode([
        'faction' => '',
        'race' => $race !== null ? (string) $race : '', // Ensure race is not null
        'class' => $class !== null ? (string) $class : '', // Ensure class is not null
        'level' => (int) $level,
        'profession1' => 'farmer',
        'profession1_mastery' => 0,
        'profession1_proficiency' => 0,
        'profession2' => 'farmer',
        'profession2_mastery' => 0,
        'profession2_proficiency' => 0,
        'profession3' => 'farmer',
        'profession3_mastery' => 0,
        'profession3_proficiency' => 0,
        'vocation' => 'armourer', // Corrected vocation value
    ]);

    // Check if the character already exists in the database
    $query = "SELECT member_id, profiledata, last_update FROM {$table_prefix}members WHERE member_name = ?";
    $stmt = $db->prepare($query);

    if (!$stmt) {
        $errorMessage = 'Error preparing SELECT statement for ' . $name . ': ' . $db->error;
        logError($errorMessage); // Log the error
        echo '<span style="color: red;">' . $errorMessage . '</span><br>';
        continue; // Skip to the next character
    }

    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Character exists, update the 'profiledata', 'member_rank_id', and 'last_update' columns
        $row = $result->fetch_assoc();
        $member_id = $row['member_id'];
        $existingProfileData = json_decode($row['profiledata'], true);
        $lastUpdateTimestamp = $row['last_update'];

        // Check if any changes need to be made
        $needsUpdate = false;

        if ($existingProfileData['level'] !== $level) {
            // Level has changed, update it
            $existingProfileData['level'] = $level;
            $needsUpdate = true;
        }

        // Merge the existing profiledata with the new level information
        $updatedProfileData = array_merge($existingProfileData, [
            'level' => $level
        ]);

        // Check if any updates are needed
        if ($needsUpdate) {
            $update_query = "UPDATE {$table_prefix}members SET profiledata = ?, member_rank_id = ?, last_update = ? WHERE member_id = ?";
            $update_stmt = $db->prepare($update_query);

            if (!$update_stmt) {
                $errorMessage = 'Error preparing UPDATE statement for ' . $name . ': ' . $db->error;
                logError($errorMessage); // Log the error
                echo '<span style="color: red;">' . $errorMessage . '</span><br>';
                continue; // Skip to the next character
            }

            $currentTimestamp = time();

            $updatedProfileData_json = json_encode($updatedProfileData);

            $update_stmt->bind_param("siii", $updatedProfileData_json, $rank, $currentTimestamp, $member_id);

            if ($update_stmt->execute()) {
                echo '<span style="color: green;">Character data for ' . $name . ' updated successfully.</span><br>';
                $updates_made = true;
            } else {
                $errorMessage = 'Error updating character data for ' . $name . ': ' . $update_stmt->error;
                logError($errorMessage); // Log the error
                echo '<span style="color: red;">' . $errorMessage . '</span><br>';
            }

            $update_stmt->close();
        }
    } else {
        // Character does not exist, insert a new record
        $insert_query = "INSERT INTO {$table_prefix}members (member_name, profiledata, member_rank_id, last_update) VALUES (?, ?, ?, ?)";
        $insert_stmt = $db->prepare($insert_query);

        if (!$insert_stmt) {
            $errorMessage = 'Error preparing INSERT statement for ' . $name . ': ' . $db->error;
            logError($errorMessage); // Log the error
            echo '<span style="color: red;">' . $errorMessage . '</span><br>';
            continue; // Skip to the next character
        }

        $currentTimestamp = time();

        $insert_stmt->bind_param("ssii", $name, $profiledata, $rank, $currentTimestamp);

        $name_param = $name;
        $profiledata_param = $profiledata;
        $rank_param = $rank;
        $currentTimestamp_param = $currentTimestamp;

        $insert_stmt->bind_param("ssii", $name_param, $profiledata_param, $rank_param, $currentTimestamp_param);

        if ($insert_stmt->execute()) {
            echo '<span style="color: green;">New character data for ' . $name . ' inserted successfully.</span><br>';
            $updates_made = true;
        } else {
            $errorMessage = 'Error inserting character data for ' . $name . ': ' . $insert_stmt->error;
            logError($errorMessage); // Log the error
            echo '<span style="color: red;">' . $errorMessage . '</span><br>';
        }

        $insert_stmt->close();
    }
}

if ($updates_made) {
    echo '<span style="color: green;"><b>Character data updated/inserted successfully.</b></span><br>';
} else {
    echo '<span style="color: blue;"><b>No updates/inserts were made.</b></span><br>';
}

// Rename the XML file to indicate it has been processed
$new_xml_filename = 'lotro_import/roster-' . date('Ymd') . '.xml'; // Rename with the current date
if (rename($xml_file, $new_xml_filename)) {
    echo '<span style="color: green;">File renamed successfully to ' . $new_xml_filename . '.</span><br>';
} else {
    echo '<span style="color: red;">Error renaming the XML file.</span><br>';
}

// Close the database connection
$db->close();
?>

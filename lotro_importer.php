<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include EQdkpPlus configuration file (assuming it's in the same directory)
require_once('config.php');

// Connect to the EQdkpPlus database
$db = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

// Check if the connection was successful
if ($db->connect_errno) {
    die('<span style="color: red;">Database connection failed: ' . $db->connect_error . '</span>');
}

// Path to your CSV file (assuming it's in the same directory)
$csv_file = 'lotro_import/character_data.csv';

// Mapping of class names to numeric values
$class_mapping = [
    'beorning' => 10,
    'brawler' => 11,
    'burglar' => 5,
    'captain' => 2,
    'champion' => 7,
    'guardian' => 6,
    'hunter' => 3,
    'lore-master' => 4,
    'minstrel' => 1,
    'rune-keeper' => 8,
    'warden' => 9,
];

// Mapping of race names to numeric values
$race_mapping = [
    'beorning' => 5,
    'dwarf' => 4,
    'elf' => 3,
    'highelf' => 6,
    'hobbit' => 2,
    'man' => 1,
    'riverhobbit' => 8,
    'stoutaxedwarf' => 7,
];

$updates_made = false; // Flag to track if any updates were made

// Open and read the CSV file
if (($handle = fopen($csv_file, 'r')) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        // Extract data from the CSV columns
        $name = $data[0];
        $class_name = strtolower($data[2]); // Convert to lowercase
        $race_name = strtolower($data[1]);  // Convert to lowercase
        $level = $data[3];
        $rank = (int) $data[4]; // Parse rank from the 5th column

        // Get the current Unix timestamp
        $currentTimestamp = time();

        // Debugging output
        echo '<span style="color: red;">Name: ' . $name . ', Class: ' . $class_name . ', Race: ' . $race_name . ', Level: ' . $level . ', Rank: ' . $rank . ', Creation Date: ' . date('Y-m-d H:i:s', $currentTimestamp) . ', Last Update: ' . date('Y-m-d H:i:s', $currentTimestamp) . '</span><br>';

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
        $query = "SELECT member_id FROM eqdkp23_members WHERE member_name = ?";
        $stmt = $db->prepare($query);

        if (!$stmt) {
            echo '<span style="color: red;">Error preparing SELECT statement for ' . $name . ': ' . $db->error . '</span><br>';
            continue; // Skip to the next character
        }

        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Character exists, update the 'profiledata', 'member_rank_id', and 'last_update' columns
            $row = $result->fetch_assoc();
            $member_id = $row['member_id'];

            // Update the 'profiledata', 'member_rank_id', and 'last_update' columns in the eqdkp23_members table
            $update_query = "UPDATE eqdkp23_members SET profiledata = ?, member_rank_id = ?, last_update = ? WHERE member_id = ?";
            $update_stmt = $db->prepare($update_query);

            if (!$update_stmt) {
                echo '<span style="color: red;">Error preparing UPDATE statement for ' . $name . ': ' . $db->error . '</span><br>';
                continue; // Skip to the next character
            }

            $update_stmt->bind_param("siii", $profiledata, $rank, $currentTimestamp, $member_id);

            if ($update_stmt->execute()) {
                echo '<span style="color: red;">Character data for ' . $name . ' updated successfully.</span><br>';
                $updates_made = true;
            } else {
                echo '<span style="color: red;">Error updating character data for ' . $name . ': ' . $update_stmt->error . '</span><br>';
            }

            $update_stmt->close();
        } else {
            // Character doesn't exist, insert a new record
            $insert_query = "INSERT INTO eqdkp23_members (member_name, profiledata, member_rank_id, last_update, member_creation_date) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);

            if (!$insert_stmt) {
                echo '<span style="color: red;">Error preparing INSERT statement for ' . $name . ': ' . $db->error . '</span><br>';
                continue; // Skip to the next character
            }

            $insert_stmt->bind_param("ssiis", $name, $profiledata, $rank, $currentTimestamp, $currentTimestamp);

            if ($insert_stmt->execute()) {
                echo '<span style="color: red;">Character data for ' . $name . ' inserted successfully.</span><br>';
                $updates_made = true;
            } else {
                echo '<span style="color: red;">Error inserting character data for ' . $name . ': ' . $insert_stmt->error . '</span><br>';
            }

            $insert_stmt->close();
        }

        $stmt->close();
    }
    fclose($handle);
}

// Close the database connection
$db->close();

if (!$updates_made) {
    // No updates were made
    echo '<span style="color: red;">No update needed, all character data is up to date.</span>';
}
?>

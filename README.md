LOTRO Character Data Importer
Overview
The LOTRO Character Data Importer is a PHP script designed to import character data from an XML file into a database. It is intended for use with EQdkpPlus, a popular web-based guild management tool for MMORPGs.

The script performs the following tasks:

Error Handling: Enables error reporting and logging to a file for debugging purposes. If errors occur during execution, they will be logged in error_log.txt.

Configuration: Requires an EQdkpPlus configuration file (config.php) to connect to the database.

Check for XML File: Verifies the existence of the XML file containing character data (roster.xml).

Database Connection: Connects to the EQdkpPlus database.

Character Data Comparison: Compares the character names in the database with the character names in the XML file. Characters that exist in the database but not in the XML file will be removed from the database.

Data Import/Update: Processes character data from the XML file. For each character:

If the character exists in the database, it checks for updates in character data, like level changes.
If the character does not exist, it inserts a new record into the database.
Logging and Output: Logs errors and status messages to a log file and provides colored output for easy monitoring.

File Renaming: Renames the processed XML file to include the current date, indicating that it has been imported.

Usage
Place the lotro_importer.php script in your web server directory.

Update the EQdkpPlus configuration file (config.php) with the appropriate database connection details.

Place your LOTRO character data XML file (e.g., roster.xml) in the same directory as the script.

Execute the script in a web browser or from the command line.

Prerequisites
Web server with PHP support.
EQdkpPlus guild management tool.
EQdkpPlus configuration file (config.php).
Notes
Ensure that the database tables in EQdkpPlus match the expected structure and that table prefixes match the configuration.

Make sure that the script, EQdkpPlus, and your XML file are in the same directory, or update the paths in the script as needed.

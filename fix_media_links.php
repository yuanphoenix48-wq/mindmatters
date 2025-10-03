<?php
require_once 'connect.php';

echo "<h2>Fixing Media Resources with Real Working Links...</h2>";

$sql_file = 'fix_media_resources.sql';
$sql_content = file_get_contents($sql_file);

if ($sql_content === false) {
    die("<p style='color: red;'>Error: Could not read SQL file: $sql_file</p>");
}

// Split SQL commands by semicolon, but handle semicolons within comments or strings
$commands = array_filter(array_map('trim', explode(';', $sql_content)));

$success_count = 0;
$error_count = 0;

foreach ($commands as $command) {
    if (empty($command)) continue;
    
    // Skip comments
    if (str_starts_with($command, '--') || str_starts_with($command, '/*')) {
        continue;
    }

    if ($conn->query($command)) {
        echo "<p style='color: green;'>✓ Executed: " . htmlspecialchars(substr($command, 0, 100)) . "...</p>";
        $success_count++;
    } else {
        echo "<p style='color: red;'>✗ Error executing: " . htmlspecialchars(substr($command, 0, 100)) . "... Error: " . $conn->error . "</p>";
        $error_count++;
    }
}

echo "<h3>Media Resources Fixed!</h3>";
echo "<p>Total commands executed: " . ($success_count + $error_count) . "</p>";
echo "<p style='color: green;'>Successful: $success_count</p>";
echo "<p style='color: red;'>Errors: $error_count</p>";

// Show some examples of the new content
echo "<h3>New Content Examples:</h3>";
$result = $conn->query("SELECT title, external_url, category FROM media_resources LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li><strong>" . htmlspecialchars($row['title']) . "</strong> (" . ucfirst($row['category']) . ") - <a href='" . htmlspecialchars($row['external_url']) . "' target='_blank'>" . htmlspecialchars($row['external_url']) . "</a></li>";
    }
    echo "</ul>";
}

$conn->close();
?>















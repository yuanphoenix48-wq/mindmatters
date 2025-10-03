<?php
// Cache clearing script for Mind Matters
// Run this script to force clear browser cache

echo "<h2>Mind Matters - Cache Clearing</h2>";

// Get current timestamp
$timestamp = time();
echo "<p>Current timestamp: " . $timestamp . "</p>";

// Check if files exist and get their modification times
$files = [
    'styles/mobile.css',
    'js/mobile.js',
    'styles/global.css',
    'styles/dashboard.css',
    'styles/notifications.css',
    'js/notifications.js'
];

echo "<h3>File Modification Times:</h3>";
echo "<ul>";

foreach ($files as $file) {
    if (file_exists($file)) {
        $mtime = filemtime($file);
        $date = date('Y-m-d H:i:s', $mtime);
        echo "<li><strong>$file</strong>: $mtime ($date)</li>";
    } else {
        echo "<li><strong>$file</strong>: <span style='color: red;'>File not found</span></li>";
    }
}

echo "</ul>";

// Generate cache busting URLs
echo "<h3>Cache Busting URLs:</h3>";
echo "<ul>";

foreach ($files as $file) {
    if (file_exists($file)) {
        $mtime = filemtime($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if ($extension === 'css') {
            echo "<li><strong>$file</strong>: <code>href=\"$file?v=$mtime\"</code></li>";
        } else if ($extension === 'js') {
            echo "<li><strong>$file</strong>: <code>src=\"$file?v=$mtime\"</code></li>";
        }
    }
}

echo "</ul>";

echo "<h3>Instructions:</h3>";
echo "<ol>";
echo "<li>Copy the URLs above and update your PHP files</li>";
echo "<li>Or use the automatic versioning already implemented in dashboard.php</li>";
echo "<li>Clear your browser cache (Ctrl+F5 or Cmd+Shift+R)</li>";
echo "<li>Test the mobile menu functionality</li>";
echo "</ol>";

echo "<p><strong>Note:</strong> The .htaccess file has been configured to prevent caching of CSS and JS files.</p>";
?>

































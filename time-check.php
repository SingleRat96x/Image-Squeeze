<?php
// Prevent direct access to this file.
defined('ABSPATH') || exit;

$now = new DateTime();
echo '<div style="padding: 20px; border: 1px solid #ddd; margin: 20px; font-family: monospace;">';
echo '<h2>Time Debug Information</h2>';
echo '<p>PHP Server Time: ' . date('Y-m-d H:i:s') . '</p>';
echo '<p>PHP Timezone: ' . date_default_timezone_get() . '</p>';
echo '<p>Current DateTime object time: ' . $now->format('Y-m-d H:i:s') . '</p>';

// Add JavaScript to get client's local time
echo '<p id="client-time">Client browser time: Loading...</p>';
echo '<p id="client-timezone">Client timezone: Loading...</p>';
echo '<script>
document.getElementById("client-time").innerHTML = "Client browser time: " + new Date().toLocaleString();
document.getElementById("client-timezone").innerHTML = "Client timezone: " + Intl.DateTimeFormat().resolvedOptions().timeZone;
</script>';

echo '</div>';
?> 
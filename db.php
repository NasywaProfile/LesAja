<?php
// Turn off default mysqli exception throwing to handle connection failures manually
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOST') ?: "127.0.0.1"; 
$username = getenv('DB_USER') ?: "root"; 
$password = getenv('DB_PASSWORD') ?: ""; 
$database = getenv('DB_NAME') ?: "lesaja_db"; 
$port = getenv('DB_PORT') ? intval(getenv('DB_PORT')) : 3306;

try {
    $conn = new mysqli($host, $username, $password, $database, $port);
    
    if ($conn->connect_errno) {
        throw new Exception($conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    if (!getenv('DB_HOST')) {
        echo "<div style='padding: 20px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; margin: 20px; border-radius: 8px; font-family: sans-serif; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>";
        echo "<h3 style='margin-top: 0; color: #856404;'>Database Connection Required</h3>";
        echo "<p>Your application is successfully running on Vercel, but it is not yet connected to a database.</p>";
        echo "<p>Please add these <strong>Environment Variables</strong> in your Vercel Project Dashboard (Settings > Environment Variables):</p>";
        echo "<ul style='line-height: 1.6;'>";
        echo "<li><code>DB_HOST</code> (Hostname of your hosted MySQL database)</li>";
        echo "<li><code>DB_USER</code></li>";
        echo "<li><code>DB_PASSWORD</code></li>";
        echo "<li><code>DB_NAME</code></li>";
        echo "<li><code>DB_PORT</code> (default is 3306)</li>";
        echo "</ul>";
        echo "<p style='font-size: 0.9em; color: #6c757d;'>Tip: You can use free hosting options from Railway, Aiven, or Clever Cloud for hosting your MySQL database.</p>";
        echo "</div>";
        exit;
    } else {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}
?>
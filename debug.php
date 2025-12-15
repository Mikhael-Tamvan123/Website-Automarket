<?php
require_once __DIR__ . '/../config.php';

echo "<h2>DEBUG SESSION & AUTH</h2>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Auth Check:</h3>";
if (isset($auth)) {
    echo "Auth object: ADA<br>";
    echo "isLoggedIn: " . ($auth->isLoggedIn() ? 'YES' : 'NO') . "<br>";
    echo "Role: " . ($_SESSION['role'] ?? 'TIDAK ADA') . "<br>";
    echo "checkRole('seller'): " . ($auth->checkRole('seller') ? 'YES' : 'NO') . "<br>";
} else {
    echo "Auth object: TIDAK ADA<br>";
}

echo "<h3>Test Links:</h3>";
echo '<a href="add_car.php">Test Add Car</a><br>';
echo '<a href="dashboard.php">Back to Dashboard</a>';
?>
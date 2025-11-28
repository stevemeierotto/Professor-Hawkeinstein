<?php
require_once 'config/database.php';

$db = getDB();

// New password that meets requirements: uppercase, lowercase, numbers
$newPassword = 'Root1234';

// Hash the password
$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

// Update root user password
$stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = 'root'");
$stmt->execute([$passwordHash]);

echo "Root password updated successfully!\n";
echo "New credentials:\n";
echo "  Username: root\n";
echo "  Password: Root1234\n";
echo "\n";
echo "Password meets requirements:\n";
echo "  - At least 8 characters ✓\n";
echo "  - Contains uppercase (R) ✓\n";
echo "  - Contains lowercase (oot) ✓\n";
echo "  - Contains numbers (1234) ✓\n";
?>

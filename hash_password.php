<?php
$password_plain = "admin123"; // Ganti dengan password yang ingin Anda gunakan
$hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);
echo "Password asli: " . $password_plain . "<br>";
echo "Password ter-hash: " . $hashed_password;
?>
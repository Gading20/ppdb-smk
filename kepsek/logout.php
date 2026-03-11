<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['kepsek_id'])) {
    // Log activity logout
    try {
        $conn->prepare(
            "INSERT INTO activity_log (user_type, user_id, activity_type, description)
             VALUES ('kepsek', :uid, 'logout', :desc)"
        )->execute([
            'uid'  => $_SESSION['kepsek_id'],
            'desc' => "Kepsek {$_SESSION['kepsek_name']} logout dari sistem"
        ]);
    } catch (Exception $e) { /* silent */
    }
}

// Hapus semua session kepsek
$keys = [
    'kepsek_id',
    'kepsek_username',
    'kepsek_name',
    'kepsek_email',
    'kepsek_nip',
    'kepsek_photo',
    'kepsek_last_login',
    'role'
];
foreach ($keys as $k) unset($_SESSION[$k]);

session_destroy();
header("Location: login.php");
exit();

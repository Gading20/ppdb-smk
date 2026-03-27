<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['walikelas_id'])) {
    try {
        $conn->prepare(
            "INSERT INTO activity_log (user_type, user_id, activity_type, description)
             VALUES ('wali_kelas', :uid, 'logout', :desc)"
        )->execute([
            'uid'  => $_SESSION['walikelas_user_id'] ?? $_SESSION['walikelas_id'],
            'desc' => "Wali Kelas {$_SESSION['walikelas_name']} logout dari sistem"
        ]);
    } catch (Exception $e) { /* silent */
    }
}

// Hapus semua session wali kelas
$keys = [
    'walikelas_id',
    'walikelas_user_id',
    'walikelas_username',
    'walikelas_name',
    'walikelas_email',
    'walikelas_nip',
    'walikelas_kelas',
    'walikelas_tingkat',
    'walikelas_rombel',
    'walikelas_jurusan',
    'walikelas_photo',
    'walikelas_last_login',
    'role',
];
foreach ($keys as $k) unset($_SESSION[$k]);

session_destroy();
header("Location: login.php");
exit();

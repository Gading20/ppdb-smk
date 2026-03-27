<?php
session_start();
require_once '../config/database.php';

// Log activity before destroying session
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $admin_name = $_SESSION['admin_name'];

    // Log the logout activity
    $conn->prepare("INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                    VALUES ('admin', :user_id, 'logout', :description)")
        ->execute([
            'user_id'     => $admin_id,
            'description' => "Admin {$admin_name} logout dari sistem"
        ]);
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();

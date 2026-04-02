<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard/");
    exit();
}

$error = '';

// Process the login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate username and password
    if (empty($username) || empty($password)) {
        $error = 'Username dan password tidak boleh kosong.';
    } else {
        // Query dari tabel users dengan role admin
        $sql = "SELECT * FROM users WHERE username = :username AND role = 'admin'";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            // Verify the password (bcrypt fallback ke plain text)
            $valid = password_verify($password, $admin['password']) || $password === $admin['password'];
            if ($valid) {
                // Set session variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['nama_lengkap'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_photo'] = $admin['foto_profil'];
                $_SESSION['admin_last_login'] = $admin['last_login'];
                $_SESSION['role'] = 'admin';

                // Record this login time
                $current_time = date('Y-m-d H:i:s');
                $conn->prepare("UPDATE users SET last_login = :login_time WHERE id = :id")
                    ->execute(['login_time' => $current_time, 'id' => $admin['id']]);

                // Store the current login time in session
                $_SESSION['admin_last_login'] = $current_time;

                // Log activity
                $conn->prepare("INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                                VALUES ('admin', :user_id, 'login', :description)")
                    ->execute([
                        'user_id' => $admin['id'],
                        'description' => "Admin {$admin['nama_lengkap']} login ke sistem"
                    ]);

                // Redirect to dashboard
                header("Location: dashboard/index.php");
                exit();
            } else {
                $error = 'Password yang Anda masukkan salah.';
            }
        } else {
            $error = 'Username tidak ditemukan.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(37, 99, 235, 0.25);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-slate-50 to-blue-100">

    <div class="max-w-md w-full mx-4 relative z-10">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <img src="../assets/default/logosmk.png" alt="SMK NURUL ULUM"
                class="h-24 mx-auto mb-4 drop-shadow-lg">
            <h2 class="text-3xl font-bold mb-2 bg-clip-text text-transparent bg-gradient-to-r from-blue-700 to-blue-900">
                Login Admin
            </h2>
            <p class="text-gray-500">Sistem Absensi SMK NURUL ULUM</p>
        </div>

        <!-- Login Form -->
        <div class="glass-effect rounded-2xl shadow-xl shadow-blue-200 p-8">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-300 text-red-600 px-4 py-3 rounded-lg relative mb-6 flex items-center" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p class="text-sm"><?= $error ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-4">
                    <div class="group">
                        <label class="text-gray-700 text-sm font-medium mb-2 block">
                            <i class="fas fa-user text-blue-600 mr-2"></i>Username
                        </label>
                        <input type="text" name="username" required
                            class="w-full px-5 py-4 rounded-lg bg-white border border-blue-300 text-gray-800 
                            focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 
                            transition-all duration-300 placeholder-gray-400"
                            placeholder="Masukkan username">
                    </div>

                    <div class="group">
                        <label class="text-gray-700 text-sm font-medium mb-2 block">
                            <i class="fas fa-lock text-blue-600 mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" name="password" required id="password"
                                class="w-full px-5 py-4 rounded-lg bg-white border border-blue-300 text-gray-800 
                                focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200
                                transition-all duration-300 placeholder-gray-400"
                                placeholder="Masukkan password">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-blue-500
                                transition-colors duration-300">
                                <i class="fas fa-eye text-lg" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-blue-800 text-white font-semibold py-4 px-4 
                    rounded-lg transition duration-300 hover:opacity-90 transform hover:-translate-y-0.5
                    focus:outline-none focus:ring-2 focus:ring-blue-300 flex items-center justify-center
                    shadow-lg shadow-blue-300">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login
                </button>

                <div class="text-center pt-2">
                    <a href="../index.php" class="text-gray-500 hover:text-blue-500 text-sm transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali ke Halaman Utama
                    </a>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-500 text-sm">
            <p>&copy; <?= date('Y') ?> DIGITECH UNIVERSITY</p>
            <p class="mt-1 text-gray-500">Intan Mutiara</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>
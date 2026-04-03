<?php
session_start();
require_once '../config/database.php';

// Hanya redirect jika sudah login DAN bukan sedang di halaman ini
if (isset($_SESSION['kepsek_id']) && $_SESSION['role'] === 'kepsek') {
    header("Location: dashboard/index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = 'Username dan password tidak boleh kosong.';
    } else {
        // Query dari tabel users dengan role kepsek
        $sql  = "SELECT * FROM users WHERE username = :username AND role = 'kepsek'";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['username' => $username]);
        $kepsek = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($kepsek) {
            // Coba password_verify dulu (bcrypt), fallback plain text
            $valid = password_verify($password, $kepsek['password'])
                || $password === $kepsek['password'];

            if ($valid) {
                // Set session
                $_SESSION['kepsek_id']        = $kepsek['id'];
                $_SESSION['kepsek_username']   = $kepsek['username'];
                $_SESSION['kepsek_name']       = $kepsek['nama_lengkap'];
                $_SESSION['kepsek_email']      = $kepsek['email'];
                $_SESSION['kepsek_nip']        = $kepsek['nip'];
                $_SESSION['kepsek_photo']      = $kepsek['foto_profil'];
                $_SESSION['kepsek_last_login'] = $kepsek['last_login'];
                $_SESSION['role']              = 'kepsek';

                // Update last_login di tabel users
                $current_time = date('Y-m-d H:i:s');
                $conn->prepare("UPDATE users SET last_login = :t WHERE id = :id")
                    ->execute(['t' => $current_time, 'id' => $kepsek['id']]);

                $_SESSION['kepsek_last_login'] = $current_time;

                // Log activity
                $conn->prepare(
                    "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                     VALUES ('kepsek', :uid, 'login', :desc)"
                )->execute([
                    'uid'  => $kepsek['id'],
                    'desc' => "Kepsek {$kepsek['nama_lengkap']} login ke sistem"
                ]);

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
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kepala Sekolah - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(217, 119, 6, 0.25);
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #1f2937;
            -webkit-box-shadow: 0 0 0px 1000px #fff inset;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-amber-50 via-yellow-50 to-orange-100 flex items-center justify-center bg-[url('../assets/default/bg-pattern.png')] bg-repeat">

    <!-- Green Gradient Overlay -->


    <div class="max-w-md w-full mx-4 relative z-10">

        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <img src="../assets/default/logosmk.png" alt="SMK NURUL ULUM"
                class="h-24 mx-auto mb-4 drop-shadow-[0_0_15px_rgba(217,119,6,0.5)]">
            <h2 class="text-3xl font-bold text-gray-800 mb-2 text-transparent bg-clip-text bg-gradient-to-r from-amber-600 to-orange-600">
                Login Kepala Sekolah
            </h2>
            <p class="text-gray-500">Sistem Absensi SMK NURUL ULUM</p>
        </div>

        <!-- Login Form -->
        <div class="glass-effect rounded-xl shadow-xl p-8 shadow-amber-200">

            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-300 text-red-400 px-4 py-3 rounded-lg mb-6 flex items-center" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-4">

                    <!-- Username -->
                    <div>
                        <label class="text-gray-700 text-sm font-medium mb-2 block">
                            <i class="fas fa-user-tie text-amber-600 mr-2"></i>Username
                        </label>
                        <input type="text" name="username" required
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            class="w-full px-5 py-4 rounded-lg bg-gray-50/80 border border-amber-200 text-gray-800 
                                   focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 
                                   transition-all duration-300 placeholder-gray-400"
                            placeholder="Masukkan username">
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="text-gray-700 text-sm font-medium mb-2 block">
                            <i class="fas fa-lock text-amber-600 mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                class="w-full px-5 py-4 rounded-lg bg-gray-50/80 border border-amber-200 text-gray-800 
                                       focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200
                                       transition-all duration-300 placeholder-gray-400"
                                placeholder="Masukkan password">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-amber-500 transition-colors duration-300">
                                <i class="fas fa-eye text-lg" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Submit -->
                <button type="submit"
                    class="w-full bg-gradient-to-r from-amber-500 to-orange-600 text-white font-medium py-4 px-4 
                           rounded-lg transition duration-300 hover:opacity-90 transform hover:-translate-y-0.5
                           focus:outline-none focus:ring-2 focus:ring-amber-200 flex items-center justify-center
                           shadow-lg shadow-amber-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login sebagai Kepala Sekolah
                </button>

                <!-- Back to main page -->
                <div class="text-center pt-2">
                    <a href="../index.php" class="text-gray-500 hover:text-amber-500 text-sm transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali ke Halaman Utama
                    </a>
                </div>

            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-500 text-sm">

            <div class="flex items-center justify-center gap-2 font-semibold">
                <img src="../assets/default/adigitech.png" class="h-5 w-5">
                <span>DIGITECH UNIVERSITY</span>
            </div>

            <p class="mt-1 text-gray-500">Intan Mutiara</p>

        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>
<?php
require_once '../config/database.php';

if (isset($_SESSION['walikelas_id'])) {
    header("Location: dashboard/");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Username dan password tidak boleh kosong.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM wali_kelas WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $walikelas = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($walikelas) {
            if ($password === $walikelas['password']) {
                $_SESSION['walikelas_id']       = $walikelas['id'];
                $_SESSION['walikelas_username']  = $walikelas['username'];
                $_SESSION['walikelas_name']      = $walikelas['nama_lengkap'];
                $_SESSION['walikelas_email']     = $walikelas['email'];
                $_SESSION['walikelas_nip']       = $walikelas['nip'];
                $_SESSION['walikelas_kelas']     = $walikelas['kelas'];
                $_SESSION['walikelas_jurusan']   = $walikelas['jurusan'];
                $_SESSION['walikelas_photo']     = $walikelas['foto_profil'];
                $_SESSION['walikelas_last_login'] = $walikelas['last_login'];

                $current_time = date('Y-m-d H:i:s');
                $conn->prepare("UPDATE wali_kelas SET last_login = :t WHERE id = :id")
                    ->execute(['t' => $current_time, 'id' => $walikelas['id']]);

                $_SESSION['walikelas_last_login'] = $current_time;

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
    <title>Login Wali Kelas - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: white;
            -webkit-box-shadow: 0 0 0px 1000px #1F2937 inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        .input-field {
            border-color: rgba(16, 185, 129, 0.3);
        }

        .input-field:focus {
            border-color: #10b981;
            --tw-ring-color: rgba(16, 185, 129, 0.2);
        }
    </style>
</head>

<body class="bg-gray-900 min-h-screen flex items-center justify-center bg-[url('../assets/default/bg-pattern.png')] bg-repeat">
    <!-- Emerald Gradient Overlay -->
    <div class="fixed inset-0 bg-gradient-to-br from-emerald-900/50 to-gray-900/60 pointer-events-none"></div>

    <div class="max-w-md w-full mx-4 relative z-10">

        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <img src="../assets/default/logosmk.png" alt="SMK NURUL ULUM"
                class="h-24 mx-auto mb-4 drop-shadow-[0_0_15px_rgba(16,185,129,0.5)]">
            <h2 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-emerald-600 mb-2">
                Login Wali Kelas
            </h2>
            <p class="text-gray-400">Sistem Absensi SMK NURUL ULUM</p>
        </div>

        <!-- Login Card -->
        <div class="glass-effect rounded-xl shadow-2xl shadow-emerald-900/20 p-8">

            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg mb-6 flex items-center gap-2" role="alert">
                    <i class="fas fa-exclamation-circle shrink-0"></i>
                    <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-4">

                    <!-- Username -->
                    <div>
                        <label class="text-gray-300 text-sm font-medium mb-2 block">
                            <i class="fas fa-chalkboard-teacher text-emerald-500 mr-2"></i>Username
                        </label>
                        <input type="text" name="username" required autofocus
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            class="input-field w-full px-5 py-4 rounded-lg bg-gray-800/80 border text-white
                                   focus:outline-none focus:ring-2 transition-all duration-300 placeholder-gray-500"
                            placeholder="Masukkan username">
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="text-gray-300 text-sm font-medium mb-2 block">
                            <i class="fas fa-lock text-emerald-500 mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                class="input-field w-full px-5 py-4 rounded-lg bg-gray-800/80 border text-white
                                       focus:outline-none focus:ring-2 transition-all duration-300 placeholder-gray-500"
                                placeholder="Masukkan password">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-emerald-400 transition-colors duration-300">
                                <i class="fas fa-eye text-lg" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit"
                    class="w-full bg-gradient-to-r from-emerald-600 to-emerald-800 hover:opacity-90
                           text-white font-medium py-4 px-4 rounded-lg transition duration-300
                           transform hover:-translate-y-0.5 focus:outline-none focus:ring-2
                           focus:ring-emerald-500/30 flex items-center justify-center gap-2
                           shadow-lg shadow-emerald-900/30">
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk
                </button>
            </form>

            <!-- Info kelas badge -->
            <div class="mt-6 flex items-center justify-center gap-2 text-xs text-gray-500">
                <i class="fas fa-info-circle text-emerald-600"></i>
                <span>Akses khusus untuk Wali Kelas</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-400 text-sm">
            <p>&copy; <?= date('Y') ?> SMK NURUL ULUM</p>
            <p class="mt-1 text-gray-500">Sistem Informasi Absensi Siswa</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            const show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
        }
    </script>
</body>

</html>
<?php
session_start();
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
        // Query users + join wali_kelas untuk mendapatkan data kelas
        $stmt = $conn->prepare(
            "SELECT u.*, wk.id AS wk_id, wk.kelas, wk.tingkat, wk.rombel, wk.jurusan
             FROM users u
             LEFT JOIN wali_kelas wk ON wk.user_id = u.id
             WHERE u.username = :username AND u.role = 'wali_kelas'"
        );
        $stmt->execute(['username' => $username]);
        $walikelas = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($walikelas) {
            $valid = password_verify($password, $walikelas['password'])
                || $password === $walikelas['password'];

            if ($valid) {
                // walikelas_id tetap mengacu ke wali_kelas.id agar FK internal tidak rusak
                $_SESSION['walikelas_id']        = $walikelas['wk_id'] ?? $walikelas['id'];
                $_SESSION['walikelas_user_id']   = $walikelas['id'];
                $_SESSION['walikelas_username']  = $walikelas['username'];
                $_SESSION['walikelas_name']      = $walikelas['nama_lengkap'];
                $_SESSION['walikelas_email']     = $walikelas['email'];
                $_SESSION['walikelas_nip']       = $walikelas['nip'];
                $_SESSION['walikelas_kelas']     = $walikelas['kelas'];
                $_SESSION['walikelas_tingkat']   = $walikelas['tingkat'];
                $_SESSION['walikelas_rombel']    = $walikelas['rombel'];
                $_SESSION['walikelas_jurusan']   = $walikelas['jurusan'];
                $_SESSION['walikelas_photo']     = $walikelas['foto_profil'];
                $_SESSION['walikelas_last_login'] = $walikelas['last_login'];
                $_SESSION['role']                = 'wali_kelas';

                $current_time = date('Y-m-d H:i:s');
                // Update last_login di users dan wali_kelas
                $conn->prepare("UPDATE users SET last_login = :t WHERE id = :id")
                    ->execute(['t' => $current_time, 'id' => $walikelas['id']]);
                $conn->prepare("UPDATE wali_kelas SET last_login = :t WHERE user_id = :id")
                    ->execute(['t' => $current_time, 'id' => $walikelas['id']]);

                $_SESSION['walikelas_last_login'] = $current_time;

                // Log activity
                $conn->prepare(
                    "INSERT INTO activity_log (user_type, user_id, activity_type, description)
                     VALUES ('wali_kelas', :uid, 'login', :desc)"
                )->execute([
                    'uid'  => $walikelas['id'],
                    'desc' => "Wali Kelas {$walikelas['nama_lengkap']} login ke sistem"
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
    <title>Login Wali Kelas - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(20, 184, 166, 0.25);
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #1f2937;
            -webkit-box-shadow: 0 0 0px 1000px #fff inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        .input-field {
            border-color: rgba(20, 184, 166, 0.3);
        }

        .input-field:focus {
            border-color: #14b8a6;
            --tw-ring-color: rgba(20, 184, 166, 0.3);
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-teal-50 via-green-50 to-emerald-100 flex items-center justify-center bg-[url('../assets/default/bg-pattern.png')] bg-repeat">
    <!-- Emerald Gradient Overlay -->


    <div class="max-w-md w-full mx-4 relative z-10">

        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <img src="../assets/default/logosmk.png" alt="SMK NURUL ULUM"
                class="h-24 mx-auto mb-4 drop-shadow-[0_0_15px_rgba(20,184,166,0.5)]">
            <h2 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-teal-600 to-emerald-600 mb-2">
                Login Wali Kelas
            </h2>
            <p class="text-gray-500">Sistem Absensi SMK NURUL ULUM</p>
        </div>

        <!-- Login Card -->
        <div class="glass-effect rounded-xl shadow-xl shadow-teal-200 p-8">

            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-300 text-red-400 px-4 py-3 rounded-lg mb-6 flex items-center gap-2" role="alert">
                    <i class="fas fa-exclamation-circle shrink-0"></i>
                    <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-4">

                    <!-- Username -->
                    <div>
                        <label class="text-gray-700 text-sm font-medium mb-2 block">
                            <i class="fas fa-chalkboard-teacher text-teal-600 mr-2"></i>Username
                        </label>
                        <input type="text" name="username" required autofocus
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            class="input-field w-full px-5 py-4 rounded-lg bg-gray-50/80 border text-gray-800
                                   focus:outline-none focus:ring-2 transition-all duration-300 placeholder-gray-400"
                            placeholder="Masukkan username">
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="text-gray-700 text-sm font-medium mb-2 block">
                            <i class="fas fa-lock text-teal-600 mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                class="input-field w-full px-5 py-4 rounded-lg bg-gray-50/80 border text-gray-800
                                       focus:outline-none focus:ring-2 transition-all duration-300 placeholder-gray-400"
                                placeholder="Masukkan password">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-teal-500 transition-colors duration-300">
                                <i class="fas fa-eye text-lg" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit"
                    class="w-full bg-gradient-to-r from-teal-500 to-emerald-600 hover:opacity-90
                           text-white font-medium py-4 px-4 rounded-lg transition duration-300
                           transform hover:-translate-y-0.5 focus:outline-none focus:ring-2
                           focus:ring-teal-500/30 flex items-center justify-center gap-2
                           shadow-lg shadow-teal-200">
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk
                </button>
                <div class="text-center pt-2">
                    <a href="../index.php" class="text-gray-500 hover:text-green-800 text-sm transition-colors duration-200">
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
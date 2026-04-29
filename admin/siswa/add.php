<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nis          = trim($_POST['nis']);
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $kelas        = trim($_POST['kelas']);
        $jurusan      = trim($_POST['jurusan']);
        $email        = trim($_POST['email']);
        $raw_password = !empty($_POST['password']) ? $_POST['password'] : "siswa_$nis";
        $password     = password_hash($raw_password, PASSWORD_BCRYPT);

        if (empty($nis) || empty($nama_lengkap) || empty($kelas) || empty($jurusan) || empty($email)) {
            throw new Exception("Semua field wajib diisi.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid.");
        }

        $conn->beginTransaction();

        // Cek duplikat NIS
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM siswa WHERE nis = :nis");
        $check_stmt->execute(['nis' => $nis]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("NIS sudah digunakan oleh siswa lain.");
        }

        // Cek duplikat email
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $check_stmt->execute(['email' => $email]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Email sudah digunakan.");
        }

        // Cek duplikat username
        $username = strtolower(str_replace(' ', '_', $nama_lengkap));
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $check_stmt->execute(['username' => $username]);
        if ($check_stmt->fetchColumn() > 0) {
            // Tambahkan NIS agar username unik
            $username = $username . '_' . $nis;
        }

        // Handle upload foto profil
        $foto_profil = 'assets/default/photo-profile.png';

        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type     = mime_content_type($_FILES['foto_profil']['tmp_name']);

            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Format file tidak didukung. Gunakan JPG atau PNG.");
            }

            if ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024) {
                throw new Exception("Ukuran file terlalu besar. Maksimum 2MB.");
            }

            $upload_dir = '../../uploads/profile/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
            $filename       = 'profile_' . $nis . '_' . time() . '.' . $file_extension;
            $target_file    = $upload_dir . $filename;

            if (!move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
                throw new Exception("Gagal mengunggah foto profil.");
            }

            $foto_profil = 'uploads/profile/' . $filename;
        }

        // ✅ INSERT ke tabel users (untuk login)
        $sql_user = "INSERT INTO users (username, email, password, nama_lengkap, role, foto_profil, nis, kelas, jurusan) 
                     VALUES (:username, :email, :password, :nama_lengkap, 'siswa', :foto_profil, :nis, :kelas, :jurusan)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->execute([
            'username'     => $username,
            'email'        => $email,
            'password'     => $password,
            'nama_lengkap' => $nama_lengkap,
            'foto_profil'  => $foto_profil,
            'nis'          => $nis,
            'kelas'        => $kelas,
            'jurusan'      => $jurusan,
        ]);
        $user_id = $conn->lastInsertId(); // ✅ Ambil user_id

        // ✅ INSERT ke tabel siswa (dengan user_id)
        $sql = "INSERT INTO siswa (user_id, nis, nama_lengkap, kelas, jurusan, email, password, foto_profil) 
                VALUES (:user_id, :nis, :nama_lengkap, :kelas, :jurusan, :email, :password, :foto_profil)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'user_id'      => $user_id,
            'nis'          => $nis,
            'nama_lengkap' => $nama_lengkap,
            'kelas'        => $kelas,
            'jurusan'      => $jurusan,
            'email'        => $email,
            'password'     => $password,
            'foto_profil'  => $foto_profil
        ]);

        $student_id = $conn->lastInsertId();

        // Log aktivitas
        $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                VALUES ('admin', :admin_id, 'create', :description)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'admin_id'    => $_SESSION['admin_id'],
            'description' => "Admin menambahkan siswa baru: $nama_lengkap ($nis)"
        ]);

        $conn->commit();

        header("Location: detail.php?id=$student_id&created=true");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Siswa - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ede9fe 100%);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(139, 92, 246, 0.15), rgba(139, 92, 246, 0.05));
            border-left: 4px solid #9333ea;
        }

        /* Mobile responsive styles */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        .mobile-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
        }

        /* Hide scrollbar */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Form animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        /* Improved touch targets for mobile */
        @media (max-width: 640px) {
            .touch-target {
                min-height: 44px;
            }

            input,
            select,
            button {
                font-size: 16px;
                /* Prevents iOS zoom on focus */
            }
        }

        /* Fix for iOS full height */
        @supports (-webkit-touch-callout: none) {
            .min-h-screen {
                min-height: -webkit-fill-available;
            }
        }

        /* Profile image responsive styles */
        .profile-upload {
            transition: all 0.2s ease;
        }

        .profile-upload:active {
            transform: scale(0.95);
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">
    <!-- Mobile Overlay - only visible when sidebar is open on mobile -->
    <div id="mobile-overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Side Navigation -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-violet-200 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-violet-200">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 lg:h-10 w-auto">
                <div>
                    <h1 class="font-semibold text-sm lg:text-base text-gray-800">SMK NURUL ULUM</h1>
                    <p class="text-xs text-gray-500">Sistem Absensi</p>
                </div>
            </div>
            <!-- Close sidebar button - only visible on mobile -->
            <button class="text-gray-600 hover:text-gray-800 lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <aside id="sidebar"
            class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-violet-200 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
            <div class="flex items-center justify-between p-4 lg:p-6 border-b border-violet-200">
                <div class="flex items-center gap-3">
                    <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 lg:h-10 w-auto">
                    <div>
                        <h1 class="font-semibold text-sm lg:text-base text-gray-800">SMK NURUL ULUM</h1>
                        <p class="text-xs text-gray-500">Sistem Absensi</p>
                    </div>
                </div>
                <!-- Close sidebar button - only visible on mobile -->
                <button class="text-gray-600 hover:text-gray-800 lg:hidden" onclick="toggleSidebar()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px);">
                <a href="../dashboard/"
                    class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <li class="relative group">
                    <button
                        class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                        <i class="fas fa-calendar-check"></i>
                        <span>Monitoring Siswa</span>
                        <i class="fas fa-chevron-down ml-auto text-sm"></i>
                    </button>

                    <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                        <li>
                            <a href="../absensi/index.php"
                                class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                                Presensi
                            </a>
                        </li>
                        <li>
                            <a href="../absensi/pelanggaran.php"
                                class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                                Pelanggaran
                            </a>
                        </li>
                        <li>
                            <a href="../absensi/konseling.php"
                                class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                                Konseling
                            </a>
                        </li>
                    </ul>
                </li>
                <a href="index.php" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg menu-active">
                    <i class="fas fa-users text-violet-600"></i>
                    <span>Data Siswa</span>
                </a>
                <li class="relative group">
                    <button
                        class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                        <i class="fas fa-file-alt"></i>
                        <span>Laporan</span>
                        <i class="fas fa-chevron-down ml-auto text-sm"></i>
                    </button>

                    <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                        <li>
                            <a href="../laporan/index.php"
                                class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                                Presensi
                            </a>
                        </li>
                        <li>
                            <a href="../laporan/laporan_pelanggaran.php"
                                class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                                Pelanggaran
                            </a>
                        </li>
                        <li>
                            <a href="../laporan/laporan_konseling.php"
                                class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                                Konseling
                            </a>
                        </li>
                    </ul>
                </li>
                <a href="../profil/"
                    class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                    <i class="fas fa-user-cog"></i>
                    <span>Profil</span>
                </a>
                <a href="../logout.php"
                    class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors mt-10">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-sky-50 to-indigo-50 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-white/90 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-800 p-2 -ml-2 rounded-lg hover:bg-gray-100" aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php
                // Use admin photo from session if available
                $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png';
                ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-violet-300">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-4xl mx-auto animate-fade-in">
                <!-- Header with back button - enhanced for mobile -->
                <div class="flex items-center mb-6">
                    <a href="index.php" class="mr-3 p-2 rounded-full hover:bg-gray-100 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Tambah Siswa</h1>
                        <p class="text-sm md:text-base text-gray-500">Tambahkan data siswa baru</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-200 text-red-500 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3"></i>
                        <div>
                            <p class="font-medium">Gagal menambahkan siswa</p>
                            <p class="text-sm text-red-500/80 mt-1"><?= $error ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add Form - Mobile optimized -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Profile Picture - Improved for mobile -->
                        <div class="mb-6 text-center">
                            <div class="relative w-28 h-28 md:w-32 md:h-32 mx-auto">
                                <img id="preview-image" src="../../assets/default/photo-profile.png"
                                    alt="Profile" class="w-28 h-28 md:w-32 md:h-32 object-cover rounded-full border-4 border-gray-800">

                                <label for="foto_profil" class="absolute -bottom-2 -right-2 bg-purple-600 hover:bg-purple-700 rounded-full w-9 h-9 md:w-10 md:h-10 flex items-center justify-center cursor-pointer transition-colors profile-upload">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="foto_profil" name="foto_profil" accept="image/*" class="hidden" onchange="previewImage()">
                            </div>
                            <p class="text-xs md:text-sm text-gray-500 mt-3">Upload foto profil (opsional)</p>
                        </div>

                        <!-- Form fields - Responsive grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <!-- NIS -->
                            <div class="mb-2 md:mb-0">
                                <label for="nis" class="block text-sm text-gray-500 mb-2">NIS</label>
                                <input type="text" id="nis" name="nis" required
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-3 md:py-2 text-gray-800 focus:outline-none focus:border-violet-500 touch-target"
                                    onchange="updateDefaultPassword()">
                                <p class="text-xs text-gray-500 mt-1">Contoh: 2024001</p>
                            </div>

                            <!-- Nama Lengkap -->
                            <div class="mb-2 md:mb-0">
                                <label for="nama_lengkap" class="block text-sm text-gray-500 mb-2">Nama Lengkap</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" required
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-3 md:py-2 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                            </div>

                            <!-- Kelas -->
                            <div class="mb-2 md:mb-0">
                                <label for="kelas" class="block text-sm text-gray-500 mb-2">Kelas</label>
                                <select id="kelas" name="kelas" required
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-3 md:py-2 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                                    <option value="10">10</option>
                                    <option value="11">11</option>
                                    <option value="12">12</option>
                                </select>
                            </div>

                            <!-- Jurusan -->
                            <div class="mb-2 md:mb-0">
                                <label for="jurusan" class="block text-sm text-gray-500 mb-2">Jurusan</label>
                                <select id="jurusan" name="jurusan" required
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-3 md:py-2 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                                    <option value="TKJ">TKJ</option>
                                    <option value="MP">MP</option>
                                    <option value="AKL">AKL</option>
                                    <option value="TSM">TSM</option>
                                    <option value="TKR">TKR</option>
                                </select>
                            </div>

                            <!-- Email -->
                            <div class="mb-2 md:mb-0">
                                <label for="email" class="block text-sm text-gray-500 mb-2">Email</label>
                                <input type="email" id="email" name="email" required
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-3 md:py-2 text-gray-800 focus:outline-none focus:border-violet-500 touch-target"
                                    placeholder="nama@email.com">
                            </div>

                            <!-- Password - Improved for mobile -->
                            <div class="mb-2 md:mb-0">
                                <label for="password" class="block text-sm text-gray-500 mb-2">Password</label>
                                <div class="relative">
                                    <input type="password" id="password" name="password"
                                        placeholder="Kosong = otomatis siswa_[NIS]"
                                        class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-3 md:py-2 pr-10 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                                    <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 w-8 h-8 flex items-center justify-center">
                                        <i class="fas fa-eye" id="password-toggle-icon"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Default: siswa_[NIS]</p>
                            </div>
                        </div>

                        <!-- Submit button - Full width on mobile -->
                        <div class="flex justify-end mt-6 md:mt-8">
                            <button type="submit" class="w-full md:w-auto px-6 py-3 md:py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i> Simpan Data Siswa
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Back button on mobile only -->
                <div class="mt-6 flex justify-center lg:hidden">
                    <a href="index.php" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg flex items-center justify-center text-sm transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Siswa
                    </a>
                </div>
            </div>
        </div>
    </main>
    <script>
        // Image preview functionality with mobile optimizations
        function previewImage() {
            const input = document.getElementById('foto_profil');
            const preview = document.getElementById('preview-image');

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    preview.src = e.target.result;

                    // Add visual feedback for mobile
                    preview.classList.add('scale-[0.98]');
                    setTimeout(() => {
                        preview.classList.remove('scale-[0.98]');
                    }, 200);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        // Toggle password visibility with better mobile touch area
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Update default password placeholder
        function updateDefaultPassword() {
            const nisInput = document.getElementById('nis');
            const passwordInput = document.getElementById('password');

            if (nisInput.value && !passwordInput.value) {
                passwordInput.placeholder = `Kosong = otomatis siswa_${nisInput.value}`;
            }
        }

        // Mobile sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            if (sidebar.classList.contains('-translate-x-full')) {
                // Open sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                // Close sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        // Update time for mobile view
        function updateMobileTime() {
            const mobileTimeElement = document.getElementById('current-time-mobile');
            if (mobileTimeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
                mobileTimeElement.textContent = timeString;
            }
        }

        // Add mobile time updater
        setInterval(updateMobileTime, 60000); // Update every minute
        updateMobileTime(); // Initial call

        // Make sure sidebar closes when pressing escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close sidebar on mobile
                if (window.innerWidth < 1024) {
                    const sidebar = document.getElementById('sidebar');
                    if (!sidebar.classList.contains('-translate-x-full')) {
                        toggleSidebar();
                    }
                }
            }
        });

        // Fix viewport height issues on mobile browsers
        function setMobileHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        window.addEventListener('resize', setMobileHeight);
        setMobileHeight();

        // Add better touch handling for mobile devices
        document.addEventListener('DOMContentLoaded', function() {
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                // Enhance touch areas for mobile
                document.querySelectorAll('input, select, button').forEach(element => {
                    element.classList.add('touch-target');
                });

                // Prevent iOS zoom on input focus
                const viewportMeta = document.querySelector('meta[name="viewport"]');
                if (viewportMeta) {
                    if (/(iPhone|iPad|iPod)/i.test(navigator.userAgent)) {
                        viewportMeta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0';
                    }
                }

                // Improve profile image upload on mobile
                const profileImgContainer = document.querySelector('.profile-upload');
                if (profileImgContainer) {
                    profileImgContainer.addEventListener('touchstart', function() {
                        this.style.transform = 'scale(0.95)';
                    });

                    profileImgContainer.addEventListener('touchend', function() {
                        this.style.transform = 'scale(1)';
                    });
                }
            }
        });
    </script>
</body>

</html>
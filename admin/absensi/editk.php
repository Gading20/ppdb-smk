<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header("Location: konseling.php");
    exit();
}

// Ambil data konseling beserta info siswa
$stmt = $conn->prepare(
    "SELECT k.*, 
            s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil,
            a.username as nama_admin
     FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     LEFT JOIN admin a ON k.created_by = a.id
     WHERE k.id = :id"
);
$stmt->execute(['id' => $id]);
$k = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$k) {
    header("Location: konseling.php");
    exit();
}

// Proses simpan jika form di-submit
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = trim($_POST['tanggal'] ?? '');
    $jenis = trim($_POST['jenis_konseling'] ?? '');
    $konselor = trim($_POST['konselor'] ?? '');
    $masalah = trim($_POST['masalah'] ?? '');
    $solusi = trim($_POST['solusi'] ?? '');
    $tindak_lanjut = trim($_POST['tindak_lanjut'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // Validasi dasar
    if (!$tanggal)
        $errors[] = 'Tanggal wajib diisi.';
    if (!$jenis)
        $errors[] = 'Jenis konseling wajib dipilih.';
    if (!$konselor)
        $errors[] = 'Konselor wajib diisi.';
    if (!$masalah)
        $errors[] = 'Uraian masalah wajib diisi.';
    if (!$status)
        $errors[] = 'Status wajib dipilih.';

    if (empty($errors)) {
        $upd = $conn->prepare(
            "UPDATE konseling SET
                tanggal        = :tanggal,
                jenis_konseling= :jenis,
                konselor       = :konselor,
                masalah        = :masalah,
                solusi         = :solusi,
                tindak_lanjut  = :tindak_lanjut,
                status         = :status,
                updated_at     = NOW()
             WHERE id = :id"
        );
        $upd->execute([
            'tanggal' => $tanggal,
            'jenis' => $jenis,
            'konselor' => $konselor,
            'masalah' => $masalah,
            'solusi' => $solusi,
            'tindak_lanjut' => $tindak_lanjut,
            'status' => $status,
            'id' => $id,
        ]);

        // Refresh data setelah update
        header("Location: konseling.php?success=updated");
        exit();
    }
}

$jenis_list = ['Akademik', 'Pribadi', 'Sosial', 'Karir', 'Keluarga', 'Lainnya'];
$status_list = ['Proses', 'Selesai', 'Ditunda'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Konseling - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(147, 51, 234, 0.2), rgba(147, 51, 234, 0.05));
            border-left: 4px solid #9333ea;
        }

        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.35s ease-out forwards;
        }

        .animate-fade-in-d1 {
            animation: fadeIn 0.35s ease-out 0.05s both;
        }

        .animate-fade-in-d2 {
            animation: fadeIn 0.35s ease-out 0.10s both;
        }

        /* Form inputs */
        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 0.6rem 0.9rem;
            color: #F3F4F6;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-input:focus {
            border-color: rgba(147, 51, 234, 0.6);
            box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.15);
        }

        .form-input option {
            background: #1F2937;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #9CA3AF;
            margin-bottom: 0.4rem;
        }

        /* Jenis badges */
        .badge-akademik {
            background: rgba(139, 92, 246, .15);
            color: #8B5CF6;
            border: 1px solid rgba(139, 92, 246, .3);
        }

        .badge-pribadi {
            background: rgba(236, 72, 153, .15);
            color: #EC4899;
            border: 1px solid rgba(236, 72, 153, .3);
        }

        .badge-sosial {
            background: rgba(16, 185, 129, .15);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .badge-karir {
            background: rgba(234, 179, 8, .15);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, .3);
        }

        .badge-keluarga {
            background: rgba(249, 115, 22, .15);
            color: #F97316;
            border: 1px solid rgba(249, 115, 22, .3);
        }

        .badge-lainnya {
            background: rgba(107, 114, 128, .15);
            color: #6B7280;
            border: 1px solid rgba(107, 114, 128, .3);
        }

        /* Alert */
        .alert-error {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
            color: #FCA5A5;
        }

        .alert-success {
            background: rgba(16, 185, 129, .1);
            border: 1px solid rgba(16, 185, 129, .3);
            color: #6EE7B7;
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar"
        class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-purple-900/30 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 lg:h-10 w-auto">
                <div>
                    <h1 class="font-semibold text-sm lg:text-base">SMK NURUL ULUM</h1>
                    <p class="text-xs text-gray-400">Sistem Absensi</p>
                </div>
            </div>
            <button class="text-gray-400 hover:text-white lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="../dashboard/"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <li class="relative group">
                <button class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active w-full">
                    <i class="fas fa-calendar-check text-purple-500"></i><span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 block">
                    <li><a href="../absensi/index.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a>
                    </li>
                    <li><a href="../absensi/pelanggaran.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a>
                    </li>
                    <li><a href="../absensi/konseling.php"
                            class="block p-2 text-purple-400 bg-purple-500/10 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../siswa/"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-users"></i><span>Data Siswa</span>
            </a>
            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-file-alt"></i><span>Laporan</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block">
                    <li><a href="../laporan/index.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a>
                    </li>
                    <li><a href="../laporan/pelanggaran"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a>
                    </li>
                    <li><a href="../laporan/konseling"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a>
                    </li>
                </ul>
            </li>
            <a href="../profil/"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-user-cog"></i><span>Profil</span>
            </a>
            <a href="../logout.php"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 transition-all duration-300">
        <!-- Mobile Header -->
        <div
            class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="Logo" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile"
                    class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-4xl mx-auto">

                <!-- Breadcrumb -->
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <a href="detailk.php?id=<?= $id ?>"
                            class="p-2 rounded-full hover:bg-gray-800 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div>
                            <h1 class="text-xl md:text-2xl font-bold">Edit Konseling</h1>
                            <p class="text-gray-400 text-sm">ID #<?= $k['id'] ?> &middot;
                                <?= date('d F Y', strtotime($k['tanggal'])) ?></p>
                        </div>
                    </div>
                    <a href="detailk.php?id=<?= $id ?>"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center gap-2 text-sm transition-colors">
                        <i class="fas fa-eye"></i> Lihat Detail
                    </a>
                </div>

                <!-- Alert Error -->
                <?php if (!empty($errors)): ?>
                    <div class="alert-error rounded-xl p-4 mb-4 flex items-start gap-3 animate-fade-in">
                        <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0"></i>
                        <ul class="text-sm space-y-1">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Alert Success -->
                <?php if ($success): ?>
                    <div class="alert-success rounded-xl p-4 mb-4 flex items-center gap-3 animate-fade-in">
                        <i class="fas fa-check-circle flex-shrink-0"></i>
                        <span class="text-sm"><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- ══ KOLOM KIRI: Info Siswa (read-only) ══ -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="glass-effect rounded-xl p-5 text-center animate-fade-in">
                            <div class="relative inline-block mb-3">
                                <img src="../../<?= $k['foto_profil'] ?: 'assets/default/avatar.png' ?>"
                                    alt="Foto Siswa"
                                    class="w-20 h-20 rounded-full object-cover border-2 border-purple-500/50 mx-auto">
                                <span
                                    class="absolute bottom-0 right-0 w-5 h-5 bg-purple-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-graduate text-xs"></i>
                                </span>
                            </div>
                            <h2 class="font-bold text-base"><?= htmlspecialchars($k['nama_lengkap']) ?></h2>
                            <p class="text-gray-400 text-sm"><?= $k['nis'] ?></p>
                            <p class="text-gray-500 text-xs mt-0.5"><?= $k['kelas'] ?> &mdash; <?= $k['jurusan'] ?></p>

                            <div
                                class="mt-4 p-3 rounded-lg bg-yellow-500/10 border border-yellow-500/20 text-xs text-yellow-400 text-left">
                                <i class="fas fa-info-circle mr-1"></i>
                                Data siswa tidak dapat diubah di halaman ini.
                            </div>
                        </div>

                        <!-- Panduan Jenis -->
                        <div class="glass-effect rounded-xl p-4 animate-fade-in-d1">
                            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                <i class="fas fa-tag text-purple-400"></i> Jenis Konseling
                            </h3>
                            <div class="space-y-2 text-xs">
                                <?php
                                $jenis_desc = [
                                    'Akademik' => 'Masalah belajar, nilai, prestasi',
                                    'Pribadi' => 'Masalah personal / psikologis',
                                    'Sosial' => 'Hubungan antar teman / lingkungan',
                                    'Karir' => 'Pilihan karir / masa depan',
                                    'Keluarga' => 'Masalah keluarga / rumah',
                                    'Lainnya' => 'Di luar kategori di atas',
                                ];
                                foreach ($jenis_desc as $j => $desc): ?>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded badge-<?= strtolower($j) ?>"><?= $j ?></span>
                                        <span class="text-gray-500"><?= $desc ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ══ KOLOM KANAN: Form Edit ══ -->
                    <div class="lg:col-span-2">
                        <form method="POST" class="space-y-5">

                            <!-- Baris 1: Tanggal + Jenis -->
                            <div class="glass-effect rounded-xl p-5 animate-fade-in">
                                <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
                                    <i class="fas fa-info-circle text-purple-400"></i> Informasi Dasar
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="form-label"><i class="fas fa-calendar-alt mr-1"></i> Tanggal
                                            Konseling</label>
                                        <input type="date" name="tanggal" class="form-input"
                                            value="<?= htmlspecialchars($_POST['tanggal'] ?? $k['tanggal']) ?>"
                                            required>
                                    </div>
                                    <div>
                                        <label class="form-label"><i class="fas fa-tag mr-1"></i> Jenis
                                            Konseling</label>
                                        <select name="jenis_konseling" class="form-input" required>
                                            <option value="">-- Pilih Jenis --</option>
                                            <?php foreach ($jenis_list as $j): ?>
                                                <option value="<?= $j ?>" <?= (($_POST['jenis_konseling'] ?? $k['jenis_konseling']) === $j) ? 'selected' : '' ?>>
                                                    <?= $j ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label"><i class="fas fa-user-tie mr-1"></i> Konselor</label>
                                        <input type="text" name="konselor" class="form-input"
                                            placeholder="Nama konselor..."
                                            value="<?= htmlspecialchars($_POST['konselor'] ?? $k['konselor']) ?>"
                                            required>
                                    </div>
                                    <div>
                                        <label class="form-label"><i class="fas fa-flag mr-1"></i> Status</label>
                                        <select name="status" class="form-input" required>
                                            <?php foreach ($status_list as $s): ?>
                                                <option value="<?= $s ?>" <?= (($_POST['status'] ?? $k['status']) === $s) ? 'selected' : '' ?>>
                                                    <?= $s ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Masalah -->
                            <div class="glass-effect rounded-xl p-5 animate-fade-in-d1">
                                <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
                                    <span
                                        class="w-5 h-5 rounded bg-red-500/20 text-red-400 flex items-center justify-center text-xs">
                                        <i class="fas fa-exclamation"></i>
                                    </span>
                                    Uraian Masalah <span class="text-red-400 ml-1">*</span>
                                </h3>
                                <textarea name="masalah" rows="5" class="form-input resize-y"
                                    placeholder="Jelaskan masalah yang dialami siswa..."
                                    required><?= htmlspecialchars($_POST['masalah'] ?? $k['masalah']) ?></textarea>
                            </div>

                            <!-- Solusi -->
                            <div class="glass-effect rounded-xl p-5 animate-fade-in-d1">
                                <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
                                    <span
                                        class="w-5 h-5 rounded bg-green-500/20 text-green-400 flex items-center justify-center text-xs">
                                        <i class="fas fa-lightbulb"></i>
                                    </span>
                                    Solusi / Rekomendasi
                                    <span class="text-gray-500 text-xs font-normal">(opsional)</span>
                                </h3>
                                <textarea name="solusi" rows="4" class="form-input resize-y"
                                    placeholder="Tuliskan solusi atau rekomendasi yang diberikan..."><?= htmlspecialchars($_POST['solusi'] ?? $k['solusi']) ?></textarea>
                            </div>

                            <!-- Tindak Lanjut -->
                            <div class="glass-effect rounded-xl p-5 animate-fade-in-d2">
                                <h3 class="font-semibold text-sm mb-4 flex items-center gap-2">
                                    <span
                                        class="w-5 h-5 rounded bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs">
                                        <i class="fas fa-forward"></i>
                                    </span>
                                    Tindak Lanjut
                                    <span class="text-gray-500 text-xs font-normal">(opsional)</span>
                                </h3>
                                <textarea name="tindak_lanjut" rows="4" class="form-input resize-y"
                                    placeholder="Tuliskan tindak lanjut yang akan dilakukan..."><?= htmlspecialchars($_POST['tindak_lanjut'] ?? $k['tindak_lanjut']) ?></textarea>
                            </div>

                            <!-- Tombol Aksi -->
                            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                                <a href="konseling.php?id=<?= $id ?>"
                                    class="px-5 py-2.5 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center gap-2 text-sm transition-colors">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                                <button type="submit"
                                    class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 rounded-lg flex items-center gap-2 text-sm font-semibold transition-colors shadow-lg shadow-purple-900/30">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </div>

                        </form>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Toast -->
    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden">
        <div id="toast-inner" class="px-5 py-3 rounded-lg text-sm font-medium shadow-lg flex items-center gap-2"></div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const isHidden = sidebar.classList.contains('-translate-x-full');
            sidebar.classList.toggle('-translate-x-full', !isHidden);
            overlay.classList.toggle('hidden', !isHidden);
            document.body.classList.toggle('overflow-hidden', isHidden);
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && window.innerWidth < 1024) toggleSidebar();
        });

        function updateMobileTime() {
            const el = document.getElementById('current-time-mobile');
            if (el) el.textContent = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit', minute: '2-digit', hour12: false
            });
        }
        setInterval(updateMobileTime, 60000);
        updateMobileTime();

        // Auto-hide success alert setelah 4 detik
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s';
            successAlert.style.opacity = '0';
            setTimeout(() => successAlert.remove(), 500);
        }, 4000);
    </script>
</body>

</html>
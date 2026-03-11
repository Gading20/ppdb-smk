<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: pelanggaran.php");
    exit();
}

$id = (int) $_GET['id'];
$error = '';

// ── Ambil data pelanggaran ──
$stmt = $conn->prepare(
    "SELECT p.*, s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil
     FROM pelanggaran p
     JOIN siswa s ON p.siswa_id = s.id
     WHERE p.id = :id"
);
$stmt->execute(['id' => $id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    header("Location: pelanggaran.php?error=not_found");
    exit();
}

// ── POST: Simpan perubahan (hanya tanggal & status) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tanggal = $_POST['tanggal'];
        $status  = $_POST['status'];

        if (!$tanggal) throw new Exception("Tanggal wajib diisi.");

        $conn->beginTransaction();

        $conn->prepare(
            "UPDATE pelanggaran
             SET tanggal = :tanggal,
                 status  = :status
             WHERE id = :id"
        )->execute([
            'tanggal' => $tanggal,
            'status'  => $status,
            'id'      => $id,
        ]);

        // Log
        $conn->prepare(
            "INSERT INTO activity_log (user_type, user_id, activity_type, description)
             VALUES ('admin', :admin_id, 'update', :desc)"
        )->execute([
            'admin_id' => $_SESSION['admin_id'],
            'desc'     => "Admin mengedit pelanggaran #{$id} ({$p['jenis_pelanggaran']}) milik {$p['nama_lengkap']}",
        ]);

        $conn->commit();
        header("Location: pelanggaran.php?edited=true");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pelanggaran - SMK NURUL ULUM</title>
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
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }

        .jenis-btn {
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .jenis-btn.active-ringan {
            border-color: #22C55E;
            background: rgba(34, 197, 94, 0.15);
            color: #22C55E;
        }

        .jenis-btn.active-sedang {
            border-color: #EAB308;
            background: rgba(234, 179, 8, 0.15);
            color: #EAB308;
        }

        .jenis-btn.active-berat {
            border-color: #EF4444;
            background: rgba(239, 68, 68, 0.15);
            color: #EF4444;
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
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a></li>
                    <li><a href="../absensi/pelanggaran.php"
                            class="block p-2 text-purple-400 bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../absensi/konseling"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a></li>
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
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a></li>
                    <li><a href="../laporan/pelanggaran"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../laporan/konseling"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a></li>
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

    <!-- Main -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="SMKN 40" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile"
                    class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-3xl mx-auto">

                <!-- Page Header -->
                <div class="flex items-center mb-6">
                    <a href="pelanggaran.php" class="mr-3 p-2 rounded-full hover:bg-gray-800/60 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Edit Pelanggaran</h1>
                        <p class="text-sm text-gray-400">Ubah data pelanggaran siswa</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3 flex-shrink-0"></i>
                        <div>
                            <p class="font-medium">Gagal menyimpan</p>
                            <p class="text-sm mt-1"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Info Siswa -->
                <div class="glass-effect rounded-xl p-4 md:p-5 mb-5 animate-fade-in">
                    <div class="flex items-center gap-4">
                        <img src="../../<?= htmlspecialchars($p['foto_profil'] ?: 'assets/default/avatar.png') ?>"
                            alt="Foto"
                            class="w-14 h-14 rounded-xl object-cover border border-purple-500/30 flex-shrink-0">
                        <div>
                            <p class="font-semibold text-base"><?= htmlspecialchars($p['nama_lengkap']) ?></p>
                            <p class="text-sm text-gray-400"><?= htmlspecialchars($p['nis']) ?> &middot;
                                Kelas <?= $p['kelas'] ?> <?= $p['jurusan'] ?></p>
                        </div>
                        <span class="ml-auto px-3 py-1 rounded-full text-xs font-medium
                            <?= $p['jenis_pelanggaran'] === 'Berat'
                                ? 'bg-red-500/10 text-red-400 border border-red-500/30'
                                : ($p['jenis_pelanggaran'] === 'Sedang'
                                    ? 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/30'
                                    : 'bg-green-500/10 text-green-400 border border-green-500/30') ?>">
                            <?= $p['jenis_pelanggaran'] ?>
                        </span>
                    </div>
                </div>

                <!-- Form Edit -->
                <form method="POST" class="space-y-5">

                    <!-- Jenis + Tanggal + Poin -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 animate-fade-in">
                        <h2 class="font-semibold text-base mb-4 flex items-center gap-2">
                            <i class="fas fa-edit text-purple-400"></i> Informasi Pelanggaran
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <!-- Jenis (read-only) -->
                            <div>
                                <label class="block text-sm text-gray-400 mb-1.5">Jenis Pelanggaran</label>
                                <div class="flex gap-2">
                                    <?php foreach (['Ringan', 'Sedang', 'Berat'] as $j):
                                        $active = $p['jenis_pelanggaran'] === $j ? 'active-' . strtolower($j) : '';
                                    ?>
                                        <span class="jenis-btn flex-1 py-2 px-1 rounded-lg border border-gray-700 text-sm text-center text-gray-400 cursor-not-allowed opacity-80 <?= $active ?>">
                                            <?= $j ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Tanggal (editable) -->
                            <div>
                                <label class="block text-sm text-gray-400 mb-1.5">Tanggal <span class="text-red-400">*</span></label>
                                <input type="date" name="tanggal"
                                    value="<?= htmlspecialchars($p['tanggal']) ?>" required
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500">
                            </div>

                            <!-- Poin (read-only) -->
                            <div>
                                <label class="block text-sm text-gray-400 mb-1.5">Poin</label>
                                <input type="number" value="<?= (int) $p['poin'] ?>" readonly
                                    class="w-full bg-gray-800/30 border border-gray-700 rounded-lg px-3 py-2.5 text-gray-400 cursor-not-allowed">
                            </div>
                        </div>

                        <!-- Deskripsi (read-only) -->
                        <div class="mb-4">
                            <label class="block text-sm text-gray-400 mb-1.5">Deskripsi Pelanggaran</label>
                            <textarea rows="2" readonly
                                class="w-full bg-gray-800/30 border border-gray-700 rounded-lg px-3 py-2.5 text-gray-400 cursor-not-allowed resize-none"><?= htmlspecialchars($p['deskripsi']) ?></textarea>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm text-gray-400 mb-1.5">Status Tindak Lanjut</label>
                            <select name="status"
                                class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500">
                                <option value="Pending" <?= $p['status'] === 'Pending'  ? 'selected' : '' ?>>Pending</option>
                                <option value="Proses" <?= $p['status'] === 'Proses'   ? 'selected' : '' ?>>Proses</option>
                                <option value="Selesai" <?= $p['status'] === 'Selesai'  ? 'selected' : '' ?>>Selesai</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tindakan (read-only) -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 animate-fade-in">
                        <h2 class="font-semibold text-base mb-4 flex items-center gap-2">
                            <i class="fas fa-gavel text-amber-400"></i> Tindakan / Sanksi
                            <span class="ml-auto text-xs text-gray-500 font-normal"><i class="fas fa-lock mr-1"></i>Tidak dapat diubah</span>
                        </h2>
                        <textarea rows="3" readonly
                            placeholder="Belum ada tindakan..."
                            class="w-full bg-gray-800/30 border border-gray-700 rounded-lg px-3 py-2.5 text-gray-400 cursor-not-allowed resize-none"><?= htmlspecialchars($p['tindakan'] ?? '') ?></textarea>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex flex-col sm:flex-row gap-3 justify-end">
                        <a href="pelanggaran.php"
                            class="w-full sm:w-auto px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors font-medium text-center">
                            <i class="fas fa-times mr-2"></i>Batal
                        </a>
                        <a href="detailp.php?id=<?= $id ?>"
                            class="w-full sm:w-auto px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors font-medium text-center">
                            <i class="fas fa-eye mr-2"></i>Lihat Detail
                        </a>
                        <button type="submit"
                            class="w-full sm:w-auto px-6 py-2.5 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors font-medium">
                            <i class="fas fa-save mr-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // ── Pilih jenis pelanggaran ──
        function setJenis(jenis) {
            document.querySelectorAll('.jenis-btn').forEach(btn => {
                btn.classList.remove('active-ringan', 'active-sedang', 'active-berat');
            });
            const btn = document.querySelector(`.jenis-btn[data-jenis="${jenis}"]`);
            if (btn) btn.classList.add('active-' + jenis.toLowerCase());
            document.getElementById('input_jenis').value = jenis;
        }

        // ── Sidebar ──
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        // ── Jam mobile ──
        function updateMobileTime() {
            const el = document.getElementById('current-time-mobile');
            if (el) el.textContent = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }
        setInterval(updateMobileTime, 60000);
        updateMobileTime();

        // ── Escape ──
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && window.innerWidth < 1024) {
                const sidebar = document.getElementById('sidebar');
                if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });
    </script>
</body>

</html>
<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];

// Get detailed pelanggaran information
$sql = "SELECT p.*, s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil, s.email,
               u.username as nama_admin
        FROM pelanggaran p
        JOIN siswa s ON p.siswa_id = s.id
        LEFT JOIN users u ON p.dicatat_oleh = u.id
        WHERE p.id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $id]);
$pelanggaran = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pelanggaran) {
    header("Location: index.php?error=not_found");
    exit();
}

// Update status if requested
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $tindakan   = $_POST['tindakan'] ?? $pelanggaran['tindakan'];

    try {
        $conn->beginTransaction();

        $sql = "UPDATE pelanggaran SET status = :status, tindakan = :tindakan WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['status' => $new_status, 'tindakan' => $tindakan, 'id' => $id]);

        // Log activity
        $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                VALUES ('admin', :admin_id, 'update', :description)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'admin_id'    => $_SESSION['admin_id'],
            'description' => "Admin memperbarui status pelanggaran " . $pelanggaran['jenis_pelanggaran'] . " siswa " . $pelanggaran['nama_lengkap'] . " menjadi " . $new_status
        ]);

        $conn->commit();
        header("Location: pelanggaran.php?updated=true");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Color maps
$jenis_colors  = ['Ringan' => 'green', 'Sedang' => 'yellow', 'Berat' => 'red'];
$status_colors = ['Pending' => 'gray', 'Proses' => 'amber', 'Selesai' => 'green'];

$jenis_color  = $jenis_colors[$pelanggaran['jenis_pelanggaran']]  ?? 'gray';
$status_color = $status_colors[$pelanggaran['status']] ?? 'gray';

// Poin color
$poin = (int) $pelanggaran['poin'];
$poin_color = $poin >= 50 ? 'red' : ($poin >= 25 ? 'orange' : 'yellow');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pelanggaran - SMK NURUL ULUM</title>
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
            animation: fadeIn 0.3s ease forwards;
        }

        /* Poin badge ring animation for berat */
        @keyframes pulse-ring {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .poin-berat {
            animation: pulse-ring 2s infinite;
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">
    <!-- Mobile Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-purple-900/30 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
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
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px);">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <li class="relative group">
                <button class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active w-full">
                    <i class="fas fa-calendar-check text-purple-500"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 block">
                    <li><a href="../absensi/index.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a></li>
                    <li><a href="index.php" class="block p-2 text-purple-400 bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="konseling.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../siswa/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-users"></i><span>Data Siswa</span>
            </a>
            <li class="relative group">
                <button class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-file-alt"></i><span>Laporan</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block">
                    <li><a href="../laporan/index.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a></li>
                    <li><a href="../laporan/laporan_pelanggaran.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../laporan/laporan_konseling.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../profil/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-user-cog"></i><span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-4xl mx-auto">

                <!-- Page Header -->
                <div class="flex items-center mb-6">
                    <a href="../laporan/laporan_pelanggaran.php" class="mr-3 p-2 rounded-full hover:bg-gray-800/60 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Detail Pelanggaran</h1>
                        <p class="text-sm md:text-base text-gray-400">Informasi lengkap pelanggaran siswa</p>
                    </div>
                </div>

                <!-- Success alert -->
                <?php if (isset($_GET['updated'])): ?>
                    <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4 mb-6 flex items-center animate-fade-in">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p>Data pelanggaran berhasil diperbarui.</p>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6 flex items-center animate-fade-in">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Student Info Card -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6 animate-fade-in">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 md:gap-6">
                        <div class="relative">
                            <img src="../../<?= htmlspecialchars($pelanggaran['foto_profil'] ?: 'assets/default/avatar.png') ?>"
                                alt="<?= htmlspecialchars($pelanggaran['nama_lengkap']) ?>"
                                class="w-20 h-20 md:w-24 md:h-24 rounded-xl object-cover border border-purple-500/30">
                            <!-- Jenis indicator badge -->
                            <div class="absolute -bottom-2 -right-2 px-2 py-0.5 rounded-full text-xs font-bold
                                <?= $pelanggaran['jenis_pelanggaran'] === 'Berat' ? 'bg-red-500/80 text-white' : ($pelanggaran['jenis_pelanggaran'] === 'Sedang' ? 'bg-yellow-500/80 text-gray-900' : 'bg-green-500/80 text-white') ?>">
                                <?= $pelanggaran['jenis_pelanggaran'] ?>
                            </div>
                        </div>

                        <div class="flex-grow text-center sm:text-left">
                            <h3 class="text-lg md:text-xl font-bold"><?= htmlspecialchars($pelanggaran['nama_lengkap']) ?></h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-1 mt-2 md:mt-3">
                                <div>
                                    <p class="text-gray-400 text-xs">NIS</p>
                                    <p class="text-sm md:text-base"><?= htmlspecialchars($pelanggaran['nis']) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-xs">Kelas</p>
                                    <p class="text-sm md:text-base"><?= $pelanggaran['kelas'] ?> <?= $pelanggaran['jurusan'] ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400 text-xs">Email</p>
                                    <p class="text-sm md:text-base truncate"><?= htmlspecialchars($pelanggaran['email']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pelanggaran Detail Card -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6 animate-fade-in">
                    <h3 class="font-semibold mb-4 text-base md:text-lg">Informasi Pelanggaran</h3>

                    <!-- Status badges -->
                    <div class="flex flex-wrap gap-2 mb-5">
                        <!-- Jenis badge -->
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs md:text-sm
                            <?= $pelanggaran['jenis_pelanggaran'] === 'Berat' ? 'bg-red-500/10 text-red-400 border border-red-500/30' : ($pelanggaran['jenis_pelanggaran'] === 'Sedang' ? 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/30' : 'bg-green-500/10 text-green-400 border border-green-500/30') ?>">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <?= $pelanggaran['jenis_pelanggaran'] ?>
                        </span>

                        <!-- Status badge -->
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs md:text-sm
                            <?= $pelanggaran['status'] === 'Selesai' ? 'bg-green-500/10 text-green-400 border border-green-500/30' : ($pelanggaran['status'] === 'Proses' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30' : 'bg-gray-500/10 text-gray-400 border border-gray-500/30') ?>">
                            <?php if ($pelanggaran['status'] === 'Selesai'): ?>
                                <i class="fas fa-check-circle mr-2"></i>
                            <?php elseif ($pelanggaran['status'] === 'Proses'): ?>
                                <i class="fas fa-spinner mr-2"></i>
                            <?php else: ?>
                                <i class="fas fa-clock mr-2"></i>
                            <?php endif; ?>
                            <?= $pelanggaran['status'] ?>
                        </span>

                        <!-- Poin badge -->
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs md:text-sm font-bold
                            <?= $pelanggaran['jenis_pelanggaran'] === 'Berat' ? 'bg-red-500/10 text-red-400 border border-red-500/30 poin-berat' : ($pelanggaran['jenis_pelanggaran'] === 'Sedang' ? 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/30' : 'bg-green-500/10 text-green-400 border border-green-500/30') ?>">
                            <i class="fas fa-star mr-2"></i>
                            <?= $poin ?> Poin
                        </span>
                    </div>

                    <!-- Detail grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-6 mb-5">
                        <div>
                            <p class="text-gray-400 text-xs mb-1">Tanggal Kejadian</p>
                            <p class="text-sm md:text-base font-medium"><?= date('d F Y', strtotime($pelanggaran['tanggal'])) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs mb-1">Dicatat Oleh</p>
                            <p class="text-sm md:text-base font-medium"><?= htmlspecialchars($pelanggaran['nama_admin'] ?? 'Admin') ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs mb-1">Dicatat Pada</p>
                            <p class="text-sm md:text-base font-medium"><?= date('d/m/Y H:i', strtotime($pelanggaran['created_at'])) ?></p>
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <div class="mb-4">
                        <p class="text-gray-400 text-xs mb-1">Deskripsi Pelanggaran</p>
                        <div class="bg-gray-800/50 rounded-lg p-3 min-h-[60px]">
                            <p class="text-sm"><?= nl2br(htmlspecialchars($pelanggaran['deskripsi'])) ?></p>
                        </div>
                    </div>

                    <!-- Tindakan -->
                    <div>
                        <p class="text-gray-400 text-xs mb-1">Tindakan / Sanksi</p>
                        <div class="bg-gray-800/50 rounded-lg p-3 min-h-[60px]">
                            <p class="text-sm">
                                <?= $pelanggaran['tindakan']
                                    ? nl2br(htmlspecialchars($pelanggaran['tindakan']))
                                    : '<span class="text-gray-500 italic">Belum ada tindakan</span>' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Update Status Card -->
                <?php if ($pelanggaran['status'] !== 'Selesai'): ?>
                    <div class="glass-effect rounded-xl p-4 md:p-6 mb-6 animate-fade-in border border-amber-500/20">
                        <h3 class="font-semibold mb-4 text-base md:text-lg flex items-center gap-2">
                            <i class="fas fa-edit text-amber-400"></i> Perbarui Status Tindak Lanjut
                        </h3>
                        <form method="POST">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Status</label>
                                    <select name="status" required
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                        <option value="Pending" <?= $pelanggaran['status'] === 'Pending'  ? 'selected' : '' ?>>Pending</option>
                                        <option value="Proses" <?= $pelanggaran['status'] === 'Proses'   ? 'selected' : '' ?>>Proses</option>
                                        <option value="Selesai" <?= $pelanggaran['status'] === 'Selesai'  ? 'selected' : '' ?>>Selesai</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs text-gray-400 mb-1">Tindakan / Sanksi</label>
                                <textarea name="tindakan" rows="2"
                                    placeholder="Isi tindakan yang diberikan..."
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500"><?= htmlspecialchars($pelanggaran['tindakan'] ?? '') ?></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="update_status"
                                    class="px-5 py-2 bg-amber-600 hover:bg-amber-700 rounded-lg text-sm font-medium transition-colors">
                                    <i class="fas fa-save mr-2"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 justify-between animate-fade-in">
                    <div class="flex flex-col sm:flex-row gap-2">
                        <a href="editp.php?id=<?= $id ?>"
                            class="px-4 py-2.5 sm:py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg text-sm font-medium flex items-center justify-center gap-2 transition-colors">
                            <i class="fas fa-edit"></i> Edit Data
                        </a>
                        <button onclick="confirmDelete()"
                            class="px-4 py-2.5 sm:py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium flex items-center justify-center gap-2 transition-colors">
                            <i class="fas fa-trash-alt"></i> Hapus
                        </button>
                    </div>
                    <a href="pelanggaran.php" class="hidden lg:flex px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm items-center gap-2 transition-colors">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                <!-- Back button mobile -->
                <div class="mt-6 flex justify-center lg:hidden">
                    <a href="index.php" class="px-4 py-2.5 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center justify-center text-sm transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Pelanggaran
                    </a>
                </div>

            </div>
        </div>
    </main>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center z-[100] hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideDeleteModal()"></div>
        <div class="glass-effect rounded-lg p-6 w-11/12 max-w-md relative z-10 mx-4 animate-fade-in">
            <h3 class="text-xl font-semibold mb-4">Konfirmasi Hapus</h3>
            <p class="text-gray-300 mb-6">Apakah Anda yakin ingin menghapus data pelanggaran ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end gap-3">
                <button onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm">Batal</button>
                <form method="POST" action="delete.php">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        function confirmDelete() {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const isHidden = sidebar.classList.contains('-translate-x-full');
            sidebar.classList.toggle('-translate-x-full', !isHidden);
            overlay.classList.toggle('hidden', !isHidden);
            document.body.classList.toggle('overflow-hidden', isHidden);
        }

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

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    hideDeleteModal();
                    return;
                }
                if (window.innerWidth < 1024) {
                    const sidebar = document.getElementById('sidebar');
                    if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
                }
            }
        });

        function setMobileHeight() {
            document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
        }
        window.addEventListener('resize', setMobileHeight);
        setMobileHeight();
    </script>
</body>

</html>
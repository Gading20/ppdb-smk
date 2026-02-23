<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get today's statistics
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stats = [
    'hadir' => 0,
    'sakit' => 0,
    'izin' => 0,
    'terlambat' => 0,
    'alpha' => 0
];

// Total pelanggaran
$sql = "SELECT COUNT(*) as total FROM pelanggaran";
$total_pelanggaran = $conn->query($sql)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total poin pelanggaran
$sql = "SELECT SUM(poin) as total_poin FROM pelanggaran";
$total_poin = $conn->query($sql)->fetch(PDO::FETCH_ASSOC)['total_poin'] ?? 0;

// Statistik per jenis pelanggaran
$sql = "SELECT jenis_pelanggaran, COUNT(*) as jumlah 
        FROM pelanggaran 
        GROUP BY jenis_pelanggaran";
$stmt = $conn->query($sql);
$pelanggaranData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$jenis = [];
$jumlah = [];

foreach ($pelanggaranData as $row) {
    $jenis[] = $row['jenis_pelanggaran'];
    $jumlah[] = $row['jumlah'];
}

// Pelanggaran per bulan (6 bulan terakhir)
$sql = "SELECT 
            DATE_FORMAT(tanggal, '%b %Y') as bulan,
            DATE_FORMAT(tanggal, '%Y-%m') as bulan_sort,
            COUNT(*) as jumlah,
            SUM(poin) as total_poin
        FROM pelanggaran
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(tanggal, '%Y-%m'), DATE_FORMAT(tanggal, '%b %Y')
        ORDER BY bulan_sort ASC";
$stmt = $conn->query($sql);
$pelanggaranBulanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bulan_label = array_column($pelanggaranBulanan, 'bulan');
$bulan_jumlah = array_column($pelanggaranBulanan, 'jumlah');
$bulan_poin = array_column($pelanggaranBulanan, 'total_poin');

// Top 5 siswa pelanggaran terbanyak
$sql = "SELECT s.nama_lengkap, s.kelas, COUNT(p.id) as jumlah, SUM(p.poin) as total_poin
        FROM pelanggaran p
        JOIN siswa s ON p.siswa_id = s.id
        GROUP BY p.siswa_id
        ORDER BY total_poin DESC
        LIMIT 5";
$topPelanggar = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get today's counts
$sql = "SELECT status, COUNT(*) as count FROM absensi 
        WHERE tanggal = :today 
        AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['today' => $today]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[strtolower($row['status'])] = $row['count'];
}

// Get yesterday's counts for comparison
$yesterday_stats = [
    'hadir' => 0,
    'sakit' => 0,
    'izin' => 0,
    'terlambat' => 0,
    'alpha' => 0
];

$sql = "SELECT status, COUNT(*) as count FROM absensi 
        WHERE tanggal = :yesterday 
        AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['yesterday' => $yesterday]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $yesterday_stats[strtolower($row['status'])] = $row['count'];
}

// Calculate percentage changes
$percentage_changes = [];
foreach ($stats as $status => $count) {
    if ($yesterday_stats[$status] > 0) {
        $change = (($count - $yesterday_stats[$status]) / $yesterday_stats[$status]) * 100;
        $percentage_changes[$status] = round($change);
    } else if ($count > 0) {
        $percentage_changes[$status] = 100;
    } else {
        $percentage_changes[$status] = 0;
    }
}

// Get weekly statistics
$sql = "SELECT 
            DATE(tanggal) as date,
            MIN(DATE_FORMAT(tanggal, '%d %b')) as date_label,
            status,
            COUNT(*) as count
        FROM absensi
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND approval_status = 'Approved'
        GROUP BY DATE(tanggal), status
        ORDER BY date ASC";
$stmt = $conn->query($sql);
$weeklyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notifications
$sql = "SELECT a.id, s.nama_lengkap, s.foto_profil, a.status, a.created_at, a.bukti_foto, a.keterangan
        FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id
        WHERE a.approval_status = 'Pending'
        ORDER BY a.created_at DESC
        LIMIT 5";
$notifications = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get recent activities
$sql = "SELECT al.*, COALESCE(a.nama_lengkap, s.nama_lengkap, 'System') as user_name,
        COALESCE(a.foto_profil, s.foto_profil, 'assets/default/photo-profile.png') as user_photo,
        DATE_FORMAT(al.created_at, '%H:%i') as time
        FROM activity_log al
        LEFT JOIN admin a ON al.user_type = 'admin' AND al.user_id = a.id
        LEFT JOIN siswa s ON al.user_type = 'siswa' AND al.user_id = s.id
        ORDER BY al.created_at DESC LIMIT 10";
$activities = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get pending approvals count
$sql = "SELECT COUNT(*) as pending FROM absensi WHERE approval_status = 'Pending'";
$pending = $conn->query($sql)->fetch(PDO::FETCH_ASSOC)['pending'];

// Get total students count
$sql = "SELECT COUNT(*) as total FROM siswa";
$total_students = $conn->query($sql)->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Smeknu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(147, 51, 234, 0.2), rgba(147, 51, 234, 0.05));
            border-left: 4px solid #9333ea;
        }

        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.3s ease-out forwards;
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

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, 0.5);
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(147, 51, 234, 0.5);
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(147, 51, 234, 0.7);
        }

        @media (max-width: 768px) {
            .touch-padding {
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            .notification-panel-mobile {
                max-height: 80vh;
                width: 92%;
                margin: 0 auto;
                top: 4rem;
                left: 4%;
                right: 4%;
            }
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">

    <!-- Mobile Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Side Navigation -->
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

        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px);">
            <a href="index.php" class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active">
                <i class="fas fa-home text-purple-500"></i>
                <span>Dashboard</span>
            </a>
            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-calendar-check"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                    <li><a href="../absensi/index.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a>
                    </li>
                    <li><a href="../absensi/pelanggaran.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a>
                    </li>
                    <li><a href="../absensi/konseling.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a>
                    </li>
                </ul>
            </li>

            <a href="../siswa/"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-users"></i>
                <span>Data Siswa</span>
            </a>

            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-file-alt"></i>
                    <span>Laporan</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
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
                <i class="fas fa-user-cog"></i>
                <span>Profil</span>
            </a>

            <hr class="border-gray-700/50 my-4">

            <div class="px-3 py-2">
                <h5 class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-3">Info Cepat</h5>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-400">Total Siswa</span>
                        <span class="font-medium text-white"><?= $total_students ?></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-400">Menunggu Persetujuan</span>
                        <span
                            class="font-medium <?= $pending > 0 ? 'text-yellow-400' : 'text-green-400' ?>"><?= $pending ?></span>
                    </div>
                </div>
            </div>

            <div class="pt-2 mt-auto">
                <a href="../logout.php"
                    class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800">

        <!-- Mobile Header -->
        <div
            class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60"
                    aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="SMKN 40" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <div class="relative">
                    <button onclick="toggleNotifications()" class="p-2 rounded-lg hover:bg-gray-800/60"
                        aria-label="Notifications">
                        <i class="fas fa-bell text-purple-500"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span
                                class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center notification-counter">
                                <?= count($notifications) ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </div>
                <img src="../../<?= $_SESSION['admin_photo'] ?: '../../assets/default/photo-profile.png' ?>" alt="Admin"
                    class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 md:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-6 lg:mb-8">
                    <div class="w-full sm:w-auto mb-4 sm:mb-0">
                        <h1 class="text-xl md:text-2xl font-bold">Dashboard</h1>
                        <p class="text-gray-400">Overview sistem absensi</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="relative hidden lg:block">
                            <button onclick="toggleNotifications()"
                                class="px-4 py-2 rounded-lg glass-effect hover:bg-purple-500/10 transition-colors">
                                <i class="fas fa-bell text-purple-500"></i>
                                <?php if (count($notifications) > 0): ?>
                                    <span
                                        class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center notification-counter">
                                        <?= count($notifications) ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <div class="hidden lg:flex items-center gap-3 px-4 py-2 rounded-lg glass-effect">
                            <img src="../../<?= $_SESSION['admin_photo'] ?: '../../assets/default/photo-profile.png' ?>"
                                alt="Admin" class="h-8 w-8 rounded-full object-cover">
                            <span class="text-sm"><?= $_SESSION['admin_name'] ?></span>
                        </div>
                    </div>
                </header>

                <!-- Notification Panel -->
                <div id="notificationPanel"
                    class="hidden fixed lg:absolute right-0 mt-2 w-[95%] sm:w-96 glass-effect rounded-xl shadow-2xl z-50 notification-panel-mobile lg:w-96 lg:right-0 lg:top-auto">
                    <div class="p-4 border-b border-purple-900/30 flex justify-between items-center">
                        <h3 class="font-semibold">Notifikasi Pending</h3>
                        <div class="notification-badge">
                            <?php if (count($notifications) > 0): ?>
                                <span
                                    class="text-xs bg-red-500/10 text-red-500 px-2 py-1 rounded-full border border-red-500/20">
                                    <span class="notification-count"><?= count($notifications) ?></span> permintaan
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div id="notificationList" class="max-h-[60vh] lg:max-h-[480px] overflow-y-auto custom-scrollbar">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="p-4 border-b border-purple-900/30 hover:bg-purple-500/5 transition-colors notification-item"
                                    data-notif-id="<?= $notif['id'] ?>">
                                    <div class="flex gap-3">
                                        <img src="../../<?= $notif['foto_profil'] ?>"
                                            class="h-10 w-10 rounded-full object-cover"
                                            alt="<?= htmlspecialchars($notif['nama_lengkap']) ?>">
                                        <div class="flex-1">
                                            <p class="font-medium"><?= htmlspecialchars($notif['nama_lengkap']) ?></p>
                                            <p class="text-sm text-gray-400 mt-0.5">
                                                Mengajukan <?= strtolower($notif['status']) ?>
                                                <span class="text-gray-500">•
                                                    <?= date('H:i', strtotime($notif['created_at'])) ?></span>
                                            </p>
                                            <?php if ($notif['keterangan']): ?>
                                                <p class="text-sm text-gray-400 mt-1">
                                                    "<?= htmlspecialchars($notif['keterangan']) ?>"</p>
                                            <?php endif; ?>
                                            <?php if ($notif['bukti_foto']): ?>
                                                <img src="../../<?= $notif['bukti_foto'] ?>"
                                                    class="mt-2 rounded-lg w-full h-32 object-cover" alt="Bukti">
                                            <?php endif; ?>
                                            <div class="flex gap-2 mt-3">
                                                <button onclick="handleAbsence(<?= $notif['id'] ?>, 'approve')"
                                                    class="flex-1 py-1.5 rounded-lg bg-green-500/10 text-green-500 hover:bg-green-500/20 transition-colors text-sm touch-padding">
                                                    <i class="fas fa-check mr-1"></i> Setujui
                                                </button>
                                                <button onclick="handleAbsence(<?= $notif['id'] ?>, 'reject')"
                                                    class="flex-1 py-1.5 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500/20 transition-colors text-sm touch-padding">
                                                    <i class="fas fa-times mr-1"></i> Tolak
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-400 empty-notification">
                                <i class="fas fa-check-circle text-2xl mb-2"></i>
                                <p class="text-sm">Tidak ada notifikasi pending</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 md:gap-6 mb-6 md:mb-8">

                    <!-- Hadir Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-green-800/10 cursor-pointer"
                        data-stat="hadir">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Hadir</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['hadir'] ?></h3>
                            </div>
                            <div
                                class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-green-500/30 to-green-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-check text-green-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['hadir'] > 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-up text-green-400 mr-1"></i><span
                                        class="text-green-400">+<?= abs($percentage_changes['hadir']) ?>% dari
                                        kemarin</span></span>
                            <?php elseif ($percentage_changes['hadir'] < 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-down text-red-400 mr-1"></i><span
                                        class="text-red-400">-<?= abs($percentage_changes['hadir']) ?>% dari
                                        kemarin</span></span>
                            <?php else: ?>
                                <span class="flex items-center"><i class="fas fa-minus text-gray-400 mr-1"></i><span
                                        class="text-gray-400">Sama dengan kemarin</span></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sakit Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-yellow-800/10 cursor-pointer"
                        data-stat="sakit">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Sakit</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['sakit'] ?></h3>
                            </div>
                            <div
                                class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-yellow-500/30 to-yellow-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-hospital text-yellow-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['sakit'] > 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-up text-green-400 mr-1"></i><span
                                        class="text-green-400">+<?= abs($percentage_changes['sakit']) ?>% dari
                                        kemarin</span></span>
                            <?php elseif ($percentage_changes['sakit'] < 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-down text-red-400 mr-1"></i><span
                                        class="text-red-400">-<?= abs($percentage_changes['sakit']) ?>% dari
                                        kemarin</span></span>
                            <?php else: ?>
                                <span class="flex items-center"><i class="fas fa-minus text-gray-400 mr-1"></i><span
                                        class="text-gray-400">Sama dengan kemarin</span></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Izin Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-purple-800/10 cursor-pointer"
                        data-stat="izin">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Izin</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['izin'] ?></h3>
                            </div>
                            <div
                                class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-purple-500/30 to-purple-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-clipboard-list text-purple-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['izin'] > 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-up text-green-400 mr-1"></i><span
                                        class="text-green-400">+<?= abs($percentage_changes['izin']) ?>% dari
                                        kemarin</span></span>
                            <?php elseif ($percentage_changes['izin'] < 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-down text-red-400 mr-1"></i><span
                                        class="text-red-400">-<?= abs($percentage_changes['izin']) ?>% dari
                                        kemarin</span></span>
                            <?php else: ?>
                                <span class="flex items-center"><i class="fas fa-minus text-gray-400 mr-1"></i><span
                                        class="text-gray-400">Sama dengan kemarin</span></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Terlambat Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-orange-800/10 cursor-pointer"
                        data-stat="terlambat">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Terlambat</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['terlambat'] ?></h3>
                            </div>
                            <div
                                class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-orange-500/30 to-orange-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-clock text-orange-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['terlambat'] > 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-up text-green-400 mr-1"></i><span
                                        class="text-green-400">+<?= abs($percentage_changes['terlambat']) ?>% dari
                                        kemarin</span></span>
                            <?php elseif ($percentage_changes['terlambat'] < 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-down text-red-400 mr-1"></i><span
                                        class="text-red-400">-<?= abs($percentage_changes['terlambat']) ?>% dari
                                        kemarin</span></span>
                            <?php else: ?>
                                <span class="flex items-center"><i class="fas fa-minus text-gray-400 mr-1"></i><span
                                        class="text-gray-400">Sama dengan kemarin</span></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Alpha Card -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 hover:bg-purple-900/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-red-800/10 cursor-pointer"
                        data-stat="alpha">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-400 text-sm font-medium mb-1">Total Alpha</p>
                                <h3 class="text-2xl font-bold stat-value"><?= $stats['alpha'] ?></h3>
                            </div>
                            <div
                                class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-gradient-to-br from-red-500/30 to-red-700/30 flex items-center justify-center shadow-md">
                                <i class="fas fa-user-times text-red-400 text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-3 md:mt-4 text-sm stat-change">
                            <?php if ($percentage_changes['alpha'] > 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-up text-green-400 mr-1"></i><span
                                        class="text-green-400">+<?= abs($percentage_changes['alpha']) ?>% dari
                                        kemarin</span></span>
                            <?php elseif ($percentage_changes['alpha'] < 0): ?>
                                <span class="flex items-center"><i class="fas fa-arrow-down text-red-400 mr-1"></i><span
                                        class="text-red-400">-<?= abs($percentage_changes['alpha']) ?>% dari
                                        kemarin</span></span>
                            <?php else: ?>
                                <span class="flex items-center"><i class="fas fa-minus text-gray-400 mr-1"></i><span
                                        class="text-gray-400">Sama dengan kemarin</span></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- ============================================================ -->
                <!-- STATISTIK PELANGGARAN SISWA                                  -->
                <!-- ============================================================ -->
                <div class="mt-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        Statistik Pelanggaran Siswa
                    </h3>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div
                            class="glass-effect rounded-xl p-6 shadow-lg border border-red-900/20 hover:border-red-500/30 transition-all">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-400 text-sm mb-1">Total Pelanggaran</p>
                                    <h2 class="text-3xl font-bold text-red-400"><?= $total_pelanggaran ?></h2>
                                    <p class="text-xs text-gray-500 mt-1">Semua catatan</p>
                                </div>
                                <div class="h-14 w-14 rounded-2xl bg-red-500/10 flex items-center justify-center">
                                    <i class="fas fa-exclamation-triangle text-2xl text-red-500"></i>
                                </div>
                            </div>
                        </div>
                        <div
                            class="glass-effect rounded-xl p-6 shadow-lg border border-yellow-900/20 hover:border-yellow-500/30 transition-all">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-400 text-sm mb-1">Total Poin Pelanggaran</p>
                                    <h2 class="text-3xl font-bold text-yellow-400"><?= $total_poin ?? 0 ?></h2>
                                    <p class="text-xs text-gray-500 mt-1">Akumulasi poin</p>
                                </div>
                                <div class="h-14 w-14 rounded-2xl bg-yellow-500/10 flex items-center justify-center">
                                    <i class="fas fa-chart-line text-2xl text-yellow-500"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

                        <!-- Chart: Pelanggaran per Jenis (Doughnut) -->
                        <div class="glass-effect rounded-xl p-4 md:p-6">
                            <h4 class="text-sm font-semibold text-gray-300 mb-4 flex items-center gap-2">
                                <i class="fas fa-tags text-purple-400"></i> Pelanggaran per Jenis
                            </h4>
                            <div class="relative h-[260px]">
                                <canvas id="chartPelanggaranJenis"></canvas>
                            </div>
                        </div>

                        <!-- Chart: Tren Bulanan (Bar + Line) -->
                        <div class="glass-effect rounded-xl p-4 md:p-6">
                            <h4 class="text-sm font-semibold text-gray-300 mb-4 flex items-center gap-2">
                                <i class="fas fa-calendar-alt text-blue-400"></i> Tren Pelanggaran 6 Bulan Terakhir
                            </h4>
                            <div class="relative h-[260px]">
                                <canvas id="chartPelanggaranBulanan"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Top 5 Pelanggar Table -->
                    <div class="glass-effect rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-red-900/30 to-orange-900/30 p-4 border-b border-gray-800/50">
                            <h4 class="font-semibold flex items-center gap-2">
                                <i class="fas fa-list-ol text-red-400"></i>
                                Top 5 Siswa Pelanggaran Tertinggi
                            </h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-gray-400 text-xs uppercase border-b border-gray-800/50">
                                        <th class="px-4 py-3 text-left">No</th>
                                        <th class="px-4 py-3 text-left">Nama Siswa</th>
                                        <th class="px-4 py-3 text-left">Kelas</th>
                                        <th class="px-4 py-3 text-center">Jumlah</th>
                                        <th class="px-4 py-3 text-center">Total Poin</th>
                                        <th class="px-4 py-3 text-left">Kategori</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/50">
                                    <?php if (!empty($topPelanggar)): ?>
                                        <?php foreach ($topPelanggar as $i => $siswa): ?>
                                            <?php
                                            $poin = $siswa['total_poin'];
                                            if ($poin >= 75) {
                                                $kategori = ['Berat', 'red'];
                                            } elseif ($poin >= 50) {
                                                $kategori = ['Sedang', 'orange'];
                                            } else {
                                                $kategori = ['Ringan', 'yellow'];
                                            }
                                            ?>
                                            <tr class="hover:bg-white/5 transition-colors">
                                                <td class="px-4 py-3 text-gray-400"><?= $i + 1 ?></td>
                                                <td class="px-4 py-3 font-medium">
                                                    <?= htmlspecialchars($siswa['nama_lengkap']) ?>
                                                </td>
                                                <td class="px-4 py-3 text-gray-400">
                                                    <?= htmlspecialchars($siswa['kelas'] ?? '-') ?>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span
                                                        class="px-2 py-1 bg-red-500/10 text-red-400 rounded-full text-xs font-medium">
                                                        <?= $siswa['jumlah'] ?>x
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center font-bold text-yellow-400">
                                                    <?= $siswa['total_poin'] ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span
                                                        class="px-2 py-1 bg-<?= $kategori[1] ?>-500/10 text-<?= $kategori[1] ?>-400 border border-<?= $kategori[1] ?>-500/20 rounded-full text-xs">
                                                        <?= $kategori[0] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                                <i class="fas fa-check-circle text-2xl mb-2 block text-green-500/50"></i>
                                                Belum ada data pelanggaran
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- END STATISTIK PELANGGARAN -->
                <!-- Charts & Activities Grid -->
                <div class="mt-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Weekly Attendance Chart -->
                        <div class="glass-effect rounded-xl p-4 md:p-6 lg:col-span-2">
                            <h3 class="text-lg font-semibold mb-3 md:mb-4">Statistik Kehadiran Mingguan</h3>
                            <div class="relative h-[300px] md:h-[400px]">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        </div>

                        <!-- Recent Activities -->
                        <div class="glass-effect rounded-xl overflow-hidden">
                            <div
                                class="bg-gradient-to-r from-purple-900/40 to-indigo-900/40 p-4 border-b border-gray-800/50">
                                <h3 class="text-lg font-semibold flex items-center">
                                    <i class="fas fa-history text-purple-400 mr-2"></i>
                                    Aktivitas Terkini
                                </h3>
                            </div>
                            <div
                                class="divide-y divide-gray-800/50 max-h-[300px] md:max-h-[440px] overflow-y-auto custom-scrollbar">
                                <?php foreach ($activities as $index => $activity): ?>
                                    <div class="p-4 hover:bg-purple-500/5 transition-colors">
                                        <div class="flex gap-3">
                                            <div class="shrink-0">
                                                <div class="relative">
                                                    <img src="../../<?= $activity['user_photo'] ?>" alt="User"
                                                        class="h-10 w-10 rounded-full object-cover border-2 border-purple-500/20 shadow-md">
                                                    <?php
                                                    $activityTypeIcons = [
                                                        'login' => 'fa-sign-in-alt bg-green-500/20 text-green-400',
                                                        'logout' => 'fa-sign-out-alt bg-orange-500/20 text-orange-400',
                                                        'create' => 'fa-plus bg-blue-500/20 text-blue-400',
                                                        'update' => 'fa-pen bg-yellow-500/20 text-yellow-400',
                                                        'delete' => 'fa-trash bg-red-500/20 text-red-400',
                                                        'approval' => 'fa-check-circle bg-purple-500/20 text-purple-400',
                                                    ];
                                                    $iconClass = $activityTypeIcons[$activity['activity_type']] ?? 'fa-circle bg-gray-500/20 text-gray-400';
                                                    ?>
                                                    <span
                                                        class="absolute -bottom-1 -right-1 rounded-full p-1 <?= explode(' ', $iconClass)[1] ?>">
                                                        <i
                                                            class="fas <?= explode(' ', $iconClass)[0] ?> text-xs <?= explode(' ', $iconClass)[2] ?>"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-start justify-between">
                                                    <p class="text-gray-200 text-sm pr-8">
                                                        <?= htmlspecialchars($activity['description']) ?>
                                                    </p>
                                                    <?php if ($index === 0): ?>
                                                        <span
                                                            class="px-1.5 py-0.5 bg-purple-500/20 text-purple-400 text-xs rounded border border-purple-500/20 ml-2 shrink-0">Baru</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex justify-between items-center mt-2">
                                                    <p class="text-purple-400 text-xs font-medium">
                                                        <?= htmlspecialchars($activity['user_name']) ?>
                                                    </p>
                                                    <div class="flex items-center text-gray-500 text-xs">
                                                        <i class="fas fa-clock mr-1 text-gray-500"></i>
                                                        <?= $activity['time'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($activities)): ?>
                                    <div class="p-8 text-center text-gray-400">
                                        <i class="fas fa-history text-4xl mb-3 opacity-30"></i>
                                        <p>Belum ada aktivitas</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Monthly Statistics -->
                <div class="mt-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <i class="fas fa-chart-bar text-purple-500 mr-2"></i>
                        Statistik Bulanan
                    </h3>
                    <div class="glass-effect rounded-xl p-4 md:p-6">
                        <div class="h-[250px] md:h-[300px] w-full relative">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div><!-- end max-w-7xl -->
        </div><!-- end p-4 -->
    </main>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // ============================================================
        // WEEKLY ATTENDANCE CHART
        // ============================================================
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const weeklyData = <?= json_encode($weeklyStats) ?>;

        function preprocessChartData(data) {
            const dateMap = {};
            if (!data || data.length === 0) {
                return {
                    dates: ['Today'],
                    result: {
                        'Hadir': [0],
                        'Sakit': [0],
                        'Izin': [0],
                        'Terlambat': [0],
                        'Alpha': [0]
                    }
                };
            }
            data.forEach(item => {
                if (!dateMap[item.date_label]) dateMap[item.date_label] = {
                    date: item.date_label
                };
            });
            const dates = Object.keys(dateMap).sort();
            const statuses = ['Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'];
            const result = {};
            statuses.forEach(status => {
                result[status] = dates.map(date => {
                    const match = data.find(item => item.date_label === date && item.status === status);
                    return match ? parseInt(match.count) : 0;
                });
            });
            return {
                dates,
                result
            };
        }

        function initChart() {
            const {
                dates,
                result
            } = preprocessChartData(weeklyData);
            const statusColors = {
                'Hadir': '#10B981',
                'Sakit': '#EAB308',
                'Izin': '#8B5CF6',
                'Terlambat': '#F97316',
                'Alpha': '#EF4444'
            };
            const datasets = [];
            for (const status in result) {
                if (result.hasOwnProperty(status)) {
                    datasets.push({
                        label: status,
                        data: result[status],
                        backgroundColor: statusColors[status] + '20',
                        borderColor: statusColors[status],
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: statusColors[status],
                        pointRadius: 4,
                        pointHoverRadius: 6
                    });
                }
            }
            if (window.attendanceChart instanceof Chart) window.attendanceChart.destroy();
            window.attendanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#9CA3AF',
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17,24,39,0.9)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            borderColor: 'rgba(147,51,234,0.3)',
                            borderWidth: 1,
                            displayColors: true,
                            usePointStyle: true
                        }
                    }
                }
            });
            initMonthlyChart();
        }

        // ============================================================
        // MONTHLY STATISTICS CHART
        // ============================================================
        function initMonthlyChart() {
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            fetch('../api/get_monthly_stats.php')
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.json();
                })
                .then(monthlyStats => {
                    const labels = monthlyStats.map(item => item.month);
                    const data = monthlyStats.map(item => item.percentage);
                    buildMonthlyChart(monthlyCtx, labels, data);
                })
                .catch(() => {
                    buildMonthlyChart(monthlyCtx,
                        ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        [85, 88, 92, 78, 90, 82]
                    );
                });
        }

        function buildMonthlyChart(ctx, labels, data) {
            if (window.monthlyChart instanceof Chart) window.monthlyChart.destroy();
            window.monthlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Kehadiran',
                        data,
                        backgroundColor: 'rgba(147,51,234,0.2)',
                        borderColor: 'rgba(147,51,234,1)',
                        borderWidth: 2,
                        borderRadius: 4,
                        barThickness: 18,
                        maxBarThickness: 30
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 10,
                            right: 20,
                            bottom: 10,
                            left: 10
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                },
                                callback: v => v + '%'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17,24,39,0.9)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            borderColor: 'rgba(147,51,234,0.3)',
                            borderWidth: 1,
                            callbacks: {
                                label: ctx => `Kehadiran: ${ctx.parsed.y}%`
                            }
                        }
                    }
                }
            });
        }

        // ============================================================
        // PELANGGARAN: DOUGHNUT CHART (per Jenis)
        // ============================================================
        (function () {
            const canvas = document.getElementById('chartPelanggaranJenis');
            if (!canvas) return;

            const jenisData = <?= json_encode($jenis) ?>;
            const jumlahData = <?= json_encode($jumlah) ?>;

            if (jenisData.length === 0) {
                canvas.parentElement.innerHTML =
                    '<div class="h-full flex items-center justify-center text-gray-500 text-sm">' +
                    '<i class="fas fa-inbox mr-2"></i>Belum ada data</div>';
                return;
            }

            const colors = ['#EF4444', '#F97316', '#EAB308', '#8B5CF6', '#3B82F6', '#10B981', '#EC4899', '#14B8A6'];

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: jenisData,
                    datasets: [{
                        data: jumlahData,
                        backgroundColor: colors.slice(0, jenisData.length).map(c => c + '30'),
                        borderColor: colors.slice(0, jenisData.length),
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                },
                                padding: 12,
                                usePointStyle: true,
                                pointStyleWidth: 8
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17,24,39,0.95)',
                            titleColor: '#fff',
                            bodyColor: '#D1D5DB',
                            borderColor: 'rgba(147,51,234,0.3)',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function (ctx) {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = ((ctx.parsed / total) * 100).toFixed(1);
                                    return ` ${ctx.parsed} kasus (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        })();

        // ============================================================
        // PELANGGARAN: BAR + LINE CHART (Tren Bulanan)
        // ============================================================
        (function () {
            const canvas = document.getElementById('chartPelanggaranBulanan');
            if (!canvas) return;

            const bulanLabel = <?= json_encode($bulan_label) ?>;
            const bulanJumlah = <?= json_encode($bulan_jumlah) ?>;
            const bulanPoin = <?= json_encode($bulan_poin) ?>;

            if (bulanLabel.length === 0) {
                canvas.parentElement.innerHTML =
                    '<div class="h-full flex items-center justify-center text-gray-500 text-sm">' +
                    '<i class="fas fa-inbox mr-2"></i>Belum ada data</div>';
                return;
            }

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: bulanLabel,
                    datasets: [{
                        label: 'Jumlah Kasus',
                        data: bulanJumlah,
                        backgroundColor: 'rgba(239,68,68,0.2)',
                        borderColor: '#EF4444',
                        borderWidth: 2,
                        borderRadius: 4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Total Poin',
                        data: bulanPoin,
                        type: 'line',
                        backgroundColor: 'rgba(234,179,8,0.1)',
                        borderColor: '#EAB308',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#EAB308',
                        pointRadius: 4,
                        yAxisID: 'y1'
                    }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            grid: {
                                color: 'rgba(255,255,255,0.08)'
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            },
                            title: {
                                display: true,
                                text: 'Kasus',
                                color: '#6B7280',
                                font: {
                                    size: 10
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                color: '#EAB308',
                                font: {
                                    size: 11
                                }
                            },
                            title: {
                                display: true,
                                text: 'Poin',
                                color: '#EAB308',
                                font: {
                                    size: 10
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17,24,39,0.95)',
                            titleColor: '#fff',
                            bodyColor: '#D1D5DB',
                            borderColor: 'rgba(239,68,68,0.3)',
                            borderWidth: 1,
                            padding: 12
                        }
                    }
                }
            });
        })();

        // ============================================================
        // INIT
        // ============================================================
        document.addEventListener('DOMContentLoaded', function () {
            try {
                initChart();
            } catch (e) {
                console.error('Chart error:', e);
            }
        });

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => initChart(), 250);
        });

        // ============================================================
        // SIDEBAR & NOTIFICATIONS
        // ============================================================
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

        function toggleNotifications() {
            const panel = document.getElementById('notificationPanel');
            panel.classList.toggle('hidden');
            if (window.innerWidth < 768) {
                if (!panel.classList.contains('hidden')) document.body.classList.add('overflow-hidden');
                else document.body.classList.remove('overflow-hidden');
            }
        }

        async function handleAbsence(id, action) {
            try {
                const response = await fetch('../api/approve_absence.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id,
                        action
                    })
                });
                const data = await response.json();
                if (response.ok) {
                    showToast(action === 'approve' ? 'Absensi berhasil disetujui' : 'Absensi ditolak', action === 'approve' ? 'success' : 'error');
                    const notifItem = document.querySelector(`.notification-item[data-notif-id="${id}"]`);
                    if (notifItem) {
                        notifItem.style.opacity = '0';
                        notifItem.style.height = notifItem.offsetHeight + 'px';
                        setTimeout(() => {
                            notifItem.style.height = '0';
                            notifItem.style.padding = '0';
                            notifItem.style.margin = '0';
                            notifItem.style.overflow = 'hidden';
                            setTimeout(() => {
                                notifItem.remove();
                                updateNotificationUI(data.remaining);
                            }, 300);
                        }, 300);
                    }
                } else throw new Error(data.message || 'Terjadi kesalahan');
            } catch (error) {
                console.error('Error:', error);
                showToast(error.message || 'Terjadi kesalahan', 'error');
            }
        }

        function updateNotificationUI(remainingCount) {
            const counter = document.querySelector('.notification-counter');
            const badge = document.querySelector('.notification-badge');
            const countSpan = document.querySelector('.notification-count');
            const notificationList = document.getElementById('notificationList');
            const notificationItems = document.querySelectorAll('.notification-item');

            if (remainingCount <= 0 || notificationItems.length === 0) {
                if (counter) counter.remove();
                if (badge) badge.innerHTML = '';
                if (!document.querySelector('.empty-notification')) {
                    notificationList.innerHTML = `
                        <div class="p-8 text-center text-gray-400 empty-notification">
                            <i class="fas fa-check-circle text-2xl mb-2"></i>
                            <p class="text-sm">Tidak ada notifikasi pending</p>
                        </div>`;
                }
            } else {
                if (counter) counter.textContent = remainingCount;
                if (countSpan) countSpan.textContent = remainingCount;
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg glass-effect border border-${type === 'success' ? 'green' : 'red'}-500/30 text-white z-50 animate-fade-in-up`;
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'times'} text-${type === 'success' ? 'green' : 'red'}-500 mr-2"></i>${message}`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'opacity 0.3s, transform 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        document.addEventListener('click', function (event) {
            const panel = document.getElementById('notificationPanel');
            if (panel && !panel.classList.contains('hidden')) {
                const bellBtns = document.querySelectorAll('button[onclick="toggleNotifications()"]');
                let clickedOnButton = false;
                bellBtns.forEach(btn => {
                    if (btn.contains(event.target)) clickedOnButton = true;
                });
                if (!panel.contains(event.target) && !clickedOnButton) {
                    panel.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                }
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const notificationPanel = document.getElementById('notificationPanel');
                if (notificationPanel && !notificationPanel.classList.contains('hidden')) {
                    notificationPanel.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                    return;
                }
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth < 1024 && !sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });

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

        function setMobileHeight() {
            document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
        }
        window.addEventListener('resize', setMobileHeight);
        setMobileHeight();

        function refreshDashboardData() {
            fetch('../api/dashboard_data.php')
                .then(r => r.json())
                .then(data => {
                    if (data.stats) {
                        Object.keys(data.stats).forEach(key => {
                            const card = document.querySelector(`[data-stat="${key}"] .stat-value`);
                            if (card) card.textContent = data.stats[key];
                        });
                    }
                    if (data.pending_count !== undefined) updateNotificationUI(data.pending_count);
                })
                .catch(e => console.error('Refresh error:', e));
        }
        setInterval(refreshDashboardData, 5 * 60 * 1000);
    </script>
</body>

</html>
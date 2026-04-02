<?php
session_start();
require_once '../../config/database.php';

// Guard: hanya wali kelas
if (!isset($_SESSION['walikelas_id'])) {
    header("Location: ../../wali_kelas/login.php");
    exit();
}

$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Kelas & jurusan dari session
$kelas   = $_SESSION['walikelas_kelas'];
$jurusan = $_SESSION['walikelas_jurusan'];
// Tingkat kelas di tabel siswa (10/11/12) — beda dengan kolom 'kelas' di wali_kelas
$tingkat = $_SESSION['walikelas_tingkat'] ?? $kelas;

// ── Statistik absensi hari ini (kelas sendiri) ─────────────────────────────
$stats = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'terlambat' => 0, 'alpha' => 0];

$stmt = $conn->prepare(
    "SELECT a.status, COUNT(*) as count FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :today AND a.approval_status = 'Approved'
       AND s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY a.status"
);
$stmt->execute(['today' => $today, 'tingkat' => $tingkat, 'jurusan' => $jurusan]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[strtolower($row['status'])] = $row['count'];
}

// ── Statistik kemarin (perbandingan) ───────────────────────────────────────
$yesterday_stats = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'terlambat' => 0, 'alpha' => 0];

$stmt = $conn->prepare(
    "SELECT a.status, COUNT(*) as count FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :yesterday AND a.approval_status = 'Approved'
       AND s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY a.status"
);
$stmt->execute(['yesterday' => $yesterday, 'tingkat' => $tingkat, 'jurusan' => $jurusan]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $yesterday_stats[strtolower($row['status'])] = $row['count'];
}

$pct = [];
foreach ($stats as $k => $v) {
    $pct[$k] = $yesterday_stats[$k] > 0
        ? round((($v - $yesterday_stats[$k]) / $yesterday_stats[$k]) * 100)
        : ($v > 0 ? 100 : 0);
}

// ── Total siswa di kelas ini ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM siswa WHERE kelas = :tingkat AND jurusan = :jurusan");
$stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$total_students = $stmt->fetchColumn();

// ── Absensi hari ini (detail kelas sendiri) ────────────────────────────────
$stmt = $conn->prepare(
    "SELECT a.*, s.nama_lengkap, s.kelas, s.jurusan, s.nis, s.foto_profil
     FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :today AND s.kelas = :tingkat AND s.jurusan = :jurusan
     ORDER BY a.created_at DESC"
);
$stmt->execute(['today' => $today, 'tingkat' => $tingkat, 'jurusan' => $jurusan]);
$absensi_hari_ini = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Weekly stats (kelas sendiri) ───────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT DATE(a.tanggal) as date,
            MIN(DATE_FORMAT(a.tanggal,'%d %b')) as date_label,
            a.status, COUNT(*) as count
     FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
       AND a.approval_status = 'Approved'
       AND s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY DATE(a.tanggal), a.status
     ORDER BY date ASC"
);
$stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$weeklyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Top pelanggaran (kelas sendiri) ───────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT s.nama_lengkap, s.kelas, COUNT(p.id) as jumlah, SUM(p.poin) as total_poin
     FROM pelanggaran p
     JOIN siswa s ON p.siswa_id = s.id
     WHERE s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY p.siswa_id
     ORDER BY total_poin DESC
     LIMIT 5"
);
$stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$topPelanggar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Ringkasan Konseling (kelas sendiri) ────────────────────────────────────
$konseling_status = ['Proses' => 0, 'Selesai' => 0, 'Ditunda' => 0];
$stmt = $conn->prepare(
    "SELECT k.status, COUNT(*) as c FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY k.status"
);
$stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($konseling_status[$r['status']])) $konseling_status[$r['status']] = (int)$r['c'];
}
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :tingkat AND s.jurusan = :jurusan"
);
$stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$konseling_total = (int)$stmt->fetchColumn();

// ── Konseling terbaru (kelas sendiri) ─────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT k.id, k.tanggal, k.jenis_konseling, k.masalah, k.status, k.konselor,
            s.nama_lengkap, s.nis, s.foto_profil
     FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :tingkat AND s.jurusan = :jurusan
     ORDER BY k.tanggal DESC, k.id DESC
     LIMIT 5"
);
$stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$recentKonseling = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Rekap absensi bulan ini (kelas sendiri) ────────────────────────────────
$bulan_ini = date('Y-m');
$stmt = $conn->prepare(
    "SELECT a.status, COUNT(*) as count FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE DATE_FORMAT(a.tanggal, '%Y-%m') = :bulan
       AND a.approval_status = 'Approved'
       AND s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY a.status"
);
$stmt->execute(['bulan' => $bulan_ini, 'tingkat' => $tingkat, 'jurusan' => $jurusan]);
$stats_bulan = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'terlambat' => 0, 'alpha' => 0];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats_bulan[strtolower($row['status'])] = (int)$row['count'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Wali Kelas – SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass {
            background: rgba(17, 24, 39, .72);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(16, 185, 129, .25);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(16, 185, 129, .18), rgba(16, 185, 129, .04));
            border-left: 4px solid #10b981;
        }

        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ede9fe 100%);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, .4);
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, .45);
            border-radius: 3px;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .fade-up {
            animation: fadeUp .35s ease-out both;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">

    <div id="overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 bg-white border-r border-teal-200 z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 shadow-sm">

        <div class="flex items-center justify-between p-5 border-b border-teal-200">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" class="h-10 w-auto" alt="Logo">
                <div>
                    <p class="font-semibold text-sm leading-tight text-gray-800">SMK NURUL ULUM</p>
                    <p class="text-xs text-teal-500">
                        Wali Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                    </p>
                </div>
            </div>

            <button class="lg:hidden text-gray-500 hover:text-gray-800" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="p-4 space-y-1 overflow-y-auto custom-scrollbar" style="max-height:calc(100vh - 76px)">

            <!-- Active -->
            <a href="index.php"
                class="flex items-center gap-3 p-3 rounded-lg bg-teal-100 text-teal-700 border border-teal-200">
                <i class="fas fa-home text-teal-500"></i>
                <span>Dashboard</span>
            </a>

            <div>
                <button onclick="toggleMenu(this)"
                    class="flex items-center gap-3 w-full p-3 rounded-lg text-gray-700 hover:bg-teal-50 transition-colors">

                    <i class="fas fa-calendar-check text-teal-500"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-xs rotate-icon"></i>
                </button>

                <ul class="ml-8 mt-1 sub-menu space-y-1">
                    <li>
                        <a href="presensi.php"
                            class="block p-2 text-gray-600 hover:text-teal-600 hover:bg-teal-50 rounded-lg text-sm">
                            Presensi
                        </a>
                    </li>
                    <li>
                        <a href="pelanggaran.php"
                            class="block p-2 text-gray-600 hover:text-teal-600 hover:bg-teal-50 rounded-lg text-sm">
                            Pelanggaran
                        </a>
                    </li>
                    <li>
                        <a href="konseling.php"
                            class="block p-2 text-gray-600 hover:text-teal-600 hover:bg-teal-50 rounded-lg text-sm">
                            Konseling
                        </a>
                    </li>
                </ul>
            </div>

            <a href="../../wali_kelas/logout.php"
                class="flex items-center gap-3 p-3 rounded-lg text-gray-500 hover:bg-red-100 hover:text-red-500 transition-colors">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>

        </nav>
    </aside>

    <!-- ── MAIN ─────────────────────────────────────────────────────────────── -->
    <main class="lg:ml-64 min-h-screen">

        <!-- Mobile topbar -->
        <div class="lg:hidden bg-white/90 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-800 p-2 -ml-2 rounded-lg hover:bg-gray-100" aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <img src="../../<?= $_SESSION['siswa_photo'] ?? 'assets/default/photo-profile.png' ?>"
                    alt="Profile"
                    class="h-8 w-8 rounded-full object-cover border border-violet-300">
            </div>
        </div>

        <div class="p-5 md:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-8 fade-up">
                    <div>
                        <h1 class="text-2xl font-bold">Selamat Datang, <?= htmlspecialchars($_SESSION['walikelas_name']) ?> 👋</h1>
                        <p class="text-gray-500 text-sm mt-1">
                            <i class="fas fa-chalkboard text-emerald-400 mr-1"></i>
                            Wali Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?> &nbsp;|&nbsp;
                            <i class="fas fa-calendar-alt text-emerald-400 mr-1"></i>
                            <?= date('l, d F Y') ?> &nbsp;|&nbsp;
                            <span id="clock" class="text-emerald-300 font-medium"></span>
                        </p>
                    </div>
                    <div class="hidden lg:flex items-center gap-3 px-4 py-2 bg-white border border-gray-200 rounded-xl shadow-sm mt-3 lg:mt-0">

                        <img src="../../<?= $_SESSION['walikelas_photo'] ?: 'assets/default/photo-profile.png' ?>"
                            class="h-9 w-9 rounded-full object-cover border border-teal-400/50" alt="Foto">

                        <div class="text-sm">
                            <p class="font-medium text-gray-800">
                                <?= htmlspecialchars($_SESSION['walikelas_name']) ?>
                            </p>
                            <p class="text-teal-500 text-xs">
                                Wali Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                            </p>
                        </div>

                    </div>
                </header>

                <!-- ── STAT CARDS ─────────────────────────────────────────────────── -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
                    <?php
                    $cards = [
                        ['label' => 'Hadir',     'key' => 'hadir',     'icon' => 'fa-check',      'color' => 'teal'],
                        ['label' => 'Sakit',     'key' => 'sakit',     'icon' => 'fa-hospital',   'color' => 'yellow'],
                        ['label' => 'Izin',      'key' => 'izin',      'icon' => 'fa-clipboard',  'color' => 'blue'],
                        ['label' => 'Terlambat', 'key' => 'terlambat', 'icon' => 'fa-clock',      'color' => 'orange'],
                        ['label' => 'Alpha',     'key' => 'alpha',     'icon' => 'fa-user-times', 'color' => 'red'],
                    ];

                    foreach ($cards as $i => $c):
                        $change = $pct[$c['key']];
                    ?>
                        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm hover:shadow-md hover:scale-[1.02] transition-all duration-300 cursor-default fade-up"
                            style="animation-delay:<?= $i * 0.06 ?>s">

                            <div class="flex justify-between items-start mb-3">
                                <p class="text-gray-500 text-xs font-medium"><?= $c['label'] ?></p>

                                <div class="h-8 w-8 rounded-lg bg-<?= $c['color'] ?>-100 flex items-center justify-center">
                                    <i class="fas <?= $c['icon'] ?> text-<?= $c['color'] ?>-600 text-sm"></i>
                                </div>
                            </div>

                            <p class="text-2xl font-bold text-gray-800">
                                <?= $stats[$c['key']] ?>
                            </p>

                            <p class="text-xs mt-1 
                <?= $change > 0 ? 'text-teal-600' : ($change < 0 ? 'text-red-500' : 'text-gray-500') ?>">

                                <?php if ($change > 0): ?>
                                    <i class="fas fa-arrow-up mr-1"></i>+<?= $change ?>%
                                <?php elseif ($change < 0): ?>
                                    <i class="fas fa-arrow-down mr-1"></i><?= $change ?>%
                                <?php else: ?>
                                    <i class="fas fa-minus mr-1"></i>Sama kemarin
                                <?php endif; ?>

                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── REKAP BULAN INI + CHART ─────────────────────────────────────── -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

                    <!-- Weekly Chart -->
                    <div class="bg-white rounded-xl p-5 lg:col-span-2 shadow-sm border border-gray-200 fade-up" style="animation-delay:.15s">
                        <h3 class="font-semibold mb-4 flex items-center gap-2 text-gray-800">
                            <i class="fas fa-chart-line text-teal-500"></i>
                            Statistik Kehadiran Mingguan
                            <span class="text-xs text-gray-500 font-normal ml-1">
                                – Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                            </span>
                        </h3>
                        <div class="relative h-72">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>

                    <!-- Rekap Bulan Ini -->
                    <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200 fade-up" style="animation-delay:.2s">

                        <div class="bg-gradient-to-r from-teal-100 to-cyan-100 px-5 py-4 border-b border-gray-200">
                            <h3 class="font-semibold flex items-center gap-2 text-sm text-gray-800">
                                <i class="fas fa-calendar-alt text-teal-500"></i>
                                Rekap Absensi Bulan Ini
                                <span class="text-xs text-gray-500 font-normal ml-1"><?= date('F Y') ?></span>
                            </h3>
                        </div>

                        <div class="p-4 space-y-3">
                            <?php
                            $bulanCards = [
                                ['Hadir',     'hadir',     'teal',   'fa-check-circle'],
                                ['Sakit',     'sakit',     'yellow', 'fa-hospital'],
                                ['Izin',      'izin',      'blue',   'fa-clipboard'],
                                ['Terlambat', 'terlambat', 'orange', 'fa-clock'],
                                ['Alpha',     'alpha',     'red',    'fa-user-times'],
                            ];

                            foreach ($bulanCards as [$lbl, $key, $col, $ico]):
                                $total_bulan = array_sum($stats_bulan);
                                $pct_bulan = $total_bulan > 0 ? round($stats_bulan[$key] / $total_bulan * 100) : 0;
                            ?>
                                <div class="flex items-center gap-3">

                                    <div class="h-7 w-7 rounded-md bg-<?= $col ?>-100 flex items-center justify-center shrink-0">
                                        <i class="fas <?= $ico ?> text-<?= $col ?>-600 text-xs"></i>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-600"><?= $lbl ?></span>
                                            <span class="text-xs font-bold text-gray-800"><?= $stats_bulan[$key] ?></span>
                                        </div>

                                        <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-<?= $col ?>-500 rounded-full"
                                                style="width:<?= $pct_bulan ?>%">
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="px-5 py-3 border-t border-gray-200">
                            <a href="presensi.php"
                                class="text-xs text-teal-600 hover:text-teal-700 flex items-center gap-1">
                                <i class="fas fa-arrow-right"></i> Lihat semua presensi
                            </a>
                        </div>
                    </div>

                </div>
                <!-- ── TOP PELANGGAR + RINGKASAN KONSELING ────────────────────────────── -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

                    <!-- Top Pelanggar -->
                    <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200 fade-up" style="animation-delay:.22s">

                        <div class="bg-gradient-to-r from-red-100 to-orange-100 px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="font-semibold flex items-center gap-2 text-sm text-gray-800">
                                <i class="fas fa-exclamation-triangle text-red-500"></i>
                                Top 5 Pelanggar
                                <span class="text-xs text-gray-500 font-normal">
                                    – Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                                </span>
                            </h3>

                            <a href="pelanggaran.php" class="text-xs text-red-500 hover:text-red-600">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="divide-y divide-gray-200">
                            <?php if (!empty($topPelanggar)): ?>
                                <?php foreach ($topPelanggar as $i => $s):
                                    $poin = $s['total_poin'];
                                    $cat  = $poin >= 75 ? ['Berat', 'red'] : ($poin >= 50 ? ['Sedang', 'orange'] : ['Ringan', 'yellow']);
                                ?>
                                    <div class="px-4 py-3 flex items-center gap-3 hover:bg-gray-50 transition-colors">
                                        <span class="text-xs font-bold text-gray-500 w-4"><?= $i + 1 ?></span>

                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-800 truncate">
                                                <?= htmlspecialchars($s['nama_lengkap']) ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <?= $s['jumlah'] ?> pelanggaran
                                            </p>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <p class="text-sm font-bold text-teal-600">
                                                <?= $s['total_poin'] ?> poin
                                            </p>
                                            <span class="text-xs px-2 py-0.5 rounded-full 
                                bg-<?= $cat[1] ?>-100 
                                text-<?= $cat[1] ?>-600 
                                border border-<?= $cat[1] ?>-200">
                                                <?= $cat[0] ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-8 text-center text-gray-500 text-sm">
                                    <i class="fas fa-check-circle text-2xl text-teal-400 mb-2 block"></i>
                                    Belum ada data pelanggaran
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Ringkasan Konseling -->
                    <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200 fade-up" style="animation-delay:.25s">

                        <div class="bg-gradient-to-r from-blue-100 to-indigo-100 px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="font-semibold flex items-center gap-2 text-sm text-gray-800">
                                <i class="fas fa-comments text-teal-500"></i>
                                Ringkasan Konseling
                                <span class="text-xs text-gray-500 font-normal">
                                    – Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                                </span>
                            </h3>

                            <a href="konseling.php" class="text-xs text-teal-600 hover:text-teal-700">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <!-- Stat mini -->
                        <div class="grid grid-cols-4 divide-x divide-gray-200 border-b border-gray-200">
                            <?php
                            $kCards = [
                                ['Total',   $konseling_total,              'purple',  'fa-clipboard-list'],
                                ['Proses',  $konseling_status['Proses'],   'blue',    'fa-spinner'],
                                ['Selesai', $konseling_status['Selesai'],  'teal',    'fa-check-circle'],
                                ['Ditunda', $konseling_status['Ditunda'],  'red',     'fa-pause-circle'],
                            ];

                            foreach ($kCards as [$lbl, $val, $col, $ico]):
                            ?>
                                <div class="p-3 text-center">
                                    <i class="fas <?= $ico ?> text-<?= $col ?>-500 text-xs mb-1 block"></i>
                                    <p class="text-lg font-bold text-gray-800"><?= $val ?></p>
                                    <p class="text-xs text-gray-500"><?= $lbl ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Recent konseling -->
                        <div class="divide-y divide-gray-200">
                            <?php if (!empty($recentKonseling)): ?>
                                <?php
                                $statusIcons = ['Proses' => '⏳', 'Selesai' => '✅', 'Ditunda' => '⏸'];
                                $jColors = [
                                    'Akademik' => 'purple',
                                    'Pribadi' => 'pink',
                                    'Sosial' => 'teal',
                                    'Karir' => 'yellow',
                                    'Keluarga' => 'orange',
                                    'Lainnya' => 'gray'
                                ];

                                foreach ($recentKonseling as $kRow):
                                    $sIcon = $statusIcons[$kRow['status']] ?? '';
                                    $jCol  = $jColors[$kRow['jenis_konseling']] ?? 'gray';
                                ?>
                                    <div class="px-4 py-3 flex items-start gap-3 hover:bg-gray-50 transition-colors">

                                        <img src="../../<?= $kRow['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                            class="h-8 w-8 rounded-full object-cover border border-gray-300 shrink-0 mt-0.5" alt="">

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <p class="text-sm font-medium text-gray-800 truncate">
                                                    <?= htmlspecialchars($kRow['nama_lengkap']) ?>
                                                </p>

                                                <span class="text-xs px-1.5 py-0.5 rounded-full 
                                    bg-<?= $jCol ?>-100 
                                    text-<?= $jCol ?>-600 
                                    border border-<?= $jCol ?>-200">
                                                    <?= htmlspecialchars($kRow['jenis_konseling']) ?>
                                                </span>
                                            </div>

                                            <p class="text-xs text-gray-500 truncate" title="<?= htmlspecialchars($kRow['masalah']) ?>">
                                                <?= htmlspecialchars($kRow['masalah']) ?>
                                            </p>

                                            <p class="text-xs text-gray-600 mt-0.5">
                                                <?= date('d M Y', strtotime($kRow['tanggal'])) ?> · <?= htmlspecialchars($kRow['konselor']) ?>
                                            </p>
                                        </div>

                                        <span class="text-sm shrink-0"><?= $sIcon ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-8 text-center text-gray-500 text-sm">
                                    <i class="fas fa-comments text-2xl text-teal-400 mb-2 block"></i>
                                    Belum ada data konseling
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- ── TABEL ABSENSI HARI INI ──────────────────────────────────────── -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200 fade-up" style="animation-delay:.25s">

                    <div class="bg-gradient-to-r from-teal-100 to-cyan-100 px-5 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3">
                        <h3 class="font-semibold flex items-center gap-2 text-gray-800">
                            <i class="fas fa-clipboard-list text-teal-500"></i>
                            Absensi Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?> Hari Ini
                            <span class="text-xs text-gray-500 font-normal">(<?= date('d F Y') ?>)</span>
                        </h3>

                        <div class="flex gap-2 flex-wrap">
                            <?php foreach (['Semua', 'Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'] as $f): ?>
                                <button onclick="filterTable('<?= $f ?>')"
                                    class="filter-btn text-xs px-3 py-1.5 rounded-full border transition-colors
                    <?= $f === 'Semua'
                                    ? 'bg-teal-100 border-teal-300 text-teal-600'
                                    : 'border-gray-300 text-gray-600 hover:border-teal-400 hover:text-gray-800 hover:bg-teal-50' ?>"
                                    data-filter="<?= $f ?>">
                                    <?= $f ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="absensiTable">
                            <thead>
                                <tr class="text-gray-500 text-xs uppercase border-b border-gray-200 bg-gray-50">
                                    <th class="px-4 py-3 text-left">Siswa</th>
                                    <th class="px-4 py-3 text-left">NIS</th>
                                    <th class="px-4 py-3 text-left">Jam Masuk</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-center">Approval</th>
                                    <th class="px-4 py-3 text-left">Keterangan</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200">
                                <?php if (!empty($absensi_hari_ini)): ?>
                                    <?php foreach ($absensi_hari_ini as $row):
                                        $statusColor = match (strtolower($row['status'])) {
                                            'hadir'     => 'teal',
                                            'sakit'     => 'yellow',
                                            'izin'      => 'blue',
                                            'terlambat' => 'orange',
                                            default     => 'red'
                                        };

                                        $approvalColor = match (strtolower($row['approval_status'] ?? '')) {
                                            'approved' => 'teal',
                                            'rejected' => 'red',
                                            default    => 'yellow'
                                        };
                                    ?>

                                        <tr class="hover:bg-teal-50 transition-colors absensi-row" data-status="<?= htmlspecialchars($row['status']) ?>">

                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= $row['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                                        class="h-8 w-8 rounded-full object-cover border border-gray-300" alt="">
                                                    <span class="font-medium text-gray-800">
                                                        <?= htmlspecialchars($row['nama_lengkap']) ?>
                                                    </span>
                                                </div>
                                            </td>

                                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($row['nis']) ?></td>

                                            <td class="px-4 py-3 text-gray-700">
                                                <?= (!empty($row['jam_masuk']) && $row['jam_masuk'] !== '00:00:00')
                                                    ? date('H:i', strtotime($row['jam_masuk']))
                                                    : '-' ?>
                                            </td>

                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                bg-<?= $statusColor ?>-100
                                text-<?= $statusColor ?>-600
                                border border-<?= $statusColor ?>-200">
                                                    <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>

                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-1 rounded-full text-xs
                                bg-<?= $approvalColor ?>-100
                                text-<?= $approvalColor ?>-600">
                                                    <?= htmlspecialchars($row['approval_status'] ?? 'Pending') ?>
                                                </span>
                                            </td>

                                            <td class="px-4 py-3 text-gray-600 max-w-[160px] truncate">
                                                <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                                            <i class="fas fa-inbox text-3xl mb-3 block opacity-40"></i>
                                            Belum ada data absensi kelas ini hari ini
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </div>
    </main>

    <script>
        // Clock
        function tick() {
            const el = document.getElementById('clock');
            if (el) el.textContent = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }
        setInterval(tick, 1000);
        tick();

        // Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        }

        // Accordion
        function toggleMenu(btn) {
            const ul = btn.nextElementSibling;
            const ico = btn.querySelector('.rotate-icon');
            ul.classList.toggle('hidden');
            ico.style.transform = ul.classList.contains('hidden') ? '' : 'rotate(180deg)';
        }
        document.querySelectorAll('.sub-menu').forEach(ul => {
            ul.classList.remove('hidden');
            const ico = ul.previousElementSibling.querySelector('.rotate-icon');
            if (ico) ico.style.transform = 'rotate(180deg)';
        });

        // Filter table
        function filterTable(status) {
            document.querySelectorAll('.filter-btn').forEach(b => {
                const active = b.dataset.filter === status;
                b.classList.toggle('bg-emerald-500/20', active);
                b.classList.toggle('border-emerald-500/50', active);
                b.classList.toggle('text-emerald-300', active);
                b.classList.toggle('border-gray-300', !active);
                b.classList.toggle('text-gray-500', !active);
            });
            document.querySelectorAll('.absensi-row').forEach(row => {
                row.style.display = (status === 'Semua' || row.dataset.status === status) ? '' : 'none';
            });
        }

        // Weekly Chart
        (function() {
            const raw = <?= json_encode($weeklyStats) ?>;
            const dateLabels = [...new Set(raw.map(r => r.date_label))].sort();
            const statuses = {
                'Hadir': '#10B981',
                'Sakit': '#EAB308',
                'Izin': '#3B82F6',
                'Terlambat': '#F97316',
                'Alpha': '#EF4444'
            };
            const datasets = Object.entries(statuses).map(([s, c]) => ({
                label: s,
                data: dateLabels.map(d => {
                    const m = raw.find(r => r.date_label === d && r.status === s);
                    return m ? +m.count : 0;
                }),
                borderColor: c,
                backgroundColor: c + '22',
                tension: .4,
                fill: true,
                pointBackgroundColor: c,
                pointRadius: 4,
                pointHoverRadius: 6
            }));

            new Chart(document.getElementById('weeklyChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: dateLabels.length ? dateLabels : ['—'],
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
                                color: 'rgba(255,255,255,.08)'
                            },
                            ticks: {
                                color: '#6b7280',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255,255,255,.08)'
                            },
                            ticks: {
                                color: '#6b7280',
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
                                color: '#6b7280',
                                usePointStyle: true,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17,24,39,.92)',
                            titleColor: '#1f2937',
                            bodyColor: '#6b7280',
                            borderColor: 'rgba(16,185,129,.3)',
                            borderWidth: 1,
                            padding: 12
                        }
                    }
                }
            });
        })();
    </script>
</body>

</html>
<?php
session_start();
require_once '../../config/database.php';

// Guard: hanya wali kelas
if (!isset($_SESSION['walikelas_id'])) {
    header("Location: ../../wali_kelas/login.php");
    exit();
}

// Kelas & jurusan dari session (tidak bisa diubah user)
$kelas   = $_SESSION['walikelas_kelas'];
$jurusan = $_SESSION['walikelas_jurusan'];
// Tingkat kelas (10/11/12) yang cocok dengan kolom kelas di tabel siswa
$tingkat = $_SESSION['walikelas_tingkat'] ?? $kelas;

// ── Filter ─────────────────────────────────────────────────────────────────
$date_filter     = $_GET['date']     ?? date('Y-m-d');
$status_filter   = $_GET['status']   ?? '';
$approval_filter = $_GET['approval'] ?? '';
$search          = $_GET['search']   ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────
$items_per_page = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// ── Base SQL (selalu filter kelas sendiri) ─────────────────────────────────
$base = "FROM absensi a JOIN siswa s ON a.siswa_id = s.id
         WHERE s.kelas = :tingkat AND s.jurusan = :jurusan";
$params = ['tingkat' => $tingkat, 'jurusan' => $jurusan];

if ($date_filter) {
    $base .= " AND a.tanggal = :date";
    $params['date'] = $date_filter;
}
if ($status_filter) {
    $base .= " AND a.status = :status";
    $params['status'] = $status_filter;
}
if ($approval_filter) {
    $base .= " AND a.approval_status = :approval";
    $params['approval'] = $approval_filter;
}
if ($search) {
    $base .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $params['search'] = "%$search%";
}

// ── Sort ───────────────────────────────────────────────────────────────────
$valid_cols  = ['nis', 'nama_lengkap', 'kelas', 'tanggal', 'jam_masuk', 'status', 'approval_status'];
$sort_col    = in_array($_GET['sort'] ?? '', $valid_cols) ? $_GET['sort'] : 'tanggal';
$sort_order  = ($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$sort_prefix = in_array($sort_col, ['nama_lengkap', 'nis', 'kelas']) ? 's.' : 'a.';

// ── Count ──────────────────────────────────────────────────────────────────
$count_stmt = $conn->prepare("SELECT COUNT(*) $base");
foreach ($params as $k => $v) $count_stmt->bindValue(":$k", $v);
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_items / $items_per_page));

// ── Data ───────────────────────────────────────────────────────────────────
$sql = "SELECT a.id, a.tanggal, a.jam_masuk, a.status, a.approval_status, a.keterangan,
               s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil
        $base
        ORDER BY {$sort_prefix}{$sort_col} {$sort_order}";
if ($sort_col !== 'tanggal') $sql .= ", a.tanggal DESC";
$sql .= " LIMIT :offset, :limit";

$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$absensi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Total siswa di kelas ini ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM siswa WHERE kelas = :tingkat AND jurusan = :jurusan");
$stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$total_students = $stmt->fetchColumn();

// ── Stat cards (kelas sendiri, hari ini) ───────────────────────────────────
$today = date('Y-m-d');
$status_counts = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Terlambat' => 0, 'Alpha' => 0];
$sc = $conn->prepare(
    "SELECT a.status, COUNT(*) as c FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :t AND a.approval_status = 'Approved'
       AND s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY a.status"
);
$sc->execute(['t' => $today, 'tingkat' => $tingkat, 'jurusan' => $jurusan]);
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) $status_counts[$r['status']] = $r['c'];

// ── Helpers ────────────────────────────────────────────────────────────────
function buildSortUrl($col)
{
    $p = $_GET;
    $p['sort']  = $col;
    $p['order'] = (isset($_GET['sort']) && $_GET['sort'] === $col && ($_GET['order'] ?? '') === 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query($p);
}
function sortIcon($col, $sc, $so)
{
    if ($col !== $sc) return '<i class="fas fa-sort text-gray-400 opacity-50"></i>';
    return $so === 'ASC' ? '<i class="fas fa-sort-up text-teal-500"></i>' : '<i class="fas fa-sort-down text-teal-500"></i>';
}
function pageUrl($pg)
{
    $p = $_GET;
    $p['page'] = $pg;
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Presensi – Wali Kelas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
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
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-up {
            animation: fadeUp .3s ease-out both;
        }

        /* Mobile topbar glass */
        .glass-mobile {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(20, 184, 166, 0.25);
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">

    <div id="overlay" class="fixed inset-0 bg-black/20 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
    <aside id="sidebar"
        class="fixed top-0 left-0 h-screen w-64 bg-white border-r border-teal-200 z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0 shadow-sm">

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

            <a href="../dashboard/index.php"
                class="flex items-center gap-3 p-3 rounded-lg text-gray-600 hover:bg-teal-50 hover:text-teal-700 transition-colors">
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
                <ul class="ml-8 mt-1 space-y-1 sub-menu">
                    <li>
                        <a href="presensi.php"
                            class="block p-2 text-teal-600 bg-teal-50 border-l-2 border-teal-500 rounded-lg text-sm font-medium">
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
        <div class="lg:hidden sticky top-0 z-30 glass-mobile px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-700 p-2 -ml-2 rounded-lg hover:bg-teal-50">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="text-sm font-medium text-gray-800">
                    Presensi Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                </span>
            </div>
            <img src="../../<?= $_SESSION['walikelas_photo'] ?: 'assets/default/photo-profile.png' ?>"
                class="h-8 w-8 rounded-full object-cover border border-teal-400/50" alt="">
        </div>

        <div class="p-5 md:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-8 fade-up">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-clipboard-list text-teal-500"></i>
                            Data Presensi Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">
                            <i class="fas fa-eye text-teal-400 mr-1"></i>
                            Monitoring kehadiran siswa – hanya lihat
                        </p>
                    </div>
                    <div class="mt-3 lg:mt-0 flex items-center gap-3 px-4 py-2 bg-white border border-gray-200 rounded-xl shadow-sm">
                        <img src="../../<?= $_SESSION['walikelas_photo'] ?: 'assets/default/photo-profile.png' ?>"
                            class="h-9 w-9 rounded-full object-cover border border-teal-400/50" alt="">
                        <div class="text-sm">
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($_SESSION['walikelas_name']) ?></p>
                            <p class="text-teal-500 text-xs">Wali Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?></p>
                        </div>
                    </div>
                </header>

                <!-- Stat cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
                    <?php
                    $cards = [
                        ['Hadir',     'hadir',     'teal',   'fa-check',      0],
                        ['Sakit',     'sakit',     'yellow', 'fa-hospital',   0.06],
                        ['Izin',      'izin',      'blue',   'fa-clipboard',  0.12],
                        ['Terlambat', 'terlambat', 'orange', 'fa-clock',      0.18],
                        ['Alpha',     'alpha',     'red',    'fa-user-times', 0.24],
                    ];
                    foreach ($cards as [$lbl, $key, $col, $ico, $delay]): ?>
                        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm hover:shadow-md hover:scale-[1.02] transition-all duration-300 cursor-default fade-up"
                            style="animation-delay:<?= $delay ?>s">
                            <div class="flex justify-between items-start mb-3">
                                <p class="text-gray-500 text-xs font-medium"><?= $lbl ?></p>
                                <div class="h-8 w-8 rounded-lg bg-<?= $col ?>-100 flex items-center justify-center">
                                    <i class="fas <?= $ico ?> text-<?= $col ?>-600 text-sm"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-gray-800"><?= $status_counts[$lbl] ?></p>
                            <p class="text-xs mt-1 text-gray-400">Hari ini</p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Filter -->
                <div class="bg-white rounded-xl p-5 mb-6 shadow-sm border border-gray-200 fade-up" style="animation-delay:.1s">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fas fa-filter text-teal-500"></i>
                            Filter &amp; Pencarian
                        </h3>
                        <?php if (!empty(array_filter([$search, $status_filter, $approval_filter])) || isset($_GET['date'])): ?>
                            <a href="presensi.php"
                                class="text-xs text-teal-600 hover:text-teal-700 flex items-center gap-1">
                                <i class="fas fa-times-circle"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                            <!-- Tanggal -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Tanggal</label>
                                <input type="date" name="date" value="<?= $date_filter ?>"
                                    class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 transition-colors">
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Status Kehadiran</label>
                                <select name="status"
                                    class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 transition-colors">
                                    <option value="">Semua Status</option>
                                    <?php foreach (['Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Approval -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Status Approval</label>
                                <select name="approval"
                                    class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 transition-colors">
                                    <option value="">Semua</option>
                                    <?php foreach (['Pending', 'Approved', 'Rejected'] as $a): ?>
                                        <option value="<?= $a ?>" <?= $approval_filter === $a ? 'selected' : '' ?>><?= $a ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Search -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Cari Siswa</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama atau NIS..."
                                        class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 transition-colors">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit"
                                class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2 shadow-sm">
                                <i class="fas fa-filter"></i> Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200 fade-up" style="animation-delay:.15s">

                    <!-- Table header bar -->
                    <div class="bg-gradient-to-r from-teal-100 to-cyan-100 px-5 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fas fa-table text-teal-500"></i>
                            Daftar Presensi
                            <span class="text-xs text-gray-500 font-normal">
                                – <?= $total_items ?> data ditemukan
                            </span>
                        </h3>
                        <span class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-teal-200 rounded-full text-xs text-teal-600 shadow-sm">
                            <i class="fas fa-eye text-teal-500"></i> Mode Lihat Saja
                        </span>
                    </div>

                    <?php if (!empty($absensi_list)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full whitespace-nowrap text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase border-b border-gray-200">
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('nis') ?>"
                                                class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                NIS <?= sortIcon('nis', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('nama_lengkap') ?>"
                                                class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                Nama <?= sortIcon('nama_lengkap', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('tanggal') ?>"
                                                class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                Tanggal <?= sortIcon('tanggal', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('jam_masuk') ?>"
                                                class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                Jam <?= sortIcon('jam_masuk', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-center">
                                            <a href="<?= buildSortUrl('status') ?>"
                                                class="flex items-center justify-center gap-1 hover:text-teal-600 transition-colors">
                                                Status <?= sortIcon('status', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-center">Approval</th>
                                        <th class="px-5 py-3 text-left">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($absensi_list as $row):
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
                                        <tr class="hover:bg-teal-50 transition-colors">
                                            <td class="px-5 py-3 text-gray-500 text-sm">
                                                <?= htmlspecialchars($row['nis']) ?>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= $row['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                                        class="h-8 w-8 rounded-full object-cover border border-gray-300 shrink-0" alt="">
                                                    <span class="font-medium text-gray-800">
                                                        <?= htmlspecialchars($row['nama_lengkap']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-gray-600">
                                                <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                                            </td>
                                            <td class="px-5 py-3 text-gray-600">
                                                <?= (!empty($row['jam_masuk']) && $row['jam_masuk'] !== '00:00:00')
                                                    ? date('H:i', strtotime($row['jam_masuk']))
                                                    : '-' ?>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                                    bg-<?= $statusColor ?>-100
                                                    text-<?= $statusColor ?>-600
                                                    border border-<?= $statusColor ?>-200">
                                                    <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <span class="px-2.5 py-1 rounded-full text-xs
                                                    bg-<?= $approvalColor ?>-100
                                                    text-<?= $approvalColor ?>-600">
                                                    <?= htmlspecialchars($row['approval_status'] ?? 'Pending') ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-gray-500 max-w-[160px] truncate text-sm">
                                                <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="px-5 py-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-3 bg-gray-50/50">
                            <p class="text-xs text-gray-500">
                                Menampilkan
                                <span class="font-semibold text-gray-700"><?= min($offset + 1, $total_items) ?></span>–<span class="font-semibold text-gray-700"><?= min($offset + $items_per_page, $total_items) ?></span>
                                dari <span class="font-semibold text-gray-700"><?= $total_items ?></span> data
                            </p>
                            <div class="flex gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= pageUrl(1) ?>"
                                        class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-teal-50 hover:border-teal-300 text-gray-500 hover:text-teal-600 transition-colors text-xs">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="<?= pageUrl($page - 1) ?>"
                                        class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-teal-50 hover:border-teal-300 text-gray-500 hover:text-teal-600 transition-colors text-xs">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $s = max(1, $page - 2);
                                $e = min($total_pages, $page + 2);
                                if ($s > 1) echo '<span class="px-2 py-1.5 text-gray-400 text-xs">…</span>';
                                for ($i = $s; $i <= $e; $i++) {
                                    if ($i == $page) {
                                        echo "<span class='px-3 py-1.5 bg-teal-600 text-white rounded-lg text-xs font-medium'>$i</span>";
                                    } else {
                                        echo "<a href='" . pageUrl($i) . "' class='px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-teal-50 hover:border-teal-300 text-gray-500 hover:text-teal-600 transition-colors text-xs'>$i</a>";
                                    }
                                }
                                if ($e < $total_pages) echo '<span class="px-2 py-1.5 text-gray-400 text-xs">…</span>';
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= pageUrl($page + 1) ?>"
                                        class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-teal-50 hover:border-teal-300 text-gray-500 hover:text-teal-600 transition-colors text-xs">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="<?= pageUrl($total_pages) ?>"
                                        class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-teal-50 hover:border-teal-300 text-gray-500 hover:text-teal-600 transition-colors text-xs">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="py-16 text-center text-gray-500">
                            <i class="fas fa-calendar-day text-4xl mb-4 block text-gray-300"></i>
                            <p class="text-gray-600 font-medium">Tidak ada data absensi kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?> yang ditemukan</p>
                            <p class="text-sm text-gray-400 mt-1">Coba ubah filter atau pilih tanggal lain</p>
                            <?php if (!empty(array_filter([$search, $status_filter, $approval_filter])) || isset($_GET['date'])): ?>
                                <a href="presensi.php"
                                    class="mt-4 inline-flex items-center gap-2 text-teal-600 hover:text-teal-700 text-sm font-medium">
                                    <i class="fas fa-arrow-left"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div><!-- /table card -->

            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        }

        function toggleMenu(btn) {
            const ul = btn.nextElementSibling;
            const ico = btn.querySelector('.rotate-icon');
            ul.classList.toggle('hidden');
            ico.style.transform = ul.classList.contains('hidden') ? '' : 'rotate(180deg)';
        }

        // Buka sub-menu secara default
        document.querySelectorAll('.sub-menu').forEach(ul => {
            ul.classList.remove('hidden');
            const ico = ul.previousElementSibling.querySelector('.rotate-icon');
            if (ico) ico.style.transform = 'rotate(180deg)';
        });

        // Auto-submit on select/date change
        document.querySelectorAll('#filterForm select, #filterForm input[type="date"]')
            .forEach(el => el.addEventListener('change', () => document.getElementById('filterForm').submit()));
    </script>
</body>

</html>
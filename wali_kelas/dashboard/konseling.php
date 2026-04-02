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

// ── Filter ─────────────────────────────────────────────────────────────────
$date_filter   = $_GET['date']   ?? '';
$jenis_filter  = $_GET['jenis']  ?? '';
$status_filter = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────
$items_per_page = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// ── Base SQL (selalu filter kelas sendiri) ─────────────────────────────────
$base   = "FROM konseling k JOIN siswa s ON k.siswa_id = s.id
           WHERE s.kelas = :kelas AND s.jurusan = :jurusan";
$params = ['kelas' => $kelas, 'jurusan' => $jurusan];

if ($date_filter) {
    $base .= " AND k.tanggal = :date";
    $params['date']   = $date_filter;
}
if ($jenis_filter) {
    $base .= " AND k.jenis_konseling = :jenis";
    $params['jenis']  = $jenis_filter;
}
if ($status_filter) {
    $base .= " AND k.status = :status";
    $params['status'] = $status_filter;
}
if ($search) {
    $base .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search OR k.konselor LIKE :search)";
    $params['search'] = "%$search%";
}

// ── Sort ───────────────────────────────────────────────────────────────────
$valid_cols  = ['nis', 'nama_lengkap', 'kelas', 'tanggal', 'jenis_konseling', 'konselor', 'status'];
$sort_col    = in_array($_GET['sort'] ?? '', $valid_cols) ? $_GET['sort'] : 'tanggal';
$sort_order  = ($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$sort_prefix = in_array($sort_col, ['nama_lengkap', 'nis', 'kelas']) ? 's.' : 'k.';

// ── Count ──────────────────────────────────────────────────────────────────
$count_stmt = $conn->prepare("SELECT COUNT(*) $base");
foreach ($params as $k => $v) $count_stmt->bindValue(":$k", $v);
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_items / $items_per_page));

// ── Data ───────────────────────────────────────────────────────────────────
$sql = "SELECT k.id, k.tanggal, k.jenis_konseling, k.masalah, k.solusi,
               k.tindak_lanjut, k.konselor, k.status, k.created_at,
               s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil
        $base
        ORDER BY {$sort_prefix}{$sort_col} {$sort_order}";
if ($sort_col !== 'tanggal') $sql .= ", k.tanggal DESC";
$sql .= " LIMIT :offset, :limit";

$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$konseling_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Total siswa di kelas ini ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM siswa WHERE kelas = :kelas AND jurusan = :jurusan");
$stmt->execute(['kelas' => $kelas, 'jurusan' => $jurusan]);
$total_students = $stmt->fetchColumn();

// ── Stat cards ────────────────────────────────────────────────────────────
$status_counts = ['Proses' => 0, 'Selesai' => 0, 'Ditunda' => 0];
$sc = $conn->prepare(
    "SELECT k.status, COUNT(*) as c FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :kelas AND s.jurusan = :jurusan
     GROUP BY k.status"
);
$sc->execute(['kelas' => $kelas, 'jurusan' => $jurusan]);
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($status_counts[$r['status']])) $status_counts[$r['status']] = $r['c'];
}

$total_stmt = $conn->prepare(
    "SELECT COUNT(*) FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :kelas AND s.jurusan = :jurusan"
);
$total_stmt->execute(['kelas' => $kelas, 'jurusan' => $jurusan]);
$total_count = $total_stmt->fetchColumn();

$jenis_options = ['Akademik', 'Pribadi', 'Sosial', 'Karir', 'Keluarga', 'Lainnya'];

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
    <title>Data Konseling – Wali Kelas</title>
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
            background: rgba(20, 184, 166, .45);
            border-radius: 3px;
        }

        /* Status badge */
        .s-proses {
            background: rgba(59, 130, 246, .1);
            color: #2563eb;
            border: 1px solid rgba(59, 130, 246, .3);
        }

        .s-selesai {
            background: rgba(20, 184, 166, .1);
            color: #0d9488;
            border: 1px solid rgba(20, 184, 166, .3);
        }

        .s-ditunda {
            background: rgba(239, 68, 68, .1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, .3);
        }

        /* Jenis badge */
        .j-akademik {
            background: rgba(139, 92, 246, .1);
            color: #7c3aed;
            border: 1px solid rgba(139, 92, 246, .3);
        }

        .j-pribadi {
            background: rgba(236, 72, 153, .1);
            color: #be185d;
            border: 1px solid rgba(236, 72, 153, .3);
        }

        .j-sosial {
            background: rgba(20, 184, 166, .1);
            color: #0d9488;
            border: 1px solid rgba(20, 184, 166, .3);
        }

        .j-karir {
            background: rgba(234, 179, 8, .1);
            color: #b45309;
            border: 1px solid rgba(234, 179, 8, .3);
        }

        .j-keluarga {
            background: rgba(249, 115, 22, .1);
            color: #c2410c;
            border: 1px solid rgba(249, 115, 22, .3);
        }

        .j-lainnya {
            background: rgba(107, 114, 128, .1);
            color: #4b5563;
            border: 1px solid rgba(107, 114, 128, .3);
        }

        /* Mobile topbar glass */
        .glass-mobile {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(20, 184, 166, 0.25);
        }

        .truncate-cell {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
                    <p class="text-xs text-teal-500">Wali Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?></p>
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
                            class="block p-2 text-teal-600 bg-teal-50 border-l-2 border-teal-500 rounded-lg text-sm font-medium">
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
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-comments text-teal-500"></i>
                            Konseling Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">
                            <i class="fas fa-eye text-teal-400 mr-1"></i>
                            Monitoring konseling siswa – hanya lihat
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
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                    <?php
                    $cards = [
                        ['Total',   $total_count,              'purple', 'fa-clipboard-list', 0],
                        ['Proses',  $status_counts['Proses'],  'blue',   'fa-spinner',        0.06],
                        ['Selesai', $status_counts['Selesai'], 'teal',   'fa-check-circle',   0.12],
                        ['Ditunda', $status_counts['Ditunda'], 'red',    'fa-pause-circle',   0.18],
                    ];
                    foreach ($cards as [$lbl, $val, $col, $ico, $delay]): ?>
                        <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm hover:shadow-md hover:scale-[1.02] transition-all duration-300 cursor-default fade-up"
                            style="animation-delay:<?= $delay ?>s">
                            <div class="flex justify-between items-start mb-3">
                                <p class="text-gray-500 text-xs font-medium"><?= $lbl ?></p>
                                <div class="h-8 w-8 rounded-lg bg-<?= $col ?>-100 flex items-center justify-center">
                                    <i class="fas <?= $ico ?> text-<?= $col ?>-600 text-sm"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-gray-800"><?= $val ?></p>
                            <p class="text-xs mt-1 text-gray-400">
                                <?= $lbl === 'Total' ? 'Semua sesi' : 'Sesi ' . strtolower($lbl) ?>
                            </p>
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
                        <?php if (!empty(array_filter([$search, $jenis_filter, $status_filter, $date_filter]))): ?>
                            <a href="konseling.php"
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

                            <!-- Jenis -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Jenis Konseling</label>
                                <select name="jenis"
                                    class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 transition-colors">
                                    <option value="">Semua Jenis</option>
                                    <?php foreach ($jenis_options as $j): ?>
                                        <option value="<?= $j ?>" <?= $jenis_filter === $j ? 'selected' : '' ?>><?= $j ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Status</label>
                                <select name="status"
                                    class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/20 transition-colors">
                                    <option value="">Semua Status</option>
                                    <?php foreach (['Proses', 'Selesai', 'Ditunda'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Search -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Cari Siswa / Konselor</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama, NIS, atau Konselor..."
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
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-5 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fas fa-table text-teal-500"></i>
                            Daftar Konseling
                            <span class="text-xs text-gray-500 font-normal">– <?= $total_items ?> data ditemukan</span>
                        </h3>
                        <span class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-teal-200 rounded-full text-xs text-teal-600 shadow-sm">
                            <i class="fas fa-eye text-teal-500"></i> Mode Lihat Saja
                        </span>
                    </div>

                    <?php if (!empty($konseling_list)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full whitespace-nowrap text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase border-b border-gray-200">
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                NIS <?= sortIcon('nis', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                Nama <?= sortIcon('nama_lengkap', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                Tanggal <?= sortIcon('tanggal', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('jenis_konseling') ?>" class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                Jenis <?= sortIcon('jenis_konseling', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">Masalah</th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('konselor') ?>" class="flex items-center gap-1 hover:text-teal-600 transition-colors">
                                                Konselor <?= sortIcon('konselor', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-center">
                                            <a href="<?= buildSortUrl('status') ?>" class="flex items-center justify-center gap-1 hover:text-teal-600 transition-colors">
                                                Status <?= sortIcon('status', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php
                                    $statusIcons = ['Proses' => '⏳', 'Selesai' => '✅', 'Ditunda' => '⏸'];
                                    foreach ($konseling_list as $row):
                                        $icon = $statusIcons[$row['status']] ?? '';
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
                                            <td class="px-5 py-3">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium j-<?= strtolower($row['jenis_konseling']) ?>">
                                                    <?= htmlspecialchars($row['jenis_konseling']) ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-gray-500">
                                                <div class="truncate-cell" title="<?= htmlspecialchars($row['masalah']) ?>">
                                                    <?= htmlspecialchars($row['masalah']) ?>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-gray-600">
                                                <?= htmlspecialchars($row['konselor']) ?>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium s-<?= strtolower($row['status']) ?>">
                                                    <?= $icon ?> <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <a href="detailk.php?id=<?= $row['id'] ?>"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-500 hover:bg-blue-100 hover:text-blue-600 transition-colors border border-blue-200"
                                                    title="Lihat Detail">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </a>
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
                            <i class="fas fa-comments text-4xl mb-4 block text-gray-300"></i>
                            <p class="text-gray-600 font-medium">
                                Tidak ada data konseling kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?> yang ditemukan
                            </p>
                            <p class="text-sm text-gray-400 mt-1">Coba ubah filter atau pilih tanggal lain</p>
                            <?php if (!empty(array_filter([$search, $jenis_filter, $status_filter, $date_filter]))): ?>
                                <a href="konseling.php"
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
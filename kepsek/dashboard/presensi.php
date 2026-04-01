<?php
session_start();
require_once '../../config/database.php';

// Guard: hanya kepsek
if (!isset($_SESSION['kepsek_id']) || $_SESSION['role'] !== 'kepsek') {
    header("Location: ../../kepsek/login.php");
    exit();
}

// ── Filter ─────────────────────────────────────────────────────────────────
$date_filter     = $_GET['date']     ?? date('Y-m-d');
$status_filter   = $_GET['status']   ?? '';
$kelas_filter    = $_GET['kelas']    ?? '';
$jurusan_filter  = $_GET['jurusan']  ?? '';
$approval_filter = $_GET['approval'] ?? '';
$search          = $_GET['search']   ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────
$items_per_page = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// ── Base SQL ───────────────────────────────────────────────────────────────
$base = "FROM absensi a JOIN siswa s ON a.siswa_id = s.id WHERE 1=1";
$params = [];

if ($date_filter) {
    $base .= " AND a.tanggal = :date";
    $params['date']    = $date_filter;
}
if ($status_filter) {
    $base .= " AND a.status = :status";
    $params['status']  = $status_filter;
}
if ($kelas_filter) {
    $base .= " AND s.kelas = :kelas";
    $params['kelas']   = $kelas_filter;
}
if ($jurusan_filter) {
    $base .= " AND s.jurusan = :jurusan";
    $params['jurusan'] = $jurusan_filter;
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

// ── Stat cards ─────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$status_counts = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Terlambat' => 0, 'Alpha' => 0];
$sc = $conn->prepare("SELECT status, COUNT(*) as c FROM absensi WHERE tanggal=:t AND approval_status='Approved' GROUP BY status");
$sc->execute(['t' => $today]);
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) $status_counts[$r['status']] = $r['c'];

// ── Helpers ────────────────────────────────────────────────────────────────
function buildSortUrl($col)
{
    $p = $_GET;
    $p['sort'] = $col;
    $p['order'] = (isset($_GET['sort']) && $_GET['sort'] === $col && ($_GET['order'] ?? '') == 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query($p);
}
function sortIcon($col, $sc, $so)
{
    if ($col !== $sc) return '<i class="fas fa-sort text-gray-400 opacity-50"></i>';
    return $so === 'ASC' ? '<i class="fas fa-sort-up text-amber-500"></i>' : '<i class="fas fa-sort-down text-amber-500"></i>';
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
    <title>Data Presensi – Kepala Sekolah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        }

        /* Sidebar */
        .menu-active {
            background: linear-gradient(to right, rgba(217, 119, 6, .18), rgba(217, 119, 6, .04));
            border-left: 4px solid #d97706;
        }

        /* Stat badge colors */
        .s-hadir {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .s-sakit {
            background: #fef9c3;
            color: #713f12;
            border: 1px solid #fde047;
        }

        .s-izin {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #93c5fd;
        }

        .s-terlambat {
            background: #ffedd5;
            color: #7c2d12;
            border: 1px solid #fdba74;
        }

        .s-alpha {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fca5a5;
        }

        .s-approved {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .s-pending {
            background: #fef9c3;
            color: #713f12;
            border: 1px solid #fde047;
        }

        .s-rejected {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fca5a5;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(217, 119, 6, .4);
            border-radius: 3px
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .fade-up {
            animation: fadeUp .35s ease-out both
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">

    <div id="overlay" class="fixed inset-0 bg-black/20 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ── SIDEBAR ───────────────────────────────────────────────────────────── -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64
        bg-white/80 backdrop-blur-md border-r border-amber-100
        z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0">

        <div class="flex items-center justify-between p-5 border-b border-amber-200">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" class="h-10 w-auto" alt="Logo">
                <div>
                    <p class="font-semibold text-sm leading-tight">SMK NURUL ULUM</p>
                    <p class="text-xs text-amber-400">Kepala Sekolah</p>
                </div>
            </div>
            <button class="lg:hidden text-gray-500 hover:text-gray-800" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="p-4 space-y-1 overflow-y-auto custom-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="../dashboard/index.php"
                class="flex items-center gap-3 p-3 rounded-lg text-gray-600 hover:bg-amber-500/10 hover:text-gray-800 transition-colors">
                <i class="fas fa-home text-amber-400"></i><span>Dashboard</span>
            </a>

            <div>
                <button onclick="toggleMenu(this)"
                    class="flex items-center gap-3 w-full p-3 rounded-lg text-gray-700 hover:bg-amber-500/10 transition-colors">
                    <i class="fas fa-calendar-check text-amber-400"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform rotate-icon" style="transform:rotate(180deg)"></i>
                </button>
                <ul class="ml-8 mt-1 space-y-1 sub-menu">
                    <li>
                        <a href="presensi.php"
                            class="block p-2 text-amber-500 bg-amber-500/10 rounded-lg text-sm font-medium">
                            Presensi
                        </a>
                    </li>
                    <li><a href="pelanggaran.php" class="block p-2 text-gray-600 hover:text-amber-400 hover:bg-amber-500/10 rounded-lg text-sm">Pelanggaran</a></li>
                    <li><a href="konseling.php" class="block p-2 text-gray-600 hover:text-amber-400 hover:bg-amber-500/10 rounded-lg text-sm">Konseling</a></li>
                </ul>
            </div>

            <hr class="border-gray-200 my-3">

            <a href="../../kepsek/logout.php"
                class="flex items-center gap-3 p-3 rounded-lg text-gray-500 hover:bg-red-500/10 hover:text-red-400 transition-colors">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ── MAIN ──────────────────────────────────────────────────────────────── -->
    <main class="lg:ml-64 min-h-screen">

        <!-- Mobile topbar -->
        <div class="lg:hidden sticky top-0 z-30 bg-white/80 backdrop-blur-md px-4 py-3 flex items-center justify-between border-b border-amber-100 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-700 p-2 -ml-2 rounded-lg hover:bg-amber-50">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="text-sm font-medium text-gray-800">Data Presensi</span>
            </div>
            <img src="../../<?= $_SESSION['kepsek_photo'] ?: 'assets/default/photo-profile.png' ?>"
                class="h-8 w-8 rounded-full object-cover border border-amber-400/50" alt="">
        </div>

        <div class="p-5 md:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- ── HEADER ──────────────────────────────────────────────────── -->
                <header class="flex flex-wrap justify-between items-center mb-8 fade-up">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-clipboard-list text-amber-400"></i> Data Presensi Siswa
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">Monitoring kehadiran siswa – hanya lihat</p>
                    </div>

                    <!-- Badge read-only -->
                    <span class="mt-3 lg:mt-0 inline-flex items-center gap-2 px-4 py-2
                        bg-white/80 backdrop-blur-md border border-amber-200 rounded-xl
                        text-xs text-amber-500 shadow-sm">
                        <i class="fas fa-eye"></i> Mode Lihat Saja
                    </span>
                </header>

                <!-- ── STAT CARDS ──────────────────────────────────────────────── -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
                    <?php
                    $cards = [
                        ['Hadir',     'amber',  'fa-check',      'amber'],
                        ['Sakit',     'yellow', 'fa-hospital',   'yellow'],
                        ['Izin',      'blue',   'fa-clipboard',  'blue'],
                        ['Terlambat', 'orange', 'fa-clock',      'orange'],
                        ['Alpha',     'red',    'fa-user-times', 'red'],
                    ];
                    foreach ($cards as $i => [$lbl, $col, $ico, $text]):
                    ?>
                        <div class="bg-white/80 backdrop-blur-md border border-gray-100 rounded-xl p-4
                            hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default fade-up"
                            style="animation-delay:<?= $i * 0.06 ?>s">

                            <div class="flex justify-between items-start mb-3">
                                <p class="text-gray-500 text-xs font-medium"><?= $lbl ?></p>
                                <div class="h-8 w-8 rounded-lg bg-<?= $col ?>-100 flex items-center justify-center">
                                    <i class="fas <?= $ico ?> text-<?= $col ?>-500 text-sm"></i>
                                </div>
                            </div>

                            <p class="text-2xl font-bold text-gray-800"><?= $status_counts[$lbl] ?></p>
                            <p class="text-xs mt-1 text-gray-400">Hari ini</p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── FILTER ─────────────────────────────────────────────────── -->
                <div class="bg-white/80 backdrop-blur-md border border-gray-100 rounded-xl p-5 mb-6 shadow-sm fade-up"
                    style="animation-delay:.1s">

                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fas fa-filter text-amber-400"></i> Filter & Pencarian
                        </h3>
                        <?php if (!empty(array_filter([$search, $status_filter, $kelas_filter, $jurusan_filter, $approval_filter])) || isset($_GET['date'])): ?>
                            <a href="presensi.php"
                                class="text-xs text-amber-500 hover:text-amber-600 flex items-center gap-1 transition-colors">
                                <i class="fas fa-times-circle"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                            <!-- Tanggal -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Tanggal</label>
                                <input type="date" name="date" value="<?= $date_filter ?>"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Status Kehadiran</label>
                                <select name="status"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                                    <option value="">Semua Status</option>
                                    <?php foreach (['Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Kelas -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Kelas</label>
                                <select name="kelas"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                                    <option value="">Semua Kelas</option>
                                    <?php foreach (['10', '11', '12'] as $k): ?>
                                        <option value="<?= $k ?>" <?= $kelas_filter === $k ? 'selected' : '' ?>><?= $k ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Jurusan -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Jurusan</label>
                                <select name="jurusan"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                                    <option value="">Semua Jurusan</option>
                                    <?php foreach (['RPL', 'DKV', 'AK', 'BR', 'MP'] as $j): ?>
                                        <option value="<?= $j ?>" <?= $jurusan_filter === $j ? 'selected' : '' ?>><?= $j ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Approval -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Status Approval</label>
                                <select name="approval"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
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
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm text-gray-800
                                               focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit"
                                class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium
                                       transition-colors shadow-sm flex items-center gap-2">
                                <i class="fas fa-filter"></i> Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TABLE ──────────────────────────────────────────────────── -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200 fade-up"
                    style="animation-delay:.15s">

                    <?php if (!empty($absensi_list)): ?>

                        <!-- Table header strip -->
                        <div class="bg-gradient-to-r from-blue-100 to-indigo-100 px-5 py-4 border-b border-gray-200">
                            <h3 class="font-semibold flex items-center gap-2 text-gray-800 text-sm">
                                <i class="fas fa-table text-blue-500"></i>
                                Daftar Presensi
                                <span class="text-xs text-gray-500 font-normal">(<?= $total_items ?> data ditemukan)</span>
                            </h3>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full whitespace-nowrap text-sm">
                                <thead>
                                    <tr class="text-gray-500 text-xs uppercase border-b border-gray-200 bg-gray-50">
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                NIS <?= sortIcon('nis', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Nama <?= sortIcon('nama_lengkap', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('kelas') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Kelas <?= sortIcon('kelas', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Tanggal <?= sortIcon('tanggal', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('jam_masuk') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Jam <?= sortIcon('jam_masuk', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-center">
                                            <a href="<?= buildSortUrl('status') ?>" class="flex items-center justify-center gap-1 hover:text-gray-800">
                                                Status <?= sortIcon('status', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-center">Approval</th>
                                        <th class="px-5 py-3 text-left">Keterangan</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($absensi_list as $row):
                                        $sc  = 's-' . strtolower($row['status']);
                                        $apc = 's-' . strtolower($row['approval_status'] ?? 'pending');
                                    ?>
                                        <tr class="hover:bg-blue-50 transition-colors">
                                            <td class="px-5 py-3 text-gray-500 text-xs"><?= htmlspecialchars($row['nis']) ?></td>

                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= $row['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                                        class="h-8 w-8 rounded-full object-cover border border-gray-200 shrink-0" alt="">
                                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($row['nama_lengkap']) ?></span>
                                                </div>
                                            </td>

                                            <td class="px-5 py-3 text-gray-600 text-xs">
                                                <?= htmlspecialchars($row['kelas']) ?> <?= htmlspecialchars($row['jurusan']) ?>
                                            </td>

                                            <td class="px-5 py-3 text-gray-700 text-xs">
                                                <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                                            </td>

                                            <td class="px-5 py-3 text-gray-700 text-xs">
                                                <?= (!empty($row['jam_masuk']) && $row['jam_masuk'] !== '00:00:00')
                                                    ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?>
                                            </td>

                                            <td class="px-5 py-3 text-center">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $sc ?>">
                                                    <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>

                                            <td class="px-5 py-3 text-center">
                                                <span class="px-2.5 py-1 rounded-full text-xs <?= $apc ?>">
                                                    <?= htmlspecialchars($row['approval_status'] ?? 'Pending') ?>
                                                </span>
                                            </td>

                                            <td class="px-5 py-3 text-gray-500 text-xs max-w-[160px] truncate">
                                                <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ── PAGINATION ─────────────────────────────────────── -->
                        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-3">
                            <p class="text-xs text-gray-500">
                                Menampilkan
                                <span class="font-medium text-gray-700"><?= min($offset + 1, $total_items) ?>–<?= min($offset + $items_per_page, $total_items) ?></span>
                                dari <span class="font-medium text-gray-700"><?= $total_items ?></span> data
                            </p>

                            <div class="flex gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= pageUrl(1) ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-amber-50 hover:border-amber-300 text-xs transition-colors"><i class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= pageUrl($page - 1) ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-amber-50 hover:border-amber-300 text-xs transition-colors"><i class="fas fa-angle-left"></i></a>
                                <?php endif; ?>

                                <?php
                                $s = max(1, $page - 2);
                                $e = min($total_pages, $page + 2);
                                if ($s > 1) echo '<span class="px-2 py-1.5 text-gray-400 text-xs">…</span>';
                                for ($i = $s; $i <= $e; $i++) {
                                    $cls = $i == $page
                                        ? 'bg-amber-500 text-white border-amber-500'
                                        : 'bg-white text-gray-700 border-gray-200 hover:bg-amber-50 hover:border-amber-300';
                                    echo "<a href='" . pageUrl($i) . "' class='px-3 py-1.5 border rounded-lg text-xs transition-colors $cls'>$i</a>";
                                }
                                if ($e < $total_pages) echo '<span class="px-2 py-1.5 text-gray-400 text-xs">…</span>';
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= pageUrl($page + 1) ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-amber-50 hover:border-amber-300 text-xs transition-colors"><i class="fas fa-angle-right"></i></a>
                                    <a href="<?= pageUrl($total_pages) ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg hover:bg-amber-50 hover:border-amber-300 text-xs transition-colors"><i class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="py-20 text-center text-gray-400">
                            <i class="fas fa-calendar-day text-5xl mb-4 block opacity-20"></i>
                            <p class="font-medium text-gray-500">Tidak ada data absensi yang ditemukan</p>
                            <?php if (!empty($_GET)): ?>
                                <a href="presensi.php"
                                    class="mt-4 inline-block text-amber-500 hover:text-amber-600 text-sm font-medium transition-colors">
                                    <i class="fas fa-arrow-left mr-1"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div><!-- /table card -->
            </div><!-- /max-w -->
        </div><!-- /padding -->
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

        // Keep sub-menus open by default
        document.querySelectorAll('.sub-menu').forEach(ul => ul.classList.remove('hidden'));

        // Auto-submit on select / date change
        document.querySelectorAll('#filterForm select, #filterForm input[type="date"]')
            .forEach(el => el.addEventListener('change', () => document.getElementById('filterForm').submit()));
    </script>
</body>

</html>
<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Default date range (current month)
$default_start_date = date('Y-m-01');
$default_end_date   = date('Y-m-t');

// Get filter parameters
$start_date     = $_GET['start_date'] ?? $default_start_date;
$end_date       = $_GET['end_date']   ?? $default_end_date;
$kelas          = $_GET['kelas']      ?? '';
$jurusan        = $_GET['jurusan']    ?? '';
$siswa_id       = $_GET['siswa_id']   ?? '';
$jenis          = $_GET['jenis']      ?? '';
$status         = $_GET['status']     ?? '';

// ──────────────────────────────────────────────
// 1. SUMMARY STATISTICS (per jenis pelanggaran)
// ──────────────────────────────────────────────
$summary_base = "FROM pelanggaran p JOIN siswa s ON p.siswa_id = s.id
                 WHERE p.tanggal BETWEEN :start_date AND :end_date";
$summary_params = ['start_date' => $start_date, 'end_date' => $end_date];

if ($kelas) {
    $summary_base .= " AND s.kelas = :kelas";
    $summary_params['kelas']    = $kelas;
}
if ($jurusan) {
    $summary_base .= " AND s.jurusan = :jurusan";
    $summary_params['jurusan']  = $jurusan;
}
if ($siswa_id) {
    $summary_base .= " AND p.siswa_id = :siswa_id";
    $summary_params['siswa_id'] = $siswa_id;
}
if ($jenis) {
    $summary_base .= " AND p.jenis_pelanggaran = :jenis";
    $summary_params['jenis']    = $jenis;
}
if ($status) {
    $summary_base .= " AND p.status = :status";
    $summary_params['status']   = $status;
}

// Jenis counts
$jenis_sql  = "SELECT p.jenis_pelanggaran, COUNT(*) as count " . $summary_base . " GROUP BY p.jenis_pelanggaran";
$jenis_stmt = $conn->prepare($jenis_sql);
$jenis_stmt->execute($summary_params);
$jenis_counts = ['Ringan' => 0, 'Sedang' => 0, 'Berat' => 0];
foreach ($jenis_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($jenis_counts[$row['jenis_pelanggaran']])) {
        $jenis_counts[$row['jenis_pelanggaran']] = $row['count'];
    }
}
$total_pelanggaran = array_sum($jenis_counts);

// Status counts
$status_sql  = "SELECT p.status, COUNT(*) as count " . $summary_base . " GROUP BY p.status";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->execute($summary_params);
$status_counts = ['Pending' => 0, 'Proses' => 0, 'Selesai' => 0];
foreach ($status_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($status_counts[$row['status']])) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// ──────────────────────────────────────────────
// 2. DAILY CHART DATA
// ──────────────────────────────────────────────
$daily_sql  = "SELECT DATE(p.tanggal) as date, p.jenis_pelanggaran, COUNT(*) as count " . $summary_base
    . " GROUP BY DATE(p.tanggal), p.jenis_pelanggaran ORDER BY DATE(p.tanggal)";
$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->execute($summary_params);
$daily_data = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_dates    = [];
$chart_jenises  = [];
$chart_data     = [];

foreach ($daily_data as $row) {
    $d = date('d M', strtotime($row['date']));
    $j = $row['jenis_pelanggaran'];
    if (!in_array($d, $chart_dates))   $chart_dates[]   = $d;
    if (!in_array($j, $chart_jenises)) $chart_jenises[]  = $j;
    if (!isset($chart_data[$j]))       $chart_data[$j]  = [];
    $chart_data[$j][$d] = $row['count'];
}

// ──────────────────────────────────────────────
// 3. TOP SISWA (highest accumulated points)
// ──────────────────────────────────────────────
$top_sql = "SELECT s.id, s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil,
            CAST(COALESCE(SUM(COALESCE(NULLIF(p.poin,0), dp.poin_default, 0)),0) AS UNSIGNED) AS total_poin,
            COUNT(p.id) AS jumlah
            FROM siswa s
            JOIN pelanggaran p ON p.siswa_id = s.id
            LEFT JOIN deskripsi_pelanggaran dp ON dp.nama = p.deskripsi AND dp.jenis = p.jenis_pelanggaran
            WHERE p.tanggal BETWEEN :start_date AND :end_date";
$top_params = ['start_date' => $start_date, 'end_date' => $end_date];
if ($kelas) {
    $top_sql .= " AND s.kelas = :kelas";
    $top_params['kelas']   = $kelas;
}
if ($jurusan) {
    $top_sql .= " AND s.jurusan = :jurusan";
    $top_params['jurusan'] = $jurusan;
}
$top_sql .= " GROUP BY s.id ORDER BY total_poin DESC LIMIT 5";
$top_stmt = $conn->prepare($top_sql);
$top_stmt->execute($top_params);
$top_siswa = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// 4. STUDENT LIST FOR DROPDOWN
// ──────────────────────────────────────────────
$student_list = $conn->query("SELECT id, nis, nama_lengkap, kelas, jurusan FROM siswa ORDER BY nama_lengkap")->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// 5. DETAIL TABLE WITH PAGINATION & SORTING
// ──────────────────────────────────────────────
$page          = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$items_per_page = 20;
$offset        = ($page - 1) * $items_per_page;

$sort_column = $_GET['sort']  ?? 'tanggal';
$sort_order  = $_GET['order'] ?? 'DESC';
$valid_cols  = ['tanggal', 'nama_lengkap', 'nis', 'kelas', 'jenis_pelanggaran', 'poin', 'status', 'total_poin'];
$sort_column = in_array($sort_column, $valid_cols) ? $sort_column : 'tanggal';

// Detail query
$detail_sql = "SELECT p.id, p.tanggal, p.jenis_pelanggaran, p.deskripsi, p.status, p.tindakan,
               s.id as siswa_id, s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil,
               COALESCE(NULLIF(p.poin,0), dp.poin_default, 0) AS poin,
               CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0), dp2.poin_default,0))
                              FROM pelanggaran pp
                              LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama = pp.deskripsi AND dp2.jenis = pp.jenis_pelanggaran
                              WHERE pp.siswa_id = s.id),0) AS UNSIGNED) AS total_poin
               FROM pelanggaran p
               JOIN siswa s ON p.siswa_id = s.id
               LEFT JOIN deskripsi_pelanggaran dp ON dp.nama = p.deskripsi AND dp.jenis = p.jenis_pelanggaran
               WHERE p.tanggal BETWEEN :start_date AND :end_date";
$detail_params = ['start_date' => $start_date, 'end_date' => $end_date];

if ($kelas) {
    $detail_sql .= " AND s.kelas = :kelas";
    $detail_params['kelas']    = $kelas;
}
if ($jurusan) {
    $detail_sql .= " AND s.jurusan = :jurusan";
    $detail_params['jurusan']  = $jurusan;
}
if ($siswa_id) {
    $detail_sql .= " AND p.siswa_id = :siswa_id";
    $detail_params['siswa_id'] = $siswa_id;
}
if ($jenis) {
    $detail_sql .= " AND p.jenis_pelanggaran = :jenis";
    $detail_params['jenis']    = $jenis;
}
if ($status) {
    $detail_sql .= " AND p.status = :status";
    $detail_params['status']   = $status;
}

// Count query for pagination
$count_sql  = "SELECT COUNT(*) FROM pelanggaran p JOIN siswa s ON p.siswa_id = s.id WHERE p.tanggal BETWEEN :start_date AND :end_date";
$count_params = ['start_date' => $start_date, 'end_date' => $end_date];
if ($kelas) {
    $count_sql .= " AND s.kelas = :kelas";
    $count_params['kelas']    = $kelas;
}
if ($jurusan) {
    $count_sql .= " AND s.jurusan = :jurusan";
    $count_params['jurusan']  = $jurusan;
}
if ($siswa_id) {
    $count_sql .= " AND p.siswa_id = :siswa_id";
    $count_params['siswa_id'] = $siswa_id;
}
if ($jenis) {
    $count_sql .= " AND p.jenis_pelanggaran = :jenis";
    $count_params['jenis']    = $jenis;
}
if ($status) {
    $count_sql .= " AND p.status = :status";
    $count_params['status']   = $status;
}
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total_detail_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_detail_items / $items_per_page);

// Sort
if ($sort_column === 'total_poin') {
    $detail_sql .= " ORDER BY total_poin " . ($sort_order === 'ASC' ? 'ASC' : 'DESC') . ", p.tanggal DESC";
} elseif (in_array($sort_column, ['nama_lengkap', 'nis', 'kelas'])) {
    $detail_sql .= " ORDER BY s.$sort_column " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');
} elseif ($sort_column === 'poin') {
    $detail_sql .= " ORDER BY poin " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');
} else {
    $detail_sql .= " ORDER BY p.$sort_column " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');
}
$detail_sql .= " LIMIT :offset, :limit";

$detail_stmt = $conn->prepare($detail_sql);
foreach ($detail_params as $k => $v) $detail_stmt->bindValue(':' . $k, $v);
$detail_stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$detail_stmt->bindValue(':limit',  (int) $items_per_page, PDO::PARAM_INT);
$detail_stmt->execute();
$detail_records = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// 6. HELPERS
// ──────────────────────────────────────────────
function buildUrl($page = null, $sort = null, $order = null)
{
    $p = $_GET;
    if ($page  !== null) $p['page']  = $page;
    if ($sort  !== null) $p['sort']  = $sort;
    if ($order !== null) $p['order'] = $order;
    return '?' . http_build_query($p);
}
function getSortIcon($col, $cur, $ord)
{
    if ($col !== $cur) return '<i class="fas fa-sort text-gray-500 opacity-50"></i>';
    return $ord === 'ASC' ? '<i class="fas fa-sort-up text-purple-500"></i>' : '<i class="fas fa-sort-down text-purple-500"></i>';
}
function totalPoinColor($poin)
{
    if ($poin >= 75) return ['bar' => 'bg-red-500',    'text' => 'text-red-400',    'bg' => 'bg-red-500/10 border-red-500/30'];
    if ($poin >= 50) return ['bar' => 'bg-orange-500', 'text' => 'text-orange-400', 'bg' => 'bg-orange-500/10 border-orange-500/30'];
    if ($poin >= 25) return ['bar' => 'bg-yellow-500', 'text' => 'text-yellow-400', 'bg' => 'bg-yellow-500/10 border-yellow-500/30'];
    return ['bar' => 'bg-green-500', 'text' => 'text-green-400', 'bg' => 'bg-green-500/10 border-green-500/30'];
}

$jenis_colors = [
    'Ringan' => '#22C55E',
    'Sedang' => '#EAB308',
    'Berat'  => '#EF4444',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pelanggaran - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%);
        }

        /* Jenis badges */
        .jenis-ringan {
            background: rgba(34, 197, 94, 0.1);
            color: #22C55E;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .jenis-sedang {
            background: rgba(234, 179, 8, 0.1);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .jenis-berat {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-selesai {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-proses {
            background: rgba(217, 119, 6, 0.1);
            color: #D97706;
            border: 1px solid rgba(217, 119, 6, 0.3);
        }

        .status-pending {
            background: rgba(107, 114, 128, 0.1);
            color: #9CA3AF;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease forwards;
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

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        @media(max-width:640px) {
            .pagination-compact .page-number {
                display: none;
            }

            .pagination-compact .current-page {
                display: inline-flex;
            }

            .chart-container-responsive {
                height: 220px !important;
            }
        }

        .poin-bar-wrap {
            width: 60px;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
        }

        .poin-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.4s;
        }

        /* Top siswa rank badges */
        .rank-1 {
            background: rgba(250, 204, 21, 0.15);
            color: #FBBF24;
            border: 1px solid rgba(250, 204, 21, 0.4);
        }

        .rank-2 {
            background: rgba(148, 163, 184, 0.15);
            color: #94A3B8;
            border: 1px solid rgba(148, 163, 184, 0.4);
        }

        .rank-3 {
            background: rgba(205, 127, 50, 0.15);
            color: #CD7F32;
            border: 1px solid rgba(205, 127, 50, 0.4);
        }

        .rank-other {
            background: rgba(107, 114, 128, 0.1);
            color: #6B7280;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">

    <!-- Mobile Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ══════════════════ SIDEBAR ══════════════════ -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-purple-900/30 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 lg:h-10 w-auto">
                <div>
                    <h1 class="font-semibold text-sm lg:text-base">SMK NURUL ULUM</h1>
                    <p class="text-xs text-gray-400">Sistem Absensi</p>
                </div>
            </div>
            <button class="text-gray-400 hover:text-white lg:hidden" onclick="toggleSidebar()"><i class="fas fa-times text-xl"></i></button>
        </div>
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <li class="relative group list-none">
                <button class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-calendar-check"></i><span>Monitoring Siswa</span><i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block">
                    <li><a href="../absensi/index.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a></li>
                    <li><a href="../absensi/pelanggaran.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../absensi/konseling.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../siswa/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors"><i class="fas fa-users"></i><span>Data Siswa</span></a>
            <li class="relative group list-none">
                <button class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-file-alt"></i><span>Laporan</span><i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block">
                    <li><a href="../laporan/index.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a></li>
                    <li><a href="../laporan/laporan_pelanggaran.php" class="block p-2 text-purple-400 bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../laporan/laporan_konseling.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../profil/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors"><i class="fas fa-user-cog"></i><span>Profil</span></a>
            <a href="../logout.php" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors mt-10"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </aside>

    <!-- ══════════════════ MAIN ══════════════════ -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 transition-all duration-300">

        <!-- Mobile Header -->
        <div class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60"><i class="fas fa-bars text-lg"></i></button>
                <img src="../../assets/default/logo-smk40.png" alt="SMK" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- ── HEADER ── -->
                <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Laporan Pelanggaran</h1>
                        <p class="text-gray-400 text-sm md:text-base">Statistik dan rekapitulasi data pelanggaran siswa</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="export_pelanggaran.php?format=pdf<?= isset($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                            class="px-3 py-2 sm:px-4 sm:py-2 bg-red-600 hover:bg-red-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                            <i class="fas fa-file-pdf"></i>
                            <span class="hidden sm:inline">Export PDF</span>
                        </a>
                    </div>
                </header>

                <!-- ── FILTER ── -->
                <div class="glass-effect rounded-xl p-4 sm:p-6 mb-6">
                    <h3 class="font-semibold text-lg mb-4">Filter Laporan</h3>
                    <form method="GET" id="filterForm" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4">

                            <!-- Date Range -->
                            <div class="bg-gray-800/30 rounded-lg p-4">
                                <h4 class="text-sm font-medium mb-3 text-purple-400"><i class="fas fa-calendar-alt mr-2"></i>Periode Waktu</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs text-gray-400 block mb-1">Tanggal Mulai</label>
                                        <input type="date" name="start_date" value="<?= $start_date ?>"
                                            class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-400 block mb-1">Tanggal Akhir</label>
                                        <input type="date" name="end_date" value="<?= $end_date ?>"
                                            class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Filters -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                                <!-- Kelas & Jurusan -->
                                <div class="bg-gray-800/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-purple-400"><i class="fas fa-school mr-2"></i>Kelas & Jurusan</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-xs text-gray-400 block mb-1">Kelas</label>
                                            <select name="kelas" class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                                                <option value="">Semua Kelas</option>
                                                <option value="10" <?= $kelas === '10' ? 'selected' : '' ?>>Kelas 10</option>
                                                <option value="11" <?= $kelas === '11' ? 'selected' : '' ?>>Kelas 11</option>
                                                <option value="12" <?= $kelas === '12' ? 'selected' : '' ?>>Kelas 12</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-400 block mb-1">Jurusan</label>
                                            <select name="jurusan" class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                                                <option value="">Semua Jurusan</option>
                                                <option value="RPL" <?= $jurusan === 'RPL' ? 'selected' : '' ?>>RPL</option>
                                                <option value="DKV" <?= $jurusan === 'DKV' ? 'selected' : '' ?>>DKV</option>
                                                <option value="AK" <?= $jurusan === 'AK' ? 'selected' : '' ?>>AK</option>
                                                <option value="BR" <?= $jurusan === 'BR' ? 'selected' : '' ?>>BR</option>
                                                <option value="MP" <?= $jurusan === 'MP' ? 'selected' : '' ?>>MP</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Jenis Pelanggaran -->
                                <div class="bg-gray-800/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-purple-400"><i class="fas fa-exclamation-triangle mr-2"></i>Jenis Pelanggaran</h4>
                                    <div>
                                        <label class="text-xs text-gray-400 block mb-1">Jenis</label>
                                        <select name="jenis" class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                                            <option value="">Semua Jenis</option>
                                            <option value="Ringan" <?= $jenis === 'Ringan' ? 'selected' : '' ?>>Ringan</option>
                                            <option value="Sedang" <?= $jenis === 'Sedang' ? 'selected' : '' ?>>Sedang</option>
                                            <option value="Berat" <?= $jenis === 'Berat' ? 'selected' : '' ?>>Berat</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="bg-gray-800/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-purple-400"><i class="fas fa-filter mr-2"></i>Status Tindak Lanjut</h4>
                                    <div>
                                        <label class="text-xs text-gray-400 block mb-1">Status</label>
                                        <select name="status" class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                                            <option value="">Semua Status</option>
                                            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Proses" <?= $status === 'Proses' ? 'selected' : '' ?>>Proses</option>
                                            <option value="Selesai" <?= $status === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Student -->
                                <div class="bg-gray-800/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-purple-400"><i class="fas fa-user-graduate mr-2"></i>Siswa</h4>
                                    <div>
                                        <label class="text-xs text-gray-400 block mb-1">Pilih Siswa</label>
                                        <select name="siswa_id" class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                                            <option value="">Semua Siswa</option>
                                            <?php foreach ($student_list as $st): ?>
                                                <option value="<?= $st['id'] ?>" <?= $siswa_id == $st['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($st['nama_lengkap']) ?> (<?= $st['nis'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-700">
                            <div class="w-full sm:w-auto">
                                <?php if (!empty(array_filter([$kelas, $jurusan, $siswa_id, $jenis, $status])) || ($_GET['start_date'] ?? '') !== $default_start_date || ($_GET['end_date'] ?? '') !== $default_end_date): ?>
                                    <button type="button" onclick="resetFilters()"
                                        class="w-full sm:w-auto px-4 py-2 border border-gray-700 hover:border-gray-600 rounded-lg text-sm text-gray-400 hover:text-white transition-colors">
                                        <i class="fas fa-redo mr-2"></i>Reset Filter
                                    </button>
                                <?php endif; ?>
                            </div>
                            <button type="submit"
                                class="w-full sm:w-auto px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm transition-colors">
                                <i class="fas fa-filter mr-2"></i>Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── SUMMARY + CHARTS ── -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                </div>
                <!-- ── DATA TABLE ── -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-gray-800">
                        <h3 class="font-semibold text-lg">Data Detail Pelanggaran</h3>
                        <p class="text-sm text-gray-400 mt-1">
                            Periode: <?= date('d F Y', strtotime($start_date)) ?> – <?= date('d F Y', strtotime($end_date)) ?>
                        </p>
                    </div>

                    <?php if (count($detail_records) > 0): ?>
                        <div class="overflow-x-auto table-container">
                            <table class="w-full whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-800/50 text-gray-300 text-left">
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'tanggal', $sort_column === 'tanggal' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-white">
                                                Tanggal <?= getSortIcon('tanggal', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'nis', $sort_column === 'nis' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-white">
                                                NIS <?= getSortIcon('nis', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'nama_lengkap', $sort_column === 'nama_lengkap' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-white">
                                                Nama Siswa <?= getSortIcon('nama_lengkap', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'kelas', $sort_column === 'kelas' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-white">
                                                Kelas <?= getSortIcon('kelas', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'jenis_pelanggaran', $sort_column === 'jenis_pelanggaran' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-white">
                                                Jenis <?= getSortIcon('jenis_pelanggaran', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">Deskripsi</th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'poin', $sort_column === 'poin' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-white">
                                                Poin <?= getSortIcon('poin', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'total_poin', $sort_column === 'total_poin' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-white">
                                                Total Poin <?= getSortIcon('total_poin', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'status', $sort_column === 'status' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-white">
                                                Status <?= getSortIcon('status', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    <?php foreach ($detail_records as $rec):
                                        $tp    = min((int)$rec['total_poin'], 100);
                                        $color = totalPoinColor($tp);
                                        $pct   = $tp . '%';
                                    ?>
                                        <tr class="hover:bg-purple-900/5 transition-colors">
                                            <td class="px-5 py-4 text-sm"><?= date('d/m/Y', strtotime($rec['tanggal'])) ?></td>
                                            <td class="px-5 py-4 text-sm"><?= htmlspecialchars($rec['nis']) ?></td>
                                            <td class="px-5 py-4 text-sm">
                                                <div class="flex items-center">
                                                    <img src="../../<?= $rec['foto_profil'] ?: 'assets/default/avatar.png' ?>"
                                                        class="w-6 h-6 rounded-full mr-2 object-cover hidden sm:block" alt="">
                                                    <?= htmlspecialchars($rec['nama_lengkap']) ?>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 text-sm"><?= $rec['kelas'] ?> <?= $rec['jurusan'] ?></td>
                                            <td class="px-5 py-4">
                                                <span class="px-2 py-1 rounded-full text-xs jenis-<?= strtolower($rec['jenis_pelanggaran']) ?>">
                                                    <?= $rec['jenis_pelanggaran'] ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-sm max-w-[160px]">
                                                <span class="truncate block text-gray-300" title="<?= htmlspecialchars($rec['deskripsi']) ?>">
                                                    <?= htmlspecialchars($rec['deskripsi']) ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-sm font-semibold <?= $rec['jenis_pelanggaran'] === 'Berat' ? 'text-red-400' : ($rec['jenis_pelanggaran'] === 'Sedang' ? 'text-yellow-400' : 'text-green-400') ?>">
                                                <?= $rec['poin'] ?>
                                            </td>
                                            <td class="px-5 py-4">
                                                <div class="flex flex-col gap-1">
                                                    <span class="text-sm font-bold <?= $color['text'] ?>"><?= $rec['total_poin'] ?> <span class="font-normal text-xs text-gray-400">poin</span></span>
                                                    <div class="poin-bar-wrap" title="Akumulasi poin: <?= $tp ?>">
                                                        <div class="poin-bar-fill <?= $color['bar'] ?>" style="width:<?= $pct ?>"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4">
                                                <span class="px-2 py-1 rounded-full text-xs status-<?= strtolower($rec['status']) ?>">
                                                    <?= $rec['status'] ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-center">
                                                <a href="../absensi/detailp.php?id=<?= $rec['id'] ?>" class="text-blue-400 hover:text-blue-300">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="p-4 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <p class="text-sm text-gray-400 order-2 sm:order-1">
                                Menampilkan <?= min($offset + 1, $total_detail_items) ?> – <?= min($offset + $items_per_page, $total_detail_items) ?> dari <?= $total_detail_items ?> data
                            </p>
                            <div class="flex space-x-1 order-1 sm:order-2 pagination-compact">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildUrl(1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= buildUrl($page - 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-left"></i></a>
                                <?php endif; ?>
                                <?php
                                $range = 2;
                                $sp = max($page - $range, 1);
                                $ep = min($page + $range, $total_pages);
                                if ($sp > 1) echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                for ($i = $sp; $i <= $ep; $i++) {
                                    $cls = $i == $page
                                        ? 'px-2 sm:px-3 py-1.5 bg-purple-600 rounded text-white text-sm flex items-center justify-center min-w-[32px] current-page page-number'
                                        : 'px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px] page-number';
                                    echo '<a href="' . buildUrl($i) . '" class="' . $cls . '">' . $i . '</a>';
                                }
                                if ($ep < $total_pages) echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildUrl($page + 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-right"></i></a>
                                    <a href="<?= buildUrl($total_pages) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="p-10 text-center">
                            <i class="fas fa-shield-alt text-5xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400">Tidak ada data pelanggaran untuk filter yang dipilih</p>
                            <button onclick="resetFilters()" class="mt-4 px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm transition-colors">
                                <i class="fas fa-redo mr-2"></i>Reset Filter
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- ── TOP SISWA ── -->
                <?php if (count($top_siswa) > 0): ?>
                    <div class="glass-effect rounded-xl p-4 sm:p-6 mb-6">
                        <h3 class="font-semibold text-lg mb-4">
                            <i class="fas fa-trophy text-yellow-400 mr-2"></i>
                            Top 5 Siswa Akumulasi Poin Pelanggaran
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                            <?php foreach ($top_siswa as $idx => $ts):
                                $rank   = $idx + 1;
                                $tp     = min((int)$ts['total_poin'], 100);
                                $color  = totalPoinColor($tp);
                                $pct    = $tp . '%';
                                $rankClass = match ($rank) {
                                    1 => 'rank-1',
                                    2 => 'rank-2',
                                    3 => 'rank-3',
                                    default => 'rank-other'
                                };
                            ?>
                                <div class="bg-gray-800/40 border border-gray-700/50 rounded-lg p-4 flex flex-col items-center text-center gap-2 hover:border-purple-500/40 transition-colors">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $rankClass ?>">#<?= $rank ?></span>
                                    <img src="../../<?= $ts['foto_profil'] ?: 'assets/default/avatar.png' ?>"
                                        class="w-12 h-12 rounded-full object-cover border-2 <?= $rank === 1 ? 'border-yellow-400' : 'border-gray-600' ?>">
                                    <div>
                                        <p class="text-sm font-semibold leading-tight"><?= htmlspecialchars($ts['nama_lengkap']) ?></p>
                                        <p class="text-xs text-gray-400"><?= $ts['kelas'] ?> <?= $ts['jurusan'] ?></p>
                                    </div>
                                    <div class="w-full">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-gray-400">Total Poin</span>
                                            <span class="font-bold <?= $color['text'] ?>"><?= $ts['total_poin'] ?></span>
                                        </div>
                                        <div class="poin-bar-wrap w-full" style="width:100%">
                                            <div class="poin-bar-fill <?= $color['bar'] ?>" style="width:<?= $pct ?>"></div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-400"><?= $ts['jumlah'] ?> pelanggaran</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Mobile FAB - Export PDF -->
                <div class="fixed bottom-4 right-4 lg:hidden">
                    <a href="export_pelanggaran.php?format=pdf<?= isset($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                        class="flex items-center justify-center w-12 h-12 bg-red-600 hover:bg-red-700 rounded-full shadow-lg transition-colors">
                        <i class="fas fa-file-pdf text-lg"></i>
                    </a>
                </div>

            </div>
        </div>
    </main>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const isMobile = window.innerWidth < 768;

            const baseOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: !isMobile,
                        labels: {
                            boxWidth: isMobile ? 8 : 12,
                            font: {
                                size: isMobile ? 10 : 11
                            },
                            color: '#9CA3AF'
                        }
                    },
                    tooltip: {
                        titleFont: {
                            size: isMobile ? 10 : 12
                        },
                        bodyFont: {
                            size: isMobile ? 10 : 12
                        },
                        padding: isMobile ? 8 : 12
                    }
                }
            };

            // ── Pie Chart ──
            new Chart(document.getElementById('jenisPieChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Ringan', 'Sedang', 'Berat'],
                    datasets: [{
                        data: [<?= $jenis_counts['Ringan'] ?>, <?= $jenis_counts['Sedang'] ?>, <?= $jenis_counts['Berat'] ?>],
                        backgroundColor: ['#22C55E', '#EAB308', '#EF4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    ...baseOptions,
                    cutout: '60%',
                    plugins: {
                        ...baseOptions.plugins,
                        legend: {
                            ...baseOptions.plugins.legend,
                            position: 'right',
                            display: window.innerWidth >= 1024
                        }
                    }
                }
            });

            // ── Trend Line Chart ──
            const chartDates = <?= json_encode($chart_dates) ?>;
            const chartData = <?= json_encode($chart_data) ?>;
            const chartJenises = <?= json_encode($chart_jenises) ?>;
            const jenisColors = <?= json_encode($jenis_colors) ?>;

            const datasets = chartJenises.map(j => {
                const color = jenisColors[j] || '#6B7280';
                return {
                    label: j,
                    data: chartDates.map(d => chartData[j]?.[d] || 0),
                    borderColor: color,
                    backgroundColor: color + '22',
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: color,
                    pointRadius: isMobile ? 2 : 4,
                    pointHoverRadius: isMobile ? 4 : 6,
                    borderWidth: isMobile ? 2 : 3
                };
            });

            new Chart(document.getElementById('trendLineChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets
                },
                options: {
                    ...baseOptions,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            ticks: {
                                font: {
                                    size: isMobile ? 9 : 10
                                },
                                color: '#6B7280'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: isMobile ? 9 : 10
                                },
                                color: '#6B7280',
                                maxRotation: isMobile ? 45 : 0
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.05)'
                            }
                        }
                    }
                }
            });
        });

        function resetFilters() {
            window.location.href = 'pelanggaran.php';
        }

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
            if (e.key === 'Escape' && window.innerWidth < 1024) {
                const sidebar = document.getElementById('sidebar');
                if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });

        window.addEventListener('resize', () => {
            document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
        });
        document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
    </script>
</body>

</html>
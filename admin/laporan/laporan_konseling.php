<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Default date range (current month)
$default_start_date = date('Y-m-01');
$default_end_date   = date('Y-m-t');

// Get filter parameters
$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date   = $_GET['end_date']   ?? $default_end_date;
$kelas      = $_GET['kelas']      ?? '';
$jurusan    = $_GET['jurusan']    ?? '';
$siswa_id   = $_GET['siswa_id']   ?? '';
$jenis      = $_GET['jenis']      ?? '';
$status     = $_GET['status']     ?? '';
$konselor   = $_GET['konselor']   ?? '';

// ──────────────────────────────────────────────
// BASE WHERE (reused across all queries)
// Columns: id, siswa_id, tanggal, jenis_konseling, masalah,
//          solusi, tindak_lanjut, konselor, status,
//          created_by, created_at, updated_at
// ──────────────────────────────────────────────
$base_where  = "FROM konseling k
                JOIN siswa s ON k.siswa_id = s.id
                WHERE k.tanggal BETWEEN :start_date AND :end_date";
$base_params = ['start_date' => $start_date, 'end_date' => $end_date];

if ($kelas) {
    $base_where .= " AND s.kelas = :kelas";
    $base_params['kelas']    = $kelas;
}
if ($jurusan) {
    $base_where .= " AND s.jurusan = :jurusan";
    $base_params['jurusan']  = $jurusan;
}
if ($siswa_id) {
    $base_where .= " AND k.siswa_id = :siswa_id";
    $base_params['siswa_id'] = $siswa_id;
}
if ($jenis) {
    $base_where .= " AND k.jenis_konseling = :jenis";
    $base_params['jenis']    = $jenis;
}
if ($status) {
    $base_where .= " AND k.status = :status";
    $base_params['status']   = $status;
}
if ($konselor) {
    $base_where .= " AND k.konselor LIKE :konselor";
    $base_params['konselor'] = "%$konselor%";
}

// ──────────────────────────────────────────────
// SUMMARY — per jenis_konseling
// ──────────────────────────────────────────────
$jenis_stmt = $conn->prepare(
    "SELECT k.jenis_konseling, COUNT(*) as count $base_where GROUP BY k.jenis_konseling"
);
$jenis_stmt->execute($base_params);
$jenis_counts = ['Akademik' => 0, 'Pribadi' => 0, 'Sosial' => 0, 'Karir' => 0];
foreach ($jenis_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($jenis_counts[$row['jenis_konseling']])) {
        $jenis_counts[$row['jenis_konseling']] = $row['count'];
    }
}
$total_konseling = array_sum($jenis_counts);

// ──────────────────────────────────────────────
// SUMMARY — per status
// ──────────────────────────────────────────────
$status_stmt = $conn->prepare(
    "SELECT k.status, COUNT(*) as count $base_where GROUP BY k.status"
);
$status_stmt->execute($base_params);
$status_counts = ['Dijadwalkan' => 0, 'Berlangsung' => 0, 'Selesai' => 0];
foreach ($status_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($status_counts[$row['status']])) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// ──────────────────────────────────────────────
// DAILY CHART DATA
// ──────────────────────────────────────────────
$daily_stmt = $conn->prepare(
    "SELECT DATE(k.tanggal) as date, k.jenis_konseling, COUNT(*) as count
     $base_where
     GROUP BY DATE(k.tanggal), k.jenis_konseling
     ORDER BY DATE(k.tanggal)"
);
$daily_stmt->execute($base_params);

$chart_dates   = [];
$chart_jenises = [];
$chart_data    = [];
foreach ($daily_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $d = date('d M', strtotime($row['date']));
    $j = $row['jenis_konseling'];
    if (!in_array($d, $chart_dates))   $chart_dates[]  = $d;
    if (!in_array($j, $chart_jenises)) $chart_jenises[] = $j;
    $chart_data[$j][$d] = $row['count'];
}

// ──────────────────────────────────────────────
// TOP KONSELOR
// ──────────────────────────────────────────────
$top_konselor_stmt = $conn->prepare(
    "SELECT k.konselor,
            COUNT(*) as jumlah,
            SUM(CASE WHEN k.status = 'Selesai' THEN 1 ELSE 0 END) as selesai
     $base_where
       AND k.konselor IS NOT NULL AND k.konselor != ''
     GROUP BY k.konselor
     ORDER BY jumlah DESC
     LIMIT 5"
);
$top_konselor_stmt->execute($base_params);
$top_konselor = $top_konselor_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// STUDENT LIST FOR DROPDOWN
// ──────────────────────────────────────────────
$student_list = $conn->query(
    "SELECT id, nis, nama_lengkap, kelas, jurusan FROM siswa ORDER BY nama_lengkap"
)->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// KONSELOR LIST FOR DROPDOWN
// ──────────────────────────────────────────────
$konselor_list = $conn->query(
    "SELECT DISTINCT konselor FROM konseling
     WHERE konselor IS NOT NULL AND konselor != ''
     ORDER BY konselor"
)->fetchAll(PDO::FETCH_COLUMN);

// ──────────────────────────────────────────────
// DETAIL TABLE — PAGINATION & SORTING
// ──────────────────────────────────────────────
$page           = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$items_per_page = 20;
$offset         = ($page - 1) * $items_per_page;

$sort_column = $_GET['sort']  ?? 'tanggal';
$sort_order  = $_GET['order'] ?? 'DESC';
$valid_cols  = ['tanggal', 'nama_lengkap', 'nis', 'kelas', 'jenis_konseling', 'konselor', 'status'];
$sort_column = in_array($sort_column, $valid_cols) ? $sort_column : 'tanggal';

// Total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) $base_where");
$count_stmt->execute($base_params);
$total_detail_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_detail_items / $items_per_page);

// Sort prefix
$sort_prefix = in_array($sort_column, ['nama_lengkap', 'nis', 'kelas']) ? 's.' : 'k.';

// Detail query — uses all real columns from the konseling table
$detail_sql = "SELECT k.id, k.tanggal, k.jenis_konseling, k.masalah, k.solusi,
                      k.tindak_lanjut, k.konselor, k.status, k.created_by, k.created_at,
                      s.id   AS siswa_id, s.nama_lengkap, s.nis,
                      s.kelas, s.jurusan, s.foto_profil
               $base_where
               ORDER BY {$sort_prefix}{$sort_column} " . ($sort_order === 'ASC' ? 'ASC' : 'DESC') . "
               LIMIT :offset, :limit";

$detail_stmt = $conn->prepare($detail_sql);
foreach ($base_params as $k => $v) $detail_stmt->bindValue(':' . $k, $v);
$detail_stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$detail_stmt->bindValue(':limit',  (int) $items_per_page, PDO::PARAM_INT);
$detail_stmt->execute();
$detail_records = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// HELPERS
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
    return $ord === 'ASC'
        ? '<i class="fas fa-sort-up text-violet-600"></i>'
        : '<i class="fas fa-sort-down text-violet-600"></i>';
}

$jenis_colors = [
    'Akademik' => '#8B5CF6',
    'Pribadi'  => '#3B82F6',
    'Sosial'   => '#10B981',
    'Karir'    => '#F97316',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Konseling - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ede9fe 100%);
        }

        /* Jenis badges */
        .jenis-akademik {
            background: rgba(139, 92, 246, 0.1);
            color: #8B5CF6;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .jenis-pribadi {
            background: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .jenis-sosial {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .jenis-karir {
            background: rgba(249, 115, 22, 0.1);
            color: #F97316;
            border: 1px solid rgba(249, 115, 22, 0.3);
        }

        /* Status badges */
        .status-dijadwalkan {
            background: rgba(107, 114, 128, 0.1);
            color: #9CA3AF;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        .status-berlangsung {
            background: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .status-selesai {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.25);
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

        @media (max-width: 640px) {
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

        .progress-bar-wrap {
            height: 6px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.5s ease;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">

    <!-- Mobile Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ══════════════════ SIDEBAR ══════════════════ -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-violet-200 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-violet-200">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 lg:h-10 w-auto">
                <div>
                    <h1 class="font-semibold text-sm lg:text-base text-gray-800">SMK NURUL ULUM</h1>
                    <p class="text-xs text-gray-500">Sistem Absensi</p>
                </div>
            </div>
            <button class="text-gray-600 hover:text-gray-800 lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px)">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <li class="relative group list-none">
                <button class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                    <i class="fas fa-calendar-check"></i><span>Monitoring Siswa</span><i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block">
                    <li><a href="../absensi/index.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Presensi</a></li>
                    <li><a href="../absensi/pelanggaran.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../absensi/konseling.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../siswa/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-users"></i><span>Data Siswa</span>
            </a>
            <li class="relative group list-none">
                <button class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                    <i class="fas fa-file-alt"></i><span>Laporan</span><i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block">
                    <li><a href="../laporan/index.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Presensi</a></li>
                    <li><a href="../laporan/laporan_pelanggaran.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../laporan/laporan_konseling.php" class="block p-2 text-violet-500 bg-purple-500/10 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../profil/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-user-cog"></i><span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ══════════════════ MAIN ══════════════════ -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-sky-50 to-indigo-50 transition-all duration-300">

        <!-- Mobile Header -->
        <div class="lg:hidden bg-white/90 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-800 p-2 -ml-2 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-violet-300">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- ── HEADER ── -->
                <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Laporan Konseling</h1>
                        <p class="text-gray-500 text-sm md:text-base">Statistik dan rekapitulasi data sesi konseling siswa</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="export_konseling.php?format=pdf<?= isset($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                            class="px-3 py-2 sm:px-4 sm:py-2 text-white bg-red-600 hover:bg-red-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                            <i class="fas fa-file-pdf"></i>
                            <span class="hidden sm:inline">Cetak PDF</span>
                        </a>
                        <a href="export_konseling.php?format=excel<?= isset($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                            class="px-3 py-2 sm:px-4 sm:py-2 text-white bg-green-600 hover:bg-green-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                            <i class="fas fa-file-excel"></i> <span class="hidden sm:inline">Export Excel</span>
                        </a>
                    </div>
                </header>

                <!-- ── FILTER ── -->
                <div class="glass-effect rounded-xl p-4 sm:p-6 mb-6">
                    <h3 class="font-semibold text-lg mb-4">Filter Laporan</h3>
                    <form method="GET" id="filterForm" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4">

                            <!-- Date Range -->
                            <div class="bg-gray-50/30 rounded-lg p-4">
                                <h4 class="text-sm font-medium mb-3 text-violet-500">
                                    <i class="fas fa-calendar-alt mr-2"></i>Periode Waktu
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Tanggal Mulai</label>
                                        <input type="date" name="start_date" value="<?= $start_date ?>"
                                            class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Tanggal Akhir</label>
                                        <input type="date" name="end_date" value="<?= $end_date ?>"
                                            class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Filters -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                                <!-- Kelas & Jurusan -->
                                <div class="bg-gray-50/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-violet-500">
                                        <i class="fas fa-school mr-2"></i>Kelas & Jurusan
                                    </h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">Kelas</label>
                                            <select name="kelas" class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                                <option value="">Semua Kelas</option>
                                                <option value="10" <?= $kelas === '10' ? 'selected' : '' ?>>Kelas 10</option>
                                                <option value="11" <?= $kelas === '11' ? 'selected' : '' ?>>Kelas 11</option>
                                                <option value="12" <?= $kelas === '12' ? 'selected' : '' ?>>Kelas 12</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">Jurusan</label>
                                            <select name="jurusan" class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                                <option value="">Semua Jurusan</option>
                                                <option value="TKJ" <?= $jurusan === 'TKJ' ? 'selected' : '' ?>>TKJ</option>
                                                <option value="MP" <?= $jurusan === 'MP' ? 'selected' : '' ?>>MP</option>
                                                <option value="AKL" <?= $jurusan === 'AKL' ? 'selected' : '' ?>>AKL</option>
                                                <option value="TSM" <?= $jurusan === 'TSM' ? 'selected' : '' ?>>TSM</option>
                                                <option value="TKR" <?= $jurusan === 'TKR' ? 'selected' : '' ?>>TKR</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Jenis Konseling -->
                                <div class="bg-gray-50/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-violet-500">
                                        <i class="fas fa-comments mr-2"></i>Jenis Konseling
                                    </h4>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Jenis</label>
                                        <select name="jenis" class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                            <option value="">Semua Jenis</option>
                                            <option value="Akademik" <?= $jenis === 'Akademik' ? 'selected' : '' ?>>Akademik</option>
                                            <option value="Pribadi" <?= $jenis === 'Pribadi' ? 'selected' : '' ?>>Pribadi</option>
                                            <option value="Sosial" <?= $jenis === 'Sosial'  ? 'selected' : '' ?>>Sosial</option>
                                            <option value="Karir" <?= $jenis === 'Karir'   ? 'selected' : '' ?>>Karir</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="bg-gray-50/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-violet-500">
                                        <i class="fas fa-filter mr-2"></i>Status Sesi
                                    </h4>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Status</label>
                                        <select name="status" class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                            <option value="">Semua Status</option>
                                            <option value="Dijadwalkan" <?= $status === 'Dijadwalkan' ? 'selected' : '' ?>>Dijadwalkan</option>
                                            <option value="Berlangsung" <?= $status === 'Berlangsung' ? 'selected' : '' ?>>Berlangsung</option>
                                            <option value="Selesai" <?= $status === 'Selesai'    ? 'selected' : '' ?>>Selesai</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Siswa & Konselor -->
                                <div class="bg-gray-50/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-violet-500">
                                        <i class="fas fa-user-graduate mr-2"></i>Siswa & Konselor
                                    </h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">Pilih Siswa</label>
                                            <select name="siswa_id" class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                                <option value="">Semua Siswa</option>
                                                <?php foreach ($student_list as $st): ?>
                                                    <option value="<?= $st['id'] ?>" <?= $siswa_id == $st['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($st['nama_lengkap']) ?> (<?= $st['nis'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">Konselor</label>
                                            <select name="konselor" class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                                <option value="">Semua Konselor</option>
                                                <?php foreach ($konselor_list as $kons): ?>
                                                    <option value="<?= htmlspecialchars($kons) ?>" <?= $konselor === $kons ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($kons) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-300">
                            <div class="w-full sm:w-auto">
                                <?php if (
                                    !empty(array_filter([$kelas, $jurusan, $siswa_id, $jenis, $status, $konselor])) ||
                                    ($_GET['start_date'] ?? '') !== $default_start_date ||
                                    ($_GET['end_date']   ?? '') !== $default_end_date
                                ): ?>
                                    <button type="button" onclick="resetFilters()"
                                        class="w-full sm:w-auto px-4 py-2 border border-gray-300 hover:border-gray-300 rounded-lg text-sm text-gray-500 hover:text-gray-800 transition-colors">
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

              
                <!-- ── DATA TABLE ── -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-gray-800">
                        <h3 class="font-semibold text-lg">Data Detail Konseling</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Periode: <?= date('d F Y', strtotime($start_date)) ?> – <?= date('d F Y', strtotime($end_date)) ?>
                        </p>
                    </div>

                    <?php if (count($detail_records) > 0): ?>
                        <div class="overflow-x-auto table-container">
                            <table class="w-full whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-50/50 text-gray-700 text-left">
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'tanggal', $sort_column === 'tanggal' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Tanggal <?= getSortIcon('tanggal', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'nis', $sort_column === 'nis' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                NIS <?= getSortIcon('nis', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'nama_lengkap', $sort_column === 'nama_lengkap' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Nama Siswa <?= getSortIcon('nama_lengkap', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'kelas', $sort_column === 'kelas' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Kelas <?= getSortIcon('kelas', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'jenis_konseling', $sort_column === 'jenis_konseling' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Jenis <?= getSortIcon('jenis_konseling', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">Masalah</th>
                                        <th class="px-5 py-3 text-xs font-medium">Solusi</th>
                                        <th class="px-5 py-3 text-xs font-medium">Tindak Lanjut</th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'konselor', $sort_column === 'konselor' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Konselor <?= getSortIcon('konselor', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'status', $sort_column === 'status' && $sort_order === 'ASC' ? 'DESC' : 'ASC') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Status <?= getSortIcon('status', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-xs font-medium text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    <?php foreach ($detail_records as $rec): ?>
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
                                                <span class="px-2 py-1 rounded-full text-xs jenis-<?= strtolower($rec['jenis_konseling']) ?>">
                                                    <?= htmlspecialchars($rec['jenis_konseling']) ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-sm max-w-[140px]">
                                                <span class="truncate block text-gray-700" title="<?= htmlspecialchars($rec['masalah'] ?? '') ?>">
                                                    <?= htmlspecialchars($rec['masalah'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-sm max-w-[140px]">
                                                <span class="truncate block text-gray-700" title="<?= htmlspecialchars($rec['solusi'] ?? '') ?>">
                                                    <?= htmlspecialchars($rec['solusi'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-sm max-w-[140px]">
                                                <span class="truncate block text-gray-700" title="<?= htmlspecialchars($rec['tindak_lanjut'] ?? '') ?>">
                                                    <?= htmlspecialchars($rec['tindak_lanjut'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-sm text-gray-700">
                                                <?= htmlspecialchars($rec['konselor'] ?? '-') ?>
                                            </td>
                                            <td class="px-5 py-4">
                                                <span class="px-2 py-1 rounded-full text-xs status-<?= strtolower($rec['status']) ?>">
                                                    <?= htmlspecialchars($rec['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-center">
                                                <a href="../absensi/detailk.php?id=<?= $rec['id'] ?>"
                                                    class="text-blue-400 hover:text-blue-300">
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
                            <p class="text-sm text-gray-500 order-2 sm:order-1">
                                Menampilkan <?= min($offset + 1, $total_detail_items) ?> – <?= min($offset + $items_per_page, $total_detail_items) ?> dari <?= $total_detail_items ?> data
                            </p>
                            <div class="flex space-x-1 order-1 sm:order-2 pagination-compact">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildUrl(1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= buildUrl($page - 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-left"></i></a>
                                <?php endif; ?>

                                <?php
                                $range = 2;
                                $sp = max($page - $range, 1);
                                $ep = min($page + $range, $total_pages);
                                if ($sp > 1) echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                for ($i = $sp; $i <= $ep; $i++) {
                                    $cls = $i === $page
                                        ? 'px-2 sm:px-3 py-1.5 bg-purple-600 rounded text-gray-800 text-sm flex items-center justify-center min-w-[32px] current-page page-number'
                                        : 'px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px] page-number';
                                    echo '<a href="' . buildUrl($i) . '" class="' . $cls . '">' . $i . '</a>';
                                }
                                if ($ep < $total_pages) echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildUrl($page + 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-right"></i></a>
                                    <a href="<?= buildUrl($total_pages) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="p-10 text-center">
                            <i class="fas fa-comments text-5xl text-gray-600 mb-4"></i>
                            <p class="text-gray-500">Tidak ada data konseling untuk filter yang dipilih</p>
                            <button onclick="resetFilters()"
                                class="mt-4 px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm transition-colors">
                                <i class="fas fa-redo mr-2"></i>Reset Filter
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile FAB -->
                <div class="fixed bottom-4 right-4 lg:hidden">
                    <a href="export_konseling.php?format=pdf<?= isset($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
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

            const baseOpts = {
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
                            color: '#6b7280'
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

            // Doughnut Chart
            new Chart(document.getElementById('jenisPieChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Akademik', 'Pribadi', 'Sosial', 'Karir'],
                    datasets: [{
                        data: [
                            <?= $jenis_counts['Akademik'] ?>,
                            <?= $jenis_counts['Pribadi'] ?>,
                            <?= $jenis_counts['Sosial'] ?>,
                            <?= $jenis_counts['Karir'] ?>
                        ],
                        backgroundColor: ['#8B5CF6', '#3B82F6', '#10B981', '#F97316'],
                        borderWidth: 0
                    }]
                },
                options: {
                    ...baseOpts,
                    cutout: '60%',
                    plugins: {
                        ...baseOpts.plugins,
                        legend: {
                            ...baseOpts.plugins.legend,
                            position: 'right',
                            display: window.innerWidth >= 1024
                        }
                    }
                }
            });

            // Trend Line Chart
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
                    ...baseOpts,
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
                                color: 'rgba(0,0,0,0.05)'
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
                                color: 'rgba(0,0,0,0.05)'
                            }
                        }
                    }
                }
            });
        });

        function resetFilters() {
            window.location.href = 'laporan_konseling.php';
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
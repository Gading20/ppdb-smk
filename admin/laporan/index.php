<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Default date range (current month)
$default_start_date = date('Y-m-01'); // First day of current month
$default_end_date = date('Y-m-t');    // Last day of current month

// Get filter parameters
$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;
$kelas = $_GET['kelas'] ?? '';
$jurusan = $_GET['jurusan'] ?? '';
$siswa_id = $_GET['siswa_id'] ?? '';
$status = $_GET['status'] ?? '';

// =============================================
// QUERY UTAMA: Summary per siswa (untuk chart)
// =============================================
$sql = "SELECT s.id as siswa_id, s.nama_lengkap, s.nis, s.kelas, s.jurusan, 
        COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
        COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
        COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
        COUNT(CASE WHEN a.status = 'Terlambat' THEN 1 END) as terlambat,
        COUNT(CASE WHEN a.status = 'Alpha' THEN 1 END) as alpha,
        MAX(a.tanggal) as latest_date
        FROM siswa s 
        LEFT JOIN absensi a ON s.id = a.siswa_id 
            AND a.tanggal BETWEEN :start_date AND :end_date 
            AND a.approval_status = 'Approved'
        WHERE 1=1";

$params = [
    'start_date' => $start_date,
    'end_date'   => $end_date
];

// Apply filters ke WHERE (sebelum GROUP BY)
if ($kelas) {
    $sql .= " AND s.kelas = :kelas";
    $params['kelas'] = $kelas;
}

if ($jurusan) {
    $sql .= " AND s.jurusan = :jurusan";
    $params['jurusan'] = $jurusan;
}

if ($siswa_id) {
    $sql .= " AND s.id = :siswa_id";
    $params['siswa_id'] = $siswa_id;
}

// GROUP BY dulu
$sql .= " GROUP BY s.id, s.nama_lengkap, s.nis, s.kelas, s.jurusan";

// Filter status pakai HAVING (setelah GROUP BY)
if ($status) {
    $sql .= " HAVING SUM(CASE WHEN a.status = :status THEN 1 ELSE 0 END) > 0";
    $params['status'] = $status;
}

// =============================================
// QUERY SUMMARY: Hitung total per status
// =============================================
$summary_sql = "SELECT a.status, COUNT(*) as count 
                FROM absensi a 
                JOIN siswa s ON a.siswa_id = s.id
                WHERE a.tanggal BETWEEN :start_date AND :end_date
                AND a.approval_status = 'Approved'";

$summary_params = [
    'start_date' => $start_date,
    'end_date'   => $end_date
];

if ($kelas) {
    $summary_sql .= " AND s.kelas = :kelas";
    $summary_params['kelas'] = $kelas;
}

if ($jurusan) {
    $summary_sql .= " AND s.jurusan = :jurusan";
    $summary_params['jurusan'] = $jurusan;
}

if ($siswa_id) {
    $summary_sql .= " AND a.siswa_id = :siswa_id";
    $summary_params['siswa_id'] = $siswa_id;
}

if ($status) {
    $summary_sql .= " AND a.status = :status";
    $summary_params['status'] = $status;
}

$summary_sql .= " GROUP BY a.status";

$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->execute($summary_params);
$summary_data = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize status counts
$status_counts = [
    'Hadir'     => 0,
    'Sakit'     => 0,
    'Izin'      => 0,
    'Terlambat' => 0,
    'Alpha'     => 0
];

foreach ($summary_data as $row) {
    if (isset($status_counts[$row['status']])) {
        $status_counts[$row['status']] = $row['count'];
    }
}

$total_absensi = array_sum($status_counts);

// =============================================
// QUERY DAILY: Untuk chart trend harian
// =============================================
$daily_sql = "SELECT DATE(a.tanggal) as date, a.status, COUNT(*) as count
               FROM absensi a 
               JOIN siswa s ON a.siswa_id = s.id
               WHERE a.tanggal BETWEEN :start_date AND :end_date
               AND a.approval_status = 'Approved'";

$daily_params = [
    'start_date' => $start_date,
    'end_date'   => $end_date
];

if ($kelas) {
    $daily_sql .= " AND s.kelas = :kelas";
    $daily_params['kelas'] = $kelas;
}

if ($jurusan) {
    $daily_sql .= " AND s.jurusan = :jurusan";
    $daily_params['jurusan'] = $jurusan;
}

if ($siswa_id) {
    $daily_sql .= " AND a.siswa_id = :siswa_id";
    $daily_params['siswa_id'] = $siswa_id;
}

if ($status) {
    $daily_sql .= " AND a.status = :status";
    $daily_params['status'] = $status;
}

$daily_sql .= " GROUP BY DATE(a.tanggal), a.status ORDER BY DATE(a.tanggal)";

$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->execute($daily_params);
$daily_data = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for daily chart
$chart_dates    = [];
$chart_statuses = [];
$chart_data     = [];

foreach ($daily_data as $row) {
    $date_label = date('d M', strtotime($row['date']));
    $s          = $row['status'];
    $count      = $row['count'];

    if (!in_array($date_label, $chart_dates)) {
        $chart_dates[] = $date_label;
    }
    if (!in_array($s, $chart_statuses)) {
        $chart_statuses[] = $s;
    }
    if (!isset($chart_data[$s])) {
        $chart_data[$s] = [];
    }
    $chart_data[$s][$date_label] = $count;
}

sort($chart_statuses);

// =============================================
// DAFTAR SISWA untuk dropdown filter
// =============================================
$student_sql  = "SELECT id, nis, nama_lengkap, kelas, jurusan FROM siswa ORDER BY nama_lengkap";
$student_stmt = $conn->query($student_sql);
$student_list = $student_stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// PAGINATION & SORTING
// =============================================
$page          = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$items_per_page = 20;
$offset        = ($page - 1) * $items_per_page;

$sort_column = $_GET['sort'] ?? 'tanggal';
$sort_order  = $_GET['order'] ?? 'DESC';

$valid_columns = ['tanggal', 'nama_lengkap', 'nis', 'kelas', 'jurusan', 'status'];
$sort_column   = in_array($sort_column, $valid_columns) ? $sort_column : 'tanggal';

// Tentukan prefix tabel untuk sorting
$sort_prefix = in_array($sort_column, ['tanggal', 'status']) ? 'a.' : 's.';

// =============================================
// QUERY DETAIL: Tabel data kehadiran
// =============================================
$detail_sql = "SELECT a.id, a.tanggal, a.jam_masuk, a.status, a.approval_status, 
               s.id as siswa_id, s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil 
               FROM absensi a
               JOIN siswa s ON a.siswa_id = s.id 
               WHERE a.tanggal BETWEEN :start_date AND :end_date
               AND a.approval_status = 'Approved'";

$detail_params = [
    'start_date' => $start_date,
    'end_date'   => $end_date
];

if ($kelas) {
    $detail_sql .= " AND s.kelas = :kelas";
    $detail_params['kelas'] = $kelas;
}

if ($jurusan) {
    $detail_sql .= " AND s.jurusan = :jurusan";
    $detail_params['jurusan'] = $jurusan;
}

if ($siswa_id) {
    $detail_sql .= " AND a.siswa_id = :siswa_id";
    $detail_params['siswa_id'] = $siswa_id;
}

if ($status) {
    $detail_sql .= " AND a.status = :status";
    $detail_params['status'] = $status;
}

// Sorting
$detail_sql .= " ORDER BY " . $sort_prefix . $sort_column . " " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');

// =============================================
// QUERY COUNT: Total untuk pagination
// =============================================
$detail_count_sql = "SELECT COUNT(*) as total FROM absensi a 
                     JOIN siswa s ON a.siswa_id = s.id 
                     WHERE a.tanggal BETWEEN :start_date AND :end_date
                     AND a.approval_status = 'Approved'";

$detail_count_params = [
    'start_date' => $start_date,
    'end_date'   => $end_date
];

if ($kelas) {
    $detail_count_sql .= " AND s.kelas = :kelas";
    $detail_count_params['kelas'] = $kelas;
}

if ($jurusan) {
    $detail_count_sql .= " AND s.jurusan = :jurusan";
    $detail_count_params['jurusan'] = $jurusan;
}

if ($siswa_id) {
    $detail_count_sql .= " AND a.siswa_id = :siswa_id";
    $detail_count_params['siswa_id'] = $siswa_id;
}

if ($status) {
    $detail_count_sql .= " AND a.status = :status";
    $detail_count_params['status'] = $status;
}

$detail_count_stmt = $conn->prepare($detail_count_sql);
$detail_count_stmt->execute($detail_count_params);
$total_detail_items = $detail_count_stmt->fetchColumn();
$total_pages        = ceil($total_detail_items / $items_per_page);

// Tambahkan LIMIT untuk detail query
$detail_sql .= " LIMIT :offset, :limit";

$detail_stmt = $conn->prepare($detail_sql);

// Bind semua parameter detail
foreach ($detail_params as $key => $value) {
    $detail_stmt->bindValue(':' . $key, $value);
}
$detail_stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$detail_stmt->bindValue(':limit', (int) $items_per_page, PDO::PARAM_INT);
$detail_stmt->execute();
$detail_records = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// HELPER FUNCTIONS
// =============================================
function buildUrl($page = null, $sort = null, $order = null)
{
    $params = $_GET;
    if ($page !== null) $params['page'] = $page;
    if ($sort !== null) $params['sort'] = $sort;
    if ($order !== null) $params['order'] = $order;
    return '?' . http_build_query($params);
}

function getSortIcon($column, $current_sort, $current_order)
{
    if ($column !== $current_sort) {
        return '<i class="fas fa-sort text-gray-500 opacity-50"></i>';
    }
    return $current_order === 'ASC'
        ? '<i class="fas fa-sort-up text-violet-600"></i>'
        : '<i class="fas fa-sort-down text-violet-600"></i>';
}

// Warna per status untuk chart
$status_colors = [
    'Hadir'     => '#10B981',
    'Sakit'     => '#EAB308',
    'Izin'      => '#8B5CF6',
    'Terlambat' => '#F97316',
    'Alpha'     => '#EF4444'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(139, 92, 246, 0.15), rgba(139, 92, 246, 0.05));
            border-left: 4px solid #9333ea;
        }

        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ede9fe 100%);
        }

        .status-hadir {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .status-sakit {
            background: rgba(234, 179, 8, 0.1);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .status-izin {
            background: rgba(139, 92, 246, 0.1);
            color: #8B5CF6;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .status-terlambat {
            background: rgba(249, 115, 22, 0.1);
            color: #F97316;
            border: 1px solid rgba(249, 115, 22, 0.3);
        }

        .status-alpha {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
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

        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        .mobile-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
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
                height: 250px !important;
            }
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">
    <!-- Mobile Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Side Navigation -->
    <aside id="sidebar"
        class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-violet-200 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
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

        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px);">
            <a href="../dashboard/"
                class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                    <i class="fas fa-calendar-check"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                    <li>
                        <a href="../absensi/index.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                            Presensi
                        </a>
                    </li>
                    <li>
                        <a href="../absensi/pelanggaran.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                            Pelanggaran
                        </a>
                    </li>
                    <li>
                        <a href="../absensi/konseling.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                            Konseling
                        </a>
                    </li>
                </ul>
            </li>
            <a href="../siswa/"
                class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-users"></i>
                <span>Data Siswa</span>
            </a>
            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                    <i class="fas fa-file-alt"></i>
                    <span>Laporan</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                    <li>
                        <a href="../laporan/index.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                            Presensi
                        </a>
                    </li>
                    <li>
                        <a href="../laporan/laporan_pelanggaran.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                            Pelanggaran
                        </a>
                    </li>
                    <li>
                        <a href="../laporan/laporan_konseling.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">
                            Konseling
                        </a>
                    </li>
                </ul>
            </li>
            <a href="../profil/"
                class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-user-cog"></i>
                <span>Profil</span>
            </a>
            <a href="../logout.php"
                class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-sky-50 to-indigo-50 transition-all duration-300">
        <!-- Mobile Header -->
        <div
            class="lg:hidden bg-white/90 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-800 p-2 -ml-2 rounded-lg hover:bg-gray-100"
                    aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php
                $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png';
                ?>
                <img src="../../<?= $photo_path ?>" alt="Profile"
                    class="h-8 w-8 rounded-full object-cover border border-violet-300">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Laporan Absensi</h1>
                        <p class="text-gray-500 text-sm md:text-base">Statistik dan rekapitulasi data kehadiran siswa</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="export_absensi.php?format=pdf<?= isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                            class="px-3 py-2 sm:px-4 sm:py-2 bg-red-600 text-white hover:bg-red-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                            <i class="fas fa-file-pdf"></i> <span class="hidden sm:inline">Export PDF</span>
                        </a>
                    </div>
                </header>

                <!-- Filter Section -->
                <div class="glass-effect rounded-xl p-4 sm:p-6 mb-6">
                    <h3 class="font-semibold text-lg mb-4">Filter Laporan</h3>
                    <form method="GET" id="filterForm" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4">
                            <!-- Date Range -->
                            <div class="bg-gray-50/30 rounded-lg p-4">
                                <h4 class="text-sm font-medium mb-3 text-violet-500">
                                    <i class="fas fa-calendar-alt mr-2"></i> Periode Waktu
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Tanggal Mulai</label>
                                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
                                            class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Tanggal Akhir</label>
                                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
                                            class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Filters -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <!-- Kelas & Jurusan -->
                                <div class="bg-gray-50/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-violet-500">
                                        <i class="fas fa-school mr-2"></i> Kelas & Jurusan
                                    </h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">Kelas</label>
                                            <select name="kelas"
                                                class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                                <option value="">Semua Kelas</option>
                                                <option value="10" <?= $kelas === '10' ? 'selected' : '' ?>>Kelas 10</option>
                                                <option value="11" <?= $kelas === '11' ? 'selected' : '' ?>>Kelas 11</option>
                                                <option value="12" <?= $kelas === '12' ? 'selected' : '' ?>>Kelas 12</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">Jurusan</label>
                                            <select name="jurusan"
                                                class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                                <option value="">Semua Jurusan</option>
                                                <option value="TKJ" <?= $jurusan === 'TKJ' ? 'selected' : '' ?>>TKJ</option>
                                                <option value="MP" <?= $jurusan === 'MP'  ? 'selected' : '' ?>>MP</option>
                                                <option value="AKL" <?= $jurusan === 'AKL' ? 'selected' : '' ?>>AKL</option>
                                                <option value="TSM" <?= $jurusan === 'TSM' ? 'selected' : '' ?>>TSM</option>
                                                <option value="TKR" <?= $jurusan === 'TKR' ? 'selected' : '' ?>>TKR</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="bg-gray-50/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-violet-500">
                                        <i class="fas fa-filter mr-2"></i> Status Kehadiran
                                    </h4>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Status</label>
                                        <select name="status"
                                            class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                            <option value="">Semua Status</option>
                                            <option value="Hadir" <?= $status === 'Hadir'     ? 'selected' : '' ?>>Hadir</option>
                                            <option value="Sakit" <?= $status === 'Sakit'     ? 'selected' : '' ?>>Sakit</option>
                                            <option value="Izin" <?= $status === 'Izin'      ? 'selected' : '' ?>>Izin</option>
                                            <option value="Terlambat" <?= $status === 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                            <option value="Alpha" <?= $status === 'Alpha'     ? 'selected' : '' ?>>Alpha</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Siswa -->
                                <div class="bg-gray-50/30 rounded-lg p-4">
                                    <h4 class="text-sm font-medium mb-3 text-violet-500">
                                        <i class="fas fa-user-graduate mr-2"></i> Siswa
                                    </h4>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">Pilih Siswa</label>
                                        <select name="siswa_id"
                                            class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800">
                                            <option value="">Semua Siswa</option>
                                            <?php foreach ($student_list as $student): ?>
                                                <option value="<?= $student['id'] ?>" <?= $siswa_id == $student['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($student['nama_lengkap']) ?> (<?= htmlspecialchars($student['nis']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-300">
                            <div class="w-full sm:w-auto">
                                <?php if (!empty($_GET) && (isset($_GET['start_date']) || isset($_GET['kelas']) || isset($_GET['jurusan']) || isset($_GET['siswa_id']) || isset($_GET['status']))): ?>
                                    <button type="button" onclick="resetFilters()"
                                        class="w-full sm:w-auto px-4 py-2 border border-gray-300 hover:border-gray-400 rounded-lg text-sm text-gray-500 hover:text-gray-800 transition-colors">
                                        <i class="fas fa-redo mr-2"></i> Reset Filter
                                    </button>
                                <?php endif; ?>
                            </div>
                            <button type="submit"
                                class="w-full sm:w-auto px-5 py-2 text-white bg-purple-600 hover:bg-purple-700 rounded-lg text-sm transition-colors">
                                <i class="fas fa-filter mr-2"></i> Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-gray-200">
                        <h3 class="font-semibold text-lg">Data Detail Kehadiran</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Periode: <?= date('d F Y', strtotime($start_date)) ?> - <?= date('d F Y', strtotime($end_date)) ?>
                            <?php if ($status): ?>
                                &nbsp;|&nbsp; Status: <span class="font-medium text-violet-600"><?= htmlspecialchars($status) ?></span>
                            <?php endif; ?>
                            <?php if ($kelas): ?>
                                &nbsp;|&nbsp; Kelas: <span class="font-medium text-violet-600"><?= htmlspecialchars($kelas) ?></span>
                            <?php endif; ?>
                            <?php if ($jurusan): ?>
                                &nbsp;|&nbsp; Jurusan: <span class="font-medium text-violet-600"><?= htmlspecialchars($jurusan) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (count($detail_records) > 0): ?>
                        <div class="overflow-x-auto table-container">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50/50 text-gray-700 text-left">
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'tanggal', $sort_column == 'tanggal' && $sort_order == 'ASC' ? 'DESC' : 'ASC') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">
                                                Tanggal <?= getSortIcon('tanggal', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'nis', $sort_column == 'nis' && $sort_order == 'ASC' ? 'DESC' : 'ASC') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">
                                                NIS <?= getSortIcon('nis', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'nama_lengkap', $sort_column == 'nama_lengkap' && $sort_order == 'ASC' ? 'DESC' : 'ASC') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">
                                                Nama Siswa <?= getSortIcon('nama_lengkap', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'kelas', $sort_column == 'kelas' && $sort_order == 'ASC' ? 'DESC' : 'ASC') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">
                                                Kelas <?= getSortIcon('kelas', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">Jam Masuk</th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildUrl(null, 'status', $sort_column == 'status' && $sort_order == 'ASC' ? 'DESC' : 'ASC') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">
                                                Status <?= getSortIcon('status', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($detail_records as $data): ?>
                                        <tr class="hover:bg-purple-50/30 transition-colors">
                                            <td class="px-6 py-4 text-sm">
                                                <?= date('d/m/Y', strtotime($data['tanggal'])) ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($data['nis']) ?></td>
                                            <td class="px-6 py-4 text-sm">
                                                <div class="flex items-center">
                                                    <img src="../../<?= $data['foto_profil'] ?: 'assets/default/avatar.png' ?>"
                                                        class="w-6 h-6 rounded-full mr-2 object-cover hidden sm:block"
                                                        alt="Profile">
                                                    <?= htmlspecialchars($data['nama_lengkap']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <?= htmlspecialchars($data['kelas']) ?> <?= htmlspecialchars($data['jurusan']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <?= (!empty($data['jam_masuk']) && $data['jam_masuk'] !== '00:00:00')
                                                    ? date('H:i', strtotime($data['jam_masuk']))
                                                    : '-' ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-3 py-1 rounded-full text-xs status-<?= strtolower($data['status']) ?>">
                                                    <?= htmlspecialchars($data['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <a href="../absensi/detail.php?id=<?= $data['id'] ?>"
                                                    class="text-blue-500 hover:text-blue-700">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="p-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <p class="text-sm text-gray-500 order-2 sm:order-1">
                                Menampilkan <?= min($offset + 1, $total_detail_items) ?> -
                                <?= min($offset + $items_per_page, $total_detail_items) ?> dari
                                <?= $total_detail_items ?> data
                            </p>
                            <div class="flex space-x-1 order-1 sm:order-2 pagination-compact">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildUrl(1) ?>"
                                        class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="<?= buildUrl($page - 1) ?>"
                                        class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $range      = 2;
                                $start_page = max($page - $range, 1);
                                $end_page   = min($page + $range, $total_pages);

                                if ($start_page > 1) {
                                    echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                }

                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $is_current = $i == $page;
                                    $class = $is_current
                                        ? 'px-2 sm:px-3 py-1.5 bg-purple-600 text-white rounded text-sm flex items-center justify-center min-w-[32px] current-page page-number'
                                        : 'px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px] page-number';
                                    echo '<a href="' . buildUrl($i) . '" class="' . $class . '">' . $i . '</a>';
                                }

                                if ($end_page < $total_pages) {
                                    echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildUrl($page + 1) ?>"
                                        class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="<?= buildUrl($total_pages) ?>"
                                        class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="p-10 text-center">
                            <i class="fas fa-search text-5xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">Tidak ada data yang ditemukan untuk filter yang dipilih</p>
                            <button onclick="resetFilters()"
                                class="mt-4 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm transition-colors">
                                <i class="fas fa-redo mr-2"></i> Reset Filter
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile FAB -->
                <div class="fixed bottom-4 right-4 lg:hidden flex flex-col gap-2">
                    <a href="export_absensi.php?format=excel<?= isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : '' ?>"
                        class="flex items-center justify-center w-12 h-12 bg-green-600 hover:bg-green-700 text-white rounded-full shadow-lg transition-colors">
                        <i class="fas fa-file-excel text-lg"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const isMobile = window.innerWidth < 768;

            // ---- Pie Chart ----
            const pieCtx = document.getElementById('statusPieChart');
            if (pieCtx) {
                new Chart(pieCtx.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: ['Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpha'],
                        datasets: [{
                            data: [
                                <?= $status_counts['Hadir'] ?>,
                                <?= $status_counts['Terlambat'] ?>,
                                <?= $status_counts['Sakit'] ?>,
                                <?= $status_counts['Izin'] ?>,
                                <?= $status_counts['Alpha'] ?>
                            ],
                            backgroundColor: ['#10B981', '#F97316', '#EAB308', '#8B5CF6', '#EF4444'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                display: !isMobile,
                                position: 'right'
                            }
                        }
                    }
                });
            }

            // ---- Trend Line Chart ----
            const trendCtx = document.getElementById('trendLineChart');
            if (trendCtx) {
                const chartDates = <?= json_encode($chart_dates) ?>;
                const chartData = <?= json_encode($chart_data) ?>;
                const chartStatuses = <?= json_encode($chart_statuses) ?>;
                const statusColors = <?= json_encode($status_colors) ?>;

                const datasets = chartStatuses.map(function(s) {
                    const color = statusColors[s] || '#6B7280';
                    return {
                        label: s,
                        data: chartDates.map(function(d) {
                            return chartData[s] && chartData[s][d] ? chartData[s][d] : 0;
                        }),
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

                new Chart(trendCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: chartDates,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                display: !isMobile,
                                position: 'top'
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    maxRotation: isMobile ? 45 : 0,
                                    minRotation: isMobile ? 45 : 0
                                }
                            }
                        }
                    }
                });
            }
        });

        function resetFilters() {
            window.location.href = 'index.php';
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const isHidden = sidebar.classList.contains('-translate-x-full');

            if (isHidden) {
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
            if (el) {
                el.textContent = new Date().toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
            }
        }

        setInterval(updateMobileTime, 60000);
        updateMobileTime();

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.innerWidth < 1024) {
                const sidebar = document.getElementById('sidebar');
                if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });

        (function setVH() {
            document.documentElement.style.setProperty('--vh', (window.innerHeight * 0.01) + 'px');
            window.addEventListener('resize', setVH);
        })();
    </script>
</body>

</html>
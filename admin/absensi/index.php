<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Handle AJAX approval update (single row)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_approval'])) {
    $id = (int) $_POST['id'];
    $new_status = $_POST['approval_status'];
    $allowed = ['Pending', 'Approved', 'Rejected'];
    if (in_array($new_status, $allowed)) {
        $stmt = $conn->prepare("UPDATE absensi SET approval_status = :status WHERE id = :id");
        $stmt->execute([':status' => $new_status, ':id' => $id]);
        echo json_encode(['success' => true, 'status' => $new_status]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Handle AJAX bulk approval update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approval'])) {
    $ids_raw = $_POST['ids'] ?? [];
    $new_status = $_POST['approval_status'] ?? '';
    $allowed = ['Pending', 'Approved', 'Rejected'];

    // Sanitize IDs
    $ids = array_filter(array_map('intval', (array) $ids_raw));

    if (!empty($ids) && in_array($new_status, $allowed)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE absensi SET approval_status = ? WHERE id IN ($placeholders)");
        $params = array_merge([$new_status], $ids);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Input tidak valid']);
    }
    exit();
}

// Default filter values
$date_filter     = $_GET['date']     ?? date('Y-m-d');
$status_filter   = $_GET['status']   ?? '';
$kelas_filter    = $_GET['kelas']    ?? '';
$jurusan_filter  = $_GET['jurusan']  ?? '';
$approval_filter = $_GET['approval'] ?? '';
$search          = $_GET['search']   ?? '';

// Pagination settings
$items_per_page = 10;
$page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Base queries
$count_sql = "SELECT COUNT(*) as total FROM absensi a JOIN siswa s ON a.siswa_id = s.id WHERE 1=1";
$sql       = "SELECT a.id, a.tanggal, a.jam_masuk, a.status, a.approval_status,
                     s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil
              FROM absensi a
              JOIN siswa s ON a.siswa_id = s.id
              WHERE 1=1";

$params = [];

if ($date_filter) {
    $sql       .= " AND a.tanggal = :date";
    $count_sql .= " AND a.tanggal = :date";
    $params['date'] = $date_filter;
}
if ($status_filter) {
    $sql       .= " AND a.status = :status";
    $count_sql .= " AND a.status = :status";
    $params['status'] = $status_filter;
}
if ($kelas_filter) {
    $sql       .= " AND s.kelas = :kelas";
    $count_sql .= " AND s.kelas = :kelas";
    $params['kelas'] = $kelas_filter;
}
if ($jurusan_filter) {
    $sql       .= " AND s.jurusan = :jurusan";
    $count_sql .= " AND s.jurusan = :jurusan";
    $params['jurusan'] = $jurusan_filter;
}
if ($approval_filter) {
    $sql       .= " AND a.approval_status = :approval";
    $count_sql .= " AND a.approval_status = :approval";
    $params['approval'] = $approval_filter;
}
if ($search) {
    $sql       .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $count_sql .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $params['search'] = "%$search%";
}

$sort_column   = $_GET['sort']  ?? 'tanggal';
$sort_order    = $_GET['order'] ?? 'DESC';
$valid_columns = ['nis', 'nama_lengkap', 'kelas', 'tanggal', 'jam_masuk', 'status', 'approval_status'];
$sort_column   = in_array($sort_column, $valid_columns) ? $sort_column : 'tanggal';
$sort_prefix   = in_array($sort_column, ['nama_lengkap', 'nis', 'kelas']) ? 's.' : 'a.';

$sql .= " ORDER BY " . $sort_prefix . "$sort_column " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');
if ($sort_column != 'tanggal') {
    $sql .= ", a.tanggal DESC";
}
$sql .= " LIMIT :offset, :limit";

$count_stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue(':' . $key, $value);
}
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  (int) $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$absensi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status counts (today, approved only)
$today = date('Y-m-d');
$status_counts = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Terlambat' => 0, 'Alpha' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM absensi WHERE tanggal = :today AND approval_status = 'Approved' GROUP BY status");
$stmt->execute(['today' => $today]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $status_counts[$row['status']] = $row['count'];
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM absensi WHERE approval_status = 'Pending'");
$stmt->execute();
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Helper functions
function buildSortUrl($column)
{
    $params = $_GET;
    $params['sort']  = $column;
    $params['order'] = (isset($_GET['sort']) && $_GET['sort'] === $column && $_GET['order'] === 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query($params);
}

function getSortIcon($column, $sort_column, $sort_order)
{
    if ($column !== $sort_column)
        return '<i class="fas fa-sort text-gray-500 opacity-50"></i>';
    return $sort_order === 'ASC'
        ? '<i class="fas fa-sort-up text-violet-600"></i>'
        : '<i class="fas fa-sort-down text-violet-600"></i>';
}

function buildPaginationUrl($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

$notification = null;
if (isset($_GET['delete'])) {
    $notification = $_GET['delete'] == 'success'
        ? ['type' => 'success', 'message' => 'Data absensi berhasil dihapus']
        : ['type' => 'error',   'message' => 'Gagal menghapus data absensi: ' . ($_GET['message'] ?? 'Terjadi kesalahan')];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Absensi - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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

        /* Status badges */
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

        .status-pending {
            background: rgba(217, 119, 6, 0.1);
            color: #D97706;
            border: 1px solid rgba(217, 119, 6, 0.3);
        }

        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .status-rejected {
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

        @media (max-width:640px) {
            .pagination-compact .page-number {
                display: none;
            }

            .pagination-compact .current-page {
                display: inline-flex;
            }
        }

        /* Approval select */
        .approval-select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            transition: all 0.2s ease;
            padding-right: 2rem;
            min-width: 110px;
        }

        .approval-select.status-pending {
            background: rgba(217, 119, 6, 0.15);
            border-color: rgba(217, 119, 6, 0.4);
            color: #D97706;
        }

        .approval-select.status-approved {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.4);
            color: #10B981;
        }

        .approval-select.status-rejected {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.4);
            color: #EF4444;
        }

        .approval-select option {
            background: #1f2937;
            color: white;
        }

        .approval-wrapper {
            position: relative;
            display: inline-block;
        }

        .approval-wrapper .select-icon {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: 0.65rem;
        }

        .approval-wrapper .loading-spinner {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }

        .approval-wrapper.loading .loading-spinner {
            display: block;
        }

        .approval-wrapper.loading .select-icon {
            display: none;
        }

        /* Bulk toolbar */
        #bulk-toolbar {
            transition: all 0.25s ease;
        }

        /* Row selected highlight */
        tr.row-selected td {
            background: rgba(139, 92, 246, 0.06);
        }

        /* Checkbox custom */
        .row-check,
        #check-all {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #9333ea;
            flex-shrink: 0;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">
    <div id="mobile-overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
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
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px);">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <li class="relative group list-none">
                <button class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                    <i class="fas fa-calendar-check"></i><span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
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
                    <i class="fas fa-file-alt"></i><span>Laporan</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                    <li><a href="../laporan/index.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Presensi</a></li>
                    <li><a href="../laporan/laporan_pelanggaran.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../laporan/laporan_konseling.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Konseling</a></li>
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

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-sky-50 to-indigo-50 transition-all duration-300">
        <!-- Mobile topbar -->
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

                <?php if ($notification): ?>
                    <div class="mb-6 animate-fade-in <?= $notification['type'] === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-500' : 'bg-red-500/10 border-red-500/30 text-red-500' ?> rounded-lg p-4 border flex items-center">
                        <i class="fas <?= $notification['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                        <p class="text-sm"><?= htmlspecialchars($notification['message']) ?></p>
                        <button class="ml-auto text-gray-500 hover:text-gray-700" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>

                <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Data Absensi</h1>
                        <p class="text-gray-500 text-sm md:text-base">Kelola data kehadiran siswa</p>
                    </div>
                    <a href="add.php" class="px-4 py-2 bg-purple-600 text-white hover:bg-purple-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                        <i class="fas fa-plus"></i> Tambah Absensi
                    </a>
                </header>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                    <?php
                    $stats = [
                        ['Hadir',     'green',  'fa-check'],
                        ['Sakit',     'yellow', 'fa-hospital'],
                        ['Izin',      'purple', 'fa-envelope'],
                        ['Terlambat', 'orange', 'fa-clock'],
                        ['Alpha',     'red',    'fa-times'],
                    ];
                    foreach ($stats as [$label, $color, $icon]): ?>
                        <div class="glass-effect rounded-lg p-4 flex items-center">
                            <div class="h-10 w-10 rounded-lg bg-<?= $color ?>-500/10 flex items-center justify-center mr-3">
                                <i class="fas <?= $icon ?> text-<?= $color ?>-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500"><?= $label ?></p>
                                <p class="text-xl font-bold"><?= $status_counts[$label] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Filter & Search -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h3 class="font-medium text-lg mb-2 md:mb-0">Filter & Pencarian</h3>
                        <?php if (!empty(array_filter([$search, $status_filter, $kelas_filter, $jurusan_filter, $approval_filter])) || isset($_GET['date'])): ?>
                            <a href="index.php" class="text-sm flex items-center gap-1 text-violet-500 hover:text-purple-300">
                                <i class="fas fa-times-circle"></i> Reset Filter
                            </a>
                        <?php endif; ?>
                    </div>
                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_column) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                            <!-- Tanggal -->
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Tanggal</label>
                                <div class="relative">
                                    <input type="date" name="date" value="<?= $date_filter ?>" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                    <?php if ($date_filter): ?>
                                        <button type="button" onclick="clearField('date')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i class="fas fa-times-circle"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Status -->
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Status</label>
                                <div class="relative">
                                    <select name="status" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                        <option value="">Semua Status</option>
                                        <?php foreach (['Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($status_filter): ?>
                                        <button type="button" onclick="clearField('status')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i class="fas fa-times-circle"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Kelas -->
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Kelas</label>
                                <div class="relative">
                                    <select name="kelas" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                        <option value="">Semua Kelas</option>
                                        <?php foreach (['10', '11', '12'] as $k): ?>
                                            <option value="<?= $k ?>" <?= $kelas_filter === $k ? 'selected' : '' ?>><?= $k ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($kelas_filter): ?>
                                        <button type="button" onclick="clearField('kelas')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i class="fas fa-times-circle"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Jurusan -->
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Jurusan</label>
                                <div class="relative">
                                    <select name="jurusan" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                        <option value="">Semua Jurusan</option>
                                        <?php foreach (['TKJ', 'MP', 'AKL', 'TSM', 'TKR'] as $j): ?>
                                            <option value="<?= $j ?>" <?= $jurusan_filter === $j ? 'selected' : '' ?>><?= $j ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($jurusan_filter): ?>
                                        <button type="button" onclick="clearField('jurusan')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i class="fas fa-times-circle"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Approval filter -->
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Status Approval</label>
                                <div class="relative">
                                    <select name="approval" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                        <option value="">Semua Status</option>
                                        <?php foreach (['Pending', 'Approved', 'Rejected'] as $a): ?>
                                            <option value="<?= $a ?>" <?= $approval_filter === $a ? 'selected' : '' ?>><?= $a ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($approval_filter): ?>
                                        <button type="button" onclick="clearField('approval')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i class="fas fa-times-circle"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Search -->
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Pencarian Siswa</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama atau NIS..." class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-9 pr-9 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                                    <?php if ($search): ?>
                                        <button type="button" onclick="clearField('search')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i class="fas fa-times-circle"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white hover:bg-purple-700 rounded-lg transition-colors text-sm">
                                <i class="fas fa-filter mr-2"></i>Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <?php if (count($absensi_list) > 0): ?>

                        <!-- ===== BULK TOOLBAR (muncul saat ada yang dipilih) ===== -->
                        <div id="bulk-toolbar" class="hidden items-center gap-2 sm:gap-3 px-4 py-3 bg-violet-50 border-b border-violet-200 flex-wrap">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="check-all-bulk" onchange="toggleAll(this)">
                                <span class="text-sm font-medium text-violet-700">
                                    <span id="selected-count">0</span> siswa dipilih
                                </span>
                            </div>
                            <div class="w-px h-5 bg-violet-200 hidden sm:block"></div>
                            <button onclick="bulkApproval('Approved')"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-green-100 text-green-700 border border-green-300 hover:bg-green-200 transition-colors">
                                <i class="fas fa-check"></i> Approve Semua
                            </button>
                            <button onclick="bulkApproval('Pending')"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-yellow-100 text-yellow-700 border border-yellow-300 hover:bg-yellow-200 transition-colors">
                                <i class="fas fa-clock"></i> Set Pending
                            </button>
                            <button onclick="bulkApproval('Rejected')"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-100 text-red-700 border border-red-300 hover:bg-red-200 transition-colors">
                                <i class="fas fa-times"></i> Reject Semua
                            </button>
                            <button onclick="clearSelection()"
                                class="ml-auto text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                                <i class="fas fa-times-circle"></i> Batal
                            </button>
                        </div>

                        <!-- ===== NORMAL TOOLBAR (header tabel) ===== -->
                        <div id="normal-toolbar" class="overflow-x-auto table-container">
                            <table class="w-full whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-50/50 text-gray-700 text-left">
                                        <!-- Kolom checkbox -->
                                        <th class="px-4 py-3 w-10">
                                            <input type="checkbox" id="check-all" onchange="toggleAll(this)" title="Pilih semua">
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-gray-800">NIS <?= getSortIcon('nis', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-gray-800">Nama <?= getSortIcon('nama_lengkap', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('kelas') ?>" class="flex items-center gap-1 hover:text-gray-800">Kelas <?= getSortIcon('kelas', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-gray-800">Tanggal <?= getSortIcon('tanggal', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('jam_masuk') ?>" class="flex items-center gap-1 hover:text-gray-800">Jam <?= getSortIcon('jam_masuk', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('status') ?>" class="flex items-center gap-1 hover:text-gray-800">Status <?= getSortIcon('status', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">Approval</th>
                                        <th class="px-6 py-3 text-xs font-medium text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="absensi-tbody" class="divide-y divide-gray-100">
                                    <?php foreach ($absensi_list as $absensi): ?>
                                        <tr class="hover:bg-purple-900/5 transition-colors animate-fade-in" id="row-<?= $absensi['id'] ?>">
                                            <!-- Checkbox -->
                                            <td class="px-4 py-4">
                                                <input type="checkbox" class="row-check" value="<?= $absensi['id'] ?>" onchange="updateBulkToolbar()">
                                            </td>
                                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($absensi['nis']) ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <img src="../../<?= $absensi['foto_profil'] ?: 'assets/default/avatar.png' ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover mr-3">
                                                    <span class="text-sm"><?= htmlspecialchars($absensi['nama_lengkap']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($absensi['kelas']) ?> <?= htmlspecialchars($absensi['jurusan']) ?></td>
                                            <td class="px-6 py-4 text-sm"><?= date('d/m/Y', strtotime($absensi['tanggal'])) ?></td>
                                            <td class="px-6 py-4 text-sm">
                                                <?= (!empty($absensi['jam_masuk']) && $absensi['jam_masuk'] !== '00:00:00') ? date('H:i', strtotime($absensi['jam_masuk'])) : '-' ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-3 py-1 rounded-full text-xs status-<?= strtolower($absensi['status']) ?>">
                                                    <?= htmlspecialchars($absensi['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="approval-wrapper">
                                                    <select class="approval-select rounded-lg px-3 py-1.5 text-xs font-medium border focus:outline-none status-<?= strtolower($absensi['approval_status']) ?>"
                                                        data-id="<?= $absensi['id'] ?>"
                                                        data-prev="<?= htmlspecialchars($absensi['approval_status']) ?>"
                                                        onchange="updateApproval(this)">
                                                        <option value="Pending" <?= $absensi['approval_status'] === 'Pending'  ? 'selected' : '' ?>>⏳ Pending</option>
                                                        <option value="Approved" <?= $absensi['approval_status'] === 'Approved' ? 'selected' : '' ?>>✅ Approved</option>
                                                        <option value="Rejected" <?= $absensi['approval_status'] === 'Rejected' ? 'selected' : '' ?>>❌ Rejected</option>
                                                    </select>
                                                    <span class="select-icon text-gray-500"><i class="fas fa-chevron-down"></i></span>
                                                    <span class="loading-spinner"><i class="fas fa-spinner fa-spin text-gray-500 text-xs"></i></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex justify-center space-x-2">
                                                    <a href="detail.php?id=<?= $absensi['id'] ?>"
                                                        class="text-blue-400 hover:text-blue-300 p-1.5 rounded-full hover:bg-blue-500/10" title="Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="p-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <p class="text-sm text-gray-500 order-2 sm:order-1">
                                Menampilkan <?= min($offset + 1, $total_items) ?> - <?= min($offset + $items_per_page, $total_items) ?> dari <?= $total_items ?> data
                            </p>
                            <div class="flex space-x-1 order-1 sm:order-2 pagination-compact">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildPaginationUrl(1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= buildPaginationUrl($page - 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-left"></i></a>
                                <?php endif; ?>
                                <?php
                                $range      = 2;
                                $start_page = max($page - $range, 1);
                                $end_page   = min($page + $range, $total_pages);
                                if ($start_page > 1) echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center">...</span>';
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $cls = $i == $page
                                        ? 'px-2 sm:px-3 py-1.5 bg-purple-600 text-white rounded text-sm flex items-center justify-center min-w-[32px] current-page page-number'
                                        : 'px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px] page-number';
                                    echo '<a href="' . buildPaginationUrl($i) . '" class="' . $cls . '">' . $i . '</a>';
                                }
                                if ($end_page < $total_pages) echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center">...</span>';
                                ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildPaginationUrl($page + 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-right"></i></a>
                                    <a href="<?= buildPaginationUrl($total_pages) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="p-10 text-center">
                            <i class="fas fa-calendar-day text-5xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">Tidak ada data absensi yang ditemukan</p>
                            <?php if (!empty($_GET)): ?>
                                <a href="index.php" class="mt-4 inline-block text-violet-500 hover:text-violet-600">
                                    <i class="fas fa-arrow-left mr-1"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- FAB Mobile -->
                <div class="fixed bottom-4 right-4 lg:hidden">
                    <a href="add.php" class="flex items-center justify-center w-14 h-14 bg-purple-600 hover:bg-purple-700 rounded-full shadow-lg transition-colors">
                        <i class="fas fa-plus text-white text-lg"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideDeleteModal()"></div>
        <div class="glass-effect rounded-lg p-6 w-11/12 max-w-md relative z-10">
            <h3 class="text-xl font-semibold mb-4">Konfirmasi Hapus</h3>
            <p class="text-gray-700 mb-6">Apakah Anda yakin ingin menghapus data absensi ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end gap-4">
                <button onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm">Batal</button>
                <form id="deleteForm" method="POST" action="delete.php">
                    <input type="hidden" id="deleteId" name="id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden transition-all">
        <div id="toast-inner" class="px-5 py-3 rounded-lg text-sm font-medium shadow-lg flex items-center gap-2"></div>
    </div>
    <script>
        // =============================================
        // BULK APPROVAL - CHECKBOX LOGIC
        // =============================================
        function getSelectedIds() {
            return [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
        }

        function updateBulkToolbar() {
            const allChecks = document.querySelectorAll('.row-check');
            const selected = [...allChecks].filter(c => c.checked);

            document.getElementById('selected-count').textContent = selected.length;

            const bulkToolbar = document.getElementById('bulk-toolbar');
            const checkAll = document.getElementById('check-all');
            const checkAllBulk = document.getElementById('check-all-bulk');

            if (selected.length > 0) {
                bulkToolbar.classList.remove('hidden');
                bulkToolbar.classList.add('flex');
            } else {
                bulkToolbar.classList.add('hidden');
                bulkToolbar.classList.remove('flex');
            }

            // Sinkronisasi state "pilih semua"
            const isAll = selected.length === allChecks.length && allChecks.length > 0;
            if (checkAll) checkAll.checked = isAll;
            if (checkAllBulk) checkAllBulk.checked = isAll;

            // Highlight baris yang dipilih
            document.querySelectorAll('#absensi-tbody tr').forEach(tr => {
                const cb = tr.querySelector('.row-check');
                tr.classList.toggle('row-selected', cb ? cb.checked : false);
            });
        }

        function toggleAll(src) {
            document.querySelectorAll('.row-check').forEach(c => c.checked = src.checked);
            // Sinkronisasi kedua checkbox "pilih semua"
            const other = src.id === 'check-all' ? 'check-all-bulk' : 'check-all';
            const el = document.getElementById(other);
            if (el) el.checked = src.checked;
            updateBulkToolbar();
        }

        function clearSelection() {
            document.querySelectorAll('.row-check').forEach(c => c.checked = false);
            const ca = document.getElementById('check-all');
            const cab = document.getElementById('check-all-bulk');
            if (ca) ca.checked = false;
            if (cab) cab.checked = false;
            updateBulkToolbar();
        }

        async function bulkApproval(status) {
            const ids = getSelectedIds();
            if (ids.length === 0) return;

            // Konfirmasi singkat
            const label = {
                Approved: 'Approve',
                Pending: 'Set Pending',
                Rejected: 'Reject'
            } [status];
            if (!confirm(`${label} ${ids.length} data absensi yang dipilih?`)) return;

            try {
                const formData = new FormData();
                formData.append('bulk_approval', '1');
                formData.append('approval_status', status);
                ids.forEach(id => formData.append('ids[]', id));

                const res = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    // Update tampilan select tiap baris yang terpilih
                    ids.forEach(id => {
                        const select = document.querySelector(`.approval-select[data-id="${id}"]`);
                        if (select) {
                            select.value = status;
                            select.className = select.className
                                .replace(/status-pending|status-approved|status-rejected/g, '')
                                .trim() + ' status-' + status.toLowerCase();
                            select.dataset.prev = status;
                        }
                    });
                    showToast(`✅ ${data.updated} data berhasil diubah ke ${status}`, 'success');
                    clearSelection();
                } else {
                    showToast('❌ Gagal memperbarui data', 'error');
                }
            } catch (e) {
                showToast('❌ Terjadi kesalahan jaringan', 'error');
            }
        }

        // =============================================
        // SINGLE ROW APPROVAL UPDATE (AJAX)
        // =============================================
        async function updateApproval(select) {
            const id = select.dataset.id;
            const newStatus = select.value;
            const wrapper = select.closest('.approval-wrapper');

            wrapper.classList.add('loading');
            select.disabled = true;

            try {
                const formData = new FormData();
                formData.append('update_approval', '1');
                formData.append('id', id);
                formData.append('approval_status', newStatus);

                const res = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    select.className = select.className
                        .replace(/status-pending|status-approved|status-rejected/g, '')
                        .trim() + ' status-' + newStatus.toLowerCase();
                    select.dataset.prev = newStatus;
                    showToast('✅ Status berhasil diubah ke ' + newStatus, 'success');
                } else {
                    showToast('❌ Gagal mengubah status', 'error');
                    select.value = select.dataset.prev || select.value;
                }
            } catch (e) {
                showToast('❌ Terjadi kesalahan jaringan', 'error');
                select.value = select.dataset.prev || select.value;
            } finally {
                wrapper.classList.remove('loading');
                select.disabled = false;
            }
        }

        // =============================================
        // TOAST NOTIFICATION
        // =============================================
        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            const inner = document.getElementById('toast-inner');
            inner.className = 'px-5 py-3 rounded-lg text-sm font-medium shadow-lg flex items-center gap-2 ' +
                (type === 'success' ?
                    'bg-green-100 text-green-700 border border-green-300' :
                    'bg-red-100 text-red-700 border border-red-300');
            inner.textContent = msg;
            toast.classList.remove('hidden');
            clearTimeout(toast._timer);
            toast._timer = setTimeout(() => toast.classList.add('hidden'), 3000);
        }

        // =============================================
        // FILTER FORM - auto submit on change
        // =============================================
        document.querySelectorAll('#filterForm select, #filterForm input[type="date"]').forEach(el => {
            el.addEventListener('change', () => document.getElementById('filterForm').submit());
        });

        function clearField(fieldName) {
            const field = document.querySelector(`#filterForm [name="${fieldName}"]`);
            if (field) {
                field.value = '';
                document.getElementById('filterForm').submit();
            }
        }

        // =============================================
        // DELETE MODAL
        // =============================================
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // =============================================
        // SIDEBAR & MOBILE
        // =============================================
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const isHidden = sidebar.classList.contains('-translate-x-full');
            sidebar.classList.toggle('-translate-x-full', !isHidden);
            overlay.classList.toggle('hidden', !isHidden);
            document.body.classList.toggle('overflow-hidden', isHidden);
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    hideDeleteModal();
                    return;
                }
                if (window.innerWidth < 1024) toggleSidebar();
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
    </script>
</body>

</html>
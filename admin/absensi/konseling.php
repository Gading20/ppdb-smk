<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Handle AJAX status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = (int) $_POST['id'];
    $new_status = $_POST['status'];

    $allowed = ['Proses', 'Selesai', 'Ditunda'];
    if (in_array($new_status, $allowed)) {
        $stmt = $conn->prepare("UPDATE konseling SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':status' => $new_status, ':id' => $id]);
        echo json_encode(['success' => true, 'status' => $new_status]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Default filter values
$date_filter    = $_GET['date'] ?? '';
$jenis_filter   = $_GET['jenis'] ?? '';
$status_filter  = $_GET['status'] ?? '';
$search         = $_GET['search'] ?? '';

// Pagination
$items_per_page = 10;
$page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Base queries
$base_join = "FROM konseling k
              JOIN siswa s ON k.siswa_id = s.id
              WHERE 1=1";

$count_sql = "SELECT COUNT(*) as total " . $base_join;
$sql = "SELECT k.id, k.tanggal, k.jenis_konseling, k.masalah, k.solusi,
               k.tindak_lanjut, k.konselor, k.status, k.created_at,
               s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil " . $base_join;

$params = [];

if ($date_filter) {
    $sql       .= " AND k.tanggal = :date";
    $count_sql .= " AND k.tanggal = :date";
    $params['date'] = $date_filter;
}
if ($jenis_filter) {
    $sql       .= " AND k.jenis_konseling = :jenis";
    $count_sql .= " AND k.jenis_konseling = :jenis";
    $params['jenis'] = $jenis_filter;
}
if ($status_filter) {
    $sql       .= " AND k.status = :status";
    $count_sql .= " AND k.status = :status";
    $params['status'] = $status_filter;
}
if ($search) {
    $sql       .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search OR k.konselor LIKE :search)";
    $count_sql .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search OR k.konselor LIKE :search)";
    $params['search'] = "%$search%";
}

// Sorting
$sort_column  = $_GET['sort'] ?? 'tanggal';
$sort_order   = $_GET['order'] ?? 'DESC';
$valid_columns = ['nis', 'nama_lengkap', 'kelas', 'tanggal', 'jenis_konseling', 'konselor', 'status'];
$sort_column  = in_array($sort_column, $valid_columns) ? $sort_column : 'tanggal';
$sort_prefix  = in_array($sort_column, ['nama_lengkap', 'nis', 'kelas']) ? 's.' : 'k.';

$sql .= " ORDER BY " . $sort_prefix . "$sort_column " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');
if ($sort_column !== 'tanggal') $sql .= ", k.tanggal DESC";
$sql .= " LIMIT :offset, :limit";

// Execute count
$count_stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) $count_stmt->bindValue(':' . $key, $value);
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Execute data
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
$stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  (int) $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$konseling_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status counts
$status_counts = ['Proses' => 0, 'Selesai' => 0, 'Ditunda' => 0];
$sc = $conn->query("SELECT status, COUNT(*) as count FROM konseling GROUP BY status");
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($status_counts[$row['status']])) $status_counts[$row['status']] = $row['count'];
}

// Total records
$total_count = $conn->query("SELECT COUNT(*) FROM konseling")->fetchColumn();

// Jenis konseling options
$jenis_options = ['Akademik', 'Pribadi', 'Sosial', 'Karir', 'Keluarga', 'Lainnya'];

function buildSortUrl($column)
{
    $params = $_GET;
    $params['sort']  = $column;
    $params['order'] = (isset($_GET['sort']) && $_GET['sort'] === $column && $_GET['order'] === 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query($params);
}

function getSortIcon($column, $sort_column, $sort_order)
{
    if ($column !== $sort_column) return '<i class="fas fa-sort text-gray-500 opacity-50"></i>';
    return $sort_order === 'ASC'
        ? '<i class="fas fa-sort-up text-purple-500"></i>'
        : '<i class="fas fa-sort-down text-purple-500"></i>';
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
        ? ['type' => 'success', 'message' => 'Data konseling berhasil dihapus']
        : ['type' => 'error',   'message' => 'Gagal menghapus data konseling: ' . ($_GET['message'] ?? 'Terjadi kesalahan')];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Konseling - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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

        .status-proses {
            background: rgba(59, 130, 246, .1);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, .3);
        }

        .status-selesai {
            background: rgba(16, 185, 129, .1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .status-ditunda {
            background: rgba(239, 68, 68, .1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, .3);
        }

        .jenis-akademik {
            background: rgba(139, 92, 246, .1);
            color: #8B5CF6;
            border: 1px solid rgba(139, 92, 246, .3);
        }

        .jenis-pribadi {
            background: rgba(236, 72, 153, .1);
            color: #EC4899;
            border: 1px solid rgba(236, 72, 153, .3);
        }

        .jenis-sosial {
            background: rgba(16, 185, 129, .1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .jenis-karir {
            background: rgba(234, 179, 8, .1);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, .3);
        }

        .jenis-keluarga {
            background: rgba(249, 115, 22, .1);
            color: #F97316;
            border: 1px solid rgba(249, 115, 22, .3);
        }

        .jenis-lainnya {
            background: rgba(107, 114, 128, .1);
            color: #6B7280;
            border: 1px solid rgba(107, 114, 128, .3);
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
            animation: fadeIn .3s ease forwards;
        }

        .sidebar-transition {
            transition: transform .3s ease-in-out;
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

        /* Status select */
        .status-select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            transition: all .2s ease;
            padding-right: 2rem;
            min-width: 110px;
        }

        .status-select.status-proses {
            background: rgba(59, 130, 246, .15);
            border-color: rgba(59, 130, 246, .4);
            color: #3B82F6;
        }

        .status-select.status-selesai {
            background: rgba(16, 185, 129, .15);
            border-color: rgba(16, 185, 129, .4);
            color: #10B981;
        }

        .status-select.status-ditunda {
            background: rgba(239, 68, 68, .15);
            border-color: rgba(239, 68, 68, .4);
            color: #EF4444;
        }

        .status-select option {
            background: #1f2937;
            color: white;
        }

        .status-wrapper {
            position: relative;
            display: inline-block;
        }

        .status-wrapper .select-icon {
            position: absolute;
            right: .5rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            font-size: .65rem;
        }

        .status-wrapper .loading-spinner {
            position: absolute;
            right: .5rem;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }

        .status-wrapper.loading .loading-spinner {
            display: block;
        }

        .status-wrapper.loading .select-icon {
            display: none;
        }

        .truncate-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">
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
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <li class="relative group">
                <button class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-calendar-check"></i><span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                    <li><a href="../absensi/index.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a></li>
                    <li><a href="../absensi/pelanggaran.php" class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../absensi/konseling.php" class="block p-2 text-purple-400 bg-purple-500/10 rounded-lg">Konseling</a></li>
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
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
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
        <!-- Mobile Top Bar -->
        <div class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="Logo" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-7xl mx-auto">

                <?php if ($notification): ?>
                    <div class="mb-6 animate-fade-in <?= $notification['type'] === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-500' : 'bg-red-500/10 border-red-500/30 text-red-500' ?> rounded-lg p-4 border flex items-center">
                        <i class="fas <?= $notification['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                        <p class="text-sm"><?= htmlspecialchars($notification['message']) ?></p>
                        <button class="ml-auto text-gray-400 hover:text-gray-300" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Data Konseling</h1>
                        <p class="text-gray-400 text-sm md:text-base">Kelola data konseling siswa</p>
                    </div>
                    <a href="addk.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                        <i class="fas fa-plus"></i> Tambah Konseling
                    </a>
                </header>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Total -->
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-purple-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-clipboard-list text-purple-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Total Konseling</p>
                            <p class="text-xl font-bold"><?= $total_count ?></p>
                        </div>
                    </div>
                    <!-- Proses -->
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-blue-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-spinner text-blue-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Proses</p>
                            <p class="text-xl font-bold"><?= $status_counts['Proses'] ?></p>
                        </div>
                    </div>
                    <!-- Selesai -->
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-green-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Selesai</p>
                            <p class="text-xl font-bold"><?= $status_counts['Selesai'] ?></p>
                        </div>
                    </div>
                    <!-- Ditunda -->
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-red-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-pause-circle text-red-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Ditunda</p>
                            <p class="text-xl font-bold"><?= $status_counts['Ditunda'] ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h3 class="font-medium text-lg mb-2 md:mb-0">Filter & Pencarian</h3>
                        <?php if (!empty(array_filter([$search, $jenis_filter, $status_filter, $date_filter]))): ?>
                            <a href="konseling.php" class="text-sm flex items-center gap-1 text-purple-400 hover:text-purple-300">
                                <i class="fas fa-times-circle"></i> Reset Filter
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_column) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                            <!-- Tanggal -->
                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Tanggal</label>
                                <div class="relative">
                                    <input type="date" name="date" value="<?= $date_filter ?>"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                    <?php if ($date_filter): ?>
                                        <button type="button" onclick="clearField('date')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Jenis Konseling -->
                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Jenis Konseling</label>
                                <div class="relative">
                                    <select name="jenis" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                        <option value="">Semua Jenis</option>
                                        <?php foreach ($jenis_options as $j): ?>
                                            <option value="<?= $j ?>" <?= $jenis_filter === $j ? 'selected' : '' ?>><?= $j ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($jenis_filter): ?>
                                        <button type="button" onclick="clearField('jenis')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Status</label>
                                <div class="relative">
                                    <select name="status" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                        <option value="">Semua Status</option>
                                        <?php foreach (['Proses', 'Selesai', 'Ditunda'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($status_filter): ?>
                                        <button type="button" onclick="clearField('status')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pencarian -->
                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Pencarian</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama, NIS, atau Konselor..."
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg pl-9 pr-9 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                                    <?php if ($search): ?>
                                        <button type="button" onclick="clearField('search')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors text-sm">
                                <i class="fas fa-filter mr-2"></i>Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <?php if (count($konseling_list) > 0): ?>
                        <div class="overflow-x-auto table-container">
                            <table class="w-full whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-800/50 text-gray-300 text-left">
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-white">NIS <?= getSortIcon('nis', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-white">Nama <?= getSortIcon('nama_lengkap', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('kelas') ?>" class="flex items-center gap-1 hover:text-white">Kelas <?= getSortIcon('kelas', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-white">Tanggal <?= getSortIcon('tanggal', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('jenis_konseling') ?>" class="flex items-center gap-1 hover:text-white">Jenis <?= getSortIcon('jenis_konseling', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">Masalah</th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('konselor') ?>" class="flex items-center gap-1 hover:text-white">Konselor <?= getSortIcon('konselor', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('status') ?>" class="flex items-center gap-1 hover:text-white">Status <?= getSortIcon('status', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    <?php foreach ($konseling_list as $k): ?>
                                        <tr class="hover:bg-purple-900/5 transition-colors animate-fade-in" id="row-<?= $k['id'] ?>">
                                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($k['nis']) ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <img src="../../<?= $k['foto_profil'] ?: 'assets/default/avatar.png' ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover mr-3">
                                                    <span class="text-sm"><?= htmlspecialchars($k['nama_lengkap']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm"><?= $k['kelas'] ?> <?= $k['jurusan'] ?></td>
                                            <td class="px-6 py-4 text-sm"><?= date('d/m/Y', strtotime($k['tanggal'])) ?></td>
                                            <td class="px-6 py-4">
                                                <span class="px-3 py-1 rounded-full text-xs jenis-<?= strtolower($k['jenis_konseling']) ?>">
                                                    <?= htmlspecialchars($k['jenis_konseling']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <div class="truncate-cell" title="<?= htmlspecialchars($k['masalah']) ?>">
                                                    <?= htmlspecialchars($k['masalah']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($k['konselor']) ?></td>
                                            <td class="px-6 py-4">
                                                <!-- Inline status select -->
                                                <div class="status-wrapper">
                                                    <select class="status-select rounded-lg px-3 py-1.5 text-xs font-medium border focus:outline-none status-<?= strtolower($k['status']) ?>"
                                                        data-id="<?= $k['id'] ?>" onchange="updateStatus(this)">
                                                        <option value="Proses" <?= $k['status'] === 'Proses'  ? 'selected' : '' ?>>⏳ Proses</option>
                                                        <option value="Selesai" <?= $k['status'] === 'Selesai' ? 'selected' : '' ?>>✅ Selesai</option>
                                                        <option value="Ditunda" <?= $k['status'] === 'Ditunda' ? 'selected' : '' ?>>⏸ Ditunda</option>
                                                    </select>
                                                    <span class="select-icon text-gray-400"><i class="fas fa-chevron-down"></i></span>
                                                    <span class="loading-spinner"><i class="fas fa-spinner fa-spin text-gray-400 text-xs"></i></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex justify-center space-x-2">
                                                    <a href="detailk.php?id=<?= $k['id'] ?>"
                                                        class="text-blue-400 hover:text-blue-300 p-1.5 rounded-full hover:bg-blue-500/10" title="Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="editk.php?id=<?= $k['id'] ?>"
                                                        class="text-yellow-400 hover:text-yellow-300 p-1.5 rounded-full hover:bg-yellow-500/10" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?= $k['id'] ?>)"
                                                        class="text-red-400 hover:text-red-300 p-1.5 rounded-full hover:bg-red-500/10" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="p-4 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <p class="text-sm text-gray-400 order-2 sm:order-1">
                                Menampilkan <?= min($offset + 1, $total_items) ?> - <?= min($offset + $items_per_page, $total_items) ?> dari <?= $total_items ?> data
                            </p>
                            <div class="flex space-x-1 order-1 sm:order-2 pagination-compact">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildPaginationUrl(1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= buildPaginationUrl($page - 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-left"></i></a>
                                <?php endif; ?>

                                <?php
                                $range = 2;
                                $start_page = max($page - $range, 1);
                                $end_page   = min($page + $range, $total_pages);
                                if ($start_page > 1) echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center">...</span>';
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $cls = $i == $page
                                        ? 'px-2 sm:px-3 py-1.5 bg-purple-600 rounded text-white text-sm flex items-center justify-center min-w-[32px] current-page page-number'
                                        : 'px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px] page-number';
                                    echo '<a href="' . buildPaginationUrl($i) . '" class="' . $cls . '">' . $i . '</a>';
                                }
                                if ($end_page < $total_pages) echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center">...</span>';
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildPaginationUrl($page + 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-right"></i></a>
                                    <a href="<?= buildPaginationUrl($total_pages) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]"><i class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="p-10 text-center">
                            <i class="fas fa-comments text-5xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400">Tidak ada data konseling yang ditemukan</p>
                            <?php if (!empty($_GET)): ?>
                                <a href="konseling.php" class="mt-4 inline-block text-purple-400 hover:text-purple-500">
                                    <i class="fas fa-arrow-left mr-1"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- FAB Mobile -->
                <div class="fixed bottom-4 right-4 lg:hidden">
                    <a href="addk.php" class="flex items-center justify-center w-14 h-14 bg-purple-600 hover:bg-purple-700 rounded-full shadow-lg transition-colors">
                        <i class="fas fa-plus text-lg"></i>
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
            <p class="text-gray-300 mb-6">Apakah Anda yakin ingin menghapus data konseling ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end gap-4">
                <button onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm">Batal</button>
                <form id="deleteForm" method="POST" action="deletek.php">
                    <input type="hidden" id="deleteId" name="id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-60" onclick="hideDetailModal()"></div>
        <div class="glass-effect rounded-xl p-6 w-11/12 max-w-lg relative z-10 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-semibold">Detail Konseling</h3>
                <button onclick="hideDetailModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="detailContent" class="space-y-4 text-sm"></div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden">
        <div id="toast-inner" class="px-5 py-3 rounded-lg text-sm font-medium shadow-lg flex items-center gap-2"></div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // =============================================
        // INLINE STATUS UPDATE (AJAX)
        // =============================================
        async function updateStatus(select) {
            const id = select.dataset.id;
            const newStatus = select.value;
            const wrapper = select.closest('.status-wrapper');

            wrapper.classList.add('loading');
            select.disabled = true;

            try {
                const fd = new FormData();
                fd.append('update_status', '1');
                fd.append('id', id);
                fd.append('status', newStatus);

                const res = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    select.className = select.className
                        .replace(/status-proses|status-selesai|status-ditunda/g, '')
                        .trim();
                    select.classList.add('status-' + newStatus.toLowerCase());
                    showToast('✅ Status berhasil diubah ke ' + newStatus, 'success');
                } else {
                    showToast('❌ Gagal mengubah status', 'error');
                    select.value = select.dataset.prev || select.value;
                }
            } catch (e) {
                showToast('❌ Terjadi kesalahan jaringan', 'error');
            } finally {
                wrapper.classList.remove('loading');
                select.disabled = false;
                select.dataset.prev = newStatus;
            }
        }

        // Simpan nilai awal
        document.querySelectorAll('.status-select').forEach(sel => {
            sel.dataset.prev = sel.value;
        });

        // =============================================
        // TOAST
        // =============================================
        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            const inner = document.getElementById('toast-inner');
            inner.className = 'px-5 py-3 rounded-lg text-sm font-medium shadow-lg flex items-center gap-2 ' +
                (type === 'success' ?
                    'bg-green-500/20 text-green-400 border border-green-500/30' :
                    'bg-red-500/20 text-red-400 border border-red-500/30');
            inner.textContent = msg;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        }

        // =============================================
        // FILTER - auto submit
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
        // DETAIL MODAL (opsional - bisa diarahkan ke detail_konseling.php)
        // =============================================
        function hideDetailModal() {
            document.getElementById('detailModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // =============================================
        // SIDEBAR
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
                if (!document.getElementById('detailModal').classList.contains('hidden')) {
                    hideDetailModal();
                    return;
                }
                if (window.innerWidth < 1024) toggleSidebar();
            }
        });

        // Mobile clock
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
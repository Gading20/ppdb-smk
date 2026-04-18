<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Default filter values
$date_filter = $_GET['date'] ?? '';
$jenis_filter = $_GET['jenis'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';
$jurusan_filter = $_GET['jurusan'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Base query for count
$count_sql = "SELECT COUNT(*) as total FROM pelanggaran p
              JOIN siswa s ON p.siswa_id = s.id 
              WHERE 1=1";

// Base query for data — tambah subquery total_poin akumulasi per siswa + poin per jenis
$sql = "SELECT p.id, p.tanggal, p.jenis_pelanggaran, p.deskripsi, p.status, p.tindakan,
        s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil,
        COALESCE(NULLIF(p.poin, 0), dp.poin_default, 0) AS poin,
        CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0), dp2.poin_default, 0)) FROM pelanggaran pp LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama = pp.deskripsi AND dp2.jenis = pp.jenis_pelanggaran WHERE pp.siswa_id = s.id), 0) AS UNSIGNED) AS total_poin,
        CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0), dp2.poin_default, 0)) FROM pelanggaran pp LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama = pp.deskripsi AND dp2.jenis = pp.jenis_pelanggaran WHERE pp.siswa_id = s.id AND pp.jenis_pelanggaran = 'Berat'), 0) AS UNSIGNED) AS poin_berat,
        CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0), dp2.poin_default, 0)) FROM pelanggaran pp LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama = pp.deskripsi AND dp2.jenis = pp.jenis_pelanggaran WHERE pp.siswa_id = s.id AND pp.jenis_pelanggaran = 'Sedang'), 0) AS UNSIGNED) AS poin_sedang,
        CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0), dp2.poin_default, 0)) FROM pelanggaran pp LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama = pp.deskripsi AND dp2.jenis = pp.jenis_pelanggaran WHERE pp.siswa_id = s.id AND pp.jenis_pelanggaran = 'Ringan'), 0) AS UNSIGNED) AS poin_ringan
        FROM pelanggaran p
        JOIN siswa s ON p.siswa_id = s.id
        LEFT JOIN deskripsi_pelanggaran dp ON dp.nama = p.deskripsi AND dp.jenis = p.jenis_pelanggaran
        WHERE 1=1";

$params = [];

// Apply filters
if ($date_filter) {
    $sql .= " AND p.tanggal = :date";
    $count_sql .= " AND p.tanggal = :date";
    $params['date'] = $date_filter;
}
if ($jenis_filter) {
    $sql .= " AND p.jenis_pelanggaran = :jenis";
    $count_sql .= " AND p.jenis_pelanggaran = :jenis";
    $params['jenis'] = $jenis_filter;
}
if ($kelas_filter) {
    $sql .= " AND s.kelas = :kelas";
    $count_sql .= " AND s.kelas = :kelas";
    $params['kelas'] = $kelas_filter;
}
if ($jurusan_filter) {
    $sql .= " AND s.jurusan = :jurusan";
    $count_sql .= " AND s.jurusan = :jurusan";
    $params['jurusan'] = $jurusan_filter;
}
if ($status_filter) {
    $sql .= " AND p.status = :status";
    $count_sql .= " AND p.status = :status";
    $params['status'] = $status_filter;
}
if ($search) {
    $sql .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $count_sql .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $params['search'] = "%$search%";
}

// Sort
$sort_column = $_GET['sort'] ?? 'tanggal';
$sort_order = $_GET['order'] ?? 'DESC';
$valid_columns = ['nis', 'nama_lengkap', 'kelas', 'tanggal', 'jenis_pelanggaran', 'poin', 'status', 'total_poin'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'tanggal';

if ($sort_column === 'total_poin') {
    $sql .= " ORDER BY total_poin " . ($sort_order === 'ASC' ? 'ASC' : 'DESC') . ", p.tanggal DESC";
} else {
    $sort_prefix = in_array($sort_column, ['nama_lengkap', 'nis', 'kelas']) ? 's.' : 'p.';
    $sql .= " ORDER BY " . $sort_prefix . "$sort_column " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');
    if ($sort_column !== 'tanggal')
        $sql .= ", p.tanggal DESC";
}

$sql .= " LIMIT :offset, :limit";

// Total count
$count_stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value)
    $count_stmt->bindValue(':' . $key, $value);
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Main query
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value)
    $stmt->bindValue(':' . $key, $value);
$stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int) $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$pelanggaran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics — per jenis hari ini
$today = date('Y-m-d');
$jenis_counts = ['Ringan' => 0, 'Sedang' => 0, 'Berat' => 0];
$stmt_stat = $conn->prepare("SELECT jenis_pelanggaran, COUNT(*) as count FROM pelanggaran WHERE tanggal = :today GROUP BY jenis_pelanggaran");
$stmt_stat->execute(['today' => $today]);
foreach ($stmt_stat->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($jenis_counts[$row['jenis_pelanggaran']]))
        $jenis_counts[$row['jenis_pelanggaran']] = $row['count'];
}

// Belum ditindak
$proses_count = $conn->query("SELECT COUNT(*) FROM pelanggaran WHERE status = 'Proses'")->fetchColumn();

// Helper functions
function buildSortUrl($column)
{
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = (isset($_GET['sort']) && $_GET['sort'] === $column && ($_GET['order'] ?? '') === 'ASC') ? 'DESC' : 'ASC';
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
function totalPoinColor($poin)
{
    if ($poin >= 75)
        return ['bar' => 'bg-red-500', 'text' => 'text-red-400', 'bg' => 'bg-red-500/10 border-red-500/30'];
    if ($poin >= 50)
        return ['bar' => 'bg-orange-500', 'text' => 'text-orange-400', 'bg' => 'bg-orange-500/10 border-orange-500/30'];
    if ($poin >= 25)
        return ['bar' => 'bg-yellow-500', 'text' => 'text-amber-600', 'bg' => 'bg-yellow-500/10 border-yellow-500/30'];
    return ['bar' => 'bg-green-500', 'text' => 'text-green-600', 'bg' => 'bg-green-500/10 border-green-500/30'];
}

// Notification
$notification = null;
if (isset($_GET['created']))
    $notification = ['type' => 'success', 'message' => 'Data pelanggaran berhasil disimpan'];
elseif (isset($_GET['updated']))
    $notification = ['type' => 'success', 'message' => 'Status pelanggaran berhasil diperbarui'];
elseif (isset($_GET['delete']) && $_GET['delete'] == 'success')
    $notification = ['type' => 'success', 'message' => 'Data pelanggaran berhasil dihapus'];
elseif (isset($_GET['delete']) && $_GET['delete'] == 'error')
    $notification = ['type' => 'error', 'message' => 'Gagal menghapus: ' . ($_GET['message'] ?? 'Terjadi kesalahan')];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Pelanggaran - SMK NURUL ULUM</title>
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
            border: 1px solid rgba(16, 185, 129, 0.25);
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

        /* Mini progress bar total poin */
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

        /* Poin badge per jenis */
        .poin-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 1px 6px;
            border-radius: 9999px;
            font-size: 0.68rem;
            font-weight: 600;
            border: 1px solid;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">
    <div id="mobile-overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
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
            <button class="text-gray-600 hover:text-gray-800 lg:hidden" onclick="toggleSidebar()"><i
                    class="fas fa-times text-xl"></i></button>
        </div>
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="../dashboard/"
                class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors"><i
                    class="fas fa-home"></i><span>Dashboard</span></a>
            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                    <i class="fas fa-calendar-check"></i><span>Monitoring Siswa</span><i
                        class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                    <li><a href="../absensi/index.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Presensi</a>
                    </li>
                    <li><a href="../absensi/pelanggaran.php"
                            class="block p-2 text-violet-500 bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../absensi/konseling.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Konseling</a>
                    </li>
                </ul>
            </li>
            <a href="../siswa/"
                class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors"><i
                    class="fas fa-users"></i><span>Data Siswa</span></a>
            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                    <i class="fas fa-file-alt"></i><span>Laporan</span><i
                        class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block transition-all duration-300">
                    <li><a href="../laporan/index.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Presensi</a>
                    </li>
                    <li><a href="../laporan/laporan_pelanggaran.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Pelanggaran</a>
                    </li>
                    <li><a href="../laporan/laporan_konseling.php"
                            class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Konseling</a>
                    </li>
                </ul>
            </li>
            <a href="../profil/"
                class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors"><i
                    class="fas fa-user-cog"></i><span>Profil</span></a>
            <a href="../logout.php"
                class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors mt-10"><i
                    class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </aside>

    <!-- Main -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-sky-50 to-indigo-50 transition-all duration-300">
        <!-- Mobile Header -->
        <div
            class="lg:hidden bg-white/90 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-800 p-2 -ml-2 rounded-lg hover:bg-gray-100"
                    aria-label="Menu"><i class="fas fa-bars text-lg"></i></button>
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile"
                    class="h-8 w-8 rounded-full object-cover border border-violet-300">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- Notification -->
                <?php if ($notification): ?>
                    <div
                        class="mb-6 animate-fade-in <?= $notification['type'] === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-500' : 'bg-red-500/10 border-red-500/30 text-red-500' ?> rounded-lg p-4 border flex items-center">
                        <i
                            class="fas <?= $notification['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                        <p class="text-sm"><?= htmlspecialchars($notification['message']) ?></p>
                        <button class="ml-auto text-gray-500 hover:text-gray-700" onclick="this.parentElement.remove()"><i
                                class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Monitoring Pelanggaran</h1>
                        <p class="text-gray-500 text-sm md:text-base">Kelola data pelanggaran siswa</p>
                    </div>
                    <a href="addp.php"
                        class="px-4 py-2 bg-purple-600 text-white hover:bg-purple-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                        <i class="fas fa-plus"></i> Tambah Pelanggaran
                    </a>
                </header>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-yellow-500/10 flex items-center justify-center mr-3"><i
                                class="fas fa-exclamation-circle text-yellow-500"></i></div>
                        <div>
                            <p class="text-xs text-gray-500">Ringan (Hari Ini)</p>
                            <p class="text-xl font-bold"><?= $jenis_counts['Ringan'] ?></p>
                        </div>
                    </div>
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-orange-500/10 flex items-center justify-center mr-3"><i
                                class="fas fa-exclamation-triangle text-orange-500"></i></div>
                        <div>
                            <p class="text-xs text-gray-500">Sedang (Hari Ini)</p>
                            <p class="text-xl font-bold"><?= $jenis_counts['Sedang'] ?></p>
                        </div>
                    </div>
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-red-500/10 flex items-center justify-center mr-3"><i
                                class="fas fa-times-circle text-red-500"></i></div>
                        <div>
                            <p class="text-xs text-gray-500">Berat (Hari Ini)</p>
                            <p class="text-xl font-bold"><?= $jenis_counts['Berat'] ?></p>
                        </div>
                    </div>
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-amber-500/10 flex items-center justify-center mr-3"><i
                                class="fas fa-hourglass-half text-amber-500"></i></div>
                        <div>
                            <p class="text-xs text-gray-500">Sedang Diproses</p>
                            <p class="text-xl font-bold"><?= $proses_count ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h3 class="font-medium text-lg mb-2 md:mb-0">Filter & Pencarian</h3>
                        <?php if (!empty(array_filter([$date_filter, $jenis_filter, $kelas_filter, $jurusan_filter, $status_filter, $search]))): ?>
                            <a href="index.php"
                                class="text-sm flex items-center gap-1 text-violet-500 hover:text-purple-300"><i
                                    class="fas fa-times-circle"></i> Reset Filter</a>
                        <?php endif; ?>
                    </div>
                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= $sort_column ?>">
                        <input type="hidden" name="order" value="<?= $sort_order ?>">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Tanggal</label>
                                <div class="relative">
                                    <input type="date" name="date" value="<?= $date_filter ?>"
                                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                    <?php if ($date_filter): ?><button type="button" onclick="clearField('date')"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i
                                                class="fas fa-times-circle"></i></button><?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Jenis Pelanggaran</label>
                                <div class="relative">
                                    <select name="jenis"
                                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                        <option value="">Semua Jenis</option>
                                        <option value="Ringan" <?= $jenis_filter === 'Ringan' ? 'selected' : '' ?>>Ringan
                                        </option>
                                        <option value="Sedang" <?= $jenis_filter === 'Sedang' ? 'selected' : '' ?>>Sedang
                                        </option>
                                        <option value="Berat" <?= $jenis_filter === 'Berat' ? 'selected' : '' ?>>Berat
                                        </option>
                                    </select>
                                    <?php if ($jenis_filter): ?><button type="button" onclick="clearField('jenis')"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i
                                                class="fas fa-times-circle"></i></button><?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Kelas</label>
                                <div class="relative">
                                    <select name="kelas"
                                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                        <option value="">Semua Kelas</option>
                                        <option value="10" <?= $kelas_filter === '10' ? 'selected' : '' ?>>10</option>
                                        <option value="11" <?= $kelas_filter === '11' ? 'selected' : '' ?>>11</option>
                                        <option value="12" <?= $kelas_filter === '12' ? 'selected' : '' ?>>12</option>
                                    </select>
                                    <?php if ($kelas_filter): ?><button type="button" onclick="clearField('kelas')"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i
                                                class="fas fa-times-circle"></i></button><?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Jurusan</label>
                                <div class="relative">
                                    <select name="jurusan"
                                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                        <option value="">Semua Jurusan</option>
                                        <option value="TKJ" <?= $jurusan_filter === 'TKJ' ? 'selected' : '' ?>>TKJ</option>
                                        <option value="MP" <?= $jurusan_filter === 'MP' ? 'selected' : '' ?>>MP</option>
                                        <option value="AKL" <?= $jurusan_filter === 'AKL' ? 'selected' : '' ?>>AKL</option>
                                        <option value="TSM" <?= $jurusan_filter === 'TSM' ? 'selected' : '' ?>>TSM</option>
                                        <option value="TKR" <?= $jurusan_filter === 'TKR' ? 'selected' : '' ?>>TKR</option>
                                    </select>
                                    <?php if ($jurusan_filter): ?><button type="button" onclick="clearField('jurusan')"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i
                                                class="fas fa-times-circle"></i></button><?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Status Tindak Lanjut</label>
                                <div class="relative">
                                    <select name="status"
                                        class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                        <option value="">Semua Status</option>
                                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>
                                            Pending
                                        </option>
                                        <option value="Proses" <?= $status_filter === 'Proses' ? 'selected' : '' ?>>Proses
                                        </option>
                                        <option value="Selesai" <?= $status_filter === 'Selesai' ? 'selected' : '' ?>>
                                            Selesai
                                        </option>
                                    </select>
                                    <?php if ($status_filter): ?><button type="button" onclick="clearField('status')"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i
                                                class="fas fa-times-circle"></i></button><?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Pencarian Siswa</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama atau NIS..."
                                        class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-9 pr-9 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-violet-500">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                                    <?php if ($search): ?><button type="button" onclick="clearField('search')"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"><i
                                                class="fas fa-times-circle"></i></button><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit"
                                class="px-5 py-2.5 bg-purple-600 text-white hover:bg-purple-700 text-gray-800 rounded-lg transition-colors text-sm">
                                <i class="fas fa-filter mr-2"></i>Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <?php if (count($pelanggaran_list) > 0): ?>
                        <div class="overflow-x-auto table-container">
                            <table class="w-full whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-50/50 text-gray-700 text-left">
                                        <th class="px-4 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('nis') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">NIS
                                                <?= getSortIcon('nis', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-4 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('kelas') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">Kelas
                                                <?= getSortIcon('kelas', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-4 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('tanggal') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">Tanggal
                                                <?= getSortIcon('tanggal', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-4 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('jenis_pelanggaran') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">Jenis
                                                <?= getSortIcon('jenis_pelanggaran', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-4 py-3 text-xs font-medium">Deskripsi</th>
                                        <th class="px-4 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('poin') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">Poin
                                                <?= getSortIcon('poin', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-4 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('status') ?>"
                                                class="flex items-center gap-1 hover:text-gray-800">Status
                                                <?= getSortIcon('status', $sort_column, $sort_order) ?></a>
                                        </th>
                                        <th class="px-4 py-3 text-xs font-medium text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    <?php foreach ($pelanggaran_list as $p):
                                        $tp = min((int) $p['total_poin'], 100);
                                        $color = totalPoinColor($tp);
                                        $pct = $tp . '%';
                                    ?>
                                        <tr class="hover:bg-purple-900/5 transition-colors animate-fade-in">
                                            <td class="px-4 py-4 text-sm"><?= htmlspecialchars($p['nis']) ?></td>
                                            <td class="px-4 py-4 text-sm"><?= $p['kelas'] ?> <?= $p['jurusan'] ?></td>
                                            <td class="px-4 py-4 text-sm"><?= date('d/m/Y', strtotime($p['tanggal'])) ?></td>
                                            <td class="px-4 py-4">
                                                <span
                                                    class="px-2 py-1 rounded-full text-xs jenis-<?= strtolower($p['jenis_pelanggaran']) ?>">
                                                    <?= $p['jenis_pelanggaran'] ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="text-gray-700 text-sm max-w-[160px] truncate block"
                                                    title="<?= htmlspecialchars($p['deskripsi']) ?>">
                                                    <?= htmlspecialchars($p['deskripsi']) ?>
                                                </span>
                                            </td>

                                            <!-- ===== KOLOM POIN (DIPERBARUI) ===== -->
                                            <td class="px-4 py-4">
                                                <div class="flex flex-col gap-1.5">
                                                    <!-- Poin kejadian ini -->
                                                    <span
                                                        class="font-bold text-sm <?= $p['jenis_pelanggaran'] === 'Berat' ? 'text-red-400' : ($p['jenis_pelanggaran'] === 'Sedang' ? 'text-amber-600' : 'text-green-600') ?>">
                                                        <?= $p['poin'] ?> <span
                                                            class="font-normal text-xs text-gray-500">poin</span>
                                                    </span>
                                                    <!-- Akumulasi poin per jenis untuk siswa ini -->
                                                    <div class="flex flex-wrap gap-1">
                                                        <?php
                                                        $pb = (int) $p['poin_berat'];
                                                        $ps = (int) $p['poin_sedang'];
                                                        $pr = (int) $p['poin_ringan'];
                                                        ?>
                                                        <?php if ($pb > 0): ?>
                                                            <span class="poin-badge jenis-berat" title="Total poin Berat siswa ini">
                                                                <i class="fas fa-circle"
                                                                    style="font-size:5px;vertical-align:middle;"></i> B:
                                                                <?= $pb ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($ps > 0): ?>
                                                            <span class="poin-badge jenis-sedang"
                                                                title="Total poin Sedang siswa ini">
                                                                <i class="fas fa-circle"
                                                                    style="font-size:5px;vertical-align:middle;"></i> S:
                                                                <?= $ps ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($pr > 0): ?>
                                                            <span class="poin-badge jenis-ringan"
                                                                title="Total poin Ringan siswa ini">
                                                                <i class="fas fa-circle"
                                                                    style="font-size:5px;vertical-align:middle;"></i> R:
                                                                <?= $pr ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($pb === 0 && $ps === 0 && $pr === 0): ?>
                                                            <span class="text-xs text-gray-500">-</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <!-- Mini progress bar total poin -->
                                                    <div class="poin-bar-wrap" title="Total poin akumulasi: <?= $tp ?>">
                                                        <div class="poin-bar-fill <?= $color['bar'] ?>"
                                                            style="width: <?= $pct ?>"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <!-- ===== END KOLOM POIN ===== -->

                                            <td class="px-4 py-4">
                                                <span
                                                    class="px-2 py-1 rounded-full text-xs status-<?= strtolower($p['status']) ?>">
                                                    <?= $p['status'] ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="flex justify-center space-x-2">
                                                    <a href="detailp.php?id=<?= $p['id'] ?>"
                                                        class="text-blue-400 hover:text-blue-300 p-1.5 rounded-full hover:bg-blue-500/10"
                                                        title="Detail"><i class="fas fa-eye"></i></a>
                                                    <a href="editp.php?id=<?= $p['id'] ?>"
                                                        class="text-amber-600 hover:text-yellow-300 p-1.5 rounded-full hover:bg-yellow-500/10"
                                                        title="Edit"><i class="fas fa-edit"></i></a>
                                                    <button onclick="confirmDelete(<?= $p['id'] ?>)"
                                                        class="text-red-400 hover:text-red-300 p-1.5 rounded-full hover:bg-red-500/10"
                                                        title="Hapus"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div
                            class="p-4 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <p class="text-sm text-gray-500 order-2 sm:order-1">
                                Menampilkan <?= min($offset + 1, $total_items) ?> -
                                <?= min($offset + $items_per_page, $total_items) ?> dari <?= $total_items ?> data
                            </p>
                            <div class="flex space-x-1 order-1 sm:order-2 pagination-compact">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildPaginationUrl(1) ?>"
                                        class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i
                                            class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= buildPaginationUrl($page - 1) ?>"
                                        class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i
                                            class="fas fa-angle-left"></i></a>
                                <?php endif; ?>
                                <?php
                                $range = 2;
                                $start_page = max($page - $range, 1);
                                $end_page = min($page + $range, $total_pages);
                                if ($start_page > 1)
                                    echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $cls = $i == $page
                                        ? 'px-2 sm:px-3 py-1.5 bg-purple-600 rounded text-gray-800 text-sm flex items-center justify-center min-w-[32px] current-page page-number'
                                        : 'px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px] page-number';
                                    echo '<a href="' . buildPaginationUrl($i) . '" class="' . $cls . '">' . $i . '</a>';
                                }
                                if ($end_page < $total_pages)
                                    echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildPaginationUrl($page + 1) ?>"
                                        class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i
                                            class="fas fa-angle-right"></i></a>
                                    <a href="<?= buildPaginationUrl($total_pages) ?>"
                                        class="px-2 sm:px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-sm flex items-center justify-center min-w-[32px]"><i
                                            class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="p-10 text-center">
                            <i class="fas fa-shield-alt text-5xl text-gray-600 mb-4"></i>
                            <p class="text-gray-500">Tidak ada data pelanggaran yang ditemukan</p>
                            <?php if (!empty(array_filter([$date_filter, $jenis_filter, $kelas_filter, $jurusan_filter, $status_filter, $search]))): ?>
                                <a href="index.php" class="mt-4 inline-block text-violet-500 hover:text-violet-600"><i
                                        class="fas fa-arrow-left mr-1"></i> Reset Filter</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile FAB -->
                <div class="fixed bottom-4 right-4 lg:hidden">
                    <a href="addp.php"
                        class="flex items-center justify-center w-14 h-14 bg-purple-600 hover:bg-purple-700 rounded-full shadow-lg transition-colors">
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
            <p class="text-gray-700 mb-6">Apakah Anda yakin ingin menghapus data pelanggaran ini? Tindakan ini tidak
                dapat dibatalkan.</p>
            <div class="flex justify-end gap-4">
                <button onclick="hideDeleteModal()"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm">Batal</button>
                <form id="deleteForm" method="POST" action="deletep.php">
                    <input type="hidden" id="deleteId" name="id" value="">
                    <button type="submit"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        document.querySelectorAll('select[name], input[type="date"]').forEach(el => {
            el.addEventListener('change', () => document.getElementById('filterForm').submit());
        });

        function clearField(fieldName) {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.value = '';
                document.getElementById('filterForm').submit();
            }
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
            if (e.key === 'Escape') {
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    hideDeleteModal();
                    return;
                }
                if (window.innerWidth < 1024 && !document.getElementById('sidebar').classList.contains('-translate-x-full')) toggleSidebar();
            }
        });
        window.addEventListener('resize', () => document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`));
        document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
    </script>
</body>

</html>
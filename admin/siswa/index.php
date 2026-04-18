<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// ─── FITUR NAIK KELAS ──────────────────────────────────────────────────────
$naik_kelas_result = null;

if (isset($_POST['action']) && $_POST['action'] === 'naik_kelas') {
    // Validasi CSRF sederhana
    if (!isset($_POST['confirm_naik']) || $_POST['confirm_naik'] !== 'YA') {
        $naik_kelas_result = ['type' => 'error', 'message' => 'Konfirmasi tidak valid.'];
    } else {
        try {
            $conn->beginTransaction();

            // 1. Siswa kelas 12 → LULUS (hapus atau tandai lulus)
            //    Di sini kita HAPUS siswa kelas 12 (sesuaikan jika ingin arsip)
            $stmt_lulus = $conn->prepare("DELETE FROM siswa WHERE kelas = '12'");
            $stmt_lulus->execute();
            $jumlah_lulus = $stmt_lulus->rowCount();

            // 2. Siswa kelas 11 → naik ke 12
            $stmt_12 = $conn->prepare("UPDATE siswa SET kelas = '12' WHERE kelas = '11'");
            $stmt_12->execute();
            $naik_ke_12 = $stmt_12->rowCount();

            // 3. Siswa kelas 10 → naik ke 11
            $stmt_11 = $conn->prepare("UPDATE siswa SET kelas = '11' WHERE kelas = '10'");
            $stmt_11->execute();
            $naik_ke_11 = $stmt_11->rowCount();

            $conn->commit();

            $naik_kelas_result = [
                'type'    => 'success',
                'message' => "Naik kelas berhasil! 
                              {$naik_ke_11} siswa naik ke kelas 11, 
                              {$naik_ke_12} siswa naik ke kelas 12, 
                              {$jumlah_lulus} siswa kelas 12 dinyatakan lulus/diarsip."
            ];
        } catch (Exception $e) {
            $conn->rollBack();
            $naik_kelas_result = ['type' => 'error', 'message' => 'Gagal: ' . $e->getMessage()];
        }
    }
}

// ─── FILTER ───────────────────────────────────────────────────────────────
$kelas_filter   = $_GET['kelas']   ?? '';
$jurusan_filter = $_GET['jurusan'] ?? '';
$search         = $_GET['search']  ?? '';

// Validasi nilai filter agar hanya nilai yang diizinkan
$valid_kelas   = ['10', '11', '12'];
$valid_jurusan = ['TKJ', 'MP', 'AKL', 'TSM', 'TKR'];

if (!in_array($kelas_filter, $valid_kelas, true))     $kelas_filter   = '';
if (!in_array($jurusan_filter, $valid_jurusan, true)) $jurusan_filter = '';

// ─── SORTING ──────────────────────────────────────────────────────────────
$valid_columns = ['nis', 'nama_lengkap', 'kelas', 'jurusan', 'email', 'created_at'];
$sort_column   = in_array($_GET['sort'] ?? '', $valid_columns) ? $_GET['sort'] : 'kelas';
$sort_order    = ($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

// ─── PAGINATION ───────────────────────────────────────────────────────────
$items_per_page = 10;
$page           = max(1, (int) ($_GET['page'] ?? 1));
$offset         = ($page - 1) * $items_per_page;

// ─── BUILD QUERY ──────────────────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = [];

if ($kelas_filter !== '') {
    $where .= " AND kelas = :kelas";
    $params['kelas'] = $kelas_filter;
}
if ($jurusan_filter !== '') {
    $where .= " AND jurusan = :jurusan";
    $params['jurusan'] = $jurusan_filter;
}
if ($search !== '') {
    $where .= " AND (nama_lengkap LIKE :search OR nis LIKE :search OR email LIKE :search)";
    $params['search'] = "%$search%";
}

// Total count
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM siswa $where");
foreach ($params as $k => $v) $count_stmt->bindValue(":$k", $v);
$count_stmt->execute();
$total_items = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_items / $items_per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $items_per_page;

// Data
$data_stmt = $conn->prepare(
    "SELECT * FROM siswa $where ORDER BY $sort_column $sort_order LIMIT :offset, :limit"
);
foreach ($params as $k => $v) $data_stmt->bindValue(":$k", $v);
$data_stmt->bindValue(':offset', $offset,          PDO::PARAM_INT);
$data_stmt->bindValue(':limit',  $items_per_page,  PDO::PARAM_INT);
$data_stmt->execute();
$siswa_list = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── STATISTIK PER KELAS & JURUSAN ────────────────────────────────────────
$stats = $conn->query(
    "SELECT kelas, jurusan, COUNT(*) as count
     FROM siswa
     GROUP BY kelas, jurusan
     ORDER BY kelas ASC, jurusan ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$total_students = array_sum(array_column($stats, 'count'));

// Ringkasan per kelas saja (untuk card utama)
$kelas_summary = [];
foreach ($stats as $s) {
    $kelas_summary[$s['kelas']] = ($kelas_summary[$s['kelas']] ?? 0) + $s['count'];
}

// ─── HELPER FUNCTIONS ─────────────────────────────────────────────────────
function buildSortUrl(string $column): string
{
    $p = $_GET;
    $p['sort']  = $column;
    $p['order'] = (($_GET['sort'] ?? '') === $column && ($_GET['order'] ?? '') === 'ASC') ? 'DESC' : 'ASC';
    unset($p['page']);
    return '?' . http_build_query($p);
}

function getSortIcon(string $column, string $sort_column, string $sort_order): string
{
    if ($column !== $sort_column) return '<i class="fas fa-sort text-gray-400 text-xs ml-1"></i>';
    return $sort_order === 'ASC'
        ? '<i class="fas fa-sort-up text-violet-600 text-xs ml-1"></i>'
        : '<i class="fas fa-sort-down text-violet-600 text-xs ml-1"></i>';
}

function buildPaginationUrl(int $p): string
{
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

function buildFilterUrl(string $key, string $val): string
{
    $p = $_GET;
    $p[$key] = $val;
    unset($p['page']);
    return '?' . http_build_query($p);
}

// ─── NOTIFIKASI ───────────────────────────────────────────────────────────
$notification = $naik_kelas_result;

if (!$notification) {
    if (isset($_GET['delete'])) {
        $notification = $_GET['delete'] === 'success'
            ? ['type' => 'success', 'message' => 'Data siswa berhasil dihapus.']
            : ['type' => 'error',   'message' => 'Gagal menghapus data siswa: ' . htmlspecialchars($_GET['message'] ?? '')];
    }
}

// Label kelas untuk tampilan
$label_kelas = ['10' => 'Kelas X', '11' => 'Kelas XI', '12' => 'Kelas XII'];
$label_jurusan_icon = [
    'TKJ' => 'fa-network-wired',
    'MP'  => 'fa-briefcase',
    'AKL' => 'fa-calculator',
    'TSM' => 'fa-motorcycle',
    'TKR' => 'fa-car',
];
$kelas_colors = [
    '10' => ['bg' => 'bg-blue-100',   'text' => 'text-blue-600',   'border' => 'border-blue-200'],
    '11' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-600', 'border' => 'border-emerald-200'],
    '12' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-600', 'border' => 'border-orange-200'],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa – SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ede9fe 100%) fixed;
        }

        .glass {
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(139, 92, 246, .2);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(139, 92, 246, .15), rgba(139, 92, 246, .05));
            border-left: 4px solid #9333ea;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .fade-in {
            animation: fadeIn .3s ease both;
        }

        .sidebar-anim {
            transition: transform .3s ease;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Badge kelas */
        .badge-10 {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-11 {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-12 {
            background: #ffedd5;
            color: #9a3412;
        }

        .badge-tkj {
            background: #ede9fe;
            color: #6d28d9;
        }

        .badge-mp {
            background: #fce7f3;
            color: #9d174d;
        }

        .badge-akl {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-tsm {
            background: #f0fdf4;
            color: #166534;
        }

        .badge-tkr {
            background: #fff7ed;
            color: #9a3412;
        }

        .kelas-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .03em;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800">

    <!-- Mobile Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ═══ SIDEBAR ═══ -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass border-r border-violet-200 z-50 sidebar-anim -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 border-b border-violet-200">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" alt="Logo" class="h-9 w-auto">
                <div>
                    <p class="font-semibold text-sm text-gray-800">SMK NURUL ULUM</p>
                    <p class="text-xs text-gray-500">Sistem Absensi</p>
                </div>
            </div>
            <button class="lg:hidden text-gray-500 hover:text-gray-700" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav class="p-4 space-y-1 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 72px)">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-home w-4"></i><span>Dashboard</span>
            </a>

            <!-- Monitoring -->
            <div class="relative group">
                <button class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 w-full transition-colors">
                    <i class="fas fa-calendar-check w-4"></i><span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-xs"></i>
                </button>
                <div class="ml-7 mt-1 hidden group-hover:block space-y-1">
                    <a href="../absensi/index.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-violet-600 hover:bg-violet-50 rounded-lg">Presensi</a>
                    <a href="../absensi/pelanggaran.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-violet-600 hover:bg-violet-50 rounded-lg">Pelanggaran</a>
                    <a href="../absensi/konseling.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-violet-600 hover:bg-violet-50 rounded-lg">Konseling</a>
                </div>
            </div>

            <a href="index.php" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg menu-active">
                <i class="fas fa-users text-violet-600 w-4"></i><span>Data Siswa</span>
            </a>

            <!-- Laporan -->
            <div class="relative group">
                <button class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 w-full transition-colors">
                    <i class="fas fa-file-alt w-4"></i><span>Laporan</span>
                    <i class="fas fa-chevron-down ml-auto text-xs"></i>
                </button>
                <div class="ml-7 mt-1 hidden group-hover:block space-y-1">
                    <a href="../laporan/index.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-violet-600 hover:bg-violet-50 rounded-lg">Presensi</a>
                    <a href="../laporan/laporan_pelanggaran.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-violet-600 hover:bg-violet-50 rounded-lg">Pelanggaran</a>
                    <a href="../laporan/laporan_konseling.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-violet-600 hover:bg-violet-50 rounded-lg">Konseling</a>
                </div>
            </div>

            <a href="../profil/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-user-cog w-4"></i><span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-red-500 p-3 rounded-lg hover:bg-red-50 transition-colors mt-6">
                <i class="fas fa-sign-out-alt w-4"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ═══ MAIN ═══ -->
    <main class="lg:ml-64 min-h-screen transition-all duration-300">

        <!-- Mobile header -->
        <div class="lg:hidden bg-white/90 backdrop-blur sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-100">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="p-2 -ml-2 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-bars text-gray-700"></i>
                </button>
                <img src="../../assets/default/logosmk.png" alt="Logo" class="h-8 w-auto">
            </div>
            <?php $photo = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
            <img src="../../<?= $photo ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-violet-300">
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- ── Notifikasi ── -->
                <?php if ($notification): ?>
                    <div class="fade-in flex items-start gap-3 p-4 rounded-xl border
                <?= $notification['type'] === 'success'
                        ? 'bg-green-50 border-green-200 text-green-800'
                        : 'bg-red-50 border-red-200 text-red-800' ?>">
                        <i class="fas <?= $notification['type'] === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> mt-0.5"></i>
                        <p class="text-sm flex-1"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- ── Header ── -->
                <div class="flex flex-wrap justify-between items-start gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Data Siswa</h1>
                        <p class="text-sm text-gray-500 mt-0.5">Kelola data siswa SMK NURUL ULUM</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <!-- Tombol Naik Kelas -->
                        <button onclick="document.getElementById('modalNaikKelas').classList.remove('hidden')"
                            class="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">
                            <i class="fas fa-arrow-up"></i>
                            <span class="hidden sm:inline">Naik Kelas</span>
                        </button>
                        <!-- Tambah Siswa -->
                        <a href="add.php"
                            class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">
                            <i class="fas fa-user-plus"></i>
                            <span class="hidden sm:inline">Tambah Siswa</span>
                        </a>
                    </div>
                </div>

                <!-- ── Statistik Kelas ── -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <!-- Total -->
                    <div class="glass rounded-xl p-4 col-span-2 sm:col-span-1 flex items-center gap-3">
                        <div class="h-11 w-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-users text-violet-600 text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total Siswa</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $total_students ?></p>
                        </div>
                    </div>

                    <?php
                    $kcfg = [
                        '10' => ['icon' => 'fa-user-graduate', 'bg' => 'bg-blue-100', 'text' => 'text-blue-600', 'label' => 'Kelas X'],
                        '11' => ['icon' => 'fa-user-tie', 'bg' => 'bg-emerald-100', 'text' => 'text-emerald-600', 'label' => 'Kelas XI'],
                        '12' => ['icon' => 'fa-user-check', 'bg' => 'bg-orange-100', 'text' => 'text-orange-600', 'label' => 'Kelas XII'],
                    ];
                    foreach ($kcfg as $k => $cfg): ?>
                        <a href="<?= buildFilterUrl('kelas', $k) ?>"
                            class="glass rounded-xl p-4 flex items-center gap-3 hover:shadow-md transition-shadow group <?= $kelas_filter === $k ? 'ring-2 ring-violet-400' : '' ?>">
                            <div class="h-11 w-11 rounded-xl <?= $cfg['bg'] ?> flex items-center justify-center flex-shrink-0">
                                <i class="fas <?= $cfg['icon'] ?> <?= $cfg['text'] ?> text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500"><?= $cfg['label'] ?></p>
                                <p class="text-2xl font-bold text-gray-900"><?= $kelas_summary[$k] ?? 0 ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- ── Filter & Search ── -->
                <div class="glass rounded-xl p-4 md:p-5">
                    <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                        <h3 class="font-semibold text-gray-800">Filter &amp; Pencarian</h3>
                        <?php if ($kelas_filter || $jurusan_filter || $search): ?>
                            <a href="index.php" class="text-xs flex items-center gap-1 text-violet-500 hover:text-violet-700">
                                <i class="fas fa-times-circle"></i> Reset Semua Filter
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_column) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- Filter Kelas -->
                            <div>
                                <label class="text-xs font-medium text-gray-500 block mb-1.5">Kelas</label>
                                <select name="kelas" onchange="this.form.submit()"
                                    class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-400">
                                    <option value="">Semua Kelas</option>
                                    <option value="10" <?= $kelas_filter === '10' ? 'selected' : '' ?>>Kelas X (10)</option>
                                    <option value="11" <?= $kelas_filter === '11' ? 'selected' : '' ?>>Kelas XI (11)</option>
                                    <option value="12" <?= $kelas_filter === '12' ? 'selected' : '' ?>>Kelas XII (12)</option>
                                </select>
                            </div>

                            <!-- Filter Jurusan -->
                            <div>
                                <label class="text-xs font-medium text-gray-500 block mb-1.5">Jurusan</label>
                                <select name="jurusan" onchange="this.form.submit()"
                                    class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-400">
                                    <option value="">Semua Jurusan</option>
                                    <option value="TKJ" <?= $jurusan_filter === 'TKJ' ? 'selected' : '' ?>>TKJ – Teknik Komputer &amp; Jaringan</option>
                                    <option value="MP" <?= $jurusan_filter === 'MP'  ? 'selected' : '' ?>>MP – Manajemen Perkantoran</option>
                                    <option value="AKL" <?= $jurusan_filter === 'AKL' ? 'selected' : '' ?>>AKL – Akuntansi &amp; Keuangan</option>
                                    <option value="TSM" <?= $jurusan_filter === 'TSM' ? 'selected' : '' ?>>TSM – Teknik Sepeda Motor</option>
                                    <option value="TKR" <?= $jurusan_filter === 'TKR' ? 'selected' : '' ?>>TKR – Teknik Kendaraan Ringan</option>
                                </select>
                            </div>

                            <!-- Pencarian -->
                            <div>
                                <label class="text-xs font-medium text-gray-500 block mb-1.5">Pencarian</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama, NIS, atau Email..."
                                        class="w-full bg-white border border-gray-200 rounded-lg pl-9 pr-9 py-2.5 text-sm text-gray-700 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-400">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                    <?php if ($search): ?>
                                        <button type="button" onclick="clearField('search')"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Filter jurusan aktif sebagai chip -->
                        <?php if ($kelas_filter || $jurusan_filter): ?>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <?php if ($kelas_filter): ?>
                                    <span class="flex items-center gap-1 bg-violet-100 text-violet-700 px-2.5 py-1 rounded-full">
                                        Kelas: <?= $label_kelas[$kelas_filter] ?? $kelas_filter ?>
                                        <button type="button" onclick="clearField('kelas')" class="hover:text-violet-900">×</button>
                                    </span>
                                <?php endif; ?>
                                <?php if ($jurusan_filter): ?>
                                    <span class="flex items-center gap-1 bg-violet-100 text-violet-700 px-2.5 py-1 rounded-full">
                                        Jurusan: <?= $jurusan_filter ?>
                                        <button type="button" onclick="clearField('jurusan')" class="hover:text-violet-900">×</button>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 flex justify-end">
                            <button type="submit"
                                class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm rounded-lg transition-colors">
                                <i class="fas fa-filter mr-1.5"></i> Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── Tabel Data Siswa ── -->
                <div class="glass rounded-xl overflow-hidden">
                    <?php if ($siswa_list): ?>
                        <!-- Info & action bar -->
                        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap justify-between items-center gap-2">
                            <p class="text-sm text-gray-500">
                                Menampilkan <strong class="text-gray-700"><?= min($offset + 1, $total_items) ?>–<?= min($offset + $items_per_page, $total_items) ?></strong>
                                dari <strong class="text-gray-700"><?= $total_items ?></strong> siswa
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full whitespace-nowrap">
                                <thead class="bg-gray-50/70 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <tr>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('nis') ?>" class="flex items-center hover:text-gray-800">
                                                NIS <?= getSortIcon('nis', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center hover:text-gray-800">
                                                Nama <?= getSortIcon('nama_lengkap', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('kelas') ?>" class="flex items-center hover:text-gray-800">
                                                Kelas <?= getSortIcon('kelas', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-left">
                                            <a href="<?= buildSortUrl('jurusan') ?>" class="flex items-center hover:text-gray-800">
                                                Jurusan <?= getSortIcon('jurusan', $sort_column, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-5 py-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($siswa_list as $idx => $s): ?>
                                        <?php
                                        $k = $s['kelas'] ?? '';
                                        $j = strtolower($s['jurusan'] ?? '');
                                        $kBadge = "badge-$k";
                                        $jBadge = "badge-$j";
                                        $labelK = $label_kelas[$k] ?? "Kelas $k";
                                        $kelasDisplay = "$labelK"; // mis: Kelas X
                                        ?>
                                        <tr class="hover:bg-purple-50/40 transition-colors fade-in" style="animation-delay:<?= $idx * 0.03 ?>s">
                                            <!-- NIS -->
                                            <td class="px-5 py-3.5 text-sm font-mono text-gray-600">
                                                <?= htmlspecialchars($s['nis']) ?>
                                            </td>

                                            <!-- Nama + foto -->
                                            <td class="px-5 py-3.5">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= htmlspecialchars($s['foto_profil'] ?: 'assets/default/avatar.png') ?>"
                                                        alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0 border border-gray-200">
                                                    <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['nama_lengkap']) ?></span>
                                                </div>
                                            </td>

                                            <!-- Kelas badge -->
                                            <td class="px-5 py-3.5">
                                                <span class="kelas-badge <?= $kBadge ?>">
                                                    <?= $kelasDisplay ?>
                                                </span>
                                            </td>

                                            <!-- Jurusan badge -->
                                            <td class="px-5 py-3.5">
                                                <span class="kelas-badge <?= $jBadge ?>">
                                                    <i class="fas <?= $label_jurusan_icon[$s['jurusan']] ?? 'fa-book' ?> text-[10px]"></i>
                                                    <?= htmlspecialchars($s['jurusan']) ?>
                                                </span>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="px-5 py-3.5">
                                                <div class="flex justify-center items-center gap-1">
                                                    <a href="detail.php?id=<?= $s['id'] ?>"
                                                        class="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50 transition-colors" title="Detail">
                                                        <i class="fas fa-eye text-sm"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?= $s['id'] ?>"
                                                        class="p-1.5 rounded-lg text-amber-500 hover:bg-amber-50 transition-colors" title="Edit">
                                                        <i class="fas fa-edit text-sm"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?= (int)$s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nama_lengkap'])) ?>')"
                                                        class="p-1.5 rounded-lg text-red-400 hover:bg-red-50 transition-colors" title="Hapus">
                                                        <i class="fas fa-trash text-sm"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="px-5 py-4 border-t border-gray-100 flex flex-wrap justify-end gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildPaginationUrl(1) ?>" class="px-2.5 py-1.5 text-sm bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fas fa-angle-double-left text-xs"></i></a>
                                    <a href="<?= buildPaginationUrl($page - 1) ?>" class="px-2.5 py-1.5 text-sm bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fas fa-angle-left text-xs"></i></a>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($total_pages, $page + 2);
                                if ($start > 1)            echo '<span class="px-2 py-1.5 text-gray-400 text-sm">…</span>';
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <?php if ($i === $page): ?>
                                        <span class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded-lg font-medium"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= buildPaginationUrl($i) ?>" class="px-3 py-1.5 text-sm bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($end < $total_pages) echo '<span class="px-2 py-1.5 text-gray-400 text-sm">…</span>'; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildPaginationUrl($page + 1) ?>" class="px-2.5 py-1.5 text-sm bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fas fa-angle-right text-xs"></i></a>
                                    <a href="<?= buildPaginationUrl($total_pages) ?>" class="px-2.5 py-1.5 text-sm bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fas fa-angle-double-right text-xs"></i></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Empty state -->
                        <div class="py-16 text-center">
                            <div class="h-16 w-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-users text-gray-300 text-2xl"></i>
                            </div>
                            <p class="text-gray-500 text-sm">Tidak ada data siswa ditemukan</p>
                            <?php if ($kelas_filter || $jurusan_filter || $search): ?>
                                <a href="index.php" class="mt-3 inline-flex items-center gap-1 text-sm text-violet-500 hover:text-violet-700">
                                    <i class="fas fa-arrow-left text-xs"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /max-w -->
        </div><!-- /p-4 -->

        <!-- FAB mobile -->
        <div class="fixed bottom-5 right-5 lg:hidden flex flex-col items-end gap-3">
            <button onclick="document.getElementById('modalNaikKelas').classList.remove('hidden')"
                class="flex items-center justify-center w-12 h-12 bg-amber-500 hover:bg-amber-600 rounded-full shadow-lg text-white transition-colors">
                <i class="fas fa-arrow-up"></i>
            </button>
            <a href="add.php"
                class="flex items-center justify-center w-14 h-14 bg-purple-600 hover:bg-purple-700 rounded-full shadow-lg text-white transition-colors">
                <i class="fas fa-user-plus text-lg"></i>
            </a>
        </div>
    </main>

    <!-- ═══ MODAL HAPUS ═══ -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black/50" onclick="hideDeleteModal()"></div>
        <div class="glass rounded-xl p-6 w-11/12 max-w-sm relative z-10 shadow-2xl">
            <div class="h-12 w-12 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-trash text-red-500 text-lg"></i>
            </div>
            <h3 class="text-lg font-semibold text-center mb-1">Hapus Data Siswa</h3>
            <p class="text-sm text-gray-500 text-center mb-2">
                Anda akan menghapus data siswa: <strong id="deleteNama"></strong>
            </p>
            <p class="text-xs text-red-500 text-center mb-6">Semua data absensi terkait juga akan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex gap-3">
                <button onclick="hideDeleteModal()" class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition-colors">
                    Batal
                </button>
                <form id="deleteForm" method="POST" action="delete.php" class="flex-1">
                    <input type="hidden" id="deleteId" name="id" value="">
                    <button type="submit" class="w-full py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors">
                        Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ MODAL NAIK KELAS ═══ -->
    <div id="modalNaikKelas" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('modalNaikKelas').classList.add('hidden')"></div>
        <div class="glass rounded-xl p-6 w-11/12 max-w-md relative z-10 shadow-2xl">
            <div class="h-12 w-12 bg-amber-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-graduation-cap text-amber-500 text-lg"></i>
            </div>
            <h3 class="text-lg font-bold text-center mb-1">Proses Naik Kelas</h3>
            <p class="text-sm text-gray-500 text-center mb-5">
                Fitur ini akan memproses kenaikan kelas semua siswa secara otomatis untuk tahun ajaran baru.
            </p>

            <!-- Diagram proses -->
            <div class="bg-gray-50 rounded-xl p-4 mb-5 space-y-2 text-sm">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-arrow-right text-blue-500 text-xs"></i>
                    </div>
                    <span class="text-gray-700">Kelas <strong>X (10)</strong> → naik ke <strong>XI (11)</strong></span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-arrow-right text-emerald-500 text-xs"></i>
                    </div>
                    <span class="text-gray-700">Kelas <strong>XI (11)</strong> → naik ke <strong>XII (12)</strong></span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-graduation-cap text-red-500 text-xs"></i>
                    </div>
                    <span class="text-gray-700">Kelas <strong>XII (12)</strong> → <strong class="text-red-600">LULUS / dihapus</strong></span>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-5 flex gap-2 text-xs text-amber-800">
                <i class="fas fa-exclamation-triangle mt-0.5 flex-shrink-0"></i>
                <span>Proses ini tidak dapat dibatalkan. Pastikan Anda telah membackup data siswa kelas XII sebelum melanjutkan.</span>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="naik_kelas">
                <input type="hidden" name="confirm_naik" value="YA">

                <!-- Checkbox konfirmasi -->
                <label class="flex items-start gap-2 mb-5 cursor-pointer group">
                    <input type="checkbox" id="chkKonfirmasi" class="mt-0.5 accent-amber-500"
                        onchange="document.getElementById('btnNaikKelas').disabled = !this.checked">
                    <span class="text-sm text-gray-600 group-hover:text-gray-800 transition-colors">
                        Saya memahami bahwa data siswa kelas XII akan dihapus dan proses ini tidak dapat dibatalkan.
                    </span>
                </label>

                <div class="flex gap-3">
                    <button type="button"
                        onclick="document.getElementById('modalNaikKelas').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition-colors">
                        Batal
                    </button>
                    <button type="submit" id="btnNaikKelas" disabled
                        class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-arrow-up mr-1.5"></i> Proses Naik Kelas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        function confirmDelete(id, nama) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteNama').textContent = nama;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        function clearField(name) {
            const el = document.querySelector(`[name="${name}"]`);
            if (el) {
                el.value = '';
                document.getElementById('filterForm').submit();
            }
        }

        function toggleSidebar() {
            const sb = document.getElementById('sidebar');
            const ov = document.getElementById('mobile-overlay');
            const open = sb.classList.contains('-translate-x-full');
            sb.classList.toggle('-translate-x-full', !open);
            ov.classList.toggle('hidden', !open);
            document.body.style.overflow = open ? 'hidden' : '';
        }

        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            hideDeleteModal();
            document.getElementById('modalNaikKelas').classList.add('hidden');
            if (window.innerWidth < 1024) {
                const sb = document.getElementById('sidebar');
                if (!sb.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });
    </script>
</body>

</html>
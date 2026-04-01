<?php
session_start();
require_once '../../config/database.php';

// Guard: hanya kepsek
if (!isset($_SESSION['kepsek_id']) || $_SESSION['role'] !== 'kepsek') {
    header("Location: ../../kepsek/login.php");
    exit();
}

// ── Filter ─────────────────────────────────────────────────────────────────
$date_filter    = $_GET['date']    ?? '';
$jenis_filter   = $_GET['jenis']   ?? '';
$kelas_filter   = $_GET['kelas']   ?? '';
$jurusan_filter = $_GET['jurusan'] ?? '';
$status_filter  = $_GET['status']  ?? '';
$search         = $_GET['search']  ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────
$items_per_page = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// ── SQL ────────────────────────────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = [];

if ($date_filter) {
    $where .= " AND p.tanggal = :date";
    $params['date']    = $date_filter;
}
if ($jenis_filter) {
    $where .= " AND p.jenis_pelanggaran = :jenis";
    $params['jenis']   = $jenis_filter;
}
if ($kelas_filter) {
    $where .= " AND s.kelas = :kelas";
    $params['kelas']   = $kelas_filter;
}
if ($jurusan_filter) {
    $where .= " AND s.jurusan = :jurusan";
    $params['jurusan'] = $jurusan_filter;
}
if ($status_filter) {
    $where .= " AND p.status = :status";
    $params['status']  = $status_filter;
}
if ($search) {
    $where .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $params['search']  = "%$search%";
}

// Sort
$valid_cols  = ['nis', 'nama_lengkap', 'kelas', 'tanggal', 'jenis_pelanggaran', 'poin', 'status', 'total_poin'];
$sort_col    = in_array($_GET['sort'] ?? '', $valid_cols) ? $_GET['sort'] : 'tanggal';
$sort_order  = ($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// Count
$count_stmt = $conn->prepare(
    "SELECT COUNT(*) FROM pelanggaran p JOIN siswa s ON p.siswa_id = s.id $where"
);
foreach ($params as $k => $v) $count_stmt->bindValue(":$k", $v);
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_items / $items_per_page));

// Main query
$order_clause = $sort_col === 'total_poin'
    ? "ORDER BY total_poin $sort_order, p.tanggal DESC"
    : "ORDER BY " . (in_array($sort_col, ['nama_lengkap', 'nis', 'kelas']) ? 's.' : 'p.') . "$sort_col $sort_order" . ($sort_col !== 'tanggal' ? ", p.tanggal DESC" : "");

$sql = "SELECT p.id, p.tanggal, p.jenis_pelanggaran, p.deskripsi, p.status, p.tindakan,
               s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil,
               COALESCE(NULLIF(p.poin,0), dp.poin_default, 0) AS poin,
               CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0),dp2.poin_default,0)) FROM pelanggaran pp LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama=pp.deskripsi AND dp2.jenis=pp.jenis_pelanggaran WHERE pp.siswa_id=s.id),0) AS UNSIGNED) AS total_poin,
               CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0),dp2.poin_default,0)) FROM pelanggaran pp LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama=pp.deskripsi AND dp2.jenis=pp.jenis_pelanggaran WHERE pp.siswa_id=s.id AND pp.jenis_pelanggaran='Berat'),0) AS UNSIGNED) AS poin_berat,
               CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0),dp2.poin_default,0)) FROM pelanggaran pp LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama=pp.deskripsi AND dp2.jenis=pp.jenis_pelanggaran WHERE pp.siswa_id=s.id AND pp.jenis_pelanggaran='Sedang'),0) AS UNSIGNED) AS poin_sedang,
               CAST(COALESCE((SELECT SUM(COALESCE(NULLIF(pp.poin,0),dp2.poin_default,0)) FROM pelanggaran pp LEFT JOIN deskripsi_pelanggaran dp2 ON dp2.nama=pp.deskripsi AND dp2.jenis=pp.jenis_pelanggaran WHERE pp.siswa_id=s.id AND pp.jenis_pelanggaran='Ringan'),0) AS UNSIGNED) AS poin_ringan
        FROM pelanggaran p
        JOIN siswa s ON p.siswa_id = s.id
        LEFT JOIN deskripsi_pelanggaran dp ON dp.nama = p.deskripsi AND dp.jenis = p.jenis_pelanggaran
        $where $order_clause
        LIMIT :offset, :limit";

$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$pelanggaran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Stat cards ─────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$jenis_counts = ['Ringan' => 0, 'Sedang' => 0, 'Berat' => 0];
$sc = $conn->prepare("SELECT jenis_pelanggaran, COUNT(*) as c FROM pelanggaran WHERE tanggal=:t GROUP BY jenis_pelanggaran");
$sc->execute(['t' => $today]);
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($jenis_counts[$r['jenis_pelanggaran']])) $jenis_counts[$r['jenis_pelanggaran']] = $r['c'];
}
$proses_count = $conn->query("SELECT COUNT(*) FROM pelanggaran WHERE status='Proses'")->fetchColumn();

// ── Helpers ────────────────────────────────────────────────────────────────
function buildSortUrl($col)
{
    $p = $_GET;
    $p['sort'] = $col;
    $p['order'] = (isset($_GET['sort']) && $_GET['sort'] === $col && ($_GET['order'] ?? '') === 'ASC') ? 'DESC' : 'ASC';
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
function poinColor($poin)
{
    if ($poin >= 75) return ['bar' => 'bg-red-500',    'text' => 'text-red-500'];
    if ($poin >= 50) return ['bar' => 'bg-orange-500', 'text' => 'text-orange-500'];
    if ($poin >= 25) return ['bar' => 'bg-yellow-500', 'text' => 'text-yellow-600'];
    return              ['bar' => 'bg-green-500',  'text' => 'text-green-600'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Pelanggaran – Kepala Sekolah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(217, 119, 6, .18), rgba(217, 119, 6, .04));
            border-left: 4px solid #d97706;
        }

        /* Jenis badges */
        .j-ringan {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .j-sedang {
            background: #fef9c3;
            color: #713f12;
            border: 1px solid #fde047;
        }

        .j-berat {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fca5a5;
        }

        /* Status badges */
        .st-selesai {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .st-proses {
            background: #ffedd5;
            color: #7c2d12;
            border: 1px solid #fdba74;
        }

        .st-pending {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }

        /* Poin mini badges */
        .poin-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 1px 6px;
            border-radius: 9999px;
            font-size: .68rem;
            font-weight: 600;
            border: 1px solid;
        }

        .poin-bar-wrap {
            width: 60px;
            height: 5px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
        }

        .poin-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width .4s;
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
                    <li><a href="presensi.php" class="block p-2 text-gray-600 hover:text-amber-400 hover:bg-amber-500/10 rounded-lg text-sm">Presensi</a></li>
                    <li><a href="pelanggaran.php" class="block p-2 text-amber-500 bg-amber-500/10 rounded-lg text-sm font-medium">Pelanggaran</a></li>
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
                <span class="text-sm font-medium text-gray-800">Monitoring Pelanggaran</span>
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
                            <i class="fas fa-exclamation-triangle text-red-400"></i> Monitoring Pelanggaran Siswa
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">Data pelanggaran siswa – hanya lihat</p>
                    </div>

                    <span class="mt-3 lg:mt-0 inline-flex items-center gap-2 px-4 py-2
                        bg-white/80 backdrop-blur-md border border-amber-200 rounded-xl
                        text-xs text-amber-500 shadow-sm">
                        <i class="fas fa-eye"></i> Mode Lihat Saja
                    </span>
                </header>

                <!-- ── STAT CARDS ──────────────────────────────────────────────── -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <?php
                    $statCards = [
                        ['label' => 'Ringan (Hari Ini)', 'val' => $jenis_counts['Ringan'], 'icon' => 'fa-exclamation-circle', 'col' => 'green'],
                        ['label' => 'Sedang (Hari Ini)', 'val' => $jenis_counts['Sedang'], 'icon' => 'fa-exclamation-triangle', 'col' => 'yellow'],
                        ['label' => 'Berat (Hari Ini)',  'val' => $jenis_counts['Berat'],  'icon' => 'fa-times-circle',        'col' => 'red'],
                        ['label' => 'Sedang Diproses',   'val' => $proses_count,           'icon' => 'fa-hourglass-half',      'col' => 'orange'],
                    ];
                    foreach ($statCards as $i => $card):
                    ?>
                        <div class="bg-white/80 backdrop-blur-md border border-gray-100 rounded-xl p-4
                            hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default fade-up"
                            style="animation-delay:<?= $i * 0.06 ?>s">
                            <div class="flex justify-between items-start mb-3">
                                <p class="text-gray-500 text-xs font-medium"><?= $card['label'] ?></p>
                                <div class="h-8 w-8 rounded-lg bg-<?= $card['col'] ?>-100 flex items-center justify-center">
                                    <i class="fas <?= $card['icon'] ?> text-<?= $card['col'] ?>-500 text-sm"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-gray-800"><?= $card['val'] ?></p>
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
                        <?php if (!empty(array_filter([$date_filter, $jenis_filter, $kelas_filter, $jurusan_filter, $status_filter, $search]))): ?>
                            <a href="pelanggaran.php"
                                class="text-xs text-amber-500 hover:text-amber-600 flex items-center gap-1 transition-colors">
                                <i class="fas fa-times-circle"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Tanggal</label>
                                <input type="date" name="date" value="<?= $date_filter ?>"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                            </div>

                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Jenis Pelanggaran</label>
                                <select name="jenis"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                                    <option value="">Semua Jenis</option>
                                    <?php foreach (['Ringan', 'Sedang', 'Berat'] as $j): ?>
                                        <option value="<?= $j ?>" <?= $jenis_filter === $j ? 'selected' : '' ?>><?= $j ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

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

                            <div>
                                <label class="text-xs text-gray-500 mb-1 block font-medium">Status Tindak Lanjut</label>
                                <select name="status"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-800
                                           focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition">
                                    <option value="">Semua Status</option>
                                    <?php foreach (['Pending', 'Proses', 'Selesai'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

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

                    <?php if (!empty($pelanggaran_list)): ?>

                        <!-- Table header strip -->
                        <div class="bg-gradient-to-r from-red-100 to-orange-100 px-5 py-4 border-b border-gray-200">
                            <h3 class="font-semibold flex items-center gap-2 text-gray-800 text-sm">
                                <i class="fas fa-table text-red-400"></i>
                                Daftar Pelanggaran
                                <span class="text-xs text-gray-500 font-normal">(<?= $total_items ?> data ditemukan)</span>
                            </h3>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full whitespace-nowrap text-sm">
                                <thead>
                                    <tr class="text-gray-500 text-xs uppercase border-b border-gray-200 bg-gray-50">
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-gray-800">NIS <?= sortIcon('nis',              $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-gray-800">Nama <?= sortIcon('nama_lengkap',     $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('kelas') ?>" class="flex items-center gap-1 hover:text-gray-800">Kelas <?= sortIcon('kelas',            $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-gray-800">Tanggal <?= sortIcon('tanggal',          $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('jenis_pelanggaran') ?>" class="flex items-center gap-1 hover:text-gray-800">Jenis <?= sortIcon('jenis_pelanggaran', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left">Deskripsi</th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('poin') ?>" class="flex items-center gap-1 hover:text-gray-800">Poin <?= sortIcon('poin',             $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-center"><a href="<?= buildSortUrl('status') ?>" class="flex items-center justify-center gap-1 hover:text-gray-800">Status <?= sortIcon('status', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left">Tindakan</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($pelanggaran_list as $p):
                                        $tp    = min((int)$p['total_poin'], 100);
                                        $color = poinColor($tp);
                                        $pb    = (int)$p['poin_berat'];
                                        $ps    = (int)$p['poin_sedang'];
                                        $pr    = (int)$p['poin_ringan'];
                                        $jc    = 'j-' . strtolower($p['jenis_pelanggaran']);
                                        $stc   = 'st-' . strtolower($p['status']);
                                    ?>
                                        <tr class="hover:bg-orange-50 transition-colors">
                                            <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($p['nis']) ?></td>

                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= $p['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                                        class="h-8 w-8 rounded-full object-cover border border-gray-200 shrink-0" alt="">
                                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($p['nama_lengkap']) ?></span>
                                                </div>
                                            </td>

                                            <td class="px-4 py-3 text-gray-600 text-xs">
                                                <?= htmlspecialchars($p['kelas']) ?> <?= htmlspecialchars($p['jurusan']) ?>
                                            </td>

                                            <td class="px-4 py-3 text-gray-700 text-xs">
                                                <?= date('d/m/Y', strtotime($p['tanggal'])) ?>
                                            </td>

                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $jc ?>">
                                                    <?= htmlspecialchars($p['jenis_pelanggaran']) ?>
                                                </span>
                                            </td>

                                            <td class="px-4 py-3">
                                                <span class="text-gray-700 text-xs max-w-[150px] truncate block"
                                                    title="<?= htmlspecialchars($p['deskripsi']) ?>">
                                                    <?= htmlspecialchars($p['deskripsi']) ?>
                                                </span>
                                            </td>

                                            <!-- Poin column -->
                                            <td class="px-4 py-3">
                                                <div class="flex flex-col gap-1.5">
                                                    <span class="font-bold text-sm
                                                        <?= $p['jenis_pelanggaran'] === 'Berat'  ? 'text-red-500'   : ($p['jenis_pelanggaran'] === 'Sedang' ? 'text-orange-500' : 'text-green-600') ?>">
                                                        <?= $p['poin'] ?> <span class="font-normal text-xs text-gray-400">poin</span>
                                                    </span>
                                                    <div class="flex flex-wrap gap-1">
                                                        <?php if ($pb > 0): ?><span class="poin-badge j-berat" title="Total poin Berat"><i class="fas fa-circle" style="font-size:4px"></i> B:<?= $pb ?></span><?php endif; ?>
                                                        <?php if ($ps > 0): ?><span class="poin-badge j-sedang" title="Total poin Sedang"><i class="fas fa-circle" style="font-size:4px"></i> S:<?= $ps ?></span><?php endif; ?>
                                                        <?php if ($pr > 0): ?><span class="poin-badge j-ringan" title="Total poin Ringan"><i class="fas fa-circle" style="font-size:4px"></i> R:<?= $pr ?></span><?php endif; ?>
                                                        <?php if ($pb === 0 && $ps === 0 && $pr === 0): ?><span class="text-xs text-gray-400">-</span><?php endif; ?>
                                                    </div>
                                                    <div class="poin-bar-wrap" title="Total poin: <?= $tp ?>">
                                                        <div class="poin-bar-fill <?= $color['bar'] ?>" style="width:<?= $tp ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $stc ?>">
                                                    <?= htmlspecialchars($p['status']) ?>
                                                </span>
                                            </td>

                                            <td class="px-4 py-3 text-gray-500 text-xs max-w-[140px] truncate"
                                                title="<?= htmlspecialchars($p['tindakan'] ?? '') ?>">
                                                <?= htmlspecialchars($p['tindakan'] ?? '-') ?>
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
                            <i class="fas fa-shield-alt text-5xl mb-4 block opacity-20"></i>
                            <p class="font-medium text-gray-500">Tidak ada data pelanggaran yang ditemukan</p>
                            <?php if (!empty(array_filter([$date_filter, $jenis_filter, $kelas_filter, $jurusan_filter, $status_filter, $search]))): ?>
                                <a href="pelanggaran.php"
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

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && window.innerWidth < 1024) toggleSidebar();
        });
    </script>
</body>

</html>
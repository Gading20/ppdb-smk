<?php
session_start();
require_once '../../config/database.php';

// Guard: hanya kepsek
if (!isset($_SESSION['kepsek_id']) || $_SESSION['role'] !== 'kepsek') {
    header("Location: ../../kepsek/login.php");
    exit();
}

// ── Filter ─────────────────────────────────────────────────────────────────
$date_filter   = $_GET['date']   ?? '';
$jenis_filter  = $_GET['jenis']  ?? '';
$status_filter = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────
$items_per_page = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// ── Base SQL ───────────────────────────────────────────────────────────────
$base   = "FROM konseling k JOIN siswa s ON k.siswa_id = s.id WHERE 1=1";
$params = [];

if ($date_filter) {
    $base .= " AND k.tanggal = :date";
    $params['date'] = $date_filter;
}
if ($jenis_filter) {
    $base .= " AND k.jenis_konseling = :jenis";
    $params['jenis'] = $jenis_filter;
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

// ── Stat cards ─────────────────────────────────────────────────────────────
$status_counts = ['Proses' => 0, 'Selesai' => 0, 'Ditunda' => 0];
$sc = $conn->query("SELECT status, COUNT(*) as c FROM konseling GROUP BY status");
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($status_counts[$r['status']])) $status_counts[$r['status']] = $r['c'];
}
$total_count = $conn->query("SELECT COUNT(*) FROM konseling")->fetchColumn();

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
    return $so === 'ASC'
        ? '<i class="fas fa-sort-up text-amber-500"></i>'
        : '<i class="fas fa-sort-down text-amber-500"></i>';
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
    <title>Data Konseling – Kepala Sekolah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* ── Disamakan 1:1 dengan index.php ── */
        .glass {
            background: rgba(17, 24, 39, .72);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(217, 119, 6, .25);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(217, 119, 6, .18), rgba(217, 119, 6, .04));
            border-left: 4px solid #d97706;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, .4);
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(217, 119, 6, .45);
            border-radius: 3px;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-up {
            animation: fadeUp .35s ease-out both;
        }

        /* Status konseling */
        .s-proses {
            background: #dbeafe;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .s-selesai {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .s-ditunda {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Jenis konseling */
        .j-akademik {
            background: #ede9fe;
            color: #7c3aed;
            border: 1px solid #ddd6fe;
        }

        .j-pribadi {
            background: #fce7f3;
            color: #db2777;
            border: 1px solid #fbcfe8;
        }

        .j-sosial {
            background: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .j-karir {
            background: #fef9c3;
            color: #ca8a04;
            border: 1px solid #fef08a;
        }

        .j-keluarga {
            background: #ffedd5;
            color: #ea580c;
            border: 1px solid #fed7aa;
        }

        .j-lainnya {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }

        .truncate-cell {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">

    <!-- Mobile overlay -->
    <div id="overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ── SIDEBAR — identik dengan index.php ────────────────────────────── -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64
        bg-white/80 backdrop-blur-md
        border-r border-amber-100
        z-50 transition-transform duration-300
        -translate-x-full lg:translate-x-0">

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

            <a href="../dashboard_kepsek/index.php"
                class="flex items-center gap-3 p-3 rounded-lg text-gray-700 hover:bg-amber-500/10 transition-colors">
                <i class="fas fa-home text-amber-400"></i><span>Dashboard</span>
            </a>

            <div class="group">
                <button onclick="toggleMenu(this)"
                    class="flex items-center gap-3 w-full p-3 rounded-lg text-gray-700 hover:bg-amber-500/10 transition-colors">
                    <i class="fas fa-calendar-check text-amber-400"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform duration-200 rotate-icon"></i>
                </button>
                <ul class="ml-8 mt-1 sub-menu space-y-1">
                    <li>
                        <a href="presensi.php"
                            class="block p-2 text-gray-600 hover:text-amber-500 hover:bg-amber-500/10 rounded-lg text-sm transition-colors">
                            Presensi
                        </a>
                    </li>
                    <li>
                        <a href="pelanggaran.php"
                            class="block p-2 text-gray-600 hover:text-amber-500 hover:bg-amber-500/10 rounded-lg text-sm transition-colors">
                            Pelanggaran
                        </a>
                    </li>
                    <li>
                        <a href="konseling.php"
                            class="block p-2 rounded-lg text-sm font-semibold text-amber-600 bg-amber-500/10">
                            Konseling
                        </a>
                    </li>
                </ul>
            </div>

            <hr class="border-gray-300/40 my-3">

            <a href="../../kepsek/logout.php"
                class="flex items-center gap-3 p-3 rounded-lg text-gray-500 hover:bg-red-500/10 hover:text-red-400 transition-colors">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ── MAIN ─────────────────────────────────────────────────────────────── -->
    <main class="lg:ml-64 min-h-screen">

        <!-- Mobile topbar — identik dengan index.php -->
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

                <!-- ── HEADER — identik dengan index.php ── -->
                <header class="flex flex-wrap justify-between items-center mb-8 fade-up">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Data Konseling Siswa 📋
                        </h1>
                        <p class="text-gray-600 text-sm mt-1">
                            <i class="fas fa-calendar-alt text-blue-500 mr-1"></i>
                            <?= date('l, d F Y') ?> &nbsp;|&nbsp;
                            <span id="clock" class="text-blue-600 font-medium"></span>
                        </p>
                    </div>

                    <div class="hidden lg:flex items-center gap-3 px-4 py-2 bg-white/80 backdrop-blur-md border border-gray-200 rounded-xl shadow-sm mt-3 lg:mt-0">
                        <img src="../../<?= $_SESSION['kepsek_photo'] ?: 'assets/default/photo-profile.png' ?>"
                            class="h-9 w-9 rounded-full object-cover border border-amber-400/50" alt="Foto">
                        <div class="text-sm">
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($_SESSION['kepsek_name']) ?></p>
                            <p class="text-xs text-amber-400">Kepala Sekolah</p>
                        </div>
                    </div>
                </header>

                <!-- ── STAT CARDS — identik dengan index.php ── -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                    <?php
                    $cards = [
                        ['label' => 'Total',   'val' => $total_count,              'icon' => 'fa-clipboard-list', 'color' => 'purple', 'delay' => 0.00],
                        ['label' => 'Proses',  'val' => $status_counts['Proses'],  'icon' => 'fa-spinner',        'color' => 'blue',   'delay' => 0.06],
                        ['label' => 'Selesai', 'val' => $status_counts['Selesai'], 'icon' => 'fa-check-circle',   'color' => 'green',  'delay' => 0.12],
                        ['label' => 'Ditunda', 'val' => $status_counts['Ditunda'], 'icon' => 'fa-pause-circle',   'color' => 'red',    'delay' => 0.18],
                    ];
                    foreach ($cards as $c): ?>
                        <div class="bg-white/80 backdrop-blur-md border border-gray-100 rounded-xl p-4
                                    hover:shadow-lg hover:scale-[1.02] transition-all duration-300
                                    cursor-default fade-up"
                            style="animation-delay:<?= $c['delay'] ?>s">
                            <div class="flex justify-between items-start mb-3">
                                <p class="text-gray-500 text-xs font-medium"><?= $c['label'] ?></p>
                                <div class="h-8 w-8 rounded-lg bg-<?= $c['color'] ?>-100 flex items-center justify-center">
                                    <i class="fas <?= $c['icon'] ?> text-<?= $c['color'] ?>-500 text-sm"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-gray-800"><?= $c['val'] ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── FILTER ── -->
                <div class="bg-white rounded-xl p-5 mb-6 shadow-sm border border-gray-200 fade-up"
                    style="animation-delay:.2s">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fas fa-filter text-amber-500"></i> Filter &amp; Pencarian
                        </h3>
                        <?php if (!empty(array_filter([$search, $jenis_filter, $status_filter, $date_filter]))): ?>
                            <a href="konseling.php"
                                class="text-xs text-amber-500 hover:text-amber-600 flex items-center gap-1">
                                <i class="fas fa-times-circle"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Tanggal</label>
                                <input type="date" name="date" value="<?= $date_filter ?>"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm
                                           text-gray-800 focus:outline-none focus:border-amber-400
                                           focus:ring-1 focus:ring-amber-200 transition-colors">
                            </div>

                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Jenis Konseling</label>
                                <select name="jenis"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm
                                           text-gray-800 focus:outline-none focus:border-amber-400
                                           focus:ring-1 focus:ring-amber-200 transition-colors">
                                    <option value="">Semua Jenis</option>
                                    <?php foreach ($jenis_options as $j): ?>
                                        <option value="<?= $j ?>" <?= $jenis_filter === $j ? 'selected' : '' ?>><?= $j ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Status</label>
                                <select name="status"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm
                                           text-gray-800 focus:outline-none focus:border-amber-400
                                           focus:ring-1 focus:ring-amber-200 transition-colors">
                                    <option value="">Semua Status</option>
                                    <?php foreach (['Proses', 'Selesai', 'Ditunda'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Cari Siswa / Konselor</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama, NIS, atau Konselor..."
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm
                                               text-gray-800 focus:outline-none focus:border-amber-400
                                               focus:ring-1 focus:ring-amber-200 transition-colors">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit"
                                class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg
                                       text-sm font-medium transition-colors flex items-center gap-2 shadow-sm">
                                <i class="fas fa-filter"></i> Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TABEL — identik strukturnya dengan tabel absensi index.php ── -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200 fade-up"
                    style="animation-delay:.25s">

                    <!-- Header bar — gradient sama dengan tabel di index.php -->
                    <div class="bg-gradient-to-r from-amber-50 to-yellow-50 px-5 py-4 border-b border-gray-200
                                flex flex-wrap justify-between items-center gap-3">
                        <h3 class="font-semibold flex items-center gap-2 text-gray-800">
                            <i class="fas fa-comments text-amber-500"></i>
                            Data Konseling
                            <span class="text-xs text-gray-500 font-normal">(<?= date('d F Y') ?>)</span>
                        </h3>

                        <!-- Quick-filter tombol — mirip filterTable index.php -->
                        <div class="flex gap-2 flex-wrap">
                            <?php foreach (['Semua', 'Proses', 'Selesai', 'Ditunda'] as $f): ?>
                                <button onclick="filterKonseling('<?= $f ?>')"
                                    class="filter-btn text-xs px-3 py-1.5 rounded-full border transition-colors
                                        <?= $f === 'Semua'
                                            ? 'bg-amber-100 border-amber-300 text-amber-600'
                                            : 'border-gray-300 text-gray-600 hover:border-amber-400 hover:text-gray-800 hover:bg-amber-50' ?>"
                                    data-filter="<?= $f ?>">
                                    <?= $f ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!empty($konseling_list)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm" id="konselingTable">
                                <thead>
                                    <tr class="text-gray-500 text-xs uppercase border-b border-gray-200 bg-gray-50">
                                        <th class="px-4 py-3 text-left">
                                            <a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                NIS <?= sortIcon('nis', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            <a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Siswa <?= sortIcon('nama_lengkap', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            <a href="<?= buildSortUrl('kelas') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Kelas <?= sortIcon('kelas', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            <a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Tanggal <?= sortIcon('tanggal', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-4 py-3 text-left">
                                            <a href="<?= buildSortUrl('jenis_konseling') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Jenis <?= sortIcon('jenis_konseling', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-4 py-3 text-left">Masalah</th>
                                        <th class="px-4 py-3 text-left">
                                            <a href="<?= buildSortUrl('konselor') ?>" class="flex items-center gap-1 hover:text-gray-800">
                                                Konselor <?= sortIcon('konselor', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-4 py-3 text-center">
                                            <a href="<?= buildSortUrl('status') ?>" class="flex items-center justify-center gap-1 hover:text-gray-800">
                                                Status <?= sortIcon('status', $sort_col, $sort_order) ?>
                                            </a>
                                        </th>
                                        <th class="px-4 py-3 text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($konseling_list as $row):
                                        $statusIcons = ['Proses' => '⏳', 'Selesai' => '✅', 'Ditunda' => '⏸'];
                                        $icon = $statusIcons[$row['status']] ?? '';
                                    ?>
                                        <tr class="hover:bg-amber-50 transition-colors konseling-row"
                                            data-status="<?= htmlspecialchars($row['status']) ?>">
                                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($row['nis']) ?></td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= $row['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                                        class="h-8 w-8 rounded-full object-cover border border-gray-300" alt="">
                                                    <span class="font-medium text-gray-800">
                                                        <?= htmlspecialchars($row['nama_lengkap']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <?= htmlspecialchars($row['kelas']) ?> <?= htmlspecialchars($row['jurusan']) ?>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700">
                                                <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium j-<?= strtolower($row['jenis_konseling']) ?>">
                                                    <?= htmlspecialchars($row['jenis_konseling']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <div class="truncate-cell" title="<?= htmlspecialchars($row['masalah']) ?>">
                                                    <?= htmlspecialchars($row['masalah']) ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700">
                                                <?= htmlspecialchars($row['konselor']) ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium s-<?= strtolower($row['status']) ?>">
                                                    <?= $icon ?> <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <a href="detailk.php?id=<?= $row['id'] ?>"
                                                    class="inline-flex items-center justify-center text-blue-500
                                                           hover:text-blue-600 p-1.5 rounded-full hover:bg-blue-50 transition-colors"
                                                    title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="px-5 py-4 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-3">
                            <p class="text-xs text-gray-500">
                                Menampilkan <?= min($offset + 1, $total_items) ?>–<?= min($offset + $items_per_page, $total_items) ?>
                                dari <?= $total_items ?> data
                            </p>
                            <div class="flex gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= pageUrl(1) ?>"
                                        class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded hover:bg-amber-50 hover:border-amber-300 text-xs transition-colors">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="<?= pageUrl($page - 1) ?>"
                                        class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded hover:bg-amber-50 hover:border-amber-300 text-xs transition-colors">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php
                                $s = max(1, $page - 2);
                                $e = min($total_pages, $page + 2);
                                if ($s > 1) echo '<span class="px-2 py-1.5 text-gray-400 text-xs">…</span>';
                                for ($i = $s; $i <= $e; $i++) {
                                    $cls = $i == $page
                                        ? 'bg-amber-500 text-white border-amber-500'
                                        : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-amber-50 hover:border-amber-300';
                                    echo "<a href='" . pageUrl($i) . "'
                                             class='px-3 py-1.5 border $cls rounded text-xs transition-colors'>$i</a>";
                                }
                                if ($e < $total_pages) echo '<span class="px-2 py-1.5 text-gray-400 text-xs">…</span>';
                                ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= pageUrl($page + 1) ?>"
                                        class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded hover:bg-amber-50 hover:border-amber-300 text-xs transition-colors">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="<?= pageUrl($total_pages) ?>"
                                        class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded hover:bg-amber-50 hover:border-amber-300 text-xs transition-colors">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="py-16 text-center text-gray-500">
                            <i class="fas fa-inbox text-3xl mb-3 block opacity-40"></i>
                            <p class="font-medium">Tidak ada data konseling yang ditemukan</p>
                            <?php if (!empty($_GET)): ?>
                                <a href="konseling.php"
                                    class="mt-3 inline-block text-amber-500 hover:text-amber-600 text-sm">
                                    <i class="fas fa-arrow-left mr-1"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div><!-- /table card -->

            </div><!-- /max-w -->
        </div><!-- /padding -->
    </main>

    <!-- ── SCRIPTS — identik dengan index.php ── -->
    <script>
        // Clock
        function tick() {
            const el = document.getElementById('clock');
            if (el) el.textContent = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }
        setInterval(tick, 1000);
        tick();

        // Sidebar
        function toggleSidebar() {
            const s = document.getElementById('sidebar');
            const o = document.getElementById('overlay');
            s.classList.toggle('-translate-x-full');
            o.classList.toggle('hidden');
        }

        // Accordion sub-menu
        function toggleMenu(btn) {
            const ul = btn.nextElementSibling;
            const ico = btn.querySelector('.rotate-icon');
            ul.classList.toggle('hidden');
            ico.style.transform = ul.classList.contains('hidden') ? '' : 'rotate(180deg)';
        }
        // Open by default
        document.querySelectorAll('.sub-menu').forEach(ul => ul.classList.remove('hidden'));
        document.querySelectorAll('.rotate-icon').forEach(ico => ico.style.transform = 'rotate(180deg)');

        // Quick-filter status (mirip filterTable di index.php)
        function filterKonseling(status) {
            document.querySelectorAll('.filter-btn').forEach(b => {
                const active = b.dataset.filter === status;
                b.classList.toggle('bg-amber-100', active);
                b.classList.toggle('border-amber-300', active);
                b.classList.toggle('text-amber-600', active);
                b.classList.toggle('border-gray-300', !active);
                b.classList.toggle('text-gray-600', !active);
            });
            document.querySelectorAll('.konseling-row').forEach(row => {
                row.style.display =
                    (status === 'Semua' || row.dataset.status === status) ? '' : 'none';
            });
        }

        // Auto-submit filter on select / date change
        document.querySelectorAll('#filterForm select, #filterForm input[type="date"]')
            .forEach(el => el.addEventListener('change', () =>
                document.getElementById('filterForm').submit()));
    </script>
</body>

</html>
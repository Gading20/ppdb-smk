<?php
session_start();
require_once '../../config/database.php';

// Guard: hanya wali kelas
if (!isset($_SESSION['walikelas_id'])) {
    header("Location: ../../wali_kelas/login.php");
    exit();
}

// Kelas & jurusan dari session (tidak bisa diubah user)
$kelas   = $_SESSION['walikelas_kelas'];
$jurusan = $_SESSION['walikelas_jurusan'];

// ── Filter ─────────────────────────────────────────────────────────────────
$date_filter   = $_GET['date']   ?? '';
$jenis_filter  = $_GET['jenis']  ?? '';
$status_filter = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────
$items_per_page = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// ── Base SQL (selalu filter kelas sendiri) ─────────────────────────────────
$base   = "FROM konseling k JOIN siswa s ON k.siswa_id = s.id
           WHERE s.kelas = :kelas AND s.jurusan = :jurusan";
$params = ['kelas' => $kelas, 'jurusan' => $jurusan];

if ($date_filter) {
    $base .= " AND k.tanggal = :date";
    $params['date']   = $date_filter;
}
if ($jenis_filter) {
    $base .= " AND k.jenis_konseling = :jenis";
    $params['jenis']  = $jenis_filter;
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

// ── Total siswa di kelas ini ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM siswa WHERE kelas = :kelas AND jurusan = :jurusan");
$stmt->execute(['kelas' => $kelas, 'jurusan' => $jurusan]);
$total_students = $stmt->fetchColumn();

// ── Statistik absensi hari ini (kelas sendiri) ─────────────────────────────
$today_date = date('Y-m-d');
$stats = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'terlambat' => 0, 'alpha' => 0];

$stmt_stats = $conn->prepare(
    "SELECT a.status, COUNT(*) as count FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :today AND a.approval_status = 'Approved'
       AND s.kelas = :kelas AND s.jurusan = :jurusan
     GROUP BY a.status"
);
$stmt_stats->execute(['today' => $today_date, 'kelas' => $kelas, 'jurusan' => $jurusan]);
while ($row = $stmt_stats->fetch(PDO::FETCH_ASSOC)) {
    $stats[strtolower($row['status'])] = $row['count'];
}

// ── Stat cards (kelas sendiri) ─────────────────────────────────────────────
$status_counts = ['Proses' => 0, 'Selesai' => 0, 'Ditunda' => 0];
$sc = $conn->prepare(
    "SELECT k.status, COUNT(*) as c FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :kelas AND s.jurusan = :jurusan
     GROUP BY k.status"
);
$sc->execute(['kelas' => $kelas, 'jurusan' => $jurusan]);
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($status_counts[$r['status']])) $status_counts[$r['status']] = $r['c'];
}

$total_stmt = $conn->prepare(
    "SELECT COUNT(*) FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :kelas AND s.jurusan = :jurusan"
);
$total_stmt->execute(['kelas' => $kelas, 'jurusan' => $jurusan]);
$total_count = $total_stmt->fetchColumn();

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
    if ($col !== $sc) return '<i class="fas fa-sort text-gray-600 opacity-50"></i>';
    return $so === 'ASC' ? '<i class="fas fa-sort-up text-emerald-400"></i>' : '<i class="fas fa-sort-down text-emerald-400"></i>';
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
    <title>Data Konseling – Wali Kelas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass {
            background: rgba(17, 24, 39, .72);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(16, 185, 129, .22);
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #064e3b 100%);
        }

        .s-proses {
            background: rgba(59, 130, 246, .12);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, .3);
        }

        .s-selesai {
            background: rgba(16, 185, 129, .12);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .s-ditunda {
            background: rgba(239, 68, 68, .12);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, .3);
        }

        .j-akademik {
            background: rgba(139, 92, 246, .12);
            color: #8b5cf6;
            border: 1px solid rgba(139, 92, 246, .3);
        }

        .j-pribadi {
            background: rgba(236, 72, 153, .12);
            color: #ec4899;
            border: 1px solid rgba(236, 72, 153, .3);
        }

        .j-sosial {
            background: rgba(16, 185, 129, .12);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .j-karir {
            background: rgba(234, 179, 8, .12);
            color: #eab308;
            border: 1px solid rgba(234, 179, 8, .3);
        }

        .j-keluarga {
            background: rgba(249, 115, 22, .12);
            color: #f97316;
            border: 1px solid rgba(249, 115, 22, .3);
        }

        .j-lainnya {
            background: rgba(107, 114, 128, .12);
            color: #6b7280;
            border: 1px solid rgba(107, 114, 128, .3);
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(8px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .fade-up {
            animation: fadeUp .3s ease-out both
        }

        .truncate-cell {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">

    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass border-r border-emerald-900/30 z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-5 border-b border-emerald-900/30">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" class="h-10 w-auto" alt="Logo">
                <div>
                    <p class="font-semibold text-sm">SMK NURUL ULUM</p>
                    <p class="text-xs text-emerald-400">Wali Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?></p>
                </div>
            </div>
            <button class="lg:hidden text-gray-400 hover:text-white" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav class="p-4 space-y-1 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="../dashboard/index.php" class="flex items-center gap-3 p-3 rounded-lg text-gray-400 hover:bg-emerald-500/10 transition-colors">
                <i class="fas fa-home text-emerald-400"></i><span>Dashboard</span>
            </a>
            <div>
                <button onclick="toggleMenu(this)" class="flex items-center gap-3 w-full p-3 rounded-lg text-gray-300 hover:bg-emerald-500/10 transition-colors">
                    <i class="fas fa-calendar-check text-emerald-400"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-xs rotate-icon" style="transform:rotate(180deg)"></i>
                </button>
                <ul class="ml-8 mt-1 space-y-1 sub-menu">
                    <li><a href="presensi.php" class="block p-2 text-gray-400 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg text-sm">Presensi</a></li>
                    <li><a href="pelanggaran.php" class="block p-2 text-gray-400 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg text-sm">Pelanggaran</a></li>
                    <li><a href="konseling.php" class="block p-2 text-emerald-400 bg-emerald-500/10 rounded-lg text-sm font-medium">Konseling</a></li>
                </ul>
            </div>

            <!-- Info Cepat -->
            <!-- <div class="px-3 py-2">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Info Kelas</p>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between"><span class="text-gray-400">Kelas</span><span class="font-semibold text-emerald-300"><?= htmlspecialchars($kelas . ' ' . $jurusan) ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Total Siswa</span><span class="font-semibold"><?= $total_students ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Hadir Hari Ini</span><span class="font-semibold text-emerald-400"><?= $stats['hadir'] ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Alpha Hari Ini</span><span class="font-semibold text-red-400"><?= $stats['alpha'] ?></span></div>
                </div>
            </div> -->
            <a href="../../wali_kelas/logout.php" class="flex items-center gap-3 p-3 rounded-lg text-gray-400 hover:bg-red-500/10 hover:text-red-400 transition-colors">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ── MAIN ─────────────────────────────────────────────────────────────── -->
    <main class="lg:ml-64 min-h-screen">

        <!-- Mobile topbar -->
        <div class="lg:hidden sticky top-0 z-30 glass px-4 py-3 flex items-center justify-between border-b border-emerald-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/50"><i class="fas fa-bars"></i></button>
                <span class="text-sm font-medium">Konseling Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?></span>
            </div>
            <img src="../../<?= $_SESSION['walikelas_photo'] ?: 'assets/default/photo-profile.png' ?>"
                class="h-8 w-8 rounded-full object-cover border border-emerald-500/50" alt="">
        </div>

        <div class="p-5 md:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-6 fade-up">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold flex items-center gap-2">
                            <i class="fas fa-comments text-emerald-400"></i>
                            Konseling Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                        </h1>
                        <p class="text-gray-400 text-sm mt-1">Monitoring konseling siswa – hanya lihat</p>
                    </div>
                    <span class="mt-3 lg:mt-0 flex items-center gap-2 px-4 py-2 glass rounded-xl text-xs text-emerald-300 border border-emerald-500/30">
                        <i class="fas fa-eye"></i> Mode Lihat Saja
                    </span>
                </header>

                <!-- Stat cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6 fade-up" style="animation-delay:.05s">
                    <?php
                    $cards = [
                        ['Total',   $total_count,              'purple',  'fa-clipboard-list'],
                        ['Proses',  $status_counts['Proses'],  'blue',    'fa-spinner'],
                        ['Selesai', $status_counts['Selesai'], 'emerald', 'fa-check-circle'],
                        ['Ditunda', $status_counts['Ditunda'], 'red',     'fa-pause-circle'],
                    ];
                    foreach ($cards as [$lbl, $val, $col, $ico]): ?>
                        <div class="glass rounded-xl p-4 flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-<?= $col ?>-500/15 flex items-center justify-center shrink-0">
                                <i class="fas <?= $ico ?> text-<?= $col ?>-400"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400"><?= $lbl ?></p>
                                <p class="text-xl font-bold"><?= $val ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Filter -->
                <div class="glass rounded-xl p-5 mb-6 fade-up" style="animation-delay:.1s">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-medium flex items-center gap-2">
                            <i class="fas fa-filter text-emerald-400 text-sm"></i> Filter & Pencarian
                        </h3>
                        <?php if (!empty(array_filter([$search, $jenis_filter, $status_filter, $date_filter]))): ?>
                            <a href="konseling.php" class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1">
                                <i class="fas fa-times-circle"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                    <form method="GET" id="filterForm">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                            <!-- Tanggal -->
                            <div>
                                <label class="text-xs text-gray-400 mb-1 block">Tanggal</label>
                                <input type="date" name="date" value="<?= $date_filter ?>"
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                            </div>

                            <!-- Jenis -->
                            <div>
                                <label class="text-xs text-gray-400 mb-1 block">Jenis Konseling</label>
                                <select name="jenis" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                                    <option value="">Semua Jenis</option>
                                    <?php foreach ($jenis_options as $j): ?>
                                        <option value="<?= $j ?>" <?= $jenis_filter === $j ? 'selected' : '' ?>><?= $j ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="text-xs text-gray-400 mb-1 block">Status</label>
                                <select name="status" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                                    <option value="">Semua Status</option>
                                    <?php foreach (['Proses', 'Selesai', 'Ditunda'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Search -->
                            <div>
                                <label class="text-xs text-gray-400 mb-1 block">Cari Siswa / Konselor</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama, NIS, atau Konselor..."
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg pl-9 pr-3 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                                <i class="fas fa-filter"></i> Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Table -->
                <div class="glass rounded-xl overflow-hidden fade-up" style="animation-delay:.15s">

                    <?php if (!empty($konseling_list)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full whitespace-nowrap text-sm">
                                <thead>
                                    <tr class="bg-gray-800/50 text-gray-400 text-xs uppercase">
                                        <th class="px-5 py-3 text-left"><a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-white">NIS <?= sortIcon('nis', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-5 py-3 text-left"><a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-white">Nama <?= sortIcon('nama_lengkap', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-5 py-3 text-left"><a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-white">Tanggal <?= sortIcon('tanggal', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-5 py-3 text-left"><a href="<?= buildSortUrl('jenis_konseling') ?>" class="flex items-center gap-1 hover:text-white">Jenis <?= sortIcon('jenis_konseling', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-5 py-3 text-left">Masalah</th>
                                        <th class="px-5 py-3 text-left"><a href="<?= buildSortUrl('konselor') ?>" class="flex items-center gap-1 hover:text-white">Konselor <?= sortIcon('konselor', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-5 py-3 text-left"><a href="<?= buildSortUrl('status') ?>" class="flex items-center gap-1 hover:text-white">Status <?= sortIcon('status', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-5 py-3 text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/40">
                                    <?php foreach ($konseling_list as $row):
                                        $statusIcons = ['Proses' => '⏳', 'Selesai' => '✅', 'Ditunda' => '⏸'];
                                        $icon = $statusIcons[$row['status']] ?? '';
                                    ?>
                                        <tr class="hover:bg-emerald-900/10 transition-colors">
                                            <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($row['nis']) ?></td>
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= $row['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                                        class="h-8 w-8 rounded-full object-cover border border-gray-700 shrink-0" alt="">
                                                    <span class="font-medium"><?= htmlspecialchars($row['nama_lengkap']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-gray-300"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                            <td class="px-5 py-3">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium j-<?= strtolower($row['jenis_konseling']) ?>">
                                                    <?= htmlspecialchars($row['jenis_konseling']) ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-gray-400">
                                                <div class="truncate-cell" title="<?= htmlspecialchars($row['masalah']) ?>">
                                                    <?= htmlspecialchars($row['masalah']) ?>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-gray-300"><?= htmlspecialchars($row['konselor']) ?></td>
                                            <td class="px-5 py-3">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium s-<?= strtolower($row['status']) ?>">
                                                    <?= $icon ?> <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <a href="detailk.php?id=<?= $row['id'] ?>"
                                                    class="inline-flex items-center justify-center text-blue-400 hover:text-blue-300 p-1.5 rounded-full hover:bg-blue-500/10 transition-colors"
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
                        <div class="px-5 py-4 border-t border-gray-800/50 flex flex-col sm:flex-row justify-between items-center gap-3">
                            <p class="text-xs text-gray-400">
                                Menampilkan <?= min($offset + 1, $total_items) ?>–<?= min($offset + $items_per_page, $total_items) ?> dari <?= $total_items ?> data
                            </p>
                            <div class="flex gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= pageUrl(1) ?>" class="px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-xs"><i class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= pageUrl($page - 1) ?>" class="px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-xs"><i class="fas fa-angle-left"></i></a>
                                <?php endif; ?>
                                <?php
                                $s = max(1, $page - 2);
                                $e = min($total_pages, $page + 2);
                                if ($s > 1) echo '<span class="px-2 py-1.5 text-gray-500 text-xs">…</span>';
                                for ($i = $s; $i <= $e; $i++) {
                                    $cls = $i == $page ? 'bg-emerald-600 text-white' : 'bg-gray-800 hover:bg-gray-700';
                                    echo "<a href='" . pageUrl($i) . "' class='px-3 py-1.5 $cls rounded text-xs'>$i</a>";
                                }
                                if ($e < $total_pages) echo '<span class="px-2 py-1.5 text-gray-500 text-xs">…</span>';
                                ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= pageUrl($page + 1) ?>" class="px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-xs"><i class="fas fa-angle-right"></i></a>
                                    <a href="<?= pageUrl($total_pages) ?>" class="px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-xs"><i class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="py-16 text-center text-gray-500">
                            <i class="fas fa-comments text-4xl mb-4 block opacity-30"></i>
                            <p>Tidak ada data konseling kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?> yang ditemukan</p>
                            <?php if (!empty(array_filter([$search, $jenis_filter, $status_filter, $date_filter]))): ?>
                                <a href="konseling.php" class="mt-3 inline-block text-emerald-400 hover:text-emerald-300 text-sm">
                                    <i class="fas fa-arrow-left mr-1"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div><!-- /table card -->
            </div>
        </div>
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
        document.querySelectorAll('#filterForm select, #filterForm input[type="date"]')
            .forEach(el => el.addEventListener('change', () => document.getElementById('filterForm').submit()));
    </script>
</body>

</html>
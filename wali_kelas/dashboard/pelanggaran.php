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
// Tingkat kelas (10/11/12) yang cocok dengan kolom kelas di tabel siswa
$tingkat = $_SESSION['walikelas_tingkat'] ?? $kelas;

// ── Filter ─────────────────────────────────────────────────────────────────
$date_filter   = $_GET['date']   ?? '';
$jenis_filter  = $_GET['jenis']  ?? '';
$status_filter = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────
$items_per_page = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// ── SQL (selalu filter kelas sendiri) ─────────────────────────────────────
$where  = "WHERE s.kelas = :tingkat AND s.jurusan = :jurusan";
$params = ['tingkat' => $tingkat, 'jurusan' => $jurusan];

if ($date_filter) {
    $where .= " AND p.tanggal = :date";
    $params['date']   = $date_filter;
}
if ($jenis_filter) {
    $where .= " AND p.jenis_pelanggaran = :jenis";
    $params['jenis']  = $jenis_filter;
}
if ($status_filter) {
    $where .= " AND p.status = :status";
    $params['status'] = $status_filter;
}
if ($search) {
    $where .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $params['search'] = "%$search%";
}

// Sort
$valid_cols = ['nis', 'nama_lengkap', 'kelas', 'tanggal', 'jenis_pelanggaran', 'poin', 'status', 'total_poin'];
$sort_col   = in_array($_GET['sort'] ?? '', $valid_cols) ? $_GET['sort'] : 'tanggal';
$sort_order = ($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// Count
$count_stmt = $conn->prepare(
    "SELECT COUNT(*) FROM pelanggaran p JOIN siswa s ON p.siswa_id = s.id $where"
);
foreach ($params as $k => $v) $count_stmt->bindValue(":$k", $v);
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_items / $items_per_page));

// ── Total siswa di kelas ini ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM siswa WHERE kelas = :tingkat AND jurusan = :jurusan");
$stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$total_students = $stmt->fetchColumn();

// ── Statistik absensi hari ini (kelas sendiri) ─────────────────────────────
$today_date = date('Y-m-d');
$stats = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'terlambat' => 0, 'alpha' => 0];

$stmt_stats = $conn->prepare(
    "SELECT a.status, COUNT(*) as count FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :today AND a.approval_status = 'Approved'
       AND s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY a.status"
);
$stmt_stats->execute(['today' => $today_date, 'tingkat' => $tingkat, 'jurusan' => $jurusan]);
while ($row = $stmt_stats->fetch(PDO::FETCH_ASSOC)) {
    $stats[strtolower($row['status'])] = $row['count'];
}

// ── Stat cards (kelas sendiri) ─────────────────────────────────────────────
$status_counts = ['Proses' => 0, 'Selesai' => 0, 'Ditunda' => 0];
$sc = $conn->prepare(
    "SELECT k.status, COUNT(*) as c FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY k.status"
);
$sc->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($status_counts[$r['status']])) $status_counts[$r['status']] = $r['c'];
}

$total_stmt = $conn->prepare(
    "SELECT COUNT(*) FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     WHERE s.kelas = :tingkat AND s.jurusan = :jurusan"
);
$total_stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$total_count = $total_stmt->fetchColumn();

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

// ── Stat cards (kelas sendiri, hari ini) ───────────────────────────────────
$today = date('Y-m-d');
$jenis_counts = ['Ringan' => 0, 'Sedang' => 0, 'Berat' => 0];
$sc = $conn->prepare(
    "SELECT p.jenis_pelanggaran, COUNT(*) as c FROM pelanggaran p
     JOIN siswa s ON p.siswa_id = s.id
     WHERE p.tanggal = :t AND s.kelas = :tingkat AND s.jurusan = :jurusan
     GROUP BY p.jenis_pelanggaran"
);
$sc->execute(['t' => $today, 'tingkat' => $tingkat, 'jurusan' => $jurusan]);
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($jenis_counts[$r['jenis_pelanggaran']])) $jenis_counts[$r['jenis_pelanggaran']] = $r['c'];
}
$proses_stmt = $conn->prepare(
    "SELECT COUNT(*) FROM pelanggaran p JOIN siswa s ON p.siswa_id = s.id
     WHERE p.status = 'Proses' AND s.kelas = :tingkat AND s.jurusan = :jurusan"
);
$proses_stmt->execute(['tingkat' => $tingkat, 'jurusan' => $jurusan]);
$proses_count = $proses_stmt->fetchColumn();

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
function poinColor($poin)
{
    if ($poin >= 75) return ['bar' => 'bg-red-500',    'text' => 'text-red-400'];
    if ($poin >= 50) return ['bar' => 'bg-orange-500', 'text' => 'text-orange-400'];
    if ($poin >= 25) return ['bar' => 'bg-yellow-500', 'text' => 'text-amber-600'];
    return              ['bar' => 'bg-emerald-500', 'text' => 'text-emerald-400'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Pelanggaran – Wali Kelas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass {
            background: rgba(17, 24, 39, .72);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(16, 185, 129, .22);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(16, 185, 129, .18), rgba(16, 185, 129, .04));
            border-left: 4px solid #10b981;
        }

        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ede9fe 100%);
        }

        .j-ringan {
            background: rgba(34, 197, 94, .1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, .3);
        }

        .j-sedang {
            background: rgba(234, 179, 8, .1);
            color: #eab308;
            border: 1px solid rgba(234, 179, 8, .3);
        }

        .j-berat {
            background: rgba(239, 68, 68, .1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, .3);
        }

        .st-selesai {
            background: rgba(16, 185, 129, .1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .st-proses {
            background: rgba(217, 119, 6, .1);
            color: #d97706;
            border: 1px solid rgba(217, 119, 6, .3);
        }

        .st-pending {
            background: rgba(107, 114, 128, .1);
            color: #9ca3af;
            border: 1px solid rgba(107, 114, 128, .3);
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, .4);
            border-radius: 3px
        }

        .poin-bar-wrap {
            width: 60px;
            height: 6px;
            background: rgba(255, 255, 255, .1);
            border-radius: 9999px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle
        }

        .poin-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width .4s
        }

        .poin-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 1px 6px;
            border-radius: 9999px;
            font-size: .68rem;
            font-weight: 600;
            border: 1px solid
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
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">

    <div id="overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass border-r border-emerald-200 z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-5 border-b border-emerald-200">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" class="h-10 w-auto" alt="Logo">
                <div>
                    <p class="font-semibold text-sm">SMK NURUL ULUM</p>
                    <p class="text-xs text-emerald-400">Wali Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?></p>
                </div>
            </div>
            <button class="lg:hidden text-gray-500 hover:text-gray-800" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        </div>
        <nav class="p-4 space-y-1 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="../dashboard/index.php" class="flex items-center gap-3 p-3 rounded-lg text-gray-500 hover:bg-emerald-500/10 transition-colors">
                <i class="fas fa-home text-emerald-400"></i><span>Dashboard</span>
            </a>
            <div>
                <button onclick="toggleMenu(this)" class="flex items-center gap-3 w-full p-3 rounded-lg text-gray-700 hover:bg-emerald-500/10 transition-colors">
                    <i class="fas fa-calendar-check text-emerald-400"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-xs rotate-icon" style="transform:rotate(180deg)"></i>
                </button>
                <ul class="ml-8 mt-1 space-y-1 sub-menu">
                    <li><a href="presensi.php" class="block p-2 text-gray-600 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg text-sm">Presensi</a></li>
                    <li><a href="pelanggaran.php" class="block p-2 text-emerald-400 bg-emerald-500/10 rounded-lg text-sm font-medium">Pelanggaran</a></li>
                    <li><a href="konseling.php" class="block p-2 text-gray-600 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg text-sm">Konseling</a></li>
                </ul>
            </div>

            <!-- Info Cepat -->
            <!-- <div class="px-3 py-2">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Info Kelas</p>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between"><span class="text-gray-500">Kelas</span><span class="font-semibold text-emerald-300"><?= htmlspecialchars($kelas . ' ' . $jurusan) ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Total Siswa</span><span class="font-semibold"><?= $total_students ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Hadir Hari Ini</span><span class="font-semibold text-emerald-400"><?= $stats['hadir'] ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Alpha Hari Ini</span><span class="font-semibold text-red-400"><?= $stats['alpha'] ?></span></div>
                </div>
            </div> -->
            <a href="../../wali_kelas/logout.php" class="flex items-center gap-3 p-3 rounded-lg text-gray-500 hover:bg-red-500/10 hover:text-red-400 transition-colors">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ── MAIN ─────────────────────────────────────────────────────────────── -->
    <main class="lg:ml-64 min-h-screen">

        <!-- Mobile topbar -->
        <div class="lg:hidden sticky top-0 z-30 glass px-4 py-3 flex items-center justify-between border-b border-emerald-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-800 p-2 -ml-2 rounded-lg hover:bg-gray-50/50"><i class="fas fa-bars"></i></button>
                <span class="text-sm font-medium">Pelanggaran Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?></span>
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
                            <i class="fas fa-exclamation-triangle text-red-400"></i>
                            Pelanggaran Kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?>
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">Data pelanggaran siswa – hanya lihat</p>
                    </div>
                    <span class="mt-3 lg:mt-0 flex items-center gap-2 px-4 py-2 glass rounded-xl text-xs text-emerald-300 border border-emerald-200">
                        <i class="fas fa-eye"></i> Mode Lihat Saja
                    </span>
                </header>

                <!-- Stat cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 fade-up" style="animation-delay:.05s">
                    <div class="glass rounded-xl p-4 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-lg bg-green-500/15 flex items-center justify-center shrink-0">
                            <i class="fas fa-exclamation-circle text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Ringan (Hari Ini)</p>
                            <p class="text-xl font-bold"><?= $jenis_counts['Ringan'] ?></p>
                        </div>
                    </div>
                    <div class="glass rounded-xl p-4 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-lg bg-yellow-500/15 flex items-center justify-center shrink-0">
                            <i class="fas fa-exclamation-triangle text-amber-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Sedang (Hari Ini)</p>
                            <p class="text-xl font-bold"><?= $jenis_counts['Sedang'] ?></p>
                        </div>
                    </div>
                    <div class="glass rounded-xl p-4 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-lg bg-red-500/15 flex items-center justify-center shrink-0">
                            <i class="fas fa-times-circle text-red-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Berat (Hari Ini)</p>
                            <p class="text-xl font-bold"><?= $jenis_counts['Berat'] ?></p>
                        </div>
                    </div>
                    <div class="glass rounded-xl p-4 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-lg bg-amber-500/15 flex items-center justify-center shrink-0">
                            <i class="fas fa-hourglass-half text-amber-400"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Sedang Diproses</p>
                            <p class="text-xl font-bold"><?= $proses_count ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="glass rounded-xl p-5 mb-6 fade-up" style="animation-delay:.1s">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-medium flex items-center gap-2">
                            <i class="fas fa-filter text-emerald-400 text-sm"></i> Filter & Pencarian
                        </h3>
                        <?php if (!empty(array_filter([$date_filter, $jenis_filter, $status_filter, $search]))): ?>
                            <a href="pelanggaran.php" class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1">
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
                                <label class="text-xs text-gray-500 mb-1 block">Tanggal</label>
                                <input type="date" name="date" value="<?= $date_filter ?>"
                                    class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-emerald-500">
                            </div>

                            <!-- Jenis -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Jenis Pelanggaran</label>
                                <select name="jenis" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-emerald-500">
                                    <option value="">Semua Jenis</option>
                                    <?php foreach (['Ringan', 'Sedang', 'Berat'] as $j): ?>
                                        <option value="<?= $j ?>" <?= $jenis_filter === $j ? 'selected' : '' ?>><?= $j ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Status Tindak Lanjut</label>
                                <select name="status" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-emerald-500">
                                    <option value="">Semua Status</option>
                                    <?php foreach (['Pending', 'Proses', 'Selesai'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Search -->
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Cari Siswa</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama atau NIS..."
                                        class="w-full bg-gray-50 border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-emerald-500">
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
                    <?php if (!empty($pelanggaran_list)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full whitespace-nowrap text-sm">
                                <thead>
                                    <tr class="bg-gray-50/50 text-gray-500 text-xs uppercase">
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-gray-800">NIS <?= sortIcon('nis', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-gray-800">Nama <?= sortIcon('nama_lengkap', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-gray-800">Tanggal <?= sortIcon('tanggal', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('jenis_pelanggaran') ?>" class="flex items-center gap-1 hover:text-gray-800">Jenis <?= sortIcon('jenis_pelanggaran', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left">Deskripsi</th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('poin') ?>" class="flex items-center gap-1 hover:text-gray-800">Poin <?= sortIcon('poin', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left"><a href="<?= buildSortUrl('status') ?>" class="flex items-center gap-1 hover:text-gray-800">Status <?= sortIcon('status', $sort_col, $sort_order) ?></a></th>
                                        <th class="px-4 py-3 text-left">Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/40">
                                    <?php foreach ($pelanggaran_list as $p):
                                        $tp    = min((int)$p['total_poin'], 100);
                                        $color = poinColor($tp);
                                        $pb    = (int)$p['poin_berat'];
                                        $ps    = (int)$p['poin_sedang'];
                                        $pr    = (int)$p['poin_ringan'];
                                        $jc    = 'j-' . strtolower($p['jenis_pelanggaran']);
                                        $stc   = 'st-' . strtolower($p['status']);
                                    ?>
                                        <tr class="hover:bg-emerald-50 transition-colors">
                                            <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($p['nis']) ?></td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= $p['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                                        class="h-8 w-8 rounded-full object-cover border border-gray-300 shrink-0" alt="">
                                                    <span class="font-medium"><?= htmlspecialchars($p['nama_lengkap']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700"><?= date('d/m/Y', strtotime($p['tanggal'])) ?></td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 rounded-full text-xs <?= $jc ?>"><?= $p['jenis_pelanggaran'] ?></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-gray-700 max-w-[150px] truncate block" title="<?= htmlspecialchars($p['deskripsi']) ?>">
                                                    <?= htmlspecialchars($p['deskripsi']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-col gap-1.5">
                                                    <span class="font-bold <?= $p['jenis_pelanggaran'] === 'Berat' ? 'text-red-400' : ($p['jenis_pelanggaran'] === 'Sedang' ? 'text-amber-600' : 'text-green-600') ?>">
                                                        <?= $p['poin'] ?> <span class="font-normal text-xs text-gray-500">poin</span>
                                                    </span>
                                                    <div class="flex flex-wrap gap-1">
                                                        <?php if ($pb > 0): ?><span class="poin-badge j-berat" title="Total poin Berat"><i class="fas fa-circle" style="font-size:4px"></i> B:<?= $pb ?></span><?php endif; ?>
                                                        <?php if ($ps > 0): ?><span class="poin-badge j-sedang" title="Total poin Sedang"><i class="fas fa-circle" style="font-size:4px"></i> S:<?= $ps ?></span><?php endif; ?>
                                                        <?php if ($pr > 0): ?><span class="poin-badge j-ringan" title="Total poin Ringan"><i class="fas fa-circle" style="font-size:4px"></i> R:<?= $pr ?></span><?php endif; ?>
                                                        <?php if ($pb === 0 && $ps === 0 && $pr === 0): ?><span class="text-xs text-gray-500">-</span><?php endif; ?>
                                                    </div>
                                                    <div class="poin-bar-wrap" title="Total poin: <?= $tp ?>">
                                                        <div class="poin-bar-fill <?= $color['bar'] ?>" style="width:<?= $tp ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 rounded-full text-xs <?= $stc ?>"><?= $p['status'] ?></span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 max-w-[140px] truncate" title="<?= htmlspecialchars($p['tindakan'] ?? '') ?>">
                                                <?= htmlspecialchars($p['tindakan'] ?? '-') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="px-5 py-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                            <p class="text-xs text-gray-500">
                                Menampilkan <?= min($offset + 1, $total_items) ?>–<?= min($offset + $items_per_page, $total_items) ?> dari <?= $total_items ?> data
                            </p>
                            <div class="flex gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= pageUrl(1) ?>" class="px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-xs"><i class="fas fa-angle-double-left"></i></a>
                                    <a href="<?= pageUrl($page - 1) ?>" class="px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-xs"><i class="fas fa-angle-left"></i></a>
                                <?php endif; ?>
                                <?php
                                $s = max(1, $page - 2);
                                $e = min($total_pages, $page + 2);
                                if ($s > 1) echo '<span class="px-2 py-1.5 text-gray-500 text-xs">…</span>';
                                for ($i = $s; $i <= $e; $i++) {
                                    $cls = $i == $page ? 'bg-emerald-600 text-gray-800' : 'bg-gray-50 hover:bg-gray-100';
                                    echo "<a href='" . pageUrl($i) . "' class='px-3 py-1.5 $cls rounded text-xs'>$i</a>";
                                }
                                if ($e < $total_pages) echo '<span class="px-2 py-1.5 text-gray-500 text-xs">…</span>';
                                ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= pageUrl($page + 1) ?>" class="px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-xs"><i class="fas fa-angle-right"></i></a>
                                    <a href="<?= pageUrl($total_pages) ?>" class="px-3 py-1.5 bg-gray-50 rounded hover:bg-gray-100 text-xs"><i class="fas fa-angle-double-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="py-16 text-center text-gray-500">
                            <i class="fas fa-shield-alt text-4xl mb-4 block opacity-30"></i>
                            <p>Tidak ada data pelanggaran kelas <?= htmlspecialchars($kelas . ' ' . $jurusan) ?> yang ditemukan</p>
                            <?php if (!empty(array_filter([$date_filter, $jenis_filter, $status_filter, $search]))): ?>
                                <a href="pelanggaran.php" class="mt-3 inline-block text-emerald-400 hover:text-emerald-300 text-sm">
                                    <i class="fas fa-arrow-left mr-1"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div><!-- /table -->
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
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && window.innerWidth < 1024) toggleSidebar();
        });
    </script>
</body>

</html>
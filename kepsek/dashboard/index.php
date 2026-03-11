<?php
session_start();
require_once '../../config/database.php';

// Guard: hanya kepsek yang boleh akses
if (!isset($_SESSION['kepsek_id']) || $_SESSION['role'] !== 'kepsek') {
    header("Location: ../../kepsek/login.php");
    exit();
}

$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// ── Statistik absensi hari ini ──────────────────────────────────────────────
$stats = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'terlambat' => 0, 'alpha' => 0];

$stmt = $conn->prepare(
    "SELECT status, COUNT(*) as count FROM absensi
     WHERE tanggal = :today AND approval_status = 'Approved'
     GROUP BY status"
);
$stmt->execute(['today' => $today]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[strtolower($row['status'])] = $row['count'];
}

// ── Statistik kemarin (perbandingan) ───────────────────────────────────────
$yesterday_stats = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'terlambat' => 0, 'alpha' => 0];

$stmt = $conn->prepare(
    "SELECT status, COUNT(*) as count FROM absensi
     WHERE tanggal = :yesterday AND approval_status = 'Approved'
     GROUP BY status"
);
$stmt->execute(['yesterday' => $yesterday]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $yesterday_stats[strtolower($row['status'])] = $row['count'];
}

$pct = [];
foreach ($stats as $k => $v) {
    if ($yesterday_stats[$k] > 0) {
        $pct[$k] = round((($v - $yesterday_stats[$k]) / $yesterday_stats[$k]) * 100);
    } else {
        $pct[$k] = $v > 0 ? 100 : 0;
    }
}

// ── Total siswa ────────────────────────────────────────────────────────────
$total_students = $conn->query("SELECT COUNT(*) FROM siswa")->fetchColumn();

// ── Data absensi hari ini (detail) ─────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT a.*, s.nama_lengkap, s.kelas, s.nis, s.foto_profil
     FROM absensi a
     JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :today
     ORDER BY a.created_at DESC"
);
$stmt->execute(['today' => $today]);
$absensi_hari_ini = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Weekly stats ───────────────────────────────────────────────────────────
$stmt = $conn->query(
    "SELECT DATE(tanggal) as date,
            MIN(DATE_FORMAT(tanggal,'%d %b')) as date_label,
            status, COUNT(*) as count
     FROM absensi
     WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
       AND approval_status = 'Approved'
     GROUP BY DATE(tanggal), status
     ORDER BY date ASC"
);
$weeklyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Top pelanggaran ────────────────────────────────────────────────────────
$topPelanggar = $conn->query(
    "SELECT s.nama_lengkap, s.kelas, COUNT(p.id) as jumlah, SUM(p.poin) as total_poin
     FROM pelanggaran p
     JOIN siswa s ON p.siswa_id = s.id
     GROUP BY p.siswa_id
     ORDER BY total_poin DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kepala Sekolah – SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass {
            background: rgba(17, 24, 39, .72);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(16, 185, 129, .25);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(16, 185, 129, .18), rgba(16, 185, 129, .04));
            border-left: 4px solid #10b981;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #064e3b 100%);
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #fff;
            -webkit-box-shadow: 0 0 0 1000px #1f2937 inset;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, .4);
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, .45);
            border-radius: 3px;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .fade-up {
            animation: fadeUp .35s ease-out both;
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">

    <!-- Mobile overlay -->
    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ── SIDEBAR ───────────────────────────────────────────────────────────── -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass border-r border-emerald-900/30 z-50 transition-transform duration-300 -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-5 border-b border-emerald-900/30">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" class="h-10 w-auto" alt="Logo">
                <div>
                    <p class="font-semibold text-sm leading-tight">SMK NURUL ULUM</p>
                    <p class="text-xs text-emerald-400">Kepala Sekolah</p>
                </div>
            </div>
            <button class="lg:hidden text-gray-400 hover:text-white" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <nav class="p-4 space-y-1 overflow-y-auto custom-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="index.php" class="flex items-center gap-3 p-3 rounded-lg menu-active text-white">
                <i class="fas fa-home text-emerald-400"></i><span>Dashboard</span>
            </a>

            <!-- Monitoring Siswa (accordion) -->
            <div x-data="{open:true}" class="group">
                <button onclick="toggleMenu(this)"
                    class="flex items-center gap-3 w-full p-3 rounded-lg text-gray-300 hover:bg-emerald-500/10 transition-colors">
                    <i class="fas fa-calendar-check text-emerald-400"></i>
                    <span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform duration-200 rotate-icon"></i>
                </button>
                <ul class="ml-8 mt-1 sub-menu space-y-1">
                    <li><a href="presensi.php" class="block p-2 text-gray-400 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg text-sm">Presensi</a></li>
                    <li><a href="pelanggaran.php" class="block p-2 text-gray-400 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg text-sm">Pelanggaran</a></li>
                    <li><a href="konseling.php" class="block p-2 text-gray-400 hover:text-emerald-400 hover:bg-emerald-500/10 rounded-lg text-sm">Konseling</a></li>
                </ul>
            </div>

            <hr class="border-gray-700/40 my-3">

            <!-- Info Cepat -->
            <div class="px-3 py-2">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Info Cepat</p>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between"><span class="text-gray-400">Total Siswa</span><span class="font-semibold"><?= $total_students ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Hadir Hari Ini</span><span class="font-semibold text-emerald-400"><?= $stats['hadir'] ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Alpha Hari Ini</span><span class="font-semibold text-red-400"><?= $stats['alpha'] ?></span></div>
                </div>
            </div>

            <hr class="border-gray-700/40 my-3">

            <a href="../../kepsek/logout.php"
                class="flex items-center gap-3 p-3 rounded-lg text-gray-400 hover:bg-red-500/10 hover:text-red-400 transition-colors">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ── MAIN ──────────────────────────────────────────────────────────────── -->
    <main class="lg:ml-64 min-h-screen">

        <!-- Mobile topbar -->
        <div class="lg:hidden sticky top-0 z-30 glass px-4 py-3 flex items-center justify-between border-b border-emerald-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/50">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="text-sm font-medium">Dashboard Kepsek</span>
            </div>
            <img src="../../<?= $_SESSION['kepsek_photo'] ?: 'assets/default/photo-profile.png' ?>"
                class="h-8 w-8 rounded-full object-cover border border-emerald-500/50" alt="Foto">
        </div>

        <div class="p-5 md:p-8">
            <div class="max-w-7xl mx-auto">

                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-8 fade-up">
                    <div>
                        <h1 class="text-2xl font-bold">Selamat Datang, <?= htmlspecialchars($_SESSION['kepsek_name']) ?> 👋</h1>
                        <p class="text-gray-400 text-sm mt-1">
                            <i class="fas fa-calendar-alt text-emerald-400 mr-1"></i>
                            <?= date('l, d F Y') ?> &nbsp;|&nbsp;
                            <span id="clock" class="text-emerald-300 font-medium"></span>
                        </p>
                    </div>
                    <div class="hidden lg:flex items-center gap-3 px-4 py-2 glass rounded-xl mt-3 lg:mt-0">
                        <img src="../../<?= $_SESSION['kepsek_photo'] ?: 'assets/default/photo-profile.png' ?>"
                            class="h-9 w-9 rounded-full object-cover border border-emerald-500/50" alt="Foto">
                        <div class="text-sm">
                            <p class="font-medium"><?= htmlspecialchars($_SESSION['kepsek_name']) ?></p>
                            <p class="text-emerald-400 text-xs">Kepala Sekolah</p>
                        </div>
                    </div>
                </header>

                <!-- ── STAT CARDS ─────────────────────────────────────────────────── -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
                    <?php
                    $cards = [
                        ['label' => 'Hadir',     'key' => 'hadir',     'icon' => 'fa-check',      'color' => 'emerald'],
                        ['label' => 'Sakit',     'key' => 'sakit',     'icon' => 'fa-hospital',   'color' => 'yellow'],
                        ['label' => 'Izin',      'key' => 'izin',      'icon' => 'fa-clipboard',  'color' => 'blue'],
                        ['label' => 'Terlambat', 'key' => 'terlambat', 'icon' => 'fa-clock',      'color' => 'orange'],
                        ['label' => 'Alpha',     'key' => 'alpha',     'icon' => 'fa-user-times', 'color' => 'red'],
                    ];
                    foreach ($cards as $i => $c):
                        $change = $pct[$c['key']];
                    ?>
                        <div class="glass rounded-xl p-4 hover:scale-[1.02] transition-all duration-300 cursor-default fade-up"
                            style="animation-delay:<?= $i * 0.06 ?>s">
                            <div class="flex justify-between items-start mb-3">
                                <p class="text-gray-400 text-xs font-medium"><?= $c['label'] ?></p>
                                <div class="h-8 w-8 rounded-lg bg-<?= $c['color'] ?>-500/20 flex items-center justify-center">
                                    <i class="fas <?= $c['icon'] ?> text-<?= $c['color'] ?>-400 text-sm"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold"><?= $stats[$c['key']] ?></p>
                            <p class="text-xs mt-1 <?= $change > 0 ? 'text-emerald-400' : ($change < 0 ? 'text-red-400' : 'text-gray-500') ?>">
                                <?php if ($change > 0): ?><i class="fas fa-arrow-up mr-1"></i>+<?= $change ?>%
                                <?php elseif ($change < 0): ?><i class="fas fa-arrow-down mr-1"></i><?= $change ?>%
                                <?php else: ?><i class="fas fa-minus mr-1"></i>Sama kemarin
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── CHART + TOP PELANGGAR ──────────────────────────────────────── -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

                    <!-- Weekly Chart -->
                    <div class="glass rounded-xl p-5 lg:col-span-2 fade-up" style="animation-delay:.15s">
                        <h3 class="font-semibold mb-4 flex items-center gap-2">
                            <i class="fas fa-chart-line text-emerald-400"></i> Statistik Kehadiran Mingguan
                        </h3>
                        <div class="relative h-72">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>

                    <!-- Top Pelanggar -->
                    <div class="glass rounded-xl overflow-hidden fade-up" style="animation-delay:.2s">
                        <div class="bg-gradient-to-r from-red-900/30 to-orange-900/20 px-5 py-4 border-b border-gray-800/50">
                            <h3 class="font-semibold flex items-center gap-2 text-sm">
                                <i class="fas fa-exclamation-triangle text-red-400"></i> Top 5 Pelanggaran
                            </h3>
                        </div>
                        <div class="divide-y divide-gray-800/50">
                            <?php if (!empty($topPelanggar)): ?>
                                <?php foreach ($topPelanggar as $i => $s):
                                    $poin = $s['total_poin'];
                                    $cat  = $poin >= 75 ? ['Berat', 'red'] : ($poin >= 50 ? ['Sedang', 'orange'] : ['Ringan', 'yellow']);
                                ?>
                                    <div class="px-4 py-3 flex items-center gap-3 hover:bg-white/5 transition-colors">
                                        <span class="text-xs font-bold text-gray-500 w-4"><?= $i + 1 ?></span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium truncate"><?= htmlspecialchars($s['nama_lengkap']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($s['kelas'] ?? '-') ?></p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-sm font-bold text-yellow-400"><?= $s['total_poin'] ?> poin</p>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-<?= $cat[1] ?>-500/10 text-<?= $cat[1] ?>-400 border border-<?= $cat[1] ?>-500/20">
                                                <?= $cat[0] ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-8 text-center text-gray-500 text-sm">
                                    <i class="fas fa-check-circle text-2xl text-emerald-500/40 mb-2 block"></i>
                                    Belum ada data pelanggaran
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ── TABEL ABSENSI HARI INI ──────────────────────────────────────── -->
                <div class="glass rounded-xl overflow-hidden fade-up" style="animation-delay:.25s">
                    <div class="bg-gradient-to-r from-emerald-900/30 to-teal-900/20 px-5 py-4 border-b border-gray-800/50 flex flex-wrap justify-between items-center gap-3">
                        <h3 class="font-semibold flex items-center gap-2">
                            <i class="fas fa-clipboard-list text-emerald-400"></i>
                            Data Absensi Hari Ini
                            <span class="text-xs text-gray-400 font-normal">(<?= date('d F Y') ?>)</span>
                        </h3>
                        <!-- Filter -->
                        <div class="flex gap-2 flex-wrap">
                            <?php foreach (['Semua', 'Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'] as $f): ?>
                                <button onclick="filterTable('<?= $f ?>')"
                                    class="filter-btn text-xs px-3 py-1.5 rounded-full border transition-colors
                     <?= $f === 'Semua' ? 'bg-emerald-500/20 border-emerald-500/50 text-emerald-300' : 'border-gray-700 text-gray-400 hover:border-emerald-500/40 hover:text-white' ?>"
                                    data-filter="<?= $f ?>">
                                    <?= $f ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="absensiTable">
                            <thead>
                                <tr class="text-gray-500 text-xs uppercase border-b border-gray-800/50 bg-gray-900/30">
                                    <th class="px-4 py-3 text-left">Siswa</th>
                                    <th class="px-4 py-3 text-left">NIS</th>
                                    <th class="px-4 py-3 text-left">Kelas</th>
                                    <th class="px-4 py-3 text-left">Jam Masuk</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-center">Approval</th>
                                    <th class="px-4 py-3 text-left">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/40">
                                <?php if (!empty($absensi_hari_ini)): ?>
                                    <?php foreach ($absensi_hari_ini as $row):
                                        $statusColor = match (strtolower($row['status'])) {
                                            'hadir'     => 'emerald',
                                            'sakit'     => 'yellow',
                                            'izin'      => 'blue',
                                            'terlambat' => 'orange',
                                            default     => 'red'
                                        };
                                        $approvalColor = match (strtolower($row['approval_status'] ?? '')) {
                                            'approved' => 'emerald',
                                            'rejected' => 'red',
                                            default    => 'yellow'
                                        };
                                    ?>
                                        <tr class="hover:bg-white/5 transition-colors absensi-row" data-status="<?= htmlspecialchars($row['status']) ?>">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="../../<?= $row['foto_profil'] ?: 'assets/default/photo-profile.png' ?>"
                                                        class="h-8 w-8 rounded-full object-cover border border-gray-700" alt="">
                                                    <span class="font-medium"><?= htmlspecialchars($row['nama_lengkap']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-400"><?= htmlspecialchars($row['nis']) ?></td>
                                            <td class="px-4 py-3 text-gray-400"><?= htmlspecialchars($row['kelas']) ?></td>
                                            <td class="px-4 py-3 text-gray-300"><?= $row['jam_masuk'] ?? '-' ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-<?= $statusColor ?>-500/10 text-<?= $statusColor ?>-400 border border-<?= $statusColor ?>-500/20">
                                                    <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-1 rounded-full text-xs bg-<?= $approvalColor ?>-500/10 text-<?= $approvalColor ?>-400">
                                                    <?= htmlspecialchars($row['approval_status'] ?? 'Pending') ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-400 max-w-[160px] truncate">
                                                <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                            <i class="fas fa-inbox text-3xl mb-3 block opacity-40"></i>
                                            Belum ada data absensi hari ini
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /max-w -->
        </div><!-- /p-5 -->
    </main>

    <!-- ── SCRIPTS ───────────────────────────────────────────────────────────── -->
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
        // open by default
        document.querySelectorAll('.sub-menu').forEach(ul => ul.classList.remove('hidden'));

        // Filter table
        function filterTable(status) {
            document.querySelectorAll('.filter-btn').forEach(b => {
                const active = b.dataset.filter === status;
                b.classList.toggle('bg-emerald-500/20', active);
                b.classList.toggle('border-emerald-500/50', active);
                b.classList.toggle('text-emerald-300', active);
                b.classList.toggle('border-gray-700', !active);
                b.classList.toggle('text-gray-400', !active);
            });
            document.querySelectorAll('.absensi-row').forEach(row => {
                row.style.display = (status === 'Semua' || row.dataset.status === status) ? '' : 'none';
            });
        }

        // Weekly Chart
        (function() {
            const raw = <?= json_encode($weeklyStats) ?>;
            const dateMap = {};
            raw.forEach(r => {
                dateMap[r.date_label] = dateMap[r.date_label] || {};
            });
            const dates = Object.keys(dateMap).sort();
            const statuses = {
                'Hadir': '#10B981',
                'Sakit': '#EAB308',
                'Izin': '#3B82F6',
                'Terlambat': '#F97316',
                'Alpha': '#EF4444'
            };
            const datasets = Object.entries(statuses).map(([s, c]) => ({
                label: s,
                data: dates.map(d => {
                    const m = raw.find(r => r.date_label === d && r.status === s);
                    return m ? +m.count : 0;
                }),
                borderColor: c,
                backgroundColor: c + '22',
                tension: .4,
                fill: true,
                pointBackgroundColor: c,
                pointRadius: 4,
                pointHoverRadius: 6
            }));
            new Chart(document.getElementById('weeklyChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: dates.length ? dates : ['—'],
                    datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255,255,255,.08)'
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255,255,255,.08)'
                            },
                            ticks: {
                                color: '#9CA3AF',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#9CA3AF',
                                usePointStyle: true,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17,24,39,.92)',
                            titleColor: '#fff',
                            bodyColor: '#d1d5db',
                            borderColor: 'rgba(16,185,129,.3)',
                            borderWidth: 1,
                            padding: 12
                        }
                    }
                }
            });
        })();
    </script>
</body>

</html>
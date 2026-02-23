<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header("Location: konseling.php");
    exit();
}

// Ambil data konseling beserta info siswa
$stmt = $conn->prepare(
    "SELECT k.*, 
            s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil,
            a.username as nama_admin
     FROM konseling k
     JOIN siswa s ON k.siswa_id = s.id
     LEFT JOIN admin a ON k.created_by = a.id
     WHERE k.id = :id"
);
$stmt->execute(['id' => $id]);
$k = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$k) {
    header("Location: konseling.php");
    exit();
}

// Ambil riwayat konseling siswa yang sama (selain record ini)
$riwayat_stmt = $conn->prepare(
    "SELECT id, tanggal, jenis_konseling, masalah, status, konselor
     FROM konseling
     WHERE siswa_id = :siswa_id AND id != :id
     ORDER BY tanggal DESC
     LIMIT 5"
);
$riwayat_stmt->execute(['siswa_id' => $k['siswa_id'], 'id' => $id]);
$riwayat = $riwayat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total konseling siswa
$total_konseling = $conn->prepare("SELECT COUNT(*) FROM konseling WHERE siswa_id = :siswa_id");
$total_konseling->execute(['siswa_id' => $k['siswa_id']]);
$total_sesi = $total_konseling->fetchColumn();

// Helper warna status
function statusClass($s)
{
    return match ($s) {
        'Selesai' => 'status-selesai',
        'Ditunda' => 'status-ditunda',
        default => 'status-proses',
    };
}
function statusIcon($s)
{
    return match ($s) {
        'Selesai' => 'fa-check-circle',
        'Ditunda' => 'fa-pause-circle',
        default => 'fa-spinner',
    };
}
function jenisClass($j)
{
    return 'badge-' . strtolower($j);
}
function jenisIcon($j)
{
    return match ($j) {
        'Akademik' => 'fa-graduation-cap',
        'Pribadi' => 'fa-user',
        'Sosial' => 'fa-users',
        'Karir' => 'fa-briefcase',
        'Keluarga' => 'fa-home',
        default => 'fa-ellipsis-h',
    };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Konseling - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(147, 51, 234, 0.2), rgba(147, 51, 234, 0.05));
            border-left: 4px solid #9333ea;
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

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.35s ease-out forwards;
        }

        .animate-fade-in-d1 {
            animation: fadeIn 0.35s ease-out 0.05s both;
        }

        .animate-fade-in-d2 {
            animation: fadeIn 0.35s ease-out 0.10s both;
        }

        .animate-fade-in-d3 {
            animation: fadeIn 0.35s ease-out 0.15s both;
        }

        /* Status badges */
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

        /* Jenis badges */
        .badge-akademik {
            background: rgba(139, 92, 246, .15);
            color: #8B5CF6;
            border: 1px solid rgba(139, 92, 246, .3);
        }

        .badge-pribadi {
            background: rgba(236, 72, 153, .15);
            color: #EC4899;
            border: 1px solid rgba(236, 72, 153, .3);
        }

        .badge-sosial {
            background: rgba(16, 185, 129, .15);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .badge-karir {
            background: rgba(234, 179, 8, .15);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, .3);
        }

        .badge-keluarga {
            background: rgba(249, 115, 22, .15);
            color: #F97316;
            border: 1px solid rgba(249, 115, 22, .3);
        }

        .badge-lainnya {
            background: rgba(107, 114, 128, .15);
            color: #6B7280;
            border: 1px solid rgba(107, 114, 128, .3);
        }

        /* Section card */
        .section-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .section-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9CA3AF;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .section-value {
            color: #F3F4F6;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .empty-value {
            color: #6B7280;
            font-style: italic;
            font-size: 0.85rem;
        }

        /* Riwayat item */
        .riwayat-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            transition: border-color 0.2s;
        }

        .riwayat-item:hover {
            border-color: rgba(147, 51, 234, 0.3);
        }

        /* Print */
        @media print {

            aside,
            .no-print {
                display: none !important;
            }

            main {
                margin-left: 0 !important;
            }

            body {
                background: white !important;
                color: black !important;
            }

            .glass-effect {
                background: white !important;
                border: 1px solid #e5e7eb !important;
            }
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar"
        class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-purple-900/30 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
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
            <a href="../dashboard/"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <li class="relative group">
                <button class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active w-full">
                    <i class="fas fa-calendar-check text-purple-500"></i><span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 block">
                    <li><a href="../absensi/index.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a>
                    </li>
                    <li><a href="../absensi/pelanggaran.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a>
                    </li>
                    <li><a href="../absensi/konseling.php"
                            class="block p-2 text-purple-400 bg-purple-500/10 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../siswa/"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-users"></i><span>Data Siswa</span>
            </a>
            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-file-alt"></i><span>Laporan</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block">
                    <li><a href="../laporan/index.php"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Presensi</a>
                    </li>
                    <li><a href="../laporan/pelanggaran"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Pelanggaran</a>
                    </li>
                    <li><a href="../laporan/konseling"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a>
                    </li>
                </ul>
            </li>
            <a href="../profil/"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-user-cog"></i><span>Profil</span>
            </a>
            <a href="../logout.php"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 transition-all duration-300">
        <!-- Mobile Header -->
        <div
            class="lg:hidden no-print bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="Logo" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile"
                    class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-5xl mx-auto">

                <!-- Breadcrumb + actions -->
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6 no-print">
                    <div class="flex items-center gap-3">
                        <a href="konseling.php" class="p-2 rounded-full hover:bg-gray-800 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div>
                            <h1 class="text-xl md:text-2xl font-bold">Detail Konseling</h1>
                            <p class="text-gray-400 text-sm">ID #<?= $k['id'] ?> &middot;
                                <?= date('d F Y', strtotime($k['tanggal'])) ?></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="window.print()"
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center gap-2 text-sm transition-colors">
                            <i class="fas fa-print"></i> Cetak
                        </button>
                        <a href="editk.php?id=<?= $k['id'] ?>"
                            class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg flex items-center gap-2 text-sm transition-colors">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button onclick="confirmDelete(<?= $k['id'] ?>)"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg flex items-center gap-2 text-sm transition-colors">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- ══ KOLOM KIRI: Info Siswa ══ -->
                    <div class="lg:col-span-1 space-y-4">

                        <!-- Kartu Profil Siswa -->
                        <div class="glass-effect rounded-xl p-5 text-center animate-fade-in">
                            <div class="relative inline-block mb-3">
                                <img src="../../<?= $k['foto_profil'] ?: 'assets/default/avatar.png' ?>"
                                    alt="Foto Siswa"
                                    class="w-20 h-20 rounded-full object-cover border-2 border-purple-500/50 mx-auto">
                                <span
                                    class="absolute bottom-0 right-0 w-5 h-5 bg-purple-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-graduate text-xs"></i>
                                </span>
                            </div>
                            <h2 class="font-bold text-base"><?= htmlspecialchars($k['nama_lengkap']) ?></h2>
                            <p class="text-gray-400 text-sm"><?= $k['nis'] ?></p>
                            <p class="text-gray-500 text-xs mt-0.5"><?= $k['kelas'] ?> &mdash; <?= $k['jurusan'] ?></p>
                        </div>

                        <!-- Statistik Konseling Siswa -->
                        <div class="glass-effect rounded-xl p-4 animate-fade-in-d1">
                            <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                <i class="fas fa-chart-bar text-purple-400"></i> Statistik Konseling
                            </h3>
                            <div class="flex items-center justify-between p-3 section-card">
                                <span class="text-sm text-gray-400">Total Sesi</span>
                                <span class="text-lg font-bold text-purple-400"><?= $total_sesi ?></span>
                            </div>
                        </div>

                        <!-- Riwayat Konseling -->
                        <?php if (count($riwayat) > 0): ?>
                            <div class="glass-effect rounded-xl p-4 animate-fade-in-d2">
                                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                                    <i class="fas fa-history text-purple-400"></i> Riwayat Konseling
                                </h3>
                                <div class="space-y-2">
                                    <?php foreach ($riwayat as $r): ?>
                                        <a href="detailk.php?id=<?= $r['id'] ?>"
                                            class="riwayat-item rounded-lg p-2.5 flex items-start gap-2 block">
                                            <span
                                                class="text-xs font-semibold px-2 py-0.5 rounded flex-shrink-0 <?= jenisClass($r['jenis_konseling']) ?>">
                                                <?= $r['jenis_konseling'] ?>
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs text-white truncate"><?= htmlspecialchars($r['masalah']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($r['tanggal'])) ?>
                                                </p>
                                            </div>
                                            <span class="text-xs flex-shrink-0 <?= match ($r['status']) {
                                                'Selesai' => 'text-green-400',
                                                'Ditunda' => 'text-red-400',
                                                default => 'text-blue-400'
                                            } ?>">
                                                <?= $r['status'] ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- ══ KOLOM KANAN: Detail Konseling ══ -->
                    <div class="lg:col-span-2 space-y-4">

                        <!-- Header Status -->
                        <div class="glass-effect rounded-xl p-5 animate-fade-in">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-12 h-12 rounded-xl <?= jenisClass($k['jenis_konseling']) ?> flex items-center justify-center flex-shrink-0">
                                        <i class="fas <?= jenisIcon($k['jenis_konseling']) ?> text-lg"></i>
                                    </div>
                                    <div>
                                        <span
                                            class="text-xs font-bold px-2 py-0.5 rounded <?= jenisClass($k['jenis_konseling']) ?>">
                                            <?= htmlspecialchars($k['jenis_konseling']) ?>
                                        </span>
                                        <p class="text-lg font-bold mt-1">Konseling
                                            <?= htmlspecialchars($k['jenis_konseling']) ?></p>
                                        <p class="text-gray-400 text-sm"><?= date('d F Y', strtotime($k['tanggal'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span
                                        class="px-3 py-1.5 rounded-full text-sm font-semibold flex items-center gap-1.5 <?= statusClass($k['status']) ?>">
                                        <i class="fas <?= statusIcon($k['status']) ?> text-xs"></i>
                                        <?= $k['status'] ?>
                                    </span>
                                    <!-- Inline status update -->
                                    <div class="status-wrapper relative no-print">
                                        <select id="status-select"
                                            class="text-xs bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-gray-300 focus:outline-none focus:border-purple-500 cursor-pointer"
                                            onchange="updateStatus(this, <?= $k['id'] ?>)">
                                            <option value="Proses" <?= $k['status'] === 'Proses' ? 'selected' : '' ?>>⏳
                                                Ubah ke Proses</option>
                                            <option value="Selesai" <?= $k['status'] === 'Selesai' ? 'selected' : '' ?>>✅
                                                Ubah ke Selesai</option>
                                            <option value="Ditunda" <?= $k['status'] === 'Ditunda' ? 'selected' : '' ?>>⏸
                                                Ubah ke Ditunda</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Meta info -->
                            <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3 pt-4 border-t border-gray-800">
                                <div>
                                    <p class="section-label"><i class="fas fa-user-tie"></i> Konselor</p>
                                    <p class="section-value font-medium"><?= htmlspecialchars($k['konselor']) ?></p>
                                </div>
                                <div>
                                    <p class="section-label"><i class="fas fa-calendar-alt"></i> Tanggal</p>
                                    <p class="section-value"><?= date('d/m/Y', strtotime($k['tanggal'])) ?></p>
                                </div>
                                <div>
                                    <p class="section-label"><i class="fas fa-user-shield"></i> Dicatat Oleh</p>
                                    <p class="section-value"><?= htmlspecialchars($k['nama_admin'] ?? 'Admin') ?></p>
                                </div>
                                <div>
                                    <p class="section-label"><i class="fas fa-clock"></i> Dibuat</p>
                                    <p class="section-value"><?= date('d/m/Y H:i', strtotime($k['created_at'])) ?></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="section-label"><i class="fas fa-sync"></i> Terakhir Update</p>
                                    <p class="section-value"><?= date('d/m/Y H:i', strtotime($k['updated_at'])) ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Masalah -->
                        <div class="glass-effect rounded-xl p-5 animate-fade-in-d1">
                            <h3 class="font-semibold text-sm mb-3 flex items-center gap-2">
                                <span
                                    class="w-5 h-5 rounded bg-red-500/20 text-red-400 flex items-center justify-center text-xs">
                                    <i class="fas fa-exclamation"></i>
                                </span>
                                Uraian Masalah
                            </h3>
                            <div class="section-card">
                                <p class="section-value leading-relaxed whitespace-pre-line">
                                    <?= $k['masalah'] ? nl2br(htmlspecialchars($k['masalah'])) : '<span class="empty-value">Tidak ada uraian masalah</span>' ?>
                                </p>
                            </div>
                        </div>

                        <!-- Solusi -->
                        <div class="glass-effect rounded-xl p-5 animate-fade-in-d2">
                            <h3 class="font-semibold text-sm mb-3 flex items-center gap-2">
                                <span
                                    class="w-5 h-5 rounded bg-green-500/20 text-green-400 flex items-center justify-center text-xs">
                                    <i class="fas fa-lightbulb"></i>
                                </span>
                                Solusi / Rekomendasi
                            </h3>
                            <div class="section-card">
                                <?php if ($k['solusi']): ?>
                                    <p class="section-value leading-relaxed whitespace-pre-line">
                                        <?= nl2br(htmlspecialchars($k['solusi'])) ?></p>
                                <?php else: ?>
                                    <p class="empty-value">Belum ada solusi yang dicatat</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tindak Lanjut -->
                        <div class="glass-effect rounded-xl p-5 animate-fade-in-d3">
                            <h3 class="font-semibold text-sm mb-3 flex items-center gap-2">
                                <span
                                    class="w-5 h-5 rounded bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs">
                                    <i class="fas fa-forward"></i>
                                </span>
                                Tindak Lanjut
                            </h3>
                            <div class="section-card">
                                <?php if ($k['tindak_lanjut']): ?>
                                    <p class="section-value leading-relaxed whitespace-pre-line">
                                        <?= nl2br(htmlspecialchars($k['tindak_lanjut'])) ?></p>
                                <?php else: ?>
                                    <p class="empty-value">Belum ada tindak lanjut yang dicatat</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Navigasi bawah -->
                        <div class="flex flex-wrap justify-between gap-3 pt-2 no-print">
                            <a href="konseling.php"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center gap-2 text-sm transition-colors">
                                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                            </a>
                            <a href="editk.php?id=<?= $k['id'] ?>"
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg flex items-center gap-2 text-sm transition-colors">
                                <i class="fas fa-edit"></i> Edit Konseling
                            </a>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center z-50 hidden no-print">
        <div class="fixed inset-0 bg-black/60" onclick="hideDeleteModal()"></div>
        <div class="glass-effect rounded-xl p-6 w-11/12 max-w-md relative z-10">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center">
                    <i class="fas fa-trash text-red-400"></i>
                </div>
                <h3 class="text-lg font-semibold">Konfirmasi Hapus</h3>
            </div>
            <p class="text-gray-300 mb-2">Apakah Anda yakin ingin menghapus data konseling ini?</p>
            <p class="text-gray-500 text-sm mb-6">Tindakan ini tidak dapat dibatalkan dan data akan hilang permanen.</p>
            <div class="flex justify-end gap-3">
                <button onclick="hideDeleteModal()"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors">
                    Batal
                </button>
                <form method="POST" action="deletk.php">
                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                    <button type="submit"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm transition-colors">
                        <i class="fas fa-trash mr-1"></i> Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden no-print">
        <div id="toast-inner" class="px-5 py-3 rounded-lg text-sm font-medium shadow-lg flex items-center gap-2"></div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // ── Inline status update ──
        async function updateStatus(select, id) {
            const newStatus = select.value;
            try {
                const fd = new FormData();
                fd.append('update_status', '1');
                fd.append('id', id);
                fd.append('status', newStatus);

                const res = await fetch('konseling.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    showToast('✅ Status diubah ke ' + newStatus, 'success');
                    const badgeMap = {
                        Proses: { cls: 'status-proses', icon: 'fa-spinner', label: 'Proses' },
                        Selesai: { cls: 'status-selesai', icon: 'fa-check-circle', label: 'Selesai' },
                        Ditunda: { cls: 'status-ditunda', icon: 'fa-pause-circle', label: 'Ditunda' },
                    };
                    const badge = document.querySelector('.px-3.py-1\\.5.rounded-full.text-sm.font-semibold');
                    if (badge && badgeMap[newStatus]) {
                        badge.className = `px-3 py-1.5 rounded-full text-sm font-semibold flex items-center gap-1.5 ${badgeMap[newStatus].cls}`;
                        badge.innerHTML = `<i class="fas ${badgeMap[newStatus].icon} text-xs"></i> ${badgeMap[newStatus].label}`;
                    }
                } else {
                    showToast('❌ Gagal mengubah status', 'error');
                }
            } catch (e) {
                showToast('❌ Terjadi kesalahan jaringan', 'error');
            }
        }

        // ── Toast ──
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

        // ── Delete modal ──
        function confirmDelete(id) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // ── Sidebar ──
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

        window.addEventListener('resize', () => {
            document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
        });
        document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
    </script>
</body>

</html>
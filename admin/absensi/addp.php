<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$error = '';

// ── Daftar siswa ──
$siswa_list = $conn->query(
    "SELECT id, nama_lengkap, nis, kelas, jurusan FROM siswa ORDER BY kelas, nama_lengkap"
)->fetchAll(PDO::FETCH_ASSOC);

$kelas_list = array_unique(array_column($siswa_list, 'kelas'));
sort($kelas_list);

// ── Daftar master deskripsi pelanggaran ──
$deskripsi_list = $conn->query(
    "SELECT id, jenis, kode, nama, poin_default, tindakan 
     FROM deskripsi_pelanggaran 
     ORDER BY FIELD(jenis,'Ringan','Sedang','Berat'), kode"
)->fetchAll(PDO::FETCH_ASSOC);

$deskripsi_by_jenis = ['Ringan' => [], 'Sedang' => [], 'Berat' => []];
foreach ($deskripsi_list as $d) {
    $deskripsi_by_jenis[$d['jenis']][] = $d;
}


// ── POST: Simpan pelanggaran ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $siswa_id = (int) $_POST['siswa_id'];
        $deskripsi_id = (int) $_POST['deskripsi_id'];
        $tanggal = $_POST['tanggal'];
        $poin = (int) $_POST['poin'];
        $tindakan = $_POST['tindakan'] ?? null;
        $status = $_POST['status'];
        $dicatat_oleh = $_SESSION['admin_id'];

        // Validasi
        if (!$siswa_id)
            throw new Exception("Pilih siswa terlebih dahulu.");
        if (!$deskripsi_id)
            throw new Exception("Pilih deskripsi pelanggaran terlebih dahulu.");
        if ($poin < 1)
            throw new Exception("Poin harus minimal 1.");

        // Ambil detail dari master deskripsi_pelanggaran
        $d_stmt = $conn->prepare("SELECT * FROM deskripsi_pelanggaran WHERE id = :id");
        $d_stmt->execute(['id' => $deskripsi_id]);
        $desc = $d_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$desc)
            throw new Exception("Deskripsi pelanggaran tidak valid.");

        // Ambil nama siswa
        $s_stmt = $conn->prepare("SELECT nama_lengkap FROM siswa WHERE id = :id");
        $s_stmt->execute(['id' => $siswa_id]);
        $siswa_row = $s_stmt->fetch(PDO::FETCH_ASSOC);

        $conn->beginTransaction();

        // ── CEK duplikat:
        //    Ringan  → cukup cek siswa_id + jenis (semua Ringan satu baris)
        //    Berat/Sedang → cek siswa_id + jenis + deskripsi (per item)
        if ($desc['jenis'] === 'Ringan') {
            $cek_stmt = $conn->prepare(
                "SELECT id, poin, deskripsi FROM pelanggaran
                 WHERE siswa_id = :siswa_id
                   AND jenis_pelanggaran = :jenis
                 LIMIT 1"
            );
            $cek_stmt->execute([
                'siswa_id' => $siswa_id,
                'jenis'    => $desc['jenis'],
            ]);
        } else {
            $cek_stmt = $conn->prepare(
                "SELECT id, poin FROM pelanggaran
                 WHERE siswa_id = :siswa_id
                   AND jenis_pelanggaran = :jenis
                   AND deskripsi = :deskripsi
                 LIMIT 1"
            );
            $cek_stmt->execute([
                'siswa_id'  => $siswa_id,
                'jenis'     => $desc['jenis'],
                'deskripsi' => $desc['nama'],
            ]);
        }
        $existing = $cek_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // ✅ SUDAH ADA → UPDATE: tambahkan poin, max 100
            $poin_baru = min($existing['poin'] + $poin, 100);

            if ($desc['jenis'] === 'Ringan') {
                // Ringan: gabungkan deskripsi baru (jika belum ada)
                $deskripsi_lama = $existing['deskripsi'] ?? '';
                $deskripsi_baru = (strpos($deskripsi_lama, $desc['nama']) === false)
                    ? ($deskripsi_lama ? $deskripsi_lama . ', ' . $desc['nama'] : $desc['nama'])
                    : $deskripsi_lama;

                $conn->prepare(
                    "UPDATE pelanggaran
                     SET poin = :poin, deskripsi = :deskripsi, tanggal = :tanggal, tindakan = :tindakan, status = :status
                     WHERE id = :id"
                )->execute([
                    'poin'      => $poin_baru,
                    'deskripsi' => $deskripsi_baru,
                    'tanggal'   => $tanggal,
                    'tindakan'  => $tindakan ?: null,
                    'status'    => $status,
                    'id'        => $existing['id'],
                ]);
            } else {
                // Berat / Sedang: update poin saja
                $conn->prepare(
                    "UPDATE pelanggaran
                     SET poin = :poin, tanggal = :tanggal, tindakan = :tindakan, status = :status
                     WHERE id = :id"
                )->execute([
                    'poin'     => $poin_baru,
                    'tanggal'  => $tanggal,
                    'tindakan' => $tindakan ?: null,
                    'status'   => $status,
                    'id'       => $existing['id'],
                ]);
            }

            $log_desc = "Perbarui poin [{$desc['kode']}] {$desc['nama']} milik {$siswa_row['nama_lengkap']} (+{$poin} → total {$poin_baru} poin)";
        } else {
            // ✅ BELUM ADA → INSERT baris baru
            $total_stmt = $conn->prepare(
                "SELECT COALESCE(SUM(poin), 0) FROM pelanggaran WHERE siswa_id = :siswa_id"
            );
            $total_stmt->execute(['siswa_id' => $siswa_id]);
            $total_skrg = (int) $total_stmt->fetchColumn();
            $poin_final = min($poin, max(0, 100 - $total_skrg));

            $conn->prepare(
                "INSERT INTO pelanggaran
                    (siswa_id, tanggal, jenis_pelanggaran, deskripsi, poin, tindakan, status, dicatat_oleh)
                 VALUES
                    (:siswa_id, :tanggal, :jenis, :deskripsi, :poin, :tindakan, :status, :dicatat_oleh)"
            )->execute([
                'siswa_id'      => $siswa_id,
                'tanggal'       => $tanggal,
                'jenis'         => $desc['jenis'],
                'deskripsi'     => $desc['nama'],
                'poin'          => $poin_final,
                'tindakan'      => $tindakan ?: null,
                'status'        => $status,
                'dicatat_oleh'  => $dicatat_oleh,
            ]);

            $log_desc = "Tambah [{$desc['kode']}] {$desc['nama']} ({$poin_final} poin) untuk {$siswa_row['nama_lengkap']}";
        }

        // Log activity
        $conn->prepare(
            "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
             VALUES ('admin', :admin_id, 'create', :description)"
        )->execute(['admin_id' => $_SESSION['admin_id'], 'description' => $log_desc]);

        $conn->commit();
        header("Location: pelanggaran.php?created=true");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction())
            $conn->rollBack();
        $error = $e->getMessage();
    }
}

$default_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pelanggaran - SMK NURUL ULUM</title>
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
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }

        @media (max-width:640px) {
            .touch-target {
                min-height: 44px
            }

            select,
            input[type="date"] {
                font-size: 16px
            }
        }

        .badge-ringan {
            background: rgba(234, 179, 8, 0.15);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .badge-sedang {
            background: rgba(249, 115, 22, 0.15);
            color: #F97316;
            border: 1px solid rgba(249, 115, 22, 0.3);
        }

        .badge-berat {
            background: rgba(239, 68, 68, 0.15);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .desc-card {
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .desc-card:hover {
            border-color: rgba(147, 51, 234, 0.5);
            background: rgba(147, 51, 234, 0.08);
        }

        .desc-card.selected {
            border-color: #9333ea;
            background: rgba(147, 51, 234, 0.15);
        }

        .desc-card.already-exist {
            border-color: rgba(234, 179, 8, 0.4);
        }

        .tab-btn {
            transition: all 0.2s;
        }

        .tab-btn.active {
            background: rgba(147, 51, 234, 0.2);
            border-color: #9333ea;
            color: white;
        }

        .tindakan-autofilled {
            animation: highlightFill 0.4s ease-out;
        }

        @keyframes highlightFill {
            0% {
                border-color: #9333ea;
                box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.3);
            }

            100% {
                border-color: rgb(55 65 81);
                box-shadow: none;
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
                            class="block p-2 text-purple-400 bg-purple-500/10 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../absensi/konseling"
                            class="block p-2 text-gray-400 hover:text-purple-400 hover:bg-purple-500/10 rounded-lg">Konseling</a>
                    </li>
                </ul>
            </li>
            <a href="../siswa/"
                class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-users"></i><span>Data Siswa</span>
            </a>
            <li class="relative group">
                <button
                    class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors w-full">
                    <i class="fas fa-file-alt"></i><span>Laporan</span><i
                        class="fas fa-chevron-down ml-auto text-sm"></i>
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
            class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="SMKN 40" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile"
                    class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-4xl mx-auto">

                <div class="flex items-center mb-6">
                    <a href="index.php" class="mr-4 p-2 rounded-full hover:bg-gray-800 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Tambah Pelanggaran</h1>
                        <p class="text-gray-400 text-sm">Pilih siswa lalu klik kartu deskripsi pelanggaran</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div
                        class="bg-red-500/10 border border-red-500/30 text-red-500 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3 flex-shrink-0"></i>
                        <div>
                            <p class="font-medium">Gagal menyimpan</p>
                            <p class="text-sm mt-1"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="mainForm">
                    <input type="hidden" name="deskripsi_id" id="input_deskripsi_id">
                    <input type="hidden" name="poin" id="input_poin">

                    <!-- STEP 1: Pilih Siswa -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 mb-4 animate-fade-in">
                        <h2 class="font-semibold text-base mb-4 flex items-center gap-2">
                            <span
                                class="w-6 h-6 rounded-full bg-purple-600 text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>
                            Pilih Siswa
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1.5">Filter Kelas</label>
                                <select id="kelas_filter" onchange="filterSiswaByKelas()"
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 touch-target">
                                    <option value="">-- Semua Kelas --</option>
                                    <?php foreach ($kelas_list as $k): ?>
                                        <option value="<?= $k ?>"><?= $k ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1.5">Nama Siswa <span
                                        class="text-red-400">*</span></label>
                                <select id="siswa_id" name="siswa_id" required onchange="onSiswaChange()"
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 touch-target">
                                    <option value="">-- Pilih Siswa --</option>
                                    <?php foreach ($siswa_list as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-kelas="<?= $s['kelas'] ?>"
                                            data-nis="<?= htmlspecialchars($s['nis']) ?>"
                                            data-jurusan="<?= $s['jurusan'] ?>">
                                            <?= htmlspecialchars($s['nama_lengkap']) ?> - <?= $s['nis'] ?>
                                            (<?= $s['kelas'] ?> <?= $s['jurusan'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Panel pelanggaran existing -->
                        <div id="existing-info"
                            class="hidden mt-4 p-3 rounded-lg bg-gray-800/60 border border-gray-700">
                            <p class="text-xs text-gray-400 mb-2 font-medium">
                                <i class="fas fa-history mr-1 text-purple-400"></i>Pelanggaran tercatat:
                            </p>
                            <div id="existing-detail" class="flex flex-wrap gap-2 mb-2"></div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500">Total:</span>
                                <div class="flex-1 bg-gray-700 rounded-full h-1.5">
                                    <div id="total-bar"
                                        class="h-1.5 rounded-full bg-green-500 transition-all duration-500"
                                        style="width:0%"></div>
                                </div>
                                <span id="total-label" class="text-xs font-bold text-green-400">0/100</span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: Pilih Deskripsi -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 mb-4 animate-fade-in">
                        <h2 class="font-semibold text-base mb-4 flex items-center gap-2">
                            <span
                                class="w-6 h-6 rounded-full bg-purple-600 text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>
                            Pilih Deskripsi Pelanggaran
                        </h2>

                        <!-- Tab -->
                        <div class="flex gap-2 mb-4">
                            <?php foreach (['Ringan', 'Sedang', 'Berat'] as $j): ?>
                                <button type="button" onclick="switchTab('<?= $j ?>')" id="tab-<?= $j ?>"
                                    class="tab-btn px-4 py-1.5 rounded-full border border-gray-600 text-sm text-gray-400">
                                    <?= $j ?>
                                    <span class="ml-1 text-xs opacity-60">(<?= count($deskripsi_by_jenis[$j]) ?>)</span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Kartu deskripsi per jenis -->
                        <?php foreach (['Ringan', 'Sedang', 'Berat'] as $jenis): ?>
                            <div id="panel-<?= $jenis ?>" class="hidden">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?php foreach ($deskripsi_by_jenis[$jenis] as $d): ?>
                                        <div class="desc-card glass-effect rounded-lg p-3" data-id="<?= $d['id'] ?>"
                                            data-kode="<?= $d['kode'] ?>" data-jenis="<?= $d['jenis'] ?>"
                                            data-nama="<?= htmlspecialchars($d['nama']) ?>"
                                            data-poin="<?= $d['poin_default'] ?>"
                                            data-tindakan="<?= htmlspecialchars($d['tindakan'] ?? '') ?>"
                                            onclick="selectDeskripsi(this)">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <span
                                                    class="text-xs font-bold px-2 py-0.5 rounded badge-<?= strtolower($jenis) ?>"><?= $d['kode'] ?></span>
                                                <span class="text-xs font-semibold text-gray-300"><?= $d['poin_default'] ?>
                                                    poin</span>
                                            </div>
                                            <p class="text-sm font-medium text-white"><?= htmlspecialchars($d['nama']) ?></p>
                                            <?php if ($d['tindakan']): ?>
                                                <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                                                    <?= htmlspecialchars($d['tindakan']) ?></p>
                                            <?php endif; ?>
                                            <div
                                                class="exist-badge hidden mt-2 flex items-center gap-1 text-xs text-yellow-400">
                                                <i class="fas fa-plus-circle"></i>
                                                <span>Poin akan ditambahkan</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div id="panel-empty" class="text-center py-8 text-gray-600">
                            <i class="fas fa-hand-pointer text-3xl mb-2"></i>
                            <p class="text-sm">Klik tab Ringan / Sedang / Berat di atas</p>
                        </div>

                        <!-- Preview terpilih -->
                        <div id="selected-preview"
                            class="hidden mt-4 p-3 rounded-lg bg-purple-900/20 border border-purple-500/40">
                            <p class="text-xs text-purple-400 mb-1.5 font-medium">Dipilih:</p>
                            <div class="flex items-center gap-3 flex-wrap">
                                <span id="prev-kode" class="text-xs font-bold px-2 py-0.5 rounded"></span>
                                <span id="prev-nama" class="text-sm font-medium text-white flex-1"></span>
                                <span id="prev-poin" class="text-sm font-bold text-purple-300"></span>
                            </div>
                            <p id="prev-notif" class="hidden mt-2 text-xs text-yellow-400">
                                <i class="fas fa-info-circle mr-1"></i>
                                Siswa sudah punya pelanggaran ini — poin akan dijumlahkan.
                            </p>
                        </div>
                    </div>

                    <!-- STEP 3: Detail -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 mb-4 animate-fade-in">
                        <h2 class="font-semibold text-base mb-4 flex items-center gap-2">
                            <span
                                class="w-6 h-6 rounded-full bg-purple-600 text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>
                            Detail & Tindakan
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1.5">Tanggal <span
                                        class="text-red-400">*</span></label>
                                <input type="date" name="tanggal" value="<?= $default_date ?>" required
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1.5">Poin Ditambahkan <span
                                        class="text-red-400">*</span></label>
                                <input type="number" id="poin_display" min="1" max="100"
                                    placeholder="Pilih deskripsi dulu"
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500">
                                <p class="text-xs text-gray-500 mt-1">Bisa diubah dari nilai default</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1.5">Status</label>
                                <select name="status"
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500">
                                    <option value="Pending">Pending</option>
                                    <option value="Proses">Proses</option>
                                    <option value="Selesai">Selesai</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1.5">
                                Tindakan / Sanksi
                                <span class="text-gray-500 font-normal">(Opsional)</span>
                                <span id="tindakan-auto-badge" class="hidden ml-2 text-xs text-purple-400 font-normal">
                                    <i class="fas fa-magic mr-1"></i>Terisi otomatis dari database
                                </span>
                            </label>
                            <textarea id="tindakan_textarea" name="tindakan" rows="2"
                                placeholder="Tindakan akan terisi otomatis saat memilih jenis pelanggaran..."
                                class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 resize-none transition-all duration-300"></textarea>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col sm:flex-row justify-end gap-3">
                        <a href="pelanggaran.php"
                            class="w-full sm:w-auto px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors font-medium text-center">
                            <i class="fas fa-times mr-2"></i>Batal
                        </a>
                        <button type="submit" id="btn-submit" disabled
                            class="w-full sm:w-auto px-6 py-2.5 bg-purple-600 hover:bg-purple-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium">
                            <i class="fas fa-save mr-2"></i>Simpan Pelanggaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // Key duplikat: "jenis|nama" karena tidak ada kolom deskripsi_id di tabel pelanggaran
        let existingByKey = {};
        let selectedDescId = null;

        // ── Tab ──
        function switchTab(jenis) {
            ['Ringan', 'Sedang', 'Berat'].forEach(j => {
                document.getElementById('panel-' + j).classList.add('hidden');
                document.getElementById('tab-' + j).classList.remove('active');
            });
            document.getElementById('panel-empty').classList.add('hidden');
            document.getElementById('panel-' + jenis).classList.remove('hidden');
            document.getElementById('tab-' + jenis).classList.add('active');
        }

        // ── Pilih kartu deskripsi ──
        function selectDeskripsi(card) {
            document.querySelectorAll('.desc-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');

            const id = card.dataset.id;
            const jenis = card.dataset.jenis;
            const nama = card.dataset.nama;
            selectedDescId = id;
            document.getElementById('input_deskripsi_id').value = id;

            // Poin default
            const poin = card.dataset.poin;
            document.getElementById('poin_display').value = poin;
            document.getElementById('input_poin').value = poin;

            // Preview
            const badgeMap = {
                Ringan: 'badge-ringan',
                Sedang: 'badge-sedang',
                Berat: 'badge-berat'
            };
            document.getElementById('prev-kode').className = 'text-xs font-bold px-2 py-0.5 rounded ' + badgeMap[jenis];
            document.getElementById('prev-kode').textContent = card.dataset.kode;
            document.getElementById('prev-nama').textContent = nama;
            document.getElementById('prev-poin').textContent = poin + ' poin';
            document.getElementById('selected-preview').classList.remove('hidden');

            // Cek duplikat berdasarkan jenis|nama
            const key = jenis + '|' + nama;
            document.getElementById('prev-notif').classList.toggle('hidden', existingByKey[key] === undefined);

            // ── AUTO-FILL TINDAKAN ──
            const tindakanVal = card.dataset.tindakan || '';
            const tindakanTextarea = document.getElementById('tindakan_textarea');
            const tindakanBadge = document.getElementById('tindakan-auto-badge');

            tindakanTextarea.value = tindakanVal;
            tindakanTextarea.classList.remove('tindakan-autofilled');
            void tindakanTextarea.offsetWidth;
            tindakanTextarea.classList.add('tindakan-autofilled');
            tindakanVal ? tindakanBadge.classList.remove('hidden') : tindakanBadge.classList.add('hidden');

            document.getElementById('btn-submit').disabled = false;
        }

        // ── Filter kelas ──
        function filterSiswaByKelas() {
            const kelas = document.getElementById('kelas_filter').value;
            const dropdown = document.getElementById('siswa_id');
            dropdown.value = '';
            document.getElementById('existing-info').classList.add('hidden');
            existingByKey = {};
            refreshExistBadges();
            Array.from(dropdown.options).forEach(opt => {
                if (!opt.value) {
                    opt.style.display = '';
                    return;
                }
                opt.style.display = (!kelas || opt.dataset.kelas === kelas) ? '' : 'none';
            });
        }

        // ── Siswa dipilih ──
        function onSiswaChange() {
            fetchPelanggaranSiswa();
        }

        // ── Fetch pelanggaran siswa ──
        async function fetchPelanggaranSiswa() {
            const siswaId = document.getElementById('siswa_id').value;
            const infoBox = document.getElementById('existing-info');
            existingByKey = {};

            if (!siswaId) {
                infoBox.classList.add('hidden');
                refreshExistBadges();
                return;
            }

            try {
                const res = await fetch(`get_pelanggaran_siswa.php?siswa_id=${siswaId}&mode=by_desc`);
                const data = await res.json();

                // Key: jenis|deskripsi (sesuai kolom yang ada di tabel pelanggaran)
                data.forEach(d => {
                    const key = d.jenis + '|' + (d.deskripsi || d.nama || '');
                    existingByKey[key] = d;
                });

                const badge = {
                    Ringan: 'badge-ringan',
                    Sedang: 'badge-sedang',
                    Berat: 'badge-berat'
                };
                document.getElementById('existing-detail').innerHTML = data.length === 0 ?
                    '<span class="text-gray-500 text-xs italic">Belum ada pelanggaran</span>' :
                    data.map(d => `
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs ${badge[d.jenis]} border">
                            <b>${d.jenis}</b> · ${d.poin} poin
                        </span>`).join('');

                const total = Math.min(data.reduce((s, d) => s + parseInt(d.poin), 0), 100);
                const bar = document.getElementById('total-bar');
                const label = document.getElementById('total-label');
                bar.style.width = total + '%';
                bar.className = 'h-1.5 rounded-full transition-all duration-500 ' +
                    (total >= 75 ? 'bg-red-500' : total >= 50 ? 'bg-orange-500' : total >= 25 ? 'bg-yellow-500' : 'bg-green-500');
                label.textContent = total + '/100';
                label.className = 'text-xs font-bold ' +
                    (total >= 75 ? 'text-red-400' : total >= 50 ? 'text-orange-400' : total >= 25 ? 'text-yellow-400' : 'text-green-400');

                infoBox.classList.remove('hidden');
            } catch (e) {
                infoBox.classList.add('hidden');
            }

            refreshExistBadges();

            // Perbarui notif preview jika sudah ada yang dipilih
            if (selectedDescId) {
                const selCard = document.querySelector(`.desc-card[data-id="${selectedDescId}"]`);
                if (selCard) {
                    const key = selCard.dataset.jenis + '|' + selCard.dataset.nama;
                    document.getElementById('prev-notif').classList.toggle('hidden', existingByKey[key] === undefined);
                }
            }
        }

        // ── Badge "Poin akan ditambahkan" ──
        function refreshExistBadges() {
            document.querySelectorAll('.desc-card').forEach(card => {
                const key = card.dataset.jenis + '|' + card.dataset.nama;
                const exists = existingByKey[key] !== undefined;
                card.querySelector('.exist-badge').classList.toggle('hidden', !exists);
                card.classList.toggle('already-exist', exists);
            });
        }

        // ── Sync poin_display → input_poin ──
        document.getElementById('poin_display').addEventListener('input', function() {
            document.getElementById('input_poin').value = this.value;
        });

        // ── Sembunyikan badge jika user edit manual ──
        document.getElementById('tindakan_textarea').addEventListener('input', function() {
            document.getElementById('tindakan-auto-badge').classList.add('hidden');
        });

        // ── Validasi sebelum submit ──
        document.getElementById('mainForm').addEventListener('submit', function(e) {
            if (!document.getElementById('input_deskripsi_id').value) {
                e.preventDefault();
                alert('Pilih deskripsi pelanggaran terlebih dahulu!');
                return;
            }
            const p = document.getElementById('poin_display').value;
            if (!p || p < 1) {
                e.preventDefault();
                alert('Poin harus minimal 1!');
                return;
            }
            document.getElementById('input_poin').value = p;
        });

        // ── Sidebar ──
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const isHidden = sidebar.classList.contains('-translate-x-full');
            sidebar.classList.toggle('-translate-x-full', !isHidden);
            overlay.classList.toggle('hidden', !isHidden);
            document.body.classList.toggle('overflow-hidden', isHidden);
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
            if (e.key === 'Escape' && window.innerWidth < 1024) {
                const s = document.getElementById('sidebar');
                if (!s.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });
        window.addEventListener('resize', () => {
            document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
        });
        document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
    </script>
</body>

</html>
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

$jenis_options = ['Akademik', 'Pribadi', 'Sosial', 'Karir', 'Keluarga', 'Lainnya'];

// ── POST: Simpan konseling ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $siswa_id      = (int) $_POST['siswa_id'];
        $tanggal       = $_POST['tanggal'];
        $jenis_konseling = trim($_POST['jenis_konseling']);
        $masalah       = trim($_POST['masalah']);
        $solusi        = trim($_POST['solusi'] ?? '');
        $tindak_lanjut = trim($_POST['tindak_lanjut'] ?? '');
        $konselor      = trim($_POST['konselor']);
        $status        = $_POST['status'];
        $created_by    = $_SESSION['admin_id'];

        // Validasi
        if (!$siswa_id)
            throw new Exception("Pilih siswa terlebih dahulu.");
        if (!$jenis_konseling)
            throw new Exception("Pilih jenis konseling.");
        if (!$masalah)
            throw new Exception("Uraian masalah tidak boleh kosong.");
        if (!$konselor)
            throw new Exception("Nama konselor tidak boleh kosong.");

        // Ambil nama siswa untuk log
        $s_stmt = $conn->prepare("SELECT nama_lengkap FROM siswa WHERE id = :id");
        $s_stmt->execute(['id' => $siswa_id]);
        $siswa_row = $s_stmt->fetch(PDO::FETCH_ASSOC);

        $conn->beginTransaction();

        $conn->prepare(
            "INSERT INTO konseling
                (siswa_id, tanggal, jenis_konseling, masalah, solusi, tindak_lanjut, konselor, status, created_by, created_at, updated_at)
             VALUES
                (:siswa_id, :tanggal, :jenis_konseling, :masalah, :solusi, :tindak_lanjut, :konselor, :status, :created_by, NOW(), NOW())"
        )->execute([
            'siswa_id'       => $siswa_id,
            'tanggal'        => $tanggal,
            'jenis_konseling' => $jenis_konseling,
            'masalah'        => $masalah,
            'solusi'         => $solusi ?: null,
            'tindak_lanjut'  => $tindak_lanjut ?: null,
            'konselor'       => $konselor,
            'status'         => $status,
            'created_by'     => $created_by,
        ]);

        // Log activity
        $log_desc = "Tambah konseling [{$jenis_konseling}] untuk {$siswa_row['nama_lengkap']} oleh {$konselor}";
        $conn->prepare(
            "INSERT INTO activity_log (user_type, user_id, activity_type, description)
             VALUES ('admin', :admin_id, 'create', :description)"
        )->execute(['admin_id' => $_SESSION['admin_id'], 'description' => $log_desc]);

        $conn->commit();
        header("Location: konseling.php?created=true");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = $e->getMessage();
    }
}

$default_date = date('Y-m-d');

// Ambil nama admin sebagai default konselor dari tabel users
$admin_stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'admin'");
$admin_stmt->execute(['id' => $_SESSION['admin_id']]);
$admin_row = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$default_konselor = $admin_row['nama_lengkap']
    ?? $admin_row['username']
    ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Konseling - SMK NURUL ULUM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ede9fe 100%);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(139, 92, 246, 0.15), rgba(139, 92, 246, 0.05));
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
            input[type="date"],
            textarea {
                font-size: 16px
            }
        }

        /* Jenis card selection */
        .jenis-card {
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .jenis-card:hover {
            border-color: rgba(147, 51, 234, 0.5);
            background: rgba(147, 51, 234, 0.08);
        }

        .jenis-card.selected {
            border-color: #9333ea;
            background: rgba(147, 51, 234, 0.15);
        }

        .jenis-akademik {
            --jc: #8B5CF6;
        }

        .jenis-pribadi {
            --jc: #EC4899;
        }

        .jenis-sosial {
            --jc: #10B981;
        }

        .jenis-karir {
            --jc: #EAB308;
        }

        .jenis-keluarga {
            --jc: #F97316;
        }

        .jenis-lainnya {
            --jc: #6B7280;
        }

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

        /* Riwayat konseling siswa */
        .riwayat-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }

        /* Highlight textarea on fill */
        @keyframes highlightFill {
            0% {
                border-color: #9333ea;
                box-shadow: 0 0 0 3px rgba(147, 51, 234, .3);
            }

            100% {
                border-color: rgb(55 65 81);
                box-shadow: none;
            }
        }

        .autofilled {
            animation: highlightFill .4s ease-out;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800 bg-fixed">
    <div id="mobile-overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-violet-200 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-violet-200">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 lg:h-10 w-auto">
                <div>
                    <h1 class="font-semibold text-sm lg:text-base text-gray-800">SMK NURUL ULUM</h1>
                    <p class="text-xs text-gray-500">Sistem Absensi</p>
                </div>
            </div>
            <button class="text-gray-600 hover:text-gray-800 lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 76px)">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-home"></i><span>Dashboard</span>
            </a>
            <li class="relative group">
                <button class="flex items-center gap-3 text-gray-700 p-3 rounded-lg menu-active w-full">
                    <i class="fas fa-calendar-check text-violet-600"></i><span>Monitoring Siswa</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 block">
                    <li><a href="../absensi/index.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Presensi</a></li>
                    <li><a href="../absensi/pelanggaran.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../absensi/konseling.php" class="block p-2 text-violet-500 bg-purple-500/10 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../siswa/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-users"></i><span>Data Siswa</span>
            </a>
            <li class="relative group">
                <button class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors w-full">
                    <i class="fas fa-file-alt"></i><span>Laporan</span>
                    <i class="fas fa-chevron-down ml-auto text-sm"></i>
                </button>
                <ul class="ml-8 mt-2 hidden group-hover:block">
                    <li><a href="../laporan/index.php" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Presensi</a></li>
                    <li><a href="../laporan/pelanggaran" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Pelanggaran</a></li>
                    <li><a href="../laporan/konseling" class="block p-2 text-gray-600 hover:text-violet-600 hover:bg-violet-100 rounded-lg">Konseling</a></li>
                </ul>
            </li>
            <a href="../profil/" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-user-cog"></i><span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-sky-50 to-indigo-50 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-white/90 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-800 p-2 -ml-2 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="Logo" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png'; ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-violet-300">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-4xl mx-auto">

                <!-- Page header -->
                <div class="flex items-center mb-6">
                    <a href="konseling.php" class="mr-4 p-2 rounded-full hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Tambah Konseling</h1>
                        <p class="text-gray-500 text-sm">Pilih siswa dan isi detail sesi konseling</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-200 text-red-500 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3 flex-shrink-0"></i>
                        <div>
                            <p class="font-medium">Gagal menyimpan</p>
                            <p class="text-sm mt-1"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="mainForm">
                    <input type="hidden" name="jenis_konseling" id="input_jenis_konseling">

                    <!-- STEP 1: Pilih Siswa -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 mb-4 animate-fade-in">
                        <h2 class="font-semibold text-base mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-purple-600 text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>
                            Pilih Siswa
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-500 mb-1.5">Filter Kelas</label>
                                <select id="kelas_filter" onchange="filterSiswaByKelas()"
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                                    <option value="">-- Semua Kelas --</option>
                                    <?php foreach ($kelas_list as $k): ?>
                                        <option value="<?= $k ?>"><?= $k ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-500 mb-1.5">Nama Siswa <span class="text-red-400">*</span></label>
                                <select id="siswa_id" name="siswa_id" required onchange="onSiswaChange()"
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                                    <option value="">-- Pilih Siswa --</option>
                                    <?php foreach ($siswa_list as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-kelas="<?= $s['kelas'] ?>" data-nis="<?= htmlspecialchars($s['nis']) ?>" data-jurusan="<?= $s['jurusan'] ?>">
                                            <?= htmlspecialchars($s['nama_lengkap']) ?> - <?= $s['nis'] ?> (<?= $s['kelas'] ?> <?= $s['jurusan'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Riwayat konseling siswa -->
                        <div id="riwayat-box" class="hidden mt-4 p-3 rounded-lg bg-gray-50/60 border border-gray-300">
                            <p class="text-xs text-gray-500 mb-2 font-medium">
                                <i class="fas fa-history mr-1 text-violet-500"></i>Riwayat konseling siswa ini:
                            </p>
                            <div id="riwayat-detail" class="space-y-2"></div>
                        </div>
                    </div>

                    <!-- STEP 2: Jenis Konseling -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 mb-4 animate-fade-in">
                        <h2 class="font-semibold text-base mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-purple-600 text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>
                            Pilih Jenis Konseling
                        </h2>

                        <?php
                        $jenis_icons = [
                            'Akademik'  => ['icon' => 'fa-graduation-cap', 'class' => 'akademik'],
                            'Pribadi'   => ['icon' => 'fa-user',           'class' => 'pribadi'],
                            'Sosial'    => ['icon' => 'fa-users',          'class' => 'sosial'],
                            'Karir'     => ['icon' => 'fa-briefcase',      'class' => 'karir'],
                            'Keluarga'  => ['icon' => 'fa-home',           'class' => 'keluarga'],
                            'Lainnya'   => ['icon' => 'fa-ellipsis-h',     'class' => 'lainnya'],
                        ];
                        $jenis_desc = [
                            'Akademik'  => 'Masalah pelajaran, nilai, belajar',
                            'Pribadi'   => 'Masalah diri, emosi, kepribadian',
                            'Sosial'    => 'Pergaulan, pertemanan, bullying',
                            'Karir'     => 'Minat, bakat, pilihan masa depan',
                            'Keluarga'  => 'Hubungan keluarga, orang tua',
                            'Lainnya'   => 'Topik lain di luar kategori di atas',
                        ];
                        ?>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <?php foreach ($jenis_icons as $jenis => $meta): ?>
                                <div class="jenis-card glass-effect rounded-lg p-3 jenis-<?= $meta['class'] ?>"
                                    data-jenis="<?= $jenis ?>"
                                    onclick="selectJenis(this)">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="fas <?= $meta['icon'] ?> text-sm badge-<?= $meta['class'] ?> px-1.5 py-0.5 rounded text-current"></i>
                                        <span class="font-semibold text-sm"><?= $jenis ?></span>
                                    </div>
                                    <p class="text-xs text-gray-500 leading-relaxed"><?= $jenis_desc[$jenis] ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Preview jenis terpilih -->
                        <div id="jenis-preview" class="hidden mt-4 p-3 rounded-lg bg-purple-900/20 border border-purple-500/40">
                            <p class="text-xs text-violet-500 mb-1 font-medium">Jenis dipilih:</p>
                            <span id="prev-jenis-badge" class="text-xs font-bold px-2 py-0.5 rounded"></span>
                        </div>
                    </div>

                    <!-- STEP 3: Detail Konseling -->
                    <div class="glass-effect rounded-xl p-4 md:p-6 mb-4 animate-fade-in">
                        <h2 class="font-semibold text-base mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-purple-600 text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>
                            Detail Konseling
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <!-- Tanggal -->
                            <div>
                                <label class="block text-sm text-gray-500 mb-1.5">Tanggal <span class="text-red-400">*</span></label>
                                <input type="date" name="tanggal" value="<?= $default_date ?>" required
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                            </div>
                            <!-- Konselor -->
                            <div>
                                <label class="block text-sm text-gray-500 mb-1.5">Nama Konselor <span class="text-red-400">*</span></label>
                                <input type="text" name="konselor" id="konselor" value="<?= htmlspecialchars($default_konselor) ?>" required
                                    placeholder="Nama konselor/guru BK..."
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                            </div>
                            <!-- Status -->
                            <div>
                                <label class="block text-sm text-gray-500 mb-1.5">Status</label>
                                <select name="status"
                                    class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-800 focus:outline-none focus:border-violet-500 touch-target">
                                    <option value="Proses">Proses</option>
                                    <option value="Selesai">Selesai</option>
                                    <option value="Ditunda">Ditunda</option>
                                </select>
                            </div>
                        </div>

                        <!-- Masalah -->
                        <div class="mb-4">
                            <label class="block text-sm text-gray-500 mb-1.5">
                                Uraian Masalah <span class="text-red-400">*</span>
                            </label>
                            <textarea name="masalah" id="masalah" rows="3" required
                                placeholder="Tuliskan uraian masalah yang dikonseling..."
                                class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-800 focus:outline-none focus:border-violet-500 resize-none"></textarea>
                            <p class="text-xs text-gray-500 mt-1" id="masalah-count">0 karakter</p>
                        </div>

                        <!-- Solusi -->
                        <div class="mb-4">
                            <label class="block text-sm text-gray-500 mb-1.5">
                                Solusi / Rekomendasi
                                <span class="text-gray-500 font-normal">(Opsional)</span>
                            </label>
                            <textarea name="solusi" id="solusi" rows="3"
                                placeholder="Solusi atau rekomendasi yang diberikan konselor..."
                                class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-800 focus:outline-none focus:border-violet-500 resize-none"></textarea>
                        </div>

                        <!-- Tindak Lanjut -->
                        <div>
                            <label class="block text-sm text-gray-500 mb-1.5">
                                Tindak Lanjut
                                <span class="text-gray-500 font-normal">(Opsional)</span>
                            </label>
                            <textarea name="tindak_lanjut" id="tindak_lanjut" rows="2"
                                placeholder="Rencana tindak lanjut / jadwal pertemuan berikutnya..."
                                class="w-full bg-gray-50/50 border border-gray-300 rounded-lg px-3 py-2.5 text-gray-800 focus:outline-none focus:border-violet-500 resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col sm:flex-row justify-end gap-3">
                        <a href="konseling.php"
                            class="w-full sm:w-auto px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition-colors font-medium text-center">
                            <i class="fas fa-times mr-2"></i>Batal
                        </a>
                        <button type="submit" id="btn-submit" disabled
                            class="w-full sm:w-auto px-6 py-2.5 bg-purple-600 hover:bg-purple-700 disabled:opacity-40 disabled:cursor-not-allowed text-gray-800 rounded-lg transition-colors font-medium">
                            <i class="fas fa-save mr-2"></i>Simpan Konseling
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        let selectedJenis = null;

        // ── Filter kelas ──
        function filterSiswaByKelas() {
            const kelas = document.getElementById('kelas_filter').value;
            const dropdown = document.getElementById('siswa_id');
            dropdown.value = '';
            document.getElementById('riwayat-box').classList.add('hidden');
            Array.from(dropdown.options).forEach(opt => {
                if (!opt.value) {
                    opt.style.display = '';
                    return;
                }
                opt.style.display = (!kelas || opt.dataset.kelas === kelas) ? '' : 'none';
            });
        }

        // ── Saat siswa dipilih ──
        function onSiswaChange() {
            fetchRiwayatKonseling();
            validateForm();
        }

        // ── Ambil riwayat konseling siswa ──
        async function fetchRiwayatKonseling() {
            const siswaId = document.getElementById('siswa_id').value;
            const box = document.getElementById('riwayat-box');
            const detail = document.getElementById('riwayat-detail');

            if (!siswaId) {
                box.classList.add('hidden');
                return;
            }

            try {
                const res = await fetch(`get_riwayat_konseling.php?siswa_id=${siswaId}`);
                const data = await res.json();

                const badgeMap = {
                    Akademik: 'badge-akademik',
                    Pribadi: 'badge-pribadi',
                    Sosial: 'badge-sosial',
                    Karir: 'badge-karir',
                    Keluarga: 'badge-keluarga',
                    Lainnya: 'badge-lainnya',
                };
                const statusColor = {
                    Proses: 'text-blue-400',
                    Selesai: 'text-green-600',
                    Ditunda: 'text-red-400'
                };

                if (data.length === 0) {
                    detail.innerHTML = '<p class="text-xs text-gray-500 italic">Belum ada riwayat konseling</p>';
                } else {
                    detail.innerHTML = data.slice(0, 5).map(d => `
                        <div class="riwayat-item rounded-lg p-2.5 flex items-start gap-3">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded flex-shrink-0 ${badgeMap[d.jenis_konseling] || 'badge-lainnya'}">${d.jenis_konseling}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-800 truncate">${d.masalah}</p>
                                <p class="text-xs text-gray-500">${d.tanggal} · ${d.konselor}</p>
                            </div>
                            <span class="text-xs flex-shrink-0 ${statusColor[d.status] || 'text-gray-500'}">${d.status}</span>
                        </div>`).join('');
                    if (data.length > 5) {
                        detail.innerHTML += `<p class="text-xs text-gray-500 text-center mt-1">+${data.length - 5} riwayat lainnya</p>`;
                    }
                }
                box.classList.remove('hidden');
            } catch (e) {
                box.classList.add('hidden');
            }
        }

        // ── Pilih jenis konseling ──
        function selectJenis(card) {
            document.querySelectorAll('.jenis-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedJenis = card.dataset.jenis;
            document.getElementById('input_jenis_konseling').value = selectedJenis;

            // Preview badge
            const badgeMap = {
                Akademik: 'badge-akademik',
                Pribadi: 'badge-pribadi',
                Sosial: 'badge-sosial',
                Karir: 'badge-karir',
                Keluarga: 'badge-keluarga',
                Lainnya: 'badge-lainnya'
            };
            const badge = document.getElementById('prev-jenis-badge');
            badge.className = 'text-xs font-bold px-2 py-0.5 rounded ' + (badgeMap[selectedJenis] || '');
            badge.textContent = selectedJenis;
            document.getElementById('jenis-preview').classList.remove('hidden');

            validateForm();
        }

        // ── Validasi: aktifkan tombol simpan ──
        function validateForm() {
            const siswa = document.getElementById('siswa_id').value;
            const jenis = document.getElementById('input_jenis_konseling').value;
            const masalah = document.getElementById('masalah').value.trim();
            document.getElementById('btn-submit').disabled = !(siswa && jenis && masalah);
        }

        // ── Counter karakter masalah ──
        document.getElementById('masalah').addEventListener('input', function() {
            document.getElementById('masalah-count').textContent = this.value.length + ' karakter';
            validateForm();
        });

        // Validasi juga saat konselor diisi
        document.getElementById('konselor').addEventListener('input', validateForm);

        // ── Validasi sebelum submit ──
        document.getElementById('mainForm').addEventListener('submit', function(e) {
            if (!document.getElementById('input_jenis_konseling').value) {
                e.preventDefault();
                alert('Pilih jenis konseling terlebih dahulu!');
                return;
            }
            if (!document.getElementById('masalah').value.trim()) {
                e.preventDefault();
                alert('Uraian masalah tidak boleh kosong!');
                return;
            }
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

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && window.innerWidth < 1024) {
                const s = document.getElementById('sidebar');
                if (!s.classList.contains('-translate-x-full')) toggleSidebar();
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
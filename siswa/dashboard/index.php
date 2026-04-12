<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../../siswa/login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];
$today = date('Y-m-d');

// Attendance summary
$sql = "SELECT 
            COUNT(CASE WHEN status = 'Hadir' AND approval_status = 'Approved' THEN 1 END) as hadir,
            COUNT(CASE WHEN status = 'Sakit' AND approval_status = 'Approved' THEN 1 END) as sakit,
            COUNT(CASE WHEN status = 'Izin' AND approval_status = 'Approved' THEN 1 END) as izin,
            COUNT(CASE WHEN status = 'Terlambat' AND approval_status = 'Approved' THEN 1 END) as terlambat,
            COUNT(CASE WHEN status = 'Alpha' AND approval_status = 'Approved' THEN 1 END) as alpha
        FROM absensi 
        WHERE siswa_id = :siswa_id";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
$attendance_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Today's attendance (exclude rejected)
$sql = "SELECT * FROM absensi 
        WHERE siswa_id = :siswa_id 
        AND tanggal = :today 
        AND approval_status != 'Rejected'";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id, 'today' => $today]);
$today_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Rejected submission today
$sql = "SELECT * FROM absensi 
        WHERE siswa_id = :siswa_id 
        AND tanggal = :today 
        AND approval_status = 'Rejected'";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id, 'today' => $today]);
$rejected_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Attendance history this month
$sql = "SELECT 
            a.*, 
            DATE_FORMAT(a.tanggal, '%d') as day,
            DATE_FORMAT(a.tanggal, '%a') as day_name
        FROM absensi a
        WHERE a.siswa_id = :siswa_id 
        AND MONTH(a.tanggal) = MONTH(CURRENT_DATE())
        AND YEAR(a.tanggal) = YEAR(CURRENT_DATE())
        AND a.approval_status = 'Approved'
        ORDER BY a.tanggal DESC";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
$attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending requests
$sql = "SELECT * FROM absensi 
        WHERE siswa_id = :siswa_id 
        AND approval_status = 'Pending'
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attendance_percentage = 100;
$submission_message = '';
$submission_status = '';

// ── POST: Submit Attendance ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $status     = $_POST['status'];
    $keterangan = $_POST['keterangan'] ?? '';

    if ($today_attendance) {
        $submission_status  = 'error';
        $submission_message = 'Anda sudah melakukan absensi hari ini.';
    } else {
        // Hapus submission rejected jika ada
        if ($rejected_attendance) {
            $conn->prepare("DELETE FROM absensi WHERE id = :id")
                ->execute(['id' => $rejected_attendance['id']]);
        }

        // Validasi foto kamera untuk Hadir
        if ($status === 'Hadir') {
            if (empty($_POST['camera_image_data'])) {
                $submission_status  = 'error';
                $submission_message = 'Bukti foto kehadiran wajib diambil melalui kamera.';
            } elseif (!preg_match('/^data:image\/(jpg|jpeg|png);base64,/', $_POST['camera_image_data'])) {
                $submission_status  = 'error';
                $submission_message = 'Format gambar tidak valid.';
            }
        }

        if ($submission_status !== 'error') {
            try {
                $conn->beginTransaction();

                $jam_masuk = ($status === 'Hadir') ? date('H:i:s') : null;

                // INSERT tanpa kolom bukti_foto
                $sql = "INSERT INTO absensi (siswa_id, tanggal, jam_masuk, status, keterangan, approval_status, created_at)
                        VALUES (:siswa_id, :tanggal, :jam_masuk, :status, :keterangan, 'Pending', NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'siswa_id'   => $siswa_id,
                    'tanggal'    => $today,
                    'jam_masuk'  => $jam_masuk,
                    'status'     => $status,
                    'keterangan' => $keterangan,
                ]);

                $conn->prepare("INSERT INTO activity_log (user_type, user_id, activity_type, description)
                                VALUES ('siswa', :user_id, 'absensi', :description)")
                    ->execute([
                        'user_id'     => $siswa_id,
                        'description' => "Siswa {$_SESSION['siswa_name']} mengajukan absensi sebagai {$status}",
                    ]);

                $conn->commit();

                header("Location: index.php?status=success&message=" . urlencode('Absensi berhasil dikirim dan menunggu persetujuan.'));
                exit();
            } catch (Exception $e) {
                $conn->rollBack();
                $submission_status  = 'error';
                $submission_message = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// ── POST: Cancel Pending Request ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'];
    try {
        $conn->beginTransaction();

        $check_stmt = $conn->prepare("SELECT * FROM absensi WHERE id = :id AND siswa_id = :siswa_id AND approval_status = 'Pending'");
        $check_stmt->execute(['id' => $request_id, 'siswa_id' => $siswa_id]);

        if ($check_stmt->rowCount() > 0) {
            $conn->prepare("DELETE FROM absensi WHERE id = :id")->execute(['id' => $request_id]);
            $conn->prepare("INSERT INTO activity_log (user_type, user_id, activity_type, description)
                            VALUES ('siswa', :user_id, 'delete', :description)")
                ->execute([
                    'user_id'     => $siswa_id,
                    'description' => "Siswa {$_SESSION['siswa_name']} membatalkan pengajuan absensi",
                ]);
            $conn->commit();
            header("Location: index.php?status=success&message=Permintaan berhasil dibatalkan");
            exit();
        } else {
            throw new Exception("Permintaan tidak ditemukan atau bukan milik Anda");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $submission_status  = 'error';
        $submission_message = 'Gagal membatalkan permintaan: ' . $e->getMessage();
    }
}

// ── GET: Flash message ────────────────────────────────────────────────────────
if (isset($_GET['status'], $_GET['message'])) {
    $submission_status  = $_GET['status'];
    $submission_message = $_GET['message'];
}

// ── Chart data ────────────────────────────────────────────────────────────────
$sql = "SELECT status, COUNT(*) as count FROM absensi 
        WHERE siswa_id = :siswa_id AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
$attendance_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_data    = ['labels' => [], 'data' => [], 'colors' => []];
$status_colors = [
    'Hadir'     => '#10B981',
    'Sakit'     => '#EAB308',
    'Izin'      => '#8B5CF6',
    'Terlambat' => '#F97316',
    'Alpha'     => '#EF4444',
];
foreach ($attendance_stats as $stat) {
    $chart_data['labels'][] = $stat['status'];
    $chart_data['data'][]   = (int) $stat['count'];
    $chart_data['colors'][] = $status_colors[$stat['status']] ?? '#9CA3AF';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - SMK NURUL ULUM</title>
    <link rel="icon" href="../assets/default/logosmk.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp .3s ease-out forwards;
        }

        .sidebar-transition {
            transition: transform .3s ease-in-out;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        button:focus,
        a:focus {
            outline: 2px solid rgba(147, 51, 234, .5);
            outline-offset: 2px;
        }

        button,
        a,
        input,
        select,
        textarea {
            transition: all .2s ease;
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
        <div class="p-4 border-b border-violet-200">
            <div class="flex items-center gap-3">
                <img src="../../<?= $_SESSION['siswa_photo'] ?: 'assets/default/photo-profile.png' ?>" alt="Profile"
                    class="h-10 w-10 rounded-full object-cover border-2 border-violet-300">
                <div>
                    <h2 class="font-medium text-sm"><?= htmlspecialchars($_SESSION['siswa_name']) ?></h2>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($_SESSION['siswa_kelas']) ?> <?= htmlspecialchars($_SESSION['siswa_jurusan']) ?></p>
                </div>
            </div>
        </div>
        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height:calc(100vh - 160px)">
            <a href="index.php" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg menu-active">
                <i class="fas fa-home text-violet-600"></i><span>Dashboard</span>
            </a>
            <a href="../riwayat/index.php" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-history"></i><span>Riwayat Absensi</span>
            </a>
            <a href="../profil/index.php" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-user"></i><span>Profil</span>
            </a>
            <div class="pt-4">
                <a href="../logout.php" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-sky-50 to-indigo-50 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-white/90 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-gray-800 p-1"><i class="fas fa-bars text-lg"></i></button>
                <img src="../../assets/default/logosmk.png" alt="SMK NURUL ULUM" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium"></span>
                <img src="../../<?= $_SESSION['siswa_photo'] ?: 'assets/default/photo-profile.png' ?>" alt="Profile"
                    class="h-8 w-8 rounded-full object-cover border border-violet-300">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-6xl mx-auto">

                <!-- Header -->
                <header class="mb-6 lg:mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 class="text-xl lg:text-2xl font-bold">Selamat Datang, <?= explode(' ', $_SESSION['siswa_name'])[0] ?>!</h1>
                            <p class="text-gray-500 text-sm lg:text-base mt-1"><?= date('l, d F Y') ?></p>
                        </div>
                        <div class="hidden lg:flex glass-effect rounded-lg px-4 py-2 items-center gap-2 mt-4 md:mt-0">
                            <i class="fas fa-clock text-violet-500"></i>
                            <span id="current-time" class="font-medium"></span>
                        </div>
                    </div>
                </header>

                <!-- Flash Message -->
                <?php if ($submission_message): ?>
                    <div class="mb-6 p-4 rounded-lg border <?= $submission_status === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-500' : 'bg-red-500/10 border-red-500/30 text-red-500' ?>">
                        <div class="flex items-center">
                            <i class="fas <?= $submission_status === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                            <p class="text-sm"><?= htmlspecialchars($submission_message) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">

                    <!-- Attendance Form -->
                    <div class="glass-effect rounded-xl p-4 lg:p-6 lg:col-span-2">
                        <h3 class="font-semibold text-base lg:text-lg mb-4 flex items-center">
                            <i class="fas fa-clipboard-check text-violet-600 mr-2"></i>Absensi Hari Ini
                        </h3>

                        <?php if ($today_attendance): ?>
                            <!-- Sudah absen -->
                            <div class="p-3 lg:p-4 border border-gray-200 rounded-lg bg-gray-50/50">
                                <div class="mb-3 flex items-center flex-wrap gap-2">
                                    <span class="text-sm font-medium">Status:</span>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                    <?php switch ($today_attendance['status']) {
                                        case 'Hadir':
                                            echo 'bg-green-50 text-green-600 border border-green-500/30';
                                            break;
                                        case 'Sakit':
                                            echo 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/30';
                                            break;
                                        case 'Izin':
                                            echo 'bg-purple-500/10 text-violet-600 border border-violet-200';
                                            break;
                                        case 'Terlambat':
                                            echo 'bg-orange-500/10 text-orange-500 border border-orange-500/30';
                                            break;
                                        case 'Alpha':
                                            echo 'bg-red-50 text-red-600 border border-red-200';
                                            break;
                                        default:
                                            echo 'bg-gray-500/10 text-gray-500 border border-gray-500/30';
                                    } ?>">
                                        <?= htmlspecialchars($today_attendance['status']) ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm font-medium">Approval Status:</span>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium ml-2
                                    <?php switch ($today_attendance['approval_status']) {
                                        case 'Approved':
                                            echo 'bg-green-50 text-green-600 border border-green-500/30';
                                            break;
                                        case 'Rejected':
                                            echo 'bg-red-50 text-red-600 border border-red-200';
                                            break;
                                        default:
                                            echo 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/30';
                                    } ?>">
                                        <?= htmlspecialchars($today_attendance['approval_status']) ?>
                                    </span>
                                </div>
                                <?php if ($today_attendance['jam_masuk']): ?>
                                    <div class="mb-3">
                                        <span class="text-sm font-medium">Waktu Absen:</span>
                                        <span class="text-gray-700 ml-2"><?= date('H:i', strtotime($today_attendance['jam_masuk'])) ?> WIB</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($today_attendance['keterangan']): ?>
                                    <div class="mt-4">
                                        <span class="text-sm font-medium block mb-1">Keterangan:</span>
                                        <p class="text-gray-500 text-sm bg-gray-50/50 p-3 rounded-lg border border-gray-200">
                                            <?= htmlspecialchars($today_attendance['keterangan']) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($rejected_attendance): ?>
                            <!-- Ditolak – kirim ulang -->
                            <div class="p-3 lg:p-4 border border-red-200 rounded-lg bg-red-500/10 mb-4">
                                <div class="flex items-center text-red-500">
                                    <i class="fas fa-exclamation-circle text-xl mr-3"></i>
                                    <div>
                                        <p class="font-medium">Pengajuan absensi Anda ditolak</p>
                                        <p class="text-xs mt-1">Silakan ajukan ulang dengan informasi yang benar</p>
                                    </div>
                                </div>
                            </div>

                            <form action="" method="POST" class="space-y-4" id="attendanceForm">
                                <!-- Camera Widget -->
                                <div id="camera-container" class="hidden mb-4 p-3 lg:p-4 border border-gray-200 rounded-lg bg-gray-50/50">
                                    <h4 class="text-sm font-medium text-gray-700 mb-1">Ambil Foto Kehadiran <span class="text-red-400">*</span></h4>
                                    <p class="text-xs text-gray-500 mb-2">Posisikan wajah Anda dengan jelas di dalam frame</p>
                                    <div class="relative mb-3 rounded-lg overflow-hidden bg-black">
                                        <video id="camera-preview" class="w-full h-40 sm:h-48 lg:h-64 object-cover rounded-lg"></video>
                                        <canvas id="camera-canvas" class="hidden"></canvas>
                                        <div id="camera-overlay" class="hidden absolute inset-0 bg-black flex items-center justify-center">
                                            <img id="camera-result" class="max-h-full max-w-full rounded-lg" src="" alt="Foto">
                                        </div>
                                        <div id="camera-loading" class="absolute inset-0 flex items-center justify-center bg-black/70">
                                            <div class="text-center">
                                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-purple-500"></div>
                                                <p class="text-xs mt-2 text-gray-300">Memuat kamera...</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" id="switch-camera-btn" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs text-gray-800 flex items-center">
                                            <i class="fas fa-sync mr-1"></i> Ganti Kamera
                                        </button>
                                        <button type="button" id="capture-btn" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-xs text-white flex items-center">
                                            <i class="fas fa-camera mr-1"></i> Ambil Foto
                                        </button>
                                        <button type="button" id="retake-btn" class="hidden px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs text-gray-800 flex items-center">
                                            <i class="fas fa-redo mr-1"></i> Ulangi
                                        </button>
                                    </div>
                                    <input type="hidden" name="camera_image_data" id="camera-image-data">
                                </div>

                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Kehadiran</label>
                                    <div class="grid grid-cols-3 gap-2 lg:gap-3">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Hadir" class="hidden peer" required <?= $rejected_attendance['status'] === 'Hadir' ? 'checked' : '' ?>>
                                            <div class="p-2 lg:p-3 border border-gray-300 rounded-lg peer-checked:border-green-500 peer-checked:bg-green-500/10 text-center transition-colors">
                                                <i class="fas fa-check text-green-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Hadir</p>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Sakit" class="hidden peer" <?= $rejected_attendance['status'] === 'Sakit' ? 'checked' : '' ?>>
                                            <div class="p-2 lg:p-3 border border-gray-300 rounded-lg peer-checked:border-yellow-500 peer-checked:bg-yellow-500/10 text-center transition-colors">
                                                <i class="fas fa-hospital text-yellow-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Sakit</p>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Izin" class="hidden peer" <?= $rejected_attendance['status'] === 'Izin' ? 'checked' : '' ?>>
                                            <div class="p-2 lg:p-3 border border-gray-300 rounded-lg peer-checked:border-purple-500 peer-checked:bg-purple-500/10 text-center transition-colors">
                                                <i class="fas fa-clipboard-list text-violet-600 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Izin</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Keterangan (Sakit/Izin) -->
                                <div id="additionalFields" class="hidden space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                                        <textarea name="keterangan" rows="3"
                                            class="w-full px-3 py-2 rounded-lg bg-gray-50/50 border border-gray-300 text-gray-800 focus:outline-none focus:ring-2 focus:ring-violet-300"
                                            placeholder="Masukkan alasan ketidakhadiran..."><?= htmlspecialchars($rejected_attendance['keterangan'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <button type="submit" name="submit_attendance"
                                        class="px-4 lg:px-6 py-2 lg:py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium text-white transition-colors flex items-center text-sm">
                                        <i class="fas fa-paper-plane mr-2"></i> Kirim Ulang Absensi
                                    </button>
                                </div>
                            </form>

                        <?php else: ?>
                            <!-- Form absensi normal -->
                            <form action="" method="POST" class="space-y-4" id="attendanceForm">
                                <!-- Camera Widget -->
                                <div id="camera-container" class="hidden mb-4 p-3 lg:p-4 border border-gray-200 rounded-lg bg-gray-50/50">
                                    <h4 class="text-sm font-medium text-gray-700 mb-1">Ambil Foto Kehadiran <span class="text-red-400">*</span></h4>
                                    <p class="text-xs text-gray-500 mb-2">Posisikan wajah Anda dengan jelas di dalam frame</p>
                                    <div class="relative mb-3 rounded-lg overflow-hidden bg-black">
                                        <video id="camera-preview" class="w-full h-48 lg:h-64 object-cover rounded-lg"></video>
                                        <canvas id="camera-canvas" class="hidden"></canvas>
                                        <div id="camera-overlay" class="hidden absolute inset-0 bg-black flex items-center justify-center">
                                            <img id="camera-result" class="max-h-full rounded-lg" src="" alt="Foto">
                                        </div>
                                        <div id="camera-loading" class="absolute inset-0 flex items-center justify-center bg-black/70">
                                            <div class="text-center">
                                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-purple-500"></div>
                                                <p class="text-xs mt-2 text-gray-300">Memuat kamera...</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" id="switch-camera-btn" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs text-gray-800 flex items-center">
                                            <i class="fas fa-sync mr-1"></i> Ganti Kamera
                                        </button>
                                        <button type="button" id="capture-btn" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-xs text-white flex items-center">
                                            <i class="fas fa-camera mr-1"></i> Ambil Foto
                                        </button>
                                        <button type="button" id="retake-btn" class="hidden px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs text-gray-800 flex items-center">
                                            <i class="fas fa-redo mr-1"></i> Ulangi
                                        </button>
                                    </div>
                                    <input type="hidden" name="camera_image_data" id="camera-image-data">
                                </div>

                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Kehadiran</label>
                                    <div class="grid grid-cols-3 gap-2 lg:gap-3">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Hadir" class="hidden peer" required checked>
                                            <div class="p-2 lg:p-3 border border-gray-300 rounded-lg peer-checked:border-green-500 peer-checked:bg-green-500/10 text-center transition-colors">
                                                <i class="fas fa-check text-green-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Hadir</p>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Sakit" class="hidden peer">
                                            <div class="p-2 lg:p-3 border border-gray-300 rounded-lg peer-checked:border-yellow-500 peer-checked:bg-yellow-500/10 text-center transition-colors">
                                                <i class="fas fa-hospital text-yellow-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Sakit</p>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Izin" class="hidden peer">
                                            <div class="p-2 lg:p-3 border border-gray-300 rounded-lg peer-checked:border-purple-500 peer-checked:bg-purple-500/10 text-center transition-colors">
                                                <i class="fas fa-clipboard-list text-violet-600 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Izin</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Keterangan (Sakit/Izin) -->
                                <div id="additionalFields" class="hidden space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                                        <textarea name="keterangan" rows="3"
                                            class="w-full px-3 py-2 rounded-lg bg-gray-50/50 border border-gray-300 text-gray-800 focus:outline-none focus:ring-2 focus:ring-violet-300"
                                            placeholder="Masukkan alasan ketidakhadiran..."></textarea>
                                    </div>
                                </div>

                                <div>
                                    <button type="submit" name="submit_attendance"
                                        class="px-4 lg:px-6 py-2 lg:py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium text-white transition-colors flex items-center text-sm">
                                        <i class="fas fa-paper-plane mr-2"></i> Kirim Absensi
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Summary -->
                    <div class="glass-effect rounded-xl p-4 lg:p-6">
                        <h3 class="font-semibold text-base lg:text-lg mb-4 flex items-center">
                            <i class="fas fa-chart-pie text-violet-600 mr-2"></i>Ringkasan Kehadiran
                        </h3>
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-500">Persentase Kehadiran</span>
                                <span class="text-sm font-medium"><?= $attendance_percentage ?>%</span>
                            </div>
                            <div class="w-full bg-gray-50/60 rounded-full h-2">
                                <div class="bg-gradient-to-r from-purple-500 to-indigo-500 h-2 rounded-full" style="width:<?= min($attendance_percentage, 100) ?>%"></div>
                            </div>
                        </div>
                        <div class="aspect-square mb-4"><canvas id="attendanceChart"></canvas></div>
                        <div class="grid grid-cols-2 gap-3 text-xs lg:text-sm">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span><span>Hadir</span></div>
                                <span class="font-medium"><?= $attendance_summary['hadir'] ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></span><span>Sakit</span></div>
                                <span class="font-medium"><?= $attendance_summary['sakit'] ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-purple-500 mr-2"></span><span>Izin</span></div>
                                <span class="font-medium"><?= $attendance_summary['izin'] ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-orange-500 mr-2"></span><span>Terlambat</span></div>
                                <span class="font-medium"><?= $attendance_summary['terlambat'] ?></span>
                            </div>
                            <div class="flex justify-between items-center col-span-2">
                                <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-red-500 mr-2"></span><span>Alpha</span></div>
                                <span class="font-medium"><?= $attendance_summary['alpha'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending & Calendar -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">

                    <!-- Pending Requests -->
                    <div class="glass-effect rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-violet-50 to-indigo-50 p-4 border-b border-gray-200">
                            <h3 class="font-semibold flex items-center text-base">
                                <i class="fas fa-clock text-violet-600 mr-2"></i>Permintaan Menunggu Persetujuan
                            </h3>
                        </div>
                        <?php if (count($pending_requests) > 0): ?>
                            <div class="divide-y divide-gray-100 max-h-[400px] overflow-y-auto">
                                <?php foreach ($pending_requests as $request): ?>
                                    <div class="p-3 lg:p-4 hover:bg-gray-50/30 transition-colors">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center
                                        <?php switch ($request['status']) {
                                            case 'Hadir':
                                                echo 'bg-green-50 text-green-600 border border-green-500/30';
                                                break;
                                            case 'Sakit':
                                                echo 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/30';
                                                break;
                                            case 'Izin':
                                                echo 'bg-purple-500/10 text-violet-600 border border-violet-200';
                                                break;
                                            default:
                                                echo 'bg-gray-500/10 text-gray-500 border border-gray-500/30';
                                        } ?>">
                                            <i class="fas <?= $request['status'] === 'Sakit' ? 'fa-hospital' : ($request['status'] === 'Hadir' ? 'fa-check' : 'fa-clipboard-list') ?> mr-1"></i>
                                            <?= htmlspecialchars($request['status']) ?>
                                        </span>
                                        <div class="mt-2 text-xs text-gray-500">
                                            <span class="inline-block mr-3">
                                                <i class="far fa-calendar-alt mr-1"></i><?= date('d M Y', strtotime($request['tanggal'])) ?>
                                            </span>
                                            <?php if ($request['created_at']): ?>
                                                <span><i class="far fa-clock mr-1"></i><?= date('H:i', strtotime($request['created_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($request['keterangan']): ?>
                                            <div class="mt-2">
                                                <p class="text-xs text-gray-500">Keterangan:</p>
                                                <p class="text-xs text-gray-700"><?= htmlspecialchars($request['keterangan']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-3 flex items-center justify-between flex-wrap gap-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/10 text-yellow-500 border border-yellow-500/30">
                                                <i class="fas fa-hourglass-half mr-1.5"></i>Menunggu Persetujuan
                                            </span>
                                            <button type="button" onclick="showConfirmationModal(<?= $request['id'] ?>)"
                                                class="text-red-400 hover:text-red-300 text-sm flex items-center">
                                                <i class="fas fa-trash-alt mr-1"></i>Batalkan
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-50/50 mb-4">
                                    <i class="fas fa-check-circle text-2xl text-gray-500"></i>
                                </div>
                                <p class="text-gray-500">Tidak ada permintaan yang tertunda.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Calendar -->
                    <div class="glass-effect rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-violet-50 to-indigo-50 p-4 border-b border-gray-200">
                            <h3 class="font-semibold flex items-center text-base">
                                <i class="fas fa-calendar-alt text-violet-600 mr-2"></i>Kehadiran Bulan Ini
                            </h3>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-7 gap-1 text-center mb-2">
                                <?php foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $d): ?>
                                    <div class="text-xs font-medium text-gray-500"><?= $d ?></div>
                                <?php endforeach; ?>
                            </div>
                            <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
                            <div class="p-4 border-t border-gray-100 grid grid-cols-3 gap-2 mt-2">
                                <div class="flex items-center"><span class="h-3 w-3 rounded-full bg-green-500 mr-2"></span><span class="text-xs">Hadir</span></div>
                                <div class="flex items-center"><span class="h-3 w-3 rounded-full bg-yellow-500 mr-2"></span><span class="text-xs">Sakit</span></div>
                                <div class="flex items-center"><span class="h-3 w-3 rounded-full bg-purple-500 mr-2"></span><span class="text-xs">Izin</span></div>
                                <div class="flex items-center"><span class="h-3 w-3 rounded-full bg-orange-500 mr-2"></span><span class="text-xs">Terlambat</span></div>
                                <div class="flex items-center"><span class="h-3 w-3 rounded-full bg-red-500 mr-2"></span><span class="text-xs">Alpha</span></div>
                                <div class="flex items-center"><span class="h-3 w-3 rounded-full bg-gray-200 mr-2"></span><span class="text-xs">Belum Absen</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Image Preview Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black/80 z-50 hidden flex items-center justify-center p-4">
        <div class="relative max-w-xl w-full">
            <button onclick="closeImagePreview()" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
            <img id="previewImage" src="" alt="Preview" class="w-full rounded-lg">
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-black/40 z-50 hidden flex items-center justify-center p-4">
        <div class="glass-effect rounded-xl max-w-md w-full p-6 border border-violet-200">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Konfirmasi</h3>
            <p class="text-gray-700 mb-6">Apakah Anda yakin ingin membatalkan pengajuan absensi ini?</p>
            <form method="POST" id="cancelForm">
                <input type="hidden" name="request_id" id="requestIdInput">
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeConfirmationModal()"
                        class="px-4 py-2 bg-gray-200/50 hover:bg-gray-200 rounded-lg text-gray-800">
                        <i class="fas fa-times mr-2"></i>Batal
                    </button>
                    <button type="submit" name="cancel_request"
                        class="px-4 py-2 bg-red-500/80 hover:bg-red-600 rounded-lg text-white">
                        <i class="fas fa-trash-alt mr-2"></i>Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // ── Clock ─────────────────────────────────────────────────────────────────────
        function updateTime() {
            const s = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }) + ' WIB';
            const el = document.getElementById('current-time');
            if (el) el.textContent = s;
        }

        function updateMobileTime() {
            const s = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            const el = document.getElementById('current-time-mobile');
            if (el) el.textContent = s;
        }
        setInterval(updateTime, 1000);
        setInterval(updateMobileTime, 60000);
        updateTime();
        updateMobileTime();

        // ── Sidebar ───────────────────────────────────────────────────────────────────
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            const isHidden = sidebar.classList.contains('-translate-x-full');
            sidebar.classList.toggle('-translate-x-full', !isHidden);
            overlay.classList.toggle('hidden', !isHidden);
            document.body.classList.toggle('overflow-hidden', isHidden);
        }

        // ── Modals ────────────────────────────────────────────────────────────────────
        function showImagePreview(src) {
            document.getElementById('previewImage').src = src;
            document.getElementById('imageModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeImagePreview() {
            document.getElementById('imageModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        document.getElementById('imageModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeImagePreview();
        });

        function showConfirmationModal(id) {
            document.getElementById('requestIdInput').value = id;
            document.getElementById('confirmationModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeConfirmationModal() {
            document.getElementById('confirmationModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        document.getElementById('confirmationModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeConfirmationModal();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeImagePreview();
                closeConfirmationModal();
            }
        });

        // ── Camera ────────────────────────────────────────────────────────────────────
        let stream = null,
            facingMode = 'user';

        function getEls() {
            return {
                preview: document.getElementById('camera-preview'),
                canvas: document.getElementById('camera-canvas'),
                result: document.getElementById('camera-result'),
                overlay: document.getElementById('camera-overlay'),
                captureBtn: document.getElementById('capture-btn'),
                retakeBtn: document.getElementById('retake-btn'),
                imageData: document.getElementById('camera-image-data'),
                loading: document.getElementById('camera-loading'),
            };
        }

        function startCamera() {
            const els = getEls();
            if (!navigator.mediaDevices?.getUserMedia) {
                alert('Browser Anda tidak mendukung kamera. Gunakan Chrome atau Firefox terbaru.');
                return;
            }
            if (stream) stopCamera();
            if (els.loading) els.loading.classList.remove('hidden');
            if (els.overlay) els.overlay.classList.add('hidden');
            if (els.captureBtn) els.captureBtn.classList.remove('hidden');
            if (els.retakeBtn) els.retakeBtn.classList.add('hidden');

            navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode,
                        width: {
                            ideal: 1280
                        },
                        height: {
                            ideal: 720
                        }
                    },
                    audio: false
                })
                .then(ms => {
                    stream = ms;
                    els.preview.srcObject = ms;
                    els.preview.play().then(() => {
                        if (els.loading) els.loading.classList.add('hidden');
                    });
                })
                .catch(err => {
                    if (els.loading) els.loading.classList.add('hidden');
                    const msgs = {
                        NotAllowedError: 'Harap berikan izin kamera.',
                        NotFoundError: 'Kamera tidak ditemukan.',
                        NotReadableError: 'Kamera digunakan aplikasi lain.'
                    };
                    alert('Tidak dapat mengakses kamera. ' + (msgs[err.name] || 'Terjadi kesalahan teknis.'));
                });
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }
        }

        // Status radio – satu handler
        document.querySelectorAll('input[name="status"]').forEach(inp => {
            inp.addEventListener('change', function() {
                const af = document.getElementById('additionalFields');
                const cc = document.getElementById('camera-container');
                if (this.value === 'Hadir') {
                    if (af) af.classList.add('hidden');
                    if (cc) {
                        cc.classList.remove('hidden');
                        startCamera();
                    }
                } else {
                    if (af) af.classList.remove('hidden');
                    if (cc) cc.classList.add('hidden');
                    stopCamera();
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Auto-start kamera jika Hadir sudah terpilih
            const hadirChecked = document.querySelector('input[name="status"][value="Hadir"]:checked');
            if (hadirChecked) {
                const cc = document.getElementById('camera-container');
                if (cc) {
                    cc.classList.remove('hidden');
                    startCamera();
                }
            }

            // Capture
            document.getElementById('capture-btn')?.addEventListener('click', function() {
                const els = getEls();
                if (!stream) return;
                els.canvas.width = els.preview.videoWidth;
                els.canvas.height = els.preview.videoHeight;
                els.canvas.getContext('2d').drawImage(els.preview, 0, 0);
                const img = els.canvas.toDataURL('image/jpeg');
                els.result.src = img;
                els.imageData.value = img;
                els.overlay.classList.remove('hidden');
                els.captureBtn.classList.add('hidden');
                els.retakeBtn.classList.remove('hidden');
            });

            // Retake
            document.getElementById('retake-btn')?.addEventListener('click', function() {
                const els = getEls();
                els.overlay.classList.add('hidden');
                els.captureBtn.classList.remove('hidden');
                els.retakeBtn.classList.add('hidden');
                els.imageData.value = '';
            });

            // Switch camera
            document.getElementById('switch-camera-btn')?.addEventListener('click', function() {
                facingMode = facingMode === 'user' ? 'environment' : 'user';
                startCamera();
            });

            // Form validation
            document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
                const hadirRadio = document.querySelector('input[name="status"][value="Hadir"]:checked');
                const imgData = document.getElementById('camera-image-data');
                if (hadirRadio && (!imgData || !imgData.value)) {
                    e.preventDefault();
                    alert('Anda harus mengambil foto terlebih dahulu untuk absensi Hadir.');
                }
            });

            // Chart
            const ctx = document.getElementById('attendanceChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($chart_data['labels']) ?>,
                        datasets: [{
                            data: <?= json_encode($chart_data['data']) ?>,
                            backgroundColor: <?= json_encode($chart_data['colors']) ?>,
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        cutout: '65%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(17,24,39,0.9)',
                                bodyColor: '#fff',
                                padding: 10,
                                borderColor: 'rgba(139,92,246,0.3)',
                                borderWidth: 1,
                                callbacks: {
                                    label: c => `${c.label}: ${c.raw} (${Math.round(c.raw / c.dataset.data.reduce((a,b)=>a+b,0) * 100)}%)`
                                }
                            }
                        }
                    }
                });
            }

            generateCalendar();
        });

        window.addEventListener('beforeunload', stopCamera);

        // ── Calendar ──────────────────────────────────────────────────────────────────
        function generateCalendar() {
            const now = new Date();
            const year = now.getFullYear();
            const month = now.getMonth();
            const data = <?= json_encode($attendance_history) ?>;
            const map = {};
            data.forEach(r => {
                map[new Date(r.tanggal).getDate()] = r.status;
            });

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const grid = document.getElementById('calendarGrid');
            if (!grid) return;
            grid.innerHTML = '';

            const colors = {
                Hadir: 'bg-green-500',
                Sakit: 'bg-yellow-500',
                Izin: 'bg-purple-500',
                Terlambat: 'bg-orange-500',
                Alpha: 'bg-red-500'
            };
            const todayStr = new Date().toDateString();
            const midnight = new Date(new Date().setHours(0, 0, 0, 0));

            for (let i = 0; i < firstDay; i++) {
                const el = document.createElement('div');
                el.className = 'h-10 rounded-md';
                grid.appendChild(el);
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const cell = document.createElement('div');
                const cellDate = new Date(year, month, d);
                const isToday = cellDate.toDateString() === todayStr;
                const isPast = cellDate < midnight;

                cell.className = 'h-10 flex flex-col items-center justify-center rounded-md hover:bg-gray-50/50 transition-colors' +
                    (isToday ? ' ring-2 ring-purple-500' : '');

                const num = document.createElement('span');
                num.className = 'text-xs' + (isToday ? ' font-bold text-violet-500' : '');
                num.textContent = d;
                cell.appendChild(num);

                if (map[d] || isPast) {
                    const dot = document.createElement('span');
                    dot.className = 'h-2 w-2 rounded-full mt-1 ' + (colors[map[d]] || 'bg-gray-200');
                    cell.title = map[d] || 'Belum Absen';
                    cell.appendChild(dot);
                }

                grid.appendChild(cell);
            }
        }

        window.addEventListener('resize', generateCalendar);
    </script>
</body>

</html>
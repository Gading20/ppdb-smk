<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../../siswa/login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];
$today    = date('Y-m-d');

// ── Attendance summary ────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT 
    COUNT(CASE WHEN status='Hadir'     AND approval_status='Approved' THEN 1 END) as hadir,
    COUNT(CASE WHEN status='Sakit'     AND approval_status='Approved' THEN 1 END) as sakit,
    COUNT(CASE WHEN status='Izin'      AND approval_status='Approved' THEN 1 END) as izin,
    COUNT(CASE WHEN status='Terlambat' AND approval_status='Approved' THEN 1 END) as terlambat,
    COUNT(CASE WHEN status='Alpha'     AND approval_status='Approved' THEN 1 END) as alpha
    FROM absensi WHERE siswa_id = :siswa_id");
$stmt->execute(['siswa_id' => $siswa_id]);
$attendance_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Today's attendance ────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM absensi WHERE siswa_id=:sid AND tanggal=:today AND approval_status!='Rejected'");
$stmt->execute(['sid' => $siswa_id, 'today' => $today]);
$today_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM absensi WHERE siswa_id=:sid AND tanggal=:today AND approval_status='Rejected'");
$stmt->execute(['sid' => $siswa_id, 'today' => $today]);
$rejected_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Attendance history this month ─────────────────────────────────────────────
$stmt = $conn->prepare("SELECT a.*, DATE_FORMAT(a.tanggal,'%d') as day, DATE_FORMAT(a.tanggal,'%a') as day_name
    FROM absensi a
    WHERE a.siswa_id=:sid AND MONTH(a.tanggal)=MONTH(CURRENT_DATE()) AND YEAR(a.tanggal)=YEAR(CURRENT_DATE())
          AND a.approval_status='Approved'
    ORDER BY a.tanggal DESC");
$stmt->execute(['sid' => $siswa_id]);
$attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Pending requests ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM absensi WHERE siswa_id=:sid AND approval_status='Pending' ORDER BY created_at DESC");
$stmt->execute(['sid' => $siswa_id]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$submission_message = '';
$submission_status  = '';
$attendance_percentage = 100;

// ═══════════════════════════════════════════════════════════════════════════════
// KOORDINAT SEKOLAH  ← satu sumber kebenaran, dipakai PHP & JS
// ═══════════════════════════════════════════════════════════════════════════════
$school_lat  = -7.0416;   // lintang sekolah (sesuaikan)
$school_lng  = 109.1460;  // bujur sekolah   (sesuaikan)
$max_radius  = 50;       // meter

// ═══════════════════════════════════════════════════════════════════════════════
// POST: Submit Attendance
// ═══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $status     = $_POST['status'];
    $keterangan = $_POST['keterangan'] ?? '';

    if ($today_attendance) {
        $submission_status  = 'error';
        $submission_message = 'Anda sudah melakukan absensi hari ini.';
    } else {
        if ($rejected_attendance) {
            $conn->prepare("DELETE FROM absensi WHERE id=:id")->execute(['id' => $rejected_attendance['id']]);
        }

        // ── Validasi khusus status Hadir ──────────────────────────────────────
        if ($status === 'Hadir') {
            // 1. Validasi foto wajah
            if (empty($_POST['camera_image_data'])) {
                $submission_status  = 'error';
                $submission_message = 'Bukti foto wajah wajib diambil melalui kamera.';
            } elseif (!preg_match('/^data:image\/(jpg|jpeg|png);base64,/', $_POST['camera_image_data'])) {
                $submission_status  = 'error';
                $submission_message = 'Format gambar tidak valid.';
            }

            // 2. Validasi face-detection result dari client
            if ($submission_status !== 'error' && empty($_POST['face_verified'])) {
                $submission_status  = 'error';
                $submission_message = 'Wajah tidak terdeteksi. Pastikan wajah Anda terlihat jelas di kamera.';
            }

            // 3. Validasi koordinat GPS
            if ($submission_status !== 'error') {
                $lat_in  = isset($_POST['latitude'])  ? (float) $_POST['latitude']  : null;
                $lng_in  = isset($_POST['longitude']) ? (float) $_POST['longitude'] : null;

                if ($lat_in === null || $lng_in === null) {
                    $submission_status  = 'error';
                    $submission_message = 'Lokasi GPS diperlukan untuk absensi Hadir. Aktifkan GPS Anda.';
                } else {
                    // Haversine formula
                    $R    = 6371000;
                    $φ1   = deg2rad($lat_in);
                    $φ2   = deg2rad($school_lat);
                    $Δφ   = deg2rad($school_lat - $lat_in);
                    $Δλ   = deg2rad($school_lng - $lng_in);
                    $a    = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
                    $dist = 2 * $R * asin(sqrt($a));

                    if ($dist > $max_radius) {
                        $submission_status  = 'error';
                        $submission_message = sprintf(
                            'Anda berada %.0f meter dari sekolah. Absensi hanya bisa dilakukan dalam radius %d meter dari sekolah.',
                            $dist,
                            $max_radius
                        );
                    }
                }
            }
        }

        // ── Insert jika lolos semua validasi ─────────────────────────────────
        if ($submission_status !== 'error') {
            try {
                $conn->beginTransaction();

                $jam_masuk = ($status === 'Hadir') ? date('H:i:s') : null;

                $sql = "INSERT INTO absensi
                            (siswa_id, tanggal, jam_masuk, status, keterangan,
                             approval_status, created_at)
                        VALUES
                            (:siswa_id, :tanggal, :jam_masuk, :status, :keterangan,
                             'Pending', NOW())";
                $conn->prepare($sql)->execute([
                    'siswa_id'   => $siswa_id,
                    'tanggal'    => $today,
                    'jam_masuk'  => $jam_masuk,
                    'status'     => $status,
                    'keterangan' => $keterangan,
                ]);

                $conn->prepare("INSERT INTO activity_log (user_type,user_id,activity_type,description)
                                VALUES ('siswa',:uid,'absensi',:desc)")
                    ->execute([
                        'uid'  => $siswa_id,
                        'desc' => "Siswa {$_SESSION['siswa_name']} mengajukan absensi sebagai {$status}",
                    ]);

                $conn->commit();
                header("Location: index.php?status=success&message=" .
                    urlencode('Absensi berhasil dikirim dan menunggu persetujuan.'));
                exit();
            } catch (Exception $e) {
                $conn->rollBack();
                $submission_status  = 'error';
                $submission_message = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// ── POST: Cancel Pending ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'];
    try {
        $conn->beginTransaction();
        $chk = $conn->prepare("SELECT id FROM absensi WHERE id=:id AND siswa_id=:sid AND approval_status='Pending'");
        $chk->execute(['id' => $request_id, 'sid' => $siswa_id]);
        if ($chk->rowCount() > 0) {
            $conn->prepare("DELETE FROM absensi WHERE id=:id")->execute(['id' => $request_id]);
            $conn->prepare("INSERT INTO activity_log(user_type,user_id,activity_type,description)
                            VALUES('siswa',:uid,'delete',:desc)")
                ->execute(['uid' => $siswa_id, 'desc' => "Siswa {$_SESSION['siswa_name']} membatalkan pengajuan absensi"]);
            $conn->commit();
            header("Location: index.php?status=success&message=Permintaan berhasil dibatalkan");
            exit();
        }
        throw new Exception("Data tidak ditemukan.");
    } catch (Exception $e) {
        $conn->rollBack();
        $submission_status  = 'error';
        $submission_message = 'Gagal: ' . $e->getMessage();
    }
}

// ── Flash message ─────────────────────────────────────────────────────────────
if (isset($_GET['status'], $_GET['message'])) {
    $submission_status  = $_GET['status'];
    $submission_message = $_GET['message'];
}

// ── Chart data ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT status,COUNT(*) as count FROM absensi WHERE siswa_id=:sid AND approval_status='Approved' GROUP BY status");
$stmt->execute(['sid' => $siswa_id]);
$attendance_stats  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chart_data        = ['labels' => [], 'data' => [], 'colors' => []];
$status_colors     = ['Hadir' => '#10B981', 'Sakit' => '#EAB308', 'Izin' => '#8B5CF6', 'Terlambat' => '#F97316', 'Alpha' => '#EF4444'];
foreach ($attendance_stats as $st) {
    $chart_data['labels'][] = $st['status'];
    $chart_data['data'][]   = (int)$st['count'];
    $chart_data['colors'][] = $status_colors[$st['status']] ?? '#9CA3AF';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa – SMK NURUL ULUM</title>
    <link rel="icon" href="../../assets/default/logosmk.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- ✅ FIX: Hapus 'defer' agar faceapi tersedia saat DOMContentLoaded -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

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
            animation: fadeUp .3s ease both;
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

        #camera-ring {
            transition: box-shadow .4s ease, border-color .4s ease;
        }

        #camera-ring.detecting {
            box-shadow: 0 0 0 3px rgba(251, 191, 36, .6);
        }

        #camera-ring.face-ok {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .7);
            border-color: #10b981;
        }

        #camera-ring.face-bad {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, .6);
            border-color: #ef4444;
        }

        .gps-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .72rem;
            padding: 4px 10px;
            border-radius: 9999px;
            font-weight: 600;
        }

        .gps-ok {
            background: #d1fae5;
            color: #065f46;
        }

        .gps-err {
            background: #fee2e2;
            color: #991b1b;
        }

        .gps-wait {
            background: #fef3c7;
            color: #92400e;
        }

        #face-canvas {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        #camera-preview {
            transform: scaleX(1) !important;
        }

        .step-badge {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .step-done {
            background: #10b981;
            color: #fff;
        }

        .step-wait {
            background: #e5e7eb;
            color: #6b7280;
        }

        .step-fail {
            background: #ef4444;
            color: #fff;
        }

        .step-active {
            background: #8b5cf6;
            color: #fff;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800">

    <div id="mobile-overlay" class="fixed inset-0 bg-white/40 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- ═══ SIDEBAR ═══ -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass border-r border-violet-200 z-50 sidebar-anim -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 border-b border-violet-200">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logosmk.png" alt="Logo" class="h-9 w-auto">
                <div>
                    <p class="font-semibold text-sm">SMK NURUL ULUM</p>
                    <p class="text-xs text-gray-500">Sistem Absensi</p>
                </div>
            </div>
            <button class="lg:hidden text-gray-500" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 border-b border-violet-200">
            <div class="flex items-center gap-3">
                <img src="../../<?= $_SESSION['siswa_photo'] ?: 'assets/default/photo-profile.png' ?>"
                    class="h-10 w-10 rounded-full object-cover border-2 border-violet-300" alt="">
                <div>
                    <p class="font-medium text-sm"><?= htmlspecialchars($_SESSION['siswa_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($_SESSION['siswa_kelas']) ?> <?= htmlspecialchars($_SESSION['siswa_jurusan']) ?></p>
                </div>
            </div>
        </div>
        <nav class="p-4 space-y-1 no-scrollbar overflow-y-auto" style="max-height:calc(100vh - 160px)">
            <a href="index.php" class="flex items-center gap-3 text-gray-700 p-3 rounded-lg menu-active">
                <i class="fas fa-home text-violet-600 w-4"></i><span>Dashboard</span>
            </a>
            <a href="../riwayat/index.php" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-history w-4"></i><span>Riwayat Absensi</span>
            </a>
            <a href="../profil/index.php" class="flex items-center gap-3 text-gray-600 p-3 rounded-lg hover:bg-violet-100 transition-colors">
                <i class="fas fa-user w-4"></i><span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-red-500 p-3 rounded-lg hover:bg-red-50 mt-6 transition-colors">
                <i class="fas fa-sign-out-alt w-4"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- ═══ MAIN ═══ -->
    <main class="lg:ml-64 min-h-screen transition-all duration-300">

        <!-- Mobile header -->
        <div class="lg:hidden bg-white/90 backdrop-blur sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-violet-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="p-1"><i class="fas fa-bars text-lg text-gray-700"></i></button>
                <img src="../../assets/default/logosmk.png" class="h-8 w-auto" alt="">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium"></span>
                <img src="../../<?= $_SESSION['siswa_photo'] ?: 'assets/default/photo-profile.png' ?>"
                    class="h-8 w-8 rounded-full object-cover border border-violet-300" alt="">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-6xl mx-auto">

                <!-- Header -->
                <header class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h1 class="text-xl lg:text-2xl font-bold">
                            Selamat Datang, <?= explode(' ', $_SESSION['siswa_name'])[0] ?>!
                        </h1>
                        <p class="text-gray-500 text-sm mt-0.5"><?= date('l, d F Y') ?></p>
                    </div>
                    <div class="hidden lg:flex glass rounded-lg px-4 py-2 items-center gap-2">
                        <i class="fas fa-clock text-violet-500"></i>
                        <span id="current-time" class="font-medium text-sm"></span>
                    </div>
                </header>

                <!-- Flash Message -->
                <?php if ($submission_message): ?>
                    <div class="mb-6 p-4 rounded-xl border flex items-start gap-3 fade-up
                    <?= $submission_status === 'success'
                        ? 'bg-green-50 border-green-200 text-green-800'
                        : 'bg-red-50 border-red-200 text-red-800' ?>">
                        <i class="fas <?= $submission_status === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?> mt-0.5"></i>
                        <p class="text-sm flex-1"><?= htmlspecialchars($submission_message) ?></p>
                        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 mb-6">

                    <!-- ════ ATTENDANCE FORM ════ -->
                    <div class="glass rounded-xl p-4 lg:p-6 lg:col-span-2">
                        <h3 class="font-semibold text-base lg:text-lg mb-4 flex items-center gap-2">
                            <i class="fas fa-clipboard-check text-violet-600"></i>Absensi Hari Ini
                        </h3>

                        <?php if ($today_attendance): ?>
                            <!-- Sudah absen -->
                            <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/60 space-y-3">
                                <?php
                                $sc = [
                                    'Hadir'     => 'bg-green-100 text-green-700',
                                    'Sakit'     => 'bg-yellow-100 text-yellow-700',
                                    'Izin'      => 'bg-purple-100 text-purple-700',
                                    'Terlambat' => 'bg-orange-100 text-orange-700',
                                    'Alpha'     => 'bg-red-100 text-red-700'
                                ];
                                $ac = [
                                    'Approved' => 'bg-green-100 text-green-700',
                                    'Rejected' => 'bg-red-100 text-red-700',
                                    'Pending'  => 'bg-yellow-100 text-yellow-700'
                                ];
                                ?>
                                <div class="flex flex-wrap gap-2 items-center">
                                    <span class="text-sm font-medium text-gray-600">Status:</span>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $sc[$today_attendance['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <?= htmlspecialchars($today_attendance['status']) ?>
                                    </span>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $ac[$today_attendance['approval_status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <?= htmlspecialchars($today_attendance['approval_status']) ?>
                                    </span>
                                </div>
                                <?php if ($today_attendance['jam_masuk']): ?>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-clock mr-1 text-violet-400"></i>
                                        Waktu: <strong><?= date('H:i', strtotime($today_attendance['jam_masuk'])) ?> WIB</strong>
                                    </p>
                                <?php endif; ?>
                                <?php if ($today_attendance['keterangan']): ?>
                                    <div class="bg-white rounded-lg p-3 border border-gray-100">
                                        <p class="text-xs text-gray-500 mb-1">Keterangan:</p>
                                        <p class="text-sm text-gray-700"><?= htmlspecialchars($today_attendance['keterangan']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <!-- ════ FORM ABSENSI ════ -->
                            <?php if ($rejected_attendance): ?>
                                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl flex gap-3 text-red-700">
                                    <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0"></i>
                                    <div>
                                        <p class="font-semibold text-sm">Pengajuan sebelumnya ditolak</p>
                                        <p class="text-xs mt-0.5">Silakan ajukan ulang dengan informasi yang benar.</p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <form id="attendanceForm" method="POST" class="space-y-5">

                                <!-- ── Pilih Status ── -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status Kehadiran</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <?php
                                        $statuses = [
                                            ['Hadir', 'fa-check',          'green'],
                                            ['Sakit', 'fa-hospital',       'yellow'],
                                            ['Izin',  'fa-clipboard-list', 'purple'],
                                        ];
                                        foreach ($statuses as [$sv, $icon, $c]):
                                            $checked = ($rejected_attendance && $rejected_attendance['status'] === $sv)
                                                || (!$rejected_attendance && $sv === 'Hadir') ? 'checked' : '';
                                        ?>
                                            <label class="cursor-pointer">
                                                <input type="radio" name="status" value="<?= $sv ?>" class="hidden peer" <?= $checked ?> required>
                                                <div class="p-3 border-2 border-gray-200 rounded-xl peer-checked:border-<?= $c ?>-500 peer-checked:bg-<?= $c ?>-50 text-center transition-all">
                                                    <i class="fas <?= $icon ?> text-<?= $c ?>-500 text-lg mb-1 block"></i>
                                                    <p class="text-xs font-semibold"><?= $sv ?></p>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- ── Keterangan (Sakit / Izin) ── -->
                                <div id="keteranganBox" class="hidden">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        Keterangan <span class="text-red-400">*</span>
                                    </label>
                                    <textarea name="keterangan" rows="3"
                                        class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-violet-300"
                                        placeholder="Masukkan alasan ketidakhadiran..."><?= htmlspecialchars($rejected_attendance['keterangan'] ?? '') ?></textarea>
                                </div>

                                <!-- ══════════════════════════════════════════════════ -->
                                <!-- VALIDASI HADIR: GPS + WAJAH                       -->
                                <!-- ══════════════════════════════════════════════════ -->
                                <div id="hadir-validation" class="space-y-4">

                                    <!-- ── Step indicator ── -->
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="flex items-center gap-1.5">
                                            <span id="step1-badge" class="step-badge step-wait">1</span>
                                            <span class="text-xs font-medium text-gray-600">GPS</span>
                                        </div>
                                        <div class="flex-1 h-px bg-gray-200 min-w-[20px]"></div>
                                        <div class="flex items-center gap-1.5">
                                            <span id="step2-badge" class="step-badge step-wait">2</span>
                                            <span class="text-xs font-medium text-gray-600">Wajah</span>
                                        </div>
                                        <div class="flex-1 h-px bg-gray-200 min-w-[20px]"></div>
                                        <div class="flex items-center gap-1.5">
                                            <span id="step3-badge" class="step-badge step-wait">3</span>
                                            <span class="text-xs font-medium text-gray-600">Foto</span>
                                        </div>
                                    </div>

                                    <!-- ── GPS Status Card ── -->
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                                        <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-map-marker-alt text-violet-500"></i>
                                                <span class="text-sm font-semibold text-gray-700">Validasi Lokasi</span>
                                            </div>
                                            <span id="gps-pill" class="gps-pill gps-wait">
                                                <i class="fas fa-spinner fa-spin"></i> Mendeteksi GPS…
                                            </span>
                                        </div>
                                        <div id="gps-detail" class="text-xs text-gray-500 space-y-1 hidden">
                                            <p>Lat: <span id="gps-lat">–</span> | Lng: <span id="gps-lng">–</span></p>
                                            <p>Jarak ke sekolah: <span id="gps-dist" class="font-semibold">–</span></p>
                                            <p>Radius diizinkan: <span class="font-semibold"><?= $max_radius ?> meter</span></p>
                                        </div>
                                        <button type="button" id="btn-refresh-gps"
                                            class="mt-2 text-xs text-violet-500 hover:text-violet-700 flex items-center gap-1">
                                            <i class="fas fa-redo-alt text-[10px]"></i> Perbarui lokasi
                                        </button>
                                        <input type="hidden" name="latitude" id="inp-lat">
                                        <input type="hidden" name="longitude" id="inp-lng">
                                    </div>

                                    <!-- ── Kamera + Face Detection ── -->
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                                        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-camera text-violet-500"></i>
                                                <span class="text-sm font-semibold text-gray-700">Verifikasi Wajah</span>
                                            </div>
                                            <span id="face-pill" class="gps-pill gps-wait">
                                                <i class="fas fa-spinner fa-spin"></i> Memuat model…
                                            </span>
                                        </div>

                                        <!-- Video wrapper -->
                                        <div class="relative rounded-xl overflow-hidden bg-black border-2 border-transparent" id="camera-ring">
                                            <video id="camera-preview" class="w-full h-48 lg:h-64 object-cover" autoplay playsinline muted></video>
                                            <canvas id="face-canvas" class="w-full h-48 lg:h-64" style="position:absolute;top:0;left:0;pointer-events:none;"></canvas>

                                            <!-- Foto hasil capture -->
                                            <div id="camera-overlay" class="hidden absolute inset-0 bg-black flex items-center justify-center">
                                                <img id="camera-result" class="max-h-full rounded-xl" src="" alt="Foto">
                                            </div>

                                            <!-- Loading kamera -->
                                            <div id="camera-loading" class="absolute inset-0 bg-black/80 flex flex-col items-center justify-center">
                                                <div class="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-purple-400"></div>
                                                <p class="text-xs text-gray-300 mt-2">Memuat kamera…</p>
                                            </div>
                                        </div>

                                        <!-- Info face detection -->
                                        <div id="face-info" class="mt-2 text-xs text-gray-500 min-h-[18px]"></div>

                                        <!-- Kontrol kamera -->
                                        <div class="flex flex-wrap gap-2 mt-3">
                                            <button type="button" id="switch-camera-btn"
                                                class="px-3 py-2 bg-white border border-gray-200 hover:bg-gray-50 rounded-lg text-xs flex items-center gap-1 transition-colors">
                                                <i class="fas fa-sync"></i> Ganti Kamera
                                            </button>
                                            <button type="button" id="capture-btn" disabled
                                                class="px-3 py-2 bg-violet-600 hover:bg-violet-700 disabled:bg-gray-300 disabled:cursor-not-allowed rounded-lg text-xs text-white flex items-center gap-1 transition-colors">
                                                <i class="fas fa-camera"></i> Ambil Foto
                                            </button>
                                            <button type="button" id="retake-btn"
                                                class="hidden px-3 py-2 bg-white border border-gray-200 hover:bg-gray-50 rounded-lg text-xs flex items-center gap-1 transition-colors">
                                                <i class="fas fa-redo"></i> Ulangi
                                            </button>
                                        </div>

                                        <input type="hidden" name="camera_image_data" id="camera-image-data">
                                        <input type="hidden" name="face_verified" id="face-verified-input">
                                    </div>
                                </div><!-- /hadir-validation -->

                                <!-- Submit -->
                                <div class="pt-1">
                                    <button type="submit" name="submit_attendance" id="btn-submit"
                                        class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 rounded-xl font-medium text-white text-sm flex items-center gap-2 transition-colors">
                                        <i class="fas fa-paper-plane"></i>
                                        <?= $rejected_attendance ? 'Kirim Ulang Absensi' : 'Kirim Absensi' ?>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div><!-- /form col -->

                    <!-- ════ SUMMARY ════ -->
                    <div class="glass rounded-xl p-4 lg:p-6">
                        <h3 class="font-semibold text-base lg:text-lg mb-4 flex items-center gap-2">
                            <i class="fas fa-chart-pie text-violet-600"></i>Ringkasan Kehadiran
                        </h3>
                        <div class="mb-4">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Persentase Kehadiran</span>
                                <span><?= $attendance_percentage ?>%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-gradient-to-r from-purple-500 to-indigo-500 h-2 rounded-full"
                                    style="width:<?= min($attendance_percentage, 100) ?>%"></div>
                            </div>
                        </div>
                        <div class="aspect-square mb-4"><canvas id="attendanceChart"></canvas></div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <?php
                            $statRows = [
                                ['Hadir',     'bg-green-500',  $attendance_summary['hadir']],
                                ['Sakit',     'bg-yellow-500', $attendance_summary['sakit']],
                                ['Izin',      'bg-purple-500', $attendance_summary['izin']],
                                ['Terlambat', 'bg-orange-500', $attendance_summary['terlambat']]
                            ];
                            foreach ($statRows as [$label, $bg, $val]):
                            ?>
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full <?= $bg ?>"></span><?= $label ?>
                                    </div>
                                    <span class="font-semibold"><?= $val ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="flex justify-between items-center col-span-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>Alpha
                                </div>
                                <span class="font-semibold"><?= $attendance_summary['alpha'] ?></span>
                            </div>
                        </div>
                    </div>

                </div><!-- /main grid -->

                <!-- Pending & Calendar -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">

                    <!-- Pending -->
                    <div class="glass rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-violet-50 to-indigo-50 p-4 border-b border-gray-100">
                            <h3 class="font-semibold text-sm flex items-center gap-2">
                                <i class="fas fa-clock text-violet-600"></i>Menunggu Persetujuan
                            </h3>
                        </div>
                        <?php if ($pending_requests): ?>
                            <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                                <?php foreach ($pending_requests as $req):
                                    $sc2 = [
                                        'Hadir' => 'bg-green-100 text-green-700',
                                        'Sakit' => 'bg-yellow-100 text-yellow-700',
                                        'Izin'  => 'bg-purple-100 text-purple-700'
                                    ];
                                ?>
                                    <div class="p-4 hover:bg-gray-50 transition-colors">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $sc2[$req['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                                <?= htmlspecialchars($req['status']) ?>
                                            </span>
                                            <button onclick="showConfirmationModal(<?= $req['id'] ?>)"
                                                class="text-red-400 hover:text-red-600 text-xs flex items-center gap-1">
                                                <i class="fas fa-trash-alt"></i> Batalkan
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            <i class="far fa-calendar-alt mr-1"></i><?= date('d M Y', strtotime($req['tanggal'])) ?>
                                            <?php if ($req['created_at']): ?>
                                                &nbsp;·&nbsp;<i class="far fa-clock mr-1"></i><?= date('H:i', strtotime($req['created_at'])) ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($req['keterangan']): ?>
                                            <p class="text-xs text-gray-600 mt-1 line-clamp-2"><?= htmlspecialchars($req['keterangan']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-10 text-center">
                                <i class="fas fa-check-circle text-3xl text-gray-300 mb-3 block"></i>
                                <p class="text-sm text-gray-500">Tidak ada permintaan yang tertunda.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Calendar -->
                    <div class="glass rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-violet-50 to-indigo-50 p-4 border-b border-gray-100">
                            <h3 class="font-semibold text-sm flex items-center gap-2">
                                <i class="fas fa-calendar-alt text-violet-600"></i>Kehadiran Bulan Ini
                            </h3>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-7 gap-1 text-center mb-2">
                                <?php foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $d): ?>
                                    <div class="text-xs font-medium text-gray-400"><?= $d ?></div>
                                <?php endforeach; ?>
                            </div>
                            <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
                            <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-gray-100 text-xs">
                                <?php foreach (
                                    [
                                        ['bg-green-500',  'Hadir'],
                                        ['bg-yellow-500', 'Sakit'],
                                        ['bg-purple-500', 'Izin'],
                                        ['bg-orange-500', 'Terlambat'],
                                        ['bg-red-500',    'Alpha'],
                                        ['bg-gray-200',   'Belum']
                                    ] as [$bg, $lb]
                                ): ?>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full <?= $bg ?>"></span><?= $lb ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /max-w -->
        </div>
    </main>

    <!-- ═══ MODAL KONFIRMASI BATAL ═══ -->
    <div id="confirmationModal" class="fixed inset-0 bg-black/40 z-50 hidden flex items-center justify-center p-4">
        <div class="glass rounded-xl max-w-sm w-full p-6 shadow-2xl">
            <div class="h-12 w-12 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-trash text-red-500 text-lg"></i>
            </div>
            <h3 class="text-base font-bold text-center mb-1">Batalkan Pengajuan?</h3>
            <p class="text-sm text-gray-500 text-center mb-5">Pengajuan absensi yang dibatalkan tidak dapat dikembalikan.</p>
            <form method="POST" id="cancelForm">
                <input type="hidden" name="request_id" id="requestIdInput">
                <div class="flex gap-3">
                    <button type="button" onclick="closeConfirmationModal()"
                        class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="cancel_request"
                        class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors">
                        Ya, Batalkan
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        // ════════════════════════════════════════════════════════════════════════════
        // KONSTANTA SEKOLAH — ✅ FIX: disinkronkan dari satu variabel PHP
        // ════════════════════════════════════════════════════════════════════════════
        const SCHOOL_LAT = <?= $school_lat ?>;
        const SCHOOL_LNG = <?= $school_lng ?>;
        const MAX_RADIUS = <?= $max_radius ?>;

        // ════════════════════════════════════════════════════════════════════════════
        // CLOCK
        // ════════════════════════════════════════════════════════════════════════════
        function tick() {
            const t = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }) + ' WIB';
            const el = document.getElementById('current-time');
            if (el) el.textContent = t;
        }

        function tickM() {
            const t = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            const el = document.getElementById('current-time-mobile');
            if (el) el.textContent = t;
        }
        setInterval(tick, 1000);
        setInterval(tickM, 60000);
        tick();
        tickM();

        // ════════════════════════════════════════════════════════════════════════════
        // SIDEBAR
        // ════════════════════════════════════════════════════════════════════════════
        function toggleSidebar() {
            const sb = document.getElementById('sidebar'),
                ov = document.getElementById('mobile-overlay');
            const open = sb.classList.contains('-translate-x-full');
            sb.classList.toggle('-translate-x-full', !open);
            ov.classList.toggle('hidden', !open);
            document.body.style.overflow = open ? 'hidden' : '';
        }

        // ════════════════════════════════════════════════════════════════════════════
        // MODAL
        // ════════════════════════════════════════════════════════════════════════════
        function showConfirmationModal(id) {
            document.getElementById('requestIdInput').value = id;
            document.getElementById('confirmationModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeConfirmationModal() {
            document.getElementById('confirmationModal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        document.getElementById('confirmationModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeConfirmationModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeConfirmationModal();
        });

        // ════════════════════════════════════════════════════════════════════════════
        // HAVERSINE
        // ════════════════════════════════════════════════════════════════════════════
        function haversine(lat1, lng1, lat2, lng2) {
            const R = 6371000,
                toRad = d => d * Math.PI / 180;
            const dLat = toRad(lat2 - lat1),
                dLng = toRad(lng2 - lng1);
            const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
            return 2 * R * Math.asin(Math.sqrt(a));
        }

        // ════════════════════════════════════════════════════════════════════════════
        // GPS
        // ════════════════════════════════════════════════════════════════════════════
        let gpsValid = false;

        function setStep1(state) {
            const b = document.getElementById('step1-badge');
            b.className = 'step-badge step-' + state;
            b.textContent = state === 'done' ? '✓' : state === 'fail' ? '✗' : '1';
        }

        function getGPS() {
            gpsValid = false;
            setStep1('active');
            const pill = document.getElementById('gps-pill');
            pill.className = 'gps-pill gps-wait';
            pill.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendeteksi GPS…';

            if (!navigator.geolocation) {
                pill.className = 'gps-pill gps-err';
                pill.innerHTML = '<i class="fas fa-times-circle"></i> GPS tidak didukung';
                setStep1('fail');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                pos => {
                    const lat = pos.coords.latitude,
                        lng = pos.coords.longitude;
                    const dist = haversine(lat, lng, SCHOOL_LAT, SCHOOL_LNG);

                    document.getElementById('inp-lat').value = lat;
                    document.getElementById('inp-lng').value = lng;
                    document.getElementById('gps-lat').textContent = lat.toFixed(6);
                    document.getElementById('gps-lng').textContent = lng.toFixed(6);
                    document.getElementById('gps-dist').textContent = Math.round(dist) + ' meter';
                    document.getElementById('gps-detail').classList.remove('hidden');

                    if (dist <= MAX_RADIUS) {
                        pill.className = 'gps-pill gps-ok';
                        pill.innerHTML = `<i class="fas fa-check-circle"></i> Dalam radius (${Math.round(dist)}m)`;
                        gpsValid = true;
                        setStep1('done');
                    } else {
                        pill.className = 'gps-pill gps-err';
                        pill.innerHTML = `<i class="fas fa-times-circle"></i> Di luar radius (${Math.round(dist)}m)`;
                        gpsValid = false;
                        setStep1('fail');
                    }
                },
                err => {
                    pill.className = 'gps-pill gps-err';
                    const msgs = {
                        1: 'Izin lokasi ditolak',
                        2: 'Posisi tidak tersedia',
                        3: 'Timeout GPS'
                    };
                    pill.innerHTML = `<i class="fas fa-times-circle"></i> ${msgs[err.code] || 'Error GPS'}`;
                    setStep1('fail');
                }, {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0
                }
            );
        }

        document.getElementById('btn-refresh-gps')?.addEventListener('click', getGPS);

        // ════════════════════════════════════════════════════════════════════════════
        // KAMERA
        // ════════════════════════════════════════════════════════════════════════════
        let stream = null,
            facingMode = 'user';

        function getVideoEl() {
            return document.getElementById('camera-preview');
        }

        function getCanvasEl() {
            return document.getElementById('face-canvas');
        }

        function startCamera() {
            const vid = getVideoEl();
            const load = document.getElementById('camera-loading');
            if (load) load.classList.remove('hidden');

            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }

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
                    vid.srcObject = ms;
                    vid.play().then(() => {
                        if (load) load.classList.add('hidden');
                    });
                })
                .catch(err => {
                    if (load) load.classList.add('hidden');
                    const msgs = {
                        NotAllowedError: 'Izin kamera ditolak. Izinkan akses kamera di browser.',
                        NotFoundError: 'Kamera tidak ditemukan.',
                        NotReadableError: 'Kamera sedang dipakai aplikasi lain.'
                    };
                    document.getElementById('face-info').textContent = msgs[err.name] || 'Gagal akses kamera: ' + err.message;
                    console.error('Camera error:', err);
                });
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }
        }

        document.getElementById('switch-camera-btn')?.addEventListener('click', () => {
            facingMode = facingMode === 'user' ? 'environment' : 'user';
            faceVerified = false;
            document.getElementById('face-verified-input').value = '';
            document.getElementById('capture-btn').disabled = true;
            setStep2('active');
            startCamera();
        });

        document.getElementById('capture-btn')?.addEventListener('click', () => {
            const vid = getVideoEl();

            // Gunakan canvas tersembunyi untuk capture agar ukurannya sesuai resolusi asli
            let canvas = document.getElementById('camera-canvas');
            if (!canvas) {
                canvas = document.createElement('canvas');
                canvas.id = 'camera-canvas';
                canvas.style.display = 'none';
                document.body.appendChild(canvas);
            }
            canvas.width = vid.videoWidth;
            canvas.height = vid.videoHeight;

            const ctx2d = canvas.getContext('2d');
            ctx2d.drawImage(vid, 0, 0);
            const img = canvas.toDataURL('image/jpeg', 0.92);

            document.getElementById('camera-result').src = img;
            document.getElementById('camera-image-data').value = img;
            document.getElementById('camera-overlay').classList.remove('hidden');
            document.getElementById('capture-btn').classList.add('hidden');
            document.getElementById('retake-btn').classList.remove('hidden');
            setStep3('done');
            stopCamera();
            clearInterval(faceInterval);
        });

        document.getElementById('retake-btn')?.addEventListener('click', () => {
            document.getElementById('camera-overlay').classList.add('hidden');
            document.getElementById('capture-btn').classList.remove('hidden');
            document.getElementById('retake-btn').classList.add('hidden');
            document.getElementById('camera-image-data').value = '';
            document.getElementById('face-verified-input').value = '';
            faceVerified = false;
            setStep2('active');
            setStep3('wait');
            document.getElementById('capture-btn').disabled = true;
            startCamera();
            startFaceLoop();
        });

        // ════════════════════════════════════════════════════════════════════════════
        // ✅ FACE DETECTION (face-api.js) — FIXED
        // ════════════════════════════════════════════════════════════════════════════
        let faceVerified = false;
        let faceInterval = null;
        let faceModelLoaded = false;

        // ✅ FIX: dua sumber fallback jika CDN pertama gagal
        const MODEL_URLS = [
            'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights',
            'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights'
        ];

        function setStep2(state) {
            const b = document.getElementById('step2-badge');
            b.className = 'step-badge step-' + state;
            b.textContent = state === 'done' ? '✓' : state === 'fail' ? '✗' : '2';
        }

        function setStep3(state) {
            const b = document.getElementById('step3-badge');
            b.className = 'step-badge step-' + state;
            b.textContent = state === 'done' ? '✓' : state === 'fail' ? '✗' : '3';
        }

        async function loadFaceModels() {
            const pill = document.getElementById('face-pill');
            pill.className = 'gps-pill gps-wait';
            pill.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat model wajah…';

            // ✅ FIX: Coba load dari beberapa URL sebagai fallback
            for (const url of MODEL_URLS) {
                try {
                    await Promise.all([
                        faceapi.nets.tinyFaceDetector.loadFromUri(url),
                        faceapi.nets.faceLandmark68TinyNet.loadFromUri(url)
                    ]);
                    faceModelLoaded = true;
                    pill.className = 'gps-pill gps-wait';
                    pill.innerHTML = '<i class="fas fa-eye"></i> Model siap — arahkan wajah Anda';
                    console.log('Face model loaded from:', url);
                    return; // sukses, hentikan loop
                } catch (e) {
                    console.warn('Gagal load model dari', url, e);
                }
            }

            // Semua URL gagal
            pill.className = 'gps-pill gps-err';
            pill.innerHTML = '<i class="fas fa-times-circle"></i> Gagal muat model — cek koneksi';
            document.getElementById('face-info').textContent = 'Model deteksi wajah gagal dimuat. Pastikan koneksi internet aktif.';
        }

        async function detectFace() {
            if (!faceModelLoaded || !stream) return;

            const vid = getVideoEl();
            // ✅ FIX: Pastikan video benar-benar siap dan memiliki dimensi
            if (vid.readyState < 2 || vid.videoWidth === 0 || vid.videoHeight === 0) return;

            // ✅ FIX: inputSize dinaikkan ke 320 & scoreThreshold diturunkan ke 0.4
            const options = new faceapi.TinyFaceDetectorOptions({
                inputSize: 320,
                scoreThreshold: 0.4
            });

            try {
                const result = await faceapi
                    .detectSingleFace(vid, options)
                    .withFaceLandmarks(true);

                const canvas = getCanvasEl();
                // ✅ FIX: Sesuaikan ukuran canvas dengan dimensi video aktual
                canvas.width = vid.videoWidth;
                canvas.height = vid.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                const ring = document.getElementById('camera-ring');
                const pill = document.getElementById('face-pill');
                const info = document.getElementById('face-info');
                const capBtn = document.getElementById('capture-btn');

                if (result) {
                    // Gambar kotak deteksi wajah
                    const disp = {
                        width: vid.videoWidth,
                        height: vid.videoHeight
                    };
                    const resized = faceapi.resizeResults([result], disp);
                    faceapi.draw.drawDetections(canvas, resized);

                    const score = Math.round(result.detection.score * 100);
                    ring.className = ring.className.replace(/\b(detecting|face-ok|face-bad)\b/g, '').trim() + ' face-ok';
                    pill.className = 'gps-pill gps-ok';
                    pill.innerHTML = `<i class="fas fa-check-circle"></i> Wajah terdeteksi (${score}%)`;
                    info.textContent = `Kepercayaan: ${score}% — posisi wajah bagus! Tekan "Ambil Foto".`;
                    faceVerified = true;
                    document.getElementById('face-verified-input').value = '1';
                    capBtn.disabled = false;
                    setStep2('done');
                } else {
                    ring.className = ring.className.replace(/\b(detecting|face-ok|face-bad)\b/g, '').trim() + ' face-bad';
                    pill.className = 'gps-pill gps-err';
                    pill.innerHTML = '<i class="fas fa-times-circle"></i> Wajah tidak terdeteksi';
                    info.textContent = 'Hadapkan wajah ke kamera, pastikan pencahayaan cukup terang.';
                    faceVerified = false;
                    document.getElementById('face-verified-input').value = '';
                    capBtn.disabled = true;
                    setStep2('active');
                }
            } catch (e) {
                console.warn('detectFace error:', e);
            }
        }

        function startFaceLoop() {
            clearInterval(faceInterval);
            // ✅ FIX: Interval 600ms agar lebih responsif
            faceInterval = setInterval(detectFace, 600);
        }

        // ════════════════════════════════════════════════════════════════════════════
        // STATUS RADIO CHANGE
        // ════════════════════════════════════════════════════════════════════════════
        function onStatusChange(val) {
            const hadirDiv = document.getElementById('hadir-validation');
            const ketBox = document.getElementById('keteranganBox');
            if (val === 'Hadir') {
                hadirDiv.classList.remove('hidden');
                ketBox.classList.add('hidden');
                if (!stream) startCamera();
                startFaceLoop();
                getGPS();
            } else {
                hadirDiv.classList.add('hidden');
                ketBox.classList.remove('hidden');
                stopCamera();
                clearInterval(faceInterval);
                document.getElementById('inp-lat').value = '';
                document.getElementById('inp-lng').value = '';
            }
        }

        // ════════════════════════════════════════════════════════════════════════════
        // FORM SUBMIT VALIDATION
        // ════════════════════════════════════════════════════════════════════════════
        document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
            const status = document.querySelector('input[name="status"]:checked')?.value;
            if (status === 'Hadir') {
                if (!gpsValid) {
                    e.preventDefault();
                    alert('⚠ Lokasi GPS Anda tidak valid atau di luar radius sekolah.\nAktifkan GPS dan tekan "Perbarui lokasi".');
                    return;
                }
                if (!faceVerified) {
                    e.preventDefault();
                    alert('⚠ Wajah belum terdeteksi. Pastikan wajah Anda terlihat jelas di kamera.');
                    return;
                }
                if (!document.getElementById('camera-image-data').value) {
                    e.preventDefault();
                    alert('⚠ Anda belum mengambil foto. Tekan tombol "Ambil Foto" terlebih dahulu.');
                    return;
                }
            }
        });

        // ════════════════════════════════════════════════════════════════════════════
        // INIT
        // ════════════════════════════════════════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', async () => {

            // ── Chart ──────────────────────────────────────────────────────────────
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
                                backgroundColor: 'rgba(17,24,39,.9)',
                                padding: 10,
                                callbacks: {
                                    label: c => `${c.label}: ${c.raw} hari`
                                }
                            }
                        }
                    }
                });
            }

            // ── Calendar ───────────────────────────────────────────────────────────
            generateCalendar();

            // ── Attach radio change listeners ──────────────────────────────────────
            document.querySelectorAll('input[name="status"]').forEach(inp => {
                inp.addEventListener('change', async () => {
                    // ✅ FIX: Load model saat pertama kali status Hadir dipilih
                    if (inp.value === 'Hadir' && !faceModelLoaded) {
                        await loadFaceModels();
                    }
                    onStatusChange(inp.value);
                });
            });

            // ── Auto init jika default Hadir terpilih ──────────────────────────────
            const defaultHadir = document.querySelector('input[name="status"][value="Hadir"]:checked');
            if (defaultHadir) {
                await loadFaceModels();
                startCamera();
                startFaceLoop();
                getGPS();
            }
        });

        window.addEventListener('beforeunload', () => {
            stopCamera();
            clearInterval(faceInterval);
        });

        // ════════════════════════════════════════════════════════════════════════════
        // CALENDAR
        // ════════════════════════════════════════════════════════════════════════════
        function generateCalendar() {
            const now = new Date(),
                year = now.getFullYear(),
                month = now.getMonth();
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
                el.className = 'h-10';
                grid.appendChild(el);
            }
            for (let d = 1; d <= daysInMonth; d++) {
                const cellDate = new Date(year, month, d);
                const isToday = cellDate.toDateString() === todayStr;
                const isPast = cellDate < midnight;

                const cell = document.createElement('div');
                cell.className = 'h-10 flex flex-col items-center justify-center rounded-lg hover:bg-gray-50 transition-colors' +
                    (isToday ? ' ring-2 ring-purple-400' : '');

                const num = document.createElement('span');
                num.className = 'text-xs' + (isToday ? ' font-bold text-violet-600' : '');
                num.textContent = d;
                cell.appendChild(num);

                if (map[d] || isPast) {
                    const dot = document.createElement('span');
                    dot.className = 'h-2 w-2 rounded-full mt-0.5 ' + (colors[map[d]] || 'bg-gray-200');
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
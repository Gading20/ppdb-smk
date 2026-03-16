<?php
require_once '../../config/database.php';

if (!isset($_SESSION['walikelas_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$kelas   = $_SESSION['walikelas_kelas']   ?? '';
$jurusan = $_SESSION['walikelas_jurusan'] ?? '';

// Initialize response data
$response = [
    'stats'         => [],
    'weeklyStats'   => [],
    'notifications' => [],
];

// Get today's statistics
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$statuses        = ['hadir', 'sakit', 'izin', 'terlambat', 'alpha'];
$today_stats     = array_fill_keys($statuses, 0);
$yesterday_stats = array_fill_keys($statuses, 0);

// Today counts – filter by kelas & jurusan
$stmt = $conn->prepare(
    "SELECT a.status, COUNT(*) as count
     FROM absensi a JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :today AND a.approval_status = 'Approved'
       AND s.kelas = :kelas AND s.jurusan = :jurusan
     GROUP BY a.status"
);
$stmt->execute(['today' => $today, 'kelas' => $kelas, 'jurusan' => $jurusan]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $s = strtolower($row['status']);
    if (isset($today_stats[$s])) $today_stats[$s] = (int)$row['count'];
}

// Yesterday counts – filter by kelas & jurusan
$stmt = $conn->prepare(
    "SELECT a.status, COUNT(*) as count
     FROM absensi a JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal = :yesterday AND a.approval_status = 'Approved'
       AND s.kelas = :kelas AND s.jurusan = :jurusan
     GROUP BY a.status"
);
$stmt->execute(['yesterday' => $yesterday, 'kelas' => $kelas, 'jurusan' => $jurusan]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $s = strtolower($row['status']);
    if (isset($yesterday_stats[$s])) $yesterday_stats[$s] = (int)$row['count'];
}

// Percentage changes
foreach ($statuses as $status) {
    $pct = 0;
    if ($yesterday_stats[$status] > 0) {
        $pct = round((($today_stats[$status] - $yesterday_stats[$status]) / $yesterday_stats[$status]) * 100);
    } elseif ($today_stats[$status] > 0) {
        $pct = 100;
    }
    $response['stats'][$status] = [
        'count'             => $today_stats[$status],
        'yesterday'         => $yesterday_stats[$status],
        'percentage_change' => $pct
    ];
}

// Weekly stats – filter by kelas & jurusan
$stmt = $conn->prepare(
    "SELECT DATE(a.tanggal) as date,
            MIN(DATE_FORMAT(a.tanggal,'%d %b')) as date_label,
            a.status, COUNT(*) as count
     FROM absensi a JOIN siswa s ON a.siswa_id = s.id
     WHERE a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
       AND a.approval_status = 'Approved'
       AND s.kelas = :kelas AND s.jurusan = :jurusan
     GROUP BY DATE(a.tanggal), a.status
     ORDER BY date ASC"
);
$stmt->execute(['kelas' => $kelas, 'jurusan' => $jurusan]);
$response['weeklyStats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending notifications – filter by kelas & jurusan
$stmt = $conn->prepare(
    "SELECT a.id, s.nama_lengkap, s.foto_profil, a.status, a.created_at, a.bukti_foto, a.keterangan
     FROM absensi a JOIN siswa s ON a.siswa_id = s.id
     WHERE a.approval_status = 'Pending'
       AND s.kelas = :kelas AND s.jurusan = :jurusan
     ORDER BY a.created_at DESC
     LIMIT 5"
);
$stmt->execute(['kelas' => $kelas, 'jurusan' => $jurusan]);
$response['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($response);

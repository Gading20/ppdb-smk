<?php
// get_pelanggaran_siswa.php
// AJAX endpoint: kembalikan pelanggaran siswa
// ?siswa_id=X              → [{jenis, poin}, ...]         (mode lama, untuk index)
// ?siswa_id=X&mode=by_desc → [{deskripsi_id, kode, nama, jenis, poin}, ...] (mode baru)

require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([]);
    exit();
}

$siswa_id = isset($_GET['siswa_id']) ? (int) $_GET['siswa_id'] : 0;
$mode = $_GET['mode'] ?? 'default';

if (!$siswa_id) {
    echo json_encode([]);
    exit();
}

if ($mode === 'by_desc') {
    // Mode baru: join dengan tabel deskripsi_pelanggaran, group by deskripsi_id
    $sql = "SELECT 
                p.deskripsi_id,
                d.kode,
                d.nama,
                p.jenis_pelanggaran AS jenis,
                p.poin
            FROM pelanggaran p
            LEFT JOIN deskripsi_pelanggaran d ON p.deskripsi_id = d.id
            WHERE p.siswa_id = :siswa_id
            ORDER BY FIELD(p.jenis_pelanggaran, 'Berat', 'Sedang', 'Ringan'), d.kode";
} else {
    // Mode lama: group by jenis (untuk kompatibilitas index.php)
    $sql = "SELECT jenis_pelanggaran AS jenis, poin
            FROM pelanggaran
            WHERE siswa_id = :siswa_id
            ORDER BY FIELD(jenis_pelanggaran, 'Berat', 'Sedang', 'Ringan')";
}

$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
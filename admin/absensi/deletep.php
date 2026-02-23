<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];

    try {
        $conn->beginTransaction();

        // Get pelanggaran details for activity log
        $sql = "SELECT p.siswa_id, s.nama_lengkap, p.jenis_pelanggaran 
                FROM pelanggaran p 
                JOIN siswa s ON p.siswa_id = s.id
                WHERE p.id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $pelanggaran = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pelanggaran) {
            // Hapus data pelanggaran
            $sql = "DELETE FROM pelanggaran WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['id' => $id]);

            // Log activity
            $description = "Admin menghapus pelanggaran " . $pelanggaran['jenis_pelanggaran'] . 
                           " untuk " . $pelanggaran['nama_lengkap'];

            $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                    VALUES ('admin', :admin_id, 'delete', :description)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'admin_id'    => $_SESSION['admin_id'],
                'description' => $description
            ]);

            $conn->commit();
            header("Location: pelanggaran.php?delete=success");
            exit();
        } else {
            throw new Exception("Data pelanggaran tidak ditemukan");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: pelanggaran.php?delete=error&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: pelanggaran.php");
    exit();
}
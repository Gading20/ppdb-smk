<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];

    try {
        $conn->beginTransaction();

        // Get konseling details for activity log
        $sql = "SELECT k.siswa_id, s.nama_lengkap, k.jenis_konseling 
                FROM konseling k 
                JOIN siswa s ON k.siswa_id = s.id
                WHERE k.id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $konseling = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($konseling) {
            // Hapus data konseling
            $sql = "DELETE FROM konseling WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['id' => $id]);

            // Log activity
            $description = "Admin menghapus konseling " . $konseling['jenis_konseling'] .
                           " untuk " . $konseling['nama_lengkap'];

            $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                    VALUES ('admin', :admin_id, 'delete', :description)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'admin_id'    => $_SESSION['admin_id'],
                'description' => $description
            ]);

            $conn->commit();
            header("Location: konseling.php?delete=success");
            exit();
        } else {
            throw new Exception("Data konseling tidak ditemukan");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: konseling.php?delete=error&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: konseling.php");
    exit();
}
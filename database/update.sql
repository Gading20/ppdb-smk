-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: absensi_siswa
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- --------------------------------------------------------
--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Administrator'),(2,'siswa','Siswa'),(3,'kepsek','Kepala Sekolah'),(4,'wali_kelas','Wali Kelas');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

-- --------------------------------------------------------
--
-- Table structure for table `users`
-- Menggantikan table admin, kepsek (auth). Siswa & wali_kelas tetap ada, linked via user_id.
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','siswa','kepsek','wali_kelas') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `foto_profil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'assets/default/photo-profile.png',
  `last_login` datetime DEFAULT NULL,
  `nip` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Untuk kepsek dan wali_kelas',
  `nis` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Untuk siswa',
  `kelas` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Untuk wali_kelas (nama jurusan) & siswa (10/11/12)',
  `tingkat` enum('10','11','12') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Untuk wali_kelas & siswa',
  `rombel` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Contoh: TKR 1',
  `jurusan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Untuk wali_kelas & siswa',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `nis` (`nis`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
-- admin (id=1), kepsek (id=2), wali_kelas (id=3..6), siswa (id=10..)
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
-- Admin
(1,'admin','admin@smkn40-jkt.sch.id','admin123','Administrator','admin','uploads/admin/admin_1_1741506108.jpg','2026-03-27 20:07:58',NULL,NULL,NULL,NULL,NULL,NULL,'2025-03-03 08:23:13','2026-03-27 13:07:58'),
-- Kepsek
(2,'kepsek','kepsek@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Kepala Sekolah','kepsek','assets/default/photo-profile.png','2026-03-16 15:54:16','196501011990031001',NULL,NULL,NULL,NULL,NULL,'2026-03-11 00:23:03','2026-03-16 08:54:16'),
-- Wali Kelas (id=3..6) — user_id akan digunakan oleh tabel wali_kelas
(3,'Supri','wk.xa@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X TKR 1','wali_kelas','assets/default/photo-profile.png',NULL,'197001011995032001',NULL,'TKR','10','TKR 1','TKR','2026-03-11 00:17:56','2026-03-16 08:07:59'),
(4,'Gading','wk.xb@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X TKJ 1','wali_kelas','assets/default/photo-profile.png',NULL,'197002021995032002',NULL,'TKJ','10','TKJ 1','TKJ','2026-03-11 00:17:56','2026-03-16 08:07:59'),
(5,'Emma','wk.xi@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X MP 1','wali_kelas','assets/default/photo-profile.png',NULL,'197503031998031003',NULL,'MP','10','MP 1','MP','2026-03-11 00:17:56','2026-03-16 08:07:59'),
(6,'Indrawan','wk.xii@sekolah.sch.id','password','Wali Kelas X TSM 1','wali_kelas','assets/default/photo-profile.png','2026-03-16 15:32:11','197804042000042004',NULL,'TSM','10','TSM 1','TSM','2026-03-11 00:17:56','2026-03-16 08:32:11'),
-- Siswa (id=10..) — NIS disimpan di kolom nis
(10,'budi.santoso','budi@siswa.smkn40jkt.sch.id','siswa_2024002','Budi Santoso','siswa','uploads/siswa/siswa_2_1741098707.png',NULL,NULL,'2024002','10',NULL,NULL,'RPL','2025-03-03 08:24:03','2025-03-04 14:31:47'),
(11,'cindy.amelia','cindy@siswa.smkn40jkt.sch.id','siswa_2024003','Cindy Amelia','siswa','assets/default/photo-profile.png',NULL,NULL,'2024003','10',NULL,NULL,'RPL','2025-03-03 08:24:03','2025-03-03 08:24:03'),
(12,'diana.putri','diana@siswa.smkn40jkt.sch.id','diana_2024004','Diana Putri','siswa','assets/default/photo-profile.png',NULL,NULL,'2024004','10',NULL,NULL,'DKV','2025-03-03 08:24:03','2025-03-03 08:24:03'),
(13,'eko.prasetyo','eko@siswa.smkn40jkt.sch.id','eko_2024005','Eko Prasetyo','siswa','assets/default/photo-profile.png',NULL,NULL,'2024005','10',NULL,NULL,'DKV','2025-03-03 08:24:03','2025-03-03 08:24:03'),
(14,'fani.azahra','fani@siswa.smkn40jkt.sch.id','fani_2024006','Fani Azahra','siswa','assets/default/photo-profile.png',NULL,NULL,'2024006','10',NULL,NULL,'DKV','2025-03-03 08:24:03','2025-03-03 08:24:03'),
(15,'galih.pratama','galih@siswa.smkn40jkt.sch.id','siswa_2024007','Galih Pratama','siswa','assets/default/photo-profile.png',NULL,NULL,'2024007','10',NULL,NULL,'AK','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(16,'hana.safira','hana@siswa.smkn40jkt.sch.id','siswa_2024008','Hana Safira','siswa','assets/default/photo-profile.png',NULL,NULL,'2024008','10',NULL,NULL,'AK','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(17,'indra.kusuma','indra@siswa.smkn40jkt.sch.id','siswa_2024009','Indra Kusuma','siswa','assets/default/photo-profile.png',NULL,NULL,'2024009','10',NULL,NULL,'AK','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(18,'jasmine.putri','jasmine@siswa.smkn40jkt.sch.id','siswa_2024010','Jasmine Putri','siswa','assets/default/photo-profile.png',NULL,NULL,'2024010','10',NULL,NULL,'BR','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(19,'kevin.wijaya','kevin@siswa.smkn40jkt.sch.id','siswa_2024011','Kevin Wijaya','siswa','assets/default/photo-profile.png',NULL,NULL,'2024011','10',NULL,NULL,'BR','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(20,'luna.sari','luna@siswa.smkn40jkt.sch.id','siswa_2024012','Luna Sari','siswa','assets/default/photo-profile.png',NULL,NULL,'2024012','10',NULL,NULL,'BR','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(21,'mario.teguh','mario@siswa.smkn40jkt.sch.id','siswa_2024013','Mario Teguh','siswa','assets/default/photo-profile.png',NULL,NULL,'2024013','10',NULL,NULL,'MP','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(22,'nina.amalia','nina@siswa.smkn40jkt.sch.id','siswa_2024014','Nina Amalia','siswa','assets/default/photo-profile.png',NULL,NULL,'2024014','10',NULL,NULL,'MP','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(23,'oscar.putra','oscar@siswa.smkn40jkt.sch.id','siswa_2024015','Oscar Putra','siswa','assets/default/photo-profile.png',NULL,NULL,'2024015','10',NULL,NULL,'MP','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(24,'qori.hidayat','qori@siswa.smkn40jkt.sch.id','siswa_2023002','Qori Hidayat','siswa','assets/default/photo-profile.png',NULL,NULL,'2023002','11',NULL,NULL,'RPL','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(25,'rama.putra','rama@siswa.smkn40jkt.sch.id','siswa_2023003','Rama Putra','siswa','assets/default/photo-profile.png',NULL,NULL,'2023003','11',NULL,NULL,'RPL','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(26,'ahmad.fadilah','ahmetkutanis8@gmail.com','siswa_2023001','Ahmad Fadilah','siswa','assets/default/photo-profile.png',NULL,NULL,'2023001','10',NULL,NULL,'RPL','2025-03-03 08:33:00','2025-03-03 08:33:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

-- --------------------------------------------------------
--
-- Table structure for table `absensi`
--

DROP TABLE IF EXISTS `absensi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absensi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `siswa_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `status` enum('Hadir','Sakit','Izin','Terlambat','Alpha') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `bukti_foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bukti_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `approval_status` enum('Pending','Approved','Rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `siswa_id` (`siswa_id`),
  KEY `tanggal` (`tanggal`),
  CONSTRAINT `fk_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `absensi` WRITE;
/*!40000 ALTER TABLE `absensi` DISABLE KEYS */;
INSERT INTO `absensi` VALUES (4,4,'2025-03-03','06:55:00','Hadir',NULL,NULL,NULL,'Approved','2025-03-03 01:24:04','2025-03-03 01:24:04'),(6,6,'2025-03-02','00:00:00','Izin',NULL,NULL,NULL,'Approved','2025-03-03 01:24:04','2025-03-09 07:39:09'),(7,7,'2025-03-01','07:30:00','Terlambat',NULL,NULL,NULL,'Approved','2025-03-03 01:24:04','2025-03-03 01:24:04'),(9,11,'2025-03-03','23:15:00','Hadir','',NULL,NULL,'Approved','2025-03-03 09:15:51','2025-03-03 09:15:51'),(31,2,'2025-03-04','22:44:00','Hadir','',NULL,NULL,'Approved','2025-03-04 08:44:10','2025-03-06 01:04:38'),(32,19,'2025-03-04','23:09:00','Hadir','',NULL,NULL,'Approved','2025-03-04 09:10:02','2025-03-04 09:10:02'),(33,14,'2025-03-05','18:48:00','Hadir','',NULL,NULL,'Approved','2025-03-05 11:48:28','2025-03-05 11:48:28'),(46,12,'2025-03-09','14:39:00','Hadir','aduhh',NULL,NULL,'Approved','2025-03-09 07:39:57','2026-03-16 09:32:08'),(51,19,'2026-02-20','16:19:15','Hadir','','uploads/bukti/bukti_19_20260220_161915.jpeg',NULL,'Approved','2026-02-20 09:19:15','2026-02-20 09:54:31'),(55,2,'2026-02-23','14:17:36','Hadir','','uploads/bukti/bukti_2_20260223_141736.jpeg',NULL,'Approved','2026-02-23 07:17:36','2026-02-23 07:17:46'),(57,2,'2026-03-16','13:49:52','Hadir','','uploads/bukti/bukti_2_20260316_134952.jpeg',NULL,'Approved','2026-03-16 06:49:52','2026-03-16 06:54:15');
/*!40000 ALTER TABLE `absensi` ENABLE KEYS */;
UNLOCK TABLES;

-- --------------------------------------------------------
--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','siswa','kepsek','wali_kelas') COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `activity_type` enum('login','logout','create','update','delete','approval','absensi') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_type` (`user_type`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=292 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (290,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 13:07:58'),(291,'admin',1,'logout','Admin Administrator logout dari sistem','2026-03-27 13:10:48');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

-- --------------------------------------------------------
--
-- Table structure for table `siswa`
-- TETAP ADA: digunakan oleh FK absensi, pelanggaran, konseling
-- Ditambah kolom user_id yang menunjuk ke tabel users
--

DROP TABLE IF EXISTS `siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL COMMENT 'FK ke tabel users',
  `nis` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kelas` enum('10','11','12') COLLATE utf8mb4_general_ci NOT NULL,
  `jurusan` enum('RPL','DKV','AK','BR','MP','TKR','TKJ','TSM','TKI','OTKP') COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `foto_profil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'assets/default/photo-profile.png',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nis` (`nis`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_siswa_user` (`user_id`),
  CONSTRAINT `fk_siswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `siswa` WRITE;
/*!40000 ALTER TABLE `siswa` DISABLE KEYS */;
INSERT INTO `siswa` VALUES
(2,10,'2024002','Budi Santoso','10','RPL','budi@siswa.smkn40jkt.sch.id','siswa_2024002','uploads/siswa/siswa_2_1741098707.png','2025-03-03 08:24:03','2025-03-04 14:31:47'),
(3,11,'2024003','Cindy Amelia','10','RPL','cindy@siswa.smkn40jkt.sch.id','siswa_2024003','assets/default/photo-profile.png','2025-03-03 08:24:03','2025-03-03 08:24:03'),
(4,12,'2024004','Diana Putri','10','DKV','diana@siswa.smkn40jkt.sch.id','diana_2024004','assets/default/photo-profile.png','2025-03-03 08:24:03','2025-03-03 08:24:03'),
(5,13,'2024005','Eko Prasetyo','10','DKV','eko@siswa.smkn40jkt.sch.id','eko_2024005','assets/default/photo-profile.png','2025-03-03 08:24:03','2025-03-03 08:24:03'),
(6,14,'2024006','Fani Azahra','10','DKV','fani@siswa.smkn40jkt.sch.id','fani_2024006','assets/default/photo-profile.png','2025-03-03 08:24:03','2025-03-03 08:24:03'),
(7,15,'2024007','Galih Pratama','10','AK','galih@siswa.smkn40jkt.sch.id','siswa_2024007','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(8,16,'2024008','Hana Safira','10','AK','hana@siswa.smkn40jkt.sch.id','siswa_2024008','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(9,17,'2024009','Indra Kusuma','10','AK','indra@siswa.smkn40jkt.sch.id','siswa_2024009','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(10,18,'2024010','Jasmine Putri','10','BR','jasmine@siswa.smkn40jkt.sch.id','siswa_2024010','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(11,19,'2024011','Kevin Wijaya','10','BR','kevin@siswa.smkn40jkt.sch.id','siswa_2024011','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(12,20,'2024012','Luna Sari','10','BR','luna@siswa.smkn40jkt.sch.id','siswa_2024012','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(13,21,'2024013','Mario Teguh','10','MP','mario@siswa.smkn40jkt.sch.id','siswa_2024013','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(14,22,'2024014','Nina Amalia','10','MP','nina@siswa.smkn40jkt.sch.id','siswa_2024014','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(15,23,'2024015','Oscar Putra','10','MP','oscar@siswa.smkn40jkt.sch.id','siswa_2024015','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(17,24,'2023002','Qori Hidayat','11','RPL','qori@siswa.smkn40jkt.sch.id','siswa_2023002','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(18,25,'2023003','Rama Putra','11','RPL','rama@siswa.smkn40jkt.sch.id','siswa_2023003','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),
(19,26,'2023001','Ahmad Fadilah','10','RPL','ahmetkutanis8@gmail.com','siswa_2023001','assets/default/photo-profile.png','2025-03-03 08:33:00','2025-03-03 08:33:00');
/*!40000 ALTER TABLE `siswa` ENABLE KEYS */;
UNLOCK TABLES;

-- --------------------------------------------------------
--
-- Table structure for table `wali_kelas`
-- TETAP ADA: menyimpan data kelas. Ditambah user_id FK ke users.
--

DROP TABLE IF EXISTS `wali_kelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wali_kelas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL COMMENT 'FK ke tabel users',
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `kelas` varchar(20) NOT NULL COMMENT 'Contoh: TKR, TKJ, MP',
  `tingkat` enum('10','11','12') DEFAULT '10',
  `rombel` varchar(30) DEFAULT NULL,
  `jurusan` varchar(50) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_wali_kelas_user` (`user_id`),
  CONSTRAINT `fk_wali_kelas_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `wali_kelas` WRITE;
/*!40000 ALTER TABLE `wali_kelas` DISABLE KEYS */;
INSERT INTO `wali_kelas` VALUES
(1,3,'Supri','wk.xa@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X TKR 1','197001011995032001','TKR','10','TKR 1','TKR',NULL,NULL,'2026-03-11 00:17:56','2026-03-16 08:07:59'),
(2,4,'Gading','wk.xb@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X TKJ 1','197002021995032002','TKJ','10','TKJ 1','TKJ',NULL,NULL,'2026-03-11 00:17:56','2026-03-16 08:07:59'),
(3,5,'Emma','wk.xi@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X MP 1','197503031998031003','MP','10','MP 1','MP',NULL,NULL,'2026-03-11 00:17:56','2026-03-16 08:07:59'),
(4,6,'Indrawan','wk.xii@sekolah.sch.id','password','Wali Kelas X TSM 1','197804042000042004','TSM','10','TSM 1','TSM',NULL,'2026-03-16 08:32:11','2026-03-11 00:17:56','2026-03-16 08:32:11');
/*!40000 ALTER TABLE `wali_kelas` ENABLE KEYS */;
UNLOCK TABLES;

-- --------------------------------------------------------
--
-- Table structure for table `kepsek`
-- CATATAN: Data kepsek sudah dimigrasikan ke tabel users (role='kepsek').
-- Tabel ini DIHAPUS karena sudah tidak diperlukan.
--

DROP TABLE IF EXISTS `kepsek`;

-- --------------------------------------------------------
--
-- Table structure for table `admin`
-- CATATAN: Data admin sudah dimigrasikan ke tabel users (role='admin').
-- Tabel ini DIHAPUS karena sudah tidak diperlukan.
--

DROP TABLE IF EXISTS `admin`;

-- --------------------------------------------------------
--
-- Table structure for table `deskripsi_pelanggaran`
--

DROP TABLE IF EXISTS `deskripsi_pelanggaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deskripsi_pelanggaran` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode` varchar(10) NOT NULL,
  `nama` text NOT NULL,
  `jenis` enum('Ringan','Sedang','Berat') NOT NULL,
  `poin_default` int NOT NULL,
  `tindakan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `deskripsi_pelanggaran` WRITE;
/*!40000 ALTER TABLE `deskripsi_pelanggaran` DISABLE KEYS */;
INSERT INTO `deskripsi_pelanggaran` VALUES (1,'P001','Membuang sampah sembarangan','Ringan',1,'Disuruh mengambil dan memasukkan ke tempat semestinya','2026-02-20 13:47:47'),(2,'P002','Memakai perhiasan berlebihan dan berdandan tidak sesuai norma','Ringan',1,'Diperingatkan dan dilepas untuk disimpan','2026-02-20 13:47:47'),(3,'P003','Memelihara kuku panjang','Ringan',1,'Dipotong','2026-02-20 13:47:47'),(4,'P004','Memakai jaket atau topi bukan identitas sekolah saat KBM','Ringan',1,'Dilepas dan diamankan guru','2026-02-20 13:47:47'),(5,'P005','Tidak memakai kaos kaki','Ringan',2,'Diperingatkan','2026-02-20 13:47:47'),(6,'P006','Sabuk tidak berlogo SMK Nurul Ulum Lebaksiu','Ringan',2,'Sabuk disita dan tidak dikembalikan','2026-02-20 13:47:47'),(7,'P007','Memakai sepatu selain warna hitam','Ringan',2,'Sepatu dilepas sebelah dan dikembalikan setelah seminggu','2026-02-20 13:47:47'),(8,'P008','Memakai sandal tanpa alasan sakit','Ringan',2,'Sandal disita dan tidak dikembalikan','2026-02-20 13:47:47'),(9,'P009','Memakai jilbab tidak sesuai ketentuan sekolah','Ringan',2,'Diperingatkan','2026-02-20 13:47:47'),(10,'P010','Tidak berseragam atau atribut tidak lengkap','Ringan',2,'Diminta melengkapi','2026-02-20 13:47:47'),(11,'P011','Peserta didik laki-laki berambut gondrong atau tidak rapi','Ringan',2,'Dipotong dan dirapikan','2026-02-20 13:47:47'),(12,'P012','Memakai pewarna rambut selain hitam','Ringan',2,'Membuat surat kesanggupan menghitamkan rambut','2026-02-20 13:47:47'),(13,'P013','Berkomunikasi lewat jendela saat KBM','Ringan',2,'Dipanggil dan diperingatkan','2026-02-20 13:47:47'),(14,'P014','Duduk di atas meja','Ringan',2,'Diperingatkan','2026-02-20 13:47:47'),(15,'P015','Duduk dengan kaki di atas kursi atau meja','Ringan',2,'Diperingatkan','2026-02-20 13:47:47'),(16,'P016','Tidak mengerjakan tugas dari pendidik','Ringan',3,'Diperingatkan dan diberi sanksi guru','2026-02-20 13:47:47'),(17,'P017','Berbicara keras atau menentang dan menolak tugas','Ringan',3,'Dilaporkan ke wali kelas atau BK','2026-02-20 13:47:47'),(18,'P018','Terlambat datang ke sekolah','Ringan',3,'Mengacu pada sanksi keterlambatan','2026-02-20 13:47:47'),(19,'P019','Memarkir kendaraan tidak pada tempatnya','Ringan',3,'Digembos atau dirantai','2026-02-20 13:47:47'),(20,'P020','Kendaraan dengan knalpot tidak standar','Ringan',3,'Dilepas dan dikembalikan setelah diganti standar','2026-02-20 13:47:47'),(21,'P021','Merusak atau mencoret fasilitas sekolah','Ringan',5,'Mengganti atau mengecat seperti semula','2026-02-20 13:47:47'),(22,'P022','Berkata jorok dan melecehkan orang lain','Ringan',5,'Ditindak guru atau petugas','2026-02-20 13:47:47'),(23,'P023','HP aktif saat KBM','Ringan',5,'HP disita sampai jam pulang','2026-02-20 13:47:47'),(24,'P024','Mengganggu jalannya pelajaran','Ringan',5,'Diperingatkan dan ditindak','2026-02-20 13:47:47'),(25,'P025','Izin tidak masuk sekolah','Ringan',7,'Pembinaan wali kelas dan BK','2026-02-20 13:47:47'),(26,'P026','Keluar atau masuk sekolah dengan meloncat pagar','Ringan',10,'Dipanggil dan dibina','2026-02-20 13:47:47'),(27,'P027','Menggunakan atau membuat surat izin palsu','Sedang',30,'Pembinaan BK dan wali kelas serta membuat pernyataan','2026-02-20 13:47:47'),(28,'P028','Alpa tanpa keterangan','Sedang',15,'Pembinaan guru, BK atau wali kelas','2026-02-20 13:47:47'),(29,'P029','Membolos saat jam pelajaran','Sedang',15,'Mengacu sanksi membolos','2026-02-20 13:47:47'),(30,'P030','Membawa atau merokok saat kegiatan sekolah','Ringan',10,'Mengacu sanksi merokok','2026-02-20 13:47:47'),(31,'P031','Perbuatan asusila, berkelahi atau berjudi','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(32,'P032','Membawa atau mengkonsumsi NAPZA dan miras','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(33,'P033','Membawa senjata tajam atau berbahaya','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(34,'P034','Menjadi anggota organisasi bertentangan dengan Pancasila','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(35,'P035','Membawa atau mengedarkan pornografi','Sedang',30,'Mengacu mekanisme penyitaan barang','2026-02-20 13:47:47'),(36,'P036','Mengancam atau melecehkan guru atau kepala sekolah','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(37,'P037','Memalsukan raport atau dokumen negara','Sedang',30,'Pembinaan dan skorsing 2 hari','2026-02-20 13:47:47'),(38,'P038','Terbukti mencuri','Berat',50,'Pembinaan dan skorsing 3 hari','2026-02-20 13:47:47'),(39,'P039','Peserta didik bertato','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(40,'P040','Peserta didik laki-laki bertindik','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(41,'P041','Peserta didik perempuan bertindik berlebihan','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(42,'P042','Menjadi terdakwa tindak pidana','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(43,'P043','Peserta didik hamil atau menghamili','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(44,'P044','Menikah saat masih sekolah','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(45,'P045','Terlibat penyimpangan perilaku seksual','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(46,'P046','Melakukan pemukulan terhadap kepala sekolah atau guru','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47');
/*!40000 ALTER TABLE `deskripsi_pelanggaran` ENABLE KEYS */;
UNLOCK TABLES;

-- --------------------------------------------------------
--
-- Table structure for table `konseling`
--

DROP TABLE IF EXISTS `konseling`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `konseling` (
  `id` int NOT NULL AUTO_INCREMENT,
  `siswa_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_konseling` varchar(20) NOT NULL,
  `masalah` text NOT NULL,
  `solusi` text,
  `tindak_lanjut` text,
  `konselor` varchar(100) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `created_by` int DEFAULT NULL COMMENT 'FK ke users.id (admin yang input)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `konseling` WRITE;
/*!40000 ALTER TABLE `konseling` DISABLE KEYS */;
INSERT INTO `konseling` VALUES (2,2,'2026-02-23','Pribadi','Bullying','Dia pukul','Penanganan','Guru BK','Selesai',1,'2026-02-23 16:20:57','2026-03-16 09:15:06');
/*!40000 ALTER TABLE `konseling` ENABLE KEYS */;
UNLOCK TABLES;

-- --------------------------------------------------------
--
-- Table structure for table `pelanggaran`
--

DROP TABLE IF EXISTS `pelanggaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pelanggaran` (
  `id` int NOT NULL AUTO_INCREMENT,
  `siswa_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_pelanggaran` enum('Ringan','Sedang','Berat') NOT NULL,
  `deskripsi` text NOT NULL,
  `poin` int NOT NULL DEFAULT '0',
  `tindakan` text,
  `status` enum('Pending','Proses','Selesai') NOT NULL DEFAULT 'Pending',
  `dicatat_oleh` int DEFAULT NULL COMMENT 'FK ke users.id (admin yang input)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pelanggaran_siswa` (`siswa_id`),
  KEY `fk_pelanggaran_admin` (`dicatat_oleh`),
  CONSTRAINT `fk_pelanggaran_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pelanggaran_admin` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pelanggaran` WRITE;
/*!40000 ALTER TABLE `pelanggaran` DISABLE KEYS */;
INSERT INTO `pelanggaran` VALUES (8,2,'2026-02-23','Berat','Peserta didik perempuan bertindik berlebihan',100,'Dikembalikan kepada orang tua','Selesai',1,'2026-02-22 21:30:28','2026-02-22 23:53:43'),(12,2,'2026-02-23','Sedang','Menggunakan atau membuat surat izin palsu',60,'Pembinaan BK dan wali kelas serta membuat pernyataan','Selesai',1,'2026-02-22 22:21:23','2026-03-10 17:24:12');
/*!40000 ALTER TABLE `pelanggaran` ENABLE KEYS */;
UNLOCK TABLES;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-27 20:34:00

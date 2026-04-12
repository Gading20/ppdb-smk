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
  `bukti_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `approval_status` enum('Pending','Approved','Rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `siswa_id` (`siswa_id`),
  KEY `tanggal` (`tanggal`),
  CONSTRAINT `fk_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `absensi`
--

LOCK TABLES `absensi` WRITE;
/*!40000 ALTER TABLE `absensi` DISABLE KEYS */;
INSERT INTO `absensi` VALUES (60,1,'2026-04-01','21:21:00','Hadir','hadir','uploads/files/file_1775053300_1.jpeg','Approved','2026-04-01 14:21:40','2026-04-01 14:21:40'),(61,1,'2026-04-02','08:29:33','Hadir','',NULL,'Approved','2026-04-02 01:29:33','2026-04-02 01:36:07'),(62,1,'2026-04-03','11:23:34','Hadir','',NULL,'Approved','2026-04-03 04:23:34','2026-04-03 04:30:27'),(63,1,'2026-04-05','00:00:00','Alpha','',NULL,'Approved','2026-04-05 14:25:02','2026-04-05 14:25:02'),(65,1,'2026-04-11',NULL,'Alpha','',NULL,'Approved','2026-04-11 03:07:02','2026-04-11 03:18:55'),(67,1,'2026-04-12','11:16:07','Hadir','',NULL,'Approved','2026-04-12 04:16:07','2026-04-12 04:20:16');
/*!40000 ALTER TABLE `absensi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','siswa','kepsek','wali_kelas') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `activity_type` enum('login','logout','create','update','delete','approval','absensi') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_type` (`user_type`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=429 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (290,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 13:07:58'),(291,'admin',1,'logout','Admin Administrator logout dari sistem','2026-03-27 13:10:48'),(292,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 13:59:06'),(293,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 13:59:35'),(294,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-03-27 13:59:55'),(295,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 14:04:23'),(296,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-03-27 14:05:42'),(297,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-03-27 14:05:57'),(298,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 14:06:43'),(299,'admin',1,'logout','Admin Administrator logout dari sistem','2026-03-27 14:15:08'),(300,'wali_kelas',3,'login','Wali Kelas Wali Kelas X TKR 1 login ke sistem','2026-03-27 14:20:39'),(301,'wali_kelas',3,'logout','Wali Kelas Wali Kelas X TKR 1 logout dari sistem','2026-03-27 14:21:04'),(302,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2026-03-27 14:22:01'),(303,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 14:26:21'),(304,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-03-27 14:26:54'),(305,'admin',1,'logout','Admin Administrator logout dari sistem','2026-03-27 14:29:18'),(306,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2026-03-27 14:29:53'),(307,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-03-27 14:31:46'),(308,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-03-27 14:31:54'),(309,'wali_kelas',3,'login','Wali Kelas Wali Kelas X TKR 1 login ke sistem','2026-03-27 14:32:12'),(310,'wali_kelas',3,'logout','Wali Kelas Wali Kelas X TKR 1 logout dari sistem','2026-03-27 14:32:21'),(311,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 14:32:35'),(312,'admin',1,'create','Admin menambahkan absensi Hadir untuk Budi Santoso pada tanggal 27/03/2026','2026-03-27 14:33:12'),(313,'admin',1,'create','Tambah [P027] Menggunakan atau membuat surat izin palsu (30 poin) untuk Diana Putri','2026-03-27 14:38:46'),(314,'admin',1,'logout','Admin Administrator logout dari sistem','2026-03-27 15:06:49'),(315,'admin',1,'login','Admin Administrator login ke sistem','2026-03-27 15:14:15'),(316,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 00:51:02'),(317,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-01 01:06:16'),(318,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-01 01:06:51'),(319,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 01:51:45'),(320,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-01 03:17:17'),(321,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 05:01:09'),(322,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-01 05:01:51'),(323,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2026-04-01 05:02:04'),(324,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-01 05:02:24'),(325,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-04-01 05:06:23'),(326,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-01 05:19:15'),(327,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-04-01 05:21:00'),(328,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2026-04-01 05:21:16'),(329,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2026-04-01 05:21:36'),(330,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 05:22:00'),(331,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-01 05:22:22'),(332,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2026-04-01 05:22:37'),(333,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 05:23:14'),(334,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 01/04/2026','2026-04-01 05:23:30'),(335,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 01/04/2026','2026-04-01 05:23:40'),(336,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 01/04/2026','2026-04-01 05:23:58'),(337,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 01/04/2026','2026-04-01 05:25:08'),(338,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 01/04/2026','2026-04-01 05:25:32'),(339,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-01 05:26:20'),(340,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-01 05:26:38'),(341,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-04-01 05:27:37'),(342,'wali_kelas',6,'login','Wali Kelas Wali Kelas X TSM 1 login ke sistem','2026-04-01 05:27:51'),(343,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-01 05:29:07'),(344,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-04-01 05:33:39'),(345,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 05:33:57'),(346,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-01 05:34:04'),(347,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-01 05:34:20'),(348,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-04-01 07:18:10'),(349,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 07:18:25'),(350,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-01 07:22:26'),(351,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2026-04-01 07:24:14'),(352,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-01 07:24:37'),(353,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-04-01 08:13:39'),(354,'wali_kelas',6,'login','Wali Kelas Wali Kelas X TSM 1 login ke sistem','2026-04-01 08:13:54'),(355,'wali_kelas',6,'logout','Wali Kelas Wali Kelas X TSM 1 logout dari sistem','2026-04-01 08:43:33'),(356,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 08:50:07'),(357,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 01/04/2026','2026-04-01 08:50:39'),(358,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-01 09:02:54'),(359,'admin',1,'login','Admin Administrator login ke sistem','2026-04-01 11:15:23'),(360,'admin',1,'update','Admin mengedit data siswa: Ahmad Fadilah (2023001)','2026-04-01 11:27:10'),(361,'admin',1,'update','Admin mengedit data siswa: Ahmad Fadilah (2023001)','2026-04-01 11:28:09'),(362,'admin',1,'update','Admin mengedit data siswa: Qori Hidayat (2023002)','2026-04-01 11:28:41'),(363,'admin',1,'create','Admin menambahkan siswa baru: Gading (21090095)','2026-04-01 11:40:48'),(364,'admin',1,'create','Admin menambahkan siswa baru: Gading (21090095)','2026-04-01 11:53:59'),(365,'admin',1,'create','Admin menambahkan siswa baru: Gading (21090095)','2026-04-01 11:57:20'),(366,'admin',1,'create','Admin menambahkan siswa baru: Gading (21090095)','2026-04-01 12:06:15'),(367,'admin',1,'create','Admin menambahkan siswa baru: Gading (21090095)','2026-04-01 12:20:23'),(368,'admin',1,'create','Tambah konseling [Pribadi] untuk Gading oleh Administrator','2026-04-01 12:23:37'),(369,'admin',1,'create','Admin menambahkan absensi Hadir untuk Gading pada tanggal 01/04/2026','2026-04-01 14:21:40'),(370,'admin',1,'login','Admin Administrator login ke sistem','2026-04-02 01:11:36'),(371,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-02 01:28:58'),(372,'siswa',1,'absensi','Siswa Gading mengajukan absensi sebagai Hadir','2026-04-02 01:29:33'),(373,'admin',1,'login','Admin Administrator login ke sistem','2026-04-02 01:34:25'),(374,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-02 02:36:18'),(375,'admin',1,'login','Admin Administrator login ke sistem','2026-04-02 13:39:24'),(376,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-02 13:45:26'),(377,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-02 13:45:41'),(378,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-02 13:48:52'),(379,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-04-02 13:56:17'),(380,'wali_kelas',6,'login','Wali Kelas Wali Kelas X TSM 1 login ke sistem','2026-04-02 13:56:31'),(381,'wali_kelas',6,'logout','Wali Kelas Wali Kelas X TSM 1 logout dari sistem','2026-04-02 13:57:43'),(382,'admin',1,'login','Admin Administrator login ke sistem','2026-04-02 13:58:02'),(383,'admin',1,'create','Tambah [P038] Terbukti mencuri (50 poin) untuk Gading','2026-04-02 14:05:10'),(384,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-02 14:15:00'),(385,'admin',1,'login','Admin Administrator login ke sistem','2026-04-02 14:46:30'),(386,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-02 14:53:32'),(387,'admin',1,'login','Admin Administrator login ke sistem','2026-04-03 02:55:05'),(388,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-03 04:09:39'),(389,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-03 04:09:57'),(390,'admin',1,'login','Admin Administrator login ke sistem','2026-04-03 04:10:47'),(391,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-03 04:22:59'),(392,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-03 04:23:11'),(393,'siswa',1,'absensi','Siswa Gading mengajukan absensi sebagai Hadir','2026-04-03 04:23:34'),(394,'admin',1,'login','Admin Administrator login ke sistem','2026-04-03 04:29:41'),(395,'admin',1,'approval','Admin Administrator menyetujui pengajuan Hadir dari siswa Gading','2026-04-03 04:30:27'),(396,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-03 04:31:13'),(397,'admin',1,'login','Admin Administrator login ke sistem','2026-04-03 04:32:47'),(398,'admin',1,'login','Admin Administrator login ke sistem','2026-04-04 15:16:56'),(399,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-04 15:19:09'),(400,'kepsek',2,'login','Kepsek Kepala Sekolah login ke sistem','2026-04-04 15:43:49'),(401,'kepsek',2,'logout','Kepsek Kepala Sekolah logout dari sistem','2026-04-04 15:50:59'),(402,'admin',1,'login','Admin Administrator login ke sistem','2026-04-05 14:13:38'),(403,'admin',1,'create','Admin menambahkan absensi Alpha untuk Gading pada tanggal 05/04/2026','2026-04-05 14:25:02'),(404,'admin',1,'login','Admin Administrator login ke sistem','2026-04-08 05:50:42'),(405,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-08 05:51:09'),(406,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-08 05:51:26'),(407,'siswa',1,'absensi','Siswa Gading mengajukan absensi sebagai Hadir','2026-04-08 05:51:43'),(408,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-11 03:06:32'),(409,'siswa',1,'absensi','Siswa Gading mengajukan absensi sebagai Hadir','2026-04-11 03:07:02'),(410,'admin',1,'login','Admin Administrator login ke sistem','2026-04-11 03:07:24'),(411,'admin',1,'approval','Admin menyetujui absensi Hadir untuk Gading','2026-04-11 03:08:00'),(412,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-11 03:08:09'),(413,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-11 03:08:19'),(414,'siswa',1,'delete','Siswa Gading membatalkan pengajuan absensi','2026-04-11 03:08:27'),(415,'admin',1,'login','Admin Administrator login ke sistem','2026-04-11 03:09:12'),(416,'admin',1,'create','Tambah [P033] Membawa senjata tajam atau berbahaya (27 poin) untuk Gading','2026-04-11 03:09:57'),(417,'admin',1,'create','Tambah [P005] Tidak memakai kaos kaki (2 poin) untuk Gading','2026-04-11 03:16:50'),(418,'admin',1,'update','Admin mengedit absensi Gading tanggal 11/04/2026','2026-04-11 03:18:55'),(419,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-11 03:19:39'),(420,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-11 03:21:01'),(421,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-12 03:55:20'),(422,'siswa',1,'absensi','Siswa Gading mengajukan absensi sebagai Hadir','2026-04-12 03:55:26'),(423,'admin',1,'login','Admin Administrator login ke sistem','2026-04-12 03:55:47'),(424,'admin',1,'delete','Admin menghapus absensi Hadir untuk Gading pada tanggal 12/04/2026','2026-04-12 03:59:49'),(425,'admin',1,'logout','Admin Administrator logout dari sistem','2026-04-12 03:59:51'),(426,'siswa',1,'login','Siswa Gading login ke sistem','2026-04-12 04:00:10'),(427,'siswa',1,'absensi','Siswa Gading mengajukan absensi sebagai Hadir','2026-04-12 04:16:07'),(428,'admin',1,'login','Admin Administrator login ke sistem','2026-04-12 04:16:29');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deskripsi_pelanggaran`
--

LOCK TABLES `deskripsi_pelanggaran` WRITE;
/*!40000 ALTER TABLE `deskripsi_pelanggaran` DISABLE KEYS */;
INSERT INTO `deskripsi_pelanggaran` VALUES (1,'P001','Membuang sampah sembarangan','Ringan',1,'Disuruh mengambil dan memasukkan ke tempat semestinya','2026-02-20 13:47:47'),(2,'P002','Memakai perhiasan berlebihan dan berdandan tidak sesuai norma','Ringan',1,'Diperingatkan dan dilepas untuk disimpan','2026-02-20 13:47:47'),(3,'P003','Memelihara kuku panjang','Ringan',1,'Dipotong','2026-02-20 13:47:47'),(4,'P004','Memakai jaket atau topi bukan identitas sekolah saat KBM','Ringan',1,'Dilepas dan diamankan guru','2026-02-20 13:47:47'),(5,'P005','Tidak memakai kaos kaki','Ringan',2,'Diperingatkan','2026-02-20 13:47:47'),(6,'P006','Sabuk tidak berlogo SMK Nurul Ulum Lebaksiu','Ringan',2,'Sabuk disita dan tidak dikembalikan','2026-02-20 13:47:47'),(7,'P007','Memakai sepatu selain warna hitam','Ringan',2,'Sepatu dilepas sebelah dan dikembalikan setelah seminggu','2026-02-20 13:47:47'),(8,'P008','Memakai sandal tanpa alasan sakit','Ringan',2,'Sandal disita dan tidak dikembalikan','2026-02-20 13:47:47'),(9,'P009','Memakai jilbab tidak sesuai ketentuan sekolah','Ringan',2,'Diperingatkan','2026-02-20 13:47:47'),(10,'P010','Tidak berseragam atau atribut tidak lengkap','Ringan',2,'Diminta melengkapi','2026-02-20 13:47:47'),(11,'P011','Peserta didik laki-laki berambut gondrong atau tidak rapi','Ringan',2,'Dipotong dan dirapikan','2026-02-20 13:47:47'),(12,'P012','Memakai pewarna rambut selain hitam','Ringan',2,'Membuat surat kesanggupan menghitamkan rambut','2026-02-20 13:47:47'),(13,'P013','Berkomunikasi lewat jendela saat KBM','Ringan',2,'Dipanggil dan diperingatkan','2026-02-20 13:47:47'),(14,'P014','Duduk di atas meja','Ringan',2,'Diperingatkan','2026-02-20 13:47:47'),(15,'P015','Duduk dengan kaki di atas kursi atau meja','Ringan',2,'Diperingatkan','2026-02-20 13:47:47'),(16,'P016','Tidak mengerjakan tugas dari pendidik','Ringan',3,'Diperingatkan dan diberi sanksi guru','2026-02-20 13:47:47'),(17,'P017','Berbicara keras atau menentang dan menolak tugas','Ringan',3,'Dilaporkan ke wali kelas atau BK','2026-02-20 13:47:47'),(18,'P018','Terlambat datang ke sekolah','Ringan',3,'Mengacu pada sanksi keterlambatan','2026-02-20 13:47:47'),(19,'P019','Memarkir kendaraan tidak pada tempatnya','Ringan',3,'Digembos atau dirantai','2026-02-20 13:47:47'),(20,'P020','Kendaraan dengan knalpot tidak standar','Ringan',3,'Dilepas dan dikembalikan setelah diganti standar','2026-02-20 13:47:47'),(21,'P021','Merusak atau mencoret fasilitas sekolah','Ringan',5,'Mengganti atau mengecat seperti semula','2026-02-20 13:47:47'),(22,'P022','Berkata jorok dan melecehkan orang lain','Ringan',5,'Ditindak guru atau petugas','2026-02-20 13:47:47'),(23,'P023','HP aktif saat KBM','Ringan',5,'HP disita sampai jam pulang','2026-02-20 13:47:47'),(24,'P024','Mengganggu jalannya pelajaran','Ringan',5,'Diperingatkan dan ditindak','2026-02-20 13:47:47'),(25,'P025','Izin tidak masuk sekolah','Ringan',7,'Pembinaan wali kelas dan BK','2026-02-20 13:47:47'),(26,'P026','Keluar atau masuk sekolah dengan meloncat pagar','Ringan',10,'Dipanggil dan dibina','2026-02-20 13:47:47'),(27,'P027','Menggunakan atau membuat surat izin palsu','Sedang',30,'Pembinaan BK dan wali kelas serta membuat pernyataan','2026-02-20 13:47:47'),(28,'P028','Alpa tanpa keterangan','Sedang',15,'Pembinaan guru, BK atau wali kelas','2026-02-20 13:47:47'),(29,'P029','Membolos saat jam pelajaran','Sedang',15,'Mengacu sanksi membolos','2026-02-20 13:47:47'),(30,'P030','Membawa atau merokok saat kegiatan sekolah','Ringan',10,'Mengacu sanksi merokok','2026-02-20 13:47:47'),(31,'P031','Perbuatan asusila, berkelahi atau berjudi','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(32,'P032','Membawa atau mengkonsumsi NAPZA dan miras','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(33,'P033','Membawa senjata tajam atau berbahaya','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(34,'P034','Menjadi anggota organisasi bertentangan dengan Pancasila','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(35,'P035','Membawa atau mengedarkan pornografi','Sedang',30,'Mengacu mekanisme penyitaan barang','2026-02-20 13:47:47'),(36,'P036','Mengancam atau melecehkan guru atau kepala sekolah','Sedang',30,'Orang tua dipanggil dan pembinaan BK','2026-02-20 13:47:47'),(37,'P037','Memalsukan raport atau dokumen negara','Sedang',30,'Pembinaan dan skorsing 2 hari','2026-02-20 13:47:47'),(38,'P038','Terbukti mencuri','Berat',50,'Pembinaan dan skorsing 3 hari','2026-02-20 13:47:47'),(39,'P039','Peserta didik bertato','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(40,'P040','Peserta didik laki-laki bertindik','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(41,'P041','Peserta didik perempuan bertindik berlebihan','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(42,'P042','Menjadi terdakwa tindak pidana','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(43,'P043','Peserta didik hamil atau menghamili','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(44,'P044','Menikah saat masih sekolah','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(45,'P045','Terlibat penyimpangan perilaku seksual','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47'),(46,'P046','Melakukan pemukulan terhadap kepala sekolah atau guru','Berat',100,'Dikembalikan kepada orang tua','2026-02-20 13:47:47');
/*!40000 ALTER TABLE `deskripsi_pelanggaran` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `konseling`
--

LOCK TABLES `konseling` WRITE;
/*!40000 ALTER TABLE `konseling` DISABLE KEYS */;
INSERT INTO `konseling` VALUES (2,2,'2026-02-23','Pribadi','Bullying','Dia pukul','Penanganan','Guru BK','Selesai',1,'2026-02-23 16:20:57','2026-03-16 09:15:06'),(3,1,'2026-04-01','Pribadi','Bullying','Gelut','Booxing','Administrator','Selesai',1,'2026-04-01 12:23:37','2026-04-01 12:43:13');
/*!40000 ALTER TABLE `konseling` ENABLE KEYS */;
UNLOCK TABLES;

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
  CONSTRAINT `fk_pelanggaran_admin` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pelanggaran_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pelanggaran`
--

LOCK TABLES `pelanggaran` WRITE;
/*!40000 ALTER TABLE `pelanggaran` DISABLE KEYS */;
INSERT INTO `pelanggaran` VALUES (22,1,'2026-04-02','Berat','Terbukti mencuri',50,'Pembinaan dan skorsing 3 hari','Selesai',1,'2026-04-02 14:05:10','2026-04-02 14:05:10'),(23,1,'2026-04-11','Sedang','Membawa senjata tajam atau berbahaya',27,'Orang tua dipanggil dan pembinaan BK','Selesai',1,'2026-04-11 03:09:57','2026-04-11 03:09:57'),(24,1,'2026-04-11','Ringan','Tidak memakai kaos kaki',2,'Diperingatkan','Selesai',1,'2026-04-11 03:16:50','2026-04-11 03:16:50');
/*!40000 ALTER TABLE `pelanggaran` ENABLE KEYS */;
UNLOCK TABLES;

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

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Administrator'),(2,'siswa','Siswa'),(3,'kepsek','Kepala Sekolah'),(4,'wali_kelas','Wali Kelas');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `siswa`
--

DROP TABLE IF EXISTS `siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL COMMENT 'FK ke tabel users',
  `nis` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kelas` enum('10','11','12') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jurusan` enum('TKJ','MP','AKL','TSM','TKR') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `siswa`
--

LOCK TABLES `siswa` WRITE;
/*!40000 ALTER TABLE `siswa` DISABLE KEYS */;
INSERT INTO `siswa` VALUES (1,101,'21090095','Gading','10','TKJ','gading@gmail.com','$2y$10$VME5olYLT1NVATfFmfpQneYHIr0CuT.aW7FRq2lkHVB/Qt8L4Af1i','assets/default/photo-profile.png','2026-04-01 12:20:23','2026-04-01 12:20:23');
/*!40000 ALTER TABLE `siswa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
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
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@smkn40-jkt.sch.id','admin123','Administrator','admin','uploads/admin/admin_1_1741506108.jpg','2026-04-12 11:16:29',NULL,NULL,NULL,NULL,NULL,NULL,'2025-03-03 08:23:13','2026-04-12 04:16:29'),(2,'kepsek','kepsek@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Kepala Sekolah','kepsek','assets/default/photo-profile.png','2026-04-04 22:43:49','196501011990031001',NULL,NULL,NULL,NULL,NULL,'2026-03-11 00:23:03','2026-04-04 15:43:49'),(3,'Supri','wk.xa@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X TKR 1','wali_kelas','assets/default/photo-profile.png','2026-03-27 21:32:12','197001011995032001',NULL,'TKR','10','TKR 1','TKR','2026-03-11 00:17:56','2026-03-27 14:32:12'),(4,'Gading','wk.xb@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X TKJ 1','wali_kelas','assets/default/photo-profile.png',NULL,'197002021995032002',NULL,'TKJ','10','TKJ 1','TKJ','2026-03-11 00:17:56','2026-03-16 08:07:59'),(5,'Emma','wk.xi@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X MP 1','wali_kelas','assets/default/photo-profile.png',NULL,'197503031998031003',NULL,'MP','10','MP 1','MP','2026-03-11 00:17:56','2026-03-16 08:07:59'),(6,'Indrawan','wk.xii@sekolah.sch.id','password','Wali Kelas X TSM 1','wali_kelas','assets/default/photo-profile.png','2026-04-02 20:56:31','197804042000042004',NULL,'TSM','10','TSM 1','TSM','2026-03-11 00:17:56','2026-04-02 13:56:31'),(10,'budi.santoso','budi@siswa.smkn40jkt.sch.id','siswa_2024002','Budi Santoso','siswa','uploads/siswa/siswa_2_1741098707.png','2026-04-01 14:24:14',NULL,'2024002','10',NULL,NULL,'RPL','2025-03-03 08:24:03','2026-04-01 07:24:14'),(11,'cindy.amelia','cindy@siswa.smkn40jkt.sch.id','siswa_2024003','Cindy Amelia','siswa','assets/default/photo-profile.png',NULL,NULL,'2024003','10',NULL,NULL,'RPL','2025-03-03 08:24:03','2025-03-03 08:24:03'),(12,'diana.putri','diana@siswa.smkn40jkt.sch.id','diana_2024004','Diana Putri','siswa','assets/default/photo-profile.png',NULL,NULL,'2024004','10',NULL,NULL,'DKV','2025-03-03 08:24:03','2025-03-03 08:24:03'),(13,'eko.prasetyo','eko@siswa.smkn40jkt.sch.id','eko_2024005','Eko Prasetyo','siswa','assets/default/photo-profile.png',NULL,NULL,'2024005','10',NULL,NULL,'DKV','2025-03-03 08:24:03','2025-03-03 08:24:03'),(14,'fani.azahra','fani@siswa.smkn40jkt.sch.id','fani_2024006','Fani Azahra','siswa','assets/default/photo-profile.png',NULL,NULL,'2024006','10',NULL,NULL,'DKV','2025-03-03 08:24:03','2025-03-03 08:24:03'),(15,'galih.pratama','galih@siswa.smkn40jkt.sch.id','siswa_2024007','Galih Pratama','siswa','assets/default/photo-profile.png',NULL,NULL,'2024007','10',NULL,NULL,'AK','2025-03-03 08:24:04','2025-03-03 08:24:04'),(16,'hana.safira','hana@siswa.smkn40jkt.sch.id','siswa_2024008','Hana Safira','siswa','assets/default/photo-profile.png',NULL,NULL,'2024008','10',NULL,NULL,'AK','2025-03-03 08:24:04','2025-03-03 08:24:04'),(17,'indra.kusuma','indra@siswa.smkn40jkt.sch.id','siswa_2024009','Indra Kusuma','siswa','assets/default/photo-profile.png',NULL,NULL,'2024009','10',NULL,NULL,'AK','2025-03-03 08:24:04','2025-03-03 08:24:04'),(18,'jasmine.putri','jasmine@siswa.smkn40jkt.sch.id','siswa_2024010','Jasmine Putri','siswa','assets/default/photo-profile.png',NULL,NULL,'2024010','10',NULL,NULL,'BR','2025-03-03 08:24:04','2025-03-03 08:24:04'),(19,'kevin.wijaya','kevin@siswa.smkn40jkt.sch.id','siswa_2024011','Kevin Wijaya','siswa','assets/default/photo-profile.png',NULL,NULL,'2024011','10',NULL,NULL,'BR','2025-03-03 08:24:04','2025-03-03 08:24:04'),(20,'luna.sari','luna@siswa.smkn40jkt.sch.id','siswa_2024012','Luna Sari','siswa','assets/default/photo-profile.png',NULL,NULL,'2024012','10',NULL,NULL,'BR','2025-03-03 08:24:04','2025-03-03 08:24:04'),(21,'mario.teguh','mario@siswa.smkn40jkt.sch.id','siswa_2024013','Mario Teguh','siswa','assets/default/photo-profile.png',NULL,NULL,'2024013','10',NULL,NULL,'MP','2025-03-03 08:24:04','2025-03-03 08:24:04'),(22,'nina.amalia','nina@siswa.smkn40jkt.sch.id','siswa_2024014','Nina Amalia','siswa','assets/default/photo-profile.png',NULL,NULL,'2024014','10',NULL,NULL,'MP','2025-03-03 08:24:04','2025-03-03 08:24:04'),(23,'oscar.putra','oscar@siswa.smkn40jkt.sch.id','siswa_2024015','Oscar Putra','siswa','assets/default/photo-profile.png',NULL,NULL,'2024015','10',NULL,NULL,'MP','2025-03-03 08:24:04','2025-03-03 08:24:04'),(24,'qori.hidayat','qori@siswa.smkn40jkt.sch.id','siswa_2023002','Qori Hidayat','siswa','assets/default/photo-profile.png',NULL,NULL,'2023002','11',NULL,NULL,'RPL','2025-03-03 08:24:04','2025-03-03 08:24:04'),(25,'rama.putra','rama@siswa.smkn40jkt.sch.id','siswa_2023003','Rama Putra','siswa','assets/default/photo-profile.png',NULL,NULL,'2023003','11',NULL,NULL,'RPL','2025-03-03 08:24:04','2025-03-03 08:24:04'),(26,'ahmad.fadilah','ahmetkutanis8@gmail.com','siswa_2023001','Ahmad Fadilah','siswa','assets/default/photo-profile.png',NULL,NULL,'2023001','10',NULL,NULL,'RPL','2025-03-03 08:33:00','2025-03-03 08:33:00'),(101,'gading_21090095','gading@gmail.com','$2y$10$VME5olYLT1NVATfFmfpQneYHIr0CuT.aW7FRq2lkHVB/Qt8L4Af1i','Gading','siswa','assets/default/photo-profile.png','2026-04-12 11:00:10',NULL,'21090095','10',NULL,NULL,'TKJ','2026-04-01 12:20:23','2026-04-12 04:00:10');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wali_kelas`
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

--
-- Dumping data for table `wali_kelas`
--

LOCK TABLES `wali_kelas` WRITE;
/*!40000 ALTER TABLE `wali_kelas` DISABLE KEYS */;
INSERT INTO `wali_kelas` VALUES (1,3,'Supri','wk.xa@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X TKR 1','197001011995032001','TKR','10','TKR 1','TKR',NULL,'2026-03-27 14:32:12','2026-03-11 00:17:56','2026-03-27 14:32:12'),(2,4,'Gading','wk.xb@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X TKJ 1','197002021995032002','TKJ','10','TKJ 1','TKJ',NULL,NULL,'2026-03-11 00:17:56','2026-03-16 08:07:59'),(3,5,'Emma','wk.xi@sekolah.sch.id','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Wali Kelas X MP 1','197503031998031003','MP','10','MP 1','MP',NULL,NULL,'2026-03-11 00:17:56','2026-03-16 08:07:59'),(4,6,'Indrawan','wk.xii@sekolah.sch.id','password','Wali Kelas X TSM 1','197804042000042004','TSM','10','TSM 1','TSM',NULL,'2026-04-02 13:56:31','2026-03-11 00:17:56','2026-04-02 13:56:31');
/*!40000 ALTER TABLE `wali_kelas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'absensi_siswa'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-12 13:18:14

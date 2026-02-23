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
  `bukti_foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bukti_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `approval_status` enum('Pending','Approved','Rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `siswa_id` (`siswa_id`),
  KEY `tanggal` (`tanggal`),
  CONSTRAINT `fk_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `absensi`
--

LOCK TABLES `absensi` WRITE;
/*!40000 ALTER TABLE `absensi` DISABLE KEYS */;
INSERT INTO `absensi` VALUES (4,4,'2025-03-03','06:55:00','Hadir',NULL,NULL,NULL,'Approved','2025-03-03 01:24:04','2025-03-03 01:24:04'),(6,6,'2025-03-02','00:00:00','Izin',NULL,NULL,NULL,'Approved','2025-03-03 01:24:04','2025-03-09 07:39:09'),(7,7,'2025-03-01','07:30:00','Terlambat',NULL,NULL,NULL,'Approved','2025-03-03 01:24:04','2025-03-03 01:24:04'),(9,11,'2025-03-03','23:15:00','Hadir','',NULL,NULL,'Approved','2025-03-03 09:15:51','2025-03-03 09:15:51'),(31,2,'2025-03-04','22:44:00','Hadir','',NULL,NULL,'Approved','2025-03-04 08:44:10','2025-03-06 01:04:38'),(32,19,'2025-03-04','23:09:00','Hadir','',NULL,NULL,'Approved','2025-03-04 09:10:02','2025-03-04 09:10:02'),(33,14,'2025-03-05','18:48:00','Hadir','',NULL,NULL,'Approved','2025-03-05 11:48:28','2025-03-05 11:48:28'),(46,12,'2025-03-09','14:39:00','Hadir','aduhh',NULL,NULL,'Pending','2025-03-09 07:39:57','2025-03-09 07:39:57'),(51,19,'2026-02-20','16:19:15','Hadir','','uploads/bukti/bukti_19_20260220_161915.jpeg',NULL,'Approved','2026-02-20 09:19:15','2026-02-20 09:54:31'),(55,2,'2026-02-23','14:17:36','Hadir','','uploads/bukti/bukti_2_20260223_141736.jpeg',NULL,'Approved','2026-02-23 07:17:36','2026-02-23 07:17:46');
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
  `user_type` enum('admin','siswa') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `activity_type` enum('login','logout','create','update','delete','approval','absensi') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_type` (`user_type`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (7,'admin',1,'delete','Admin menghapus data siswa: Ahmad Fadillah (2024001)','2025-03-03 08:32:13'),(8,'admin',1,'delete','Admin menghapus data siswa: Putri Rahayu (2023001)','2025-03-03 08:32:30'),(9,'admin',1,'create','Admin menambahkan siswa baru: Ahmad Fadilah (2023001)','2025-03-03 08:33:00'),(10,'admin',1,'delete','Admin menghapus absensi Hadir untuk Hana Safira pada tanggal 03/03/2025','2025-03-03 08:33:19'),(11,'admin',1,'logout','Admin logged out from the system','2025-03-03 10:04:41'),(12,'admin',1,'login','Admin logged into the system','2025-03-03 10:04:43'),(13,'admin',1,'logout','Admin logged out from the system','2025-03-03 10:04:48'),(14,'admin',1,'login','Admin logged into the system','2025-03-03 10:04:49'),(15,'admin',1,'logout','Admin logged out from the system','2025-03-03 10:14:10'),(16,'admin',1,'login','Admin logged into the system','2025-03-03 10:14:11'),(17,'admin',1,'logout','Admin Administrator logout dari sistem','2025-03-03 10:17:06'),(18,'admin',1,'login','Admin Administrator login ke sistem','2025-03-03 10:17:08'),(19,'admin',1,'update','Admin mengubah profil','2025-03-03 10:30:08'),(20,'admin',1,'update','Admin mengubah profil','2025-03-03 10:30:45'),(21,'admin',1,'update','Admin mengubah profil','2025-03-03 10:30:48'),(22,'admin',1,'update','Admin mengubah password','2025-03-03 10:43:30'),(23,'admin',1,'update','Admin mengubah password','2025-03-03 10:43:45'),(24,'admin',1,'update','Admin mengubah profil','2025-03-03 10:43:57'),(25,'admin',1,'update','Admin mengubah profil','2025-03-03 10:45:45'),(26,'admin',1,'update','Admin mengubah profil','2025-03-03 10:50:28'),(27,'admin',1,'update','Admin mengubah profil','2025-03-03 10:50:35'),(28,'admin',1,'update','Admin mengubah profil','2025-03-03 11:09:08'),(29,'admin',1,'update','Admin mengubah profil','2025-03-03 11:09:15'),(30,'admin',1,'update','Admin mengubah profil','2025-03-03 11:09:29'),(31,'admin',1,'update','Admin mengubah profil','2025-03-03 11:09:38'),(32,'admin',1,'logout','Admin Administrator logout dari sistem','2025-03-03 11:19:29'),(33,'admin',1,'login','Admin Administrator login ke sistem','2025-03-03 11:19:31'),(34,'admin',1,'update','Admin mengubah profil','2025-03-03 16:02:50'),(35,'admin',1,'create','Admin menambahkan absensi Hadir untuk Kevin Wijaya pada tanggal 03/03/2025','2025-03-03 16:15:51'),(36,'admin',1,'logout','Admin Administrator logout dari sistem','2025-03-03 16:56:39'),(37,'admin',1,'login','Admin Administrator login ke sistem','2025-03-03 16:57:31'),(38,'admin',1,'logout','Admin Administrator logout dari sistem','2025-03-03 17:05:39'),(39,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-04 07:32:47'),(40,'siswa',2,'logout','Siswa Budi Santoso logout dari sistem','2025-03-04 07:43:47'),(41,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-04 07:44:00'),(42,'siswa',2,'logout','Siswa Budi Santoso logout dari sistem','2025-03-04 07:44:04'),(43,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-04 07:44:34'),(44,'admin',1,'login','Admin Administrator login ke sistem','2025-03-04 08:13:37'),(45,'siswa',2,'create','Siswa Budi Santoso mengisi absensi sebagai Hadir','2025-03-04 08:18:28'),(46,'siswa',2,'create','Siswa Budi Santoso mengisi absensi sebagai Sakit','2025-03-04 08:19:23'),(47,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 12:33:46'),(48,'siswa',2,'absensi','Siswa Budi Santoso mengisi absensi sebagai Izin','2025-03-04 12:33:58'),(49,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 12:34:03'),(50,'siswa',2,'absensi','Siswa Budi Santoso mengisi absensi sebagai Hadir','2025-03-04 12:34:17'),(51,'siswa',2,'absensi','Siswa Budi Santoso mengisi absensi sebagai Sakit','2025-03-04 12:34:43'),(52,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 12:34:59'),(53,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Sakit','2025-03-04 12:47:46'),(54,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 12:47:53'),(55,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 12:47:57'),(56,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 12:48:01'),(57,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Izin','2025-03-04 12:48:04'),(58,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 12:48:16'),(59,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 12:48:18'),(60,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 12:48:27'),(61,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Sakit','2025-03-04 12:48:40'),(62,'admin',1,'approval','Admin Administrator menolak pengajuan Sakit dari siswa Budi Santoso','2025-03-04 12:48:56'),(63,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 13:10:20'),(64,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 13:10:26'),(65,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 13:21:54'),(66,'admin',1,'approval','Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso','2025-03-04 13:22:07'),(67,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 13:37:14'),(68,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 13:37:30'),(69,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 13:37:49'),(70,'admin',1,'approval','Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso','2025-03-04 13:37:58'),(71,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 13:46:13'),(72,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 13:46:16'),(73,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Sakit','2025-03-04 13:52:41'),(74,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 13:52:53'),(75,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 14:10:30'),(76,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-04 14:10:55'),(77,'siswa',2,'update','Siswa mengubah profil','2025-03-04 14:31:30'),(78,'siswa',2,'update','Siswa mengubah profil','2025-03-04 14:31:47'),(79,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-04 14:33:23'),(80,'admin',1,'approval','Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso','2025-03-04 14:33:30'),(81,'admin',1,'delete','Admin menghapus absensi Hadir untuk Budi Santoso pada tanggal 04/03/2025','2025-03-04 14:35:21'),(82,'siswa',2,'update','Siswa mengubah profil','2025-03-04 14:36:44'),(83,'admin',1,'create','Admin menambahkan absensi Hadir untuk Budi Santoso pada tanggal 04/03/2025','2025-03-04 15:44:10'),(84,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 04/03/2025','2025-03-04 15:45:00'),(85,'admin',1,'approval','Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso','2025-03-04 15:45:13'),(86,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 04/03/2025','2025-03-04 16:09:53'),(87,'admin',1,'create','Admin menambahkan absensi Hadir untuk Ahmad Fadilah pada tanggal 04/03/2025','2025-03-04 16:10:02'),(88,'admin',1,'login','Admin Administrator login ke sistem','2025-03-05 08:21:39'),(89,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-05 08:21:59'),(90,'admin',1,'create','Admin menambahkan absensi Hadir untuk Ahmad Fadilah pada tanggal 05/03/2025','2025-03-05 08:38:31'),(91,'admin',1,'create','Admin menambahkan absensi Hadir untuk Cindy Amelia pada tanggal 05/03/2025','2025-03-05 08:39:06'),(92,'admin',1,'update','Admin mengedit absensi Ahmad Fadilah tanggal 04/03/2025','2025-03-05 08:39:38'),(93,'admin',1,'create','Admin menambahkan absensi Hadir untuk Ahmad Fadilah pada tanggal 05/03/2025','2025-03-05 08:40:00'),(94,'admin',1,'update','Admin mengedit absensi Ahmad Fadilah tanggal 04/03/2025','2025-03-05 08:40:15'),(95,'admin',1,'delete','Admin menghapus absensi Hadir untuk Ahmad Fadilah pada tanggal 04/03/2025','2025-03-05 08:40:36'),(96,'admin',1,'create','Admin menambahkan absensi Hadir untuk Hana Safira pada tanggal 05/03/2025','2025-03-05 08:41:16'),(97,'admin',1,'update','Admin mengedit absensi Hana Safira tanggal 05/03/2025','2025-03-05 08:42:10'),(98,'admin',1,'update','Admin mengedit absensi Hana Safira tanggal 05/03/2025','2025-03-05 08:42:24'),(99,'admin',1,'update','Admin mengedit absensi Cindy Amelia tanggal 05/03/2025','2025-03-05 08:42:33'),(100,'admin',1,'update','Admin mengedit absensi Cindy Amelia tanggal 05/03/2025','2025-03-05 08:43:53'),(101,'admin',1,'update','Admin mengedit absensi Cindy Amelia tanggal 05/03/2025','2025-03-05 08:44:08'),(102,'admin',1,'update','Admin mengedit absensi Cindy Amelia tanggal 05/03/2025','2025-03-05 08:44:29'),(103,'siswa',2,'logout','Siswa Budi Santoso logout dari sistem','2025-03-05 10:00:57'),(104,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-05 10:14:50'),(105,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 10:15:13'),(106,'admin',1,'approval','Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso','2025-03-05 10:15:31'),(107,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 04/03/2025','2025-03-05 10:16:02'),(108,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Izin','2025-03-05 10:16:23'),(109,'admin',1,'approval','Admin Administrator menyetujui pengajuan Sakit dari siswa Budi Santoso','2025-03-05 10:16:29'),(110,'admin',1,'approval','Admin Administrator menyetujui pengajuan Izin dari siswa Budi Santoso','2025-03-05 10:16:30'),(111,'siswa',2,'logout','Siswa Budi Santoso logout dari sistem','2025-03-05 10:35:48'),(112,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-05 10:36:11'),(113,'siswa',2,'logout','Siswa Budi Santoso logout dari sistem','2025-03-05 11:14:35'),(114,'siswa',2,'login','Siswa Budi Santoso melakukan login','2025-03-05 11:14:55'),(115,'siswa',2,'logout','Siswa Budi Santoso logout dari sistem','2025-03-05 11:43:06'),(116,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-05 11:43:53'),(117,'admin',1,'logout','Admin Administrator logout dari sistem','2025-03-05 11:44:17'),(118,'admin',1,'login','Admin Administrator login ke sistem','2025-03-05 11:44:20'),(119,'admin',1,'create','Admin menambahkan absensi Hadir untuk Budi Santoso pada tanggal 05/03/2025','2025-03-05 11:47:01'),(120,'admin',1,'create','Admin menambahkan absensi Hadir untuk Nina Amalia pada tanggal 05/03/2025','2025-03-05 11:48:28'),(121,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 12:12:06'),(122,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-05 12:20:52'),(123,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 13:27:57'),(124,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-05 13:28:03'),(125,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 13:44:40'),(126,'admin',1,'approval','Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso','2025-03-05 13:44:51'),(127,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 13:54:33'),(128,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-05 13:54:38'),(129,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 13:55:23'),(130,'admin',1,'approval','Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso','2025-03-05 13:55:30'),(131,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 14:15:01'),(132,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-05 14:15:10'),(133,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 14:15:20'),(134,'admin',1,'approval','Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso','2025-03-05 14:15:26'),(135,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-05 14:15:45'),(136,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2025-03-05 14:15:48'),(137,'admin',1,'login','Admin Administrator login ke sistem','2025-03-06 00:41:10'),(138,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-06 00:41:20'),(139,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-06 00:41:30'),(140,'admin',1,'approval','Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso','2025-03-06 00:41:41'),(141,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-06 00:58:19'),(142,'admin',1,'approval','Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso','2025-03-06 00:58:30'),(143,'admin',1,'approval','Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso','2025-03-06 01:04:38'),(144,'admin',1,'approval','Admin Administrator menyetujui pengajuan Sakit dari siswa Cindy Amelia','2025-03-06 01:04:44'),(145,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2025-03-06 01:04:56'),(146,'admin',1,'approval','Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso','2025-03-06 01:05:07'),(147,'admin',1,'delete','Admin menghapus absensi Hadir untuk Budi Santoso pada tanggal 06/03/2025','2025-03-06 01:18:30'),(148,'admin',1,'login','Admin Administrator login ke sistem','2025-03-06 12:09:25'),(149,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-07 15:13:59'),(150,'admin',1,'login','Admin Administrator login ke sistem','2025-03-07 15:14:33'),(151,'admin',1,'login','Admin Administrator login ke sistem','2025-03-08 08:22:44'),(152,'admin',1,'create','Admin menambahkan absensi Hadir untuk Indra Kusuma pada tanggal 08/03/2025','2025-03-08 08:33:57'),(153,'admin',1,'delete','Admin menghapus absensi Hadir untuk Indra Kusuma pada tanggal 08/03/2025','2025-03-08 08:52:51'),(154,'admin',1,'login','Admin Administrator login ke sistem','2025-03-08 16:44:42'),(155,'admin',1,'login','Admin Administrator login ke sistem','2025-03-09 05:35:50'),(156,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2025-03-09 06:43:10'),(157,'admin',1,'approval','Admin Administrator menyetujui pengajuan Izin dari siswa Fani Azahra','2025-03-09 07:39:09'),(158,'admin',1,'create','Admin menambahkan absensi Hadir untuk Luna Sari pada tanggal 09/03/2025','2025-03-09 07:39:57'),(159,'admin',1,'update','Admin mengubah profil','2025-03-09 07:41:39'),(160,'admin',1,'update','Admin mengubah profil','2025-03-09 07:41:48'),(161,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Sakit','2025-03-09 07:43:02'),(162,'admin',1,'approval','Admin Administrator menolak pengajuan Sakit dari siswa Budi Santoso','2025-03-09 07:43:43'),(163,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Sakit','2025-03-09 07:44:00'),(164,'admin',1,'login','Admin Administrator login ke sistem','2026-02-18 21:34:26'),(165,'siswa',19,'login','Siswa Ahmad Fadilah login ke sistem','2026-02-18 21:35:40'),(166,'siswa',19,'absensi','Siswa Ahmad Fadilah mengajukan absensi sebagai Hadir','2026-02-18 21:39:03'),(167,'admin',1,'create','Admin menambahkan absensi Alpha untuk Ahmad Fadilah pada tanggal 19/02/2026','2026-02-18 23:19:35'),(168,'admin',1,'delete','Admin menghapus absensi Alpha untuk Ahmad Fadilah pada tanggal 19/02/2026','2026-02-18 23:19:46'),(169,'admin',1,'create','Admin mencatat pelanggaran Berat untuk Ahmad Fadilah pada tanggal 19/02/2026','2026-02-18 23:33:52'),(170,'admin',1,'create','Admin mencatat pelanggaran Berat untuk Ahmad Fadilah pada tanggal 19/02/2026','2026-02-18 23:42:21'),(171,'admin',1,'delete','Admin menghapus absensi Terlambat untuk Budi Santoso pada tanggal 03/03/2025','2026-02-19 00:06:46'),(172,'admin',1,'create','Admin mencatat pelanggaran Sedang untuk Ahmad Fadilah pada tanggal 19/02/2026','2026-02-19 00:08:45'),(173,'admin',1,'create','Admin mencatat pelanggaran Ringan untuk Ahmad Fadilah pada tanggal 19/02/2026','2026-02-19 00:14:36'),(174,'admin',1,'login','Admin Administrator login ke sistem','2026-02-20 09:17:32'),(175,'siswa',19,'login','Siswa Ahmad Fadilah login ke sistem','2026-02-20 09:17:43'),(176,'siswa',19,'absensi','Siswa Ahmad Fadilah mengajukan absensi sebagai Hadir','2026-02-20 09:19:15'),(177,'siswa',19,'delete','Siswa Ahmad Fadilah membatalkan pengajuan absensi','2026-02-20 09:59:47'),(178,'admin',1,'create','Admin mencatat pelanggaran Berat untuk Ahmad Fadilah pada tanggal 20/02/2026','2026-02-20 10:04:01'),(179,'admin',1,'delete','Admin menghapus absensi Hadir untuk Eko Prasetyo pada tanggal 02/03/2025','2026-02-20 10:14:03'),(180,'admin',1,'create','Admin memperbarui poin pelanggaran Sedang milik Ahmad Fadilah (+25 poin → total 50) pada 20/02/2026','2026-02-20 10:15:08'),(181,'admin',1,'create','Admin memperbarui poin pelanggaran Sedang milik Ahmad Fadilah (+10 poin → total 60) pada 20/02/2026','2026-02-20 10:15:58'),(182,'admin',1,'create','Admin memperbarui poin pelanggaran Sedang milik Ahmad Fadilah (+10 poin → total 70) pada 20/02/2026','2026-02-20 10:19:06'),(183,'admin',1,'create','Tambah [P027] Menggunakan atau membuat surat izin palsu (0 poin) untuk Ahmad Fadilah','2026-02-20 14:56:25'),(184,'admin',1,'create','Perbarui poin [P027] Menggunakan atau membuat surat izin palsu milik Ahmad Fadilah (+30 → total 30 poin)','2026-02-20 15:01:52'),(185,'admin',1,'create','Perbarui poin [P027] Menggunakan atau membuat surat izin palsu milik Ahmad Fadilah (+30 → total 60 poin)','2026-02-20 15:03:43'),(186,'admin',1,'login','Admin Administrator login ke sistem','2026-02-22 20:06:54'),(187,'siswa',2,'login','Siswa Budi Santoso login ke sistem','2026-02-22 20:07:43'),(188,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2026-02-22 20:09:03'),(189,'siswa',2,'delete','Siswa Budi Santoso membatalkan pengajuan absensi','2026-02-22 20:09:17'),(190,'admin',1,'delete','Admin menghapus absensi Sakit untuk Cindy Amelia pada tanggal 03/03/2025','2026-02-22 20:10:49'),(191,'admin',1,'delete','Admin menghapus absensi Hadir untuk Budi Santoso pada tanggal 23/02/2026','2026-02-22 21:12:49'),(192,'admin',1,'delete','Admin menghapus pelanggaran Sedang untuk Ahmad Fadilah','2026-02-22 21:17:14'),(193,'admin',1,'delete','Admin menghapus pelanggaran Berat untuk Ahmad Fadilah','2026-02-22 21:18:53'),(194,'admin',1,'delete','Admin menghapus pelanggaran Sedang untuk Ahmad Fadilah','2026-02-22 21:19:26'),(195,'admin',1,'delete','Admin menghapus pelanggaran Berat untuk Ahmad Fadilah','2026-02-22 21:25:40'),(196,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Ahmad Fadilah','2026-02-22 21:26:18'),(197,'admin',1,'create','Tambah [P038] Terbukti mencuri (50 poin) untuk Budi Santoso','2026-02-22 21:28:00'),(198,'admin',1,'delete','Admin menghapus pelanggaran Berat untuk Budi Santoso','2026-02-22 21:29:34'),(199,'admin',1,'create','Tambah [P041] Peserta didik perempuan bertindik berlebihan (100 poin) untuk Budi Santoso','2026-02-22 21:30:28'),(200,'admin',1,'create','Tambah [P001] Membuang sampah sembarangan (0 poin) untuk Budi Santoso','2026-02-22 21:31:27'),(201,'admin',1,'create','Tambah [P029] Membolos saat jam pelajaran (0 poin) untuk Budi Santoso','2026-02-22 21:34:26'),(202,'admin',1,'update','Admin memperbarui status pelanggaran Ringan siswa Budi Santoso menjadi Selesai','2026-02-22 21:41:41'),(203,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-22 22:06:48'),(204,'admin',1,'delete','Admin menghapus pelanggaran Sedang untuk Budi Santoso','2026-02-22 22:06:55'),(205,'admin',1,'create','Tambah [P029] Membolos saat jam pelajaran (0 poin) untuk Budi Santoso','2026-02-22 22:07:12'),(206,'admin',1,'delete','Admin menghapus pelanggaran Sedang untuk Budi Santoso','2026-02-22 22:21:02'),(207,'admin',1,'create','Tambah [P027] Menggunakan atau membuat surat izin palsu (0 poin) untuk Budi Santoso','2026-02-22 22:21:23'),(208,'admin',1,'create','Tambah [P030] Membawa atau merokok saat kegiatan sekolah (0 poin) untuk Budi Santoso','2026-02-22 22:22:41'),(209,'admin',1,'create','Perbarui poin [P027] Menggunakan atau membuat surat izin palsu milik Budi Santoso (+30 → total 30 poin)','2026-02-22 22:29:22'),(210,'admin',1,'create','Tambah [P026] Keluar atau masuk sekolah dengan meloncat pagar (0 poin) untuk Budi Santoso','2026-02-22 22:29:57'),(211,'admin',1,'create','Perbarui poin [P027] Menggunakan atau membuat surat izin palsu milik Budi Santoso (+30 → total 60 poin)','2026-02-22 22:30:45'),(212,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-22 22:31:27'),(213,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-22 22:31:34'),(214,'admin',1,'create','Tambah [P026] Keluar atau masuk sekolah dengan meloncat pagar (0 poin) untuk Budi Santoso','2026-02-22 22:31:55'),(215,'admin',1,'create','Tambah [P019] Memarkir kendaraan tidak pada tempatnya (0 poin) untuk Budi Santoso','2026-02-22 22:46:30'),(216,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-22 22:46:43'),(217,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-22 22:59:13'),(218,'admin',1,'create','Tambah [P001] Membuang sampah sembarangan (0 poin) untuk Budi Santoso','2026-02-22 23:01:13'),(219,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-22 23:07:19'),(220,'admin',1,'create','Tambah [P003] Memelihara kuku panjang (0 poin) untuk Budi Santoso','2026-02-22 23:07:33'),(221,'admin',1,'create','Tambah [P030] Membawa atau merokok saat kegiatan sekolah (0 poin) untuk Budi Santoso','2026-02-22 23:11:20'),(222,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-22 23:14:59'),(223,'admin',1,'create','Perbarui poin [P030] Membawa atau merokok saat kegiatan sekolah milik Budi Santoso (+10 → total 10 poin)','2026-02-22 23:15:14'),(224,'admin',1,'create','Perbarui poin [P026] Keluar atau masuk sekolah dengan meloncat pagar milik Budi Santoso (+10 → total 20 poin)','2026-02-22 23:15:38'),(225,'admin',1,'update','Admin memperbarui status pelanggaran Berat siswa Budi Santoso menjadi Proses','2026-02-22 23:21:28'),(226,'admin',1,'update','Admin memperbarui status pelanggaran Berat siswa Budi Santoso menjadi Selesai','2026-02-22 23:23:45'),(227,'admin',1,'update','Admin memperbarui status pelanggaran Sedang siswa Budi Santoso menjadi Proses','2026-02-22 23:31:04'),(228,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2026-02-22 23:34:40'),(229,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 23/02/2026','2026-02-22 23:40:51'),(230,'admin',1,'update','Admin mengedit pelanggaran #8 (Berat) milik Budi Santoso','2026-02-22 23:53:25'),(231,'admin',1,'update','Admin mengedit pelanggaran #8 (Berat) milik Budi Santoso','2026-02-22 23:53:43'),(232,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-22 23:53:53'),(233,'admin',1,'create','Tambah [P026] Keluar atau masuk sekolah dengan meloncat pagar (0 poin) untuk Budi Santoso','2026-02-22 23:54:09'),(234,'admin',1,'create','Admin menambahkan absensi Hadir untuk Budi Santoso pada tanggal 23/02/2026','2026-02-23 07:13:00'),(235,'admin',1,'delete','Admin menghapus absensi Hadir untuk Budi Santoso pada tanggal 23/02/2026','2026-02-23 07:13:14'),(236,'admin',1,'delete','Admin menghapus pelanggaran Ringan untuk Budi Santoso','2026-02-23 07:13:26'),(237,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 23/02/2026','2026-02-23 07:14:11'),(238,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 23/02/2026','2026-02-23 07:14:35'),(239,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 23/02/2026','2026-02-23 07:16:27'),(240,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 23/02/2026','2026-02-23 07:16:49'),(241,'admin',1,'update','Admin mengedit absensi Budi Santoso tanggal 23/02/2026','2026-02-23 07:17:14'),(242,'admin',1,'delete','Admin menghapus absensi Hadir untuk Budi Santoso pada tanggal 23/02/2026','2026-02-23 07:17:21'),(243,'siswa',2,'absensi','Siswa Budi Santoso mengajukan absensi sebagai Hadir','2026-02-23 07:17:36'),(244,'admin',1,'create','Tambah konseling [Pribadi] untuk Budi Santoso oleh Administrator','2026-02-23 09:37:12'),(245,'admin',1,'login','Admin Administrator login ke sistem','2026-02-23 15:33:38'),(246,'admin',1,'delete','Admin menghapus konseling Karir untuk Budi Santoso','2026-02-23 16:19:48'),(247,'admin',1,'create','Tambah konseling [Pribadi] untuk Budi Santoso oleh Guru BK','2026-02-23 16:20:57');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `foto_profil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'assets/default/photo-profile.png',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES (1,'admin','admin@smkn40-jkt.sch.id','admin123','Administrator','uploads/admin/admin_1_1741506108.jpg','2026-02-23 22:33:38','2025-03-03 08:23:13','2026-02-23 15:33:38');
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `konseling`
--

LOCK TABLES `konseling` WRITE;
/*!40000 ALTER TABLE `konseling` DISABLE KEYS */;
INSERT INTO `konseling` VALUES (2,2,'2026-02-23','Pribadi','Bullying','Dia pukul , Pukul balik aja','Penanganan','Guru BK','Selesai',1,'2026-02-23 16:20:57','2026-02-23 16:20:57');
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
  `dicatat_oleh` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pelanggaran_siswa` (`siswa_id`),
  KEY `fk_pelanggaran_admin` (`dicatat_oleh`),
  CONSTRAINT `fk_pelanggaran_admin` FOREIGN KEY (`dicatat_oleh`) REFERENCES `admin` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pelanggaran_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pelanggaran`
--

LOCK TABLES `pelanggaran` WRITE;
/*!40000 ALTER TABLE `pelanggaran` DISABLE KEYS */;
INSERT INTO `pelanggaran` VALUES (8,2,'2026-02-23','Berat','Peserta didik perempuan bertindik berlebihan',100,'Dikembalikan kepada orang tua','Selesai',1,'2026-02-22 21:30:28','2026-02-22 23:53:43'),(12,2,'2026-02-23','Sedang','Menggunakan atau membuat surat izin palsu',60,'Pembinaan BK dan wali kelas serta membuat pernyataan','Proses',1,'2026-02-22 22:21:23','2026-02-22 23:31:04');
/*!40000 ALTER TABLE `pelanggaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `siswa`
--

DROP TABLE IF EXISTS `siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nis` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kelas` enum('10','11','12') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jurusan` enum('RPL','DKV','AK','BR','MP') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `foto_profil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'assets/default/photo-profile.png',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nis` (`nis`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `siswa`
--

LOCK TABLES `siswa` WRITE;
/*!40000 ALTER TABLE `siswa` DISABLE KEYS */;
INSERT INTO `siswa` VALUES (2,'2024002','Budi Santoso','10','RPL','budi@siswa.smkn40jkt.sch.id','siswa_2024002','uploads/siswa/siswa_2_1741098707.png','2025-03-03 08:24:03','2025-03-04 14:31:47'),(3,'2024003','Cindy Amelia','10','RPL','cindy@siswa.smkn40jkt.sch.id','siswa_2024003','assets/default/photo-profile.png','2025-03-03 08:24:03','2025-03-03 08:24:03'),(4,'2024004','Diana Putri','10','DKV','diana@siswa.smkn40jkt.sch.id','diana_2024004','assets/default/photo-profile.png','2025-03-03 08:24:03','2025-03-03 08:24:03'),(5,'2024005','Eko Prasetyo','10','DKV','eko@siswa.smkn40jkt.sch.id','eko_2024005','assets/default/photo-profile.png','2025-03-03 08:24:03','2025-03-03 08:24:03'),(6,'2024006','Fani Azahra','10','DKV','fani@siswa.smkn40jkt.sch.id','fani_2024006','assets/default/photo-profile.png','2025-03-03 08:24:03','2025-03-03 08:24:03'),(7,'2024007','Galih Pratama','10','AK','galih@siswa.smkn40jkt.sch.id','siswa_2024007','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(8,'2024008','Hana Safira','10','AK','hana@siswa.smkn40jkt.sch.id','siswa_2024008','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(9,'2024009','Indra Kusuma','10','AK','indra@siswa.smkn40jkt.sch.id','siswa_2024009','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(10,'2024010','Jasmine Putri','10','BR','jasmine@siswa.smkn40jkt.sch.id','siswa_2024010','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(11,'2024011','Kevin Wijaya','10','BR','kevin@siswa.smkn40jkt.sch.id','siswa_2024011','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(12,'2024012','Luna Sari','10','BR','luna@siswa.smkn40jkt.sch.id','siswa_2024012','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(13,'2024013','Mario Teguh','10','MP','mario@siswa.smkn40jkt.sch.id','siswa_2024013','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(14,'2024014','Nina Amalia','10','MP','nina@siswa.smkn40jkt.sch.id','siswa_2024014','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(15,'2024015','Oscar Putra','10','MP','oscar@siswa.smkn40jkt.sch.id','siswa_2024015','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(17,'2023002','Qori Hidayat','11','RPL','qori@siswa.smkn40jkt.sch.id','siswa_2023002','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(18,'2023003','Rama Putra','11','RPL','rama@siswa.smkn40jkt.sch.id','siswa_2023003','assets/default/photo-profile.png','2025-03-03 08:24:04','2025-03-03 08:24:04'),(19,'2023001','Ahmad Fadilah','10','RPL','ahmetkutanis8@gmail.com','siswa_2023001','assets/default/photo-profile.png','2025-03-03 08:33:00','2025-03-03 08:33:00');
/*!40000 ALTER TABLE `siswa` ENABLE KEYS */;
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

-- Dump completed on 2026-02-23 23:22:14

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: helpdeskbismillahyallah
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_trails`
--

DROP TABLE IF EXISTS `audit_trails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_trails` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint(20) unsigned NOT NULL,
  `module` enum('service_catalog','sla_configuration','user_role_management','ticket_approval','ticket_support') NOT NULL,
  `action` enum('create','update','activate','deactivate','assign_support','change_level','change_role','approve','request_revision','reject','resolve','escalate') NOT NULL,
  `target_type` varchar(255) NOT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `target_name` varchar(255) NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_trails_actor_id_foreign` (`actor_id`),
  CONSTRAINT `audit_trails_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_trails`
--

LOCK TABLES `audit_trails` WRITE;
/*!40000 ALTER TABLE `audit_trails` DISABLE KEYS */;
INSERT INTO `audit_trails` VALUES (1,3,'sla_configuration','update','sla_policy',2,'High Priority','{\"policy_name\":\"High Priority\",\"priority\":\"High\",\"service_type\":\"Incident\",\"response_time_minutes\":120,\"resolution_time_minutes\":480,\"warning_threshold_percent\":80,\"status\":\"active\"}','{\"policy_name\":\"High Priority\",\"priority\":\"High\",\"service_type\":\"Incident\",\"response_time_minutes\":120,\"resolution_time_minutes\":360,\"warning_threshold_percent\":80,\"status\":\"active\"}','Marcell Laforteza mengubah Resolution Time SLA \"High Priority\" dari 8 Jam menjadi 6 Jam.','2026-07-20 13:22:18'),(2,3,'service_catalog','assign_support','subject',133,'Password Expired','{\"support\":\"Arief Kurniawan\"}','{\"support\":\"Sarah\"}','Marcell Laforteza mengubah Support subject \"Password Expired\" dari Arief Kurniawan menjadi Sarah.','2026-07-20 13:24:10'),(3,3,'user_role_management','create','user',15,'Andi Pratama',NULL,'{\"name\":\"Andi Pratama\",\"nip\":null,\"email\":\"andi.pratama@adhi.co.id\",\"whatsapp\":null,\"unit\":null,\"jabatan\":null,\"kode_proyek\":null,\"nama_proyek\":null,\"status\":\"active\"}','Marcell Laforteza menambahkan user \"Andi Pratama\".','2026-07-20 13:25:30'),(4,3,'user_role_management','change_role','user',15,'Andi Pratama','{\"roles\":[\"Requester\"]}','{\"roles\":[\"Support BPO\"]}','Marcell Laforteza mengubah role user \"Andi Pratama\" dari Requester menjadi Support BPO.','2026-07-20 13:27:56'),(5,3,'user_role_management','create','role',8,'Support IT - SAP',NULL,'{\"name\":\"Support IT - SAP\",\"status\":\"active\"}','Marcell Laforteza membuat role \"Support IT - SAP\".','2026-07-20 13:29:26'),(6,3,'sla_configuration','update','sla_policy',1,'Critical Response','{\"Warning Threshold\":\"80%\"}','{\"Warning Threshold\":\"75%\"}','Marcell Laforteza mengubah Warning Threshold SLA \"Critical Response\" dari 80% menjadi 75%.','2026-07-20 13:35:32'),(7,3,'service_catalog','assign_support','subject',133,'Password Expired','{\"support\":\"Sarah\"}','{\"support\":\"Agung Wijayanto\"}','Marcell Laforteza mengubah Support subject \"Password Expired\" dari Sarah menjadi Agung Wijayanto.','2026-07-20 14:55:51'),(8,3,'service_catalog','assign_support','subject',133,'Password Expired','{\"support\":\"Agung Wijayanto\"}','{\"support\":\"Aditya Dwi Nugraha\"}','Marcell Laforteza mengubah Support subject \"Password Expired\" dari Agung Wijayanto menjadi Aditya Dwi Nugraha.','2026-07-20 19:18:32'),(9,3,'service_catalog','create','subject',266,'Bakar Ayam',NULL,'{\"issue_category\":\"Incident\",\"layanan\":\"Jual Ayam\",\"subcategory\":\"EPROCUREMENT\",\"subject\":\"Bakar Ayam\"}','Marcell Laforteza menambahkan layanan \"Jual Ayam — Bakar Ayam\".','2026-07-20 19:19:06'),(10,3,'service_catalog','assign_support','subject',266,'Bakar Ayam','{\"support\":\"Rio Saputra\"}','{\"support\":\"Genta Pratama\"}','Marcell Laforteza mengubah Support subject \"Bakar Ayam\" dari Rio Saputra menjadi Genta Pratama.','2026-07-21 02:23:33'),(11,3,'sla_configuration','update','sla_policy',1,'Critical Response','{\"Response Time\":\"1 Jam\"}','{\"Response Time\":\"2 Jam\"}','Marcell Laforteza mengubah Response Time SLA \"Critical Response\" dari 1 Jam menjadi 2 Jam.','2026-07-21 02:23:53'),(12,3,'service_catalog','assign_support','subject',133,'Password Expired','{\"support\":\"Aditya Dwi Nugraha\"}','{\"support\":\"Agung Wijayanto\"}','Marcell Laforteza mengubah Support subject \"Password Expired\" dari Aditya Dwi Nugraha menjadi Agung Wijayanto.','2026-07-21 10:39:05'),(13,3,'service_catalog','create','subject',275,'Muhammad Jordan',NULL,'{\"issue_category\":\"Service Request\",\"layanan\":\"Bakar Ayam\",\"subcategory\":\"Bolak balik ayam\",\"subject\":\"Muhammad Jordan\"}','Marcell Laforteza menambahkan layanan \"Bakar Ayam — Muhammad Jordan\".','2026-07-21 11:49:53'),(14,3,'sla_configuration','deactivate','sla_policy',1,'Critical Response','{\"status\":\"active\"}','{\"status\":\"inactive\"}','Marcell Laforteza menonaktifkan SLA Policy \"Critical Response\".','2026-07-21 11:50:41'),(15,3,'sla_configuration','deactivate','sla_policy',2,'High Priority','{\"status\":\"active\"}','{\"status\":\"inactive\"}','Marcell Laforteza menonaktifkan SLA Policy \"High Priority\".','2026-07-21 11:50:45'),(16,3,'service_catalog','assign_support','subject',270,'Email / Outlook Bermasalah','{\"support\":\"Aditya Dwi Nugraha\"}','{\"support\":\"Agung Wijayanto\"}','Marcell Laforteza mengubah Support subject \"Email / Outlook Bermasalah\" dari Aditya Dwi Nugraha menjadi Agung Wijayanto.','2026-07-21 11:57:14'),(17,3,'user_role_management','update','user',15,'Andi Pratama','{\"NIP\":\"\",\"Unit Kerja\":\"\",\"Jabatan\":\"\"}','{\"NIP\":\"1844875876968\",\"Unit Kerja\":\"Aselole\",\"Jabatan\":\"Dept.Strava\"}','Marcell Laforteza memperbarui user \"Andi Pratama\": NIP  → 1844875876968; Unit Kerja  → Aselole; Jabatan  → Dept.Strava.','2026-07-21 12:10:19'),(18,3,'service_catalog','assign_support','subject',269,'Tidak Bisa Terima Email','{\"support\":\"Rian\"}','{\"support\":\"Aditya Dwi Nugraha\"}','Marcell Laforteza mengubah Support subject \"Tidak Bisa Terima Email\" dari Rian menjadi Aditya Dwi Nugraha.','2026-07-21 12:32:58'),(19,3,'service_catalog','create','subject',276,'Mas Vito',NULL,'{\"issue_category\":\"Incident\",\"layanan\":\"Vito Hama\",\"subcategory\":\"Hama\",\"subject\":\"Mas Vito\"}','Marcell Laforteza menambahkan layanan \"Vito Hama — Mas Vito\".','2026-07-21 12:40:45'),(20,3,'service_catalog','deactivate','subject',179,'Reschedule jadwal tender','{\"status\":\"active\"}','{\"status\":\"inactive\"}','Marcell Laforteza menonaktifkan subject \"Reschedule jadwal tender\".','2026-07-21 13:05:14'),(21,3,'service_catalog','activate','subject',179,'Reschedule jadwal tender','{\"status\":\"inactive\"}','{\"status\":\"active\"}','Marcell Laforteza mengaktifkan subject \"Reschedule jadwal tender\".','2026-07-21 13:09:20'),(22,3,'service_catalog','assign_support','subject',276,'Mas Vito','{\"support\":\"Genta Pratama\"}','{\"support\":\"Lutfi Ramadhan\"}','Marcell Laforteza mengubah Support subject \"Mas Vito\" dari Genta Pratama menjadi Lutfi Ramadhan.','2026-07-21 13:13:32'),(23,3,'service_catalog','assign_support','subject',276,'Mas Vito','{\"support\":\"Lutfi Ramadhan\"}','{\"support\":\"Maya Prameswari\"}','Marcell Laforteza mengubah Support subject \"Mas Vito\" dari Lutfi Ramadhan menjadi Maya Prameswari.','2026-07-21 13:13:57'),(24,3,'service_catalog','update','subject',276,'Mas Vito','{\"Layanan\":\"Vito Hama\"}','{\"Layanan\":\"Vito Hama Tangkot\"}','Marcell Laforteza mengubah Layanan subject \"Mas Vito\" dari Vito Hama menjadi Vito Hama Tangkot.','2026-07-21 13:16:28'),(25,3,'service_catalog','assign_support','subject',276,'Mas Vito','{\"support\":\"Maya Prameswari\"}','{\"support\":\"Aditya Dwi Nugraha\"}','Marcell Laforteza mengubah Support subject \"Mas Vito\" dari Maya Prameswari menjadi Aditya Dwi Nugraha.','2026-07-21 13:16:28'),(26,3,'service_catalog','change_level','subject',276,'Mas Vito','{\"support_level\":1}','{\"support_level\":2}','Marcell Laforteza mengubah Level subject \"Mas Vito\" dari Level 1 menjadi Level 2.','2026-07-21 13:16:28'),(27,3,'service_catalog','update','subject',276,'Mas Vito Update Test','{\"Subject\":\"Mas Vito\"}','{\"Subject\":\"Mas Vito Update Test\"}','Marcell Laforteza mengubah Subject subject \"Mas Vito Update Test\" dari Mas Vito menjadi Mas Vito Update Test.','2026-07-21 13:25:47'),(28,3,'service_catalog','create','subject',277,'level 2',NULL,'{\"issue_category\":\"Incident\",\"layanan\":\"sambal bakar\",\"subcategory\":\"geprek\",\"subject\":\"level 2\"}','Marcell Laforteza menambahkan layanan \"sambal bakar — level 2\".','2026-07-21 13:29:38'),(29,3,'service_catalog','deactivate','subject',277,'level 2','{\"status\":\"active\"}','{\"status\":\"inactive\"}','Marcell Laforteza menonaktifkan subject \"level 2\".','2026-07-21 13:29:54'),(30,3,'service_catalog','activate','subject',277,'level 2','{\"status\":\"inactive\"}','{\"status\":\"active\"}','Marcell Laforteza mengaktifkan subject \"level 2\".','2026-07-21 13:29:56'),(31,3,'service_catalog','deactivate','subject',277,'level 2','{\"status\":\"active\"}','{\"status\":\"inactive\"}','Marcell Laforteza menonaktifkan subject \"level 2\".','2026-07-21 13:29:59'),(32,3,'service_catalog','activate','subject',277,'level 2','{\"status\":\"inactive\"}','{\"status\":\"active\"}','Marcell Laforteza mengaktifkan subject \"level 2\".','2026-07-21 13:30:57'),(33,3,'service_catalog','create','subject',278,'nasi',NULL,'{\"issue_category\":\"Incident\",\"layanan\":\"sambal bakar\",\"subcategory\":\"penyet\",\"subject\":\"nasi\"}','Marcell Laforteza menambahkan layanan \"sambal bakar — nasi\".','2026-07-21 13:33:13'),(34,3,'service_catalog','update','subject',276,'VITOPLANKTON','{\"Issue Category\":\"Incident\",\"Layanan\":\"Vito Hama Tangkot\",\"Subject\":\"Mas Vito Update Test\"}','{\"Issue Category\":\"Service Request\",\"Layanan\":\"APALAH MAS VITO\",\"Subject\":\"VITOPLANKTON\"}','Marcell Laforteza memperbarui subject \"VITOPLANKTON\": Issue Category Incident → Service Request; Layanan Vito Hama Tangkot → APALAH MAS VITO; Subject Mas Vito Update Test → VITOPLANKTON.','2026-07-21 14:18:18'),(35,3,'service_catalog','assign_support','subject',276,'VITOPLANKTON','{\"support\":\"Aditya Dwi Nugraha\"}','{\"support\":\"Agung Wijayanto\"}','Marcell Laforteza mengubah Support subject \"VITOPLANKTON\" dari Aditya Dwi Nugraha menjadi Agung Wijayanto.','2026-07-21 14:18:18'),(36,3,'service_catalog','change_level','subject',276,'VITOPLANKTON','{\"support_level\":2}','{\"support_level\":1}','Marcell Laforteza mengubah Level subject \"VITOPLANKTON\" dari Level 2 menjadi Level 1.','2026-07-21 14:18:18'),(37,3,'service_catalog','create','subject',279,'subject1',NULL,'{\"issue_category\":\"Incident\",\"layanan\":\"nyoba 1\",\"subcategory\":\"sub1\",\"subject\":\"subject1\"}','Marcell Laforteza menambahkan layanan \"nyoba 1 — subject1\".','2026-07-21 14:19:54'),(38,4,'ticket_approval','reject','ticket',40,'SR-2026-0040','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Rejected\",\"catatan\":\"Tes audit trail reject.\"}','Karina Putri menolak tiket \"SR-2026-0040\": Tes audit trail reject.','2026-07-22 01:35:43'),(39,4,'ticket_approval','request_revision','ticket',41,'AR-2026-0041','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Draft\",\"catatan\":\"kokooko\"}','Karina Putri meminta perbaikan pada tiket \"AR-2026-0041\": kokooko','2026-07-22 01:44:23'),(40,4,'ticket_approval','approve','ticket',41,'AR-2026-0041','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Open\",\"catatan\":\"okok\"}','Karina Putri menyetujui tiket \"AR-2026-0041\" dan meneruskannya ke Support BPO - AKUN APLIKASI.','2026-07-22 01:46:07'),(41,4,'ticket_approval','reject','ticket',42,'SR-2026-0042','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Rejected\",\"catatan\":\"koko\"}','Karina Putri menolak tiket \"SR-2026-0042\": koko','2026-07-22 01:49:42'),(42,10,'ticket_support','resolve','ticket',9,'INC-2026-0009','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"Sudah dicek, masalah selesai setelah reset konfigurasi mailbox.\"}','Aditya Dwi Nugraha menutup layanan tiket \"INC-2026-0009\": Sudah dicek, masalah selesai setelah reset konfigurasi mailbox.','2026-07-22 13:36:07'),(43,10,'ticket_support','escalate','ticket',34,'INC-2026-0034','{\"escalated\":false}','{\"escalated\":true,\"catatan\":\"Perlu penanganan lanjutan dari tim IT untuk akses server.\"}','Aditya Dwi Nugraha mengeskalasi tiket \"INC-2026-0034\" ke Tim IT Lanjutan: Perlu penanganan lanjutan dari tim IT untuk akses server.','2026-07-22 13:38:23'),(44,10,'ticket_support','escalate','ticket',34,'INC-2026-0034','{\"escalated\":false}','{\"escalated\":true,\"catatan\":\"Perlu penanganan lanjutan dari tim IT untuk akses server.\"}','Aditya Dwi Nugraha mengeskalasi tiket \"INC-2026-0034\" ke Tim IT Lanjutan: Perlu penanganan lanjutan dari tim IT untuk akses server.','2026-07-22 13:40:58'),(45,14,'ticket_support','escalate','ticket',39,'INC-2026-0039','{\"assigned_agent\":\"Denny Firmansyah\"}','{\"assigned_agent\":\"Aditya Dwi Nugraha\",\"catatan\":\"Perlu pengecekan konfigurasi release strategy SAP MM, di luar kewenangan BPO.\"}','Denny Firmansyah mengeskalasi tiket \"INC-2026-0039\" ke Support IT (Aditya Dwi Nugraha): Perlu pengecekan konfigurasi release strategy SAP MM, di luar kewenangan BPO.','2026-07-22 14:21:50'),(46,4,'ticket_approval','approve','ticket',45,'AR-2026-0045','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Open\",\"catatan\":\"Disetujui untuk tes end-to-end.\"}','Karina Putri menyetujui tiket \"AR-2026-0045\" dan meneruskannya ke Support IT - PERUBAHAN AKSES APLIKASI.','2026-07-22 14:49:33'),(47,10,'ticket_support','resolve','ticket',45,'AR-2026-0045','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"Akses aplikasi sudah ditambahkan sesuai permintaan.\"}','Aditya Dwi Nugraha menutup layanan tiket \"AR-2026-0045\": Akses aplikasi sudah ditambahkan sesuai permintaan.','2026-07-22 14:51:39'),(48,14,'ticket_support','resolve','ticket',41,'AR-2026-0041','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"sudah kelar\"}','Denny Firmansyah menutup layanan tiket \"AR-2026-0041\": sudah kelar','2026-07-22 15:12:18'),(49,4,'ticket_approval','request_revision','ticket',47,'SR-2026-0047','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Draft\",\"catatan\":\"tolong masih ada yang perlu di perbaikin\"}','Karina Putri meminta perbaikan pada tiket \"SR-2026-0047\": tolong masih ada yang perlu di perbaikin','2026-07-22 15:17:12'),(50,4,'ticket_approval','approve','ticket',47,'SR-2026-0047','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Open\",\"catatan\":\"oke baik ditunggu ya kak\"}','Karina Putri menyetujui tiket \"SR-2026-0047\" dan meneruskannya ke Support BPO - SAP & Support IT - SAP.','2026-07-22 15:17:55'),(51,10,'ticket_support','resolve','ticket',39,'INC-2026-0039','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"okee kelar nihh\"}','Aditya Dwi Nugraha menutup layanan tiket \"INC-2026-0039\": okee kelar nihh','2026-07-22 15:18:23'),(52,3,'sla_configuration','activate','sla_policy',1,'Critical Response','{\"status\":\"inactive\"}','{\"status\":\"active\"}','Marcell Laforteza mengaktifkan SLA Policy \"Critical Response\".','2026-07-23 06:27:51'),(53,3,'sla_configuration','activate','sla_policy',2,'High Priority','{\"status\":\"inactive\"}','{\"status\":\"active\"}','Marcell Laforteza mengaktifkan SLA Policy \"High Priority\".','2026-07-23 06:27:55'),(54,4,'ticket_approval','request_revision','ticket',48,'SR-2026-0048','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Draft\",\"catatan\":\"pemayy\"}','Karina Putri meminta perbaikan pada tiket \"SR-2026-0048\": pemayy','2026-07-23 06:32:20'),(55,4,'ticket_approval','request_revision','ticket',50,'SR-2026-0050','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Draft\",\"catatan\":\"okok\"}','Karina Putri meminta perbaikan pada tiket \"SR-2026-0050\": okok','2026-07-23 06:48:41'),(56,4,'ticket_approval','approve','ticket',48,'SR-2026-0048','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Open\",\"catatan\":\"zhaaam\"}','Karina Putri menyetujui tiket \"SR-2026-0048\" dan meneruskannya ke Support IT - SILO APPS.','2026-07-23 06:58:17'),(57,19,'ticket_support','resolve','ticket',48,'SR-2026-0048','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"whaamm\"}','Agung Wijayanto menutup layanan tiket \"SR-2026-0048\": whaamm','2026-07-23 07:14:54'),(58,19,'ticket_support','resolve','ticket',48,'SR-2026-0048','{\"status\":\"In Progress\"}','{\"status\":\"Resolved\",\"catatan\":\"zhaap bahaap cayttt\"}','Agung Wijayanto menutup layanan tiket \"SR-2026-0048\": zhaap bahaap cayttt','2026-07-23 07:15:49'),(59,4,'ticket_approval','reject','ticket',51,'SR-2026-0051','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Rejected\",\"catatan\":\"cinta suciku kau bauang2\"}','Karina Putri menolak tiket \"SR-2026-0051\": cinta suciku kau bauang2','2026-07-23 07:21:20'),(60,4,'ticket_approval','approve','ticket',52,'INC-2026-0052','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Open\",\"catatan\":\"kereta berangkat__\"}','Karina Putri menyetujui tiket \"INC-2026-0052\" dan meneruskannya ke Support IT - sambal bakar.','2026-07-23 07:28:10'),(61,3,'service_catalog','create','subject',280,'TEST SUBJECT',NULL,'{\"issue_category\":\"Incident\",\"layanan\":\"TEST LAYANAN\",\"subcategory\":\"TEST SUB\",\"subject\":\"TEST SUBJECT\"}','Marcell Laforteza menambahkan layanan \"TEST LAYANAN — TEST SUBJECT\".','2026-07-23 07:35:02'),(62,4,'ticket_approval','approve','ticket',55,'SR-2026-0055','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Open\",\"catatan\":\"ok\"}','Karina Putri menyetujui tiket \"SR-2026-0055\" dan meneruskannya ke Support BPO - SAP & Support IT - SAP.','2026-07-23 08:28:56'),(63,26,'ticket_support','resolve','ticket',55,'SR-2026-0055','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"ok\"}','Lutfi Ramadhan menutup layanan tiket \"SR-2026-0055\": ok','2026-07-23 08:29:51'),(64,4,'ticket_approval','approve','ticket',56,'SR-2026-0056','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Open\",\"catatan\":\"ok\"}','Karina Putri menyetujui tiket \"SR-2026-0056\" dan meneruskannya ke Support BPO - SAP & Support IT - SAP.','2026-07-23 08:31:06'),(65,26,'ticket_support','resolve','ticket',56,'SR-2026-0056','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"ok\"}','Lutfi Ramadhan menutup layanan tiket \"SR-2026-0056\": ok','2026-07-23 08:31:26'),(66,19,'ticket_support','resolve','ticket',11,'INC-2026-0011','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"Sudah saya perbaiki posting periodnya.\"}','Agung Wijayanto menutup layanan tiket \"INC-2026-0011\": Sudah saya perbaiki posting periodnya.','2026-07-23 08:46:37'),(67,19,'ticket_support','resolve','ticket',11,'INC-2026-0011','{\"status\":\"In Progress\"}','{\"status\":\"Resolved\",\"catatan\":\"okok\"}','Agung Wijayanto menutup layanan tiket \"INC-2026-0011\": okok','2026-07-23 08:49:38'),(68,25,'ticket_support','escalate','ticket',57,'INC-2026-0057','{\"assigned_agent\":\"Rio Saputra\"}','{\"assigned_agent\":\"Sarah\",\"catatan\":\"oaaooaaoo\"}','Rio Saputra mengeskalasi tiket \"INC-2026-0057\" ke Support IT (Sarah): oaaooaaoo','2026-07-23 08:51:29'),(69,21,'ticket_support','resolve','ticket',57,'INC-2026-0057','{\"status\":\"Open\"}','{\"status\":\"Resolved\",\"catatan\":\"okoko\"}','Sarah menutup layanan tiket \"INC-2026-0057\": okoko','2026-07-23 08:52:50'),(70,4,'ticket_approval','request_revision','ticket',58,'AR-2026-0058','{\"status\":\"Waiting for Approval\"}','{\"status\":\"Draft\",\"catatan\":\"ok\"}','Karina Putri meminta perbaikan pada tiket \"AR-2026-0058\": ok','2026-07-23 09:13:58');
/*!40000 ALTER TABLE `audit_trails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `issue_categories`
--

DROP TABLE IF EXISTS `issue_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `issue_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `issue_categories_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `issue_categories`
--

LOCK TABLES `issue_categories` WRITE;
/*!40000 ALTER TABLE `issue_categories` DISABLE KEYS */;
INSERT INTO `issue_categories` VALUES (1,'Incident','2026-07-20 11:44:54','2026-07-20 11:44:54'),(2,'Service Request','2026-07-20 11:44:54','2026-07-20 11:44:54'),(3,'Access Request','2026-07-20 11:44:54','2026-07-20 11:44:54');
/*!40000 ALTER TABLE `issue_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_21_005440_create_sla_policies_table',2),(5,'2026_07_21_005441_create_audit_trails_table',2),(6,'2026_07_21_005442_create_tickets_table',3),(7,'2026_07_21_014110_create_issue_categories_table',4),(8,'2026_07_21_014111_create_service_catalog_services_table',4),(9,'2026_07_21_014112_create_service_catalog_subcategories_table',4),(10,'2026_07_21_014113_create_support_agents_table',4),(11,'2026_07_21_014114_create_service_catalog_subjects_table',4),(12,'2026_07_21_025959_recreate_audit_trails_table',5),(13,'2026_07_21_030001_add_helpdesk_fields_to_users_table',5),(14,'2026_07_21_030002_create_roles_table',5),(15,'2026_07_21_030003_create_role_user_table',5),(16,'2026_07_21_070601_add_intake_fields_to_tickets_table',6),(17,'2026_07_21_081809_create_ticket_comments_table',6),(18,'2026_07_21_085007_add_satisfaction_rating_to_tickets_table',6),(19,'2026_07_21_091522_create_ticket_notifications_table',6),(20,'2026_07_21_091523_add_attachment_and_agent_fields_to_tickets_table',6),(21,'2026_07_21_100000_add_email_to_support_agents_table',6),(22,'2026_07_21_151155_drop_service_type_from_sla_policies_table',7),(23,'2026_07_22_020447_add_catalog_subject_id_to_tickets_table',8),(24,'2026_07_22_040124_add_it_agent_id_to_service_catalog_subjects_table',9),(25,'2026_07_22_120000_create_ticket_approvals_table',10),(26,'2026_07_22_150000_add_ticket_approval_to_audit_trails_enums',11),(27,'2026_07_22_160000_add_user_id_to_support_agents_table',12),(28,'2026_07_22_170000_add_escalation_fields_to_tickets_table',12),(29,'2026_07_22_180000_add_ticket_support_to_audit_trails_enums',12),(30,'2026_07_22_190000_add_feedback_note_to_tickets_table',13),(31,'2026_07_23_133527_create_ticket_attachments_table',14),(32,'2026_07_23_153751_add_reopen_fields_to_tickets_table',15);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_user`
--

DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_user_user_id_role_id_unique` (`user_id`,`role_id`),
  KEY `role_user_role_id_foreign` (`role_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_user`
--

LOCK TABLES `role_user` WRITE;
/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` VALUES (1,3,6,NULL,NULL),(2,3,1,NULL,NULL),(3,4,2,NULL,NULL),(4,4,5,NULL,NULL),(5,4,1,NULL,NULL),(6,5,5,NULL,NULL),(7,5,1,NULL,NULL),(8,6,5,NULL,NULL),(9,6,1,NULL,NULL),(10,7,5,NULL,NULL),(11,7,1,NULL,NULL),(12,8,5,NULL,NULL),(13,8,1,NULL,NULL),(14,9,5,NULL,NULL),(15,9,7,NULL,NULL),(16,9,1,NULL,NULL),(17,10,3,NULL,NULL),(18,10,1,NULL,NULL),(19,11,1,NULL,NULL),(20,12,1,NULL,NULL),(21,13,1,NULL,NULL),(22,14,4,NULL,NULL),(23,14,1,NULL,NULL),(26,15,1,NULL,NULL),(27,17,3,NULL,NULL),(28,17,1,NULL,NULL),(29,18,3,NULL,NULL),(30,18,1,NULL,NULL),(31,19,3,NULL,NULL),(32,19,1,NULL,NULL),(33,20,3,NULL,NULL),(34,20,1,NULL,NULL),(35,21,3,NULL,NULL),(36,21,1,NULL,NULL),(37,22,3,NULL,NULL),(38,22,1,NULL,NULL),(39,23,3,NULL,NULL),(40,23,1,NULL,NULL),(41,24,4,NULL,NULL),(42,24,1,NULL,NULL),(43,25,4,NULL,NULL),(44,25,1,NULL,NULL),(45,26,4,NULL,NULL),(46,26,1,NULL,NULL),(47,27,4,NULL,NULL),(48,27,1,NULL,NULL);
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('system','custom') NOT NULL DEFAULT 'custom',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Requester','system','active',0,'2026-07-20 13:04:16','2026-07-20 13:04:16'),(2,'Approver','system','active',1,'2026-07-20 13:04:16','2026-07-20 13:04:16'),(3,'Support IT','system','active',0,'2026-07-20 13:04:16','2026-07-20 13:04:16'),(4,'Support BPO','system','active',0,'2026-07-20 13:04:16','2026-07-20 13:04:16'),(5,'Team Lead','system','active',0,'2026-07-20 13:04:16','2026-07-20 13:04:16'),(6,'Administrator','system','active',0,'2026-07-20 13:04:16','2026-07-20 13:04:16'),(7,'Knowledge Administrator','system','active',0,'2026-07-20 13:04:16','2026-07-20 13:04:16'),(8,'Support IT - SAP','custom','active',0,'2026-07-20 13:29:26','2026-07-20 13:29:26');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_catalog_services`
--

DROP TABLE IF EXISTS `service_catalog_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_catalog_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_catalog_services_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_catalog_services`
--

LOCK TABLES `service_catalog_services` WRITE;
/*!40000 ALTER TABLE `service_catalog_services` DISABLE KEYS */;
INSERT INTO `service_catalog_services` VALUES (14,'SAP','2026-07-20 12:00:10','2026-07-20 12:00:10'),(15,'ELISA','2026-07-20 12:00:10','2026-07-20 12:00:10'),(16,'ADELE','2026-07-20 12:00:10','2026-07-20 12:00:10'),(17,'ARISE','2026-07-20 12:00:10','2026-07-20 12:00:10'),(18,'ERISKA','2026-07-20 12:00:10','2026-07-20 12:00:10'),(19,'SINTA','2026-07-20 12:00:10','2026-07-20 12:00:10'),(20,'CLOUDIA','2026-07-20 12:00:10','2026-07-20 12:00:10'),(21,'NETWORK','2026-07-20 12:00:10','2026-07-20 12:00:10'),(22,'PERANGKAT','2026-07-20 12:00:10','2026-07-20 12:00:10'),(23,'SILO APPS','2026-07-20 12:00:10','2026-07-20 12:00:10'),(24,'AKUN APLIKASI','2026-07-20 12:00:10','2026-07-20 12:00:10'),(25,'PERUBAHAN AKSES APLIKASI','2026-07-20 12:00:11','2026-07-20 12:00:11'),(26,'VPN','2026-07-20 12:00:11','2026-07-20 12:00:11'),(27,'Jual Ayam','2026-07-20 19:19:06','2026-07-20 19:19:06'),(28,'ADHI MAN-POWER','2026-07-21 00:16:03','2026-07-21 00:16:03'),(29,'ADHIMIS-JO','2026-07-21 00:16:04','2026-07-21 00:16:04'),(30,'ADHISEHAT','2026-07-21 00:16:04','2026-07-21 00:16:04'),(31,'AISO','2026-07-21 00:16:04','2026-07-21 00:16:04'),(32,'ANDINI','2026-07-21 00:16:04','2026-07-21 00:16:04'),(33,'ANTISPAM','2026-07-21 00:16:04','2026-07-21 00:16:04'),(34,'APB ERP','2026-07-21 00:16:04','2026-07-21 00:16:04'),(35,'APG ERP','2026-07-21 00:16:04','2026-07-21 00:16:04'),(36,'ARINA (DASHBOARD)','2026-07-21 00:16:04','2026-07-21 00:16:04'),(37,'Asset Management System','2026-07-21 00:16:04','2026-07-21 00:16:04'),(38,'BIMO','2026-07-21 00:16:04','2026-07-21 00:16:04'),(39,'CCM','2026-07-21 00:16:04','2026-07-21 00:16:04'),(40,'CRM','2026-07-21 00:16:04','2026-07-21 00:16:04'),(41,'DHIERA','2026-07-21 00:16:04','2026-07-21 00:16:04'),(42,'EA ADHI','2026-07-21 00:16:04','2026-07-21 00:16:04'),(43,'FIDA','2026-07-21 00:16:04','2026-07-21 00:16:04'),(44,'HRIS','2026-07-21 00:16:04','2026-07-21 00:16:04'),(45,'iBLAST','2026-07-21 00:16:04','2026-07-21 00:16:04'),(46,'ILMU','2026-07-21 00:16:04','2026-07-21 00:16:04'),(47,'InnoDash','2026-07-21 00:16:04','2026-07-21 00:16:04'),(48,'INSAP','2026-07-21 00:16:04','2026-07-21 00:16:04'),(49,'KMS','2026-07-21 00:16:04','2026-07-21 00:16:04'),(50,'MAIA','2026-07-21 00:16:04','2026-07-21 00:16:04'),(51,'MAILIA','2026-07-21 00:16:04','2026-07-21 00:16:04'),(52,'NAGIA','2026-07-21 00:16:04','2026-07-21 00:16:04'),(53,'Sahabat APP','2026-07-21 00:16:04','2026-07-21 00:16:04'),(54,'SHISAN','2026-07-21 00:16:04','2026-07-21 00:16:04'),(55,'SKK','2026-07-21 00:16:04','2026-07-21 00:16:04'),(56,'WIDIA','2026-07-21 00:16:04','2026-07-21 00:16:04'),(57,'Bakar Ayam','2026-07-21 11:49:53','2026-07-21 11:49:53'),(58,'Vito Hama','2026-07-21 12:40:45','2026-07-21 12:40:45'),(59,'Vito Hama Tangkot','2026-07-21 13:16:28','2026-07-21 13:16:28'),(60,'sambal bakar','2026-07-21 13:29:38','2026-07-21 13:29:38'),(61,'APALAH MAS VITO','2026-07-21 14:18:17','2026-07-21 14:18:17'),(62,'nyoba 1','2026-07-21 14:19:54','2026-07-21 14:19:54'),(63,'TEST LAYANAN','2026-07-23 07:35:02','2026-07-23 07:35:02');
/*!40000 ALTER TABLE `service_catalog_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_catalog_subcategories`
--

DROP TABLE IF EXISTS `service_catalog_subcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_catalog_subcategories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_catalog_subcategories_service_id_name_unique` (`service_id`,`name`),
  CONSTRAINT `service_catalog_subcategories_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service_catalog_services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_catalog_subcategories`
--

LOCK TABLES `service_catalog_subcategories` WRITE;
/*!40000 ALTER TABLE `service_catalog_subcategories` DISABLE KEYS */;
INSERT INTO `service_catalog_subcategories` VALUES (39,14,'LOGIN SAP','2026-07-20 12:00:10','2026-07-20 12:00:10'),(40,14,'AUTHORIZATION','2026-07-20 12:00:10','2026-07-20 12:00:10'),(41,14,'MASTER DATA','2026-07-20 12:00:10','2026-07-20 12:00:10'),(42,14,'REPORT','2026-07-20 12:00:10','2026-07-20 12:00:10'),(43,14,'INTEGRATION','2026-07-20 12:00:10','2026-07-20 12:00:10'),(44,14,'PRINTING','2026-07-20 12:00:10','2026-07-20 12:00:10'),(45,14,'PERFORMANCE','2026-07-20 12:00:10','2026-07-20 12:00:10'),(46,14,'DATA','2026-07-20 12:00:10','2026-07-20 12:00:10'),(47,14,'TRANSAKSI MM','2026-07-20 12:00:10','2026-07-20 12:00:10'),(48,14,'TRANSAKSI PS','2026-07-20 12:00:10','2026-07-20 12:00:10'),(49,14,'TRANSAKSI FICO','2026-07-20 12:00:10','2026-07-20 12:00:10'),(50,15,'VENDOR MANAGEMENT','2026-07-20 12:00:10','2026-07-20 12:00:10'),(51,15,'EPROCUREMENT','2026-07-20 12:00:10','2026-07-20 12:00:10'),(52,15,'CONTRACT MANAGEMENT','2026-07-20 12:00:10','2026-07-20 12:00:10'),(53,16,'Account & Profile Issues','2026-07-20 12:00:10','2026-07-20 12:00:10'),(54,17,'Employee Self-Service (ESS) Features','2026-07-20 12:00:10','2026-07-20 12:00:10'),(55,18,'Risk Data & Reporting','2026-07-20 12:00:10','2026-07-20 12:00:10'),(56,19,'Akses & Data Karyawan','2026-07-20 12:00:10','2026-07-20 12:00:10'),(57,20,'Pengelolaan File & Sinkronisasi','2026-07-20 12:00:10','2026-07-20 12:00:10'),(58,21,'INTERNET','2026-07-20 12:00:10','2026-07-20 12:00:10'),(59,21,'WiFi','2026-07-20 12:00:10','2026-07-20 12:00:10'),(60,21,'VPN','2026-07-20 12:00:10','2026-07-20 12:00:10'),(61,21,'LAINNYA','2026-07-20 12:00:10','2026-07-20 12:00:10'),(62,22,'PRINTER JARINGAN','2026-07-20 12:00:10','2026-07-20 12:00:10'),(63,14,'TRANSACTION SUPPORT','2026-07-20 12:00:10','2026-07-20 12:00:10'),(64,14,'WORKFLOW SERVICE','2026-07-20 12:00:10','2026-07-20 12:00:10'),(65,14,'LAYANAN KONSULTASI','2026-07-20 12:00:10','2026-07-20 12:00:10'),(66,23,'APLIKASI & SOFTWARE','2026-07-20 12:00:10','2026-07-20 12:00:10'),(67,23,'DATA & DOKUMEN','2026-07-20 12:00:10','2026-07-20 12:00:10'),(68,23,'EMAIL & KOMUNIKASI','2026-07-20 12:00:10','2026-07-20 12:00:10'),(69,23,'JARINGAN & KONEKSI','2026-07-20 12:00:10','2026-07-20 12:00:10'),(70,23,'VPN & REMOTE ACCESS','2026-07-20 12:00:10','2026-07-20 12:00:10'),(71,23,'KONSULTASI & BANTUAN','2026-07-20 12:00:10','2026-07-20 12:00:10'),(72,24,'SAP','2026-07-20 12:00:10','2026-07-20 12:00:10'),(73,24,'SILO (OTHER APPS)','2026-07-20 12:00:11','2026-07-20 12:00:11'),(74,25,'SAP','2026-07-20 12:00:11','2026-07-20 12:00:11'),(75,25,'SILO (OTHER APPS)','2026-07-20 12:00:11','2026-07-20 12:00:11'),(76,26,'Akses & Aktivasi VPN','2026-07-20 12:00:11','2026-07-20 12:00:11'),(77,27,'EPROCUREMENT','2026-07-20 19:19:06','2026-07-20 19:19:06'),(78,51,'Email Service & Operations','2026-07-21 00:16:04','2026-07-21 00:16:04'),(79,57,'Bolak balik ayam','2026-07-21 11:49:53','2026-07-21 11:49:53'),(80,58,'Hama','2026-07-21 12:40:45','2026-07-21 12:40:45'),(81,59,'Hama','2026-07-21 13:16:28','2026-07-21 13:16:28'),(82,60,'geprek','2026-07-21 13:29:38','2026-07-21 13:29:38'),(83,60,'penyet','2026-07-21 13:33:13','2026-07-21 13:33:13'),(84,61,'Hama','2026-07-21 14:18:17','2026-07-21 14:18:17'),(85,62,'sub1','2026-07-21 14:19:54','2026-07-21 14:19:54'),(86,63,'TEST SUB','2026-07-23 07:35:02','2026-07-23 07:35:02');
/*!40000 ALTER TABLE `service_catalog_subcategories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_catalog_subjects`
--

DROP TABLE IF EXISTS `service_catalog_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_catalog_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issue_category_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `subcategory_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `support_agent_id` bigint(20) unsigned DEFAULT NULL,
  `it_agent_id` bigint(20) unsigned DEFAULT NULL,
  `support_level` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_unique_per_subcategory` (`issue_category_id`,`subcategory_id`,`name`),
  KEY `service_catalog_subjects_service_id_foreign` (`service_id`),
  KEY `service_catalog_subjects_subcategory_id_foreign` (`subcategory_id`),
  KEY `service_catalog_subjects_support_agent_id_foreign` (`support_agent_id`),
  KEY `service_catalog_subjects_it_agent_id_foreign` (`it_agent_id`),
  CONSTRAINT `service_catalog_subjects_issue_category_id_foreign` FOREIGN KEY (`issue_category_id`) REFERENCES `issue_categories` (`id`),
  CONSTRAINT `service_catalog_subjects_it_agent_id_foreign` FOREIGN KEY (`it_agent_id`) REFERENCES `support_agents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_catalog_subjects_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service_catalog_services` (`id`),
  CONSTRAINT `service_catalog_subjects_subcategory_id_foreign` FOREIGN KEY (`subcategory_id`) REFERENCES `service_catalog_subcategories` (`id`),
  CONSTRAINT `service_catalog_subjects_support_agent_id_foreign` FOREIGN KEY (`support_agent_id`) REFERENCES `support_agents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_catalog_subjects`
--

LOCK TABLES `service_catalog_subjects` WRITE;
/*!40000 ALTER TABLE `service_catalog_subjects` DISABLE KEYS */;
INSERT INTO `service_catalog_subjects` VALUES (133,1,14,39,'Password Expired',0,NULL,13,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:43'),(134,1,14,39,'User Locked',0,NULL,14,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(135,1,14,39,'SAP GUI Error',0,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(136,1,14,40,'Akses Ditolak pada Transaksi',0,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(137,1,14,40,'Authorization Error',0,NULL,17,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(138,1,14,41,'Data Tidak Ditemukan/ Tidak Muncul',0,NULL,18,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(139,1,14,41,'Data Tidak Sesuai',0,NULL,19,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(140,1,14,42,'Report Tidak Muncul',0,NULL,20,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(141,1,14,42,'Data Report Tidak Sesuai',0,NULL,13,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(142,1,14,42,'Report Lambat',0,NULL,14,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(143,1,14,43,'Data Tidak Terkirim',0,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(144,1,14,43,'Data Tidak Sinkron',0,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(145,1,14,43,'Interface Error',0,NULL,17,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(146,1,14,44,'Gagal Print',0,NULL,18,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(147,1,14,44,'Output Tidak Muncul',0,NULL,19,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(148,1,14,44,'Format Output Salah',0,NULL,20,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(149,1,14,45,'SAP Lambat',0,NULL,13,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(150,1,14,45,'Timeout',0,NULL,14,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(151,1,14,45,'Not Responding',0,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(152,1,14,46,'Data Duplikat',0,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(153,1,14,46,'Data Hilang',0,NULL,17,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(154,1,14,46,'Data Tidak Sinkron',0,18,NULL,2,1,'2026-07-20 12:00:10','2026-07-20 12:00:10'),(155,1,14,47,'Faulty PO Header Data',0,21,13,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(156,1,14,47,'Quantity & Value Exceeded',0,22,14,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(157,1,14,47,'Kode Material tidak sesuai kewenangan',0,23,15,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(158,1,14,47,'Release Strategy tidak sesuai',0,24,16,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(159,1,14,47,'Transaksi PR/ PO tidak terbaca saat penarikan / tidak bisa adopt transaksi',0,21,17,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(160,1,14,47,'Posting period is not open for variant',0,22,18,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(161,1,14,47,'Purchase Requisition Can\'t Be Released',0,23,19,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(162,1,14,47,'GI - Defisit Stock',0,24,20,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(163,1,14,48,'Budget Update (Revisi Budget berbeda dengan proses sebelumnya)',0,21,13,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(164,1,14,48,'Budget Update (Budget Exceeded)',0,22,14,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(165,1,14,48,'Report PS (Error Dump)',0,23,15,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(166,1,14,48,'Error Approval Budget Original & Update',0,24,16,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(167,1,14,49,'Maintain New GL Account to Financial Statement',0,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(168,1,14,49,'Maintain Reconcilliation Account for Vendor',0,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(169,1,14,49,'Maintain Reconcilliation Account for Customer',0,NULL,17,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(170,1,14,49,'Balancing field \"Profit Center\" in line item 001 not filled',0,NULL,18,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(171,1,14,49,'Direct Fico saldo Asset',0,NULL,19,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(172,1,14,49,'Year end Closing preparation',0,NULL,20,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(173,1,14,49,'Maintain Account for Upload Jurnal',0,21,17,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(174,1,14,49,'Maintain Account for Reclass Utang',0,22,18,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(175,1,14,49,'Maintain Account for Report Budget Access',0,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(176,1,15,50,'Tidak bisa release vendor',0,23,19,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(177,1,15,50,'Tidak bisa verifikasi vendor',0,24,20,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(178,1,15,50,'Business Field tidak sesuai dengan SAP (rilis vendor)',0,21,13,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(179,1,15,51,'Reschedule jadwal tender',0,22,14,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(180,1,15,52,'Tidak bisa submit kontrak',0,23,15,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(181,1,16,53,'User Session Belum Logout',0,NULL,13,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(182,1,16,53,'Username tidak ditemukan',0,NULL,14,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(183,1,16,53,'Unit Kerja Belum Update',0,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(184,1,17,54,'Tidak bisa presensi',0,24,16,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(185,1,17,54,'Tidak bisa update CV',0,21,17,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(186,1,18,55,'Gagal Submit Data Risiko / Upload Eviden',0,NULL,NULL,1,1,'2026-07-20 12:00:10','2026-07-20 12:00:10'),(187,1,18,55,'Dashboard Risiko Tidak Sesuai',0,NULL,NULL,1,1,'2026-07-20 12:00:10','2026-07-20 12:00:10'),(188,1,18,55,'Gagal Export Risk Register',0,NULL,NULL,1,1,'2026-07-20 12:00:10','2026-07-20 12:00:10'),(189,1,19,56,'Data profil tidak sesuai',0,22,18,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(190,1,19,56,'Slip gaji tidak bisa didownload',0,23,19,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(191,1,19,56,'Tidak bisa akses ke Aplikasi Internal',0,NULL,20,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(192,1,20,57,'Menu / tombol hilang',0,NULL,13,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(193,1,20,57,'Share link expired',0,NULL,14,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(194,1,20,57,'Kuota habis',0,NULL,NULL,1,1,'2026-07-20 12:00:10','2026-07-20 12:00:10'),(195,1,20,57,'Gagal sinkronisasi pada desktop',0,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(196,1,21,58,'Tidak bisa akses internet',0,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(197,1,21,58,'Internet lambat',0,NULL,17,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(198,1,21,58,'Koneksi internet putus-putus',0,NULL,18,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(199,1,21,59,'Tidak bisa terhubung WiFi',0,NULL,19,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(200,1,21,59,'Sinyal WiFi lemah',0,NULL,20,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(201,1,21,59,'WiFi lambat',0,NULL,13,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(202,1,21,60,'Tidak bisa login VPN',0,NULL,14,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(203,1,21,60,'VPN terputus',0,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(204,1,21,60,'VPN lambat',0,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(206,1,22,62,'Tidak bisa cetak ke printer jaringan',0,24,20,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(207,1,22,62,'Printer tidak ditemukan',0,21,13,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(208,1,22,62,'Printer offline',0,22,14,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(209,2,14,41,'Pembuatan Data Master (Vendor / Customer / Material)',1,23,15,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(210,2,14,41,'Perubahan Data Master',1,24,16,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(211,2,14,41,'Koreksi Data Master',1,21,17,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(212,2,14,41,'Aktivasi / Penonaktifan Data Master',1,22,18,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(213,2,14,63,'Bantuan Input Transaksi (PR / PO / GR / GI / MIRO)',1,23,19,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(214,2,14,63,'Koreksi Transaksi SAP',1,24,20,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(215,2,14,63,'Reversal / Pembatalan Dokumen',1,21,13,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(216,2,14,63,'Reposting Dokumen',1,22,14,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(217,2,14,63,'Penyesuaian Data Transaksi',1,23,15,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(218,2,14,64,'Perubahan Alur Approval',1,NULL,13,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(219,2,14,64,'Penambahan Tahap Approval',1,NULL,14,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(220,2,14,64,'Penyesuaian Rule Approval',1,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(221,2,14,64,'Setup Workflow Baru',1,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(222,2,14,65,'Konsultasi Proses Bisnis SAP',1,24,16,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(223,2,14,65,'Konsultasi Penggunaan Transaksi SAP',1,21,17,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(224,2,14,65,'Konsultasi Data & Laporan SAP',1,22,18,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(225,2,14,65,'Konsultasi Alur Approval / Workflow',1,23,19,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(226,2,14,65,'Konsultasi Integrasi SAP dengan Sistem Lain',1,24,20,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(227,2,14,65,'Konsultasi Requirement / Kebutuhan User',1,21,13,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(228,2,14,65,'Konsultasi Perbaikan Proses SAP',1,22,14,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(229,2,23,66,'Instalasi / reinstall aplikasi',1,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(230,2,23,66,'Update / upgrade aplikasi',1,NULL,17,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(231,2,23,66,'Perubahan setting aplikasi',1,NULL,18,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(232,2,23,67,'Permintaan laporan / rekap data',1,23,15,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(233,2,23,67,'Permintaan analisis data',1,24,16,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(234,2,23,67,'Koreksi data / file',1,21,17,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(235,2,23,67,'Pengolahan data (Excel, dll)',1,22,18,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(236,2,23,68,'Setup email di perangkat',1,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(237,2,23,68,'Backup / restore email',1,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(238,2,23,68,'Pengaturan email',1,NULL,17,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(239,2,23,69,'Permintaan koneksi internet tambahan',1,NULL,18,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(240,2,23,69,'Setup WiFi / LAN',1,NULL,19,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(241,2,23,69,'Perubahan jaringan kerja',1,NULL,20,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(242,2,23,69,'Permintaan pengecekan koneksi',1,NULL,13,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(243,2,23,70,'Instalasi VPN',1,NULL,14,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(244,2,23,70,'Instalasi FortiClient',1,NULL,15,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(245,2,23,70,'Setup VPN di laptop / PC',1,NULL,16,1,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(246,2,23,71,'Cara penggunaan aplikasi',1,23,19,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(247,2,23,71,'Konsultasi proses kerja',1,24,20,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(248,2,23,71,'Penjelasan fitur sistem',1,21,13,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(249,2,23,71,'Diskusi kebutuhan kerja',1,22,14,2,1,'2026-07-20 12:00:10','2026-07-21 14:12:30'),(250,3,24,72,'Pembuatan akun baru',1,21,NULL,1,1,'2026-07-20 12:00:10','2026-07-20 12:00:10'),(251,3,24,72,'Aktivasi/ Unlock akun',0,13,NULL,2,1,'2026-07-20 12:00:10','2026-07-20 12:00:10'),(252,3,24,72,'Penonaktifan akun',1,14,NULL,3,1,'2026-07-20 12:00:10','2026-07-20 12:00:10'),(253,3,24,72,'Perubahan data akun',1,15,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(254,3,24,72,'Reset Password',0,16,NULL,2,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(255,3,24,73,'Pembuatan akun baru',1,17,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(256,3,24,73,'Penonaktifan akun',1,18,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(257,3,24,73,'Perubahan data akun',1,19,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(258,3,24,73,'Reset Password',0,20,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(259,3,25,74,'Penambahan akses aplikasi',1,13,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(260,3,25,74,'Penghapusan akses aplikasi',1,14,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(261,3,25,74,'Perubahan akses aplikasi',1,15,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(262,3,25,75,'Penambahan akses aplikasi',1,16,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(263,3,25,75,'Penghapusan akses aplikasi',1,17,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(264,3,25,75,'Perubahan akses aplikasi',1,18,NULL,3,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(265,3,26,76,'Aktivasi VPN Forticlient',1,19,NULL,2,1,'2026-07-20 12:00:11','2026-07-20 12:00:11'),(266,1,27,77,'Bakar Ayam',0,21,NULL,1,1,'2026-07-20 19:19:06','2026-07-21 02:23:33'),(267,1,51,78,'Tidak Bisa Login Email',0,NULL,18,1,1,'2026-07-21 00:16:04','2026-07-21 14:12:30'),(268,1,51,78,'Tidak Bisa Kirim Email',0,NULL,19,1,1,'2026-07-21 00:16:04','2026-07-21 14:12:30'),(269,1,51,78,'Tidak Bisa Terima Email',0,NULL,13,1,1,'2026-07-21 00:16:04','2026-07-21 14:12:30'),(270,1,51,78,'Email / Outlook Bermasalah',0,NULL,16,1,1,'2026-07-21 00:16:04','2026-07-21 14:12:30'),(271,1,51,78,'Attachment Bermasalah',0,NULL,14,1,1,'2026-07-21 00:16:04','2026-07-21 14:12:30'),(272,1,51,78,'Mailbox Penuh',0,NULL,15,1,1,'2026-07-21 00:16:04','2026-07-21 14:12:30'),(273,1,51,78,'Email Terblokir / Spam',0,NULL,16,1,1,'2026-07-21 00:16:04','2026-07-21 14:12:30'),(274,1,21,61,'…........................................',0,NULL,NULL,1,1,'2026-07-21 00:16:04','2026-07-21 00:16:04'),(275,2,57,79,'Muhammad Jordan',0,21,NULL,1,1,'2026-07-21 11:49:53','2026-07-21 11:49:53'),(276,2,61,84,'VITOPLANKTON',0,NULL,16,1,1,'2026-07-21 12:40:45','2026-07-21 14:18:18'),(277,1,60,82,'level 2',1,17,NULL,2,1,'2026-07-21 13:29:38','2026-07-21 13:30:57'),(278,1,60,83,'nasi',0,23,NULL,1,1,'2026-07-21 13:33:13','2026-07-21 13:33:13'),(279,1,62,85,'subject1',1,21,15,2,1,'2026-07-21 14:19:54','2026-07-21 14:19:54');
/*!40000 ALTER TABLE `service_catalog_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('0159tn325uwynIPBBqx4ykkv4SpsLqQYVzLlH6Ps',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJ1QndLd05iUmJyQU5JRExLejFlNGRtTTh2YW1qbEFrMVk1ZjhvRk1nIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9hZG1pblwvdGlja2V0LW1hbmFnZW1lbnRcL2V4cG9ydD9pZHM9QVItMjAyNi0wMDU4JTJDSU5DLTIwMjYtMDA1NyUyQ1NSLTIwMjYtMDA1NiUyQ1NSLTIwMjYtMDA1NSUyQ0lOQy0yMDI2LTAwNTQlMkNJTkMtMjAyNi0wMDUzJTJDSU5DLTIwMjYtMDA1MiUyQ1NSLTIwMjYtMDA1MSUyQ1NSLTIwMjYtMDA1MCUyQ0lOQy0yMDI2LTAwNDklMkNTUi0yMDI2LTAwNDglMkNTUi0yMDI2LTAwNDclMkNJTkMtMjAyNi0wMDQ2JTJDQVItMjAyNi0wMDQ1JTJDSU5DLTIwMjYtMDA0NCUyQ1NSLTIwMjYtMDA0MyUyQ1NSLTIwMjYtMDA0MiUyQ0FSLTIwMjYtMDA0MSUyQ1NSLTIwMjYtMDA0MCUyQ0lOQy0yMDI2LTAwMzklMkNTUi0yMDI2LTAwMzglMkNTUi0yMDI2LTAwMzclMkNJTkMtMjAyNi0wMDM2JTJDSU5DLTIwMjYtMDAzNSUyQ0lOQy0yMDI2LTAwMzQlMkNJTkMtMjAyNi0wMDMzJTJDSU5DLTIwMjYtMDAzMiUyQ0lOQy0yMDI2LTAwMzElMkNJTkMtMjAyNi0wMDE3JTJDSU5DLTIwMjYtMDAxNSUyQ0lOQy0yMDI2LTAwMjIlMkNTUi0yMDI2LTAwMDIlMkNTUi0yMDI2LTAwMDQlMkNJTkMtMjAyNi0wMDA4JTJDU1ItMjAyNi0wMDAxJTJDU1ItMjAyNi0wMDI1JTJDSU5DLTIwMjYtMDAyMSUyQ0lOQy0yMDI2LTAwMTAlMkNBUi0yMDI2LTAwMTQlMkNTUi0yMDI2LTAwMDMlMkNJTkMtMjAyNi0wMDE4JTJDSU5DLTIwMjYtMDAyNyUyQ0lOQy0yMDI2LTAwMTMlMkNJTkMtMjAyNi0wMDE2JTJDQVItMjAyNi0wMDI2JTJDU1ItMjAyNi0wMDI5JTJDSU5DLTIwMjYtMDAwOSUyQ1NSLTIwMjYtMDAzMCUyQ1NSLTIwMjYtMDAwNiUyQ0lOQy0yMDI2LTAwMjglMkNJTkMtMjAyNi0wMDIwJTJDSU5DLTIwMjYtMDAxOSUyQ1NSLTIwMjYtMDAyNCUyQ0lOQy0yMDI2LTAwMTIlMkNJTkMtMjAyNi0wMDA3JTJDSU5DLTIwMjYtMDAxMSUyQ1NSLTIwMjYtMDAwNSUyQ0lOQy0yMDI2LTAwMjMiLCJyb3V0ZSI6ImFkbWluLnRpY2tldC1tYW5hZ2VtZW50LmV4cG9ydCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImFjdGluZ19zdXBwb3J0X2FnZW50X2lkIjoxNiwiYWN0aW5nX3N1cHBvcnRfYnBvX2FnZW50X2lkIjoyNX0=',1784801265),('Ekh5AAL5SHXSjUukmqM1vxpZDn9I0aHdW9cJ2VNd',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiIzRkoyNFVJbG5XODFaUU1Ydko2dU9MdVlyZWpDVG5lRWFoS244b0NpIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1784815557),('F1CgcjF0895iDckGd3yK6YbircyZq40MKJJnJJ49',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJpUXZpcFRweGRaSTBhUGhkYWhvdUhDOUJlMmNEUDRwa0VSM0dSQnhnIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1784815556),('I4VU47OApngYMf5FlfrGbBEaXNQPRaSM7N2lnAic',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJRWTluM2VQUUFRMmFRckt2cDg1eXVZbkNod293UzNLV2NFY0JSOHlkIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1785119603),('ibURExQ3gW4LmxzmumLDP1kJ4eOsgY1QaP268r7m',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJJZURockpqSXRKdnFMc0tzRmh1YWwyWnZtYTFhRUd3V0xHdjFzb0YwIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1784815558),('k3qE1i6HVbRS70bIiJoo4ixifgjyLAjyJij51BBD',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.129.1 Chrome/148.0.7778.280 Electron/42.6.0 Safari/537.36','eyJfdG9rZW4iOiJvZFhzelpidnNCbHhwTUc0NkZRbmc4dTRkRmh2dmZrWkNBbUI5U2tjIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJwb3J0YWwuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1784797370),('Kntw2EyBxH1S6DVskNqNYpB4frkjBxotaLME8dSc',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJ0aDNlOHhvTmtQUUJkeklia2ZtbHhMTXJ2NjhMbEdsSk5kREZQZ0VpIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1785119603),('rlOIDVkur8u8gvR7LrAHaFJuXPp5Tw5JFJua7MXa',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJZdXVtN3Z4dXJnS2gxMFpwVzkxUEtjMXVSa21uajNKMFdkNG5oa3ZnIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOiJwb3J0YWwuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1784815558),('RWTVrKfA4e6l7pkLhrUpDPHGwKyOnOg7yVlcZzuA',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJYR3lJQ21NWHladEVSRTlCRFdQenRiZTJmczRnRkdsNnltR2RQYUh2IiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1785119603),('uRuubsKdnUkoKPCi6OCgbgpwBuXpEx76mmGMDOfO',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiIwRElZWllHNkxlMk5qdGZRTDliY3FUVmdtcFRnV1AyR0Ezc0xjdTRrIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1784815557),('vverrHyhd41yTHQa8H7mDJVzI8o2rJRYHt0Mp3NI',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJrZjQ5YUFSWEI3dm5DU2JBUHVqRnFHNnBRdWluY0l0VXdGMFR4QW1UIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1784815557),('XAUhmTWr1FE7Z8KHgZRBvhwpyG4VFeoTjhs31V2b',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJQVnUwcWZiNFpZRmNHaDZoeHBHTWVWenFEcjZKcWQ4UFpZazJodDU4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9kYXNoYm9hcmRcL3RlYW0tbGVhZCIsInJvdXRlIjoiZGFzaGJvYXJkLnRlYW0tbGVhZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1785119701),('Yul7duvNOcD3iG6cvwAoX2DOg9HJK9gtXNTo7usX',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.24012.1 Chrome/148.0.7778.280 Electron/42.7.0 Safari/537.36 MSIX','eyJfdG9rZW4iOiJ5V05pajU5MGlrSnNKd20wb0lDRTdtVUVoWFFUU0VSTkJ0eDBaUXU5IiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1784815557);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sla_policies`
--

DROP TABLE IF EXISTS `sla_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sla_policies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_name` varchar(255) NOT NULL,
  `priority` enum('Critical','High','Medium','Low') NOT NULL,
  `response_time_minutes` int(10) unsigned NOT NULL,
  `resolution_time_minutes` int(10) unsigned NOT NULL,
  `warning_threshold_percent` tinyint(3) unsigned NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sla_policies`
--

LOCK TABLES `sla_policies` WRITE;
/*!40000 ALTER TABLE `sla_policies` DISABLE KEYS */;
INSERT INTO `sla_policies` VALUES (1,'Critical Response','Critical',120,180,75,'active','2026-07-20 10:57:49','2026-07-23 06:27:51'),(2,'High Priority','High',120,360,80,'active','2026-07-20 10:57:49','2026-07-23 06:27:55'),(3,'Medium Standard','Medium',480,2880,75,'active','2026-07-20 10:57:49','2026-07-20 10:57:49'),(4,'Low Priority','Low',1440,7200,70,'active','2026-07-20 10:57:49','2026-07-20 12:45:55');
/*!40000 ALTER TABLE `sla_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_agents`
--

DROP TABLE IF EXISTS `support_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_agents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `type` enum('bpo','it') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_agents_user_id_foreign` (`user_id`),
  CONSTRAINT `support_agents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_agents`
--

LOCK TABLES `support_agents` WRITE;
/*!40000 ALTER TABLE `support_agents` DISABLE KEYS */;
INSERT INTO `support_agents` VALUES (13,10,'Aditya Dwi Nugraha','aditya.dwi.nugraha@adhikarya-helpdesk.test','it',1,'2026-07-20 12:00:10','2026-07-22 13:33:34'),(14,17,'Arief Kurniawan','arief.kurniawan@adhikarya-helpdesk.test','it',1,'2026-07-20 12:00:10','2026-07-23 07:06:49'),(15,18,'Febria Sahrina','febria.sahrina@adhikarya-helpdesk.test','it',1,'2026-07-20 12:00:10','2026-07-23 07:06:50'),(16,19,'Agung Wijayanto','agung.wijayanto@adhikarya-helpdesk.test','it',1,'2026-07-20 12:00:10','2026-07-23 07:06:50'),(17,20,'Naufal Akbar','naufal.akbar@adhikarya-helpdesk.test','it',1,'2026-07-20 12:00:10','2026-07-23 07:06:50'),(18,21,'Sarah','sarah@adhikarya-helpdesk.test','it',1,'2026-07-20 12:00:10','2026-07-23 07:06:50'),(19,22,'Kevin','kevin@adhikarya-helpdesk.test','it',1,'2026-07-20 12:00:10','2026-07-23 07:06:51'),(20,23,'Rian','rian@adhikarya-helpdesk.test','it',1,'2026-07-20 12:00:10','2026-07-23 07:06:51'),(21,24,'Genta Pratama','genta.pratama@adhikarya-helpdesk.test','bpo',1,'2026-07-20 12:00:10','2026-07-23 07:06:51'),(22,25,'Rio Saputra','rio.saputra@adhikarya-helpdesk.test','bpo',1,'2026-07-20 12:00:10','2026-07-23 07:06:51'),(23,26,'Lutfi Ramadhan','lutfi.ramadhan@adhikarya-helpdesk.test','bpo',1,'2026-07-20 12:00:10','2026-07-23 07:06:52'),(24,27,'Maya Prameswari','maya.prameswari@adhikarya-helpdesk.test','bpo',1,'2026-07-20 12:00:10','2026-07-23 07:06:52'),(25,14,'Denny Firmansyah','denny.firmansyah@adhikarya-helpdesk.test','bpo',1,'2026-07-22 14:10:50','2026-07-22 14:10:50');
/*!40000 ALTER TABLE `support_agents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_approvals`
--

DROP TABLE IF EXISTS `ticket_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `approver_id` bigint(20) unsigned NOT NULL,
  `decision` enum('approved','revision_requested','rejected') NOT NULL,
  `note` text NOT NULL,
  `forwarded_to` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_approvals_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_approvals_approver_id_foreign` (`approver_id`),
  CONSTRAINT `ticket_approvals_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_approvals_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_approvals`
--

LOCK TABLES `ticket_approvals` WRITE;
/*!40000 ALTER TABLE `ticket_approvals` DISABLE KEYS */;
INSERT INTO `ticket_approvals` VALUES (1,1,4,'approved','Sesuai kebutuhan, silakan diproses tim support.','Support IT - SILO APPS','2026-07-22 00:12:38','2026-07-22 00:12:38'),(2,3,4,'revision_requested','Mohon lampirkan detail alur approval yang diinginkan terlebih dahulu.','Kirim ke Requester','2026-07-22 00:15:44','2026-07-22 00:15:44'),(3,38,4,'approved','setuju','Support BPO - Lutfi Ramadhan','2026-07-22 01:00:52','2026-07-22 01:00:52'),(4,29,4,'revision_requested','ada yang masih kurang ini','Kirim ke Requester','2026-07-22 01:02:05','2026-07-22 01:02:05'),(5,39,4,'approved','Tes Level 2 routing.','Support BPO - SAP & Support IT - SAP','2026-07-22 01:15:50','2026-07-22 01:15:50'),(6,5,4,'rejected','gajelas','Kirim ke Requester','2026-07-22 01:18:33','2026-07-22 01:18:33'),(7,2,4,'revision_requested','jadii','Kirim ke Requester','2026-07-22 01:21:18','2026-07-22 01:21:18'),(8,40,4,'rejected','Tes audit trail reject.','Kirim ke Requester','2026-07-22 01:35:43','2026-07-22 01:35:43'),(9,41,4,'revision_requested','kokooko','Kirim ke Requester','2026-07-22 01:44:23','2026-07-22 01:44:23'),(10,41,4,'approved','okok','Support BPO - AKUN APLIKASI','2026-07-22 01:46:07','2026-07-22 01:46:07'),(11,42,4,'rejected','koko','Kirim ke Requester','2026-07-22 01:49:42','2026-07-22 01:49:42'),(12,45,4,'approved','Disetujui untuk tes end-to-end.','Support IT - PERUBAHAN AKSES APLIKASI','2026-07-22 14:49:33','2026-07-22 14:49:33'),(13,47,4,'revision_requested','tolong masih ada yang perlu di perbaikin','Kirim ke Requester','2026-07-22 15:17:12','2026-07-22 15:17:12'),(14,47,4,'approved','oke baik ditunggu ya kak','Support BPO - SAP & Support IT - SAP','2026-07-22 15:17:55','2026-07-22 15:17:55'),(15,48,4,'revision_requested','pemayy','Kirim ke Requester','2026-07-23 06:32:20','2026-07-23 06:32:20'),(16,50,4,'revision_requested','okok','Kirim ke Requester','2026-07-23 06:48:41','2026-07-23 06:48:41'),(17,48,4,'approved','zhaaam','Support IT - SILO APPS','2026-07-23 06:58:17','2026-07-23 06:58:17'),(18,51,4,'rejected','cinta suciku kau bauang2','Kirim ke Requester','2026-07-23 07:21:20','2026-07-23 07:21:20'),(19,52,4,'approved','kereta berangkat__','Support IT - sambal bakar','2026-07-23 07:28:10','2026-07-23 07:28:10'),(20,55,4,'approved','ok','Support BPO - SAP & Support IT - SAP','2026-07-23 08:28:56','2026-07-23 08:28:56'),(21,56,4,'approved','ok','Support BPO - SAP & Support IT - SAP','2026-07-23 08:31:06','2026-07-23 08:31:06'),(22,58,4,'revision_requested','ok','Kirim ke Requester','2026-07-23 09:13:58','2026-07-23 09:13:58');
/*!40000 ALTER TABLE `ticket_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_attachments`
--

DROP TABLE IF EXISTS `ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_attachments_ticket_id_foreign` (`ticket_id`),
  CONSTRAINT `ticket_attachments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_attachments`
--

LOCK TABLES `ticket_attachments` WRITE;
/*!40000 ALTER TABLE `ticket_attachments` DISABLE KEYS */;
INSERT INTO `ticket_attachments` VALUES (1,33,'72062773272374113ab84790aa919969.jpg','ticket-attachments/UmscFvmreqpKvw96OVyjP42wo7W8HybcAv1271zW.jpg','2026-07-21 12:01:56','2026-07-21 12:29:16'),(2,34,'images (1).jpg','ticket-attachments/ydSzdZSauLhHN90OtmhCfKw3iwa0QhPBVa7CeTIN.jpg','2026-07-21 12:41:18','2026-07-22 13:40:58'),(3,47,'Screenshot (514).png','ticket-attachments/DM7EZIKJtAmJdYbbYMmgF93KLDM0KfXdDw6kcMRt.png','2026-07-22 15:16:34','2026-07-22 15:17:55'),(4,48,'˖ꉂ qᥙ᥆kkaᥕn❤️_🔥⃟ֶָ֢ᶻ 𝗓.jpg','ticket-attachments/nE7knaVZau5yCFm69EZAyqr6w9zvFwOAEPt9nVRt.jpg','2026-07-23 06:29:15','2026-07-23 06:32:20'),(5,49,'psel_maverick.jpeg','ticket-attachments/4Y1JFaTsWTbptZFNGskxG2yOz7tHmcK61g3DChvh.jpg','2026-07-23 06:31:36','2026-07-23 06:31:36'),(6,50,'72062773272374113ab84790aa919969.jpg','ticket-attachments/ByQf8pNg7qgFgfrhz7rluTuiitKur27g7sSH6D5m.jpg','2026-07-23 06:46:50','2026-07-23 06:46:50'),(7,50,'WhatsApp Image 2026-07-20 at 22.02.04.jpeg','ticket-attachments/QVwyWo21ovonyLx9loF3eMQOzvvB9aJaXj2sUulX.jpg','2026-07-23 06:46:50','2026-07-23 06:46:50'),(8,48,'psel jaket kulit.jpeg','ticket-attachments/2C8JxZ3Sd2WZpEe8dOjDBU1Iy5062jMQ5tQ6XGBu.jpg','2026-07-23 06:57:12','2026-07-23 06:57:12'),(9,48,'WhatsApp Image 2025-12-03 at 09.19.37_026fba1f.jpg','ticket-attachments/kFoDkILReWRvTsgenMXZQbYfuXeAig0vtaqA39HC.jpg','2026-07-23 06:57:12','2026-07-23 06:57:12'),(10,52,'Corkcicle-Canteen-60-oz-1774-ml-Hulk.jpg','ticket-attachments/DAOU1RYMC5XLHsBPK6COvCV990JoCSjaIpa1c5Lo.jpg','2026-07-23 07:27:16','2026-07-23 07:27:16'),(11,55,'WhatsApp Image 2026-07-20 at 22.02.04.jpeg','ticket-attachments/ThH2CoHiCrrjIv8WhHoFwPzzxANO3ouWDniYQ6Zz.jpg','2026-07-23 08:28:40','2026-07-23 08:28:40'),(12,55,'drawSQL-image-export-2026-07-06.jpg','ticket-attachments/n9ZPYurp9OZqZjM9L184IZBzBmvhmd3ZbxF5do6J.jpg','2026-07-23 08:28:40','2026-07-23 08:28:40'),(13,57,'WhatsApp Image 2026-07-20 at 22.02.04.jpeg','ticket-attachments/Z2JgncUtSWoXmxaHRVnL1WzGKVTE6uKzZfNImfwd.jpg','2026-07-23 08:51:03','2026-07-23 08:51:03'),(14,58,'WhatsApp Image 2026-07-20 at 22.02.04.jpeg','ticket-attachments/Ciz5i2nVhqXdk1lkBiOXGSM8xvug1fwxy4Ls8EH5.jpg','2026-07-23 09:10:22','2026-07-23 09:10:22'),(15,58,'72062773272374113ab84790aa919969.jpg','ticket-attachments/EkUfgRGhMvM8TuTdcNXYAqmF2WPzovnELuXl8XQg.jpg','2026-07-23 09:10:23','2026-07-23 09:10:23'),(16,58,'July Week 2 - Progress Report - Aplikasi Helpdesk.pdf','ticket-attachments/7KMnWDZWDvA0lqY07sOecOKpmyOb06xrT4ir9QQg.pdf','2026-07-23 09:12:16','2026-07-23 09:12:16');
/*!40000 ALTER TABLE `ticket_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_comments`
--

DROP TABLE IF EXISTS `ticket_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `author_role` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_comments_ticket_id_foreign` (`ticket_id`),
  CONSTRAINT `ticket_comments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_comments`
--

LOCK TABLES `ticket_comments` WRITE;
/*!40000 ALTER TABLE `ticket_comments` DISABLE KEYS */;
INSERT INTO `ticket_comments` VALUES (1,34,'Andi Pratama','Requester','qwoi','2026-07-21 12:41:50','2026-07-21 12:41:50'),(2,35,'Andi Pratama','Requester','oke','2026-07-21 13:38:35','2026-07-21 13:38:35'),(3,36,'Karina Putri','Approver','misi kak','2026-07-22 01:27:47','2026-07-22 01:27:47'),(4,36,'Andi Pratama','Requester','jadi gini kak','2026-07-22 01:28:24','2026-07-22 01:28:24'),(5,9,'Andi Pratama','Requester','sudah sangat baik','2026-07-22 14:35:31','2026-07-22 14:35:31'),(6,41,'Andi Pratama','Requester','sudahh baik','2026-07-22 15:12:43','2026-07-22 15:12:43'),(7,39,'Andi Pratama','Requester','okok','2026-07-22 15:18:47','2026-07-22 15:18:47'),(8,48,'Karina Putri','Approver','woi ngawur','2026-07-23 06:32:16','2026-07-23 06:32:16'),(9,48,'Karina Putri','Approver','pemayyy','2026-07-23 06:34:15','2026-07-23 06:34:15'),(10,50,'Andi Pratama','Requester','okooo','2026-07-23 06:47:47','2026-07-23 06:47:47'),(11,50,'Karina Putri','Approver','edede','2026-07-23 06:48:13','2026-07-23 06:48:13'),(12,48,'Andi Pratama','Requester','Sudah saya perbaiki, mohon dicek kembali.','2026-07-23 06:55:42','2026-07-23 06:55:42'),(13,48,'Karina Putri','Approver','zhaam','2026-07-23 06:57:59','2026-07-23 06:57:59'),(14,48,'Agung Wijayanto','Support','whoom','2026-07-23 07:13:05','2026-07-23 07:13:05'),(15,48,'Andi Pratama','Requester','vito hamil puki','2026-07-23 07:14:05','2026-07-23 07:14:05'),(16,48,'Agung Wijayanto','Support','iyee','2026-07-23 07:14:26','2026-07-23 07:14:26'),(17,48,'Andi Pratama','Requester','belom coco le','2026-07-23 07:15:18','2026-07-23 07:15:18'),(18,48,'Agung Wijayanto','Support','cem mana pula kau','2026-07-23 07:15:38','2026-07-23 07:15:38'),(19,51,'Karina Putri','Approver','apa salah dan dosaku sayang','2026-07-23 07:21:07','2026-07-23 07:21:07'),(20,52,'Karina Putri','Approver','jugijagijugiajugijau','2026-07-23 07:27:42','2026-07-23 07:27:42'),(21,55,'Andi Pratama','Requester','woi','2026-07-23 08:30:07','2026-07-23 08:30:07'),(22,56,'Andi Pratama','Requester','pukmayt','2026-07-23 08:32:29','2026-07-23 08:32:29'),(23,58,'Andi Pratama','Requester','hbhbhbh','2026-07-23 09:12:41','2026-07-23 09:12:41'),(24,58,'Karina Putri','Approver','vitoooo','2026-07-23 09:12:54','2026-07-23 09:12:54'),(25,58,'Andi Pratama','Requester','lapo aku wak','2026-07-23 09:14:30','2026-07-23 09:14:30'),(26,15,'Andi Pratama','Requester','Halo, ada update soal tiket ini?','2026-07-23 09:22:14','2026-07-23 09:22:14'),(27,15,'Agung Wijayanto','Support','gada lee pukimay','2026-07-23 09:31:22','2026-07-23 09:31:22'),(28,6,'Karina Putri','Approver','apa we','2026-07-23 09:33:18','2026-07-23 09:33:18'),(29,6,'Andi Pratama','Requester','lapo','2026-07-23 09:33:37','2026-07-23 09:33:37');
/*!40000 ALTER TABLE `ticket_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_notifications`
--

DROP TABLE IF EXISTS `ticket_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `ticket_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_notifications_user_id_foreign` (`user_id`),
  KEY `ticket_notifications_ticket_id_foreign` (`ticket_id`),
  CONSTRAINT `ticket_notifications_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_notifications`
--

LOCK TABLES `ticket_notifications` WRITE;
/*!40000 ALTER TABLE `ticket_notifications` DISABLE KEYS */;
INSERT INTO `ticket_notifications` VALUES (1,15,9,'sla_warning','SLA Warning','Tiket INC-2026-0009 \"Tidak Bisa Terima Email — MAILIA\" mendekati batas waktu SLA.',NULL,'2026-07-21 01:15:21','2026-07-21 01:15:21'),(2,15,27,'sla_warning','SLA Warning','Tiket INC-2026-0027 \"Permintaan lain-lain — ARINA (DASHBOARD)\" mendekati batas waktu SLA.',NULL,'2026-07-21 01:15:21','2026-07-21 01:15:21'),(3,15,11,'sla_breach','SLA Breach','Tiket INC-2026-0011 \"Posting period is not open for variant — SAP\" sudah melewati batas SLA.',NULL,'2026-07-21 01:15:21','2026-07-21 01:15:21'),(4,15,12,'sla_breach','SLA Breach','Tiket INC-2026-0012 \"Reschedule jadwal tender — ELISA\" sudah melewati batas SLA.',NULL,'2026-07-21 01:15:21','2026-07-21 01:15:21'),(5,15,24,'sla_breach','SLA Breach','Tiket SR-2026-0024 \"Permintaan lain-lain — ARISE\" sudah melewati batas SLA.',NULL,'2026-07-21 01:15:21','2026-07-21 01:15:21'),(6,15,26,'sla_warning','SLA Warning','Tiket AR-2026-0026 \"Permintaan lain-lain — ARINA (DASHBOARD)\" mendekati batas waktu SLA.',NULL,'2026-07-21 02:15:59','2026-07-21 02:15:59'),(7,15,9,'sla_breach','SLA Breach','Tiket INC-2026-0009 \"Tidak Bisa Terima Email — MAILIA\" sudah melewati batas SLA.',NULL,'2026-07-21 02:15:59','2026-07-21 02:15:59'),(8,15,27,'sla_breach','SLA Breach','Tiket INC-2026-0027 \"Permintaan lain-lain — ARINA (DASHBOARD)\" sudah melewati batas SLA.',NULL,'2026-07-21 02:15:59','2026-07-21 02:15:59'),(9,15,31,'ticket_created','Tiket Dibuat','Tiket INC-2026-0031 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-21 02:22:53','2026-07-21 02:22:53'),(10,15,32,'ticket_created','Tiket Dibuat','Tiket INC-2026-0032 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-21 02:23:12','2026-07-21 02:23:12'),(11,15,13,'sla_breach','SLA Breach','Tiket INC-2026-0013 \"Printer offline — PERANGKAT\" sudah melewati batas SLA.',NULL,'2026-07-21 10:38:23','2026-07-21 10:38:23'),(12,15,15,'sla_breach','SLA Breach','Tiket INC-2026-0015 \"Akses Ditolak pada Transaksi — SAP\" sudah melewati batas SLA.',NULL,'2026-07-21 10:38:23','2026-07-21 10:38:23'),(13,15,17,'sla_breach','SLA Breach','Tiket INC-2026-0017 \"Data Tidak Sinkron — SAP\" sudah melewati batas SLA.',NULL,'2026-07-21 10:38:23','2026-07-21 10:38:23'),(14,15,21,'sla_breach','SLA Breach','Tiket INC-2026-0021 \"Year end Closing preparation — SAP\" sudah melewati batas SLA.',NULL,'2026-07-21 10:38:23','2026-07-21 10:38:23'),(15,15,22,'sla_breach','SLA Breach','Tiket INC-2026-0022 \"Reschedule jadwal tender — ELISA\" sudah melewati batas SLA.',NULL,'2026-07-21 10:38:23','2026-07-21 10:38:23'),(16,15,25,'sla_breach','SLA Breach','Tiket SR-2026-0025 \"Permintaan lain-lain — ARISE\" sudah melewati batas SLA.',NULL,'2026-07-21 10:38:23','2026-07-21 10:38:23'),(17,15,26,'sla_breach','SLA Breach','Tiket AR-2026-0026 \"Permintaan lain-lain — ARINA (DASHBOARD)\" sudah melewati batas SLA.',NULL,'2026-07-21 10:38:23','2026-07-21 10:38:23'),(18,15,29,'ticket_created','Tiket Dikirim','Tiket SR-2026-0029 berhasil dikirim dan menunggu persetujuan.',NULL,'2026-07-21 11:21:34','2026-07-21 11:21:34'),(19,15,30,'ticket_created','Tiket Dikirim','Tiket SR-2026-0030 berhasil dikirim dan menunggu persetujuan.',NULL,'2026-07-21 11:48:15','2026-07-21 11:48:15'),(20,15,33,'ticket_created','Tiket Dibuat','Tiket INC-2026-0033 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-21 12:01:56','2026-07-21 12:01:56'),(21,15,34,'ticket_created','Tiket Dikirim','Tiket INC-2026-0034 berhasil dikirim ke Tim Support.',NULL,'2026-07-21 12:41:42','2026-07-21 12:41:42'),(22,15,35,'ticket_created','Tiket Dibuat','Tiket INC-2026-0035 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-21 13:35:14','2026-07-21 13:35:14'),(23,15,36,'ticket_created','Tiket Dibuat','Tiket INC-2026-0036 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-21 14:20:19','2026-07-21 14:20:19'),(24,15,1,'ticket_approved','Tiket Disetujui','Tiket SR-2026-0001 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-22 00:12:38','2026-07-22 00:12:38'),(25,15,3,'ticket_reopened','Perlu Diperbaiki','Tiket SR-2026-0003 dikembalikan untuk diperbaiki: Mohon lampirkan detail alur approval yang diinginkan terlebih dahulu.',NULL,'2026-07-22 00:15:44','2026-07-22 00:15:44'),(26,15,37,'ticket_created','Tiket Dibuat','Tiket SR-2026-0037 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-22 00:23:15','2026-07-22 00:23:15'),(27,15,38,'ticket_created','Tiket Dibuat','Tiket SR-2026-0038 berhasil dibuat dan menunggu persetujuan.','2026-07-22 01:00:30','2026-07-22 00:28:39','2026-07-22 01:00:30'),(28,4,38,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0038 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-22 00:28:39','2026-07-22 00:28:39'),(29,15,38,'ticket_approved','Tiket Disetujui','Tiket SR-2026-0038 disetujui dan diteruskan ke Tim Support.','2026-07-22 01:01:13','2026-07-22 01:00:52','2026-07-22 01:01:13'),(30,15,29,'ticket_reopened','Perlu Diperbaiki','Tiket SR-2026-0029 dikembalikan untuk diperbaiki: ada yang masih kurang ini','2026-07-22 01:02:16','2026-07-22 01:02:05','2026-07-22 01:02:16'),(31,15,29,'ticket_created','Tiket Dikirim','Tiket SR-2026-0029 berhasil dikirim dan menunggu persetujuan.','2026-07-22 01:13:21','2026-07-22 01:12:48','2026-07-22 01:13:21'),(32,4,29,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0029 dari Andi Pratama menunggu persetujuan Anda.','2026-07-22 01:12:58','2026-07-22 01:12:48','2026-07-22 01:12:58'),(33,15,39,'ticket_created','Tiket Dibuat','Tiket INC-2026-0039 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-22 01:15:22','2026-07-22 01:15:22'),(34,4,39,'waiting_decision','Menunggu Keputusan Anda','Tiket INC-2026-0039 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-22 01:15:22','2026-07-22 01:15:22'),(35,15,39,'ticket_approved','Tiket Disetujui','Tiket INC-2026-0039 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-22 01:15:50','2026-07-22 01:15:50'),(36,15,5,'ticket_rejected','Tiket Ditolak','Tiket SR-2026-0005 ditolak: gajelas',NULL,'2026-07-22 01:18:33','2026-07-22 01:18:33'),(37,15,2,'ticket_reopened','Perlu Diperbaiki','Tiket SR-2026-0002 dikembalikan untuk diperbaiki: jadii','2026-07-22 01:21:26','2026-07-22 01:21:18','2026-07-22 01:21:26'),(38,15,40,'ticket_created','Tiket Dibuat','Tiket SR-2026-0040 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-22 01:35:06','2026-07-22 01:35:06'),(39,4,40,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0040 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-22 01:35:06','2026-07-22 01:35:06'),(40,15,40,'ticket_rejected','Tiket Ditolak','Tiket SR-2026-0040 ditolak: Tes audit trail reject.',NULL,'2026-07-22 01:35:43','2026-07-22 01:35:43'),(41,15,41,'ticket_created','Tiket Dibuat','Tiket AR-2026-0041 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-22 01:43:28','2026-07-22 01:43:28'),(42,4,41,'waiting_decision','Menunggu Keputusan Anda','Tiket AR-2026-0041 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-22 01:43:28','2026-07-22 01:43:28'),(43,15,41,'ticket_reopened','Perlu Diperbaiki','Tiket AR-2026-0041 dikembalikan untuk diperbaiki: kokooko','2026-07-22 01:45:15','2026-07-22 01:44:23','2026-07-22 01:45:15'),(44,15,41,'ticket_created','Tiket Dikirim','Tiket AR-2026-0041 berhasil dikirim dan menunggu persetujuan.',NULL,'2026-07-22 01:45:27','2026-07-22 01:45:27'),(45,4,41,'waiting_decision','Menunggu Keputusan Anda','Tiket AR-2026-0041 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-22 01:45:27','2026-07-22 01:45:27'),(46,15,41,'ticket_approved','Tiket Disetujui','Tiket AR-2026-0041 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-22 01:46:07','2026-07-22 01:46:07'),(47,15,42,'ticket_created','Tiket Dikirim','Tiket SR-2026-0042 berhasil dikirim dan menunggu persetujuan.',NULL,'2026-07-22 01:47:42','2026-07-22 01:47:42'),(48,4,42,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0042 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-22 01:47:42','2026-07-22 01:47:42'),(49,15,42,'ticket_rejected','Tiket Ditolak','Tiket SR-2026-0042 ditolak: koko','2026-07-22 01:49:50','2026-07-22 01:49:42','2026-07-22 01:49:50'),(50,15,43,'ticket_created','Tiket Dibuat','Tiket SR-2026-0043 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-22 08:53:44','2026-07-22 08:53:44'),(51,15,9,'ticket_resolved','Tiket Diselesaikan','Tiket INC-2026-0009 telah diselesaikan oleh Tim Support: Sudah dicek, masalah selesai setelah reset konfigurasi mailbox.',NULL,'2026-07-22 13:36:07','2026-07-22 13:36:07'),(52,15,34,'ticket_escalated','Tiket Dieskalasi','Tiket INC-2026-0034 telah dieskalasi ke Tim IT Lanjutan.',NULL,'2026-07-22 13:38:23','2026-07-22 13:38:23'),(53,15,34,'ticket_escalated','Tiket Dieskalasi','Tiket INC-2026-0034 telah dieskalasi ke Tim IT Lanjutan.',NULL,'2026-07-22 13:40:58','2026-07-22 13:40:58'),(54,15,39,'ticket_escalated','Tiket Dieskalasi','Tiket INC-2026-0039 telah dieskalasi ke Tim IT Lanjutan.',NULL,'2026-07-22 14:21:50','2026-07-22 14:21:50'),(55,10,39,'ticket_incoming_escalation','Tiket Eskalasi Masuk','Tiket INC-2026-0039 dieskalasi dari Support BPO (Denny Firmansyah): Perlu pengecekan konfigurasi release strategy SAP MM, di luar kewenangan BPO.','2026-07-22 15:18:11','2026-07-22 14:21:50','2026-07-22 15:18:11'),(56,15,7,'sla_warning','SLA Warning','Tiket INC-2026-0007 \"Sinyal WiFi lemah — NETWORK\" mendekati batas waktu SLA.',NULL,'2026-07-22 14:28:04','2026-07-22 14:28:04'),(57,15,9,'ticket_closed','Tiket Ditutup','Tiket INC-2026-0009 berhasil ditutup. Terima kasih atas penilaian Anda (5/5).',NULL,'2026-07-22 14:35:31','2026-07-22 14:35:31'),(58,15,44,'ticket_created','Tiket Dibuat','Tiket INC-2026-0044 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-22 14:40:16','2026-07-22 14:40:16'),(59,15,45,'ticket_created','Tiket Dibuat','Tiket AR-2026-0045 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-22 14:47:51','2026-07-22 14:47:51'),(60,4,45,'waiting_decision','Menunggu Keputusan Anda','Tiket AR-2026-0045 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-22 14:47:51','2026-07-22 14:47:51'),(61,15,45,'ticket_approved','Tiket Disetujui','Tiket AR-2026-0045 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-22 14:49:33','2026-07-22 14:49:33'),(62,15,45,'ticket_resolved','Tiket Diselesaikan','Tiket AR-2026-0045 telah diselesaikan oleh Tim Support: Akses aplikasi sudah ditambahkan sesuai permintaan.',NULL,'2026-07-22 14:51:39','2026-07-22 14:51:39'),(63,15,45,'ticket_closed','Tiket Ditutup','Tiket AR-2026-0045 berhasil ditutup. Terima kasih atas penilaian Anda (5/5).',NULL,'2026-07-22 14:52:53','2026-07-22 14:52:53'),(64,15,46,'ticket_created','Tiket Dibuat','Tiket INC-2026-0046 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-22 14:59:59','2026-07-22 14:59:59'),(65,15,41,'ticket_resolved','Tiket Diselesaikan','Tiket AR-2026-0041 telah diselesaikan oleh Tim Support: sudah kelar',NULL,'2026-07-22 15:12:18','2026-07-22 15:12:18'),(66,15,41,'ticket_closed','Tiket Ditutup','Tiket AR-2026-0041 berhasil ditutup. Terima kasih atas penilaian Anda (5/5).',NULL,'2026-07-22 15:12:43','2026-07-22 15:12:43'),(67,15,47,'ticket_created','Tiket Dibuat','Tiket SR-2026-0047 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-22 15:16:34','2026-07-22 15:16:34'),(68,4,47,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0047 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-22 15:16:34','2026-07-22 15:16:34'),(69,15,47,'ticket_reopened','Perlu Diperbaiki','Tiket SR-2026-0047 dikembalikan untuk diperbaiki: tolong masih ada yang perlu di perbaikin','2026-07-22 15:17:21','2026-07-22 15:17:12','2026-07-22 15:17:21'),(70,15,47,'ticket_created','Tiket Dikirim','Tiket SR-2026-0047 berhasil dikirim dan menunggu persetujuan.',NULL,'2026-07-22 15:17:33','2026-07-22 15:17:33'),(71,4,47,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0047 dari Andi Pratama menunggu persetujuan Anda.','2026-07-22 15:17:44','2026-07-22 15:17:33','2026-07-22 15:17:44'),(72,15,47,'ticket_approved','Tiket Disetujui','Tiket SR-2026-0047 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-22 15:17:55','2026-07-22 15:17:55'),(73,15,39,'ticket_resolved','Tiket Diselesaikan','Tiket INC-2026-0039 telah diselesaikan oleh Tim Support: okee kelar nihh','2026-07-22 15:18:41','2026-07-22 15:18:23','2026-07-22 15:18:41'),(74,15,39,'ticket_closed','Tiket Ditutup','Tiket INC-2026-0039 berhasil ditutup. Terima kasih atas penilaian Anda (5/5).',NULL,'2026-07-22 15:18:47','2026-07-22 15:18:47'),(75,15,8,'sla_warning','SLA Warning','Tiket INC-2026-0008 \"Internet lambat — NETWORK\" mendekati batas waktu SLA.',NULL,'2026-07-23 02:49:05','2026-07-23 02:49:05'),(76,15,16,'sla_warning','SLA Warning','Tiket INC-2026-0016 \"Maintain Reconcilliation Account for Customer — SAP\" mendekati batas waktu SLA.',NULL,'2026-07-23 02:49:05','2026-07-23 02:49:05'),(77,15,18,'sla_warning','SLA Warning','Tiket INC-2026-0018 \"Bakar Ayam — Jual Ayam\" mendekati batas waktu SLA.',NULL,'2026-07-23 02:49:05','2026-07-23 02:49:05'),(78,15,31,'sla_warning','SLA Warning','Tiket INC-2026-0031 \"Bakar Ayam\" mendekati batas waktu SLA.',NULL,'2026-07-23 02:49:05','2026-07-23 02:49:05'),(79,15,7,'sla_breach','SLA Breach','Tiket INC-2026-0007 \"Sinyal WiFi lemah — NETWORK\" sudah melewati batas SLA.',NULL,'2026-07-23 02:49:05','2026-07-23 02:49:05'),(80,15,16,'sla_breach','SLA Breach','Tiket INC-2026-0016 \"Maintain Reconcilliation Account for Customer — SAP\" sudah melewati batas SLA.',NULL,'2026-07-23 06:27:10','2026-07-23 06:27:10'),(81,15,18,'sla_breach','SLA Breach','Tiket INC-2026-0018 \"Bakar Ayam — Jual Ayam\" sudah melewati batas SLA.',NULL,'2026-07-23 06:27:10','2026-07-23 06:27:10'),(82,15,48,'ticket_created','Tiket Dibuat','Tiket SR-2026-0048 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-23 06:29:15','2026-07-23 06:29:15'),(83,4,48,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0048 dari Andi Pratama menunggu persetujuan Anda.','2026-07-23 06:32:04','2026-07-23 06:29:15','2026-07-23 06:32:04'),(84,15,48,'ticket_reopened','Perlu Diperbaiki','Tiket SR-2026-0048 dikembalikan untuk diperbaiki: pemayy','2026-07-23 06:32:31','2026-07-23 06:32:20','2026-07-23 06:32:31'),(85,15,8,'sla_breach','SLA Breach','Tiket INC-2026-0008 \"Internet lambat — NETWORK\" sudah melewati batas SLA.',NULL,'2026-07-23 06:42:41','2026-07-23 06:42:41'),(86,15,50,'ticket_created','Tiket Dibuat','Tiket SR-2026-0050 berhasil dibuat dan menunggu persetujuan.','2026-07-23 06:48:18','2026-07-23 06:46:50','2026-07-23 06:48:18'),(87,4,50,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0050 dari Andi Pratama menunggu persetujuan Anda.','2026-07-23 06:48:06','2026-07-23 06:46:50','2026-07-23 06:48:06'),(88,15,50,'ticket_reopened','Perlu Diperbaiki','Tiket SR-2026-0050 dikembalikan untuk diperbaiki: okok',NULL,'2026-07-23 06:48:41','2026-07-23 06:48:41'),(89,15,48,'ticket_created','Tiket Dikirim','Tiket SR-2026-0048 berhasil dikirim dan menunggu persetujuan.',NULL,'2026-07-23 06:57:11','2026-07-23 06:57:11'),(90,4,48,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0048 dari Andi Pratama menunggu persetujuan Anda.',NULL,'2026-07-23 06:57:11','2026-07-23 06:57:11'),(91,15,48,'ticket_approved','Tiket Disetujui','Tiket SR-2026-0048 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-23 06:58:17','2026-07-23 06:58:17'),(92,15,33,'sla_warning','SLA Warning','Tiket INC-2026-0033 \"Unit Kerja Belum Update\" mendekati batas waktu SLA.',NULL,'2026-07-23 07:13:22','2026-07-23 07:13:22'),(93,15,48,'ticket_resolved','Tiket Diselesaikan','Tiket SR-2026-0048 telah diselesaikan oleh Tim Support: whaamm','2026-07-23 07:15:03','2026-07-23 07:14:54','2026-07-23 07:15:03'),(94,15,48,'ticket_reopened','Tiket Dibuka Kembali','Tiket SR-2026-0048 dibuka kembali dan dikirim ke Tim Support untuk penanganan lanjutan.',NULL,'2026-07-23 07:15:18','2026-07-23 07:15:18'),(95,15,48,'ticket_resolved','Tiket Diselesaikan','Tiket SR-2026-0048 telah diselesaikan oleh Tim Support: zhaap bahaap cayttt','2026-07-23 07:15:54','2026-07-23 07:15:49','2026-07-23 07:15:54'),(96,15,48,'ticket_closed','Tiket Ditutup','Tiket SR-2026-0048 berhasil ditutup. Terima kasih atas penilaian Anda (1/5).',NULL,'2026-07-23 07:16:05','2026-07-23 07:16:05'),(97,15,51,'ticket_created','Tiket Dibuat','Tiket SR-2026-0051 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-23 07:20:42','2026-07-23 07:20:42'),(98,4,51,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0051 dari Andi Pratama menunggu persetujuan Anda.','2026-07-23 07:20:55','2026-07-23 07:20:42','2026-07-23 07:20:55'),(99,15,51,'ticket_rejected','Tiket Ditolak','Tiket SR-2026-0051 ditolak: cinta suciku kau bauang2','2026-07-23 07:21:26','2026-07-23 07:21:20','2026-07-23 07:21:26'),(100,15,52,'ticket_created','Tiket Dibuat','Tiket INC-2026-0052 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-23 07:27:15','2026-07-23 07:27:15'),(101,4,52,'waiting_decision','Menunggu Keputusan Anda','Tiket INC-2026-0052 dari Andi Pratama menunggu persetujuan Anda.','2026-07-23 07:27:30','2026-07-23 07:27:15','2026-07-23 07:27:30'),(102,15,52,'ticket_approved','Tiket Disetujui','Tiket INC-2026-0052 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-23 07:28:10','2026-07-23 07:28:10'),(103,15,53,'ticket_created','Tiket Dibuat','Tiket INC-2026-0053 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-23 07:49:09','2026-07-23 07:49:09'),(104,15,54,'ticket_created','Tiket Dibuat','Tiket INC-2026-0054 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-23 07:49:57','2026-07-23 07:49:57'),(105,15,55,'ticket_created','Tiket Dibuat','Tiket SR-2026-0055 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-23 08:28:40','2026-07-23 08:28:40'),(106,4,55,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0055 dari Andi Pratama menunggu persetujuan Anda.','2026-07-23 08:28:50','2026-07-23 08:28:40','2026-07-23 08:28:50'),(107,15,55,'ticket_approved','Tiket Disetujui','Tiket SR-2026-0055 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-23 08:28:56','2026-07-23 08:28:56'),(108,15,55,'ticket_resolved','Tiket Diselesaikan','Tiket SR-2026-0055 telah diselesaikan oleh Tim Support: ok','2026-07-23 08:29:57','2026-07-23 08:29:51','2026-07-23 08:29:57'),(109,15,55,'ticket_reopened','Tiket Dibuka Kembali','Tiket SR-2026-0055 dibuka kembali dan dikirim ke Tim Support untuk penanganan lanjutan.',NULL,'2026-07-23 08:30:07','2026-07-23 08:30:07'),(110,15,56,'ticket_created','Tiket Dibuat','Tiket SR-2026-0056 berhasil dibuat dan menunggu persetujuan.',NULL,'2026-07-23 08:30:46','2026-07-23 08:30:46'),(111,4,56,'waiting_decision','Menunggu Keputusan Anda','Tiket SR-2026-0056 dari Andi Pratama menunggu persetujuan Anda.','2026-07-23 08:30:53','2026-07-23 08:30:46','2026-07-23 08:30:53'),(112,15,56,'ticket_approved','Tiket Disetujui','Tiket SR-2026-0056 disetujui dan diteruskan ke Tim Support.',NULL,'2026-07-23 08:31:06','2026-07-23 08:31:06'),(113,15,56,'ticket_resolved','Tiket Diselesaikan','Tiket SR-2026-0056 telah diselesaikan oleh Tim Support: ok',NULL,'2026-07-23 08:31:26','2026-07-23 08:31:26'),(114,15,56,'ticket_reopened','Tiket Dibuka Kembali','Tiket SR-2026-0056 dibuka kembali dan dikirim ke Tim Support untuk penanganan lanjutan.',NULL,'2026-07-23 08:32:29','2026-07-23 08:32:29'),(115,15,11,'ticket_resolved','Tiket Diselesaikan','Tiket INC-2026-0011 telah diselesaikan oleh Tim Support: Sudah saya perbaiki posting periodnya.',NULL,'2026-07-23 08:46:37','2026-07-23 08:46:37'),(116,19,11,'ticket_reopened','Tiket Dibuka Kembali','Tiket INC-2026-0011 dibuka kembali oleh Andi Pratama: Masih error saat posting periode Juli, mohon dicek lagi.','2026-07-23 08:50:07','2026-07-23 08:48:15','2026-07-23 08:50:07'),(117,15,11,'ticket_resolved','Tiket Diselesaikan','Tiket INC-2026-0011 telah diselesaikan oleh Tim Support: okok','2026-07-23 08:49:46','2026-07-23 08:49:38','2026-07-23 08:49:46'),(118,15,11,'ticket_closed','Tiket Ditutup','Tiket INC-2026-0011 berhasil ditutup. Terima kasih atas penilaian Anda (5/5).',NULL,'2026-07-23 08:49:53','2026-07-23 08:49:53'),(119,15,57,'ticket_created','Tiket Dibuat','Tiket INC-2026-0057 berhasil dibuat dan dikirim ke Tim Support.',NULL,'2026-07-23 08:51:02','2026-07-23 08:51:02'),(120,25,57,'ticket_created','Tiket Baru Ditugaskan','Tiket INC-2026-0057 \"Data profil tidak sesuai\" telah ditugaskan ke Anda.','2026-07-23 08:51:21','2026-07-23 08:51:02','2026-07-23 08:51:21'),(121,15,57,'ticket_escalated','Tiket Dieskalasi','Tiket INC-2026-0057 telah dieskalasi ke Tim IT Lanjutan.',NULL,'2026-07-23 08:51:29','2026-07-23 08:51:29'),(122,21,57,'ticket_incoming_escalation','Tiket Eskalasi Masuk','Tiket INC-2026-0057 dieskalasi dari Support BPO (Rio Saputra): oaaooaaoo','2026-07-23 08:52:37','2026-07-23 08:51:29','2026-07-23 08:52:37'),(123,15,57,'ticket_resolved','Tiket Diselesaikan','Tiket INC-2026-0057 telah diselesaikan oleh Tim Support: okoko',NULL,'2026-07-23 08:52:50','2026-07-23 08:52:50'),(124,15,58,'ticket_created','Tiket Dikirim','Tiket AR-2026-0058 berhasil dikirim dan menunggu persetujuan.','2026-07-23 09:13:14','2026-07-23 09:12:16','2026-07-23 09:13:14'),(125,4,58,'waiting_decision','Menunggu Keputusan Anda','Tiket AR-2026-0058 dari Andi Pratama menunggu persetujuan Anda.','2026-07-23 09:12:48','2026-07-23 09:12:16','2026-07-23 09:12:48'),(126,15,58,'ticket_reopened','Perlu Diperbaiki','Tiket AR-2026-0058 dikembalikan untuk diperbaiki: ok','2026-07-23 09:14:06','2026-07-23 09:13:58','2026-07-23 09:14:06'),(127,15,58,'ticket_created','Tiket Dikirim','Tiket AR-2026-0058 berhasil dikirim dan menunggu persetujuan.',NULL,'2026-07-23 09:14:21','2026-07-23 09:14:21'),(128,4,58,'waiting_decision','Menunggu Keputusan Anda','Tiket AR-2026-0058 dari Andi Pratama menunggu persetujuan Anda.','2026-07-23 09:14:39','2026-07-23 09:14:21','2026-07-23 09:14:39'),(129,19,15,'discussion_message','Pesan Baru di Diskusi Tiket','Andi Pratama (Requester) di tiket INC-2026-0015: Halo, ada update soal tiket ini?',NULL,'2026-07-23 09:22:14','2026-07-23 09:22:14'),(130,15,15,'discussion_message','Pesan Baru di Diskusi Tiket','Agung Wijayanto (Support IT) di tiket INC-2026-0015: gada lee pukimay','2026-07-23 09:31:29','2026-07-23 09:31:22','2026-07-23 09:31:29'),(131,15,31,'sla_breach','SLA Breach','Tiket INC-2026-0031 \"Bakar Ayam\" sudah melewati batas SLA.',NULL,'2026-07-23 09:31:25','2026-07-23 09:31:25'),(132,15,6,'discussion_message','Pesan Baru di Diskusi Tiket','Karina Putri (Approver) di tiket SR-2026-0006: apa we','2026-07-23 09:33:32','2026-07-23 09:33:18','2026-07-23 09:33:32'),(133,21,6,'discussion_message','Pesan Baru di Diskusi Tiket','Karina Putri (Approver) di tiket SR-2026-0006: apa we',NULL,'2026-07-23 09:33:18','2026-07-23 09:33:18'),(134,4,6,'discussion_message','Pesan Baru di Diskusi Tiket','Andi Pratama (Requester) di tiket SR-2026-0006: lapo',NULL,'2026-07-23 09:33:37','2026-07-23 09:33:37'),(135,21,6,'discussion_message','Pesan Baru di Diskusi Tiket','Andi Pratama (Requester) di tiket SR-2026-0006: lapo',NULL,'2026-07-23 09:33:37','2026-07-23 09:33:37');
/*!40000 ALTER TABLE `ticket_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_no` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `subcategory_name` varchar(255) DEFAULT NULL,
  `subject_name` varchar(255) DEFAULT NULL,
  `issue_category` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requester_name` varchar(255) NOT NULL,
  `requester_id` bigint(20) unsigned DEFAULT NULL,
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_agent_id` bigint(20) unsigned DEFAULT NULL,
  `catalog_subject_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `sla_policy_id` bigint(20) unsigned NOT NULL,
  `priority` varchar(255) NOT NULL,
  `response_time_minutes` int(10) unsigned NOT NULL,
  `resolution_time_minutes` int(10) unsigned NOT NULL,
  `warning_threshold_percent` tinyint(3) unsigned NOT NULL,
  `response_due_at` datetime NOT NULL,
  `resolution_due_at` datetime NOT NULL,
  `warning_at` datetime NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Open',
  `is_draft` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_at` datetime DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `escalation_note` text DEFAULT NULL,
  `reopen_note` text DEFAULT NULL,
  `reopen_at` timestamp NULL DEFAULT NULL,
  `satisfaction_rating` tinyint(3) unsigned DEFAULT NULL,
  `feedback_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tickets_ticket_no_unique` (`ticket_no`),
  KEY `tickets_sla_policy_id_foreign` (`sla_policy_id`),
  KEY `tickets_requester_id_foreign` (`requester_id`),
  KEY `tickets_approver_id_foreign` (`approver_id`),
  KEY `tickets_assigned_agent_id_foreign` (`assigned_agent_id`),
  KEY `tickets_catalog_subject_id_foreign` (`catalog_subject_id`),
  CONSTRAINT `tickets_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_assigned_agent_id_foreign` FOREIGN KEY (`assigned_agent_id`) REFERENCES `support_agents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_catalog_subject_id_foreign` FOREIGN KEY (`catalog_subject_id`) REFERENCES `service_catalog_subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_requester_id_foreign` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_sla_policy_id_foreign` FOREIGN KEY (`sla_policy_id`) REFERENCES `sla_policies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (1,'SR-2026-0001','Koreksi data / file — SILO APPS','SILO APPS','DATA & DOKUMEN','Koreksi data / file','Service Request','Permintaan/laporan terkait Koreksi data / file pada SILO APPS.','Andi Pratama',15,4,13,234,'Service Request',4,'Low',1440,7200,70,'2026-07-22 13:35:48','2026-07-26 13:35:48','2026-07-25 01:35:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:35:48','2026-07-22 00:12:38'),(2,'SR-2026-0002','Bantuan Input Transaksi (PR / PO / GR / GI / MIRO) — SAP','SAP','TRANSACTION SUPPORT','Bantuan Input Transaksi (PR / PO / GR / GI / MIRO)','Service Request','Permintaan/laporan terkait Bantuan Input Transaksi (PR / PO / GR / GI / MIRO) pada SAP.','Andi Pratama',15,4,16,213,'Service Request',3,'Medium',480,2880,75,'2026-07-21 22:10:48','2026-07-23 14:10:48','2026-07-23 02:10:48','Draft',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 00:10:48','2026-07-22 01:21:18'),(3,'SR-2026-0003','Perubahan Alur Approval — SAP','SAP','WORKFLOW SERVICE','Perubahan Alur Approval','Service Request','Permintaan/laporan terkait Perubahan Alur Approval pada SAP.','Andi Pratama',15,4,13,218,'Service Request',4,'Low',1440,7200,70,'2026-07-22 12:40:48','2026-07-26 12:40:48','2026-07-25 00:40:48','Draft',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 22:40:48','2026-07-22 00:15:44'),(4,'SR-2026-0004','Setup Workflow Baru — SAP','SAP','WORKFLOW SERVICE','Setup Workflow Baru','Service Request','Permintaan/laporan terkait Setup Workflow Baru pada SAP.','Andi Pratama',15,4,16,221,'Service Request',1,'Critical',60,180,75,'2026-07-21 14:36:48','2026-07-21 16:36:48','2026-07-21 15:51:48','Waiting for Approval',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:36:48','2026-07-21 12:29:16'),(5,'SR-2026-0005','Penjelasan fitur sistem — SILO APPS','SILO APPS','KONSULTASI & BANTUAN','Penjelasan fitur sistem','Service Request','Permintaan/laporan terkait Penjelasan fitur sistem pada SILO APPS.','Andi Pratama',15,4,19,248,'Service Request',3,'Medium',480,2880,75,'2026-07-21 15:39:48','2026-07-23 07:39:48','2026-07-22 19:39:48','Rejected',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 17:39:48','2026-07-22 01:18:33'),(6,'SR-2026-0006','Permintaan koneksi internet tambahan — SILO APPS','SILO APPS','JARINGAN & KONEKSI','Permintaan koneksi internet tambahan','Service Request','Permintaan/laporan terkait Permintaan koneksi internet tambahan pada SILO APPS.','Andi Pratama',15,4,18,239,'Service Request',3,'Medium',480,2880,75,'2026-07-21 17:39:48','2026-07-23 09:39:48','2026-07-22 21:39:48','Waiting for Approval',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 19:39:48','2026-07-21 12:29:16'),(7,'INC-2026-0007','Sinyal WiFi lemah — NETWORK','NETWORK','WiFi','Sinyal WiFi lemah','Incident','Permintaan/laporan terkait Sinyal WiFi lemah pada NETWORK.','Andi Pratama',15,NULL,20,200,'Incident',3,'Medium',480,2880,75,'2026-07-21 15:59:48','2026-07-23 07:59:48','2026-07-22 19:59:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 17:59:48','2026-07-21 12:29:16'),(8,'INC-2026-0008','Internet lambat — NETWORK','NETWORK','INTERNET','Internet lambat','Incident','Permintaan/laporan terkait Internet lambat pada NETWORK.','Andi Pratama',15,NULL,17,197,'Incident',3,'Medium',480,2880,75,'2026-07-21 21:36:48','2026-07-23 13:36:48','2026-07-23 01:36:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:36:48','2026-07-21 12:29:16'),(9,'INC-2026-0009','Tidak Bisa Terima Email — MAILIA','MAILIA','Email Service & Operations','Tidak Bisa Terima Email','Incident','Permintaan/laporan terkait Tidak Bisa Terima Email pada MAILIA.','Andi Pratama',15,NULL,13,269,'Incident',2,'High',120,360,80,'2026-07-21 12:15:48','2026-07-21 16:15:48','2026-07-21 15:03:48','Closed',0,'2026-07-22 20:36:07',NULL,NULL,NULL,NULL,5,NULL,'2026-07-20 20:15:48','2026-07-22 14:35:31'),(10,'INC-2026-0010','Gagal Print — SAP','SAP','PRINTING','Gagal Print','Incident','Permintaan/laporan terkait Gagal Print pada SAP.','Andi Pratama',15,NULL,18,146,'Incident',4,'Low',1440,7200,70,'2026-07-22 13:02:48','2026-07-26 13:02:48','2026-07-25 01:02:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:02:48','2026-07-21 12:29:16'),(11,'INC-2026-0011','Posting period is not open for variant — SAP','SAP','TRANSAKSI MM','Posting period is not open for variant','Incident','Permintaan/laporan terkait Posting period is not open for variant pada SAP.','Andi Pratama',15,NULL,16,160,'Incident',1,'Critical',60,180,75,'2026-07-21 08:46:48','2026-07-21 10:46:48','2026-07-21 10:01:48','Closed',0,'2026-07-23 15:49:38',NULL,NULL,NULL,NULL,5,'lppl;','2026-07-20 17:46:48','2026-07-23 08:49:53'),(12,'INC-2026-0012','Reschedule jadwal tender — ELISA','ELISA','EPROCUREMENT','Reschedule jadwal tender','Incident','Permintaan/laporan terkait Reschedule jadwal tender pada ELISA.','Andi Pratama',15,NULL,19,179,'Incident',2,'High',120,360,80,'2026-07-21 10:09:48','2026-07-21 14:09:48','2026-07-21 12:57:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 18:09:48','2026-07-21 12:29:16'),(13,'INC-2026-0013','Printer offline — PERANGKAT','PERANGKAT','PRINTER JARINGAN','Printer offline','Incident','Permintaan/laporan terkait Printer offline pada PERANGKAT.','Andi Pratama',15,NULL,19,208,'Incident',2,'High',120,360,80,'2026-07-21 14:15:48','2026-07-21 18:15:48','2026-07-21 17:03:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 22:15:48','2026-07-21 12:29:16'),(14,'AR-2026-0014','Aktivasi/ Unlock akun — AKUN APLIKASI','AKUN APLIKASI','SAP','Aktivasi/ Unlock akun','Access Request','Permintaan/laporan terkait Aktivasi/ Unlock akun pada AKUN APLIKASI.','Andi Pratama',15,NULL,13,251,'Access Request',4,'Low',1440,7200,70,'2026-07-22 12:44:48','2026-07-26 12:44:48','2026-07-25 00:44:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 22:44:48','2026-07-21 12:29:16'),(15,'INC-2026-0015','Akses Ditolak pada Transaksi — SAP','SAP','AUTHORIZATION','Akses Ditolak pada Transaksi','Incident','Permintaan/laporan terkait Akses Ditolak pada Transaksi pada SAP.','Andi Pratama',15,NULL,16,136,'Incident',2,'High',120,360,80,'2026-07-21 16:55:48','2026-07-21 20:55:48','2026-07-21 19:43:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 00:55:48','2026-07-21 12:29:16'),(16,'INC-2026-0016','Maintain Reconcilliation Account for Customer — SAP','SAP','TRANSAKSI FICO','Maintain Reconcilliation Account for Customer','Incident','Permintaan/laporan terkait Maintain Reconcilliation Account for Customer pada SAP.','Andi Pratama',15,NULL,17,169,'Incident',3,'Medium',480,2880,75,'2026-07-21 19:18:48','2026-07-23 11:18:48','2026-07-22 23:18:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 21:18:48','2026-07-21 12:29:16'),(17,'INC-2026-0017','Data Tidak Sinkron — SAP','SAP','DATA','Data Tidak Sinkron','Incident','Permintaan/laporan terkait Data Tidak Sinkron pada SAP.','Andi Pratama',15,NULL,18,154,'Incident',1,'Critical',60,180,75,'2026-07-21 16:12:48','2026-07-21 18:12:48','2026-07-21 17:27:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 01:12:48','2026-07-21 12:29:16'),(18,'INC-2026-0018','Bakar Ayam — Jual Ayam','Jual Ayam','EPROCUREMENT','Bakar Ayam','Incident','Permintaan/laporan terkait Bakar Ayam pada Jual Ayam.','Andi Pratama',15,NULL,25,266,'Incident',3,'Medium',480,2880,75,'2026-07-21 20:39:48','2026-07-23 12:39:48','2026-07-23 00:39:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 22:39:48','2026-07-22 14:11:04'),(19,'INC-2026-0019','Printer tidak ditemukan — PERANGKAT','PERANGKAT','PRINTER JARINGAN','Printer tidak ditemukan','Incident','Permintaan/laporan terkait Printer tidak ditemukan pada PERANGKAT.','Andi Pratama',15,NULL,18,207,'Incident',4,'Low',1440,7200,70,'2026-07-22 08:49:48','2026-07-26 08:49:48','2026-07-24 20:49:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 18:49:48','2026-07-21 12:29:16'),(20,'INC-2026-0020','User Locked — SAP','SAP','LOGIN SAP','User Locked','Incident','Permintaan/laporan terkait User Locked pada SAP.','Andi Pratama',15,NULL,14,134,'Incident',4,'Low',1440,7200,70,'2026-07-22 08:56:48','2026-07-26 08:56:48','2026-07-24 20:56:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 18:56:48','2026-07-21 12:29:16'),(21,'INC-2026-0021','Year end Closing preparation — SAP','SAP','TRANSAKSI FICO','Year end Closing preparation','Incident','Permintaan/laporan terkait Year end Closing preparation pada SAP.','Andi Pratama',15,NULL,20,172,'Incident',2,'High',120,360,80,'2026-07-21 15:13:48','2026-07-21 19:13:48','2026-07-21 18:01:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:13:48','2026-07-21 12:29:16'),(22,'INC-2026-0022','Reschedule jadwal tender — ELISA','ELISA','EPROCUREMENT','Reschedule jadwal tender','Incident','Permintaan/laporan terkait Reschedule jadwal tender pada ELISA.','Andi Pratama',15,NULL,19,179,'Incident',1,'Critical',60,180,75,'2026-07-21 15:55:48','2026-07-21 17:55:48','2026-07-21 17:10:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 00:55:48','2026-07-21 12:29:16'),(23,'INC-2026-0023','Gagal Print — SAP','SAP','PRINTING','Gagal Print','Incident','Permintaan/laporan terkait Gagal Print pada SAP.','Andi Pratama',15,NULL,18,146,'Incident',4,'Low',1440,7200,70,'2026-07-22 07:37:48','2026-07-26 07:37:48','2026-07-24 19:37:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 17:37:48','2026-07-21 12:29:16'),(24,'SR-2026-0024','Permintaan lain-lain — ARISE','ARISE','Other','Lainnya (belum ada di katalog)','Service Request','Permintaan di luar daftar layanan yang tersedia pada katalog saat ini.','Andi Pratama',15,NULL,NULL,NULL,'Service Request',1,'Critical',60,180,75,'2026-07-21 09:21:48','2026-07-21 11:21:48','2026-07-21 10:36:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 18:21:48','2026-07-20 18:21:48'),(25,'SR-2026-0025','Permintaan lain-lain — ARISE','ARISE','Other','Lainnya (belum ada di katalog)','Service Request','Permintaan di luar daftar layanan yang tersedia pada katalog saat ini.','Andi Pratama',15,NULL,NULL,NULL,'Service Request',2,'High',120,360,80,'2026-07-21 15:21:48','2026-07-21 19:21:48','2026-07-21 18:09:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:21:48','2026-07-20 23:21:48'),(26,'AR-2026-0026','Permintaan lain-lain — ARINA (DASHBOARD)','ARINA (DASHBOARD)','Other','Lainnya (belum ada di katalog)','Access Request','Permintaan di luar daftar layanan yang tersedia pada katalog saat ini.','Andi Pratama',15,NULL,NULL,NULL,'Access Request',2,'High',120,360,80,'2026-07-21 12:57:48','2026-07-21 16:57:48','2026-07-21 15:45:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 20:57:48','2026-07-20 20:57:48'),(27,'INC-2026-0027','Permintaan lain-lain — ARINA (DASHBOARD)','ARINA (DASHBOARD)','Other','Lainnya (belum ada di katalog)','Incident','Permintaan di luar daftar layanan yang tersedia pada katalog saat ini.','Andi Pratama',15,NULL,NULL,NULL,'Incident',1,'Critical',60,180,75,'2026-07-21 13:22:48','2026-07-21 15:22:48','2026-07-21 14:37:48','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 22:22:48','2026-07-20 22:22:48'),(28,'INC-2026-0028','Budget Update (Revisi Budget berbeda dengan proses sebelumnya) — SAP','SAP','TRANSAKSI PS','Budget Update (Revisi Budget berbeda dengan proses sebelumnya)','Incident','Permintaan/laporan terkait Budget Update (Revisi Budget berbeda dengan proses sebelumnya) pada SAP.','Andi Pratama',15,NULL,19,163,'Incident',1,'Critical',60,180,75,'2026-07-21 10:31:48','2026-07-21 12:31:48','2026-07-21 11:46:48','Draft',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 19:31:48','2026-07-21 12:29:16'),(29,'SR-2026-0029','Permintaan analisis data','SILO APPS','DATA & DOKUMEN','Permintaan analisis data','Service Request','Permintaan/laporan terkait Permintaan analisis data pada SILO APPS. jadi','Andi Pratama',15,4,24,233,'Service Request',3,'Medium',480,2880,75,'2026-07-22 16:12:48','2026-07-24 08:12:48','2026-07-23 20:12:48','Waiting for Approval',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 20:16:48','2026-07-22 01:12:48'),(30,'SR-2026-0030','Konsultasi Alur Approval / Workflow','SAP','LAYANAN KONSULTASI','Konsultasi Alur Approval / Workflow','Service Request','Permintaan/laporan terkait Konsultasi Alur Approval / Workflow pada SAP.','Andi Pratama',15,4,20,225,'Service Request',3,'Medium',480,2880,75,'2026-07-22 09:48:15','2026-07-24 01:48:15','2026-07-23 13:48:15','Waiting for Approval',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-20 20:12:48','2026-07-21 12:29:16'),(31,'INC-2026-0031','Bakar Ayam','Jual Ayam','EPROCUREMENT','Bakar Ayam','Incident','woy','Andi Pratama',15,NULL,21,266,'Incident',3,'Medium',480,2880,75,'2026-07-22 00:22:53','2026-07-23 16:22:53','2026-07-23 04:22:53','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 02:22:53','2026-07-21 12:29:16'),(32,'INC-2026-0032','Email / Outlook Bermasalah','MAILIA','Email Service & Operations','Email / Outlook Bermasalah','Incident','test','Andi Pratama',15,NULL,16,270,'Incident',4,'Low',1440,7200,70,'2026-07-22 16:23:12','2026-07-26 16:23:12','2026-07-25 04:23:12','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 02:23:12','2026-07-21 12:35:06'),(33,'INC-2026-0033','Unit Kerja Belum Update','ADELE','Account & Profile Issues','Unit Kerja Belum Update','Incident','woy','Andi Pratama',15,NULL,15,183,'Incident',3,'Medium',480,2880,75,'2026-07-22 10:01:56','2026-07-24 02:01:56','2026-07-23 14:01:56','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 12:01:56','2026-07-21 12:29:16'),(34,'INC-2026-0034','VITOPLANKTON','APALAH MAS VITO','Hama','VITOPLANKTON','Service Request','woi vito lancang','Andi Pratama',15,NULL,13,276,'Service Request',4,'Low',1440,7200,70,'2026-07-23 02:41:42','2026-07-27 02:41:42','2026-07-25 14:41:42','Open',0,NULL,'2026-07-22 13:40:58','Perlu penanganan lanjutan dari tim IT untuk akses server.',NULL,NULL,NULL,NULL,'2026-07-21 12:41:18','2026-07-22 13:40:58'),(35,'INC-2026-0035','level 2','sambal bakar','geprek','level 2','Incident','adadada','Andi Pratama',15,4,17,277,'Incident',3,'Medium',480,2880,75,'2026-07-22 11:35:14','2026-07-24 03:35:14','2026-07-23 15:35:14','Waiting for Approval',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 13:35:14','2026-07-21 13:35:14'),(36,'INC-2026-0036','subject1','nyoba 1','sub1','subject1','Incident','adadadada','Andi Pratama',15,4,21,279,'Incident',3,'Medium',480,2880,75,'2026-07-22 12:20:19','2026-07-24 04:20:19','2026-07-23 16:20:19','Waiting for Approval',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 14:20:19','2026-07-21 14:20:19'),(37,'SR-2026-0037','Konsultasi Alur Approval / Workflow','SAP','LAYANAN KONSULTASI','Konsultasi Alur Approval / Workflow','Service Request','Tes alur workflow requester ke approver end-to-end.','Andi Pratama',15,4,23,225,'Service Request',3,'Medium',480,2880,75,'2026-07-22 15:23:15','2026-07-24 07:23:15','2026-07-23 19:23:15','Waiting for Approval',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 00:23:15','2026-07-22 00:23:15'),(38,'SR-2026-0038','Tes notifikasi approver',NULL,NULL,NULL,'Service Request','Tes notifikasi waiting_decision ke approver.','Andi Pratama',15,4,23,209,'Service Request',3,'Medium',480,2880,75,'2026-07-22 15:28:39','2026-07-24 07:28:39','2026-07-23 19:28:39','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 00:28:39','2026-07-22 01:00:52'),(39,'INC-2026-0039','Tes routing Level 2 BPO+IT','SAP',NULL,NULL,'Incident','Tes forwarded_to Level 2.','Andi Pratama',15,4,13,155,'Incident',3,'Medium',480,2880,75,'2026-07-22 16:15:22','2026-07-24 08:15:22','2026-07-23 20:15:22','Closed',0,'2026-07-22 22:18:23','2026-07-22 14:21:50','Perlu pengecekan konfigurasi release strategy SAP MM, di luar kewenangan BPO.',NULL,NULL,5,NULL,'2026-07-22 01:15:22','2026-07-22 15:18:47'),(40,'SR-2026-0040','Tes audit trail approval',NULL,NULL,NULL,'Service Request','Tes audit trail.','Andi Pratama',15,4,23,209,'Service Request',3,'Medium',480,2880,75,'2026-07-22 16:35:06','2026-07-24 08:35:06','2026-07-23 20:35:06','Rejected',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 01:35:06','2026-07-22 01:35:43'),(41,'AR-2026-0041','Pembuatan akun baru','AKUN APLIKASI','SAP','Pembuatan akun baru','Access Request','lokkkk','Andi Pratama',15,4,25,250,'Access Request',3,'Medium',480,2880,75,'2026-07-22 16:45:27','2026-07-24 08:45:27','2026-07-23 20:45:27','Closed',0,'2026-07-22 22:12:18',NULL,NULL,NULL,NULL,5,NULL,'2026-07-22 01:43:28','2026-07-22 15:12:43'),(42,'SR-2026-0042','Instalasi / reinstall aplikasi','SILO APPS','APLIKASI & SOFTWARE','Instalasi / reinstall aplikasi','Service Request',NULL,'Andi Pratama',15,4,16,229,'Service Request',4,'Low',1440,7200,70,'2026-07-23 08:47:42','2026-07-27 08:47:42','2026-07-25 20:47:42','Rejected',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 01:47:18','2026-07-22 14:56:35'),(43,'SR-2026-0043','Tes waktu WIB',NULL,NULL,NULL,'Service Request','Tes timezone.','Andi Pratama',15,NULL,23,209,'Service Request',3,'Medium',480,2880,75,'2026-07-22 23:53:44','2026-07-24 15:53:44','2026-07-24 03:53:44','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 08:53:44','2026-07-22 08:53:44'),(44,'INC-2026-0044','Email / Outlook Bermasalah','MAILIA','Email Service & Operations','Email / Outlook Bermasalah','Incident','Tes end-to-end: create ticket sampai closed via verifikasi Claude.','Andi Pratama',15,NULL,16,270,'Incident',4,'Low',1440,7200,70,'2026-07-23 21:40:16','2026-07-27 21:40:16','2026-07-26 09:40:16','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 14:40:16','2026-07-22 14:56:35'),(45,'AR-2026-0045','Penambahan akses aplikasi','PERUBAHAN AKSES APLIKASI','SAP','Penambahan akses aplikasi','Access Request','Tes end-to-end lengkap: create -> approve -> BPO -> escalate IT -> resolve -> close.','Andi Pratama',15,4,13,259,'Access Request',4,'Low',1440,7200,70,'2026-07-23 21:47:51','2026-07-27 21:47:51','2026-07-26 09:47:51','Closed',0,'2026-07-22 21:51:39',NULL,NULL,NULL,NULL,5,'Pelayanan cepat dan solutif, terima kasih!','2026-07-22 14:47:51','2026-07-22 15:35:12'),(46,'INC-2026-0046','Email / Outlook Bermasalah','MAILIA','Email Service & Operations','Email / Outlook Bermasalah','Incident','Tes fallback it_agent_id setelah fix.','Andi Pratama',15,NULL,16,270,'Incident',4,'Low',1440,7200,70,'2026-07-23 21:59:59','2026-07-27 21:59:59','2026-07-26 09:59:59','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 14:59:59','2026-07-22 14:59:59'),(47,'SR-2026-0047','Koreksi Data Master','SAP','MASTER DATA','Koreksi Data Master','Service Request','jadi gini saya sudah perbaiki','Andi Pratama',15,4,21,211,'Service Request',3,'Medium',480,2880,75,'2026-07-23 06:17:33','2026-07-24 22:17:33','2026-07-24 10:17:33','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 15:16:34','2026-07-22 15:17:55'),(48,'SR-2026-0048','Instalasi / reinstall aplikasi','SILO APPS','APLIKASI & SOFTWARE','Instalasi / reinstall aplikasi','Service Request','puki pemay telaso','Andi Pratama',15,4,16,229,'Service Request',1,'Critical',120,180,75,'2026-07-23 15:57:11','2026-07-23 16:57:11','2026-07-23 16:12:11','Closed',0,'2026-07-23 14:15:49',NULL,NULL,NULL,NULL,1,'hama','2026-07-23 06:29:15','2026-07-23 07:16:05'),(49,'INC-2026-0049','User Session Belum Logout','ADELE','Account & Profile Issues','User Session Belum Logout','Incident','woi','Andi Pratama',15,NULL,13,181,'Incident',1,'Critical',120,180,75,'2026-07-23 15:31:36','2026-07-23 16:31:36','2026-07-23 15:46:36','Draft',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 06:31:36','2026-07-23 06:31:36'),(50,'SR-2026-0050','Perubahan setting aplikasi','SILO APPS','APLIKASI & SOFTWARE','Perubahan setting aplikasi','Service Request','oaoaoa','Andi Pratama',15,4,18,231,'Service Request',1,'Critical',120,180,75,'2026-07-23 15:46:50','2026-07-23 16:46:50','2026-07-23 16:01:50','Draft',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 06:46:50','2026-07-23 06:48:41'),(51,'SR-2026-0051','Pembuatan Data Master (Vendor / Customer / Material)','SAP','MASTER DATA','Pembuatan Data Master (Vendor / Customer / Material)','Service Request','wes nyerepo','Andi Pratama',15,4,23,209,'Service Request',4,'Low',1440,7200,70,'2026-07-24 14:20:42','2026-07-28 14:20:42','2026-07-27 02:20:42','Rejected',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 07:20:42','2026-07-23 07:21:20'),(52,'INC-2026-0052','level 2','sambal bakar','geprek','level 2','Incident','ayam gembus pak vito','Andi Pratama',15,4,17,277,'Incident',1,'Critical',120,180,75,'2026-07-23 16:27:15','2026-07-23 17:27:15','2026-07-23 16:42:15','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 07:27:15','2026-07-23 07:28:10'),(53,'INC-2026-0053','Unit Kerja Belum Update','ADELE','Account & Profile Issues','Unit Kerja Belum Update','Incident','tes tes tes','Andi Pratama',15,NULL,15,183,'Incident',1,'Critical',120,180,75,'2026-07-23 16:49:09','2026-07-23 17:49:09','2026-07-23 17:04:09','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 07:49:09','2026-07-23 07:49:09'),(54,'INC-2026-0054','Unit Kerja Belum Update','ADELE','Account & Profile Issues','Unit Kerja Belum Update','Incident','teteee','Andi Pratama',15,NULL,15,183,'Incident',3,'Medium',480,2880,75,'2026-07-23 22:49:57','2026-07-25 14:49:57','2026-07-25 02:49:57','Open',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 07:49:57','2026-07-23 07:49:57'),(55,'SR-2026-0055','Pembuatan Data Master (Vendor / Customer / Material)','SAP','MASTER DATA','Pembuatan Data Master (Vendor / Customer / Material)','Service Request','ooo','Andi Pratama',15,4,23,209,'Service Request',2,'High',120,360,80,'2026-07-23 17:28:40','2026-07-23 21:28:40','2026-07-23 20:16:40','In Progress',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 08:28:40','2026-07-23 08:30:07'),(56,'SR-2026-0056','Pembuatan Data Master (Vendor / Customer / Material)','SAP','MASTER DATA','Pembuatan Data Master (Vendor / Customer / Material)','Service Request','q','Andi Pratama',15,4,23,209,'Service Request',4,'Low',1440,7200,70,'2026-07-24 15:30:46','2026-07-28 15:30:46','2026-07-27 03:30:46','In Progress',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 08:30:46','2026-07-23 08:32:29'),(57,'INC-2026-0057','Data profil tidak sesuai','SINTA','Akses & Data Karyawan','Data profil tidak sesuai','Incident','okoko','Andi Pratama',15,NULL,18,189,'Incident',1,'Critical',120,180,75,'2026-07-23 17:51:02','2026-07-23 18:51:02','2026-07-23 18:06:02','Resolved',0,'2026-07-23 15:52:50','2026-07-23 08:51:29','oaaooaaoo',NULL,NULL,NULL,NULL,'2026-07-23 08:51:02','2026-07-23 08:52:50'),(58,'AR-2026-0058','Penonaktifan akun','AKUN APLIKASI','SAP','Penonaktifan akun','Access Request','asgygsyas','Andi Pratama',15,4,14,252,'Access Request',4,'Low',1440,7200,70,'2026-07-24 16:14:21','2026-07-28 16:14:21','2026-07-27 04:14:21','Waiting for Approval',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-23 09:10:22','2026-07-23 09:14:21');
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `nip` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `whatsapp` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `jabatan` varchar(255) DEFAULT NULL,
  `kode_proyek` varchar(255) DEFAULT NULL,
  `nama_proyek` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test User',NULL,'test@example.com',NULL,'2026-07-20 10:57:48','$2y$12$F5vSSxobT.eyjzJvXbCXN.HYKyDbDu3.a0VO0ZVUz8od1hlvVtAZe','a2aFuP8i8V','2026-07-20 10:57:49','2026-07-20 10:57:49',NULL,NULL,NULL,NULL,'active',NULL),(3,'Marcell Laforteza','19870114001','marcell.laforteza@adhi.co.id','+6281234567890','2026-07-20 13:04:17','$2y$12$ZgHXmuCqJg2XpltnmKT/zu.fcpHB8pVs.5YcGJqKR1/BmMHHHyvye',NULL,'2026-07-20 13:04:17','2026-07-20 13:04:17','Dept. Strategi Korporasi','Administrator Sistem',NULL,NULL,'active','2026-07-19 00:04:17'),(4,'Karina Putri','19900322014','karina.putri@adhi.co.id','+6281298765432','2026-07-20 13:04:17','$2y$12$cNVlMv6pxDJE6RpFfa2Rp.wTEl5Qati3zfgcwvjMXMPAtChldZf6u',NULL,'2026-07-20 13:04:17','2026-07-20 13:04:17','Dept. Pengendali Operasi','Manager Dept. Pengendali Operasi',NULL,NULL,'active','2026-07-20 01:04:17'),(5,'Rizky Hidayat','19880609027','rizky.hidayat@adhi.co.id','+6281322233344','2026-07-20 13:04:17','$2y$12$oggB2ckL.RC7Th8qcIRNa.6bA.gNt6e7BidpGnNgWO9dMVxr8z8z2',NULL,'2026-07-20 13:04:17','2026-07-20 13:04:17','Dept. Supply Chain Management','Team Lead',NULL,NULL,'active','2026-07-19 02:04:17'),(6,'Dimas Kurniawan','10021190','dimas.kurniawan@adhi.co.id','+6281811166677','2026-07-20 13:04:17','$2y$12$EdKA16E.xxBmt1jo9UJXPO2Dh1D45EYXagbLHR2SF/rJSlKUfnPCG',NULL,'2026-07-20 13:04:17','2026-07-20 13:04:17','Dept. Keuangan','Team Lead',NULL,NULL,'active','2026-07-19 21:04:17'),(7,'Raka Mahendra','19891117033','raka.mahendra@adhi.co.id','+6281911177788','2026-07-20 13:04:18','$2y$12$241UUUBdDrm9TqggISZKxOlSzsF0HbJu0.aYeKCFgU26K6EOXweMG',NULL,'2026-07-20 13:04:18','2026-07-20 13:04:18','Dept. Infrastruktur I','Team Lead',NULL,NULL,'active','2026-07-20 07:04:18'),(8,'Fajar Nugraha','19850228041','fajar.nugraha@adhi.co.id','+6281433344455','2026-07-20 13:04:18','$2y$12$Tapqzobgczkjhf9.5g5.IurCoDV5qln0sSMCWdYKdmr1aHbpZfqWK',NULL,'2026-07-20 13:04:18','2026-07-20 13:04:18','Satuan Pengawas Internal','Team Lead',NULL,NULL,'active','2026-07-18 21:04:18'),(9,'Nina Amelia','19920504052','nina.amelia@adhi.co.id','+6281544455566','2026-07-20 13:04:18','$2y$12$LlHPIkafnVdew0VOYPNbdurQXyK8VEKqVDIAA/qno0OlDTQEzNuxi',NULL,'2026-07-20 13:04:18','2026-07-20 13:04:18','Adhi Learning Center','Knowledge Administrator',NULL,NULL,'active','2026-07-19 07:04:18'),(10,'Aditya Dwi Nugraha','10027761','aditya.nugraha@adhi.co.id','+6281611144455','2026-07-20 13:04:18','$2y$12$zx5ri8FsREoJbtSlKlcOGuNMh.tnDmujYlQPEGlrN/jB85HDrAm52',NULL,'2026-07-20 13:04:18','2026-07-20 13:04:18','Dept. Strava','IT Support Staff',NULL,NULL,'active','2026-07-19 02:04:18'),(11,'Siti Nurhaliza','19930712063','siti.nurhaliza@adhi.co.id','+6281755566677','2026-07-20 13:04:18','$2y$12$/NWZBiygF399RHAFIgqKCOnzNfjNQIdITA/bTJNgmHwNhm0UtqZhe',NULL,'2026-07-20 13:04:18','2026-07-20 13:04:18','Dept. SDM','HR Staff',NULL,NULL,'inactive','2026-07-18 21:04:18'),(12,'Budi Santoso','19940815074','budi.santoso@adhi.co.id','+6281866677788','2026-07-20 13:04:19','$2y$12$ksH/kDYJ4m1EjKnERZSCC.w7VPIBfB/wqUAf8UjOG8oFK/gPjF3Lu',NULL,'2026-07-20 13:04:19','2026-07-20 13:04:19','Dept. Proyek Balikpapan','Site Engineer','PRJ-BPP-01','Pembangunan Jalan Tol Balikpapan','active','2026-07-19 10:04:19'),(13,'Maria Christin','19910925085','maria.christin@adhi.co.id','+6281977788899','2026-07-20 13:04:19','$2y$12$AVEv8MLbt.2NlNphCcT9UeiUsu77vozdyF9UMu1m9d7OJE.gpAPyq',NULL,'2026-07-20 13:04:19','2026-07-20 13:04:19','Dept. Keuangan','Finance Staff',NULL,NULL,'inactive','2026-07-19 08:04:19'),(14,'Denny Firmansyah','19960130096','denny.firmansyah@adhi.co.id','+6282188899900','2026-07-20 13:04:19','$2y$12$sponse8ybPYMS0kum/SgJ.ivU7ug3609V514bit3IuCrtnOlp/Dq.',NULL,'2026-07-20 13:04:19','2026-07-20 13:04:19','Dept. Supply Chain Management','Procurement Staff',NULL,NULL,'active','2026-07-18 20:04:19'),(15,'Andi Pratama','1844875876968','andi.pratama@adhi.co.id',NULL,NULL,'$2y$12$mcVUDyGr6i9DJxgJSAVCJuPL9JtIzj19/Z2zI7QI/ly/BuRNxzbRy',NULL,'2026-07-20 13:25:30','2026-07-21 12:10:19','Aselole','Dept.Strava',NULL,NULL,'active',NULL),(17,'Arief Kurniawan',NULL,'arief.kurniawan@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$WqZs/DV1S1L4WOTivqUKG.QwP3LnbpPCdATT5UbCjcNT8zuSBDukK',NULL,'2026-07-23 07:06:49','2026-07-23 07:06:49','IT & Operations Bureau','IT Support Staff',NULL,NULL,'active','2026-07-23 04:06:49'),(18,'Febria Sahrina',NULL,'febria.sahrina@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$RcWz7YbpEw2UcLp0bRvLY.n99V..xAVNYFjLE22MdfvhYo5DkSL2C',NULL,'2026-07-23 07:06:50','2026-07-23 07:06:50','IT & Operations Bureau','IT Support Staff',NULL,NULL,'active','2026-07-21 19:06:50'),(19,'Agung Wijayanto',NULL,'agung.wijayanto@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$PZwogStrdok5Ch8j7m8K3e7ztovG.hOSUxH8WlW9KgYHwe2Jvtima',NULL,'2026-07-23 07:06:50','2026-07-23 07:06:50','IT & Operations Bureau','IT Support Staff',NULL,NULL,'active','2026-07-22 07:06:50'),(20,'Naufal Akbar',NULL,'naufal.akbar@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$8A.0GzJl2gAW2OI8e3/ieuz8e2uHkEyncNrk0sv.4xFnPC715gG52',NULL,'2026-07-23 07:06:50','2026-07-23 07:06:50','IT & Operations Bureau','IT Support Staff',NULL,NULL,'active','2026-07-22 02:06:50'),(21,'Sarah',NULL,'sarah@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$/9RedYIguo7q9R1Q4/u7vOI4mP8JB6JorRYSPBU7aeERNEybcIBIe',NULL,'2026-07-23 07:06:50','2026-07-23 07:06:50','IT & Operations Bureau','IT Support Staff',NULL,NULL,'active','2026-07-22 06:06:50'),(22,'Kevin',NULL,'kevin@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$unp.KXmpkBgDhSf849btc.pe6SEJsZZxrwqftViZBfiIvgQQdvrGu',NULL,'2026-07-23 07:06:51','2026-07-23 07:06:51','IT & Operations Bureau','IT Support Staff',NULL,NULL,'active','2026-07-23 01:06:51'),(23,'Rian',NULL,'rian@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$YrKcxR8E97B3GO40aVL6sOlh9sh6pXzYjEhNSL7fE8hUu2xqPN4XK',NULL,'2026-07-23 07:06:51','2026-07-23 07:06:51','IT & Operations Bureau','IT Support Staff',NULL,NULL,'active','2026-07-23 04:06:51'),(24,'Genta Pratama',NULL,'genta.pratama@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$QS3nbka71aaSQPo8xla5eOZ9gN8.AkUm91h4Qp2fe2JpZ9rGk6n5K',NULL,'2026-07-23 07:06:51','2026-07-23 07:06:51','IT & Operations Bureau','BPO Support Staff',NULL,NULL,'active','2026-07-23 05:06:51'),(25,'Rio Saputra',NULL,'rio.saputra@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$aI6HCh4.zMWwdtNbV1Pmh.ZO3tunVUiNy/SzJMKtasD1oBCyvA4ay',NULL,'2026-07-23 07:06:51','2026-07-23 07:06:51','IT & Operations Bureau','BPO Support Staff',NULL,NULL,'active','2026-07-22 21:06:51'),(26,'Lutfi Ramadhan',NULL,'lutfi.ramadhan@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$XQY5K4zuhrtr5G15lliyy.cIJo/FLg5y1ouGdmImC0dx4v.biAnZa',NULL,'2026-07-23 07:06:52','2026-07-23 07:06:52','IT & Operations Bureau','BPO Support Staff',NULL,NULL,'active','2026-07-21 16:06:52'),(27,'Maya Prameswari',NULL,'maya.prameswari@adhikarya-helpdesk.test',NULL,NULL,'$2y$12$plmykMbPpoJazlnd/Y4a1.qp4njWzKQ91DxLYwaWAdVJPGk0S9c/S',NULL,'2026-07-23 07:06:52','2026-07-23 07:06:52','IT & Operations Bureau','BPO Support Staff',NULL,NULL,'active','2026-07-22 19:06:52');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-27  9:44:46

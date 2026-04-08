-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: erp_ezy_chat
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.24.04.1

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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('superadmin','admin','users') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'users',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Brandon Superadmin','brandon@kkbuddy.com','$2y$10$938LjSDFeLPS4NkS3hyb/eOnL3rNm9TWMwQO/x7JH90UnOPu1dsja','superadmin','2026-04-07 02:07:39','2026-04-07 02:07:39'),(2,'Test User 1775529460','test_1775529460@example.com','$2y$10$uEZe.3y08KROHluhR7ARZObBTuktpB6oQI5tHogXCi46Fkgu2BSiC','users','2026-04-07 02:37:40','2026-04-07 02:37:40'),(3,'Test User','test_1775529492@example.com','$2y$10$PcVROasSeO8A.nyHH/iAU.b9aq9ppYuJv9t4cEcL0SY/uylAkFpzi','users','2026-04-07 02:38:12','2026-04-07 02:38:12'),(4,'API Test User','api_test_1775529745@example.com','$2y$10$/UP2Bm7d0RSoxdbItaZnYu6G0IhQOpgvrSvVcoVY.udh7AxUOEllC','users','2026-04-07 02:42:26','2026-04-07 02:42:26'),(5,'Route Test','route_test_1775529773@example.com','$2y$10$RjywHLDbxIOsBkyNVZyk4O84H/qACDwwXXSG4sOytMt.qODFdY/Wu','users','2026-04-07 02:42:53','2026-04-07 02:42:53'),(6,'WebbyLink','brandon@webbypage.com','$2y$10$Oacbj8vmPOlYdhshfvIcrObJrBCfukIS/wlZlqYyZ/DbYBnyFLmeq','users','2026-04-07 05:40:01','2026-04-07 05:40:01');
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

-- Dump completed on 2026-04-08 20:40:50

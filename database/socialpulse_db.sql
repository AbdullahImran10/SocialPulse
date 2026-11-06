-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: socialpulse_db
-- ------------------------------------------------------
-- Server version	8.4.11-0ubuntu0.26.04.1

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
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,3,'Login','User logged in successfully.','2026-11-05 15:55:37'),(2,2,'Login Failed','Invalid credentials or inactive account.','2026-11-05 15:55:52'),(3,2,'Login','User logged in successfully.','2026-11-05 15:55:57'),(4,1,'Login','User logged in successfully.','2026-11-05 15:56:04'),(5,2,'Login Failed','Invalid credentials or inactive account.','2026-11-05 16:01:35'),(6,2,'Login','User logged in successfully.','2026-11-05 16:01:47'),(7,2,'Logout','User logged out successfully.','2026-11-05 16:06:46'),(8,1,'Login','User logged in successfully.','2026-11-05 16:06:50'),(9,1,'Logout','User logged out successfully.','2026-11-05 16:15:01'),(10,1,'Login','User logged in successfully.','2026-11-05 16:26:36'),(11,1,'Login','User logged in successfully.','2026-11-05 17:37:21'),(12,1,'Logout','User logged out successfully.','2026-11-05 17:49:08'),(13,3,'Login','User logged in successfully.','2026-11-05 17:49:12'),(14,3,'Logout','User logged out successfully.','2026-11-05 17:49:30'),(15,2,'Login','User logged in successfully.','2026-11-05 17:49:33'),(16,2,'Logout','User logged out successfully.','2026-11-05 17:55:27'),(17,1,'Login','User logged in successfully.','2026-11-05 17:55:32'),(18,1,'Logout','User logged out successfully.','2026-11-05 17:55:39'),(19,2,'Login','User logged in successfully.','2026-11-05 17:55:42'),(20,2,'Logout','User logged out successfully.','2026-11-05 17:55:47'),(21,3,'Login','User logged in successfully.','2026-11-05 17:58:28'),(22,3,'Logout','User logged out successfully.','2026-11-05 18:02:14'),(23,3,'Login','User logged in successfully.','2026-11-05 18:02:18'),(24,3,'Logout','User logged out successfully.','2026-11-05 18:02:57'),(25,2,'Login','User logged in successfully.','2026-11-05 18:08:53'),(26,2,'Logout','User logged out successfully.','2026-11-05 18:09:15'),(27,1,'Login','User logged in successfully.','2026-11-05 18:09:18');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `brand_members`
--

DROP TABLE IF EXISTS `brand_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `brand_members` (
  `brand_id` int NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`brand_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `brand_members_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`brand_id`) ON DELETE CASCADE,
  CONSTRAINT `brand_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brand_members`
--

LOCK TABLES `brand_members` WRITE;
/*!40000 ALTER TABLE `brand_members` DISABLE KEYS */;
INSERT INTO `brand_members` VALUES (1,2),(2,2),(1,3),(2,3);
/*!40000 ALTER TABLE `brand_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `brands` (
  `brand_id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `brand_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`brand_id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `brands_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` VALUES (1,1,'Nike','2026-10-30 12:15:08'),(2,1,'Instagram','2026-10-30 12:15:20');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `client_id` int NOT NULL AUTO_INCREMENT,
  `client_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'Ali','2026-10-30 11:58:29');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_metrics`
--

DROP TABLE IF EXISTS `post_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_metrics` (
  `metric_id` int NOT NULL AUTO_INCREMENT,
  `post_id` int NOT NULL,
  `recorded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `views` bigint unsigned DEFAULT '0',
  `likes` bigint unsigned DEFAULT '0',
  `comments` bigint unsigned DEFAULT '0',
  `shares` bigint unsigned DEFAULT '0',
  `reach` bigint unsigned DEFAULT '0',
  PRIMARY KEY (`metric_id`),
  KEY `post_id` (`post_id`),
  CONSTRAINT `post_metrics_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_metrics`
--

LOCK TABLES `post_metrics` WRITE;
/*!40000 ALTER TABLE `post_metrics` DISABLE KEYS */;
INSERT INTO `post_metrics` VALUES (2,7,'2026-11-01 15:18:10',1000,500,300,700,0),(3,8,'2026-11-01 15:28:00',5002,450,35,25,4200),(4,9,'2026-11-01 15:28:34',8000,650,50,40,7000),(5,10,'2026-11-01 15:28:00',3001,200,15,10,2500),(6,11,'2026-11-05 20:48:00',4000,1000,299,400,800);
/*!40000 ALTER TABLE `post_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `posts` (
  `post_id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `caption` text,
  `post_type` varchar(50) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `post_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`post_id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `social_accounts` (`account_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `posts`
--

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
INSERT INTO `posts` VALUES (1,1,'New Nike Air Max Collection','Image','2026-10-20 10:00:00','https://example.com/nike-post-1'),(2,1,'Just Do It - Never Give Up','Video','2026-10-22 12:00:00','https://example.com/nike-post-2'),(3,2,'Nike Running Campaign','Reel','2026-10-24 15:00:00','https://example.com/nike-post-3'),(4,3,'Adidas New Sports Collection','Image','2026-10-21 11:00:00','https://example.com/adidas-post-1'),(5,3,'Impossible Is Nothing','Video','2026-10-25 14:00:00','https://example.com/adidas-post-2'),(7,1,'Nothing is Impossible','Image','2025-11-06 00:00:00','https://www.instagram.com/nike/'),(8,3,'Product Launch','Image','2026-10-20 00:00:00','https://instagram.com/p/123'),(9,3,'New Collection','Reel','2026-10-21 00:00:00','https://instagram.com/p/456'),(10,3,'Behind the Scenes','Video','2026-10-22 00:00:00','https://instagram.com/p/789'),(11,4,'Yes!!!','Image','2026-11-05 20:47:00','https://www.instagram.com/nike/');
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `brand_id` int NOT NULL,
  `created_by` int NOT NULL,
  `report_name` varchar(150) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `platform` enum('Instagram','Facebook','All') DEFAULT 'All',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  KEY `brand_id` (`brand_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`brand_id`),
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
INSERT INTO `reports` VALUES (2,1,2,'Client1','2024-10-10','2026-12-12','Instagram','2026-11-04 21:12:12');
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin'),(3,'Client'),(2,'Social Media Manager');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_accounts`
--

DROP TABLE IF EXISTS `social_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_accounts` (
  `account_id` int NOT NULL AUTO_INCREMENT,
  `brand_id` int NOT NULL,
  `platform` enum('Instagram','Facebook') NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `platform_account_id` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`account_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `social_accounts_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`brand_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_accounts`
--

LOCK TABLES `social_accounts` WRITE;
/*!40000 ALTER TABLE `social_accounts` DISABLE KEYS */;
INSERT INTO `social_accounts` VALUES (1,1,'Instagram','@nike','nike_instagram_001'),(2,1,'Facebook','Nike Official','nike_facebook_001'),(3,2,'Instagram','@adidas','adidas_instagram_001'),(4,2,'Facebook','Adidas Official','adidas_facebook_001');
/*!40000 ALTER TABLE `social_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,1),(2,2),(3,3);
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `client_id` int DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,'SocialPulse Admin','admin@socialpulse.local','$2y$12$zPfQxYFI2f2Qm8kYAxEhMuY9szlJIwGTjYCD9C9iDErf7LJERV2I6','Active','2026-10-17 09:57:39'),(2,NULL,'manager1','manager@test.com','$2y$12$polDVNHK3rx5OnEyvxLJjuZ1cYOAZtt65alRIcHtyVzPjIWFikRji','Active','2026-10-30 11:43:18'),(3,1,'client1','client@test.com','$2y$12$F41LBzFOIQCYbtNzbP0gOO/3i57XXFSI0QizgnM/EFwbDDS5xVU.2','Active','2026-10-30 11:50:59');
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

-- Dump completed on 2026-11-05 23:14:39

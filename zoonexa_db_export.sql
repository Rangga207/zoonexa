-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: zoonexa_db
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

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
-- Table structure for table `bonus_missions`
--

DROP TABLE IF EXISTS `bonus_missions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bonus_missions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `mission_type` enum('jogging','strava') NOT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `points_awarded` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bonus_missions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bonus_missions`
--

LOCK TABLES `bonus_missions` WRITE;
/*!40000 ALTER TABLE `bonus_missions` DISABLE KEYS */;
/*!40000 ALTER TABLE `bonus_missions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_logs`
--

DROP TABLE IF EXISTS `health_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `health_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `steps` int(11) DEFAULT 0,
  `sleep_hours` float DEFAULT 0,
  `weight_kg` float DEFAULT 0,
  `bmi` float DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_log_per_day` (`user_id`,`log_date`),
  CONSTRAINT `health_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_logs`
--

LOCK TABLES `health_logs` WRITE;
/*!40000 ALTER TABLE `health_logs` DISABLE KEYS */;
INSERT INTO `health_logs` VALUES (1,1,'2026-03-01',1000,8,80,29.38,'2026-03-01 07:19:50'),(2,1,'2026-05-08',5000,7,80,29.38,'2026-05-08 15:26:40'),(3,6,'2026-05-09',400,7,80,29.38,'2026-05-09 06:22:25'),(4,1,'2026-05-10',10000,12,78,28.31,'2026-05-10 05:25:31');
/*!40000 ALTER TABLE `health_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merchandise_orders`
--

DROP TABLE IF EXISTS `merchandise_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merchandise_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_id` varchar(50) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `points_used` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `status` enum('pending','processed','shipped') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `merchandise_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchandise_orders`
--

LOCK TABLES `merchandise_orders` WRITE;
/*!40000 ALTER TABLE `merchandise_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `merchandise_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `milestones`
--

DROP TABLE IF EXISTS `milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `icon` varchar(10) NOT NULL,
  `reward_points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `milestones`
--

LOCK TABLES `milestones` WRITE;
/*!40000 ALTER TABLE `milestones` DISABLE KEYS */;
INSERT INTO `milestones` VALUES (1,'first_log','First Step','Log your health data for the first time.','🌱',50,'2026-02-03 12:40:42'),(2,'seven_day_streak','7-Day Streak','Log your health data for 7 consecutive days.','🔥',200,'2026-02-03 12:40:42'),(3,'thirty_day_streak','30-Day Streak','Log your health data for 30 consecutive days.','💎',500,'2026-02-03 12:40:42'),(4,'steps_10k','Steps Master','Record 10,000 or more steps in a single day.','🏃',100,'2026-02-03 12:40:42'),(5,'steps_15k','Speed Demon','Record 15,000 or more steps in a single day.','⚡',200,'2026-02-03 12:40:42'),(6,'sleep_champion','Sleep Champion','Record 8 or more hours of sleep in a single night.','😴',100,'2026-02-03 12:40:42'),(7,'sleep_perfect_week','Sleep Perfectionist','Record 8+ hours of sleep for 7 consecutive days.','🌙',300,'2026-02-03 12:40:42'),(8,'total_logs_10','Getting Started','Complete a total of 10 health logs.','📝',100,'2026-02-03 12:40:42'),(9,'total_logs_30','Consistent Tracker','Complete a total of 30 health logs.','📊',250,'2026-02-03 12:40:42'),(10,'total_logs_100','Health Guru','Complete a total of 100 health logs.','🏆',500,'2026-02-03 12:40:42'),(11,'bmi_normal','Healthy BMI','Record a BMI in the normal range (18.5 - 24.9).','💪',150,'2026-02-03 12:40:42'),(12,'first_subscribe','Supporter','Subscribe to unlock all premium features.','⭐',100,'2026-02-03 12:40:42'),(13,'points_1000','Point Collector','Accumulate 1,000 health points.','💰',200,'2026-02-03 12:40:42'),(14,'points_5000','Point Legend','Accumulate 5,000 health points.','👑',500,'2026-02-03 12:40:42');
/*!40000 ALTER TABLE `milestones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `midtrans_order_id` varchar(100) NOT NULL,
  `midtrans_transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','active','expired','cancelled','failed') DEFAULT 'pending',
  `amount_paid` int(11) DEFAULT 10000,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `midtrans_order_id` (`midtrans_order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
INSERT INTO `subscriptions` VALUES (1,1,'test-order-12345','test-trans-12345','active',10000,'2026-03-01','2026-03-31','manual_test','2026-03-01 07:23:45','2026-03-01 07:23:45'),(2,4,'zoonexa-4-1778245287','manual-admin-1778245312','active',10000,'2026-05-08','2026-06-07','qris_manual','2026-05-08 13:01:27','2026-05-08 13:01:52'),(3,6,'zoonexa-6-1778307800','manual-admin-1778307833','active',10000,'2026-05-09','2026-06-08','qris_manual','2026-05-09 06:23:20','2026-05-09 06:23:53');
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_milestones`
--

DROP TABLE IF EXISTS `user_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `milestone_id` int(11) NOT NULL,
  `achieved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_milestone_per_user` (`user_id`,`milestone_id`),
  KEY `milestone_id` (`milestone_id`),
  CONSTRAINT `user_milestones_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_milestones_ibfk_2` FOREIGN KEY (`milestone_id`) REFERENCES `milestones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_milestones`
--

LOCK TABLES `user_milestones` WRITE;
/*!40000 ALTER TABLE `user_milestones` DISABLE KEYS */;
INSERT INTO `user_milestones` VALUES (1,1,1,'2026-03-01 07:19:50'),(2,1,6,'2026-03-01 07:19:50'),(3,1,12,'2026-03-01 07:24:02'),(4,4,12,'2026-05-08 13:01:52'),(5,6,1,'2026-05-09 06:22:25'),(6,6,12,'2026-05-09 06:23:53'),(7,1,4,'2026-05-10 05:25:31');
/*!40000 ALTER TABLE `user_milestones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `password_hash` varchar(255) NOT NULL,
  `health_mode` enum('maintain','bulking','cutting') DEFAULT 'maintain',
  `points` int(11) DEFAULT 0,
  `subscription_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Rangga','admin','$2y$12$11IMZ0XD1RKgFGqRW8ndF.zs6V0hDxvjDz33n2gghstxc1IZJyCo.','maintain',80,1,'2026-02-04 06:14:32','2026-05-10 05:25:38'),(2,'Gharul','user','$2y$10$CHszQTvvKtwYI5Sxf9zx..OH92DMzzVloxptwL8vQwIxfEFP9U4Ry','maintain',0,0,'2026-03-02 15:13:20','2026-03-02 15:13:20'),(4,'teguh','user','$2y$10$.mf.FkE3TAIl97i6hcBWcOOXxB45qJx7SRq3hz4HJPycOW8e/AfVe','maintain',100,1,'2026-05-08 13:00:22','2026-05-08 13:01:52'),(5,'okee','user','$2y$10$9sB7SdccnYHSLzXqpkhOXOvingXZHEk1bw5xcUgt3qqSyN5SBfJYa','maintain',0,0,'2026-05-08 13:11:05','2026-05-08 13:11:05'),(6,'supra','user','$2y$10$oxZFipXPWhhfdjcOM6lo0Op9RPtEFZn2w128hATCv/171SLdMPyiK','maintain',160,1,'2026-05-09 06:20:52','2026-05-09 06:23:53');
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

-- Dump completed on 2026-05-10 12:48:57

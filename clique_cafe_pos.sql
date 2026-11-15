-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: restaurant_pos
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
-- Table structure for table `audit_events`
--

DROP TABLE IF EXISTS `audit_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `before` json DEFAULT NULL,
  `after` json DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_events_restaurant_id_foreign` (`restaurant_id`),
  KEY `audit_events_auditable_type_auditable_id_created_at_index` (`auditable_type`,`auditable_id`,`created_at`),
  KEY `audit_events_actor_id_created_at_index` (`actor_id`,`created_at`),
  CONSTRAINT `audit_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_events_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_events`
--

LOCK TABLES `audit_events` WRITE;
/*!40000 ALTER TABLE `audit_events` DISABLE KEYS */;
INSERT INTO `audit_events` VALUES (1,1,NULL,'table.created','App\\Models\\DiningTable',16,NULL,'{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"updated_at\": \"2026-11-11T11:34:57.000000Z\", \"display_name\": \"QA 1794396893261\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:34:57'),(2,1,NULL,'table.updated','App\\Models\\DiningTable',16,'{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:34:57.000000Z\", \"display_name\": \"QA 1794396893261\", \"restaurant_id\": 1}','{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:01.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:35:02'),(3,1,NULL,'table.deactivated','App\\Models\\DiningTable',16,'{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:01.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}','{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:02.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:35:02'),(4,1,NULL,'table.activated','App\\Models\\DiningTable',16,'{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:02.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}','{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:03.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:35:03'),(5,1,NULL,'table.deactivated','App\\Models\\DiningTable',16,'{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:03.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}','{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:04.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:35:04'),(6,1,NULL,'table.created','App\\Models\\DiningTable',17,NULL,'{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"updated_at\": \"2026-11-11T11:35:56.000000Z\", \"display_name\": \"QA 1794396951764\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:35:56'),(7,1,NULL,'table.updated','App\\Models\\DiningTable',17,'{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:56.000000Z\", \"display_name\": \"QA 1794396951764\", \"restaurant_id\": 1}','{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:36:00.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:36:00'),(8,1,NULL,'table.deactivated','App\\Models\\DiningTable',17,'{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:36:00.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}','{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:36:01.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:36:01'),(9,1,NULL,'table.activated','App\\Models\\DiningTable',17,'{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:36:01.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}','{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:36:02.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:36:02'),(10,1,NULL,'table.deactivated','App\\Models\\DiningTable',17,'{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:36:02.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}','{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:36:03.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:36:03'),(11,1,NULL,'table.created','App\\Models\\DiningTable',18,NULL,'{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"updated_at\": \"2026-11-11T11:38:47.000000Z\", \"display_name\": \"QA 1794397120421\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:38:47'),(12,1,NULL,'table.updated','App\\Models\\DiningTable',18,'{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:47.000000Z\", \"display_name\": \"QA 1794397120421\", \"restaurant_id\": 1}','{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:53.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:38:53'),(13,1,NULL,'table.deactivated','App\\Models\\DiningTable',18,'{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:53.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}','{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:54.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:38:54'),(14,1,NULL,'table.activated','App\\Models\\DiningTable',18,'{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:54.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}','{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:55.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:38:55'),(15,1,NULL,'table.deactivated','App\\Models\\DiningTable',18,'{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:55.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}','{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:56.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 05:38:56'),(16,1,1,'order.opened','App\\Models\\Order',1,NULL,'{\"id\": 1, \"note\": null, \"total\": \"0.00\", \"status\": \"OPEN\", \"version\": 1, \"subtotal\": \"0.00\", \"opened_at\": \"2026-11-11T12:28:30.000000Z\", \"waiter_id\": 1, \"created_at\": \"2026-11-11T12:28:30.000000Z\", \"tax_amount\": \"0.00\", \"updated_at\": \"2026-11-11T12:28:30.000000Z\", \"balance_due\": \"0.00\", \"guest_count\": 3, \"paid_amount\": \"0.00\", \"order_number\": \"ORD-0001\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 4, \"discount_amount\": \"0.00\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 06:28:31'),(17,1,1,'order_item.added','App\\Models\\OrderItem',1,NULL,'{\"id\": 1, \"sku\": \"BEV-03\", \"note\": null, \"status\": \"DRAFT\", \"station\": \"BAR\", \"version\": 1, \"line_tax\": \"6.00\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Artisanal Cola\", \"modifiers\": [], \"created_at\": \"2026-11-11T12:28:42.000000Z\", \"line_total\": \"126.00\", \"unit_price\": \"120.00\", \"updated_at\": \"2026-11-11T12:28:42.000000Z\", \"menu_item_id\": 8, \"variant_name\": null, \"line_subtotal\": \"120.00\", \"restaurant_id\": 1, \"sent_quantity\": \"0.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 06:28:42'),(18,1,1,'order_item.added','App\\Models\\OrderItem',2,NULL,'{\"id\": 2, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"DRAFT\", \"station\": \"KITCHEN\", \"version\": 1, \"line_tax\": \"19.50\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"created_at\": \"2026-11-11T12:28:55.000000Z\", \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-11T12:28:55.000000Z\", \"menu_item_id\": 2, \"variant_name\": null, \"line_subtotal\": \"390.00\", \"restaurant_id\": 1, \"sent_quantity\": \"0.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 06:28:55'),(19,1,1,'kot.created','App\\Models\\Kot',1,NULL,'{\"station\": \"KITCHEN\", \"kot_number\": \"KOT-0001\", \"round_number\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 06:28:59'),(20,1,1,'kot.created','App\\Models\\Kot',2,NULL,'{\"station\": \"BAR\", \"kot_number\": \"KOT-0002\", \"round_number\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 06:28:59'),(21,1,1,'order.round_sent','App\\Models\\Order',1,NULL,'{\"kots\": [\"KOT-0001\", \"KOT-0002\"], \"round_number\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 06:28:59'),(22,1,NULL,'table.created','App\\Models\\DiningTable',19,NULL,'{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"updated_at\": \"2026-11-12T05:16:51.000000Z\", \"display_name\": \"QA 1794460603426\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:16:51'),(23,1,NULL,'table.updated','App\\Models\\DiningTable',19,'{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:51.000000Z\", \"display_name\": \"QA 1794460603426\", \"restaurant_id\": 1}','{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:55.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:16:55'),(24,1,NULL,'table.deactivated','App\\Models\\DiningTable',19,'{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:55.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}','{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:56.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:16:56'),(25,1,NULL,'table.activated','App\\Models\\DiningTable',19,'{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:56.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}','{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:56.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:16:56'),(26,1,NULL,'table.deactivated','App\\Models\\DiningTable',19,'{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:56.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}','{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:58.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:16:58'),(27,1,NULL,'table.created','App\\Models\\DiningTable',20,NULL,'{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"updated_at\": \"2026-11-12T05:22:03.000000Z\", \"display_name\": \"QA 1794460918343\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:22:03'),(28,1,NULL,'table.updated','App\\Models\\DiningTable',20,'{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:03.000000Z\", \"display_name\": \"QA 1794460918343\", \"restaurant_id\": 1}','{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:07.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:22:07'),(29,1,NULL,'table.deactivated','App\\Models\\DiningTable',20,'{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:07.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}','{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:08.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:22:08'),(30,1,NULL,'table.activated','App\\Models\\DiningTable',20,'{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:08.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}','{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:09.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:22:09'),(31,1,NULL,'table.deactivated','App\\Models\\DiningTable',20,'{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:09.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}','{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:10.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:22:10'),(32,1,NULL,'table.created','App\\Models\\DiningTable',21,NULL,'{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"updated_at\": \"2026-11-12T05:23:49.000000Z\", \"display_name\": \"QA 1794461024913\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:23:49'),(33,1,NULL,'table.updated','App\\Models\\DiningTable',21,'{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:49.000000Z\", \"display_name\": \"QA 1794461024913\", \"restaurant_id\": 1}','{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:53.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:23:53'),(34,1,NULL,'table.deactivated','App\\Models\\DiningTable',21,'{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:53.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}','{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:54.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:23:54'),(35,1,NULL,'table.activated','App\\Models\\DiningTable',21,'{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:54.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}','{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:55.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:23:56'),(36,1,NULL,'table.deactivated','App\\Models\\DiningTable',21,'{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:55.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}','{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:57.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/157.0.0.0 Safari/537.36','2026-11-11 23:23:57'),(37,1,1,'kot.started','App\\Models\\Kot',1,'{\"status\": \"PENDING\"}','{\"status\": \"PREPARING\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 04:12:51'),(38,1,1,'kot.ready','App\\Models\\Kot',1,'{\"status\": \"PREPARING\"}','{\"status\": \"READY\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 04:12:59'),(39,1,1,'table.activated','App\\Models\\DiningTable',16,'{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:35:04.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}','{\"id\": 16, \"code\": \"QA1794396893261\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:34:57.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T10:22:15.000000Z\", \"display_name\": \"QA verified 1794396893261\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 04:22:15'),(40,1,1,'table.activated','App\\Models\\DiningTable',17,'{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:36:03.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}','{\"id\": 17, \"code\": \"QA1794396951764\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:35:56.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T10:22:17.000000Z\", \"display_name\": \"QA verified 1794396951764\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 04:22:18'),(41,1,1,'table.activated','App\\Models\\DiningTable',18,'{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-11T11:38:56.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}','{\"id\": 18, \"code\": \"QA1794397120421\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-11T11:38:47.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T10:22:19.000000Z\", \"display_name\": \"QA verified 1794397120421\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 04:22:19'),(42,1,1,'table.activated','App\\Models\\DiningTable',19,'{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:16:58.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}','{\"id\": 19, \"code\": \"QA1794460603426\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:16:51.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T10:22:21.000000Z\", \"display_name\": \"QA verified 1794460603426\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 04:22:21'),(43,1,1,'table.activated','App\\Models\\DiningTable',20,'{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:22:10.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}','{\"id\": 20, \"code\": \"QA1794460918343\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:22:03.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T10:22:24.000000Z\", \"display_name\": \"QA verified 1794460918343\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 04:22:24'),(44,1,1,'table.activated','App\\Models\\DiningTable',21,'{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": false, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T05:23:57.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}','{\"id\": 21, \"code\": \"QA1794461024913\", \"active\": true, \"zone_id\": 1, \"capacity\": 6, \"created_at\": \"2026-11-12T05:23:49.000000Z\", \"sort_order\": 0, \"updated_at\": \"2026-11-12T10:22:26.000000Z\", \"display_name\": \"QA verified 1794461024913\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 04:22:26'),(45,1,1,'order_item.added','App\\Models\\OrderItem',3,NULL,'{\"id\": 3, \"sku\": \"BRG-01\", \"note\": null, \"status\": \"DRAFT\", \"station\": \"KITCHEN\", \"version\": 1, \"line_tax\": \"22.50\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Smokehouse Beef Burger\", \"modifiers\": [], \"created_at\": \"2026-11-12T11:10:44.000000Z\", \"line_total\": \"472.50\", \"unit_price\": \"450.00\", \"updated_at\": \"2026-11-12T11:10:44.000000Z\", \"menu_item_id\": 1, \"variant_name\": null, \"line_subtotal\": \"450.00\", \"restaurant_id\": 1, \"sent_quantity\": \"0.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:10:44'),(46,1,1,'order.opened','App\\Models\\Order',2,NULL,'{\"id\": 2, \"note\": null, \"total\": \"0.00\", \"status\": \"OPEN\", \"version\": 1, \"subtotal\": \"0.00\", \"opened_at\": \"2026-11-12T11:11:04.000000Z\", \"waiter_id\": 1, \"created_at\": \"2026-11-12T11:11:04.000000Z\", \"tax_amount\": \"0.00\", \"updated_at\": \"2026-11-12T11:11:04.000000Z\", \"balance_due\": \"0.00\", \"guest_count\": 4, \"paid_amount\": \"0.00\", \"order_number\": \"ORD-0002\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 2, \"discount_amount\": \"0.00\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:11:04'),(47,1,1,'order_item.added','App\\Models\\OrderItem',4,NULL,'{\"id\": 4, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"DRAFT\", \"station\": \"KITCHEN\", \"version\": 1, \"line_tax\": \"19.50\", \"order_id\": 2, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"created_at\": \"2026-11-12T11:11:09.000000Z\", \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-12T11:11:09.000000Z\", \"menu_item_id\": 2, \"variant_name\": null, \"line_subtotal\": \"390.00\", \"restaurant_id\": 1, \"sent_quantity\": \"0.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:11:09'),(48,1,1,'kot.created','App\\Models\\Kot',3,NULL,'{\"station\": \"KITCHEN\", \"kot_number\": \"KOT-0003\", \"round_number\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:11:26'),(49,1,1,'order.round_sent','App\\Models\\Order',2,NULL,'{\"kots\": [\"KOT-0003\"], \"round_number\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:11:26'),(50,1,1,'order.bill_requested','App\\Models\\Order',2,'{\"status\": \"OPEN\", \"version\": 3}','{\"status\": \"BILL_REQUESTED\", \"version\": 4}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:11:34'),(51,1,1,'kot.started','App\\Models\\Kot',2,'{\"status\": \"PENDING\"}','{\"status\": \"PREPARING\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:11:41'),(52,1,1,'kot.ready','App\\Models\\Kot',3,'{\"status\": \"PENDING\"}','{\"status\": \"READY\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:11:44'),(53,1,1,'shift.opened','App\\Models\\CashierShift',1,NULL,'{\"id\": 1, \"notes\": null, \"status\": \"OPEN\", \"opened_at\": \"2026-11-12T11:12:00.000000Z\", \"cashier_id\": 1, \"created_at\": \"2026-11-12T11:12:00.000000Z\", \"updated_at\": \"2026-11-12T11:12:00.000000Z\", \"expected_cash\": \"500.00\", \"opening_float\": \"500.00\", \"restaurant_id\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:12:00'),(54,1,1,'payment.captured','App\\Models\\Payment',1,NULL,'{\"id\": 1, \"amount\": \"409.50\", \"method\": \"CASH\", \"status\": \"CAPTURED\", \"order_id\": 2, \"created_at\": \"2026-11-12T11:12:25.000000Z\", \"updated_at\": \"2026-11-12T11:12:25.000000Z\", \"captured_at\": \"2026-11-12T11:12:25.000000Z\", \"received_by\": 1, \"change_given\": \"0.00\", \"restaurant_id\": 1, \"tendered_amount\": \"409.50\", \"cashier_shift_id\": 1, \"provider_reference\": null}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:12:25'),(55,1,1,'invoice.issued','App\\Models\\Invoice',1,NULL,'{\"total\": \"409.50\", \"order_id\": 2, \"invoice_number\": \"INV-0001\"}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 05:12:25'),(56,1,1,'order.updated','App\\Models\\Order',1,'{\"note\": null, \"version\": 5, \"guest_count\": 3}','{\"note\": null, \"version\": 6, \"guest_count\": 2}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-13 23:14:17'),(57,1,1,'order.updated','App\\Models\\Order',1,'{\"note\": null, \"version\": 6, \"guest_count\": 2}','{\"note\": null, \"version\": 7, \"guest_count\": 1}',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-13 23:14:18'),(58,1,1,'order.cancelled','App\\Models\\Order',1,'{\"status\": \"OPEN\", \"version\": 7}','{\"status\": \"CANCELLED\", \"version\": 8}','time',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-13 23:14:30');
/*!40000 ALTER TABLE `audit_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('clique-cafe-cache-356a192b7913b04c54574d18c28d46e6395428ab','i:3;',1794481400),('clique-cafe-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer','i:1794481400;',1794481400),('clique-cafe-cache-5c785c036466adea360111aa28563bfd556b5fba','i:2;',1794633278),('clique-cafe-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1794633278;',1794633278),('clique-cafe-cache-735a750ddc7dc77a387b33599230be11','i:1;',1794633284),('clique-cafe-cache-735a750ddc7dc77a387b33599230be11:timer','i:1794633284;',1794633284),('restoflow-cache-1b6453892473a467d07372d45eb05abc2031647a','i:2;',1794397017),('restoflow-cache-1b6453892473a467d07372d45eb05abc2031647a:timer','i:1794397017;',1794397017),('restoflow-cache-349c9f967498e773362d23826934a004','i:2;',1794461087),('restoflow-cache-349c9f967498e773362d23826934a004:timer','i:1794461087;',1794461087),('restoflow-cache-356a192b7913b04c54574d18c28d46e6395428ab','i:2;',1794478101),('restoflow-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer','i:1794478101;',1794478101),('restoflow-cache-5c785c036466adea360111aa28563bfd556b5fba','i:2;',1794477608),('restoflow-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1794477608;',1794477608),('restoflow-cache-735a750ddc7dc77a387b33599230be11','i:1;',1794477705),('restoflow-cache-735a750ddc7dc77a387b33599230be11:timer','i:1794477705;',1794477705),('restoflow-cache-77de68daecd823babbb58edb1c8e14d7106e83bb','i:2;',1794396958),('restoflow-cache-77de68daecd823babbb58edb1c8e14d7106e83bb:timer','i:1794396958;',1794396958),('restoflow-cache-7d7f7374abdf45b89936c43efcce7537','i:1;',1794460981),('restoflow-cache-7d7f7374abdf45b89936c43efcce7537:timer','i:1794460981;',1794460981),('restoflow-cache-902ba3cda1883801594b6e1b452790cc53948fda','i:2;',1794460984),('restoflow-cache-902ba3cda1883801594b6e1b452790cc53948fda:timer','i:1794460984;',1794460984),('restoflow-cache-a9468f5f6e7a964756f801172cbac031','i:2;',1794460669),('restoflow-cache-a9468f5f6e7a964756f801172cbac031:timer','i:1794460668;',1794460668),('restoflow-cache-ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4','i:2;',1794397188),('restoflow-cache-ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4:timer','i:1794397188;',1794397188),('restoflow-cache-c1dfd96eea8cc2b62785275bca38ac261256e278','i:2;',1794460671),('restoflow-cache-c1dfd96eea8cc2b62785275bca38ac261256e278:timer','i:1794460671;',1794460671),('restoflow-cache-d45dc2d75bd2f7a39e177e4ed78f073c','i:2;',1794397014),('restoflow-cache-d45dc2d75bd2f7a39e177e4ed78f073c:timer','i:1794397014;',1794397014),('restoflow-cache-d5486178fa8b95218afb37cb35f6fe30','i:1;',1794396935),('restoflow-cache-d5486178fa8b95218afb37cb35f6fe30:timer','i:1794396935;',1794396935),('restoflow-cache-ec2da861bc26f43c65578dc03557d22b','i:2;',1794397184),('restoflow-cache-ec2da861bc26f43c65578dc03557d22b:timer','i:1794397183;',1794397183),('restoflow-cache-ed6bcde97cf526af7707895dcd1e388c','i:1;',1794396956),('restoflow-cache-ed6bcde97cf526af7707895dcd1e388c:timer','i:1794396955;',1794396955),('restoflow-cache-fe5dbbcea5ce7e2988b8c69bcfdfde8904aabc1f','i:2;',1794461090),('restoflow-cache-fe5dbbcea5ce7e2988b8c69bcfdfde8904aabc1f:timer','i:1794461090;',1794461090);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
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
-- Table structure for table `cashier_shifts`
--

DROP TABLE IF EXISTS `cashier_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashier_shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `cashier_id` bigint unsigned NOT NULL,
  `opening_float` decimal(13,2) NOT NULL DEFAULT '0.00',
  `expected_cash` decimal(13,2) NOT NULL DEFAULT '0.00',
  `counted_cash` decimal(13,2) DEFAULT NULL,
  `variance` decimal(13,2) DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opened_at` timestamp NOT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cashier_shifts_cashier_id_foreign` (`cashier_id`),
  KEY `cashier_shifts_approved_by_foreign` (`approved_by`),
  KEY `cashier_shifts_restaurant_id_cashier_id_status_index` (`restaurant_id`,`cashier_id`,`status`),
  CONSTRAINT `cashier_shifts_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cashier_shifts_cashier_id_foreign` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cashier_shifts_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cashier_shifts`
--

LOCK TABLES `cashier_shifts` WRITE;
/*!40000 ALTER TABLE `cashier_shifts` DISABLE KEYS */;
INSERT INTO `cashier_shifts` VALUES (1,1,1,500.00,909.50,NULL,NULL,'OPEN',NULL,'2026-11-12 05:12:00',NULL,NULL,NULL,'2026-11-12 05:12:00','2026-11-12 05:12:25');
/*!40000 ALTER TABLE `cashier_shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dining_tables`
--

DROP TABLE IF EXISTS `dining_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dining_tables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `zone_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` smallint unsigned NOT NULL DEFAULT '4',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dining_tables_restaurant_id_code_unique` (`restaurant_id`,`code`),
  UNIQUE KEY `dining_tables_restaurant_id_display_name_unique` (`restaurant_id`,`display_name`),
  KEY `dining_tables_zone_id_active_sort_order_index` (`zone_id`,`active`,`sort_order`),
  CONSTRAINT `dining_tables_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `dining_tables_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dining_tables`
--

LOCK TABLES `dining_tables` WRITE;
/*!40000 ALTER TABLE `dining_tables` DISABLE KEYS */;
INSERT INTO `dining_tables` VALUES (1,1,1,'T1','Table 1',4,1,1,'2026-11-11 02:05:44','2026-11-11 02:05:44'),(2,1,1,'T2','Table 2',4,2,1,'2026-11-11 02:05:44','2026-11-11 02:05:44'),(3,1,1,'T3','Table 3',4,3,1,'2026-11-11 02:05:44','2026-11-11 02:05:44'),(4,1,1,'T4','Table 4',4,4,1,'2026-11-11 02:05:44','2026-11-11 02:05:44'),(5,1,1,'T5','Table 5',6,5,1,'2026-11-11 02:05:44','2026-11-11 02:05:44'),(6,1,1,'T6','Table 6',4,6,1,'2026-11-11 02:05:44','2026-11-11 02:05:44'),(7,1,1,'T7','Table 7',4,7,1,'2026-11-11 02:05:44','2026-11-11 02:05:44'),(8,1,1,'T8','Table 8',4,8,1,'2026-11-11 02:05:45','2026-11-11 02:05:45'),(9,1,1,'T9','Table 9',4,9,1,'2026-11-11 02:05:45','2026-11-11 02:05:45'),(10,1,1,'T10','Table 10',6,10,1,'2026-11-11 02:05:45','2026-11-11 02:05:45'),(11,1,2,'T11','Table 11',4,11,1,'2026-11-11 02:05:45','2026-11-11 02:05:45'),(12,1,2,'T12','Table 12',4,12,1,'2026-11-11 02:05:45','2026-11-11 02:05:45'),(13,1,2,'T13','Table 13',4,13,1,'2026-11-11 02:05:45','2026-11-11 02:05:45'),(14,1,2,'T14','Table 14',4,14,1,'2026-11-11 02:05:45','2026-11-11 02:05:45'),(15,1,2,'T15','Table 15',6,15,1,'2026-11-11 02:05:45','2026-11-11 02:05:45');
/*!40000 ALTER TABLE `dining_tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `discounts`
--

DROP TABLE IF EXISTS `discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(13,2) NOT NULL,
  `computed_amount` decimal(13,2) NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discounts_restaurant_id_foreign` (`restaurant_id`),
  KEY `discounts_requested_by_foreign` (`requested_by`),
  KEY `discounts_approved_by_foreign` (`approved_by`),
  KEY `discounts_order_id_index` (`order_id`),
  CONSTRAINT `discounts_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discounts_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discounts_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discounts_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discounts`
--

LOCK TABLES `discounts` WRITE;
/*!40000 ALTER TABLE `discounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `discounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
-- Table structure for table `idempotency_keys`
--

DROP TABLE IF EXISTS `idempotency_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `idempotency_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `scope` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_code` smallint unsigned NOT NULL,
  `response_body` json NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_unique` (`restaurant_id`,`user_id`,`scope`,`idempotency_key`),
  KEY `idempotency_keys_user_id_foreign` (`user_id`),
  CONSTRAINT `idempotency_keys_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `idempotency_keys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `idempotency_keys`
--

LOCK TABLES `idempotency_keys` WRITE;
/*!40000 ALTER TABLE `idempotency_keys` DISABLE KEYS */;
INSERT INTO `idempotency_keys` VALUES (1,1,1,'tables.4.orders.open','open-4-1794400110731','cc8f7ffdc51e504356fb895ffc27162738f2286d42cce09ca17a794a229127e6',201,'{\"data\": {\"id\": 1, \"kots\": [], \"note\": null, \"items\": [], \"table\": {\"id\": 4, \"code\": \"T4\", \"zone\": {\"id\": 1, \"name\": \"Indoor\"}, \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T08:05:44.000000Z\", \"sort_order\": 4, \"updated_at\": \"2026-11-11T08:05:44.000000Z\", \"display_name\": \"Table 4\", \"restaurant_id\": 1}, \"total\": \"0.00\", \"status\": \"OPEN\", \"waiter\": {\"id\": 1, \"name\": \"Local Manager\"}, \"version\": 1, \"subtotal\": \"0.00\", \"opened_at\": \"2026-11-11T12:28:30.000000Z\", \"transfers\": [], \"waiter_id\": 1, \"created_at\": \"2026-11-11T12:28:30.000000Z\", \"tax_amount\": \"0.00\", \"updated_at\": \"2026-11-11T12:28:30.000000Z\", \"balance_due\": \"0.00\", \"guest_count\": 3, \"paid_amount\": \"0.00\", \"order_number\": \"ORD-0001\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 4, \"discount_amount\": \"0.00\"}}','2026-11-11 06:28:31'),(2,1,1,'orders.1.send','send-1-1794400139411','9af23cea10a478f3bb916ff35c07a36debe444adf9b56814ab63861ec674ab8b',201,'{\"data\": {\"id\": 1, \"kots\": [{\"id\": 1, \"items\": [{\"id\": 1, \"sku\": \"BRG-02\", \"note\": null, \"kot_id\": 1, \"status\": \"PENDING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Crispy Chicken Burger\", \"served_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"started_at\": null, \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"cancelled_at\": null, \"cancelled_by\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 2, \"restaurant_id\": 1, \"cancelled_quantity\": \"0.000\", \"modifiers_snapshot\": []}], \"round\": {\"id\": 1, \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"sent_by\": 1, \"round_number\": 1}, \"status\": \"PENDING\", \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"station\": \"KITCHEN\", \"order_id\": 1, \"ready_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"kot_number\": \"KOT-0001\", \"started_at\": null, \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"reprint_count\": 0, \"restaurant_id\": 1, \"order_round_id\": 1, \"last_reprinted_at\": null, \"last_reprint_reason\": null}, {\"id\": 2, \"items\": [{\"id\": 2, \"sku\": \"BEV-03\", \"note\": null, \"kot_id\": 2, \"status\": \"PENDING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Artisanal Cola\", \"served_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"started_at\": null, \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"cancelled_at\": null, \"cancelled_by\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 1, \"restaurant_id\": 1, \"cancelled_quantity\": \"0.000\", \"modifiers_snapshot\": []}], \"round\": {\"id\": 1, \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"sent_by\": 1, \"round_number\": 1}, \"status\": \"PENDING\", \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"station\": \"BAR\", \"order_id\": 1, \"ready_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"kot_number\": \"KOT-0002\", \"started_at\": null, \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"reprint_count\": 0, \"restaurant_id\": 1, \"order_round_id\": 1, \"last_reprinted_at\": null, \"last_reprint_reason\": null}], \"note\": null, \"items\": [{\"id\": 1, \"sku\": \"BEV-03\", \"note\": null, \"status\": \"PENDING\", \"station\": \"BAR\", \"version\": 2, \"line_tax\": \"6.00\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Artisanal Cola\", \"modifiers\": [], \"created_at\": \"2026-11-11T12:28:42.000000Z\", \"line_total\": \"126.00\", \"unit_price\": \"120.00\", \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"menu_item_id\": 8, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"120.00\", \"restaurant_id\": 1, \"sent_quantity\": \"1.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}, {\"id\": 2, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"PENDING\", \"station\": \"KITCHEN\", \"version\": 2, \"line_tax\": \"19.50\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"created_at\": \"2026-11-11T12:28:55.000000Z\", \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"menu_item_id\": 2, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"390.00\", \"restaurant_id\": 1, \"sent_quantity\": \"1.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}], \"table\": {\"id\": 4, \"code\": \"T4\", \"zone\": {\"id\": 1, \"name\": \"Indoor\"}, \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T08:05:44.000000Z\", \"sort_order\": 4, \"updated_at\": \"2026-11-11T08:05:44.000000Z\", \"display_name\": \"Table 4\", \"restaurant_id\": 1}, \"total\": \"535.50\", \"status\": \"OPEN\", \"waiter\": {\"id\": 1, \"name\": \"Local Manager\"}, \"version\": 4, \"subtotal\": \"510.00\", \"closed_at\": null, \"opened_at\": \"2026-11-11T12:28:30.000000Z\", \"transfers\": [], \"waiter_id\": 1, \"created_at\": \"2026-11-11T12:28:30.000000Z\", \"tax_amount\": \"25.50\", \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"balance_due\": \"535.50\", \"guest_count\": 3, \"paid_amount\": \"0.00\", \"order_number\": \"ORD-0001\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 4, \"discount_amount\": \"0.00\"}, \"kots\": [{\"id\": 1, \"items\": [{\"id\": 1, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"PENDING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"served_at\": null, \"started_at\": null, \"cancelled_at\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 2, \"cancelled_quantity\": \"0.000\"}], \"table\": {\"id\": 4, \"code\": \"T4\", \"zone\": \"Indoor\", \"display_name\": \"Table 4\"}, \"status\": \"PENDING\", \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"station\": \"KITCHEN\", \"order_id\": 1, \"ready_at\": null, \"kot_number\": \"KOT-0001\", \"order_note\": null, \"started_at\": null, \"guest_count\": 3, \"waiter_name\": \"Local Manager\", \"order_number\": \"ORD-0001\", \"round_number\": 1, \"reprint_count\": 0, \"restaurant_name\": \"Demo Restaurant\", \"last_reprinted_at\": null, \"last_reprint_reason\": null}, {\"id\": 2, \"items\": [{\"id\": 2, \"sku\": \"BEV-03\", \"note\": null, \"status\": \"PENDING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Artisanal Cola\", \"modifiers\": [], \"served_at\": null, \"started_at\": null, \"cancelled_at\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 1, \"cancelled_quantity\": \"0.000\"}], \"table\": {\"id\": 4, \"code\": \"T4\", \"zone\": \"Indoor\", \"display_name\": \"Table 4\"}, \"status\": \"PENDING\", \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"station\": \"BAR\", \"order_id\": 1, \"ready_at\": null, \"kot_number\": \"KOT-0002\", \"order_note\": null, \"started_at\": null, \"guest_count\": 3, \"waiter_name\": \"Local Manager\", \"order_number\": \"ORD-0001\", \"round_number\": 1, \"reprint_count\": 0, \"restaurant_name\": \"Demo Restaurant\", \"last_reprinted_at\": null, \"last_reprint_reason\": null}]}','2026-11-11 06:28:59'),(3,1,1,'tables.4.orders.open','open-4-1794478349421','35a493860fc13a37140f44a9769c56f53805b4d96941e985e43860bf49659aa1',200,'{\"data\": {\"id\": 1, \"kots\": [{\"id\": 1, \"items\": [{\"id\": 1, \"sku\": \"BRG-02\", \"note\": null, \"kot_id\": 1, \"status\": \"PENDING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Crispy Chicken Burger\", \"served_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"started_at\": null, \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"cancelled_at\": null, \"cancelled_by\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 2, \"restaurant_id\": 1, \"cancelled_quantity\": \"0.000\", \"modifiers_snapshot\": []}], \"round\": {\"id\": 1, \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"sent_by\": 1, \"round_number\": 1}, \"status\": \"PENDING\", \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"station\": \"KITCHEN\", \"order_id\": 1, \"ready_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"kot_number\": \"KOT-0001\", \"started_at\": null, \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"reprint_count\": 0, \"restaurant_id\": 1, \"order_round_id\": 1, \"last_reprinted_at\": null, \"last_reprint_reason\": null}, {\"id\": 2, \"items\": [{\"id\": 2, \"sku\": \"BEV-03\", \"note\": null, \"kot_id\": 2, \"status\": \"PENDING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Artisanal Cola\", \"served_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"started_at\": null, \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"cancelled_at\": null, \"cancelled_by\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 1, \"restaurant_id\": 1, \"cancelled_quantity\": \"0.000\", \"modifiers_snapshot\": []}], \"round\": {\"id\": 1, \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"sent_by\": 1, \"round_number\": 1}, \"status\": \"PENDING\", \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"station\": \"BAR\", \"order_id\": 1, \"ready_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"kot_number\": \"KOT-0002\", \"started_at\": null, \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"reprint_count\": 0, \"restaurant_id\": 1, \"order_round_id\": 1, \"last_reprinted_at\": null, \"last_reprint_reason\": null}], \"note\": null, \"items\": [{\"id\": 1, \"sku\": \"BEV-03\", \"note\": null, \"status\": \"PENDING\", \"station\": \"BAR\", \"version\": 2, \"line_tax\": \"6.00\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Artisanal Cola\", \"modifiers\": [], \"created_at\": \"2026-11-11T12:28:42.000000Z\", \"line_total\": \"126.00\", \"unit_price\": \"120.00\", \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"menu_item_id\": 8, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"120.00\", \"restaurant_id\": 1, \"sent_quantity\": \"1.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}, {\"id\": 2, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"PENDING\", \"station\": \"KITCHEN\", \"version\": 2, \"line_tax\": \"19.50\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"created_at\": \"2026-11-11T12:28:55.000000Z\", \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"menu_item_id\": 2, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"390.00\", \"restaurant_id\": 1, \"sent_quantity\": \"1.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}], \"table\": {\"id\": 4, \"code\": \"T4\", \"zone\": {\"id\": 1, \"name\": \"Indoor\"}, \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T08:05:44.000000Z\", \"sort_order\": 4, \"updated_at\": \"2026-11-11T08:05:44.000000Z\", \"display_name\": \"Table 4\", \"restaurant_id\": 1}, \"total\": \"535.50\", \"status\": \"OPEN\", \"waiter\": {\"id\": 1, \"name\": \"Local Manager\"}, \"version\": 4, \"payments\": [], \"subtotal\": \"510.00\", \"closed_at\": null, \"discounts\": [], \"opened_at\": \"2026-11-11T12:28:30.000000Z\", \"transfers\": [], \"waiter_id\": 1, \"created_at\": \"2026-11-11T12:28:30.000000Z\", \"tax_amount\": \"25.50\", \"updated_at\": \"2026-11-11T12:28:59.000000Z\", \"balance_due\": \"535.50\", \"guest_count\": 3, \"paid_amount\": \"0.00\", \"order_number\": \"ORD-0001\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 4, \"discount_amount\": \"0.00\"}}','2026-11-12 04:12:29'),(4,1,1,'tables.2.orders.open','open-2-1794481864147','35a493860fc13a37140f44a9769c56f53805b4d96941e985e43860bf49659aa1',201,'{\"data\": {\"id\": 2, \"kots\": [], \"note\": null, \"items\": [], \"table\": {\"id\": 2, \"code\": \"T2\", \"zone\": {\"id\": 1, \"name\": \"Indoor\"}, \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T08:05:44.000000Z\", \"sort_order\": 2, \"updated_at\": \"2026-11-11T08:05:44.000000Z\", \"display_name\": \"Table 2\", \"restaurant_id\": 1}, \"total\": \"0.00\", \"status\": \"OPEN\", \"waiter\": {\"id\": 1, \"name\": \"Local Manager\"}, \"version\": 1, \"payments\": [], \"subtotal\": \"0.00\", \"discounts\": [], \"opened_at\": \"2026-11-12T11:11:04.000000Z\", \"transfers\": [], \"waiter_id\": 1, \"created_at\": \"2026-11-12T11:11:04.000000Z\", \"tax_amount\": \"0.00\", \"updated_at\": \"2026-11-12T11:11:04.000000Z\", \"balance_due\": \"0.00\", \"guest_count\": 4, \"paid_amount\": \"0.00\", \"order_number\": \"ORD-0002\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 2, \"discount_amount\": \"0.00\"}}','2026-11-12 05:11:04'),(5,1,1,'orders.2.send','send-2-1794481886234','732322c128ed67841ef5aa42c7cd04ffcd74a61b2d17d710cd502b713d62930d',201,'{\"data\": {\"id\": 2, \"kots\": [{\"id\": 3, \"items\": [{\"id\": 3, \"sku\": \"BRG-02\", \"note\": null, \"kot_id\": 3, \"status\": \"PENDING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Crispy Chicken Burger\", \"served_at\": null, \"created_at\": \"2026-11-12T11:11:26.000000Z\", \"started_at\": null, \"updated_at\": \"2026-11-12T11:11:26.000000Z\", \"cancelled_at\": null, \"cancelled_by\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 4, \"restaurant_id\": 1, \"cancelled_quantity\": \"0.000\", \"modifiers_snapshot\": []}], \"round\": {\"id\": 2, \"sent_at\": \"2026-11-12T11:11:26.000000Z\", \"sent_by\": 1, \"round_number\": 1}, \"status\": \"PENDING\", \"sent_at\": \"2026-11-12T11:11:26.000000Z\", \"station\": \"KITCHEN\", \"order_id\": 2, \"ready_at\": null, \"created_at\": \"2026-11-12T11:11:26.000000Z\", \"kot_number\": \"KOT-0003\", \"started_at\": null, \"updated_at\": \"2026-11-12T11:11:26.000000Z\", \"reprint_count\": 0, \"restaurant_id\": 1, \"order_round_id\": 2, \"last_reprinted_at\": null, \"last_reprint_reason\": null}], \"note\": null, \"items\": [{\"id\": 4, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"PENDING\", \"station\": \"KITCHEN\", \"version\": 2, \"line_tax\": \"19.50\", \"order_id\": 2, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"created_at\": \"2026-11-12T11:11:09.000000Z\", \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-12T11:11:26.000000Z\", \"menu_item_id\": 2, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"390.00\", \"restaurant_id\": 1, \"sent_quantity\": \"1.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}], \"table\": {\"id\": 2, \"code\": \"T2\", \"zone\": {\"id\": 1, \"name\": \"Indoor\"}, \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T08:05:44.000000Z\", \"sort_order\": 2, \"updated_at\": \"2026-11-11T08:05:44.000000Z\", \"display_name\": \"Table 2\", \"restaurant_id\": 1}, \"total\": \"409.50\", \"status\": \"OPEN\", \"waiter\": {\"id\": 1, \"name\": \"Local Manager\"}, \"version\": 3, \"subtotal\": \"390.00\", \"closed_at\": null, \"opened_at\": \"2026-11-12T11:11:04.000000Z\", \"transfers\": [], \"waiter_id\": 1, \"created_at\": \"2026-11-12T11:11:04.000000Z\", \"tax_amount\": \"19.50\", \"updated_at\": \"2026-11-12T11:11:26.000000Z\", \"balance_due\": \"409.50\", \"guest_count\": 4, \"paid_amount\": \"0.00\", \"order_number\": \"ORD-0002\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 2, \"discount_amount\": \"0.00\"}, \"kots\": [{\"id\": 3, \"items\": [{\"id\": 3, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"PENDING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"served_at\": null, \"started_at\": null, \"cancelled_at\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 4, \"cancelled_quantity\": \"0.000\"}], \"table\": {\"id\": 2, \"code\": \"T2\", \"zone\": \"Indoor\", \"display_name\": \"Table 2\"}, \"status\": \"PENDING\", \"sent_at\": \"2026-11-12T11:11:26.000000Z\", \"station\": \"KITCHEN\", \"order_id\": 2, \"ready_at\": null, \"kot_number\": \"KOT-0003\", \"order_note\": null, \"started_at\": null, \"guest_count\": 4, \"waiter_name\": \"Local Manager\", \"order_number\": \"ORD-0002\", \"round_number\": 1, \"reprint_count\": 0, \"restaurant_name\": \"Clique Cafe\", \"last_reprinted_at\": null, \"last_reprint_reason\": null}]}','2026-11-12 05:11:26'),(6,1,1,'orders.2.checkout','checkout-2-1794481945155','c0b03bd827167c5aaa7499c385620ec0b16ef1e421ba49784706a492481e4185',201,'{\"data\": {\"id\": 2, \"kots\": [{\"id\": 3, \"items\": [{\"id\": 3, \"sku\": \"BRG-02\", \"note\": null, \"kot_id\": 3, \"status\": \"READY\", \"quantity\": \"1.000\", \"ready_at\": \"2026-11-12T11:11:43.000000Z\", \"item_name\": \"Crispy Chicken Burger\", \"served_at\": null, \"created_at\": \"2026-11-12T11:11:26.000000Z\", \"started_at\": \"2026-11-12T11:11:43.000000Z\", \"updated_at\": \"2026-11-12T11:11:43.000000Z\", \"cancelled_at\": null, \"cancelled_by\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 4, \"restaurant_id\": 1, \"cancelled_quantity\": \"0.000\", \"modifiers_snapshot\": []}], \"round\": {\"id\": 2, \"sent_at\": \"2026-11-12T11:11:26.000000Z\", \"sent_by\": 1, \"round_number\": 1}, \"status\": \"READY\", \"sent_at\": \"2026-11-12T11:11:26.000000Z\", \"station\": \"KITCHEN\", \"order_id\": 2, \"ready_at\": \"2026-11-12T11:11:43.000000Z\", \"created_at\": \"2026-11-12T11:11:26.000000Z\", \"kot_number\": \"KOT-0003\", \"started_at\": \"2026-11-12T11:11:43.000000Z\", \"updated_at\": \"2026-11-12T11:11:43.000000Z\", \"reprint_count\": 0, \"restaurant_id\": 1, \"order_round_id\": 2, \"last_reprinted_at\": null, \"last_reprint_reason\": null}], \"note\": null, \"items\": [{\"id\": 4, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"READY\", \"station\": \"KITCHEN\", \"version\": 2, \"line_tax\": \"19.50\", \"order_id\": 2, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"created_at\": \"2026-11-12T11:11:09.000000Z\", \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-12T11:11:43.000000Z\", \"menu_item_id\": 2, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"390.00\", \"restaurant_id\": 1, \"sent_quantity\": \"1.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}], \"table\": {\"id\": 2, \"code\": \"T2\", \"zone\": {\"id\": 1, \"name\": \"Indoor\"}, \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T08:05:44.000000Z\", \"sort_order\": 2, \"updated_at\": \"2026-11-11T08:05:44.000000Z\", \"display_name\": \"Table 2\", \"restaurant_id\": 1}, \"total\": \"409.50\", \"status\": \"PAID\", \"waiter\": {\"id\": 1, \"name\": \"Local Manager\"}, \"invoice\": {\"id\": 1, \"items\": [{\"id\": 1, \"sku\": \"BRG-02\", \"line_tax\": \"19.50\", \"quantity\": \"1.000\", \"item_name\": \"Crispy Chicken Burger\", \"created_at\": \"2026-11-12T11:12:25.000000Z\", \"invoice_id\": 1, \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-12T11:12:25.000000Z\", \"variant_name\": null, \"line_subtotal\": \"390.00\", \"modifiers_snapshot\": [], \"modifier_unit_total\": \"0.00\"}], \"total\": \"409.50\", \"status\": \"ISSUED\", \"order_id\": 2, \"subtotal\": \"390.00\", \"issued_at\": \"2026-11-12T11:12:25.000000Z\", \"issued_by\": 1, \"voided_at\": null, \"voided_by\": null, \"created_at\": \"2026-11-12T11:12:25.000000Z\", \"table_code\": \"T2\", \"table_name\": \"Table 2\", \"tax_amount\": \"19.50\", \"updated_at\": \"2026-11-12T11:12:25.000000Z\", \"guest_count\": 4, \"paid_amount\": \"409.50\", \"void_reason\": null, \"waiter_name\": \"Local Manager\", \"cashier_name\": \"Local Manager\", \"change_given\": \"0.00\", \"reprint_count\": 0, \"restaurant_id\": 1, \"tendered_cash\": \"409.50\", \"invoice_number\": \"INV-0001\", \"service_charge\": \"0.00\", \"discount_amount\": \"0.00\", \"refunded_amount\": \"0.00\", \"payment_snapshot\": [{\"id\": 1, \"amount\": \"409.50\", \"method\": \"CASH\", \"captured_at\": \"2026-11-12T11:12:25+00:00\", \"change_given\": \"0.00\", \"tendered_amount\": \"409.50\", \"provider_reference\": null}], \"last_reprinted_at\": null, \"last_reprint_reason\": null}, \"version\": 6, \"payments\": [{\"id\": 1, \"amount\": \"409.50\", \"method\": \"CASH\", \"status\": \"CAPTURED\", \"order_id\": 2, \"receiver\": {\"id\": 1, \"name\": \"Local Manager\"}, \"created_at\": \"2026-11-12T11:12:25.000000Z\", \"updated_at\": \"2026-11-12T11:12:25.000000Z\", \"captured_at\": \"2026-11-12T11:12:25.000000Z\", \"received_by\": 1, \"change_given\": \"0.00\", \"restaurant_id\": 1, \"tendered_amount\": \"409.50\", \"cashier_shift_id\": 1, \"provider_reference\": null}], \"subtotal\": \"390.00\", \"closed_at\": \"2026-11-12T11:12:25.000000Z\", \"discounts\": [], \"opened_at\": \"2026-11-12T11:11:04.000000Z\", \"transfers\": [], \"waiter_id\": 1, \"created_at\": \"2026-11-12T11:11:04.000000Z\", \"tax_amount\": \"19.50\", \"updated_at\": \"2026-11-12T11:12:25.000000Z\", \"balance_due\": \"0.00\", \"guest_count\": 4, \"paid_amount\": \"409.50\", \"order_number\": \"ORD-0002\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 2, \"discount_amount\": \"0.00\"}, \"invoice\": {\"id\": 1, \"items\": [{\"id\": 1, \"sku\": \"BRG-02\", \"line_tax\": \"19.50\", \"quantity\": \"1.000\", \"item_name\": \"Crispy Chicken Burger\", \"created_at\": \"2026-11-12T11:12:25.000000Z\", \"invoice_id\": 1, \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-12T11:12:25.000000Z\", \"variant_name\": null, \"line_subtotal\": \"390.00\", \"modifiers_snapshot\": [], \"modifier_unit_total\": \"0.00\"}], \"total\": \"409.50\", \"status\": \"ISSUED\", \"order_id\": 2, \"subtotal\": \"390.00\", \"issued_at\": \"2026-11-12T11:12:25.000000Z\", \"issued_by\": 1, \"created_at\": \"2026-11-12T11:12:25.000000Z\", \"table_code\": \"T2\", \"table_name\": \"Table 2\", \"tax_amount\": \"19.50\", \"updated_at\": \"2026-11-12T11:12:25.000000Z\", \"guest_count\": 4, \"paid_amount\": \"409.50\", \"waiter_name\": \"Local Manager\", \"cashier_name\": \"Local Manager\", \"change_given\": \"0.00\", \"restaurant_id\": 1, \"tendered_cash\": \"409.50\", \"invoice_number\": \"INV-0001\", \"service_charge\": \"0.00\", \"discount_amount\": \"0.00\", \"payment_snapshot\": [{\"id\": 1, \"amount\": \"409.50\", \"method\": \"CASH\", \"captured_at\": \"2026-11-12T11:12:25+00:00\", \"change_given\": \"0.00\", \"tendered_amount\": \"409.50\", \"provider_reference\": null}]}}','2026-11-12 05:12:25'),(7,1,1,'tables.4.orders.open','open-4-1794633236791','35a493860fc13a37140f44a9769c56f53805b4d96941e985e43860bf49659aa1',200,'{\"data\": {\"id\": 1, \"kots\": [{\"id\": 1, \"items\": [{\"id\": 1, \"sku\": \"BRG-02\", \"note\": null, \"kot_id\": 1, \"status\": \"READY\", \"quantity\": \"1.000\", \"ready_at\": \"2026-11-12T10:12:59.000000Z\", \"item_name\": \"Crispy Chicken Burger\", \"served_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"started_at\": \"2026-11-12T10:12:51.000000Z\", \"updated_at\": \"2026-11-12T10:12:59.000000Z\", \"cancelled_at\": null, \"cancelled_by\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 2, \"restaurant_id\": 1, \"cancelled_quantity\": \"0.000\", \"modifiers_snapshot\": []}], \"round\": {\"id\": 1, \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"sent_by\": 1, \"round_number\": 1}, \"status\": \"READY\", \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"station\": \"KITCHEN\", \"order_id\": 1, \"ready_at\": \"2026-11-12T10:12:59.000000Z\", \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"kot_number\": \"KOT-0001\", \"started_at\": \"2026-11-12T10:12:51.000000Z\", \"updated_at\": \"2026-11-12T10:12:59.000000Z\", \"reprint_count\": 0, \"restaurant_id\": 1, \"order_round_id\": 1, \"last_reprinted_at\": null, \"last_reprint_reason\": null}, {\"id\": 2, \"items\": [{\"id\": 2, \"sku\": \"BEV-03\", \"note\": null, \"kot_id\": 2, \"status\": \"PREPARING\", \"quantity\": \"1.000\", \"ready_at\": null, \"item_name\": \"Artisanal Cola\", \"served_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"started_at\": \"2026-11-12T11:11:41.000000Z\", \"updated_at\": \"2026-11-12T11:11:41.000000Z\", \"cancelled_at\": null, \"cancelled_by\": null, \"variant_name\": null, \"cancel_reason\": null, \"order_item_id\": 1, \"restaurant_id\": 1, \"cancelled_quantity\": \"0.000\", \"modifiers_snapshot\": []}], \"round\": {\"id\": 1, \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"sent_by\": 1, \"round_number\": 1}, \"status\": \"PREPARING\", \"sent_at\": \"2026-11-11T12:28:59.000000Z\", \"station\": \"BAR\", \"order_id\": 1, \"ready_at\": null, \"created_at\": \"2026-11-11T12:28:59.000000Z\", \"kot_number\": \"KOT-0002\", \"started_at\": \"2026-11-12T11:11:41.000000Z\", \"updated_at\": \"2026-11-12T11:11:41.000000Z\", \"reprint_count\": 0, \"restaurant_id\": 1, \"order_round_id\": 1, \"last_reprinted_at\": null, \"last_reprint_reason\": null}], \"note\": null, \"items\": [{\"id\": 1, \"sku\": \"BEV-03\", \"note\": null, \"status\": \"PREPARING\", \"station\": \"BAR\", \"version\": 2, \"line_tax\": \"6.00\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Artisanal Cola\", \"modifiers\": [], \"created_at\": \"2026-11-11T12:28:42.000000Z\", \"line_total\": \"126.00\", \"unit_price\": \"120.00\", \"updated_at\": \"2026-11-12T11:11:41.000000Z\", \"menu_item_id\": 8, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"120.00\", \"restaurant_id\": 1, \"sent_quantity\": \"1.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}, {\"id\": 2, \"sku\": \"BRG-02\", \"note\": null, \"status\": \"READY\", \"station\": \"KITCHEN\", \"version\": 2, \"line_tax\": \"19.50\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Crispy Chicken Burger\", \"modifiers\": [], \"created_at\": \"2026-11-11T12:28:55.000000Z\", \"line_total\": \"409.50\", \"unit_price\": \"390.00\", \"updated_at\": \"2026-11-12T10:12:59.000000Z\", \"menu_item_id\": 2, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"390.00\", \"restaurant_id\": 1, \"sent_quantity\": \"1.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}, {\"id\": 3, \"sku\": \"BRG-01\", \"note\": null, \"status\": \"DRAFT\", \"station\": \"KITCHEN\", \"version\": 1, \"line_tax\": \"22.50\", \"order_id\": 1, \"quantity\": \"1.000\", \"tax_rate\": \"5.000\", \"item_name\": \"Smokehouse Beef Burger\", \"modifiers\": [], \"created_at\": \"2026-11-12T11:10:44.000000Z\", \"line_total\": \"472.50\", \"unit_price\": \"450.00\", \"updated_at\": \"2026-11-12T11:10:44.000000Z\", \"menu_item_id\": 1, \"variant_name\": null, \"cancel_reason\": null, \"line_subtotal\": \"450.00\", \"restaurant_id\": 1, \"sent_quantity\": \"0.000\", \"tax_inclusive\": false, \"item_variant_id\": null, \"served_quantity\": \"0.000\", \"cancelled_quantity\": \"0.000\", \"modifier_unit_total\": \"0.00\"}], \"table\": {\"id\": 4, \"code\": \"T4\", \"zone\": {\"id\": 1, \"name\": \"Indoor\"}, \"active\": true, \"zone_id\": 1, \"capacity\": 4, \"created_at\": \"2026-11-11T08:05:44.000000Z\", \"sort_order\": 4, \"updated_at\": \"2026-11-11T08:05:44.000000Z\", \"display_name\": \"Table 4\", \"restaurant_id\": 1}, \"total\": \"1008.00\", \"status\": \"OPEN\", \"waiter\": {\"id\": 1, \"name\": \"Local Manager\"}, \"version\": 5, \"payments\": [], \"subtotal\": \"960.00\", \"closed_at\": null, \"discounts\": [], \"opened_at\": \"2026-11-11T12:28:30.000000Z\", \"transfers\": [], \"waiter_id\": 1, \"created_at\": \"2026-11-11T12:28:30.000000Z\", \"tax_amount\": \"48.00\", \"updated_at\": \"2026-11-12T11:10:44.000000Z\", \"balance_due\": \"1008.00\", \"guest_count\": 3, \"paid_amount\": \"0.00\", \"order_number\": \"ORD-0001\", \"restaurant_id\": 1, \"service_charge\": \"0.00\", \"dining_table_id\": 4, \"discount_amount\": \"0.00\"}}','2026-11-13 23:13:57');
/*!40000 ALTER TABLE `idempotency_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `sku` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General',
  `unit` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `current_stock` decimal(13,3) NOT NULL DEFAULT '0.000',
  `reorder_level` decimal(13,3) NOT NULL DEFAULT '0.000',
  `unit_cost` decimal(13,2) NOT NULL DEFAULT '0.00',
  `auto_eighty_six` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_items_restaurant_id_sku_unique` (`restaurant_id`,`sku`),
  KEY `inventory_items_restaurant_id_active_index` (`restaurant_id`,`active`),
  CONSTRAINT `inventory_items_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
INSERT INTO `inventory_items` VALUES (1,1,'ING-BEEF-PATTY','Beef Burger Patty 150g','Meat & Poultry','pcs',40.000,10.000,135.00,1,1,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(2,1,'ING-CHICKEN-THIGH','Crispy Chicken Thigh Fillet','Meat & Poultry','pcs',34.000,10.000,95.00,1,1,'2026-11-12 03:58:05','2026-11-12 05:11:26'),(3,1,'ING-BRIOCHE-BUN','Artisan Brioche Burger Bun','Bakery','pcs',59.000,15.000,28.00,1,1,'2026-11-12 03:58:05','2026-11-12 05:11:26'),(4,1,'ING-CHEDDAR-SLICE','Aged Cheddar Slice','Dairy','pcs',80.000,20.000,22.00,1,1,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(5,1,'ING-POTATO-FRIES','Hand-Cut Fries Portion','Produce','kg',18.000,5.000,160.00,1,1,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(6,1,'ING-COLA-BTL','Artisanal Cola 330ml Bottle','Beverages','btl',48.000,12.000,45.00,1,1,'2026-11-12 03:58:05','2026-11-12 03:58:05');
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `sku` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_price` decimal(13,2) NOT NULL,
  `modifier_unit_total` decimal(13,2) NOT NULL DEFAULT '0.00',
  `modifiers_snapshot` json DEFAULT NULL,
  `line_subtotal` decimal(13,2) NOT NULL,
  `line_tax` decimal(13,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(13,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (1,1,'BRG-02','Crispy Chicken Burger',NULL,1.000,390.00,0.00,'[]',390.00,19.50,409.50,'2026-11-12 05:12:25','2026-11-12 05:12:25');
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `invoice_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ISSUED',
  `guest_count` smallint unsigned NOT NULL DEFAULT '1',
  `table_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waiter_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cashier_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(13,2) NOT NULL,
  `discount_amount` decimal(13,2) NOT NULL DEFAULT '0.00',
  `service_charge` decimal(13,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(13,2) NOT NULL DEFAULT '0.00',
  `total` decimal(13,2) NOT NULL,
  `paid_amount` decimal(13,2) NOT NULL,
  `refunded_amount` decimal(13,2) NOT NULL DEFAULT '0.00',
  `tendered_cash` decimal(13,2) NOT NULL DEFAULT '0.00',
  `change_given` decimal(13,2) NOT NULL DEFAULT '0.00',
  `reprint_count` smallint unsigned NOT NULL DEFAULT '0',
  `last_reprinted_at` timestamp NULL DEFAULT NULL,
  `last_reprint_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `issued_at` timestamp NOT NULL,
  `void_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `payment_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_restaurant_id_invoice_number_unique` (`restaurant_id`,`invoice_number`),
  UNIQUE KEY `invoices_order_id_unique` (`order_id`),
  KEY `invoices_issued_by_foreign` (`issued_by`),
  KEY `invoices_voided_by_foreign` (`voided_by`),
  KEY `invoices_restaurant_id_issued_at_index` (`restaurant_id`,`issued_at`),
  CONSTRAINT `invoices_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoices_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `invoices_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,1,2,'INV-0001','ISSUED',4,'T2','Table 2','Local Manager','Local Manager',390.00,0.00,0.00,19.50,409.50,409.50,0.00,409.50,0.00,0,NULL,NULL,1,'2026-11-12 05:12:25',NULL,NULL,NULL,'[{\"id\": 1, \"amount\": \"409.50\", \"method\": \"CASH\", \"captured_at\": \"2026-11-12T11:12:25+00:00\", \"change_given\": \"0.00\", \"tendered_amount\": \"409.50\", \"provider_reference\": null}]','2026-11-12 05:12:25','2026-11-12 05:12:25');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_modifier_groups`
--

DROP TABLE IF EXISTS `item_modifier_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_modifier_groups` (
  `menu_item_id` bigint unsigned NOT NULL,
  `modifier_group_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`menu_item_id`,`modifier_group_id`),
  KEY `item_modifier_groups_modifier_group_id_foreign` (`modifier_group_id`),
  CONSTRAINT `item_modifier_groups_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_modifier_groups_modifier_group_id_foreign` FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_modifier_groups`
--

LOCK TABLES `item_modifier_groups` WRITE;
/*!40000 ALTER TABLE `item_modifier_groups` DISABLE KEYS */;
INSERT INTO `item_modifier_groups` VALUES (1,1),(2,1);
/*!40000 ALTER TABLE `item_modifier_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_variants`
--

DROP TABLE IF EXISTS `item_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_variants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `menu_item_id` bigint unsigned NOT NULL,
  `sku` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(13,2) NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_variants_restaurant_id_sku_unique` (`restaurant_id`,`sku`),
  UNIQUE KEY `item_variants_menu_item_id_name_unique` (`menu_item_id`,`name`),
  CONSTRAINT `item_variants_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_variants_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_variants`
--

LOCK TABLES `item_variants` WRITE;
/*!40000 ALTER TABLE `item_variants` DISABLE KEYS */;
/*!40000 ALTER TABLE `item_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
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
-- Table structure for table `kot_items`
--

DROP TABLE IF EXISTS `kot_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kot_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `kot_id` bigint unsigned NOT NULL,
  `order_item_id` bigint unsigned NOT NULL,
  `sku` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `cancelled_quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modifiers_snapshot` json DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `cancel_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `served_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kot_items_restaurant_id_foreign` (`restaurant_id`),
  KEY `kot_items_order_item_id_foreign` (`order_item_id`),
  KEY `kot_items_cancelled_by_foreign` (`cancelled_by`),
  KEY `kot_items_kot_id_status_index` (`kot_id`,`status`),
  CONSTRAINT `kot_items_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kot_items_kot_id_foreign` FOREIGN KEY (`kot_id`) REFERENCES `kots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kot_items_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kot_items_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kot_items`
--

LOCK TABLES `kot_items` WRITE;
/*!40000 ALTER TABLE `kot_items` DISABLE KEYS */;
INSERT INTO `kot_items` VALUES (1,1,1,2,'BRG-02','Crispy Chicken Burger',NULL,1.000,0.000,NULL,'[]','READY',NULL,NULL,'2026-11-12 04:12:51','2026-11-12 04:12:59',NULL,NULL,'2026-11-11 06:28:59','2026-11-12 04:12:59'),(2,1,2,1,'BEV-03','Artisanal Cola',NULL,1.000,0.000,NULL,'[]','PREPARING',NULL,NULL,'2026-11-12 05:11:41',NULL,NULL,NULL,'2026-11-11 06:28:59','2026-11-12 05:11:41'),(3,1,3,4,'BRG-02','Crispy Chicken Burger',NULL,1.000,0.000,NULL,'[]','READY',NULL,NULL,'2026-11-12 05:11:43','2026-11-12 05:11:43',NULL,NULL,'2026-11-12 05:11:26','2026-11-12 05:11:43');
/*!40000 ALTER TABLE `kot_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kot_status_events`
--

DROP TABLE IF EXISTS `kot_status_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kot_status_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `kot_id` bigint unsigned NOT NULL,
  `kot_item_id` bigint unsigned DEFAULT NULL,
  `from_status` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `kot_status_events_restaurant_id_foreign` (`restaurant_id`),
  KEY `kot_status_events_kot_item_id_foreign` (`kot_item_id`),
  KEY `kot_status_events_actor_id_foreign` (`actor_id`),
  KEY `kot_status_events_kot_id_created_at_index` (`kot_id`,`created_at`),
  CONSTRAINT `kot_status_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kot_status_events_kot_id_foreign` FOREIGN KEY (`kot_id`) REFERENCES `kots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kot_status_events_kot_item_id_foreign` FOREIGN KEY (`kot_item_id`) REFERENCES `kot_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kot_status_events_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kot_status_events`
--

LOCK TABLES `kot_status_events` WRITE;
/*!40000 ALTER TABLE `kot_status_events` DISABLE KEYS */;
INSERT INTO `kot_status_events` VALUES (1,1,1,1,'DRAFT','PENDING',1,NULL,'2026-11-11 06:28:59'),(2,1,2,2,'DRAFT','PENDING',1,NULL,'2026-11-11 06:28:59'),(3,1,1,NULL,'PENDING','PREPARING',1,NULL,'2026-11-12 04:12:51'),(4,1,1,NULL,'PREPARING','READY',1,NULL,'2026-11-12 04:12:59'),(5,1,3,3,'DRAFT','PENDING',1,NULL,'2026-11-12 05:11:26'),(6,1,2,NULL,'PENDING','PREPARING',1,NULL,'2026-11-12 05:11:41'),(7,1,3,NULL,'PENDING','READY',1,NULL,'2026-11-12 05:11:43');
/*!40000 ALTER TABLE `kot_status_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kots`
--

DROP TABLE IF EXISTS `kots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `order_round_id` bigint unsigned NOT NULL,
  `station` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kot_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `reprint_count` smallint unsigned NOT NULL DEFAULT '0',
  `last_reprinted_at` timestamp NULL DEFAULT NULL,
  `last_reprint_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` timestamp NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kots_restaurant_id_kot_number_unique` (`restaurant_id`,`kot_number`),
  UNIQUE KEY `kots_order_round_id_station_unique` (`order_round_id`,`station`),
  KEY `kots_order_id_foreign` (`order_id`),
  KEY `kots_restaurant_id_station_status_sent_at_index` (`restaurant_id`,`station`,`status`,`sent_at`),
  CONSTRAINT `kots_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kots_order_round_id_foreign` FOREIGN KEY (`order_round_id`) REFERENCES `order_rounds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kots_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kots`
--

LOCK TABLES `kots` WRITE;
/*!40000 ALTER TABLE `kots` DISABLE KEYS */;
INSERT INTO `kots` VALUES (1,1,1,1,'KITCHEN','KOT-0001','READY',0,NULL,NULL,'2026-11-11 06:28:59','2026-11-12 04:12:51','2026-11-12 04:12:59','2026-11-11 06:28:59','2026-11-12 04:12:59'),(2,1,1,1,'BAR','KOT-0002','PREPARING',0,NULL,NULL,'2026-11-11 06:28:59','2026-11-12 05:11:41',NULL,'2026-11-11 06:28:59','2026-11-12 05:11:41'),(3,1,2,2,'KITCHEN','KOT-0003','READY',0,NULL,NULL,'2026-11-12 05:11:26','2026-11-12 05:11:43','2026-11-12 05:11:43','2026-11-12 05:11:26','2026-11-12 05:11:43');
/*!40000 ALTER TABLE `kots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_categories`
--

DROP TABLE IF EXISTS `menu_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_station` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KITCHEN',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_categories_restaurant_id_name_unique` (`restaurant_id`,`name`),
  KEY `menu_categories_restaurant_id_active_sort_order_index` (`restaurant_id`,`active`,`sort_order`),
  CONSTRAINT `menu_categories_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_categories`
--

LOCK TABLES `menu_categories` WRITE;
/*!40000 ALTER TABLE `menu_categories` DISABLE KEYS */;
INSERT INTO `menu_categories` VALUES (1,1,'Mains & Burgers','KITCHEN',1,1,'2026-11-11 04:57:19','2026-11-11 04:57:19'),(2,1,'Starters & Sides','KITCHEN',2,1,'2026-11-11 04:57:19','2026-11-11 04:57:19'),(3,1,'Beverages & Bar','BAR',3,1,'2026-11-11 04:57:19','2026-11-11 04:57:19');
/*!40000 ALTER TABLE `menu_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_item_ingredients`
--

DROP TABLE IF EXISTS `menu_item_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_item_ingredients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `menu_item_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity` decimal(13,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_item_ing_unique` (`menu_item_id`,`inventory_item_id`),
  KEY `menu_item_ingredients_restaurant_id_foreign` (`restaurant_id`),
  KEY `menu_item_ingredients_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `menu_item_ingredients_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_item_ingredients_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_item_ingredients_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_item_ingredients`
--

LOCK TABLES `menu_item_ingredients` WRITE;
/*!40000 ALTER TABLE `menu_item_ingredients` DISABLE KEYS */;
INSERT INTO `menu_item_ingredients` VALUES (1,1,1,1,1.000,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(2,1,1,3,1.000,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(3,1,1,4,1.000,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(4,1,2,2,1.000,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(5,1,2,3,1.000,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(6,1,5,5,0.250,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(7,1,8,6,1.000,'2026-11-12 03:58:05','2026-11-12 03:58:05');
/*!40000 ALTER TABLE `menu_item_ingredients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `menu_category_id` bigint unsigned NOT NULL,
  `menu_subcategory_id` bigint unsigned DEFAULT NULL,
  `tax_category_id` bigint unsigned DEFAULT NULL,
  `sku` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` longtext COLLATE utf8mb4_unicode_ci,
  `station` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KITCHEN',
  `price` decimal(13,2) NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_items_restaurant_id_sku_unique` (`restaurant_id`,`sku`),
  UNIQUE KEY `menu_items_restaurant_id_menu_category_id_name_unique` (`restaurant_id`,`menu_category_id`,`name`),
  KEY `menu_items_menu_category_id_foreign` (`menu_category_id`),
  KEY `menu_items_tax_category_id_foreign` (`tax_category_id`),
  KEY `menu_items_restaurant_id_active_available_sort_order_index` (`restaurant_id`,`active`,`available`,`sort_order`),
  KEY `menu_items_menu_subcategory_id_foreign` (`menu_subcategory_id`),
  CONSTRAINT `menu_items_menu_category_id_foreign` FOREIGN KEY (`menu_category_id`) REFERENCES `menu_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `menu_items_menu_subcategory_id_foreign` FOREIGN KEY (`menu_subcategory_id`) REFERENCES `menu_subcategories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `menu_items_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `menu_items_tax_category_id_foreign` FOREIGN KEY (`tax_category_id`) REFERENCES `tax_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,1,1,NULL,1,'BRG-01','Smokehouse Beef Burger','Chargrilled patty, cheddar, house smoky mayo, brioche bun.','https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=640&q=80','KITCHEN',450.00,1,1,1,'2026-11-11 04:57:19','2026-11-12 04:47:01'),(2,1,1,NULL,1,'BRG-02','Crispy Chicken Burger','Buttermilk fried thigh, spicy slaw, pickles.','https://images.unsplash.com/photo-1606755962773-d324e0a13086?auto=format&fit=crop&w=640&q=80','KITCHEN',390.00,2,1,1,'2026-11-11 04:57:19','2026-11-12 04:47:01'),(3,1,1,NULL,1,'MNS-01','Grilled Herb Chicken Steak','Served with mashed potatoes and sautéed vegetables.','https://images.unsplash.com/photo-1532550907401-a500c9a57435?auto=format&fit=crop&w=640&q=80','KITCHEN',620.00,3,1,1,'2026-11-11 04:57:19','2026-11-12 04:47:02'),(4,1,2,NULL,1,'STR-01','Peri-Peri Chicken Wings (6 pcs)','Flame-grilled wings tossed in house peri-peri glaze.','https://images.unsplash.com/photo-1527477396000-e27163b481c2?auto=format&fit=crop&w=640&q=80','KITCHEN',320.00,1,1,1,'2026-11-11 04:57:19','2026-11-12 04:47:02'),(5,1,2,NULL,1,'STR-02','Truffle Parmesan Fries','Hand-cut fries with grated parmesan and herb oil.','https://images.unsplash.com/photo-1576107232684-1279f3908594?auto=format&fit=crop&w=640&q=80','KITCHEN',240.00,2,1,1,'2026-11-11 04:57:19','2026-11-12 04:47:02'),(6,1,3,NULL,1,'BEV-01','Fresh Lime Soda','Sweet, salted, or mixed fresh lime sparkler.','https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=640&q=80','BAR',140.00,1,1,1,'2026-11-11 04:57:19','2026-11-12 04:47:02'),(7,1,3,NULL,1,'BEV-02','Iced Hazelnut Latte','Double-shot espresso over ice with roasted hazelnut.','https://images.unsplash.com/photo-1517701550927-30cf4ba1dba5?auto=format&fit=crop&w=640&q=80','BAR',260.00,2,1,1,'2026-11-11 04:57:19','2026-11-12 04:47:02'),(8,1,3,NULL,1,'BEV-03','Artisanal Cola','Chilled 330ml glass bottle.','https://images.unsplash.com/photo-1554866585-cd94860890b7?auto=format&fit=crop&w=640&q=80','BAR',120.00,3,1,1,'2026-11-11 04:57:19','2026-11-12 04:47:02');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_subcategories`
--

DROP TABLE IF EXISTS `menu_subcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_subcategories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `menu_category_id` bigint unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `menu_subcategories_menu_category_id_name_unique` (`menu_category_id`,`name`),
  KEY `menu_subcategories_lookup_index` (`restaurant_id`,`menu_category_id`,`active`,`sort_order`),
  CONSTRAINT `menu_subcategories_menu_category_id_foreign` FOREIGN KEY (`menu_category_id`) REFERENCES `menu_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `menu_subcategories_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_subcategories`
--

LOCK TABLES `menu_subcategories` WRITE;
/*!40000 ALTER TABLE `menu_subcategories` DISABLE KEYS */;
/*!40000 ALTER TABLE `menu_subcategories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_11_11_000001_create_restaurant_access_tables',1),(5,'2026_11_11_000002_create_floor_tables',1),(6,'2026_11_11_000003_create_audit_events_table',1),(7,'2026_11_11_000004_create_menu_tables',2),(8,'2026_11_11_000005_create_order_core_tables',3),(9,'2026_11_11_000006_create_kot_tables',4),(10,'2026_11_12_000007_add_subcategories_and_menu_configuration',5),(11,'2026_11_12_000008_create_billing_and_shift_tables',6),(12,'2026_11_12_000009_create_refunds_table',7),(13,'2026_11_12_000010_create_inventory_and_purchasing_tables',7),(14,'2026_11_12_000011_widen_menu_item_image_path',8);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modifier_groups`
--

DROP TABLE IF EXISTS `modifier_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modifier_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_selections` smallint unsigned NOT NULL DEFAULT '0',
  `max_selections` smallint unsigned NOT NULL DEFAULT '1',
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modifier_groups_restaurant_id_name_unique` (`restaurant_id`,`name`),
  CONSTRAINT `modifier_groups_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modifier_groups`
--

LOCK TABLES `modifier_groups` WRITE;
/*!40000 ALTER TABLE `modifier_groups` DISABLE KEYS */;
INSERT INTO `modifier_groups` VALUES (1,1,'Burger Add-ons',0,3,0,1,1,'2026-11-11 04:57:18','2026-11-11 04:57:18');
/*!40000 ALTER TABLE `modifier_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modifier_options`
--

DROP TABLE IF EXISTS `modifier_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modifier_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `modifier_group_id` bigint unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(13,2) NOT NULL DEFAULT '0.00',
  `station_override` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modifier_options_modifier_group_id_name_unique` (`modifier_group_id`,`name`),
  KEY `modifier_options_restaurant_id_foreign` (`restaurant_id`),
  CONSTRAINT `modifier_options_modifier_group_id_foreign` FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `modifier_options_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modifier_options`
--

LOCK TABLES `modifier_options` WRITE;
/*!40000 ALTER TABLE `modifier_options` DISABLE KEYS */;
INSERT INTO `modifier_options` VALUES (1,1,1,'Extra Cheese',60.00,NULL,1,1,1,'2026-11-11 04:57:19','2026-11-11 04:57:19'),(2,1,1,'Beef Bacon',120.00,NULL,2,1,1,'2026-11-11 04:57:19','2026-11-11 04:57:19'),(3,1,1,'Jalapeños',40.00,NULL,3,1,1,'2026-11-11 04:57:19','2026-11-11 04:57:19');
/*!40000 ALTER TABLE `modifier_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_item_modifiers`
--

DROP TABLE IF EXISTS `order_item_modifiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_item_modifiers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint unsigned NOT NULL,
  `modifier_option_id` bigint unsigned DEFAULT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(13,2) NOT NULL DEFAULT '0.00',
  `quantity` smallint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_item_modifiers_order_item_id_foreign` (`order_item_id`),
  KEY `order_item_modifiers_modifier_option_id_foreign` (`modifier_option_id`),
  CONSTRAINT `order_item_modifiers_modifier_option_id_foreign` FOREIGN KEY (`modifier_option_id`) REFERENCES `modifier_options` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_item_modifiers_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_item_modifiers`
--

LOCK TABLES `order_item_modifiers` WRITE;
/*!40000 ALTER TABLE `order_item_modifiers` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_item_modifiers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `menu_item_id` bigint unsigned DEFAULT NULL,
  `item_variant_id` bigint unsigned DEFAULT NULL,
  `sku` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `station` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KITCHEN',
  `unit_price` decimal(13,2) NOT NULL,
  `modifier_unit_total` decimal(13,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(6,3) NOT NULL DEFAULT '0.000',
  `tax_inclusive` tinyint(1) NOT NULL DEFAULT '0',
  `quantity` decimal(10,3) NOT NULL DEFAULT '1.000',
  `sent_quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `cancelled_quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `served_quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `line_subtotal` decimal(13,2) NOT NULL DEFAULT '0.00',
  `line_tax` decimal(13,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(13,2) NOT NULL DEFAULT '0.00',
  `note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `cancel_reason` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_restaurant_id_foreign` (`restaurant_id`),
  KEY `order_items_menu_item_id_foreign` (`menu_item_id`),
  KEY `order_items_item_variant_id_foreign` (`item_variant_id`),
  KEY `order_items_order_id_status_index` (`order_id`,`status`),
  CONSTRAINT `order_items_item_variant_id_foreign` FOREIGN KEY (`item_variant_id`) REFERENCES `item_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,8,NULL,'BEV-03','Artisanal Cola',NULL,'BAR',120.00,0.00,5.000,0,1.000,1.000,0.000,0.000,120.00,6.00,126.00,NULL,'CANCELLED','time',2,'2026-11-11 06:28:42','2026-11-13 23:14:30'),(2,1,1,2,NULL,'BRG-02','Crispy Chicken Burger',NULL,'KITCHEN',390.00,0.00,5.000,0,1.000,1.000,0.000,0.000,390.00,19.50,409.50,NULL,'CANCELLED','time',2,'2026-11-11 06:28:55','2026-11-13 23:14:30'),(3,1,1,1,NULL,'BRG-01','Smokehouse Beef Burger',NULL,'KITCHEN',450.00,0.00,5.000,0,1.000,0.000,0.000,0.000,450.00,22.50,472.50,NULL,'CANCELLED','time',1,'2026-11-12 05:10:44','2026-11-13 23:14:30'),(4,1,2,2,NULL,'BRG-02','Crispy Chicken Burger',NULL,'KITCHEN',390.00,0.00,5.000,0,1.000,1.000,0.000,0.000,390.00,19.50,409.50,NULL,'READY',NULL,2,'2026-11-12 05:11:09','2026-11-12 05:11:43');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_merges`
--

DROP TABLE IF EXISTS `order_merges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_merges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `source_order_id` bigint unsigned NOT NULL,
  `target_order_id` bigint unsigned NOT NULL,
  `from_dining_table_id` bigint unsigned NOT NULL,
  `to_dining_table_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `snapshot` json DEFAULT NULL,
  `merged_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_merges_restaurant_id_foreign` (`restaurant_id`),
  KEY `order_merges_source_order_id_foreign` (`source_order_id`),
  KEY `order_merges_target_order_id_foreign` (`target_order_id`),
  KEY `order_merges_from_dining_table_id_foreign` (`from_dining_table_id`),
  KEY `order_merges_to_dining_table_id_foreign` (`to_dining_table_id`),
  KEY `order_merges_actor_id_foreign` (`actor_id`),
  CONSTRAINT `order_merges_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_merges_from_dining_table_id_foreign` FOREIGN KEY (`from_dining_table_id`) REFERENCES `dining_tables` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_merges_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_merges_source_order_id_foreign` FOREIGN KEY (`source_order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_merges_target_order_id_foreign` FOREIGN KEY (`target_order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_merges_to_dining_table_id_foreign` FOREIGN KEY (`to_dining_table_id`) REFERENCES `dining_tables` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_merges`
--

LOCK TABLES `order_merges` WRITE;
/*!40000 ALTER TABLE `order_merges` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_merges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_rounds`
--

DROP TABLE IF EXISTS `order_rounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_rounds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `round_number` smallint unsigned NOT NULL,
  `sent_by` bigint unsigned DEFAULT NULL,
  `sent_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_rounds_order_id_round_number_unique` (`order_id`,`round_number`),
  KEY `order_rounds_restaurant_id_foreign` (`restaurant_id`),
  KEY `order_rounds_sent_by_foreign` (`sent_by`),
  CONSTRAINT `order_rounds_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_rounds_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_rounds_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_rounds`
--

LOCK TABLES `order_rounds` WRITE;
/*!40000 ALTER TABLE `order_rounds` DISABLE KEYS */;
INSERT INTO `order_rounds` VALUES (1,1,1,1,1,'2026-11-11 06:28:59','2026-11-11 06:28:59','2026-11-11 06:28:59'),(2,1,2,1,1,'2026-11-12 05:11:26','2026-11-12 05:11:26','2026-11-12 05:11:26');
/*!40000 ALTER TABLE `order_rounds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `dining_table_id` bigint unsigned NOT NULL,
  `order_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `waiter_id` bigint unsigned DEFAULT NULL,
  `guest_count` smallint unsigned NOT NULL DEFAULT '1',
  `version` int unsigned NOT NULL DEFAULT '1',
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(13,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(13,2) NOT NULL DEFAULT '0.00',
  `service_charge` decimal(13,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(13,2) NOT NULL DEFAULT '0.00',
  `total` decimal(13,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(13,2) NOT NULL DEFAULT '0.00',
  `balance_due` decimal(13,2) NOT NULL DEFAULT '0.00',
  `opened_at` timestamp NOT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_restaurant_id_order_number_unique` (`restaurant_id`,`order_number`),
  KEY `orders_waiter_id_foreign` (`waiter_id`),
  KEY `orders_dining_table_id_status_index` (`dining_table_id`,`status`),
  KEY `orders_restaurant_id_opened_at_index` (`restaurant_id`,`opened_at`),
  CONSTRAINT `orders_dining_table_id_foreign` FOREIGN KEY (`dining_table_id`) REFERENCES `dining_tables` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_waiter_id_foreign` FOREIGN KEY (`waiter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,1,4,'ORD-0001','CANCELLED',1,1,8,NULL,960.00,0.00,0.00,48.00,1008.00,0.00,1008.00,'2026-11-11 06:28:30','2026-11-13 23:14:30','2026-11-11 06:28:30','2026-11-13 23:14:30'),(2,1,2,'ORD-0002','PAID',1,4,6,NULL,390.00,0.00,0.00,19.50,409.50,409.50,0.00,'2026-11-12 05:11:04','2026-11-12 05:12:25','2026-11-12 05:11:04','2026-11-12 05:12:25');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `cashier_shift_id` bigint unsigned DEFAULT NULL,
  `method` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(13,2) NOT NULL,
  `tendered_amount` decimal(13,2) DEFAULT NULL,
  `change_given` decimal(13,2) NOT NULL DEFAULT '0.00',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CAPTURED',
  `provider_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_by` bigint unsigned DEFAULT NULL,
  `captured_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_restaurant_id_foreign` (`restaurant_id`),
  KEY `payments_received_by_foreign` (`received_by`),
  KEY `payments_order_id_status_index` (`order_id`,`status`),
  KEY `payments_cashier_shift_id_method_status_index` (`cashier_shift_id`,`method`,`status`),
  CONSTRAINT `payments_cashier_shift_id_foreign` FOREIGN KEY (`cashier_shift_id`) REFERENCES `cashier_shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payments_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,2,1,'CASH',409.50,409.50,0.00,'CAPTURED',NULL,1,'2026-11-12 05:12:25','2026-11-12 05:12:25','2026-11-12 05:12:25');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permission_role`
--

DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permission_role`
--

LOCK TABLES `permission_role` WRITE;
/*!40000 ALTER TABLE `permission_role` DISABLE KEYS */;
INSERT INTO `permission_role` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(1,2),(2,2),(3,2),(6,2),(7,2),(8,2),(9,2),(10,2),(11,2),(12,2),(1,3),(7,3),(1,4),(7,4),(10,4),(8,5),(9,6);
/*!40000 ALTER TABLE `permission_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'view-floor','View floor and table status','2026-11-11 02:05:43','2026-11-11 02:05:43'),(2,'manage-floor','Manage zones and tables','2026-11-11 02:05:43','2026-11-11 02:05:43'),(3,'manage-menu','Manage menu and pricing','2026-11-11 02:05:43','2026-11-11 02:05:43'),(4,'manage-users','Manage users and roles','2026-11-11 02:05:43','2026-11-11 02:05:43'),(5,'manage-security','Manage security-sensitive access controls','2026-11-11 02:05:43','2026-11-11 02:05:43'),(6,'manage-settings','Manage restaurant settings','2026-11-11 02:05:43','2026-11-11 02:05:43'),(7,'manage-orders','Create and update orders','2026-11-11 02:05:43','2026-11-11 02:05:43'),(8,'manage-kitchen','Update Kitchen tickets','2026-11-11 02:05:43','2026-11-11 02:05:43'),(9,'manage-bar','Update Bar tickets','2026-11-11 02:05:43','2026-11-11 02:05:43'),(10,'take-payment','Take payments and finalize invoices','2026-11-11 02:05:43','2026-11-11 02:05:43'),(11,'refund-payment','Void and refund payments','2026-11-11 02:05:43','2026-11-11 02:05:43'),(12,'view-reports','View financial reports','2026-11-11 02:05:43','2026-11-11 02:05:43');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `price_histories`
--

DROP TABLE IF EXISTS `price_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `priceable_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priceable_id` bigint unsigned NOT NULL,
  `amount` decimal(13,2) NOT NULL,
  `effective_from` timestamp NOT NULL,
  `effective_to` timestamp NULL DEFAULT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `price_histories_restaurant_id_foreign` (`restaurant_id`),
  KEY `price_histories_changed_by_foreign` (`changed_by`),
  KEY `price_histories_priceable_type_priceable_id_effective_from_index` (`priceable_type`,`priceable_id`,`effective_from`),
  CONSTRAINT `price_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `price_histories_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `price_histories`
--

LOCK TABLES `price_histories` WRITE;
/*!40000 ALTER TABLE `price_histories` DISABLE KEYS */;
INSERT INTO `price_histories` VALUES (1,1,'App\\Models\\MenuItem',1,450.00,'2026-11-11 04:57:19',NULL,NULL,'Initial seed price','2026-11-11 04:57:19','2026-11-11 04:57:19'),(2,1,'App\\Models\\MenuItem',2,390.00,'2026-11-11 04:57:19',NULL,NULL,'Initial seed price','2026-11-11 04:57:19','2026-11-11 04:57:19'),(3,1,'App\\Models\\MenuItem',3,620.00,'2026-11-11 04:57:19',NULL,NULL,'Initial seed price','2026-11-11 04:57:19','2026-11-11 04:57:19'),(4,1,'App\\Models\\MenuItem',4,320.00,'2026-11-11 04:57:19',NULL,NULL,'Initial seed price','2026-11-11 04:57:19','2026-11-11 04:57:19'),(5,1,'App\\Models\\MenuItem',5,240.00,'2026-11-11 04:57:19',NULL,NULL,'Initial seed price','2026-11-11 04:57:19','2026-11-11 04:57:19'),(6,1,'App\\Models\\MenuItem',6,140.00,'2026-11-11 04:57:19',NULL,NULL,'Initial seed price','2026-11-11 04:57:19','2026-11-11 04:57:19'),(7,1,'App\\Models\\MenuItem',7,260.00,'2026-11-11 04:57:19',NULL,NULL,'Initial seed price','2026-11-11 04:57:19','2026-11-11 04:57:19'),(8,1,'App\\Models\\MenuItem',8,120.00,'2026-11-11 04:57:19',NULL,NULL,'Initial seed price','2026-11-11 04:57:19','2026-11-11 04:57:19');
/*!40000 ALTER TABLE `price_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity` decimal(13,3) NOT NULL,
  `unit_cost` decimal(13,2) NOT NULL,
  `line_total` decimal(13,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_order_items_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `purchase_order_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `po_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECEIVED',
  `subtotal` decimal(13,2) NOT NULL,
  `tax_amount` decimal(13,2) NOT NULL DEFAULT '0.00',
  `total` decimal(13,2) NOT NULL,
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_by` bigint unsigned DEFAULT NULL,
  `received_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_restaurant_id_po_number_unique` (`restaurant_id`,`po_number`),
  KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_orders_received_by_foreign` (`received_by`),
  KEY `purchase_orders_restaurant_id_received_at_index` (`restaurant_id`,`received_at`),
  CONSTRAINT `purchase_orders_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refunds`
--

DROP TABLE IF EXISTS `refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refunds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `cashier_shift_id` bigint unsigned DEFAULT NULL,
  `refund_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(13,2) NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_by` bigint unsigned DEFAULT NULL,
  `refunded_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `refunds_restaurant_id_refund_number_unique` (`restaurant_id`,`refund_number`),
  KEY `refunds_order_id_foreign` (`order_id`),
  KEY `refunds_cashier_shift_id_foreign` (`cashier_shift_id`),
  KEY `refunds_processed_by_foreign` (`processed_by`),
  KEY `refunds_restaurant_id_refunded_at_index` (`restaurant_id`,`refunded_at`),
  KEY `refunds_invoice_id_index` (`invoice_id`),
  CONSTRAINT `refunds_cashier_shift_id_foreign` FOREIGN KEY (`cashier_shift_id`) REFERENCES `cashier_shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `refunds_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `refunds_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `refunds_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `refunds_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refunds`
--

LOCK TABLES `refunds` WRITE;
/*!40000 ALTER TABLE `refunds` DISABLE KEYS */;
/*!40000 ALTER TABLE `refunds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restaurants`
--

DROP TABLE IF EXISTS `restaurants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Dhaka',
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BDT',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restaurants`
--

LOCK TABLES `restaurants` WRITE;
/*!40000 ALTER TABLE `restaurants` DISABLE KEYS */;
INSERT INTO `restaurants` VALUES (1,'Clique Cafe','Asia/Dhaka','BDT',1,'2026-11-11 02:05:43','2026-11-12 04:22:46');
/*!40000 ALTER TABLE `restaurants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_user`
--

DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_user` (
  `role_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`,`user_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_user`
--

LOCK TABLES `role_user` WRITE;
/*!40000 ALTER TABLE `role_user` DISABLE KEYS */;
INSERT INTO `role_user` VALUES (1,9,'2026-11-14 00:34:06','2026-11-14 00:34:06'),(2,1,'2026-11-11 02:05:46','2026-11-11 02:05:46');
/*!40000 ALTER TABLE `role_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Admin','2026-11-11 02:05:43','2026-11-11 02:05:43'),(2,'manager','Manager','2026-11-11 02:05:44','2026-11-11 02:05:44'),(3,'waiter','Waiter','2026-11-11 02:05:44','2026-11-11 02:05:44'),(4,'cashier','Cashier','2026-11-11 02:05:44','2026-11-11 02:05:44'),(5,'kitchen','Kitchen','2026-11-11 02:05:44','2026-11-11 02:05:44'),(6,'bar','Bar','2026-11-11 02:05:44','2026-11-11 02:05:44');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
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
INSERT INTO `sessions` VALUES ('1zPyLqDlyjZMkJMaDVy02co74PZfbgmGi91YT9GO',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJYelZTTWdwOHRvT290d1hsaHRHTm5VWTNib3dkRTFSdElvbkJUellWIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL3YxXC9vcmRlcnMiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjEsInBhc3N3b3JkX2hhc2hfd2ViIjoiMGMzYmVlOWJkNTlhY2RlZjY5YTFiZDdmN2M0NGRmZjRiMWI2NjA2MGM4ZTI2YmIxZGYzNjEzMDg1OWY5Mzk2YiJ9',1794478380),('4l9mXBOl53RT8eGCJFd6vDkt2AlFmY5f1E1bkwdk',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','eyJfdG9rZW4iOiI3MnVxc3NpZ093S25aVkFadFhmWEVHRU9iMmo0d0ZJdDZDUG9kZmYwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL3YxXC9rb3RzIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxLCJwYXNzd29yZF9oYXNoX3dlYiI6IjBjM2JlZTliZDU5YWNkZWY2OWExYmQ3ZjdjNDRkZmY0YjFiNjYwNjBjOGUyNmJiMWRmMzYxMzA4NTlmOTM5NmIifQ==',1794633274),('817SPTKs9Lbawa7rlz3qxtdEoZ1JjlXsZOrDi6FC',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJEdHdNS3A1NlRkNE91N1FjbFR3U0RqRllZWlNOWFYwOXMxeWJSSThaIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1794633207),('aUZ4lfgAZdeEhQX0BYN4nlMcm1dg50isPeGvTqCa',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJPMENTTlV0Z0ZEcGhUT3NOQ3ZoSHBlU24ybUpzc0VzcWVtbEdpUmZkIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL3YxXC9tZSIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1794633220),('b6ok5mEZ6BekdMvcjyrJfbEYywsoIi4E2YgReJ48',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJWaVBVdTNaczI3VDhWWGJ6T0JzUXZ4d1NnN1duemlrRHROYzBkQkx3IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL3YxXC9tZSIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1794478762),('WCZEfqzost0RZNNAJsFTlczjQv8Pa9FA5KwYpWn5',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJXNFZWUnBjV25FeHNTU3g5a2h4V1k5WjVLQlRxVWU1VTlQb0ZmcFF0IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9hcGlcL3YxXC9tZSIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1794477549),('YCoK9Hp0QloUbOOA97ASfzMFXF5jVKHiXreMbALS',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJlOUJnNFFyUHFQNFlrSE5uTHQwSkNpblVSV1ZmTmUyVEdSVUtMQkJXIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2FwaVwvdjFcL21lIiwicm91dGUiOm51bGx9fQ==',1794484796);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_delta` decimal(13,3) NOT NULL,
  `stock_after` decimal(13,3) NOT NULL,
  `unit_cost` decimal(13,2) DEFAULT NULL,
  `reference_type` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_actor_id_foreign` (`actor_id`),
  KEY `stock_movements_restaurant_id_occurred_at_index` (`restaurant_id`,`occurred_at`),
  KEY `stock_movements_inventory_item_id_occurred_at_index` (`inventory_item_id`,`occurred_at`),
  CONSTRAINT `stock_movements_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_movements_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (1,1,2,'SALE_DEDUCTION',-1.000,34.000,95.00,'App\\Models\\Kot',3,'KOT KOT-0003 · 1x Crispy Chicken Burger',1,'2026-11-12 05:11:26','2026-11-12 05:11:26','2026-11-12 05:11:26'),(2,1,3,'SALE_DEDUCTION',-1.000,59.000,28.00,'App\\Models\\Kot',3,'KOT KOT-0003 · 1x Crispy Chicken Burger',1,'2026-11-12 05:11:26','2026-11-12 05:11:26','2026-11-12 05:11:26');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_restaurant_id_name_unique` (`restaurant_id`,`name`),
  CONSTRAINT `suppliers_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,1,'Dhaka Prime Meats & Poultry','Karim Uddin','01711-000111',NULL,NULL,1,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(2,1,'artisan Bakery & Buns Co.','Nadia Rahman','01819-222333',NULL,NULL,1,'2026-11-12 03:58:05','2026-11-12 03:58:05'),(3,1,'Metro Beverage Distributors','Tanvir Ahmed','01911-444555',NULL,NULL,1,'2026-11-12 03:58:05','2026-11-12 03:58:05');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `table_transfers`
--

DROP TABLE IF EXISTS `table_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `table_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `from_dining_table_id` bigint unsigned NOT NULL,
  `to_dining_table_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transferred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `table_transfers_restaurant_id_foreign` (`restaurant_id`),
  KEY `table_transfers_order_id_foreign` (`order_id`),
  KEY `table_transfers_from_dining_table_id_foreign` (`from_dining_table_id`),
  KEY `table_transfers_to_dining_table_id_foreign` (`to_dining_table_id`),
  KEY `table_transfers_actor_id_foreign` (`actor_id`),
  CONSTRAINT `table_transfers_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `table_transfers_from_dining_table_id_foreign` FOREIGN KEY (`from_dining_table_id`) REFERENCES `dining_tables` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `table_transfers_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `table_transfers_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `table_transfers_to_dining_table_id_foreign` FOREIGN KEY (`to_dining_table_id`) REFERENCES `dining_tables` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `table_transfers`
--

LOCK TABLES `table_transfers` WRITE;
/*!40000 ALTER TABLE `table_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `table_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_categories`
--

DROP TABLE IF EXISTS `tax_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(6,3) NOT NULL DEFAULT '0.000',
  `inclusive` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `effective_from` timestamp NULL DEFAULT NULL,
  `effective_to` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_categories_restaurant_id_name_unique` (`restaurant_id`,`name`),
  CONSTRAINT `tax_categories_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_categories`
--

LOCK TABLES `tax_categories` WRITE;
/*!40000 ALTER TABLE `tax_categories` DISABLE KEYS */;
INSERT INTO `tax_categories` VALUES (1,1,'Standard VAT 5%',5.000,0,1,'2026-11-11 04:57:18',NULL,'2026-11-11 04:57:18','2026-11-11 04:57:18'),(2,1,'VAT Included in Food Price (5%)',5.000,1,1,'2026-11-12 05:23:14',NULL,'2026-11-12 05:23:14','2026-11-12 05:23:14');
/*!40000 ALTER TABLE `tax_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_restaurant_id_active_index` (`restaurant_id`,`active`),
  CONSTRAINT `users_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'Local Manager','manager@local.test',NULL,'$2y$12$3Fnz2RRqg7eMJR5WgtBezOQ/Al/NlHj4ND4.pLmS0pH4Z8z9BS2ju',1,NULL,'2026-11-11 02:05:46','2026-11-11 06:28:14'),(9,1,'Clique Cafe Admin','admin@cliquecafe.com',NULL,'$2y$12$jr8Ca7GC7WJ0qJF8ItE7UeWOzTgFqoJ8OTXqlLPneEP3hh66slLsi',1,NULL,'2026-11-14 00:34:05','2026-11-14 00:34:05');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zones`
--

DROP TABLE IF EXISTS `zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `restaurant_id` bigint unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zones_restaurant_id_name_unique` (`restaurant_id`,`name`),
  KEY `zones_restaurant_id_active_sort_order_index` (`restaurant_id`,`active`,`sort_order`),
  CONSTRAINT `zones_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zones`
--

LOCK TABLES `zones` WRITE;
/*!40000 ALTER TABLE `zones` DISABLE KEYS */;
INSERT INTO `zones` VALUES (1,1,'Indoor',1,1,'2026-11-11 02:05:44','2026-11-11 02:05:44'),(2,1,'Outdoor',2,1,'2026-11-11 02:05:44','2026-11-11 02:05:44');
/*!40000 ALTER TABLE `zones` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-14 12:36:38


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
DROP TABLE IF EXISTS `ai_action_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_action_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `instruction` text NOT NULL,
  `response_json` longtext DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'previewed',
  `applied_summary` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_action_user` (`user_id`),
  KEY `idx_ai_action_status` (`status`),
  CONSTRAINT `fk_ai_action_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_number` varchar(50) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_action` varchar(100) NOT NULL,
  `event_result` varchar(50) NOT NULL DEFAULT 'success',
  `severity` varchar(50) NOT NULL DEFAULT 'medium',
  `actor_type` varchar(50) NOT NULL DEFAULT 'user',
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `related_type` varchar(100) NOT NULL,
  `related_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `contract_id` bigint(20) unsigned DEFAULT NULL,
  `installment_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_method` varchar(20) DEFAULT NULL,
  `request_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_audit_logs_audit_number` (`audit_number`),
  KEY `idx_audit_logs_event_type` (`event_type`),
  KEY `idx_audit_logs_event_action` (`event_action`),
  KEY `idx_audit_logs_related` (`related_type`,`related_id`),
  KEY `idx_audit_logs_contract_id` (`contract_id`),
  KEY `idx_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(40) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chat_attachments_message` (`message_id`),
  KEY `idx_chat_attachments_status` (`status`),
  KEY `fk_chat_attachment_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_chat_attachment_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_attachment_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_channels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'public',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_channels_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_change_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_change_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `change_type` varchar(80) NOT NULL,
  `old_value_json` longtext DEFAULT NULL,
  `new_value_json` longtext DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contract_change_logs_contract` (`contract_id`),
  CONSTRAINT `fk_contract_change_logs_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_guarantees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_guarantees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `guarantee_type` varchar(50) NOT NULL,
  `guarantee_count` int(11) NOT NULL DEFAULT 1,
  `guarantee_serial` varchar(190) DEFAULT NULL,
  `guarantee_description` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contract_guarantees_contract` (`contract_id`),
  CONSTRAINT `fk_contract_guarantees_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_guarantor_people`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_guarantor_people` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `full_name` varchar(190) NOT NULL,
  `father_name` varchar(190) DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `relationship` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contract_guarantor_people_contract` (`contract_id`),
  CONSTRAINT `fk_contract_guarantor_people_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_guarantors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_guarantors` (
  `contract_id` bigint(20) unsigned NOT NULL,
  `guarantor_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`contract_id`,`guarantor_id`),
  KEY `fk_guarantor_user` (`guarantor_id`),
  CONSTRAINT `fk_guarantor_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_guarantor_user` FOREIGN KEY (`guarantor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `product_model` varchar(190) NOT NULL,
  `imei_1` varchar(80) DEFAULT NULL,
  `imei_2` varchar(80) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contract_items_contract` (`contract_id`),
  CONSTRAINT `fk_contract_items_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned NOT NULL,
  `contract_number` varchar(80) NOT NULL,
  `prefix` varchar(30) NOT NULL,
  `serial` bigint(20) unsigned NOT NULL,
  `principal_amount` decimal(18,2) NOT NULL,
  `down_payment_amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `monthly_interest_rate` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `interest_type` varchar(30) NOT NULL,
  `months` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `first_due_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `assigned_operator_id` bigint(20) unsigned DEFAULT NULL,
  `legal_status` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_number` (`contract_number`),
  UNIQUE KEY `uq_prefix_serial` (`prefix`,`serial`),
  KEY `idx_contract_customer` (`customer_id`),
  KEY `idx_contract_operator` (`assigned_operator_id`),
  CONSTRAINT `fk_contract_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contract_operator` FOREIGN KEY (`assigned_operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_merge_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_merge_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `keep_customer_id` bigint(20) unsigned NOT NULL,
  `merged_customer_id` bigint(20) unsigned NOT NULL,
  `merged_by` bigint(20) unsigned DEFAULT NULL,
  `snapshot_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer_merge_keep` (`keep_customer_id`),
  KEY `idx_customer_merge_merged` (`merged_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `event_type` varchar(40) NOT NULL DEFAULT 'general',
  `description` text DEFAULT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'primary',
  `reminder_type` varchar(40) DEFAULT NULL,
  `reminder_at` datetime DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `due_day_sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_date` (`event_date`),
  KEY `idx_events_assigned` (`assigned_user_id`),
  KEY `idx_events_reminder` (`reminder_at`,`reminder_sent_at`),
  KEY `fk_event_user` (`user_id`),
  CONSTRAINT `fk_event_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `generated_contract_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generated_contract_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `rendered_title` varchar(190) DEFAULT NULL,
  `rendered_header` text DEFAULT NULL,
  `rendered_body` longtext NOT NULL,
  `generated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_generated_contract` (`contract_id`),
  CONSTRAINT `fk_generated_contract_documents_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `identity_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `identity_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `document_type` varchar(40) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `uploaded_at` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identity_documents_user` (`user_id`),
  KEY `idx_identity_documents_status` (`status`),
  KEY `fk_identity_documents_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_identity_documents_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_identity_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `filename` varchar(255) NOT NULL,
  `status` varchar(40) NOT NULL,
  `raw_path` varchar(255) DEFAULT NULL,
  `parsed_json` longtext DEFAULT NULL,
  `error_summary` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_import_batch_user` (`user_id`),
  CONSTRAINT `fk_import_batch_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint(20) unsigned NOT NULL,
  `row_index` int(11) NOT NULL,
  `raw_json` longtext NOT NULL,
  `parsed_json` longtext DEFAULT NULL,
  `status` varchar(40) NOT NULL,
  `errors` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_import_row_batch` (`batch_id`),
  CONSTRAINT `fk_import_row_batch` FOREIGN KEY (`batch_id`) REFERENCES `import_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `installments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `installments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `installment_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `base_amount` decimal(18,2) NOT NULL,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `last_payment_date` date DEFAULT NULL,
  `manual_penalty_adjustment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `manual_reward_adjustment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `penalty_discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `guarantee_serial` varchar(190) DEFAULT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `custom_title` varchar(190) DEFAULT NULL,
  `custom_description` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_installment` (`contract_id`,`installment_number`),
  KEY `idx_due_status` (`due_date`,`status`),
  CONSTRAINT `fk_installment_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legal_case_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `legal_case_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `legal_case_id` bigint(20) unsigned DEFAULT NULL,
  `action_stage` varchar(80) NOT NULL,
  `action_title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `action_date` date NOT NULL,
  `action_time` time DEFAULT NULL,
  `registered_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_lawyer_id` bigint(20) unsigned DEFAULT NULL,
  `next_status` varchar(80) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `cost_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cost_type` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_legal_case_logs_contract` (`contract_id`),
  KEY `idx_legal_case_logs_case` (`legal_case_id`),
  KEY `idx_legal_case_logs_stage` (`action_stage`),
  KEY `idx_legal_case_logs_date` (`action_date`),
  KEY `fk_legal_case_logs_registered_by` (`registered_by`),
  KEY `fk_legal_case_logs_lawyer` (`assigned_lawyer_id`),
  CONSTRAINT `fk_legal_case_logs_case` FOREIGN KEY (`legal_case_id`) REFERENCES `legal_cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_legal_case_logs_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_legal_case_logs_lawyer` FOREIGN KEY (`assigned_lawyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_legal_case_logs_registered_by` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legal_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `legal_cases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lawyer_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `contract_id` bigint(20) unsigned NOT NULL,
  `status` varchar(30) NOT NULL,
  `stage` varchar(190) NOT NULL,
  `complaint_number` varchar(100) DEFAULT NULL,
  `notice_date` date DEFAULT NULL,
  `court_date` date DEFAULT NULL,
  `hearing_date` date DEFAULT NULL,
  `expense_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `expense_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_legal_lawyer` (`lawyer_id`),
  KEY `fk_legal_customer` (`customer_id`),
  KEY `fk_legal_contract` (`contract_id`),
  CONSTRAINT `fk_legal_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_legal_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_legal_lawyer` FOREIGN KEY (`lawyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `code` varchar(80) DEFAULT NULL,
  `icon_key` varchar(40) DEFAULT 'award',
  `source` varchar(40) NOT NULL DEFAULT 'manual',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medals_user_code` (`user_id`,`code`),
  CONSTRAINT `fk_medal_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) unsigned NOT NULL,
  `receiver_id` bigint(20) unsigned DEFAULT NULL,
  `channel_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `target_unit` varchar(80) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_message_pair` (`sender_id`,`receiver_id`,`id`),
  KEY `idx_message_unread` (`receiver_id`,`is_read`),
  KEY `idx_message_channel` (`channel_id`,`id`),
  CONSTRAINT `fk_message_channel` FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(190) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(40) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notification_user` (`user_id`,`is_read`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operator_calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `operator_calls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `operator_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `contract_id` bigint(20) unsigned NOT NULL,
  `installment_id` bigint(20) unsigned DEFAULT NULL,
  `call_result` varchar(190) NOT NULL,
  `notes` text DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `promise_payment_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_operator_calls_operator` (`operator_id`),
  KEY `fk_call_customer` (`customer_id`),
  KEY `fk_call_contract` (`contract_id`),
  KEY `fk_call_installment` (`installment_id`),
  CONSTRAINT `fk_call_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_call_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_call_installment` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_call_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `mobile` varchar(30) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_user` (`user_id`,`used_at`,`expires_at`),
  KEY `idx_password_resets_expiry` (`expires_at`,`used_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_corrections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned NOT NULL,
  `installment_id` bigint(20) unsigned DEFAULT NULL,
  `contract_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `reason` text NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `corrected_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_correction` (`payment_id`),
  KEY `idx_payment_correction_installment` (`installment_id`),
  KEY `fk_correction_admin` (`corrected_by`),
  CONSTRAINT `fk_correction_admin` FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_correction_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned NOT NULL,
  `installment_id` bigint(20) unsigned NOT NULL,
  `contract_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `receipt_path` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `review_note` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_receipts_status` (`status`),
  KEY `idx_payment_receipts_customer` (`customer_id`),
  KEY `fk_payment_receipts_payment` (`payment_id`),
  KEY `fk_payment_receipts_installment` (`installment_id`),
  KEY `fk_payment_receipts_contract` (`contract_id`),
  KEY `fk_payment_receipts_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_payment_receipts_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_receipts_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_receipts_installment` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_receipts_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_receipts_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `installment_id` bigint(20) unsigned DEFAULT NULL,
  `contract_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `method` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL,
  `gateway_track_id` varchar(100) DEFAULT NULL,
  `gateway_ref_id` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `calculated_penalty` decimal(18,2) NOT NULL DEFAULT 0.00,
  `calculated_reward` decimal(18,2) NOT NULL DEFAULT 0.00,
  `remaining_before_payment` decimal(18,2) DEFAULT NULL,
  `remaining_after_payment` decimal(18,2) DEFAULT NULL,
  `payment_type` varchar(40) NOT NULL DEFAULT 'installment',
  `is_corrected` tinyint(1) NOT NULL DEFAULT 0,
  `correction_reason` text DEFAULT NULL,
  `corrected_at` datetime DEFAULT NULL,
  `corrected_by` bigint(20) unsigned DEFAULT NULL,
  `correction_snapshot_json` longtext DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gateway_track` (`gateway_track_id`),
  KEY `idx_payment_paid_at` (`paid_at`),
  KEY `idx_payment_contract` (`contract_id`),
  KEY `fk_payment_installment` (`installment_id`),
  KEY `fk_payment_user` (`user_id`),
  CONSTRAINT `fk_payment_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_installment` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `penalties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `penalties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `installment_id` bigint(20) unsigned NOT NULL,
  `type` varchar(30) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `percent` decimal(8,4) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_penalty_installment` (`installment_id`),
  KEY `fk_penalty_user` (`created_by`),
  CONSTRAINT `fk_penalty_installment` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_penalty_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `profile_update_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profile_update_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `payload_json` longtext NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_profile_request_status` (`status`),
  KEY `idx_profile_request_user` (`user_id`),
  KEY `fk_profile_request_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_profile_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_profile_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_plugin_logs`;
DROP TABLE IF EXISTS `system_plugin_role_permissions`;
DROP TABLE IF EXISTS `system_plugin_permissions`;
DROP TABLE IF EXISTS `system_plugin_migrations`;
DROP TABLE IF EXISTS `system_plugins`;
CREATE TABLE `system_plugins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` varchar(100) NOT NULL,
  `name` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `version` varchar(40) NOT NULL,
  `path` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'discovered',
  `installed_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `installed_by` bigint(20) unsigned DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `manifest_json` longtext DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_plugins_plugin_id` (`plugin_id`),
  KEY `idx_system_plugins_status` (`status`),
  KEY `idx_system_plugins_installed_by` (`installed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `system_plugin_migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` varchar(100) NOT NULL,
  `migration_name` varchar(190) NOT NULL,
  `batch` int(10) unsigned NOT NULL DEFAULT 1,
  `checksum` char(64) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'running',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_plugin_migration` (`plugin_id`,`migration_name`),
  KEY `idx_system_plugin_migrations_status` (`status`),
  KEY `idx_system_plugin_migrations_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `system_plugin_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` varchar(100) NOT NULL,
  `permission_key` varchar(190) NOT NULL,
  `label` varchar(190) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_plugin_permission` (`plugin_id`,`permission_key`),
  KEY `idx_system_plugin_permissions_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `system_plugin_role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` varchar(100) NOT NULL,
  `permission_key` varchar(190) NOT NULL,
  `role` varchar(40) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `granted_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_plugin_role_permission` (`plugin_id`,`permission_key`,`role`),
  KEY `idx_system_plugin_role_permissions_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `system_plugin_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` varchar(100) NOT NULL,
  `log_level` varchar(20) NOT NULL,
  `log_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `context_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_system_plugin_logs_plugin` (`plugin_id`),
  KEY `idx_system_plugin_logs_level` (`log_level`),
  KEY `idx_system_plugin_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `system_plugin_permissions` (`plugin_id`,`permission_key`,`label`,`is_active`,`created_at`) VALUES
('core','view_plugins','مشاهده پلاگین‌ها',1,NOW()),
('core','manage_plugins','مدیریت پلاگین‌ها',1,NOW()),
('core','install_plugins','نصب پلاگین‌ها',1,NOW()),
('core','activate_plugins','فعال‌سازی پلاگین‌ها',1,NOW()),
('core','deactivate_plugins','غیرفعال‌سازی پلاگین‌ها',1,NOW()),
('core','update_plugins','بروزرسانی پلاگین‌ها',1,NOW()),
('core','uninstall_plugins','حذف پلاگین‌ها',1,NOW()),
('core','purge_plugin_data','حذف کامل داده پلاگین',1,NOW());
DROP TABLE IF EXISTS `user_medal_history`;
DROP TABLE IF EXISTS `user_medals`;
DROP TABLE IF EXISTS `medal_definitions`;
DROP TABLE IF EXISTS `installment_bulk_operations`;
DROP TABLE IF EXISTS `payment_allocations`;
DROP TABLE IF EXISTS `payment_groups`;
DROP TABLE IF EXISTS `contract_document_versions`;
DROP TABLE IF EXISTS `contract_deletion_archives`;
CREATE TABLE `contract_deletion_archives` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `contract_id` bigint(20) unsigned NOT NULL, `contract_number` varchar(80) NOT NULL, `customer_id` bigint(20) unsigned NOT NULL, `deletion_reason` text NOT NULL, `gateway_warning` text DEFAULT NULL, `corrected_payment_count` int(10) unsigned NOT NULL DEFAULT 0, `snapshot_json` longtext NOT NULL, `deleted_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_contract_deletion_archive` (`contract_id`,`created_at`), KEY `idx_contract_deletion_archives_customer` (`customer_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `contract_document_versions` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `contract_id` bigint(20) unsigned NOT NULL, `version_number` int(10) unsigned NOT NULL, `rendered_title` varchar(190) DEFAULT NULL, `rendered_header` text DEFAULT NULL, `rendered_body` longtext NOT NULL, `source` varchar(30) NOT NULL DEFAULT 'generated', `checksum` char(64) NOT NULL, `is_published` tinyint(1) NOT NULL DEFAULT 1, `is_finalized` tinyint(1) NOT NULL DEFAULT 0, `generated_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_contract_document_version` (`contract_id`,`version_number`), KEY `idx_contract_document_versions_published` (`contract_id`,`is_published`,`version_number`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `payment_groups` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `group_number` varchar(80) NOT NULL, `contract_id` bigint(20) unsigned NOT NULL, `customer_id` bigint(20) unsigned NOT NULL, `created_by` bigint(20) unsigned DEFAULT NULL, `requested_amount` decimal(18,2) NOT NULL, `allocated_amount` decimal(18,2) NOT NULL DEFAULT 0.00, `method` varchar(30) NOT NULL DEFAULT 'manual', `status` varchar(30) NOT NULL DEFAULT 'paid', `gateway_track_id` varchar(100) DEFAULT NULL, `idempotency_key` varchar(120) DEFAULT NULL, `description` text DEFAULT NULL, `created_at` datetime NOT NULL, `completed_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_payment_groups_number` (`group_number`), UNIQUE KEY `uq_payment_groups_idempotency` (`idempotency_key`), KEY `idx_payment_groups_contract` (`contract_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `payment_allocations` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `payment_group_id` bigint(20) unsigned NOT NULL, `payment_id` bigint(20) unsigned NOT NULL, `contract_id` bigint(20) unsigned NOT NULL, `installment_id` bigint(20) unsigned NOT NULL, `allocated_amount` decimal(18,2) NOT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_payment_allocation_installment` (`payment_group_id`,`installment_id`), KEY `idx_payment_allocations_payment` (`payment_id`), CONSTRAINT `fk_payment_allocation_group` FOREIGN KEY (`payment_group_id`) REFERENCES `payment_groups` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `installment_bulk_operations` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `operation_number` varchar(80) NOT NULL, `contract_id` bigint(20) unsigned NOT NULL, `operation_type` varchar(40) NOT NULL, `installment_ids_json` longtext NOT NULL, `old_snapshot_json` longtext DEFAULT NULL, `new_snapshot_json` longtext DEFAULT NULL, `reason` text NOT NULL, `performed_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_installment_bulk_operation_number` (`operation_number`), KEY `idx_installment_bulk_operations_contract` (`contract_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `medal_definitions` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `slug` varchar(100) NOT NULL, `title` varchar(190) NOT NULL, `short_description` varchar(255) DEFAULT NULL, `full_description` text DEFAULT NULL, `how_to_earn` text DEFAULT NULL, `icon_key` varchar(50) NOT NULL DEFAULT 'award', `icon_path` varchar(255) DEFAULT NULL, `color` varchar(20) NOT NULL DEFAULT '#f59e0b', `category` varchar(30) NOT NULL DEFAULT 'activity', `points` int(11) NOT NULL DEFAULT 0, `award_type` varchar(30) NOT NULL DEFAULT 'automatic', `criteria_type` varchar(50) DEFAULT NULL, `criteria_json` longtext DEFAULT NULL, `is_repeatable` tinyint(1) NOT NULL DEFAULT 0, `maximum_awards` int(10) unsigned DEFAULT NULL, `is_active` tinyint(1) NOT NULL DEFAULT 1, `sort_order` int(11) NOT NULL DEFAULT 0, `created_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, `updated_at` datetime DEFAULT NULL, `archived_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_medal_definition_slug` (`slug`), KEY `idx_medal_definitions_active_sort` (`is_active`,`sort_order`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `user_medals` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `user_id` bigint(20) unsigned NOT NULL, `medal_definition_id` bigint(20) unsigned NOT NULL, `source` varchar(30) NOT NULL DEFAULT 'automatic', `note` text DEFAULT NULL, `related_contract_id` bigint(20) unsigned DEFAULT NULL, `related_payment_id` bigint(20) unsigned DEFAULT NULL, `awarded_by` bigint(20) unsigned DEFAULT NULL, `awarded_at` datetime NOT NULL, `revoked_at` datetime DEFAULT NULL, `revoked_by` bigint(20) unsigned DEFAULT NULL, `revoke_reason` text DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `idx_user_medals_user_active` (`user_id`,`revoked_at`), KEY `idx_user_medals_definition` (`medal_definition_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `user_medal_history` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `user_medal_id` bigint(20) unsigned NOT NULL, `action` varchar(30) NOT NULL, `reason` text DEFAULT NULL, `performed_by` bigint(20) unsigned DEFAULT NULL, `snapshot_json` longtext DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `idx_user_medal_history_medal` (`user_medal_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `medal_definitions` (`slug`,`title`,`short_description`,`icon_key`,`color`,`category`,`points`,`criteria_type`,`criteria_json`,`sort_order`,`created_at`) VALUES
('first-contract','اولین قرارداد','اولین قرارداد اقساطی شما','file-text','#2563eb','contract',10,'contract_count','{"minimum":1}',10,NOW()),
('first-payment','اولین پرداخت موفق','اولین پرداخت موفق ثبت شد','check-circle','#16a34a','payment',15,'payment_count','{"minimum":1}',20,NOW()),
('on-time-payment','پرداخت به‌موقع','پرداخت در موعد انجام شد','clock','#0f766e','early_payment',20,'on_time_count','{"minimum":1}',30,NOW()),
('five-on-time-payments','۵ پرداخت به‌موقع','پنج پرداخت خوش‌حسابانه','award','#d97706','early_payment',50,'on_time_count','{"minimum":5}',40,NOW()),
('early-payment','پرداخت زودهنگام','پرداخت پیش از سررسید','zap','#0891b2','early_payment',25,'early_payment_count','{"minimum":1}',35,NOW()),
('five-early-payments','۵ قسط زودتر از موعد','پنج پرداخت زودهنگام','trending-up','#0284c7','early_payment',55,'early_payment_count','{"minimum":5}',45,NOW()),
('ten-on-time-payments','۱۰ پرداخت به‌موقع','ده پرداخت خوش‌حسابانه','star','#ca8a04','early_payment',90,'on_time_count','{"minimum":10}',47,NOW()),
('first-settlement','تسویه اولین قرارداد','اولین قرارداد تسویه شد','shield-check','#7c3aed','settlement',60,'completed_contract_count','{"minimum":1}',50,NOW()),
('early-settlement','تسویه زودهنگام قرارداد','تسویه پیش از موعد','fast-forward','#9333ea','settlement',90,'early_settlement_count','{"minimum":1}',55,NOW()),
('three-successful-contracts','۳ قرارداد موفق','سه قرارداد غیرلغوشده','layers','#4f46e5','contract',45,'contract_count','{"minimum":3}',58,NOW()),
('ten-successful-contracts','۱۰ قرارداد موفق','ده قرارداد غیرلغوشده','briefcase','#3730a3','contract',120,'contract_count','{"minimum":10}',59,NOW()),
('loyal-customer','مشتری وفادار','پنج قرارداد موفق','heart','#db2777','loyalty',80,'contract_count','{"minimum":5}',60,NOW()),
('no-overdue','بدون معوقه','اقساط معوق ندارید','shield','#16a34a','activity',35,'overdue_count','{"maximum":0}',65,NOW()),
('special-customer','مشتری ویژه','امتیاز ویژه مشتری','crown','#be123c','special',150,NULL,'{}',70,NOW());
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(120) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `is_secret` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role` varchar(30) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `full_name` varchar(190) NOT NULL,
  `father_name` varchar(190) DEFAULT NULL,
  `issued_from` varchar(190) DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `secondary_phone` varchar(30) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `avatar_key` varchar(40) DEFAULT NULL,
  `avatar_category` varchar(40) DEFAULT NULL,
  `avatar_source` varchar(30) NOT NULL DEFAULT 'fallback',
  `avatar_locked` tinyint(1) NOT NULL DEFAULT 0,
  `avatar_suggestion_reason` varchar(255) DEFAULT NULL,
  `department` varchar(40) DEFAULT NULL,
  `is_department_manager` tinyint(1) NOT NULL DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `tour_completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_national_id` (`national_id`),
  UNIQUE KEY `uq_users_mobile` (`mobile`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


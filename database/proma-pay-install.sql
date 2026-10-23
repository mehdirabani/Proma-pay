
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
DROP TABLE IF EXISTS `chat_channel_reads`;
CREATE TABLE `chat_channel_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `last_read_message_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_channel_read_user` (`channel_id`,`user_id`),
  KEY `idx_chat_channel_reads_user` (`user_id`,`last_read_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `guarantee_amount` decimal(18,2) NOT NULL DEFAULT 0,
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
  `previous_status` varchar(30) DEFAULT NULL,
  `cancellation_metadata_json` longtext DEFAULT NULL,
  `assigned_operator_id` bigint(20) unsigned DEFAULT NULL,
  `seller_user_id` bigint(20) unsigned DEFAULT NULL,
  `marketer_user_id` bigint(20) unsigned DEFAULT NULL,
  `legal_status` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_number` (`contract_number`),
  UNIQUE KEY `uq_prefix_serial` (`prefix`,`serial`),
  KEY `idx_contract_customer` (`customer_id`),
  KEY `idx_contract_operator` (`assigned_operator_id`),
  KEY `idx_contract_seller` (`seller_user_id`),
  KEY `idx_contract_marketer` (`marketer_user_id`),
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
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `event_type` varchar(40) NOT NULL DEFAULT 'general',
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
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
  `template_version_id` bigint(20) unsigned DEFAULT NULL,
  `template_status` varchar(30) NOT NULL DEFAULT 'legacy',
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
DROP TABLE IF EXISTS `file_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` bigint(20) unsigned NOT NULL,
  `action` varchar(80) NOT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_file_audit_file` (`file_id`,`created_at`),
  KEY `idx_file_audit_actor` (`actor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `file_relations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_relations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` bigint(20) unsigned NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `relation_type` varchar(60) NOT NULL DEFAULT 'attachment',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_file_relation` (`file_id`,`entity_type`,`entity_id`,`relation_type`),
  KEY `idx_file_relations_entity` (`entity_type`,`entity_id`),
  KEY `idx_file_relations_file` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_uuid` char(32) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `display_name` varchar(190) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `mime_type` varchar(190) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `storage_disk` varchar(30) NOT NULL DEFAULT 'private',
  `storage_path` varchar(500) NOT NULL,
  `checksum_sha256` char(64) DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `visibility` varchar(20) NOT NULL DEFAULT 'private',
  `uploader_user_id` bigint(20) unsigned DEFAULT NULL,
  `uploader_role` varchar(40) DEFAULT NULL,
  `related_entity_type` varchar(60) DEFAULT NULL,
  `related_entity_id` bigint(20) unsigned DEFAULT NULL,
  `parent_file_id` bigint(20) unsigned DEFAULT NULL,
  `version_number` int(10) unsigned NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `tags_json` longtext DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) unsigned DEFAULT NULL,
  `deletion_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_files_uuid` (`file_uuid`),
  UNIQUE KEY `uq_files_storage_path` (`storage_path`),
  KEY `idx_files_status_created` (`status`,`created_at`),
  KEY `idx_files_category` (`category`),
  KEY `idx_files_uploader` (`uploader_user_id`),
  KEY `idx_files_related` (`related_entity_type`,`related_entity_id`),
  KEY `idx_files_parent` (`parent_file_id`)
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
  `effective_settlement_at` datetime DEFAULT NULL,
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
  `internal_note` text DEFAULT NULL,
  `customer_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_reason` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_installment` (`contract_id`,`installment_number`),
  KEY `idx_due_status` (`due_date`,`status`),
    KEY `idx_installments_due_status_contract` (`due_date`,`status`,`contract_id`),
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
  `request_uuid` varchar(64) DEFAULT NULL,
  `legal_referred_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_legal_lawyer` (`lawyer_id`),
  KEY `fk_legal_customer` (`customer_id`),
  KEY `fk_legal_contract` (`contract_id`),
    KEY `idx_legal_customer_updated_id` (`customer_id`,`updated_at`,`id`),
  KEY `idx_legal_contract_status` (`contract_id`,`status`),
  UNIQUE KEY `uq_legal_case_request_uuid` (`request_uuid`),
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
  KEY `idx_message_reverse_pair` (`receiver_id`,`sender_id`,`id`),
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
  `delivered_at` datetime DEFAULT NULL,
  `seen_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `actioned_at` datetime DEFAULT NULL,
  `dedupe_key` varchar(190) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_recipient_dedupe` (`user_id`,`dedupe_key`),
  KEY `idx_notification_user` (`user_id`,`is_read`),
  KEY `idx_notification_active_user` (`user_id`,`deleted_at`,`archived_at`,`id`),
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
DROP TABLE IF EXISTS `payment_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_uuid` varchar(64) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `installment_id` bigint(20) unsigned DEFAULT NULL,
  `contract_id` bigint(20) unsigned DEFAULT NULL,
  `quote_uuid` varchar(64) DEFAULT NULL,
  `selection_json` longtext DEFAULT NULL,
  `request_hash` char(64) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'processing',
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `payment_group_id` bigint(20) unsigned DEFAULT NULL,
  `response_code` varchar(60) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_requests_uuid` (`request_uuid`),
  KEY `idx_payment_requests_installment` (`installment_id`),
  KEY `idx_payment_requests_payment` (`payment_id`),
  KEY `idx_payment_requests_group` (`payment_group_id`),
  KEY `idx_payment_requests_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_outbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(80) NOT NULL,
  `aggregate_type` varchar(80) DEFAULT NULL,
  `aggregate_id` bigint(20) unsigned DEFAULT NULL,
  `payload_json` longtext NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 5,
  `last_error` text DEFAULT NULL,
  `next_attempt_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_system_outbox_status` (`status`,`next_attempt_at`,`id`),
  KEY `idx_system_outbox_aggregate` (`aggregate_type`,`aggregate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
  DROP TABLE IF EXISTS `customer_automation_events`;
  /*!40101 SET @saved_cs_client     = @@character_set_client */;
  /*!40101 SET character_set_client = utf8 */;
  CREATE TABLE `customer_automation_events` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `event_key` varchar(190) NOT NULL,
    `event_type` varchar(60) NOT NULL,
    `customer_id` bigint(20) unsigned NOT NULL,
    `contract_id` bigint(20) unsigned NOT NULL,
    `installment_id` bigint(20) unsigned NOT NULL,
    `event_id` bigint(20) unsigned DEFAULT NULL,
    `payload_json` longtext NOT NULL,
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customer_automation_event_key` (`event_key`),
    KEY `idx_customer_automation_customer_date` (`customer_id`,`created_at`,`id`),
    KEY `idx_customer_automation_installment` (`installment_id`,`event_type`)
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
  `payment_group_id` bigint(20) unsigned DEFAULT NULL,
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
  `principal_applied` decimal(18,2) DEFAULT NULL,
  `normal_penalty_applied` decimal(18,2) DEFAULT NULL,
  `legal_penalty_applied` decimal(18,2) DEFAULT NULL,
  `reward_applied` decimal(18,2) DEFAULT NULL,
  `remaining_before_payment` decimal(18,2) DEFAULT NULL,
  `remaining_after_payment` decimal(18,2) DEFAULT NULL,
  `settlement_quote_uuid` varchar(64) DEFAULT NULL,
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
  KEY `idx_payment_contract_status` (`contract_id`,`status`,`is_corrected`,`id`),
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
  `reviewed_fields_json` longtext DEFAULT NULL,
  `rejected_fields_json` longtext DEFAULT NULL,
  `current_snapshot_json` longtext DEFAULT NULL,
  `customer_response` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_profile_request_status` (`status`),
  KEY `idx_profile_request_status_created` (`status`,`created_at`,`id`),
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
DROP TABLE IF EXISTS `settlement_quotes`;
DROP TABLE IF EXISTS `payment_allocations`;
DROP TABLE IF EXISTS `payment_groups`;
DROP TABLE IF EXISTS `contract_document_versions`;
DROP TABLE IF EXISTS `contract_deletion_archives`;
CREATE TABLE `contract_deletion_archives` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `contract_id` bigint(20) unsigned NOT NULL, `contract_number` varchar(80) NOT NULL, `customer_id` bigint(20) unsigned NOT NULL, `deletion_reason` text NOT NULL, `gateway_warning` text DEFAULT NULL, `corrected_payment_count` int(10) unsigned NOT NULL DEFAULT 0, `snapshot_json` longtext NOT NULL, `deleted_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_contract_deletion_archive` (`contract_id`,`created_at`), KEY `idx_contract_deletion_archives_customer` (`customer_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `contract_duplicate_repairs`;
CREATE TABLE `contract_duplicate_repairs` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `canonical_contract_id` bigint(20) unsigned NOT NULL, `duplicate_contract_id` bigint(20) unsigned NOT NULL, `action` varchar(40) NOT NULL, `confidence` varchar(20) NOT NULL, `snapshot_json` longtext DEFAULT NULL, `repaired_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_contract_duplicate_repair` (`duplicate_contract_id`), KEY `idx_contract_duplicate_canonical` (`canonical_contract_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `backup_logs` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `user_id` bigint(20) unsigned DEFAULT NULL, `action` varchar(40) NOT NULL, `file_name` varchar(255) DEFAULT NULL, `status` varchar(40) NOT NULL, `message` text DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `idx_backup_logs_user` (`user_id`), KEY `idx_backup_logs_action` (`action`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `contract_templates` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(190) NOT NULL, `description` text DEFAULT NULL, `status` varchar(30) NOT NULL DEFAULT 'active', `current_version_id` bigint(20) unsigned DEFAULT NULL, `created_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `idx_contract_templates_status` (`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `contract_template_versions` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `template_id` bigint(20) unsigned NOT NULL, `version_number` int(10) unsigned NOT NULL, `body_source` longtext NOT NULL, `body_format` varchar(30) NOT NULL DEFAULT 'plain_text_v1', `content_hash` char(64) NOT NULL, `change_reason` text DEFAULT NULL, `status` varchar(30) NOT NULL DEFAULT 'draft', `created_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, `published_by` bigint(20) unsigned DEFAULT NULL, `published_at` datetime DEFAULT NULL, `superseded_at` datetime DEFAULT NULL, `archived_at` datetime DEFAULT NULL, `archived_by` bigint(20) unsigned DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_contract_template_version` (`template_id`,`version_number`), KEY `idx_contract_template_versions_status` (`template_id`,`status`,`version_number`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `contract_template_audit_logs` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `action` varchar(80) NOT NULL, `actor_id` bigint(20) unsigned DEFAULT NULL, `template_id` bigint(20) unsigned DEFAULT NULL, `version_id` bigint(20) unsigned DEFAULT NULL, `old_values_json` longtext DEFAULT NULL, `new_values_json` longtext DEFAULT NULL, `reason` text DEFAULT NULL, `ip_address` varchar(45) DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `idx_contract_template_audit_created` (`created_at`), KEY `idx_contract_template_audit_template` (`template_id`,`version_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `contract_document_rebuild_jobs` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `target_template_version_id` bigint(20) unsigned NOT NULL, `status` varchar(30) NOT NULL DEFAULT 'pending', `total_documents` int(10) unsigned NOT NULL DEFAULT 0, `processed_documents` int(10) unsigned NOT NULL DEFAULT 0, `failed_documents` int(10) unsigned NOT NULL DEFAULT 0, `last_contract_id` bigint(20) unsigned NOT NULL DEFAULT 0, `failure_report_json` longtext DEFAULT NULL, `created_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, `started_at` datetime DEFAULT NULL, `completed_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `idx_contract_rebuild_status` (`status`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `contract_templates` (`id`,`name`,`description`,`status`,`created_at`) VALUES (1,'قالب اصلی قرارداد','قالب پیش فرض اسناد قرارداد','active',NOW());
CREATE TABLE `contract_document_versions` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `contract_id` bigint(20) unsigned NOT NULL, `template_version_id` bigint(20) unsigned DEFAULT NULL, `version_number` int(10) unsigned NOT NULL, `rendered_title` varchar(190) DEFAULT NULL, `rendered_header` text DEFAULT NULL, `rendered_body` longtext NOT NULL, `source` varchar(30) NOT NULL DEFAULT 'generated', `checksum` char(64) NOT NULL, `is_published` tinyint(1) NOT NULL DEFAULT 1, `is_finalized` tinyint(1) NOT NULL DEFAULT 0, `generated_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_contract_document_version` (`contract_id`,`version_number`), KEY `idx_contract_document_versions_published` (`contract_id`,`is_published`,`version_number`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `settlement_quotes` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `quote_uuid` varchar(64) NOT NULL, `actor_id` bigint(20) unsigned DEFAULT NULL, `customer_id` bigint(20) unsigned NOT NULL, `contract_id` bigint(20) unsigned NOT NULL, `scope` varchar(20) NOT NULL DEFAULT 'selected', `selected_installment_ids_json` longtext NOT NULL, `calculation_date` date NOT NULL, `calculated_at` datetime NOT NULL, `principal_total` decimal(18,2) NOT NULL DEFAULT 0.00, `normal_penalty_total` decimal(18,2) NOT NULL DEFAULT 0.00, `legal_penalty_total` decimal(18,2) NOT NULL DEFAULT 0.00, `reward_total` decimal(18,2) NOT NULL DEFAULT 0.00, `final_payable` decimal(18,2) NOT NULL DEFAULT 0.00, `snapshot_hash` char(64) NOT NULL, `calculation_version` varchar(120) NOT NULL, `status` varchar(30) NOT NULL DEFAULT 'open', `expires_at` datetime NOT NULL, `used_at` datetime DEFAULT NULL, `payment_group_id` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_settlement_quotes_uuid` (`quote_uuid`), KEY `idx_settlement_quotes_contract_status` (`contract_id`,`status`,`expires_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `payment_groups` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `group_number` varchar(80) NOT NULL, `contract_id` bigint(20) unsigned NOT NULL, `customer_id` bigint(20) unsigned NOT NULL, `created_by` bigint(20) unsigned DEFAULT NULL, `requested_amount` decimal(18,2) NOT NULL, `allocated_amount` decimal(18,2) NOT NULL DEFAULT 0.00, `method` varchar(30) NOT NULL DEFAULT 'manual', `status` varchar(30) NOT NULL DEFAULT 'paid', `gateway_track_id` varchar(100) DEFAULT NULL, `idempotency_key` varchar(120) DEFAULT NULL, `quote_uuid` varchar(64) DEFAULT NULL, `payment_request_uuid` varchar(64) DEFAULT NULL, `allocation_scope` varchar(20) NOT NULL DEFAULT 'selected', `description` text DEFAULT NULL, `selection_json` longtext DEFAULT NULL, `allocation_json` longtext DEFAULT NULL, `created_at` datetime NOT NULL, `completed_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_payment_groups_number` (`group_number`), UNIQUE KEY `uq_payment_groups_idempotency` (`idempotency_key`), KEY `idx_payment_groups_contract` (`contract_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `payment_groups` ADD COLUMN `gateway_verified_amount` bigint(20) unsigned DEFAULT NULL AFTER `gateway_track_id`, ADD COLUMN `gateway_ref_id` varchar(100) DEFAULT NULL AFTER `gateway_verified_amount`, ADD COLUMN `reconciliation_reason` varchar(255) DEFAULT NULL AFTER `gateway_ref_id`, ADD INDEX `idx_payment_groups_review_queue` (`status`, `created_at`);
CREATE TABLE `payment_allocations` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `payment_group_id` bigint(20) unsigned NOT NULL, `payment_id` bigint(20) unsigned NOT NULL, `contract_id` bigint(20) unsigned NOT NULL, `installment_id` bigint(20) unsigned NOT NULL, `allocated_amount` decimal(18,2) NOT NULL, `principal_applied` decimal(18,2) NOT NULL DEFAULT 0.00, `normal_penalty_applied` decimal(18,2) NOT NULL DEFAULT 0.00, `legal_penalty_applied` decimal(18,2) NOT NULL DEFAULT 0.00, `reward_applied` decimal(18,2) NOT NULL DEFAULT 0.00, `remaining_before` decimal(18,2) NOT NULL DEFAULT 0.00, `remaining_after` decimal(18,2) NOT NULL DEFAULT 0.00, `status_after` varchar(30) DEFAULT NULL, `quote_uuid` varchar(64) DEFAULT NULL, `reversal_of_allocation_id` bigint(20) unsigned DEFAULT NULL, `is_reversal` tinyint(1) NOT NULL DEFAULT 0, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `idx_payment_allocations_group_installment` (`payment_group_id`,`installment_id`,`is_reversal`), KEY `idx_payment_allocations_payment` (`payment_id`), CONSTRAINT `fk_payment_allocation_group` FOREIGN KEY (`payment_group_id`) REFERENCES `payment_groups` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `installment_bulk_operations` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `operation_number` varchar(80) NOT NULL, `contract_id` bigint(20) unsigned NOT NULL, `operation_type` varchar(40) NOT NULL, `installment_ids_json` longtext NOT NULL, `old_snapshot_json` longtext DEFAULT NULL, `new_snapshot_json` longtext DEFAULT NULL, `reason` text NOT NULL, `performed_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_installment_bulk_operation_number` (`operation_number`), KEY `idx_installment_bulk_operations_contract` (`contract_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `medal_definitions` (`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `slug` varchar(100) NOT NULL, `title` varchar(190) NOT NULL, `short_description` varchar(255) DEFAULT NULL, `full_description` text DEFAULT NULL, `how_to_earn` text DEFAULT NULL, `icon_key` varchar(50) NOT NULL DEFAULT 'award', `icon_path` varchar(255) DEFAULT NULL, `color` varchar(20) NOT NULL DEFAULT '#f59e0b', `category` varchar(30) NOT NULL DEFAULT 'activity', `points` int(11) NOT NULL DEFAULT 0, `award_type` varchar(30) NOT NULL DEFAULT 'automatic', `behavior_type` varchar(30) NOT NULL DEFAULT 'permanent', `revocation_behavior` varchar(30) NOT NULL DEFAULT 'never', `reactivation_behavior` varchar(30) NOT NULL DEFAULT 'restore', `criteria_type` varchar(50) DEFAULT NULL, `criteria_json` longtext DEFAULT NULL, `is_repeatable` tinyint(1) NOT NULL DEFAULT 0, `maximum_awards` int(10) unsigned DEFAULT NULL, `is_active` tinyint(1) NOT NULL DEFAULT 1, `sort_order` int(11) NOT NULL DEFAULT 0, `created_by` bigint(20) unsigned DEFAULT NULL, `created_at` datetime NOT NULL, `updated_at` datetime DEFAULT NULL, `last_evaluated_at` datetime DEFAULT NULL, `archived_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_medal_definition_slug` (`slug`), KEY `idx_medal_definitions_active_sort` (`is_active`,`sort_order`), KEY `idx_medal_definitions_behavior` (`behavior_type`,`revocation_behavior`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
UPDATE `medal_definitions` SET `behavior_type` = 'reversible', `revocation_behavior` = 'automatic', `reactivation_behavior` = 'restore' WHERE `slug` = 'no-overdue';
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
  `avatar_path` varchar(500) DEFAULT NULL,
  `avatar_version` bigint(20) unsigned NOT NULL DEFAULT 0,
  `avatar_updated_at` datetime DEFAULT NULL,
  `avatar_category` varchar(40) DEFAULT NULL,
  `avatar_source` varchar(30) NOT NULL DEFAULT 'fallback',
  `avatar_locked` tinyint(1) NOT NULL DEFAULT 0,
  `avatar_suggestion_reason` varchar(255) DEFAULT NULL,
  `department` varchar(40) DEFAULT NULL,
  `is_department_manager` tinyint(1) NOT NULL DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `customer_password_mode` varchar(12) DEFAULT NULL,
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
DROP TABLE IF EXISTS `auth_login_attempts`;
CREATE TABLE `auth_login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` varchar(20) NOT NULL,
  `scope_hash` char(64) NOT NULL,
  `failed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `first_failed_at` datetime DEFAULT NULL,
  `last_failed_at` datetime DEFAULT NULL,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_login_attempt_scope` (`scope_type`,`scope_hash`),
  KEY `idx_auth_login_attempt_lock` (`locked_until`),
  KEY `idx_auth_login_attempt_last_failure` (`last_failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `contract_requests`;
CREATE TABLE `contract_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_uuid` varchar(64) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `request_hash` char(64) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'processing',
  `contract_id` bigint(20) unsigned DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_requests_uuid` (`request_uuid`),
  KEY `idx_contract_requests_contract` (`contract_id`),
  KEY `idx_contract_requests_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- V1.5.0 legal policy, internal workflow and immutable-document metadata.
CREATE TABLE IF NOT EXISTS `legal_policy_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `policy_code` varchar(64) NOT NULL DEFAULT 'default',
  `version_number` int unsigned NOT NULL,
  `policy_json` longtext NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legal_policy_version` (`policy_code`,`version_number`),
  KEY `idx_legal_policy_active` (`policy_code`,`is_active`,`version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `contract_legal_policy_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint unsigned NOT NULL,
  `policy_version_id` bigint unsigned DEFAULT NULL,
  `policy_version_number` int unsigned NOT NULL DEFAULT 0,
  `policy_json` longtext NOT NULL,
  `source` varchar(40) NOT NULL DEFAULT 'legacy_review_required',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_legal_policy_snapshot` (`contract_id`),
  KEY `idx_contract_legal_policy_version` (`policy_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `legal_case_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_uuid` varchar(64) NOT NULL,
  `contract_id` bigint unsigned NOT NULL,
  `legal_case_id` bigint unsigned DEFAULT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legal_case_request_uuid` (`request_uuid`),
  KEY `idx_legal_case_request_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `legal_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_uuid` varchar(64) NOT NULL,
  `request_uuid` varchar(64) NOT NULL,
  `legal_case_id` bigint unsigned NOT NULL,
  `contract_id` bigint unsigned NOT NULL,
  `document_type` varchar(40) NOT NULL,
  `title` varchar(190) NOT NULL,
  `body` longtext NOT NULL,
  `document_status` varchar(50) NOT NULL DEFAULT 'internal_draft',
  `deadline_date` date DEFAULT NULL,
  `policy_snapshot_json` longtext DEFAULT NULL,
  `debt_snapshot_json` longtext DEFAULT NULL,
  `external_reference` varchar(190) DEFAULT NULL,
  `external_authority` varchar(190) DEFAULT NULL,
  `external_submitted_at` date DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `externally_confirmed_by` bigint unsigned DEFAULT NULL,
  `externally_confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legal_document_uuid` (`document_uuid`),
  UNIQUE KEY `uq_legal_document_request` (`request_uuid`),
  KEY `idx_legal_document_case` (`legal_case_id`,`document_status`),
  KEY `idx_legal_document_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `legal_case_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cost_uuid` varchar(64) NOT NULL,
  `legal_case_id` bigint unsigned DEFAULT NULL,
  `contract_id` bigint unsigned NOT NULL,
  `source_legal_log_id` bigint unsigned DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(190) NOT NULL,
  `amount_toman` bigint NOT NULL,
  `paid_amount_toman` bigint NOT NULL DEFAULT 0,
  `cost_date` date NOT NULL,
  `payment_status` varchar(30) NOT NULL DEFAULT 'pending',
  `approval_status` varchar(30) NOT NULL DEFAULT 'pending_approval',
  `paid_by` bigint unsigned DEFAULT NULL,
  `chargeable_to_customer` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `external_reference` varchar(190) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `approved_at` datetime DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL,
  `reversal_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legal_case_cost_uuid` (`cost_uuid`),
  UNIQUE KEY `uq_legal_case_cost_source_log` (`source_legal_log_id`),
  KEY `idx_legal_case_cost_contract_status` (`contract_id`,`approval_status`,`payment_status`),
  KEY `idx_legal_case_cost_case_date` (`legal_case_id`,`cost_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `legal_cost_payment_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_group_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned NOT NULL,
  `legal_case_cost_id` bigint unsigned NOT NULL,
  `contract_id` bigint unsigned NOT NULL,
  `allocated_amount_toman` bigint NOT NULL,
  `reversal_of_allocation_id` bigint unsigned DEFAULT NULL,
  `is_reversal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_legal_cost_payment_group` (`payment_group_id`),
  KEY `idx_legal_cost_payment_cost` (`legal_case_cost_id`,`is_reversal`),
  UNIQUE KEY `uq_legal_cost_payment_source` (`payment_id`,`legal_case_cost_id`,`is_reversal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `legal_cost_disbursements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_uuid` varchar(64) NOT NULL,
  `legal_case_cost_id` bigint unsigned NOT NULL,
  `contract_id` bigint unsigned NOT NULL,
  `amount_toman` bigint NOT NULL,
  `recipient` varchar(190) NOT NULL,
  `payment_reference` varchar(190) DEFAULT NULL,
  `paid_at` date NOT NULL,
  `note` text DEFAULT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `is_reversal` tinyint(1) NOT NULL DEFAULT 0,
  `reversal_of_id` bigint unsigned DEFAULT NULL,
  `reversal_reason` text DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legal_cost_disbursement_uuid` (`disbursement_uuid`),
  UNIQUE KEY `uq_legal_cost_disbursement_request` (`idempotency_key`),
  UNIQUE KEY `uq_legal_cost_disbursement_reversal` (`reversal_of_id`),
  KEY `idx_legal_cost_disbursement_cost` (`legal_case_cost_id`,`id`),
  KEY `idx_legal_cost_disbursement_contract` (`contract_id`,`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `installment_change_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` varchar(48) NOT NULL,
  `installment_id` bigint unsigned NOT NULL,
  `contract_id` bigint unsigned NOT NULL,
  `request_type` varchar(24) NOT NULL,
  `status` varchar(24) NOT NULL,
  `before_snapshot_json` longtext NOT NULL,
  `requested_snapshot_json` longtext NOT NULL,
  `dependency_snapshot_json` longtext DEFAULT NULL,
  `reason` text NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `affected_installment_ids_json` text DEFAULT NULL,
  `calculation_version` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL,
  `applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_installment_change_request` (`request_id`),
  KEY `idx_installment_change_contract_status` (`contract_id`,`status`),
  KEY `idx_installment_change_installment` (`installment_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `installment_voids` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `installment_id` bigint unsigned NOT NULL,
  `contract_id` bigint unsigned NOT NULL,
  `previous_amount` bigint NOT NULL,
  `previous_due_date` date NOT NULL,
  `void_reason` text NOT NULL,
  `voided_by` bigint unsigned NOT NULL,
  `request_id` varchar(48) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_installment_void_once` (`installment_id`),
  KEY `idx_installment_void_contract` (`contract_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `portal_banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `placement` varchar(40) NOT NULL DEFAULT 'customer_dashboard',
  `audience_role` varchar(30) NOT NULL DEFAULT 'customer',
  `title` varchar(190) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `eyebrow` varchar(80) DEFAULT NULL,
  `desktop_image_path` varchar(255) DEFAULT NULL,
  `mobile_image_path` varchar(255) DEFAULT NULL,
  `image_alt` varchar(190) DEFAULT NULL,
  `cta_label` varchar(80) DEFAULT NULL,
  `link_type` varchar(20) NOT NULL DEFAULT 'none',
  `link_target` varchar(500) DEFAULT NULL,
  `open_in_new_tab` tinyint(1) NOT NULL DEFAULT 0,
  `tone` varchar(20) NOT NULL DEFAULT 'primary',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `archived_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_portal_banners_delivery` (`placement`,`audience_role`,`is_active`,`archived_at`,`sort_order`),
  KEY `idx_portal_banners_schedule` (`starts_at`,`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `portal_banners`
(`placement`, `audience_role`, `title`, `body`, `eyebrow`, `cta_label`, `link_type`, `link_target`, `tone`, `is_active`, `sort_order`)
SELECT 'customer_dashboard', 'customer', 'پرداخت اقساط، ساده و شفاف',
       'مانده، سررسید و مبلغ قابل پرداخت امروز را یکجا ببینید و با اطمینان پرداخت کنید.',
       'پروما پی همراه شما', 'مشاهده اقساط', 'internal', 'installments/panel', 'primary', 1, 10
WHERE NOT EXISTS (SELECT 1 FROM `portal_banners` WHERE `placement` = 'customer_dashboard' AND `archived_at` IS NULL);
INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`,`is_secret`) VALUES
('legal_delay_value','30',0),('legal_delay_unit','day',0),('legal_overdue_count_threshold','1',0),('legal_overdue_amount_enabled','0',0),('legal_overdue_amount_threshold','0',0),('legal_eligibility_operator','delay_only',0),('legal_warning_before_days','0',0),('legal_allow_self_initiation','1',0);
INSERT IGNORE INTO `legal_policy_versions` (`policy_code`,`version_number`,`policy_json`,`is_active`,`created_at`) VALUES
('default',1,'{"delay_value":30,"delay_unit":"day","overdue_count_threshold":1,"overdue_amount_enabled":0,"overdue_amount_threshold":0,"eligibility_operator":"delay_only","warning_before_days":0,"allow_self_initiation":1,"legal_referred_at_mode":"persisted_referral_confirmation"}',1,NOW());



-- Customer activity points and tier medals (V4.0.2).
CREATE TABLE IF NOT EXISTS customer_point_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_key VARCHAR(80) NOT NULL,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    source_type VARCHAR(20) NOT NULL DEFAULT 'system',
    points INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_customer_point_rules_activity (activity_key),
    KEY idx_customer_point_rules_active (is_active, source_type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_point_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    rule_id BIGINT UNSIGNED NULL,
    activity_key VARCHAR(80) NOT NULL,
    activity_title VARCHAR(150) NOT NULL,
    points INT NOT NULL,
    source_type VARCHAR(20) NOT NULL DEFAULT 'system',
    note TEXT NULL,
    reference_type VARCHAR(40) NULL,
    reference_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(191) NOT NULL,
    awarded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_customer_point_ledger_idempotency (idempotency_key),
    KEY idx_customer_point_ledger_customer_time (customer_id, created_at, id),
    KEY idx_customer_point_ledger_reference (reference_type, reference_id),
    KEY idx_customer_point_ledger_activity (activity_key, source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_loyalty_tiers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    title VARCHAR(100) NOT NULL,
    medal_title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    min_points INT UNSIGNED NOT NULL,
    icon_key VARCHAR(50) NOT NULL DEFAULT 'award',
    color VARCHAR(20) NOT NULL DEFAULT '#d97706',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_customer_loyalty_tiers_slug (slug),
    KEY idx_customer_loyalty_tiers_threshold (is_active, min_points, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_loyalty_tier_awards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    tier_id BIGINT UNSIGNED NOT NULL,
    tier_title VARCHAR(100) NOT NULL,
    medal_title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    icon_key VARCHAR(50) NOT NULL DEFAULT 'award',
    color VARCHAR(20) NOT NULL DEFAULT '#d97706',
    points_at_award INT UNSIGNED NOT NULL DEFAULT 0,
    awarded_at DATETIME NOT NULL,
    KEY idx_customer_loyalty_awards_customer (customer_id, awarded_at),
    UNIQUE KEY uq_customer_loyalty_awards_tier (customer_id, tier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO customer_point_rules
    (activity_key, title, description, source_type, points, is_active, sort_order, created_at)
VALUES
    ('contract_created', 'ثبت قرارداد', 'برای ثبت قرارداد غیرلغوشده به مشتری امتیاز داده می‌شود.', 'system', 20, 1, 10, NOW()),
    ('payment_completed', 'پرداخت موفق', 'برای هر پرداخت موفق و تأییدشده، یک‌بار امتیاز داده می‌شود.', 'system', 5, 1, 20, NOW()),
    ('payment_on_time', 'تکمیل قسط به‌موقع', 'وقتی قسط به‌طور کامل تا تاریخ سررسید پرداخت شود.', 'system', 10, 1, 30, NOW()),
    ('payment_early', 'تکمیل زودهنگام قسط', 'وقتی قسط به‌طور کامل پیش از سررسید پرداخت شود.', 'system', 15, 1, 40, NOW()),
    ('contract_completed', 'تسویه کامل قرارداد', 'برای تکمیل موفق قرارداد، یک‌بار امتیاز داده می‌شود.', 'system', 50, 1, 50, NOW()),
    ('identity_verified', 'تأیید هویت', 'پس از تأیید هر دو مدرک هویتی مشتری.', 'system', 30, 1, 60, NOW());

INSERT IGNORE INTO customer_loyalty_tiers
    (slug, title, medal_title, description, min_points, icon_key, color, sort_order, is_active, created_at)
VALUES
    ('bronze', 'برنزی', 'مدال برنز', 'شروع مسیر وفاداری و دریافت اولین مدال.', 100, 'award', '#b45309', 10, 1, NOW()),
    ('silver', 'نقره‌ای', 'مدال نقره', 'سطح مشتری نقره‌ای باشگاه وفاداری.', 300, 'star', '#64748b', 20, 1, NOW()),
    ('gold', 'طلایی', 'مدال طلا', 'سطح مشتری طلایی باشگاه وفاداری.', 700, 'award', '#ca8a04', 30, 1, NOW()),
    ('platinum', 'پلاتینی', 'مدال پلاتین', 'سطح مشتری پلاتینی باشگاه وفاداری.', 1500, 'shield', '#0f766e', 40, 1, NOW()),
    ('diamond', 'الماسی', 'مدال الماس', 'بالاترین سطح فعلی باشگاه وفاداری.', 3000, 'crown', '#6d28d9', 50, 1, NOW());

-- V6 additive tables are also present in the fresh-install schema.
CREATE TABLE IF NOT EXISTS `financial_ledger_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_uuid` char(36) NOT NULL,
  `contract_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `idempotency_key` varchar(190) NOT NULL,
  `effective_at` datetime NOT NULL,
  `posted_by` bigint unsigned DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `reverses_event_id` bigint unsigned DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_financial_ledger_event_uuid` (`event_uuid`),
  UNIQUE KEY `uq_financial_ledger_idempotency` (`idempotency_key`),
  KEY `idx_financial_ledger_contract` (`contract_id`,`id`),
  KEY `idx_financial_ledger_customer` (`customer_id`,`id`),
  KEY `idx_financial_ledger_source` (`source_type`,`source_id`),
  KEY `idx_financial_ledger_effective_at` (`effective_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_ledger_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `component` varchar(32) NOT NULL,
  `delta_toman` bigint NOT NULL,
  `source_line_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_financial_ledger_line_event` (`event_id`,`id`),
  KEY `idx_financial_ledger_line_component` (`component`,`event_id`),
  KEY `idx_financial_ledger_source_line` (`source_line_id`),
  CONSTRAINT `fk_financial_ledger_line_event` FOREIGN KEY (`event_id`) REFERENCES `financial_ledger_events` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `body` text NOT NULL,
  `audience_roles_json` text NOT NULL,
  `local_url` varchar(255) DEFAULT NULL,
  `published_by` bigint unsigned NOT NULL,
  `published_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_system_announcements_published` (`published_at`,`id`),
  KEY `idx_system_announcements_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_announcement_recipients` (
  `announcement_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`announcement_id`,`user_id`),
  KEY `idx_announcement_recipient_user` (`user_id`,`is_read`,`announcement_id`),
  CONSTRAINT `fk_announcement_recipient_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `system_announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcement_recipient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `web_push_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `p256dh` varchar(120) NOT NULL,
  `auth_secret` varchar(120) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_success_at` datetime DEFAULT NULL,
  `last_error_code` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_web_push_endpoint_hash` (`endpoint_hash`),
  KEY `idx_web_push_user_active` (`user_id`,`is_active`,`id`),
  CONSTRAINT `fk_web_push_subscription_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `web_push_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint unsigned NOT NULL,
  `event_key` varchar(190) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `http_status` smallint unsigned DEFAULT NULL,
  `attempts` smallint unsigned NOT NULL DEFAULT 0,
  `error_code` varchar(80) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_web_push_delivery_event` (`subscription_id`,`event_key`),
  KEY `idx_web_push_delivery_status` (`status`,`updated_at`),
  CONSTRAINT `fk_web_push_delivery_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `web_push_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `collection_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `stage` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `due_at` datetime NOT NULL,
  `dedupe_key` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_collection_task_dedupe` (`dedupe_key`),
  KEY `idx_collection_task_assigned` (`assigned_to`,`status`,`due_at`),
  KEY `idx_collection_task_contract` (`contract_id`,`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_credit_profiles` (
  `customer_id` bigint unsigned NOT NULL,
  `score` smallint unsigned NOT NULL,
  `risk_level` varchar(16) NOT NULL,
  `metrics_json` longtext NOT NULL,
  `recommendation_json` longtext NOT NULL,
  `reasons_json` longtext NOT NULL,
  `rules_hash` char(64) NOT NULL,
  `calculated_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`customer_id`),
  KEY `idx_customer_credit_profiles_rank` (`risk_level`,`score`),
  KEY `idx_customer_credit_profiles_calculated` (`calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


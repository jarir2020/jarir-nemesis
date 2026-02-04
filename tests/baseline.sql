-- Nemesis Database Dump
-- Generated at: 2026-02-04 08:39:39

SET FOREIGN_KEY_CHECKS=0;

-- Table structure for table `applications`
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `serial` int(10) NOT NULL,
  `application_number` longtext NOT NULL,
  `registration_number` varchar(10) NOT NULL,
  `name_of_worker` text NOT NULL,
  `document_number` varchar(10) NOT NULL,
  `status` text NOT NULL,
  `employer_identification` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `applications`
INSERT INTO `applications` (`id`, `serial`, `application_number`, `registration_number`, `name_of_worker`, `document_number`, `status`, `employer_identification`) VALUES ('2', '1023', 'APP20250725001_UPDATED', 'REG7654322', 'Jane Smith', 'DOC1234567', 'approved', NULL);
INSERT INTO `applications` (`id`, `serial`, `application_number`, `registration_number`, `name_of_worker`, `document_number`, `status`, `employer_identification`) VALUES ('4', '1025', 'APP202250725001_UPDATED', 'REG7254322', 'Jarir2020', 'ABCDEFGH', 'Pending', NULL);
INSERT INTO `applications` (`id`, `serial`, `application_number`, `registration_number`, `name_of_worker`, `document_number`, `status`, `employer_identification`) VALUES ('5', '1024', 'APP202507250012_UPDATED', 'REG7654323', 'Jane Smiths', 'DOC1234568', 'approved', NULL);
INSERT INTO `applications` (`id`, `serial`, `application_number`, `registration_number`, `name_of_worker`, `document_number`, `status`, `employer_identification`) VALUES ('6', '1026', 'BPA/FWCMS/DE517F15E60281777E', 'REG1234467', 'John Does', 'DOC6876543', 'pending', NULL);

-- Table structure for table `cache`
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` longtext DEFAULT NULL,
  `expires_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `cache`
INSERT INTO `cache` (`key`, `value`, `expires_at`) VALUES ('test_key', 's:29:\"Cached at 2026-02-04 07:54:18\";', '1770191718');

-- Table structure for table `migrations`
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `migrations`
INSERT INTO `migrations` (`id`, `migration`, `created_at`) VALUES ('3', '2026_02_04_081846_create_users_table.php', '2026-02-04 14:20:01');

-- Table structure for table `product`
DROP TABLE IF EXISTS `product`;
CREATE TABLE `product` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `product`
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('1', 'Updateds ssProduct Name', '29.99');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('4', 'Example Product 2', '19.99');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('5', 'Example Product 3', '19.99');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('6', 'Example Product 34', '19.99');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('7', 'Example Product 34s', '19.99');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('8', 'Example Product 34s', '19.99');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('9', 'Example Product 34ssss', '19.99');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('10', 'Example Product 34ssss', '19.99');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('13', 'Gaming Laptop', '1800.00');
INSERT INTO `product` (`id`, `name`, `price`) VALUES ('16', 'Laptop', '1500.00');

-- Table structure for table `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`) VALUES ('1', 'jarir_test', 'test@nemesis.com', 'password123', '2026-02-04 14:20:01');
INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`) VALUES ('2', 'nemesis_dev', 'dev@nemesis.com', 'secret', '2026-02-04 14:20:01');

SET FOREIGN_KEY_CHECKS=1;

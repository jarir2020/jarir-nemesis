-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 26, 2025 at 10:28 PM
-- Server version: 10.6.22-MariaDB
-- PHP Version: 8.3.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eservica_database_2025`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `serial` int(100) NOT NULL,
  `application_number` longtext NOT NULL,
  `registration_number` varchar(10) DEFAULT NULL,
  `name_of_worker` text NOT NULL,
  `document_number` varchar(10) NOT NULL,
  `status` text NOT NULL,
  `employer_identification` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `serial`, `application_number`, `registration_number`, `name_of_worker`, `document_number`, `status`, `employer_identification`) VALUES
(38, 1, 'BPA/FWCMS/OL45290C322687CC8B', '33138-D', 'ISWANDI', 'C9250797', 'CETAK', ''),
(39, 2, 'BPA/FWCMS/VOB50B63945E900C48', '33138-D', 'ISWANDI', 'C9250797', 'CETAK', NULL),
(40, 3, 'BPA/FWCMS/HFB930131AB1F4A9A9', '33138-D', 'MUHAMAD YUDI', 'C8665792', 'CETAK', NULL),
(41, 4, 'BPA/FWCMS/HL6917580C8E17F743', '33138-D', 'HUMAM MUSTOFA', 'E1024545', 'CETAK', NULL),
(42, 5, 'BPA/FWCMS/MTA7EA870BAACC9B99', '33138-D', 'RUDI IRWAN', 'C9832343', 'CETAK', NULL),
(43, 6, 'BPA/FWCMS/UO8957378B36EB9437', '33138-D', 'MUSTARIZAL', 'C9836770', 'CETAK', NULL),
(44, 7, 'BPA/FWCMS/YQ4A048846B79A8CAE', '33138-D', 'JUMADI', 'E0706268', 'CETAK', NULL),
(45, 8, 'BPA/FWCMS/MD47BE9FA93A8B65DC', '33138-D', 'RINALDI', 'C9836555', 'CETAK', NULL),
(46, 9, 'BPA/FWCMS/LS232FAA8A2EA22322', '33138-D', 'RIKI AYUN', 'C8664645', 'CETAK', NULL),
(47, 10, 'BPA/FWCMS/KJ98B2DC1868BE39B0', '33138-D', 'PIRMAN', 'C9251756', 'CETAK', NULL),
(48, 11, 'BPA/FWCMS/NFB809EFDCC7FD6099', '33138-D', 'MUKLIS', 'C8592316', 'CETAK', NULL),
(49, 12, 'BPA/FWCMS/LI34D86B7BFE71A707', '33138-D', 'MUHAMMAD AGUS SULISTIO', 'E1216304', 'CETAK', NULL),
(50, 13, 'BPA/FWCMS/LTB26E8968EC2C3CAD', '33138-D', 'KHOLIK MASAI', 'E1673871', 'CETAK', NULL),
(51, 14, 'BPA/FWCMS/SG82FDB03F59019052', '33138-D', 'MULYANA', 'C9105481', 'CETAK', NULL),
(52, 15, 'BPA/FWCMS/YLF486D3E30C22807F', '33138-D', 'IWAN PALES', 'C9253574', 'CETAK', NULL),
(53, 16, 'BPA/FWCMS/ZPC597D7E26535CDAF', '33138-D', 'ISMANTO', 'E2910226', 'CETAK', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`id`, `name`, `price`) VALUES
(1, 'Updateds ssProduct Name', 29.99),
(4, 'Example Product 2', 19.99),
(5, 'Example Product 3', 19.99),
(6, 'Example Product 34', 19.99),
(7, 'Example Product 34s', 19.99),
(8, 'Example Product 34s', 19.99),
(9, 'Example Product 34ssss', 19.99),
(10, 'Example Product 34ssss', 19.99),
(13, 'Gaming Laptop', 1800.00),
(16, 'Laptop', 1500.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `auth_token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `otp` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `auth_token`, `created_at`, `updated_at`, `otp`) VALUES
(50, 'jarir1114@gmail.com', '$2y$10$bCJxfP1pC7Vqf/UlPKV/ROiH5JJ1vvOxGT0fYbAFYxi3LTvrOzyD6', '51dff3fd8d957ed01c3906b4dd9c5a4e7e484bda80eb623689692cce8e94', '2025-07-25 17:19:16', '2025-07-25 20:03:51', NULL),
(51, 'admin@gmail.com', '$2y$10$.MRx3h3NdgBFHzqL87UfRONDOqB.Pj/ZoP0a2je/kgEmv.f5L4QAq', '837cf463dbbde91edd6f1be81a2d1e4f0f0515d412ef1b5e2d238a46c90e', '2025-07-25 20:10:12', '2025-07-26 22:19:05', NULL),
(53, 'jarircse16@gmail.com', '$2y$10$bCJxfP1pC7Vqf/UlPKV/ROiH5JJ1vvOxGT0fYbAFYxi3LTvrOzyD6', '51dff3fd8d957ed01c3906b4dd9c5a4e7e484bda80eb623689692cce8e99', '2025-07-25 17:19:16', '2025-07-25 20:03:51', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `auth_token` (`auth_token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 13, 2026 at 12:55 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ecxpense_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_notifications`
--

CREATE TABLE `activity_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `workspace_name` varchar(150) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_notifications`
--

INSERT INTO `activity_notifications` (`id`, `user_id`, `workspace_name`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'Shared Record 2 Test', 'Added a new expense: Web Hosting (₱3,000.00)', 0, '2026-10-13 10:52:22');

-- --------------------------------------------------------

--
-- Table structure for table `collaborators`
--

CREATE TABLE `collaborators` (
  `id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','editor','viewer') NOT NULL DEFAULT 'viewer',
  `status` enum('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `collaborators`
--

INSERT INTO `collaborators` (`id`, `record_id`, `user_id`, `role`, `status`, `created_at`) VALUES
(1, 1, 1, 'owner', 'accepted', '2026-10-13 10:08:29'),
(2, 2, 2, 'owner', 'accepted', '2026-10-13 10:11:09'),
(3, 2, 1, 'editor', 'accepted', '2026-10-13 10:11:16'),
(4, 1, 2, 'editor', 'pending', '2026-10-13 10:19:44');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shared_record_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `category` varchar(50) NOT NULL,
  `expense_date` date NOT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `user_id`, `shared_record_id`, `title`, `amount`, `category`, `expense_date`, `receipt_image`, `created_at`) VALUES
(1, 1, 1, 'Server Hosting', 1500.00, 'Other', '2026-10-13', NULL, '2026-10-13 10:22:05'),
(2, 1, NULL, 'Dinner', 5000.00, 'Food', '2026-10-13', NULL, '2026-10-13 10:28:34'),
(3, 1, 2, 'Web Hosting', 3000.00, 'Other', '2026-10-13', NULL, '2026-10-13 10:52:22');

-- --------------------------------------------------------

--
-- Table structure for table `shared_records`
--

CREATE TABLE `shared_records` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shared_records`
--

INSERT INTO `shared_records` (`id`, `name`, `owner_id`, `created_at`) VALUES
(1, 'Shared Test', 1, '2026-10-13 10:08:29'),
(2, 'Shared Record 2 Test', 2, '2026-10-13 10:11:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'walo', '$2y$10$dSK8UIvUQ1aMCZl86xO1GeesfJrU0cCsBGpInKSCecmcT.lrI5Ixi', '2026-10-13 09:05:02'),
(2, 'walo2', '$2y$10$6Gt5fEvgymELGzHAObYNOOHiO/ipGtfgH3zWQzZCpP2xiVYPHz48C', '2026-10-13 10:10:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_notifications`
--
ALTER TABLE `activity_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `collaborators`
--
ALTER TABLE `collaborators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `record_id` (`record_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shared_record_id` (`shared_record_id`);

--
-- Indexes for table `shared_records`
--
ALTER TABLE `shared_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_notifications`
--
ALTER TABLE `activity_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `collaborators`
--
ALTER TABLE `collaborators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `shared_records`
--
ALTER TABLE `shared_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_notifications`
--
ALTER TABLE `activity_notifications`
  ADD CONSTRAINT `activity_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `collaborators`
--
ALTER TABLE `collaborators`
  ADD CONSTRAINT `collaborators_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `shared_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `collaborators_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`shared_record_id`) REFERENCES `shared_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_records`
--
ALTER TABLE `shared_records`
  ADD CONSTRAINT `shared_records_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

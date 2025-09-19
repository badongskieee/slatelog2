-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 20, 2025 at 03:23 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

--
-- Database: `logistics_db`
--
CREATE DATABASE IF NOT EXISTS `logistics_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `logistics_db`;

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `alert_type` enum('SOS','Breakdown','Other') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Pending','Acknowledged','Resolved') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `status` enum('Active','Suspended','Inactive','Pending') NOT NULL DEFAULT 'Pending',
  `rating` decimal(3,1) DEFAULT 0.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `user_id`, `name`, `license_number`, `status`, `rating`, `created_at`) VALUES
(1, 2, 'J. Cruz', 'D01-23-456789', 'Active', 4.5, '2025-09-18 08:55:18'),
(2, 3, 'S. Tan', 'D02-34-567890', 'Active', 4.2, '2025-09-18 08:55:18');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_approvals`
--

CREATE TABLE `maintenance_approvals` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `arrival_date` date NOT NULL,
  `date_of_return` date DEFAULT NULL,
  `status` enum('Pending','Approved','In Progress','Completed','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_approvals`
--

INSERT INTO `maintenance_approvals` (`id`, `vehicle_id`, `arrival_date`, `date_of_return`, `status`, `created_at`) VALUES
(1, 1, '2025-09-15', '2025-09-18', 'Completed', '2025-09-19 18:10:10'),
(2, 3, '2025-09-20', NULL, 'In Progress', '2025-09-19 18:10:10'),
(3, 2, '2025-09-22', NULL, 'Approved', '2025-09-19 18:11:15'),
(4, 5, '2025-09-25', NULL, 'Pending', '2025-09-19 18:11:15');


-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `reservation_code` varchar(20) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `reserved_by_user_id` int(11) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `status` enum('Confirmed','Pending','Cancelled','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

CREATE TABLE `routes` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `route_name` varchar(255) NOT NULL,
  `distance_km` decimal(10,2) NOT NULL,
  `estimated_time` varchar(50) NOT NULL,
  `estimated_cost` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Recommended',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tracking_log`
--

CREATE TABLE `tracking_log` (
  `id` bigint(20) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `speed_mph` int(11) DEFAULT 0,
  `status_message` varchar(255) DEFAULT NULL,
  `log_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `trip_code` varchar(20) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `pickup_time` datetime NOT NULL,
  `destination` varchar(255) NOT NULL,
  `status` enum('Scheduled','Completed','Cancelled','En Route','Breakdown','Idle') NOT NULL DEFAULT 'Scheduled',
  `current_location` varchar(255) DEFAULT NULL,
  `eta` datetime DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trip_costs`
--

CREATE TABLE `trip_costs` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `fuel_cost` decimal(10,2) DEFAULT 0.00,
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `tolls_cost` decimal(10,2) DEFAULT 0.00,
  `other_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (`fuel_cost` + `labor_cost` + `tolls_cost` + `other_cost`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usage_logs`
--

CREATE TABLE `usage_logs` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `metrics` varchar(255) NOT NULL,
  `fuel_usage` decimal(10,2) NOT NULL,
  `mileage` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usage_logs`
--

INSERT INTO `usage_logs` (`id`, `vehicle_id`, `log_date`, `metrics`, `fuel_usage`, `mileage`, `created_at`) VALUES
(1, 1, '2025-09-19', 'Daily Checkup', 25.50, 150234, '2025-09-19 18:12:45'),
(2, 2, '2025-09-19', 'Delivery to Makati', 15.20, 89765, '2025-09-19 18:12:45'),
(3, 3, '2025-09-18', 'Pre-maintenance check', 5.00, 120450, '2025-09-19 18:13:21'),
(4, 1, '2025-09-18', 'Delivery to Pasig', 22.00, 150110, '2025-09-19 18:13:21'),
(5, 2, '2025-09-17', 'City Driving', 18.70, 89650, '2025-09-19 18:14:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','driver') NOT NULL DEFAULT 'staff',
  `failed_login_attempts` int(11) DEFAULT 0,
  `lockout_until` datetime DEFAULT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$R9c1Y.h3.qg8s/j3qZ8TLOz.jC6G2/aW1zE.V3s.U5N3h3.v4.v1.', 'admin'),
(2, 'jcruz', 'jcruz@example.com', '$2y$10$Y8.PAe5r9GgBvLW3f.2LHu0G8iE4Hk.cjgSxmmW.x8uJ5Q.FLWvJ.', 'driver'),
(3, 'stan', 'stan@example.com', '$2y$10$wF.5bK/3g20K.a4i6G/pFOaYd3u.L9eI03gC.1n8L3b2J/4i3b.B.', 'driver');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tag_type` varchar(50) DEFAULT NULL,
  `tag_code` varchar(100) DEFAULT NULL,
  `load_capacity_kg` int(11) DEFAULT NULL,
  `plate_no` varchar(20) DEFAULT NULL,
  `status` enum('Active','Inactive','Maintenance','En Route','Idle','Breakdown') NOT NULL DEFAULT 'Active',
  `assigned_driver_id` int(11) DEFAULT NULL,
  `image_url` varchar(2083) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `type`, `model`, `tag_type`, `tag_code`, `load_capacity_kg`, `plate_no`, `status`, `assigned_driver_id`, `image_url`) VALUES
(1, 'Truck', 'Isuzu Elf', 'RFID', 'RF12345', 10000, 'ABC-1234', 'Active', 1, NULL),
(2, 'Van', 'Toyota Hiace', 'Barcode', 'BC67890', 5000, 'DEF-5678', 'Active', NULL, NULL),
(3, 'Container Truck', 'Volvo FH16', 'RFID', 'RF-CT-001', 25000, 'PQR-1122', 'Active', NULL, NULL),
(4, 'Trailer Truck', 'Scania R730', 'Barcode', 'BC-TT-002', 30000, 'STU-3344', 'Active', NULL, NULL),
(5, 'Truck', 'Fuso Canter', 'RFID', 'RF54321', 12000, 'MNO-7890', 'Maintenance', 1, NULL),
(6, 'Box Truck', 'Hino 500', 'QR Code', 'QR-BT-003', 15000, 'VWX-5566', 'Active', NULL, NULL);

--
-- Indexes for dumped tables
--

ALTER TABLE `alerts` ADD PRIMARY KEY (`id`), ADD KEY `trip_id` (`trip_id`), ADD KEY `driver_id` (`driver_id`);
ALTER TABLE `drivers` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `license_number` (`license_number`), ADD KEY `user_id` (`user_id`);
ALTER TABLE `maintenance_approvals` ADD PRIMARY KEY (`id`), ADD KEY `vehicle_id` (`vehicle_id`);
ALTER TABLE `messages` ADD PRIMARY KEY (`id`), ADD KEY `sender_id` (`sender_id`), ADD KEY `receiver_id` (`receiver_id`);
ALTER TABLE `password_resets` ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`);
ALTER TABLE `reservations` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `reservation_code` (`reservation_code`), ADD KEY `vehicle_id` (`vehicle_id`), ADD KEY `reserved_by_user_id` (`reserved_by_user_id`);
ALTER TABLE `routes` ADD PRIMARY KEY (`id`), ADD KEY `trip_id` (`trip_id`);
ALTER TABLE `tracking_log` ADD PRIMARY KEY (`id`), ADD KEY `trip_id` (`trip_id`);
ALTER TABLE `trips` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `trip_code` (`trip_code`), ADD KEY `vehicle_id` (`vehicle_id`), ADD KEY `driver_id` (`driver_id`);
ALTER TABLE `trip_costs` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `trip_id` (`trip_id`);
ALTER TABLE `usage_logs` ADD PRIMARY KEY (`id`), ADD KEY `vehicle_id` (`vehicle_id`);
ALTER TABLE `users` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`), ADD UNIQUE KEY `email` (`email`);
ALTER TABLE `vehicles` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `tag_code` (`tag_code`), ADD UNIQUE KEY `plate_no` (`plate_no`), ADD KEY `assigned_driver_id` (`assigned_driver_id`);

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `alerts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `drivers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `maintenance_approvals` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `messages` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `password_resets` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `reservations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `routes` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tracking_log` MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE `trips` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `trip_costs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `usage_logs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `vehicles` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `alerts_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL;

ALTER TABLE `drivers`
  ADD CONSTRAINT `drivers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `maintenance_approvals`
  ADD CONSTRAINT `maintenance_approvals_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`reserved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `routes`
  ADD CONSTRAINT `routes_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL;

ALTER TABLE `tracking_log`
  ADD CONSTRAINT `tracking_log_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`);

ALTER TABLE `trip_costs`
  ADD CONSTRAINT `trip_costs_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

ALTER TABLE `usage_logs`
  ADD CONSTRAINT `usage_logs_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;

ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`assigned_driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL;
COMMIT;


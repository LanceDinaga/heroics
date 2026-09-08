-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql302.infinityfree.com
-- Generation Time: Sep 06, 2026 at 10:31 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42761603_heroics`
--

-- --------------------------------------------------------

--
-- Table structure for table `balance_logs`
--

CREATE TABLE `balance_logs` (
  `id` int(11) NOT NULL,
  `date_time` datetime DEFAULT current_timestamp(),
  `account_name` varchar(100) NOT NULL,
  `topup_added` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `logged_by` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `balance_logs`
--

INSERT INTO `balance_logs` (`id`, `date_time`, `account_name`, `topup_added`, `payment_method`, `notes`, `logged_by`) VALUES
(2, '2026-09-06 06:19:17', 'bogart', '200.00', '', 'free time', 'dominic');

-- --------------------------------------------------------

--
-- Table structure for table `cash_counter_logs`
--

CREATE TABLE `cash_counter_logs` (
  `id` int(11) NOT NULL,
  `date_time` datetime DEFAULT current_timestamp(),
  `c1000` int(11) DEFAULT 0,
  `c500` int(11) DEFAULT 0,
  `c200` int(11) DEFAULT 0,
  `c100` int(11) DEFAULT 0,
  `c50` int(11) DEFAULT 0,
  `c20` int(11) DEFAULT 0,
  `c10` int(11) DEFAULT 0,
  `c5` int(11) DEFAULT 0,
  `c1` int(11) DEFAULT 0,
  `grand_total` decimal(10,2) NOT NULL,
  `logged_by` varchar(50) NOT NULL,
  `gcash` decimal(10,2) DEFAULT 0.00
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `cash_counter_logs`
--

INSERT INTO `cash_counter_logs` (`id`, `date_time`, `c1000`, `c500`, `c200`, `c100`, `c50`, `c20`, `c10`, `c5`, `c1`, `grand_total`, `logged_by`, `gcash`) VALUES
(3, '2026-09-06 06:20:08', 7, 3, 3, 2, 1, 4, 3, 70, 100, '10074.00', 'dominic', '164.00');

-- --------------------------------------------------------

--
-- Table structure for table `expense_logs`
--

CREATE TABLE `expense_logs` (
  `id` int(11) NOT NULL,
  `date_time` datetime DEFAULT current_timestamp(),
  `item_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `logged_by` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `expense_logs`
--

INSERT INTO `expense_logs` (`id`, `date_time`, `item_name`, `amount`, `notes`, `logged_by`) VALUES
(2, '2026-09-06 06:19:39', 'trashbag', '150.00', '', 'dominic');

-- --------------------------------------------------------

--
-- Table structure for table `records`
--

CREATE TABLE `records` (
  `id` int(11) NOT NULL,
  `date_time` datetime DEFAULT current_timestamp(),
  `category` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `actual_balance` decimal(10,2) DEFAULT 0.00,
  `topup_added` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'staff',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(4, 'admin', '$2y$10$jNcB5oaXSRHohHK/XH0uK.zjA2edyDaY0s3b9y8KOngvSvBHIekl.', 'admin', '2026-09-06 01:24:20'),
(2, 'lance', '$2y$10$eN9anolFP7Fg91Q4NFPwKu5oUU/xzfM99PnVbcxtfTkeqEmAEWJQu', 'staff', '2026-09-06 01:24:20'),
(3, 'dominic', '$2y$10$u/MhekPhpFzMzShR/qTVVuMnV4M9Nu/lmyaezxNXh09RfyBWeXjIa', 'admin', '2026-09-06 01:24:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `balance_logs`
--
ALTER TABLE `balance_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cash_counter_logs`
--
ALTER TABLE `cash_counter_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense_logs`
--
ALTER TABLE `expense_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `records`
--
ALTER TABLE `records`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `balance_logs`
--
ALTER TABLE `balance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cash_counter_logs`
--
ALTER TABLE `cash_counter_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `expense_logs`
--
ALTER TABLE `expense_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `records`
--
ALTER TABLE `records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

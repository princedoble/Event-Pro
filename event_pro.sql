-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 17, 2026 at 05:09 AM
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
-- Database: `event_pro`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendee_id` int(255) NOT NULL,
  `participant_id` int(255) NOT NULL,
  `time_in` datetime NOT NULL DEFAULT current_timestamp(),
  `attendee_status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendee_id`, `participant_id`, `time_in`, `attendee_status`) VALUES
(12, 62, '2026-04-17 07:47:11', 'Present'),
(13, 63, '2026-04-17 07:49:29', 'Present'),
(14, 61, '2026-04-17 07:49:43', 'Present'),
(15, 67, '2026-04-17 07:55:11', 'Present'),
(16, 65, '2026-04-17 07:56:06', 'Present'),
(17, 66, '2026-04-17 07:56:11', 'Present'),
(18, 69, '2026-04-17 11:08:07', 'Present');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `max_capacity` int(11) DEFAULT 1000,
  `event_status` varchar(20) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `event_name`, `event_date`, `max_capacity`, `event_status`) VALUES
(18, 'Pickelball basic', '2026-05-09', 50, 'Active'),
(19, 'CPE Empowerment', '2026-05-08', 100, 'Active'),
(20, 'Basketball Seminar', '2026-04-30', 3, 'Ended'),
(21, 'Basketball Seminar 2', '2026-05-08', 5, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `participants`
--

CREATE TABLE `participants` (
  `participant_id` int(255) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `participant_qr` varchar(255) NOT NULL,
  `participant_email` varchar(255) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `participant_status` varchar(255) NOT NULL,
  `account_username` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `participants`
--

INSERT INTO `participants` (`participant_id`, `participant_name`, `participant_qr`, `participant_email`, `event_id`, `participant_status`, `account_username`) VALUES
(61, 'Bro Double', 'qrcodes/user_1776383054.png', 'jamzdoble@gmail.com', 19, 'Attended', 'bro'),
(62, 'Jameel Double', 'qrcodes/user_1776383073.png', 'jameel@gmail.com', 19, 'Attended', 'jameel'),
(63, 'Kirk PL', 'qrcodes/user_1776383098.png', 'kirk@gmail.com', 19, 'Attended', 'kirk'),
(64, 'Kirk PL', 'qrcodes/user_1776383431.png', 'kirk@gmail.com', 18, 'Pending', 'kirk'),
(65, 'Bro Double', 'qrcodes/user_1776383516.png', 'jamzdoble@gmail.com', 20, 'Completed', 'bro'),
(66, 'Jameel Double', 'qrcodes/user_1776383563.png', 'jameel@gmail.com', 20, 'Completed', 'jameel'),
(67, 'Kirk PL', 'qrcodes/user_1776383595.png', 'kirk@gmail.com', 20, 'Completed', 'kirk'),
(68, 'Telos Double', 'qrcodes/user_1776387649.png', 'telos@gmail.com', 18, 'Pending', 'telos'),
(69, 'Telos Double', 'qrcodes/user_1776394999.png', 'telos@gmail.com', 19, 'Attended', 'telos');

-- --------------------------------------------------------

--
-- Table structure for table `registration`
--

CREATE TABLE `registration` (
  `id` int(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `registration_email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` text NOT NULL,
  `role` text NOT NULL,
  `attempts` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration`
--

INSERT INTO `registration` (`id`, `username`, `registration_email`, `password`, `status`, `role`, `attempts`) VALUES
(1, 'admin', 'dobleprincejameel@gmail.com', '$2y$10$o0vTDBoGxn9di0SsC.54tOcl1/CLDXnAz8qPT4FhDBCRcQJ3I6aAa', 'active', 'admin', 0),
(11, 'bro', 'jamzdoble@gmail.com', '$2y$10$kZtwdVVwG2rMrpOIzV4FkuIbztHBAot9VlMK7uPVVYBda8adQTZGG', 'active', 'user', 0),
(19, 'kirk', 'kirk@gmail.com', '$2y$10$/lgraj3m/Mr210X62qfPs.pDgN31A8mxIiYXaryxYbn9ZS4qs5ZGm', 'active', 'user', 0),
(20, 'jameel', 'jameel@gmail.com', '$2y$10$itmLJce8a1jqhKickUFFA.dgX.j17/51Yzum6lrZgMLdZ52HwWPFm', 'active', 'user', 0),
(21, 'telos', 'telos@gmail.com', '$2y$10$5vN//zXlNA9UAZ2UbLU8PutVLkFomWgTXhlXPAXxlzeY438KIQYOC', 'active', 'user', 0),
(22, 'israel', 'israel@gmail.com', '$2y$10$dwblxEOBqKlURu.ao4XWJO2/b33ydB9hzmvW8BDcvfvqphZE5kzyS', 'active', 'user', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendee_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`participant_id`),
  ADD UNIQUE KEY `participant_email` (`participant_email`,`event_id`);

--
-- Indexes for table `registration`
--
ALTER TABLE `registration`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendee_id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `participants`
--
ALTER TABLE `participants`
  MODIFY `participant_id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `registration`
--
ALTER TABLE `registration`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

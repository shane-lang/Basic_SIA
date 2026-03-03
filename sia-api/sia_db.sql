-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 03, 2026 at 07:27 PM
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
-- Database: `sia_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `add_drop_requests`
--

CREATE TABLE `add_drop_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `request_type` enum('Add','Drop') NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrollment_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `add_drop_window`
--

CREATE TABLE `add_drop_window` (
  `id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `label` varchar(150) DEFAULT '',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `add_drop_window`
--

INSERT INTO `add_drop_window` (`id`, `start_date`, `end_date`, `label`, `is_active`, `created_by`, `created_at`) VALUES
(1, '2026-03-03 02:00:00', '2026-03-17 22:56:00', '1st sem ay 2026-2027', 1, NULL, '2026-03-03 14:56:59');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `date` date NOT NULL,
  `type` enum('enrollment','payment','school','department','system') DEFAULT 'school',
  `priority` enum('high','normal','low') DEFAULT 'normal',
  `icon` varchar(10) DEFAULT '?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `date`, `type`, `priority`, `icon`, `created_at`) VALUES
(1, 'Enrollment for 1st Semester AY 2026 is NOW OPEN', 'All students must complete their enrollment. Coordinate with your Academic Adviser for pre-enrollment requirements.', '2026-01-31', 'enrollment', 'high', '📋', '2026-02-01 00:52:41'),
(2, 'Tuition Fee Payment Deadline', 'Tuition fees must be paid within 30 days from enrollment. Submit your GCash or Cash payment proof through the portal.', '2026-01-31', 'payment', 'high', '💳', '2026-02-01 00:52:41'),
(3, 'Library Hours Extended', 'The university library is now open Monday–Saturday, 7:00 AM to 8:00 PM to accommodate students during enrollment.', '2026-01-28', 'school', 'normal', '🏫', '2026-02-01 00:52:41'),
(4, 'Grade Submission Portal Now Available', 'Faculty members may now submit grades through the SIA portal. Students can view their grades once submission is complete.', '2026-01-29', 'school', 'normal', '🏫', '2026-02-01 00:52:41'),
(5, 'System Maintenance — Every Sunday 12 AM–4 AM', 'The Student Information System undergoes weekly maintenance every Sunday.', '2026-01-29', 'system', 'normal', '⚙️', '2026-02-01 00:52:41');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_email` varchar(150) DEFAULT NULL,
  `user_role` varchar(30) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `user_email`, `user_role`, `action`, `target_type`, `target_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 119, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 18:17:38');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `credits` int(11) NOT NULL DEFAULT 3,
  `instructor` varchar(100) DEFAULT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `schedule` varchar(100) DEFAULT NULL,
  `day` varchar(50) DEFAULT NULL,
  `time` varchar(50) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 40,
  `enrolled_count` int(11) DEFAULT 0,
  `semester` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT '1st Year',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_lab` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `instructor`, `faculty_id`, `schedule`, `day`, `time`, `room`, `capacity`, `enrolled_count`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `is_lab`) VALUES
(550, 'GE100', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(551, 'GE105', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(552, 'BME100', 'International Business and Trade', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(553, 'GE108', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(554, 'AEC111', 'Financial Accounting and Reporting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(555, 'AEC109', 'Managerial Economics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 2, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(556, 'BSNA102', 'Organization and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(557, 'PE1-BSA', 'Physical Education 1 (Aquatics)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(558, 'NSTP1-BSA', 'NSTP 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(559, 'AEC112', 'Conceptual Framework and Accounting Standards', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC111', 'Business', '2-Yrs. Tourism, Hotel and Restaurant Operations', '1st Year', '2026-03-03 01:44:07', 0),
(560, 'GE101', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(561, 'GE109', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(562, 'AEC120', 'Cost Accounting and Control', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC111', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(563, 'BSNA101', 'Fundamentals of Accountancy, Business and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(564, 'AEC113', 'Intermediate Accounting 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC111', 'Business', '2-Yrs. Tourism, Hotel and Restaurant Operations', '1st Year', '2026-03-03 01:44:07', 0),
(565, 'BME101', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BME100', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(566, 'PE2-BSA', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(567, 'NSTP2-BSA', 'NSTP 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '1st Year', '2026-03-03 01:44:07', 0),
(568, 'BSNA103', 'Business Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(569, 'AEC121', 'Strategic Cost Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC120', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(570, 'AEC108', 'Governance, Business Ethics, Risk Management and Internal Control', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(571, 'AEC116', 'Financial Markets', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(572, 'BME103', 'Law on Obligations and Contracts', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(573, 'AEC107', 'Statistical Analysis and Software Application', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', '2-Yrs. Tourism, Hotel and Restaurant Operations', '2nd Year', '2026-03-03 01:44:07', 0),
(574, 'AEC105', 'Intermediate Accounting 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC113', 'Business', '2-Yrs. Tourism, Hotel and Restaurant Operations', '2nd Year', '2026-03-03 01:44:07', 0),
(575, 'AEC117', 'Financial Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC109', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(576, 'PE3-BSA', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(577, 'GE103', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(578, 'AEC101', 'Business Laws and Regulations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BME103', 'Business', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(579, 'AEC115', 'Intermediate Accounting 3', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC105', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(580, 'AEC118', 'Accounting Information System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC112', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(581, 'AEC124', 'Income Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC101', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(582, 'GE116', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(583, 'BSNA104', 'Business Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(584, 'GE110', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(585, 'PE4-BSA', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '2nd Year', '2026-03-03 01:44:07', 0),
(586, 'GE104', 'Readings in the Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(587, 'AEC103', 'Management Science', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'Business', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(588, 'AEC119', 'IT Application Tools in Business', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC118', 'Business', '2-Yrs. Tourism, Hotel and Restaurant Operations', '3rd Year', '2026-03-03 01:44:07', 0),
(589, 'AEC122', 'Strategic Business Analysis', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC117', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(590, 'AEC123', 'Business Tax', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC101', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(591, 'AEC110', 'Economic Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'Business', '2-Yrs. Tourism, Hotel and Restaurant Operations', '3rd Year', '2026-03-03 01:44:07', 0),
(592, 'AEC102', 'Regulatory Framework and Legal Issues in Business', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC101', 'Business', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(593, 'GE115', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(594, 'BME102', 'Operations Management and TQM', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BME100', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(595, 'GE106', 'Science, Technology and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(596, 'GE107', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(597, 'AEC104', 'Accounting Research Methods', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: 3rd Year Standing', 'Business', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(598, 'ELEC1-BSA', 'Updates in Financial Reporting and Standards (Elective 1)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(599, 'APE108', 'Accounting for Government and Non-profit Organizations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC108', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(600, 'APE107', 'Accounting for Business Combinations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All prior BME and AEC subjects', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(601, 'AEC114', 'Accounting Internship', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, 'Summer, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '3rd Year', '2026-03-03 01:44:07', 0),
(602, 'APE101', 'Auditing and Assurance Principles', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(603, 'APE102', 'Auditing and Assurance: Concepts and Applications 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(604, 'APE103', 'Auditing and Assurance: Concepts and Applications 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(605, 'AEC106', 'Accountancy Research', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC104', 'Business', '2-Yrs. Tourism, Hotel and Restaurant Operations', '4th Year', '2026-03-03 01:44:07', 0),
(606, 'APE106', 'Accounting for Special Transactions', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(607, 'APE109', 'Financial Accounting and Reporting Integration', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(608, 'APE111', 'Taxation Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(609, 'APE112', 'Regulatory Framework for Business Transactions Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(610, 'APE113', 'Management Advisory Services Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(611, 'APE104', 'Auditing and Assurance: Specialized Industries', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(612, 'APE105', 'Auditing in a CIS Environment', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'Business', 'BSA', '4th Year', '2026-03-03 01:44:07', 0),
(613, 'GE100-CA', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(614, 'GE105-CA', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(615, 'GE108-CA', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(616, 'GE104-CA', 'Readings in the Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(617, 'SCP101', 'Introduction to Supply Chain Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(618, 'BSNA102-CA', 'Organization and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(619, 'PE1-CA', 'PATHFit 1 (Movement Competency Training)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(620, 'NSTP1-CA', 'NSTP 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(621, 'GE103-CA', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(622, 'GE101-CA', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(623, 'GE109-CA', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(624, 'TMC100', 'Fundamentals of Customs and Tariff System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(625, 'SCP102', 'Warehouse Operations Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: SCP101', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(626, 'BSNA101-CA', 'Fundamentals of Accounting, Business and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(627, 'PE2-CA', 'PATHFit 2 (Exercise-Based Fitness Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(628, 'NSTP2-CA', 'NSTP 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '1st Year', '2026-03-03 01:44:07', 0),
(629, 'BLT100', 'Business Law (Obligations and Contracts, Negotiable Instruments Law, Intellectual Property Law and Insurance Law)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(630, 'CMC100', 'Border Control and Security', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC100', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(631, 'SCP103', 'Procurement and Inventory Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: SCP101', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(632, 'CMC101', 'Customs Operations and Cargo Handling', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC100', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(633, 'TMC101', 'Commodity Classification System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC100', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(634, 'TMC106', 'International Trade Organizations, Agreements and Rules of Origin', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC100', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(635, 'BSNA103-CA', 'Business Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(636, 'PE3-CA', 'PATHFit 3 (Group Exercise)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(637, 'BLT101', 'Taxation (Income and Business Taxation)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(638, 'GE116-CA', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(639, 'GE107-CA', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(640, 'SCP104', 'Transportation Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: SCP101', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(641, 'CMC102', 'Customs Warehousing', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: CMC101', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(642, 'TMC102', 'Customs Valuation System', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: TMC106', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(643, 'BSNA104-CA', 'Business Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(644, 'PE4-CA', 'PATHFit 4 (Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '2nd Year', '2026-03-03 01:44:07', 0),
(645, 'GE115-CA', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(646, 'GE110-CA', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(647, 'CMC106', 'Ethics and Standards of the Customs Broker', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(648, 'CMC103', 'Customs Clearance', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior CMC', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(649, 'TMC103', 'Customs Appraisal and Assessment', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC102', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(650, 'BME100-CA', 'Operations Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All BSNA', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(651, 'BME101-CA', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All BSNA', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(652, 'GE106-CA', 'Science, Technology and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(653, 'CMC105', 'Customs Post Clearance Audit and Fraud Detection', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior CMC', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(654, 'CMC104', 'Customs Proceedings', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior CMC', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(655, 'TMC105', 'Special Duties and Trade Remedies', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior TMC', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(656, 'TMC104', 'Excise Taxes, Liquidation of Duty and Surcharges', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior TMC', 'Business', 'BSCA', '3rd Year', '2026-03-03 01:44:07', 0),
(657, 'CMC107', 'Competency Assessment in Customs Management', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior CMC', 'Business', 'BSCA', '4th Year', '2026-03-03 01:44:07', 0),
(658, 'TMC107', 'Competency Assessment in Tariff Management', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior TMC', 'Business', 'BSCA', '4th Year', '2026-03-03 01:44:07', 0),
(659, 'RSH100', 'Research 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: 4th Year Standing', 'Business', 'BSCA', '4th Year', '2026-03-03 01:44:07', 0),
(660, 'RSH101', 'Research 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RSH100', 'Business', 'BSCA', '4th Year', '2026-03-03 01:44:07', 0),
(661, 'OJT100', 'Internship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: 4th Year Standing', 'Business', 'BSCA', '4th Year', '2026-03-03 01:44:07', 0),
(662, 'BME102-E', 'International Business and Trade', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(663, 'GE100-E', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(664, 'GE105-E', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(665, 'GE108-E', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(666, 'ECS101', 'Entrepreneurial Behavior', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(667, 'BSNA102-E', 'Organization and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(668, 'PE1-E', 'Physical Education 1 (Aquatics)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(669, 'NSTP1-E', 'NSTP 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(670, 'GE101-E', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(671, 'BSNA101-E', 'Fundamentals of Accounting, Business and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(672, 'ECS102', 'Opportunity Seeking', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(673, 'GE109-E', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(674, 'ECS108', 'Microeconomics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(675, 'PE2-E', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(676, 'NSTP2-E', 'NSTP 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '1st Year', '2026-03-03 01:44:07', 0),
(677, 'BME103-E', 'Human Resource Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(678, 'ECS107', 'Market Research and Consumer Behavior', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(679, 'ECS109', 'Business Law and Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(680, 'ECS114', 'Programs and Policies on Enterprise Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(681, 'BSNA103-E', 'Business Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(682, 'BME104', 'Basic Accounting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(683, 'PE3-E', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(684, 'GE103-E', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(685, 'GE116-E', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(686, 'ECS111', 'Pricing and Costing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(687, 'BSNA104-E', 'Business Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: ECS109', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(688, 'PE4-E', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'Business', 'BSE', '2nd Year', '2026-03-03 01:44:07', 0),
(689, 'GE104-E', 'Readings in the Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(690, 'GE110-E', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(691, 'BME100-E', 'Operations Management (Total Quality Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(692, 'GE115-E', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(693, 'EST101', 'Specialized Track 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS114', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(694, 'EEC101', 'Elective 1 (Supply Chain Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(695, 'ECS112', 'Innovation and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(696, 'GE106-E', 'Science, Technology and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(697, 'GE107-E', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(698, 'EST102', 'Specialized Track 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: EST101', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(699, 'EEC102', 'Elective 2 (E-Commerce)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(700, 'ECS103', 'Business Plan Preparation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: 3rd Year Standing', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(701, 'ECS110', 'Financial Management and Analysis for Decision Making', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA104', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(702, 'ECS113', 'Social Entrepreneurship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSE', '3rd Year', '2026-03-03 01:44:07', 0),
(703, 'BME101-E', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BME100', 'Business', 'BSE', '4th Year', '2026-03-03 01:44:07', 0),
(704, 'EST103', 'Specialized Track 3', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: EST102', 'Business', 'BSE', '4th Year', '2026-03-03 01:44:07', 0),
(705, 'EEC103', 'Elective 3 (Hospitality Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'Business', 'BSE', '4th Year', '2026-03-03 01:44:07', 0),
(706, 'ECS104', 'Business Plan Implementation 1 (Product Development and Market Analysis)', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: 4th Year Standing', 'Business', 'BSE', '4th Year', '2026-03-03 01:44:07', 0),
(707, 'EST104', 'Specialized Track 4', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: EST103', 'Business', 'BSE', '4th Year', '2026-03-03 01:44:07', 0),
(708, 'EEC104', 'Elective 4 (Managing a Service Enterprise)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'Business', 'BSE', '4th Year', '2026-03-03 01:44:07', 0),
(709, 'ECS105', 'Business Plan Implementation 2', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: 4th Year Standing', 'Business', 'BSE', '4th Year', '2026-03-03 01:44:07', 0),
(710, 'RE-FUN013', 'Fundamentals of Real Estate Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(711, 'GE-ENG013', 'Conversational English Competency', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(712, 'GE-FIL013', 'Komunikasyon Sa Akademikong Filipino', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(713, 'GE-MAT013', 'College Algebra - Math 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(714, 'RE-TAX013', 'Business and Real Estate Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(715, 'AC-TAX013', 'Economics with Taxation and Land Reform', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(716, 'GE-NSC013', 'Biological Science', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(717, 'RE-HGP013', 'Human and Physical Geography', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(718, 'GE-PHE012', 'Recreational Activities', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(719, 'GE-NST013', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(720, 'BN-MGT013', 'Principles of Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(721, 'RE-REC013', 'Fundamentals of Real Estate Consulting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-FUN013', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(722, 'LW-BSN013', 'Law on Obligations and Contracts with Real Properties', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(723, 'GE-NSC023', 'Environment and Greenbuilding Technology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-NSC013', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(724, 'GE-ENG023', 'Grammar and Composition', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-ENG013', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(725, 'RE-PAD013', 'Real Estate Planning and Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-REA013', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(726, 'RE-REB013', 'Real Estate Brokerage', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(727, 'GE-FIL023', 'Pagbasa at Pagsulat Tungo sa Pananaliksik', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-FIL013', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(728, 'GE-PHE032', 'Individual and Team Sports', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(729, 'GE-NST023', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-NST013', 'Business', 'BSREM', '1st Year', '2026-03-03 01:44:07', 0),
(730, 'BN-MKT013', 'Principles of Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-MGT013', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(731, 'RE-LAR013', 'Legal Aspects of Real Estate', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: LW-BSN013', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(732, 'GE-BAC013', 'Basic Accounting 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(733, 'RE-CSE013', 'Consulting for Specific Engagements', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: RE-REC013', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(734, 'BN-ECO013', 'Macroeconomics and Microeconomics Theory and Practice', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(735, 'RE-REA013', 'Real Estate Appraisal and Property Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: RE-PAD013', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(736, 'IT-CSA013', 'Computer Software Application', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(737, 'GE-ENG033', 'Business Correspondence and Technical Writing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: GE-ENG023', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(738, 'GE-PHE052', 'Rhythmic Activities', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(739, 'BN-FIN013', 'Basic Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(740, 'RE-MKB013', 'Real Estate Marketing and Brokerage', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-MKT013', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(741, 'RE-CIA013', 'Real Estate Consulting and Investments Analysis', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-REC013', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(742, 'RE-PVS013', 'Philippine Valuation Studies for Real Estate', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(743, 'GE-SCF013', 'Society and Culture with Family Planning', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(744, 'RE-POE013', 'Principles of Ecology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-NSC013', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(745, 'GE-PSY013', 'General Psychology with Drug Education, SARS, HIV/AIDS', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(746, 'GE-BAC023', 'Basic Accounting 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-BAC013', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(747, 'GE-PHE062', 'Sports and Games', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '2nd Year', '2026-03-03 01:44:07', 0),
(748, 'IT-DBM013', 'Database Management System 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: IT-CSA013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(749, 'RE-PM013', 'Property Management System 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-MGT013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(750, 'GE-GCR013', 'Good Governance and Corporate Responsibility', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-MGT013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(751, 'RE-HSD013', 'Housing and Subdivision Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: RE-PMS013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(752, 'GE-MAT053', 'Business Statistics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: GE-MAT013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(753, 'GE-LCT013', 'Logic and Critical Thinking', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(754, 'RE-AGS013', 'Appraisal/Assessment in Government Sector', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-FIN013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(755, 'RE-REF013', 'Real Estate Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-FIN013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(756, 'GE-PHC013', 'Philippine History and Culture', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(757, 'RE-ARD013', 'Appraisal Report and Data Gathering', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-PVS013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(758, 'RE-ESP013', 'Ethical Standards for Real Estate Practice', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-HBO013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(759, 'RE-REE013', 'Real Estate Economics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-ECO013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(760, 'RE-CCD013', 'Condominium Concept and other Specialized Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-PMS013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(761, 'BN-HRM013', 'Human Resource Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-HBO013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(762, 'GE-APA013', 'Appreciation of Arts', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(763, 'GE-LWR013', 'Life and Works of Rizal', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-PHC013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(764, 'BN-HBO013', 'Human Behavior in Organization', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-MGT013', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(765, 'GE-ENG053', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-ENG023', 'Business', 'BSREM', '3rd Year', '2026-03-03 01:44:07', 0),
(766, 'RE-INR015', 'Integration and Review for Real Estate', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Major Subjects', 'Business', 'BSREM', '4th Year', '2026-03-03 01:44:07', 0),
(767, 'GE-OJT013', 'On-the-Job Training (600 hours)', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Major Subjects', 'Business', 'BSREM', '4th Year', '2026-03-03 01:44:07', 0),
(768, 'CC100', 'Introduction to Computing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(769, 'CC101', 'Computer Programming 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(770, 'IT-CMT015', 'Computer Organization and Maintenance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(771, 'GE105-CIMT', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(772, 'GE100-CIMT', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(773, 'GE112', 'Pilipino: Retorika', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(774, 'PE1-CIMT', 'Physical Education 1 (Aquatic)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(775, 'NSTP1-CIMT', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(776, 'EMC200', 'Free Hand and Digital Drawing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(777, 'CC102', 'Computer Programming 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(778, 'GE113', 'Pilipino: Pagsasalingwika', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(779, 'GE101-CIMT', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0);
INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `instructor`, `faculty_id`, `schedule`, `day`, `time`, `room`, `capacity`, `enrolled_count`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `is_lab`) VALUES
(780, 'GE116-CIMT', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(781, 'GE109-CIMT', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(782, 'PE2-CIMT', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(783, 'NSTP2-CIMT', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0),
(784, 'CC103', 'Data Structures and Algorithms', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(785, 'CC105', 'Application Development and Emerging Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(786, 'IT105', 'Discrete Mathematics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(787, 'GE108-CIMT', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(788, 'ELEC400', 'Object Oriented Programming', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(789, 'GE110-CIMT', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(790, 'CAP501', 'Capstone Project', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(791, 'EMC203', 'Usability, HCI, and User Interaction Design', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(792, 'PE3-CIMT', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(793, 'CC104', 'Information Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(794, 'IT103', 'Fundamentals of Database Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(795, 'IT107', 'Networking 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(796, 'EMC202', 'Computer Graphics Programming', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(797, 'GE114', 'Pilipino: Tula, Sanaysay, Nobela', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(798, 'GE103-CIMT', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(799, 'OJT-CIMT', 'Internship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(800, 'EMC204', 'Principles of 2D Animation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(801, 'PE4-CIMT', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(802, 'CC100-IT', 'Introduction to Computing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(803, 'CC101-IT', 'Computer Programming 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(804, 'IT-CMT015-IT', 'Computer Organization and Maintenance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 5, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(805, 'GE105-IT', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(806, 'GE100-IT', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 5, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(807, 'PE1-IT', 'Physical Education 1 (Aquatic)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(808, 'NSTP1-IT', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(809, 'IT100', 'Introduction to Human Computer Interaction', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(810, 'CC102-IT', 'Computer Programming 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(811, 'IS103', 'IT Infrastructure and Network Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(812, 'GE101-IT', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(813, 'GE109-IT', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(814, 'PE2-IT', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(815, 'NSTP2-IT', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0),
(816, 'CC103-IT', 'Data Structures and Algorithms', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(817, 'CC105-IT', 'Application Development and Emerging Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(818, 'IT105-IT', 'Discrete Mathematics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(819, 'GE108-IT', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(820, 'ELEC400-IT', 'Object-Oriented Programming', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(821, 'GE110-IT', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(822, 'EMC203-IT', 'Usability, HCI, and User Interaction Design', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(823, 'PE3-IT', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(824, 'CC104-IT', 'Information Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(825, 'IT103-IT', 'Fundamentals of Database Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(826, 'IT107-IT', 'Networking 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(827, 'GE103-IT', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(828, 'GE116-IT', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(829, 'PE4-IT', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0),
(830, 'IT104', 'Integrative Programming and Technologies 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(831, 'IT101', 'Information Assurance and Security 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(832, 'IT108', 'Networking 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(833, 'ELEC401', 'Multimedia Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(834, 'IT106', 'Quantitative Methods (including Modelling and Simulation)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(835, 'GE115-IT', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(836, 'EMC207', 'Principles of 3D Animation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(837, 'GE111', 'Social and Professional Issues', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(838, 'IT102', 'Information Assurance and Security 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(839, 'IT110', 'System Integration and Architecture 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(840, 'ELEC103', 'Platform Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(841, 'GE104-IT', 'Readings in Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(842, 'GE106-IT', 'Science, Technology, and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(843, 'GE107-IT', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0),
(844, 'IT109', 'System Administration and Maintenance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 0),
(845, 'DM101', 'Organization and Management Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 0),
(846, 'ELEC403', 'Web Systems and Technology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 0),
(847, 'CAP501-IT', 'Capstone Project', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 0),
(848, 'OJT-BSIT', 'Internship (486 hours)', 9, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'Information Technology', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 0);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrollment_date` date DEFAULT NULL,
  `status` enum('Pending','Enrolled','Completed','Dropped') DEFAULT 'Pending',
  `grade` varchar(5) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `prelim_grade` decimal(4,2) DEFAULT NULL,
  `midterm_grade` decimal(4,2) DEFAULT NULL,
  `final_grade` decimal(4,2) DEFAULT NULL,
  `overall_grade` decimal(4,2) DEFAULT NULL,
  `remarks` varchar(20) DEFAULT 'In Progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_permits`
--

CREATE TABLE `exam_permits` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_period` enum('Prelim','Midterm','Finals') NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `semester` varchar(30) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `id` int(11) NOT NULL,
  `faculty_id` varchar(20) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(150) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `specialty` varchar(150) DEFAULT NULL,
  `subjects` longtext DEFAULT '[]' COMMENT 'JSON array of subject names',
  `status` enum('Active','Inactive','On Leave') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`id`, `faculty_id`, `first_name`, `last_name`, `email`, `department`, `specialty`, `subjects`, `status`, `created_at`) VALUES
(1, 'FAC-2024-001', 'Maria', 'Santos', 'maria.santos@school.edu', 'Information Technology', 'Web Development', '[\"CS112\",\"CS111\",\"IT101\"]', 'Active', '2026-02-01 00:03:41'),
(2, 'FAC-2024-002', 'Juan', 'Reyes', 'juan.reyes@school.edu', 'Information Technology', 'Database Systems', '[\"CS113\",\"CS114\"]', 'Active', '2026-02-01 00:03:41'),
(3, 'FAC-2024-003', 'Anna', 'Garcia', 'anna.garcia@school.edu', 'Mathematics', 'Discrete Mathematics', '[\"MATH101\",\"MATH201\"]', 'Active', '2026-02-01 00:03:41'),
(4, 'FAC-2024-004', 'Luis', 'Rodriguez', 'luis.rodriguez@school.edu', 'Information Technology', 'Software Engineering', '[\"CS115\",\"CS116\"]', 'Active', '2026-02-01 00:03:41'),
(5, 'FAC-2024-005', 'Sarah', 'Kim', 'sarah.kim@school.edu', 'English', 'Technical Writing', '[\"ENG101\"]', 'Active', '2026-02-01 00:03:41'),
(7, 'FAC-2026-001', 'shane', 'binoya', 'shanecarlobinoya@gmail.com', 'Information Technology', 'ai', '[\"CS111\",\"IT104\",\"IT102\"]', 'Active', '2026-03-02 10:07:26');

-- --------------------------------------------------------

--
-- Table structure for table `installment_payments`
--

CREATE TABLE `installment_payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `payment_log_id` int(11) DEFAULT NULL,
  `or_ar_number` varchar(30) NOT NULL COMMENT 'Auto-generated e.g. AR-20260001',
  `or_ar_type` enum('OR','AR') NOT NULL DEFAULT 'AR',
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'Cash',
  `gcash_reference` varchar(100) DEFAULT NULL,
  `exam_period` enum('Downpayment','Prelim','Midterm','Finals','Full') NOT NULL DEFAULT 'Downpayment',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL COMMENT 'Accounting user id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `or_ar_sequences`
--

CREATE TABLE `or_ar_sequences` (
  `year` int(11) NOT NULL,
  `last_seq` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `or_ar_sequences`
--

INSERT INTO `or_ar_sequences` (`year`, `last_seq`) VALUES
(2026, 7);

-- --------------------------------------------------------

--
-- Table structure for table `payment_logs`
--

CREATE TABLE `payment_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'GCash',
  `gcash_reference` varchar(100) NOT NULL,
  `gcash_amount` decimal(10,2) NOT NULL,
  `gcash_date` date DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_scholar` tinyint(1) DEFAULT 0,
  `scholar_type` varchar(100) DEFAULT NULL,
  `scholar_grantor` varchar(150) DEFAULT NULL,
  `scholarship_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_notices`
--

CREATE TABLE `payment_notices` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_period` enum('Prelim','Midterm','Finals') NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sent_by` int(11) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_schedules`
--

CREATE TABLE `payment_schedules` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `payment_type` enum('full','installment') NOT NULL DEFAULT 'installment',
  `total_assessment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `downpayment_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prelim_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `midterm_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `finals_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prelim_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `midterm_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `finals_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prelim_status` enum('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
  `prelim_unlocked_at` timestamp NULL DEFAULT NULL,
  `midterm_status` enum('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
  `midterm_unlocked_at` timestamp NULL DEFAULT NULL,
  `finals_status` enum('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
  `finals_unlocked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `downpayment_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `downpayment_status` enum('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
  `downpayment_unlocked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `code` varchar(30) NOT NULL,
  `level_type` enum('College','SHS','TVET') DEFAULT 'College',
  `duration` int(2) DEFAULT 4 COMMENT 'Years (College) or 2 (SHS)',
  `description` text DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `name`, `code`, `level_type`, `duration`, `description`, `department`, `created_at`) VALUES
(1, 'Bachelor of Science in Accountancy', 'BSA', 'College', 4, 'A professional program covering financial accounting, auditing, taxation, and management advisory services.', 'Business', '2026-03-03 01:44:06'),
(2, 'Bachelor of Science in Customs Administration', 'BSCA', 'College', 4, 'A program focused on customs brokerage, tariff, trade, and border control management.', 'Business', '2026-03-03 01:44:06'),
(3, 'Bachelor of Science in Entrepreneurship', 'BSE', 'College', 4, 'A program developing entrepreneurial skills, business planning, and enterprise management.', 'Business', '2026-03-03 01:44:06'),
(4, 'Bachelor of Science in Real Estate Management', 'BSREM', 'College', 4, 'A program covering real estate appraisal, brokerage, property management, and real estate finance.', 'Business', '2026-03-03 01:44:06'),
(5, 'Computer Information Multimedia Technology', 'CIMT', 'College', 2, 'A 2-year program in computing, multimedia, and digital arts technology.', 'ICTD', '2026-03-03 01:44:06'),
(6, 'Bachelor of Science in Information Technology', 'BSIT', 'College', 4, 'A program in software development, networking, database systems, and information assurance.', 'ICTD', '2026-03-03 01:44:06'),
(23, 'Accountancy, Business and Management', 'ABM', 'SHS', 2, 'SHS strand focusing on business, accounting, economics, and management principles.', 'Academic Track', '2026-03-03 01:48:50'),
(24, 'General Academic Strand', 'GAS', 'SHS', 2, 'SHS strand offering a broad general academic curriculum for undecided learners.', 'Academic Track', '2026-03-03 01:48:50'),
(25, 'Humanities and Social Sciences Strand', 'HUMSS', 'SHS', 2, 'SHS strand focusing on humanities, social sciences, and communication arts.', 'Academic Track', '2026-03-03 01:48:50'),
(26, 'Information and Communication Technology', 'ICT', 'SHS', 2, 'SHS TVL strand focused on computer and information technology skills.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50'),
(27, 'Computer Systems Servicing NCII', 'CSS-NCII', 'SHS', 2, 'SHS TVL strand with TESDA National Certificate II in Computer Systems Servicing.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50'),
(28, 'Cookery NCII', 'COOKERY-NCII', 'SHS', 2, 'SHS Home Economics strand with TESDA National Certificate II in Cookery.', 'Home Economics', '2026-03-03 01:48:50'),
(29, 'Bread and Pastry Production NCII', 'BPP-NCII', 'SHS', 2, 'SHS Home Economics strand with TESDA National Certificate II in Bread and Pastry Production.', 'Home Economics', '2026-03-03 01:48:50'),
(30, 'Food and Beverages Services NCII', 'FBS-NCII-SHS', 'SHS', 2, 'SHS Home Economics strand with TESDA National Certificate II in Food and Beverages Services.', 'Home Economics', '2026-03-03 01:48:50'),
(31, 'Diploma in Travel and Tourism Technology (Leading to BSTM)', 'DTTT', 'TVET', 2, 'A diploma program in travel and tourism technology that may lead to a BSTM degree.', 'TVET', '2026-03-03 01:48:50'),
(32, '2-Yrs. Computer Information and Multimedia Technology', 'CIMT-TVET', 'TVET', 2, 'Two-year TVET program in computer information and multimedia technology.', 'TVET', '2026-03-03 01:48:50'),
(33, '2-Yrs. Cruise Ship Management', 'CSM', 'TVET', 2, 'Two-year TVET program in cruise ship operations and hospitality management.', 'TVET', '2026-03-03 01:48:50'),
(34, '2-Yrs. Tourism, Hotel and Restaurant Operations', 'THRO', 'TVET', 2, 'Two-year TVET program in tourism, hotel, and restaurant operations.', 'TVET', '2026-03-03 01:48:50'),
(35, 'Housekeeping NCII', 'HK-NCII', 'TVET', 1, 'TESDA National Certificate II program in Housekeeping.', 'TVET', '2026-03-03 01:48:50'),
(36, 'Bartending NCII', 'BART-NCII', 'TVET', 1, 'TESDA National Certificate II program in Bartending.', 'TVET', '2026-03-03 01:48:50'),
(37, 'Food and Beverages Services NCII', 'FBS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Food and Beverages Services.', 'TVET', '2026-03-03 01:48:50'),
(38, 'Front Office NCII', 'FO-NCII', 'TVET', 1, 'TESDA National Certificate II program in Front Office services.', 'TVET', '2026-03-03 01:48:50'),
(39, '3D Animation NCIII', '3DA-NCIII', 'TVET', 1, 'TESDA National Certificate III program in 3D Animation.', 'TVET', '2026-03-03 01:48:50'),
(40, 'Game Programming NCIII', 'GP-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Game Programming.', 'TVET', '2026-03-03 01:48:50'),
(41, 'Computer Systems Servicing NCII', 'CSS-NCII-TVET', 'TVET', 1, 'TESDA National Certificate II program in Computer Systems Servicing.', 'TVET', '2026-03-03 01:48:50'),
(42, 'Visual Graphic Design NCIII', 'VGD-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Visual Graphic Design.', 'TVET', '2026-03-03 01:48:50'),
(43, 'Travel Services NCII', 'TS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Travel Services.', 'TVET', '2026-03-03 01:48:50'),
(44, 'Tourism Promotion Services NCII', 'TPS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Tourism Promotion Services.', 'TVET', '2026-03-03 01:48:50'),
(45, 'Event Management Services NCIII', 'EMS-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Event Management Services.', 'TVET', '2026-03-03 01:48:50'),
(84, 'Information and Communication Technology', 'ICT-SHS', 'SHS', 2, 'SHS TVL strand focused on ICT skills.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:59:11'),
(85, 'Computer Systems Servicing NCII', 'CSS-NCII-SHS', 'SHS', 2, 'SHS TVL strand with TESDA NCII in Computer Systems Servicing.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:59:11');

-- --------------------------------------------------------

--
-- Table structure for table `program_courses`
--

CREATE TABLE `program_courses` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program_courses`
--

INSERT INTO `program_courses` (`id`, `program_id`, `course_id`) VALUES
(236, 1, 550),
(237, 1, 551),
(238, 1, 552),
(239, 1, 553),
(242, 1, 556),
(243, 1, 557),
(244, 1, 558),
(245, 1, 559),
(246, 1, 560),
(247, 1, 561),
(248, 1, 562),
(249, 1, 563),
(250, 1, 564),
(251, 1, 565),
(252, 1, 566),
(253, 1, 567),
(254, 1, 568),
(255, 1, 569),
(256, 1, 570),
(257, 1, 571),
(258, 1, 572),
(259, 1, 573),
(260, 1, 574),
(261, 1, 575),
(262, 1, 576),
(263, 1, 577),
(265, 1, 579),
(266, 1, 580),
(267, 1, 581),
(268, 1, 582),
(269, 1, 583),
(270, 1, 584),
(271, 1, 585),
(272, 1, 586),
(274, 1, 588),
(275, 1, 589),
(276, 1, 590),
(277, 1, 591),
(279, 1, 593),
(280, 1, 594),
(281, 1, 595),
(282, 1, 596),
(283, 1, 598),
(284, 1, 599),
(285, 1, 600),
(286, 1, 601),
(287, 1, 602),
(288, 1, 603),
(289, 1, 604),
(290, 1, 605),
(291, 1, 606),
(292, 1, 607),
(293, 1, 608),
(294, 1, 609),
(295, 1, 610),
(296, 1, 611),
(297, 1, 612),
(298, 2, 613),
(299, 2, 614),
(300, 2, 615),
(301, 2, 616),
(302, 2, 617),
(303, 2, 618),
(304, 2, 619),
(305, 2, 620),
(306, 2, 621),
(307, 2, 622),
(308, 2, 623),
(309, 2, 624),
(310, 2, 625),
(311, 2, 626),
(312, 2, 627),
(313, 2, 628),
(314, 2, 629),
(315, 2, 630),
(316, 2, 631),
(317, 2, 632),
(318, 2, 633),
(319, 2, 634),
(320, 2, 635),
(321, 2, 636),
(322, 2, 637),
(323, 2, 638),
(324, 2, 639),
(325, 2, 640),
(326, 2, 641),
(327, 2, 642),
(328, 2, 643),
(329, 2, 644),
(330, 2, 645),
(331, 2, 646),
(332, 2, 647),
(333, 2, 648),
(334, 2, 649),
(335, 2, 650),
(336, 2, 651),
(337, 2, 652),
(338, 2, 653),
(339, 2, 654),
(340, 2, 655),
(341, 2, 656),
(342, 2, 657),
(343, 2, 658),
(344, 2, 659),
(345, 2, 660),
(346, 2, 661),
(347, 3, 662),
(348, 3, 663),
(349, 3, 664),
(350, 3, 665),
(351, 3, 666),
(352, 3, 667),
(353, 3, 668),
(354, 3, 669),
(355, 3, 670),
(356, 3, 671),
(357, 3, 672),
(358, 3, 673),
(359, 3, 674),
(360, 3, 675),
(361, 3, 676),
(362, 3, 677),
(363, 3, 678),
(364, 3, 679),
(365, 3, 680),
(366, 3, 681),
(367, 3, 682),
(368, 3, 683),
(369, 3, 684),
(370, 3, 685),
(371, 3, 686),
(372, 3, 687),
(373, 3, 688),
(374, 3, 689),
(375, 3, 690),
(376, 3, 691),
(377, 3, 692),
(378, 3, 693),
(379, 3, 694),
(380, 3, 695),
(381, 3, 696),
(382, 3, 697),
(383, 3, 698),
(384, 3, 699),
(385, 3, 700),
(386, 3, 701),
(387, 3, 702),
(388, 3, 703),
(389, 3, 704),
(390, 3, 705),
(391, 3, 706),
(392, 3, 707),
(393, 3, 708),
(394, 3, 709),
(395, 4, 710),
(396, 4, 711),
(397, 4, 712),
(398, 4, 713),
(399, 4, 714),
(400, 4, 715),
(401, 4, 716),
(402, 4, 717),
(403, 4, 718),
(404, 4, 719),
(405, 4, 720),
(406, 4, 721),
(407, 4, 722),
(408, 4, 723),
(409, 4, 724),
(410, 4, 725),
(411, 4, 726),
(412, 4, 727),
(413, 4, 728),
(414, 4, 729),
(415, 4, 730),
(416, 4, 731),
(417, 4, 732),
(418, 4, 733),
(419, 4, 734),
(420, 4, 735),
(421, 4, 736),
(422, 4, 737),
(423, 4, 738),
(424, 4, 739),
(425, 4, 740),
(426, 4, 741),
(427, 4, 742),
(428, 4, 743),
(429, 4, 744),
(430, 4, 745),
(431, 4, 746),
(432, 4, 747),
(433, 4, 748),
(434, 4, 749),
(435, 4, 750),
(436, 4, 751),
(437, 4, 752),
(438, 4, 753),
(439, 4, 754),
(440, 4, 755),
(441, 4, 756),
(442, 4, 757),
(443, 4, 758),
(444, 4, 759),
(445, 4, 760),
(446, 4, 761),
(447, 4, 762),
(448, 4, 763),
(449, 4, 764),
(450, 4, 765),
(451, 4, 766),
(452, 4, 767),
(809, 5, 768),
(810, 5, 769),
(811, 5, 770),
(812, 5, 771),
(813, 5, 772),
(814, 5, 773),
(815, 5, 774),
(816, 5, 775),
(817, 5, 776),
(818, 5, 777),
(819, 5, 778),
(820, 5, 779),
(821, 5, 780),
(822, 5, 781),
(823, 5, 782),
(824, 5, 783),
(825, 5, 784),
(826, 5, 785),
(827, 5, 786),
(828, 5, 787),
(829, 5, 788),
(830, 5, 789),
(831, 5, 790),
(832, 5, 791),
(833, 5, 792),
(834, 5, 793),
(835, 5, 794),
(836, 5, 795),
(837, 5, 796),
(838, 5, 797),
(839, 5, 798),
(840, 5, 799),
(841, 5, 800),
(842, 5, 801),
(756, 6, 554),
(757, 6, 555),
(758, 6, 578),
(759, 6, 587),
(760, 6, 592),
(761, 6, 597),
(762, 6, 802),
(763, 6, 803),
(764, 6, 804),
(765, 6, 805),
(766, 6, 806),
(767, 6, 807),
(768, 6, 808),
(769, 6, 809),
(770, 6, 810),
(771, 6, 811),
(772, 6, 812),
(773, 6, 813),
(774, 6, 814),
(775, 6, 815),
(776, 6, 816),
(777, 6, 817),
(778, 6, 818),
(779, 6, 819),
(780, 6, 820),
(781, 6, 821),
(782, 6, 822),
(783, 6, 823),
(784, 6, 824),
(785, 6, 825),
(786, 6, 826),
(787, 6, 827),
(788, 6, 828),
(789, 6, 829),
(790, 6, 830),
(791, 6, 831),
(792, 6, 832),
(793, 6, 833),
(794, 6, 834),
(795, 6, 835),
(796, 6, 836),
(797, 6, 837),
(798, 6, 838),
(799, 6, 839),
(800, 6, 840),
(801, 6, 841),
(802, 6, 842),
(803, 6, 843),
(804, 6, 844),
(805, 6, 845),
(806, 6, 846),
(807, 6, 847),
(808, 6, 848),
(753, 34, 559),
(754, 34, 564),
(751, 34, 573),
(749, 34, 574),
(755, 34, 588),
(752, 34, 591),
(750, 34, 605);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `capacity` int(4) DEFAULT 40,
  `room_type` enum('Classroom','Laboratory','Lecture Hall','Conference Room','Gymnasium') DEFAULT 'Classroom',
  `status` enum('Available','Occupied','Under Maintenance') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `building`, `capacity`, `room_type`, `status`, `created_at`) VALUES
(1, 'Room 101', 'Liberal Arts Building', 45, 'Classroom', 'Available', '2026-02-01 00:11:11'),
(2, 'Room 102', 'Liberal Arts Building', 45, 'Classroom', 'Available', '2026-02-01 00:11:11'),
(3, 'Room 103', 'Liberal Arts Building', 45, 'Classroom', 'Available', '2026-02-01 00:11:11'),
(4, 'Room 205', 'Science Building', 40, 'Classroom', 'Available', '2026-02-01 00:11:11'),
(5, 'Room 301', 'Science Building', 51, 'Classroom', 'Available', '2026-02-01 00:11:11'),
(6, 'Room 301', 'IT Building', 40, 'Laboratory', 'Available', '2026-02-01 00:11:11'),
(7, 'Room 401', 'IT Building', 40, 'Classroom', 'Available', '2026-02-01 00:11:11'),
(8, 'Lab 101', 'IT Building', 35, 'Laboratory', 'Available', '2026-02-01 00:11:11'),
(9, 'Lab 202', 'IT Building', 35, 'Laboratory', 'Available', '2026-02-01 00:11:11'),
(10, 'Lecture Hall A', 'Main Building', 100, 'Lecture Hall', 'Available', '2026-02-01 00:11:11'),
(11, 'Conference Room 1', 'Admin Building', 20, 'Conference Room', 'Available', '2026-02-01 00:11:11'),
(12, 'Room 301 Lab', 'IT Building', 40, 'Laboratory', 'Available', '2026-03-01 13:05:21');

-- --------------------------------------------------------

--
-- Table structure for table `school_events`
--

CREATE TABLE `school_events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `type` enum('enrollment','payment','exam','activity','holiday') DEFAULT 'activity',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_events`
--

INSERT INTO `school_events` (`id`, `title`, `event_date`, `type`, `description`, `created_at`) VALUES
(1, 'Enrollment Period Opens', '2026-01-20', 'enrollment', '1st Semester enrollment starts', '2026-02-01 00:52:41'),
(2, 'Enrollment Deadline', '2026-01-31', 'enrollment', 'Last day to enroll without late penalty', '2026-02-01 00:52:41'),
(3, 'Tuition Payment Deadline', '2026-02-28', 'payment', 'Pay tuition to avoid holds on your account', '2026-02-01 00:52:41'),
(4, 'University Sports Fest', '2026-02-14', 'activity', 'Annual inter-department sports festival', '2026-02-01 00:52:41'),
(5, 'Midterm Examinations', '2026-03-10', 'exam', 'Midterm exam week begins — all departments', '2026-02-01 00:52:41'),
(6, 'Midterm Exams End', '2026-03-14', 'exam', 'Last day of midterm examinations', '2026-02-01 00:52:41'),
(7, 'Foundation Day (No Classes)', '2026-03-25', 'holiday', 'University Foundation Day — school holiday', '2026-02-01 00:52:41'),
(8, 'Araw ng Kagitingan', '2026-04-09', 'holiday', 'Day of Valor — national holiday', '2026-02-01 00:52:41'),
(9, 'Holy Thursday', '2026-04-17', 'holiday', 'Holy Week — school suspended', '2026-02-01 00:52:41'),
(10, 'Good Friday', '2026-04-18', 'holiday', 'Holy Week — school suspended', '2026-02-01 00:52:41'),
(11, 'Final Examinations Begin', '2026-05-05', 'exam', 'Final examination period starts', '2026-02-01 00:52:41'),
(12, 'Final Examinations End', '2026-05-09', 'exam', 'Last day of final examinations', '2026-02-01 00:52:41'),
(13, 'Official Grades Released', '2026-05-20', 'activity', 'Final grades viewable via student portal', '2026-02-01 00:52:41'),
(14, 'Enrollment — 2nd Semester', '2026-06-01', 'enrollment', 'Enrollment opens for 2nd Semester', '2026-02-01 00:52:41'),
(15, 'Independence Day', '2026-06-12', 'holiday', 'Philippine Independence Day — no classes', '2026-02-01 00:52:41');

-- --------------------------------------------------------

--
-- Table structure for table `seed_flags`
--

CREATE TABLE `seed_flags` (
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seed_flags`
--

INSERT INTO `seed_flags` (`name`) VALUES
('program_courses');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'student',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `lrn_no` varchar(30) DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `age` tinyint(4) DEFAULT NULL,
  `place_of_birth` varchar(150) DEFAULT NULL,
  `citizenship` varchar(100) DEFAULT NULL,
  `mother_tongue` varchar(100) DEFAULT NULL,
  `is_indigenous` tinyint(1) DEFAULT 0,
  `has_special_needs` tinyint(1) DEFAULT 0,
  `special_needs_details` varchar(255) DEFAULT NULL,
  `has_assistive_tech` tinyint(1) DEFAULT 0,
  `assistive_tech_details` varchar(255) DEFAULT NULL,
  `strand` varchar(150) DEFAULT NULL,
  `learning_delivery` varchar(100) DEFAULT NULL,
  `last_school_attended` varchar(255) DEFAULT NULL,
  `psa_birth_cert_no` varchar(100) DEFAULT NULL,
  `guardian_name` varchar(150) DEFAULT NULL,
  `guardian_address` text DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT '1st Year',
  `gpa` decimal(4,2) DEFAULT 0.00,
  `enrollment_status` enum('Pending','Enrolled','Completed','Dropped') DEFAULT 'Pending',
  `student_type` enum('New','Old','Continuing','Returning','Transferee') DEFAULT 'New',
  `tor_eval_status` enum('NotRequired','Pending','Evaluated','Rejected') NOT NULL DEFAULT 'NotRequired',
  `student_category` varchar(20) DEFAULT 'College',
  `payment_status` enum('Pending','Paid','Overdue') DEFAULT 'Pending',
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `payment_method` varchar(20) NOT NULL DEFAULT 'GCash',
  `payment_plan` enum('full','installment') NOT NULL DEFAULT 'full',
  `semester` varchar(100) DEFAULT '',
  `is_scholar` tinyint(1) DEFAULT 0,
  `scholar_type` varchar(100) DEFAULT NULL,
  `scholar_grantor` varchar(150) DEFAULT NULL,
  `scholarship_amount` decimal(10,2) DEFAULT 0.00,
  `gcash_reference` varchar(100) DEFAULT NULL,
  `gcash_amount` decimal(10,2) DEFAULT NULL,
  `gcash_date` date DEFAULT NULL,
  `gcash_transaction_id` varchar(100) DEFAULT NULL,
  `accounting_approved_by` int(11) DEFAULT NULL,
  `accounting_approved_at` timestamp NULL DEFAULT NULL,
  `accounting_notes` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `tor_file` varchar(255) DEFAULT NULL,
  `psa_file` varchar(255) DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `guardian_contact` varchar(50) DEFAULT '',
  `tvet_type` varchar(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_grades`
--

CREATE TABLE `student_grades` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `semester` varchar(100) DEFAULT '',
  `term` enum('Prelim','Midterm','Final') NOT NULL,
  `grade` decimal(4,2) DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `term_payments`
--

CREATE TABLE `term_payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `term` enum('Prelim','Midterm','Finals') NOT NULL,
  `amount_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `status` enum('Unpaid','Partial','Paid') NOT NULL DEFAULT 'Unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tor_evaluations`
--

CREATE TABLE `tor_evaluations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('Pending','Evaluated','Rejected') NOT NULL DEFAULT 'Pending',
  `credited_units` int(11) NOT NULL DEFAULT 0 COMMENT 'Total units credited from previous school',
  `approved_units` int(11) NOT NULL DEFAULT 0 COMMENT 'Units student still needs to take this sem (program units - credited_units)',
  `credited_subjects` text DEFAULT NULL COMMENT 'JSON array: [{ courseId, code, name, credits, creditedFrom }]',
  `credited_course_ids` text DEFAULT NULL COMMENT 'JSON int array of credited course IDs for fast enrollment skip e.g. [18,22,24]',
  `registrar_notes` text DEFAULT NULL,
  `evaluated_by` int(11) DEFAULT NULL COMMENT 'users.id of registrar who evaluated',
  `evaluated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tuition_fees`
--

CREATE TABLE `tuition_fees` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `units` int(11) NOT NULL DEFAULT 18,
  `tuition_fee` decimal(10,2) NOT NULL COMMENT 'units x 650',
  `miscellaneous_fee` decimal(10,2) NOT NULL DEFAULT 6688.00,
  `registration_fee` decimal(10,2) NOT NULL DEFAULT 700.00,
  `laboratory_fee` decimal(10,2) NOT NULL COMMENT 'units x 1900',
  `energy_fee` decimal(10,2) NOT NULL COMMENT 'units x 21 x 3',
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Scholar discount',
  `installment_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '750 if installment',
  `total_assessment` decimal(10,2) NOT NULL COMMENT 'Final Assessment = Subtotal - Discount + Installment Fee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','admin','accounting','registrar','faculty') NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `first_name`, `last_name`, `created_at`) VALUES
(1, 'student@example.com', 'password123', 'student', 'Juan', 'Dela Cruz', '2026-01-29 07:51:13'),
(2, 'admin@example.com', '$2y$12$XWLv0C3I3ZxY1s6AP/thoOddETmMXxL1lcuJJLDI4ZCEzsXBFSYS2', 'admin', 'Admin', 'User', '2026-01-29 07:51:13'),
(3, 'accounting@example.com', '$2y$12$lqO2L/wO1gGW1G7iVlNL1eoNJEAwaUKKpymNTLc8/bDphJioiLzhu', 'accounting', 'Accounting', 'Staff', '2026-01-29 07:51:13'),
(4, 'registrar@example.com', '$2y$12$mBRinZXeLFpyge/D499ceeBuVHsRqy6OiVNtDm.YuSdfAgVdFNrWG', 'registrar', 'Registrar', 'Admin', '2026-01-29 08:54:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `add_drop_requests`
--
ALTER TABLE `add_drop_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `add_drop_window`
--
ALTER TABLE `add_drop_window`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `fk_course_faculty` (`faculty_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_course` (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `exam_permits`
--
ALTER TABLE `exam_permits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_permit` (`student_id`,`exam_period`,`school_year`,`semester`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `payment_date` (`payment_date`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempted_at`);

--
-- Indexes for table `or_ar_sequences`
--
ALTER TABLE `or_ar_sequences`
  ADD UNIQUE KEY `year` (`year`);

--
-- Indexes for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `payment_notices`
--
ALTER TABLE `payment_notices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_notice` (`student_id`,`exam_period`);

--
-- Indexes for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `program_courses`
--
ALTER TABLE `program_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prog_course` (`program_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_building` (`room_name`,`building`);

--
-- Indexes for table `school_events`
--
ALTER TABLE `school_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seed_flags`
--
ALTER TABLE `seed_flags`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `student_grades`
--
ALTER TABLE `student_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_grade` (`enrollment_id`,`term`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_course` (`course_id`);

--
-- Indexes for table `term_payments`
--
ALTER TABLE `term_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_term` (`student_id`,`semester`,`term`);

--
-- Indexes for table `tor_evaluations`
--
ALTER TABLE `tor_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `add_drop_requests`
--
ALTER TABLE `add_drop_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `add_drop_window`
--
ALTER TABLE `add_drop_window`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=849;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=531;

--
-- AUTO_INCREMENT for table `exam_permits`
--
ALTER TABLE `exam_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `payment_notices`
--
ALTER TABLE `payment_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=307;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `program_courses`
--
ALTER TABLE `program_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=843;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `school_events`
--
ALTER TABLE `school_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `student_grades`
--
ALTER TABLE `student_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `term_payments`
--
ALTER TABLE `term_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tor_evaluations`
--
ALTER TABLE `tor_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=928;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_permits`
--
ALTER TABLE `exam_permits`
  ADD CONSTRAINT `exam_permits_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD CONSTRAINT `installment_payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD CONSTRAINT `payment_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_notices`
--
ALTER TABLE `payment_notices`
  ADD CONSTRAINT `payment_notices_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  ADD CONSTRAINT `payment_schedules_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `program_courses`
--
ALTER TABLE `program_courses`
  ADD CONSTRAINT `program_courses_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `program_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  ADD CONSTRAINT `tuition_fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 04, 2026 at 07:30 AM
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
(5, 'System Maintenance — Every Sunday 12 AM–4 AM', 'The Student Information System undergoes weekly maintenance every Sunday.', '2026-01-29', 'system', 'normal', '⚙️', '2026-02-01 00:52:41'),
(6, 'wew', 'wew', '2026-03-04', 'school', 'normal', '📢', '2026-03-04 05:57:28');

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
(1, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 119, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 18:17:38'),
(2, 2, 'admin@example.com', 'admin', 'DELETE_FACULTY', 'faculty', 7, 'Deleted faculty: shane binoya', '{\"id\":\"7\",\"faculty_id\":\"FAC-2026-001\",\"first_name\":\"shane\",\"last_name\":\"binoya\",\"email\":\"shanecarlobinoya@gmail.com\",\"department\":\"Information Technology\",\"specialty\":\"ai\",\"subjects\":\"[\\\"CS111\\\",\\\"IT104\\\",\\\"IT102\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-02 18:07:26\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 19:05:46'),
(3, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 574, 'Updated course: AEC105 - Intermediate Accounting 2', '{\"id\":\"574\",\"code\":\"AEC105\",\"name\":\"Intermediate Accounting 2\",\"credits\":\"3\",\"instructor\":null,\"faculty_id\":null,\"schedule\":null,\"day\":null,\"time\":null,\"room\":null,\"capacity\":\"40\",\"enrolled_count\":\"0\",\"semester\":\"1st Semester, AY 2025-2026\",\"description\":\"Pre-requisite: AEC113\",\"department\":\"Business\",\"program\":\"2-Yrs. Tourism, Hotel and Restaurant Operations\",\"year_level\":\"2nd Year\",\"created_at\":\"2026-03-03 09:44:07\",\"is_lab\":\"0\"}', '{\"code\":\"AEC105\",\"name\":\"Intermediate Accounting 2\",\"program\":\"2-Yrs. Tourism, Hotel and Restaurant Operations\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 19:30:02'),
(4, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 555, 'Updated course: AEC109 - Managerial Economics', '{\"id\":\"555\",\"code\":\"AEC109\",\"name\":\"Managerial Economics\",\"credits\":\"3\",\"instructor\":null,\"faculty_id\":null,\"schedule\":null,\"day\":null,\"time\":null,\"room\":null,\"capacity\":\"40\",\"enrolled_count\":\"2\",\"semester\":\"1st Semester, AY 2025-2026\",\"description\":\"Pre-requisite: None\",\"department\":\"Business\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-03 09:44:07\",\"is_lab\":\"0\"}', '{\"code\":\"AEC109\",\"name\":\"Managerial Economics\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:00:27'),
(5, 2, 'admin@example.com', 'admin', 'CREATE_COURSE', 'course', 849, 'Created course: 123 - sd', NULL, '{\"code\":\"123\",\"name\":\"sd\",\"program\":\"Accountancy, Business and Management\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:23:41'),
(6, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 23, 'Updated program: ABM - Accountancy, Business and Management', '{\"id\":\"23\",\"name\":\"Accountancy, Business and Management\",\"code\":\"ABM\",\"level_type\":\"SHS\",\"duration\":\"2\",\"description\":\"SHS strand focusing on business, accounting, economics, and management principles.\",\"department\":\"Academic Track\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"Accountancy, Business and Management\",\"code\":\"ABM\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:23:59'),
(7, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 849, 'Updated course: 123 - sd', '{\"id\":\"849\",\"code\":\"123\",\"name\":\"sd\",\"credits\":\"3\",\"instructor\":\"\",\"faculty_id\":null,\"schedule\":null,\"day\":\"\",\"time\":\"\",\"room\":\"\",\"capacity\":\"40\",\"enrolled_count\":\"0\",\"semester\":\"1st Semester, AY 2024-2025\",\"description\":\"d\",\"department\":\"Academic Track\",\"program\":\"Accountancy, Business and Management\",\"year_level\":\"Grade 11\",\"created_at\":\"2026-03-04 04:23:41\",\"is_lab\":\"0\",\"lec_units\":\"3\",\"lab_units\":\"0\"}', '{\"code\":\"123\",\"name\":\"sd\",\"program\":\"Accountancy, Business and Management\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:24:32'),
(8, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 849, 'Updated course: 123 - sd', '{\"id\":\"849\",\"code\":\"123\",\"name\":\"sd\",\"credits\":\"2\",\"instructor\":\"\",\"faculty_id\":null,\"schedule\":null,\"day\":\"\",\"time\":\"\",\"room\":\"\",\"capacity\":\"40\",\"enrolled_count\":\"0\",\"semester\":\"1st Semester, AY 2024-2025\",\"description\":\"d\",\"department\":\"Academic Track\",\"program\":\"Accountancy, Business and Management\",\"year_level\":\"Grade 11\",\"created_at\":\"2026-03-04 04:23:41\",\"is_lab\":\"1\",\"lec_units\":\"1\",\"lab_units\":\"1\"}', '{\"code\":\"123\",\"name\":\"sd\",\"program\":\"Accountancy, Business and Management\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:25:01'),
(9, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 849, 'Updated course: 123 - sd', '{\"id\":\"849\",\"code\":\"123\",\"name\":\"sd\",\"credits\":\"2\",\"instructor\":\"\",\"faculty_id\":null,\"schedule\":null,\"day\":\"\",\"time\":\"\",\"room\":\"\",\"capacity\":\"40\",\"enrolled_count\":\"0\",\"semester\":\"2nd Semester, AY 2024-2025\",\"description\":\"d\",\"department\":\"Academic Track\",\"program\":\"Accountancy, Business and Management\",\"year_level\":\"Grade 11\",\"created_at\":\"2026-03-04 04:23:41\",\"is_lab\":\"1\",\"lec_units\":\"1\",\"lab_units\":\"1\"}', '{\"code\":\"123\",\"name\":\"sd\",\"program\":\"Accountancy, Business and Management\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:25:10'),
(10, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 849, 'Deleted course: 123 - sd', '{\"id\":\"849\",\"code\":\"123\",\"name\":\"sd\",\"credits\":\"2\",\"instructor\":\"\",\"faculty_id\":null,\"schedule\":null,\"day\":\"\",\"time\":\"\",\"room\":\"\",\"capacity\":\"40\",\"enrolled_count\":\"0\",\"semester\":\"2nd Semester, AY 2025-2026\",\"description\":\"d\",\"department\":\"Academic Track\",\"program\":\"Accountancy, Business and Management\",\"year_level\":\"Grade 11\",\"created_at\":\"2026-03-04 04:23:41\",\"is_lab\":\"1\",\"lec_units\":\"1\",\"lab_units\":\"1\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:28:01'),
(11, 2, 'admin@example.com', 'admin', 'RENAME_DEPARTMENT', 'department', 0, 'Renamed dept: \"BMD\" → \"BMD\" (0 programs updated)', NULL, '{\"old\":\"BMD\",\"new\":\"BMD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:52:22'),
(12, 2, 'admin@example.com', 'admin', 'RENAME_DEPARTMENT', 'department', 0, 'Renamed dept: \"BMD\" → \"BMD\" (0 programs updated)', NULL, '{\"old\":\"BMD\",\"new\":\"BMD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:52:38'),
(13, 2, 'admin@example.com', 'admin', 'RENAME_DEPARTMENT', 'department', 0, 'Renamed dept: \"BMD\" → \"BMD\" (0 programs updated)', NULL, '{\"old\":\"BMD\",\"new\":\"BMD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 20:52:51'),
(14, 2, 'admin@example.com', 'admin', 'RENAME_DEPARTMENT', 'department', 0, 'Renamed dept: \"BMD\" → \"BMD\" (0 programs updated)', NULL, '{\"old\":\"BMD\",\"new\":\"BMD\"}', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-03 20:53:14'),
(15, 2, 'admin@example.com', 'admin', 'RENAME_DEPARTMENT', 'department', 0, 'Renamed dept: \"Business\" → \"BMD\" (4 programs updated)', NULL, '{\"old\":\"Business\",\"new\":\"BMD\"}', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-03 20:56:37'),
(16, 2, 'admin@example.com', 'admin', 'RENAME_DEPARTMENT', 'department', 0, 'Renamed dept: \"BMD\" → \"BMD\" (0 programs, 0 courses updated)', NULL, '{\"old\":\"BMD\",\"new\":\"BMD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:06:16'),
(17, 2, 'admin@example.com', 'admin', 'RENAME_DEPARTMENT', 'department', 0, 'Renamed dept: \"BMD\" → \"BMDs\" (4 programs, 0 courses updated)', NULL, '{\"old\":\"BMD\",\"new\":\"BMDs\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:15:30'),
(18, 2, 'admin@example.com', 'admin', 'DELETE_DEPARTMENT', 'department', 0, 'Deleted dept: \"Home Economics\" (3 programs, 0 courses cleared)', NULL, '{\"dept\":\"Home Economics\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:22:01'),
(19, 2, 'admin@example.com', 'admin', 'DELETE_DEPARTMENT', 'department', 0, 'Deleted dept: \"TVET\" (15 programs, 0 courses cleared)', NULL, '{\"dept\":\"TVET\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:23:14'),
(20, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 292 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:29:48'),
(21, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:30:44'),
(22, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:32:05'),
(23, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:35:38'),
(24, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:35:42'),
(25, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:36:23'),
(26, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:37:15'),
(27, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:38:21'),
(28, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:38:33'),
(29, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:40:08'),
(30, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:40:36'),
(31, 2, 'admin@example.com', 'admin', 'CREATE_COURSE', 'course', 850, 'Created course: 1 - 1', NULL, '{\"code\":\"1\",\"name\":\"1\",\"program\":\"Diploma in Travel and Tourism Technology (Leading to BSTM)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:41:06'),
(32, 2, 'admin@example.com', 'admin', 'SYNC_COURSE_DEPARTMENTS', 'course', 0, 'Synced departments for 0 courses', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 21:41:30'),
(33, 2, 'admin@example.com', 'admin', 'CREATE_COURSE', 'course', 851, 'Created course: dsd - dssd', NULL, '{\"code\":\"dsd\",\"name\":\"dssd\",\"program\":\"Accountancy, Business and Management\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:01:27'),
(34, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 39, 'Updated program: 3DA-NCIII - 3D Animation NCIII', '{\"id\":\"39\",\"name\":\"3D Animation NCIII\",\"code\":\"3DA-NCIII\",\"level_type\":\"TVET\",\"duration\":\"1\",\"description\":\"TESDA National Certificate III program in 3D Animation.\",\"department\":\"\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"3D Animation NCIII\",\"code\":\"3DA-NCIII\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:03:29'),
(35, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 33, 'Updated program: CSM - 2-Yrs. Cruise Ship Management', '{\"id\":\"33\",\"name\":\"2-Yrs. Cruise Ship Management\",\"code\":\"CSM\",\"level_type\":\"TVET\",\"duration\":\"2\",\"description\":\"Two-year TVET program in cruise ship operations and hospitality management.\",\"department\":\"\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"2-Yrs. Cruise Ship Management\",\"code\":\"CSM\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:03:56'),
(36, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 32, 'Updated program: CIMT-TVET - 2-Yrs. Computer Information and Multimedia Technology', '{\"id\":\"32\",\"name\":\"2-Yrs. Computer Information and Multimedia Technology\",\"code\":\"CIMT-TVET\",\"level_type\":\"TVET\",\"duration\":\"2\",\"description\":\"Two-year TVET program in computer information and multimedia technology.\",\"department\":\"\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"2-Yrs. Computer Information and Multimedia Technology\",\"code\":\"CIMT-TVET\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:08:20'),
(37, 2, 'admin@example.com', 'admin', 'CREATE_COURSE', 'course', 852, 'Created course: 11 - 1', NULL, '{\"code\":\"11\",\"name\":\"1\",\"program\":\"Humanities and Social Sciences Strand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:12:10'),
(38, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 29, 'Updated program: BPP-NCII - Bread and Pastry Production NCII', '{\"id\":\"29\",\"name\":\"Bread and Pastry Production NCII\",\"code\":\"BPP-NCII\",\"level_type\":\"SHS\",\"duration\":\"2\",\"description\":\"SHS Home Economics strand with TESDA National Certificate II in Bread and Pastry Production.\",\"department\":\"\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"Bread and Pastry Production NCII\",\"code\":\"BPP-NCII\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:13:25'),
(39, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 28, 'Updated program: COOKERY-NCII - Cookery NCII', '{\"id\":\"28\",\"name\":\"Cookery NCII\",\"code\":\"COOKERY-NCII\",\"level_type\":\"SHS\",\"duration\":\"2\",\"description\":\"SHS Home Economics strand with TESDA National Certificate II in Cookery.\",\"department\":\"\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"Cookery NCII\",\"code\":\"COOKERY-NCII\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:13:51'),
(40, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 30, 'Updated program: FBS-NCII-SHS - Food and Beverages Services NCII', '{\"id\":\"30\",\"name\":\"Food and Beverages Services NCII\",\"code\":\"FBS-NCII-SHS\",\"level_type\":\"SHS\",\"duration\":\"2\",\"description\":\"SHS Home Economics strand with TESDA National Certificate II in Food and Beverages Services.\",\"department\":\"\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"Food and Beverages Services NCII\",\"code\":\"FBS-NCII-SHS\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:14:26'),
(41, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 27, 'Updated program: CSS-NCII - Computer Systems Servicing NCII', '{\"id\":\"27\",\"name\":\"Computer Systems Servicing NCII\",\"code\":\"CSS-NCII\",\"level_type\":\"SHS\",\"duration\":\"2\",\"description\":\"SHS TVL strand with TESDA National Certificate II in Computer Systems Servicing.\",\"department\":\"Technical-Vocational Livelihood Track (TVL)\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"Computer Systems Servicing NCII\",\"code\":\"CSS-NCII\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:14:33'),
(42, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 29, 'Updated program: BPP-NCII - Bread and Pastry Production NCII', '{\"id\":\"29\",\"name\":\"Bread and Pastry Production NCII\",\"code\":\"BPP-NCII\",\"level_type\":\"SHS\",\"duration\":\"2\",\"description\":\"SHS Home Economics strand with TESDA National Certificate II in Bread and Pastry Production.\",\"department\":\"Technical-Vocational Livelihood Track (TVL)\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"Bread and Pastry Production NCII\",\"code\":\"BPP-NCII\",\"department\":\"Technical-Vocational Livelihood Track (TVL)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:29:48'),
(43, 2, 'admin@example.com', 'admin', 'CREATE_COURSE', 'course', 854, 'Created course: 23 - dsdds', NULL, '{\"code\":\"23\",\"name\":\"dsdds\",\"program\":\"Computer Systems Servicing NCII\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:31:29'),
(44, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 29, 'Updated program: BPP-NCII - Bread and Pastry Production NCII', '{\"id\":\"29\",\"name\":\"Bread and Pastry Production NCII\",\"code\":\"BPP-NCII\",\"level_type\":\"SHS\",\"duration\":\"2\",\"description\":\"SHS Home Economics strand with TESDA National Certificate II in Bread and Pastry Production.\",\"department\":\"Technical-Vocational Livelihood Track (TVL)\",\"created_at\":\"2026-03-03 09:48:50\"}', '{\"name\":\"Bread and Pastry Production NCII\",\"code\":\"BPP-NCII\",\"department\":\"Technical-Vocational Livelihood Track (TVL)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 22:31:52'),
(45, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 6, 'Updated program: BSIT - Bachelor of Science in Information Technology', '{\"id\":\"6\",\"name\":\"Bachelor of Science in Information Technology\",\"code\":\"BSIT\",\"level_type\":\"College\",\"duration\":\"4\",\"description\":\"A program in software development, networking, database systems, and information assurance.\",\"department\":\"ICTD\",\"created_at\":\"2026-03-03 09:44:06\"}', '{\"name\":\"Bachelor of Science in Information Technology\",\"code\":\"BSIT\",\"department\":\"ICTD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 23:11:24'),
(46, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 128, 'Admin viewed student record: Shane Carlo Nodado', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 00:01:09'),
(47, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 128, 'Admin viewed student record: Shane Carlo Nodado', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 03:05:55'),
(48, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 129, 'Admin viewed student record: Shane Carlo Nodado', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 03:06:01'),
(49, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 130, 'Admin viewed student record: Shane Carlo Nodado', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 03:06:03'),
(50, 2, 'admin@example.com', 'admin', 'UPDATE_PROGRAM', 'program', 6, 'Updated program: BSIT - Bachelor of Science in Information Technology', '{\"id\":\"6\",\"name\":\"Bachelor of Science in Information Technology\",\"code\":\"BSIT\",\"level_type\":\"College\",\"duration\":\"4\",\"description\":\"A program in software development, networking, database systems, and information assurance.\",\"department\":\"ICTD\",\"created_at\":\"2026-03-03 09:44:06\"}', '{\"name\":\"Bachelor of Science in Information Technology\",\"code\":\"BSIT\",\"department\":\"ICTD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 04:47:16'),
(51, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 574, 'Updated course: AEC105 - Intermediate Accounting 2', '{\"id\":\"574\",\"code\":\"AEC105\",\"name\":\"Intermediate Accounting 2\",\"credits\":\"3\",\"instructor\":\"\",\"faculty_id\":null,\"schedule\":null,\"day\":\"\",\"time\":\"\",\"room\":\"\",\"capacity\":\"40\",\"enrolled_count\":\"4\",\"semester\":\"1st Semester, AY 2025-2026\",\"description\":\"Pre-requisite: AEC113\",\"department\":\"ICTD\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"Year 1\",\"created_at\":\"2026-03-03 09:44:07\",\"is_lab\":\"0\",\"lec_units\":\"3\",\"lab_units\":\"0\"}', '{\"code\":\"AEC105\",\"name\":\"Intermediate Accounting 2\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 05:09:49'),
(52, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 574, 'Updated course: AEC105 - Intermediate Accounting 2', '{\"id\":\"574\",\"code\":\"AEC105\",\"name\":\"Intermediate Accounting 2\",\"credits\":\"4\",\"instructor\":\"\",\"faculty_id\":null,\"schedule\":null,\"day\":\"\",\"time\":\"\",\"room\":\"\",\"capacity\":\"40\",\"enrolled_count\":\"4\",\"semester\":\"1st Semester\",\"description\":\"Pre-requisite: AEC113\",\"department\":\"ICTD\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-03 09:44:07\",\"is_lab\":\"0\",\"lec_units\":\"3\",\"lab_units\":\"1\"}', '{\"code\":\"AEC105\",\"name\":\"Intermediate Accounting 2\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 05:10:48');

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
  `is_lab` tinyint(1) DEFAULT 0,
  `lec_units` int(11) DEFAULT 0,
  `lab_units` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `instructor`, `faculty_id`, `schedule`, `day`, `time`, `room`, `capacity`, `enrolled_count`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `is_lab`, `lec_units`, `lab_units`) VALUES
(550, 'GE100', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(551, 'GE105', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(552, 'BME100', 'International Business and Trade', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(553, 'GE108', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(554, 'AEC111', 'Financial Accounting and Reporting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 5, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(555, 'AEC109', 'Managerial Economics', 3, '', NULL, NULL, '', '', '', 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(556, 'BSNA102', 'Organization and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(557, 'PE1-BSA', 'Physical Education 1 (Aquatics)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(558, 'NSTP1-BSA', 'NSTP 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(559, 'AEC112', 'Conceptual Framework and Accounting Standards', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC111', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(560, 'GE101', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(561, 'GE109', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(562, 'AEC120', 'Cost Accounting and Control', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC111', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(563, 'BSNA101', 'Fundamentals of Accountancy, Business and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(564, 'AEC113', 'Intermediate Accounting 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC111', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(565, 'BME101', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BME100', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(566, 'PE2-BSA', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(567, 'NSTP2-BSA', 'NSTP 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(568, 'BSNA103', 'Business Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(569, 'AEC121', 'Strategic Cost Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC120', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(570, 'AEC108', 'Governance, Business Ethics, Risk Management and Internal Control', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(571, 'AEC116', 'Financial Markets', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(572, 'BME103', 'Law on Obligations and Contracts', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(573, 'AEC107', 'Statistical Analysis and Software Application', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(574, 'AEC105', 'Intermediate Accounting 2', 3, '', NULL, NULL, '', '', '', 40, 4, '1st Semester', 'Pre-requisite: AEC113', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 1, 2, 1),
(575, 'AEC117', 'Financial Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC109', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(576, 'PE3-BSA', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(577, 'GE103', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(578, 'AEC101', 'Business Laws and Regulations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '2nd Semester, AY 2025-2026', 'Pre-requisite: BME103', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(579, 'AEC115', 'Intermediate Accounting 3', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC105', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(580, 'AEC118', 'Accounting Information System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC112', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(581, 'AEC124', 'Income Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(582, 'GE116', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(583, 'BSNA104', 'Business Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(584, 'GE110', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(585, 'PE4-BSA', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(586, 'GE104', 'Readings in the Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(587, 'AEC103', 'Management Science', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(588, 'AEC119', 'IT Application Tools in Business', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC118', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(589, 'AEC122', 'Strategic Business Analysis', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC117', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(590, 'AEC123', 'Business Tax', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC101', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(591, 'AEC110', 'Economic Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(592, 'AEC102', 'Regulatory Framework and Legal Issues in Business', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC101', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(593, 'GE115', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(594, 'BME102', 'Operations Management and TQM', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BME100', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(595, 'GE106', 'Science, Technology and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(596, 'GE107', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(597, 'AEC104', 'Accounting Research Methods', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '2nd Semester, AY 2025-2026', 'Pre-requisite: 3rd Year Standing', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(598, 'ELEC1-BSA', 'Updates in Financial Reporting and Standards (Elective 1)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(599, 'APE108', 'Accounting for Government and Non-profit Organizations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: AEC108', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(600, 'APE107', 'Accounting for Business Combinations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All prior BME and AEC subjects', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(601, 'AEC114', 'Accounting Internship', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, 'Summer, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 6, 0),
(602, 'APE101', 'Auditing and Assurance Principles', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(603, 'APE102', 'Auditing and Assurance: Concepts and Applications 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(604, 'APE103', 'Auditing and Assurance: Concepts and Applications 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(605, 'AEC106', 'Accountancy Research', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: AEC104', 'ICTD', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(606, 'APE106', 'Accounting for Special Transactions', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(607, 'APE109', 'Financial Accounting and Reporting Integration', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 6, 0),
(608, 'APE111', 'Taxation Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(609, 'APE112', 'Regulatory Framework for Business Transactions Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(610, 'APE113', 'Management Advisory Services Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(611, 'APE104', 'Auditing and Assurance: Specialized Industries', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(612, 'APE105', 'Auditing in a CIS Environment', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Professional Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(613, 'GE100-CA', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(614, 'GE105-CA', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(615, 'GE108-CA', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(616, 'GE104-CA', 'Readings in the Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(617, 'SCP101', 'Introduction to Supply Chain Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(618, 'BSNA102-CA', 'Organization and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(619, 'PE1-CA', 'PATHFit 1 (Movement Competency Training)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(620, 'NSTP1-CA', 'NSTP 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(621, 'GE103-CA', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(622, 'GE101-CA', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(623, 'GE109-CA', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(624, 'TMC100', 'Fundamentals of Customs and Tariff System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(625, 'SCP102', 'Warehouse Operations Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: SCP101', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(626, 'BSNA101-CA', 'Fundamentals of Accounting, Business and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(627, 'PE2-CA', 'PATHFit 2 (Exercise-Based Fitness Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(628, 'NSTP2-CA', 'NSTP 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(629, 'BLT100', 'Business Law (Obligations and Contracts, Negotiable Instruments Law, Intellectual Property Law and Insurance Law)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(630, 'CMC100', 'Border Control and Security', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC100', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(631, 'SCP103', 'Procurement and Inventory Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: SCP101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(632, 'CMC101', 'Customs Operations and Cargo Handling', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC100', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(633, 'TMC101', 'Commodity Classification System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC100', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(634, 'TMC106', 'International Trade Organizations, Agreements and Rules of Origin', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC100', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 5, 0),
(635, 'BSNA103-CA', 'Business Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(636, 'PE3-CA', 'PATHFit 3 (Group Exercise)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(637, 'BLT101', 'Taxation (Income and Business Taxation)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(638, 'GE116-CA', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(639, 'GE107-CA', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(640, 'SCP104', 'Transportation Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: SCP101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(641, 'CMC102', 'Customs Warehousing', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: CMC101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 5, 0),
(642, 'TMC102', 'Customs Valuation System', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: TMC106', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 5, 0),
(643, 'BSNA104-CA', 'Business Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(644, 'PE4-CA', 'PATHFit 4 (Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(645, 'GE115-CA', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(646, 'GE110-CA', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(647, 'CMC106', 'Ethics and Standards of the Customs Broker', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(648, 'CMC103', 'Customs Clearance', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior CMC', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 5, 0),
(649, 'TMC103', 'Customs Appraisal and Assessment', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: TMC102', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 5, 0),
(650, 'BME100-CA', 'Operations Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All BSNA', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(651, 'BME101-CA', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All BSNA', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(652, 'GE106-CA', 'Science, Technology and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(653, 'CMC105', 'Customs Post Clearance Audit and Fraud Detection', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior CMC', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(654, 'CMC104', 'Customs Proceedings', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior CMC', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 5, 0),
(655, 'TMC105', 'Special Duties and Trade Remedies', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior TMC', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 5, 0),
(656, 'TMC104', 'Excise Taxes, Liquidation of Duty and Surcharges', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior TMC', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 5, 0),
(657, 'CMC107', 'Competency Assessment in Customs Management', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior CMC', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 5, 0),
(658, 'TMC107', 'Competency Assessment in Tariff Management', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior TMC', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 5, 0),
(659, 'RSH100', 'Research 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: 4th Year Standing', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(660, 'RSH101', 'Research 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RSH100', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(661, 'OJT100', 'Internship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: 4th Year Standing', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(662, 'BME102-E', 'International Business and Trade', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(663, 'GE100-E', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(664, 'GE105-E', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(665, 'GE108-E', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(666, 'ECS101', 'Entrepreneurial Behavior', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(667, 'BSNA102-E', 'Organization and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(668, 'PE1-E', 'Physical Education 1 (Aquatics)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(669, 'NSTP1-E', 'NSTP 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(670, 'GE101-E', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(671, 'BSNA101-E', 'Fundamentals of Accounting, Business and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(672, 'ECS102', 'Opportunity Seeking', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(673, 'GE109-E', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(674, 'ECS108', 'Microeconomics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(675, 'PE2-E', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(676, 'NSTP2-E', 'NSTP 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(677, 'BME103-E', 'Human Resource Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(678, 'ECS107', 'Market Research and Consumer Behavior', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(679, 'ECS109', 'Business Law and Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(680, 'ECS114', 'Programs and Policies on Enterprise Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(681, 'BSNA103-E', 'Business Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(682, 'BME104', 'Basic Accounting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(683, 'PE3-E', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(684, 'GE103-E', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(685, 'GE116-E', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(686, 'ECS111', 'Pricing and Costing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(687, 'BSNA104-E', 'Business Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: ECS109', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(688, 'PE4-E', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA101', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(689, 'GE104-E', 'Readings in the Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(690, 'GE110-E', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(691, 'BME100-E', 'Operations Management (Total Quality Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BSNA102', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(692, 'GE115-E', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(693, 'EST101', 'Specialized Track 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS114', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(694, 'EEC101', 'Elective 1 (Supply Chain Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(695, 'ECS112', 'Innovation and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(696, 'GE106-E', 'Science, Technology and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(697, 'GE107-E', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(698, 'EST102', 'Specialized Track 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: EST101', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(699, 'EEC102', 'Elective 2 (E-Commerce)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(700, 'ECS103', 'Business Plan Preparation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: 3rd Year Standing', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(701, 'ECS110', 'Financial Management and Analysis for Decision Making', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BSNA104', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(702, 'ECS113', 'Social Entrepreneurship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(703, 'BME101-E', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BME100', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(704, 'EST103', 'Specialized Track 3', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: EST102', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(705, 'EEC103', 'Elective 3 (Hospitality Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(706, 'ECS104', 'Business Plan Implementation 1 (Product Development and Market Analysis)', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: 4th Year Standing', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 5, 0),
(707, 'EST104', 'Specialized Track 4', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: EST103', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(708, 'EEC104', 'Elective 4 (Managing a Service Enterprise)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: ECS102', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 3, 0),
(709, 'ECS105', 'Business Plan Implementation 2', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: 4th Year Standing', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 5, 0),
(710, 'RE-FUN013', 'Fundamentals of Real Estate Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(711, 'GE-ENG013', 'Conversational English Competency', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(712, 'GE-FIL013', 'Komunikasyon Sa Akademikong Filipino', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(713, 'GE-MAT013', 'College Algebra - Math 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(714, 'RE-TAX013', 'Business and Real Estate Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(715, 'AC-TAX013', 'Economics with Taxation and Land Reform', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(716, 'GE-NSC013', 'Biological Science', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(717, 'RE-HGP013', 'Human and Physical Geography', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(718, 'GE-PHE012', 'Recreational Activities', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(719, 'GE-NST013', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(720, 'BN-MGT013', 'Principles of Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(721, 'RE-REC013', 'Fundamentals of Real Estate Consulting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-FUN013', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(722, 'LW-BSN013', 'Law on Obligations and Contracts with Real Properties', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(723, 'GE-NSC023', 'Environment and Greenbuilding Technology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-NSC013', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(724, 'GE-ENG023', 'Grammar and Composition', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-ENG013', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(725, 'RE-PAD013', 'Real Estate Planning and Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-REA013', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(726, 'RE-REB013', 'Real Estate Brokerage', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(727, 'GE-FIL023', 'Pagbasa at Pagsulat Tungo sa Pananaliksik', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-FIL013', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(728, 'GE-PHE032', 'Individual and Team Sports', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(729, 'GE-NST023', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-NST013', 'BMDs', '', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(730, 'BN-MKT013', 'Principles of Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-MGT013', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(731, 'RE-LAR013', 'Legal Aspects of Real Estate', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: LW-BSN013', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(732, 'GE-BAC013', 'Basic Accounting 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(733, 'RE-CSE013', 'Consulting for Specific Engagements', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: RE-REC013', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(734, 'BN-ECO013', 'Macroeconomics and Microeconomics Theory and Practice', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(735, 'RE-REA013', 'Real Estate Appraisal and Property Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: RE-PAD013', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(736, 'IT-CSA013', 'Computer Software Application', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(737, 'GE-ENG033', 'Business Correspondence and Technical Writing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: GE-ENG023', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(738, 'GE-PHE052', 'Rhythmic Activities', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(739, 'BN-FIN013', 'Basic Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(740, 'RE-MKB013', 'Real Estate Marketing and Brokerage', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-MKT013', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(741, 'RE-CIA013', 'Real Estate Consulting and Investments Analysis', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-REC013', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(742, 'RE-PVS013', 'Philippine Valuation Studies for Real Estate', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(743, 'GE-SCF013', 'Society and Culture with Family Planning', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(744, 'RE-POE013', 'Principles of Ecology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-NSC013', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(745, 'GE-PSY013', 'General Psychology with Drug Education, SARS, HIV/AIDS', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(746, 'GE-BAC023', 'Basic Accounting 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-BAC013', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(747, 'GE-PHE062', 'Sports and Games', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(748, 'IT-DBM013', 'Database Management System 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: IT-CSA013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(749, 'RE-PM013', 'Property Management System 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-MGT013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(750, 'GE-GCR013', 'Good Governance and Corporate Responsibility', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-MGT013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(751, 'RE-HSD013', 'Housing and Subdivision Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: RE-PMS013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(752, 'GE-MAT053', 'Business Statistics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: GE-MAT013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(753, 'GE-LCT013', 'Logic and Critical Thinking', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(754, 'RE-AGS013', 'Appraisal/Assessment in Government Sector', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-FIN013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(755, 'RE-REF013', 'Real Estate Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: BN-FIN013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(756, 'GE-PHC013', 'Philippine History and Culture', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(757, 'RE-ARD013', 'Appraisal Report and Data Gathering', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-PVS013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(758, 'RE-ESP013', 'Ethical Standards for Real Estate Practice', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-HBO013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(759, 'RE-REE013', 'Real Estate Economics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-ECO013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(760, 'RE-CCD013', 'Condominium Concept and other Specialized Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: RE-PMS013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(761, 'BN-HRM013', 'Human Resource Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-HBO013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(762, 'GE-APA013', 'Appreciation of Arts', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(763, 'GE-LWR013', 'Life and Works of Rizal', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-PHC013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(764, 'BN-HBO013', 'Human Behavior in Organization', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: BN-MGT013', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(765, 'GE-ENG053', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: GE-ENG023', 'BMDs', '', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(766, 'RE-INR015', 'Integration and Review for Real Estate', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: All Prior Major Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 5, 0),
(767, 'GE-OJT013', 'On-the-Job Training (600 hours)', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: All Prior Major Subjects', 'BMDs', '', '4th Year', '2026-03-03 01:44:07', 0, 6, 0),
(768, 'CC100', 'Introduction to Computing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(769, 'CC101', 'Computer Programming 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(770, 'IT-CMT015', 'Computer Organization and Maintenance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(771, 'GE105-CIMT', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(772, 'GE100-CIMT', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(773, 'GE112', 'Pilipino: Retorika', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(774, 'PE1-CIMT', 'Physical Education 1 (Aquatic)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(775, 'NSTP1-CIMT', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(776, 'EMC200', 'Free Hand and Digital Drawing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(777, 'CC102', 'Computer Programming 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(778, 'GE113', 'Pilipino: Pagsasalingwika', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(779, 'GE101-CIMT', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(780, 'GE116-CIMT', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(781, 'GE109-CIMT', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0);
INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `instructor`, `faculty_id`, `schedule`, `day`, `time`, `room`, `capacity`, `enrolled_count`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `is_lab`, `lec_units`, `lab_units`) VALUES
(782, 'PE2-CIMT', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(783, 'NSTP2-CIMT', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(784, 'CC103', 'Data Structures and Algorithms', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(785, 'CC105', 'Application Development and Emerging Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(786, 'IT105', 'Discrete Mathematics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(787, 'GE108-CIMT', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(788, 'ELEC400', 'Object Oriented Programming', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(789, 'GE110-CIMT', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(790, 'CAP501', 'Capstone Project', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(791, 'EMC203', 'Usability, HCI, and User Interaction Design', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(792, 'PE3-CIMT', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(793, 'CC104', 'Information Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(794, 'IT103', 'Fundamentals of Database Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(795, 'IT107', 'Networking 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(796, 'EMC202', 'Computer Graphics Programming', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(797, 'GE114', 'Pilipino: Tula, Sanaysay, Nobela', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(798, 'GE103-CIMT', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(799, 'OJT-CIMT', 'Internship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(800, 'EMC204', 'Principles of 2D Animation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(801, 'PE4-CIMT', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(802, 'CC100-IT', 'Introduction to Computing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 8, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 1, 2, 1),
(803, 'CC101-IT', 'Computer Programming 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 9, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 1, 2, 1),
(804, 'IT-CMT015-IT', 'Computer Organization and Maintenance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 8, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 1, 2, 1),
(805, 'GE105-IT', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 6, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(806, 'GE100-IT', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 9, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(807, 'PE1-IT', 'Physical Education 1 (Aquatic)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 5, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(808, 'NSTP1-IT', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 5, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(809, 'IT100', 'Introduction to Human Computer Interaction', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 1, 2, 1),
(810, 'CC102-IT', 'Computer Programming 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 1, 2, 1),
(811, 'IS103', 'IT Infrastructure and Network Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 1, 2, 1),
(812, 'GE101-IT', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(813, 'GE109-IT', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(814, 'PE2-IT', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 2, 0),
(815, 'NSTP2-IT', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-03 01:44:07', 0, 3, 0),
(816, 'CC103-IT', 'Data Structures and Algorithms', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 1, 2, 1),
(817, 'CC105-IT', 'Application Development and Emerging Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 1, 2, 1),
(818, 'IT105-IT', 'Discrete Mathematics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 1, 2, 1),
(819, 'GE108-IT', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(820, 'ELEC400-IT', 'Object-Oriented Programming', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 1, 2, 1),
(821, 'GE110-IT', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(822, 'EMC203-IT', 'Usability, HCI, and User Interaction Design', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 1, 2, 1),
(823, 'PE3-IT', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(824, 'CC104-IT', 'Information Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 1, 2, 1),
(825, 'IT103-IT', 'Fundamentals of Database Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 1, 2, 1),
(826, 'IT107-IT', 'Networking 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 1, 2, 1),
(827, 'GE103-IT', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(828, 'GE116-IT', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0, 3, 0),
(829, 'PE4-IT', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-03 01:44:07', 0, 2, 0),
(830, 'IT104', 'Integrative Programming and Technologies 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(831, 'IT101', 'Information Assurance and Security 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(832, 'IT108', 'Networking 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(833, 'ELEC401', 'Multimedia Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(834, 'IT106', 'Quantitative Methods (including Modelling and Simulation)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(835, 'GE115-IT', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(836, 'EMC207', 'Principles of 3D Animation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(837, 'GE111', 'Social and Professional Issues', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(838, 'IT102', 'Information Assurance and Security 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(839, 'IT110', 'System Integration and Architecture 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(840, 'ELEC103', 'Platform Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 1, 2, 1),
(841, 'GE104-IT', 'Readings in Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(842, 'GE106-IT', 'Science, Technology, and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(843, 'GE107-IT', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-03 01:44:07', 0, 3, 0),
(844, 'IT109', 'System Administration and Maintenance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 1, 2, 1),
(845, 'DM101', 'Organization and Management Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 1, 2, 1),
(846, 'ELEC403', 'Web Systems and Technology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 1, 2, 1),
(847, 'CAP501-IT', 'Capstone Project', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 4, '1st Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 0, 6, 0),
(848, 'OJT-BSIT', 'Internship (486 hours)', 9, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '2nd Semester, AY 2025-2026', 'Pre-requisite: None', 'ICTD', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-03 01:44:07', 0, 9, 0),
(850, '1', '1', 3, '', NULL, NULL, '', '', '', 40, 0, '1st Semester', '1', 'College Diploma', 'Diploma in Travel and Tourism Technology (Leading to BSTM)', 'Year 1', '2026-03-03 21:41:06', 0, 3, 0),
(851, 'dsd', 'dssd', 3, '', NULL, NULL, '', '', '', 40, 0, '1st Semester', 'sdsd', 'Academic Track', 'Bread and Pastry Production NCII', 'Grade 11', '2026-03-03 22:01:27', 0, 3, 0),
(852, '11', '1', 3, '', NULL, NULL, '', '', '', 40, 0, '1st Semester', '1', 'Academic Track', 'Bread and Pastry Production NCII', 'Grade 11', '2026-03-03 22:12:10', 0, 3, 0),
(854, '23', 'dsdds', 3, '', NULL, NULL, '', '', '', 40, 0, '1st Semester', 'dssd', 'Technical-Vocational Livelihood Track (TVL)', 'Bread and Pastry Production NCII', 'Grade 11', '2026-03-03 22:31:28', 0, 3, 0);

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

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `enrollment_date`, `status`, `grade`, `semester`, `notes`, `created_at`, `prelim_grade`, `midterm_grade`, `final_grade`, `overall_grade`, `remarks`) VALUES
(531, 128, 555, '2026-03-03', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-03 22:35:56', NULL, NULL, NULL, NULL, 'In Progress'),
(532, 128, 554, '2026-03-03', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-03 22:35:56', NULL, NULL, NULL, NULL, 'In Progress'),
(533, 128, 805, '2026-03-03', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-03 22:35:56', NULL, NULL, NULL, NULL, 'In Progress'),
(534, 128, 808, '2026-03-03', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-03 22:35:56', NULL, NULL, NULL, NULL, 'In Progress'),
(535, 128, 807, '2026-03-03', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-03 22:35:57', NULL, NULL, NULL, NULL, 'In Progress'),
(548, 128, 802, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled (Transferee)', '2026-03-03 23:57:43', NULL, NULL, NULL, NULL, 'In Progress'),
(549, 128, 803, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled (Transferee)', '2026-03-03 23:57:44', NULL, NULL, NULL, NULL, 'In Progress'),
(550, 128, 804, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled (Transferee)', '2026-03-03 23:57:44', NULL, NULL, NULL, NULL, 'In Progress'),
(551, 128, 806, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled (Transferee)', '2026-03-03 23:57:44', NULL, NULL, NULL, NULL, 'In Progress'),
(703, 129, 555, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 02:31:42', NULL, NULL, NULL, NULL, 'In Progress'),
(704, 129, 554, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 02:31:42', NULL, NULL, NULL, NULL, 'In Progress'),
(705, 129, 802, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 02:31:42', NULL, NULL, NULL, NULL, 'In Progress'),
(706, 129, 803, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 02:31:42', NULL, NULL, NULL, NULL, 'In Progress'),
(707, 129, 806, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 02:31:42', NULL, NULL, NULL, NULL, 'In Progress'),
(708, 129, 805, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 02:31:42', NULL, NULL, NULL, NULL, 'In Progress'),
(709, 130, 554, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:14', NULL, NULL, NULL, NULL, 'In Progress'),
(710, 130, 555, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:14', NULL, NULL, NULL, NULL, 'In Progress'),
(711, 130, 802, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:14', NULL, NULL, NULL, NULL, 'In Progress'),
(712, 130, 803, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:14', NULL, NULL, NULL, NULL, 'In Progress'),
(713, 130, 804, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:14', NULL, NULL, NULL, NULL, 'In Progress'),
(714, 130, 805, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:15', NULL, NULL, NULL, NULL, 'In Progress'),
(715, 130, 806, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:15', NULL, NULL, NULL, NULL, 'In Progress'),
(716, 130, 807, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:15', NULL, NULL, NULL, NULL, 'In Progress'),
(717, 130, 808, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 03:00:15', NULL, NULL, NULL, NULL, 'In Progress'),
(718, 131, 555, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:27:59', NULL, NULL, NULL, NULL, 'In Progress'),
(719, 131, 554, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:27:59', NULL, NULL, NULL, NULL, 'In Progress'),
(720, 131, 802, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:27:59', NULL, NULL, NULL, NULL, 'In Progress'),
(721, 131, 806, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:27:59', NULL, NULL, NULL, NULL, 'In Progress'),
(722, 131, 805, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:27:59', NULL, NULL, NULL, NULL, 'In Progress'),
(723, 131, 808, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:27:59', NULL, NULL, NULL, NULL, 'In Progress'),
(724, 132, 555, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:40:01', NULL, NULL, NULL, NULL, 'In Progress'),
(725, 132, 554, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:40:01', NULL, NULL, NULL, NULL, 'In Progress'),
(726, 132, 806, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:40:01', NULL, NULL, NULL, NULL, 'In Progress'),
(727, 132, 805, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:40:01', NULL, NULL, NULL, NULL, 'In Progress'),
(728, 132, 804, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:40:02', NULL, NULL, NULL, NULL, 'In Progress'),
(729, 132, 808, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:40:02', NULL, NULL, NULL, NULL, 'In Progress'),
(730, 132, 807, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:40:02', NULL, NULL, NULL, NULL, 'In Progress'),
(731, 132, 574, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 03:40:02', NULL, NULL, NULL, NULL, 'In Progress'),
(732, 132, 802, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled (Transferee)', '2026-03-04 03:41:07', NULL, NULL, NULL, NULL, 'In Progress'),
(733, 132, 803, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled (Transferee)', '2026-03-04 03:41:07', NULL, NULL, NULL, NULL, 'In Progress'),
(734, 133, 554, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:36', NULL, NULL, NULL, NULL, 'In Progress'),
(735, 133, 555, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:36', NULL, NULL, NULL, NULL, 'In Progress'),
(736, 133, 802, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:37', NULL, NULL, NULL, NULL, 'In Progress'),
(737, 133, 803, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:37', NULL, NULL, NULL, NULL, 'In Progress'),
(738, 133, 804, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:37', NULL, NULL, NULL, NULL, 'In Progress'),
(739, 133, 805, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:37', NULL, NULL, NULL, NULL, 'In Progress'),
(740, 133, 806, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:37', NULL, NULL, NULL, NULL, 'In Progress'),
(741, 133, 807, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:37', NULL, NULL, NULL, NULL, 'In Progress'),
(742, 133, 808, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 1', 'Auto-enrolled', '2026-03-04 04:03:37', NULL, NULL, NULL, NULL, 'In Progress'),
(743, 134, 554, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 04:48:40', NULL, NULL, NULL, NULL, 'In Progress'),
(744, 134, 802, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 04:48:40', NULL, NULL, NULL, NULL, 'In Progress'),
(745, 134, 803, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 04:48:40', NULL, NULL, NULL, NULL, 'In Progress'),
(746, 134, 806, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 04:48:40', NULL, NULL, NULL, NULL, 'In Progress'),
(747, 134, 805, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 04:48:40', NULL, NULL, NULL, NULL, 'In Progress'),
(748, 134, 804, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 04:48:40', NULL, NULL, NULL, NULL, 'In Progress'),
(749, 134, 807, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 04:48:41', NULL, NULL, NULL, NULL, 'In Progress'),
(750, 135, 574, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 05:11:15', NULL, NULL, NULL, NULL, 'In Progress'),
(751, 135, 554, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 05:11:15', NULL, NULL, NULL, NULL, 'In Progress'),
(752, 135, 805, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 05:11:15', NULL, NULL, NULL, NULL, 'In Progress'),
(753, 135, 804, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 05:11:15', NULL, NULL, NULL, NULL, 'In Progress'),
(754, 135, 808, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 05:11:15', NULL, NULL, NULL, NULL, 'In Progress'),
(755, 135, 807, '2026-03-04', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-04 05:11:15', NULL, NULL, NULL, NULL, 'In Progress'),
(756, 135, 802, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled (Transferee)', '2026-03-04 05:12:19', NULL, NULL, NULL, NULL, 'In Progress'),
(757, 135, 803, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled (Transferee)', '2026-03-04 05:12:19', NULL, NULL, NULL, NULL, 'In Progress'),
(758, 135, 806, '2026-03-04', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled (Transferee)', '2026-03-04 05:12:19', NULL, NULL, NULL, NULL, 'In Progress');

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
(5, 'FAC-2024-005', 'Sarah', 'Kim', 'sarah.kim@school.edu', 'English', 'Technical Writing', '[\"ENG101\"]', 'Active', '2026-02-01 00:03:41');

-- --------------------------------------------------------

--
-- Table structure for table `fee_config`
--

CREATE TABLE `fee_config` (
  `id` int(11) NOT NULL,
  `category` enum('College','SHS','TVET') NOT NULL DEFAULT 'College',
  `fee_key` varchar(60) NOT NULL,
  `fee_label` varchar(120) NOT NULL,
  `value` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `is_per_unit` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = multiply by enrolled units',
  `applies_to` varchar(200) NOT NULL DEFAULT 'All',
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fee_config`
--

INSERT INTO `fee_config` (`id`, `category`, `fee_key`, `fee_label`, `value`, `is_per_unit`, `applies_to`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'College', 'tuition_rate_per_unit', 'Tuition Fee (per unit)', 650.0000, 1, 'All', 'Charged per enrolled unit', 1, 1, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(2, 'College', 'misc_fee', 'Miscellaneous Fee', 6688.0000, 0, 'All', 'Fixed miscellaneous fee', 1, 2, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(3, 'College', 'reg_fee', 'Registration Fee', 700.0000, 0, 'All', 'Fixed registration fee', 1, 3, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(4, 'College', 'lab_fee_per_room', 'Laboratory Fee (per lab room)', 1900.0000, 1, 'All', 'Per laboratory room on campus', 1, 4, '2026-03-04 01:23:12', '2026-03-04 01:50:36'),
(5, 'College', 'energy_rate_per_unit', 'Energy Fee (per unit)', 63.0000, 1, 'All', 'Units × ₱21 × 3 terms = ₱63/unit', 1, 5, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(6, 'College', 'installment_fee', 'Installment Surcharge', 750.0000, 0, 'All', 'Added when payment plan is installment', 1, 6, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(7, 'SHS', 'transferee_flat_rate', 'Transferee Flat Rate', 20000.0000, 0, 'Transferee', 'Flat fee for SHS transferees', 1, 1, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(8, 'SHS', 'installment_fee', 'Installment Surcharge', 750.0000, 0, 'All', 'Added when payment plan is installment', 1, 2, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(9, 'TVET', 'misc_fee', 'Miscellaneous Fee', 0.0000, 1, 'All', 'Fixed miscellaneous fee for TVET', 1, 1, '2026-03-04 01:23:12', '2026-03-04 03:10:19'),
(10, 'TVET', 'reg_fee', 'Registration Fee', 0.0000, 1, 'All', 'Fixed registration fee for TVET', 1, 2, '2026-03-04 01:23:12', '2026-03-04 03:10:47'),
(11, 'TVET', 'installment_fee', 'Installment Surcharge', 750.0000, 1, 'All', 'Added when payment plan is installment', 1, 3, '2026-03-04 01:23:12', '2026-03-04 03:11:00'),
(12, 'TVET', 'transferee_flat_rate', 'Transferee Flat Rate', 20000.0000, 0, 'Transferee', 'Flat fee for TVET transferees', 1, 4, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(25, 'College', 'prisaa', 'Prisaa', 1000.0000, 0, 'All', '', 1, 7, '2026-03-04 01:54:11', '2026-03-04 02:24:28');

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

--
-- Dumping data for table `installment_payments`
--

INSERT INTO `installment_payments` (`id`, `student_id`, `payment_log_id`, `or_ar_number`, `or_ar_type`, `amount`, `payment_date`, `payment_method`, `gcash_reference`, `exam_period`, `notes`, `recorded_by`, `created_at`) VALUES
(68, 128, 113, 'AR-20260008', 'AR', 6074.00, '2026-03-03', 'GCash', '1234', 'Downpayment', '', 3, '2026-03-03 22:36:56'),
(69, 130, 114, 'OR-20260009', 'OR', 34526.00, '2026-03-04', 'Cash', '', 'Full', '', 3, '2026-03-04 03:00:09'),
(70, 129, 115, 'OR-20260010', 'OR', 21692.00, '2026-03-04', 'Cash', '', 'Full', '', 3, '2026-03-04 03:40:56'),
(71, 131, 116, 'OR-20260011', 'OR', 21692.00, '2026-03-04', 'Cash', '', 'Full', '', 3, '2026-03-04 03:40:59'),
(72, 132, 117, 'AR-20260012', 'AR', 5254.00, '2026-03-04', 'Cash', '', 'Downpayment', '', 3, '2026-03-04 03:41:02'),
(73, 133, 118, 'AR-20260013', 'AR', 5000.00, '2026-03-04', 'Cash', '', 'Downpayment', '', 3, '2026-03-04 04:03:28'),
(74, 135, 119, 'AR-20260014', 'AR', 5000.00, '2026-03-04', 'GCash', '12356', 'Downpayment', '', 3, '2026-03-04 05:12:13');

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

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip`, `attempted_at`) VALUES
(42, 'student2', '::1', '2026-03-03 23:57:07');

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
(2026, 14);

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

--
-- Dumping data for table `payment_logs`
--

INSERT INTO `payment_logs` (`id`, `student_id`, `payment_method`, `gcash_reference`, `gcash_amount`, `gcash_date`, `transaction_id`, `semester`, `status`, `verified_by`, `verified_at`, `notes`, `is_scholar`, `scholar_type`, `scholar_grantor`, `scholarship_amount`, `created_at`) VALUES
(113, 128, 'GCash', '1234', 6074.00, '2026-03-03', 'TXN-1772577384955-A2FLF', '1st Semester, AY 2026-2027', 'Verified', 3, '2026-03-03 22:36:56', '', 0, NULL, NULL, 0.00, '2026-03-03 22:36:25'),
(114, 130, 'Cash', '', 34526.00, '2026-03-04', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-03-04 03:00:09', '', 0, NULL, NULL, 0.00, '2026-03-04 02:54:02'),
(115, 129, 'Cash', '', 21692.00, '2026-03-04', NULL, '1st Semester, AY 2026-2028', 'Verified', 3, '2026-03-04 03:40:56', '', 0, NULL, NULL, 0.00, '2026-03-04 03:00:00'),
(116, 131, 'Cash', '', 21692.00, '2026-03-04', NULL, '1st Semester, AY 2026-2028', 'Verified', 3, '2026-03-04 03:40:59', '', 0, NULL, NULL, 0.00, '2026-03-04 03:40:49'),
(117, 132, 'Cash', '', 5254.00, '2026-03-04', NULL, '1st Semester, AY 1', 'Verified', 3, '2026-03-04 03:41:02', '', 0, NULL, NULL, 0.00, '2026-03-04 03:40:49'),
(118, 133, 'Cash', '', 5000.00, '2026-03-04', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-03-04 04:03:27', '', 0, NULL, NULL, 0.00, '2026-03-04 03:57:05'),
(119, 135, 'GCash', '12356', 5000.00, '2026-03-04', 'TXN-1772601118868-BE7PO', '1st Semester, AY 2025-2026', 'Verified', 3, '2026-03-04 05:12:12', '', 0, NULL, NULL, 0.00, '2026-03-04 05:11:58'),
(120, 134, 'Cash', '', 0.00, NULL, NULL, '1st Semester, AY 2025-2026', 'Pending', NULL, NULL, NULL, 0, NULL, NULL, 0.00, '2026-03-04 05:12:06');

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

--
-- Dumping data for table `payment_schedules`
--

INSERT INTO `payment_schedules` (`id`, `student_id`, `payment_type`, `total_assessment`, `downpayment_due`, `prelim_due`, `midterm_due`, `finals_due`, `prelim_paid`, `midterm_paid`, `finals_paid`, `prelim_status`, `prelim_unlocked_at`, `midterm_status`, `midterm_unlocked_at`, `finals_status`, `finals_unlocked_at`, `created_at`, `updated_at`, `downpayment_paid`, `downpayment_status`, `downpayment_unlocked_at`) VALUES
(307, 128, 'installment', 24294.00, 0.00, 6073.34, 6073.34, 6073.32, 0.00, 0.00, 0.00, 'locked', NULL, 'locked', NULL, 'locked', NULL, '2026-03-03 23:57:57', '2026-03-04 00:06:59', 0.00, 'locked', NULL),
(313, 130, 'full', 34526.00, 0.00, 8631.50, 8631.50, 8631.50, 0.00, 0.00, 0.00, 'locked', NULL, 'locked', NULL, 'locked', NULL, '2026-03-04 02:59:48', '2026-03-04 02:59:48', 0.00, 'locked', NULL),
(314, 132, 'installment', 21016.00, 0.00, 5254.00, 5254.00, 5254.00, 0.00, 0.00, 0.00, 'locked', NULL, 'locked', NULL, 'locked', NULL, '2026-03-04 03:42:02', '2026-03-04 03:42:02', 0.00, 'locked', NULL),
(319, 133, 'installment', 35276.00, 0.00, 10092.00, 10092.00, 10092.00, 0.00, 0.00, 0.00, 'locked', NULL, 'locked', NULL, 'locked', NULL, '2026-03-04 04:03:50', '2026-03-04 04:03:50', 0.00, 'locked', NULL),
(323, 135, 'installment', 23155.00, 0.00, 6051.67, 6051.67, 6051.66, 0.00, 0.00, 0.00, 'locked', NULL, 'locked', NULL, 'locked', NULL, '2026-03-04 05:22:07', '2026-03-04 05:22:07', 0.00, 'locked', NULL);

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
(1, 'Bachelor of Science in Accountancy', 'BSA', 'College', 4, 'A professional program covering financial accounting, auditing, taxation, and management advisory services.', 'BMDs', '2026-03-03 01:44:06'),
(2, 'Bachelor of Science in Customs Administration', 'BSCA', 'College', 4, 'A program focused on customs brokerage, tariff, trade, and border control management.', 'BMDs', '2026-03-03 01:44:06'),
(3, 'Bachelor of Science in Entrepreneurship', 'BSE', 'College', 4, 'A program developing entrepreneurial skills, business planning, and enterprise management.', 'BMDs', '2026-03-03 01:44:06'),
(4, 'Bachelor of Science in Real Estate Management', 'BSREM', 'College', 4, 'A program covering real estate appraisal, brokerage, property management, and real estate finance.', 'BMDs', '2026-03-03 01:44:06'),
(5, 'Computer Information Multimedia Technology', 'CIMT', 'College', 2, 'A 2-year program in computing, multimedia, and digital arts technology.', 'ICTD', '2026-03-03 01:44:06'),
(6, 'Bachelor of Science in Information Technology', 'BSIT', 'College', 4, 'A program in software development, networking, database systems, and information assurance.', 'ICTD', '2026-03-03 01:44:06'),
(23, 'Accountancy, Business and Management', 'ABM', 'SHS', 2, 'SHS strand focusing on business, accounting, economics, and management principles.', 'Academic Track', '2026-03-03 01:48:50'),
(24, 'General Academic Strand', 'GAS', 'SHS', 2, 'SHS strand offering a broad general academic curriculum for undecided learners.', 'Academic Track', '2026-03-03 01:48:50'),
(25, 'Humanities and Social Sciences Strand', 'HUMSS', 'SHS', 2, 'SHS strand focusing on humanities, social sciences, and communication arts.', 'Academic Track', '2026-03-03 01:48:50'),
(26, 'Information and Communication Technology', 'ICT', 'SHS', 2, 'SHS TVL strand focused on computer and information technology skills.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50'),
(27, 'Computer Systems Servicing NCII', 'CSS-NCII', 'SHS', 2, 'SHS TVL strand with TESDA National Certificate II in Computer Systems Servicing.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50'),
(28, 'Cookery NCII', 'COOKERY-NCII', 'SHS', 2, 'SHS Home Economics strand with TESDA National Certificate II in Cookery.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50'),
(29, 'Bread and Pastry Production NCII', 'BPP-NCII', 'SHS', 2, 'SHS Home Economics strand with TESDA National Certificate II in Bread and Pastry Production.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50'),
(30, 'Food and Beverages Services NCII', 'FBS-NCII-SHS', 'SHS', 2, 'SHS Home Economics strand with TESDA National Certificate II in Food and Beverages Services.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50'),
(31, 'Diploma in Travel and Tourism Technology (Leading to BSTM)', 'DTTT', 'TVET', 2, 'A diploma program in travel and tourism technology that may lead to a BSTM degree.', '', '2026-03-03 01:48:50'),
(32, '2-Yrs. Computer Information and Multimedia Technology', 'CIMT-TVET', 'TVET', 2, 'Two-year TVET program in computer information and multimedia technology.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(33, '2-Yrs. Cruise Ship Management', 'CSM', 'TVET', 2, 'Two-year TVET program in cruise ship operations and hospitality management.', 'Collge Diploma', '2026-03-03 01:48:50'),
(34, '2-Yrs. Tourism, Hotel and Restaurant Operations', 'THRO', 'TVET', 2, 'Two-year TVET program in tourism, hotel, and restaurant operations.', '', '2026-03-03 01:48:50'),
(35, 'Housekeeping NCII', 'HK-NCII', 'TVET', 1, 'TESDA National Certificate II program in Housekeeping.', '', '2026-03-03 01:48:50'),
(36, 'Bartending NCII', 'BART-NCII', 'TVET', 1, 'TESDA National Certificate II program in Bartending.', '', '2026-03-03 01:48:50'),
(37, 'Food and Beverages Services NCII', 'FBS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Food and Beverages Services.', '', '2026-03-03 01:48:50'),
(38, 'Front Office NCII', 'FO-NCII', 'TVET', 1, 'TESDA National Certificate II program in Front Office services.', '', '2026-03-03 01:48:50'),
(39, '3D Animation NCIII', '3DA-NCIII', 'TVET', 1, 'TESDA National Certificate III program in 3D Animation.', 'Collge Diploma', '2026-03-03 01:48:50'),
(40, 'Game Programming NCIII', 'GP-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Game Programming.', '', '2026-03-03 01:48:50'),
(41, 'Computer Systems Servicing NCII', 'CSS-NCII-TVET', 'TVET', 1, 'TESDA National Certificate II program in Computer Systems Servicing.', '', '2026-03-03 01:48:50'),
(42, 'Visual Graphic Design NCIII', 'VGD-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Visual Graphic Design.', '', '2026-03-03 01:48:50'),
(43, 'Travel Services NCII', 'TS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Travel Services.', '', '2026-03-03 01:48:50'),
(44, 'Tourism Promotion Services NCII', 'TPS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Tourism Promotion Services.', '', '2026-03-03 01:48:50'),
(45, 'Event Management Services NCIII', 'EMS-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Event Management Services.', '', '2026-03-03 01:48:50'),
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
(871, 5, 768),
(872, 5, 769),
(873, 5, 770),
(874, 5, 771),
(875, 5, 772),
(876, 5, 773),
(877, 5, 774),
(878, 5, 775),
(879, 5, 776),
(880, 5, 777),
(881, 5, 778),
(882, 5, 779),
(883, 5, 780),
(884, 5, 781),
(885, 5, 782),
(886, 5, 783),
(887, 5, 784),
(888, 5, 785),
(889, 5, 786),
(890, 5, 787),
(891, 5, 788),
(892, 5, 789),
(893, 5, 790),
(894, 5, 791),
(895, 5, 792),
(896, 5, 793),
(897, 5, 794),
(898, 5, 795),
(899, 5, 796),
(900, 5, 797),
(901, 5, 798),
(902, 5, 799),
(903, 5, 800),
(904, 5, 801),
(998, 6, 554),
(999, 6, 559),
(1000, 6, 564),
(1001, 6, 573),
(1058, 6, 574),
(1003, 6, 578),
(1004, 6, 587),
(1005, 6, 588),
(1006, 6, 591),
(1007, 6, 592),
(1008, 6, 597),
(1009, 6, 605),
(1010, 6, 802),
(1011, 6, 803),
(1012, 6, 804),
(1013, 6, 805),
(1014, 6, 806),
(1015, 6, 807),
(1016, 6, 808),
(1017, 6, 809),
(1018, 6, 810),
(1019, 6, 811),
(1020, 6, 812),
(1021, 6, 813),
(1022, 6, 814),
(1023, 6, 815),
(1024, 6, 816),
(1025, 6, 817),
(1026, 6, 818),
(1027, 6, 819),
(1028, 6, 820),
(1029, 6, 821),
(1030, 6, 822),
(1031, 6, 823),
(1032, 6, 824),
(1033, 6, 825),
(1034, 6, 826),
(1035, 6, 827),
(1036, 6, 828),
(1037, 6, 829),
(1038, 6, 830),
(1039, 6, 831),
(1040, 6, 832),
(1041, 6, 833),
(1042, 6, 834),
(1043, 6, 835),
(1044, 6, 836),
(1045, 6, 837),
(1046, 6, 838),
(1047, 6, 839),
(1048, 6, 840),
(1049, 6, 841),
(1050, 6, 842),
(1051, 6, 843),
(1052, 6, 844),
(1053, 6, 845),
(1054, 6, 846),
(1055, 6, 847),
(1056, 6, 848),
(851, 23, 851),
(852, 25, 852),
(853, 27, 854),
(854, 29, 851),
(856, 29, 852),
(855, 29, 854),
(850, 31, 850);

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
(15, 'Independence Day', '2026-06-12', 'holiday', 'Philippine Independence Day — no classes', '2026-02-01 00:52:41'),
(16, 'asa', '2026-03-04', 'enrollment', '123', '2026-03-04 06:05:41');

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

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `token`, `role`, `expires_at`, `created_at`) VALUES
(37, 4, '717fda5fa49108fbca4827feaf89fff98b275819034564e93f97374239ed7543', 'registrar', '2026-03-04 07:35:36', '2026-03-03 22:35:37'),
(39, 3, 'd8c915c8423d552ec837f0b0b121267d6717200f4a810fcc5f005532dc073dbe', 'accounting', '2026-03-04 07:36:43', '2026-03-03 22:36:43'),
(41, 127, 'fbe5e333220d50a8e4fced808a3aff0634243fbe9d6454b5f72c885d2647c136', 'student', '2026-03-04 10:54:44', '2026-03-04 01:54:44'),
(42, 129, 'd793e04c38412c329c0f6c6ebbba70e8a38f736324486b46ec9184186929e843', 'student', '2026-03-04 11:54:03', '2026-03-04 02:54:03'),
(44, 131, '800180d0173f3cb2159117023a99292272d9661030a7cbbd36cb10fba7c0acc5', 'student', '2026-03-04 12:55:55', '2026-03-04 03:55:55'),
(45, 132, 'fbfed6e355c498e93f32f7c50ebca367b7ef1272c332727bab6ac71537a3ab62', 'student', '2026-03-04 12:57:06', '2026-03-04 03:57:06'),
(46, 2, '5cc758294094d616e0ad6b8eb2b7e22ff6cdd78c52ab7bb5f17d87239bd5d09e', 'admin', '2026-03-04 13:46:26', '2026-03-04 04:46:26'),
(47, 134, 'e0b00034ba2cab66da7c454f03d22fc4f8ec1b282987061893e812c0c732790c', 'student', '2026-03-04 14:11:44', '2026-03-04 05:11:44');

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

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_number`, `first_name`, `last_name`, `middle_name`, `suffix`, `lrn_no`, `sex`, `religion`, `age`, `place_of_birth`, `citizenship`, `mother_tongue`, `is_indigenous`, `has_special_needs`, `special_needs_details`, `has_assistive_tech`, `assistive_tech_details`, `strand`, `learning_delivery`, `last_school_attended`, `psa_birth_cert_no`, `guardian_name`, `guardian_address`, `email`, `phone`, `date_of_birth`, `address`, `emergency_contact`, `emergency_phone`, `program`, `year_level`, `gpa`, `enrollment_status`, `student_type`, `tor_eval_status`, `student_category`, `payment_status`, `approval_status`, `payment_method`, `payment_plan`, `semester`, `is_scholar`, `scholar_type`, `scholar_grantor`, `scholarship_amount`, `gcash_reference`, `gcash_amount`, `gcash_date`, `gcash_transaction_id`, `accounting_approved_by`, `accounting_approved_at`, `accounting_notes`, `profile_picture`, `tor_file`, `psa_file`, `enrollment_date`, `created_at`, `guardian_contact`, `tvet_type`) VALUES
(128, 127, 'STU-2026-0001', 'Shane Carlo', 'Nodado', 'Binoya', '', '1', 'Male', '1', 1, '1', '1', '1', 0, 0, '0', 0, '', NULL, NULL, '0', '', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'studenttranscollege', '09300987316', '0111-11-11', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'Bachelor of Science in Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'installment', '1st Semester, AY 2026-2027', 0, '', '', 0.00, '1234', 6074.00, '2026-03-03', 'TXN-1772577384955-A2FLF', 3, '2026-03-03 22:36:56', '', NULL, 'tor_128_1772577325.pdf', NULL, '2026-03-03', '2026-03-03 22:35:24', '09300987316', ''),
(129, 128, 'STU-2026-0002', 'Shane Carlo', 'Nodado', 'Binoya', '', '1', 'Male', '1', 1, '1', '1', '1', 0, 0, '0', 0, '', NULL, NULL, '0', '1', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'studenttranscollege1', '09300987316', '1111-11-11', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'Bachelor of Science in Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester, AY 2026-2028', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-04 03:40:56', '', NULL, 'tor_129_1772591485.pdf', NULL, '2026-03-04', '2026-03-04 02:31:25', '09300987316', ''),
(130, 129, 'STU-2026-0003', 'Shane Carlo', 'Nodado', 'Binoya', '', '1', 'Male', '1', 1, '1', '1', '1', 0, 0, '0', 0, '0', '', '', 'Elementary - 1 (1)', '', '1', '1', 'studenttranscollege2', '09300987316', '1111-11-11', '118 Avocado Street Purok 3 New Cabalan', '1', '1', 'Bachelor of Science in Information Technology', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '1st Semester, AY 1', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-04 03:00:09', '', NULL, NULL, NULL, '2026-03-04', '2026-03-04 02:54:02', '1', ''),
(131, 130, 'STU-2026-0004', 'Shane Carlo', 'Nodado', 'Binoya', '', '1', 'Male', '1', 19, '1', '1', '1', 0, 0, '0', 0, '', NULL, NULL, '0', '1', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'studenttranscollege3', '09300987316', '2002-11-22', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'Bachelor of Science in Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester, AY 2026-2028', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-04 03:40:59', '', NULL, 'tor_131_1772594857.pdf', NULL, '2026-03-04', '2026-03-04 03:27:37', '09300987316', ''),
(132, 131, 'STU-2026-0005', 'Shane Carlo', 'Nodado', 'Binoya', '', '1', 'Male', '1', 19, '1', '1', '1', 0, 0, '0', 0, '', NULL, NULL, '0', '1', '1', '118 Avocado Street Purok 3 New Cabalan', 'studenttranscollege4', '09300987316', '1111-11-11', '118 Avocado Street Purok 3 New Cabalan', '1', '09300987316', 'Bachelor of Science in Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'Cash', 'installment', '1st Semester, AY 1', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-04 03:41:03', '', NULL, 'tor_132_1772595567.pdf', NULL, '2026-03-04', '2026-03-04 03:39:27', '09300987316', ''),
(133, 132, 'STU-2026-0006', 'Shane Carlo', 'Nodado', 'Binoya', '', '1', 'Male', '1', 1, '1', '1', '1', 0, 0, '0', 0, '0', '', '', 'Elementary - 1 (1)', '1', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'studenttranscollege5', '09300987316', '2000-11-22', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'Bachelor of Science in Information Technology', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'installment', '1st Semester, AY 1', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-04 04:03:28', '', NULL, NULL, NULL, '2026-03-04', '2026-03-04 03:57:05', '09300987316', ''),
(134, 133, 'STU-2026-0007', 'Shane Carlo', 'Nodado', 'Binoya', '', '1', 'Male', '1', 1, '1', '1', '1', 0, 0, '0', 0, '', NULL, NULL, '0', '1', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'studentx1', '09300987316', '2002-11-22', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'Bachelor of Science in Information Technology', '1st Year', 0.00, 'Pending', 'Transferee', 'Evaluated', 'College', 'Pending', 'Pending', 'GCash', 'full', '1st Semester, AY 2025-2026', 0, '', '', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'tor_134_1772599697.pdf', NULL, '2026-03-04', '2026-03-04 04:48:17', '09300987316', ''),
(135, 134, 'STU-2026-0008', 'Shane Carlo', 'Nodado', 'Binoya', '', '1', 'Male', '1', 1, '1', '1', '1', 0, 0, '0', 0, '', NULL, NULL, '0', '1', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'studentx3', '09300987316', '2002-11-22', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'Bachelor of Science in Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'installment', '1st Semester, AY 2025-2026', 0, '', '', 0.00, '12356', 5000.00, '2026-03-04', 'TXN-1772601118868-BE7PO', 3, '2026-03-04 05:12:13', '', NULL, 'tor_135_1772600134.pdf', NULL, '2026-03-04', '2026-03-04 04:55:34', '09300987316', '');

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

--
-- Dumping data for table `tor_evaluations`
--

INSERT INTO `tor_evaluations` (`id`, `student_id`, `status`, `credited_units`, `approved_units`, `credited_subjects`, `credited_course_ids`, `registrar_notes`, `evaluated_by`, `evaluated_at`, `created_at`, `updated_at`) VALUES
(52, 128, 'Evaluated', 14, 0, '[{\"courseId\":555,\"code\":\"AEC109\",\"name\":\"Managerial Economics\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":554,\"code\":\"AEC111\",\"name\":\"Financial Accounting and Reporting\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":805,\"code\":\"GE105-IT\",\"name\":\"Mathematics in the Modern World\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":808,\"code\":\"NSTP1-IT\",\"name\":\"National Service Training Program 1\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":807,\"code\":\"PE1-IT\",\"name\":\"Physical Education 1 (Aquatic)\",\"credits\":2,\"creditedFrom\":\"0\"}]', '[555,554,805,808,807]', '', 4, '2026-03-03 22:35:56', '2026-03-03 22:35:24', '2026-03-03 23:56:41'),
(55, 129, 'Evaluated', 18, 8, '[{\"courseId\":555,\"code\":\"AEC109\",\"name\":\"Managerial Economics\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":554,\"code\":\"AEC111\",\"name\":\"Financial Accounting and Reporting\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":802,\"code\":\"CC100-IT\",\"name\":\"Introduction to Computing\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":803,\"code\":\"CC101-IT\",\"name\":\"Computer Programming 1\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":806,\"code\":\"GE100-IT\",\"name\":\"Conversational English and Personality Development\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":805,\"code\":\"GE105-IT\",\"name\":\"Mathematics in the Modern World\",\"credits\":3,\"creditedFrom\":\"0\"}]', '[555,554,802,803,806,805]', '', 4, '2026-03-04 02:31:41', '2026-03-04 02:31:25', '2026-03-04 02:31:41'),
(58, 131, 'Evaluated', 18, 8, '[{\"courseId\":555,\"code\":\"AEC109\",\"name\":\"Managerial Economics\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":554,\"code\":\"AEC111\",\"name\":\"Financial Accounting and Reporting\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":802,\"code\":\"CC100-IT\",\"name\":\"Introduction to Computing\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":806,\"code\":\"GE100-IT\",\"name\":\"Conversational English and Personality Development\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":805,\"code\":\"GE105-IT\",\"name\":\"Mathematics in the Modern World\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":808,\"code\":\"NSTP1-IT\",\"name\":\"National Service Training Program 1\",\"credits\":3,\"creditedFrom\":\"0\"}]', '[555,554,802,806,805,808]', '', 4, '2026-03-04 03:27:59', '2026-03-04 03:27:37', '2026-03-04 03:27:59'),
(61, 132, 'Evaluated', 23, 6, '[{\"courseId\":555,\"code\":\"AEC109\",\"name\":\"Managerial Economics\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":554,\"code\":\"AEC111\",\"name\":\"Financial Accounting and Reporting\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":806,\"code\":\"GE100-IT\",\"name\":\"Conversational English and Personality Development\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":805,\"code\":\"GE105-IT\",\"name\":\"Mathematics in the Modern World\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":804,\"code\":\"IT-CMT015-IT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":808,\"code\":\"NSTP1-IT\",\"name\":\"National Service Training Program 1\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":807,\"code\":\"PE1-IT\",\"name\":\"Physical Education 1 (Aquatic)\",\"credits\":2,\"creditedFrom\":\"0\"},{\"courseId\":574,\"code\":\"AEC105\",\"name\":\"Intermediate Accounting 2\",\"credits\":3,\"creditedFrom\":\"0\"}]', '[555,554,806,805,804,808,807,574]', '', 4, '2026-03-04 03:40:01', '2026-03-04 03:39:27', '2026-03-04 03:40:01'),
(64, 134, 'Evaluated', 20, 3, '[{\"courseId\":554,\"code\":\"AEC111\",\"name\":\"Financial Accounting and Reporting\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":802,\"code\":\"CC100-IT\",\"name\":\"Introduction to Computing\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":803,\"code\":\"CC101-IT\",\"name\":\"Computer Programming 1\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":806,\"code\":\"GE100-IT\",\"name\":\"Conversational English and Personality Development\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":805,\"code\":\"GE105-IT\",\"name\":\"Mathematics in the Modern World\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":804,\"code\":\"IT-CMT015-IT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":807,\"code\":\"PE1-IT\",\"name\":\"Physical Education 1 (Aquatic)\",\"credits\":2,\"creditedFrom\":\"0\"}]', '[554,802,803,806,805,804,807]', '', 4, '2026-03-04 04:48:40', '2026-03-04 04:48:17', '2026-03-04 04:48:40'),
(67, 135, 'Evaluated', 17, 9, '[{\"courseId\":574,\"code\":\"AEC105\",\"name\":\"Intermediate Accounting 2\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":554,\"code\":\"AEC111\",\"name\":\"Financial Accounting and Reporting\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":805,\"code\":\"GE105-IT\",\"name\":\"Mathematics in the Modern World\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":804,\"code\":\"IT-CMT015-IT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":808,\"code\":\"NSTP1-IT\",\"name\":\"National Service Training Program 1\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":807,\"code\":\"PE1-IT\",\"name\":\"Physical Education 1 (Aquatic)\",\"credits\":2,\"creditedFrom\":\"0\"}]', '[574,554,805,804,808,807]', '', 4, '2026-03-04 05:11:14', '2026-03-04 04:55:34', '2026-03-04 05:11:14');

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

--
-- Dumping data for table `tuition_fees`
--

INSERT INTO `tuition_fees` (`id`, `student_id`, `units`, `tuition_fee`, `miscellaneous_fee`, `registration_fee`, `laboratory_fee`, `energy_fee`, `subtotal`, `discount`, `installment_fee`, `total_assessment`, `created_at`, `updated_at`) VALUES
(928, 128, 12, 7800.00, 6688.00, 700.00, 7600.00, 756.00, 24544.00, 0.00, 750.00, 25294.00, '2026-03-03 22:35:56', '2026-03-04 03:24:42'),
(990, 129, 8, 5200.00, 6688.00, 700.00, 7600.00, 504.00, 21692.00, 0.00, 0.00, 21692.00, '2026-03-04 02:31:42', '2026-03-04 02:31:42'),
(1000, 130, 26, 16900.00, 6688.00, 700.00, 7600.00, 1638.00, 34526.00, 0.00, 0.00, 34526.00, '2026-03-04 02:54:03', '2026-03-04 03:55:34'),
(1019, 131, 8, 5200.00, 6688.00, 700.00, 7600.00, 504.00, 21692.00, 0.00, 0.00, 21692.00, '2026-03-04 03:27:59', '2026-03-04 03:28:27'),
(1025, 132, 6, 3900.00, 6688.00, 700.00, 7600.00, 378.00, 20266.00, 0.00, 750.00, 21016.00, '2026-03-04 03:40:01', '2026-03-04 03:42:26'),
(1042, 133, 26, 16900.00, 6688.00, 700.00, 7600.00, 1638.00, 34526.00, 0.00, 750.00, 35276.00, '2026-03-04 03:57:06', '2026-03-04 05:37:09'),
(1056, 134, 3, 1950.00, 6688.00, 700.00, 7600.00, 189.00, 18127.00, 0.00, 0.00, 18127.00, '2026-03-04 04:48:40', '2026-03-04 04:48:40'),
(1058, 135, 9, 5850.00, 6688.00, 700.00, 7600.00, 567.00, 22405.00, 0.00, 750.00, 23155.00, '2026-03-04 05:11:14', '2026-03-04 06:27:25');

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
(4, 'registrar@example.com', '$2y$12$mBRinZXeLFpyge/D499ceeBuVHsRqy6OiVNtDm.YuSdfAgVdFNrWG', 'registrar', 'Registrar', 'Admin', '2026-01-29 08:54:49'),
(127, 'studenttranscollege', '$2y$12$UhdiDXxCGl3tl1lAI6tWj.RdgK2IUvFMmMPdKR8y2vSWgiud3KG4i', 'student', 'Shane Carlo', 'Nodado', '2026-03-03 22:35:24'),
(128, 'studenttranscollege1', '$2y$12$Rp0xAuiGsP/8SfexCT896elupDx1zEyUeeo1EKepPXw0jNoo3l/ra', 'student', 'Shane Carlo', 'Nodado', '2026-03-04 02:31:25'),
(129, 'studenttranscollege2', '$2y$12$/tQ30hSsmjrHR.Y5wpfn1euHA2aAknmeIuOavSWqDqjTu.yjyzRxq', 'student', 'Shane Carlo', 'Nodado', '2026-03-04 02:54:02'),
(130, 'studenttranscollege3', '$2y$12$Eri9aFoLZ7YkNo0r4XCkq.iaAXUQtKWTpo0s7kktQRGMNNibQ.sG.', 'student', 'Shane Carlo', 'Nodado', '2026-03-04 03:27:37'),
(131, 'studenttranscollege4', '$2y$12$urwG03qbAPTg73G4zGcFr.5HNXkUvytCKKXFz8JMjv2ti3mSJDmNm', 'student', 'Shane Carlo', 'Nodado', '2026-03-04 03:39:27'),
(132, 'studenttranscollege5', '$2y$12$DFVBvu7MzTMY6DP1Exsr6ehGmyBFRsD4vBxsxM6w3ZUXNy09oIZdW', 'student', 'Shane Carlo', 'Nodado', '2026-03-04 03:57:05'),
(133, 'studentx1', '$2y$12$/ebRcsLD7E4H0PCzVUufQeT3GoTXOV93Ugm9Qc9jMUZWNTTWmSH/2', 'student', 'Shane Carlo', 'Nodado', '2026-03-04 04:48:17'),
(134, 'studentx3', '$2y$12$hOnElqVjHN/5Itpj8YefluqVWaq9o3BO6mZWUdBvvDGsQ0rU4MGw6', 'student', 'Shane Carlo', 'Nodado', '2026-03-04 04:55:34');

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
-- Indexes for table `fee_config`
--
ALTER TABLE `fee_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_key` (`category`,`fee_key`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=855;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=759;

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
-- AUTO_INCREMENT for table `fee_config`
--
ALTER TABLE `fee_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `payment_notices`
--
ALTER TABLE `payment_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=327;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `program_courses`
--
ALTER TABLE `program_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1059;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `school_events`
--
ALTER TABLE `school_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1080;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

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

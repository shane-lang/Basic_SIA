-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 03, 2026 at 04:27 PM
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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_privacy_summary` (IN `days_back` INT)   BEGIN
    SELECT
        field_key,
        user_role,
        action_taken,
        COUNT(*)              AS occurrences,
        MAX(accessed_at)      AS last_accessed,
        COUNT(DISTINCT user_id) AS unique_users
    FROM privacy_access_log
    WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL days_back DAY)
    GROUP BY field_key, user_role, action_taken
    ORDER BY occurrences DESC;
END$$

DELIMITER ;

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accounting_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `accounting_reviewed_by` int(11) DEFAULT NULL,
  `accounting_reviewed_at` datetime DEFAULT NULL,
  `accounting_notes` text DEFAULT NULL,
  `fee_impact` decimal(10,2) DEFAULT 0.00,
  `new_total_assessment` decimal(10,2) DEFAULT 0.00,
  `accounting_forwarded_at` datetime DEFAULT NULL
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
(1, '2026-03-03 02:00:00', '2026-03-17 22:56:00', '1st sem ay 2026-2027', 0, NULL, '2026-03-03 14:56:59'),
(2, '2026-03-22 01:00:00', '2026-03-27 22:56:00', '1st sem ay 2026-2027', 0, NULL, '2026-03-22 15:51:07'),
(3, '2026-03-29 01:00:00', '2026-04-02 22:56:00', '1st sem ay 2026-2027', 0, NULL, '2026-03-28 19:02:09'),
(4, '2026-03-30 01:00:00', '2026-03-30 22:56:00', '1st sem ay 2026-2027', 0, NULL, '2026-03-30 17:37:06'),
(5, '2026-03-31 16:25:00', '2026-04-07 16:25:00', '', 0, NULL, '2026-03-31 08:25:11'),
(6, '2026-03-31 16:25:00', '2026-04-07 16:25:00', '', 0, NULL, '2026-04-03 05:38:54'),
(7, '2026-03-31 16:25:00', '2026-04-07 16:25:00', '', 1, NULL, '2026-04-03 05:42:10');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `date`, `type`, `priority`, `icon`, `created_at`, `updated_at`) VALUES
(1, 'Enrollment for 1st Semester AY 2026 is NOW OPEN', 'All students must complete their enrollment. Coordinate with your Academic Adviser for pre-enrollment requirements.', '2026-01-31', 'enrollment', 'high', '📋', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(2, 'Tuition Fee Payment Deadline', 'Tuition fees must be paid within 30 days from enrollment. Submit your GCash or Cash payment proof through the portal.', '2026-01-31', 'payment', 'high', '💳', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(3, 'Library Hours Extended', 'The university library is now open Monday–Saturday, 7:00 AM to 8:00 PM to accommodate students during enrollment.', '2026-01-28', 'school', 'normal', '🏫', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(4, 'Grade Submission Portal Now Available', 'Faculty members may now submit grades through the SIA portal. Students can view their grades once submission is complete.', '2026-01-29', 'school', 'normal', '🏫', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(5, 'System Maintenance — Every Sunday 12 AM–4 AM', 'The Student Information System undergoes weekly maintenance every Sunday.', '2026-01-29', 'system', 'normal', '⚙️', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(6, 'wew', 'wew', '2026-03-04', 'school', 'normal', '📢', '2026-03-04 05:57:28', '2026-03-11 16:27:11');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
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
(139, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 144, 'Full payment verified, enrollment approved for student ID 144', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 16:30:57'),
(140, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 145, 'Downpayment verified ₱5,000.00, installment enrollment approved for student ID 145 (AR: AR-20260033)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 18:02:31'),
(141, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 143, 'Payment rejected for student ID 143. Log: 140. Reason: zxas', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 19:43:31'),
(142, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 144, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 02:01:29'),
(143, 158, 'shane2', 'student', 'RE_ENROLL', 'student', 145, 'Student 145 re-enrolled: 1st Year 2nd Semester, AY 2026-2027 → 1st Year 1st sem ay 2025-2026 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 02:22:53'),
(144, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 145, 'Full payment verified, enrollment approved for student ID 145', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 20:53:22'),
(145, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 143, 'Full payment verified, enrollment approved for student ID 143', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 20:53:25'),
(146, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 144, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 22:38:07'),
(147, 2, 'admin@example.com', 'admin', 'DELETE_DEPARTMENT', 'department', 0, 'Deleted department: ml;', '{\"name\":\"ml;\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 22:38:27'),
(148, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"AEC111\\\",\\\"AEC109\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-19 06:42:21\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 22:49:31'),
(149, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"AEC111\\\",\\\"AEC109\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-19 06:42:21\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 22:49:46'),
(150, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"AEC111\\\",\\\"AEC109\\\",\\\"CC100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-19 06:49:46\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 22:56:32'),
(151, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"AEC111\\\",\\\"AEC109\\\",\\\"CC101\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-19 06:56:32\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-18 22:59:31'),
(152, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 145, 'Verified Prelim payment ₱10,471.67 for student ID 145 (OR: AR-20260036)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 17:10:58'),
(153, 3, 'accounting@example.com', 'accounting', 'APPROVED_PERMIT', 'exam_permit', 11, 'Exam permit approvedd. ID: 11. Permit#: EP-20260319-STU20260003-PRE. Remarks: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 18:23:35'),
(154, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 146, 'Full payment verified, enrollment approved for student ID 146', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 19:07:47'),
(155, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 147, 'Full payment verified for student ID 147 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 19:48:03'),
(156, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 148, 'Full payment verified for student ID 148 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 19:54:47'),
(157, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 149, 'Full payment verified for student ID 149 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 20:06:45'),
(158, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 150, 'Full payment verified for student ID 150 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 20:23:44'),
(159, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 151, 'Full payment verified for student ID 151 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 20:25:30'),
(160, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 148, 'Admin viewed student record: Shane Carlo Nodado', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:24:34'),
(161, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 152, 'Full payment verified for student ID 152 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:32:38'),
(162, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 153, 'Full payment verified for student ID 153 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:41:04'),
(163, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 153, 'Registration confirmed for shane binoya (STU-2026-0007). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:41:23'),
(164, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 154, 'Downpayment verified ₱9,000.00 for student ID 154 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 17:16:14'),
(165, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 154, 'Registration confirmed for shane binoya (STU-2026-0008). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 17:16:52'),
(166, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 154, 'Verified Prelim payment ₱7,712.34 for student ID 154 (OR: AR-20260046)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 17:24:06'),
(167, 3, 'accounting@example.com', 'accounting', 'BULK_SEND_NOTICE', 'notice', 0, 'Bulk Prelim notice: 0 sent, 1 skipped. Category: ALL', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 17:56:10'),
(168, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 154, 'Sent Midterm notice to student ID 154 (₱7,712.33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:07:37'),
(169, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 154, 'Verified Midterm payment ₱8,000.00 for student ID 154 (OR: AR-20260047)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:08:42'),
(170, 3, 'accounting@example.com', 'accounting', 'BULK_SEND_NOTICE', 'notice', 0, 'Bulk Prelim notice: 0 sent, 1 skipped. Category: ALL', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:11:19'),
(171, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 143, 'Sent Prelim notice to student ID 143 (₱6,955.50)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:17:09'),
(172, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 150, 'Sent Prelim notice to student ID 150 (₱7,846.75)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:17:13'),
(173, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 153, 'Sent Prelim notice to student ID 153 (₱0.00)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:30:18'),
(174, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 154, 'Sent Finals notice to student ID 154 (₱7,424.66)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:30:59'),
(175, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 154, 'Verified Finals payment ₱7,424.66 for student ID 154 (OR: AR-20260048)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 18:31:38'),
(176, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 16:30:23'),
(177, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 17:00:41'),
(178, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 17:21:19'),
(179, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 17:30:08'),
(180, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 17:48:41'),
(181, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 17:52:00'),
(182, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 17:55:22'),
(183, 3, 'accounting@example.com', 'accounting', 'BULK_SEND_NOTICE', 'notice', 0, 'Bulk Prelim notice: 0 sent, 1 skipped. Category: ALL', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 19:37:15'),
(184, 3, 'accounting@example.com', 'accounting', 'APPROVED_PERMIT', 'exam_permit', 12, 'Exam permit approvedd. ID: 12. Permit#: EP-20260321-STU20260007-PRE. Remarks: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 19:44:34'),
(185, 3, 'accounting@example.com', 'accounting', 'APPROVE_SCHOLARSHIP', 'student', 155, 'Scholarship approved: Full Scholarship ₱31,387.00 by accounting@example.com (Full Tuition)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 13:19:11'),
(186, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 155, 'Registration confirmed for Shane Gongora (STU-2026-0009). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 13:19:44'),
(187, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 155, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 14:48:10'),
(188, 3, 'accounting@example.com', 'accounting', 'CREATE_SCHOLARSHIP_PREAPPROVAL', 'scholarship_pre_approvals', 1, 'Pre-approval created: Full Scholarship by City of Olongapo. Code: SCH-82NPKTUS', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 15:24:46'),
(189, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 155, 'Payment rejected for student ID 155. Log: 158. Reason: s', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 15:49:31'),
(190, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 156, 'Registration confirmed for Shane Gongora (STU-2026-0010). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 15:50:18'),
(191, 169, 'shane6', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 45, 'Add request submitted by student ID 156 for course ID 45. Reason: wdw', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 15:51:33'),
(192, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:01:18'),
(193, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:01:23'),
(194, 3, 'accounting@example.com', 'accounting', 'CREATE_SCHOLARSHIP_PREAPPROVAL', 'scholarship_pre_approvals', 2, 'Pre-approval created: Full Scholarship by City of Olongapo. Code: SCH-NV9DU8BX', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:04:59'),
(195, 3, 'accounting@example.com', 'accounting', 'APPROVE_SCHOLARSHIP', 'student', 157, 'Scholarship approved: Full Scholarship ₱31,387.00 by accounting@example.com (Full Tuition)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:06:48'),
(196, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 157, 'Registration confirmed for Shane Gongora (STU-2026-0011). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:11:38'),
(197, 3, 'accounting@example.com', 'accounting', 'CREATE_SCHOLARSHIP_PREAPPROVAL', 'scholarship_pre_approvals', 3, 'Pre-approval created: Full Scholarship by aesa. Code: SCH-5HJYF4NQ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:13:32'),
(198, 3, 'accounting@example.com', 'accounting', 'CREATE_SCHOLARSHIP_PREAPPROVAL', 'scholarship_pre_approvals', 4, 'Pre-approval created: Full Scholarship by . Code: SCH-ZMP5J3XG', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:17:00'),
(199, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 157, 'Payment rejected for student ID 157. Log: 159. Reason: ww', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:17:52'),
(200, 3, 'accounting@example.com', 'accounting', 'APPROVE_SCHOLARSHIP', 'student', 159, 'Scholarship approved: Full Scholarship ₱31,387.00 by accounting@example.com (Full Tuition)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:18:03'),
(201, 3, 'accounting@example.com', 'accounting', 'APPROVE_SCHOLARSHIP', 'student', 158, 'Scholarship approved: Full Scholarship ₱31,387.00 by accounting@example.com (Full Tuition)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:18:06'),
(202, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 159, 'Registration confirmed for Shane Gongora (STU-2026-0013). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:18:44'),
(203, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 158, 'Registration confirmed for Shane Gongora (STU-2026-0012). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:18:51'),
(204, 172, 'shane24', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 45, 'Add request submitted by student ID 159 for course ID 45. Reason: dsd', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:30:26'),
(205, 172, 'shane24', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 11, 'Add request submitted by student ID 159 for course ID 11. Reason: q', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:34:00'),
(206, 172, 'shane24', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 23, 'Add request submitted by student ID 159 for course ID 23. Reason: s', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:38:58'),
(207, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 10, 'Add/Drop request #10 Approved by Accounting. Fee impact: ₱4,039.00. New total: ₱10,456.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:37:10'),
(208, 165, 'shane@gmail.com', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 11, 'Add request submitted by student ID 152 for course ID 11. Reason: dwdwad', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:38:24'),
(209, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 11, 'Add/Drop request #11 Approved by Accounting. Fee impact: ₱4,039.00. New total: ₱35,426.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:38:48'),
(210, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 11, 'Add/Drop request #11 (Add course 11 for student 152) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:39:21'),
(211, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 9, 'Add/Drop request #9 Approved by Accounting. Fee impact: ₱4,039.00. New total: ₱10,456.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:43:40'),
(212, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 8, 'Add/Drop request #8 Approved by Accounting. Fee impact: ₱4,278.00. New total: ₱10,695.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:43:44'),
(213, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 7, 'Add/Drop request #7 Approved by Accounting. Fee impact: ₱4,278.00. New total: ₱4,278.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:43:48'),
(214, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 9, 'Add/Drop request #9 (Add course 11 for student 159) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:43:55'),
(215, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 7, 'Add/Drop request #7 (Add course 45 for student 156) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:43:58'),
(216, 172, 'shane24', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 16, 'Add request submitted by student ID 159 for course ID 16. Reason: s', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:54:02'),
(217, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 12, 'Add/Drop request #12 Approved by Accounting. Fee impact: ₱0.00. New total: ₱0.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:57:42'),
(218, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 12, 'Add/Drop request #12 (Add course 16 for student 159) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:57:50'),
(219, 165, 'shane@gmail.com', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 45, 'Add request submitted by student ID 152 for course ID 45. Reason: wd', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:59:19'),
(220, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 13, 'Add/Drop request #13 Approved by Accounting. Fee impact: ₱4,278.00. New total: ₱37,804.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:59:39'),
(221, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 13, 'Add/Drop request #13 (Add course 45 for student 152) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:59:55'),
(222, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 06:02:32'),
(223, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 06:07:01'),
(224, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 153, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 06:07:09'),
(225, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 06:10:24'),
(226, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 160, 'Downpayment verified ₱8,034.25 for student ID 160 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 06:25:51'),
(227, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 160, 'Registration confirmed for shane binoya (STU-2026-0014). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 06:26:36'),
(228, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 161, 'Downpayment verified ₱7,499.50 for student ID 161 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 14:10:52'),
(229, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 162, 'Downpayment verified ₱7,499.50 for student ID 162 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 14:16:46'),
(230, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 161, 'Registration confirmed for Dave Zarene Cuevas (STU-2026-0015). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 14:17:25'),
(231, 161, 'shane1', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 45, 'Add request submitted by student ID 148 for course ID 45. Reason: k', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 14:38:24'),
(232, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 14, 'Add/Drop request #14 Approved by Accounting. Fee impact: ₱4,278.00. New total: ₱33,526.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 14:38:56'),
(233, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 14, 'Add/Drop request #14 (Add course 45 for student 148) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 14:39:20'),
(234, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 163, 'Downpayment verified ₱7,499.50 for student ID 163 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 07:17:54'),
(235, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 10, 'Add/Drop request #10 (Add course 23 for student 159) Rejected by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 07:29:38'),
(236, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 163, 'Registration confirmed for Claire Shannaiah (STU-2026-0017). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 07:30:23'),
(237, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 152, 'Admin viewed student record: shane binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 07:31:36'),
(238, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 165, 'Full payment verified for student ID 165 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 07:55:52'),
(239, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 165, 'Registration confirmed for Shane Carlo Nodado (STU-2026-0019). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 07:57:50'),
(240, 178, 'nodadoshanecarlo111@gmail.com', 'student', 'RE_ENROLL', 'student', 165, 'Student 165 re-enrolled: 1st Year 2nd Semester, AY 2025-2026 → 1st Year 1st sem ay 2025-2026 (type: Transferee → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 07:58:37'),
(241, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 155, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 05:40:10'),
(242, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 166, 'Full payment verified for student ID 166 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 06:11:18'),
(243, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 167, 'Full payment verified for student ID 167 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:00:53'),
(244, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 167, 'Registration confirmed for Shane Carlo Nodado (STU-2026-0021). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:01:15'),
(245, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 168, 'Downpayment verified ₱7,499.50 for student ID 168 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:21:05'),
(246, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 168, 'Registration confirmed for Shane Carlo Nodado (STU-2026-0022). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:21:22'),
(247, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 166, 'Registration confirmed for Shane Carlo Nodado (STU-2026-0020). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:25:44'),
(248, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 169, 'Downpayment verified ₱7,500.00 for student ID 169 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:47:44'),
(249, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 162, 'Registration confirmed for Shane Carlo Nodado (STU-2026-0016). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:48:37'),
(250, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 169, 'Registration confirmed for john bautista (STU-2026-0023). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:48:57'),
(251, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 170, 'Downpayment verified ₱8,000.00 for student ID 170 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 07:57:09'),
(252, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 171, 'Full payment verified for student ID 171 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 10:09:58'),
(253, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 171, 'Registration confirmed for Ana Binoya (STU-2026-0025). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 10:13:35'),
(254, 184, 'anamariebinoya0909@gmail.com', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 4, 'Add request submitted by student ID 171 for course ID 4. Reason: needed to add this subject', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 11:43:03'),
(255, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 15, 'Add/Drop request #15 Approved by Accounting. Fee impact: ₱4,039.00. New total: ₱33,287.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 11:47:22'),
(256, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 15, 'Add/Drop request #15 (Add course 4 for student 171) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 11:48:31'),
(257, 184, 'anamariebinoya0909@gmail.com', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 45, 'Add request submitted by student ID 171 for course ID 45. Reason: sd', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:39:26'),
(258, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 16, 'Add/Drop request #16 Approved by Accounting. Fee impact: ₱4,278.00. New total: ₱35,665.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:39:44'),
(259, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 16, 'Add/Drop request #16 (Add course 45 for student 171) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:57:59'),
(260, 184, 'anamariebinoya0909@gmail.com', 'student', 'RE_ENROLL', 'student', 171, 'Student 171 re-enrolled: 1st Year 2nd Semester, AY 2025-2026 → 1st Year 1st sem ay 2025-2026 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 20:56:00'),
(261, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 171, 'Full payment verified for student ID 171 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 21:08:01'),
(262, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 170, 'Registration confirmed for Shane Carlo Nodado (STU-2026-0024). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:41:35'),
(263, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 172, 'Downpayment verified ₱7,500.00 for student ID 172 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:53:13'),
(264, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 172, 'Registration confirmed for Ana Binoya (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:54:57'),
(265, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 9, 'Updated faculty: Liza Dela Cruz', '{\"id\":\"9\",\"user_id\":\"141\",\"faculty_id\":\"FAC-2024-007\",\"first_name\":\"Liza\",\"last_name\":\"Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Systems and Architecture\",\"subjects\":\"[\\\"IT110\\\",\\\"IT104\\\",\\\"IT-CMT015-IT\\\",\\\"EMC203-IT\\\",\\\"EMC207\\\",\\\"CC100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:01:21\",\"updated_at\":\"2026-03-19 03:55:39\",\"program_levels\":null}', '{\"name\":\"Liza Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:57:55'),
(266, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 9, 'Updated faculty: Liza Dela Cruz', '{\"id\":\"9\",\"user_id\":\"141\",\"faculty_id\":\"FAC-2024-007\",\"first_name\":\"Liza\",\"last_name\":\"Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Systems and Architecture\",\"subjects\":\"[\\\"CC102\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:01:21\",\"updated_at\":\"2026-03-27 15:57:55\",\"program_levels\":null}', '{\"name\":\"Liza Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:01:00'),
(267, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 9, 'Updated faculty: Liza Dela Cruz', '{\"id\":\"9\",\"user_id\":\"141\",\"faculty_id\":\"FAC-2024-007\",\"first_name\":\"Liza\",\"last_name\":\"Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Systems and Architecture\",\"subjects\":\"[\\\"CC102\\\",\\\"GE101\\\",\\\"GE103\\\",\\\"IS103\\\",\\\"IT100\\\",\\\"NSTP2\\\",\\\"PE2\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:01:21\",\"updated_at\":\"2026-03-27 16:00:59\",\"program_levels\":null}', '{\"name\":\"Liza Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:07:49'),
(268, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 3, 'Updated faculty: Anna Garcia', '{\"id\":\"3\",\"user_id\":\"138\",\"faculty_id\":\"FAC-2024-003\",\"first_name\":\"Anna\",\"last_name\":\"Garcia\",\"email\":\"anna.garcia@school.edu\",\"department\":\"Mathematics\",\"specialty\":\"Discrete Mathematics\",\"subjects\":\"[\\\"GE105-IT\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-02-01 08:03:41\",\"updated_at\":\"2026-03-19 03:55:39\",\"program_levels\":null}', '{\"name\":\"Anna Garcia\",\"email\":\"anna.garcia@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:08:16'),
(269, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 9, 'Updated faculty: Liza Dela Cruz', '{\"id\":\"9\",\"user_id\":\"141\",\"faculty_id\":\"FAC-2024-007\",\"first_name\":\"Liza\",\"last_name\":\"Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Systems and Architecture\",\"subjects\":\"[\\\"CC102\\\",\\\"GE101\\\",\\\"GE103\\\",\\\"IS103\\\",\\\"IT100\\\",\\\"NSTP2\\\",\\\"PE2\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:01:21\",\"updated_at\":\"2026-03-27 16:00:59\",\"program_levels\":null}', '{\"name\":\"Liza Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:13:20'),
(270, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 9, 'Updated faculty: Liza Dela Cruz', '{\"id\":\"9\",\"user_id\":\"141\",\"faculty_id\":\"FAC-2024-007\",\"first_name\":\"Liza\",\"last_name\":\"Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Systems and Architecture\",\"subjects\":\"[\\\"CC102\\\",\\\"GE101\\\",\\\"GE103\\\",\\\"IS103\\\",\\\"IT100\\\",\\\"NSTP2\\\",\\\"PE2\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:01:21\",\"updated_at\":\"2026-03-27 16:00:59\",\"program_levels\":null}', '{\"name\":\"Liza Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:17:39'),
(271, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 3, 'Updated faculty: Anna Garcia', '{\"id\":\"3\",\"user_id\":\"138\",\"faculty_id\":\"FAC-2024-003\",\"first_name\":\"Anna\",\"last_name\":\"Garcia\",\"email\":\"anna.garcia@school.edu\",\"department\":\"Mathematics\",\"specialty\":\"Discrete Mathematics\",\"subjects\":\"[\\\"GE105-IT\\\",\\\"CC102\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-02-01 08:03:41\",\"updated_at\":\"2026-03-27 16:08:16\",\"program_levels\":null}', '{\"name\":\"Anna Garcia\",\"email\":\"anna.garcia@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:18:01'),
(272, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 16, 'Updated course: CC103 - Data Structures and Algorithms', '{\"id\":\"16\",\"code\":\"CC103\",\"name\":\"Data Structures and Algorithms\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"\",\"department\":\"Information Communication and Technology (ICTD)\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"2nd Year\",\"created_at\":\"2026-03-05 01:43:03\",\"updated_at\":\"2026-03-12 00:27:10\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"is_general\":\"0\",\"is_lab\":\"1\"}', '{\"code\":\"CC103\",\"name\":\"Data Structures and Algorithms\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:22:28'),
(273, 185, 'nodadoshanecarlo@gmail.com', 'student', 'RE_ENROLL', 'student', 172, 'Student 172 re-enrolled: 1st Year 2nd Semester → 1st Year 1st sem ay 2025-2026 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:53:36'),
(274, 185, 'nodadoshanecarlo@gmail.com', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 172, 'Payment plan updated to \'installment\' (GCash) for student ID 172', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:53:52'),
(275, 185, 'nodadoshanecarlo@gmail.com', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 172, 'Payment plan updated to \'full\' (GCash) for student ID 172', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:00:12'),
(276, 185, 'nodadoshanecarlo@gmail.com', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 172, 'Payment plan updated to \'full\' (GCash) for student ID 172', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:00:37'),
(277, 185, 'nodadoshanecarlo@gmail.com', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 172, 'Payment plan updated to \'full\' (GCash) for student ID 172', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:01:05'),
(278, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 173, 'Downpayment verified ₱8,000.00 for student ID 173 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:33:32');
INSERT INTO `audit_logs` (`id`, `user_id`, `user_email`, `user_role`, `action`, `target_type`, `target_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(279, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 173, 'Registration confirmed for Shane Carlo Nodado (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:34:05'),
(280, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 174, 'Downpayment verified ₱9,000.00 for student ID 174 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:09:40'),
(281, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 174, 'Registration confirmed for kuplas Nodado (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:10:10'),
(282, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 175, 'Full payment verified for student ID 175 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:14:07'),
(283, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 175, 'Registration confirmed for Shane Carlo Nodado (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:14:29'),
(284, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 176, 'Downpayment verified ₱7,500.00 for student ID 176 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:21:19'),
(285, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 176, 'Registration confirmed for rochhele Nodado (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:21:47'),
(286, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 556, 'Deleted course: NSTP1-BMD - National Service Training Program 1', '{\"id\":\"556\",\"code\":\"NSTP1-BMD\",\"name\":\"National Service Training Program 1\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"\",\"department\":\"Business Management Department (BMD)\",\"program\":\"Bachelor of Science in Accountancy\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 09:03:52\",\"updated_at\":\"2026-03-12 00:27:10\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:59:18'),
(287, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 563, 'Deleted course: NSTP2-BMD - National Service Training Program 2', '{\"id\":\"563\",\"code\":\"NSTP2-BMD\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"2nd Semester\",\"description\":\"\",\"department\":\"Business Management Department (BMD)\",\"program\":\"Bachelor of Science in Accountancy\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 09:03:52\",\"updated_at\":\"2026-03-12 00:27:10\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:59:23'),
(288, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 8, 'Updated course: NSTP3 - National Service Training Program', '{\"id\":\"8\",\"code\":\"NSTP1\",\"name\":\"National Service Training Program 1\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"\",\"department\":\"Information Communication and Technology (ICTD)\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 01:43:03\",\"updated_at\":\"2026-03-12 00:27:10\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', '{\"code\":\"NSTP3\",\"name\":\"National Service Training Program\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 16:04:16'),
(289, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 8, 'Updated course: NSTP1 - National Service Training Program1', '{\"id\":\"8\",\"code\":\"NSTP3\",\"name\":\"National Service Training Program\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"\",\"department\":\"Information Communication and Technology (ICTD)\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 01:43:03\",\"updated_at\":\"2026-03-28 00:04:16\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', '{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 16:04:34'),
(290, 189, 'shane2', 'student', 'RE_ENROLL', 'student', 176, 'Student 176 re-enrolled: 1st Year 2nd Semester → 2nd Year 1ST Semester, AY 2026-2027 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 17:46:52'),
(291, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 177, 'Full payment verified for student ID 177 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:06:42'),
(292, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 177, 'Registration confirmed for Dump Account2 (STU-2026-0003). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:07:41'),
(293, 188, 'shane1', 'student', 'RE_ENROLL', 'student', 175, 'Student 175 re-enrolled: 1st Year 2nd Semester → 1st Year 2nd Semester, AY 2027-2026 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:10:58'),
(294, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 175, 'Downpayment verified ₱7,500.00 for student ID 175 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:12:16'),
(295, 190, 'shane3', 'student', 'RE_ENROLL', 'student', 177, 'Student 177 re-enrolled: 1st Year 2nd Semester → 1st Year 2nd Semester, AY 2028-2029 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:16:11'),
(296, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 177, 'Full payment verified for student ID 177 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:22:03'),
(297, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 178, 'Full payment verified for student ID 178 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:24:47'),
(298, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 178, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:25:04'),
(299, 191, 'shane1', 'student', 'RE_ENROLL', 'student', 178, 'Student 178 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester, AY 2025-2026 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:26:55'),
(300, 191, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 178, 'Payment plan updated to \'full\' (GCash) for student ID 178', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:27:03'),
(301, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 178, 'Full payment verified for student ID 178 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 18:27:25'),
(302, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 178, 'Admin viewed student record: Dump Account2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 05:47:06'),
(303, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 179, 'Full payment verified for student ID 179 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:05:43'),
(304, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 179, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:24:49'),
(305, 192, 'shane1', 'student', 'RE_ENROLL', 'student', 179, 'Student 179 re-enrolled: 1st Year 2nd Semester → 2nd Year 1st Semester, AY 2026-2027 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:31:02'),
(306, 192, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 179, 'Payment plan updated to \'full\' (GCash) for student ID 179', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:31:14'),
(307, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 179, 'Full payment verified for student ID 179 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:31:41'),
(308, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 180, 'Full payment verified for student ID 180 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:48:00'),
(309, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 180, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:48:20'),
(310, 193, 'shane1', 'student', 'RE_ENROLL', 'student', 180, 'Student 180 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester, AY 2026-2027 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:51:02'),
(311, 193, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 180, 'Payment plan updated to \'full\' (GCash) for student ID 180', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:51:07'),
(312, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 180, 'Full payment verified for student ID 180 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 06:51:26'),
(313, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 181, 'Full payment verified for student ID 181 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:07:34'),
(314, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 181, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:07:51'),
(315, 194, 'shane1', 'student', 'RE_ENROLL', 'student', 181, 'Student 181 re-enrolled: 1st Year 2nd Semester → 2nd Year 1st Semester, AY 2027-2028 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:08:49'),
(316, 194, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 181, 'Payment plan updated to \'full\' (GCash) for student ID 181', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:08:55'),
(317, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 181, 'Full payment verified for student ID 181 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:09:32'),
(318, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 181, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:09:53'),
(319, 194, 'shane1', 'student', 'RE_ENROLL', 'student', 181, 'Student 181 re-enrolled: 2nd Year 1st Semester → 2nd Year 2nd Semester, AY 2027-2028 (type: Continuing → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:14:04'),
(320, 194, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 181, 'Payment plan updated to \'full\' (GCash) for student ID 181', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:14:09'),
(321, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 181, 'Full payment verified for student ID 181 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:20:34'),
(322, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 181, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:21:17'),
(323, 194, 'shane1', 'student', 'RE_ENROLL', 'student', 181, 'Student 181 re-enrolled: 2nd Year 2nd Semester → 3rd Year 1st Semester, AY 2028-2029 (type: Continuing → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:22:51'),
(324, 194, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 181, 'Payment plan updated to \'full\' (GCash) for student ID 181', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:23:06'),
(325, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 181, 'Full payment verified for student ID 181 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:39:13'),
(326, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 181, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:39:32'),
(327, 194, 'shane1', 'student', 'RE_ENROLL', 'student', 181, 'Student 181 re-enrolled: 3rd Year 1st Semester → 3rd Year 2nd Semester, AY 2028-2029 (type: Continuing → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:40:43'),
(328, 194, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 181, 'Payment plan updated to \'full\' (Cash) for student ID 181', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:40:47'),
(329, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 181, 'Full payment verified for student ID 181 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:47:33'),
(330, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 181, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 07:54:03'),
(331, 194, 'shane1', 'student', 'RE_ENROLL', 'student', 181, 'Student 181 re-enrolled: 3rd Year 2nd Semester → 4th Year 1st Semester, AY 2029-2030 (type: Continuing → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 15:16:37'),
(332, 194, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 181, 'Payment plan updated to \'full\' (GCash) for student ID 181', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 15:17:03'),
(333, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 181, 'Full payment verified for student ID 181 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 15:17:20'),
(334, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 181, 'Registration confirmed for Dump Account2 (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 15:18:11'),
(335, 3, 'accounting@example.com', 'accounting', 'BULK_SEND_NOTICE', 'notice', 0, 'Bulk Prelim notice: 0 sent, 0 skipped. Category: ALL', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 16:04:20'),
(336, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 181, 'Sent Prelim notice to student ID 181 (₱6,420.75)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 16:04:35'),
(337, 3, 'accounting@example.com', 'accounting', 'APPROVED_PERMIT', 'exam_permit', 13, 'Exam permit approvedd. ID: 13. Permit#: EP-20260328-STU20260001-PRE. Remarks: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 16:04:57'),
(338, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 11, 'Updated course: CC102 - Computer Programming 2', '{\"id\":\"11\",\"code\":\"CC102\",\"name\":\"Computer Programming 2\",\"credits\":\"3\",\"faculty_id\":\"141\",\"capacity\":\"40\",\"semester\":\"2nd Semester\",\"description\":\"\",\"department\":\"Information Communication and Technology (ICTD)\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 01:43:03\",\"updated_at\":\"2026-03-27 16:17:39\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"is_general\":\"0\",\"is_lab\":\"1\"}', '{\"code\":\"CC102\",\"name\":\"Computer Programming 2\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:32:12'),
(339, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 182, 'Downpayment verified ₱8,034.25 for student ID 182 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:34:20'),
(340, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 182, 'Registration confirmed for Shane Gongora (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:35:00'),
(341, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"AEC111\\\",\\\"AEC109\\\",\\\"CC101\\\",\\\"GE100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-19 06:59:30\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:37:31'),
(342, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"CC101\\\",\\\"CC100\\\",\\\"GE105\\\",\\\"GE108\\\",\\\"GE109\\\",\\\"IT-CMT\\\",\\\"NSTP1\\\",\\\"PE1\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-29 02:37:30\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:38:06'),
(343, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 1, 'Updated faculty: Maria Santos', '{\"id\":\"1\",\"user_id\":\"136\",\"faculty_id\":\"FAC-2024-001\",\"first_name\":\"Maria\",\"last_name\":\"Santos\",\"email\":\"maria.santos@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Web Development, It Specialist\",\"subjects\":\"[\\\"CC100-IT\\\",\\\"CC101-IT\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-02-01 08:03:41\",\"updated_at\":\"2026-03-19 03:55:39\",\"program_levels\":null}', '{\"name\":\"Maria Santos\",\"email\":\"maria.santos@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:39:05'),
(344, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"CC101\\\",\\\"GE105\\\",\\\"GE108\\\",\\\"GE109\\\",\\\"IT-CMT\\\",\\\"NSTP1\\\",\\\"PE1\\\",\\\"CC100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-29 02:38:05\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:39:22'),
(345, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 3, 'Updated faculty: Anna Garcia', '{\"id\":\"3\",\"user_id\":\"138\",\"faculty_id\":\"FAC-2024-003\",\"first_name\":\"Anna\",\"last_name\":\"Garcia\",\"email\":\"anna.garcia@school.edu\",\"department\":\"Mathematics\",\"specialty\":\"Discrete Mathematics\",\"subjects\":\"[\\\"GE105-IT\\\",\\\"CC102\\\",\\\"PE2\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-02-01 08:03:41\",\"updated_at\":\"2026-03-27 16:18:01\",\"program_levels\":null}', '{\"name\":\"Anna Garcia\",\"email\":\"anna.garcia@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:39:57'),
(346, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 9, 'Updated faculty: Liza Dela Cruz', '{\"id\":\"9\",\"user_id\":\"141\",\"faculty_id\":\"FAC-2024-007\",\"first_name\":\"Liza\",\"last_name\":\"Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Systems and Architecture\",\"subjects\":\"[\\\"CC102\\\",\\\"GE101\\\",\\\"GE103\\\",\\\"IS103\\\",\\\"IT100\\\",\\\"NSTP2\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:01:21\",\"updated_at\":\"2026-03-27 16:17:39\",\"program_levels\":null}', '{\"name\":\"Liza Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:40:49'),
(347, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 9, 'Updated faculty: Liza Dela Cruz', '{\"id\":\"9\",\"user_id\":\"141\",\"faculty_id\":\"FAC-2024-007\",\"first_name\":\"Liza\",\"last_name\":\"Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Systems and Architecture\",\"subjects\":\"[\\\"CC102\\\",\\\"GE101\\\",\\\"GE103\\\",\\\"IS103\\\",\\\"IT100\\\",\\\"NSTP2\\\",\\\"CC100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:01:21\",\"updated_at\":\"2026-03-29 02:40:49\",\"program_levels\":null}', '{\"name\":\"Liza Dela Cruz\",\"email\":\"liza.delacruz@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:41:09'),
(348, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 183, 'Downpayment verified ₱8,035.00 for student ID 183 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:42:37'),
(349, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 183, 'Registration confirmed for Shane Gongora (STU-2026-0003). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:42:56'),
(350, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"CC101\\\",\\\"GE105\\\",\\\"GE108\\\",\\\"GE109\\\",\\\"IT-CMT\\\",\\\"NSTP1\\\",\\\"PE1\\\",\\\"CC100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-29 02:38:05\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:47:40'),
(351, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 3, 'Updated faculty: Anna Garcia', '{\"id\":\"3\",\"user_id\":\"138\",\"faculty_id\":\"FAC-2024-003\",\"first_name\":\"Anna\",\"last_name\":\"Garcia\",\"email\":\"anna.garcia@school.edu\",\"department\":\"Mathematics\",\"specialty\":\"Discrete Mathematics\",\"subjects\":\"[\\\"GE105-IT\\\",\\\"CC102\\\",\\\"PE2\\\",\\\"CC100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-02-01 08:03:41\",\"updated_at\":\"2026-03-29 02:39:57\",\"program_levels\":null}', '{\"name\":\"Anna Garcia\",\"email\":\"anna.garcia@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:53:10'),
(352, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 11, 'Updated faculty: Carlo Mendoza', '{\"id\":\"11\",\"user_id\":\"143\",\"faculty_id\":\"FAC-2024-006\",\"first_name\":\"Carlo\",\"last_name\":\"Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Accounting and Finance\",\"subjects\":\"[\\\"CC101\\\",\\\"GE105\\\",\\\"GE108\\\",\\\"GE109\\\",\\\"IT-CMT\\\",\\\"NSTP1\\\",\\\"PE1\\\",\\\"CC100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-03-04 20:06:34\",\"updated_at\":\"2026-03-29 02:38:05\",\"program_levels\":null}', '{\"name\":\"Carlo Mendoza\",\"email\":\"carlo.mendoza@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:53:16'),
(353, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 555, 'Deleted course: PE1-BMD - Physical Education 1 (Aquatics)', '{\"id\":\"555\",\"code\":\"PE1-BMD\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"faculty_id\":\"143\",\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"\",\"department\":\"Business Management Department (BMD)\",\"program\":\"Bachelor of Science in Accountancy\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 09:03:52\",\"updated_at\":\"2026-03-29 02:53:16\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:59:00'),
(354, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 7, 'Updated course: PE1 - Physical Education 1 (Aquatics)', '{\"id\":\"7\",\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"faculty_id\":\"143\",\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"\",\"department\":\"Information Communication and Technology (ICTD)\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 01:43:03\",\"updated_at\":\"2026-03-29 02:53:16\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', '{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 18:59:18'),
(355, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 11, 'Updated course: CC102 - Computer Programming 2', '{\"id\":\"11\",\"code\":\"CC102\",\"name\":\"Computer Programming 2\",\"credits\":\"3\",\"faculty_id\":\"141\",\"capacity\":\"40\",\"semester\":\"2nd Semester\",\"description\":\"\",\"department\":\"Information Communication and Technology (ICTD)\",\"program\":\"Bachelor of Science in Information Technology\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 01:43:03\",\"updated_at\":\"2026-03-29 02:41:09\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"is_general\":\"0\",\"is_lab\":\"1\"}', '{\"code\":\"CC102\",\"name\":\"Computer Programming 2\",\"program\":\"Bachelor of Science in Information Technology\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:14:44'),
(356, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 1, 'Updated faculty: Maria Santos', '{\"id\":\"1\",\"user_id\":\"136\",\"faculty_id\":\"FAC-2024-001\",\"first_name\":\"Maria\",\"last_name\":\"Santos\",\"email\":\"maria.santos@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Web Development, It Specialist\",\"subjects\":\"[]\",\"status\":\"Active\",\"created_at\":\"2026-02-01 08:03:41\",\"updated_at\":\"2026-03-29 02:39:05\",\"program_levels\":null}', '{\"name\":\"Maria Santos\",\"email\":\"maria.santos@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:18:54'),
(357, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 184, 'Downpayment verified ₱9,000.00 for student ID 184 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:21:28'),
(358, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 184, 'Registration confirmed for Shane Gongora (STU-2026-0004). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:22:00'),
(359, 2, 'admin@example.com', 'admin', 'UPDATE_FACULTY', 'faculty', 1, 'Updated faculty: Maria Santos', '{\"id\":\"1\",\"user_id\":\"136\",\"faculty_id\":\"FAC-2024-001\",\"first_name\":\"Maria\",\"last_name\":\"Santos\",\"email\":\"maria.santos@school.edu\",\"department\":\"Information Technology\",\"specialty\":\"Web Development, It Specialist\",\"subjects\":\"[\\\"CC100\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-02-01 08:03:41\",\"updated_at\":\"2026-03-29 03:18:54\",\"program_levels\":null}', '{\"name\":\"Maria Santos\",\"email\":\"maria.santos@school.edu\",\"status\":\"Active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:23:25'),
(360, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 184, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:28:51'),
(361, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 182, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:29:00'),
(362, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 183, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:29:06'),
(363, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 185, 'Full payment verified for student ID 185 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:32:39'),
(364, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 185, 'Registration confirmed for Shane Gongora (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:33:08'),
(365, 198, 'shane4', 'student', 'RE_ENROLL', 'student', 185, 'Student 185 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester, AY 2029-2030 (type: Old → Continuing). Marked IRREGULAR — 1 failed subject(s): Computer Programming 1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:37:50'),
(366, 198, 'shane4', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 185, 'Payment plan updated to \'installment\' (Cash) for student ID 185', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:37:54'),
(367, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 185, 'Downpayment verified ₱7,499.50 for student ID 185 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:38:06'),
(368, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 185, 'Registration confirmed for Shane Gongora (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:38:24'),
(369, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 185, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:40:31'),
(370, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 185, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 19:58:34'),
(371, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 185, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:10:13'),
(372, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 185, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:19:20'),
(373, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 185, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:19:45'),
(374, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 187, 'Full payment verified for student ID 187 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:35:53'),
(375, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 187, 'Registration confirmed for Shane Gongora (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:36:02'),
(376, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 187, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:44:26'),
(377, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 187, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:53:37'),
(378, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 188, 'Full payment verified for student ID 188 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:58:36'),
(379, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 188, 'Registration confirmed for Shane Gongora (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 20:58:53'),
(380, 201, 'shane1', 'student', 'RE_ENROLL', 'student', 188, 'Student 188 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester, AY 2025-2026 (type: Old → Continuing). Marked IRREGULAR — 1 failed subject(s): Computer Programming 1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 21:12:32'),
(381, 201, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 188, 'Payment plan updated to \'installment\' (Cash) for student ID 188', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 21:12:37'),
(382, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 188, 'Downpayment verified ₱7,499.50 for student ID 188 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 21:12:52'),
(383, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 188, 'Registration confirmed for Shane Gongora (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 21:13:38'),
(384, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 187, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 08:15:41'),
(385, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 188, 'Admin viewed student record: Shane Gongora', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 08:15:57'),
(386, 201, 'shane1', 'student', 'RE_ENROLL', 'student', 188, 'Student 188 re-enrolled: 1st Year 2nd Semester → 1st Year 2nd Semester, AY 2026-2027 (type: Continuing → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 08:59:27'),
(387, 201, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 188, 'Payment plan updated to \'full\' (Cash) for student ID 188', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:00:46'),
(388, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 188, 'Full payment verified for student ID 188 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:01:41'),
(389, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 189, 'Downpayment verified ₱7,499.50 for student ID 189 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:05:59'),
(390, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 189, 'Registration confirmed for Dave Cuevas (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:09:24'),
(391, 202, 'shane1', 'student', 'RE_ENROLL', 'student', 189, 'Student 189 re-enrolled: 1st Year 2nd Semester → 2nd Year 1st Semester, AY 2027-2028 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:33:53'),
(392, 202, 'shane1', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 189, 'Payment plan updated to \'full\' (Cash) for student ID 189', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:36:46'),
(393, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 189, 'Full payment verified for student ID 189 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:37:00'),
(394, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 189, 'Registration confirmed for Dave Cuevas (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 09:48:18'),
(395, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 190, 'Full payment verified for student ID 190 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:12:39'),
(396, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 190, 'Registration confirmed for Dave Cuevas (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:13:02'),
(397, 203, 'shane2', 'student', 'RE_ENROLL', 'student', 190, 'Student 190 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester, AY 2027-2028 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:32:40'),
(398, 203, 'shane2', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 190, 'Payment plan updated to \'installment\' (Cash) for student ID 190', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:32:47'),
(399, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 190, 'Downpayment verified ₱7,499.50 for student ID 190 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:32:57'),
(400, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 190, 'Registration confirmed for Dave Cuevas (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:33:50'),
(401, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 190, 'Registration confirmed for Dave Cuevas (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 15:39:18'),
(402, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 191, 'Downpayment verified ₱7,500.00 for student ID 191 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:14:49'),
(403, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 191, 'Registration confirmed for Dave Cuevas (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:15:11'),
(404, 204, 'shane3', 'student', 'RE_ENROLL', 'student', 191, 'Student 191 re-enrolled: 1st Year 2nd Semester → 2nd Year 1st Semester, AY 2026-2027 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:18:55'),
(405, 204, 'shane3', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 191, 'Payment plan updated to \'full\' (Cash) for student ID 191', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:19:26');
INSERT INTO `audit_logs` (`id`, `user_id`, `user_email`, `user_role`, `action`, `target_type`, `target_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(406, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 191, 'Full payment verified for student ID 191 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:19:39'),
(407, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 191, 'Registration confirmed for Dave Cuevas (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 17:19:50'),
(408, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 192, 'Full payment verified for student ID 192 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:17:05'),
(409, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 192, 'Registration confirmed for Shane Gongora (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:18:00'),
(410, 205, 'shane4', 'student', 'RE_ENROLL', 'student', 192, 'Student 192 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester, AY 2026-2027 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:25:08'),
(411, 205, 'shane4', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 192, 'Payment plan updated to \'installment\' (Cash) for student ID 192', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:25:15'),
(412, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 192, 'Downpayment verified ₱7,499.50 for student ID 192 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:26:10'),
(413, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 192, 'Registration confirmed for Shane Gongora (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:50:56'),
(414, 205, 'shane4', 'student', 'RE_ENROLL', 'student', 192, 'Student 192 re-enrolled: 1st Year 2nd Semester → 2nd Year 1st Semester, AY 2027-2028 (type: Continuing → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:51:12'),
(415, 205, 'shane4', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 192, 'Payment plan updated to \'installment\' (GCash) for student ID 192', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:51:18'),
(416, 3, 'accounting@example.com', 'accounting', 'BULK_SEND_NOTICE', 'notice', 0, 'Bulk Prelim notice: 0 sent, 0 skipped. Category: ALL', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 10:51:57'),
(417, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 192, 'Verified Prelim payment ₱7,500.00 for student ID 192 (OR: AR-20260098)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 11:37:43'),
(418, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 192, 'Downpayment verified ₱7,500.00 for student ID 192 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 11:38:05'),
(419, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 192, 'Registration confirmed for Shane Gongora (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 12:10:12'),
(420, 2, 'admin@example.com', 'admin', 'CREATE_COURSE', 'course', 2043, 'Created course: ALG12 - algebra', NULL, '{\"code\":\"ALG12\",\"name\":\"algebra\",\"program\":\"General Academic Strand (GAS)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 18:48:41'),
(421, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 2043, 'Updated course: ALG12 - algebra', '{\"id\":\"2043\",\"code\":\"ALG12\",\"name\":\"algebra\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"\",\"description\":\"\",\"department\":\"Academic Track\",\"program\":\"General Academic Strand (GAS)\",\"year_level\":\"Grade 11\",\"created_at\":\"2026-03-31 02:48:41\",\"updated_at\":\"2026-03-31 02:48:41\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', '{\"code\":\"ALG12\",\"name\":\"algebra\",\"program\":\"General Academic Strand (GAS)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 18:49:43'),
(422, 2, 'admin@example.com', 'admin', 'UPDATE_COURSE', 'course', 2043, 'Updated course: GEALG12 - algebra', '{\"id\":\"2043\",\"code\":\"ALG12\",\"name\":\"algebra\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"\",\"department\":\"Academic Track\",\"program\":\"General Academic Strand (GAS)\",\"year_level\":\"Grade 11\",\"created_at\":\"2026-03-31 02:48:41\",\"updated_at\":\"2026-03-31 02:49:42\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', '{\"code\":\"GEALG12\",\"name\":\"algebra\",\"program\":\"General Academic Strand (GAS)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 18:53:22'),
(423, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 194, 'Full payment verified for student ID 194 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 07:47:26'),
(424, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 194, 'Registration confirmed for Lharriane Binoya (STU-2026-0004). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 08:06:50'),
(425, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 194, 'Sent Prelim notice to student ID 194 (₱7,846.75)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 08:08:31'),
(426, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 194, 'Sent Midterm notice to student ID 194 (₱7,846.75)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 08:32:16'),
(427, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 192, 'Sent Midterm notice to student ID 192 (₱7,499.00)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 08:40:51'),
(428, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 192, 'Verified Midterm payment ₱8,000.00 for student ID 192 (OR: AR-20260101)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 08:41:56'),
(429, 205, 'shane4', 'student', 'REQUEST_PERMIT', 'student', 192, 'Requested Prelim permit for student ID 192 (installment, ₱7,500.00 paid)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 17:24:39'),
(430, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 195, 'Downpayment verified ₱8,034.25 for student ID 195 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:45:13'),
(431, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 195, 'Registration confirmed for Ana Binoya (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:46:00'),
(432, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 195, 'Sent Prelim notice to student ID 195 (₱8,034.25)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:47:33'),
(433, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 195, 'Verified Prelim payment ₱10,000.00 for student ID 195 (OR: AR-20260103)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:48:49'),
(434, 208, 'shane', 'student', 'REQUEST_PERMIT', 'student', 195, 'Requested Prelim permit for student ID 195 (installment, ₱10,000.00 paid)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:49:14'),
(435, 3, 'accounting@example.com', 'accounting', 'APPROVED_PERMIT', 'exam_permit', 18, 'Exam permit approvedd. ID: 18. Permit#: EP-20260401-STU20260001-PRE. Remarks: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:50:15'),
(436, 3, 'accounting@example.com', 'accounting', 'SEND_NOTICE', 'student', 195, 'Sent Midterm notice to student ID 195 (₱7,051.38)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:50:57'),
(437, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 195, 'Verified Midterm payment ₱8,000.00 for student ID 195 (OR: AR-20260104)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:51:47'),
(438, 208, 'shane', 'student', 'REQUEST_PERMIT', 'student', 195, 'Requested Midterm permit for student ID 195 (installment, ₱8,000.00 paid)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:53:05'),
(439, 3, 'accounting@example.com', 'accounting', 'APPROVED_PERMIT', 'exam_permit', 19, 'Exam permit approvedd. ID: 19. Permit#: EP-20260401-STU20260001-MID. Remarks: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 07:53:10'),
(440, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 195, 'Admin viewed student record: Ana Binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:43:33'),
(441, 2, 'admin@example.com', 'admin', 'CREATE_STAFF_ACCOUNT', 'staff_profiles', 4, 'Created accounting account: Shane Carlo Nodado (maria@edu.com)', NULL, '{\"email\":\"maria@edu.com\",\"role\":\"accounting\",\"name\":\"Shane Carlo Nodado\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 15:03:09'),
(442, 2, 'admin@example.com', 'admin', 'DELETE_STAFF_ACCOUNT', 'users', 209, 'Deleted accounting account: maria@edu.com', '{\"id\":\"4\",\"user_id\":\"209\",\"first_name\":\"Shane Carlo\",\"last_name\":\"Nodado\",\"middle_name\":null,\"phone\":\"09300987316\",\"department\":\"Accounting\",\"position\":\"\",\"created_at\":\"2026-04-01 23:03:09\",\"updated_at\":\"2026-04-01 23:03:09\",\"email\":\"maria@edu.com\",\"role\":\"accounting\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 15:05:33'),
(443, 2, 'admin@example.com', 'admin', 'CREATE_FACULTY', 'faculty', 12, 'Created faculty: Ana Binoya', NULL, '{\"faculty_id\":\"FAC-2026-001\",\"name\":\"Ana Binoya\",\"email\":\"anamariebinoya0909@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:51:16'),
(444, 2, 'admin@example.com', 'admin', 'CREATE_FACULTY', 'faculty', 13, 'Created faculty: Ana Binoya', NULL, '{\"faculty_id\":\"FAC-2026-002\",\"name\":\"Ana Binoya\",\"email\":\"anamariebinoya@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:52:58'),
(445, 2, 'admin@example.com', 'admin', 'DELETE_FACULTY', 'faculty', 12, 'Deleted faculty: Ana Binoya', '{\"id\":\"12\",\"user_id\":\"210\",\"faculty_id\":\"FAC-2026-001\",\"first_name\":\"Ana\",\"last_name\":\"Binoya\",\"email\":\"anamariebinoya0909@gmail.com\",\"department\":\"Information Technology\",\"specialty\":\"Web development\",\"subjects\":\"[\\\"BME103\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-04-03 12:51:16\",\"updated_at\":\"2026-04-03 12:51:16\",\"program_levels\":null}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:53:35'),
(446, 2, 'admin@example.com', 'admin', 'DELETE_FACULTY', 'faculty', 13, 'Deleted faculty: Ana Binoya', '{\"id\":\"13\",\"user_id\":\"211\",\"faculty_id\":\"FAC-2026-002\",\"first_name\":\"Ana\",\"last_name\":\"Binoya\",\"email\":\"anamariebinoya@gmail.com\",\"department\":\"Mathematics\",\"specialty\":\"sdsds\",\"subjects\":\"[\\\"AEC102\\\"]\",\"status\":\"Active\",\"created_at\":\"2026-04-03 12:52:58\",\"updated_at\":\"2026-04-03 12:52:58\",\"program_levels\":null}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:53:38'),
(447, 2, 'admin@example.com', 'admin', 'VIEW_STUDENT', 'student', 195, 'Admin viewed student record: Ana Binoya', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:01:59'),
(448, 208, 'shane', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 3, 'Drop request submitted by student ID 195 for course ID 3. Reason: 1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:10:28'),
(449, 208, 'shane', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 45, 'Add request submitted by student ID 195 for course ID 45. Reason: 123', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:11:42'),
(450, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 196, 'Downpayment verified ₱8,034.25 for student ID 196 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:20:56'),
(451, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 196, 'Registration confirmed for Ana Binoya (STU-2026-0002). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:21:13'),
(452, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 197, 'Full payment verified for student ID 197 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:25:21'),
(453, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 197, 'Registration confirmed for Ana Binoya1 (STU-2026-0003). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:26:42'),
(454, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 197, 'Registration confirmed for Ana Binoya1 (STU-2026-0003). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:36:27'),
(455, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 198, 'Downpayment verified ₱8,034.25 for student ID 198 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:38:20'),
(456, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 198, 'Registration confirmed for Ana Binoya (STU-2026-0004). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:39:28'),
(457, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 199, 'Full payment verified for student ID 199 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:43:45'),
(458, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 199, 'Registration confirmed for Ana2 Binoya2 (STU-2026-0005). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:44:09'),
(459, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 199, 'Registration confirmed for Ana2 Binoya2 (STU-2026-0005). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:45:09'),
(460, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 200, 'Full payment verified for student ID 200 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:48:51'),
(461, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 200, 'Registration confirmed for Ana Binoya (STU-2026-0006). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:49:05'),
(462, 216, 'shane5', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 45, 'Add request submitted by student ID 200 for course ID 45. Reason: 123', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 07:50:26'),
(463, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 19, 'Add/Drop request #19 Approved by Accounting. Fee impact: ₱4,278.00. New total: ₱35,665.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:03:40'),
(464, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 18, 'Add/Drop request #18 Approved by Accounting. Fee impact: ₱4,278.00. New total: ₱36,415.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:11:49'),
(465, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 17, 'Add/Drop request #17 Approved by Accounting. Fee impact: ₱-4,039.00. New total: ₱28,098.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:20:15'),
(466, 216, 'shane5', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 4, 'Drop request submitted by student ID 200 for course ID 4. Reason: 1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:26:57'),
(467, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 20, 'Add/Drop request #20 Approved by Accounting. Fee impact: ₱-4,039.00. New total: ₱27,348.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:27:27'),
(468, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 20, 'Add/Drop request #20 (Drop course 4 for student 200) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:47:30'),
(469, 216, 'shane5', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 3, 'Drop request submitted by student ID 200 for course ID 3. Reason: 1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:58:18'),
(470, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 21, 'Add/Drop request #21 Approved by Accounting. Fee impact: ₱-2,139.00. New total: ₱27,109.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:58:47'),
(471, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 21, 'Add/Drop request #21 (Drop course 3 for student 200) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 08:58:55'),
(472, 216, 'shane5', 'student', 'RE_ENROLL', 'student', 200, 'Student 200 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester AY 2026-2027 (type: New → Old)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:10:37'),
(473, 216, 'shane5', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 200, 'Payment plan updated to \'installment\' (Cash) for student ID 200', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:10:44'),
(474, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 200, 'Downpayment verified ₱7,499.50 for student ID 200 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:11:51'),
(475, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 200, 'Registration confirmed for Ana Binoya (STU-2026-0006). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:12:33'),
(476, 216, 'shane5', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 11, 'Add request submitted by student ID 200 for course ID 11. Reason: 1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:15:11'),
(477, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 22, 'Add/Drop request #22 Approved by Accounting. Fee impact: ₱4,039.00. New total: ₱31,898.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:15:18'),
(478, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 22, 'Add/Drop request #22 (Add course 11 for student 200) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 09:15:28'),
(479, 208, 'shane', 'student', 'RE_ENROLL', 'student', 195, 'Student 195 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester AY 2026-2027 (type: New → Old)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:10:16'),
(480, 208, 'shane', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 195, 'Payment plan updated to \'full\' (GCash) for student ID 195', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:11:43'),
(481, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 195, 'Verified Finals payment ₱29,998.00 for student ID 195 (OR: OR-20260111)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:12:10'),
(482, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 195, 'Registration confirmed for Ana Binoya (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:12:42'),
(483, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 19, 'Add/Drop request #19 (Add course 45 for student 200) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:14:36'),
(484, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 18, 'Add/Drop request #18 (Add course 45 for student 195) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:14:38'),
(485, 208, 'shane', 'student', 'RE_ENROLL', 'student', 195, 'Student 195 re-enrolled: 1st Year 2nd Semester → 2nd Year 1st Semester AY 2027-2028 (type: Old → Continuing)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:20:23'),
(486, 208, 'shane', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 195, 'Payment plan updated to \'installment\' (GCash) for student ID 195', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:20:35'),
(487, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 195, 'Downpayment verified ₱7,500.00 for student ID 195 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:20:51'),
(488, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 195, 'Registration confirmed for Ana Binoya (STU-2026-0001). Notes: ', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 10:22:18'),
(489, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 201, 'Downpayment verified ₱8,035.00 for student ID 201 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 13:28:52'),
(490, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 202, 'Downpayment verified ₱8,035.00 for student ID 202 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:10:24'),
(491, 4, 'registrar@example.com', 'registrar', 'CREATE_BLOCK', 'class_blocks', 1, 'Auto-created block BSIT-1A for Bachelor of Science in Information Technology 1st Year (1st Semester, AY 2027-2028) on registration confirm', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:16:41'),
(492, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 202, 'Student 202 auto-assigned to block BSIT-1A (ID 1) [new block created] — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:16:41'),
(493, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 202, 'Registration confirmed for shane binoya (STU-2026-0008). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:16:41'),
(494, 218, 'shane33', 'student', 'RE_ENROLL', 'student', 202, 'Student 202 re-enrolled: 1st Year 1st Semester → 1st Year 1st Semester AY 2027-2028 (type: New → Old)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:16:58'),
(495, 218, 'shane33', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 202, 'Payment plan updated to \'full\' (Cash) for student ID 202', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:17:03'),
(496, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 202, 'Full payment verified for student ID 202 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:17:12'),
(497, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 202, 'Registration confirmed for shane binoya (STU-2026-0008). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:17:29'),
(498, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 203, 'Downpayment verified ₱8,034.25 for student ID 203 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:18:50'),
(499, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 203, 'Student 203 auto-assigned to block BSIT-1A (ID 1) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:19:07'),
(500, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 203, 'Registration confirmed for shane binoya (STU-2026-0009). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:19:08'),
(501, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 204, 'Downpayment verified ₱8,035.00 for student ID 204 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:22:44'),
(502, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 204, 'Student 204 auto-assigned to block BSIT-1A (ID 1) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:22:57'),
(503, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 204, 'Registration confirmed for shane binoya (STU-2026-0001). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 14:22:57');

-- --------------------------------------------------------

--
-- Table structure for table `block_course_sections`
--

CREATE TABLE `block_course_sections` (
  `id` int(11) NOT NULL,
  `block_id` int(11) NOT NULL COMMENT 'FK → class_blocks.id',
  `course_id` int(11) NOT NULL COMMENT 'FK → courses.id',
  `course_section_id` int(11) DEFAULT NULL COMMENT 'FK → course_sections.id (nullable — unset until assigned)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Links a class block to the course_section row for each subject.';

-- --------------------------------------------------------

--
-- Table structure for table `class_blocks`
--

CREATE TABLE `class_blocks` (
  `id` int(11) NOT NULL,
  `block_code` varchar(30) NOT NULL COMMENT 'e.g. BSIT-1A, BSBA-2B',
  `program` varchar(150) NOT NULL COMMENT 'Full program name stored in students.program',
  `year_level` varchar(30) NOT NULL COMMENT '1st Year, 2nd Year, Grade 11, Grade 12…',
  `semester` varchar(100) NOT NULL COMMENT '1st Semester, AY 2026-2027',
  `school_year` varchar(20) NOT NULL COMMENT '2026-2027',
  `max_capacity` int(11) NOT NULL DEFAULT 40,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Class blocks (e.g. BSIT-1A). Each block is a cohort of students.';

--
-- Dumping data for table `class_blocks`
--

INSERT INTO `class_blocks` (`id`, `block_code`, `program`, `year_level`, `semester`, `school_year`, `max_capacity`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'BSIT-1A', 'Bachelor of Science in Information Technology', '1st Year', '1st Semester, AY 2027-2028', '2027-2028', 40, 1, '2026-04-03 14:16:41', '2026-04-03 14:16:41');

-- --------------------------------------------------------

--
-- Table structure for table `coe_requests`
--

CREATE TABLE `coe_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `purpose` varchar(255) DEFAULT 'General Purpose',
  `copies` tinyint(4) DEFAULT 1,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `registrar_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `control_number` varchar(30) DEFAULT NULL,
  `semester` varchar(100) NOT NULL DEFAULT '',
  `school_year` varchar(20) NOT NULL DEFAULT '',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coe_requests`
--

INSERT INTO `coe_requests` (`id`, `student_id`, `purpose`, `copies`, `status`, `registrar_notes`, `approved_by`, `approved_at`, `control_number`, `semester`, `school_year`, `requested_at`, `updated_at`) VALUES
(1, 145, 'General Purpose', 1, 'Approved', '', 0, '2026-03-12 03:56:28', 'COE-202603-0001', '', '', '2026-03-11 19:45:54', '2026-03-11 19:56:28'),
(2, 144, 'General Purpose', 1, 'Approved', '', 0, '2026-03-12 05:28:45', 'COE-202603-0002', '', '', '2026-03-11 20:38:17', '2026-03-11 21:28:45'),
(3, 146, 'General Purpose', 1, 'Pending', NULL, NULL, NULL, NULL, '', '', '2026-03-19 19:08:18', '2026-03-19 19:08:18'),
(4, 151, 'General Purpose', 1, 'Pending', NULL, NULL, NULL, NULL, '', '', '2026-03-19 20:26:17', '2026-03-19 20:26:17'),
(5, 152, 'General Purpose', 1, 'Pending', NULL, NULL, NULL, NULL, '', '', '2026-03-20 16:38:54', '2026-03-20 16:38:54'),
(6, 153, 'General Purpose', 1, 'Pending', NULL, NULL, NULL, NULL, '', '', '2026-03-20 16:41:42', '2026-03-20 16:41:42'),
(7, 154, 'General Purpose', 1, 'Pending', NULL, NULL, NULL, NULL, '', '', '2026-03-20 18:56:37', '2026-03-20 18:56:37'),
(8, 149, 'General Purpose', 1, 'Pending', NULL, NULL, NULL, NULL, '', '', '2026-03-21 16:28:47', '2026-03-21 16:28:47'),
(9, 164, 'General Purpose', 1, 'Pending', NULL, NULL, NULL, NULL, '', '', '2026-03-24 07:45:05', '2026-03-24 07:45:05'),
(10, 171, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-26 18:13:35', 'COE-202603-0003', '', '', '2026-03-26 10:13:35', '2026-03-26 10:13:35'),
(11, 170, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-27 15:41:35', 'COE-202603-0004', '', '', '2026-03-27 07:41:35', '2026-03-27 07:41:35'),
(12, 172, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-27 15:54:57', 'COE-202603-0005', '', '', '2026-03-27 07:54:57', '2026-03-27 07:54:57'),
(13, 173, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-27 22:34:05', 'COE-202603-0006', '', '', '2026-03-27 14:34:05', '2026-03-27 14:34:05'),
(14, 174, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-27 23:10:10', 'COE-202603-0007', '', '', '2026-03-27 15:10:10', '2026-03-27 15:10:10'),
(15, 175, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-27 23:14:29', 'COE-202603-0008', '', '', '2026-03-27 15:14:29', '2026-03-27 15:14:29'),
(16, 176, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 0, '2026-03-27 23:21:25', 'COE-202603-0009', '', '', '2026-03-27 15:21:25', '2026-03-27 15:21:25'),
(17, 177, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-28 02:07:41', 'COE-202603-0010', '', '', '2026-03-27 18:07:41', '2026-03-27 18:07:41'),
(18, 178, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-28 02:25:04', 'COE-202603-0011', '', '', '2026-03-27 18:25:04', '2026-03-27 18:25:04'),
(19, 179, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-28 14:24:49', 'COE-202603-0012', '', '', '2026-03-28 06:24:49', '2026-03-28 06:24:49'),
(20, 180, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-28 14:48:20', 'COE-202603-0013', '', '', '2026-03-28 06:48:20', '2026-03-28 06:48:20'),
(21, 181, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-28 15:07:51', 'COE-202603-0014', '2nd Semester', '2028-2029', '2026-03-28 07:07:51', '2026-03-28 14:55:29'),
(22, 181, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 0, '2026-03-28 23:49:10', 'COE-202603-0015', '1st Semester', '2029-2030', '2026-03-28 15:49:10', '2026-03-28 15:49:10'),
(23, 181, 'General Purpose', 1, 'Approved', 'Auto-approved for past enrollment term', 0, '2026-03-28 23:49:10', 'COE-202603-0016', '2nd Semester', '2026-2027', '2026-03-28 15:49:10', '2026-03-28 15:49:10'),
(24, 181, 'General Purpose', 1, 'Approved', 'Auto-approved for past enrollment term', 0, '2026-03-28 23:49:10', 'COE-202603-0017', '1st Semester', '2027-2028', '2026-03-28 15:49:10', '2026-03-28 15:49:10'),
(25, 181, 'General Purpose', 1, 'Approved', 'Auto-approved for past enrollment term', 0, '2026-03-28 23:49:10', 'COE-202603-0018', '2nd Semester', '2027-2028', '2026-03-28 15:49:10', '2026-03-28 15:49:10'),
(26, 181, 'General Purpose', 1, 'Approved', 'Auto-approved for past enrollment term', 0, '2026-03-28 23:49:10', 'COE-202603-0019', '1st Semester', '2028-2029', '2026-03-28 15:49:10', '2026-03-28 15:49:10'),
(27, 182, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 0, '2026-03-29 02:34:36', 'COE-202603-0020', '1st Semester', '2029-2030', '2026-03-28 18:34:36', '2026-03-28 18:34:36'),
(28, 183, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 02:42:56', 'COE-202603-0021', '1st Semester', '2029-2030', '2026-03-28 18:42:56', '2026-03-28 18:42:56'),
(29, 184, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 03:22:00', 'COE-202603-0022', '1st Semester', '2029-2030', '2026-03-28 19:22:00', '2026-03-28 19:22:00'),
(30, 185, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 03:33:08', 'COE-202603-0023', '1st Semester', '2029-2030', '2026-03-28 19:33:08', '2026-03-28 19:33:08'),
(31, 185, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 03:38:24', 'COE-202603-0024', '2nd Semester', '2029-2030', '2026-03-28 19:38:24', '2026-03-28 19:38:24'),
(32, 187, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 04:36:02', 'COE-202603-0025', '1st Semester', '2025-2026', '2026-03-28 20:36:02', '2026-03-28 20:36:02'),
(33, 188, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 04:58:53', 'COE-202603-0026', '1st Semester', '2025-2026', '2026-03-28 20:58:53', '2026-03-28 20:58:53'),
(34, 188, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 05:13:38', 'COE-202603-0027', '2nd Semester', '2025-2026', '2026-03-28 21:13:38', '2026-03-28 21:13:38'),
(35, 189, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 17:09:24', 'COE-202603-0028', '2nd Semester', '2026-2027', '2026-03-29 09:09:24', '2026-03-29 09:09:24'),
(36, 189, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 17:48:18', 'COE-202603-0029', '1st Semester', '2027-2028', '2026-03-29 09:48:18', '2026-03-29 09:48:18'),
(37, 190, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 23:13:02', 'COE-202603-0030', '1st Semester', '2027-2028', '2026-03-29 15:13:02', '2026-03-29 15:13:02'),
(38, 190, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-29 23:33:50', 'COE-202603-0031', '2nd Semester', '2027-2028', '2026-03-29 15:33:50', '2026-03-29 15:33:50'),
(39, 191, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-30 01:15:11', 'COE-202603-0032', '2nd Semester', '2025-2026', '2026-03-29 17:15:11', '2026-03-29 17:15:11'),
(40, 191, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-30 01:19:50', 'COE-202603-0033', '1st Semester', '2026-2027', '2026-03-29 17:19:50', '2026-03-29 17:19:50'),
(41, 192, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-30 18:18:00', 'COE-202603-0034', '1st Semester', '2026-2027', '2026-03-30 10:18:00', '2026-03-30 10:18:00'),
(42, 192, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-30 18:50:56', 'COE-202603-0035', '2nd Semester', '2026-2027', '2026-03-30 10:50:56', '2026-03-30 10:50:56'),
(43, 192, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-30 20:10:12', 'COE-202603-0036', '1st Semester', '2027-2028', '2026-03-30 12:10:12', '2026-03-30 12:10:12'),
(44, 193, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 0, '2026-03-31 02:52:39', 'COE-202603-0037', '1st Semester', '2027-2028', '2026-03-30 18:52:39', '2026-03-30 18:52:39'),
(45, 194, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-03-31 16:06:50', 'COE-202603-0038', '1st Semester', '2027-2028', '2026-03-31 08:06:50', '2026-03-31 08:06:50'),
(46, 195, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-01 15:46:00', 'COE-202604-0001', '1st Semester', '2027-2028', '2026-04-01 07:46:00', '2026-04-01 07:46:00'),
(47, 196, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 15:21:13', 'COE-202604-0002', '1st Semester', '2026-2027', '2026-04-03 07:21:13', '2026-04-03 07:21:13'),
(48, 197, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 15:26:42', 'COE-202604-0003', '1st Semester', '2026-2027', '2026-04-03 07:26:42', '2026-04-03 07:26:42'),
(49, 198, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 15:39:28', 'COE-202604-0004', '1st Semester', '2026-2027', '2026-04-03 07:39:28', '2026-04-03 07:39:28'),
(50, 199, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 15:44:09', 'COE-202604-0005', '1st Semester', '2026-2027', '2026-04-03 07:44:09', '2026-04-03 07:44:09'),
(51, 200, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 15:49:05', 'COE-202604-0006', '1st Semester', '2026-2027', '2026-04-03 07:49:05', '2026-04-03 07:49:05'),
(52, 200, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 17:12:33', 'COE-202604-0007', '2nd Semester', '2026-2027', '2026-04-03 09:12:33', '2026-04-03 09:12:33'),
(53, 195, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 18:12:42', 'COE-202604-0008', '2nd Semester', '2026-2027', '2026-04-03 10:12:42', '2026-04-03 10:12:42'),
(54, 202, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 22:16:41', 'COE-202604-0009', '1st Semester', '2027-2028', '2026-04-03 14:16:41', '2026-04-03 14:16:41'),
(55, 203, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 22:19:08', 'COE-202604-0010', '1st Semester', '2027-2028', '2026-04-03 14:19:08', '2026-04-03 14:19:08'),
(56, 204, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-03 22:22:57', 'COE-202604-0011', '1st Semester', '2027-2028', '2026-04-03 14:22:57', '2026-04-03 14:22:57');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `credits` int(11) NOT NULL DEFAULT 3,
  `faculty_id` int(11) DEFAULT NULL,
  `capacity` int(11) DEFAULT 40,
  `semester` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT '1st Year',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lec_units` int(11) DEFAULT 0,
  `lab_units` int(11) DEFAULT 0,
  `is_general` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = available across all programs, no program restriction',
  `is_lab` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `faculty_id`, `capacity`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `updated_at`, `lec_units`, `lab_units`, `is_general`, `is_lab`) VALUES
(1, 'GE109', 'Understanding the Self', 3, 143, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:53:16', 3, 0, 0, 0),
(2, 'GE108', 'Ethics', 3, 143, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:53:16', 3, 0, 0, 0),
(3, 'CC100', 'Introduction to Computing', 3, 9, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(4, 'CC101', 'Computer Programming 1', 3, 143, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:53:16', 2, 1, 0, 1),
(5, 'IT-CMT', 'Computer Organization and Maintenance', 3, 143, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:53:16', 2, 1, 0, 1),
(6, 'GE105', 'Mathematics in the Modern World', 3, 143, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:53:16', 3, 0, 0, 0),
(7, 'PE1', 'Physical Education 1 (Aquatics)', 2, 143, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-04-03 07:18:36', 2, 0, 0, 0),
(8, 'NSTP1', 'National Service Training Program1', 3, 143, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-04-03 07:18:36', 3, 0, 0, 0),
(9, 'GE101', 'Purposive Communication', 3, 141, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:41:09', 3, 0, 0, 0),
(10, 'IT100', 'Introduction to Human Computer Interaction', 3, 141, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:41:09', 2, 1, 0, 1),
(11, 'CC102', 'Computer Programming 2', 3, 141, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:41:09', 2, 1, 0, 1),
(12, 'IS103', 'IT Infrastructure and Network Technologies', 3, 141, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:41:09', 2, 1, 0, 1),
(13, 'GE103', 'Art Appreciation', 3, 141, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:41:09', 3, 0, 0, 0),
(14, 'PE2', 'Physical Education 2 (Outdoor Pursuits)', 2, 138, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:53:10', 2, 0, 0, 0),
(15, 'NSTP2', 'National Service Training Program 2', 3, 141, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-03-28 18:41:09', 3, 0, 0, 0),
(16, 'CC103', 'Data Structures and Algorithms', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(17, 'CC105', 'Application Development and Emerging Technologies', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(18, 'IT105', 'Discrete Mathematics', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 3, 0, 0, 0),
(19, 'ELEC400', 'Object-Oriented Programming', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(20, 'EMC203', 'Usability, HCI, and User Interaction Design', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(21, 'GE109-2', 'Understanding the Self (GE Elective)', 3, 143, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-28 18:53:16', 3, 0, 0, 0),
(22, 'PE3', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 0, 0, 0),
(23, 'CC104', 'Information Management', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(24, 'IT103', 'Fundamentals of Database Systems', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(25, 'IT107', 'Networking 1', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(26, 'GE110', 'Rizal\'s Life and Works', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 3, 0, 0, 0),
(27, 'GE115', 'Philippine Literature', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 3, 0, 0, 0),
(28, 'PE4', 'Physical Education 4 (Endurance Exercises)', 2, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 0, 0, 0),
(29, 'IT104', 'Integrative Programming and Technologies 1', 3, 9, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(30, 'IT101', 'Information Assurance and Security 1', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(31, 'IT108', 'Networking 2', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(32, 'ELEC401', 'Multimedia Systems', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(33, 'IT106', 'Quantitative Methods', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(34, 'EMC207', 'Principles of 3D Animation', 3, 9, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(35, 'GE111', 'Social and Professional Issues', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 3, 0, 0, 0),
(36, 'IT102', 'Information Assurance and Security 2', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(37, 'IT110', 'System Integration and Architecture 1', 3, 9, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(38, 'ELEC103', 'Platform Technologies', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(39, 'GE104', 'Readings in Philippine History', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 3, 0, 0, 0),
(40, 'GE106', 'Science, Technology, and Society', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 3, 0, 0, 0),
(41, 'GE107', 'The Contemporary World', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 3, 0, 0, 0),
(42, 'IT109', 'System Administration and Maintenance', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(43, 'DM101', 'Organization and Management Systems', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(44, 'ELEC403', 'Web Systems and Technology', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 2, 1, 0, 1),
(45, 'CAP501', 'Capstone Project', 6, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 6, 0, 0, 0),
(46, 'OJT-BSIT', 'Internship (486 hours)', 9, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', '2026-03-11 16:27:10', 9, 0, 0, 0),
(550, 'GE100', 'Conversational English and Personality Development', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-28 18:37:30', 3, 0, 0, 0),
(551, 'BME100-BSA', 'International Business and Trade', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(552, 'AEC111', 'Financial Accounting and Reporting', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-28 18:37:30', 3, 0, 0, 0),
(553, 'AEC109', 'Managerial Economics', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-28 18:37:30', 3, 0, 0, 0),
(554, 'BSNA102', 'Organization & Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(557, 'AEC112', 'Conceptual Framework and Accounting Standards', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(558, 'AEC120', 'Cost Accounting and Control', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(559, 'BSNA101', 'Fundamentals of Accountancy, Business and Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(560, 'AEC113', 'Intermediate Accounting 1', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(561, 'BME101-BSA', 'Strategic Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(562, 'PE2-BMD', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, 138, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', '2026-03-28 18:53:10', 2, 0, 0, 0),
(564, 'BSNA103', 'Business Marketing', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(565, 'AEC121', 'Strategic Cost Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(566, 'AEC108', 'Governance, Business Ethics, Risk Mgt. and Internal Control', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(567, 'AEC116', 'Financial Markets', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(568, 'BME103-BSA', 'Law on Obligations and Contracts', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-04-03 04:53:34', 3, 0, 0, 0),
(569, 'AEC107', 'Statistical Analysis and Software Application', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 1, 0, 1),
(570, 'AEC105', 'Intermediate Accounting 2', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(571, 'AEC117', 'Financial Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(572, 'PE3-BMD', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 0, 0, 0),
(573, 'GE103-BMD', 'Art Appreciation', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(574, 'AEC101', 'Business Laws and Regulations', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(575, 'AEC115', 'Intermediate Accounting 3', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(576, 'AEC118', 'Accounting Information System', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 1, 0, 1),
(577, 'AEC124', 'Income Taxation', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(578, 'GE116-BMD', 'World Literature', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(579, 'BSNA104', 'Business Finance', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(580, 'GE110-BMD', 'Rizal\'s Life and Works', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(581, 'PE4-BMD', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 0, 0, 0),
(582, 'GE104-BMD', 'Readings in the Philippine History', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(583, 'AEC103', 'Management Science', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(584, 'AEC119', 'IT Application Tools in Business', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 1, 0, 1),
(585, 'AEC122', 'Strategic Business Analysis', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(586, 'AEC123', 'Business Tax', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(587, 'AEC110', 'Economic Development', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(588, 'AEC102', 'Regulatory Framework and Legal Issues in Business', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-04-03 04:53:38', 3, 0, 0, 0),
(589, 'GE115-BMD', 'Philippine Literature', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(590, 'BME102-BSA', 'Operations Management and TQM', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(591, 'GE106-BMD', 'Science, Technology and Society', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(592, 'GE107-BMD', 'The Contemporary World', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(593, 'AEC104', 'Accounting Research Methods', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(594, 'ELEC1-BSA', 'Updates in Financial Reporting and Standards', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(595, 'APE108', 'Accounting for Government and Non-profit Organizations', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(596, 'APE107', 'Accounting for Business Combinations', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(597, 'AEC114', 'Accounting Internship', 6, NULL, 40, 'Summer', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 6, 0, 0, 0),
(598, 'APE101', 'Auditing and Assurance Principles', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(599, 'APE102', 'Auditing and Assurance: Concepts and Applications 1', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(600, 'APE103', 'Auditing and Assurance: Concepts and Applications 2', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(601, 'AEC106', 'Accountancy Research', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(602, 'APE106', 'Accounting for Special Transactions', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(603, 'APE109', 'Financial Accounting and Reporting Integration', 6, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 6, 0, 0, 0),
(604, 'APE111', 'Taxation Integration', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(605, 'APE112', 'Regulatory Framework for Business Transactions Integration', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(606, 'APE113', 'Management Advisory Services Integration', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(607, 'APE104', 'Auditing and Assurance: Specialized Industries', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(608, 'APE105', 'Auditing in a CIS Environment', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 1, 0, 1),
(609, 'SCP101', 'Introduction to Supply Chain Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(610, 'TMC100', 'Fundamentals of Customs and Tariff System', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(611, 'SCP102', 'Warehouse Operations Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(612, 'BSNA101-CA', 'Fundamentals of Accounting, Business and Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(613, 'GE103-CA', 'Art Appreciation', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(614, 'BLT100', 'Business Law (Obligations, Negotiable Instruments, IP and Insurance Law)', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(615, 'CMC100', 'Border Control and Security', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(616, 'SCP103', 'Procurement and Inventory Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(617, 'CMC101', 'Customs Operations and Cargo Handling', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(618, 'TMC101', 'Commodity Classification System', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(619, 'TMC106', 'International Trade Organizations, Agreements and Rules of Origin', 5, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(620, 'BLT101', 'Taxation (Income and Business Taxation)', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(621, 'SCP104', 'Transportation Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(622, 'CMC102', 'Customs Warehousing', 5, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(623, 'TMC102', 'Customs Valuation System', 5, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(624, 'BSNA104-CA', 'Business Finance', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(625, 'CMC106', 'Ethics and Standards of the Customs Broker', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(626, 'CMC103', 'Customs Clearance', 5, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(627, 'TMC103', 'Customs Appraisal and Assessment', 5, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(628, 'BME100-CA', 'Operations Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(629, 'BME101-CA', 'Strategic Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(630, 'CMC105', 'Customs Post Clearance Audit and Fraud Detection', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(631, 'CMC104', 'Customs Proceedings', 5, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(632, 'TMC105', 'Special Duties and Trade Remedies', 5, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(633, 'TMC104', 'Excise Taxes, Liquidation of Duty and Surcharges', 5, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(634, 'CMC107', 'Competency Assessment in Customs Management', 5, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(635, 'TMC107', 'Competency Assessment in Tariff Management', 5, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(636, 'RSH100', 'Research 1', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(637, 'RSH101', 'Research 2', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(638, 'OJT100', 'Internship', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(639, 'BME102-BSE', 'International Business and Trade', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(640, 'ECS101', 'Entrepreneurial Behavior', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(641, 'ECS102', 'Opportunity Seeking', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(642, 'ECS108', 'Microeconomics', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(643, 'BME103-BSE', 'Human Resource Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', '2026-04-03 04:53:34', 3, 0, 0, 0),
(644, 'ECS107', 'Market Research and Consumer Behavior', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(645, 'ECS109', 'Business Law and Taxation', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(646, 'ECS114', 'Programs and Policies on Enterprise Development', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(647, 'BME104', 'Basic Accounting', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(648, 'ECS111', 'Pricing and Costing', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(649, 'BME100-BSE', 'Operations Management (Total Quality Management)', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(650, 'EST101', 'Specialized Track 1', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(651, 'EEC101', 'Elective 1 (Supply Chain Management)', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(652, 'ECS112', 'Innovation and Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(653, 'EST102', 'Specialized Track 2', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(654, 'EEC102', 'Elective 2 (E-Commerce)', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(655, 'ECS103', 'Business Plan Preparation', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(656, 'ECS110', 'Financial Management and Analysis for Decision Making', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(657, 'ECS113', 'Social Entrepreneurship', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(658, 'BME101-BSE', 'Strategic Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(659, 'EST103', 'Specialized Track 3', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(660, 'EEC103', 'Elective 3 (Hospitality Management)', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(661, 'ECS104', 'Business Plan Implementation 1', 5, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 3, 0, 1),
(662, 'EST104', 'Specialized Track 4', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(663, 'EEC104', 'Elective 4 (Managing a Service Enterprise)', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(664, 'ECS105', 'Business Plan Implementation 2', 5, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 3, 0, 1),
(665, 'RE-FUN013', 'Fundamentals of Real Estate Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(666, 'GE-ENG013', 'Conversational English Competency', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(667, 'GE-FIL013', 'Komunikasyon Sa Akademikong Filipino', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(668, 'GE-MAT013', 'College Algebra - Math 1', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(669, 'RE-TAX013', 'Business and Real Estate Taxation', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(670, 'AC-TAX013', 'Economics with Taxation and Land Reform', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(671, 'GE-NSC013', 'Biological Science', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(672, 'RE-HGP013', 'Human and Physical Geography', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(673, 'GE-PHE012', 'Recreational Activities', 2, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 0, 0, 0),
(674, 'GE-NST013', 'National Service Training Program 1', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(675, 'BN-MGT013', 'Principles of Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(676, 'RE-REC013', 'Fundamentals of Real Estate Consulting', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(677, 'LW-BSN013', 'Law on Obligations and Contracts w/ Real Properties', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(678, 'GE-NSC023', 'Environment and Greenbuilding Technology', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(679, 'GE-ENG023', 'Grammar and Composition', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(680, 'RE-PAD013', 'Real Estate Planning and Development', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(681, 'RE-REB013', 'Real Estate Brokerage', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(682, 'GE-FIL023', 'Pagbasa at pagsulat Tungo Sa Pananaliksik', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(683, 'GE-PHE032', 'Individual and Team Sports', 2, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 0, 0, 0),
(684, 'GE-NST023', 'National Service Training Program 2', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(685, 'BN-MKT013', 'Principles of Marketing', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(686, 'RE-LAR013', 'Legal Aspects of Real Estate', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(687, 'GE-BAC013', 'Basic Accounting 1', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(688, 'RE-CSE013', 'Consulting for Specific Engagements', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(689, 'BN-ECO013', 'Macroeconomics and Microeconomics Theory & Practice', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(690, 'RE-REA013', 'Real Estate Appraisal and Property Management', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(691, 'IT-CSA013', 'Computer Software Application', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 1, 0, 1),
(692, 'GE-ENG033', 'Business Correspondence and Technical Writing', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(693, 'GE-PHE052', 'Rhythmic Activities', 2, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 0, 0, 0),
(694, 'BN-FIN013', 'Basic Finance', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(695, 'RE-MKB013', 'Real Estate Marketing and Brokerage', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(696, 'RE-CIA013', 'Real Estate Consulting and Investments Analysis', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(697, 'RE-PVS013', 'Philippine Valuation Studies for Real Estate', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(698, 'GE-SCF013', 'Society and Culture with Family Planning', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(699, 'RE-POE013', 'Principles of Ecology', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(700, 'GE-PSY013', 'General Psychology w/ Drug Education, SARS, HIV/AIDS', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(701, 'GE-BAC023', 'Basic Accounting 2', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(702, 'GE-PHE062', 'Sports and Games', 2, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 0, 0, 0),
(703, 'IT-DBM013', 'Database Management System 1', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 1, 0, 1),
(704, 'RE-PM013', 'Property Management System 1', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(705, 'GE-GCR013', 'Good Governance and Corporate Responsibility', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(706, 'RE-HSD013', 'Housing and Subdivision Development', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(707, 'GE-MAT053', 'Business Statistics', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(708, 'GE-LCT013', 'Logic and Critical Thinking', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(709, 'RE-AGS013', 'Appraisal/Assessment in Government Sector', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(710, 'RE-REF013', 'Real Estate Finance', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(711, 'GE-PHC013', 'Philippine History and Culture', 3, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(712, 'RE-ARD013', 'Appraisal Report and Data Gathering', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0);
INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `faculty_id`, `capacity`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `updated_at`, `lec_units`, `lab_units`, `is_general`, `is_lab`) VALUES
(713, 'RE-ESP013', 'Ethical Standards for Real Estate Practice', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(714, 'RE-REE013', 'Real Estate Economics', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(715, 'RE-CCD013', 'Condominium Concept and other Specialized Development', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(716, 'BN-HRM013', 'Human Resource Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(717, 'GE-APA013', 'Appreciation of Arts', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(718, 'GE-LWR013', 'Life and Works of Rizal', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(719, 'BN-HBO013', 'Human Behavior in Organization', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(720, 'GE-ENG053', 'Philippine Literature', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(721, 'RE-INR015', 'Integration and Review for Real Estate', 5, NULL, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 5, 0, 0, 0),
(722, 'GE-OJT013', 'On-the-job Training (600hrs)', 6, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '4th Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 6, 0, 0, 0),
(723, 'GE112', 'Pilipino: Retorika', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(724, 'EMC200', 'Free Hand and Digital Drawing', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '1st Year', '2026-03-05 01:03:52', '2026-03-11 18:02:41', 2, 1, 0, 1),
(725, 'GE113', 'Pilipino: Pagsasalingwika', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '1st Year', '2026-03-05 01:03:52', '2026-03-11 18:02:41', 3, 0, 0, 0),
(726, 'GE114', 'Pilipino: Tula, Sanaysay, Nobela', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(727, 'EMC202', 'Computer Graphics Programming', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 1, 0, 1),
(728, 'EMC204', 'Principles of 2D Animation', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 1, 0, 1),
(729, 'OJT-CIMT', 'Internship', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(730, 'CAP501-CIMT', 'Capstone Project', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(2027, 'GE116', 'World Literature', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '1st Year', '2026-03-05 04:05:04', '2026-03-11 18:02:41', 3, 0, 0, 0),
(2028, 'EMC201', 'Introduction to Game Design and Development', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '2nd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2029, 'GD301', 'Game Programming 1', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '2nd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2030, 'GD302', 'Game Programming 2', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '2nd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2031, 'EMC205', 'Audio Design and Sound Engineering', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2032, 'GD303', 'Applied Mathematics for Games', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2033, 'GD305', 'Game Programming 3', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2034, 'GE102', 'Creative Writing', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 3, 0, 0, 0),
(2035, 'EMC206', 'Scriptwriting and Story Board Design', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2036, 'EMC208', 'Design Production and Process', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2037, 'GD304', 'Applied Physics for Games', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2038, 'GD306', 'Artificial Intelligence in Games', 3, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2039, 'GD307', 'Advance Game Design', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '4th Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2040, 'GD308', 'Game Networking', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '4th Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2041, 'GD309', 'Game Production', 3, NULL, 40, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '4th Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 2, 1, 0, 1),
(2042, 'ITRN', 'Internship (486 hours)', 9, NULL, 40, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '4th Year', '2026-03-05 04:05:04', '2026-03-11 16:27:10', 9, 0, 0, 0),
(2043, 'GEALG12', 'algebra', 3, NULL, 40, '1st Semester', '', 'Academic Track', 'General Academic Strand (GAS)', 'Grade 11', '2026-03-30 18:48:41', '2026-03-30 18:53:22', 3, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `course_prerequisites`
--

CREATE TABLE `course_prerequisites` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL COMMENT 'The course that has a prerequisite',
  `prerequisite_id` int(11) NOT NULL COMMENT 'The course that must be passed first',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores prerequisite relationships between courses.';

--
-- Dumping data for table `course_prerequisites`
--

INSERT INTO `course_prerequisites` (`id`, `course_id`, `prerequisite_id`, `created_at`) VALUES
(1, 16, 11, '2026-03-27 08:22:28'),
(3, 11, 4, '2026-03-28 19:14:44');

-- --------------------------------------------------------

--
-- Table structure for table `course_sections`
--

CREATE TABLE `course_sections` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL COMMENT 'FK → courses.id',
  `section_code` varchar(30) NOT NULL COMMENT 'e.g. A, B, Section-1',
  `faculty_id` int(11) DEFAULT NULL COMMENT 'FK → users.id (faculty role)',
  `room_id` int(11) DEFAULT NULL COMMENT 'FK → rooms.id',
  `day` varchar(50) DEFAULT NULL COMMENT 'e.g. MWF, TTh',
  `time_start` time DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 40,
  `semester` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL COMMENT 'e.g. 2026-2027',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='One row per section/timeslot of a course';

--
-- Dumping data for table `course_sections`
--

INSERT INTO `course_sections` (`id`, `course_id`, `section_code`, `faculty_id`, `room_id`, `day`, `time_start`, `time_end`, `capacity`, `semester`, `school_year`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 3, 'A', 9, NULL, '', NULL, NULL, 40, '1st Semester', '2026-2027', 1, '2026-03-11 16:27:12', '2026-03-11 16:27:12'),
(2, 29, 'A', 9, NULL, NULL, NULL, NULL, 40, '1st Semester', '2026-2027', 1, '2026-03-11 16:27:12', '2026-03-11 16:27:12'),
(3, 34, 'A', 9, NULL, NULL, NULL, NULL, 40, '1st Semester', '2026-2027', 1, '2026-03-11 16:27:12', '2026-03-11 16:27:12'),
(4, 37, 'A', 9, NULL, NULL, NULL, NULL, 40, '2nd Semester', '2026-2027', 1, '2026-03-11 16:27:12', '2026-03-11 16:27:12');

-- --------------------------------------------------------

--
-- Table structure for table `email_notifications`
--

CREATE TABLE `email_notifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `recipient` varchar(150) NOT NULL COMMENT 'Email address sent to',
  `type` enum('enrollment_report','soa','payment_receipt','archive_notice') NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `status` enum('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Log of all emails sent to students/guardians';

--
-- Dumping data for table `email_notifications`
--

INSERT INTO `email_notifications` (`id`, `student_id`, `recipient`, `type`, `subject`, `status`, `error_message`, `sent_at`, `created_at`) VALUES
(1, 154, 'shane2@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-23 06:06:43'),
(2, 160, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-23 06:27:03'),
(3, 160, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-23 06:27:46'),
(4, 160, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-23 06:48:17'),
(5, 160, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-23 07:50:48', '2026-03-23 06:50:48'),
(6, 160, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-23 08:07:50', '2026-03-23 07:07:50'),
(7, 163, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — Claire Shannaiah | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-24 08:35:10', '2026-03-24 07:35:10'),
(8, 165, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Full (₱27,822.00)', 'pending', NULL, NULL, '2026-03-24 07:55:52'),
(9, 165, 'nodadoshanecarlo111@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 1st sem ay 2025-2026', 'sent', '', '2026-03-25 06:41:29', '2026-03-25 05:41:29'),
(10, 165, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 1st sem ay 2025-2026', 'sent', '', '2026-03-25 06:41:33', '2026-03-25 05:41:33'),
(11, 167, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-25 07:00:53'),
(12, 167, 'nodadoshanecarlo@gmail.com', '', 'Enrollment Confirmed – Shane Carlo Nodado (STU-2026-0021)', 'failed', NULL, NULL, '2026-03-25 07:01:18'),
(13, 168, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,499.50)', 'pending', NULL, NULL, '2026-03-25 07:21:05'),
(14, 168, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Carlo Nodado (STU-2026-0022)', 'failed', NULL, NULL, '2026-03-25 07:21:24'),
(15, 168, 'gongorashane2122@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:22:54', '2026-03-25 07:22:54'),
(16, 168, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:22:59', '2026-03-25 07:22:59'),
(17, 166, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:25:49', '2026-03-25 07:25:49'),
(18, 169, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,500.00)', 'pending', NULL, NULL, '2026-03-25 07:47:45'),
(19, 169, 'gongorashane2123@gmail.com', 'soa', 'Statement of Account — john bautista | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:47:49', '2026-03-25 07:47:49'),
(20, 169, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — john bautista | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:47:55', '2026-03-25 07:47:55'),
(21, 162, 'shane123@example.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:48:42', '2026-03-25 07:48:42'),
(22, 162, 'nodadoshanecarlo@gmail.com', '', 'Enrollment Confirmed – Shane Carlo Nodado (STU-2026-0016)', 'failed', NULL, NULL, '2026-03-25 07:48:44'),
(23, 162, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:48:47', '2026-03-25 07:48:47'),
(24, 169, 'gongorashane2123@gmail.com', 'enrollment_report', 'Enrollment Confirmation — john bautista | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:49:02', '2026-03-25 07:49:02'),
(25, 169, 'nodadoshanecarlo@gmail.com', '', 'Enrollment Confirmed – john bautista (STU-2026-0023)', 'failed', NULL, NULL, '2026-03-25 07:49:04'),
(26, 169, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — john bautista | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:49:07', '2026-03-25 07:49:07'),
(27, 170, 'gongorashanecarlo@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,000.00)', 'pending', NULL, NULL, '2026-03-25 07:57:09'),
(28, 170, 'gongorashane2223@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:57:15', '2026-03-25 07:57:15'),
(29, 170, 'gongorashanecarlo@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-25 08:57:19', '2026-03-25 07:57:19'),
(30, 171, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-26 10:09:58'),
(31, 171, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-26 11:10:08', '2026-03-26 10:10:08'),
(32, 171, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-26 11:10:13', '2026-03-26 10:10:13'),
(33, 171, 'anamariebinoya0909@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-26 11:13:41', '2026-03-26 10:13:41'),
(34, 171, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0025)', 'failed', NULL, NULL, '2026-03-26 10:13:42'),
(35, 171, 'anamariebinoya0909@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-26 11:13:46', '2026-03-26 10:13:46'),
(36, 169, 'gongorashane2123@gmail.com', 'soa', 'Statement of Account — john bautista | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-26 21:34:24', '2026-03-26 20:34:24'),
(37, 169, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — john bautista | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-26 21:34:29', '2026-03-26 20:34:29'),
(38, 171, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Full (₱116,947.00)', 'pending', NULL, NULL, '2026-03-26 21:08:01'),
(39, 171, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st sem ay 2025-2026', 'sent', '', '2026-03-26 22:08:06', '2026-03-26 21:08:06'),
(40, 171, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st sem ay 2025-2026', 'sent', '', '2026-03-26 22:08:11', '2026-03-26 21:08:11'),
(41, 170, 'gongorashane2223@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:41:40', '2026-03-27 07:41:40'),
(42, 170, 'gongorashanecarlo@gmail.com', '', 'Enrollment Confirmed – Shane Carlo Nodado (STU-2026-0024)', 'failed', NULL, NULL, '2026-03-27 07:41:42'),
(43, 170, 'gongorashanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:41:45', '2026-03-27 07:41:45'),
(44, 170, 'gongorashane2223@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:41:46', '2026-03-27 07:41:46'),
(45, 170, 'gongorashanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:41:51', '2026-03-27 07:41:51'),
(46, 172, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,500.00)', 'pending', NULL, NULL, '2026-03-27 07:53:13'),
(47, 172, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:53:18', '2026-03-27 07:53:18'),
(48, 172, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:53:23', '2026-03-27 07:53:23'),
(49, 172, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:55:02', '2026-03-27 07:55:02'),
(50, 172, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-27 07:55:04'),
(51, 172, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:55:07', '2026-03-27 07:55:07'),
(52, 172, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:55:09', '2026-03-27 07:55:09'),
(53, 172, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 08:55:14', '2026-03-27 07:55:14'),
(54, 173, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,000.00)', 'pending', NULL, NULL, '2026-03-27 14:33:32'),
(55, 173, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 15:33:38', '2026-03-27 14:33:38'),
(56, 173, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 15:33:43', '2026-03-27 14:33:43'),
(57, 173, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 15:34:10', '2026-03-27 14:34:10'),
(58, 173, 'nodadoshanecarlo@gmail.com', '', 'Enrollment Confirmed – Shane Carlo Nodado (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-27 14:34:12'),
(59, 173, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 15:34:15', '2026-03-27 14:34:15'),
(60, 173, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 15:34:18', '2026-03-27 14:34:18'),
(61, 173, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 15:34:23', '2026-03-27 14:34:23'),
(62, 174, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Downpayment (₱9,000.00)', 'pending', NULL, NULL, '2026-03-27 15:09:40'),
(63, 174, 'shane1', 'soa', 'Statement of Account — kuplas Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:09:40'),
(64, 174, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — kuplas Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:09:45', '2026-03-27 15:09:45'),
(65, 174, 'shane1', 'enrollment_report', 'Enrollment Confirmation — kuplas Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:10:10'),
(66, 174, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — kuplas Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:10:15', '2026-03-27 15:10:15'),
(67, 174, 'nodadoshanecarlo@gmail.com', '', 'Enrollment Confirmed – kuplas Nodado (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-27 15:10:17'),
(68, 174, 'shane1', 'enrollment_report', 'Enrollment Confirmation — kuplas Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:10:17'),
(69, 174, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — kuplas Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:10:22', '2026-03-27 15:10:22'),
(70, 175, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-27 15:14:07'),
(71, 175, 'shane1', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:14:07'),
(72, 175, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:14:12', '2026-03-27 15:14:12'),
(73, 175, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:14:29'),
(74, 175, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:14:33', '2026-03-27 15:14:33'),
(75, 175, 'nodadoshanecarlo@gmail.com', '', 'Enrollment Confirmed – Shane Carlo Nodado (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-27 15:14:35'),
(76, 175, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:14:36'),
(77, 175, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Carlo Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:14:41', '2026-03-27 15:14:41'),
(78, 176, 'cuevasdave01@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,500.00)', 'pending', NULL, NULL, '2026-03-27 15:21:20'),
(79, 176, 'shane2', 'soa', 'Statement of Account — rochhele Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:21:20'),
(80, 176, 'cuevasdave01@gmail.com', 'soa', 'Statement of Account — rochhele Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:21:24', '2026-03-27 15:21:24'),
(81, 176, 'shane2', 'enrollment_report', 'Enrollment Confirmation — rochhele Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:21:48'),
(82, 176, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — rochhele Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:21:53', '2026-03-27 15:21:53'),
(83, 176, 'cuevasdave01@gmail.com', '', 'Enrollment Confirmed – rochhele Nodado (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-27 15:21:55'),
(84, 176, 'shane2', 'enrollment_report', 'Enrollment Confirmation — rochhele Nodado | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 15:21:55'),
(85, 176, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — rochhele Nodado | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 16:22:01', '2026-03-27 15:22:01'),
(86, 177, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-27 18:06:43'),
(87, 177, 'shane3', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2027-2026', 'failed', '', NULL, '2026-03-27 18:06:44'),
(88, 177, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2027-2026', 'sent', '', '2026-03-27 19:06:49', '2026-03-27 18:06:49'),
(89, 177, 'shane3', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2027-2026', 'failed', '', NULL, '2026-03-27 18:07:41'),
(90, 177, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2027-2026', 'sent', '', '2026-03-27 19:07:46', '2026-03-27 18:07:46'),
(91, 177, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0003)', 'failed', NULL, NULL, '2026-03-27 18:07:48'),
(92, 177, 'shane3', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2027-2026', 'failed', '', NULL, '2026-03-27 18:07:48'),
(93, 177, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2027-2026', 'sent', '', '2026-03-27 19:07:53', '2026-03-27 18:07:53'),
(94, 175, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,500.00)', 'pending', NULL, NULL, '2026-03-27 18:12:16'),
(95, 175, 'shane1', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2027-2026', 'failed', '', NULL, '2026-03-27 18:12:16'),
(96, 175, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — Shane Carlo Nodado | 2nd Semester, AY 2027-2026', 'sent', '', '2026-03-27 19:12:21', '2026-03-27 18:12:21'),
(97, 177, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-27 18:22:03'),
(98, 177, 'shane3', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2028-2029', 'failed', '', NULL, '2026-03-27 18:22:03'),
(99, 177, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2028-2029', 'sent', '', '2026-03-27 19:22:08', '2026-03-27 18:22:08'),
(100, 178, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-03-27 18:24:47'),
(101, 178, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 18:24:47'),
(102, 178, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-27 19:24:52', '2026-03-27 18:24:52'),
(103, 178, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 18:25:04'),
(104, 178, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-27 19:25:08', '2026-03-27 18:25:08'),
(105, 178, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-27 18:25:10'),
(106, 178, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 18:25:10'),
(107, 178, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-27 19:25:14', '2026-03-27 18:25:14'),
(108, 178, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-27 18:27:25'),
(109, 178, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-27 18:27:25'),
(110, 178, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-27 19:27:30', '2026-03-27 18:27:30'),
(111, 179, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-28 06:05:43'),
(112, 179, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 06:05:43'),
(113, 179, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-28 07:05:48', '2026-03-28 06:05:48'),
(114, 179, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 06:24:49'),
(115, 179, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-28 07:24:53', '2026-03-28 06:24:53'),
(116, 179, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 06:24:55'),
(117, 179, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 06:24:56'),
(118, 179, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-28 07:25:01', '2026-03-28 06:25:01'),
(119, 179, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-28 06:31:41'),
(120, 179, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-28 06:31:41'),
(121, 179, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-28 07:31:46', '2026-03-28 06:31:46'),
(122, 180, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-03-28 06:48:01'),
(123, 180, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-28 06:48:01'),
(124, 180, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-28 07:48:06', '2026-03-28 06:48:06'),
(125, 180, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-28 06:48:20'),
(126, 180, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-28 07:48:24', '2026-03-28 06:48:24'),
(127, 180, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 06:48:27'),
(128, 180, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-28 06:48:27'),
(129, 180, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-28 07:48:31', '2026-03-28 06:48:31'),
(130, 180, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-28 06:51:26'),
(131, 180, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-28 06:51:26'),
(132, 180, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-28 07:51:31', '2026-03-28 06:51:31'),
(133, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-28 07:07:34'),
(134, 181, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-28 07:07:34'),
(135, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-28 08:07:39', '2026-03-28 07:07:39'),
(136, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-28 07:07:51'),
(137, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-28 08:07:57', '2026-03-28 07:07:57'),
(138, 181, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 07:07:58'),
(139, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-28 07:07:58'),
(140, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-28 08:08:03', '2026-03-28 07:08:03'),
(141, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-28 07:09:32'),
(142, 181, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-28 07:09:32'),
(143, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-28 08:09:37', '2026-03-28 07:09:37'),
(144, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-28 07:09:53'),
(145, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-28 08:09:57', '2026-03-28 07:09:57'),
(146, 181, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 07:09:59'),
(147, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-28 07:10:00'),
(148, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-28 08:10:10', '2026-03-28 07:10:10'),
(149, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱27,109.00)', 'pending', NULL, NULL, '2026-03-28 07:20:34'),
(150, 181, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-28 07:20:34'),
(151, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2027-2028', 'sent', '', '2026-03-28 08:20:40', '2026-03-28 07:20:40'),
(152, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-28 07:21:17'),
(153, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2027-2028', 'sent', '', '2026-03-28 08:21:22', '2026-03-28 07:21:22'),
(154, 181, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 07:21:24'),
(155, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-28 07:21:25'),
(156, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2027-2028', 'sent', '', '2026-03-28 08:21:30', '2026-03-28 07:21:30'),
(157, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱29,961.00)', 'pending', NULL, NULL, '2026-03-28 07:39:13'),
(158, 181, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2028-2029', 'failed', '', NULL, '2026-03-28 07:39:13'),
(159, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2028-2029', 'sent', '', '2026-03-28 08:39:18', '2026-03-28 07:39:18'),
(160, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2028-2029', 'failed', '', NULL, '2026-03-28 07:39:32'),
(161, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2028-2029', 'sent', '', '2026-03-28 08:39:37', '2026-03-28 07:39:37'),
(162, 181, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 07:39:39'),
(163, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2028-2029', 'failed', '', NULL, '2026-03-28 07:39:39'),
(164, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2028-2029', 'sent', '', '2026-03-28 08:39:47', '2026-03-28 07:39:47'),
(165, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱27,822.00)', 'pending', NULL, NULL, '2026-03-28 07:47:33'),
(166, 181, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2028-2029', 'failed', '', NULL, '2026-03-28 07:47:33'),
(167, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 2nd Semester, AY 2028-2029', 'sent', '', '2026-03-28 08:47:38', '2026-03-28 07:47:38'),
(168, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2028-2029', 'failed', '', NULL, '2026-03-28 07:54:03'),
(169, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2028-2029', 'sent', '', '2026-03-28 08:54:08', '2026-03-28 07:54:08'),
(170, 181, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 07:54:10'),
(171, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2028-2029', 'failed', '', NULL, '2026-03-28 07:54:10'),
(172, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 2nd Semester, AY 2028-2029', 'sent', '', '2026-03-28 08:54:14', '2026-03-28 07:54:14'),
(173, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Payment Verified – Full (₱25,683.00)', 'pending', NULL, NULL, '2026-03-28 15:17:20'),
(174, 181, 'shane1', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 15:17:20'),
(175, 181, 'dmpaccnt.1991@gmail.com', 'soa', 'Statement of Account — Dump Account2 | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 16:17:27', '2026-03-28 15:17:27'),
(176, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 15:18:11'),
(177, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 16:18:16', '2026-03-28 15:18:16'),
(178, 181, 'dmpaccnt.1991@gmail.com', '', 'Enrollment Confirmed – Dump Account2 (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 15:18:18'),
(179, 181, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 15:18:18'),
(180, 181, 'dmpaccnt.1991@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dump Account2 | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 16:18:23', '2026-03-28 15:18:23'),
(181, 182, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,034.25)', 'pending', NULL, NULL, '2026-03-28 18:34:20'),
(182, 182, 'shane2', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 18:34:20'),
(183, 182, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 19:34:27', '2026-03-28 18:34:27'),
(184, 182, 'shane2', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 18:35:00'),
(185, 182, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 19:35:05', '2026-03-28 18:35:05'),
(186, 182, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-28 18:35:07'),
(187, 182, 'shane2', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 18:35:07'),
(188, 182, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 19:35:12', '2026-03-28 18:35:12'),
(189, 183, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,035.00)', 'pending', NULL, NULL, '2026-03-28 18:42:37'),
(190, 183, 'shane3', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 18:42:37'),
(191, 183, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 19:42:43', '2026-03-28 18:42:43'),
(192, 183, 'shane3', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 18:42:56'),
(193, 183, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 19:43:01', '2026-03-28 18:43:01'),
(194, 183, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0003)', 'failed', NULL, NULL, '2026-03-28 18:43:03'),
(195, 183, 'shane3', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 18:43:03'),
(196, 183, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 19:43:10', '2026-03-28 18:43:10'),
(197, 184, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱9,000.00)', 'pending', NULL, NULL, '2026-03-28 19:21:28'),
(198, 184, 'shane4', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:21:28'),
(199, 184, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:21:35', '2026-03-28 19:21:35'),
(200, 184, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:22:00'),
(201, 184, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:22:06', '2026-03-28 19:22:06'),
(202, 184, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0004)', 'failed', NULL, NULL, '2026-03-28 19:22:07'),
(203, 184, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:22:07'),
(204, 184, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:22:12', '2026-03-28 19:22:12'),
(205, 185, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-03-28 19:32:40'),
(206, 185, 'shane4', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:32:40'),
(207, 185, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:32:47', '2026-03-28 19:32:47'),
(208, 185, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:33:08'),
(209, 185, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:33:13', '2026-03-28 19:33:13'),
(210, 185, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 19:33:15'),
(211, 185, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:33:15'),
(212, 185, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:33:20', '2026-03-28 19:33:20'),
(213, 185, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,499.50)', 'pending', NULL, NULL, '2026-03-28 19:38:06'),
(214, 185, 'shane4', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:38:06'),
(215, 185, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:38:11', '2026-03-28 19:38:11'),
(216, 185, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:38:24'),
(217, 185, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:38:29', '2026-03-28 19:38:29'),
(218, 185, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 19:38:31'),
(219, 185, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2029-2030', 'failed', '', NULL, '2026-03-28 19:38:31'),
(220, 185, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2029-2030', 'sent', '', '2026-03-28 20:38:37', '2026-03-28 19:38:37'),
(221, 187, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-03-28 20:35:53'),
(222, 187, 'shane', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 20:35:54'),
(223, 187, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-28 21:35:59', '2026-03-28 20:35:59'),
(224, 187, 'shane', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 20:36:02'),
(225, 187, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-28 21:36:07', '2026-03-28 20:36:07'),
(226, 187, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-28 20:36:09'),
(227, 187, 'shane', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 20:36:09'),
(228, 187, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-28 21:36:14', '2026-03-28 20:36:14'),
(229, 188, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-03-28 20:58:36'),
(230, 188, 'shane1', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 20:58:36'),
(231, 188, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-28 21:58:41', '2026-03-28 20:58:41'),
(232, 188, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 20:58:53'),
(233, 188, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-28 21:58:58', '2026-03-28 20:58:58'),
(234, 188, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-28 20:59:00'),
(235, 188, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 20:59:01'),
(236, 188, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2025-2026', 'sent', '', '2026-03-28 21:59:06', '2026-03-28 20:59:06'),
(237, 188, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,499.50)', 'pending', NULL, NULL, '2026-03-28 21:12:52'),
(238, 188, 'shane1', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 21:12:52'),
(239, 188, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-28 22:12:57', '2026-03-28 21:12:57'),
(240, 188, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 21:13:38'),
(241, 188, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-28 21:13:45'),
(242, 188, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-28 22:13:45', '2026-03-28 21:13:45'),
(243, 188, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-28 21:13:45'),
(244, 188, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-28 22:13:51', '2026-03-28 21:13:51'),
(245, 188, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-29 09:01:41'),
(246, 188, 'shane1', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-29 09:01:43'),
(247, 188, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-29 11:01:48', '2026-03-29 09:01:48'),
(248, 189, 'cuevasdave01@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,499.50)', 'pending', NULL, NULL, '2026-03-29 09:05:59'),
(249, 189, 'shane1', 'soa', 'Statement of Account — Dave Cuevas | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-29 09:05:59'),
(250, 189, 'cuevasdave01@gmail.com', 'soa', 'Statement of Account — Dave Cuevas | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-29 11:06:04', '2026-03-29 09:06:04'),
(251, 189, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-29 09:09:24'),
(252, 189, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-29 11:09:29', '2026-03-29 09:09:29'),
(253, 189, 'cuevasdave01@gmail.com', '', 'Enrollment Confirmed – Dave Cuevas (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-29 09:09:31'),
(254, 189, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-29 09:09:31'),
(255, 189, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-29 11:09:36', '2026-03-29 09:09:36'),
(256, 189, 'cuevasdave01@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-29 09:37:00'),
(257, 189, 'shane1', 'soa', 'Statement of Account — Dave Cuevas | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 09:37:01'),
(258, 189, 'cuevasdave01@gmail.com', 'soa', 'Statement of Account — Dave Cuevas | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-29 11:37:07', '2026-03-29 09:37:07'),
(259, 189, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 09:48:18'),
(260, 189, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-29 11:48:23', '2026-03-29 09:48:23'),
(261, 189, 'cuevasdave01@gmail.com', '', 'Enrollment Confirmed – Dave Cuevas (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-29 09:48:25'),
(262, 189, 'shane1', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 09:48:25'),
(263, 189, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-29 11:48:30', '2026-03-29 09:48:30'),
(264, 190, 'cuevasdave01@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-03-29 15:12:39'),
(265, 190, 'shane2', 'soa', 'Statement of Account — Dave Cuevas | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 15:12:39'),
(266, 190, 'cuevasdave01@gmail.com', 'soa', 'Statement of Account — Dave Cuevas | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-29 17:12:45', '2026-03-29 15:12:45'),
(267, 190, 'shane2', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 15:13:02'),
(268, 190, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-29 17:13:08', '2026-03-29 15:13:08'),
(269, 190, 'cuevasdave01@gmail.com', '', 'Enrollment Confirmed – Dave Cuevas (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-29 15:13:10'),
(270, 190, 'shane2', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 15:13:10'),
(271, 190, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-29 17:13:15', '2026-03-29 15:13:15'),
(272, 190, 'cuevasdave01@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,499.50)', 'pending', NULL, NULL, '2026-03-29 15:32:57'),
(273, 190, 'shane2', 'soa', 'Statement of Account — Dave Cuevas | 2nd Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 15:32:57'),
(274, 190, 'cuevasdave01@gmail.com', 'soa', 'Statement of Account — Dave Cuevas | 2nd Semester, AY 2027-2028', 'sent', '', '2026-03-29 17:33:02', '2026-03-29 15:33:02'),
(275, 190, 'shane2', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 15:33:50'),
(276, 190, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2027-2028', 'sent', '', '2026-03-29 17:33:55', '2026-03-29 15:33:55'),
(277, 190, 'cuevasdave01@gmail.com', '', 'Enrollment Confirmed – Dave Cuevas (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-29 15:33:57'),
(278, 190, 'shane2', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 15:33:57'),
(279, 190, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2027-2028', 'sent', '', '2026-03-29 17:34:02', '2026-03-29 15:34:02'),
(280, 190, 'shane2', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 15:39:19'),
(281, 190, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2027-2028', 'sent', '', '2026-03-29 17:39:24', '2026-03-29 15:39:24'),
(282, 190, 'cuevasdave01@gmail.com', '', 'Enrollment Confirmed – Dave Cuevas (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-29 15:39:26'),
(283, 190, 'shane2', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-29 15:39:26'),
(284, 190, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2027-2028', 'sent', '', '2026-03-29 17:39:32', '2026-03-29 15:39:32'),
(285, 191, 'cuevasdave01@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,500.00)', 'pending', NULL, NULL, '2026-03-29 17:14:50'),
(286, 191, 'shane3', 'soa', 'Statement of Account — Dave Cuevas | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-29 17:14:50'),
(287, 191, 'cuevasdave01@gmail.com', 'soa', 'Statement of Account — Dave Cuevas | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-29 19:14:56', '2026-03-29 17:14:56'),
(288, 191, 'shane3', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-29 17:15:11'),
(289, 191, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-29 19:15:16', '2026-03-29 17:15:16'),
(290, 191, 'cuevasdave01@gmail.com', '', 'Enrollment Confirmed – Dave Cuevas (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-29 17:15:18'),
(291, 191, 'shane3', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2025-2026', 'failed', '', NULL, '2026-03-29 17:15:18'),
(292, 191, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 2nd Semester, AY 2025-2026', 'sent', '', '2026-03-29 19:15:23', '2026-03-29 17:15:23'),
(293, 191, 'cuevasdave01@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-03-29 17:19:39'),
(294, 191, 'shane3', 'soa', 'Statement of Account — Dave Cuevas | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-29 17:19:39'),
(295, 191, 'cuevasdave01@gmail.com', 'soa', 'Statement of Account — Dave Cuevas | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-29 19:19:44', '2026-03-29 17:19:44'),
(296, 191, 'shane3', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-29 17:19:50'),
(297, 191, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-29 19:19:55', '2026-03-29 17:19:55'),
(298, 191, 'cuevasdave01@gmail.com', '', 'Enrollment Confirmed – Dave Cuevas (STU-2026-0001)', 'failed', NULL, NULL, '2026-03-29 17:19:57'),
(299, 191, 'shane3', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-29 17:19:57'),
(300, 191, 'cuevasdave01@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-29 19:20:02', '2026-03-29 17:20:02'),
(301, 192, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-03-30 10:17:05'),
(302, 192, 'shane4', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-30 10:17:07'),
(303, 192, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-30 12:17:14', '2026-03-30 10:17:14'),
(304, 192, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-30 10:18:00'),
(305, 192, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-30 12:18:05', '2026-03-30 10:18:05'),
(306, 192, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-30 10:18:08'),
(307, 192, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-30 10:18:08'),
(308, 192, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2026-2027', 'sent', '', '2026-03-30 12:18:13', '2026-03-30 10:18:13'),
(309, 192, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,499.50)', 'pending', NULL, NULL, '2026-03-30 10:26:11'),
(310, 192, 'shane4', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-30 10:26:11'),
(311, 192, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-30 12:26:16', '2026-03-30 10:26:16');
INSERT INTO `email_notifications` (`id`, `student_id`, `recipient`, `type`, `subject`, `status`, `error_message`, `sent_at`, `created_at`) VALUES
(312, 192, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-30 10:50:57'),
(313, 192, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-30 12:51:02', '2026-03-30 10:51:02'),
(314, 192, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-30 10:51:03'),
(315, 192, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2026-2027', 'failed', '', NULL, '2026-03-30 10:51:03'),
(316, 192, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2026-2027', 'sent', '', '2026-03-30 12:51:09', '2026-03-30 10:51:09'),
(317, 192, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Prelim (₱7,500.00)', 'pending', NULL, NULL, '2026-03-30 11:37:43'),
(318, 192, 'shane4', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-30 11:37:43'),
(319, 192, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-30 13:37:48', '2026-03-30 11:37:48'),
(320, 192, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,500.00)', 'pending', NULL, NULL, '2026-03-30 11:38:05'),
(321, 192, 'shane4', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-30 11:38:05'),
(322, 192, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-30 13:38:10', '2026-03-30 11:38:10'),
(323, 192, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-30 12:10:12'),
(324, 192, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-30 14:10:18', '2026-03-30 12:10:18'),
(325, 192, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0002)', 'failed', NULL, NULL, '2026-03-30 12:10:19'),
(326, 192, 'shane4', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-30 12:10:19'),
(327, 192, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-30 14:10:24', '2026-03-30 12:10:24'),
(328, 194, 'lharrianebinoya5@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-03-31 07:47:26'),
(329, 194, 'lhar1', 'soa', 'Statement of Account — Lharriane Binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-31 07:47:27'),
(330, 194, 'lharrianebinoya5@gmail.com', 'soa', 'Statement of Account — Lharriane Binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-31 09:47:33', '2026-03-31 07:47:33'),
(331, 194, 'lhar1', 'enrollment_report', 'Enrollment Confirmation — Lharriane Binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-31 08:06:50'),
(332, 194, 'lharrianebinoya5@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Lharriane Binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-31 10:06:56', '2026-03-31 08:06:56'),
(333, 194, 'lharrianebinoya5@gmail.com', '', 'Enrollment Confirmed – Lharriane Binoya (STU-2026-0004)', 'failed', NULL, NULL, '2026-03-31 08:06:57'),
(334, 194, 'lhar1', 'enrollment_report', 'Enrollment Confirmation — Lharriane Binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-31 08:06:58'),
(335, 194, 'lharrianebinoya5@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Lharriane Binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-31 10:07:03', '2026-03-31 08:07:03'),
(336, 192, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Midterm (₱8,000.00)', 'pending', NULL, NULL, '2026-03-31 08:41:56'),
(337, 192, 'shane4', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-03-31 08:41:57'),
(338, 192, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2027-2028', 'sent', '', '2026-03-31 10:42:02', '2026-03-31 08:42:02'),
(339, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,034.25)', 'pending', NULL, NULL, '2026-04-01 07:45:13'),
(340, 195, 'shane', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-01 07:45:14'),
(341, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-01 09:45:20', '2026-04-01 07:45:20'),
(342, 195, 'shane', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-01 07:46:00'),
(343, 195, 'anamariebinoya0909@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-01 09:46:04', '2026-04-01 07:46:04'),
(344, 195, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-01 07:46:06'),
(345, 195, 'shane', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-01 07:46:06'),
(346, 195, 'anamariebinoya0909@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-01 09:46:12', '2026-04-01 07:46:12'),
(347, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Prelim (₱10,000.00)', 'pending', NULL, NULL, '2026-04-01 07:48:49'),
(348, 195, 'shane', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-01 07:48:49'),
(349, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-01 09:48:54', '2026-04-01 07:48:54'),
(350, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Midterm (₱8,000.00)', 'pending', NULL, NULL, '2026-04-01 07:51:47'),
(351, 195, 'shane', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-01 07:51:47'),
(352, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-01 09:51:51', '2026-04-01 07:51:51'),
(353, 196, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,034.25)', 'pending', NULL, NULL, '2026-04-03 07:20:56'),
(354, 196, 'shane1', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-04-03 07:20:58'),
(355, 196, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2026-2027', 'sent', '', '2026-04-03 09:21:03', '2026-04-03 07:21:03'),
(356, 196, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0002)', 'failed', NULL, NULL, '2026-04-03 07:21:16'),
(357, 197, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-04-03 07:25:21'),
(358, 197, 'shane2', 'soa', 'Statement of Account — Ana Binoya1 | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-04-03 07:25:21'),
(359, 197, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya1 | 1st Semester, AY 2026-2027', 'sent', '', '2026-04-03 09:25:26', '2026-04-03 07:25:26'),
(360, 197, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya1 (STU-2026-0003)', 'failed', NULL, NULL, '2026-04-03 07:26:44'),
(361, 197, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya1 (STU-2026-0003)', 'failed', NULL, NULL, '2026-04-03 07:36:29'),
(362, 198, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,034.25)', 'pending', NULL, NULL, '2026-04-03 07:38:21'),
(363, 198, 'shane3', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-04-03 07:38:21'),
(364, 198, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2026-2027', 'sent', '', '2026-04-03 09:38:26', '2026-04-03 07:38:26'),
(365, 198, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0004)', 'failed', NULL, NULL, '2026-04-03 07:39:30'),
(366, 199, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-04-03 07:43:45'),
(367, 199, 'shane4', 'soa', 'Statement of Account — Ana2 Binoya2 | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-04-03 07:43:45'),
(368, 199, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana2 Binoya2 | 1st Semester, AY 2026-2027', 'sent', '', '2026-04-03 09:43:50', '2026-04-03 07:43:50'),
(369, 199, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana2 Binoya2 (STU-2026-0005)', 'failed', NULL, NULL, '2026-04-03 07:44:11'),
(370, 199, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana2 Binoya2 (STU-2026-0005)', 'failed', NULL, NULL, '2026-04-03 07:45:11'),
(371, 200, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-04-03 07:48:51'),
(372, 200, 'shane5', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-04-03 07:48:51'),
(373, 200, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st Semester, AY 2026-2027', 'sent', '', '2026-04-03 09:48:57', '2026-04-03 07:48:57'),
(374, 200, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0006)', 'failed', NULL, NULL, '2026-04-03 07:49:07'),
(375, 200, 'shane5', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 1st Semester, AY 2026-2027', 'failed', '', NULL, '2026-04-03 07:49:11'),
(376, 200, 'anamariebinoya0909@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 1st Semester, AY 2026-2027', 'sent', '', '2026-04-03 09:49:16', '2026-04-03 07:49:16'),
(377, 200, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,499.50)', 'pending', NULL, NULL, '2026-04-03 09:11:51'),
(378, 200, 'shane5', 'soa', 'Statement of Account — Ana Binoya | 2nd Semester AY 2026-2027', 'failed', '', NULL, '2026-04-03 09:11:51'),
(379, 200, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 2nd Semester AY 2026-2027', 'sent', '', '2026-04-03 11:11:56', '2026-04-03 09:11:56'),
(380, 200, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0006)', 'failed', NULL, NULL, '2026-04-03 09:12:36'),
(381, 200, 'shane5', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester AY 2026-2027', 'failed', '', NULL, '2026-04-03 09:12:39'),
(382, 200, 'anamariebinoya0909@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester AY 2026-2027', 'sent', '', '2026-04-03 11:12:44', '2026-04-03 09:12:44'),
(383, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Finals (₱29,998.00)', 'pending', NULL, NULL, '2026-04-03 10:12:10'),
(384, 195, 'shane', 'soa', 'Statement of Account — Ana Binoya | 2nd Semester AY 2026-2027', 'failed', '', NULL, '2026-04-03 10:12:11'),
(385, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 2nd Semester AY 2026-2027', 'sent', '', '2026-04-03 12:12:16', '2026-04-03 10:12:16'),
(386, 195, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-03 10:12:44'),
(387, 195, 'shane', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester AY 2026-2027', 'failed', '', NULL, '2026-04-03 10:12:47'),
(388, 195, 'anamariebinoya0909@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 2nd Semester AY 2026-2027', 'sent', '', '2026-04-03 12:12:52', '2026-04-03 10:12:52'),
(389, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,500.00)', 'pending', NULL, NULL, '2026-04-03 10:20:51'),
(390, 195, 'shane', 'soa', 'Statement of Account — Ana Binoya | 1st Semester AY 2027-2028', 'failed', '', NULL, '2026-04-03 10:20:51'),
(391, 195, 'anamariebinoya0909@gmail.com', 'soa', 'Statement of Account — Ana Binoya | 1st Semester AY 2027-2028', 'sent', '', '2026-04-03 12:20:56', '2026-04-03 10:20:56'),
(392, 195, 'anamariebinoya0909@gmail.com', '', 'Enrollment Confirmed – Ana Binoya (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-03 10:22:21'),
(393, 195, 'shane', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 1st Semester AY 2027-2028', 'failed', '', NULL, '2026-04-03 10:22:24'),
(394, 195, 'anamariebinoya0909@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Ana Binoya | 1st Semester AY 2027-2028', 'sent', '', '2026-04-03 12:22:29', '2026-04-03 10:22:29'),
(395, 201, 'shanecarlobinoya@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,035.00)', 'pending', NULL, NULL, '2026-04-03 13:28:53'),
(396, 201, 'shane22', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-03 13:28:53'),
(397, 201, 'shanecarlobinoya@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-03 15:28:58', '2026-04-03 13:28:58'),
(398, 202, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,035.00)', 'pending', NULL, NULL, '2026-04-03 14:10:24'),
(399, 202, 'shane33', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-03 14:10:24'),
(400, 202, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-03 16:10:29', '2026-04-03 14:10:29'),
(401, 202, 'nodadoshanecarlo@gmail.com', '', 'Enrollment Confirmed – shane binoya (STU-2026-0008)', 'failed', NULL, NULL, '2026-04-03 14:16:43'),
(402, 202, 'shane33', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-03 14:16:46'),
(403, 202, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-03 16:16:51', '2026-04-03 14:16:51'),
(404, 202, 'nodadoshanecarlo@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-04-03 14:17:12'),
(405, 202, 'shane33', 'soa', 'Statement of Account — shane binoya | 1st Semester AY 2027-2028', 'failed', '', NULL, '2026-04-03 14:17:13'),
(406, 202, 'nodadoshanecarlo@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester AY 2027-2028', 'sent', '', '2026-04-03 16:17:18', '2026-04-03 14:17:18'),
(407, 202, 'nodadoshanecarlo@gmail.com', '', 'Enrollment Confirmed – shane binoya (STU-2026-0008)', 'failed', NULL, NULL, '2026-04-03 14:17:32'),
(408, 202, 'shane33', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-03 14:17:35'),
(409, 202, 'nodadoshanecarlo@gmail.com', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-03 16:17:40', '2026-04-03 14:17:40'),
(410, 203, 'shanecarlobinoya@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,034.25)', 'pending', NULL, NULL, '2026-04-03 14:18:50'),
(411, 203, 'shane333', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-03 14:18:51'),
(412, 203, 'shanecarlobinoya@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-03 16:18:56', '2026-04-03 14:18:56'),
(413, 203, 'shanecarlobinoya@gmail.com', '', 'Enrollment Confirmed – shane binoya (STU-2026-0009)', 'failed', NULL, NULL, '2026-04-03 14:19:10'),
(414, 203, 'shane333', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-03 14:19:14'),
(415, 203, 'shanecarlobinoya@gmail.com', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-03 16:19:20', '2026-04-03 14:19:20'),
(416, 204, 'shanecarlobinoya@gmail.com', 'soa', 'Payment Verified – Downpayment (₱8,035.00)', 'pending', NULL, NULL, '2026-04-03 14:22:45'),
(417, 204, 'shane333', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-03 14:22:45'),
(418, 204, 'shanecarlobinoya@gmail.com', 'soa', 'Statement of Account — shane binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-03 16:22:50', '2026-04-03 14:22:50'),
(419, 204, 'shanecarlobinoya@gmail.com', '', 'Enrollment Confirmed – shane binoya (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-03 14:22:59'),
(420, 204, 'shane333', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2027-2028', 'failed', '', NULL, '2026-04-03 14:23:02'),
(421, 204, 'shanecarlobinoya@gmail.com', 'enrollment_report', 'Enrollment Confirmation — shane binoya | 1st Semester, AY 2027-2028', 'sent', '', '2026-04-03 16:23:07', '2026-04-03 14:23:07');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrollment_date` date DEFAULT NULL,
  `status` enum('Pending','Enrolled','Completed','Dropped','Failed') DEFAULT 'Pending',
  `semester` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remarks` varchar(20) DEFAULT 'In Progress',
  `grade_released` tinyint(1) DEFAULT 0,
  `grade_submitted` tinyint(1) DEFAULT 0,
  `grade_submitted_at` datetime DEFAULT NULL,
  `prelim_grade` decimal(4,2) DEFAULT NULL,
  `midterm_grade` decimal(4,2) DEFAULT NULL,
  `final_grade` decimal(4,2) DEFAULT NULL,
  `overall_grade` decimal(4,2) DEFAULT NULL,
  `grade_released_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `enrollment_date`, `status`, `semester`, `notes`, `created_at`, `updated_at`, `remarks`, `grade_released`, `grade_submitted`, `grade_submitted_at`, `prelim_grade`, `midterm_grade`, `final_grade`, `overall_grade`, `grade_released_at`) VALUES
(1381, 204, 1, '2026-04-03', 'Enrolled', '1st Semester, AY 2027-2028', 'Auto-enrolled', '2026-04-03 14:22:56', '2026-04-03 14:22:56', 'In Progress', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(1382, 204, 2, '2026-04-03', 'Enrolled', '1st Semester, AY 2027-2028', 'Auto-enrolled', '2026-04-03 14:22:56', '2026-04-03 14:22:56', 'In Progress', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(1383, 204, 3, '2026-04-03', 'Enrolled', '1st Semester, AY 2027-2028', 'Auto-enrolled', '2026-04-03 14:22:56', '2026-04-03 14:22:56', 'In Progress', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(1384, 204, 4, '2026-04-03', 'Enrolled', '1st Semester, AY 2027-2028', 'Auto-enrolled', '2026-04-03 14:22:56', '2026-04-03 14:22:56', 'In Progress', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(1385, 204, 5, '2026-04-03', 'Enrolled', '1st Semester, AY 2027-2028', 'Auto-enrolled', '2026-04-03 14:22:56', '2026-04-03 14:22:56', 'In Progress', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(1386, 204, 6, '2026-04-03', 'Enrolled', '1st Semester, AY 2027-2028', 'Auto-enrolled', '2026-04-03 14:22:56', '2026-04-03 14:22:56', 'In Progress', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(1387, 204, 7, '2026-04-03', 'Enrolled', '1st Semester, AY 2027-2028', 'Auto-enrolled', '2026-04-03 14:22:56', '2026-04-03 14:22:56', 'In Progress', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(1388, 204, 8, '2026-04-03', 'Enrolled', '1st Semester, AY 2027-2028', 'Auto-enrolled', '2026-04-03 14:22:56', '2026-04-03 14:22:56', 'In Progress', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_snapshots`
--

CREATE TABLE `enrollment_snapshots` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `year_level` varchar(30) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `snapshot` longtext NOT NULL COMMENT 'JSON array of enrolled courses at snapshot time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `permit_identifier` varchar(60) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `faculty_id` varchar(20) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(150) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `specialty` varchar(150) DEFAULT NULL,
  `subjects` longtext DEFAULT '[]' COMMENT 'JSON array of subject names',
  `status` enum('Active','Inactive','On Leave') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `program_levels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`program_levels`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`id`, `user_id`, `faculty_id`, `first_name`, `last_name`, `email`, `department`, `specialty`, `subjects`, `status`, `created_at`, `updated_at`, `program_levels`) VALUES
(1, 136, 'FAC-2024-001', 'Maria', 'Santos', 'maria.santos@school.edu', 'Information Technology', 'Web Development, It Specialist', '[]', 'Active', '2026-02-01 00:03:41', '2026-03-28 19:23:24', NULL),
(2, 137, 'FAC-2024-002', 'Juan', 'Reyes', 'juan.reyes@school.edu', 'Information Technology', 'Database Systems', '[\"IT-CMT015-IT\"]', 'Active', '2026-02-01 00:03:41', '2026-03-18 19:55:39', NULL),
(3, 138, 'FAC-2024-003', 'Anna', 'Garcia', 'anna.garcia@school.edu', 'Mathematics', 'Discrete Mathematics', '[\"GE105-IT\",\"CC102\",\"PE2\"]', 'Active', '2026-02-01 00:03:41', '2026-03-28 18:53:10', NULL),
(4, 139, 'FAC-2024-004', 'Luis', 'Rodriguez', 'luis.rodriguez@school.edu', 'Information Technology', 'Software Engineering', '[\"PE1-IT\",\"NSTP1-IT\"]', 'Active', '2026-02-01 00:03:41', '2026-03-18 19:55:39', NULL),
(5, 140, 'FAC-2024-005', 'Sarah', 'Kim', 'sarah.kim@school.edu', 'English', 'Technical Writing', '[\"GE100-IT\"]', 'Active', '2026-02-01 00:03:41', '2026-03-18 19:55:39', NULL),
(9, 141, 'FAC-2024-007', 'Liza', 'Dela Cruz', 'liza.delacruz@school.edu', 'Information Technology', 'Systems and Architecture', '[\"CC102\",\"GE101\",\"GE103\",\"IS103\",\"IT100\",\"NSTP2\"]', 'Active', '2026-03-04 12:01:21', '2026-03-28 18:41:09', NULL),
(10, 142, 'FAC-2024-008', 'Ramon', 'Villanueva', 'ramon.villanueva@school.edu', 'Information Technology', 'Capstone and Practicum', '[\"CAP501-IT\",\"OJT-BSIT\",\"ELEC401\",\"ELEC403\",\"ELEC103\",\"DM101\"]', 'Active', '2026-03-04 12:01:21', '2026-03-18 19:55:39', NULL),
(11, 143, 'FAC-2024-006', 'Carlo', 'Mendoza', 'carlo.mendoza@school.edu', 'Information Technology', 'Accounting and Finance', '[\"CC101\",\"GE105\",\"GE108\",\"GE109\",\"IT-CMT\",\"NSTP1\",\"PE1\",\"CC100\"]', 'Active', '2026-03-04 12:06:34', '2026-03-28 18:38:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `faculty_subjects`
--

CREATE TABLE `faculty_subjects` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL COMMENT 'FK to courses if matched',
  `course_code` varchar(30) NOT NULL COMMENT 'Original code from JSON'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty_subjects`
--

INSERT INTO `faculty_subjects` (`id`, `faculty_id`, `course_id`, `course_code`) VALUES
(1, 1, NULL, 'CC100-IT'),
(2, 1, NULL, 'CC101-IT'),
(3, 2, NULL, 'IT-CMT015-IT'),
(4, 3, NULL, 'GE105-IT'),
(5, 4, NULL, 'PE1-IT'),
(6, 4, NULL, 'NSTP1-IT'),
(7, 5, NULL, 'GE100-IT'),
(8, 9, 37, 'IT110'),
(9, 9, 29, 'IT104'),
(10, 9, NULL, 'IT-CMT015-IT'),
(11, 9, NULL, 'EMC203-IT'),
(12, 9, 34, 'EMC207'),
(13, 9, 3, 'CC100'),
(14, 10, NULL, 'CAP501-IT'),
(15, 10, 46, 'OJT-BSIT'),
(16, 10, 32, 'ELEC401'),
(17, 10, 44, 'ELEC403'),
(18, 10, 38, 'ELEC103'),
(19, 10, 43, 'DM101'),
(20, 11, 552, 'AEC111'),
(21, 11, 553, 'AEC109');

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
(4, 'College', 'lab_fee_per_room', 'Laboratory Fee (per lab room)', 1900.0000, 1, 'All', 'Per laboratory room on St. Benilde', 1, 4, '2026-03-04 01:23:12', '2026-03-04 23:09:12'),
(5, 'College', 'energy_rate_per_unit', 'Energy Fee (per unit)', 63.0000, 1, 'All', 'Units × ₱21 × 3 terms = ₱63/unit', 1, 5, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(6, 'College', 'installment_fee', 'Installment Surcharge', 750.0000, 0, 'All', 'Added when payment plan is installment', 1, 6, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(7, 'SHS', 'transferee_flat_rate', 'Transferee Flat Rate', 20000.0000, 0, 'Transferee', 'Flat fee for SHS transferees', 1, 1, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(8, 'SHS', 'installment_fee', 'Installment Surcharge', 750.0000, 0, 'All', 'Added when payment plan is installment', 1, 2, '2026-03-04 01:23:12', '2026-03-04 01:23:12'),
(9, 'TVET', 'misc_fee', 'Miscellaneous Fee', 0.0000, 1, 'All', 'Fixed miscellaneous fee for TVET', 1, 1, '2026-03-04 01:23:12', '2026-03-04 03:10:19'),
(10, 'TVET', 'reg_fee', 'Registration Fee', 0.0000, 1, 'All', 'Fixed registration fee for TVET', 1, 2, '2026-03-04 01:23:12', '2026-03-04 03:10:47'),
(11, 'TVET', 'installment_fee', 'Installment Surcharge', 750.0000, 1, 'All', 'Added when payment plan is installment', 1, 3, '2026-03-04 01:23:12', '2026-03-04 03:11:00'),
(12, 'TVET', 'transferee_flat_rate', 'Transferee Flat Rate', 20000.0000, 0, 'Transferee', 'Flat fee for TVET transferees', 1, 4, '2026-03-04 01:23:12', '2026-03-04 01:23:12');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `receipt_token` varchar(64) DEFAULT NULL,
  `receipt_signed_at` datetime DEFAULT NULL,
  `semester` varchar(100) DEFAULT NULL COMMENT 'Semester this payment belongs to'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `installment_payments`
--

INSERT INTO `installment_payments` (`id`, `student_id`, `payment_log_id`, `or_ar_number`, `or_ar_type`, `amount`, `payment_date`, `payment_method`, `gcash_reference`, `exam_period`, `notes`, `recorded_by`, `created_at`, `receipt_token`, `receipt_signed_at`, `semester`) VALUES
(177, 204, 234, 'AR-20260117', 'AR', 8035.00, '2026-04-03', 'GCash', '12345', 'Downpayment', '', 3, '2026-04-03 14:22:44', NULL, NULL, '1st Semester, AY 2027-2028');

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
(42, 'student2', '::1', '2026-03-03 23:57:07'),
(176, 'shane3@gmail.com', '::1', '2026-03-20 19:01:17'),
(385, 'maria@edu.com', '::1', '2026-04-01 15:07:03'),
(386, 'maria@edu.com', '::1', '2026-04-01 15:07:22');

-- --------------------------------------------------------

--
-- Table structure for table `login_otp`
--

CREATE TABLE `login_otp` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL DEFAULT '',
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_otp`
--

INSERT INTO `login_otp` (`id`, `email`, `otp`, `otp_code`, `expires_at`, `used`, `created_at`) VALUES
(1, 'nodadoshanecarlo@gmail.com', '999103', '', '2026-03-24 14:10:48', 1, '2026-03-24 12:55:48'),
(4, 'nodadoshanecarlo@gmail.com', '112870', '', '2026-03-24 14:21:20', 1, '2026-03-24 13:06:20'),
(5, 'nodadoshanecarlo@gmail.com', '538569', '', '2026-03-24 14:53:27', 1, '2026-03-24 13:38:27'),
(6, 'nodadoshanecarlo@gmail.com', '861882', '', '2026-03-24 14:58:39', 1, '2026-03-24 13:43:39'),
(7, 'nodadoshanecarlo@gmail.com', '774697', '', '2026-03-24 14:59:22', 1, '2026-03-24 13:44:22'),
(8, 'nodadoshanecarlo@gmail.com', '177517', '', '2026-03-24 15:09:41', 1, '2026-03-24 13:54:41'),
(9, 'nodadoshanecarlo@gmail.com', '797257', '', '2026-03-24 15:14:49', 1, '2026-03-24 13:59:49'),
(10, 'nodadoshanecarlo@gmail.com', '875176', '', '2026-03-24 15:20:21', 1, '2026-03-24 14:05:21'),
(11, 'nodadoshanecarlo@gmail.com', '197883', '', '2026-03-24 15:20:50', 1, '2026-03-24 14:05:50'),
(12, 'nodadoshanecarlo@gmail.com', '849549', '', '2026-03-24 22:24:46', 1, '2026-03-24 14:09:46');

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
(2026, 117);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `exam_period` varchar(30) DEFAULT NULL,
  `or_ar_number` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_logs`
--

INSERT INTO `payment_logs` (`id`, `student_id`, `payment_method`, `gcash_reference`, `gcash_amount`, `gcash_date`, `transaction_id`, `semester`, `status`, `verified_by`, `verified_at`, `notes`, `created_at`, `updated_at`, `exam_period`, `or_ar_number`) VALUES
(234, 204, 'GCash', '12345', 8035.00, '2026-04-03', 'TXN-1775226154053-Y84U8', '1st Semester, AY 2027-2028', 'Verified', 3, '2026-04-03 14:22:44', '', '2026-04-03 14:22:34', '2026-04-03 14:22:44', 'Downpayment', NULL);

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
-- Table structure for table `payment_terms`
--

CREATE TABLE `payment_terms` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `term` enum('Downpayment','Prelim','Midterm','Finals') NOT NULL,
  `amount_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('locked','unpaid','partial','paid') NOT NULL DEFAULT 'locked',
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `privacy_access_log`
--

CREATE TABLE `privacy_access_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `accessed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL COMMENT 'Session user_id from sessions table',
  `user_role` varchar(30) NOT NULL,
  `endpoint` varchar(120) NOT NULL COMMENT 'PHP_SELF or action param',
  `field_key` varchar(80) NOT NULL,
  `action_taken` enum('full','masked','redacted','hidden') NOT NULL,
  `target_type` varchar(50) DEFAULT NULL COMMENT 'e.g. student, enrollment, grade',
  `target_id` int(11) DEFAULT NULL COMMENT 'Primary key of the target record',
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for every privacy mask or redaction applied';

-- --------------------------------------------------------

--
-- Table structure for table `privacy_settings`
--

CREATE TABLE `privacy_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `field_key` varchar(80) NOT NULL COMMENT 'Field name as it appears in API responses',
  `role` varchar(30) NOT NULL COMMENT 'Role this rule applies to',
  `access_level` enum('full','masked','hidden') NOT NULL DEFAULT 'hidden' COMMENT 'full=raw value, masked=apply mask fn, hidden=omit field',
  `mask_fn` varchar(60) DEFAULT NULL COMMENT 'PHP masking function name (e.g. maskEmail)',
  `updated_by` int(11) DEFAULT NULL COMMENT 'admin user_id who last changed this',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Runtime overrides for data_privacy.php RBAC+masking policy';

--
-- Dumping data for table `privacy_settings`
--

INSERT INTO `privacy_settings` (`id`, `field_key`, `role`, `access_level`, `mask_fn`, `updated_by`, `updated_at`) VALUES
(1, 'email', 'student', 'masked', 'maskEmail', NULL, '2026-03-21 22:49:27'),
(2, 'phone', 'student', 'masked', 'maskPhone', NULL, '2026-03-21 22:49:27'),
(3, 'gpa', 'student', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(4, 'student_number', 'student', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(5, 'address', 'student', 'hidden', NULL, NULL, '2026-03-21 22:49:27'),
(6, 'date_of_birth', 'student', 'hidden', NULL, NULL, '2026-03-21 22:49:27'),
(7, 'prelim', 'faculty', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(8, 'midterm', 'faculty', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(9, 'final', 'faculty', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(10, 'grade', 'faculty', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(11, 'gpa', 'faculty', 'masked', 'maskGpa', NULL, '2026-03-21 22:49:27'),
(12, 'amount', 'accounting', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(13, 'balance', 'accounting', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(14, 'total_paid', 'accounting', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(15, 'gcash_amount', 'accounting', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(16, 'reference_number', 'accounting', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(17, 'email', 'registrar', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(18, 'phone', 'registrar', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(19, 'address', 'registrar', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(20, 'date_of_birth', 'registrar', 'full', NULL, NULL, '2026-03-21 22:49:27'),
(21, 'amount', 'registrar', 'masked', 'maskAmount', NULL, '2026-03-21 22:49:27'),
(22, 'balance', 'registrar', 'masked', 'maskAmount', NULL, '2026-03-21 22:49:27');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `name`, `code`, `level_type`, `duration`, `description`, `department`, `created_at`, `updated_at`) VALUES
(1, 'Bachelor of Science in Accountancy', 'BSA', 'College', 4, 'A professional program covering financial accounting, auditing, taxation, and management advisory services.', 'Business Management Department (BMD)', '2026-03-03 01:44:06', '2026-03-11 16:27:11'),
(2, 'Bachelor of Science in Customs Administration', 'BSCA', 'College', 4, 'A program focused on customs brokerage, tariff, trade, and border control management.', 'Business Management Department (BMD)', '2026-03-03 01:44:06', '2026-03-11 16:27:11'),
(3, 'Bachelor of Science in Entrepreneurship', 'BSE', 'College', 4, 'A program developing entrepreneurial skills, business planning, and enterprise management.', 'Business Management Department (BMD)', '2026-03-03 01:44:06', '2026-03-11 16:27:11'),
(4, 'Bachelor of Science in Real Estate Management', 'BSREM', 'College', 4, 'A program covering real estate appraisal, brokerage, property management, and real estate finance.', 'Business Management Department (BMD)', '2026-03-03 01:44:06', '2026-03-11 16:27:11'),
(5, 'Computer Information Multimedia Technology', 'CIMT', 'College', 2, 'A 2-year program in computing, multimedia, and digital arts technology.', 'Information Communication and Technology (ICTD)', '2026-03-03 01:44:06', '2026-03-11 16:27:11'),
(6, 'Bachelor of Science in Information Technology', 'BSIT', 'College', 4, 'A program in software development, networking, database systems, and information assurance.', 'Information Communication and Technology (ICTD)', '2026-03-03 01:44:06', '2026-03-11 16:27:11'),
(24, 'General Academic Strand (GAS)', 'GAS', 'SHS', 2, 'SHS strand offering a broad general academic curriculum for undecided learners.', 'Academic Track', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(26, 'Information and Communication Technology (ICT)', 'ICT', 'SHS', 2, 'SHS TVL strand focused on computer and information technology skills.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(31, '3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)', 'DTTT', 'TVET', 2, 'A diploma program in travel and tourism technology that may lead to a BSTM degree.', 'Collge Diploma', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(35, 'Housekeeping NCII', 'HK-NCII', 'TVET', 1, 'TESDA National Certificate II program in Housekeeping.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(36, 'Bartending NCII', 'BART-NCII', 'TVET', 1, 'TESDA National Certificate II program in Bartending.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(37, 'Food and Beverages Services NCII', 'FBS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Food and Beverages Services.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(38, 'Front Office Services NCII', 'FO-NCII', 'TVET', 1, 'TESDA National Certificate II program in Front Office services.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(40, 'Technical Drafting NCII', 'GP-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Game Programming.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(41, 'Computer Systems Servicing NCII', 'CSS-NCII-TVET', 'TVET', 1, 'TESDA National Certificate II program in Computer Systems Servicing.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(42, 'Visual Graphic Design NCIII', 'VGD-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Visual Graphic Design.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(43, 'Cookery NCII', 'TS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Travel Services.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(44, 'Tourism Promotion Services NCII', 'TPS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Tourism Promotion Services.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(45, 'Event Management Services NCIII', 'EMS-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Event Management Services.', 'Short Programs(NC)', '2026-03-03 01:48:50', '2026-03-11 16:27:11'),
(89, 'Humanities and Social Sciences Strand (HUMMS)', 'HUMMS', 'SHS', 2, '', 'Academic Track', '2026-03-05 02:58:51', '2026-03-11 16:27:11'),
(90, 'Home Economics (HE)', 'HE', 'SHS', 2, '', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-05 03:03:31', '2026-03-11 16:27:11'),
(91, '3-Yrs. Diploma in Travel and Hospitality Management Technology (Leading to BSHM)', 'CDTHMT', 'TVET', 1, '', 'Collge Diploma', '2026-03-05 03:15:24', '2026-03-11 16:27:11'),
(92, 'Bread and Pastry Production NCII', 'BPP', 'TVET', 1, '', 'Short Programs(NC)', '2026-03-05 03:19:46', '2026-03-11 16:27:11'),
(93, '2-Yrs. Cruise Ship Management', '2YCSM', 'College', 4, '', 'Tourism and Hospitality Department (THD)', '2026-03-05 03:21:27', '2026-03-11 16:27:11'),
(94, 'Bachelor of Science in Hospitality Management', 'BSHM', 'College', 4, '', 'Tourism and Hospitality Department (THD)', '2026-03-05 03:22:05', '2026-03-11 16:27:11'),
(95, 'Bachelor of Science in Toursm Management', 'BSTM', 'College', 4, '', 'Tourism and Hospitality Department (THD)', '2026-03-05 03:22:44', '2026-03-11 16:27:11'),
(96, 'Bachelor of Science in Entertainment and Multimedia Computing', 'BSEMC', 'College', 4, 'A program integrating game development, 2D/3D animation, multimedia computing, and digital arts.', 'Information Communication and Technology (ICTD)', '2026-03-05 04:05:04', '2026-03-11 16:27:11');

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
(1441, 1, 7),
(1434, 1, 8),
(48, 1, 550),
(51, 1, 551),
(52, 1, 552),
(53, 1, 553),
(54, 1, 554),
(62, 1, 557),
(63, 1, 558),
(64, 1, 559),
(66, 1, 560),
(67, 1, 561),
(68, 1, 562),
(74, 1, 564),
(77, 1, 565),
(78, 1, 566),
(79, 1, 567),
(80, 1, 568),
(81, 1, 569),
(82, 1, 570),
(83, 1, 571),
(84, 1, 572),
(87, 1, 573),
(89, 1, 574),
(90, 1, 575),
(91, 1, 576),
(92, 1, 577),
(93, 1, 578),
(96, 1, 579),
(98, 1, 580),
(100, 1, 581),
(103, 1, 582),
(105, 1, 583),
(106, 1, 584),
(107, 1, 585),
(108, 1, 586),
(109, 1, 587),
(110, 1, 588),
(111, 1, 589),
(114, 1, 590),
(115, 1, 591),
(117, 1, 592),
(119, 1, 593),
(120, 1, 594),
(121, 1, 595),
(122, 1, 596),
(123, 1, 597),
(124, 1, 598),
(125, 1, 599),
(126, 1, 600),
(127, 1, 601),
(128, 1, 602),
(129, 1, 603),
(130, 1, 604),
(131, 1, 605),
(132, 1, 606),
(133, 1, 607),
(134, 1, 608),
(1433, 2, 8),
(56, 2, 554),
(70, 2, 562),
(76, 2, 564),
(86, 2, 572),
(94, 2, 578),
(99, 2, 580),
(102, 2, 581),
(112, 2, 589),
(116, 2, 591),
(118, 2, 592),
(135, 2, 609),
(136, 2, 610),
(137, 2, 611),
(138, 2, 612),
(139, 2, 613),
(140, 2, 614),
(141, 2, 615),
(142, 2, 616),
(143, 2, 617),
(144, 2, 618),
(145, 2, 619),
(146, 2, 620),
(147, 2, 621),
(148, 2, 622),
(149, 2, 623),
(150, 2, 624),
(151, 2, 625),
(152, 2, 626),
(153, 2, 627),
(154, 2, 628),
(155, 2, 629),
(156, 2, 630),
(157, 2, 631),
(158, 2, 632),
(159, 2, 633),
(160, 2, 634),
(161, 2, 635),
(162, 2, 636),
(163, 2, 637),
(164, 2, 638),
(49, 3, 550),
(55, 3, 554),
(65, 3, 559),
(69, 3, 562),
(75, 3, 564),
(85, 3, 572),
(88, 3, 573),
(95, 3, 578),
(97, 3, 579),
(101, 3, 581),
(104, 3, 582),
(113, 3, 589),
(165, 3, 639),
(166, 3, 640),
(167, 3, 641),
(168, 3, 642),
(169, 3, 643),
(170, 3, 644),
(171, 3, 645),
(172, 3, 646),
(173, 3, 647),
(174, 3, 648),
(175, 3, 649),
(176, 3, 650),
(177, 3, 651),
(178, 3, 652),
(179, 3, 653),
(180, 3, 654),
(181, 3, 655),
(182, 3, 656),
(183, 3, 657),
(184, 3, 658),
(185, 3, 659),
(186, 3, 660),
(187, 3, 661),
(188, 3, 662),
(189, 3, 663),
(190, 3, 664),
(191, 4, 665),
(192, 4, 666),
(193, 4, 667),
(194, 4, 668),
(195, 4, 669),
(196, 4, 670),
(197, 4, 671),
(198, 4, 672),
(199, 4, 673),
(200, 4, 674),
(201, 4, 675),
(202, 4, 676),
(203, 4, 677),
(204, 4, 678),
(205, 4, 679),
(206, 4, 680),
(207, 4, 681),
(208, 4, 682),
(209, 4, 683),
(210, 4, 684),
(211, 4, 685),
(212, 4, 686),
(213, 4, 687),
(214, 4, 688),
(215, 4, 689),
(216, 4, 690),
(217, 4, 691),
(218, 4, 692),
(219, 4, 693),
(220, 4, 694),
(221, 4, 695),
(222, 4, 696),
(223, 4, 697),
(224, 4, 698),
(225, 4, 699),
(226, 4, 700),
(227, 4, 701),
(228, 4, 702),
(229, 4, 703),
(230, 4, 704),
(231, 4, 705),
(232, 4, 706),
(233, 4, 707),
(234, 4, 708),
(235, 4, 709),
(236, 4, 710),
(237, 4, 711),
(238, 4, 712),
(239, 4, 713),
(240, 4, 714),
(241, 4, 715),
(242, 4, 716),
(243, 4, 717),
(244, 4, 718),
(245, 4, 719),
(246, 4, 720),
(247, 4, 721),
(248, 4, 722),
(259, 5, 1),
(258, 5, 2),
(260, 5, 3),
(261, 5, 4),
(263, 5, 5),
(257, 5, 6),
(1440, 5, 7),
(1432, 5, 8),
(277, 5, 9),
(1444, 5, 11),
(273, 5, 13),
(281, 5, 14),
(282, 5, 15),
(1424, 5, 16),
(265, 5, 17),
(266, 5, 18),
(267, 5, 19),
(268, 5, 20),
(274, 5, 22),
(269, 5, 23),
(270, 5, 24),
(271, 5, 25),
(272, 5, 26),
(275, 5, 28),
(276, 5, 45),
(50, 5, 550),
(278, 5, 578),
(249, 5, 723),
(250, 5, 724),
(251, 5, 725),
(252, 5, 726),
(253, 5, 727),
(254, 5, 728),
(255, 5, 729),
(256, 5, 730),
(1, 6, 1),
(2, 6, 2),
(47, 6, 3),
(4, 6, 4),
(5, 6, 5),
(6, 6, 6),
(1438, 6, 7),
(1430, 6, 8),
(9, 6, 9),
(10, 6, 10),
(1442, 6, 11),
(12, 6, 12),
(13, 6, 13),
(14, 6, 14),
(15, 6, 15),
(1422, 6, 16),
(17, 6, 17),
(18, 6, 18),
(19, 6, 19),
(20, 6, 20),
(21, 6, 21),
(22, 6, 22),
(23, 6, 23),
(24, 6, 24),
(25, 6, 25),
(26, 6, 26),
(27, 6, 27),
(28, 6, 28),
(29, 6, 29),
(30, 6, 30),
(31, 6, 31),
(32, 6, 32),
(33, 6, 33),
(34, 6, 34),
(35, 6, 35),
(36, 6, 36),
(37, 6, 37),
(38, 6, 38),
(39, 6, 39),
(40, 6, 40),
(41, 6, 41),
(42, 6, 42),
(43, 6, 43),
(44, 6, 44),
(45, 6, 45),
(46, 6, 46),
(1448, 24, 2043),
(1383, 96, 1),
(1374, 96, 2),
(1369, 96, 3),
(1370, 96, 4),
(1371, 96, 5),
(1372, 96, 6),
(1439, 96, 7),
(1431, 96, 8),
(1380, 96, 9),
(1443, 96, 11),
(1398, 96, 13),
(1384, 96, 14),
(1385, 96, 15),
(1423, 96, 16),
(1387, 96, 17),
(1391, 96, 19),
(1389, 96, 20),
(1392, 96, 22),
(1393, 96, 23),
(1420, 96, 26),
(1406, 96, 27),
(1399, 96, 28),
(1404, 96, 32),
(1401, 96, 34),
(1407, 96, 35),
(1412, 96, 39),
(1413, 96, 40),
(1414, 96, 41),
(1418, 96, 44),
(1419, 96, 45),
(1373, 96, 550),
(1377, 96, 723),
(1378, 96, 724),
(1381, 96, 725),
(1397, 96, 726),
(1394, 96, 727),
(1395, 96, 728),
(1382, 96, 2027),
(1388, 96, 2028),
(1390, 96, 2029),
(1396, 96, 2030),
(1400, 96, 2031),
(1402, 96, 2032),
(1403, 96, 2033),
(1405, 96, 2034),
(1408, 96, 2035),
(1409, 96, 2036),
(1410, 96, 2037),
(1411, 96, 2038),
(1415, 96, 2039),
(1416, 96, 2040),
(1417, 96, 2041),
(1421, 96, 2042);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `building`, `capacity`, `room_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Room 101', 'Liberal Arts Building', 45, 'Classroom', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(2, 'Room 102', 'Liberal Arts Building', 45, 'Classroom', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(3, 'Room 103', 'Liberal Arts Building', 45, 'Classroom', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(4, 'Room 205', 'Science Building', 40, 'Classroom', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(5, 'Room 301', 'Science Building', 51, 'Classroom', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(6, 'Room 301', 'IT Building', 40, 'Laboratory', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(7, 'Room 401', 'IT Building', 40, 'Classroom', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(8, 'Lab 101', 'IT Building', 35, 'Laboratory', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(9, 'Lab 202', 'IT Building', 35, 'Laboratory', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(10, 'Lecture Hall A', 'Main Building', 100, 'Lecture Hall', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(11, 'Conference Room 1', 'Admin Building', 20, 'Conference Room', 'Available', '2026-02-01 00:11:11', '2026-03-11 16:27:11'),
(12, 'Room 301 Lab', 'IT Building', 40, 'Laboratory', 'Available', '2026-03-01 13:05:21', '2026-03-11 16:27:11');

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_pre_approvals`
--

CREATE TABLE `scholarship_pre_approvals` (
  `id` int(11) NOT NULL,
  `claim_code` varchar(20) NOT NULL,
  `scholar_type` varchar(100) NOT NULL DEFAULT 'Full Scholarship',
  `grantor` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `semester` varchar(100) DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_by_student_id` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `is_revoked` tinyint(1) NOT NULL DEFAULT 0,
  `revoked_at` datetime DEFAULT NULL,
  `revoke_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_by_email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scholarship_pre_approvals`
--

INSERT INTO `scholarship_pre_approvals` (`id`, `claim_code`, `scholar_type`, `grantor`, `notes`, `semester`, `is_used`, `used_by_student_id`, `used_at`, `is_revoked`, `revoked_at`, `revoke_reason`, `created_by`, `created_by_email`, `created_at`, `updated_at`) VALUES
(1, 'SCH-82NPKTUS', 'Full Scholarship', 'City of Olongapo', '', '1st Semester AY 2025 2026', 1, 156, '2026-03-22 23:28:16', 0, NULL, NULL, 3, 'accounting@example.com', '2026-03-22 15:24:46', '2026-03-22 15:28:16'),
(2, 'SCH-NV9DU8BX', 'Full Scholarship', 'City of Olongapo', '', 'dsds', 0, NULL, NULL, 0, NULL, NULL, 3, 'accounting@example.com', '2026-03-22 16:04:59', '2026-03-22 16:04:59'),
(3, 'SCH-5HJYF4NQ', 'Full Scholarship', 'aesa', '', 'sa', 0, NULL, NULL, 0, NULL, NULL, 3, 'accounting@example.com', '2026-03-22 16:13:32', '2026-03-22 16:13:32'),
(4, 'SCH-ZMP5J3XG', 'Full Scholarship', '', '', '', 0, NULL, NULL, 0, NULL, NULL, 3, 'accounting@example.com', '2026-03-22 16:17:00', '2026-03-22 16:17:00');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_events`
--

INSERT INTO `school_events` (`id`, `title`, `event_date`, `type`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Enrollment Period Opens', '2026-01-20', 'enrollment', '1st Semester enrollment starts', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(2, 'Enrollment Deadline', '2026-01-31', 'enrollment', 'Last day to enroll without late penalty', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(3, 'Tuition Payment Deadline', '2026-02-28', 'payment', 'Pay tuition to avoid holds on your account', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(4, 'University Sports Fest', '2026-02-14', 'activity', 'Annual inter-department sports festival', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(5, 'Midterm Examinations', '2026-03-10', 'exam', 'Midterm exam week begins — all departments', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(6, 'Midterm Exams End', '2026-03-14', 'exam', 'Last day of midterm examinations', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(7, 'Foundation Day (No Classes)', '2026-03-25', 'holiday', 'University Foundation Day — school holiday', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(8, 'Araw ng Kagitingan', '2026-04-09', 'holiday', 'Day of Valor — national holiday', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(9, 'Holy Thursday', '2026-04-17', 'holiday', 'Holy Week — school suspended', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(10, 'Good Friday', '2026-04-18', 'holiday', 'Holy Week — school suspended', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(11, 'Final Examinations Begin', '2026-05-05', 'exam', 'Final examination period starts', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(12, 'Final Examinations End', '2026-05-09', 'exam', 'Last day of final examinations', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(13, 'Official Grades Released', '2026-05-20', 'activity', 'Final grades viewable via student portal', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(14, 'Enrollment — 2nd Semester', '2026-06-01', 'enrollment', 'Enrollment opens for 2nd Semester', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(15, 'Independence Day', '2026-06-12', 'holiday', 'Philippine Independence Day — no classes', '2026-02-01 00:52:41', '2026-03-11 16:27:11'),
(16, 'asa', '2026-03-04', 'enrollment', '123', '2026-03-04 06:05:41', '2026-03-11 16:27:11');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` varchar(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `token`, `role`, `expires_at`, `created_at`, `device_id`, `ip_address`) VALUES
(242, 141, 'fbb8a574304924c90a2d6c57aed13d952596dc162cf28bfb2a725b88481f57f6', 'faculty', '2026-03-28 06:30:05', '2026-03-27 14:30:05', '', '::1'),
(250, 138, '0248151663f1012c316aea48716eed5c4e5dabae5e0d1c25c249b8a4a780712b', 'faculty', '2026-03-28 07:26:59', '2026-03-27 15:26:59', '', '::1'),
(340, 143, 'e74b066774cf9ee002c6672eef5db8334d483d8538ca1efc439c5220659b23d3', 'faculty', '2026-04-02 07:32:46', '2026-04-01 15:32:46', '', '::1'),
(362, 4, '3825e8d15cf614c2a9933c3e04cb8d7b1b7016998736f1fbee4064400a1bc8b3', 'registrar', '2026-04-04 02:10:58', '2026-04-03 10:10:58', '', '::1'),
(363, 3, '03162060d08512264b72e05ca4e15f43dd14ade4a16b959762707b36ba7f9359', 'accounting', '2026-04-04 02:11:01', '2026-04-03 10:11:01', '', '::1'),
(364, 2, '20a324471a9349c6479e7ca65fe2f91c4558ec548c44192167433075d88ae2a7', 'admin', '2026-04-04 02:11:05', '2026-04-03 10:11:05', '', '::1'),
(369, 220, '5ac0e6ffe36cd5269ab306027ce69aa73d00a2c1cbe768993bbf40a5b2d53e36', 'student', '2026-04-04 06:22:27', '2026-04-03 14:22:27', '', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `soa_snapshots`
--

CREATE TABLE `soa_snapshots` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `semester` varchar(100) NOT NULL,
  `units` int(11) NOT NULL DEFAULT 0,
  `tuition_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `miscellaneous_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `registration_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `laboratory_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `energy_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `installment_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_assessment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_plan` varchar(20) NOT NULL DEFAULT 'full',
  `payment_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `subjects_json` mediumtext DEFAULT NULL,
  `payments_json` mediumtext DEFAULT NULL,
  `snapshotted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `soa_snapshots`
--

INSERT INTO `soa_snapshots` (`id`, `student_id`, `semester`, `units`, `tuition_fee`, `miscellaneous_fee`, `registration_fee`, `laboratory_fee`, `energy_fee`, `subtotal`, `discount`, `installment_fee`, `total_assessment`, `total_paid`, `balance`, `payment_plan`, `payment_status`, `subjects_json`, `payments_json`, `snapshotted_at`) VALUES
(1, 191, '2nd Semester, AY 2025-2026', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 750.00, 29998.00, 7500.00, 22498.00, 'installment', 'Partially Paid', '[{\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"IS103\",\"name\":\"IT Infrastructure and Network Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"IT100\",\"name\":\"Introduction to Human Computer Interaction\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"NSTP2\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Completed\"}]', '[{\"or_ar_number\":\"AR-20260094\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-03-29\",\"payment_method\":\"GCash\",\"amount\":\"7500.00\",\"semester\":\"2nd Semester, AY 2025-2026\"}]', '2026-03-29 17:18:54'),
(3, 191, '1st Semester, AY 2026-2027', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 0.00, 29248.00, 29248.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260095\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-03-29\",\"payment_method\":\"Cash\",\"amount\":\"29248.00\",\"semester\":\"1st Semester, AY 2026-2027\"}]', '2026-03-29 17:19:39'),
(4, 192, '1st Semester, AY 2026-2027', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 0.00, 31387.00, 31387.00, 0.00, 'full', 'Fully Paid', '[{\"code\":\"CC100\",\"name\":\"Introduction to Computing\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"CC101\",\"name\":\"Computer Programming 1\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"GE105\",\"name\":\"Mathematics in the Modern World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Completed\"}]', '[{\"or_ar_number\":\"OR-20260096\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-03-30\",\"payment_method\":\"Cash\",\"amount\":\"31387.00\",\"semester\":\"1st Semester, AY 2026-2027\"}]', '2026-03-30 10:25:07'),
(6, 192, '2nd Semester, AY 2026-2027', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 750.00, 29998.00, 7499.50, 22498.50, 'installment', 'Partially Paid', '[{\"code\":\"CC102\",\"name\":\"Computer Programming 2\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"IS103\",\"name\":\"IT Infrastructure and Network Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"IT100\",\"name\":\"Introduction to Human Computer Interaction\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"NSTP2\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Completed\"}]', '[{\"or_ar_number\":\"AR-20260097\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-03-30\",\"payment_method\":\"Cash\",\"amount\":\"7499.50\",\"semester\":\"2nd Semester, AY 2026-2027\"}]', '2026-03-30 10:51:11'),
(8, 192, '1st Semester, AY 2027-2028', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 750.00, 29998.00, 15000.00, 14998.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260098\",\"or_ar_type\":\"AR\",\"exam_period\":\"Prelim\",\"payment_date\":\"2026-03-30\",\"payment_method\":\"GCash\",\"amount\":\"7500.00\",\"semester\":\"1st Semester, AY 2027-2028\"},{\"or_ar_number\":\"AR-20260099\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-03-30\",\"payment_method\":\"GCash\",\"amount\":\"7500.00\",\"semester\":\"1st Semester, AY 2027-2028\"}]', '2026-03-30 11:38:05'),
(9, 194, '1st Semester, AY 2027-2028', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 0.00, 31387.00, 31387.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260100\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-03-31\",\"payment_method\":\"GCash\",\"amount\":\"31387.00\",\"semester\":\"1st Semester, AY 2027-2028\"}]', '2026-03-31 07:47:26'),
(10, 195, '1st Semester, AY 2027-2028', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, 26034.25, 6102.75, 'installment', 'Partially Paid', '[{\"code\":\"CC100\",\"name\":\"Introduction to Computing\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"CC101\",\"name\":\"Computer Programming 1\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"GE105\",\"name\":\"Mathematics in the Modern World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Completed\"}]', '[{\"or_ar_number\":\"AR-20260102\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-01\",\"payment_method\":\"Cash\",\"amount\":\"8034.25\",\"semester\":\"1st Semester, AY 2027-2028\"},{\"or_ar_number\":\"AR-20260103\",\"or_ar_type\":\"AR\",\"exam_period\":\"Prelim\",\"payment_date\":\"2026-04-01\",\"payment_method\":\"Cash\",\"amount\":\"10000.00\",\"semester\":\"1st Semester, AY 2027-2028\"},{\"or_ar_number\":\"AR-20260104\",\"or_ar_type\":\"AR\",\"exam_period\":\"Midterm\",\"payment_date\":\"2026-04-01\",\"payment_method\":\"Cash\",\"amount\":\"8000.00\",\"semester\":\"1st Semester, AY 2027-2028\"}]', '2026-04-03 10:10:15'),
(12, 196, '1st Semester, AY 2026-2027', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, 8034.25, 24102.75, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260105\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"8034.25\",\"semester\":\"1st Semester, AY 2026-2027\"}]', '2026-04-03 07:20:56'),
(13, 197, '1st Semester, AY 2026-2027', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 0.00, 31387.00, 31387.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260106\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"31387.00\",\"semester\":\"1st Semester, AY 2026-2027\"}]', '2026-04-03 07:25:21'),
(14, 198, '1st Semester, AY 2026-2027', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, 8034.25, 24102.75, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260107\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"8034.25\",\"semester\":\"1st Semester, AY 2026-2027\"}]', '2026-04-03 07:38:21'),
(15, 199, '1st Semester, AY 2026-2027', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 0.00, 31387.00, 31387.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260108\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"31387.00\",\"semester\":\"1st Semester, AY 2026-2027\"}]', '2026-04-03 07:43:45'),
(16, 200, '1st Semester, AY 2026-2027', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 750.00, 29998.00, 31387.00, 0.00, 'installment', 'Fully Paid', '[{\"code\":\"GE105\",\"name\":\"Mathematics in the Modern World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Completed\"}]', '[{\"or_ar_number\":\"OR-20260109\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"31387.00\",\"semester\":\"1st Semester, AY 2026-2027\"}]', '2026-04-03 09:16:15'),
(18, 200, '2nd Semester AY 2026-2027', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 750.00, 29998.00, 7499.50, 22498.50, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260110\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"7499.50\",\"semester\":\"2nd Semester AY 2026-2027\"}]', '2026-04-03 09:11:51'),
(28, 195, '2nd Semester AY 2026-2027', 26, 16900.00, 6688.00, 700.00, 7600.00, 1638.00, 33526.00, 0.00, 750.00, 34276.00, 29998.00, 4278.00, 'installment', 'Partially Paid', '[{\"code\":\"CAP501\",\"name\":\"Capstone Project\",\"credits\":\"6\",\"lec_units\":\"6\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"CC102\",\"name\":\"Computer Programming 2\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IS103\",\"name\":\"IT Infrastructure and Network Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"IT100\",\"name\":\"Introduction to Human Computer Interaction\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP2\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"OR-20260111\",\"or_ar_type\":\"OR\",\"exam_period\":\"Finals\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"29998.00\",\"semester\":\"2nd Semester AY 2026-2027\"}]', '2026-04-03 10:20:23'),
(37, 195, '1st Semester AY 2027-2028', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 750.00, 29998.00, 7500.00, 22498.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260112\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"GCash\",\"amount\":\"7500.00\",\"semester\":\"1st Semester AY 2027-2028\"}]', '2026-04-03 10:20:51'),
(38, 201, '1st Semester, AY 2027-2028', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, 8035.00, 24102.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260113\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"GCash\",\"amount\":\"8035.00\",\"semester\":\"1st Semester, AY 2027-2028\"}]', '2026-04-03 13:28:52'),
(39, 202, '1st Semester, AY 2027-2028', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, 8035.00, 24102.00, 'installment', 'Partially Paid', '[{\"code\":\"CC100\",\"name\":\"Introduction to Computing\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"CC101\",\"name\":\"Computer Programming 1\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"GE105\",\"name\":\"Mathematics in the Modern World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Completed\"}]', '[{\"or_ar_number\":\"AR-20260114\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"GCash\",\"amount\":\"8035.00\",\"semester\":\"1st Semester, AY 2027-2028\"}]', '2026-04-03 14:16:58'),
(41, 202, '1st Semester AY 2027-2028', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 0.00, 31387.00, 31387.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260115\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"31387.00\",\"semester\":\"1st Semester AY 2027-2028\"}]', '2026-04-03 14:17:12'),
(42, 203, '1st Semester, AY 2027-2028', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, 8034.25, 24102.75, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260116\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"Cash\",\"amount\":\"8034.25\",\"semester\":\"1st Semester, AY 2027-2028\"}]', '2026-04-03 14:18:50'),
(43, 204, '1st Semester, AY 2027-2028', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, 8035.00, 24102.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260117\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-03\",\"payment_method\":\"GCash\",\"amount\":\"8035.00\",\"semester\":\"1st Semester, AY 2027-2028\"}]', '2026-04-03 14:22:44');

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `middle_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_profiles`
--

INSERT INTO `staff_profiles` (`id`, `user_id`, `first_name`, `last_name`, `middle_name`, `phone`, `department`, `position`, `created_at`, `updated_at`) VALUES
(1, 2, 'Admin', 'User', NULL, NULL, NULL, NULL, '2026-03-18 19:24:57', '2026-03-18 19:24:57'),
(2, 3, 'Accounting', 'Staff', NULL, NULL, NULL, NULL, '2026-03-18 19:24:57', '2026-03-18 19:24:57'),
(3, 4, 'Registrar', 'Admin', NULL, NULL, NULL, NULL, '2026-03-18 19:24:57', '2026-03-18 19:24:57');

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
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT '1st Year',
  `gpa` decimal(4,2) DEFAULT 0.00,
  `enrollment_status` enum('Pending','Enrolled','Confirmed','Completed','Graduated','Inactive','Dropped') DEFAULT 'Pending',
  `student_type` enum('New','Old','Continuing','Returning','Transferee') DEFAULT 'New',
  `tor_eval_status` enum('NotRequired','Pending','Evaluated','Rejected') NOT NULL DEFAULT 'NotRequired',
  `student_category` varchar(20) DEFAULT 'College',
  `payment_status` enum('Pending','Paid','Overdue','Partial','Free') DEFAULT 'Pending',
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `semester` varchar(100) DEFAULT '',
  `accounting_approved_by` int(11) DEFAULT NULL,
  `accounting_approved_at` timestamp NULL DEFAULT NULL,
  `accounting_notes` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `tor_file` varchar(255) DEFAULT NULL,
  `psa_file` varchar(255) DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tvet_type` varchar(50) DEFAULT '',
  `payment_plan` varchar(20) DEFAULT 'full',
  `payment_method` varchar(20) DEFAULT 'GCash',
  `age` int(11) DEFAULT NULL,
  `is_scholar` tinyint(1) NOT NULL DEFAULT 0,
  `scholar_grantor` varchar(150) DEFAULT NULL,
  `scholar_type` varchar(100) DEFAULT NULL,
  `scholarship_amount` decimal(10,2) DEFAULT 0.00,
  `registrar_confirmed` enum('Pending','Confirmed','Rejected') DEFAULT 'Pending',
  `registrar_confirmed_at` datetime DEFAULT NULL,
  `registrar_confirmed_by` int(11) DEFAULT NULL,
  `registrar_notes` text DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL COMMENT 'When the record was archived (soft-delete)',
  `archived_by` int(11) DEFAULT NULL COMMENT 'Admin user_id who triggered the archive',
  `archive_reason` varchar(255) DEFAULT NULL COMMENT 'e.g. Graduated, Dropped, 10-year retention expired',
  `is_anonymized` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = personal data has been anonymized (NPC compliance)',
  `anonymized_at` datetime DEFAULT NULL,
  `last_active_year` year(4) DEFAULT NULL COMMENT 'Last academic year the student was active (for 10-yr countdown)',
  `is_irregular` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = student is irregular (failed ≥1 subject; not on standard curriculum path)',
  `block_id` int(11) DEFAULT NULL COMMENT 'FK → class_blocks.id — null until Registrar assigns a block'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_number`, `first_name`, `last_name`, `middle_name`, `suffix`, `lrn_no`, `sex`, `religion`, `place_of_birth`, `citizenship`, `mother_tongue`, `is_indigenous`, `has_special_needs`, `special_needs_details`, `has_assistive_tech`, `assistive_tech_details`, `strand`, `learning_delivery`, `last_school_attended`, `psa_birth_cert_no`, `phone`, `date_of_birth`, `address`, `emergency_contact`, `emergency_phone`, `program`, `program_id`, `year_level`, `gpa`, `enrollment_status`, `student_type`, `tor_eval_status`, `student_category`, `payment_status`, `approval_status`, `semester`, `accounting_approved_by`, `accounting_approved_at`, `accounting_notes`, `profile_picture`, `tor_file`, `psa_file`, `enrollment_date`, `created_at`, `updated_at`, `tvet_type`, `payment_plan`, `payment_method`, `age`, `is_scholar`, `scholar_grantor`, `scholar_type`, `scholarship_amount`, `registrar_confirmed`, `registrar_confirmed_at`, `registrar_confirmed_by`, `registrar_notes`, `archived_at`, `archived_by`, `archive_reason`, `is_anonymized`, `anonymized_at`, `last_active_year`, `is_irregular`, `block_id`) VALUES
(204, 220, 'STU-2026-0001', 'shane', 'binoya', 'carlo', '', '1', 'Male', '1', '1', '1', '1', 0, 0, '', 0, '', '', '', 'Junior High School - 1 (1)', '1', '1', '2002-11-22', '1', 'shane carlo binoya', '1', 'Bachelor of Science in Information Technology', NULL, '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Partial', 'Approved', '1st Semester, AY 2027-2028', 3, '2026-04-03 14:22:44', '', NULL, NULL, NULL, '2026-04-03', '2026-04-03 14:22:25', '2026-04-03 14:22:57', '', 'installment', 'GCash', NULL, 0, NULL, NULL, 0.00, 'Confirmed', '2026-04-03 22:22:56', 4, '', NULL, NULL, NULL, 0, NULL, NULL, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_archive_log`
--

CREATE TABLE `student_archive_log` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL COMMENT 'Original students.id',
  `student_number` varchar(20) NOT NULL,
  `full_name` varchar(255) NOT NULL COMMENT 'Snapshot of name at archive time',
  `program` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `last_active_year` year(4) DEFAULT NULL,
  `archive_reason` varchar(255) DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL COMMENT 'Admin user_id',
  `archived_at` datetime NOT NULL DEFAULT current_timestamp(),
  `scheduled_anonymize_at` date DEFAULT NULL COMMENT '= archived_at + 10 years. Personal data wiped on this date.',
  `is_anonymized` tinyint(1) NOT NULL DEFAULT 0,
  `anonymized_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks all student archiving and 10-year anonymization schedule (RA 10173)';

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
-- Table structure for table `student_guardians`
--

CREATE TABLE `student_guardians` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `guardian_name` varchar(150) NOT NULL DEFAULT '',
  `address` text DEFAULT NULL,
  `contact` varchar(50) DEFAULT '',
  `relationship` varchar(50) DEFAULT NULL,
  `is_emergency` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(150) DEFAULT NULL COMMENT 'Guardian email — used for SOA and enrollment report emails'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_guardians`
--

INSERT INTO `student_guardians` (`id`, `student_id`, `guardian_name`, `address`, `contact`, `relationship`, `is_emergency`, `created_at`, `email`) VALUES
(65, 204, 'shane carlo binoya', '1', '1', 'Mother', 1, '2026-04-03 14:22:25', 'shanecarlobinoya@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `student_scholarships`
--

CREATE TABLE `student_scholarships` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `scholar_type` varchar(100) DEFAULT NULL,
  `grantor` varchar(150) DEFAULT NULL,
  `scholarship_amount` decimal(10,2) DEFAULT 0.00,
  `semester` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `granted_by_email` varchar(150) DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by_email` varchar(150) DEFAULT NULL,
  `revoke_reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','superseded') NOT NULL DEFAULT 'approved',
  `reviewed_by_email` varchar(150) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reject_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subject_fee_log`
--

CREATE TABLE `subject_fee_log` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `course_code` varchar(30) DEFAULT NULL,
  `course_name` varchar(150) DEFAULT NULL,
  `action` enum('Add','Drop') NOT NULL DEFAULT 'Add',
  `subject_type` varchar(20) DEFAULT 'Lecture' COMMENT 'Lecture or Laboratory',
  `course_category` varchar(30) DEFAULT NULL COMMENT 'Major, Minor, GE, PE, NSTP, Elective',
  `units` int(11) DEFAULT 0,
  `lec_units` int(11) DEFAULT 0,
  `lab_units` int(11) DEFAULT 0,
  `tuition_impact` decimal(10,2) DEFAULT 0.00 COMMENT 'Change in tuition (units × rate)',
  `lab_fee_impact` decimal(10,2) DEFAULT 0.00 COMMENT 'Lab fee added (1 lab room = ₱1900)',
  `energy_impact` decimal(10,2) DEFAULT 0.00 COMMENT 'Energy fee impact',
  `total_impact` decimal(10,2) DEFAULT 0.00 COMMENT 'Net change in total assessment',
  `semester` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `added_by_role` varchar(30) DEFAULT NULL,
  `added_by_email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sys_config`
--

CREATE TABLE `sys_config` (
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sys_config`
--

INSERT INTO `sys_config` (`config_key`, `config_value`, `updated_at`) VALUES
('enrollment_period', '{\"is_open\":true,\"start\":\"2026-04-02 22:52\",\"end\":\"2026-04-10 22:52\",\"label\":\"1st Semester AY 2027-2028\",\"semester\":\"\",\"school_year\":\"2026-2027\"}', '2026-04-03 10:18:42'),
('payment_due_dates', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"jan 1\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"aug 2\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"dec 3\"}}', '2026-04-03 10:20:04'),
('payment_due_dates:1st_semester:2026-2027', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"JUNE 18 - 27 2027\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"JULY 19 - 28  2027\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"AUGUST 20 - 30 2027\"}}', '2026-03-29 09:11:11'),
('payment_due_dates:1st_semester:2027-2028', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"jan 1\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"aug 2\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"dec 3\"}}', '2026-04-03 10:20:04'),
('payment_due_dates:2nd_semester:2026-2027', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"JUNE 21-30\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"JULY 20-30\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"AUG 20-30\"}}', '2026-03-30 10:49:59'),
('payment_due_dates:2nd_semester:2027-2028', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"JANUARY 11-19, 2026\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"FEBRUARY 22- 29, 2026\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"MARCH 10 - APRIL 4, 2026\"}}', '2026-03-29 16:18:37'),
('payment_due_dates_active_semester', 'payment_due_dates:1st_semester:2027-2028', '2026-04-03 10:20:04');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `semester` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tuition_fees`
--

INSERT INTO `tuition_fees` (`id`, `student_id`, `units`, `tuition_fee`, `miscellaneous_fee`, `registration_fee`, `laboratory_fee`, `energy_fee`, `subtotal`, `discount`, `installment_fee`, `total_assessment`, `created_at`, `updated_at`, `semester`) VALUES
(3193, 204, 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, '2026-04-03 14:22:28', '2026-04-03 14:26:44', '1st Semester, AY 2027-2028');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','admin','accounting','registrar','faculty') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `created_at`, `updated_at`, `is_active`) VALUES
(2, 'admin@example.com', '$2y$12$3PKbSB8zc6aChMqLYG8ogueASBqxP0Bwu5/oTOjlhD8wItI45NHSa', 'admin', '2026-01-29 07:51:13', '2026-03-19 18:17:56', 1),
(3, 'accounting@example.com', '$2y$12$lqO2L/wO1gGW1G7iVlNL1eoNJEAwaUKKpymNTLc8/bDphJioiLzhu', 'accounting', '2026-01-29 07:51:13', '2026-03-11 16:27:10', 1),
(4, 'registrar@example.com', '$2y$12$mBRinZXeLFpyge/D499ceeBuVHsRqy6OiVNtDm.YuSdfAgVdFNrWG', 'registrar', '2026-01-29 08:54:49', '2026-03-11 16:27:10', 1),
(136, 'maria.santos@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(137, 'juan.reyes@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(138, 'anna.garcia@school.edu', '$2y$12$gaEO5.wb1d.usu8cOYX1QOYh9pHSP0qk5xkXevfWThw1NiBSwa5ri', 'faculty', '2026-03-04 13:10:06', '2026-03-27 15:26:59', 1),
(139, 'luis.rodriguez@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(140, 'sarah.kim@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(141, 'liza.delacruz@school.edu', '$2y$12$3FUmisJn3HX8CKJRlDUbc.SZ6.LtP2eV99MfH3sMBUZLftGT8S18m', 'faculty', '2026-03-04 13:10:06', '2026-03-27 08:20:20', 1),
(142, 'ramon.villanueva@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(143, 'carlo.mendoza@school.edu', '$2y$12$.LpnKQRr717Vhf4vip2Iq.mnD8MuI6jDTqdqysk99kvNXhtARslB2', 'faculty', '2026-03-04 13:10:06', '2026-03-28 18:56:29', 1),
(220, 'shane333', '$2y$12$eKVi304/iIbznfIpcvAA6.yPaQvXllJ7QZxTnABMvy7w0rmQHIPw6', 'student', '2026-04-03 14:22:25', '2026-04-03 14:22:25', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_course_enrolled_count`
-- (See below for the actual view)
--
CREATE TABLE `v_course_enrolled_count` (
`course_id` int(11)
,`enrolled_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_privacy_policy`
-- (See below for the actual view)
--
CREATE TABLE `v_privacy_policy` (
`field_key` varchar(80)
,`admin` varchar(6)
,`registrar` varchar(6)
,`faculty` varchar(6)
,`accounting` varchar(6)
,`student` varchar(6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_students_due_anonymization`
-- (See below for the actual view)
--
CREATE TABLE `v_students_due_anonymization` (
`id` int(11)
,`student_id` int(11)
,`student_number` varchar(20)
,`full_name` varchar(255)
,`program` varchar(100)
,`last_active_year` year(4)
,`archived_at` datetime
,`scheduled_anonymize_at` date
,`days_remaining` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_guardian_contact`
-- (See below for the actual view)
--
CREATE TABLE `v_student_guardian_contact` (
`student_id` int(11)
,`student_number` varchar(20)
,`student_name` varchar(201)
,`program` varchar(100)
,`year_level` varchar(20)
,`semester` varchar(100)
,`enrollment_status` enum('Pending','Enrolled','Confirmed','Completed','Graduated','Inactive','Dropped')
,`payment_status` enum('Pending','Paid','Overdue','Partial','Free')
,`student_email` varchar(255)
,`guardian_name` varchar(150)
,`relationship` varchar(50)
,`guardian_phone` varchar(50)
,`guardian_email` varchar(150)
);

-- --------------------------------------------------------

--
-- Structure for view `v_course_enrolled_count`
--
DROP TABLE IF EXISTS `v_course_enrolled_count`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_course_enrolled_count`  AS SELECT `enrollments`.`course_id` AS `course_id`, count(0) AS `enrolled_count` FROM `enrollments` WHERE `enrollments`.`status` in ('Enrolled','Pending') GROUP BY `enrollments`.`course_id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_privacy_policy`
--
DROP TABLE IF EXISTS `v_privacy_policy`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_privacy_policy`  AS SELECT `privacy_settings`.`field_key` AS `field_key`, max(case when `privacy_settings`.`role` = 'admin' then `privacy_settings`.`access_level` end) AS `admin`, max(case when `privacy_settings`.`role` = 'registrar' then `privacy_settings`.`access_level` end) AS `registrar`, max(case when `privacy_settings`.`role` = 'faculty' then `privacy_settings`.`access_level` end) AS `faculty`, max(case when `privacy_settings`.`role` = 'accounting' then `privacy_settings`.`access_level` end) AS `accounting`, max(case when `privacy_settings`.`role` = 'student' then `privacy_settings`.`access_level` end) AS `student` FROM `privacy_settings` GROUP BY `privacy_settings`.`field_key` ORDER BY `privacy_settings`.`field_key` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_students_due_anonymization`
--
DROP TABLE IF EXISTS `v_students_due_anonymization`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_students_due_anonymization`  AS SELECT `sal`.`id` AS `id`, `sal`.`student_id` AS `student_id`, `sal`.`student_number` AS `student_number`, `sal`.`full_name` AS `full_name`, `sal`.`program` AS `program`, `sal`.`last_active_year` AS `last_active_year`, `sal`.`archived_at` AS `archived_at`, `sal`.`scheduled_anonymize_at` AS `scheduled_anonymize_at`, to_days(`sal`.`scheduled_anonymize_at`) - to_days(curdate()) AS `days_remaining` FROM `student_archive_log` AS `sal` WHERE `sal`.`is_anonymized` = 0 AND `sal`.`scheduled_anonymize_at` is not null ORDER BY `sal`.`scheduled_anonymize_at` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_student_guardian_contact`
--
DROP TABLE IF EXISTS `v_student_guardian_contact`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_guardian_contact`  AS SELECT `s`.`id` AS `student_id`, `s`.`student_number` AS `student_number`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`, `s`.`program` AS `program`, `s`.`year_level` AS `year_level`, `s`.`semester` AS `semester`, `s`.`enrollment_status` AS `enrollment_status`, `s`.`payment_status` AS `payment_status`, `u`.`email` AS `student_email`, `sg`.`guardian_name` AS `guardian_name`, `sg`.`relationship` AS `relationship`, `sg`.`contact` AS `guardian_phone`, `sg`.`email` AS `guardian_email` FROM ((`students` `s` join `users` `u` on(`u`.`id` = `s`.`user_id`)) left join `student_guardians` `sg` on(`sg`.`student_id` = `s`.`id` and `sg`.`is_emergency` = 1)) WHERE `s`.`archived_at` is null ORDER BY `s`.`last_name` ASC, `s`.`first_name` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `add_drop_requests`
--
ALTER TABLE `add_drop_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `fk_adr_course` (`course_id`),
  ADD KEY `fk_adr_enrollment` (`enrollment_id`),
  ADD KEY `fk_adr_processed_by` (`processed_by`);

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
-- Indexes for table `block_course_sections`
--
ALTER TABLE `block_course_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_block_course` (`block_id`,`course_id`),
  ADD KEY `idx_block` (`block_id`),
  ADD KEY `idx_course` (`course_id`);

--
-- Indexes for table `class_blocks`
--
ALTER TABLE `class_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_block` (`program`(100),`year_level`,`semester`(50),`block_code`),
  ADD KEY `idx_program_year` (`program`(100),`year_level`,`semester`(50));

--
-- Indexes for table `coe_requests`
--
ALTER TABLE `coe_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `unique_course_code` (`code`),
  ADD KEY `fk_course_faculty` (`faculty_id`);

--
-- Indexes for table `course_prerequisites`
--
ALTER TABLE `course_prerequisites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_course_prereq` (`course_id`,`prerequisite_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_prerequisite` (`prerequisite_id`);

--
-- Indexes for table `course_sections`
--
ALTER TABLE `course_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section` (`course_id`,`section_code`,`semester`,`school_year`),
  ADD KEY `fk_cs_course` (`course_id`),
  ADD KEY `fk_cs_faculty` (`faculty_id`),
  ADD KEY `fk_cs_room` (`room_id`);

--
-- Indexes for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_type` (`type`,`status`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_course` (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `enrollment_snapshots`
--
ALTER TABLE `enrollment_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_semester` (`semester`);

--
-- Indexes for table `exam_permits`
--
ALTER TABLE `exam_permits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_permit` (`student_id`,`exam_period`,`school_year`,`semester`),
  ADD UNIQUE KEY `uq_permit_identifier` (`permit_identifier`),
  ADD KEY `fk_ep_approved_by` (`approved_by`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `faculty_id` (`faculty_id`),
  ADD KEY `fk_faculty_user` (`user_id`);

--
-- Indexes for table `faculty_subjects`
--
ALTER TABLE `faculty_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_faculty_course` (`faculty_id`,`course_code`),
  ADD KEY `fk_fs_course` (`course_id`);

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
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `idx_ip_semester` (`student_id`,`semester`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_time` (`email`,`attempted_at`);

--
-- Indexes for table `login_otp`
--
ALTER TABLE `login_otp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`);

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
-- Indexes for table `payment_terms`
--
ALTER TABLE `payment_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_term` (`student_id`,`term`);

--
-- Indexes for table `privacy_access_log`
--
ALTER TABLE `privacy_access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_accessed` (`accessed_at`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_role` (`user_role`),
  ADD KEY `idx_field` (`field_key`);

--
-- Indexes for table `privacy_settings`
--
ALTER TABLE `privacy_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_field_role` (`field_key`,`role`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_field` (`field_key`);

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
-- Indexes for table `scholarship_pre_approvals`
--
ALTER TABLE `scholarship_pre_approvals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `claim_code` (`claim_code`);

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
-- Indexes for table `soa_snapshots`
--
ALTER TABLE `soa_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_semester` (`student_id`,`semester`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_student_program` (`program_id`),
  ADD KEY `idx_block_id` (`block_id`);

--
-- Indexes for table `student_archive_log`
--
ALTER TABLE `student_archive_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_schedule` (`scheduled_anonymize_at`,`is_anonymized`);

--
-- Indexes for table `student_grades`
--
ALTER TABLE `student_grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_grade` (`enrollment_id`,`term`),
  ADD UNIQUE KEY `uq_enrollment_term` (`enrollment_id`,`term`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `fk_sg_submitted_by` (`submitted_by`);

--
-- Indexes for table `student_guardians`
--
ALTER TABLE `student_guardians`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_guardian_student` (`student_id`);

--
-- Indexes for table `student_scholarships`
--
ALTER TABLE `student_scholarships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scholar_student` (`student_id`);

--
-- Indexes for table `subject_fee_log`
--
ALTER TABLE `subject_fee_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `sys_config`
--
ALTER TABLE `sys_config`
  ADD PRIMARY KEY (`config_key`);

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
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `fk_tor_evaluated_by` (`evaluated_by`);

--
-- Indexes for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_semester` (`student_id`,`semester`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `add_drop_window`
--
ALTER TABLE `add_drop_window`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=504;

--
-- AUTO_INCREMENT for table `block_course_sections`
--
ALTER TABLE `block_course_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_blocks`
--
ALTER TABLE `class_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `coe_requests`
--
ALTER TABLE `coe_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2044;

--
-- AUTO_INCREMENT for table `course_prerequisites`
--
ALTER TABLE `course_prerequisites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_sections`
--
ALTER TABLE `course_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `email_notifications`
--
ALTER TABLE `email_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=422;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1389;

--
-- AUTO_INCREMENT for table `enrollment_snapshots`
--
ALTER TABLE `enrollment_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_permits`
--
ALTER TABLE `exam_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `faculty_subjects`
--
ALTER TABLE `faculty_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `fee_config`
--
ALTER TABLE `fee_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=420;

--
-- AUTO_INCREMENT for table `login_otp`
--
ALTER TABLE `login_otp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=235;

--
-- AUTO_INCREMENT for table `payment_notices`
--
ALTER TABLE `payment_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=972;

--
-- AUTO_INCREMENT for table `payment_terms`
--
ALTER TABLE `payment_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `privacy_access_log`
--
ALTER TABLE `privacy_access_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `privacy_settings`
--
ALTER TABLE `privacy_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `program_courses`
--
ALTER TABLE `program_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1449;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `scholarship_pre_approvals`
--
ALTER TABLE `scholarship_pre_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `school_events`
--
ALTER TABLE `school_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=370;

--
-- AUTO_INCREMENT for table `soa_snapshots`
--
ALTER TABLE `soa_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- AUTO_INCREMENT for table `student_archive_log`
--
ALTER TABLE `student_archive_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_grades`
--
ALTER TABLE `student_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=262;

--
-- AUTO_INCREMENT for table `student_guardians`
--
ALTER TABLE `student_guardians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `student_scholarships`
--
ALTER TABLE `student_scholarships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subject_fee_log`
--
ALTER TABLE `subject_fee_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `term_payments`
--
ALTER TABLE `term_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tor_evaluations`
--
ALTER TABLE `tor_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3194;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `add_drop_requests`
--
ALTER TABLE `add_drop_requests`
  ADD CONSTRAINT `fk_adr_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE NO ACTION,
  ADD CONSTRAINT `fk_adr_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_adr_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_adr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_course_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_prerequisites`
--
ALTER TABLE `course_prerequisites`
  ADD CONSTRAINT `fk_cp_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_prereq` FOREIGN KEY (`prerequisite_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `course_sections`
--
ALTER TABLE `course_sections`
  ADD CONSTRAINT `fk_cs_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cs_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cs_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `fk_enroll_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE NO ACTION,
  ADD CONSTRAINT `fk_enroll_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_permits`
--
ALTER TABLE `exam_permits`
  ADD CONSTRAINT `exam_permits_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ep_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `faculty`
--
ALTER TABLE `faculty`
  ADD CONSTRAINT `fk_faculty_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `faculty_subjects`
--
ALTER TABLE `faculty_subjects`
  ADD CONSTRAINT `fk_fs_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fs_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD CONSTRAINT `fk_ip_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD CONSTRAINT `fk_pl_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_notices`
--
ALTER TABLE `payment_notices`
  ADD CONSTRAINT `fk_pn_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  ADD CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_terms`
--
ALTER TABLE `payment_terms`
  ADD CONSTRAINT `fk_term_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `program_courses`
--
ALTER TABLE `program_courses`
  ADD CONSTRAINT `fk_pc_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pc_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_grades`
--
ALTER TABLE `student_grades`
  ADD CONSTRAINT `fk_sg_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE NO ACTION,
  ADD CONSTRAINT `fk_sg_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sg_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sg_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_guardians`
--
ALTER TABLE `student_guardians`
  ADD CONSTRAINT `fk_guardian_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_scholarships`
--
ALTER TABLE `student_scholarships`
  ADD CONSTRAINT `fk_scholar_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tor_evaluations`
--
ALTER TABLE `tor_evaluations`
  ADD CONSTRAINT `fk_tor_evaluated_by` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tor_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  ADD CONSTRAINT `fk_tf_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

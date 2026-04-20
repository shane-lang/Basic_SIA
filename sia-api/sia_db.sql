-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 20, 2026 at 02:53 PM
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
(8, '2026-04-08 01:42:00', '2026-04-09 01:42:00', '', 0, NULL, '2026-04-07 17:42:37'),
(9, '2026-04-09 01:42:00', '2026-04-10 01:42:00', '', 1, NULL, '2026-04-08 20:03:13');

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
(580, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 220, 'Full payment verified for student ID 220 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 15:01:56'),
(581, 4, 'registrar@example.com', 'registrar', 'CREATE_BLOCK', 'class_blocks', 8, 'Auto-created block BSIT-1A for Bachelor of Science in Information Technology 1st Year (2nd Semester, AY 2027-2028) on registration confirm', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 15:02:08'),
(582, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 220, 'Student 220 auto-assigned to block BSIT-1A (ID 8) [new block created] — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 15:02:08'),
(583, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 220, 'Registration confirmed for Shane Gongora (STU-2026-0002). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 15:02:08'),
(584, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 674, 'Deleted course: GE-NST013 - National Service Training Program 1', '{\"id\":\"674\",\"code\":\"GE-NST013\",\"name\":\"National Service Training Program 1\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"\",\"department\":\"Business Management Department (BMD)\",\"program\":\"Bachelor of Science in Real Estate Management\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 09:03:52\",\"updated_at\":\"2026-03-12 00:27:10\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:36:22'),
(585, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 684, 'Deleted course: GE-NST023 - National Service Training Program 2', '{\"id\":\"684\",\"code\":\"GE-NST023\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"2nd Semester\",\"description\":\"\",\"department\":\"Business Management Department (BMD)\",\"program\":\"Bachelor of Science in Real Estate Management\",\"year_level\":\"1st Year\",\"created_at\":\"2026-03-05 09:03:52\",\"updated_at\":\"2026-03-12 00:27:10\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:36:35'),
(586, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 222, 'Full payment verified for student ID 222 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 18:13:43'),
(587, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 222, 'Student 222 auto-assigned to block BSIT-1A (ID 8) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 18:14:35'),
(588, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 222, 'Registration confirmed for Shane Gongora (STU-2026-0004). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 18:14:35'),
(589, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 2046, 'Deleted course: 33 - 33', '{\"id\":\"2046\",\"code\":\"33\",\"name\":\"33\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"34\",\"department\":\"Collge Diploma\",\"program\":\"3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)\",\"year_level\":\"Year 2\",\"created_at\":\"2026-04-05 21:15:55\",\"updated_at\":\"2026-04-05 21:15:55\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 18:50:41'),
(590, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 2047, 'Deleted course: 333 - 3333', '{\"id\":\"2047\",\"code\":\"333\",\"name\":\"3333\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"2nd Semester\",\"description\":\"3333\",\"department\":\"Collge Diploma\",\"program\":\"3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)\",\"year_level\":\"Year 1\",\"created_at\":\"2026-04-05 21:16:12\",\"updated_at\":\"2026-04-05 21:16:12\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 18:50:43'),
(591, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 2045, 'Deleted course: 222 - 22', '{\"id\":\"2045\",\"code\":\"222\",\"name\":\"22\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"22\",\"department\":\"Collge Diploma\",\"program\":\"3-Yrs. Diploma in Travel and Hospitality Management Technology (Leading to BSHM)\",\"year_level\":\"Year 1\",\"created_at\":\"2026-04-05 21:14:53\",\"updated_at\":\"2026-04-05 21:14:53\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 18:50:46'),
(592, 2, 'admin@example.com', 'admin', 'DELETE_COURSE', 'course', 2044, 'Deleted course: SSDD - we', '{\"id\":\"2044\",\"code\":\"SSDD\",\"name\":\"we\",\"credits\":\"3\",\"faculty_id\":null,\"capacity\":\"40\",\"semester\":\"1st Semester\",\"description\":\"wew\",\"department\":\"Collge Diploma\",\"program\":\"3-Yrs. Diploma in Travel and Hospitality Management Technology (Leading to BSHM)\",\"year_level\":\"Year 1\",\"created_at\":\"2026-04-05 21:03:32\",\"updated_at\":\"2026-04-05 21:03:32\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"is_general\":\"0\",\"is_lab\":\"0\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 18:50:48'),
(593, 237, 'shane1@gmail.com', 'student', 'RE_ENROLL', 'student', 220, 'Student 220 re-enrolled: 1st Year 2nd Semester → 2nd Year 1st Semester AY 2028-2029 (type: New → Old)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 19:26:36'),
(594, 237, 'shane1@gmail.com', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 220, 'Payment plan updated to \'full\' (Cash) for student ID 220', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 19:26:40'),
(595, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 220, 'Full payment verified for student ID 220 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 19:27:00'),
(596, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 220, 'Registration confirmed for Shane Gongora (STU-2026-0002). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 19:27:15'),
(597, 237, 'shane1@gmail.com', 'student', 'SUBMIT_ADD_DROP', 'enrollment', 3, 'Add request submitted by student ID 220 for course ID 3. Reason: 123', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 20:07:00'),
(598, 3, 'accounting@example.com', 'accounting', 'ACCOUNTING_REVIEW_ADD_DROP', 'add_drop_requests', 23, 'Add/Drop request #23 Approved by Accounting. Fee impact: ₱2,139.00. New total: ₱29,248.00', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 20:17:51'),
(599, 4, 'registrar@example.com', 'registrar', 'PROCESS_ADD_DROP', 'enrollment', 23, 'Add/Drop request #23 (Add course 3 for student 220) Approved by registrar.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 20:17:57'),
(600, 3, 'accounting@example.com', 'accounting', 'CREATE_SCHOLARSHIP_PREAPPROVAL', 'scholarship_pre_approvals', 5, 'Pre-approval created: Full Scholarship by olongapo. Code: SCH-NQERYFCG', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 09:21:19'),
(601, 3, 'accounting@example.com', 'accounting', 'APPROVE_SCHOLARSHIP', 'student', 224, 'Scholarship approved: Full Scholarship ₱31,387.00 by accounting@example.com (Full Tuition)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 10:10:41'),
(602, 3, 'accounting@example.com', 'accounting', 'APPROVE_SCHOLARSHIP', 'student', 226, 'Scholarship approved: CHED — Tertiary Education Subsidy (TES) ₱10,000.00 by accounting@example.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 16:18:41'),
(603, 3, 'accounting@example.com', 'accounting', 'REJECT_SCHOLARSHIP', 'student', 227, 'Scholarship rejected: CHED — Tertiary Education Subsidy (TES). Reason: G', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 16:43:51'),
(604, 2, 'admin@example.com', 'admin', 'CREATE_COURSE', 'course', 2048, 'Created course: 12 - 12', NULL, '{\"code\":\"12\",\"name\":\"12\",\"program\":\"3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 14:23:46'),
(605, 2, 'admin@example.com', 'admin', 'CREATE_COURSE', 'course', 2049, 'Created course: 1 - 1', NULL, '{\"code\":\"1\",\"name\":\"1\",\"program\":\"3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 14:24:23'),
(606, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 230, 'Full payment verified for student ID 230 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 15:10:00'),
(607, 4, 'registrar@example.com', 'registrar', 'CREATE_BLOCK', 'class_blocks', 9, 'Auto-created block DTTT-1A for 3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM) 1st Year (1st Semester, AY 2028-2029) on registration confirm', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 15:10:20'),
(608, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 230, 'Student 230 auto-assigned to block DTTT-1A (ID 9) [new block created] — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 15:10:20'),
(609, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 230, 'Registration confirmed for Shane Nodado (STU-2026-0012). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 15:10:20'),
(610, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 231, 'Full payment verified for student ID 231 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 19:27:20'),
(611, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 231, 'Student 231 auto-assigned to block DTTT-1A (ID 9) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 19:32:14'),
(612, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 231, 'Registration confirmed for Shane Gongora (STU-2026-0001). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 19:32:15'),
(613, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 234, 'Full payment verified for student ID 234 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 20:21:50'),
(614, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 234, 'Student 234 auto-assigned to block DTTT-1A (ID 9) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 20:22:03'),
(615, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 234, 'Registration confirmed for Shane Nodado (STU-2026-0004). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 20:22:03'),
(616, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 234, 'Registration confirmed for Shane Nodado (STU-2026-0004). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 20:22:51'),
(617, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 236, 'Full payment verified for student ID 236 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 20:43:13'),
(618, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 236, 'Student 236 auto-assigned to block DTTT-1A (ID 9) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 20:47:13'),
(619, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 236, 'Registration confirmed for Shane Nodado (STU-2026-0006). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 20:47:13'),
(620, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 237, 'Full payment verified for student ID 237 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 20:51:28'),
(621, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 238, 'Full payment verified for student ID 238 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 21:11:27'),
(622, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 238, 'Student 238 auto-assigned to block DTTT-1A (ID 9) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 21:11:44'),
(623, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 238, 'Registration confirmed for Shane Nodado (STU-2026-0001). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 21:11:44'),
(624, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 239, 'Full payment verified for student ID 239 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 21:43:17'),
(625, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 242, 'Full payment verified for student ID 242 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 22:05:18'),
(626, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 239, 'Student 239 auto-assigned to block DTTT-1A (ID 9) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 22:17:31'),
(627, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 239, 'Registration confirmed for Shane Nodado (STU-2026-0002). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 22:17:31'),
(628, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 242, 'Student 242 auto-assigned to block DTTT-1A (ID 9) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 22:17:40'),
(629, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 242, 'Registration confirmed for Shane Nodado (STU-2026-0005). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 22:17:41'),
(630, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 243, 'Full payment verified for student ID 243 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-10 22:21:06'),
(631, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 253, 'Downpayment verified ₱5,187.50 for student ID 253 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 09:04:51'),
(632, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 253, 'Student 253 auto-assigned to block DTTT-1A (ID 9) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 09:10:10'),
(633, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 253, 'Registration confirmed for Shane Nodado (STU-2026-0004). Notes:  | Block: DTTT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 09:10:10'),
(634, 2, 'admin@example.com', 'admin', 'UPDATE_STAFF_ACCOUNT', 'staff_profiles', 4, 'Updated registrar account: Registrar Admin (registrar@example.com)', '{\"id\":\"3\",\"user_id\":\"4\",\"first_name\":\"Registrar\",\"last_name\":\"Admin\",\"middle_name\":null,\"phone\":null,\"department\":null,\"position\":null,\"created_at\":\"2026-03-19 03:24:57\",\"updated_at\":\"2026-03-19 03:24:57\"}', '{\"first_name\":\"Registrar\",\"last_name\":\"Admin\",\"email\":\"registrar@example.com\",\"phone\":\"\",\"position\":\"Dept Head\",\"department\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 14:46:50'),
(635, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 254, 'Full payment verified for student ID 254 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 15:03:43'),
(636, 4, 'registrar@example.com', 'registrar', 'CREATE_BLOCK', 'class_blocks', 10, 'Auto-created block BSIT-1A for Bachelor of Science in Information Technology 1st Year (1st Semester, AY 2028-2029) on registration confirm', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 15:06:57'),
(637, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 254, 'Student 254 auto-assigned to block BSIT-1A (ID 10) [new block created] — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 15:06:57'),
(638, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 254, 'Registration confirmed for Dave Cuevas (STU-2026-0005). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 15:06:57'),
(639, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 256, 'Downpayment verified ₱6,073.50 for student ID 256 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 15:45:30'),
(640, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 265, 'Payment plan updated to \'installment\' (Cash) for student ID 265', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:50:54'),
(641, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 265, 'Payment plan updated to \'installment\' (Cash) for student ID 265', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:50:56'),
(642, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 266, 'Payment plan updated to \'installment\' (Cash) for student ID 266', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:53:50'),
(643, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 266, 'Payment plan updated to \'installment\' (Cash) for student ID 266', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 16:53:51'),
(644, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 267, 'Payment plan updated to \'installment\' (Cash) for student ID 267', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 19:49:46'),
(645, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 267, 'Payment plan updated to \'installment\' (Cash) for student ID 267', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 19:49:55'),
(646, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 268, 'Payment plan updated to \'installment\' (Cash) for student ID 268', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 19:55:06'),
(647, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 268, 'Payment plan updated to \'installment\' (Cash) for student ID 268', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 19:55:09'),
(648, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 269, 'Payment plan updated to \'installment\' (Cash) for student ID 269', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 20:03:25'),
(649, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 269, 'Downpayment verified ₱6,430.00 for student ID 269 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 20:05:50'),
(650, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 268, 'Downpayment verified ₱6,430.00 for student ID 268 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 20:27:34'),
(651, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 267, 'Downpayment verified ₱6,430.00 for student ID 267 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 20:27:40'),
(652, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 269, 'Student 269 auto-assigned to block BSIT-1A (ID 10) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:49:29'),
(653, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 269, 'Registration confirmed for Juan Dela Cruz (STU-2026-0005). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:49:29'),
(654, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 268, 'Student 268 auto-assigned to block BSIT-1A (ID 10) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:49:43'),
(655, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 268, 'Registration confirmed for Juan Dela Cruz (STU-2026-0004). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:49:43'),
(656, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 270, 'Payment plan updated to \'installment\' (Cash) for student ID 270', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:53:30'),
(657, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 270, 'Payment plan updated to \'installment\' (GCash) for student ID 270', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:53:33'),
(658, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 270, 'Payment plan updated to \'installment\' (GCash) for student ID 270', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:53:34'),
(659, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 271, 'Payment plan updated to \'installment\' (Cash) for student ID 271', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:54:54'),
(660, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 272, 'Payment plan updated to \'installment\' (Cash) for student ID 272', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:56:33'),
(661, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 272, 'Payment plan updated to \'installment\' (Cash) for student ID 272', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 21:56:35'),
(662, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 273, 'Payment plan updated to \'installment\' (Cash) for student ID 273', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:01:39'),
(663, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 273, 'Downpayment verified ₱6,073.50 for student ID 273 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:01:56'),
(664, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 275, 'Payment plan updated to \'installment\' (Cash) for student ID 275', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:04:01'),
(665, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 275, 'Payment plan updated to \'installment\' (GCash) for student ID 275', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:04:02'),
(666, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 275, 'Payment plan updated to \'installment\' (GCash) for student ID 275', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:04:03'),
(667, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 275, 'Downpayment verified ₱5,895.25 for student ID 275 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:04:54'),
(668, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 276, 'Payment plan updated to \'installment\' (Cash) for student ID 276', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:11:12'),
(669, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 276, 'Payment plan updated to \'installment\' (GCash) for student ID 276', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:11:20'),
(670, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 276, 'Payment plan updated to \'installment\' (GCash) for student ID 276', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:11:21'),
(671, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 276, 'Downpayment verified ₱5,360.50 for student ID 276 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:12:19'),
(672, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 277, 'Payment plan updated to \'installment\' (Cash) for student ID 277', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:17:28'),
(673, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 277, 'Payment plan updated to \'installment\' (GCash) for student ID 277', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:17:29'),
(674, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 277, 'Payment plan updated to \'installment\' (GCash) for student ID 277', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:17:31'),
(675, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 277, 'Downpayment verified ₱5,895.25 for student ID 277 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-11 22:17:43'),
(676, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 278, 'Payment plan updated to \'installment\' (Cash) for student ID 278', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:26:23'),
(677, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 278, 'Payment plan updated to \'installment\' (GCash) for student ID 278', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:26:31'),
(678, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 278, 'Payment plan updated to \'installment\' (GCash) for student ID 278', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:26:34'),
(679, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 279, 'Payment plan updated to \'installment\' (Cash) for student ID 279', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:36:37'),
(680, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 279, 'Payment plan updated to \'installment\' (GCash) for student ID 279', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:36:38'),
(681, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 279, 'Payment plan updated to \'installment\' (GCash) for student ID 279', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:36:51'),
(682, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 279, 'Downpayment verified ₱5,896.00 for student ID 279 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:37:16'),
(683, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 280, 'Payment plan updated to \'installment\' (Cash) for student ID 280', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:51:50'),
(684, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 280, 'Payment plan updated to \'installment\' (GCash) for student ID 280', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:51:54'),
(685, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 280, 'Payment plan updated to \'installment\' (GCash) for student ID 280', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:51:58'),
(686, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 280, 'Downpayment verified ₱5,896.00 for student ID 280 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 10:52:15'),
(687, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 281, 'Payment plan updated to \'installment\' (Cash) for student ID 281', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:02:35'),
(688, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 281, 'Payment plan updated to \'full\' (Cash) for student ID 281', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:02:37'),
(689, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 281, 'Payment plan updated to \'full\' (GCash) for student ID 281', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:02:40'),
(690, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 281, 'Payment plan updated to \'full\' (GCash) for student ID 281', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:02:41'),
(691, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 281, 'Full payment verified for student ID 281 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:02:56'),
(692, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 282, 'Payment plan updated to \'installment\' (Cash) for student ID 282', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:18:09'),
(693, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 282, 'Payment plan updated to \'full\' (Cash) for student ID 282', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:18:20'),
(694, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 282, 'Payment plan updated to \'full\' (GCash) for student ID 282', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:18:22'),
(695, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 282, 'Payment plan updated to \'installment\' (GCash) for student ID 282', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:18:24'),
(696, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 282, 'Payment plan updated to \'installment\' (Cash) for student ID 282', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:18:26'),
(697, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 282, 'Payment plan updated to \'installment\' (Cash) for student ID 282', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:18:26'),
(698, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 282, 'Downpayment verified ₱5,895.25 for student ID 282 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:18:48'),
(699, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 283, 'Payment plan updated to \'installment\' (Cash) for student ID 283', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:24:24'),
(700, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 283, 'Payment plan updated to \'installment\' (Cash) for student ID 283', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:24:28'),
(701, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 283, 'Downpayment verified ₱5,895.25 for student ID 283 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:24:46'),
(702, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 284, 'Payment plan updated to \'installment\' (Cash) for student ID 284', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:34:56'),
(703, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 284, 'Payment plan updated to \'full\' (Cash) for student ID 284', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:34:58'),
(704, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 284, 'Payment plan updated to \'full\' (Cash) for student ID 284', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:35:00'),
(705, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 284, 'Full payment verified for student ID 284 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:35:11'),
(706, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 285, 'Payment plan updated to \'full\' (Cash) for student ID 285', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:41:30'),
(707, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 285, 'Full payment verified for student ID 285 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:41:40'),
(708, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 285, 'Student 285 auto-assigned to block BSIT-1A (ID 10) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:42:10'),
(709, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 285, 'Registration confirmed for Juan Dela Cruz (STU-2026-0001). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:42:10'),
(710, 314, 'shan@example.com', 'student', 'RE_ENROLL', 'student', 285, 'Student 285 re-enrolled: 1st Year 1st Semester → 1st Year 2nd Semester, AY 2028-2029 (type: New → Old)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:44:18'),
(711, 314, 'shan@example.com', 'student', 'UPDATE_PAYMENT_PLAN', 'student', 285, 'Payment plan updated to \'full\' (Cash) for student ID 285', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:44:31'),
(712, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 285, 'Full payment verified for student ID 285 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:45:31'),
(713, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 285, 'Registration confirmed for Juan Dela Cruz (STU-2026-0001). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:45:47'),
(714, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 289, 'Payment rejected for student ID 289. Log: 319. Reason: mali yung reference', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 14:32:27'),
(715, 4, 'registrar@example.com', 'registrar', 'UPDATE_STUDENT_INFO', 'student', 287, 'Registrar updated personal info for student ID 287.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 14:46:18'),
(716, 4, 'registrar@example.com', 'registrar', 'UPDATE_STUDENT_INFO', 'student', 289, 'Registrar updated personal info for student ID 289.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 14:46:57'),
(717, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 289, 'Payment rejected for student ID 289. Log: 320. Reason: invalid', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 14:51:14'),
(718, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 289, 'Payment rejected for student ID 289. Log: 321. Reason: invalid', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 14:52:16'),
(719, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 289, 'Payment rejected for student ID 289. Log: 322. Reason: invalid gcash', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 14:58:14'),
(720, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 289, 'Downpayment verified ₱7,143.00 for student ID 289 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:13:04'),
(721, 4, 'registrar@example.com', 'registrar', 'CREATE_BLOCK', 'class_blocks', 11, 'Auto-created block GAS-1A for General Academic Strand (GAS) Academic Track (2nd Semester, AY 2028-2029) on registration confirm', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:13:30'),
(722, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 288, 'Student 288 auto-assigned to block GAS-1A (ID 11) [new block created] — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:13:30'),
(723, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 288, 'Registration confirmed for Juan Dela Cruz (STU-2026-0004). Notes:  | Block: GAS-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:13:30'),
(724, 4, 'registrar@example.com', 'registrar', 'CREATE_BLOCK', 'class_blocks', 12, 'Auto-created block GAS-11A for General Academic Strand (GAS) Grade 11 (2nd Semester, AY 2028-2029) on registration confirm', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:14:04'),
(725, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 289, 'Student 289 auto-assigned to block GAS-11A (ID 12) [new block created] — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:14:04'),
(726, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 289, 'Registration confirmed for Juan suon (STU-2026-0005). Notes:  | Block: GAS-11A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:14:04'),
(727, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 286, 'Payment rejected for student ID 286. Log: 316. Reason: dw', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:21:04');
INSERT INTO `audit_logs` (`id`, `user_id`, `user_email`, `user_role`, `action`, `target_type`, `target_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(728, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 290, 'Full payment verified for student ID 290 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:21:39'),
(729, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 290, 'Student 290 auto-assigned to block BSIT-1A (ID 8) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:22:21'),
(730, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 290, 'Registration confirmed for Juan Dela Cruz (STU-2026-0006). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:22:21'),
(731, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 291, 'Full payment verified for student ID 291 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:26:54'),
(732, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 291, 'Student 291 auto-assigned to block BSIT-1A (ID 8) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:27:30'),
(733, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 291, 'Registration confirmed for Juan Dela Cruz (STU-2026-0001). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:27:30'),
(734, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 292, 'Payment plan updated to \'installment\' (Cash) for student ID 292', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:38:05'),
(735, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 292, 'Payment plan updated to \'installment\' (Cash) for student ID 292', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:38:07'),
(736, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 292, 'Downpayment verified ₱5,895.25 for student ID 292 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:38:21'),
(737, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 292, 'Student 292 auto-assigned to block BSIT-1A (ID 8) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:38:49'),
(738, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 292, 'Registration confirmed for Juan Dela Cruz (STU-2026-0002). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:38:49'),
(739, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 293, 'Payment plan updated to \'installment\' (Cash) for student ID 293', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:59:40'),
(740, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 293, 'Payment plan updated to \'full\' (Cash) for student ID 293', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:59:41'),
(741, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 293, 'Payment plan updated to \'installment\' (Cash) for student ID 293', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:59:42'),
(742, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 293, 'Payment plan updated to \'installment\' (GCash) for student ID 293', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:59:44'),
(743, NULL, 'system', '', 'UPDATE_PAYMENT_PLAN', 'student', 293, 'Payment plan updated to \'installment\' (GCash) for student ID 293', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 15:59:45'),
(744, 3, 'accounting@example.com', 'accounting', 'REJECT_PAYMENT', 'student', 293, 'Payment rejected for student ID 293. Log: 327. Reason: invalid', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 16:00:07'),
(745, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 293, 'Downpayment verified ₱5,361.00 for student ID 293 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 16:00:57'),
(746, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 293, 'Student 293 auto-assigned to block BSIT-1A (ID 8) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 16:01:16'),
(747, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 293, 'Registration confirmed for Juan Dela Cruz (STU-2026-0003). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 16:01:16'),
(748, 3, 'accounting@example.com', 'accounting', 'APPROVE_SCHOLARSHIP', 'student', 294, 'Scholarship approved: CHED — Tertiary Education Subsidy (TES) ₱10,000.00 by accounting@example.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 19:36:23'),
(749, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 294, 'Downpayment verified ₱3,930.00 for student ID 294 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 19:36:30'),
(750, 4, 'registrar@example.com', 'registrar', 'CREATE_BLOCK', 'class_blocks', 13, 'Auto-created block BSCA-1A for Bachelor of Science in Customs Administration 1st Year (2nd Semester, AY 2028-2029) on registration confirm', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 19:37:14'),
(751, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 294, 'Student 294 auto-assigned to block BSCA-1A (ID 13) [new block created] — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 19:37:14'),
(752, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 294, 'Registration confirmed for Juan Dela Cruz (STU-2026-0004). Notes:  | Block: BSCA-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 19:37:14'),
(753, 3, 'accounting@example.com', 'accounting', 'VERIFY_PAYMENT', 'student', 300, 'Downpayment verified ₱25,683.00 for student ID 300 — awaiting registrar final approval', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 21:42:41'),
(754, 4, 'registrar@example.com', 'registrar', 'AUTO_ASSIGN_BLOCK', 'students', 300, 'Student 300 auto-assigned to block BSIT-1A (ID 8) — triggered by confirm_registration', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 21:45:27'),
(755, 4, 'registrar@example.com', 'registrar', 'CONFIRM_REGISTRATION', 'student', 300, 'Registration confirmed for ihosho NOniso (STU-2026-0010). Notes:  | Block: BSIT-1A', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 21:45:28');

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
(8, 'BSIT-1A', 'Bachelor of Science in Information Technology', '1st Year', '2nd Semester, AY 2027-2028', '2027-2028', 40, 1, '2026-04-08 15:02:08', '2026-04-08 15:02:08'),
(9, 'DTTT-1A', '3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)', '1st Year', '1st Semester, AY 2028-2029', '2028-2029', 40, 1, '2026-04-10 15:10:20', '2026-04-10 15:10:20'),
(10, 'BSIT-1A', 'Bachelor of Science in Information Technology', '1st Year', '1st Semester, AY 2028-2029', '2028-2029', 40, 1, '2026-04-11 15:06:57', '2026-04-11 15:06:57'),
(11, 'GAS-1A', 'General Academic Strand (GAS)', 'Academic Track', '2nd Semester, AY 2028-2029', '2028-2029', 40, 1, '2026-04-12 15:13:30', '2026-04-12 15:13:30'),
(12, 'GAS-11A', 'General Academic Strand (GAS)', 'Grade 11', '2nd Semester, AY 2028-2029', '2028-2029', 40, 1, '2026-04-12 15:14:04', '2026-04-12 15:14:04'),
(13, 'BSCA-1A', 'Bachelor of Science in Customs Administration', '1st Year', '2nd Semester, AY 2028-2029', '2028-2029', 40, 1, '2026-04-12 19:37:14', '2026-04-12 19:37:14');

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
(70, 221, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 0, '2026-04-07 20:48:13', 'COE-202604-0001', '2nd Semester', '2027-2028', '2026-04-07 12:48:13', '2026-04-07 12:48:13'),
(71, 220, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-08 23:02:08', 'COE-202604-0002', '2nd Semester', '2027-2028', '2026-04-08 15:02:08', '2026-04-08 15:02:08'),
(72, 222, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-09 02:14:35', 'COE-202604-0003', '2nd Semester', '2027-2028', '2026-04-08 18:14:35', '2026-04-08 18:14:35'),
(73, 220, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-09 03:27:15', 'COE-202604-0004', '1st Semester', '2028-2029', '2026-04-08 19:27:15', '2026-04-08 19:27:15'),
(74, 230, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-10 23:10:20', 'COE-202604-0005', '1st Semester', '2028-2029', '2026-04-10 15:10:20', '2026-04-10 15:10:20'),
(75, 231, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-11 03:32:15', 'COE-202604-0006', '1st Semester', '2028-2029', '2026-04-10 19:32:15', '2026-04-10 19:32:15'),
(76, 234, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-11 04:22:03', 'COE-202604-0007', '1st Semester', '2028-2029', '2026-04-10 20:22:03', '2026-04-10 20:22:03'),
(77, 236, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-11 04:47:13', 'COE-202604-0008', '1st Semester', '2028-2029', '2026-04-10 20:47:13', '2026-04-10 20:47:13'),
(78, 238, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-11 05:11:44', 'COE-202604-0009', '1st Semester', '2028-2029', '2026-04-10 21:11:44', '2026-04-10 21:11:44'),
(79, 242, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 0, '2026-04-11 06:17:14', 'COE-202604-0010', '1st Semester', '2028-2029', '2026-04-10 22:17:14', '2026-04-10 22:17:14'),
(80, 239, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-11 06:17:31', 'COE-202604-0011', '1st Semester', '2028-2029', '2026-04-10 22:17:31', '2026-04-10 22:17:31'),
(81, 251, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 0, '2026-04-11 16:29:15', 'COE-202604-0012', '1st Semester', '2028-2029', '2026-04-11 08:29:15', '2026-04-11 08:29:15'),
(82, 253, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-11 17:10:10', 'COE-202604-0013', '1st Semester', '2028-2029', '2026-04-11 09:10:10', '2026-04-11 09:10:10'),
(83, 254, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-11 23:06:57', 'COE-202604-0014', '1st Semester', '2028-2029', '2026-04-11 15:06:57', '2026-04-11 15:06:57'),
(84, 269, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 0, '2026-04-12 05:16:13', 'COE-202604-0015', '1st Semester', '2028-2029', '2026-04-11 21:16:13', '2026-04-11 21:16:13'),
(85, 268, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-12 05:49:43', 'COE-202604-0016', '1st Semester', '2028-2029', '2026-04-11 21:49:43', '2026-04-11 21:49:43'),
(86, 285, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-12 19:42:10', 'COE-202604-0017', '1st Semester', '2028-2029', '2026-04-12 11:42:10', '2026-04-12 11:42:10'),
(87, 285, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-12 19:45:47', 'COE-202604-0018', '2nd Semester', '2028-2029', '2026-04-12 11:45:47', '2026-04-12 11:45:47'),
(88, 288, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-12 23:13:30', 'COE-202604-0019', '2nd Semester', '2028-2029', '2026-04-12 15:13:30', '2026-04-12 15:13:30'),
(89, 289, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-12 23:14:04', 'COE-202604-0020', '2nd Semester', '2028-2029', '2026-04-12 15:14:04', '2026-04-12 15:14:04'),
(90, 290, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-12 23:22:21', 'COE-202604-0021', '2nd Semester', '2028-2029', '2026-04-12 15:22:21', '2026-04-12 15:22:21'),
(91, 291, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-12 23:27:30', 'COE-202604-0022', '2nd Semester', '2028-2029', '2026-04-12 15:27:30', '2026-04-12 15:27:30'),
(92, 292, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-12 23:38:49', 'COE-202604-0023', '2nd Semester', '2028-2029', '2026-04-12 15:38:49', '2026-04-12 15:38:49'),
(93, 293, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-13 00:01:16', 'COE-202604-0024', '2nd Semester', '2028-2029', '2026-04-12 16:01:16', '2026-04-12 16:01:16'),
(94, 294, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-13 03:37:14', 'COE-202604-0025', '2nd Semester', '2028-2029', '2026-04-12 19:37:14', '2026-04-12 19:37:14'),
(95, 300, 'General Purpose', 1, 'Approved', 'Auto-approved on enrollment confirmation', 4, '2026-04-13 05:45:28', 'COE-202604-0026', '2nd Semester', '2028-2029', '2026-04-12 21:45:28', '2026-04-12 21:45:28');

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
(7, 'PE1', 'Physical Education 1 (Aquatics)', 2, 143, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-04-11 14:45:42', 2, 0, 0, 0),
(8, 'NSTP1', 'National Service Training Program1', 3, 143, 40, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', '2026-04-11 14:45:42', 3, 0, 0, 0),
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
(675, 'BN-MGT013', 'Principles of Management', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(676, 'RE-REC013', 'Fundamentals of Real Estate Consulting', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(677, 'LW-BSN013', 'Law on Obligations and Contracts w/ Real Properties', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(678, 'GE-NSC023', 'Environment and Greenbuilding Technology', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(679, 'GE-ENG023', 'Grammar and Composition', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(680, 'RE-PAD013', 'Real Estate Planning and Development', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(681, 'RE-REB013', 'Real Estate Brokerage', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(682, 'GE-FIL023', 'Pagbasa at pagsulat Tungo Sa Pananaliksik', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(683, 'GE-PHE032', 'Individual and Team Sports', 2, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 2, 0, 0, 0),
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
(712, 'RE-ARD013', 'Appraisal Report and Data Gathering', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(713, 'RE-ESP013', 'Ethical Standards for Real Estate Practice', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0),
(714, 'RE-REE013', 'Real Estate Economics', 3, NULL, 40, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', '2026-03-11 16:27:10', 3, 0, 0, 0);
INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `faculty_id`, `capacity`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `updated_at`, `lec_units`, `lab_units`, `is_general`, `is_lab`) VALUES
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
(2043, 'GEALG12', 'algebra', 3, NULL, 40, '1st Semester', '', 'Academic Track', 'General Academic Strand (GAS)', 'Grade 11', '2026-03-30 18:48:41', '2026-03-30 18:53:22', 3, 0, 0, 0),
(2048, '12', '12', 3, NULL, 40, '1st Semester', '1', 'Collge Diploma', '3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)', 'Year 1', '2026-04-10 14:23:46', '2026-04-10 14:23:46', 3, 0, 0, 0),
(2049, '1', '1', 3, NULL, 40, '1st Semester', '1', 'Collge Diploma', '3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)', 'Year 1', '2026-04-10 14:24:23', '2026-04-10 14:24:23', 3, 0, 0, 0);

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
(524, 220, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-04-08 15:01:56'),
(525, 220, 'shane1@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2027-2028', 'sent', '', '2026-04-08 17:02:05', '2026-04-08 15:02:05'),
(526, 220, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0002)', 'failed', NULL, NULL, '2026-04-08 15:02:11'),
(527, 220, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2027-2028', 'sent', '', '2026-04-08 17:02:11', '2026-04-08 15:02:11'),
(528, 220, 'shane1@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2027-2028', 'sent', '', '2026-04-08 17:02:21', '2026-04-08 15:02:21'),
(529, 220, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2027-2028', 'sent', '', '2026-04-08 17:02:28', '2026-04-08 15:02:28'),
(530, 222, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱25,000.00)', 'pending', NULL, NULL, '2026-04-08 18:13:44'),
(531, 222, 'shanecarlo@yahoo.com', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2027-2028', 'sent', '', '2026-04-08 20:13:50', '2026-04-08 18:13:50'),
(532, 222, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 2nd Semester, AY 2027-2028', 'sent', '', '2026-04-08 20:13:54', '2026-04-08 18:13:54'),
(533, 222, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0004)', 'failed', NULL, NULL, '2026-04-08 18:14:37'),
(534, 222, 'shanecarlo@yahoo.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2027-2028', 'sent', '', '2026-04-08 20:14:47', '2026-04-08 18:14:47'),
(535, 222, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 2nd Semester, AY 2027-2028', 'sent', '', '2026-04-08 20:14:52', '2026-04-08 18:14:52'),
(536, 220, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-04-08 19:27:00'),
(537, 220, 'shane1@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester AY 2028-2029', 'sent', '', '2026-04-08 21:27:05', '2026-04-08 19:27:05'),
(538, 220, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester AY 2028-2029', 'sent', '', '2026-04-08 21:27:10', '2026-04-08 19:27:10'),
(539, 220, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0002)', 'failed', NULL, NULL, '2026-04-08 19:27:18'),
(540, 220, 'shane1@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester AY 2028-2029', 'sent', '', '2026-04-08 21:27:26', '2026-04-08 19:27:26'),
(541, 220, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester AY 2028-2029', 'sent', '', '2026-04-08 21:27:31', '2026-04-08 19:27:31'),
(542, 230, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱20,000.00)', 'pending', NULL, NULL, '2026-04-10 15:10:00'),
(543, 230, 'shane1@21gmail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 17:10:08', '2026-04-10 15:10:08'),
(544, 230, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 17:10:12', '2026-04-10 15:10:12'),
(545, 230, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Nodado (STU-2026-0012)', 'failed', NULL, NULL, '2026-04-10 15:10:22'),
(546, 230, 'shane1@21gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 17:10:35', '2026-04-10 15:10:35'),
(547, 230, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 17:10:41', '2026-04-10 15:10:41'),
(548, 231, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱20,000.00)', 'pending', NULL, NULL, '2026-04-10 19:27:20'),
(549, 231, 'shane@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 21:27:27', '2026-04-10 19:27:27'),
(550, 231, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 21:27:31', '2026-04-10 19:27:31'),
(551, 231, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Gongora (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-10 19:32:17'),
(552, 231, 'shane@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 21:32:26', '2026-04-10 19:32:26'),
(553, 231, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Gongora | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 21:32:31', '2026-04-10 19:32:31'),
(554, 234, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Full (₱20,000.00)', 'pending', NULL, NULL, '2026-04-10 20:21:50'),
(555, 234, 'shane22@example.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:21:56', '2026-04-10 20:21:56'),
(556, 234, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:22:01', '2026-04-10 20:22:01'),
(557, 234, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Nodado (STU-2026-0004)', 'failed', NULL, NULL, '2026-04-10 20:22:05'),
(558, 234, 'shane22@example.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:22:15', '2026-04-10 20:22:15'),
(559, 234, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:22:21', '2026-04-10 20:22:21'),
(560, 234, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Shane Nodado (STU-2026-0004)', 'failed', NULL, NULL, '2026-04-10 20:22:54'),
(561, 234, 'shane22@example.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:23:03', '2026-04-10 20:23:03'),
(562, 234, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:23:07', '2026-04-10 20:23:07'),
(563, 236, 'gongorashane22@gm12ail.com', 'soa', 'Payment Verified – Full (₱20,000.00)', 'pending', NULL, NULL, '2026-04-10 20:43:13'),
(564, 236, 'shane2223@example.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:43:19', '2026-04-10 20:43:19'),
(565, 236, 'gongorashane22@gm12ail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:43:24', '2026-04-10 20:43:24'),
(566, 236, 'gongorashane22@gm12ail.com', '', 'Enrollment Confirmed – Shane Nodado (STU-2026-0006)', 'failed', NULL, NULL, '2026-04-10 20:47:15'),
(567, 236, 'shane2223@example.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:47:25', '2026-04-10 20:47:25'),
(568, 236, 'gongorashane22@gm12ail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:47:31', '2026-04-10 20:47:31'),
(569, 237, 'gongorashan2e22@gmail.com', 'soa', 'Payment Verified – Full (₱10,000.00)', 'pending', NULL, NULL, '2026-04-10 20:51:29'),
(570, 237, 'shanes22232@example.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:51:34', '2026-04-10 20:51:34'),
(571, 237, 'gongorashan2e22@gmail.com', 'soa', 'Statement of Account — Shane Gongora | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 22:51:39', '2026-04-10 20:51:39'),
(572, 238, 'gongoras2hane22@gmail.com', 'soa', 'Payment Verified – Full (₱20,000.00)', 'pending', NULL, NULL, '2026-04-10 21:11:27'),
(573, 238, 'shane@example.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 23:11:32', '2026-04-10 21:11:32'),
(574, 238, 'gongoras2hane22@gmail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 23:11:37', '2026-04-10 21:11:37'),
(575, 238, 'gongoras2hane22@gmail.com', '', 'Enrollment Confirmed – Shane Nodado (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-10 21:11:46'),
(576, 239, 'gongorashane22@gmail.com3', 'soa', 'Payment Verified – Full (₱5,000.00)', 'pending', NULL, NULL, '2026-04-10 21:43:17'),
(577, 239, 'shane1@example.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 23:43:22', '2026-04-10 21:43:22'),
(578, 239, 'gongorashane22@gmail.com3', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-10 23:43:26', '2026-04-10 21:43:26'),
(579, 242, 'gongorasha2ne22@gmail.com', 'soa', 'Payment Verified – Full (₱20,000.00)', 'pending', NULL, NULL, '2026-04-10 22:05:19'),
(580, 242, 'shaneS2s@example.comss', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 00:05:24', '2026-04-10 22:05:24'),
(581, 242, 'gongorasha2ne22@gmail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 00:05:29', '2026-04-10 22:05:29'),
(582, 239, 'gongorashane22@gmail.com3', '', 'Enrollment Confirmed – Shane Nodado (STU-2026-0002)', 'failed', NULL, NULL, '2026-04-10 22:17:33'),
(583, 242, 'gongorasha2ne22@gmail.com', '', 'Enrollment Confirmed – Shane Nodado (STU-2026-0005)', 'failed', NULL, NULL, '2026-04-10 22:17:43'),
(584, 239, 'shane1@example.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 00:17:43', '2026-04-10 22:17:43'),
(585, 239, 'gongorashane22@gmail.com3', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 00:17:48', '2026-04-10 22:17:48'),
(586, 242, 'shaneS2s@example.comss', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 00:17:50', '2026-04-10 22:17:50'),
(587, 242, 'gongorasha2ne22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 00:17:55', '2026-04-10 22:17:55'),
(588, 243, 'gongorashaane22@gmail.com', 'soa', 'Payment Verified – Full (₱20,000.00)', 'pending', NULL, NULL, '2026-04-10 22:21:07'),
(589, 243, 'shanesS2s@example.comss', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 00:21:12', '2026-04-10 22:21:12'),
(590, 243, 'gongorashaane22@gmail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 00:21:18', '2026-04-10 22:21:18'),
(591, 253, 'gongorasha2ne22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,187.50)', 'pending', NULL, NULL, '2026-04-11 09:04:51'),
(592, 253, 'shane3@example.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 11:04:59', '2026-04-11 09:04:59'),
(593, 253, 'gongorasha2ne22@gmail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 11:05:05', '2026-04-11 09:05:05'),
(594, 253, 'gongorasha2ne22@gmail.com', '', 'Enrollment Confirmed – Shane Nodado (STU-2026-0004)', 'failed', NULL, NULL, '2026-04-11 09:10:12'),
(595, 253, 'shane3@example.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 11:10:23', '2026-04-11 09:10:23'),
(596, 253, 'gongorasha2ne22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 11:10:29', '2026-04-11 09:10:29'),
(597, 254, 'cuevasdavezarene@gmail.com', 'soa', 'Payment Verified – Full (₱31,387.00)', 'pending', NULL, NULL, '2026-04-11 15:03:43'),
(598, 254, 'shane123@example.com', 'soa', 'Statement of Account — Dave Cuevas | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 17:03:49', '2026-04-11 15:03:49'),
(599, 254, 'cuevasdavezarene@gmail.com', 'soa', 'Statement of Account — Dave Cuevas | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 17:03:53', '2026-04-11 15:03:53'),
(600, 254, 'cuevasdavezarene@gmail.com', '', 'Enrollment Confirmed – Dave Cuevas (STU-2026-0005)', 'failed', NULL, NULL, '2026-04-11 15:06:59'),
(601, 254, 'shane123@example.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 17:07:08', '2026-04-11 15:07:08'),
(602, 254, 'cuevasdavezarene@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Dave Cuevas | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 17:07:13', '2026-04-11 15:07:13'),
(603, 256, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱6,073.50)', 'pending', NULL, NULL, '2026-04-11 15:45:30'),
(604, 256, 'shane333@example.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 17:45:35', '2026-04-11 15:45:35'),
(605, 256, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Shane Nodado | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 17:45:40', '2026-04-11 15:45:40'),
(606, 269, 'guardian1183@gmail.com', 'soa', 'Payment Verified – Downpayment (₱6,430.00)', 'pending', NULL, NULL, '2026-04-11 20:05:50'),
(607, 269, 'shane1231223@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 22:05:58', '2026-04-11 20:05:58'),
(608, 269, 'guardian1183@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 22:06:03', '2026-04-11 20:06:03'),
(609, 268, 'guardian2818@gmail.com', 'soa', 'Payment Verified – Downpayment (₱6,430.00)', 'pending', NULL, NULL, '2026-04-11 20:27:35'),
(610, 268, 'shane12312223@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 22:27:40', '2026-04-11 20:27:40'),
(611, 267, 'guardian6132@gmail.com', 'soa', 'Payment Verified – Downpayment (₱6,430.00)', 'pending', NULL, NULL, '2026-04-11 20:27:40'),
(612, 267, 'shane12312s223@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 22:27:45', '2026-04-11 20:27:45'),
(613, 268, 'guardian2818@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 22:27:46', '2026-04-11 20:27:46'),
(614, 267, 'guardian6132@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 22:27:50', '2026-04-11 20:27:50'),
(615, 269, 'guardian1183@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0005)', 'failed', NULL, NULL, '2026-04-11 21:49:32'),
(616, 269, 'shane1231223@example.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 23:49:42', '2026-04-11 21:49:42'),
(617, 268, 'guardian2818@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0004)', 'failed', NULL, NULL, '2026-04-11 21:49:45'),
(618, 269, 'guardian1183@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 23:49:47', '2026-04-11 21:49:47'),
(619, 268, 'shane12312223@example.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 23:49:53', '2026-04-11 21:49:53'),
(620, 268, 'guardian2818@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-11 23:49:58', '2026-04-11 21:49:58'),
(621, 273, 'guardian4875@gmail.com', 'soa', 'Payment Verified – Downpayment (₱6,073.50)', 'pending', NULL, NULL, '2026-04-11 22:01:56'),
(622, 273, 'shane1231S22223aa@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 00:02:01', '2026-04-11 22:02:01'),
(623, 273, 'guardian4875@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 00:02:06', '2026-04-11 22:02:06'),
(624, 275, 'guardian5425@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,895.25)', 'pending', NULL, NULL, '2026-04-11 22:04:54'),
(625, 275, 'shane1231SS22S223aa@example.com', 'soa', 'Statement of Account — Juan DWWAD | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 00:04:59', '2026-04-11 22:04:59'),
(626, 275, 'guardian5425@gmail.com', 'soa', 'Statement of Account — Juan DWWAD | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 00:05:04', '2026-04-11 22:05:04'),
(627, 276, 'guardian7567@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,360.50)', 'pending', NULL, NULL, '2026-04-11 22:12:20'),
(628, 276, 'shane1231S22sS223aa@example.com', 'soa', 'Statement of Account — Juan wdwew | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 00:12:24', '2026-04-11 22:12:24'),
(629, 276, 'guardian7567@gmail.com', 'soa', 'Statement of Account — Juan wdwew | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 00:12:30', '2026-04-11 22:12:30'),
(630, 277, 'guardian3436@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,895.25)', 'pending', NULL, NULL, '2026-04-11 22:17:44'),
(631, 277, 'shane1231S22ssS223aa@example.com', 'soa', 'Statement of Account — Juan fdsf | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 00:17:49', '2026-04-11 22:17:49'),
(632, 277, 'guardian3436@gmail.com', 'soa', 'Statement of Account — Juan fdsf | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 00:17:54', '2026-04-11 22:17:54'),
(633, 279, 'guardian6494@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,896.00)', 'pending', NULL, NULL, '2026-04-12 10:37:16'),
(634, 279, 'shane@example.com', 'soa', 'Statement of Account — Juan Dela Cru | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 12:37:25', '2026-04-12 10:37:25'),
(635, 279, 'guardian6494@gmail.com', 'soa', 'Statement of Account — Juan Dela Cru | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 12:37:30', '2026-04-12 10:37:30'),
(636, 280, 'guardian2694@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,896.00)', 'pending', NULL, NULL, '2026-04-12 10:52:16'),
(637, 280, 'shane1@example.com', 'soa', 'Statement of Account — Juan Dela Cruq | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 12:52:21', '2026-04-12 10:52:21'),
(638, 280, 'guardian2694@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruq | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 12:52:27', '2026-04-12 10:52:27'),
(639, 281, 'guardian0264@gmail.com', 'soa', 'Payment Verified – Full (₱24,970.00)', 'pending', NULL, NULL, '2026-04-12 11:02:56'),
(640, 281, 'shane2@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:03:02', '2026-04-12 11:03:02'),
(641, 281, 'guardian0264@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:03:08', '2026-04-12 11:03:08'),
(642, 282, 'guardian4872@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,895.25)', 'pending', NULL, NULL, '2026-04-12 11:18:48'),
(643, 282, 'shane@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:18:53', '2026-04-12 11:18:53'),
(644, 282, 'guardian4872@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:18:58', '2026-04-12 11:18:58'),
(645, 283, 'guardian2699@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,895.25)', 'pending', NULL, NULL, '2026-04-12 11:24:46'),
(646, 283, 'shane1@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:24:51', '2026-04-12 11:24:51'),
(647, 283, 'guardian2699@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:24:56', '2026-04-12 11:24:56'),
(648, 284, 'guardian2805@gmail.com', 'soa', 'Payment Verified – Full (₱22,831.00)', 'pending', NULL, NULL, '2026-04-12 11:35:11'),
(649, 284, 'shane1@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:35:16', '2026-04-12 11:35:16'),
(650, 284, 'guardian2805@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:35:23', '2026-04-12 11:35:23'),
(651, 285, 'guardian4115@gmail.com', 'soa', 'Payment Verified – Full (₱24,970.00)', 'pending', NULL, NULL, '2026-04-12 11:41:40'),
(652, 285, 'shan@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:41:45', '2026-04-12 11:41:45'),
(653, 285, 'guardian4115@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 1st Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:41:49', '2026-04-12 11:41:49'),
(654, 285, 'guardian4115@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-12 11:42:12'),
(655, 285, 'guardian4115@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-04-12 11:45:31'),
(656, 285, 'shan@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:45:36', '2026-04-12 11:45:36'),
(657, 285, 'guardian4115@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 13:45:41', '2026-04-12 11:45:41'),
(658, 285, 'guardian4115@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-12 11:45:49'),
(659, 289, 'guardian3652@gmail.com', 'soa', 'Payment Verified – Downpayment (₱7,143.00)', 'pending', NULL, NULL, '2026-04-12 15:13:04'),
(660, 289, 'sh1s2@example.com', 'soa', 'Statement of Account — Juan suon | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:13:10', '2026-04-12 15:13:10'),
(661, 289, 'guardian3652@gmail.com', 'soa', 'Statement of Account — Juan suon | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:13:15', '2026-04-12 15:13:15'),
(662, 288, 'guardian1386@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0004)', 'failed', NULL, NULL, '2026-04-12 15:13:33'),
(663, 289, 'guardian3652@gmail.com', '', 'Enrollment Confirmed – Juan suon (STU-2026-0005)', 'failed', NULL, NULL, '2026-04-12 15:14:06'),
(664, 290, 'guardian8279@gmail.com', 'soa', 'Payment Verified – Full (₱1,000.00)', 'pending', NULL, NULL, '2026-04-12 15:21:39'),
(665, 290, 'college2@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:21:44', '2026-04-12 15:21:44'),
(666, 290, 'guardian8279@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:21:56', '2026-04-12 15:21:56'),
(667, 290, 'guardian8279@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0006)', 'failed', NULL, NULL, '2026-04-12 15:22:23'),
(668, 290, 'college2@example.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:22:32', '2026-04-12 15:22:32'),
(669, 290, 'guardian8279@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:22:36', '2026-04-12 15:22:36'),
(670, 291, 'guardian7948@gmail.com', 'soa', 'Payment Verified – Full (₱29,248.00)', 'pending', NULL, NULL, '2026-04-12 15:26:54'),
(671, 291, 'shane@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:26:59', '2026-04-12 15:26:59'),
(672, 291, 'guardian7948@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:27:03', '2026-04-12 15:27:03'),
(673, 291, 'guardian7948@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0001)', 'failed', NULL, NULL, '2026-04-12 15:27:32'),
(674, 291, 'shane@example.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:27:41', '2026-04-12 15:27:41'),
(675, 291, 'guardian7948@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:27:46', '2026-04-12 15:27:46'),
(676, 292, 'guardian9302@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,895.25)', 'pending', NULL, NULL, '2026-04-12 15:38:22'),
(677, 292, 'shanetrans@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:38:28', '2026-04-12 15:38:28'),
(678, 292, 'guardian9302@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 17:38:32', '2026-04-12 15:38:32'),
(679, 292, 'guardian9302@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0002)', 'failed', NULL, NULL, '2026-04-12 15:38:51'),
(680, 293, 'guardian4417@gmail.com', 'soa', 'Payment Verified – Downpayment (₱5,361.00)', 'pending', NULL, NULL, '2026-04-12 16:00:57'),
(681, 293, 'shanetrans1@example.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 18:01:13', '2026-04-12 16:01:13'),
(682, 293, 'guardian4417@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 18:01:18', '2026-04-12 16:01:18'),
(683, 293, 'guardian4417@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0003)', 'failed', NULL, NULL, '2026-04-12 16:01:18'),
(684, 294, 'gongorashane22@gmail.com', 'soa', 'Payment Verified – Downpayment (₱3,930.00)', 'pending', NULL, NULL, '2026-04-12 19:36:31'),
(685, 294, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 21:36:42', '2026-04-12 19:36:42'),
(686, 294, 'gongorashane22@gmail.com', 'soa', 'Statement of Account — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 21:36:47', '2026-04-12 19:36:47'),
(687, 294, 'gongorashane22@gmail.com', '', 'Enrollment Confirmed – Juan Dela Cruz (STU-2026-0004)', 'failed', NULL, NULL, '2026-04-12 19:37:16'),
(688, 294, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 21:37:25', '2026-04-12 19:37:25'),
(689, 294, 'gongorashane22@gmail.com', 'enrollment_report', 'Enrollment Confirmation — Juan Dela Cruz | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 21:37:32', '2026-04-12 19:37:32'),
(690, 300, 'shanetry3@example.comSS', 'soa', 'Payment Verified – Downpayment (₱25,683.00)', 'pending', NULL, NULL, '2026-04-12 21:42:41'),
(691, 300, 'shanetry4@example.comSS', 'soa', 'Statement of Account — ihosho NOniso | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 23:42:47', '2026-04-12 21:42:47'),
(692, 300, 'shanetry3@example.comSS', 'soa', 'Statement of Account — ihosho NOniso | 2nd Semester, AY 2028-2029', 'sent', '', '2026-04-12 23:42:52', '2026-04-12 21:42:52'),
(693, 300, 'shanetry3@example.comSS', '', 'Enrollment Confirmed – ihosho NOniso (STU-2026-0010)', 'failed', NULL, NULL, '2026-04-12 21:45:30');

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
(508, 'shane2@gmail.com', '::1', '2026-04-08 21:37:15'),
(509, 'shane2@gmail.com', '::1', '2026-04-08 21:37:43'),
(510, 'shane234@gmail.com', '::1', '2026-04-08 21:37:50'),
(511, 'shane2@gmail.com', '::1', '2026-04-08 21:42:20'),
(512, 'shane2@gmail.com', '::1', '2026-04-08 21:42:34'),
(513, 'shane2@gmail.com', '::1', '2026-04-08 21:46:31'),
(514, 'shane2@gmail.com', '::1', '2026-04-08 21:46:44'),
(515, 'shane2@gmail.com', '::1', '2026-04-08 21:46:48'),
(516, 'shane5@gmail.com', '::1', '2026-04-08 21:46:55'),
(517, 'shane2@gmail.com', '::1', '2026-04-08 21:50:18'),
(518, 'shane2@gmail.com', '::1', '2026-04-08 21:52:30'),
(519, 'shane2@gmail.com', '::1', '2026-04-08 21:52:46'),
(520, 'shane2@gmail.com', '::1', '2026-04-08 21:53:07'),
(521, 'shane2@gmail.com', '::1', '2026-04-08 21:55:56'),
(557, 'shanesSs2s@example.coms', '::1', '2026-04-11 07:09:23');

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
(13, 'gongorashane22@gmail.com', '138977', '', '2026-04-13 03:52:32', 0, '2026-04-12 19:37:32');

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
(2026, 177);

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
  `or_ar_number` varchar(30) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
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
  `downpayment_unlocked_at` timestamp NULL DEFAULT NULL,
  `prelim_carry_over` decimal(10,2) NOT NULL DEFAULT 0.00,
  `midterm_carry_over` decimal(10,2) NOT NULL DEFAULT 0.00,
  `finals_carry_over` decimal(10,2) NOT NULL DEFAULT 0.00
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
(201, 4, 675),
(202, 4, 676),
(203, 4, 677),
(204, 4, 678),
(205, 4, 679),
(206, 4, 680),
(207, 4, 681),
(208, 4, 682),
(209, 4, 683),
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
(1453, 31, 2048),
(1454, 31, 2049),
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
(5, 'SCH-NQERYFCG', 'Full Scholarship', 'olongapo', 'shane nodado', '1st semester', 0, NULL, NULL, 0, NULL, NULL, 3, 'accounting@example.com', '2026-04-09 09:21:18', '2026-04-09 09:21:18');

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
  `ip_address` varchar(45) DEFAULT NULL,
  `prev_token` varchar(64) DEFAULT NULL,
  `prev_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `token`, `role`, `expires_at`, `created_at`, `device_id`, `ip_address`, `prev_token`, `prev_expires`) VALUES
(564, 2, '67fbc00ab8e2260dc7f00f7fda1f9b613ce2b9dc1a77ab2244ffc66e3a578d7a', 'admin', '2026-04-13 10:56:37', '2026-04-12 18:56:37', '', '::1', NULL, NULL),
(565, 141, '400dd30654c53511fbf9a6b5455208537197d7563a929fa2943478e118b8ec08', 'faculty', '2026-04-13 10:57:12', '2026-04-12 18:57:12', '', '::1', NULL, NULL),
(579, 3, '799adae1fec58a925bcc35449a5d999f0a9f8c66f798ff87e9767c4335d46b36', 'accounting', '2026-04-13 13:42:11', '2026-04-12 21:42:11', '', '::1', NULL, NULL),
(581, 4, '80e99403d1ce41e3646eb2f78efc9ee12280f5297d9c2893f47aaee5e8250b11', 'registrar', '2026-04-13 13:43:58', '2026-04-12 21:43:58', '', '::1', NULL, NULL);

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
  `snapshotted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `extra_fees_json` mediumtext DEFAULT NULL,
  `department` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `soa_snapshots`
--

INSERT INTO `soa_snapshots` (`id`, `student_id`, `semester`, `units`, `tuition_fee`, `miscellaneous_fee`, `registration_fee`, `laboratory_fee`, `energy_fee`, `subtotal`, `discount`, `installment_fee`, `total_assessment`, `total_paid`, `balance`, `payment_plan`, `payment_status`, `subjects_json`, `payments_json`, `snapshotted_at`, `extra_fees_json`, `department`) VALUES
(91, 221, '2nd Semester, AY 2027-2028', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'full', 'Free', '[]', '[]', '2026-04-08 14:30:22', NULL, 'Senior High School (SHS)'),
(92, 220, '2nd Semester, AY 2027-2028', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 0.00, 29248.00, 29248.00, 10695.00, 'full', 'Partially Paid', '[{\"code\":\"ELEC103\",\"name\":\"Platform Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE104\",\"name\":\"Readings in Philippine History\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE106\",\"name\":\"Science, Technology, and Society\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"GE107\",\"name\":\"The Contemporary World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"IS103\",\"name\":\"IT Infrastructure and Network Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"IT100\",\"name\":\"Introduction to Human Computer Interaction\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"IT102\",\"name\":\"Information Assurance and Security 2\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"IT110\",\"name\":\"System Integration and Architecture 1\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Completed\"},{\"code\":\"NSTP2\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Completed\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Completed\"}]', '[{\"or_ar_number\":\"OR-20260141\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-08\",\"payment_method\":\"GCash\",\"amount\":\"29248.00\",\"semester\":\"2nd Semester, AY 2027-2028\"}]', '2026-04-08 19:26:36', '[]', 'Information Communication and Technology (ICTD)'),
(93, 222, '2nd Semester, AY 2027-2028', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 0.00, 29248.00, 25000.00, 4248.00, 'full', 'Partially Paid', '[]', '[{\"or_ar_number\":\"OR-20260142\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-08\",\"payment_method\":\"Cash\",\"amount\":\"25000.00\",\"semester\":\"2nd Semester, AY 2027-2028\"}]', '2026-04-08 18:13:43', '[]', 'Information Communication and Technology (ICTD)'),
(95, 220, '1st Semester AY 2028-2029', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 0.00, 29248.00, 29248.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260143\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-08\",\"payment_method\":\"Cash\",\"amount\":\"29248.00\",\"semester\":\"1st Semester AY 2028-2029\"}]', '2026-04-08 19:27:00', '[]', 'Information Communication and Technology (ICTD)'),
(96, 228, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-10 14:22:15', '[]', 'Technical-Vocational Education and Training (TVET)'),
(98, 229, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-10 14:48:24', '[]', 'Technical-Vocational Education and Training (TVET)'),
(100, 230, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 20000.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260144\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"GCash\",\"amount\":\"20000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 15:10:00', '[]', 'Technical-Vocational Education and Training (TVET)'),
(103, 231, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 20000.00, 0.00, 'full', 'Fully Paid', '[{\"code\":\"12\",\"name\":\"12\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"OR-20260145\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"GCash\",\"amount\":\"20000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 20:45:18', '[]', 'Technical-Vocational Education and Training (TVET)'),
(106, 232, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-10 19:56:19', '[]', 'Technical-Vocational Education and Training (TVET)'),
(108, 233, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-10 20:07:17', '[]', 'Technical-Vocational Education and Training (TVET)'),
(110, 234, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 20000.00, 0.00, 'full', 'Fully Paid', '[{\"code\":\"12\",\"name\":\"12\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"OR-20260146\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"GCash\",\"amount\":\"20000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 20:45:14', '[]', 'Technical-Vocational Education and Training (TVET)'),
(113, 235, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-10 20:30:26', '[]', 'Technical-Vocational Education and Training (TVET)'),
(115, 236, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 20000.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260147\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"GCash\",\"amount\":\"20000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 20:45:05', '[]', 'Technical-Vocational Education and Training (TVET)'),
(121, 237, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 10000.00, 10000.00, 'full', 'Partially Paid', '[]', '[{\"or_ar_number\":\"OR-20260148\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"GCash\",\"amount\":\"10000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 20:51:29', '[]', 'Technical-Vocational Education and Training (TVET)'),
(124, 238, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 20000.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260149\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"GCash\",\"amount\":\"20000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 21:11:27', '[]', 'Technical-Vocational Education and Training (TVET)'),
(127, 239, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 5000.00, 15000.00, 'full', 'Partially Paid', '[]', '[{\"or_ar_number\":\"OR-20260150\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"Cash\",\"amount\":\"5000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 21:43:17', '[]', 'Technical-Vocational Education and Training (TVET)'),
(129, 241, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-10 21:42:21', '[]', 'Technical-Vocational Education and Training (TVET)'),
(132, 242, '1st Semester, AY 2028-2029', 3, 20000.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 20000.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260151\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"Cash\",\"amount\":\"20000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 22:05:19', '[]', 'Technical-Vocational Education and Training (TVET)'),
(135, 243, '1st Semester, AY 2028-2029', 3, 20000.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 20000.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260152\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-10\",\"payment_method\":\"Cash\",\"amount\":\"20000.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-10 22:21:06', '[]', 'Technical-Vocational Education and Training (TVET)'),
(138, 244, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 07:06:23', '[]', 'Technical-Vocational Education and Training (TVET)'),
(140, 245, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 07:19:40', '[]', 'Technical-Vocational Education and Training (TVET)'),
(142, 246, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 07:31:29', '[]', 'Technical-Vocational Education and Training (TVET)'),
(144, 247, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 07:47:54', '[]', 'Technical-Vocational Education and Training (TVET)'),
(146, 248, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 08:01:25', '[]', 'Technical-Vocational Education and Training (TVET)'),
(148, 249, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 08:12:21', '[]', 'Technical-Vocational Education and Training (TVET)'),
(150, 250, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 08:22:12', '[]', 'Technical-Vocational Education and Training (TVET)'),
(160, 252, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 08:35:49', '[]', 'Technical-Vocational Education and Training (TVET)'),
(162, 253, '1st Semester, AY 2028-2029', 3, 20000.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 750.00, 20750.00, 5187.50, 15562.50, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260153\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"5187.50\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 09:04:51', '[]', 'Technical-Vocational Education and Training (TVET)'),
(165, 254, '1st Semester, AY 2028-2029', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 0.00, 31387.00, 31387.00, 0.00, 'full', 'Fully Paid', '[]', '[{\"or_ar_number\":\"OR-20260154\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"31387.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 15:03:43', '[]', 'Information Communication and Technology (ICTD)'),
(166, 256, '1st Semester, AY 2028-2029', 23, 14950.00, 6688.00, 700.00, 7600.00, 1449.00, 31387.00, 0.00, 750.00, 32137.00, 6073.50, 26063.50, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260155\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"6073.50\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 15:45:30', '[]', 'Information Communication and Technology (ICTD)'),
(167, 251, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'full', 'Free', '[{\"code\":\"1\",\"name\":\"1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"12\",\"name\":\"12\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[]', '2026-04-11 16:12:00', NULL, 'Technical-Vocational Education and Training (TVET)'),
(168, 263, '1st Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 0.00, 20000.00, 0.00, 20000.00, 'full', 'Pending', '[]', '[]', '2026-04-11 16:42:51', '[]', 'Technical-Vocational Education and Training (TVET)'),
(170, 265, '1st Semester, AY 2028-2029', 14, 9100.00, 6688.00, 700.00, 7600.00, 882.00, 24970.00, 0.00, 750.00, 25720.00, 0.00, 25720.00, 'installment', 'Pending', '[]', '[]', '2026-04-11 16:50:56', '[]', 'Information Communication and Technology (ICTD)'),
(172, 266, '1st Semester, AY 2028-2029', 0, 20000.00, 0.00, 0.00, 0.00, 0.00, 20000.00, 0.00, 750.00, 20750.00, 0.00, 20750.00, 'installment', 'Pending', '[]', '[]', '2026-04-11 16:53:51', '[]', 'Technical-Vocational Education and Training (TVET)'),
(176, 267, '1st Semester, AY 2028-2029', 14, 9100.00, 6688.00, 700.00, 7600.00, 882.00, 24970.00, 0.00, 750.00, 32137.00, 6430.00, 25707.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260158\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"6430.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 20:27:40', '[]', 'Information Communication and Technology (ICTD)'),
(178, 268, '1st Semester, AY 2028-2029', 14, 9100.00, 6688.00, 700.00, 7600.00, 882.00, 24970.00, 0.00, 750.00, 32137.00, 6430.00, 25707.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260157\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"6430.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 20:27:35', '[]', 'Information Communication and Technology (ICTD)'),
(180, 269, '1st Semester, AY 2028-2029', 14, 9100.00, 6688.00, 700.00, 7600.00, 882.00, 24970.00, 0.00, 750.00, 25720.00, 6430.00, 19290.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260156\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"6430.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 20:05:50', '[]', 'Information Communication and Technology (ICTD)'),
(184, 270, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 0.00, 23581.00, 'installment', 'Pending', '[]', '[]', '2026-04-11 21:53:34', '[]', 'Information Communication and Technology (ICTD)'),
(187, 271, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 0.00, 23581.00, 'installment', 'Pending', '[]', '[]', '2026-04-11 21:54:54', '[]', 'Information Communication and Technology (ICTD)'),
(188, 272, '1st Semester, AY 2028-2029', 14, 9100.00, 6688.00, 700.00, 7600.00, 882.00, 24970.00, 0.00, 750.00, 25720.00, 0.00, 25720.00, 'installment', 'Pending', '[]', '[]', '2026-04-11 21:56:34', '[]', 'Information Communication and Technology (ICTD)'),
(190, 273, '1st Semester, AY 2028-2029', 12, 7800.00, 6688.00, 700.00, 7600.00, 756.00, 23544.00, 0.00, 750.00, 24294.00, 6073.50, 18220.50, 'installment', 'Partially Paid', '[{\"code\":\"CC100\",\"name\":\"Introduction to Computing\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"CC101\",\"name\":\"Computer Programming 1\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"GE105\",\"name\":\"Mathematics in the Modern World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260159\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"6073.50\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 22:02:18', '[]', 'Information Communication and Technology (ICTD)'),
(196, 275, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 5895.25, 17685.75, 'installment', 'Partially Paid', '[{\"code\":\"CC100\",\"name\":\"Introduction to Computing\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"CC101\",\"name\":\"Computer Programming 1\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"GE105\",\"name\":\"Mathematics in the Modern World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260160\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"5895.25\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 22:09:55', '[]', 'Information Communication and Technology (ICTD)'),
(204, 276, '1st Semester, AY 2028-2029', 8, 5200.00, 6688.00, 700.00, 7600.00, 504.00, 20692.00, 0.00, 750.00, 21442.00, 5360.50, 16081.50, 'installment', 'Partially Paid', '[{\"code\":\"CC100\",\"name\":\"Introduction to Computing\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"CC101\",\"name\":\"Computer Programming 1\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"GE105\",\"name\":\"Mathematics in the Modern World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260161\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"5360.50\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-11 22:16:10', '[]', 'Information Communication and Technology (ICTD)'),
(212, 277, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 5895.25, 17685.75, 'installment', 'Partially Paid', '[{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260162\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-11\",\"payment_method\":\"Cash\",\"amount\":\"5895.25\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-12 10:20:24', '[]', 'Information Communication and Technology (ICTD)'),
(222, 278, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 0.00, 23581.00, 'installment', 'Pending', '[]', '[]', '2026-04-12 10:26:34', '[]', 'Information Communication and Technology (ICTD)'),
(225, 279, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 5896.00, 17685.00, 'installment', 'Partially Paid', '[{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260163\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"GCash\",\"amount\":\"5896.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-12 10:37:53', '[]', 'Information Communication and Technology (ICTD)'),
(235, 280, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 5896.00, 17685.00, 'installment', 'Partially Paid', '[{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260164\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"GCash\",\"amount\":\"5896.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-12 11:00:52', '[]', 'Information Communication and Technology (ICTD)'),
(243, 281, '1st Semester, AY 2028-2029', 14, 9100.00, 6688.00, 700.00, 7600.00, 882.00, 24970.00, 0.00, 750.00, 25720.00, 24970.00, 0.00, 'installment', 'Fully Paid', '[{\"code\":\"GE105\",\"name\":\"Mathematics in the Modern World\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"OR-20260165\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"GCash\",\"amount\":\"24970.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-12 11:16:20', '[]', 'Information Communication and Technology (ICTD)'),
(252, 282, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 5895.25, 17685.75, 'installment', 'Partially Paid', '[{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260166\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"5895.25\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-12 11:23:06', '[]', 'Information Communication and Technology (ICTD)'),
(267, 283, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 5895.25, 17685.75, 'installment', 'Partially Paid', '[{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260167\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"5895.25\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-12 11:32:48', '[]', 'Information Communication and Technology (ICTD)'),
(280, 284, '1st Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 23581.00, 22831.00, 0.00, 'installment', 'Fully Paid', '[{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"OR-20260168\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"22831.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-12 11:35:14', '[]', 'Information Communication and Technology (ICTD)'),
(286, 285, '1st Semester, AY 2028-2029', 14, 9100.00, 6688.00, 700.00, 7600.00, 882.00, 24970.00, 0.00, 0.00, 24970.00, 24970.00, 0.00, 'full', 'Fully Paid', '[{\"code\":\"GE108\",\"name\":\"Ethics\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"GE109\",\"name\":\"Understanding the Self\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"IT-CMT\",\"name\":\"Computer Organization and Maintenance\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Pending\"},{\"code\":\"NSTP1\",\"name\":\"National Service Training Program1\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"PE1\",\"name\":\"Physical Education 1 (Aquatics)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Pending\"}]', '[{\"or_ar_number\":\"OR-20260169\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"24970.00\",\"semester\":\"1st Semester, AY 2028-2029\"}]', '2026-04-12 11:44:17', '[]', 'Information Communication and Technology (ICTD)'),
(294, 285, '2nd Semester, AY 2028-2029', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 0.00, 29248.00, 29248.00, 0.00, 'full', 'Fully Paid', '[{\"code\":\"CC102\",\"name\":\"Computer Programming 2\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Pending\"},{\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"IS103\",\"name\":\"IT Infrastructure and Network Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Pending\"},{\"code\":\"IT100\",\"name\":\"Introduction to Human Computer Interaction\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Pending\"},{\"code\":\"NSTP2\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Pending\"}]', '[{\"or_ar_number\":\"OR-20260170\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"29248.00\",\"semester\":\"2nd Semester, AY 2028-2029\"}]', '2026-04-12 14:41:50', '[]', 'Information Communication and Technology (ICTD)'),
(304, 287, '2nd Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'full', 'Free', '[]', '[]', '2026-04-12 13:41:04', NULL, 'Technical-Vocational Education and Training (TVET)'),
(313, 288, '2nd Semester, AY 2028-2029', 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'full', 'Free', '[]', '[]', '2026-04-12 14:13:23', NULL, 'Senior High School (SHS)'),
(315, 289, '2nd Semester, AY 2028-2029', 18, 11700.00, 6688.00, 700.00, 7600.00, 1134.00, 27822.00, 0.00, 750.00, 28572.00, 7143.00, 21429.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260171\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"GCash\",\"amount\":\"7143.00\",\"semester\":\"2nd Semester, AY 2028-2029\"}]', '2026-04-12 15:13:04', '[]', 'Senior High School (SHS)'),
(316, 290, '2nd Semester, AY 2028-2029', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 0.00, 29248.00, 1000.00, 26109.00, 'full', 'Partially Paid', '[{\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IS103\",\"name\":\"IT Infrastructure and Network Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"IT100\",\"name\":\"Introduction to Human Computer Interaction\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP2\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"OR-20260172\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"1000.00\",\"semester\":\"2nd Semester, AY 2028-2029\"}]', '2026-04-12 15:22:23', '[]', 'Information Communication and Technology (ICTD)'),
(319, 291, '2nd Semester, AY 2028-2029', 20, 13000.00, 6688.00, 700.00, 7600.00, 1260.00, 29248.00, 0.00, 0.00, 29248.00, 29248.00, 0.00, 'full', 'Fully Paid', '[{\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"IS103\",\"name\":\"IT Infrastructure and Network Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"IT100\",\"name\":\"Introduction to Human Computer Interaction\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Enrolled\"},{\"code\":\"NSTP2\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"OR-20260173\",\"or_ar_type\":\"OR\",\"exam_period\":\"Full\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"29248.00\",\"semester\":\"2nd Semester, AY 2028-2029\"}]', '2026-04-12 18:44:19', '[]', 'Information Communication and Technology (ICTD)'),
(322, 292, '2nd Semester, AY 2028-2029', 11, 7150.00, 6688.00, 700.00, 7600.00, 693.00, 22831.00, 0.00, 750.00, 29998.00, 5895.25, 24102.75, 'installment', 'Partially Paid', '[{\"code\":\"CC102\",\"name\":\"Computer Programming 2\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Pending\"},{\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"IS103\",\"name\":\"IT Infrastructure and Network Technologies\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Pending\"},{\"code\":\"IT100\",\"name\":\"Introduction to Human Computer Interaction\",\"credits\":\"3\",\"lec_units\":\"2\",\"lab_units\":\"1\",\"status\":\"Pending\"},{\"code\":\"NSTP2\",\"name\":\"National Service Training Program 2\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Pending\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Pending\"}]', '[{\"or_ar_number\":\"AR-20260174\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"5895.25\",\"semester\":\"2nd Semester, AY 2028-2029\"}]', '2026-04-12 15:57:10', '[]', 'Information Communication and Technology (ICTD)'),
(329, 293, '2nd Semester, AY 2028-2029', 8, 5200.00, 6688.00, 700.00, 7600.00, 504.00, 20692.00, 0.00, 750.00, 21442.00, 5361.00, 16081.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260175\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"GCash\",\"amount\":\"5361.00\",\"semester\":\"2nd Semester, AY 2028-2029\"}]', '2026-04-12 16:00:57', '[]', 'Information Communication and Technology (ICTD)'),
(344, 294, '2nd Semester, AY 2028-2029', 14, 9100.00, 6688.00, 700.00, 7600.00, 882.00, 24970.00, 10000.00, 750.00, 15720.00, 3930.00, 11790.00, 'installment', 'Partially Paid', '[{\"code\":\"BSNA101\",\"name\":\"Fundamentals of Accounting, Business and Management\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"GE103\",\"name\":\"Art Appreciation\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"PE2\",\"name\":\"Physical Education 2 (Outdoor Pursuits and Contemporary Activities)\",\"credits\":\"2\",\"lec_units\":\"2\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"SCP102\",\"name\":\"Warehouse Operations Management\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"},{\"code\":\"TMC100\",\"name\":\"Fundamentals of Customs and Tariff System\",\"credits\":\"3\",\"lec_units\":\"3\",\"lab_units\":\"0\",\"status\":\"Enrolled\"}]', '[{\"or_ar_number\":\"AR-20260176\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"3930.00\",\"semester\":\"2nd Semester, AY 2028-2029\"}]', '2026-04-12 19:37:21', '[]', 'Business Management Department (BMD)'),
(347, 300, '2nd Semester, AY 2028-2029', 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 0.00, 750.00, 26433.00, 25683.00, 750.00, 'installment', 'Partially Paid', '[]', '[{\"or_ar_number\":\"AR-20260177\",\"or_ar_type\":\"AR\",\"exam_period\":\"Downpayment\",\"payment_date\":\"2026-04-12\",\"payment_method\":\"Cash\",\"amount\":\"25683.00\",\"semester\":\"2nd Semester, AY 2028-2029\"}]', '2026-04-12 21:42:41', '[]', 'Information Communication and Technology (ICTD)');

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
(3, 4, 'Registrar', 'Admin', NULL, '', '', 'Dept Head', '2026-03-18 19:24:57', '2026-04-11 14:46:50');

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
  `block_id` int(11) DEFAULT NULL COMMENT 'FK → class_blocks.id — null until Registrar assigns a block',
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `reject_reason` text DEFAULT NULL,
  `claim_code` varchar(30) DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL
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
('enrollment_period', '{\"is_open\":true,\"start\":\"2026-04-07 22:52\",\"end\":\"2026-04-16 22:52\",\"label\":\"2nd Semester, AY 2028-2029\",\"semester\":\"2nd Semester\",\"school_year\":\"2028-2029\"}', '2026-04-12 19:45:46'),
('payment_due_dates', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"upon enrollment\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"feb1\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"feb2\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"feb3\"}}', '2026-04-12 11:45:22'),
('payment_due_dates:1st_semester:2026-2027', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"JUNE 18 - 27 2027\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"JULY 19 - 28  2027\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"AUGUST 20 - 30 2027\"}}', '2026-03-29 09:11:11'),
('payment_due_dates:1st_semester:2027-2028', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"jan 1\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"aug 2\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"dec 3\"}}', '2026-04-03 10:20:04'),
('payment_due_dates:1st_semester:2028-2029', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"upon enrollment\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"jan1\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"jan2\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"jan3\"}}', '2026-04-11 13:29:58'),
('payment_due_dates:2nd_semester:2026-2027', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"JUNE 21-30\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"JULY 20-30\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"AUG 20-30\"}}', '2026-03-30 10:49:59'),
('payment_due_dates:2nd_semester:2027-2028', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"JANUARY 11-19, 2026\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"FEBRUARY 22- 29, 2026\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"MARCH 10 - APRIL 4, 2026\"}}', '2026-03-29 16:18:37'),
('payment_due_dates:2nd_semester:2028-2029', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"upon enrollment\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"feb1\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"feb2\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"feb3\"}}', '2026-04-12 11:45:22'),
('payment_due_dates_active_semester', 'payment_due_dates:2nd_semester:2028-2029', '2026-04-12 11:45:22');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tor_file_path` varchar(500) DEFAULT NULL
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
(3, 'accounting@example.com', '$2y$12$7are6PEEVB23Gx35vRUJBeG.tCR0nSv6qkTdwoHpjCofk/iE4moiy', 'accounting', '2026-01-29 07:51:13', '2026-04-12 21:42:11', 1),
(4, 'registrar@example.com', '$2y$12$mBRinZXeLFpyge/D499ceeBuVHsRqy6OiVNtDm.YuSdfAgVdFNrWG', 'registrar', '2026-01-29 08:54:49', '2026-03-11 16:27:10', 1),
(136, 'maria.santos@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(137, 'juan.reyes@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(138, 'anna.garcia@school.edu', '$2y$12$gaEO5.wb1d.usu8cOYX1QOYh9pHSP0qk5xkXevfWThw1NiBSwa5ri', 'faculty', '2026-03-04 13:10:06', '2026-03-27 15:26:59', 1),
(139, 'luis.rodriguez@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(140, 'sarah.kim@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(141, 'liza.delacruz@school.edu', '$2y$12$vauEgdYk/C8x8l9//.0QZu.87qWqDyeRUo.rFQtkN5j4ZkGcn.HWi', 'faculty', '2026-03-04 13:10:06', '2026-04-12 16:10:40', 1),
(142, 'ramon.villanueva@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjChp3zlcuV2xoE2', 'faculty', '2026-03-04 13:10:06', '2026-03-18 22:34:17', 1),
(143, 'carlo.mendoza@school.edu', '$2y$12$.LpnKQRr717Vhf4vip2Iq.mnD8MuI6jDTqdqysk99kvNXhtARslB2', 'faculty', '2026-03-04 13:10:06', '2026-03-28 18:56:29', 1);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `add_drop_window`
--
ALTER TABLE `add_drop_window`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=756;

--
-- AUTO_INCREMENT for table `block_course_sections`
--
ALTER TABLE `block_course_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_blocks`
--
ALTER TABLE `class_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `coe_requests`
--
ALTER TABLE `coe_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2050;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=694;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1830;

--
-- AUTO_INCREMENT for table `enrollment_snapshots`
--
ALTER TABLE `enrollment_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_permits`
--
ALTER TABLE `exam_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=238;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=690;

--
-- AUTO_INCREMENT for table `login_otp`
--
ALTER TABLE `login_otp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=336;

--
-- AUTO_INCREMENT for table `payment_notices`
--
ALTER TABLE `payment_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1289;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1455;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `scholarship_pre_approvals`
--
ALTER TABLE `scholarship_pre_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `school_events`
--
ALTER TABLE `school_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=582;

--
-- AUTO_INCREMENT for table `soa_snapshots`
--
ALTER TABLE `soa_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=348;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=301;

--
-- AUTO_INCREMENT for table `student_archive_log`
--
ALTER TABLE `student_archive_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_grades`
--
ALTER TABLE `student_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=264;

--
-- AUTO_INCREMENT for table `student_guardians`
--
ALTER TABLE `student_guardians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

--
-- AUTO_INCREMENT for table `student_scholarships`
--
ALTER TABLE `student_scholarships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=268;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3407;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=332;

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

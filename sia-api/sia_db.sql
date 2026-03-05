-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 05, 2026 at 07:47 AM
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
  `lec_units` int(11) DEFAULT 0,
  `lab_units` int(11) DEFAULT 0,
  `is_general` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = available across all programs, no program restriction',
  `is_lab` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `instructor`, `faculty_id`, `schedule`, `day`, `time`, `room`, `capacity`, `enrolled_count`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `lec_units`, `lab_units`, `is_general`, `is_lab`) VALUES
(1, 'GE109', 'Understanding the Self', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(2, 'GE108', 'Ethics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(3, 'CC100', 'Introduction to Computing', 3, 'Liza Dela Cruz', 9, NULL, '', '', '', 40, 3, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(4, 'CC101', 'Computer Programming 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(5, 'IT-CMT', 'Computer Organization and Maintenance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(6, 'GE105', 'Mathematics in the Modern World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(7, 'PE1', 'Physical Education 1 (Aquatics)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 2, 0, 0, 0),
(8, 'NSTP1', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 3, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(9, 'GE101', 'Purposive Communication', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 1, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(10, 'IT100', 'Introduction to Human Computer Interaction', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 1, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(11, 'CC102', 'Computer Programming 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 1, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(12, 'IS103', 'IT Infrastructure and Network Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 1, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(13, 'GE103', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 1, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(14, 'PE2', 'Physical Education 2 (Outdoor Pursuits)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 1, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 2, 0, 0, 0),
(15, 'NSTP2', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 1, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '1st Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(16, 'CC103', 'Data Structures and Algorithms', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(17, 'CC105', 'Application Development and Emerging Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(18, 'IT105', 'Discrete Mathematics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(19, 'ELEC400', 'Object-Oriented Programming', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(20, 'EMC203', 'Usability, HCI, and User Interaction Design', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(21, 'GE109-2', 'Understanding the Self (GE Elective)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(22, 'PE3', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 0, 0, 0),
(23, 'CC104', 'Information Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(24, 'IT103', 'Fundamentals of Database Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(25, 'IT107', 'Networking 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(26, 'GE110', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(27, 'GE115', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(28, 'PE4', 'Physical Education 4 (Endurance Exercises)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '2nd Year', '2026-03-04 17:43:03', 2, 0, 0, 0),
(29, 'IT104', 'Integrative Programming and Technologies 1', 3, 'Liza Dela Cruz', 9, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(30, 'IT101', 'Information Assurance and Security 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(31, 'IT108', 'Networking 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(32, 'ELEC401', 'Multimedia Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(33, 'IT106', 'Quantitative Methods', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(34, 'EMC207', 'Principles of 3D Animation', 3, 'Liza Dela Cruz', 9, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(35, 'GE111', 'Social and Professional Issues', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(36, 'IT102', 'Information Assurance and Security 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(37, 'IT110', 'System Integration and Architecture 1', 3, 'Liza Dela Cruz', 9, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(38, 'ELEC103', 'Platform Technologies', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(39, 'GE104', 'Readings in Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(40, 'GE106', 'Science, Technology, and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(41, 'GE107', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '3rd Year', '2026-03-04 17:43:03', 3, 0, 0, 0),
(42, 'IT109', 'System Administration and Maintenance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(43, 'DM101', 'Organization and Management Systems', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(44, 'ELEC403', 'Web Systems and Technology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', 2, 1, 0, 1),
(45, 'CAP501', 'Capstone Project', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', 6, 0, 0, 0),
(46, 'OJT-BSIT', 'Internship (486 hours)', 9, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Information Technology', '4th Year', '2026-03-04 17:43:03', 9, 0, 0, 0),
(550, 'GE100', 'Conversational English and Personality Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(551, 'BME100-BSA', 'International Business and Trade', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(552, 'AEC111', 'Financial Accounting and Reporting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(553, 'AEC109', 'Managerial Economics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(554, 'BSNA102', 'Organization & Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(555, 'PE1-BMD', 'Physical Education 1 (Aquatics)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 2, 0, 0, 0),
(556, 'NSTP1-BMD', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 1, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(557, 'AEC112', 'Conceptual Framework and Accounting Standards', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(558, 'AEC120', 'Cost Accounting and Control', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(559, 'BSNA101', 'Fundamentals of Accountancy, Business and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(560, 'AEC113', 'Intermediate Accounting 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(561, 'BME101-BSA', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(562, 'PE2-BMD', 'Physical Education 2 (Outdoor Pursuits and Contemporary Activities)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 2, 0, 0, 0),
(563, 'NSTP2-BMD', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(564, 'BSNA103', 'Business Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(565, 'AEC121', 'Strategic Cost Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(566, 'AEC108', 'Governance, Business Ethics, Risk Mgt. and Internal Control', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(567, 'AEC116', 'Financial Markets', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(568, 'BME103-BSA', 'Law on Obligations and Contracts', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(569, 'AEC107', 'Statistical Analysis and Software Application', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(570, 'AEC105', 'Intermediate Accounting 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(571, 'AEC117', 'Financial Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(572, 'PE3-BMD', 'Physical Education 3 (Exercises for Fitness)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 2, 0, 0, 0),
(573, 'GE103-BMD', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(574, 'AEC101', 'Business Laws and Regulations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(575, 'AEC115', 'Intermediate Accounting 3', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(576, 'AEC118', 'Accounting Information System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(577, 'AEC124', 'Income Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(578, 'GE116-BMD', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(579, 'BSNA104', 'Business Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(580, 'GE110-BMD', 'Rizal\'s Life and Works', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(581, 'PE4-BMD', 'Physical Education 4 (Endurance Exercises through Dance)', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '2nd Year', '2026-03-05 01:03:52', 2, 0, 0, 0),
(582, 'GE104-BMD', 'Readings in the Philippine History', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(583, 'AEC103', 'Management Science', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(584, 'AEC119', 'IT Application Tools in Business', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(585, 'AEC122', 'Strategic Business Analysis', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(586, 'AEC123', 'Business Tax', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(587, 'AEC110', 'Economic Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(588, 'AEC102', 'Regulatory Framework and Legal Issues in Business', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(589, 'GE115-BMD', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(590, 'BME102-BSA', 'Operations Management and TQM', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(591, 'GE106-BMD', 'Science, Technology and Society', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(592, 'GE107-BMD', 'The Contemporary World', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(593, 'AEC104', 'Accounting Research Methods', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(594, 'ELEC1-BSA', 'Updates in Financial Reporting and Standards', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(595, 'APE108', 'Accounting for Government and Non-profit Organizations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(596, 'APE107', 'Accounting for Business Combinations', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(597, 'AEC114', 'Accounting Internship', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, 'Summer', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '3rd Year', '2026-03-05 01:03:52', 6, 0, 0, 0),
(598, 'APE101', 'Auditing and Assurance Principles', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(599, 'APE102', 'Auditing and Assurance: Concepts and Applications 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(600, 'APE103', 'Auditing and Assurance: Concepts and Applications 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(601, 'AEC106', 'Accountancy Research', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(602, 'APE106', 'Accounting for Special Transactions', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(603, 'APE109', 'Financial Accounting and Reporting Integration', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 6, 0, 0, 0),
(604, 'APE111', 'Taxation Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(605, 'APE112', 'Regulatory Framework for Business Transactions Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(606, 'APE113', 'Management Advisory Services Integration', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(607, 'APE104', 'Auditing and Assurance: Specialized Industries', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(608, 'APE105', 'Auditing in a CIS Environment', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Accountancy', '4th Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(609, 'SCP101', 'Introduction to Supply Chain Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(610, 'TMC100', 'Fundamentals of Customs and Tariff System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(611, 'SCP102', 'Warehouse Operations Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(612, 'BSNA101-CA', 'Fundamentals of Accounting, Business and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(613, 'GE103-CA', 'Art Appreciation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(614, 'BLT100', 'Business Law (Obligations, Negotiable Instruments, IP and Insurance Law)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(615, 'CMC100', 'Border Control and Security', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(616, 'SCP103', 'Procurement and Inventory Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(617, 'CMC101', 'Customs Operations and Cargo Handling', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(618, 'TMC101', 'Commodity Classification System', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(619, 'TMC106', 'International Trade Organizations, Agreements and Rules of Origin', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(620, 'BLT101', 'Taxation (Income and Business Taxation)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(621, 'SCP104', 'Transportation Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(622, 'CMC102', 'Customs Warehousing', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(623, 'TMC102', 'Customs Valuation System', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(624, 'BSNA104-CA', 'Business Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(625, 'CMC106', 'Ethics and Standards of the Customs Broker', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(626, 'CMC103', 'Customs Clearance', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(627, 'TMC103', 'Customs Appraisal and Assessment', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(628, 'BME100-CA', 'Operations Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(629, 'BME101-CA', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(630, 'CMC105', 'Customs Post Clearance Audit and Fraud Detection', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(631, 'CMC104', 'Customs Proceedings', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(632, 'TMC105', 'Special Duties and Trade Remedies', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(633, 'TMC104', 'Excise Taxes, Liquidation of Duty and Surcharges', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '3rd Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(634, 'CMC107', 'Competency Assessment in Customs Management', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(635, 'TMC107', 'Competency Assessment in Tariff Management', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(636, 'RSH100', 'Research 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(637, 'RSH101', 'Research 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(638, 'OJT100', 'Internship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Customs Administration', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(639, 'BME102-BSE', 'International Business and Trade', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(640, 'ECS101', 'Entrepreneurial Behavior', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(641, 'ECS102', 'Opportunity Seeking', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(642, 'ECS108', 'Microeconomics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(643, 'BME103-BSE', 'Human Resource Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(644, 'ECS107', 'Market Research and Consumer Behavior', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(645, 'ECS109', 'Business Law and Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(646, 'ECS114', 'Programs and Policies on Enterprise Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(647, 'BME104', 'Basic Accounting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(648, 'ECS111', 'Pricing and Costing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(649, 'BME100-BSE', 'Operations Management (Total Quality Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(650, 'EST101', 'Specialized Track 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(651, 'EEC101', 'Elective 1 (Supply Chain Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(652, 'ECS112', 'Innovation and Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(653, 'EST102', 'Specialized Track 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(654, 'EEC102', 'Elective 2 (E-Commerce)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(655, 'ECS103', 'Business Plan Preparation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(656, 'ECS110', 'Financial Management and Analysis for Decision Making', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(657, 'ECS113', 'Social Entrepreneurship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(658, 'BME101-BSE', 'Strategic Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(659, 'EST103', 'Specialized Track 3', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(660, 'EEC103', 'Elective 3 (Hospitality Management)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(661, 'ECS104', 'Business Plan Implementation 1', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', 2, 3, 0, 1),
(662, 'EST104', 'Specialized Track 4', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(663, 'EEC104', 'Elective 4 (Managing a Service Enterprise)', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(664, 'ECS105', 'Business Plan Implementation 2', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Entrepreneurship', '4th Year', '2026-03-05 01:03:52', 2, 3, 0, 1),
(665, 'RE-FUN013', 'Fundamentals of Real Estate Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(666, 'GE-ENG013', 'Conversational English Competency', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(667, 'GE-FIL013', 'Komunikasyon Sa Akademikong Filipino', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(668, 'GE-MAT013', 'College Algebra - Math 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(669, 'RE-TAX013', 'Business and Real Estate Taxation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(670, 'AC-TAX013', 'Economics with Taxation and Land Reform', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(671, 'GE-NSC013', 'Biological Science', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(672, 'RE-HGP013', 'Human and Physical Geography', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(673, 'GE-PHE012', 'Recreational Activities', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 2, 0, 0, 0),
(674, 'GE-NST013', 'National Service Training Program 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(675, 'BN-MGT013', 'Principles of Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(676, 'RE-REC013', 'Fundamentals of Real Estate Consulting', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(677, 'LW-BSN013', 'Law on Obligations and Contracts w/ Real Properties', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(678, 'GE-NSC023', 'Environment and Greenbuilding Technology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(679, 'GE-ENG023', 'Grammar and Composition', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(680, 'RE-PAD013', 'Real Estate Planning and Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(681, 'RE-REB013', 'Real Estate Brokerage', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(682, 'GE-FIL023', 'Pagbasa at pagsulat Tungo Sa Pananaliksik', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(683, 'GE-PHE032', 'Individual and Team Sports', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 2, 0, 0, 0),
(684, 'GE-NST023', 'National Service Training Program 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(685, 'BN-MKT013', 'Principles of Marketing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(686, 'RE-LAR013', 'Legal Aspects of Real Estate', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(687, 'GE-BAC013', 'Basic Accounting 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(688, 'RE-CSE013', 'Consulting for Specific Engagements', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(689, 'BN-ECO013', 'Macroeconomics and Microeconomics Theory & Practice', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(690, 'RE-REA013', 'Real Estate Appraisal and Property Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(691, 'IT-CSA013', 'Computer Software Application', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(692, 'GE-ENG033', 'Business Correspondence and Technical Writing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(693, 'GE-PHE052', 'Rhythmic Activities', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 2, 0, 0, 0),
(694, 'BN-FIN013', 'Basic Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(695, 'RE-MKB013', 'Real Estate Marketing and Brokerage', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(696, 'RE-CIA013', 'Real Estate Consulting and Investments Analysis', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(697, 'RE-PVS013', 'Philippine Valuation Studies for Real Estate', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(698, 'GE-SCF013', 'Society and Culture with Family Planning', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(699, 'RE-POE013', 'Principles of Ecology', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(700, 'GE-PSY013', 'General Psychology w/ Drug Education, SARS, HIV/AIDS', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0);
INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `instructor`, `faculty_id`, `schedule`, `day`, `time`, `room`, `capacity`, `enrolled_count`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`, `lec_units`, `lab_units`, `is_general`, `is_lab`) VALUES
(701, 'GE-BAC023', 'Basic Accounting 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(702, 'GE-PHE062', 'Sports and Games', 2, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '2nd Year', '2026-03-05 01:03:52', 2, 0, 0, 0),
(703, 'IT-DBM013', 'Database Management System 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(704, 'RE-PM013', 'Property Management System 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(705, 'GE-GCR013', 'Good Governance and Corporate Responsibility', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(706, 'RE-HSD013', 'Housing and Subdivision Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(707, 'GE-MAT053', 'Business Statistics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(708, 'GE-LCT013', 'Logic and Critical Thinking', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(709, 'RE-AGS013', 'Appraisal/Assessment in Government Sector', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(710, 'RE-REF013', 'Real Estate Finance', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(711, 'GE-PHC013', 'Philippine History and Culture', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(712, 'RE-ARD013', 'Appraisal Report and Data Gathering', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(713, 'RE-ESP013', 'Ethical Standards for Real Estate Practice', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(714, 'RE-REE013', 'Real Estate Economics', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(715, 'RE-CCD013', 'Condominium Concept and other Specialized Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(716, 'BN-HRM013', 'Human Resource Management', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(717, 'GE-APA013', 'Appreciation of Arts', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(718, 'GE-LWR013', 'Life and Works of Rizal', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(719, 'BN-HBO013', 'Human Behavior in Organization', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(720, 'GE-ENG053', 'Philippine Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '3rd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(721, 'RE-INR015', 'Integration and Review for Real Estate', 5, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '4th Year', '2026-03-05 01:03:52', 5, 0, 0, 0),
(722, 'GE-OJT013', 'On-the-job Training (600hrs)', 6, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Business Management Department (BMD)', 'Bachelor of Science in Real Estate Management', '4th Year', '2026-03-05 01:03:52', 6, 0, 0, 0),
(723, 'GE112', 'Pilipino: Retorika', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(724, 'EMC200', 'Free Hand and Digital Drawing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '1st Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(725, 'GE113', 'Pilipino: Pagsasalingwika', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '1st Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(726, 'GE114', 'Pilipino: Tula, Sanaysay, Nobela', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(727, 'EMC202', 'Computer Graphics Programming', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(728, 'EMC204', 'Principles of 2D Animation', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', 2, 1, 0, 1),
(729, 'OJT-CIMT', 'Internship', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(730, 'CAP501-CIMT', 'Capstone Project', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Computer Information Multimedia Technology', '2nd Year', '2026-03-05 01:03:52', 3, 0, 0, 0),
(2027, 'GE116', 'World Literature', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '1st Year', '2026-03-05 04:05:04', 3, 0, 0, 0),
(2028, 'EMC201', 'Introduction to Game Design and Development', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '2nd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2029, 'GD301', 'Game Programming 1', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '2nd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2030, 'GD302', 'Game Programming 2', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '2nd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2031, 'EMC205', 'Audio Design and Sound Engineering', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2032, 'GD303', 'Applied Mathematics for Games', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2033, 'GD305', 'Game Programming 3', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2034, 'GE102', 'Creative Writing', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', 3, 0, 0, 0),
(2035, 'EMC206', 'Scriptwriting and Story Board Design', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2036, 'EMC208', 'Design Production and Process', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2037, 'GD304', 'Applied Physics for Games', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2038, 'GD306', 'Artificial Intelligence in Games', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '3rd Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2039, 'GD307', 'Advance Game Design', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '4th Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2040, 'GD308', 'Game Networking', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '4th Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2041, 'GD309', 'Game Production', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '1st Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '4th Year', '2026-03-05 04:05:04', 2, 1, 0, 1),
(2042, 'ITRN', 'Internship (486 hours)', 9, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '2nd Semester', '', 'Information Communication and Technology (ICTD)', 'Bachelor of Science in Entertainment and Multimedia Computing', '4th Year', '2026-03-05 04:05:04', 9, 0, 0, 0);

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
  `remarks` varchar(20) DEFAULT 'In Progress',
  `grade_released` tinyint(1) DEFAULT 0,
  `grade_submitted` tinyint(1) DEFAULT 0,
  `grade_submitted_at` datetime DEFAULT NULL,
  `grade_released_at` datetime DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `program_levels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`program_levels`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`id`, `faculty_id`, `first_name`, `last_name`, `email`, `department`, `specialty`, `subjects`, `status`, `created_at`, `program_levels`) VALUES
(1, 'FAC-2024-001', 'Maria', 'Santos', 'maria.santos@school.edu', 'Information Technology', 'Web Development, It Specialist', '[\"CC100-IT\",\"CC101-IT\"]', 'Active', '2026-02-01 00:03:41', NULL),
(2, 'FAC-2024-002', 'Juan', 'Reyes', 'juan.reyes@school.edu', 'Information Technology', 'Database Systems', '[\"IT-CMT015-IT\"]', 'Active', '2026-02-01 00:03:41', NULL),
(3, 'FAC-2024-003', 'Anna', 'Garcia', 'anna.garcia@school.edu', 'Mathematics', 'Discrete Mathematics', '[\"GE105-IT\"]', 'Active', '2026-02-01 00:03:41', NULL),
(4, 'FAC-2024-004', 'Luis', 'Rodriguez', 'luis.rodriguez@school.edu', 'Information Technology', 'Software Engineering', '[\"PE1-IT\",\"NSTP1-IT\"]', 'Active', '2026-02-01 00:03:41', NULL),
(5, 'FAC-2024-005', 'Sarah', 'Kim', 'sarah.kim@school.edu', 'English', 'Technical Writing', '[\"GE100-IT\"]', 'Active', '2026-02-01 00:03:41', NULL),
(9, 'FAC-2024-007', 'Liza', 'Dela Cruz', 'liza.delacruz@school.edu', 'Information Technology', 'Systems and Architecture', '[\"IT110\",\"IT104\",\"IT-CMT015-IT\",\"EMC203-IT\",\"EMC207\",\"CC100\"]', 'Active', '2026-03-04 12:01:21', NULL),
(10, 'FAC-2024-008', 'Ramon', 'Villanueva', 'ramon.villanueva@school.edu', 'Information Technology', 'Capstone and Practicum', '[\"CAP501-IT\",\"OJT-BSIT\",\"ELEC401\",\"ELEC403\",\"ELEC103\",\"DM101\"]', 'Active', '2026-03-04 12:01:21', NULL),
(11, 'FAC-2024-006', 'Carlo', 'Mendoza', 'carlo.mendoza@school.edu', 'Business Administration', 'Accounting and Finance', '[\"AEC111\",\"AEC109\"]', 'Active', '2026-03-04 12:06:34', NULL);

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
(2026, 31);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `exam_period` varchar(30) DEFAULT NULL
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
(1, 'Bachelor of Science in Accountancy', 'BSA', 'College', 4, 'A professional program covering financial accounting, auditing, taxation, and management advisory services.', 'Business Management Department (BMD)', '2026-03-03 01:44:06'),
(2, 'Bachelor of Science in Customs Administration', 'BSCA', 'College', 4, 'A program focused on customs brokerage, tariff, trade, and border control management.', 'Business Management Department (BMD)', '2026-03-03 01:44:06'),
(3, 'Bachelor of Science in Entrepreneurship', 'BSE', 'College', 4, 'A program developing entrepreneurial skills, business planning, and enterprise management.', 'Business Management Department (BMD)', '2026-03-03 01:44:06'),
(4, 'Bachelor of Science in Real Estate Management', 'BSREM', 'College', 4, 'A program covering real estate appraisal, brokerage, property management, and real estate finance.', 'Business Management Department (BMD)', '2026-03-03 01:44:06'),
(5, 'Computer Information Multimedia Technology', 'CIMT', 'College', 2, 'A 2-year program in computing, multimedia, and digital arts technology.', 'Information Communication and Technology (ICTD)', '2026-03-03 01:44:06'),
(6, 'Bachelor of Science in Information Technology', 'BSIT', 'College', 4, 'A program in software development, networking, database systems, and information assurance.', 'Information Communication and Technology (ICTD)', '2026-03-03 01:44:06'),
(24, 'General Academic Strand (GAS)', 'GAS', 'SHS', 2, 'SHS strand offering a broad general academic curriculum for undecided learners.', 'Academic Track', '2026-03-03 01:48:50'),
(26, 'Information and Communication Technology (ICT)', 'ICT', 'SHS', 2, 'SHS TVL strand focused on computer and information technology skills.', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-03 01:48:50'),
(31, '3- Yrs. Diploma in Travel and Tourism Technology (Leading to BSTM)', 'DTTT', 'TVET', 2, 'A diploma program in travel and tourism technology that may lead to a BSTM degree.', 'Collge Diploma', '2026-03-03 01:48:50'),
(35, 'Housekeeping NCII', 'HK-NCII', 'TVET', 1, 'TESDA National Certificate II program in Housekeeping.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(36, 'Bartending NCII', 'BART-NCII', 'TVET', 1, 'TESDA National Certificate II program in Bartending.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(37, 'Food and Beverages Services NCII', 'FBS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Food and Beverages Services.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(38, 'Front Office Services NCII', 'FO-NCII', 'TVET', 1, 'TESDA National Certificate II program in Front Office services.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(40, 'Technical Drafting NCII', 'GP-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Game Programming.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(41, 'Computer Systems Servicing NCII', 'CSS-NCII-TVET', 'TVET', 1, 'TESDA National Certificate II program in Computer Systems Servicing.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(42, 'Visual Graphic Design NCIII', 'VGD-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Visual Graphic Design.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(43, 'Cookery NCII', 'TS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Travel Services.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(44, 'Tourism Promotion Services NCII', 'TPS-NCII', 'TVET', 1, 'TESDA National Certificate II program in Tourism Promotion Services.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(45, 'Event Management Services NCIII', 'EMS-NCIII', 'TVET', 1, 'TESDA National Certificate III program in Event Management Services.', 'Short Programs(NC)', '2026-03-03 01:48:50'),
(89, 'Humanities and Social Sciences Strand (HUMMS)', 'HUMMS', 'SHS', 2, '', 'Academic Track', '2026-03-05 02:58:51'),
(90, 'Home Economics (HE)', 'HE', 'SHS', 2, '', 'Technical-Vocational Livelihood Track (TVL)', '2026-03-05 03:03:31'),
(91, '3-Yrs. Diploma in Travel and Hospitality Management Technology (Leading to BSHM)', 'CDTHMT', 'TVET', 1, '', 'Collge Diploma', '2026-03-05 03:15:24'),
(92, 'Bread and Pastry Production NCII', 'BPP', 'TVET', 1, '', 'Short Programs(NC)', '2026-03-05 03:19:46'),
(93, '2-Yrs. Cruise Ship Management', '2YCSM', 'College', 4, '', 'Tourism and Hospitality Department (THD)', '2026-03-05 03:21:27'),
(94, 'Bachelor of Science in Hospitality Management', 'BSHM', 'College', 4, '', 'Tourism and Hospitality Department (THD)', '2026-03-05 03:22:05'),
(95, 'Bachelor of Science in Toursm Management', 'BSTM', 'College', 4, '', 'Tourism and Hospitality Department (THD)', '2026-03-05 03:22:44'),
(96, 'Bachelor of Science in Entertainment and Multimedia Computing', 'BSEMC', 'College', 4, 'A program integrating game development, 2D/3D animation, multimedia computing, and digital arts.', 'Information Communication and Technology (ICTD)', '2026-03-05 04:05:04');

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
(48, 1, 550),
(51, 1, 551),
(52, 1, 552),
(53, 1, 553),
(54, 1, 554),
(57, 1, 555),
(59, 1, 556),
(62, 1, 557),
(63, 1, 558),
(64, 1, 559),
(66, 1, 560),
(67, 1, 561),
(68, 1, 562),
(71, 1, 563),
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
(56, 2, 554),
(60, 2, 556),
(70, 2, 562),
(72, 2, 563),
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
(58, 3, 555),
(61, 3, 556),
(65, 3, 559),
(69, 3, 562),
(73, 3, 563),
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
(279, 5, 7),
(280, 5, 8),
(277, 5, 9),
(262, 5, 11),
(273, 5, 13),
(281, 5, 14),
(282, 5, 15),
(264, 5, 16),
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
(7, 6, 7),
(8, 6, 8),
(9, 6, 9),
(10, 6, 10),
(11, 6, 11),
(12, 6, 12),
(13, 6, 13),
(14, 6, 14),
(15, 6, 15),
(16, 6, 16),
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
(1383, 96, 1),
(1374, 96, 2),
(1369, 96, 3),
(1370, 96, 4),
(1371, 96, 5),
(1372, 96, 6),
(1375, 96, 7),
(1376, 96, 8),
(1380, 96, 9),
(1379, 96, 11),
(1398, 96, 13),
(1384, 96, 14),
(1385, 96, 15),
(1386, 96, 16),
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
('enrollment_period', '{\"is_open\":true,\"start\":\"2026-03-03T02:09\",\"end\":\"2026-03-13T15:09\",\"label\":\"1st sem ay 2025-2026\"}', '2026-03-04 17:12:04'),
('payment_due_dates', '{\"downpayment\":{\"label\":\"Downpayment\",\"date_range\":\"\"},\"prelim\":{\"label\":\"Prelim\",\"date_range\":\"JANUARY 14-16, 2026\"},\"midterm\":{\"label\":\"Midterm\",\"date_range\":\"FEBRUARY 10 - 14, 2026\"},\"finals\":{\"label\":\"Finals\",\"date_range\":\"MARCH 30 - APRIL 4, 2026\"}}', '2026-03-04 23:45:12');

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
(4, 'registrar@example.com', '$2y$12$mBRinZXeLFpyge/D499ceeBuVHsRqy6OiVNtDm.YuSdfAgVdFNrWG', 'registrar', 'Registrar', 'Admin', '2026-01-29 08:54:49'),
(136, 'maria.santos@school.edu', '$2y$12$Z.zv7NY/Kj9Mo3DlMC0YK.fgP/tWrQlq2OGxteKNgTesB8p2B4RaO', 'faculty', 'Maria', 'Santos', '2026-03-04 13:10:06'),
(137, 'juan.reyes@school.edu', 'faculty123', 'faculty', 'Juan', 'Reyes', '2026-03-04 13:10:06'),
(138, 'anna.garcia@school.edu', 'faculty123', 'faculty', 'Anna', 'Garcia', '2026-03-04 13:10:06'),
(139, 'luis.rodriguez@school.edu', 'faculty123', 'faculty', 'Luis', 'Rodriguez', '2026-03-04 13:10:06'),
(140, 'sarah.kim@school.edu', 'faculty123', 'faculty', 'Sarah', 'Kim', '2026-03-04 13:10:06'),
(141, 'liza.delacruz@school.edu', 'faculty123', 'faculty', 'Liza', 'Dela Cruz', '2026-03-04 13:10:06'),
(142, 'ramon.villanueva@school.edu', 'faculty123', 'faculty', 'Ramon', 'Villanueva', '2026-03-04 13:10:06'),
(143, 'carlo.mendoza@school.edu', 'faculty123', 'faculty', 'Carlo', 'Mendoza', '2026-03-04 13:10:06');

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
  ADD UNIQUE KEY `unique_course_code` (`code`),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2043;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=815;

--
-- AUTO_INCREMENT for table `exam_permits`
--
ALTER TABLE `exam_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `fee_config`
--
ALTER TABLE `fee_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `payment_notices`
--
ALTER TABLE `payment_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=517;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `program_courses`
--
ALTER TABLE `program_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1422;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=143;

--
-- AUTO_INCREMENT for table `student_grades`
--
ALTER TABLE `student_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1584;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exam_permits`
--
ALTER TABLE `exam_permits`
  ADD CONSTRAINT `exam_permits_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

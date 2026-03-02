-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 09:46 AM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `instructor`, `faculty_id`, `schedule`, `day`, `time`, `room`, `capacity`, `enrolled_count`, `semester`, `description`, `department`, `program`, `year_level`, `created_at`) VALUES
(1, 'CS111', 'Introduction to Programming', 3, 'Engr. Maria Santos', NULL, 'MWF 8:00 AM - 9:30 AM', 'Monday,Wednesday,Friday', '8:00 AM - 9:30 AM', 'Room 301 (Science Building)', 51, 36, '1st Semester, AY 2024-2025', 'Fundamentals of programming using Python', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(2, 'CS112', 'Web Development Basics', 3, 'Engr. Juan Reyes', NULL, 'TTh 10:00 AM - 11:30 AM', 'Tuesday,Thursday', '10:00 AM - 11:30 AM', 'Lab 202 (IT Building)', 35, 35, '1st Semester, AY 2024-2025', 'HTML, CSS, and JavaScript fundamentals', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(3, 'MATH101', 'Discrete Mathematics', 4, 'Engr. Anna Garcia', NULL, 'MWF 9:45 AM - 11:15 AM', 'Monday,Wednesday,Friday', '9:45 AM - 11:15 AM', 'Room 205 (Science Building)', 40, 24, '1st Semester, AY 2024-2025', 'Sets, logic, and mathematical proofs', 'Mathematics', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(5, 'ENG101', 'English Composition', 3, 'Prof. Sarah Kim', NULL, 'MWF 1:00 PM - 2:30 PM', 'Monday,Wednesday,Friday', '1:00 PM - 2:30 PM', 'Room 101 (Liberal Arts Building)', 45, 26, '1st Semester, AY 2024-2025', 'Academic writing and communication skills', 'English', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(18, 'IT101', 'Introduction to Computing', 3, 'Engr. Maria Santos', NULL, 'MWF 7:30 AM - 8:30 AM', 'Monday,Wednesday,Friday', '7:30 AM - 8:30 AM', 'Room 301 (IT Building)', 40, 9, '1st Semester, AY 2024-2025', 'Overview of computing concepts, history, and modern applications', 'Information Technology', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(19, 'IT102', 'Computer Programming 1', 3, 'Engr. Juan Reyes', NULL, 'TTh 7:30 AM - 9:00 AM', 'Tuesday,Thursday', '7:30 AM - 9:00 AM', 'Lab 101 (IT Building)', 35, 31, '1st Semester, AY 2024-2025', 'Fundamentals of programming using Python — logic, loops, functions', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(20, 'IT103', 'Computer Hardware Fundamentals', 3, 'Engr. Luis Rodriguez', NULL, 'MWF 9:00 AM - 10:00 AM', 'Monday,Wednesday,Friday', '9:00 AM - 10:00 AM', 'Lab 202 (IT Building)', 35, 31, '1st Semester, AY 2024-2025', 'Hardware components, assembly, troubleshooting, and maintenance', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(21, 'IT104', 'Web Development 1', 3, 'Engr. Anna Garcia', NULL, 'TTh 10:30 AM - 12:00 PM', 'Tuesday,Thursday', '10:30 AM - 12:00 PM', 'Lab 101 (IT Building)', 35, 19, '2nd Semester, AY 2024-2025', 'HTML5, CSS3, and responsive design fundamentals', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(22, 'MATH111', 'College Algebra', 3, 'Prof. Reyna Cruz', NULL, 'MWF 10:30 AM - 11:30 AM', 'Monday,Wednesday,Friday', '10:30 AM - 11:30 AM', 'Room 205 (Science Building)', 40, 5, '1st Semester, AY 2024-2025', 'Algebraic expressions, equations, functions, and graphing', 'Mathematics', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(23, 'MATH112', 'Discrete Mathematics', 3, 'Prof. Reyna Cruz', NULL, 'TTh 1:00 PM - 2:30 PM', 'Tuesday,Thursday', '1:00 PM - 2:30 PM', 'Room 205 (Science Building)', 40, 5, '1st Semester, AY 2024-2025', 'Sets, logic, relations, functions, and graph theory', 'Mathematics', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(24, 'GE101', 'Purposive Communication', 3, 'Prof. Sarah Kim', NULL, 'MWF 1:00 PM - 2:00 PM', 'Monday,Wednesday,Friday', '1:00 PM - 2:00 PM', 'Room 101 (Liberal Arts)', 45, 23, '1st Semester, AY 2024-2025', 'Academic and professional communication skills', 'English', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(25, 'GE102', 'Understanding the Self', 3, 'Prof. James Lim', NULL, 'TTh 3:00 PM - 4:30 PM', 'Tuesday,Thursday', '3:00 PM - 4:30 PM', 'Room 102 (Liberal Arts)', 45, 6, '1st Semester, AY 2024-2025', 'Self-development, identity, and human values', 'General Education', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(26, 'GE103', 'Readings in Philippine History', 3, 'Prof. Maria Reyes', NULL, 'MWF 2:30 PM - 3:30 PM', 'Monday,Wednesday,Friday', '2:30 PM - 3:30 PM', 'Room 103 (Liberal Arts)', 45, 5, '1st Semester, AY 2024-2025', 'Critical reading of primary sources in Philippine history', 'General Education', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(27, 'PE101', 'Physical Fitness and Wellness', 2, 'Coach Robert Lee', NULL, 'Saturday 8:00 AM - 10:00 AM', 'Saturday', '8:00 AM - 10:00 AM', 'Sports Complex', 60, 5, '1st Semester, AY 2024-2025', 'Physical fitness, health, and wellness activities', 'Physical Education', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(28, 'NSTP101', 'National Service Training Program 1', 3, 'Capt. Jose Dela Rosa', NULL, 'Saturday 1:00 PM - 4:00 PM', 'Saturday', '1:00 PM - 4:00 PM', 'Auditorium', 80, 6, '2nd Semester, AY 2024-2025', 'Civic welfare, literacy training, and reserve officers training', 'General Education', 'BS Information Technology', '2nd Year', '2026-01-31 13:46:28'),
(30, 'dssdsd', 'sdsd', 3, NULL, NULL, NULL, NULL, NULL, NULL, 40, 0, '', 'sdsds', 'Computer Science', 'sdssd', '1st Year', '2026-02-01 00:47:38'),
(31, 'c2', 'asd', 3, '', NULL, NULL, '', '', '', 40, 0, '', 'sdd', 'Information Technology', 'sdsd', '1st Year', '2026-02-01 01:31:16');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `enrollment_date`, `status`, `grade`, `semester`, `notes`, `created_at`) VALUES
(68, 53, 1, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(69, 53, 2, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(70, 53, 3, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(71, 53, 5, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(72, 53, 18, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(206, 76, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:04:57'),
(207, 76, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:04:57'),
(208, 76, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:04:57'),
(209, 76, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:04:57'),
(210, 76, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:04:57'),
(211, 76, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:04:57'),
(212, 76, 21, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:04:57'),
(213, 76, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:04:57'),
(214, 77, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:24:01'),
(215, 77, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:24:01'),
(216, 77, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:24:01'),
(217, 77, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:24:02'),
(218, 77, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:24:02'),
(219, 77, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:24:02'),
(220, 77, 21, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:24:02'),
(221, 77, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 14:24:02'),
(222, 78, 21, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 15:01:57'),
(223, 79, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:18:14'),
(224, 79, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:18:14'),
(225, 79, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:18:14'),
(226, 79, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:18:14'),
(227, 79, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:18:14'),
(228, 79, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:18:15'),
(229, 79, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:18:15'),
(230, 80, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:35:21'),
(231, 80, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:35:21'),
(232, 80, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:35:22'),
(233, 80, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:35:22'),
(234, 80, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:35:22'),
(235, 80, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:35:22'),
(236, 80, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 15:35:22'),
(237, 82, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 18:51:27'),
(238, 82, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 18:51:27'),
(239, 82, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 18:51:28'),
(240, 82, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 18:51:28'),
(241, 82, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 18:51:28'),
(242, 82, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 18:51:28'),
(243, 82, 21, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 18:51:28'),
(244, 82, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 18:51:28'),
(245, 83, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 19:04:37'),
(246, 83, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 19:04:37'),
(247, 83, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 19:04:37'),
(248, 83, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 19:04:37'),
(249, 83, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 19:04:37'),
(250, 83, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 19:04:37'),
(251, 83, 21, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 19:04:38'),
(252, 83, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 19:04:38'),
(257, 85, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:32:23'),
(258, 85, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:32:23'),
(259, 85, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:32:24'),
(260, 85, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:32:24'),
(261, 85, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:32:24'),
(262, 85, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:32:24'),
(263, 85, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:32:24'),
(264, 86, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:34:09'),
(265, 86, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:34:09'),
(266, 86, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:34:09'),
(267, 86, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:34:09'),
(268, 86, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:34:09'),
(269, 86, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:34:09'),
(270, 86, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:34:10'),
(271, 86, 21, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:34:13'),
(272, 87, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:36:41'),
(273, 87, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:36:41'),
(274, 87, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:36:41'),
(275, 87, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:36:41'),
(276, 87, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:36:42'),
(277, 87, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:36:42'),
(278, 87, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:36:42'),
(279, 88, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:39:42'),
(280, 88, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:39:42'),
(281, 88, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:39:42'),
(282, 88, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:39:42'),
(283, 88, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:39:43'),
(284, 88, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:39:43'),
(285, 88, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:39:43'),
(286, 88, 21, '2026-03-01', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-01 19:39:44'),
(291, 90, 5, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:08:45'),
(292, 90, 24, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:08:46'),
(293, 90, 3, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:08:46'),
(294, 90, 1, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:17:01'),
(295, 90, 2, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:17:01'),
(296, 90, 19, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:17:01'),
(297, 90, 20, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:17:01'),
(298, 91, 5, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:25:32'),
(299, 91, 24, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:25:32'),
(300, 91, 3, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:25:33'),
(301, 91, 1, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:26:27'),
(302, 91, 2, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:26:27'),
(303, 91, 19, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:26:27'),
(304, 91, 20, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:26:27'),
(305, 92, 5, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:58:38'),
(306, 92, 24, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:58:38'),
(307, 92, 3, '2026-03-02', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-02 02:58:38'),
(308, 92, 1, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:59:41'),
(309, 92, 2, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:59:42'),
(310, 92, 19, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:59:42'),
(311, 92, 20, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled (Transferee)', '2026-03-02 02:59:42'),
(312, 95, 1, '2026-03-02', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled', '2026-03-02 04:48:41'),
(313, 95, 2, '2026-03-02', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled', '2026-03-02 04:48:41'),
(314, 95, 3, '2026-03-02', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled', '2026-03-02 04:48:41'),
(315, 95, 5, '2026-03-02', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled', '2026-03-02 04:48:42'),
(316, 95, 19, '2026-03-02', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled', '2026-03-02 04:48:42'),
(317, 95, 20, '2026-03-02', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled', '2026-03-02 04:48:42'),
(318, 95, 24, '2026-03-02', 'Enrolled', NULL, '1st Semester, AY 2026-2027', 'Auto-enrolled', '2026-03-02 04:48:42'),
(319, 96, 1, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:34:15'),
(320, 96, 2, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:34:15'),
(321, 96, 3, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:34:15'),
(322, 96, 5, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:34:15'),
(323, 96, 19, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:34:15'),
(324, 96, 20, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:34:15'),
(325, 96, 24, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:34:15'),
(326, 97, 1, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:38:51'),
(327, 97, 2, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:38:51'),
(328, 97, 3, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:38:51'),
(329, 97, 5, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:38:51'),
(330, 97, 19, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:38:51'),
(331, 97, 20, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:38:51'),
(332, 97, 24, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:38:51'),
(333, 98, 1, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:43:55'),
(334, 98, 2, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:43:56'),
(335, 98, 3, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:43:56'),
(336, 98, 5, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:43:56'),
(337, 98, 19, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:43:56'),
(338, 98, 20, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:43:56'),
(339, 98, 24, '2026-03-02', 'Enrolled', NULL, '1st Semester', 'Auto-enrolled', '2026-03-02 05:43:56');

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
(1, 65, 39, 'OR-20260001', 'OR', 25683.00, '2026-03-01', 'Cash', '', 'Full', '', 3, '2026-03-01 11:51:49'),
(2, 66, 40, 'OR-20260002', 'OR', 11901.00, '2026-03-01', 'Cash', '', 'Full', '', 3, '2026-03-01 12:16:49'),
(3, 53, 27, 'OR-20260003', 'OR', 64874.00, '2026-02-01', 'Cash', '', 'Full', '', 46, '2026-03-01 12:16:57'),
(4, 67, 41, 'OR-20260004', 'OR', 23683.00, '2026-03-01', 'Cash', '', 'Full', '', 59, '2026-03-01 12:31:46'),
(5, 68, 42, 'OR-20260005', 'OR', 23500.00, '2026-03-01', 'Cash', '', 'Full', '', 60, '2026-03-01 12:37:36'),
(6, 69, 43, 'OR-20260006', 'OR', 6833.00, '2026-03-01', 'Cash', '', 'Full', '', 61, '2026-03-01 12:44:49'),
(7, 70, 44, 'AR-20260007', 'AR', 8391.00, '2026-03-01', 'Cash', '', 'Downpayment', '', 62, '2026-03-01 13:14:53'),
(8, 71, 45, 'OR-20260008', 'OR', 6833.00, '2026-03-01', 'Cash', '', 'Full', '', 3, '2026-03-01 13:28:26'),
(9, 72, 46, 'OR-20260009', 'OR', 5000.00, '2026-03-01', 'Cash', '', 'Full', '', 64, '2026-03-01 13:40:01'),
(10, 73, 47, 'OR-20260010', 'OR', 6609.00, '2026-03-01', 'Cash', '', 'Full', '', 3, '2026-03-01 13:51:21'),
(11, 75, 49, 'AR-20260011', 'AR', 8391.00, '2026-03-01', 'Cash', '', 'Downpayment', '', 67, '2026-03-01 13:56:15'),
(12, 74, 50, 'AR-20260012', 'AR', 6609.00, '2026-03-01', 'Cash', '', 'Downpayment', '', 67, '2026-03-01 13:59:41'),
(13, 76, 51, 'OR-20260013', 'OR', 32813.00, '2026-03-01', 'Cash', '', 'Full', '', 68, '2026-03-01 14:04:53'),
(14, 77, 52, 'OR-20260014', 'OR', 32813.00, '2026-03-01', 'GCash', '112345', 'Full', '', 3, '2026-03-01 14:23:51'),
(15, 78, 53, 'OR-20260015', 'OR', 32813.00, '2026-03-01', 'GCash', 'ewewewe', 'Full', '', 3, '2026-03-01 15:01:55'),
(16, 79, 54, 'OR-20260016', 'OR', 32813.00, '2026-03-01', 'GCash', '123', 'Full', '', 3, '2026-03-01 15:18:09'),
(17, 80, 55, 'OR-20260017', 'OR', 30674.00, '2026-03-01', 'GCash', '12345', 'Full', '', 72, '2026-03-01 15:34:31'),
(18, 81, 56, 'OR-20260018', 'OR', 30674.00, '2026-03-01', 'Cash', '', 'Full', '', 3, '2026-03-01 15:50:49'),
(19, 82, 57, 'AR-20260019', 'AR', 33563.00, '2026-03-01', 'GCash', '2323323', 'Downpayment', '', 74, '2026-03-01 18:51:20'),
(20, 83, 58, 'AR-20260020', 'AR', 8391.00, '2026-03-01', 'Cash', '', 'Downpayment', '', 75, '2026-03-01 18:56:58'),
(21, 84, 59, 'AR-20260021', 'AR', 5599.00, '2026-03-01', 'GCash', '1323434324', 'Downpayment', '', 76, '2026-03-01 19:09:25'),
(22, 85, 60, 'OR-20260022', 'OR', 28774.00, '2026-03-01', 'GCash', '1212345', 'Full', '', 77, '2026-03-01 19:32:20'),
(23, 86, 61, 'OR-20260023', 'OR', 28774.00, '2026-03-01', 'Cash', '', 'Full', '', 78, '2026-03-01 19:34:05'),
(24, 87, 62, 'AR-20260024', 'AR', 8391.00, '2026-03-01', 'GCash', '123456', 'Downpayment', '', 79, '2026-03-01 19:36:35'),
(25, 88, 63, 'AR-20260025', 'AR', 8391.00, '2026-03-01', 'Cash', '', 'Downpayment', '', 80, '2026-03-01 19:39:37'),
(26, 89, 64, 'AR-20260026', 'AR', 5777.00, '2026-03-02', 'GCash', '23107e', 'Downpayment', '', 3, '2026-03-02 01:46:48'),
(27, 90, 65, 'AR-20260027', 'AR', 6134.00, '2026-03-02', 'Cash', '', 'Downpayment', '', 84, '2026-03-02 02:09:49'),
(28, 91, 66, 'AR-20260028', 'AR', 5599.00, '2026-03-02', 'Cash', '', 'Downpayment', '', 85, '2026-03-02 02:26:22'),
(29, 92, 67, 'AR-20260029', 'AR', 6800.00, '2026-03-02', 'GCash', '1234577', 'Downpayment', '', 86, '2026-03-02 02:59:41'),
(30, 95, 70, 'OR-20260030', 'OR', 28774.00, '2026-03-02', 'Cash', '', 'Full', '', 3, '2026-03-02 04:48:39'),
(31, 96, 73, 'OR-20260031', 'OR', 28774.00, '2026-03-02', 'GCash', '1234567', 'Full', '', 3, '2026-03-02 05:34:10'),
(32, 97, 74, 'OR-20260032', 'OR', 30000.00, '2026-03-02', 'Cash', '', 'Full', '', 3, '2026-03-02 05:38:45'),
(33, 98, 75, 'OR-20260033', 'OR', 28774.00, '2026-03-02', 'Cash', '', 'Full', '', 3, '2026-03-02 05:43:51');

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
(27, 53, 'Cash', '', 64874.00, '2026-02-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 46, '2026-02-01 01:23:00', '', 0, NULL, NULL, 0.00, '2026-02-01 01:22:28'),
(51, 76, 'Cash', '', 32813.00, '2026-03-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 68, '2026-03-01 14:04:53', '', 0, NULL, NULL, 0.00, '2026-03-01 14:04:20'),
(52, 77, 'GCash', '112345', 32813.00, '2026-03-01', 'TXN-1772375015405-INXJA', '', 'Verified', 3, '2026-03-01 14:23:51', '', 0, NULL, NULL, 0.00, '2026-03-01 14:23:35'),
(53, 78, 'GCash', 'ewewewe', 32813.00, '2026-03-01', 'TXN-1772377285217-TYHB3', '', 'Verified', 3, '2026-03-01 15:01:54', '', 0, NULL, NULL, 0.00, '2026-03-01 15:01:25'),
(54, 79, 'GCash', '123', 32813.00, '2026-03-01', 'TXN-1772378272553-ZZ5IU', '', 'Verified', 3, '2026-03-01 15:18:09', '', 0, NULL, NULL, 0.00, '2026-03-01 15:17:52'),
(55, 80, 'GCash', '12345', 30674.00, '2026-03-01', 'TXN-1772379239185-ZNWYA', '', 'Verified', 72, '2026-03-01 15:34:31', '', 0, NULL, NULL, 0.00, '2026-03-01 15:33:59'),
(56, 81, 'Cash', '', 30674.00, '2026-03-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-03-01 15:50:49', '', 0, NULL, NULL, 0.00, '2026-03-01 15:50:06'),
(57, 82, 'GCash', '2323323', 33563.00, '2026-03-01', 'TXN-1772391066298-CSSPJ', '', 'Verified', 74, '2026-03-01 18:51:20', '', 0, NULL, NULL, 0.00, '2026-03-01 18:48:54'),
(58, 83, 'Cash', '', 8391.00, '2026-03-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 75, '2026-03-01 18:56:58', '', 0, NULL, NULL, 0.00, '2026-03-01 18:54:06'),
(59, 84, 'GCash', '1323434324', 5599.00, '2026-03-01', 'TXN-1772392148210-68XDX', '1st Semester', 'Verified', 76, '2026-03-01 19:09:25', '', 0, NULL, NULL, 0.00, '2026-03-01 19:09:08'),
(60, 85, 'GCash', '1212345', 28774.00, '2026-03-01', 'TXN-1772393523407-8RTZ8', '1st Semester', 'Verified', 77, '2026-03-01 19:32:20', '', 0, NULL, NULL, 0.00, '2026-03-01 19:32:03'),
(61, 86, 'Cash', '', 28774.00, '2026-03-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 78, '2026-03-01 19:34:05', '', 0, NULL, NULL, 0.00, '2026-03-01 19:33:43'),
(62, 87, 'GCash', '123456', 8391.00, '2026-03-01', 'TXN-1772393780845-EMUDC', '1st Semester', 'Verified', 79, '2026-03-01 19:36:35', '', 0, NULL, NULL, 0.00, '2026-03-01 19:36:20'),
(63, 88, 'Cash', '', 8391.00, '2026-03-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 80, '2026-03-01 19:39:36', '', 0, NULL, NULL, 0.00, '2026-03-01 19:38:54'),
(64, 89, 'GCash', '23107e', 5777.00, '2026-03-02', 'TXN-1772415980555-Q9HYV', '1st Semester', 'Verified', 3, '2026-03-02 01:46:47', '', 0, NULL, NULL, 0.00, '2026-03-02 01:46:20'),
(65, 90, 'Cash', '', 6134.00, '2026-03-02', NULL, '1st Semester, AY 2024-2025', 'Verified', 84, '2026-03-02 02:09:48', '', 0, NULL, NULL, 0.00, '2026-03-02 02:09:33'),
(66, 91, 'Cash', '', 5599.00, '2026-03-02', NULL, '1st Semester, AY 2024-2025', 'Verified', 85, '2026-03-02 02:26:22', '', 0, NULL, NULL, 0.00, '2026-03-02 02:26:06'),
(67, 92, 'GCash', '1234577', 6800.00, '2026-03-02', 'TXN-1772420371059-K6AOL', '1st Semester', 'Verified', 86, '2026-03-02 02:59:41', '', 0, NULL, NULL, 0.00, '2026-03-02 02:59:31'),
(68, 93, 'Cash', '', 0.00, NULL, NULL, '1st Semester, AY 2026-2027', 'Rejected', 3, '2026-03-02 05:00:59', 'wewe', 0, NULL, NULL, 0.00, '2026-03-02 03:48:46'),
(69, 94, 'Cash', '', 0.00, NULL, NULL, '1st Semester, AY 2026-2027', 'Rejected', 3, '2026-03-02 05:00:56', 'ae', 0, NULL, NULL, 0.00, '2026-03-02 03:51:10'),
(70, 95, 'Cash', '', 28774.00, '2026-03-02', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-03-02 04:48:39', '', 0, NULL, NULL, 0.00, '2026-03-02 03:58:29'),
(71, 93, 'Cash', '', 0.00, NULL, NULL, '1st Semester, AY 2024-2025', 'Pending', NULL, NULL, NULL, 0, NULL, NULL, 0.00, '2026-03-02 05:30:33'),
(72, 94, 'Cash', '', 0.00, NULL, NULL, '1st Semester, AY 2024-2025', 'Pending', NULL, NULL, NULL, 0, NULL, NULL, 0.00, '2026-03-02 05:30:33'),
(73, 96, 'GCash', '1234567', 28774.00, '2026-03-02', 'TXN-1772429615020-YBJBH', '1st Semester', 'Verified', 3, '2026-03-02 05:34:10', '', 0, NULL, NULL, 0.00, '2026-03-02 05:33:35'),
(74, 97, 'Cash', '', 30000.00, '2026-03-02', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-03-02 05:38:45', '', 0, NULL, NULL, 0.00, '2026-03-02 05:37:23'),
(75, 98, 'Cash', '', 28774.00, '2026-03-02', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-03-02 05:43:51', '', 0, NULL, NULL, 0.00, '2026-03-02 05:43:25');

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
  `prelim_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `midterm_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `finals_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prelim_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `midterm_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `finals_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prelim_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `midterm_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `finals_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_schedules`
--

INSERT INTO `payment_schedules` (`id`, `student_id`, `payment_type`, `total_assessment`, `prelim_due`, `midterm_due`, `finals_due`, `prelim_paid`, `midterm_paid`, `finals_paid`, `prelim_status`, `midterm_status`, `finals_status`, `created_at`, `updated_at`) VALUES
(1, 92, 'installment', 22394.00, 8957.60, 6718.20, 6718.20, 0.00, 0.00, 0.00, 'unpaid', 'unpaid', 'unpaid', '2026-03-02 07:43:36', '2026-03-02 07:43:36'),
(4, 85, 'installment', 28774.00, 11509.60, 8632.20, 8632.20, 0.00, 0.00, 0.00, 'unpaid', 'unpaid', 'unpaid', '2026-03-02 07:58:24', '2026-03-02 07:58:24');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `code` varchar(30) NOT NULL,
  `level_type` enum('College','SHS') DEFAULT 'College',
  `duration` int(2) DEFAULT 4 COMMENT 'Years (College) or 2 (SHS)',
  `description` text DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `name`, `code`, `level_type`, `duration`, `description`, `department`, `created_at`) VALUES
(1, 'BS Information Technology', 'BSIT', 'College', 4, 'Bachelor of Science in Information Technology', 'Information and Communication Technology', '2026-02-01 00:03:41'),
(2, 'BS Computer Science', 'BSCS', 'College', 4, 'Bachelor of Science in Computer Science', 'Information and Communication Technology', '2026-02-01 00:03:41'),
(3, 'BS Information Systems', 'BSIS', 'College', 4, 'Bachelor of Science in Information Systems', 'Information and Communication Technology', '2026-02-01 00:03:41'),
(4, 'STEM Strand', 'STEM', 'SHS', 2, 'Science, Technology, Engineering and Mathematics', 'Academic Track', '2026-02-01 00:03:41'),
(5, 'ABM Strand', 'ABM', 'SHS', 2, 'Accountancy, Business and Management', 'Academic Track', '2026-02-01 00:03:41'),
(6, 'HUMSS Strand', 'HUMSS', 'SHS', 2, 'Humanities and Social Sciences', 'Academic Track', '2026-02-01 00:03:41'),
(14, 'BSIT', 'JSPas', 'College', 4, 'Sasa', 'TOURISM', '2026-02-01 00:37:18'),
(15, 'gre', 'rg', '', 4, 'rt', '', '2026-02-01 01:01:45'),
(16, 'sdd', 'dsds', '', 4, 'ds', 'sd', '2026-02-01 01:02:43');

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
(1, 1, 1),
(2, 1, 2),
(6, 1, 3),
(4, 1, 5),
(5, 1, 18),
(16, 1, 21),
(15, 1, 28),
(18, 2, 1),
(17, 2, 2),
(20, 2, 3),
(19, 2, 5),
(22, 2, 19),
(23, 2, 20),
(24, 2, 21),
(21, 2, 24),
(12, 15, 30),
(13, 16, 1);

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
  `guardian_contact` varchar(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_number`, `first_name`, `last_name`, `middle_name`, `suffix`, `lrn_no`, `sex`, `religion`, `age`, `place_of_birth`, `citizenship`, `mother_tongue`, `is_indigenous`, `has_special_needs`, `special_needs_details`, `has_assistive_tech`, `assistive_tech_details`, `strand`, `learning_delivery`, `last_school_attended`, `psa_birth_cert_no`, `guardian_name`, `guardian_address`, `email`, `phone`, `date_of_birth`, `address`, `emergency_contact`, `emergency_phone`, `program`, `year_level`, `gpa`, `enrollment_status`, `student_type`, `tor_eval_status`, `student_category`, `payment_status`, `approval_status`, `payment_method`, `payment_plan`, `semester`, `is_scholar`, `scholar_type`, `scholar_grantor`, `scholarship_amount`, `gcash_reference`, `gcash_amount`, `gcash_date`, `gcash_transaction_id`, `accounting_approved_by`, `accounting_approved_at`, `accounting_notes`, `profile_picture`, `tor_file`, `psa_file`, `enrollment_date`, `created_at`, `guardian_contact`) VALUES
(53, 3, 'STU-2026-0001', 'Shane Carlo', 'Nodado', 'Binoya', '', 'adsd', 'Male', 'sdsd', 12, 'sdd', 'sds', 'sdsd', 0, 0, '0', 0, '0', '', '', 'sdd', '0', 'sd', 'sd', 'accounting@example.com', '09300987316', '0022-11-12', '118 Avocado Street Purok 3 New Cabalan', 'sd', 'sd', 'BSIT', '1st Year', 0.00, 'Enrolled', 'Transferee', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 46, '2026-02-01 01:23:00', '', NULL, NULL, NULL, '2026-02-01', '2026-02-01 01:22:28', 'sd'),
(76, 68, 'STU-2026-0002', 'Shane', 'new cash payment', 'sddd', '', 'sdsdsd', 'Male', 'sdds', 23, 'sdsd', 'sdsd', '0', 0, 0, '0', 0, '0', '', '', 'gordon college', '12321', 'Shane Gongora', '118 Avocado City', 'fullpaymentcash', '2323132', '2002-11-22', '118 Avocado City', 'Shane Gongora', '09300987316', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 68, '2026-03-01 14:04:53', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 14:04:20', '09300987316'),
(77, 69, 'STU-2026-0003', 'gcash', 'full payment', 'Carlo', '', 'sdawdsd', 'Male', 'sddssd', 19, 'sds', 'sdsd', '0', 0, 0, '0', 0, '0', '', '', 'sdsdsssd', 'sdd', 'Shane Gongora', '118 Avocado City', 'fullpaymentgcash1', '2232323', '2002-11-22', 'sdsd', 'Shane Gongora', '232332', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'full', '', 0, '', '', 0.00, '112345', 32813.00, '2026-03-01', 'TXN-1772375015405-INXJA', 3, '2026-03-01 14:23:51', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 14:23:25', '232332'),
(78, 70, 'STU-2026-0004', 'shane', 'trnsferee cash', 'carlo', '', 'aeedDSdsd', 'Male', 'sdsd', 23, 'sdds', 'sds', '0', 0, 0, '0', 0, '0', '', '', 'gordon college', '123323sds', 'shane carlo binoya', 'dsssdad', 'fullgcash', '0930938363', '2002-11-22', 'sdsds', 'shane carlo binoya', '0930097272', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'full', '2nd Semester, AY 2025-2026', 0, '', '', 0.00, 'ewewewe', 32813.00, '2026-03-01', 'TXN-1772377285217-TYHB3', 3, '2026-03-01 15:01:55', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 15:01:19', '0930097272'),
(79, 71, 'STU-2026-0005', 'shane', 'binoya', 'carlo', '', 'ddaddd', 'Male', 'dsdsfsdfd', 19, 'adadssd', 'sdsds', '0', 0, 0, '0', 0, '0', '', '', 'gordoncollege', '23232', 'shane carlo binoya', 'dsdsdsdsd', 'fullgcash1', '09300938373', '2002-11-20', '23233', 'shane carlo binoya', '093009383763', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester', 0, '', '', 0.00, '123', 32813.00, '2026-03-01', 'TXN-1772378272553-ZZ5IU', 3, '2026-03-01 15:18:09', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 15:17:43', '093009383763'),
(80, 72, 'STU-2026-0006', 'shane', 'binoya', 'carlo', '', '1234', 'Male', 'dsddss', 23, 'dsdsd', 'dsds', '0', 0, 0, '0', 0, '0', '', '', 'fsdds', 'sddd', 'shane carlo binoya', '23dsrr', 'fullgcash11', '093903983', '2002-11-15', 'adasdds', 'shane carlo binoya', '09348948732', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester', 0, '', '', 0.00, '12345', 30674.00, '2026-03-01', 'TXN-1772379239185-ZNWYA', 72, '2026-03-01 15:34:31', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 15:27:06', '09348948732'),
(81, 73, 'STU-2026-0007', 'shane', 'binoya', 'carlo', '', '12345', 'Male', 'dasdsdsdw', 19, 'awddssd', 'sddsdad', 'sddsdsds', 0, 0, '', 0, '', '', '', 'gordon college', '123456', 'shane carlo binoya', 'olongapo', 'fullcash2', '0930098731', '2002-11-22', 'adsdsd', 'shane carlo binoya', '092038332', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '1st Semester', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-01 15:50:49', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 15:50:06', '092038332'),
(82, 74, 'STU-2026-0008', 'Shane', 'Gongora', 'Carlo', '', '212356', 'Male', 'dsss', 19, 'ddsa', 'sdsds', '0', 0, 0, '0', 0, '0', '', '', 'dsds', 'sds', 'Shane Gongora', '118 Avocado City', 'gcashinstallment', '232323293', '2002-11-22', '118 Avocado City', 'Shane Gongora', '23123232', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'installment', '', 0, '', '', 0.00, '2323323', 33563.00, '2026-03-01', 'TXN-1772391066298-CSSPJ', 74, '2026-03-01 18:51:21', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 18:48:06', '23123232'),
(83, 75, 'STU-2026-0009', 'Shane', 'cashinstallment', 'Carlo', '', 'wrrwew', 'Male', 'dsadssd', 22, 'rfddgh', 'fdsffdsf', '0', 0, 0, '0', 0, '0', '', '', 'dsdad', 'adssdad', 'Shane Gongora', '118 Avocado City', 'cashinstallment', '0988977987', '2002-11-22', '118 Avocado City', 'Shane Gongora', '232324567', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'installment', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 75, '2026-03-01 18:56:59', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 18:54:06', '232324567'),
(84, 76, 'STU-2026-0010', 'installment', 'gcashtrans', 'nso', '', '23232', 'Male', 'sdsdds', 22, 'sdsds', 'sddss', '0', 0, 0, '0', 0, '0', '', '', 'gordoncollege', '1426t373', 'Shane Gongora', '118 Avocado City', 'gcashtrans', 'd2332235324', '2002-11-01', 'sddsd', 'Shane Gongora', '907387393', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Pending', 'College', 'Paid', 'Approved', 'GCash', 'installment', '1st Semester', 0, '', '', 0.00, '1323434324', 5599.00, '2026-03-01', 'TXN-1772392148210-68XDX', 76, '2026-03-01 19:09:26', '', NULL, 'tor_84_1772393222.pdf', NULL, '2026-03-01', '2026-03-01 19:07:49', '907387393'),
(85, 77, 'STU-2026-0011', 'Shane', 'Gongora', 'Carlo', '', 'wewe', 'Male', 'dssd', 22, 'sdads', 'dsd', '0', 0, 0, '0', 0, '0', '', '', 'dsds', 'sdds', 'Shane Gongora', '118 Avocado City', 'qweasd', '09303903833', '2002-11-22', '118 Avocado City', 'Shane Gongora', '413222434', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester', 0, '', '', 0.00, '1212345', 28774.00, '2026-03-01', 'TXN-1772393523407-8RTZ8', 77, '2026-03-01 19:32:20', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 19:31:49', '413222434'),
(86, 78, 'STU-2026-0012', 'Shane', 'Gongora', 'Carlo', '', 'dddsasa', 'Male', 'sdsd', 22, 'sddsads', 'sdsds', '0', 0, 0, '0', 0, '0', '', '', 'dsssd', 'sdds', 'Shane Gongora', '118 Avocado City', 'qweasd1', '3223233', '2222-11-22', '118 Avocado City', 'Shane Gongora', 'dsdfsfsfs', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '1st Semester', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 78, '2026-03-01 19:34:05', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 19:33:43', 'dsdfsfsfs'),
(87, 79, 'STU-2026-0013', 'Shane', 'Gongora', 'Carlo', '', '1234567', 'Male', 'sdsd', 19, 'sdsd', 'sdds', '0', 0, 0, '0', 0, '0', '', '', 'dsdsd', '2323442', 'Shane Gongora', '118 Avocado City', 'qweasd2', '23232', '2002-11-22', '118 Avocado City', 'Shane Gongora', '0938383632', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'installment', '1st Semester', 0, '', '', 0.00, '123456', 8391.00, '2026-03-01', 'TXN-1772393780845-EMUDC', 79, '2026-03-01 19:36:35', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 19:35:52', '0938383632'),
(88, 80, 'STU-2026-0014', 'Shane', 'Gongora', 'Carlo', '', '1234567', 'Male', 'sdsdsd', 19, 'sdsdsd', 'fdsfefsfd', '0', 0, 0, '0', 0, '0', '', '', 'gordoncollege', '1234', 'Shane Gongora', '118 Avocado City', 'qweasd3', '323234', '2002-11-22', '118 Avocado City', 'Shane Gongora', '23345', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'installment', '1st Semester', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 80, '2026-03-01 19:39:37', '', NULL, NULL, NULL, '2026-03-01', '2026-03-01 19:38:54', '23345'),
(89, 83, 'STU-2026-0015', 'shane', 'binoya', 'carlo', '', '123455', 'Male', 'dsdsd', 22, 'dsssd', 'filipino', '0', 0, 0, '0', 0, '', NULL, NULL, '0', '132456', 'shane carlo binoya', 'dsdds', 'shancashinstallment', '0939389389', '2002-11-22', 'sddsd', 'shane carlo binoya', '90903903833', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'installment', '1st Semester', 0, '', '', 0.00, '23107e', 5777.00, '2026-03-02', 'TXN-1772415980555-Q9HYV', 3, '2026-03-02 01:46:48', '', NULL, 'tor_89_1772415098.pdf', NULL, '2026-03-02', '2026-03-02 01:31:38', '90903903833'),
(90, 84, 'STU-2026-0016', 'shane', 'binoya', 'carlo', '', '123456', 'Male', 'sddss', 22, 'sdsdad', 'filipino', '0', 0, 0, '0', 0, '', NULL, NULL, '0', 'sddssd', 'sjsgauha', 'josihusis', 'shanecashinstallment1', '09399282625', '2002-11-22', 'sghjsgjkshj', 'sjsgauha', 'dsadsds', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'Cash', 'installment', '1st Semester', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 84, '2026-03-02 02:09:49', '', NULL, 'tor_90_1772417281.pdf', NULL, '2026-03-02', '2026-03-02 02:08:01', 'dsadsds'),
(91, 85, 'STU-2026-0017', 'shane', 'cashinstallment', 'carlo', '', 'ssss', 'Male', 'Catholic', 22, '123', 'ss', '0', 0, 0, '0', 0, '', NULL, NULL, '0', '123456', 'ss', 'ss', 'cashinstallment2', 'ss', '2002-11-11', 'ss', 'ss', 'ss', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'Cash', 'installment', '1st Semester', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 85, '2026-03-02 02:26:22', '', NULL, 'tor_91_1772418316.pdf', NULL, '2026-03-02', '2026-03-02 02:25:15', 'ss'),
(92, 86, 'STU-2026-0018', 'shane', 'binoya', 'carlo', '', '123456', 'Male', '11', 23, '11', '11', '0', 0, 0, '0', 0, '', NULL, NULL, '0', '123', 'shane carlo binoya', 'dsdsd', 'gcashinstallment1', 'sdsd', '2002-11-22', '11', 'shane carlo binoya', 'dsd', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'installment', '1st Semester', 0, '', '', 0.00, '1234577', 6800.00, '2026-03-02', 'TXN-1772420371059-K6AOL', 86, '2026-03-02 02:59:41', '', NULL, 'tor_92_1772420304.pdf', NULL, '2026-03-02', '2026-03-02 02:58:23', 'dsd'),
(93, 87, 'STU-2026-0019', 'sddddddddds', 'dssdsd', 'carlo', '', '123456', 'Male', 'sdsdsd', 19, 'sdsd', 'sdsd', '0', 0, 0, '0', 0, '0', '', '', 'Elementary: olongapo (2002-2002); Elementary: gapo (2001-2003); Elementary: manila (2008 - 2001)', 'sdsdad', 'shane carlo binoya', '1234', 'cash3', 'sdadssd', '2002-11-20', 'dsasdsa', 'shane carlo binoya', '093099nw82', 'BS Information Systems', '1st Year', 0.00, 'Pending', 'New', 'NotRequired', 'College', 'Pending', 'Pending', 'Cash', 'full', '1st Semester, AY 2026-2027', 0, '', '', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-02', '2026-03-02 03:48:46', '093099nw82'),
(94, 88, 'STU-2026-0020', 'shane', 'cashfull', 'carlo', '', '1234', 'Male', 'sdsds', 19, 'sdsdd', 'sdsd', '0', 0, 0, '0', 0, '0', '', '', 'Junior High School: ocnhs (2016-2021)', '123456', 'shane carlo binoya', 'new cabalan', 'newcashfull', '09300987316', '2022-11-22', 'sdsd', 'shane carlo binoya', '0938377637', 'BS Information Systems', '1st Year', 0.00, 'Pending', 'New', 'NotRequired', 'College', 'Pending', 'Pending', 'Cash', 'full', '1st Semester, AY 2026-2027', 0, '', '', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-02', '2026-03-02 03:51:10', '0938377637'),
(95, 89, 'STU-2026-0021', 'shane', 'binoya', 'carlo', '', '123', 'Male', 'sdsdads', 12, 'sddss', 'sdds', '0', 0, 0, '0', 0, '0', '', '', 'Elementary: dsdsd (123444)', 'adsd', 'shane carlo binoya', 'sdsd', 'newcashfull1', '093083983', '2222-11-22', 'sdsdad', 'shane carlo binoya', '0930927262', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '1st Semester, AY 2026-2027', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-02 04:48:39', '', NULL, NULL, NULL, '2026-03-02', '2026-03-02 03:58:29', '0930927262'),
(96, 90, 'STU-2026-0022', 'shane', 'binoya', 'carlo', '', 'sihu515268', 'Male', 'Catholic', 19, 'Olongapo', 'Filipino', '0', 0, 0, '0', 0, '0', '', '', 'Elementary - OCES (2006-2012)', '113', 'shane carlo binoya', 'olongapo city', 'gcashfull', '1181301', '2002-11-22', 'Olongapo', 'shane carlo binoya', '099u81762', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester', 0, '', '', 0.00, '1234567', 28774.00, '2026-03-02', 'TXN-1772429615020-YBJBH', 3, '2026-03-02 05:34:10', '', NULL, NULL, NULL, '2026-03-02', '2026-03-02 05:32:50', '099u81762'),
(97, 91, 'STU-2026-0023', 'shane', 'gcashfull', 'carlo', '', '12345', 'Male', 'DSDSS', 19, 'SD', 'DAD', '0', 0, 0, '0', 0, '0', '', '', 'Elementary - OCES (2022-2028)', '', 'shane carlo binoya', 'OLONGAPO', 'cashfull11', '09300963718', '2002-11-15', 'DSDS', 'shane carlo binoya', '12434567', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '1st Semester', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-02 05:38:45', '', NULL, NULL, NULL, '2026-03-02', '2026-03-02 05:37:23', '12434567'),
(98, 92, 'STU-2026-0024', 'shane', 'cashfull2', 'carlo', '', '1234567', 'Male', 'sdsdd', 19, 'sddsd', 'sdsd', '0', 0, 0, '0', 0, '0', '', '', 'Elementary - olongapo (2008-2002)', '123456', 'shane carlo binoya', 'olongapo', 'cashfull111', '093083773', '2002-11-22', 'olongapo', 'shane carlo binoya', '09300987316', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'New', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '1st Semester', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-02 05:43:51', '', NULL, NULL, NULL, '2026-03-02', '2026-03-02 05:43:25', '09300987316');

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
(7, 61, 'Evaluated', 12, 13, '[{\"courseId\":1,\"code\":\"CS111\",\"name\":\"Introduction to Programming\",\"credits\":3,\"creditedFrom\":\"sdsd\"},{\"courseId\":2,\"code\":\"CS112\",\"name\":\"Web Development Basics\",\"credits\":3,\"creditedFrom\":\"sdsd\"},{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"sdsd\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"sdsd\"}]', '[1,2,5,24]', '', 2, '2026-03-01 09:42:47', '2026-03-01 09:40:19', '2026-03-01 09:42:47'),
(8, 62, 'Evaluated', 11, 11, '[{\"courseId\":26,\"code\":\"GE103\",\"name\":\"Readings in Philippine History\",\"credits\":3,\"creditedFrom\":\"GORDON\"},{\"courseId\":22,\"code\":\"MATH111\",\"name\":\"College Algebra\",\"credits\":3,\"creditedFrom\":\"GORDON\"},{\"courseId\":23,\"code\":\"MATH112\",\"name\":\"Discrete Mathematics\",\"credits\":3,\"creditedFrom\":\"GORDON\"},{\"courseId\":27,\"code\":\"PE101\",\"name\":\"Physical Fitness and Wellness\",\"credits\":2,\"creditedFrom\":\"GORDON\"}]', '[26,22,23,27]', '', 4, '2026-03-01 10:01:45', '2026-03-01 10:00:59', '2026-03-01 10:01:45'),
(9, 63, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"sdsd\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"sdsd\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"sdsd\"}]', '[5,24,3]', '', 4, '2026-03-01 10:33:19', '2026-03-01 10:32:00', '2026-03-01 10:33:19'),
(10, 64, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"Gordon College\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"Gordon College\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"Gordon College\"}]', '[5,24,3]', '', 4, '2026-03-01 11:04:36', '2026-03-01 11:03:07', '2026-03-01 11:04:36'),
(11, 65, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"Gordon college\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"Gordon college\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"Gordon college\"}]', '[5,24,3]', 'needed your original copy', 4, '2026-03-01 11:50:33', '2026-03-01 11:49:36', '2026-03-01 11:50:33'),
(12, 66, 'Evaluated', 21, 1, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"gordon college\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"gordon college\"},{\"courseId\":28,\"code\":\"NSTP101\",\"name\":\"National Service Training Program 1\",\"credits\":3,\"creditedFrom\":\"gordon college\"},{\"courseId\":25,\"code\":\"GE102\",\"name\":\"Understanding the Self\",\"credits\":3,\"creditedFrom\":\"gordon college\"},{\"courseId\":26,\"code\":\"GE103\",\"name\":\"Readings in Philippine History\",\"credits\":3,\"creditedFrom\":\"gordon college\"},{\"courseId\":22,\"code\":\"MATH111\",\"name\":\"College Algebra\",\"credits\":3,\"creditedFrom\":\"gordon college\"},{\"courseId\":27,\"code\":\"PE101\",\"name\":\"Physical Fitness and Wellness\",\"credits\":2,\"creditedFrom\":\"gordon college\"}]', '[5,3,28,25,26,22,27]', '', 4, '2026-03-01 12:15:31', '2026-03-01 12:14:33', '2026-03-01 12:15:31'),
(13, 67, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"gordon\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"gordon\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"gordon\"}]', '[5,24,3]', '', 58, '2026-03-01 12:30:39', '2026-03-01 12:30:13', '2026-03-01 12:30:39'),
(14, 68, 'Evaluated', 13, 12, '[{\"courseId\":19,\"code\":\"IT102\",\"name\":\"Computer Programming 1\",\"credits\":3,\"creditedFrom\":\"gordon\"},{\"courseId\":20,\"code\":\"IT103\",\"name\":\"Computer Hardware Fundamentals\",\"credits\":3,\"creditedFrom\":\"gordon\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"gordon\"},{\"courseId\":21,\"code\":\"IT104\",\"name\":\"Web Development 1\",\"credits\":3,\"creditedFrom\":\"gordon\"}]', '[19,20,3,21]', '', 0, '2026-03-01 12:36:34', '2026-03-01 12:36:08', '2026-03-01 12:36:34'),
(15, 69, 'Evaluated', 16, 9, '[{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"sdsdd\"},{\"courseId\":19,\"code\":\"IT102\",\"name\":\"Computer Programming 1\",\"credits\":3,\"creditedFrom\":\"sdsdd\"},{\"courseId\":20,\"code\":\"IT103\",\"name\":\"Computer Hardware Fundamentals\",\"credits\":3,\"creditedFrom\":\"sdsdd\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"sdsdd\"},{\"courseId\":21,\"code\":\"IT104\",\"name\":\"Web Development 1\",\"credits\":3,\"creditedFrom\":\"sdsdd\"}]', '[24,19,20,3,21]', '', 3, '2026-03-01 12:44:22', '2026-03-01 12:43:42', '2026-03-01 12:44:22'),
(16, 71, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"gordon\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"gordon\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"gordon\"}]', '[5,24,3]', '', 4, '2026-03-01 13:26:53', '2026-03-01 13:26:13', '2026-03-01 13:26:53'),
(17, 72, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"sd\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"sd\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"sd\"}]', '[5,24,3]', '', 4, '2026-03-01 13:38:19', '2026-03-01 13:37:33', '2026-03-01 13:38:19'),
(18, 73, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"dsdsdds\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"dsdsdds\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"dsdsdds\"}]', '[5,24,3]', '', 4, '2026-03-01 13:48:39', '2026-03-01 13:47:58', '2026-03-01 13:48:39'),
(19, 74, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"sdds\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"sdds\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"sdds\"}]', '[5,24,3]', '', 0, '2026-03-01 13:50:28', '2026-03-01 13:50:07', '2026-03-01 13:50:28'),
(20, 84, 'Evaluated', 13, 12, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"gordoncollege\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"gordoncollege\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"gordoncollege\"},{\"courseId\":21,\"code\":\"IT104\",\"name\":\"Web Development 1\",\"credits\":3,\"creditedFrom\":\"gordoncollege\"}]', '[5,24,3,21]', '', 4, '2026-03-01 19:08:25', '2026-03-01 19:07:49', '2026-03-01 19:27:03'),
(22, 89, 'Evaluated', 12, 13, '[{\"courseId\":1,\"code\":\"CS111\",\"name\":\"Introduction to Programming\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":2,\"code\":\"CS112\",\"name\":\"Web Development Basics\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"0\"}]', '[1,2,5,24]', '', 4, '2026-03-02 01:33:27', '2026-03-02 01:31:38', '2026-03-02 01:33:27'),
(24, 90, 'Evaluated', 10, 15, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"0\"}]', '[5,24,3]', '', 4, '2026-03-02 02:08:45', '2026-03-02 02:08:01', '2026-03-02 02:08:45'),
(26, 91, 'Evaluated', 10, 12, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"0\"}]', '[5,24,3]', '', 4, '2026-03-02 02:25:32', '2026-03-02 02:25:15', '2026-03-02 02:25:32'),
(28, 92, 'Evaluated', 10, 12, '[{\"courseId\":5,\"code\":\"ENG101\",\"name\":\"English Composition\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"0\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"0\"}]', '[5,24,3]', '', 0, '2026-03-02 02:58:38', '2026-03-02 02:58:23', '2026-03-02 02:58:38');

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
(1, 55, 16, 10400.00, 6688.00, 700.00, 30400.00, 1008.00, 49196.00, 0.00, 0.00, 49196.00, '2026-02-01 02:37:26', '2026-02-01 02:37:26'),
(2, 56, 13, 8450.00, 6688.00, 700.00, 24700.00, 819.00, 41357.00, 2323323.00, 0.00, 0.00, '2026-02-01 03:09:00', '2026-02-01 05:12:13'),
(4, 57, 7, 4550.00, 6688.00, 700.00, 13300.00, 441.00, 25679.00, 2000.00, 0.00, 23679.00, '2026-02-01 03:48:02', '2026-02-01 05:15:37'),
(6, 58, 16, 10400.00, 6688.00, 700.00, 30400.00, 1008.00, 49196.00, 0.00, 0.00, 49196.00, '2026-02-01 04:24:58', '2026-02-01 04:24:58'),
(8, 59, 18, 11700.00, 6688.00, 700.00, 34200.00, 1134.00, 54422.00, 0.00, 0.00, 54422.00, '2026-02-01 05:13:28', '2026-02-01 05:13:28'),
(10, 60, 18, 11700.00, 6688.00, 700.00, 34200.00, 1134.00, 54422.00, 0.00, 0.00, 54422.00, '2026-02-01 06:16:41', '2026-02-01 06:16:41'),
(12, 61, 13, 8450.00, 6688.00, 700.00, 24700.00, 819.00, 41357.00, 0.00, 0.00, 41357.00, '2026-03-01 09:42:47', '2026-03-01 09:42:47'),
(15, 62, 11, 7150.00, 6688.00, 700.00, 3800.00, 693.00, 19031.00, 0.00, 0.00, 19031.00, '2026-03-01 10:01:45', '2026-03-01 10:01:50'),
(18, 63, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 2000.00, 0.00, 23683.00, '2026-03-01 10:33:19', '2026-03-01 10:33:21'),
(21, 64, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 0.00, 0.00, 25683.00, '2026-03-01 11:04:36', '2026-03-01 11:04:38'),
(24, 65, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 0.00, 0.00, 25683.00, '2026-03-01 11:50:33', '2026-03-01 11:50:37'),
(27, 53, 22, 14300.00, 6688.00, 700.00, 1900.00, 1386.00, 24974.00, 0.00, 0.00, 24974.00, '2026-03-01 11:53:58', '2026-03-02 02:06:01'),
(28, 66, 1, 650.00, 6688.00, 700.00, 3800.00, 63.00, 11901.00, 0.00, 0.00, 11901.00, '2026-03-01 12:15:31', '2026-03-01 12:15:33'),
(31, 67, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 2000.00, 0.00, 23683.00, '2026-03-01 12:30:39', '2026-03-01 12:30:43'),
(34, 68, 12, 7800.00, 6688.00, 700.00, 7600.00, 756.00, 23544.00, 0.00, 0.00, 23544.00, '2026-03-01 12:36:34', '2026-03-01 12:36:38'),
(37, 69, 9, 5850.00, 6688.00, 700.00, 7600.00, 567.00, 21405.00, 0.00, 0.00, 21405.00, '2026-03-01 12:44:22', '2026-03-01 12:44:22'),
(40, 70, 25, 16250.00, 6688.00, 700.00, 7600.00, 1575.00, 32813.00, 0.00, 750.00, 33563.00, '2026-03-01 13:13:59', '2026-03-01 13:13:59'),
(41, 71, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 0.00, 0.00, 25683.00, '2026-03-01 13:26:54', '2026-03-01 13:26:54'),
(44, 72, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 0.00, 0.00, 25683.00, '2026-03-01 13:38:19', '2026-03-01 13:38:19'),
(47, 73, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 0.00, 0.00, 25683.00, '2026-03-01 13:48:39', '2026-03-01 13:48:39'),
(50, 74, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 0.00, 750.00, 26433.00, '2026-03-01 13:50:28', '2026-03-01 13:50:46'),
(53, 75, 25, 16250.00, 6688.00, 700.00, 7600.00, 1575.00, 32813.00, 0.00, 750.00, 33563.00, '2026-03-01 13:55:32', '2026-03-01 13:55:32'),
(55, 76, 25, 16250.00, 6688.00, 700.00, 7600.00, 1575.00, 32813.00, 0.00, 0.00, 32813.00, '2026-03-01 14:04:21', '2026-03-01 14:04:21'),
(56, 77, 25, 16250.00, 6688.00, 700.00, 7600.00, 1575.00, 32813.00, 0.00, 0.00, 32813.00, '2026-03-01 14:23:25', '2026-03-01 14:23:25'),
(57, 78, 25, 16250.00, 6688.00, 700.00, 7600.00, 1575.00, 32813.00, 0.00, 0.00, 32813.00, '2026-03-01 15:01:19', '2026-03-01 15:01:19'),
(58, 79, 25, 16250.00, 6688.00, 700.00, 7600.00, 1575.00, 32813.00, 0.00, 0.00, 32813.00, '2026-03-01 15:17:43', '2026-03-01 15:17:43'),
(59, 80, 22, 14300.00, 6688.00, 700.00, 7600.00, 1386.00, 30674.00, 0.00, 0.00, 30674.00, '2026-03-01 15:33:48', '2026-03-01 15:35:22'),
(61, 81, 22, 14300.00, 6688.00, 700.00, 7600.00, 1386.00, 30674.00, 0.00, 0.00, 30674.00, '2026-03-01 15:50:07', '2026-03-01 15:50:07'),
(62, 82, 25, 16250.00, 6688.00, 700.00, 7600.00, 1575.00, 32813.00, 0.00, 750.00, 33563.00, '2026-03-01 18:48:07', '2026-03-01 18:48:07'),
(65, 83, 25, 16250.00, 6688.00, 700.00, 7600.00, 1575.00, 32813.00, 0.00, 750.00, 33563.00, '2026-03-01 18:54:06', '2026-03-01 18:54:06'),
(67, 84, 12, 7800.00, 6688.00, 700.00, 5700.00, 756.00, 21644.00, 0.00, 750.00, 22394.00, '2026-03-01 19:08:25', '2026-03-01 19:29:27'),
(83, 85, 22, 14300.00, 6688.00, 700.00, 5700.00, 1386.00, 28774.00, 0.00, 0.00, 28774.00, '2026-03-01 19:31:49', '2026-03-01 19:32:24'),
(86, 86, 22, 14300.00, 6688.00, 700.00, 5700.00, 1386.00, 28774.00, 0.00, 0.00, 28774.00, '2026-03-01 19:33:43', '2026-03-01 19:34:13'),
(91, 87, 22, 14300.00, 6688.00, 700.00, 5700.00, 1386.00, 28774.00, 0.00, 750.00, 29524.00, '2026-03-01 19:35:53', '2026-03-01 19:36:42'),
(94, 88, 22, 14300.00, 6688.00, 700.00, 5700.00, 1386.00, 28774.00, 0.00, 750.00, 29524.00, '2026-03-01 19:38:54', '2026-03-02 07:07:45'),
(99, 89, 13, 8450.00, 6688.00, 700.00, 5700.00, 819.00, 22357.00, 0.00, 750.00, 23107.00, '2026-03-02 01:33:27', '2026-03-02 01:46:51'),
(115, 90, 15, 9750.00, 6688.00, 700.00, 5700.00, 945.00, 23783.00, 0.00, 750.00, 24533.00, '2026-03-02 02:08:45', '2026-03-02 02:23:18'),
(129, 91, 12, 7800.00, 6688.00, 700.00, 5700.00, 756.00, 21644.00, 0.00, 750.00, 22394.00, '2026-03-02 02:25:32', '2026-03-02 08:26:57'),
(137, 92, 12, 7800.00, 6688.00, 700.00, 5700.00, 756.00, 21644.00, 0.00, 750.00, 22394.00, '2026-03-02 02:58:38', '2026-03-02 05:00:50'),
(189, 95, 22, 14300.00, 6688.00, 700.00, 5700.00, 1386.00, 28774.00, 0.00, 0.00, 28774.00, '2026-03-02 03:58:30', '2026-03-02 05:00:52'),
(236, 96, 22, 14300.00, 6688.00, 700.00, 5700.00, 1386.00, 28774.00, 0.00, 0.00, 28774.00, '2026-03-02 05:32:51', '2026-03-02 05:34:16'),
(239, 97, 22, 14300.00, 6688.00, 700.00, 5700.00, 1386.00, 28774.00, 0.00, 0.00, 28774.00, '2026-03-02 05:37:23', '2026-03-02 05:40:58'),
(246, 98, 22, 14300.00, 6688.00, 700.00, 5700.00, 1386.00, 28774.00, 0.00, 0.00, 28774.00, '2026-03-02 05:43:25', '2026-03-02 07:53:10');

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
(2, 'admin@example.com', 'admin123', 'admin', 'Admin', 'User', '2026-01-29 07:51:13'),
(3, 'accounting@example.com', 'acc123', 'accounting', 'Accounting', 'Staff', '2026-01-29 07:51:13'),
(4, 'registrar@example.com', 'registrar123', 'registrar', 'Registrar', 'Admin', '2026-01-29 08:54:49'),
(68, 'fullpaymentcash', 'shane1', 'student', 'Shane', 'new cash payment', '2026-03-01 14:04:20'),
(69, 'fullpaymentgcash1', 'shane1', 'student', 'gcash', 'full payment', '2026-03-01 14:23:25'),
(70, 'fullgcash', 'shane1', 'student', 'shane', 'trnsferee cash', '2026-03-01 15:01:19'),
(71, 'fullgcash1', 'shane1', 'student', 'shane', 'binoya', '2026-03-01 15:17:43'),
(72, 'fullgcash11', 'shane1', 'student', 'shane', 'binoya', '2026-03-01 15:27:06'),
(73, 'fullcash2', 'shane1', 'student', 'shane', 'binoya', '2026-03-01 15:50:06'),
(74, 'gcashinstallment', 'shane1', 'student', 'Shane', 'Gongora', '2026-03-01 18:48:06'),
(75, 'cashinstallment', 'shane1', 'student', 'Shane', 'cashinstallment', '2026-03-01 18:54:05'),
(76, 'gcashtrans', 'shane1', 'student', 'installment', 'gcashtrans', '2026-03-01 19:07:49'),
(77, 'qweasd', 'shane1', 'student', 'Shane', 'Gongora', '2026-03-01 19:31:49'),
(78, 'qweasd1', 'shane1', 'student', 'Shane', 'Gongora', '2026-03-01 19:33:43'),
(79, 'qweasd2', 'shane1', 'student', 'Shane', 'Gongora', '2026-03-01 19:35:52'),
(80, 'qweasd3', 'shane1', 'student', 'Shane', 'Gongora', '2026-03-01 19:38:54'),
(81, 'shanecashinstallment', 'shane123', 'student', 'shane', 'binoya', '2026-03-01 23:22:44'),
(82, 'scashinstallment', 'shane1', 'student', 'shane', 'binoya', '2026-03-01 23:59:24'),
(83, 'shancashinstallment', 'shane1', 'student', 'shane', 'binoya', '2026-03-02 00:18:24'),
(84, 'shanecashinstallment1', 'shane1', 'student', 'shane', 'binoya', '2026-03-02 02:08:01'),
(85, 'cashinstallment2', 'shane1', 'student', 'shane', 'cashinstallment', '2026-03-02 02:25:15'),
(86, 'gcashinstallment1', 'shane1', 'student', 'shane', 'binoya', '2026-03-02 02:58:23'),
(87, 'cash3', 'shane1', 'student', 'sddddddddds', 'dssdsd', '2026-03-02 03:48:46'),
(88, 'newcashfull', 'shane1', 'student', 'shane', 'cashfull', '2026-03-02 03:51:10'),
(89, 'newcashfull1', 'shane1', 'student', 'shane', 'binoya', '2026-03-02 03:58:29'),
(90, 'gcashfull', 'shane1', 'student', 'shane', 'binoya', '2026-03-02 05:32:50'),
(91, 'cashfull11', 'shane1', 'student', 'shane', 'gcashfull', '2026-03-02 05:37:23'),
(92, 'cashfull111', 'shane1', 'student', 'shane', 'cashfull2', '2026-03-02 05:43:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=340;

--
-- AUTO_INCREMENT for table `exam_permits`
--
ALTER TABLE `exam_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `payment_notices`
--
ALTER TABLE `payment_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `program_courses`
--
ALTER TABLE `program_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `term_payments`
--
ALTER TABLE `term_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tor_evaluations`
--
ALTER TABLE `tor_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=276;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

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
-- Constraints for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD CONSTRAINT `payment_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

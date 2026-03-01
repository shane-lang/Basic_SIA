-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 01, 2026 at 01:48 PM
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
(1, 'CS111', 'Introduction to Programming', 3, 'Engr. Maria Santos', NULL, 'MWF 8:00 AM - 9:30 AM', 'Monday,Wednesday,Friday', '8:00 AM - 9:30 AM', 'Room 301 (Science Building)', 51, 13, '1st Semester, AY 2024-2025', 'Fundamentals of programming using Python', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(2, 'CS112', 'Web Development Basics', 3, 'Engr. Juan Reyes', NULL, 'TTh 10:00 AM - 11:30 AM', 'Tuesday,Thursday', '10:00 AM - 11:30 AM', 'Lab 202 (IT Building)', 35, 12, '1st Semester, AY 2024-2025', 'HTML, CSS, and JavaScript fundamentals', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(3, 'MATH101', 'Discrete Mathematics', 4, 'Engr. Anna Garcia', NULL, 'MWF 9:45 AM - 11:15 AM', 'Monday,Wednesday,Friday', '9:45 AM - 11:15 AM', 'Room 205 (Science Building)', 40, 8, '1st Semester, AY 2024-2025', 'Sets, logic, and mathematical proofs', 'Mathematics', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(5, 'ENG101', 'English Composition', 3, 'Prof. Sarah Kim', NULL, 'MWF 1:00 PM - 2:30 PM', 'Monday,Wednesday,Friday', '1:00 PM - 2:30 PM', 'Room 101 (Liberal Arts Building)', 45, 10, '1st Semester, AY 2024-2025', 'Academic writing and communication skills', 'English', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(18, 'IT101', 'Introduction to Computing', 3, 'Engr. Maria Santos', NULL, 'MWF 7:30 AM - 8:30 AM', 'Monday,Wednesday,Friday', '7:30 AM - 8:30 AM', 'Room 301 (IT Building)', 40, 9, '1st Semester, AY 2024-2025', 'Overview of computing concepts, history, and modern applications', 'Information Technology', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(19, 'IT102', 'Computer Programming 1', 3, 'Engr. Juan Reyes', NULL, 'TTh 7:30 AM - 9:00 AM', 'Tuesday,Thursday', '7:30 AM - 9:00 AM', 'Lab 101 (IT Building)', 35, 8, '1st Semester, AY 2024-2025', 'Fundamentals of programming using Python — logic, loops, functions', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(20, 'IT103', 'Computer Hardware Fundamentals', 3, 'Engr. Luis Rodriguez', NULL, 'MWF 9:00 AM - 10:00 AM', 'Monday,Wednesday,Friday', '9:00 AM - 10:00 AM', 'Lab 202 (IT Building)', 35, 8, '1st Semester, AY 2024-2025', 'Hardware components, assembly, troubleshooting, and maintenance', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(21, 'IT104', 'Web Development 1', 3, 'Engr. Anna Garcia', NULL, 'TTh 10:30 AM - 12:00 PM', 'Tuesday,Thursday', '10:30 AM - 12:00 PM', 'Lab 101 (IT Building)', 35, 8, '2nd Semester, AY 2024-2025', 'HTML5, CSS3, and responsive design fundamentals', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(22, 'MATH111', 'College Algebra', 3, 'Prof. Reyna Cruz', NULL, 'MWF 10:30 AM - 11:30 AM', 'Monday,Wednesday,Friday', '10:30 AM - 11:30 AM', 'Room 205 (Science Building)', 40, 5, '1st Semester, AY 2024-2025', 'Algebraic expressions, equations, functions, and graphing', 'Mathematics', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(23, 'MATH112', 'Discrete Mathematics', 3, 'Prof. Reyna Cruz', NULL, 'TTh 1:00 PM - 2:30 PM', 'Tuesday,Thursday', '1:00 PM - 2:30 PM', 'Room 205 (Science Building)', 40, 5, '1st Semester, AY 2024-2025', 'Sets, logic, relations, functions, and graph theory', 'Mathematics', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(24, 'GE101', 'Purposive Communication', 3, 'Prof. Sarah Kim', NULL, 'MWF 1:00 PM - 2:00 PM', 'Monday,Wednesday,Friday', '1:00 PM - 2:00 PM', 'Room 101 (Liberal Arts)', 45, 7, '1st Semester, AY 2024-2025', 'Academic and professional communication skills', 'English', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
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
(57, 54, 1, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 01:23:06'),
(58, 54, 2, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 01:23:06'),
(59, 54, 3, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 01:23:06'),
(61, 54, 5, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 01:23:06'),
(62, 54, 18, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 01:23:06'),
(63, 55, 1, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:39:55'),
(64, 55, 2, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:39:55'),
(65, 55, 3, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:39:55'),
(66, 55, 5, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:39:55'),
(67, 55, 18, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:39:55'),
(68, 53, 1, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(69, 53, 2, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(70, 53, 3, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(71, 53, 5, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(72, 53, 18, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 02:40:09'),
(73, 56, 1, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:10:18'),
(74, 56, 2, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:10:18'),
(75, 56, 3, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:10:19'),
(76, 56, 5, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:10:19'),
(77, 56, 18, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:10:19'),
(78, 56, 19, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:50'),
(79, 56, 20, '2026-02-01', 'Dropped', NULL, '1st Semester, AY 2024-2025', 'Credited via TOR evaluation — permanently excluded', '2026-02-01 03:38:51'),
(80, 56, 21, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:51'),
(81, 56, 22, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:51'),
(82, 56, 23, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:51'),
(83, 56, 24, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:51'),
(84, 56, 25, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:52'),
(85, 56, 26, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:52'),
(86, 56, 27, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:53'),
(87, 56, 28, '2026-02-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-02-01 03:38:53'),
(89, 57, 1, '2026-02-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-02-01 05:15:37'),
(90, 57, 2, '2026-02-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-02-01 05:15:37'),
(91, 57, 24, '2026-02-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-02-01 05:15:37'),
(92, 61, 1, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 09:42:47'),
(93, 61, 2, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 09:42:47'),
(94, 61, 5, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 09:42:47'),
(95, 61, 24, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 09:42:47'),
(96, 62, 26, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 10:01:45'),
(97, 62, 22, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 10:01:45'),
(98, 62, 23, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 10:01:45'),
(99, 62, 27, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 10:01:46'),
(100, 62, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 10:02:52'),
(101, 62, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 10:02:53'),
(102, 62, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 10:02:53'),
(103, 62, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 10:02:53'),
(104, 62, 18, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 10:02:53'),
(105, 62, 21, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 10:02:53'),
(106, 62, 28, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 10:02:53'),
(107, 62, 25, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 10:22:07'),
(108, 63, 5, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 10:33:19'),
(109, 63, 24, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 10:33:19'),
(110, 63, 3, '2026-03-01', 'Dropped', NULL, 'TOR Credit', 'Credited via TOR evaluation — permanently excluded', '2026-03-01 10:33:19'),
(114, 64, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:14:41'),
(115, 64, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:14:41'),
(116, 64, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:14:41'),
(117, 64, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:14:41'),
(118, 64, 21, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:14:41'),
(119, 64, 3, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:29:04'),
(120, 64, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:29:04'),
(121, 64, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:29:04'),
(125, 65, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:59:17'),
(126, 65, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:59:17'),
(127, 65, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:59:17'),
(128, 65, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:59:17'),
(129, 65, 21, '2026-03-01', 'Enrolled', NULL, '2nd Semester, AY 2024-2025', 'Auto-enrolled', '2026-03-01 11:59:18'),
(140, 67, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:33:04'),
(141, 67, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:33:04'),
(142, 67, 19, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:33:04'),
(143, 67, 20, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:33:05'),
(148, 68, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:37:44'),
(149, 68, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:37:44'),
(150, 68, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:37:44'),
(151, 68, 24, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:37:45'),
(157, 69, 1, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:45:01'),
(158, 69, 2, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:45:01'),
(159, 69, 5, '2026-03-01', 'Enrolled', NULL, '1st Semester, AY 2025-2026', 'Auto-enrolled', '2026-03-01 12:45:01');

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
(6, 69, 43, 'OR-20260006', 'OR', 6833.00, '2026-03-01', 'Cash', '', 'Full', '', 61, '2026-03-01 12:44:49');

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
(28, 54, 'Cash', '', 0.00, NULL, NULL, '1st Semester, AY 2026-2027', 'Verified', 46, '2026-02-01 01:22:52', '', 0, NULL, NULL, 0.00, '2026-02-01 01:22:37'),
(29, 55, 'GCash', 'sdsq', 49196.00, '2026-02-01', 'TXN-1769913540902-CTE0C', '', 'Verified', 3, '2026-02-01 02:39:28', '', 0, NULL, NULL, 0.00, '2026-02-01 02:39:00'),
(30, 56, 'Cash', '', 0.00, NULL, NULL, '1st Semester, AY 2026-2027', 'Verified', 48, '2026-02-01 03:10:07', '', 0, NULL, NULL, 0.00, '2026-02-01 03:09:00'),
(31, 57, 'Cash', '', 23679.00, '2026-02-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-02-01 05:14:00', '', 0, NULL, NULL, 0.00, '2026-02-01 03:48:02'),
(32, 58, 'Cash', '', 49196.00, '2026-02-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-02-01 05:13:58', '', 0, NULL, NULL, 0.00, '2026-02-01 04:24:58'),
(33, 59, 'Cash', '', 54422.00, '2026-02-01', NULL, '1st Semester, AY 2026-2027', 'Verified', 3, '2026-02-01 05:13:54', '', 0, NULL, NULL, 0.00, '2026-02-01 05:13:27'),
(34, 60, 'Cash', '', 54422.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 0, '2026-03-01 09:56:50', '', 0, NULL, NULL, 0.00, '2026-03-01 09:55:31'),
(35, 61, 'Cash', '', 41357.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 0, '2026-03-01 09:57:04', '', 0, NULL, NULL, 0.00, '2026-03-01 09:55:32'),
(36, 62, 'Cash', '', 19031.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 54, '2026-03-01 10:02:46', '', 0, NULL, NULL, 0.00, '2026-03-01 10:02:26'),
(37, 63, 'Cash', '', 23683.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 3, '2026-03-01 10:35:09', '', 0, NULL, NULL, 0.00, '2026-03-01 10:34:31'),
(38, 64, 'Cash', '', 25000.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 3, '2026-03-01 11:06:15', '', 0, NULL, NULL, 0.00, '2026-03-01 11:05:43'),
(39, 65, 'Cash', '', 25683.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 3, '2026-03-01 11:51:49', '', 0, NULL, NULL, 0.00, '2026-03-01 11:51:28'),
(40, 66, 'Cash', '', 11901.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 3, '2026-03-01 12:16:49', '', 0, NULL, NULL, 0.00, '2026-03-01 12:16:23'),
(41, 67, 'Cash', '', 23683.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 59, '2026-03-01 12:31:46', '', 0, NULL, NULL, 0.00, '2026-03-01 12:31:28'),
(42, 68, 'Cash', '', 23500.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 60, '2026-03-01 12:37:36', '', 0, NULL, NULL, 0.00, '2026-03-01 12:37:20'),
(43, 69, 'Cash', '', 6833.00, '2026-03-01', NULL, '1st Semester, AY 2024-2025', 'Verified', 61, '2026-03-01 12:44:49', '', 0, NULL, NULL, 0.00, '2026-03-01 12:44:36');

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
(11, 'Conference Room 1', 'Admin Building', 20, 'Conference Room', 'Available', '2026-02-01 00:11:11');

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
(54, 46, 'STU-2026-0002', 'Shane Carlo', 'Nodado', 'Binoya', '', 'adsd', 'Male', 'sdsd', 12, 'sdd', 'sds', 'sdsd', 0, 0, '0', 0, '0', '', '', 'sdd', '0', 'sd', 'sd', 'accountin1g@example.com', '09300987316', '0022-11-12', '118 Avocado Street Purok 3 New Cabalan', 'sd', 'sd', 'BSIT', '1st Year', 0.00, 'Enrolled', 'Transferee', 'NotRequired', 'College', 'Paid', 'Approved', 'Cash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 46, '2026-02-01 01:22:52', '', NULL, NULL, NULL, '2026-02-01', '2026-02-01 01:22:36', 'sd'),
(55, 47, 'STU-2026-0003', 'Shane Carlo', 'Nodado', 'Binoya', '', 'SSa', 'Male', 'sds', 23, 'sdsd', 'sdds', 'sdsd', 0, 0, '0', 0, '0', '', '', 'sd', '0', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'shane1', '09300987316', '2122-02-22', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'Old', 'NotRequired', 'College', 'Paid', 'Approved', 'GCash', 'full', '', 0, '', '', 0.00, 'sdsq', 49196.00, '2026-02-01', 'TXN-1769913540902-CTE0C', 3, '2026-02-01 02:39:28', '', NULL, NULL, NULL, '2026-02-01', '2026-02-01 02:37:25', '09300987316'),
(56, 48, 'STU-2026-0004', 'Shane Carlo', 'Nodado', 'Binoya', '', 'wwds', 'Male', 'sdsd', 19, 'sd', 'sd', 'sd', 0, 0, '0', 0, '0', '', '', 'gordon', '2322', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'shane2', '09300987316', '2002-11-22', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'New', 'Evaluated', 'College', 'Paid', 'Approved', 'Cash', 'full', '', 1, 'TESDA Scholarship', 'dsd', 2323323.00, NULL, NULL, NULL, NULL, 48, '2026-02-01 03:10:07', '', NULL, NULL, NULL, '2026-02-01', '2026-02-01 03:08:59', '09300987316'),
(57, 49, 'STU-2026-0005', 'Shane Carlo', 'Nodado', 'Binoya', '', 'AsaS', 'Male', 'SDS', 23, 'SD', 'SD', 'SD', 0, 0, '0', 0, '0', '', '', 'SDS', '0', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'shane22', '09300987316', '2222-11-22', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'Cash', 'full', '', 1, 'TESDA Scholarship', 'SDDSD', 2000.00, NULL, NULL, NULL, NULL, 3, '2026-02-01 05:14:00', '', NULL, NULL, NULL, '2026-02-01', '2026-02-01 03:48:02', '09300987316'),
(58, 50, 'STU-2026-0006', 'shane', 'binoyas', 'carlo', '', 'sqaS', 'Male', 'SDSDSD', 21, 'SD', 'SD', 'DDSSD', 0, 0, '0', 0, '0', '', '', 'GORDON COLLEGE', '0', 'shane carlo binoya', 'SDSDA', 'shane5', '0930098731', '2002-11-22', 'SDSSD', 'shane carlo binoya', '09393993', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Pending', 'College', 'Paid', 'Approved', 'Cash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-02-01 05:13:58', '', NULL, NULL, NULL, '2026-02-01', '2026-02-01 04:24:58', '09393993'),
(59, 51, 'STU-2026-0007', 'Shane Carlo', 'Nodado', 'Binoya', '', 'asdds', 'Male', 'sdsd', 23, 'sdsd', 'dsd', 'sdd', 0, 0, '0', 0, '0', '', '', 'gordno', '0', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'shanecarlo', '09300987316', '2222-11-20', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Pending', 'College', 'Paid', 'Approved', 'Cash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-02-01 05:13:54', '', NULL, NULL, NULL, '2026-02-01', '2026-02-01 05:13:27', '09300987316'),
(60, 52, 'STU-2026-0008', 'Shane Carlo', 'Nodado', 'Binoya', '', '1342526', 'Male', 'sds', 23, 'sd', 'ds', 'dsds', 0, 0, '0', 0, '0', '', '', 'sdsdds', '0', 'Shane Carlo Binoya Nodado', '118 Avocado Street Purok 3 New Cabalan', 'shanecarlo1', '09300987316', '2002-11-22', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Pending', 'College', 'Paid', 'Approved', 'GCash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 0, '2026-03-01 09:56:50', '', NULL, NULL, NULL, '2026-02-01', '2026-02-01 06:16:41', '09300987316'),
(61, 53, 'STU-2026-0009', 'Dave', 'Cuevas', 'zarene', '', 'dadad', 'Male', 'dss', 12, 'dssd', 'sdsd', 'cssdsd', 0, 0, '0', 0, '0', '', '', 'sdsd', '0', 'sdds', 'sds', 'dave1', '093009398', '2002-11-22', 'sdd', 'sdds', '2332323323', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 0, '2026-03-01 09:57:04', '', NULL, 'tor_61_1772358019.pdf', NULL, '2026-03-01', '2026-03-01 09:40:19', '2332323323'),
(62, 54, 'STU-2026-0010', 'Dave', 'Cuevas', 'zarene', '', '32323234', 'Male', 'CXZXC', 19, 'CXCX', 'CXCDY', 'DWDDDSD', 0, 0, '0', 0, '0', '', '', 'GORDON', '12345897', 'Dave zarene Cuevas', 'SDSD', 'DAVE2', '0990827829', '2002-11-22', 'OLONGAPO', 'Dave zarene Cuevas', '0939832617', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 54, '2026-03-01 10:02:46', '', NULL, 'tor_62_1772359259.pdf', NULL, '2026-03-01', '2026-03-01 10:00:59', '0939832617'),
(63, 55, 'STU-2026-0011', 'Dave', 'Cuevas', 'zarene', '', 'sfsffsf', 'Male', 'tee5e', 23, 'fgffh', 'hfgh', 'kjhi', 0, 0, '0', 0, '0', '', '', 'sdsd', '0', 'Dave zarene Cuevasf', 'fsdfdf', 'DAVE3', 'bnnnbv', '2212-02-12', 'fsdfet', 'Dave zarene Cuevasf', 'dsf3434234', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '', 1, 'Sibling Discount', 'iyiyi', 2000.00, NULL, NULL, NULL, NULL, 3, '2026-03-01 10:35:09', '', NULL, 'tor_63_1772361120.pdf', NULL, '2026-03-01', '2026-03-01 10:32:00', 'dsf3434234'),
(64, 56, 'STU-2026-0012', 'Shane', 'Gongora', 'Carlo', '', '12345', 'Male', 'Catholic', 23, 'olongapo', 'filipino', 'ndhdi', 0, 0, '0', 0, '0', '', '', 'Gordon College', '123455', 'Lhar Gongora', '118 Avocado City', 'gongorashane22@gmail.com', '09300987316', '2002-11-22', '118 Avocado City', 'Lhar Gongora', '09184113130', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-01 11:06:15', '', NULL, 'tor_64_1772362987.pdf', NULL, '2026-03-01', '2026-03-01 11:03:07', '09184113130'),
(65, 57, 'STU-2026-0013', 'Shane', 'Nodado', 'Carlo', '', 'shanee', 'Male', 'sdsd', 22, 'sdsa', 'sdsda', 'sdds', 0, 0, '0', 0, '0', '', '', 'Gordon college', 'ssds', 'Shane Gongora', '118 Avocado City', 'gongorashane13@gmail.com', 'sdsd', '2002-11-02', 'sdasdsd', 'Shane Gongora', '2323232123', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-01 11:51:49', '', NULL, 'tor_65_1772365776.pdf', NULL, '2026-03-01', '2026-03-01 11:49:36', '2323232123'),
(66, 58, 'STU-2026-0014', 'Shane', 'Nodado', 'Carlo', '', '12345', 'Male', 'aSAAs', 23, 'dadaS', 'SasAS', '0', 0, 0, '0', 0, '0', '', '', 'gordon college', '123456', 'Shane Gongora', '118 Avocado City', 'gongorashane1@gmail.com', 'd333323', '2002-11-15', '118 Avocado City', 'Shane Gongora', '2332323', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester, AY 2025-2026', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 3, '2026-03-01 12:16:49', '', NULL, 'tor_66_1772367273.pdf', NULL, '2026-03-01', '2026-03-01 12:14:33', '2332323'),
(67, 59, 'STU-2026-0015', 'Shane', 'Gongora', 'santos', '', '1234567', 'Male', 'catholic', 23, 'olongapo', ';mkpsmkl', '0', 0, 0, '0', 0, '0', '', '', 'gordon', 'sdsdsd', 'Shane Gongora', '118 Avocado City', 'gongorashane11@gmail.com', '093009873133', '2002-11-20', '118 Avocado City', 'Shane Gongora', '233233', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester, AY 2025-2026', 1, 'Local Government Unit (LGU) Scholarship', 'olongapo', 2000.00, NULL, NULL, NULL, NULL, 59, '2026-03-01 12:31:46', '', NULL, 'tor_67_1772368213.pdf', NULL, '2026-03-01', '2026-03-01 12:30:13', '233233'),
(68, 60, 'STU-2026-0016', 'Shanes', 'Gongoras', 'binoya', '', '13243234', 'Male', 'dwdsd', 19, 'sdsd', 'sds', '0', 0, 0, '0', 0, '0', '', '', 'gordon', '1231231232', 'Shane Gongora', '118 Avocado City', 'gongorashane10@gmail.com', '293089239', '2002-11-22', '118 Avocado City', 'Shane Gongora', '222323', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester, AY 2025-2026', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 60, '2026-03-01 12:37:36', '', NULL, 'tor_68_1772368568.pdf', NULL, '2026-03-01', '2026-03-01 12:36:07', '222323'),
(69, 61, 'STU-2026-0017', 'Shane', 'Gongora', 'Carlo', '', '2323', 'Male', 'sds', 23, 'sdsd', 'sdsd', '0', 0, 0, '0', 0, '0', '', '', 'sdsdd', 'sdsd', 'Shane Gongora', '118 Avocado City', 'gongorashane19@gmail.com', '09309039332', '2002-11-02', '118 Avocado City', 'Shane Gongora', '093039838', 'BS Computer Science', '1st Year', 0.00, 'Enrolled', 'Transferee', 'Evaluated', 'College', 'Paid', 'Approved', 'GCash', 'full', '1st Semester, AY 2025-2026', 0, '', '', 0.00, NULL, NULL, NULL, NULL, 61, '2026-03-01 12:44:49', '', NULL, 'tor_69_1772369022.pdf', NULL, '2026-03-01', '2026-03-01 12:43:42', '093039838');

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
(15, 69, 'Evaluated', 16, 9, '[{\"courseId\":24,\"code\":\"GE101\",\"name\":\"Purposive Communication\",\"credits\":3,\"creditedFrom\":\"sdsdd\"},{\"courseId\":19,\"code\":\"IT102\",\"name\":\"Computer Programming 1\",\"credits\":3,\"creditedFrom\":\"sdsdd\"},{\"courseId\":20,\"code\":\"IT103\",\"name\":\"Computer Hardware Fundamentals\",\"credits\":3,\"creditedFrom\":\"sdsdd\"},{\"courseId\":3,\"code\":\"MATH101\",\"name\":\"Discrete Mathematics\",\"credits\":4,\"creditedFrom\":\"sdsdd\"},{\"courseId\":21,\"code\":\"IT104\",\"name\":\"Web Development 1\",\"credits\":3,\"creditedFrom\":\"sdsdd\"}]', '[24,19,20,3,21]', '', 3, '2026-03-01 12:44:22', '2026-03-01 12:43:42', '2026-03-01 12:44:22');

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
(27, 53, 22, 14300.00, 6688.00, 700.00, 41800.00, 1386.00, 64874.00, 0.00, 0.00, 64874.00, '2026-03-01 11:53:58', '2026-03-01 11:53:58'),
(28, 66, 1, 650.00, 6688.00, 700.00, 3800.00, 63.00, 11901.00, 0.00, 0.00, 11901.00, '2026-03-01 12:15:31', '2026-03-01 12:15:33'),
(31, 67, 15, 9750.00, 6688.00, 700.00, 7600.00, 945.00, 25683.00, 2000.00, 0.00, 23683.00, '2026-03-01 12:30:39', '2026-03-01 12:30:43'),
(34, 68, 12, 7800.00, 6688.00, 700.00, 7600.00, 756.00, 23544.00, 0.00, 0.00, 23544.00, '2026-03-01 12:36:34', '2026-03-01 12:36:38'),
(37, 69, 9, 5850.00, 6688.00, 700.00, 7600.00, 567.00, 21405.00, 0.00, 0.00, 21405.00, '2026-03-01 12:44:22', '2026-03-01 12:44:22');

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
(46, 'accountin1g@example.com', 'shane1', 'student', 'Shane Carlo', 'Nodado', '2026-02-01 01:22:36'),
(47, 'shane1', 'shane1', 'student', 'Shane Carlo', 'Nodado', '2026-02-01 02:37:25'),
(48, 'shane2', 'shane1', 'student', 'Shane Carlo', 'Nodado', '2026-02-01 03:08:59'),
(49, 'shane22', 'SHANE1', 'student', 'Shane Carlo', 'Nodado', '2026-02-01 03:48:02'),
(50, 'shane5', 'shane1', 'student', 'shane', 'binoyas', '2026-02-01 04:24:57'),
(51, 'shanecarlo', 'shane1', 'student', 'Shane Carlo', 'Nodado', '2026-02-01 05:13:27'),
(52, 'shanecarlo1', 'shane1', 'student', 'Shane Carlo', 'Nodado', '2026-02-01 06:16:41'),
(53, 'dave1', 'shane1', 'student', 'Dave', 'Cuevas', '2026-03-01 09:40:18'),
(54, 'DAVE2', 'shane1', 'student', 'Dave', 'Cuevas', '2026-03-01 10:00:58'),
(55, 'DAVE3', 'shane1', 'student', 'Dave', 'Cuevas', '2026-03-01 10:32:00'),
(56, 'gongorashane22@gmail.com', 'shane1', 'student', 'Shane', 'Gongora', '2026-03-01 11:03:07'),
(57, 'gongorashane13@gmail.com', 'shane1', 'student', 'Shane', 'Nodado', '2026-03-01 11:45:11'),
(58, 'gongorashane1@gmail.com', 'shane1', 'student', 'Shane', 'Nodado', '2026-03-01 12:14:32'),
(59, 'gongorashane11@gmail.com', 'shane1', 'student', 'Shane', 'Gongora', '2026-03-01 12:30:12'),
(60, 'gongorashane10@gmail.com', 'shane1', 'student', 'Shanes', 'Gongoras', '2026-03-01 12:36:07'),
(61, 'gongorashane19@gmail.com', 'shane1', 'student', 'Shane', 'Gongora', '2026-03-01 12:43:41');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `school_events`
--
ALTER TABLE `school_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `term_payments`
--
ALTER TABLE `term_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tor_evaluations`
--
ALTER TABLE `tor_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

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

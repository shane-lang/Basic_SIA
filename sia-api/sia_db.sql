-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 11:29 AM
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
(1, 'CS111', 'Introduction to Programming', 3, 'Engr. Maria Santos', NULL, 'MWF 8:00 AM - 9:30 AM', 'Monday,Wednesday,Friday', '8:00 AM - 9:30 AM', 'Room 301 (Science Building)', 51, 39, '1st Semester, AY 2024-2025', 'Fundamentals of programming using Python', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(2, 'CS112', 'Web Development Basics', 3, 'Engr. Juan Reyes', NULL, 'TTh 10:00 AM - 11:30 AM', 'Tuesday,Thursday', '10:00 AM - 11:30 AM', 'Lab 202 (IT Building)', 35, 37, '1st Semester, AY 2024-2025', 'HTML, CSS, and JavaScript fundamentals', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(3, 'MATH101', 'Discrete Mathematics', 4, 'Engr. Anna Garcia', NULL, 'MWF 9:45 AM - 11:15 AM', 'Monday,Wednesday,Friday', '9:45 AM - 11:15 AM', 'Room 205 (Science Building)', 40, 26, '1st Semester, AY 2024-2025', 'Sets, logic, and mathematical proofs', 'Mathematics', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(5, 'ENG101', 'English Composition', 3, 'Prof. Sarah Kim', NULL, 'MWF 1:00 PM - 2:30 PM', 'Monday,Wednesday,Friday', '1:00 PM - 2:30 PM', 'Room 101 (Liberal Arts Building)', 45, 28, '1st Semester, AY 2024-2025', 'Academic writing and communication skills', 'English', 'BS Computer Science', '1st Year', '2026-01-31 08:04:35'),
(18, 'IT101', 'Introduction to Computing', 3, 'Engr. Maria Santos', NULL, 'MWF 7:30 AM - 8:30 AM', 'Monday,Wednesday,Friday', '7:30 AM - 8:30 AM', 'Room 301 (IT Building)', 40, 9, '1st Semester, AY 2024-2025', 'Overview of computing concepts, history, and modern applications', 'Information Technology', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(19, 'IT102', 'Computer Programming 1', 3, 'Engr. Juan Reyes', NULL, 'TTh 7:30 AM - 9:00 AM', 'Tuesday,Thursday', '7:30 AM - 9:00 AM', 'Lab 101 (IT Building)', 35, 34, '1st Semester, AY 2024-2025', 'Fundamentals of programming using Python — logic, loops, functions', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(20, 'IT103', 'Computer Hardware Fundamentals', 3, 'Engr. Luis Rodriguez', NULL, 'MWF 9:00 AM - 10:00 AM', 'Monday,Wednesday,Friday', '9:00 AM - 10:00 AM', 'Lab 202 (IT Building)', 35, 34, '1st Semester, AY 2024-2025', 'Hardware components, assembly, troubleshooting, and maintenance', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(21, 'IT104', 'Web Development 1', 3, 'Engr. Anna Garcia', NULL, 'TTh 10:30 AM - 12:00 PM', 'Tuesday,Thursday', '10:30 AM - 12:00 PM', 'Lab 101 (IT Building)', 35, 19, '2nd Semester, AY 2024-2025', 'HTML5, CSS3, and responsive design fundamentals', 'Information Technology', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(22, 'MATH111', 'College Algebra', 3, 'Prof. Reyna Cruz', NULL, 'MWF 10:30 AM - 11:30 AM', 'Monday,Wednesday,Friday', '10:30 AM - 11:30 AM', 'Room 205 (Science Building)', 40, 5, '1st Semester, AY 2024-2025', 'Algebraic expressions, equations, functions, and graphing', 'Mathematics', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(23, 'MATH112', 'Discrete Mathematics', 3, 'Prof. Reyna Cruz', NULL, 'TTh 1:00 PM - 2:30 PM', 'Tuesday,Thursday', '1:00 PM - 2:30 PM', 'Room 205 (Science Building)', 40, 5, '1st Semester, AY 2024-2025', 'Sets, logic, relations, functions, and graph theory', 'Mathematics', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(24, 'GE101', 'Purposive Communication', 3, 'Prof. Sarah Kim', NULL, 'MWF 1:00 PM - 2:00 PM', 'Monday,Wednesday,Friday', '1:00 PM - 2:00 PM', 'Room 101 (Liberal Arts)', 45, 25, '1st Semester, AY 2024-2025', 'Academic and professional communication skills', 'English', 'BS Computer Science', '1st Year', '2026-01-31 13:46:28'),
(25, 'GE102', 'Understanding the Self', 3, 'Prof. James Lim', NULL, 'TTh 3:00 PM - 4:30 PM', 'Tuesday,Thursday', '3:00 PM - 4:30 PM', 'Room 102 (Liberal Arts)', 45, 6, '1st Semester, AY 2024-2025', 'Self-development, identity, and human values', 'General Education', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(26, 'GE103', 'Readings in Philippine History', 3, 'Prof. Maria Reyes', NULL, 'MWF 2:30 PM - 3:30 PM', 'Monday,Wednesday,Friday', '2:30 PM - 3:30 PM', 'Room 103 (Liberal Arts)', 45, 5, '1st Semester, AY 2024-2025', 'Critical reading of primary sources in Philippine history', 'General Education', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(27, 'PE101', 'Physical Fitness and Wellness', 2, 'Coach Robert Lee', NULL, 'Saturday 8:00 AM - 10:00 AM', 'Saturday', '8:00 AM - 10:00 AM', 'Sports Complex', 60, 5, '1st Semester, AY 2024-2025', 'Physical fitness, health, and wellness activities', 'Physical Education', 'BS Information Technology', '1st Year', '2026-01-31 13:46:28'),
(28, 'NSTP101', 'National Service Training Program 1', 3, 'Capt. Jose Dela Rosa', NULL, 'Saturday 1:00 PM - 4:00 PM', 'Saturday', '1:00 PM - 4:00 PM', 'Auditorium', 80, 6, '2nd Semester, AY 2024-2025', 'Civic welfare, literacy training, and reserve officers training', 'General Education', 'BS Information Technology', '2nd Year', '2026-01-31 13:46:28');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(6, 'HUMSS Strand', 'HUMSS', 'SHS', 2, 'Humanities and Social Sciences', 'Academic Track', '2026-02-01 00:03:41');

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
(21, 2, 24);

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
(2, 'admin@example.com', 'admin123', 'admin', 'Admin', 'User', '2026-01-29 07:51:13'),
(3, 'accounting@example.com', 'acc123', 'accounting', 'Accounting', 'Staff', '2026-01-29 07:51:13'),
(4, 'registrar@example.com', 'registrar123', 'registrar', 'Registrar', 'Admin', '2026-01-29 08:54:49');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=361;

--
-- AUTO_INCREMENT for table `exam_permits`
--
ALTER TABLE `exam_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `payment_notices`
--
ALTER TABLE `payment_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_schedules`
--
ALTER TABLE `payment_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `term_payments`
--
ALTER TABLE `term_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tor_evaluations`
--
ALTER TABLE `tor_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `tuition_fees`
--
ALTER TABLE `tuition_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=302;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

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

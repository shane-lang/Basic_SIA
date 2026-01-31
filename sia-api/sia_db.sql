-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 31, 2026 at 12:52 PM
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
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `credits` int(11) NOT NULL DEFAULT 3,
  `instructor` varchar(100) DEFAULT NULL,
  `schedule` varchar(100) DEFAULT NULL,
  `day` varchar(50) DEFAULT NULL,
  `time` varchar(50) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 40,
  `enrolled_count` int(11) DEFAULT 0,
  `semester` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `credits`, `instructor`, `schedule`, `day`, `time`, `room`, `capacity`, `enrolled_count`, `semester`, `description`, `department`, `created_at`) VALUES
(1, 'CS111', 'Introduction to Programming', 3, 'Engr. Maria Santos', 'MWF 8:00 AM - 9:30 AM', 'Monday,Wednesday,Friday', '8:00 AM - 9:30 AM', 'Room 301 (Science Building)', 51, 1, '1st Semester, AY 2024-2025', 'Fundamentals of programming using Python', 'Information Technology', '2026-01-31 08:04:35'),
(2, 'CS112', 'Web Development Basics', 3, 'Engr. Juan Reyes', 'TTh 10:00 AM - 11:30 AM', 'Tuesday,Thursday', '10:00 AM - 11:30 AM', 'Lab 202 (IT Building)', 35, 0, '1st Semester, AY 2024-2025', 'HTML, CSS, and JavaScript fundamentals', 'Information Technology', '2026-01-31 08:04:35'),
(3, 'MATH101', 'Discrete Mathematics', 4, 'Engr. Anna Garcia', 'MWF 9:45 AM - 11:15 AM', 'Monday,Wednesday,Friday', '9:45 AM - 11:15 AM', 'Room 205 (Science Building)', 40, 0, '1st Semester, AY 2024-2025', 'Sets, logic, and mathematical proofs', 'Mathematics', '2026-01-31 08:04:35'),
(4, 'CS113', 'Database Fundamentals', 3, 'Engr. Luis Rodriguez', 'TTh 1:00 PM - 2:30 PM', 'Tuesday,Thursday', '1:00 PM - 2:30 PM', 'Room 401 (IT Building)', 40, 1, '1st Semester, AY 2024-2025', 'Relational databases and SQL', 'Information Technology', '2026-01-31 08:04:35'),
(5, 'ENG101', 'English Composition', 3, 'Prof. Sarah Kim', 'MWF 1:00 PM - 2:30 PM', 'Monday,Wednesday,Friday', '1:00 PM - 2:30 PM', 'Room 101 (Liberal Arts Building)', 45, 0, '1st Semester, AY 2024-2025', 'Academic writing and communication skills', 'English', '2026-01-31 08:04:35'),
(6, 'PE101', 'Physical Education 1', 2, 'Coach Robert Lee', 'MWF 3:00 PM - 4:00 PM', 'Monday,Wednesday,Friday', '3:00 PM - 4:00 PM', 'Sports Complex', 60, 0, '1st Semester, AY 2024-2025', 'Basic fitness and wellness', 'Physical Education', '2026-01-31 08:04:35');

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
(1, 20, 1, '2026-01-31', 'Enrolled', NULL, '1st Semester, AY 2024-2025', '', '2026-01-31 10:02:21'),
(2, 20, 4, '2026-01-31', 'Enrolled', NULL, '1st Semester, AY 2024-2025', '', '2026-01-31 10:12:01');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_logs`
--

INSERT INTO `payment_logs` (`id`, `student_id`, `payment_method`, `gcash_reference`, `gcash_amount`, `gcash_date`, `transaction_id`, `semester`, `status`, `verified_by`, `verified_at`, `notes`, `created_at`) VALUES
(7, 16, 'Cash', 'CASH-PAYMENT', 0.00, NULL, NULL, '1st Semester, AY 2024-2025', 'Verified', 3, '2026-01-31 11:31:30', '', '2026-01-31 11:23:35'),
(8, 17, 'Cash', 'CASH-PAYMENT', 0.00, NULL, NULL, '1st Semester, AY 2024-2025', 'Verified', 3, '2026-01-31 11:31:24', '', '2026-01-31 11:30:43'),
(9, 18, 'GCash', '1234567890', 25000.00, '2026-01-31', 'TXN-1769861254506-3LFOW', '1st Semester, AY 2024-2025', 'Verified', 16, '2026-01-31 12:07:51', '', '2026-01-31 12:07:34'),
(10, 19, 'Cash', '', 0.00, NULL, NULL, '1st Semester, AY 2024-2025', 'Verified', 17, '2026-01-31 12:11:02', 'goods', '2026-01-31 12:10:11'),
(11, 20, 'GCash', '1234452627252728', 25000.00, '2026-01-31', 'TXN-1769853647073-BFB04', '1st Semester, AY 2024-2025', 'Verified', 3, '2026-01-31 10:01:52', '', '2026-01-31 10:00:47');

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
  `student_type` enum('New','Continuing','Returning') DEFAULT 'New',
  `payment_status` enum('Pending','Paid','Overdue') DEFAULT 'Pending',
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `payment_method` varchar(20) NOT NULL DEFAULT 'GCash',
  `gcash_reference` varchar(100) DEFAULT NULL,
  `gcash_amount` decimal(10,2) DEFAULT NULL,
  `gcash_date` date DEFAULT NULL,
  `gcash_transaction_id` varchar(100) DEFAULT NULL,
  `accounting_approved_by` int(11) DEFAULT NULL,
  `accounting_approved_at` timestamp NULL DEFAULT NULL,
  `accounting_notes` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_number`, `first_name`, `last_name`, `email`, `phone`, `date_of_birth`, `address`, `emergency_contact`, `emergency_phone`, `program`, `year_level`, `gpa`, `enrollment_status`, `student_type`, `payment_status`, `approval_status`, `payment_method`, `gcash_reference`, `gcash_amount`, `gcash_date`, `gcash_transaction_id`, `accounting_approved_by`, `accounting_approved_at`, `accounting_notes`, `profile_picture`, `enrollment_date`, `created_at`) VALUES
(16, 14, 'STU-2026-0001', 'Dave', 'Cuevas', 'cuevasdavezarene@gmail.com', '09029829829', '2002-11-11', 'olongapo', 'shane', '092828292', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'New', 'Paid', 'Approved', 'GCash', NULL, NULL, NULL, NULL, 3, '2026-01-31 11:31:30', '', NULL, '2026-01-31', '2026-01-31 11:23:35'),
(17, 15, 'STU-2026-0002', 'Dave', 'Cuevas', 'cuevasdavezarene123@gmail.com', '0930390390', '2002-11-20', 'olo', 'shane', '039383930', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'New', 'Paid', 'Approved', 'GCash', NULL, NULL, NULL, NULL, 3, '2026-01-31 11:31:24', '', NULL, '2026-01-31', '2026-01-31 11:30:43'),
(18, 16, 'STU-2026-0003', 'Shane Carlo', 'Nodado', 'juantamad@eedu.com', '09300987316', NULL, '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'New', 'Paid', 'Approved', 'GCash', '1234567890', 25000.00, '2026-01-31', 'TXN-1769861254506-3LFOW', 16, '2026-01-31 12:07:51', '', NULL, '2026-01-31', '2026-01-31 12:07:16'),
(19, 17, 'STU-2026-0004', 'Shane Carlo', 'Nodado', 'jasonbarera', '09300987316', '2002-11-11', '118 Avocado Street Purok 3 New Cabalan', 'Shane Carlo Binoya Nodado', '09300987316', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'New', 'Paid', 'Approved', 'Cash', NULL, NULL, NULL, NULL, 17, '2026-01-31 12:11:02', 'goods', NULL, '2026-01-31', '2026-01-31 12:10:11'),
(20, 18, 'STU-2026-0005', 'shane', 'binoya', 'shanecarlobinoya@gmail.com', '09235272892', '2002-11-11', 'olongapo', 'rochelle', '0918927872', 'BS Information Technology', '1st Year', 0.00, 'Enrolled', 'New', 'Paid', 'Approved', 'GCash', '1234452627252728', 25000.00, '2026-01-31', 'TXN-1769853647073-BFB04', 3, '2026-01-31 10:01:52', '', NULL, '2026-01-31', '2026-01-31 09:59:59');

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
(5, 'juan@edu.com', 'shane123', 'student', 'Shane Carlo', 'Nodado', '2026-01-31 10:20:05'),
(6, 'juna@edu.com', 'shane1', 'student', 'Shane Carlo', 'Nodado', '2026-01-31 10:23:03'),
(7, 'cuevasdavezarene@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 10:50:11'),
(8, 'cuevasdavezaren1e@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 10:54:49'),
(9, 'cuevasdavez32arene@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 10:56:16'),
(10, 'cuevasdavezarene221@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 11:03:24'),
(11, 'cuevas1davezarene@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 11:15:57'),
(12, 'cuevasda1234vezarene@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 11:19:11'),
(13, 'cu234evasdavezarene@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 11:20:03'),
(14, 'cuevasdavezar1ene@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 11:23:35'),
(15, 'cuevasdavezarene123@gmail.com', 'shane1', 'student', 'Dave', 'Cuevas', '2026-01-31 11:30:43'),
(16, 'juantamad@eedu.com', 'shane1', 'student', 'Shane Carlo', 'Nodado', '2026-01-31 12:07:15'),
(17, 'jasonbarera', 'shane1', 'student', 'Shane Carlo', 'Nodado', '2026-01-31 12:10:11'),
(18, 'shanecarlobinoya@gmail.com', 'shane1', 'student', 'shane', 'binoya', '2026-01-31 09:59:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_course` (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

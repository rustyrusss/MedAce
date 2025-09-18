-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 18, 2025 at 11:51 AM
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
-- Database: `medacedb`
--

-- --------------------------------------------------------

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `answers`
--

INSERT INTO `answers` (`id`, `question_id`, `answer_text`, `is_correct`) VALUES
(1, 1, 'Hev Abi', 1),
(2, 1, 'Buzz Cut', 0),
(3, 1, 'Lapu-Lapu', 0),
(4, 1, 'Fishball Man', 0),
(5, 2, 'Diwata Pares', 0),
(6, 2, 'Jose P. Rizal', 1),
(7, 2, 'Vic Sotto', 0),
(8, 2, 'Renejay', 0);

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text NOT NULL,
  `professor_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `title`, `description`, `content`, `professor_id`, `created_at`) VALUES
(7, 'Introduction to Human Anatomy', 'Covers the basic structure of the human body including organs and systems.', '', 7, '2025-09-15 20:11:57'),
(8, 'Medical Microbiology', 'Focuses on microorganisms and their impact on human health and disease.', '', 7, '2025-09-15 20:11:57'),
(9, 'Pharmacology Basics', 'Introduction to common drugs, their effects, and safe usage.', '', 7, '2025-09-15 20:11:57');

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `order_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nursing_tips`
--

CREATE TABLE `nursing_tips` (
  `id` int(11) NOT NULL,
  `tip_text` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_answer` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `quiz_id`, `question_text`, `options`, `correct_answer`) VALUES
(1, 8, 'Sino ang pumatay kay Magellan?', NULL, NULL),
(2, 8, 'Sino ang Pambansang Bayani ng Pilipinas?', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `professor_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `lesson_id` int(11) NOT NULL,
  `content` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `title`, `description`, `created_by`, `created_at`, `status`, `professor_id`, `module_id`, `lesson_id`, `content`) VALUES
(6, 'TEST', 'TEST', NULL, '2025-09-15 12:12:57', 'active', 11, NULL, 9, '{\"instructions\":\"TEST LANG PO\"}'),
(8, 'TRY LANG PO ATE', 'TRYYYY', NULL, '2025-09-15 12:20:59', 'active', 11, NULL, 8, '{\"instructions\":\"TRYYYYY\"}');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Completed','Failed') DEFAULT 'Pending',
  `score` int(11) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_attempts`
--

INSERT INTO `quiz_attempts` (`id`, `quiz_id`, `student_id`, `status`, `score`, `attempted_at`) VALUES
(17, 8, 10, 'Completed', 2, '2025-09-17 14:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_answers`
--

INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `answer_id`, `created_at`, `answered_at`) VALUES
(10, 17, 1, 1, '2025-09-17 14:25:08', '2025-09-17 14:25:08'),
(11, 17, 2, 6, '2025-09-17 14:25:08', '2025-09-17 14:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `student_progress`
--

CREATE TABLE `student_progress` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `step_type` enum('lesson','quiz') NOT NULL,
  `step_id` int(11) NOT NULL,
  `status` enum('Pending','Current','Completed','Failed') DEFAULT 'Pending',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(255) NOT NULL,
  `firstName` varchar(50) NOT NULL,
  `lastName` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','professor','dean') NOT NULL DEFAULT 'student',
  `section` varchar(50) DEFAULT NULL,
  `year` varchar(50) DEFAULT NULL,
  `studentID` varchar(50) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstName`, `lastName`, `username`, `email`, `password`, `role`, `section`, `year`, `studentID`, `status`, `created_at`) VALUES
(1, 'russel', 'santos', '', 'Russ@gmail.com', '1', 'dean', 'na', 'na', 'na', 'approved', '2025-09-12 18:41:36'),
(2, '', '', '', '', '', '', '', '', '', 'approved', '2025-09-12 18:41:36'),
(3, 'Russel', 'Santos', 'RusSantos', 'rus@gmail.com', '$2y$10$72B3kcB5fjfofceG1cBWzORZBFx5K4tfpgjpzX0h9Hz', '', '', '', '', 'approved', '2025-09-12 18:41:36'),
(4, 'Russel', 'Santos', 'RusSantos023', 'RusSantos023@gmail.com', '$2y$10$14Q.AZypV./eSpJojyXlQO92Sf9UT27K0W5D2455AFE', 'dean', '', '', '', 'approved', '2025-09-12 18:41:36'),
(5, 'Mariah', 'Dela Cruz', 'mariah123', 'mariahdelacruz2003@gmail.com', '$2y$10$.G4dHlhVKTZ9KHgDEhi5TOxZA8QUzi5JBSDoskKFdvO', '', '1A', '1', '', 'approved', '2025-09-12 18:41:36'),
(6, 'Allain', 'Arcayera', 'Allain11', 'Allain@gmail.com', '$2y$10$TQdxQkZti8xGzvMIGqg5Murhdty8AgcsflSXh7EeRNY', 'student', '2A', '2', '', 'approved', '2025-09-12 18:41:36'),
(7, 'Maverick ', 'Austria', 'Maverick', 'Mavs@gmail.com', '$2y$10$mVpbBWhjyMWmJMFgqDP5Z.Dyjlix0S9xreVofzuz73B', 'student', '1C', '1', '', 'approved', '2025-09-12 18:41:36'),
(8, 'Russel', 'San', 'RusSan', 'RusSan@gmail.com', '$2y$10$oUwBOK6zAwW5KLGo.ctbCOhnUI8rn009JrxhYoVg3KswrHqyK1RVS', 'student', '1B', '1', '', 'approved', '2025-09-12 18:41:36'),
(9, 'Dean', 'Ambrose', 'DeanAmbrose', 'DeanAmbrose@gmail.com', '$2y$10$9ESbmXBUKXAeql7LW5GP1.ERSMm170BrGdecFF9/CneIeiSQnnIXG', 'dean', '1A', '1', '', 'approved', '2025-09-12 18:41:36'),
(10, 'russel', 'santos', 'Russel', 'Russel@gmail.com', '$2y$10$MCnWjRopQARSJ2BvozN5S.C7iC3vOoBq6FiQLSNEWhrEV/jah8piC', 'student', '3B', '3', '', 'approved', '2025-09-12 18:41:36'),
(11, 'Prof', 'lang', 'prof', 'prof@gmail.com', '$2y$10$dLiecC3EB4VGZVjgsu4kKefsZL0iyBbJSk9XrDwLPkKx6CmyYHR8O', 'professor', '3D', '3', '', 'approved', '2025-09-12 18:41:36'),
(12, 'Proff', 'profy', 'profy', 'Profy@gmail.com', '$2y$10$5uX/JetPE3MlxU8tGlnKWOffISbVE0gl0.s.qQMdNVvPnTWwQrk6W', 'professor', NULL, NULL, '', 'approved', '2025-09-12 18:41:36'),
(13, '', '', 'Dean', '', '123456', 'dean', NULL, NULL, '', 'approved', '2025-09-12 18:41:36'),
(14, 'Dean', 'Dean', 'dean1', 'dean@gmail.com', '$2y$10$/WQUetNkYay0vy1fug8PculcJSCwoT6gEhEimNr6HAINWEVjUZ3wG', 'dean', '3D', '3', '', 'approved', '2025-09-12 18:41:36'),
(15, 'John', 'Cena', 'John', 'John@gmail.com', '$2y$10$Q.7GemI/uZG9/wh0gqGacOVSWBsxiUmeDlH3TAJK9VwpbmYjTrY.6', 'professor', NULL, NULL, '', 'approved', '2025-09-12 18:41:36'),
(16, 'Prof', 'Rey', 'Rey', 'ReyMysterio@gmail.com', '$2y$10$MriO2TabyDUTXUz7TieoN.FZFgU89Fdpln3vyLnNIzLZRZ9Z2ZVyW', 'professor', NULL, NULL, '', 'pending', '2025-09-12 18:46:53'),
(17, 'Mavs', 'Austria', 'mavs', 'Mavs1@gmail.com', '$2y$10$UIgwN7D5goIzOblv96t.4eEFHX9w8fOfxLJIlLgUsC1Xi7MloNTfK', 'student', '3A', '3', '', 'approved', '2025-09-17 13:41:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `nursing_tips`
--
ALTER TABLE `nursing_tips`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_quizzes_lesson` (`lesson_id`),
  ADD KEY `fk_quiz_module` (`module_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_quiz_attempts_student` (`student_id`),
  ADD KEY `fk_quiz_attempts_quiz` (`quiz_id`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attempt` (`attempt_id`),
  ADD KEY `fk_question` (`question_id`),
  ADD KEY `fk_answer` (`answer_id`);

--
-- Indexes for table `student_progress`
--
ALTER TABLE `student_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_student_progress_student` (`student_id`),
  ADD KEY `fk_student_progress_module` (`module_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nursing_tips`
--
ALTER TABLE `nursing_tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_progress`
--
ALTER TABLE `student_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quiz_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_quizzes_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `fk_quiz_attempts_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quiz_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `fk_answer` FOREIGN KEY (`answer_id`) REFERENCES `answers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_progress`
--
ALTER TABLE `student_progress`
  ADD CONSTRAINT `fk_student_progress_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_progress_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

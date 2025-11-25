-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 06:01 PM
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
(8, 2, 'Renejay', 0),
(63, 19, 'Sphygmomanometer', 1),
(64, 19, 'Thermometer', 0),
(65, 19, 'ECG Machine', 0),
(66, 19, 'Pulse Oximeter', 0),
(67, 20, 'Check the patient\'s ID band', 1),
(68, 20, 'Asking a family member', 0),
(69, 20, 'Verifying the nurse on duty', 0),
(70, 20, 'Observing the patient\'s behavior', 0),
(71, 21, 'Oral', 0),
(72, 21, 'Subcutaneous', 0),
(73, 21, 'Intravenous', 1),
(74, 21, 'Intramuscular', 0),
(75, 22, 'Physical', 0),
(76, 22, 'Emotional', 0),
(77, 22, 'Mental', 0),
(78, 22, 'All of the above', 1),
(79, 23, 'Supine', 0),
(80, 23, 'Fowler\'s', 1),
(81, 23, 'Prone', 0),
(82, 23, 'Lateral', 0);

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
  `professor_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `order_number` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `professor_id`, `title`, `description`, `content`, `order_number`, `created_at`, `updated_at`, `status`, `display_order`) VALUES
(1, 11, 'MedAce', 'Try', 'uploads/modules/1759665851_MedAce A Smart Web-Based Review and Progress Tracker for Allied Health Licensure Exams (1).pptx', NULL, '2025-10-05 20:04:11', '0000-00-00 00:00:00', 'published', 0),
(2, 11, 'Think Green', 'Think Green', 'uploads/modules/1760256144_Think Green · SlidesMania.pptx', NULL, '2025-10-12 16:02:24', '0000-00-00 00:00:00', 'published', 1),
(10, 11, 'nursing', 'nursing', '../uploads/modules/module_6925dcb7310289.43839036.pdf', NULL, '2025-11-26 00:43:35', '0000-00-00 00:00:00', 'published', 2);

-- --------------------------------------------------------

--
-- Table structure for table `module_progress`
--

CREATE TABLE `module_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `status` enum('started','completed') DEFAULT 'started',
  `started_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module_progress`
--

INSERT INTO `module_progress` (`id`, `user_id`, `module_id`, `status`, `started_at`, `completed_at`) VALUES
(1, 10, 2, 'started', '2025-11-11 22:17:03', NULL),
(21, 10, 1, 'started', '2025-10-14 19:38:08', NULL),
(82, 17, 1, 'started', '2025-11-15 15:19:31', NULL),
(83, 17, 2, 'started', '2025-11-15 15:19:36', NULL);

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
  `correct_answer` varchar(255) DEFAULT NULL,
  `time_limit` int(11) DEFAULT 0,
  `question_type` varchar(50) NOT NULL DEFAULT 'short_text',
  `points` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `quiz_id`, `question_text`, `options`, `correct_answer`, `time_limit`, `question_type`, `points`) VALUES
(1, 8, 'Sino ang pumatay kay Magellan?', NULL, NULL, 0, 'short_text', 1),
(2, 8, 'Sino ang Pambansang Bayani ng Pilipinas?', NULL, NULL, 0, 'short_text', 1),
(19, 12, 'Which method is commonly used to measure blood pressure?', '[{\"letter\":\"A\",\"text\":\"Sphygmomanometer\"},{\"letter\":\"B\",\"text\":\"Thermometer\"},{\"letter\":\"C\",\"text\":\"ECG Machine\"},{\"letter\":\"D\",\"text\":\"Pulse Oximeter\"}]', 'A', 0, 'multiple_choice', 1),
(20, 12, 'What is the best way to ensure the patient identification before medication administration?', '[{\"letter\":\"A\",\"text\":\"Check the patient\'s ID band\"},{\"letter\":\"B\",\"text\":\"Asking a family member\"},{\"letter\":\"C\",\"text\":\"Verifying the nurse on duty\"},{\"letter\":\"D\",\"text\":\"Observing the patient\'s behavior\"}]', 'A', 0, 'multiple_choice', 1),
(21, 12, 'Which route is used for the fastest drug absorption?', '[{\"letter\":\"A\",\"text\":\"Oral\"},{\"letter\":\"B\",\"text\":\"Subcutaneous\"},{\"letter\":\"C\",\"text\":\"Intravenous\"},{\"letter\":\"D\",\"text\":\"Intramuscular\"}]', 'C', 0, 'multiple_choice', 1),
(22, 12, 'What needs of a patient does a nurse address?', '[{\"letter\":\"A\",\"text\":\"Physical\"},{\"letter\":\"B\",\"text\":\"Emotional\"},{\"letter\":\"C\",\"text\":\"Mental\"},{\"letter\":\"D\",\"text\":\"All of the above\"}]', 'D', 0, 'multiple_choice', 1),
(23, 12, 'Which position is ideal for a patient in a respiratory distress?', '[{\"letter\":\"A\",\"text\":\"Supine\"},{\"letter\":\"B\",\"text\":\"Fowler\'s\"},{\"letter\":\"C\",\"text\":\"Prone\"},{\"letter\":\"D\",\"text\":\"Lateral\"}]', 'B', 0, 'multiple_choice', 1),
(24, 12, 'What are the qualities of an effective nursing leader?', '[]', '', 0, 'essay', 3),
(25, 12, 'Define the 5 steps of the nursing process.', '[]', '', 0, 'short_answer', 2),
(26, 14, 'Hey sino kgaba?', '[]', 'gokayg kalang?', 0, 'short_answer', 10);

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
  `publish_time` datetime DEFAULT NULL,
  `deadline_time` datetime DEFAULT NULL,
  `professor_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `content` longtext NOT NULL,
  `time_limit` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `title`, `description`, `created_by`, `created_at`, `status`, `publish_time`, `deadline_time`, `professor_id`, `module_id`, `lesson_id`, `content`, `time_limit`) VALUES
(6, 'TEST', 'TEST', NULL, '2025-09-15 12:12:57', 'active', NULL, NULL, 11, NULL, 9, '{\"instructions\":\"TEST LANG PO\"}', 0),
(8, 'TRY LANG PO ATE', 'TRYYYY', NULL, '2025-09-15 12:20:59', 'active', NULL, NULL, 11, NULL, 8, '{\"instructions\":\"TRYYYYY\"}', 10),
(9, 'QUIZ NO. 2', 'INTRODUCTION TO HUMAN ANATOMY', NULL, '2025-09-18 10:19:24', 'inactive', NULL, NULL, 11, NULL, 7, '{\"instructions\":\"Multiple Choice\"}', 2),
(10, 'Test ulit', 'Test ulit', NULL, '2025-09-18 10:29:29', 'active', '2025-09-18 18:28:00', '2025-09-19 17:28:00', 11, NULL, 8, '{\"instructions\":\"Testing ulit\"}', 0),
(11, 'MUP', 'MUP', NULL, '2025-11-15 07:35:36', 'active', NULL, '2025-11-16 18:38:00', 11, NULL, 8, '{\"instructions\":\"MUP\"}', 0),
(12, 'Basic Nursing Quiz', 'Basic quiz for nursing students', NULL, '2025-11-19 07:35:27', 'active', '2025-11-19 15:34:00', '2025-11-20 17:34:00', 11, NULL, 7, '{\"instructions\":\"Choose the best answer possible. As for the essays write the best answer you think suits the problem.\"}', 8),
(14, 'try lang', 'try langg', NULL, '2025-11-19 08:01:53', 'active', NULL, NULL, 11, 2, NULL, 'try', 8);

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
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attempt_number` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_attempts`
--

INSERT INTO `quiz_attempts` (`id`, `quiz_id`, `student_id`, `status`, `score`, `attempted_at`, `attempt_number`) VALUES
(46, 8, 10, 'Completed', 1, '2025-11-11 15:10:48', 1),
(71, 11, 10, 'Pending', 20, '2025-11-19 07:24:14', 19),
(72, 11, 10, 'Pending', 20, '2025-11-19 07:25:42', 2),
(73, 12, 10, 'Pending', 5, '2025-11-19 07:44:02', 1),
(74, 12, 10, 'Pending', 5, '2025-11-19 07:44:40', 2),
(75, 12, 10, 'Pending', 0, '2025-11-19 07:47:01', 3),
(76, 12, 10, 'Pending', 0, '2025-11-19 07:58:03', 4),
(77, 12, 17, 'Pending', 0, '2025-11-19 09:13:01', 1),
(78, 12, 17, 'Pending', 0, '2025-11-19 09:13:38', 2),
(79, 9, 10, 'Pending', 0, '2025-11-25 09:03:27', 1),
(80, 11, 10, 'Pending', 0, '2025-11-25 09:03:36', 3),
(81, 14, 10, 'Pending', 0, '2025-11-25 09:03:46', 1),
(82, 14, 10, 'Pending', 0, '2025-11-25 09:03:56', 2),
(83, 12, 10, 'Pending', 5, '2025-11-25 09:04:15', 5),
(84, 12, 10, 'Pending', 5, '2025-11-25 09:57:56', 6),
(85, 12, 10, 'Pending', 5, '2025-11-25 09:59:04', 7),
(86, 12, 10, 'Pending', 5, '2025-11-25 10:04:35', 8),
(87, 8, 10, 'Pending', 0, '2025-11-25 10:04:53', 2),
(88, 8, 10, 'Pending', 0, '2025-11-25 10:05:35', 3),
(89, 11, 10, 'Pending', 0, '2025-11-25 10:05:41', 4),
(90, 11, 10, 'Pending', 0, '2025-11-25 10:05:52', 5),
(91, 12, 10, 'Pending', 2, '2025-11-25 10:07:23', 9),
(92, 11, 10, 'Completed', 0, '2025-11-25 10:11:06', 6),
(93, 12, 10, 'Completed', 5, '2025-11-25 10:12:08', 10),
(94, 11, 10, 'Completed', 0, '2025-11-25 10:12:29', 7),
(95, 11, 10, 'Completed', 0, '2025-11-25 10:16:34', 8),
(96, 10, 10, 'Completed', 0, '2025-11-25 15:16:05', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `student_answer` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_answers`
--

INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `answer_id`, `created_at`, `answered_at`, `student_answer`) VALUES
(54, 46, 1, 1, '2025-11-11 15:10:48', '2025-11-11 15:10:48', NULL),
(55, 46, 2, 5, '2025-11-11 15:10:48', '2025-11-11 15:10:48', NULL),
(95, 73, 19, 63, '2025-11-19 07:44:02', '2025-11-19 07:44:02', NULL),
(96, 73, 20, 67, '2025-11-19 07:44:02', '2025-11-19 07:44:02', NULL),
(97, 73, 21, 73, '2025-11-19 07:44:02', '2025-11-19 07:44:02', NULL),
(98, 73, 22, 78, '2025-11-19 07:44:02', '2025-11-19 07:44:02', NULL),
(99, 73, 23, 80, '2025-11-19 07:44:02', '2025-11-19 07:44:02', NULL),
(100, 73, 24, NULL, '2025-11-19 07:44:02', '2025-11-19 07:44:02', 'Ewan'),
(101, 73, 25, NULL, '2025-11-19 07:44:02', '2025-11-19 07:44:02', 'Ewan'),
(102, 74, 19, 63, '2025-11-19 07:44:40', '2025-11-19 07:44:40', NULL),
(103, 74, 20, 67, '2025-11-19 07:44:40', '2025-11-19 07:44:40', NULL),
(104, 74, 21, 73, '2025-11-19 07:44:40', '2025-11-19 07:44:40', NULL),
(105, 74, 22, 78, '2025-11-19 07:44:40', '2025-11-19 07:44:40', NULL),
(106, 74, 23, 80, '2025-11-19 07:44:40', '2025-11-19 07:44:40', NULL),
(107, 74, 24, NULL, '2025-11-19 07:44:40', '2025-11-19 07:44:40', 'ewan ko'),
(108, 74, 25, NULL, '2025-11-19 07:44:40', '2025-11-19 07:44:40', 'ewan ko'),
(109, 75, 24, NULL, '2025-11-19 07:47:01', '2025-11-19 07:47:01', ''),
(110, 75, 25, NULL, '2025-11-19 07:47:01', '2025-11-19 07:47:01', ''),
(111, 76, 24, NULL, '2025-11-19 07:58:03', '2025-11-19 07:58:03', ''),
(112, 76, 25, NULL, '2025-11-19 07:58:03', '2025-11-19 07:58:03', ''),
(113, 77, 24, NULL, '2025-11-19 09:13:01', '2025-11-19 09:13:01', ''),
(114, 77, 25, NULL, '2025-11-19 09:13:01', '2025-11-19 09:13:01', ''),
(115, 78, 24, NULL, '2025-11-19 09:13:38', '2025-11-19 09:13:38', ''),
(116, 78, 25, NULL, '2025-11-19 09:13:38', '2025-11-19 09:13:38', ''),
(117, 81, 26, NULL, '2025-11-25 09:03:46', '2025-11-25 09:03:46', 'gg'),
(118, 82, 26, NULL, '2025-11-25 09:03:56', '2025-11-25 09:03:56', 'dvcsggdsggfs'),
(119, 83, 19, 63, '2025-11-25 09:04:15', '2025-11-25 09:04:15', NULL),
(120, 83, 20, 67, '2025-11-25 09:04:15', '2025-11-25 09:04:15', NULL),
(121, 83, 21, 73, '2025-11-25 09:04:15', '2025-11-25 09:04:15', NULL),
(122, 83, 22, 78, '2025-11-25 09:04:15', '2025-11-25 09:04:15', NULL),
(123, 83, 23, 80, '2025-11-25 09:04:15', '2025-11-25 09:04:15', NULL),
(124, 83, 24, NULL, '2025-11-25 09:04:15', '2025-11-25 09:04:15', 'efs'),
(125, 83, 25, NULL, '2025-11-25 09:04:15', '2025-11-25 09:04:15', 'fds'),
(126, 84, 19, 63, '2025-11-25 09:57:56', '2025-11-25 09:57:56', NULL),
(127, 84, 20, 67, '2025-11-25 09:57:56', '2025-11-25 09:57:56', NULL),
(128, 84, 21, 73, '2025-11-25 09:57:56', '2025-11-25 09:57:56', NULL),
(129, 84, 22, 78, '2025-11-25 09:57:56', '2025-11-25 09:57:56', NULL),
(130, 84, 23, 80, '2025-11-25 09:57:56', '2025-11-25 09:57:56', NULL),
(131, 84, 24, NULL, '2025-11-25 09:57:56', '2025-11-25 09:57:56', 'as'),
(132, 84, 25, NULL, '2025-11-25 09:57:56', '2025-11-25 09:57:56', 'as'),
(133, 85, 19, 63, '2025-11-25 09:59:04', '2025-11-25 09:59:04', NULL),
(134, 85, 20, 67, '2025-11-25 09:59:04', '2025-11-25 09:59:04', NULL),
(135, 85, 21, 73, '2025-11-25 09:59:04', '2025-11-25 09:59:04', NULL),
(136, 85, 22, 78, '2025-11-25 09:59:04', '2025-11-25 09:59:04', NULL),
(137, 85, 23, 80, '2025-11-25 09:59:04', '2025-11-25 09:59:04', NULL),
(138, 85, 24, NULL, '2025-11-25 09:59:04', '2025-11-25 09:59:04', 'as'),
(139, 85, 25, NULL, '2025-11-25 09:59:04', '2025-11-25 09:59:04', 'as'),
(140, 86, 19, 63, '2025-11-25 10:04:35', '2025-11-25 10:04:35', NULL),
(141, 86, 20, 67, '2025-11-25 10:04:35', '2025-11-25 10:04:35', NULL),
(142, 86, 21, 73, '2025-11-25 10:04:35', '2025-11-25 10:04:35', NULL),
(143, 86, 22, 78, '2025-11-25 10:04:35', '2025-11-25 10:04:35', NULL),
(144, 86, 23, 80, '2025-11-25 10:04:35', '2025-11-25 10:04:35', NULL),
(145, 86, 24, NULL, '2025-11-25 10:04:35', '2025-11-25 10:04:35', 'gh'),
(146, 86, 25, NULL, '2025-11-25 10:04:35', '2025-11-25 10:04:35', 'dfhg'),
(147, 91, 19, 66, '2025-11-25 10:07:23', '2025-11-25 10:07:23', NULL),
(148, 91, 20, 70, '2025-11-25 10:07:23', '2025-11-25 10:07:23', NULL),
(149, 91, 21, 74, '2025-11-25 10:07:23', '2025-11-25 10:07:23', NULL),
(150, 91, 22, 78, '2025-11-25 10:07:23', '2025-11-25 10:07:23', NULL),
(151, 91, 23, 80, '2025-11-25 10:07:23', '2025-11-25 10:07:23', NULL),
(152, 91, 24, NULL, '2025-11-25 10:07:23', '2025-11-25 10:07:23', 'df'),
(153, 91, 25, NULL, '2025-11-25 10:07:23', '2025-11-25 10:07:23', 'fd'),
(154, 93, 19, 63, '2025-11-25 10:12:08', '2025-11-25 10:12:08', NULL),
(155, 93, 20, 67, '2025-11-25 10:12:08', '2025-11-25 10:12:08', NULL),
(156, 93, 21, 73, '2025-11-25 10:12:08', '2025-11-25 10:12:08', NULL),
(157, 93, 22, 78, '2025-11-25 10:12:08', '2025-11-25 10:12:08', NULL),
(158, 93, 23, 80, '2025-11-25 10:12:08', '2025-11-25 10:12:08', NULL),
(159, 93, 24, NULL, '2025-11-25 10:12:08', '2025-11-25 10:12:08', 'eas'),
(160, 93, 25, NULL, '2025-11-25 10:12:08', '2025-11-25 10:12:08', 'asd');

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
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_progress`
--

INSERT INTO `student_progress` (`id`, `student_id`, `module_id`, `step_type`, `step_id`, `status`, `started_at`, `completed_at`, `updated_at`) VALUES
(6, 10, 2, 'lesson', 0, 'Completed', '2025-10-12 16:11:56', '2025-11-25 23:16:20', '2025-11-25 23:16:20'),
(7, 10, 1, 'lesson', 0, 'Completed', '2025-11-15 19:20:45', '2025-11-25 23:17:12', '2025-11-25 23:17:12');

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
  `gender` enum('Male','Female') NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','professor','dean') NOT NULL DEFAULT 'student',
  `section` varchar(50) DEFAULT NULL,
  `year` varchar(50) DEFAULT NULL,
  `student_id` varchar(50) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstName`, `lastName`, `username`, `email`, `gender`, `password`, `role`, `section`, `year`, `student_id`, `status`, `created_at`, `profile_pic`) VALUES
(1, 'russel', 'santos', '', 'Russ@gmail.com', 'Male', '1', 'dean', 'na', 'na', 'na', 'approved', '2025-09-12 18:41:36', NULL),
(2, '', '', '', '', 'Male', '', '', '', '', '', 'approved', '2025-09-12 18:41:36', NULL),
(3, 'Russel', 'Santos', 'RusSantos', 'rus@gmail.com', 'Male', '$2y$10$72B3kcB5fjfofceG1cBWzORZBFx5K4tfpgjpzX0h9Hz', '', '', '', '', 'approved', '2025-09-12 18:41:36', NULL),
(4, 'Russel', 'Santos', 'RusSantos023', 'RusSantos023@gmail.com', 'Male', '$2y$10$14Q.AZypV./eSpJojyXlQO92Sf9UT27K0W5D2455AFE', 'dean', '', '', '', 'approved', '2025-09-12 18:41:36', NULL),
(5, 'Mariah', 'Dela Cruz', 'mariah123', 'mariahdelacruz2003@gmail.com', 'Male', '$2y$10$.G4dHlhVKTZ9KHgDEhi5TOxZA8QUzi5JBSDoskKFdvO', '', '1A', '1', '', 'approved', '2025-09-12 18:41:36', NULL),
(6, 'Allain', 'Arcayera', 'Allain11', 'Allain@gmail.com', 'Male', '$2y$10$TQdxQkZti8xGzvMIGqg5Murhdty8AgcsflSXh7EeRNY', 'student', '2A', '2', '', 'approved', '2025-09-12 18:41:36', NULL),
(7, 'Maverick ', 'Austria', 'Maverick', 'Mavs@gmail.com', 'Male', '$2y$10$mVpbBWhjyMWmJMFgqDP5Z.Dyjlix0S9xreVofzuz73B', 'student', '1C', '1', '', 'approved', '2025-09-12 18:41:36', NULL),
(8, 'Russel', 'San', 'RusSan', 'RusSan@gmail.com', 'Male', '$2y$10$oUwBOK6zAwW5KLGo.ctbCOhnUI8rn009JrxhYoVg3KswrHqyK1RVS', 'student', '1B', '1', '', 'approved', '2025-09-12 18:41:36', NULL),
(9, 'Dean', 'Ambrose', 'DeanAmbrose', 'DeanAmbrose@gmail.com', 'Male', '$2y$10$9ESbmXBUKXAeql7LW5GP1.ERSMm170BrGdecFF9/CneIeiSQnnIXG', 'dean', '1A', '1', '', 'approved', '2025-09-12 18:41:36', NULL),
(10, 'russel', 'santos', 'Russel', 'Russel@gmail.com', 'Male', '$2y$10$MCnWjRopQARSJ2BvozN5S.C7iC3vOoBq6FiQLSNEWhrEV/jah8piC', 'student', '3B', '3', '', 'approved', '2025-09-12 18:41:36', 'uploads/profile_pics/profile_10_1759149624.jpg'),
(11, 'Prof', 'lang', 'prof', 'prof@gmail.com', 'Male', '$2y$10$dLiecC3EB4VGZVjgsu4kKefsZL0iyBbJSk9XrDwLPkKx6CmyYHR8O', 'professor', '3D', '3', '', 'approved', '2025-09-12 18:41:36', 'uploads/profiles/profile_11_1764056806.jpg'),
(12, 'Proff', 'profy', 'profy', 'Profy@gmail.com', 'Male', '$2y$10$5uX/JetPE3MlxU8tGlnKWOffISbVE0gl0.s.qQMdNVvPnTWwQrk6W', 'professor', NULL, NULL, '', 'approved', '2025-09-12 18:41:36', NULL),
(13, '', '', 'Dean', '', 'Male', '123456', 'dean', NULL, NULL, '', 'approved', '2025-09-12 18:41:36', NULL),
(14, 'Dean', 'Dean', 'dean1', 'dean@gmail.com', 'Male', '$2y$10$/WQUetNkYay0vy1fug8PculcJSCwoT6gEhEimNr6HAINWEVjUZ3wG', 'dean', '3D', '3', '', 'approved', '2025-09-12 18:41:36', NULL),
(15, 'John', 'Cena', 'John', 'John@gmail.com', 'Male', '$2y$10$Q.7GemI/uZG9/wh0gqGacOVSWBsxiUmeDlH3TAJK9VwpbmYjTrY.6', 'professor', NULL, NULL, '', 'approved', '2025-09-12 18:41:36', NULL),
(16, 'Prof', 'Rey', 'Rey', 'ReyMysterio@gmail.com', 'Male', '$2y$10$MriO2TabyDUTXUz7TieoN.FZFgU89Fdpln3vyLnNIzLZRZ9Z2ZVyW', 'professor', NULL, NULL, '', 'pending', '2025-09-12 18:46:53', NULL),
(17, 'Mavs', 'Austria', 'mavs', 'Mavs1@gmail.com', 'Male', '$2y$10$UIgwN7D5goIzOblv96t.4eEFHX9w8fOfxLJIlLgUsC1Xi7MloNTfK', 'student', '3A', '3', '', 'approved', '2025-09-17 13:41:52', 'uploads/profile_pics/profile_17_1759146667.jpg');

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
-- Indexes for table `module_progress`
--
ALTER TABLE `module_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_module` (`user_id`,`module_id`),
  ADD KEY `module_id` (`module_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `module_progress`
--
ALTER TABLE `module_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `nursing_tips`
--
ALTER TABLE `nursing_tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `student_progress`
--
ALTER TABLE `student_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
-- Constraints for table `module_progress`
--
ALTER TABLE `module_progress`
  ADD CONSTRAINT `module_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_progress_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

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

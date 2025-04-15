-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 06, 2025 at 06:25 PM
-- Server version: 11.6.2-MariaDB-log
-- PHP Version: 8.2.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `qlns`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `random_time` () RETURNS TIME DETERMINISTIC BEGIN
  DECLARE random_hour INT;
  DECLARE random_minute INT;
  SET random_hour = FLOOR(RAND() * 9) + 8; -- Between 8 AM and 5 PM
  SET random_minute = FLOOR(RAND() * 60);
  RETURN MAKETIME(random_hour, random_minute, 0);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `delete_day_log`
--

CREATE TABLE `delete_day_log` (
  `log_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `date_deleted` timestamp NULL DEFAULT NULL,
  `reason` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_code` varchar(50) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `kpi_name` varchar(100) NOT NULL,
  `kpi_unit` varchar(100) NOT NULL,
  `max_employees` int(11) NOT NULL,
  `default_kpi` decimal(10,2) NOT NULL,
  `default_salary` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_code`, `department_name`, `kpi_name`, `kpi_unit`, `max_employees`, `default_kpi`, `default_salary`, `created_at`, `updated_at`, `is_active`) VALUES
(3, 'HR', 'Phòng quản lý nhân sự', 'Số lượng nhân viên tuyển mới', 'Người', 10, '100.00', '10000000.00', '2025-03-14 07:29:56', '2025-03-17 06:29:28', 1),
(4, 'SALE', 'Phòng sale', 'Tỉ lệ chuyển đổi', '%', 15, '120.00', '12000000.00', '2025-03-14 07:29:56', '2025-03-17 06:22:10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `department_work_schedules`
--

CREATE TABLE `department_work_schedules` (
  `schedule_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `schedule_type_id` int(11) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `department_work_schedules`
--

INSERT INTO `department_work_schedules` (`schedule_id`, `department_id`, `schedule_type_id`, `start_time`, `end_time`, `created_at`, `updated_at`) VALUES
(3, 1, 1, '08:00:00', '17:00:00', '2025-03-14 07:29:56', '2025-03-14 07:29:56'),
(4, 2, 1, '08:00:00', '17:00:00', '2025-03-14 07:29:56', '2025-03-14 07:29:56');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `id_card_number` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_locked` tinyint(1) DEFAULT 0,
  `birth_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `user_id`, `first_name`, `last_name`, `address`, `phone_number`, `id_card_number`, `gender`, `department_id`, `created_at`, `updated_at`, `is_locked`, `birth_date`) VALUES
(5, 4, 'Sale 1', 'nhan vien', 'test', '09087978', '789789789', 'female', 4, '2025-03-14 07:36:07', '2025-03-21 03:36:38', 0, '2000-10-10'),
(6, 3, 'HR', 'nhan vien', 'test', '12321', '3123', 'male', 3, '2025-03-14 07:36:42', '2025-03-14 07:36:55', 0, '2000-01-01'),
(7, NULL, 'sale 2', 'Nhan Vien', '234234', '2342', '3423', 'male', 4, '2025-03-17 08:02:02', '2025-03-21 03:36:57', 0, '2000-01-01'),
(8, NULL, 'sale 3', 'Nhan vien', '23', '23', '4234', 'male', 4, '2025-03-18 09:06:01', '2025-03-21 03:37:12', 0, '2000-01-01'),
(9, NULL, 'sale 4', 'Nhan vien', '435', '34', '345', 'male', 4, '2025-03-18 09:06:10', '2025-03-21 03:37:23', 0, '2000-01-01'),
(10, NULL, 'sale 5', 'Nhan vien', '6575676557', '567567567567', '5675675665', 'male', 4, '2025-03-18 09:06:33', '2025-03-21 03:37:33', 0, '2000-01-01'),
(11, NULL, 'sale 6', 'Nhan vien', '5654645654', '456456456456456', '456546456456', 'male', 4, '2025-03-18 09:06:54', '2025-03-21 03:37:41', 0, '2000-01-01');

-- --------------------------------------------------------

--
-- Table structure for table `employee_bonuses_penalties`
--

CREATE TABLE `employee_bonuses_penalties` (
  `id` int(11) NOT NULL,
  `performance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `is_bonus` tinyint(1) NOT NULL COMMENT '1 = bonus, 0 = penalty',
  `reason` text NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Triggers `employee_bonuses_penalties`
--
DELIMITER $$
CREATE TRIGGER `after_delete_bonuses_penalties` AFTER DELETE ON `employee_bonuses_penalties` FOR EACH ROW BEGIN
    IF OLD.is_bonus = 1 THEN
        UPDATE employee_monthly_performance
        SET bonus_more = bonus_more - OLD.amount
        WHERE performance_id = OLD.performance_id;
    ELSE
        UPDATE employee_monthly_performance
        SET penalty_more = penalty_more - OLD.amount
        WHERE performance_id = OLD.performance_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_insert_bonuses_penalties` AFTER INSERT ON `employee_bonuses_penalties` FOR EACH ROW BEGIN
    IF NEW.is_bonus = 1 THEN
        UPDATE employee_monthly_performance
        SET bonus_more = bonus_more + NEW.amount
        WHERE performance_id = NEW.performance_id;
    ELSE
        UPDATE employee_monthly_performance
        SET penalty_more = penalty_more + NEW.amount
        WHERE performance_id = NEW.performance_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_update_bonuses_penalties` AFTER UPDATE ON `employee_bonuses_penalties` FOR EACH ROW BEGIN
    -- Xóa giá trị cũ
    IF OLD.is_bonus = 1 THEN
        UPDATE employee_monthly_performance
        SET bonus_more = bonus_more - OLD.amount
        WHERE performance_id = OLD.performance_id;
    ELSE
        UPDATE employee_monthly_performance
        SET penalty_more = penalty_more - OLD.amount
        WHERE performance_id = OLD.performance_id;
    END IF;

    -- Thêm giá trị mới
    IF NEW.is_bonus = 1 THEN
        UPDATE employee_monthly_performance
        SET bonus_more = bonus_more + NEW.amount
        WHERE performance_id = NEW.performance_id;
    ELSE
        UPDATE employee_monthly_performance
        SET penalty_more = penalty_more + NEW.amount
        WHERE performance_id = NEW.performance_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `employee_monthly_performance`
--

CREATE TABLE `employee_monthly_performance` (
  `performance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `individual_kpi_target` decimal(10,2) NOT NULL,
  `individual_base_salary` decimal(12,2) NOT NULL,
  `kpi_achieved` decimal(10,2) DEFAULT 0.00,
  `authorized_absences` int(11) DEFAULT 0,
  `unauthorized_absences` int(11) DEFAULT 0,
  `final_salary` decimal(12,2) DEFAULT 0.00,
  `salary_calculated` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `working_day` int(11) DEFAULT NULL,
  `allowance_shift_count` int(11) DEFAULT NULL,
  `total_allowance` int(11) NOT NULL DEFAULT 0,
  `late_day` int(11) NOT NULL DEFAULT 0,
  `penalty_more` int(11) NOT NULL DEFAULT 0,
  `bonus_more` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `employee_monthly_performance`
--

INSERT INTO `employee_monthly_performance` (`performance_id`, `employee_id`, `department_id`, `month`, `year`, `individual_kpi_target`, `individual_base_salary`, `kpi_achieved`, `authorized_absences`, `unauthorized_absences`, `final_salary`, `salary_calculated`, `created_at`, `updated_at`, `working_day`, `allowance_shift_count`, `total_allowance`, `late_day`, `penalty_more`, `bonus_more`) VALUES
(20, 6, 3, 3, 2025, '100.00', '15000000.00', '0.00', 0, 0, '0.00', 0, '2025-03-21 03:37:59', '2025-03-21 03:37:59', NULL, NULL, 0, 0, 0, 0),
(22, 5, 4, 3, 2025, '200.00', '10000000.00', '100.00', 0, 0, '5185000.00', 0, '2025-03-21 03:41:22', '2025-03-21 14:51:24', 17, 11, 385000, 2, 0, 0),
(23, 7, 4, 3, 2025, '200.00', '10000000.00', '0.00', 0, 0, '0.00', 0, '2025-03-21 03:41:22', '2025-03-21 14:51:24', 0, 0, 0, 0, 0, 0),
(24, 8, 4, 3, 2025, '200.00', '10000000.00', '0.00', 0, 0, '0.00', 0, '2025-03-21 03:41:22', '2025-03-21 14:51:24', 0, 0, 0, 0, 0, 0),
(25, 9, 4, 3, 2025, '200.00', '10000000.00', '0.00', 0, 0, '0.00', 0, '2025-03-21 03:41:22', '2025-03-21 14:51:24', 0, 0, 0, 0, 0, 0),
(26, 10, 4, 3, 2025, '200.00', '10000000.00', '0.00', 0, 0, '0.00', 0, '2025-03-21 03:41:22', '2025-03-21 14:51:24', 0, 0, 0, 0, 0, 0),
(27, 11, 4, 3, 2025, '200.00', '10000000.00', '0.00', 0, 0, '0.00', 0, '2025-03-21 03:41:22', '2025-03-21 14:51:24', 0, 0, 0, 0, 0, 0),
(28, 5, 4, 2, 2025, '200.00', '10000000.00', '190.00', 0, 0, '9695000.00', 0, '2025-03-21 03:41:22', '2025-03-21 14:37:03', 23, 17, 595000, 4, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `employee_requests`
--

CREATE TABLE `employee_requests` (
  `request_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `request_type` enum('leave','shift_change','absence','other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `reason_rejected` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_absence_authorized` tinyint(1) NOT NULL DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_work_schedules`
--

CREATE TABLE `employee_work_schedules` (
  `employee_schedule_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `schedule_type_id` int(11) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_authorized_absence` tinyint(1) NOT NULL DEFAULT 0,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `is_worked` tinyint(1) NOT NULL DEFAULT 0,
  `is_allowance` tinyint(1) DEFAULT 0,
  `is_late` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `employee_work_schedules`
--

INSERT INTO `employee_work_schedules` (`employee_schedule_id`, `employee_id`, `schedule_type_id`, `start_time`, `end_time`, `shift_id`, `effective_date`, `created_at`, `updated_at`, `is_authorized_absence`, `check_in`, `check_out`, `is_worked`, `is_allowance`, `is_late`) VALUES
(710, 5, 2, NULL, NULL, 1, '2025-03-01', '2025-03-21 03:42:17', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(711, 5, 2, NULL, NULL, 2, '2025-03-03', '2025-03-21 03:42:23', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(712, 5, 2, NULL, NULL, 3, '2025-03-04', '2025-03-21 03:42:29', '2025-03-21 14:50:53', 0, '13:10:00', '16:00:00', 1, 0, 1),
(713, 5, 2, NULL, NULL, 1, '2025-03-05', '2025-03-21 03:42:32', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(714, 5, 2, NULL, NULL, 1, '2025-03-06', '2025-03-21 03:42:35', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(715, 5, 2, NULL, NULL, 3, '2025-03-07', '2025-03-21 03:42:39', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(716, 5, 2, NULL, NULL, 1, '2025-03-08', '2025-03-21 03:42:42', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(717, 5, 2, NULL, NULL, 2, '2025-03-10', '2025-03-21 03:42:47', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(718, 5, 2, NULL, NULL, 3, '2025-03-11', '2025-03-21 03:42:52', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(719, 5, 2, NULL, NULL, 2, '2025-03-12', '2025-03-21 03:42:57', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(720, 5, 2, NULL, NULL, 3, '2025-03-13', '2025-03-21 03:43:02', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(721, 5, 2, NULL, NULL, 1, '2025-03-14', '2025-03-21 03:43:06', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(722, 5, 2, NULL, NULL, 2, '2025-03-15', '2025-03-21 03:43:11', '2025-03-21 14:50:53', 0, '17:10:00', '21:00:00', 1, 1, 1),
(723, 5, 2, NULL, NULL, 3, '2025-03-17', '2025-03-21 03:43:16', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(724, 5, 2, NULL, NULL, 2, '2025-03-18', '2025-03-21 03:43:21', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(725, 5, 2, NULL, NULL, 3, '2025-03-19', '2025-03-21 03:43:25', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(726, 5, 2, NULL, NULL, 2, '2025-03-20', '2025-03-21 03:43:30', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(727, 5, 2, NULL, NULL, 1, '2025-03-22', '2025-03-21 03:43:34', '2025-03-21 03:43:34', 0, NULL, NULL, 0, 0, 0),
(728, 5, 2, NULL, NULL, 1, '2025-03-25', '2025-03-21 03:43:37', '2025-03-21 03:43:37', 0, NULL, NULL, 0, 0, 0),
(729, 5, 2, NULL, NULL, 1, '2025-03-24', '2025-03-21 03:43:40', '2025-03-21 03:43:40', 0, NULL, NULL, 0, 0, 0),
(730, 5, 2, NULL, NULL, 1, '2025-03-26', '2025-03-21 03:43:44', '2025-03-21 03:43:44', 0, NULL, NULL, 0, 0, 0),
(731, 5, 2, NULL, NULL, 1, '2025-03-27', '2025-03-21 03:43:47', '2025-03-21 03:43:47', 0, NULL, NULL, 0, 0, 0),
(732, 5, 2, NULL, NULL, 1, '2025-03-29', '2025-03-21 03:43:52', '2025-03-21 03:43:52', 0, NULL, NULL, 0, 0, 0),
(733, 5, 2, NULL, NULL, 1, '2025-02-01', '2025-03-20 20:42:17', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(734, 5, 2, NULL, NULL, 2, '2025-02-03', '2025-03-20 20:42:23', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(735, 5, 2, NULL, NULL, 3, '2025-02-04', '2025-03-20 20:42:29', '2025-03-21 14:50:53', 0, '13:10:00', '16:00:00', 1, 0, 1),
(736, 5, 2, NULL, NULL, 1, '2025-02-05', '2025-03-20 20:42:32', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(737, 5, 2, NULL, NULL, 1, '2025-02-06', '2025-03-20 20:42:35', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(738, 5, 2, NULL, NULL, 3, '2025-02-07', '2025-03-20 20:42:39', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(739, 5, 2, NULL, NULL, 1, '2025-02-08', '2025-03-20 20:42:42', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(740, 5, 2, NULL, NULL, 2, '2025-02-10', '2025-03-20 20:42:47', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(741, 5, 2, NULL, NULL, 3, '2025-02-11', '2025-03-20 20:42:52', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(742, 5, 2, NULL, NULL, 2, '2025-02-12', '2025-03-20 20:42:57', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(743, 5, 2, NULL, NULL, 3, '2025-02-13', '2025-03-20 20:43:02', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(744, 5, 2, NULL, NULL, 1, '2025-02-14', '2025-03-20 20:43:06', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(745, 5, 2, NULL, NULL, 2, '2025-02-15', '2025-03-20 20:43:11', '2025-03-21 14:50:53', 0, '17:10:00', '21:00:00', 1, 1, 1),
(746, 5, 2, NULL, NULL, 3, '2025-02-17', '2025-03-20 20:43:16', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(747, 5, 2, NULL, NULL, 2, '2025-02-18', '2025-03-20 20:43:21', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(748, 5, 2, NULL, NULL, 3, '2025-02-19', '2025-03-20 20:43:25', '2025-03-21 14:50:53', 0, '13:00:00', '16:00:00', 1, 0, 0),
(749, 5, 2, NULL, NULL, 2, '2025-02-20', '2025-03-20 20:43:30', '2025-03-21 14:50:53', 0, '17:00:00', '21:00:00', 1, 1, 0),
(750, 5, 2, NULL, NULL, 1, '2025-02-22', '2025-03-20 20:43:34', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(751, 5, 2, NULL, NULL, 1, '2025-02-25', '2025-03-20 20:43:37', '2025-03-21 14:50:53', 0, '08:10:00', '12:00:00', 1, 1, 1),
(752, 5, 2, NULL, NULL, 1, '2025-02-24', '2025-03-20 20:43:40', '2025-03-21 14:50:53', 0, NULL, NULL, 1, 0, 0),
(753, 5, 2, NULL, NULL, 1, '2025-02-26', '2025-03-20 20:43:44', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(754, 5, 2, NULL, NULL, 1, '2025-02-27', '2025-03-20 20:43:47', '2025-03-21 14:50:53', 0, '08:00:00', '12:00:00', 1, 1, 0),
(755, 5, 2, NULL, NULL, 1, '2025-02-28', '2025-03-20 20:43:52', '2025-03-21 14:50:53', 0, '08:21:00', '12:00:00', 1, 1, 1);

--
-- Triggers `employee_work_schedules`
--
DELIMITER $$
CREATE TRIGGER `before_employee_schedule_insert_or_update` BEFORE INSERT ON `employee_work_schedules` FOR EACH ROW BEGIN
    IF NEW.check_in IS NOT NULL OR NEW.check_out IS NOT NULL THEN
        SET NEW.is_authorized_absence = 0;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_employee_schedule_update` BEFORE UPDATE ON `employee_work_schedules` FOR EACH ROW BEGIN
    IF NEW.check_in IS NOT NULL OR NEW.check_out IS NOT NULL THEN
        SET NEW.is_authorized_absence = 0;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_department_settings`
--

CREATE TABLE `monthly_department_settings` (
  `setting_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `kpi_target` decimal(10,2) NOT NULL,
  `base_salary` decimal(12,2) NOT NULL,
  `daily_meal_allowance` decimal(10,2) DEFAULT 0.00,
  `unauthorized_absence_penalty` decimal(10,2) DEFAULT 0.00,
  `is_locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `late_arrival_penalty` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `monthly_department_settings`
--

INSERT INTO `monthly_department_settings` (`setting_id`, `department_id`, `month`, `year`, `kpi_target`, `base_salary`, `daily_meal_allowance`, `unauthorized_absence_penalty`, `is_locked`, `created_at`, `updated_at`, `late_arrival_penalty`) VALUES
(5, 3, 3, 2025, '100.00', '15000000.00', '35000.00', '450000.00', 0, '2025-03-15 06:58:52', '2025-03-17 14:31:44', '100000.00'),
(6, 4, 3, 2025, '200.00', '10000000.00', '35000.00', '500000.00', 0, '2025-03-15 07:01:31', '2025-03-21 03:35:17', '100000.00'),
(7, 3, 2, 2025, '100.00', '15000000.00', '35000.00', '450000.00', 1, '2025-02-15 06:58:52', '2025-03-21 03:39:09', '100000.00'),
(8, 4, 2, 2025, '200.00', '10000000.00', '35000.00', '500000.00', 1, '2025-02-15 07:01:31', '2025-03-21 03:39:12', '100000.00');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(11, 'Admin'),
(10, 'Nhân viên'),
(12, 'Quản Lý Nhân Sự');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `shift_id` int(11) NOT NULL,
  `shift_name` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`shift_id`, `shift_name`, `start_time`, `end_time`) VALUES
(1, 'Sáng', '08:00:00', '12:00:00'),
(2, 'Tối', '17:00:00', '21:00:00'),
(3, 'Chiều', '13:00:00', '16:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_locked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `role_id`, `created_at`, `is_locked`) VALUES
(3, 'nhansu', '$2y$10$xTaKG23ElYcuH5KyVd656OcmoqzaVnkXGrD57DeNiTcq59bHGDkr6', 'employee_hr@example.com', 12, '2025-03-14 07:29:56', 0),
(4, 'nhanvien', '$2y$10$rERiel4jFh6OU9j8G0nAIu1Y.QxNCm5KPrf8GbzGKr5Gnt3JVyCOW', 'employee_sale@example.com', 10, '2025-03-14 07:29:56', 0),
(5, 'admin', '$2y$10$PmECZSc2jfetBPYfCIX5tO5w8iFSS86URj2sSyYzlP38pTNFqKknK', 'admin@gmail.com', 11, '2025-03-14 07:30:16', 0);

-- --------------------------------------------------------

--
-- Table structure for table `work_schedule_types`
--

CREATE TABLE `work_schedule_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `work_schedule_types`
--

INSERT INTO `work_schedule_types` (`type_id`, `type_name`, `description`) VALUES
(1, 'Ca cố định', 'Fixed working hours schedule'),
(2, 'Ca Linh Hoạt', 'Shift-based working schedule');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `delete_day_log`
--
ALTER TABLE `delete_day_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_code` (`department_code`);

--
-- Indexes for table `department_work_schedules`
--
ALTER TABLE `department_work_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `schedule_type_id` (`schedule_type_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `id_card_number` (`id_card_number`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `employee_bonuses_penalties`
--
ALTER TABLE `employee_bonuses_penalties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bonuses_penalties_performance` (`performance_id`),
  ADD KEY `fk_bonuses_penalties_employee` (`employee_id`);

--
-- Indexes for table `employee_monthly_performance`
--
ALTER TABLE `employee_monthly_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`,`month`,`year`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `employee_requests`
--
ALTER TABLE `employee_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `employee_work_schedules`
--
ALTER TABLE `employee_work_schedules`
  ADD PRIMARY KEY (`employee_schedule_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `schedule_type_id` (`schedule_type_id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `monthly_department_settings`
--
ALTER TABLE `monthly_department_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `department_id` (`department_id`,`month`,`year`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`shift_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `work_schedule_types`
--
ALTER TABLE `work_schedule_types`
  ADD PRIMARY KEY (`type_id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `delete_day_log`
--
ALTER TABLE `delete_day_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `department_work_schedules`
--
ALTER TABLE `department_work_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `employee_bonuses_penalties`
--
ALTER TABLE `employee_bonuses_penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `employee_monthly_performance`
--
ALTER TABLE `employee_monthly_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `employee_requests`
--
ALTER TABLE `employee_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `employee_work_schedules`
--
ALTER TABLE `employee_work_schedules`
  MODIFY `employee_schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=756;

--
-- AUTO_INCREMENT for table `monthly_department_settings`
--
ALTER TABLE `monthly_department_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `work_schedule_types`
--
ALTER TABLE `work_schedule_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `delete_day_log`
--
ALTER TABLE `delete_day_log`
  ADD CONSTRAINT `delete_day_log_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `department_work_schedules`
--
ALTER TABLE `department_work_schedules`
  ADD CONSTRAINT `department_work_schedules_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  ADD CONSTRAINT `department_work_schedules_ibfk_2` FOREIGN KEY (`schedule_type_id`) REFERENCES `work_schedule_types` (`type_id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `employee_bonuses_penalties`
--
ALTER TABLE `employee_bonuses_penalties`
  ADD CONSTRAINT `fk_bonuses_penalties_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bonuses_penalties_performance` FOREIGN KEY (`performance_id`) REFERENCES `employee_monthly_performance` (`performance_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employee_monthly_performance`
--
ALTER TABLE `employee_monthly_performance`
  ADD CONSTRAINT `employee_monthly_performance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employee_monthly_performance_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `employee_requests`
--
ALTER TABLE `employee_requests`
  ADD CONSTRAINT `employee_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employee_requests_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`shift_id`),
  ADD CONSTRAINT `employee_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `employee_work_schedules`
--
ALTER TABLE `employee_work_schedules`
  ADD CONSTRAINT `employee_work_schedules_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employee_work_schedules_ibfk_2` FOREIGN KEY (`schedule_type_id`) REFERENCES `work_schedule_types` (`type_id`),
  ADD CONSTRAINT `employee_work_schedules_ibfk_3` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`shift_id`);

--
-- Constraints for table `monthly_department_settings`
--
ALTER TABLE `monthly_department_settings`
  ADD CONSTRAINT `monthly_department_settings_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

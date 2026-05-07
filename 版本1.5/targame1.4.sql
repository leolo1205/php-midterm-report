-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-05-07 20:09:13
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `targame`
--

-- --------------------------------------------------------

--
-- 資料表結構 `monster_stats`
--

CREATE TABLE `monster_stats` (
  `level` int(11) NOT NULL,
  `hp` int(11) NOT NULL,
  `dmg` int(11) NOT NULL,
  `def` int(11) NOT NULL,
  `exp` int(11) NOT NULL,
  `gold` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `monster_stats`
--

INSERT INTO `monster_stats` (`level`, `hp`, `dmg`, `def`, `exp`, `gold`) VALUES
(1, 40, 12, 0, 15, 10),
(2, 60, 15, 1, 20, 15),
(3, 85, 18, 1, 25, 20),
(4, 110, 22, 2, 35, 25),
(5, 150, 28, 3, 50, 35),
(6, 190, 32, 3, 60, 45),
(7, 240, 36, 4, 75, 55),
(8, 290, 42, 5, 90, 65),
(9, 350, 48, 6, 110, 80),
(10, 450, 60, 8, 150, 120),
(11, 520, 66, 9, 175, 140),
(12, 600, 72, 10, 200, 160),
(13, 690, 78, 11, 230, 180),
(14, 790, 85, 12, 260, 200),
(15, 900, 100, 15, 320, 250),
(16, 1050, 110, 16, 360, 280),
(17, 1200, 120, 18, 400, 310),
(18, 1350, 130, 20, 450, 340),
(19, 1500, 145, 22, 500, 380),
(20, 2000, 180, 30, 800, 600);

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `level` int(11) DEFAULT 1,
  `exp` int(11) DEFAULT 0,
  `hp` int(11) DEFAULT 100,
  `max_hp` int(11) DEFAULT 100,
  `dmg` int(11) DEFAULT 10,
  `def` int(11) DEFAULT 0,
  `stat_points` int(11) DEFAULT 0,
  `gold` int(11) DEFAULT 0,
  `max_floor` int(11) DEFAULT 0,
  `last_train_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `username`, `level`, `exp`, `hp`, `max_hp`, `dmg`, `def`, `stat_points`, `gold`, `max_floor`, `last_train_time`) VALUES
(1, '玄墨', 15, 275, 620, 620, 151, 16, 1, 14271, 7, '2026-05-08 02:08:18');

-- --------------------------------------------------------

--
-- 資料表結構 `user_skills`
--

CREATE TABLE `user_skills` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `skill_id` varchar(50) NOT NULL,
  `level` int(11) DEFAULT 0,
  `exp` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `user_skills`
--

INSERT INTO `user_skills` (`id`, `user_id`, `skill_id`, `level`, `exp`) VALUES
(9, 1, 'crit', 1, 17),
(10, 1, 'dodge', 1, 15);

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `monster_stats`
--
ALTER TABLE `monster_stats`
  ADD PRIMARY KEY (`level`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `user_skills`
--
ALTER TABLE `user_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_skill` (`user_id`,`skill_id`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_skills`
--
ALTER TABLE `user_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

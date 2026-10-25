-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 20, 2025 at 05:00 PM
-- Server version: 5.7.24
-- PHP Version: 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `library_room_booking`
--
-- NOTE (GitHub version): tr_AutoBlacklist redesigned, admin_history enum extended.
-- Old SHA-256 password hashes are upgraded to password_hash() automatically on first login.
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_AdminHistory_Update` (IN `p_admin_id` BIGINT, IN `p_action_type` ENUM('ADD_ROOM','EDIT_ROOM','DELETE_ROOM','CANCEL_RESERVATION','VERIFY_STUDENT','BLACKLIST_STUDENT'), IN `p_target_table` VARCHAR(50), IN `p_target_id` INT, IN `p_description` TEXT)   BEGIN
    -- Validate admin role
    DECLARE v_role VARCHAR(20);
    SELECT Role INTO v_role FROM user WHERE User_ID = p_admin_id;
    IF v_role != 'admin' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only admin may write to admin_history.';
    END IF;

    INSERT INTO admin_history (user_id, action_type, target_table, target_id, description, action_timestamp)
    VALUES (p_admin_id, p_action_type, p_target_table, p_target_id, IFNULL(p_description,''), NOW());

    SELECT 'Admin history logged.' AS message;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_CancelReservation` (IN `p_book_id` INT)   proc: BEGIN
    DECLARE v_reservation_id INT;
    DECLARE v_remaining INT;

    -- 1) หา reservation id จาก book id
    SELECT Reservation_ID INTO v_reservation_id
    FROM book
    WHERE Book_ID = p_book_id;

    -- ถ้าไม่พบ book_id → return not_found
    IF v_reservation_id IS NULL THEN
        SELECT 'not_found' AS status;
        LEAVE proc;
    END IF;

    -- 2) ลบ slot เฉพาะอันนี้
    DELETE FROM book
    WHERE Book_ID = p_book_id;

    -- 3) ตรวจสอบว่ายังมี slot เหลือไหม
    SELECT COUNT(*) INTO v_remaining
    FROM book
    WHERE Reservation_ID = v_reservation_id;

    -- 4) ถ้ามี slot เหลือ → update min/max
    IF v_remaining > 0 THEN

        UPDATE reservation r
        JOIN (
            SELECT 
                Booking_Date,
                MIN(BStart_Time) AS minStart,
                MAX(BEnd_Time) AS maxEnd
            FROM book
            WHERE Reservation_ID = v_reservation_id
            GROUP BY Booking_Date
            LIMIT 1
        ) AS t
        ON r.Reservation_ID = v_reservation_id
        SET 
            r.Booking_Date = t.Booking_Date,
            r.BStart_Time = t.minStart,
            r.BEnd_Time = t.maxEnd;

    ELSE
        -- 5) ถ้า slot หมด → reservation cancelled (แต่เก็บเวลาไว้)
        UPDATE reservation
        SET R_Status = 'cancelled'
        WHERE Reservation_ID = v_reservation_id;
    END IF;

    -- 6) ส่งผลลัพธ์
    SELECT 
        'success' AS status,
        v_reservation_id AS reservation_id,
        v_remaining AS remaining_slots;

END proc$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_CreateReservation` (IN `p_user_id` INT, IN `p_room_id` INT)   BEGIN
    DECLARE cnt INT;

    -- check if user already booked today
    SELECT COUNT(*) INTO cnt
    FROM reservation
    WHERE User_ID = p_user_id
    AND DATE(Reservation_Date) = CURDATE();

    IF cnt >= 2 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Daily booking limit reached';
    END IF;

    INSERT INTO reservation(User_ID, Room_ID, R_Status)
    VALUES(p_user_id, p_room_id, 'pending');

    SELECT LAST_INSERT_ID() AS reservation_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_SendNotification` (IN `p_event_type` ENUM('RESERVE_SUCCESS','RESERVE_CANCEL'), IN `p_reservation_id` INT)   BEGIN
    DECLARE v_user_id BIGINT;
    DECLARE v_room_id INT;
    DECLARE v_room_name VARCHAR(255);
    DECLARE v_date DATE;
    DECLARE v_start TIME;
    DECLARE v_end TIME;
    DECLARE v_message TEXT;

    -- Load reservation owner
    SELECT User_ID, Room_ID
    INTO v_user_id, v_room_id
    FROM reservation
    WHERE Reservation_ID = p_reservation_id;

    -- Load room name
    SELECT Room_Name INTO v_room_name
    FROM room 
    WHERE Room_ID = v_room_id;

    -- Load booking details from book
    SELECT 
        Booking_Date,
        MIN(BStart_Time),
        MAX(BEnd_Time)
    INTO v_date, v_start, v_end
    FROM book
    WHERE Reservation_ID = p_reservation_id
    GROUP BY Booking_Date
    LIMIT 1;

    -- If no book rows → use reservation table (history still intact)
    IF v_date IS NULL THEN
        SELECT Booking_Date, BStart_Time, BEnd_Time
        INTO v_date, v_start, v_end
        FROM reservation
        WHERE Reservation_ID = p_reservation_id;
    END IF;

    -- Create Message
    IF p_event_type = 'RESERVE_SUCCESS' THEN
        SET v_message = CONCAT(
            'Reservation confirmed for room "', v_room_name,
            '" on ', v_date,
            ' from ', v_start, ' to ', v_end, '.'
        );
    ELSEIF p_event_type = 'RESERVE_CANCEL' THEN
        SET v_message = CONCAT(
            'Your reservation for room "', v_room_name,
            '" on ', v_date,
            '" has been cancelled.'
        );
    ELSE
        SET v_message = 'Notification regarding your reservation.';
    END IF;

    -- Insert Notification
    INSERT INTO notification(User_ID, Reservation_ID, Message, SentTime, IsRead)
    VALUES(v_user_id, p_reservation_id, v_message, NOW(), 0);

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_history`
--

CREATE TABLE `admin_history` (
  `history_id` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `action_type` enum('ADD_ROOM','EDIT_ROOM','DELETE_ROOM','CANCEL_RESERVATION','VERIFY_STUDENT','BLACKLIST_STUDENT','EDIT_ADMIN','EDIT_BLACKLIST','ADD_ADMIN','DELETE_ADMIN','SYSTEM_UPDATE') NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `action_timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `admin_history`
--

INSERT INTO `admin_history` (`history_id`, `user_id`, `action_type`, `target_table`, `target_id`, `action_timestamp`, `description`) VALUES
(7051, 215, 'BLACKLIST_STUDENT', 'user', 105, '2025-11-20 22:29:30', 'Admin restored student to active'),
(7052, 215, 'DELETE_ROOM', 'room', 8, '2025-11-20 22:30:48', 'Room deleted'),
(7053, 215, 'BLACKLIST_STUDENT', 'user', 227, '2025-11-20 23:12:23', 'Admin blacklisted student'),
(7054, 215, 'EDIT_ROOM', 'room', 1, '2025-11-20 23:32:29', 'Changed availability to unavailable'),
(7055, 215, 'EDIT_ROOM', 'room', 1, '2025-11-20 23:32:32', 'Changed availability to available');

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE `blacklist` (
  `Blacklist_ID` int(11) NOT NULL,
  `User_ID` bigint(11) NOT NULL,
  `Reason` varchar(255) NOT NULL,
  `Blacklist_Status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `Date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Date_removed` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `blacklist`
--

INSERT INTO `blacklist` (`Blacklist_ID`, `User_ID`, `Reason`, `Blacklist_Status`, `Date_added`, `Date_removed`) VALUES
(201, 104, 'Repeated no-shows (3 times)', 'active', '2025-11-01 09:00:00', NULL),
(211, 227, 'Admin action', 'active', '2025-11-20 23:12:23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `book`
--

CREATE TABLE `book` (
  `Book_ID` int(11) NOT NULL,
  `Reservation_ID` int(11) NOT NULL,
  `Room_ID` int(11) NOT NULL,
  `Booking_Date` date NOT NULL,
  `BStart_Time` time NOT NULL,
  `BEnd_Time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `book`
--

INSERT INTO `book` (`Book_ID`, `Reservation_ID`, `Room_ID`, `Booking_Date`, `BStart_Time`, `BEnd_Time`) VALUES
(66, 5057, 1, '2025-11-20', '15:00:00', '16:00:00'),
(67, 5058, 2, '2025-11-20', '15:00:00', '16:00:00'),
(68, 5058, 2, '2025-11-20', '17:00:00', '18:00:00'),
(69, 5059, 2, '2025-11-20', '14:00:00', '15:00:00'),
(70, 5059, 2, '2025-11-20', '16:00:00', '17:00:00');

--
-- Triggers `book`
--
DELIMITER $$
CREATE TRIGGER `prevent_double_booking` BEFORE INSERT ON `book` FOR EACH ROW BEGIN

    IF EXISTS (
        SELECT 1 
        FROM book
        WHERE Room_ID = NEW.Room_ID
        AND Booking_Date = NEW.Booking_Date
        AND NOT (
            NEW.BEnd_Time   <= BStart_Time OR 
            NEW.BStart_Time >= BEnd_Time
        )
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Time slot overlaps with existing booking';
    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `Notification_ID` int(11) NOT NULL,
  `User_ID` bigint(11) NOT NULL,
  `Reservation_ID` int(11) NOT NULL,
  `Message` text NOT NULL,
  `SentTime` datetime NOT NULL,
  `IsRead` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`Notification_ID`, `User_ID`, `Reservation_ID`, `Message`, `SentTime`, `IsRead`) VALUES
(12, 101, 5057, 'Your reservation for room \"Study Room A\" on 2025-11-20\" has been cancelled.', '2025-11-20 23:32:29', 0);

-- --------------------------------------------------------

--
-- Table structure for table `reservation`
--

CREATE TABLE `reservation` (
  `Reservation_ID` int(11) NOT NULL,
  `User_ID` bigint(11) NOT NULL,
  `Room_ID` int(11) NOT NULL,
  `Reservation_Date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `R_Status` enum('active','cancelled','completed','Pending') NOT NULL DEFAULT 'active',
  `Booking_Date` date DEFAULT NULL,
  `BStart_Time` time DEFAULT NULL,
  `BEnd_Time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `reservation`
--

INSERT INTO `reservation` (`Reservation_ID`, `User_ID`, `Room_ID`, `Reservation_Date`, `R_Status`, `Booking_Date`, `BStart_Time`, `BEnd_Time`) VALUES
(5057, 101, 1, '2025-11-20 13:02:15', 'completed', '2025-11-20', '15:00:00', '16:00:00'),
(5058, 102, 2, '2025-11-20 13:22:40', 'active', '2025-11-20', '15:00:00', '18:00:00'),
(5059, 105, 2, '2025-11-20 13:51:37', 'Pending', '2025-11-20', '14:00:00', '17:00:00'),
(5061, 101, 1, '2025-11-20 16:44:43', 'cancelled', '2025-11-20', '16:00:00', '17:00:00');

--
-- Triggers `reservation`
--
DELIMITER $$
CREATE TRIGGER `tr_UpdateCancelCount` AFTER UPDATE ON `reservation` FOR EACH ROW BEGIN
    IF NEW.R_Status = 'cancelled' AND OLD.R_Status != 'cancelled' THEN
        UPDATE user
        SET Cancellation_Count = COALESCE(Cancellation_Count,0) + 1
        WHERE User_ID = NEW.User_ID;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `Room_ID` int(11) NOT NULL,
  `Room_Name` varchar(50) NOT NULL,
  `Capacity` int(11) NOT NULL,
  `Equipment` text NOT NULL,
  `Room_Status` enum('available','unavailable') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `room`
--

INSERT INTO `room` (`Room_ID`, `Room_Name`, `Capacity`, `Equipment`, `Room_Status`) VALUES
(1, 'Study Room A', 4, 'Whiteboard, HDMI', 'available'),
(2, 'Meeting Room B', 8, 'Projector', 'available'),
(3, 'Silent Zone C', 2, 'No equipment', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `Config_ID` int(11) NOT NULL,
  `Config_Name` varchar(100) NOT NULL,
  `Config_Value` varchar(100) NOT NULL,
  `Description` text,
  `Last_Updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `Updated_By` bigint(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`Config_ID`, `Config_Name`, `Config_Value`, `Description`, `Last_Updated`, `Updated_By`) VALUES
(1, 'system_status', 'online', 'Whether system is online', '2025-11-19 19:16:45', 200),
(2, 'maintenance_mode', 'off', 'Enable maintenance mode', '2025-11-19 19:16:45', 200),
(3, 'data_backup', 'on', 'Automatic backup enabled', '2025-11-19 19:16:45', 200),
(4, 'max_booking_hours', '2', '                                Maximum hours per booking                            ', '2025-11-18 20:25:42', 200),
(5, 'max_reservations_per_day', '2', 'Daily booking limit', '2025-11-19 19:16:45', 200),
(6, 'system_version', 'v1.0', 'Version number', '2025-11-19 19:16:45', 200),
(7, 'term_of_use', 'Default Terms of Use text', 'Legal terms', '2025-11-19 19:16:45', 200),
(8, 'privacy_policy', 'Default Privacy Policy text', 'Privacy terms', '2025-11-19 19:16:45', 200);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `User_ID` bigint(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `Role` enum('student','admin','super admin') NOT NULL DEFAULT 'student',
  `Status` enum('active','blacklisted') NOT NULL DEFAULT 'active',
  `Cancellation_Count` int(11) NOT NULL DEFAULT '0',
  `Verify_Status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `Profile_Image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`User_ID`, `Name`, `Email`, `PasswordHash`, `Role`, `Status`, `Cancellation_Count`, `Verify_Status`, `Profile_Image`) VALUES
(101, 'Spongebob SquarePants', 'spongebob@bikini.com', '673d190b758967621da243f06c350ce68be4276174dc886560239fea923d4a5a', 'student', 'active', 1, 'verified', 'profile_101_1763565860.jpg'),
(102, 'Patrick Star', 'patrick@bikini.com', 'c0df64036c36df6eabd6951f3442a74a38e5ce8556f4d579b83eda535495f1cc', 'student', 'active', 1, 'verified', NULL),
(104, 'Sheldon J. Plankton', 'plankton@bikini.com', '001dfb5676627c25bcac7bf3393d3e8b1e2e9a0735947365fe618a168b202a19', 'student', 'blacklisted', 3, 'verified', NULL),
(105, 'Alice Wonderland', 'alicewdl@bikini.com', 'a3fde670ed73691a7616cbca46acf25a1ca39c4d307fb435f697726dbae840c1', 'student', 'active', 0, 'verified', NULL),
(200, 'Admin', 'admin@example.com', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'super admin', 'active', 0, 'verified', NULL),
(215, 'Ashan', 'ashan@gmail.com', 'c8e422e57ee12178999eb0e2fbb5c200ee419184c3dd3ba5f801d0acfa7d94f9', 'admin', 'active', 0, 'verified', NULL),
(216, 'test', 'test@gmail.com', 'ecd71870d1963316a97e3ac3408c9835ad8cf0f3c1bc703527c30265534f75ae', 'admin', 'active', 0, 'verified', NULL),
(227, 'Nicha', 'nicha@gmail.com', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 'student', 'blacklisted', 0, 'verified', NULL);

--
-- Triggers `user`
--
DELIMITER $$
CREATE TRIGGER `tr_AutoBlacklist` BEFORE UPDATE ON `user` FOR EACH ROW BEGIN
    -- ยกเลิกครบ 3 ครั้ง -> blacklist อัตโนมัติ (เฉพาะ student)
    -- ใช้ BEFORE UPDATE + SET NEW.Status เพราะ MySQL ไม่ให้ trigger UPDATE ตาราง user ตัวเอง (error 1442)
    IF NEW.Cancellation_Count >= 3 AND OLD.Cancellation_Count < 3
       AND NEW.Role = 'student' AND NEW.Status <> 'blacklisted' THEN
        SET NEW.Status = 'blacklisted';
        INSERT INTO blacklist (User_ID, Reason, Blacklist_Status, Date_added)
        VALUES (NEW.User_ID, 'Auto: Excessive cancellations', 'active', NOW());
    END IF;

    -- ปลด blacklist -> รีเซ็ตยอดยกเลิก เพื่อให้ auto-blacklist ทำงานได้อีกในอนาคต
    IF OLD.Status = 'blacklisted' AND NEW.Status = 'active' THEN
        SET NEW.Cancellation_Count = 0;
    END IF;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_history`
--
ALTER TABLE `admin_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD PRIMARY KEY (`Blacklist_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `book`
--
ALTER TABLE `book`
  ADD PRIMARY KEY (`Book_ID`),
  ADD KEY `Reservation_ID` (`Reservation_ID`),
  ADD KEY `Room_ID` (`Room_ID`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`Notification_ID`),
  ADD KEY `Reservation_ID` (`Reservation_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `reservation`
--
ALTER TABLE `reservation`
  ADD PRIMARY KEY (`Reservation_ID`),
  ADD KEY `Room_ID` (`Room_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`Room_ID`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`Config_ID`),
  ADD UNIQUE KEY `Config_Name` (`Config_Name`),
  ADD KEY `User_ID` (`Updated_By`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_history`
--
ALTER TABLE `admin_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7056;

--
-- AUTO_INCREMENT for table `blacklist`
--
ALTER TABLE `blacklist`
  MODIFY `Blacklist_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;

--
-- AUTO_INCREMENT for table `book`
--
ALTER TABLE `book`
  MODIFY `Book_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `Notification_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `reservation`
--
ALTER TABLE `reservation`
  MODIFY `Reservation_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5062;

--
-- AUTO_INCREMENT for table `system_config`
--
ALTER TABLE `system_config`
  MODIFY `Config_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `User_ID` bigint(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=228;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_history`
--
ALTER TABLE `admin_history`
  ADD CONSTRAINT `admin_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`User_ID`);

--
-- Constraints for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD CONSTRAINT `blacklist_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`);

--
-- Constraints for table `book`
--
ALTER TABLE `book`
  ADD CONSTRAINT `book_ibfk_1` FOREIGN KEY (`Room_ID`) REFERENCES `room` (`Room_ID`),
  ADD CONSTRAINT `book_ibfk_2` FOREIGN KEY (`Reservation_ID`) REFERENCES `reservation` (`Reservation_ID`);

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_2` FOREIGN KEY (`Reservation_ID`) REFERENCES `reservation` (`Reservation_ID`),
  ADD CONSTRAINT `notification_ibfk_3` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`);

--
-- Constraints for table `reservation`
--
ALTER TABLE `reservation`
  ADD CONSTRAINT `reservation_ibfk_3` FOREIGN KEY (`Room_ID`) REFERENCES `room` (`Room_ID`),
  ADD CONSTRAINT `reservation_ibfk_4` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`);

--
-- Constraints for table `system_config`
--
ALTER TABLE `system_config`
  ADD CONSTRAINT `system_config_ibfk_1` FOREIGN KEY (`Updated_By`) REFERENCES `user` (`User_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

<?php
// student_mgmt_process.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once("db.php");

$requireAdmin = true;
require "auth_protect.php";
require_once "admin_log.php";

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { echo json_encode(['success'=>false,'message'=>'not_logged_in']); exit; }

// role check
$role = '';
if ($stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID = ? LIMIT 1")) {
    $stmt->bind_param("i",$user_id); $stmt->execute(); $rr = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $role = $rr['Role'] ?? '';
}
if (!in_array($role, ['admin','super admin'])) {
    echo json_encode(['success'=>false,'message'=>'forbidden']); exit;
}

// parse input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $input['action'] ?? null;

try {
    if ($action === 'list') {
        $q = trim($input['q'] ?? '');
        if ($q === '') {
            $stmt = $conn->prepare("SELECT User_ID, Name, Email, Role, Status, Cancellation_count FROM user WHERE Role = 'student' ORDER BY Name LIMIT 1000");
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $like = "%{$q}%";
            $stmt = $conn->prepare("SELECT User_ID, Name, Email, Role, Status, Cancellation_count FROM user WHERE Role = 'student' AND (Name LIKE ? OR Email LIKE ?) ORDER BY Name LIMIT 500");
            $stmt->bind_param("ss", $like, $like);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
       echo json_encode(['success'=>true,'students'=>$res]); 
exit;

    }

    if ($action === 'get') {
        $uid = intval($input['user_id'] ?? 0);
        if (!$uid) { echo json_encode(['success'=>false,'message'=>'missing_user']); exit; }

        // user record
        $stmt = $conn->prepare("SELECT User_ID, Name, Email, Role, Status, Cancellation_count FROM user WHERE User_ID = ? LIMIT 1");
        $stmt->bind_param("i",$uid); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$user) { echo json_encode(['success'=>false,'message'=>'user_not_found']); exit; }

        // bookings: today and future (join book + reservation)
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT b.Book_ID, b.Reservation_ID, b.Room_ID, b.Booking_Date, b.BStart_Time, b.BEnd_Time, r.R_Status
            FROM book b
            JOIN reservation r ON b.Reservation_ID = r.Reservation_ID
            WHERE r.User_ID = ? AND b.Booking_Date >= ?
            ORDER BY b.Booking_Date, b.BStart_Time
        ");
        $stmt->bind_param("is", $uid, $today);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success'=>true,'user'=>$user,'bookings'=>$bookings]);
        exit;
    }

    if ($action === 'update') {

    $uid = intval($input['user_id'] ?? 0);
    $newRole = $input['role'] ?? 'student';
    $newStatus = $input['status'] ?? 'active';

    if (!$uid) {
        echo json_encode(['success'=>false,'message'=>'missing_user_id']);
        exit;
    }

    // Prevent normal admin from assigning super admin
    if ($newRole === 'super admin' && $role !== 'super admin') {
        echo json_encode(['success'=>false,'message'=>'forbidden_role_change']);
        exit;
    }

    // Validate values (must match DB enums)
    if (!in_array($newRole, ['student', 'admin', 'super admin'], true)
        || !in_array($newStatus, ['active', 'blacklisted'], true)) {
        echo json_encode(['success'=>false,'message'=>'invalid_value']);
        exit;
    }

    // Read current status first, so we only act when it actually changes
    $cur = $conn->prepare("SELECT Status FROM user WHERE User_ID = ?");
    $cur->bind_param("i", $uid);
    $cur->execute();
    $curRow = $cur->get_result()->fetch_assoc();
    $cur->close();
    if (!$curRow) {
        echo json_encode(['success'=>false,'message'=>'user_not_found']);
        exit;
    }
    $oldStatus = $curRow['Status'];

    // Update User table
    // (เมื่อเปลี่ยน blacklisted -> active, trigger ใน DB จะรีเซ็ต Cancellation_Count เป็น 0 ให้เอง)
    $stmt = $conn->prepare("UPDATE user SET Role = ?, Status = ? WHERE User_ID = ?");
    $stmt->bind_param("ssi", $newRole, $newStatus, $uid);
    $stmt->execute();
    $stmt->close();

    // ---------------------------------------------
    // BLACKLIST LOGIC (only when status changed)
    // ---------------------------------------------
    if ($newStatus === "blacklisted" && $oldStatus !== "blacklisted") {

        // check only ACTIVE blacklist rows (old inactive rows are history)
        $chk = $conn->prepare("SELECT 1 FROM blacklist WHERE User_ID = ? AND Blacklist_Status = 'active'");
        $chk->bind_param("i", $uid);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();

        if (!$exists) {
            $ins = $conn->prepare("INSERT INTO blacklist (User_ID, Reason, Blacklist_Status, Date_Added)
                                   VALUES (?, 'Admin action', 'active', NOW())");
            $ins->bind_param("i", $uid);
            $ins->execute();
            $ins->close();
        }

        log_admin_action($conn, $_SESSION['user_id'], 'BLACKLIST_STUDENT', 'user', $uid, "Admin blacklisted student");

    } else if ($newStatus === "active" && $oldStatus === "blacklisted") {

        // Close active blacklist rows instead of deleting them (keep history)
        $upd = $conn->prepare("UPDATE blacklist
                               SET Blacklist_Status = 'inactive', Date_removed = NOW()
                               WHERE User_ID = ? AND Blacklist_Status = 'active'");
        $upd->bind_param("i", $uid);
        $upd->execute();
        $upd->close();

        log_admin_action($conn, $_SESSION['user_id'], 'BLACKLIST_STUDENT', 'user', $uid, "Admin restored student to active");

    }

    echo json_encode(['success' => true]);
    exit;
}


    echo json_encode(['success'=>false,'message'=>'unknown_action']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    exit;
}

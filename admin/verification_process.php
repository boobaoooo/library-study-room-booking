<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header("Content-Type: application/json; charset=utf-8");

require_once("db.php");

$requireAdmin = true;
require "auth_protect.php";

require_once "admin_log.php";


// --- Role Check ---
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { http_response_code(401); exit("Not logged in"); }

$stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!in_array($r['Role'], ['admin','super admin'])) {
    http_response_code(403); exit("Forbidden");
}
$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? null;

try {

    // =============================
    // FETCH USERS WITH Verify_Status = 'pending'
    // =============================
    if ($action === "fetch") {
        $q = "%".($input['q'] ?? "")."%";

        $stmt = $conn->prepare("
            SELECT User_ID, Name, Email, Role, Cancellation_Count, Verify_Status
            FROM user
            WHERE Role='student'
            AND Verify_Status = 'pending'
            AND (Name LIKE ? OR Email LIKE ?)
            ORDER BY User_ID DESC
        ");
        $stmt->bind_param("ss", $q, $q);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success'=>true, 'users'=>$users]);
        exit;
    }

    // =============================
    // VERIFY USER
    // =============================
    if ($action === "verify") {

        $uid = intval($input['user_id'] ?? 0);
        if (!$uid) throw new Exception("Missing user_id");

        // Update verification status
        $stmt = $conn->prepare("
            UPDATE user
            SET Verify_Status='verified', Status='active'
            WHERE User_ID=? AND Verify_Status='pending'
        ");
        $stmt->bind_param("i", $uid);
        $stmt->execute();

        if ($stmt->affected_rows === 0)
            throw new Exception("User not found or already verified");

        $stmt->close();

        echo json_encode(['success'=>true]);
        log_admin_action(
    $conn,
    $_SESSION['user_id'],
    'VERIFY_STUDENT',
    'user',
    $uid,
    "Student verification approved"
);
        exit;
    }

    // Unknown action
    throw new Exception("Unknown action");

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
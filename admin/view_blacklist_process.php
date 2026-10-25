<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }


require_once "db.php";

$requireAdmin = true;
require "auth_protect.php";

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { echo json_encode(["success"=>false,"message"=>"Not logged in"]); exit; }

$rq = $conn->prepare("SELECT Role FROM user WHERE User_ID = ?");
$rq->bind_param("i", $user_id);
$rq->execute();
$roleQuery = $rq->get_result()->fetch_assoc();
$rq->close();
if (!in_array($roleQuery['Role'], ['admin','super admin'])) {
    echo json_encode(["success"=>false,"message"=>"Forbidden"]); exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw,true);

$action = $_GET['action'] ?? ($data['action'] ?? null);

// FETCH blacklist by date
if ($action === "fetch") {
    $date = $data['date'];

    $sql = "
        SELECT 
            Blacklist_ID,
            User_ID,
            Reason,
            Blacklist_Status,
            Date_Added,
            Date_Removed
        FROM blacklist
        WHERE DATE(Date_Added) = ?
        ORDER BY Blacklist_ID DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s",$date);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(["success"=>true,"records"=>$res]);
    exit;
}

// UPDATE blacklist entry
// UPDATE blacklist entry
// UPDATE blacklist entry
// UPDATE blacklist entry
// UPDATE blacklist entry
if ($action === "update") {
    $id = intval($data['id']);
    $reason = $data['reason'];
    $newStatus = $data['status'];

    // Get current row + user ID
    $stmt = $conn->prepare("SELECT User_ID, Blacklist_Status FROM blacklist WHERE Blacklist_ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        echo json_encode(["success"=>false,"message"=>"Blacklist entry not found"]);
        exit;
    }

    $userId = $current['User_ID'];
    $oldStatus = $current['Blacklist_Status'];

   
    if ($oldStatus !== $newStatus) {

        // -------------------------------
        // NEW STATUS = active
        // → Call stored procedure (re-blacklist)
        // -------------------------------
        if ($newStatus === "active") {

    // Admin is reactivating an old blacklist entry
    // DO NOT CALL procedure → no new row should be created

    $stmt = $conn->prepare("
        UPDATE blacklist
        SET Reason=?, Blacklist_Status='active', Date_Added=NOW(), Date_Removed=NULL
        WHERE Blacklist_ID=?
    ");
    $stmt->bind_param("si", $reason, $id);
    $stmt->execute();
    $stmt->close();

    // Update user status
    $stmt = $conn->prepare("UPDATE user SET Status='blacklisted' WHERE User_ID=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success"=>true,"message"=>"User blacklisted (admin update)"]);
    exit;
}


        // -------------------------------
        // NEW STATUS = inactive
        // → Remove blacklist, restore user
        // -------------------------------
        if ($newStatus === "inactive") {
            $stmt = $conn->prepare("
                UPDATE blacklist
                SET Reason=?, Blacklist_Status='inactive', Date_Removed=NOW()
                WHERE Blacklist_ID=?
            ");
            $stmt->bind_param("si", $reason, $id);
            $stmt->execute();
            $stmt->close();

            // restore user status
            // (trigger ใน DB จะรีเซ็ต Cancellation_Count เป็น 0 ให้เอง)
            $stmt = $conn->prepare("UPDATE user SET Status='active' WHERE User_ID=?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            echo json_encode(["success"=>true, "message"=>"User restored"]);
            exit;
        }
    }

    // ===============================
    // CASE 2: ONLY reason updated
    // ===============================
    $stmt = $conn->prepare("UPDATE blacklist SET Reason=? WHERE Blacklist_ID=?");
    $stmt->bind_param("si", $reason, $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success"=>true, "message"=>"Reason updated"]);
    exit;
}




// DOWNLOAD as Excel
if ($action === "download") {
    $date = $_GET['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        die("Invalid date");
    }

    $dl = $conn->prepare("
        SELECT Blacklist_ID, User_ID, Reason, Blacklist_Status, Date_Added, Date_Removed
        FROM blacklist
        WHERE DATE(Date_Added) = ?
        ORDER BY Blacklist_ID DESC
    ");
    $dl->bind_param("s", $date);
    $dl->execute();
    $result = $dl->get_result();

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=blacklist_$date.xls");

    echo "Blacklist_ID\tUser_ID\tReason\tStatus\tDate_Added\tDate_Removed\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Blacklist_ID']}\t{$row['User_ID']}\t{$row['Reason']}\t{$row['Blacklist_Status']}\t{$row['Date_Added']}\t{$row['Date_Removed']}\n";
    }
    exit;
}

echo json_encode(["success"=>false,"message"=>"Invalid action"]);
exit;
?>
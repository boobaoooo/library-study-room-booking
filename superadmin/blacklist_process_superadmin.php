<?php
// superadmin/blacklist_process_superadmin.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
require_once "auth_superadmin.php"; // ensure superadmin

// Only accept POST from form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: blacklist.php");
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? null;

$reasons  = $_POST['reason'] ?? [];
$status   = $_POST['status'] ?? [];
$removed  = $_POST['date_removed'] ?? [];

foreach ($reasons as $blacklistId => $reasonVal) {

    $blacklistId = (int)$blacklistId;
    if ($blacklistId <= 0) continue;

    $reason = trim($reasonVal);
    $newStatus = trim($status[$blacklistId] ?? "");

    if ($newStatus !== "active" && $newStatus !== "inactive") {
        $newStatus = "inactive";
    }

    // fetch current row
    $stmt = $conn->prepare("SELECT User_ID, Blacklist_Status FROM blacklist WHERE Blacklist_ID=? LIMIT 1");
    $stmt->bind_param("i", $blacklistId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) continue;

    $userId = (int)$row['User_ID'];
    $oldStatus = $row['Blacklist_Status'];

    // CASE: change active -> inactive (remove blacklist)
    if ($oldStatus !== $newStatus && $newStatus === "inactive") {

        // if admin provided date, use it; else use today
        $date_removed_input = trim($removed[$blacklistId] ?? "");
        if (!empty($date_removed_input)) {
            // expected YYYY-MM-DD -> convert to DATETIME
            $date_removed = $date_removed_input . " 00:00:00";
        } else {
            $date_removed = date("Y-m-d H:i:s");
        }

        // update blacklist row: set status inactive + date_removed
        $u = $conn->prepare("UPDATE blacklist SET Reason=?, Blacklist_Status='inactive', Date_removed=? WHERE Blacklist_ID=?");
        $u->bind_param("ssi", $reason, $date_removed, $blacklistId);
        $u->execute();
        $u->close();

        // update user -> active
        $up = $conn->prepare("UPDATE user SET Status='active' WHERE User_ID=? LIMIT 1");
        $up->bind_param("i", $userId);
        $up->execute();
        $up->close();

        // log activity if function exists
        if (function_exists('logActivity')) {
            $desc = "Superadmin removed blacklist ID $blacklistId for user $userId (date_removed: $date_removed)";
            logActivity($conn, $current_user_id, "EDIT_BLACKLIST", $desc);
        }

        // continue to next
        continue;
    }

    // CASE: change inactive -> active (reactivate blacklist)
    if ($oldStatus !== $newStatus && $newStatus === "active") {

        // set Date_removed = NULL, Date_added = NOW()
        $u = $conn->prepare("UPDATE blacklist SET Reason=?, Blacklist_Status='active', Date_added=NOW(), Date_removed=NULL WHERE Blacklist_ID=?");
        $u->bind_param("si", $reason, $blacklistId);
        $u->execute();
        $u->close();

        // set user -> blacklisted
        $up = $conn->prepare("UPDATE user SET Status='blacklisted' WHERE User_ID=? LIMIT 1");
        $up->bind_param("i", $userId);
        $up->execute();
        $up->close();

        if (function_exists('logActivity')) {
            $desc = "Superadmin reactivated blacklist ID $blacklistId for user $userId";
            logActivity($conn, $current_user_id, "EDIT_BLACKLIST", $desc);
        }

        continue;
    }

    // CASE: no status change - only reason/date_changed submitted
    // handle manual date change if admin set a date and status currently inactive -> update date_removed accordingly
    if ($oldStatus === $newStatus) {
        // if admin provided date_removed and status is inactive, update it
        $date_removed_input = trim($removed[$blacklistId] ?? "");
        if (!empty($date_removed_input) && $newStatus === "inactive") {
            $date_removed = $date_removed_input . " 00:00:00";
            $u = $conn->prepare("UPDATE blacklist SET Reason=?, Date_removed=? WHERE Blacklist_ID=?");
            $u->bind_param("ssi", $reason, $date_removed, $blacklistId);
            $u->execute();
            $u->close();

            if (function_exists('logActivity')) {
                $desc = "Superadmin updated blacklist ID $blacklistId (date_removed changed to $date_removed)";
                logActivity($conn, $current_user_id, "EDIT_BLACKLIST", $desc);
            }

            continue;
        }

        // otherwise just update reason
        $u = $conn->prepare("UPDATE blacklist SET Reason=? WHERE Blacklist_ID=?");
        $u->bind_param("si", $reason, $blacklistId);
        $u->execute();
        $u->close();

        if (function_exists('logActivity')) {
            $desc = "Superadmin updated reason for blacklist ID $blacklistId";
            logActivity($conn, $current_user_id, "EDIT_BLACKLIST", $desc);
        }

        continue;
    }
}

// done — redirect back with message
header("Location: blacklist.php?msg=updated");
exit;

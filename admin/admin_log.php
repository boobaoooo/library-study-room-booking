<?php
function log_admin_action($conn, $adminId, $actionType, $affectedTable, $affectedId, $details = null)
{
    // Allowed enum values from database
    $allowed = [
        'ADD_ROOM',
        'EDIT_ROOM',
        'DELETE_ROOM',
        'CANCEL_RESERVATION',
        'VERIFY_STUDENT',
        'BLACKLIST_STUDENT'
    ];

    // Ignore anything not in ENUM
    if (!in_array($actionType, $allowed)) {
        return; // do nothing
    }

    $stmt = $conn->prepare("
        INSERT INTO admin_history (user_id, action_type, target_table, target_id, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issis", $adminId, $actionType, $affectedTable, $affectedId, $details);
    $stmt->execute();
    $stmt->close();
}
?>

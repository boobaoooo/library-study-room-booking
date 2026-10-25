<?php
function logActivity($conn, $user_id, $type, $desc) {
    $sql = "INSERT INTO admin_history (user_id, action_type, description) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $type, $desc);
    $stmt->execute();
    $stmt->close();
}

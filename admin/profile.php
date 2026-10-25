<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once "db.php";

// MUST be logged in
$requireAdmin = true;
require "auth_protect.php";

$userId = $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT User_ID, Name, Email, Role, Status FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "User not found.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="profile.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="content">
    <h1 class="page-title">Profile</h1>

    <div class="profile-card">

        <div class="profile-icon">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
    </svg>
</div>

        <div class="right-panel">
            <form id="profileForm">

                <input type="hidden" id="user_id" value="<?= $user['User_ID'] ?>">

                <label>Name:</label>
                <input type="text" id="name" value="<?= htmlspecialchars($user['Name']) ?>" readonly>

                <label>Email:</label>
                <input type="email" id="email" value="<?= htmlspecialchars($user['Email']) ?>" readonly>

                <label>Role:</label>
                <input type="text" id="role" value="<?= htmlspecialchars($user['Role']) ?>" readonly>

                <label>Status:</label>
                <input type="text" id="status" value="<?= htmlspecialchars($user['Status']) ?>" readonly>

                <div id="passwordSection" class="hidden">
                    <label>New Password:</label>
                    <input type="password" id="new_password" placeholder="Enter new password">

                    <label>Confirm New Password:</label>
                    <input type="password" id="confirm_password">
                </div>

                <div class="btn-group">
                    <button type="button" id="editNameBtn" class="btn primary">Edit Username</button>
                    <button type="button" id="changePasswordBtn" class="btn danger">Change Password</button>
                    <button type="button" id="confirmBtn" class="btn success hidden">Confirm</button>
                    <button type="button" id="cancelBtn" class="btn hidden">Cancel</button>
                </div>

                <div id="messageBox"></div>

            </form>
        </div>

    </div>
</main>

<script>
const nameField = document.getElementById("name");
const passwordSection = document.getElementById("passwordSection");
const newPasswordField = document.getElementById("new_password");
const confirmPasswordField = document.getElementById("confirm_password");

const editNameBtn = document.getElementById("editNameBtn");
const changePasswordBtn = document.getElementById("changePasswordBtn");
const confirmBtn = document.getElementById("confirmBtn");
const cancelBtn = document.getElementById("cancelBtn");
const messageBox = document.getElementById("messageBox");

let editMode = null; // 'name' or 'password'

// Edit Username
editNameBtn.onclick = () => {
    editMode = 'name';
    nameField.removeAttribute("readonly");
    nameField.focus();
    
    // Hide main buttons, show confirm/cancel
    editNameBtn.classList.add("hidden");
    changePasswordBtn.classList.add("hidden");
    confirmBtn.classList.remove("hidden");
    cancelBtn.classList.remove("hidden");
    
    messageBox.innerHTML = '';
};

// Change Password
changePasswordBtn.onclick = () => {
    editMode = 'password';
    passwordSection.classList.remove("hidden");
    newPasswordField.focus();
    
    // Hide main buttons, show confirm/cancel
    editNameBtn.classList.add("hidden");
    changePasswordBtn.classList.add("hidden");
    confirmBtn.classList.remove("hidden");
    cancelBtn.classList.remove("hidden");
    
    messageBox.innerHTML = '';
};

// Cancel
cancelBtn.onclick = () => {
    window.location.reload();
};

// Confirm changes
confirmBtn.onclick = async () => {
    if (editMode === 'name') {
        await updateUsername();
    } else if (editMode === 'password') {
        await updatePassword();
    }
};

// Update Username
async function updateUsername() {
    const newName = nameField.value.trim();
    
    if (!newName) {
        messageBox.innerHTML = `<div class='error'>Name cannot be empty</div>`;
        return;
    }
    
    const payload = {
        action: "update_name",
        user_id: document.getElementById("user_id").value,
        name: newName
    };
    
    try {
        const res = await fetch("profile_process.php", {
            method: "POST",
            headers: {"Content-Type":"application/json"},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (!data.success) {
            messageBox.innerHTML = `<div class='error'>${data.message}</div>`;
        } else {
            messageBox.innerHTML = `<div class='success'>Username updated successfully!</div>`;
            setTimeout(() => location.reload(), 1500);
        }
    } catch (e) {
        messageBox.innerHTML = `<div class='error'>Request failed: ${e.message}</div>`;
    }
}

// Update Password
async function updatePassword() {
    const newPassword = newPasswordField.value;
    const confirmPassword = confirmPasswordField.value;
    
    if (!newPassword || !confirmPassword) {
        messageBox.innerHTML = `<div class='error'>Please fill in both password fields</div>`;
        return;
    }
    
    if (newPassword !== confirmPassword) {
        messageBox.innerHTML = `<div class='error'>Passwords do not match</div>`;
        return;
    }
    
    if (newPassword.length < 6) {
        messageBox.innerHTML = `<div class='error'>Password must be at least 6 characters</div>`;
        return;
    }
    
    const payload = {
        action: "update_password",
        user_id: document.getElementById("user_id").value,
        password: newPassword
    };
    
    try {
        const res = await fetch("profile_process.php", {
            method: "POST",
            headers: {"Content-Type":"application/json"},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (!data.success) {
            messageBox.innerHTML = `<div class='error'>${data.message}</div>`;
        } else {
            messageBox.innerHTML = `<div class='success'>Password updated successfully!</div>`;
            setTimeout(() => location.reload(), 1500);
        }
    } catch (e) {
        messageBox.innerHTML = `<div class='error'>Request failed: ${e.message}</div>`;
    }
}
</script>

</body>
</html>

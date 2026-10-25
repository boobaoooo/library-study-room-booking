<?php
// Debug mode (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once "db.php";
require_once "auth_superadmin.php";   // super admin only




// must login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: /librarystudyroombooking/login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];

$msg = '';
$err = '';

/* ---------------------------------------------------
   1. Define default system config entries
------------------------------------------------------ */
$default_items = [
    "system_status"             => ["active", "Whether system is online"],
    "maintenance_mode"          => ["off", "Enable maintenance mode"],
    "data_backup"               => ["off", "Automatic backup enabled"],
    "max_booking_hours"         => ["4", "Maximum hours per booking"],
    "max_reservations_per_day"  => ["2", "Daily booking limit"],
    "system_version"            => ["v1.0", "Version number"]
];

/* ---------------------------------------------------
   2. Insert missing default config
------------------------------------------------------ */
foreach ($default_items as $name => $arr) {
    [$val, $desc] = $arr;

    // check if exists
    $check = $conn->prepare("SELECT Config_ID FROM system_config WHERE Config_Name = ?");
    $check->bind_param("s", $name);
    $check->execute();
    $check->store_result();

    if ($check->num_rows == 0) {
        // insert new config
        $insert = $conn->prepare("
            INSERT INTO system_config (Config_Name, Config_Value, Description, Updated_By)
            VALUES (?, ?, ?, ?)
        ");
        $insert->bind_param("sssi", $name, $val, $desc, $current_user_id);
        $insert->execute();
        $insert->close();
    }
    $check->close();
}

/* ---------------------------------------------------
   3. SAVE CONFIG (NO MORE STORED PROCEDURE)
------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='save_config') {

    if ($current_role !== 'super admin') {
        $err = "Only super admin can update system settings.";
    } else {

        $selected = $_POST['sel'] ?? [];
        $values   = $_POST['value'] ?? [];
        $descs    = $_POST['desc'] ?? [];

        if (!empty($selected)) {

            $update = $conn->prepare("
                UPDATE system_config 
                SET Config_Value = ?, Description = ?, Updated_By = ? 
                WHERE Config_ID = ?
            ");

            foreach ($selected as $cfg_id) {

                $cfg_id = (int)$cfg_id;

                $val  = $values[$cfg_id] ?? "";
                $desc = $descs[$cfg_id] ?? "";

                $update->bind_param("ssii", $val, $desc, $current_user_id, $cfg_id);
                $update->execute();
            }

            $update->close();
            logActivity($conn, $_SESSION['user_id'], "SYSTEM_UPDATE", "Updated system configuration");

            $msg = "System settings updated.";

        } else {
            $err = "No settings selected.";
        }
    }
}

/* ---------------------------------------------------
   4. Fetch updated settings
------------------------------------------------------ */
$rows = [];
$q = $conn->query("SELECT * FROM system_config ORDER BY Config_ID ASC");
while ($r = $q->fetch_assoc()) {
    $rows[] = $r;
}
$q->free();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>System Management</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="c5.css">
</head>

<body>

<?php include 'header.php'; ?>

<main class="container">

    <h1 class="login-heading">SYSTEM MANAGEMENT</h1>

    <div class="page-box">

        <?php if ($msg): ?>
            <div class="alert" style="background:#e6ffed;"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="alert"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!-- FORM -->
        <form id="systemForm" method="POST">
            <input type="hidden" name="action" value="save_config">

            <table class="data-table">
                <thead>
                    <tr>
                        <th id="p02"><input type="checkbox" onclick="toggleAll(this)"></th>
                        <th>Setting</th>
                        <th>Value</th>
                        <th>Description</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($rows as $cfg): ?>
                    <tr>
                        <td id="p01">
                            <input type="checkbox" name="sel[]" value="<?= $cfg['Config_ID'] ?>">
                        </td>

                        <td><?= htmlspecialchars($cfg['Config_Name']) ?></td>

                        <td>
                            <input type="text"
                                name="value[<?= $cfg['Config_ID'] ?>]"
                                value="<?= htmlspecialchars($cfg['Config_Value']) ?>"
                                style="width:90%; padding:5px; border-radius:6px;">
                        </td>

                        <td>
                            <textarea name="desc[<?= $cfg['Config_ID'] ?>]"
                                      rows="2"
                                      style="width:90%; border-radius:6px;">
                                <?= htmlspecialchars($cfg['Description']) ?>
                            </textarea>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

            </table>

        </form>

    </div>

    <!-- Buttons -->
    <div class="controls">
        <button class="btn primary" onclick="document.getElementById('systemForm').submit()">SAVE</button>
        <a href="staff.php" class="btn danger">CANCEL</a>
        <!--<a href="settings.php" class="btn edit">SETTINGS</a>-->
    </div>

</main>

<script>
function toggleAll(src) {
    document.querySelectorAll("input[name='sel[]']").forEach(x => x.checked = src.checked);
}
</script>

</body>
</html>


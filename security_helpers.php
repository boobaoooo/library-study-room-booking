<?php
// security_helpers.php
// ฟังก์ชันกลางเรื่องรหัสผ่านและการอัปโหลดรูป ใช้ร่วมกันทุก role
// ใช้งาน: require_once __DIR__ . '/security_helpers.php';          (จาก root)
//        require_once __DIR__ . '/../security_helpers.php';       (จาก admin/ student/ superadmin/)

/**
 * สร้าง hash รหัสผ่านแบบปลอดภัย (bcrypt / argon ตาม PHP default)
 */
function make_password_hash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * ตรวจรหัสผ่าน รองรับทั้ง hash แบบใหม่ (password_hash)
 * และ hash แบบเก่าที่เคยใช้ในโปรเจค (SHA-256 / MD5) เพื่อให้บัญชีเดิมยัง login ได้
 *
 * @param bool $needsRehash จะเป็น true ถ้าควรอัปเกรด hash เป็นแบบใหม่
 */
function check_password(string $password, string $storedHash, ?bool &$needsRehash = null): bool
{
    $needsRehash = false;

    // hash แบบใหม่
    if (password_get_info($storedHash)['algo'] !== null && password_get_info($storedHash)['algo'] !== 0) {
        $ok = password_verify($password, $storedHash);
        $needsRehash = $ok && password_needs_rehash($storedHash, PASSWORD_DEFAULT);
        return $ok;
    }

    // hash แบบเก่า (legacy) — ตรวจแล้วให้อัปเกรด
    $legacy = null;
    if (preg_match('/^[a-f0-9]{64}$/i', $storedHash)) {
        $legacy = hash('sha256', $password);
    } elseif (preg_match('/^[a-f0-9]{32}$/i', $storedHash)) {
        $legacy = md5($password);
    }

    if ($legacy !== null && hash_equals(strtolower($storedHash), $legacy)) {
        $needsRehash = true;
        return true;
    }

    return false;
}

/**
 * บันทึกรูปที่อัปโหลดอย่างปลอดภัย
 * - ตรวจว่าเป็นรูป JPG/PNG จริงจากเนื้อไฟล์ (ไม่เชื่อ MIME ที่ browser ส่งมา)
 * - ตั้งนามสกุลเองจากชนิดไฟล์จริง (กันการอัป .php)
 * - จำกัดขนาด 2MB
 *
 * @return array ['ok' => bool, 'filename' => string|null, 'error' => string|null]
 */
function save_uploaded_image(array $file, string $prefix, string $uploadDir): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'filename' => null, 'error' => 'Upload error.'];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'filename' => null, 'error' => 'File too large (max 2MB).'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'filename' => null, 'error' => 'Invalid upload.'];
    }

    // ตรวจชนิดไฟล์จากเนื้อไฟล์จริง
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if ($info === false || !isset($allowed[$info[2]])) {
        return ['ok' => false, 'filename' => null, 'error' => 'Only JPG and PNG images are allowed.'];
    }

    $ext = $allowed[$info[2]];
    $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix);
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], rtrim($uploadDir, '/') . '/' . $filename)) {
        return ['ok' => false, 'filename' => null, 'error' => 'Cannot save file.'];
    }

    return ['ok' => true, 'filename' => $filename, 'error' => null];
}

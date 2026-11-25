<?php
/**
 * =========================================================================
 * การเชื่อมต่อฐานข้อมูล (Database Connection)
 * IT System Access Request Form
 * =========================================================================
 * 
 * 💡 คำอธิบายสำหรับผู้เริ่มต้น (ไม่ต้องกังวลเรื่อง Cross Database!):
 * - คุณ "ไม่จำเป็น" ต้องเขียนโค้ดเชื่อม 2 ฐานข้อมูลใน PHP ให้ยุ่งยาก
 * - ใน PHP เราเชื่อมต่อฐานข้อมูลตัวนี้ตัวเดียวตามปกติ (`DB_NAME`)
 * - หากฐานข้อมูลสมาชิก (dashboard_member) อยู่คนละก้อนบนเซิร์ฟเวอร์เดียวกัน
 *   เพียงนำเข้าไฟล์ `database_requests_link.sql` ใน phpMyAdmin ครั้งเดียว
 *   ระบบจะจำลองตารางสมาชิก (ex_*) มาไว้ในฐานข้อมูลนี้ให้ทันที โค้ดทั้งหมดจะทำงานได้อัตโนมัติ!
 */

// 1. กำหนด Timezone ประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// 2. ระบบความปลอดภัย Session และ HTTPS (รองรับทั้ง XAMPP และ Plesk SSL)
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if ($isSecure) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// 3. โหลดการตั้งค่าฐานข้อมูล
// - เครื่อง Local (XAMPP): ใช้งานได้ทันที (ใช้ localhost / root / ไม่มีรหัสผ่าน)
// - โฮสติ้งจริง (Plesk): ให้สร้างไฟล์ `config/db_config.php` แล้วใส่ User/Pass ของโฮสต์
$customConfigFile = __DIR__ . '/db_config.php';
$config = file_exists($customConfigFile) ? (include $customConfigFile) : [];
if (!is_array($config)) {
    $config = [];
}

define('DB_HOST', $config['host'] ?? 'localhost');
define('DB_PORT', $config['port'] ?? '3306');
define('DB_NAME', $config['name'] ?? 'db_system_access');
define('DB_USER', $config['user'] ?? 'root');
define('DB_PASS', $config['pass'] ?? '');
define('DB_CHARSET', $config['charset'] ?? 'utf8mb4');

// 4. ฟังก์ชันเชื่อมต่อฐานข้อมูล (PDO)
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . ", time_zone = '+07:00'"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // แจ้งเตือนข้อผิดพลาดแบบเข้าใจง่าย
            die("<div style='font-family:sans-serif;max-width:600px;margin:50px auto;padding:24px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;color:#991b1b;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);'>"
                . "<h3 style='margin:0 0 10px;font-size:18px;'>⚠️ ไม่สามารถเชื่อมต่อฐานข้อมูลได้ (Database Error)</h3>"
                . "<p style='margin:0 0 12px;font-size:14px;color:#4b5563;line-height:1.6;'>กรุณาตรวจสอบการตั้งค่าในไฟล์ <code>config/db_config.php</code> ว่าชื่อฐานข้อมูล, ชื่อผู้ใช้ (User) หรือรหัสผ่านถูกต้องหรือไม่</p>"
                . "<div style='font-size:12px;background:#fff;padding:10px;border-radius:6px;border:1px solid #fee2e2;color:#b91c1c;word-break:break-all;'>"
                . "<strong>ข้อความระบบ:</strong> " . htmlspecialchars($e->getMessage())
                . "</div></div>");
        }
    }
    return $pdo;
}

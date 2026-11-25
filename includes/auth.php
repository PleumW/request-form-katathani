<?php

/**
 * Authentication and Level Management (Based on users table & ex_level)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// ---- Security Helpers ----

/**
 * Generate a CSRF token and store in session
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify submitted CSRF token matches session token
 */
function verifyCsrfToken($token = null)
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $submittedToken = $token ?? ($_POST['csrf_token'] ?? '');
    if (empty($sessionToken) || empty($submittedToken)) return false;
    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Abort with 403 if CSRF token invalid
 */
function requireCsrf()
{
    if (!verifyCsrfToken()) {
        http_response_code(403);
        setFlash('error', 'คำขอไม่ถูกต้อง (CSRF token invalid) กรุณาลองใหม่อีกครั้ง');
        redirect('index.php');
    }
}

/**
 * Require admin (level 99) or redirect
 */
function requireAdmin()
{
    requireLogin();
    $user = getCurrentUser();
    if (!$user || (int)($user['level_id'] ?? 0) !== 99) {
        setFlash('error', 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่เข้าถึงหน้านี้ได้');
        redirect('index.php');
    }
}

/**
 * Determine operational role dynamically from level_id, department, and position
 * 
 * Rules:
 * - level_id == 99 -> 'admin'
 * - level_id >= 40 (Manager Level: Manager 40, Director 45, RM 50, HM 60, COO/CEO 70):
 *     - แยกว่าใครเป็น manager ส่วนไหนผ่านแผนกของ user (และระดับตำแหน่งบริหาร)
 *     - แผนก IT (dep_id = 1 หรือมีคำว่า IT) -> 'it_manager' (ผู้อนุมัติขั้นที่ 3)
 *     - แผนก EX (dep_id = 6 / Executive) หรือระดับผู้บริหารโรงแรม RM 50, HM 60, CEO 70 -> 'hotel_manager' (ผู้อนุมัติขั้นที่ 2)
 *     - แผนกอื่นๆ ทั้งหมด (เช่น AC, FB, EN, SPA, KC, HR, ฯลฯ) -> 'dept_head' (ผู้อนุมัติขั้นที่ 1 ประจำแผนกของตนเอง)
 * - Demo test accounts shortcut: 'depthead', 'hotelmgr', 'itmgr'
 * - level_id == 20:
 *     - If IT -> 'it_staff'
 *     - If HR -> 'hr_staff'
 *     - Otherwise -> 'coordinator'
 * - level_id <= 3 (default 2) -> 'requester' (general user)
 */
function determineRoleFromUser($user)
{
    if (!$user) return 'requester';
    $lvl = (int)($user['level_id'] ?? ($user['user_level'] ?? 2));
    $uname = $user['username'] ?? ($user['user_username'] ?? '');
    $depId = (int)($user['dep_id'] ?? ($user['department_id'] ?? 0));
    $dept = $user['department'] ?? ($user['dep_name'] ?? ($user['dep_name_s'] ?? ''));
    $pos = $user['position'] ?? '';

    $isIT = (
        $depId === 1 ||
        preg_match('/\bIT\b/i', $dept) ||
        preg_match('/\bIT\b/i', $pos) ||
        stripos($dept, 'Information') !== false ||
        stripos($dept, 'Technology') !== false
    );

    $isHotel = (
        $depId === 6 ||
        in_array($lvl, [50, 60, 70]) ||
        preg_match('/\bEX\b/i', $dept) ||
        stripos($dept, 'Executive') !== false ||
        stripos($pos, 'Hotel Manager') !== false ||
        stripos($pos, 'General Manager') !== false ||
        stripos($pos, 'Resort Manager') !== false
    );

    // 1. Administrator
    if ($lvl === 99) return 'admin';

    // 2. Demo Account shortcuts (for quick testing)
    if ($uname === 'depthead') return 'dept_head';
    if ($uname === 'hotelmgr') return 'hotel_manager';
    if ($uname === 'itmgr') return 'it_manager';

    // 3. Manager Level (level_id >= 40)
    // แยกบทบาทผู้อนุมัติผ่านแผนกของ user และระดับตำแหน่งบริหาร
    if ($lvl >= 40) {
        // (a) ฝ่าย IT -> IT Manager (Stage 3)
        if ($isIT) {
            return 'it_manager';
        }

        // (b) ฝ่ายบริหาร / ผู้จัดการโรงแรม / GM / RM / HM / CEO / COO -> Hotel Manager (Stage 2)
        if ($isHotel) {
            return 'hotel_manager';
        }

        // (c) แผนกอื่นๆ ทั้งหมด (เช่น AC, FB, EN, SPA, KC, HR, ฯลฯ) -> Department Head (Stage 1)
        return 'dept_head';
    }

    // 4. Operational Staff (Level 20 - Coordinator)
    if ($lvl === 20) {
        if ($isIT || $uname === 'it') {
            return 'it_staff';
        }
        $isHR = (
            $depId === 2 ||
            preg_match('/\bHR\b/i', $dept) ||
            preg_match('/\bHR\b/i', $pos) ||
            stripos($dept, 'Human Resource') !== false ||
            stripos($pos, 'Personnel') !== false ||
            $uname === 'hr'
        );
        if ($isHR) {
            return 'hr_staff';
        }
        return 'coordinator';
    }

    // 5. General Staff / Requester (Level 1, 2, 3, 30)
    return 'requester';
}

function getCurrentUser()
{
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'user_code' => $_SESSION['user_code'] ?? '',
            'login_token' => $_SESSION['login_token'] ?? '',
            'employee_id' => $_SESSION['employee_id'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? 'requester',
            'level_id' => $_SESSION['level_id'] ?? 1,
            'level_name' => $_SESSION['level_name'] ?? 'User',
            'hotel_id' => $_SESSION['hotel_id'] ?? 1,
            'hotel_name' => $_SESSION['hotel_name'] ?? 'Katathani',
            'property_name' => $_SESSION['property_name'] ?? 'PHUKET',
            'department' => $_SESSION['department'] ?? '',
            'position' => $_SESSION['position'] ?? '',
            'property' => $_SESSION['property'] ?? 'Katathani'
        ];
    }
    return null;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        setFlash('warning', 'กรุณาเข้าสู่ระบบก่อนดำเนินการ');
        redirect('login.php');
    }
}

function requireRole($roles = [])
{
    requireLogin();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    $user = getCurrentUser();
    if (!in_array($user['role'], $roles) && $user['level_id'] !== 99) {
        setFlash('error', 'คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้');
        redirect('index.php');
    }
}

/**
 * Log in user by user id from ex_user table
 * Updates ex_login table with login_token and login_update
 */
function loginUserById($userId)
{
    $db = getDB();
    $sql = "SELECT u.*, 
                   t.title_name,
                   d.dep_name, d.dep_name_s,
                   l.level_name, 
                   h.hotel_name, h.hotel_name_s, 
                   p.property_name,
                   lg.login_id, lg.user_username, lg.user_password, lg.login_token AS existing_token
            FROM ex_user u
            LEFT JOIN ex_title t ON u.user_title_id = t.title_id
            LEFT JOIN ex_department d ON u.dep_id = d.dep_id
            LEFT JOIN ex_level l ON u.user_level = l.level_id
            LEFT JOIN ex_hotel h ON u.user_hotel = h.hotel_id
            LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id
            LEFT JOIN ex_login lg ON u.user_code = lg.user_code
            WHERE u.user_id = ?
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([(int)$userId]);
    $user = $stmt->fetch();
    if ($user) {
        // Prevent session fixation
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        // Generate and record login_token
        $loginToken = bin2hex(random_bytes(32));
        $userCode = $user['user_code'] ?? ($user['hr_employee_id'] ?? ('U-' . $user['user_id']));
        $username = $user['user_username'] ?? $userCode;

        try {
            $stmtLogin = $db->prepare("UPDATE ex_login SET login_token = ?, login_update = NOW() WHERE user_code = ? OR user_username = ?");
            $stmtLogin->execute([$loginToken, $userCode, $username]);
        } catch (Exception $e) {
        }

        $titleName = $user['title_name'] ?? '';
        $firstName = $user['user_name'] ?? '';
        $lastName = $user['user_surname'] ?? '';
        $fullName = trim("{$titleName} {$firstName} {$lastName}");
        if (empty($fullName)) {
            $fullName = trim("{$firstName} {$lastName}") ?: $username;
        }

        $userProfile = [
            'id' => (int)$user['user_id'],
            'user_id' => (int)$user['user_id'],
            'user_code' => $userCode,
            'username' => $username,
            'employee_id' => !empty($user['hr_employee_id']) ? $user['hr_employee_id'] : ($user['user_employee_id'] ?? $userCode),
            'full_name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'nickname' => $user['user_nickname'] ?? '',
            'title' => $titleName,
            'gender' => ($user['user_gender'] == 2) ? 'หญิง' : 'ชาย',
            'email' => $user['user_email'] ?? '',
            'level_id' => (int)$user['user_level'],
            'level_name' => $user['level_name'] ?? 'User',
            'hotel_id' => (int)$user['user_hotel'],
            'hotel_name' => $user['hotel_name'] ?? 'Katathani',
            'property_name' => $user['property_name'] ?? 'PHUKET',
            'department' => $user['dep_name'] ?? '',
            'dep_id' => (int)$user['dep_id'],
            'position' => $user['level_name'] ?? '',
            'property' => $user['hotel_name'] ?? 'Katathani'
        ];

        $computedRole = determineRoleFromUser($userProfile);

        $_SESSION['user_id'] = $userProfile['id'];
        $_SESSION['login_id'] = $user['login_id'] ?? $userProfile['id'];
        $_SESSION['user_code'] = $userCode;
        $_SESSION['login_token'] = $loginToken;
        $_SESSION['username'] = $username;
        $_SESSION['employee_id'] = $userProfile['employee_id'];
        $_SESSION['full_name'] = $fullName;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['nickname'] = $userProfile['nickname'];
        $_SESSION['email'] = $userProfile['email'];
        $_SESSION['role'] = $computedRole;
        $_SESSION['level_id'] = $userProfile['level_id'];
        $_SESSION['level_name'] = $userProfile['level_name'];
        $_SESSION['hotel_id'] = $userProfile['hotel_id'];
        $_SESSION['hotel_name'] = $userProfile['hotel_name'];
        $_SESSION['property_name'] = $userProfile['property_name'];
        $_SESSION['department'] = $userProfile['department'];
        $_SESSION['dep_id'] = $userProfile['dep_id'];
        $_SESSION['position'] = $userProfile['position'];
        $_SESSION['property'] = $userProfile['property'];

        // บันทึก Log การเข้าสู่ระบบสำเร็จ
        recordLoginLog(
            $_SESSION['user_id'],
            $_SESSION['user_code'],
            $_SESSION['username'],
            $_SESSION['full_name'],
            $_SESSION['role'],
            $_SESSION['department'],
            'success',
            null
        );

        return true;
    }
    return false;
}

/**
 * Log in user by user_username or user_code with password hash verification from ex_login table
 * Updates login_token and login_update in the ex_login table
 */
function loginUserByUsername($username, $password = '')
{
    $db = getDB();

    // 1. Authenticate against ex_login table using user_username or user_code
    $stmt = $db->prepare("SELECT * FROM ex_login WHERE user_username = ? OR user_code = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $loginAccount = $stmt->fetch();

    // Fallback: check ex_user by employee_id or user_code if not directly matched by username
    if (!$loginAccount) {
        $fallbackStmt = $db->prepare("SELECT * FROM ex_user WHERE hr_employee_id = ? OR user_employee_id = ? OR user_code = ? LIMIT 1");
        $fallbackStmt->execute([$username, $username, $username]);
        $uFallback = $fallbackStmt->fetch();
        if ($uFallback) {
            $stmtExLog = $db->prepare("SELECT * FROM ex_login WHERE user_code = ? LIMIT 1");
            $stmtExLog->execute([$uFallback['user_code']]);
            $loginAccount = $stmtExLog->fetch();
        }
    }

    if ($loginAccount && (password_verify($password, $loginAccount['user_password']) || $password === $loginAccount['user_password'])) {
        // Generate new secure login_token
        $loginToken = bin2hex(random_bytes(32));

        // If password was plaintext, re-hash it securely
        if ($password === $loginAccount['user_password'] && !password_verify($password, $loginAccount['user_password'])) {
            $loginAccount['user_password'] = password_hash($password, PASSWORD_BCRYPT);
            try {
                $db->prepare("UPDATE ex_login SET user_password = ? WHERE login_id = ?")->execute([$loginAccount['user_password'], $loginAccount['login_id']]);
            } catch (Exception $e) {
            }
        }

        // Update login_token and login_update in ex_login table
        try {
            $updateStmt = $db->prepare("UPDATE ex_login SET login_token = ?, login_update = NOW() WHERE login_id = ?");
            $updateStmt->execute([$loginToken, $loginAccount['login_id']]);
        } catch (Exception $e) {
        }

        // Prevent session fixation
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        // Fetch full user profile from ex_user joined with master tables
        $exSql = "SELECT ex.*, 
                         t.title_name,
                         d.dep_name, d.dep_name_s, 
                         l.level_name, 
                         h.hotel_name, h.hotel_name_s, 
                         p.property_name
                  FROM ex_user ex
                  LEFT JOIN ex_title t ON ex.user_title_id = t.title_id
                  LEFT JOIN ex_department d ON ex.dep_id = d.dep_id
                  LEFT JOIN ex_level l ON ex.user_level = l.level_id
                  LEFT JOIN ex_hotel h ON ex.user_hotel = h.hotel_id
                  LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id
                  WHERE ex.user_code = ? OR ex.hr_employee_id = ? OR ex.user_employee_id = ?
                  LIMIT 1";
        $exStmt = $db->prepare($exSql);
        $exStmt->execute([$loginAccount['user_code'], $loginAccount['user_code'], $loginAccount['user_code']]);
        $ex = $exStmt->fetch();

        $empId = !empty($ex['hr_employee_id']) ? $ex['hr_employee_id'] : ($ex['user_employee_id'] ?? $loginAccount['user_code']);
        $titleName = $ex['title_name'] ?? '';
        $firstName = $ex['user_name'] ?? '';
        $lastName = $ex['user_surname'] ?? '';
        $fullName = trim("{$titleName} {$firstName} {$lastName}");
        if (empty($fullName)) {
            $fullName = trim("{$firstName} {$lastName}") ?: $loginAccount['user_username'];
        }

        $userProfile = [
            'id' => (int)($ex['user_id'] ?? $loginAccount['login_id']),
            'user_id' => (int)($ex['user_id'] ?? $loginAccount['login_id']),
            'username' => $loginAccount['user_username'],
            'user_code' => $loginAccount['user_code'],
            'employee_id' => $empId,
            'full_name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'nickname' => $ex['user_nickname'] ?? '',
            'title' => $titleName,
            'gender' => (($ex['user_gender'] ?? 1) == 2) ? 'หญิง' : 'ชาย',
            'email' => $ex['user_email'] ?? '',
            'level_id' => (int)($ex['user_level'] ?? 2),
            'level_name' => $ex['level_name'] ?? 'User',
            'hotel_id' => (int)($ex['user_hotel'] ?? 1),
            'hotel_name' => $ex['hotel_name'] ?? 'Katathani',
            'property_name' => $ex['property_name'] ?? 'PHUKET',
            'department' => $ex['dep_name'] ?? '',
            'dep_id' => (int)($ex['dep_id'] ?? 1),
            'position' => $ex['level_name'] ?? '',
            'property' => $ex['hotel_name'] ?? 'Katathani'
        ];

        $computedRole = determineRoleFromUser($userProfile);

        $_SESSION['user_id'] = $userProfile['id'];
        $_SESSION['login_id'] = $loginAccount['login_id'];
        $_SESSION['user_code'] = $loginAccount['user_code'];
        $_SESSION['login_token'] = $loginToken;
        $_SESSION['username'] = $loginAccount['user_username'];
        $_SESSION['employee_id'] = $userProfile['employee_id'];
        $_SESSION['full_name'] = $userProfile['full_name'];
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['nickname'] = $userProfile['nickname'];
        $_SESSION['email'] = $userProfile['email'];
        $_SESSION['role'] = $computedRole;
        $_SESSION['level_id'] = $userProfile['level_id'];
        $_SESSION['level_name'] = $userProfile['level_name'];
        $_SESSION['hotel_id'] = $userProfile['hotel_id'];
        $_SESSION['hotel_name'] = $userProfile['hotel_name'];
        $_SESSION['property_name'] = $userProfile['property_name'];
        $_SESSION['department'] = $userProfile['department'];
        $_SESSION['dep_id'] = $userProfile['dep_id'];
        $_SESSION['position'] = $userProfile['position'];
        $_SESSION['property'] = $userProfile['property'];

        // บันทึก Log การเข้าสู่ระบบสำเร็จ
        recordLoginLog(
            $_SESSION['user_id'],
            $_SESSION['user_code'],
            $_SESSION['username'],
            $_SESSION['full_name'],
            $_SESSION['role'],
            $_SESSION['department'],
            'success',
            null
        );

        return true;
    }

    // บันทึก Log การเข้าสู่ระบบล้มเหลว
    recordLoginLog(
        null,
        null,
        $username,
        null,
        null,
        null,
        'failed',
        'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
    );

    return false;
}

/**
 * Record a login attempt (successful or failed) into logs/login.log file
 */
function recordLoginLog($userId, $userCode, $username, $fullName, $role, $department, $status = 'success', $failureReason = null)
{
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $logsDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0777, true);
        }
        $logFile = $logsDir . '/login.log';

        $entry = [
            'id' => time() . rand(100, 999),
            'user_id' => $userId,
            'user_code' => $userCode,
            'username' => $username,
            'full_name' => $fullName,
            'role' => $role,
            'department' => $department,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status' => $status,
            'failure_reason' => $failureReason,
            'login_at' => date('Y-m-d H:i:s')
        ];

        @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        error_log("Failed to record login log: " . $e->getMessage());
    }
}

function logoutUser()
{
    $db = getDB();
    if (!empty($_SESSION['username']) || !empty($_SESSION['user_code'])) {
        $uname = $_SESSION['username'] ?? '';
        $ucode = $_SESSION['user_code'] ?? '';
        try {
            $stmt = $db->prepare("UPDATE ex_login SET login_token = NULL, login_update = NOW() WHERE user_username = ? OR user_code = ?");
            $stmt->execute([$uname, $ucode]);
        } catch (Exception $e) {
        }
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Get all users from ex_user table
 */
function getAllUsers()
{
    $db = getDB();
    return $db->query("SELECT u.user_id AS id, u.user_id, u.user_code, 
                              u.hr_employee_id AS employee_id, u.hr_employee_id, u.user_employee_id,
                              u.user_name AS first_name, u.user_surname AS last_name, u.user_nickname AS nickname,
                              TRIM(CONCAT(COALESCE(t.title_name, ''), ' ', u.user_name, ' ', u.user_surname)) AS full_name,
                              u.user_email AS email, u.user_level AS level_id, u.user_hotel AS hotel_id,
                              u.dep_id, d.dep_name AS department, d.dep_name_s,
                              l.level_name, h.hotel_name, h.hotel_name_s, p.property_name,
                              t.title_name,
                              lg.user_username AS username, lg.login_update, lg.login_token
                       FROM ex_user u
                       LEFT JOIN ex_title t ON u.user_title_id = t.title_id
                       LEFT JOIN ex_department d ON u.dep_id = d.dep_id
                       LEFT JOIN ex_level l ON u.user_level = l.level_id
                       LEFT JOIN ex_hotel h ON u.user_hotel = h.hotel_id
                       LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id
                       LEFT JOIN ex_login lg ON u.user_code = lg.user_code
                       WHERE u.user_status = 1
                       ORDER BY u.user_level DESC, u.user_id ASC")->fetchAll();
}

/**
 * Check if the user is authorized to approve based on level_id and role
 * - Department Heads must have level_id >= 40 (specifically level 40)
 * - Differentiates head (Dept Head), hotel (Hotel Manager), and it (IT Manager)
 */
function canApprove($user, $request)
{
    if (!$user || !$request) return false;
    $status = $request['status'];
    $lvl = (int)($user['level_id'] ?? 2);
    $role = determineRoleFromUser($user);

    // Admin does NOT sign documents (ผู้ดูแลระบบไม่ต้องเซ็นเอกสาร)
    if ($lvl === 99 || $role === 'admin') return false;

    // Must have level_id >= 40 to approve
    if ($lvl < 40) return false;

    // 1. Dept Head (head) approves pending_dept_head
    if ($status === 'pending_dept_head' && $role === 'dept_head') return true;

    // 2. Hotel Manager (hotel) approves pending_hotel_manager
    if ($status === 'pending_hotel_manager' && $role === 'hotel_manager') return true;

    // 3. IT Manager (it) approves pending_it_manager
    if ($status === 'pending_it_manager' && $role === 'it_manager') return true;

    return false;
}

/**
 * Check if the user can fulfill IT Action (Effective & Expiry Date)
 */
function canITAction($user, $request)
{
    if (!$user || !$request) return false;
    if ($request['status'] !== 'pending_it_action') return false;
    $lvl = (int)($user['level_id'] ?? 1);
    $role = determineRoleFromUser($user);
    if ($lvl === 99 || $role === 'admin') return false; // Admin does NOT sign
    if ($lvl === 45) return true; // IT Director
    $pos = $user['position'] ?? '';
    $dept = $user['department'] ?? '';
    if ($lvl === 20 && (stripos($pos, 'IT') !== false || stripos($dept, 'IT') !== false || stripos($user['username'] ?? '', 'it') !== false)) {
        return true;
    }
    return false;
}

/**
 * Check if the user can fulfill HR Action (Kept for compatibility)
 */
function canHROnlyAction($user, $request)
{
    if (!$user || !$request) return false;
    if ($request['status'] !== 'pending_hr') return false;
    $lvl = (int)($user['level_id'] ?? 1);
    if ($lvl === 99) return true;
    $pos = $user['position'] ?? '';
    $dept = $user['department'] ?? '';
    if ($lvl === 20 && (stripos($pos, 'HR') !== false || stripos($pos, 'Personnel') !== false || stripos($dept, 'HR') !== false)) {
        return true;
    }
    return false;
}

/**
 * Synchronize user profile into ex_user master table
 * Ensures user appears in employee lookup dropdowns and request forms
 */
function syncUserToExUser($userData)
{
    $db = getDB();

    $userCode   = trim($userData['user_code'] ?? ($userData['employee_id'] ?? ''));
    $employeeId = trim($userData['employee_id'] ?? $userCode);
    $firstName  = trim($userData['first_name'] ?? '');
    $lastName   = trim($userData['last_name'] ?? '');
    $nickname   = trim($userData['nickname'] ?? '');
    $gender     = (($userData['gender'] ?? '') === 'หญิง' || ($userData['gender'] ?? '') == 2) ? 2 : 1;
    $hotelId    = (int)($userData['hotel_id'] ?? 1);
    $levelId    = (int)($userData['level_id'] ?? 2);
    $department = trim($userData['department'] ?? '');

    // Map title to user_title_id
    $titleId = 1; // Default Mr.
    $title = trim($userData['title'] ?? '');
    if ($title === 'นาง' || $title === 'Mrs.') {
        $titleId = 2;
    } elseif ($title === 'นางสาว' || $title === 'Ms.') {
        $titleId = 3;
    } else {
        $stmtTitle = $db->prepare("SELECT title_id FROM ex_title WHERE title_name = ? LIMIT 1");
        $stmtTitle->execute([$title]);
        $foundTitleId = $stmtTitle->fetchColumn();
        if ($foundTitleId) {
            $titleId = (int)$foundTitleId;
        }
    }

    // Map department name to dep_id
    $depId = 1; // Default
    if (!empty($department)) {
        $stmtDep = $db->prepare("SELECT dep_id FROM ex_department WHERE dep_name = ? OR dep_name_s = ? LIMIT 1");
        $stmtDep->execute([$department, $department]);
        $foundDepId = $stmtDep->fetchColumn();
        if ($foundDepId) {
            $depId = (int)$foundDepId;
        } else {
            $stmtDepLike = $db->prepare("SELECT dep_id FROM ex_department WHERE dep_name LIKE ? LIMIT 1");
            $stmtDepLike->execute(["%{$department}%"]);
            $foundDepIdLike = $stmtDepLike->fetchColumn();
            if ($foundDepIdLike) {
                $depId = (int)$foundDepIdLike;
            }
        }
    }

    if (empty($userCode) || empty($firstName)) {
        return false;
    }

    try {
        // Check if user already exists in ex_user by user_code or hr_employee_id
        $stmtCheck = $db->prepare("SELECT user_id FROM ex_user WHERE user_code = ? OR hr_employee_id = ? LIMIT 1");
        $stmtCheck->execute([$userCode, $employeeId]);
        $existingExUserId = $stmtCheck->fetchColumn();

        if ($existingExUserId) {
            $stmtUpdate = $db->prepare("UPDATE ex_user SET 
                user_code = ?, hr_employee_id = ?, user_employee_id = ?,
                user_name = ?, user_surname = ?, user_nickname = ?,
                user_gender = ?, user_title_id = ?, dep_id = ?,
                user_hotel = ?, user_level = ?, user_status = 1,
                user_update = NOW()
                WHERE user_id = ?");
            $stmtUpdate->execute([
                $userCode,
                $employeeId,
                $employeeId,
                $firstName,
                $lastName,
                $nickname,
                $gender,
                $titleId,
                $depId,
                $hotelId,
                $levelId,
                $existingExUserId
            ]);
            return $existingExUserId;
        } else {
            $now = date('Y-m-d H:i:s');
            $stmtInsert = $db->prepare("INSERT INTO ex_user 
                (user_code, hr_employee_id, user_employee_id, user_name, user_surname, user_nickname, 
                 user_gender, user_title_id, dep_id, user_hotel, user_level, user_status, user_create, user_other_hotel)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, '')");
            $stmtInsert->execute([
                $userCode,
                $employeeId,
                $employeeId,
                $firstName,
                $lastName,
                $nickname,
                $gender,
                $titleId,
                $depId,
                $hotelId,
                $levelId,
                $now
            ]);
            return $db->lastInsertId();
        }
    } catch (Exception $e) {
        error_log("Failed to sync user to ex_user: " . $e->getMessage());
        return false;
    }
}

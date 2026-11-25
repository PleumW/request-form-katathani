<?php

/**
 * Admin Edit User Page
 * Allows administrator to update personal info, department,
 * AND exclusively permission/level, username, and password.
 * 
 * Access: Administrator only (level_id = 99)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$currentUser = getCurrentUser();
$db = getDB();

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    setFlash('error', 'ไม่พบรหัสผู้ใช้งานที่ระบุ');
    redirect('users.php');
}

// Fetch user data from ex_user
$stmt = $db->prepare("SELECT u.user_id AS id, u.user_id, u.user_code,
                             u.hr_employee_id AS employee_id, u.hr_employee_id, u.user_employee_id,
                             u.user_name AS first_name, u.user_surname AS last_name, u.user_nickname AS nickname,
                             TRIM(CONCAT(COALESCE(t.title_name, ''), ' ', u.user_name, ' ', u.user_surname)) AS full_name,
                             u.user_email AS email, u.user_level AS level_id, u.user_hotel AS hotel_id,
                             u.dep_id, d.dep_name AS department, d.dep_name_s,
                             t.title_name AS title, u.user_title_id,
                             (CASE WHEN u.user_gender = 2 THEN 'หญิง' ELSE 'ชาย' END) AS gender,
                             l.level_name, h.hotel_name, p.property_name,
                             lg.login_id AS login_table_id, lg.user_username AS username, lg.login_update
                      FROM ex_user u
                      LEFT JOIN ex_title t ON u.user_title_id = t.title_id
                      LEFT JOIN ex_department d ON u.dep_id = d.dep_id
                      LEFT JOIN ex_level l ON u.user_level = l.level_id
                      LEFT JOIN ex_hotel h ON u.user_hotel = h.hotel_id
                      LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id
                      LEFT JOIN ex_login lg ON u.user_code = lg.user_code
                      WHERE u.user_id = ?
                      LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'ไม่พบข้อมูลผู้ใช้งานในระบบ');
    redirect('users.php');
}

$allHotels = getExHotels();
$allDepartments = getExDepartments();
$allLevels = getExLevels();
$allTitles = getExTitles();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Standard fields (Personal & Department)
    $gender          = trim($_POST['gender'] ?? 'ชาย');
    $title           = trim($_POST['title'] ?? '');
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $nickname        = trim($_POST['nickname'] ?? '');
    $employeeId      = trim($_POST['employee_id'] ?? '');
    $userCode        = trim($_POST['user_code'] ?? ($employeeId ?: $user['user_code']));
    $hotelId         = (int)($_POST['hotel_id'] ?? 1);
    $department      = trim($_POST['department'] ?? '');
    $position        = trim($_POST['position'] ?? '');
    $email           = trim($_POST['email'] ?? '');

    // ADMIN-ONLY fields: Permission, Username, Password
    $levelId         = (int)($_POST['level_id'] ?? 2);
    $username        = trim($_POST['username'] ?? '');
    $newPassword     = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    // Construct full name
    $fullName = trim("{$title} {$firstName} {$lastName}");
    if (empty($fullName)) {
        $fullName = trim("{$firstName} {$lastName}");
    }

    // Validation
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'ชื่อผู้ใช้ (Username) ต้องมีความยาวอย่างน้อย 3 ตัวอักษร';
    }
    if (empty($firstName)) {
        $errors[] = 'กรุณาระบุชื่อ (First Name)';
    }
    if (empty($lastName)) {
        $errors[] = 'กรุณาระบุนามสกุล (Last Name)';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณาระบุรูปแบบอีเมลที่ถูกต้อง';
    }

    // Check duplicate username if username changed
    if ($username !== ($user['username'] ?? '')) {
        $chkStmt = $db->prepare("SELECT login_id FROM ex_login WHERE user_username = ? AND user_code != ? LIMIT 1");
        $chkStmt->execute([$username, $user['user_code']]);
        if ($chkStmt->fetch()) {
            $errors[] = 'ชื่อผู้ใช้นี้ (Username) ถูกใช้งานโดยบัญชีอื่นแล้ว';
        }
    }

    // Check duplicate email if changed and not empty
    if (!empty($email) && $email !== ($user['email'] ?? '')) {
        $chkEmail = $db->prepare("SELECT user_id FROM ex_user WHERE user_email = ? AND user_id != ? LIMIT 1");
        $chkEmail->execute([$email, $userId]);
        if ($chkEmail->fetch()) {
            $errors[] = 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว';
        }
    }

    // Check new password if provided
    $updatePassword = false;
    $hashedPassword = '';
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            $errors[] = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'รหัสผ่านยืนยันไม่ตรงกับรหัสผ่านใหม่ที่ระบุ';
        }
        if (empty($errors)) {
            $updatePassword = true;
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        }
    }

    if (empty($errors)) {
        try {
            // Get hotel info
            $hotelInfo = getHotelById($hotelId);
            $propertyName = $hotelInfo['hotel_name'] ?? 'Katathani';

            // Map gender
            $genderInt = ($gender === 'หญิง' || $gender == 2) ? 2 : 1;

            // Map title to user_title_id
            $titleId = 1;
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

            // Map department to dep_id
            $depId = 1;
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

            // 1. Update ex_user table safely (if local or writable, otherwise skip if VIEW from dashboard_member)
            try {
                $updateExUserSql = "UPDATE ex_user SET 
                    user_name = ?, user_surname = ?, user_nickname = ?,
                    user_gender = ?, user_title_id = ?, dep_id = ?,
                    user_hotel = ?, user_level = ?, user_email = ?,
                    hr_employee_id = ?, user_employee_id = ?, user_code = ?,
                    user_update = NOW()
                    WHERE user_id = ?";
                $stmtUpdate = $db->prepare($updateExUserSql);
                $stmtUpdate->execute([
                    $firstName,
                    $lastName,
                    $nickname,
                    $genderInt,
                    $titleId,
                    $depId,
                    $hotelId,
                    $levelId,
                    $email,
                    !empty($employeeId) ? $employeeId : $userCode,
                    !empty($employeeId) ? $employeeId : $userCode,
                    $userCode,
                    $userId
                ]);
            } catch (Exception $e) {
                // If ex_user is a read-only VIEW from dashboard_member, skip safely without error
                error_log("Note: ex_user could not be updated (read-only/VIEW): " . $e->getMessage());
            }

            // 2. Synchronize ex_login table safely
            try {
                $oldUsername = $user['username'] ?? '';
                $oldUserCode = $user['user_code'];

                $stmtFindLogin = $db->prepare("SELECT login_id FROM ex_login WHERE user_username = ? OR user_code = ? LIMIT 1");
                $stmtFindLogin->execute([$oldUsername, $oldUserCode]);
                $existingLoginId = $stmtFindLogin->fetchColumn();

                if ($existingLoginId) {
                    if ($updatePassword) {
                        $stmtUpdLogin = $db->prepare("UPDATE ex_login SET user_code = ?, user_username = ?, user_password = ?, login_update = NOW() WHERE login_id = ?");
                        $stmtUpdLogin->execute([$userCode, $username, $hashedPassword, $existingLoginId]);
                    } else {
                        $stmtUpdLogin = $db->prepare("UPDATE ex_login SET user_code = ?, user_username = ?, login_update = NOW() WHERE login_id = ?");
                        $stmtUpdLogin->execute([$userCode, $username, $existingLoginId]);
                    }
                } else {
                    $stmtInsLogin = $db->prepare("INSERT INTO ex_login (user_code, user_username, user_password, login_create, login_update) VALUES (?, ?, ?, NOW(), NOW())");
                    $stmtInsLogin->execute([$userCode, $username, $updatePassword ? $hashedPassword : password_hash('password123', PASSWORD_BCRYPT)]);
                }
            } catch (Exception $e) {
                // If ex_login is a read-only VIEW, skip safely
                error_log("Note: ex_login could not be updated (read-only/VIEW): " . $e->getMessage());
            }

            // Refresh current user session role if the edited user is currently logged in
            if ($userId === (int)($currentUser['id'] ?? 0)) {
                $_SESSION['role'] = determineRoleFromUser(array_merge($currentUser, [
                    'user_id' => $userId,
                    'level_id' => $levelId,
                    'user_level' => $levelId,
                    'department' => $department,
                    'dep_id' => $depId
                ]));
                $_SESSION['level_id'] = $levelId;
            }

            setFlash('success', "บันทึกการแก้ไขข้อมูลผู้ใช้ '{$fullName}' ({$username}) ในระบบเรียบร้อยแล้ว");
            redirect('users.php');
        } catch (Exception $e) {
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

$pageTitle = lang('แก้ไขข้อมูลและสิทธิ์ผู้ใช้: ', 'Edit User Profile: ') . htmlspecialchars($user['full_name']) . ' - ' . APP_NAME;
$curLevel  = (int)($user['level_id'] ?? 2);

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto py-6 space-y-6">

    <!-- Breadcrumb & Back Link -->
    <div class="flex items-center justify-between">
        <a href="<?= url('users.php') ?>" class="inline-flex items-center space-x-2 text-xs font-semibold text-slate-500 hover:text-sky-600 transition">
            <i class="fa-solid fa-arrow-left"></i>
            <span><?= lang('กลับหน้ารายชื่อผู้ใช้งาน', 'Back to User Management') ?></span>
        </a>
    </div>

    <!-- Header Box -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xl shadow-md shadow-sky-900/10">
                <?= mb_substr($user['first_name'] ?: $user['full_name'], 0, 1) ?>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-800">
                    <?= lang('แก้ไขข้อมูล:', 'Edit Profile:') ?> <?= htmlspecialchars($user['full_name']) ?>
                    <?php if (!empty($user['nickname'])): ?>
                        <span class="text-sky-600 text-base font-semibold">(<?= htmlspecialchars($user['nickname']) ?>)</span>
                    <?php endif; ?>
                </h1>
                <div class="text-xs text-slate-500 flex flex-wrap items-center gap-2 mt-1">
                    <span class="font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-semibold">
                        <?= lang('รหัสพนักงาน:', 'Code:') ?> <?= htmlspecialchars($user['user_code'] ?: $user['employee_id'] ?: '-') ?>
                    </span>
                    <span>•</span>
                    <span><?= lang('ชื่อผู้ใช้:', 'Username:') ?> <strong class="text-slate-700 font-mono"><?= htmlspecialchars($user['username']) ?></strong></span>
                    <span>•</span>
                    <span><?= lang('สิทธิ์ปัจจุบัน:', 'Current Permission:') ?> <strong class="text-sky-700"><?= htmlspecialchars($user['level_name'] ?? 'User') ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1">
            <div class="font-bold flex items-center space-x-2 text-rose-700">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= lang('ไม่สามารถบันทึกข้อมูลได้ กรุณาตรวจสอบ:', 'Unable to save. Please review the following errors:') ?></span>
            </div>
            <ul class="list-disc list-inside text-xs pl-2 space-y-0.5">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Form Container -->
    <form method="POST" action="edit_user.php?id=<?= $userId ?>" class="space-y-6" id="editUserForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

        <!-- Section 1: ข้อมูลส่วนตัว -->
        <div class="bg-white p-6 sm:p-7 rounded-2xl border border-slate-200 shadow-sm space-y-5">
            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 flex items-center space-x-2">

                    <span><?= lang('1. ข้อมูลส่วนบุคคล', '1. Personal Information') ?></span>
                </h3>

            </div>

            <!-- เพศ, ชื่อหน้า, ชื่อเล่น -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- เพศ -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('เพศ', 'Gender') ?> <span class="text-rose-500">*</span>
                    </label>
                    <select name="gender" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                        <option value="ชาย" <?= (($user['gender'] ?? 'ชาย') === 'ชาย') ? 'selected' : '' ?>><?= lang('ชาย', 'Male') ?></option>
                        <option value="หญิง" <?= (($user['gender'] ?? '') === 'หญิง') ? 'selected' : '' ?>><?= lang('หญิง', 'Female') ?></option>
                    </select>
                </div>

                <!-- คำนำหน้าชื่อ -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('คำนำหน้าชื่อ', 'Title / Prefix') ?> <span class="text-rose-500">*</span>
                    </label>
                    <select name="title" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                        <option value="">-- <?= lang('เลือกคำนำหน้า', 'Select Title') ?> --</option>
                        <option value="นาย" <?= (($user['title'] ?? '') === 'นาย') ? 'selected' : '' ?>><?= lang('นาย', 'Mr.') ?></option>
                        <option value="นาง" <?= (($user['title'] ?? '') === 'นาง') ? 'selected' : '' ?>><?= lang('นาง', 'Mrs.') ?></option>
                        <option value="นางสาว" <?= (($user['title'] ?? '') === 'นางสาว') ? 'selected' : '' ?>><?= lang('นางสาว', 'Ms.') ?></option>
                        <option value="Mr." <?= (($user['title'] ?? '') === 'Mr.') ? 'selected' : '' ?>>Mr.</option>
                        <option value="Mrs." <?= (($user['title'] ?? '') === 'Mrs.') ? 'selected' : '' ?>>Mrs.</option>
                        <option value="Ms." <?= (($user['title'] ?? '') === 'Ms.') ? 'selected' : '' ?>>Ms.</option>
                        <?php foreach ($allTitles as $t): ?>
                            <?php if (!in_array($t['title_name'], ['Mr.', 'Mrs.', 'Ms.', 'นาย', 'นาง', 'นางสาว'])): ?>
                                <option value="<?= htmlspecialchars($t['title_name']) ?>" <?= (($user['title'] ?? '') === $t['title_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['title_name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ชื่อเล่น -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('ชื่อเล่น', 'Nickname') ?>
                    </label>
                    <input type="text" name="nickname" value="<?= htmlspecialchars($user['nickname'] ?? '') ?>" placeholder="<?= lang('ชื่อเล่น', 'Nickname') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>
            </div>

            <!-- ชื่อ & สกุล -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('ชื่อ', 'First Name') ?> <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" placeholder="<?= lang('ชื่อจริง', 'First Name') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('นามสกุล', 'Last Name') ?> <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" placeholder="<?= lang('นามสกุล', 'Last Name') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>
            </div>

            <!-- รหัสพนักงาน & User Code -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('รหัสพนักงาน', 'Employee ID') ?>
                    </label>
                    <input type="text" name="employee_id" value="<?= htmlspecialchars($user['employee_id'] ?? '') ?>" placeholder="<?= lang('เช่น 11002693', 'e.g. 11002693') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white font-mono transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('รหัสผู้ใช้งาน', 'User Code') ?> <span class="text-slate-400 text-[10px]">(<?= lang('สำหรับเข้าสู่ระบบด้วยรหัสพนักงาน', 'For login credentials') ?>)</span>
                    </label>
                    <input type="text" name="user_code" value="<?= htmlspecialchars($user['user_code'] ?? '') ?>" placeholder="<?= lang('เช่น 3255-2693', 'e.g. 3255-2693') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white font-mono transition">
                </div>
            </div>
        </div>

        <!-- Section 2: สังกัด & ข้อมูลติดต่อ -->
        <div class="bg-white p-6 sm:p-7 rounded-2xl border border-slate-200 shadow-sm space-y-5">
            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 flex items-center space-x-2">

                    <span><?= lang('2. ข้อมูลสังกัดและสถานที่ปฏิบัติงาน', '2. Department & Location') ?></span>
                </h3>

            </div>

            <!-- โรงแรม & แผนก -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('โรงแรม / สังกัด', 'Hotel / Property') ?> <span class="text-rose-500">*</span>
                    </label>
                    <select name="hotel_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                        <?php foreach ($allHotels as $h): ?>
                            <option value="<?= $h['hotel_id'] ?>" <?= (($user['hotel_id'] ?? 1) == $h['hotel_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($h['hotel_name']) ?> (<?= htmlspecialchars($h['property_name'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('แผนก', 'Department') ?> <span class="text-rose-500">*</span>
                    </label>
                    <select name="department" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                        <option value="">-- <?= lang('เลือกแผนก', 'Select Department') ?> --</option>
                        <?php foreach ($allDepartments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['dep_name']) ?>" <?= (($user['department'] ?? '') === $dept['dep_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['dep_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- ตำแหน่ง & อีเมล -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5"><?= lang('ตำแหน่ง', 'Position') ?></label>
                    <input type="text" name="position" value="<?= htmlspecialchars($user['position'] ?? '') ?>" placeholder="<?= lang('เช่น หัวหน้าแผนก, ผู้ช่วย, พนักงาน', 'e.g. Supervisor, Officer, Manager') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('อีเมล', 'Email') ?>
                    </label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="example@katathani.com"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>
            </div>
        </div>

        <!-- Section 3: สิทธิ์การใช้งานระบบ -->
        <div class="bg-white p-6 sm:p-7 rounded-2xl border border-slate-200 shadow-sm space-y-5">
            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 flex items-center space-x-2">
                        <span><?= lang('3. สิทธิ์การใช้งานในระบบ', '3. System Permission Level') ?></span>
                    </h3>
                </div>
            </div>

            <!-- Permission Selection: ติ๊กเลือกสิทธิ์ได้อย่างสะดวกรวดเร็ว -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <!-- 1. User (Level 2) -->
                <label class="relative flex flex-col p-4 bg-slate-50 border-2 rounded-xl cursor-pointer transition hover:border-slate-300 group has-checked:border-sky-600 has-checked:bg-sky-50/50 shadow-2xs">
                    <div class="flex items-center justify-between mb-2">

                        <input type="radio" name="level_id" value="2" <?= ($curLevel <= 3) ? 'checked' : '' ?> class="w-4 h-4 text-sky-600 focus:ring-sky-500">
                    </div>
                    <div class="text-xs font-bold text-slate-800 group-has-checked:text-sky-900"><?= lang('ผู้ใช้งานทั่วไป', 'General User') ?></div>
                    <div class="text-[11px] text-slate-500 mt-1"><?= lang('ยื่นคำขอและติดตามสถานะคำขอของตนเอง', 'Submit and track own requests') ?></div>
                </label>

                <!-- 2. Staff / Coordinator (Level 20) -->
                <label class="relative flex flex-col p-4 bg-slate-50 border-2 rounded-xl cursor-pointer transition hover:border-slate-300 group has-checked:border-amber-600 has-checked:bg-amber-50/50 shadow-2xs">
                    <div class="flex items-center justify-between mb-2">

                        <input type="radio" name="level_id" value="20" <?= ($curLevel === 20) ? 'checked' : '' ?> class="w-4 h-4 text-amber-600 focus:ring-amber-500">
                    </div>
                    <div class="text-xs font-bold text-slate-800 group-has-checked:text-amber-900"><?= lang('เจ้าหน้าที่ผู้ประสานงาน', 'Staff / Coordinator') ?></div>
                    <div class="text-[11px] text-slate-500 mt-1"><?= lang('เจ้าหน้าที่ไอที หรือ ฝ่ายบุคคล ดำเนินการและจัดเตรียมสิทธิ์', 'IT or HR staff responsible for provisioning') ?></div>
                </label>

                <!-- 3. Manager / Approver (Level 40) -->
                <label class="relative flex flex-col p-4 bg-slate-50 border-2 rounded-xl cursor-pointer transition hover:border-slate-300 group has-checked:border-indigo-600 has-checked:bg-indigo-50/50 shadow-2xs">
                    <div class="flex items-center justify-between mb-2">

                        <input type="radio" name="level_id" value="40" <?= ($curLevel >= 40 && $curLevel < 60) ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                    </div>
                    <div class="text-xs font-bold text-slate-800 group-has-checked:text-indigo-900"><?= lang('ผู้จัดการ / หัวหน้างาน', 'Manager / Dept Head') ?></div>
                    <div class="text-[11px] text-slate-500 mt-1"><?= lang('ผู้อนุมัติตามแผนก (หัวหน้าแผนก หรือ ผู้จัดการไอที)', 'Department Head or IT Manager approval stage') ?></div>
                </label>

                <!-- 4. Hotel Manager / Executive (Level 60) -->
                <label class="relative flex flex-col p-4 bg-slate-50 border-2 rounded-xl cursor-pointer transition hover:border-slate-300 group has-checked:border-orange-600 has-checked:bg-orange-50/50 shadow-2xs">
                    <div class="flex items-center justify-between mb-2">

                        <input type="radio" name="level_id" value="60" <?= (in_array($curLevel, [50, 60, 70])) ? 'checked' : '' ?> class="w-4 h-4 text-orange-600 focus:ring-orange-500">
                    </div>
                    <div class="text-xs font-bold text-slate-800 group-has-checked:text-orange-900"><?= lang('ผู้จัดการโรงแรม / บริหาร', 'Hotel Manager / Executive') ?></div>
                    <div class="text-[11px] text-slate-500 mt-1"><?= lang('ผู้อนุมัติระดับบริหารโรงแรม (GM / HM / RM)', 'Hotel Executive / General Manager approval stage') ?></div>
                </label>

                <!-- 5. Administrator (Level 99) -->
                <label class="relative flex flex-col p-4 bg-slate-50 border-2 rounded-xl cursor-pointer transition hover:border-slate-300 group has-checked:border-purple-600 has-checked:bg-purple-50/50 shadow-2xs">
                    <div class="flex items-center justify-between mb-2">

                        <input type="radio" name="level_id" value="99" <?= ($curLevel === 99) ? 'checked' : '' ?> class="w-4 h-4 text-purple-600 focus:ring-purple-500">
                    </div>
                    <div class="text-xs font-bold text-slate-800 group-has-checked:text-purple-900"><?= lang('ผู้ดูแลระบบ', 'Administrator') ?></div>
                    <div class="text-[11px] text-slate-500 mt-1"><?= lang('สิทธิ์สูงสุด จัดการข้อมูลและระบบทั้งหมด', 'Full system and administrative privileges') ?></div>
                </label>
            </div>

            <!-- Username & Password -->
            <div class="pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        <?= lang('ชื่อผู้ใช้', 'Username') ?> <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($user['username']) ?>" placeholder="<?= lang('ชื่อผู้ใช้', 'Username') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('กำหนดรหัสผ่านใหม่', 'Reset Password') ?>
                    </label>
                    <div class="relative">
                        <input type="password" name="new_password" id="new_password" placeholder="<?= lang('เว้นว่างถ้าไม่ต้องการเปลี่ยน', 'Leave blank to keep unchanged') ?>"
                            class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                        <button type="button" onclick="togglePassVisibility('new_password', this)" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <?= lang('ยืนยันรหัสผ่านใหม่', 'Confirm Password') ?>
                    </label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="<?= lang('พิมพ์ยืนยันอีกครั้ง', 'Re-enter to confirm') ?>"
                            class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                        <button type="button" onclick="togglePassVisibility('confirm_password', this)" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-2">
            <a href="<?= url('users.php') ?>" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                <?= lang('ยกเลิก', 'Cancel') ?>
            </a>

            <div class="flex items-center space-x-3">
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/25 transition flex items-center space-x-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span><?= lang('บันทึกการเปลี่ยนแปลง', 'Save Changes') ?></span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function togglePassVisibility(inputId, btn) {
        var input = document.getElementById(inputId);
        var icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
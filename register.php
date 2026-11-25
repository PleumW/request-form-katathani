<?php

/**
 * User Creation Page (Admin Only)
 * Allows administrators to register/create new user accounts.
 * Regular users cannot register themselves.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

// Strict Security: Only Administrator can access this page
requireAdmin();

$allDepartments = getExDepartments();
$allHotels = getExHotels();
$allTitles = getExTitles();
$allLevels = getExLevels();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $username        = trim($_POST['username'] ?? '');
    $password        = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $gender          = trim($_POST['gender'] ?? 'ชาย');
    $title           = trim($_POST['title'] ?? '');
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $nickname        = trim($_POST['nickname'] ?? '');
    $employeeId      = trim($_POST['employee_id'] ?? '');
    $userCode        = trim($_POST['user_code'] ?? ($employeeId ?: ('U-' . rand(1000, 9999))));
    $email           = null;
    $department      = trim($_POST['department'] ?? '');
    $hotelId         = (int)($_POST['hotel_id'] ?? 1);
    $position        = trim($_POST['position'] ?? '');
    $levelId         = (int)($_POST['level_id'] ?? 2);

    // Construct full name
    $fullName = trim("{$title} {$firstName} {$lastName}");
    if (empty($fullName)) {
        $fullName = trim("{$firstName} {$lastName}");
    }

    // Validation
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'ชื่อผู้ใช้ (Username) ต้องมีความยาวอย่างน้อย 3 ตัวอักษร';
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'รหัสผ่าน (Password) ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'รหัสผ่านยืนยันไม่ตรงกับรหัสผ่านที่ตั้งไว้';
    }
    if (empty($firstName)) {
        $errors[] = 'กรุณาระบุชื่อ (First Name)';
    }
    if (empty($lastName)) {
        $errors[] = 'กรุณาระบุนามสกุล (Last Name)';
    }
    if (empty($department)) {
        $errors[] = 'กรุณาเลือกแผนก';
    }

    $db = getDB();

    // Check duplicate username (check ex_login table)
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT login_id FROM ex_login WHERE user_username = ? OR user_code = ? LIMIT 1");
        $stmt->execute([$username, $userCode]);
        if ($stmt->fetch()) {
            $errors[] = 'ชื่อผู้ใช้นี้ (Username) หรือรหัสผู้ใช้นี้ มีอยู่ในระบบแล้ว กรุณาเลือกชื่ออื่น';
        }
    }

    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $loginToken = bin2hex(random_bytes(32));

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

            // 1. Insert into ex_user table
            $insertExUserSql = "INSERT INTO ex_user 
                (user_code, hr_employee_id, user_employee_id, user_name, user_surname, user_nickname, 
                 user_gender, user_title_id, dep_id, user_hotel, user_level, user_status, user_create, user_other_hotel, user_email)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), '', ?)";
            $stmtExUser = $db->prepare($insertExUserSql);
            $stmtExUser->execute([
                $userCode,
                !empty($employeeId) ? $employeeId : $userCode,
                !empty($employeeId) ? $employeeId : $userCode,
                $firstName,
                $lastName,
                $nickname,
                $genderInt,
                $titleId,
                $depId,
                $hotelId,
                $levelId,
                $email
            ]);
            $newExUserId = $db->lastInsertId();

            // 2. Insert into ex_login table
            $stmtExLogin = $db->prepare("INSERT INTO ex_login (user_code, user_username, user_password, login_create, login_update, login_token) 
                                         VALUES (?, ?, ?, NOW(), NOW(), ?)
                                         ON DUPLICATE KEY UPDATE user_code = VALUES(user_code), user_password = VALUES(user_password), login_token = VALUES(login_token)");
            $stmtExLogin->execute([$userCode, $username, $hashedPassword, $loginToken]);

            // Keep Admin logged in and redirect back to User Management page
            setFlash('success', "เพิ่มผู้ใช้งานใหม่ '{$fullName}' ({$username}) ในระบบเรียบร้อยแล้ว");
            redirect('users.php');
        } catch (Exception $e) {
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'เพิ่มผู้ใช้งานใหม่ (Admin Create User) - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto py-6 space-y-6">

    <!-- Breadcrumb & Back Link -->
    <div class="flex items-center justify-between">
        <a href="<?= url('users.php') ?>" class="inline-flex items-center space-x-2 text-xs font-semibold text-slate-500 hover:text-sky-600 transition">
            <i class="fa-solid fa-arrow-left"></i>
            <span>กลับหน้ารายชื่อผู้ใช้งาน</span>
        </a>

    </div>

    <!-- Header Box -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-4">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-600 items-center justify-center text-white text-xl shadow-md shadow-sky-900/15 flex flex-shrink-0">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        <div>
            <h1 class="text-xl font-bold text-slate-800">เพิ่มผู้ใช้งานใหม่ในระบบ (Create User)</h1>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1">
            <div class="font-bold flex items-center space-x-2 text-rose-700">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>กรุณาตรวจสอบข้อมูล:</span>
            </div>
            <ul class="list-disc list-inside text-xs pl-2 space-y-0.5">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
        <form method="POST" action="register.php" class="space-y-6" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

            <!-- 1. ค้นหารหัสพนักงาน & สังกัด -->
            <div class="space-y-4">
                <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-sky-700 uppercase tracking-wider flex items-center">
                        <span class="w-2 h-2 rounded-full bg-sky-500 mr-2"></span>
                        1. ข้อมูลพนักงาน
                    </h3>
                </div>

                <!-- รหัสพนักงาน (Searchable Dropdown) -->
                <div class="relative z-30" id="regEmpDropdownWrapper">
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5 flex items-center justify-between">
                        <span>รหัสพนักงาน (Employee ID)</span>
                        <span id="regEmpLookupStatus" class="text-[10px] text-sky-600 font-normal hidden">
                            <i class="fa-solid fa-check text-emerald-500 mr-1"></i>ดึงข้อมูลพนักงานเรียบร้อย
                        </span>
                    </label>
                    <div class="relative">
                        <input type="text" name="employee_id" id="reg_employee_id" value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>" placeholder="คลิกเพื่อเลือก หรือพิมพ์ค้นหาชื่อ / รหัสพนักงาน..." autocomplete="off"
                            class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white font-mono cursor-pointer transition">
                        <button type="button" id="regEmpToggleBtn" class="absolute right-0 top-0 bottom-0 px-3 text-slate-400 hover:text-sky-600 flex items-center justify-center cursor-pointer transition">
                            <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform duration-200" id="regEmpChevron"></i>
                        </button>
                    </div>

                    <!-- Dropdown List -->
                    <div id="regEmpDropdownList" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto z-50 divide-y divide-slate-100 text-xs">
                    </div>
                </div>

                <!-- เพศ, ชื่อหน้า, ชื่อเล่น -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- เพศ -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            เพศ (Gender) <span class="text-rose-500">*</span>
                        </label>
                        <select name="gender" id="reg_gender" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                            <option value="ชาย" <?= (($_POST['gender'] ?? 'ชาย') === 'ชาย') ? 'selected' : '' ?>>ชาย (Male)</option>
                            <option value="หญิง" <?= (($_POST['gender'] ?? '') === 'หญิง') ? 'selected' : '' ?>>หญิง (Female)</option>
                        </select>
                    </div>

                    <!-- ชื่อหน้า (คำนำหน้าชื่อ) -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            ชื่อหน้า (Title) <span class="text-rose-500">*</span>
                        </label>
                        <select name="title" id="reg_title" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                            <option value="">-- เลือก --</option>
                            <option value="นาย" <?= (($_POST['title'] ?? '') === 'นาย') ? 'selected' : '' ?>>นาย (Mr.)</option>
                            <option value="นาง" <?= (($_POST['title'] ?? '') === 'นาง') ? 'selected' : '' ?>>นาง (Mrs.)</option>
                            <option value="นางสาว" <?= (($_POST['title'] ?? '') === 'นางสาว') ? 'selected' : '' ?>>นางสาว (Ms.)</option>
                            <option value="Mr." <?= (($_POST['title'] ?? '') === 'Mr.') ? 'selected' : '' ?>>Mr.</option>
                            <option value="Mrs." <?= (($_POST['title'] ?? '') === 'Mrs.') ? 'selected' : '' ?>>Mrs.</option>
                            <option value="Ms." <?= (($_POST['title'] ?? '') === 'Ms.') ? 'selected' : '' ?>>Ms.</option>
                            <?php foreach ($allTitles as $t): ?>
                                <?php if (!in_array($t['title_name'], ['Mr.', 'Mrs.', 'Ms.'])): ?>
                                    <option value="<?= htmlspecialchars($t['title_name']) ?>" <?= (($_POST['title'] ?? '') === $t['title_name']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['title_name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ชื่อเล่น -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            ชื่อเล่น (Nickname)
                        </label>
                        <input type="text" name="nickname" id="reg_nickname" value="<?= htmlspecialchars($_POST['nickname'] ?? '') ?>" placeholder="ชื่อเล่น"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    </div>
                </div>

                <!-- ชื่อ & สกุล -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            ชื่อ (First Name) <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="first_name" id="reg_first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" placeholder="ชื่อจริงพนักงาน"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            สกุล (Last Name) <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="last_name" id="reg_last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" placeholder="นามสกุล"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    </div>
                </div>

                <!-- โรงแรม & แผนก -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            โรงแรม (Hotel) <span class="text-rose-500">*</span>
                        </label>
                        <select name="hotel_id" id="reg_hotel_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                            <?php foreach ($allHotels as $h): ?>
                                <option value="<?= $h['hotel_id'] ?>" <?= (($_POST['hotel_id'] ?? 1) == $h['hotel_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($h['hotel_name']) ?> (<?= htmlspecialchars($h['property_name'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            แผนก (Department) <span class="text-rose-500">*</span>
                        </label>
                        <select name="department" id="reg_department" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                            <option value="">-- เลือกแผนก --</option>
                            <?php foreach ($allDepartments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['dep_name']) ?>" <?= (($_POST['department'] ?? '') === $dept['dep_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['dep_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- ตำแหน่ง -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">ตำแหน่ง (Position)</label>
                    <input type="text" name="position" id="reg_position" value="<?= htmlspecialchars($_POST['position'] ?? '') ?>" placeholder="เช่น GSA, IT Support, Manager"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>
            </div>

            <!-- 2. กำหนดสิทธิ์การใช้งาน (Admin Role Selection) -->
            <div class="bg-amber-50/70 p-5 rounded-xl border border-amber-200/90 space-y-3">
                <div class="flex items-center justify-between border-b border-amber-200/60 pb-2">
                    <h3 class="text-xs font-bold text-amber-900 flex items-center">
                        <i class="fa-solid fa-shield-halved text-amber-600 mr-2"></i>
                        2. กำหนดสิทธิ์การใช้งานในระบบ (Permission / Role) <span class="text-rose-500 ml-1">*</span>
                    </h3>
                </div>

                <div>
                    <select name="level_id" required class="w-full px-3.5 py-2.5 bg-white border border-amber-300 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
                        <?php foreach ($allLevels as $lvl): ?>
                            <option value="<?= $lvl['level_id'] ?>" <?= (($_POST['level_id'] ?? 2) == $lvl['level_id']) ? 'selected' : '' ?>>
                                [Level <?= $lvl['level_id'] ?>] <?= htmlspecialchars($lvl['level_name']) ?>
                                <?php
                                if ($lvl['level_id'] == 99) echo ' - ผู้ดูแลระบบสูงสุด (Administrator)';
                                elseif ($lvl['level_id'] == 40) echo ' - หัวหน้าแผนก / ผู้จัดการโรงแรม / ผู้จัดการไอที (Approver)';
                                elseif ($lvl['level_id'] == 20) echo ' - เจ้าหน้าที่ไอที / ฝ่ายบุคคล (Fulfillment Staff)';
                                elseif ($lvl['level_id'] <= 3) echo ' - ผู้ยื่นคำขอทั่วไป (Requester)';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 3. ข้อมูลการเข้าสู่ระบบ (Credentials) -->
            <div class="space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h3 class="text-xs font-bold text-sky-700 uppercase tracking-wider flex items-center">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 mr-2"></span>
                        3. บัญชีเข้าสู่ระบบ (Login Credentials)
                    </h3>
                </div>

                <!-- Username -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        ชื่อผู้ใช้ (Username) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="ใช้ภาษาอังกฤษหรือตัวเลข สำหรับล็อกอิน"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>

                <!-- Passwords -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            รหัสผ่านเริ่มต้น (Initial Password) <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="password" required placeholder="อย่างน้อย 6 ตัวอักษร"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            ยืนยันรหัสผ่าน (Confirm Password) <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="confirm_password" required placeholder="พิมพ์รหัสผ่านอีกครั้ง"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    </div>
                </div>
            </div>

            <!-- Submit Button & Cancel -->
            <div class="pt-4 flex items-center justify-between border-t border-slate-100">
                <a href="<?= url('users.php') ?>" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition">
                    ยกเลิก
                </a>

                <button type="submit" class="py-2.5 px-6 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/25 transition flex items-center space-x-2">
                    <i class="fa-solid fa-user-check"></i>
                    <span>สร้างบัญชีผู้ใช้งาน (Create User)</span>
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var empInput = document.getElementById('reg_employee_id');
        var empList = document.getElementById('regEmpDropdownList');
        var empToggleBtn = document.getElementById('regEmpToggleBtn');
        var empChevron = document.getElementById('regEmpChevron');
        var empWrapper = document.getElementById('regEmpDropdownWrapper');
        var empStatus = document.getElementById('regEmpLookupStatus');

        var lookupTimeout = null;

        function openDropdown() {
            if (empList) {
                empList.classList.remove('hidden');
                if (empChevron) empChevron.style.transform = 'rotate(180deg)';
                fetchEmployees(empInput ? empInput.value.trim() : '');
            }
        }

        function closeDropdown() {
            if (empList) {
                empList.classList.add('hidden');
                if (empChevron) empChevron.style.transform = 'rotate(0deg)';
            }
        }

        function renderDropdown(items, query) {
            if (!empList) return;
            empList.innerHTML = '';

            if (!items || items.length === 0) {
                empList.innerHTML = '<div class="p-4 text-center text-slate-400">ไม่พบข้อมูลพนักงานที่ตรงกับคำค้นหา</div>';
                return;
            }

            var html = '';
            items.forEach(function(item) {
                var dispEmpId = item.employee_id || item.user_code;
                var dispNick = item.nickname ? ' (' + item.nickname + ')' : '';
                var itemJson = encodeURIComponent(JSON.stringify(item));

                html += '<div class="p-3 hover:bg-sky-50 cursor-pointer transition select-emp-item" data-user="' + itemJson + '">' +
                    '<div class="flex items-center justify-between">' +
                    '<div class="font-bold text-slate-800">' + (item.title ? item.title + ' ' : '') + item.first_name + ' ' + item.last_name + dispNick + '</div>' +
                    '<span class="font-mono text-[11px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-semibold">' + dispEmpId + '</span>' +
                    '</div>' +
                    '<div class="text-[11px] text-slate-500 mt-1 flex items-center space-x-2">' +
                    '<span><i class="fa-solid fa-hotel text-slate-400 mr-1"></i>' + (item.hotel_name || '-') + '</span>' +
                    '<span>•</span>' +
                    '<span><i class="fa-solid fa-briefcase text-slate-400 mr-1"></i>' + (item.department || '-') + '</span>' +
                    '</div>' +
                    '</div>';
            });

            empList.innerHTML = html;

            empList.querySelectorAll('.select-emp-item').forEach(function(el) {
                el.addEventListener('click', function() {
                    try {
                        var data = JSON.parse(decodeURIComponent(this.getAttribute('data-user')));
                        applyEmployeeData(data);
                        closeDropdown();
                    } catch (e) {
                        console.error('JSON parse error', e);
                    }
                });
            });
        }

        function applyEmployeeData(data) {
            if (empInput) empInput.value = data.employee_id || data.user_code || '';
            if (document.getElementById('reg_first_name')) document.getElementById('reg_first_name').value = data.first_name || '';
            if (document.getElementById('reg_last_name')) document.getElementById('reg_last_name').value = data.last_name || '';
            if (document.getElementById('reg_nickname') && data.nickname) document.getElementById('reg_nickname').value = data.nickname;

            // Set Gender
            var genderEl = document.getElementById('reg_gender');
            if (genderEl && data.gender) {
                genderEl.value = data.gender;
            }

            // Set Title
            var titleEl = document.getElementById('reg_title');
            if (titleEl && data.title) {
                for (var i = 0; i < titleEl.options.length; i++) {
                    if (titleEl.options[i].value === data.title ||
                        (data.title === 'Mr.' && titleEl.options[i].value === 'นาย') ||
                        (data.title === 'Mrs.' && titleEl.options[i].value === 'นาง') ||
                        (data.title === 'Ms.' && titleEl.options[i].value === 'นางสาว')) {
                        titleEl.selectedIndex = i;
                        break;
                    }
                }
            }

            // Set Hotel
            var hotelEl = document.getElementById('reg_hotel_id');
            if (hotelEl && data.hotel_id) {
                hotelEl.value = data.hotel_id;
            }

            // Set Department
            var deptEl = document.getElementById('reg_department');
            if (deptEl && data.department) {
                for (var j = 0; j < deptEl.options.length; j++) {
                    if (deptEl.options[j].value.toLowerCase().includes(data.department.toLowerCase()) ||
                        data.department.toLowerCase().includes(deptEl.options[j].value.toLowerCase())) {
                        deptEl.selectedIndex = j;
                        break;
                    }
                }
            }

            // Suggested username based on first name if blank
            var uInput = document.querySelector('input[name="username"]');
            if (uInput && !uInput.value && data.first_name) {
                uInput.value = data.first_name.toLowerCase().replace(/[^a-z0-9]/g, '');
            }

            if (empStatus) empStatus.classList.remove('hidden');
        }

        function fetchEmployees(q) {
            fetch('actions/search_ex_user.php?code=' + encodeURIComponent(q || ''))
                .then(function(res) {
                    return res.json();
                })
                .then(function(json) {
                    if (json && json.success && json.data) {
                        renderDropdown(json.data, q);
                    } else {
                        renderDropdown([], q);
                    }
                })
                .catch(function(err) {
                    console.error('Error searching employee:', err);
                    renderDropdown([], q);
                });
        }

        if (empInput) {
            empInput.addEventListener('focus', function() {
                openDropdown();
            });

            empInput.addEventListener('click', function(e) {
                e.stopPropagation();
                openDropdown();
            });

            empInput.addEventListener('input', function() {
                var val = this.value.trim();
                clearTimeout(lookupTimeout);
                lookupTimeout = setTimeout(function() {
                    fetchEmployees(val);
                }, 200);
            });
        }

        if (empToggleBtn) {
            empToggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (empList.classList.contains('hidden')) {
                    openDropdown();
                } else {
                    closeDropdown();
                }
            });
        }

        document.addEventListener('click', function(e) {
            if (empWrapper && !empWrapper.contains(e.target)) {
                closeDropdown();
            }
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
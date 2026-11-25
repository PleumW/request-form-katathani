<?php

/**
 * Admin User Management Page
 * Access: Administrator only (level_id = 99)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$currentUser = getCurrentUser();
$db = getDB();

$allHotels = getExHotels();
$allDepartments = getExDepartments();
$allLevels = getExLevels();

// Search and filter parameters
$search     = trim($_GET['search'] ?? '');
$filterHotel = trim($_GET['hotel_id'] ?? '');
$filterDept  = trim($_GET['department'] ?? '');
$filterLevel = trim($_GET['level_id'] ?? '');

$query = "SELECT u.user_id AS id, u.user_id, u.user_code,
                 u.hr_employee_id AS employee_id, u.hr_employee_id, u.user_employee_id,
                 u.user_name AS first_name, u.user_surname AS last_name, u.user_nickname AS nickname,
                 TRIM(CONCAT(COALESCE(t.title_name, ''), ' ', u.user_name, ' ', u.user_surname)) AS full_name,
                 u.user_email AS email, u.user_level AS level_id, u.user_hotel AS hotel_id,
                 u.dep_id, d.dep_name AS department, d.dep_name_s,
                 t.title_name AS title,
                 (CASE WHEN u.user_gender = 2 THEN 'หญิง' ELSE 'ชาย' END) AS gender,
                 l.level_name, h.hotel_name, h.hotel_name_s, p.property_name,
                 lg.user_username AS username, lg.login_update, lg.login_token
          FROM ex_user u
          LEFT JOIN ex_title t ON u.user_title_id = t.title_id
          LEFT JOIN ex_department d ON u.dep_id = d.dep_id
          LEFT JOIN ex_level l ON u.user_level = l.level_id
          LEFT JOIN ex_hotel h ON u.user_hotel = h.hotel_id
          LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id
          LEFT JOIN ex_login lg ON u.user_code = lg.user_code
          WHERE u.user_status = 1";

$params = [];

if (!empty($search)) {
    $query .= " AND (lg.user_username LIKE ? OR u.user_name LIKE ? OR u.user_surname LIKE ? OR CONCAT(u.user_name, ' ', u.user_surname) LIKE ? OR u.user_nickname LIKE ? OR u.hr_employee_id LIKE ? OR u.user_code LIKE ? OR u.user_email LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like]);
}

if (!empty($filterHotel)) {
    $query .= " AND u.user_hotel = ?";
    $params[] = (int)$filterHotel;
}

if (!empty($filterDept)) {
    $query .= " AND (d.dep_name = ? OR u.dep_id = ?)";
    $params[] = $filterDept;
    $params[] = (int)$filterDept;
}

if (!empty($filterLevel)) {
    $query .= " AND u.user_level = ?";
    $params[] = (int)$filterLevel;
}

$query .= " GROUP BY u.user_id ORDER BY u.user_level DESC, u.user_id ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

// Statistics directly from ex_user
$totalUsers = count($usersList);
$adminCount = $db->query("SELECT COUNT(*) FROM ex_user WHERE user_level = 99 AND user_status = 1")->fetchColumn();
$managerCount = $db->query("SELECT COUNT(*) FROM ex_user WHERE user_level >= 40 AND user_level < 99 AND user_status = 1")->fetchColumn();
$staffCount = $db->query("SELECT COUNT(*) FROM ex_user WHERE user_level = 20 AND user_status = 1")->fetchColumn();
$requesterCount = $db->query("SELECT COUNT(*) FROM ex_user WHERE user_level <= 3 AND user_status = 1")->fetchColumn();

$pageTitle = lang('จัดการข้อมูลผู้ใช้งาน', 'User Management') . ' - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-6">
    <!-- Header Title & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 mb-1">
                <span><?= lang('ระบบบริหารจัดการ', 'System Management') ?></span>
                <span>/</span>
                <span class="text-sky-600 font-semibold"><?= lang('ผู้ใช้งานระบบ', 'Users') ?></span>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center space-x-2.5">
                <i class="fa-solid fa-users text-sky-600"></i>
                <span><?= lang('จัดการข้อมูลและสิทธิ์ผู้ใช้งาน', 'User Management') ?></span>
            </h1>
            <p class="text-slate-500 text-xs mt-1">
                <?= lang('ดูรายชื่อผู้ใช้งานทั้งหมด แก้ไขข้อมูลส่วนตัว สังกัด สิทธิ์การเข้าถึง และตั้งค่าบัญชี', 'View all system users, manage personal info, department, permissions, and accounts.') ?>
            </p>
        </div>

        <div class="flex items-center space-x-2.5">
            <a href="<?= url('login_logs.php') ?>"
                class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 border border-slate-300 shadow-sm">
                <i class="fa-solid fa-clock-rotate-left text-[11px] text-slate-500"></i>
                <span><?= lang('ประวัติเข้าสู่ระบบ', 'Login Logs') ?></span>
            </a>
            <a href="<?= url('register.php') ?>"
                class="px-4 py-2 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/20 transition flex items-center space-x-2">
                <i class="fa-solid fa-user-plus"></i>
                <span><?= lang('เพิ่มผู้ใช้ใหม่', 'Register User') ?></span>
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div class="text-[11px] text-slate-500 font-medium"><?= lang('ผู้ใช้ทั้งหมด', 'Total Users') ?></div>
                <div class="text-lg font-bold text-slate-800"><?= $totalUsers ?> <span class="text-xs font-normal text-slate-400"><?= lang('บัญชี', 'accounts') ?></span></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <div class="text-[11px] text-slate-500 font-medium"><?= lang('ผู้ดูแลระบบ', 'Administrators') ?></div>
                <div class="text-lg font-bold text-slate-800"><?= $adminCount ?> <span class="text-xs font-normal text-slate-400"><?= lang('บัญชี', 'accounts') ?></span></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div>
                <div class="text-[11px] text-slate-500 font-medium"><?= lang('ผู้จัดการ / หัวหน้า', 'Managers / Heads') ?></div>
                <div class="text-lg font-bold text-slate-800"><?= $managerCount ?> <span class="text-xs font-normal text-slate-400"><?= lang('บัญชี', 'accounts') ?></span></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center space-x-3.5">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-id-badge"></i>
            </div>
            <div>
                <div class="text-[11px] text-slate-500 font-medium"><?= lang('เจ้าหน้าที่ & ผู้ใช้ทั่วไป', 'Staff & Users') ?></div>
                <div class="text-lg font-bold text-slate-800"><?= $staffCount + $requesterCount ?> <span class="text-xs font-normal text-slate-400"><?= lang('บัญชี', 'accounts') ?></span></div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="users.php" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">

            <!-- Search Text -->
            <div class="sm:col-span-4">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= lang('ค้นหาชื่อ, สกุล, ชื่อเล่น, username, รหัส...', 'Search by name, nickname, username, code...') ?>"
                        class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                </div>
            </div>

            <!-- Filter Hotel -->
            <div class="sm:col-span-3">
                <select name="hotel_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    <option value="">-- <?= lang('โรงแรมทั้งหมด', 'All Hotels') ?> --</option>
                    <?php foreach ($allHotels as $h): ?>
                        <option value="<?= $h['hotel_id'] ?>" <?= ($filterHotel == $h['hotel_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['hotel_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Dept -->
            <div class="sm:col-span-2">
                <select name="department" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    <option value="">-- <?= lang('แผนกทั้งหมด', 'All Departments') ?> --</option>
                    <?php foreach ($allDepartments as $d): ?>
                        <option value="<?= htmlspecialchars($d['dep_name']) ?>" <?= ($filterDept === $d['dep_name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['dep_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Level -->
            <div class="sm:col-span-2">
                <select name="level_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    <option value="">-- <?= lang('ทุกระดับสิทธิ์', 'All Permissions') ?> --</option>
                    <?php foreach ($allLevels as $lvl): ?>
                        <option value="<?= $lvl['level_id'] ?>" <?= ($filterLevel == $lvl['level_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lvl['level_name']) ?> (<?= $lvl['level_id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Submit / Reset -->
            <div class="sm:col-span-1 flex items-center space-x-1.5">
                <button type="submit" class="w-full py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition flex items-center justify-center">
                    <i class="fa-solid fa-filter"></i>
                </button>
                <?php if (!empty($search) || !empty($filterHotel) || !empty($filterDept) || !empty($filterLevel)): ?>
                    <a href="users.php" title="<?= lang('ล้างตัวกรอง', 'Clear Filters') ?>" class="px-2.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- Users List Table & Mobile Responsive Cards Container -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- 1. Desktop View (hidden on mobile md:block) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3.5 px-4"><?= lang('ผู้ใช้งาน / ข้อมูลส่วนบุคคล', 'User / Profile') ?></th>
                        <th class="py-3.5 px-4"><?= lang('เพศ & คำนำหน้า', 'Gender & Title') ?></th>
                        <th class="py-3.5 px-4"><?= lang('รหัสพนักงาน', 'Employee ID') ?></th>
                        <th class="py-3.5 px-4"><?= lang('สังกัด & โรงแรม', 'Department & Hotel') ?></th>
                        <th class="py-3.5 px-4"><?= lang('ชื่อผู้ใช้', 'Username') ?></th>
                        <th class="py-3.5 px-4"><?= lang('สิทธิ์ในระบบ', 'Permission Level') ?></th>
                        <th class="py-3.5 px-4 text-center"><?= lang('จัดการ', 'Action') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($usersList)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <i class="fa-regular fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                                <?= lang('ไม่พบข้อมูลผู้ใช้ตามเงื่อนไขที่ค้นหา', 'No users found matching search criteria') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): ?>
                            <?php
                            $lvl = (int)($u['level_id'] ?? 2);
                            $badgeClass = match (true) {
                                $lvl === 99 => 'bg-purple-50 text-purple-700 border-purple-200',
                                $lvl >= 40  => 'bg-sky-50 text-sky-700 border-sky-200',
                                $lvl === 20 => 'bg-amber-50 text-amber-700 border-amber-200',
                                default     => 'bg-slate-50 text-slate-600 border-slate-200'
                            };

                            $dispName = !empty($u['first_name']) ? trim("{$u['first_name']} {$u['last_name']}") : $u['full_name'];
                            $dispNick = !empty($u['nickname']) ? " ({$u['nickname']})" : '';
                            $dispCode = !empty($u['user_code']) ? $u['user_code'] : ($u['employee_id'] ?? '-');
                            ?>
                            <tr class="hover:bg-slate-50/80 transition group">
                                <!-- Name & Avatar -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xs shadow-sm flex-shrink-0">
                                            <?= mb_substr($u['first_name'] ?: $u['full_name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm">
                                                <?= htmlspecialchars($dispName) ?>
                                                <span class="text-xs text-sky-600 font-semibold"><?= htmlspecialchars($dispNick) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Gender & Title -->
                                <td class="py-3.5 px-4">
                                    <div class="text-slate-700 font-medium">
                                        <?= htmlspecialchars($u['title'] ?: '-') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        <?php if (($u['gender'] ?? '') === 'หญิง'): ?>
                                            <span class="text-rose-500"><i class="fa-solid fa-venus mr-1"></i><?= lang('หญิง', 'Female') ?></span>
                                        <?php else: ?>
                                            <span class="text-sky-600"><i class="fa-solid fa-mars mr-1"></i><?= lang('ชาย', 'Male') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Employee ID / Code -->
                                <td class="py-3.5 px-4">
                                    <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                                        <?= htmlspecialchars($dispCode) ?>
                                    </span>
                                </td>

                                <!-- Hotel & Department -->
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800">
                                        <?= htmlspecialchars($u['hotel_name'] ?? 'Katathani') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        <?= htmlspecialchars($u['department'] ?: '-') ?>
                                        <?php if (!empty($u['position'])): ?>
                                            • <span class="text-slate-400"><?= htmlspecialchars($u['position']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Username -->
                                <td class="py-3.5 px-4 font-mono font-semibold text-slate-700">
                                    <?= htmlspecialchars($u['username'] ?: ($u['user_code'] ?? '-')) ?>
                                </td>

                                <!-- Level / Permission -->
                                <td class="py-3.5 px-4">
                                    <div class="flex flex-col space-y-1">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $badgeClass ?> w-fit">
                                            <?= htmlspecialchars($u['level_name'] ?? 'User') ?>
                                            <span class="text-[10px] opacity-70 ml-1">(<?= $lvl ?>)</span>
                                        </span>
                                        <?php if ($lvl >= 40 && $lvl < 99): ?>
                                            <?php
                                            $mRole = determineRoleFromUser($u);
                                            if ($mRole === 'dept_head') {
                                                echo '<span class="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 w-fit"><i class="fa-solid fa-building-user text-[9px]"></i><span>' . lang('หัวหน้าแผนก', 'Dept Head') . '</span></span>';
                                            } elseif ($mRole === 'hotel_manager') {
                                                echo '<span class="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-orange-50 text-orange-700 border border-orange-200 w-fit"><i class="fa-solid fa-hotel text-[9px]"></i><span>' . lang('ผู้จัดการโรงแรม', 'Hotel Manager') . '</span></span>';
                                            } elseif ($mRole === 'it_manager') {
                                                echo '<span class="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 w-fit"><i class="fa-solid fa-laptop-code text-[9px]"></i><span>' . lang('ผู้จัดการไอที', 'IT Manager') . '</span></span>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="py-3.5 px-4 text-center">
                                    <a href="<?= url('edit_user.php?id=' . $u['id']) ?>"
                                        class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-sky-50 hover:bg-sky-600 text-sky-700 hover:text-white rounded-lg font-bold text-xs border border-sky-200 hover:border-transparent transition shadow-xs">
                                        <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                        <span><?= lang('แก้ไข', 'Edit') ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Mobile Responsive Card List (md:hidden - ไม่ต้องเลื่อนซ้ายขวา) -->
        <div class="md:hidden divide-y divide-slate-100">
            <?php if (empty($usersList)): ?>
                <div class="py-12 px-4 text-center text-slate-400">
                    <i class="fa-regular fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                    ไม่พบข้อมูลผู้ใช้ตามเงื่อนไขที่ค้นหา
                </div>
            <?php else: ?>
                <?php foreach ($usersList as $u): ?>
                    <?php
                    $lvl = (int)($u['level_id'] ?? 2);
                    $badgeClass = match (true) {
                        $lvl === 99 => 'bg-purple-50 text-purple-700 border-purple-200',
                        $lvl >= 40  => 'bg-sky-50 text-sky-700 border-sky-200',
                        $lvl === 20 => 'bg-amber-50 text-amber-700 border-amber-200',
                        default     => 'bg-slate-50 text-slate-600 border-slate-200'
                    };

                    $dispName = !empty($u['first_name']) ? trim("{$u['first_name']} {$u['last_name']}") : $u['full_name'];
                    $dispNick = !empty($u['nickname']) ? " ({$u['nickname']})" : '';
                    $dispCode = !empty($u['user_code']) ? $u['user_code'] : ($u['employee_id'] ?? '-');
                    ?>
                    <div class="p-4 hover:bg-slate-50/60 transition bg-white space-y-3">
                        <!-- Top Bar: Level Badge & User Code -->
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                                Code: <?= htmlspecialchars($dispCode) ?>
                            </span>
                            <div class="flex items-center space-x-1.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $badgeClass ?>">
                                    <?= htmlspecialchars($u['level_name'] ?? 'User') ?>
                                </span>
                                <?php if ($lvl >= 40 && $lvl < 99): ?>
                                    <?php
                                    $mRole = determineRoleFromUser($u);
                                    if ($mRole === 'dept_head') {
                                        echo '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 border border-sky-200">Head</span>';
                                    } elseif ($mRole === 'hotel_manager') {
                                        echo '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-orange-50 text-orange-700 border border-orange-200">Hotel</span>';
                                    } elseif ($mRole === 'it_manager') {
                                        echo '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">IT</span>';
                                    }
                                    ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Main Row: ชื่อ & ตำแหน่ง พร้อมปุ่มดูเพิ่มเติม -->
                        <div class="flex items-center justify-between gap-3 bg-slate-50/80 p-3 rounded-xl border border-slate-100">
                            <div class="flex items-center space-x-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xs shadow-xs flex-shrink-0">
                                    <?= mb_substr($u['first_name'] ?: $u['full_name'], 0, 1) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 text-sm truncate">
                                        <?= htmlspecialchars($dispName) ?>
                                        <span class="text-xs text-sky-600 font-semibold"><?= htmlspecialchars($dispNick) ?></span>
                                    </div>
                                    <div class="text-xs text-slate-500 truncate flex items-center space-x-1 mt-0.5">
                                        <i class="fa-solid fa-briefcase text-[10px] text-slate-400"></i>
                                        <span><?= htmlspecialchars($u['position'] ?: lang('ไม่ระบุตำแหน่ง', 'Unspecified Position')) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- ปุ่มดูเพิ่มเติม -->
                            <button type="button" onclick="toggleUserMobile(<?= $u['id'] ?>)"
                                class="px-2.5 py-1.5 bg-white hover:bg-sky-50 text-slate-700 hover:text-sky-700 border border-slate-200 rounded-lg text-[11px] font-semibold transition flex items-center space-x-1 flex-shrink-0 shadow-2xs cursor-pointer">
                                <span id="userToggleText_<?= $u['id'] ?>"><?= lang('ดูเพิ่มเติม', 'Details') ?></span>
                                <i id="userToggleIcon_<?= $u['id'] ?>" class="fa-solid fa-chevron-down text-[9px] transition-transform duration-200 text-slate-400"></i>
                            </button>
                        </div>

                        <!-- Collapsible Detail Section (รายละเอียดหลักของ user) -->
                        <div id="userMobileDetail_<?= $u['id'] ?>" class="hidden pt-1 space-y-3">
                            <div class="grid grid-cols-2 gap-2.5 p-3 rounded-xl bg-slate-50/90 border border-slate-200 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-medium"><?= lang('เพศ & คำนำหน้า:', 'Gender & Title:') ?></span>
                                    <span class="text-slate-800 text-xs font-medium">
                                        <?= htmlspecialchars($u['title'] ?: '-') ?> (<?= htmlspecialchars($u['gender'] ?: '-') ?>)
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-medium"><?= lang('ชื่อผู้ใช้:', 'Username:') ?></span>
                                    <span class="font-mono font-bold text-slate-800 text-xs"><?= htmlspecialchars($u['username'] ?: ($u['user_code'] ?? '-')) ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-medium"><?= lang('โรงแรม / สังกัด:', 'Hotel / Property:') ?></span>
                                    <span class="text-slate-700 text-xs"><?= htmlspecialchars($u['hotel_name'] ?? 'Katathani') ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-medium"><?= lang('แผนก:', 'Department:') ?></span>
                                    <span class="font-semibold text-slate-800 text-xs"><?= htmlspecialchars($u['department'] ?: '-') ?></span>
                                </div>
                                <?php if (!empty($u['email'])): ?>
                                    <div class="col-span-2 pt-1 border-t border-slate-200 flex items-center space-x-1.5">
                                        <span class="text-[10px] text-slate-400"><?= lang('อีเมล:', 'Email:') ?></span>
                                        <span class="text-slate-700 text-xs"><?= htmlspecialchars($u['email']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ปุ่มแก้ไขข้อมูล -->
                            <div class="pt-1">
                                <a href="<?= url('edit_user.php?id=' . $u['id']) ?>"
                                    class="w-full py-2 px-3 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold text-xs shadow-sm flex items-center justify-center space-x-1.5 transition">
                                    <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                    <span><?= lang('แก้ไขข้อมูลและสิทธิ์ผู้ใช้งาน', 'Edit User & Permissions') ?></span>
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    function toggleUserMobile(id) {
        var detail = document.getElementById('userMobileDetail_' + id);
        var icon = document.getElementById('userToggleIcon_' + id);
        var text = document.getElementById('userToggleText_' + id);
        if (!detail) return;

        if (detail.classList.contains('hidden')) {
            detail.classList.remove('hidden');
            if (icon) icon.style.transform = 'rotate(180deg)';
            if (text) text.textContent = 'ย่อข้อมูล';
        } else {
            detail.classList.add('hidden');
            if (icon) icon.style.transform = 'rotate(0deg)';
            if (text) text.textContent = 'ดูเพิ่มเติม';
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
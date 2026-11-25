<?php

/**
 * Admin Login Activity Logs Page
 * Access: Administrator only (level_id = 99)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$currentUser = getCurrentUser();
$db = getDB();

// Search and filter parameters
$today      = date('Y-m-d');
$view       = trim($_GET['view'] ?? ''); // 'today' or 'all'
$search     = trim($_GET['search'] ?? '');
$status     = trim($_GET['status'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

$logFile = __DIR__ . '/logs/login.log';
$allLogs = [];
if (file_exists($logFile)) {
    $fileLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($fileLines as $line) {
        $data = json_decode($line, true);
        if (is_array($data)) {
            $allLogs[] = $data;
        }
    }
}

// Global counts
$totalAllLogs = count($allLogs);
$todayLogsCount = 0;
foreach ($allLogs as $l) {
    if (substr($l['login_at'] ?? '', 0, 10) === $today) {
        $todayLogsCount++;
    }
}

// Default to today if no specific date range is searched and view is not explicitly 'all'
$isCustomDate = (!empty($dateFrom) || !empty($dateTo));
if (!$isCustomDate && $view !== 'all') {
    $view = 'today';
    $dateFrom = $today;
    $dateTo = $today;
} elseif ($view === 'all') {
    // Clear dateFrom/dateTo if user explicitly clicked view=all without custom inputs
    if (!$isCustomDate) {
        $dateFrom = '';
        $dateTo = '';
    }
}

// Filter Logs
$filteredLogs = [];
foreach ($allLogs as $l) {
    if (!empty($search)) {
        $haystack = ($l['username'] ?? '') . ' ' . ($l['full_name'] ?? '') . ' ' . ($l['user_code'] ?? '') . ' ' . ($l['ip_address'] ?? '') . ' ' . ($l['department'] ?? '');
        if (stripos($haystack, $search) === false) {
            continue;
        }
    }
    if (!empty($status) && in_array($status, ['success', 'failed'])) {
        if (($l['status'] ?? '') !== $status) {
            continue;
        }
    }
    $loginDate = substr($l['login_at'] ?? '', 0, 10);
    if (!empty($dateFrom) && $loginDate < $dateFrom) {
        continue;
    }
    if (!empty($dateTo) && $loginDate > $dateTo) {
        continue;
    }
    $filteredLogs[] = $l;
}

// Metrics for current filtered view
$currentCount = count($filteredLogs);
$successLogs = 0;
$failedLogs = 0;
$uniqueUsersMap = [];

foreach ($filteredLogs as $l) {
    if (($l['status'] ?? '') === 'success') {
        $successLogs++;
        if (!empty($l['username'])) {
            $uniqueUsersMap[$l['username']] = true;
        }
    } else {
        $failedLogs++;
    }
}
$uniqueUsers = count($uniqueUsersMap);

// Sort latest first (DESC)
$filteredLogs = array_reverse($filteredLogs);
$logs = $filteredLogs;

$pageTitle = lang('ประวัติการเข้าสู่ระบบ', 'Login Logs') . ' - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="pb-16 max-w-7xl mx-auto">

    <!-- Top Greeting Banner & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center space-x-2.5">
                <i class="fa-solid fa-clock-rotate-left text-sky-600"></i>
                <span><?= lang('ประวัติการเข้าสู่ระบบ', 'Login Logs') ?></span>
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Toggle: เฉพาะวันนี้ (Today) -->
            <a href="login_logs.php?view=today"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?= ($view === 'today') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/20' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 shadow-2xs' ?>">
                <i class="fa-solid fa-calendar-day text-[11px]"></i>
                <span><?= lang('เฉพาะวันนี้', 'Today Only') ?></span>
                <span class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] <?= ($view === 'today') ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?>">
                    <?= number_format($todayLogsCount) ?>
                </span>
            </a>

            <!-- Toggle: ดูทั้งหมด (View All) -->
            <a href="login_logs.php?view=all"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?= ($view === 'all') ? 'bg-sky-600 text-white shadow-md shadow-sky-600/20' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 shadow-2xs' ?>">
                <i class="fa-solid fa-list-ul text-[11px]"></i>
                <span><?= lang('ดูทั้งหมด', 'View All') ?></span>
                <span class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] <?= ($view === 'all') ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?>">
                    <?= number_format($totalAllLogs) ?>
                </span>
            </a>
        </div>
    </div>

    <!-- Active View Mode Indicator Banner -->
    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
        <div class="flex items-center space-x-2">
            <span class="text-slate-500"><?= lang('พบ ' . number_format($currentCount) . ' รายการ', 'Found ' . number_format($currentCount) . ' records') ?></span>
        </div>

        <div>
            <?php if ($view === 'today'): ?>
                <a href="login_logs.php?view=all" class="text-sky-600 hover:text-sky-700 font-bold flex items-center space-x-1">
                    <span><?= lang('ดูประวัติทั้งหมด', 'View All Logs') ?></span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            <?php else: ?>
                <a href="login_logs.php?view=today" class="text-sky-600 hover:text-sky-700 font-bold flex items-center space-x-1">
                    <i class="fa-solid fa-calendar-day text-[10px]"></i>
                    <span><?= lang('กลับไปดูเฉพาะวันนี้', 'Back to Today Only') ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metric Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 font-medium">
                    <?= ($view === 'today') ? lang('เข้าสู่ระบบวันนี้', 'Today Logins') : lang('การเข้าสู่ระบบทั้งหมด', 'Total Logins') ?>
                </span>
                <div class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($currentCount) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                    <?= ($view === 'today') ? lang('จากประวัติทั้งหมด ' . number_format($totalAllLogs) . ' ครั้ง', 'Out of ' . number_format($totalAllLogs) . ' total') : lang('ทั้งหมดทุกช่วงเวลา', 'All time') ?>
                </div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>
        </div>

        <!-- Successful -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-emerald-700 font-medium"><?= lang('เข้าสู่ระบบสำเร็จ', 'Successful Logins') ?></span>
                <div class="text-2xl font-bold text-emerald-600 mt-1"><?= number_format($successLogs) ?></div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <!-- Failed -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-rose-700 font-medium"><?= lang('เข้าสู่ระบบไม่สำเร็จ', 'Failed Logins') ?></span>
                <div class="text-2xl font-bold text-rose-600 mt-1"><?= number_format($failedLogs) ?></div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
        </div>

        <!-- Unique Users -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-sky-700 font-medium"><?= lang('ผู้ใช้งานที่เข้าสู่ระบบ', 'Active Users') ?></span>
                <div class="text-2xl font-bold text-sky-600 mt-1"><?= number_format($uniqueUsers) ?></div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-sm mb-6">
        <form method="GET" action="login_logs.php" class="space-y-3">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">

                <!-- Search Input -->
                <div class="sm:col-span-6 relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= lang('ค้นหาตามชื่อผู้ใช้, ชื่อ-นามสกุล, รหัส, IP...', 'Search by username, name, code, IP...') ?>"
                        class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>

                <!-- Status Filter -->
                <div class="sm:col-span-3">
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <option value=""><?= lang('ทุกสถานะ', 'All Statuses') ?></option>
                        <option value="success" <?= $status === 'success' ? 'selected' : '' ?>><?= lang('เข้าสู่ระบบสำเร็จ', 'Success') ?></option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>><?= lang('ไม่สำเร็จ', 'Failed') ?></option>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="sm:col-span-3 flex items-center space-x-1.5">
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                        class="w-1/2 px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500" title="<?= lang('ตั้งแต่วันที่', 'From Date') ?>">
                    <span class="text-slate-400 text-xs">-</span>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                        class="w-1/2 px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500" title="<?= lang('ถึงวันที่', 'To Date') ?>">
                </div>

            </div>

            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="submit" class="py-2 px-4 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1.5 shadow-sm">
                    <i class="fa-solid fa-filter text-[10px]"></i>
                    <span><?= lang('ค้นหา / กรองข้อมูล', 'Search / Filter') ?></span>
                </button>
                <?php if (!empty($search) || !empty($status) || $isCustomDate): ?>
                    <a href="login_logs.php?view=<?= $view === 'all' ? 'all' : 'today' ?>" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition flex items-center space-x-1">
                        <i class="fa-solid fa-rotate-left text-[11px]"></i>
                        <span><?= lang('ล้างตัวกรอง', 'Clear Filters') ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                        <th class="py-3.5 px-4 sm:px-6"><?= lang('วัน-เวลา', 'Timestamp') ?></th>
                        <th class="py-3.5 px-4"><?= lang('ชื่อผู้ใช้ / รหัส', 'Username / Code') ?></th>
                        <th class="py-3.5 px-4"><?= lang('ชื่อ-นามสกุล', 'Full Name') ?></th>
                        <th class="py-3.5 px-4"><?= lang('แผนก / บทบาท', 'Department / Role') ?></th>
                        <th class="py-3.5 px-4">IP Address</th>
                        <th class="py-3.5 px-4"><?= lang('สถานะ', 'Status') ?></th>
                        <th class="py-3.5 px-4 sm:px-6"><?= lang('อุปกรณ์ / User Agent', 'Device / User Agent') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <!-- Timestamp -->
                                <td class="py-3.5 px-4 sm:px-6 font-mono text-slate-600 whitespace-nowrap">
                                    <div class="font-bold text-slate-800"><?= date('d/m/Y', strtotime($log['login_at'])) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= date('H:i:s', strtotime($log['login_at'])) ?><?= isThai() ? ' น.' : '' ?></div>
                                </td>

                                <!-- Username & Code -->
                                <td class="py-3.5 px-4">
                                    <div class="font-bold font-mono text-sky-700"><?= htmlspecialchars($log['username']) ?></div>
                                    <?php if (!empty($log['user_code'])): ?>
                                        <div class="text-[10px] text-slate-400 font-mono">Code: <?= htmlspecialchars($log['user_code']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Full Name -->
                                <td class="py-3.5 px-4 font-medium text-slate-800">
                                    <?= htmlspecialchars($log['full_name'] ?: '-') ?>
                                </td>

                                <!-- Department & Role -->
                                <td class="py-3.5 px-4">
                                    <div class="text-slate-700"><?= htmlspecialchars($log['department'] ?: '-') ?></div>
                                    <?php if (!empty($log['role'])): ?>
                                        <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">
                                            <?= htmlspecialchars($log['role']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- IP Address -->
                                <td class="py-3.5 px-4 font-mono text-slate-600">
                                    <span class="px-2 py-0.5 bg-slate-100 rounded text-[11px] border border-slate-200">
                                        <?= htmlspecialchars($log['ip_address']) ?>
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <i class="fa-solid fa-check-circle text-[9px] mr-1 text-emerald-600"></i>
                                            <?= lang('สำเร็จ', 'Success') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200" title="<?= htmlspecialchars($log['failure_reason'] ?? '') ?>">
                                            <i class="fa-solid fa-circle-xmark text-[9px] mr-1 text-rose-600"></i>
                                            <?= lang('ล้มเหลว', 'Failed') ?>
                                        </span>
                                        <?php if (!empty($log['failure_reason'])): ?>
                                            <div class="text-[10px] text-rose-500 mt-0.5 max-w-[120px] truncate" title="<?= htmlspecialchars($log['failure_reason']) ?>">
                                                <?= htmlspecialchars($log['failure_reason']) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <!-- User Agent -->
                                <td class="py-3.5 px-4 sm:px-6 text-slate-500 max-w-[220px]">
                                    <div class="truncate text-[11px] font-mono" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                        <?= htmlspecialchars($log['user_agent'] ?: '-') ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <?php if ($view === 'today'): ?>
                                    <i class="fa-solid fa-calendar-xmark text-3xl mb-2 text-slate-300 block"></i>
                                    <span class="font-medium"><?= lang('ไม่พบประวัติการเข้าสู่ระบบในวันนี้', 'No login records found for today') ?></span>
                                    <div class="mt-2">
                                        <a href="login_logs.php?view=all" class="text-sky-600 hover:underline font-bold text-xs inline-flex items-center space-x-1">
                                            <span><?= lang('คลิกเพื่อดูประวัติการเข้าสู่ระบบทั้งหมด', 'Click to view all login activity') ?></span>
                                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <i class="fa-regular fa-folder-open text-3xl mb-2 block"></i>
                                    <span><?= lang('ไม่พบประวัติการเข้าสู่ระบบตามเงื่อนไขที่ระบุ', 'No login activity matches the criteria') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
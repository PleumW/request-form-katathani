<?php

/**
 * Clean & Executive Approvers Dashboard
 * Designed for quick comprehension with minimal charts and clear approval workflow tracking
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$currentUser = getCurrentUser();
$db = getDB();
$userLevel = (int)($currentUser['level_id'] ?? 1);
$userRole = determineRoleFromUser($currentUser);

// Filter by Property / Hotel if selected
$selectedHotelId = !empty($_GET['hotel_id']) ? (int)$_GET['hotel_id'] : 0;
$allHotels = getExHotels();

$whereHotel = '';
$params = [];
if ($selectedHotelId > 0) {
    $whereHotel = ' AND hotel_id = ?';
    $params[] = $selectedHotelId;
}

// 1. Overall Key Metrics
$totalRequests = $db->prepare("SELECT COUNT(*) FROM requests WHERE 1=1" . $whereHotel);
$totalRequests->execute($params);
$totalCount = (int)$totalRequests->fetchColumn();

// Count by Status
$statusSql = "SELECT status, COUNT(*) as cnt FROM requests WHERE 1=1" . $whereHotel . " GROUP BY status";
$stmt = $db->prepare($statusSql);
$stmt->execute($params);
$rawStatusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pendingDeptHead = (int)($rawStatusCounts['pending_dept_head'] ?? 0);
$pendingHotelMgr = (int)($rawStatusCounts['pending_hotel_manager'] ?? 0);
$pendingITAction = (int)($rawStatusCounts['pending_it_action'] ?? 0);
$pendingITMgr   = (int)($rawStatusCounts['pending_it_manager'] ?? 0);
$pendingHR      = (int)($rawStatusCounts['pending_hr'] ?? 0);
$completedCount = (int)($rawStatusCounts['completed'] ?? 0);
$rejectedCount  = (int)($rawStatusCounts['rejected'] ?? 0);

$inProgressCount = $pendingDeptHead + $pendingHotelMgr + $pendingITAction + $pendingITMgr + $pendingHR;
$completionRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0;

// 2. Pending count specifically for THIS current user
$myPendingCount = 0;
$myPendingLabel = lang('รอฉันอนุมัติ', 'Pending My Approval');
$myPendingStatus = '';

if ($userRole === 'dept_head') {
    $myPendingCount = $pendingDeptHead;
    $myPendingLabel = lang('รอหัวหน้าแผนกอนุมัติ', 'Pending Dept Head Approval');
    $myPendingStatus = 'pending_dept_head';
} elseif ($userRole === 'hotel_manager') {
    $myPendingCount = $pendingHotelMgr;
    $myPendingLabel = lang('รอผู้จัดการโรงแรมอนุมัติ', 'Pending Hotel Manager Approval');
    $myPendingStatus = 'pending_hotel_manager';
} elseif ($userRole === 'it_staff') {
    $myPendingCount = $pendingITAction;
    $myPendingLabel = lang('รอฝ่ายไอทีกำหนดวันเริ่มงานและลงนาม', 'Pending IT Staff Provisioning');
    $myPendingStatus = 'pending_it_action';
} elseif ($userRole === 'it_manager') {
    $myPendingCount = $pendingITMgr;
    $myPendingLabel = lang('รอผู้จัดการไอทีอนุมัติขั้นสุดท้าย', 'Pending IT Manager Final Approval');
    $myPendingStatus = 'pending_it_manager';
} elseif ($userRole === 'admin' || $userLevel === 99) {
    $myPendingCount = $inProgressCount;
    $myPendingLabel = lang('รอการอนุมัติทั้งหมดในระบบ', 'All Pending Approvals in System');
    $myPendingStatus = 'all_pending';
} else {
    // Normal Requester
    $myPendingLabel = lang('คำขอของฉันที่อยู่ระหว่างดำเนินการ', 'My In-Progress Requests');
    $userId = (int)($currentUser['id'] ?? 0);

    $stmtMy = $db->prepare("SELECT COUNT(*) FROM requests WHERE requester_id = ?" . $whereHotel . " AND status NOT IN ('completed', 'rejected')");
    $stmtMy->execute(array_merge([$userId], $params));
    $myPendingCount = (int)$stmtMy->fetchColumn();
    $myPendingStatus = 'my_pending';
}

// 3. Pending requests list for this approver / user
$pendingListSql = "SELECT r.*, h.hotel_name_s, p.property_name 
                   FROM requests r
                   LEFT JOIN ex_hotel h ON r.hotel_id = h.hotel_id
                   LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id
                   WHERE 1=1" . $whereHotel;

$listParams = $params;
if ($myPendingStatus === 'pending_dept_head') {
    $pendingListSql .= " AND r.status = 'pending_dept_head'";
} elseif ($myPendingStatus === 'pending_hotel_manager') {
    $pendingListSql .= " AND r.status = 'pending_hotel_manager'";
} elseif ($myPendingStatus === 'pending_it_action') {
    $pendingListSql .= " AND r.status = 'pending_it_action'";
} elseif ($myPendingStatus === 'pending_it_manager') {
    $pendingListSql .= " AND r.status = 'pending_it_manager'";
} elseif ($myPendingStatus === 'my_pending') {
    $pendingListSql .= " AND r.requester_id = ? AND r.status NOT IN ('completed', 'rejected')";
    $listParams = array_merge($params, [(int)($currentUser['id'] ?? 0)]);
} elseif ($myPendingStatus === 'all_pending') {
    $pendingListSql .= " AND r.status IN ('pending_dept_head', 'pending_hotel_manager', 'pending_it_action', 'pending_it_manager')";
} else {
    $pendingListSql .= " AND r.status NOT IN ('completed', 'rejected')";
}
$pendingListSql .= " ORDER BY r.id DESC LIMIT 10";

$stmtPending = $db->prepare($pendingListSql);
$stmtPending->execute($listParams);
$pendingRequests = $stmtPending->fetchAll();

$todayDate = date('Y-m-d');

$pageTitle = lang('ภาพรวมระบบ', 'Dashboard') . ' - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<!-- Chart.js CDN for clean single Donut -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="max-w-7xl mx-auto pb-16 space-y-6">

    <!-- Top Greeting Banner & Property Filter -->
    <div class="bg-gradient-to-r from-slate-800 via-slate-600 to-sky-800 text-white rounded-3xl p-6 sm:p-7 shadow-xl border border-slate-700/60 relative overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 relative z-10">
            <div class="space-y-1.5">
                <div class="flex items-center space-x-2 text-xs text-sky-300 font-medium">

                    <span>Katathani Collection </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                    <?= lang('ภาพรวมและสถานะคำขอ', 'Dashboard & Request Overview') ?>
                </h1>
                <p class="text-xs sm:text-sm text-slate-300">
                    <?= lang('ยินดีต้อนรับคุณ', 'Welcome') ?> <strong class="text-white"><?= htmlspecialchars($currentUser['full_name']) ?></strong>

                </p>
            </div>

        </div>
    </div>

    <!-- Alert Banner: Action Required For This Approver -->
    <?php if ($myPendingCount > 0): ?>
        <div class="bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-white border-l-4 border-amber-500 bg-white rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center space-x-3.5">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-lg font-bold shrink-0">
                    <i class="fa-solid fa-bell animate-bounce"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">
                        <?= ($userRole === 'requester') ? lang('คุณมีคำขอที่อยู่ระหว่างดำเนินการ', 'You have in-progress requests') : lang('มีคำขอที่รอลงนามอนุมัติ', 'Requests awaiting your approval') ?>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <?= lang('คำขอ', 'Total') ?> <strong class="text-amber-600 font-bold"><?= number_format($myPendingCount) ?> <?= lang('รายการ', 'requests') ?></strong>

                    </p>
                </div>
            </div>
            <a href="#pendingTableSection" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center justify-center space-x-1.5 shrink-0">
                <span><?= ($userRole === 'requester') ? lang('ดูรายการคำขอ', 'View Requests') : lang('ลงนามอนุมัติทันที', 'Sign Approval Now') ?></span>
                <i class="fa-solid fa-arrow-down text-[10px]"></i>
            </a>
        </div>
    <?php endif; ?>

    <!-- === SECTION 1: Summary KPI Cards === -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5 sm:gap-4">

        <!-- 1. Total Requests -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-2xs hover:border-sky-300 transition group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-500"><?= lang('คำขอทั้งหมด', 'Total Requests') ?></span>
                <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-xs group-hover:bg-slate-900 group-hover:text-white transition">
                    <i class="fa-solid fa-file-shield"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900"><?= number_format($totalCount) ?></div>
            <p class="text-[11px] text-slate-400 mt-0.5"><?= lang('คำขอทั้งหมดในระบบ', 'All requests in system') ?></p>
        </div>

        <!-- 2. Pending My Action -->
        <div class="bg-white rounded-2xl border <?= $myPendingCount > 0 ? 'border-amber-300 bg-amber-50/20' : 'border-slate-200' ?> p-4 sm:p-5 shadow-2xs hover:border-amber-400 transition group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-amber-700">
                    <?= ($userRole === 'requester') ? lang('คำขอของฉันที่รอ', 'My Pending') : lang('รอลงนาม', 'Awaiting Signature') ?>
                </span>
                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xs group-hover:bg-amber-500 group-hover:text-white transition">
                    <i class="fa-solid fa-pen-nib"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-amber-600"><?= number_format($myPendingCount) ?></div>
            <p class="text-[11px] text-slate-500 mt-0.5 truncate" title="<?= htmlspecialchars($myPendingLabel) ?>"><?= htmlspecialchars($myPendingLabel) ?></p>
        </div>

        <!-- 3. In Progress (Total Pipeline) -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-2xs hover:border-indigo-300 transition group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-indigo-700"><?= lang('อยู่ระหว่างดำเนินการ', 'In Progress') ?></span>
                <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs group-hover:bg-indigo-600 group-hover:text-white transition">
                    <i class="fa-solid fa-spinner"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-indigo-600"><?= number_format($inProgressCount) ?></div>
            <p class="text-[11px] text-slate-400 mt-0.5"><?= lang('ทุกขั้นตอนการอนุมัติ', 'All workflow stages') ?></p>
        </div>

        <!-- 4. Completed -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-2xs hover:border-emerald-300 transition group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-emerald-700"><?= lang('เสร็จสมบูรณ์', 'Completed') ?></span>
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs group-hover:bg-emerald-600 group-hover:text-white transition">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600"><?= number_format($completedCount) ?></div>
            <p class="text-[11px] text-emerald-600 font-semibold mt-0.5"><?= lang('สำเร็จ', 'Success') ?> <?= $completionRate ?>%</p>
        </div>

        <!-- 5. Rejected -->
        <div class="col-span-2 lg:col-span-1 bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-2xs hover:border-rose-300 transition group">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-rose-700"><?= lang('ไม่อนุมัติ / ปฏิเสธ', 'Rejected') ?></span>
                <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-xs group-hover:bg-rose-600 group-hover:text-white transition">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-rose-600"><?= number_format($rejectedCount) ?></div>
            <p class="text-[11px] text-slate-400 mt-0.5"><?= lang('คำขอที่ไม่ผ่านอนุมัติ', 'Rejected requests') ?></p>
        </div>

    </div>

    <!-- === SECTION 2: Clean Visual Overview & Approval Workflow Pipeline === -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">

        <!-- Column 1: Clean Single Status Overview Donut Chart (5 cols) -->
        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <i class="fa-solid fa-chart-pie text-sky-600"></i>
                        <span><?= lang('ภาพรวมคำขอ', 'Request Overview') ?></span>
                    </h3>
                </div>
                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                    <?= lang('สำเร็จ', 'Success') ?> <?= $completionRate ?>%
                </span>
            </div>

            <!-- Single Donut Chart with Centered Metric -->
            <div class="relative flex items-center justify-center my-4" style="min-height: 200px;">
                <canvas id="statusChart" class="max-h-[200px]"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-extrabold text-slate-800"><?= $completionRate ?>%</span>
                    <span class="text-[10px] text-slate-400 font-medium"><?= lang('สำเร็จเรียบร้อย', 'Completed') ?></span>
                </div>
            </div>

            <!-- Clean Legend & Totals -->
            <div class="grid grid-cols-3 gap-2 pt-3 border-t border-slate-100 text-center">
                <div class="p-2 rounded-xl bg-emerald-50/60 border border-emerald-100">
                    <span class="text-[10px] text-emerald-700 block font-medium"><?= lang('เสร็จสมบูรณ์', 'Completed') ?></span>
                    <span class="text-base font-bold text-emerald-800"><?= number_format($completedCount) ?></span>
                </div>
                <div class="p-2 rounded-xl bg-sky-50/60 border border-sky-100">
                    <span class="text-[10px] text-sky-700 block font-medium"><?= lang('รอดำเนินการ', 'In Progress') ?></span>
                    <span class="text-base font-bold text-sky-800"><?= number_format($inProgressCount) ?></span>
                </div>
                <div class="p-2 rounded-xl bg-rose-50/60 border border-rose-100">
                    <span class="text-[10px] text-rose-700 block font-medium"><?= lang('ไม่อนุมัติ', 'Rejected') ?></span>
                    <span class="text-base font-bold text-rose-800"><?= number_format($rejectedCount) ?></span>
                </div>
            </div>
        </div>

        <!-- Column 2: Approval Workflow Pipeline Tracker (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <span><?= lang('เอกสารที่ค้างอยู่', 'Pending Documents') ?></span>
                    </h3>
                </div>
                <span class="text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-xl">
                    <?= lang('ค้างรวม ', 'Pending Total ') ?><?= number_format($inProgressCount) ?> <?= lang('รายการ', 'items') ?>
                </span>
            </div>

            <!-- Workflow Pipeline Steps -->
            <div class="space-y-2.5 my-3">

                <!-- Step 1: Dept Head -->
                <?php $isMyStep1 = ($userRole === 'dept_head'); ?>
                <div class="p-3 rounded-xl border <?= $isMyStep1 ? 'border-amber-400 bg-amber-50/30 ring-1 ring-amber-300' : 'border-slate-200 bg-slate-50/50' ?> flex items-center justify-between gap-3 transition">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center text-xs font-bold shrink-0">
                            1
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold text-slate-900">Department Head</span>
                                <?php if ($isMyStep1): ?>
                                    <span class="bg-amber-500 text-white text-[9px] font-bold px-1.5 py-0.2 rounded-md animate-pulse"><?= lang('งานของคุณ', 'Your Task') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-sm font-bold <?= $pendingDeptHead > 0 ? 'text-amber-600' : 'text-slate-400' ?>">
                            <?= number_format($pendingDeptHead) ?>
                        </span>
                        <span class="text-[10px] text-slate-400 ml-0.5"><?= lang('รายการ', 'items') ?></span>
                    </div>
                </div>

                <!-- Step 2: Hotel Manager -->
                <?php $isMyStep2 = ($userRole === 'hotel_manager'); ?>
                <div class="p-3 rounded-xl border <?= $isMyStep2 ? 'border-orange-400 bg-orange-50/30 ring-1 ring-orange-300' : 'border-slate-200 bg-slate-50/50' ?> flex items-center justify-between gap-3 transition">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-800 flex items-center justify-center text-xs font-bold shrink-0">
                            2
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold text-slate-900">Hotel Manager</span>
                                <?php if ($isMyStep2): ?>
                                    <span class="bg-orange-500 text-white text-[9px] font-bold px-1.5 py-0.2 rounded-md animate-pulse"><?= lang('งานของคุณ', 'Your Task') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-sm font-bold <?= $pendingHotelMgr > 0 ? 'text-orange-600' : 'text-slate-400' ?>">
                            <?= number_format($pendingHotelMgr) ?>
                        </span>
                        <span class="text-[10px] text-slate-400 ml-0.5"><?= lang('รายการ', 'items') ?></span>
                    </div>
                </div>

                <!-- Step 4: IT Manager Final Approval -->
                <?php $isMyStep4 = ($userRole === 'it_manager'); ?>
                <div class="p-3 rounded-xl border <?= $isMyStep4 ? 'border-indigo-400 bg-indigo-50/30 ring-1 ring-indigo-300' : 'border-slate-200 bg-slate-50/50' ?> flex items-center justify-between gap-3 transition">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-800 flex items-center justify-center text-xs font-bold shrink-0">
                            3
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold text-slate-900">IT Manager</span>
                                <?php if ($isMyStep4): ?>
                                    <span class="bg-indigo-600 text-white text-[9px] font-bold px-1.5 py-0.2 rounded-md animate-pulse"><?= lang('งานของคุณ', 'Your Task') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-sm font-bold <?= $pendingITMgr > 0 ? 'text-indigo-600' : 'text-slate-400' ?>">
                            <?= number_format($pendingITMgr) ?>
                        </span>
                        <span class="text-[10px] text-slate-400 ml-0.5"><?= lang('รายการ', 'items') ?></span>
                    </div>
                </div>

                <!-- Step 3: IT Staff (Action & Setup) -->
                <?php $isMyStep3 = ($userRole === 'it_staff'); ?>
                <div class="p-3 rounded-xl border <?= $isMyStep3 ? 'border-blue-400 bg-blue-50/30 ring-1 ring-blue-300' : 'border-slate-200 bg-slate-50/50' ?> flex items-center justify-between gap-3 transition">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center text-xs font-bold shrink-0">
                            4
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold text-slate-900">IT </span>
                                <?php if ($isMyStep3): ?>
                                    <span class="bg-blue-600 text-white text-[9px] font-bold px-1.5 py-0.2 rounded-md animate-pulse"><?= lang('งานของคุณ', 'Your Task') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-sm font-bold <?= $pendingITAction > 0 ? 'text-blue-600' : 'text-slate-400' ?>">
                            <?= number_format($pendingITAction) ?>
                        </span>
                        <span class="text-[10px] text-slate-400 ml-0.5"><?= lang('รายการ', 'items') ?></span>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- === SECTION 3: Pending Approvals Quick Action Table === -->
    <div id="pendingTableSection" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold">
                        <i class="fa-solid fa-list-check"></i>
                    </span>
                    <span>
                        <?= ($userRole === 'requester') ? lang('รายการคำขอของฉันที่อยู่ระหว่างดำเนินการ', 'My In-Progress Requests') : lang('รายการคำขอที่รอการพิจารณา', 'Requests Awaiting Action') ?>
                    </span>
                </h2>
                <p class="text-xs text-slate-500 mt-1 ml-9">
                    <?= ($userRole === 'requester') ? lang('ติดตามสถานะคำขอที่ท่านได้ยื่นไว้', 'Track the status of your submitted requests') : lang('คลิก "ตรวจ & ลงนาม" เพื่อเปิดดูรายละเอียดและลงลายมือชื่ออนุมัติ', 'Click "Review & Sign" to view details and provide signature') ?>
                </p>
            </div>

            <a href="index.php" class="text-xs text-sky-600 hover:text-sky-700 font-bold flex items-center space-x-1.5 transition self-start sm:self-auto">
                <span><?= lang('ดูประวัติคำขอทั้งหมด', 'View All Requests') ?></span>
                <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </a>
        </div>

        <?php if (empty($pendingRequests)): ?>
            <div class="py-12 text-center text-slate-400">
                <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto mb-3 text-xl">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h4 class="text-sm font-bold text-slate-700"><?= lang('ไม่มีคำขอค้างรอการดำเนินการในขณะนี้', 'No pending requests at this time') ?></h4>
                <p class="text-xs text-slate-400 mt-1"><?= lang('คำขอทั้งหมดได้รับการดำเนินการเรียบร้อยแล้ว', 'All requests have been processed') ?></p>
            </div>
        <?php else: ?>
            <!-- Desktop Table View (hidden on mobile md:block) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200/80">
                        <tr>
                            <th class="py-3 px-4"><?= lang('เลขที่คำขอ', 'Request No.') ?></th>
                            <th class="py-3 px-4"><?= lang('ผู้ยื่นคำขอ (พนักงาน)', 'Requester (Employee)') ?></th>
                            <th class="py-3 px-4"><?= lang('แผนก / ตำแหน่ง', 'Department / Position') ?></th>
                            <th class="py-3 px-4"><?= lang('โรงแรม / สาขา', 'Property / Hotel') ?></th>
                            <th class="py-3 px-4"><?= lang('สถานะปัจจุบัน', 'Status') ?></th>
                            <th class="py-3 px-4"><?= lang('วันที่ยื่น / ค้าง', 'Submitted / Duration') ?></th>
                            <th class="py-3 px-4 text-right"><?= lang('ดำเนินการ', 'Action') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($pendingRequests as $pr):
                            $stInfo = $STATUS_LIST[$pr['status']] ?? ['th_label' => $pr['status'], 'color' => 'bg-slate-100 text-slate-800'];
                            $fullName = (!empty($pr['title']) ? $pr['title'] . ' ' : '') . $pr['first_name'] . ' ' . $pr['last_name'];
                            $durInfo = getPendingDurationInfo($pr['created_at'], $pr['status']);
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <!-- Request No -->
                                <td class="py-3.5 px-4 font-mono font-bold text-sky-700">
                                    <a href="view_request.php?id=<?= $pr['id'] ?>" class="hover:underline">
                                        <?= htmlspecialchars($pr['request_no']) ?>
                                    </a>
                                </td>

                                <!-- Employee Name -->
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($fullName) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono">ID: <?= htmlspecialchars($pr['employee_id']) ?></div>
                                </td>

                                <!-- Dept & Position -->
                                <td class="py-3.5 px-4">
                                    <div class="text-slate-800 font-medium"><?= htmlspecialchars($pr['department']) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= htmlspecialchars($pr['position']) ?></div>
                                </td>

                                <!-- Property -->
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        <i class="fa-solid fa-hotel text-[9px] text-slate-400"></i>
                                        <span><?= htmlspecialchars($pr['property']) ?></span>
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $stInfo['color'] ?>">
                                        <i class="fa-solid fa-circle text-[6px] mr-1"></i>
                                        <?= getStatusLabel($pr['status']) ?>
                                    </span>
                                </td>

                                <!-- Date & Overdue Tag -->
                                <td class="py-3.5 px-4 text-slate-600 text-[11px]">
                                    <div class="font-mono"><?= date('d/m/Y H:i', strtotime($pr['created_at'])) ?></div>
                                    <?php if ($durInfo['is_pending']): ?>
                                        <span class="inline-block mt-0.5 px-2 py-0.2 rounded-full text-[10px] font-bold border <?= $durInfo['badge_class'] ?>">
                                            <?= $durInfo['text'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td class="py-3.5 px-4 text-right">
                                    <a href="view_request.php?id=<?= $pr['id'] ?>"
                                        class="px-3 py-1.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition shadow-2xs inline-flex items-center space-x-1">
                                        <span><?= ($userRole === 'requester') ? lang('ดูรายละเอียด', 'View Details') : lang('ตรวจสอบ', 'Review') ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View Card List (md:hidden) -->
            <div class="md:hidden divide-y divide-slate-100">
                <?php foreach ($pendingRequests as $pr):
                    $stInfo = $STATUS_LIST[$pr['status']] ?? ['th_label' => $pr['status'], 'color' => 'bg-slate-100 text-slate-800'];
                    $fullName = (!empty($pr['title']) ? $pr['title'] . ' ' : '') . $pr['first_name'] . ' ' . $pr['last_name'];
                    $durInfo = getPendingDurationInfo($pr['created_at'], $pr['status']);
                ?>
                    <div class="p-4 hover:bg-slate-50/60 transition bg-white space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center space-x-2">
                                <a href="view_request.php?id=<?= $pr['id'] ?>" class="font-mono font-bold text-sky-700 hover:text-sky-900 text-xs">
                                    <?= htmlspecialchars($pr['request_no']) ?>
                                </a>
                                <?php if ($durInfo['is_pending']): ?>
                                    <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold border <?= $durInfo['badge_class'] ?>">
                                        <?= $durInfo['text'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $stInfo['color'] ?>">
                                <i class="fa-solid fa-circle text-[5px] mr-1"></i>
                                <?= getStatusLabel($pr['status']) ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3 bg-slate-50/80 p-3 rounded-xl border border-slate-100">
                            <div class="flex items-center space-x-3 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xs shrink-0">
                                    <?= mb_substr($pr['first_name'], 0, 1) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 text-xs truncate">
                                        <?= htmlspecialchars($fullName) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 truncate mt-0.5">
                                        <?= htmlspecialchars($pr['position'] ?: lang('ไม่ระบุตำแหน่ง', 'Unspecified position')) ?>
                                    </div>
                                </div>
                            </div>

                            <button type="button" onclick="toggleDashReqMobile(<?= $pr['id'] ?>)"
                                class="px-2.5 py-1.5 bg-white hover:bg-sky-50 text-slate-700 hover:text-sky-700 border border-slate-200 rounded-lg text-[11px] font-semibold transition flex items-center space-x-1 shrink-0">
                                <span id="dashToggleText_<?= $pr['id'] ?>"><?= lang('ดูเพิ่ม', 'Details') ?></span>
                                <i id="dashToggleIcon_<?= $pr['id'] ?>" class="fa-solid fa-chevron-down text-[8px] text-slate-400"></i>
                            </button>
                        </div>

                        <div id="dashReqMobileDetail_<?= $pr['id'] ?>" class="hidden pt-1 space-y-3">
                            <div class="grid grid-cols-2 gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 block"><?= lang('รหัสพนักงาน:', 'Employee ID:') ?></span>
                                    <span class="font-mono font-bold text-slate-700"><?= htmlspecialchars($pr['employee_id'] ?: '-') ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block"><?= lang('แผนก:', 'Department:') ?></span>
                                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($pr['department'] ?: '-') ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block"><?= lang('โรงแรม:', 'Property:') ?></span>
                                    <span class="text-slate-700"><?= htmlspecialchars($pr['property']) ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block"><?= lang('วันที่ยื่น:', 'Submitted:') ?></span>
                                    <span class="font-mono text-slate-600"><?= date('d/m/Y H:i', strtotime($pr['created_at'])) ?></span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <a href="view_request.php?id=<?= $pr['id'] ?>" class="flex-1 py-2 px-3 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold text-xs shadow-xs flex items-center justify-center space-x-1.5 transition">
                                    <i class="fa-solid fa-signature text-[10px]"></i>
                                    <span><?= ($userRole === 'requester') ? lang('ดูรายละเอียด', 'View Details') : lang('ตรวจ & ลงนามอนุมัติ', 'Review & Sign') ?></span>
                                </a>
                                <a href="export_pdf.php?id=<?= $pr['id'] ?>" target="_blank" class="py-2 px-3 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs transition flex items-center justify-center space-x-1" title="<?= lang('พิมพ์ PDF', 'Print PDF') ?>">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <span>PDF</span>
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- === Single Clean Chart.js Initialization Script === -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Chart.defaults.font.family = "'Prompt', 'Sarabun', sans-serif";
        Chart.defaults.color = '#64748b';

        var ctxStatus = document.getElementById('statusChart');
        if (ctxStatus) {
            new Chart(ctxStatus.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: [<?= json_encode(lang('เสร็จสมบูรณ์', 'Completed')) ?>, <?= json_encode(lang('อยู่ระหว่างดำเนินการ', 'In Progress')) ?>, <?= json_encode(lang('ไม่อนุมัติ', 'Rejected')) ?>],
                    datasets: [{
                        data: [
                            <?= $completedCount ?>,
                            <?= $inProgressCount ?>,
                            <?= $rejectedCount ?>
                        ],
                        backgroundColor: [
                            '#10b981', // Emerald
                            '#0284c7', // Sky Blue
                            '#f43f5e' // Rose
                        ],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var sum = ctx.dataset.data.reduce(function(a, b) {
                                        return a + b;
                                    }, 0);
                                    var val = ctx.raw || 0;
                                    var pct = sum > 0 ? ((val / sum) * 100).toFixed(1) : 0;
                                    return ' ' + ctx.label + ': ' + val + ' <?= lang('รายการ', 'requests') ?> (' + pct + '%)';
                                }
                            }
                        }
                    },
                    cutout: '72%'
                }
            });
        }
    });

    var dashTextCollapse = <?= json_encode(lang('ย่อข้อมูล', 'Collapse')) ?>;
    var dashTextExpand = <?= json_encode(lang('ดูเพิ่ม', 'Details')) ?>;

    function toggleDashReqMobile(id) {
        var detail = document.getElementById('dashReqMobileDetail_' + id);
        var icon = document.getElementById('dashToggleIcon_' + id);
        var text = document.getElementById('dashToggleText_' + id);
        if (!detail) return;

        if (detail.classList.contains('hidden')) {
            detail.classList.remove('hidden');
            if (icon) icon.style.transform = 'rotate(180deg)';
            if (text) text.textContent = dashTextCollapse;
        } else {
            detail.classList.add('hidden');
            if (icon) icon.style.transform = 'rotate(0deg)';
            if (text) text.textContent = dashTextExpand;
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
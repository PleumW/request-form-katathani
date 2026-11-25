<?php

/**
 * Dashboard & Request Tracking Index
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

// Security: Require authentication - no auto-login for index
if (!isLoggedIn()) {
    redirect('login.php');
}

$currentUser = getCurrentUser();
$db = getDB();
$userLevel = (int)($currentUser['level_id'] ?? 2);
$userRole  = determineRoleFromUser($currentUser);

$isRequester    = ($userRole === 'requester');
$isDeptHead     = ($userRole === 'dept_head');
$isHotelMgr     = ($userRole === 'hotel_manager');
$isITMgr        = ($userRole === 'it_manager');
$isITStaff      = ($userRole === 'it_staff');
$isHRStaff      = ($userRole === 'hr_staff' || (int)($currentUser['dep_id'] ?? 0) === 2 || stripos($currentUser['department'] ?? '', 'HR') !== false || stripos($currentUser['department'] ?? '', 'Human') !== false);
$isAdmin        = ($userLevel === 99 || $userRole === 'admin');
$isHeadOrAbove  = ($isDeptHead || $isHotelMgr || $isITMgr || $userLevel >= 40);
$isApproverManager = ($isDeptHead || $isHotelMgr || $isITMgr);

// สิทธิ์ของ User และ Super (level_id 1 คือ User, 2 คือ Super)
$userLevelName  = strtolower($currentUser['level_name'] ?? '');
$isUserOrSuper  = ($userLevel === 1 || $userLevel === 2 || $userLevelName === 'user' || $userLevelName === 'super' || $isRequester);

// สิทธิ์ในการเข้าถึงและเห็นแท็บ "คำขอที่ฉันเคยยื่นส่ง": มีแค่ user, super และ admin เท่านั้น
$canViewSent    = ($isUserOrSuper || $isAdmin);

// Active Tab & View Mode
$defaultTab = $isAdmin ? 'all' : 'pending';
$tab = trim($_GET['tab'] ?? $defaultTab);
if (!in_array($tab, ['pending', 'signed', 'sent', 'all'])) {
    $tab = $defaultTab;
}

// Show All toggle for submitted requests (tab=sent or requester)
$showAll = (isset($_GET['show']) && $_GET['show'] === 'all');

// ตัวกรองที่เคยเซ็น ให้มีเฉพาะระดับหัวหน้าแผนกขึ้นไปเท่านั้น (level_id >= 40) หรือ IT Staff หรือ Admin
if ($tab === 'signed' && !$isHeadOrAbove && !$isITStaff && !$isAdmin) {
    $tab = 'pending';
}

// แท็บคำขอที่เคยยื่นส่ง (tab=sent) มีแค่ user, super และ admin เท่านั้นที่สามารถเข้าดูได้
if ($tab === 'sent' && !$canViewSent) {
    $tab = 'pending';
}

// Search & Filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$propertyFilter = trim($_GET['property'] ?? '');
$departmentFilter = trim($_GET['department'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

// Permission check for filtering by department: Only Dept Heads, IT Staff, HR, and Admin
$canFilterDept = ($isAdmin || $userLevel >= 40 || $isDeptHead || $isHotelMgr || $isITMgr || $isITStaff || $isHRStaff);

// Load departments for filter dropdown if user has permission
$deptList = [];
if ($canFilterDept) {
    foreach (getExDepartments() as $d) {
        if (!empty($d['dep_name'])) {
            $deptList[$d['dep_name']] = $d['dep_name'];
        }
    }
    $existingDepts = $db->query("SELECT DISTINCT department FROM requests WHERE department IS NOT NULL AND department != ''")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($existingDepts as $ed) {
        if (!empty($ed) && !isset($deptList[$ed])) {
            $deptList[$ed] = $ed;
        }
    }
    ksort($deptList);
}

$uid = (int)($currentUser['id'] ?? 0);
$uFullName = $currentUser['full_name'] ?? '';
$uDept = $currentUser['department'] ?? '';

// My requests counts (requests submitted by current user)
$countSent = (int)$db->query("SELECT COUNT(*) FROM requests WHERE requester_id = {$uid}")->fetchColumn();
$countMyIncomplete = (int)$db->query("SELECT COUNT(*) FROM requests WHERE requester_id = {$uid} AND status != 'completed'")->fetchColumn();

// 1. Pending count (waiting for current role's action, or incomplete for requester)
$countPending = 0;
$countIncoming = 0;
$countOverdue = 0;

if ($isDeptHead) {
    $countPending = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_dept_head'")->fetchColumn();
    $countIncoming = (int)$db->query("SELECT COUNT(*) FROM requests")->fetchColumn();
    $countOverdue = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_dept_head' AND DATEDIFF(CURDATE(), DATE(created_at)) >= 3")->fetchColumn();
} elseif ($isHotelMgr) {
    $countPending = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_hotel_manager'")->fetchColumn();
    $countIncoming = (int)$db->query("SELECT COUNT(*) FROM requests")->fetchColumn();
    $countOverdue = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_hotel_manager' AND DATEDIFF(CURDATE(), DATE(created_at)) >= 3")->fetchColumn();
} elseif ($isITMgr) {
    $countPending = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_it_manager'")->fetchColumn();
    $countIncoming = (int)$db->query("SELECT COUNT(*) FROM requests")->fetchColumn();
    $countOverdue = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_it_manager' AND DATEDIFF(CURDATE(), DATE(created_at)) >= 3")->fetchColumn();
} elseif ($isITStaff) {
    $countPending = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_it_action'")->fetchColumn();
} elseif ($isHRStaff) {
    $countPending = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'completed'")->fetchColumn();
} elseif ($isAdmin) {
    $countPending = (int)$db->query("SELECT COUNT(*) FROM requests WHERE status IN ('pending_dept_head', 'pending_hotel_manager', 'pending_it_action', 'pending_it_manager')")->fetchColumn();
} else {
    $countPending = $countMyIncomplete;
}

// 2. Signed / History count (documents signed or acted by user/role)
$countSigned = 0;
if ($isDeptHead) {
    // หัวหน้าแผนกเห็นแต่เอกสารที่ตัวเองเซ็นไปแล้วเท่านั้น
    $stmtC = $db->prepare("SELECT COUNT(*) FROM requests r WHERE r.id IN (SELECT request_id FROM approvals WHERE approver_role = 'dept_head' AND (approver_id = ? OR approver_name = ?))");
    $stmtC->execute([$uid, $uFullName]);
    $countSigned = (int)$stmtC->fetchColumn();
} elseif ($isITStaff) {
    $countSigned = (int)$db->query("SELECT COUNT(*) FROM requests r WHERE r.it_completed_at IS NOT NULL")->fetchColumn();
} elseif ($isHotelMgr) {
    $countSigned = (int)$db->query("SELECT COUNT(*) FROM requests r WHERE r.id IN (SELECT request_id FROM approvals WHERE approver_role = 'hotel_manager')")->fetchColumn();
} elseif ($isITMgr) {
    $countSigned = (int)$db->query("SELECT COUNT(*) FROM requests r WHERE r.id IN (SELECT request_id FROM approvals WHERE approver_role = 'it_manager')")->fetchColumn();
} elseif ($isAdmin) {
    $countSigned = (int)$db->query("SELECT COUNT(*) FROM requests r WHERE r.id IN (SELECT request_id FROM approvals) OR r.it_completed_at IS NOT NULL")->fetchColumn();
}

// 4. All Accessible count
$countAll = 0;
if ($isDeptHead) {
    $countAll = $countSigned;
} elseif ($isRequester) {
    $countAll = $countSent;
} else {
    // Admin, Hotel Mgr, IT Mgr, IT Staff ดูได้ทั้งหมด
    $countAll = (int)$db->query("SELECT COUNT(*) FROM requests")->fetchColumn();
}

$where = ["1=1"];
$params = [];

// Data scoping by Active Tab & User Role
if ($tab === 'pending') {
    // Default view: Latest requests currently needing action
    if ($isRequester) {
        $where[] = "r.requester_id = ?";
        $params[] = $uid;
        $where[] = "r.status != 'completed'";
    } elseif ($isDeptHead) {
        $where[] = "r.status = 'pending_dept_head'";
    } elseif ($isHotelMgr) {
        $where[] = "r.status = 'pending_hotel_manager'";
    } elseif ($isITStaff) {
        $where[] = "r.status = 'pending_it_action'";
    } elseif ($isITMgr) {
        $where[] = "r.status = 'pending_it_manager'";
    } elseif ($isHRStaff) {
        $where[] = "r.status = 'completed'";
    } elseif ($isAdmin) {
        $where[] = "r.status IN ('pending_dept_head', 'pending_hotel_manager', 'pending_it_action', 'pending_it_manager')";
    }
} elseif ($tab === 'signed') {
    // Documents previously signed / acted by user or role
    if ($isDeptHead) {
        // หัวหน้าแผนกเห็นแต่เอกสารที่ตัวเองเซ็นไปแล้วเท่านั้น
        $where[] = "r.id IN (SELECT request_id FROM approvals WHERE approver_role = 'dept_head' AND (approver_id = ? OR approver_name = ?))";
        $params[] = $uid;
        $params[] = $uFullName;
    } elseif ($isITStaff) {
        $where[] = "r.it_completed_at IS NOT NULL";
    } elseif ($isHotelMgr) {
        $where[] = "r.id IN (SELECT request_id FROM approvals WHERE approver_role = 'hotel_manager')";
    } elseif ($isITMgr) {
        $where[] = "r.id IN (SELECT request_id FROM approvals WHERE approver_role = 'it_manager')";
    } elseif ($isAdmin) {
        $where[] = "(r.id IN (SELECT request_id FROM approvals) OR r.it_completed_at IS NOT NULL)";
    } else {
        $where[] = "r.requester_id = ?";
        $params[] = $uid;
    }
} elseif ($tab === 'sent') {
    // Requests submitted by current user (Approver or Requester)
    $where[] = "r.requester_id = ?";
    $params[] = $uid;
    if (!$showAll) {
        $where[] = "r.status != 'completed'";
    }
} elseif ($tab === 'all') {
    // All accessible requests
    if ($isDeptHead) {
        // หัวหน้าแผนกเห็นแต่เอกสารที่ตัวเองเซ็นไปแล้วเท่านั้น
        $where[] = "r.id IN (SELECT request_id FROM approvals WHERE approver_role = 'dept_head' AND (approver_id = ? OR approver_name = ?))";
        $params[] = $uid;
        $params[] = $uFullName;
    } elseif ($isRequester) {
        $where[] = "r.requester_id = ?";
        $params[] = $uid;
    }
    // Admin, Hotel Mgr, IT Mgr, IT Staff ดูได้ทั้งหมดในระบบ
}

if (!empty($search)) {
    $where[] = "(r.request_no LIKE ? OR r.employee_id LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ? OR r.department LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

// Status filter enabled for Admin, Requester, or anytime non-pending tab is viewed
if (!empty($statusFilter) && ($isAdmin || $isRequester || $tab !== 'pending')) {
    $where[] = "r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($propertyFilter) && $isAdmin) {
    $where[] = "r.property = ?";
    $params[] = $propertyFilter;
}

// Filter by Department (เฉพาะหัวหน้าแผนก, IT Staff, HR, Admin)
if ($canFilterDept && !empty($departmentFilter)) {
    $where[] = "r.department = ?";
    $params[] = $departmentFilter;
}

// Filter by Date (วันที่ยื่นคำขอ)
if (!empty($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = "DATE(r.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = "DATE(r.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSql = implode(' AND ', $where);

// Metrics calculation for summary cards
$totalRequests = ($tab === 'pending') ? $countPending : (($tab === 'signed') ? $countSigned : (($tab === 'sent') ? ($showAll ? $countSent : $countMyIncomplete) : $countAll));
$pendingApprovals = ($isRequester || $tab === 'sent') ? $countMyIncomplete : $countPending;
$inProgressCount  = $countSigned;
$completedCount   = ($isRequester || $tab === 'sent')
    ? (int)$db->query("SELECT COUNT(*) FROM requests WHERE requester_id = {$uid} AND status = 'completed'")->fetchColumn()
    : (int)$db->query("SELECT COUNT(*) FROM requests WHERE status = 'completed'")->fetchColumn();

// Fetch Requests with master table joins
$query = "SELECT r.*, h.hotel_name_s, p.property_name, l.level_name 
          FROM requests r 
          LEFT JOIN ex_hotel h ON r.hotel_id = h.hotel_id 
          LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id 
          LEFT JOIN ex_level l ON r.level_id = l.level_id 
          WHERE {$whereSql} 
          ORDER BY r.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Set page heading & subheadings dynamically based on active tab & role
$pageHeading = match (true) {
    $tab === 'signed' => ($isDeptHead ? lang('คำขอที่ฉันเซ็นอนุมัติแล้ว', 'My Signed Requests') : lang('ประวัติคำขอที่เคยลงนาม', 'Signature History')),
    $tab === 'sent' => ($showAll ? lang('คำขอที่ฉันเคยยื่นส่งทั้งหมด', 'All My Submitted Requests') : lang('คำขอที่เคยยื่นส่ง', 'My Incomplete Requests')),
    $tab === 'all' => ($isRequester ? lang('ประวัติคำขอที่เคยยื่นทั้งหมด', 'All My Requests') : ($isDeptHead ? lang('คำขอทั้งหมด', 'All Requests') : ($isAdmin ? lang('รายการคำขอทั้งหมดในระบบ', 'All System Requests') : lang('รายการคำขอทั้งหมดในระบบ', 'All System Requests')))),
    default => match (true) {
        $isRequester => lang('รายการคำขอ', 'Requests'),
        $isDeptHead => lang('รอหัวหน้าแผนกอนุมัติ', 'Pending Department Head Approval'),
        $isHotelMgr => lang('รอผู้จัดการโรงแรมอนุมัติ', 'Pending Hotel Manager Approval'),
        $isITMgr => lang('รอผู้จัดการไอทีอนุมัติ', 'Pending IT Manager Approval'),
        $isITStaff => lang('งานที่รอฝ่ายไอทีดำเนินการ', 'Pending Action — IT'),
        $isHRStaff => lang('รายการคำขอที่เสร็จสมบูรณ์', 'Completed Requests'),
        default => lang('รายการคำขอเปิดสิทธิ์ระบบสารสนเทศ', 'IT System Access Requests')
    }
};

$pageSubheading = match (true) {
    $tab === 'signed' => lang('เอกสารทั้งหมดที่ท่านได้ลงนามอนุมัติหรือบันทึกข้อมูลเรียบร้อยแล้ว ตรวจสอบย้อนหลังได้ตลอดเวลา', 'All requests you have approved or acted upon. Available for audit anytime.'),
    $tab === 'sent' => ($showAll ? lang('แสดงประวัติคำขอเปิดสิทธิ์ระบบทั้งหมดที่ท่านเป็นผู้ยื่นส่ง', 'Showing all access requests submitted by you.') : lang('แสดงเฉพาะคำขอที่ท่านยื่นส่งและยังอยู่ระหว่างการดำเนินการอนุมัติหรือเปิดสิทธิ์', 'Showing your requests currently in progress or awaiting approval.')),
    $tab === 'all' => ($isRequester ? lang('แสดงรายการคำขอเปิดสิทธิ์ระบบทั้งหมดที่ท่านเคยยื่นส่ง ทั้งที่อยู่ระหว่างดำเนินการและเสร็จสมบูรณ์แล้ว', 'Showing all requests submitted by you, both in progress and completed.') : ($isDeptHead ? lang('แสดงเฉพาะเอกสารคำขอที่ท่านได้ลงนามอนุมัติเรียบร้อยแล้วเท่านั้น', 'Showing requests that you have reviewed and signed.') : lang('แสดงรายการคำขอทั้งหมดตามขอบเขตสิทธิ์การเข้าถึงข้อมูลของท่าน', 'Showing all requests accessible to your role.'))),
    default => match (true) {
        $isRequester => lang('แสดงเฉพาะคำขอที่อยู่ระหว่างดำเนินการอนุมัติและเปิดสิทธิ์ระบบ', 'Showing your requests currently undergoing approval or fulfillment.'),
        $isDeptHead || $isHotelMgr || $isITMgr => lang('รายการคำขอที่รอลายเซ็นของท่าน — ตรวจสอบและลงนามอนุมัติ', 'Requests awaiting your review and signature.'),
        $isITStaff => lang('รายการคำขอที่ผ่านการอนุมัติระดับโรงแรมแล้ว รอฝ่ายไอทีกำหนดวันเริ่มงานและลงลายมือชื่อ', 'Requests approved by hotel management, awaiting IT setup and signature.'),
        $isHRStaff => lang('แสดงเฉพาะรายการที่เปิดระบบเสร็จสมบูรณ์แล้ว เพื่อดาวน์โหลดและจัดเก็บรายงาน', 'Showing completed requests for reporting and archiving.'),
        default => lang('จัดการและติดตามสถานะคำขอเปิดสิทธิ์ระบบสารสนเทศ Katathani Collection', 'Manage and track IT system access requests for Katathani Collection.')
    }
};

$pageTitle = $pageHeading . ' - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="pb-16">

    <!-- Top Greeting Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($pageHeading) ?></h1>
            <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($pageSubheading) ?></p>
        </div>

        <div class="flex items-center space-x-3">
            <?php if ($isHRStaff || $isAdmin): ?>
                <a href="actions/export_requests.php?<?= http_build_query($_GET) ?>" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-file-excel"></i>
                    <span><?= lang('ส่งออกข้อมูล Excel / CSV', 'Export Excel / CSV') ?></span>
                </a>
            <?php endif; ?>

            <?php if ($isRequester || $isAdmin || $userLevel >= 40 || $isDeptHead || $isHotelMgr || $isITMgr || $isITStaff): ?>
                <a href="new_request.php" class="px-4 py-2.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/20 transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-circle-plus"></i>
                    <span><?= lang('ยื่นคำขอใหม่', 'New Request') ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metric Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <!-- Card 1: Total / Incoming -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 font-medium">
                    <?php
                    if ($isApproverManager) {
                        echo lang('เอกสารเข้ามาทั้งหมด', 'Total Incoming');
                    } elseif ($isRequester) {
                        echo ($tab === 'all') ? lang('คำขอที่เคยยื่นทั้งหมด', 'All My Requests') : lang('คำขอที่ยังไม่สมบูรณ์', 'Pending Requests');
                    } elseif ($tab === 'sent') {
                        echo ($showAll) ? lang('คำขอที่เคยยื่นทั้งหมด', 'All My Requests') : lang('คำขอที่ยังไม่สมบูรณ์', 'Pending Requests');
                    } elseif ($isITStaff) {
                        echo lang('อนุมัติครบ (รอ IT)', 'Approved (Pending IT)');
                    } elseif ($isHRStaff) {
                        echo lang('คำขอที่เสร็จสมบูรณ์', 'Completed Requests');
                    } else {
                        echo lang('คำขอทั้งหมด', 'Total Requests');
                    }
                    ?>
                </span>
                <div class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($isApproverManager ? $countIncoming : $totalRequests) ?></div>
                <?php if ($isApproverManager): ?>
                    <div class="text-[10px] text-slate-400 mt-0.5"><?= lang('เอกสารที่ส่งเข้ามาในระบบ', 'Total documents submitted') ?></div>
                <?php endif; ?>
            </div>
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg">
                <i class="fa-solid <?= $isApproverManager ? 'fa-inbox' : 'fa-folder-open' ?>"></i>
            </div>
        </div>

        <!-- Card 2: Pending -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-amber-700 font-medium">
                    <?php
                    if ($isDeptHead) echo lang('รอหัวหน้าแผนกอนุมัติ', 'Pending Dept Head Approval');
                    elseif ($isHotelMgr) echo lang('รอผู้จัดการโรงแรมอนุมัติ', 'Pending Hotel Manager Approval');
                    elseif ($isITMgr) echo lang('รอผู้จัดการไอทีอนุมัติ', 'Pending IT Manager Approval');
                    elseif ($isRequester || $tab === 'sent') echo lang('กำลังรอดำเนินการ', 'In Progress');
                    elseif ($isHRStaff) echo lang('รอจัดเก็บ', 'Pending Filing');
                    else echo lang('รอการอนุมัติ', 'Pending Approval');
                    ?>
                </span>
                <div class="text-2xl font-bold text-amber-600 mt-1"><?= number_format($pendingApprovals) ?></div>
                <?php if ($isApproverManager): ?>
                    <div class="text-[10px] text-slate-400 mt-0.5">
                        <?= $isDeptHead ? lang('รอหัวหน้าแผนกพิจารณา', 'Awaiting Department Head') : ($isHotelMgr ? lang('รอผู้จัดการโรงแรมพิจารณา', 'Awaiting Hotel Manager') : lang('รอผู้จัดการไอทีพิจารณา', 'Awaiting IT Manager')) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
        </div>

        <!-- Card 3: Overdue for Managers (Dept Head, Hotel Manager, IT Manager) / In Progress for other roles -->
        <?php if ($isApproverManager): ?>
            <!-- เฉพาะผู้จัดการ (หัวหน้าแผนก, ผู้จัดการโรงแรม, IT Manager): เอกสารค้างมาแล้วกี่ฉบับ (นับตั้งแต่ 3 วันขึ้นไป) -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border <?= $countOverdue > 0 ? 'border-rose-300 bg-rose-50/20 ring-1 ring-rose-200' : 'border-slate-200' ?> shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-rose-700 font-bold flex items-center space-x-1">
                        <span><?= lang('เอกสารค้าง', 'Overdue Requests') ?></span>
                    </span>
                    <div class="text-2xl font-bold text-rose-600 mt-1"><?= number_format($countOverdue) ?></div>
                    <div class="text-[10px] text-slate-400 mt-0.5"><?= lang('ค้างรออนุมัติตั้งแต่ 3 วันขึ้นไป', 'Pending for 3 or more days') ?></div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>
        <?php elseif (!$isHRStaff && !$isITStaff): ?>
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-blue-700 font-medium"><?= ($isRequester || $tab === 'sent') ? lang('กำลังดำเนินการ', 'In Progress') : lang('อนุมัติแล้ว (รอฝ่ายไอที)', 'Approved (Pending IT)') ?></span>
                    <div class="text-2xl font-bold text-blue-600 mt-1"><?= number_format($inProgressCount) ?></div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>
            </div>
        <?php endif; ?>

        <!-- Card 4: Completed / Signed -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-emerald-700 font-medium">
                    <?php
                    if ($isApproverManager) echo lang('เซ็นอนุมัติแล้ว', 'Signed / Approved');
                    elseif ($isRequester || $tab === 'sent') echo lang('อนุมัติเสร็จสมบูรณ์', 'Completed');
                    else echo lang('เสร็จสมบูรณ์', 'Completed');
                    ?>
                </span>
                <div class="text-2xl font-bold text-emerald-600 mt-1"><?= number_format($isApproverManager ? $countSigned : $completedCount) ?></div>
                <?php if ($isApproverManager): ?>
                    <div class="text-[10px] text-slate-400 mt-0.5"><?= lang('ประวัติที่ลงนามแล้ว', 'Signed History') ?></div>
                <?php endif; ?>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                <i class="fa-solid <?= $isApproverManager ? 'fa-signature' : 'fa-circle-check' ?>"></i>
            </div>
        </div>

    </div>

    <!-- Consolidated Tabs & Search Card -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-sm mb-6 space-y-4">

        <!-- View Mode Navigation Tabs (รวมอยู่ในกล่องเดียวกับแถบค้นหา) -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3.5 border-b border-slate-100">
            <div class="flex flex-wrap items-center gap-2">

                <?php if ($isUserOrSuper && !$isAdmin): ?>
                    <!-- FOR USER & SUPER (ผู้ยื่นคำขอ): แสดงเฉพาะ 2 แท็บของคำขอที่ตนเองยื่นส่ง -->

                    <!-- 1. คำขอที่ยังไม่เสร็จสมบูรณ์ (Default) -->
                    <a href="index.php?tab=pending" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 border <?= $tab === 'pending' ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200' ?>">
                        <i class="fa-solid fa-clock-rotate-left <?= $tab === 'pending' ? 'text-white' : 'text-amber-500' ?>"></i>
                        <span><?= lang('คำขอที่ยังไม่เสร็จสมบูรณ์', 'Pending Requests') ?></span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tab === 'pending' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' ?>">
                            <?= number_format($countMyIncomplete) ?>
                        </span>
                    </a>

                    <!-- 2. คำขอที่เคยยื่นทั้งหมด -->
                    <a href="index.php?tab=all" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 border <?= $tab === 'all' ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200' ?>">
                        <i class="fa-solid fa-list-check <?= $tab === 'all' ? 'text-white' : 'text-slate-500' ?>"></i>
                        <span><?= lang('คำขอที่เคยยื่นทั้งหมด', 'All Submitted') ?></span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700' ?>">
                            <?= number_format($countSent) ?>
                        </span>
                    </a>

                <?php else: ?>
                    <!-- FOR APPROVERS & STAFF (ผู้อนุมัติ, เจ้าหน้าที่ไอที, ผู้ดูแลระบบ) -->

                    <!-- 1. งานที่ต้องจัดการ (รอฉันดำเนินการ) -->
                    <a href="index.php?tab=pending" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 border <?= $tab === 'pending' ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200' ?>">
                        <i class="fa-solid fa-clock-rotate-left <?= $tab === 'pending' ? 'text-white' : 'text-amber-500' ?>"></i>
                        <span><?= $isAdmin ? lang('คำขอที่รออนุมัติในระบบ', 'Pending in System') : lang('งานที่ต้องจัดการ', 'Pending Action') ?></span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tab === 'pending' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' ?>">
                            <?= number_format($countPending) ?>
                        </span>
                    </a>

                    <!-- 2. ประวัติที่เคยเซ็น / ดำเนินการ -->
                    <?php if ($isHeadOrAbove || $isITStaff || $isAdmin): ?>
                        <a href="index.php?tab=signed" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 border <?= $tab === 'signed' ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200' ?>">
                            <i class="fa-solid fa-signature <?= $tab === 'signed' ? 'text-white' : 'text-emerald-500' ?>"></i>
                            <span><?= $isDeptHead ? lang('คำขอที่ฉันเซ็นอนุมัติแล้ว', 'Signed by Me') : lang('ประวัติที่เคยเซ็น', 'Signed History') ?></span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tab === 'signed' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' ?>">
                                <?= number_format($countSigned) ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <!-- 3. คำขอที่ฉันเคยยื่นส่ง (มีแค่ User, Super และ Admin เท่านั้นที่สามารถเห็นได้) -->
                    <?php if ($canViewSent): ?>
                        <a href="index.php?tab=sent" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 border <?= $tab === 'sent' ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200' ?>">
                            <i class="fa-solid fa-paper-plane <?= $tab === 'sent' ? 'text-white' : 'text-indigo-500' ?>"></i>
                            <span><?= lang('คำขอที่ฉันเคยยื่นส่ง', 'My Submissions') ?></span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tab === 'sent' ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-800' ?>">
                                <?= number_format($countSent) ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <!-- 4. รายการคำขอทั้งหมดในระบบ -->
                    <?php if ($isAdmin || $isHotelMgr || $isITMgr || $isITStaff || $isHRStaff): ?>
                        <a href="index.php?tab=all" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 border <?= $tab === 'all' ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-600/20' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200' ?>">
                            <i class="fa-solid fa-list-check <?= $tab === 'all' ? 'text-white' : 'text-slate-500' ?>"></i>
                            <span><?= lang('รายการทั้งหมด', 'All Requests') ?></span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700' ?>">
                                <?= number_format($countAll) ?>
                            </span>
                        </a>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

            <!-- Toggle Incomplete vs All for Tab 'sent' (สำหรับผู้อนุมัติที่เข้ามาดูคำขอที่เคยยื่น) -->
            <?php if ($tab === 'sent'): ?>
                <div class="flex items-center space-x-2 text-xs">
                    <?php if ($showAll): ?>
                        <a href="index.php?tab=sent" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl font-bold shadow-2xs transition flex items-center space-x-1.5 cursor-pointer">
                            <i class="fa-solid fa-clock-rotate-left text-amber-500"></i>
                            <span><?= lang('แสดงเฉพาะที่ยังไม่สมบูรณ์', 'Show Pending Only') ?> (<?= number_format($countMyIncomplete) ?>)</span>
                        </a>
                    <?php else: ?>
                        <a href="index.php?tab=sent&show=all" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold shadow-2xs transition flex items-center space-x-1.5 cursor-pointer">
                            <i class="fa-solid fa-list-check"></i>
                            <span><?= lang('แสดงคำขอที่เคยยื่นทั้งหมด', 'Show All Submissions') ?> (<?= number_format($countSent) ?>)</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- Search & Filter Controls -->
        <form method="GET" action="index.php" class="space-y-3">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <?php if ($showAll): ?>
                <input type="hidden" name="show" value="all">
            <?php endif; ?>
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">

                <!-- Search Input -->
                <div class="<?= ($canFilterDept && ($isAdmin || $isRequester)) ? 'sm:col-span-5' : ($canFilterDept ? 'sm:col-span-7' : 'sm:col-span-8') ?> relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= lang('ค้นหาตามเลขที่คำขอ, รหัสพนักงาน, ชื่อ หรือแผนก...', 'Search by request no, employee ID, name, or department...') ?>"
                        class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500">
                </div>

                <?php if ($canFilterDept): ?>
                    <!-- Department Filter (เฉพาะหัวหน้าแผนก, IT Staff, HR Staff, Admin) -->
                    <div class="<?= ($isAdmin || $isRequester) ? 'sm:col-span-4' : 'sm:col-span-5' ?>">
                        <select name="department" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <option value=""><?= lang('ทุกแผนก', 'All Departments') ?></option>
                            <?php foreach ($deptList as $dName): ?>
                                <option value="<?= htmlspecialchars($dName) ?>" <?= $departmentFilter === $dName ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($isAdmin || $isRequester || $tab !== 'pending'): ?>
                    <!-- Status Filter -->
                    <div class="<?= $canFilterDept ? 'sm:col-span-3' : 'sm:col-span-4' ?>">

                    </div>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <!-- Property Filter (Admin only) -->
                    <div class="sm:col-span-3">
                        <select name="property" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <option value=""><?= lang('ทุกโรงแรม', 'All Hotels') ?></option>
                            <?php foreach (getExHotels() as $h): ?>
                                <option value="<?= htmlspecialchars($h['hotel_name']) ?>" <?= $propertyFilter === $h['hotel_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($h['hotel_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Date Range Filter & Action Buttons (ทุกคนสามารถกรองวันที่ได้) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-slate-100">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500 font-medium flex items-center gap-1.5">
                        <i class="fa-regular fa-calendar text-slate-400"></i>
                        <span><?= lang('ช่วงวันที่ยื่นคำขอ:', 'Submission Date Range:') ?></span>
                    </span>
                    <div class="flex items-center gap-1.5">
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                            class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500"
                            title="<?= lang('ตั้งแต่วันที่', 'From Date') ?>">
                        <span class="text-slate-400 text-xs"><?= lang('ถึง', 'to') ?></span>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                            class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500"
                            title="<?= lang('ถึงวันที่', 'To Date') ?>">
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <button type="submit" class="py-2 px-4 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition flex items-center justify-center space-x-1.5 shadow-sm">
                        <span><?= lang('ค้นหา', 'Search') ?></span>
                    </button>
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($propertyFilter) || (!empty($departmentFilter) && $canFilterDept) || !empty($dateFrom) || !empty($dateTo)): ?>
                        <a href="index.php?tab=<?= urlencode($tab) ?>" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition flex items-center space-x-1" title="<?= lang('ล้างตัวกรอง', 'Reset Filters') ?>">
                            <i class="fa-solid fa-rotate-left text-[11px]"></i>
                            <span><?= lang('ล้างตัวกรอง', 'Reset') ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </form>
    </div>

    <!-- Requests Table & Mobile Responsive Cards Container -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- 1. Desktop View: Full Data Table (hidden on mobile md:block) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                        <th class="py-3.5 px-4 sm:px-6"><?= lang('เลขที่คำขอ / วันที่ยื่น', 'Request No / Date') ?></th>
                        <th class="py-3.5 px-4"><?= lang('พนักงานผู้ขอสิทธิ์', 'Employee') ?></th>
                        <th class="py-3.5 px-4"><?= lang('แผนก / ตำแหน่ง', 'Department / Position') ?></th>
                        <th class="py-3.5 px-4"><?= lang('สถานที่ปฏิบัติงาน', 'Property') ?></th>
                        <th class="py-3.5 px-4"><?= lang('สถานะปัจจุบัน', 'Status') ?></th>
                        <th class="py-3.5 px-4 sm:px-6 text-right"><?= lang('ดำเนินการ', 'Action') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($requests) > 0): ?>
                        <?php foreach ($requests as $req):
                            $stInfo = $STATUS_LIST[$req['status']] ?? ['label' => $req['status'], 'color' => 'bg-slate-100 text-slate-800', 'th_label' => $req['status']];
                            $userCanApprove = canApprove($currentUser, $req);
                        ?>
                            <tr class="hover:bg-slate-50/80 transition group">

                                <!-- Request No & Date -->
                                <td class="py-4 px-4 sm:px-6">
                                    <a href="view_request.php?id=<?= $req['id'] ?>" class="font-bold font-mono text-sky-700 hover:text-sky-900 group-hover:underline block">
                                        <?= htmlspecialchars($req['request_no']) ?>
                                    </a>
                                    <span class="text-[11px] text-slate-400">
                                        <?= date('d/m/Y H:i', strtotime($req['created_at'])) ?>
                                    </span>
                                </td>

                                <!-- Employee -->
                                <td class="py-4 px-4">
                                    <div class="font-bold text-slate-800">
                                        <?= htmlspecialchars((!empty($req['title']) ? $req['title'] . ' ' : '') . $req['first_name'] . ' ' . $req['last_name']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono">
                                        ID: <?= htmlspecialchars($req['employee_id']) ?>
                                    </div>
                                </td>

                                <!-- Dept & Position -->
                                <td class="py-4 px-4">
                                    <div class="text-slate-800 font-medium"><?= htmlspecialchars($req['department']) ?></div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        <span><?= htmlspecialchars($req['position']) ?></span>
                                    </div>
                                </td>

                                <!-- Property -->
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        <i class="fa-solid fa-hotel text-[9px] text-slate-400"></i>
                                        <span><?= htmlspecialchars($req['property']) ?></span>
                                    </span>
                                    <?php if (!empty($req['property_name'])): ?>
                                        <span class="block text-[10px] text-slate-400 mt-0.5 pl-1"><?= htmlspecialchars($req['property_name']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge + Days Pending -->
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $stInfo['color'] ?>">
                                        <i class="fa-solid fa-circle text-[7px] mr-1.5"></i>
                                        <?= getStatusLabel($req['status']) ?>
                                    </span>
                                    <?php
                                    // Show days pending for approver roles
                                    if ($isDeptHead || $isHotelMgr || $isITMgr || $isITStaff) {
                                        $durInfo = getPendingDurationInfo($req['created_at'], $req['status']);
                                        if ($durInfo['is_pending']) {
                                            echo '<span class="inline-flex items-center space-x-1 mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold border ' . $durInfo['badge_class'] . '">';
                                            echo '<i class="fa-regular fa-clock text-[9px]"></i>';
                                            echo '<span>' . $durInfo['text'] . '</span>';
                                            echo '</span>';
                                        }
                                    }
                                    ?>
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-4 px-4 sm:px-6 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <?php if ($userCanApprove): ?>
                                            <a href="view_request.php?id=<?= $req['id'] ?>" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-bold shadow-sm flex items-center space-x-1 animate-pulse">
                                                <i class="fa-solid fa-signature text-[10px]"></i>
                                                <span><?= lang('ลงนาม', 'Sign') ?></span>
                                            </a>
                                        <?php else: ?>
                                            <a href="view_request.php?id=<?= $req['id'] ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-sky-50 text-slate-700 hover:text-sky-700 rounded-lg font-semibold transition border border-slate-200">
                                                <?= lang('ดูข้อมูล', 'View') ?>
                                            </a>
                                        <?php endif; ?>

                                        <a href="export_pdf.php?id=<?= $req['id'] ?>" target="_blank" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition" title="<?= lang('ดาวน์โหลด PDF / พิมพ์', 'Download PDF / Print') ?>">
                                            <i class="fa-solid fa-file-pdf text-sm"></i>
                                        </a>
                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-12 px-4 text-center">
                                <?php
                                $hasFilterActive = (!empty($search) || !empty($statusFilter) || !empty($propertyFilter) || (!empty($departmentFilter) && $canFilterDept) || !empty($dateFrom) || !empty($dateTo));
                                if ($hasFilterActive):
                                ?>
                                    <div class="max-w-sm mx-auto text-slate-400">
                                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400 text-xl">
                                            <i class="fa-solid fa-magnifying-glass"></i>
                                        </div>
                                        <p class="font-bold text-slate-700 text-sm"><?= lang('ไม่พบรายการคำขอที่ตรงกับเงื่อนไข', 'No requests match your filter criteria') ?></p>
                                        <p class="text-xs text-slate-400 mt-1 mb-4"><?= lang('ลองปรับเปลี่ยนหรือล้างเงื่อนไขตัวกรองเพื่อดูรายการทั้งหมด', 'Try adjusting or clearing your filters to see all requests') ?></p>
                                        <a href="index.php" class="inline-flex items-center space-x-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">
                                            <i class="fa-solid fa-rotate-left text-[10px]"></i>
                                            <span><?= lang('ล้างตัวกรองทั้งหมด', 'Clear All Filters') ?></span>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="max-w-sm mx-auto text-slate-400">
                                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400 text-xl">
                                            <i class="fa-regular fa-folder-open"></i>
                                        </div>
                                        <p class="font-bold text-slate-700 text-sm">
                                            <?= $isRequester ? lang('คุณยังไม่มีรายการคำขอในระบบ', 'You have no requests in the system') : lang('ยังไม่มีรายการคำขอในระบบ', 'No requests found in the system') ?>
                                        </p>
                                        <p class="text-xs text-slate-400 mt-1 mb-4"><?= lang('สามารถกดปุ่มด้านล่างเพื่อเริ่มสร้างและยื่นคำขอได้ทันที', 'Click the button below to start creating and submitting a request') ?></p>
                                        <a href="new_request.php" class="inline-flex items-center space-x-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition">
                                            <i class="fa-solid fa-plus text-[10px]"></i>
                                            <span><?= lang('ยื่นคำขอใหม่', 'New Request') ?></span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Mobile Responsive Card List (md:hidden - ไม่ต้องเลื่อนซ้ายขวา) -->
        <div class="md:hidden divide-y divide-slate-100">
            <?php if (count($requests) > 0): ?>
                <?php foreach ($requests as $req):
                    $stInfo = $STATUS_LIST[$req['status']] ?? ['label' => $req['status'], 'color' => 'bg-slate-100 text-slate-800', 'th_label' => $req['status']];
                    $userCanApprove = canApprove($currentUser, $req);
                    $fullName = (!empty($req['title']) ? $req['title'] . ' ' : '') . $req['first_name'] . ' ' . $req['last_name'];
                    $durInfo = getPendingDurationInfo($req['created_at'], $req['status']);
                ?>
                    <div class="p-4 hover:bg-slate-50/60 transition bg-white space-y-3">
                        <!-- Top Bar: เลขที่คำขอ & สถานะ -->
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center space-x-2">
                                <a href="view_request.php?id=<?= $req['id'] ?>" class="font-mono font-bold text-sky-700 hover:text-sky-900 text-xs">
                                    <?= htmlspecialchars($req['request_no']) ?>
                                </a>
                                <span class="text-[10px] text-slate-400 font-mono">
                                    <?= date('d/m/Y', strtotime($req['created_at'])) ?>
                                </span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $stInfo['color'] ?>">
                                <i class="fa-solid fa-circle text-[5px] mr-1"></i>
                                <?= getStatusLabel($req['status']) ?>
                            </span>
                        </div>

                        <!-- Main Info: แสดงเฉพาะชื่อ และตำแหน่ง (ตามที่ต้องการ) พร้อมปุ่มคลิกดูเพิ่มเติม -->
                        <div class="flex items-center justify-between gap-3 bg-slate-50/80 p-3 rounded-xl border border-slate-100">
                            <div class="flex items-center space-x-3 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xs shadow-xs flex-shrink-0">
                                    <?= mb_substr($req['first_name'], 0, 1) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 text-sm truncate">
                                        <?= htmlspecialchars($fullName) ?>
                                    </div>
                                    <div class="text-xs text-slate-500 truncate flex items-center space-x-1 mt-0.5">
                                        <i class="fa-solid fa-briefcase text-[10px] text-slate-400"></i>
                                        <span><?= htmlspecialchars($req['position'] ?: lang('ไม่ระบุตำแหน่ง', 'Unspecified position')) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- ปุ่มคลิกดูเพิ่มเติม -->
                            <button type="button" onclick="toggleReqMobile(<?= $req['id'] ?>)"
                                class="px-2.5 py-1.5 bg-white hover:bg-sky-50 text-slate-700 hover:text-sky-700 border border-slate-200 rounded-lg text-[11px] font-semibold transition flex items-center space-x-1 flex-shrink-0 shadow-2xs cursor-pointer">
                                <span id="toggleText_<?= $req['id'] ?>"><?= lang('ดูเพิ่มเติม', 'Details') ?></span>
                                <i id="toggleIcon_<?= $req['id'] ?>" class="fa-solid fa-chevron-down text-[9px] transition-transform duration-200 text-slate-400"></i>
                            </button>
                        </div>

                        <!-- Collapsible Detail Section (รายละเอียดหลักของ user ที่ส่งคำขอ) -->
                        <div id="reqMobileDetail_<?= $req['id'] ?>" class="hidden pt-1 space-y-3">
                            <div class="grid grid-cols-2 gap-2.5 p-3 rounded-xl bg-slate-50/90 border border-slate-200 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-medium"><?= lang('รหัสพนักงาน:', 'Employee ID:') ?></span>
                                    <span class="font-mono font-bold text-slate-700 text-xs"><?= htmlspecialchars($req['employee_id'] ?: '-') ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-medium"><?= lang('แผนก:', 'Department:') ?></span>
                                    <span class="font-semibold text-slate-800 text-xs"><?= htmlspecialchars($req['department'] ?: '-') ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-medium"><?= lang('สถานที่ปฏิบัติงาน:', 'Property:') ?></span>
                                    <span class="text-slate-700 text-xs flex items-center space-x-1">
                                        <i class="fa-solid fa-hotel text-[9px] text-slate-400"></i>
                                        <span><?= htmlspecialchars($req['property']) ?></span>
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block font-medium"><?= lang('วันที่-เวลายื่นคำขอ:', 'Submission Date:') ?></span>
                                    <span class="font-mono text-slate-600 text-xs"><?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></span>
                                </div>
                                <?php if (($isDeptHead || $isHotelMgr || $isITMgr || $isITStaff) && $durInfo['is_pending']): ?>
                                    <div class="col-span-2 pt-1.5 border-t border-slate-200 flex items-center space-x-1.5">
                                        <span class="text-[10px] text-slate-400"><?= lang('ระยะเวลา:', 'Duration:') ?></span>
                                        <span class="font-bold <?= $durInfo['text_class'] ?> text-[11px]">
                                            <i class="fa-regular fa-clock text-[10px] mr-1"></i><?= $durInfo['is_today'] ? lang('ส่งวันนี้', 'Submitted Today') : (lang('ค้างอนุมัติ ', 'Pending ') . $durInfo['days'] . lang(' วัน', ' days')) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ปุ่มดำเนินการบนมือถือ -->
                            <div class="flex items-center space-x-2">
                                <?php if ($userCanApprove): ?>
                                    <a href="view_request.php?id=<?= $req['id'] ?>" class="flex-1 py-2 px-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-xs shadow-sm flex items-center justify-center space-x-1.5 transition">
                                        <i class="fa-solid fa-signature text-[11px]"></i>
                                        <span><?= lang('ตรวจสอบ & ลงนามอนุมัติ', 'Review & Sign') ?></span>
                                    </a>
                                <?php else: ?>
                                    <a href="view_request.php?id=<?= $req['id'] ?>" class="flex-1 py-2 px-3 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-semibold text-xs shadow-sm transition flex items-center justify-center space-x-1.5">
                                        <i class="fa-regular fa-eye text-[11px]"></i>
                                        <span><?= lang('ดูรายละเอียดคำขอ', 'View Details') ?></span>
                                    </a>
                                <?php endif; ?>

                                <a href="export_pdf.php?id=<?= $req['id'] ?>" target="_blank" class="py-2 px-3 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs transition flex items-center justify-center space-x-1" title="<?= lang('พิมพ์ PDF', 'Print PDF') ?>">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <span>PDF</span>
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="py-12 px-4 text-center">
                    <div class="max-w-sm mx-auto text-slate-400">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400 text-xl">
                            <i class="fa-regular fa-folder-open"></i>
                        </div>
                        <p class="font-bold text-slate-700 text-sm"><?= lang('ไม่พบรายการคำขอในระบบ', 'No requests found in the system') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
    var textCollapse = <?= json_encode(lang('ย่อข้อมูล', 'Collapse')) ?>;
    var textExpand = <?= json_encode(lang('ดูเพิ่มเติม', 'Details')) ?>;

    function toggleReqMobile(id) {
        var detail = document.getElementById('reqMobileDetail_' + id);
        var icon = document.getElementById('toggleIcon_' + id);
        var text = document.getElementById('toggleText_' + id);
        if (!detail) return;

        if (detail.classList.contains('hidden')) {
            detail.classList.remove('hidden');
            if (icon) icon.style.transform = 'rotate(180deg)';
            if (text) text.textContent = textCollapse;
        } else {
            detail.classList.add('hidden');
            if (icon) icon.style.transform = 'rotate(0deg)';
            if (text) text.textContent = textExpand;
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
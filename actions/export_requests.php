<?php
/**
 * Export System Access Requests to CSV / Excel
 * Allows HR Staff and Admins to export all request data with UTF-8 BOM
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = getCurrentUser();
if (!$currentUser) {
    redirect('login.php');
}

$userLevel = (int)($currentUser['level_id'] ?? 1);
$userRole  = determineRoleFromUser($currentUser);
$isAdmin   = ($userLevel === 99 || $userRole === 'admin');
$isHRStaff = ($userRole === 'hr_staff' || (int)($currentUser['dep_id'] ?? 0) === 2 || stripos($currentUser['department'] ?? '', 'HR') !== false || stripos($currentUser['department'] ?? '', 'Human') !== false);

// Only HR Staff and Admin can export Excel / CSV
$canExport = ($isAdmin || $isHRStaff);
if (!$canExport) {
    setFlash('error', 'คุณไม่มีสิทธิ์ในการดาวน์โหลดข้อมูล Export (สิทธิ์เฉพาะฝ่ายบุคคล HR และ Admin เท่านั้น)');
    redirect('index.php');
}

$db = getDB();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$propertyFilter = trim($_GET['property'] ?? '');
$departmentFilter = trim($_GET['department'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = ["1=1"];
$params = [];

// Security: HR Staff exports completed requests by default unless admin
if ($isHRStaff && !$isAdmin) {
    $where[] = "r.status = 'completed'";
}

if (!empty($search)) {
    $where[] = "(r.request_no LIKE ? OR r.employee_id LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ? OR r.department LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if (!empty($statusFilter)) {
    $where[] = "r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($propertyFilter)) {
    $where[] = "r.property = ?";
    $params[] = $propertyFilter;
}

if (!empty($departmentFilter)) {
    $where[] = "r.department = ?";
    $params[] = $departmentFilter;
}

if (!empty($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = "DATE(r.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = "DATE(r.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSql = implode(' AND ', $where);

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

// Pre-fetch all approvals and it_actions for efficiency
$requestIds = array_column($requests, 'id');
$approvalsByReq = [];
$itActionsByReq = [];

if (!empty($requestIds)) {
    $inClause = implode(',', array_map('intval', $requestIds));
    $appRows = $db->query("SELECT * FROM approvals WHERE request_id IN ({$inClause}) ORDER BY id ASC")->fetchAll();
    foreach ($appRows as $ar) {
        $approvalsByReq[$ar['request_id']][$ar['approver_role']] = $ar;
    }

    foreach ($requests as $r) {
        if (!empty($r['it_completed_at']) || !empty($r['it_effective_date'])) {
            $itActionsByReq[$r['id']] = [
                'it_staff_name'  => $r['it_staff_name'] ?? '',
                'effective_date' => $r['it_effective_date'] ?? '',
                'expiry_date'    => $r['it_expiry_date'] ?? '',
                'notes'          => $r['it_notes'] ?? '',
            ];
        }
    }
}

// Generate CSV with UTF-8 BOM
$filename = 'System_Access_Requests_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel Thai language compatibility
fwrite($output, "\xEF\xBB\xBF");

// Headers
fputcsv($output, [
    'เลขที่คำขอ (Request No)',
    'วันที่ยื่นคำขอ (Created Date)',
    'สถานะ (Status)',
    'รหัสพนักงาน (Employee ID)',
    'คำนำหน้า (Title)',
    'ชื่อ (First Name)',
    'นามสกุล (Last Name)',
    'แผนก (Department)',
    'ตำแหน่ง (Position)',
    'สถานที่ปฏิบัติงาน (Property / Hotel)',
    'ชื่อผู้จัดการ (Manager Name)',
    'หัวหน้าแผนกผู้อนุมัติ (Dept Head)',
    'วันที่หัวหน้าแผนกอนุมัติ (Dept Head Date)',
    'ผู้จัดการโรงแรมผู้อนุมัติ (Hotel Manager)',
    'วันที่ผู้จัดการโรงแรมอนุมัติ (Hotel Manager Date)',
    'ผู้จัดการไอทีผู้อนุมัติ (IT Manager)',
    'วันที่ผู้จัดการไอทีอนุมัติ (IT Manager Date)',
    'เจ้าหน้าที่ไอที (IT Staff)',
    'วันที่มีผล / เริ่มงาน (Effective Date)',
    'วันหมดอายุบัญชี (Expiry Date)',
    'หมายเหตุไอที (IT Notes)',
    'ยอมรับนโยบาย (Policy Agreed)',
    'เหตุผลที่ปฏิเสธ (Reject Reason)'
]);

foreach ($requests as $r) {
    $rid = $r['id'];
    $statusTh = $STATUS_LIST[$r['status']]['th_label'] ?? $r['status'];
    
    $deptHeadApp = $approvalsByReq[$rid]['dept_head'] ?? null;
    $hotelMgrApp = $approvalsByReq[$rid]['hotel_manager'] ?? null;
    $itMgrApp    = $approvalsByReq[$rid]['it_manager'] ?? null;
    $itAct       = $itActionsByReq[$rid] ?? null;

    fputcsv($output, [
        $r['request_no'],
        $r['created_at'],
        $statusTh,
        $r['employee_id'],
        $r['title'] ?? '',
        $r['first_name'],
        $r['last_name'],
        $r['department'],
        $r['position'] ?? '',
        $r['property'],
        $r['manager_name'] ?? '',
        $deptHeadApp ? $deptHeadApp['approver_name'] : '',
        $deptHeadApp ? $deptHeadApp['signed_at'] : '',
        $hotelMgrApp ? $hotelMgrApp['approver_name'] : '',
        $hotelMgrApp ? $hotelMgrApp['signed_at'] : '',
        $itMgrApp ? $itMgrApp['approver_name'] : '',
        $itMgrApp ? $itMgrApp['signed_at'] : '',
        $itAct ? $itAct['it_staff_name'] : '',
        $itAct ? $itAct['effective_date'] : '',
        $itAct ? $itAct['expiry_date'] : '',
        $itAct ? $itAct['notes'] : '',
        $r['policy_agreed'] ? 'ยอมรับเงื่อนไข' : 'ไม่ระบุ',
        $r['reject_reason'] ?? ''
    ]);
}

fclose($output);
exit;

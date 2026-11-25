<?php
/**
 * Action: Handle Request Submission
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('new_request.php');
}

// Security: Verify CSRF token
requireCsrf();

$currentUser = getCurrentUser();
$requesterId = $currentUser ? $currentUser['id'] : null;

$title = trim($_POST['title'] ?? '');
$employeeId = trim($_POST['employee_id'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$department = trim($_POST['department'] ?? '');
$position = trim($_POST['position'] ?? '');
$hotelId = !empty($_POST['hotel_id']) ? (int)$_POST['hotel_id'] : null;
$levelId = null;

// Resolve property name from hotel_id (ex_hotel)
$property = 'Katathani';
if ($hotelId) {
    $hInfo = getHotelById($hotelId);
    if ($hInfo) {
        $property = $hInfo['hotel_name'];
    }
} elseif (!empty($_POST['property'])) {
    $property = trim($_POST['property']);
}

$managerName = trim($_POST['manager_name'] ?? '');
$policyAgreed = isset($_POST['policy_agreed']) ? 1 : 0;
$requesterSignature = null; // ผู้ยื่นคำขอไม่ต้องเซ็น ให้แค่ติ๊กยอมรับเงื่อนไข
$systemsData = $_POST['systems'] ?? [];

if (empty($employeeId) || empty($firstName) || empty($lastName)) {
    setFlash('error', 'กรุณากรอกข้อมูลพนักงานที่จำเป็นให้ครบถ้วน');
    redirect('new_request.php');
}

if (!$policyAgreed) {
    setFlash('error', 'กรุณาทำเครื่องหมายยอมรับเงื่อนไขนโยบายไอที (IT Policies)');
    redirect('new_request.php');
}

$db = getDB();

// Generate Request No: REQ-YYYYMM-0001
$prefix = 'REQ-' . date('Ym') . '-';
$stmt = $db->prepare("SELECT request_no FROM requests WHERE request_no LIKE ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$prefix . '%']);
$lastNo = $stmt->fetchColumn();

if ($lastNo) {
    $lastSeq = (int)substr($lastNo, strlen($prefix));
    $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
} else {
    $nextSeq = '0001';
}
$requestNo = $prefix . $nextSeq;

try {
    $jsonSystems = json_encode($systemsData, JSON_UNESCAPED_UNICODE);

    $insertSql = "INSERT INTO requests 
        (request_no, requester_id, employee_id, title, first_name, last_name, department, position, property, hotel_id, level_id, manager_name, systems_data, policy_agreed, requester_signature, status, current_approver_role, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_dept_head', 'dept_head', NOW())";

    $stmt = $db->prepare($insertSql);
    $stmt->execute([
        $requestNo,
        $requesterId,
        $employeeId,
        $title,
        $firstName,
        $lastName,
        $department,
        $position,
        $property,
        $hotelId,
        $levelId,
        $managerName,
        $jsonSystems,
        $policyAgreed,
        $requesterSignature
    ]);

    $newId = $db->lastInsertId();

    // Fetch the inserted record
    $stmt = $db->prepare("SELECT * FROM requests WHERE id = ?");
    $stmt->execute([$newId]);
    $requestRecord = $stmt->fetch();

    setFlash('success', "ยื่นคำขอเลขที่ {$requestNo} สำเร็จเรียบร้อยแล้ว");
    redirect('view_request.php?id=' . $newId);

} catch (Exception $e) {
    setFlash('error', 'เกิดข้อผิดพลาดในการบันทึกคำขอ: ' . $e->getMessage());
    redirect('new_request.php');
}

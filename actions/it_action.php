<?php
/**
 * Action: Handle IT Staff Provisioning & Signature
 * Step 3 in Workflow: Head -> Hotel -> IT Staff -> IT Manager
 * Records Effective Date, Account Expiry Date, Account Notes, and IT Staff Signature,
 * then advances status to 'pending_it_manager' for IT Manager final approval.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

// Security: Verify CSRF token
requireCsrf();

$currentUser = getCurrentUser();
if (!$currentUser) {
    setFlash('error', 'กรุณาเข้าสู่ระบบก่อนดำเนินการ');
    redirect('login.php');
}

$requestId = (int)($_POST['request_id'] ?? 0);
$action = trim($_POST['action'] ?? 'approved'); // 'approved' or 'rejected'
$notes = trim($_POST['notes'] ?? '');
$signature = trim($_POST['signature'] ?? '');
$effectiveDate = !empty($_POST['effective_date']) ? trim($_POST['effective_date']) : date('Y-m-d');
$expiryDate = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
$deletedDate = !empty($_POST['deleted_date']) ? trim($_POST['deleted_date']) : null;

$db = getDB();
$stmt = $db->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    setFlash('error', 'ไม่พบข้อมูลคำขอนี้');
    redirect('index.php');
}

if ($request['status'] !== 'pending_it_action' || !canITAction($currentUser, $request)) {
    setFlash('error', 'คำขอนี้ไม่ได้อยู่ในสถานะรอ IT ดำเนินการ หรือท่านไม่มีสิทธิ์ดำเนินการ');
    redirect('view_request.php?id=' . $requestId);
}

try {
    $itStaffName = $currentUser['full_name'];

    if ($action === 'rejected') {
        if (empty($notes)) {
            setFlash('error', 'กรุณาระบุเหตุผลในการปฏิเสธคำขอ');
            redirect('view_request.php?id=' . $requestId);
        }

        $stmt = $db->prepare("UPDATE requests SET status = 'rejected', current_approver_role = NULL, rejected_by = ?, reject_reason = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$itStaffName . " (IT Staff)", $notes, $requestId]);

        setFlash('warning', "ฝ่ายไอทีได้ปฏิเสธคำขอเรียบร้อยแล้ว");
        redirect('view_request.php?id=' . $requestId);

    } else {
        if (empty($signature)) {
            setFlash('error', 'กรุณาลงลายมือชื่อเจ้าหน้าที่ไอทีก่อนกดยืนยัน (IT Staff Signature is required)');
            redirect('view_request.php?id=' . $requestId);
        }

        if (empty($effectiveDate)) {
            setFlash('error', 'กรุณาระบุ Effective Date (วันเริ่มใช้งาน)');
            redirect('view_request.php?id=' . $requestId);
        }

        // Record IT Action details directly into requests table & advance status to pending_it_manager
        $stmt = $db->prepare("UPDATE requests SET 
            it_staff_id = ?,
            it_staff_name = ?,
            it_effective_date = ?,
            it_expiry_date = ?,
            it_deleted_date = ?,
            it_notes = ?,
            it_signature = ?,
            it_completed_at = NOW(),
            status = 'pending_it_manager',
            current_approver_role = 'it_manager',
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([
            $currentUser['id'],
            $itStaffName,
            $effectiveDate,
            $expiryDate,
            $deletedDate,
            $notes,
            $signature,
            $requestId
        ]);

        setFlash('success', "ฝ่ายไอที ({$itStaffName}) บันทึกวันเริ่มงานและลงลายมือชื่อสำเร็จ! ส่งต่อให้ IT Manager ลงนามอนุมัติขั้นสุดท้าย");
        redirect('view_request.php?id=' . $requestId);
    }

} catch (Exception $e) {
    setFlash('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูลไอที: ' . $e->getMessage());
    redirect('view_request.php?id=' . $requestId);
}


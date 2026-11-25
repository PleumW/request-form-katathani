<?php
/**
 * Action: Handle HR Personnel Department Filing
 * Records Filing Date and marks workflow as Completed
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    setFlash('error', 'กรุณาเข้าสู่ระบบก่อนดำเนินการ');
    redirect('login.php');
}

$requestId = (int)($_POST['request_id'] ?? 0);
$filingDate = trim($_POST['filing_date'] ?? date('Y-m-d'));
$resignedDate = !empty($_POST['resigned_date']) ? $_POST['resigned_date'] : null;
$notes = trim($_POST['notes'] ?? '');

$db = getDB();
$stmt = $db->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    setFlash('error', 'ไม่พบข้อมูลคำขอนี้');
    redirect('index.php');
}

if ($request['status'] !== 'pending_hr' || !canHROnlyAction($currentUser, $request)) {
    setFlash('error', 'คำขอนี้ไม่ได้อยู่ในสถานะรอ HR จัดเก็บแฟ้ม หรือท่านไม่มีสิทธิ์ดำเนินการ');
    redirect('view_request.php?id=' . $requestId);
}

try {
    // Mark request as completed
    $stmt = $db->prepare("UPDATE requests SET status = 'completed', current_approver_role = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$requestId]);

    setFlash('success', "ฝ่ายบุคคลบันทึกจัดเก็บเอกสารเข้าแฟ้มประวัติเสร็จสมบูรณ์แล้ว! กระบวนการครบถ้วนทุกขั้นตอน สามารถดาวน์โหลด PDF ได้ทันที");
    redirect('view_request.php?id=' . $requestId);

} catch (Exception $e) {
    setFlash('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูลฝ่ายบุคคล: ' . $e->getMessage());
    redirect('view_request.php?id=' . $requestId);
}

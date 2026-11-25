<?php
/**
 * Action: Handle Multi-Level Approvals & Signatures
 * Sequential Flow: Dept Head -> Hotel Manager -> IT Manager
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
    setFlash('error', 'กรุณาเข้าสู่ระบบก่อนลงนาม');
    redirect('login.php');
}

$requestId = (int)($_POST['request_id'] ?? 0);
$action = trim($_POST['action'] ?? ''); // 'approved' or 'rejected'
$signature = trim($_POST['signature'] ?? '');
$comment = trim($_POST['comment'] ?? '');

$db = getDB();
$stmt = $db->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    setFlash('error', 'ไม่พบข้อมูลคำขอนี้');
    redirect('index.php');
}

$curStatus = $request['status'];
$userLevel = (int)($currentUser['level_id'] ?? 1);
$depId = (int)($currentUser['dep_id'] ?? 0);
$depName = $currentUser['department'] ?? '';
$approverName = $currentUser['full_name'];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Determine if user has permission to approve at current stage (level_id >= 40: head, hotel, it)
$targetRole = null;
$userRole = determineRoleFromUser($currentUser);

if ($curStatus === 'pending_dept_head' && $userRole === 'dept_head') {
    $targetRole = 'dept_head';
} elseif ($curStatus === 'pending_hotel_manager' && $userRole === 'hotel_manager') {
    $targetRole = 'hotel_manager';
} elseif ($curStatus === 'pending_it_manager' && $userRole === 'it_manager') {
    $targetRole = 'it_manager';
} else {
    setFlash('error', 'ท่านไม่มีสิทธิ์ในการอนุมัติคำขอนี้ในสถานะปัจจุบัน หรือคำขอนี้ได้รับการดำเนินการแล้ว');
    redirect('view_request.php?id=' . $requestId);
}

try {
    if ($action === 'rejected') {
        if (empty($comment)) {
            setFlash('error', 'กรุณาระบุเหตุผลในการไม่อนุมัติ / ปฏิเสธคำขอ');
            redirect('view_request.php?id=' . $requestId);
        }

        // 1. Record rejection in approvals log
        $stmt = $db->prepare("INSERT INTO approvals (request_id, approver_role, approver_id, approver_name, action, signature, comment, ip_address, signed_at)
                              VALUES (?, ?, ?, ?, 'rejected', NULL, ?, ?, NOW())");
        $stmt->execute([$requestId, $targetRole, $currentUser['id'], $approverName, $comment, $ipAddress]);

        // 2. Update request status to rejected
        $stmt = $db->prepare("UPDATE requests SET status = 'rejected', current_approver_role = NULL, rejected_by = ?, reject_reason = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$approverName . " (" . strtoupper($targetRole) . ")", $comment, $requestId]);

        setFlash('warning', "บันทึกการปฏิเสธคำขอเรียบร้อยแล้ว");
        redirect('view_request.php?id=' . $requestId);

    } elseif ($action === 'approved') {
        if (empty($signature)) {
            setFlash('error', 'กรุณาลงลายมือชื่อก่อนกดยืนยันการอนุมัติ');
            redirect('view_request.php?id=' . $requestId);
        }

        // 1. Record approval log with signature
        $stmt = $db->prepare("INSERT INTO approvals (request_id, approver_role, approver_id, approver_name, action, signature, comment, ip_address, signed_at)
                              VALUES (?, ?, ?, ?, 'approved', ?, ?, ?, NOW())");
        $stmt->execute([$requestId, $targetRole, $currentUser['id'], $approverName, $signature, $comment, $ipAddress]);

        // 2. Advance to the next sequential stage
        if ($targetRole === 'dept_head') {
            $nextStatus = 'pending_hotel_manager';
            $nextRole = 'hotel_manager';
            $stmt = $db->prepare("UPDATE requests SET status = ?, current_approver_role = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$nextStatus, $nextRole, $requestId]);
            setFlash('success', "Department Head ({$approverName}) ลงนามอนุมัติสำเร็จ!");

        } elseif ($targetRole === 'hotel_manager') {
            $nextStatus = 'pending_it_action';
            $nextRole = 'it_staff';
            $stmt = $db->prepare("UPDATE requests SET status = ?, current_approver_role = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$nextStatus, $nextRole, $requestId]);
            setFlash('success', "Hotel Manager ({$approverName}) ลงนามอนุมัติสำเร็จ! ส่งต่องานให้ฝ่ายไอที (IT Staff) บันทึกวันเริ่มงานและลงลายมือชื่อ");

        } elseif ($targetRole === 'it_manager') {
            $nextStatus = 'completed';
            $nextRole = null;
            $stmt = $db->prepare("UPDATE requests SET status = ?, current_approver_role = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$nextStatus, $nextRole, $requestId]);
            setFlash('success', "IT Manager ({$approverName}) ลงนามอนุมัติขั้นสุดท้ายเรียบร้อยแล้ว! กระบวนการเสร็จสมบูรณ์ (Completed)");
        }

        redirect('view_request.php?id=' . $requestId);
    }

} catch (Exception $e) {
    setFlash('error', 'เกิดข้อผิดพลาดในการบันทึกการอนุมัติ: ' . $e->getMessage());
    redirect('view_request.php?id=' . $requestId);
}

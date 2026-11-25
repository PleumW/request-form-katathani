<?php

/**
 * View Request Details, Workflow Timeline & Approval Signature Action
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

// Security: Require authentication
if (!isLoggedIn()) {
    redirect('login.php');
}

$currentUser = getCurrentUser();
$requestId = (int)($_GET['id'] ?? 0);

$userLevel = (int)($currentUser['level_id'] ?? 1);
$userRole  = determineRoleFromUser($currentUser);
$isAdmin   = ($userLevel === 99 || $userRole === 'admin');
$isHRStaff = ($userRole === 'hr_staff' || (int)($currentUser['dep_id'] ?? 0) === 2 || stripos($currentUser['department'] ?? '', 'HR') !== false || stripos($currentUser['department'] ?? '', 'Human') !== false);

$db = getDB();
$stmt = $db->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    setFlash('error', 'ไม่พบรหัสคำขอที่ระบุ');
    redirect('index.php');
}

// Fetch Hotel & Level from ex_hotel & ex_level
$hotelInfo = !empty($request['hotel_id']) ? getHotelById($request['hotel_id']) : null;
$levelInfo = !empty($request['level_id']) ? getLevelById($request['level_id']) : null;

// Ensure regular requester can only access their own request
$isGenRequester = ($currentUser['level_id'] <= 3 && stripos($currentUser['position'] ?? '', 'IT') === false && stripos($currentUser['position'] ?? '', 'Personnel') === false);
$isOwner = ($request['requester_id'] == $currentUser['id']) || (!empty($currentUser['employee_id']) && $request['employee_id'] === $currentUser['employee_id']);
if ($isGenRequester && !$isOwner) {
    setFlash('error', 'คุณสามารถดูได้เฉพาะคำขอของตนเองเท่านั้น');
    redirect('index.php');
}

$systemsData = json_decode($request['systems_data'], true) ?: [];

// Build a flat key→label lookup map and parsed structure from the system matrix definition
$_matrixDef = getSystemMatrixDefinition();
$_labelMap = [];
foreach ($_matrixDef as $col) {
    foreach ($col as $sysKey => $sysDef) {
        if (!empty($sysDef['items'])) {
            foreach ($sysDef['items'] as $item) {
                $_labelMap[$item['id']] = $item['label'];
            }
        }
    }
}
// Helper: convert array of field-keys (that are truthy) into an array of their full labels
function resolveLabels(array $data, array $labelMap): array
{
    $labels = [];
    foreach ($data as $k => $v) {
        if ($v) {
            $labels[] = $labelMap[$k] ?? ucwords(str_replace('_', ' ', $k));
        }
    }
    return $labels;
}

// Function to parse the complete matrix for actionable IT fulfillment view
function parseMatrixSection($matrixDef, $systemsData, $labelMap)
{
    $parsed = ['left' => [], 'right' => [], 'active_count' => 0, 'permission_count' => 0, 'active_list' => []];

    $colorMap = [
        'hotel_network' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-200'],
        'katathani_app' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-200'],
        'opera_pms' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-200'],
        'check_scm' => ['bg' => 'bg-teal-50', 'text' => 'text-teal-600', 'border' => 'border-teal-200'],
        'emo' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-200'],
        'vpn' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'border' => 'border-rose-200'],
        'adell' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600', 'border' => 'border-sky-200'],
        'micros' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-200'],
        'eagle' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'border' => 'border-purple-200'],
        'sun' => ['bg' => 'bg-cyan-50', 'text' => 'text-cyan-600', 'border' => 'border-cyan-200'],
        'antlap' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-200'],
        'well_fixed_asset' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200'],
        'bs_minimart' => ['bg' => 'bg-violet-50', 'text' => 'text-violet-600', 'border' => 'border-violet-200'],
        'fcs' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-200'],
        'cctv' => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'border' => 'border-red-200'],
    ];

    foreach (['left' => 'left_column', 'right' => 'right_column'] as $side => $colKey) {
        foreach ($matrixDef[$colKey] as $catKey => $catDef) {
            $data = $systemsData[$catKey] ?? [];
            $hasData = false;
            $items = [];
            $customFields = [];
            $properties = [];
            $roles = [];

            if ($catKey === 'hotel_network') {
                if (!empty($data['win_login'])) {
                    $hasData = true;
                    $items[] = ['label' => 'Windows User Login'];
                }
                if (!empty($data['usb_allow'])) {
                    $hasData = true;
                    $items[] = ['label' => 'USB Allow'];
                }
                $fsVal = !empty($data['file_server']) && is_string($data['file_server']) && $data['file_server'] !== '1' ? trim($data['file_server']) : (!empty($data['file_server_drive']) ? trim($data['file_server_drive']) : '');
                $fsCheck = !empty($data['file_server_check']) || !empty($fsVal) || (!empty($data['file_server']) && $data['file_server'] == 1);
                if ($fsCheck) {
                    $hasData = true;
                    $items[] = ['label' => 'File Server' . (!empty($fsVal) ? ': ' . $fsVal : '')];
                }
                if (!empty($data['net_allow_all'])) {
                    $hasData = true;
                    $items[] = ['label' => 'Internet: Allow all website'];
                }
                if (!empty($data['net_block_some'])) {
                    $hasData = true;
                    $items[] = ['label' => 'Internet: Block some website'];
                }
                $emailVal = !empty($data['email']) && is_string($data['email']) && $data['email'] !== '1' ? trim($data['email']) : (!empty($data['email_address']) ? trim($data['email_address']) : '');
                $emailCheck = !empty($data['email_check']) || !empty($emailVal) || (!empty($data['email']) && $data['email'] == 1);
                if ($emailCheck) {
                    $hasData = true;
                    $items[] = ['label' => 'E-Mail' . (!empty($emailVal) ? ': ' . $emailVal : '')];
                }
            } elseif ($catKey === 'opera_pms') {
                if (!empty($data['property']) && is_array($data['property'])) {
                    foreach ($data['property'] as $propKey => $propVal) {
                        if ($propVal) {
                            $hasData = true;
                            $properties[] = ucwords(str_replace('_', ' ', $propKey));
                        }
                    }
                }
                if (!empty($data['roles']) && is_array($data['roles'])) {
                    foreach ($data['roles'] as $roleKey => $roleVal) {
                        if ($roleVal) {
                            $hasData = true;
                            $roles[] = $labelMap[$roleKey] ?? ucwords(str_replace('_', ' ', $roleKey));
                        }
                    }
                }
            } elseif ($catKey === 'cctv') {
                $cctvText = !empty($data['user_monitoring']) ? trim($data['user_monitoring']) : '';
                $cctvCheck = !empty($data['user_monitoring_check']) || !empty($cctvText);
                if ($cctvCheck) {
                    $hasData = true;
                    $items[] = ['label' => 'CCTV User Monitoring', 'detail' => $cctvText ?: 'เปิดสิทธิ์ดูกล้องวงจรปิด'];
                    if (!empty($cctvText)) {
                        $customFields['cctv'] = $cctvText;
                    }
                }
            } else {
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        if ($v) {
                            $hasData = true;
                            $items[] = [
                                'label' => $labelMap[$k] ?? ucwords(str_replace('_', ' ', $k)),
                                'detail' => ''
                            ];
                        }
                    }
                }
            }

            $count = ($catKey === 'opera_pms') ? (count($properties) + count($roles)) : count($items);
            $card = [
                'key' => $catKey,
                'title' => $catDef['title'],
                'icon' => $catDef['icon'],
                'colors' => $colorMap[$catKey] ?? ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'border' => 'border-slate-200'],
                'has_data' => $hasData,
                'items' => $items,
                'custom_fields' => $customFields,
                'properties' => $properties,
                'roles' => $roles,
                'count' => $count
            ];

            $parsed[$side][$catKey] = $card;
            if ($hasData) {
                $parsed['active_count']++;
                $parsed['permission_count'] += $count;
                $parsed['active_list'][] = $card;
            }
        }
    }
    return $parsed;
}
$matrixParsed = parseMatrixSection($_matrixDef, $systemsData, $_labelMap);

// Fetch Approvals
$stmt = $db->prepare("SELECT * FROM approvals WHERE request_id = ? ORDER BY id ASC");
$stmt->execute([$requestId]);
$approvals = $stmt->fetchAll();

$deptHeadApproval = null;
$hotelMgrApproval = null;
$itMgrApproval = null;

foreach ($approvals as $app) {
    if ($app['approver_role'] === 'dept_head' && $app['action'] === 'approved') $deptHeadApproval = $app;
    if ($app['approver_role'] === 'hotel_manager' && $app['action'] === 'approved') $hotelMgrApproval = $app;
    if ($app['approver_role'] === 'it_manager' && $app['action'] === 'approved') $itMgrApproval = $app;
}

// Fetch IT Action directly from request record
$itAction = (!empty($request['it_completed_at']) || !empty($request['it_effective_date'])) ? [
    'action_by'      => $request['it_staff_id'] ?? null,
    'it_staff_name'  => $request['it_staff_name'] ?? null,
    'effective_date' => $request['it_effective_date'] ?? null,
    'expiry_date'    => $request['it_expiry_date'] ?? null,
    'deleted_date'   => $request['it_deleted_date'] ?? null,
    'notes'          => $request['it_notes'] ?? null,
    'signature'      => $request['it_signature'] ?? null,
    'completed_at'   => $request['it_completed_at'] ?? null,
] : null;

// HR Action (Obsolete step - Document ends at IT Manager)
$hrAction = null;

$curStatusInfo = $STATUS_LIST[$request['status']] ?? ['label' => $request['status'], 'color' => 'bg-slate-100 text-slate-800', 'step' => 1];

$userRole = determineRoleFromUser($currentUser);
$isDeptHeadSigned = ($deptHeadApproval !== null);
$isITStaffSigned = ($itAction !== null);

// เมื่อหัวหน้าแผนก หรือ IT Staff เซ็นแล้ว ให้ซ่อนแบนเนอร์แจ้งเตือนสถานะรอการดำเนินการ
$hideStatusBanner = false;
if ($userRole === 'dept_head' && $isDeptHeadSigned) {
    $hideStatusBanner = true;
}
if ($userRole === 'it_staff' && $isITStaffSigned) {
    $hideStatusBanner = true;
}

$canUserApprove = canApprove($currentUser, $request);
$canUserDoIT = canITAction($currentUser, $request);
$canUserDoHR = canHROnlyAction($currentUser, $request);

$pageTitle = 'คำขอ ' . $request['request_no'] . ' - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto pb-16">

    <!-- Top Breadcrumb & Status Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 mb-1">
                <a href="index.php" class="hover:text-sky-600 transition"><?= lang('รายการคำขอ', 'Requests') ?></a>
                <span>/</span>
                <span class="font-mono text-slate-700 font-semibold"><?= htmlspecialchars($request['request_no']) ?></span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars((!empty($request['title']) ? $request['title'] . ' ' : '') . $request['first_name'] . ' ' . $request['last_name']) ?></h1>
                <span class="text-xs px-3 py-1 rounded-full font-bold border <?= $curStatusInfo['color'] ?>">
                    <i class="fa-solid fa-circle text-[8px] mr-1"></i>
                    <?= getStatusLabel($request['status']) ?>
                </span>
                <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md font-mono border border-slate-200">
                    ID: <?= htmlspecialchars($request['employee_id']) ?>
                </span>
            </div>
        </div>

        <!-- Action Tools (Print/PDF & Export) -->
        <div class="flex items-center space-x-3">
            <?php if ($isHRStaff || $isAdmin): ?>
                <a href="actions/export_requests.php?search=<?= urlencode($request['request_no']) ?>"
                    class="px-4 py-2 bg-emerald-50 border border-emerald-300 hover:bg-emerald-100 text-emerald-800 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 shadow-sm">
                    <i class="fa-solid fa-file-excel text-emerald-600 text-sm"></i>
                    <span>Export CSV</span>
                </a>
            <?php endif; ?>
            <a href="export_pdf.php?id=<?= $request['id'] ?>" target="_blank"
                class="px-4 py-2 bg-white border border-slate-300 hover:border-slate-400 text-slate-700 rounded-xl text-xs font-bold transition flex items-center space-x-2 shadow-sm">
                <i class="fa-solid fa-file-pdf text-rose-500 text-sm"></i>
                <span><?= lang('ดูแบบฟอร์ม PDF / พิมพ์', 'View PDF / Print') ?></span>
            </a>
        </div>
    </div>

    <!-- Stepper / Workflow Progress Bar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm mb-6">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 flex items-center space-x-2">

            <span><?= lang('ความคืบหน้ากระบวนการอนุมัติ ', 'Approval Workflow Progress') ?></span>
        </h3>

        <?php
        $steps = [
            1 => ['title' => lang('ผู้ยื่นคำขอ', 'Requester'), 'sub' => lang('ยอมรับเงื่อนไขแล้ว', 'Policy Accepted'), 'role' => 'Requester', 'done' => true, 'icon' => 'fa-clipboard-check'],
            2 => ['title' => lang('หัวหน้าแผนก', 'Department Head'), 'sub' => lang('ผู้อนุมัติลำดับที่ 1', 'Approver 1'), 'role' => 'dept_head', 'done' => (bool)$deptHeadApproval, 'icon' => 'fa-signature'],
            3 => ['title' => lang('ผู้จัดการโรงแรม', 'Hotel Manager'), 'sub' => lang('ผู้อนุมัติลำดับที่ 2', 'Approver 2'), 'role' => 'hotel_manager', 'done' => (bool)$hotelMgrApproval, 'icon' => 'fa-hotel'],
            4 => ['title' => lang('เจ้าหน้าที่ไอที', 'IT Staff'), 'sub' => lang('กำหนดวันเริ่ม & ลงนาม', 'Provision & Sign'), 'role' => 'it_staff', 'done' => (bool)$itAction, 'icon' => 'fa-wrench'],
            5 => ['title' => lang('ผู้จัดการไอที', 'IT Manager'), 'sub' => lang('ผู้อนุมัติขั้นสุดท้าย', 'Final Approver'), 'role' => 'it_manager', 'done' => (bool)$itMgrApproval, 'icon' => 'fa-network-wired'],
            6 => ['title' => lang('เสร็จสมบูรณ์', 'Completed'), 'sub' => lang('เสร็จสมบูรณ์', 'Completed'), 'role' => 'completed', 'done' => ($request['status'] === 'completed'), 'icon' => 'fa-circle-check']
        ];
        ?>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <?php foreach ($steps as $stepNum => $st):
                $isCurrent = false;
                if ($request['status'] === 'pending_dept_head' && $stepNum === 2) $isCurrent = true;
                if ($request['status'] === 'pending_hotel_manager' && $stepNum === 3) $isCurrent = true;
                if ($request['status'] === 'pending_it_action' && $stepNum === 4) $isCurrent = true;
                if ($request['status'] === 'pending_it_manager' && $stepNum === 5) $isCurrent = true;
                if ($request['status'] === 'completed' && $stepNum === 6) $isCurrent = true;

                $boxClass = 'bg-slate-50 border-slate-200 text-slate-400';
                $iconClass = 'bg-slate-200 text-slate-500';

                if ($st['done']) {
                    $boxClass = 'bg-emerald-50/70 border-emerald-300 text-emerald-900';
                    $iconClass = 'bg-emerald-500 text-white shadow-sm';
                } elseif ($isCurrent) {
                    $boxClass = 'bg-sky-50 border-sky-400 text-sky-950 ring-2 ring-sky-300 ring-offset-1';
                    $iconClass = 'bg-sky-600 text-white animate-pulse';
                } elseif ($request['status'] === 'rejected') {
                    $boxClass = 'bg-slate-50 border-slate-200 text-slate-400 opacity-60';
                }
            ?>
                <div class="p-3.5 rounded-xl border <?= $boxClass ?> flex flex-col justify-between transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold font-mono">0<?= $stepNum ?></span>
                        <div class="w-6 h-6 rounded-full <?= $iconClass ?> flex items-center justify-center text-[10px]">
                            <?php if ($st['done']): ?>
                                <i class="fa-solid fa-check"></i>
                            <?php else: ?>
                                <i class="fa-solid <?= $st['icon'] ?>"></i>
                            <?php endif; ?>


                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-bold truncate"><?= $st['title'] ?></div>
                        <div class="text-[10px] leading-tight mt-0.5 opacity-80"><?= $st['sub'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- REJECTION ALERT (IF REJECTED) -->
    <?php if ($request['status'] === 'rejected'): ?>
        <div class="bg-rose-50 border-2 border-rose-300 rounded-2xl p-6 mb-6 text-rose-900 shadow-sm">
            <div class="flex items-start space-x-3">
                <i class="fa-solid fa-circle-xmark text-rose-600 text-2xl mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="text-base font-bold"><?= lang('คำขอนี้ไม่ผ่านการอนุมัติ', 'Request Rejected') ?></h3>
                    <p class="text-xs text-rose-700 mt-1">
                        <?= lang('ปฏิเสธโดย:', 'Rejected by:') ?> <strong><?= htmlspecialchars($request['rejected_by'] ?? 'Approver') ?></strong>
                    </p>
                    <div class="mt-2 p-3 bg-white/80 rounded-xl border border-rose-200 text-xs">
                        <strong><?= lang('เหตุผล:', 'Reason:') ?></strong> <?= nl2br(htmlspecialchars($request['reject_reason'] ?? '-')) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>



    <!-- DYNAMIC ACTION CARD: APPROVAL SIGNATURE PAD (If it's the user's turn to approve) -->
    <?php if ($canUserApprove): ?>
        <div class="bg-gradient-to-br from-slate-700 to-indigo-600 text-white rounded-2xl p-6 sm:p-8 shadow-xl mb-8 border border-slate-800">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-800 pb-4 mb-5">
                <div>
                    <h2 class="text-xl font-bold mt-1"><?= lang('ลงนามอนุมัติ', 'Approve Request') ?></h2>
                    <p class="text-xs text-slate-300">
                        <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>
                    </p>
                </div>
                <div class="text-right text-xs text-slate-400">
                    <?= lang('ลำดับการอนุมัติ:', 'Approval Step:') ?>
                    <span class="font-bold text-white">
                        <?= $request['status'] === 'pending_dept_head' ? lang('หัวหน้าแผนก', 'Department Head') : ($request['status'] === 'pending_hotel_manager' ? lang('ผู้จัดการโรงแรม', 'Hotel Manager') : lang('ผู้จัดการฝ่ายไอที', 'IT Manager')) ?>
                    </span>
                </div>
            </div>

            <form id="approvalForm" action="actions/approve_request.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                <input type="hidden" name="action" id="actionType" value="approved">
                <input type="hidden" name="signature" id="approverSignatureData">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                    <!-- Digital Signature Pad -->
                    <div class="lg:col-span-7">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-bold text-slate-200 flex items-center space-x-1.5">
                                <span><?= lang('ลงลายมือชื่ออนุมัติ', 'Digital Signature') ?> <span class="text-rose-400">*</span></span>
                            </label>
                            <div>
                                <button type="button" id="clearApproverSigBtn" class="text-xs text-rose-300 hover:text-rose-100 underline cursor-pointer">
                                    <?= lang('ล้าง', 'Clear') ?>
                                </button>
                            </div>
                        </div>

                        <!-- Canvas Box -->
                        <div class="bg-white rounded-xl overflow-hidden border-2 border-slate-700 relative shadow-inner">
                            <canvas id="approverCanvas" class="w-full h-40 cursor-crosshair touch-none"></canvas>
                            <div class="absolute bottom-2 right-3 text-[10px] text-slate-400 pointer-events-none select-none">
                                <?= lang('เซ็นชื่อ', 'Sign here') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Comment & Action Buttons -->
                    <div class="lg:col-span-5 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-200 mb-1.5">
                                <?= lang('ความเห็นเพิ่มเติม', 'Comment (Optional)') ?>
                            </label>
                            <textarea name="comment" id="commentText" rows="3" placeholder="<?= lang('ระบุข้อความหรือบันทึกเพิ่มเติม (ถ้ามี)', 'Enter optional comment or notes') ?>"
                                class="w-full px-3.5 py-2 bg-slate-800/90 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-sky-500 placeholder-slate-500"></textarea>
                        </div>

                        <div class="pt-2 flex flex-col sm:flex-row gap-3">
                            <!-- Approve Button -->
                            <button type="button" id="btnApprove" class="flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-900/30 transition flex items-center justify-center space-x-2">
                                <span><?= lang('ลงนามอนุมัติ', 'Approve') ?></span>
                            </button>

                            <!-- Reject Button -->
                            <button type="button" id="btnReject" class="py-3 px-4 bg-slate-800 hover:bg-rose-950 hover:text-rose-200 text-slate-300 rounded-xl text-xs font-bold border border-slate-700 hover:border-rose-800 transition flex items-center justify-center space-x-1.5">
                                <span><?= lang('ไม่อนุมัติ', 'Reject') ?></span>
                            </button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    <?php elseif (!$canUserApprove && !$hideStatusBanner && in_array($request['status'], ['pending_dept_head', 'pending_hotel_manager', 'pending_it_manager'])): ?>
        <!-- Current Status Notification Banner -->
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-center space-x-3 text-xs text-amber-900">
            <i class="fa-solid fa-clock text-amber-500 text-lg shrink-0"></i>
            <div>
                <span class="font-bold"><?= lang('สถานะปัจจุบัน:', 'Current Status:') ?></span>
                <?= getStatusLabel($request['status']) ?>
                (<?= lang('ผู้อนุมัติที่ต้องดำเนินการ:', 'Action required by:') ?> <strong><?= strtoupper($request['current_approver_role']) ?></strong>)
            </div>
        </div>
    <?php endif; ?>



    <!-- DYNAMIC ACTION CARD: IT DEPARTMENT USE (Step 3: Awaiting IT Staff Action & Signature) -->
    <?php if ($canUserDoIT): ?>
        <div class="bg-gradient-to-br from-slate-900 to-sky-950 text-white rounded-2xl p-6 sm:p-8 shadow-xl mb-8 border border-sky-800">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-800 pb-4 mb-5">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-sky-400 bg-sky-400/10 px-2.5 py-0.5 rounded-full border border-sky-400/20">IT Staff Action Required</span>
                    <h2 class="text-xl font-bold mt-1"><?= lang('ฝ่ายไอทีกำหนดวันเริ่มงาน & ลงลายมือชื่อ', 'IT Staff Provisioning & Signature') ?></h2>
                    <p class="text-xs text-slate-300">
                        <?= lang('คำขอผ่านการอนุมัติระดับโรงแรมแล้ว โปรดระบุข้อมูลวันเริ่มงานและลงลายมือชื่อของท่าน เพื่อส่งต่อให้ IT Manager ลงนามอนุมัติขั้นสุดท้าย', 'Approved by Hotel Management. Please specify effective date and sign to forward to IT Manager.') ?>
                    </p>
                </div>
                <div class="text-right text-xs text-slate-400">
                    <?= lang('ลำดับขั้นตอน:', 'Step:') ?> <span class="font-bold text-sky-300">3. IT Staff Provisioning & Sign</span>
                </div>
            </div>

            <form id="itActionForm" action="actions/it_action.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                <input type="hidden" name="action" id="itActionType" value="approved">
                <input type="hidden" name="signature" id="itStaffSignatureData">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-200 mb-1.5">
                            <?= lang('วันเริ่มใช้งาน', 'Effective Date') ?> <span class="text-rose-400">*</span>
                        </label>
                        <input type="date" name="effective_date" id="itEffectiveDate" required value="<?= date('Y-m-d') ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-200 mb-1.5">
                            <?= lang('วันหมดอายุบัญชี (ถ้ามี)', 'Account Expiry Date (Optional)') ?>
                        </label>
                        <input type="date" name="expiry_date" id="itExpiryDate"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-200 mb-1.5">
                        <?= lang('ข้อมูลบัญชีผู้ใช้ / หมายเหตุการเปิดระบบ', 'Account Notes / Provisioning Details') ?>
                    </label>
                    <textarea name="notes" id="itNotes" rows="2" placeholder="<?= lang('เช่น สร้างบัญชี Windows Login, Email และกำหนดสิทธิ์เรียบร้อยแล้ว', 'e.g. Created Windows Login and Email accounts') ?>"
                        class="w-full px-3.5 py-2 bg-slate-800/90 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-sky-500 placeholder-slate-500"></textarea>
                </div>

                <!-- Digital Signature Pad for IT Staff -->
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-bold text-slate-200 flex items-center space-x-1.5">
                            <i class="fa-solid fa-signature text-sky-400"></i>
                            <span><?= lang('ลายมือชื่อเจ้าหน้าที่ไอที', 'IT Staff Signature') ?> <span class="text-rose-400">*</span></span>
                        </label>
                        <div class="flex items-center space-x-2">
                            <button type="button" id="mockItStaffSignBtn" class="text-xs text-sky-300 hover:text-sky-100 underline">
                                Demo Sign
                            </button>
                            <span class="text-slate-600">&bull;</span>
                            <button type="button" id="clearItStaffSigBtn" class="text-xs text-rose-300 hover:text-rose-100 underline">
                                <?= lang('ล้าง', 'Clear') ?>
                            </button>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl overflow-hidden border-2 border-slate-700 relative shadow-inner">
                        <canvas id="itStaffCanvas" class="w-full h-36 cursor-crosshair touch-none"></canvas>
                        <div class="absolute bottom-2 right-3 text-[10px] text-slate-400 pointer-events-none select-none">
                            <?= lang('เซ็นชื่อเจ้าหน้าที่ไอทีด้วยนิ้วมือหรือเมาส์', 'Sign here using touch or mouse') ?>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex flex-col sm:flex-row gap-3 justify-end">
                    <button type="button" id="btnItReject" class="py-3 px-5 bg-slate-800 hover:bg-rose-950 hover:text-rose-200 text-slate-300 rounded-xl text-xs font-bold border border-slate-700 hover:border-rose-800 transition flex items-center justify-center space-x-1.5">
                        <i class="fa-solid fa-ban text-rose-400"></i>
                        <span><?= lang('ปฏิเสธคำขอ', 'Reject') ?></span>
                    </button>
                    <button type="button" id="btnItSubmit" class="py-3 px-6 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-sky-900/30 transition flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span><?= lang('ลงนามและส่งให้ IT Manager อนุมัติ', 'Sign & Forward to IT Manager') ?></span>
                    </button>
                </div>
            </form>
        </div>
    <?php elseif ($request['status'] === 'pending_it_action' && !$canUserDoIT && !$hideStatusBanner): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-6 flex items-center space-x-3 text-xs text-blue-900">
            <i class="fa-solid fa-circle-info text-blue-500 text-lg shrink-0"></i>
            <div>
                <span class="font-bold"><?= lang('สถานะปัจจุบัน:', 'Current Status:') ?></span>
                <?= lang('กำลังรอฝ่ายไอที (IT Staff) บันทึกวันเริ่มงาน (Effective Date) และลงลายมือชื่อ', 'Awaiting IT Staff provisioning & signature') ?>
            </div>
        </div>
    <?php endif; ?>



    <!-- DOCUMENT PREVIEW CONTAINER (Matching FM-KTNC-IT-003 Rev.00) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">

        <!-- Doc Header -->
        <div class="p-6 sm:p-8 bg-slate-50/50 border-b border-slate-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">REQUEST FORM</h2>
                </div>
                <div class="text-right text-xs text-slate-500 font-mono">
                    <div>Katathani Collection</div>
                </div>
            </div>

            <!-- Employee & Property Summary Box -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-5 border-t border-slate-200/80 text-xs">
                <div>
                    <span class="text-slate-400 block mb-0.5"><?= lang('พนักงาน:', 'Employee:') ?></span>
                    <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars((!empty($request['title']) ? $request['title'] . ' ' : '') . $request['first_name'] . ' ' . $request['last_name']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block mb-0.5"><?= lang('รหัสพนักงาน:', 'Employee ID:') ?></span>
                    <span class="font-bold text-slate-800 font-mono text-sm"><?= htmlspecialchars($request['employee_id']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block mb-0.5"><?= lang('แผนก:', 'Department:') ?></span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($request['department']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block mb-0.5"><?= lang('ตำแหน่ง:', 'Position:') ?></span>
                    <div class="mt-0.5">
                        <span class="font-bold text-slate-800"><?= htmlspecialchars($request['position']) ?></span>
                    </div>
                </div>
                <div class="col-span-2">
                    <span class="text-slate-400 block mb-0.5"><?= lang('สถานที่ปฏิบัติงาน:', 'Property & Hotel:') ?></span>
                    <span class="font-bold text-sky-800 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-200 inline-flex items-center space-x-1.5">
                        <i class="fa-solid fa-hotel text-sky-600"></i>
                        <span><?= htmlspecialchars($hotelInfo ? $hotelInfo['hotel_name'] : $request['property']) ?></span>
                        <?php if ($hotelInfo && !empty($hotelInfo['property_name'])): ?>
                            <span class="text-[11px] text-sky-600 font-semibold">(<?= htmlspecialchars($hotelInfo['property_name']) ?>)</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="col-span-2">
                    <span class="text-slate-400 block mb-0.5"><?= lang('ผู้บังคับบัญชา:', 'Direct Manager:') ?></span>
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($request['manager_name']) ?></span>
                </div>
                <div class="col-span-2 sm:col-span-4">
                    <span class="text-slate-400 block mb-0.5"><?= lang('วันที่ยื่นคำขอ:', 'Submission Date:') ?></span>
                    <span class="font-semibold text-slate-800"><?= date('d/m/Y H:i', strtotime($request['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- System Matrix Readonly Summary: Actionable Checklist for IT / Fulfillment -->
        <div class="p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 mb-5 border-b border-slate-200/80 gap-3">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800 flex items-center space-x-2">
                        <i class="fa-solid fa-layer-group text-sky-600"></i>
                        <span><?= lang('รายการสิทธิ์และระบบที่ร้องขอ', 'Requested Systems & Permissions') ?></span>
                    </h3>

                </div>


            </div>



            <!-- Two Columns Matrix Layout (Exact match with Form FM-KTNC-IT-003) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                <!-- LEFT COLUMN -->
                <div class="space-y-4">

                    <?php foreach ($matrixParsed['left'] as $catKey => $card): ?>
                        <?php if ($card['has_data']): ?>
                            <!-- Active Category Card -->
                            <div class="matrix-card matrix-card-active bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-sm space-y-3.5 transition hover:border-sky-300">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <div class="flex items-center space-x-2.5">

                                        <h4 class="font-bold text-slate-800 text-sm leading-tight"><?= htmlspecialchars($card['title']) ?></h4>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center space-x-1 shadow-2xs">

                                        <span><?= lang('ขอสิทธิ์', 'Requested') ?> (<?= $card['count'] ?>)</span>
                                    </span>
                                </div>

                                <?php if ($catKey === 'opera_pms'): ?>
                                    <!-- Opera PMS -->
                                    <div class="space-y-3 text-xs">
                                        <?php if (!empty($card['properties'])): ?>
                                            <div class="p-2.5 rounded-xl bg-amber-50/80 border border-amber-200/80 flex flex-wrap items-center gap-2">
                                                <span class="font-bold text-amber-950 flex items-center space-x-1">
                                                    <span>Property / โรงแรมที่ขอใช้งาน:</span>
                                                </span>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <?php foreach ($card['properties'] as $prop): ?>
                                                        <span class="px-2.5 py-0.5 rounded-md bg-amber-200/80 text-amber-950 text-xs font-bold border border-amber-300/80 shadow-2xs"><?= htmlspecialchars($prop) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($card['roles'])): ?>
                                            <div class="font-bold text-slate-700 mb-1 flex items-center space-x-1.5">

                                                <span>สิทธิ์และตำแหน่งใน Opera ที่ขอ (Roles & Permissions):</span>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <?php foreach ($card['roles'] as $role): ?>
                                                    <div class="flex items-center space-x-2 p-2 rounded-lg bg-amber-50/40 border border-amber-100 text-slate-800 font-semibold">
                                                        <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                                                        <span><?= htmlspecialchars($role) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-slate-400 italic">ไม่ได้ระบุสิทธิ์ย่อยในระบบ Opera</p>
                                        <?php endif; ?>
                                    </div>

                                <?php else: ?>
                                    <!-- Standard Checkboxes (Including Hotel Network) -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                        <?php foreach ($card['items'] as $item): ?>
                                            <div class="flex items-center space-x-2 p-2 rounded-lg bg-slate-50 border border-slate-200/70 font-semibold text-slate-800">
                                                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                                                <span><?= htmlspecialchars($item['label']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Inactive Category Card (Hidden by default, shown in Full Form mode) -->
                            <div class="matrix-card matrix-card-inactive hidden p-3 rounded-xl bg-slate-50/60 border border-dashed border-slate-200/90 flex items-center justify-between text-xs text-slate-400 transition hover:border-slate-300">
                                <div class="flex items-center space-x-2.5">
                                    <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center text-xs">
                                        <i class="<?= htmlspecialchars($card['icon']) ?>"></i>
                                    </span>
                                    <span class="font-medium text-slate-500"><?= htmlspecialchars($card['title']) ?></span>
                                </div>
                                <span class="text-[10px] bg-slate-200/60 text-slate-400 px-2 py-0.5 rounded font-medium"><?= lang('ไม่ได้ขอสิทธิ์', 'Not Requested') ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="space-y-4">


                    <?php foreach ($matrixParsed['right'] as $catKey => $card): ?>
                        <?php if ($card['has_data']): ?>
                            <!-- Active Category Card -->
                            <div class="matrix-card matrix-card-active bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-sm space-y-3.5 transition hover:border-sky-300">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <div class="flex items-center space-x-2.5">

                                        <h4 class="font-bold text-slate-800 text-sm leading-tight"><?= htmlspecialchars($card['title']) ?></h4>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center space-x-1 shadow-2xs">

                                        <span><?= lang('ขอสิทธิ์', 'Requested') ?> (<?= $card['count'] ?>)</span>
                                    </span>
                                </div>

                                <?php if ($catKey === 'cctv'): ?>
                                    <!-- CCTV Items -->
                                    <div class="space-y-2.5 text-xs">
                                        <div class="flex items-center space-x-2 p-2 rounded-lg bg-slate-50 border border-slate-200/70 font-semibold text-slate-800">
                                            <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                                            <span>CCTV User Monitoring (เปิดสิทธิ์ดูกล้องวงจรปิด)</span>
                                        </div>
                                        <?php if (!empty($card['custom_fields']['cctv'])): ?>
                                            <div class="p-3 rounded-xl bg-red-50/70 border border-red-200/80 text-xs space-y-1">
                                                <span class="font-bold text-red-950 flex items-center space-x-1.5">
                                                    <i class="fa-solid fa-video text-red-600"></i>
                                                    <span>โซน / หมายเลขกล้อง / แผนกที่ต้องการ:</span>
                                                </span>
                                                <div class="bg-white p-2.5 rounded-lg border border-red-200 font-medium text-red-900 text-xs shadow-2xs">
                                                    <?= htmlspecialchars($card['custom_fields']['cctv']) ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                <?php else: ?>
                                    <!-- Standard Checkboxes -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                        <?php foreach ($card['items'] as $item): ?>
                                            <div class="flex items-center space-x-2 p-2 rounded-lg bg-slate-50 border border-slate-200/70 font-semibold text-slate-800">
                                                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                                                <span><?= htmlspecialchars($item['label']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Inactive Category Card (Hidden by default, shown in Full Form mode) -->
                            <div class="matrix-card matrix-card-inactive hidden p-3 rounded-xl bg-slate-50/60 border border-dashed border-slate-200/90 flex items-center justify-between text-xs text-slate-400 transition hover:border-slate-300">
                                <div class="flex items-center space-x-2.5">
                                    <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center text-xs">
                                        <i class="<?= htmlspecialchars($card['icon']) ?>"></i>
                                    </span>
                                    <span class="font-medium text-slate-500"><?= htmlspecialchars($card['title']) ?></span>
                                </div>
                                <span class="text-[10px] bg-slate-200/60 text-slate-400 px-2 py-0.5 rounded font-medium"><?= lang('ไม่ได้ขอสิทธิ์', 'Not Requested') ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>

        <script>
            function setMatrixFilter(mode) {
                var inactives = document.querySelectorAll('.matrix-card-inactive');
                var btnActive = document.getElementById('btnViewActive');
                var btnAll = document.getElementById('btnViewAll');
                if (mode === 'active') {
                    inactives.forEach(function(el) {
                        el.classList.add('hidden');
                    });
                    if (btnActive) {
                        btnActive.className = 'px-3 py-1.5 text-xs font-bold rounded-lg bg-white text-sky-700 shadow-xs transition flex items-center space-x-1.5 cursor-pointer';
                    }
                    if (btnAll) {
                        btnAll.className = 'px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center space-x-1.5 cursor-pointer';
                    }
                } else {
                    inactives.forEach(function(el) {
                        el.classList.remove('hidden');
                    });
                    if (btnActive) {
                        btnActive.className = 'px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center space-x-1.5 cursor-pointer';
                    }
                    if (btnAll) {
                        btnAll.className = 'px-3 py-1.5 text-xs font-bold rounded-lg bg-white text-sky-700 shadow-xs transition flex items-center space-x-1.5 cursor-pointer';
                    }
                }
            }
        </script>

        <!-- SECTION: SIGNATURES DISPLAY (The 3 Main Approvers + Requester Policy Acceptance) -->
        <div class="p-6 sm:p-8 bg-slate-50/70 border-t border-slate-200">

            <!-- Requester Policy Acceptance Row (ผู้ยื่นคำขอติ๊กยอมรับเงื่อนไข) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 mb-5 border-b border-slate-200/80 gap-3">
                <div class="flex items-center space-x-2.5 text-xs text-slate-700">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs">
                        <i class="fa-solid fa-check"></i>
                    </span>
                    <div>
                        <span class="font-bold text-slate-800">I have read and understand all relevant IT policies</span>
                        <span class="text-slate-400 block text-[11px]"><?= lang('ข้าพเจ้าได้อ่านและเข้าใจนโยบายความมั่นคงปลอดภัยสารสนเทศของโรงแรม และยอมรับการปฏิบัติตามอย่างเคร่งครัด', 'I have read and understood the hotel IT Security Policy and agree to strictly comply.') ?></span>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="text-right text-xs">
                        <span class="text-slate-400 block text-[10px]"><?= lang('ผู้ยื่นคำขอ:', 'Requester (Acceptance):') ?></span>
                        <span class="font-bold text-slate-800">( <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?> )</span>
                    </div>
                    <span class="inline-flex items-center space-x-1 text-xs bg-emerald-100 text-emerald-800 font-bold px-3 py-1.5 rounded-xl border border-emerald-200">
                        <i class="fa-solid fa-check-circle text-emerald-600"></i>
                        <span><?= lang('ยอมรับเงื่อนไขแล้ว', 'Policy Accepted') ?></span>
                    </span>
                </div>
            </div>

            <!-- Approval Signatures Header: ลำดับการอนุมัติ -->
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center space-x-2">
                    <i class="fa-solid fa-signature text-sky-600"></i>
                    <span><?= lang('การลงนามอนุมัติตามลำดับ', 'Approval Signatures') ?></span>
                </h3>
                <span class="text-[11px] text-slate-500 font-medium"><?= lang('ลำดับ: Head &rarr; Hotel &rarr; IT Staff &rarr; IT Manager', 'Order: Head &rarr; Hotel &rarr; IT Staff &rarr; IT Manager') ?></span>
            </div>

            <!-- 3 Slots: Department Head, Hotel Manager, IT Manager -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <!-- 1. Department Head -->
                <div class="bg-white p-4 rounded-xl border <?= $deptHeadApproval ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200' ?> flex flex-col justify-between h-44 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800"><?= lang('หัวหน้าแผนก', 'Department Head') ?></span>
                        <?php if ($deptHeadApproval): ?>
                            <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full font-bold"><?= lang('อนุมัติแล้ว', 'Approved') ?></span>
                        <?php else: ?>
                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= lang('รออนุมัติ', 'Pending') ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="my-auto text-center flex items-center justify-center">
                        <?php if ($deptHeadApproval && !empty($deptHeadApproval['signature'])): ?>
                            <img src="<?= $deptHeadApproval['signature'] ?>" alt="Dept Head Signature" class="max-h-16 max-w-full object-contain">
                        <?php else: ?>
                            <div class="text-[11px] text-slate-300 italic flex flex-col items-center">
                                <i class="fa-solid fa-pen-fancy text-lg mb-1 opacity-40"></i>
                                <span><?= lang('รอดำเนินการลงนาม', 'Awaiting signature') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-slate-100 pt-2 text-[11px] text-slate-500">
                        <?php if ($deptHeadApproval): ?>
                            <div class="font-bold text-slate-700 truncate"><?= htmlspecialchars($deptHeadApproval['approver_name']) ?></div>
                            <div class="text-[10px] text-slate-400"><?= date('d/m/Y H:i', strtotime($deptHeadApproval['signed_at'])) ?></div>
                        <?php else: ?>
                            <div class="text-slate-400"><?= lang('ยังไม่มีลายเซ็น', 'Not signed yet') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Hotel Manager -->
                <div class="bg-white p-4 rounded-xl border <?= $hotelMgrApproval ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200' ?> flex flex-col justify-between h-44 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800"><?= lang('ผู้จัดการโรงแรม', 'Hotel Manager') ?></span>
                        <?php if ($hotelMgrApproval): ?>
                            <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full font-bold"><?= lang('อนุมัติแล้ว', 'Approved') ?></span>
                        <?php else: ?>
                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= lang('รออนุมัติ', 'Pending') ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="my-auto text-center flex items-center justify-center">
                        <?php if ($hotelMgrApproval && !empty($hotelMgrApproval['signature'])): ?>
                            <img src="<?= $hotelMgrApproval['signature'] ?>" alt="Hotel Manager Signature" class="max-h-16 max-w-full object-contain">
                        <?php else: ?>
                            <div class="text-[11px] text-slate-300 italic flex flex-col items-center">
                                <i class="fa-solid fa-pen-fancy text-lg mb-1 opacity-40"></i>
                                <span><?= lang('รอดำเนินการลงนาม', 'Awaiting signature') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-slate-100 pt-2 text-[11px] text-slate-500">
                        <?php if ($hotelMgrApproval): ?>
                            <div class="font-bold text-slate-700 truncate"><?= htmlspecialchars($hotelMgrApproval['approver_name']) ?></div>
                            <div class="text-[10px] text-slate-400"><?= date('d/m/Y H:i', strtotime($hotelMgrApproval['signed_at'])) ?></div>
                        <?php else: ?>
                            <div class="text-slate-400"><?= lang('ยังไม่มีลายเซ็น', 'Not signed yet') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. IT Manager -->
                <div class="bg-white p-4 rounded-xl border <?= $itMgrApproval ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200' ?> flex flex-col justify-between h-44 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800"><?= lang('ผู้จัดการฝ่ายไอที', 'IT Manager') ?></span>
                        <?php if ($itMgrApproval): ?>
                            <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full font-bold"><?= lang('อนุมัติแล้ว', 'Approved') ?></span>
                        <?php else: ?>
                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= lang('รออนุมัติ', 'Pending') ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="my-auto text-center flex items-center justify-center">
                        <?php if ($itMgrApproval && !empty($itMgrApproval['signature'])): ?>
                            <img src="<?= $itMgrApproval['signature'] ?>" alt="IT Manager Signature" class="max-h-16 max-w-full object-contain">
                        <?php else: ?>
                            <div class="text-[11px] text-slate-300 italic flex flex-col items-center">
                                <i class="fa-solid fa-pen-fancy text-lg mb-1 opacity-40"></i>
                                <span><?= lang('รอดำเนินการลงนาม', 'Awaiting signature') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-slate-100 pt-2 text-[11px] text-slate-500">
                        <?php if ($itMgrApproval): ?>
                            <div class="font-bold text-slate-700 truncate"><?= htmlspecialchars($itMgrApproval['approver_name']) ?></div>
                            <div class="text-[10px] text-slate-400"><?= date('d/m/Y H:i', strtotime($itMgrApproval['signed_at'])) ?></div>
                        <?php else: ?>
                            <div class="text-slate-400"><?= lang('ยังไม่มีลายเซ็น', 'Not signed yet') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- IT & HR FULFILLMENT FOOTER SUMMARY -->
            <div class="mt-6 pt-5 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <!-- IT Section -->
                <div class="bg-white p-4 rounded-xl border border-slate-200">
                    <div class="font-bold text-slate-800 mb-2 flex items-center space-x-1.5">
                        <i class="fa-solid fa-wrench text-sky-600"></i>
                        <span>IT DEPARTMENT USE:</span>
                    </div>
                    <?php if ($itAction): ?>
                        <div class="space-y-1 text-slate-600">
                            <div><strong>Effective Date:</strong> <?= date('d/m/Y', strtotime($itAction['effective_date'])) ?></div>
                            <div><strong>Account Expiry Date:</strong> <?= $itAction['expiry_date'] ? date('d/m/Y', strtotime($itAction['expiry_date'])) : '-' ?></div>
                            <div><strong>Operator:</strong> <?= htmlspecialchars($itAction['it_staff_name'] ?? 'IT Staff') ?></div>
                            <?php if ($itAction['notes']): ?>
                                <div class="text-[11px] text-slate-500"><em>Note: <?= htmlspecialchars($itAction['notes']) ?></em></div>
                            <?php endif; ?>


                            <?php if (!empty($itAction['signature'])): ?>
                                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-slate-500 font-semibold text-[11px]"><?= lang('ลายเซ็น IT Staff:', 'IT Staff Signature:') ?></span>
                                    <img src="<?= $itAction['signature'] ?>" alt="IT Staff Signature" class="max-h-12 max-w-[130px] object-contain">
                                </div>
                            <?php endif; ?>


                        </div>
                    <?php else: ?>
                        <p class="text-slate-400 italic"><?= lang('อยู่ระหว่างรอดำเนินการ ', 'Awaiting provisioning ') ?></p>
                    <?php endif; ?>


                </div>

                <!-- HR Section -->
                <div class="bg-white p-4 rounded-xl border border-slate-200">
                    <div class="font-bold text-slate-800 mb-2 flex items-center space-x-1.5">
                        <i class="fa-solid fa-folder-open text-purple-600"></i>
                        <span>PERSONNEL DEPARTMENT USE (HR):</span>
                    </div>
                    <?php if ($hrAction): ?>
                        <div class="space-y-1 text-slate-600">
                            <div><strong>Filing Date:</strong> <?= date('d/m/Y', strtotime($hrAction['filing_date'])) ?></div>
                            <div><strong>Recorded By:</strong> <?= htmlspecialchars($hrAction['hr_staff_name'] ?? 'HR Officer') ?></div>
                            <?php if ($hrAction['notes']): ?>
                                <div class="text-[11px] text-slate-500"><em>Note: <?= htmlspecialchars($hrAction['notes']) ?></em></div>
                            <?php endif; ?>


                        </div>
                    <?php else: ?>
                        <p class="text-slate-400 italic"><?= lang('รอเอกสารเสร็จสิ้น', 'Waiting for the documents to be completed') ?></p>
                    <?php endif; ?>


                </div>
            </div>

        </div>

    </div>

</div>

<?php if ($canUserApprove): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var canvas = document.getElementById('approverCanvas');
            if (!canvas) return;

            var sigPad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(15, 23, 42)',
                minWidth: 1.5,
                maxWidth: 3.5
            });

            function resizeApproverCanvas() {
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                sigPad.clear();
            }
            window.addEventListener("resize", resizeApproverCanvas);
            resizeApproverCanvas();

            var clearBtn = document.getElementById('clearApproverSigBtn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    sigPad.clear();
                });
            }

            var mockBtn = document.getElementById('mockApproverSignBtn');
            if (mockBtn) {
                mockBtn.addEventListener('click', function() {
                    var ctx = canvas.getContext("2d");
                    sigPad.clear();
                    ctx.beginPath();
                    ctx.lineWidth = 2.5;
                    ctx.strokeStyle = "#0f172a";
                    ctx.moveTo(40, 70);
                    ctx.bezierCurveTo(70, 20, 110, 130, 160, 60);
                    ctx.bezierCurveTo(180, 40, 210, 110, 260, 70);
                    ctx.stroke();
                    ctx.font = "italic 15px sans-serif";
                    ctx.fillStyle = "#0f172a";
                    ctx.fillText("<?= addslashes($currentUser['full_name']) ?>", 50, 110);
                });
            }

            // Handle Approve
            var btnApprove = document.getElementById('btnApprove');
            if (btnApprove) {
                btnApprove.addEventListener('click', function() {
                    if (sigPad.isEmpty()) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'กรุณาลงลายมือชื่อ',
                                text: 'โปรดเซ็นลายเซ็นของท่านในกล่องก่อนกดยืนยันการอนุมัติ',
                                confirmButtonColor: '#059669'
                            });
                        } else {
                            alert('กรุณาลงลายมือชื่อก่อนกดยืนยันการอนุมัติ');
                        }
                        return;
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'ยืนยันการลงนามอนุมัติ?',
                            text: 'คำขอจะถูกบันทึกพร้อมลายเซ็นของท่านและส่งต่อไปยังลำดับถัดไป',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#059669',
                            cancelButtonColor: '#64748b',
                            confirmButtonText: 'ยืนยันอนุมัติ',
                            cancelButtonText: 'ยกเลิก'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('actionType').value = 'approved';
                                document.getElementById('approverSignatureData').value = sigPad.toDataURL('image/png');
                                document.getElementById('approvalForm').submit();
                            }
                        });
                    } else {
                        if (confirm('ยืนยันการลงนามอนุมัติคำขอนี้?')) {
                            document.getElementById('actionType').value = 'approved';
                            document.getElementById('approverSignatureData').value = sigPad.toDataURL('image/png');
                            document.getElementById('approvalForm').submit();
                        }
                    }
                });
            }

            // Handle Reject
            var btnReject = document.getElementById('btnReject');
            if (btnReject) {
                btnReject.addEventListener('click', function() {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'ระบุเหตุผลในการไม่อนุมัติ',
                            input: 'textarea',
                            inputPlaceholder: 'กรอกเหตุผลที่ปฏิเสธคำขอนี้...',
                            inputAttributes: {
                                'aria-label': 'เหตุผลการปฏิเสธ'
                            },
                            showCancelButton: true,
                            confirmButtonColor: '#e11d48',
                            confirmButtonText: 'ยืนยันปฏิเสธคำขอ',
                            cancelButtonText: 'ยกเลิก',
                            preConfirm: (reason) => {
                                if (!reason || !reason.trim()) {
                                    Swal.showValidationMessage('กรุณาระบุเหตุผลการไม่อนุมัติ');
                                }
                                return reason;
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('actionType').value = 'rejected';
                                document.getElementById('commentText').value = result.value;
                                document.getElementById('approvalForm').submit();
                            }
                        });
                    } else {
                        var reason = prompt('กรุณาระบุเหตุผลในการไม่อนุมัติ:');
                        if (reason && reason.trim()) {
                            document.getElementById('actionType').value = 'rejected';
                            document.getElementById('commentText').value = reason.trim();
                            document.getElementById('approvalForm').submit();
                        }
                    }
                });
            }
        });
    </script>
<?php endif; ?>

<?php if ($canUserDoIT): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var itCanvas = document.getElementById('itStaffCanvas');
            if (!itCanvas) return;

            var itSigPad = new SignaturePad(itCanvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(15, 23, 42)',
                minWidth: 1.5,
                maxWidth: 3.5
            });

            function resizeItCanvas() {
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                itCanvas.width = itCanvas.offsetWidth * ratio;
                itCanvas.height = itCanvas.offsetHeight * ratio;
                itCanvas.getContext("2d").scale(ratio, ratio);
                itSigPad.clear();
            }
            window.addEventListener("resize", resizeItCanvas);
            resizeItCanvas();

            var clearBtn = document.getElementById('clearItStaffSigBtn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    itSigPad.clear();
                });
            }

            var mockBtn = document.getElementById('mockItStaffSignBtn');
            if (mockBtn) {
                mockBtn.addEventListener('click', function() {
                    var ctx = itCanvas.getContext("2d");
                    itSigPad.clear();
                    ctx.beginPath();
                    ctx.lineWidth = 2.5;
                    ctx.strokeStyle = "#0f172a";
                    ctx.moveTo(40, 60);
                    ctx.bezierCurveTo(70, 20, 110, 120, 160, 50);
                    ctx.bezierCurveTo(180, 40, 210, 100, 260, 60);
                    ctx.stroke();
                    ctx.font = "italic 14px sans-serif";
                    ctx.fillStyle = "#0f172a";
                    ctx.fillText("<?= addslashes($currentUser['full_name']) ?> (IT)", 45, 95);
                });
            }

            // Handle IT Staff Submit
            var submitBtn = document.getElementById('btnItSubmit');
            if (submitBtn) {
                submitBtn.addEventListener('click', function() {
                    var effDate = document.getElementById('itEffectiveDate');
                    if (!effDate || !effDate.value) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'กรุณาระบุวันเริ่มงาน',
                                text: 'โปรดเลือก Effective Date ก่อนบันทึกข้อมูล',
                                confirmButtonColor: '#0284c7'
                            });
                        } else {
                            alert('กรุณาเลือก Effective Date ก่อนบันทึกข้อมูล');
                        }
                        return;
                    }

                    if (itSigPad.isEmpty()) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'กรุณาลงลายมือชื่อ',
                                text: 'โปรดเซ็นลายมือชื่อเจ้าหน้าที่ไอที (IT Staff Signature) ในกล่องก่อนกดยืนยัน',
                                confirmButtonColor: '#0284c7'
                            });
                        } else {
                            alert('โปรดเซ็นลายมือชื่อเจ้าหน้าที่ไอทีก่อนกดยืนยัน');
                        }
                        return;
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'ยืนยันการบันทึกวันเริ่มงานและลงลายมือชื่อ?',
                            text: 'ระบบจะบันทึกข้อมูล Effective Date, รายละเอียดบัญชี และลายมือชื่อของท่าน จากนั้นส่งต่อให้ IT Manager ลงนามอนุมัติขั้นสุดท้าย',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#0284c7',
                            cancelButtonColor: '#64748b',
                            confirmButtonText: 'ยืนยันและส่งต่อ',
                            cancelButtonText: 'ยกเลิก'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('itActionType').value = 'approved';
                                document.getElementById('itStaffSignatureData').value = itSigPad.toDataURL('image/png');
                                document.getElementById('itActionForm').submit();
                            }
                        });
                    } else {
                        if (confirm('ยืนยันการบันทึกวันเริ่มงานและลงลายมือชื่อ?')) {
                            document.getElementById('itActionType').value = 'approved';
                            document.getElementById('itStaffSignatureData').value = itSigPad.toDataURL('image/png');
                            document.getElementById('itActionForm').submit();
                        }
                    }
                });
            }

            // Handle IT Staff Reject
            var rejectBtn = document.getElementById('btnItReject');
            if (rejectBtn) {
                rejectBtn.addEventListener('click', function() {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'ระบุเหตุผลในการปฏิเสธคำขอ',
                            input: 'textarea',
                            inputPlaceholder: 'กรอกเหตุผลที่ฝ่ายไอทีไม่สามารถดำเนินการได้...',
                            showCancelButton: true,
                            confirmButtonColor: '#e11d48',
                            confirmButtonText: 'ยืนยันปฏิเสธคำขอ',
                            cancelButtonText: 'ยกเลิก',
                            preConfirm: (reason) => {
                                if (!reason || !reason.trim()) {
                                    Swal.showValidationMessage('กรุณาระบุเหตุผลการปฏิเสธ');
                                }
                                return reason;
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('itActionType').value = 'rejected';
                                document.getElementById('itNotes').value = result.value;
                                document.getElementById('itActionForm').submit();
                            }
                        });
                    } else {
                        var reason = prompt('กรุณาระบุเหตุผลการปฏิเสธ:');
                        if (reason && reason.trim()) {
                            document.getElementById('itActionType').value = 'rejected';
                            document.getElementById('itNotes').value = reason.trim();
                            document.getElementById('itActionForm').submit();
                        }
                    }
                });
            }
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
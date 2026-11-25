<?php

/**
 * Official Form View & PDF Export Engine
 * Replicates Form FM-KTNC-IT-003 Rev.00 with all Digital Signatures Embedded
 * Guaranteed Strictly 1 Single A4 Page (No Multi-Page Overflow)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

// Security: Require authentication
requireLogin();

$currentUser = getCurrentUser();
$requestId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    die("ไม่พบข้อมูลคำขอนี้ (Request not found)");
}

// Security: Authorization check (Requesters can only export their own requests)
$isGenRequester = ($currentUser['level_id'] <= 3 && stripos($currentUser['position'] ?? '', 'IT') === false && stripos($currentUser['position'] ?? '', 'Personnel') === false);
$isOwner = ($request['requester_id'] == $currentUser['id']) || (!empty($currentUser['employee_id']) && $request['employee_id'] === $currentUser['employee_id']);
if ($isGenRequester && !$isOwner) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์ในการเข้าถึงเอกสารนี้ (Access Denied)");
}

$systemsData = json_decode($request['systems_data'], true) ?: [];

// Fetch approvals
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

// Check if all 3 signatures are present
$isAllApproved = ($deptHeadApproval && $hotelMgrApproval && $itMgrApproval);

// Katathani Collection Logo (Embedded as Base64 for guaranteed print rendering)
$logoPath = __DIR__ . '/photo/images-Photoroom.png';
$logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : url('photo/images-Photoroom.png');

function chk($val)
{
    return !empty($val) ? '&#9746;' : '&#9744;'; // Checked or Unchecked box
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYSTEM_ACCESS_<?= htmlspecialchars($request['request_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 4mm 5mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background-color: #525659;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            padding: 15px 0;
        }

        .page {
            background: white;
            width: 200mm;
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.3);
            position: relative;
            page-break-inside: avoid;
            page-break-after: avoid;
        }

        table.form-table {
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse;
            font-size: 7.7pt;
            line-height: 1.18;
        }

        table.form-table td,
        table.form-table th {
            border: 1px solid #333;
            padding: 2px 3.5px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .section-header {
            background-color: #b0c4de !important;
            font-weight: bold;
            text-align: center;
            font-size: 8.2pt;
            padding: 2px 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sub-header {
            background-color: #dcdcdc !important;
            font-weight: bold;
            font-size: 7.2pt;
            padding: 1.5px 3px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .checkbox-symbol {
            font-size: 9.5pt;
            font-family: "DejaVu Sans", "Arial Unicode MS", sans-serif;
            display: inline-block;
            width: 12px;
            margin-right: 2px;
            line-height: 1;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 4mm 5mm;
            }

            html,
            body {
                width: 100% !important;
                height: 100% !important;
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: hidden !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .page {
                box-shadow: none !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 100% !important;
                height: auto !important;
                margin: 0 auto !important;
                padding: 0 !important;
                overflow: visible !important;
                page-break-inside: avoid !important;
                page-break-after: avoid !important;
            }

            .no-print {
                display: none !important;
            }

            table.form-table {
                width: 100% !important;
                table-layout: fixed !important;
            }
        }
    </style>
</head>

<body>

    <!-- On-screen Action Floating Toolbar (Hidden during Print) -->
    <div class="no-print max-w-4xl mx-auto mb-3 bg-slate-900 text-white p-3 rounded-xl shadow-lg flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-lg bg-rose-600 flex items-center justify-center text-white">
                <i class="fa-solid fa-file-pdf"></i>
            </div>
            <div>
                <div class="font-bold text-sm">แบบฟอร์มเอกสารทางการ (Official PDF Preview - 1 Page Form)</div>
                <div class="text-xs text-slate-400">เลขที่: <?= htmlspecialchars($request['request_no']) ?> &bull; Katathani Collection</div>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <a href="view_request.php?id=<?= $request['id'] ?>" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold transition border border-slate-700">
                &larr; กลับหน้ารายละเอียด
            </a>

            <button onclick="window.print()" class="px-5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-bold transition shadow flex items-center space-x-1.5">
                <i class="fa-solid fa-print"></i>
                <span>พิมพ์ / บันทึกเป็น PDF (Print to PDF)</span>
            </button>
        </div>
    </div>

    <!-- The Exact Physical Form Layout (A4 Strictly 1 Page) -->
    <div class="page">

        <!-- Header Banner with Katathani Collection Logo inside INFORMATION TECHNOLOGY box -->
        <table class="form-table" style="margin-bottom: 0;">
            <tr>
                <td colspan="4" class="section-header" style="background-color: #8da3c0 !important; color: #000; font-size: 9.5pt; letter-spacing: 0.5px; padding: 3px 6px; position: relative;">
                    <div style="display: flex; align-items: center; justify-content: center; position: relative; min-height: 22px;">
                        <span style="font-weight: bold;">INFORMATION TECHNOLOGY</span>
                        <div style="position: absolute; right: 0; top: 50%; transform: translateY(-50%); display: flex; align-items: center;">
                            <img src="<?= $logoBase64 ?>" alt="Katathani Collection" style="height: 20px; width: auto; object-fit: contain;">
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: center; font-weight: bold; font-size: 8.8pt; padding: 3px 0; background-color: #e6ecf5; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                    SYSTEM ACCESS REQUEST FORM
                </td>
            </tr>
            <!-- General Info Grid -->
            <tr>
                <td style="width: 14%; font-weight: bold; background-color: #f5f5f5;">First Name</td>
                <td style="width: 36%;"><?= htmlspecialchars((!empty($request['title']) ? $request['title'] . ' ' : '') . $request['first_name']) ?></td>
                <td style="width: 14%; font-weight: bold; background-color: #f5f5f5;">Property</td>
                <td style="width: 36%;">
                    <span class="checkbox-symbol"><?= chk($request['property'] === 'Katathani') ?></span> Katathani &nbsp;
                    <span class="checkbox-symbol"><?= chk($request['property'] === 'The Shore') ?></span> The Shore &nbsp;
                    <span class="checkbox-symbol"><?= chk($request['property'] === 'The Sea Gallery' || $request['property'] === 'The Sea Galleri') ?></span> The Sea Gallery
                    <?php if (!in_array($request['property'], ['Katathani', 'The Shore', 'The Sea Gallery', 'The Sea Galleri'])): ?>
                        &nbsp; <span class="checkbox-symbol">&#9746;</span> <?= htmlspecialchars($request['property']) ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold; background-color: #f5f5f5;">Last Name</td>
                <td><?= htmlspecialchars($request['last_name']) ?></td>
                <td style="font-weight: bold; background-color: #f5f5f5;">Employee ID</td>
                <td style="font-family: monospace; font-weight: bold;"><?= htmlspecialchars($request['employee_id']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold; background-color: #f5f5f5;">Department</td>
                <td><?= htmlspecialchars($request['department']) ?></td>
                <td style="font-weight: bold; background-color: #f5f5f5;">Manager's Name</td>
                <td><?= htmlspecialchars($request['manager_name']) ?></td>
            </tr>
            <tr>
                <td style="font-weight: bold; background-color: #f5f5f5;">Position</td>
                <td><?= htmlspecialchars($request['position'] ?? '') ?></td>
            </tr>
        </table>

        <!-- Two Column System Access Matrix -->
        <table class="form-table" style="margin-top: -1px;">
            <tr>
                <!-- LEFT COLUMN -->
                <td style="width: 50%; vertical-align: top; padding: 0;">

                    <!-- Hotel Network -->
                    <table class="form-table" style="border: none;">
                        <tr>
                            <td colspan="2" class="section-header">Hotel Network</td>
                        </tr>
                        <tr>
                            <td style="width: 50%;">
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['hotel_network']['win_login'])) ?></span> Windows User Login
                            </td>
                            <td>
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['hotel_network']['usb_allow'])) ?></span> USB Allow
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['hotel_network']['file_server_check'])) ?></span> File Server (Drive: <u><?= htmlspecialchars($systemsData['hotel_network']['file_server_drive'] ?? '__________') ?></u>)
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['hotel_network']['net_allow_all'])) ?></span> Internet allow all website
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['hotel_network']['net_block_some'])) ?></span> Internet block some website
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['hotel_network']['email_check'])) ?></span> E-Mail : <u><?= htmlspecialchars($systemsData['hotel_network']['email_address'] ?? '____________________') ?></u>
                            </td>
                        </tr>
                    </table>

                    <!-- Katathani Application & Tool -->
                    <table class="form-table" style="border: none; margin-top: -1px;">
                        <tr>
                            <td class="sub-header">Katathani Application & Tool</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['katathani_app']['agent_systems'])) ?></span> Agent Systems</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['katathani_app']['member_systems'])) ?></span> Member Systems</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['katathani_app']['customer_systems'])) ?></span> Customer Systems</td>
                        </tr>
                    </table>

                    <!-- Opera PMS -->
                    <table class="form-table" style="border: none; margin-top: -1px;">
                        <tr>
                            <td class="sub-header">Opera - Property Management System</td>
                        </tr>
                        <tr style="background-color: #f9f9f9; font-size: 6.8pt;">
                            <td>
                                Property:
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['opera_pms']['property']['katathani'])) ?></span> Katathani &nbsp;
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['opera_pms']['property']['the_sands'])) ?></span> The Sands &nbsp;
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['opera_pms']['property']['riverie'])) ?></span> Riverie
                            </td>
                        </tr>
                        <?php
                        $opRoles = [
                            'engineer' => 'Engineer',
                            'sales_catering' => 'Sales & Catering',
                            'enquiry_executive' => 'Enquiry Only (Executive)',
                            'housekeeping' => 'Housekeeping',
                            'guest_service_agents' => 'Guest Service Agents',
                            'assistant_managers' => 'Assistant Managers',
                            'front_office_manager' => 'Front Office Manager',
                            'reservation_manager' => 'Reservation Manager',
                            'night_audit_manager' => 'Night Audit / Night Manager',
                            'accounting' => 'Accounting',
                            'operator' => 'Operator',
                            'vision_opera' => 'Vision Opera'
                        ];
                        foreach ($opRoles as $k => $lbl):
                        ?>
                            <tr>
                                <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['opera_pms']['roles'][$k])) ?></span> <?= $lbl ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <!-- Check SCM -->
                    <table class="form-table" style="border: none; margin-top: -1px;">
                        <tr>
                            <td class="sub-header">Check SCM - Inventory/Purchasing</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['check_scm']['enquiry_only'])) ?></span> Enquiry Only</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['check_scm']['purchasing'])) ?></span> Purchasing</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['check_scm']['receiving'])) ?></span> Receiving</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['check_scm']['stores'])) ?></span> Stores</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['check_scm']['cost_supervisor'])) ?></span> Cost Supervisor</td>
                        </tr>
                    </table>

                    <!-- EMO & VPN -->
                    <table class="form-table" style="border: none; margin-top: -1px;">
                        <tr>
                            <td class="sub-header">EMO - Engineer Maintenance Order</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['emo']['order_request'])) ?></span> Order Request &nbsp;&nbsp;&nbsp; <span class="checkbox-symbol"><?= chk(!empty($systemsData['emo']['order_manage'])) ?></span> Order Manage</td>
                        </tr>
                        <tr>
                            <td class="sub-header">VPN - FortiClient Remote Access</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['vpn']['outside_remote'])) ?></span> Request Outside Remote Access</td>
                        </tr>
                    </table>

                </td>

                <!-- RIGHT COLUMN -->
                <td style="width: 50%; vertical-align: top; padding: 0;">

                    <!-- ADELL -->
                    <table class="form-table" style="border: none;">
                        <tr>
                            <td class="sub-header">ADELL - Key Card System</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['adell']['key_card_mgmt'])) ?></span> Key Card User Management Login</td>
                        </tr>
                    </table>

                    <!-- Micros POS -->
                    <table class="form-table" style="border: none; margin-top: -1px;">
                        <tr>
                            <td class="sub-header">Micros - Point of Sales System</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['micros']['waiters_order_taker'])) ?></span> Waiters/Waitress/Order Taker</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['micros']['cashier_captain'])) ?></span> Cashier/Captain</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['micros']['night_audit'])) ?></span> Night Audit</td>
                        </tr>
                    </table>

                    <!-- Eagle HRMS -->
                    <table class="form-table" style="border: none; margin-top: -1px;">
                        <tr>
                            <td class="sub-header">Eagle - HRMS & Payroll System</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['eagle']['time_attendance'])) ?></span> Time Attendance</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['eagle']['personnel_office'])) ?></span> Personnel Office</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['eagle']['personnel_manager'])) ?></span> Personnel Manager</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['eagle']['paymaster'])) ?></span> Paymaster</td>
                        </tr>
                    </table>

                    <!-- Sun Financial -->
                    <table class="form-table" style="border: none; margin-top: -1px;">
                        <tr>
                            <td class="sub-header">Sun - Financial System</td>
                        </tr>
                        <?php
                        $sunList = [
                            'account_payable' => 'Account Payable',
                            'account_receivable' => 'Account Receivable',
                            'asset_supervisor' => 'Asset Supervisor',
                            'cost_controller' => 'Cost Controller',
                            'general_cashier' => 'General Cashier',
                            'income_auditor' => 'Income Auditor',
                            'chief_accountant' => 'Chief Accountant / Assistant Chief Accountant',
                            'financial_controller' => 'Chief Financial Controller / Financial Controller',
                            'vision_sun' => 'Vision Sun',
                            'sun_remote' => 'Sun Remote'
                        ];
                        foreach ($sunList as $k => $lbl):
                        ?>
                            <tr>
                                <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['sun'][$k])) ?></span> <?= $lbl ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <!-- Antlap / Fixed Asset / BS Minimart / FCS / CCTV -->
                    <table class="form-table" style="border: none; margin-top: -1px;">
                        <tr>
                            <td class="sub-header">Antlap - Internet Gateway</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['antlap']['view_user'])) ?></span> View User</td>
                        </tr>

                        <tr>
                            <td class="sub-header">Well Fixed asset - Asset System</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['well_fixed_asset']['asset_supervisor'])) ?></span> Asset Supervisor</td>
                        </tr>

                        <tr>
                            <td class="sub-header">BS Minimart - POS minimart</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['bs_minimart']['cashier'])) ?></span> Cashier &nbsp;&nbsp;&nbsp; <span class="checkbox-symbol"><?= chk(!empty($systemsData['bs_minimart']['back_office'])) ?></span> Back office</td>
                        </tr>

                        <tr>
                            <td class="sub-header">FCS - Telephone Billing System</td>
                        </tr>
                        <tr>
                            <td><span class="checkbox-symbol"><?= chk(!empty($systemsData['fcs']['user_view'])) ?></span> User view</td>
                        </tr>

                        <tr>
                            <td class="sub-header">CCTV</td>
                        </tr>
                        <tr>
                            <td>
                                <span class="checkbox-symbol"><?= chk(!empty($systemsData['cctv']['user_monitoring'])) ?></span> User Monitoring:
                                <u><?= htmlspecialchars($systemsData['cctv']['user_monitoring'] ?? '__________') ?></u>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
        </table>

        <!-- Policy Consent (Employee Acceptance) -->
        <table class="form-table" style="margin-top: -1px;">
            <tr>
                <td style="width: 58%; font-weight: bold; font-size: 7.6pt; padding: 3px 5px;">
                    <span class="checkbox-symbol">&#9746;</span> I have read and understand all relevant IT policies
                </td>
                <td style="width: 42%; text-align: center; vertical-align: bottom; height: 32px; position: relative; padding: 2px 4px;">
                    <div style="font-size: 7pt; color: #166534; font-weight: bold; margin-bottom: 2px;">
                        &#10003; Acknowledged (ยอมรับเงื่อนไขนโยบายไอทีแล้ว)
                    </div>
                    <div style="font-size: 7.2pt; border-top: 1px dotted #888; padding-top: 2px;">
                        ( <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?> ) &bull; <strong>Employee Acceptance</strong>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Approval Signatures (3 Approvers) -->
        <table class="form-table" style="margin-top: -1px;">
            <tr>
                <td colspan="3" class="section-header" style="text-align: left; padding-left: 6px; font-size: 7.8pt; padding: 2px 5px;">
                    Approval Signature
                </td>
            </tr>
            <tr style="height: 56px; vertical-align: bottom; text-align: center;">
                <!-- 1. Department Head -->
                <td style="width: 33.33%; padding: 2px 3px;">
                    <?php if ($deptHeadApproval && !empty($deptHeadApproval['signature'])): ?>
                        <div style="height: 36px; display: flex; align-items: center; justify-content: center;">
                            <img src="<?= $deptHeadApproval['signature'] ?>" style="max-height: 34px; max-width: 140px; object-fit: contain;" alt="Dept Head">
                        </div>
                    <?php else: ?>
                        <div style="height: 36px;"></div>
                    <?php endif; ?>
                    <div style="font-size: 7.2pt; border-top: 1px solid #444; padding-top: 2px;">
                        <strong>Department Head</strong><br>
                        <span style="font-size: 6.8pt; color: #444;">
                            <?= $deptHeadApproval ? htmlspecialchars($deptHeadApproval['approver_name']) . ' (' . date('d/m/Y', strtotime($deptHeadApproval['signed_at'])) . ')' : '&nbsp;' ?>
                        </span>
                    </div>
                </td>

                <!-- 2. Hotel Manager -->
                <td style="width: 33.33%; padding: 2px 3px;">
                    <?php if ($hotelMgrApproval && !empty($hotelMgrApproval['signature'])): ?>
                        <div style="height: 36px; display: flex; align-items: center; justify-content: center;">
                            <img src="<?= $hotelMgrApproval['signature'] ?>" style="max-height: 34px; max-width: 140px; object-fit: contain;" alt="Hotel Manager">
                        </div>
                    <?php else: ?>
                        <div style="height: 36px;"></div>
                    <?php endif; ?>
                    <div style="font-size: 7.2pt; border-top: 1px solid #444; padding-top: 2px;">
                        <strong>Hotel Manager</strong><br>
                        <span style="font-size: 6.8pt; color: #444;">
                            <?= $hotelMgrApproval ? htmlspecialchars($hotelMgrApproval['approver_name']) . ' (' . date('d/m/Y', strtotime($hotelMgrApproval['signed_at'])) . ')' : '&nbsp;' ?>
                        </span>
                    </div>
                </td>

                <!-- 3. IT Manager -->
                <td style="width: 33.33%; padding: 2px 3px;">
                    <?php if ($itMgrApproval && !empty($itMgrApproval['signature'])): ?>
                        <div style="height: 36px; display: flex; align-items: center; justify-content: center;">
                            <img src="<?= $itMgrApproval['signature'] ?>" style="max-height: 34px; max-width: 140px; object-fit: contain;" alt="IT Manager">
                        </div>
                    <?php else: ?>
                        <div style="height: 36px;"></div>
                    <?php endif; ?>
                    <div style="font-size: 7.2pt; border-top: 1px solid #444; padding-top: 2px;">
                        <strong>IT Manager</strong><br>
                        <span style="font-size: 6.8pt; color: #444;">
                            <?= $itMgrApproval ? htmlspecialchars($itMgrApproval['approver_name']) . ' (' . date('d/m/Y', strtotime($itMgrApproval['signed_at'])) . ')' : '&nbsp;' ?>
                        </span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Routing Notice -->
        <div style="font-size: 6.5pt; font-weight: bold; margin: 2px 0; text-transform: uppercase; text-align: center; color: #444;">
            ROUTE TO HUMAN RESOURCES AFTER ACCESS ESTABLISHED &bull; ORIGINAL TO BE RETAINED IN ASSOCIATE'S PHYSICAL PERSONNEL FILE
        </div>

        <!-- IT Department Use & Personnel Department Use -->
        <table class="form-table">
            <tr style="background-color: #f0f0f0;">
                <th style="width: 50%; text-align: left; font-size: 7.2pt; padding: 2px 4px;">IT DEPARTMENT USE:</th>
                <th style="width: 50%; text-align: left; font-size: 7.2pt; padding: 2px 4px;">PERSONNEL DEPARTMENT USE:</th>
            </tr>
            <tr>
                <td style="vertical-align: top; padding: 3px 5px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
                        <div style="font-size: 7.2pt; line-height: 1.35; flex: 1;">
                            <div>Effective Date: <strong><?= $itAction && $itAction['effective_date'] ? date('d / m / Y', strtotime($itAction['effective_date'])) : '&nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp;' ?></strong></div>
                            <div>Account Expiry Date: <strong><?= $itAction && $itAction['expiry_date'] ? date('d / m / Y', strtotime($itAction['expiry_date'])) : '&nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp;' ?></strong></div>
                            <?php if ($itAction && !empty($itAction['notes'])): ?>
                                <div style="font-size: 6.5pt; color: #555; margin-top: 1px;">Ref: <?= htmlspecialchars($itAction['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="width: 130px; text-align: center; flex-shrink: 0;">
                            <?php if ($itAction && !empty($itAction['signature'])): ?>
                                <div style="height: 36px; display: flex; align-items: center; justify-content: center;">
                                    <img src="<?= $itAction['signature'] ?>" style="max-height: 34px; max-width: 125px; object-fit: contain;" alt="IT Staff Signature">
                                </div>
                            <?php else: ?>
                                <div style="height: 36px;"></div>
                            <?php endif; ?>

                        </div>
                    </div>
                </td>
                <td style="vertical-align: top; padding: 3px 5px;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
                        <div style="font-size: 7.2pt; line-height: 1.35; flex: 1;">
                            <div>Filing Date: <strong><?= $hrAction && $hrAction['filing_date'] ? date('d / m / Y', strtotime($hrAction['filing_date'])) : '&nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp;' ?></strong></div>
                            <?php if ($hrAction && !empty($hrAction['notes'])): ?>
                                <div style="font-size: 6.5pt; color: #555; margin-top: 1px;">Ref: <?= htmlspecialchars($hrAction['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="width: 130px; text-align: center; flex-shrink: 0;">
                            <?php if ($hrAction && !empty($hrAction['signature'])): ?>
                                <div style="height: 36px; display: flex; align-items: center; justify-content: center;">
                                    <img src="<?= $hrAction['signature'] ?>" style="max-height: 34px; max-width: 125px; object-fit: contain;" alt="HR Signature">
                                </div>
                            <?php else: ?>
                                <div style="height: 36px;"></div>
                            <?php endif; ?>

                        </div>
                    </div>
                </td>
            </tr>
            <tr style="background-color: #f0f0f0;">
                <th style="text-align: left; font-size: 7.2pt; padding: 2px 4px;">IT DEPARTMENT USE:</th>
                <th style="text-align: left; font-size: 7.2pt; padding: 2px 4px;">PERSONNEL DEPARTMENT USE:</th>
            </tr>
            <tr>
                <td style="height: 22px; vertical-align: top; padding: 2px 4px;">
                    Deleted Date:
                    <strong><?= $itAction && $itAction['deleted_date'] ? date('d / m / Y', strtotime($itAction['deleted_date'])) : '&nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp;' ?></strong>
                </td>
                <td style="vertical-align: top; padding: 2px 4px;">
                    Resigned Date:
                    <strong><?= $hrAction && $hrAction['resigned_date'] ? date('d / m / Y', strtotime($hrAction['resigned_date'])) : '&nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;&nbsp;' ?></strong>
                </td>
            </tr>
        </table>

        <!-- Document Footer Metadata -->
        <table style="width: 100%; font-size: 6.8pt; color: #444; margin-top: 3px;">
            <tr>
                <td style="width: 35%; text-align: left; font-weight: bold;">
                    <?= APP_DOC_CODE ?>
                </td>
                <td style="width: 30%; text-align: center;">
                    Revised: <?= APP_DOC_REVISED ?>
                </td>
                <td style="width: 35%; text-align: right;">
                    Effective Date: <?= APP_DOC_EFFECTIVE_DATE ?>
                </td>
            </tr>
        </table>

    </div>

</body>

</html>
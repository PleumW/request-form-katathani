<?php

/**
 * Settings Page - Approver Emails & Form Field Editor
 * Access: Admin only (level_id = 99)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$currentUser = getCurrentUser();
$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = trim($_POST['action'] ?? '');

    // 1. Add New Section / System Category
    if ($action === 'add_section') {
        $col = ($_POST['col'] ?? '') === 'right_column' ? 'right_column' : 'left_column';
        $secTitle = trim($_POST['section_title'] ?? '');

        if (empty($secTitle)) {
            setFlash('error', 'กรุณาระบุชื่อหมวดหมู่ให้ครบถ้วน');
            redirect('settings.php');
        }

        // Auto-generate section_id if not provided
        $secId = !empty($_POST['section_id'])
            ? preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', trim($_POST['section_id']))))
            : 'sec_' . substr(md5($secTitle . microtime()), 0, 8);

        $matrix = getActiveSystemMatrixDefinition();
        if (isset($matrix['left_column'][$secId]) || isset($matrix['right_column'][$secId])) {
            $secId .= '_' . rand(10, 99);
        }

        $matrix[$col][$secId] = [
            'title' => $secTitle,
            'icon' => '',
            'type' => 'checkboxes',
            'items' => []
        ];

        saveSystemMatrixDefinition($matrix);
        setFlash('success', "เพิ่มหมวดหมู่ '{$secTitle}' เรียบร้อยแล้ว");
        redirect('settings.php');
    }

    // 2. Edit Section / System Category
    if ($action === 'edit_section') {
        $curCol = ($_POST['col'] ?? '') === 'right_column' ? 'right_column' : 'left_column';
        $newCol = ($_POST['new_col'] ?? '') === 'right_column' ? 'right_column' : 'left_column';
        $secId = trim($_POST['section_id'] ?? '');
        $secTitle = trim($_POST['section_title'] ?? '');

        $matrix = getActiveSystemMatrixDefinition();
        if (!isset($matrix[$curCol][$secId])) {
            setFlash('error', 'ไม่พบหมวดหมู่ที่ต้องการแก้ไข');
            redirect('settings.php');
        }

        $secData = $matrix[$curCol][$secId];
        $secData['title'] = $secTitle;
        $secData['icon'] = '';

        if ($curCol !== $newCol) {
            unset($matrix[$curCol][$secId]);
            $matrix[$newCol][$secId] = $secData;
        } else {
            $matrix[$curCol][$secId] = $secData;
        }

        saveSystemMatrixDefinition($matrix);
        setFlash('success', "แก้ไขข้อมูลหมวดหมู่ '{$secTitle}' เรียบร้อยแล้ว");
        redirect('settings.php');
    }

    // 4. Delete Section / System Category
    if ($action === 'delete_section') {
        $col = ($_POST['col'] ?? '') === 'right_column' ? 'right_column' : 'left_column';
        $secId = trim($_POST['section_id'] ?? '');

        $matrix = getActiveSystemMatrixDefinition();
        if (isset($matrix[$col][$secId])) {
            $title = $matrix[$col][$secId]['title'] ?? $secId;
            unset($matrix[$col][$secId]);
            saveSystemMatrixDefinition($matrix);
            setFlash('success', "ลบหมวดหมู่ '{$title}' ออกจากแบบฟอร์มเรียบร้อยแล้ว");
        }
        redirect('settings.php');
    }

    // 5. Add Item / Field inside a Section
    if ($action === 'add_item') {
        $col = ($_POST['col'] ?? '') === 'right_column' ? 'right_column' : 'left_column';
        $secId = trim($_POST['section_id'] ?? '');
        $itemLabel = trim($_POST['item_label'] ?? '');
        $itemType = in_array($_POST['item_type'] ?? '', ['text_input', 'text']) ? 'text_input' : 'checkbox';
        $placeholder = trim($_POST['placeholder'] ?? '');

        if (empty($itemLabel)) {
            setFlash('error', 'กรุณาระบุชื่อสิทธิ์ที่ต้องการเปิดให้ครบถ้วน');
            redirect('settings.php');
        }

        // Auto-generate item_id if not provided
        $itemId = !empty($_POST['item_id'])
            ? preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', trim($_POST['item_id']))))
            : 'item_' . substr(md5($itemLabel . microtime()), 0, 8);

        $matrix = getActiveSystemMatrixDefinition();
        if (!isset($matrix[$col][$secId])) {
            setFlash('error', 'ไม่พบหมวดหมู่ที่เลือก');
            redirect('settings.php');
        }

        // Check if item id already exists in this section, if so append random suffix
        foreach ($matrix[$col][$secId]['items'] as $it) {
            if ($it['id'] === $itemId) {
                $itemId .= '_' . rand(10, 99);
                break;
            }
        }

        $newItem = [
            'id' => $itemId,
            'label' => $itemLabel,
            'type' => $itemType
        ];
        if ($itemType === 'text_input' && !empty($placeholder)) {
            $newItem['placeholder'] = $placeholder;
        }

        $matrix[$col][$secId]['items'][] = $newItem;
        saveSystemMatrixDefinition($matrix);
        setFlash('success', "เพิ่มรายการสิทธิ์ '{$itemLabel}' ในหมวดหมู่เรียบร้อยแล้ว");
        redirect('settings.php');
    }

    // 6. Edit Item / Field
    if ($action === 'edit_item') {
        $col = ($_POST['col'] ?? '') === 'right_column' ? 'right_column' : 'left_column';
        $secId = trim($_POST['section_id'] ?? '');
        $itemId = trim($_POST['item_id'] ?? '');
        $itemLabel = trim($_POST['item_label'] ?? '');
        $itemType = in_array($_POST['item_type'] ?? '', ['text_input', 'text']) ? 'text_input' : 'checkbox';
        $placeholder = trim($_POST['placeholder'] ?? '');

        $matrix = getActiveSystemMatrixDefinition();
        if (!isset($matrix[$col][$secId])) {
            setFlash('error', 'ไม่พบหมวดหมู่ที่เลือก');
            redirect('settings.php');
        }

        $found = false;
        foreach ($matrix[$col][$secId]['items'] as &$it) {
            if ($it['id'] === $itemId) {
                $it['label'] = $itemLabel;
                $it['type'] = $itemType;
                if ($itemType === 'text_input') {
                    $it['placeholder'] = $placeholder;
                } else {
                    unset($it['placeholder']);
                }
                $found = true;
                break;
            }
        }

        if ($found) {
            saveSystemMatrixDefinition($matrix);
            setFlash('success', "แก้ไขรายการสิทธิ์ '{$itemLabel}' เรียบร้อยแล้ว");
        } else {
            setFlash('error', 'ไม่พบรายการที่ต้องการแก้ไข');
        }
        redirect('settings.php');
    }

    // 7. Delete Item / Field
    if ($action === 'delete_item') {
        $col = ($_POST['col'] ?? '') === 'right_column' ? 'right_column' : 'left_column';
        $secId = trim($_POST['section_id'] ?? '');
        $itemId = trim($_POST['item_id'] ?? '');

        $matrix = getActiveSystemMatrixDefinition();
        if (isset($matrix[$col][$secId]['items'])) {
            $newItems = [];
            foreach ($matrix[$col][$secId]['items'] as $it) {
                if ($it['id'] !== $itemId) {
                    $newItems[] = $it;
                }
            }
            $matrix[$col][$secId]['items'] = $newItems;
            saveSystemMatrixDefinition($matrix);
            setFlash('success', "ลบรายการสิทธิ์ออกจากแบบฟอร์มเรียบร้อยแล้ว");
        }
        redirect('settings.php');
    }

    // 8. Reset Form Definition to System Default
    if ($action === 'reset_form') {
        resetSystemMatrixDefinition();
        setFlash('success', 'คืนค่าแบบฟอร์มเริ่มต้นตามระเบียบเอกสาร FM-KTNC-IT-003 Rev.00 เรียบร้อยแล้ว');
        redirect('settings.php?tab=form');
    }

    // 9. Add New Hotel
    if ($action === 'add_hotel') {
        $hotelName = trim($_POST['hotel_name'] ?? '');
        $propertyId = intval($_POST['hotel_property_id'] ?? 1);
        $hotelShort = strtoupper(trim($_POST['hotel_name_s'] ?? ''));
        $nameOpera = trim($_POST['name_opera'] ?? '');

        if (empty($hotelName)) {
            setFlash('error', 'กรุณาระบุชื่อโรงแรมให้ครบถ้วน');
            redirect('settings.php?tab=hotels');
        }

        // Auto-generate short code if not provided
        if (empty($hotelShort)) {
            $words = preg_split('/[\s_-]+/', $hotelName);
            $short = '';
            foreach ($words as $w) {
                $short .= mb_substr($w, 0, 1);
            }
            $hotelShort = strtoupper(substr($short ?: 'HOT', 0, 5));
        } else {
            $hotelShort = substr($hotelShort, 0, 5);
        }

        $hotelNameSs = strtolower($hotelShort);
        $nameForac = $hotelShort;
        if (empty($nameOpera)) {
            $nameOpera = $hotelShort;
        }

        // Check if hotel name already exists
        $checkStmt = $db->prepare("SELECT hotel_id FROM ex_hotel WHERE hotel_name = ?");
        $checkStmt->execute([$hotelName]);
        if ($checkStmt->fetch()) {
            setFlash('error', "ชื่อโรงแรม '{$hotelName}' มีอยู่ในระบบแล้ว");
            redirect('settings.php?tab=hotels');
        }

        $stmt = $db->prepare("INSERT INTO ex_hotel (hotel_name, hotel_property_id, hotel_name_s, hotel_name_ss, name_forac, name_opera) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$hotelName, $propertyId, $hotelShort, $hotelNameSs, $nameForac, $nameOpera]);

        setFlash('success', "เพิ่มโรงแรม '{$hotelName}' เรียบร้อยแล้ว");
        redirect('settings.php?tab=hotels');
    }

    // 10. Edit Hotel
    if ($action === 'edit_hotel') {
        $hotelId = intval($_POST['hotel_id'] ?? 0);
        $hotelName = trim($_POST['hotel_name'] ?? '');
        $propertyId = intval($_POST['hotel_property_id'] ?? 1);
        $hotelShort = strtoupper(trim($_POST['hotel_name_s'] ?? ''));
        $nameOpera = trim($_POST['name_opera'] ?? '');

        if ($hotelId <= 0 || empty($hotelName)) {
            setFlash('error', 'กรุณาระบุข้อมูลโรงแรมให้ครบถ้วน');
            redirect('settings.php?tab=hotels');
        }

        if (empty($hotelShort)) {
            $hotelShort = strtoupper(substr($hotelName, 0, 5));
        } else {
            $hotelShort = substr($hotelShort, 0, 5);
        }
        $hotelNameSs = strtolower($hotelShort);
        $nameForac = $hotelShort;

        $stmt = $db->prepare("UPDATE ex_hotel SET hotel_name = ?, hotel_property_id = ?, hotel_name_s = ?, hotel_name_ss = ?, name_forac = ?, name_opera = ? WHERE hotel_id = ?");
        $stmt->execute([$hotelName, $propertyId, $hotelShort, $hotelNameSs, $nameForac, $nameOpera, $hotelId]);

        setFlash('success', "แก้ไขข้อมูลโรงแรม '{$hotelName}' เรียบร้อยแล้ว");
        redirect('settings.php?tab=hotels');
    }

    // 11. Delete Hotel
    if ($action === 'delete_hotel') {
        $hotelId = intval($_POST['hotel_id'] ?? 0);

        // Safety checks: check if referenced in ex_user, requests
        $uStmt = $db->prepare("SELECT COUNT(*) FROM ex_user WHERE user_hotel = ? AND user_status = 1");
        $uStmt->execute([$hotelId]);
        $usersCount = $uStmt->fetchColumn();

        $rStmt = $db->prepare("SELECT COUNT(*) FROM requests WHERE hotel_id = ?");
        $rStmt->execute([$hotelId]);
        $requestsCount = $rStmt->fetchColumn();

        if ($usersCount > 0 || $requestsCount > 0) {
            setFlash('error', "ไม่สามารถลบโรงแรมนี้ได้ เนื่องจากมีข้อมูลผูกอยู่กับโรงแรมนี้ (ผู้ใช้งาน/ฐานพนักงาน {$usersCount} คน, คำขอ {$requestsCount} รายการ)");
            redirect('settings.php?tab=hotels');
        }

        $stmt = $db->prepare("DELETE FROM ex_hotel WHERE hotel_id = ?");
        $stmt->execute([$hotelId]);

        setFlash('success', 'ลบโรงแรมออกจากระบบเรียบร้อยแล้ว');
        redirect('settings.php?tab=hotels');
    }
}

// Active tab selection
$activeTab = $_GET['tab'] ?? 'form';
if (!in_array($activeTab, ['form', 'hotels'])) {
    $activeTab = 'form';
}

// Load active matrix definition
$matrix = getActiveSystemMatrixDefinition();

// Master data for hotels tab
$properties = getExProperties();
$hotelsQuery = $db->query("
    SELECT h.*, p.property_name,
           (SELECT COUNT(*) FROM ex_user eu WHERE eu.user_hotel = h.hotel_id AND eu.user_status = 1) AS user_count,
           (SELECT COUNT(*) FROM requests r WHERE r.hotel_id = h.hotel_id) AS request_count
    FROM ex_hotel h
    LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id
    ORDER BY h.hotel_property_id ASC, h.hotel_id ASC
");
$hotelList = $hotelsQuery->fetchAll();

$pageTitle = 'จัดการระบบและแบบฟอร์ม - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto pb-16">

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center space-x-3 mb-1">
            <a href="<?= url('index.php') ?>" class="text-xs text-slate-400 hover:text-sky-600 flex items-center space-x-1">
                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                <span>กลับหน้าหลัก</span>
            </a>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-slate-800 to-sky-900 text-white flex items-center justify-center text-xl shadow-lg">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">จัดการระบบและแบบฟอร์ม</h1>
                    <p class="text-xs text-slate-500 mt-0.5">จัดการสิทธิ์ หมวดหมู่ระบบ และรายชื่อโรงแรมในเครือ Katathani</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center space-x-2 border-b border-slate-200 mb-6">
        <a href="settings.php?tab=form" class="px-5 py-3 border-b-2 font-bold text-sm transition flex items-center space-x-2 <?= $activeTab === 'form' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-list-check"></i>
            <span>หมวดหมู่และสิทธิ์ในแบบฟอร์ม</span>
        </a>
        <a href="settings.php?tab=hotels" class="px-5 py-3 border-b-2 font-bold text-sm transition flex items-center space-x-2 <?= $activeTab === 'hotels' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-hotel"></i>
            <span>รายชื่อโรงแรมในเครือ (Hotels)</span>
            <span class="px-2 py-0.5 text-xs bg-slate-100 text-slate-600 rounded-full font-semibold"><?= count($hotelList) ?></span>
        </a>
    </div>

    <!-- === Dynamic Form Builder & Editor === -->
    <?php if ($activeTab === 'form'): ?>
    <div id="formBuilderSection" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-7 mb-8">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5 mb-6">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                    <span>แก้ไขและจัดการแบบฟอร์ม</span>
                </h2>
            </div>

            <!-- Controls -->
            <div class="flex items-center space-x-3">
                <button type="button" onclick="openAddSectionModal()"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>เพิ่มหมวดหมู่ระบบใหม่</span>
                </button>

                <form method="POST" action="settings.php" onsubmit="return confirm('ยืนยันคืนค่าแบบฟอร์มกลับเป็นค่าเริ่มต้นตามระเบียบ FM-KTNC-IT-003 Rev.00? (การแก้ไขทั้งหมดจะถูกรีเซ็ต)')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <input type="hidden" name="action" value="reset_form">
                    <button type="submit" class="px-3.5 py-2 bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 rounded-xl text-xs font-medium transition flex items-center space-x-1.5 border border-slate-200 hover:border-rose-200 cursor-pointer" title="คืนค่าแบบฟอร์มเริ่มต้น">
                        <i class="fa-solid fa-rotate-left text-[11px]"></i>
                        <span>คืนค่าเริ่มต้น</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- 2 Columns View (Left Column & Right Column) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

            <!-- LEFT COLUMN CATEGORIES -->
            <div>

                <div class="space-y-5">
                    <?php
                    $leftCol = $matrix['left_column'] ?? [];
                    if (empty($leftCol)): ?>
                        <div class="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-300 text-xs text-slate-400">
                            ไม่มีหมวดหมู่ในคอลัมน์นี้
                        </div>
                    <?php endif; ?>

                    <?php foreach ($leftCol as $secId => $sec): ?>
                        <?= renderSectionCard('left_column', $secId, $sec) ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RIGHT COLUMN CATEGORIES -->
            <div>


                <div class="space-y-5">
                    <?php
                    $rightCol = $matrix['right_column'] ?? [];
                    if (empty($rightCol)): ?>
                        <div class="p-8 text-center bg-slate-50 rounded-xl border border-dashed border-slate-300 text-xs text-slate-400">
                            ไม่มีหมวดหมู่ในคอลัมน์นี้
                        </div>
                    <?php endif; ?>

                    <?php foreach ($rightCol as $secId => $sec): ?>
                        <?= renderSectionCard('right_column', $secId, $sec) ?>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </div>
    <?php endif; ?>

    <!-- === Hotel Management Section === -->
    <?php if ($activeTab === 'hotels'): ?>
    <div id="hotelManagementSection" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-7 mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5 mb-6">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                    <i class="fa-solid fa-hotel text-sky-600"></i>
                    <span>รายชื่อโรงแรมทั้งหมดในเครือ Katathani (<?= count($hotelList) ?> แห่ง)</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    จัดการรายชื่อโรงแรมในเครือ เมื่อเพิ่มหรือแก้ไข ข้อมูลจะปรากฏในแบบฟอร์มขอสิทธิ์และหน้าจัดการผู้ใช้งานโดยอัตโนมัติ
                </p>
            </div>

            <!-- Controls -->
            <div class="flex items-center space-x-3">
                <button type="button" onclick="openAddHotelModal()"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center space-x-1.5 cursor-pointer">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>เพิ่มโรงแรมใหม่</span>
                </button>
            </div>
        </div>

        <!-- Property Summary Pills -->
        <div class="flex flex-wrap gap-2 mb-6">
            <?php
            $propCounts = [];
            foreach ($hotelList as $h) {
                $pName = $h['property_name'] ?? 'ไม่ระบุ';
                $propCounts[$pName] = ($propCounts[$pName] ?? 0) + 1;
            }
            ?>
            <span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg">
                ทั้งหมด: <?= count($hotelList) ?> โรงแรม
            </span>
            <?php foreach ($propCounts as $pName => $count): ?>
                <span class="px-3 py-1 bg-sky-50 text-sky-700 border border-sky-100 text-xs font-semibold rounded-lg">
                    <?= htmlspecialchars($pName) ?>: <?= $count ?>
                </span>
            <?php endforeach; ?>
        </div>

        <!-- Desktop Table (md and up) -->
        <div class="hidden md:block overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                        <th class="py-3 px-4 w-12 text-center">ID</th>
                        <th class="py-3 px-4">ชื่อโรงแรม</th>
                        <th class="py-3 px-4">ทำเล / พื้นที่ (Property)</th>
                        <th class="py-3 px-4 text-center">ตัวย่อ</th>
                        <th class="py-3 px-4 text-center">รหัส Opera</th>
                        <th class="py-3 px-4 text-center">ข้อมูลที่ผูก</th>
                        <th class="py-3 px-4 text-center w-28">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($hotelList)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">ยังไม่มีข้อมูลโรงแรมในระบบ</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($hotelList as $h): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 text-center font-mono text-slate-400 font-medium"><?= $h['hotel_id'] ?></td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($h['hotel_name']) ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-sky-100 text-sky-800">
                                        <i class="fa-solid fa-location-dot mr-1 text-[10px]"></i>
                                        <?= htmlspecialchars($h['property_name'] ?? 'ไม่ระบุ') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="font-mono font-semibold px-2 py-0.5 bg-slate-100 rounded text-slate-700">
                                        <?= htmlspecialchars($h['hotel_name_s']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-slate-600">
                                    <?= !empty($h['name_opera']) ? htmlspecialchars($h['name_opera']) : '<span class="text-slate-300">-</span>' ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="inline-flex items-center space-x-2 text-[11px]">
                                        <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-600" title="จำนวนผู้ใช้งาน">
                                            <i class="fa-solid fa-user mr-1 text-slate-400"></i><?= $h['user_count'] ?>
                                        </span>
                                        <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-600" title="จำนวนคำขอ">
                                            <i class="fa-solid fa-file-lines mr-1 text-slate-400"></i><?= $h['request_count'] ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <button type="button"
                                            onclick='openEditHotelModal(<?= json_encode($h, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                            class="p-1.5 text-slate-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition cursor-pointer" title="แก้ไข">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>

                                        <form method="POST" action="settings.php?tab=hotels" class="inline"
                                            onsubmit="return confirm('ยืนยันลบโรงแรม <?= htmlspecialchars($h['hotel_name'], ENT_QUOTES) ?> ออกจากระบบ?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_hotel">
                                            <input type="hidden" name="hotel_id" value="<?= $h['hotel_id'] ?>">
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer" title="ลบโรงแรม">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards (md:hidden) -->
        <div class="block md:hidden space-y-3">
            <?php if (empty($hotelList)): ?>
                <div class="p-6 text-center text-slate-400 text-xs bg-slate-50 rounded-xl">
                    ยังไม่มีข้อมูลโรงแรมในระบบ
                </div>
            <?php else: ?>
                <?php foreach ($hotelList as $h): ?>
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($h['hotel_name']) ?></div>
                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">ID: <?= $h['hotel_id'] ?> | ตัวย่อ: <?= htmlspecialchars($h['hotel_name_s']) ?></div>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-100 text-sky-800 shrink-0">
                                <?= htmlspecialchars($h['property_name'] ?? 'ไม่ระบุ') ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
                            <div class="text-[11px] text-slate-500">
                                <span>ผูกผู้ใช้: <?= $h['user_count'] ?></span> | <span>คำขอ: <?= $h['request_count'] ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button"
                                    onclick='openEditHotelModal(<?= json_encode($h, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    class="px-2.5 py-1 bg-sky-50 text-sky-700 rounded-lg text-xs font-semibold hover:bg-sky-100 transition cursor-pointer">
                                    <i class="fa-solid fa-pen text-[10px] mr-1"></i>แก้ไข
                                </button>
                                <form method="POST" action="settings.php?tab=hotels" class="inline"
                                    onsubmit="return confirm('ยืนยันลบโรงแรม <?= htmlspecialchars($h['hotel_name'], ENT_QUOTES) ?>?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete_hotel">
                                    <input type="hidden" name="hotel_id" value="<?= $h['hotel_id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 bg-rose-50 text-rose-700 rounded-lg text-xs font-semibold hover:bg-rose-100 transition cursor-pointer">
                                        <i class="fa-solid fa-trash text-[10px] mr-1"></i>ลบ
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>

</div>

<?php
/**
 * Helper to render each section card in admin settings
 */
function renderSectionCard($col, $secId, $sec)
{
    $title = htmlspecialchars($sec['title'] ?? $secId);
    $items = $sec['items'] ?? [];
    $isOpera = ($sec['type'] ?? '') === 'opera';
    ob_start();
?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hover:border-slate-300 transition">
        <!-- Section Card Header -->
        <div class="bg-slate-50/80 px-4 py-3 border-b border-slate-200 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-xs font-bold text-slate-900"><?= $title ?></h3>
                <div class="text-[10px] text-slate-400">
                    <span><?= count($items) ?> รายการ</span>
                </div>
            </div>

            <!-- Section Controls -->
            <div class="flex items-center space-x-1">
                <button type="button" onclick="openAddItemModal('<?= $col ?>', '<?= htmlspecialchars($secId, ENT_QUOTES) ?>', '<?= htmlspecialchars($title, ENT_QUOTES) ?>')"
                    class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-emerald-700 rounded-lg text-[11px] font-semibold transition border border-slate-200 hover:border-emerald-300 flex items-center space-x-1 shadow-2xs" title="เพิ่มรายการสิทธิ์ในหมวดนี้">
                    <i class="fa-solid fa-plus text-[9px]"></i>
                    <span>เพิ่มสิทธิ์</span>
                </button>

                <button type="button" onclick="openEditSectionModal('<?= $col ?>', '<?= htmlspecialchars($secId, ENT_QUOTES) ?>', '<?= htmlspecialchars($title, ENT_QUOTES) ?>')"
                    class="p-1.5 text-slate-400 hover:text-sky-600 hover:bg-white rounded-lg transition" title="แก้ไขชื่อหมวดหมู่">
                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                </button>

                <form method="POST" action="settings.php" class="inline" onsubmit="return confirm('ยืนยันลบหมวดหมู่ <?= htmlspecialchars($title, ENT_QUOTES) ?> พร้อมรายการทั้งหมด?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <input type="hidden" name="action" value="delete_section">
                    <input type="hidden" name="col" value="<?= $col ?>">
                    <input type="hidden" name="section_id" value="<?= htmlspecialchars($secId) ?>">
                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-white rounded-lg transition" title="ลบหมวดหมู่นี้">
                        <i class="fa-regular fa-trash-can text-xs"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Section Card Body: Items List -->
        <div class="p-3">
            <?php if (empty($items)): ?>
                <div class="py-4 text-center text-[11px] text-slate-400 italic">
                    ยังไม่มีรายการสิทธิ์ในหมวดหมู่นี้ (กด "เพิ่มสิทธิ์" เพื่อสร้างรายการแรก)
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 text-xs">
                    <?php foreach ($items as $it):
                        $itId = htmlspecialchars($it['id']);
                        $itLabel = htmlspecialchars($it['label']);
                        $itType = $it['type'] ?? 'checkbox';
                        $itPlaceholder = htmlspecialchars($it['placeholder'] ?? '');
                    ?>
                        <li class="py-2 px-1 flex items-center justify-between group hover:bg-slate-50/70 rounded-lg transition">
                            <div class="flex items-center space-x-2.5">
                                <span class="text-[11px] <?= $itType === 'text_input' ? 'text-amber-500' : 'text-sky-500' ?>">
                                    <i class="<?= $itType === 'text_input' ? 'fa-regular fa-pen-to-square' : 'fa-regular fa-square-check' ?>"></i>
                                </span>
                                <div>
                                    <span class="font-medium text-slate-800"><?= $itLabel ?></span>
                                    <span class="text-[10px] text-slate-400 block"><?= $itType === 'text_input' ? 'กล่องติ๊กเลือกพร้อมช่องพิมพ์ข้อความ (Checkbox + Text)' : 'กล่องติ๊กเลือก (Checkbox)' ?></span>
                                </div>
                            </div>

                            <!-- Item Actions -->
                            <div class="flex items-center space-x-1 opacity-70 group-hover:opacity-100 transition">
                                <button type="button" onclick="openEditItemModal('<?= $col ?>', '<?= htmlspecialchars($secId, ENT_QUOTES) ?>', '<?= htmlspecialchars($itId, ENT_QUOTES) ?>', '<?= htmlspecialchars($itLabel, ENT_QUOTES) ?>', '<?= htmlspecialchars($itType, ENT_QUOTES) ?>', '<?= htmlspecialchars($itPlaceholder, ENT_QUOTES) ?>')"
                                    class="p-1 text-slate-400 hover:text-sky-600 transition" title="แก้ไขรายการ">
                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                </button>

                                <form method="POST" action="settings.php" class="inline" onsubmit="return confirm('ยืนยันลบรายการสิทธิ์ <?= htmlspecialchars($itLabel, ENT_QUOTES) ?>?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="col" value="<?= $col ?>">
                                    <input type="hidden" name="section_id" value="<?= htmlspecialchars($secId) ?>">
                                    <input type="hidden" name="item_id" value="<?= $itId ?>">
                                    <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="ลบรายการนี้">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php
    return ob_get_clean();
}
?>

<!-- === MODALS FOR ADDING / EDITING SECTIONS & ITEMS === -->

<!-- 1. Modal: Add Section -->
<div id="modalAddSection" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="font-bold text-sm text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-folder-plus text-emerald-600"></i>
                <span>เพิ่มหมวดหมู่ระบบใหม่</span>
            </h3>
            <button type="button" onclick="closeModal('modalAddSection')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="settings.php" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="add_section">

            <div>
                <label class="block font-bold text-slate-700 mb-1">ตำแหน่งคอลัมน์ในแบบฟอร์ม <span class="text-rose-500">*</span></label>
                <select name="col" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
                    <option value="left_column">หมวดหมู่ฝั่งซ้าย (Left Column)</option>
                    <option value="right_column">หมวดหมู่ฝั่งขวา (Right Column)</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">ชื่อหมวดหมู่ (Section Title) <span class="text-rose-500">*</span></label>
                <input type="text" name="section_title" required placeholder="เช่น Canva Team / AI Tools / Cloud Storage"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalAddSection')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold transition shadow-sm">บันทึกหมวดหมู่</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Modal: Edit Section -->
<div id="modalEditSection" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="font-bold text-sm text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-pen-to-square text-sky-600"></i>
                <span>แก้ไขหมวดหมู่ระบบ</span>
            </h3>
            <button type="button" onclick="closeModal('modalEditSection')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="settings.php" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="edit_section">
            <input type="hidden" name="col" id="editSecCol">
            <input type="hidden" name="section_id" id="editSecId">

            <div>
                <label class="block font-bold text-slate-700 mb-1">ตำแหน่งคอลัมน์ในแบบฟอร์ม</label>
                <select name="new_col" id="editSecNewCol" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
                    <option value="left_column">หมวดหมู่ฝั่งซ้าย (Left Column)</option>
                    <option value="right_column">หมวดหมู่ฝั่งขวา (Right Column)</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">ชื่อหมวดหมู่ (Section Title) <span class="text-rose-500">*</span></label>
                <input type="text" name="section_title" id="editSecTitle" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalEditSection')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold transition shadow-sm">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Modal: Add Item -->
<div id="modalAddItem" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="font-bold text-sm text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-plus-circle text-emerald-600"></i>
                <span>เพิ่มรายการสิทธิ์ใน <span id="addItemSectionTitle" class="text-sky-600"></span></span>
            </h3>
            <button type="button" onclick="closeModal('modalAddItem')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="settings.php" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="col" id="addItemCol">
            <input type="hidden" name="section_id" id="addItemSecId">

            <div>
                <label class="block font-bold text-slate-700 mb-1">ชื่อรายการสิทธิ์ (Item Label) <span class="text-rose-500">*</span></label>
                <input type="text" name="item_label" required placeholder="เช่น Canva Pro License / สิทธิ์เข้าถึงไดรฟ์แชร์"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">ประเภทช่องกรอก (Input Type)</label>
                <select name="item_type" id="addItemType" onchange="togglePlaceholder('addItemType', 'addItemPlaceholderRow')" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
                    <option value="checkbox">กล่องติ๊กเลือก (Checkbox)</option>
                    <option value="text_input">กล่องติ๊กเลือกพร้อมช่องพิมพ์ข้อความ (Checkbox + Text Input)</option>
                </select>
            </div>

            <div id="addItemPlaceholderRow" class="hidden">
                <label class="block font-bold text-slate-700 mb-1">ข้อความแนะนำในช่องกรอก (Placeholder)</label>
                <input type="text" name="placeholder" placeholder="เช่น ระบุชื่อโฟลเดอร์ หรือ รายละเอียดที่ต้องการ"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalAddItem')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold transition shadow-sm">เพิ่มรายการ</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Modal: Edit Item -->
<div id="modalEditItem" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="font-bold text-sm text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-pen text-sky-600"></i>
                <span>แก้ไขรายการสิทธิ์</span>
            </h3>
            <button type="button" onclick="closeModal('modalEditItem')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="settings.php" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="edit_item">
            <input type="hidden" name="col" id="editItemCol">
            <input type="hidden" name="section_id" id="editItemSecId">
            <input type="hidden" name="item_id" id="editItemId">

            <div>
                <label class="block font-bold text-slate-700 mb-1">ชื่อรายการสิทธิ์ (Item Label) <span class="text-rose-500">*</span></label>
                <input type="text" name="item_label" id="editItemLabel" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">ประเภทช่องกรอก (Input Type)</label>
                <select name="item_type" id="editItemType" onchange="togglePlaceholder('editItemType', 'editItemPlaceholderRow')" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
                    <option value="checkbox">กล่องติ๊กเลือก (Checkbox)</option>
                    <option value="text_input">กล่องติ๊กเลือกพร้อมช่องพิมพ์ข้อความ (Checkbox + Text Input)</option>
                </select>
            </div>

            <div id="editItemPlaceholderRow" class="hidden">
                <label class="block font-bold text-slate-700 mb-1">ข้อความแนะนำในช่องกรอก (Placeholder)</label>
                <input type="text" name="placeholder" id="editItemPlaceholder"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalEditItem')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold transition shadow-sm">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Modal: Add Hotel -->
<div id="modalAddHotel" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="font-bold text-sm text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-hotel text-emerald-600"></i>
                <span>เพิ่มโรงแรมใหม่เข้าสู่ระบบ</span>
            </h3>
            <button type="button" onclick="closeModal('modalAddHotel')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="settings.php?tab=hotels" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="add_hotel">

            <div>
                <label class="block font-bold text-slate-700 mb-1">ชื่อโรงแรม (Hotel Name) <span class="text-rose-500">*</span></label>
                <input type="text" name="hotel_name" required placeholder="เช่น Katathani Grand Beach Resort" maxlength="30"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">กลุ่มทำเล / พื้นที่ (Property) <span class="text-rose-500">*</span></label>
                <select name="hotel_property_id" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
                    <?php foreach ($properties as $prop): ?>
                        <?php if ($prop['property_id'] != 99): ?>
                            <option value="<?= $prop['property_id'] ?>"><?= htmlspecialchars($prop['property_name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">ชื่อย่อ (Short Code)</label>
                    <input type="text" name="hotel_name_s" placeholder="เช่น KTG (สูงสุด 5 ตัว)" maxlength="5"
                        class="w-full px-3 py-2 border border-slate-300 rounded-xl uppercase focus:ring-2 focus:ring-sky-500">
                    <p class="text-[10px] text-slate-400 mt-1">หากไม่ระบุ ระบบจะย่อจากชื่อให้อัตโนมัติ</p>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">รหัสระบบ Opera</label>
                    <input type="text" name="name_opera" placeholder="เช่น KTG (ไม่บังคับ)" maxlength="20"
                        class="w-full px-3 py-2 border border-slate-300 rounded-xl uppercase focus:ring-2 focus:ring-sky-500">
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalAddHotel')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition cursor-pointer">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold transition shadow-sm cursor-pointer">บันทึกโรงแรม</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. Modal: Edit Hotel -->
<div id="modalEditHotel" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <h3 class="font-bold text-sm text-slate-900 flex items-center space-x-2">
                <i class="fa-solid fa-pen-to-square text-sky-600"></i>
                <span>แก้ไขข้อมูลโรงแรม</span>
            </h3>
            <button type="button" onclick="closeModal('modalEditHotel')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="settings.php?tab=hotels" class="space-y-4 text-xs">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="edit_hotel">
            <input type="hidden" name="hotel_id" id="editHotelId">

            <div>
                <label class="block font-bold text-slate-700 mb-1">ชื่อโรงแรม (Hotel Name) <span class="text-rose-500">*</span></label>
                <input type="text" name="hotel_name" id="editHotelName" required maxlength="30"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">กลุ่มทำเล / พื้นที่ (Property) <span class="text-rose-500">*</span></label>
                <select name="hotel_property_id" id="editHotelPropertyId" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500">
                    <?php foreach ($properties as $prop): ?>
                        <?php if ($prop['property_id'] != 99): ?>
                            <option value="<?= $prop['property_id'] ?>"><?= htmlspecialchars($prop['property_name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">ชื่อย่อ (Short Code)</label>
                    <input type="text" name="hotel_name_s" id="editHotelNameS" maxlength="5"
                        class="w-full px-3 py-2 border border-slate-300 rounded-xl uppercase focus:ring-2 focus:ring-sky-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">รหัสระบบ Opera</label>
                    <input type="text" name="name_opera" id="editHotelNameOpera" maxlength="20"
                        class="w-full px-3 py-2 border border-slate-300 rounded-xl uppercase focus:ring-2 focus:ring-sky-500">
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeModal('modalEditHotel')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium transition cursor-pointer">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold transition shadow-sm cursor-pointer">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script>
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function openAddHotelModal() {
        document.getElementById('modalAddHotel').classList.remove('hidden');
    }

    function openEditHotelModal(hotel) {
        document.getElementById('editHotelId').value = hotel.hotel_id;
        document.getElementById('editHotelName').value = hotel.hotel_name;
        document.getElementById('editHotelPropertyId').value = hotel.hotel_property_id;
        document.getElementById('editHotelNameS').value = hotel.hotel_name_s;
        document.getElementById('editHotelNameOpera').value = hotel.name_opera || '';
        document.getElementById('modalEditHotel').classList.remove('hidden');
    }

    function openAddSectionModal() {
        document.getElementById('modalAddSection').classList.remove('hidden');
    }

    function openEditSectionModal(col, secId, title) {
        document.getElementById('editSecCol').value = col;
        document.getElementById('editSecNewCol').value = col;
        document.getElementById('editSecId').value = secId;
        document.getElementById('editSecTitle').value = title;
        document.getElementById('modalEditSection').classList.remove('hidden');
    }

    function openAddItemModal(col, secId, secTitle) {
        document.getElementById('addItemCol').value = col;
        document.getElementById('addItemSecId').value = secId;
        document.getElementById('addItemSectionTitle').innerText = secTitle;
        document.getElementById('addItemType').value = 'checkbox';
        document.getElementById('addItemPlaceholderRow').classList.add('hidden');
        document.getElementById('modalAddItem').classList.remove('hidden');
    }

    function openEditItemModal(col, secId, itemId, label, type, placeholder) {
        document.getElementById('editItemCol').value = col;
        document.getElementById('editItemSecId').value = secId;
        document.getElementById('editItemId').value = itemId;
        document.getElementById('editItemLabel').value = label;
        document.getElementById('editItemType').value = type;
        document.getElementById('editItemPlaceholder').value = placeholder || '';
        if (type === 'text_input') {
            document.getElementById('editItemPlaceholderRow').classList.remove('hidden');
        } else {
            document.getElementById('editItemPlaceholderRow').classList.add('hidden');
        }
        document.getElementById('modalEditItem').classList.remove('hidden');
    }

    function togglePlaceholder(selectId, rowId) {
        var sel = document.getElementById(selectId);
        var row = document.getElementById(rowId);
        if (sel && row) {
            if (sel.value === 'text_input') {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
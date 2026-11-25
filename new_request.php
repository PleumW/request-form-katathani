<?php

/**
 * New Request Form (FM-KTNC-IT-003 Rev.00)
 * Responsive Tailwind UI with Digital Signature Pad
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

// Security: Require authentication
if (!isLoggedIn()) {
    redirect('login.php');
}

$currentUser = getCurrentUser();
$matrix = getActiveSystemMatrixDefinition();
$allHotels = getExHotels();
$allDepartments = getExDepartments();
$allTitles = getExTitles();
$allProperties = getExProperties();
$pageTitle = 'ยื่นคำขอใหม่ - ' . APP_NAME;

require_once __DIR__ . '/includes/header.php';
?>

<!-- MANDATORY TERMS & POLICY AGREEMENT MODAL (FM-KTNC-IT-002Rev01) -->
<div id="policyModalOverlay" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-3xl flex flex-col max-h-[92vh] overflow-hidden">

        <!-- Modal Header -->
        <div class="p-5 sm:p-6 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
            <div class="flex items-center space-x-3.5">
                <img src="<?= url('photo/images-Photoroom.png') ?>" alt="Logo" class="h-10 w-auto object-contain" onerror="this.onerror=null; this.src='photo/images-Photoroom.png';">
                <div>
                    <span class="text-[10px] sm:text-xs font-bold text-sky-700 uppercase tracking-wider block">Katathani Collection</span>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">
                        <?= lang('ระเบียบการใช้อินเตอร์เน็ต และเครือข่ายคอมพิวเตอร์ของบริษัท', 'Company Internet and Computer Network Regulations') ?>
                    </h2>
                </div>
            </div>
        </div>

        <!-- Scrollable Agreement Text Body (User MUST scroll to the bottom) -->
        <div id="policyScrollContainer" class="overflow-y-auto p-5 sm:p-7 space-y-4 text-xs sm:text-sm text-slate-700 leading-relaxed max-h-[58vh] bg-white">

            <!-- Notice Box -->
            <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl text-amber-950 text-xs sm:text-sm font-medium">
                <strong><?= lang('การยอมรับ :', 'Acknowledgement:') ?></strong>
                <?= lang('ผู้มีสิทธิ์ใช้งานคอมพิวเตอร์ทุกคน ต้องทำความเข้าใจและยอมรับระเบียบโดยการลงชื่อ และเอกสารฉบับนี้จะถูกจัดเก็บในแฟ้มประวัติพนักงาน ที่ฝ่ายบุคคล', 'All authorized computer users must understand and accept these regulations by acknowledging. This document will be archived in the employee record at the HR Department.') ?>
            </div>

            <!-- Intro Paragraph -->
            <p class="text-slate-700 text-justify">
                <?= lang('คอมพิวเตอร์และเครือข่ายคอมพิวเตอร์ คือทรัพย์สินของโรงแรมในเครือ กะตะธานี คอลเลคชั่น ซึ่งอยู่ภายใต้การดูแลและควบคุมโดย ไอที ผู้ใช้คอมพิวเตอร์ทุกคนมีหน้าที่ใช้ทรัพยากรคอมพิวเตอร์ที่ทางโรงแรมจัดให้ ให้เกิดประโยชน์ต่อหน้าที่ของตน ชอบด้วยกฎหมายและมีจริยธรรมในการใช้งาน เพื่อที่จะลดความเสี่ยงที่อาจจะเกิดขึ้นจากการปฏิบัติงาน ทางฝ่ายผู้บริหารของโรงแรม ได้กำหนดระเบียบการใช้คอมพิวเตอร์ เครือข่ายคอมพิวเตอร์และอินเตอร์เน็ตดังต่อไปนี้', 'Computers and computer networks are property of Katathani Collection, under the supervision and management of the IT Department. All authorized users are responsible for utilizing computer resources provided by the hotel for legitimate job duties, lawfully, and ethically. In order to mitigate potential operational risks, hotel executive management has established the following computer, network, and internet regulations:') ?>
            </p>

            <!-- Rules Header -->
            <h3 class="text-xs sm:text-sm font-bold text-slate-900 border-b border-slate-200 pb-2 pt-1 flex items-center space-x-2">
                <span><?= lang('ระเบียบการใช้คอมพิวเตอร์ เครือข่ายคอมพิวเตอร์และอินเตอร์เน็ต', 'Computer, Computer Network, and Internet Regulations') ?></span>
            </h3>

            <!-- 9 Rules from FM-KTNC-IT-002Rev01 -->
            <ol class="space-y-3 pl-1 text-slate-700 list-decimal list-inside text-justify">
                <li class="pl-1"><?= lang('ผู้ใช้งานจะต้องมีความรู้ในการใช้งาน และการดูแลคอมพิวเตอร์และเครือข่ายคอมพิวเตอร์เบื้องต้น', 'Users must possess basic knowledge of computer and network operation and maintenance.') ?></li>
                <li class="pl-1"><?= lang('ผู้ใช้งานจะต้องดูแลระบบคอมพิวเตอร์ให้อยู่ในสภาพที่ดีพร้อมใช้งาน และรักษาความสะอาดอย่างสม่ำเสมอ หากพบว่าเกิดความผิดปกติจะต้องแจ้งไอทีรับทราบทันที', 'Users must maintain computer systems in clean and operational condition. Any malfunctions must be reported immediately to the IT Department.') ?></li>
                <li class="pl-1"><?= lang('ห้ามผู้ที่ไม่ได้รับอนุญาตหรือเกี่ยวกับการรับส่ง แจกจ่าย ข้อมูลที่เป็นความลับของโรงแรมและห้ามเผยแพร่ข้อมูลในระบบเครือข่ายคอมพิวเตอร์ของบริษัทสู่บุคคลภายนอก', 'Unauthorized transmission, distribution, or dissemination of company confidential information or internal network data to third parties is strictly prohibited.') ?></li>
                <li class="pl-1"><?= lang('ห้ามส่งข้อมูล และจัดเท็จข้อมูล โดยจดหมายอิเล็กทรอนิกส์ เวปบอร์ด แชทรูม หรือ ห้องสนทนา ผ่านระบบอินเตอร์เน็ต รวมถึงแอฟฟริเคชั่นต่างๆ ที่แสดงออกถึง การหลอกลวง ลามกอนาจาร ข่มขู่คุกคาม สื่อที่ผิดกฎหมาย หรือ หมิ่นประมาทผู้อื่น ทั้งนี้หากผู้ใดได้รับข้อมูลที่กล่าวไว้ข้างต้น ต้องลบทิ้งโดยทันที', 'Transmitting or fabricating data via email, web forums, chat rooms, messaging platforms, or apps that contains deceptive, obscene, threatening, defamatory, or unlawful content is strictly prohibited. Any recipient of such materials must delete them immediately.') ?></li>
                <li class="pl-1"><?= lang('ห้ามทำสำเนาสื่อหรือข้อมูลที่อยู่ภายใต้ความคุ้มครองกฎหมายลิขสิทธิ์ หรือดาวน์โหลดข้อมูล หรือ สื่อลิขสิทธิ์ใดๆโดยปราศจากการอนุญาต จาก ฝ่ายบริหาร', 'Duplicating copyright-protected media or downloading copyrighted materials without explicit authorization from Management is prohibited.') ?></li>
                <li class="pl-1"><?= lang('ผู้ใช้งาน จะต้องไม่สงวนสิทธิ์ในงานที่ได้สร้าง จัดเก็บ รับมาหรือ ส่งออก ซึ่งงานที่เกิดจากการใช้คอมพิวเตอร์ เครือข่ายคอมพิวเตอร์ หรือ อินเตอร์เน็ตที่ทางโรงแรมจัดให้', 'Users waive any personal proprietary rights over work created, stored, received, or sent using the company’s computers, networks, or internet access.') ?></li>
                <li class="pl-1"><?= lang('ผู้ใช้ จะต้องยินยอมให้ตรวจสอบ และ ไม่สงวนสิทธิ์ในงาน ที่สร้าง จัดเก็บ รับมาหรือส่งออก ซึ่งเกิดจากการใช้คอมพิวเตอร์ เครือข่ายคอมพิวเตอร์ หรืออินเตอร์เน็ตที่โรงแรมให้', 'Users must consent to audits and inspections of any data and activities conducted using company computer and internet resources.') ?></li>
                <li class="pl-1"><?= lang('ผู้บริหารระบบของโรงแรม มีสิทธิ์ที่จะติดตาม เก็บบันทึก การใช้งานคอมพิวเตอร์ การเข้าถึงเว็ปไซด์ และ ระงับสิทธิ์การใช้งานในส่วนที่เห็นว่าไม่เหมาะสม', 'System administrators reserve the right to monitor, log computer and website activity, and suspend access deemed inappropriate.') ?></li>
                <li class="pl-1"><?= lang('ผู้ใช้งานคอมพิวเตอร์ต้องมี ชื่อและรหัส ที่ออกให้โดยผู้ดูแลระบบ ผู้ใช้ต้องรักษาเป็นความลับไม่ให้ผู้อื่นนำไปใช้ หากลืมรหัสหรือรู้สึกว่า ถูกนำไปใช้โดยบุคคลอื่น ต้องรีบแจ้งผู้ดูแลระบบโดยทันที', 'Computer users must maintain confidentiality of their system credentials issued by the administrator. Any forgotten or compromised password must be reported immediately.') ?></li>
            </ol>

            <!-- Agreement Acceptance Box -->
            <div class="mt-4 p-4 bg-sky-50 border border-sky-200 rounded-2xl text-sky-950 space-y-1.5">
                <div class="font-bold text-xs sm:text-sm text-sky-900 flex items-center space-x-1.5">
                    <span><?= lang('ยินยอมรับระเบียบ', 'Acceptance of Regulations') ?></span>
                </div>
                <p class="text-xs sm:text-sm text-slate-700 leading-normal text-justify">
                    <?= lang('ข้าพเจ้าได้อ่าน และยินดีที่จะปฏิบัติตามเงื่อนไขการใช้งานคอมพิวเตอร์และอินเตอร์เน็ต ตามระเบียบที่ออกโดย โรงแรม ในเครือ กะตะธานี คอลเลคชั่น ฉบับนี้ และเข้าใจเป็นอย่างดีว่า การละเมิด อาจถูกลงโทษจากบริษัท หรืออาจส่งผลถึงความผิดในทางคดีอาญา', 'I have read, understood, and agree to strictly comply with the computer and internet acceptable use regulations issued by Katathani Collection. I fully understand that any violation may result in company disciplinary measures or criminal legal liability.') ?>
                </p>
            </div>
            <div class="flex justify-between mr-2 ml-2 text-gray-400 sm:text-sm">
                <p>FM-KTNC-IT-002Rev01</p>
                <p>Revised</p>
                <p>Effective 13.04.22</p>
            </div>
        </div>

        <!-- Modal Footer with Scroll Indicator & Action Buttons -->
        <div class="p-4 sm:p-5 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div id="scrollHint" class="text-xs text-amber-800 bg-amber-50 px-3.5 py-1.5 rounded-xl border border-amber-200 flex items-center space-x-2">
                <i class="fa-solid fa-arrow-down animate-bounce text-amber-600"></i>
                <span><?= lang('กรุณาอ่านระเบียบให้ครบถ้วนก่อนกดยินยอม', 'Please scroll down and read through the regulations before accepting') ?></span>
            </div>

            <div class="flex items-center space-x-3 w-full sm:w-auto justify-end">
                <a href="index.php" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-100 text-xs sm:text-sm font-semibold transition">
                    <?= lang('ไม่ยินยอม', 'Decline') ?>
                </a>
                <button type="button" id="btnAcceptPolicy" disabled
                    class="px-5 py-2.5 bg-slate-200 text-slate-400 rounded-xl text-xs sm:text-sm font-bold cursor-not-allowed transition flex items-center space-x-2 select-none">
                    <i class="fa-solid fa-check"></i>
                    <span><?= lang('ยินยอม', 'Accept') ?></span>
                </button>
            </div>
        </div>

    </div>
</div>

<div class="max-w-6xl mx-auto pb-16">

    <!-- Form Header Banner -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div class="flex items-center justify-center">
                <div class="flex items-center space-x-4">
                    <img src="<?= url('photo/images-Photoroom.png') ?>" alt="Katathani Logo" class="h-13 w-auto object-contain max-w-[100px]" onerror="this.onerror=null; this.src='photo/images-Photoroom.png';">
                    <h1 class="text-2xl font-bold text-slate-900 mt-0.5">REQUEST FORM</h1>
                </div>
            </div>
        </div>

        <form id="requestForm" action="actions/submit_request.php" method="POST" class="mt-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

            <!-- SECTION 1: General Employee Information -->
            <div class="mb-8">
                <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2 mb-4">
                    <span class="w-6 h-6 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center text-xs font-bold">1</span>
                    <span><?= lang('ข้อมูลพนักงานและสถานที่ปฏิบัติงาน', 'Employee & Property Information') ?></span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 bg-slate-50/70 p-5 rounded-2xl border border-slate-200/80">

                    <!-- Property (สถานที่ปฏิบัติงาน - จาก ex_hotel & ex_property) -->
                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="block text-xs font-bold text-slate-700 mb-2">
                            <?= lang('สถานที่ปฏิบัติงาน', 'Property & Location') ?> <span class="text-rose-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                            <!-- 1. Katathani (hotel_id: 1) -->
                            <label class="flex items-center space-x-3 p-3 bg-white rounded-xl border border-slate-200 cursor-pointer hover:border-sky-500 hover:bg-sky-50/50 transition">
                                <input type="radio" name="hotel_id" value="1" required class="hotel-radio w-4 h-4 text-sky-600 border-slate-300 focus:ring-sky-500" <?= ($currentUser['hotel_id'] ?? 1) == 1 ? 'checked' : '' ?>>
                                <div>
                                    <span class="block text-xs font-bold text-slate-800">Katathani</span>
                                </div>
                            </label>

                            <!-- 2. The Shore (hotel_id: 2) -->
                            <label class="flex items-center space-x-3 p-3 bg-white rounded-xl border border-slate-200 cursor-pointer hover:border-sky-500 hover:bg-sky-50/50 transition">
                                <input type="radio" name="hotel_id" value="2" required class="hotel-radio w-4 h-4 text-sky-600 border-slate-300 focus:ring-sky-500" <?= ($currentUser['hotel_id'] ?? 1) == 2 ? 'checked' : '' ?>>
                                <div>
                                    <span class="block text-xs font-bold text-slate-800">The Shore</span>
                                </div>
                            </label>

                            <!-- 3. The Sea Gallery (hotel_id: 5) -->
                            <label class="flex items-center space-x-3 p-3 bg-white rounded-xl border border-slate-200 cursor-pointer hover:border-sky-500 hover:bg-sky-50/50 transition">
                                <input type="radio" name="hotel_id" value="5" required class="hotel-radio w-4 h-4 text-sky-600 border-slate-300 focus:ring-sky-500" <?= ($currentUser['hotel_id'] ?? 1) == 5 ? 'checked' : '' ?>>
                                <div>
                                    <span class="block text-xs font-bold text-slate-800">The Sea Gallery</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Employee ID (Searchable Dropdown from ex_user) -->
                    <div class="relative z-30" id="empDropdownWrapper">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                            <span><?= lang('รหัสพนักงาน', 'Employee ID') ?> <span class="text-rose-500">*</span></span>
                            <span id="empLookupStatus" class="text-[10px] font-normal hidden"></span>
                        </label>
                        <div class="relative">
                            <input type="text" name="employee_id" id="employee_id" required value="<?= htmlspecialchars($currentUser['employee_id'] ?? '') ?>" placeholder="<?= lang('คลิกเพื่อเลือก หรือพิมพ์ค้นหาชื่อ / รหัส...', 'Click to select or search name / ID...') ?>" autocomplete="off"
                                class="w-full pl-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono cursor-pointer">
                            <button type="button" id="empDropdownToggleBtn" class="absolute right-0 top-0 bottom-0 px-3 text-slate-400 hover:text-sky-600 flex items-center justify-center cursor-pointer transition">
                                <div id="empLookupSpinner" class="hidden mr-1">
                                    <i class="fa-solid fa-circle-notch fa-spin text-sky-500 text-xs"></i>
                                </div>
                                <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform duration-200" id="empDropdownChevron"></i>
                            </button>
                        </div>

                        <!-- Dropdown Menu for Employee Selection -->
                        <div id="empDropdownList" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-2xl max-h-72 overflow-y-auto z-50 divide-y divide-slate-100 text-xs">
                        </div>
                    </div>

                    <!-- Title (คำนำหน้าชื่อ จาก ex_title) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            <?= lang('คำนำหน้า', 'Title') ?> <span class="text-rose-500">*</span>
                        </label>
                        <select name="title" id="titleSelect" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <option value=""><?= lang('-- เลือกคำนำหน้า --', '-- Select Title --') ?></option>
                            <?php foreach ($allTitles as $t): ?>
                                <option value="<?= htmlspecialchars($t['title_name']) ?>" <?= (($currentUser['title'] ?? '') === $t['title_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['title_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- First Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            <?= lang('ชื่อ', 'First Name') ?> <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="first_name" id="first_name" required value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>" placeholder="<?= lang('ชื่อจริงพนักงาน', 'First name') ?>"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>

                    <!-- Last Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            <?= lang('นามสกุล', 'Last Name') ?> <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="last_name" id="last_name" required value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>" placeholder="<?= lang('นามสกุลพนักงาน', 'Last name') ?>"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>

                    <!-- Department (จาก ex_department) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            <?= lang('แผนก', 'Department') ?> <span class="text-rose-500">*</span>
                        </label>
                        <select name="department" id="departmentSelect" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <option value=""><?= lang('-- เลือกแผนก --', '-- Select Department --') ?></option>
                            <?php foreach ($allDepartments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['dep_name']) ?>" data-id="<?= $dept['dep_id'] ?>" <?= (($currentUser['department'] ?? '') === $dept['dep_name'] || ($currentUser['dep_id'] ?? 0) == $dept['dep_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['dep_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Position -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            <?= lang('ตำแหน่ง', 'Position') ?> <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="position" required value="" placeholder="<?= lang('กรุณาระบุตำแหน่ง', 'Enter position') ?>"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>

                    <!-- Manager's Name (2 cols on large screen) -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            <?= lang('ชื่อผู้จัดการ / หัวหน้างาน', 'Manager / Supervisor Name') ?> <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="manager_name" required value="" placeholder="<?= lang('ชื่อ-สกุล ผู้จัดการฝ่าย / หัวหน้างาน', 'Direct manager / supervisor full name') ?>"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                    </div>

                </div>
            </div>

            <!-- SECTION 2: System Access Matrix (Two Columns Exactly as Paper Form, Dynamically Managed) -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                        <span class="w-6 h-6 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center text-xs font-bold">2</span>
                        <span>รายการสิทธิ์ที่ต้องการเปิดใช้งาน (System Access Matrix)</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                    <!-- LEFT COLUMN -->
                    <div class="space-y-5">
                        <?php
                        $leftCol = $matrix['left_column'] ?? [];
                        foreach ($leftCol as $catKey => $cat):
                            $catTitle = htmlspecialchars($cat['title'] ?? $catKey);
                            $catIcon = htmlspecialchars($cat['icon'] ?? 'fa-solid fa-shapes');
                            $catType = $cat['type'] ?? 'checkboxes';
                            $catItems = $cat['items'] ?? [];
                        ?>
                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                                <div class="pb-3 mb-3 border-b border-slate-100 text-sm font-bold text-slate-800">
                                    <span><?= $catTitle ?></span>
                                </div>

                                <?php if ($catType === 'opera'): ?>
                                    <!-- Property Selectors for Opera -->
                                    <div class="bg-amber-50/60 p-2.5 rounded-xl border border-amber-200/60 mb-3 flex items-center space-x-4 text-xs font-semibold text-amber-900">
                                        <span>Property:</span>
                                        <label class="inline-flex items-center space-x-1.5 cursor-pointer">
                                            <input type="checkbox" name="systems[<?= $catKey ?>][property][katathani]" value="1" class="w-4 h-4 rounded text-amber-600 border-amber-300 focus:ring-amber-500">
                                            <span>Katathani</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-1.5 cursor-pointer">
                                            <input type="checkbox" name="systems[<?= $catKey ?>][property][the_sands]" value="1" class="w-4 h-4 rounded text-amber-600 border-amber-300 focus:ring-amber-500">
                                            <span>The Sands</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-1.5 cursor-pointer">
                                            <input type="checkbox" name="systems[<?= $catKey ?>][property][riverie]" value="1" class="w-4 h-4 rounded text-amber-600 border-amber-300 focus:ring-amber-500">
                                            <span>Riverie</span>
                                        </label>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs text-slate-700">
                                        <?php foreach ($catItems as $item): ?>
                                            <label class="flex items-center space-x-2.5 cursor-pointer hover:text-slate-950 transition">
                                                <input type="checkbox" name="systems[<?= $catKey ?>][roles][<?= $item['id'] ?>]" value="1" class="w-4 h-4 rounded text-sky-600 border-slate-300 focus:ring-sky-500">
                                                <span><?= htmlspecialchars($item['label']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php else: ?>
                                    <div class="space-y-3">
                                        <?php
                                        $checkboxItems = [];
                                        $textItems = [];
                                        foreach ($catItems as $item) {
                                            if (($item['type'] ?? 'checkbox') === 'text_input' || ($item['type'] ?? '') === 'text') {
                                                $textItems[] = $item;
                                            } else {
                                                $checkboxItems[] = $item;
                                            }
                                        }
                                        ?>

                                        <?php if (!empty($checkboxItems)): ?>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs text-slate-700">
                                                <?php foreach ($checkboxItems as $item): ?>
                                                    <label class="flex items-center space-x-2.5 cursor-pointer hover:text-slate-950 transition">
                                                        <input type="checkbox" name="systems[<?= $catKey ?>][<?= $item['id'] ?>]" value="1" class="w-4 h-4 rounded text-sky-600 border-slate-300 focus:ring-sky-500">
                                                        <span><?= htmlspecialchars($item['label']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($textItems)): ?>
                                            <div class="space-y-2.5 pt-1 border-t border-slate-50">
                                                <?php foreach ($textItems as $item): ?>
                                                    <div>
                                                        <label class="flex items-center space-x-2.5 text-xs text-slate-700 cursor-pointer mb-1 hover:text-slate-950">
                                                            <input type="checkbox" name="systems[<?= $catKey ?>][<?= $item['id'] ?>_check]" value="1" class="w-4 h-4 rounded text-sky-600 border-slate-300 focus:ring-sky-500">
                                                            <span class="font-medium"><?= htmlspecialchars($item['label']) ?></span>
                                                            <?php if (!empty($item['sublabel'])): ?>
                                                                <span class="text-[11px] text-slate-400 font-normal">(<?= htmlspecialchars($item['sublabel']) ?>)</span>
                                                            <?php endif; ?>
                                                        </label>
                                                        <input type="text" name="systems[<?= $catKey ?>][<?= $item['id'] ?>]" value="" placeholder="<?= htmlspecialchars($item['placeholder'] ?? 'ระบุรายละเอียด...') ?>"
                                                            class="w-full ml-6 max-w-[calc(100%-1.5rem)] px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-sky-500 transition">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="space-y-5">
                        <?php
                        $rightCol = $matrix['right_column'] ?? [];
                        foreach ($rightCol as $catKey => $cat):
                            $catTitle = htmlspecialchars($cat['title'] ?? $catKey);
                            $catIcon = htmlspecialchars($cat['icon'] ?? 'fa-solid fa-shapes');
                            $catType = $cat['type'] ?? 'checkboxes';
                            $catItems = $cat['items'] ?? [];
                        ?>
                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                                <div class="pb-3 mb-3 border-b border-slate-100 text-sm font-bold text-slate-800">
                                    <span><?= $catTitle ?></span>
                                </div>

                                <div class="space-y-3">
                                    <?php
                                    $checkboxItems = [];
                                    $textItems = [];
                                    foreach ($catItems as $item) {
                                        if (($item['type'] ?? 'checkbox') === 'text_input' || ($item['type'] ?? '') === 'text') {
                                            $textItems[] = $item;
                                        } else {
                                            $checkboxItems[] = $item;
                                        }
                                    }
                                    ?>

                                    <?php if (!empty($checkboxItems)): ?>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs text-slate-700">
                                            <?php foreach ($checkboxItems as $item): ?>
                                                <label class="flex items-center space-x-2.5 cursor-pointer hover:text-slate-950 transition">
                                                    <input type="checkbox" name="systems[<?= $catKey ?>][<?= $item['id'] ?>]" value="1" class="w-4 h-4 rounded text-sky-600 border-slate-300 focus:ring-sky-500">
                                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($textItems)): ?>
                                        <div class="space-y-2.5 pt-1 border-t border-slate-50">
                                            <?php foreach ($textItems as $item): ?>
                                                <div>
                                                    <label class="flex items-center space-x-2.5 text-xs text-slate-700 cursor-pointer mb-1 hover:text-slate-950">
                                                        <input type="checkbox" name="systems[<?= $catKey ?>][<?= $item['id'] ?>_check]" value="1" class="w-4 h-4 rounded text-sky-600 border-slate-300 focus:ring-sky-500">
                                                        <span class="font-medium"><?= htmlspecialchars($item['label']) ?></span>
                                                        <?php if (!empty($item['sublabel'])): ?>
                                                            <span class="text-[11px] text-slate-400 font-normal">(<?= htmlspecialchars($item['sublabel']) ?>)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <input type="text" name="systems[<?= $catKey ?>][<?= $item['id'] ?>]" value="" placeholder="<?= htmlspecialchars($item['placeholder'] ?? 'ระบุรายละเอียด...') ?>"
                                                        class="w-full ml-6 max-w-[calc(100%-1.5rem)] px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-1 focus:ring-sky-500 transition">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>

            <!-- SECTION 3: Terms & Policy Agreement -->
            <div class="bg-slate-900 text-white rounded-2xl p-6 sm:p-8 shadow-lg mb-8">
                <div class="max-w-2xl mx-auto">

                    <h2 class="text-base font-bold text-white flex items-center space-x-2 mb-4">
                        <span class="w-6 h-6 rounded-lg bg-sky-500 text-slate-950 flex items-center justify-center text-xs font-bold">3</span>
                        <span><?= lang('เงื่อนไขและยอมรับข้อตกลง', 'Terms & Policy Acceptance') ?></span>
                    </h2>

                    <!-- Policy Checkbox -->
                    <div class="bg-slate-800/90 border border-slate-700 rounded-2xl p-5 sm:p-6 mb-4">
                        <label class="flex items-start space-x-3.5 cursor-pointer">
                            <input type="checkbox" id="policyAgreement" name="policy_agreed" value="1" required
                                class="w-5 h-5 mt-0.5 rounded text-sky-500 border-slate-600 focus:ring-sky-400 focus:ring-offset-slate-800 pointer-events-none">
                            <div class="text-xs sm:text-sm text-slate-200 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="text-white font-bold text-sm sm:text-base"><?= lang('ข้าพเจ้าได้เข้าใจนโยบายและระเบียบการใช้งานด้านไอที', 'I have understood all relevant IT policies') ?> <span class="text-rose-400">*</span></p>
                                    <button type="button" id="btnReviewPolicy" class="text-xs text-sky-400 hover:text-sky-300 underline font-normal">
                                        <?= lang('คลิกเพื่อดูระเบียบอีกครั้ง', 'Click to review regulations again') ?>
                                    </button>
                                </div>
                                <p class="text-slate-300 text-xs mt-1"><?= lang('ข้าพเจ้าได้เข้าใจนโยบายความมั่นคงปลอดภัยสารสนเทศของโรงแรม Katathani Collection', 'I understand the Information Security & Acceptable Use Policy of Katathani Collection') ?></p>
                                <div id="policyAgreedBadge" class="mt-2.5 hidden items-center space-x-1.5 text-xs text-emerald-400 bg-emerald-950/60 border border-emerald-500/40 px-3 py-1 rounded-lg">
                                    <i class="fa-solid fa-circle-check text-emerald-400"></i>
                                    <span><?= lang('ท่านได้อ่านและกดยินยอมรับระเบียบเรียบร้อยแล้ว', 'You have read and accepted the regulations') ?></span>
                                </div>
                            </div>
                        </label>
                    </div>

                </div>
            </div>

            <!-- Submit Button & Controls -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-200">
                <a href="index.php" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition">
                    <?= lang('&larr; ยกเลิก / กลับหน้ารายการ', '&larr; Cancel / Back to Requests') ?>
                </a>

                <button type="submit" id="submitFormBtn" class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-sky-600/25 transition transform active:scale-98 flex items-center justify-center">
                    <span><?= lang('ส่งคำขอเปิดสิทธิ์', 'Submit Request') ?></span>
                </button>
            </div>

        </form>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hotel Selection synchronization
        var otherSelect = document.getElementById('otherHotelSelect');
        var radioOther = document.getElementById('radioOtherHotel');
        var hotelRadios = document.querySelectorAll('input[name="hotel_id"]');

        if (otherSelect && radioOther) {
            otherSelect.addEventListener('change', function() {
                if (this.value) {
                    radioOther.value = this.value;
                    radioOther.checked = true;
                }
            });

            hotelRadios.forEach(function(r) {
                r.addEventListener('change', function() {
                    if (this !== radioOther) {
                        otherSelect.value = '';
                    }
                });
            });
        }

        // Searchable Employee Dropdown from ex_user
        var empWrapper = document.getElementById('empDropdownWrapper');
        var empInput = document.getElementById('employee_id');
        var empList = document.getElementById('empDropdownList');
        var empSpinner = document.getElementById('empLookupSpinner');
        var empStatus = document.getElementById('empLookupStatus');
        var empChevron = document.getElementById('empDropdownChevron');
        var empToggleBtn = document.getElementById('empDropdownToggleBtn');
        var empLookupTimeout = null;

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function openEmpDropdown() {
            if (!empList) return;
            empList.classList.remove('hidden');
            if (empChevron) empChevron.classList.add('rotate-180');
            var val = empInput ? empInput.value.trim() : '';
            fetchEmployees(val);
        }

        function closeEmpDropdown() {
            if (!empList) return;
            empList.classList.add('hidden');
            if (empChevron) empChevron.classList.remove('rotate-180');
        }

        function renderEmpDropdown(items, query) {
            if (!empList) return;

            if (!items || items.length === 0) {
                empList.innerHTML = '<div class="p-4 text-center text-xs text-slate-400">' +
                    '<i class="fa-solid fa-circle-exclamation text-slate-300 text-lg mb-1 block"></i>' +
                    <?= json_encode(lang('ไม่พบพนักงานที่ตรงกับ ', 'No employee found matching ')) ?> + (query ? '<span class="font-bold text-slate-600">"' + escapeHtml(query) + '"</span>' : <?= json_encode(lang('เงื่อนไข', 'criteria')) ?>) +
                    '</div>';
                empList.classList.remove('hidden');
                return;
            }

            var html = '<div class="px-3 py-1.5 bg-slate-50 border-b border-slate-100 text-[10px] text-slate-500 font-medium flex items-center justify-between sticky top-0">' +
                '<span><i class="fa-solid fa-users text-sky-600 mr-1"></i>' + <?= json_encode(lang('รายชื่อพนักงาน (คลิกเพื่อเลือก)', 'Employee list (Click to select)')) ?> + '</span>' +
                '<span class="text-slate-400">' + <?= json_encode(lang('พบ ', 'Found ')) ?> + items.length + <?= json_encode(lang(' รายการ', ' records')) ?> + '</span>' +
                '</div>';

            items.forEach(function(u) {
                var titleName = u.title ? escapeHtml(u.title) + ' ' : '';
                var deptInfo = u.department ? escapeHtml(u.department) : '-';
                var hotelInfo = u.hotel_name ? ' &bull; ' + escapeHtml(u.hotel_name) : '';

                html += '<div class="emp-opt px-3 py-2.5 hover:bg-sky-50 active:bg-sky-100 cursor-pointer transition flex items-center justify-between border-b border-slate-100 last:border-b-0" ' +
                    'data-empid="' + escapeHtml(u.employee_id || '') + '" ' +
                    'data-title="' + escapeHtml(u.title || '') + '" ' +
                    'data-first="' + escapeHtml(u.first_name || '') + '" ' +
                    'data-last="' + escapeHtml(u.last_name || '') + '" ' +
                    'data-fullname="' + escapeHtml(u.full_name || '') + '" ' +
                    'data-dept="' + escapeHtml(u.department || '') + '" ' +
                    'data-deptid="' + escapeHtml(u.dep_id || '') + '" ' +
                    'data-hotelid="' + escapeHtml(u.hotel_id || '') + '">' +
                    '<div class="min-w-0 pr-2">' +
                    '<div class="font-bold text-slate-800 text-xs truncate">' + titleName + escapeHtml(u.full_name) + '</div>' +
                    '<div class="text-[10px] text-slate-500 truncate mt-0.5">' + deptInfo + hotelInfo + '</div>' +
                    '</div>' +
                    '<span class="font-mono text-[11px] bg-sky-50 text-sky-700 border border-sky-200 px-2 py-0.5 rounded-md font-bold shrink-0">' + escapeHtml(u.employee_id) + '</span>' +
                    '</div>';
            });

            empList.innerHTML = html;
            empList.classList.remove('hidden');

            empList.querySelectorAll('.emp-opt').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                    selectEmployee({
                        employee_id: this.getAttribute('data-empid'),
                        title: this.getAttribute('data-title'),
                        first_name: this.getAttribute('data-first'),
                        last_name: this.getAttribute('data-last'),
                        full_name: this.getAttribute('data-fullname'),
                        department: this.getAttribute('data-dept'),
                        dep_id: this.getAttribute('data-deptid'),
                        hotel_id: parseInt(this.getAttribute('data-hotelid'), 10)
                    });
                });
            });
        }

        function selectEmployee(u) {
            if (!u) return;

            empInput.value = u.employee_id;

            if (document.getElementById('first_name')) document.getElementById('first_name').value = u.first_name || '';
            if (document.getElementById('last_name')) document.getElementById('last_name').value = u.last_name || '';

            // Match Title
            var titleSel = document.getElementById('titleSelect');
            if (titleSel && u.title) {
                for (var i = 0; i < titleSel.options.length; i++) {
                    if (titleSel.options[i].value.trim().toLowerCase() === u.title.trim().toLowerCase()) {
                        titleSel.selectedIndex = i;
                        break;
                    }
                }
            }

            // Match Department
            var deptSel = document.getElementById('departmentSelect');
            if (deptSel) {
                for (var i = 0; i < deptSel.options.length; i++) {
                    var opt = deptSel.options[i];
                    if (u.dep_id && opt.getAttribute('data-id') == u.dep_id) {
                        deptSel.selectedIndex = i;
                        break;
                    }
                    if (u.department && opt.value.trim().toLowerCase() === u.department.trim().toLowerCase()) {
                        deptSel.selectedIndex = i;
                        break;
                    }
                }
            }

            // Match Hotel
            var hotelId = parseInt(u.hotel_id, 10);
            if (hotelId === 1 || hotelId === 2 || hotelId === 5) {
                var hRadio = document.querySelector('input[name="hotel_id"][value="' + hotelId + '"]');
                if (hRadio) hRadio.checked = true;
                if (otherSelect) otherSelect.value = '';
            } else if (hotelId && otherSelect && radioOther) {
                otherSelect.value = hotelId;
                radioOther.value = hotelId;
                radioOther.checked = true;
            }



            closeEmpDropdown();
        }

        function fetchEmployees(query) {
            if (empSpinner) empSpinner.classList.remove('hidden');
            fetch('actions/search_ex_user.php?code=' + encodeURIComponent(query || ''))
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function(json) {
                    if (empSpinner) empSpinner.classList.add('hidden');
                    if (json && json.success && json.data) {
                        renderEmpDropdown(json.data, query);
                    } else {
                        renderEmpDropdown([], query);
                    }
                })
                .catch(function(err) {
                    if (empSpinner) empSpinner.classList.add('hidden');
                    console.error('Error fetching employees:', err);
                    renderEmpDropdown([], query);
                });
        }

        if (empInput && empList) {
            // Open dropdown when clicking or focusing on input
            empInput.addEventListener('focus', function() {
                this.select();
                openEmpDropdown();
            });

            empInput.addEventListener('click', function(e) {
                e.stopPropagation();
                openEmpDropdown();
            });

            // Filter on typing
            empInput.addEventListener('input', function() {
                var val = this.value.trim();
                clearTimeout(empLookupTimeout);
                empLookupTimeout = setTimeout(function() {
                    fetchEmployees(val);
                }, 200);
            });

            // Toggle button
            if (empToggleBtn) {
                empToggleBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (empList.classList.contains('hidden')) {
                        empInput.focus();
                        openEmpDropdown();
                    } else {
                        closeEmpDropdown();
                    }
                });
            }

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (empWrapper && !empWrapper.contains(e.target)) {
                    closeEmpDropdown();
                }
            });
        }

        // Policy Agreement Scroll Gatekeeper (FM-KTNC-IT-002Rev01)
        var policyModal = document.getElementById('policyModalOverlay');
        var policyContainer = document.getElementById('policyScrollContainer');
        var btnAccept = document.getElementById('btnAcceptPolicy');
        var scrollHint = document.getElementById('scrollHint');
        var policyCheck = document.getElementById('policyAgreement');
        var policyBadge = document.getElementById('policyAgreedBadge');
        var btnReview = document.getElementById('btnReviewPolicy');
        var form = document.getElementById('requestForm');
        var hasAcceptedPolicy = false;

        if (policyContainer && btnAccept) {
            policyContainer.addEventListener('scroll', function() {
                var tolerance = 35;
                if (this.scrollHeight - this.scrollTop <= this.clientHeight + tolerance) {
                    btnAccept.disabled = false;
                    btnAccept.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed');
                    btnAccept.classList.add('bg-emerald-600', 'hover:bg-emerald-500', 'text-white', 'cursor-pointer', 'shadow-lg', 'shadow-emerald-600/30');
                    if (scrollHint) {
                        scrollHint.className = 'text-xs text-emerald-800 bg-emerald-50 px-3.5 py-1.5 rounded-xl border border-emerald-200 flex items-center space-x-2';
                        scrollHint.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600"></i><span><?= lang('กรุณากดยินยอมเพื่อดำเนินการ', 'Please click Accept to proceed') ?></span>';
                    }
                }
            });

            btnAccept.addEventListener('click', function() {
                hasAcceptedPolicy = true;
                if (policyCheck) policyCheck.checked = true;
                if (policyBadge) {
                    policyBadge.classList.remove('hidden');
                    policyBadge.classList.add('inline-flex');
                }
                if (policyModal) policyModal.classList.add('hidden');
                Swal.fire({
                    icon: 'success',
                    title: <?= json_encode(lang('ยินยอมรับระเบียบเรียบร้อย', 'Regulations Accepted')) ?>,
                    text: <?= json_encode(lang('ท่านสามารถเริ่มกรอกแบบฟอร์มคำขอเปิดสิทธิ์ระบบได้แล้ว', 'You can now proceed to fill in the request form.')) ?>,
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        }

        if (btnReview && policyModal) {
            btnReview.addEventListener('click', function() {
                policyModal.classList.remove('hidden');
            });
        }

        // Form Submission Handler
        form.addEventListener('submit', function(e) {
            if (!hasAcceptedPolicy || !policyCheck.checked) {
                e.preventDefault();
                if (policyModal) policyModal.classList.remove('hidden');
                Swal.fire({
                    icon: 'warning',
                    title: <?= json_encode(lang('กรุณายอมรับเงื่อนไขก่อน', 'Please Accept Terms First')) ?>,
                    text: <?= json_encode(lang('ต้องเลื่อนอ่านระเบียบการใช้อินเตอร์เน็ตให้ครบถ้วนและกดยินยอมก่อนกรอกแบบฟอร์ม', 'You must scroll through and accept the regulations before submitting the form.')) ?>,
                    confirmButtonColor: '#0284c7'
                });
                return false;
            }
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
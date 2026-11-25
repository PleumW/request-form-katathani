<?php

/**
 * Global Header with Tailwind CSS & Navigation
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = getCurrentUser();
$flash = getFlash();

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$userLevel = (int)($currentUser['level_id'] ?? 2);
$userRole  = $currentUser ? determineRoleFromUser($currentUser) : 'guest';

$isRequester    = ($userRole === 'requester');
$isDeptHead     = ($userRole === 'dept_head');
$isHotelMgr     = ($userRole === 'hotel_manager');
$isITMgr        = ($userRole === 'it_manager');
$isITStaff      = ($userRole === 'it_staff');
$isHRStaff      = ($userRole === 'hr_staff');
$isAdmin        = ($userLevel === 99 || $userRole === 'admin');
$isHeadOrAbove  = ($isDeptHead || $isHotelMgr || $isITMgr || $userLevel >= 40);

// Count pending approvals for current user based on role (head, hotel, it, it_staff, hr_staff, admin)
$pendingCount = 0;
if ($currentUser) {
    $db = getDB();
    if ($userRole === 'dept_head') {
        $pendingCount = $db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_dept_head'")->fetchColumn();
    } elseif ($userRole === 'hotel_manager') {
        $pendingCount = $db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_hotel_manager'")->fetchColumn();
    } elseif ($userRole === 'it_manager') {
        $pendingCount = $db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_it_manager'")->fetchColumn();
    } elseif ($userRole === 'it_staff') {
        $pendingCount = $db->query("SELECT COUNT(*) FROM requests WHERE status = 'pending_it_action'")->fetchColumn();
    } elseif ($userRole === 'hr_staff') {
        $pendingCount = 0; // HR no longer has filing tasks; they export data directly
    } elseif ($userRole === 'admin' || $userLevel === 99) {
        $pendingCount = $db->query("SELECT COUNT(*) FROM requests WHERE status != 'completed' AND status != 'rejected'")->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= currentLang() ?>" class="h-full bg-slate-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            500: '#0284c7',
                            600: '#0369a1',
                            700: '#075985',
                            800: '#0c4a6e',
                            900: '#0f172a',
                        }
                    },
                    fontFamily: {
                        sans: ['Sarabun', 'Prompt', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts: Sarabun & Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Prompt', 'Sarabun', sans-serif;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
            }
        }
    </style>
</head>

<body class="flex flex-col min-h-full text-slate-800 antialiased">

    <!-- Top Navigation Bar -->
    <header class="bg-slate-700 text-slate-200 border-b border-slate-800 shadow-sm sticky top-0 z-40 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <!-- Logo & Hotel Name (Katathani Collection) -->
                <div class="flex items-center space-x-3">
                    <a href="<?= url('index.php') ?>" class="flex items-center space-x-3 group">
                        <img src="<?= url('photo/images-Photoroom.png') ?>" alt="Katathani Logo" class="h-10 w-auto object-contain max-w-[150px]" onerror="this.onerror=null; this.src='photo/images-Photoroom.png';">
                        <div>
                            <span class="font-bold text-base text-white tracking-tight leading-none block">Request Form</span>
                            <span class="text-xs text-slate-400 block mt-0.5 font-medium">Katathani Collection</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links with Sliding / Active Page Indicator -->
                <nav class="hidden md:flex items-center p-1 bg-slate-950/70 rounded-xl border border-slate-800 text-xs">

                    <?php if ($isRequester): ?>
                        <!-- Regular User (Requester): ONLY New Request & My Requests -->
                        <a href="<?= url('new_request.php') ?>"
                            class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center space-x-1.5 <?= ($currentPage === 'new_request.php') ? 'bg-sky-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span><?= lang('ยื่นคำขอใหม่', 'New Request') ?></span>
                        </a>

                        <a href="<?= url('index.php') ?>"
                            class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center space-x-1.5 <?= ($currentPage === 'index.php' || $currentPage === 'view_request.php') ? 'bg-sky-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                            <i class="fa-solid fa-list-check text-[11px]"></i>
                            <span><?= lang('รายการคำขอ', 'Requests') ?></span>
                        </a>

                    <?php else: ?>
                        <!-- Staff / Managers / Admin: Standard Management Navigation -->
                        <a href="<?= url('dashboard.php') ?>"
                            class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center space-x-1.5 <?= ($currentPage === 'dashboard.php') ? 'bg-sky-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                            <i class="fa-solid fa-chart-pie text-[11px]"></i>
                            <span>Dashboard</span>
                        </a>

                        <a href="<?= url('index.php') ?>"
                            class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center space-x-2 <?= ($currentPage === 'index.php' || $currentPage === 'view_request.php') ? 'bg-sky-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                            <i class="fa-solid fa-list-check text-[11px]"></i>
                            <span><?= lang('รายการคำขอ', 'Requests') ?></span>
                            <?php if ($pendingCount > 0): ?>
                                <span class="bg-amber-400 text-slate-950 text-[10px] px-1.5 py-0.2 rounded-full font-bold"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>

                        <?php if ($userLevel === 99 || $userLevel >= 40 || $isDeptHead || $isHotelMgr || $isITMgr || $isITStaff): // New Request accessible to Admins, Approvers, and IT Staff 
                        ?>
                            <a href="<?= url('new_request.php') ?>"
                                class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center space-x-1.5 <?= ($currentPage === 'new_request.php') ? 'bg-sky-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                                <i class="fa-solid fa-plus text-[10px]"></i>
                                <span><?= lang('ยื่นคำขอใหม่', 'New Request') ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if ($userLevel === 99): // Admin-only: Users & Settings 
                        ?>
                            <a href="<?= url('users.php') ?>"
                                class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center space-x-1.5 <?= ($currentPage === 'users.php' || $currentPage === 'edit_user.php') ? 'bg-sky-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                                <i class="fa-solid fa-users-gear text-[11px]"></i>
                                <span><?= lang('ผู้ใช้งาน', 'Users') ?></span>
                            </a>

                            <a href="<?= url('login_logs.php') ?>"
                                class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center space-x-1.5 <?= ($currentPage === 'login_logs.php') ? 'bg-sky-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                                <i class="fa-solid fa-clock-rotate-left text-[11px]"></i>
                                <span><?= lang('ประวัติเข้าสู่ระบบ', 'Login Logs') ?></span>
                            </a>

                            <a href="<?= url('settings.php') ?>"
                                class="px-3.5 py-1.5 rounded-lg font-medium transition flex items-center space-x-1.5 <?= ($currentPage === 'settings.php') ? 'bg-sky-600 text-white font-semibold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' ?>">
                                <i class="fa-solid fa-gear text-[11px]"></i>
                                <span><?= lang('แก้ไขแบบฟอร์ม', 'Settings') ?></span>
                            </a>
                        <?php endif; ?>

                    <?php endif; ?>

                </nav>

                <!-- Profile & Language Switcher Group (ด้านหลังตัวโปรไฟล์บอกชื่อ) -->
                <div class="flex items-center space-x-2.5">

                    <!-- Minimal Profile Button & Click-to-Toggle Dropdown -->
                    <div class="relative">
                        <?php if ($currentUser): ?>

                            <!-- Minimal Profile Button (แสดงแค่ชื่อ ไม่แสดงสิทธิ์/Role) -->
                            <button id="profileDropdownBtn" type="button"
                                class="flex items-center space-x-2 bg-slate-800/90 hover:bg-slate-800 px-3 py-1.5 rounded-full border border-slate-700 text-xs text-slate-200 transition focus:outline-none cursor-pointer">
                                <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center text-[11px] font-bold shadow-sm">
                                    <?= mb_substr($currentUser['full_name'], 0, 1) ?>
                                </div>
                                <span class="font-medium text-slate-200 text-xs hidden sm:inline-block max-w-[150px] truncate">
                                    <?= htmlspecialchars($currentUser['full_name']) ?>
                                </span>
                                <i class="fa-solid fa-chevron-down text-[9px] text-slate-400 ml-0.5 transition-transform duration-200" id="dropdownChevron"></i>
                            </button>

                            <!-- Clean Minimal Dropdown Menu (แสดงเฉพาะข้อมูลผู้ใช้และออกจากระบบ) -->
                            <div id="profileDropdownMenu"
                                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-50 text-slate-800 text-xs transition duration-150 transform origin-top-right">

                                <!-- User Info Header (แสดงเฉพาะชื่อและโรงแรม) -->
                                <div class="px-3.5 py-2.5 border-b border-slate-100 bg-slate-50/70">
                                    <div class="font-bold text-slate-800 truncate"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                                    <div class="text-[10px] text-slate-500 truncate mt-0.5"><?= htmlspecialchars($currentUser['hotel_name'] ?? 'Katathani') ?></div>
                                </div>

                                <a href="<?= url('logout.php') ?>" class="flex items-center px-3.5 py-2 text-rose-600 hover:bg-rose-50 text-xs font-medium transition">
                                    <i class="fa-solid fa-right-from-bracket mr-2 text-[11px]"></i>
                                    <span><?= lang('ออกจากระบบ', 'Sign Out') ?></span>
                                </a>
                            </div>

                        <?php else: ?>
                            <div class="flex items-center space-x-2">
                                <a href="<?= url('login.php') ?>" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-semibold transition border border-slate-700">
                                    <?= lang('เข้าสู่ระบบ', 'Sign In') ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Language Switcher Button (TH | EN) อยู่ตรง header ด้านหลังตัวโปรไฟล์บอกชื่อ -->
                    <div class="flex items-center bg-slate-900/90 p-0.5 rounded-full border border-slate-700 text-xs shadow-inner" title="<?= lang('เปลี่ยนภาษา', 'Switch Language') ?>">
                        <a href="<?= langUrl('th') ?>"
                            class="px-2.5 py-1 rounded-full font-bold transition flex items-center space-x-1 <?= isTh() ? 'bg-sky-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-200' ?>"
                            title="ภาษาไทย">
                            <span class="text-[11px] leading-none">TH</span>
                        </a>
                        <a href="<?= langUrl('en') ?>"
                            class="px-2.5 py-1 rounded-full font-bold transition flex items-center space-x-1 <?= isEn() ? 'bg-sky-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-200' ?>"
                            title="English">
                            <span class="text-[11px] leading-none">EN</span>
                        </a>
                    </div>

                </div>

            </div>
        </div>

        <!-- Mobile Navigation Strip -->
        <div class="md:hidden border-t border-slate-800 bg-slate-900/95 px-4 py-2 flex items-center justify-around text-xs">
            <?php if ($isRequester): ?>
                <a href="<?= url('new_request.php') ?>" class="<?= ($currentPage === 'new_request.php') ? 'text-sky-400 font-bold' : 'text-slate-300' ?> flex flex-col items-center">
                    <i class="fa-solid fa-circle-plus mb-1"></i>
                    <span><?= lang('ยื่นคำขอใหม่', 'New Request') ?></span>
                </a>
                <a href="<?= url('index.php') ?>" class="<?= ($currentPage === 'index.php') ? 'text-sky-400 font-bold' : 'text-slate-300' ?> flex flex-col items-center">
                    <i class="fa-solid fa-list-check mb-1"></i>
                    <span><?= lang('รายการคำขอ', 'Requests') ?></span>
                </a>
            <?php else: ?>
                <a href="<?= url('dashboard.php') ?>" class="<?= ($currentPage === 'dashboard.php') ? 'text-sky-400 font-bold' : 'text-slate-300' ?> flex flex-col items-center">
                    <i class="fa-solid fa-chart-pie mb-1"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= url('index.php') ?>" class="<?= ($currentPage === 'index.php') ? 'text-sky-400 font-bold' : 'text-slate-300' ?> flex flex-col items-center">
                    <i class="fa-solid fa-list-check mb-1"></i>
                    <span><?= lang('รายการคำขอ', 'Requests') ?></span>
                </a>
                <?php if ($userLevel === 99 || $userLevel >= 40 || $isDeptHead || $isHotelMgr || $isITMgr || $isITStaff): ?>
                    <a href="<?= url('new_request.php') ?>" class="<?= ($currentPage === 'new_request.php') ? 'text-sky-400 font-bold' : 'text-slate-300' ?> flex flex-col items-center">
                        <i class="fa-solid fa-circle-plus mb-1"></i>
                        <span><?= lang('ยื่นคำขอ', 'New Request') ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($userLevel === 99): ?>
                    <a href="<?= url('users.php') ?>" class="<?= ($currentPage === 'users.php' || $currentPage === 'edit_user.php') ? 'text-sky-400 font-bold' : 'text-slate-300' ?> flex flex-col items-center">
                        <i class="fa-solid fa-users mb-1"></i>
                        <span><?= lang('ผู้ใช้งาน', 'Users') ?></span>
                    </a>
                    <a href="<?= url('login_logs.php') ?>" class="<?= ($currentPage === 'login_logs.php') ? 'text-sky-400 font-bold' : 'text-slate-300' ?> flex flex-col items-center">
                        <i class="fa-solid fa-clock-rotate-left mb-1"></i>
                        <span>Logs</span>
                    </a>
                    <a href="<?= url('settings.php') ?>" class="<?= ($currentPage === 'settings.php') ? 'text-sky-400 font-bold' : 'text-slate-300' ?> flex flex-col items-center">
                        <i class="fa-solid fa-gear mb-1"></i>
                        <span><?= lang('ตั้งค่า', 'Settings') ?></span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </header>

    <!-- Global Flash Notification Banner -->
    <?php if ($flash): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 no-print">
            <?php
            $bg = match ($flash['type']) {
                'success' => 'bg-emerald-50 border-emerald-300 text-emerald-800',
                'error' => 'bg-rose-50 border-rose-300 text-rose-800',
                'warning' => 'bg-amber-50 border-amber-300 text-amber-800',
                default => 'bg-sky-50 border-sky-300 text-sky-800'
            };
            $icon = match ($flash['type']) {
                'success' => 'fa-circle-check text-emerald-500',
                'error' => 'fa-circle-xmark text-rose-500',
                'warning' => 'fa-triangle-exclamation text-amber-500',
                default => 'fa-circle-info text-sky-500'
            };
            ?>
            <div class="flex items-center justify-between p-4 rounded-xl border <?= $bg ?> shadow-sm">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid <?= $icon ?> text-xl"></i>
                    <span class="text-sm font-medium"><?= htmlspecialchars($flash['message']) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <script>
            // Click-to-toggle Dropdown with Click-Outside listener (Eliminates premature closing completely)
            document.addEventListener('DOMContentLoaded', function() {
                var btn = document.getElementById('profileDropdownBtn');
                var menu = document.getElementById('profileDropdownMenu');
                var chevron = document.getElementById('dropdownChevron');

                if (btn && menu) {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        var isHidden = menu.classList.contains('hidden');
                        if (isHidden) {
                            menu.classList.remove('hidden');
                            if (chevron) chevron.style.transform = 'rotate(180deg)';
                        } else {
                            menu.classList.add('hidden');
                            if (chevron) chevron.style.transform = 'rotate(0deg)';
                        }
                    });

                    // Prevent closing when clicking inside the menu
                    menu.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });

                    // Close when clicking anywhere outside
                    document.addEventListener('click', function() {
                        if (!menu.classList.contains('hidden')) {
                            menu.classList.add('hidden');
                            if (chevron) chevron.style.transform = 'rotate(0deg)';
                        }
                    });
                }
            });
        </script>
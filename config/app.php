<?php

/**
 * Application Constants and System Matrix Definition
 * Based on Document Code: FM-KTNC-IT-003 Rev.00
 */

// Session is started securely in config/database.php
require_once __DIR__ . '/lang.php';

// Set application timezone to Thailand
date_default_timezone_set('Asia/Bangkok');

define('APP_NAME', 'IT System Access Request');
define('APP_DOC_CODE', 'FM-KTNC-IT-003 Rev.00');
define('APP_DOC_REVISED', '-');
define('APP_DOC_EFFECTIVE_DATE', '13.04.2022');

// Determine base URL dynamically
function getBaseUrl()
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    $protocol = $isHttps ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    // Clean slashes and current-dir dots
    $scriptDir = trim($scriptDir, '/.');
    // If inside a subfolder like 'actions' or 'config', navigate up
    if (basename($scriptDir) === 'actions' || basename($scriptDir) === 'config') {
        $scriptDir = dirname($scriptDir);
        $scriptDir = trim($scriptDir, '/.');
    }
    return $protocol . $host . ($scriptDir !== '' ? '/' . $scriptDir : '');
}

function url($path = '')
{
    $base = getBaseUrl();
    $path = ltrim($path, '/');
    return $base . '/' . $path;
}

function redirect($path)
{
    header("Location: " . url($path));
    exit;
}

function setFlash($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function getFlash()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Calculate pending duration info for a request
 * Returns array with:
 * - is_pending: boolean (whether status is awaiting action)
 * - is_today: boolean (submitted today)
 * - days: int (number of full days pending)
 * - text: string ('ส่งวันนี้', 'ค้าง 1 วัน', 'ค้าง X วัน')
 * - badge_class: string
 * - text_class: string
 */
function getPendingDurationInfo($createdAt, $status)
{
    $pendingStatuses = ['pending_dept_head', 'pending_hotel_manager', 'pending_it_action', 'pending_it_manager', 'pending_hr'];
    $isPending = in_array($status, $pendingStatuses);

    $createdTimestamp = strtotime($createdAt);
    $createdDateStr = date('Y-m-d', $createdTimestamp);
    $todayDateStr = date('Y-m-d');

    $isToday = ($createdDateStr === $todayDateStr);
    $diffDays = 0;

    if (!$isToday) {
        $createdDate = new DateTime($createdDateStr);
        $todayDate = new DateTime($todayDateStr);
        if ($createdDate > $todayDate) {
            // Future or timezone offset
            $isToday = true;
            $diffDays = 0;
        } else {
            $diff = $todayDate->diff($createdDate);
            $diffDays = (int)$diff->days;
            if ($diffDays <= 0) {
                $isToday = true;
            }
        }
    }

    if ($isToday) {
        $text = lang('ส่งวันนี้', 'Submitted Today');
        $badgeClass = 'text-sky-700 bg-sky-50 border-sky-200';
        $textClass = 'text-sky-700';
    } else {
        $text = lang('ค้าง ' . $diffDays . ' วัน', 'Pending ' . $diffDays . ($diffDays === 1 ? ' day' : ' days'));
        if ($diffDays >= 5) {
            $badgeClass = 'text-rose-600 bg-rose-50 border-rose-200';
            $textClass = 'text-rose-600';
        } elseif ($diffDays >= 2) {
            $badgeClass = 'text-amber-600 bg-amber-50 border-amber-200';
            $textClass = 'text-amber-600';
        } else {
            $badgeClass = 'text-slate-600 bg-slate-50 border-slate-200';
            $textClass = 'text-slate-600';
        }
    }

    return [
        'is_pending' => $isPending,
        'is_today' => $isToday,
        'days' => $diffDays,
        'text' => $text,
        'badge_class' => $badgeClass,
        'text_class' => $textClass
    ];
}

require_once __DIR__ . '/database.php';

// Master Data: Query directly from ex_property, ex_hotel, ex_level
function getExProperties()
{
    static $properties = null;
    if ($properties === null) {
        $db = getDB();
        $properties = $db->query("SELECT * FROM ex_property ORDER BY property_id ASC")->fetchAll();
    }
    return $properties;
}

function getExHotels($propertyId = null)
{
    $db = getDB();
    if ($propertyId) {
        $stmt = $db->prepare("SELECT h.*, p.property_name FROM ex_hotel h LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id WHERE h.hotel_property_id = ? ORDER BY h.hotel_id ASC");
        $stmt->execute([$propertyId]);
        return $stmt->fetchAll();
    }
    return $db->query("SELECT h.*, p.property_name FROM ex_hotel h LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id ORDER BY h.hotel_property_id ASC, h.hotel_id ASC")->fetchAll();
}

function getExLevels()
{
    static $levels = null;
    if ($levels === null) {
        $db = getDB();
        $levels = $db->query("SELECT * FROM ex_level ORDER BY level_id ASC")->fetchAll();
    }
    return $levels;
}

function getExDepartments()
{
    static $departments = null;
    if ($departments === null) {
        $db = getDB();
        $departments = $db->query("SELECT dep_id, dep_name, dep_name_s FROM ex_department WHERE dep_status = 1 ORDER BY dep_name ASC")->fetchAll();
    }
    return $departments;
}

function getExTitles()
{
    static $titles = null;
    if ($titles === null) {
        $db = getDB();
        $titles = $db->query("SELECT title_id, title_name FROM ex_title ORDER BY title_id ASC")->fetchAll();
    }
    return $titles;
}

function getHotelById($hotelId)
{
    $db = getDB();
    $stmt = $db->prepare("SELECT h.*, p.property_name FROM ex_hotel h LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id WHERE h.hotel_id = ?");
    $stmt->execute([$hotelId]);
    return $stmt->fetch();
}

function getLevelById($levelId)
{
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM ex_level WHERE level_id = ?");
    $stmt->execute([$levelId]);
    return $stmt->fetch();
}

// Available Departments
$DEPARTMENTS = [
    'Front Office',
    'Housekeeping',
    'F/B' => 'Food & Beverage (F/B)',
    'Kitchen',
    'Engineering',
    'Sales & Marketing',
    'Accounting & Finance',
    'Human Resources',
    'Information Technology',
    'Purchasing & Store',
    'Security',
    'Spa & Recreation',
    'Executive Office'
];

// System Statuses & Labels (Workflow: Head -> Hotel -> IT Staff -> IT Manager -> Completed)
$STATUS_LIST = [
    'pending_dept_head' => [
        'label' => 'Pending Dept Head',
        'th_label' => 'รอหัวหน้าแผนก',
        'color' => 'bg-amber-100 text-amber-800 border-amber-300',
        'badge' => 'amber',
        'step' => 1
    ],
    'pending_hotel_manager' => [
        'label' => 'Pending Hotel Manager',
        'th_label' => 'รอผู้จัดการโรงแรม',
        'color' => 'bg-orange-100 text-orange-800 border-orange-300',
        'badge' => 'orange',
        'step' => 2
    ],
    'pending_it_action' => [
        'label' => 'Pending IT Staff',
        'th_label' => 'รอ IT ดำเนินการ',
        'color' => 'bg-blue-100 text-blue-800 border-blue-300',
        'badge' => 'blue',
        'step' => 3
    ],
    'pending_it_manager' => [
        'label' => 'Pending IT Manager',
        'th_label' => 'รอผู้จัดการไอที',
        'color' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
        'badge' => 'indigo',
        'step' => 4
    ],
    'completed' => [
        'label' => 'Completed',
        'th_label' => 'เสร็จสิ้น',
        'color' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'badge' => 'emerald',
        'step' => 5
    ],
    'pending_hr' => [
        'label' => 'IT Setup Done (Awaiting HR Filing)',
        'th_label' => 'IT สร้างบัญชีแล้ว (รอ HR จัดเก็บ)',
        'color' => 'bg-purple-100 text-purple-800 border-purple-300',
        'badge' => 'purple',
        'step' => 6
    ],
    'rejected' => [
        'label' => 'Rejected',
        'th_label' => 'ไม่อนุมัติ / ปฏิเสธคำขอ',
        'color' => 'bg-rose-100 text-rose-800 border-rose-300',
        'badge' => 'rose',
        'step' => 0
    ]
];

// Full System Access Matrix Definition (Exact match with Form FM-KTNC-IT-003 Rev.00)
function getSystemMatrixDefinition()
{
    return [
        'left_column' => [
            'hotel_network' => [
                'title' => 'Hotel Network',
                'icon' => 'fa-solid fa-network-wired',
                'type' => 'mixed',
                'items' => [
                    ['id' => 'win_login', 'label' => 'Windows User Login', 'type' => 'checkbox'],
                    ['id' => 'usb_allow', 'label' => 'USB Allow', 'type' => 'checkbox'],
                    ['id' => 'file_server', 'label' => 'File Server', 'type' => 'text_input', 'sublabel' => 'Network Drive', 'placeholder' => 'เช่น Drive Z: / Shared Folder'],
                    ['id' => 'net_allow_all', 'label' => 'Internet allow all website', 'type' => 'checkbox'],
                    ['id' => 'net_block_some', 'label' => 'Internet block some website', 'type' => 'checkbox'],
                    ['id' => 'email', 'label' => 'E-Mail', 'type' => 'text_input', 'placeholder' => 'เช่น name.surname@katathani.com']
                ]
            ],
            'katathani_app' => [
                'title' => 'Katathani Application & Tool',
                'icon' => 'fa-solid fa-shapes',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'agent_systems', 'label' => 'Agent Systems', 'type' => 'checkbox'],
                    ['id' => 'member_systems', 'label' => 'Member Systems', 'type' => 'checkbox'],
                    ['id' => 'customer_systems', 'label' => 'Customer Systems', 'type' => 'checkbox']
                ]
            ],
            'opera_pms' => [
                'title' => 'Opera - Property Management System',
                'icon' => 'fa-solid fa-hotel',
                'type' => 'opera',
                'properties' => ['Katathani', 'The Sands', 'Riverie'],
                'items' => [
                    ['id' => 'engineer', 'label' => 'Engineer', 'type' => 'checkbox'],
                    ['id' => 'sales_catering', 'label' => 'Sales & Catering', 'type' => 'checkbox'],
                    ['id' => 'enquiry_executive', 'label' => 'Enquiry Only (Executive)', 'type' => 'checkbox'],
                    ['id' => 'housekeeping', 'label' => 'Housekeeping', 'type' => 'checkbox'],
                    ['id' => 'guest_service_agents', 'label' => 'Guest Service Agents', 'type' => 'checkbox'],
                    ['id' => 'assistant_managers', 'label' => 'Assistant Managers', 'type' => 'checkbox'],
                    ['id' => 'front_office_manager', 'label' => 'Front Office Manager', 'type' => 'checkbox'],
                    ['id' => 'reservation_manager', 'label' => 'Reservation Manager', 'type' => 'checkbox'],
                    ['id' => 'night_audit_manager', 'label' => 'Night Audit / Night Manager', 'type' => 'checkbox'],
                    ['id' => 'accounting', 'label' => 'Accounting', 'type' => 'checkbox'],
                    ['id' => 'operator', 'label' => 'Operator', 'type' => 'checkbox'],
                    ['id' => 'vision_opera', 'label' => 'Vision Opera', 'type' => 'checkbox']
                ]
            ],
            'check_scm' => [
                'title' => 'Check SCM - Inventory/Purchasing',
                'icon' => 'fa-solid fa-boxes-stacked',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'enquiry_only', 'label' => 'Enquiry Only', 'type' => 'checkbox'],
                    ['id' => 'purchasing', 'label' => 'Purchasing', 'type' => 'checkbox'],
                    ['id' => 'receiving', 'label' => 'Receiving', 'type' => 'checkbox'],
                    ['id' => 'stores', 'label' => 'Stores', 'type' => 'checkbox'],
                    ['id' => 'cost_supervisor', 'label' => 'Cost Supervisor', 'type' => 'checkbox']
                ]
            ],
            'emo' => [
                'title' => 'EMO - Engineer Maintenance Order',
                'icon' => 'fa-solid fa-screwdriver-wrench',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'order_request', 'label' => 'Order Request', 'type' => 'checkbox'],
                    ['id' => 'order_manage', 'label' => 'Order Manage', 'type' => 'checkbox']
                ]
            ],
            'vpn' => [
                'title' => 'VPN - FortiClient Remote Access',
                'icon' => 'fa-solid fa-lock',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'outside_remote', 'label' => 'Request Outside Remote Access', 'type' => 'checkbox']
                ]
            ]
        ],
        'right_column' => [
            'adell' => [
                'title' => 'ADELL - Key Card System',
                'icon' => 'fa-solid fa-id-card',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'key_card_mgmt', 'label' => 'Key Card User Management Login', 'type' => 'checkbox']
                ]
            ],
            'micros' => [
                'title' => 'Micros - Point of Sales System',
                'icon' => 'fa-solid fa-cash-register',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'waiters_order_taker', 'label' => 'Waiters/Waitress/Order Taker', 'type' => 'checkbox'],
                    ['id' => 'cashier_captain', 'label' => 'Cashier/Captain', 'type' => 'checkbox'],
                    ['id' => 'night_audit', 'label' => 'Night Audit', 'type' => 'checkbox']
                ]
            ],
            'eagle' => [
                'title' => 'Eagle - HRMS & Payroll System',
                'icon' => 'fa-solid fa-users-gear',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'time_attendance', 'label' => 'Time Attendance', 'type' => 'checkbox'],
                    ['id' => 'personnel_office', 'label' => 'Personnel Office', 'type' => 'checkbox'],
                    ['id' => 'personnel_manager', 'label' => 'Personnel Manager', 'type' => 'checkbox'],
                    ['id' => 'paymaster', 'label' => 'Paymaster', 'type' => 'checkbox']
                ]
            ],
            'sun' => [
                'title' => 'Sun - Financial System',
                'icon' => 'fa-solid fa-chart-line',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'account_payable', 'label' => 'Account Payable', 'type' => 'checkbox'],
                    ['id' => 'account_receivable', 'label' => 'Account Receivable', 'type' => 'checkbox'],
                    ['id' => 'asset_supervisor', 'label' => 'Asset Supervisor', 'type' => 'checkbox'],
                    ['id' => 'cost_controller', 'label' => 'Cost Controller', 'type' => 'checkbox'],
                    ['id' => 'general_cashier', 'label' => 'General Cashier', 'type' => 'checkbox'],
                    ['id' => 'income_auditor', 'label' => 'Income Auditor', 'type' => 'checkbox'],
                    ['id' => 'chief_accountant', 'label' => 'Chief Accountant / Assistant Chief Accountant', 'type' => 'checkbox'],
                    ['id' => 'financial_controller', 'label' => 'Chief Financial Controller / Financial Controller', 'type' => 'checkbox'],
                    ['id' => 'vision_sun', 'label' => 'Vision Sun', 'type' => 'checkbox'],
                    ['id' => 'sun_remote', 'label' => 'Sun Remote', 'type' => 'checkbox']
                ]
            ],
            'antlap' => [
                'title' => 'Antlap - Internet Gateway',
                'icon' => 'fa-solid fa-globe',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'view_user', 'label' => 'View User', 'type' => 'checkbox']
                ]
            ],
            'well_fixed_asset' => [
                'title' => 'Well Fixed asset - Asset System',
                'icon' => 'fa-solid fa-cubes',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'asset_supervisor', 'label' => 'Asset Supervisor', 'type' => 'checkbox']
                ]
            ],
            'bs_minimart' => [
                'title' => 'BS Minimart - POS minimart',
                'icon' => 'fa-solid fa-store',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'cashier', 'label' => 'Cashier', 'type' => 'checkbox'],
                    ['id' => 'back_office', 'label' => 'Back office', 'type' => 'checkbox']
                ]
            ],
            'fcs' => [
                'title' => 'FCS - Telephone Billing System',
                'icon' => 'fa-solid fa-phone',
                'type' => 'checkboxes',
                'items' => [
                    ['id' => 'user_view', 'label' => 'User view', 'type' => 'checkbox']
                ]
            ],
            'cctv' => [
                'title' => 'CCTV',
                'icon' => 'fa-solid fa-video',
                'type' => 'mixed',
                'items' => [
                    ['id' => 'user_monitoring', 'label' => 'User Monitoring Systems', 'type' => 'text_input', 'placeholder' => 'ระบุโซน / หมายเลขกล้อง หรือ แผนกที่ต้องการ']
                ]
            ]
        ]
    ];
}

/**
 * Settings File Helper (Config JSON storage)
 */
function getSettingsFilePath()
{
    return __DIR__ . '/settings.json';
}

function getAllAppSettings()
{
    static $cached = null;
    $filePath = getSettingsFilePath();
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return [];
}

function getAppSetting($key, $default = null)
{
    $settings = getAllAppSettings();
    return $settings[$key] ?? $default;
}

function saveAppSetting($key, $value)
{
    $filePath = getSettingsFilePath();
    $settings = getAllAppSettings();
    $settings[$key] = $value;
    return (bool)file_put_contents($filePath, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * Get active system matrix definition (customized from config JSON or default)
 */
function getActiveSystemMatrixDefinition()
{
    try {
        $custom = getAppSetting('form_matrix_custom');
        if (!empty($custom)) {
            if (is_array($custom) && (!empty($custom['left_column']) || !empty($custom['right_column']))) {
                return $custom;
            }
            if (is_string($custom)) {
                $decoded = json_decode($custom, true);
                if (is_array($decoded) && (!empty($decoded['left_column']) || !empty($decoded['right_column']))) {
                    return $decoded;
                }
            }
        }
    } catch (Exception $e) {
        // Fallback to default
    }
    return getSystemMatrixDefinition();
}

/**
 * Save customized system matrix definition to config JSON file
 */
function saveSystemMatrixDefinition($matrix)
{
    return saveAppSetting('form_matrix_custom', $matrix);
}

/**
 * Reset system matrix definition back to system default in config JSON file
 */
function resetSystemMatrixDefinition()
{
    $filePath = getSettingsFilePath();
    $settings = getAllAppSettings();
    unset($settings['form_matrix_custom']);
    return (bool)file_put_contents($filePath, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

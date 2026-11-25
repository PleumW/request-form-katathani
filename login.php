<?php

/**
 * User Login Page
 * Secure Authentication using users table with Mockup Testing Credentials
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to index
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

// Basic Brute-Force Rate Limiting (Lockout for 60 seconds after 5 consecutive failed attempts)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_login_attempt'] = time();
}

$isLocked = false;
if ($_SESSION['login_attempts'] >= 5) {
    $timeSinceLast = time() - ($_SESSION['last_login_attempt'] ?? 0);
    if ($timeSinceLast < 60) {
        $isLocked = true;
        $remaining = 60 - $timeSinceLast;
        $error = "ระบบถูกระงับชั่วคราวเนื่องจากเข้าสู่ระบบผิดพลาดเกิน 5 ครั้ง กรุณารออีก {$remaining} วินาที";
    } else {
        // Reset after lockout expiration
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    requireCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่านให้ครบถ้วน';
    } else {
        if (loginUserByUsername($username, $password)) {
            // Reset attempts on successful login
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_login_attempt']);
            redirect('index.php');
        } else {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['last_login_attempt'] = time();
            $attemptsLeft = max(0, 5 - $_SESSION['login_attempts']);
            if ($attemptsLeft > 0) {
                $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง (เหลือโอกาสอีก {$attemptsLeft} ครั้ง)";
            } else {
                $error = "เข้าสู่ระบบผิดพลาดเกิน 5 ครั้ง ระบบถูกระงับชั่วคราวเป็นเวลา 60 วินาที";
            }
        }
    }
}

// Mockup demo accounts for testing all workflow stages (Linked with ex_login & ex_user)
$mockGroups = [
    [
        'category' => lang('ผู้อนุมัติทั้ง 3 แผนก', '3 Approver Levels'),
        'desc' => lang('บัญชีสำหรับทดสอบขั้นตอนการลงนามอนุมัติคำขอ', 'Accounts for testing the approval workflow'),
        'items' => [
            [
                'role' => 'dept_head',
                'badge' => lang('ผู้อนุมัติขั้น 1', 'Approver 1'),
                'badge_color' => 'bg-sky-100 text-sky-800 border-sky-200',
                'title' => lang('หัวหน้าแผนก', 'Department Head'),
                'username' => 'depthead',
                'user_code' => '25-2156',
                'password' => 'password123',
                'name' => 'Atisak Limpananurak',
                'info' => 'Level 40 &bull; Head (แผนกบัญชี AC) &bull; Code: 25-2156'
            ],
            [
                'role' => 'hotel_manager',
                'badge' => lang('ผู้อนุมัติขั้น 2', 'Approver 2'),
                'badge_color' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                'title' => lang('ผู้จัดการโรงแรม', 'Hotel Manager'),
                'username' => 'hotelmgr',
                'user_code' => '65-23',
                'password' => 'password123',
                'name' => 'Onpailin Petsang',
                'info' => 'Level 60 &bull; Hotel (ผู้จัดการโรงแรม / HM) &bull; Code: 65-23'
            ],
            [
                'role' => 'it_manager',
                'badge' => lang('ผู้อนุมัติขั้น 3', 'Approver 3'),
                'badge_color' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                'title' => lang('ผู้จัดการฝ่ายไอที', 'IT Manager'),
                'username' => 'itmgr',
                'user_code' => '326-3325',
                'password' => 'password123',
                'name' => 'Usa Tantipongviwat',
                'info' => 'Level 45 &bull; IT (ฝ่ายเทคโนโลยีสารสนเทศ) &bull; Code: 326-3325'
            ]
        ]
    ],
    [
        'category' => lang('ผู้ยื่นคำขอและฝ่ายปฏิบัติการ', 'Operations & Admin'),
        'desc' => lang('บัญชีสำหรับยื่นคำขอและดำเนินการเปิดระบบ', 'Accounts for submitting requests and provisioning'),
        'items' => [
            [
                'role' => 'requester',
                'badge' => lang('ผู้ยื่นคำขอ', 'Requester'),
                'badge_color' => 'bg-amber-100 text-amber-800 border-amber-200',
                'title' => lang('พนักงานทั่วไป', 'Requester'),
                'username' => 'requester',
                'user_code' => '3255-2693',
                'password' => 'password123',
                'name' => 'Auttaporn Najit',
                'info' => 'Level 2 &bull; แผนกต้อนรับ (Front Office) &bull; Code: 3255-2693'
            ],
            [
                'role' => 'it_staff',
                'badge' => lang('เปิดระบบ IT', 'IT Action'),
                'badge_color' => 'bg-blue-100 text-blue-800 border-blue-200',
                'title' => lang('เจ้าหน้าที่ไอที', 'IT Staff'),
                'username' => 'itstaff',
                'user_code' => '3316-70385',
                'password' => 'password123',
                'name' => 'Parattakorn Yootung',
                'info' => 'Level 20 &bull; สร้างบัญชีและเปิดสิทธิ์ระบบ &bull; Code: 3316-70385'
            ],
            [
                'role' => 'hr_staff',
                'badge' => lang('ฝ่ายบุคคล HR', 'HR Staff'),
                'badge_color' => 'bg-purple-100 text-purple-800 border-purple-200',
                'title' => lang('เจ้าหน้าที่บุคคล', 'HR Staff'),
                'username' => 'hrstaff',
                'user_code' => '45-148',
                'password' => 'password123',
                'name' => 'Chalermwut Thaisai',
                'info' => 'Level 20 &bull; ตรวจสอบและ Export ข้อมูล &bull; Code: 45-148'
            ],
            [
                'role' => 'admin',
                'badge' => lang('ผู้ดูแลระบบ', 'Admin'),
                'badge_color' => 'bg-rose-100 text-rose-800 border-rose-200',
                'title' => lang('ผู้ดูแลระบบ', 'Administrator'),
                'username' => 'admin',
                'user_code' => '6-9938',
                'password' => 'password123',
                'name' => 'Administrator super user',
                'info' => 'Level 99 &bull; สิทธิ์สูงสุด / จัดการแบบฟอร์ม &bull; Code: 6-9938'
            ]
        ]
    ]
];

$pageTitle = lang('เข้าสู่ระบบ', 'Sign In') . ' - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6">
    <div class="text-center mb-8">
        <div class="inline-flex w-16 h-16  items-center justify-center text-white text-3xl mb-3">
            <img src="<?= url('photo/images-Photoroom.png') ?>" alt="Katathani Logo" class="h-15 w-auto object-contain max-w-[150px]" onerror="this.onerror=null; this.src='photo/images-Photoroom.png';">
        </div>
        <h1 class="text-2xl font-bold text-slate-800"><?= lang('เข้าสู่ระบบ Request Form', 'Sign In to Request Form') ?></h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-1"><?= lang('แบบฟอร์มขอเปิดสิทธิ์ใช้งานระบบสารสนเทศ Katathani Collection', 'Katathani Collection IT Access Request Form') ?></p>
    </div>

    <?php if ($error): ?>
        <div class="max-w-md mx-auto mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm flex items-center space-x-3 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- Standard Secure Login Form (Left Column) -->
        <div class="lg:col-span-5 bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm sticky top-6">
            <h2 class="text-sm font-bold text-slate-800 mb-5 pb-3 border-b border-slate-100 flex items-center justify-center space-x-2">
                <span><?= lang('กรอกข้อมูลเพื่อลงชื่อเข้าใช้งาน', 'Enter credentials to sign in') ?></span>
            </h2>

            <form id="loginForm" method="POST" action="login.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5"><?= lang('ชื่อผู้ใช้งาน', 'Username') ?></label>
                    <div class="relative">
                        <i class="fa-regular fa-user absolute left-3.5 top-3 text-slate-400 text-sm"></i>
                        <input type="text" name="username" id="loginUsername" required autocomplete="username" placeholder="<?= lang('ชื่อผู้ใช้งาน', 'Username') ?>"
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5"><?= lang('รหัสผ่าน', 'Password') ?></label>
                    <div class="relative">
                        <i class="fa-solid fa-key absolute left-3.5 top-3 text-slate-400 text-sm"></i>
                        <input type="password" name="password" id="loginPassword" required autocomplete="current-password" placeholder="<?= lang('ระบุรหัสผ่าน', 'Enter password') ?>"
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:bg-white transition">
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="w-full py-2.5 px-4 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-sm font-bold shadow-md shadow-sky-600/20 transition flex items-center justify-center space-x-2">
                    <span><?= lang('เข้าสู่ระบบ', 'Sign In') ?></span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </form>
        </div>

        <!-- Mockup Testing Accounts (Right Column) -->
        <div class="lg:col-span-7 bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-5 border-b border-slate-100 gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 flex items-center space-x-2">
                        <i class="fa-solid fa-vial text-amber-500 text-base"></i>
                        <span><?= lang('ข้อมูลสำหรับทดสอบเข้าสู่ระบบ', 'Demo Testing Accounts') ?></span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5"><?= lang('คลิก "กรอกข้อมูล" หรือ "เข้าสู่ระบบทันที" เพื่อทดสอบการทำงานของแต่ละบทบาท', 'Click "Fill Credentials" or "Instant Login" to test each role') ?></p>
                </div>
                <span class="text-[11px] bg-slate-100 text-slate-600 px-2.5 py-1 rounded-lg font-mono font-medium self-start sm:self-auto shrink-0">
                    PW: <span class="text-sky-700 font-bold">password123</span>
                </span>
            </div>

            <div class="space-y-6">
                <?php foreach ($mockGroups as $grp): ?>
                    <div>
                        <div class="mb-3">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                                <span><?= $grp['category'] ?></span>
                            </h3>
                            <p class="text-[11px] text-slate-400"><?= $grp['desc'] ?></p>
                        </div>

                        <div class="grid grid-cols-1 gap-2.5">
                            <?php foreach ($grp['items'] as $item): ?>
                                <div class="p-3 rounded-xl border border-slate-200 hover:border-sky-300 hover:bg-slate-50/50 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <span class="font-bold text-slate-800"><?= $item['title'] ?></span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-md font-semibold border <?= $item['badge_color'] ?>"><?= $item['badge'] ?></span>
                                        </div>
                                        <div class="text-slate-600 font-medium truncate"><?= $item['name'] ?></div>
                                        <div class="text-[11px] text-slate-400 mt-0.5 flex items-center space-x-2">
                                            <span>User: <code class="font-mono font-bold text-slate-700 bg-slate-100 px-1 py-0.2 rounded"><?= $item['username'] ?></code></span>
                                            <span>&bull;</span>
                                            <span><?= $item['info'] ?></span>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-1.5 shrink-0 self-end sm:self-center">
                                        <button type="button" onclick="fillLoginForm('<?= $item['username'] ?>', '<?= $item['password'] ?>', false)"
                                            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold transition flex items-center space-x-1">
                                            <i class="fa-regular fa-pen-to-square text-[10px]"></i>
                                            <span><?= lang('กรอกข้อมูล', 'Fill') ?></span>
                                        </button>
                                        <button type="button" onclick="fillLoginForm('<?= $item['username'] ?>', '<?= $item['password'] ?>', true)"
                                            class="px-3 py-1.5 bg-sky-600 hover:bg-sky-500 text-white rounded-lg text-[11px] font-bold shadow-sm transition flex items-center space-x-1">
                                            <i class="fa-solid fa-right-to-bracket text-[10px]"></i>
                                            <span><?= lang('เข้าสู่ระบบทันที', 'Login') ?></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
    function fillLoginForm(username, password, autoSubmit) {
        var uInput = document.getElementById('loginUsername');
        var pInput = document.getElementById('loginPassword');
        var form = document.getElementById('loginForm');

        if (uInput && pInput) {
            uInput.value = username;
            pInput.value = password;

            // Flash effect to highlight input
            uInput.classList.add('ring-2', 'ring-sky-500', 'bg-white');
            pInput.classList.add('ring-2', 'ring-sky-500', 'bg-white');
            setTimeout(function() {
                uInput.classList.remove('ring-2', 'ring-sky-500');
                pInput.classList.remove('ring-2', 'ring-sky-500');
            }, 600);

            if (autoSubmit && form) {
                var btn = document.getElementById('submitBtn');
                if (btn) btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-xs"></i> <span>กำลังเข้าสู่ระบบ...</span>';
                form.submit();
            }
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php
/**
 * User Logout
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlash('info', 'ออกจากระบบเรียบร้อยแล้ว');
redirect('login.php');

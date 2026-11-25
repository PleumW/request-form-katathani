<?php
/**
 * AJAX Endpoint: Search & Select Employees from ex_user
 * Searches by Employee ID, User Code, or Name to populate dropdown
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Allow searching for logged in users OR when registering new account
// (ex_user holds basic directory information: names and department)

$db = getDB();
$code = trim($_GET['code'] ?? ($_GET['q'] ?? ''));

$sql = "SELECT u.user_id, u.user_code, u.hr_employee_id, u.user_employee_id, 
               u.user_name, u.user_surname, u.user_nickname, u.user_gender, u.dep_id, u.user_hotel, u.user_title_id,
               t.title_name, d.dep_name, d.dep_name_s, h.hotel_name, h.hotel_name_s, p.property_name
        FROM ex_user u
        LEFT JOIN ex_title t ON u.user_title_id = t.title_id
        LEFT JOIN ex_department d ON u.dep_id = d.dep_id
        LEFT JOIN ex_hotel h ON u.user_hotel = h.hotel_id
        LEFT JOIN ex_property p ON h.hotel_property_id = p.property_id
        WHERE u.user_status = 1";

$params = [];
if (!empty($code)) {
    $sql .= " AND (u.hr_employee_id = ? OR u.user_employee_id = ? OR u.user_code = ? 
               OR u.hr_employee_id LIKE ? OR u.user_employee_id LIKE ? OR u.user_code LIKE ?
               OR u.user_name LIKE ? OR u.user_surname LIKE ? OR CONCAT(u.user_name, ' ', u.user_surname) LIKE ? OR d.dep_name LIKE ?)";
    $exact = $code;
    $like = "%{$code}%";
    $params = [$exact, $exact, $exact, $like, $like, $like, $like, $like, $like, $like, $exact, $exact];
    $sql .= " ORDER BY (u.hr_employee_id = ? OR u.user_code = ?) DESC, u.user_name ASC LIMIT 30";
} else {
    // When no search query, show top active employees
    $sql .= " ORDER BY u.user_id ASC LIMIT 30";
}

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $results = [];
    foreach ($users as $u) {
        $empId = !empty($u['hr_employee_id']) ? $u['hr_employee_id'] : ($u['user_employee_id'] ?? $u['user_code']);
        $genderText = ($u['user_gender'] == 2) ? 'หญิง' : 'ชาย';
        $results[] = [
            'user_id' => $u['user_id'],
            'user_code' => $u['user_code'],
            'employee_id' => $empId,
            'title' => $u['title_name'] ?? 'Mr.',
            'title_id' => $u['user_title_id'],
            'gender' => $genderText,
            'first_name' => $u['user_name'],
            'last_name' => $u['user_surname'],
            'nickname' => $u['user_nickname'] ?? '',
            'full_name' => trim($u['user_name'] . ' ' . $u['user_surname']),
            'dep_id' => $u['dep_id'],
            'department' => $u['dep_name'] ?? '',
            'hotel_id' => $u['user_hotel'],
            'hotel_name' => $u['hotel_name'] ?? 'Katathani',
            'property_name' => $u['property_name'] ?? 'PHUKET'
        ];
    }

    echo json_encode([
        'success' => true,
        'found' => count($results) > 0,
        'exact' => !empty($results[0]) ? $results[0] : null,
        'data' => $results
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;

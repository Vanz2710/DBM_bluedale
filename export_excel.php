<?php
session_start();
if (!isset($_SESSION['user_id'])) { exit; }

$host = 'localhost'; 
$db = 'dbm_bluedale'; 
$user = 'root'; 
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
} catch (PDOException $e) { die("Connection Error"); }

$where_clauses = [];
$params = [];

// --- GET SELECTED IDS FROM CHECKBOXES ---
if (!empty($_GET['ids'])) {
    $idArray = explode(',', $_GET['ids']);
    $placeholders = implode(',', array_fill(0, count($idArray), '?'));
    $where_clauses[] = "t.id IN ($placeholders)";
    $params = $idArray;
} else {
    // Fallback: If no checkboxes, export by the date filtered on screen
    $selected_date = $_GET['todo_date'] ?? date('Y-m-d');
    $where_clauses[] = "t.task_date = ?";
    $params[] = $selected_date;
}

// --- SQL QUERY ---
$query = "SELECT 
            t.task_date as to_do_date,
            t.deadline_date as date_created,
            s.status_name, 
            typ.Type_name, 
            c.company_name, 
            u.name as user_name, 
            t.task_type, 
            t.remark
          FROM company_tasks t
          LEFT JOIN companies c ON t.company_id = c.id
          LEFT JOIN users u ON t.user_id = u.id
          LEFT JOIN statuses s ON c.status_id = s.id
          LEFT JOIN type typ ON c.type_id = typ.id";

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY t.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- CSV EXPORT SETTINGS ---
$filename = "Bluedale_ToDo_Export_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel encoding (UTF-8)

// --- CSV HEADER ---
fputcsv($output, [
    'NO', 
    'TO DO DATE', 
    'DATE CREATED', 
    'STATUS', 
    'TYPE', 
    'COMPANY NAME', 
    'USER', 
    'TASK', 
    'REMARK'
]);

// --- CSV DATA ---
$no = 1;
foreach ($data as $row) {
    // Format "To Do Date" (d-m-y)
    $formatted_todo = ($row['to_do_date']) ? date('d-m-Y', strtotime($row['to_do_date'])) : '-';
    
    // Format "Date Created" (d-m-y)
    // We check if it's empty or set to the SQL default '0000-00-00'
    $formatted_created = ($row['date_created'] && $row['date_created'] != '0000-00-00') 
                         ? date('d-m-Y', strtotime($row['date_created'])) 
                         : '-';

    fputcsv($output, [
        $no++,
        $formatted_todo,
        $formatted_created,
        $row['status_name'],
        $row['Type_name'],
        $row['company_name'],
        $row['user_name'],
        $row['task_type'],
        $row['remark']
    ]);
}

fclose($output);
exit();
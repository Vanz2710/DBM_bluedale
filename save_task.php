<?php
session_start();
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_id  = $_POST['company_id'];
    $user_id     = $_POST['user_id']; // This captures your choice from task.php
    $task_date   = $_POST['task_date'];
    $task_type   = $_POST['task_type'];
    $remark      = $_POST['remark'];
    $date_created = $_POST['date_created'];

    $sql = "INSERT INTO company_tasks (company_id, user_id, task_date, task_type, remark, deadline_date) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissss", $company_id, $user_id, $task_date, $task_type, $remark, $date_created);
    
    if ($stmt->execute()) {
        header("Location: to_do.php?todo_date=" . $task_date);
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
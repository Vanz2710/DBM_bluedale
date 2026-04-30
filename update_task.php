<?php
session_start();
// Security check: only allow logged-in users
if (!isset($_SESSION['user_id'])) { exit("Unauthorized access."); }

$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect Task data from the form
    $task_id    = $_POST['task_id'];
    $user_id    = $_POST['user_id'];    // This updates the user for this task
    $task_date  = $_POST['task_date'];
    $task_type  = $_POST['task_type'];
    $remark     = $_POST['remark'];

    // Collect Company data from the form
    $company_id = $_POST['company_id'];
    $status_id  = $_POST['status_id'];
    $type_id    = $_POST['type_id'];

    // 1. Update the company_tasks table
    // We update the user_id here so the name changes in your to_do.php list
    $stmt1 = $conn->prepare("UPDATE company_tasks SET user_id = ?, task_date = ?, task_type = ?, remark = ? WHERE id = ?");
    $stmt1->bind_param("isssi", $user_id, $task_date, $task_type, $remark, $task_id);
    $stmt1->execute();

    // 2. Update the companies table
    // This updates the status and type for that specific company
    $stmt2 = $conn->prepare("UPDATE companies SET status_id = ?, type_id = ? WHERE id = ?");
    $stmt2->bind_param("iii", $status_id, $type_id, $company_id);
    $stmt2->execute();

    // 3. Success Redirect
    // Redirecting with the task_date ensures you land on the correct date view in to_do.php
    header("Location: to_do.php?todo_date=" . $task_date . "&msg=updated");
    exit();
}

$conn->close();
?>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "dbm_bluedale");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set current_page for navbar active state. Assuming 'summary' is the main hub, or you can create a 'history.php' specific entry if needed.
$current_page = 'summary.php'; 

$company_id = $_GET['id'] ?? null;

if (!$company_id) {
    // Redirect if company_id is not provided in the URL
    header("Location: summary.php?error=no_company_id"); 
    exit();
}

// Fetch Company Name for the header
$company_name = "Unknown Company";
$stmt_company = $conn->prepare("SELECT company_name FROM companies WHERE id = ?");
$stmt_company->bind_param("i", $company_id);
$stmt_company->execute();
$result_company = $stmt_company->get_result();
if ($row = $result_company->fetch_assoc()) {
    $company_name = $row['company_name'];
}
$stmt_company->close();

// Fetch tasks for this company, ordered by date (newest first as per your example image)
$tasks = [];
$stmt_tasks = $conn->prepare("SELECT ct.task_date, ct.task_type, ct.remark, u.name as user_name 
                            FROM company_tasks ct
                            LEFT JOIN users u ON ct.user_id = u.id
                            WHERE ct.company_id = ? 
                            ORDER BY ct.task_date DESC, ct.id DESC"); // Added ct.id DESC for stable sorting if dates are same
$stmt_tasks->bind_param("i", $company_id);
$stmt_tasks->execute();
$result_tasks = $stmt_tasks->get_result();
while ($row = $result_tasks->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt_tasks->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>History - <?= htmlspecialchars($company_name) ?></title>
    <link rel="stylesheet" href="history.css">
    <!-- Font Awesome for the TODO button icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <!-- Navbar: Make sure this is consistent across all your pages -->
   <nav class="navbar">
        <div class="logo"><a href="logout.php"><img src="logo.png" alt="Logo"></a></div>
        
            <ul class="nav-links">
            <li><a href="summary.php" class="<?= ($current_page == 'summary.php') ? 'active' : '' ?>">summary</a></li>
            <li><a href="listPage.php" class="<?= ($current_page == 'list.php') ? 'active' : '' ?>">List</a></li>
            <li><a href="addData.php" class="<?= ($current_page == 'addData.php') ? 'active' : '' ?>">Add Data</a></li>
            <li><a href="to_do.php" class="<?= ($current_page == 'import.php') ? 'active' : '' ?>">to do</a></li>
            <li><a href="project.php" class="<?= ($current_page == 'project.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="performance.php" class="<?= ($current_page == 'performance.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="bbtp.php" class="<?= ($current_page == 'bbtp.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="tutorial.php" class="<?= ($current_page == 'tutorial.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="admin_features.php" class="<?= ($current_page == 'media1.php') ? 'active' : '' ?>">admin</a></li>
        </ul>
    </nav>

    <div class="main-container">
        <div class="white-card">
            <div class="header-bar">Task History for <?= htmlspecialchars($company_name) ?></div>
            
            <div class="history-table">
                <div class="table-header">
                    <div class="col date">DATE</div>
                    <div class="col cs">CS</div>
                    <div class="col task">TASK</div>
                    <div class="col action">ACTION</div>
                    <div class="col remark">REMARK</div>
                    <div class="col todo"></div> <!-- For the TODO button -->
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="no-tasks">No tasks recorded for this company.</div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): 
                        $formatted_date = date('d-m-y', strtotime($task['task_date']));
                        
                        // For 'ACTION' column:
                        // If remark is short (e.g., a code like "AA", "JG", "Call - F 1"), use it.
                        // If remark is a longer descriptive sentence, use the task_type for 'ACTION' for distinction.
                        // This heuristic aims to match the image's varied 'ACTION' column.
                        $action_display_text = htmlspecialchars($task['remark']);
                        if (strlen($task['remark']) > 30 && strpos($task['remark'], ' - ') === false) { // A simple heuristic
                            $action_display_text = htmlspecialchars($task['task_type']);
                        }
                        // For 'REMARK' column: Always display the full remark.
                        $full_remark_text = htmlspecialchars($task['remark']);
                    ?>
                        <div class="task-row">
                            <div class="col date"><?= $formatted_date ?></div>
                            <div class="col cs">Media <?= htmlspecialchars($task['user_name'] ?? 'N/A') ?></div>
                            <div class="col task"><?= htmlspecialchars($task['task_type']) ?></div>
                            <div class="col action"><?= $action_display_text ?></div>
                            <div class="col remark"><?= $full_remark_text ?></div>
                            <div class="col todo">
                                <button class="todo-btn">
                                    <i class="fas fa-tasks"></i> TODO
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
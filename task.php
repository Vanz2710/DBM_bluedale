<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn = new mysqli("localhost", "root", "", "dbm_bluedale");
$company_id = $_GET['id'];

// Get session name if missing
if (!isset($_SESSION['name'])) {
    $uid = $_SESSION['user_id'];
    $u_res = $conn->query("SELECT name FROM users WHERE id = $uid");
    $u_row = $u_res->fetch_assoc();
    $_SESSION['name'] = $u_row['name'];
}

// Fetch Company Name
$res = $conn->query("SELECT company_name FROM companies WHERE id = $company_id");
$company = $res->fetch_assoc();

// Fetch Task List
$task_list_res = $conn->query("SELECT task_name FROM task_list ORDER BY task_name ASC");

// --- FETCH USERS FOR THE DROPDOWN ---
$user_res = $conn->query("SELECT id, name FROM users ORDER BY name ASC");
$users = [];
while($row = $user_res->fetch_assoc()) {
    $users[] = $row;
}

$current_page = 'summary.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Task Entry - Bluedale</title>
    <link rel="stylesheet" href="task.css">
</head>
<body>
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
        <div class="task-card">
            <div class="blue-header">TO DO</div>
            <div class="form-content">
                <div class="green-company-bar"><?= strtoupper($company['company_name']) ?></div>

                <form action="save_task.php" method="POST">
                    <input type="hidden" name="company_id" value="<?= $company_id ?>">

                    <div class="input-row">
                        <label>USER</label>
                        <select name="user_id" class="grey-input" required>
                            <option value="">Select User</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= (isset($_SESSION['name']) && $u['name'] == $u['name']) ? 'selected' : '' ?>>
                                    <?= strtoupper($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-row">
                        <label>TO DO DATE</label>
                        <input type="date" name="task_date" required class="grey-input">
                    </div>

                    <div class="input-row">
                        <label>DATE CREATED</label>
                        <input type="date" name="date_created" class="grey-input" value="<?= date('Y-m-d') ?>" readonly>
                    </div>

                    <div class="input-row">
                        <label>TASK</label>
                        <select name="task_type" required class="grey-input">
                            <option value="">Select Task</option>
                            <?php while($row = $task_list_res->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['task_name']) ?>"><?= htmlspecialchars($row['task_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-row">
                        <label>REMARK</label>
                        <input type="text" name="remark" placeholder="Enter initials" required class="grey-input">
                    </div>

                    <div class="btn-center">
                        <button type="submit" class="create-btn">CREATE</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
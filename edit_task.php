<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

// Get the Task ID from the URL safely
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch current task details and joined company info
// We need t.user_id to know who is currently assigned
$sql = "SELECT t.*, c.status_id, c.type_id, c.company_name 
        FROM company_tasks t 
        JOIN companies c ON t.company_id = c.id 
        WHERE t.id = $task_id";
$res = $conn->query($sql);
$current_task = $res->fetch_assoc();

if (!$current_task) { die("Task not found."); }

// Fetch Dropdown Data
$users = $conn->query("SELECT id, name FROM users ORDER BY name ASC");
$tasks_list = $conn->query("SELECT task_name FROM task_list ORDER BY task_name ASC");
$statuses = $conn->query("SELECT id, status_name FROM statuses");
$types = $conn->query("SELECT id, Type_name FROM type");

$current_page = 'to_do.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit To Do - Bluedale</title>
    <link rel="stylesheet" href="edit_task.css">
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

    <div class="edit-container">
        <div class="edit-card">
            <div class="edit-header">EDIT TO DO</div>
            
            <form action="update_task.php" method="POST" class="edit-form">
                <!-- Hidden IDs so the update script knows which records to change -->
                <input type="hidden" name="task_id" value="<?= $task_id ?>">
                <input type="hidden" name="company_id" value="<?= $current_task['company_id'] ?>">

                <div class="form-grid">
                    <!-- USER DROPDOWN (Matches task.php style) -->
                    <div class="input-group">
                        <label>user</label>
                        <select name="user_id" class="grey-input" required>
                            <?php while($u = $users->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>" <?= ($u['id'] == $current_task['user_id']) ? 'selected' : '' ?>>
                                    <?= strtoupper($u['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- DATE CREATED (Read Only) -->
                    <div class="input-group">
                        <label>date created</label>
                        <input type="date" name="deadline_date" value="<?= $current_task['deadline_date'] ?>" class="grey-input" readonly>
                    </div>

                    <!-- DATE TO DO -->
                    <div class="input-group">
                        <label>date to do*</label>
                        <input type="date" name="task_date" value="<?= $current_task['task_date'] ?>" class="grey-input" required>
                    </div>

                    <!-- TASK TYPE -->
                    <div class="input-group">
                        <label>task type*</label>
                        <select name="task_type" class="grey-input" required>
                            <?php while($tk = $tasks_list->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($tk['task_name']) ?>" <?= ($tk['task_name'] == $current_task['task_type']) ? 'selected' : '' ?>>
                                    <?= $tk['task_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- COMPANY STATUS -->
                    <div class="input-group">
                        <label>company status</label>
                        <select name="status_id" class="grey-input">
                            <?php while($s = $statuses->fetch_assoc()): ?>
                                <option value="<?= $s['id'] ?>" <?= ($s['id'] == $current_task['status_id']) ? 'selected' : '' ?>>
                                    <?= $s['status_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- COMPANY TYPE -->
                    <div class="input-group">
                        <label>company type</label>
                        <select name="type_id" class="grey-input">
                            <?php while($t = $types->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>" <?= ($t['id'] == $current_task['type_id']) ? 'selected' : '' ?>>
                                    <?= $t['Type_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="input-group full-width">
                    <label>remark</label>
                    <textarea name="remark" class="grey-textarea"><?= htmlspecialchars($current_task['remark']) ?></textarea>
                </div>

                <div class="btn-container">
                    <button type="submit" class="done-btn">DONE</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$host = 'localhost'; $db = 'dbm_bluedale'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
} catch (PDOException $e) { die("Connection Error: " . $e->getMessage()); }

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_done'])) {
    $company_id = $_POST['company_id'];
    $user_id = $_POST['user_id'];
    $task_date = $_POST['task_date'];
    $date_created = $_POST['date_created']; 
    $task_type = $_POST['task_type'];
    $status_id = $_POST['status_id'];
    $type_id = $_POST['type_id'];
    $remark = $_POST['remark'];

    // 1. Insert into company_tasks
    $stmt = $pdo->prepare("INSERT INTO company_tasks (company_id, user_id, task_date, deadline_date, task_type, remark) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$company_id, $user_id, $task_date, $date_created, $task_type, $remark]);

    // 2. Sync/Update the Company Status and Type in the main table
    $updateStmt = $pdo->prepare("UPDATE companies SET status_id = ?, type_id = ? WHERE id = ?");
    $updateStmt->execute([$status_id, $type_id, $company_id]);

    // --- FIXED REDIRECTION HERE ---
    header("Location: to_do.php"); 
    exit();
}

// Fetch data for dropdowns
$companies = $pdo->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll();
$users = $pdo->query("SELECT id, name FROM users")->fetchAll();
$statuses = $pdo->query("SELECT id, status_name FROM statuses")->fetchAll();
$types = $pdo->query("SELECT id, Type_name FROM type")->fetchAll();
$task_options = $pdo->query("SELECT * FROM task_list ORDER BY task_name ASC")->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reminder - Bluedale</title>
    <link rel="stylesheet" href="add_reminder.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

    <div class="reminder-container">
        <form method="POST">
            <div class="reminder-card">
                <div class="reminder-header">Reminder</div>
                
                <div class="form-grid">
                    <div class="left-col">
                        <div class="input-box">
                            <label>search or select company name</label>
                            <div class="search-wrapper">
                                <select name="company_id" class="searchable-select" required>
                                    <option value="">Select Company</option>
                                    <?php foreach($companies as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="input-box">
                            <label>To Do Date*</label>
                            <input type="date" name="task_date" required>
                        </div>

                        <div class="input-box">
                            <label>this fields is required*</label>
                            <select name="task_type" required>
                                <option value="">select task</option>
                                <?php foreach($task_options as $task): ?>
                                    <option value="<?= htmlspecialchars($task['task_name']) ?>"><?= htmlspecialchars($task['task_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-box">
                            <label>this fields is required*</label>
                            <select name="status_id" required>
                                <option value="">select status</option>
                                <?php foreach($statuses as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= $s['status_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-box">
                            <label>this fields is required*</label>
                            <select name="type_id" required>
                                <option value="">select type</option>
                                <?php foreach($types as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= $t['Type_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-box">
                            <label>Date Created*</label>
                            <input type="date" name="date_created" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="right-col">
                        <div class="input-box">
                            <label>this fields is required*</label>
                            <select name="user_id" required>
                                <option value="">select user</option>
                                <?php foreach($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= $u['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="remark-section">
                    <label>remark</label>
                    <textarea name="remark" placeholder="..."></textarea>
                </div>

                <div class="button-section">
                    <button type="submit" name="btn_done" class="btn-done">done</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.searchable-select').select2();
        });
    </script>
</body>
</html>
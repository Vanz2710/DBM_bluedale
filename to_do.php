<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$host = 'localhost'; $db = 'dbm_bluedale'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
} catch (PDOException $e) { die("Connection Error: " . $e->getMessage()); }

$current_page = basename($_SERVER['PHP_SELF']);

// --- 1. COLLECT FILTERS ---
$view_mode = $_GET['view'] ?? 'Day'; // Day, Month, or Year
$selected_date = $_GET['todo_date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$per_page = (isset($_GET['per_page']) && (int)$_GET['per_page'] > 0) ? (int)$_GET['per_page'] : 100;
$selected_user_id = $_GET['user_id'] ?? ''; 

// --- 2. FETCH USERS (FOR DROPDOWN) ---
$all_users = $pdo->query("SELECT id, name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- 3. BUILD DYNAMIC SQL QUERY ---
$query = "SELECT t.*, c.company_name, u.name as user_name, s.status_name, typ.Type_name
          FROM company_tasks t
          LEFT JOIN companies c ON t.company_id = c.id
          LEFT JOIN users u ON t.user_id = u.id
          LEFT JOIN statuses s ON c.status_id = s.id
          LEFT JOIN type typ ON c.type_id = typ.id
          WHERE 1=1"; // Dummy where to allow easy appending

// Handle Day / Month / Year Filtering
if ($view_mode == 'Day') {
    $query .= " AND t.task_date = :date";
} elseif ($view_mode == 'Month') {
    $query .= " AND DATE_FORMAT(t.task_date, '%Y-%m') = DATE_FORMAT(:date, '%Y-%m')";
} elseif ($view_mode == 'Year') {
    $query .= " AND YEAR(t.task_date) = YEAR(:date)";
}

if(!empty($search)) { $query .= " AND c.company_name LIKE :search"; }
if(!empty($selected_user_id)) { $query .= " AND t.user_id = :user_id"; }

$query .= " ORDER BY t.task_date DESC, t.id DESC LIMIT :limit";

// --- 4. PREPARE AND EXECUTE ---
$stmt = $pdo->prepare($query);
$stmt->bindValue(':date', $selected_date);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);

if(!empty($search)) { $stmt->bindValue(':search', "%$search%"); }
if(!empty($selected_user_id)) { $stmt->bindValue(':user_id', $selected_user_id, PDO::PARAM_INT); }

$stmt->execute();
$tasks = $stmt->fetchAll();

// Fetch Task List for action dropdown
$all_available_tasks = $pdo->query("SELECT task_name FROM task_list ORDER BY task_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>To Do List - Bluedale</title>
    <link rel="stylesheet" href="to_do.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

    <div class="selection-info-bar" id="selectionBar" style="display: none;">
        <button type="button" class="btn-export-blue" onclick="handleExport()"><i class="fas fa-share-square"></i> Export</button>
        <span class="selection-text">Selected: <strong id="selectedCount">0</strong> record(s).</span>
    </div>

    <div class="top-toolbar">
        <form method="GET" class="filter-flex" id="filterForm">
            <!-- View By Dropdown -->
            <div class="filter-group">
                <label>View by</label>
                <select name="view" onchange="this.form.submit()">
                    <option value="Day" <?= $view_mode == 'Day' ? 'selected' : '' ?>>Day</option>
                    <option value="Month" <?= $view_mode == 'Month' ? 'selected' : '' ?>>Month</option>
                    <option value="Year" <?= $view_mode == 'Year' ? 'selected' : '' ?>>Year</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Select date</label>
                <input type="date" name="todo_date" value="<?= $selected_date ?>" onchange="this.form.submit()">
            </div>

            <div class="filter-group search-grow">
                <label>Filter term</label>
                <div style="display: flex; gap: 5px;">
                    <input type="text" name="search" placeholder="Search Company..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-search-icon"><i class="fas fa-search"></i></button>
                </div>
            </div>

            <div class="filter-group">
                <label>Select user</label>
                <select name="user_id" onchange="this.form.submit()">
                    <option value="">All Users</option>
                    <?php foreach($all_users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($selected_user_id == $u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Per page</label>
                <input type="number" name="per_page" value="<?= $per_page ?>" style="width: 70px;" onchange="this.form.submit()">
            </div>
            
            <button type="button" class="btn-to-do" onclick="window.location.href='addData.php'">+ TO DO</button>
        </form>
    </div>

    <div class="main-content">
        <div class="table-outer">
            <div class="table-header-date">
                <?php 
                    if($view_mode == 'Day') echo date('d-m-Y', strtotime($selected_date));
                    elseif($view_mode == 'Month') echo date('F Y', strtotime($selected_date));
                    else echo date('Y', strtotime($selected_date));
                ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                        <th style="width: 40px;">NO</th>
                        <th>TO DO DATE</th>
                        <th>DATE CREATED</th>
                        <th>STATUS</th>
                        <th>TYPE</th>
                        <th>COMPANY NAME</th>
                        <th>USER</th>
                        <th>TASK</th>
                        <th>REMARK</th>
                        <th>ACTION</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($tasks) > 0): ?>
                        <?php foreach($tasks as $i => $row): ?>
                        <tr>
                            <td><input type="checkbox" class="task-checkbox" value="<?= $row['id'] ?>"></td>
                            <td><?= $i + 1 ?></td>
                            <td><?= date('d-m-y', strtotime($row['task_date'])) ?></td>
                            <td><?= (!empty($row['deadline_date']) && $row['deadline_date'] != '0000-00-00') ? date('d-m-y', strtotime($row['deadline_date'])) : '-' ?></td>
                            <td><?= $row['status_name'] ?></td>
                            <td><?= $row['Type_name'] ?></td>
                            <td class="text-left"><?= htmlspecialchars($row['company_name'] ?? 'N/A') ?></td>
                            <td><strong><?= htmlspecialchars($row['user_name'] ?? 'Unassigned') ?></strong></td>
                            <td><?= htmlspecialchars($row['task_type']) ?></td>
                            <td><?= htmlspecialchars($row['remark']) ?></td>
                            <td>
                                 <select class="action-select">
                                    <option value="">Action</option>
                                    <?php foreach($all_available_tasks as $t): ?>
                                        <option value="<?= htmlspecialchars($t['task_name']) ?>" <?= ($t['task_name'] == $row['task_type']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['task_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><a href="edit_task.php?id=<?= $row['id'] ?>"><img src="editYellowButton.png" width="25"></a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="12" style="padding: 20px;">No results found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.task-checkbox');
    const selectionBar = document.getElementById('selectionBar');
    const countDisplay = document.getElementById('selectedCount');

    function updateSelectionUI() {
        const checkedBoxes = document.querySelectorAll('.task-checkbox:checked');
        countDisplay.textContent = checkedBoxes.length;
        selectionBar.style.display = checkedBoxes.length > 0 ? 'flex' : 'none';
    }

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateSelectionUI();
    });

    checkboxes.forEach(cb => cb.addEventListener('change', updateSelectionUI));
});

function handleExport() {
    const ids = Array.from(document.querySelectorAll('.task-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) return alert("Select records.");
    window.location.href = 'export_todo.php?ids=' + ids.join(',');
}
</script>
</body>
</html>
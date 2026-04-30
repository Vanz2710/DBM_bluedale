<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$current_page = 'listPage.php';
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_company') {
    $company_id = (int)($_POST['company_id'] ?? 0);

    if ($company_id > 0) {
        $conn->begin_transaction();

        try {
            $stmt_delete_tasks = $conn->prepare("DELETE FROM company_tasks WHERE company_id = ?");
            $stmt_delete_tasks->bind_param("i", $company_id);
            $stmt_delete_tasks->execute();
            $stmt_delete_tasks->close();

            $stmt_delete_pics = $conn->prepare("DELETE FROM company_pics WHERE company_id = ?");
            $stmt_delete_pics->bind_param("i", $company_id);
            $stmt_delete_pics->execute();
            $stmt_delete_pics->close();

            $stmt_delete_company = $conn->prepare("DELETE FROM companies WHERE id = ?");
            $stmt_delete_company->bind_param("i", $company_id);
            $stmt_delete_company->execute();
            $stmt_delete_company->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
        }
    }

    $return_query = str_replace(["\r", "\n"], "", $_POST['return_query'] ?? "");
    header("Location: listPage.php" . ($return_query ? "?" . $return_query : ""));
    exit();
}

// --- 1. SET THE DATE SORT (Defaults to Oldest First) ---
$date_sort = $_GET['date_sort'] ?? 'oldest';
if (!in_array($date_sort, ['newest', 'oldest'], true)) {
    $date_sort = 'oldest';
}

// --- 2. BUILD FILTERING LOGIC ---
$where_clauses = [];
$params = [];
$types = "";

// Other filters (User, Status, etc.)
$filters = ['user_id', 'status_id', 'type_id', 'industry', 'product_id'];
foreach ($filters as $f) {
    if (!empty($_GET[$f])) {
        $where_clauses[] = "c.$f = ?";
        $params[] = $_GET[$f];
        $types .= "i";
    }
}

// Search filter
if (!empty($_GET['search'])) {
    $where_clauses[] = "c.company_name LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
    $types .= "s";
}

$query = "SELECT c.*, s.status_name, t.Type_name, i.Industry_name, p.product_name, u.name as user_name
          FROM companies c
          LEFT JOIN statuses s ON c.status_id = s.id
          LEFT JOIN type t ON c.type_id = t.id
          LEFT JOIN industry i ON c.industry = i.id
          LEFT JOIN products p ON c.product_id = p.id
          LEFT JOIN users u ON c.user_id = u.id";

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}
$order_direction = ($date_sort === 'oldest') ? 'ASC' : 'DESC';
$query .= " ORDER BY c.created_at $order_direction, c.id $order_direction";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Company List</title>
    <link rel="stylesheet" href="list.css">
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

    <div class="toolbar">
        <form method="GET" style="display:flex; gap:15px; align-items:center;">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search Company..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <input type="hidden" name="date_sort" value="<?= $date_sort ?>">
        </form>
    </div>

    <form id="deleteCompanyForm" method="POST">
        <input type="hidden" name="action" value="delete_company">
        <input type="hidden" name="return_query" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>">
    </form>

    <div class="table-container">
        <form id="filterForm" method="GET">
            <input type="hidden" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            
            <table>
                <thead>
                    <tr class="header-row">
                        <th>No</th>
                        <th>Date Created</th>
                        <th>CS (User)</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Industry</th>
                        <th>Company Name</th>
                        <th>Product</th>
                        <th>Action</th>
                    </tr>
                    <tr class="filter-row">
                        <td></td>
                        <td>
                            <select name="date_sort" onchange="this.form.submit()">
                                <option value="newest" <?= ($date_sort == 'newest') ? 'selected' : '' ?>>Newest To Oldest</option>
                                <option value="oldest" <?= ($date_sort == 'oldest') ? 'selected' : '' ?>>Oldest To Newest</option>
                            </select>
                        </td>
                        <td>
                            <select name="user_id" onchange="this.form.submit()">
                                <option value="">All Users</option>
                                <?php 
                                $usrs = $conn->query("SELECT * FROM users");
                                while($u = $usrs->fetch_assoc()) echo "<option value='{$u['id']}' ".((isset($_GET['user_id']) && $_GET['user_id']==$u['id'])?'selected':'').">{$u['name']}</option>";
                                ?>
                            </select>
                        </td>
                        <td>
                             <select name="status_id" onchange="this.form.submit()">
                                <option value="">All</option>
                                <?php 
                                $st = $conn->query("SELECT * FROM statuses");
                                while($s = $st->fetch_assoc()) echo "<option value='{$s['id']}' ".((isset($_GET['status_id']) && $_GET['status_id']==$s['id'])?'selected':'').">{$s['status_name']}</option>";
                                ?>
                            </select>
                        </td>
                        <td colspan="5"></td>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
                        <td><strong><?= $row['user_name'] ?></strong></td>
                        <td><?= $row['status_name'] ?></td>
                        <td><?= $row['Type_name'] ?></td>
                        <td><?= $row['Industry_name'] ?></td>
                        <td class="company-link"><a href="company_info.php?id=<?= $row['id'] ?>"><?= $row['company_name'] ?></a></td>
                        <td><?= $row['product_name'] ?></td>
                        <td class="action-btns">
                            <a href="task.php?id=<?= $row['id'] ?>"><img src="ToDoButton.png" class="todo-action-icon" width="30"></a>
                            <a href="edit_company.php?id=<?= $row['id'] ?>"><img src="editYellowButton.png" class="edit-action-icon" width="30"></a>
                            <button type="submit" form="deleteCompanyForm" name="company_id" value="<?= $row['id'] ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this company data?');">
                                <img src="DeleteButton.png" width="30" alt="Delete">
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($result->num_rows == 0): ?>
                        <tr><td colspan="9" style="padding:20px;">No data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>
</body>
</html>

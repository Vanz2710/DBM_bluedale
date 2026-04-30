<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}



$host = 'localhost'; 
$db = 'dbm_bluedale'; 
$user = 'root'; 
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
} catch (PDOException $e) { 
    die("Connection Error: " . $e->getMessage()); 
}

$current_page = basename($_SERVER['PHP_SELF']);

// ==========================================
// 1. GET THE SELECTED YEAR
// ==========================================
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : 2026;

// --- FILTERING LOGIC ---
$where_clauses = [];
$params = [];

$filters = ['user_id', 'status_id', 'type_id', 'product_id', 'industry', 'area'];
foreach ($filters as $filter) {
    if (!empty($_GET[$filter])) {
        $where_clauses[] = "c.$filter = :$filter";
        $params[$filter] = $_GET[$filter];
    }
}

if (!empty($_GET['search'])) {
    $where_clauses[] = "c.company_name LIKE :search";
    $params['search'] = "%" . $_GET['search'] . "%";
}

// Fetch Companies
$query = "SELECT c.*, s.status_name, u.name as user_name, t.Type_name, p.product_name, i.Industry_name, a.Area_name 
          FROM companies c
          LEFT JOIN statuses s ON c.status_id = s.id
          LEFT JOIN users u ON c.user_id = u.id
          LEFT JOIN type t ON c.type_id = t.id
          LEFT JOIN products p ON c.product_id = p.id
          LEFT JOIN industry i ON c.industry = i.id
          LEFT JOIN area a ON c.area = a.id";

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}
$query .= " ORDER BY c.id DESC";

$stmt_main = $pdo->prepare($query);
$stmt_main->execute($params);
$companies = $stmt_main->fetchAll();

// ==========================================
// 2. FETCH TASKS
// ==========================================
$task_map = [];
$task_query = "SELECT company_id, task_type, remark, task_date, MONTH(task_date) as month 
               FROM company_tasks 
               WHERE YEAR(task_date) = ?"; 
$task_stmt = $pdo->prepare($task_query);
$task_stmt->execute([$selected_year]);
$all_tasks = $task_stmt->fetchAll();

foreach ($all_tasks as $t) {
    $formatted_date = date("d-m-y", strtotime($t['task_date']));
    // Store as array to keep type and remark
    $task_map[$t['company_id']][$t['month']] = [
        'date' => $formatted_date,
        'type' => $t['task_type'],
        'remark' => $t['remark']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Summary - Bluedale</title>
    <link rel="stylesheet" href="summary.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        table { width: 100%; table-layout: fixed; border-collapse: collapse; background-color: transparent;}
        
        /* Headers styling */
        thead th { background-color: #007bff; color: white; font-size: 10px; border: 1px solid #007bff; padding: 5px 2px; }

        /* Bright Blue Month Headers matching image */
        .main-headers th:nth-last-child(-n+12) { background-color: #007bff !important; }

        th:nth-child(2), th:nth-child(3), th:nth-child(4), 
        th:nth-child(5), th:nth-child(6), th:nth-child(7), th:nth-child(8) { width: 70px; }
        
        th:nth-child(9), td:nth-child(9) { width: 220px; text-align: left; }

        .filter-row td { background-color: #4576ab; padding: 4px; border: 1px solid #4576ab; }
        .filter-row select { width: 100%; font-size: 10px; border-radius: 4px; border: none; padding: 2px; cursor: pointer; }

        td { height: 45px; vertical-align: middle; padding: 5px; border: 1px solid #ddd; font-size: 11px;}
        
        /* Box/Badge Styling */
        .task-badge {
            display: inline-block; padding: 3px 5px; border-radius: 4px;
            font-size: 9px; line-height: 1.1; text-align: center; width: 90%;
        }
        .badge-date { font-weight: bold; font-size: 10px; }
        .badge-info { display: block; font-size: 8px; font-weight: normal; opacity: 0.8; }
        
        .badge-green { background-color: #9df3c4; color: #1b5e20; }
        .badge-red { background-color: #ffb3ba; color: #b71c1c; }
        .year-select { background: none; border: none; font-size: 24px; font-weight: bold; color: #333; cursor: pointer; }
    </style>
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
        <div class="year-box">
            <form method="GET" id="yearForm">
                <select name="year" class="year-select" onchange="this.form.submit()">
                    <?php 
                    for($y=2024; $y<=2030; $y++) {
                        $sel = ($y == $selected_year) ? 'selected' : '';
                        echo "<option value='$y' $sel>$y</option>";
                    }
                    ?>
                </select>
                <?php foreach($_GET as $key => $val): if($key != 'year'): ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
                <?php endif; endforeach; ?>
            </form>
        </div>

        <form method="GET" class="search-container">
            <input type="text" name="search" placeholder="Search + Press ENTER" value="<?= $_GET['search'] ?? '' ?>">
            <input type="hidden" name="year" value="<?= $selected_year ?>">
            <button type="submit" class="search-btn"><i class="fa fa-search"></i></button>
        </form>
        <!-- Replace your current export button with this -->
        <button type="button" class="export-btn" onclick="exportData()">
            <i class="fa fa-share-square"></i> Export
        </button>

       <script>
        // 1. SELECT ALL FUNCTIONALITY
        document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.company-checkbox');
        for (let checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
        });

        // 2. EXPORT FUNCTIONALITY
        function exportData() {
        const checkboxes = document.querySelectorAll('.company-checkbox:checked');
        const selectedIds = Array.from(checkboxes).map(cb => cb.value);

        const urlParams = new URLSearchParams(window.location.search);

        if (selectedIds.length > 0) {
            urlParams.set('ids', selectedIds.join(','));
        }

        window.location.href = 'export_excel.php?' + urlParams.toString();
            }
        </script>

    </div>

    <div class="table-container">
        <form id="filterForm" method="GET">
        <input type="hidden" name="year" value="<?= $selected_year ?>">
        
        <table>
            <thead>
                <tr class="main-headers">
                    <th style="width:30px;"><input type="checkbox" id="selectAll"></th>
                    <th>NO.</th>
                    <th>USER</th>
                    <th>STATUS</th>
                    <th>TYPE</th>
                    <th>PRODUCT</th>
                    <th>INDUSTRY</th>
                    <th>AREA</th>
                    <th>Company</th>
                    <?php 
                    $months = ["JAN","FEB","MAR","APR","MAY","JUN","JUL","AUG","SEP","OCT","NOV","DEC"];
                    foreach($months as $m) echo "<th style='width:75px;'>$m</th>";
                    ?>
                </tr>
                <tr class="filter-row">
                    <td></td><td></td>
                    <td>
                    <select name="user_id" onchange="this.form.submit()">
                    <option value="">All</option>
                     <?php 
                        $usrs = $pdo->query("SELECT * FROM users")->fetchAll();
                    foreach($usrs as $u) {
                        $sel = (isset($_GET['user_id']) && $_GET['user_id'] == $u['id']) ? 'selected' : '';
                        echo "<option value='{$u['id']}' $sel>{$u['name']}</option>";
                        }
                            ?>
                        </select>
                    </td>
                    <td>
                        <select name="status_id" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php 
                            $st = $pdo->query("SELECT * FROM statuses")->fetchAll();
                            foreach($st as $row) echo "<option value='{$row['id']}' ".((isset($_GET['status_id']) && $_GET['status_id']==$row['id'])?'selected':'').">{$row['status_name']}</option>";
                            ?>
                        </select>
                    </td>
                    <td>
                        <select name="type_id" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php 
                            $ty = $pdo->query("SELECT * FROM type")->fetchAll();
                            foreach($ty as $row) echo "<option value='{$row['id']}' ".((isset($_GET['type_id']) && $_GET['type_id']==$row['id'])?'selected':'').">{$row['Type_name']}</option>";
                            ?>
                        </select>
                    </td>
                    <td>
                        <select name="product_id" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php 
                            $pr = $pdo->query("SELECT * FROM products")->fetchAll();
                            foreach($pr as $row) echo "<option value='{$row['id']}' ".((isset($_GET['product_id']) && $_GET['product_id']==$row['id'])?'selected':'').">{$row['product_name']}</option>";
                            ?>
                        </select>
                    </td>
                    <td>
                        <select name="industry" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php 
                            $in = $pdo->query("SELECT * FROM industry")->fetchAll();
                            foreach($in as $row) echo "<option value='{$row['id']}' ".((isset($_GET['industry']) && $_GET['industry']==$row['id'])?'selected':'').">{$row['Industry_name']}</option>";
                            ?>
                        </select>
                    </td>
                    <td>
                        <select name="area" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php 
                            $ar = $pdo->query("SELECT * FROM area")->fetchAll();
                            foreach($ar as $row) echo "<option value='{$row['id']}' ".((isset($_GET['area']) && $_GET['area']==$row['id'])?'selected':'').">{$row['Area_name']}</option>";
                            ?>
                        </select>
                    </td>
                    <td colspan="13"></td>
                </tr>
            </thead>
            <tbody>
                <?php if(count($companies) > 0): $no = 1; ?>
                    <?php foreach($companies as $c): ?>
                    <tr>
                        <td><input type="checkbox" class="company-checkbox" value="<?= $c['id'] ?>"></td>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($c['user_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['status_name'] ?? 'Raw') ?></td>
                        <td><?= htmlspecialchars($c['Type_name'] ?? 'A1') ?></td>
                        <td><?= htmlspecialchars($c['product_name'] ?? 'Billboard') ?></td>
                        <td><?= htmlspecialchars($c['Industry_name'] ?? 'Medical') ?></td>
                        <td><?= htmlspecialchars($c['Area_name'] ?? '-') ?></td>
                        <td style="text-align:left;">
                            <a href="company_info.php?id=<?= $c['id'] ?>" style="color:#4a90e2; text-decoration:none; font-weight:bold;">
                            <?= htmlspecialchars($c['company_name']) ?>
                            </a>
                        </td>
                        <?php 
                        for($m=1; $m<=12; $m++) {
                            echo "<td>";
                            if (isset($task_map[$c['id']][$m])) {
                                $t_data = $task_map[$c['id']][$m];
                                $color_class = 'badge-green'; 
                                if ($m == 4 && in_array($c['company_name'], ['3S Home Living', '7-Eleven Malaysia Sdn Bhd', '99 Speed Mart HQ'])) {
                                    $color_class = 'badge-red';
                                }
                                echo "<div class='task-badge $color_class'>";
                                echo "<span class='badge-date'>{$t_data['date']}</span><br>";
                                echo "<span class='badge-info'>{$t_data['type']} - {$t_data['remark']}</span>";
                                echo "</div>";
                            }
                            echo "</td>";
                        }
                        ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </form>
    </div>
</body>
</html>
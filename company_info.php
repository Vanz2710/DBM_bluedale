<?php
session_start();
// Database Connection
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Get Company ID from URL
$company_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. Fetch Company Details with joins for names
$sql = "SELECT c.*, i.Industry_name, t.Type_name, a.Area_name, s.status_name, u.name as user_name
        FROM companies c 
        LEFT JOIN industry i ON c.industry = i.id 
        LEFT JOIN type t ON c.type_id = t.id 
        LEFT JOIN area a ON c.area = a.id 
        LEFT JOIN statuses s ON c.status_id = s.id 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.id = $company_id";

$res = $conn->query($sql);
$company = $res->fetch_assoc();

if (!$company) { die("Company not found."); }

// 2. Fetch PICs for this company
$pics = $conn->query("SELECT * FROM company_pics WHERE company_id = $company_id");

$current_page = 'summary.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Information - <?= htmlspecialchars($company['company_name']) ?></title>
    <link rel="stylesheet" href="company_info.css">
</head>
<body>

<!-- Navbar (Keeping your original logic/style) -->
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

<div class="main-card">
    
    <!-- SECTION: COMPANY INFORMATION -->
    <div class="header-bar">COMPANY INFORMATION</div>
    
    <div class="info-flex-container">
        <!-- Data Labels and Values -->
        <div class="info-grid">
            <div class="label">NAME</div><div class="value"><?= strtoupper($company['company_name']) ?></div>
            <div class="label">INDUSTRY</div><div class="value"><?= strtoupper($company['Industry_name'] ?? 'N/A') ?></div>
            <div class="label">TYPE</div><div class="value"><?= strtoupper($company['Type_name'] ?? 'N/A') ?></div>
            <div class="label">AREA</div><div class="value"><?= strtoupper($company['Area_name'] ?? 'N/A') ?></div>
            <div class="label">ADDRESS</div><div class="value"><?= strtoupper($company['address'] ?? '') ?></div>
            <div class="label">STATUS</div><div class="value"><?= strtoupper($company['status_name'] ?? 'RAW') ?></div>
            <div class="label">USER</div><div class="value"><?= strtoupper($company['user_name'] ?? 'UNASSIGNED') ?></div>
            <div class="label">HISTORY</div>
            <div class="value">
                <a href="history.php?id=<?= $company_id ?>">
                    <img src="historybutton.png" class="icon-folder" alt="History">
                </a>
            </div>
        </div>

        <!-- Action Buttons Right Side -->
        <div class="info-actions">
            <a href="task.php?id=<?= $company_id ?>">
                <img src="ToDoButton.png" class="btn-todo" alt="To Do">
            </a>
            <!-- NOTE: This button goes to a future page to be created later -->
            <a href="edit_company.php?id=<?= $company_id ?>">
                <img src="editYellowButton.png" class="icon-yellow-edit" alt="Edit Company">
            </a>
        </div>
    </div>

    <!-- SECTION: PIC -->
    <div class="header-bar">PIC</div>
    
    <div class="pic-section-wrapper">
        <!-- Add New PIC Button -->
        <div class="add-btn-container">
            <a href="add_pic.php?company_id=<?= $company_id ?>">
                <img src="AddNewButton.png" class="btn-add-new" alt="Add New PIC">
            </a>
        </div>

        <?php 
        $count = 1;
        while($p = $pics->fetch_assoc()): 
        ?>
        <div class="pic-row">
            <!-- Sequence Number Circle -->
            <div class="pic-circle"><?= $count++ ?></div>
            
            <!-- PIC Data Columns -->
            <div class="pic-details">
                <div class="pic-item"><strong>NAME:</strong><span><?= strtoupper($p['name']) ?></span></div>
                <div class="pic-item"><strong>EMAIL:</strong><span><?= strtoupper($p['email']) ?></span></div>
                <div class="pic-item"><strong>PHONE NUMBER:</strong><span><?= $p['phone_number'] ?></span></div>
                <div class="pic-item"><strong>OFFICE NUMBER:</strong><span><?= $p['office_number'] ?></span></div>
            </div>

            <!-- PIC Edit/Delete Icons -->
            <div class="pic-actions">
                <a href="edit_pic.php?id=<?= $p['id'] ?>">
                    <img src="EditContact.png" class="icon-pic-action" alt="Edit">
                </a>
                <img src="DeleteButton.png" class="icon-pic-action" alt="Delete" 
                     onclick="confirmDelete(<?= $p['id'] ?>, <?= $company_id ?>)" style="cursor:pointer;">
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
function confirmDelete(picId, companyId) {
    if(confirm("Are you sure to delete the pic?")) {
        window.location.href = "delete_pic.php?id=" + picId + "&company_id=" + companyId;
    }
}
</script>

</body>
</html>
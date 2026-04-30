<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

// Get the PIC ID from the URL
$pic_id = $_GET['id'];

// Fetch the existing data for this PIC
$res = $conn->query("SELECT * FROM company_pics WHERE id = $pic_id");
$p = $res->fetch_assoc();

$current_page = 'summary.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit PIC Information</title>
    <link rel="stylesheet" href="edit_pic.css">
</head>
<body>

    <!-- Navbar -->
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
        <div class="edit-card">
            <div class="blue-header">EDIT PIC INFORMATION</div>

            <div class="form-body">
                <form action="update_pic.php" method="POST">
                    <!-- Hidden IDs so the system knows what to update -->
                    <input type="hidden" name="pic_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="company_id" value="<?= $p['company_id'] ?>">

                    <div class="input-grid">
                        <div class="input-group">
                            <label>NAME</label>
                            <input type="text" value="<?php echo $row['name'] ?? ''; ?>">
                        </div>

                        <div class="input-group">
                            <label>EMAIL</label>
                            <input type="text" value="<?php echo $row['email'] ?? ''; ?>">
                        </div>

                        <div class="input-group">
                            <label>PHONE NUMBER</label>
                            <input type="text" value="<?php echo $row['phone_number'] ?? ''; ?>">
                        </div>

                        <div class="input-group">
                            <label>OFFICE NUMBER</label>
                            <input type="text" value="<?php echo $row['office_number'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="btn-wrapper">
                        <button type="submit" class="change-btn">
                            <img src="ChangeButton.png" alt="Change">
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
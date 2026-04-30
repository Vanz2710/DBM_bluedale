<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn = new mysqli("localhost", "root", "", "dbm_bluedale");
$company_id = $_GET['company_id'];

// Get company name for the title
$res = $conn->query("SELECT company_name FROM companies WHERE id = $company_id");
$company = $res->fetch_assoc();

$current_page = 'summary.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New PIC</title>
    <!-- Using your same CSS file -->
    <link rel="stylesheet" href="addData2_.css">
</head>
<body>

     <nav class="navbar">
        <div class="logo"><img src="logo.png" alt="Logo"></div>
        
            <ul class="nav-links">
            <li><a href="summary.php" class="<?= ($current_page == 'summary.php') ? 'active' : '' ?>">summary</a></li>
            <li><a href="listPage.php" class="<?= ($current_page == 'list.php') ? 'active' : '' ?>">List</a></li>
            <li><a href="addData.php" class="<?= ($current_page == 'addData.php') ? 'active' : '' ?>">Add Data</a></li>
            <li><a href="import.php" class="<?= ($current_page == 'import.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="project.php" class="<?= ($current_page == 'project.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="performance.php" class="<?= ($current_page == 'performance.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="bbtp.php" class="<?= ($current_page == 'bbtp.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="tutorial.php" class="<?= ($current_page == 'tutorial.php') ? 'active' : '' ?>">test</a></li>
            <li><a href="admin_features.php" class="<?= ($current_page == 'media1.php') ? 'active' : '' ?>">admin</a></li>
        </ul>
    </nav>

    <div class="main-container">
        <div class="white-card">
            <div class="blue-title-bar">ADD NEW PIC</div>

            <div class="form-content">
                <h2 class="section-title">For: <?= strtoupper($company['company_name']) ?></h2>

                <form action="save_new_pic.php" method="POST">
                    <!-- Hidden ID to know which company to attach to -->
                    <input type="hidden" name="company_id" value="<?= $company_id ?>">

                    <div class="input-group">
                        <label>this fields is required*</label>
                        <input type="text" name="phone_number" placeholder="contact number" required>
                    </div>

                    <div class="input-group">
                        <label>this fields is required*</label>
                        <input type="email" name="email" placeholder="enter email" required>
                    </div>

                    <div class="input-group">
                        <label>this fields is required*</label>
                        <input type="text" name="name" placeholder="enter company pic name" required>
                    </div>

                    <div class="input-group">
                        <input type="text" name="office_number" placeholder="enter office number">
                    </div>

                    <div class="button-container">
                        <button type="submit" class="register-btn">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
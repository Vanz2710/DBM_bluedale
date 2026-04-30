<?php
session_start();
$current_page = 'addData.php'; // Keep "add data" highlighted
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Company Details - Bluedale</title>
    <link rel="stylesheet" href="addData.css">
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

<div class="main-card">
    <div class="blue-header">ADD DATA</div>
    
    <div class="form-body">
        <h2 class="section-title">Company Info</h2>
        
        <form action="finalize_registration.php" method="POST">
            
            <div class="input-group">
                <div class="required-label">this fields is required*</div>
                <input type="text" name="phone_number" placeholder="contact number" required>
            </div>

            <div class="input-group">
                <div class="required-label">this fields is required*</div>
                <input type="email" name="email" placeholder="enter email" required>
            </div>

            <div class="input-group">
                <div class="required-label">this fields is required*</div>
                <input type="text" name="contact_person" placeholder="enter company pic name" required>
            </div>

            <div class="input-group">
                <input type="text" name="office_number" placeholder="enter office number">
            </div>

            <div class="btn-wrapper" style="text-align: left; margin-top: 40px;">
                <button type="submit" class="next-submit-btn">
                    <img src="register.png" alt="Register" style="width: 150px;">
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
<?php
session_start();
// Security check: Redirect if they didn't come from the first page
if (!isset($_POST['company_name'])) {
    header("Location: addData.php");
    exit();
}

$current_page = 'addData.php'; // Keep "add data" highlighted in navbar
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Data - Contact Info</title>
    <link rel="stylesheet" href="addData2_.css">
</head>
<body>

    <!-- Navbar matching your screenshot -->
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
        <div class="white-card">
            <!-- Blue Header Bar -->
            <div class="blue-title-bar">
                ADD DATA
            </div>

            <div class="form-content">
                <h2 class="section-title">Company Info</h2>

                <form action="process_data.php" method="POST">
                    
                    <!-- HIDDEN FIELDS: Carrying data from your first addData.php -->
                    <input type="hidden" name="company_name" value="<?= htmlspecialchars($_POST['company_name']) ?>">
                    <input type="hidden" name="industry_id" value="<?= $_POST['industry_id'] ?>">
                    <input type="hidden" name="type_id" value="<?= $_POST['type_id'] ?>">
                    <input type="hidden" name="product_id" value="<?= $_POST['product_id'] ?>">
                    <input type="hidden" name="area_id" value="<?= $_POST['area_id'] ?>">
                    <input type="hidden" name="address" value="<?= htmlspecialchars($_POST['address']) ?>">

                    <!-- Visible Input Fields -->
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
                        <input type="text" name="contact_person" placeholder="enter company pic name" required>
                    </div>

                    <div class="input-group">
                        <input type="text" name="office_number" placeholder="enter office number">
                    </div>
                     
                    <div class="input-group">
                    <label>date created (select date)</label>
                    <input type="date" name="created_at" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <!-- Register Button -->
                    <div class="button-container">
                        <button type="submit" class="register-btn">Register</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

</body>
</html>
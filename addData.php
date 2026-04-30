<?php
session_start(); // 1. Start the session
// 2. Redirect to login if user isn't logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$current_page = basename($_SERVER['PHP_SELF']); 
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

// Fetch data for dropdowns
$industries = $conn->query("SELECT * FROM industry");
$types = $conn->query("SELECT * FROM type");
$products = $conn->query("SELECT * FROM products");
$areas = $conn->query("SELECT * FROM area");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Data - Bluedale</title>
    <link rel="stylesheet" href="addData.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        
        <form action="addData2.php" method="POST">
            
            <div class="input-group">
                <div class="required-label" id="nameLabel">this field is required*</div>
                <input type="text" name="company_name" id="company_name" placeholder="enter company name" required>
            </div>

            <div class="input-group">
                <div class="required-label">this field is required*</div>
                <select name="industry_id" required>
                    <option value="">select company industry</option>
                    <?php while($row = $industries->fetch_assoc()): ?>
                        <option value="<?=$row['id']?>"><?=$row['Industry_name']?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="input-group">
                <div class="required-label">this field is required*</div>
                <select name="type_id" required>
                    <option value="">select type</option>
                    <?php while($row = $types->fetch_assoc()): ?>
                        <option value="<?=$row['id']?>"><?=$row['Type_name']?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="input-group">
                <div class="required-label">this field is required*</div>
                <select name="product_id" required>
                    <option value="">select product</option>
                    <?php while($row = $products->fetch_assoc()): ?>
                        <option value="<?=$row['id']?>"><?=$row['product_name']?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="input-group">
                <div class="required-label">this field is required*</div>
                <select name="area_id" required>
                    <option value="">select company area</option>
                    <?php while($row = $areas->fetch_assoc()): ?>
                        <option value="<?=$row['id']?>"><?=$row['Area_name']?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <textarea name="address" placeholder="enter address"></textarea>

            <div class="btn-wrapper">
                <button type="submit" class="next-submit-btn" id="submitBtn">
                    <img src="nextButton.png" alt="next">
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#company_name').on('input', function() {
        var name = $(this).val();
        if(name.length > 0) {
            $.ajax({
                url: 'check_duplicate.php',
                method: 'POST',
                data: {company_name: name},
                success: function(response) {
                    if(response == "exists") {
                        $('#nameLabel').text("this company already existed").addClass('error-text');
                        $('#submitBtn').addClass('disabled-btn').attr('disabled', true);
                    } else {
                        $('#nameLabel').text("this field is required*").removeClass('error-text');
                        $('#submitBtn').removeClass('disabled-btn').attr('disabled', false);
                    }
                }
            });
        }
    });
});
</script>

</body>
</html>
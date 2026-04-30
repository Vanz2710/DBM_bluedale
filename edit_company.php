<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$current_page = 'listPage.php';
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

// 1. Get Company ID
$company_id = $_GET['id'] ?? null;
if (!$company_id) { die("Invalid ID"); }

// 2. Handle Update Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = $_POST['company_name'];
    $status   = $_POST['status_id'];
    $product  = $_POST['product_id'];
    $type     = $_POST['type_id'];
    $industry = $_POST['industry_id'];
    $area     = $_POST['area_id'];
    $address  = $_POST['address'];

    $sql = "UPDATE companies SET 
            company_name = ?, status_id = ?, product_id = ?, 
            type_id = ?, industry = ?, area = ?, address = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiiiisi", $name, $status, $product, $type, $industry, $area, $address, $company_id);
    
    if ($stmt->execute()) {
        // REDIRECT back to this same page with the company ID and a success message
        header("Location: edit_company.php?id=" . $company_id . "&msg=updated");
        exit();
    }
}

// 3. Fetch Company Data
$res = $conn->query("SELECT * FROM companies WHERE id = $company_id");
$company = $res->fetch_assoc();

// Helper to fetch dropdowns
function getOptions($conn, $table, $valField, $nameField) {
    return $conn->query("SELECT $valField, $nameField FROM $table");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Company Information</title>
    <link rel="stylesheet" href="edit_company.css">
</head>
<body>

<!-- SUCCESS POP-UP MESSAGE -->
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
    <script>
        alert("the company have been update.");
    </script>
<?php endif; ?>

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

<div class="container">
    <div class="card">
        <div class="header-banner">EDIT COMPANY INFORMATION</div>

        <form method="POST">
            <div class="form-grid">
                
                <div class="input-group">
                    <label>Select Status</label>
                    <select name="status_id">
                        <?php 
                        $opts = getOptions($conn, 'statuses', 'id', 'status_name');
                        while($o = $opts->fetch_assoc()) {
                            $selected = ($company['status_id'] == $o['id']) ? 'selected' : '';
                            echo "<option value='{$o['id']}' $selected>{$o['status_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Select Category</label>
                    <select name="product_id">
                        <?php 
                        $opts = getOptions($conn, 'products', 'id', 'product_name');
                        while($o = $opts->fetch_assoc()) {
                            $selected = ($company['product_id'] == $o['id']) ? 'selected' : '';
                            echo "<option value='{$o['id']}' $selected>{$o['product_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Select Type</label>
                    <select name="type_id">
                        <?php 
                        $opts = getOptions($conn, 'type', 'id', 'Type_name');
                        while($o = $opts->fetch_assoc()) {
                            $selected = ($company['type_id'] == $o['id']) ? 'selected' : '';
                            echo "<option value='{$o['id']}' $selected>{$o['Type_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Select Industry</label>
                    <select name="industry_id">
                        <?php 
                        $opts = getOptions($conn, 'industry', 'id', 'Industry_name');
                        while($o = $opts->fetch_assoc()) {
                            $selected = ($company['industry'] == $o['id']) ? 'selected' : '';
                            echo "<option value='{$o['id']}' $selected>{$o['Industry_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Select Area</label>
                    <select name="area_id">
                        <?php 
                        $opts = getOptions($conn, 'area', 'id', 'Area_name');
                        while($o = $opts->fetch_assoc()) {
                            $selected = ($company['area'] == $o['id']) ? 'selected' : '';
                            echo "<option value='{$o['id']}' $selected>{$o['Area_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div><!-- Spacer --></div>

                <div class="input-group full-width">
                    <label>Company Name</label>
                    <input type="text" name="company_name" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required>
                </div>

                <div class="input-group full-width">
                    <label>Address</label>
                    <textarea name="address"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                </div>

            </div>

            <div class="btn-container">
                <button type="submit" class="btn-done">change</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
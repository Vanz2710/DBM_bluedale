<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category = $_POST['category']; // e.g., 'type', 'products'
    $new_value = trim($_POST['new_value']);

    if (!empty($category) && !empty($new_value)) {
        // Mapping category to table names and column names
        $table_map = [
            'type'      => 'Type_name',
            'products'  => 'product_name',
            'industry'  => 'Industry_name',
            'area'      => 'Area_name',
            'statuses'  => 'status_name'
        ];

        if (array_key_exists($category, $table_map)) {
            $column = $table_map[$category];
            
            // Check if it already exists to avoid duplicates
            $check = $conn->prepare("SELECT id FROM $category WHERE $column = ?");
            $check->bind_param("s", $new_value);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "<div class='alert error'>This $category already exists!</div>";
            } else {
                // Insert new feature
                $stmt = $conn->prepare("INSERT INTO $category ($column) VALUES (?)");
                $stmt->bind_param("s", $new_value);
                if ($stmt->execute()) {
                    $message = "<div class='alert success'>New $category added successfully!</div>";
                }
                $stmt->close();
            }
            $check->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Features - Bluedale</title>
    <link rel="stylesheet" href="admin_features.css">
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

    <div class="main-container">
        <div class="white-card">
            <div class="blue-title-bar">ADMIN FEATURES</div>
            
            <div class="form-content">
                <h3>NEW FEATURES</h3>
                <?= $message ?>
                <form method="POST">
                    <div class="input-group">
                        <select name="category" id="featureSelect" required onchange="updatePlaceholder()">
                            <option value="" disabled selected>SELECT FEATURES</option>
                            <option value="type">Type</option>
                            <option value="products">Product</option>
                            <option value="industry">Industry</option>
                            <option value="area">Area</option>
                            <option value="statuses">Status</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <input type="text" name="new_value" id="valueInput" placeholder="ENTER NEW PRODUCT" required>
                    </div>

                    <button type="submit" class="register-btn">Register</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updatePlaceholder() {
            const select = document.getElementById('featureSelect');
            const input = document.getElementById('valueInput');
            const selectedText = select.options[select.selectedIndex].text.toUpperCase();
            input.placeholder = "ENTER NEW " + selectedText;
        }
    </script>
</body>
</html>
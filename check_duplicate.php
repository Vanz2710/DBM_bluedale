<?php
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

if(isset($_POST['company_name'])) {
    $name = $conn->real_escape_string($_POST['company_name']);
    $check = $conn->query("SELECT id FROM companies WHERE company_name = '$name'");
    
    if($check->num_rows > 0) {
        echo "exists";
    } else {
        echo "available";
    }
}
?>
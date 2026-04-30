<?php
session_start();
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

if(isset($_GET['id'])) {
    $id = $_GET['id'];
    $company_id = $_GET['company_id'];
    
    $conn->query("DELETE FROM company_pics WHERE id = $id");
    header("Location: company_info.php?id=$company_id");
}
?>
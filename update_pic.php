<?php
session_start();
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id    = $_POST['pic_id'];
    $cid   = $_POST['company_id']; // For redirection
    $name  = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone_number'];
    $off   = $_POST['office_number'];

    // Update Query
    $stmt = $conn->prepare("UPDATE company_pics SET name=?, email=?, phone_number=?, office_number=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $email, $phone, $off, $id);

    if ($stmt->execute()) {
        // Go back to the company info page you came from
        header("Location: company_info.php?id=$cid");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
<?php
session_start();
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cid    = $_POST['company_id'];
    $name   = $_POST['name'];
    $email  = $_POST['email'];
    $phone  = $_POST['phone_number'];
    $office = $_POST['office_number'];

    // Insert into company_pics
    $stmt = $conn->prepare("INSERT INTO company_pics (company_id, name, email, phone_number, office_number) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $cid, $name, $email, $phone, $office);

    if ($stmt->execute()) {
        // Redirect back to company_info.php
        header("Location: company_info.php?id=$cid");
    } else {
        echo "Error: " . $conn->error;
    }
    $stmt->close();
}
$conn->close();
?>
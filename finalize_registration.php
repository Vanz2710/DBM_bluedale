<?php
session_start();
$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from Session (Page 1)
    $p1 = $_SESSION['temp_company'];
    
    // Get data from Form (Page 2)
    $phone = $_POST['phone_number'];
    $email = $_POST['email'];
    $pic = $_POST['contact_person'];
    $office = $_POST['office_number'];

    $sql = "INSERT INTO companies (company_name, industry, type_id, product_id, area, address, contact_person, phone_number, office_number, email) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiiisssss", 
        $p1['name'], $p1['industry'], $p1['type'], $p1['product'], $p1['area'], 
        $p1['address'], $pic, $phone, $office, $email
    );

    if ($stmt->execute()) {
        // Clear session and redirect
        unset($_SESSION['temp_company']);
        echo "<script>alert('Company Registered Successfully!'); window.location.href='summary.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
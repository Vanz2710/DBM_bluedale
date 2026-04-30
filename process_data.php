<?php
session_start();
// Security check: Redirect if they are not logged in
if (!isset($_SESSION['user_id'])) { 
    exit("Unauthorized access"); 
}

$conn = new mysqli("localhost", "root", "", "dbm_bluedale");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. User ID from Session (The CS/User assigned to this company)
    $user_id = $_SESSION['user_id']; 

    // 2. Data from Page 1 (Hidden Fields)
    $company_name = $_POST['company_name'];
    $industry     = $_POST['industry_id'];
    $type_id      = $_POST['type_id'];
    $product_id   = $_POST['product_id'];
    $area         = $_POST['area_id'];
    $address      = $_POST['address'];

    // 3. Data from Page 2 (Visible Fields)
    $phone_number   = $_POST['phone_number'];
    $email          = $_POST['email'];
    $contact_person = $_POST['contact_person'];
    $office_number  = $_POST['office_number'];
    $status_id      = 1; // Default to "Raw" or your first status ID
    
    // 4. Capture the Created Date from the form
    $created_at     = $_POST['created_at']; 

    // --- STEP A: INSERT INTO COMPANIES TABLE ---
    $sql_company = "INSERT INTO companies (company_name, user_id, industry, type_id, product_id, area, address, contact_person, phone_number, office_number, email, status_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql_company);
    
    // Bind Param Types: 13 placeholders
    // s (string), i (int), i, i, i, i, s, s, s, s, s, i, s
    $stmt->bind_param("siiiiisssssis", 
        $company_name, 
        $user_id, 
        $industry, 
        $type_id, 
        $product_id, 
        $area, 
        $address, 
        $contact_person, 
        $phone_number, 
        $office_number, 
        $email, 
        $status_id, 
        $created_at
    );

    if ($stmt->execute()) {
        $new_id = $conn->insert_id; // Get the ID of the company we just created

        // --- STEP B: INSERT INTO COMPANY_PICS TABLE ---
        $stmt_pic = $conn->prepare("INSERT INTO company_pics (company_id, name, email, phone_number, office_number) VALUES (?, ?, ?, ?, ?)");
        $stmt_pic->bind_param("issss", $new_id, $contact_person, $email, $phone_number, $office_number);
        $stmt_pic->execute();
        $stmt_pic->close();

        // --- STEP C: REDIRECT TO LIST PAGE ---
        // We redirect to listPage.php and pass the created_at date in the URL.
        // This ensures the user sees the data they just keyed in for that day.
        header("Location: listPage.php?filter_date=" . $created_at . "&success=1");
        exit();

    } else {
        echo "Database Error: " . $stmt->error;
    }
    
    $stmt->close();
}

$conn->close();
?>
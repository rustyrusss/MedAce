<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $email     = trim($_POST['email']);
    $username  = trim($_POST['username']);
    $year      = isset($_POST['year']) ? trim($_POST['year']) : null;
    $section   = isset($_POST['section']) ? trim($_POST['section']) : null;  
    $role      = trim($_POST['role']); // ✅ now role comes from dropdown
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm'];

    // check empty (role decides required fields)
    if (empty($firstname) || empty($lastname) || empty($email) || empty($username) || empty($password) || empty($role)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: ../public/register.php");
        exit;
    }

    // if student, year and section must be filled
    if ($role === "student" && (empty($year) || empty($section))) {
        $_SESSION['error'] = "Year and Section are required for students.";
        header("Location: ../public/register.php");
        exit;
    }

    // check password match
    if ($password !== $confirm) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: ../public/register.php");
        exit;
    }

    // check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Email already registered.";
        header("Location: ../public/register.php");
        exit;
    }

    // check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Username already taken.";
        header("Location: ../public/register.php");
        exit;
    }

    // hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // ✅ Professors require dean approval
    $status = ($role === "professor") ? "pending" : "approved";

    // insert user
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, email, username, `year`, section, `password`, role, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $firstname, 
        $lastname, 
        $email, 
        $username, 
        $role === "student" ? $year : null, 
        $role === "student" ? $section : null, 
        $hashedPassword, 
        $role, 
        $status
    ]);

    if ($role === "professor") {
        $_SESSION['success'] = "Your account is pending dean approval.";
    } else {
        $_SESSION['success'] = "Registration successful! Please log in.";
    }

    header("Location: ../public/index.php");
    exit;
}
?>

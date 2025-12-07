<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db_conn.php';

function returnWithError($message) {
    $_SESSION['error'] = $message;
    $_SESSION['old'] = $_POST; // Save old form data
    header("Location: ../public/register.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize inputs
    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $email     = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $gender    = trim($_POST['gender']);
    $username  = trim($_POST['username']);
    $role      = trim($_POST['role']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm'];

    // Convert empty strings to null for optional fields
    $year      = !empty($_POST['year']) ? $_POST['year'] : null;
    $section   = !empty($_POST['section']) ? $_POST['section'] : null;
    $student_id = !empty($_POST['student_id']) ? trim($_POST['student_id']) : null;

    // Required fields validation
    if (empty($firstname) || empty($lastname) || empty($email) || empty($gender) || empty($username) || empty($password) || empty($role)) {
        returnWithError("All fields are required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        returnWithError("Invalid email format.");
    }

    // Validate gender
    if (!in_array($gender, ['Male', 'Female', 'Other'])) {
        returnWithError("Please select a valid gender.");
    }

    // Student-specific validation
    if ($role === "student") {
        if (empty($year) || empty($section) || empty($student_id)) {
            returnWithError("Year, Section, and Student ID are required for students.");
        }
    } else {
        // For professors, set student_id to empty string instead of null
        $student_id = '';
        $year = null;
        $section = null;
    }

    // Username validation
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        returnWithError("Username must be 3–20 characters and contain only letters, numbers, and underscores.");
    }

    // Password validation
    if ($password !== $confirm) {
        returnWithError("Passwords do not match.");
    }

    if (strlen($password) < 8) returnWithError("Password must be at least 8 characters long.");
    if (preg_match('/[^a-zA-Z0-9]/', $password)) returnWithError("Password must not contain symbols.");
    if (!preg_match('/[A-Z]/', $password)) returnWithError("Password must contain at least one uppercase letter.");
    if (!preg_match('/[a-z]/', $password)) returnWithError("Password must contain at least one lowercase letter.");
    if (!preg_match('/[0-9]/', $password)) returnWithError("Password must contain at least one number.");

    $commonPasswords = ['password','12345678','qwerty123','password123','admin123'];
    if (in_array(strtolower($password), $commonPasswords)) {
        returnWithError("Password is too common. Choose a stronger password.");
    }

    // Check duplicates
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) returnWithError("This email is already registered.");

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) returnWithError("This username is already taken.");

    // Only check student_id if user is a student and student_id is provided
    if ($role === "student" && !empty($student_id)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
        $stmt->execute([$student_id]);
        if ($stmt->fetch()) returnWithError("This Student ID is already registered.");
    }

    // Process registration
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $status = "pending";

    try {
        // Database columns: firstname, lastname, email, gender, password, role, year, section, student_id, status, created_at
        $stmt = $conn->prepare("
            INSERT INTO users 
            (firstname, lastname, email, username, gender, password, role, year, section, student_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $firstname,
            $lastname,
            $email,
            $username,
            $gender,
            $hashedPassword,
            $role,
            $year,       // null for professors
            $section,    // null for professors
            $student_id, // empty string for professors, actual ID for students
            $status
        ]);

        $_SESSION['success'] = $role === "student"
            ? "Registration successful! Your account is pending dean approval."
            : "Registration successful! Your professor account is pending dean approval.";

        unset($_SESSION['old']); // clear restored data
        
        header("Location: ../public/register.php");
        exit;

    } catch (PDOException $e) {
        // Log error for debugging
        error_log("Registration error: " . $e->getMessage());
        returnWithError("Registration failed: " . $e->getMessage());
    }

} else {
    header("Location: ../public/register.php");
    exit;
}
?>
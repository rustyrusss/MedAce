<?php
session_start();
require_once '../config/db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['email']); // can be username OR email
    $password = $_POST['password'];

    if (empty($login) || empty($password)) {
        $_SESSION['error'] = "Please enter your email/username and password.";
        header("Location: ../public/index.php");
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT id, firstname, lastname, role, password 
                                FROM users 
                                WHERE email = ? OR username = ? 
                                LIMIT 1");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname']  = $user['lastname'];
            $_SESSION['role']      = $user['role'];

            switch ($user['role']) {
                case 'student':
                    header("Location: ../member/dashboard.php");
                    break;
                case 'professor': 
                    header("Location: ../professor/dashboard.php");
                    break;
                case 'dean':
                    header("Location: ../admin/dashboard.php");
                    break;
                default:
                    $_SESSION['error'] = "Unauthorized role.";
                    header("Location: ../public/index.php");
                    break;
            }
            exit;
        } else {
            $_SESSION['error'] = "Invalid email/username or password.";
            header("Location: ../public/index.php");
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "Something went wrong. Please try again.";
        header("Location: ../public/index.php");
        exit;
    }
}

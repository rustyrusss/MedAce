<?php
session_start();
require_once '../config/db_conn.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../public/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $userId = (int) $_SESSION['user_id'];
    $file   = $_FILES['profile_pic'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_error'] = "File upload error. Please try again.";
        header("Location: ../member/profile_edit.php");
        exit();
    }

    // Validate file size (max 2MB)
    $maxFileSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxFileSize) {
        $_SESSION['upload_error'] = "File size exceeds 2MB limit.";
        header("Location: ../member/profile_edit.php");
        exit();
    }

    // Validate file type (JPEG or PNG only)
    $allowedMimeTypes = ['image/jpeg', 'image/png'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedMimeTypes)) {
        $_SESSION['upload_error'] = "Invalid file type. Only JPG and PNG allowed.";
        header("Location: ../member/profile_edit.php");
        exit();
    }

    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/../uploads/profile_pics/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            $_SESSION['upload_error'] = "Unable to create upload directory.";
            header("Location: ../member/profile_edit.php");
            exit();
        }
    }

    // Generate unique filename
    $ext = $mimeType === 'image/png' ? '.png' : '.jpg';
    $newFileName = 'profile_' . $userId . '_' . time() . $ext;
    $destination = $uploadDir . $newFileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $_SESSION['upload_error'] = "Failed to save uploaded file.";
        header("Location: ../member/profile_edit.php");
        exit();
    }

    // Path saved in DB (web-accessible relative path)
    $profilePicPath = 'uploads/profile_pics/' . $newFileName;

    // --------- Database update (supports both PDO and mysqli) ----------
    $dbUpdated = false;
    if ($conn instanceof PDO) {
        try {
            $sql = "UPDATE users SET profile_pic = :profile_pic WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $dbUpdated = $stmt->execute(['profile_pic' => $profilePicPath, 'id' => $userId]);
            if (!$dbUpdated) {
                // optional: fetch error info
                $err = $stmt->errorInfo();
                $_SESSION['upload_error'] = "Database update failed: " . ($err[2] ?? 'unknown PDO error');
            }
        } catch (PDOException $e) {
            $_SESSION['upload_error'] = "Database error: " . $e->getMessage();
        }
    } elseif ($conn instanceof mysqli) {
        $sql = "UPDATE users SET profile_pic = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $_SESSION['upload_error'] = "DB prepare failed: " . $conn->error;
        } else {
            // bind_param requires variables and a type string ("s" for string, "i" for int)
            $stmt->bind_param("si", $profilePicPath, $userId);
            if ($stmt->execute()) {
                $dbUpdated = true;
            } else {
                $_SESSION['upload_error'] = "DB execute failed: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $_SESSION['upload_error'] = "Unsupported DB connection type.";
    }

    // Finalize
    if ($dbUpdated) {
        $_SESSION['upload_success'] = "Profile picture updated successfully.";
    } else {
        // If DB failed, optionally delete the moved file to avoid orphan files:
        if (file_exists($destination)) {
            @unlink($destination);
        }
        if (!isset($_SESSION['upload_error'])) {
            $_SESSION['upload_error'] = "Unknown error while updating profile picture.";
        }
    }

    header("Location: ../member/profile_edit.php");
    exit();

} else {
    // Invalid access
    header("Location: ../member/profile_edit.php");
    exit();
}

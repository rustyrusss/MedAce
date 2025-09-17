<?php
session_start();

// destroy session
session_unset();
session_destroy();

// redirect to login page
header("Location: ../public/index.php");
exit();

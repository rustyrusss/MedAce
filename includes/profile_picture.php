<?php
// includes/profile_picture.php

function getProfilePicture($student) {
    // Default avatar based on gender
    if (!empty($student['gender'])) {
        $gender = strtolower($student['gender']);
        if ($gender === "male") {
            $defaultAvatar = "../assets/img/avatar_male.png";
        } elseif ($gender === "female") {
            $defaultAvatar = "../assets/img/avatar_female.png";
        } else {
            $defaultAvatar = "../assets/img/avatar_neutral.png";
        }
    } else {
        $defaultAvatar = "../assets/img/avatar_neutral.png"; // fallback
    }

    // Final profile picture (uploaded or default avatar)
    $profilePic = !empty($student['profile_pic'])
        ? "../" . $student['profile_pic']
        : $defaultAvatar;

    return $profilePic;
}
?>

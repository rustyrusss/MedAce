<?php
function getProfilePicture($user, $basePath = "../") {
    $gender = isset($user['gender']) ? strtolower($user['gender']) : '';

    // Default avatar based on gender
    switch ($gender) {
        case "male":
            $defaultAvatar = $basePath . "assets/img/avatar_male.png";
            break;
        case "female":
            $defaultAvatar = $basePath . "assets/img/avatar_female.png";
            break;
        default:
            $defaultAvatar = $basePath . "assets/img/avatar_neutral.png";
            break;
    }

    // Use uploaded profile pic or default
    return !empty($user['profile_pic'])
        ? $basePath . $user['profile_pic']
        : $defaultAvatar;
}
?>

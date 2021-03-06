<?php

function login_init($autologouttime) {
    $resetLogin = FALSE;
    if (!isset($_SESSION["Login"])) {
        $resetLogin = TRUE;
    } else if ($autologouttime > -1) {
        $timeSinceLastAction = time() - $_SESSION["Login"]["last-action"];

        if ($timeSinceLastAction > $autologouttime) {
            $resetLogin = TRUE;
        }
    }

    if ($resetLogin) {
        login_reset();
    }

    $_SESSION["Login"]["last-action"] = time();
}

function login_privileges() {
    // 0 = no login, 1 = user, 2 = employee
    return $_SESSION["Login"]["privileges"];
}

function login_userid() {
    return $_SESSION["Login"]["id"];
}

function login_username() {
    return $_SESSION["Login"]["username"];
}

function login_reset() {
    $_SESSION["Login"] = array(
        "login" => 0,
        "privileges" => 0,
        "username" => FALSE,
        "id" => -1,
        "logged-in-since" => 0,
        "last-action" => 0
    );
}

function login_dologin($submitteddata, $getUsersForName) {
    $requirements = array("email", "password");
    foreach ($requirements as $rq) {
        if (!isset($submitteddata[$rq])) {
            return 3;
        }
    }

    $username = $submitteddata["email"];

    if (!preg_match("/^[a-zA-Z0-9.-]+(@[a-zA-Z0-9.-]+)?$/", $username)) {
        return 1;
    }

    $rawusers = $getUsersForName($username);

    $users = array();

    foreach ($rawusers as $user) {
        $users[] = $user;
    }

    if (sizeof($users) !== 1) {
        return 1;
    }

    $user = $users[0];

    //password_verify($submitteddata["password"], $user["credentials"])
    if (sha1($submitteddata["password"]) === $user["credentials"]) {
        $_SESSION["Login"]["privileges"] = $user["isEmployee"] + 1;
        $_SESSION["Login"]["login"] = TRUE;
        $_SESSION["Login"]["username"] = $username;
        $_SESSION["Login"]["id"] = $user["userid"];
        $_SESSION["Login"]["logged-in-since"] = time();

        return 0;
    }

    return 1;
}
?>

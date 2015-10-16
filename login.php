<?php

class Login {
    public function __construct($autologouttime) {
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
            $this->reset();
        }

        $_SESSION["Login"]["last-action"] = time();
    }

    public function __invoke() {
        return max($_SESSION["Login"]["privileges"]);
    }

    public function reset() {
        $_SESSION["Login"] = array(
            "login" => 0,
            "privileges" => 0,
            "username" => FALSE,
            "logged-in-since" => 0,
            "last-action" => 0
        );
    }

    public function login($submitteddata, $getUsersForName) {
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

        if (password_verify($submitteddata["password"], $user["auth"])) {
            $_SESSION["Login"]["privileges"] = $user["level"];
            $_SESSION["Login"]["login"] = TRUE;
            $_SESSION["Login"]["username"] = $username;
            $_SESSION["Login"]["logged-in-since"] = time();

            return 0;
        }

        return 1;
    }
}
?>

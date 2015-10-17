<?php

session_start();

require_once "database.php";
require_once "login.php";

$login = new Login(600); // Auto logout after ten minutes inactivity


// first char: _ means public, ! means error, u means user (login privileges 1), e means employee (p 2)
$page = "_home";

if (isset($_GET["page"])) {
    $page = "_" . $_GET["page"];
    if (strlen($page) > 2 && (substr($page, 1, 1) === "u" || substr($page, 1, 1) === "2")) {
        $page = $_GET["page"];
    }
}

// TODO: Some style, html wrapper, etc.
?>
<html><head><title>SecureBank</title><link rel="stylesheet" href="style.css" /></head><body><div class="container">
<?php

if (!Database::open()) {
    $page = "!dberror";
}

if (substr($page, 1, 1) === "u" && $login() !== 1) {
    $page = "!auth";
} else if (substr($page, 1, 1) === "e" && $login() !== 2) {
    $page = "!auth";
}

switch ($page) {
    case "_home":
        echo "<h1>Welcome to the SecureBank!</h1>";
        echo "<a href='?page=login'>Login</a> or <a href='?page=register'>register</a>.";
        break;
    case "_register":
        ?><h1>Register at the SecureBank</h1>
        <form action="?page=doregister" method="post">E-Mail<br><input type="email" name="email"><br><br>Password<br><input type="password" name="password"><br><br><input type="submit" value="Register"></form>
        <?php
        break;
    case "_login":
        ?><h1>Login at the SecureBank</h1>
        <form action="?page=dologin" method="post">E-Mail<br><input type="email" name="email"><br><br>Password<br><input type="password" name="password"><br><br><input type="submit" value="Login"></form>
        <?php
        break;
    case "_doregister":
        if (!isset($_POST["email"]) || !isset($_POST["password"])) {
            echo "<h1>Registration failed.</h1>";
        }
        else {
            $users = Database::queryWith("SELECT userid,email,isEmployee,credentials FROM users WHERE (email = :email)", array("email" => $email));
            if ($users->rowCount() !== 0) {
                // We don't want to give more details here, do we?
                echo "<h1>Registration failed.</h1>";
            }
            else {
                $data = array("email" => $_POST["email"], "credentials" => password_hash($_POST["password"], PASSWORD_DEFAULT), "isVerified" => "0", "isEmployee" => "0");
                $userid = Database::insert("users", $data, TRUE);
                $accountData = array("userid" => $userid, "balance" => "1000"); // We are generous!
                Database::insert("accounts", $accountData);
                echo "<h1>Registration successful.</h1>Your account has to be approved, before you can login.";
            }
        }
        break;
    case "_dologin":
        $getUsersForName = function($email) {
            return Database::queryWith("SELECT userid,email,isEmployee,credentials FROM users WHERE isVerified = 1 AND (email = :email)", array("email" => $email));
        };
        $success = $login->login($_POST, $getUsersForName);
        if ($success !== 0) {
            echo "<h1>Login failed.$success</h1>";
            echo "<a href='?page=login'>Try again</a>";
        }
        else {
            echo "<h1>Login successful.</h1>Redirecting...";
            if ($login() === 1) header("Location: index.php?page=uhome");
            if ($login() === 2) header("Location: index.php?page=ehome");
        }
        break;
    case "uhome":
        echo "<h1>Welcome client</h1>";
        break;
    case "ehome":
        echo "<h1>Welcome employee</h1>";
        break;
    case "_logout":
        $login->reset();
        header("Location: index.php");
        break;
    case "!dberror":
        echo "<h1>Could not connect to the database</h1>";
        break;
    case "!auth":
        echo "<h1>You are not authenticated to do this.</h1>";
        echo "<a href='?page=login'>Login</a>";
        break;
    default:
        echo "<h1>Could not find requested page.</h1><a href='?page=home'>Go home instead</a>";
        break;
}

if ($login() !== 0) {
    echo "<p><a href='?page=logout'>Logout</a></p>";
}

Database::close();

?>
</div></body></html>

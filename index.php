<?php

session_start();

require_once "database.php";
require_once "login.php";

$getUsersForName = function($email, $verified = 1) {
    return Database::queryWith("SELECT userid,email,isEmployee,credentials FROM users WHERE isVerified = $verified AND (email = :email)", array("email" => $email));
};

$login = new Login(600); // Auto logout after ten minutes inactivity


// first char: _ means ordinary, ! means error
$page = "_home";

if (isset($_GET["page"])) {
    $page = "_" . $_GET["page"];
}


// TODO: Some style, html wrapper, etc.
?>
<html><head><title>SecureBank</title><link rel="stylesheet" href="style.css" /></head><body><div class="container">
<?php

if (!Database::open()) {
    $page = "!dberror";
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
    case "_doregister":
        if (!isset($_POST["email"]) || !isset($_POST["password"])) {
            echo "<h1>Registration failed.</h1>";
        }
        else {
            $users = $getUsersForName($_POST["email"], 0);
            if ($users->rowCount() !== 0) {
                // We don't want to give more details here, do we?
                echo "<h1>Registration failed.</h1>";
            }
            else {
                $data = array("email" => $_POST["email"], "credentials" => password_hash($_POST["password"], PASSWORD_DEFAULT), "isVerified" => "0", "isEmployee" => "0");
                Database::insert("users", $data);
                echo "<h1>Registration successful.</h1>Your account has to be approved, before you can login.";
            }
        }
        break;
    case "!dberror":
        echo "<h1>Could not connect to the database</h1>";
        break;
    default:
        echo "<h1>Could not find requested page.</h1><a href='?page=home'>Go home instead</a>";
        break;
}

Database::close();

?>
</div></body></html>

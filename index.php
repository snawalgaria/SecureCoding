<?php

session_start();

require_once "database.php";
require_once "login.php";

login_init(600); // Auto logout after ten minutes inactivity


// first char: _ means public, ! means error, u means user (login privileges 1), e means employee (p 2)
$page = "_home";

if (isset($_GET["page"])) {
    $page = "_" . $_GET["page"];
    if (strlen($page) > 2 && (substr($page, 1, 1) === "u" || substr($page, 1, 1) === "e")) {
        $page = $_GET["page"];
    }
}

if (!db_open()) {
    $page = "!dberror";
}

if (substr($page, 0, 1) === "u" && login_privileges() !== 1) {
    $page = "!auth";
} else if (substr($page, 0, 1) === "e" && login_privileges() !== 2) {
    $page = "!auth";
}

if (login_privileges() !== 0 && substr($page, 0, 1) === "_" && $page !== "_logout") {
    // $page = (login_privileges() === 1 ? "u" : "e") . "home";
    header("Location: index.php?page=" . (login_privileges() === 1 ? "u" : "e") . "home");
    exit;
}

function display_userstate($userid) {
    if ($userid !== login_userid() && login_privileges() !== 2) {
        echo "<p>Access denied for this information.</p>";
        return;
    }
    $userData = db_queryWith("SELECT * FROM users WHERE userid = :userid", array("userid" => $userid));
    if ($userData->rowCount() === 0) {
        echo "<p>Access denied for this information.</p>";
        return;
    }
    $userData = $userData->fetch();
    echo "<p>Customer Details: " . $userData["name"] . " ($userData[email])</p>";
    $users = db_queryWith("SELECT balance FROM accounts WHERE userid = :userid", array("userid" => $userid));
    $balance = $users->fetch()["balance"] / 100.0;
    echo "<hr><p>Account balance: $balance €</p>";
    if ($userid === login_userid()) echo "<p><a href='?page=utransaction'>New transaction</a></p>";
    for ($verified = 1; $verified >= 0; $verified--) {
        $transactions = db_queryWith("SELECT * FROM transactions WHERE (sourceAccount = :userid OR targetAccount = :userid) AND isVerified = $verified ORDER BY unixtime DESC", array("userid" => $userid));
        echo "<hr><h3>" . ($verified ? "Performed Transactions" : "Unverified Transactions") . "</h3>";
        if ($transactions->rowCount() === 0)
        {
            echo "<p>There are no transactions in this section.</p>";
        }
        else {
            echo "<table class='transactions'><thead><tr><th>Date</th><th>Description</th><th>Other Party</th><th>Volume</th></tr></thead><tbody>";
            foreach ($transactions as $t) {
                echo "<tr><td>" . date("Y-m-d H:i", $t["unixtime"]) . "</td><td>$t[description]</td>";
                $volume = $t["volume"] / 100.0;
                $other = $t["sourceAccount"];
                if ($t["sourceAccount"] === $userid) {
                    if ($t["targetAccount"] === $userid) {
                        $volume = 0;
                    }
                    else {
                        $volume = -$volume;
                    }
                    $other = $t["targetAccount"];
                }
                echo "<td>$other</td><td>$volume €</td></tr>";
            }
            echo "</tbody></table>";
        }
    }
    // TODO: Offer Download as PDF according to latest specification.
}

?>
<html><head><title>SecureBank</title><link rel="stylesheet" href="style.css" /></head><body><div class="container">
<?php

switch ($page) {
    case "_home":
        echo "<h1>Welcome to the SecureBank!</h1>";
        echo "<a href='?page=login'>Login</a> or <a href='?page=register'>register</a>.";
        break;
    case "_register":
        ?><h1>Register at the SecureBank</h1>
        <form action="?page=doregister" method="post">
        Name<br><input name="name"><br><br>
        E-Mail<br><input type="email" name="email"><br><br>
        Password<br><input type="password" name="password"><br><br>
        <label for="employee">
        <input type="checkbox" name="isEmployee" value="employee" id="employee">
        You are an employee? (be honest!)
        </label><br><br>
        <input type="submit" value="Register"></form>
        <?php
        break;
    case "_login":
        ?><h1>Login at the SecureBank</h1>
        <form action="?page=dologin" method="post">E-Mail<br><input type="email" name="email"><br><br>Password<br><input type="password" name="password"><br><br><input type="submit" value="Login"></form>
        <?php
        break;
    case "_doregister":
        var_dump($_POST);
        if (!isset($_POST["name"]) || !isset($_POST["email"]) || !isset($_POST["password"]) ||
            strlen($_POST["name"]) === 0 || strlen($_POST["email"]) === 0) {
            echo "<h1>Registration failed.</h1>";
        }
        else {
            $employee = isset($_POST["isEmployee"]) ? 1 : 0;
            $email = htmlspecialchars($_POST["email"]);
            $name = htmlspecialchars($_POST["name"]);
            $users = db_queryWith("SELECT userid,email,isEmployee,credentials FROM users WHERE (email = :email)", array("email" => $email));
            if ($users->rowCount() !== 0) {
                // We don't want to give more details here, do we?
                echo "<h1>Registration failed.</h1>";
            }
            else {
                // If we were allowed to use PHP > 5.5, this would be *MUCH* more secure.
                //$credential = password_hash($_POST["password"], PASSWORD_DEFAULT);
                $credentials = sha1($_POST["password"]);
                $data = array("name" => $name, "email" => $email, "credentials" => $credentials, "isVerified" => "0", "isEmployee" => $employee);
                $userid = db_insert("users", $data, TRUE);
                if (!$employee) {
                    $accountData = array("userid" => $userid, "balance" => "10000"); // We are generous and are giving everyone so much money!
                    db_insert("accounts", $accountData);
                }
                echo "<h1>Registration successful.</h1>Your account has to be approved, before you can login. We will send you an e-mail when we verified your account.";
            }
        }
        break;
    case "_dologin":
        $getUsersForName = function($email) {
            return db_queryWith("SELECT userid,email,isEmployee,credentials FROM users WHERE isVerified = 1 AND (email = :email)", array("email" => $email));
        };
        $success = login_dologin($_POST, $getUsersForName);
        if ($success !== 0) {
            echo "<h1>Login failed.$success</h1>";
            echo "<a href='?page=login'>Try again</a>";
        }
        else {
            echo "<h1>Login successful.</h1>Redirecting...";
            if (login_privileges() === 1) header("Location: index.php?page=uhome");
            if (login_privileges() === 2) header("Location: index.php?page=ehome");
        }
        break;
    case "uhome":
        echo "<h1>Welcome, client</h1>";
        $userid = login_userid();
        display_userstate($userid);
        break;
    case "utransaction":
        echo "<h1>New transaction</h1>";
        echo "You can enter the information of the transaction here, our you can <a href='?page=utransactionupload'>upload a transaction file</a>.<hr>";
        echo "<h3>Transaction data</h3>";
        echo "<form action='?page=udotransaction' method='POST'>";
        echo "<p><input name='target'> Target account</p>";
        echo "<p><input name='volume'> Transaction volume</p>";
        echo "<p>Description</p><textarea cols='80' rows='3' name='desc'></textarea>";
        echo "<p><input name='tan'> Enter a TAN here.</p>";
        echo "<p><input type='submit' value='Perform Transaction'></p></form>";
        break;
    case "udotransaction":
        var_dump($_POST); // Debugging.
        // Perform transaction. if volume < 10000€ change account balances.
        break;
    case "utransactionupload":
        echo "<h1>Transaction from file</h1>";
        echo "<form enctype='multipart/form-data' action='?page=udotransactionupload' method='POST'>";
        echo "<input type='hidden' name='MAX_FILE_SIZE' value='3000'>";
        echo "<p>File containing the transaction: <input name='transactionfile' type='file'>";
        echo "<input type='submit'></form></p>";
        break;
    case "udotransactionupload":
        if (!isset($_FILES["transactionfile"]) || $_FILES["transactionfile"]["size"] > 3000 || $_FILES["transactionfile"]["error"] !== UPLOAD_ERR_OK) {
            echo "<h1>Error while processing the transaction file.</h1>";
        }
        else {
            // TODO: popen the C program here and implement further logic
            echo "Got file at " . $_FILES["transactionfile"]["tmp_name"];
            // Perform transaction from uploaded file
        }
        break;
    case "ehome":
        echo "<h1>Welcome, employee</h1>";
        echo "View account for customer with ID: <form style='display: inline-block;' action='?page=etakeover' method='POST'><input name='userid'><input type='submit' value='Show'></form>";
        echo "<hr><p>Things to do:</p>";
        $users = db_queryWith("SELECT userid,name,email,isEmployee FROM users WHERE isVerified = 0");
        echo "<ul>";
        if ($users->rowCount() === 0) {
            // TODO: This never gets displayed. Why?
            "<li>No users to verify.</li>";
        }
        else {
            foreach ($users as $user) {
                echo "<li>" . ($user["isEmployee"] ? "Employee" : "New customer") . " '" . $user["name"] . "' with e-mail '" . $user["email"] . "' registered.";
                echo "<form style='display: inline-block;' action='?page=edoverify' method='post'><input type='hidden' name='userid' value='" . $user["userid"] . "'><input type='hidden' name='success' value='true'><input type='submit' value='Verify'></form>";
                echo "<form style='display: inline-block;' action='?page=edoverify' method='post'><input type='hidden' name='userid' value='" . $user["userid"] . "'><input type='hidden' name='success' value='not true'><input type='submit' value='Drop'></form>";
                echo "</li>";
            }
        }
        echo "</ul>";

        // TODO: Find and display unverified transactions
        break;
    case "edoverify":
        if (!isset($_POST["userid"]) || !isset($_POST["success"])) {
            echo "<h1>Your are clever, but not clever enough.</h1>";
        }
        else {
            $success = $_POST["success"] === "true";
            if ($success) {
                db_queryWith("UPDATE users SET isVerified = 1 WHERE userid = :userid", array("userid" => $_POST["userid"]));
                // TODO: Send Email with TAN if it is not an employee.
            }
            else {
                db_queryWith("DELETE FROM users WHERE userid = :userid", array("userid" => $_POST["userid"]));
                db_queryWith("DELETE FROM accounts WHERE userid = :userid", array("userid" => $_POST["userid"]));
            }
        }
        header("Location: index.php?page=ehome");
        break;
    case "eapprovetransaction":
        // Verify transaction UI
        break;
    case "edoapprovetransaction":
        // Perform transaction, if not verified yet and change account balances.
        break;
    case "etakeover":
        echo "<h1>Customer details</h1>";
        echo "<p><a href='?page=ehome'>Back to dashboard</a></p><hr>";
        // PVUL: Check existence.
        display_userstate($_POST["userid"]);
        break;
    case "_logout":
        login_reset();
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

if (login_privileges() !== 0) {
    echo "<hr><p><a href='?page=logout'>Logout</a></p>";
}

db_close();

?>
</div></body></html>

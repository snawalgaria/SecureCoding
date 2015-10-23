<?php

session_start();

require_once "database.php";
require_once "login.php";
require_once "pagebuilder.php";

login_init(600); // Auto logout after ten minutes inactivity

pb_init();

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
        pb_replace_with("main", "<p>Access denied for this information.</p>");
        return;
    }
    $userData = db_queryWith("SELECT * FROM users WHERE userid = :userid", array("userid" => $userid));
    if ($userData->rowCount() === 0) {
        pb_replace_with("main", "<p>Access denied for this information.</p>");
        return;
    }
    $userData = $userData->fetch();
    pb_replace_all("main", "display_userstate.html");
    pb_replace_with("name", $userData["name"]);
    pb_replace_with("email", $userData["email"]);
    //echo "<p>Customer Details: " . $userData["name"] . ", " . ($userData["email"]) . "</p>";
    $users = db_queryWith("SELECT balance FROM accounts WHERE userid = :userid", array("userid" => $userid));
    $balance = $users->fetch(PDO::FETCH_ASSOC);
    $balance = $balance["balance"] / 100.0;
    //echo "<hr><p>Account balance: $balance €</p>";
    pb_replace_with("balance", $balance);
    if ($userid === login_userid())
        pb_replace_with("utransaction", "<p><a href='?page=utransaction'>New transaction</a></p>");
    else pb_replace_with("utransaction", "");
        //echo "<p><a href='?page=utransaction'>New transaction</a></p>";
    for ($verified = 1; $verified >= 0; $verified--) {
        $transactions = db_queryWith("SELECT * FROM transactions WHERE (sourceAccount = :userid OR targetAccount = :userid) AND isVerified = $verified ORDER BY unixtime DESC", array("userid" => $userid));
        //echo "<hr><h3>" . ($verified ? "Performed Transactions" : "Unverified Transactions") . "</h3>";
        pb_replace_with("transactions", str_repeat("%%transaction%%\n", $transactions->rowCount()));
        pb_replace_all("transaction", "transaction.html");
        //if ($transactions->rowCount() !== 0) {
            //echo "<table class='transactions'><thead><tr><th>Date</th><th>Description</th><th>Other Party</th><th>Volume</th></tr></thead><tbody>";
            foreach ($transactions as $t) {
                //echo "<tr><td>" . date("Y-m-d H:i", $t["unixtime"]) . "</td><td>$t[description]</td>";
                pb_replace_with("time", date("Y-m-d H:i", $t["unixtime"]));
                pb_replace_with("description", $t[description]);
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
                pb_replace_with("other", $other);
                pb_replace_with("volume", $volume);
                //echo "<td>$other</td><td>$volume €</td></tr>";
            }
            //echo "</tbody></table>";
        //}
    }
    // TODO: Offer Download as PDF according to latest specification.
}

switch ($page) {
    case "_home":
        pb_replace_all("main", "home.html");
        break;
    case "_register":
        pb_replace_all("main", "register.html");
        break;
    case "_login":
        pb_replace_all("main", "login.html");
        break;
    case "_doregister":
        //var_dump($_POST);
        if (!isset($_POST["name"]) || !isset($_POST["email"]) || !isset($_POST["password"]) ||
            strlen($_POST["name"]) === 0 || strlen($_POST["email"]) === 0) {
            //echo "<h1>Registration failed.</h1>";
        }
        else {
            $employee = isset($_POST["isEmployee"]) ? 1 : 0;
            $email = htmlspecialchars($_POST["email"]);
            $name = htmlspecialchars($_POST["name"]);
            $users = db_queryWith("SELECT userid,email,isEmployee,credentials FROM users WHERE (email = :email)", array("email" => $email));
            if ($users->rowCount() === 0) {
                // If we were allowed to use PHP > 5.5, this would be *MUCH* more secure.
                //$credential = password_hash($_POST["password"], PASSWORD_DEFAULT);
                $credentials = sha1($_POST["password"]);
                $data = array("name" => $name, "email" => $email, "credentials" => $credentials, "isVerified" => "0", "isEmployee" => $employee);
                $userid = db_insert("users", $data, TRUE);
                if (!$employee) {
                    $accountData = array("userid" => $userid, "balance" => "10000"); // We are generous and are giving everyone so much money!
                    db_insert("accounts", $accountData);
                }
                //echo "<h1>Registration successful.</h1>Your account has to be approved, before you can login. We will send you an e-mail when we verified your account.";
                pb_replace_all("main", "doregister_success.html");
            }
        }
        // only happens if failed, since otherwise '%%main%%' is already removed from the html
        pb_replace_all("main", "doregister_fail.html");
        break;
    case "_dologin":
        $getUsersForName = function($email) {
            return db_queryWith("SELECT userid,email,isEmployee,credentials FROM users WHERE isVerified = 1 AND (email = :email)", array("email" => $email));
        };
        $success = login_dologin($_POST, $getUsersForName);
        if ($success !== 0) {
			pb_replace_all("main", "dologin_fail.html");
			pb_replace_with("ERRORCODE", $success);
        }
        else {
            pb_replace_all("main", "dologin_success.html");
            if (login_privileges() === 1) header("Location: index.php?page=uhome");
            if (login_privileges() === 2) header("Location: index.php?page=ehome");
        }
        break;
    case "uhome":
        //echo "<h1>Welcome, client</h1>";
        $userid = login_userid();
        display_userstate($userid);
        pb_replace_with("headline", "<h1>Welcome, client</h1>");
        break;
    case "utransaction":
        pb_replace_all("main", "utransaction.html");
        break;
    case "udotransaction":
        var_dump($_POST); // Debugging.
        // Perform transaction. if volume < 10000€ change account balances.
        break;
    case "utransactionupload":
        pb_replace_all("main", "utransactionupload.html");
        break;
    case "udotransactionupload":
        if (isset($_FILES["transactionfile"]) && $_FILES["transactionfile"]["size"] <= 3000 && $_FILES["transactionfile"]["error"] === UPLOAD_ERR_OK) {
            $handle = popen("./parser/parser " . $_FILES["transactionfile"]["tmp_name"], "r");
            $read = fread($handle, 3000);
            $status = pclose($handle);
            if ($status === 0) {
                // TODO: Perform transaction.
				pb_replace_with("main", $read);
            }
        }
        pb_replace_all("main", "udotransactionupload_fail.html");
        break;
    case "ehome":
        pb_replace_all("main", "ehome.html");
        $users = db_queryWith("SELECT userid,name,email,isEmployee FROM users WHERE isVerified = 0");
        if ($users->rowCount() === 0) {
			pb_replace_with("element", "<li>No users to verify.</li>");
        }
        else {
			pb_replace_with("element", str_repeat("%%element%%\n", $users->rowCount()));
			pb_replace_all("element", "ehome_user.html");
            foreach ($users as $user) {
				pb_replace_with("type", $user["isEmployee"] ? "Employee" : "New customer");
				pb_replace_with("name", $user["name"]);
				pb_replace_with("email", $user["email"]);
				pb_replace_with("userid", $user["userid"]);
				pb_replace_with("userid", $user["userid"]);
            }
        }

        // TODO: Find and display unverified transactions
        break;
    case "edoverify":
        if (!isset($_POST["userid"]) || !isset($_POST["success"])) {
			pb_replace_all("main", "edoverify_fail.html");
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
        pb_replace_all("main", "etakeover.html");
        // PVUL: Check existence.
        display_userstate($_POST["userid"]);
        pb_replace_with("headline", "");
        break;
    case "_logout":
        login_reset();
        header("Location: index.php");
        break;
    case "!dberror":
        pb_replace_all("main", "dberror.html");
        break;
    case "!auth":
        pb_replace_all("main", "auth.html");
        break;
    default:
        pb_replace_all("main", "default.html");
        break;
}

if (login_privileges() !== 0) {
    pb_replace_with("logout", "<hr><p><a href='?page=logout'>Logout</a></p>");
}
else {
    pb_replace_with("logout", "");
}

db_close();

pb_print();
//*/
?>



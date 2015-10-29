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
    $users = db_queryWith("SELECT balance FROM accounts WHERE userid = :userid", array("userid" => $userid));
    $balance = $users->fetch(PDO::FETCH_ASSOC);
    $balance = $balance["balance"] / 100.0;
    pb_replace_with("balance", $balance);
    if ($userid === login_userid())
        pb_replace_with("utransaction", "<p><a href='?page=utransaction'>New transaction</a></p>");
    else pb_replace_with("utransaction", "");
    for ($verified = 1; $verified >= 0; $verified--) {
        $transactions = db_queryWith("SELECT * FROM transactions WHERE (sourceAccount = :userid OR targetAccount = :userid) AND isVerified = $verified ORDER BY unixtime DESC", array("userid" => $userid));
        pb_replace_with("transactions", str_repeat("%%transaction%%\n", $transactions->rowCount()));
        pb_replace_all("transaction", "transaction.html");
        foreach ($transactions as $t) {
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
        }
    }

    // TODO: PDF export button!!
}

switch ($page) {
    case "_home":
        pb_replace_all("main", "entry_page.html");//"home.html");
        break;
    case "_register":
        pb_replace_all("main", "register.html");
        break;
    case "_login":
        pb_replace_all("main", "login.html");
        break;
    case "_doregister":


        $input_complete =
            isset($_POST["name"]) &&
            isset($_POST["email"]) &&
            isset($_POST["password"]) &&
            isset($_POST["confirm_password"]);

        $input_valid = strlen($_POST["name"]) != 0 && strlen($_POST["email"]) != 0;

        //could do other checks... or some type of policy maybe
        $valid_password =
            strlen($_POST["password"]) >= 8 &&
            strcmp($_POST["confirm_password"], $_POST["password"]) === 0;

        //var_dump($_POST);
        if (!$input_complete || !$input_valid) {
            //TODO... exchange registration failed against ui info to user
			pb_replace_all("main", "doregister_fail.html");
            pb_replace_with("ERRORCODE", "Please make sure that you enter values into all fields!");
        }
        else if(!$valid_password){
            //TODO... inform user that something went wrong with the password
            pb_replace_all("main", "doregister_fail.html");
            pb_replace_with("ERRORCODE", "Please make sure that your password is at least 8 signs long and identical to the confirmation field!");
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
                pb_replace_all("main", "doregister_success.html");
            }
        }
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
		// mail("johannes.w.fischer@web.de", "Test", "This is just a test.", 'From: webmaster@example.com' . "\r\n" .
		// 			'Reply-To: webmaster@example.com' . "\r\n" .
		// 			'X-Mailer: PHP/' . phpversion());
        pb_replace_all("main", "ehome.html");
        $users = db_query("SELECT userid,name,email,isEmployee FROM users WHERE isVerified = 0");
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
                try {
                    $verified_user = db_queryWith("SELECT name, email, isEmployee FROM users WHERE userid = :userid AND isVerified = 0", array("userid" => $_POST["userid"]));

                    if ($verified_user->rowCount() !== 1) {
                        pb_replace_all("main", "edoverify_fail.html");
                        $failed = TRUE;
                    }
                    else {
                        $user = $verified_user->fetch();
                        db_queryWith("UPDATE users SET isVerified = 1 WHERE userid = :userid", array("userid" => $_POST["userid"]));
                    }

                    if (!$failed && !$user["isEmployee"]) {
                        $tans = array();

                        while (count($tans) < 100) {
                            $tanQuery = "SELECT tan FROM tans WHERE ";
                            for ($i = count($tans); $i < 110; $i++) {
                                $tan = substr(base64_encode(openssl_random_pseudo_bytes(12)), 0, 15);
                                $tans[] = $tan;
                                if ($i !== 0) $tanQuery .= " OR ";
                                $tanQuery .= "tan = '$tan'"; // No need for placeholder unless base64 is hijacked.
                            }

                            array_unique($tans);

                            // Small overhead for database query.
                            if (count($tans) < 105) continue; // Don't spam the database.

                            $existingTans = db_query($tanQuery);

                            foreach ($existingTans as $etan) {
                                if (($index = array_search($etan["tan"], $tans)) !== FALSE) {
                                    array_splice($tans, $index, 1);
                                }
                            }
                        }

                        // We found 100 tans.
                        $tans = array_slice($tans, 0, 100);
                        $tanQueryPart = array();
                        foreach ($tans as $tan) {
                            $tanQueryPart[] = "('$tan',:userid)";
                        }

                        $tanQueryPart = join(",", $tanQueryPart);
                        $tanQuery = "INSERT INTO tans (`tan`, `userid`) VALUES $tanQueryPart";

                        try {
                            db_queryWith($tanQuery, array("userid" => $_POST["userid"]));
                        } catch (Exception $e) {
                            pb_replace_with("main", "Error: ETANGENT");
                            $failed = TRUE;
                        }

                        if (!$failed) {
                            // TODO: Send Email with TANs
                        }
                    }

                    if (!$failed)
                        header("Location: index.php?page=ehome");

                }
                catch (Exception $e) {
                    pb_replace_with("main", "We have some serious problems. Please contact the webmaster.");
                    // echo $e->getMessage();
                }
            }
            else {
                db_queryWith("DELETE FROM users WHERE userid = :userid", array("userid" => $_POST["userid"]));
                db_queryWith("DELETE FROM accounts WHERE userid = :userid", array("userid" => $_POST["userid"]));
                header("Location: index.php?page=ehome");
            }
        }
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
    pb_replace_with("logout", "<a href='?page=logout'>Logout</a>");
}
else {
    pb_replace_with("logout", "<a href='?page=register'>Register</a><a href='?page=login'>Login</a>");
}

db_close();

pb_print();
//*/
?>



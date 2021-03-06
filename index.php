<?php

session_start();

require_once "database.php";
require_once "login.php";
require_once "pagebuilder.php";
include_once "mail_sender.php";

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

function makepdf($userid) {
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
    if ($userData["isEmployee"]) {
        pb_replace_with("main", "<p>This ($userData[name]) is an employee.</p>");
        return;
    }

    $transactions = db_queryWith("SELECT tran.unixtime, sour.userId as sourceID, sour.name as source, targ.name as target, tran.volume as volume, tran.description as description ".
        "FROM transactions as tran, users as sour, users as targ ".
        "WHERE (tran.sourceAccount = :userid OR tran.targetAccount = :userid) AND tran.isVerified ".
        "AND sour.userId = tran.sourceAccount AND targ.userId = tran.targetAccount ".
        "ORDER BY tran.unixtime DESC ", array("userid" => $userid));
    if ($transactions->rowCount() === 0) {
        pb_replace_with("main", "<p>No transactions.</p>");
        return;
    }

    require_once("transactionpdf.php");
    // column titles
    $header = array('Date', 'Description', 'Other Party', 'Volume');

    // date, taken from the database
    $data = array();
    foreach ($transactions as $t) {
        $volume = $t["volume"] / 100.0;
        if ($t["source"] === $t["target"]) {
            $volume = 0;
        }
        $other = $t["target"];
        if ($t["sourceID"] === $userid) {
            $volume = -$volume;
        }
        else {
            $other = $t["source"];
        }
        $data[] = array(date("Y-m-d H:i", $t["unixtime"]),
            $t["description"], $other, $volume);
    }

    // Create the PDF and print it
    $pdf = TransactionPDF::create($header, $data);
    $pdf->Output('transaction-hstory.pdf', 'I');

    // After printing the pdf we don't want to end with an HTML file, so exit immediately
    exit(0);
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
    if ($userData["isEmployee"]) {
        pb_replace_with("main", "<p>This ($userData[name]) is an employee, you can't take it over.</p>");
        return;
    }
    pb_replace_all("main", "display_userstate.html");
    if (login_privileges() == 2) {
        pb_replace_with("details", "Customer Details: %%name%%, %%userid%%, %%email%%");
        pb_replace_with("name", $userData["name"]);
        pb_replace_with("email", $userData["email"]);
        pb_replace_with("userid", $userid);
        pb_replace_with("pdflink", '<a href="?page=etransactionpdf&userid='.$userid.'">Print PDF</a>');
    }
    else {
        pb_replace_with("details", "Your account ID is '%%userid%%'");
        pb_replace_with("userid", $userid);
        pb_replace_with("pdflink", '<a href="?page=utransactionpdf">Print PDF</a>');
    }
    $users = db_queryWith("SELECT balance FROM accounts WHERE userid = :userid", array("userid" => $userid));
    $balance = $users->fetch(PDO::FETCH_ASSOC);
    $balance = $balance["balance"] / 100.0;
    pb_replace_with("balance", $balance);
    if ($userid === login_userid())
        pb_replace_with("utransaction", "<p><a href='?page=utransaction'>New transaction</a></p>");
    else pb_replace_with("utransaction", "");
    for ($verified = 1; $verified >= 0; $verified--) {
        $transactions = db_queryWith("SELECT tran.unixtime, sour.userId as sourceID, sour.name as source, targ.name as target, tran.volume as volume, tran.description as description ".
            "FROM transactions as tran, users as sour, users as targ ".
            "WHERE (tran.sourceAccount = :userid OR tran.targetAccount = :userid) AND tran.isVerified = :verified ".
            "AND sour.userId = tran.sourceAccount AND targ.userId = tran.targetAccount ".
            "ORDER BY tran.unixtime DESC ", array("userid" => $userid, "verified" => $verified));
        if ($transactions->rowCount() === 0) {
            pb_replace_with("table", "<p>No transaction in this category.</p>");
        } else {
            pb_replace_with_file("table", "display_userstate_table.html");
        }
        pb_replace_with("transactions", str_repeat("%%transaction%%\n", $transactions->rowCount()));
        pb_replace_all("transaction", "transaction.html");
        foreach ($transactions as $t) {
            pb_replace_with("time", date("Y-m-d H:i", $t["unixtime"]));
            pb_replace_with("description", $t["description"]);
            $volume = $t["volume"] / 100.0;
            if ($t["source"] === $t["target"]) {
                $volume = 0;
            }
            $other = $t["target"];
            if ($t["sourceID"] === $userid) {
                $volume = -$volume;
            }
            else {
                $other = $t["source"];
            }
            pb_replace_with("other", $other);
            pb_replace_with("volume", $volume);
        }
    }
}

function performTransaction($tid) {
    // TODO: Implement logic.
    //  1. Get data from database via queryWith(..., array("tid" => $tid))
    //  2. Load accounts from participants
    //  3. Fail gracefully, if that didn't work (i.e. we don't have exactly one unverified transaction, excactly one account for each participants, etc.)
    //  4. Perform account balance change
    //  5. Mark as transaction as verified
    //  6. Store updated data
    //
    // One potential problem: ensure database atomicity.
    try{

        $transaction = db_queryWith("SELECT * FROM transactions WHERE tid = :tid", array("tid" => $tid));
        if ($transaction->rowCount() !== 1) {
            return "Transaction does not exist.";
        }
        $transaction = $transaction->fetch();
        $srcAccount = $transaction["sourceAccount"];
        $targAccount = $transaction["targetAccount"];
        $srcArray =db_queryWith("SELECT * FROM accounts WHERE userid =:userid", array("userid" => $srcAccount));
        if($srcArray->rowCount()!==1)
        {
            return "User does not exist";
        }
        $srcArray = $srcArray->fetch();
        $targArray =db_queryWith("SELECT * FROM accounts WHERE userid= :userid", array("userid" => $targAccount));
        if($targArray->rowCount()!==1)
        {
            return "User does not exist";
        }
        $targArray = $targArray->fetch();
        $srcBalance =$srcArray["balance"]-$transaction["volume"];
        $targBalance = $targArray["balance"] + $transaction["volume"];
        if($srcBalance >= 0 && $targBalance >=0)
        {
            $firstTxn=db_queryWith("update accounts set balance =:srcBalance where userid=:userid",array("srcBalance" =>$srcBalance,"userid"=>$srcAccount));
            if($firstTxn)
            {
                $secondTxn=db_queryWith("update accounts set balance=:targBalance where userid=:userid",array("targBalance" =>$targBalance,"userid"=>$targAccount));
                if($secondTxn)
                {
                    $verify=db_queryWith("update transactions set isVerified=1 where tid=:tid",array("tid" =>$tid));
                    if($verify)
                    {
                        return "Transaction is Successful";
                    }
                }
                else
                {
                    //Reverting Back the changes in DB since secondTxn failed

                    db_queryWith("update accounts set balance =:balance where userid=:userid",array("balance"=>$srcArray->balance,"userid"=>$srcAccount));
                    return "Transaction failed";
                }
            }
            else
            {
                return "Transaction Failed";
            }
        }
        else
        {
            return "Transaction failed due to insufficient balance";
        }
    }
    catch(Exception $exe)
    {
        return "Transaction failed to perform";
    }
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
            pb_replace_all("main", "doregister_fail.html");
            pb_replace_with("ERRORCODE", "Please make sure that you enter values into all fields!");
        }
        else if(!$valid_password){

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
        if (!isset($_POST["target"]) || !isset($_POST["volume"]) || !isset($_POST["tan"]) || !isset($_POST["desc"])) {
            pb_replace_with("main", "<p>Sorry.</p>");
            break;
        }

        $volume = intval(floatval($_POST["volume"])*100);
        // This is too bad. Eventually TODO: Fix this.
        // Example case: 53.40 € => 5340 => 53.4
        // if (strval($volume / 100.0) !== $_POST["volume"]) {
        //     pb_replace_with("main", "<p>Invalid volume.</p>");
        //     break;
        // }
        $userid = login_userid();
        $eigenAccount = db_queryWith("SELECT * FROM accounts WHERE userid = :userid", array("userid" => $userid));
        if($eigenAccount->rowCount() !== 1) {
            pb_replace_with("main", "<p>You don't exist.</p>");
            break;
        }
        $eigenAccount = $eigenAccount->fetch();
        if ($eigenAccount["balance"] - $volume < 0) {
            pb_replace_with("main", "<p>You don't have enough money.</p>");
            break;
        }
        $other = db_queryWith("SELECT * FROM accounts WHERE userid = :userid", array("userid" => $_POST["target"]));
        if($other->rowCount() !== 1) {
            pb_replace_with("main", "<p>You want to send money to nobody?!</p>");
            break;
        }
        $tan = db_queryWith("SELECT * FROM tans WHERE userid = :userid AND tan = :tan AND used = 0", array("userid" => $userid, "tan" => $_POST["tan"]));
        if ($tan->rowCount() !== 1) {
            pb_replace_with("main", "<p>The TAN you entered does not exist or is already used.</p>");
            break;
        }
        db_queryWith("INSERT INTO transactions (sourceAccount,targetAccount,volume,description,unixtime,isVerified)".
            " VALUES (:userid, :target, :volume, :description, :time, :verified)", array(
            "userid" => $userid,
            "target" => $_POST["target"],
            "volume" => $volume,
            "description" => $_POST["desc"],
            "time" => time(),
            "verified" => $volume < 1000000 ? 1 : 0
        ));
        db_queryWith("UPDATE tans SET used = 1 WHERE tan = :tan", array("tan" => $_POST["tan"]));
        if ($volume < 1000000) {
            db_queryWith("UPDATE accounts SET balance = balance - :volume WHERE userid = :userid", array("userid" => $userid, "volume" => $volume));
            db_queryWith("UPDATE accounts SET balance = balance + :volume WHERE userid = :userid", array("userid" => $_POST["target"], "volume" => $volume));
            pb_replace_with("main", "<p>Transaction performed successfully.</p>");
        } else
            pb_replace_with("main", "<p>Transaction has to be approved by the bank.</p>");
        break;
    case "utransactionupload":
        pb_replace_all("main", "utransactionupload.html");
        break;
    case "udotransactionupload":
        if (isset($_FILES["transactionfile"]) && $_FILES["transactionfile"]["size"] <= 3000 && $_FILES["transactionfile"]["error"] === UPLOAD_ERR_OK) {
            $handle = popen("./parser/parser " . login_userid() . " " . $_FILES["transactionfile"]["tmp_name"], "r");
            $read = fread($handle, 3000);
            $status = pclose($handle);
            pb_replace_with("main", $read);
        }
        pb_replace_all("main", "udotransactionupload_fail.html");
        break;
    case "utransactionpdf":
    case "etransactionpdf":
        // Find out for which user the transaction history needs to be shown
        $userid = login_userid();
        if (substr($page, 0, 1) == 'e')
        {
            if(isset($_GET["userid"]))
                $userid = $_GET["userid"];
        }

        makepdf($userid);
        break;
    case "ehome":
        pb_replace_all("main", "ehome.html");
        $users = db_query("SELECT userid,name,email,isEmployee FROM users WHERE isVerified = 0");
        $transactions = db_query("SELECT * FROM transactions WHERE isVerified = 0");
        if ($transactions->rowCount() === 0 && $users->rowCount() === 0) {
            pb_replace_with("element", "<li>Nothing to do.</li>");
        }
        else {
            pb_replace_with("element", str_repeat("%%element%%\n", $users->rowCount() + $transactions->rowCount()));
            foreach ($users as $user) {
                pb_replace_with_file("element", "ehome_user.html");
                pb_replace_with("type", $user["isEmployee"] ? "Employee" : "New customer");
                pb_replace_with("name", $user["name"]);
                pb_replace_with("email", $user["email"]);
                pb_replace_with("userid", $user["userid"]);
                pb_replace_with("userid", $user["userid"]);
            }
            foreach ($transactions as $t) {
                pb_replace_with_file("element", "ehome_transaction.html");
                pb_replace_with("type", "Transaction to verify about " . $t["volume"] / 100.0 . " &euro;");
                pb_replace_with("tid", $t["tid"]);
                pb_replace_with("tid", $t["tid"]);
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
            $failed = FALSE;
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

                        $iterCount = 0;
                        while (count($tans) < 100 && $iterCount < 10) {
                            $iterCount++;
                            $tanQuery = "SELECT tan FROM tans WHERE ";
                            // TODO: Is the query really complete?
                            // We have some TANs from the last iteration. Ensure that this works.
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

                        if (count($tans) < 100) {
                            pb_replace_with("main", "Error: ETANGENT");
                            $failed = TRUE;
                        }
                        else {
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
                                //message can be anything, as long as it does not contain \r or \n, content type is utf8
                                $msg =
                                    "Hello dear Sir/Mam,<br><br>" .
                                    "You are now registered at SCBanking with the account ID " . $_POST["userid"] . "<br>" .
                                    "These are the TAN numbers for your transactions:<br><br>";
                                //5 * 20 columns of numbers
                                for($i = 0; $i < count($tans);++$i){
                                    $msg .= $tans[$i];
                                    if(($i + 1) % 5 === 0) $msg .= "<br>";
                                    else $msg .= " &nbsp; ";
                                }
                                $msg .= "<br><br>Please print out these numbers and keep them at a secure place.<br>" .
                                    "They are needed to make transactions from your account<br><br>" .
                                    "Thank you very much,<br>Your SecureBanking-Team";
                                $mail_ret = send_mail(
                                //not all mail servers will accept any address... some do actually verify
                                    array("The SecureBank","scbanking@roschaumann.com"),
                                    //assuming that $user is an array and 1st, 2nd vals are name and email
                                    array($user[0],$user[1]),
                                    "Your tan numbers have arrived!!!",
                                    //mail coding is utf8
                                    $msg
                                );
                                echo $mail_ret . "\n";
                                $failed = $mail_ret != 0;

                            }
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
    case "edoapprovetransaction":
        if (!isset($_POST["tid"]) || !isset($_POST["success"])) {
            pb_replace_all("main", "edoverify_fail.html");
        } else {
            $success = $_POST["success"] === "true";
            if ($success) {
                $transaction = db_queryWith("SELECT * FROM transactions WHERE tid = :tid AND isVerified = 0", array("tid" => $tid));
                if ($transaction->rowCount() !== 1) {
                    pb_replace_with("main", "Transaction does not exist.");
                }
                $transaction = $transaction->fetch();

                $volume  = $transaction["volume"];
                $source = $transaction["sourceAccount"];
                $target = $transaction["targetAccount"];
                db_queryWith("UPDATE accounts SET balance = balance - :volume WHERE userid = :userid", array("userid" => $sourceAccount, "volume" => $volume));
                db_queryWith("UPDATE accounts SET balance = balance + :volume WHERE userid = :userid", array("userid" => $targetAccount, "volume" => $volume));
                header("Location: index.php?page=ehome");
            } else {
                db_queryWith("DELETE FROM transactions WHERE tid = :tid AND isVerified = 0", array("tid" => $_POST["tid"]));
                header("Location: index.php?page=ehome");
            }
        }
        break;
    case "etakeover":
        pb_replace_all("main", "etakeover.html");
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



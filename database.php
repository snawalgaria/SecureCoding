<?php

$_ENV["DBPdo"] = null;

function db_open() {
    try {
        $dbconfig = array("host"=>"localhost","name"=>"scbanking","user"=>"scbanking","password"=>"thisisasupersecurepassword");
        $_ENV["DBPdo"] = new PDO('mysql:host=' . $dbconfig["host"] . ';dbname=' . $dbconfig["name"], $dbconfig["user"], $dbconfig["password"]);

        // We could use foreign keys here, if we are sure that we use InnoDB.

        db_ensureExists("users", "`userid` INT(11) AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(64) NOT NULL, `email` VARCHAR(64) NOT NULL, `isEmployee` TINYINT(1) NOT NULL, `isVerified` TINYINT(1) NOT NULL, `credentials` TINYTEXT NOT NULL");

        // balance stores cents (not euros)
        db_ensureExists("accounts", "userid INT(11) NOT NULL PRIMARY KEY, balance INT(11) NOT NULL");
        // Transactions
        db_ensureExists("transactions", "tid INT(11) AUTO_INCREMENT PRIMARY KEY, sourceAccount INT(11) NOT NULL, targetAccount INT(11) NOT NULL, volume INT(11) NOT NULL, description TEXT NOT NULL, unixtime INT(11) NOT NULL, `isVerified` TINYINT(1) NOT NULL");
        // Generated TANs
        db_ensureExists("tans", "tan CHAR(15) PRIMARY KEY, userid INT(11) NOT NULL, used TINYINT(1) NOT NULL DEFAULT 0");
        return TRUE;
    } catch (Exception $e) {
        $_ENV["DBError"] = $e;
        return FALSE;
    }
}
function db_ensureExists($name, $createTableString) {
    db_exec("CREATE TABLE IF NOT EXISTS $name ($createTableString)");
}
function db_close() {
    $_ENV["DBPdo"] = null;
}
function db_query($sql) {
    $result = $_ENV["DBPdo"]->query($sql);
    return $result;
}
function db_exec($sql) {
    $result = $_ENV["DBPdo"]->exec($sql);
    return $result;
}
function db_queryWith($sql, $mapping) {
    $stmt = $_ENV["DBPdo"]->prepare($sql);
    if ($stmt->execute($mapping)) return $stmt;
    return FALSE;
}
function db_insert($dbname, $data, $returnInsertId = FALSE) {
    $keys = array();
    $values = array();
    foreach ($data as $key => $value) {
        $keys[] = $key;
        $values[] = ":" . $key;
    }
    $keys = join(",", $keys);
    $values = join(",", $values);
    $insertquery = "INSERT INTO `$dbname` ($keys) VALUES ($values)";
    db_queryWith($insertquery, $data);

    if ($returnInsertId) {
        return $_ENV["DBPdo"]->lastInsertId();
    }
}

?>
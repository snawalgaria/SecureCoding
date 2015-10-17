<?php


class Database {
    private static $pdo;
    public static function open() {
        try {
            $dbconfig = array("host"=>"localhost","name"=>"scbanking","user"=>"scbanking","password"=>"thisisasupersecurepassword");
            Database::$pdo = new PDO('mysql:host=' . $dbconfig["host"] . ';dbname=' . $dbconfig["name"], $dbconfig["user"], $dbconfig["password"]);

            // We could use foreign keys here, if we are sure that we use InnoDB.

            Database::ensureExists("users", "`userid` INT(11) AUTO_INCREMENT PRIMARY KEY, `email` VARCHAR(64) NOT NULL, `isEmployee` TINYINT(1) NOT NULL, `isVerified` TINYINT(1) NOT NULL, `credentials` TINYTEXT NOT NULL");
            Database::ensureExists("accounts", "userid INT(11) NOT NULL PRIMARY KEY, balance INT(11) NOT NULL");
            // Transactions
            Database::ensureExists("transactions", "tid INT(11) AUTO_INCREMENT PRIMARY KEY, sourceAccount INT(11) NOT NULL, targetAccount INT(11) NOT NULL, volume INT(11) NOT NULL, unixtime INT(11) NOT NULL");
            // Generated TANs
            Database::ensureExists("tans", "tan CHAR(15) PRIMARY KEY, userid INT(11) NOT NULL, used TINYINT(1) NOT NULL DEFAULT 0");
            return TRUE;
        } catch (Exception $e) {
            $_ENV["DBError"] = $e;
            return FALSE;
        }
    }
    public static function ensureExists($name, $createTableString) {
        Database::exec("CREATE TABLE IF NOT EXISTS $name ($createTableString)");
    }
    public static function close() {
        Database::$pdo = null;
    }
    public static function query($sql) {
        $result = Database::$pdo->query($sql);
        return $result;
    }
    public static function exec($sql) {
        $result = Database::$pdo->exec($sql);
        return $result;
    }
    public static function queryWith($sql, $mapping) {
        $stmt = Database::$pdo->prepare($sql);
        if ($stmt->execute($mapping)) return $stmt;
        return FALSE;
    }
    public static function insert($dbname, $data, $returnInsertId = FALSE) {
        $keys = array();
        $values = array();
        foreach ($data as $key => $value) {
            $keys[] = $key;
            $values[] = ":" . $key;
        }
        $keys = join(",", $keys);
        $values = join(",", $values);
        $insertquery = "INSERT INTO `$dbname` ($keys) VALUES ($values)";
        Database::queryWith($insertquery, $data);

        if ($returnInsertId) {
            return Database::$pdo->lastInsertId();
        }
    }
}

?>
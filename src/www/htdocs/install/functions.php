<?php
/**
 * conjoon
 * (c) 2002-2012 siteartwork.de/conjoon.org
 * licensing@conjoon.org
 *
 * $Author$
 * $Id$
 * $Date$
 * $Revision$
 * $LastChangedDate$
 * $LastChangedBy$
 * $URL$
 */

/**
 * Utility methods for the conjoon installation process.
 *
 * @author Thorsten Suckow-Homberg <tsuckow@conjoon.org>
 */

/**
 * Returns true if the key could be found in a previous version of
 * config.ini.php, otehrwise false.
 *
 * @param string $key
 *
 * @return boolean
 */
function conjoon_configInfoExists($key)
{
    $keys   = explode('.', $key);
    $key    = array_shift($keys);
    $remKey = implode('.', $keys);

    if (isset($_SESSION['config_info'])
        && isset($_SESSION['config_info'][$key])
        && isset($_SESSION['config_info'][$key][$remKey])) {
        return true;
    }


    return false;
}

/**
 * Returns the value found withing the config.ini.php for this key, or false
 * if it is not existing.
 *
 * @param string $key
 *
 * @return mixed
 */
function conjoon_getConfigInfo($key)
{
    if (!conjoon_configInfoExists($key)) {
        return false;
    }

    $keys   = explode('.', $key);
    $key    = array_shift($keys);
    $remKey = implode('.', $keys);

    return $_SESSION['config_info'][$key][$remKey];
}

/**
 * Returns a special snippet to be used with cache settings
 *
 * @param $wording
 * @param $key
 *
 * @return string
 */
function conjoon_cacheEnabledSnippet($wording, $key)
{
    if (!conjoon_configInfoExists($key)) {
        return "";
    }
    return
        "<table class=\"configInfo\"><tr>"
        . "<td class=\"key\">"
        . $wording ." (config.ini.php):</td>"
        . "<td class=\"value\">" . (conjoon_getConfigInfo($key)
            ? "Yes"
            : "No")
        . "</td></tr></table><br />";
}

/**
 * Returns a special snippet to be used with cache settings
 *
 * @param $wording
 * @param $key
 *
 * @return string
 *
 * @deprecated use conjoon_configInfoSnippet instead
 */
function conjoon_cacheDirSnippet($wording, $key)
{
    return conjoon_configInfoSnippet($wording, $key);
}

/**
 * Returns a snippet to be used for showing config.ini.php settings.
 *
 * @param $wording
 * @param $key
 *
 * @return string
 *
 */
function conjoon_configInfoSnippet($wording, $key)
{
    if (!conjoon_configInfoExists($key)) {
        return "";
    }

    return
        "<table class=\"configInfo\"><tr>"
        . "<td class=\"key\">"
        . $wording ." (config.ini.php):</td>"
        . "<td class=\"value\">" . (conjoon_getConfigInfo($key)
                                              ? conjoon_getConfigInfo($key)
                                              : '<code> - empty string - </code>')
        . "</td></tr></table><br />";
}

/**
 * Reads out the max allowed packets setting for the database type.
 * Returns "0" if the value for this db setting could not be retrieved.
 *
 * @param string $dbAdapter
 * @param array  $connectionInfo An array with the connection info to conenct
 * to the database and read out the value. Possible keys are:
 *  host
 *  user
 *  password
 *  database
 *  port
 * This function relies on the PDO extension of PHP.
 *
 * @return float
 */
function conjoon_getMaxAllowedPacket($dbAdapter, Array $connectionInfo)
{
    $dbType = strtolower(str_replace("pdo_", "", $dbAdapter));

    $bytes = 0;

    switch ($dbType) {
        case 'mysql':
            $db = new PDO(
                $dbType . ":" .
                "host=" . $connectionInfo['host'] . ";".
                "dbname=".$connectionInfo['database'].";".
                "port=".$connectionInfo['port'],
                $connectionInfo['user'], $connectionInfo['password']
            );

            $sql = "SHOW VARIABLES WHERE Variable_name = 'max_allowed_packet'";
            foreach ($db->query($sql) as $row) {
                $bytes = $row['Value'];
            }
            $db = null;
        break;

        default:
            die("No support for adapter \"$dbType\"");
        break;
    }

    return $bytes;
}

/**
 * Fills the db (specified in $config['database']) with the sql from
 * as found in the file specified via $path.
 *
 * @param string $sql
 * @param string $path
 * @param array $config
 *
 */
function conjoon_createTables($path, $dbAdapter, Array $config)
{
    $path = str_replace("\\", "/", $path);

    $dbType = strtolower(str_replace("pdo_", "", $dbAdapter));

    $bytes = 0;

    $prefix = $config['prefix'];

    switch ($dbType) {
        case 'mysql':
            $db = new PDO(
                $dbType . ":" .
                "host=" . $config['host'] . ";".
                "dbname=".$config['database'].";".
                "port=".$config['port'],
                $config['user'], $config['password']
            );

            conjoon_createDatastructure($db, $path, $prefix);

        break;

        default:
            die("No support for adapter \"$dbType\"");
        break;
    }
}

/**
 * Parses the sql file and executes the given statements.
 *
 * @param Object $db The db adapter to use
 * @param String $path The path to the sql file to execute
 * @param String $prefix The prefix to use for the tables
 */
function conjoon_createDatastructure($db, $path, $prefix = "")
{
    // check here if we need to migrate data
    $migrate = false;
    $sql = "SELECT * FROM ".$prefix."groupware_email_folders_users";
    $result = $db->query($sql);
    if (!$result) {
        $migrate = true;
    }

    // twitter migrate
    $twittersql = "SELECT twitter_id FROM ".$prefix."service_twitter_accounts";
    $twitterresult = $db->query($twittersql);

    if (!$twitterresult) {
        $db->query("TRUNCATE TABLE `".$prefix."service_twitter_accounts`");
    }

    $sqlFile = file_get_contents($path);

    // remove sql comments
    $sqlFile = preg_replace("/^--.*?$/ims", "", $sqlFile);
    //replace prefix
    $sqlFile = str_replace('{DATABASE.TABLE.PREFIX}', $prefix, $sqlFile);

    $statements = explode(';', $sqlFile);

    for ($i = 0, $len = count($statements); $i < $len; $i++) {
        $statement = trim($statements[$i]);
        if ($statement == "") {
            continue;
        }

        $db->query($statement);
    }

    if ($migrate) {
        sleep(1);
        // migrate to groupware_email_folders_users
        // get the user ids associated with the user accounts
        $folderAccountsQuery = "SELECT ".$prefix."groupware_email_folders_accounts.*, "
                             ."".$prefix."groupware_email_accounts.user_id FROM "
                             ." ".$prefix."groupware_email_folders_accounts, "
                             ."".$prefix."groupware_email_accounts"
                             ." ".$prefix."groupware_email_accounts "
                             ." WHERE ".$prefix."groupware_email_accounts.id = "
                             ."".$prefix."groupware_email_folders_accounts.groupware_email_accounts_id";

        $folderAccountsResult = $db->query($folderAccountsQuery);

        if (!$folderAccountsResult) {
            // error or something - return
            return;
        }
        $folderAccountsResultCount = 0;
        $folderMapping = array();
        foreach ($folderAccountsResult as $row) {
            $folderAccountsResultCount++;
            $folderMapping[] = $row;
        }

        if ($folderAccountsResultCount == 0) {
            return;
        }

        for ( $i = 0, $len = count($folderMapping); $i < $len; $i++) {
            $query = "INSERT INTO ".$prefix."groupware_email_folders_users "
                   ."(groupware_email_folders_id, users_id, relationship) "
                   ."VALUES ("
                   ."".$folderMapping[$i]['groupware_email_folders_id'].","
                   ."".$folderMapping[$i]['user_id'].","
                   ."'owner'"
                   .")";

            $db->query($query);
        }
    }

}

/**
 * Parses the sql file and executes the given statements.
 *
 * @param String $path The path to the sql file to execute
 * @param Object $dbAdapter
 * @param Array $dbConfig
 */
function conjoon_insertFixtures($path, $dbAdapter, Array $dbConfig)
{
    $path = str_replace("\\", "/", $path);

    $dbType = strtolower(str_replace("pdo_", "", $dbAdapter));

    $bytes = 0;

    $prefix = $dbConfig['prefix'];

    switch ($dbType) {
        case 'mysql':
            $db = new PDO(
                $dbType . ":" .
                    "host=" . $dbConfig['host'] . ";".
                    "dbname=".$dbConfig['database'].";".
                    "port=".$dbConfig['port'],
                $dbConfig['user'], $dbConfig['password']
            );
            break;

        default:
            die("No support for adapter \"$dbType\"");
            break;
    }

    $sqlFile = file_get_contents($path);

    // remove sql comments
    $sqlFile = preg_replace("/^--.*?$/ims", "", $sqlFile);
    //replace prefix
    $sqlFile = str_replace('{DATABASE.TABLE.PREFIX}', $prefix, $sqlFile);

    $statements = explode(';', $sqlFile);

    for ($i = 0, $len = count($statements); $i < $len; $i++) {
        $statement = trim($statements[$i]);
        if ($statement == "") {
            continue;
        }
        InstallLogger::getInstance()->logMessage("[FIXTURE]: " . $statement);
        if (!$db->query($statement)) {
            $err = $db->errorInfo();
            InstallLogger::getInstance()->logMessage(
                "[FIXTURE:FAILED]: "
                . (!empty($err) ? $err[1] : $statement)
            );
        };
    }

}

/**
 * Creates an admin user, only if the user table is empty.
 *
 * @param string $user
 * @param string $password
 * @param array $config
 *
 */
function conjoon_createAdmin($dbAdapter, $userData, Array $config)
{
    $dbType = strtolower(str_replace("pdo_", "", $dbAdapter));

    $bytes = 0;

    $prefix = $config['prefix'];

    switch ($dbType) {
        case 'mysql':
            $db = new PDO(
                $dbType . ":" .
                "host=" . $config['host'] . ";".
                "dbname=".$config['database'].";".
                "port=".$config['port'],
                $config['user'], $config['password']
            );

            $sql = "SELECT COUNT(id) as count_id FROM ".$prefix."users WHERE is_root = 1";
            $count = 0;
            foreach ($db->query($sql) as $row) {
                $count = $row['count_id'];
            }

            if ($count == 0) {
                $sql = "INSERT INTO ".$prefix."users (
                    firstname,
                    lastname,
                    email_address,
                    user_name,
                    password,
                    is_root
                ) VALUES (
                    ?,?,?,?,?,?
                )";
                $sth = $db->prepare($sql);
                $sth->execute(array(
                    $userData['firstname'],
                    $userData['lastname'],
                    $userData['email_address'],
                    $userData['user'],
                    md5($userData['password']),
                    1
                ));
            }

            $db = null;

        break;

        default:
            die("No support for adapter \"$dbType\"");
        break;
    }
}

/**
 * Removes a directory recursively.
 *
 * @param string $path
 */
function conjoon_rmdir($path)
{
    $path = rtrim(str_replace("\\", "/", $path), '/').'/';

    if (!file_exists($path)) {
        return;
    }

    $handle = opendir($path);

    for (;false !== ($file = readdir($handle));) {
        if($file != "." and $file != ".." ) {
            $fullpath= $path.$file;

            if(is_dir($fullpath)) {
                conjoon_rmdir($fullpath);
                if (!rmdir($fullpath)) {
                    InstallLogger::getInstance()
                        ->logMessage("ERROR: could not rmdir $fullpath");
                } else {
                    InstallLogger::getInstance()
                        ->logMessage("rmdir $fullpath");
                }
            } else {
                if (!unlink($fullpath)) {
                    InstallLogger::getInstance()
                        ->logMessage("ERROR: could not unlink $fullpath");
                } else {
                    InstallLogger::getInstance()
                        ->logMessage("unlink $fullpath");
                }
            }
        }
    }
    closedir($handle);
}

/**
 * Tries to create a directory. Will try to create each directory level.
 * if the second parameter is set to true, the created directory will be removed
 * afterwards.
 * The directory has to be specified absolutely.
 *
 */
function conjoon_mkdir($dir, $remove = false)
{
    if (strpos($dir, '/') !== 0 && strpos($dir, ':') !== 1) {
        return false;
    }

    $dir = str_replace("\\", "/", $dir);

    $parts  = explode('/', $dir);
    if ($parts[0] == "") {
        $parts[0] = "/";
    }
    $tmpDir = realpath($parts[0]);

    if ($tmpDir === false) {
        return false;
    }

    $existing   = array();
    $removeDirs = array();
    for ($i = 1, $len = count($parts); $i < $len+1; $i++) {

        if (!file_exists($tmpDir)) {

            $removeDirs[] = $tmpDir;

            $res = @mkdir($tmpDir);
            if ($res === false) {
                conjoon_rmdir($tmpDir);
                return false;
            }
        } else {
            $existing[$tmpDir] = true;
        }

        if (!isset($parts[$i])) {
            break;
        }
        $tmpDir .= '/' . $parts[$i];
    }

    $isCool = conjoon_validateDir($dir);

    if ($remove === true) {
        for ($i = count($removeDirs) -1; $i >= 0; $i--) {
            rmdir($removeDirs[$i]);
        }
    }

    return $isCool;
}

/**
 * Returns true if the specified directory is existing and both read/writable,
 * otherwise false.
 *
 */
function conjoon_validateDir($dir)
{
    $dir = @realpath($dir);

    if ($dir === false) {
        return false;
    }

    $dir = str_replace("\\", "/", $dir);
    $is_readable = @is_readable($dir);
    $is_writable = @is_writable($dir);
    if (!$is_readable || !$is_writable) {
        return false;
    }

    return true;
}


/**
 * Copies a directory recursively.
 *
 *
 */
function conjoon_copy($source, $target)
{
    $source = str_replace("\\", "/", $source);
    $target = str_replace("\\", "/", $target);

    if (is_dir($source)) {
        @mkdir($target);

        $d = dir($source);

        while (($entry = $d->read()) !== false) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            $_entry = $source . '/' . $entry;
            if (is_dir($_entry)) {
                conjoon_copy($_entry, $target . '/' . $entry);
                continue;
            }
            copy($_entry, $target . '/' . $entry);
        }

        $d->close();
    }else {
        copy($source, $target);
    }
}


/**
 * Takes a camelized string as the argument and returns it underscored, all
 * lowercased.
 * For example, passing the string "underScore" to this function will return
 * the string "under_score".
 *
 * @param {String} $value
 *
 * @return
 */
function conjoon_underscoreString($value)
{
    return strtolower(preg_replace('/([a-z])([A-Z])/', "$1_$2", $value));
}

class InstallLogger {

    private static $_logFile = "";

    private static $_instance = null;

    public static function getInstance($fileName = null)
    {
        if (!self::$_instance) {
            self::$_instance = new InstallLogger();
            self::$_logFile = $fileName;
            file_put_contents($fileName, "INSTALL LOG\n==========\n\n");
        }

        return self::$_instance;
    }

    public function logMessage($message, $date = null)
    {
        file_put_contents(
            self::$_logFile,
            date("H:i:s", time()) . " - " . $message . "\n",
            FILE_APPEND
        );
    }

}
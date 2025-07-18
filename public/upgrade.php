    }
    return false;
}

function _db_field_exists($table, $field)
{
    global $CONF;
    if ($CONF['database_type'] == 'pgsql') {
        return _pgsql_field_exists($table, $field);
    } elseif ($CONF['database_type'] == 'sqlite') {
        return _sqlite_field_exists($table, $field);
    } else {
        return _mysql_field_exists($table, $field);
    }
}

function _db_add_field($table, $field, $fieldtype, $after = '')
{
    global $CONF;

    $query = "ALTER TABLE " . table_by_key($table) . " ADD COLUMN $field $fieldtype";
    if ($CONF['database_type'] == 'mysql' && !empty($after)) {
        $query .= " AFTER $after "; # PgSQL does not support to specify where to add the column, MySQL does
    }

    if (!_db_field_exists(table_by_key($table), $field)) {
        db_query_parsed($query);
    } else {
        printdebug("field already exists: $table.$field");
    }
}

function echo_out($text)
{
    if (defined('PHPUNIT_TEST')) {
        //error_log("" . $text);
    } else {
        echo $text . "\n";
    }
}

function printdebug($text)
{
    if (safeget('debug') != "") {
        echo_out("<p style='color:#999'>$text</p>");
    }
}

$table = table_by_key('config');
if ($CONF['database_type'] == 'pgsql') {
    // check if table already exists, if so, don't recreate it
    if (!_pgsql_object_exists($table)) {
        $pgsql = "
            CREATE TABLE  $table ( 
                    id SERIAL,
                    name VARCHAR(20) NOT NULL UNIQUE,
                    value VARCHAR(20) NOT NULL,
                    PRIMARY KEY(id)
                    )";
        db_query_parsed($pgsql);
    }
} elseif (db_sqlite()) {
    $enc = 'PRAGMA encoding = "UTF-8"';
    db_query_parsed($enc);
    $sql = "
        CREATE TABLE {IF_NOT_EXISTS} $table (
        `id` {AUTOINCREMENT},
        `name` TEXT NOT NULL UNIQUE DEFAULT '',
        `value` TEXT NOT NULL DEFAULT ''
        )
    ";
    db_query_parsed($sql);
} else {
    $mysql = "
        CREATE TABLE {IF_NOT_EXISTS} $table (
        `id` {AUTOINCREMENT} {PRIMARY},
        `name`  VARCHAR(20) {LATIN1} NOT NULL DEFAULT '',
        `value` VARCHAR(20) {LATIN1} NOT NULL DEFAULT '',
        UNIQUE name ( `name` )
        )
    ";
    db_query_parsed($mysql, 0, " COMMENT = 'PostfixAdmin settings'");
}

$version = check_db_version(false);
return _do_upgrade($version);

function _do_upgrade(int $current_version): bool
{
    global $CONF;

    $target_version = $current_version;
    // Rather than being bound to an svn revision number, just look for the largest function name that matches upgrade_\d+...
    // $target_version = preg_replace('/[^0-9]/', '', '$Revision$');
    $funclist = get_defined_functions();

    $filter = function (string $name): bool {
        return preg_match('/upgrade_[\d]+(_mysql|_pgsql|_sqlite|_mysql_pgsql)?$/', $name) == 1;
    };

    $our_upgrade_functions = array_filter(
        $funclist['user'],
        $filter
    );


    foreach ($our_upgrade_functions as $function_name) {
        $bits = explode("_", $function_name);
        $function_number = $bits[1];
        // just find the highest number we need to go to, later on we go through numerically ascending.
        if ($function_number > $current_version && $function_number > $target_version) {
            $target_version = $function_number;
        }
    }

    if ($target_version == 0) {
        $target_version = $current_version;
    }


    if ($current_version == $target_version) {
        echo_out("all database updates are applied (version $current_version)");
        return true;
    }

    if ($current_version > $target_version) {
        // surely impossible?
        echo_out("Database - our current version $current_version is more recent than the $target_version - this shouldn't be possible?");
        return false;
    }

    echo_out("<p>Updating database:</p><p>- old version: $current_version; target version: $target_version</p>\n");
    echo_out("<div style='color:#999'>&nbsp;&nbsp;(If the update doesn't work, run setup.php?debug=1 to see the detailed error messages and SQL queries.)</div>");

    if (db_sqlite() && $current_version < 1824) {
        // Fast forward to the first revision supporting SQLite
        $current_version = 1823;
    }

    for ($i = $current_version + 1; $i <= $target_version; $i++) {
        $function = "upgrade_$i";
        $function_mysql_pgsql = $function . "_mysql_pgsql";
        $function_mysql = $function . "_mysql";
        $function_pgsql = $function . "_pgsql";
        $function_sqlite = $function . "_sqlite";

        if (function_exists($function)) {
            echo_out("<p>updating to version $i (all databases)...");
            $function();
            echo_out(" &nbsp; done");
        }
        if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli' || $CONF['database_type'] == 'pgsql') {
            if (function_exists($function_mysql_pgsql)) {
                echo_out("<p>updating to version $i (MySQL and PgSQL)...");
                $function_mysql_pgsql();
                echo_out(" &nbsp; done");
            }
        }
        if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli') {
            if (function_exists($function_mysql)) {
                echo_out("<p>updating to version $i (MySQL)...");
                $function_mysql();
                echo_out(" &nbsp; done");
            }
        } elseif (db_sqlite()) {
            if (function_exists($function_sqlite)) {
                echo_out("<p>updating to version $i (SQLite)...");
                $function_sqlite();
                echo_out(" &nbsp; done");
            }
        } elseif ($CONF['database_type'] == 'pgsql') {
            if (function_exists($function_pgsql)) {
                echo_out("<p>updating to version $i (PgSQL)...");
                $function_pgsql();
                echo_out(" &nbsp; done");
            }
        }
        // Update config table so we don't run the same query twice in the future.
        $table = table_by_key('config');
        $sql = "UPDATE $table SET value = :value WHERE name = 'version'";
        db_execute($sql, ['value' => $i]);
    }
    return true;
}

/**
 * Replaces database specific parts in a query
 * @param string sql query with placeholders
 * @param int (optional) whether errors should be ignored (0=false)
 * @param string (optional) MySQL specific code to attach, useful for COMMENT= on CREATE TABLE
 * @return void
 */

function db_query_parsed($sql, $ignore_errors = 0, $attach_mysql = "")
{
    global $CONF;

    if (db_mysql()) {
        $replace = array(
            '{AUTOINCREMENT}' => 'int(11) not null auto_increment',
            '{PRIMARY}' => 'primary key',
            '{UNSIGNED}' => 'unsigned',
            '{FULLTEXT}' => 'FULLTEXT',
            '{BOOLEAN}' => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(false) . "'",
            '{BOOLEAN_TRUE}' => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(true) . "'",
            '{UTF-8}' => '/*!40100 CHARACTER SET utf8mb4 */',
            '{LATIN1}' => '/*!40100 CHARACTER SET latin1 COLLATE latin1_general_ci */',
            '{IF_NOT_EXISTS}' => 'IF NOT EXISTS',
            '{RENAME_COLUMN}' => 'CHANGE COLUMN',
            '{MYISAM}' => '',
            '{INNODB}' => 'ENGINE=InnoDB',
            '{INT}' => 'integer NOT NULL DEFAULT 0',
            '{BIGINT}' => 'bigint NOT NULL DEFAULT 0',
            '{DATETIME}' => "datetime NOT NULL default '2000-01-01 00:00:00'", # different from {DATE} only for MySQL
            '{DATE}' => "timestamp NOT NULL default '2000-01-01'", # MySQL needs a sane default (no default is interpreted as CURRENT_TIMESTAMP, which is ...
            '{DATEFUTURE}' => "timestamp NOT NULL default '2038-01-18'", # different default timestamp for vacation.activeuntil
            '{DATECURRENT}' => 'timestamp NOT NULL default CURRENT_TIMESTAMP', # only allowed once per table in MySQL
            '{COLLATE}' => "CHARACTER SET latin1 COLLATE latin1_general_ci", # just incase someone has a unicode collation set.

        );
        $sql = "$sql $attach_mysql";
    } elseif (db_sqlite()) {
        $replace = array(
            '{AUTOINCREMENT}' => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL',
            '{PRIMARY}' => 'PRIMARY KEY',
            '{UNSIGNED}' => 'unsigned',
            '{FULLTEXT}' => 'text',
            '{BOOLEAN}' => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(false) . "'",
            '{BOOLEAN_TRUE}' => "tinyint(1) NOT NULL DEFAULT '" . db_get_boolean(true) . "'",
            '{UTF-8}' => '',
            '{LATIN1}' => '',
            '{IF_NOT_EXISTS}' => 'IF NOT EXISTS',
            '{RENAME_COLUMN}' => 'CHANGE COLUMN',
            '{MYISAM}' => '',
            '{INNODB}' => '',
            '{INT}' => 'int(11) NOT NULL DEFAULT 0',
            '{BIGINT}' => 'bigint(20) NOT NULL DEFAULT 0',
            '{DATETIME}' => "datetime NOT NULL default '2000-01-01'",
            '{DATE}' => "datetime NOT NULL default '2000-01-01'",
            '{DATEFUTURE}' => "datetime NOT NULL default '2038-01-18'", # different default timestamp for vacation.activeuntil
            '{DATECURRENT}' => 'datetime NOT NULL default CURRENT_TIMESTAMP',
            '{COLLATE}' => ''
        );
    } elseif ($CONF['database_type'] == 'pgsql') {
        $replace = array(
            '{AUTOINCREMENT}' => 'SERIAL',
            '{PRIMARY}' => 'primary key',
            '{UNSIGNED}' => '',
            '{FULLTEXT}' => '',
            '{BOOLEAN}' => "BOOLEAN NOT NULL DEFAULT '" . db_get_boolean(false) . "'",
            '{BOOLEAN_TRUE}' => "BOOLEAN NOT NULL DEFAULT '" . db_get_boolean(true) . "'",
            '{UTF-8}' => '', # UTF-8 is simply ignored.
            '{LATIN1}' => '', # same for latin1
            '{IF_NOT_EXISTS}' => '', # does not work with PgSQL
            '{RENAME_COLUMN}' => 'ALTER COLUMN', # PgSQL : ALTER TABLE x RENAME x TO y
            '{MYISAM}' => '',
            '{INNODB}' => '',
            '{INT}' => 'integer NOT NULL DEFAULT 0',
            '{BIGINT}' => 'bigint NOT NULL DEFAULT 0',
            'int(1)' => 'int',
            'int(10)' => 'int',
            'int(11)' => 'int',
            'int(4)' => 'int',
            '{DATETIME}' => "timestamp with time zone default '2000-01-01'", # stay in sync with MySQL
            '{DATE}' => "timestamp with time zone default '2000-01-01'", # stay in sync with MySQL
            '{DATEFUTURE}' => "timestamp with time zone default '2038-01-18'", # stay in sync with MySQL
            '{DATECURRENT}' => 'timestamp with time zone default now()',
            '{COLLATE}' => '',
        );
    } else {
        echo_out("Sorry, unsupported database type " . $CONF['database_type']);
        exit;
    }

    $replace['{BOOL_TRUE}'] = db_get_boolean(true);
    $replace['{BOOL_FALSE}'] = db_get_boolean(false);

    $query = trim(str_replace(array_keys($replace), $replace, $sql));

    $debug = safeget('debug', '') != '';

    if ($debug) {
        printdebug($query);
    }

    try {
        $result = db_execute($query, array(), true);
    } catch (PDOException $e) {
        error_log("Exception running PostfixAdmin query: $query " . $e);
        if ($debug) {
            echo_out("<div style='color:#f00'>" . $e->getMessage() . "</div>");
        }

        throw new \Exception("Postfixadmin DB update failed. Please check your PHP error_log");
    }
}

/**
 * @param string $table
 * @param string $index
 * @return string
 */
function _drop_index($table, $index)
{
    global $CONF;
    $table = table_by_key($table);

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli') {
        return "ALTER TABLE $table DROP INDEX $index";
    } elseif ($CONF['database_type'] == 'pgsql' || db_sqlite()) {
        return "DROP INDEX $index"; # Index names are unique with a DB for PostgreSQL
    } else {
        echo_out("Sorry, unsupported database type " . $CONF['database_type']);
        exit;
    }
}

/**
 * @param string $table
 * @param string $indexname
 * @param string $fieldlist
 * @return string
 */
function _add_index($table, $indexname, $fieldlist)
{
    global $CONF;
    $table = table_by_key($table);

    if ($CONF['database_type'] == 'mysql' || $CONF['database_type'] == 'mysqli') {
        $fieldlist = str_replace(',', '`,`', $fieldlist); # fix quoting if index contains multiple fields
        return "ALTER TABLE $table ADD INDEX `$indexname` ( `$fieldlist` )";
    } elseif ($CONF['database_type'] == 'pgsql') {
        $pgindexname = $table . "_" . $indexname . '_idx';
        return "CREATE INDEX $pgindexname ON $table($fieldlist);"; # Index names are unique with a DB for PostgreSQL
    } else {
        echo_out("Sorry, unsupported database type " . $CONF['database_type']);
        exit;
    }
}

/**
 * @return void
 */
function upgrade_1_mysql()
{
    #
    # creating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to start with a pre-2.1 database layout)

    // CREATE MYSQL DATABASE TABLES.
    $admin = table_by_key('admin');
    $alias = table_by_key('alias');
    $domain = table_by_key('domain');
    $domain_admins = table_by_key('domain_admins');
    $log = table_by_key('log');
    $mailbox = table_by_key('mailbox');
    $vacation = table_by_key('vacation');

    $sql = array();
    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $admin (
      `username` varchar(255) NOT NULL default '',
      `password` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`username`)
  ) {COLLATE} COMMENT='Postfix Admin - Virtual Admins';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $alias (
      `address` varchar(255) NOT NULL default '',
      `goto` text NOT NULL,
      `domain` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      `x_regexp` tinyint(1) NOT NULL default '0',
      PRIMARY KEY  (`address`)
    ) {COLLATE} COMMENT='Postfix Admin - Virtual Aliases'; ";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $domain (
      `domain` varchar(255) NOT NULL default '',
      `description` varchar(255) NOT NULL default '',
      `aliases` int(10) NOT NULL default '0',
      `mailboxes` int(10) NOT NULL default '0',
      `maxquota` bigint(20) NOT NULL default '0',
      `quota` bigint(20) NOT NULL default '0',
      `transport` varchar(255) default NULL,
      `backupmx` tinyint(1) NOT NULL default '0',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`domain`)
    ) {COLLATE} COMMENT='Postfix Admin - Virtual Domains'; ";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $domain_admins (
      `username` varchar(255) NOT NULL default '',
      `domain` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      KEY username (`username`)
    ) {COLLATE} COMMENT='Postfix Admin - Domain Admins';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $log (
      `timestamp` {DATETIME},
      `username` varchar(255) NOT NULL default '',
      `domain` varchar(255) NOT NULL default '',
      `action` varchar(255) NOT NULL default '',
      `data` varchar(255) NOT NULL default '',
      KEY timestamp (`timestamp`)
    ) {COLLATE} COMMENT='Postfix Admin - Log';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $mailbox (
      `username` varchar(255) NOT NULL default '',
      `password` varchar(255) NOT NULL default '',
      `name` varchar(255) NOT NULL default '',
      `maildir` varchar(255) NOT NULL default '',
      `quota` bigint(20) NOT NULL default '0',
      `domain` varchar(255) NOT NULL default '',
      `created` {DATETIME},
      `modified` {DATETIME},
      `active` tinyint(1) NOT NULL default '1',
      PRIMARY KEY  (`username`)
    ) {COLLATE} COMMENT='Postfix Admin - Virtual Mailboxes';";

    $sql[] = "
    CREATE TABLE {IF_NOT_EXISTS} $vacation ( 
        email varchar(255) NOT NULL , 
        subject varchar(255) NOT NULL, 
        body text NOT NULL, 
        cache text NOT NULL, 
        domain varchar(255) NOT NULL , 
        created {DATETIME},
        active tinyint(4) NOT NULL default '1', 
        PRIMARY KEY (email), 
        KEY email (email) 
    ) {INNODB} {COLLATE} COMMENT='Postfix Admin - Virtual Vacation' ;";

    foreach ($sql as $query) {
        db_query_parsed($query);
    }
}

/**
 * @return void
 */
function upgrade_2_mysql()
{
    #
    # updating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to update a pre-2.1 database)

    # upgrade pre-2.1 database
    # from TABLE_BACKUP_MX.TXT
    $table_domain = table_by_key('domain');
    if (!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;", true);
    }
    if (!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx {BOOLEAN} AFTER transport;", true);
    }
}

/**
 * @return void
 */
function upgrade_2_pgsql()
{
    if (!_pgsql_object_exists(table_by_key('domain'))) {
        db_query_parsed("
            CREATE TABLE " . table_by_key('domain') . " (
                domain character varying(255) NOT NULL,
                description character varying(255) NOT NULL default '',
                aliases integer NOT NULL default 0,
                mailboxes integer NOT NULL default 0,
                maxquota integer NOT NULL default 0,
                quota integer NOT NULL default 0,
                transport character varying(255) default NULL,
                backupmx boolean NOT NULL default false,
                created timestamp with time zone default now(),
                modified timestamp with time zone default now(),
                active boolean NOT NULL default true,
                Constraint \"domain_key\" Primary Key (\"domain\")
            ); ");
        db_query_parsed("CREATE INDEX domain_domain_active ON " . table_by_key('domain') . "(domain,active);");
        db_query_parsed("COMMENT ON TABLE " . table_by_key('domain') . " IS 'Postfix Admin - Virtual Domains'");
    }
    if (!_pgsql_object_exists(table_by_key('admin'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key("admin") . ' (
              "username" character varying(255) NOT NULL,
              "password" character varying(255) NOT NULL default \'\',
              "created" timestamp with time zone default now(),
              "modified" timestamp with time zone default now(),
              "active" boolean NOT NULL default true,
            Constraint "admin_key" Primary Key ("username")
        )');
        db_query_parsed("COMMENT ON TABLE " . table_by_key('admin') . " IS 'Postfix Admin - Virtual Admins'");
    }

    if (!_pgsql_object_exists(table_by_key('alias'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key("alias") . ' (
             address character varying(255) NOT NULL,
             goto text NOT NULL,
             domain character varying(255) NOT NULL REFERENCES "' . table_by_key("domain") . '",
             created timestamp with time zone default now(),
             modified timestamp with time zone default now(),
             active boolean NOT NULL default true,
             Constraint "alias_key" Primary Key ("address")
            );');
        db_query_parsed('CREATE INDEX alias_address_active ON ' . table_by_key("alias") . '(address,active)');
        db_query_parsed('COMMENT ON TABLE ' . table_by_key("alias") . ' IS \'Postfix Admin - Virtual Aliases\'');
    }

    if (!_pgsql_object_exists(table_by_key('domain_admins'))) {
        db_query_parsed('
        CREATE TABLE ' . table_by_key('domain_admins') . ' (
             username character varying(255) NOT NULL,
             domain character varying(255) NOT NULL REFERENCES "' . table_by_key('domain') . '",
             created timestamp with time zone default now(),
             active boolean NOT NULL default true
            );');
        db_query_parsed('COMMENT ON TABLE ' . table_by_key('domain_admins') . ' IS \'Postfix Admin - Domain Admins\'');
    }

    if (!_pgsql_object_exists(table_by_key('log'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('log') . ' (
             timestamp timestamp with time zone default now(),
             username character varying(255) NOT NULL default \'\',
             domain character varying(255) NOT NULL default \'\',
             action character varying(255) NOT NULL default \'\',
             data text NOT NULL default \'\'
            );');
        db_query_parsed('COMMENT ON TABLE ' . table_by_key('log') . ' IS \'Postfix Admin - Log\'');
    }
    if (!_pgsql_object_exists(table_by_key('mailbox'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('mailbox') . ' (
                 username character varying(255) NOT NULL,
                 password character varying(255) NOT NULL default \'\',
                 name character varying(255) NOT NULL default \'\',
                 maildir character varying(255) NOT NULL default \'\',
                 quota integer NOT NULL default 0,
                 domain character varying(255) NOT NULL REFERENCES "' . table_by_key('domain') . '",
                 created timestamp with time zone default now(),
                 modified timestamp with time zone default now(),
                 active boolean NOT NULL default true,
                 Constraint "mailbox_key" Primary Key ("username")
                );');
        db_query_parsed('CREATE INDEX mailbox_username_active ON ' . table_by_key('mailbox') . '(username,active);');
        db_query_parsed('COMMENT ON TABLE ' . table_by_key('mailbox') . ' IS \'Postfix Admin - Virtual Mailboxes\'');
    }

    if (!_pgsql_object_exists(table_by_key('vacation'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('vacation') . ' (
                email character varying(255) PRIMARY KEY,
                subject character varying(255) NOT NULL,
                body text NOT NULL ,
                cache text NOT NULL ,
                "domain" character varying(255) NOT NULL REFERENCES "' . table_by_key('domain') . '",
                created timestamp with time zone DEFAULT now(),
                active boolean DEFAULT true NOT NULL
            );');
        db_query_parsed('CREATE INDEX vacation_email_active ON ' . table_by_key('vacation') . '(email,active);');
    }

    if (!_pgsql_object_exists(table_by_key('vacation_notification'))) {
        db_query_parsed('
            CREATE TABLE ' . table_by_key('vacation_notification') . ' (
                on_vacation character varying(255) NOT NULL REFERENCES ' . table_by_key('vacation') . '(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
                CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified)
            );
        ');
    }
}

/**
 * @return void
 */

/**
 * @return void
 */

/**
 * Changes between 2.1 and moving to sf.net
 * @return void
 */
function upgrade_4_pgsql()
{
    $table_domain = table_by_key('domain');
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain_admins = table_by_key('domain_admins');
    $table_log = table_by_key('log');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');
    $table_vacation_notification = table_by_key('vacation_notification');

    if (!_pgsql_field_exists($table_domain, 'quota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int NOT NULL default '0'");
    }

    db_query_parsed("ALTER TABLE $table_domain ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('domain_domain_active')) {
        db_query_parsed("CREATE INDEX domain_domain_active ON $table_domain(domain,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN address DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('alias_address_active')) {
        db_query_parsed("CREATE INDEX alias_address_active ON $table_alias(address,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN ");
    db_query_parsed("ALTER TABLE $table_log RENAME COLUMN data TO data_old;");
    db_query_parsed("ALTER TABLE $table_log ADD COLUMN data text NOT NULL default '';");
    db_query_parsed("UPDATE $table_log SET data = CAST(data_old AS text);");
    db_query_parsed("ALTER TABLE $table_log DROP COLUMN data_old;");
    db_query_parsed("COMMIT");

    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN;");
    db_query_parsed("ALTER TABLE $table_mailbox RENAME COLUMN domain TO domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN domain varchar(255) REFERENCES $table_domain (domain);");
    db_query_parsed("UPDATE $table_mailbox SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('mailbox_username_active')) {
        db_query_parsed("CREATE INDEX mailbox_username_active ON $table_mailbox(username,active)");
    }


    db_query_parsed("ALTER TABLE $table_vacation ALTER COLUMN body SET DEFAULT ''");
    if (_pgsql_field_exists($table_vacation, 'cache')) {
        db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN cache");
    }

    db_query_parsed(" BEGIN; ");
    db_query_parsed("ALTER TABLE $table_vacation RENAME COLUMN domain to domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain varchar(255) REFERENCES $table_domain;");
    db_query_parsed("UPDATE $table_vacation SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('vacation_email_active')) {
        db_query_parsed("CREATE INDEX vacation_email_active ON $table_vacation(email,active)");
    }

    if (!_pgsql_object_exists($table_vacation_notification)) {
        db_query_parsed("
            CREATE TABLE $table_vacation_notification (
                on_vacation character varying(255) NOT NULL REFERENCES $table_vacation(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
        CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified));");
    }
}


/**
 * @return void
 */
function upgrade_3_mysql()
{
    #
    # updating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to update a pre-2.1 database)

    # upgrade pre-2.1 database
    # from TABLE_CHANGES.TXT
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');

    if (!_mysql_field_exists($table_admin, 'created')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_admin, 'modified')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'created')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'modified')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'created')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'modified')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'aliases')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN aliases INT(10) DEFAULT '-1' NOT NULL AFTER description;");
    }
    if (!_mysql_field_exists($table_domain, 'mailboxes')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN mailboxes INT(10) DEFAULT '-1' NOT NULL AFTER aliases;");
    }
    if (!_mysql_field_exists($table_domain, 'maxquota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN maxquota INT(10) DEFAULT '-1' NOT NULL AFTER mailboxes;");
    }
    if (!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;");
    }
    if (!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx TINYINT(1) DEFAULT '0' NOT NULL AFTER transport;");
    }
    if (!_mysql_field_exists($table_mailbox, 'created')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'modified')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'quota')) {
        db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;");
    }
    if (!_mysql_field_exists($table_vacation, 'domain')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;");
    }
    if (!_mysql_field_exists($table_vacation, 'created')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN created {DATETIME} AFTER domain;");
    }
    if (!_mysql_field_exists($table_vacation, 'active')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN active TINYINT(1) DEFAULT '1' NOT NULL AFTER created;");
    }
    db_query_parsed("ALTER TABLE $table_vacation DROP PRIMARY KEY");
    db_query_parsed("ALTER TABLE $table_vacation ADD PRIMARY KEY(email)");
    db_query_parsed("UPDATE $table_vacation SET domain=SUBSTRING_INDEX(email, '@', -1) WHERE email=email;");
}

/**
 * @return void
 */
function upgrade_4_mysql()
{ # MySQL only
    # changes between 2.1 and moving to sourceforge

    return; // as the above _mysql functions are disabled; this one will just error for a new db.
    $table_domain = table_by_key('domain');

    db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int(10) NOT NULL default '0' AFTER maxquota", true);
    # Possible errors that can be ignored:
    # - Invalid query: Table 'postfix.domain' doesn't exist
}

/**
 * Changes between 2.1 and moving to sf.net
 * @return void
 */
function upgrade_4_pgsql()
{
    $table_domain = table_by_key('domain');
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain_admins = table_by_key('domain_admins');
    $table_log = table_by_key('log');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');
    $table_vacation_notification = table_by_key('vacation_notification');

    if (!_pgsql_field_exists($table_domain, 'quota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int NOT NULL default '0'");
    }

    db_query_parsed("ALTER TABLE $table_domain ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('domain_domain_active')) {
        db_query_parsed("CREATE INDEX domain_domain_active ON $table_domain(domain,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN address DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('alias_address_active')) {
        db_query_parsed("CREATE INDEX alias_address_active ON $table_alias(address,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN ");
    db_query_parsed("ALTER TABLE $table_log RENAME COLUMN data TO data_old;");
    db_query_parsed("ALTER TABLE $table_log ADD COLUMN data text NOT NULL default '';");
    db_query_parsed("UPDATE $table_log SET data = CAST(data_old AS text);");
    db_query_parsed("ALTER TABLE $table_log DROP COLUMN data_old;");
    db_query_parsed("COMMIT");

    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN;");
    db_query_parsed("ALTER TABLE $table_mailbox RENAME COLUMN domain TO domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN domain varchar(255) REFERENCES $table_domain (domain);");
    db_query_parsed("UPDATE $table_mailbox SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('mailbox_username_active')) {
        db_query_parsed("CREATE INDEX mailbox_username_active ON $table_mailbox(username,active)");
    }


    db_query_parsed("ALTER TABLE $table_vacation ALTER COLUMN body SET DEFAULT ''");
    if (_pgsql_field_exists($table_vacation, 'cache')) {
        db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN cache");
    }

    db_query_parsed(" BEGIN; ");
    db_query_parsed("ALTER TABLE $table_vacation RENAME COLUMN domain to domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain varchar(255) REFERENCES $table_domain;");
    db_query_parsed("UPDATE $table_vacation SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('vacation_email_active')) {
        db_query_parsed("CREATE INDEX vacation_email_active ON $table_vacation(email,active)");
    }

    if (!_pgsql_object_exists($table_vacation_notification)) {
        db_query_parsed("
            CREATE TABLE $table_vacation_notification (
                on_vacation character varying(255) NOT NULL REFERENCES $table_vacation(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
        CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified));");
    }
}


/**
 * @return void
 */
function upgrade_3_mysql()
{
    #
    # updating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to update a pre-2.1 database)

    # upgrade pre-2.1 database
    # from TABLE_CHANGES.TXT
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');

    if (!_mysql_field_exists($table_admin, 'created')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_admin, 'modified')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'created')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'modified')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'created')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'modified')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'aliases')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN aliases INT(10) DEFAULT '-1' NOT NULL AFTER description;");
    }
    if (!_mysql_field_exists($table_domain, 'mailboxes')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN mailboxes INT(10) DEFAULT '-1' NOT NULL AFTER aliases;");
    }
    if (!_mysql_field_exists($table_domain, 'maxquota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN maxquota INT(10) DEFAULT '-1' NOT NULL AFTER mailboxes;");
    }
    if (!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;");
    }
    if (!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx TINYINT(1) DEFAULT '0' NOT NULL AFTER transport;");
    }
    if (!_mysql_field_exists($table_mailbox, 'created')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'modified')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'quota')) {
        db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;");
    }
    if (!_mysql_field_exists($table_vacation, 'domain')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;");
    }
    if (!_mysql_field_exists($table_vacation, 'created')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN created {DATETIME} AFTER domain;");
    }
    if (!_mysql_field_exists($table_vacation, 'active')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN active TINYINT(1) DEFAULT '1' NOT NULL AFTER created;");
    }
    db_query_parsed("ALTER TABLE $table_vacation DROP PRIMARY KEY");
    db_query_parsed("ALTER TABLE $table_vacation ADD PRIMARY KEY(email)");
    db_query_parsed("UPDATE $table_vacation SET domain=SUBSTRING_INDEX(email, '@', -1) WHERE email=email;");
}

/**
 * @return void
 */
function upgrade_4_mysql()
{ # MySQL only
    # changes between 2.1 and moving to sourceforge

    return; // as the above _mysql functions are disabled; this one will just error for a new db.
    $table_domain = table_by_key('domain');

    db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int(10) NOT NULL default '0' AFTER maxquota", true);
    # Possible errors that can be ignored:
    # - Invalid query: Table 'postfix.domain' doesn't exist
}

/**
 * Changes between 2.1 and moving to sf.net
 * @return void
 */
function upgrade_4_pgsql()
{
    $table_domain = table_by_key('domain');
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain_admins = table_by_key('domain_admins');
    $table_log = table_by_key('log');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');
    $table_vacation_notification = table_by_key('vacation_notification');

    if (!_pgsql_field_exists($table_domain, 'quota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int NOT NULL default '0'");
    }

    db_query_parsed("ALTER TABLE $table_domain ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('domain_domain_active')) {
        db_query_parsed("CREATE INDEX domain_domain_active ON $table_domain(domain,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN address DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('alias_address_active')) {
        db_query_parsed("CREATE INDEX alias_address_active ON $table_alias(address,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN ");
    db_query_parsed("ALTER TABLE $table_log RENAME COLUMN data TO data_old;");
    db_query_parsed("ALTER TABLE $table_log ADD COLUMN data text NOT NULL default '';");
    db_query_parsed("UPDATE $table_log SET data = CAST(data_old AS text);");
    db_query_parsed("ALTER TABLE $table_log DROP COLUMN data_old;");
    db_query_parsed("COMMIT");

    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN;");
    db_query_parsed("ALTER TABLE $table_mailbox RENAME COLUMN domain TO domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN domain varchar(255) REFERENCES $table_domain (domain);");
    db_query_parsed("UPDATE $table_mailbox SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('mailbox_username_active')) {
        db_query_parsed("CREATE INDEX mailbox_username_active ON $table_mailbox(username,active)");
    }


    db_query_parsed("ALTER TABLE $table_vacation ALTER COLUMN body SET DEFAULT ''");
    if (_pgsql_field_exists($table_vacation, 'cache')) {
        db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN cache");
    }

    db_query_parsed(" BEGIN; ");
    db_query_parsed("ALTER TABLE $table_vacation RENAME COLUMN domain to domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain varchar(255) REFERENCES $table_domain;");
    db_query_parsed("UPDATE $table_vacation SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('vacation_email_active')) {
        db_query_parsed("CREATE INDEX vacation_email_active ON $table_vacation(email,active)");
    }

    if (!_pgsql_object_exists($table_vacation_notification)) {
        db_query_parsed("
            CREATE TABLE $table_vacation_notification (
                on_vacation character varying(255) NOT NULL REFERENCES $table_vacation(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
        CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified));");
    }
}


/**
 * @return void
 */
function upgrade_3_mysql()
{
    #
    # updating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to update a pre-2.1 database)

    # upgrade pre-2.1 database
    # from TABLE_CHANGES.TXT
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');

    if (!_mysql_field_exists($table_admin, 'created')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_admin, 'modified')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'created')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'modified')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'created')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'modified')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'aliases')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN aliases INT(10) DEFAULT '-1' NOT NULL AFTER description;");
    }
    if (!_mysql_field_exists($table_domain, 'mailboxes')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN mailboxes INT(10) DEFAULT '-1' NOT NULL AFTER aliases;");
    }
    if (!_mysql_field_exists($table_domain, 'maxquota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN maxquota INT(10) DEFAULT '-1' NOT NULL AFTER mailboxes;");
    }
    if (!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;");
    }
    if (!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx TINYINT(1) DEFAULT '0' NOT NULL AFTER transport;");
    }
    if (!_mysql_field_exists($table_mailbox, 'created')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'modified')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'quota')) {
        db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;");
    }
    if (!_mysql_field_exists($table_vacation, 'domain')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;");
    }
    if (!_mysql_field_exists($table_vacation, 'created')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN created {DATETIME} AFTER domain;");
    }
    if (!_mysql_field_exists($table_vacation, 'active')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN active TINYINT(1) DEFAULT '1' NOT NULL AFTER created;");
    }
    db_query_parsed("ALTER TABLE $table_vacation DROP PRIMARY KEY");
    db_query_parsed("ALTER TABLE $table_vacation ADD PRIMARY KEY(email)");
    db_query_parsed("UPDATE $table_vacation SET domain=SUBSTRING_INDEX(email, '@', -1) WHERE email=email;");
}

/**
 * @return void
 */
function upgrade_4_mysql()
{ # MySQL only
    # changes between 2.1 and moving to sourceforge

    return; // as the above _mysql functions are disabled; this one will just error for a new db.
    $table_domain = table_by_key('domain');

    db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int(10) NOT NULL default '0' AFTER maxquota", true);
    # Possible errors that can be ignored:
    # - Invalid query: Table 'postfix.domain' doesn't exist
}

/**
 * Changes between 2.1 and moving to sf.net
 * @return void
 */
function upgrade_4_pgsql()
{
    $table_domain = table_by_key('domain');
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain_admins = table_by_key('domain_admins');
    $table_log = table_by_key('log');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');
    $table_vacation_notification = table_by_key('vacation_notification');

    if (!_pgsql_field_exists($table_domain, 'quota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int NOT NULL default '0'");
    }

    db_query_parsed("ALTER TABLE $table_domain ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('domain_domain_active')) {
        db_query_parsed("CREATE INDEX domain_domain_active ON $table_domain(domain,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN address DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('alias_address_active')) {
        db_query_parsed("CREATE INDEX alias_address_active ON $table_alias(address,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN ");
    db_query_parsed("ALTER TABLE $table_log RENAME COLUMN data TO data_old;");
    db_query_parsed("ALTER TABLE $table_log ADD COLUMN data text NOT NULL default '';");
    db_query_parsed("UPDATE $table_log SET data = CAST(data_old AS text);");
    db_query_parsed("ALTER TABLE $table_log DROP COLUMN data_old;");
    db_query_parsed("COMMIT");

    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN;");
    db_query_parsed("ALTER TABLE $table_mailbox RENAME COLUMN domain TO domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN domain varchar(255) REFERENCES $table_domain (domain);");
    db_query_parsed("UPDATE $table_mailbox SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('mailbox_username_active')) {
        db_query_parsed("CREATE INDEX mailbox_username_active ON $table_mailbox(username,active)");
    }


    db_query_parsed("ALTER TABLE $table_vacation ALTER COLUMN body SET DEFAULT ''");
    if (_pgsql_field_exists($table_vacation, 'cache')) {
        db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN cache");
    }

    db_query_parsed(" BEGIN; ");
    db_query_parsed("ALTER TABLE $table_vacation RENAME COLUMN domain to domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain varchar(255) REFERENCES $table_domain;");
    db_query_parsed("UPDATE $table_vacation SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('vacation_email_active')) {
        db_query_parsed("CREATE INDEX vacation_email_active ON $table_vacation(email,active)");
    }

    if (!_pgsql_object_exists($table_vacation_notification)) {
        db_query_parsed("
            CREATE TABLE $table_vacation_notification (
                on_vacation character varying(255) NOT NULL REFERENCES $table_vacation(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
        CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified));");
    }
}


/**
 * @return void
 */
function upgrade_3_mysql()
{
    #
    # updating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to update a pre-2.1 database)

    # upgrade pre-2.1 database
    # from TABLE_CHANGES.TXT
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');

    if (!_mysql_field_exists($table_admin, 'created')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_admin, 'modified')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'created')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'modified')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'created')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'modified')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'aliases')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN aliases INT(10) DEFAULT '-1' NOT NULL AFTER description;");
    }
    if (!_mysql_field_exists($table_domain, 'mailboxes')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN mailboxes INT(10) DEFAULT '-1' NOT NULL AFTER aliases;");
    }
    if (!_mysql_field_exists($table_domain, 'maxquota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN maxquota INT(10) DEFAULT '-1' NOT NULL AFTER mailboxes;");
    }
    if (!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;");
    }
    if (!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx TINYINT(1) DEFAULT '0' NOT NULL AFTER transport;");
    }
    if (!_mysql_field_exists($table_mailbox, 'created')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'modified')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'quota')) {
        db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;");
    }
    if (!_mysql_field_exists($table_vacation, 'domain')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;");
    }
    if (!_mysql_field_exists($table_vacation, 'created')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN created {DATETIME} AFTER domain;");
    }
    if (!_mysql_field_exists($table_vacation, 'active')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN active TINYINT(1) DEFAULT '1' NOT NULL AFTER created;");
    }
    db_query_parsed("ALTER TABLE $table_vacation DROP PRIMARY KEY");
    db_query_parsed("ALTER TABLE $table_vacation ADD PRIMARY KEY(email)");
    db_query_parsed("UPDATE $table_vacation SET domain=SUBSTRING_INDEX(email, '@', -1) WHERE email=email;");
}

/**
 * @return void
 */
function upgrade_4_mysql()
{ # MySQL only
    # changes between 2.1 and moving to sourceforge

    return; // as the above _mysql functions are disabled; this one will just error for a new db.
    $table_domain = table_by_key('domain');

    db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int(10) NOT NULL default '0' AFTER maxquota", true);
    # Possible errors that can be ignored:
    # - Invalid query: Table 'postfix.domain' doesn't exist
}

/**
 * Changes between 2.1 and moving to sf.net
 * @return void
 */
function upgrade_4_pgsql()
{
    $table_domain = table_by_key('domain');
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain_admins = table_by_key('domain_admins');
    $table_log = table_by_key('log');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');
    $table_vacation_notification = table_by_key('vacation_notification');

    if (!_pgsql_field_exists($table_domain, 'quota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN quota int NOT NULL default '0'");
    }

    db_query_parsed("ALTER TABLE $table_domain ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('domain_domain_active')) {
        db_query_parsed("CREATE INDEX domain_domain_active ON $table_domain(domain,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN address DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_alias ALTER COLUMN domain DROP DEFAULT");
    if (!_pgsql_object_exists('alias_address_active')) {
        db_query_parsed("CREATE INDEX alias_address_active ON $table_alias(address,active)");
    }

    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_domain_admins ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN ");
    db_query_parsed("ALTER TABLE $table_log RENAME COLUMN data TO data_old;");
    db_query_parsed("ALTER TABLE $table_log ADD COLUMN data text NOT NULL default '';");
    db_query_parsed("UPDATE $table_log SET data = CAST(data_old AS text);");
    db_query_parsed("ALTER TABLE $table_log DROP COLUMN data_old;");
    db_query_parsed("COMMIT");

    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN username DROP DEFAULT");
    db_query_parsed("ALTER TABLE $table_mailbox ALTER COLUMN domain DROP DEFAULT");

    db_query_parsed("BEGIN;");
    db_query_parsed("ALTER TABLE $table_mailbox RENAME COLUMN domain TO domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN domain varchar(255) REFERENCES $table_domain (domain);");
    db_query_parsed("UPDATE $table_mailbox SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_mailbox DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('mailbox_username_active')) {
        db_query_parsed("CREATE INDEX mailbox_username_active ON $table_mailbox(username,active)");
    }


    db_query_parsed("ALTER TABLE $table_vacation ALTER COLUMN body SET DEFAULT ''");
    if (_pgsql_field_exists($table_vacation, 'cache')) {
        db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN cache");
    }

    db_query_parsed(" BEGIN; ");
    db_query_parsed("ALTER TABLE $table_vacation RENAME COLUMN domain to domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain varchar(255) REFERENCES $table_domain;");
    db_query_parsed("UPDATE $table_vacation SET domain = domain_old;");
    db_query_parsed("ALTER TABLE $table_vacation DROP COLUMN domain_old;");
    db_query_parsed("COMMIT;");

    if (!_pgsql_object_exists('vacation_email_active')) {
        db_query_parsed("CREATE INDEX vacation_email_active ON $table_vacation(email,active)");
    }

    if (!_pgsql_object_exists($table_vacation_notification)) {
        db_query_parsed("
            CREATE TABLE $table_vacation_notification (
                on_vacation character varying(255) NOT NULL REFERENCES $table_vacation(email) ON DELETE CASCADE,
                notified character varying(255) NOT NULL,
                notified_at timestamp with time zone NOT NULL DEFAULT now(),
        CONSTRAINT vacation_notification_pkey primary key(on_vacation,notified));");
    }
}


/**
 * @return void
 */
function upgrade_3_mysql()
{
    #
    # updating the tables in this very old layout (pre 2.1) causes trouble if the MySQL charset is not latin1 (multibyte vs. index length)
    # therefore:

    return; # <-- skip running this function at all.

    # (remove the above "return" if you really want to update a pre-2.1 database)

    # upgrade pre-2.1 database
    # from TABLE_CHANGES.TXT
    $table_admin = table_by_key('admin');
    $table_alias = table_by_key('alias');
    $table_domain = table_by_key('domain');
    $table_mailbox = table_by_key('mailbox');
    $table_vacation = table_by_key('vacation');

    if (!_mysql_field_exists($table_admin, 'created')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_admin, 'modified')) {
        db_query_parsed("ALTER TABLE $table_admin {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'created')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_alias, 'modified')) {
        db_query_parsed("ALTER TABLE $table_alias {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'created')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'modified')) {
        db_query_parsed("ALTER TABLE $table_domain {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_domain, 'aliases')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN aliases INT(10) DEFAULT '-1' NOT NULL AFTER description;");
    }
    if (!_mysql_field_exists($table_domain, 'mailboxes')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN mailboxes INT(10) DEFAULT '-1' NOT NULL AFTER aliases;");
    }
    if (!_mysql_field_exists($table_domain, 'maxquota')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN maxquota INT(10) DEFAULT '-1' NOT NULL AFTER mailboxes;");
    }
    if (!_mysql_field_exists($table_domain, 'transport')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN transport VARCHAR(255) AFTER maxquota;");
    }
    if (!_mysql_field_exists($table_domain, 'backupmx')) {
        db_query_parsed("ALTER TABLE $table_domain ADD COLUMN backupmx TINYINT(1) DEFAULT '0' NOT NULL AFTER transport;");
    }
    if (!_mysql_field_exists($table_mailbox, 'created')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} create_date created {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'modified')) {
        db_query_parsed("ALTER TABLE $table_mailbox {RENAME_COLUMN} change_date modified {DATETIME};");
    }
    if (!_mysql_field_exists($table_mailbox, 'quota')) {
        db_query_parsed("ALTER TABLE $table_mailbox ADD COLUMN quota INT(10) DEFAULT '-1' NOT NULL AFTER maildir;");
    }
    if (!_mysql_field_exists($table_vacation, 'domain')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN domain VARCHAR(255) DEFAULT '' NOT NULL AFTER cache;");
    }
    if (!_mysql_field_exists($table_vacation, 'created')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN created {DATETIME} AFTER domain;");
    }
    if (!_mysql_field_exists($table_vacation, 'active')) {
        db_query_parsed("ALTER TABLE $table_vacation ADD COLUMN active TINYINT(1) DEFAULT '1' NOT NULL AFTER created;");
    }
    db_query_parsed("ALTER TABLE $table_vacation DROP PRIMARY KEY");
    db_query_parsed("ALTER TABLE $table_vacation ADD PRIMARY KEY(email)");
    db_query_parsed("UPDATE $table_vacation SET domain=SUBSTRING_INDEX(email, '@', -1) WHERE email=email;");
}

/**
 * Add x_regexp field to alias table
 * @return void
 */
function upgrade_5()
{
    $table_alias = table_by_key('alias');
    _db_add_field('alias', 'x_regexp', '{BOOLEAN}', 'active');
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
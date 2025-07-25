<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at https://github.com/postfixadmin/postfixadmin 
 * 
 * @license GNU GPL v2 or later. 
 * 
 * File: config.inc.php
 * Contains configuration options.
 */


################################################################################
#                                                                              #
#    PostfixAdmin default configuration                                        #
#                                                                              #
#    This file contains the PostfixAdmin default configuration settings.       #
#                                                                              #
#    Please do not edit this file.                                             #
#                                                                              #
#    Instead, add the options you want to change/override to                   #
#    config.local.php (if it doesn't exist, create it).                        #
#    This will make version upgrades much easier.                              #
#                                                                              #
################################################################################

global $CONF;

/*****************************************************************
 *  !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! 
 * You have to set $CONF['configured'] = true; before the
 * application will run!
 * Doing this implies you have changed this file as required.
 * i.e. configuring database etc; specifying setup.php password etc.
 */
$CONF['configured'] = false;

// In order to setup Postfixadmin, you MUST specify a hashed password here.
// To create the hash, visit setup.php in a browser and type a password into the field,
// on submission it will be echoed out to you as a hashed value.
$CONF['setup_password'] = 'changeme';

// Language config
// Language files are located in './languages', change as required..
$CONF['default_language'] = 'en';

// Hook to override or add translations in $PALANG
// Set to the function name you want to use as hook function (see language_hook example function below)
$CONF['language_hook'] = '';

/*
    language_hook example function
 
    Called if $CONF['language_hook'] == '<name_of_the_function>'
    Allows to add or override $PALANG interface texts.

    If you add new texts, please always prefix them with 'x_' (for example 
    $PALANG['x_mytext'] = 'foo') to avoid they clash with texts that might be
    added to languages/*.lang in future versions of PostfixAdmin.

    Please also make sure that all your added texts are included in all
    sections - that includes all 'case "XY":' sections and the 'default:'
    section (for users that don't have any of the languages specified
    in the 'case "XY":' section). 
    Usually the 'default:' section should contain english text.

    If you modify an existing text/translation, please consider to report it
    to the bugtracker on http://sf.net/projects/postfixadmin so that all users
    can benefit from the corrected text/translation.

    Returns: modified $PALANG array
*/
/*
function language_hook($PALANG, $language) {
    switch ($language) {
        case "de":
            $PALANG['x_whatever'] = 'foo';
            break;
        case "fr":
            $PALANG['x_whatever'] = 'bar';
            break;
        default:
            $PALANG['x_whatever'] = 'foobar';
    }

    return $PALANG;
}
*/

// Database Config
// mysql = MySQL 3.23 and 4.0, 4.1 or 5
// mysqli = MySQL 4.1+ or MariaDB
// pgsql = PostgreSQL
// sqlite = SQLite 3
$CONF['database_type'] = 'mysqli';
$CONF['database_host'] = 'localhost';
$CONF['database_user'] = 'postfix';
$CONF['database_password'] = 'postfixadmin';
$CONF['database_name'] = 'postfix';

// Database SSL Config (PDO/MySQLi only)
$CONF['database_use_ssl'] = false;
$CONF['database_ssl_key'] = NULL;
$CONF['database_ssl_cert'] = NULL;
$CONF['database_ssl_ca'] = NULL;
$CONF['database_ssl_ca_path'] = NULL;
$CONF['database_ssl_cipher'] = NULL;
$CONF['database_ssl_verify_server_cert'] = true;

// If you need to specify a different port for a MYSQL database connection, use e.g.
//   $CONF['database_host'] = '172.30.33.66:3308';
//
// If you need to specify a different port for MySQLi(3306)/POSTGRESQL(5432) database connection
//   uncomment and change the following
// $CONF['database_port'] = '5432';
//
// If you wish to connect using a local socket file (e.g /var/run/mysql.sock) set this to the socket path.
// $CONF['database_socket'] = '/var/run/mysql/mysqld.sock';
$CONF['database_socket'] = '';

// If sqlite is used, specify the database file path:
//   $CONF['database_name'] = '/etc/postfix/sqlite/postfixadmin.db'

// Here, if you need, you can customize table names.
$CONF['database_prefix'] = '';
$CONF['database_tables'] = array (
    'admin' => 'admin',
    'alias' => 'alias',
    'alias_domain' => 'alias_domain',
    'config' => 'config',
    'domain' => 'domain',
    'domain_admins' => 'domain_admins',
    'fetchmail' => 'fetchmail',
    'log' => 'log',
    'mailbox' => 'mailbox',
    'vacation' => 'vacation',
    'vacation_notification' => 'vacation_notification',
    'quota' => 'quota',
    'quota2' => 'quota2',
    'dkim' => 'dkim',
    'dkim_signing' => 'dkim_signing',
    'recipient_blacklist' => 'recipient_blacklist',
);

// Site Admin
// Define the Site Admin's email address below.
// This will be used to send emails from to 
//  * create mailboxes and
//  * Send Email / Broadcast message pages and 
//  * In password reset emails.
//
// Leave blank to send email from the logged-in Admin's Email address.
$CONF['admin_email'] = '';

// Define the smtp password for admin_email.
// This will be used to send emails from to create mailboxes and
// from Send Email / Broadcast message pages.
// Leave blank to send emails without authentification
$CONF['admin_smtp_password'] = '';

// Site admin name
// This will be used as signature in notification messages
$CONF['admin_name'] = 'Postmaster';

// Mail Server
// Hostname (FQDN) of your mail server.
// This is used to send email to Postfix in order to create mailboxes.
$CONF['smtp_server'] = 'localhost';
$CONF['smtp_port'] = '25';

// The communication layer used.
//
// 'plain'    Everything in plain text (standard port: 25).
// 'tls'      TLS/SSL from the very beginning (standard port: 465).
// 'starttls' "STARTTLS" in plain text and then TLS/SSL (standard port: 587).
$CONF['smtp_type'] = 'plain';

// SMTP Client
// Hostname (FQDN) of the server hosting Postfix Admin
// Used in the HELO when sending emails from Postfix Admin
$CONF['smtp_client'] = '';

// Encrypt - how passwords are stored/hashed in the database.
//
// See: https://github.com/postfixadmin/postfixadmin/blob/master/DOCUMENTS/HASHING.md
//
// - PLAIN, CLEAR or CLEARTEXT - plain text variants, may be useful for testing.
// - ARGON2ID, ARGON2I, SHA512-CRYPT, SHA256-CRYPT or BLF-CRYPT might be good options.
//
// - other, older variants are : 
//   - md5crypt, 
//   - md5, 
//   - system,
//   - dovecot:CRYPT-METHOD = use dovecotpw -s 'CRYPT-METHOD'. 
//     - Note: dovecot relies on doveadm binary, and suitable permissions on config files - see https://github.com/postfixadmin/postfixadmin/issues/398
//
// - authlib = support for courier-authlib style passwords - also set $CONF['authlib_default_flavor']
//
// - php_crypt:CRYPT-METHOD:DIFFICULTY:PREFIX = use PHP built in crypt()-function. Example: php_crypt:SHA512:50000
// - php_crypt CRYPT-METHOD: Supported values are DES, MD5, BLOWFISH, SHA256, SHA512 (default)
// - php_crypt - DIFFICULTY: Larger value is more secure, but uses more CPU and time for each login.
// - php_crypt - DIFFICULTY: Set this according to your CPU processing power.
// - php_crypt - DIFFICULTY: Supported values are BLOWFISH:4-31, SHA256:1000-999999999, SHA512:1000-999999999
// - php_crypt - DIFFICULTY: leave empty to use default values (BLOWFISH:10, SHA256:5000, SHA512:5000). Example: php_crypt:SHA512
// - php_crypt - PREFIX: hash has specified prefix - example: php_crypt:SHA512::{SHA512-CRYPT}
//
// - sha512.b64 - {SHA512-CRYPT.B64} (base64 encoded sha512 crypt) (no dovecot dependency; should support migration from md5crypt)
//
// If in doubt, use php_crypt which will give you a SHA512 style crypt which looks like: $6$ijF8bgunALqnEHTo$LHVa6XQB.....

$CONF['encrypt'] = 'php_crypt';

// In what flavor should courier-authlib style passwords be encrypted?
// (only used if $CONF['encrypt'] == 'authlib')
// md5 = {md5} + base64 encoded md5 hash
// md5raw = {md5raw} + plain encoded md5 hash
// SHA = {SHA} + base64-encoded sha1 hash
// crypt = {crypt} + Standard UNIX DES-encrypted with 2-character salt
$CONF['authlib_default_flavor'] = 'md5raw';

// If you use the dovecot encryption method: where is the dovecotpw binary located?
// for dovecot 1.x
// $CONF['dovecotpw'] = "/usr/sbin/dovecotpw";
// for dovecot 2.x (dovecot 2.0.0 - 2.0.7 is not supported!)
$CONF['dovecotpw'] = "/usr/sbin/doveadm pw";
if(@file_exists('/usr/bin/doveadm')) { // @ to silence openbase_dir stuff; see https://github.com/postfixadmin/postfixadmin/issues/171
    $CONF['dovecotpw'] = "/usr/bin/doveadm pw"; # debian
}

// Password validation
// New/changed passwords will be validated using all regular expressions in the array.
// If a password doesn't match one of the regular expressions, the corresponding
// error message from $PALANG (see languages/*.lang) will be displayed.
// See http://de3.php.net/manual/en/reference.pcre.pattern.syntax.php for details
// about the regular expression syntax.
// If you need custom error messages, you can add them using $CONF['language_hook'].
// If a $PALANG text contains a %s, you can add its value after the $PALANG key
// (separated with a space).
$CONF['password_validation'] = array(
#    '/regular expression/' => '$PALANG key (optional: + parameter)',
    '/.{5}/'                => 'password_too_short 5',      # minimum length 5 characters
    '/([a-zA-Z].*){3}/'     => 'password_no_characters 3',  # must contain at least 3 characters
    '/([0-9].*){2}/'        => 'password_no_digits 2',      # must contain at least 2 digits
#    '/([!\".,*&^%$£)(_+=\-`\'#@~\[\]\\<>\/].*){1,}/' => 'password_no_special 1', # must contain at least 1 special character

    /*  support a 'callable' value which if it returns a non-empty string will be assumed to have failed, non-empty string should be a PALANG key */
    // 'length_check'          => function($password) { if (strlen(trim($password)) < 3) { return 'password_too_short'; } },
);

// Username legal characters
// New/changed usernames will be checked against this regular expression with javascript
// during entry, offending characters not displaying.
// For example:
// $CONF['username_legal_chars'] = '^[a-zA-Z0-9-_.]+$';
$CONF['username_legal_chars'] = '';

// Generate Password
// Generate a random password for a mailbox or admin and display it.
// If you want to automagically generate passwords set this to 'YES'.
$CONF['generate_password'] = 'NO';

// Show Password
// Always show password after adding a mailbox or admin.
// If you want to always see what password was set set this to 'YES'.
$CONF['show_password'] = 'NO';

// Page Size
// Set the number of entries that you would like to see
// in one page.
$CONF['page_size'] = '10';

// Default Aliases
// The default aliases that need to be created for all domains.
// You can specify the target address in two ways:
// a) a full mail address
// b) only a localpart ('postmaster' => 'admin') - the alias target will point to the same domain
$CONF['default_aliases'] = array (
    'abuse' => 'abuse@change-this-to-your.domain.tld',
    'hostmaster' => 'hostmaster@change-this-to-your.domain.tld',
    'postmaster' => 'postmaster@change-this-to-your.domain.tld',
    'webmaster' => 'webmaster@change-this-to-your.domain.tld'
);

// Mailboxes
// If you want to store the mailboxes per domain set this to 'YES'.
// Examples:
//   YES: /usr/local/virtual/domain.tld/username@domain.tld
//   NO:  /usr/local/virtual/username@domain.tld
$CONF['domain_path'] = 'YES';
// If you don't want to have the domain in your mailbox set this to 'NO'.
// Examples: 
//   YES: /usr/local/virtual/domain.tld/username@domain.tld
//   NO:  /usr/local/virtual/domain.tld/username
// Note: If $CONF['domain_path'] is set to NO, this setting will be forced to YES.
$CONF['domain_in_mailbox'] = 'NO';
// If you want to define your own function to generate a maildir path set this to the name of the function.
// Notes: 
//   - this configuration directive will override both domain_path and domain_in_mailbox
//   - the maildir_name_hook() function example is present below, commented out
//   - if the function does not exist the program will default to the above domain_path and domain_in_mailbox settings
$CONF['maildir_name_hook'] = 'NO';

/*
    maildir_name_hook example function
 
    Called when creating a mailbox if $CONF['maildir_name_hook'] == '<name_of_the_function>'
    - allows for customized maildir paths determined by a custom function
    - the example below will prepend a single-character directory to the
      beginning of the maildir, splitting domains more or less evenly over
      36 directories for improved filesystem performance with large numbers
      of domains.

    Returns: maildir path
    ie. I/example.com/user/
*/
/*
function maildir_name_hook($domain, $user) {
    $chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    $dir_index = hexdec(substr(md5($domain), 28)) % strlen($chars);
    $dir = substr($chars, $dir_index, 1);
    return sprintf("%s/%s/%s/", $dir, $domain, $user);
}
*/

/*  
    *_struct_hook - change, add or remove fields

    If you need additional fields or want to change or remove existing fields,
    you can write a hook function to modify $struct in the *Handler classes. 

    The edit form will automatically be updated according to the modified
    $struct. The list page is not yet updated automatically.

    You can define one hook function per class, named like the primary database
    table of that class.
    The hook function is called with $struct as parameter and must return the
    modified $struct. 

    Note: Adding a field to $struct adds the handling of this field in
    PostfixAdmin, but it does not create it in the database. You have to do
    that yourself.
    Note: If you add fields here and you want them to be displayed in the
    virtual lists, you must also modify the corresponding virtual-list template.
    Please follow the naming policy for custom database fields and tables on
    https://sourceforge.net/p/postfixadmin/wiki/Custom_fields/
    to avoid clashes with future versions of PostfixAdmin.

    See initStruct() in the *Handler class for the default $struct.
    See pacol() in the PFAHandler class for the available flags on each column.
    
    Example:

    function x_struct_admin_modify($struct) {
        $struct['superadmin']['editable'] = 0;          # make the 'superadmin' flag read-only
        $struct['superadmin']['display_in_form'] = 0;   # don't display the 'superadmin' flag in edit form
        $struct['x_newfield'] = PFAHandler::pacol( [...] );        # additional field 'x_newfield'
        return $struct; # important!
    }
    $CONF['admin_struct_hook'] = 'x_struct_admin_modify';
*/
$CONF['admin_struct_hook']          = '';
$CONF['domain_struct_hook']         = '';
$CONF['alias_struct_hook']          = '';
$CONF['mailbox_struct_hook']        = '';
$CONF['alias_domain_struct_hook']   = '';
$CONF['fetchmail_struct_hook']      = '';
$CONF['dkim_struct_hook']           = '';
$CONF['dkim_signing_struct_hook']   = '';
$CONF['recipient_blacklist_struct_hook']           = '';
$CONF['mysql_virtual_recipient_blacklist_struct_hook'] = ''; // deprecated - use recipient_blacklist_struct_hook

/*
    mailbox_postcreation_hook example function

    Function called right after mailbox is created if $CONF['mailbox_postcreation_hook'] == '<name_of_the_function>', in the form:

        function x_mailbox_postcreation_action($id, $values) {
        }
        $CONF['mailbox_postcreation_hook ']           = 'x_mailbox_postcreation_action';    

    where:
    $id is the username
    $values contains the values of the mailbox record created
*/
$CONF['mailbox_postcreation_hook ']           = '';

// Default Domain Values
// Specify your default values below. Quota in MB.
$CONF['aliases'] = '10';
$CONF['mailboxes'] = '10';
$CONF['maxquota'] = '10';
$CONF['domain_quota_default'] = '2048';

// Quota
// When you want to enforce quota for your mailbox users set this to 'YES'.
$CONF['quota'] = 'NO';
// If you want to enforce domain-level quotas set this to 'YES'.
$CONF['domain_quota'] = 'YES';
// You can either use '1024000' or '1048576'
$CONF['quota_multiplier'] = '1024000';
// fill state threshold (in per cent) for medium level (displayed as orange)
$CONF['quota_level_med_pct'] = 55;
// fill state threshold (in per cent) for high level (displayed as red)
$CONF['quota_level_high_pct'] = 90;

// Transport
// If you want to define additional transport options for a domain set this to 'YES'.
// Read the transport file of the Postfix documentation.
$CONF['transport'] = 'NO';
// Transport options
// If you want to define additional transport options put them in array below.
$CONF['transport_options'] = array (
    'virtual',  // for virtual accounts
    'local',    // for system accounts
    'relay'     // for backup mx
);
// Transport default
// You should define default transport. It must be in array above.
$CONF['transport_default'] = 'virtual';


//
//
// Virtual Vacation Stuff
//
//

// If you want to use virtual vacation for you mailbox users set this to 'YES'.
// NOTE: Make sure that you install the vacation module. (See VIRTUAL-VACATION/)
$CONF['vacation'] = 'NO';

// This is the autoreply domain that you will need to set in your Postfix
// transport maps to handle virtual vacations. It does not need to be a
// real domain (i.e. you don't need to setup DNS for it).
// This domain must exclusively be used for vacation. Do NOT use it for "normal" mail addresses.
$CONF['vacation_domain'] = 'autoreply.change-this-to-your.domain.tld';

// Vacation Control
// If you want users to take control of vacation set this to 'YES'.
$CONF['vacation_control'] ='YES';

// Vacation Control for admins
// Set to 'YES' if your domain admins should be able to edit user vacation.
$CONF['vacation_control_admin'] = 'YES';

// ReplyType options
// If you want to define additional reply options put them in array below.
// The array has the format   seconds between replies => $PALANG text
// Special values for seconds are: 
// 0 => only reply to the first mail while on vacation 
// 1 => reply on every mail
$CONF['vacation_choice_of_reply'] = array (
   0 => 'reply_once',        // Sends only Once the message during Out of Office
   # considered annoying - only send a reply on every mail if you really need it
   # 1 => 'reply_every_mail',       // Reply on every email
   60*60 *24*7 => 'reply_once_per_week'        // Reply if last autoreply was at least a week ago
);

//
// End Vacation Stuff.
//

// Alias Control
// Postfix Admin inserts an alias in the alias table for every mailbox it creates.
// The reason for this is that when you want catch-all and normal mailboxes
// to work you need to have the mailbox replicated in the alias table.
// If you want to take control of these aliases as well set this to 'YES'.

// If you don't want edit alias tab (user mode) set this to 'NO';
$CONF['edit_alias'] = 'YES';

// Alias control for superadmins
$CONF['alias_control'] = 'YES';

// Alias Control for domain admins
$CONF['alias_control_admin'] = 'YES';

// Special Alias Control
// Set to 'NO' if your domain admins shouldn't be able to edit the default aliases
// as defined in $CONF['default_aliases']
$CONF['special_alias_control'] = 'NO';


// Alias Domains
// Alias domains allow to "mirror" aliases and mailboxes to another domain. This makes 
// configuration easier if you need the same set of aliases on multiple domains, but
// also requires postfix to do more database queries.
// Note: If you update from 2.2.x or earlier, you will have to update your postfix configuration.
// Set to 'NO' to disable alias domains.
$CONF['alias_domain'] = 'YES';

// Backup
// If you don't want backup tab set this to 'NO';
$CONF['backup'] = 'NO';

// Send Mail
// If you don't want sendmail tab set this to 'NO';
$CONF['sendmail'] = 'YES';
// Set this to YES if you want to allow non-super-admins to
// send mails to their users
$CONF['sendmail_all_admins'] = 'NO';

// Logging
// If you don't want logging set this to 'NO';
$CONF['logging'] = 'YES';

// Fetchmail
// If you don't want fetchmail tab set this to 'NO';
$CONF['fetchmail'] = 'YES';

// fetchmail_extra_options allows users to specify any fetchmail options and any MDA
// (it will even accept 'rm -rf /' as MDA!)
// This should be set to NO, except if you *really* trust *all* your users.
$CONF['fetchmail_extra_options'] = 'NO';

// Header
$CONF['show_header_text'] = 'NO';
$CONF['header_text'] = ':: Postfix Admin ::';

// Footer
// Below information will be on all pages.
// If you don't want the footer information to appear set this to 'NO'.
$CONF['show_footer_text'] = 'YES';
$CONF['footer_text'] = 'Return to change-this-to-your.domain.tld';
$CONF['footer_link'] = 'http://change-this-to-your.domain.tld';

// MOTD ("Motto of the day")
// You can display a MOTD below the menu on all pages.
// This can be configured seperately for users, domain admins and superadmins
$CONF['motd_user'] = '';
$CONF['motd_admin'] = '';
$CONF['motd_superadmin'] = '';

// Welcome Message
// This message is send to every newly created mailbox.
// Change the text between EOM.
$CONF['welcome_text'] = <<<EOM
Hi,

Welcome to your new account.
EOM;

// When creating mailboxes or aliases, check that the domain-part of the
// address is legal by performing a name server look-up.
$CONF['emailcheck_resolve_domain']='YES';

// When creating mailboxes or aliases, check that the domain-part of the
// address is local and managed by postfixadmin, preventing remote domains
// from being the destination for an alias
$CONF['emailcheck_localaliasonly']='NO';

// Use TOTP for logging into Postfixadmin, can be overridden for listed
// IPs to allow access by software that provide their own checking.
// Exceptions can be of user, domain or global scope.
// This also bundles several menu items in a "security" dropdown.
$CONF['totp'] = 'NO';

// Use revokable application passwords to limit the risk of storing a
// password in another system. These passwords can not access Postfixadmin.
$CONF['app_passwords'] = 'NO';


// OpenDKIM stuff
// Enable the dkim database component
$CONF['dkim'] = 'NO';
// Allow regular admins to add/edit/remove dkim entries
$CONF['dkim_all_admins'] = 'NO';
// End OpenDKIM stuff


// Optional:
// Analyze alias gotos and display a colored block in the first column
// indicating if an alias or mailbox appears to deliver to a non-existent
// account.  Also, display indications, for POP/IMAP mailboxes and
// for custom destinations (such as mailboxes that forward to a UNIX shell
// account or mail that is sent to a MS exchange server, or any other
// domain or subdomain you use)
// See http://www.w3schools.com/html/html_colornames.asp for a list of
// color names available on most browsers

//set to YES to enable this feature
$CONF['show_status']='YES';
//display a guide to what these colors mean
$CONF['show_status_key']='YES';
// 'show_status_text' will be displayed with the background colors
// associated with each status, you can customize it here
$CONF['show_status_text']='&nbsp;&nbsp;';
// show_undeliverable is useful if most accounts are delivered to this
// postfix system.  If many aliases and mailboxes are forwarded
// elsewhere, you will probably want to disable this.
$CONF['show_undeliverable']='YES';
$CONF['show_undeliverable_color']='tomato';
// mails to these domains will never be flagged as undeliverable
$CONF['show_undeliverable_exceptions']=array("unixmail.domain.ext","exchangeserver.domain.ext");
// show mailboxes with expired password; requires password_expiration to be enabled
$CONF['show_expired']='YES';
$CONF['show_expired_color']='orange';
// show vacation enabled mailboxes
$CONF['show_vacation']='YES';
$CONF['show_vacation_color']='turquoise';
// show disabled accounts
$CONF['show_disabled']='YES';
$CONF['show_disabled_color']='grey';
// show POP/IMAP mailboxes
$CONF['show_popimap']='YES';
$CONF['show_popimap_color']='darkgrey';
// you can assign special colors to some domains. To do this,
// - add the domain to show_custom_domains
// - add the corresponding color to show_custom_colors
$CONF['show_custom_domains']=array("subdomain.domain.ext","domain2.ext");
$CONF['show_custom_colors']=array("lightgreen","lightblue");
// If you use a recipient_delimiter in your postfix config, you can also honor it when aliases are checked.
// Example: $CONF['recipient_delimiter'] = "+";
// Set to "" to disable this check.
$CONF['recipient_delimiter'] = "";

/**
 * NOTE FOR OPTIONAL SCRIPTS BELOW.
 *
 * These scripts will probably be called by your webserver user (typically 'www-data').
 *
 * Execution may fail for a number of reasons, perhaps :
 *  * PHP is running in 'safe mode' 
 *  * you have operating system features like SELinux or Apparmor
 *  * Unix file ownership/permission restrictions
 *
 * Your mail system probably requires different ownership (e.g. courier, dovecot, mail ...)
 *
 * You will probably need to use 'sudo' either within the script, or when calling it, to resolve issues of ownership/permission.
 *
 * Details about errors from execution should be logged into PHP's error_log. 
 *
 * See also: https://github.com/postfixadmin/postfixadmin/blob/master/DOCUMENTS/FAQ.txt
 *
 */

// Optional: See NOTE above.
// Script to run after creation of mailboxes.
// Parameters: (1) username (2) domain (3) maildir (4) quota
// $CONF['mailbox_postcreation_script']='sudo -u courier /usr/local/bin/postfixadmin-mailbox-postcreation.sh';
$CONF['mailbox_postcreation_script'] = '';

// Optional: See NOTE above.
// Script to run after alteration of mailboxes.
// Parameters: (1) username (2) domain (3) maildir (4) quota
// $CONF['mailbox_postedit_script']='sudo -u courier /usr/local/bin/postfixadmin-mailbox-postedit.sh';
$CONF['mailbox_postedit_script'] = '';

// Optional: See NOTE above.
// Script to run after deletion of mailboxes.
// Parameters: (1) username (2) domain
// $CONF['mailbox_postdeletion_script']='sudo -u courier /usr/local/bin/postfixadmin-mailbox-postdeletion.sh';
$CONF['mailbox_postdeletion_script'] = '';

// Optional: See NOTE above.
// Script to run after setting a mailbox password. (New mailbox [old password = empty] or change existing password)
// Disables changing password without entering old password.
// Parameters: (1) username (2) domain
// STDIN: old password + \0 + new password
// $CONF['mailbox_postpassword_script']='sudo -u dovecot /usr/local/bin/postfixadmin-mailbox-postpassword.sh';
$CONF['mailbox_postpassword_script'] = '';

// Optional: See NOTE above.
// Script to run after setting a mailbox TOTP secret.
// Parameters: (1) username (2) domain
// STDIN: TOTP secret + \0
// $CONF['mailbox_post_TOTP_change_secret_script']='sudo -u dovecot /usr/local/bin/postfixadmin-mailbox-postpassword.sh';
$CONF['mailbox_post_TOTP_change_secret_script'] = '';

// Optional: See NOTE above.
// Script to run after adding an exception address (disable TOTP).
// Parameters: (1) username (2) ip
// STDIN: TOTP secret + \0
// $CONF['mailbox_post_exception_add_script']='sudo -u dovecot /usr/local/bin/postfixadmin-mailbox-postpassword.sh';
$CONF['mailbox_post_totp_exception_add_script'] = '';

// Optional: See NOTE above.
// Script to run after deleting an exception address (disable TOTP).
// Parameters: (1) username (2) ip
// STDIN: TOTP secret + \0
// $CONF['mailbox_post_totp_exception_delete_script']='sudo -u dovecot /usr/local/bin/postfixadmin-mailbox-postpassword.sh';
$CONF['mailbox_post_totp_exception_delete_script'] = '';

// Optional: See NOTE above.
// Script to run after adding an app password.
// Parameters: (1) username (2) app description
// STDIN: password + \0
// $CONF['mailbox_postapppassword_script']='sudo -u dovecot /usr/local/bin/postfixadmin-mailbox-postpassword.sh';
$CONF['mailbox_postapppassword_script'] = '';

// Optional: See NOTE above.
// Script to run after creation of domains.
// Parameters: (1) domain
//$CONF['domain_postcreation_script']='sudo -u courier /usr/local/bin/postfixadmin-domain-postcreation.sh';
$CONF['domain_postcreation_script'] = '';

// Optional: See NOTE above.
// Script to run after alteation of domains.
// Parameters: (1) domain
//$CONF['domain_postedit_script']='sudo -u courier /usr/local/bin/postfixadmin-domain-postedit.sh';
$CONF['domain_postedit_script'] = '';

// Optional: See NOTE above.
// Script to run after deletion of domains.
// Parameters: (1) domain
// $CONF['domain_postdeletion_script']='sudo -u courier /usr/local/bin/postfixadmin-domain-postdeletion.sh';
$CONF['domain_postdeletion_script'] = '';


// Optional:
// Show used quotas from Dovecot dictionary backend in virtual
// mailbox listing.
// See: DOCUMENTATION/DOVECOT.txt
//      http://wiki.dovecot.org/Quota/Dict
//
$CONF['used_quotas'] = 'NO';

// if you use dovecot >= 1.2, set this to yes.
// Note about dovecot config: table "quota" is for 1.0 & 1.1, table "quota2" is for dovecot 1.2 and newer
$CONF['new_quota_table'] = 'YES';

// Optional:
// Allows a user to reset his forgotten password with a code sent by email/SMS
$CONF['forgotten_user_password_reset'] = true;
// Allows an admin to reset his forgotten password with a code sent by email/SMS
$CONF['forgotten_admin_password_reset'] = false;

// Name of the function to send a SMS
// Please use a name that begins with "x_" to prevent collisions
// This function must accept 2 parameters: phone number and message,
// and return true on success or false on failure
// Note: if no sms_send_function is defined, the input field for the mobile
// number won't be displayed
$CONF['sms_send_function'] = '';

/*
// Example of send SMS function using Clickatell HTTP API
function x_send_sms_clickatell($to, $message) {

    $clickatell_api_id = 'CHANGEME';
    $clickatell_user = 'CHANGEME';
    $clickatell_password = 'CHANGEME';
    $clickatell_sender = 'CHANGEME';

    $url = 'https://api.clickatell.com/http/sendmsg?api_id=%s&user=%s&password=%s&to=%s&from=%s&text=%s';

    $url = sprintf($url, $clickatell_api_id, $clickatell_user, $clickatell_password, $to, $clickatell_sender, urlencode($message));

    $result = file_get_contents($url);

    return $result !== false;
}
*/

// Theme Config
$CONF['theme'] = 'default';
// Specify your own favicon, logo and CSS file
$CONF['theme_favicon'] = 'images/favicon.ico';
$CONF['theme_logo'] = 'images/logo-default.png';
$CONF['theme_css'] = 'css/bootstrap.css';
// If you want to customize some styles without editing the $CONF['theme_css'] file,
// you can add a custom CSS file. It will be included after $CONF['theme_css'].
$CONF['theme_custom_css'] = '';

// XMLRPC Interface.
// This should be only of use if you wish to use e.g the
// Postfixadmin-Squirrelmail package
//  change to boolean true to enable xmlrpc
$CONF['xmlrpc_enabled'] = false;

//Account expiration info
//If enabled, mailbox passwords have a password_expiry field set, which is updated each time the password is changed, based on the parent domain's password_expiry (days) value.
//More details in Password_Expiration.md
$CONF['password_expiration'] = 'YES';

// If defined, use this rather than trying to construct it from  $_SERVER parameters.
// used in (at least) password-recover.php.
$CONF['site_url'] = null;

$CONF['version'] = '4.0';

// The smtp_active_flag when set to YES enables editing of the smtp_active 
// field of the mailbox table. The smtp_active field can be used to enable
// or disable smtp sending for a mailbox separately to other mailbox functions.  
// This can be useful if you want the ability to stop a user sending email 
// while still allowing receipt of new mail and reading existing email. 
// Please refer to DOCUMENTS/DOVECOT.txt for an example of how to configure this.
// The default is NO for backwards compatibility. Only enable this if you
// have also set up the SQL queries that make use of the smtp_active field
// in your Dovecot SQL configuration.
$CONF['smtp_active_flag'] = 'NO';

// If you want to keep most settings at default values and/or want to ensure
// that future updates work without problems, you can use a separate config 
// file (config.local.php) instead of editing this file and override some
// settings there.
if (file_exists(dirname(__FILE__) . '/config.local.php')) {
    require_once(dirname(__FILE__) . '/config.local.php');
}

//
// END OF CONFIG FILE
//
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */

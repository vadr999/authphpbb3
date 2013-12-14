<?php
/**
 * DokuWiki phpbb3 authentication plugin
 * https://www.dokuwiki.org/plugin:authphpbb3
 *
 * This plugin basically replaces DokuWiki's own authentication features
 * with the phpbb3 authentication configured in the same host. 
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Alexander Diev <ostravadr@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/* We have to distinguish between the plugin being loaded and the plugin
   actually being used for authentication. */
$active = (
    $conf['authtype'] == 'authphpbb3' ||
    (
        $conf['authtype'] == 'authsplit' &&
        $conf['plugin']['authsplit']['primary_authplugin'] == 'authphpbb3'
    )
);

class auth_plugin_authphpbb3 extends DokuWiki_Auth_Plugin {
    protected $phpbb3config;

    /**
     * Constructor.
     */
    public function __construct() {
        global $conf;

        parent::__construct();

        // Set capabilities accordingly
        $this->cando['addUser']     = false; // can Users be created?
        $this->cando['delUser']     = false; // can Users be deleted?
        $this->cando['modLogin']    = false; // can login names be changed?
        $this->cando['modPass']     = false; // can passwords be changed?
        $this->cando['modName']     = false; // can real names be changed?
        $this->cando['modMail']     = false; // can emails be changed?
        $this->cando['modGroups']   = false; // can groups be changed?
        $this->cando['getUsers']    = false; // can a (filtered) list of users be retrieved?
        $this->cando['getUserCount']= false; // can the number of users be retrieved?
        $this->cando['getGroups']   = false; // can a list of available groups be retrieved?
        $this->cando['external']    = true; // does the module do external auth checking?
        $this->cando['logout']      = false; // can the user logout again? 

        //  Load the config 
        $this->loadConfig();
		
		// set and check the config values
		$phpbb3config = $this->getConf("$phpbb3config");
        if (!$$phpbb3config) {
		    // Error #1: $phpbb3config not set
            msg("Configuration error. Contact wiki administrator", -1);
            $this->success = false;
            return;}

		if (!file_exists($filename)) {
		    // Error #2: phpbb3 config file not found
            msg("Configuration error. Contact wiki administrator", -1);
            $this->success = false;
            return;}

		include ($phpbb3config);	

		$this->phpbb3_dbhost = $dbhost;
		$this->phpbb3_dbname = $dbname;
		$this->phpbb3_dbuser = $dbuser;
		$this->phpbb3_dbpasswd = $dbpasswd;
		$this->phpbb3_table_prefix = $table_prefix;
		
        foreach (array("phpbb3_dbhost", "phpbb3_dbname", "phpbb3_dbuser", "phpbb3_dbpasswd") as $cfgvar) {
            if (!$this->$cfgvar) {
				msg ("Configuration error. Contact wiki administrator", -1);
//                 msg("Config error: \"$cfgvar\" not set!", -1);
                 $this->success = false;
                 return;
            }
        }	
			
	$this->success = true;		
 
    }

    /**
     * Check user+password
     *
     * @param   string $user the user name
     * @param   string $pass the clear text password
     * @return  bool
     */
    public function checkPass($user, $pass) {
        return false;
    }

	
    /**
     * Return user info
     *
     * Returned info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email address of the user
     * grps array   list of groups the user is in
     *
     * @param   string $user the user name
     * @return  array containing user data or false
     */
    public function getUserData($user) {
        return false;
    }
	
	function trustExternal($user, $pass, $sticky=false) {
    global $USERINFO;
 
        //situation: no login form used or logged in successful 
	
    	// connect to mysql 
    	$link = mysql_connect($this->phpbb3_dbhost, $this->phpbb3_dbuser, $this->phpbb3_dbpasswd); 
	    if (!$link) {
//     die('Could not connect: <br />' . mysql_error());
	    	msg ("Database error. Contact wiki administrator", -1);
    		return false;
	    }

        // set codepage to utf-8
        mysql_set_charset ( "utf8", $link );

	    // select forum database
    	if (!mysql_select_db($this->phpbb3_dbname)) {
	    	msg ("Database error. Contact wiki administrator", -1);
	    	mysql_close($link);
	    	return false;
	    };

        // query for cookie_name
        $query = "select config_name, config_value 
                    from {$this->phpbb3_table_prefix}config 
                    where config_name = 'cookie_name';";
        $rs = mysql_query($query);
        if (!($row = mysql_fetch_array($rs))){
        // some error in db structure 
            return false;
        };
        $this->phpbb3_cookie_name = $row["config_value"] . "_sid";
        unset($rs, $row);

        // get forum sid from cookie
        $this->phpbb3_sid = $_COOKIE[$this->phpbb3_cookie_name];

        // check potential vulnerability - modification $this->phpbb3_sid with sql injection 
        // forum sid can be headecimal digit only, check prevent any "union", "select" etc
        if (!ctype_xdigit($this->phpbb3_sid)){
        // wrong sid
            return false;
        }

        // get session data from db
        $query = "select session_id, session_user_id 
                    from {$this->phpbb3_table_prefix}sessions 
                    where session_id = '{$this->phpbb3_cookie_name}';";
        $rs = mysql_query($query);
        if (!($row = mysql_fetch_array($rs))){
        // session is not found in db - guest access only
            return false;
        };
        $this->phpbb3_userid = $row["session_user_id"]
        unset($rs, $row);

        // check for guest session
        if ($this->phpbb3_userid == 1){
        // session_user_id == 1 on guest session
            return false;
        };

        




//// \\\\\\\\\\\\\\\\\\\\\\\\

    
        // check where if there is a logged in user e.g from session,
        // $_SERVER or what your auth backend supplies...
    
        if( ...check here if there is a logged in user...) {
    
            $USERINFO['name'] = string
            $USERINFO['mail'] = string
            $USERINFO['grps'] = array()
    
            $_SERVER['REMOTE_USER']                = $user; //userid
            $_SESSION[DOKU_COOKIE]['auth']['user'] = $user; //userid
            $_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;
    
            return true;
        }else{
            //when needed, logoff explicitly.
        }
	}
}

// vim:ts=4:sw=4:et:

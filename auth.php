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

/*
* Version history:
* v0.0.1 - initial release
* issue #1: session expiring if no activity in forum
* v0.0.2 - issue #1 is fixed
*
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
        $this->cando['getGroups']   = true; // can a list of available groups be retrieved?
        $this->cando['external']    = true; // does the module do external auth checking?
        $this->cando['logout']      = false; // can the user logout again? 

        //  Load the config 
        $this->loadConfig();
		
		// set and check the config values
		$wikirootpath = realpath(dirname(__FILE__) . "/../../../");
		$phpbb3relpath = $this->getConf("phpbb3rootpath");
		$phpbb3relpath = trim ($phpbb3relpath);    // remove (if exist) spases from start/end of path
		$phpbb3relpath = trim ($phpbb3relpath, "/\\"); // remove (if exist) slashes from start/end of path
		$phpbb3config = $wikirootpath . '/' . $phpbb3relpath . '/config.php' ;
        if (!$phpbb3config) {
		    // Error : $phpbb3config not set
			dbglog("authphpbb3 error: phpbb3config is not set"); 
            msg("Configuration error. Contact wiki administrator", -1);
            $this->success = false;
            return;}

		if (!file_exists($phpbb3config)) {
		    // Error: phpbb3 config file not found
			dbglog("authphpbb3 error: phpbb3 config {$phpbb3config} not found"); 
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
				dbglog("authphpbb3 error: phpbb3 config variable {$cfgvar} not set");
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
	
    	// connect to mysql 
    	$link = mysql_connect($this->phpbb3_dbhost, $this->phpbb3_dbuser, $this->phpbb3_dbpasswd); 
	    if (!$link) {
			dbglog("authphpbb3 error: can't connect to database server");
	    	msg ("Database error. Contact wiki administrator", -1);
    		return false;
	    }

        // set codepage to utf-8
        mysql_set_charset ( "utf8", $link );

	    // select forum database
    	if (!mysql_select_db($this->phpbb3_dbname, $link )) {
			dbglog("authphpbb3 error: can't use database");
	    	msg ("Database error. Contact wiki administrator", -1);
	    	mysql_close($link);
	    	return false;
	    };
		
		// set realname field
		$realnamefield = "pf_" . $this->getConf("realnamefield");
        // get user data from db
		if ($realnamefield == "pf_"){
        $query = "select u.username, u.user_email, g.group_name
                    from {$this->phpbb3_table_prefix}users u, {$this->phpbb3_table_prefix}groups g, {$this->phpbb3_table_prefix}user_group ug
                    where u.user_id = ug.user_id AND g.group_id = ug.group_id AND u.username = '{$user}'";
		}
		else {
        $query = "select u.username, u.user_email, g.group_name, pf.{$realnamefield}
                    from {$this->phpbb3_table_prefix}users u, {$this->phpbb3_table_prefix}groups g, {$this->phpbb3_table_prefix}user_group ug, {$this->phpbb3_table_prefix}profile_fields_data pf
                    where u.user_id = ug.user_id AND g.group_id = ug.group_id AND pf.user_id = u.user_id AND u.username = '{$user}'";
		};
		
        $rs = mysql_query($query, $link );
		if (!$rs) {
        // where is no name in db
			dbglog("authphpbb3 error: where is no username in db");
            return false;
        }
		else{ 
			while($row = mysql_fetch_array($rs)) 
			{
				// fill array of groups names whith data from db
				$tmpvar['grps'][] = $row['group_name'];
			};
			$tmpvar['name'] = ( ($realnamefield == "pf_") ? $user : $row["{$realnamefield}"] );
			$tmpvar['mail'] = $row['user_email'];
		};

		mysql_free_result ($rs);
		unset($row);		
		
	mysql_close($link);	
	
	return $tmpvar;
    }

	function trustExternal($user, $pass, $sticky=false) {
    global $USERINFO;
 
        //situation: no login form used or logged in successful 
	
    	// connect to mysql 
    	$link = mysql_connect($this->phpbb3_dbhost, $this->phpbb3_dbuser, $this->phpbb3_dbpasswd); 
	    if (!$link) {
			dbglog("authphpbb3 error: can't connect to database server");
	    	msg ("Database error. Contact wiki administrator", -1);
    		return false;
	    }

        // set codepage to utf-8
        mysql_set_charset ( "utf8", $link );

	    // select forum database
    	if (!mysql_select_db($this->phpbb3_dbname, $link )) {
			dbglog("authphpbb3 error: can't use database");
	    	msg ("Database error. Contact wiki administrator", -1);
	    	mysql_close($link);
	    	return false;
	    };

        // query for cookie_name
        $query = "select config_name, config_value 
                    from {$this->phpbb3_table_prefix}config 
                    where config_name = 'cookie_name'";
        $rs = mysql_query($query, $link );
        if (!($row = mysql_fetch_array($rs))){
		dbglog("authphpbb3 error: some error in db structure");
            return false;
        };
        $this->phpbb3_cookie_name = $row["config_value"] . "_sid";
		mysql_free_result ($rs);
        unset($row);

        // get forum sid from cookie
        $this->phpbb3_sid = $_COOKIE[$this->phpbb3_cookie_name];
		
        // check potential vulnerability - modification $this->phpbb3_sid with sql injection 
        // forum sid can be hexadecimal digit only, check prevent any "union", "select" etc
        if (!ctype_xdigit($this->phpbb3_sid)){
		// wrong sid
			dbglog("authphpbb3 error: wrong phpbb3 sid in cookie");
            return false;
        }

        // get session data from db
        $query = "select session_id, session_user_id 
                    from {$this->phpbb3_table_prefix}sessions 
                    where session_id = '{$this->phpbb3_sid}'";
        $rs = mysql_query($query, $link );
        if (!($row = mysql_fetch_array($rs))){
		// session is not found in db - guest access only	
			dbglog("authphpbb3 error: session is not found in db - guest access only");
            return false;
        };
        $this->phpbb3_userid = $row["session_user_id"];
		$this->phpbb3_sessiontime = $row["session_time"];
		mysql_free_result ($rs);
		unset($row);
		
		// update session time on page load (this function is called every time on page load)
		$current_time = time();
		if ($current_time > $this->phpbb3_sessiontime){
			$query = "update {$this->phpbb3_table_prefix}sessions 
						set session_time = '{$current_time}' 
						where session_id = '{$this->phpbb3_sid}'";
			mysql_query($query, $link );
		};

        // check for guest session
        if ($this->phpbb3_userid == 1){
		// session_user_id == 1 on guest session
		dbglog("authphpbb3 error: session_user_id == 1 on guest session");
            return false;
        };

        // get username from db
        $query = "select user_id, username, user_email 
                    from {$this->phpbb3_table_prefix}users 
                    where user_id = '{$this->phpbb3_userid}'";
        $rs = mysql_query($query, $link );
        if (!($row = mysql_fetch_array($rs))){
        // where is no userid in db
			dbglog("authphpbb3 error: where is no userid in db");
            return false;
        };
        $this->phpbb3_username = $row["username"];
		$this->phpbb3_user_email = $row["user_email"];
		mysql_free_result ($rs);
		unset($row);

        // get user groups from db
		$query = "select *
					from {$this->phpbb3_table_prefix}groups g, {$this->phpbb3_table_prefix}users u, {$this->phpbb3_table_prefix}user_group ug 
					where u.user_id = ug.user_id AND g.group_id = ug.group_id AND u.user_id={$this->phpbb3_userid}";
		$rs = mysql_query($query, $link );
		while($row = mysql_fetch_array($rs)) 
			{
				// fill array of groups names whith data from db
				$this->phpbb3_groups[] = $row['group_name'];
			};
		if(!($this->phpbb3_groups))
		{
			// where is no group info in db, guest access only
			dbglog("authphpbb3 error: where is no group info in db, guest access only");
			return false;
		};
		mysql_free_result ($rs);
		unset($row);
		
		// now we have info about logged in user and can fill $USERINFO
				
		// get realname from db - may be missing
		$query = "select user_id, pf_{$this->getConf("realnamefield")} from {$this->phpbb3_table_prefix}profile_fields_data where user_id = '{$this->phpbb3_userid}'";
        if ($rs = mysql_query($query, $link )){
			$USERINFO['name'] = (($row = mysql_fetch_array($rs)) ? $row["pf_{$this->getConf("realnamefield")}"] : $this->phpbb3_username);
		}
		else{
			$USERINFO['name'] = $this->phpbb3_username;
		};
		
		mysql_close($link);
		
		$USERINFO['mail'] = $this->phpbb3_user_email;
		$USERINFO['grps'] = $this->phpbb3_groups;

        $_SERVER['REMOTE_USER']                = $this->phpbb3_username; //userid
        $_SESSION[DOKU_COOKIE]['auth']['user'] = $this->phpbb3_username; //userid
        $_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;	

		return true;

	}

    public function retrieveGroups($start=0,$limit=0) {

   	// connect to mysql 
    	$link = mysql_connect($this->phpbb3_dbhost, $this->phpbb3_dbuser, $this->phpbb3_dbpasswd); 
	    if (!$link) {
			dbglog("authphpbb3 error: can't connect to database server");
	    	msg ("Database error. Contact wiki administrator", -1);
    		return false;
	    }

    // set codepage to utf-8
        mysql_set_charset ( "utf8", $link );

	// select forum database
    	if (!mysql_select_db($this->phpbb3_dbname, $link )) {
			dbglog("authphpbb3 error: can't use database");
	    	msg ("Database error. Contact wiki administrator", -1);
	    	mysql_close($link);
	    	return false;
	    };
    
    if (limit > 0) {
    // get groups from db
		$query = "select *
					from {$this->phpbb3_table_prefix}groups 
					where 1 = 1
                    order by group_name
                    limit {$start}, {$limit}";
		$rs = mysql_query($query, $link );
		while($row = mysql_fetch_array($rs)) 
			{
				// fill array of groups names whith data from db
				$tmpvar[] = $row['group_name'];
			};
        mysql_close($link);
        return $tmpvar;
        }
        else{
            mysql_close($link);
            return false;
        }

    }


}

// vim:ts=4:sw=4:et:

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

		$this->dbhost = $dbhost;
		$this->dbname = $dbname;
		$this->dbuser = $dbuser;
		$this->dbpasswd = $dbpasswd;
		$this->table_prefix = $table_prefix;
		
        foreach (array("dbhost", "dbname", "dbuser", "dbpasswd") as $cfgvar) {
            if (!$this->$cfgvar) {
				msg ("Configuration error. Contact wiki administrator", -1);
//                 msg("Config error: \"$cfgvar\" not set!", -1);
                 $this->success = false;
                 return;
            }
        }	
			
			
			
			
 
 
    }

    /**
     * Check user+password
     *
     * @param   string $user the user name
     * @param   string $pass the clear text password
     * @return  bool
     */
    public function checkPass($user, $pass) {
        return ($user == $_SERVER['PHP_AUTH_USER'] && $pass == $_SERVER['PHP_AUTH_PW']);
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
        global $conf;

        $info['name'] = $user;
        $info['mail'] = $user."@".$this->emaildomain;
        $info['grps'] = array($conf['defaultgroup']);
        if (in_array($user, $this->specialusers)) {
            $info['grps'][] = $this->specialgroup;
        }

        return $info;
    }
}

// vim:ts=4:sw=4:et:

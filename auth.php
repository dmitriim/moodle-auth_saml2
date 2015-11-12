<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Authenticate using an embeded SimpleSamlPhp instance
 *
 * @package   auth_saml2
 * @copyright Brendan Heywood <brendan@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/authlib.php');

/**
 * Plugin for Saml2 authentication.
 *
 * @copyright  Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_plugin_saml2 extends auth_plugin_base {

    /**
     * @var $defaults The config defaults
     */
    public $defaults = array(
        'idpname'         => 'SAML IdP',
        'entityid'        => '',
        'idpmetadata'     => '',
        'debug'           => 0,
        'duallogin'       => 1,
        'anyauth'         => 1,
        'idpattr'         => 'uid',
        'mdlattr'         => 'username',
        'tolower'         => 0,
    );

    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG;
        $this->authtype = 'saml2';
        $mdl = new moodle_url($CFG->wwwroot);
        $this->spname = $mdl->get_host();
        $this->certdir = "$CFG->dataroot/saml2/";
        $this->certpem = $this->certdir . $this->spname . '.pem';
        $this->certcrt = $this->certdir . $this->spname . '.crt';
        $this->config = (object) array_merge($this->defaults, (array) get_config('auth_saml2') );
    }

    /**
     * A debug function, dumps to the php log
     *
     * @param string $msg Log message
     */
    private function log($msg) {
        if ($this->config->debug) {
            error_log('auth_saml2: ' . $msg);
        }
    }

    /**
     * All the checking happens before the login page in this hook
     */
    public function pre_loginpage_hook() {
        $this->log(__FUNCTION__ . ' enter');
        $this->loginpage_hook();
        $this->log(__FUNCTION__ . ' exit');
    }

    /**
     * Returns a list of potential IdPs that this authentication plugin supports.
     * This is used to provide links on the login page.
     *
     * @param string $wantsurl the relative url fragment the user wants to get to.
     *
     * @return array of IdP's
     */
    public function loginpage_idp_list($wantsurl) {

        return array(
            array(
                'url'  => new moodle_url($wantsurl, array('saml' => 'on')),
                'icon' => new pix_icon('i/user', 'Login'),
                'name' => $this->config->idpname,
            ),
        );
    }

    /**
     * Shows an error page for various authenticatio issues.
     *
     * @param string $msg The error message.
     */
    public function error_page($msg) {
        global $PAGE, $OUTPUT, $SITE;

        $PAGE->set_course($SITE);
        $PAGE->set_url('/');
        echo $OUTPUT->header();
        echo $OUTPUT->box($msg);
        echo $OUTPUT->footer();
        // TODO kill session to enable login as somebody else.
        exit;
    }

    /**
     * All the checking happens before the login page in this hook
     */
    public function loginpage_hook() {
        global $CFG, $DB, $USER, $SESSION, $saml2auth;

        $this->log(__FUNCTION__ . ' enter');

        // If ?saml=on even when duallogin is on, go directly to IdP.
        $saml = optional_param('saml', 0, PARAM_BOOL);
        if (!$saml && $this->config->duallogin == 1) {
            $this->log(__FUNCTION__ . ' skipping due to dual auth');
            return;
        }

        // If ?saml=off then show the login page.
        $saml = optional_param('saml', 1, PARAM_BOOL);
        if ($saml == 0) {
            $this->log(__FUNCTION__ . ' skipping due to ?saml=off');
            return;
        }

        require_once('setup.php');
        require_once("$CFG->dirroot/login/lib.php");
        $auth = new SimpleSAML_Auth_Simple($this->spname);
        $auth->requireAuth();
        $attributes = $auth->getAttributes();

        $uid = '';
        $attr = $this->config->idpattr;
        if (!empty($attributes[$attr]) ) {
            $uid = $attributes[$attr][0];
        }
        if (!$uid) {
            $this->error_page(get_string('noattribute', 'auth_saml2', $attr));
        }
        if ($this->config->tolower) {
            $this->log(__FUNCTION__ . ' to lowercase');
            $uid = strtolower($uid);
        }
        if ($user = $DB->get_record('user', array( $this->config->mdlattr => $uid ))) {

            if (!$this->config->anyauth && $user->auth != 'saml2') {
                $this->log(__FUNCTION__ . " user $uid is auth type: $user->auth");
                $this->error_page(get_string('wrongauth', 'auth_saml2', $uid));
            }

            $this->log(__FUNCTION__ . ' found user '.$user->username);
            complete_user_login($user);
            $USER->loggedin = true;
            $USER->site = $CFG->wwwroot;
            set_moodle_cookie($USER->username);

            $urltogo = core_login_get_return_url();
            // If we are not on the page we want, then redirect to it.
            if ( qualified_me() !== $urltogo ) {
                $this->log(__FUNCTION__ . " redirecting to $urltogo");
                redirect($urltogo);
                exit;
            } else {
                $this->log(__FUNCTION__ . " continuing onto " . qualified_me() );
            }
        } else {
            $this->log(__FUNCTION__ . " user $uid is not in moodle");
            $this->error_page(get_string('nouser', 'auth_saml2', $uid));
        }

    }

    /**
     * Make sure we also cleanup the SAML session AND log out of the IdP
     */
    public function logoutpage_hook() {

        global $CFG, $saml2auth, $redirect;

        $this->log(__FUNCTION__ . ' Do moodle logout');

        // Do the normal moodle logout first as we may redirect away before it
        // gets called by the normal core process.
        require_logout();

        require_once('setup.php');
        $auth = new SimpleSAML_Auth_Simple($this->spname);

        // Only log out of the IdP if we logged in via the IdP. TODO check session timeouts.
        if ($auth->isAuthenticated()) {
            $this->log(__FUNCTION__ . ' Do SSP logout');
            $auth->logout($redirect);
        }
    }


    /**
     * Returns false regardless of the username and password as we never get
     * to the web form. If we do, some other auth plugin will handle it
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    public function user_login ($username, $password) {
        return false;
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param object $config
     * @param object $err
     * @param array $userfields
     */
    public function config_form($config, $err, $userfields) {
        $config = (object) array_merge($this->defaults, (array) $config );
        global $CFG, $OUTPUT;
        include("config.php");
    }

    /**
     * A chance to validate form data, and last chance to
     * do stuff before it is inserted in config_plugin
     *
     * @param object $form with submitted configuration settings (without system magic quotes)
     * @param array $err array of error messages
     *
     * @return array of any errors
     */
    public function validate_form($form, &$err) {

        global $CFG;

        // The IdP entityName needs to be parsed out of the XML.
        try {
            $xml = new SimpleXMLElement($form->idpmetadata);
            $form->entityid = ''.$xml['entityID'];
            if (empty($form->entityid)) {
                $err['idpmetadata'] = get_string('idpmetadata_noentityid', 'auth_saml2');
            } else {
                file_put_contents("$CFG->dataroot/saml2/idp.xml" , $form->idpmetadata);
            }
        } catch (Exception $e) {
            $err['idpmetadata'] = get_string('idpmetadata_invalid', 'auth_saml2');
        }
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     *
     * @param object $config
     */
    public function process_config($config) {
        foreach ($this->defaults as $key => $value) {
            set_config($key, $config->$key, 'auth_saml2');
        }
        return true;
    }

    /**
     * A simple GUI tester which shows the raw API output
     */
    public function test_settings() {
        include('test.php');
    }
}


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
 * SSP config which inherits from Moodle config
 *
 * @package    auth_saml2
 * @copyright  Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG, $saml2auth;

$config = array(

    'certdir'           => $saml2auth->certdir,
    'debug'             => $saml2auth->config->debug ? true : false,
    'logging.level'     => $saml2auth->config->debug ? SimpleSAML_Logger::DEBUG : SimpleSAML_Logger::ERR,
    'logging.handler'   => 'errorlog',
    'showerrors'        => $CFG->debugdisplay ? true : false,
    'errorreporting'    => false,
    'debug.validatexml' => false,
    'secretsalt'        => get_site_identifier(),

    'technicalcontact_name'  => $CFG->supportname,
    'technicalcontact_email' => $CFG->supportemail,

    /*
     * The timezone of the server. This option should be set to the timezone you want
     * simpleSAMLphp to report the time in. The default is to guess the timezone based
     * on your system timezone.
     *
     * See this page for a list of valid timezones: http://php.net/manual/en/timezones.php
     */
    'timezone' => 'crap',

    'session.duration'          => 60 * 60 * 8, // 8 hours. TODO same as moodle.
    'session.datastore.timeout' => 60 * 60 * 4,
    'session.state.timeout'     => 60 * 60,

    'session.cookie.name'     => 'SimpleSAMLSessionID',
    'session.cookie.path'     => '/', // TODO restrict to moodle path.
    'session.cookie.domain'   => null,
    'session.cookie.secure'   => false, // TODO.
    'session.cookie.lifetime' => 0,

    'session.phpsession.cookiename' => null,
    'session.phpsession.savepath'   => null,
    'session.phpsession.httponly'   => true,

    'session.authtoken.cookiename'  => 'SimpleSAMLAuthToken',

    'enable.http_post' => false,

    'metadata.sign.enable'          => true,
    'metadata.sign.certificate'     => $saml2auth->certcrt,
    'metadata.sign.privatekey'      => $saml2auth->certpem,
    'metadata.sign.privatekey_pass' => get_site_identifier(),
    'metadata.sources' => array(
        array('type' => 'xml', 'file' => "$CFG->dataroot/saml2/idp.xml"),
    ),

    /*
     * Piggy back SAML sessions inside the moodle DB
     */
    'store.type'           => 'sql',
    'store.sql.username'   => $CFG->dbuser,
    'store.sql.password'   => $CFG->dbpass,
    'store.sql.prefix'     => $CFG->prefix . 'authsaml_',
    'store.sql.persistent' => false,
    'store.sql.dsn'        => "{$CFG->dbtype}:host={$CFG->dbhost};dbname={$CFG->dbname}",

    'proxy' => null, // TODO inherit from moodle conf see http://moodle.local/admin/settings.php?section=http for more.

);

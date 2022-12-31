<?php
/**
 * Fax SMS Module Member
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2018-2019 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General public License 3
 */
namespace OpenEMR\Modules\FaxSMS\Controllers;

use OpenEMR\Common\Crypto\CryptoGen;

/**
 * Class AppDispatch
 *
 * @package OpenEMR\Modules\FaxSMS\Controllers
 */
abstract class AppDispatch
{
    private $_request, $_response, $_query, $_post, $_server, $_cookies, $_session;
    private $authUser;
    protected $crypto;
    protected $_currentAction;
    static $_apiService;
    const ACTION_DEFAULT = 'index';

    public function __construct()
    {
        $this->_request = &$_REQUEST;
        $this->_query = &$_GET;
        $this->_post = &$_POST;
        $this->_server = &$_SERVER;
        $this->_cookies = &$_COOKIE;
        $this->_session = &$_SESSION;
        $this->authUser = (int)$this->getSession('authUserID');
        $this->dispatchActions();
        $this->render();
    }

abstract function faxProcessUploads();
abstract function sendFax();
abstract function sendSMS();

private function indexAction()
    {
        return null;
    }

    private function dispatchActions()
    {
        $action = $this->getQuery('_ACTION_COMMAND');
        $this->_currentAction = $action;

        if ($action) {
            if (method_exists($this, $action)) {
                $this->setResponse(
                    call_user_func(array($this, $action), array())
                );
            } else {
                $this->setHeader("HTTP/1.0 404 Not Found");
            }
        } else {
            $this->setResponse(
                call_user_func(array($this, self::ACTION_DEFAULT), array())
            );
        }

        return $this->_response;
    }

    private function render()
    {
        if ($this->_response) {
            if (is_scalar($this->_response)) {
                echo $this->_response;
            } else {
                throw new \Exception('Response content must be scalar');
            }

            exit;
        }
    }

    // This is where we decide which Api to use.
    private static function getServiceInstance()
    {
        $s = self::getServiceType();
        switch ($s) {
            case 0:
                http_response_code(404);
                exit();
            case 1:
                return new RCFaxClient();
                break;
            case 2:
                return new TwilioFaxClient();
        }
    }

    static function getApiService()
    {
        self::$_apiService = self::getServiceInstance();
        return self::$_apiService;
    }

    static function getServiceType()
    {
        return $GLOBALS['oefax_enable'];
    }

    public function getRequest($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_request[$param]) ?
                $this->_request[$param] : $default;
        }

        return $this->_request;
    }

    public function getQuery($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_query[$param]) ?
                $this->_query[$param] : $default;
        }

        return $this->_query;
    }

    public function getPost($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_post[$param]) ?
                $this->_post[$param] : $default;
        }

        return $this->_post;
    }

    public function setResponse($content)
    {
        $this->_response = $content;
    }

    public function getServer($param = null, $default = null)
    {
        if ($param) {
            return $this->_server[$param] ?? $default;
        }

        return $this->_server;
    }

    public function getSession($param = null, $default = null)
    {
        if ($param) {
            return $this->_session[$param] ?? $default;
        }

        return $this->_session;
    }

    public function getCookie($param = null, $default = null)
    {
        if ($param) {
            return $this->_cookies[$param] ?? $default;
        }

        return $this->_cookies;
    }

    public function setHeader($params)
    {
        if (!headers_sent()) {
            if (is_scalar($params)) {
                header($params);
            } else {
                foreach ($params as $key => $value) {
                    header(sprintf('%s: %s', $key, $value));
                }
            }
        }

        return $this;
    }

    public function setSession($key, $value)
    {
        $_SESSION[$key] = $value;
        return $this;
    }

    public function setCookie($key, $value, $seconds = 3600)
    {
        $this->_cookies[$key] = $value;
        if (!headers_sent()) {
            setcookie($key, $value, time() + $seconds);
            return $this;
        }
        return 0;
    }

    protected function saveSetup($setup = []): string
    {
        if (empty($setup)) {
            $username = $this->getRequest('username');
            $ext = $this->getRequest('extension');
            $password = $this->getRequest('password');
            $appkey = $this->getRequest('key');
            $appsecret = $this->getRequest('secret');
            $production = $this->getRequest('production');
            $smsNumber = $this->getRequest('smsnumber');
            $smsMessage = $this->getRequest('smsmessage');
            $smsHours = $this->getRequest('smshours');
            $setup = array(
                'username' => "$username",
                'extension' => "$ext",
                'password' => "$password",
                'appKey' => "$appkey",
                'appSecret' => "$appsecret",
                'server' => !$production ? 'https://platform.devtest.ringcentral.com' : "https://platform.ringcentral.com",
                'portal' => !$production ? "https://service.devtest.ringcentral.com/" : "https://service.ringcentral.com/",
                'smsNumber' => "$smsNumber",
                'production' => $production,
                'redirect_url' => $this->getRequest('redirect_url'),
                'smsHours' => $smsHours,
                'smsMessage' => $smsMessage
            );
        }
        $vendor = self::getServiceType() === '1' ? '_ringcentral' : '_twilio';

        $content = $this->crypto->encryptStandard(json_encode($setup));

        // for now we'll allow any user to use this service initial setup user credentials
        // @todo allow setup option to restrict who is allowed to use
        $this->authUser = 0;
        $sql = "INSERT INTO `module_faxsms_credentials` (`id`, `auth_user`, `vendor`, `credentials`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `auth_user`= ?, `vendor` = ?, `credentials`= ?";
        sqlStatement($sql, array('', $this->authUser,$vendor, $content, $this->authUser, $vendor, $content));
        return xlt('Save Success');
    }

    /**
     * Common credentials storage between services
     * the service class will set specific credential.
     *
     * @return array|mixed
     */
    protected function getSetup()
    {
$DBSQL = <<<'DB'
CREATE TABLE IF NOT EXISTS `module_faxsms_credentials`
(
`id` int(10) UNSIGNED NOT NULL,
`auth_user` int(10) UNSIGNED DEFAULT '0',
`vendor` varchar(60)  DEFAULT NULL,
`credentials` mediumblob  NOT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `vendor` (`vendor`)
) ENGINE = InnoDB COMMENT ='Vendor credentials for Fax/SMS';
DB;
        $db = $GLOBALS['dbase'];
        $exist = sqlQuery("SHOW TABLES FROM `$db` LIKE 'module_faxsms_credentials'");
        if (empty($exist)) {
            $exist = sqlQuery($DBSQL);
        }
        $vendor = self::getServiceType() === '1' ? '_ringcentral' : '_twilio';
        // for now we'll allow all users to use this service credentials
        $this->authUser = 0;

        $credentials = sqlQuery("SELECT * FROM `module_faxsms_credentials` WHERE `auth_user` = ? AND `vendor` = ?", array($this->authUser, $vendor))['credentials'];

        if(empty($credentials)) {
            $credentials = array(
                'username' => '',
                'extension' => '',
                'password' => '',
                'appKey' => '',
                'appSecret' => '',
                'server' => '',
                'portal' => '',
                'smsNumber' => '',
                'production' => '',
                'redirect_url' => '',
                'smsHours' => "50",
                'smsMessage' => "A courtesy reminder for ***NAME*** \r\nFor the appointment scheduled on: ***DATE*** At: ***STARTTIME*** Until: ***ENDTIME*** \r\nWith: ***PROVIDER*** Of: ***ORG***\r\nPlease call if unable to attend.",
            );
            return $credentials;
        }

        $credentials = json_decode($this->crypto->decryptStandard($credentials), true);

        return $credentials;
    }
}

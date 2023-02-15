<?php

/**
 * Fax SMS Module Member
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2018-2023 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General public License 3
 */

namespace OpenEMR\Modules\FaxSMS\Controllers;

/**
 * Class AppDispatch
 *
 * @package OpenEMR\Modules\FaxSMS\Controllers
 */
abstract class AppDispatch
{
    const ACTION_DEFAULT = 'index';
    static $_apiService;
    static $_apiModule;
    public static $timeZone;
    protected $crypto;
    protected $_currentAction;
    private $_request, $_response, $_query, $_post, $_server, $_cookies, $_session;
    private $authUser;

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

    /**
     * @param $param
     * @param $default
     * @return mixed|null
     */
    public function getSession($param = null, $default = null): mixed
    {
        if ($param) {
            return $_SESSION[$param] ?? $default;
        }

        return $this->_session;
    }

    /**
     * @return void
     */
    private function dispatchActions(): void
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
    }

    /**
     * @param $param
     * @param $default
     * @return mixed|null
     */
    public function getQuery($param = null, $default = null): mixed
    {
        if ($param) {
            return $this->_query[$param] ?? $default;
        }

        return $this->_query;
    }

    /**
     * @param $content
     * @return void
     */
    public function setResponse($content): void
    {
        $this->_response = $content;
    }

    /**
     * @param $params
     * @return $this
     */
    public function setHeader($params): static
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

    /**
     * @return void
     * @throws \Exception
     */
    private function render(): void
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

    /**
     * @param string $type
     * @return EtherFaxActions|RCFaxClient|TwilioSMSClient|void|null
     */
    static function getApiService(string $type)
    {
        try {
            self::setModuleType($type);
            self::$_apiService = self::getServiceInstance($type);
            return self::$_apiService;
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }

    /**
     * @param $type
     * @return void
     */
    static function setModuleType($type): void
    {
        $_SESSION['current_module_type'] = $type;
        self::$_apiModule = $type;
    }

    /**
     * @param $type
     * @return EtherFaxActions|RCFaxClient|TwilioSMSClient|void
     */
    private static function getServiceInstance($type)
    {
        $s = self::getServiceType();
        if ($type == 'sms') {
            switch ($s) {
                case 0:
                    http_response_code(404);
                    exit();
                case 1:
                    return new RCFaxClient();
                case 2:
                    return new TwilioSMSClient();
            }
        } elseif ($type == 'fax') {
            switch ($s) {
                case 0:
                    http_response_code(404);
                    exit();
                case 1:
                    return new RCFaxClient();
                case 3:
                    return new EtherFaxActions();
            }
        }

        http_response_code(404);
        exit();
    }

    /**
     * @return int|mixed
     */
    static function getServiceType(): mixed
    {
        if (empty(self::$_apiModule)) {
            self::$_apiModule = $_SESSION['current_module_type'];
            if (empty(self::$_apiModule)) {
                self::$_apiModule = $_REQUEST['type'];
            }
        }
        if (self::$_apiModule == 'sms') {
            return $GLOBALS['oefax_enable_sms'];
        }
        if (self::$_apiModule == 'fax') {
            return $GLOBALS['oefax_enable_fax'];
        }

        return 0;
    }

    /**
     * @return mixed
     */
    static function getModuleType(): mixed
    {
        return self::$_apiModule;
    }

    /**
     * @return mixed
     */
    abstract function faxProcessUploads();

    /**
     * @return mixed
     */
    abstract function sendFax();

    /**
     * @return mixed
     */
    abstract function sendSMS();

    /**
     * @param $param
     * @param $default
     * @return mixed|null
     */
    public function getPost($param = null, $default = null): mixed
    {
        if ($param) {
            return $this->_post[$param] ?? $default;
        }

        return $this->_post;
    }

    /**
     * @param $param
     * @param $default
     * @return mixed|null
     */
    public function getServer($param = null, $default = null): mixed
    {
        if ($param) {
            return $this->_server[$param] ?? $default;
        }

        return $this->_server;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setSession($key, $value): static
    {
        $_SESSION[$key] = $value;
        return $this;
    }

    /**
     * @param array $setup
     * @return string
     */
    protected function saveSetup(array $setup = []): string
    {
        if (empty($setup)) {
            $username = $this->getRequest('username');
            $ext = $this->getRequest('extension');
            $account = $this->getRequest('account');
            $phone = $this->getRequest('phone');
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
                'account' => $account,
                'phone' => $phone,
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

        $vendor = self::getModuleVendor();
        $this->authUser = (int)$this->getSession('authUserID');

        $content = $this->crypto->encryptStandard(json_encode($setup));

        $sql = "INSERT INTO `module_faxsms_credentials` (`id`, `auth_user`, `vendor`, `credentials`) 
            VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `auth_user`= ?, `vendor` = ?, `credentials`= ?, `updated` = NOW()";
        sqlStatement($sql, array('', $this->authUser, $vendor, $content, $this->authUser, $vendor, $content));
        return xlt('Save Success');
    }

    /**
     * @param $param
     * @param $default
     * @return mixed|null
     */
    public function getRequest($param = null, $default = null): mixed
    {
        if ($param) {
            return $this->_request[$param] ?? $default;
        }

        return $this->_request;
    }

    /**
     * @return mixed
     */
    static function getModuleVendor(): mixed
    {
        switch ((string)self::getServiceType()) {
            case '1':
                return '_ringcentral';
            case '2':
                return '_twilio';
            case '3':
                return '_etherfax';
        }
        return null;
    }

    /**
     * Common credentials storage between services
     * the service class will set specific credential.
     *
     * @return array|mixed
     */
    protected function getSetup(): mixed
    {
        $vendor = self::getModuleVendor();
        $this->authUser = (int)$this->getSession('authUserID');

        $credentials = sqlQuery("SELECT * FROM `module_faxsms_credentials` WHERE `auth_user` = ? AND `vendor` = ?", array($this->authUser, $vendor))['credentials'];

        if (empty($credentials)) {
            $credentials = array(
                'username' => '',
                'extension' => '',
                'password' => '',
                'account' => '',
                'phone' => '',
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

        return json_decode($this->crypto->decryptStandard($credentials), true);
    }

    /**
     * @return null
     */
    private function indexAction()
    {
        return null;
    }
}

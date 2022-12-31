<?php
/**
 * Fax Server SMS Module Member
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2019 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$sessionAllowWrite = true;
$ignoreAuth = 1;
require_once(__DIR__ . "/../../../../globals.php");

use OpenEMR\Common\Crypto\CryptoGen;
use Twilio\Security\RequestValidator;

class FaxServer
{
    private $baseDir;
    private $crypto, $production;
    private $authToken, $authUser;

    public function __construct()
    {
        $this->baseDir = $GLOBALS['temporary_files_dir'];
        $this->cacheDir = $GLOBALS['OE_SITE_DIR'] . '/documents/logs_and_misc/_cache';
        $this->serverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        $this->crypto = new CryptoGen();
        $this->authUser = 0;
        $this->getCredentials();
        if($this->production){
            $this->validate();
        }

        $this->dispatchActions();
    }

    private function dispatchActions()
    {
        $action = $_GET['_FAX'];

        if ($action) {
            if (method_exists($this, $action)) {
                call_user_func(array($this, $action), array());
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(401);
        }

        exit;
    }

    private function serveFax()
    {
        $file = $_GET['file'];
        $FAX_FILE = $this->baseDir . '/send/' . $file;
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type: application/pdf");
        header("Content-Length: " . filesize($FAX_FILE));
        header("Content-Disposition: attachment; filename=" . basename($FAX_FILE));
        header("Content-Description: File Transfer");

        if (is_file($FAX_FILE)) {
            $chunkSize = 1024 * 1024;
            $handle = fopen($FAX_FILE, 'rb');
            while (!feof($handle)) {
                $buffer = fread($handle, $chunkSize);
                echo $buffer;
                ob_flush();
                flush();
            }
            fclose($handle);
        } else {
            error_log(errorLogEscape("Serve File Not Found " . $FAX_FILE));
            http_response_code(404);
            exit;
        }
        unlink($FAX_FILE);
        exit;
    }

    private function getCredentials()
    {
        $this->authUser = 0;
        $credentials = sqlQuery("SELECT * FROM `module_faxsms_credentials` WHERE `auth_user` = ? AND `vendor` = ?", array($this->authUser, '_twilio'))['credentials'];

        if(empty($credentials)) {
            // for legacy
            $cacheDir = $GLOBALS['OE_SITE_DIR'] . '/documents/logs_and_misc/_cache';
            $fn = '/_credentials_twilio.php';
            $credentials = file_get_contents($cacheDir . $fn);
            if(empty($credentials)) {
                error_log(errorLogEscape("Failed get auth: legacy"));
                http_response_code(401);
                exit;
            }
        }

        $credentials = json_decode($this->crypto->decryptStandard($credentials), true);
        $this->authToken = $credentials['password'];
        $this->production = $credentials['production'];
        unset($credentials);

        return;
    }

    // verify request signature from twilio
    // locally compute hash. Not used currently.
    private function verify($file = null)
    {
        $url = $this->serverUrl . $_SERVER['REQUEST_URI'];
        $me = $this->computeSignature($url, $_POST);
        $them = $_SERVER["HTTP_X_TWILIO_SIGNATURE"];
        $agree = $me === $them;
        if ($agree) {
            return $agree;
        } else {
            error_log(errorLogEscape("Failed request verification me: " . $me . ' them: ' . $them));
            http_response_code(401);
            exit;
        }
    }

    private function computeSignature($url, $data = array())
    {
        ksort($data);
        foreach ($data as $key => $value) {
            $url = $url . $key . $value;
        }
        // calculates the HMAC hash of the data with the key of authToken
        $hmac = hash_hmac("sha1", $url, $this->authToken, true);
        return base64_encode($hmac);
    }

    protected function faxCallback()
    {
        $file_path = $_POST['OriginalMediaUrl'];
        ['basename' => $basename, 'dirname' => $dirname] = pathinfo($file_path);
        $file = $this->baseDir . '/send/' . $basename;
        // they own it now so throw away.
        unlink($file);
        http_response_code(200);
        exit;
    }

    protected function receivedFax()
    {
        $dispose_uri = $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-faxsms/faxserver/receiveContent';
        $twimlResponse = new SimpleXMLElement("<Response></Response>");
        $receiveEl = $twimlResponse->addChild('Receive');
        $receiveEl->addAttribute('action', $dispose_uri);
        header('Content-type: text/xml');
        echo $twimlResponse->asXML();
        exit;
    }

    protected function receiveContent()
    {
        // Throw away content. we'll manage on their server.
        $file = $_POST["MediaUrl"];
        header('Content-type: text/xml');
        http_response_code(200);
        echo '';
        exit;
    }

    /**
     * Twilio validation of computed signatures
     * This will ensure we're only transacting with Twilio.
     *
     * @return bool
     */
    private function validate() {
        $url = $this->serverUrl . $_SERVER['REQUEST_URI'];
        $me = $this->computeSignature($url, $_POST);
        $signature = $_SERVER["HTTP_X_TWILIO_SIGNATURE"];
        $token = $this->authToken;
        $postVars = $_POST;

        // Initialize the validator
        $validator = new RequestValidator($token);

        if ($validator->validate($signature, $url, $postVars)) {
            //error_log(errorLogEscape("Confirmed Request from Twilio."));
            return true;
        } else {
            error_log(errorLogEscape("Failed Request Signature verification Url: $url Computed: " . $me . ' Twilio: ' . $signature));
            http_response_code(401);
            exit;
        }
    }
}

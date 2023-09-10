<?php

namespace OpenEMR\Modules\FaxSMS\EtherFax;

use DateTime;
use Guzzle\GuzzleHttp\GuzzleException;
use Guzzle\Http\Client;
use http\Exception;

/*
 * OpenEMR\Modules\FaxSMS\EtherFax\EtherFaxClient class.
 */

class EtherFaxClient
{
    // class constants
    const EFAX_API_URL = 'https://na.connect.etherfax.net/rest/3.0/api';
    const DEFAULT_TIMEOUT = 30;
    const HTTP_OK = 200;
    private static $timeZone;

    protected $auth;
    protected $httpCode;
    protected $timeout;

    public function __construct($account = null, $user = null, $password = null, $key = null)
    {
        // set credentials, default timeout
        $this->setCredentials($account, $user, $password, $key);
        $this->timeout = EtherFaxClient::DEFAULT_TIMEOUT;
    }

    /*
     * Sets the credentials to use when connecting to the etherFAX web service.
     */
    /**
     * @param $account
     * @param $user
     * @param $password
     * @param $key
     * @return void
     */
    public function setCredentials($account, $user, $password, $key)
    {
        // set credentials
        if (is_null($user)) {
            $this->auth = null;
        }
        if (!empty($key)) {
            $this->auth = 'Bearer ' . $key;
        } elseif (!empty($user)) {
            $this->auth = 'Basic ' . base64_decode($account . '/' . $user . ':' . $password);
        }
    }

    /**
     * @param $timeout
     * @return void
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /*
     * Returns the $httpCode from the last operation.
     */
    /**
     * @return mixed
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /*
     * Get fax account information.
     */
    /**
     * @return FaxAccount|null
     */
    public function getFaxAccount()
    {
        $account = null;

        // get account status
        $response = $this->clientHttpGet('/accounts?a=status');
        if ($response && $this->isOK()) {
            // process response
            $account = new FaxAccount();
            $account->set(json_decode($response));
        }
        self::$timeZone = $account->TimeZone ?? null;

        return $account;
    }

    /**
     * @param            $url
     * @param array|null $get
     * @return string
     */
    private function clientHttpGet($url, array $get = null)
    {
        // create full uri request
        $uri = EtherFaxClient::EFAX_API_URL . $url;
        if ($get != null) {
            $uri .= '?' . http_build_query($get);
        }

        $client = new \GuzzleHttp\Client(array(
            "defaults" => array(
                "allow_redirects" => true,
                "exceptions" => true,
                "decode_content" => true,
            ),
            'verify' => false,
            //'proxy' => "localhost:8888",
        ));

        try {
            $response = $client->request('GET', $uri, [
                'debug' => false,
                'headers' => [
                    'accept' => 'application/json',
                    'Authorization' => $this->auth,
                ],
            ]);
        } catch (GuzzleException $e) {
            return $e->getMessage();
        }
        $this->httpCode = $response->getStatusCode();
        $response->getBody()->rewind();

        return $response->getBody();
    }

    /**
     * @return bool
     */
    public function isOK()
    {
        return $this->httpCode == EtherFaxClient::HTTP_OK;
    }

    /**
     * @param $number
     * @param $file
     * @param $pages
     * @param $localId
     * @param $callerId
     * @param $tag
     * @param $tz
     * @return FaxStatus
     */
    public function sendFax($number, $file, $pages, $localId = null, $callerId = null, $tag = null, $tz = null): FaxStatus
    {
        // create fax status
        $status = new FaxStatus();
        if (is_file($file)) {
            $data = file_get_contents($file);
            if (strlen($data) == 0) {
                $status->Result = FaxResult::InvalidOrMissingFile;
                return $status;
            }
            unlink($file);
        } else {
            // is content of document
            $data = $file;
        }
        // set default timezone offset
        $tz = $GLOBALS['gbl_time_zone'] ?: self::$timeZone;
        if (empty($tz)) {
            $now = new DateTime();
            $tz = (string)($now->getOffset() / 3600);
        }
        // set default page count
        if (is_null($pages)) {
            $pages = 0;
        }
        // create post array/items
        $post = array(
            'DialNumber' => $number,
            'FaxImage' => base64_encode($data),
            'TotalPages' => $pages,
            'TimeZoneOffset' => $tz
        );
        // add optional items
        if (!is_null($localId)) {
            $post['LocalId'] = $localId;
        }
        if (!is_null($callerId)) {
            $post['CallerId'] = $callerId;
        }
        if (!is_null($tag)) {
            $post['Tag'] = $tag;
        }
        $post['HeaderString'] = "  {date:d-MMM-yyyy}  {time}   FROM: {csid}  TO: {number}   P. {page}";
        // set error default
        $status->Result = FaxResult::Error;
        // send fax
        $response = $this->clientHttpPost('/outbox', $post);
        if ($response && $this->isOK()) {
            $status->set(json_decode($response));
        } else {
            // This will have an error message in response.
            $status->set(json_decode($response));
        }
        return $status;
    }

    /*
     * Handles the http request "post" method and returns the response.
     * The vars contain an array of items that will be url encoded.
     */
    /**
     * @param            $url
     * @param array|null $post
     * @return bool|string
     */
    private function clientHttpPost($url, array $post = null): bool|string
    {
        // create full uri
        $uri = EtherFaxClient::EFAX_API_URL . $url;

        try {
            $client = new \GuzzleHttp\Client();

            $response = $client->request('POST', $uri, [
                'allow_redirects' => false,
                'form_params' => $post,
                'headers' => [
                    'Authorization' => $this->auth,
                    'accept' => 'application/json',
                    'content-type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $this->httpCode = $response->getStatusCode();
            $result = $response->getBody();
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return $result;
    }

    /*
     * Returns the status of the specified fax id.
     *
     * @param $id
     * @return FaxStatus|null
     */
    /**
     * @param $id
     * @return FaxStatus|null
     */
    public function getFaxStatus($id): ?FaxStatus
    {
        // create fax status
        $status = null;

        // get fax status for specified id
        $response = $this->clientHttpGet('/status?id=' . $id);
        if ($response && $this->isOK()) {
            $status = new FaxStatus();
            $status->set(json_decode($response));
        }

        // return status
        return $status;
    }

    /*
     * Gets the number of pending faxes waiting for delivery.
     */
    /**
     * @return int
     */
    public function getPendingFaxCount(): int
    {
        // get pending fax count
        $response = $this->clientHttpGet('/outbox?a=pending');
        if ($response && $this->isOK()) {
            $obj = json_decode($response);
            return intval($obj->{'PendingFaxes'});
        }

        return 0;
    }

    /*
     * Gets the number of unread faxes.
     */
    /**
     * @return int
     */
    public function getUnreadFaxCount(): int
    {
        // get unread fax count
        $response = $this->clientHttpGet('/inbox?a=unread');
        if ($response && $this->isOK()) {
            $obj = json_decode($response);
            return intval($obj->{'UnreadFaxes'});
        }

        return 0;
    }

    /*
     * Gets a list of unread faxes.
     */
    /**
     * @return mixed|null
     */
    public function getUnreadFaxList()
    {
        // get unread fax list
        $response = $this->clientHttpGet('/inbox?a=list');
        if ($response && $this->isOK()) {
            return json_decode($response);
        }

        return null;
    }

    /*
     * Retrieves (downloads) the specified fax id including the image.
     */
    /*
     * @param $id
     * @return FaxReceive|null
     */
    /**
     * @param $id
     * @return FaxReceive|null
     */
    public function getFax($id): ?FaxReceive
    {
        // retrieve the specified fax
        $response = $this->clientHttpGet('/inbox?a=get&f=pdf&id=' . $id);
        if ($response && $this->isOK()) {
            $fax = new FaxReceive();
            $set = json_decode($response);
            $fax->set($set);
            return $fax;
        }

        return null;
    }

    /*
     * Retrieves the next unread fax (oldest). This function will automatically flag
     * the received fax so other peers may not download it. This flag will be released
     * if the fax is not received within 5 minutes (by default). The FaxImage will only be
     * downloaded if $download = true.
     */
    /**
     * @param $download
     * @param $sid
     * @return FaxReceive|null
     */
    public function getNextUnreadFax($download = false, $sid = null)
    {
        // create set parameters
        $get = array(
            'a' => 'getnext',
            'download' => $download ? "1" : "0"
        );

        // sid?
        if (!is_null($sid)) {
            $get ['sid'] = $sid;
        }

        // retrieve the specified fax
        $response = $this->clientHttpGet('/inbox', $get);
        if ($response && $this->isOK()) {
            $fax = new FaxReceive();
            $fax->set(json_decode($response));
            return $fax;
        }

        return null;
    }

    /*
     * Sets the specified fax id as "received". Once called, this fax will no longer
     * appear in the unread fax items.
     */
    /**
     * @param $id
     * @return bool
     */
    public function setFaxReceived($id)
    {
        // set fax as received
        $response = $this->clientHttpGet('/inbox?a=received&id=' . $id);
        if ($response && $this->isOK()) {
            return true;
        }

        return false;
    }

    /*
     * Retrieves the accepted formats for the route (number) specified.
     */
    /**
     * @param $id
     * @return mixed|null
     */
    public function getRouteInfo($id)
    {
        $response = $this->clientHttpGet('/routes?a=info&id=' . $id);
        if ($response && $this->isOK()) {
            return json_decode($response);
        }

        return null;
    }
}

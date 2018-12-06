<?php

namespace EPG\EasyPaymentGateway\Model;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Api
{
    /**
     * @var ScopeConfigInterface
     */
    protected $_configScopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    protected $_logger;

    public function __construct(ScopeConfigInterface $configScopeConfigInterface,
        StoreManagerInterface $modelStoreManagerInterface)
    {
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/epg_' . date('Ymd') . '.log');
        $this->_logger = new \Zend\Log\Logger();
        $this->_logger->addWriter($writer);
    }

    const BASE_ENDPOINT_SANDBOX_MEP = 'https://epgjs-mep-stg.easypaymentgateway.com/';
    const BASE_ENDPOINT_PRODUCTION_MEP = 'https://epgjs-mep.easypaymentgateway.com/';

    const BASE_ENDPOINT_SANDBOX_WEB = 'https://epgjs-web-stg.easypaymentgateway.com/';
    const BASE_ENDPOINT_PRODUCTION_WEB = 'https://epgjs-web.easypaymentgateway.com/';

    const BASE_ENDPOINT_SANDBOX_CHECKOUT = 'https://checkout-stg.easypaymentgateway.com/EPGCheckout';
    const BASE_ENDPOINT_PRODUCTION_CHECKOUT = 'https://checkout.easypaymentgateway.com/EPGCheckout';

    const CONNECTION_TIMEOUT = 120;

    /**
     * Authentication
     */
    public function authentication(
            $customerId,
            $currency,
            $country,
            $operation = 'debit'
            )
    {
        $client = new HttpClient(['verify' => false]);
        try {

            $headers = [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ];
            $body = [
                    'merchantId' => trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_merchant_id', ScopeInterface::SCOPE_STORE)),
                    'customerId' => $customerId,
                    'merchantKey' => trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_merchant_key', ScopeInterface::SCOPE_STORE)),
                    'productId' => trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_product_id', ScopeInterface::SCOPE_STORE)),
                    'country' => $country,
                    'currency' => $currency,
                    'operations' => [$operation]
                ];

            $this->debugLog('Authentication - operation: ' . $operation);
            $this->debugLog('Authentication - customerId: ' . $customerId);

            $response = $client->post( $this->getBaseEndPoint(false) . 'auth', [
                'timeout' => self::CONNECTION_TIMEOUT,
                'headers' => $headers,
                'body' => empty($body)?'{}':json_encode($body)
            ]);

            if ($response->getStatusCode() == 200) {
                $result = json_decode($response->getBody(), true);
                if (isset($result['authToken'])) {
                    return $result['authToken'];
                }
            }

        } catch (RequestException $e) {
            $this->debugLog('Authentication error: ' . $e->getMessage(), 'error');
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody(), true);
                $error = isset($error['errorMessage'])?$error['errorMessage']:$error;
                $error = isset($error['message'])?$error['message']:$error;
                throw new \Exception(print_r($error, true));
            }
        }

        return false;
    }

    /**
     * Cashier
     */
    public function cashier($authToken, $operation = 'debit/credit')
    {
        $client = new HttpClient(['verify' => false]);
        try {

            $headers = [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'authToken' => $authToken
                    ];

            $this->debugLog('Cashier - authToken: ' . $authToken);

            $response = $client->get( $this->getBaseEndPoint() . 'cashier', array(
                'timeout' => self::CONNECTION_TIMEOUT,
                'headers' => $headers,
                'form_params' => array()
            ));

            if ($response->getStatusCode() == 200) {
                $result = json_decode($response->getBody(), true);
                if (isset($result['paymentMethods'])) {
                    return $result;
                }
            }

        } catch (RequestException $e) {
            $this->debugLog('Cashier error: ' . $e->getMessage(), 'error');
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody(), true);
                $error = isset($error['errorMessage'])?$error['errorMessage']:$error;
                $error = isset($error['message'])?$error['message']:$error;
                throw new \Exception(print_r($error, true));
            }
        }

        return false;
    }

    /**
     * Register account
     */
     public function registerAccount($authToken, $fields, $method = 'creditcards')
     {
         $client = new HttpClient(['verify' => false]);
         try {

             $headers = array(
                     'Content-Type' => 'application/json',
                     'Accept' => 'application/json',
                     'authToken' => $authToken
                     );

             $body = $fields;

             $this->debugLog('Register account - request endPoint: ' . ($this->getBaseEndPoint(false) . 'account/' . $method));
             //$this->debugLog('Register account - fields: ' . json_encode($fields));

             $response = $client->post( $this->getBaseEndPoint() . 'account/' . $method, array(
                 'timeout' => self::CONNECTION_TIMEOUT,
                 'headers' => $headers,
                 'body' => empty($body)?'{}':json_encode($body)
             ));

             $this->debugLog('Register account - response status: ' . $response->getStatusCode());

             $result = null;
             if ($response->getStatusCode() == 200) {
                 $result = json_decode($response->getBody(), true);

                 $this->debugLog('Register account - result: ' . json_encode($result));

                 if (isset($result['accountId'])) {
                     return $result;
                 }
             }

         } catch (RequestException $e) {
             $this->debugLog('Register account error: ' . $e->getMessage(), 'error');
             if ($e->hasResponse()) {
                 $error = json_decode($e->getResponse()->getBody(), true);
                 $error = isset($error['errorMessage'])?$error['errorMessage']:$error;
                 $error = isset($error['message'])?$error['message']:$error;
                 throw new \Exception(print_r($error, true));
             }
         }

         return false;
     }

    /**
     * Disable account
     */
     public function disableAccount($authToken, $accountId)
     {
         $client = new HttpClient(['verify' => false]);
         try {

             $headers = array(
                     'Content-Type' => 'application/json',
                     'Accept' => 'application/json',
                     'authToken' => $authToken
                     );

 			       $this->debugLog('Disable account: ' . $accountId);

             $response = $client->post( $this->getBaseEndPoint() . 'account/disable/' . $accountId, array(
                 'timeout' => self::CONNECTION_TIMEOUT,
                 'headers' => $headers,
             ));

             $result = null;
             if ($response->getStatusCode() == 200) {
                 $result = json_decode($response->getBody(), true);
                 $this->debugLog('Disable account response: ' . json_encode($result));
                 if (isset($result['accountId'])) {
                     return $result;
                 }
             }

         } catch (RequestException $e) {
             $this->debugLog('Disable account error: ' . $e->getMessage(), 'error');
             if ($e->hasResponse()) {
                 $error = json_decode($e->getResponse()->getBody(), true);
                 $error = isset($error['errorMessage'])?$error['errorMessage']:$error;
                 $error = isset($error['message'])?$error['message']:$error;
                 throw new \Exception(print_r($error, true));
             }
         }

         return false;
     }

    /**
     * Prepay token
     */
     public function prepayToken($authToken, $accountId, $fields = [])
     {
         $client = new HttpClient(['verify' => false]);
         try {

             $headers = array(
                     'Content-Type' => 'application/json',
                     'Accept' => 'application/json',
                     'authToken' => $authToken
                     );

             $body = $fields;

 			       $this->debugLog('Prepay token');
             $this->debugLog('Prepay token request body: ' . json_encode($fields));

             $response = $client->post( $this->getBaseEndPoint() . 'prepay/' . $accountId, array(
                 'timeout' => self::CONNECTION_TIMEOUT,
                 'headers' => $headers,
                 'body' => empty($body)?'{}':json_encode($body)
             ));

             $result = null;
             if ($response->getStatusCode() == 200) {
                 $result = json_decode($response->getBody(), true);
                 if (isset($result['prepayToken'])) {
                     return $result['prepayToken'];
                 }
             }

         } catch (RequestException $e) {
             $this->debugLog('Prepay token error: ' . $e->getMessage(), 'error');
             if ($e->hasResponse()) {
                 $error = json_decode($e->getResponse()->getBody(), true);
                 $error = isset($error['errorMessage'])?$error['errorMessage']:$error;
                 $error = isset($error['message'])?$error['message']:$error;
                 throw new \Exception(print_r($error, true));
             }
         }

         return false;
     }

    /**
     * Charge
     */
     public function charge($prepayToken, $customerId, $customer, $address, $total, $currency, $country, $language, $statusURL, $successURL, $awaitingURL, $errorURL, $cancelURL, $paymentSolution = 'CreditCards', $operationType = 'credit')
     {
         $merchantTransactionId = $this->idGenerator();
         $client = new HttpClient(['verify' => false]);
         try {

             $headers = array(
                     'Content-Type' => 'application/json',
                     'Accept' => 'application/json',
                     'prepayToken' => $prepayToken
                     );

             $body = array(
                     'amount' => number_format($total, 2),
                     'description' => 'Payment from ' . $this->_modelStoreManagerInterface->getStore()->getName() . '.',
                     'statusURL' => $statusURL,
                     'successURL' => $successURL,
                     'awaitingURL' => $awaitingURL,
                     'errorURL' => $errorURL,
                     'cancelURL' => $cancelURL,
                     'firstName' => $address->getFirstname(),
                     'lastName' => $address->getLastname(),
                     'customerEmail' => $address->getEmail(),
                     'addressLine1' => is_array($address->getStreet())?$address->getStreet()[0]:$address->getStreet(),
                     'addressLine2' => '',
                     'city' => $address->getCity(),
                     'postCode' => $address->getPostcode(),
                     'telephone' => $address->getTelephone(),
                     'customerCountry' => $address->getCountryId(),
                     'customerCompanyName' => $address->getCompany(),

                     'operationType'=> $operationType,
                     'merchantId'=> trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_merchant_id', ScopeInterface::SCOPE_STORE)),
                     'merchantPassword'=> trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_merchant_key', ScopeInterface::SCOPE_STORE)),
                     'productId'=> trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_product_id', ScopeInterface::SCOPE_STORE)),
                     'country' => $country,
                     'currency' => $currency,
                     'paymentSolution'=> $paymentSolution,
                     'merchantTransactionId'=> $merchantTransactionId,
                     'language'=> strtolower($language),
                     'customerId'=> $customerId
                 );

             $this->debugLog('Charge - request endPoint: ' . ($this->getBaseEndPoint(false) . 'charge'));
             $this->debugLog('Charge - request body: ' . json_encode($body));

             $response = $client->post( $this->getBaseEndPoint(false) . 'charge', array(
                 'timeout' => self::CONNECTION_TIMEOUT,
                 'headers' => $headers,
                 'body' => empty($body)?'{}':json_encode($body)
             ));

             $this->debugLog('Charge - response status: ' . $response->getStatusCode());

             if ($response->getStatusCode() == 200) {
                 $result = json_decode($response->getBody(), true);

                 $this->debugLog('Charge - result: ' . json_encode($result));

                 if (isset($result['response']) && $result['response'] != 'null') {

                     $xml = \simplexml_load_string($this->stripInvalidXml((string)$result['response']));
                     $json = json_encode($xml);
                     $data = json_decode($json, true);

                     if (!isset($data['operations']['operation'])) {
                         throw new \Exception('There are not found "operations > operation" field.');
                     }

                     if (is_array($data['operations']['operation']) && isset($data['operations']['operation']['status'])) {
                         $operation = $data['operations']['operation'];
                     } else {
                         $operation = $data['operations']['operation'][count($data['operations']['operation']) - 1];
                     }

                     if (empty($operation) || (!empty($operation) && !isset($operation['status']))) {
                         throw new \Exception('There are not found "status" field.');
                     }

                     $this->debugLog('Charge - result status: ' . $operation['status']);

                     if ($operation['status'] == 'ERROR' || $operation['status'] == 'FAIL') {
                         throw new \Exception($operation['message']);
                     }

                     $redirectUrl = null;
                     if (isset($operation['redirectionResponse'])) {
                         $redirection = $operation['redirectionResponse'];

                         if (substr($redirection, 0, 13 ) === "redirect:http") {
                             $redirectUrl = substr($redirection, 9);
                         } else {
                             $redirectUrl = preg_replace('#^redirect?:#', $this->getCheckoutEndPoint(), $redirection);
                         }
                     }

                     return [
                             'transactionResponse' => $data,
                             'transactionId' => $merchantTransactionId,
                             'status' => $operation['status'],
                             'epgCustomerId' => $customerId,
                             'redirectURL' => $redirectUrl
                         ];
                 }
             }

         } catch (RequestException $e) {
             $this->debugLog('Charge error: ' . $e->getMessage(), 'error');
             if ($e->hasResponse()) {
                 $error = json_decode($e->getResponse()->getBody(), true);
                 if (is_array($error)) {
                     $error = implode('<br/>', $error);
                 }
                 $this->debugLog('Charge - response body: ' . $e->getResponse()->getBody(), 'error');
                 $this->debugLog('Charge - error: ' . isset($error['errorMessage'])?$error['errorMessage']:$error, 'error');
                 throw new \Exception(isset($error['errorMessage'])?$error['errorMessage']:$error);
             }
         }

         return false;
     }

    /**
     *
     * @return string
     */
    private function getBaseEndPoint($web = true)
    {
        $sandobox = $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_sandbox', ScopeInterface::SCOPE_STORE) == '1';

        if ($sandobox) {
            if ($web) {
                return self::BASE_ENDPOINT_SANDBOX_WEB;
            }

            return self::BASE_ENDPOINT_SANDBOX_MEP;
        }

        if ($web) {
            return self::BASE_ENDPOINT_PRODUCTION_WEB;
        }

        return self::BASE_ENDPOINT_PRODUCTION_MEP;
    }

    /**
     *
     * @return string
     */
    private function getCheckoutEndPoint()
    {
        $sandobox = $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_sandbox', ScopeInterface::SCOPE_STORE) == '1';

        if ($sandobox) {
            return self::BASE_ENDPOINT_SANDBOX_CHECKOUT;
        }

        return self::BASE_ENDPOINT_PRODUCTION_CHECKOUT;
    }

    /**
     * Removes invalid XML
     *
     * @access public
     * @param string $value
     * @return string
     */
    private function stripInvalidXml($value)
    {
        $ret = "";
        $current;
        if (empty($value))
        {
            return $ret;
        }
        $length = strlen($value);
        for ($i=0; $i < $length; $i++)
        {
            $current = ord($value{$i});
            if (($current == 0x9) ||
                ($current == 0xA) ||
                ($current == 0xD) ||
                (($current >= 0x20) && ($current <= 0xD7FF)) ||
                (($current >= 0xE000) && ($current <= 0xFFFD)) ||
                (($current >= 0x10000) && ($current <= 0x10FFFF)))
            {
                $ret .= chr($current);
            }
            else
            {
                $ret .= " ";
            }
        }
        return $ret;
    }

    public function idGenerator($prefix = '', $length = 10) {
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($length / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($length / 2));
        } else {
            return $prefix . substring(md5(uniqid()), 0, $length);
        }
        return $prefix . substr(bin2hex($bytes), 0, $length);
    }

    private function debugLog($message = '', $level = 'debug')
    {
        $debug = $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_debug', ScopeInterface::SCOPE_STORE) == '1';

        if (!$debug) {
          return;
        }

        $message = 'EPG API | ' . $message;
        if ($level == 'critical') {
            $this->_logger->crit($message);
        } elseif ($level == 'error') {
            $this->_logger->err($message);
        } elseif ($level == 'warning') {
            $this->_logger->warn($message);
        } elseif ($level == 'info') {
            $this->_logger->info($message);
        } else {
            $this->_logger->debug($message);
        }
    }
}

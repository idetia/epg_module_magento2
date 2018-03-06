<?php

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

namespace EPG\EasyPaymentGateway\Model;

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

    public function __construct(ScopeConfigInterface $configScopeConfigInterface, 
        StoreManagerInterface $modelStoreManagerInterface)
    {
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;

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
            $country
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
                    'operations' => ['debit']
                ];

//            var_dump(json_encode($headers));
//            var_dump(json_encode($body));
//            var_dump(($this->getBaseEndPoint(false) . 'auth'));

            $response = $client->post( $this->getBaseEndPoint(false) . 'auth', [
                'timeout' => self::CONNECTION_TIMEOUT,
                'headers' => $headers,
                'body' => json_encode($body)
            ]);

            if ($response->getStatusCode() == 200) {
                $result = $response->json();
                if (isset($result['authToken'])) {
                    return $result['authToken'];
                }
            }

        } catch (RequestException $e) {
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
    public function registerAccount($authToken, $data)
    {
        $client = new HttpClient(['verify' => false]);
        try {

            $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'authToken' => $authToken
                    ];

            $body = [
                    'cardNumber' => (isset($data['card_number'])?str_replace(' ', '', (string)$data['card_number']):null),
                    'expDate' => (isset($data['card_expiry_month'])?(string)$data['card_expiry_month']:null) . (isset($data['card_expiry_year'])?$data['card_expiry_year']:null),
                    'chName' => (isset($data['card_holder_name'])?(string)$data['card_holder_name']:null),
                    'cvnNumber' => (isset($data['card_cvn'])?(string)$data['card_cvn']:null),
                ];

//            var_dump(json_encode($headers));
//            var_dump(json_encode($body));
//            var_dump(($this->getBaseEndPoint() . 'account/creditcards'));

            $response = $client->post( $this->getBaseEndPoint() . 'account/creditcards', [
                'timeout' => self::CONNECTION_TIMEOUT,
                'headers' => $headers,
                'body' => json_encode($body)
            ]);

            $result = null;
            if ($response->getStatusCode() == 200) {
                $result = $response->json();
                if (isset($result['accountId'])) {
                    return $result;
                }
            }

        } catch (RequestException $e) {
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

            $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'authToken' => $authToken
                    ];

//            var_dump(json_encode($headers));
//            var_dump(json_encode($body));
//            var_dump(($this->getBaseEndPoint() . 'account/disable/' . $accountId));

            $response = $client->post( $this->getBaseEndPoint() . 'account/disable/' . $accountId, [
                'timeout' => self::CONNECTION_TIMEOUT,
                'headers' => $headers,
            ]);

            $result = null;
            if ($response->getStatusCode() == 200) {
                $result = $response->json();
                if (isset($result['accountId'])) {
                    return $result;
                }
            }

        } catch (RequestException $e) {
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
    public function prepayToken($authToken, $accountId, $data)
    {
        $client = new HttpClient(['verify' => false]);
        try {

            $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'authToken' => $authToken
                    ];

            $body = [
                    'cvnNumber' => (isset($data['card_cvn'])?(string)$data['card_cvn']:null),
                ];

//            var_dump(json_encode($headers));
//            var_dump(json_encode($body));
//            var_dump(($this->getBaseEndPoint() . 'prepay/'.$accountId));

            $response = $client->post( $this->getBaseEndPoint() . 'prepay/' . $accountId, [
                'timeout' => self::CONNECTION_TIMEOUT,
                'headers' => $headers,
                'body' => json_encode($body)
            ]);

            $result = null;
            if ($response->getStatusCode() == 200) {
                $result = $response->json();
                if (isset($result['prepayToken'])) {
                    return $result['prepayToken'];
                }
            }

        } catch (RequestException $e) {
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
    public function charge($cartId, $prepayToken, $customerId, $customer, $address, $total, $currency, $country, $language, $statusURL, $successURL, $errorURL, $cancelURL)
    {
        $merchantTransactionId = $this->idGenerator();
        $client = new HttpClient(['verify' => false]);
        try {

            $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'prepayToken' => $prepayToken
                    ];

            $body = [
                    'amount' => number_format($total, 2),
                    'description' => 'Payment from ' . $this->_modelStoreManagerInterface->getStore()->getName() . '.',
                    'statusURL' => $statusURL,
                    'successURL' => $successURL,
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

                    'operationType'=> 'credit',
                    'merchantId'=> trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_merchant_id', ScopeInterface::SCOPE_STORE)),
                    'merchantPassword'=> trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_merchant_key', ScopeInterface::SCOPE_STORE)),
                    'productId'=> trim($this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_product_id', ScopeInterface::SCOPE_STORE)),
                    'country' => $country,
                    'currency' => $currency,
                    'paymentSolution'=> 'CreditCards',
                    'merchantTransactionId'=> $merchantTransactionId,
                    'language'=> strtolower($language),
                    'customerId'=> $customerId
                ];

//            var_dump(json_encode($headers));
//            var_dump(json_encode($body));
//            var_dump(($this->getBaseEndPoint(false) . 'charge'));

            $response = $client->post( $this->getBaseEndPoint(false) . 'charge', [
                'timeout' => self::CONNECTION_TIMEOUT,
                'headers' => $headers,
                'body' => json_encode($body)
            ]);

            if ($response->getStatusCode() == 200) {
                $result = $response->json();

                if (isset($result['response']) && $result['response'] != 'null') {

                    $xml = \simplexml_load_string($this->stripInvalidXml((string)$result['response']));
                    $json = json_encode($xml);
                    $data = json_decode($json, true);

                    if (!isset($data['operations']['operation']['status'])) {
                        return false;
                    }

                    if ($data['operations']['operation']['status'] == 'ERROR' || $data['operations']['operation']['status'] == 'FAIL') {
                        throw new \Exception($data['operations']['operation']['message']);
                    }

                    $redirectUrl = null;
                    if (isset($data['operations']['operation']['redirectionResponse'])) {
                        $redirectUrl = preg_replace('#^redirect?:#', $this->getCheckoutEndPoint(), $data['operations']['operation']['redirectionResponse']);
                    }

                    return [
                            'transactionResponse' => $data,
                            'transactionId' => $merchantTransactionId,
                            'status' => $data['operations']['operation']['status'],
                            'epgCustomerId' => $customerId,
                            'redirectURL' => $redirectUrl
                        ];
                }
            }

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody(), true);
                if (is_array($error)) {
                    $error = implode('<br/>', $error);
                }
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

    private function idGenerator($length = 10) {
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($length / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($length / 2));
        } else {
            return substring(md5(uniqid()), 0, $length);
        }
        return substr(bin2hex($bytes), 0, $length);
    }
}

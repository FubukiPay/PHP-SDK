<?php

namespace FubukiPay;

use FubukiPay\Exceptions\ApiException;
use FubukiPay\Exceptions\HttpException;
use InvalidArgumentException;

class Client
{
    protected $endpoint;
    protected $access_key;
    protected $secret_key;

    public function __construct(string $access_key, string $secret_key, string $endpoint = 'https://api.fubuki.us')
    {
        $this->endpoint = $endpoint;
        $this->access_key = $access_key;
        $this->secret_key = $secret_key;
    }

    /**
     * Create a Invoice.
     * @param string $base_currency USD / CNY
     * @param float $amount 
     * @param int $fees_payer 1/Merchant, 2/Customer
     * @param string|null $merchant_tradeno Merchant Order Identity
     * @param string|null $title Invoice Title
     * @param string|null $notify_url URL for payment success notify
     * @param string|null $return_url URL that customer will redirect to after paid or canceled
     * @return object
     */
    public function createInvoice(string $base_currency, float $amount, int $fees_payer, string $merchant_tradeno = null, string $title = null, string $notify_url = null, string $return_url = null)
    {
        if (!in_array($base_currency, ['USD', 'CNY'])) {
            throw new InvalidArgumentException('Only USD or CNY is supported');
        }

        if ($amount < 0.01) {
            throw new InvalidArgumentException('Invalid Amount');
        }

        if (!in_array($fees_payer, [1, 2])) {
            throw new InvalidArgumentException('Invalid Fees Payer');
        }

        $body = [
            'base_currency' => $base_currency,
            'amount' => $amount,
            'fees_payer' => $fees_payer
        ];

        if (!empty($merchant_tradeno)) {
            $body['merchant_tradeno'] = $merchant_tradeno;
        }

        if (!empty($title)) {
            $body['title'] = $title;
        }

        if (!empty($notify_url)) {
            $body['notify_url'] = $notify_url;
        }

        if (!empty($return_url)) {
            $body['return_url'] = $return_url;
        }

        $s = $this->sign('/v1/invoice/create');

        $rsp = $this->post(
            $s['url'],
            [
                'X-Access-Key: ' . $this->access_key,
                'X-Signature: ' . $s['sign']
            ],
            $body
        );

        $result = json_decode($rsp);
        if (!isset($result->code)) {
            throw new ApiException('Unexpected response from API: ' . $rsp);
        }

        if ($result->code !== 0) {
            throw new ApiException($result->msg);
        }

        return $result->data;
    }

    protected function sign($uri)
    {
        $nonce = md5(random_bytes(16));
        $ts = time();
        $uri = "$uri?ts=$ts&nonce=$nonce";
        $full_url = $this->endpoint . $uri;
        $signature = hash_hmac('SHA3-384', $uri, $this->secret_key);
        return [
            'url' => $full_url,
            'sign' => $signature
        ];
    }

    protected function post($url, array $headers = [], array $data = [])
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERAGENT => 'FubukiPay-PHP-SDK/1.0.0',
            CURLOPT_TIMEOUT => 20
        ));
        $rsp = curl_exec($ch);
        if (empty($rsp)) {
            throw new HttpException('cURL Error: ' . curl_error($ch));
        }

        return $rsp;
    }
}

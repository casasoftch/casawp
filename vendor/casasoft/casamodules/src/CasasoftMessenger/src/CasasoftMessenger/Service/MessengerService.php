<?php
namespace CasasoftMessenger\Service;

use Laminas\Http\Client as HttpClient;
use Laminas\Json\Json;

class MessengerService
{
    protected $config = [
        'username' => '',
        'password' => '',
        'url' => 'http://undefined-domain.ch',
        'publisher' => 'undefined'
    ];

    protected $translator;
    protected $htmlPurifier;

    public function __construct($translator, $htmlPurifier)
    {
        $this->translator = $translator;
        $this->htmlPurifier = $htmlPurifier;
    }

    public function setConfig($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    public function wpautop($pee, $br = true)
    {
        // Existing wpautop function code here...
    }

    public function sanitizeHtml($html)
    {
        if (substr_count($html, '<p>') === 1) {
            $html = str_replace(['<p>', '</p>'], '', $html);
        }

        if ($this->htmlPurifier) {
            $html = $this->htmlPurifier->purify($html);
        }

        if (class_exists('Michelf\Markdown')) {
            $html = \Michelf\Markdown::defaultTransform($html);
        }

        return $this->wpautop($html, true);
    }

    public function sendMessage($postdata)
    {
        $defaults = [
            'publisher' => $this->config['publisher'],
            'provider' => '',
            'message' => '',
            'message_plain' => '',
            'lang' => '',
            'property_reference' => '',
            'salutation_code' => '',
            'firstname' => '',
            'lastname' => '',
            'legal_name' => '',
            'street' => '',
            'postal_code' => '',
            'locality' => '',
            'phone' => '',
            'mobile' => '',
            'fax' => '',
            'email' => '',
        ];

        $postdata = array_merge($defaults, $postdata);

        $postdata['publisher'] = $this->config['publisher'];
        if ($postdata['message'] && !$postdata['message_plain']) {
            $postdata['message'] = $this->sanitizeHtml($postdata['message']);
            $postdata['message_plain'] = strip_tags($postdata['message']);
        } elseif (!$postdata['message'] && $postdata['message_plain']) {
            $postdata['message_plain'] = strip_tags($postdata['message_plain']);
            $postdata['message'] = $this->sanitizeHtml($postdata['message_plain']);
        } elseif ($postdata['message'] && $postdata['message_plain']) {
            $postdata['message_plain'] = strip_tags($postdata['message_plain']);
            $postdata['message'] = $this->sanitizeHtml($postdata['message_plain']);
        }

        $config = [
            'adapter' => 'Laminas\Http\Client\Adapter\Curl',
            'curloptions' => [
                CURLOPT_FRESH_CONNECT => true,
            ],
        ];

        $uri = $this->config['url'] . '/msg?' . http_build_query([]);
        $client = new HttpClient($uri, $config);
        $client->setHeaders([
            'Accept' => 'application/json; charset=UTF-8',
            'Content-Type' => 'application/json'
        ]);

        $client->setMethod('POST');
        $client->setRawBody(Json::encode($postdata));
        $client->setEncType(HttpClient::ENC_FORMDATA);
        $client->setAuth($this->config['username'], $this->config['password'], HttpClient::AUTH_BASIC);

        $response = $client->send();
        
        return $response->getContent();
    }
}

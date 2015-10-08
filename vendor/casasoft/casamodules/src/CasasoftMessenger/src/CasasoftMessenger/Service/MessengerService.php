<?php
namespace CasasoftMessenger\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Zend\Http\Client as HttpClient;
use Zend\Json\Json;

class MessengerService implements FactoryInterface {

	protected $config = array(
        'username' => '',
        'password' => '',
        'url' => 'http://undefined-domain.ch',
        'publisher' => 'undefined'
    );


    public function __construct($translator, $htmlPurifier){
        $this->translator = $translator;
        $this->htmlPurifier = $htmlPurifier;
    }

    public function setConfig($config){
        $this->config = array_merge($this->config, $config);
    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

     public function wpautop($pee, $br = true) {
        $pre_tags = array();

        if ( trim($pee) === '' )
            return '';

        $pee = $pee . "\n"; // just to make things a little easier, pad the end

        if ( strpos($pee, '<pre') !== false ) {
            $pee_parts = explode( '</pre>', $pee );
            $last_pee = array_pop($pee_parts);
            $pee = '';
            $i = 0;

            foreach ( $pee_parts as $pee_part ) {
                $start = strpos($pee_part, '<pre');

                // Malformed html?
                if ( $start === false ) {
                    $pee .= $pee_part;
                    continue;
                }

                $name = "<pre wp-pre-tag-$i></pre>";
                $pre_tags[$name] = substr( $pee_part, $start ) . '</pre>';

                $pee .= substr( $pee_part, 0, $start ) . $name;
                $i++;
            }

            $pee .= $last_pee;
        }

        $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
        // Space things out a little
        $allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|noscript|legend|section|article|aside|hgroup|header|footer|nav|figure|details|menu|summary)';
        $pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
        $pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
        $pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines

        if ( strpos( $pee, '</object>' ) !== false ) {
            // no P/BR around param and embed
            $pee = preg_replace( '|(<object[^>]*>)\s*|', '$1', $pee );
            $pee = preg_replace( '|\s*</object>|', '</object>', $pee );
            $pee = preg_replace( '%\s*(</?(?:param|embed)[^>]*>)\s*%', '$1', $pee );
        }

        if ( strpos( $pee, '<source' ) !== false || strpos( $pee, '<track' ) !== false ) {
            // no P/BR around source and track
            $pee = preg_replace( '%([<\[](?:audio|video)[^>\]]*[>\]])\s*%', '$1', $pee );
            $pee = preg_replace( '%\s*([<\[]/(?:audio|video)[>\]])%', '$1', $pee );
            $pee = preg_replace( '%\s*(<(?:source|track)[^>]*>)\s*%', '$1', $pee );
        }

        $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
        // make paragraphs, including one at the end
        $pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
        $pee = '';

        foreach ( $pees as $tinkle ) {
            $pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
        }

        $pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
        $pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);
        $pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
        $pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
        $pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
        $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
        $pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
        $pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);

        if ( $br ) {
            /*$pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', '_autop_newline_preservation_helper', $pee);*/
            $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
            $pee = str_replace('<WPPreserveNewline />', "\n", $pee);
        }

        $pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
        $pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
        $pee = preg_replace( "|\n</p>$|", '</p>', $pee );

        if ( !empty($pre_tags) )
            $pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);

        return $pee;
    }

    public function sanitizeHtml($html){
        //remove wrapping p (markdown doesn't like it)
        $wrapped_p = substr_count($html, '<p>');
        if ($wrapped_p === 1) {
            $html = str_replace(array('<p>','</p>'), '', $html);
        }
        //make sure its proper html
        if ($this->htmlPurifier) {
            $html = $this->htmlPurifier->purify($html);
        }
        //convert markdown to html
        if (class_exists('Michelf\Markdown')){
            $html = \Michelf\Markdown::defaultTransform($html);
        }
        //convert double breaks to p etc.
        $html = $this->wpautop( $html, true );
        
        return $html;
    }

    public function sendMessage($postdata){
        $defaults = array(
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
        );

        $postdata = array_merge($defaults, $postdata);

    	$postdata['publisher'] = $this->config['publisher'];
    	if ($postdata['message'] && !$postdata['message_plain']) {
    		$postdata['message'] = $this->sanitizeHtml($postdata['message']);
    		$postdata['message_plain'] = strip_tags($postdata['message']);
    	}
    	if (!$postdata['message'] && $postdata['message_plain']) {
    		$postdata['message_plain'] = strip_tags($postdata['message_plain']);
    		$postdata['message'] = $this->sanitizeHtml($postdata['message_plain']);
    	}
    	if ($postdata['message'] && $postdata['message_plain']) {
    		$postdata['message_plain'] = strip_tags($postdata['message_plain']);
    		$postdata['message'] = $this->sanitizeHtml($postdata['message_plain']);
    	}
    	
    	

    	$config = array(
            'adapter'   => 'Zend\Http\Client\Adapter\Curl',
            'curloptions' => array(
                CURLOPT_FRESH_CONNECT => true,
                //CURLOPT_TIMEOUT_MS => 1
            ),
        );
        $query = array();
        $uri = $this->config['url'] . '/msg?' . http_build_query($query);
        $client = new HttpClient($uri, $config);
        $client->setHeaders(array(
            'Accept' => 'application/json; charset=UTF-8',
            'Content-Type' => 'application/json'
        ));
        
        $client->setMethod('POST');
        
        

        $client->setRawBody(Json::encode($postdata));
        $client->setEncType(HttpClient::ENC_FORMDATA);
        $client->setAuth($this->config['username'], $this->config['password'], \Zend\Http\Client::AUTH_BASIC);

        $response = $client->send();    
		
		return $response->getContent();
    }
}
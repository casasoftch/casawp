<?php
namespace CasasoftEmail\Service;

use Zend\View\Model\ViewModel;

use Zend\Mail\Message as Message;
use Zend\Mail\Transport\Sendmail as SendmailTransport;
use Zend\Mail\Transport\SmtpOptions as SmtpOptions;
use Zend\Mail\Transport\Smtp as SmtpTransport;

use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;
use Zend\Mime\Mime;

class EmailService implements EmailServiceInterface
{
    protected $translator;
    protected $viewRender;
    protected $defaultTemplate = 'message';
    protected $defaultEmailOptions = array();
    protected $defaultTemplateVariables = array();
    protected $defaultLayoutVariables = array();
    protected $encoding = 'UTF-8';

    protected $config = array(
        'debug' => false,
        'send' => true,
        'subject' => 'No Subject Defined',
        'from' => 'robot@domain.com',
        'from_name' => 'robot',
        'replyto' => 'robot@domain.com',
        'to' => 'recipient@domain.com',
        'cc' => '',
        'bcc' => '',
        'message' => 'No Message Defined',
        'domain' => 'domain.com',
        'title' => 'Email Service Title',
        'mandrill' => [
            'from_email' => '',
            'from_name' => '',
            'tags' => []
        ]
    );

    public function __construct($translator, $viewRender, $resolver, $casasoftMailTemplate){
        $this->translator = $translator;
        $this->viewRender = $viewRender;
        $this->resolver = $resolver;
        $this->casasoftMailTemplate = $casasoftMailTemplate;

        //$this->config['domain'] = $_SERVER['HTTP_HOST'];
    }

    public function setEncoding($encoding){
        $this->encoding = $encoding;
    }

    public function setConfig($config){
        $this->config = array_merge($this->config, $config);
    }

    protected $templateGroup = 'default';
    public function getTemplateGroup(){return $this->templateGroup;}
    public function setTemplateGroup($templateGroup){$this->templateGroup = $templateGroup;}

    protected $html = true;
    public function getHtml(){return $this->html;}
    public function setHtml($html){$this->html = $html;}

    public function setDefaults($template = 'message', $emailOptions = array()){
        if ($template) {
            $this->defaultTemplate = $template;
        }
        $this->config = array_merge($this->config, $emailOptions);
    }

    public function renderEmail($template = 'message', $emailOptions = array()){
        $emailOptions = array_merge($this->defaultEmailOptions, $emailOptions);
        if ($this->html && $this->casasoftMailTemplate && ($this->templateGroup == 'icasa' || $this->templateGroup == 'homestreet') && isset($emailOptions['msg'])) {
            //$casaMailTemplate  = $this->getServiceLocator()->get('CasasoftMailTemplate');

            if ($emailOptions['msg']->getLang() == 'de') {
                $this->translator->setLocale('de_CH');
            }
            if ($emailOptions['msg']->getLang() == 'en') {
                $this->translator->setLocale('en_US');
            }
            if ($emailOptions['msg']->getLang() == 'fr') {
                $this->translator->setLocale('fr_CH');
            }
            if ($emailOptions['msg']->getLang() == 'it') {
                $this->translator->setLocale('it_CH');
            }

            // $service_name = 'SBB Immobilien';
         //    $service_website = 'sbb-immobilienprojekte.ch';
         //    $service_logo = 'https://sbb-immobilienprojekte.ch/images/SBB_POS_2F_RGB_100.svg';
         //    if (isset($posteddata['from_service'])) {
         //      switch ($posteddata['from_service']) {
         //        case 'icasa':
         //          $service_name = 'iCasa.ch';
         //          $service_website = 'icasa.ch';
         //          $service_logo = 'https://icasa.ch/img/logo.svg';
         //          break;
         //      }
         //    }

            $person = [];
            if ($emailOptions['msg']->getGender()) {
                $gender = $emailOptions['msg']->getGender();
                if($gender === 1) {
                    $honorificPrefix = 'Mr';
                } elseif($gender === 2) {
                    $honorificPrefix = 'Mrs';
                } else {
                    $honorificPrefix = 'not specified';
                }

                $person[] = ["key" => 'honorificPrefix', "value" => $honorificPrefix];
            }
            if ($emailOptions['msg']->getFirstname()) {
                $person[] = ["key" => 'First name', "value" => $emailOptions['msg']->getFirstname()];
            }
            if ($emailOptions['msg']->getLastname()){
                $person[] = ["key" => 'Last name', "value" => $emailOptions['msg']->getLastname()];
            }
            if ($emailOptions['msg']->getLegal_name()){
                $person[] = ["key" => 'Company', "value" => $emailOptions['msg']->getLegal_name()];
            }
            if ($emailOptions['msg']->getStreet()){
                $person[] = ["key" => 'Street', "value" => $emailOptions['msg']->getStreet()];
            }
            if ($emailOptions['msg']->getLocality()){
                if($emailOptions['msg']->getPostal_code()){
                    $person[] = ["key" => 'City', "value" => $emailOptions['msg']->getPostal_code().' '.$emailOptions['msg']->getLocality()];
                } else {
                    $person[] = ["key" => 'City', "value" => $emailOptions['msg']->getLocality()];
                }
            }
            if ($emailOptions['msg']->getPhone()){
                $person[] = ["key" => 'Phone', "value" => $emailOptions['msg']->getPhone()];
            }
            if ($emailOptions['msg']->getEmail()){
                $person[] = ["key" => 'Email', "value" => $emailOptions['msg']->getEmail()];
            }

            $property = [
                'propertyOptions' => [],
                'objectReference' => null
            ];
            if ($emailOptions['msg']->getProperty_reference()) {
                $property['objectReference'] = ["text" => 'Object-Ref.', "value" => $emailOptions['msg']->getProperty_reference()];
            }
            if ($emailOptions['msg']->getProject_reference()) {
                $property['objectReference'] = ["text" => 'Project-Ref.', "value" => $emailOptions['msg']->getProject_reference()];
            }
            if ($emailOptions['msg']->getProperty_street()) {
                $property['propertyOptions'][] = ["optionDescription" => 'Street', "optionValue" => $emailOptions['msg']->getProperty_street()];
            }
            if (trim($emailOptions['msg']->getProperty_postal_code().$emailOptions['msg']->getProperty_locality())) {
                $property['propertyOptions'][] = ["optionDescription" => 'City', "optionValue" => trim($emailOptions['msg']->getProperty_postal_code().' '.$emailOptions['msg']->getProperty_locality())];
            }
            if ($emailOptions['msg']->getProperty_type()) {
                $property['propertyOptions'][] = ["optionDescription" => 'Sales Type', "optionValue" => $emailOptions['msg']->getProperty_type()];
            }
            if ($emailOptions['msg']->getProperty_category()) {
                $property['propertyOptions'][] = ["optionDescription" => 'Category', "optionValue" => $emailOptions['msg']->getProperty_category()];
            }
            if ($emailOptions['msg']->getProperty_country()) {
                $property['propertyOptions'][] = ["optionDescription" => 'Country', "optionValue" => $emailOptions['msg']->getProperty_country()];
            }
            if ($emailOptions['msg']->getProperty_rooms()) {
                $property['propertyOptions'][] = ["optionDescription" => 'Rooms', "optionValue" => $emailOptions['msg']->getProperty_rooms()];
            }
            if ($emailOptions['msg']->getProperty_price()) {
                $property['propertyOptions'][] = ["optionDescription" => 'Price', "optionValue" => $emailOptions['msg']->getProperty_price()];
            }
            if ($emailOptions['msg']->getBacklink()) {
                $property['propertyOptions'][] = ["optionDescription" => 'Link', "optionValue" => 'to website', "optionLink" => true, "optionUrl" => $emailOptions['msg']->getBacklink()];
            }

            // $property['image'] = [
            //     'src' => 'https://casamail.com/img/property-placeholder.jpg',
            //     'link' => $emailOptions['msg']->getBacklink(),
            //     'alt' => 'Objektbild'
            // ];

            $extra_data = [];
            $searchProfile = [];
            if ($emailOptions['msg']->getExtra_data()) {
                $extra_data_arr = json_decode($emailOptions['msg']->getExtra_data());
                if ($extra_data_arr) {
                    foreach ($extra_data_arr as $key => $value) {
                        if (!in_array($key, ['searchProfile'])) {
                            $extra_data[] = ["key" => $key, "value" => $value];
                        }
                        if ($key == 'searchProfile' && is_array($value)) {
                            foreach ($value as $spkey => $spvalue) {
                                $searchProfile[] = ["key" => $spkey, "value" => $spvalue];
                            }
                        }
                    }
                }
            }

            if ($this->templateGroup == 'icasa') {
                $data = [
                    "logo" => "https://icasa.ch/img/logo.jpg",
                    "message" => [
                        'header' => 'Message',
                        'txt' => $emailOptions['msg']->getMessage_plain(),
                    ]
                ];
            } elseif ($this->templateGroup == 'homestreet') {
                $data = [
                    "logo" => "https://homestreet.ch/images/logo_de_CH.png",
                    "message" => [
                        'header' => 'Message',
                        'txt' => $emailOptions['msg']->getMessage_plain(),
                    ]
                ];
            }


            if ($person) {
                $data['person'] = [
                    'header' => 'Requestor',
                    'data' => $person
                ];
            }
            if ($property['propertyOptions'] || $property['objectReference']) {
                $data['property'] = [
                    'header' => 'Immobilie',
                    'objectReference' => $property['objectReference'],
                    'propertyOptions' => $property['propertyOptions']
                ];
            }
            if ($searchProfile) {
                $data['searchProfile'] = [
                    'header' => 'Search profile',
                    'data' => $searchProfile
                ];
            }
            if ($extra_data) {
                $data['extraData'] = [
                    'header' => 'More information',
                    'data' => $extra_data
                ];
            }


            $content = $this->casasoftMailTemplate->renderTemplate('request', $data);
        } else {
            $layout = new ViewModel($emailOptions);
            if ($this->html) {

                if ($this->resolver->resolve("email/".$this->templateGroup."/layout")) {
                    $layout->setTemplate("email/".$this->templateGroup."/layout");
                } elseif ($this->resolver->resolve("email/default/layout")) {
                    $layout->setTemplate("email/default/layout");
                } else {
                    throw new \Exception("neither " . "email/".$this->templateGroup."/layout" . ' or ' . "email/default/layout" . ' is available', 1);
                }
            } else {
                if ($this->resolver->resolve("email/".$this->templateGroup."/layout_plain")) {
                    $layout->setTemplate("email/".$this->templateGroup."/layout_plain");
                } elseif ($this->resolver->resolve("email/default/layout_plain")) {
                    $layout->setTemplate("email/default/layout_plain");
                } else {
                    throw new \Exception("neither " . "email/".$this->templateGroup."/layout_plain" . ' or ' . "email/default/layout_plain" . ' is available', 1);
                }
            }

            $contentView = null;
            if ($this->resolver->resolve("email/".$this->templateGroup . "/" . $template)) {
                $contentView = $this->viewRender->render("email/".$this->templateGroup . "/" . $template, $emailOptions);
            } elseif ($this->resolver->resolve("email/default/".$template)) {
                $contentView = $this->viewRender->render("email/default/" . $template, $emailOptions);
            } else {
                throw new \Exception("neither " . "email/".$this->templateGroup."/".$template . ' or ' . "email/default/".$template . ' is available', 1);
            }

            $layout->setVariable("content", $contentView);

            $content = $this->viewRender->render($layout);
            //replace domain with url
            $content = str_replace('{{domain}}', 'http://'.$emailOptions['domain'], $content);
        }

        return $content;
    }

    public function sendMandrill($template = 'message', $emailOptions = array(), $content = null){
        try {
            $mandrill = new \Mandrill($emailOptions['mandrill']['key']);
            $message = array(
                'subject' => $emailOptions['subject'],
                //'from_email' => $emailOptions['from'],
                //'from_email' => $this->config['from'],
                'from_email' => $emailOptions['mandrill']['from_email'],
                'from_name' => $emailOptions['mandrill']['from_name'],
                'headers' => [],
                'important' => false,
                'track_opens' => true,
                'track_clicks' => true,
                'inline_css' => true,
                'tags' => ($emailOptions['mandrill']['tags'] ? $emailOptions['mandrill']['tags'] : []),
                //'metadata' => array('website' => $service_website),
            );
            if ($this->html) {
                $message['html'] = $content;
                $message['auto_text'] = true;
            } else {
                $message['text'] = $content;
            }
            if ($emailOptions['bcc']) {
                $message['bcc_address'] = $emailOptions['bcc'];
            }
            // if ($emailOptions['cc']) {
            //     $message->addCc($emailOptions['cc']);
            // }
            if ($emailOptions['replyto']) {
                $message['headers']['Reply-To'] = $emailOptions['replyto'];
            }
            $message['to'] = array(
                array(
                    'email' => $emailOptions['to'],
                    'name' => $emailOptions['to'],
                    'type' => 'to'
                )
            );
            $async = false;
            $ip_pool = 'Main Pool';
            $now = new \DateTime('Now');
            //$send_at = $now->format('c');
            $send_at = null;
            $mandrill_result = $mandrill->messages->send($message, $async, $ip_pool, $send_at);


            $emailOptionsSave = $emailOptions;
            unset($emailOptionsSave['msg']);

            switch ($mandrill_result[0]['status']) {
                case 'sent':
                case 'scheduled':
                    return 'mandrill:'.$mandrill_result[0]['status'];
                    break;
                case 'queued':
                case 'rejected':
                case 'invalid':
                    echo print_r($mandrill_result, true). "\n";
//                    $this->sendEmail('error', array(
//                      'to' => 'js@casasoft.ch',
//                      'from' => 'alert@cassaoft.com',
//                      'subject' => 'Mandrill Fehler',
//                      'error' => print_r(array_merge($emailOptionsSave, $mandrill_result), true),
//                      'domain' => 'casamail.local'
//                    ));
                    return 'mandrill:'.$mandrill_result[0]['status'];

                    break;

                default:
                    return 'mandrill:?'.$mandrill_result[0]['status'];
                    break;
            }


            //print_r($mandrill_result);
            //print_r($message);
        } catch (\Exception $e) {
            return $this->sendSMTP($template, $emailOptions, $content);
        }

    }

    public function sendSMTP($template = 'message', $emailOptions = array(), $content = null){
        $attachments = (isset($emailOptions['attachments']) && $emailOptions['attachments'] && is_array($emailOptions['attachments']) ? $emailOptions['attachments'] : array() );

        $message = new Message();

        $message->addTo($emailOptions['to']);
        $message->addFrom($emailOptions['from']);
        $message->setSubject($emailOptions['subject']);
        if ($emailOptions['bcc']) {
            $message->addBcc($emailOptions['bcc']);
        }
        if ($emailOptions['cc']) {
            $message->addCc($emailOptions['cc']);
        }
        if ($emailOptions['replyto']) {
            $message->addReplyTo($emailOptions['replyto']);
        }


        if ($this->encoding == 'iso-8859-1') {
            $content = mb_convert_encoding($content, 'iso-8859-1', 'UTF-8');
        }

        if ($this->html) {
            // HTML part iso-8859-1
            $htmlPart           = new MimePart($content);
            $htmlPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
            $htmlPart->type     = "text/html; charset=".$this->encoding;
        }


        // Plain text part
        $textPart           = new MimePart(strip_tags($content));
        $textPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $textPart->type     = "text/plain; charset=".$this->encoding;

        //mb_convert_encoding($string, 'ISO-2022-JP', 'UTF-8')

        $body = new MimeMessage();
        if ($attachments) {
            // With attachments, we need a multipart/related email. First part
            // is itself a multipart/alternative message
            $content = new MimeMessage();
            $content->addPart($textPart);
            if ($this->html) {
                $content->addPart($htmlPart);
            }

            $contentPart = new MimePart($content->generateMessage());
            $contentPart->type = "multipart/alternative;\n boundary=\"" .
                $content->getMime()->boundary() . '"';

            $body->addPart($contentPart);
            $messageType = 'multipart/related';

            // Add each attachment
            foreach ($attachments as $thisAttachment) {
                $attachment = new MimePart($thisAttachment['buffer']);
                $attachment->filename    = $thisAttachment['filename'];
                $attachment->type        = Mime::TYPE_OCTETSTREAM;
                $attachment->encoding    = Mime::ENCODING_BASE64;
                $attachment->disposition = Mime::DISPOSITION_ATTACHMENT;

                $body->addPart($attachment);
            }

        } else {
            // No attachments, just add the two textual parts to the body
            if ($this->html) {
                $body->setParts(array($textPart, $htmlPart));
                $messageType = 'multipart/alternative';
            } else {
                $body->setParts(array($textPart));
                $messageType = 'text/plain';
            }

        }

        // attach the body to the message and set the content-type
        $message->setBody($body);
        $message->getHeaders()->get('content-type')->setType($messageType);
        $message->setEncoding($this->encoding);

        if ($emailOptions['send']) {
            if (isset($emailOptions['smtp']) && $emailOptions['smtp'] == 'google') {
              $transport = new SmtpTransport();
              $options   = new SmtpOptions(array(
                  'name'              => 'casamail.com',
                  'host'              => 'smtp.gmail.com',
                  'port' => 465,
                  'connection_class'  => 'login',
                  'connection_config' => array(
                    'username' => $emailOptions['smtp_username'],
                    'password' => $emailOptions['smtp_password'],
                    'ssl'=> 'ssl',
                  ),
              ));
              $transport->setOptions($options);
            } else {
              $transport = new SendmailTransport();
            }
            try {
                $transport->send($message);
            } catch (\Exception $e) {
              if (!get_class($transport) == 'Sendmail') {
                //try with postfix
                $transport = new SendmailTransport();
                $transport->send($message);
              }
            }
        } else {
            echo '<h1>E-Mail <strong>NOT</strong> sent</h1>';
        }

        return 'smtp:?';
    }

    public function sendEmail($template = 'message', $emailOptions = array(), $content = null){

        $mandrillOptions = [];
        if (isset($emailOptions['mandrill']) && $emailOptions['mandrill']) {
            $mandrillOptions = array_merge($this->config['mandrill'], $emailOptions['mandrill']);
        } else {
            $mandrillOptions = $this->config['mandrill'];
        }

        $mandrillTags = [];
        if (isset($this->config['mandrill']['tags']) && isset($emailOptions['mandrill']['tags']) && $emailOptions['mandrill']['tags']) {
            $mandrillTags = array_merge($this->config['mandrill']['tags'], $emailOptions['mandrill']['tags']);
        }
        elseif(isset($this->config['mandrill']['tags']) && !isset($emailOptions['mandrill']['tags'])){
            $mandrillTags = $this->config['mandrill']['tags'];
        }
        elseif(!isset($this->config['mandrill']['tags']) && isset($emailOptions['mandrill']['tags'])){
            $mandrillTags = $emailOptions['mandrill']['tags'];
        }


        $mandrillOptions['tags'] = $mandrillTags;

        $emailOptions = array_merge($this->config, $emailOptions);
        $emailOptions['mandrill'] = $mandrillOptions;

        if (!$content) {
            $content = $this->renderEmail($template, $emailOptions);
        }


        if ($emailOptions['debug']) {
            $displays = array();
            foreach ($emailOptions as $key => $value) {
                if ($key  !== 'message' && $key != 'attachments') {
                    if (!is_object($value)) {
                        $displays[] = $key . ': <strong>' . (is_array($value) ? implode(',', $value) : $value) . '</strong>';
                    } else {
                        $displays[] = $key . ': <strong>OBJ</strong>';
                    }


                }
            }
            echo '<div style="
                        background-color: #444; 
                        padding: 50px;
                        box-shadow: 1px 1px 10px rgba(0,0,0,0.8) inset;
                ">
                    <div style="
                                width:80%; 
                                margin:10px auto; 
                                background-color:#ffffff; 
                                box-shadow:1px 2px 5px rgba(0,0,0,0.5);
                                padding: 15px;
                    " >
                        '.implode(' ; ', $displays).'
                    </div>
                    <div style="
                                width:80%; 
                                margin:10px auto; 
                                background-color:#ffffff; 
                                box-shadow:1px 2px 5px rgba(0,0,0,0.5);
                    " >'
                        .$content
                    .'</div>'
                    .(isset($emailOptions['attachments']) && $emailOptions['attachments'] ? '<div style="
                                width:80%; 
                                margin:10px auto; 
                                background-color:#ffffff; 
                                box-shadow:1px 2px 5px rgba(0,0,0,0.5);
                                padding: 15px;
                    " >Mit Anhang</div>' : '')
                .'</div>';


        }
        if (isset($emailOptions['mandrill']) && $this->encoding == 'UTF-8') {
            return $this->sendMandrill($template, $emailOptions, $content);
        } else {
            return $this->sendSMTP($template, $emailOptions, $content);
        }



        //return $content;
        return true;

    }

}

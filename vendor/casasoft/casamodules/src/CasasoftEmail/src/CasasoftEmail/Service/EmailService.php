<?php
namespace CasasoftEmail\Service;


use Zend\View\Model\ViewModel;

use Zend\Mail\Message as Message;
use Zend\Mail\Transport\Sendmail as SendmailTransport;

use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;
use Zend\Mime\Mime;
 
class EmailService {
    protected $translator;
    protected $viewRender;
    protected $defaultTemplate = 'message';
    protected $defaultEmailOptions = array();
    protected $defaultTemplateVariables = array();
    protected $defaultLayoutVariables = array();


    protected $config = array(
        'debug' => false,
        'send' => true,
        'subject' => 'No Subject Defined',
        'from' => 'robot@domain.com',
        'replyto' => 'robot@domain.com',
        'to' => 'recipient@domain.com',
        'cc' => '',
        'bcc' => '',
        'message' => 'No Message Defined',
        'domain' => 'domain.com',
        'title' => 'Email Service Title'
    );

    public function __construct($translator, $viewRender, $resolver){
        $this->translator = $translator;
        $this->viewRender = $viewRender;
        $this->resolver = $resolver;

        $this->config['domain'] = $_SERVER['HTTP_HOST'];
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

        return $content;
    }

    public function sendEmail($template = 'message', $emailOptions = array()){
        $emailOptions = array_merge($this->config, $emailOptions);

        $content = $this->renderEmail($template, $emailOptions);

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

        if ($this->html) {
            // HTML part
            $htmlPart           = new MimePart($content);
            $htmlPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
            $htmlPart->type     = "text/html; charset=UTF-8";    
        }
        

        // Plain text part
        $textPart           = new MimePart(strip_tags($content));
        $textPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $textPart->type     = "text/plain; charset=UTF-8";

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
        $message->setEncoding('UTF-8');

        $transport = new SendmailTransport();

        if ($emailOptions['send']) {
            try {
                $transport->send($message);
            } catch (\Exception $e) {
                die($e->getMessage());
                die('failed_to_send');
            }
        } else {
            echo '<h1>E-Mail <strong>NOT</strong> sent</h1>';
        }

        return $content;

    }

}
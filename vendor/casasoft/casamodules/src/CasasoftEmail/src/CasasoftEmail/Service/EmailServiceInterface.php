<?php

namespace CasasoftEmail\Service;

interface EmailServiceInterface
{
    public function setEncoding($encoding);

    public function setConfig($config);

    public function getTemplateGroup();

    public function setTemplateGroup($templateGroup);

    public function getHtml();

    public function setHtml($html);

    public function setDefaults($template = 'message', $emailOptions = array());

    public function renderEmail($template = 'message', $emailOptions = array());

    public function sendMandrill($template = 'message', $emailOptions = array(), $content = null);

    public function sendSMTP($template = 'message', $emailOptions = array(), $content = null);

    public function sendEmail($template = 'message', $emailOptions = array(), $content = null);
}

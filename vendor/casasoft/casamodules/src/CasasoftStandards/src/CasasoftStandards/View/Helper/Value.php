<?php
namespace CasasoftStandards\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\View\Model\ViewModel;

class Value extends AbstractHelper{
    public $conversionService = false;
    function __construct($conversionService){
       $this->conversionService = $conversionService;
    }

    public function __invoke($label, $context = 'smart', $rendered = true){
      if ($rendered) {
        return $this->conversionService()->getRenderedValue($label, $context);
      } else {
        return $this->conversionService()->getValue($label, $context);
      }

    }
}

<?php
namespace CasasoftStandards\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Model\ViewModel;

class Label extends AbstractHelper{
    public $conversionService = false;
    function __construct($conversionService){
       $this->conversionService = $conversionService;
    }

    public function __invoke($label, $context = 'smart'){
        return $this->conversionService->getLabel($label, $context);
    }
}

<?php
namespace CasasoftStandards\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\View\Model\ViewModel;

class GetList extends AbstractHelper{
    public $conversionService = false;
    function __construct($conversionService){
       $this->conversionService = $conversionService;
    }

    public function __invoke($template, $filtered = true){
        return $this->conversionService->getList($template, $filtered);
    }
}

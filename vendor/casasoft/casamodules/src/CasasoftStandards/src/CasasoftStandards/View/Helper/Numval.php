<?php
namespace CasasoftStandards\View\Helper;
 
use Zend\View\Helper\AbstractHelper;
use Zend\View\Model\ViewModel;

class Numval extends AbstractHelper{ 
    public $numvalService = false;
    public function __construct($numvalService){
       $this->numvalService = $numvalService;
    }

    public function __invoke($input, $seek = 'label', $options = array()){
        $item = false;
        try {
            $item = $this->numvalService->getItem($input);
        } catch (\Exception $e) {
            return '';
        }
        switch ($seek) {
            case 'label':
                return $item->getLabel();
                break;
            case 'value':
                return $item->getKey();
                break;
            case 'icon':
                return $item->getIcon();
                break;
            case 'si':
                return $item->getSi();
                break;
        }
        return '';
    }
}
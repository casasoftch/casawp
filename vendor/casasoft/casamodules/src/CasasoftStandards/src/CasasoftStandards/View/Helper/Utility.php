<?php
namespace CasasoftStandards\View\Helper;
 
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Model\ViewModel;

class Utility extends AbstractHelper{ 
    public $translator = false;
    public $keys = array();
    public function __construct($utilityService){
        $this->utilityService = $utilityService;
    }

    public function __invoke($key, $seek = 'label', $options = array()){
        $item = false;
        try {
            $item = $this->utilityService->getItem($key);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        switch ($seek) {
            case 'label':
                return $item->getLabel();
                break;
            case 'icon':
                return $item->getIcon();
                break;
        }
        return '';
    }
}
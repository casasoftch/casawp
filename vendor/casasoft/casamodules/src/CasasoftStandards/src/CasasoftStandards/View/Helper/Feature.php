<?php
namespace CasasoftStandards\View\Helper;
 
use Zend\View\Helper\AbstractHelper;
use Zend\View\Model\ViewModel;

class Feature extends AbstractHelper{ 
    public $translator = false;
    public $keys = array();
    public function __construct($featureService){
        $this->featureService = $featureService;
    }

    public function __invoke($key, $seek = 'label', $options = array()){
        $item = false;
        try {
            $item = $this->featureService->getItem($key);
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
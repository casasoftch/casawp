<?php
namespace CasasoftStandards\View\Helper;
 
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Model\ViewModel;

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
        if ($item) {
            switch ($seek) {
                case 'label':
                    return $item->getLabel();
                    break;
                case 'icon':
                    return $item->getIcon();
                    break;
            }
        }
        return '';
    }
}
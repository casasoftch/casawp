<?php
namespace CasasoftStandards\View\Helper;
 
use Zend\View\Helper\AbstractHelper;
use Zend\View\Model\ViewModel;

class Category extends AbstractHelper{ 
    public $translator = false;
    public $keys = array();
    public function __construct($categoryService){
        $this->categoryService = $categoryService;
    }

    public function __invoke($key, $seek = 'label', $options = array()){
        $item = false;
        try {
            $item = $this->categoryService->getItem($key);
        } catch (\Exception $e) {
            return $key;
            //return $e->getMessage();
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
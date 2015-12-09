<?php
namespace CasasoftStandards\View\Helper;
 
use Zend\View\Helper\AbstractHelper;
use Zend\View\Model\ViewModel;

class Numval extends AbstractHelper{ 
    public $numvalService = false;
    function __construct($numvalService){
       $this->numvalService = $numvalService;
    }

    public function __invoke($item, $seek = 'label', $value = null, $options = array()){
        $options = array_merge(array(
            'km_convert_at' => 501
        ), $options);
        if (is_string($item)) {
            try {
                $item = $this->numvalService->getItem($item);
                $item->setValue($value);
            } catch (\Exception $e) {
                return '';
            }
        } elseif (!is_object($item)) {
            return '';
        }

        $number_filter = new \Zend\I18n\Filter\NumberFormat("de_CH");

        switch ($seek) {
            case 'label':
                return $item->getLabel();
                break;
            case 'value':
                $val = $item->getValue();
                $km = false;

                //km conversion
                if (in_array($item->getSi(), array('m'))) {
                    if ($options['km_convert_at'] && $val >= $options['km_convert_at']) {
                        $val = $val/1000;
                        $km = true;
                    }
                }

                switch ($item->getSi()) {
                    case 'm': $val = $number_filter->filter($val) . ' ' . ($km ? 'km' : 'm'); break;
                    case 'm2': $val = $number_filter->filter($val) . ' ' . ($km ? 'km' : 'm') . '<sup>2</sup>'; break;
                    case 'm3': $val = $number_filter->filter($val) . ' ' . ($km ? 'km' : 'm') . '<sup>3</sup>'; break;
                }

                return $val;
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
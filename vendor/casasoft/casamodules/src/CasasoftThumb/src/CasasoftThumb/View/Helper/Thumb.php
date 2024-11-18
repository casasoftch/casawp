<?php
namespace CasasoftThumb\View\Helper;
 
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Model\ViewModel;

class Thumb extends AbstractHelper{ 
    public $thumbService = false;

    public function __construct($thumbService){
        $this->thumbService = $thumbService;
    }

    public function __invoke($path, $options = array(), $render = 'img', $depricated = false){
        if ($depricated && is_array($depricated)) {
            $options['attributes'] = $depricated;
        }
        
        $attr = array(
            'class' => '',
            'style' => '',
        );
        
        if (isset($options['attributes'])) {
            foreach ($options['attributes'] as $o_attr => $o_value) {
                if (isset($attr[$o_attr])) {
                    $attr[$o_attr] = trim($attr[$o_attr] . ' ' . $o_value);
                } else {
                    $attr[$o_attr] = trim($o_value);
                }
            }
        }

        $result = $this->thumbService->generateThumb($path, $options);

        if (in_array($result, array(
            'original_missing',
            'Imagick_missing',
            'Imagick_exception'
        ))) {
            return $result;
        }
        
        if ($render != 'img-no-width') {
            //$attr['width'] = $result['width'];
            //$attr['height'] = $result['height'];
        }

        
       
        $attr['src'] = $result['src'];
        if (!isset($attr['alt']) || !$attr['alt']) {
            if (isset($attr['title']) && $attr['title']) {
                $attr['alt'] = $attr['title'];
            } else {
                $attr['alt'] = 'Image';
            }
        }

        //$attr['width'] = round($result['width']);
        //$attr['height'] = round($result['height']);

        $attr['src'] = $attr['src'];

        

        //marginify
        /*if (!isset($attr['style']) || !$attr['style']) {
            $styles = array();
            if (isset($options['width']) && (float) $options['width']) {
                if ((float) $options['width'] > (float) $result['width']) {
                    $diff = (float) ((float) $options['width']) - ((float) $result['width']);
                    $styles[] = 'padding-left: ' . ($diff/2) . 'px';
                    $styles[] = 'padding-right: ' . ($diff/2) . 'px';
                    //$styles[] = 'width: ' . $result['width'] . 'px';
                }
            }
            if (isset($options['height']) && (float) $options['height']) {
                if ((float) $options['height'] > (float) $result['height']) {
                    $diff = (float) ((float) $options['height']) - ((float) $result['height']);
                    $styles[] = 'padding-top: ' . ($diff/2) . 'px';
                    $styles[] = 'padding-bottom: ' . ($diff/2) . 'px';
                    //$styles[] = 'height: ' . $result['height'] . 'px';
                }
            }
            if (count($styles)) {
                $attr['style'] = implode('; ', $styles);
            }
        }*/

        
        

        $attr_str = '';
        if (is_array($result)) {
        	switch ($render) {
        		case 'img':
                    foreach ($attr as $prop => $val) {
                        if ($val) {
                            $attr_str .= $prop . '="'.$val.'" ';
                        }
                    }
        			return '<img ' . $attr_str . ' />';
        			break;
                case 'img-no-width':
                    foreach ($attr as $prop => $val) {
                        if ($val) {
                            $attr_str .= $prop . '="'.$val.'" ';
                        }
                    }
                    return '<img' . $attr_str . ' />';
                    break;
        		
        		case 'src':
        			return $result['src'];
        			break;
        	}
        } else {
            return $path;
        }
    }
}
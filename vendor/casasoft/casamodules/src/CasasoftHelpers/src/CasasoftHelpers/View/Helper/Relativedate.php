<?php
namespace CasasoftHelpers\View\Helper;
 
use Laminas\View\Helper\AbstractHelper;

class Relativedate extends AbstractHelper{ 


    public function __construct(){
        //$this->view->translate->setTranslatorTextDomain('casasoft-helpers'); 
        //$this->plugin('translate')->setTranslatorTextDomain('casasoft-helpers');
    }
 
    public function __invoke($date, $stamp = false){
            if (!$date) {
                return $this->view->translate('Unknown');
            }
        
    	    if ($stamp) {
                $diff = time() - $date;
            } else if ($date instanceof \DateTime) {
                $diff = time() - $date->getTimestamp();
            } else {
                $diff = time() - strtotime($date);
            }

            //future
            if ($diff < 0) {
                $diff = $diff * (-1);

                //seconds
                if ($diff < 60){
                    return sprintf(
                        $this->view->translatePlural('in one second', 'in %s seconds', $diff, 'casasoft-helpers')
                    , $diff);
                }

                //minutes
                $diff = round($diff/60);
                if ($diff < 60){
                    return sprintf(
                        $this->view->translatePlural('in one minute', 'in %s minutes', $diff, 'casasoft-helpers')
                    , $diff);
                }

                //hours
                $diff = round($diff/60);
                if ($diff < 24){
                    return sprintf(
                        $this->view->translatePlural('in one hour', 'in %s hours', $diff, 'casasoft-helpers')
                    , $diff);
                }

                //days
                $diff = round($diff/24);
                if ($diff < 7){
                    return sprintf(
                        $this->view->translatePlural('in one day', 'in %s days', $diff, 'casasoft-helpers')
                    , $diff);
                }

                //week
                $diff = round($diff/7);
                if ($diff < 4){
                    return sprintf(
                        $this->view->translatePlural('in one week', 'in %s weeks', $diff, 'casasoft-helpers')
                    , $diff);
                }
                if ($stamp) {
                    return date('d.m.Y', $date);     
                } else if ($date instanceof \DateTime) {
                    return date('d.m.Y', $date->getTimestamp());
                } else {
                    return date('d.m.Y', strtotime($date));
                }

            //past
            } else {
                
                //seconds
                if ($diff < 60){
                    return sprintf(
                        $this->view->translatePlural('one second ago', '%s seconds ago', $diff, 'casasoft-helpers')
                    , $diff);
                }

                //minutes
                $diff = round($diff/60);
                if ($diff < 60){
                    return sprintf(
                        $this->view->translatePlural('one minute ago', '%s minutes ago', $diff, 'casasoft-helpers')
                    , $diff);
                }

                //hours
                $diff = round($diff/60);
                if ($diff < 24){
                    return sprintf(
                        $this->view->translatePlural('one hour ago', '%s hours ago', $diff, 'casasoft-helpers')
                    , $diff);
                }

                //days
                $diff = round($diff/24);
                if ($diff < 7){
                    return sprintf(
                        $this->view->translatePlural('one day ago', '%s days ago', $diff, 'casasoft-helpers')
                    , $diff);
                }

                //week
                $diff = round($diff/7);
                if ($diff < 4){
                    return sprintf(
                        $this->view->translatePlural('one week ago', '%s weeks ago', $diff, 'casasoft-helpers')
                    , $diff);
                }

                if ($stamp) {
                    return date('d.m.Y', $date);     
                } else if ($date instanceof \DateTime) {
                    return date('d.m.Y', $date->getTimestamp());
                } else {
                    return date('d.m.Y', strtotime($date));
                }
            }
    }
}
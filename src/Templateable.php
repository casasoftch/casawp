<?php
  namespace CasaSync;

  class Templateable {  
    public $template = false;
    public $type = 'archive';
    public $object = false;

    public function __construct(){ 
    }  

    public function setSingle(){

    }

    public function setTemplate($type = 'archive', $object){
      $this->type = $type;
      switch ($type) {
        case 'archive':
          if (get_option( 'casasync_archive_template', false )) {
            $this->template = stripslashes(get_option( 'casasync_archive_template', false ));
          } else {
            $this->template = false;
          }
          break;
        case 'single':
          if (get_option( 'casasync_single_template', false )) {
            $this->template = stripslashes(get_option( 'casasync_single_template', false ));
          } else {
            $this->template = false;
          }
          break;
        case 'archive_single':

          if (get_option( 'casasync_archive_single_template', false )) {
            $this->template = stripslashes(get_option( 'casasync_archive_single_template', false ));
          } else {
            $this->template = false;
          }
          break;
        
        default:
          $this->template = false;
          break;
      }
      $this->object = $object;
      return false;
      return $this->template;
    }

    public function getTemplateType(){
      return $this->type;
    }

    public function getTags(){
      if ($this->type == 'single' || $this->type == 'archive_single') {
        return array(
          'tags' => array(
            'ids' => 'getIds',
            'classes' => 'getClasses',
            'title' => 'getTitle',
            'gallery' => 'getGallery',
            'content' => 'getTabable',
            'tabable' => 'getTabable',
            'pagination' => 'getPagination',
            'cta' => 'getCta',
            'share' => 'getShare',
            'contactform' => 'getContactform',
            'seller' => 'getSeller',
            'salesperson' => 'getSalesperson',
            'reference' => 'getReference',
            'permalink' => 'getPermalink',
          ),
          'if' => array(
            'planned' => 'isPlanned',
            'reference' => 'isReference',
          )
        );
      } elseif ($this->type == 'archive') {
        return array(
          'tags' => array(
          ),
          'special' => array(
            'properties' => ''
          )
        );
      }
      return array();
    }

    public function getStringBetween($string, $start, $end){
      $string = " ".$string;
      $pos = strpos($string,$start);
      if ($pos == 0) { return "";}
      $pos += strlen($start);
      $len = strpos($string,$end,$pos) - $pos;
      return substr($string,$pos,$len);
    }

    function interpret_gettext($return){
      $finished = false;
      while ($finished == false) {
          $translatable_str = $this->getStringBetween($return, "__(", ")");
          if ($translatable_str) {
              $return = str_replace("__(" . $translatable_str . ")", __($translatable_str, 'casasync'), $return);
          } else {
              $finished = true;
          }
          
      }
      return $return;
    }

    public function setIf($return, $tagslug, $value){
      for ($i=0; $i < 3; $i++) {
        if ($value) {
            $before = $this->getStringBetween($return, "{if_".$tagslug."}", "{".$tagslug."}");
            $after = $this->getStringBetween($return, "{".$tagslug."}", "{end_if_".$tagslug."}");
            $return = str_replace($before.'{'.$tagslug.'}'.$after, $before . $value . $after, $return);
            $return = str_replace('{if_'.$tagslug.'}', '', $return);
            $return = str_replace('{end_if_'.$tagslug.'}', '', $return);
        } else {
            $rm = $this->getStringBetween($return, "{if_".$tagslug."}", "{end_if_".$tagslug."}");
            $return = str_replace("{if_".$tagslug."}" . $rm . "{end_if_".$tagslug."}", '', $return);
            $return = str_replace("{".$tagslug."}", '', $return);
        }
      }
      return $return;
    }

    public function setIfNot($return, $tagslug, $value){
      for ($i=0; $i < 3; $i++) {
        if ($value) {
            $rm = $this->getStringBetween($return, "{!if_".$tagslug."}", "{!end_if_".$tagslug."}");
            $return = str_replace("{!if_".$tagslug."}" . $rm . "{!end_if_".$tagslug."}", '', $return);
        } else {
            $return = str_replace("{!if_".$tagslug."}", '', $return);
            $return = str_replace("{!end_if_".$tagslug."}", '', $return);
        }
      }
      return $return;
    }

    public function render(){
      if ($this->template) {
        $return = $this->interpret_gettext($this->template);
        foreach ($this->getTags() as $tagGroupName => $tagGroup) {
          if ($tagGroupName == 'tags') {
            foreach ($tagGroup as $tag => $method) {
              if (method_exists($this->object, $method) && (strpos($return, '{'.$tag.'}') || strpos($return, 'if_'.$tag.'}'))) {
                $return = $this->setIfNot($return, $tag, $this->object->{$method}());
                $return = $this->setIf($return, $tag,  $this->object->{$method}());
              }
            }
          } else if ($tagGroupName == 'special') {
            foreach ($tagGroup as $tag => $method) {
              if ($tag == 'properties') {
                $p_return = '';
                while ( have_posts() ) : the_post();
                  global $post;
                    $single = new Single($post);
                    $template = new Templateable();
                  if ($template->setTemplate('archive_single', $single)):
                    $p_return .= $template->render();
                  else: 
                    $p_return .= '<h1>' . $single->getTitle() . '</h1>';
                    $p_return .= $single->getGallery();
                  endif;
                endwhile;
                $return = str_replace('{'.$tag.'}', $p_return, $return);
              }
            }
          }
        }
        return $return;
      } else {
        return false;
      }
    }

  }  

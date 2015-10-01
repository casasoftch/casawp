<?php
namespace Casasync\Service;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver;

class OfferService{
    public $post = null;
    private $categories = null;
    private $attachments = null;
    private $numvals = array();
    private $numvals_to_display = null;

    public function __construct($categoryService, $numvalService, $messengerService){
    	$this->categoryService = $categoryService;
    	$this->numvalService = $numvalService;
    	$this->messengerService = $messengerService;
    }

    public function setPost($post){
        $this->post = $post;
    }

    //deligate all other gets to the WP_post class
    function __get($name){
    	return $this->post->{$name};
    }

    //deligate all other methods to WP_Post Class
    function __call($name, $arguments){
    	if (method_exists($this->post, $name)) {
    		return $this->post->{$name}($arguments);
    	}
    }

    public function to_array() {
    	$post_array = $this->post->to_array();
		$offer_array = get_object_vars( $this );
		return array_merge($post_array, $offer_array);
	}

	public function getTitle(){
		return $this->post->post_title;
	}

	public function getCategories(){
		if ($this->categories === null) {
			$terms = wp_get_post_terms( $this->post->ID, 'casasync_category', array("fields" => "names"));
			foreach ($terms as $termName) {
				if ($this->categoryService->keyExists($termName)) {
					$this->categories[] = $this->categoryService->getItem($termName);
				} else {
					$unknown_category = new CasasoftStandards\Service\Category();
					$unknown_category->setKey($termName);
					$unknown_category->setLabel('?'.$termName);
					$this->categories[] = $unknown_category;
				}
			}
		}
		return $this->categories;
	}

	public function renderCategoryLabels(){
		$cat_labels = array();
		foreach ($this->getCategories() as $category) {
			$cat_labels[] = $category->getLabel();
		}
		return implode(', ', $cat_labels);
	}

	public function getNumval($key){
		foreach ($this->getNumvals() as $numval) {
			if ($numval->getKey() == $key) {
				return $numval;
			}
		}
	}

	public function getNumvals(){
		if ($this->numvals == null) {
			foreach ($this->numvalService->getItems() as $numval) {
				$value = get_post_meta( $this->post->ID, $numval->getKey(), $single = true );
				if ($value) {
					$numval->setValue($value);
					$this->numvals[$numval->getKey()] = $numval;
				}
			}
			
		}
		return $this->numvals;
	}

	public function getAttachments(){
		if ($this->attachments === null) {
			$this->attachments = get_posts( array(
	          'post_type'                => 'attachment',
	          'posts_per_page'           => -1,
	          'post_parent'              => $this->post->ID,
	          //'exclude'                => get_post_thumbnail_id(),
	          'casasync_attachment_type' => 'image',
	          'orderby'                  => 'menu_order',
	          'order'                    => 'ASC'
	        ) );
		}
		return $this->attachments;
	}

	public function getFieldValue($key){
		switch (true) {
			case strpos($key,'address') === 0:
				return $this->getFieldValue('casasync_property_'.$key);
				break;
			case !strpos($key,'casasync') === 0:
				return $this->getFieldValue('casasync_'.$key);
			default:
				return get_post_meta( $this->post->ID, $key, $single = true );
				break;
		}
	}

	private function render($view, $args){
		$renderer = new PhpRenderer();
		$resolver = new Resolver\AggregateResolver();
		$renderer->setResolver($resolver);
		$stack = new Resolver\TemplatePathStack(array(
		    'script_paths' => array(
		        CASASYNC_PLUGIN_DIR . '/modules/Casasync/view'
		    )
		));
		$resolver->attach($stack);
		$model = new ViewModel($args);
		$model->setTemplate('casasync/offer/'.$view);
		$result = $renderer->render($model);

		return $result;
	}

	public function getPrimarySingleDatapoints(){
		if ($this->numvals_to_display === null) {
			$presentable_numvals = array(
	          'casasync_single_show_number_of_rooms',
	          #'casasync_single_show_surface_usable',
	          #'casasync_single_show_surface_living',
	          'casasync_single_show_area_sia_nf',
	          'casasync_single_show_area_nwf',
	          'casasync_single_show_area_bwf',
	          'casasync_single_show_surface_property',
	          'casasync_single_show_floor',
	          'casasync_single_show_number_of_floors',
	          'casasync_single_show_year_built',
	          'casasync_single_show_year_renovated',
	          'casasync_single_show_availability'
	        );

	        $numvals_to_display = array();
	        $i = 1000;
	        foreach ($presentable_numvals as $value) {
	          if(get_option($value, false)) {
	          	switch ($value) {
					case 'casasync_single_show_number_of_rooms': $key = 'number_of_rooms'; break;
		          	case 'casasync_single_show_surface_usable': $key = 'unknown'; break;
		          	case 'casasync_single_show_surface_living': $key = 'unknown'; break;
		          	case 'casasync_single_show_area_sia_nf': $key = 'area_sia_nf'; break;
		          	case 'casasync_single_show_area_nwf': $key = 'area_nwf'; break;
		          	case 'casasync_single_show_area_bwf': $key = 'area_bwf'; break;
		          	case 'casasync_single_show_surface_property': $key = 'area_sia_angf'; break;
		          	case 'casasync_single_show_floor': $key = 'floor'; break;
		          	case 'casasync_single_show_number_of_floors': $key = 'number_of_floors'; break;
		          	case 'casasync_single_show_year_built': $key = 'year_built'; break;
		          	case 'casasync_single_show_year_renovated': $key = 'year_last_renovated'; break;
		          	case 'casasync_single_show_availability': $key = 'special_availability'; break;
					default: $key='unknown'; break;
				}

	            $numval_order = get_option($value.'_order', false);
	            if($numval_order) {
	              $numvals_to_display[$numval_order] = $key;
	            } else {
	              $numvals_to_display[$i] = $key;
	              $i++;
	            }
	          }
	        }
	        ksort($numvals_to_display);
	        $this->numvals_to_display = $numvals_to_display;
        }
        return $this->numvals_to_display;
	}

	public function getAvailablility(){
		return '...';
	}

	//view actions "direct"
	public function renderNumvalValue($numval){
		switch ($numval->getSi()) {
			case 'm2': return $numval->getValue() .'m<sup>2</sup>'; break;
			case 'm':  return $numval->getValue() .'m'; break;
			default:   return $numval->getValue(); break;
		}
		return null;
	}

	public function renderContent(){
		$html = '';
		foreach ($this->getContentParts() as $part) {
			$html .= $part;
		}
		return $html;
	}

	public function getContentParts(){
		$content = apply_filters('the_content', $this->post->post_content);
     	$content_parts = explode('<hr class="property-separator" />', $content);
     	return $content_parts;
	}

	//view actions
	public function getGallery(){
		return $this->renderGallery();
	}
	public function renderGallery(){
		$attachments = $this->getAttachments();
		return $this->render('gallery', array(
			'attachments' => $attachments,
			'offer' => $this
		));
	}

	public function getTabable(){
		return $this->renderTabable();
	}
	public function renderTabable(){
		return $this->render('tabable', array(
			'offer' => $this
		));
	}

	public function getBasicBoxes(){
		return $this->renderBasicBoxes();
	}
	public function renderBasicBoxes(){
		return $this->render('basic-boxes', array(
			'offer' => $this
		));
	}

	public function getAddress(){
		return $this->renderAddress();
	}
	public function renderAddress(){
		return $this->render('address', array(
			'offer' => $this
		));
	}

	

	public function renderDatapoints($context = 'single'){
		if ($context == 'single') {
			$datapoints = $this->getPrimarySingleDatapoints();
		} else {
			$datapoints = array();
		}
		$numvals = array();
		foreach ($datapoints as $key) {
			$numval = $this->getNumval($key);
			if ($numval) {
				$numvals[] = $numval;
			}
		}
		return $this->render('datapoints', array(
			'offer' => $this,
			'context' => $context,
			'numvals' => $numvals
		));
	}

	public function getMap(){
		return $this->renderMap();
	}
	public function renderMap(){
		return $this->render('map', array(
			'offer' => $this
		));	    
	}


}
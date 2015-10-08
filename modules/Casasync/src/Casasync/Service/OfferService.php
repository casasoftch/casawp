<?php
namespace Casasync\Service;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver;

class OfferService{
    public $post = null;
    private $categories = null;
    private $availability = null;
    private $attachments = null;
    private $documents = null;
    private $single_dynamic_fields = null;
    private $archive_dynamic_fields = null;
    private $salestype = null;
    private $metas = null;
    private $casasync = null;

    public function __construct($categoryService, $numvalService, $messengerService, $utilityService, $featureService){
    	$this->utilityService = $utilityService;
    	$this->categoryService = $categoryService;
    	$this->numvalService = $numvalService;
        $this->featureService = $featureService;
    	$this->messengerService = $messengerService;
    }

    private function resetPost(){
	    foreach (get_class_vars(get_class($this)) as $var => $def_val){
	        $this->$var= $def_val;
	    }
   	}

    public function setPost($post){
    	$this->resetPost();
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
		$offer_array = array(
			'post' => $this->post->to_array()
		);

		//basics
		$offer_array['title'] = $this->getTitle();

    	//if load categories example
    	$offer_array['categories'] = $this->getCategoriesArray();

		return $offer_array;
	}


	/*====================================
	=            Data Getters            =
	====================================*/
		
	public function getTitle(){
		return $this->post->post_title;
	}

	public function getCategories(){
		if ($this->categories === null) {
			$terms = wp_get_post_terms( $this->post->ID, 'casasync_category', array("fields" => "names"));
			foreach ($terms as $termName) {
				if ($this->categoryService->keyExists($termName)) {
					$this->categories[] = $this->categoryService->getItem($termName);
				} else if ($this->utilityService->keyExists($termName)) {
					$this->categories[] = $this->utilityService->getItem($termName);
				} else {
					$unknown_category = new \CasasoftStandards\Service\Category();
					$unknown_category->setKey($termName);
					$unknown_category->setLabel('?'.$termName);
					$this->categories[] = $unknown_category;
				}
			}
		}
		return $this->categories;
	}

	public function getCategoriesArray(){
		$categories = $this->getCategories();
		$arr_categories = array();
		foreach ($categories as $category) {
			$arr_categories[] = array(
				'key' => $category->getKey(),
				'label' => $category->getLabel()
			);
		}
		return $arr_categories;
	}

	public function getAvailablility() {
		if ($this->availability === null) {
			$terms = wp_get_post_terms( $this->post->ID, 'casasync_availability', array("fields" => "names"));
			$this->availability = isset($terms[0]) ? $terms[0] : false;
		}
		return $this->availability;
	}

	public function getSalestype(){
		if ($this->salestype === null) {
			$types = get_the_terms( $this->post->ID, 'casasync_salestype' );
			if ($types) {
				$type = array_pop($types);
				$this->salestype = $type->slug;
			} else {
				$this->salestype = false;
			}
		}
		return $this->salestype;
	}

    public function getFeature($key){
        foreach ($this->getFeatures() as $numval) {
            if ($numval->getKey() == $key) {
                return $numval;
            }
        }
    }

    public function getFeatures(){
        $features = array();
        foreach ($this->featureService->getItems() as $feature) {
            $meta_features = json_decode($this->getFieldValue('features', false), true);
            foreach ($meta_features as $meta_feature) {
                if ($meta_feature["value"] == $feature->getKey()) {
                    $features[$feature->getKey()] = $feature;
                    break;
                }
            }
        }
        return $features;
    }

	public function getNumval($key){
		foreach ($this->getNumvals() as $numval) {
			if ($numval->getKey() == $key) {
				return $numval;
			}
		}
	}

	public function getNumvals(){
		$numvals = array();
		foreach ($this->numvalService->getItems() as $numval) {
            if (strpos($numval->getKey(), "distance_") !== 0) {
    			$value = $this->getFieldValue($numval->getKey(), false);
    			if ($value) {
    				$numval->setValue($value);
    				$numvals[$numval->getKey()] = $numval;
    			}
            }
		}
		return $numvals;
	}

    public function getDistances(){
        $numvals = array();
        foreach ($this->numvalService->getItems() as $numval) {
            if (strpos($numval->getKey(), "distance_") === 0) {
                $value = $this->getFieldValue($numval->getKey(), false);
                if ($value) {
                    $numval->setValue($value);
                    $numvals[$numval->getKey()] = $numval;
                }
            }
        }
        return $numvals;
    }

	public function getAttachments(){
		if ($this->attachments === null) {
			$this->attachments = get_posts( array(
	          'post_type'                => 'attachment',
	          'posts_per_page'           => -1,
	          'post_parent'              => $this->post->ID,
	          //'exclude'                => get_post_thumbnail_id(),
              'taxonomy'                 => 'casasync_attachment_type',
	          //'casasync_attachment_type' => 'image',
	          'orderby'                  => 'menu_order',
	          'order'                    => 'ASC'
	        ) );
		}
		return $this->attachments;
	}

	public function getImages(){
		$images = array();
		foreach ($this->getAttachments() as $attachment) {
			if(has_term( 'image', 'casasync_attachment_type', $attachment )){
				$images[] = $attachment;
			}
		}
		return $images;
	}

	public function getDocuments(){
		$docs = array();
		foreach ($this->getAttachments() as $attachment) {
			if(has_term( 'document', 'casasync_attachment_type', $attachment )){
				$docs[] = $attachment;
			}
		}
		return $docs;
	}

	public function getSalesBrochures(){
		$docs = array();
		foreach ($this->getAttachments() as $attachment) {
			if(has_term( 'sales-brochure', 'casasync_attachment_type', $attachment )){
				$docs[] = $attachment;
			}
		}
		return $docs;
	}

	public function getPlans(){
		$docs = array();
		foreach ($this->getAttachments() as $attachment) {
			if(has_term( 'plan', 'casasync_attachment_type', $attachment )){
				$docs[] = $attachment;
			}
		}
		return $docs;
	}

	public function getMetas(){
		if ($this->metas === null) {
			$this->metas = get_post_meta($this->post->ID);
		}
		return $this->metas;
	}

	public function getMeta($key){
		if (array_key_exists($key, $this->getMetas())) {
			return $this->metas[$key][0];
		}
		return null;
	}

	public function getFieldValue($key, $fallback = null){
		switch (true) {
			case strpos($key,'address') === 0:
				$value = $this->getFieldValue('casasync_property_'.$key, $fallback);
				break;
			default:
				$value = $this->getMeta($key);
				break;
		}
		if (!$value) {
			//try with casasync prefix
			$value = $this->getMeta('casasync_'.$key);
		}
		if ($value) {
			return $value;
		} else {
			return $fallback;
		}
	}

    private function getSingleDynamicFields() {
        return array(
          'casasync_single_show_number_of_rooms',
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
    }

    private function getArchiveDynamicFields() {
        return array(
            'casasync_archive_show_street_and_number',
            'casasync_archive_show_location',
            'casasync_archive_show_number_of_rooms',
            'casasync_archive_show_area_sia_nf',
            'casasync_archive_show_area_bwf',
            'casasync_archive_show_surface_property',
            'casasync_archive_show_floor',
            'casasync_archive_show_number_of_floors',
            'casasync_archive_show_year_built',
            'casasync_archive_show_year_renovated',
            'casasync_archive_show_price',
            'casasync_archive_show_excerpt',
            'casasync_archive_show_availability'
        );
    }

	public function getPrimarySingleDatapoints(){
		if ($this->single_dynamic_fields === null) {
			
	        $values_to_display = array();
	        $i = 1000;
	        foreach ($this->getSingleDynamicFields() as $value) {
	          if(get_option($value, false)) {
	          	switch ($value) {
					case 'casasync_single_show_number_of_rooms': $key = 'number_of_rooms'; break;
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

	            $value_order = get_option($value.'_order', false);
	            if($value_order) {
	              $values_to_display[$value_order] = $key;
	            } else {
	              $values_to_display[$i] = $key;
	              $i++;
	            }
	          }
	        }
	        ksort($values_to_display);
	        $this->single_dynamic_fields = $values_to_display;
        }
        return $this->single_dynamic_fields;
	}

    public function getPrimaryArchiveDatapoints() {
        $value_to_display = array();
        $i = 1000;
        foreach ($this->getArchiveDynamicFields() as $value) {
          if(get_option($value, false)) {

            $value_order = get_option($value.'_order', false);
            if($value_order) {
              $value_to_display[$value_order] = $value;
            } else {
              $value_to_display[$i] = $value;
              $i++;
            }
          }
        }
        ksort($value_to_display);
        return $value_to_display;
    }
    public function renderQuickInfosTable() {
        // todo: delete options for archive-fields. new way is to edit the view file
        return $this->render('quick-infos-table', array(
            'offer' => $this
        ));
    }

    /*===========================================
    =          Direct renders actions           =
    ===========================================*/

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

	public function renderPrice($scope = 'gross'){
		$type = $this->getSalestype();

		$meta_prefix = 'price';
		if ($type == 'rent') {
			$meta_prefix = 'grossPrice';
			if ($scope == 'net') { $meta_prefix = 'netPrice';}	
		}
		
		$value = $this->getFieldValue($meta_prefix, false);
		$currency = $this->getFieldValue('price_currency', 'CHF');
		$propertySegment = $this->getFieldValue($meta_prefix.'_propertysegment', 'all');
		$timeSegment = $this->getFieldValue($meta_prefix.'_timesegment', 'infinite');

		$timesegment_labels = array(
	        'm' => __('month', 'casasync'),
	        'w' => __('week', 'casasync'),
	        'd' => __('day', 'casasync'),
	        'y' => __('year', 'casasync'),
	        'h' => __('hour', 'casasync')
	    );

		if ($value) {
			$parts = array();
			$parts[] = $currency;
			$parts[] = number_format(round($value), 0, '', '\'');
			$parts[] = ($propertySegment != 'all' ? ' / m<sup>2</sup>' : '' );
			$parts[] = (in_array($timeSegment, array_keys($timesegment_labels)) ? ' / ' . $timesegment_labels[$timeSegment] : '' );
			array_walk($parts, function(&$value){ $value = trim($value);});
			$parts = array_filter($parts);
			return implode(' ', $parts);
		} else {
			return __('On Request', 'casasync');
		}
		
	}

	public function renderAvailabilityDate($start = false){
		$current_datetime = strtotime(date('c'));
		if (!$start) {
			$start = $this->getFieldValue('start', false);
		}
		$property_datetime = false;
	    if ($start) {
	    	$property_datetime = strtotime($this->start);
	    }
	    
	    if ($property_datetime && $property_datetime > $current_datetime) {
	    	$datetime = new \DateTime(str_replace(array("+02:00", "+01:00"), "", $this->start));
	    	$return = date_i18n(get_option('date_format'), $datetime->getTimestamp());
	    } else if (!$property_datetime){
	    	$return = __('On Request', 'casasync');  
	    } else {
	    	$return = __('Immediate' ,'casasync');
	    }
	      
	    return $return;
	}

	public function renderCategoryLabels(){
		$cat_labels = array();
		foreach ($this->getCategories() as $category) {
			$cat_labels[] = $category->getLabel();
		}
		return implode(', ', $cat_labels);
	}


	/*======================================
	=            Render Actions            =
	======================================*/

	public function render($view, $args = array()){
		global $casasync;
		return $casasync->render($view, $args);
	}

	public function renderFeatures() {
        $features = $this->getFeatures();
        return $this->render('features', array(
            'features' => $features
        ));
    }

    public function renderDistances() {
        $distances = $this->getDistances();
        return $this->render('distances', array(
            'distances' => $distances,
            'offer' => $this
        ));
    }
	
	public function renderGallery(){
		$images = $this->getImages();
		return $this->render('gallery', array(
			'images' => $images,
			'offer' => $this
		));
	}

	public function renderTabable(){
		return $this->render('tabable', array(
			'offer' => $this
		));
	}

	public function renderBasicBoxes(){
		return $this->render('basic-boxes', array(
			'offer' => $this
		));
	}

	public function renderAddress(){
		return $this->render('address', array(
			'type' => 'property',
			'offer' => $this
		));
	}

	public function renderSellerAddress(){
		return $this->render('address', array(
			'type' => 'seller',
			'offer' => $this
		));
	}

	public function renderSeller(){
		return $this->render('seller', array(
			'offer' => $this
		));
	}

	public function renderSalesPerson(){
		return $this->render('sales-person', array(
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
			if ($key == 'special_availability') {
				$numval = 'special_availability';
			} else {
				$numval = $this->getNumval($key);
			}
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

	public function renderMap(){
		return $this->render('map', array(
			'offer' => $this
		));	    
	}

	public function renderDatatable(){
		return $this->render('datatable', array(
			'offer' => $this
		));
	}

	public function renderDatatableOffer(){
		return $this->render('datatable-offer', array(
			'offer' => $this
		));
	}

	public function renderDatatableProperty(){
		return $this->render('datatable-property', array(
			'offer' => $this
		));
	}

	public function renderFeaturedImage(){
		return $this->render('featured-image', array(
			'offer' => $this
		));
	}

	public function renderContactForm(){
        $form = new \Casasync\Form\ContactForm();
        $sent = false;
        $customerid = get_option('casasoft_customerid');
        $publisherid = get_option('casasoft_publisherid');
        $email = get_option('casasync_email_fallback');

        if ($this->getFieldValue('seller_org_customerid', false)) {
        	$customerid = $this->getFieldValue('seller_org_customerid', false);
        }
        if ($this->getFieldValue('seller_inquiry_person_email', false)) {
        	$email = $this->getFieldValue('seller_inquiry_person_email', false);
        }
        
        if (get_option('casasync_inquiry_method') == 'casamail') {
        	//casamail
        	if (!$customerid || !$publisherid) {
        		return '<p class="alert alert-danger">CASAMAIL MISCONFIGURED: please define a provider and publisher id <a href="/wp-admin/admin.php?page=casasync&tab=contactform">here</a></p>';
        	}
        	
        } else {
        	if (!$email) {
        		return '<p class="alert alert-danger">EMAIL MISCONFIGURED: please define a email address <a href="/wp-admin/admin.php?page=casasync&tab=contactform">here</a></p>';
        	}
        }

        if ($_POST) {
        	$filter = $form->getFilter();
	        $form->setInputFilter($filter);
        	$form->setData($_POST);
        	if ($form->isValid()) {
			    $validatedData = $form->getData();
			    $sent = true;
			    if (isset($_POST['email']) && $_POST['email']) {
			    	//SPAM
			    } else {
			    	//add to WP for safekeeping
			    	$post = array(
			    		'post_type' => 'casasync_inquiry',
			    		'post_content' => $form->get('message')->getValue(),
			    		'post_title' => wp_strip_all_tags($form->get('firstname')->getValue() . ' ' . $form->get('lastname')->getValue() . ': [' . ($this->getFieldValue('reference_id') ? $this->getFieldValue('reference_id') : $this->getFieldValue('casasync_id')) . '] ' . $this->getTitle()),
			    		'post_status' => 'private',
			    		'ping_status' => false
			    	);
			    	$inquiry_id = wp_insert_post($post);
			    	foreach ($form->getElements() as $element) {
			    		if (!in_array($element->getName(), array('message')) ) {
			    			add_post_meta($inquiry_id, 'sender_' . $element->getName(), $element->getValue(), true );
			    		}
			    	}
			    	add_post_meta($inquiry_id, 'casasync_id', $this->getFieldValue('casasync_id'), true );
			    	add_post_meta($inquiry_id, 'reference_id', $this->getFieldValue('reference_id'), true );

			    	if (get_option('casasync_inquiry_method') == 'casamail') {
			        	//casamail
			        } else {
			        	
			        }
			    }

			} else {
			    $messages = $form->getMessages();
			}
        } else {
        	$form->get('message')->setValue(__('I am interested concerning this property. Please contact me.','casasync'));
        }

        //$form->bind($this->queryService);
        return $this->render('contact-form', array(
        	'form' => $form,
        	'offer' => $this,
        	'sent' => $sent
        ));
    }

    public function renderContactFormElement($element){
    	return $this->render('contact-form-element', array('element' => $element));
    }

    public function renderPagination(){
    	return $this->render('single-pagination', array());
    }

}
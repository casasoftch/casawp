<?php
namespace casawp\Service;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver;

class OfferService{
    public $post = null;
    private $categories = null; //lazy
    private $features = null; //lazy
    private $utilities = null; //lazy
    private $availability = null; //lazy
    private $attachments = null; //lazy
    private $documents = null; //lazy
    private $single_dynamic_fields = null;  //lazy
    private $archive_dynamic_fields = null;  //lazy
    private $salestype = null; //lazy
    private $metas = null; //lazy
    private $similar_properties_by_price = null; //lazy
    private $similar_properties_by_location = null; //lazy
    private $casawp = null;

    public function __construct($categoryService, $numvalService, $messengerService, $utilityService, $featureService, $integratedOfferService){
    	$this->utilityService = $utilityService;
    	$this->categoryService = $categoryService;
    	$this->numvalService = $numvalService;
        $this->featureService = $featureService;
    	$this->messengerService = $messengerService;
    	$this->integratedOfferService = $integratedOfferService;
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


	/*=======================================
	=            Array Functions            =
	========================================*/

	public function to_array() {
		$offer_array = array(
			'post' => $this->post->to_array()
		);

		//basics
		$offer_array['permalink'] = get_permalink($this->post);
		$offer_array['address'] = $this->address_to_array();
		$offer_array['salestype'] = $this->getSalestype();
		if ($this->getSalestype() == 'buy'){
			$price = $this->getFieldValue('price', false);
			$offer_array['price'] = array(
				'value' => ($price ? $price : 0),
				'rendered' => ($price ? $this->renderPrice() : __('On Request', 'casawp')),
				'propertySegment' => $this->getFieldValue('price_propertysegment', false),
				'label' => __('Sales price', 'casawp')
			);
		} elseif($this->getSalestype() == 'rent') {
			$price = $this->getFieldValue('grossPrice', false);
			$offer_array['grossPrice'] = array(
				'value' =>  ($price ? $price : 0),
				'rendered' => ($price ? $this->renderPrice('gross') : __('On Request', 'casawp')),
				'timeSegment' => $this->getFieldValue('grossPrice_timesegment', false),
				'label' => __('Gross price', 'casawp')
			);

			$price = $this->getFieldValue('netPrice', false);
			$offer_array['netPrice'] = array(
				'value' =>  ($price ? $price : 0),
				'rendered' => ($price ? $this->renderPrice('net') : __('On Request', 'casawp')),
				'timeSegment' => $this->getFieldValue('netPrice_timesegment', false),
				'label' => __('Net price', 'casawp')
			);
		}

		//essential relations
		$offer_array['categories'] = $this->getCategoriesArray();
		$offer_array['numvals'] = $this->getNumvalsArray();
		$offer_array['features'] = $this->getFeaturesArray();

		$offer_array['images'] = $this->getImagesArray();

		return $offer_array;
	}

	public function address_to_array(){
		//address
		$prefix = 'address';
		$address = array();
		$address['street'] = $this->getFieldValue($prefix.'_streetaddress') . ' ' . $this->getFieldValue($prefix.'_streetnumber');
		$address['postalcode'] = $this->getFieldValue($prefix.'_postalcode');
		$address['locality'] = $this->getFieldValue($prefix.'_locality');
		$address['country'] = $this->getFieldValue($prefix.'_country');

		if (class_exists('Locale') && $this->getFieldValue($prefix.'_country')) {
			$address['country_locale'] = \Locale::getDisplayRegion('-'.$this->getFieldValue($prefix.'_country'), get_bloginfo('language'));
		} elseif($this->getFieldValue($prefix.'_country')) {
			switch ($this->getFieldValue($prefix.'_country')) {
				case 'CH': $address['country_locale'] = __('Switzerland', 'casawp'); break;
				case 'DE': $address['country_locale'] = __('Germany', 'casawp'); break;
				case 'AT': $address['country_locale'] = __('Austria', 'casawp'); break;
				case 'IT': $address['country_locale'] = __('Italy', 'casawp'); break;
				case 'FR': $address['country_locale'] = __('France', 'casawp'); break;
				default: $address['country_locale'] = $this->getFieldValue($prefix.'_country'); break;
			}
		}

		$address['lng'] = $this->getFieldValue('property_geo_longitude');
		$address['lat'] = $this->getFieldValue('property_geo_latitude');

		array_walk($address, function(&$value){$value = trim($value);});
		$address = array_filter($address);
		return $address;
	}


	/*====================================
	=            Data Getters            =
	====================================*/
	
	public function getTitle(){
		return $this->post->post_title;
	}

	public function getCategories(){
		if ($this->categories === null) {
			$terms = wp_get_post_terms( $this->post->ID, 'casawp_category', array("fields" => "all"));

			$c_trans = null;
			$lang = substr(get_bloginfo('language'), 0, 2);
			foreach ($terms as $term) {
				$termSlug = $term->slug;
				$termName = $term->name;
				if ($this->categoryService->keyExists($termSlug)) {
					$this->categories[] = $this->categoryService->getItem($termSlug);
				} else if ($this->utilityService->keyExists($termSlug)) {
					$this->categories[] = $this->utilityService->getItem($termSlug);
				} else {
					$unknown_category = new \CasasoftStandards\Service\Category();
					$unknown_category->setKey($termSlug);
					$unknown_category->setLabel($termName);	
					if ($c_trans === null) {
						$c_trans = maybe_unserialize(get_option('casawp_custom_category_translations'));
						if (!$c_trans) {
							$c_trans = array();
						}
					}
					foreach ($c_trans as $key => $trans) {
						if ($key == $termName && array_key_exists($lang, $trans)) {
							$unknown_category->setLabel($trans[$lang]);	
						}
					}
					
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

	public function getAvailability() {
		if ($this->availability === null) {
			$terms = wp_get_post_terms( $this->post->ID, 'casawp_availability', array("fields" => "names"));
			$this->availability = isset($terms[0]) ? $terms[0] : false;
		}
		return $this->availability;
	}

	public function isReference() {
		if ($this->getAvailability() == "reference") return true;
		return false;
	}

	public function getAvailabilityLabel(){
		if ($this->getAvailability()) {
			switch ($this->getAvailability()) {
      			case 'reserved': return __('Reserved', 'casawp'); break;
      			case 'active': return __('Available', 'casawp'); break;
				case 'reference': return __('Reference', 'casawp'); break;
				case 'private': return __('Private', 'casawp'); break;
				case 'reference': return __('Reference', 'casawp'); break;
      			case 'taken':
      				if ($this->getSalestype() == 'rent')
      					return __('Rented', 'casawp');
      				if ($this->getSalestype() == 'buy')
      					return __('Sold', 'casawp');
      				break;
				
			}
		}
		return '';
	}

	public function getSalestype(){
		if ($this->salestype === null) {
			$types = get_the_terms( $this->post->ID, 'casawp_salestype' );
			if ($types) {
				$type = array_pop($types);
				$this->salestype = $type->slug;
			} else {
				$this->salestype = false;
			}
		}
		return $this->salestype;
	}

    public function getUtility($key){
        foreach ($this->getUtilities() as $utility) {
            if ($utility->getKey() == $key) {
                return $utility;
            }
        }
    }

    public function getUtilities(){
    	if ($this->utilities === null) {
    		$this->utilities = array();
			$terms = wp_get_post_terms( $this->post->ID, 'casawp_utility', array("fields" => "names"));
			foreach ($terms as $termName) {
				if ($this->utilityService->keyExists($termName)) {
					$this->utilities[] = $this->utilityService->getItem($termName);
				} else {
					$unknown_utility = new \CasasoftStandards\Service\Utility();
					$unknown_utility->setKey($termName);
					$unknown_utility->setLabel('?'.$termName);
					$this->utilities[] = $unknown_utility;
				}
			}
		}
		if ($this->utilities != null) {
			usort($this->utilities, array($this, "sortByLabel"));
		}
		

		return $this->utilities;
    }

    public function getFeature($key){
        foreach ($this->getFeatures() as $numval) {
            if ($numval->getKey() == $key) {
                return $numval;
            }
        }
    }

    private function sortByLabel($a, $b){
    	return strcmp($a->getLabel(), $b->getLabel());
    }

    public function getFeatures(){
    	if ($this->features === null) {
    		$this->features = array();
			$terms = wp_get_post_terms( $this->post->ID, 'casawp_feature', array("fields" => "names"));
			foreach ($terms as $termName) {
				if ($this->featureService->keyExists($termName)) {
					$this->features[] = $this->featureService->getItem($termName);
				} else {
					$unknown_feature = new \CasasoftStandards\Service\Feature();
					$unknown_feature->setKey($termName);
					$unknown_feature->setLabel('?'.$termName);
					$this->features[] = $unknown_feature;
				}
			}
		}

		usort($this->features, array($this, "sortByLabel"));

		return $this->features;
    }

    public function getFeaturesArray(){
		$features = $this->getFeatures();
		$arr_features = array();
		foreach ($features as $feature) {
			$arr_features[] = array(
				'key' => $feature->getKey(),
				'label' => $feature->getLabel()
			);
		}
		return $arr_features;
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

	public function getNumvalsArray(){
		$numvals = $this->getNumvals();
		$arr_numvals = array();
		foreach ($numvals as $numval) {
			$arr_numvals[] = array(
				'key' => $numval->getKey(),
				'label' => $numval->getLabel(),
				'value' => $numval->getValue(),
				'rendered' => $this->renderNumvalValue($numval)
			);
		}
		return $arr_numvals;
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
              'taxonomy'                 => 'casawp_attachment_type',
	          //'casawp_attachment_type' => 'image',
	          'orderby'                  => 'menu_order',
	          'order'                    => 'ASC'
	        ) );
		}
		return $this->attachments;
	}

	public function getImages(){
		$images = array();
		foreach ($this->getAttachments() as $attachment) {
			if(has_term( 'image', 'casawp_attachment_type', $attachment )){
				$images[] = $attachment;
			}
		}
		return $images;
	}

	public function getImagesArray(){
		$images = $this->getImages();
		$images_array = array();
		foreach ($images as $i => $image){
			$image_array = array();
            $img     = wp_get_attachment_image( $image->ID, 'full', true, array('class' => 'casawp-image') );
            $img_url = wp_get_attachment_image_src( $image->ID, 'full' );
            $img_medium     = wp_get_attachment_image( $image->ID, 'medium', true, array('class' => 'casawp-image casawp-image-medium') );
            $img_medium_url = wp_get_attachment_image_src( $image->ID, 'medium' );
        	
        	$image_array['full_src'] = $img_url[0];
        	$image_array['full_html'] = $img;
        	$image_array['medium_src'] = $img_medium_url[0];
        	$image_array['medium_html'] = $img_medium;
        	$image_array['caption'] = $image->post_excerpt;
        	
        	$images_array[] = $image_array;
        }

        return $images_array;
	}

	public function getDocuments(){
		$docs = array();
		foreach ($this->getAttachments() as $attachment) {
			if(has_term( 'document', 'casawp_attachment_type', $attachment )){
				$docs[] = $attachment;
			}
		}
		return $docs;
	}

	public function getSalesBrochures(){
		$docs = array();
		foreach ($this->getAttachments() as $attachment) {
			if(has_term( 'sales-brochure', 'casawp_attachment_type', $attachment )){
				$docs[] = $attachment;
			}
		}
		return $docs;
	}

	public function getPlans(){
		$docs = array();
		foreach ($this->getAttachments() as $attachment) {
			if(has_term( 'plan', 'casawp_attachment_type', $attachment )){
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
				$value = $this->getFieldValue('property_'.$key, $fallback);
				break;
			default:
				$value = $this->getMeta($key);
				break;
		}
		if ($value) {
			return $value;
		} else {
			return $fallback;
		}
	}

    private function getSingleDynamicFields() {
        return array(
          'casawp_single_show_number_of_rooms',
          'casawp_single_show_area_sia_nf',
          'casawp_single_show_area_nwf',
          'casawp_single_show_area_bwf',
          'casawp_single_show_surface_property',
          'casawp_single_show_floor',
          'casawp_single_show_number_of_floors',
          'casawp_single_show_year_built',
          'casawp_single_show_year_renovated',
          'casawp_single_show_availability'
        );
    }

    private function getArchiveDynamicFields() {
        return array(
            'casawp_archive_show_street_and_number',
            'casawp_archive_show_location',
            'casawp_archive_show_number_of_rooms',
            'casawp_archive_show_area_sia_nf',
            'casawp_archive_show_area_bwf',
            'casawp_archive_show_surface_property',
            'casawp_archive_show_floor',
            'casawp_archive_show_number_of_floors',
            'casawp_archive_show_year_built',
            'casawp_archive_show_year_renovated',
            'casawp_archive_show_price',
            'casawp_archive_show_excerpt',
            'casawp_archive_show_availability'
        );
    }

	public function getPrimarySingleDatapoints(){
		if ($this->single_dynamic_fields === null) {
			
	        $values_to_display = array();
	        $i = 1000;
	        foreach ($this->getSingleDynamicFields() as $value) {
	          if(get_option($value, false)) {
	          	switch ($value) {
					case 'casawp_single_show_number_of_rooms': $key = 'number_of_rooms'; break;
		          	case 'casawp_single_show_area_sia_nf': $key = 'area_sia_nf'; break;
		          	case 'casawp_single_show_area_nwf': $key = 'area_nwf'; break;
		          	case 'casawp_single_show_area_bwf': $key = 'area_bwf'; break;
		          	case 'casawp_single_show_surface_property': $key = 'area_sia_gsf'; break;
		          	case 'casawp_single_show_floor': $key = 'floor'; break;
		          	case 'casawp_single_show_number_of_floors': $key = 'number_of_floors'; break;
		          	case 'casawp_single_show_year_built': $key = 'year_built'; break;
		          	case 'casawp_single_show_year_renovated': $key = 'year_last_renovated'; break;
		          	case 'casawp_single_show_availability': $key = 'special_availability'; break;
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

    //depricated: redirected to standard method
    public function renderQuickInfosTable() {
		$html  = '<table class="table"><tbody>';
    	$html .=  $this->renderDatapoints('archive', array());
    	$html .= '</tbody></table>';
    	return $html;
    }

    public function getExtraCosts(){
    	$extra_costs = $this->getFieldValue('extraPrice', false);
    	if ($extra_costs) {
    		return maybe_unserialize($extra_costs);
    	}
    	return array();
    }

    public function getIntegratedOffers(){
    	$offers = $this->getFieldValue('integratedoffers', false);
    	if (empty($offers)) return NULL;
    	
    	if ($offers) {
    		$offers = maybe_unserialize($offers);
    	}

    	//group em
    	$f_offers = array();
    	$check_keys = array('type', 'price', 'timesegment', 'propertysegment', 'currency', 'frequency', 'inclusive');

    	foreach ($offers as $offer) {
    		$found = false;
    		foreach ($f_offers as $f_key => $f_offer) {
    			$check_a = array_intersect_key($f_offer, array_flip($check_keys));
    			$check_b = array_intersect_key($offer, array_flip($check_keys));
    			asort($check_a);
    			asort($check_b);
    			if ($check_a == $check_b) {
    				$found = $f_key;
    				break;
    			}
    		}
    
    		if ($found) {
    			$f_offers[$found]['count'] = (isset($f_offers[$found]['count']) ? $f_offers[$found]['count'] : 1) + 1;
    		} else {
    			$offer['count'] = 1;
    			$f_offers[] = $offer;
    		}
    	}

    	$r_offers = array();
    	foreach ($f_offers as $offer) {
    		if ($this->integratedOfferService->keyExists($offer["type"])) {
    			$r_offer = $this->integratedOfferService->getItem($offer["type"]);
    			$r_offer->setCost($offer["price"]);
    			$r_offer->setTimesegment($offer["timesegment"]);
    			$r_offer->setPropertysegment($offer["propertysegment"]);
    			$r_offer->setInclusive($offer["inclusive"]);
    			$r_offer->setCount($offer["count"]);
    			$r_offers[] = $r_offer;
    		} else {
    			$unknown_offer = new \CasasoftStandards\Service\IntegratedOffer();
    			$unknown_offer->setKey($offer["type"]);
    			$unknown_offer->setLabel('?'.$offer["type"]);
    			$r_offers[] = $unknown_offer;
    		}
    	}
    	return $r_offers;
    }

    public function getUrls(){
    	$value = $this->getFieldValue('the_urls', array());
    	$value = maybe_unserialize($value);
    	return $value;
    }

    public function getExcerpt(){
    	return apply_filters( 'get_the_excerpt', $this->post->post_excerpt );
    }

    public function getShareWidgetsScripts() {
      $return = null;
      if (get_option( 'casawp_share_facebook', false )) {
        $return .= '<div id="fb-root"></div><script>(function(d, s, id) {'
          .'var js, fjs = d.getElementsByTagName(s)[0];'
          .'if (d.getElementById(id)) return;'
          .'js = d.createElement(s); js.id = id;'
          .'js.src = "//connect.facebook.net/' . str_replace('-','_',get_bloginfo('language')) . '/all.js#xfbml=1";'
          .'fjs.parentNode.insertBefore(js, fjs);'
        ."}(document, 'script', 'facebook-jssdk'));</script>";
      }
      return print_r($return);
    }

    /*===========================================
    =          Direct renders actions           =
    ===========================================*/

	public function renderNumvalValue($numval){
		switch ($numval->getSi()) {
			case 'm3': return $numval->getValue() .' m<sup>3</sup>'; break;
			case 'm2': return $numval->getValue() .' m<sup>2</sup>'; break;
			case 'm':  return $numval->getValue() .' m'; break;
			case 'kg':  return $numval->getValue() .' kg'; break;
			case '%':  return $numval->getValue() .' %'; break;
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
		$timeSegment = $this->getFieldValue($meta_prefix.'_timesegment', false);

		if (!$timeSegment) {
			if ($type == 'rent') {
				$timeSegment = 'm';
			}
		}

		global $casawp;
		$render = $casawp->renderPrice($value, $currency, $propertySegment, $timeSegment);
		if ($render) {
			return $render;
		} else {
			return __('On Request', 'casawp');	
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
	    	$return = __('On Request', 'casawp');  
	    } else {
	    	$return = __('Immediate' ,'casawp');
	    }
	      
	    return $return;
	}

	public function renderCategoryLabels(){
		$cat_labels = array();
		$categories = $this->getCategories();
		if ($categories) {
			foreach ($categories as $category) {
				$cat_labels[] = $category->getLabel();
			}
			return implode(', ', $cat_labels);
		}
	}

	public function renderUtilityLabels(){
		$util_labels = array();
		$utilities = $this->getUtilities();
		if ($utilities) {
			foreach ($utilities as $utility) {
				$util_labels[] = $utility->getLabel();
			}
			return implode(', ', $util_labels);
		}
	}

	public function renderDatapoints($context = 'single', $args = array()){
		if ($context == 'single') {
			$datapoints = $this->getPrimarySingleDatapoints();
			$defaults = array(
				'pattern_1' => '{{label}}: {{value}}<br>',
				'pattern_2' => '{{value}}<br>'
			);
		} else {
			$datapoints = $this->getPrimaryArchiveDatapoints();
			$defaults = array(
				'pattern_1' => '<tr><th>{{label}}</th><td>{{value}}</td></tr>',
				'pattern_2' => '<tr><td colspan="2">{{value}}</td></tr>'
			);
		}

		$args = array_merge($defaults, $args);

		$html = '';
		foreach ($datapoints as $datapoint){
	        $field = str_replace('casawp_'.$context.'_show_', '', $datapoint);
	        switch ($field) {
	          case 'street_and_number':
	            if ($this->getFieldValue('property_address_streetaddress')):
		            $point = str_replace('{{label}}', __('Street', 'casawp'), $args['pattern_1']);
		            $html .= str_replace('{{value}}', trim($this->getFieldValue('property_address_streetaddress') . ' ' . $this->getFieldValue('property_address_streetnumber')), $point);
	            endif;
	            break;
	          case 'location':
	            $point = str_replace('{{label}}', __('Locality', 'casawp'), $args['pattern_1']);
	            $html .= str_replace('{{value}}', trim($this->getFieldValue('address_postalcode') . ' ' . $this->getFieldValue('address_locality')), $point);
	            break;
	          case 'surface_property':
	            $numval = $this->getNumval('area_sia_gsf');
	            if ($numval) {
	              $point = str_replace('{{label}}', $numval->getLabel(), $args['pattern_1']);
	              $html .= str_replace('{{value}}', $this->renderNumvalValue($numval), $point);
	            }
	            break;
	          case 'price':
	            if ($this->getAvailability() != 'reference') {
	              if ($this->getSalestype() == 'buy') {
	              	if ($this->getFieldValue('price', false)) {
	              		$point = str_replace('{{label}}', __('Sales price', 'casawp'), $args['pattern_1']);
	              		$html .= str_replace('{{value}}', $this->renderPrice(), $point);
	              	} else {
	              		$point = str_replace('{{label}}', __('Sales price', 'casawp'), $args['pattern_1']);
	              		$html .= str_replace('{{value}}', __('On Request', 'casawp'), $point);
	              	}
	              }
	              if ($this->getSalestype() == 'rent') {
  	                if ($this->getFieldValue('grossPrice', false)) {
  	                  $point = str_replace('{{label}}', __('Gross price', 'casawp'), $args['pattern_1']);
  	                  $html .= str_replace('{{value}}', $this->renderPrice('gross'), $point);
  	                }
  	                if ($this->getFieldValue('netPrice', false)) {
  	                  $point = str_replace('{{label}}', __('Net price', 'casawp'), $args['pattern_1']);
  	                  $html .= str_replace('{{value}}', $this->renderPrice('net'), $point);
  	                }
  	                if (!$this->getFieldValue('grossPrice', false) && !$this->getFieldValue('netPrice', false)) {
  	                	$point = str_replace('{{label}}', __('Rent price', 'casawp'), $args['pattern_1']);
  	                	$html .= str_replace('{{value}}', __('On Request', 'casawp'), $point);
  	                }
  	              }
	            }
	            break;
	          case 'excerpt':
	            $html .= str_replace('{{value}}', $this->getExcerpt(), $args['pattern_2']);
	            break;
	          case 'availability':
	          case 'special_availability':
	            if ($this->getAvailability() != 'reference') {
	              $value = $this->renderAvailabilityDate();
	              if ($value) {
	                $point = str_replace('{{label}}', __('Available from','casawp'), $args['pattern_1']);
	                $html .= str_replace('{{value}}', $value, $point); 
	              }
	            }
	            break;
	          default:
	            $numval = $this->getNumval($field);
	            if ($numval) {
	              $point = str_replace('{{label}}', $numval->getLabel(), $args['pattern_1']);
	              $html .= str_replace('{{value}}', $this->renderNumvalValue($numval), $point);
	            }
	          
	            break;
	        }

	      } 

	    return $html;
	}





	/*****/

	public function getSimilarPropertiesByPrice($ppp = 4) {
		if ($this->similar_properties_by_location == null) {
			
			$args = array(
				'posts_per_page' => -1,
				'offset'         => 0,
				'post_type'      => 'casawp_property',
				'post_status'    => 'publish',
				'meta_key'       => 'priceForOrder',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC'
			);
			$posts = get_posts( $args );

			$current_property_key = 0;

			

			foreach ($posts as $key => $value) {
				if ($value->ID == $this->post->ID) {
					$current_property_key = $key;
				}
			}

			$l = $current_property_key--;
			$r = $current_property_key++;

			$i = 0;
			$result = array();
			for($i < count($posts)*2) {
				if (array_key_exists($l, $posts) && $i < $ppp) {
					$result[] = $posts[$l];
					$i++;
				}
				if (array_key_exists($r, $posts) && $i < $ppp) {
					$result[] = $posts[$r];
					$i++;
				}
			}


			echo '<pre>';
			print_r($posts);
			echo '</pre><hr>';

			echo '<pre>';
			print_r($result);
			echo '</pre>';

			// order by price

			//$this->similar_properties_by_location = $result;
		}
		return $this->similar_properties_by_location;
	}
	public function getSimilarPropertiesByLocation($ppp = 4) {
		
	}

	public function renderSimilarProperties(){
		return $this->render('similar-properties', array(
			'offer' => $this
		));
	}
	/*****/


	/*======================================
	=            Render Actions            =
	======================================*/

	public function render($view, $args = array()){
		global $casawp;
		return $casawp->render($view, $args);
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

	public function renderGalleryThumbnails(){
		$images = $this->getImages();
		return $this->render('gallery-thumbnails', array(
			'images' => $images,
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
			'offer' => $this,
		));
	}

	public function renderAvailabilityLabel(){
		return $this->render('availability-label', array(
			'offer' => $this,
		));
	}

	public function renderAllShareWidgets() {
		add_action('wp_footer', array($this, 'getShareWidgetsScripts'), 30);
		return $this->render('share-widget');
	}

	public function sanitizeContactFormPost($post){
		$data = array();
		foreach ($post as $key => $value) {
			switch ($key) {
				default:
					$data[$key] = sanitize_text_field($value);
					break;
			}
		}
		return $data;
	}

	public function renderContactForm(){
		if ($this->getAvailability() == 'reference') {
	        return false;
	    }
        $form = new \casawp\Form\ContactForm();
        $sent = false;
        $customerid = get_option('casawp_customerid');
        $publisherid = get_option('casawp_publisherid');
        $email = get_option('casawp_email_fallback');

        if ($this->getFieldValue('seller_org_customerid', false)) {
        	$customerid = $this->getFieldValue('seller_org_customerid', false);
        }
        if ($this->getFieldValue('seller_inquiry_person_email', false)) {
        	$email = $this->getFieldValue('seller_inquiry_person_email', false);
        }
        
        if (get_option('casawp_inquiry_method') == 'casamail') {
        	//casamail
        	if (!$customerid || !$publisherid) {
        		return '<p class="alert alert-danger">CASAMAIL MISCONFIGURED: please define a provider and publisher id <a href="/wp-admin/admin.php?page=casawp&tab=contactform">here</a></p>';
        	}
        	
        } else {
        	if (!$email) {
        		return '<p class="alert alert-danger">EMAIL MISCONFIGURED: please define a email address <a href="/wp-admin/admin.php?page=casawp&tab=contactform">here</a></p>';
        	}
        }

        if ($_POST) {
        	$postdata = $this->sanitizeContactFormPost($_POST);
        	$filter = $form->getFilter();
	        $form->setInputFilter($filter);
        	$form->setData($postdata);
        	if ($form->isValid()) {
			    $validatedData = $form->getData();
			    $sent = true;
			    if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'send-inquiry')) {
			    	echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
			    	print_r('NONCE ISSUE BITTE MELDEN');
			    	echo "</textarea>";
			    	//SPAM
			    } else if (isset($postdata['email']) && $postdata['email']) {
			    	//SPAM
			    } else {
			    	//add to WP for safekeeping
			    	$post_title = wp_strip_all_tags($form->get('firstname')->getValue() . ' ' . $form->get('lastname')->getValue() . ': [' . ($this->getFieldValue('referenceId') ? $this->getFieldValue('referenceId') : $this->getFieldValue('casawp_id')) . '] ' . $this->getTitle());
			    	$post = array(
			    		'post_type' => 'casawp_inquiry',
			    		'post_content' => $form->get('message')->getValue(),
			    		'post_title' => $post_title,
			    		'post_status' => 'private',
			    		'ping_status' => false
			    	);
			    	$inquiry_id = wp_insert_post($post);
			    	foreach ($form->getElements() as $element) {
			    		if (!in_array($element->getName(), array('message')) ) {
			    			add_post_meta($inquiry_id, 'sender_' . $element->getName(), $element->getValue(), true );
			    		}
			    	}
			    	add_post_meta($inquiry_id, 'casawp_id', $this->getFieldValue('casawp_id'), true );
			    	add_post_meta($inquiry_id, 'referenceId', $this->getFieldValue('referenceId'), true );



					if (get_option('casawp_inquiry_method') == 'casamail') {
						//casamail
						$data = $postdata;
						$data['email'] = $postdata['emailreal'];
						$data['provider'] = $customerid;
						$data['publisher'] = $publisherid;
						$data['lang'] = substr(get_bloginfo('language'), 0, 2);
						$data['property_reference'] = $this->getFieldValue('referenceId');


						$data['property_street'] = $this->getFieldValue('address_streetaddress');
						$data['property_postal_code'] = $this->getFieldValue('address_postalcode');
						$data['property_locality'] = $this->getFieldValue('address_locality');
						//$data['property_category'] = $this->getFieldValue('referenceId');
						$data['property_country'] = $this->getFieldValue('address_country');
						//$data['property_rooms'] = $this->getFieldValue('referenceId');
						//$data['property_type'] = $this->getFieldValue('referenceId');
						//$data['property_price'] = $this->getFieldValue('referenceId');


						//direct recipient emails
						if (get_option('casawp_casamail_direct_recipient') && $this->getFieldValue('seller_inquiry_person_email', false)) {
							$data['direct_recipient_email'] = $this->getFieldValue('seller_inquiry_person_email', false);
						}
						$data_string = json_encode($data);                                                                                   
						                                                                                                                     
						$ch = curl_init('http://onemail.ch/api/msg');
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
						curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
						curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
						    'Content-Type: application/json',                                                                                
						    'Content-Length: ' . strlen($data_string))                                                                       
						);

						curl_setopt($ch, CURLOPT_USERPWD,  "casawp:MQX-2C2-Hrh-zUu");
						                                                                                                                     
						$result = curl_exec($ch);
						$json = json_decode($result, true);
						if (isset($json['validation_messages'])) {
							wp_mail( 'js@casasoft.ch', 'casawp casamail issue', print_r($json['validation_messages'], true));
							return '<p class="alert alert-danger">'.print_r($json['validation_messages'], true).'</p>';
						}

						//header("Location: /anfrage-erfolg/");
						//die('SUCCESS');


			        } else {
			        	
			        }
			    }

			} else {
			    $messages = $form->getMessages();
			}
        } else {
        	$form->get('message')->setValue(__('I am interested concerning this property. Please contact me.','casawp'));
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
    	return $this->render('single-pagination', array(
    		'post_id' => $this->post->ID
    	));
    }

    public function renderIntegratedOffers(){
    	return $this->render('integrated-offers', array(
    		'offer' => $this,
    		'integratedOffers' => $this->getIntegratedOffers()
    	));
    }

}
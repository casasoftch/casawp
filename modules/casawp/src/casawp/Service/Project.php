<?php
namespace casawp\Service;

class Project{
	public $post = null;
    private $availability = null; //lazy
    private $attachments = null; //lazy
    private $documents = null; //lazy
    private $metas = null;  //lazy
    private $units = null; //lazy
    private $properties = null; //lazy

    private $projectService = null;

    public function __construct($service){
    	$this->projectService = $service;
    }

    function __get($name){
    	switch ($name) {
    		case 'utilityService':
    		case 'categoryService':
    		case 'numvalService':
    		case 'featureService':
    		case 'messengerService':
    			return $this->projectService->{$name};
    		break;

    		//deligate the rest to the Project Object
    		default:
    			return $this->post->{$name};
    			break;
    	}

    }

    //deligate all other methods to Project Object
	function __call($name, $arguments){
		switch ($name) {
			case 'render':
				return $this->projectService->{$name}($arguments[0], (isset($arguments[1]) ? $arguments[1] : array() ));
				break;
		}
		if (method_exists($this->post, $name)) {
			return $this->post->{$name}($arguments);
		}
	}

	public function getCurrent(){
		return $this->project;
	}





    private function resetPost(){
	    foreach (get_class_vars(get_class($this)) as $var => $def_val){
	        $this->$var= $def_val;
	    }
   	}

    public function setPost($post){
        $this->post = $post;
    }



	/*=======================================
	=            Array Functions            =
	========================================*/

	public function to_array() {
		$project_array = array(
			'post' => $this->post->to_array()
		);

		//basics
		$project_array['permalink'] = get_permalink($this->post);
		$project_array['address'] = $this->address_to_array();
		$project_array['salestype'] = $this->getSalestype();
		if ($this->getSalestype() == 'buy'){
			$price = $this->getFieldValue('price', false);
			$project_array['price'] = array(
				'value' => ($price ? $price : 0),
				'rendered' => ($price ? $this->renderPrice() : __('On Request', 'casawp')),
				'propertySegment' => $this->getFieldValue('price_propertysegment', false),
				'label' => __('Sales price', 'casawp')
			);
		} elseif($this->getSalestype() == 'rent') {
			$price = $this->getFieldValue('grossPrice', false);
			$project_array['grossPrice'] = array(
				'value' =>  ($price ? $price : 0),
				'rendered' => ($price ? $this->renderPrice('gross') : __('On Request', 'casawp')),
				'timeSegment' => $this->getFieldValue('grossPrice_timesegment', false),
				'label' => __('Gross price', 'casawp')
			);

			$price = $this->getFieldValue('netPrice', false);
			$project_array['netPrice'] = array(
				'value' =>  ($price ? $price : 0),
				'rendered' => ($price ? $this->renderPrice('net') : __('On Request', 'casawp')),
				'timeSegment' => $this->getFieldValue('netPrice_timesegment', false),
				'label' => __('Net price', 'casawp')
			);
		}

		//essential relations
		$project_array['categories'] = $this->getCategoriesArray();
		$project_array['numvals'] = $this->getNumvalsArray();
		$project_array['features'] = $this->getFeaturesArray();

		$project_array['images'] = $this->getImagesArray();

		return $project_array;
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

	public function isReserved() {
		if ($this->getAvailability() == "reserved") return true;
		return false;
	}

	public function isTaken() {
		if ($this->getAvailability() == "taken") return true;
		return false;
	}

	public function isActive() {
		if ($this->getAvailability() == "active") return true;
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

    public function getExcerpt(){
    	return apply_filters( 'get_the_excerpt', $this->post->post_excerpt );
    }


    /*===========================================
    =          Direct renders actions           =
    ===========================================*/


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


	/*======================================
	=            Render Actions            =
	======================================*/

	public function renderGallery(){
		$images = $this->getImages();
		return $this->render('gallery', array(
			'images' => $images,
			'project' => $this
		));
	}

	public function renderGalleryThumbnails(){
		$images = $this->getImages();
		return $this->render('gallery-thumbnails', array(
			'images' => $images,
		));
	}

	public function renderAddress(){
		return $this->render('address', array(
			'type' => 'property',
			'project' => $this
		));
	}


	public function renderFeaturedImage(){
		return $this->render('featured-image', array(
			'project' => $this,
		));
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

	//only for top level projects
	public function getUnits(){
		if (!$this->units) {
			$unit_posts = get_children( array(
				'post_parent' => $this->post->ID,
				'post_type'   => 'casawp_project',
				'numberposts' => -1,
				'post_status' => 'any'
			), OBJECT );
			$units = array();
			foreach ($unit_posts as $post) {
				$project = new Project($this);
				$project->setPost($post);
				$units[] = $project;
			}

			$this->units = $units;

		}

		return $this->units;
	}

	//only for sublevel projects/units
	public function getProperties(){
		global $casawp;
		if (!$this->properties) {
			$properties = array();
			$queryService = new \casawp\Service\QueryService;

			$the_query = $queryService->createWpQuery(array(
				'projectunit_id' => $this->post->ID,
				'availabilities' => 'reference, active, taken, reserved'
			));

			if ( $the_query->have_posts() ) {
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					$offer = $casawp->prepareOffer($the_query->post);
					$properties[] = $offer;
				}
				wp_reset_query();
			}
			$this->properties = $properties;
		}
		return $this->properties;
	}

	public function getProject(){

	}

	//active or reserved properties (maybe this should be done during import for PERFORMANCE reasons)
	public function hasAvailableProperties(){
		if (!$this->post->post_parent) {
			//its a project
			foreach ($this->getUnits() as $unit) {
				foreach ($unit->getProperties() as $offer) {
					if ($offer->getAvailability() == 'active' || $offer->getAvailability() == 'reserved') {
						return true;
					}
				}
			}
		} else {
			//its a unit
			foreach ($this->getProperties() as $offer) {
				if ($offer->getAvailability() == 'active' || $offer->getAvailability() == 'reserved') {
					return true;
				}
			}
		}
		return false;
	}

	public function renderContactForm(){
		$formResult = $this->formService->buildAndValidateContactForm($this);
        return $this->render('contact-form', array(
        	'form' => $formResult['form'],
        	'offer' => $this,
        	'sent' => $formResult['sent']
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



}

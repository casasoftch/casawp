<?php
namespace casawp\Service;

use CasasoftStandards\Service\CategoryService;
use CasasoftStandards\Service\NumvalService;
use CasasoftMessenger\Service\MessengerService;
use CasasoftStandards\Service\UtilityService;
use CasasoftStandards\Service\FeatureService;
use CasasoftStandards\Service\IntegratedOfferService;

use casawp\Service\FormService;

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
    private $salestype = null;  //lazy
    private $metas = null;  //lazy
    private $casawp = null;
    private $currentOffer = null;
    private $collection = array();

	private $utilityService;
	private $categoryService;
	private $numvalService;
	private $featureService;
	private $messengerService;
	private $integratedOfferService;
	private $formService;

	public function __construct(
		CategoryService $categoryService,
		NumvalService $numvalService,
		MessengerService $messengerService,
		UtilityService $utilityService,
		FeatureService $featureService,
		IntegratedOfferService $integratedOfferService,
		FormService $formService
	) {
		$this->utilityService = $utilityService;
		$this->categoryService = $categoryService;
		$this->numvalService = $numvalService;
		$this->featureService = $featureService;
		$this->messengerService = $messengerService;
		$this->integratedOfferService = $integratedOfferService;
		$this->formService = $formService;
	}

	public function getUtilityService() {
		return $this->utilityService;
	}

	public function getCategoryService() {
		return $this->categoryService;
	}

	public function getNumvalService() {
		return $this->numvalService;
	}

	public function getFeatureService() {
		return $this->featureService;
	}

	public function getMessengerService() {
		return $this->messengerService;
	}

	public function getIntegratedOfferService() {
		return $this->integratedOfferService;
	}

	public function getFormService() {
		return $this->formService;
	}


    //deligate all other gets to the Offer Object 
    function __get($name){
    	return $this->post->{$name};
    }

    //deligate all other methods to Offer Object
	function __call($name, $arguments){
		if (method_exists($this->currentOffer, $name)) {
			return $this->currentOffer->{$name}($arguments);
		}
	}

	public function getCurrent(){
		return $this->currentOffer;
	}

	public function setPost($post){
		if (array_key_exists($post->ID, $this->collection)) {
			$this->currentOffer = $this->collection[$post->ID];
			return true;
		}

		$offer = new Offer($this);
		$offer->setPost($post);
		$this->currentOffer = $offer;

		$this->collection[$post->ID] = $offer;
	}

	public function render($view, $args = array()){
		global $casawp;
		return $casawp->render($view, $args);
	}

}
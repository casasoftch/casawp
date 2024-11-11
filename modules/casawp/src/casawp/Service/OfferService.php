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

	private UtilityService $utilityService;
	private CategoryService $categoryService;
	private NumvalService $numvalService;
	private FeatureService $featureService;
	private MessengerService $messengerService;
	private IntegratedOfferService $integratedOfferService;
	private FormService $formService;

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
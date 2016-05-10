<?php
namespace casawp\Service;

class ProjectService{
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
    private $currentProject = null;
    private $collection = array();

    public function __construct($categoryService, $numvalService, $messengerService, $utilityService, $featureService, $integratedProjectService){
    	$this->utilityService = $utilityService;
    	$this->categoryService = $categoryService;
    	$this->numvalService = $numvalService;
        $this->featureService = $featureService;
    	$this->messengerService = $messengerService;
    	$this->integratedProjectService = $integratedProjectService;
    }


    //deligate all other gets to the Project Object 
    function __get($name){
    	return $this->post->{$name};
    }

    //deligate all other methods to Project Object
	function __call($name, $arguments){
		if (method_exists($this->currentProject, $name)) {
			return $this->currentProject->{$name}($arguments);
		}
	}

	public function getCurrent(){
		return $this->currentProject;
	}

	public function setPost($post){
		if (array_key_exists($post->ID, $this->collection)) {
			$this->currentProject = $this->collection[$post->ID];
			return true;
		}

		$project = new Project($this);
		$project->setPost($post);
		$this->currentProject = $project;

		$this->collection[$post->ID] = $project;
	}

	public function render($view, $args = array()){
		global $casawp;
		return $casawp->render($view, $args);
	}

}
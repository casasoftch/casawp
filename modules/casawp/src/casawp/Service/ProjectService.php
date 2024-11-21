<?php
namespace casawp\Service;

class ProjectService
{
	private $categoryService;
	private $numvalService;
	private $messengerService;
	private $utilityService;
	private $featureService;
	private $integratedProjectService;

	private $post = null;
	private $currentProject = null;
	private $collection = [];

	private $categories = null; // Lazy-loaded
	private $features = null; // Lazy-loaded
	private $utilities = null; // Lazy-loaded
	private $availability = null; // Lazy-loaded
	private $attachments = null; // Lazy-loaded
	private $documents = null; // Lazy-loaded
	private $singleDynamicFields = null; // Lazy-loaded
	private $archiveDynamicFields = null; // Lazy-loaded
	private $salesType = null; // Lazy-loaded
	private $metas = null; // Lazy-loaded
	private $casawp = null;

	public function __construct(
		$categoryService,
		$numvalService,
		$messengerService,
		$utilityService,
		$featureService,
		$integratedProjectService
	) {
		$this->categoryService = $categoryService;
		$this->numvalService = $numvalService;
		$this->messengerService = $messengerService;
		$this->utilityService = $utilityService;
		$this->featureService = $featureService;
		$this->integratedProjectService = $integratedProjectService;
	}

	/**
	 * Delegates property access to the current project object.
	 */
	public function __get($name)
	{
		return $this->post->{$name} ?? null;
	}

	/**
	 * Delegates method calls to the current project object.
	 */
	public function __call($name, $arguments)
	{
		if (method_exists($this->currentProject, $name)) {
			return call_user_func_array([$this->currentProject, $name], $arguments);
		}

		throw new \BadMethodCallException("Method {$name} does not exist on the current project.");
	}

	/**
	 * Gets the current project instance.
	 */
	public function getCurrent()
	{
		return $this->currentProject;
	}

	/**
	 * Sets the current post and initializes the project.
	 */
	public function setPost($post)
	{
		if (isset($this->collection[$post->ID])) {
			$this->currentProject = $this->collection[$post->ID];
			return;
		}

		$project = new Project($this);
		$project->setPost($post);
		$this->currentProject = $project;

		$this->collection[$post->ID] = $project;
	}

	/**
	 * Renders a view with arguments using the global Casawp instance.
	 */
	public function render($view, $args = [])
	{
		global $casawp;

		if (!is_callable([$casawp, 'render'])) {
			throw new \RuntimeException('Casawp render method is not available.');
		}

		return $casawp->render($view, $args);
	}
}

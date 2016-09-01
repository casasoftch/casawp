<?php 

namespace casawp\Form;

class DefaultFormSetting {
	public $id = 'gratis-bewertung';
	public $viewFile = 'contact-form';

	public function setAdditionalFields($form){
		return $form;
	}
	public function preCasaMailFilter($data, $postdata){
		return $data;
	}
	public function getView(){
		return $this->viewFile;
	}
	public function getId(){
		return $this->id;
	}
}
<?php
namespace casawp\Service;

class FormSettingService{
	public $settings = array();

	public function addFormSetting($object){
		$this->settings[] = $object;
	}

	public function getFormSetting($id){
		foreach ($this->settings as $setting) {
			if ($setting->getId() == $id) {
				return $setting;
			}
		}
		return null;
	}
}

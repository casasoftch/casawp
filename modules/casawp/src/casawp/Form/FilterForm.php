<?php
namespace casawp\Form;

use Zend\Form\Form;

class FilterForm extends Form
{
    public $categories = array();
    public $salestypes = array();
    public $locations = array();

    public function __construct($categories = array(), $salestypes = array(), $locations = array()){
        $this->categories = $categories;
        $this->salestypes = $salestypes;
        $this->locations = $locations;

        parent::__construct('filter');

        $this->setAttribute('method', 'GET');
        $this->setAttribute('action', '/immobilien/');

        if ($this->categories) {
            $category_options = $this->getCategoryOptions();
            $this->add(array(
                'name' => 'categories',
                'type' => 'Select',
                'attributes' => array(
                    'multiple' => 'multiple',
                ),
                'options' => array(
                    'label' => __('Category', 'casawp'),
                    'value_options' => $category_options,
                ),
            ));
        }
        if ($this->salestypes) {
            $salestype_options = $this->getSalestypeOptions();
            $this->add(array(
                'name' => 'salestypes',
                'type' => 'Select',
                'attributes' => array(
                    'multiple' => 'multiple',
                ),
                'options' => array(
                    'label' => __('Sales type', 'casawp'),
                    'value_options' => $salestype_options,
                ),
            ));
        }
        if ($this->locations) {
            $location_options = $this->getLocationOptions();
            $this->add(array(
                'name' => 'locations',
                'type' => 'Select',
                'attributes' => array(
                    'multiple' => 'multiple',
                ),
                'options' => array(
                    'label' => __('Location', 'casawp'),
                    'value_options' => $location_options,
                ),
            ));
        }
    }

    public function getCategoryOptions(){
        $category_options = array();
        foreach ($this->categories as $category) {
            $category_options[$category->getKey()] = $category->getLabel();
        }
        return $category_options;
    }

    public function getSalestypeOptions(){
        /*$salestype_options = array();
        foreach ($this->salestypes as $salestype) {
            $salestype_options[$salestype->getKey()] = $salestype->getLabel();
        }
        return $salestype_options;*/
        return $this->salestypes;
    }

    public function getLocationOptions(){
        $locations_workload = $this->locations;

        //not enough locations available
        if ($locations_workload <= 1) {
            return array();
        }

        $depth = 0;

        $parents = array();
        foreach ($locations_workload as $i => $location) {
            if ($location->parent == 0) {
                $parents[] = (array) $location;
                $depth = 1;
                unset($locations_workload[$i]);
            }
        }
        
        if ($depth == 1) {
            foreach ($parents as $u => $parent) {
                $children = array();
                foreach ($locations_workload as $i => $location) {
                    if ($location->parent == $parent['term_id']) {
                        $children[] = (array) $location;
                        $depth = 2;
                        unset($locations_workload[$i]);
                    }
                }
                $parents[$u]['children'] = $children;
            }
        }
        if ($depth == 2) {
            foreach ($parents as $u => $parent) {
                foreach ($parent['children'] as $c => $child) {
                    $grandchildren = array();
                    foreach ($locations_workload as $i => $location) {
                        if ($location->parent == $child['term_id']) {
                            $grandchildren[] = (array) $location;
                            $depth = 3;
                            unset($locations_workload[$i]);
                        }
                    }
                    $parents[$u]['children'][$c]['children'] = $grandchildren;
                }
            }
        }

        //ignore parent if parent is alone
        if (count($parents) == 1 && $depth > 1) {
            $parents = $parents[0]['children'];
            $depth = $depth - 1;
        }

        //(again) ignore parent if parent is alone
        if (count($parents) == 1 && $depth > 1) {
            $parents = $parents[0]['children'];
            $depth = $depth - 1;
        }

        //(again) (again) ignore parent if parent is alone
        if (count($parents) == 1 && $depth > 1) {
            $parents = $parents[0]['children'];
            $depth = $depth - 1;
        }

        //it there is only one parent ignore options
        if (count($parents) <= 1) {
            return array();
        }

        //build options array
        $options = array();

        if ($depth == 1) {
            foreach ($parents as $parent) {
                $options[$parent['slug']] = $parent['name'];
            } 
        } elseif ($depth == 2) {
            foreach ($parents as $parent) {
                $value_options = array();
                foreach ($parent['children'] as $child) {
                    $value_options[$child['slug']] = $child['name'];
                }
                $options[] = array(
                    'label' => $parent['name'],
                    'options' => $value_options
                );
            } 
        } elseif ($depth == 3){
            foreach ($parents as $parent) {
                $label = $parent['name'];
                foreach ($parent['children'] as $child) {
                    $label .= ' ' . $child['name'];
                    $value_options = array();
                    foreach ($child['children'] as $grandchild) {
                        $value_options[$grandchild['slug']] = $grandchild['name'];
                    }
                    $options[] = array(
                        'label' => $label,
                        'options' => $value_options
                    );
                }
            } 
        }

        return $options;
    }
}
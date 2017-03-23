<?php
namespace casawp\Form;

use Zend\Form\Form;

class FilterForm extends Form
{
    public $categories = array();
    public $salestypes = array();
    public $locations = array();
    public $availabilities = array();

    public function __construct($options, $categories = array(), $utilities = array(), $salestypes = array(), $locations = array(), $availabilities = array()){
        $this->options = $options;
        $this->categories = $categories;
        $this->utilities = $utilities;
        $this->salestypes = $salestypes;
        $this->locations = $locations;
        $this->availabilities = $availabilities;

        //set default options
        if (!$this->options['casawp_filter_rooms_from_elementtype']) {
          $this->options['casawp_filter_rooms_from_elementtype'] = 'hidden';
        }
        if (!$this->options['casawp_filter_rooms_to_elementtype']) {
          $this->options['casawp_filter_rooms_to_elementtype'] = 'hidden';
        }
        if (!$this->options['casawp_filter_price_from_elementtype']) {
          $this->options['casawp_filter_price_from_elementtype'] = 'hidden';
        }
        if (!$this->options['casawp_filter_price_to_elementtype']) {
          $this->options['casawp_filter_price_to_elementtype'] = 'hidden';
        }
        if (!$this->options['casawp_filter_utilities_elementtype']) {
          $this->options['casawp_filter_utilities_elementtype'] = 'hidden';
        }

        parent::__construct('filter');

        $this->setAttribute('method', 'GET');
        $this->setAttribute('action', '/immobilien/');


        if ($this->availabilities) {
            $this->add(array(
                'name' => 'availabilities',
                'type' => 'Select',
                'attributes' => array(
                    'multiple' => 'multiple',
                ),
                'options' => array(
                    'label' => __('Sales type', 'casawp'),
                    'value_options' => $this->getAvailabilityOptions(),
                ),
            ));
        }

        if ($this->salestypes) {
            $this->addSelector(
                'salestypes',
                __('Sales type', 'casawp'),
                __('Choose offer','casawp'),
                $this->getSalestypeOptions(),
                $this->options['chosen_salestypes']
            );

        }

        if ($this->categories) {
            $this->addSelector(
                'categories',
                __('Category', 'casawp'),
                __('Choose category','casawp'),
                $this->getCategoryOptions(),
                $this->options['chosen_categories']
            );
        }
        if ($this->utilities) {
            $this->addSelector(
                'utilities',
                __('Utility', 'casawp'),
                __('Choose utility','casawp'),
                $this->getUtilityOptions(),
                $this->options['chosen_utilities']
            );
        }
        if ($this->locations) {
            $this->addSelector(
                'locations',
                __('Location', 'casawp'),
                __('Choose locality','casawp'),
                $this->getLocationOptions(),
                $this->options['chosen_locations']
            );
        }
        //if ($this->rooms_from) {
            $this->addSelector(
                'rooms_from',
                __('Rooms from', 'casawp'),
                __('Rooms from','casawp'),
                $this->getRoomOptions(),
                $this->options['chosen_rooms_from']
            );
            $this->addSelector(
                'rooms_to',
                __('Rooms to', 'casawp'),
                __('Rooms to','casawp'),
                $this->getRoomOptions(),
                $this->options['chosen_rooms_to']
            );
        //}

        //if ($this->price_from) {
            $this->addSelector(
                'price_from',
                __('Price from', 'casawp'),
                __('Price from','casawp'),
                $this->getPriceOptions(),
                $this->options['chosen_price_from']
            );
            $this->addSelector(
                'price_to',
                __('Price to', 'casawp'),
                __('Price to','casawp'),
                $this->getPriceOptions(),
                $this->options['chosen_price_to']
            );
        //}
    }

    private function addSelector($name, $label, $emptyLabel, $value_options, $chosen_values = array()){

        /*<?php if (in_array(get_option('casawp_filter_categories_elementtype', false), ['multicheckbox', 'radio'])): ?>
            <?php echo $this->formLabel($form->get('categories')->setOptions(array('label_attributes' => array('class' => 'casawp-filterform-checkbox-label checkbox')))); ?>
            <?php echo $this->formElement($form->get('categories')->setAttribute('class', 'form-control form-control-multicheckbox')); ?>
        <?php else: ?>
            <?php echo $this->formLabel($form->get('categories')->setOptions(array('label_attributes' => array('class' => 'visible-xs casawp-filterform-label')))); ?>
            <?php echo $this->formElement($form->get('categories')->setAttribute('class', 'form-control chosen-select')->setAttribute('data-placeholder', __('Choose category','casawp'))); ?>
        <?php endif ?>*/

        if (count($chosen_values) > 1) {
            if ($this->options['casawp_filter_'.$name.'_elementtype'] == 'singleselect') {
                $this->options['casawp_filter_'.$name.'_elementtype'] = 'multiselect';
            }
            if ($this->options['casawp_filter_'.$name.'_elementtype'] == 'radio') {
                $this->options['casawp_filter_'.$name.'_elementtype'] = 'multicheckbox';
            }
        }

        switch ($this->options['casawp_filter_' . $name . '_elementtype']) {
            case 'singleselect':
                $this->add(array(
                    'name' => $name,
                    'type' => 'Select',
                    'options' => array(
                        'label' => $label,
                        'empty_option' => $emptyLabel,
                        'value_options' => $value_options,
                        'label_attributes' => array(
                            'class' => 'visible-xs casawp-filterform-label'
                        )
                    ),
                    'attributes' => array(
                        'class' => 'form-control form-control-singleselect chosen-select',
                        'data-placeholder' => $emptyLabel
                    )

                ));
                break;
            case 'multicheckbox':
                if (isset($value_options[0]['options'])) {
                    $flat_value_options = array();
                    foreach ($value_options as $group) {
                        foreach ($group['options'] as $key => $value) {
                            $flat_value_options[$key] = $value;
                        }
                    }
                    $value_options = $flat_value_options;
                }
                $this->add(array(
                    'name' => $name,
                    'type' => 'Zend\Form\Element\MultiCheckbox',
                    'options' => array(
                        'label' => $label,
                        'value_options' => $value_options,
                        'label_attributes' => array(
                            'class' => 'casawp-multicheckbox-label'
                        ),
                        'separator' => 'hello'
                    )
                ));
                break;
            case 'radio':
                if (isset($value_options[0]['options'])) {
                    $flat_value_options = array();
                    foreach ($value_options as $group) {
                        foreach ($group['options'] as $key => $value) {
                            $flat_value_options[$key] = $value;
                        }
                    }
                    $value_options = $flat_value_options;
                }
                $this->add(array(
                    'name' => $name,
                    'type' => 'Zend\Form\Element\Radio',
                    'options' => array(
                        'label' => $label,
                        'value_options' => $value_options,
                        'label_attributes' => array(
                            'class' => 'casawp-radio-label'
                        )
                    )
                ));
                break;
            case 'hidden':
                $this->add(array(
                    'name' => $name,
                    'type' => 'Zend\Form\Element\Hidden',
                    'options' => array(
                        'label' => $label,
                        'label_attributes' => array(
                            'class' => 'casawp-hidden'
                        )
                    )
                ));
                break;
            default: //case 'multiselect':
                $this->add(array(
                    'name' => $name,
                    'type' => 'Select',
                    'options' => array(
                        'label' => $label,
                        'value_options' => $value_options,
                        'label_attributes' => array(
                            'class' => 'visible-xs casawp-filterform-label'
                        )
                    ),
                    'attributes' => array(
                        'multiple' => 'multiple',
                        'class' => 'form-control form-control-multiselect chosen-select',
                        'data-placeholder' => $emptyLabel
                    )
                ));
                break;
        }
    }


    public function getCategoryOptions(){
        //TODO SORTING!!!
        $category_options = array();
        foreach ($this->categories as $category) {
            $category_options[$category->getKey()] = html_entity_decode($category->getLabel());
        }
        asort($category_options);
        return $category_options;
    }

    public function getUtilityOptions(){
        //TODO SORTING!!!
        $utility_options = array();
        foreach ($this->utilities as $utility) {
            $utility_options[$utility->getKey()] = html_entity_decode($utility->getLabel());
        }
        asort($utility_options);
        return $utility_options;
    }

    public function getSalestypeOptions(){
        /*$salestype_options = array();
        foreach ($this->salestypes as $salestype) {
            $salestype_options[$salestype->getKey()] = $salestype->getLabel();
        }
        return $salestype_options;*/
        asort($this->salestypes);
        return $this->salestypes;
    }


    public function getAvailabilityOptions(){
        asort($this->availabilities);
        return $this->availabilities;
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
                foreach ($parent['children'] as $child) {
                    $label = $parent['name'] . ' ' . $child['name'];
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

    public function getRoomOptions(){
        $options = array();
        for ($i=1; $i < 8.5; $i = $i+0.5) {
            $options[(string) $i] = $i;
        }
        return $options;
    }

    public function getPriceOptions(){
      $options = array();
      if (in_array('rent', $this->options['chosen_salestypes'])) {
        $options = array(
          500 => '500',
          600 => '600',
          700 => '700',
          800 => '800',
          900 => '900',
          1000 => '1\'000',
          1100 => '1\'100',
          1200 => '1\'200',
          1300 => '1\'300',
          1400 => '1\'400',
          1500 => '1\'500',
          1600 => '1\'600',
          1700 => '1\'700',
          1800 => '1\'800',
          1900 => '1\'800',
          2000 => '2\'000',
          2200 => '2\'200',
          2400 => '2\'400',
          2600 => '2\'600',
          2800 => '2\'800',
          3000 => '3\'000',
          3500 => '3\'500',
          4000 => '4\'000',
          4500 => '4\'500',
          5000 => '5\'000',
          5500 => '5\'500',
          6000 => '6\'000',
          7000 => '7\'000',
          8000 => '8\'000',
          9000 => '9\'000',
          10000 => '10\'000',
        );
      } else if (in_array('buy', $this->options['chosen_salestypes'])) {
        $options = array(
          50000 => '50\'000',
          100000 => '100\'000',
          150000 => '150\'000',
          200000 => '200\'000',
          300000 => '300\'000',
          400000 => '400\'000',
          500000 => '500\'000',
          600000 => '600\'000',
          700000 => '700\'000',
          800000 => '800\'000',
          900000 => '900\'000',
          1000000 => '1\'000\'000',
          1250000 => '1\'250\'000',
          2000000 => '2\'000\'000',
          2500000 => '2\'500\'000',
          3000000 => '3\'000\'000',
          4000000 => '4\'000\'000',
          5000000 => '5\'000\'000',
        );
      }
      return $options;
    }

    public function populateValues($data)
    {
        if (!is_array($data) && !$data instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable set of data; received "%s"',
                __METHOD__,
                (is_object($data) ? get_class($data) : gettype($data))
            ));
        }

        foreach ($this->iterator as $name => $elementOrFieldset) {
            $valueExists = array_key_exists($name, $data);

            if ($valueExists) {
                $value = $data[$name];
                if (
                    $name == 'salestypes' && in_array($this->options['casawp_filter_salestypes_elementtype'], ['singleselect', 'radio', 'hidden'])
                    ||
                    $name == 'categories' && in_array($this->options['casawp_filter_categories_elementtype'], ['singleselect', 'radio', 'hidden'])
                    ||
                    $name == 'utilities' && in_array($this->options['casawp_filter_utilities_elementtype'], ['singleselect', 'radio', 'hidden'])
                    ||
                    $name == 'locations' && in_array($this->options['casawp_filter_locations_elementtype'], ['singleselect', 'radio', 'hidden'])
                    ||
                    $name == 'rooms_from'
                    ||
                    $name == 'rooms_to'
                ) {
                    if ($data[$name] && is_array($data[$name])) {
                        $value = $data[$name][0];
                    } else if($data[$name]){
                      $value = $data[$name];
                    } else {
                        $value = '';
                    }
                }


                $elementOrFieldset->setValue($value);
            }
        }
    }

    /*public function populateValues($data, $onlyBase = false)
    {
        if ($onlyBase && $this->baseFieldset !== null) {
            $name = $this->baseFieldset->getName();
            if (array_key_exists($name, $data)) {
                if ($name == 'categories' && $options['casawp_filter_categories_elementtype'] == 'singleselect') {
                    $this->baseFieldset->populateValues($data[$name][0]);
                } else {
                    $this->baseFieldset->populateValues($data[$name]);
                }

            }
        } else {
            parent::populateValues($data);
        }
    }*/
}

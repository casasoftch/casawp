<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/*
  echo $this->casasoftConversion->setProperty(['array_of_property']);
  echo $this->casasoftConversion->getLabel('number_of_rooms');
  echo $this->casasoftConversion->getLabel('number_of_rooms', 'numeric_value');
  echo $this->casasoftConversion->getValue('number_of_rooms', 'numeric_value');
  echo $this->casasoftConversion->getValue('number_of_rooms');
  echo $this->casasoftConversion->getRenderedValue('number_of_rooms', 'numeric_value');
  echo $this->casasoftConversion->getRenderedValue('number_of_rooms');
  print_r($this->casasoftConversion->getList('key-facts'));
  var_dump($this->casasoftConversion->getList([['number_of_rooms', 'numeric_value'],['is-new', 'feature']]));
*/
class ConversionService {

    public $property = null;

    public function __construct($translator, $numvalService, $categoryService, $featureService, $utilityService){
        $this->translator = $translator;
        $this->numvalService = $numvalService;
        $this->categoryService = $categoryService;
        $this->featureService = $featureService;
        $this->utilityService = $utilityService;

        $this->setProperty([]);
    }

    private function transformPrice(Array $input, Array $output){

			$reqInputfields = ['value', 'property_segment', 'time_segment', 'area'];
			$reqOutputfields = ['property_segment', 'time_segment'];

			//tests
			foreach ($reqInputfields as $key) {
				if (!array_key_exists($key, $input) ) {
					return null;
				}
			}
			if (!$input['property_segment']) {
				$input['property_segment'] = 'all';
			}
			if (!$input['time_segment']) {
				$input['time_segment'] = 'infinite';
			}
			if (!is_numeric($input['value']) || !is_string($input['property_segment']) || !is_string($input['time_segment']) ) {
				return null;
			}
			if (!array_key_exists('property_segment', $output) || !array_key_exists('time_segment', $output) ) {
				return null;
			}
			if (!is_string($output['property_segment']) || !is_string($output['time_segment']) ) {
				return null;
			}


			$resulting_price = $input['value'];
			if ($output['property_segment'] == 'all' && $output['time_segment'] == 'infinite') {
				if ($input['time_segment'] != 'infinite') {
					return null;
				}
				if ($input['property_segment'] == 'm') {
					if (!$input['area']) {
						return null;
					}
					return $resulting_price * $input['area'];
				} else {
					return $resulting_price;
				}
			}
			if ($output['property_segment'] == 'm' && $output['time_segment'] == 'infinite') {
				if ($input['property_segment'] == 'all') {
					if (!$input['area']) {
						return null;
					}
					return $resulting_price / $input['area'];
				} else {
					return $resulting_price;
				}
			}


			if ($input['property_segment'] === $output['property_segment'] && $input['time_segment'] === $output['time_segment']) {
				return $resulting_price;
			}

			//normalize price to base month
			switch ($input['time_segment'] ) {
				case 'd': $resulting_price = $resulting_price * 30; break;
				case 'w': $resulting_price = $resulting_price * 4; break;
				case 'y': $resulting_price = $resulting_price / 12; break;
			}
			if ($input['property_segment'] == 'm' && $output['property_segment'] == 'all') {
				if (!$input['area']) {
					return null;
				}
				$resulting_price = $resulting_price * $input['area'];
			}
			if ($input['property_segment'] == 'all' && $output['property_segment'] == 'm') {
				if (!$input['area']) {
					return null;
				}
				$resulting_price = $resulting_price / $input['area'];

			}

			switch ($output['time_segment']) {
				case 'd': return $resulting_price / 30; break;
				case 'w': return $resulting_price / 4; break;
				case 'y': return $resulting_price * 12; break;
				case 'm': return $resulting_price; break;
			}

		}

    private function renderPrice($input){
      if (isset($input['price']) && $input['price']){
        if(!$input['time_segment']){
          //needs currencyFormat
          // $price = $this->currencyFormat($input['price'], 'CHF', false, 'de_CH') . '.–';
          $price = 'CHF '. $input['price'].'.–';
        }
        else if($input['time_segment']){
          switch ($input['time_segment']) {
            case 'w':
              $time = $this->translator->translate('Woche');
              break;
            case 'm':
              $time = $this->translator->translate('Monat');
              break;
            case 'y':
              $time = $this->translator->translate('Jahr');
              break;
          }

          if($input['property_segment'] == 'm'){
            $show_sqm = true;
          }

          if(isset($show_sqm) && $show_sqm){
            $price = 'CHF '. $input['price'].'.–' . " / m<sup>2</sup> / ".$time;
          }
          else{
            $price = 'CHF '. $input['price'].'.–' . " / ".$time;
          }
        }
      }
      return isset($price) ? $price : $this->translator->translate('Auf Anfrage');
    }


    public function setProperty(Array $data){
      $this->property = $data;

      if (isset($data['_embedded']['property'])) {
          $this->property = $data['_embedded']['property'];
      } elseif (isset($data['_embedded']['provider'])) {
        $this->property = $data;
      }

      //ensure
      if (!isset($this->property['features']) || !$this->property['features']) {
        $this->property['features'] = [];
      }
      if (!isset($this->property['numeric_values']) || !$this->property['numeric_values']) {
        $this->property['numeric_values'] = [];
      }

      //simplify
      if ($this->property['_embedded']['numeric_values']) {
        $this->property['numeric_values'] = $this->property['_embedded']['numeric_values'];
        unset($this->property['_embedded']['numeric_values']);
      }
      if ($this->property['_embedded']['features']) {
        $this->property['features'] = [];
        foreach ($this->property['_embedded']['features'] as $embfeature) {
          $this->property['features'][] = $embfeature['key'];
        }
        unset($this->property['_embedded']['features']);
      }
    }

    private function getCalculatedPrices(){
      $prices = [];
      $areas = $this->getList('areas');
      $area_seek = ['area_sia_gf', 'area_sia_nf', 'area_nwf', 'area_sia_gsf', 'volume_sia_gv'];
      foreach ($areas as $area) {
        if(in_array($area['key'], $area_seek) && $area['value'] != ''){
            $area = $area['value'];
            break;
        }
        else{
          $area = '';
        }
      }

      $price['price']['key'] = 'price';
      $price['price']['context'] = '';
      $price['price']['label'] = $this->getLabel('price');
      $price['price']['value'] = round($this->transformPrice([
  			'value' => $this->property['price'],
  			'property_segment' => $this->property['price_property_segment'],
  			'time_segment' => 'infinite',
  			'area' => $area
  		], [
  			'property_segment' => 'all',
  			'time_segment' => 'infinite'
  		]));
      $price['price']['renderedValue'] = $this->renderPrice([
        'price' => $price['price']['value'],
        'property_segment' => 'all',
  			'time_segment' => 'infinite'
      ]);

      $price['pricePerSqm']['key'] = 'pricePerSqm';
      $price['pricePerSqm']['context'] = '';
      $price['pricePerSqm']['label'] = $this->getLabel('pricePerSqm');
  		$price['pricePerSqm']['value'] = round($this->transformPrice([
  			'value' => $this->property['price'],
  			'property_segment' => $this->property['price_property_segment'],
  			'time_segment' => 'infinite',
  			'area' => $area
  		], [
  			'property_segment' => 'm',
  			'time_segment' => 'infinite'
  		]));
      $price['pricePerSqm']['renderedValue'] = $this->renderPrice([
        'price' => $price['pricePerSqm']['value'],
        'property_segment' => 'm',
  			'time_segment' => 'infinite'
      ]);

      $price['priceBruttoPerSqmPerMonth']['key'] = 'priceBruttoPerSqmPerMonth';
      $price['priceBruttoPerSqmPerMonth']['context'] = '';
      $price['priceBruttoPerSqmPerMonth']['label'] = $this->getLabel('priceBruttoPerSqmPerMonth');
  		$price['priceBruttoPerSqmPerMonth']['value'] = round($this->transformPrice([
  			'value' => $this->property['gross_price'],
  			'property_segment' => $this->property['gross_price_property_segment'],
  			'time_segment' => $this->property['gross_price_time_segment'],
  			'area' => $area
  		], [
  			'property_segment' => 'm',
  			'time_segment' => 'm'
  		]));
      $price['priceBruttoPerSqmPerMonth']['renderedValue'] = $this->renderPrice([
        'price' => $price['priceBruttoPerSqmPerMonth']['value'],
        'property_segment' => 'm',
  			'time_segment' => 'm'
      ]);

      $price['priceBruttoPerSqmPerYear']['key'] = 'priceBruttoPerSqmPerYear';
      $price['priceBruttoPerSqmPerYear']['context'] = '';
      $price['priceBruttoPerSqmPerYear']['label'] = $this->getLabel('priceBruttoPerSqmPerYear');
  		$price['priceBruttoPerSqmPerYear']['value'] = round($this->transformPrice([
  			'value' => $this->property['gross_price'],
  			'property_segment' => $this->property['gross_price_property_segment'],
  			'time_segment' => $this->property['gross_price_time_segment'],
  			'area' => $area
  		], [
  			'property_segment' => 'm',
  			'time_segment' => 'y'
  		]));
      $price['priceBruttoPerSqmPerMonth']['renderedValue'] = $this->renderPrice([
        'price' => $price['priceBruttoPerSqmPerMonth']['value'],
        'property_segment' => 'm',
  			'time_segment' => 'y'
      ]);


      $price['priceBruttoTotalPerMonth']['key'] = 'priceBruttoTotalPerMonth';
      $price['priceBruttoTotalPerMonth']['context'] = '';
      $price['priceBruttoTotalPerMonth']['label'] = $this->getLabel('priceBruttoTotalPerMonth');
  		$price['priceBruttoTotalPerMonth']['value'] = round($this->transformPrice([
  			'value' => $this->property['gross_price'],
  			'property_segment' => $this->property['gross_price_property_segment'],
  			'time_segment' => $this->property['gross_price_time_segment'],
  			'area' => $area
  		], [
  			'property_segment' => 'all',
  			'time_segment' => 'm'
  		]));
      $price['priceBruttoTotalPerMonth']['renderedValue'] = $this->renderPrice([
        'price' => $price['priceBruttoTotalPerMonth']['value'],
        'property_segment' => 'all',
  			'time_segment' => 'm'
      ]);


      $price['priceBruttoTotalPerYear']['key'] = 'priceBruttoTotalPerYear';
      $price['priceBruttoTotalPerYear']['context'] = '';
      $price['priceBruttoTotalPerYear']['label'] = $this->getLabel('priceBruttoTotalPerYear');
  		$price['priceBruttoTotalPerYear']['value'] = round($this->transformPrice([
  			'value' => $this->property['gross_price'],
  			'property_segment' => $this->property['gross_price_property_segment'],
  			'time_segment' => $this->property['gross_price_time_segment'],
  			'area' => $area
  		], [
  			'property_segment' => 'all',
  			'time_segment' => 'y'
  		]));
      $price['priceBruttoTotalPerYear']['renderedValue'] = $this->renderPrice([
        'price' => $price['priceBruttoTotalPerYear']['value'],
        'property_segment' => 'all',
  			'time_segment' => 'y'
      ]);


      $price['priceNettoPerSqmPerMonth']['key'] = 'priceNettoPerSqmPerMonth';
      $price['priceNettoPerSqmPerMonth']['context'] = '';
      $price['priceNettoPerSqmPerMonth']['label'] = $this->getLabel('priceNettoPerSqmPerMonth');
  		$price['priceNettoPerSqmPerMonth']['value'] = round($this->transformPrice([
  			'value' => $this->property['gross_price'],
  			'property_segment' => $this->property['gross_price_property_segment'],
  			'time_segment' => $this->property['gross_price_time_segment'],
  			'area' => $area
  		], [
  			'property_segment' => 'm',
  			'time_segment' => 'm'
  		]));
      $price['priceNettoPerSqmPerMonth']['renderedValue'] = $this->renderPrice([
        'price' => $price['priceNettoPerSqmPerMonth']['value'],
        'property_segment' => 'm',
  			'time_segment' => 'm'
      ]);

      $price['priceNettoPerSqmPerYear']['key'] = 'priceNettoPerSqmPerYear';
      $price['priceNettoPerSqmPerYear']['context'] = '';
      $price['priceNettoPerSqmPerYear']['label'] = $this->getLabel('priceNettoPerSqmPerYear');
  		$price['priceNettoPerSqmPerYear']['value'] = round($this->transformPrice([
  			'value' => $this->property['gross_price'],
  			'property_segment' => $this->property['gross_price_property_segment'],
  			'time_segment' => $this->property['gross_price_time_segment'],
  			'area' => $area
  		], [
  			'property_segment' => 'm',
  			'time_segment' => 'y'
  		]));
      $price['priceNettoPerSqmPerMonth']['renderedValue'] = $this->renderPrice([
        'price' => $price['priceNettoPerSqmPerMonth']['value'],
        'property_segment' => 'm',
  			'time_segment' => 'y'
      ]);


      $price['priceNettoTotalPerMonth']['key'] = 'priceNettoTotalPerMonth';
      $price['priceNettoTotalPerMonth']['context'] = '';
      $price['priceNettoTotalPerMonth']['label'] = $this->getLabel('priceNettoTotalPerMonth');
  		$price['priceNettoTotalPerMonth']['value'] = round($this->transformPrice([
  			'value' => $this->property['gross_price'],
  			'property_segment' => $this->property['gross_price_property_segment'],
  			'time_segment' => $this->property['gross_price_time_segment'],
  			'area' => $area
  		], [
  			'property_segment' => 'all',
  			'time_segment' => 'm'
  		]));
      $price['priceNettoTotalPerMonth']['renderedValue'] = $this->renderPrice([
        'price' => $price['priceNettoTotalPerMonth']['value'],
        'property_segment' => 'all',
  			'time_segment' => 'm'
      ]);


      $price['priceNettoTotalPerYear']['key'] = 'priceNettoTotalPerYear';
      $price['priceNettoTotalPerYear']['context'] = '';
      $price['priceNettoTotalPerYear']['label'] = $this->getLabel('priceNettoTotalPerYear');
  		$price['priceNettoTotalPerYear']['value'] = round($this->transformPrice([
  			'value' => $this->property['gross_price'],
  			'property_segment' => $this->property['gross_price_property_segment'],
  			'time_segment' => $this->property['gross_price_time_segment'],
  			'area' => $area
  		], [
  			'property_segment' => 'all',
  			'time_segment' => 'y'
  		]));
      $price['priceNettoTotalPerYear']['renderedValue'] = $this->renderPrice([
        'price' => $price['priceNettoTotalPerYear']['value'],
        'property_segment' => 'all',
  			'time_segment' => 'y'
      ]);

  		$nullcheck = [
  			'price',
  			'pricePerSqm',
  			'priceBruttoPerSqmPerMonth',
  			'priceBruttoPerSqmPerYear',
  			'priceBruttoTotalPerMonth',
  			'priceBruttoTotalPerYear',
  			'priceNettoPerSqmPerMonth',
  			'priceNettoPerSqmPerYear',
  			'priceNettoTotalPerMonth',
  			'priceNettoTotalPerYear',
  		];
  		foreach ($nullcheck as $key) {
  			if (!$price[$key]) {
  				$price[$key] = null;
  			}
  		}
      return $price;
    }

    public $templates = [
      'key-facts' => [
          ['visualReferenceId', 'special'],
          ['categories', 'special'],
          ['start', 'special'],
          ['number_of_rooms', 'numeric_value'],
          ['number_of_bathrooms', 'numeric_value'],
          ['number_of_apartments','numeric_value'],
          ['number_of_floors','numeric_value'],
          ['year_built','numeric_value'],
          ['year_last_renovated','numeric_value'],
          ['condition','special'],
          ['ceiling_height','numeric_value'],
          ['volume_gva','numeric_value'],
          ['Wärmeerzeugung','special'],
          ['Wärmeverteilung','special'],
          ['granny-flat','category'],
          ['parcelNumbers','special'],
          ['Erschliessung','special'],
          ['Auflagen','Auflagen'],
          ['zoneTypes','special'],
          ['construction_utilization_number','numeric_value'],
          ['hall_height','numeric_value'],
          ['maximal_floor_loading','numeric_value'],
          ['carrying_capacity_crane','numeric_value'],
          ['carrying_capacity_elevator','numeric_value']
      ],
      'prices' => [
        ['price', 'special'],
        ['priceNettoPerSqm', 'renders'],
        ['priceBruttoTotalPerMonth', 'renders'],
        ['priceNettoPerSqmPerMonth', 'renders'],
        ['priceNettoPerSqmPerYear', 'renders'],
        ['priceNettoPerTotalPerMonth', 'renders'],
        ['priceNettoPerTotalPerYear', 'renders'],
        ['extraCosts', 'special'],
        ['has-rental-deposit-guarantee', 'feature'],
        ['rental_deposit', 'numeric_value']
      ]
    ];

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function getLabel($key, $context = 'smart'){
      if ($context == 'smart' || $context == 'special') {
        switch ($key) {
          case 'visualReferenceId': return $this->translator->translate('Reference no.', 'casasoft-standards'); break;
          case 'categories': return $this->translator->translate('Categories', 'casasoft-standards'); break;
          case 'start': return $this->translator->translate('Available from', 'casasoft-standards'); break;
          case 'condition': return $this->translator->translate('Condition', 'casasoft-standards'); break;
          case 'Wärmeerzeugung': return 'Wärmeerzeugung'; break;
          case 'Wärmeverteilung': return 'Wärmeverteilung'; break;
          case 'parcelNumbers': return $this->translator->translate('Plot no.', 'casasoft-standards'); break;
          case 'Erschliessung': return 'Erschliessung'; break;
          case 'zoneTypes': return $this->translator->translate('Zone type', 'casasoft-standards'); break;
          case 'key-facts': return $this->translator->translate('Key facts', 'casasoft-standards'); break;
          case 'areas': return $this->translator->translate('Areas', 'casasoft-standards'); break;
          case 'features': return $this->translator->translate('Features', 'casasoft-standards'); break;
          case 'price': return $this->translator->translate('Price', 'casasoft-standards'); break;
          case 'pricePerSqm': return $this->translator->translate('pricePerSqm', 'casasoft-standards'); break;
    			case 'priceBruttoPerSqmPerMonth': return $this->translator->translate('priceBruttoPerSqmPerMonth', 'casasoft-standards'); break;
    			case 'priceBruttoPerSqmPerYear': return $this->translator->translate('priceBruttoPerSqmPerYear', 'casasoft-standards'); break;
    			case 'priceBruttoTotalPerMonth': return $this->translator->translate('priceBruttoTotalPerMonth', 'casasoft-standards'); break;
    			case 'priceBruttoTotalPerYear': return $this->translator->translate('priceBruttoTotalPerYear', 'casasoft-standards'); break;
    			case 'priceNettoPerSqmPerMonth': return $this->translator->translate('priceNettoPerSqmPerMonth', 'casasoft-standards'); break;
    			case 'priceNettoPerSqmPerYear': return $this->translator->translate('priceNettoPerSqmPerYear', 'casasoft-standards'); break;
    			case 'priceNettoTotalPerMonth': return $this->translator->translate('priceNettoTotalPerMonth', 'casasoft-standards'); break;
    			case 'priceNettoTotalPerYear': return $this->translator->translate('priceNettoTotalPerYear', 'casasoft-standards'); break;
        }
      }

      if ($context == 'smart' || $context == 'numeric_value') {
        $numval = $this->numvalService->getItem($key);
        if ($numval) {return $numval->getLabel();}
      }

      if ($context == 'smart' || $context == 'feature') {
        $feature = $this->featureService->getItem($key);
        if ($feature) {return $feature->getLabel();}
      }

      if ($context == 'smart' || $context == 'category') {
        $category = $this->categoryService->getItem($key);
        if ($category) {return $category->getLabel();}
      }

      if ($context == 'smart' || $context == 'utility') {
        $utility = $this->utilityService->getItem($key);
        if ($utility) {return $utility->getLabel();}
      }
      return $key;
    }

    public function getRenderedValue($key, $context = 'smart'){
      if ($context == 'smart' || $context == 'numeric_value') {
        $numval = $this->numvalService->getItem($key);
        if ($numval) {
          if (isset($this->property['numeric_values'])) {
            foreach ($this->property['numeric_values'] as $propnumval) {
              if ($propnumval['key'] == $key) {
                $numval->setValue($propnumval['value']);
              }
            }
          }
          return $numval->getRenderedValue();
        }
      }
      $value = $this->getValue($key, $context);
      return $value;
    }

    public function getValue($key, $context = 'smart'){
      if ($context == 'smart' || $context == 'numeric_value') {
        $numval = $this->numvalService->getItem($key);
        if ($numval) {
          if (isset($this->property['numeric_values'])) {
            foreach ($this->property['numeric_values'] as $propnumval) {
              if ($propnumval['key'] == $key) {
                $numval->setValue($propnumval['value']);
              }
            }
          }
          return $numval->getValue();
        }
      }

      if ($context == 'smart' || $context == 'feature') {
        $feature = $this->featureService->getItem($key);
        if ($feature) {
          return in_array($key, $this->property['features']);
        }
      }

      if ($context == 'smart' || $context == 'special') {
        switch ($key) {
          case 'visualReferenceId':
            if (isset($this->property['visual_reference_id'])) {
              return $this->property['visual_reference_id'];
            }
            if (isset($this->property['id'])) {
              return $this->property['id'];
            }
            break;
          case 'categories':
            $categories = array();
            if (isset($this->property['_embedded']['property_categories'])) {
                foreach ($this->property['_embedded']['property_categories'] as $cat_item) {
                    $categories[] = $this->getLabel($cat_item['category_id'], 'category');
                }
            }
            return str_replace(' ', '-', implode('-', $categories));
            break;
          case 'start':
            if (isset($this->property['start'])) {
              if(is_array($this->property['start'])){
                $date_time = new \DateTime($this->property['start']['date']);

          			return $date_time->format('d.m.Y');
              }
              else{
                return $this->property['start'];
              }
            } else {
              return $this->translator->translate('On Request', 'casasoft-standards');
            }
            break;
          case 'condition':
            $conditions = array();
            foreach ($this->property['features'] as $featureKey) {
              if (in_array($featureKey, [
                'is-demolition-property',
                'is-dilapidated',
                'is-gutted',
                'is-first-time-occupancy',
                'is-well-tended',
                'is-modernized',
                'is-renovation-indigent',
                'is-shell-construction',
                'is-new-construction',
                'is-partially-renovation-indigent',
                'is-partially-refurbished',
                'is-refurbished'
              ] ) ) {
                  $conditions[] = $this->getLabel($featureKey, 'feature');
              }
            }
            return str_replace(' ', '-', implode('-', $conditions));
            break;
          case 'Wärmeerzeugung':
            return '';
            break;
          case 'Wärmeverteilung':
            return '';
            break;
          case 'parcelNumbers':
            if (isset($this->property['parcelNumbers'])) {
              return $this->property['parcelNumbers'];
            }
            break;
          case 'Erschliessung':
            $features = array();
            foreach ($this->property['features'] as $featureKey) {
              if (in_array($featureKey, [
                'has-water-supply',
                'has-sewage-supply',
                'has-power-supply',
                'has-gas-supply',
              ] ) ) {
                  $features[] = $this->getLabel($featureKey, 'feature');
              }
            }
            if (count($features) == 4) {
              $this->translator->translate('Fully ***', 'casasoft-standards');
            } elseif (count($features)) {
              $this->translator->translate('Partialy ***', 'casasoft-standards');
            } else {
              $this->translator->translate('NOT ***', 'casasoft-standards');
            }
            return '';
            break;
          case 'zoneTypes':
            if (isset($this->property['zoneTypes'])) {
              return $this->property['zoneTypes'];
            }
            break;
        }
      }


      return null;
    }

    public function getList($templateMixed = 'key-facts', $filtered = false){
      $list = [];
      $template = [];
      if (is_string($templateMixed)) {
        if (array_key_exists($templateMixed, $this->templates)) {
          $template = $this->templates[$templateMixed];
        } elseif ($templateMixed === 'areas') {
          $template = [];
          foreach ($this->numvalService->getDefaultOptions() as $key => $options) {
            if(strpos($key, 'area_') !== false) {
              $template[] = [$key, 'numeric_value'];
            }
          }
        } elseif ($templateMixed === 'distances') {
          $template = [];
          foreach ($this->numvalService->getDefaultOptions() as $key => $options) {
            if(strpos($key, 'distance_') !== false) {
              $template[] = [$key, 'numeric_value'];
            }
          }
        } elseif ($templateMixed === 'features') {
          $template = [];
          foreach ($this->featureService->getDefaultOptions() as $key => $options) {
            $template[] = [$key, 'feature'];
          }
        } else {
          return $list;
        }
      } else {
        if (!is_array($template)) {
          return $list;
        } else {
          $template = $templateMixed;
        }
      }



      foreach ($template as $field) {
        $rfield = [
          'key' => $field[0],
          'context' => ($field[1] ? $field[1] : 'smart'),
          'label' => $this->getLabel($field[0], ($field[1] ? $field[1] : 'smart')),
          'value' => $this->getValue($field[0], ($field[1] ? $field[1] : 'smart')),
          'renderedValue' => $this->getRenderedValue($field[0], ($field[1] ? $field[1] : 'smart')),
        ];
        if ($filtered && !$rfield['value']) {

        } else {
            $list[] = $rfield;
        }
      }

      if ($templateMixed == 'features') {
        usort($list, function($a, $b) {
            return strcmp($a["label"], $b["label"]);
        });
      }

      if($templateMixed == 'prices'){
        $list = array_merge($list, $this->getCalculatedPrices());
      }

      return $list;


    }


}

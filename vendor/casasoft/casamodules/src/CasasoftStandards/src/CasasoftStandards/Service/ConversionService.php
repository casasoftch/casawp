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
              $time = $this->translator->translate('week', 'casasoft-standards');
              break;
            case 'm':
              $time = $this->translator->translate('month', 'casasoft-standards');
              break;
            case 'y':
              $time = $this->translator->translate('year', 'casasoft-standards');
              break;
            default:
              $time = 1;
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
      return isset($price) ? $price : $this->translator->translate('On Request');
    }


    public function setProperty(Array $data){
      $this->property = $data;
      $this->numvalService->resetService();


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
      if (isset($this->property['_embedded']) && array_key_exists('numeric_values', $this->property['_embedded']) && $this->property['_embedded']['numeric_values']) {
        $this->property['numeric_values'] = $this->property['_embedded']['numeric_values'];

        unset($this->property['_embedded']['numeric_values']);
      }
      else{
        $this->property['numeric_values'] = [];        
      }
      if (isset($this->property['_embedded']) && array_key_exists('features', $this->property['_embedded']) && $this->property['_embedded']['features']) {
        $this->property['features'] = [];
        foreach ($this->property['_embedded']['features'] as $embfeature) {
          $this->property['features'][] = $embfeature['key'];
        }
        unset($this->property['_embedded']['features']);
      }
    }

    private function getCalculatedPrices($type='rent'){
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
      if($type === 'buy'){
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
        $show_price_per_sqm = false;
        if (isset($this->property['_embedded']['property_utilities'])):

          foreach ($this->property['_embedded']['property_utilities'] as $key => $utility):
            if($utility['utility_id'] == 'building'):
              $show_price_per_sqm = true;
              break;
            endif;
          endforeach;
        endif;
        if($show_price_per_sqm){
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
          $nullcheck = [
            'price',
            'pricePerSqm'
          ];
        }
        else{
          $nullcheck = [
            'price',
          ];
        }


      }
      elseif($type === 'rent'){

        $price_utilities = [
          'commercial', 'industrial', 'storage', 'gastronomy'
        ];
        $show_prices = false;
        if (isset($this->property['_embedded']['property_utilities'])):

          foreach ($this->property['_embedded']['property_utilities'] as $key => $utility):
            if(in_array($utility['utility_id'], $price_utilities)):
              $show_prices = true;
              break;
            endif;
          endforeach;
        endif;

        // if(!$show_prices){
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

        // }
        if($show_prices){

          $price['priceNettoPerSqmPerMonth']['key'] = 'priceNettoPerSqmPerMonth';
          $price['priceNettoPerSqmPerMonth']['context'] = '';
          $price['priceNettoPerSqmPerMonth']['label'] = $this->getLabel('priceNettoPerSqmPerMonth');
          $price['priceNettoPerSqmPerMonth']['value'] = round($this->transformPrice([
            'value' => $this->property['net_price'],
            'property_segment' => $this->property['net_price_property_segment'],
            'time_segment' => $this->property['net_price_time_segment'],
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
            'value' => $this->property['net_price'],
            'property_segment' => $this->property['net_price_property_segment'],
            'time_segment' => $this->property['net_price_time_segment'],
            'area' => $area
          ], [
            'property_segment' => 'm',
            'time_segment' => 'y'
          ]));
          $price['priceNettoPerSqmPerYear']['renderedValue'] = $this->renderPrice([
            'price' => $price['priceNettoPerSqmPerYear']['value'],
            'property_segment' => 'm',
            'time_segment' => 'y'
          ]);

        }

        $price['priceNettoTotalPerMonth']['key'] = 'priceNettoTotalPerMonth';
        $price['priceNettoTotalPerMonth']['context'] = '';
        $price['priceNettoTotalPerMonth']['label'] = $this->getLabel('priceNettoTotalPerMonth');
        $price['priceNettoTotalPerMonth']['value'] = round($this->transformPrice([
          'value' => $this->property['net_price'],
          'property_segment' => $this->property['net_price_property_segment'],
          'time_segment' => $this->property['net_price_time_segment'],
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

        $nullcheck = [
          'priceBruttoTotalPerMonth',
          'priceNettoTotalPerMonth'
        ];


        if($show_prices){

          $price['priceNettoTotalPerYear']['key'] = 'priceNettoTotalPerYear';
          $price['priceNettoTotalPerYear']['context'] = '';
          $price['priceNettoTotalPerYear']['label'] = $this->getLabel('priceNettoTotalPerYear');
          $price['priceNettoTotalPerYear']['value'] = round($this->transformPrice([
            'value' => $this->property['net_price'],
            'property_segment' => $this->property['net_price_property_segment'],
            'time_segment' => $this->property['net_price_time_segment'],
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

          $nullcheck_addition = [
            'priceNettoPerSqmPerMonth',
            'priceNettoPerSqmPerYear',
            'priceNettoTotalPerYear'#
          ];

          $nullcheck = array_merge($nullcheck, $nullcheck_addition);
        }



      }

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
          ['floor', 'numeric_value'],
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
          //['granny-flat','category'], Wrong!! this whould be a feature
          ['parcelNumbers','special'],
          ['Erschliessung','special'],
          ['Auflagen','Auflagen'],
          ['zoneTypes','special'],
          ['utilization_number','numeric_value'],
          ['hall_height','numeric_value'],
          ['maximal_floor_loading','numeric_value'],
          ['carrying_capacity_crane','numeric_value'],
          ['carrying_capacity_elevator','numeric_value']
      ],
      'prices-buy' => [
          ['price', 'special'],
          ['pricePerSqm', 'renders'],
          ['gross_premium', 'numeric_value'],
      ],
      'prices-rent' => [
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
          case 'volumes': return $this->translator->translate('Volumes', 'casasoft-standards'); break;
          case 'features': return $this->translator->translate('Features', 'casasoft-standards'); break;
          case 'distances': return $this->translator->translate('Distances', 'casasoft-standards'); break;
          case 'price': return $this->translator->translate('Sales price', 'casasoft-standards'); break;
          case 'on-request': return $this->translator->translate('On Request', 'casasoft-standards'); break;
          case 'pricePerSqm': return $this->translator->translate('Price per sqm', 'casasoft-standards'); break;
    			case 'priceBruttoPerSqmPerMonth': return $this->translator->translate('priceBruttoPerSqmPerMonth', 'casasoft-standards'); break;
    			case 'priceBruttoPerSqmPerYear': return $this->translator->translate('priceBruttoPerSqmPerYear', 'casasoft-standards'); break;
    			case 'priceBruttoTotalPerMonth': return $this->translator->translate('Gross rent / month', 'casasoft-standards'); break;
    			case 'priceBruttoTotalPerYear': return $this->translator->translate('priceBruttoTotalPerYear', 'casasoft-standards'); break;
    			case 'priceNettoPerSqmPerMonth': return $this->translator->translate('Net rent / m<sup>2</sup> / month', 'casasoft-standards'); break;
    			case 'priceNettoPerSqmPerYear': return $this->translator->translate('Net rent / m<sup>2</sup> / year', 'casasoft-standards'); break;
    			case 'priceNettoTotalPerMonth': return $this->translator->translate('Net rent / month', 'casasoft-standards'); break;
    			case 'priceNettoTotalPerYear': return $this->translator->translate('Net rent / year', 'casasoft-standards'); break;
          case 'extraCosts': return $this->translator->translate('Extra Costs', 'casasoft-standards'); break;
          
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

      if ($context == 'smart' || $context == 'utility-alt') {
        switch ($key) {
          case 'building': return $this->translator->translate('Building land', 'casasoft-standards'); break;
          case 'parking': return $this->translator->translate('Parking / Garage', 'casasoft-standards'); break;
          case 'gastronomy': return $this->translator->translate('Gastronomy / Hotel', 'casasoft-standards'); break;
          default: 
            $utility = $this->utilityService->getItem($key);
            if ($utility) {return $utility->getLabel();}
          break;
        }
      }
      return $key;
    }

    public function getRenderedValue($key, $context = 'smart'){
      if ($context == 'smart' || $context == 'special') {
        switch ($key) {
          case 'extraCosts':
            $extraCost = null;
            foreach ($this->property['_embedded']['extracosts'] as $extracost) {
                if (in_array($extracost['title'], ['extracosts', 'Nebenkosten']) && $extracost['cost']) {
                  $extraCost = $extracost;
                  break;
                }
              }
            if ($extraCost) {
              return $this->renderPrice([
                'price' => $extraCost['cost'],
                'time_segment' => $extraCost['time_segment'],
                'property_segment' => $extraCost['property_segment'],
              ]
              );
            }
          break;
        }
      }
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
            if (isset($this->property['exportid'])) {
              return $this->property['exportid'];
            }
            break;
          case 'categories':
            $categories = array();
            if (isset($this->property['_embedded']['property_categories'])) {
                foreach ($this->property['_embedded']['property_categories'] as $cat_item) {
                    $categories[] = $this->getLabel($cat_item['category_id'], 'category');
                }
            }
            if ($categories) {
              return array_values(array_slice($categories, -1))[0];
            }
            break;
          case 'start':
            if (isset($this->property['start'])) {
              if(is_array($this->property['start'])){
                $now = new \DateTime();
                $date_time = new \DateTime($this->property['start']['date']);

                if ($now > $date_time) {
                  return $this->translator->translate('Immediate', 'casasoft-standards');
                } else {
                  return $date_time->format('d.m.Y');
                }
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
            return implode(', ', $conditions);
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
            // $features = array(); //that is wrong!!!
            // foreach ($this->property['features'] as $featureKey) {
            //   if (in_array($featureKey, [
            //     'has-water-supply',
            //     'has-sewage-supply',
            //     'has-power-supply',
            //     'has-gas-supply',
            //   ] ) ) {
            //       $features[] = $this->getLabel($featureKey, 'feature');
            //   }
            // }
            // if (count($features) == 4) {
            //   return $this->translator->translate('Fully connected to utilities', 'casasoft-standards');
            // } elseif (count($features)) {
            //   return $this->translator->translate('Partialy connected to utilities', 'casasoft-standards');
            // } else {
            //   return $this->translator->translate('Not connected to utilities', 'casasoft-standards');
            // }
            return '';
            break;
          case 'extraCosts':
            if (isset($this->property['_embedded']['extracosts'])) {
              foreach ($this->property['_embedded']['extracosts'] as $extracost) {
                if (in_array($extracost['title'], ['extracosts', 'Nebenkosten']) && $extracost['cost']) {
                  return $extracost['cost'];
                  break;
                }
              }
            }
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
        } elseif ($templateMixed === 'volumes') {
          $template = [];
          foreach ($this->numvalService->getDefaultOptions() as $key => $options) {
            if(strpos($key, 'volume_') !== false) {
              $template[] = [$key, 'numeric_value'];
            }
          }
        }
         elseif ($templateMixed === 'distances') {
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
        } elseif ($templateMixed === 'curated-utilities') {
          $template = [
              ['residential', 'utility'],
              ['building', 'utility-alt'],
              ['commercial', 'utility'],
              ['parking', 'utility-alt'],
              ['storage', 'utility'],
              ['gastronomy', 'utility-alt'],
              ['industrial', 'utility'],
              ['investment', 'utility'],
              ['agricultural', 'utility'],
              ['vacation', 'utility'],
          ];
        } elseif ($templateMixed === 'curated-categories') {
          $categories = $this->categoryService->getDefaultOptions();
          $template = [];
          $allCategories = [];
          foreach ($categories as $optionkey => $option) {
            if (!in_array($optionkey, [ //remove *some categories
              'farm',
              'mountain-farm',
              'old-age-home',
              'hobby-room',
              'exhibition-space',
              'building-project',
              'boat-mooring',
              'boat-dry-dock',
              'boat-landing-stage',
              'cafe-bar',
              'campground',
              'double-garage',
              'duplex',
              'shopping-center',
              'single-garage',
              'retail',
              'ground-floor-flat',
              'attic-compartment',
              'factory',
              'outdoor-swimming-pool',
              'commercial-plot',
              'golf-course',
              'plot',
              'indoor-swimming-pool',
              'house',
              'home',
              'hotel',
              'cellar-compartment',
              'hospital',
              'mini-golf-course',
              'covered-bike-space',
              'car-park',
              'bed-and-breakfast',
              'nursing-home',
              'riding-hall',
              'sanatorium',
              'sauna',
              'display-window',
              'alottmen-garden',
              'solarium',
              'sports-hall',
              'squash-badminton',
              'horse-box',
              'studio',
              'bachelor-flat',
              'fuel-station',
              'indoor-tennis-court',
              'tennis-court',
              'underground-slot',
              'covered-slot',
              'workshop',
              'open-slot',
              'house-part',
              'residential-commercial-building',
              'commercial'
            ])) {
              $template[] = [$optionkey, 'category'];
            }
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

      if ($templateMixed == 'curated-categories') {
        usort($list, function($a, $b) {
            return strcmp($a["label"], $b["label"]);
        });
      }

      if($templateMixed == 'prices-rent'){
        $list = array_merge($this->getCalculatedPrices('rent'), $list);
      }
      if($templateMixed == 'prices-buy'){
        $list = array_merge($this->getCalculatedPrices('buy'), $list);
      }

      return $list;


    }


}

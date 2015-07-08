<?php
  namespace CasaSync;

  class Conversion {

    public function __construct(){
    }

    public function countrycode_to_countryname($cc=''){
        $arr = $this->country_arrays();
        if(isset($arr[$cc])){
            return $arr[$cc];
        } else {
            return $cc;
        }
    }

    public function country_arrays(){
        $country_arr = array(
          "AD" => __("Andorra", 'casasync'),
          "AE" => __("United Arab Emirates", 'casasync'),
          "AF" => __("Afghanistan", 'casasync'),
          "AG" => __("Antigua and Barbuda", 'casasync'),
          "AI" => __("Anguilla", 'casasync'),
          "AL" => __("Albania", 'casasync'),
          "AM" => __("Armenia", 'casasync'),
          "AN" => __("Netherlands Antilles", 'casasync'),
          "AO" => __("Angola", 'casasync'),
          "AQ" => __("Antarctica", 'casasync'),
          "AR" => __("Argentina", 'casasync'),
          "AS" => __("American Samoa", 'casasync'),
          "AT" => __("Austria", 'casasync'),
          "AU" => __("Australia", 'casasync'),
          "AW" => __("Aruba", 'casasync'),
          "AZ" => __("Azerbaijan", 'casasync'),
          "BA" => __("Bosnia and Herzegovina", 'casasync'),
          "BB" => __("Barbados", 'casasync'),
          "BD" => __("Bangladesh", 'casasync'),
          "BE" => __("Belgium", 'casasync'),
          "BF" => __("Burkina Faso", 'casasync'),
          "BG" => __("Bulgaria", 'casasync'),
          "BH" => __("Bahrain", 'casasync'),
          "BI" => __("Burundi", 'casasync'),
          "BJ" => __("Benin", 'casasync'),
          "BM" => __("Bermuda", 'casasync'),
          "BN" => __("Brunei Darussalam", 'casasync'),
          "BO" => __("Bolivia", 'casasync'),
          "BR" => __("Brazil", 'casasync'),
          "BS" => __("Bahamas", 'casasync'),
          "BT" => __("Bhutan", 'casasync'),
          "BV" => __("Bouvet Island", 'casasync'),
          "BW" => __("Botswana", 'casasync'),
          "BY" => __("Belarus", 'casasync'),
          "BZ" => __("Belize", 'casasync'),
          "CA" => __("Canada", 'casasync'),
          "CC" => __("Cocos (Keeling) Islands", 'casasync'),
          "CD" => __("Congo, The Democratic Republic of the", 'casasync'),
          "CF" => __("Central African Republic", 'casasync'),
          "CG" => __("Congo", 'casasync'),
          "CH" => __("Switzerland", 'casasync'),
          "CI" => __("Cote D'Ivoire", 'casasync'),
          "CK" => __("Cook Islands", 'casasync'),
          "CL" => __("Chile", 'casasync'),
          "CM" => __("Cameroon", 'casasync'),
          "CN" => __("China", 'casasync'),
          "CO" => __("Colombia", 'casasync'),
          "CR" => __("Costa Rica", 'casasync'),
          "CU" => __("Cuba", 'casasync'),
          "CV" => __("Cape Verde", 'casasync'),
          "CX" => __("Christmas Island", 'casasync'),
          "CY" => __("Cyprus", 'casasync'),
          "CZ" => __("Czech Republic", 'casasync'),
          "DE" => __("Germany", 'casasync'),
          "DJ" => __("Djibouti", 'casasync'),
          "DK" => __("Denmark", 'casasync'),
          "DM" => __("Dominica", 'casasync'),
          "DO" => __("Dominican Republic", 'casasync'),
          "DZ" => __("Algeria", 'casasync'),
          "EC" => __("Ecuador", 'casasync'),
          "EE" => __("Estonia", 'casasync'),
          "EG" => __("Egypt", 'casasync'),
          "EH" => __("Western Sahara", 'casasync'),
          "ER" => __("Eritrea", 'casasync'),
          "ES" => __("Spain", 'casasync'),
          "ET" => __("Ethiopia", 'casasync'),
          "FI" => __("Finland", 'casasync'),
          "FJ" => __("Fiji", 'casasync'),
          "FK" => __("Falkland Islands (Malvinas)", 'casasync'),
          "FM" => __("Micronesia, Federated States of", 'casasync'),
          "FO" => __("Faroe Islands", 'casasync'),
          "FR" => __("France", 'casasync'),
          "FX" => __("France, Metropolitan", 'casasync'),
          "GA" => __("Gabon", 'casasync'),
          "GB" => __("United Kingdom", 'casasync'),
          "GD" => __("Grenada", 'casasync'),
          "GE" => __("Georgia", 'casasync'),
          "GF" => __("French Guiana", 'casasync'),
          "GH" => __("Ghana", 'casasync'),
          "GI" => __("Gibraltar", 'casasync'),
          "GL" => __("Greenland", 'casasync'),
          "GM" => __("Gambia", 'casasync'),
          "GN" => __("Guinea", 'casasync'),
          "GP" => __("Guadeloupe", 'casasync'),
          "GQ" => __("Equatorial Guinea", 'casasync'),
          "GR" => __("Greece", 'casasync'),
          "GS" => __("South Georgia and the South Sandwich Islands", 'casasync'),
          "GT" => __("Guatemala", 'casasync'),
          "GU" => __("Guam", 'casasync'),
          "GW" => __("Guinea-Bissau", 'casasync'),
          "GY" => __("Guyana", 'casasync'),
          "HK" => __("Hong Kong", 'casasync'),
          "HM" => __("Heard Island and McDonald Islands", 'casasync'),
          "HN" => __("Honduras", 'casasync'),
          "HR" => __("Croatia", 'casasync'),
          "HT" => __("Haiti", 'casasync'),
          "HU" => __("Hungary", 'casasync'),
          "ID" => __("Indonesia", 'casasync'),
          "IE" => __("Ireland", 'casasync'),
          "IL" => __("Israel", 'casasync'),
          "IN" => __("India", 'casasync'),
          "IO" => __("British Indian Ocean Territory", 'casasync'),
          "IQ" => __("Iraq", 'casasync'),
          "IR" => __("Iran, Islamic Republic of", 'casasync'),
          "IS" => __("Iceland", 'casasync'),
          "IT" => __("Italy", 'casasync'),
          "JM" => __("Jamaica", 'casasync'),
          "JO" => __("Jordan", 'casasync'),
          "JP" => __("Japan", 'casasync'),
          "KE" => __("Kenya", 'casasync'),
          "KG" => __("Kyrgyzstan", 'casasync'),
          "KH" => __("Cambodia", 'casasync'),
          "KI" => __("Kiribati", 'casasync'),
          "KM" => __("Comoros", 'casasync'),
          "KN" => __("Saint Kitts and Nevis", 'casasync'),
          "KP" => __("Korea, Democratic People's Republic of", 'casasync'),
          "KR" => __("Korea, Republic of", 'casasync'),
          "KW" => __("Kuwait", 'casasync'),
          "KY" => __("Cayman Islands", 'casasync'),
          "KZ" => __("Kazakstan", 'casasync'),
          "LA" => __("Lao People's Democratic Republic", 'casasync'),
          "LB" => __("Lebanon", 'casasync'),
          "LC" => __("Saint Lucia", 'casasync'),
          "LI" => __("Liechtenstein", 'casasync'),
          "LK" => __("Sri Lanka", 'casasync'),
          "LR" => __("Liberia", 'casasync'),
          "LS" => __("Lesotho", 'casasync'),
          "LT" => __("Lithuania", 'casasync'),
          "LU" => __("Luxembourg", 'casasync'),
          "LV" => __("Latvia", 'casasync'),
          "LY" => __("Libyan Arab Jamahiriya", 'casasync'),
          "MA" => __("Morocco", 'casasync'),
          "MC" => __("Monaco", 'casasync'),
          "MD" => __("Moldova, Republic of", 'casasync'),
          "MG" => __("Madagascar", 'casasync'),
          "MH" => __("Marshall Islands", 'casasync'),
          "MK" => __("Macedonia", 'casasync'),
          "ML" => __("Mali", 'casasync'),
          "MM" => __("Myanmar", 'casasync'),
          "MN" => __("Mongolia", 'casasync'),
          "MO" => __("Macau", 'casasync'),
          "MP" => __("Northern Mariana Islands", 'casasync'),
          "MQ" => __("Martinique", 'casasync'),
          "MR" => __("Mauritania", 'casasync'),
          "MS" => __("Montserrat", 'casasync'),
          "MT" => __("Malta", 'casasync'),
          "MU" => __("Mauritius", 'casasync'),
          "MV" => __("Maldives", 'casasync'),
          "MW" => __("Malawi", 'casasync'),
          "MX" => __("Mexico", 'casasync'),
          "MY" => __("Malaysia", 'casasync'),
          "MZ" => __("Mozambique", 'casasync'),
          "NA" => __("Namibia", 'casasync'),
          "NC" => __("New Caledonia", 'casasync'),
          "NE" => __("Niger", 'casasync'),
          "NF" => __("Norfolk Island", 'casasync'),
          "NG" => __("Nigeria", 'casasync'),
          "NI" => __("Nicaragua", 'casasync'),
          "NL" => __("Netherlands", 'casasync'),
          "NO" => __("Norway", 'casasync'),
          "NP" => __("Nepal", 'casasync'),
          "NR" => __("Nauru", 'casasync'),
          "NU" => __("Niue", 'casasync'),
          "NZ" => __("New Zealand", 'casasync'),
          "OM" => __("Oman", 'casasync'),
          "PA" => __("Panama", 'casasync'),
          "PE" => __("Peru", 'casasync'),
          "PF" => __("French Polynesia", 'casasync'),
          "PG" => __("Papua New Guinea", 'casasync'),
          "PH" => __("Philippines", 'casasync'),
          "PK" => __("Pakistan", 'casasync'),
          "PL" => __("Poland", 'casasync'),
          "PM" => __("Saint Pierre and Miquelon", 'casasync'),
          "PN" => __("Pitcairn Islands", 'casasync'),
          "PR" => __("Puerto Rico", 'casasync'),
          "PS" => __("Palestinian Territory", 'casasync'),
          "PT" => __("Portugal", 'casasync'),
          "PW" => __("Palau", 'casasync'),
          "PY" => __("Paraguay", 'casasync'),
          "QA" => __("Qatar", 'casasync'),
          "RE" => __("Reunion", 'casasync'),
          "RO" => __("Romania", 'casasync'),
          "RU" => __("Russian Federation", 'casasync'),
          "RW" => __("Rwanda", 'casasync'),
          "SA" => __("Saudi Arabia", 'casasync'),
          "SB" => __("Solomon Islands", 'casasync'),
          "SC" => __("Seychelles", 'casasync'),
          "SD" => __("Sudan", 'casasync'),
          "SE" => __("Sweden", 'casasync'),
          "SG" => __("Singapore", 'casasync'),
          "SH" => __("Saint Helena", 'casasync'),
          "SI" => __("Slovenia", 'casasync'),
          "SJ" => __("Svalbard and Jan Mayen", 'casasync'),
          "SK" => __("Slovakia", 'casasync'),
          "SL" => __("Sierra Leone", 'casasync'),
          "SM" => __("San Marino", 'casasync'),
          "SN" => __("Senegal", 'casasync'),
          "SO" => __("Somalia", 'casasync'),
          "SR" => __("Suriname", 'casasync'),
          "ST" => __("Sao Tome and Principe", 'casasync'),
          "SV" => __("El Salvador", 'casasync'),
          "SY" => __("Syrian Arab Republic", 'casasync'),
          "SZ" => __("Swaziland", 'casasync'),
          "TC" => __("Turks and Caicos Islands", 'casasync'),
          "TD" => __("Chad", 'casasync'),
          "TF" => __("French Southern Territories", 'casasync'),
          "TG" => __("Togo", 'casasync'),
          "TH" => __("Thailand", 'casasync'),
          "TJ" => __("Tajikistan", 'casasync'),
          "TK" => __("Tokelau", 'casasync'),
          "TM" => __("Turkmenistan", 'casasync'),
          "TN" => __("Tunisia", 'casasync'),
          "TO" => __("Tonga", 'casasync'),
          "TL" => __("Timor-Leste", 'casasync'),
          "TR" => __("Turkey", 'casasync'),
          "TT" => __("Trinidad and Tobago", 'casasync'),
          "TV" => __("Tuvalu", 'casasync'),
          "TW" => __("Taiwan", 'casasync'),
          "TZ" => __("Tanzania, United Republic of", 'casasync'),
          "UA" => __("Ukraine", 'casasync'),
          "UG" => __("Uganda", 'casasync'),
          "UM" => __("United States Minor Outlying Islands", 'casasync'),
          "US" => __("United States", 'casasync'),
          "UY" => __("Uruguay", 'casasync'),
          "UZ" => __("Uzbekistan", 'casasync'),
          "VA" => __("Holy See (Vatican City State)", 'casasync'),
          "VC" => __("Saint Vincent and the Grenadines", 'casasync'),
          "VE" => __("Venezuela", 'casasync'),
          "VG" => __("Virgin Islands, British", 'casasync'),
          "VI" => __("Virgin Islands, U.S.", 'casasync'),
          "VN" => __("Vietnam", 'casasync'),
          "VU" => __("Vanuatu", 'casasync'),
          "WF" => __("Wallis and Futuna", 'casasync'),
          "WS" => __("Samoa", 'casasync'),
          "YE" => __("Yemen", 'casasync'),
          "YT" => __("Mayotte", 'casasync'),
          "RS" => __("Serbia", 'casasync'),
          "ZA" => __("South Africa", 'casasync'),
          "ZM" => __("Zambia", 'casasync'),
          "ME" => __("Montenegro", 'casasync'),
          "ZW" => __("Zimbabwe", 'casasync'),
          "AX" => __("Aland Islands", 'casasync'),
          "GG" => __("Guernsey", 'casasync'),
          "IM" => __("Isle of Man", 'casasync'),
          "JE" => __("Jersey", 'casasync'),
          "BL" => __("Saint Barthelemy", 'casasync'),
          "MF" => __("Saint Martin", 'casasync')
        );
        return $country_arr;
    }

    public function casasync_convert_numvalKeyToLabel($key){
        switch ($key) {
            #case 'surface_living':             return __('Living space' ,'casasync');break;
            #case 'surface_usable':             return __('Surface usable' ,'casasync');break;
            case 'surface_property':           return __('Property space' ,'casasync');break;
            case 'area_bwf':                   return __('Living space' ,'casasync');break;
            case 'area_nwf':                   return __('Net living space' ,'casasync');break;
            case 'area_sia_gf':                return __('Gross floor area' ,'casasync');break;
            case 'area_sia_nf':                return __('Surface usable' ,'casasync');break;

            case 'year_renovated':             return __('Year of renovation' ,'casasync');break;
            case 'year_built':                 return __('Year of construction' ,'casasync');break;
            case 'number_of_rooms':            return __('Number of rooms' ,'casasync');break;
            case 'number_of_lavatory':            return __('Number of lavatory' ,'casasync');break;
            case 'number_of_guest_wc':            return __('Number of guest toilets' ,'casasync');break;
            case 'number_of_floors':           return __('Number of floors' ,'casasync');break;
            case 'floor':                      return __('Floor' ,'casasync');break;
            case 'volume':                     return __('Volume' ,'casasync');break;
            case 'number_of_apartments':       return __('Number of apartments' ,'casasync');break;
            case 'ceiling_height':             return __('Ceiling height' ,'casasync');break;
            case 'hall_height':                return __('Hall height' ,'casasync');break;
            case 'maximal_floor_loading':      return __('Maximal floor loading' ,'casasync');break;
            case 'carrying_capacity_crane':    return __('Carrying capacity crane' ,'casasync');break;
            case 'carrying_capacity_elevator': return __('Carrying capacity elevator' ,'casasync');break;

            /*
            'area_bwf'                    ,
      'area_nwf'                    ,
      'area_sia_gf'                 ,
      'area_sia_nf'*/
        }
    }

    public function casasync_convert_categoryKeyToLabel($key, $fallback = ''){
        $label = null;

        if (substr($key, 0, 7) == 'custom_') {
            $current_lang = function_exists('icl_get_home_url') ? ICL_LANGUAGE_CODE : 'de';
            $translations = get_option('casasync_custom_category_translations');
            if (!is_array($translations)) {
              $translations = array();
            }
            foreach ($translations as $slug => $strings) {
              if ($slug == $key) {
                if ($strings['show']) {
                  $label = $strings[$current_lang];
                } else {
                  return false;
                }
              }
            }
        }

        if (!$label) {
          switch ($key) {
              case 'secondary-rooms': $label = __('Secondary rooms' ,'casasync');break;
              case 'garden':          $label = __('Garden' ,'casasync');break;

              case 'apartment':       $label = __('Apartment' ,'casasync');break;
              case 'attic-flat':      $label = __('Attic Flat' ,'casasync');break;
              case 'bachelor-flat':      $label = __('Bachelor Flat' ,'casasync');break;
              case 'bifamiliar-house':      $label = __('Bifamiliar House' ,'casasync');break;
              case 'building-land':      $label = __('Building Land' ,'casasync');break;
              case 'double-garage':      $label = __('Double Garage' ,'casasync');break;
              case 'duplex':      $label = __('Duplex' ,'casasync');break;
              case 'factory':      $label = __('Factory' ,'casasync');break;
              case 'farm':      $label = __('Farm' ,'casasync');break;
              case 'farm-house':      $label = __('Farm House' ,'casasync');break;
              case 'furnished-flat':      $label = __('Furnished Flat' ,'casasync');break;
              case 'garage':      $label = __('Garage' ,'casasync');break;
              case 'loft':      $label = __('Loft' ,'casasync');break;
              case 'mountain-farm':      $label = __('Mountain Farm' ,'casasync');break;
              case 'multiple-dwelling':      $label = __('Multiple Dwelling' ,'casasync');break;
              case 'open-slot':      $label = __('Open Slot' ,'casasync');break;
              case 'parking-space':      $label = __('Parking Space' ,'casasync');break;
              case 'plot':            $label = __('Plot' ,'casasync');break;
              case 'roof-flat':      $label = __('Roof Flat' ,'casasync');break;
              case 'row-house':      $label = __('Row House' ,'casasync');break;
              case 'single-garage':      $label = __('Single Garage' ,'casasync');break;
              case 'single-house':      $label = __('Single House' ,'casasync');break;
              case 'single-room':      $label = __('Single Room' ,'casasync');break;
              case 'terrace-flat':      $label = __('Terrace Flat' ,'casasync');break;
              case 'terrace-house':      $label = __('Terrace House' ,'casasync');break;
              case 'underground-slot':      $label = __('Underground Slot' ,'casasync');break;
              case 'villa':      $label = __('Villa' ,'casasync');break;
              case 'chalet':      $label = __('Chalet' ,'casasync');break;
              case 'studio':      $label = __('Studio' ,'casasync');break;
              case 'house':           $label = __('House' ,'casasync');break;
              case 'covered-slot':      $label = __('Covered Slot' ,'casasync');break;

              case 'commercial':      $label = __('Commercial' ,'casasync');break;
              case 'gastronomy':      $label = __('Gastronomy' ,'casasync');break;
              case 'vacation':      $label = __('Vacation' ,'casasync');break;
              case 'agriculture':     $label = __('Agriculture' ,'casasync');break;
              case 'industrial':      $label = __('Industrial' ,'casasync');break;
              case 'residential':      $label = __('Residential' ,'casasync');break;
              case 'storage':      $label = __('Storage' ,'casasync');break;
              case 'parking':         $label = __('Parking space' ,'casasync');break;
              case 'building':      $label = __('Building' ,'casasync');break;
              case 'advertising-area':      $label = __('Advertising Area' ,'casasync');break;
              case 'arcade':      $label = __('Arcade' ,'casasync');break;
              case 'atelier':      $label = __('Atelier' ,'casasync');break;
              case 'bakery':      $label = __('Bakery' ,'casasync');break;
              case 'bar':      $label = __('Bar' ,'casasync');break;
              case 'butcher':      $label = __('Butcher' ,'casasync');break;
              case 'café':      $label = __('Café' ,'casasync');break;
              case 'casino':      $label = __('Casino' ,'casasync');break;
              case 'cheese-factory':      $label = __('Cheese Factory' ,'casasync');break;
              case 'club/disco':      $label = __('Club/Disco' ,'casasync');break;
              case 'fuel-station':      $label = __('Fuel Station' ,'casasync');break;
              case 'gardening':      $label = __('Gardening' ,'casasync');break;
              case 'hairdresser':      $label = __('Hairdresser' ,'casasync');break;
              case 'kiosk':      $label = __('Kiosk' ,'casasync');break;
              case 'movie-theater':      $label = __('Movie Theater' ,'casasync');break;
              case 'office':      $label = __('Office' ,'casasync');break;
              case 'practice':      $label = __('Practice' ,'casasync');break;
              case 'restaurant':      $label = __('Restaurant' ,'casasync');break;
              case 'shop':      $label = __('Shop' ,'casasync');break;
              case 'shopping-centre':      $label = __('Shopping Centre' ,'casasync');break;
              case 'hotel':      $label = __('Hotel' ,'casasync');break;


          }
        }

        if (!$label && $fallback != '') {
          return $fallback;
        } elseif (!$label) {
          return $key;
        } else {
          return $label;
        }
    }

    public function casasync_get_allDistanceKeys(){
        return array(
            'distance_public_transport',
            'distance_shop',
            'distance_kindergarten',
            'distance_motorway',
            'distance_school1',
            'distance_school2',
            'distance_bus_stop',
            'distance_train_station',
            'distance_post',
            'distance_bank',
            'distance_cable_railway_station',
            'distance_boat_dock',
            'distance_airport'
        );
    }

    public function casasync_convert_distanceKeyToLabel($key){
        switch ($key) {
            case 'distance_public_transport':      return __('Public transportation' ,'casasync');break;
            case 'distance_shop':                  return __('Shopping' ,'casasync');break;
            case 'distance_kindergarten':          return __('Kindergarten' ,'casasync');break;
            case 'distance_motorway':              return __('Motorway' ,'casasync');break;
            case 'distance_school1':               return __('Primary school' ,'casasync');break;
            case 'distance_school2':               return __('Secondary school' ,'casasync');break;
            case 'distance_bus_stop':              return __('Bus stop' ,'casasync');break;
            case 'distance_train_station':         return __('Train station' ,'casasync');break;
            case 'distance_post':                  return __('Post' ,'casasync');break;
            case 'distance_bank':                  return __('Bank' ,'casasync');break;
            case 'distance_cable_railway_station': return __('Railway Station' ,'casasync');break;
            case 'distance_boat_dock':             return __('Boat dock' ,'casasync');break;
            case 'distance_airport':               return __('Airport', 'casasync');break;
        }
    }

    public function casasync_convert_availabilityKeyToLabel($key){
        switch ($key) {
            //old
            case 'on-request':   return __('On Request' ,'casasync');break;
            case 'by-agreement': return __('By Agreement' ,'casasync');break;
            case 'immediately':  return __('Immediate' ,'casasync');break;

            //new
            case 'active':       return __('Available' ,'casasync');break;
            case 'reserved':     return __('Reserved' ,'casasync');break;
            case 'sold':         return __('Sold' ,'casasync');break;
            case 'rented':       return __('Rented' ,'casasync');break;
            case 'reference':    return __('Reference' ,'casasync');break;
        }
    }

    public function casasync_convert_featureKeyToLabel($key, $value = false){
        switch ($key) {
            case 'prop_child-friendly':   return __('Child friendly' ,'casasync');break;
            case 'prop_garage':
                if ($value && $value > 1) {
                    return printf( __( '%d garages', 'casasync'), $value );
                } else {
                    return __('Garage' ,'casasync');
                }
            break;
            case 'prop_balcony':
                if ($value) {
                    return printf( __( '%dx balconies', 'casasync'), $value );
                } else {
                    return __('Balcony' ,'casasync');
                }
            break;
            case 'prop_view':   return __('Vista' ,'casasync');break;
            case 'prop_cabletv':   return __('Cable TV' ,'casasync');break;
            case 'prop_parking':
                if ($value) {
                    return printf( __( '%d Parking spaces', 'casasync'), $value );
                } else {
                    return __('Parking space' ,'casasync');
                }
            break;
            case 'animal_allowed':
                if ($value) {
                    return printf( __( '%d Pets allowed', 'casasync'), $value );
                } else {
                    return __('Pets allowed' ,'casasync');
                }
                break;
            case 'isdn':   return __('ISDN Anschluss' ,'casasync');
                break;
            case 'restrooms':
                if ($value && $value != 1) {
                    return printf( __( '%d Restrooms', 'casasync'), $value );
                } else {
                    return __('Restrooms' ,'casasync');
                }
                break;
            case 'prop_elevator':
                if ($value && $value != 1) {
                    return printf( __( '%d elevators', 'casasync'), $value );
                } else {
                    return __('Elevator' ,'casasync');
                }
                break;

            case 'prop_fireplace':          return __('Fireplace' ,'casasync');break;
            case 'wheelchair_accessible':   return __('wheelchair accessible' ,'casasync');break;
            case 'ramp':                    return __('Ramp' ,'casasync');break;
            case 'lifting_platform':        return __('lifting platform' ,'casasync');break;
            case 'railway_terminal':        return __('Railway terminal' ,'casasync');break;
            case 'water_supply':            return __('Water Supply' ,'casasync');break;
            case 'sewage_supply':           return __('Sewage supply' ,'casasync');break;
            case 'power_supply':            return __('Power Supply' ,'casasync');break;
            case 'gas_supply':              return __('Gas supply' ,'casasync');break;
            case 'corner_house':            return __('Corner house' ,'casasync');break;
            case 'middle_house':            return __('Middle house' ,'casasync');break;
            case 'gardenhouse':             return __('Gardenhouse' ,'casasync');break;
            case 'raised_ground_floor':     return __('Raised ground floor' ,'casasync');break;
            case 'new_building':            return __('New building' ,'casasync');break;
            case 'old_building':            return __('Old building' ,'casasync');break;
            case 'under_roof':              return __('Under roof' ,'casasync');break;
            case 'swimmingpool':            return __('Swimmingpool' ,'casasync');break;
            case 'minergie_general':        return __('Minergie general' ,'casasync');break;
            case 'minergie_certified':      return __('Minergie certified' ,'casasync');break;
            case 'under_building_laws':     return __('Under building laws' ,'casasync');break;
            case 'building_land_connected': return __('Building land connected' ,'casasync');break;
            case 'flat_sharing_community':  return __('Flat sharing community' ,'casasync');break;

            default : return $key . ($value ? ': ' . $value : ''); break;
        }
    }

    public function casasync_get_allNumvalKeys(){
      return array(
        #'surface_living',
        #'surface_usable',
        'area_bwf',
        'area_nwf',
        'area_sia_gf',
        'area_sia_nf',
        'surface_property',
        'year_renovated',
        'year_built',
        'number_of_rooms',
        'number_of_floors',
        'number_of_lavatory',
        'floor',
        'number_of_apartments',
        'volume',
        'ceiling_height',
        'hall_height',
        'maximal_floor_loading',
        'carrying_capacity_crane',
        'carrying_capacity_elevator'
      );
    }

    public function casasync_numStringToArray($key, $string){
      
      $si = false;
      if ($string == '') {
        return false;
      }
      if (strlen($string) == 1) {
        if (!is_numeric($string[0])) {
           $string = false;
        }
      } elseif (strlen($string) == 2) { // 23 or m2 or km or 1m
        $first  = $string[strlen($string)-2];
        $second = $string[strlen($string)-1];

        //avoid float dots to be considered as SI
        $first  = ($first == '.' ? 0 : $first);
        $second = ($first == '.' ? 0 : $first);

        if ( !is_numeric($string[0]) ) { //m2 or km
          $string = false;
        } elseif (is_numeric($first) && !is_numeric($second)) { // 1m
          $string = substr($string, 0, -1);
          $si = $second;
        }
      } elseif (strlen($string) > 2) { //123 or 1m2 or 1km or 12m
        $first  = $string[strlen($string)-3];
        $second = $string[strlen($string)-2];
        $third  = $string[strlen($string)-1];

        //avoid float dots to be considered as SI
        $first  = ($first == '.' ? 0 : $first);
        $second = ($second == '.' ? 0 : $second);
        $third  = ($third == '.' ? 0 : $third);

        if (is_numeric($first)  && !is_numeric($second) && is_numeric($third)) { //(...)1m2
          $string = substr($string, 0, -2);
          $si = $second;
        } elseif (is_numeric($first)  && !is_numeric($second) && !is_numeric($third)) { //(...)1km
          $string = substr($string, 0, -2);
          $si = $second . $third;
        } elseif (is_numeric($first)  && is_numeric($second) && !is_numeric($third)) { //(...)12m
          $string = substr($string, 0, -1);
          $si = $third;
        } elseif ( // (...)1km2
            strlen($string) > 3 &&
            is_numeric($first) &&
            !is_numeric($second) &&
            is_numeric($third) &&
            is_numeric($string[strlen($string)-4])
          ) {
            $string = substr($string, 0, -3);
            $si = $first . $second;
        }
      }

      switch ($key) {
        case 'area_bwf':
        case 'area_nwf':
        case 'area_sia_gf':
        case 'area_sia_nf':
          if(!$si) {
            $si = 'm';
          }
          break;
        default:
          break;
      }


      return array('value' => (FLOAT) $string, 'si' => $si);
    }
  }

<?php
  namespace casawp;

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
          "AD" => __("Andorra", 'casawp'),
          "AE" => __("United Arab Emirates", 'casawp'),
          "AF" => __("Afghanistan", 'casawp'),
          "AG" => __("Antigua and Barbuda", 'casawp'),
          "AI" => __("Anguilla", 'casawp'),
          "AL" => __("Albania", 'casawp'),
          "AM" => __("Armenia", 'casawp'),
          "AN" => __("Netherlands Antilles", 'casawp'),
          "AO" => __("Angola", 'casawp'),
          "AQ" => __("Antarctica", 'casawp'),
          "AR" => __("Argentina", 'casawp'),
          "AS" => __("American Samoa", 'casawp'),
          "AT" => __("Austria", 'casawp'),
          "AU" => __("Australia", 'casawp'),
          "AW" => __("Aruba", 'casawp'),
          "AZ" => __("Azerbaijan", 'casawp'),
          "BA" => __("Bosnia and Herzegovina", 'casawp'),
          "BB" => __("Barbados", 'casawp'),
          "BD" => __("Bangladesh", 'casawp'),
          "BE" => __("Belgium", 'casawp'),
          "BF" => __("Burkina Faso", 'casawp'),
          "BG" => __("Bulgaria", 'casawp'),
          "BH" => __("Bahrain", 'casawp'),
          "BI" => __("Burundi", 'casawp'),
          "BJ" => __("Benin", 'casawp'),
          "BM" => __("Bermuda", 'casawp'),
          "BN" => __("Brunei Darussalam", 'casawp'),
          "BO" => __("Bolivia", 'casawp'),
          "BR" => __("Brazil", 'casawp'),
          "BS" => __("Bahamas", 'casawp'),
          "BT" => __("Bhutan", 'casawp'),
          "BV" => __("Bouvet Island", 'casawp'),
          "BW" => __("Botswana", 'casawp'),
          "BY" => __("Belarus", 'casawp'),
          "BZ" => __("Belize", 'casawp'),
          "CA" => __("Canada", 'casawp'),
          "CC" => __("Cocos (Keeling) Islands", 'casawp'),
          "CD" => __("Congo, The Democratic Republic of the", 'casawp'),
          "CF" => __("Central African Republic", 'casawp'),
          "CG" => __("Congo", 'casawp'),
          "CH" => __("Switzerland", 'casawp'),
          "CI" => __("Cote D'Ivoire", 'casawp'),
          "CK" => __("Cook Islands", 'casawp'),
          "CL" => __("Chile", 'casawp'),
          "CM" => __("Cameroon", 'casawp'),
          "CN" => __("China", 'casawp'),
          "CO" => __("Colombia", 'casawp'),
          "CR" => __("Costa Rica", 'casawp'),
          "CU" => __("Cuba", 'casawp'),
          "CV" => __("Cape Verde", 'casawp'),
          "CX" => __("Christmas Island", 'casawp'),
          "CY" => __("Cyprus", 'casawp'),
          "CZ" => __("Czech Republic", 'casawp'),
          "DE" => __("Germany", 'casawp'),
          "DJ" => __("Djibouti", 'casawp'),
          "DK" => __("Denmark", 'casawp'),
          "DM" => __("Dominica", 'casawp'),
          "DO" => __("Dominican Republic", 'casawp'),
          "DZ" => __("Algeria", 'casawp'),
          "EC" => __("Ecuador", 'casawp'),
          "EE" => __("Estonia", 'casawp'),
          "EG" => __("Egypt", 'casawp'),
          "EH" => __("Western Sahara", 'casawp'),
          "ER" => __("Eritrea", 'casawp'),
          "ES" => __("Spain", 'casawp'),
          "ET" => __("Ethiopia", 'casawp'),
          "FI" => __("Finland", 'casawp'),
          "FJ" => __("Fiji", 'casawp'),
          "FK" => __("Falkland Islands (Malvinas)", 'casawp'),
          "FM" => __("Micronesia, Federated States of", 'casawp'),
          "FO" => __("Faroe Islands", 'casawp'),
          "FR" => __("France", 'casawp'),
          "FX" => __("France, Metropolitan", 'casawp'),
          "GA" => __("Gabon", 'casawp'),
          "GB" => __("United Kingdom", 'casawp'),
          "GD" => __("Grenada", 'casawp'),
          "GE" => __("Georgia", 'casawp'),
          "GF" => __("French Guiana", 'casawp'),
          "GH" => __("Ghana", 'casawp'),
          "GI" => __("Gibraltar", 'casawp'),
          "GL" => __("Greenland", 'casawp'),
          "GM" => __("Gambia", 'casawp'),
          "GN" => __("Guinea", 'casawp'),
          "GP" => __("Guadeloupe", 'casawp'),
          "GQ" => __("Equatorial Guinea", 'casawp'),
          "GR" => __("Greece", 'casawp'),
          "GS" => __("South Georgia and the South Sandwich Islands", 'casawp'),
          "GT" => __("Guatemala", 'casawp'),
          "GU" => __("Guam", 'casawp'),
          "GW" => __("Guinea-Bissau", 'casawp'),
          "GY" => __("Guyana", 'casawp'),
          "HK" => __("Hong Kong", 'casawp'),
          "HM" => __("Heard Island and McDonald Islands", 'casawp'),
          "HN" => __("Honduras", 'casawp'),
          "HR" => __("Croatia", 'casawp'),
          "HT" => __("Haiti", 'casawp'),
          "HU" => __("Hungary", 'casawp'),
          "ID" => __("Indonesia", 'casawp'),
          "IE" => __("Ireland", 'casawp'),
          "IL" => __("Israel", 'casawp'),
          "IN" => __("India", 'casawp'),
          "IO" => __("British Indian Ocean Territory", 'casawp'),
          "IQ" => __("Iraq", 'casawp'),
          "IR" => __("Iran, Islamic Republic of", 'casawp'),
          "IS" => __("Iceland", 'casawp'),
          "IT" => __("Italy", 'casawp'),
          "JM" => __("Jamaica", 'casawp'),
          "JO" => __("Jordan", 'casawp'),
          "JP" => __("Japan", 'casawp'),
          "KE" => __("Kenya", 'casawp'),
          "KG" => __("Kyrgyzstan", 'casawp'),
          "KH" => __("Cambodia", 'casawp'),
          "KI" => __("Kiribati", 'casawp'),
          "KM" => __("Comoros", 'casawp'),
          "KN" => __("Saint Kitts and Nevis", 'casawp'),
          "KP" => __("Korea, Democratic People's Republic of", 'casawp'),
          "KR" => __("Korea, Republic of", 'casawp'),
          "KW" => __("Kuwait", 'casawp'),
          "KY" => __("Cayman Islands", 'casawp'),
          "KZ" => __("Kazakstan", 'casawp'),
          "LA" => __("Lao People's Democratic Republic", 'casawp'),
          "LB" => __("Lebanon", 'casawp'),
          "LC" => __("Saint Lucia", 'casawp'),
          "LI" => __("Liechtenstein", 'casawp'),
          "LK" => __("Sri Lanka", 'casawp'),
          "LR" => __("Liberia", 'casawp'),
          "LS" => __("Lesotho", 'casawp'),
          "LT" => __("Lithuania", 'casawp'),
          "LU" => __("Luxembourg", 'casawp'),
          "LV" => __("Latvia", 'casawp'),
          "LY" => __("Libyan Arab Jamahiriya", 'casawp'),
          "MA" => __("Morocco", 'casawp'),
          "MC" => __("Monaco", 'casawp'),
          "MD" => __("Moldova, Republic of", 'casawp'),
          "MG" => __("Madagascar", 'casawp'),
          "MH" => __("Marshall Islands", 'casawp'),
          "MK" => __("Macedonia", 'casawp'),
          "ML" => __("Mali", 'casawp'),
          "MM" => __("Myanmar", 'casawp'),
          "MN" => __("Mongolia", 'casawp'),
          "MO" => __("Macau", 'casawp'),
          "MP" => __("Northern Mariana Islands", 'casawp'),
          "MQ" => __("Martinique", 'casawp'),
          "MR" => __("Mauritania", 'casawp'),
          "MS" => __("Montserrat", 'casawp'),
          "MT" => __("Malta", 'casawp'),
          "MU" => __("Mauritius", 'casawp'),
          "MV" => __("Maldives", 'casawp'),
          "MW" => __("Malawi", 'casawp'),
          "MX" => __("Mexico", 'casawp'),
          "MY" => __("Malaysia", 'casawp'),
          "MZ" => __("Mozambique", 'casawp'),
          "NA" => __("Namibia", 'casawp'),
          "NC" => __("New Caledonia", 'casawp'),
          "NE" => __("Niger", 'casawp'),
          "NF" => __("Norfolk Island", 'casawp'),
          "NG" => __("Nigeria", 'casawp'),
          "NI" => __("Nicaragua", 'casawp'),
          "NL" => __("Netherlands", 'casawp'),
          "NO" => __("Norway", 'casawp'),
          "NP" => __("Nepal", 'casawp'),
          "NR" => __("Nauru", 'casawp'),
          "NU" => __("Niue", 'casawp'),
          "NZ" => __("New Zealand", 'casawp'),
          "OM" => __("Oman", 'casawp'),
          "PA" => __("Panama", 'casawp'),
          "PE" => __("Peru", 'casawp'),
          "PF" => __("French Polynesia", 'casawp'),
          "PG" => __("Papua New Guinea", 'casawp'),
          "PH" => __("Philippines", 'casawp'),
          "PK" => __("Pakistan", 'casawp'),
          "PL" => __("Poland", 'casawp'),
          "PM" => __("Saint Pierre and Miquelon", 'casawp'),
          "PN" => __("Pitcairn Islands", 'casawp'),
          "PR" => __("Puerto Rico", 'casawp'),
          "PS" => __("Palestinian Territory", 'casawp'),
          "PT" => __("Portugal", 'casawp'),
          "PW" => __("Palau", 'casawp'),
          "PY" => __("Paraguay", 'casawp'),
          "QA" => __("Qatar", 'casawp'),
          "RE" => __("Reunion", 'casawp'),
          "RO" => __("Romania", 'casawp'),
          "RU" => __("Russian Federation", 'casawp'),
          "RW" => __("Rwanda", 'casawp'),
          "SA" => __("Saudi Arabia", 'casawp'),
          "SB" => __("Solomon Islands", 'casawp'),
          "SC" => __("Seychelles", 'casawp'),
          "SD" => __("Sudan", 'casawp'),
          "SE" => __("Sweden", 'casawp'),
          "SG" => __("Singapore", 'casawp'),
          "SH" => __("Saint Helena", 'casawp'),
          "SI" => __("Slovenia", 'casawp'),
          "SJ" => __("Svalbard and Jan Mayen", 'casawp'),
          "SK" => __("Slovakia", 'casawp'),
          "SL" => __("Sierra Leone", 'casawp'),
          "SM" => __("San Marino", 'casawp'),
          "SN" => __("Senegal", 'casawp'),
          "SO" => __("Somalia", 'casawp'),
          "SR" => __("Suriname", 'casawp'),
          "ST" => __("Sao Tome and Principe", 'casawp'),
          "SV" => __("El Salvador", 'casawp'),
          "SY" => __("Syrian Arab Republic", 'casawp'),
          "SZ" => __("Swaziland", 'casawp'),
          "TC" => __("Turks and Caicos Islands", 'casawp'),
          "TD" => __("Chad", 'casawp'),
          "TF" => __("French Southern Territories", 'casawp'),
          "TG" => __("Togo", 'casawp'),
          "TH" => __("Thailand", 'casawp'),
          "TJ" => __("Tajikistan", 'casawp'),
          "TK" => __("Tokelau", 'casawp'),
          "TM" => __("Turkmenistan", 'casawp'),
          "TN" => __("Tunisia", 'casawp'),
          "TO" => __("Tonga", 'casawp'),
          "TL" => __("Timor-Leste", 'casawp'),
          "TR" => __("Turkey", 'casawp'),
          "TT" => __("Trinidad and Tobago", 'casawp'),
          "TV" => __("Tuvalu", 'casawp'),
          "TW" => __("Taiwan", 'casawp'),
          "TZ" => __("Tanzania, United Republic of", 'casawp'),
          "UA" => __("Ukraine", 'casawp'),
          "UG" => __("Uganda", 'casawp'),
          "UM" => __("United States Minor Outlying Islands", 'casawp'),
          "US" => __("United States", 'casawp'),
          "UY" => __("Uruguay", 'casawp'),
          "UZ" => __("Uzbekistan", 'casawp'),
          "VA" => __("Holy See (Vatican City State)", 'casawp'),
          "VC" => __("Saint Vincent and the Grenadines", 'casawp'),
          "VE" => __("Venezuela", 'casawp'),
          "VG" => __("Virgin Islands, British", 'casawp'),
          "VI" => __("Virgin Islands, U.S.", 'casawp'),
          "VN" => __("Vietnam", 'casawp'),
          "VU" => __("Vanuatu", 'casawp'),
          "WF" => __("Wallis and Futuna", 'casawp'),
          "WS" => __("Samoa", 'casawp'),
          "YE" => __("Yemen", 'casawp'),
          "YT" => __("Mayotte", 'casawp'),
          "RS" => __("Serbia", 'casawp'),
          "ZA" => __("South Africa", 'casawp'),
          "ZM" => __("Zambia", 'casawp'),
          "ME" => __("Montenegro", 'casawp'),
          "ZW" => __("Zimbabwe", 'casawp'),
          "AX" => __("Aland Islands", 'casawp'),
          "GG" => __("Guernsey", 'casawp'),
          "IM" => __("Isle of Man", 'casawp'),
          "JE" => __("Jersey", 'casawp'),
          "BL" => __("Saint Barthelemy", 'casawp'),
          "MF" => __("Saint Martin", 'casawp')
        );
        return $country_arr;
    }

    public function region_arrays(){ // todo: Deutsche Bundesländer hinzufügen
        $region_arr = array(
          "AG" => __("Aargau", 'casawp'),
          "AI" => __("Appenzell Innerrhoden", 'casawp'),
          "AR" => __("Appenzell Ausserrhoden", 'casawp'),
          "BE" => __("Bern", 'casawp'),
          "BL" => __("Basel-Land", 'casawp'),
          "BS" => __("Basel-Stadt", 'casawp'),
          "FR" => __("Fribourg", 'casawp'),
          "GE" => __("Genève", 'casawp'),
          "GL" => __("Glarus", 'casawp'),
          "GR" => __("Graubünden", 'casawp'),
          "JU" => __("Jura", 'casawp'),
          "LU" => __("Luzern", 'casawp'),
          "NE" => __("Neuchâtel", 'casawp'),
          "NW" => __("Nidwalden", 'casawp'),
          "OW" => __("Obwalden", 'casawp'),
          "SG" => __("Sankt Gallen", 'casawp'),
          "SH" => __("Schaffhausen", 'casawp'),
          "SO" => __("Solothurn", 'casawp'),
          "SZ" => __("Schwyz", 'casawp'),
          "TG" => __("Thurgau", 'casawp'),
          "TI" => __("Ticino", 'casawp'),
          "UR" => __("Uri", 'casawp'),
          "VS" => __("Valais", 'casawp'),
          "VD" => __("Vaud", 'casawp'),
          "ZG" => __("Zug", 'casawp'),
          "ZH" => __("Zürich", 'casawp')
        );
        return $region_arr;
    }

    public function casawp_convert_numvalKeyToLabel($key){
        switch ($key) {
            #case 'surface_living':             return __('Living space' ,'casawp');break;
            #case 'surface_usable':             return __('Surface usable' ,'casawp');break;
            case 'surface_property':           return __('Property space' ,'casawp');break;
            case 'area_bwf':                   return __('Living space' ,'casawp');break;
            case 'area_nwf':                   return __('Net living space' ,'casawp');break;
            case 'area_sia_gf':                return __('Gross floor area' ,'casawp');break;
            case 'area_sia_nf':                return __('Surface usable' ,'casawp');break;

            case 'year_renovated':             return __('Year of renovation' ,'casawp');break;
            case 'year_built':                 return __('Year of construction' ,'casawp');break;
            case 'number_of_rooms':            return __('Number of rooms' ,'casawp');break;
            case 'number_of_lavatory':            return __('Number of lavatory' ,'casawp');break;
            case 'number_of_toilets_guest':         return __('Number of guest toilets' ,'casawp');break;
            case 'number_of_floors':           return __('Number of floors' ,'casawp');break;
            case 'floor':                      return __('Floor' ,'casawp');break;
            case 'volume':                     return __('Volume' ,'casawp');break;
            case 'number_of_apartments':       return __('Number of apartments' ,'casawp');break;
            case 'ceiling_height':             return __('Ceiling height' ,'casawp');break;
            case 'hall_height':                return __('Hall height' ,'casawp');break;
            case 'maximal_floor_loading':      return __('Maximal floor loading' ,'casawp');break;
            case 'carrying_capacity_crane':    return __('Carrying capacity crane' ,'casawp');break;
            case 'carrying_capacity_elevator': return __('Carrying capacity elevator' ,'casawp');break;

            /*
            'area_bwf'                    ,
      'area_nwf'                    ,
      'area_sia_gf'                 ,
      'area_sia_nf'*/
        }
    }

    public function casawp_convert_categoryKeyToLabel($key, $fallback = ''){
        $label = null;

        if (substr($key, 0, 7) == 'custom_') {
            $current_lang = function_exists('icl_get_home_url') ? ICL_LANGUAGE_CODE : 'de';
            $translations = get_option('casawp_custom_category_translations');
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
              case 'secondary-rooms': $label = __('Secondary rooms' ,'casawp');break;
              case 'garden':          $label = __('Garden' ,'casawp');break;

              case 'apartment':       $label = __('Apartment' ,'casawp');break;
              case 'attic-flat':      $label = __('Attic Flat' ,'casawp');break;
              case 'bachelor-flat':      $label = __('Bachelor Flat' ,'casawp');break;
              case 'bifamiliar-house':      $label = __('Bifamiliar House' ,'casawp');break;
              case 'building-land':      $label = __('Building Land' ,'casawp');break;
              case 'double-garage':      $label = __('Double Garage' ,'casawp');break;
              case 'duplex':      $label = __('Duplex' ,'casawp');break;
              case 'factory':      $label = __('Factory' ,'casawp');break;
              case 'farm':      $label = __('Farm' ,'casawp');break;
              case 'farm-house':      $label = __('Farm House' ,'casawp');break;
              case 'furnished-flat':      $label = __('Furnished Flat' ,'casawp');break;
              case 'garage':      $label = __('Garage' ,'casawp');break;
              case 'loft':      $label = __('Loft' ,'casawp');break;
              case 'mountain-farm':      $label = __('Mountain Farm' ,'casawp');break;
              case 'multiple-dwelling':      $label = __('Multiple Dwelling' ,'casawp');break;
              case 'open-slot':      $label = __('Open Slot' ,'casawp');break;
              case 'parking-space':      $label = __('Parking Space' ,'casawp');break;
              case 'plot':            $label = __('Plot' ,'casawp');break;
              case 'roof-flat':      $label = __('Roof Flat' ,'casawp');break;
              case 'row-house':      $label = __('Row House' ,'casawp');break;
              case 'single-garage':      $label = __('Single Garage' ,'casawp');break;
              case 'single-house':      $label = __('Single House' ,'casawp');break;
              case 'single-room':      $label = __('Single Room' ,'casawp');break;
              case 'terrace-flat':      $label = __('Terrace Flat' ,'casawp');break;
              case 'terrace-house':      $label = __('Terrace House' ,'casawp');break;
              case 'underground-slot':      $label = __('Underground Slot' ,'casawp');break;
              case 'villa':      $label = __('Villa' ,'casawp');break;
              case 'chalet':      $label = __('Chalet' ,'casawp');break;
              case 'studio':      $label = __('Studio' ,'casawp');break;
              case 'house':           $label = __('House' ,'casawp');break;
              case 'covered-slot':      $label = __('Covered Slot' ,'casawp');break;

              case 'commercial':      $label = __('Commercial' ,'casawp');break;
              case 'gastronomy':      $label = __('Gastronomy' ,'casawp');break;
              case 'vacation':      $label = __('Vacation' ,'casawp');break;
              case 'agriculture':     $label = __('Agriculture' ,'casawp');break;
              case 'industrial':      $label = __('Industrial' ,'casawp');break;
              case 'residential':      $label = __('Residential' ,'casawp');break;
              case 'storage':      $label = __('Storage' ,'casawp');break;
              case 'parking':         $label = __('Parking space' ,'casawp');break;
              case 'building':      $label = __('Building' ,'casawp');break;
              case 'advertising-area':      $label = __('Advertising Area' ,'casawp');break;
              case 'arcade':      $label = __('Arcade' ,'casawp');break;
              case 'atelier':      $label = __('Atelier' ,'casawp');break;
              case 'bakery':      $label = __('Bakery' ,'casawp');break;
              case 'bar':      $label = __('Bar' ,'casawp');break;
              case 'butcher':      $label = __('Butcher' ,'casawp');break;
              case 'café':      $label = __('Café' ,'casawp');break;
              case 'casino':      $label = __('Casino' ,'casawp');break;
              case 'cheese-factory':      $label = __('Cheese Factory' ,'casawp');break;
              case 'club/disco':      $label = __('Club/Disco' ,'casawp');break;
              case 'fuel-station':      $label = __('Fuel Station' ,'casawp');break;
              case 'gardening':      $label = __('Gardening' ,'casawp');break;
              case 'hairdresser':      $label = __('Hairdresser' ,'casawp');break;
              case 'kiosk':      $label = __('Kiosk' ,'casawp');break;
              case 'movie-theater':      $label = __('Movie Theater' ,'casawp');break;
              case 'office':      $label = __('Office' ,'casawp');break;
              case 'practice':      $label = __('Practice' ,'casawp');break;
              case 'restaurant':      $label = __('Restaurant' ,'casawp');break;
              case 'shop':      $label = __('Shop' ,'casawp');break;
              case 'shopping-centre':      $label = __('Shopping Centre' ,'casawp');break;
              case 'hotel':      $label = __('Hotel' ,'casawp');break;


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

    public function casawp_get_allDistanceKeys(){
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

    public function casawp_convert_distanceKeyToLabel($key){
        switch ($key) {
            case 'distance_public_transport':      return __('Public transportation' ,'casawp');break;
            case 'distance_shop':                  return __('Shopping' ,'casawp');break;
            case 'distance_kindergarten':          return __('Kindergarten' ,'casawp');break;
            case 'distance_motorway':              return __('Motorway' ,'casawp');break;
            case 'distance_school1':               return __('Primary school' ,'casawp');break;
            case 'distance_school2':               return __('Secondary school' ,'casawp');break;
            case 'distance_bus_stop':              return __('Bus stop' ,'casawp');break;
            case 'distance_train_station':         return __('Train station' ,'casawp');break;
            case 'distance_post':                  return __('Post' ,'casawp');break;
            case 'distance_bank':                  return __('Bank' ,'casawp');break;
            case 'distance_cable_railway_station': return __('Railway Station' ,'casawp');break;
            case 'distance_boat_dock':             return __('Boat dock' ,'casawp');break;
            case 'distance_airport':               return __('Airport', 'casawp');break;
        }
    }

    public function casawp_convert_availabilityKeyToLabel($key){
        switch ($key) {
            //old
            case 'on-request':   return __('On Request' ,'casawp');break;
            case 'by-agreement': return __('By Agreement' ,'casawp');break;
            case 'immediately':  return __('Immediate' ,'casawp');break;

            //new
            case 'active':       return __('Available' ,'casawp');break;
            case 'reserved':     return __('Reserved' ,'casawp');break;
            case 'sold':         return __('Sold' ,'casawp');break;
            case 'rented':       return __('Rented' ,'casawp');break;
            case 'reference':    return __('Reference' ,'casawp');break;
        }
    }

    public function casawp_convert_featureKeyToLabel($key, $value = false){
        switch ($key) {
            case 'prop_child-friendly':   return __('Child friendly' ,'casawp');break;
            case 'prop_garage':
                if ($value && $value > 1) {
                    return printf( __( '%d garages', 'casawp'), $value );
                } else {
                    return __('Garage' ,'casawp');
                }
            break;
            case 'prop_balcony':
                if ($value) {
                    return printf( __( '%dx balconies', 'casawp'), $value );
                } else {
                    return __('Balcony' ,'casawp');
                }
            break;
            case 'prop_view':   return __('Vista' ,'casawp');break;
            case 'prop_cabletv':   return __('Cable TV' ,'casawp');break;
            case 'prop_parking':
                if ($value) {
                    return printf( __( '%d Parking spaces', 'casawp'), $value );
                } else {
                    return __('Parking space' ,'casawp');
                }
            break;
            case 'animal_allowed':
                if ($value) {
                    return printf( __( '%d Pets allowed', 'casawp'), $value );
                } else {
                    return __('Pets allowed' ,'casawp');
                }
                break;
            case 'isdn':   return __('ISDN Anschluss' ,'casawp');
                break;
            case 'restrooms':
                if ($value && $value != 1) {
                    return printf( __( '%d Restrooms', 'casawp'), $value );
                } else {
                    return __('Restrooms' ,'casawp');
                }
                break;
            case 'prop_elevator':
                if ($value && $value != 1) {
                    return printf( __( '%d elevators', 'casawp'), $value );
                } else {
                    return __('Elevator' ,'casawp');
                }
                break;

            case 'prop_fireplace':          return __('Fireplace' ,'casawp');break;
            case 'wheelchair_accessible':   return __('wheelchair accessible' ,'casawp');break;
            case 'ramp':                    return __('Ramp' ,'casawp');break;
            case 'lifting_platform':        return __('lifting platform' ,'casawp');break;
            case 'railway_terminal':        return __('Railway terminal' ,'casawp');break;
            case 'water_supply':            return __('Water Supply' ,'casawp');break;
            case 'sewage_supply':           return __('Sewage supply' ,'casawp');break;
            case 'power_supply':            return __('Power Supply' ,'casawp');break;
            case 'gas_supply':              return __('Gas supply' ,'casawp');break;
            case 'corner_house':            return __('Corner house' ,'casawp');break;
            case 'middle_house':            return __('Middle house' ,'casawp');break;
            case 'gardenhouse':             return __('Gardenhouse' ,'casawp');break;
            case 'raised_ground_floor':     return __('Raised ground floor' ,'casawp');break;
            case 'new_building':            return __('New building' ,'casawp');break;
            case 'old_building':            return __('Old building' ,'casawp');break;
            case 'under_roof':              return __('Under roof' ,'casawp');break;
            case 'swimmingpool':            return __('Swimmingpool' ,'casawp');break;
            case 'minergie_general':        return __('Minergie general' ,'casawp');break;
            case 'minergie_certified':      return __('Minergie certified' ,'casawp');break;
            case 'under_building_laws':     return __('Under building laws' ,'casawp');break;
            case 'building_land_connected': return __('Building land connected' ,'casawp');break;
            case 'flat_sharing_community':  return __('Flat sharing community' ,'casawp');break;

            default : return $key . ($value ? ': ' . $value : ''); break;
        }
    }

    public function casawp_get_allNumvalKeys(){
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
        'number_of_toilets_guest',
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

    public function casawp_numStringToArray($key, $string){
      
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

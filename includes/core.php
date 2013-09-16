<?php
/**
 * Main functionality
 **/




/** Hook plugin's action and filters **/
function msp_helloworld_init(){

    add_action('init', 'msp_helloworld_taxonomies');
    add_filter('the_content', 'msp_helloworld_author_block_filter');
    add_filter('post_class', 'msp_helloworld_post_class');

}
add_action('plugins_loaded', 'msp_helloworld_init');

/** Register custom taxonomy **/
function msp_helloworld_taxonomies(){

    $args = array(
        'labels' => array(
            'name'          => 'Guest authors',
            'singular_name' => 'Guest author'
        ),
        'show_in_nav_menus' => false
    );

    register_taxonomy('gauthor', array('post'), $args);

}

/** Create author's box markup **/
function msp_helloworld_author_block(){
    global $post;

    $author_terms = wp_get_object_terms($post->ID, 'gauthor');
    if(empty($author_terms))
        return;

    $name = stripslashes($author_terms[0]->name);
    $url = esc_url(get_term_link($author_terms[0]));
    $desc = wp_filter_post_kses($author_terms[0]->description);

    $out = "<div class='gauthor-info'>";
    $out .= "<h5>This is a guest post by <a href='{$url}'>{$name}</a></h5>";
    $out .= "<div class='gauthor-desc'>{$desc}</div></div>";

    return $out;
}

/** Add author's box to the end of the post **/
function msp_helloworld_author_block_filter($content){

    if(is_single())
        $content .= msp_helloworld_author_block();

    return $content;
}

/** Add custom CSS class to the post's container **/
function msp_helloworld_post_class($post_class){
    global $post;

    $author_terms = wp_get_object_terms($post->ID, 'gauthor');
    if(!empty($author_terms)){
        $post_class[] = 'gauthor';
    }

    return $post_class;
}


function casasync_get_single_template(){
    if (get_option( 'casasync_single_template', false )) {
        $template = stripslashes(get_option( 'casasync_single_template', false ));
    } else {
        $template = file_get_contents(CASASYNC_PLUGIN_DIR . '/single-template-default.txt');
    }
    return $template;
}

function casasync_get_archive_template(){
    if (get_option( 'casasync_archive_template', false )) {
        $template = stripslashes(get_option( 'casasync_archive_template', false ));
    } else {
        $template = file_get_contents(CASASYNC_PLUGIN_DIR . '/archive-template-default.txt');
    }
    return $template;
}

function casasync_get_archive_single_template(){
    if (get_option( 'casasync_archive_single_template', false )) {
        $template = stripslashes(get_option( 'casasync_archive_single_template', false ));
    } else {
        $template = file_get_contents(CASASYNC_PLUGIN_DIR . '/archive-template-single-default.txt');
    }
    return $template;
}

function country_arrays()
{
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

function casasync_get_allNumvalKeys(){
    return array(
        'surface_living',
        'surface_property',
        'year_renovated',
        'year_built',
        'number_of_rooms',
        'number_of_floors'
    );
}
function casasync_convert_numvalKeyToLabel($key){
    switch ($key) {
        case 'surface_living':   return __('Living space' ,'casasync');break;
        case 'surface_property': return __('Property space' ,'casasync');break;
        case 'year_renovated':   return __('Year of renovation' ,'casasync');break;
        case 'year_built':       return __('Year of construction' ,'casasync');break;
        case 'number_of_rooms':  return __('Number of rooms' ,'casasync');break;
        case 'number_of_floors': return __('Number of floors' ,'casasync');break;
    }
}

function casasync_convert_categoryKeyToLabel($key){
    switch ($key) {
        case 'agriculture': return __('Agriculture' ,'casasync');break;
        case 'apartment':   return __('Apartment' ,'casasync');break;
        case 'gastronomy': return __('Gastronomy' ,'casasync');break;
        case 'house': return __('House' ,'casasync');break;
        case 'industrial': return __('Industrial' ,'casasync');break;
        case 'parking': return __('Parking space' ,'casasync');break;
        case 'plot': return __('Grundstück' ,'casasync');break;
        case 'secondary-rooms': return __('Secondary rooms' ,'casasync');break;
        case 'garden': return __('Garden' ,'casasync');break;
        case 'commercial': return __('Commercial' ,'casasync');break;
    }
}
function casasync_get_allDistanceKeys(){
    return array(
        'distance_public_transport',
        'distance_shop',
        'distance_kindergarten',
        'distance_motorway',
        'distance_school1',
        'distance_school2'
    );
}
function casasync_convert_distanceKeyToLabel($key){
    switch ($key) {
        case 'distance_public_transport':   return __('Public transportation' ,'casasync');break;
        case 'distance_shop':               return __('Shopping' ,'casasync');break;
        case 'distance_kindergarten':       return __('Kindergarten' ,'casasync');break;
        case 'distance_motorway':           return __('Motorway' ,'casasync');break;
        case 'distance_school1':            return __('Primary school' ,'casasync');break;
        case 'distance_school2':            return __('Secondary school' ,'casasync');break;
    }
}

function casasync_convert_featureKeyToLabel($key, $value = false){
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

        case 'prop_fireplace':   return __('Fireplace' ,'casasync');break;
        case 'wheelchair_accessible':   return __('wheelchair accessible' ,'casasync');break;
        case 'ramp':   return __('Ramp' ,'casasync');break;
        case 'lifting_platform':   return __('lifting platform' ,'casasync');break;
        case 'railway_terminal':   return __('Railway terminal' ,'casasync');break;
        case 'water_supply':   return __('Water Supply' ,'casasync');break;
        case 'sewage_supply':   return __('Sewage supply' ,'casasync');break;
        case 'power_supply':   return __('Power Supply' ,'casasync');break;
        case 'gas_supply':   return __('Gas supply' ,'casasync');break;
        case 'corner_house':   return __('Corner house' ,'casasync');break;
        case 'middle_house':   return __('Middle house' ,'casasync');break;
        case 'gardenhouse':   return __('Gardenhouse' ,'casasync');break;
        case 'raised_ground_floor':   return __('Raised ground floor' ,'casasync');break;
        case 'new_building':   return __('New building' ,'casasync');break;
        case 'old_building':   return __('Old building' ,'casasync');break;
        case 'under_roof':   return __('Under roof' ,'casasync');break;
        case 'swimmingpool':   return __('Swimmingpool' ,'casasync');break;
        case 'minergie_general':   return __('Minergie general' ,'casasync');break;
        case 'minergie_certified':   return __('Minergie certified' ,'casasync');break;
        case 'under_building_laws':   return __('Under building laws' ,'casasync');break;
        case 'building_land_connected':   return __('Building land connected' ,'casasync');break;
        case 'flat_sharing_community':   return __('Flat sharing community' ,'casasync');break;

        default : return $key . ($value ? ': ' . $value : ''); break;
    }
}

function countrycode_to_countryname($cc='')
{
    $arr = country_arrays();
        if(isset($arr[$cc])){
            return $arr[$cc];
        } else {
            return $cc;
        }
}

function form_countryoptions($cc='')
{
    $arr = country_arrays();
    foreach ($arr as $k => $v) {
   if($k == $cc) $opt .= '<option value="'.$k.'" selected="selected">'.$v.'</option>';
   else $opt .= '<option value="'.$k.'">'.$v.'</option>';
    }
    return $opt;
}




function casasync_numStringToArray($string){
    $si = false;
    if (!$string) {
        return false;
    }
    if (strlen($string) == 1) {
        if (!is_numeric($string[0])) {
            $string = false;
        }
    } elseif (strlen($string) == 2) { // 23 or m2 or km or 1m
        $first = $string[strlen($string)-2];
        $second = $string[strlen($string)-1];

        //avoid float dots to be considered as SI
        $first = ($first == '.' ? 0 : $first);
        $second = ($first == '.' ? 0 : $first);

        if ( !is_numeric($string[0]) ) { //m2 or km
            $string = false;
        } elseif (is_numeric($first) && !is_numeric($second)) { // 1m
            $string = substr($string, 0, -1);
            $si = $second;
        }
    } elseif (strlen($string) > 2) { //123 or 1m2 or 1km or 12m
        $first = $string[strlen($string)-3];
        $second = $string[strlen($string)-2];
        $third = $string[strlen($string)-1];

        //avoid float dots to be considered as SI
        $first = ($first == '.' ? 0 : $first);
        $second = ($second == '.' ? 0 : $second);
        $third = ($third == '.' ? 0 : $third);

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

    return array('value' => (FLOAT) $string, 'si' => $si);
}


function contact_fn( $atts ) {

    extract( shortcode_atts( array(
        'recipients' => 'Jens Stalder:js@casasoft.ch',
        'ccs' => '',
        'post_id' => false
    ), $atts ) );
    $errors = array();
    $table = '';
    $validation = false;



    $rec_ar1 = explode(';', $recipients);
    $recipientses = array();
    foreach ($rec_ar1 as $key => $value) {
        $recipientses[] = explode(':', trim(str_replace('<br />', '', $value)));
    }

    $cc_ar1 = explode(';', $ccs);
    $ccs_arr = array();
    foreach ($cc_ar1 as $key => $value) {
        $ccs_arr[] = explode(':', trim(str_replace('<br />', '', $value)));
    }



    //labels and whitelist
    $fieldlabels = array(
        'firstname' => __('First name', 'casasync'), //'Vorname',
        'lastname'  => __('Last name', 'casasync'), //'Nachname',
        'emailreal' => __('Email', 'casasync'), //'E-Mail',
        'salutation'   => __('Salutation', 'casasync'), //'Anrede',
        'title'   => __('Title', 'casasync'), //'Titel',
        'phone'   => __('Phone', 'casasync'), //'Telefon',
        'email'   => 'E-Mail SPAM!',
        'company'   => __('Company', 'casasync'), //'Firma',
        'street'   => __('Street', 'casasync'), //'Strasse',
        'postal_code'   => __('ZIP', 'casasync'), //'PLZ',
        'locality'   => __('Locality', 'casasync'), //'Stadt',
        'state'   => __('Kanton', 'casasync'), //'Kanton',
        'subject'   => __('Subject', 'casasync'), //'Betreff',
        'message'   => __('Message', 'casasync'), //'Nachricht',
        'recipient'   => __('Recipient', 'casasync'), //'Rezipient',
    );




    if (!empty($_POST)) {
        $validation = true;
        $required = array(
            'firstname',
            'lastname',
            'emailreal',
            'subject',
            'street',
            'postal_code',
            'locality'
        );
        $companyname = get_bloginfo( 'name' );
        $companyAddress = '{STREET}
                <br />
                CH-{ZIP} {CITY}
                <br />
                Tel. {PHONE}
                <br />
                Fax {FAX}';

        //not alowed fields!!!
        foreach($_POST as $key => $value){
            if (!array_key_exists($key, $fieldlabels)) {
                $errors[] = '<b>Form ERROR!</b>: please contact the administrator. Ilegal Field has been posted[' . $key . ']'; //ausfüllen
                $validation = false;
            }
        }

        //required
        foreach ($required as $name) {
            if (array_key_exists($name, $_POST)) {
                if (!$_POST[$name]) {
                    $errors[] = '<b>' . $fieldlabels[$name] . '</b>: ' . __('Required', 'casasync'); //ausfüllen
                    $validation = false;
                }
            }
        }
        //spam
        if ($_POST['email']) {
            $validation = false;
        }
        if ($validation) {

            $casa_id = get_post_meta( $post_id, 'casasync_id', $single = true );
            $casa_id_arr = explode('_', $casa_id);
            $customer_id = $casa_id_arr[0];
            $property_id = $casa_id_arr[1];

            
            //REM
            if (get_option('casasync_remCat', false ) && get_option('casasync_remCat_email', false )) {
                $categories = wp_get_post_terms( get_the_ID(), 'casasync_category'); 
                if ($categories) {
                    $type = casasync_convert_categoryKeyToLabel($categories[0]->name); 
                } else {
                    $type = '';
                }

                $remCat = array(
                    0  => $_SERVER['SERVER_NAME'],
                    1  => get_post_meta( $post_id, 'seller_org_legalname', true ),
                    2  => get_post_meta( $post_id, 'seller_org_address_streetaddress', true ),
                    3  => get_post_meta( $post_id, 'seller_org_address_postalcode', true ),
                    4  => get_post_meta( $post_id, 'seller_org_address_locality', true ),
                    5  => get_post_meta( $post_id, 'seller_person_givenname', true ) . ' ' . get_post_meta( $post_id, 'seller_person_familyname', true ),
                    6  => filter_input(INPUT_POST, 'emailreal', FILTER_VALIDATE_EMAIL),
                    7  => $property_id,
                    8  => get_permalink($post_id),
                    9  => get_post_meta($post_id, 'casasync_property_address_streetaddress', true),
                    10 => get_post_meta($post_id, 'casasync_property_address_locality', true),
                    11 => $type,
                    12 => 'DE', //LANG
                    13 => '', //anrede
                    14 => (isset($_POST['firstname']) ? $_POST['firstname'] : ''),
                    15 => (isset($_POST['lastname']) ? $_POST['lastname'] : ''),
                    16 => (isset($_POST['company']) ? $_POST['company'] : ''),
                    17 => (isset($_POST['street']) ? $_POST['street'] : ''),
                    18 => (isset($_POST['postal_code']) ? $_POST['postal_code'] : ''),
                    19 => (isset($_POST['locality']) ? $_POST['locality'] : ''),
                    20 => (isset($_POST['phone']) ? $_POST['phone'] : ''),
                    21 => (isset($_POST['mobile']) ? $_POST['mobile'] : ''),
                    22 => (isset($_POST['fax']) ? $_POST['fax'] : ''),
                    23 => (isset($_POST['emailreal']) ? $_POST['emailreal'] : ''),
                    24 => (isset($_POST['message']) ? $_POST['message'] : ''),
                    25 => '',
                    26 => ''
                );
                $remCat_str = '';
                foreach ($remCat as $key => $value) {
                    $remCat_str .= '#' . $value;
                }

                $header  = "From: \"\" <remcat@casasync.ch>\r\n";
                $header .= "MIME-Version: 1.0\r\n";
                $header .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";

                wp_mail(get_option('casasync_remCat_email', false), 'Neue Anfrage', utf8_decode($remCat_str), $header);
            }

            $template = file_get_contents(CASASYNC_PLUGIN_DIR . 'email_templates/message_de.html');

            $the_thumbnail = '';
            $thumbnail = get_the_post_thumbnail($post_id, array(250, 250));

            if ( $thumbnail ) { $the_thumbnail = $thumbnail; }

            $thumb  = '<table border="0">';
            $thumb .= '<tr>';
            $thumb .= '<td><a href="' . get_permalink($post_id) . '">' . $the_thumbnail . '</a></td>';
            $thumb .= '</tr>';
            $thumb .= '</table>';

            $message = '<table width="100%">';
            foreach($_POST as $key => $value){
                if (array_key_exists($key, $fieldlabels)) {
                    if ($key != 'email') {
                        $message.= '<tr><td align="left" style="padding-right:10px" valign="top"><strong>'.$fieldlabels[$key].'</strong></td><td align="left">' . nl2br($value) . '</td></tr>';
                    }
                }
            }
            if ($post_id) {
                $message .= '<tr></td colspan="2">&nbsp;</td></tr>';
                $message .= '<tr>';
                $message .= '<td colspan="2" class="property"><a href="' . get_permalink($post_id) . '" style="text-decoration: none; color: #969696; font-weight: bold; font-family: Helvetica, Arial, sans-serif;">Objekt anzeigen ...</a></td>';
                $message .= '</tr>';
            }
            $message.='</table>';

            $template = str_replace('{:logo_src:}', '#', $template);
            $template = str_replace('{:logo_url:}', '#', $template);
            $template = str_replace('{:site_title:}', $_SERVER['SERVER_NAME'], $template);
            $template = str_replace('{:domain:}', $_SERVER['SERVER_NAME'], $template);

            $template = str_replace('{:src_social_1:}', '#', $template);
            $template = str_replace('{:src_social_2:}', '#', $template);
            $template = str_replace('{:src_social_3:}', '#', $template);
            $template = str_replace('{:sender_title:}', get_the_title( $post_id ), $template);

            if ($message) {
                $template = str_replace('{:message:}', $message, $template);
            }

            if ($thumb) {
                $template = str_replace('{:thumb:}', $thumb, $template);
            }

            $template = str_replace('{:support_email:}', 'support@casasoft.ch', $template);
            $template = str_replace('{:href_mapify:}', 'http://'. $_SERVER['SERVER_NAME'], $template);
            $template = str_replace('{:href_casasoft:}', 'http://casasoft.ch', $template);

            $template = str_replace('{:href_social_1:}', '#', $template);
            $template = str_replace('{:href_social_2:}', '#', $template);
            $template = str_replace('{:href_social_3:}', '#', $template);

            $template = str_replace('{:href_message_archive:}','http://'. $_SERVER['SERVER_NAME'] . '', $template);
            $template = str_replace('{:href_message_edit:}', '#', $template);

            $sender_email    = filter_input(INPUT_POST, 'emailreal', FILTER_VALIDATE_EMAIL);
            $sender_fistname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
            $sender_lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);

            $header  = "From: \"$sender_fistname $sender_lastname\" <$sender_email>\r\n";
            $header .= "MIME-Version: 1.0\r\n";
            $header .= "Content-Type: text/html; charset=UTF-8\r\n";

            // :CC
            /*$the_ccs = array();
            if (isset($css_arr) && $css_arr) {
                foreach ($ccs_arr as $cc) {
                    $the_ccs[] = $cc[1];
                }
                $the_cc = implode(', ', $the_ccs);

                $headers .= "Cc: " . $the_cc . "\r\n";
            }*/

            foreach ($recipientses as $recipient2) {
                if (isset($recipient2[1])) {
                    if (wp_mail($recipient2[1], 'Neue Anfrage', $template, $header)) {
                        return '<p class="alert alert-success">Vielen Dank!</p>';
                    } else {
                        return '<p class="alert alert-error">Fehler!</p>';
                    }
                } else {
                    if (isset($remCat)) {
                        return '<p class="alert alert-success">Vielen Dank!</p>';
                    } else {
                        return '<p class="alert alert-error">Fehler!</p>';
                    }
                }
            }
        }

    } else {
        $validation = false;
    }

    $form = '';
    if (!$validation) {
        ob_start();

        if ($errors) {
            echo '<div class="alert alert-error">';
            echo "<strong>" . __('Please consider the following errors and try sending it again', 'casasync')  . "</strong>"; //Bitte beachten Sie folgendes und versuchen Sie es erneut
            echo "<ul>";
            echo "<li>".implode('</li><li>', $errors) . '</li>';
            echo '</ul>';
            echo "</div>";
        }
        echo $table;
    ?>
        <form class="form casasync-property-contact-form" id="casasyncPropertyContactForm" method="POST" action="">
            <input type="hidden" name="email" value="" />
                <div class="row-fluid">
                    <div class="span5">
                        <label for="firstname"><?php echo __('First name', 'casasync') ?></label>
                        <input name="firstname" class="span12" value="<?php echo (isset($_POST['firstname']) ? $_POST['firstname'] : '') ?>" type="text" id="firstname" />
                    </div>
                    <div class="span7">
                        <label for="lastname"><?php echo __('Last name', 'casasync') ?></label>
                        <input name="lastname" class="span12" value="<?php echo (isset($_POST['lastname']) ? $_POST['lastname'] : '') ?>" type="text" id="lastname" />
                    </div>
                </div>
                <div class="row-fluid">
                </div>
                <div class="row-fluid">
                    <label for="emailreal"><?php echo __('Email', 'casasync') ?></label>
                    <input name="emailreal" class="span12" value="<?php echo (isset($_POST['emailreal']) ? $_POST['emailreal'] : '') ?>" type="text" id="emailreal" />
                </div>
                <div class="row-fluid">
                    <label for="street"><?php echo __('Street', 'casasync') ?></label>
                    <input name="street" class="span12" value="<?php echo (isset($_POST['street']) ? $_POST['street'] : '') ?>"  type="text" id="street" />
                </div>
                <div class="row-fluid">
                    <div class="span4">
                        <label for="postal_code"><?php echo __('ZIP', 'casasync') ?></label>
                        <input name="postal_code" class="span12" value="<?php echo (isset($_POST['postal_code']) ? $_POST['postal_code'] : '') ?>"  value="<?php echo (isset($_POST['postal_code']) ? $_POST['postal_code'] : '') ?>" type="text" id="postal_code" />
                    </div>
                    <div class="span8">
                        <label for="locality"><?php echo __('Locality', 'casasync') ?></label>
                        <input name="locality" class="span12" value="<?php echo (isset($_POST['locality']) ? $_POST['locality'] : '') ?>"  type="text" id="locality" />
                    </div>
                </div>
                <div class="row-fluid">
                    <label for="phone"><?php echo __('Phone', 'casasync') ?></label>
                    <input name="phone" class="span12" value="<?php echo (isset($_POST['phone']) ? $_POST['phone'] : '') ?>"  type="text" id="tel" />
                </div>
                <div class="row-fluid">
                    <div class="span12">
                        <label for="message"><?php echo __('Message', 'casasync') ?></label>
                        <textarea name="message" class="span12" id="message"><?php echo (isset($_POST['message']) ? $_POST['message'] : '') ?></textarea>
                    </div>
                </div>
                <div class="row-fluid">
                    <div class="span7"><br>
                        <small><?php echo __('Please fill out all the fields', 'casasync') ?></small>
                    </div>
                    <div class="span5"><br>
                        <input type="submit" class="btn btn-primary pull-right" value="<?php echo __('Send', 'casasync') ?>" />
                    </div>
                </div>
            </form>

        <?php
        $form = ob_get_contents();
        ob_end_clean();
    } //validation

    return $form;
}
add_shortcode( 'casasync_contact', 'contact_fn' );


function casasyncSinglePrev($class = ''){

    $prev_post_obj  = get_adjacent_post( '', '', true );
    $prev_post_ID   = isset( $prev_post_obj->ID ) ? $prev_post_obj->ID : '';
    $prev_post_link     = get_permalink( $prev_post_ID );
    $prev_post_title    = '<i class="icon icon-arrow-right"></i>';

    return '<a href="' . $prev_post_link . '" rel="previous" class="casasync-single-prev ' . $class . '">' . $prev_post_title . '</a>';
}

function casasyncSingleNext($class = ''){

    $next_post_obj  = get_adjacent_post( '', '', false );
    $next_post_ID   = isset( $next_post_obj->ID ) ? $next_post_obj->ID : '';
    $next_post_link     = get_permalink( $next_post_ID );
    $next_post_title    = '<i class="icon icon-arrow-left"></i>';

    return '<a href="' . $next_post_link . '" rel="next" class="casasync-single-next ' . $class . '">' . $next_post_title . '</a>';
}


function casasync_get_string_between($string, $start, $end){
    $string = " ".$string;
    $pos = strpos($string,$start);
    if ($pos == 0) return "";
        $pos += strlen($start);
        $len = strpos($string,$end,$pos) - $pos;
    return substr($string,$pos,$len);
}

function casasync_template_set_if($template, $tagslug, $value){
    for ($i=0; $i < 3; $i++) {
        if ($value) {
            $before = casasync_get_string_between($template, "{if_".$tagslug."}", "{".$tagslug."}");
            $after = casasync_get_string_between($template, "{".$tagslug."}", "{end_if_".$tagslug."}");
            $template = str_replace($before.'{'.$tagslug.'}'.$after, $before . $value . $after, $template);
            $template = str_replace('{if_'.$tagslug.'}', '', $template);
            $template = str_replace('{end_if_'.$tagslug.'}', '', $template);
        } else {
            $rm = casasync_get_string_between($template, "{if_".$tagslug."}", "{end_if_".$tagslug."}");
            $template = str_replace("{if_".$tagslug."}" . $rm . "{end_if_".$tagslug."}", '', $template);
            $template = str_replace("{".$tagslug."}", '', $template);
        }
    }

    return $template;
}


function casasync_interpret_gettext($template){
    $finished = false;
    while ($finished == false) {
        $translatable_str = casasync_get_string_between($template, "__(", ")");
        if ($translatable_str) {
            $template = str_replace("__(" . $translatable_str . ")", __($translatable_str, 'casasync'), $template);
        } else {
            $finished = true;
        }
        
    }
    return $template;
}

function casasync_template_set_if_not($template, $tagslug, $value){
    for ($i=0; $i < 3; $i++) {
        if ($value) {
            $rm = casasync_get_string_between($template, "{!if_".$tagslug."}", "{!end_if_".$tagslug."}");
            $template = str_replace("{!if_".$tagslug."}" . $rm . "{!end_if_".$tagslug."}", '', $template);
        } else {
            $template = str_replace("{!if_".$tagslug."}", '', $template);
            $template = str_replace("{!end_if_".$tagslug."}", '', $template);
        }
    }


    return $template;
}
?>
<?php
  namespace CasaSync;

  class Single {  
    public $conversion = null;

    public $attachments = array();
    public $documents = array();
    public $plans = array();
    public $address_street = false;
    public $address_streetnumber = false;
    public $address_postalcode = false;
    public $address_region = false;
    public $address_locality = false;
    public $address_country = false;
    public $address_country_name = false;
    public $casa_id = false;
    public $customer_id = false;
    public $property_id = false;
    public $reference_id = false;
    public $property = false;
    public $tag_values = array();
    public $categories_names = array();
    public $floors = array();
    public $basises = array();
    public $main_basis = 'buy';
    public $prices = array();
    public $price_currency = 'CHF';
    public $numvals = array();
    public $features = array();
    public $content = '';
    public $content_parts = array();
    public $urls = array();
    public $availability = false;
    public $availability_label = '';
    public $seller = array(
      'fallback' => false,
      'country' => '',
      'locality' => '',
      'region' => '',
      'postalcode' => '',
      'street' => '',
      'legalname' => '',
      'email' => '',
      'fax' => '',
      'phone_direct' => '',
      'phone_central' => '',
      'phone_mobile' => '',
    );
    public $salesperson = array(
      'function' => '',
      'givenname' => '',
      'familyname' => '',
      'email' => '',
      'fax' => '',
      'phone_direct' => '',
      'phone_central' => '',
      'phone_mobile' => '',
      'gender' => '',
      'honorific' => false,
    );

    public function __construct($post){ 
      $this->conversion = new Conversion;
      $this->setProperty($post);      
    }  

    public function setProperty($post){
      $this->property = $post;

      $this->attachments = get_posts( array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'post_parent' => $post->ID,
        //'exclude'     => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'image',
        'orderby' => 'menu_order',
        'order' => 'ASC'
      ) ); 

      $this->documents = get_posts( array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'post_parent' => $post->ID,
        //'exclude'     => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'document',
        'orderby' => 'menu_order'
      ) ); 

      $this->plans = get_posts( array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'post_parent' => $post->ID,
        //'exclude'     => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'plan'
      ) ); 

      $this->address_street = get_post_meta( get_the_ID(), 'casasync_property_address_streetaddress', $single = true );
      $this->address_streetnumber = get_post_meta( get_the_ID(), 'casasync_property_address_streetnumber', $single = true );
      $this->address_postalcode = get_post_meta( get_the_ID(), 'casasync_property_address_postalcode', $single = true );
      $this->address_region = get_post_meta( get_the_ID(), 'casasync_property_address_region', $single = true );
      $this->address_locality = get_post_meta( get_the_ID(), 'casasync_property_address_locality', $single = true );
      $this->address_country = get_post_meta( get_the_ID(), 'casasync_property_address_country', $single = true );
      $this->address_country_name = $this->conversion->countrycode_to_countryname($this->address_country);


      $this->casa_id = get_post_meta( get_the_ID(), 'casasync_id', $single = true );
      $casa_id_arr = explode('_', $this->casa_id);
      $this->customer_id = $casa_id_arr[0];
      $this->property_id = $casa_id_arr[1];

      $this->reference_id = get_post_meta( get_the_ID(), 'casasync_referenceId', $single = true );

      $start = get_post_meta( get_the_ID(), 'casasync_start', $single = true );

      $categories = wp_get_post_terms( get_the_ID(), 'casasync_category'); 
      $this->categories_names = array();
      foreach ($categories as $category) {
        $this->categories_names[] = $this->conversion->casasync_convert_categoryKeyToLabel($category->name);
      } 

      $floors = get_post_meta( get_the_ID(), 'casasync_floors', $single = true ); 
      if ($floors) {
        $floors_quirk = trim($floors,"[");   
        $floors_quirk = trim($floors_quirk,"]");
        $floors_arr = explode(']+[', $floors_quirk);
        $largest_val = 0;
        $largest_key = 0;
        foreach ($floors_arr as $key => $value) {
          if ((int)$value > $largest_val ) {
            $largest_val = (int)$value;
            $largest_key = $key;
          }
          $this->floors[] = $value . '. Stock' . ($value == '0' ? ' (EG)' : '');
        }
        if (isset($this->floors[$largest_key])) {
          $this->floors[$largest_key] = $floors_arr[$largest_key] . '. Stock (OG)';
        }
      }
      
      $basis = wp_get_post_terms( get_the_ID(), 'casasync_salestype'); 
      $basis_slugs = array();
      foreach ($basis as $basi) {
        $this->basises[] = __(ucfirst($basi->name),'casasync');
        $basis_slugs[] = $basi->slug;
      } 
      if ($basis_slugs) {
        $this->main_basis = $basis_slugs[0];
      }

      $this->price_currency = get_post_meta( get_the_ID(), 'price_currency', $single = true );

      $sales['num'] = get_post_meta( get_the_ID(), 'price', $single = true );
      $sales['propertysegment'] = get_post_meta( get_the_ID(), 'price_propertysegment', $single = true );
      $sales['timesegment'] = get_post_meta( get_the_ID(), 'price_timesegment', $single = true );

      $net['num'] = get_post_meta( get_the_ID(), 'grossprice', $single = true );
      $net['propertysegment'] = get_post_meta( get_the_ID(), 'grossprice_propertysegment', $single = true );
      $net['timesegment'] = get_post_meta( get_the_ID(), 'grossprice_timesegment', $single = true );

      $gross['num'] = get_post_meta( get_the_ID(), 'netPrice', $single = true );
      $gross['propertysegment'] = get_post_meta( get_the_ID(), 'netPrice_propertysegment', $single = true );
      $gross['timesegment'] = get_post_meta( get_the_ID(), 'netPrice_timesegment', $single = true );

      $extra_costs_json =  get_post_meta( get_the_ID(), 'extraPrice', $single = true ); 
      $extra_costs_arr = array();
      if ($extra_costs_json) {
        $extra_costs_arr = json_decode($extra_costs_json, true);
      }

      $this->prices = array(
        'sales' => $sales,
        'net' => $net,
        'gross' => $gross,
        'extra_costs' => $extra_costs_arr
      );

      $this->urls = json_decode(get_post_meta( get_the_ID(), 'casasync_urls', $single = true ), true);

      foreach ($this->conversion->casasync_get_allNumvalKeys() as $numval_key) {
        $numval = get_post_meta( get_the_ID(), $numval_key, $single = true );
        if ($numval) {
          $title = $this->conversion->casasync_convert_numvalKeyToLabel($numval_key);
          $this->numvals[$numval_key] = array('title' => $title, 'value' => $numval, 'key' => $numval_key);
        }
      }

      $features_json = get_post_meta( get_the_ID(), 'casasync_features', $single = true );
      if ($features_json) {
        $this->features = json_decode($features_json, true);
      }

      $this->content = get_the_content(); 
      $this->content_parts = explode('<hr class="property-separator" />', $this->content);

      /*$own_seller_email = false;
      $emails = array();
      if (!get_option('casasync_seller_email_block')) {
        $email = get_post_meta( get_the_ID(), 'seller_inquiry_person_email', true );
        if ($email) {
          $firstname = get_post_meta( get_the_ID(), 'seller_inquiry_person_givenname', true );
          $lastname = get_post_meta( get_the_ID(), 'seller_inquiry_person_familyname', true );
          $gender    = get_post_meta( get_the_ID(), 'seller_inquiry_person_gender', true);
          if ($gender == 'F') {
            $honorific = 'Frau';
          } elseif ($gender == 'M') {
            $honorific = 'Herr';
          } else {
            $honorific = false;
          }
          $name = ($honorific ? $honorific . ' ' : '') . ($firstname ? $firstname . ' ' : '') . $lastname;
          $emails[] = ($name ? $name : 'Kontaktperson') . ':' . $email;
          $own_seller_email = true;
        }
      }
      if (get_option('casasync_sellerfallback_email_use') != 'never') {
        if (($own_seller_email == false && get_option('casasync_sellerfallback_email_use') == 'fallback') || (get_option('casasync_sellerfallback_email_use') == 'always')) {
          $email = get_option('casasync_sellerfallback_email');
          if ($email) {
            $name = get_option('casasync_sellerfallback_legalname');
            $emails[] = ($name ? $name : 'Hauptanbieter') . ':' . $email;
          }
        }
      }

      $has_remcat = false;
      if (get_option('casasync_remCat', false ) && get_option('casasync_remCat_email', false )) {
        $has_remcat = true; 
      }*/

      if (get_option('casasync_seller_show') == 1) {
        $this->seller['fallback'] = true;
        $this->seller['country'] = get_post_meta( get_the_ID(), 'seller_org_address_country', $single = true );
        $this->seller['locality'] = get_post_meta( get_the_ID(), 'seller_org_address_locality', $single = true );
        $this->seller['region'] = get_post_meta( get_the_ID(), 'seller_org_address_region', $single = true );
        $this->seller['postalcode'] = get_post_meta( get_the_ID(), 'seller_org_address_postalcode', $single = true );
        $this->seller['street'] = get_post_meta( get_the_ID(), 'seller_org_address_streetaddress', $single = true );

        //$seller_address  = ($seller_address_street ? $seller_address_street . '<br>' : '');
        //$seller_address .= ($seller_address_postalcode ?  $seller_address_postalcode . ' ': '') . ($seller_address_locality ? $seller_address_locality : '') . ($seller_address_postalcode || $seller_address_locality ? '<br>' : '');
        //$seller_address .= countrycode_to_countryname($seller_address_country); 

        $this->seller['legalname'] = get_post_meta( get_the_ID(), 'seller_org_legalname', $single = true );
        $this->seller['email'] = get_post_meta( get_the_ID(), 'seller_org_email', $single = true );
        $this->seller['fax'] = get_post_meta( get_the_ID(), 'seller_org_fax', $single = true );
        $this->seller['phone_direct'] = get_post_meta( get_the_ID(), 'seller_org_phone_direct', $single = true );
        $this->seller['phone_central'] = get_post_meta( get_the_ID(), 'seller_org_phone_central', $single = true );
        $this->seller['phone_mobile'] = get_post_meta( get_the_ID(), 'seller_org_phone_mobile', $single = true );
        
        /*if (
          $seller_address 
          . $sellerlegalname
          . $selleremail
          . $sellerfax
          . $sellerphone_direct
          . $sellerphone_central 
          . $sellerphone_mobile
        ) {
          $own_seller = true;
          $has_seller = true;
        }*/

      }
      if (get_option('casasync_sellerfallback_show') == 1) {
        if (!$this->hasSeller()) {
          $this->seller['country'] = get_option('casasync_sellerfallback_address_country');
          $this->seller['locality'] = get_option('casasync_sellerfallback_address_locality');
          $this->seller['region'] = get_option('casasync_sellerfallback_address_region');
          $this->seller['postalcode'] = get_option('casasync_sellerfallback_address_postalcode');
          $this->seller['street'] = get_option('casasync_sellerfallback_address_street');

          //$seller_address  = ($seller_address_street ? $seller_address_street . '<br>' : '');
          //$seller_address .= ($seller_address_postalcode ?  $seller_address_postalcode . ' ': '') . ($seller_address_locality ? $seller_address_locality : '') . ($seller_address_postalcode || $seller_address_locality ? '<br>' : '');
          //$seller_address .= countrycode_to_countryname($seller_address_country); 

          $this->seller['legalname'] = get_option('casasync_sellerfallback_legalname');
          $this->seller['email'] = get_option('casasync_sellerfallback_email');
          $this->seller['fax'] = get_option('casasync_sellerfallback_fax');
          $this->seller['phone_direct'] = get_option('casasync_sellerfallback_phone_direct');
          $this->seller['phone_central'] = get_option('casasync_sellerfallback_phone_central');
          $this->seller['phone_mobile'] = get_option('casasync_sellerfallback_phone_mobile');

          /*if (
            $seller_address 
            . $sellerlegalname
            . $selleremail
            . $sellerfax
            . $sellerphone_direct
            . $sellerphone_central 
            . $sellerphone_mobile
          ) {
            $own_seller = false;
            $has_seller = true;
          }*/
        }
      }
      

      $this->salesperson['function']        = get_post_meta( get_the_ID(), 'seller_person_function', true);
      $this->salesperson['givenname']       = get_post_meta( get_the_ID(), 'seller_person_givenname', true);
      $this->salesperson['familyname']      = get_post_meta( get_the_ID(), 'seller_person_familyname', true);
      $this->salesperson['email']           = get_post_meta( get_the_ID(), 'seller_person_email', true);
      $this->salesperson['fax']             = get_post_meta( get_the_ID(), 'seller_person_fax', true);
      $this->salesperson['phone_direct']    = get_post_meta( get_the_ID(), 'seller_person_phone_direct', true);
      $this->salesperson['phone_central']   = get_post_meta( get_the_ID(), 'seller_person_phone_central', true);
      $this->salesperson['phone_mobile']    = get_post_meta( get_the_ID(), 'seller_person_phone_mobile', true);
      $this->salesperson['gender']    = get_post_meta( get_the_ID(), 'seller_person_phone_gender', true);
      if ($this->salesperson['gender'] == 'F') {
        $this->salesperson['honorific'] = 'Frau';
      } elseif ($this->salesperson['gender'] == 'M') {
        $this->salesperson['honorific'] = 'Herr';
      }

      $this->availability = get_post_meta( get_the_ID(), 'availability', $single = true );
      $this->availability_label = get_post_meta( get_the_ID(), 'availability_label', $single = true );
      if ($this->availability && !$this->availability_label) {
        $this->availability_label = $this->availabilitySlug2Label($this->availability);
      }
    }

    public function getGallery($size = 'large'){

      if ($this->attachments) {
        $return = '<div class="carousel slide" id="slider_'.get_the_ID().'" data-ride="carousel" data-interval="false">';
          
          //indicators
          $return .= '<ol class="carousel-indicators">';
          $i = 0;
          foreach ($this->attachments as $attachment) {
            $return .= '<li data-target="#slider_'.get_the_ID().'" data-slide-to="'.$i.'" class="'.($i==0?'active':'').'"></li>';  
            $i++;
          }
          $return .= '</ol>';

          //Wrapper for slides
          $return .= '<div class="carousel-inner">';
            $i = 0;
            foreach ($this->attachments as $attachment) {
              $return .= '<div class="item '.($i==0?'active':'').'">';
                
                if ($size == 'large') {
                  $img = wp_get_attachment_image( $attachment->ID, 'full', true );
                } else{
                  $img = wp_get_attachment_image( $attachment->ID, 'casasync-thumb', true );
                }
                $return .= $img;
                if ($size == 'large') {
                  $return .= '<div class="carousel-caption">';
                    $return .= '<h3>' . (!is_numeric($attachment->post_title) ? $attachment->post_title : get_the_title()) . '</h3>';
                    $return .= '<p>' . $attachment->post_excerpt . '</p>';
                  $return .= '</div>';
                }
                
              $return .= '</div>';
              $i++;
            }
          $return .= '</div>';

          //controlls
          $return .= '<a class="left carousel-control" href="#slider_'.get_the_ID().'" data-slide="prev">
            <span class="glyphicon glyphicon-chevron-left"></span>
          </a>
          <a class="right carousel-control" href="#slider_'.get_the_ID().'" data-slide="next">
            <span class="glyphicon glyphicon-chevron-right"></span>
          </a>';

        $return .= '</div>';
        return $return;
      }
    }

    public function getTitle(){
      return get_the_title();
    }


    public function getBasicBoxes(){
      $content = '<div class="casasync-basic-box"><div>';
        $content .= '<h4>'.implode(', ', $this->categories_names).'</h4>';
        if ($this->getNumval('rooms') && $wellcount < $lines){
          $content .= __("Rooms:", 'casasync') . ' ' . $this->getNumval('rooms') . '<br>';
        }
        if ($this->floors && !isset($this->floors[0]) && $wellcount < $lines){
          $content .= __("Floor:", 'casasync');
          $content .= $this->floors[0];
        }
        if ($this->getNumval('surface_living') && $wellcount < $lines){
          $content .= __("Living space:", 'casasync') . ' ' . $this->getNumval('surface_living') . '<sup>2</sup>';
        }
      $content .= '</div></div>';
      $content .= '<div class="casasync-basic-box"><div>';
        $content .= '<h4>' . __("Address", 'casasync') . '</h4>';
        if ($this->getAddress('property')){
          $content .= $this->getAddress('property');  
        };
      $content .= '</div></div>';
      $content .= '<div class="casasync-basic-box"><div>';
        $content .= '<h4>' . implode(', ', $this->basises) . '</h4>';
        if ($this->getPrice()):
          if ($this->getPrice()):
            $content .= $this->getPrice('formated') . ' ' . ($this->main_basis == 'rent' ? '(brutto)' : '' ) . '<br>';
          endif;
          if ($this->getPrice('num','net')):
            $content .= $this->getPrice('formated','net') . ' ' . ($this->main_basis == 'rent' ? '(netto)' : '' ) . '<br>';
          endif;
          if (!$this->getPrice('num','net') && !$this->getPrice('num', false)): 
            $content .= 'Auf Anfrage';
          endif;
        endif;
      $content .= '</div></div>';

      return $content;
    }

    public function getSpecificationsTable(){
      $content = '<h3>' . __('Offer','casasync'). '</h3>';
      
      $content .= '<table class="table">';
      if ($this->getPrice('num')) {
        $content .= '<tr>
          <td width="25%">' . ($this->main_basis == 'rent' ? __('Rent price', 'casasync') : __('Sales price', 'casasync')) . '</td>'
          .'<td width="75%">' . $this->getPrice('formated') . '</td>'
        .'</tr>';
      }
      if ($this->getPrice('num', true)) {
        $content .= '<tr>
          <td width="25%"> ' . __('Net Price', 'casasync') . '</td>'
          .'<td width="75%">' . $this->getPrice('formated', true) . '</td>'
        .'</tr>';
      }
      $content .= '</table>';

      /*<div class="tab-pane fade" id="text_numbers">
                      <h3><!-- <i class="icon icon-tags"></i>  --><?php echo __('Offer','casasync'); ?></h3>
                      <table class="table">

                        <?php if (!$gross || !$net): ?>
                            <?php if ($price_formated): ?>
                              <tr><td width="25%"><?php echo ($the_basis == 'rent' ? 'Rent price' : __('Sales price', 'casasync')) ?></td><td width="75%"><?php echo $price_formated ?> <?php echo $price_formated_timesegment ?></td></tr>
                            <?php endif ?>
                          <?php endif ?>
                          <?php if ($gross_formated): ?>
                            <tr><td width="25%"><?php echo ($the_basis == 'rent' ? 'Rent price' : __('Sales price', 'casasync')) ?> (brutto)</td><td width="75%"><?php echo $gross_formated ?> <?php echo $gross_formated_timesegment ?></td></tr>
                          <?php endif ?>
                          <?php if ($net_formated): ?>
                            <tr><td width="25%"><?php echo ($the_basis == 'rent' ? 'Rent price' : __('Sales price', 'casasync')) ?> (netto)</td><td width="75%"><?php echo $net_formated ?> <?php echo $net_formated_timesegment ?></td></tr>
                          <?php endif ?>
                          <?php if (!$gross && !$net && !$price): ?>
                            <tr><td width="25%"><?php echo ($the_basis == 'rent' ? 'Rent price' : __('Sales price', 'casasync')) ?></td><td width="75%">Auf Anfrage</td></tr>
                          <?php endif ?>
                        <?php if ($extra_costs_arr): ?>
                          <tr><td><?php echo __('Additional costs','casasync') ?></td><td>
                          <ul class="unstyled">
                            <?php foreach ($extra_costs_arr as $extra_cost): ?>
                              <li>
                                <?php if($extra_cost['title']) : ?><strong><?php echo $extra_cost['title'] ?>: </strong><?php endif; ?>
                                <span><?php echo $extra_cost['value'] ?></span>
                              </li>
                            <?php endforeach ?>
                          </ul>

                        </td></tr>
                        <?php endif ?>
                        <?php if ($start): ?>
                            <tr><td width="25%"><?php echo __('Availability starts','casasync'); ?></td><td width="75%"><?php echo date(get_option('date_format'), strtotime($start)); ?></td></tr>
                          <?php endif ?>

                        
                      </table>
              <?php if ($address || $the_floors_arr || $numvals || $property_id || $reference_id): ?>
                      <h3><!-- <i class="icon icon-building"></i>  --><?php echo __('Property','casasync'); ?></h3>
                      <table class="table">
                        
                        <?php if ($reference_id): ?>
                          <tr><td width="25%"><?php echo __('Reference','casasync') ?></td><td width="75%"><?php echo $reference_id ?></td></tr>
                        <?php elseif ($property_id): ?> 
                          <!-- <tr><td width="25%"><?php echo __('Object ID','casasync') ?></td><td width="75%"><?php echo $property_id ?></td></tr> -->
                        <?php endif ?>
                        
                        <tr><td width="25%"><?php echo __('Address','casasync') ?></td><td width="75%"><?php echo $address ?></td></tr>
                        <?php if ($the_floors_arr): ?>
                          <tr><td width="25%"><?php echo __('Floor(s)','casasync') ?></td><td width="75%"><?php 
                            echo "<ul><li>" . implode("</li><li>", $the_floors_arr) . "</li></ul>";
                          ?></td></tr>  
                        <?php endif ?>
                        
                        <?php if ($numvals): ?>
                          <?php $store = ''; ?>
                          <?php foreach ($numvals as $numval): ?>
                            <?php if (in_array($numval['key'], array(
                              'number_of_apartments',
                              'number_of_floors',
                              'floor',
                              'number_of_rooms',
                              'number_of_bathrooms',
                              'room_height'
                            ))): ?>
                              <tr>
                                <td width="25%"><?php echo __($numval['title'], 'casasync') ?></td>
                          <td width="75%"><?php echo $numval['value'] ?><?php echo (in_array($numval['key'], array('surface_living', 'surface_property')) ? '<sup>2</sup>' : '' ) ?></td>
                        </tr>
                            <?php else: ?>
                              <?php $store .= '
                                <tr>
                                  <td width="25%">' . __($numval['title'], 'casasync')  . '</td>
                            <td width="75%">' . $numval['value'] .  (in_array($numval['key'], array('surface_living', 'surface_property')) ? '<sup>2</sup>' : '' ) .'</td>
                          </tr>
                              '; ?>
                            <?php endif ?>
                          <?php endforeach ?>
                          <?php //echo '<tr><td colspan="2"></td></tr>' ?>
                          <?php echo $store; ?>
                        <?php endif ?>
                      </table>
                    <?php endif ?>
                    <?php if ($features): ?>
                      <h3><?php echo __('Features','casasync'); ?></h3>
                        <div class="casasync-features">
                          <?php foreach ($features as $feature){
                            switch ($feature['key']) {
                              case 'wheel-chair-access':
                                echo "<span class='label'><i class='icon icon-ok'></i> " . __('Wheelchair accessible', 'casasync') . ($feature['value'] ? ': ' . $feature['value'] . ' Eingänge' : '') . '</span>';
                                break;
                              case 'animals-alowed':
                                echo "<span class='label'>" . ($feature['value'] ? $feature['value'] . ' ' : '') . __('Pets allowed', 'casasync') . '</span>';
                                break;
                              default:
                                echo "<span class='label'><i class='icon icon-ok'></i> " . ($feature['value'] ? $feature['value'] . ' ' : '') . ' ' . casasync_convert_featureKeyToLabel($feature['key']) . '</span>';
                                break;
                            }
                          } ?>
                        </div>
                    <?php endif ?>
                    <div class="row-fluid">
                    <?php if ($distances): ?>
                      <div class="span6">
                        <h3><?php echo __('Distances','casasync'); ?></h3>
                        <?php if ($distances): ?>
                          <ul class="unstyled">
                          <?php if ($distances): ?>
                            <?php foreach ($distances as $key => $value): ?>
                              <li>
                                <strong><?php echo $value['title'] ?>: </strong>
                                <span><?php echo $value['value'] ?></span>
                              </li>
                            <?php endforeach ?>
                          <?php endif ?>
                          </ul>
                        <?php endif ?>
                      </div>
                        
                    <?php endif ?>

                    <?php if ($urls): ?>
                      <div class="span6">
                        <h3><?php echo __('Links', 'casasync') ?></h3>
                        <ul class="unstyled">
                          <?php foreach ($urls as $key => $url): ?>
                            <li>
                              <a href="<?php echo $url['href'] ?>" title="<?php echo $url['title'] ?>" target="blank"><?php echo $url['label'] ?></a>
                            </li>
                          <?php endforeach ?>
                        </ul>
                      </div>
                    <?php endif ?>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="text_documents">
                    <?php if ($plans): ?>
                      <h2><?php echo __('Plans','casasync'); ?></h2>
                      <ul>
                      <?php foreach ($plans as $plan): ?>
                        <?php
                          $classes = '';
                          $data = '';
                          $excerpt = $plan->post_excerpt;
                          $title = $plan->post_title;
                          $url = wp_get_attachment_url( $plan->ID );
                          if (in_array($plan->post_mime_type, array('image/jpeg', 'image/png', 'image/jpg'))) {
                            $classes = 'casasync-fancybox';
                            $data = 'data-fancybox-group="casasync-property-plans"';
                          }
                        ?>
                        <li><a href="<?php echo $url; ?>" class="<?php echo $classes ?>" title="<?php echo $excerpt; ?>" target="_blank" <?php echo $data ?>><?php echo $title ?></a>
                          <?php echo ($excerpt ? ': ' . $excerpt : ''); ?>
                        </li>
                      <?php endforeach ?>
                      </ul>
                    <?php endif ?>
                    <?php if ($documents): ?>
                      <h2><?php echo __('Documents','casasync'); ?></h2>
                <ul>
                      <?php foreach ($documents as $document): ?>
                  <?php
                          $classes = '';
                          $data = '';
                          $excerpt = $document->post_excerpt;
                          $title = $document->post_title;
                          $url = wp_get_attachment_url( $document->ID );
                          if (in_array($document->post_mime_type, array('image/jpeg', 'image/png', 'image/jpg'))) {
                            $classes = 'casasync-fancybox';
                            $data = 'data-fancybox-group="casasync-property-documents"';
                          }
                        ?>
                        <li><a href="<?php echo $url; ?>" class="<?php echo $classes ?>" title="<?php echo $excerpt; ?>" target="_blank" <?php echo $data ?>><?php echo $title ?></a>
                          <?php echo ($excerpt ? ': ' . $excerpt : ''); ?>
                        </li>
                      <?php endforeach ?>
                      </ul>
                    <?php endif ?>
                  </div>
                </div>*/
      
      return $content;
    }

    public function getPagination(){
      return '<nav class="casasync-single-paginate">
                <a class="casasync-single-back" href="#" onclick="javascript:window.history.back(-1);return false;"><i class="fa fa-arrow-left"></i> '. __('Back to the list','casasync') . '</a>
              </nav>';
    }

    public function getEmails(){
      $emails = array();
      if ($this->seller['email']) {
        $emails[] = $this->seller['firstname'] . ' ' . $this->seller['lastname'] . ':' . $this->seller['email'];
      }
      return $emails;
    }

    public function getContactform(){
      return do_shortcode( '[casasync_contact recipients="' . implode(';', $this->getEmails()) . '" post_id="' . get_the_ID() . '"]' );
    }

    public function getTabable(){
      $nav = '<ul class="casasync-tabable-nav">';
      $navend = '</ul>';
      $content = '<div class="casasync-tabable-content">';
      $contentend = '</div>';
      
      //basics
      $nav .= '<li class="active"><a data-toggle="tab" href="#text_basics"><small>' . __("Base data", 'casasync') . '</small></a></li>';
      $content .= '<div class="casasync-tabable-pane active in" id="text_basics">';
        $content .= '<div class="casasync-basic-boxes">';
          $content .= $this->getBasicBoxes();
        $content .= '</div>';
        if ($this->getAddress('property')){ 
          $map_url = "https://maps.google.com/maps?f=q&amp;source=s_q&amp;hl=" . substr(get_locale(), 0, 2)  . "&amp;geocode=&amp;q=" . urlencode( str_replace(' ',', ', str_replace('<br>', ', ', $this->getAddress('property') ))) . "&amp;aq=&amp;ie=UTF8&amp;hq=&amp;hnear=" . urlencode( str_replace('<br>', ', ', $this->getAddress('property') )) . "&amp;t=m&amp;z=14&amp;output=embed";
          $content .= '<div class="hidden-xs"><div class="casasync-map" style="display:none" data-address="'. str_replace('<br>', ', ', $this->getAddress('property')) . '"><div id="map-canvas" style="width:100%; height:400px;" ></div><br /><small><a href="' . $map_url . '" class="casasync-fancybox" data-fancybox-type="iframe">' . __('View lager version', 'casasync') . '</a></small></div></div>';
          $content .= '<div class="visible-xs"><a class="btn btn-default btn-block" href="' . $map_url . '"><i class="icon icon-map-marker"></i> Auf Google Maps anzeigen</a></div>';
        }
      $content .= '</div>';

      //Description
      $nav .= '<li><a data-toggle="tab" href="#text_description">&nbsp;&#9998;&nbsp;<small>' . __('Description', 'casasync') . '</small></a></li>';
      $content .= '<div class="casasync-tabable-pane" id="text_description">';
      $content .= $this->content;
      $content .= '</div>';

      //details table
      $nav .= '<li><a data-toggle="tab" href="#text_numbers"><i class="fa fa-file-o"></i> <small>' . __("Specifications", 'casasync') . '</small></a></li>';
      $content .= '<div class="casasync-tabable-pane" id="text_numbers">';
        $content .= $this->getSpecificationsTable();
      $content .= '</div>';
      return $nav . $navend . $content . $contentend;

    }

    public function getPermalink(){
      return get_permalink();
    }

    public function getAddress($from){
      switch ($from) {
        case 'seller':
          $address  = ($this->seller['street'] ? $this->seller['street'] . '<br>' : '');
          $address .= ($this->seller['postalcode'] ?  $this->seller['postalcode'] . ' ': '') . ($this->seller['locality'] ? $this->seller['locality'] : '') . ($this->seller['postalcode'] || $this->seller['locality'] ? '<br>' : '');
          $address .= $this->conversion->countrycode_to_countryname($this->seller['country']); 
          return $address;
          break;
        case 'property':
          $address  = ($this->address_street ? $this->address_street . ' ' . $this->address_streetnumber . '<br>' : '');
          $address .= ($this->address_postalcode ?  $this->address_postalcode . ' ': '') . ($this->address_locality ? $this->address_locality : '') . ($this->address_postalcode || $this->address_locality ? '<br>' : '');
          $address .= ($this->address_country_name ? $this->address_country_name : '');
          return $address;
        default:
          # code...
          break;
      }
    }

    public function hasSeller(){
      if (
          $this->getAddress('seller')
          . $this->seller['legalname']
          . $this->seller['email']
          . $this->seller['fax']
          . $this->seller['phone_direct']
          . $this->seller['phone_central']
          . $this->seller['phone_mobile']
        ) {
          return true;
        } else {
          return false;
        }
    }

    public function availabilitySlug2Label($slug){
      switch ($slug) {
          case 'available':
            return 'Verfügbar';
            break;
          case 'reserved':
            return 'Reserved';
            break;
          case 'planned':
            return 'In Planung';
            break;
          case 'under-construction':
            return 'Im Bau';
            break;
          case 'reference':
            return 'Referenz';
            break;
          
          default:
            return $slug;
            break;
        }
    }

    public function getPrice($format = 'num', $net = false){
      $timesegment_labels = array(
        'm' => __('month', 'casasync'),
        'w' => __('week', 'casasync'),
        'd' => __('day', 'casasync'),
        'y' => __('year', 'casasync'),
        'h' => __('hour', 'casasync')
      );

      if ($net) {
        $price = $this->prices['net'];
      } else {
        if ($this->prices['sales']) {
          $price = $this->prices['sales'];
        } else {
          $price = $this->prices['gross'];
        }
      }
      $return = '';
      switch ($format) {
        case 'num':
          $return = $price['num'];
          break;
        case 'currency':
        case 'formated':
        case 'full':
          $return = $this->price_currency . ' ';
        case 'formated':
        case 'full':
          $return .= number_format(
                      round($price['num']), 
                      0, 
                      '', 
                      '\''
                    ) . '.–';
        case 'full':
          $return .= ($price['propertysegment'] != 'full' ? ' / ' . substr($price['propertysegment'], 0, -1) . '<sup>2</sup>' : '');
      }

      return $return;
    }

    public function getNumval($name){
      switch ($name) {
        case 'rooms':
            return (isset($numvals['number_of_rooms']) ? $numvals['number_of_rooms']['value'] : false);
          break;
        case 'surface_living':
          return (isset($numvals['surface_living']) ? $numvals['surface_living']['value'] : false);
          break;
        case 'distances':
          $distances = array();
          foreach (casasync_get_allDistanceKeys() as $distance_key) {
            $distance = get_post_meta( get_the_ID(), $distance_key, $single = true );
            $distance_arr = $this->casasync_distance_to_array($distance);
            if ($distance) {
              $title = casasync_convert_distanceKeyToLabel($distance_key);
              $distances[$distance_key] = array('title' => $title, 'value' => implode($distance_arr, ' ' . __('and','casasync') . ' '));
            }
          }
          return $distances;
          break;
      }
    }

    public function casasync_distance_to_array($distance){
      if ($distance) {
        $distance_quirk = trim($distance,"[");   
        $distance_quirk = trim($distance_quirk,"]");
        $distance_arr = explode(']+[', $distance_quirk);
        return $distance_arr;
      }
      return false;
    }
      
    public function setTagValue($tagname){
      switch ($tagname) {
        case 'title':
            $this->tag[$tagname] = get_the_title();
          break;
        default:
          $this->tag[$tagname] = false;
          break;
      }
      return $this->tag[$tagname];

    }

    public function getTagValue($tagname){
      if (isset($this->tag[$tagname])) {
        return $this->tag[$tagname];
      } else {
        return $this->setTagValue($tagname);
      }
    }


    public function getTextBetweenTags($string, $tagname){
      $d = new DOMDocument();
      $d->loadHTML($string);
      $return = array();
      foreach($d->getElementsByTagName($tagname) as $item){
          $return[] = $item->textContent;
      }
      return (array_key_exists(0, $return) ? $return[0] : '');
    }

  }  

<?php
/**
 * Import Casasync file
 **/

function casasync_category_getLabel($term_slug, $lang){
    if ($lang == 'de_CH') {
        switch ($term_slug) {
            case 'house':
                return 'Haus';
                break;        
            
            default:
                return $term_slug;
                break;
        }
    } elseif ($lang == 'en_US') {
        switch ($term_slug) {
            case 'house':
                return 'House';
                break;
            
            default:
                return $term_slug;
                break;
        }
    }
    
}




function casasync_category_setTerm($term_slug, $lang){
    //$wp_term = get_term_by('slug', $term_slug, 'casasync_category');
    

        $label = $term_slug;

        $existing_term_id = term_exists( $label, 'casasync_category');
        if ($existing_term_id) {
            return $existing_term_id['term_id'];
        } else {
                $options = array(
                        'description' => '',
                        'slug' => $term_slug
                );
                $id = wp_insert_term( 
                    $label, 
                    'casasync_category', 
                    $options
                );
                return $id;
        }

}



function casasync_upload_attachment($the_mediaitem, $post_id, $property_id){
    if ($the_mediaitem['file']) {
        $filename = '/casasync/import/attachment/'. $the_mediaitem['file'];
    } elseif ($the_mediaitem['url']) { //external
        $filename = '/casasync/import/attachment/externalsync/' . $property_id . '/' . basename($the_mediaitem['url']);
        
        //extention is required
        $file_parts = pathinfo($filename);
        if (!isset($file_parts['extension'])) {
            $filename = $filename . '.jpg';
        }
        if (!is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename)) {
            if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import/attachment/externalsync')) {
                mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import/attachment/externalsync');
            }
            if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import/attachment/externalsync/' . $property_id)) {
                mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import/attachment/externalsync/' . $property_id);
            }
            if (is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename )) {
                $could_copy = copy($the_mediaitem['url'], CASASYNC_CUR_UPLOAD_BASEDIR . $filename );
            } else {
                $could_copy = false;
            }
            
            if (!$could_copy) {
                $filename = false;
            }
        }
    } else { //missing
        $filename = false;
    }

    if ($filename && is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename)) {
        //new file attachment upload it and attach it fully
        $wp_filetype = wp_check_filetype(basename($filename), null );
        $attachment = array(
            'guid' => CASASYNC_CUR_UPLOAD_BASEURL . $filename, 
            'post_mime_type' => $wp_filetype['type'],
            'post_title' =>  preg_replace('/\.[^.]+$/', '', ( $the_mediaitem['title'] ? $the_mediaitem['title'] : basename($filename)) ),
            'post_content' => '',
            'post_excerpt' => $the_mediaitem['caption'],
            'post_status' => 'inherit',
            'menu_order' => $the_mediaitem['order']
        );
        
        $attach_id = wp_insert_attachment( $attachment, CASASYNC_CUR_UPLOAD_BASEDIR . $filename, $post_id );
        // you must first include the image.php file
        // for the function wp_generate_attachment_metadata() to work
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, CASASYNC_CUR_UPLOAD_BASEDIR . $filename );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        //category
        $term = get_term_by('slug', $the_mediaitem['type'], 'casasync_attachment_type');
        $term_id = $term->term_id;
        wp_set_post_terms( $attach_id,  array($term_id), 'casasync_attachment_type' );

        //alt
        update_post_meta($attach_id, '_wp_attachment_image_alt', $the_mediaitem['alt']);

        //orig
        update_post_meta($attach_id, '_origin', ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url']));

    }
    
}



// Imports the file on ftp
function casasync_import(){
    $good_to_go = false;

    //1. check if file exists
    if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import')) {
            mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import');
        }
    $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/import/data.xml';
    if (file_exists($file)) {
        $good_to_go = true;
    } else {
        if (isset($_GET['force_last_import'])) {
            $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/import/data.xml.done';
            if (file_exists($file)) {
                $good_to_go = true;
            } 
        }
    }
    if ($good_to_go == true) {
    //2. get file properties and update/insert them
        //A. Save it to processing dir
        if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/processing')) {
            mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/processing');
        }
        $processing_file = CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/processing/' . date('Y_m_d_H_i_s') . '_processing.xml';
        copy($file, $processing_file);
        
        //B. rename the file so that it wont import again
        if (!isset($_GET['force_last_import'])) {
            rename ( $file , $file . '.done' );
        }

        //C. To be filled during process
        $found_properties = array();

        //D. read xml and save the property
        $xml = simplexml_load_file($processing_file, 'SimpleXMLElement', LIBXML_NOCDATA);
        

        //depricated!!! **********************************************************
        //update admin options
        if (get_option('casasync_sellerfallback_update') == 1) {

            if ($xml->provider->organization && $xml->provider->organization->address) {
                if ($xml->provider->organization->address->country) {update_option("casasync_sellerfallback_address_country", $xml->provider->organization->address->country->__toString() );}
                if ($xml->provider->organization->address->locality) {update_option("casasync_sellerfallback_address_locality", $xml->provider->organization->address->locality->__toString() );}
                if ($xml->provider->organization->address->region) {update_option("casasync_sellerfallback_address_region", $xml->provider->organization->address->region->__toString() );}
                if ($xml->provider->organization->address->postalCode) {update_option("casasync_sellerfallback_address_postalcode", $xml->provider->organization->address->postalCode->__toString() );}
                if ($xml->provider->organization->address->postOfficeBoxNumber) {/*update_option("casasync_sellerfallback_address_pobox", $xml->provider->organization->address->postOfficeBoxNumber->__toString() );*/}
                if ($xml->provider->organization->address->street) {update_option("casasync_sellerfallback_address_street", $xml->provider->organization->address->street->__toString() );}
            }
            if ($xml->provider->organization) {
                if ($xml->provider->organization->legalName) {update_option("casasync_sellerfallback_legalname", $xml->provider->organization->legalName->__toString() );}
                $general = false;
                foreach ($xml->provider->organization->email as $email) {
                    if ($email['type']) {
                        switch ($email['type']->__toString()) {
                            case 'rem':
                                update_option("casasync_remCat_email", $email->__toString() );
                                break;
                            case 'general':
                                update_option("casasync_sellerfallback_phone_central", $email->__toString() );
                                $general = true;
                                break;
                            default:
                                if (!$general) {
                                    update_option("casasync_sellerfallback_phone_central", $email->__toString() );
                                }
                                break;
                        }
                    } else {
                        if (!$general) {
                            update_option("casasync_sellerfallback_phone_central", $email->__toString() );
                        }
                    }
                }
                if ($xml->provider->organization->email) {update_option("casasync_sellerfallback_email", $xml->provider->organization->email->__toString() );}
                if ($xml->provider->organization->faxNumber) {update_option("casasync_sellerfallback_fax", $xml->provider->organization->faxNumber->__toString() );}
                if ($xml->provider->organization->phone) {
                    $central = false;
                    foreach ($xml->provider->organization->phone as $phone) {
                        if ($phone['type']) {
                            switch ($phone['type']->__toString()) {
                                case 'direct':
                                    update_option("casasync_sellerfallback_phone_direct", $phone->__toString() );
                                    break;
                                case 'central':
                                    update_option("casasync_sellerfallback_phone_central", $phone->__toString() );
                                    $central = true;
                                    break;
                                case 'mobile':
                                    update_option("casasync_sellerfallback_phone_mobile", $phone->__toString() );
                                    break;
                                default:
                                    if (!$central) {
                                        update_option("casasync_sellerfallback_phone_central", $phone->__toString() );
                                    }
                                    break;
                            }
                        } else {
                            if (!$central) {
                                update_option("casasync_sellerfallback_phone_central", $phone->__toString() );
                            }
                        }
                    }
                }
            } 
            if ($xml->provider->organization) {
                if ($xml->provider->organization->legalName) {update_option("casasync_sellerfallback_legalname", $xml->provider->organization->legalName->__toString() );}
                if ($xml->provider->organization->email) {update_option("casasync_sellerfallback_email", $xml->provider->organization->email->__toString() );}
                if ($xml->provider->organization->faxNumber) {update_option("casasync_sellerfallback_fax", $xml->provider->organization->faxNumber->__toString() );}
                if ($xml->provider->organization->phone) {
                    $central = false;
                    foreach ($xml->provider->organization->phone as $phone) {
                        if ($phone['type']) {
                            switch ($phone['type']->__toString()) {
                                case 'direct':
                                    update_option("casasync_sellerfallback_phone_direct", $phone->__toString() );
                                    break;
                                case 'central':
                                    update_option("casasync_sellerfallback_phone_central", $phone->__toString() );
                                    $central = true;
                                    break;
                                case 'mobile':
                                    update_option("casasync_sellerfallback_phone_mobile", $phone->__toString() );
                                    break;
                                default:
                                    if (!$central) {
                                        update_option("casasync_sellerfallback_phone_central", $phone->__toString() );
                                    }
                                    break;
                            }
                        } else {
                            if (!$central) {
                                update_option("casasync_sellerfallback_phone_central", $phone->__toString() );
                            }
                        }
                    }
                }
            }
        }

        if (get_option('casasync_feedback_update') == 1) {
            if ($xml->technicalFeedback) {
                if ($xml->technicalFeedback->givenName) {update_option("casasync_feedback_given_name", $xml->technicalFeedback->givenName->__toString() );}
                if ($xml->technicalFeedback->familyName) {update_option("casasync_feedback_family_name", $xml->technicalFeedback->familyName->__toString() );}
                if ($xml->technicalFeedback->email) {update_option("casasync_feedback_email", $xml->technicalFeedback->email->__toString() );}
                if ($xml->technicalFeedback->phone) {update_option("casasync_feedback_telephone", $xml->technicalFeedback->phone->__toString() );}
                if ($xml->technicalFeedback->gender) {update_option("casasync_feedback_gender", $xml->technicalFeedback->gender->__toString() );}
            }
        }
        //END depricated!!! *********************************
        




        $new_location = array();
        foreach ($xml->property as $property) {
            //requirenments
            if (
                !$property['id'] 
                || !$property->provider
                || !$property->provider['id']
                || !isset($property->offer)
            ) {
                echo "required data missing!!!";
                continue;
            }
            if (isset($property->provider['id']) ) {
                $exporter = $property->provider['id'];
            } elseif(isset($property->provider->organization->legalName) && $property->provider->organization->legalName) {
                $exporter = urlencode(str_replace(' ', '-', strtolower($property->provider->organization->legalName)));
            } else {
                $exporter = 'cs';
            }


            //defaults
            $the_post_custom = array();
            // $the_post_custom = array(
            //     'casasync_url' => '',

            //     'casasync_property_address_country' => '',
            //     'casasync_property_address_locality' => '',
            //     'casasync_property_address_region' => '',
            //     'casasync_property_address_postalcode' => '',
            //     'casasync_property_address_postofficeboxnumber' => '',
            //     'casasync_property_address_streetaddress' => '',

            //     'casasync_property_geo_latitude' => '',
            //     'casasync_property_geo_longitude' => '',
            // );

            //try to fetch property from wordpress
            $wp_property = false;
            $wp_post_custom = false;
            
            $casasync_id = $property->provider['id'] . '_' . $property['id'] . $property->offer['lang'];
            $wp_category_terms = array(); //all
            $wp_category_terms_to_keep = array(); //non casasync in property
            $wp_casasync_category_terms = array(); //casasync in property
            $wp_casasync_category_terms_slugs = array(); //casasync slugs in property
            
            $wp_casasync_attachments = array();
            $the_casasync_attachments = array();

            $the_post_category_term_slugs = array();

            $the_query = new WP_Query( 'post_type=casasync_property&meta_key=casasync_id&meta_value=' . $casasync_id );
            while ( $the_query->have_posts() ) :
                $the_query->the_post();
                global $post;
                $wp_property = $post;
            endwhile;
            wp_reset_postdata();


            //get xml media files
            if ($property->offer->attachments) {
                foreach ($property->offer->attachments->media as $media) {
                    if (in_array($media['type']->__toString(), array('image', 'document', 'plan'))) {
                        $filename = ($media->file->__toString() ? $media->file->__toString() : $media->url->__toString());
                        $the_casasync_attachments[] = array(
                            'type' => $media['type']->__toString(),
                            'alt' => $media->alt->__toString(),
                            'title' => preg_replace('/\.[^.]+$/', '', ( $media->title->__toString() ? $media->title->__toString() : basename($filename)) ),
                            'file' => $media->file->__toString(),
                            'url' => $media->url->__toString(),
                            'caption' => $media->caption->__toString(),
                            'order' => $media['order']->__toString()
                        );
                    }
                }
            }

            $attachment_image_order = array();
            foreach ($the_casasync_attachments as $the_mediaitem) {
                if ($the_mediaitem['type'] == 'image') {
                    $attachment_image_order[$the_mediaitem['order']] = $the_mediaitem;
                }
            }
            

            //check if data has changed
            $changed = false;

            if (isset($_GET['force_all_properties'])) {
                $changed = true;
            }


            if(!$wp_property){
                $the_post_custom['casasync_id'] = $casasync_id;
                $changed = true;
            } else {
                //collect all the ids
                $found_properties[] = $wp_property->ID;

                //get post custom fields
                $wp_post_custom = get_post_custom( $wp_property->ID );

                //get post categoryterms
                $wp_category_terms = wp_get_object_terms($wp_property->ID, 'casasync_category');
                

                //get post attachments already attached
                $args = array(
                    'post_type' => 'attachment',
                    'numberposts' => -1,
                    'post_status' => null,
                    'post_parent' => $wp_property->ID,
                    'tax_query' => array(
                        'relation' => 'AND',
                        array(
                            'taxonomy' => 'casasync_attachment_type',
                            'field' => 'slug',
                            'terms' => array( 'image', 'plan', 'document' )
                        )
                    )
                ); 
                $attachments = get_posts($args);
                if ($attachments) {
                    foreach ($attachments as $attachment) {
                        $wp_casasync_attachments[] = $attachment;
                        //the_attachment_link($attachment->ID, false);
                    }
                }

                

                //upload necesary images to wordpress
                $attachmentfilenames_in_xml = array();
                foreach ($the_casasync_attachments as $the_mediaitem) {
                    
                    //look up wp and see if file is already attached
                    $existing = false;
                    $existing_attachment = array();
                    $attachmentfilenames_in_xml[] = ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url']);
                    
                    foreach ($wp_casasync_attachments as $wp_mediaitem) {
                        $attachment_customfields = get_post_custom($wp_mediaitem->ID);
                        $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
                        $alt = '';
                        if ($original_filename == ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url'])) {
                            $existing = true;
                            $types = wp_get_post_terms( $wp_mediaitem->ID, 'casasync_attachment_type');
                            if (array_key_exists(0, $types)) {
                                $typeslug = $types[0]->slug;
                                $alt = get_post_meta($wp_mediaitem->ID, '_wp_attachment_image_alt', true);
                                //build a proper array out of it
                                $existing_attachment = array(
                                    'type' => $typeslug,
                                    'alt' => $alt,
                                    'title' => $wp_mediaitem->post_title,
                                    'file' => $the_mediaitem['file'],
                                    'url' => $the_mediaitem['url'],
                                    'caption' => $wp_mediaitem->post_excerpt,
                                    'order' => $wp_mediaitem->menu_order
                                );
                            }

                            

                            //have its values changed?
                            if($existing_attachment != $the_mediaitem ){
                                $changed = true;
                                //update attachment data
                                if ($existing_attachment['caption'] != $the_mediaitem['caption']
                                    || $existing_attachment['title'] != $the_mediaitem['title']
                                    || $existing_attachment['order'] != $the_mediaitem['order']
                                    ) {
                                    $att['post_excerpt'] = $the_mediaitem['caption'];
                                    $att['post_title']   = preg_replace('/\.[^.]+$/', '', ( $the_mediaitem['title'] ? $the_mediaitem['title'] : basename($filename)) );
                                    $att['ID']           = $wp_mediaitem->ID;
                                    $att['menu_order']   = $the_mediaitem['order'];
                                    $insert_id = wp_update_post( $att);
                                }
                                //update attachment category
                                if ($existing_attachment['type'] != $the_mediaitem['type']) {
                                    $term = get_term_by('slug', $the_mediaitem['type'], 'casasync_attachment_type');
                                    $term_id = $term->term_id;
                                    wp_set_post_terms( $wp_mediaitem->ID,  array($term_id), 'casasync_attachment_type' );
                                }
                                //update attachment alt
                                if ($alt != $the_mediaitem['alt']) {
                                    update_post_meta($wp_mediaitem->ID, '_wp_attachment_image_alt', $the_mediaitem['alt']);
                                }
                            }
                        }
                    }

                    if (!$existing) {
                        //insert the new image
                        $new_id = casasync_upload_attachment($the_mediaitem, $wp_property->ID, $property['id']->__toString());
                    }
                }

                
                //if ($set_featured_image) {
                //  set_post_thumbnail( $post_id, $attach_id );
                //}

                //remove all extra atachments
                foreach ($wp_casasync_attachments as $wp_mediaitem2) {
                    $attachment_customfields = get_post_custom($wp_mediaitem2->ID);
                    $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
                    if (!in_array($original_filename , $attachmentfilenames_in_xml)) {
                        wp_delete_attachment( $wp_mediaitem2->ID );
                    }
                }



                

            }




            //build description
            $the_description = '';
            foreach ($property->offer->description as $description) {
                $the_description .= ($the_description ? '<hr class="property-separator" />' : '');
                if ($description['title']) {
                    $the_description .= '<h2>' . $description['title']->__toString() . '</h2>';
                }
                $the_description .= $description->__toString();
            }
            $the_post['post_excerpt']           = $property->offer->excerpt->__toString();
            $the_post['post_content']           = $the_description;
            $the_post['post_title']             = $property->offer->name->__toString();
            $the_post['post_date']              = date('Y-m-d H:i:s', strtotime(($property->software->creation->__toString() ? $property->software->creation->__toString() : $property->software->lastUpdate->__toString() ) ));
            $the_post['post_modified']          = date('Y-m-d H:i:s', strtotime($property->software->lastUpdate->__toString()));

            if(
                $property->excerpt->__toString()                                            != ($wp_property ? $wp_property->post_excerpt : '')
                || $the_description                                                         != ($wp_property ? $wp_property->post_content : '')
                || $property->name->__toString()                                            != ($wp_property ? $wp_property->post_title : '')
                //|| date('Y-m-d H:i:s', strtotime($property->releaseDate->__toString()))     != ($wp_property ? $wp_property->post_date : '')
                //|| date('Y-m-d H:i:s', strtotime($property->modifyDate->__toString()))      != ($wp_property ? $wp_property->post_modified : '')
                                
            ){
                $changed = true;
            }



            // set post custom fields
            $casasync_visitInformation = $property->visitInformation->__toString();

            $casasync_property_url = $property->url->__toString();

            $casasync_property_address_country              = ($property->address ? $property->address->country->__toString() : '');
            $casasync_property_address_locality             = ($property->address ? $property->address->locality->__toString() : '');
            $casasync_property_address_region               = ($property->address ? $property->address->region->__toString() : '');
            $casasync_property_address_postalcode           = ($property->address ? $property->address->postalCode->__toString() : '');
            $casasync_property_address_streetaddress        = ($property->address ? $property->address->street->__toString() : '');
            $casasync_property_address_streetnumber        = ($property->address ? $property->address->streetNumber->__toString() : '');
            
            $casasync_property_geo_latitude     = (int) ($property->geo ? $property->geo->latitude->__toString() : '');
            $casasync_property_geo_longitude    = (int) ($property->geo ? $property->geo->longitude->__toString() : '');

            $casasync_start = ($property->offer->start ? $property->offer->start->__toString() : '');
            $casasync_referenceId = ($property->referenceId ? $property->referenceId->__toString() : '');



            $offer_type = '';
            $price_currency = '';

            $price_timesegment = '';
            $price_propertysegment = '';
            $price = 0;

            $grossPrice_timesegment = '';
            $grossPrice_propertysegment = '';
            $grossPrice = 0;

            $netPrice_timesegment = '';
            $netPrice_propertysegment = '';
            $netPrice = 0;

            $availability = '';
            $availability_label = '';


            $extraPrice = array();

            if ($property->offer) {

                //urls
                $the_urls = array();
                if ($property->offer->url) {
                    foreach ($property->offer->url as $url) {
                        $href = $url->__toString();
                        $label = (isset($url['label']) && $url['label'] ? $url['label'] : false);
                        $title = (isset($url['title']) && $url['title'] ? $url['title'] : false);
                        $rank =  (isset($url['rank'])  && (int) $url['rank'] ? (int) $url['rank'] : false);
                        if ($rank ) {
                            $the_urls[$rank] = array(
                                'href' => $href,
                                'label' => ($label ? $label : $href),
                                'title' => ($title ? $title : $href)
                            );
                        } else {
                            $the_urls[] = array(
                                'href' => $href,
                                'label' => ($label ? $label : $href),
                                'title' => ($title ? $title : $href)
                            );
                        }
                    }
                    ksort($the_urls);
                    $the_urls = array_values($the_urls);
                }
                $the_urls = json_encode($the_urls);

                $offer_type = $property->offer->type;
                $price_currency = $property->offer->priceCurrency;
                if (!in_array($property->offer->priceCurrency, array('CHF', 'EUR', 'USD', 'GBP'))) {
                    $price_currency = '';
                }


                if ($property->offer->availability) {
                    $availability = $property->offer->availability->__toString();
                    if ($property->offer->availability['title']) {
                        $availability_label = $property->offer->availability['title']->__toString();
                    }
                }
                

                if ($property->offer->price) {
                    $price_timesegment = $property->offer->price['timesegment'];
                    if (!in_array($price_timesegment, array('m','w','d','y','h','infinite'))) {
                        $price_timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
                    }
                    $price_propertysegment = $property->offer->price['propertysegment'];
                    if (!in_array($price_propertysegment, array('m2','km2','full'))) {
                        $price_propertysegment = 'full';
                    }
                    $price = (float) $property->offer->price->__toString();
                }

                if ($property->offer->netPrice) {
                    $netPrice_timesegment = $property->offer->netPrice['timesegment'];
                    if (!in_array($netPrice_timesegment, array('m','w','d','y','h','infinite'))) {
                        $netPrice_timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
                    }
                    $netPrice_propertysegment = $property->offer->netPrice['propertysegment'];
                    if (!in_array($netPrice_propertysegment, array('m2','km2','full'))) {
                        $netPrice_propertysegment = 'full';
                    }
                    $netPrice = (float) $property->offer->netPrice->__toString();
                }

                if ($property->offer->grossPrice) {
                    $grossPrice_timesegment = $property->offer->grossPrice['timesegment'];
                    if (!in_array($grossPrice_timesegment, array('m','w','d','y','h','infinite'))) {
                        $grossPrice_timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
                    }
                    $grossPrice_propertysegment = $property->offer->grossPrice['propertysegment'];
                    if (!in_array($grossPrice_propertysegment, array('m2','km2','full'))) {
                        $grossPrice_propertysegment = 'full';
                    }
                    $grossPrice = (float) $property->offer->grossPrice->__toString();
                }
                if($property->offer->extraCost){
                    foreach ($property->offer->extraCost as $extraCost) {
                        $timesegment = '';
                        $propertysegment = '';

                        $timesegment = $extraCost['timesegment'];
                        if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
                            $timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
                        }
                        $propertysegment = $extraCost['propertysegment'];
                        if (!in_array($propertysegment, array('m2','km2','full'))) {
                            $propertysegment = 'full';
                        }
                        $the_extraPrice = (float) $extraCost->__toString();

                        $timesegment_labels = array(
                            'm' => __('month', 'casasync'),
                            'w' => __('week', 'casasync'),
                            'd' => __('day', 'casasync'),
                            'y' => __('year', 'casasync'),
                            'h' => __('hour', 'casasync')
                        );
                        $extraPrice[] = array(
                                'value' => 
                                    ($price_currency ? $price_currency . ' ' : '') . 
                                    number_format(round($the_extraPrice), 0, '', '\'') . '.&#8211;' .
                                    ($propertysegment != 'full' ? ' / ' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '') .
                                    ($timesegment != 'infinite' ? ' / ' . $timesegment_labels[(string) $timesegment] : '')
                                ,
                                'title' => (string) $extraCost['title']

                            )
                        ;
                        
                    }
                }
            }

            
            /* property seller
            <seller>
                <organization>
                    <address>
                        <addressCountry>CH</addressCountry>
                        <addressLocality>Zürich</addressLocality>
                        <addressRegion>Zürich</addressRegion>
                        <postalCode>8000</postalCode>
                        <postOfficeBoxNumber>123</postOfficeBoxNumber>
                        <streetAddress>Street 999</streetAddress>
                    </address>
                    <legalName>Your Company</legalName>
                    <logo>URL</logo>
                    <email></email>
                    <faxNumber></faxNumber>
                    <telephone type=""></telephone> <!-- ENUM('direct','central','mobile') -->
                    <telephone></telephone>
                    <brand>ERA Zugerland Immobilien</brand>
                </organization>
                <person type="view"><!-- ('inquiry', 'visit', 'view') standard is view!! --> 
                    <function></function> <!-- examples ('Ceo', 'Sales', 'Information', 'Guide') -->
                    <givenName></givenName>
                    <familyName></familyName>
                    <email></email>
                    <faxNumber></faxNumber>
                    <telephone type=""></telephone> <!-- ENUM('direct','central','mobile') -->
                    <telephone></telephone>
                    <gender></gender>
                </person>
                <person type="inquiry">
                    <email>inquiry@company.ch</email>
                </person>
            </seller>
            */

            $seller_org_address_country = '';
            $seller_org_address_locality = '';
            $seller_org_address_region = '';
            $seller_org_address_postalcode = '';
            $seller_org_address_postofficeboxnumber = '';
            $seller_org_address_streetaddress = '';

            $seller_org_legalname = '';
            $seller_org_email = '';
            $seller_org_fax = '';
            $seller_org_phone_direct = '';
            $seller_org_phone_central = '';
            $seller_org_phone_mobile = '';
            $seller_org_brand = '';

            $seller_person_function = '';
            $seller_person_givenname = '';
            $seller_person_familyname = '';
            $seller_person_email = '';
            $seller_person_fax = '';
            $seller_person_phone_direct = '';
            $seller_person_phone_central = '';
            $seller_person_phone_mobile = '';
            $seller_person_phone_gender = '';


            $seller_inquiry_person_function = '';
            $seller_inquiry_person_givenname = '';
            $seller_inquiry_person_familyname = '';
            $seller_inquiry_person_email = '';
            $seller_inquiry_person_fax = '';
            $seller_inquiry_person_phone_direct = '';
            $seller_inquiry_person_phone_central = '';
            $seller_inquiry_person_phone_mobile = '';
            $seller_inquiry_person_phone_gender = '';


            if ($property->offer->seller && $property->offer->seller->organization) {
                if ($property->offer->seller->organization->address) {
                    $seller_org_address_country = $property->offer->seller->organization->address->Country->__toString();
                    $seller_org_address_locality = $property->offer->seller->organization->address->locality->__toString();
                    $seller_org_address_region = $property->offer->seller->organization->address->region->__toString();
                    $seller_org_address_postalcode = $property->offer->seller->organization->address->postalCode->__toString();
                    $seller_org_address_postofficeboxnumber = $property->offer->seller->organization->address->postOfficeBoxNumber->__toString();
                    $seller_org_address_streetaddress = $property->offer->seller->organization->address->street->__toString();
                }
                $seller_org_legalname = $property->offer->seller->organization->legalName->__toString();
                $seller_org_email = $property->offer->seller->organization->email->__toString();
                $seller_org_fax = $property->offer->seller->organization->faxNumber->__toString();
                if ($property->offer->seller->organization->phone) {
                    $central = false;
                    foreach ($property->offer->seller->organization->organization as $phone) {
                        if ($phone['type']) {
                            switch ($phone['type']->__toString()) {
                                case 'direct':
                                    $seller_org_phone_direct = $phone->__toString();
                                    break;
                                case 'central':
                                    $seller_org_phone_central = $phone->__toString();
                                    $central = true;
                                    break;
                                case 'mobile':
                                    $seller_org_phone_mobile = $phone->__toString();
                                    break;
                                default:
                                    if (!$central) {
                                        $seller_org_phone_central = $phone->__toString();
                                    }
                                    break;
                            }
                        } else {
                            if (!$central) {
                                $seller_org_phone_central = $phone->__toString();
                            }
                        }
                    }
                }
                $seller_org_brand = $property->offer->seller->organization->brand->__toString();
            }
            if ($property->offer->seller && $property->offer->seller->person) {
                $view_person_set = false;
                foreach ($property->offer->seller->person as $person) {
                    if (!$view_person_set && (!$person['type'] || $person['type']->__toString() == 'view')) {   
                        $view_person_set = true;             
                        $seller_person_function = $person->function->__toString();
                        $seller_person_givenname = $person->givenName->__toString();
                        $seller_person_familyname = $person->familyName->__toString();
                        $seller_person_email = $person->email->__toString();
                        $seller_person_fax = $person->faxNumber->__toString();
                        if ($person->phone) {
                            $central = false;
                            foreach ($person->phone as $phone) {
                                if ($phone['type']) {
                                    switch ($phone['type']->__toString()) {
                                        case 'direct':
                                            $seller_person_phone_direct = $phone->__toString();
                                            break;
                                        case 'central':
                                            $seller_person_phone_central = $phone->__toString();
                                            $central = true;
                                            break;
                                        case 'mobile':
                                            $seller_person_phone_mobile = $phone->__toString();
                                            break;
                                        default:
                                            if (!$central) {
                                                $seller_person_phone_central = $phone->__toString();
                                            }
                                            break;
                                    }
                                } else {
                                    if (!$central) {
                                        $seller_person_phone_central = $phone->__toString();
                                    }
                                }
                            }
                        }
                        $seller_person_phone_gender = $person->gender->__toString();
                    } elseif ($person['type'] && $person['type']->__toString() == 'inquiry') {
                        $seller_inquiry_person_function = $person->function->__toString();
                        $seller_inquiry_person_givenname = $person->givenName->__toString();
                        $seller_inquiry_person_familyname = $person->familyName->__toString();
                        $seller_inquiry_person_email = $person->email->__toString();
                        $seller_inquiry_person_fax = $person->faxNumber->__toString();
                        if ($person->phone) {
                            $central = false;
                            foreach ($person->phone as $phone) {
                                if ($phone['type']) {
                                    switch ($phone['type']->__toString()) {
                                        case 'direct':
                                            $seller_inquiry_person_phone_direct = $phone->__toString();
                                            break;
                                        case 'central':
                                            $seller_inquiry_person_phone_central = $phone->__toString();
                                            $central = true;
                                            break;
                                        case 'mobile':
                                            $seller_inquiry_person_phone_mobile = $phone->__toString();
                                            break;
                                        default:
                                            if (!$central) {
                                                $seller_inquiry_person_phone_central = $phone->__toString();
                                            }
                                            break;
                                    }
                                } else {
                                    if (!$central) {
                                        $seller_inquiry_person_phone_central = $phone->__toString();
                                    }
                                }
                            }
                        }
                        $seller_inquiry_person_phone_gender = $person->gender->__toString();
                    }
                }
            }


            //check if changed and set the values
            if (
                !$wp_property
                
                || (string) $casasync_visitInformation              != (string) (isset($wp_post_custom['casasync_visitInformation']) ? $wp_post_custom['casasync_visitInformation'][0] : '')

                || (string) $casasync_property_url != (string) (isset($wp_post_custom['casasync_property_url']) ? $wp_post_custom['casasync_property_url'][0] : '')

                || (string) $casasync_property_address_country              != (string) (isset($wp_post_custom['casasync_property_address_country']) ? $wp_post_custom['casasync_property_address_country'][0] : '')
                || (string) $casasync_property_address_locality             != (string) (isset($wp_post_custom['casasync_property_address_locality']) ? $wp_post_custom['casasync_property_address_locality'][0] : '')
                || (string) $casasync_property_address_region               != (string) (isset($wp_post_custom['casasync_property_address_region']) ? $wp_post_custom['casasync_property_address_region'][0] : '')
                || (string) $casasync_property_address_postalcode           != (string) (isset($wp_post_custom['casasync_property_address_postalcode']) ? $wp_post_custom['casasync_property_address_postalcode'][0] : '')
                || (string) $casasync_property_address_streetaddress        != (string) (isset($wp_post_custom['casasync_property_address_streetaddress']) ? $wp_post_custom['casasync_property_address_streetaddress'][0] : '')
                || (string) $casasync_property_address_streetnumber         != (string) (isset($wp_post_custom['casasync_property_address_streetnumber']) ? $wp_post_custom['casasync_property_address_streetnumber'][0] : '')


                || (float) $casasync_property_geo_latitude  != (float) (isset($wp_post_custom['casasync_property_geo_latitude']) ? $wp_post_custom['casasync_property_geo_latitude'][0] : 0)
                || (float) $casasync_property_geo_longitude != (float) (isset($wp_post_custom['casasync_property_geo_longitude']) ? $wp_post_custom['casasync_property_geo_longitude'][0] : 0)

                || (string) $the_urls   != (string) (isset($wp_post_custom['casasync_urls']) ? $wp_post_custom['casasync_urls'][0] : 0)

                || (string) $casasync_start                      != (string) (isset($wp_post_custom['casasync_start']) ? $wp_post_custom['casasync_start'][0] : 0)
                || (string) $casasync_referenceId                != (string) (isset($wp_post_custom['casasync_referenceId']) ? $wp_post_custom['casasync_referenceId'][0] : 0)

                || (string) $availability !=                  (string) (isset($wp_post_custom['availability']) ? $wp_post_custom['availability'][0] : '')
                || (string) $availability_label !=            (string) (isset($wp_post_custom['availability_label']) ? $wp_post_custom['availability_label'][0] : '')

                || (string) $offer_type !=                  (string) (isset($wp_post_custom['offer_type']) ? $wp_post_custom['offer_type'][0] : '')
                || (string) $price_currency !=              (string) (isset($wp_post_custom['price_currency']) ? $wp_post_custom['price_currency'][0] : '')
                || (string) $price_timesegment !=           (string) (isset($wp_post_custom['price_timesegment']) ? $wp_post_custom['price_timesegment'][0] : '')
                || (string) $price_propertysegment !=       (string) (isset($wp_post_custom['price_propertysegment']) ? $wp_post_custom['price_propertysegment'][0] : '')
                || (float)  $price !=                       (float)  (isset($wp_post_custom['price']) ? $wp_post_custom['price'][0] : '')
                || (string) $grossPrice_timesegment !=        (string) (isset($wp_post_custom['grossPrice_timesegment']) ? $wp_post_custom['grossPrice_timesegment'][0] : '')
                || (string) $grossPrice_propertysegment !=    (string) (isset($wp_post_custom['grossPrice_propertysegment']) ? $wp_post_custom['grossPrice_propertysegment'][0] : '')
                || (float)  $grossPrice !=                    (float)  (isset($wp_post_custom['grossPrice']) ? $wp_post_custom['grossPrice'][0] : '')
                || (string) $netPrice_timesegment !=        (string) (isset($wp_post_custom['netPrice_timesegment']) ? $wp_post_custom['netPrice_timesegment'][0] : '')
                || (string) $netPrice_propertysegment !=    (string) (isset($wp_post_custom['netPrice_propertysegment']) ? $wp_post_custom['netPrice_propertysegment'][0] : '')
                || (float)  $netPrice !=                    (float)  (isset($wp_post_custom['netPrice']) ? $wp_post_custom['netPrice'][0] : '')
                || (array) $extraPrice !=                   (array) (isset($wp_post_custom['extraPrice']) ? json_decode($wp_post_custom['extraPrice'][0], true) : array())

                || (string) $seller_org_address_country                  !=     (string) (isset($wp_post_custom['seller_org_address_country'])                  ? $wp_post_custom['seller_org_address_country'][0] : '')
                || (string) $seller_org_address_locality                 !=     (string) (isset($wp_post_custom['seller_org_address_locality'])                 ? $wp_post_custom['seller_org_address_locality'][0] : '')
                || (string) $seller_org_address_region                   !=     (string) (isset($wp_post_custom['seller_org_address_region'])                   ? $wp_post_custom['seller_org_address_region'][0] : '')
                || (string) $seller_org_address_postalcode               !=     (string) (isset($wp_post_custom['seller_org_address_postalcode'])               ? $wp_post_custom['seller_org_address_postalcode'][0] : '')
                || (string) $seller_org_address_postofficeboxnumber      !=     (string) (isset($wp_post_custom['seller_org_address_postofficeboxnumber'])      ? $wp_post_custom['seller_org_address_postofficeboxnumber'][0] : '')
                || (string) $seller_org_address_streetaddress            !=     (string) (isset($wp_post_custom['seller_org_address_streetaddress'])            ? $wp_post_custom['seller_org_address_streetaddress'][0] : '')
                || (string) $seller_org_legalname                        !=     (string) (isset($wp_post_custom['seller_org_legalname'])                        ? $wp_post_custom['seller_org_legalname'][0] : '')
                || (string) $seller_org_email                            !=     (string) (isset($wp_post_custom['seller_org_email'])                            ? $wp_post_custom['seller_org_email'][0] : '')
                || (string) $seller_org_fax                              !=     (string) (isset($wp_post_custom['seller_org_fax'])                              ? $wp_post_custom['seller_org_fax'][0] : '')
                || (string) $seller_org_phone_direct                     !=     (string) (isset($wp_post_custom['seller_org_phone_direct'])                     ? $wp_post_custom['seller_org_phone_direct'][0] : '')
                || (string) $seller_org_phone_central                    !=     (string) (isset($wp_post_custom['seller_org_phone_central'])                    ? $wp_post_custom['seller_org_phone_central'][0] : '')
                || (string) $seller_org_phone_mobile                     !=     (string) (isset($wp_post_custom['seller_org_phone_mobile'])                     ? $wp_post_custom['seller_org_phone_mobile'][0] : '')
                || (string) $seller_org_brand                            !=     (string) (isset($wp_post_custom['seller_org_brand'])                            ? $wp_post_custom['seller_org_brand'][0] : '')
                || (string) $seller_person_function                      !=     (string) (isset($wp_post_custom['seller_person_function'])                      ? $wp_post_custom['seller_person_function'][0] : '')
                || (string) $seller_person_givenname                     !=     (string) (isset($wp_post_custom['seller_person_givenname'])                     ? $wp_post_custom['seller_person_givenname'][0] : '')
                || (string) $seller_person_familyname                    !=     (string) (isset($wp_post_custom['seller_person_familyname'])                    ? $wp_post_custom['seller_person_familyname'][0] : '')
                || (string) $seller_person_email                         !=     (string) (isset($wp_post_custom['seller_person_email'])                         ? $wp_post_custom['seller_person_email'][0] : '')
                || (string) $seller_person_fax                           !=     (string) (isset($wp_post_custom['seller_person_fax'])                           ? $wp_post_custom['seller_person_fax'][0] : '')
                || (string) $seller_person_phone_direct                  !=     (string) (isset($wp_post_custom['seller_person_phone_direct'])                  ? $wp_post_custom['seller_person_phone_direct'][0] : '')
                || (string) $seller_person_phone_central                 !=     (string) (isset($wp_post_custom['seller_person_phone_central'])                 ? $wp_post_custom['seller_person_phone_central'][0] : '')
                || (string) $seller_person_phone_mobile                  !=     (string) (isset($wp_post_custom['seller_person_phone_mobile'])                  ? $wp_post_custom['seller_person_phone_mobile'][0] : '')
                || (string) $seller_person_phone_gender                  !=     (string) (isset($wp_post_custom['seller_person_phone_gender'])                  ? $wp_post_custom['seller_person_phone_gender'][0] : '')

                || (string) $seller_inquiry_person_function              !=     (string) (isset($wp_post_custom['seller_inquiry_person_function'])                      ? $wp_post_custom['seller_inquiry_person_function'][0] : '')
                || (string) $seller_inquiry_person_givenname             !=     (string) (isset($wp_post_custom['seller_inquiry_person_givenname'])                     ? $wp_post_custom['seller_inquiry_person_givenname'][0] : '')
                || (string) $seller_inquiry_person_familyname            !=     (string) (isset($wp_post_custom['seller_inquiry_person_familyname'])                    ? $wp_post_custom['seller_inquiry_person_familyname'][0] : '')
                || (string) $seller_inquiry_person_email                 !=     (string) (isset($wp_post_custom['seller_inquiry_person_email'])                         ? $wp_post_custom['seller_inquiry_person_email'][0] : '')
                || (string) $seller_inquiry_person_fax                   !=     (string) (isset($wp_post_custom['seller_inquiry_person_fax'])                           ? $wp_post_custom['seller_inquiry_person_fax'][0] : '')
                || (string) $seller_inquiry_person_phone_direct          !=     (string) (isset($wp_post_custom['seller_inquiry_person_phone_direct'])                  ? $wp_post_custom['seller_inquiry_person_phone_direct'][0] : '')
                || (string) $seller_inquiry_person_phone_central         !=     (string) (isset($wp_post_custom['seller_inquiry_person_phone_central'])                 ? $wp_post_custom['seller_inquiry_person_phone_central'][0] : '')
                || (string) $seller_inquiry_person_phone_mobile          !=     (string) (isset($wp_post_custom['seller_inquiry_person_phone_mobile'])                  ? $wp_post_custom['seller_inquiry_person_phone_mobile'][0] : '')
                || (string) $seller_inquiry_person_phone_gender          !=     (string) (isset($wp_post_custom['seller_inquiry_person_phone_gender'])                  ? $wp_post_custom['seller_inquiry_person_phone_gender'][0] : '')

            ) {
                $changed = true;
                $the_post_custom['casasync_visitInformation']               = (string) $casasync_visitInformation;

                $the_post_custom['casasync_property_url']               = (string) $casasync_property_url;
                $the_post_custom['casasync_property_address_country']               = (string) $casasync_property_address_country               ;
                $the_post_custom['casasync_property_address_locality']              = (string) $casasync_property_address_locality              ;
                $the_post_custom['casasync_property_address_region']                = (string) $casasync_property_address_region                ;
                $the_post_custom['casasync_property_address_postalcode']            = (string) $casasync_property_address_postalcode            ;
                $the_post_custom['casasync_property_address_streetaddress']         = (string) $casasync_property_address_streetaddress         ;
                $the_post_custom['casasync_property_address_streetnumber']          = (string) $casasync_property_address_streetnumber         ;
                
                $the_post_custom['casasync_property_geo_latitude']          = (float) $casasync_property_geo_latitude;
                $the_post_custom['casasync_property_geo_longitude']         = (float) $casasync_property_geo_longitude;
                
                $the_post_custom['casasync_urls']                           = (string) $the_urls;
                

                $the_post_custom['casasync_start']                          = (string) $casasync_start;
                $the_post_custom['casasync_referenceId']                    = (string) $casasync_referenceId;

                $the_post_custom['availability']                            = (string) $availability;
                $the_post_custom['availability_label']                      = (string) $availability_label;

                $the_post_custom['offer_type']                              = (string) $offer_type;
                $the_post_custom['price_currency']                          = (string) $price_currency;
                $the_post_custom['price_timesegment']                       = (string) $price_timesegment;
                $the_post_custom['price_propertysegment']                   = (string) $price_propertysegment;
                $the_post_custom['price']                                   = (float)  $price;
                $the_post_custom['grossprice_timesegment']                  = (string) $grossPrice_timesegment;
                $the_post_custom['grossprice_propertysegment']              = (string) $grossPrice_propertysegment;
                $the_post_custom['grossprice']                              = (float)  $grossPrice;
                $the_post_custom['netPrice_timesegment']                    = (string) $netPrice_timesegment;
                $the_post_custom['netPrice_propertysegment']                = (string) $netPrice_propertysegment;
                $the_post_custom['netPrice']                                = (float)  $netPrice;
                $the_post_custom['extraPrice']                              = (string) json_encode($extraPrice);

                $the_post_custom['seller_org_address_country']              = (string) $seller_org_address_country;
                $the_post_custom['seller_org_address_locality']             = (string) $seller_org_address_locality;
                $the_post_custom['seller_org_address_region']               = (string) $seller_org_address_region;
                $the_post_custom['seller_org_address_postalcode']           = (string) $seller_org_address_postalcode;
                $the_post_custom['seller_org_address_postofficeboxnumber']  = (string) $seller_org_address_postofficeboxnumber;
                $the_post_custom['seller_org_address_streetaddress']        = (string) $seller_org_address_streetaddress;
                $the_post_custom['seller_org_legalname']                    = (string) $seller_org_legalname;
                $the_post_custom['seller_org_email']                        = (string) $seller_org_email;
                $the_post_custom['seller_org_fax']                          = (string) $seller_org_fax;
                $the_post_custom['seller_org_phone_direct']                 = (string) $seller_org_phone_direct;
                $the_post_custom['seller_org_phone_central']                = (string) $seller_org_phone_central;
                $the_post_custom['seller_org_phone_mobile']                 = (string) $seller_org_phone_mobile;
                $the_post_custom['seller_org_brand']                        = (string) $seller_org_brand;
                
                $the_post_custom['seller_person_function']                  = (string) $seller_person_function;
                $the_post_custom['seller_person_givenname']                 = (string) $seller_person_givenname;
                $the_post_custom['seller_person_familyname']                = (string) $seller_person_familyname;
                $the_post_custom['seller_person_email']                     = (string) $seller_person_email;
                $the_post_custom['seller_person_fax']                       = (string) $seller_person_fax;
                $the_post_custom['seller_person_phone_direct']              = (string) $seller_person_phone_direct;
                $the_post_custom['seller_person_phone_central']             = (string) $seller_person_phone_central;
                $the_post_custom['seller_person_phone_mobile']              = (string) $seller_person_phone_mobile;
                $the_post_custom['seller_person_phone_gender']              = (string) $seller_person_phone_gender;

                $the_post_custom['seller_inquiry_person_function']                  = (string) $seller_inquiry_person_function;
                $the_post_custom['seller_inquiry_person_givenname']                 = (string) $seller_inquiry_person_givenname;
                $the_post_custom['seller_inquiry_person_familyname']                = (string) $seller_inquiry_person_familyname;
                $the_post_custom['seller_inquiry_person_email']                     = (string) $seller_inquiry_person_email;
                $the_post_custom['seller_inquiry_person_fax']                       = (string) $seller_inquiry_person_fax;
                $the_post_custom['seller_inquiry_person_phone_direct']              = (string) $seller_inquiry_person_phone_direct;
                $the_post_custom['seller_inquiry_person_phone_central']             = (string) $seller_inquiry_person_phone_central;
                $the_post_custom['seller_inquiry_person_phone_mobile']              = (string) $seller_inquiry_person_phone_mobile;
                $the_post_custom['seller_inquiry_person_phone_gender']              = (string) $seller_inquiry_person_phone_gender;
            }


    
            //set numericValues
            $the_numvals = array();

            if ($property->numericValues && $property->numericValues->value) {
                foreach ($property->numericValues->value as $numval) {
                    if ($numval->__toString() && $numval['key']) {
                        $values = explode('+', $numval->__toString());
                        $the_values = array();
                        foreach ($values as $value) {
                            $numval_parts = explode('to', $value);

                            $numval_from = $numval_parts[0];
                            $numval_to = (isset($numval_parts[1]) ? $numval_parts[1] : false);
                           
                            $the_values[] = array(
                                'from' => casasync_numStringToArray($numval_from),
                                'to' => casasync_numStringToArray($numval_to)
                            );
                        }
                        $the_numvals[(string)$numval['key']] = $the_values;
                    }
                }
            }
            $casasync_floors = '';
            $casasync_living_space = '';
            $all_distance_keys = casasync_get_allDistanceKeys();
            $all_numval_keys = casasync_get_allNumvalKeys();
            $the_distances = array();
            $xml_numval = array();

            foreach ($the_numvals as $key => $numval) {
                if (in_array($key, $all_distance_keys)) {
                    $the_value = '';
                    foreach ($numval as $key2 => $value) {
                        $the_value .= ($key2 != 0 ? '+' : '') . '[' . $value['from']['value'] . $value['from']['si'] . ']'; 
                    }
                    $the_distances[$key] = $the_value;
                }
                if (in_array($key, $all_numval_keys)) {            
                    switch ($key) {
                        //multiple simple values
                        case 'floor':
                            $the_value = '';
                            foreach ($numval as $key2 => $value) {
                                $the_value .= ($key2 != 0 ? '+' : '') . '[' . $value['from']['value'] . ']'; 
                            }
                            $casasync_floors = $the_value;
                            
                            break;
                        //simple value with si
                        case 'surface_living':
                        case 'surface_property': /* ? */
                        case 'surface_usable': /* ? */
                        case 'volume': /* ? */
                        case 'ceiling_height': /* ? */
                        case 'hall_height': /* ? */
                        case 'maximal_floor_loading': /* ? */
                        case 'carrying_capacity_crane': /* ? */
                        case 'carrying_capacity_elevator': /* ? */
                            $the_value = '';
                            foreach ($numval as $key2 => $value) {
                                $the_value = $value['from']['value'] . $value['from']['si']; 
                            }
                            $xml_numval[$key] = $the_value;
                            break;
                        //INT
                        case 'year_built':
                        case 'year_renovated':
                            $the_value = '';
                            foreach ($numval as $key2 => $value) {
                                $the_value = round($value['from']['value']); 
                            }
                            $xml_numval[$key] = $the_value;
                            break;
                        //float
                        case 'number_of_rooms':
                        case 'number_of_apartments':
                        case 'number_of_floors':
                            $the_value = '';
                            foreach ($numval as $key2 => $value) {
                                $the_value = $value['from']['value']; 
                            }
                            $xml_numval[$key] = $the_value;
                            break;
                        default:
                        
                            break;
                    }
                }
            }


            foreach ($all_distance_keys as $distance_key) {
                    if (!isset($the_distances[$distance_key])) {
                        $the_distances[$distance_key] = '';
                    }
                    if ((string) $the_distances[$distance_key] != (string) (isset($wp_post_custom[$distance_key]) ? $wp_post_custom[$distance_key][0] : '') ) {
                        
                        $changed = true;
                        $the_post_custom[$distance_key] = (string) $the_distances[$distance_key];
                    }
            }


            foreach ($all_numval_keys as $numval_key) {
                    if (!isset($xml_numval[$numval_key])) {
                        $xml_numval[$numval_key] = '';
                    }
                    if ((string) $xml_numval[$numval_key] != (string) (isset($wp_post_custom[$numval_key]) ? $wp_post_custom[$numval_key][0] : '') ) {
                        $changed = true;
                        $the_post_custom[$numval_key] = (string) $xml_numval[$numval_key];
                    }
            }


            //set features
            $the_features = array();
            if ($property->features && $property->features->feature) {
                $set_orders = array();
                foreach ($property->features->feature as $feature) {
                    //requirenments
                    if ($feature['key']) {
                        $key = $feature['key']->__toString();
                        $value = $feature->__toString();

                        if ($set_orders) {
                            $next_key_available = max($set_orders) + 1;
                        } else {
                            $next_key_available = 0;
                        }
                        $order = ($feature['order'] && !in_array($feature['order']->__toString(), $set_orders) ? $feature['order']->__toString() : $next_key_available);
                        $set_orders[] = $order;

                        $the_features[$order] = array(
                                'key' => $key,
                                'value' => $value,
                            );
                    }
                }
            }
            if ($the_features) {
                ksort($the_features);
                $the_features_json = json_encode($the_features);
             
            } else {
                $the_features_json = '';
            }
            
            if (
                !$wp_property
                || (string) $the_features_json != (string) (isset($wp_post_custom['casasync_features']) ? $wp_post_custom['casasync_features'][0] : '')
            ) {
                $changed = true;
                $the_post_custom['casasync_features'] = (string) $the_features_json;
            }
            





            

            //set post global data
            $the_post['post_type'] = 'casasync_property';
            $the_post['post_status'] =  'publish';
            $the_id = ($wp_property ? $wp_property->ID : false);
            $the_post['ID'] = $the_id;
            //$changed = true;

            if ($changed) {
                //insert post
                $insert_id = wp_insert_post( $the_post);
                $found_properties[] = $insert_id;
                if (!$the_id) {
                    $the_id = $insert_id;
                }
                //set post custom fields
                foreach ($the_post_custom as $key => $value) {
                    if ($value) {
                        update_post_meta($the_id, $key, $value);
                    } else {
                        delete_post_meta($the_id, $key, $value);
                    }
                }

                //set post category
                $wp_category_terms_to_keep = array();
                $wp_casasync_category_terms_slugs = array();
                foreach ($wp_category_terms as $term) {
                        $wp_casasync_category_terms_slugs[] = $term->slug;
                }
                if ($property->category) {
                    $le_cats = array();
                    foreach ($property->category as $category) {
                            $le_cats[] = $category->__toString();
                    }
                    $the_post_category_term_slugs = $le_cats;
                }

                if ((array) $the_post_category_term_slugs != (array) $wp_casasync_category_terms_slugs ) {
                    $changed = true;
                }




                //make sure they exist first
                $terms_to_add = array();
                foreach ($the_post_category_term_slugs as $new_term_slug) {
                    $newterm = casasync_category_setTerm($new_term_slug, 'de_CH');
                    if ($newterm) {
                        $terms_to_add[] = $newterm;
                    }
                    
                }
                //figure out which to add
                $category_terms = get_terms( array('casasync_category'), array('hide_empty'    => false));
                foreach ($category_terms as $term) {
                    foreach ($the_post_category_term_slugs as $xml_slug) {
                        if ( $term->slug == $xml_slug) {
                            $terms_to_add[] = $term->term_id;
                        } 
                    }
                }

                //add THEM
                //$wp_category_terms_to_keep = array();
                wp_set_post_terms( $the_id, array_merge($terms_to_add,$wp_category_terms_to_keep), 'casasync_category' );





                //set post locations
                $lvl1_country = ($casasync_property_address_country ? $casasync_property_address_country : 'CH' );
                $lvl1_country_id = false;
                $lvl2_region = $casasync_property_address_region;
                $lvl2_region_id = false;
                $lvl3_locality = $casasync_property_address_locality;
                $lvl3_locality_id = false;

                //set country
                $country_term = get_term_by('name', $lvl1_country, 'casasync_location');
                if ($country_term) {
                    $lvl1_country_id = $country_term->term_id;
                }
                if (!$lvl1_country_id) {
                    if (!isset($new_location[$lvl1_country])) {
                        $new_location[$lvl1_country] = array('properties' => array($the_id));
                    } else {
                        $new_location[$lvl1_country]['properties'][] = $the_id;
                    }
                }
               
                //set region
                if ($lvl2_region) {

                    $region_term = get_term_by('name', $lvl2_region, 'casasync_location');
                    if ($region_term) {
                        $lvl2_region_id = $region_term->term_id;
                    }
                    if (!$lvl2_region_id) {
                        
                        if ($lvl1_country) {
                            if (!isset($new_location[$lvl1_country])) {
                                $new_location[$lvl1_country][$lvl2_region] = array('properties' => array($the_id));
                            } else {
                                $new_location[$lvl1_country][$lvl2_region]['properties'][] = $the_id;
                            }
                        } else {
                            if (!isset($new_location[$lvl2_region])) {
                                $new_location[$lvl2_region] = array('properties' => array($the_id));
                            } else {
                                $new_location[$lvl2_region]['properties'][] = $the_id;
                            }
                        }
                    }
                }

                //set city
                if ($lvl3_locality) {
                    
                    $locality_term = get_term_by('name', $lvl3_locality, 'casasync_location');
                    if ($locality_term) {
                        $lvl3_locality_id = $locality_term->term_id;
                    }
                    if (!$lvl3_locality_id) {
                        if ($lvl1_country && $lvl2_region) {
                            if (!isset($new_location[$lvl1_country][$lvl2_region])) {
                                $new_location[$lvl1_country][$lvl2_region][$lvl3_locality] = array('properties' => array($the_id));
                            } else {
                                $new_location[$lvl1_country][$lvl2_region][$lvl3_locality]['properties'][] = $the_id;
                            }
                        } elseif ($lvl2_region) {
                            if (!isset($new_location[$lvl2_region])) {
                                $new_location[$lvl2_region][$lvl3_locality] = array('properties' => array($the_id));
                            } else {
                                $new_location[$lvl2_region][$lvl3_locality]['properties'][] = $the_id;
                            }
                        } elseif ($lvl1_country){
                            if (!isset($new_location[$lvl1_country])) {
                                $new_location[$lvl1_country][$lvl3_locality] = array('properties' => array($the_id));
                            } else {
                                $new_location[$lvl1_country][$lvl3_locality]['properties'][] = $the_id;
                            }
                        }
                    }
                }

                $terms_to_add_real = array();
                if ($lvl1_country_id) {
                    $terms_to_add_real[] = $lvl1_country_id;
                }
                if ($lvl2_region_id) {
                    $terms_to_add_real[] = $lvl2_region_id;
                }
                if ($lvl3_locality_id) {
                    $terms_to_add_real[] = $lvl3_locality_id;
                }

                echo "<script>console.log('" . json_encode($terms_to_add_real, true) . "');</script>";

                wp_set_post_terms( $the_id, $terms_to_add_real, 'casasync_location' );
                
                delete_option("casasync_location_children");
                wp_cache_flush();

                //set basis
                wp_set_post_terms( $the_id, $offer_type, 'casasync_salestype' );


                //global $wpdb;
                //$wpdb->query("DELETE FROM wp_options WHERE option_name LIKE 'casasync_location_children'");


                //if new upload the new attachments
                if (!$wp_property) {
                    foreach ($the_casasync_attachments as $the_mediaitem) {
                        $new_id = casasync_upload_attachment($the_mediaitem, $the_id, $property['id']->__toString());
                    }
                    //(re)get post attachments already attached (again)
                    $args = array(
                        'post_type' => 'attachment',
                        'numberposts' => -1,
                        'post_status' => null,
                        'post_parent' => $the_id,
                        'tax_query' => array(
                            'relation' => 'AND',
                            array(
                                'taxonomy' => 'casasync_attachment_type',
                                'field' => 'slug',
                                'terms' => array( 'image', 'plan', 'document' )
                            )
                        )
                    ); 
                    $attachments = get_posts($args);
                    if ($attachments) {
                        foreach ($attachments as $attachment) {
                            $wp_casasync_attachments[] = $attachment;
                            //the_attachment_link($attachment->ID, false);
                        }
                    }
                }



                //set featured image
                if (isset($attachment_image_order) && !empty($attachment_image_order)) {
                    ksort($attachment_image_order);
                    $attachment_image_order = reset($attachment_image_order);
                    if (!empty($attachment_image_order)) {
                        foreach ($wp_casasync_attachments as $wp_mediaitem) {
                            $attachment_customfields = get_post_custom($wp_mediaitem->ID);
                            $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
                            if ($original_filename == ($attachment_image_order['file'] ? $attachment_image_order['file'] : $attachment_image_order['url'])) {
                                set_post_thumbnail( $the_id, $wp_mediaitem->ID );
                            }
                        }
                    }
                    //$attachment_image_order[0];
                } else {

                }


            }

        }

        //set new locations
        if (!empty($new_location)) {
            foreach ($new_location as $lvl1 => $lvl1_value) {
                $lvl1_id = false;
                $lvl2_id = false;
                $lvl3_id = false;
                $term = get_term_by('name', $lvl1, 'casasync_location');
                if ($term) {
                    $lvl1_id = $term->term_id;
                } else {
                    $lvl1_id = wp_insert_term( $lvl1, 'casasync_location');
                    if (!$lvl1_id instanceof WP_Error) {
                        $lvl1_id = $lvl1_id['term_id'];
                    } else {
                        $lvl1_id = false;
                    }
                    delete_option("casasync_location_children"); // clear the cache
                }
                if ($lvl1_id) {
                    if (isset($lvl1_value['properties'])) {
                        foreach ($lvl1_value['properties'] as $property_id) {
                            wp_set_post_terms( $property_id, $lvl1_id, 'casasync_location', true );
                        }
                    }
                    
                    foreach ($lvl1_value as $lvl2 => $lvl2_value) {
                        if ($lvl2 != 'properties') {
                            $term = get_term_by('name', $lvl2, 'casasync_location');
                            if ($term) {
                                $lvl2_id = $term->term_id;
                            } else {
                                $lvl2_id = wp_insert_term( $lvl2, 'casasync_location', $args = array('parent' => (int)$lvl1_id));
                                if (!$lvl2_id instanceof WP_Error) {
                                    $lvl2_id = $lvl2_id['term_id'];
                                } else {
                                    $lvl2_id = false;
                                }
                                delete_option("casasync_location_children"); // clear the cache
                            }
                            if ($lvl2_id) {
                                if (isset($lvl2_value['properties'])) {
                                    foreach ($lvl2_value['properties'] as $property_id) {
                                        wp_set_post_terms( $property_id, $lvl2_id, 'casasync_location', true );
                                    }
                                }
                                foreach ($lvl2_value as $lvl3 => $lvl3_value) {
                                    if ($lvl3 != 'properties') {
                                        $term = get_term_by('name', $lvl3, 'casasync_location');
                                        if ($term) {
                                            $lvl3_id = $term->term_id;
                                        } else {
                                            $lvl3_id = wp_insert_term( $lvl3, 'casasync_location', $args = array('parent' => (int)$lvl2_id));
                                            if (!$lvl3_id instanceof WP_Error) {
                                                $lvl3_id = $lvl3_id['term_id'];
                                            } else {
                                                $lvl3_id = false;
                                            }
                                            delete_option("casasync_location_children"); // clear the cache
                                        }
                                        if ($lvl3_id) {
                                            if (isset($lvl3_value['properties'])) {
                                                foreach ($lvl3_value['properties'] as $property_id) {
                                                    wp_set_post_terms( $property_id, $lvl3_id, 'casasync_location', true );
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } 
                }
                
                //$lvl1_id = wp_insert_term( $lv1, 'casasync_location', $args = array('parent' => (int)$lvl2_region_id) ); 
            }
        }

        //3. remove all the unused properties
        $properties_to_remove = get_posts(  array(
            'numberposts'       =>  100,
            'exclude'           =>  $found_properties,
            'post_type'         =>  'casasync_property',
            'post_status'       =>  'publish' 
            )
        );

        foreach ($properties_to_remove as $prop_to_rm) {
            //remove the attachments
            $attachments = get_posts( array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_parent' => $prop_to_rm->ID,
                'exclude'     => get_post_thumbnail_id()
            ) );

            if ( $attachments ) {
                foreach ( $attachments as $attachment ) {
                    $attachment_id = $attachment->ID;
                    global $wpdb;
                    //$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->posts, $wpdb->postmeta WHERE $wpdb->posts.ID = %d OR $wpdb->postmeta.post_id = %d", $attachment_id, $attachment_id ) );
                }
                
            }

            wp_trash_post($prop_to_rm->ID);
        }




        //4. finish off
        if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/done')) {
            mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/done');
        }
        copy ( $processing_file , CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/done/' . date('Y_m_d_H_i_s') . '_completed.xml');

        //rename ( $file , CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/data_i_' . date('Y_m_d_H_i_s') . '.xml');



    }
}

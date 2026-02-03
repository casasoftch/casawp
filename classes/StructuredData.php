<?php
if (!defined('ABSPATH')) {
    exit;
}


class StructuredData
{

    private static function get_category_slugs($offer): array
    {
        $slugs = [];
        if (is_object($offer) && method_exists($offer, 'getCategories')) {
            foreach ((array)$offer->getCategories() as $cat) {
                if (is_object($cat) && method_exists($cat, 'getKey')) {
                    $slugs[] = (string)$cat->getKey();
                }
            }
        }
        return array_values(array_unique(array_filter($slugs)));
    }

    private static function map_property_schema_type(array $slugs): string
    {
        // priority: land > parking > commercial > residential
        $land = ['building-land', 'plot', 'building-project', 'agricultural-land', 'agricultural-lot', 'commercial-lot', 'industrial-lot'];
        $parkingGarage = ['garage', 'single-garage', 'double-garage'];
        $parking = ['parking-space', 'open-slot', 'covered-slot', 'underground-slot', 'parking', 'car-park', 'multistorey-car-park'];

        $apartments = ['apartment', 'flat', 'studio', 'bachelor-flat', 'attic-flat', 'roof-flat', 'terrace-flat', 'ground-floor-flat', 'maisonette', 'loft', 'penthouse', 'granny-flat', 'furnished-flat'];
        $singleFamily = ['single-house', 'villa', 'chalet', 'bungalow', 'terrace-house', 'row-house', 'farm-house', 'rustico', 'engadine-house', 'patrician-house', 'house', 'house-part'];
        $multiFamily = ['multiple-dwelling'];

        $commercial = ['office', 'commercial-space', 'industrial-object', 'factory', 'warehouse', 'workshop', 'retail', 'retail-space', 'exhibition-space', 'laboratory', 'doctors-office', 'fuel-station', 'shopping-center', 'commercial', 'commercial-plot', 'residential-commercial-building', 'restaurant', 'cafe-bar', 'pub', 'club-disco', 'casino', 'kiosk'];

        $has = static fn($set) => (bool) array_intersect($slugs, $set);

        if ($has($land)) return 'LandParcel';
        if ($has($parkingGarage)) return 'ParkingGarage';
        if ($has($parking)) return 'ParkingFacility';
        if ($has(['hotel', 'motel', 'bed-and-breakfast'])) return 'Hotel';
        if ($has($commercial)) return 'CommercialProperty';
        if ($has($multiFamily)) return 'MultiFamilyResidence';
        if ($has($apartments)) return 'Apartment';
        if ($has($singleFamily)) return 'SingleFamilyResidence';

        return 'Residence'; // fallback
    }

    private static function get_category_labels($offer): array
    {
        $labels = [];
        if (is_object($offer) && method_exists($offer, 'getCategories')) {
            foreach ((array)$offer->getCategories() as $cat) {
                if (is_object($cat) && method_exists($cat, 'getLabel')) {
                    $labels[] = (string) $cat->getLabel();
                }
            }
        }
        return array_values(array_unique(array_filter($labels)));
    }


    public static function should_output(WP_Post $post, $offer): bool
    {
        // Only on real property singles
        if (!is_singular('casawp_property')) return false;

        // Suppress on CASAWP JSON/AJAX/map endpoints (your templates set JSON headers)
        if (!empty($_GET['ajax']) || !empty($_GET['json']) || !empty($_GET['casawp_map'])) return false;

        // Offer-level availability (your Offer object supports getAvailability())
        if (is_object($offer) && method_exists($offer, 'getAvailability')) {
            $a = (string) $offer->getAvailability();
            if (in_array($a, ['reference', 'taken', 'private'], true)) return false;
        }

        // Taxonomy-level availability (fallback)
        $terms = get_the_terms($post, 'casawp_availability');
        if (!is_wp_error($terms) && !empty($terms)) {
            $slug = $terms[0]->slug;
            if (in_array($slug, ['reference', 'taken', 'private'], true)) return false;
        }

        return (bool) apply_filters('casawp_structured_data_enabled', true, $offer, $post);
    }

    public static function render_script($offer, WP_Post $post): string
    {
        $graph = self::build_graph($offer, $post);
        $graph = apply_filters('casawp_structured_data_graph', $graph, $offer, $post);

        return '<script type="application/ld+json">' .
            wp_json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
            '</script>';
    }

    public static function build_graph($offer, WP_Post $post): array
    {
        $permalink = get_permalink($post);
        $site_name = get_bloginfo('name');

        $txt = static function ($v) {
            if ($v === null || $v === false) return '';
            return trim(wp_strip_all_tags((string)$v));
        };
        $to_float = static function ($v) {
            if ($v === null || $v === '' || $v === false) return null;
            if (is_numeric($v)) return (float)$v;
            $clean = preg_replace('/[^\d\.\,]/', '', (string)$v);
            $clean = str_replace(',', '.', $clean);
            return is_numeric($clean) ? (float)$clean : null;
        };
        $meta = static function (WP_Post $p, string $key) use ($txt) {
            return $txt(get_post_meta($p->ID, $key, true));
        };

        // --- helpers (inside build_graph(), near your other helpers)
        $getOfferField = static function ($offer, string $key) {
            if (is_object($offer) && method_exists($offer, 'getFieldValue')) {
                return $offer->getFieldValue($key);
            }
            return null;
        };

        $qv = static function (?float $value, string $unitCode) {
            if ($value === null) return null;
            return [
                '@type' => 'QuantitativeValue',
                'value' => $value,
                'unitCode' => $unitCode,
            ];
        };

        $addQVProp = static function (array &$props, string $name, ?float $value, string $unitCode) use ($qv) {
            if ($value === null) return;
            $props[] = [
                '@type' => 'PropertyValue',
                'name' => $name,
                'value' => [
                    '@type' => 'QuantitativeValue',
                    'value' => $value,
                    'unitCode' => $unitCode,
                ],
            ];
        };

        $addSimpleProp = static function (array &$props, string $name, $value) {
            if ($value === null || $value === '' || $value === false) return;
            $props[] = [
                '@type' => 'PropertyValue',
                'name' => $name,
                'value' => is_scalar($value) ? $value : wp_json_encode($value),
            ];
        };

        // --- Core
        $title = $txt(get_the_title($post));
        $desc  = $txt($post->post_content);
        $lang  = get_bloginfo('language'); // e.g. de-CH (you store per-language content)

        $datePosted = null;
        $dt = get_post_datetime($post, 'date'); // publish date (WP_DateTime)
        if ($dt instanceof DateTimeInterface) {
          $datePosted = $dt->format(DATE_ATOM);
        }

        $availabilityStarts = null;
        $startRaw = $getOfferField($offer, 'start');

        if ($startRaw) {
          $startRaw = trim((string)$startRaw);

          // Reject non-machine values early (common: "Immediate", "On Request", etc.)
          if (preg_match('/^\d{4}-\d{2}-\d{2}/', $startRaw)) {
            try {
              $startDt = new DateTimeImmutable($startRaw);
              $nowDt   = new DateTimeImmutable('now', $startDt->getTimezone());

              if ($startDt > $nowDt) {
                $availabilityStarts = $startDt->format(DATE_ATOM);
              }
            } catch (Exception $e) {
              // ignore invalid
            }
          }
        }


        // --- Images (best effort: featured)
        $images = [];
        $thumb = get_the_post_thumbnail_url($post, 'full');
        if ($thumb) $images[] = esc_url_raw($thumb);

        // --- Address
        $street  = $meta($post, 'property_address_streetaddress');
        $number  = $meta($post, 'property_address_streetnumber');
        $zip     = $meta($post, 'property_address_postalcode');
        $city    = $meta($post, 'property_address_locality');
        $region  = $meta($post, 'property_address_region');
        $country = $meta($post, 'property_address_country') ?: 'CH';

        $lat = $to_float(get_post_meta($post->ID, 'property_geo_latitude', true));
        $lng = $to_float(get_post_meta($post->ID, 'property_geo_longitude', true));

        $addressNode = array_filter([
          '@type' => 'PostalAddress',
          'streetAddress'   => trim($street . ' ' . $number) ?: null,
          'postalCode'      => $zip ?: null,
          'addressLocality' => $city ?: null,
          'addressRegion'   => $region ?: null,
          'addressCountry'  => $country ?: null,
        ]);

        $geoNode = null;
        if ($lat !== null && $lng !== null) {
          $geoNode = [
            '@type' => 'GeoCoordinates',
            'latitude'  => $lat,
            'longitude' => $lng,
          ];
        }

        // --- Place node
        $place = [
          '@type' => 'Place',
          '@id'   => $permalink . '#place',
          'address' => $addressNode ?: null,
          'geo'     => $geoNode ?: null,
        ];

        // --- read CASAWP fields
        $area_bwf = $to_float($getOfferField($offer, 'area_bwf'));        // gross living
        $area_nwf = $to_float($getOfferField($offer, 'area_nwf'));        // net living
        $area_sia_gf = $to_float($getOfferField($offer, 'area_sia_gf'));  // gross floor
        $area_sia_nf = $to_float($getOfferField($offer, 'area_sia_nf'));  // usable commercial
        $surface_property = $to_float($getOfferField($offer, 'surface_property')); // plot area

        $num_rooms  = $to_float($getOfferField($offer, 'number_of_rooms'));
        $num_floors = $to_float($getOfferField($offer, 'number_of_floors'));
        $num_lav    = $to_float($getOfferField($offer, 'number_of_lavatory'));

        $num_apts   = $to_float($getOfferField($offer, 'number_of_apartments'));
        $num_comm   = $to_float($getOfferField($offer, 'number_of_commercial_units'));

        $volume = $to_float($getOfferField($offer, 'volume'));
        $ceil_h = $to_float($getOfferField($offer, 'ceiling_height'));

        $year_built = $to_float($getOfferField($offer, 'year_built'));
        $year_reno  = $to_float($getOfferField($offer, 'year_renovated'));

        $floorLevel = $txt($getOfferField($offer, 'floor'));

        // --- decide canonical floorSize
        // Recommendation: net living as floorSize; keep the rest as additionalProperty.
        $canonical_floor_size = $area_nwf ?? $area_bwf ?? $area_sia_nf ?? $area_sia_gf;

        // --- property node
        $additionalProps = [];

        $addQVProp($additionalProps, 'Gross living area', $area_bwf, 'MTK');
        $addQVProp($additionalProps, 'Gross floor area (SIA GF)', $area_sia_gf, 'MTK');
        $addQVProp($additionalProps, 'Usable area (SIA 416 NF)', $area_sia_nf, 'MTK');

        if ($num_lav !== null) $addSimpleProp($additionalProps, 'Number of lavatories', $num_lav);
        if ($num_comm !== null) $addSimpleProp($additionalProps, 'Commercial units', $num_comm);
        if ($year_reno !== null) $addSimpleProp($additionalProps, 'Year renovated', (int)$year_reno);
        if ($num_apts !== null) $addSimpleProp($additionalProps, 'Accommodation units', (int)$num_apts);

        if ($volume !== null) $addQVProp($additionalProps, 'Volume', $volume, 'MTQ');

        $cat_slugs = self::get_category_slugs($offer);
        $cat_labels = self::get_category_labels($offer);
        $propertyType = self::map_property_schema_type($cat_slugs);
        

        $propertyNode = array_filter([
            '@type' => $propertyType,
            '@id'   => $permalink . '#property',
            'name'  => $title ?: null,

            // duplicate address/geo onto the property (fine to repeat; helps parsers)
            'address' => $addressNode ?? null,
            'geo'     => $geoNode ?? null,

            'floorSize' => $qv($canonical_floor_size, 'MTK'),
            'lotSize'   => $qv($surface_property, 'MTK'),

            'numberOfRooms' => $num_rooms !== null ? $num_rooms : null,
            'numberOfFloors' => $num_floors !== null ? (int)$num_floors : null,
            'floorLevel' => $floorLevel ?: null,

            'yearBuilt' => $year_built !== null ? (int)$year_built : null,
            'ceilingHeight' => $qv($ceil_h, 'MTR'),

            'additionalProperty' => $additionalProps ?: null,
        ]);

        // --- Availability (taxonomy)
        $availability_term = '';
        $terms = get_the_terms($post, 'casawp_availability');
        if (!is_wp_error($terms) && !empty($terms)) $availability_term = $terms[0]->slug;

        $availability_map = [
            'active'    => 'https://schema.org/InStock',
            'reserved'  => 'https://schema.org/Reserved',
            'taken'     => 'https://schema.org/SoldOut',
            'reference' => 'https://schema.org/SoldOut',
            'private'   => 'https://schema.org/Discontinued',
        ];
        $schema_availability = $availability_map[$availability_term] ?? 'https://schema.org/InStock';

        // --- Sale type (taxonomy)
        $sale_type = '';
        if (has_term('rent', 'casawp_salestype', $post)) $sale_type = 'rent';
        if (has_term('buy',  'casawp_salestype', $post)) $sale_type = 'buy';

        // --- Price fields (from your ACF)
        $currency = $meta($post, 'price_currency') ?: 'CHF';

        $price = null;
        $timesegment = null;

        if ($sale_type === 'buy') {
            $price = $to_float(get_post_meta($post->ID, 'price', true));
        } elseif ($sale_type === 'rent') {
            $gross = get_post_meta($post->ID, 'grossPrice', true);
            $net   = get_post_meta($post->ID, 'netPrice', true);
            $price = $to_float($gross !== '' ? $gross : $net);

            $tsGross = $meta($post, 'grossPrice_timesegment');
            $tsNet   = $meta($post, 'netPrice_timesegment');
            $timesegment = $tsGross ?: ($tsNet ?: 'm'); // default month
        }

        $unitTextMap = [
            'm' => 'MONTH',
            'w' => 'WEEK',
            'd' => 'DAY',
            'y' => 'YEAR',
            'h' => 'HOUR',
        ];
        $unitText = $unitTextMap[$timesegment] ?? 'MONTH';

        // --- Identifiers
        $casawp_id = $meta($post, 'casawp_id');
        $ref_id    = $meta($post, 'referenceId');

        $orgId = trailingslashit(home_url('/')) . '#organization';

        $brokerRef = ['@id' => $orgId];

        // --- Agent (display person)
        $given  = $meta($post, 'seller_view_person_givenname');
        $family = $meta($post, 'seller_view_person_familyname');
        $agentName = trim($given . ' ' . $family);

        $agentEmail = $meta($post, 'seller_view_person_email');
        $agentPhone = $meta($post, 'seller_view_person_phone_direct') ?: $meta($post, 'seller_view_person_phone_mobile');

        $agent = null;

        if ($agentName) {
            $companyLike = mb_strtolower(trim($agentName));

            $looksLikeCompany =
            preg_match('/\b(gmbh|ag|sa|sarl|ltd|inc|kg|llc|plc)\b/u', $companyLike) ||
            preg_match('/\b(immobilien|real\s?estate|groupe|group|holding)\b/u', $companyLike);

            $norm = static function (string $s) {
              $s = mb_strtolower(trim($s));
              $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s); // keep letters+digits only
              return $s;
            };

            if ($norm($agentName) !== $norm($site_name) && !$looksLikeCompany) {
                $agent = array_filter([
                    '@type' => 'Person',
                    '@id'   => $permalink . '#agent',
                    'name'  => $agentName,
                    'email' => $agentEmail ? ('mailto:' . $agentEmail) : null,
                    'telephone' => $agentPhone ?: null,
                ]);
            }
        }


        // --- Offer node
        $offerNode = [
            '@type' => 'Offer',
            '@id'   => $permalink . '#offer',
            'url'   => $permalink,
            'availability'   => $schema_availability,
            'priceCurrency'  => $currency,
            'seller' => $brokerRef,
        ];
        // Add categories in-domain (Offer supports "category")
        if (!empty($cat_labels)) {
          $offerNode['category'] = $cat_labels; // human readable
        } elseif (!empty($cat_slugs)) {
          $offerNode['category'] = $cat_slugs; // fallback
        }

        if ($availabilityStarts) {
          $offerNode['availabilityStarts'] = $availabilityStarts;
          $offerNode['validFrom'] = $availabilityStarts; // optional but consistent
        } elseif (!empty($datePosted)) {
          $offerNode['validFrom'] = $datePosted; // optional
        }



        // --- link the offer to the property
        $offerNode['itemOffered'] = ['@id' => $propertyNode['@id']];

        if ($price !== null) {
            if ($sale_type === 'rent') {
                $offerNode['priceSpecification'] = [
                    '@type' => 'UnitPriceSpecification',
                    'price' => $price,
                    'priceCurrency' => $currency,
                    'unitText' => $unitText,
                ];
            } else {
                $offerNode['price'] = $price;
            }
        }
        $offerNode['offeredBy'] = $brokerRef;

        // --- Listing node (use a conservative property type; you can upgrade via filters)
        $listing = array_filter([
            '@type' => 'RealEstateListing',
            '@id'   => $permalink . '#listing',
            'url'   => $permalink,
            'name'  => $title ?: null,
            'description' => $desc ?: null,
            'inLanguage'  => $lang ?: null,
            'image'       => $images ?: null,
            'mainEntity' => ['@id' => $propertyNode['@id']],
            'offers'      => ['@id' => $offerNode['@id']],
            'contentLocation' => ['@id' => $place['@id']],
            'mainEntityOfPage' => $permalink,
            'identifier' => array_values(array_filter([
                $casawp_id ? [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'CASAWP',
                    'value' => $casawp_id,
                ] : null,
                $ref_id ? [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'ReferenceId',
                    'value' => $ref_id,
                ] : null,
            ])) ?: null,
        ]);

        if ($datePosted) {
          $listing['datePosted'] = $datePosted;
        }

        // Brokerage is the provider
        $listing['provider'] = $brokerRef;

        // Optional: if you have a Person, expose them as a contact
        if ($agent) {
            $listing['contactPoint'] = array_filter([
              '@type' => 'ContactPoint',
              'contactType' => 'sales',
              'name' => $agentName ?: null,
              'email' => $agentEmail ? ('mailto:' . $agentEmail) : null,
              'telephone' => $agentPhone ?: null,
            ]);
        }

        $isYoastActive = defined('WPSEO_VERSION') || class_exists('WPSEO_Options');

        $orgNode = null;
        if (!$isYoastActive) {
          $orgNode = [
            '@type' => 'Organization',
            '@id'   => $orgId,
            'name'  => $site_name,
            'url'   => home_url('/'),
          ];
        }


        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                $orgNode,
                $place,
                $propertyNode,
                $offerNode,
                $listing,
            ])),
        ];
    }
}

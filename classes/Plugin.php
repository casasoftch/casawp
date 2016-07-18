<?php
namespace casawp;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver;
use Zend\EventManager\EventManager;
use Zend\Http\PhpEnvironment;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceManager;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\I18n\Translator\Translator;

class Plugin {  
    public $textids = false;
    public $fields = false;
    public $meta_box = false;
    public $admin = false;
    public $conversion = null;
    public $show_sticky = true;
    public $tax_query = array();
    public $translator = null;
    public $locale = 'de';
    public $configuration = array();

    public function __construct($configuration){  
        $this->configuration = $configuration;
        $this->conversion = new Conversion;
        $this->locale = substr(get_bloginfo('language'), 0, 2);
        add_filter('icl_set_current_language', array($this, 'wpmlLanguageSwitchedTo'));

        //what is this?
        add_shortcode('casawp_contact', array($this,'contact_shortcode'));

        add_shortcode('casawp_properties', array($this,'properties_shortcode'));

        add_action('init', array($this, 'setPostTypes'));
        if(function_exists('acf_add_local_field_group') ):
            add_action('init', array($this, 'setACF'));
        endif;


        add_action('wp_enqueue_scripts', array($this, 'registerScriptsAndStyles'));
        add_action('wp_enqueue_scripts', array($this, 'setOptionJsVars'));
        add_filter("attachment_fields_to_edit", array($this, "casawp_image_attachment_fields_to_edit"), null, 2);
        add_filter("attachment_fields_to_save", array($this, "casawp_image_attachment_fields_to_save"), null, 2);
        if (!is_admin()) {
            add_action('pre_get_posts', array($this, 'casawp_queryfilter'));  
        }

        add_filter( 'template_include', array($this, 'include_template_function'), 1 );
        register_activation_hook(CASASYNC_PLUGIN_DIR, array($this, 'casawp_activation'));
        register_deactivation_hook(CASASYNC_PLUGIN_DIR, array($this, 'casawp_deactivation'));

        add_action('wp_head', array($this, 'add_meta_tags'));

        add_action( 'right_now_content_table_end', array($this, 'casawp_right_now') );
    
        if ( function_exists( 'add_theme_support' ) ) {
            add_theme_support( 'post-thumbnails' );
            set_post_thumbnail_size( 150, 150 ); // default Post Thumbnail dimensions
        }
        if ( function_exists( 'add_image_size' ) ) {
            //add_image_size( 'category-thumb', 300, 9999 );
            $standard_thumbnail_width = '506';
            $standard_thumbnail_height = '360';
            $thumb_size_w    = get_option('casawp_archive_show_thumbnail_size_w', 506) != '' ? get_option('casawp_archive_show_thumbnail_size_w') : $standard_thumbnail_width;
            $thumb_size_h    = get_option('casawp_archive_show_thumbnail_size_h', 360) != '' ? get_option('casawp_archive_show_thumbnail_size_h') : $standard_thumbnail_height;
            $thumb_size_crop = get_option('casawp_archive_show_thumbnail_size_crop', 506) == false ? 'true' : 'false';
            add_image_size(
                'casawp-thumb',
                $thumb_size_w,
                $thumb_size_h,
                $thumb_size_crop
            );
        }

        add_action('plugins_loaded', array($this, 'setTranslation'));

        $this->bootstrap($configuration);

        if (isset($_GET['ajax']) && $_GET['ajax'] == 'prevnext' && isset($_GET['base_id']) && $_GET['base_id']) {
            if (!isset($_GET['query'])) {
                $query = array();
            } else {
                $query = $_GET['query'];
            }
            add_action('wp_loaded', array($this, 'returnPrevNext'));
        }

    }

    public function properties_shortcode($args = array()){
        
        
        add_action('wp_enqueue_scripts', array($this, 'setArchiveParams'));

        $template_path = false;

        $viewgroup = get_option('casawp_viewgroup', 'bootstrap3');
        switch ($viewgroup) {
            case 'bootstrap2': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap2/casawp-shortcode-properties.php'; break;
            case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap4/casawp-shortcode-properties.php'; break;
            default: $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-shortcode-properties.php'; break;
        }
        if ( $theme_file = locate_template(array('casawp-shortcode-properties.php'))) {
            $template_path = $theme_file;
        }


        $the_query = $this->queryService->createWpQuery($args);

        $col_count = (isset($args['col_count']) && is_numeric($args['col_count']) ? $args['col_count'] : 3);

        return $this->render('shortcode-properties', array(
            'casawp' => $this, 
            'the_query' => $the_query,
            'col_count' => $col_count
        ));
        /*echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
        print_r($store);
        echo "</textarea>";*/

        //require_once($template_path);



    }

    public function wpmlLanguageSwitchedTo($lang) {
        if ($this->locale != substr($lang, 0, 2)) {
            $this->locale = substr($lang, 0, 2);
            $this->bootstrap($this->configuration);
        }
        return $lang;
    }

    public function returnPrevNext(){
        if (!isset($_GET['query'])) {
            $query = array();
        } else {
            $query = $_GET['query'];
        }
        $array = $this->getPrevNext($query, $_GET['base_id']);
        header('Content-Type: application/json');
        echo json_encode($array, true);
        die();
    }

    public function setArchiveParams(){
        $query = $this->queryService->getQuery();
        //$url = '/immobilien/?'.http_build_query($query);
        $url = $_SERVER['REQUEST_URI'];
        $query['archive_link'] = $url;
        wp_localize_script( 'casawp', 'casawpParams', $query);
    }

    
    private function bootstrap($configuration){
        
        // setup service manager
        $serviceManager = new ServiceManager(new ServiceManagerConfig());
        $serviceManager->setService('ApplicationConfig', $configuration);

        // set translator
        $translator = new Translator();
        $translator->addTranslationFilePattern('gettext', CASASYNC_PLUGIN_DIR. 'vendor/casasoft/casamodules/src/CasasoftStandards/language/', '%s.mo', 'casasoft-standards');
        $translator->setLocale($this->locale);
        $serviceManager->setService('translator', $translator);

        // mvc translator
        $MVCtranslator = new \Zend\Mvc\I18n\Translator($translator);
        $MVCtranslator->addTranslationFile('phpArray', CASASYNC_PLUGIN_DIR. 'resources/languages/'.substr(get_bloginfo('language'), 0, 2).'/Zend_Validate.php', 'default');
        \Zend\Validator\AbstractValidator::setDefaultTranslator($MVCtranslator);
        $this->MVCtranslator = $MVCtranslator;

        $this->translator = $translator;

        // load modules -- which will provide services, configuration, and more
        $serviceManager->get('ModuleManager')->loadModules();
       
        //renderer
        $this->renderer = new PhpRenderer();
        $pluginManager = $this->renderer->getHelperPluginManager();

        //view helper plugins
        $defaultHelperMapClasses = [
            'Zend\Form\View\HelperConfig',
            'Zend\I18n\View\HelperConfig',
            'Zend\Navigation\View\HelperConfig'
        ];
        foreach ($defaultHelperMapClasses as $configClass) {
            if (is_string($configClass) && class_exists($configClass)) {
                $config = new $configClass;
                $config->configureServiceManager($pluginManager);
            }
        }

        $this->serviceManager = $serviceManager;
        $this->queryService = $this->serviceManager->get('casawpQuery');
        $this->categoryService = $this->serviceManager->get('CasasoftCategory');
        $this->utilityService = $this->serviceManager->get('CasasoftUtility');
        $this->numvalService = $this->serviceManager->get('CasasoftNumval');

    }

    public function getQueryService(){
        return $this->queryService;
    }

    


    public function casawp_right_now() {
        $num = wp_count_posts( 'casawp_property' );

        $num = number_format_i18n( $num->publish );
        $text = _n( 'Property', 'Properties', $num->publish, 'casawp' );
        if ( current_user_can( 'edit_pages' ) ) { 
            $num = "<a href='edit.php?post_type=widget'>$num</a>";
            $text = "<a href='edit.php?post_type=widget'>$text</a>";
        }   

        echo '<tr>';
        echo '<td class="first b b_pages">' . $num . '</td>';
        echo '<td class="t pages">' . $text . '</td>';
        echo '</tr>';
    }

    public function casawp_queryfilter($query){
        if ($query->is_main_query() && (is_tax('casawp_salestype') || is_tax('casawp_availability') || is_tax('casawp_category') || is_tax('casawp_location') || is_tax('casawp_feature') || is_post_type_archive( 'casawp_property' ))) {
            $this->queryService->setQuery();
            $query = $this->queryService->applyToWpQuery($query);
        }
        if ($query->is_main_query() && is_post_type_archive( 'casawp_project' )){
            /*$this->queryService->setQuery();
            $query = $this->queryService->applyToWpQuery($query);*/
            $query->set('post_parent', 0);
        }
        return $query;
    }
    
    
    public function setOptionJsVars(){
        $script_params = array(
           'google_maps'              => get_option('casawp_load_googlemaps', 0),
           'google_maps_zoomlevel'    => get_option('casawp_single_use_zoomlevel', 12),
           //'fancybox'                 => get_option('casawp_load_fancybox', 0),
           'featherlight'             => get_option('casawp_load_featherlight', 0),
           'chosen'                   => get_option('casawp_load_chosen', 0),
           'load_css'                 => get_option('casawp_load_css', 'bootstrapv3'),
           'load_bootstrap_js'        => get_option('casawp_load_bootstrap_scripts'),
           'thumbnails_ideal_width'   => get_option('casawp_single_thumbnail_ideal_width', 150),
        );
        //wp_localize_script( 'casawp_bootstrap2_main', 'casawpOptionParams', $script_params );
        //wp_localize_script( 'casawp_bootstrap3_main', 'casawpOptionParams', $script_params );
        //wp_localize_script( 'casawp_bootstrap4_main', 'casawpOptionParams', $script_params );
        wp_localize_script( 'casawp', 'casawpOptionParams', $script_params );
    }


    function casawp_activation() {
        register_uninstall_hook(__FILE__, array($this, 'casawp_uninstall'));
    }

    function casawp_deactivation() {
        // actions to perform once on plugin deactivation go here
    }

    function casawp_uninstall(){
        //actions to perform once on plugin uninstall go here
    }

    public function renderSingle($post){
        $offer = $this->prepareOffer($post);
        return $offer->render('single', array('offer' => $offer));
    }

    public function renderProjectSingle($post){
        $project = $this->prepareProject($post);
        return $project->render('project-single', array('project' => $project));
    }

    public function renderArchiveSingle($post){
        $offer = $this->prepareOffer($post);
        return $offer->render('single-archive', array('offer' => $offer));
    }

    public function renderProjectArchiveSingle($post){
        $project = $this->prepareOffer($post);
        return $project->render('project-archive-single', array('project' => $project));
    }

    public function renderArchivePagination(){
        global $wp_query;

        if ( $GLOBALS['wp_query']->max_num_pages < 2 ) {
            return;
        }

        $paged        = get_query_var( 'paged' ) ? intval( get_query_var( 'paged' ) ) : 1;
        $pagenum_link = html_entity_decode( get_pagenum_link() );
        $query_args   = array();
        $url_parts    = explode( '?', $pagenum_link );

        if ( isset( $url_parts[1] ) ) {
            wp_parse_str( $url_parts[1], $query_args );
        }

        $pagenum_link = remove_query_arg( array_keys( $query_args ), $pagenum_link );
        $pagenum_link = trailingslashit( $pagenum_link ) . '%_%';

        $format  = $GLOBALS['wp_rewrite']->using_index_permalinks() && ! strpos( $pagenum_link, 'index.php' ) ? 'index.php/' : '';
        $format .= $GLOBALS['wp_rewrite']->using_permalinks() ? user_trailingslashit( 'page/%#%', 'paged' ) : '?paged=%#%';

        // Set up paginated links.
        $links = paginate_links( array(
            'base'     => $pagenum_link,
            'format'   => $format,
            'total'    => $GLOBALS['wp_query']->max_num_pages,
            'current'  => $paged,
            'mid_size' => 1,
            'add_args' => $query_args,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'type' => 'list',
        ) );

        if ( $links ) {
            return '<div class="casawp-pagination pagination">' . $links . '</div>';
        }
/*
        $total_pages = $wp_query->max_num_pages;
        if ($total_pages > 1) {
            $current_page = max(1, get_query_var('paged'));
            if($current_page) {
                //TODO: prev/next These dont work yet!
                $i = 0;
                $return = '<ul class="casawp-pagination pagination">';
                $return .= '<li class="disabled"><a href="#">&laquo;</span></a></li>';
                while ($i < $total_pages) {
                    $i++;
                    if ($current_page == $i) {
                        $return .= '<li><a href="#"><span>' . $i . '<span class="sr-only">(current)</span></span></a></li>';
                    } else {
                        $return .= '<li><a href="' . get_pagenum_link($i) . '">' . $i . '</a></li>';
                  }
                }
                $return .= '<li class="disabled"><a href="#">&raquo;</a></li>';
                $return .= '</ul>';
                return $return;
            }
        }*/
    }

    public function render($view, $args){
        $renderer = $this->renderer;
        $resolver = new Resolver\AggregateResolver();
        $renderer->setResolver($resolver);


        $stack = new Resolver\TemplatePathStack(array(
            'script_paths' => array(
                CASASYNC_PLUGIN_DIR . '/theme-defaults/casawp',
                get_template_directory() . '/casawp'
            )
        ));
        $resolver->attach($stack);
        $model = new ViewModel($args);

        $stack = array(
            'bootstrap3',
            'bootstrap4'
        );

        $viewgroup = get_option('casawp_viewgroup', 'bootstrap3');

        $template = $viewgroup.'/'.$view;
        if (false === $resolver->resolve($template)) {
            $template = false;

            //try up the stack
            for ($i=1; $i < 5; $i++) { 
                $ancestor = array_search($viewgroup, $stack)-$i;    
                if (isset($stack[$ancestor])) {
                    if (false === $resolver->resolve($stack[$ancestor].'/'.$view)) {
                        continue;
                    } else {
                        $template = $stack[$ancestor].'/'.$view;
                        break;
                    }
                } else {
                    break;
                }   
            }

            if (!$template) {
                return "View file not found for: " . $viewgroup;
            }

        }
        $model->setTemplate($template);

        $result = $renderer->render($model);

        return $result;
    }

    public function renderPrice($value, $currency, $propertySegment, $timeSegment){
        $timesegment_labels = array(
            'm' => __('month', 'casawp'),
            'w' => __('week', 'casawp'),
            'd' => __('day', 'casawp'),
            'y' => __('year', 'casawp'),
            'h' => __('hour', 'casawp')
        );

        if ($value) {
            $parts = array();
            $parts[] = $currency;
            $parts[] = number_format(round($value), 0, '', '\'') . '.–';
            $parts[] = ($propertySegment == 'm' ? ' / m<sup>2</sup>' : '' );
            $parts[] = (in_array($timeSegment, array_keys($timesegment_labels)) ? ' / ' . $timesegment_labels[$timeSegment] : '' );
            array_walk($parts, function(&$value){ $value = trim($value);});
            $parts = array_filter($parts);
            return implode(' ', $parts);
        } else {
            return false;
        }
    }

    public function getCategories(){
        $categories = array();
        $category_terms = get_terms('casawp_category', array(
            'hide_empty'        => true, 
        ));
        $c_trans = null;

        $locale = get_locale();
        $lang = "de";
        switch (substr($locale, 0, 2)) {
            case 'de': $lang = 'de'; break;
            case 'en': $lang = 'en'; break;
            case 'it': $lang = 'it'; break;
            case 'fr': $lang = 'fr'; break;
            default: $lang = 'de'; break;
        }

        foreach ($category_terms as $category_term) {
            if ($this->categoryService->keyExists($category_term->slug)) {
                $categories[] = $this->categoryService->getItem($category_term->slug);
            } else if ($this->utilityService->keyExists($category_term->slug)) {
                //$categories[] = $this->utilityService->getItem($category_term->slug);
            } else {
                //needs to check for custom categories


                $unknown_category = new \CasasoftStandards\Service\Category();
                $unknown_category->setKey($category_term->slug);

                if ($c_trans === null) {
                    $c_trans = maybe_unserialize(get_option('casawp_custom_category_translations'));
                    if (!$c_trans) {
                        $c_trans = array();
                    }
                }

                $unknown_category->setLabel($unknown_category->getKey()); 

                foreach ($c_trans as $key => $trans) {
                    if ($key == $category_term->slug && array_key_exists($lang, $trans)) {
                        $unknown_category->setLabel($trans[$lang]); 
                    }
                }

                $categories[] = $unknown_category;
            }
        }
        return $categories;
    }

    public function getSalestypes(){
        $salestypes = array();
        $salestype_terms = get_terms('casawp_salestype', array(
            'hide_empty'        => true, 
        ));
        foreach ($salestype_terms as $salestype_term) {
            switch ($salestype_term->slug) {
                case 'rent': $salestypes[$salestype_term->slug] = __('Rent', 'casawp'); break;
                case 'buy': $salestypes[$salestype_term->slug] = __('Buy', 'casawp'); break;
                default: $salestypes[$salestype_term->slug] = $salestype_term->slug; break;
            }
        }
        return $salestypes;
    }

    public function getAvailabilities(){
        $availabilities = array();
        $availability_terms = get_terms('casawp_availability', array(
            'hide_empty'        => true, 
        ));
        foreach ($availability_terms as $availability_term) {
            switch ($availability_term->slug) {
                case 'active': $availabilities[$availability_term->slug] = __('Active', 'casawp'); break;
                case 'reference': $availabilities[$availability_term->slug] = __('Reference', 'casawp'); break;
                default: $availabilities[$availability_term->slug] = $availability_term->slug; break;
            }
        }
        return $availabilities;
    }

    public function isReferenceArchive(){
        $query = $this->queryService->getQuery();
        $reference = false;
        if ($query && isset($query['availabilities']) && in_array('reference', $query['availabilities'])) {
            $reference = true;
        }
        return $reference;
    }

    public function getQueriedSingularAvailability(){
        $query = $this->queryService->getQuery();
        if (isset($query['availabilities']) && count($query['availabilities']) == 1) {
            return $query['availabilities'][0];
        }
        return false;
    }

    public function getLocations(){
        $localities = get_terms('casawp_location',array(
            'hierarchical'      => true
        ));
        $availability = $this->getQueriedSingularAvailability();
        if ($availability) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT wp_terms.term_id FROM wp_terms 
                INNER JOIN wp_term_taxonomy ON wp_term_taxonomy.term_id = wp_terms.term_id AND wp_term_taxonomy.taxonomy = 'casawp_location'
                INNER JOIN wp_term_relationships ON wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id 
                INNER JOIN wp_posts ON wp_term_relationships.object_id = wp_posts.ID AND wp_posts.post_status = 'publish'

                INNER JOIN wp_term_relationships AS referenceCheck ON referenceCheck.object_id = wp_posts.ID
                INNER JOIN wp_term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_availability'
                INNER JOIN wp_terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$availability'
                GROUP BY wp_terms.term_id";
            $location_property_count = $wpdb->get_results( $query, ARRAY_A );

            $location_id_array = array_map(function($item){return $item['term_id'];}, $location_property_count);

            foreach ($localities as $key => $locality) {
                if (!in_array($locality->term_id, $location_id_array)) {
                    unset($localities[$key]);
                }
            }
        }
        return $localities;
    }

    public function renderArchiveFilter(){
        $form = new \casawp\Form\FilterForm(
            array(
                'casawp_filter_categories_as_checkboxes' => get_option('casawp_filter_categories_as_checkboxes', false)
            ),
            $this->getCategories(),
            $this->getSalestypes(),
            $this->getLocations(),
            $this->getAvailabilities()
        );
        $form->bind($this->queryService);
        return $this->render('archive-filter', array('form' => $form));
    }

    public function getPrevNext($query, $base_post_id){
        $lapost = get_post( $base_post_id );
        $query['posts_per_page'] = 100;
        $this->queryService->setQuery($query);
        $args = $this->queryService->getArgs();
        $args['post_type'] = 'casawp_property';
        $the_query = new \WP_Query($args);

        $prev = false;
        $next = false;
        while($the_query->have_posts() ) {
            $the_query->next_post();
            if ($the_query->post->post_name == $lapost->post_name) {
                if ($the_query->current_post + 1 < $the_query->post_count ) {
                    $next_post = $the_query->next_post();
                    $next = $next_post;
                    break;
                }
            }
            if ($the_query->post_count-1 != $the_query->current_post) { //because nextpost will fail at the end :-)
                $prev = $the_query->post;
            }
        }

        $prevnext = array(
          'nextlink' => ($prev ? get_permalink($prev->ID) : 'no'), 
          'prevlink' => ($next ? get_permalink($next->ID) : 'no')
        );
        return $prevnext;
    }

    public function include_template_function( $template_path ) {

        //project main view files
        if ( get_post_type() == 'casawp_property' && is_single()) {
            if ($_GET && (isset($_GET['ajax']) || isset($_GET['json'])  || isset($_GET['casawp_map']))) {
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-single-json.php';
                if ( $theme_file = locate_template( array( 'casawp-single-json.php' ) ) ) {
                    $template_path = $theme_file;
                }    
                
                header('Content-Type: application/json');
                
            } else {
                $viewgroup = get_option('casawp_viewgroup', 'bootstrap3');
                switch ($viewgroup) {
                    case 'bootstrap2': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap2/casawp-single.php'; break;
                    case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap4/casawp-single.php'; break;
                    default: $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-single.php'; break;
                }
                if ( $theme_file = locate_template( array( 'casawp-single.php' ) ) ) {
                    $template_path = $theme_file;
                }
            }

        }
        if (is_post_type_archive( 'casawp_property' ) || is_tax('casawp_salestype') || is_tax('casawp_availability') || is_tax('casawp_category') || is_tax('casawp_location') || is_tax('casawp_feature')) {
            if ($_GET && (isset($_GET['casawp_map']) || isset($_GET['ajax']) || isset($_GET['json']) )) {
                //$template_path = CASASYNC_PLUGIN_DIR . '/ajax/properties.php';
                header('Content-Type: application/json');
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-archive-json.php';
                if ( $theme_file = locate_template( array( 'casawp-archive-json.php' ) ) ) {
                    $template_path = $theme_file;
                }
            } else {
                add_action('wp_enqueue_scripts', array($this, 'setArchiveParams'));

                $viewgroup = get_option('casawp_viewgroup', 'bootstrap3');
                switch ($viewgroup) {
                    case 'bootstrap2': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap2/casawp-archive.php'; break;
                    case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap4/casawp-archive.php'; break;
                    default: $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-archive.php'; break;
                }
                if ( $theme_file = locate_template(array('casawp-archive.php'))) {
                    $template_path = $theme_file;
                }
            }
        }

        //project main view files
        if ( get_post_type() == 'casawp_project' && is_single()) {
            if ($_GET && (isset($_GET['ajax']) || isset($_GET['json']))) {
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-project-single-json.php';
                if ( $theme_file = locate_template( array( 'casawp-single-json.php' ) ) ) {
                    $template_path = $theme_file;
                }
                header('Content-Type: application/json');

            } else {
                add_action('wp_enqueue_scripts', array($this, 'setArchiveParams'));

                $viewgroup = get_option('casawp_viewgroup', 'bootstrap3');
                switch ($viewgroup) {
                    case 'bootstrap2': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap2/casawp-project-single.php'; break;
                    case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap4/casawp-project-single.php'; break;
                    default: $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-project-single.php'; break;
                }
                if ( $theme_file = locate_template( array( 'casawp-project-single.php' ) ) ) {
                    $template_path = $theme_file;
                }
            }

        }
        if (is_post_type_archive( 'casawp_project' )) {
            if ($_GET && (isset($_GET['ajax']) || isset($_GET['json']) )) {
                //$template_path = CASASYNC_PLUGIN_DIR . '/ajax/properties.php';
                header('Content-Type: application/json');
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-project-archive-json.php';
                if ( $theme_file = locate_template( array( 'casawp-project-archive-json.php' ) ) ) {
                    $template_path = $theme_file;
                }
            } else {


                $viewgroup = get_option('casawp_viewgroup', 'bootstrap3');
                switch ($viewgroup) {
                    case 'bootstrap2': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap2/casawp-project-archive.php'; break;
                    case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap4/casawp-project-archive.php'; break;
                    default: $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-project-archive.php'; break;
                }
                if ( $theme_file = locate_template(array('casawp-project-archive.php'))) {
                    $template_path = $theme_file;
                }
            }
        }
        return $template_path;
    }

    public function casawp_image_attachment_fields_to_edit($form_fields, $post) {
        $form_fields["origin"] = array(
            "label" => __("Custom Text Field"),
            "input" => "text", // this is default if "input" is omitted
            "value" => get_post_meta($post->ID, "_custom1", true)
        );
        $form_fields["origin"]["label"] = __("Original filename");
        $form_fields["origin"]["input"] = "text";
        $form_fields["origin"]["value"] = get_post_meta($post->ID, "_origin", true);
        return $form_fields;
    }

    public function casawp_image_attachment_fields_to_save($post, $attachment) {
        if( isset($attachment['origin']) ){
            update_post_meta($post['ID'], '_origin', $attachment['origin']);
        }
        return $post;
    }

    function registerScriptsAndStyles(){
        wp_register_style( 'casawp_css', CASASYNC_PLUGIN_URL . 'plugin-assets/global/casawp.css' );
        wp_enqueue_style( 'casawp_css' );
        wp_enqueue_script('casawp', CASASYNC_PLUGIN_URL . 'plugin-assets/global/casawp.js', array( 'jquery' ), false, true );

        switch (get_option('casawp_viewgroup', 'bootstrap3')) {
            case 'bootstrap2':
                if (get_option('casawp_load_css', false)) {
                    wp_register_style( 'casawp_bootstrap2', CASASYNC_PLUGIN_URL . 'plugin-assets/bootstrap2/css/casawp_template_bs2.css' );
                    wp_enqueue_style( 'casawp_bootstrap2' );
                }
                if (get_option('casawp_load_scripts', false)) {
                    wp_enqueue_script('casawp_bootstrap2_assets', CASASYNC_PLUGIN_URL . 'plugin-assets/bootstrap2/js/bootstrap.min.js', array( 'jquery' ), false, true );
                }
                break;
            case 'bootstrap3':
                if (get_option('casawp_load_scripts', false)) {
                    wp_enqueue_script('casawp_bootstrap3_assets', CASASYNC_PLUGIN_URL . 'plugin-assets/bootstrap3/js/assets.min.js', array( 'jquery' ), false, true );
                    //wp_enqueue_script('casawp_bootstrap3_main', CASASYNC_PLUGIN_URL . 'plugin-assets/bootstrap3/js/main.min.js', array( 'jquery', 'casawp_bootstrap3_assets' ), false, true );
                }
                if (get_option('casawp_load_css', false)) {
                    wp_register_style( 'casawp_bootstrap3_css', CASASYNC_PLUGIN_URL . 'plugin-assets/bootstrap3/css/bs3.css' );
                    wp_enqueue_style( 'casawp_bootstrap3_css' );
                }
                break;
            case 'bootstrap4':
                if (get_option('casawp_load_scripts', false)) {
                    wp_enqueue_script('casawp_bootstrap4_assets', CASASYNC_PLUGIN_URL . 'plugin-assets/bootstrap4/js/assets.min.js', array( 'jquery' ), false, true );
                    //wp_enqueue_script('casawp_bootstrap4_main', CASASYNC_PLUGIN_URL . 'plugin-assets/bootstrap4/js/main.min.js', array( 'jquery', 'casawp_bootstrap4_assets' ), false, true );
                }
                if (get_option('casawp_load_css', false)) {
                    wp_register_style( 'casawp_bootstrap4_css', CASASYNC_PLUGIN_URL . 'plugin-assets/bootstrap4/css/bs4.css' );
                    wp_enqueue_style( 'casawp_bootstrap4_css' );
                }
                break;
        }

        wp_enqueue_script('jstorage', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/jstorage.js', array( 'jquery' ));

        if(is_singular('casawp_property')) {
            wp_enqueue_script('casawp_jquery_eqheight', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/jquery.equal-height-columns.js', array( 'jquery' ), false, true);
        }

        if (get_option( 'casawp_load_featherlight', 1 )) {
            wp_enqueue_script('featherlight', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/featherlight.min.js', array( 'jquery' ), false, true);
            wp_register_style('featherlight', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/featherlight.min.css' );
            wp_enqueue_style('featherlight' );
            wp_enqueue_script('featherlight-gallery', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/featherlight.gallery.min.js', array( 'jquery', 'featherlight' ), false, true );
            wp_register_style('featherlight-gallery', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/featherlight.gallery.min.css' );
            wp_enqueue_style('featherlight-gallery' );
        }


        if (get_option( 'casawp_load_chosen', 1 )) {
            wp_enqueue_script('chosen', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/chosen.jquery.min.js', array( 'jquery' ), false, true);
            wp_register_style('chosen-css', CASASYNC_PLUGIN_URL . 'plugin-assets/global/css/chosen.css' );
            wp_enqueue_style('chosen-css' );
        }

        if (get_option( 'casawp_load_googlemaps', 1 ) && is_singular('casawp_property')) {
            wp_enqueue_script('google_maps_v3', 'https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false', array(), false, true );
        }


    }

    public function setTranslation(){
        $locale = get_locale();

        switch (substr($locale, 0, 2)) {
            case 'de': $locale = 'de_DE'; break;
            case 'en': $locale = 'en_US'; break;
            case 'it': $locale = 'it_CH'; break;
            case 'fr': $locale = 'fr_CH'; break;
            default: $locale = 'de_DE'; break;
        }


        //$locale_file = get_template_directory_uri() . "/includes/languages/$locale.php";
        /* $locale_file = CASASYNC_PLUGIN_DIR . "languages/$locale.php";
        if ( is_readable( $locale_file ) ) {
            require_once( $locale_file );
        }*/
        load_plugin_textdomain('casawp', false, '/casawp/languages/' );
    }

    function setUploadDir($upload) {
        $upload['subdir'] = '/casawp' . $upload['subdir'];
        $upload['path']   = $upload['basedir'] . $upload['subdir'];
        $upload['url']    = $upload['baseurl'] . $upload['subdir'];
        return $upload;
    }

    public function setACF(){
        $used = array();
        
        add_action( 'add_meta_boxes_casawp_property', array($this,'casawp_property_custom_metaboxes'), 10, 2 );

        foreach ($this->numvalService->getTemplate() as $group => $groupsettings) {
            $fields = array();

            foreach ($groupsettings['items'] as $key => $settings) {
                $used[] = $key;
                $append = '';
                switch ($this->numvalService->getItem($key)->getSi()) {
                    case 'm': $append = 'm'; break;
                    case 'm2': $append = 'm<sup>2</sup>'; break;
                    case 'm3': $append = 'm<sup>3</sup>'; break;
                    case '%': $append = '%'; break;
                }
                $fields[] = array(
                    'key' => 'field_casawp_property_'.$key,
                    'label' => $this->numvalService->getItem($key)->getLabel(),
                    'name' => $key,
                    'type' => 'number',
                    'instructions' => '',
                    'append' => $append,
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array (
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                );
            }

            acf_add_local_field_group(array (
                'key' => 'group_casawp_property_numvals_'.$group,
                'title' => $groupsettings['name'],
                'fields' => $fields,
                'location' => array (
                    array (
                        array (
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'casawp_property',
                        ),
                    ),
                ),
                'menu_order' => 29,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
            ));
        }

        //rest numeric values
        $fields = array();
        foreach ($this->numvalService->getItems() as $numval) {
            if (!in_array($numval->getKey(), $used)) {
                $fields[] = array(
                    'key' => 'field_casawp_property_'.$numval->getKey(),
                    'label' => $numval->getLabel(),
                    'name' => $numval->getKey(),
                    'type' => 'text',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array (
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                );
            }
        }
        if ($fields) {
            acf_add_local_field_group(array (
                'key' => 'group_casawp_property_numvals_unsorted',
                'title' => 'Ungeordnete werte',
                'fields' => $fields,
                'location' => array (
                    array (
                        array (
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'casawp_property',
                        ),
                    ),
                ),
                'menu_order' => 30,
                'position' => 'default',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
            ));
        }

        //identifiers
        $fields = array();
        $fields[] = array(
            'key' => 'field_casawp_property_'.'casawp_id',
            'label' => __('CASAWP ID', 'casawp'),
            'name' => 'casawp_id',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'referenceId',
            'label' => __('Reference Nr.', 'casawp'),
            'name' => 'referenceId',
            'type' => 'text',
            'required' => 0
        );
        acf_add_local_field_group(array (
            'key' => 'group_casawp_identifiers',
            'title' => __('Identifiers', 'casawp'),
            'fields' => $fields,
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'casawp_property',
                    ),
                ),
            ),
            'menu_order' => 1,
            'position' => 'side',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));

        //property address
        $fields = array();
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_address_streetaddress',
            'label' => __('Street', 'casawp'),
            'name' => 'property_address_streetaddress',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_address_streetnumber',
            'label' => __('Nr.', 'casawp'),
            'name' => 'property_address_streetnumber',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_address_streetaddition',
            'label' => __('Addition', 'casawp'),
            'name' => 'property_address_streetaddition',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_address_postalcode',
            'label' => __('Postal Code', 'casawp'),
            'name' => 'property_address_postalcode',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_address_locality',
            'label' => __('City', 'casawp'),
            'name' => 'property_address_locality',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_address_region',
            'label' => __('Region', 'casawp'),
            'name' => 'property_address_region',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_address_country',
            'label' => __('Country', 'casawp'),
            'name' => 'property_address_country',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_geo_latitude',
            'label' => __('Latitude', 'casawp'),
            'name' => 'property_geo_latitude',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_property_'.'property_geo_longitude',
            'label' => __('Longitude', 'casawp'),
            'name' => 'property_geo_longitude',
            'type' => 'text',
            'required' => 0
        );
        acf_add_local_field_group(array (
            'key' => 'group_casawp_property_address',
            'title' => __('Address', 'casawp'),
            'fields' => $fields,
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'casawp_property',
                    ),
                ),
            ),
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'left',
            'instruction_placement' => 'label',
        ));


        //Settings
        $fields = array();
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'start',
            'label' => __('Start', 'casawp'),
            'name' => 'start',
            'type' => 'date_time_picker',
            'required' => 0,
            'date_format' => 'yy-mm-dd',
            'time_format' => 'HH:mm:ss',
            'first_day' => 1,
            'save_as_timestamp' => 'false',
            'get_as_timestamp' => 'false',
        );
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'price_currency',
            'label' => __('Currency', 'casawp'),
            'name' => 'price_currency',
            'type' => 'radio',
            'required' => 0,
            'choices' => array(
                'CHF'   => 'CHF',
                'EUR'   => '€',
                'GBP'   => '£',
                'USD'   => '$',
            ),
            'layout' => 'horizontal',
        );
        acf_add_local_field_group(array (
            'key' => 'group_casawp_setting',
            'title' => __('General settings', 'casawp'),
            'fields' => $fields,
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'casawp_property',
                    ),
                ),
            ),
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'left',
            'instruction_placement' => 'label',
        ));


        //Buy
        $fields = array();
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'price',
            'label' => __('Price', 'casawp'),
            'name' => 'price',
            'type' => 'number',
            'required' => 0,
            'step' => 1,
            'min' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'price_propertysegment',
            'label' => __('Price property segment', 'casawp'),
            'name' => 'price_propertysegment',
            'type' => 'radio',
            'required' => 0,
            'choices' => array(
                'all'   => __('All', 'casawp'),
                'm'   => 'm<sup>2</sup>'
            ),
            'layout' => 'horizontal',
        );
        acf_add_local_field_group(array (
            'key' => 'group_casawp_offer_buy',
            'title' => __('Buy', 'casawp'),
            'fields' => $fields,
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'casawp_property',
                    ),
                    array (
                        'param' => 'post_taxonomy',
                        'operator' => '==',
                        'value' => 'casawp_salestype:buy',
                    ),
                ),
            ),
            'menu_order' => 2,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'left',
            'instruction_placement' => 'label',
        ));


        //Rent
        $fields = array();
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'netPrice',
            'label' => __('Net Price', 'casawp'),
            'name' => 'netPrice',
            'type' => 'number',
            'required' => 0,
            'step' => 1,
            'min' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'netPrice_propertysegment',
            'label' => __('Net price property segment', 'casawp'),
            'name' => 'netPrice_propertysegment',
            'type' => 'radio',
            'required' => 0,
            'choices' => array(
                'all'   => __('All', 'casawp'),
                'm'   => 'm<sup>2</sup>'
            ),
            'layout' => 'horizontal',
        );
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'netPrice_timesegment',
            'label' => __('Net price time segment', 'casawp'),
            'name' => 'netPrice_propertysegment',
            'type' => 'radio',
            'required' => 0,
            'choices' => array(
                'm' => __('month', 'casawp'),
                'w' => __('week', 'casawp'),
                'd' => __('day', 'casawp'),
                'y' => __('year', 'casawp'),
                'h' => __('hour', 'casawp')
            ),
            'layout' => 'horizontal',
        );

        $fields[] = array(
            'key' => 'field_casawp_offer_'.'grossPrice',
            'label' => __('Gross Price', 'casawp'),
            'name' => 'grossPrice',
            'type' => 'number',
            'required' => 0,
            'step' => 1,
            'min' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'grossPrice_propertysegment',
            'label' => __('Gross price property segment', 'casawp'),
            'name' => 'grossPrice_propertysegment',
            'type' => 'radio',
            'required' => 0,
            'choices' => array(
                'all'   => __('All', 'casawp'),
                'm'   => 'm<sup>2</sup>'
            ),
            'layout' => 'horizontal',
        );
        $fields[] = array(
            'key' => 'field_casawp_offer_'.'grossPrice_timesegment',
            'label' => __('Gross price time segment', 'casawp'),
            'name' => 'grossPrice_propertysegment',
            'type' => 'radio',
            'required' => 0,
            'choices' => array(
                'm' => __('month', 'casawp'),
                'w' => __('week', 'casawp'),
                'd' => __('day', 'casawp'),
                'y' => __('year', 'casawp'),
                'h' => __('hour', 'casawp')
            ),
            'layout' => 'horizontal',
        );

        acf_add_local_field_group(array (
            'key' => 'group_casawp_offer_rent',
            'title' => __('Rent', 'casawp'),
            'fields' => $fields,
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'casawp_property',
                    ),
                    array (
                        'param' => 'post_taxonomy',
                        'operator' => '==',
                        'value' => 'casawp_salestype:rent',
                    ),
                ),
            ),
            'menu_order' => 2,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'left',
            'instruction_placement' => 'label',
        ));

        //organization
        $fields = array();
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_legalname',
            'label' => __('Legal name', 'casawp'),
            'name' => 'seller_org_legalname',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_brand',
            'label' => __('Brand', 'casawp'),
            'name' => 'seller_org_brand',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_phone_central',
            'label' => __('Phone', 'casawp'),
            'name' => 'seller_org_phone_central',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_address_streetaddress',
            'label' => __('Street', 'casawp'),
            'name' => 'seller_org_address_streetaddress',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_address_streetaddition',
            'label' => __('Street addition', 'casawp'),
            'name' => 'seller_org_address_streetaddition',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_address_postalcode',
            'label' => __('Postal Code', 'casawp'),
            'name' => 'seller_org_address_postalcode',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_address_locality',
            'label' => __('Locality', 'casawp'),
            'name' => 'seller_org_address_locality',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_address_region',
            'label' => __('Region', 'casawp'),
            'name' => 'seller_org_address_region',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_address_country',
            'label' => __('Country', 'casawp'),
            'name' => 'seller_org_address_country',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_person_'.'seller_org_address_postofficeboxnumber',
            'label' => __('P.O. Box', 'casawp'),
            'name' => 'seller_org_address_postofficeboxnumber',
            'type' => 'text',
            'required' => 0
        );
        acf_add_local_field_group(array (
            'key' => 'group_casawp_organization',
            'title' => __('Organization', 'casawp'),
            'fields' => $fields,
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'casawp_property',
                    ),
                ),
            ),
            'menu_order' => 19,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'left',
            'instruction_placement' => 'label',
        ));


        //people
        $prefixes = array('seller_view_person_', 'seller_inquiry_person_', 'seller_visit_person_');
        foreach ($prefixes as $prefix) {
            $fields = array();
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'email',
                'label' => __('Email', 'casawp'),
                'name' => $prefix.'email',
                'type' => 'email',
                'required' => 0
            );
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'function',
                'label' => __('Function', 'casawp'),
                'name' => $prefix.'function',
                'type' => 'text',
                'required' => 0
            );
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'givenname',
                'label' => __('Firstname', 'casawp'),
                'name' => $prefix.'givenname',
                'type' => 'text',
                'required' => 0
            );
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'familyname',
                'label' => __('Lastname', 'casawp'),
                'name' => $prefix.'familyname',
                'type' => 'text',
                'required' => 0
            );
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'fax',
                'label' => __('Fax', 'casawp'),
                'name' => $prefix.'fax',
                'type' => 'text',
                'required' => 0
            );
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'phone_direct',
                'label' => __('Direct phone', 'casawp'),
                'name' => $prefix.'phone_direct',
                'type' => 'text',
                'required' => 0
            );
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'phone_mobile',
                'label' => __('Mobile phone', 'casawp'),
                'name' => $prefix.'phone_mobile',
                'type' => 'text',
                'required' => 0
            );
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'gender',
                'label' => __('Gender', 'casawp'),
                'name' => $prefix.'gender',
                'type' => 'radio',
                'required' => 0,
                'choices' => array(
                    '0'   => __('Unknown', 'casawp'),
                    '1'   => __('Male', 'casawp'),
                    '2'   => __('Female', 'casawp'),
                ),
                'layout' => 'horizontal',
    
            );
            $fields[] = array(
                'key' => 'field_casawp_person_'.$prefix.'note',
                'label' => __('Note', 'casawp'),
                'name' => $prefix.'note',
                'type' => 'text',
                'required' => 0
            );

            $groupname = 'Person';
            switch ($prefix) {
                case 'seller_view_person_': $groupname = __('Display person','casawp');break;
                case 'seller_inquiry_person_': $groupname = __('Inquiry recipient','casawp');break;
                case 'seller_visit_person_': $groupname = __('Visit person','casawp');break;
            }

            acf_add_local_field_group(array (
                'key' => 'group_casawp_'.$prefix,
                'title' => $groupname,
                'fields' => $fields,
                'location' => array (
                    array (
                        array (
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'casawp_property',
                        ),
                    ),
                ),
                'menu_order' => 20,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
            ));
        }


        //INQUIRY POST TYPE

        $fields = array();
        $form = new \casawp\Form\ContactForm();
        foreach ($form->getElements() as $element) {
            if ($element->getName() != 'message') {
                $fields[] = array(
                    'key' => 'field_casawp_inquiry_sender_'.$element->getName(),
                    'label' => $element->getLabel(),
                    'name' => 'sender_'.$element->getName(),
                    'type' => 'text',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array (
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                );
            }
        }
        acf_add_local_field_group(array (
            'key' => 'group_casawp_inquiry',
            'title' => 'Sender',
            'fields' => $fields,
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'casawp_inquiry',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'left',
            'instruction_placement' => 'label',
            'label_placement' => 'left',
        ));


        //identifiers inquiry
        $fields = array();
        $fields[] = array(
            'key' => 'field_casawp_inquiry_'.'casawp_id',
            'label' => __('CASAWP ID', 'casawp'),
            'name' => 'casawp_id',
            'type' => 'text',
            'required' => 0
        );
        $fields[] = array(
            'key' => 'field_casawp_inquiry_'.'reference_id',
            'label' => __('Reference Nr.', 'casawp'),
            'name' => 'reference_id',
            'type' => 'text',
            'required' => 0
        );
        acf_add_local_field_group(array (
            'key' => 'group_casawp_inquiry_identifiers',
            'title' => __('Identifiers', 'casawp'),
            'fields' => $fields,
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'casawp_inquiry',
                    ),
                ),
            ),
            'menu_order' => 1,
            'position' => 'side',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));
        
    }

    public function setPostTypes(){

        /*----------  properties  ----------*/
        
        $labels = array(
            'name'               => __('Properties', 'casawp'),
            'singular_name'      => __('Property', 'casawp'),
            'add_new'            => __('Add New', 'casawp'),
            'add_new_item'       => __('Add New Property', 'casawp'),
            'edit_item'          => __('Edit Property', 'casawp'),
            'new_item'           => __('New Property', 'casawp'),
            'all_items'          => __('All Properties', 'casawp'),
            'view_item'          => __('View Property', 'casawp'),
            'search_items'       => __('Search Properties', 'casawp'),
            'not_found'          => __('No properties found', 'casawp'),
            'not_found_in_trash' => __('No properties found in Trash', 'casawp'),
            'menu_name'          => __('Properties', 'casawp')
        );
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'immobilien' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields', 'page-attributes', 'revisions' ),
            'menu_icon'          => 'dashicons-admin-home',
            'show_in_nav_menus'  => true
        );
        register_post_type( 'casawp_property', $args );


        /*----------  projects  ----------*/
        
        $labels = array(
            'name'               => __('Projects', 'casawp'),
            'singular_name'      => __('Project', 'casawp'),
            'add_new'            => __('Add New', 'casawp'),
            'add_new_item'       => __('Add New Project', 'casawp'),
            'edit_item'          => __('Edit Project', 'casawp'),
            'new_item'           => __('New Project', 'casawp'),
            'all_items'          => __('All Projects', 'casawp'),
            'view_item'          => __('View Project', 'casawp'),
            'search_items'       => __('Search Projects', 'casawp'),
            'not_found'          => __('No properties found', 'casawp'),
            'not_found_in_trash' => __('No properties found in Trash', 'casawp'),
            'menu_name'          => __('Projects', 'casawp')
        );
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'projekte' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => true,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'thumbnail'),
            'menu_icon'          => 'dashicons-admin-tools',
            'show_in_nav_menus'  => true
        );
        register_post_type( 'casawp_project', $args );
        



        /*----------  Inquiry  ----------*/
    
        $labels = array(
            'name'               => __('Inquiries', 'casawp'),
            'singular_name'      => __('Inquiry', 'casawp'),
            'add_new'            => __('Add New', 'casawp'),
            'add_new_item'       => __('Add New inquiry', 'casawp'),
            'edit_item'          => __('Edit Inquiry', 'casawp'),
            'new_item'           => __('New Inquiry', 'casawp'),
            'all_items'          => __('All Inquiries', 'casawp'),
            'view_item'          => __('View Inquiry', 'casawp'),
            'search_items'       => __('Search Inquiries', 'casawp'),
            'not_found'          => __('No inquiries found', 'casawp'),
            'not_found_in_trash' => __('No inquiries found in Trash', 'casawp'),
            'menu_name'          => __('Inquiries', 'casawp')
        );
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'anfragen' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author','custom-fields', 'page-attributes' ),
            'menu_icon'          => 'dashicons-admin-comments',
            'show_in_nav_menus'  => true
        );
        register_post_type( 'casawp_inquiry', $args );



        /*----------  category  ----------*/
        
        $labels = array(
            'name'              => __( 'Property categories', 'casawp'),
            'singular_name'     => __( 'Category', 'casawp'),
            'search_items'      => __( 'Search Categories', 'casawp'),
            'all_items'         => __( 'All Categories', 'casawp'),
            'parent_item'       => __( 'Parent Category', 'casawp'),
            'parent_item_colon' => __( 'Parent Category:', 'casawp'),
            'edit_item'         => __( 'Edit Category', 'casawp'),
            'update_item'       => __( 'Update Category', 'casawp'),
            'add_new_item'      => __( 'Add New Category', 'casawp'),
            'new_item_name'     => __( 'New Category Name', 'casawp'),
            'menu_name'         => __( 'Category', 'casawp')
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-kategorie' )
        );
        register_taxonomy( 'casawp_category', array( 'casawp_property' ), $args );

        /*----------  features  ----------*/
        
        $labels = array(
            'name'              => __( 'Property features', 'casawp'),
            'singular_name'     => __( 'Feature', 'casawp'),
            'search_items'      => __( 'Search Categories', 'casawp'),
            'all_items'         => __( 'All Categories', 'casawp'),
            'parent_item'       => __( 'Parent Feature', 'casawp'),
            'parent_item_colon' => __( 'Parent Feature:', 'casawp'),
            'edit_item'         => __( 'Edit Feature', 'casawp'),
            'update_item'       => __( 'Update Feature', 'casawp'),
            'add_new_item'      => __( 'Add New Feature', 'casawp'),
            'new_item_name'     => __( 'New Feature Name', 'casawp'),
            'menu_name'         => __( 'Feature', 'casawp')
        );
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-eigenschaft' )
        );
        register_taxonomy( 'casawp_feature', array( 'casawp_property' ), $args );
        

        /*----------  utilities  ----------*/
        
        $labels = array(
            'name'              => __( 'Property utilities', 'casawp'),
            'singular_name'     => __( 'Utility', 'casawp'),
            'search_items'      => __( 'Search Utilities', 'casawp'),
            'all_items'         => __( 'All Utilities', 'casawp'),
            'parent_item'       => __( 'Parent Utility', 'casawp'),
            'parent_item_colon' => __( 'Parent Utility:', 'casawp'),
            'edit_item'         => __( 'Edit Utility', 'casawp'),
            'update_item'       => __( 'Update Utility', 'casawp'),
            'add_new_item'      => __( 'Add New Utility', 'casawp'),
            'new_item_name'     => __( 'New Utility Name', 'casawp'),
            'menu_name'         => __( 'Utility', 'casawp')
        );
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-nutzung' )
        );
        register_taxonomy( 'casawp_utility', array( 'casawp_property' ), $args );


        /*----------  location  ----------*/
        
        $labels = array(
            'name'              => __( 'Property locations', 'casawp' ),
            'singular_name'     => __( 'Location', 'casawp' ),
            'search_items'      => __( 'Search Locations', 'casawp'),
            'all_items'         => __( 'All Locations', 'casawp'),
            'parent_item'       => __( 'Parent Location', 'casawp'),
            'parent_item_colon' => __( 'Parent Location:', 'casawp'),
            'edit_item'         => __( 'Edit Location', 'casawp'),
            'update_item'       => __( 'Update Location', 'casawp'),
            'add_new_item'      => __( 'Add New Location', 'casawp'),
            'new_item_name'     => __( 'New Location Name', 'casawp'),
            'menu_name'         => __( 'Location', 'casawp')
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-ortschaft' )
        );
        register_taxonomy( 'casawp_location', array( 'casawp_property' ), $args );


        /*----------  salestypes  ----------*/
        
        $labels = array(
            'name'                       => __( 'Property salestypes', 'casawp' ),
            'singular_name'              => __( 'Salestype', 'casawp' ),
            'search_items'               => __( 'Search Salestypes', 'casawp' ),
            'popular_items'              => __( 'Popular Salestypes', 'casawp' ),
            'all_items'                  => __( 'All Salestypes', 'casawp' ),
            'edit_item'                  => __( 'Edit Salestype', 'casawp' ),
            'update_item'                => __( 'Update Salestype', 'casawp' ),
            'add_new_item'               => __( 'Add New Salestype', 'casawp' ),
            'new_item_name'              => __( 'New Salestype Name', 'casawp' ),
            'separate_items_with_commas' => __( 'Separate salestypes with commas', 'casawp' ),
            'add_or_remove_items'        => __( 'Add or remove salestypes', 'casawp' ),
            'choose_from_most_used'      => __( 'Choose from the most used salestypes', 'casawp' ),
            'not_found'                  => __( 'No Salestypes found.', 'casawp' ),
            'menu_name'                  => __( 'Salestype', 'casawp' )
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-vermarktungsart' )
        );
        register_taxonomy( 'casawp_salestype', array( 'casawp_property' ), $args );


        /*----------  availability  ----------*/
        
        $labels = array(
            'name'                       => __( 'Property availability', 'casawp' ),
            'singular_name'              => __( 'Availability', 'casawp' ),
            'search_items'               => __( 'Search availabilities', 'casawp' ),
            'popular_items'              => __( 'Popular Availabilities', 'casawp' ),
            'all_items'                  => __( 'All Availabilities', 'casawp' ),
            'edit_item'                  => __( 'Edit Availability', 'casawp' ),
            'update_item'                => __( 'Update Availability', 'casawp' ),
            'add_new_item'               => __( 'Add New Availability', 'casawp' ),
            'new_item_name'              => __( 'New Availability Name', 'casawp' ),
            'separate_items_with_commas' => __( 'Separate availabilities with commas', 'casawp' ),
            'add_or_remove_items'        => __( 'Add or remove availabilities', 'casawp' ),
            'choose_from_most_used'      => __( 'Choose from the most used availabilities', 'casawp' ),
            'not_found'                  => __( 'No Availabilities found.', 'casawp' ),
            'menu_name'                  => __( 'Availability', 'casawp' )
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-verfuegbarkeit' )
        );
        register_taxonomy( 'casawp_availability', array( 'casawp_property' ), $args );



        /*----------  attachments  ----------*/        

        $labels = array(
          'name'              => __( 'Property Attachment Types', 'casawp' ),
            'singular_name'     => __( 'Attachment Type', 'casawp' ),
            'search_items'      => __( 'Search Attachment Types', 'casawp' ),
            'all_items'         => __( 'All Attachment Types', 'casawp' ),
            'parent_item'       => __( 'Parent Attachment Type', 'casawp' ),
            'parent_item_colon' => __( 'Parent Attachment Type:', 'casawp' ),
            'edit_item'         => __( 'Edit Attachment Type', 'casawp' ),
            'update_item'       => __( 'Update Attachment Type', 'casawp' ),
            'add_new_item'      => __( 'Add New Attachment Type', 'casawp' ),
            'new_item_name'     => __( 'New Attachment Type Name', 'casawp' ),
            'menu_name'         => __( 'Attachment Type', 'casawp' )
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-anhangstyp' )
        );
        register_taxonomy( 'casawp_attachment_type', array(), $args );
        register_taxonomy_for_object_type('casawp_attachment_type', 'attachment');
        add_post_type_support('attachment', 'casawp_attachment_type');
        $id1 = wp_insert_term('Image', 'casawp_attachment_type', array('slug' => 'image'));
        $id2 = wp_insert_term('Plan', 'casawp_attachment_type', array('slug' => 'plan'));
        $id3 = wp_insert_term('Document', 'casawp_attachment_type', array('slug' => 'document'));
        $id3 = wp_insert_term('Sales Brochure', 'casawp_attachment_type', array('slug' => 'sales-brochure'));
    }

    function casawp_property_custom_metaboxes($post){
        add_meta_box('unsorted-metas', __('Additional Meta Fields'),  array($this, 'casawp_add_unsorted_metabox'), 'casawp_property', 'normal', 'high');
    }

    function casawp_add_unsorted_metabox($post) {
        $meta_keys = get_post_custom_keys($post->ID);
        echo '<table class="acf-table">';
        $skip = array(
            'property_address_streetaddress',
            'property_address_streetnumber',
            'property_address_streetaddition',
            'property_address_postalcode',
            'property_address_locality',
            'property_address_region',
            'property_address_country',
            'property_geo_latitude',
            'property_geo_longitude',
            'netPrice',
            'netPrice_propertysegment',
            'price_currency',
            'price_propertysegment',
            'grossPrice',
            'grossPrice_propertysegment',
            'start',
            'casawp_id',
            'referenceId'
        );  

        $old = array(
            'availability'
        );

        $json_hide = array(
            'extraPrice',
            'integratedoffers',
            'the_urls'
        );

        $skip = array_merge($skip, $old, array_keys($this->numvalService->items));
           
        
        foreach ($meta_keys as $meta_key) {
            if (!in_array($meta_key, $skip) 
                && strpos($meta_key, 'seller_inquiry_person') !== 0
                && strpos($meta_key, 'seller_view_person') !== 0
                && strpos($meta_key, 'seller_visit_person') !== 0
                && strpos($meta_key, 'seller_org') !== 0
                && strpos($meta_key, '_') !== 0
            ) {
                $value = implode(', ',get_post_custom_values($meta_key, $post->ID));
                if (strpos($value, '}')) {
                    $value =  print_r(maybe_unserialize($value), true);
                }
                echo '<tr><td class="acf-label">'.$meta_key . '</td><td class="acf-input">' . $value . '</td></tr>';
            }      
        }  
        
        echo "</table>";
    }

    function add_meta_tags() {
        global $post;
        if ( is_singular('casawp_property') ) {
            echo '<meta property="og:url"          content="' . get_the_permalink() . '" />' . "\n";
            echo '<meta property="og:type"         content="article" />' . "\n";
            echo '<meta property="og:title"        content="' . htmlspecialchars(get_the_title()) . '" />' . "\n";
            echo '<meta property="og:description"  content="' . htmlspecialchars(strip_tags($post->post_content)) . '" />' . "\n";
            echo '<meta property="og:image"        content="' . wp_get_attachment_url( get_post_thumbnail_id() ) . '">' . "\n";
            echo '<meta property="og:locale"       content="' . get_locale() . '" />' . "\n";
        }
    }

    public function prepareOffer($post){
        $offerService = $this->serviceManager->get('casawpOffer');
        $offerService->setPost($post);
        return $offerService->getCurrent();
    }

    public function prepareProject($post){
        $service = $this->serviceManager->get('casawpProject');
        $service->setPost($post);
        return $service->getCurrent();
    }
}

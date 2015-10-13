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

    public function __construct($configuration){  
        $this->conversion = new Conversion;

        add_shortcode('casawp_contact', array($this,'contact_shortcode'));
        add_action('init', array($this, 'setPostTypes'));

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
            $template_path = CASASYNC_PLUGIN_DIR . '/plugin_assets/prevnext.php';
            add_action('wp_loaded', array($this, 'returnPrevNext'));

        }

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
        $translator->setLocale(substr(get_bloginfo('language'), 0, 2));
        $serviceManager->setService('Translator', $translator);

        // mvc translator
        $MVCtranslator = new \Zend\Mvc\I18n\Translator($translator);
        $MVCtranslator->addTranslationFile('phpArray', CASASYNC_PLUGIN_DIR. 'resources/languages/'.substr(get_bloginfo('language'), 0, 2).'/Zend_Validate.php', 'default');
        \Zend\Validator\AbstractValidator::setDefaultTranslator($MVCtranslator);

        

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
        $this->numvalService = $this->serviceManager->get('CasasoftNumval');
        
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

    public function renderArchiveSingle($post){
        $offer = $this->prepareOffer($post);
        return $offer->render('single-archive', array('offer' => $offer));
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
            return '<div class="casawp-pagination ' . (get_option('casawp_load_css', 'bootstrapv3') == 'bootstrapv2' ? 'pagination' : '') . '">' . $links . '</div>';
        }

        $total_pages = $wp_query->max_num_pages;
        if ($total_pages > 1) {
            $current_page = max(1, get_query_var('paged'));
            if($current_page) {
                //TODO: prev/next These dont work yet!
                $i = 0;
                $return = '<ul class="casawp-pagination">';
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
        }
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

    public function getCategories(){
        $categories = array();
        $category_terms = get_terms('casawp_category', array(
            'hide_empty'        => true, 
        ));
        foreach ($category_terms as $category_term) {
            if ($this->categoryService->keyExists($category_term->slug)) {
                $categories[] = $this->categoryService->getItem($category_term->slug);
            } else if ($this->utilityService->keyExists($category_term->slug)) {
                $categories[] = $this->utilityService->getItem($category_term->slug);
            } else {
                $unknown_category = new \CasasoftStandards\Service\Category();
                $unknown_category->setKey($category_term->slug);
                $unknown_category->setLabel('?'.$category_term->slug);
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

    public function getLocations(){
        $localities = get_terms('casawp_location',array('hierarchical'      => true));
        return $localities;
    }

    public function renderArchiveFilter(){
        $this->getLocations();

        $form = new \casawp\Form\FilterForm(
            $this->getCategories(),
            $this->getSalestypes(),
            $this->getLocations()
        );
        $form->bind($this->queryService);
        return $this->render('archive-filter', array('form' => $form));
    }

    public function getPrevNext($query, $base_post_id){
        $lapost = get_post( $base_post_id );
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
        if ( get_post_type() == 'casawp_property' && is_single()) {
            if ($_GET && (isset($_GET['ajax']) || isset($_GET['json']))) {
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-single-json.php';
                if ( $theme_file = locate_template( array( 'casawp-single-json.php' ) ) ) {
                    $template_path = $theme_file;
                }    
                
                header('Content-Type: application/json');
                
            } else {
                $viewgroup = get_option('casawp_viewgroup', 'bootstrap3');
                switch ($viewgroup) {
                    case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap4/casawp-single.php'; break;
                    default: $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-single.php'; break;
                }
                if ( $theme_file = locate_template( array( 'casawp-single.php' ) ) ) {
                    $template_path = $theme_file;
                }
            }

        }
        if (is_tax('casawp_salestype') || is_tax('casawp_availability') || is_tax('casawp_category') || is_tax('casawp_location') || is_tax('casawp_feature') || is_post_type_archive( 'casawp_property' )) {
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
                    case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap4/casawp-archive.php'; break;
                    default: $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-archive.php'; break;
                }
                if ( $theme_file = locate_template(array('casawp-archive.php'))) {
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
                    wp_register_style( 'casawp_bootstrap2', CASASYNC_PLUGIN_URL . 'plugin_assets/css/casawp_template_bs2.css' );
                    wp_enqueue_style( 'casawp_bootstrap2' );
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

        wp_enqueue_script('jstorage', CASASYNC_PLUGIN_URL . 'plugin_assets/js/jstorage.js', array( 'jquery' ));

        if(is_singular('casawp_property')) {
            wp_enqueue_script('casawp_jquery_eqheight', CASASYNC_PLUGIN_URL . 'plugin_assets/js/jquery.equal-height-columns.js', array( 'jquery' ), false, true);
        }

        if (get_option( 'casawp_load_featherlight', 1 )) {
            wp_enqueue_script('featherlight', CASASYNC_PLUGIN_URL . 'plugin_assets/js/featherlight/release/featherlight.min.js', array( 'jquery' ), false, true);
            wp_register_style('featherlight', CASASYNC_PLUGIN_URL . 'plugin_assets/js/featherlight/release/featherlight.min.css' );
            wp_enqueue_style('featherlight' );
            wp_enqueue_script('featherlight-gallery', CASASYNC_PLUGIN_URL . 'plugin_assets/js/featherlight/release/featherlight.gallery.min.js', array( 'jquery', 'featherlight' ), false, true );
            wp_register_style('featherlight-gallery', CASASYNC_PLUGIN_URL . 'plugin_assets/js/featherlight/release/featherlight.gallery.min.css' );
            wp_enqueue_style('featherlight-gallery' );
        }


        if (get_option( 'casawp_load_chosen', 1 )) {
            wp_enqueue_script('chosen', CASASYNC_PLUGIN_URL . 'plugin_assets/js/chosen.jquery.min.js', array( 'jquery' ), false, true);
            wp_register_style('chosen-css', CASASYNC_PLUGIN_URL . 'plugin_assets/css/chosen.css' );
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
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields', 'page-attributes' ),
            'menu_icon'          => 'dashicons-admin-home',
            'show_in_nav_menus'  => true
        );
        register_post_type( 'casawp_property', $args );

        $used = array();
        if( function_exists('acf_add_local_field_group') ):
            add_action( 'add_meta_boxes_casawp_property', array($this,'casawp_property_custom_metaboxes'), 10, 2 );

            foreach ($this->numvalService->getTemplate() as $group => $groupsettings) {
                $fields = array();

                foreach ($groupsettings['items'] as $key => $settings) {
                    $used[] = $key;
                    $fields[] = array(
                        'key' => 'field_casawp_property_'.$key,
                        'label' => $this->numvalService->getItem($key)->getLabel(),
                        'name' => $key,
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
                    'menu_order' => 0,
                    'position' => 'normal',
                    'style' => 'default',
                    'label_placement' => 'left',
                    'instruction_placement' => 'label',
                    'hide_on_screen' => array (
                        0 => 'excerpt',
                        1 => 'discussion',
                        2 => 'comments',
                        3 => 'revisions',
                        5 => 'author',
                        6 => 'format',
                        10 => 'send-trackbacks',
                        11 => 'custom_fields'
                    ),
                ));
            }

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
                    'menu_order' => 0,
                    'position' => 'default',
                    'style' => 'default',
                    'label_placement' => 'left',
                    'instruction_placement' => 'label',
                    'hide_on_screen' => array (
                        0 => 'excerpt',
                        1 => 'discussion',
                        2 => 'comments',
                        3 => 'revisions',
                        5 => 'author',
                        6 => 'format',
                        10 => 'send-trackbacks',
                        11 => 'custom_fields'
                    ),
                ));
            }

        endif;

        



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


        if( function_exists('acf_add_local_field_group') ):
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
                'position' => 'side',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => array (
                    0 => 'excerpt',
                    1 => 'discussion',
                    2 => 'comments',
                    3 => 'revisions',
                    4 => 'slug',
                    5 => 'author',
                    6 => 'format',
                    7 => 'page_attributes',
                    8 => 'categories',
                    9 => 'tags',
                    10 => 'send-trackbacks',
                    11 => 'custom_fields'
                ),
            ));
        endif;


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
        foreach ($meta_keys as $meta_key) {
            if (!array_key_exists($meta_key, $this->numvalService->items)) {
                echo '<tr><td class="acf-label">'.$meta_key . '</td><td class="acf-input">' . implode(', ',get_post_custom_values($meta_key, $post->ID)) . '</td></tr>';
            }
        }        
        echo "</table>";
    }

    function add_meta_tags() {
        global $post;
        if ( is_singular('casawp_property') ) {
            echo '<meta property="og:url"          content="' . get_the_permalink() . '" />' . "\n";
            echo '<meta property="og:type"         content="article" />' . "\n";
            echo '<meta property="og:title"        content="' . get_the_title() . '" />' . "\n";
            echo '<meta property="og:description"  content="' . strip_tags($post->post_content) . '" />' . "\n";
            echo '<meta property="og:image"        content="' . wp_get_attachment_url( get_post_thumbnail_id() ) . '">' . "\n";
            echo '<meta property="og:locale"       content="' . get_locale() . '" />' . "\n";
        }
    }

    public function prepareOffer($post){
        $offer = $this->serviceManager->get('casawpOffer');
        $offer->setPost($post);
        return $offer;
    }
}

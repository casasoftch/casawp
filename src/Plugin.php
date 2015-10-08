<?php
namespace CasaSync;
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

        add_shortcode('casasync_contact', array($this,'contact_shortcode'));
        add_action('init', array($this, 'setPostTypes'));

        add_action('wp_enqueue_scripts', array($this, 'registerScriptsAndStyles'));
        add_action('wp_enqueue_scripts', array($this, 'setOptionJsVars'));
        add_filter("attachment_fields_to_edit", array($this, "casasync_image_attachment_fields_to_edit"), null, 2);
        add_filter("attachment_fields_to_save", array($this, "casasync_image_attachment_fields_to_save"), null, 2);
        if (!is_admin()) {
            add_action('pre_get_posts', array($this, 'casasync_queryfilter'));  
        }
        add_filter( 'template_include', array($this, 'include_template_function'), 1 );
        register_activation_hook(CASASYNC_PLUGIN_DIR, array($this, 'casasync_activation'));
        register_deactivation_hook(CASASYNC_PLUGIN_DIR, array($this, 'casasync_deactivation'));

        add_action('wp_head', array($this, 'add_meta_tags'));
    
        if ( function_exists( 'add_theme_support' ) ) {
            add_theme_support( 'post-thumbnails' );
            set_post_thumbnail_size( 150, 150 ); // default Post Thumbnail dimensions
        }
        if ( function_exists( 'add_image_size' ) ) {
            //add_image_size( 'category-thumb', 300, 9999 );
            $standard_thumbnail_width = '506';
            $standard_thumbnail_height = '360';
            $thumb_size_w    = get_option('casasync_archive_show_thumbnail_size_w', 506) != '' ? get_option('casasync_archive_show_thumbnail_size_w') : $standard_thumbnail_width;
            $thumb_size_h    = get_option('casasync_archive_show_thumbnail_size_h', 360) != '' ? get_option('casasync_archive_show_thumbnail_size_h') : $standard_thumbnail_height;
            $thumb_size_crop = get_option('casasync_archive_show_thumbnail_size_crop', 506) == false ? 'true' : 'false';
            add_image_size(
                'casasync-thumb',
                $thumb_size_w,
                $thumb_size_h,
                $thumb_size_crop
            );
        }

        add_action('plugins_loaded', array($this, 'setTranslation'));
            
        $this->bootstrap($configuration);

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
        $this->queryService = $this->serviceManager->get('CasasyncQuery');
        $this->categoryService = $this->serviceManager->get('CasasoftCategory');
        $this->numvalService = $this->serviceManager->get('CasasoftNumval');
        
    }

    public function casasync_queryfilter($query){
        $this->queryService->setQuery();
        $query = $this->queryService->applyToWpQuery($query);
        return $query;
    }
    
    
    public function setOptionJsVars(){
        $script_params = array(
           'google_maps'              => get_option('casasync_load_googlemaps', 0),
           'google_maps_zoomlevel'    => get_option('casasync_single_use_zoomlevel', 12),
           //'fancybox'                 => get_option('casasync_load_fancybox', 0),
           'featherlight'             => get_option('casasync_load_featherlight', 0),
           'chosen'                   => get_option('casasync_load_chosen', 0),
           'load_css'                 => get_option('casasync_load_css', 'bootstrapv3'),
           'load_bootstrap_js'        => get_option('casasync_load_bootstrap_scripts'),
           'thumbnails_ideal_width'   => get_option('casasync_single_thumbnail_ideal_width', 150),
        );
        wp_localize_script( 'casasync_script', 'casasyncOptionParams', $script_params );
    }


    function casasync_activation() {
        register_uninstall_hook(__FILE__, array($this, 'casasync_uninstall'));
    }

    function casasync_deactivation() {
        // actions to perform once on plugin deactivation go here
    }

    function casasync_uninstall(){
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
            return '<div class="casasync-pagination ' . (get_option('casasync_load_css', 'bootstrapv3') == 'bootstrapv2' ? 'pagination' : '') . '">' . $links . '</div>';
        }

        $total_pages = $wp_query->max_num_pages;
        if ($total_pages > 1) {
            $current_page = max(1, get_query_var('paged'));
            if($current_page) {
                //TODO: prev/next These dont work yet!
                $prev_page = '<li class="disabled"><a href="#">&laquo;</span></a></li>';
                $next_page = '<li class="disabled"><a href="#">&raquo;</a></li>';
                $i = 0;
                $return = '<ul class="casasync-pagination">';
                $return .= $prev_page;
                while ($i < $total_pages) {
                    $i++;
                    if ($current_page == $i) {
                        $return .= '<li><a href="#"><span>' . $i . '<span class="sr-only">(current)</span></span></a></li>';
                    } else {
                        $return .= '<li><a href="' . get_pagenum_link($i) . '">' . $i . '</a></li>';
                  }
                }
                $return .= $next_page;
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
                CASASYNC_PLUGIN_DIR . '/view',
                get_template_directory() . '/casasync'
            )
        ));
        $resolver->attach($stack);
        $model = new ViewModel($args);

        $stack = array(
            'bootstrap3',
            'bootstrap4'
        );

        $viewgroup = get_option('casasync_viewgroup', 'bootstrap3');

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
        $category_terms = get_terms('casasync_category', array(
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
        $salestype_terms = get_terms('casasync_salestype', array(
            'hide_empty'        => true, 
        ));
        foreach ($salestype_terms as $salestype_term) {
            switch ($salestype_term->slug) {
                case 'rent': $salestypes[$salestype_term->slug] = __('Rent', 'casasync'); break;
                case 'buy': $salestypes[$salestype_term->slug] = __('Buy', 'casasync'); break;
                default: $salestypes[$salestype_term->slug] = $salestype_term->slug; break;
            }
        }
        return $salestypes;
    }

    public function getLocations(){
        $localities = get_terms('casasync_location',array('hierarchical'      => true));
        return $localities;
    }

    public function renderArchiveFilter(){
        $this->getLocations();

        $form = new \Casasync\Form\FilterForm(
            $this->getCategories(),
            $this->getSalestypes(),
            $this->getLocations()
        );
        $form->bind($this->queryService);
        return $this->render('archive-filter', array('form' => $form));
    }

    public function include_template_function( $template_path ) {
        if ( get_post_type() == 'casasync_property' && is_single()) {
            if ($_GET && (isset($_GET['ajax']) || isset($_GET['json']))) {
                //$template_path = CASASYNC_PLUGIN_DIR . '/ajax/prevnext.php';
                header('Content-Type: application/json');
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casasync-single-json.php';
                if ( $theme_file = locate_template( array( 'casasync-single-json.php' ) ) ) {
                    $template_path = $theme_file;
                }
            } else {
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casasync-single.php';
                if ( $theme_file = locate_template( array( 'casasync-single.php' ) ) ) {
                    $template_path = $theme_file;
                }
            }

        }
        if (is_tax('casasync_salestype') || is_tax('casasync_availability') || is_tax('casasync_category') || is_tax('casasync_location') || is_tax('casasync_feature') || is_post_type_archive( 'casasync_property' )) {
            if ($_GET && (isset($_GET['casasync_map']) || isset($_GET['ajax']) || isset($_GET['json']) )) {
                //$template_path = CASASYNC_PLUGIN_DIR . '/ajax/properties.php';
                header('Content-Type: application/json');
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casasync-archive-json.php';
                if ( $theme_file = locate_template( array( 'casasync-archive-json.php' ) ) ) {
                    $template_path = $theme_file;
                }
            } else {
                $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casasync-archive.php';
                if ( $theme_file = locate_template(array('casasync-archive.php'))) {
                    $template_path = $theme_file;
                }
            }
        }
        return $template_path;
    }

    public function casasync_image_attachment_fields_to_edit($form_fields, $post) {
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

    public function casasync_image_attachment_fields_to_save($post, $attachment) {
        if( isset($attachment['origin']) ){
            update_post_meta($post['ID'], '_origin', $attachment['origin']);
        }
        return $post;
    }

    function registerScriptsAndStyles(){
        switch (get_option('casasync_load_css', 'bootstrapv3')) {
            case 'bootstrapv2':
                wp_register_style( 'casasync-css', CASASYNC_PLUGIN_URL . 'plugin_assets/css/casasync_template_bs2.css' );
                wp_enqueue_style( 'casasync-css' );
                break;
            case 'bootstrapv3':
                wp_register_style( 'casasync-css', CASASYNC_PLUGIN_URL . 'plugin_assets/css/casasync_template_bs3.css' );
                wp_enqueue_style( 'casasync-css' );
                break;
            case 'none':
            default:
                break;
        }

        if (get_option( 'casasync_load_bootstrap_scripts', 'none' )) {
            switch (get_option('casasync_load_css', 'bootstrapv3')) {
                case 'bootstrapv2':
                    wp_enqueue_script(
                        'casasync_bootstrap2',
                        CASASYNC_PLUGIN_URL . 'plugin_assets/js/bootstrap.min.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    break;
                case 'bootstrapv3':
                    wp_enqueue_script(
                        'casasync_bootstrap3_transition',
                        CASASYNC_PLUGIN_URL . 'plugin_assets/js/bootstrap3/transition.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    wp_enqueue_script(
                        'casasync_bootstrap3_tab',
                        CASASYNC_PLUGIN_URL . 'plugin_assets/js/bootstrap3/tab.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    wp_enqueue_script(
                        'casasync_bootstrap3_carousel',
                        CASASYNC_PLUGIN_URL . 'plugin_assets/js/bootstrap3/carousel.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    wp_enqueue_script(
                        'casasync_bootstrap3_tooltip',
                        CASASYNC_PLUGIN_URL . 'plugin_assets/js/bootstrap3/tooltip.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    wp_enqueue_script(
                        'casasync_bootstrap3_popover',
                        CASASYNC_PLUGIN_URL . 'plugin_assets/js/bootstrap3/popover.js',
                        array( 'jquery' ),
                        false,
                        true
                    );
                    break;
                case 'none':
                default:
                    # code...
                    break;
            }
            // Add Bootstrap v2 
        }

        wp_enqueue_script(
            'jstorage',
            CASASYNC_PLUGIN_URL . 'plugin_assets/js/jstorage.js',
            array( 'jquery' )
        );
        if(is_singular('casasync_property')) {
            wp_enqueue_script(
                'casasync_jquery_eqheight',
                CASASYNC_PLUGIN_URL . 'plugin_assets/js/jquery.equal-height-columns.js',
                array( 'jquery' ),
                false,
                true
            );
            /*if (get_option( 'casasync_load_fancybox', 1 )) {
                wp_enqueue_script(
                    'fancybox',
                    CASASYNC_PLUGIN_URL . 'plugin_assets/js/jquery.fancybox.pack.js',
                    array( 'jquery' ),
                    false,
                    true
                );
                wp_register_style( 'fancybox', CASASYNC_PLUGIN_URL . 'plugin_assets/css/jquery.fancybox.css' );
                wp_enqueue_style( 'fancybox' );
            }*/
        }

        if (get_option( 'casasync_load_featherlight', 1 )) {
            wp_enqueue_script(
                'featherlight',
                CASASYNC_PLUGIN_URL . 'plugin_assets/js/featherlight/release/featherlight.min.js',
                array( 'jquery' ),
                false,
                true
            );
            wp_register_style( 'featherlight', CASASYNC_PLUGIN_URL . 'plugin_assets/js/featherlight/release/featherlight.min.css' );
            wp_enqueue_style( 'featherlight' );

            wp_enqueue_script(
                'featherlight-gallery',
                CASASYNC_PLUGIN_URL . 'plugin_assets/js/featherlight/release/featherlight.gallery.min.js',
                array( 'jquery', 'featherlight' ),
                false,
                true
            );
            wp_register_style( 'featherlight-gallery', CASASYNC_PLUGIN_URL . 'plugin_assets/js/featherlight/release/featherlight.gallery.min.css' );
            wp_enqueue_style( 'featherlight-gallery' );
        }

        if (get_option( 'casasync_load_chosen', 1 )) {
            wp_enqueue_script(
                'chosen',
                CASASYNC_PLUGIN_URL . 'plugin_assets/js/chosen.jquery.min.js',
                array( 'jquery' ),
                false,
                true
            );
            wp_register_style( 'chosen-css', CASASYNC_PLUGIN_URL . 'plugin_assets/css/chosen.css' );
            wp_enqueue_style( 'chosen-css' );
        }
        if (get_option( 'casasync_load_googlemaps', 1 ) && is_singular('casasync_property')) {
            wp_enqueue_script(
                'google_maps_v3',
                'https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false',
                array(),
                false,
                true
            );
        }
        wp_enqueue_script(
            'casasync_script',
            CASASYNC_PLUGIN_URL . 'plugin_assets/js/script.js',
            array( 'jquery' ),
            false,
            true
        );

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
        load_plugin_textdomain('casasync', false, '/casasync/languages/' );
    }

    function setUploadDir($upload) {
        $upload['subdir'] = '/casasync' . $upload['subdir'];
        $upload['path']   = $upload['basedir'] . $upload['subdir'];
        $upload['url']    = $upload['baseurl'] . $upload['subdir'];
        return $upload;
    }

    public function setPostTypes(){

        /*----------  properties  ----------*/
        

        $labels = array(
            'name'               => __('Properties', 'casasync'),
            'singular_name'      => __('Property', 'casasync'),
            'add_new'            => __('Add New', 'casasync'),
            'add_new_item'       => __('Add New Property', 'casasync'),
            'edit_item'          => __('Edit Property', 'casasync'),
            'new_item'           => __('New Property', 'casasync'),
            'all_items'          => __('All Properties', 'casasync'),
            'view_item'          => __('View Property', 'casasync'),
            'search_items'       => __('Search Properties', 'casasync'),
            'not_found'          => __('No properties found', 'casasync'),
            'not_found_in_trash' => __('No properties found in Trash', 'casasync'),
            'menu_name'          => __('Properties', 'casasync')
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
        register_post_type( 'casasync_property', $args );

        $used = array();
        if( function_exists('acf_add_local_field_group') ):
            add_action( 'add_meta_boxes_casasync_property', array($this,'casasync_property_custom_metaboxes'), 10, 2 );

            foreach ($this->numvalService->getTemplate() as $group => $groupsettings) {
                $fields = array();

                foreach ($groupsettings['items'] as $key => $settings) {
                    $used[] = $key;
                    $fields[] = array(
                        'key' => 'field_casasync_property_'.$key,
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
                    'key' => 'group_casasync_property_numvals_'.$group,
                    'title' => $groupsettings['name'],
                    'fields' => $fields,
                    'location' => array (
                        array (
                            array (
                                'param' => 'post_type',
                                'operator' => '==',
                                'value' => 'casasync_property',
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
                        'key' => 'field_casasync_property_'.$numval->getKey(),
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
                    'key' => 'group_casasync_property_numvals_unsorted',
                    'title' => 'Ungeordnete werte',
                    'fields' => $fields,
                    'location' => array (
                        array (
                            array (
                                'param' => 'post_type',
                                'operator' => '==',
                                'value' => 'casasync_property',
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
            'name'               => __('Inquiries', 'casasync'),
            'singular_name'      => __('Inquiry', 'casasync'),
            'add_new'            => __('Add New', 'casasync'),
            'add_new_item'       => __('Add New inquiry', 'casasync'),
            'edit_item'          => __('Edit Inquiry', 'casasync'),
            'new_item'           => __('New Inquiry', 'casasync'),
            'all_items'          => __('All Inquiries', 'casasync'),
            'view_item'          => __('View Inquiry', 'casasync'),
            'search_items'       => __('Search Inquiries', 'casasync'),
            'not_found'          => __('No inquiries found', 'casasync'),
            'not_found_in_trash' => __('No inquiries found in Trash', 'casasync'),
            'menu_name'          => __('Inquiries', 'casasync')
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
        register_post_type( 'casasync_inquiry', $args );


        if( function_exists('acf_add_local_field_group') ):
            $fields = array();
            $form = new \Casasync\Form\ContactForm();
            foreach ($form->getElements() as $element) {
                if ($element->getName() != 'message') {
                    $fields[] = array(
                        'key' => 'field_casasync_inquiry_sender_'.$element->getName(),
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
                'key' => 'group_casasync_inquiry',
                'title' => 'Sender',
                'fields' => $fields,
                'location' => array (
                    array (
                        array (
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'casasync_inquiry',
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
            'name'              => __( 'Property categories', 'casasync'),
            'singular_name'     => __( 'Category', 'casasync'),
            'search_items'      => __( 'Search Categories', 'casasync'),
            'all_items'         => __( 'All Categories', 'casasync'),
            'parent_item'       => __( 'Parent Category', 'casasync'),
            'parent_item_colon' => __( 'Parent Category:', 'casasync'),
            'edit_item'         => __( 'Edit Category', 'casasync'),
            'update_item'       => __( 'Update Category', 'casasync'),
            'add_new_item'      => __( 'Add New Category', 'casasync'),
            'new_item_name'     => __( 'New Category Name', 'casasync'),
            'menu_name'         => __( 'Category', 'casasync')
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-kategorie' )
        );
        register_taxonomy( 'casasync_category', array( 'casasync_property' ), $args );

        /*----------  features  ----------*/
        
        $labels = array(
            'name'              => __( 'Property features', 'casasync'),
            'singular_name'     => __( 'Feature', 'casasync'),
            'search_items'      => __( 'Search Categories', 'casasync'),
            'all_items'         => __( 'All Categories', 'casasync'),
            'parent_item'       => __( 'Parent Feature', 'casasync'),
            'parent_item_colon' => __( 'Parent Feature:', 'casasync'),
            'edit_item'         => __( 'Edit Feature', 'casasync'),
            'update_item'       => __( 'Update Feature', 'casasync'),
            'add_new_item'      => __( 'Add New Feature', 'casasync'),
            'new_item_name'     => __( 'New Feature Name', 'casasync'),
            'menu_name'         => __( 'Feature', 'casasync')
        );
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-eigenschaft' )
        );
        register_taxonomy( 'casasync_feature', array( 'casasync_property' ), $args );


        /*----------  location  ----------*/
        
        $labels = array(
            'name'              => __( 'Property locations', 'casasync' ),
            'singular_name'     => __( 'Location', 'casasync' ),
            'search_items'      => __( 'Search Locations', 'casasync'),
            'all_items'         => __( 'All Locations', 'casasync'),
            'parent_item'       => __( 'Parent Location', 'casasync'),
            'parent_item_colon' => __( 'Parent Location:', 'casasync'),
            'edit_item'         => __( 'Edit Location', 'casasync'),
            'update_item'       => __( 'Update Location', 'casasync'),
            'add_new_item'      => __( 'Add New Location', 'casasync'),
            'new_item_name'     => __( 'New Location Name', 'casasync'),
            'menu_name'         => __( 'Location', 'casasync')
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-ortschaft' )
        );
        register_taxonomy( 'casasync_location', array( 'casasync_property' ), $args );


        /*----------  salestypes  ----------*/
        
        $labels = array(
            'name'                       => __( 'Property salestypes', 'casasync' ),
            'singular_name'              => __( 'Salestype', 'casasync' ),
            'search_items'               => __( 'Search Salestypes', 'casasync' ),
            'popular_items'              => __( 'Popular Salestypes', 'casasync' ),
            'all_items'                  => __( 'All Salestypes', 'casasync' ),
            'edit_item'                  => __( 'Edit Salestype', 'casasync' ),
            'update_item'                => __( 'Update Salestype', 'casasync' ),
            'add_new_item'               => __( 'Add New Salestype', 'casasync' ),
            'new_item_name'              => __( 'New Salestype Name', 'casasync' ),
            'separate_items_with_commas' => __( 'Separate salestypes with commas', 'casasync' ),
            'add_or_remove_items'        => __( 'Add or remove salestypes', 'casasync' ),
            'choose_from_most_used'      => __( 'Choose from the most used salestypes', 'casasync' ),
            'not_found'                  => __( 'No Salestypes found.', 'casasync' ),
            'menu_name'                  => __( 'Salestype', 'casasync' )
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-vermarktungsart' )
        );
        register_taxonomy( 'casasync_salestype', array( 'casasync_property' ), $args );


        /*----------  availability  ----------*/
        
        $labels = array(
            'name'                       => __( 'Property availability', 'casasync' ),
            'singular_name'              => __( 'Availability', 'casasync' ),
            'search_items'               => __( 'Search availabilities', 'casasync' ),
            'popular_items'              => __( 'Popular Availabilities', 'casasync' ),
            'all_items'                  => __( 'All Availabilities', 'casasync' ),
            'edit_item'                  => __( 'Edit Availability', 'casasync' ),
            'update_item'                => __( 'Update Availability', 'casasync' ),
            'add_new_item'               => __( 'Add New Availability', 'casasync' ),
            'new_item_name'              => __( 'New Availability Name', 'casasync' ),
            'separate_items_with_commas' => __( 'Separate availabilities with commas', 'casasync' ),
            'add_or_remove_items'        => __( 'Add or remove availabilities', 'casasync' ),
            'choose_from_most_used'      => __( 'Choose from the most used availabilities', 'casasync' ),
            'not_found'                  => __( 'No Availabilities found.', 'casasync' ),
            'menu_name'                  => __( 'Availability', 'casasync' )
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-verfuegbarkeit' )
        );
        register_taxonomy( 'casasync_availability', array( 'casasync_property' ), $args );



        /*----------  attachments  ----------*/        

        $labels = array(
          'name'              => __( 'Property Attachment Types', 'casasync' ),
            'singular_name'     => __( 'Attachment Type', 'casasync' ),
            'search_items'      => __( 'Search Attachment Types', 'casasync' ),
            'all_items'         => __( 'All Attachment Types', 'casasync' ),
            'parent_item'       => __( 'Parent Attachment Type', 'casasync' ),
            'parent_item_colon' => __( 'Parent Attachment Type:', 'casasync' ),
            'edit_item'         => __( 'Edit Attachment Type', 'casasync' ),
            'update_item'       => __( 'Update Attachment Type', 'casasync' ),
            'add_new_item'      => __( 'Add New Attachment Type', 'casasync' ),
            'new_item_name'     => __( 'New Attachment Type Name', 'casasync' ),
            'menu_name'         => __( 'Attachment Type', 'casasync' )
        );
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-anhangstyp' )
        );
        register_taxonomy( 'casasync_attachment_type', array(), $args );
        register_taxonomy_for_object_type('casasync_attachment_type', 'attachment');
        add_post_type_support('attachment', 'casasync_attachment_type');
        $id1 = wp_insert_term('Image', 'casasync_attachment_type', array('slug' => 'image'));
        $id2 = wp_insert_term('Plan', 'casasync_attachment_type', array('slug' => 'plan'));
        $id3 = wp_insert_term('Document', 'casasync_attachment_type', array('slug' => 'document'));
        $id3 = wp_insert_term('Sales Brochure', 'casasync_attachment_type', array('slug' => 'sales-brochure'));
    }

    function casasync_property_custom_metaboxes($post){
        add_meta_box('unsorted-metas', __('Additional Meta Fields'),  array($this, 'casasync_add_unsorted_metabox'), 'casasync_property', 'normal', 'high');
    }

    function casasync_add_unsorted_metabox($post) {
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
        if ( is_singular('casasync_property') ) {
            echo '<meta property="og:url"          content="' . get_the_permalink() . '" />' . "\n";
            echo '<meta property="og:type"         content="article" />' . "\n";
            echo '<meta property="og:title"        content="' . get_the_title() . '" />' . "\n";
            echo '<meta property="og:description"  content="' . strip_tags($post->post_content) . '" />' . "\n";
            echo '<meta property="og:image"        content="' . wp_get_attachment_url( get_post_thumbnail_id() ) . '">' . "\n";
            echo '<meta property="og:locale"       content="' . get_locale() . '" />' . "\n";
        }
    }

    public function prepareOffer($post){
        $offer = $this->serviceManager->get('CasasyncOffer');
        $offer->setPost($post);
        return $offer;
    }
}

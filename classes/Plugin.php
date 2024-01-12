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
    public $MVCtranslator;
    public $renderer;
    public $serviceManager;
    public $queryService;
    public $categoryService;
    public $utilityService;
    public $numvalService;
    public $featureService;
    public $formSettingService;
    public $formService;

    public function __construct($configuration){
        $this->configuration = $configuration;
        $this->conversion = new Conversion;
        $this->locale = substr(get_bloginfo('language'), 0, 2);

        // this is in case wpml is not loaded yet. We will take the first segment of the uri
        $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri_segments = explode('/', $uri_path);

        if (isset($uri_segments[1]) && $uri_segments[1] == 'en') {
            $this->locale = 'en';
        }
        if (isset($uri_segments[1]) && $uri_segments[1] == 'de') {
            $this->locale = 'de';
        }
        if (isset($uri_segments[1]) && $uri_segments[1] == 'fr') {
            $this->locale = 'fr';
        }
        if (isset($uri_segments[1]) && $uri_segments[1] == 'it') {
            $this->locale = 'it';
        }
        if (isset($uri_segments[1]) && $uri_segments[1] == 'ru') {
            $this->locale = 'ru';
        }
        if (isset($uri_segments[1]) && $uri_segments[1] == 'rm') {
            $this->locale = 'rm';
        }

        add_filter('icl_set_current_language', array($this, 'wpmlLanguageSwitchedTo'));

        //what is this?
        add_shortcode('casawp_contactform', array($this,'contactform_shortcode'));

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
            add_action('pre_get_posts', array($this, 'casawp_queryfilter'), 10);
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

        // auto add pages if missing for private users
        add_action('after_setup_theme', array($this, 'privateUserMakeSurePagesExist') );

        // remove adminbar for registered users
        add_action('after_setup_theme', array($this, 'privateUserHideAdminBarForRegisteredUsers') );

        // hook failed login
        add_action( 'wp_login_failed', array($this, 'privateUserRedirectToOrigin') );

        // pages for private user login area
        add_filter('the_content', array($this, 'privateUserPageRenders'));

        // logout page
        add_action ('template_redirect', array($this, 'privateUserLogOutOnLogoutPage'));

        // custom post thumbnail (for gateway cdn usage)
        add_filter('post_thumbnail_html', array($this, 'modifyPostThumbnailHtml'), 99, 5);

        add_filter('wp_get_attachment_image_src', array($this, 'modifyGetAttachmentImageSrc'), 99, 5);

        add_filter('wp_get_attachment_url', array($this, 'modifyGetAttachmentUrl'), 99, 5);
    }

    public function privateUserMakeSurePagesExist(){
      $loginpage = get_option('casawp_private_loginpage', false);
      if ( 'publish' != get_post_status ( $loginpage ) ) {
        $loginpage = false;
      }
      if (!$loginpage) {
        $page = get_page_by_title('Exklusiv Login');
        if (!$page) {
          $pageId = wp_insert_post(array(
            'post_title' => 'Exklusiv Login',
            'post_status' => 'publish',
            'post_author'   => 1,
            'post_content'  => '<p><strong>Melden Sie sich hier an um Zugang zu den exklusiven Objekten zu erhalten.</strong></p>',
            'post_type' => 'page'
          ));
          if (is_admin()) {
            echo '<div class="updated"><p><strong>' . __('Gennerated login page', 'casawp' ) . ' ' .$pageId . '</strong></p></div>';
          }
        } else {
          $pageId = $page->ID;
        }
        update_option('casawp_private_loginpage', $pageId);
      }

      $logoutpage = get_option('casawp_private_logoutpage', false);
      if ( 'publish' != get_post_status ( $logoutpage ) ) {
        $logoutpage = false;
      }
      if (!$logoutpage) {
        $page = get_page_by_title('Exklusiv Abmeldung');
        if (!$page) {
          $pageId = wp_insert_post(array(
            'post_title' => 'Exklusiv Logout',
            'post_status' => 'publish',
            'post_author'   => 1,
            'post_content'  => '<p>Sie haben sich erfolgreich abgemeldet.</p>',
            'post_type' => 'page'
          ));
          if (is_admin()) {
            echo '<div class="updated"><p><strong>' . __('Gennerated logout page', 'casawp' ) . ' ' .$pageId . '</strong></p></div>';
          }
        } else {
          $pageId = $page->ID;
        }
        update_option('casawp_private_logoutpage', $pageId);
      }

    }

    public function privateUserLogOutOnLogoutPage(){
      if (get_the_ID() == get_option('casawp_private_logoutpage', false) ) {
        if (is_user_logged_in()) {
          wp_logout();
        } else {
          wp_redirect(get_permalink(get_option('casawp_private_loginpage', false)));
        }
      }
    }

    public function origToGwSrc($orig, $targetSize){
        // gateway sizes
        // -72x72_C.png
        // -100x72_C.png
        // -240x180_C.png
        // -500x375_C.jpg
        // -1024x768_F.jpg
        // -1300x800_F.jpg
        $remoteSrc = $orig;
        $remoteSrc = str_replace('%3F', '?', $remoteSrc);
        $remoteSrc = str_replace('%3D', '=', $remoteSrc);
        $width = null;
        $height = null;
        //echo $remoteSrc;
        // echo "*" . $targetSize . "*";
        /*if (strpos($orig, '-p-xl.jpg')){
            $remoteSrc = str_replace('-p-xl.jpg', '.jpg?p=xl', $remoteSrc);
        }*/
        if ($targetSize === 'thumbnail') {
            $width = 240;
            $height = 180;
            //echo '.';
            if (strpos($orig, '-1300x800_F.jpg')){
                $remoteSrc = str_replace('-1300x800_F.jpg', '.jpg?p=sm', $remoteSrc);
            }
            if (strpos($remoteSrc, '?p=lg')){
                $remoteSrc = str_replace('?p=lg', '?p=sm', $remoteSrc);
            }
            if (strpos($remoteSrc, '?p=hd')){
                $remoteSrc = str_replace('?p=hd', '?p=sm', $remoteSrc);
            }
            if (strpos($remoteSrc, '?p=xl')){
                //echo 'xl_prop';
                $remoteSrc = str_replace('?p=xl', '?p=sm', $remoteSrc);
            }
            $remoteSrc = str_replace('/media-thumb/', '/media/', $remoteSrc);
        }
        if ($targetSize === 'casawp-thumb') {
            $width = 500;
            $height = 375;
            if (strpos($orig, '-1300x800_F.jpg')){
                $remoteSrc = str_replace('-1300x800_F.jpg', '.jpg?p=md', $remoteSrc);
            }
            if (strpos($remoteSrc, '?p=lg')){
                $remoteSrc = str_replace('?p=lg', '?p=md', $remoteSrc);
            }
            if (strpos($remoteSrc, '?p=hd')){
                $remoteSrc = str_replace('?p=hd', '?p=md', $remoteSrc);
            }
            if (strpos($remoteSrc, '?p=xl')){
                $remoteSrc = str_replace('?p=xl', '?p=md', $remoteSrc);
            }
            $remoteSrc = str_replace('/media-thumb/', '/media/', $remoteSrc);
        }
        if ($targetSize === 'large') {
            $width = 1024;
            $height = 768;
            if (strpos($orig, '-1300x800_F.jpg')){
                $remoteSrc = str_replace('-1300x800_F.jpg', '.jpg?p=lg', $remoteSrc);
            }
            // if (strpos($remoteSrc, '?p=lg')){
            //     $remoteSrc = str_replace('?p=lg', '?p=lg', $remoteSrc);
            // }
            if (strpos($remoteSrc, '?p=hd')){
                $remoteSrc = str_replace('?p=hd', '?p=lg', $remoteSrc);
            }
            if (strpos($remoteSrc, '?p=xl')){
                $remoteSrc = str_replace('?p=xl', '?p=lg', $remoteSrc);
            }
            $remoteSrc = str_replace('/media-thumb/', '/media/', $remoteSrc);
        }
        if ($targetSize === 'full') {
            $width = 1300;
            $height = 800;
            if (strpos($orig, '-1300x800_F.jpg')){
                $remoteSrc = str_replace('-1300x800_F.jpg', '.jpg?p=xl', $remoteSrc);
            }
            if (strpos($remoteSrc, '?p=lg')){
                $remoteSrc = str_replace('?p=hd', '?p=xl', $remoteSrc);
            }
            // if (strpos($remoteSrc, '?p=xl')){
            //     $remoteSrc = str_replace('?p=xl', '?p=xl', $remoteSrc);
            // }
            $remoteSrc = str_replace('/media-thumb/', '/media/', $remoteSrc);
        }
        $remoteSrc = str_replace('http://', 'https://', $remoteSrc);
        $remoteSrc = str_replace('casagateway.ch', 'cdn.casasoft.com', $remoteSrc);
        return [
            'src' => $remoteSrc,
            'width' => $width,
            'height' => $height,
        ];
    }

    public function modifyGetAttachmentUrl($url, $attachment_id) {
        if (get_option('casawp_use_casagateway_cdn', false)) {
            $orig = get_post_meta($attachment_id, '_origin', true);
            if ($orig && strpos($orig, 'casagateway.ch') && (strpos($orig, '/media-thumb/') || strpos($orig, '/media/')) ) {
                $remoteSrcArr = $this->origToGwSrc($orig, 'full');
                return $remoteSrcArr['src'];
            }
        }
        return $url;
    }

    public function modifyGetAttachmentImageSrc($image, $attachment_id, $size, $icon) {
        if (get_option('casawp_use_casagateway_cdn', false)) {
            $orig = get_post_meta($attachment_id, '_origin', true);
            if ($orig && strpos($orig, 'casagateway.ch') && (strpos($orig, '/media-thumb/') || strpos($orig, '/media/')) ) {
                $remoteSrcArr = $this->origToGwSrc($orig, $size);
                $image[0] = $remoteSrcArr['src'];
                $image[1] = $remoteSrcArr['width'];
                $image[2] = $remoteSrcArr['height'];
            }
        }
        return $image;
    }

    public function modifyPostThumbnailHtml($html, $post_id, $post_thumbnail_id, $size, $attr){
        if (get_option('casawp_use_casagateway_cdn', false)) {
            $post_thumbnail_id = get_post_thumbnail_id( $post_id );
            $orig = get_post_meta($post_thumbnail_id, '_origin', true);
            $attachment_id = $post_thumbnail_id;
            $remoteSrcArr = false;
            if ($orig && strpos($orig, 'casagateway.ch') && (strpos($orig, '/media-thumb/') || strpos($orig, '/media/')) ) {
                $remoteSrcArr = $this->origToGwSrc($orig, $size);
            } else {
                return $html;
            }
            $id = get_post_thumbnail_id(); // gets the id of the current post_thumbnail (in the loop)
            $alt = trim( strip_tags( get_post_meta( $post_thumbnail_id, '_wp_attachment_image_alt', true ) ) ); // get_the_title($id); // gets the post thumbnail title
            $size_class = $size;
            if ( is_array( $size_class ) ) {
                $size_class = join( 'x', $size_class );
            }
            $default_attr = array(
                'src'   => $remoteSrcArr['src'],
                'class' => "attachment-$size_class size-$size_class",
                'alt'   => trim( strip_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ),
            );
            $attr = wp_parse_args( $attr, $default_attr );
            $attachment = get_post($attachment_id);
            $attr = apply_filters( 'wp_get_attachment_image_attributes', $attr, $attachment, $size );
            $attr = array_map( 'esc_attr', $attr );
            $hwstring = image_hwstring($remoteSrcArr['width'], $remoteSrcArr['height']);
            $html = rtrim("<img $hwstring");
            foreach ( $attr as $name => $value ) {
                $html .= " $name=" . '"' . $value . '"';
            }
            $html .= ' />';
        }

        return $html;
      }

    public function privateUserHideAdminBarForRegisteredUsers(){
      if (!current_user_can('edit_posts')) {
        show_admin_bar(false);
      }
    }

    public function privateUserRedirectToOrigin( $username ) {
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';  // Check if HTTP_REFERER is set

        // if there's a valid referrer, and it's not the default log-in screen
        if ( !empty($referrer) && !strstr($referrer,'wp-login') && !strstr($referrer,'wp-admin') ) {
            wp_redirect( $referrer . '?login=failed' );  // let's append some information (login=failed) to the URL for the theme to use
            exit;
        }
    }


    public function privateUserPageRenders($content){
      switch (get_the_ID()) {
        case get_option('casawp_private_loginpage', false):
          if (is_user_logged_in()) {
            $content .= 'Sie sind bereits Angemeldet. ' . '<a href="/?p=' . get_option('casawp_private_logoutpage', false) . '">Jetzt Abmelden</a>';
          } else {
            $target = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'];
            if (isset($_GET['target'])) {
              $target = $_GET['target'];
            } else {
              $target = $target . urldecode($_SERVER['REQUEST_URI']);
            }
            $args = array(
              'echo'           => false,
              'remember'       => true,
              'redirect'       => $target,
              //'form_id'        => 'loginform',
              //'id_username'    => 'user_login',
              //'id_password'    => 'user_pass',
              //'id_remember'    => 'rememberme',
              //'id_submit'      => 'wp-submit',
              //'label_username' => __( 'Username' ),
              //'label_password' => __( 'Password' ),
              //'label_remember' => __( 'Remember Me' ),
              //'label_log_in'   => __( 'Log In' ),
              //'value_username' => '',
              //'value_remember' => false
            );
            if (isset($_GET['login']) && $_GET['login'] == 'failed') {
              $content .= '<div class="alert alert-danger" role="alert">Login fehlgeschlagen.</div>';
            }
            $content .= '<div class="casawp-private-login-form">'.wp_login_form( $args ).'</div>';
          }
          break;
      }


      return $content;
    }

    public function sanitizeContactFormPost($post){
        $data = array();
        foreach ($post as $key => $value) {
            switch ($key) {
                default:
                    $data[$key] = sanitize_text_field($value);
                    break;
            }
        }
        return $data;
    }

    public function contactform_shortcode($args = array()){
        $args = array_merge(array(
            'id' => false,
            'offer_id' => false,
            'project_id' => false,
            'direct_recipient_email' => false,
            'property_reference' => false
        ), ($args ? $args : array()));


        $offer = false;
        $project = false;
        if ($args['project_id'] || $args['offer_id'] ) {
            $post = get_post(($args['offer_id'] ? $args['offer_id'] : $args['project_id'] ));
            if ($post) {
                switch ($post->post_type) {
                    case 'casawp_property':
                        $offer = $this->prepareOffer($post);
                        break;
                    case 'casawp_project':
                        $project = $this->prepareProject($post);
                        break;
                }

            }

        }
        /*if (!$offer && !$project) {
            return '<p class="alert alert-danger">offer or project with id [' . $args['offer_id'] . '] not found</p>';
        }*/
        if ($offer && $offer->getAvailability() == 'reference') {
            return false;
        }

        $setting = false;
        if ($args['id']) {
            $setting = $this->formSettingService->getFormSetting($args['id']);
        }
        if (!$setting) {
            $setting = new \casawp\Form\DefaultFormSetting();
        }
        $formResult = $this->formService->buildAndValidateContactForm(($offer ? $offer : $project), $setting, $args['direct_recipient_email'], $args['property_reference']);
        if (is_string($formResult)) {
            return $formResult;
        }

        $result = $this->render($setting->getView(), array(
            'form' => $formResult['form'],
            'offer' => $offer,
            'project' => $project,
            'sent' => $formResult['sent'],
            'invalidCaptcha' => $formResult['invalidCaptcha']
        ));
        return $result;
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
        $this->featureService = $this->serviceManager->get('CasasoftFeature');
        $this->formSettingService = $this->serviceManager->get('casawpFormSettingService');
        $this->formService = $this->serviceManager->get('casawpFormService');

        add_action('after_setup_theme', function(){
            do_action('casawp_register_forms', $this->formSettingService);
        });

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

    public function isLoggedInToPrivateArea(){
        return $this->privateAuth(false);
    }



    public function privateAuth($checkpost = true){
        return is_user_logged_in();
    }

    public function privateRedirectToLogin(){
      $url = get_permalink(get_option('casawp_private_loginpage', false)) . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?' : '&') . 'target=' . urlencode($_SERVER['REQUEST_URI']);
      wp_redirect($url);
      echo '<script>window.location.replace("' . $url . '");</script>';
      die();
    }

    public function casawp_queryfilter($query){
        if ($query->is_main_query() && (is_tax('casawp_salestype') || is_tax('casawp_availability') || is_tax('casawp_category') || is_tax('casawp_location') || is_tax('casawp_feature') || is_post_type_archive( 'casawp_property' ))) {
            $this->queryService->setQuery();


            $query = $this->queryService->applyToWpQuery($query);

            $availabilities = array();
            $availabilities = $this->queryService->getQueryValue('availabilities');
            if ($availabilities) {
                if (in_array('private', $availabilities)) {
                    $loggedin = $this->privateAuth();
                    if (!$loggedin) {
                        $this->privateRedirectToLogin();
                    }
                }
            }


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
           'ajaxify_archive'          => get_option('casawp_ajaxify_archive', 0),
           'footer_script'              => get_option('casawp_load_scripts_in_footer', 0),
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

        if ($offer->getAvailability() == 'private') {
            $loggedin = $this->privateAuth();
            if (!$loggedin) {
                $this->privateRedirectToLogin();
            }
        }

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
        $project = $this->prepareProject($post);
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
      unset($query_args['ajax']);

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
      $links = str_replace("ajax", "ajaxno", $links);
      if ( $links ) {
        return '<div class="casawp-pagination pagination">' . $links . '</div>';
      }
    }

    public function renderContactFormElement($element, $form = null){
        return $this->render('contact-form-element', array('element' => $element, 'form' => $form));
    }

    public function render($view, $args){
        $renderer = $this->renderer;
        $resolver = new Resolver\AggregateResolver();
        $renderer->setResolver($resolver);


        $stack = new Resolver\TemplatePathStack(array(
            'script_paths' => array(
                CASASYNC_PLUGIN_DIR . '/theme-defaults/casawp',
                get_template_directory() . '/casawp',
                get_theme_file_path() . '/casawp',
                //get_stylesheet_directory() . '/casawp'
            )
        ));
        $resolver->attach($stack);

        //add plugin to view model
        $args['casawp'] = $this;


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
            $parts[] = number_format(round($value), 0, '', '&#39;') . '.–';
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
            case 'ru': $lang = 'ru'; break;
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

                $hidden = true;

                foreach ($c_trans as $key => $trans) {

                    if ($key == $category_term->slug) {
                        if (array_key_exists($lang, $trans)) {
                          $unknown_category->setLabel($trans[$lang]);
                        }

                        if (isset($c_trans[$category_term->slug]['show']) && $c_trans[$category_term->slug]['show']) {

                          $hidden = false;
                        }
                    }
                }
                if (!$hidden) {
                  $categories[] = $unknown_category;
                }
            }
        }


        //availabilities reduces categories
        $availabilities = $this->getQueriedArrayAvailability();
         $salestype = $this->getQueriedSingularSalestype();
        $availability = $this->getQueriedSingularAvailability();
        if ($availabilities) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_category'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_availability'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` IN ('" . implode('\', \'', $availabilities). "')";

            if ($salestype) {
               $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckSalestype ON referenceCheckSalestype.object_id = ". $wpdb->prefix . "posts.ID
               INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckSalestypeTermTax ON referenceCheckSalestype.term_taxonomy_id = referenceCheckSalestypeTermTax.term_taxonomy_id AND referenceCheckSalestypeTermTax.taxonomy = 'casawp_salestype'
               INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckSalestypeTerms ON referenceCheckSalestypeTerms.`term_id` = referenceCheckSalestypeTermTax.term_id AND referenceCheckSalestypeTerms.`slug` = '$salestype' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";

            $category_property_count = $wpdb->get_results( $query, ARRAY_A );

            $category_slug_array = array_map(function($item){return $item['slug'];}, $category_property_count);

            foreach ($categories as $key => $category) {
                if (!in_array($category->getKey(), $category_slug_array)) {
                    unset($categories[$key]);
                }
            }
        }

        //salestype reduces categories
       

        if ($salestype) {
        global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_category'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_salestype'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '" . $salestype . "'";

            if ($availability) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


            $category_property_count = $wpdb->get_results( $query, ARRAY_A );

            $category_slug_array = array_map(function($item){return $item['slug'];}, $category_property_count);

            foreach ($categories as $key => $category) {
                if (!in_array($category->getKey(), $category_slug_array)) {
                    unset($categories[$key]);
                }
            }
        }


        //location reduces categories
        $location = $this->getQueriedSingularLocation();
        if ($location) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_category'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_location'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$location'";

            if ($salestype) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckSalestype ON referenceCheckSalestype.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckSalestypeTermTax ON referenceCheckSalestype.term_taxonomy_id = referenceCheckSalestypeTermTax.term_taxonomy_id AND referenceCheckSalestypeTermTax.taxonomy = 'casawp_salestype'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckSalestypeTerms ON referenceCheckSalestypeTerms.`term_id` = referenceCheckSalestypeTermTax.term_id AND referenceCheckSalestypeTerms.`slug` = '$salestype' ";
            }

            if ($availability) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


            $category_property_count = $wpdb->get_results( $query, ARRAY_A );

            $category_slug_array = array_map(function($item){return $item['slug'];}, $category_property_count);

            foreach ($categories as $key => $category) {
                if (!in_array($category->getKey(), $category_slug_array)) {
                    unset($categories[$key]);
                }
            }
        }

        return $categories;
    }


    public function getRegions(){
        $regions = array();
        $region_terms = get_terms('casawp_region', array(
            'hide_empty'        => true,
        ));
        foreach ($region_terms as $region_term) {
            $regions[$region_term->slug] = $region_term->name;
        }

        //availability reduces regions
        $availability = $this->getQueriedSingularAvailability();
        if ($availability) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_region'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_availability'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$availability'
            GROUP BY ". $wpdb->prefix . "terms.term_id";

            $region_property_count = $wpdb->get_results( $query, ARRAY_A );

            $region_slug_array = array_map(function($item){return $item['slug'];}, $region_property_count);

            foreach ($regions as $key => $region) {
                if (!in_array($key, $region_slug_array)) {
                    unset($regions[$key]);
                }
            }
        }

        //salestype reduces regions
        $salestype = $this->getQueriedSingularSalestype();
        if ($salestype) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_region'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_salestype'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$salestype'
            GROUP BY ". $wpdb->prefix . "terms.term_id";

            $region_property_count = $wpdb->get_results( $query, ARRAY_A );

            $region_slug_array = array_map(function($item){return $item['slug'];}, $region_property_count);

            foreach ($regions as $key => $region) {
                if (!in_array($key, $region_slug_array)) {
                    unset($regions[$key]);
                }
            }
        }

        //categories reduces regions
        $categories = $this->getQueriedArrayCategory();
        if ($categories) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_region'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_category'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` IN ('" . implode('\', \'', $categories). "')";

            if ($salestype) {
               $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckSalestype ON referenceCheckSalestype.object_id = ". $wpdb->prefix . "posts.ID
               INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckSalestypeTermTax ON referenceCheckSalestype.term_taxonomy_id = referenceCheckSalestypeTermTax.term_taxonomy_id AND referenceCheckSalestypeTermTax.taxonomy = 'casawp_salestype'
               INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckSalestypeTerms ON referenceCheckSalestypeTerms.`term_id` = referenceCheckSalestypeTermTax.term_id AND referenceCheckSalestypeTerms.`slug` = '$salestype' ";
            }

            if ($availability) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";

            $region_property_count = $wpdb->get_results( $query, ARRAY_A );

            $region_slug_array = array_map(function($item){return $item['slug'];}, $region_property_count);

            foreach ($regions as $key => $region) {
                if (!in_array($key, $region_slug_array)) {
                    unset($regions[$key]);
                }
            }
        }

        //localities reduces regions
        $locality = $this->getQueriedSingularLocation();
        if ($locality) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_region'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_location'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$locality'";

            if ($salestype) {
               $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckSalestype ON referenceCheckSalestype.object_id = ". $wpdb->prefix . "posts.ID
               INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckSalestypeTermTax ON referenceCheckSalestype.term_taxonomy_id = referenceCheckSalestypeTermTax.term_taxonomy_id AND referenceCheckSalestypeTermTax.taxonomy = 'casawp_salestype'
               INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckSalestypeTerms ON referenceCheckSalestypeTerms.`term_id` = referenceCheckSalestypeTermTax.term_id AND referenceCheckSalestypeTerms.`slug` = '$salestype' ";
            }

            if ($availability) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";

            $region_property_count = $wpdb->get_results( $query, ARRAY_A );

            $region_slug_array = array_map(function($item){return $item['slug'];}, $region_property_count);

            foreach ($regions as $key => $region) {
                if (!in_array($key, $region_slug_array)) {
                    unset($regions[$key]);
                }
            }
        }

        return $regions;
    }

    public function getUtilities(){
      $utilities = array();
      $utility_terms = get_terms('casawp_utility', array(
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
          case 'ru': $lang = 'ru'; break;
          default: $lang = 'de'; break;
      }

      foreach ($utility_terms as $utility_term) {
          if ($this->utilityService->keyExists($utility_term->slug)) {
              $utilities[] = $this->utilityService->getItem($utility_term->slug);
          } else if ($this->utilityService->keyExists($utility_term->slug)) {
              //$utilities[] = $this->utilityService->getItem($utility_term->slug);
          } else {
              //needs to check for custom utilities


              $unknown_utility = new \CasasoftStandards\Service\Utility();
              $unknown_utility->setKey($utility_term->slug);

              if ($c_trans === null) {
                  $c_trans = maybe_unserialize(get_option('casawp_custom_utility_translations'));
                  if (!$c_trans) {
                      $c_trans = array();
                  }
              }

              $unknown_utility->setLabel($unknown_utility->getKey());

              $hidden = true;

              foreach ($c_trans as $key => $trans) {

                  if ($key == $utility_term->slug) {
                      if (array_key_exists($lang, $trans)) {
                        $unknown_utility->setLabel($trans[$lang]);
                      }

                      if (isset($c_trans[$utility_term->slug]['show']) && $c_trans[$utility_term->slug]['show']) {

                        $hidden = false;
                      }
                  }
              }
              if (!$hidden) {
                $utilities[] = $unknown_utility;
              }
          }
      }

    //availability reduces utilities
    $availability = $this->getQueriedSingularAvailability();
    if ($availability) {
        global $wpdb;
        /*filters the result with reference context in mind (WPML IGNORANT) */
        $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
        INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_utility'
        INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
        INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

        INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
        INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_availability'
        INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '" . $availability . "'
        GROUP BY ". $wpdb->prefix . "terms.term_id";


        $utility_property_count = $wpdb->get_results( $query, ARRAY_A );

        $utility_slug_array = array_map(function($item){return $item['slug'];}, $utility_property_count);

        foreach ($utilities as $key => $utility) {
            if (!in_array($utility->getKey(), $utility_slug_array)) {
                unset($utilities[$key]);
            }
        }
    }

    //salestype reduces utilities
    $salestype = $this->getQueriedSingularSalestype();

    if ($salestype) {
    global $wpdb;
        /*filters the result with reference context in mind (WPML IGNORANT) */
        $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
        INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_utility'
        INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
        INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

        INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
        INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_salestype'
        INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '" . $salestype . "'";

        if ($availability) {
            $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
        }

        $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


        $utility_property_count = $wpdb->get_results( $query, ARRAY_A );

        $utility_slug_array = array_map(function($item){return $item['slug'];}, $utility_property_count);

        foreach ($utilities as $key => $utility) {
            if (!in_array($utility->getKey(), $utility_slug_array)) {
                unset($utilities[$key]);
            }
        }
    }


      return $utilities;
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

    public function getQueriedArrayAvailability(){
        $query = $this->queryService->getQuery();
        return $query['availabilities'];
    }

    public function getQueriedSingularSalestype(){
        $query = $this->queryService->getQuery();
        if (isset($query['salestypes']) && count($query['salestypes']) == 1) {
            return $query['salestypes'][0];
        }
        return false;
    }

    public function getQueriedSingularCategory(){
        $query = $this->queryService->getQuery();
        if (isset($query['categories']) && count($query['categories']) == 1) {
            return $query['categories'][0];
        }
        return false;
    }

    public function getQueriedSingularUtility(){
        $query = $this->queryService->getQuery();
        if (isset($query['utilities']) && count($query['utilities']) == 1) {
            return $query['utilities'][0];
        }
        return false;
    }

    public function getQueriedArrayCategory(){
        $query = $this->queryService->getQuery();
        return $query['categories'];
    }

    public function getQueriedArrayRegion(){
        $query = $this->queryService->getQuery();
        return $query['regions'];
    }

    public function getQueriedArrayUtility(){
        $query = $this->queryService->getQuery();
        return $query['utilities'];
    }

    public function getQueriedSingularLocation(){
        $query = $this->queryService->getQuery();
        if (isset($query['locations']) && count($query['locations']) == 1) {
            return $query['locations'][0];
        }
        return false;
    }

    public function getLocations(){
        $localities = get_terms('casawp_location',array(
            'hierarchical'      => true
        ));
        
        $availability = $this->getQueriedSingularAvailability();
        //availability reduces locations
        /* $availability = $this->getQueriedSingularAvailability();
        if ($availability) {
            global $wpdb;
            $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_location'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_availability'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$availability'
            GROUP BY ". $wpdb->prefix . "terms.term_id";


            $location_property_count = $wpdb->get_results( $query, ARRAY_A );

            $location_id_array = array_map(function($item){return $item['term_id'];}, $location_property_count);

            foreach ($localities as $key => $locality) {
                if (!in_array($locality->term_id, $location_id_array)) {
                    unset($localities[$key]);
                }
            }
        } */

        $availabilities = $this->getQueriedArrayAvailability();

        $salestype = $this->getQueriedSingularSalestype();

        if ($availabilities) {
            global $wpdb;
            $query = "SELECT ". $wpdb->prefix . "terms.term_id, ". $wpdb->prefix . "terms.slug FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_location'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_availability'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` IN ('" . implode('\', \'', $availabilities). "')";

            if ($salestype) {
               $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckSalestype ON referenceCheckSalestype.object_id = ". $wpdb->prefix . "posts.ID
               INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckSalestypeTermTax ON referenceCheckSalestype.term_taxonomy_id = referenceCheckSalestypeTermTax.term_taxonomy_id AND referenceCheckSalestypeTermTax.taxonomy = 'casawp_salestype'
               INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckSalestypeTerms ON referenceCheckSalestypeTerms.`term_id` = referenceCheckSalestypeTermTax.term_id AND referenceCheckSalestypeTerms.`slug` = '$salestype' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";

            $location_property_count = $wpdb->get_results( $query, ARRAY_A );

            $location_id_array = array_map(function($item){return $item['term_id'];}, $location_property_count);

            foreach ($localities as $key => $locality) {
                if (!in_array($locality->term_id, $location_id_array)) {
                    unset($localities[$key]);
                }
            }
        }

        //salestype reduces locations
        if ($salestype) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_location'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_salestype'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$salestype'";

            if ($availability) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


            $location_property_count = $wpdb->get_results( $query, ARRAY_A );

            $location_id_array = array_map(function($item){return $item['term_id'];}, $location_property_count);

            foreach ($localities as $key => $locality) {
                if (!in_array($locality->term_id, $location_id_array)) {
                    unset($localities[$key]);
                }
            }
        }

        //categories reduces locations
        $categories = $this->getQueriedArrayCategory();
        if ($categories) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_location'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_category'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` IN ('" . implode('\', \'', $categories). "')";

            if ($salestype) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckSalestype ON referenceCheckSalestype.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckSalestypeTermTax ON referenceCheckSalestype.term_taxonomy_id = referenceCheckSalestypeTermTax.term_taxonomy_id AND referenceCheckSalestypeTermTax.taxonomy = 'casawp_salestype'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckSalestypeTerms ON referenceCheckSalestypeTerms.`term_id` = referenceCheckSalestypeTermTax.term_id AND referenceCheckSalestypeTerms.`slug` = '$salestype' ";
            }

            if ($availability) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


            $location_property_count = $wpdb->get_results( $query, ARRAY_A );

            $location_id_array = array_map(function($item){return $item['term_id'];}, $location_property_count);

            foreach ($localities as $key => $locality) {
                if (!in_array($locality->term_id, $location_id_array)) {
                    unset($localities[$key]);
                }
            }
        }

        //regions reduces locations
        $regions = $this->getQueriedArrayRegion();
        if ($regions) {
            global $wpdb;
            /*filters the result with reference context in mind (WPML IGNORANT) */
            $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
            INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_location'
            INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
            INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

            INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

            INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_region'
            INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` IN ('" . implode('\', \'', $regions). "')";

            if ($availability) {
                $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
                INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
                INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
            }

            $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


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


    public function getFeatures(){
      $features = get_terms('casawp_feature',array(
          'hierarchical'      => false,
          'hide_empty'        => true
      ));

      //availability reduces features
      $availability = $this->getQueriedSingularAvailability();
      if ($availability) {
          global $wpdb;
          /*filters the result with reference context in mind (WPML IGNORANT) */
          $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
          INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_feature'
          INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
          INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

          INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
          INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_availability'
          INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$availability'
          GROUP BY ". $wpdb->prefix . "terms.term_id";


          $feature_property_count = $wpdb->get_results( $query, ARRAY_A );

          $feature_id_array = array_map(function($item){return $item['term_id'];}, $feature_property_count);

          foreach ($features as $key => $locality) {
              if (!in_array($locality->term_id, $feature_id_array)) {
                  unset($features[$key]);
              }
          }
      }

      //salestype reduces features
      $salestype = $this->getQueriedSingularSalestype();
      if ($salestype) {
          global $wpdb;
          /*filters the result with reference context in mind (WPML IGNORANT) */
          $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
          INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_feature'
          INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
          INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

          INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID
          INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_salestype'
          INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` = '$salestype'";

          if ($availability) {
              $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
              INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
              INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
          }

          $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


          $feature_property_count = $wpdb->get_results( $query, ARRAY_A );

          $feature_id_array = array_map(function($item){return $item['term_id'];}, $feature_property_count);

          foreach ($features as $key => $locality) {
              if (!in_array($locality->term_id, $feature_id_array)) {
                  unset($features[$key]);
              }
          }
      }

      //categories reduces features
      $categories = $this->getQueriedArrayCategory();
      if ($categories) {
          global $wpdb;
          /*filters the result with reference context in mind (WPML IGNORANT) */
          $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
          INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_feature'
          INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
          INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

          INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

          INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_category'
          INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` IN ('" . implode('\', \'', $categories). "')";

          if ($salestype) {
              $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckSalestype ON referenceCheckSalestype.object_id = ". $wpdb->prefix . "posts.ID
              INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckSalestypeTermTax ON referenceCheckSalestype.term_taxonomy_id = referenceCheckSalestypeTermTax.term_taxonomy_id AND referenceCheckSalestypeTermTax.taxonomy = 'casawp_salestype'
              INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckSalestypeTerms ON referenceCheckSalestypeTerms.`term_id` = referenceCheckSalestypeTermTax.term_id AND referenceCheckSalestypeTerms.`slug` = '$salestype' ";
          }

          if ($availability) {
              $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
              INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
              INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
          }

          $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


          $feature_property_count = $wpdb->get_results( $query, ARRAY_A );

          $feature_id_array = array_map(function($item){return $item['term_id'];}, $feature_property_count);

          foreach ($features as $key => $locality) {
              if (!in_array($locality->term_id, $feature_id_array)) {
                  unset($features[$key]);
              }
          }
      }

      //regions reduces features
      $regions = $this->getQueriedArrayRegion();
      if ($regions) {
          global $wpdb;
          /*filters the result with reference context in mind (WPML IGNORANT) */
          $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
          INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_feature'
          INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
          INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

          INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

          INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_region'
          INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` IN ('" . implode('\', \'', $regions). "')";

          if ($availability) {
              $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
              INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
              INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
          }

          $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


          $feature_property_count = $wpdb->get_results( $query, ARRAY_A );

          $feature_id_array = array_map(function($item){return $item['term_id'];}, $feature_property_count);

          foreach ($features as $key => $locality) {
              if (!in_array($locality->term_id, $feature_id_array)) {
                  unset($features[$key]);
              }
          }
      }

      //utilities reduces features
      $utilities = $this->getQueriedArrayUtility();
      if ($utilities) {
          global $wpdb;
          /*filters the result with reference context in mind (WPML IGNORANT) */
          $query = "SELECT ". $wpdb->prefix . "terms.term_id FROM ". $wpdb->prefix . "terms
          INNER JOIN ". $wpdb->prefix . "term_taxonomy ON ". $wpdb->prefix . "term_taxonomy.term_id = ". $wpdb->prefix . "terms.term_id AND ". $wpdb->prefix . "term_taxonomy.taxonomy = 'casawp_feature'
          INNER JOIN ". $wpdb->prefix . "term_relationships ON ". $wpdb->prefix . "term_relationships.term_taxonomy_id = ". $wpdb->prefix . "term_taxonomy.term_taxonomy_id
          INNER JOIN ". $wpdb->prefix . "posts ON ". $wpdb->prefix . "term_relationships.object_id = ". $wpdb->prefix . "posts.ID AND ". $wpdb->prefix . "posts.post_status = 'publish'

          INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheck ON referenceCheck.object_id = ". $wpdb->prefix . "posts.ID

          INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckTermTax ON referenceCheck.term_taxonomy_id = referenceCheckTermTax.term_taxonomy_id AND referenceCheckTermTax.taxonomy = 'casawp_utility'
          INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckTerms ON referenceCheckTerms.`term_id` = referenceCheckTermTax.term_id AND referenceCheckTerms.`slug` IN ('" . implode('\', \'', $utilities). "')";

          if ($availability) {
              $query .= " INNER JOIN ". $wpdb->prefix . "term_relationships AS referenceCheckAvailability ON referenceCheckAvailability.object_id = ". $wpdb->prefix . "posts.ID
              INNER JOIN ". $wpdb->prefix . "term_taxonomy AS referenceCheckAvailabilityTermTax ON referenceCheckAvailability.term_taxonomy_id = referenceCheckAvailabilityTermTax.term_taxonomy_id AND referenceCheckAvailabilityTermTax.taxonomy = 'casawp_availability'
              INNER JOIN ". $wpdb->prefix . "terms AS referenceCheckAvailabilityTerms ON referenceCheckAvailabilityTerms.`term_id` = referenceCheckAvailabilityTermTax.term_id AND referenceCheckAvailabilityTerms.`slug` = '$availability' ";
          }

          $query .= " GROUP BY ". $wpdb->prefix . "terms.term_id";


          $feature_property_count = $wpdb->get_results( $query, ARRAY_A );

          $feature_id_array = array_map(function($item){return $item['term_id'];}, $feature_property_count);

          foreach ($features as $key => $locality) {
              if (!in_array($locality->term_id, $feature_id_array)) {
                  unset($features[$key]);
              }
          }
      }

      $featureObjects = array();
      foreach ($features as $tax_term) {
        if ($this->featureService->keyExists($tax_term->slug)) {
          $feature = $this->featureService->getItem($tax_term->slug);
        } else {
          $feature = new \CasasoftStandards\Service\Feature();
          $feature->setKey($tax_term->slug);
          $feature->setLabel('?'.$tax_term->slug);
        }

        $featureObjects[$tax_term->slug] = $feature;
      }
      return $featureObjects;
    }

    public function renderArchiveFilter(){
        $form = new \casawp\Form\FilterForm(
            array(
                'casawp_filter_categories_elementtype' => get_option('casawp_filter_categories_elementtype', false),
                'casawp_filter_utilities_elementtype' => get_option('casawp_filter_utilities_elementtype', false),
                'casawp_filter_salestypes_elementtype' => get_option('casawp_filter_salestypes_elementtype', false),
                'casawp_filter_locations_elementtype' => get_option('casawp_filter_locations_elementtype', false),
                'casawp_filter_countries_elementtype' => get_option('casawp_filter_countries_elementtype', false),
                'casawp_filter_rooms_from_elementtype' => get_option('casawp_filter_rooms_from_elementtype', false),
                'casawp_filter_rooms_to_elementtype' => get_option('casawp_filter_rooms_to_elementtype', false),
                'casawp_filter_areas_from_elementtype' => get_option('casawp_filter_areas_from_elementtype', false),
                'casawp_filter_areas_to_elementtype' => get_option('casawp_filter_areas_to_elementtype', false),
                'casawp_filter_price_from_elementtype' => get_option('casawp_filter_price_from_elementtype', false),
                'casawp_filter_price_to_elementtype' => get_option('casawp_filter_price_to_elementtype', false),
                'casawp_filter_regions_elementtype' => get_option('casawp_filter_regions_elementtype', false),
                'casawp_filter_features_elementtype' => get_option('casawp_filter_features_elementtype', false),
                'chosen_categories' => $this->queryService->getQueryValue('categories'),
                'chosen_salestypes' => $this->queryService->getQueryValue('salestypes'),
                'chosen_features' => $this->queryService->getQueryValue('features'),
                'chosen_locations' => $this->queryService->getQueryValue('locations'),
                'chosen_regions' => $this->queryService->getQueryValue('regions'),
                'chosen_countries' => $this->queryService->getQueryValue('countries'),
                'chosen_rooms_from' => $this->queryService->getQueryValue('rooms_from'),
                'chosen_rooms_to' => $this->queryService->getQueryValue('rooms_to'),
                'chosen_areas_from' => $this->queryService->getQueryValue('areas_from'),
                'chosen_areas_to' => $this->queryService->getQueryValue('areas_to')
            ),
            $this->getCategories(),
            $this->getUtilities(),
            $this->getSalestypes(),
            $this->getLocations(),
            $this->getAvailabilities(),
            $this->getRegions(),
            $this->getFeatures()
        );
        $form->setAttribute('action', get_post_type_archive_link( 'casawp_property' ));
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

        //property main view files
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
              case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-single.php'; break;
              default: $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-single.php'; break;
            }
            if ( $theme_file = locate_template( array( 'casawp-single.php' ) ) ) {
              $template_path = $theme_file;
            }
          }
        }
        //property archive view files
        if (is_post_type_archive( 'casawp_property' ) || is_tax('casawp_salestype') || is_tax('casawp_availability') || is_tax('casawp_category') || is_tax('casawp_location') || is_tax('casawp_feature')) {
          if ($_GET && (isset($_GET['casawp_map']) || isset($_GET['ajax']) || isset($_GET['json']) )) {
            //$template_path = CASASYNC_PLUGIN_DIR . '/ajax/properties.php';
            header('Content-Type: application/json');
            if ($_GET['ajax'] === 'archive-filter') {
              $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-archive-filter-ajax.php';
              if ( $theme_file = locate_template( array('casawp-archive-filter-ajax.php') ) ) {
                $template_path = $theme_file;
              }
            } else if ($_GET['ajax'] === 'archive') {
              $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-archive-ajax.php';

              if ( $theme_file = locate_template( array('casawp-archive-ajax.php') ) ) {
                $template_path = $theme_file;
              }
            } else {
              $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-archive-json.php';
              if ( $theme_file = locate_template( array( 'casawp-archive-json.php' ) ) ) {
                $template_path = $theme_file;
              }
            }
          } else {
            add_action('wp_enqueue_scripts', array($this, 'setArchiveParams'));

            $viewgroup = get_option('casawp_viewgroup', 'bootstrap3');
            switch ($viewgroup) {
                case 'bootstrap2': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp/bootstrap2/casawp-archive.php'; break;
                case 'bootstrap4': $template_path = CASASYNC_PLUGIN_DIR . 'theme-defaults/casawp-archive.php'; break;
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
        //project archive files
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
        if (get_option('casawp_load_css', false)) {
            wp_register_style( 'casawp_css', CASASYNC_PLUGIN_URL . 'plugin-assets/global/casawp.css' );
            wp_enqueue_style( 'casawp_css' );
        }
        $version = 4;
        if (get_option( 'casawp_load_scripts_in_footer', 1 )) {
            wp_enqueue_script('casawp', CASASYNC_PLUGIN_URL . 'plugin-assets/global/casawp.js', array( 'jquery', 'jstorage' ), $version, true);
        } else {
            wp_enqueue_script('casawp', CASASYNC_PLUGIN_URL . 'plugin-assets/global/casawp.js', array( 'jquery', 'jstorage' ), $version);
        }

        wp_enqueue_script('jstorage', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/jstorage.js', array( 'jquery' ), array(), true);


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



       /*  if(is_singular('casawp_property') && in_array(get_option('casawp_viewgroup', 'bootstrap3'), ['bootstrap2', 'bootstrap3'])) {
            wp_enqueue_script('casawp_jquery_eqheight', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/jquery.equal-height-columns.js', array( 'jquery' ), false, true);
        } */

        if (get_option( 'casawp_load_featherlight', 1 )) {
            wp_enqueue_script('featherlight', CASASYNC_PLUGIN_URL . 'plugin-assets/global/featherlight/release/featherlight.min.js', array( 'jquery' ), false, true);
            wp_register_style('featherlight', CASASYNC_PLUGIN_URL . 'plugin-assets/global/featherlight/release/featherlight.min.css' );
            wp_enqueue_style('featherlight' );
            wp_enqueue_script('featherlight-gallery', CASASYNC_PLUGIN_URL . 'plugin-assets/global/featherlight/release/featherlight.gallery.min.js', array( 'jquery', 'featherlight' ), false, true );
            wp_register_style('featherlight-gallery', CASASYNC_PLUGIN_URL . 'plugin-assets/global/featherlight/release/featherlight.gallery.min.css' );
            wp_enqueue_style('featherlight-gallery' );
        }


        if (get_option( 'casawp_load_chosen', 1 )) {
            wp_enqueue_script('chosen', CASASYNC_PLUGIN_URL . 'plugin-assets/global/js/chosen.cs.jquery.min.js', array( 'jquery' ), $version, true);
            wp_register_style('chosen-css', CASASYNC_PLUGIN_URL . 'plugin-assets/global/css/chosen.css' );
            wp_enqueue_style('chosen-css' );
        }

        if (get_option( 'casawp_load_googlemaps', 1 ) && is_singular('casawp_property')) {
            $google_api_key = get_option( 'casawp_google_apikey', '' );
            wp_enqueue_script('google_maps_v3', 'https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&key=' . $google_api_key, array(), false, true );
        }

        if (get_option( 'casawp_casadistance_active', false ) && is_singular('casawp_property')) {
            wp_enqueue_script('casadistance', CASASYNC_PLUGIN_URL . 'node_modules/casadistance/dist/main-bundle.js', array(), false, true );
            if (get_option( 'casawp_casadistance_basecss', false ) && is_singular('casawp_property')) {
               wp_register_style('casadistance-css', CASASYNC_PLUGIN_URL . 'node_modules/casadistance/dist/style.css' );
                wp_enqueue_style('casadistance-css' );
            }
        }


    }

    public function setTranslation(){
        $locale = get_locale();

        switch (substr($locale, 0, 2)) {
            case 'de': $locale = 'de_DE'; break;
            case 'en': $locale = 'en_US'; break;
            case 'it': $locale = 'it_CH'; break;
            case 'fr': $locale = 'fr_CH'; break;
            case 'ru': $locale = 'ru_RU'; break;
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
        $fields[] = array(
            'key' => 'last_import_hash',
            'label' => __('Last Import Hash', 'casawp'),
            'name' => 'last_import_hash',
            'type' => 'text',
            'required' => 0
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
            'name' => 'netPrice_timesegment',
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
        if (get_option('casawp_custom_slug')) {
            $casawpSlug = array( 'slug' => get_option('casawp_custom_slug') );
        } else {
            $casawpSlug = array( 'slug' => 'immobilien' );
        }
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => $casawpSlug,
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => true,
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
            'show_in_rest'       => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'projekte' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => true,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields'),
            'menu_icon'          => 'dashicons-admin-tools',
            'show_in_nav_menus'  => true
        );
        register_post_type( 'casawp_project', $args );




        /*----------  Inquiry  ----------*/
        // THIS HAS BEEN DISABLED DUE TO GDPR

        // $labels = array(
        //     'name'               => __('Inquiries', 'casawp'),
        //     'singular_name'      => __('Inquiry', 'casawp'),
        //     'add_new'            => __('Add New', 'casawp'),
        //     'add_new_item'       => __('Add New inquiry', 'casawp'),
        //     'edit_item'          => __('Edit Inquiry', 'casawp'),
        //     'new_item'           => __('New Inquiry', 'casawp'),
        //     'all_items'          => __('All Inquiries', 'casawp'),
        //     'view_item'          => __('View Inquiry', 'casawp'),
        //     'search_items'       => __('Search Inquiries', 'casawp'),
        //     'not_found'          => __('No inquiries found', 'casawp'),
        //     'not_found_in_trash' => __('No inquiries found in Trash', 'casawp'),
        //     'menu_name'          => __('Inquiries', 'casawp')
        // );
        // $args = array(
        //     'labels'             => $labels,
        //     'public'             => false,
        //     'publicly_queryable' => false,
        //     'show_ui'            => true,
        //     'show_in_menu'       => true,
        //     'query_var'          => true,
        //     'rewrite'            => array( 'slug' => 'anfragen' ),
        //     'capability_type'    => 'post',
        //     'has_archive'        => false,
        //     'hierarchical'       => false,
        //     'menu_position'      => null,
        //     'supports'           => array( 'title', 'editor', 'author','custom-fields', 'page-attributes' ),
        //     'menu_icon'          => 'dashicons-admin-comments',
        //     'show_in_nav_menus'  => true
        // );
        // register_post_type( 'casawp_inquiry', $args );



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
            'show_admin_column' => false,
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


        /*----------  custom region segments ----------*/

        $labels = array(
            'name'                       => __( 'Property regions', 'casawp' ),
            'singular_name'              => __( 'Region Segment', 'casawp' ),
            'search_items'               => __( 'Search Region Segments', 'casawp' ),
            'popular_items'              => __( 'Popular Region Segments', 'casawp' ),
            'all_items'                  => __( 'All Region Segments', 'casawp' ),
            'edit_item'                  => __( 'Edit Region Segment', 'casawp' ),
            'update_item'                => __( 'Update Region Segment', 'casawp' ),
            'add_new_item'               => __( 'Add New Region Segment', 'casawp' ),
            'new_item_name'              => __( 'New Region Segment Name', 'casawp' ),
            'separate_items_with_commas' => __( 'Separate regions with commas', 'casawp' ),
            'add_or_remove_items'        => __( 'Add or remove regions', 'casawp' ),
            'choose_from_most_used'      => __( 'Choose from the most used salestypes', 'casawp' ),
            'not_found'                  => __( 'No Region Segments found.', 'casawp' ),
            'menu_name'                  => __( 'Region Segment', 'casawp' )
        );
        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => false,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'immobilien-regions' )
        );
        register_taxonomy( 'casawp_region', array( 'casawp_property' ), $args );


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
            'netPrice_timesegment',
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

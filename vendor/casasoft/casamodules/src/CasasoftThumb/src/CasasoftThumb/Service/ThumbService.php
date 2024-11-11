<?php
namespace CasasoftThumb\Service;

use Laminas\Http\Request;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;


use Doctrine\ORM\Tools\Pagination\Paginator;
 
use Imagick;

//define('MAGICK_PATH', '/Applications/MAMP/bin/ImageMagick/ImageMagick-6.6.1/bin/convert');

class ThumbService implements FactoryInterface {

    protected $config = array(
        'public_dir' => '/public/',
        'public_subdir' => 'media-thumb/',
        //'data_dir' => '/data/',
        //'data_subdir' => 'media/',
        'retina' => true,
        'fallback_image' => 'fallback/path/image.png'
    );

    public function __construct(){

    }

    public function setConfig($config){
        $this->config = array_merge($this->config, $config);
    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function setFallbackImage($path){
        $this->config['fallback_image'] = $path;
    }

    //within media
    public function setRootDir($dir){
        $this->root_path = $dir;
    }

    public function setRetina($val){
        $this->config['retina'] = ($crop ? true : false);
    }

    /**
     * Calculate new image dimensions to new constraints
     *
     * @param Original X size in pixels
     * @param Original Y size in pixels
     * @return New X maximum size in pixels
     * @return New Y maximum size in pixels
     */
    public function scaleImage($x,$y,$cx,$cy) {
        //Set the default NEW values to be the old, in case it doesn't even need scaling
        list($nx,$ny)=array($x,$y);
        
        //If image is generally smaller, don't even bother
        if ($x>=$cx || $y>=$cx) {
                
            //Work out ratios
            if ($x>0) $rx=$cx/$x;
            if ($y>0) $ry=$cy/$y;
            
            //Use the lowest ratio, to ensure we don't go over the wanted image size
            if ($rx>$ry) {
                $r=$ry;
            } else {
                $r=$rx;
            }
            
            //Calculate the new size based on the chosen ratio
            $nx=intval($x*$r);
            $ny=intval($y*$r);
        }    
        
        //Return the results
        return array($nx,$ny);
    }

    public function isImageAnimated($image){
        $nb_image_frame = 0;
        foreach($image->deconstructImages() as $i) {
            $nb_image_frame++;
            if ($nb_image_frame > 1) {
                return true;
            }
        }
        return false;
    }
    

    //$path -> $partial_path . $filename;

    public function generateThumbFromBlob($path, $blob, $options = array(), $qHeight = 100){
         $defaults = array(
            'width'      => 100,
            'height'     => 100,
            'crop'       => true,
            'area'       => false,
            'withinfos'  => true,
            'target_dir'  => '',
            'target_filename' => false,
            'retina' => $this->config['retina']
        );
        if (!is_array($options) && $options) {
            $qWidth = $options;
            $options = array(
                'width' => $qWidth ,
                'height' => $qHeight
            );
        }
        $args = array_merge($defaults, $options);


        //make sure extension is not in filename
        if (!$args['target_filename']) {
            $args['target_filename'] = basename($path);
        }
        $info = pathinfo($args['target_filename']);
        $file_name = $info['filename'];
        $args['target_filename'] = $file_name;
        extract($args, EXTR_OVERWRITE, "wddx");

        $targetextention = ($width > 300 ? 'jpg' : 'png');

        $targetfilePublic = '/' . $this->config['public_subdir'] . basename(dirname(dirname($path))) . '/' . basename(dirname($path)) . '/' . $target_dir . $target_filename . '-' . $width . 'x' . $height . '_' . ($crop ? 'C' : 'F') . ($area ? 'A' : '') . ($retina ? '@2x' : '') . '.' . $targetextention;
        $targetfile = getcwd() . $this->config['public_dir'] . $targetfilePublic;
        
        $multiplier = ($retina ? 2 : 1);
        //$originalfile = getcwd() . '/' . $this->config['data_dir'] . $this->config['data_subdir'] . $path;
        $originalfile = $path;

        //test if thumbnail already exists
        if (is_file($targetfile) ) {
            if (!$withinfos) {
                return $targetfilePublic;
            } else {
                list($thewidth, $theheight, $thetype, $theattr) = getimagesize($targetfile);
                return array(
                    'src' => $targetfilePublic,
                    'height' => $theheight/$multiplier,
                    'width' => $thewidth/$multiplier,
                    'fitted_to' => ($theheight == $height ? 'height' : 'width')
                );
            }
        }


        //svgs need no conversion
        if (pathinfo($originalfile, PATHINFO_EXTENSION) == 'svg') {
            return false;
        }

        if (!class_exists('Imagick')) {
            return 'Imagick_missing';
        }
        try {
            //$image = new Imagick($originalfile. '[0]');
            $image = new Imagick();
            if (is_string($blob)) {
                $image->readImageBlob($blob);
            } else {
                $image->readImageFile($blob);
            }
        } catch (\ImagickException $e) {
            echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
            print_r($e->getMessage());
            echo "</textarea>";
            return $e->getMessage();
        }
        

        //calculate with and height for area restrictions
        if ($area) {
            $org_size = $image->getImageGeometry();
            $org_width = $org_size['width'];
            $org_height = $org_size['height'];
            $originalarea = ($org_width * $org_height);
            $max_height = ($height ? $height : 9999999999999999999);
            $max_width = ($width ? $width : 9999999999999999999);
            $scalefactor = sqrt($area/$originalarea);
            $widthForArea = $scalefactor * $org_width;
            $heightForArea = $scalefactor * $org_height;
            if ($widthForArea > $max_width) {
                $width = $max_width;
                $height = $max_height;
                $area = false;
            } elseif ($heightForArea > $max_height) {
                $width = $max_width;
                $height = $max_height;
                $area = false;
            } else {
                $width = round($widthForArea);
                $height = round($heightForArea);
            }   
        }


        if (class_exists('Imagick')) {
            try {
                if ($targetextention == 'png') {
                    $image->setImageFormat("png32");
                } else {
                    $image->setImageFormat("jpg");
                    $image->setImageCompressionQuality(80);
                }
                
                if ($image->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
                    $profiles = $image->getImageProfiles('*', false); 
                    // we're only interested if ICC profile(s) exist 
                    $has_icc_profile = (array_search('icc', $profiles) !== false); 
                    // if it doesnt have a CMYK ICC profile, we add one 
                    if ($has_icc_profile === false) { 
                    } 
                }
                $image->stripImage();
                if ($crop && !$area) {
                    $image->cropThumbnailImage( $width*$multiplier,$height*$multiplier);
                } elseif ($area) {
                    $image->thumbnailImage($width*$multiplier,$height*$multiplier);
                } else {
                    $image->scaleImage(
                        $width*$multiplier,
                        $height*$multiplier,
                        true
                    );
                }

                if (!file_exists(dirname($targetfile))) {
                    mkdir(dirname($targetfile), 0777, true);
                }
                $image->stripImage();
                $image->writeImage($targetfile);
                

                if (!$withinfos) {
                    return $targetfilePublic;
                } else {
                    list($thewidth, $theheight, $thetype, $theattr) = getimagesize($targetfile);
                    return array(
                        'src' =>  $targetfilePublic,
                        'height' => $theheight/$multiplier,
                        'width' => $thewidth/$multiplier,
                        'fitted_to' => ($theheight/$multiplier == $height ? 'height' : 'width')
                    );
                } 
            } catch (\ImagickException $e) {
                echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
                print_r($e->getMessage());
                echo "</textarea>";
                return $e->getMessage();
            }
        } else {
            return ('<pre style="width:'.$width.'px;height:'.$height.'px;overflow:scroll;">The thumbnail generator required ImageMagick/Imagick to be enabled!</pre>');
        }
    }


    public function generateThumb($path, $options = array(), $qHeight = 100){
        
        $defaults = array(
            'width'      => 100,
            'height'     => 100,
            'crop'       => true,
            'area'       => false,
            'withinfos'  => true,
            'target_dir'  => '',
            'target_filename' => false,
            'retina' => $this->config['retina']
        );
        if (!is_array($options) && $options) {
            $qWidth = $options;
            $options = array(
                'width' => $qWidth ,
                'height' => $qHeight
            );
        }
        $args = array_merge($defaults, $options);


        //make sure extension is not in filename
        if (!$args['target_filename']) {
            $args['target_filename'] = basename($path);
        }
        $info = pathinfo($args['target_filename']);
        $file_name = $info['filename'];
        $args['target_filename'] = $file_name;
        extract($args, EXTR_OVERWRITE, "wddx");

        $targetextention = ($width > 300 ? 'jpg' : 'png');

        /* $file_dirs_orig =  str_replace(PUBLIC_PATH, '', dirname($path));
        $file_dirs_arr = explode("/", $file_dirs_orig);
        unset($file_dirs_arr[0]);
        unset($file_dirs_arr[1]);
        $file_dirs = implode("/", $file_dirs_arr);
        */

        $targetfilePublic = '/' . $this->config['public_subdir'] . basename(dirname(dirname($path))) . '/' . basename(dirname($path)) . '/' . $target_dir . $target_filename . '-' . $width . 'x' . $height . '_' . ($crop ? 'C' : 'F') . ($area ? 'A' : '') . ($retina ? '@2x' : '') . '.' . $targetextention;
        $targetfile = getcwd() . $this->config['public_dir'] . $targetfilePublic;
        
        $multiplier = ($retina ? 2 : 1);
        //$originalfile = getcwd() . '/' . $this->config['data_dir'] . $this->config['data_subdir'] . $path;
        $originalfile = $path;

        //test if thumbnail already exists
        if (is_file($targetfile) ) {
            if (!$withinfos) {
                return $targetfilePublic;
            } else {
                list($thewidth, $theheight, $thetype, $theattr) = getimagesize($targetfile);
                return array(
                    'src' => $targetfilePublic,
                    'height' => $theheight/$multiplier,
                    'width' => $thewidth/$multiplier,
                    'fitted_to' => ($theheight == $height ? 'height' : 'width')
                );
            }
        }


        //svgs need no conversion
        if (pathinfo($originalfile, PATHINFO_EXTENSION) == 'svg') {
           /* if (!$withinfos) {
                return  (DISPLAY_DOMAIN ? 'http://'.DISPLAY_DOMAIN : '') . $originalfile;
            } else {

               // list($thewidth, $theheight, $thetype, $theattr) = getimagesize(PUBLIC_PATH . $originalfile);
                return array(
                    'src' => $originalfile,
                    'height' => $height,
                    'width' => $width,
                    'fitted_to' => 'height'
                );
            }*/
            return false;
        }


        

        //test if original exists
        if (!is_file($originalfile)) {
            return 'original_missing';
        }

        if (!class_exists('Imagick')) {
            return 'Imagick_missing';
        }
        try {
            $image = new Imagick($originalfile. '[0]');
        } catch (\ImagickException $e) {
            echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
            print_r($e->getMessage());
            echo "</textarea>";
            return 'Imagick_exception';
        }
        

        //calculate with and height for area restrictions
        if ($area) {
            $org_size = $image->getImageGeometry();
            $org_width = $org_size['width'];
            $org_height = $org_size['height'];
            $originalarea = ($org_width * $org_height);
            $max_height = ($height ? $height : 9999999999999999999);
            $max_width = ($width ? $width : 9999999999999999999);
            $scalefactor = sqrt($area/$originalarea);
            $widthForArea = $scalefactor * $org_width;
            $heightForArea = $scalefactor * $org_height;
            if ($widthForArea > $max_width) {
                $width = $max_width;
                $height = $max_height;
                $area = false;
            } elseif ($heightForArea > $max_height) {
                $width = $max_width;
                $height = $max_height;
                $area = false;
            } else {
                $width = round($widthForArea);
                $height = round($heightForArea);
            }   
        }


        if (class_exists('Imagick')) {
            if ($targetextention == 'png') {
                $image->setImageFormat("png32");
            } else {
                $image->setImageFormat("jpg");
                $image->setImageCompressionQuality(80);
            }
            
            if ($image->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
                $profiles = $image->getImageProfiles('*', false); 
                // we're only interested if ICC profile(s) exist 
                $has_icc_profile = (array_search('icc', $profiles) !== false); 
                // if it doesnt have a CMYK ICC profile, we add one 
                if ($has_icc_profile === false) { 
                    /* $icc_cmyk = file_get_contents(PUBLIC_PATH.'/icc/CMYK/USWebUncoated.icc'); 
                    $image->profileImage('icc', $icc_cmyk); 
                    unset($icc_cmyk); */
                } 
                // then we add an RGB profile 
                /*$icc_rgb = file_get_contents(PUBLIC_PATH.'/icc/RGB/sRGB_v4_ICC_preference.icc'); 
                $image->profileImage('icc', $icc_rgb); 
                unset($icc_rgb);*/ 
            }
            $image->stripImage();
            if ($crop && !$area) {
                $image->cropThumbnailImage( $width*$multiplier,$height*$multiplier);
            } elseif ($area) {
                $image->thumbnailImage($width*$multiplier,$height*$multiplier);
            } else {
                /*list($newX,$newY)=$this->scaleImage(
                    $image->getImageWidth(),
                    $image->getImageHeight(),
                    $width,
                    $height
                );*/
                //$image->thumbnailImage($newX*$multiplier,$newY*$multiplier);
                $image->scaleImage(
                    $width*$multiplier,
                    $height*$multiplier,
                    true
                );
            }

            if (!file_exists(dirname($targetfile))) {
                mkdir(dirname($targetfile), 0777, true);
            }
            $image->stripImage();
            $image->writeImage($targetfile);
            

            if (!$withinfos) {
                return $targetfilePublic;
            } else {
                list($thewidth, $theheight, $thetype, $theattr) = getimagesize($targetfile);
                return array(
                    'src' =>  $targetfilePublic,
                    'height' => $theheight/$multiplier,
                    'width' => $thewidth/$multiplier,
                    'fitted_to' => ($theheight/$multiplier == $height ? 'height' : 'width')
                );
            } 

        } else {
            return ('<pre style="width:'.$width.'px;height:'.$height.'px;overflow:scroll;">The thumbnail generator required ImageMagick/Imagick to be enabled!</pre>');
        }

       /*

        try {
            if ($crop) {
                $crop = '-thumbnail ' . $width*$multiplier . 'x' . $height*$multiplier . '^ -gravity Center -crop ' . $width*$multiplier . 'x' . $height*$multiplier . '+0+0';
            } else {
                $crop = ' -thumbnail ' . $width*$multiplier . 'x' . $height*$multiplier . ' ';
            }

            if ($targetextention == 'jpg') {
                $compression = ' -quality 80 -strip -background white -flatten -alpha off';
            } else {
                $compression = ' -strip ';
            } 
            $command = 'convert ' . PUBLIC_PATH . $originalfile . '[0]' . $compression . $crop . ' -colorspace rgb ' . PUBLIC_PATH . $targetfile;
            if(function_exists('exec')) {
                exec($command);
            } else {
                return ('<pre style="width:'.$width.'px;height:'.$height.'px;overflow:scroll;">Exec has been turned off!!! can\'t generate thumbnails</pre>');
            }
            if (!file_exists( PUBLIC_PATH . $targetfile )) {
                //$script = 'console.log("' . $command . '");';
                //$script = 'console.log("file missing after convert");';
                //$this->view->headScript()->appendScript($script, 'text/javascript');
            }
            if (!$withinfos) {
                return $targetfile;
            } else {
                list($thewidth, $theheight, $thetype, $theattr) = getimagesize(PUBLIC_PATH . $targetfile);
                return array(
                    'src' => $targetfile,
                    'height' => $theheight/$multiplier,
                    'width' => $thewidth/$multiplier,
                    'fitted_to' => ($theheight/$multiplier == $height ? 'height' : 'width')
                );
            }    

            

        } catch (Exception $e) {
            if (!$withinfos) {
                return $external_path . $sourcefile;
            } else {
                list($thewidth, $theheight, $thetype, $theattr) = getimagesize(PUBLIC_PATH . $targetfile);
                return array(
                    'src' =>  $originalfile,
                    'height' => 0,
                    'width' => 0,
                    'fitted_to' => false
                );
            }

        }*/

    }
}
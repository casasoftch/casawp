<?php
namespace CasasoftGeo\Service;

use Zend\Http\Client;
use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


use Doctrine\ORM\Tools\Pagination\Paginator;
 

//define('MAGICK_PATH', '/Applications/MAMP/bin/ImageMagick/ImageMagick-6.6.1/bin/convert');

class GeoService implements FactoryInterface {

    protected $config = array(
        'url' => 'http://domain.com',
    );

    public function __construct(){

    }

    public function setConfig($config){
        $this->config = array_merge($this->config, $config);
    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function get($action, $query){
        switch ($action) {
            case 'ch-city':
                return $this->findLocality('CH', $query);
                break;
            case 'ch-region':
                return $this->findRegion('CH', $query);
                break;
        }
        return false;
    }

    public function findLocality($country, $query){
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        foreach ($query as $key => $value) {
            $request->getQuery()->set($key, $value);
        }
        
        $request->getHeaders()->addHeaderLine('Accept', 'application/json');
        switch ($country) {
            case 'CH':
                $request->setUri($this->config['url'].'/ch-city');
                break;
            default:
                $request->setUri($this->config['url'].'/ch-city');
                break;
        }

        $client = new Client();
        $response = $client->send($request);
        $body = $response->getBody();

        $result = json_decode($body, true);
        if ($result) {
            return $result['_embedded']['ch_city'];
        }

        /*echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
        print_r($body);
        echo "</textarea>";
        die();*/

        return null;

    }

    public function findNodesFromPoint($lat, $lng){
      $request = new Request();
      $request->setMethod(Request::METHOD_GET);

      $request->getQuery()->set('lat', $lat);
      $request->getQuery()->set('lng', $lng);

      $request->getHeaders()->addHeaderLine('Accept', 'application/json');
      // switch ($country) {
      //     case 'CH':
      //         $request->setUri($this->config['url'].'/rpc/tree/search-with-point');
      //         break;
      //     default:
      //         $request->setUri($this->config['url'].'/rpc/tree/search-with-point');
      //         break;
      // }
      $request->setUri($this->config['url'].'/rpc/tree/search-with-point');

      $client = new Client();
      $response = $client->send($request);
      $body = $response->getBody();

      $result = json_decode($body, true);
      if ($result) {
          return $result['nodes'];
      }

      return null;
    }

    public function findLocalityFromTree($country, $query){
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        foreach ($query as $key => $value) {
          if ($key == 'postal_code') {
            $key = 's';
          }
          $request->getQuery()->set($key, $value);
        }


        $request->getHeaders()->addHeaderLine('Accept', 'application/json');
        switch ($country) {
            case 'CH':
                $request->setUri($this->config['url'].'/rpc/tree/search');
                break;
            default:
                $request->setUri($this->config['url'].'/rpc/tree/search');
                break;
        }

        $client = new Client();
        $response = $client->send($request);
        $body = $response->getBody();

        $result = json_decode($body, true);
        if ($result) {
            return $result['nodes'];
        }

        return null;
    }

    //gets siblings children and parents
    public function getTree($geoid){
        $country = 'CH';
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        // foreach ($query as $key => $value) {
        //   $request->getQuery()->set($key, $value);
        // }

        $request->getQuery()->set('id', $geoid);

        $request->getHeaders()->addHeaderLine('Accept', 'application/json');
        switch ($country) {
            case 'CH':
                $request->setUri($this->config['url'].'/rpc/tree');
                break;
            default:
                $request->setUri($this->config['url'].'/rpc/tree');
                break;
        }

        $client = new Client();
        $response = $client->send($request);
        $body = $response->getBody();

        $result = json_decode($body, true);
        if ($result) {
            return $result;
        }

        return null;

    }


    public function findRegion($country, $query){
        $request = new Request();
        $request->setMethod(Request::METHOD_GET);
        foreach ($query as $key => $value) {
            $request->getQuery()->set($key, $value);
        }
        
        $request->getHeaders()->addHeaderLine('Accept', 'application/json');
        switch ($country) {
            case 'CH':
                $request->setUri($this->config['url'].'/ch-region');
                break;
            default:
                $request->setUri($this->config['url'].'/ch-region');
                break;
        }

        $client = new Client();
        $response = $client->send($request);
        $body = $response->getBody();

        $result = json_decode($body, true);
        if ($result) {
            return $result['_embedded']['ch_region'];
        }

        /*echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
        print_r($body);
        echo "</textarea>";
        die();*/

        return null;

    }

}
<?php
namespace CasasoftHelpers\View\Helper;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class PaginateMeFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $viewHelperManager)
    {
        $serviceLocator = $viewHelperManager->getServiceLocator();

        $vhm = $serviceLocator->get('viewhelpermanager');
		$url = $vhm->get('url');

        $helper = new PaginateMe($url);
        return $helper;
    }
}
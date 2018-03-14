<?php
namespace CARLI\Search\Results;
use Zend\ServiceManager\ServiceLocatorInterface;

class PluginFactory extends \VuFind\ServiceManager\AbstractPluginFactory
{
    public function __construct()
    {
        $this->defaultNamespace = 'CARLI\Search';
        $this->classSuffix = '\Results';
    }

    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName, array $extraParams = []
    ) {
        $params = $serviceLocator->getServiceLocator()
            ->get('VuFind\SearchParamsPluginManager')->get($requestedName);
        $searchService = $serviceLocator->getServiceLocator()
            ->get('VuFind\Search');
        $recordLoader = $serviceLocator->getServiceLocator()
            ->get('VuFind\RecordLoader');
        $class = $this->getClassName($name, $requestedName);
        return new $class($params, $searchService, $recordLoader, ...$extraParams);
    }
}

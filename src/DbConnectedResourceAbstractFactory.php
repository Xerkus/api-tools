<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools;

use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\ServiceLocatorInterface;

class DbConnectedResourceAbstractFactory implements AbstractFactoryInterface
{
    public function canCreateServiceWithName(ServiceLocatorInterface $services, $name, $requestedName)
    {
        if (!$services->has('Config')) {
            return false;
        }

        $config = $services->get('Config');
        if (!isset($config['api-tools'])
            || !isset($config['api-tools']['db-connected'])
        ) {
            return false;
        }

        $config = $config['api-tools']['db-connected'];
        if (!isset($config[$requestedName])
            || !is_array($config[$requestedName])
            || !$this->isValidConfig($config[$requestedName], $requestedName, $services)
        ) {
            return false;
        }

        return true;
    }

    public function createServiceWithName(ServiceLocatorInterface $services, $name, $requestedName)
    {
        $config        = $services->get('Config');
        $config        = $config['api-tools']['db-connected'][$requestedName];
        $table         = $this->getTableGatewayFromConfig($config, $requestedName, $services);
        $identifier    = $this->getIdentifierFromConfig($config);
        $collection    = $this->getCollectionFromConfig($config, $requestedName);
        $resourceClass = $this->getResourceClassFromConfig($config, $requestedName);

        return new $resourceClass($table, $identifier, $collection);
    }

    /**
     * Tests if the configuration is valid
     *
     * If the configuration has a "table_service" key, and that service exists,
     * then the configuration is valid.
     *
     * Otherwise, it checks if the service $requestedName\Table exists.
     *
     * @param  array $config
     * @param  string $requestedName
     * @param  ServiceLocatorInterface $services
     * @return bool
     */
    protected function isValidConfig(array $config, $requestedName, ServiceLocatorInterface $services)
    {
        if (isset($config['table_service'])) {
            return $services->has($config['table_service']);
        }

        $tableGatewayService = $requestedName . '\Table';
        return $services->has($tableGatewayService);
    }

    protected function getTableGatewayFromConfig(array $config, $requestedName, ServiceLocatorInterface $services)
    {
        if (isset($config['table_service'])) {
            return $services->get($config['table_service']);
        }

        $tableGatewayService = $requestedName . '\Table';
        return $services->get($tableGatewayService);
    }

    protected function getIdentifierFromConfig(array $config)
    {
        if (isset($config['entity_identifier_name'])) {
            return $config['entity_identifier_name'];
        }

        // Deprecated; for pre-0.8.1 code only.
        if (isset($config['identifier_name'])) {
            return $config['identifier_name'];
        }

        if (isset($config['table_name'])) {
            return $config['table_name'] . '_id';
        }

        return 'id';
    }

    protected function getCollectionFromConfig(array $config, $requestedName)
    {
        $collection = isset($config['collection_class']) ? $config['collection_class'] : 'Laminas\Paginator\Paginator';
        if (!class_exists($collection)) {
            throw new ServiceNotCreatedException(sprintf(
                'Unable to create instance for service "%s"; collection class "%s" cannot be found',
                $requestedName,
                $collection
            ));
        }
        return $collection;
    }

    protected function getResourceClassFromConfig(array $config, $requestedName)
    {
        $defaultClass  = __NAMESPACE__ . '\DbConnectedResource';
        $resourceClass = isset($config['resource_class']) ? $config['resource_class'] : $defaultClass;
        if ($resourceClass !== $defaultClass
            && (
                !class_exists($resourceClass)
                || !is_subclass_of($resourceClass, $defaultClass)
            )
        ) {
            throw new ServiceNotCreatedException(sprintf(
                'Unable to create instance for service "%s"; resource class "%s" cannot be found or does not extend %s',
                $requestedName,
                $resourceClass,
                $defaultClass
            ));
        }
        return $resourceClass;
    }
}

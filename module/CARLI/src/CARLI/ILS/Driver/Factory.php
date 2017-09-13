<?php

namespace CARLI\ILS\Driver;

class Factory
{

    /**
     * Factory for VoyagerRestful driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return VoyagerRestful
     */
    public static function getVoyagerRestful(\Zend\ServiceManager\ServiceManager $sm)
    {
        $ils = $sm->getServiceLocator()->get('VuFind\ILSHoldSettings');
        $vr = new \CARLI\ILS\Driver\VoyagerRestful(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $ils->getHoldsMode(), $ils->getTitleHoldsMode()
        );
        $vr->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );
        return $vr;
    }

    /**
     * Factory for MultiBackend driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MultiBackend
     */
    public static function getMultiBackend(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new \CARLI\ILS\Driver\MultiBackend(
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\ILSAuthenticator'),
            $sm
        );
    }

    /**
     * Factory for NoILS driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return NoILS
     */
    public static function getNoILS(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new \CARLI\ILS\Driver\NoILS($sm->getServiceLocator()->get('VuFind\RecordLoader'));
    }


}


<?php

namespace CARLI\Search\Solr;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

class IgnoreSuppressedListener
{
    public function __construct()
    {
    }

    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
    }

    public function onSearchPre(EventInterface $event)
    {
        // We do not want to prevent record loading (id: searches)!
        // These id: searches do not have a 'type' parameter;
        // so do nothing if there is no 'type' parameter.
        if (!array_key_exists('type', $_REQUEST)) {
            return $event;
        }

        // Only ignore suppressed (searchable:false) records for regular searches.
        // Note: regular searches have a 'type' parameter
        $params = $event->getParam('params');
        $fq = $params->get('fq');
        if (!is_array($fq)) {
            $fq = [];
        }
        $fq[] = '-searchable:"false"';
        $params->set('fq', $fq);

        return $event;
    }
}

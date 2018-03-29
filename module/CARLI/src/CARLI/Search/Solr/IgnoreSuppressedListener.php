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
        // Only ignore suppressed (searchable:false) records for regular searches.
        // We do not want to prevent record loading, though (id: searches).
        if (!array_key_exists('lookfor', $_REQUEST) && !array_key_exists('lookfor0', $_REQUEST)) {
            return $event;
        }

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

<?php

namespace CARLI\ILS\Driver;

class NoILS extends \VuFind\ILS\Driver\NoILS
{

    // Need to expose this function for MultiBackend driver
    public function getSolrRecord($id)
    {
        return parent::getSolrRecord($id);
    }

    // Need to expose this function for MultiBackend driver
    public function getFormattedMarcDetails($recordDriver, $configSection)
    {
        return parent::getFormattedMarcDetails($recordDriver, $configSection);
    }
}


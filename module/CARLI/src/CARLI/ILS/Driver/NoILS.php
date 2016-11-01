<?php

namespace CARLI\ILS\Driver;

class NoILS extends \VuFind\ILS\Driver\NoILS
{

    public function getSolrRecord($id)
    {
        return parent::getSolrRecord($id);
    }

    public function getFormattedMarcDetails($recordDriver, $configSection)
    {
        return parent::getFormattedMarcDetails($recordDriver, $configSection);
    }
}


<?php

namespace CARLI\ILS\Driver;

use File_MARC;

class VoyagerRestful extends \VuFind\ILS\Driver\VoyagerRestful
{


     // We always want local "callslip" info from VXWS services too
     // Because we are relying on VXWS data from VoyagerRestful and *not*
     // the SQL data from Voyager driver.
     // One way to make this happen is to override this method and return false always.
     protected function isLocalInst($institution)
     {
         return false;
     }


    public function getMyTransactions($patron)
    {
        $cnt = 0;
        $filteredResults = array();

        $results = parent::getMyTransactions($patron);

        foreach ($results as $result) {

            // If it has a renewLimit property, then we know it's a local version of getMyTransactions; skip these.
            // We only want to display the VXWS data.
            if ($result['renewLimit']) continue;

            $filteredResults[] = $result;
        }
        return $filteredResults;
    }

    public function getMyILLRequests($patron)
    {
        return array_merge(
                       $this->getHoldsFromApi($patron, false),
                       $this->getRemoteCallSlips($patron, true)
        );
    }

    protected function getUBRequestDetails($id, $patron)
    {
        $results = parent::getUBRequestDetails($id, $patron);

        // Make certain that home library is included in list of Pickup libraries
        $libraries = $results['libraries'];
        $service = $this->translate(strtolower($this->config['Catalog']['service']));
        $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

        $filteredLibraries = array();
        $filteredLibraries[] = [
            'id' => $localUbId,
            'name' => $service,
            'isDefault' => true
        ];

        foreach ($libraries as $library) {
            if (
                $library['id'] == $localUbId
                ||
                $library['id'] == '1@RESDB20020723111103'
            ) {
                continue;
            }
            $library['isDefault'] = false;
            $filteredLibraries[] = $library;
        }

        $results['libraries'] = $filteredLibraries;

        return $results;
    }

    protected function isStorageRetrievalRequestAllowed($holdingsRow)
    {
        // Disallow Callslip requesting. We use ILL requesting for everything now.
        return false;
    }

    protected function processHoldingRow($sqlRow)
    {
        $row = parent::processHoldingRow($sqlRow);

        try {
            $marc = new File_MARC(
                str_replace(["\n", "\r"], '', $row['_fullRow']['RECORD_SEGMENT']),
                File_MARC::SOURCE_STRING
            );
            if ($record = $marc->next()) {
                $labels = $this->getMFHDData(
                    $record,
                    '856z'
                );
                if ($labels) {
                    if (! is_array($labels)) {
                        $labelsArray[] = $labels;
                    } else {
                        $labelsArray = $labels;
                    }
                    $row['eresource_label'] = $labelsArray;
                }
                $URLs = $this->getMFHDData(
                    $record,
                    '856u'
                );
                if ($URLs) {
                    if (! is_array($URLs)) {
                        $URLsArray[] = $URLs;
                    } else {
                        $URLsArray = $URLs;
                    }
                    $row['eresource'] = $URLsArray;
                }
            }
        } catch (\Exception $e) {
            trigger_error(
                'Poorly Formatted MFHD Record', E_USER_NOTICE
            );
        }

        return $row;
    }

}


<?php

namespace CARLI\ILS\Driver;

class MultiBackend extends \VuFind\ILS\Driver\MultiBackend
{
    /**
     * Get list of libraries that do not support Callslip
     *
     * @return string[] list of libraries that do not support Callslip
     */
    public function getNonCallslipLibraries()
    {
        return isset($this->config['CARLI']['NonCallslipLibraries'])
            ? $this->config['CARLI']['NonCallslipLibraries']
            : [];
    }

    // we need to override this because we really need to know the source of the bib ID!
    public function getILLPickupLibraries($id, $patron)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver
            && $this->methodSupported(
                $driver, 'getILLPickupLibraries', compact('id', 'patron')
            )
        ) {
            // Patron is not stripped so that the correct library can be determined
            return $driver->getILLPickupLibraries(
                //$this->stripIdPrefixes($id, $source, ['id']),
                $id,
                $patron
            );
        }
        throw new ILSException('No suitable backend driver found');
    }

    public function getHolding($id, array $patron = null)
    {
        // we need to disable Request/Available links for libraries that prohibit "Call slip"
        // Call slip = Patron Requesting Item belonging to their Home (logged-in) library account
        $disable_callslip = false;
        $patron_agency_id = '';
        if ($patron) {
            if (preg_match('/([^\.)]+)\.([0-9]+)/', $patron['id'], $matches)) {
                $patron_agency_id = $matches[1];
            }
         }
         $noCallslipLibraries = $this->getNonCallslipLibraries();
         if ($patron_agency_id != '') {
            foreach ($noCallslipLibraries as $noCallslipLibrary) {
                if ($patron_agency_id == $noCallslipLibrary) {
                    $disable_callslip = true;
                    break;
                }
            }
        }

        $results = array();
        $source = $this->getSource($id);
        if ($source) {
            $driver = $this->getDriver($source);
        }
        // If the ID is not prefixed with a library instances, e.g., UIUdb.123, then we can assume it's a deduped record (Universal Catalog)
        if (! $source || ! $driver) {
            // load our CARLIdb version of the NoILS driver, which provides access to the Solr Record Driver
            // which we'll use to parse 035a's in order to load the source records' holdings information
            $driver = $this->getDriver("DUMMY");
            $record = $driver->getSolrRecord($id);

            $ucHoldings = $driver->getFormattedMarcDetails($record, 'MarcHoldings');

            // randomize the holdings!!!
            shuffle($ucHoldings);

            //  0 => 
            //      array (
            //      'availability' => false,
            //      'use_unknown_message' => true,
            //      'status' => 'Library System Unavailable',
            //      'location' => '(ARUdb)25312',
            //      'reserve' => 'N',
            //      'callnumber' => '',
            //      'barcode' => 'Unavailable',
            //      'number' => '',
            //      'id' => '264895161',
            //      ),
            $localLibrary = getenv('VUFIND_LIBRARY_DB');
            $sourceRecords = array();
            $totalCount = 0;
            $localCount = 0;
            foreach ($ucHoldings as $ucHolding) {
                $sourceRecord = $ucHolding['location'];
                // Need to parse out the 035$a format, e.g., "(Agency) 123"
                if (preg_match('/\(([^\)]+)\)\s*([0-9]+)/', $sourceRecord, $matches)) {
                    $matched_agency = $matches[1];
                    $matched_id = $matches[2];
                    $sourceRecord = $matched_agency . '.' . $matched_id;
                    // move local library's results to the top
                    if (strcmp($matched_agency, $localLibrary) == 0) {
                        $sourceRecords[$totalCount] = $sourceRecords[$localCount];
                        $sourceRecords[$localCount] = $sourceRecord;
                        $localCount++;
                    } else {
                        $sourceRecords[$totalCount] = $sourceRecord;
                    }
                    $totalCount++;
                }
            }
            foreach ($sourceRecords as $sourceRecord) {
                $sourceDB = $this->getSource($sourceRecord);
                $driver = $this->getDriver($sourceDB);
                if ($driver) {
                    $holdings = $driver->getHolding(
                        $this->getLocalId($sourceRecord),
                        $this->stripIdPrefixes($patron, $sourceDB)
                    );
                    if (preg_match('/^(...)db/', $sourceDB, $matches)) {
                        $item_agency_id_lc3 = strtolower($matches[1]);
                        $item_agency_id = strtoupper($item_agency_id_lc3) . 'db';

                        for ($i=0 ; $i<count($holdings); $i++) {
                            $holdings[$i]['item_agency_id'] = $item_agency_id_lc3;
                            if ($disable_callslip && $item_agency_id == $patron_agency_id) {
                                $holdings[$i]['availability'] = false;
                            }
                        }
                    }
                    $result = $this->addIdPrefixes($holdings, $sourceDB);
                    $results[] = $result;
                }
            }
            // The results are currently nested one level too deep. Flatten them out:
            $flattenedResults = array();
            foreach ($results as $result) {
                foreach ($result as $individualRecord) {
                    $flattenedResults[] = $individualRecord;
                }
            }

            return $flattenedResults;
        } else {
           $result = parent::getHolding($id, $patron);
           $agency =  $this->getSource($result[0]['id']);
           if (preg_match('/^(...)db/', $agency, $matches)) {
               $item_agency_id_lc3 = strtolower($matches[1]);
               $item_agency_id = strtoupper($matches[1]) . 'db';

               // for non-logged-in patrons in a LOCAL catalog, 
               // *still* honor the noCallSlip setting for LOCAL items.
               if ($patron_agency_id == '' && !getenv('VUFIND_LIBRARY_IS_UC')) {
                   foreach ($noCallslipLibraries as $noCallslipLibrary) {
                       if ($item_agency_id == $noCallslipLibrary) {
                           $disable_callslip = true;
                           $item_agency_id = $patron_agency_id;
                           break;
                       }
                    }
               }

               for ($i=0 ; $i<count($result); $i++) {
                   $result[$i]['item_agency_id'] = $item_agency_id_lc3;
                   if ($disable_callslip && $item_agency_id == $patron_agency_id) {
                       $result[$i]['availability'] = false;
                   }
               }
           }
           return $result;
        }
    }
}

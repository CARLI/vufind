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

    // we need to override this because we really need to know the source of the bib ID!
    public function getILLPickupLocations($id, $pickupLib, $patron)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver
            && $this->methodSupported(
                $driver, 'getILLPickupLocations',
                compact('id', 'pickupLib', 'patron')
            )
        ) {
            // Patron is not stripped so that the correct library can be determined
            return $driver->getILLPickupLocations(
                //$this->stripIdPrefixes($id, $source, ['id']),
                $id,
                $pickupLib,
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
            // $ucHoldings:
            //
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

            // Keep only those with proper 035$a format, e.g., "(Agency) 123"
            $sourceRecords = array();
            $totalCount = 0;
            foreach ($ucHoldings as $ucHolding) {
                $sourceRecord = $ucHolding['location'];
                // Need to parse out the 035$a format, e.g., "(Agency) 123"
                if (preg_match('/\(([^\)]+)\)\s*([0-9]+)/', $sourceRecord, $matches)) {
                    $matched_agency = $matches[1];
                    $matched_id = $matches[2];
                    $sourceRecord = $matched_agency . '.' . $matched_id;
                    $sourceRecords[$totalCount++] = $sourceRecord;
                }
            }

            // put the prioritized records at the top
            usort($sourceRecords, 
                function($a, $b) { 
                    $aPriority = MultiBackend::getPriorityLevel($a);
                    $bPriority = MultiBackend::getPriorityLevel($b);
                    return ($bPriority - $aPriority);
                }
            );

            // need to randomize non-prioritized records
            // while leaving the priorized ones alone (at the top)
            $firstNonPrioritizedSourceInx = 0;
            $inx = 0;
            foreach ($sourceRecords as $sourceRecord) {
                if (MultiBackend::getPriorityLevel($sourceRecord) == 0) {
                    $firstNonPrioritizedSourceInx = $inx;
                    break;
                }
                $inx++;
            }

            if ($firstNonPrioritizedSourceInx > 0) {
                $prioritizedSourceRecords = array_slice($sourceRecords, 0, $firstNonPrioritizedSourceInx);
                $nonPrioritizedSourceRecords = array_slice($sourceRecords, $firstNonPrioritizedSourceInx);
                shuffle($nonPrioritizedSourceRecords);
                $sourceRecords = array_merge($prioritizedSourceRecords, $nonPrioritizedSourceRecords);
            } else {
                // randomize the holdings!!!
                shuffle($sourceRecords);
            }

            foreach ($sourceRecords as $sourceRecord) {
                $sourceDB = $this->getSource($sourceRecord);
                $driver = $this->getDriver($sourceDB);
                if ($driver) {
                  try {
                    $holdings = $driver->getHolding(
                        $this->getLocalId($sourceRecord),
                        $this->stripIdPrefixes($patron, $sourceDB)
                    );
                  } catch (ILSException $ilse) {
                     // skip holding records for any local bibs that fail (better to skip one than throw exception for all the rest of those that succeed!)
                     continue;
                  }
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

           // No holdings situation is possible
           if (! $result) { 
               return $result;
           }

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

    public static function getPriorityLevel($bibId) {
        $agency = explode('.', $bibId)[0];
        $localLibrary = getenv('VUFIND_LIBRARY_DB');
        $priorityLevel = array();
        $priorityLevel[$localLibrary] = 10;
        $priorityLevel["HAT"] = 9;
        $priorityLevel["EBL"] = 8;
        $priorityLevel["OTL"] = 7;
        $priorityLevel["OAC"] = 6;
        if (array_key_exists($agency, $priorityLevel)) {
            return $priorityLevel[$agency];
        } else {
            return 0;
        }
    }
}

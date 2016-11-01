<?php

namespace CARLI\ILS\Driver;

class MultiBackend extends \VuFind\ILS\Driver\MultiBackend
{

    public function getStatus($id)
    {
        $result =  parent::getStatus($id);
        return $result;
    }

    public function getHolding($id, array $patron = null)
    {
        $results = array();
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        // If the ID is not prefixed with a library instances, e.g., UIUdb.123, then we can assume it's a deduped record (Universal Catalog)
        if (! $driver) {
            // load our CARLIdb version of the NoILS driver, which provides access to the Solr Record Driver
            // which we'll use to parse 035a's in order to load the source records' holdings information
            $driver = $this->getDriver("CARLIdb");
            $record = $driver->getSolrRecord($id);

            $ucHoldings = $driver->getFormattedMarcDetails($record, 'MarcHoldings');
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
                if (preg_match('/\(([^\)]+db)\)\s*([0-9]+)/', $sourceRecord, $matches)) {
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
// TODO: remove this line. gotta find a better way to detect an invalid driver! bhc hasn't been defined yet
if (
    $sourceDB != "HRTdb" 
&&  $sourceDB != "JUDdb" 
&&  $sourceDB != "JWCdb" 
&&  $sourceDB != "KCCdb"
)
{ 
continue;
}
if ($sourceDB == "BHCdb") { continue; }
                $driver = $this->getDriver($sourceDB);
                if ($driver) {
                    $holdings = $driver->getHolding(
                        $this->getLocalId($sourceRecord),
                        $this->stripIdPrefixes($patron, $sourceDB)
                    );
                    if (preg_match('/^(...)db/', $sourceDB, $matches)) {
                        $holdings[0]['item_agency_id'] = strtolower($matches[1]);
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
               $result[0]['item_agency_id'] = strtolower($matches[1]);
           }
           return $result;
        }
    }
}

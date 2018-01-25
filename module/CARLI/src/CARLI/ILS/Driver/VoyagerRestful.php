<?php

namespace CARLI\ILS\Driver;

use File_MARC, Yajra\Pdo\Oci8, PDO, PDOException, VuFind\Exception\ILS as ILSException;
use VuFind\Config\Locator as ConfigLocator;

class VoyagerRestful extends \VuFind\ILS\Driver\VoyagerRestful
{

    protected function determineAvailability($statusArray)
    {
        // It's possible for a record to have multiple status codes.  We
        // need to loop through in search of the "Not Charged" (i.e. on
        // shelf) status, collecting any other statuses we find along the
        // way...
        $notCharged = false;
        $otherStatuses = [];
        foreach ($statusArray as $status) {
            switch ($status) {
            // Treat the following statuses as if they were 'Not Charged':
            case 'Discharged':
            case 'Cataloging Review':
            case 'Circulation Review':
            // The real 'Not Charged' status:
            case 'Not Charged':
                $notCharged = true;
                break;
            default:
                $otherStatuses[] = $status;
                break;
            }
        }

        // If we found other statuses or if we failed to find "Not Charged,"
        // the item is not available!
        $available = (count($otherStatuses) == 0 && $notCharged);

        return ['available' => $available, 'otherStatuses' => $otherStatuses];
    }


    protected function checkAccountBlocks($patronId)
    {
        $callingFunction = debug_backtrace()[1]['function'];
        // We do NOT want to prevent Request/Renew capability; let the VXWS API sort this out.
        // This is because we do NOT want a block from one institution preventing requests/renews from other institutions
        // But we DO want to show block reasons on Checked Out Items (getAccountBlocks) page...
        if ($callingFunction != "getAccountBlocks") {
            return false;
        }
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\nCALLING FUNCTION:" . var_export($callingFunction, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        $cacheId = "blocks|$patronId";
        $blockReasons = $this->getCachedData($cacheId);
        if (null === $blockReasons) {
            // Build Hierarchy
            $hierarchy = [
                'patron' =>  $patronId,
                'patronStatus' => 'blocks'
            ];

            // Add Required Params
            $params = [
                'patron_homedb' => $this->ws_patronHomeUbId,
                'view' => 'full'
            ];

            $blocks = $this->makeRequest($hierarchy, $params);
            if ($blocks
                && (string)$blocks->{'reply-text'} == 'ok'
                && isset($blocks->blocks->institution->borrowingBlock)
            ) {
                $blockReasons = $this->extractBlockReasons(
                    // CARLI edit: send all institutions' blocks, not just the first one!
                    //$blocks->blocks->institution->borrowingBlock
                    $blocks->blocks
                );
            } else {
                $blockReasons = [];
            }
            $this->putCachedData($cacheId, $blockReasons);
        }
//$debug = 'In CARLI::VoyagerRestful::checkAccountBlocks, blockReasons: ' . var_export($blockReasons, true);
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);
        return $blockReasons;
    }

    // CARLI edit: parse each inst blocks, instead of assuming just one inst
    protected function extractBlockReasons($borrowBlocks)
    {
        $whitelistConfig = isset($this->config['Patron']['ignoredBlockCodes'])
            ? $this->config['Patron']['ignoredBlockCodes'] : '';
        $whitelist = array_map('trim', explode(',', $whitelistConfig));

        $blockReason = [];
        // CARLI added foreach inst:
        foreach ($borrowBlocks->institution as $inst) {
            $instName = $inst->instName;
            // CARLI edit:
            //foreach ($borrowBlocks as $borrowBlock) {
            foreach ($inst->borrowingBlock as $borrowBlock) {
                if (!in_array((string)$borrowBlock->blockCode, $whitelist)) {
                    $blockReason[] = $instName . ': ' . (string)$borrowBlock->blockReason;
                }
            }
        }
        return $blockReason;
    }


    /**
     * UB Map
     *
     * @var array
     */
    protected $ubMap_libraryToKey = [];
    protected $ubMap_keyToLibrary = [];

    public function init()
    {
        parent::init();

        $this->loadUbMap('ubMap.ini');
    }

    /**
     * Loads UB information from configuration file.
     *
     * @param string $filename File to load from
     *
     * @throws ILSException
     * @return void
     */
    protected function loadUbMap($filename)
    {
        // Load ubMap file:
        $ubFile
            = ConfigLocator::getConfigPath($filename, 'config/vufind');
        if (!file_exists($ubFile)) {
            throw new ILSException(
                "Cannot load ub file: {$ubFile}."
            );
        }
        if (($handle = fopen($ubFile, "r")) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $this->ubMap_libraryToKey[$data[0]] = $data[1];
                $this->ubMap_keyToLibrary[$data[1]] = $data[0];
            }
            fclose($handle);
        }
    }

    protected function mapKeyToLibrary($key)
    {
       if (array_key_exists($key, $this->ubMap_keyToLibrary)) {
           return $this->ubMap_keyToLibrary[$key];
       }
       return null;
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
     // CARLI EDIT: The original function always uses the patron's web service. But this doesn't
     // seem to work in our UB environment. Instead, we need to call the item's library's RenewService 
     // for each item. For now, we will simply split each item up into individual calls; later, we might
     // want to consider grouping by item library and making a batch call per library.
    public function renewMyItems($renewDetails)
    {
//$debug = 'In CARLI::renewMyItems, renewDetails: ' . var_export($renewDetails, true);
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);
        $patron = $renewDetails['patron'];
        $finalResult = ['details' => []];

        // Get Account Blocks
        $finalResult['blocks'] = $this->checkAccountBlocks($patron['id']);

        if (!$finalResult['blocks']) {
            // Add Items and Attempt Renewal
            $itemIdentifiers = '';

          $wsAppOriginal = $this->ws_app; // probably not necessary, but just in case
          foreach ($renewDetails['details'] as $renewID) {
                list($dbKey, $loanId) = explode('|', $renewID);
//$debug = 'In CARLI::renewMyItems, dbKey: ' . $dbKey . ' ; loanId: ' . $loanId;
//$debug .= "\n" . '$this->ws_dbKey: ' . $this->ws_dbKey . ' $this->ws_app: ' . $this->ws_app;
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);
                if (!$dbKey) {
                    $dbKey = $this->ws_dbKey;
                } else {
                    // CARLI EDIT: set ws_app to item library's
                    $itemLibrary = $this->mapKeyToLibrary($dbKey);
                    if ($itemLibrary) {
                       $this->ws_app = $itemLibrary . '/vxws';
                    }
                }

                $loanId = $this->encodeXML($loanId);
                $dbKey = $this->encodeXML($dbKey);

                $itemIdentifiers = ''; // CARLI EDIT: we are calling the webservice each time per item
                $itemIdentifiers .= <<<EOT
      <myac:itemIdentifier>
       <myac:itemId>$loanId</myac:itemId>
       <myac:ubId>$dbKey</myac:ubId>
      </myac:itemIdentifier>
EOT;

            $patronId = $this->encodeXML($patron['id']);
            $lastname = $this->encodeXML($patron['lastname']);
            $barcode = $this->encodeXML($patron['cat_username']);
            $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

            // The RenewService has a weird prerequisite that
            // AuthenticatePatronService must be called first and JSESSIONID header
            // be preserved. There's no explanation why this is required, and a
            // quick check implies that RenewService works without it at least in
            // Voyager 8.1, but who knows if it fails with UB or something, so let's
            // try to play along with the rules.
            $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;

            $response = $this->makeRequest(
                ['AuthenticatePatronService' => false], [], 'POST', $xml
            );
            if ($response === false) {
                throw new ILSException('renew_error');
            }

            $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
   <ser:parameters/>
   <ser:definedParameters xsi:type="myac:myAccountServiceParametersType"
   xmlns:myac="http://www.endinfosys.com/Voyager/myAccount"
   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
$itemIdentifiers
   </ser:definedParameters>
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId"
  patronId="$patronId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;

            $response = $this->makeRequest(
                ['RenewService' => false], [], 'POST', $xml
            );
            if ($response === false) {
                throw new ILSException('renew_error');
            }

            // Process
            $myac_ns = 'http://www.endinfosys.com/Voyager/myAccount';
            $response->registerXPathNamespace(
                'ser', 'http://www.endinfosys.com/Voyager/serviceParameters'
            );
            $response->registerXPathNamespace('myac', $myac_ns);
            // The service doesn't actually return messages (in Voyager 8.1),
            // but maybe in the future...
            foreach ($response->xpath('//ser:message') as $message) {
                if ($message->attributes()->type == 'system'
                    || $message->attributes()->type == 'error'
                ) {
                    return false;
                }
            }
            foreach ($response->xpath('//myac:clusterChargedItems') as $cluster) {
                $cluster = $cluster->children($myac_ns);
                $dbKey = (string)$cluster->cluster->ubSiteId;
                foreach ($cluster->chargedItem as $chargedItem) {
                    $chargedItem = $chargedItem->children($myac_ns);
                    $renewStatus = $chargedItem->renewStatus;
                    if (!$renewStatus) {
                        continue;
                    }
                    $renewed = false;
                    foreach ($renewStatus->status as $status) {
                        if ((string)$status == 'Renewed') {
                            $renewed = true;
                        }
                    }

                    $result = [];
                    $result['item_id'] = (string)$chargedItem->itemId;
                    $result['sysMessage'] = (string)$renewStatus->status;

                    $dueDate = (string)$chargedItem->dueDate;
                    try {
                        $newDate = $this->dateFormat->convertToDisplayDate(
                            'Y-m-d H:i', $dueDate
                        );
                        $response['new_date'] = $newDate;
                    } catch (DateException $e) {
                        // If we can't parse out the date, use the raw string:
                        $response['new_date'] = $dueDate;
                    }
                    try {
                        $newTime = $this->dateFormat->convertToDisplayTime(
                            'Y-m-d H:i', $dueDate
                        );
                        $response['new_time'] = $newTime;
                    } catch (DateException $e) {
                        // If we can't parse out the time, just ignore it:
                        $response['new_time'] = false;
                    }
                    $result['new_date'] = $newDate;
                    $result['new_time'] = $newTime;
                    $result['success'] = $renewed;

                    $finalResult['details'][$result['item_id']] = $result;
                }
            }
          } // for each item
          $this->ws_app = $wsAppOriginal; // probably not necessary, but just in case
        }
        return $finalResult;
    }


    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $localUbId = $this->encodeXML($this->ws_patronHomeUbId);
        $lastname = $patron['cat_password'];
        $barcode = $patron['cat_username'];
        $patronId = $patron['id'];

        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId" patronId="$patronId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;


//file_put_contents("/usr/local/vufind/look.txt", "CARLI::VoyagerRestful::getMyFines() patron = " . var_export($patron, true) . "\n\nubid = " . $localUbId .  "\n\n", FILE_APPEND | LOCK_EX);

        $response = $this->makeRequest(
            ['MyAccountService' => false], [], 'POST', $xml
        );
        if ($response === false) {
            return null;
        }
//file_put_contents("/usr/local/vufind/look.txt", "CARLI::VoyagerRestful::getMyFines() response = " . var_export($response, true) . "\n\n", FILE_APPEND | LOCK_EX);

        // Process
        $myac_ns = 'http://www.endinfosys.com/Voyager/myAccount';
        $response->registerXPathNamespace(
            'ser', 'http://www.endinfosys.com/Voyager/serviceParameters'
        );
        $response->registerXPathNamespace('myac', $myac_ns);

        $fineList = [];

        foreach ($response->xpath('//myac:clusterFinesFees') as $cluster) {
            $cluster = $cluster->children($myac_ns);
            $dbKey = (string)$cluster->cluster->ubSiteId;
            $instName = (string)$cluster->cluster->clusterName;
            foreach ($cluster->fineFee as $fineFee) {
                $fineFee = $fineFee->children($myac_ns);

                $result = [];
                $result['id'] = (string)$fineFee->fineFeeId;
                $result['institution_id'] = '';
                $result['institution_name'] = $instName;
                $result['institution_dbkey'] = $dbKey;

                $date = '';
                try {
                    $date = $this->dateFormat->convertToDisplayDate(
                        'Y-m-d H:i', (string)$fineFee->date
                    );
                } catch (DateException $e) {
                    // If we can't parse out the date, use the raw string:
                    $date = (string)$fineFee->date;
                }
                $result['date'] = $date;

                $result['title'] = (string)$fineFee->title;
                $result['title2'] = (string)$fineFee->title;
                $result['type'] = (string)$fineFee->postingType;
                $result['amount'] = (string)$fineFee->amount->amount;
                $result['amount_total'] = (string)$fineFee->amountTotal->amount;
                $result['amount_balance'] = (string)$fineFee->balance->amount;
                $result['amount_balance_total'] = (string)$fineFee->balanceTotal->amount;
                $fineList[] = $result;
            }
        }

        return $fineList;
    }


    public function patronLogin($barcode, $lastname) 
    {
//$debug = 'In patronLogin... barcode: ' . $barcode . ' lastname: ' . $lastname;
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;

        // makeRequest2 includes a workaround libxml issue with Voyager API data
        $response = $this->makeRequest2(
            ['AuthenticatePatronService' => false], [], 'POST', $xml
        );
        if ($response === false) {
            return null;
        }

        // We must enforce local patrons only! I.e., do not allow someone from aff2 to authenticate for aff1
        if (!$response->serviceData || !$response->serviceData->isLocalPatron || $response->serviceData->isLocalPatron != "Y") {
            return null;
        }


        // There's got to be a better way. But I only need the one attribute for now.
        $patronIdentifier = $response->serviceData->patronIdentifier;
        $atts_object = $patronIdentifier->attributes();
        $atts_array = (array) $atts_object;
        $atts_array = $atts_array['@attributes'];
        $patronId = $atts_array['patronId'];

        //$firstName = $response->serviceData->fullName; // better info comes from PersonalInfoService
        //$lastName = $response->serviceData->lastName; // better info comes from PersonalInfoService

        // Acquire email address, firstname, lastname
        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:parameters>
  </ser:parameters>
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId"
  patronId="$patronId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;
        $response = $this->makeRequest2(
            ['PersonalInfoService' => false], [], 'POST', $xml
        );
        $emailAddress = $response->serviceData->emailAddress->address;
        $firstName = $response->serviceData->name->firstName;
        $lastName = $response->serviceData->name->lastName;

        return [
            'id' => utf8_encode($patronId),
            'firstname' => utf8_encode($firstName),
            'lastname' => utf8_encode($lastName),
            'cat_username' => utf8_encode($barcode),
            'cat_password' => utf8_encode($lastname),
            'email' => utf8_encode($emailAddress),
            'major' => null,
            'college' => null];

       //return parent::patronLogin($barcode, $lastname);
    }

    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        $oracleInstance = getenv('VUFIND_LIBRARY_DB');

        $items = [];

        $bindParams = [
            ':enddate' => date('d-m-Y', strtotime('now')),
            ':startdate' => date('d-m-Y', strtotime('-' . $daysOld . ' day'))
# hardcoded for now because devel server doesn't have any recent data!
#':startdate' => '01-03-2016',
#':enddate' => '01-01-2017'
        ];

        $sql = 
"select count(distinct bib_id) as count from      "  .
"(select distinct ${oracleInstance}.bib_master.bib_id, ${oracleInstance}.item.create_date as cdate      "  .
"from      ${oracleInstance}.bib_master,     "  .
"         ${oracleInstance}.bib_text,     "  .
"         ${oracleInstance}.bib_mfhd,     "  .
"         ${oracleInstance}.mfhd_item,     "  .
"         ${oracleInstance}.mfhd_master,     "  .
"         ${oracleInstance}.item     "  .
"where     ${oracleInstance}.bib_master.bib_id=${oracleInstance}.bib_text.bib_id and     "  .
"         ${oracleInstance}.bib_text.bib_id=${oracleInstance}.bib_mfhd.bib_id and     "  .
"         ${oracleInstance}.bib_mfhd.mfhd_id=${oracleInstance}.mfhd_master.mfhd_id and     "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=${oracleInstance}.mfhd_item.mfhd_id and     "  .
"         ${oracleInstance}.mfhd_item.item_id=${oracleInstance}.item.item_id and     "  .
"         ${oracleInstance}.mfhd_master.suppress_in_opac not in ('Y') and     "  .
"         ${oracleInstance}.bib_master.suppress_in_opac not in ('Y') and     "  .
"         ${oracleInstance}.item.on_reserve not in ('Y') and     "  .
"         substr(${oracleInstance}.bib_text.bib_format,-1,1) in ('a','c','m') and     "  .
"         ${oracleInstance}.item.create_date between to_date(:startdate, 'dd-mm-yyyy') and     "  .
"         to_date(:enddate, 'dd-mm-yyyy') and     "  .
"         ((${oracleInstance}.mfhd_master.create_date between     "  .
"            to_date(:startdate, 'dd-mm-yyyy') and     "  .
"            to_date(:enddate, 'dd-mm-yyyy'))  or     "  .
"          (${oracleInstance}.mfhd_master.update_date between     "  .
"            to_date(:startdate, 'dd-mm-yyyy') and     "  .
"            to_date(:enddate, 'dd-mm-yyyy')))     "  .
"UNION     "  .
"select distinct ${oracleInstance}.bib_master.bib_id, ${oracleInstance}.mfhd_master.create_date as cdate     "  .
"from      ${oracleInstance}.bib_master,     "  .
"         ${oracleInstance}.bib_mfhd,     "  .
"         ${oracleInstance}.mfhd_item,     "  .
"         ${oracleInstance}.mfhd_master,     "  .
"         (select record_id, link     "  .
"          from ${oracleInstance}.elink_index     "  .
"          where record_type='M') elink     "  .
"where     ${oracleInstance}.bib_master.bib_id=${oracleInstance}.bib_mfhd.bib_id and     "  .
"         ${oracleInstance}.bib_mfhd.mfhd_id=${oracleInstance}.mfhd_master.mfhd_id and     "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=elink.record_id and     "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=${oracleInstance}.mfhd_item.mfhd_id(+) and     "  .
"         ${oracleInstance}.mfhd_item.item_id is null and     "  .
"         ${oracleInstance}.mfhd_master.suppress_in_opac not in ('Y') and     "  .
"         ${oracleInstance}.bib_master.suppress_in_opac not in ('Y') and     "  .
"         ${oracleInstance}.mfhd_master.create_date between to_date(:startdate, 'dd-mm-yyyy') and     "  .
"         to_date(:enddate, 'dd-mm-yyyy') and     "  .
"         elink.link is not null)     "  
;

        try {
            $sqlStmt = $this->executeSQL($sql, $bindParams);
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $items['count'] = $row['COUNT'];
//file_put_contents("/usr/local/vufind/look.txt", "new items count:\n" . var_export($count, true) . "\n\nbindParmams:\n" . var_export($bindParams, true) . "\n\nsql:\n" . var_export($sql, true) . "\n\n", FILE_APPEND | LOCK_EX);
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        $page = ($page) ? $page : 1;
        $limit = ($limit) ? $limit : 20;
        $bindParams[':startRow'] = (($page - 1) * $limit) + 1;
        $bindParams[':endRow'] = ($page * $limit);
        $sql = 
"select * from                      "  .
"(select a.*, rownum rnum from                      "  .
"(select bib_id from                      "  .
"(select distinct ${oracleInstance}.bib_master.bib_id, ${oracleInstance}.item.create_date as cdate                      "  .
"from      ${oracleInstance}.bib_master,                      "  .
"         ${oracleInstance}.bib_text,                      "  .
"         ${oracleInstance}.bib_mfhd,                      "  .
"         ${oracleInstance}.mfhd_item,                      "  .
"         ${oracleInstance}.mfhd_master,                      "  .
"         ${oracleInstance}.item                      "  .
"where     ${oracleInstance}.bib_master.bib_id=${oracleInstance}.bib_text.bib_id and                      "  .
"         ${oracleInstance}.bib_text.bib_id=${oracleInstance}.bib_mfhd.bib_id and                      "  .
"         ${oracleInstance}.bib_mfhd.mfhd_id=${oracleInstance}.mfhd_master.mfhd_id and                      "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=${oracleInstance}.mfhd_item.mfhd_id and                      "  .
"         ${oracleInstance}.mfhd_item.item_id=${oracleInstance}.item.item_id and                      "  .
"         ${oracleInstance}.mfhd_master.suppress_in_opac not in ('Y') and                      "  .
"         ${oracleInstance}.bib_master.suppress_in_opac not in ('Y') and                      "  .
"         ${oracleInstance}.item.on_reserve not in ('Y') and                      "  .
"         substr(${oracleInstance}.bib_text.bib_format,-1,1) in ('a','c','m') and                      "  .
"        ${oracleInstance}.item.create_date between to_date(:startdate, 'dd-mm-yyyy') and                      "  .
"         to_date(:enddate, 'dd-mm-yyyy') and                      "  .
"         ((${oracleInstance}.mfhd_master.create_date between                      "  .
"            to_date(:startdate, 'dd-mm-yyyy') and                      "  .
"            to_date(:enddate, 'dd-mm-yyyy'))  or                      "  .
"          (${oracleInstance}.mfhd_master.update_date between                      "  .
"            to_date(:startdate, 'dd-mm-yyyy') and                      "  .
"            to_date(:enddate, 'dd-mm-yyyy')))                      "  .
"UNION                      "  .
"select distinct ${oracleInstance}.bib_master.bib_id, ${oracleInstance}.mfhd_master.create_date as cdate                      "  .
"from      ${oracleInstance}.bib_master,                      "  .
"         ${oracleInstance}.bib_mfhd,                      "  .
"         ${oracleInstance}.mfhd_item,                      "  .
"         ${oracleInstance}.mfhd_master,                      "  .
"         (select record_id, link                      "  .
"          from ${oracleInstance}.elink_index                      "  .
"          where record_type='M') elink                      "  .
"where     ${oracleInstance}.bib_master.bib_id=${oracleInstance}.bib_mfhd.bib_id and                      "  .
"         ${oracleInstance}.bib_mfhd.mfhd_id=${oracleInstance}.mfhd_master.mfhd_id and                      "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=elink.record_id and                      "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=${oracleInstance}.mfhd_item.mfhd_id(+) and                      "  .
"         ${oracleInstance}.mfhd_item.item_id is null and                      "  .
"         ${oracleInstance}.mfhd_master.suppress_in_opac not in ('Y') and                      "  .
"         ${oracleInstance}.bib_master.suppress_in_opac not in ('Y') and                      "  .
"         ${oracleInstance}.mfhd_master.create_date between to_date(:startdate, 'dd-mm-yyyy') and                      "  .
"         to_date(:enddate, 'dd-mm-yyyy') and                      "  .
"         elink.link is not null)                      "  .
"group by bib_id                      "  .
"order by max(cdate) desc) a                      "  .
"where rownum <= :endRow)                      "  .
"where rnum >= :startRow                      "  
;

        try {
            $sqlStmt = $this->executeSQL($sql, $bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $items['results'][]['id'] = $row['BIB_ID'];
            }
            return $items;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }



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
            if (array_key_exists('renewLimit', $result) &&  $result['renewLimit']) continue;

            $filteredResults[] = $result;
        }
        return $filteredResults;
    }

    public function getMyILLRequests($patron)
    {
        return array_merge(
                       $this->getHoldsFromApi($patron, false),
                       $this->getCallSlips($patron, true) // local callslips too
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
                $labels = array();
                $links = array();
                $texts = array();
                $the856s = $this->getMFHD856s($record);
                foreach ($the856s as $the856) {
                   $labels[] = $the856['label'];
                   $links[] = $the856['link'];
                   $texts[] = $the856['text'];
                }
                $row['eresource_text'] = $texts;
                $row['eresource_label'] = $labels;
                $row['eresource'] = $links;

            }
        } catch (\Exception $e) {
            trigger_error(
                'Poorly Formatted MFHD Record', E_USER_NOTICE
            );
        }
        // Always show the Request Item link (i.e., even before patrons are logged in!)
        $row['addILLRequestLink'] = true;

//file_put_contents("/usr/local/vufind/holdings.txt", "\n\n******************************HOLDING info\n" . var_export($row, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);
        return $row;
    }


    // Overridden so that we can "fix" the response XML so that it can be loaded into XML
    protected function makeRequest2($hierarchy, $params = false, $mode = 'GET',
        $xml = false
    ) {
        // Build Url Base
        $urlParams = "http://{$this->ws_host}:{$this->ws_port}/{$this->ws_app}";

        // Add Hierarchy
        foreach ($hierarchy as $key => $value) {
            $hierarchyString[] = ($value !== false)
                ? urlencode($key) . '/' . urlencode($value) : urlencode($key);
        }

        // Add Params
        $queryString = [];
        foreach ($params as $key => $param) {
            $queryString[] = urlencode($key) . '=' . urlencode($param);
        }

        // Build Hierarchy
        $urlParams .= '/' . implode('/', $hierarchyString);

        // Build Params
        $urlParams .= '?' . implode('&', $queryString);
//$debug = "urlParams: $urlParams \n\n";

        // Create Proxy Request
        $client = $this->httpService->createClient($urlParams);

        // Add any cookies
        if ($this->cookies) {
            $client->addCookie($this->cookies);
        }

        // Set timeout value
        $timeout = isset($this->config['Catalog']['http_timeout'])
            ? $this->config['Catalog']['http_timeout'] : 30;
        $client->setOptions(['timeout' => $timeout]);

        // Attach XML if necessary
        if ($xml !== false) {
            $client->setEncType('text/xml');
            $client->setRawBody($xml);
//$debug .= "xml: $xml \n\n";
        }
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        // Send Request and Retrieve Response
        $startTime = microtime(true);
        $result = $client->setMethod($mode)->send();
        if (!$result->isSuccess()) {
            $this->error(
                "$mode request for '$urlParams' with contents '$xml' failed: "
                . $result->getStatusCode() . ': ' . $result->getReasonPhrase()
            );
            throw new ILSException('Problem with RESTful API.');
        }

        // Store cookies
        $cookie = $result->getCookie();
        if ($cookie) {
            $this->cookies = $cookie;
        }

        // Process response
        $xmlResponse = $result->getBody();
//$debug = "response to " . $urlParams . "\n\n" . $xmlResponse;
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        ///////////////////////////////////////////////////////////////////////
        // HACK: libxml will simply *NOT* load the XML returned by Voyager API
        // Therefore, we strip the namespace defs and refs
        $xmlResponse = preg_replace('/(<\/*)[^>:]+:/', '$1', $xmlResponse);
        ///////////////////////////////////////////////////////////////////////

        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $mode request $urlParams, contents:" . PHP_EOL . $xml
            . PHP_EOL . 'response: ' . PHP_EOL
            . $xmlResponse
        );
        $oldLibXML = libxml_use_internal_errors();
        libxml_use_internal_errors(true);
        $simpleXML = @simplexml_load_string($xmlResponse);
        libxml_use_internal_errors($oldLibXML);

        if ($simpleXML === false) {
            return false;
        }
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\nsimpleXML:\n\n" . var_export($simpleXML, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        return $simpleXML;
    }

    // https://github.com/CARLI/vufind/issues/192
    //
    // The 'label' should be taken from the $y.
    // In the absence of the $y, use the $3.
    // In the absence of the $y or $3, use the $u.
    //
    // The 'link' is always the URL in the 856 $u.
    // After the 'link', insert a space and display the 'text' of the 856 $z (which is free text note field).
    //
    protected function getMFHD856s($record)
    {
        $results = array();
        if ($fields = $record->getFields('856')) {
            $sfValues = array();
            foreach ($fields as $field) {
                if ($subfields = $field->getSubfields()) {
                    foreach ($subfields as $code => $subfield) {
                        if (!strstr('y3uz', $code)) {
                            continue;
                        }
                        $sfValues[$code] = $subfield->getData();
                    }
                }

                if (! array_key_exists('u', $sfValues)) {
                    continue;
                }

                $the856 = array();

                $the856['link'] = $sfValues['u'];

                $the856['label'] = $sfValues['u'];
                if (array_key_exists('y', $sfValues)) {
                    $the856['label'] = $sfValues['y'];
                } else if (array_key_exists('3', $sfValues)) {
                    $the856['label'] = $sfValues['3'];
                } 

                $the856['text'] = '';
                if (array_key_exists('z', $sfValues)) {
                    $the856['text'] = $sfValues['z'];
                }

                $results[] = $the856;
            }
        }
        return $results;
    }

    // Disable the Holds & Recalls actions, since we show this information already in Requested Items
    public function supportsMethod($method, $params)
    {
        if ($method == "getMyHolds") {
            return false;
        }
        return parent::supportsMethod($method, $params);
    }

    // Set the default pickup library to that of the patron's library (not the item owning's library)
    public function getILLPickupLibraries($id, $patron)
    {
        $results = parent::getILLPickupLibraries($id, $patron);
        if ($results === false) {
            return false;
        }

        // determine logged-in patron's UbId
        list($source, $patronId) = explode('.', $patron['id'], 2);
        if (isset($this->config['ILLRequestSources'][$source])) {
            $pickupLibUbId = $this->config['ILLRequestSources'][$source];

            // clear out any default settings and set only the patron's UbId setting
            for ($i = 0; $i < count($results); ++$i) {
               if ($results[$i]['id'] == $pickupLibUbId) {
                   $results[$i]['isDefault'] = true;
               } else {
                   $results[$i]['isDefault'] = false;
               }
            }

            // The results aren't in order. The item owning's library is at the top. Fix this by re-sorting.
            $this->sortByAssocArrayKey('name', $results, 'asc');
        }

        return $results;
    }


   // Function described in following article: https://joshtronic.com/2013/09/23/sorting-associative-array-specific-key/
   function sortByAssocArrayKey($field, &$array, $direction = 'asc')
   {
       usort($array, create_function('$a, $b', '
           $a = $a["' . $field . '"];
           $b = $b["' . $field . '"];

           if ($a == $b) return 0;

           $direction = strtolower(trim($direction));

           return ($a ' . ($direction == 'desc' ? '>' : '<') .' $b) ? -1 : 1;
       '));

       return true;
   }

}

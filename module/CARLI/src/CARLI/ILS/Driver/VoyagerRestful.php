<?php

namespace CARLI\ILS\Driver;

use File_MARC, Yajra\Pdo\Oci8, PDO, PDOException, VuFind\Exception\ILS as ILSException;

class VoyagerRestful extends \VuFind\ILS\Driver\VoyagerRestful
{

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
                $result['fine'] = (string)$fineFee->postingType;
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

file_put_contents("/usr/local/vufind/holdings.txt", "\n\n******************************HOLDING info\n" . var_export($row, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);
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
$debug = "urlParams: $urlParams \n\n";

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
$debug .= "xml: $xml \n\n";
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
$debug = "response to " . $urlParams . "\n\n" . $xmlResponse;
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

}


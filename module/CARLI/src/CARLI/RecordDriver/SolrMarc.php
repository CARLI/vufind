<?php

namespace CARLI\RecordDriver;

class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{

    /**************************

    GitHub Issue #234 
    GitHub Issue #235

    * Only Bibliographic 856 fields with first indicator 1, 4 or 7 AND second indicator 0 or 1 are included on the VuFind Results page.

    1. The linking text for one Bibliographic 856 field is "Get it online".

    2. If more than one 856 field with appropriate indicators exists in the Bibliographic record,
    the first 856 that has appropriate indicators is the link from the “Get it online” text and a
    hyperlinked "(More...)" leads the user to the Record page to access all 856 fields.

    * If the Bibliographic 856 field has the text “table of contents” in any subfield, it is not included on
    the VuFind Results page regardless of indicators.

    * The presence of the Bibliographic 856 field does not suppress the display of the call number.

    * If the SFX button will display (because the ISSN in the 022$a of the Bibliographic record matches the ISSN of an active object in the library’s SFX KnowledgeBase), the Results page will not display a “Get it online” link even if a Bibliographic 856 field(s) is present.

    * Future development: if the SFX (or other link resolver) button OR the “Get it online” link displays,
    the red “Not available” status will be suppressed even if the Item is unavailable.


    **************************/
    public function get856s()
    {
        $results = array();
        if ($fields = $this->getMarcRecord()->getFields('856')) {
            foreach ($fields as $field) {
                $ind1 = $field->getIndicator(1);
                $ind2 = $field->getIndicator(2);
                if (($ind1 == 1 || $ind1 == 4 || $ind1 == 7) && ($ind2 == 0 || $ind2 == 1)) {
                    // basic requirement met
                } else {
                    // basic requirement NOT met... skip it
                    continue;
                }

                $isTOC = false;
                $sfValues = array();
                if ($subfields = $field->getSubfields()) {
                    foreach ($subfields as $code => $subfield) {
                        if (!strstr('y3uz', $code)) {
                            continue;
                        }
                        $subfieldData = $subfield->getData();

                        if (preg_match('/table of contents/i', $subfieldData)) {
                            $isTOC = true;
                            break;
                        }

                        if (array_key_exists($code, $sfValues)) {
                            $sfValues[$code] .= ' ' . $subfieldData;
                        } else {
                            $sfValues[$code] = $subfieldData;
                        }
                    }
                }
                if (! array_key_exists('u', $sfValues)) {
                    continue;
                }
                // skip it if 'table of contents' is present
                if ($isTOC) {
                    continue;
                }

                $the856 = array();
                $the856['url'] = $sfValues['u'];
                $the856['desc'] = $sfValues['u'];
                if (array_key_exists('3', $sfValues) && array_key_exists('y', $sfValues)) {
                    $the856['desc'] = $sfValues['3'] . ' ' . $sfValues['y'];
                } else if (array_key_exists('3', $sfValues)) {
                    $the856['desc'] = $sfValues['3'];
                } else if (array_key_exists('y', $sfValues)) {
                    $the856['desc'] = $sfValues['y'];
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

    protected function getOpenUrlFormat()
    {
        // If we have multiple formats, Book, Journal and Article are most
        // important...
        $formats = $this->getFormats();
        if (in_array('Book', $formats)) {
            return 'Book';
        } else if (in_array('Article', $formats)) {
            return 'Article';
        // CARLI edit: Treat "Journal / Magazine" like it's a "Journal"
        } else if (in_array('Journal / Magazine', $formats)) {
            return 'Journal';
        } else if (isset($formats[0])) {
            return $formats[0];
        } else if (strlen($this->getCleanISSN()) > 0) {
            return 'Journal';
        } else if (strlen($this->getCleanISBN()) > 0) {
            return 'Book';
        }
        return 'UnknownFormat';
    }

    public function getURLs()
    {
       # the base path of the URL, e.g., /all/vf-xxx, /vf-xxx
       $basePath = getenv('VUFIND_LIBRARY_BASE_PATH');

       # we do not want bib-related URLs to show up in the union catalog
       if (strpos($basePath, '/all/vf') === 0) {
          return null;
       }

       return $this->get856s();
    }

    public function getTOC()
    {
        // Return empty array if we have no table of contents:
        $fields = $this->getMarcRecord()->getFields('505');
        if (!$fields) {
            return [];
        }

        // If we got this far, we have a table -- collect it as a string:
        $tocStr = '';
        foreach ($fields as $field) {
            $subfields = $field->getSubfields();
            foreach ($subfields as $subfield) {
                $tocStr .= $subfield->getData() . ' ';
            }
        }
        $toc = array();
        $toc = explode('--', $tocStr);
        return $toc;
    }

    public function getSummary()
    {
        return $this->getFieldArray('520', ['3', 'a', 'b', 'c'], true);
    }

    public function getTargetAudienceNotes()
    {
        return array_merge($this->getFieldArray('385', ['a', 'm', '3']), $this->getFieldArray('521', ['a', '3']));
    }

    public function getSeries()
    {
        $matches = [];

        // First check the 440, 800 and 830 fields for series information:
        $primaryFields = [
            '440' => ['a', 'n', 'p'],
            '800' => ['a', 'b', 'c', 'd', 'f', 'p', 'q', 't'],
            '830' => ['a', 'n', 'p']
        ];
        $matches = $this->getSeriesFromMARC($primaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Now check 490 and display it only if 440/800/830 were empty:
        $secondaryFields = ['490' => ['a']];
        $matches = $this->getSeriesFromMARC($secondaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Still no results found?  Resort to the Solr-based method just in case!
        return parent::getSeries();
    }

    public function getGeneralNotes()
    {
        $ret = $this->getFieldArray('500', [ 'a', '3' ]);
        $ret = array_merge($ret, $this->getFieldArray('515', [ 'a' ]));
        $ret = array_merge($ret, $this->getFieldArray('550', [ 'a' ]));
        return $ret;
    }

    /**
     * Get access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        return $this->getFieldArray('506', [ 'a', 'b', 'c', 'd', 'e', 'f' ]);
    }

    /**
     * Get an array of technical details on the item represented by the record.
     *
     * @return array
     */
    public function getSystemDetails()
    {
        return $this->getFieldArray('538', [ 'a', '3' ]);
    }

    /**
     * Get award notes for the record.
     *
     * @return array
     */
    public function getAwards()
    {
        return $this->getFieldArray('586', ['a', '3']);
    }

    /**
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
        return $this->getFieldArray('555', ['a', 'b', 'c', 'd', 'u', '3']);
    }

    //////////////////////////////////////////
    // CARLI-specific methods:
    /**
     * Get an array of scale information.
     *
     * @return array
     */
    public function getScale()
    {
        return $this->getFieldArray('255', ['a', 'b', 'c', 'd', 'e', 'f', 'g']);
    }

    /**
     * Get an array of creator characteristics information.
     *
     * @return array
     */
    public function getCreatorCharacteristics()
    {
        return $this->getFieldArray('386', ['a', 'i', 'm', '3']);
    }

    /**
     * Get an array of dissertation note information.
     *
     * @return array
     */
    public function getDissertationNote()
    {
        return $this->getFieldArray('502', ['a', 'b', 'c', 'd', 'g', 'o']);
    }

    /**
     * Get an array of language notes information.
     *
     * @return array
     */
    public function getLanguageNotes()
    {
        return $this->getFieldArray('546', ['a', 'b', '3']);
    }

    /**
     * Get an array of performer note information.
     *
     * @return array
     */
    public function getPerformerNote()
    {
        return $this->getFieldArray('511', ['a']);
    }

    /**
     * Get an array of event notes information.
     *
     * @return array
     */
    public function getEvent()
    {
        return $this->getFieldArray('518', ['a', 'd', 'o', 'p', '3']);
    }

    /**
     * Get an array of references notes information.
     *
     * @return array
     */
    public function getReferences()
    {
        return $this->getFieldArray('510', ['a', 'b', 'c', '3']);
    }

    /**
     * Get an array of publications notes information.
     *
     * @return array
     */
    public function getPublications()
    {
        return $this->getFieldArray('581', ['a', '3']);
    }

    /**
     * Get an array of meeting name notes information.
     *
     * @return array
     */
    public function getMeetingName()
    {
        return $this->getFieldArray('111', ['a', 'c', 'd', 'e', 'f', 'g', 'j', 'k', 'l', 'n', 'p', 'q', 't', 'u']);
    }

    public function getUniformTitle()
    {
        $results = $this->getFieldArray('130', ['a', 'd', 'f', 'g', 'h', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't']);
        $results = array_merge($results, $this->getFieldArrayWithIndicatorValue('240', ['a', 'd', 'f', 'g', 'h', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's'], [1], null));
        return $results;
    }

    public function getDescriptionOfWork()
    {
        $results = $this->getFieldArray('383', ['a', 'b', 'c', 'd', 'e', '3']);
        $results = array_merge($results, $this->getFieldArray('384', ['a', '3']));
        $results = array_merge($results, $this->getFieldArray('380', ['a', '3']));
        $results = array_merge($results, $this->getFieldArray('381', ['a']));
        $results = array_merge($results, $this->getFieldArray('382', ['a', 'b', 'd', 'e', 'n', 'p', 'r', 's', 't', 'v', '3']));
        return $results;
    }

    public function getHostItem()
    {
        $results = $this->getFieldArrayWithIndicatorValue('773', ['a', 'b', 'd', 'g', 'h', 'i', 'k', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'x', 'y', 'z', '3'], [0], null);
        return $results;
    }

    public function getMainAuthor()
    {
        $results = $this->getFieldArray('100', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'j', 'k', 'l', 'n', 'p', 'q', 't', 'u']);
        $results = array_merge($results, $this->getFieldArray('110', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'k', 'l', 'n', 'p', 'q', 't', 'u']));
        return $results;
    }

    public function getOtherNotes()
    {
        $results = $this->getFieldArray('501', ['a']);
        $results = array_merge($results, $this->getFieldArray('533', ['a']));
        $results = array_merge($results, $this->getFieldArray('544', ['a', 'b', 'c', 'd', 'e', 'n', '3']));
        $results = array_merge($results, $this->getFieldArray('562', ['a', 'b', 'c', 'd', '3']));
        $results = array_merge($results, $this->getFieldArray('590', ['a', '3']));
        $results = array_merge($results, $this->getFieldArray('591', ['a']));
        $results = array_merge($results, $this->getFieldArray('592', ['a']));
        $results = array_merge($results, $this->getFieldArray('593', ['a']));
        $results = array_merge($results, $this->getFieldArray('594', ['a']));
        $results = array_merge($results, $this->getFieldArray('595', ['a']));
        $results = array_merge($results, $this->getFieldArray('596', ['a']));
        $results = array_merge($results, $this->getFieldArray('597', ['a']));
        $results = array_merge($results, $this->getFieldArray('598', ['a']));
        $results = array_merge($results, $this->getFieldArray('599', ['a']));
        return $results;
    }

    public function getPreferredCitation()
    {
        return  $this->getFieldArray('524', ['a', '3']);
    }

    public function getSupplementNote()
    {
        return  $this->getFieldArray('525', ['a']);
    }

    public function getUseRestrictions()
    {
        return  $this->getFieldArray('540', ['a', 'b', 'c', 'd', 'u', '3']);
    }

    public function getSourceOfAcquisition()
    {
        return $this->getFieldArrayWithIndicatorValue('541', ['a', 'b', 'c', 'd', 'e', 'f', 'n', 'o', '3'], [1], null);
    }

    public function getBioHistoricalData()
    {
        return $this->getFieldArray('545', ['a', 'b', 'u']);
    }

    public function getOwnershipHistory()
    {
        return $this->getFieldArrayWithIndicatorValue('561', ['a', '3'], [1], null);
    }

    public function getBindingInformation()
    {
        return $this->getFieldArray('563', ['a', '3']);
    }

    public function getActionNote()
    {
        return $this->getFieldArrayWithIndicatorValue('583', ['a', 'b', 'c', 'd', 'e', 'f', 'h', 'i', 'j', 'k', 'l', 'n', 'o', 'u', 'z', '3'], [1], null);
    }

    public function getExhibitionNote()
    {
        return $this->getFieldArray('585', ['a', '3']);
    }

    public function getTechnicalSpecifications()
    {
        $results = $this->getFieldArray('344', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);
        $results = array_merge($results, $this->getFieldArray('345', ['a', 'b', '3']));
        $results = array_merge($results, $this->getFieldArray('346', ['a', 'b', '3']));
        return $results;
    }

    public function getDigitalCharacteristics()
    {
        return $this->getFieldArray('347', ['a', 'b', 'c', 'd', 'e', 'f']);
    }

    //////////////////////////////////////////

    // HELPER method
    protected function getFieldArrayWithIndicatorValue($fieldValue, $validSubfields, $ind1Array, $ind2Array)
    {
        $results = array();

        if ($fields = $this->getMarcRecord()->getFields($fieldValue)) {
            foreach ($fields as $field) {

                $ind1Valid = false;
                if (is_null($ind1Array)) {
                    $ind1Valid = true;
                } else {
                    if (in_array($field->getIndicator(1), $ind1Array)) {
                        $ind1Valid = true;
                    }
                }

                $ind2Valid = false;
                if (is_null($ind2Array)) {
                    $ind2Valid = true;
                } else {
                    if (in_array($field->getIndicator(2), $ind2Array)) {
                        $ind2Valid = true;
                    }
                }

                if ($ind1Valid && $ind2Valid) {
                    $valStr = '';
                    if ($subfields = $field->getSubfields()) {
                        foreach ($subfields as $code => $subfield) {
                            if (in_array($code, $validSubfields)) {
                                $valStr .= ' '. $subfield->getData();
                            }
                        }
                    }
                    if (strlen($valStr) > 0) {
                        $results[] = ltrim($valStr);
                    }
                }
            }
        }

        return $results;
    }

}


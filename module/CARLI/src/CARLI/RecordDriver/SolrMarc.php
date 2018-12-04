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
        return $this->getFieldArray('520', ['a', 'c'], true);
    }



}


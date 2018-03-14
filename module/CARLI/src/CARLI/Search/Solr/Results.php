<?php

namespace CARLI\Search\Solr;

use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Backend\Solr\Response\Json\NamedList;

class Results extends \VuFind\Search\Solr\Results
{
    protected $includeUngroupedLocations = false;
    protected $facetMinCount = 1;
    protected $groupSortOrder; // needed in usort anon fn
    protected $libraryInstance; // e.g., UIUdb

    protected function performSearch()
    {
        parent::performSearch();

        $this->libraryInstance = getenv('VUFIND_LIBRARY_DB'); // e.g., UIUdb

        Util::group_data($id2group, $group2id, $group2desc, $this->groupSortOrder);
        Util::location_data($loc2ids, $id2locs);

        $fieldFacets = $this->responseFacets->getFieldFacets();

        if (! array_key_exists('collection', $fieldFacets)) {
            return;
        }
        $locs = $fieldFacets['collection'];

        $groupedLocations = array();
        $unGroupedLocations = array();
        foreach ($locs as $loc => $count) {
            if ($count < $this->facetMinCount) {
                continue;
            }
            if (array_key_exists($loc, $loc2ids)) {
                $groupIds = $loc2ids[$loc];
                foreach ($groupIds as $groupId) {
                    $groupName = $groupId;
                    if (array_key_exists($groupId, $id2group)) {
                        $groupName = $id2group[$groupId];
                    } else {
                        // not assigned to a group? ignore it...
                        continue;
                    }
                    // If group has been seen already, simply update the count
                    $isDuplicate = 0;
                    for ($i = 0; $i < count($groupedLocations); ++$i) {
                        if ($groupedLocations[$i][0] == $groupName) {
                            $isDuplicate = 1;
                            if ($count > $groupedLocations[$i][1]) {
                                $groupedLocations[$i][1] = 0; // We can't know for certain what this count is, so set it to 0 (hide later)
                            }
                            break;
                        }
                    }
                    if (! $isDuplicate) {
                        $groupedLocations[] = array($groupName, 0); // We can't know for certain what this count is, so set it to 0 (hide later in templates)
                    }
               }
            } else {
                $unGroupedLocations[] = array($loc, 0);// We can't know for certain what this count is, so set it to 0 (hide later in templates)
            }
        }

        if ($this->includeUngroupedLocations) {
            $combinedLocs = array_merge($groupedLocations, $unGroupedLocations);
        } else {
            $combinedLocs = $groupedLocations;
        }
        usort($combinedLocs, function($a, $b) {
            $a0 = 1000000; 
            if (preg_match('/_group_/', $a[0], $matches) ) {
                $a0 -= 1000; // give precedence to groups (over ungrouped locations)
            }
            $b0 = 1000000; // a "large" number
            if (preg_match('/_group_/', $b[0], $matches) ) {
                $b0 -= 1000; // give precedence to groups (over ungrouped locations)
            }

            // only keep sort order if within the same local library instance; otherwise, put randomly at the end
            if (strlen($this->libraryInstance) > 0) {
                if (preg_match('/^' . $this->libraryInstance . '_group_/', $a[0], $matches) ) {
                    if (array_key_exists($a[0], $this->groupSortOrder)) {
                        $a0 = $this->groupSortOrder[$a[0]];
                    }
                }
                if (preg_match('/^' . $this->libraryInstance . '_group_/', $b[0], $matches) ) {
                    if (array_key_exists($b[0], $this->groupSortOrder)) {
                        $b0 = $this->groupSortOrder[$b[0]];
                    }
                }
            }
            return $a0 - $b0;
        });

        $replaceWith = new NamedList($combinedLocs);
        $fieldFacets['collection'] = $replaceWith;
    }

}

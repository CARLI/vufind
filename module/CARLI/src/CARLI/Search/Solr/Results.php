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
    protected $groupSortOrder;
    protected $libraryInstance; // e.g., UIUdb

    protected function performSearch()
    {
        parent::performSearch();

        $isUC = getenv('VUFIND_LIBRARY_IS_UC'); // e.g., 1

        // only process local catalogs
        if ($isUC) {
            return;
        }

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
                if (array_key_exists($loc, $loc2ids)) {
                    $unGroupedLocations[] = array($loc, 0);// We can't know for certain what this count is, so set it to 0 (hide later in templates)
                }
            }
        }

        if ($this->includeUngroupedLocations) {
            $combinedLocs = array_merge($groupedLocations, $unGroupedLocations);
        } else {
            $combinedLocs = $groupedLocations;
        }
        usort($combinedLocs, function($a, $b) {
            $groupNameA = $this->translate($a[0]);
            $groupNameB = $this->translate($b[0]);

            // give precedence to groups within same local library instance
            $isLocalGroupA = false;
            $isLocalGroupB = false;
            if (strlen($this->libraryInstance) > 0) {
                if (preg_match('/^' . $this->libraryInstance . '_group_/', $a[0], $matches) ) {
                    $isLocalGroupA = true;
                }
                if (preg_match('/^' . $this->libraryInstance . '_group_/', $b[0], $matches) ) {
                    $isLocalGroupB = true;
                }
            }
            if ($isLocalGroupA) {
                $groupNameA = ' ' . $groupNameA; // prepend space to put it up near the top
            }
            if ($isLocalGroupB) {
                $groupNameB = ' ' . $groupNameB; // prepend space to put it up near the top
            }
            return strcmp(strtoupper($groupNameA), strtoupper($groupNameB));
        });

        $replaceWith = new NamedList($combinedLocs);
        $fieldFacets['collection'] = $replaceWith;
    }

}

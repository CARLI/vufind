<?php

namespace CARLI\Search\Solr;

use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Backend\Solr\Response\Json\NamedList;

class Results extends \VuFind\Search\Solr\Results
{
    protected $includeUngroupedLocations = false;
    protected $facetMinCount = 2;
    protected $group2desc; // needed in usort anon fn

    protected function performSearch()
    {
        parent::performSearch();

        Util::group_data($id2group, $group2id, $this->group2desc);
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
                        $groupedLocations[] = array($groupName, 0); // We can't know for certain what this count is, so set it to 0 (hide later)
                    }
               }
            } else {
                $unGroupedLocations[] = array($loc, 0);// We can't know for certain what this count is, so set it to 0 (hide later)
            }
        }

        if ($this->includeUngroupedLocations) {
            $combinedLocs = array_merge($groupedLocations, $unGroupedLocations);
        } else {
            $combinedLocs = $groupedLocations;
        }
        usort($combinedLocs, function($a, $b) {
            $a0 = $a[0];
            $b0 = $b[0];
            if (array_key_exists($a0, $this->group2desc)) {
                $a0 = $this->group2desc[$a0];
            }
            if (array_key_exists($b0, $this->group2desc)) {
                $b0 = $this->group2desc[$b0];
            }
            return strcmp(strtolower($a0), strtolower($b0));
        });

        $replaceWith = new NamedList($combinedLocs);
        $fieldFacets['collection'] = $replaceWith;
    }

}

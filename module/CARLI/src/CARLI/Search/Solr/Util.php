<?php

namespace CARLI\Search\Solr;

use VuFind\Config\Locator as ConfigLocator;

class Util
{
    static public function read_locs() {
        return Util::read_config_file('loc_limit_locs.txt');
    }

    static public function read_groups() {
        return Util::read_config_file('loc_limit_grps.txt');
    }

    static public function read_config_file($filename)
    {
        $data = array();
        $filename
            = ConfigLocator::getConfigPath($filename, 'config/vufind');
        if (!file_exists($filename)) {
            throw new Exception(
                "Cannot load config file: {$filename}."
            );
        }

        if (($handle = fopen($filename, "r")) !== false) {
            while (($line = fgets($handle)) !== false) {
                $data[] = trim($line);
            }
            fclose($handle);
        }
        return $data;
    }

    # groups
    #UIUdb|   57|oakstreet |Oak Street Facility
    static public function group_data(&$id2group, &$group2id, &$group2desc) {
        $id2group = array();
        $group2desc = array();
        $data = Util::read_groups();
        foreach ($data as $line) {
            list($library, $id, $group, $desc) = preg_split('/\s*\|\s*/', $line);
            $id2group[$library . '_' . $id] = $library . '_group_' . $group;
            $group2id[$library . '_group_' . $group] = $library . '_' . $id;
            $group2desc[$library . '_' . $group] = $desc;
        }
    }

    # locs - NOTE: many-to-many loc-to-id
    #UIUdb|stos      |   57
    #UIUdb|maos      |   57
    #UIUdb|mdos      |   57
    #UIUdb|bios      |   57
    #UIUdb|rbcl-nc   |   43
    #UIUdb|rhlallg   |   56
    #UIUdb|rbpres-nc |   43
    #UIUdb|rbs-nc    |   43
    #UIUdb|rbts-nc   |   43
    static public function location_data(&$loc2ids, &$id2locs) {
        $loc2ids = array();
        $data = Util::read_locs();
        foreach ($data as $line) {
            list($library, $loc, $id) = preg_split('/\s*\|\s*/', $line);
            $loc2ids[$library . '_' . $loc][] = $library . '_' . $id;
            $id2locs[$library . '_' . $id][] = $library . '_' . $loc;
        }
    }

}

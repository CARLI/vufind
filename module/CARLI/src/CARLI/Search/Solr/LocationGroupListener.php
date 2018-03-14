<?php

namespace CARLI\Search\Solr;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

class LocationGroupListener
{
    protected $filters;

    public function __construct($searchConf)
    {
        $this->filters = $searchConf;
    }

    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
    }

    public function onSearchPre(EventInterface $event)
    {
        $isUC = getenv('VUFIND_LIBRARY_IS_UC'); // e.g., 1
        $libraryInstance = getenv('VUFIND_LIBRARY_DB'); // e.g., UIUdb

        // only process local catalogs
        if ($isUC) {
            return $event;
        }

        $params = $event->getParam('params');
        $fq = $params->get('fq');
        if (!is_array($fq)) {
            $fq = [];
        }

        Util::group_data($id2group, $group2id, $group2desc);
        Util::location_data($loc2ids, $id2locs);

        $new_fq = array();
        #0 => 'collection:"UIUdb_group_stacks"',
        foreach ($fq as $orig_fq) {
            $replace_with_fq_str = '';
            $replace_with_fq = array();
            if (preg_match('/^collection:"(' . $libraryInstance . '_group_[^"]+)"/', $orig_fq, $matches) ) {
                $group = $matches[1];
                if (array_key_exists($group, $group2id)) {
                    $group_id = $group2id[$group];
                    if (array_key_exists($group_id, $id2locs)) {
                        foreach ($id2locs[$group_id] as $loc) {
                            $locs[] = $loc;
                            $replace_with_fq[] = 'collection:"' . $loc . '"';
                        }
                        $replace_with_fq_str = implode(' OR ', $replace_with_fq);

                    }
                }
            } else if (preg_match('/^collection:"([^_]+)_group_([^"]+)"/', $orig_fq, $matches) ) {
                $libInst = $matches[1];
                $group = $matches[2];
                $replace_with_fq_str = 'collection:"' . $libInst . '_' . $group . '"';
            }
            if (strlen($replace_with_fq_str) > 0) {
                $new_fq[] = '(' . $replace_with_fq_str . ')';
            } else {
                $new_fq[] = $orig_fq;
            }
        }


//file_put_contents("/usr/local/vufind/look.txt", "\n\n****************************** new_fq = " . var_export($new_fq, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        $params->set('fq', $new_fq);

        return $event;
    }
}

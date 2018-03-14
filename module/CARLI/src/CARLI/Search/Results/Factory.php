<?php

namespace CARLI\Search\Results;

class Factory
{

    public static function getSolr(\Zend\ServiceManager\ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $solr = $factory->createServiceWithName($sm, 'solr', 'Solr');
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')->get('config');
        $spellConfig = isset($config->Spelling)
            ? $config->Spelling : null;
        $solr->setSpellingProcessor(
            new \VuFind\Search\Solr\SpellingProcessor($spellConfig)
        );
        return $solr;
    }

}


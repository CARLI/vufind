<?php

return array (
  'vufind' => 
  array (
    'plugin_managers' => 
    array (
      'ils_driver' => 
      array (
        'factories' => 
        array (
          'voyagerrestful' => 'CARLI\ILS\Driver\Factory::getVoyagerRestful',
          'multibackend' => 'CARLI\ILS\Driver\Factory::getMultiBackend',
          'noils' => 'CARLI\ILS\Driver\Factory::getNoILS',
        ),
      ),
      'recorddriver' => 
      array (
        'factories' => 
        array (
          'solrmarc' => 'CARLI\RecordDriver\Factory::getSolrMarc',
        ),
      ),

      //// CARLI EDIT BEGIN //////
      'search_results' => 
       array (
        'factories' => 
         array (
           'solr' => 'CARLI\Search\Results\Factory::getSolr',
         ),
       ),
      //// CARLI EDIT END //////
    ),
    /////// CARLI EDIT BEGIN ///////
        'recorddriver_tabs' => array (
            'VuFind\RecordDriver\SolrDefault' => array (
                'tabs' => array (
                    'Holdings' => 'HoldingsILS', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    //'Similar' => 'SimilarItemsCarousel',
                    'Similar' => null, // CARLI: remove the Similar Items tab!
                    'Details' => 'StaffViewArray',
                ),
                'defaultTab' => null,
                // 'backgroundLoadedTabs' => ['UserComments', 'Details']
            ),
            'VuFind\RecordDriver\SolrMarc' => array (
                'tabs' => array (
                    'Holdings' => 'HoldingsILS', 'Description' => 'Description',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    //'Similar' => 'SimilarItemsCarousel',
                    'Similar' => null, // CARLI: remove the Similar Items tab!
                    'Details' => 'StaffViewMARC',
                ),
                'defaultTab' => null,
            ),
        ),
    /////// CARLI EDIT END /////////
  ),
);

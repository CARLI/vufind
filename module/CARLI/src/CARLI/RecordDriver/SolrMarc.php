<?php

namespace CARLI\RecordDriver;

class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
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

       return parent::getURLs();
    }

}


<?php

namespace CARLI\View\Helper\Root;

class RecordDataFormatterFactory extends \VuFind\View\Helper\Root\RecordDataFormatterFactory
{

    /**
     * Get default specifications for displaying data in collection-info metadata.
     *
     * @return array
     */
    public function getDefaultCollectionInfoSpecs()
    {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();

        $spec->setTemplateLine(
            'Main Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'labelFunction' => function ($data) {
                    return count($data['primary']) > 1
                        ? 'Main Authors' : 'Main Author';
                },
                'context' => ['type' => 'primary', 'schemaLabel' => 'author'],
            ]
        );
        $spec->setTemplateLine(
            'Corporate Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'labelFunction' => function ($data) {
                    return count($data['corporate']) > 1
                        ? 'Corporate Authors' : 'Corporate Author';
                },
                'context' => ['type' => 'corporate', 'schemaLabel' => 'creator'],
            ]
        );
        $spec->setTemplateLine(
            'Other Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'context' => [
                    'type' => 'secondary', 'schemaLabel' => 'contributor'
                ],
            ]
        );
        $spec->setLine('Summary', 'getSummary');
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        $spec->setLine('Language', 'getLanguages');
        $spec->setTemplateLine(
            'Published', 'getPublicationDetails', 'data-publicationDetails.phtml'
        );
        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml'
        );
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );
        $spec->setLine('General Notes', 'getGeneralNotes'); // CARLI edited label "Notes" -> "General Notes"
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('ISBN', 'getISBNs');
        $spec->setLine('ISSN', 'getISSNs');
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in collection-record metadata.
     *
     * @return array
     */
    public function getDefaultCollectionRecordSpecs()
    {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();

        $spec->setLine('Summary', 'getSummary');
        $spec->setTemplateLine(
            'Main Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'labelFunction' => function ($data) {
                    return count($data['primary']) > 1
                        ? 'Main Authors' : 'Main Author';
                },
                'context' => ['type' => 'primary', 'schemaLabel' => 'author'],
            ]
        );
        $spec->setTemplateLine(
            'Corporate Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'labelFunction' => function ($data) {
                    return count($data['corporate']) > 1
                        ? 'Corporate Authors' : 'Corporate Author';
                },
                'context' => ['type' => 'corporate', 'schemaLabel' => 'creator'],
            ]
        );
        $spec->setTemplateLine(
            'Other Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'context' => [
                    'type' => 'secondary', 'schemaLabel' => 'contributor'
                ],
            ]
        );
        $spec->setLine('Language', 'getLanguages');
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        $spec->setLine('Restrictions', 'getAccessRestrictions'); // CARLI edited label "Access" -> "Restrictions"
        $spec->setLine('Related Items', 'getRelationshipNotes');
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();

        $spec->setTemplateLine(
            'Published in', 'getContainerTitle', 'data-containerTitle.phtml'
        );
        $spec->setLine(
            'New Title', 'getNewerTitles', null, ['recordLink' => 'title']
        );
        $spec->setLine(
            'Previous Title', 'getPreviousTitles', null, ['recordLink' => 'title']
        );
        $spec->setTemplateLine(
            'Main Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'labelFunction' => function ($data) {
                    return count($data['primary']) > 1
                        ? 'Main Authors' : 'Main Author';
                },
                'context' => [
                    'type' => 'primary',
                    'schemaLabel' => 'author',
                    'requiredDataFields' => [
                        ['name' => 'role', 'prefix' => 'CreatorRoles::']
                    ]
                ]
            ]
        );
        $spec->setTemplateLine(
            'Corporate Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'labelFunction' => function ($data) {
                    return count($data['corporate']) > 1
                        ? 'Corporate Authors' : 'Corporate Author';
                },
                'context' => [
                    'type' => 'corporate',
                    'schemaLabel' => 'creator',
                    'requiredDataFields' => [
                        ['name' => 'role', 'prefix' => 'CreatorRoles::']
                    ]
                ]
            ]
        );
        $spec->setTemplateLine(
            'Other Authors', 'getDeduplicatedAuthors', 'data-authors.phtml',
            [
                'useCache' => true,
                'context' => [
                    'type' => 'secondary',
                    'schemaLabel' => 'contributor',
                    'requiredDataFields' => [
                        ['name' => 'role', 'prefix' => 'CreatorRoles::']
                    ]
                ],
            ]
        );
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        $spec->setLine('Language', 'getLanguages');
        $spec->setTemplateLine(
            'Published', 'getPublicationDetails', 'data-publicationDetails.phtml'
        );
        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml'
        );
        $spec->setTemplateLine(
            'child_records', 'getChildRecordCount', 'data-childRecords.phtml',
            ['allowZero' => false]
        );
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );
        $spec->setTemplateLine('Tags', true, 'data-tags.phtml');
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    public function getDefaultDescriptionSpecs()
    {

        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();

        $isLocal = !getenv('VUFIND_LIBRARY_IS_UC');

        $spec->setLine('Main Author', 'getMainAuthor'); // CARLI added new method
        $spec->setLine('Meeting Name', 'getMeetingName'); // CARLI added new method
        $spec->setLine('Uniform Title', 'getUniformTitle'); // CARLI added new method
        $spec->setLine('Description of Work', 'getDescriptionOfWork'); // CARLI added new method
        $spec->setLine('In', 'getHostItem'); // CARLI added new method
        $spec->setTemplateLine('Summary', true, 'data-summary.phtml');
        $spec->setLine('Published', 'getDateSpan');
        $spec->setLine('Thesis/Dissertation', 'getDissertationNote'); // CARLI added new method
        $spec->setLine('Language Notes', 'getLanguageNotes'); // CARLI added new method
        $spec->setLine('General Notes', 'getGeneralNotes'); // CARLI edited label "Item Description" -> "General Notes"
        $spec->setLine('Physical Description', 'getPhysicalDescriptions');
        $spec->setLine('Scale', 'getScale'); // CARLI added new method
        $spec->setLine('Technical Specifications', 'getTechnicalSpecifications'); // CARLI added new method
        $spec->setLine('Performer', 'getPerformerNote'); // CARLI added new method
        $spec->setLine('Event', 'getEvent'); // CARLI added new method
        $spec->setLine('References', 'getReferences'); // CARLI added new method
        $spec->setLine('Publications', 'getPublications'); // CARLI added new method
        $spec->setLine('Publication Frequency', 'getPublicationFrequency');
        $spec->setLine('Playing Time', 'getPlayingTimes');
        $spec->setLine('System Details', 'getSystemDetails'); // CARLI edited label "Format" -> "System Details"
        $spec->setLine('Audience', 'getTargetAudienceNotes');
        $spec->setLine('Creator Characteristics', 'getCreatorCharacteristics'); // CARLI added new method
        $spec->setLine('Awards', 'getAwards');
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('Bibliography', 'getBibliographyNotes');
        $spec->setLine('ISBN', 'getISBNs');
        $spec->setLine('ISSN', 'getISSNs');
        $spec->setLine('DOI', 'getCleanDOI');
        $spec->setLine('Related Items', 'getRelationshipNotes');
        $spec->setLine('Restrictions', 'getAccessRestrictions'); // CARLI edited label "Access" -> "Restrictions"
        $spec->setLine('Index/Finding Aids', 'getFindingAids'); // CARLI edited label "Finding Aid" -> "Index/Finding Aids"
        $spec->setLine('Publication_Place', 'getHierarchicalPlaceNames');
        if ($isLocal) {
            $spec->setLine('Specific Notes', 'getOtherNotes'); // CARLI added new method
            $spec->setLine('Cite As', 'getPreferredCitation'); // CARLI added new method
            $spec->setLine('Supplement', 'getSupplementNote'); // CARLI added new method
            $spec->setLine('Use Restrictions', 'getUseRestrictions'); // CARLI added new method
            $spec->setLine('Source of Acquisition', 'getSourceOfAcquisition'); // CARLI added new method
            $spec->setLine('Biographical/Historical Note', 'getBioHistoricalData'); // CARLI added new method
            $spec->setLine('Ownership History', 'getOwnershipHistory'); // CARLI added new method
            $spec->setLine('Binding Information', 'getBindingInformation'); // CARLI added new method
            $spec->setLine('Action Note', 'getActionNote'); // CARLI added new method
            $spec->setLine('Exhibition', 'getExhibitionNote'); // CARLI added new method
        }
        $spec->setTemplateLine('Author Notes', true, 'data-authorNotes.phtml');
        return $spec->getArray();
    }
}

<?php
/**
 * (c) Kitodo. Key to digital objects e.V. <contact@kitodo.org>
 *
 * This file is part of the Kitodo and TYPO3 projects.
 *
 * @license GNU General Public License version 3 or later.
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

// Define metadata elements.
// @see http://dfg-viewer.de/en/profile-of-the-metadata/
$metadata = array (
    'type' => array (
        'format' => array (
            array (
                'encoded' => 3,
                'xpath' => '$.metadata.[?(@.label==\'Manifest Type\')].value',
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 1,
        'index_indexed' => 0,
        'index_boost' => 1.00,
        'is_sortable' => 1,
        'is_facet' => 1,
        'is_listed' => 1,
        'index_autocomplete' => 0,
    ),
    'title' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => 'concat(./mods:titleInfo/mods:nonSort," ",./mods:titleInfo/mods:title)',
                'xpath_sorting' => './mods:titleInfo/mods:title',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:sourceDesc/teihdr:msDesc/teihdr:head/teihdr:note[@type="caption"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 3,
                'xpath' => '$[label]',
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => "key.wrap = <dt class=\"tx-dlf-metadata-title\">|</dt>\nvalue.required = 1\nvalue.wrap = <dd class=\"tx-dlf-metadata-title\">|</dd>",
        'index_tokenized' => 1,
        'index_stored' => 1,
        'index_indexed' => 1,
        'index_boost' => 2.00,
        'is_sortable' => 1,
        'is_facet' => 0,
        'is_listed' => 1,
        'index_autocomplete' => 1,
    ),
    'volume' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:part/mods:detail/mods:number',
                'xpath_sorting' => './mods:part[@type="host"]/@order',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 1,
        'index_indexed' => 0,
        'index_boost' => 1.00,
        'is_sortable' => 1,
        'is_facet' => 0,
        'is_listed' => 1,
        'index_autocomplete' => 0,
    ),
    'author' => array (
        'format' => array (
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:sourceDesc/teihdr:msDesc/teihdr:head/teihdr:name',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 3,
                'xpath' => "$.metadata.[?(@.label=='Author')].value",
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 1,
        'index_stored' => 1,
        'index_indexed' => 1,
        'index_boost' => 2.00,
        'is_sortable' => 1,
        'is_facet' => 1,
        'is_listed' => 1,
        'index_autocomplete' => 1,
    ),
    'place' => array (
        'format' => array (
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:sourceDesc/teihdr:msDesc/teihdr:head/teihdr:origPlace',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 3,
                'xpath' => "$.metadata.[?(@.label=='Place of publication')].value",
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 1,
        'index_stored' => 1,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 1,
        'is_facet' => 1,
        'is_listed' => 1,
        'index_autocomplete' => 0,
    ),
    'year' => array (
        'format' => array (
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:sourceDesc/teihdr:msDesc/teihdr:head/teihdr:origDate',
                'xpath_sorting' => './teihdr:fileDesc/teihdr:sourceDesc/teihdr:msDesc/teihdr:head/teihdr:origDate/@when',
            ),
            array (
                'encoded' => 3,
                'xpath' => "$.metadata.[?(@.label=='Date of publication')].value",
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 1,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 1,
        'is_facet' => 1,
        'is_listed' => 1,
        'index_autocomplete' => 0,
    ),
    'language' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:language/mods:languageTerm',
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 0,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 0,
        'is_facet' => 1,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    ),
    'collection' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:classification',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:sourceDesc/teihdr:msDesc/teihdr:msIdentifier/teihdr:collection',
                'xpath_sorting' => '',
            ),
// TODO inserting a new collection from a manifest does not work yet             
//             array (
//                 'encoded' => 3,
//                 'xpath' => "$.metadata.[?(@.label=='Collection')].value",
//                 'xpath_sorting' => '',
//             ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 1,
        'index_stored' => 0,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 0,
        'is_facet' => 1,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    ),
    'owner' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:name[./mods:role/mods:roleTerm="own"]/mods:displayForm',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:publicationStmt/teihdr:publisher',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 3,
                'xpath' => "$.metadata.[?(@.label=='Owner')].value",
                'xpath_sorting' => '',
            ),
            
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 0,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 0,
        'is_facet' => 1,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    ),
    'purl' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:identifier[@type="purl"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:publicationStmt/teihdr:idno[@type="purl"]',
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => "key.wrap = <dt>|</dt>\nvalue.required = 1\nvalue.setContentToCurrent = 1\nvalue.typolink.parameter.current = 1\nvalue.wrap = <dd>|</dd>",
        'index_tokenized' => 0,
        'index_stored' => 0,
        'index_indexed' => 0,
        'index_boost' => 1.00,
        'is_sortable' => 0,
        'is_facet' => 0,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    ),
    'urn' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:identifier[@type="urn"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:publicationStmt/teihdr:idno[@type="urn"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 3,
                'xpath' => "$.metadata.[?(@.label=='URN')].value",
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => "key.wrap = <dt>|</dt>\nvalue.required = 1\nvalue.setContentToCurrent = 1\nvalue.typolink.parameter.current = 1\nvalue.typolink.parameter.prepend = TEXT\nvalue.typolink.parameter.prepend.value = http://nbn-resolving.de/\nvalue.wrap = <dd>|</dd>",
        'index_tokenized' => 0,
        'index_stored' => 0,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 0,
        'is_facet' => 0,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    ),
    'opac_id' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:identifier[@type="opac"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:publicationStmt/teihdr:idno[@type="opac"]',
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 0,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 0,
        'is_facet' => 0,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    ),
    'union_id' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:identifier[@type="ppn"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:publicationStmt/teihdr:idno[@type="mmid"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 3,
                'xpath' => "$.metadata.[?(@.label=='Source PPN (SWB)')].value",
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 0,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 0,
        'is_facet' => 0,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    ),
    'record_id' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:recordInfo/mods:recordIdentifier',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:publicationStmt/teihdr:idno[@type="recordIdentifier"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 3,
                'xpath' => "$['@id']",
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 0,
        'index_indexed' => 1,
        'index_boost' => 1.00,
        'is_sortable' => 0,
        'is_facet' => 0,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    ),
    'prod_id' => array (
        'format' => array (
            array (
                'encoded' => 1,
                'xpath' => './mods:identifier[@type="kitodo"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 2,
                'xpath' => './teihdr:fileDesc/teihdr:publicationStmt/teihdr:idno[@type="kitodo"]',
                'xpath_sorting' => '',
            ),
            array (
                'encoded' => 3,
                'xpath' => "$.metadata.[?(@.label=='Kitodo')].value",
                'xpath_sorting' => '',
            ),
        ),
        'default_value' => '',
        'wrap' => '',
        'index_tokenized' => 0,
        'index_stored' => 0,
        'index_indexed' => 0,
        'index_boost' => 0.00,
        'is_sortable' => 0,
        'is_facet' => 0,
        'is_listed' => 0,
        'index_autocomplete' => 0,
    )
);

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

return [
    'ctrl' => [
        'title'     => 'LLL:EXT:dlf/locallang.xml:tx_dlf_structures',
        'label'     => 'label',
        'tstamp'    => 'tstamp',
        'crdate'    => 'crdate',
        'cruser_id' => 'cruser_id',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l18n_parent',
        'transOrigDiffSourceField' => 'l18n_diffsource',
        'default_sortby' => 'ORDER BY label',
        'delete'	=> 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile'	=> 'EXT:dlf/Resources/Public/Icons/txdlfstructures.png',
        'rootLevel'	=> 0,
        'dividers2tabs' => 2,
        'searchFields' => 'label,index_name,oai_name',
        'requestUpdate' => 'toplevel',
    ],
    'feInterface' => [
        'fe_admin_fieldList' => '',
    ],
    'interface' => [
        'showRecordFieldList' => 'label,index_name,oai_name,toplevel',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => [
                    ['LLL:EXT:lang/locallang_general.xml:LGL.allLanguages', -1],
                    ['LLL:EXT:lang/locallang_general.xml:LGL.default_value', 0],
                ],
                'default' => 0
            ],
        ],
        'l18n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_dlf_structures',
                'foreign_table_where' => 'AND tx_dlf_structures.pid=###CURRENT_PID### AND tx_dlf_structures.sys_language_uid IN (-1,0) ORDER BY label ASC',
            ],
        ],
        'l18n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'toplevel' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:dlf/locallang.xml:tx_dlf_structures.toplevel',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'label' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:dlf/locallang.xml:tx_dlf_structures.label',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'required,trim',
            ],
        ],
        'index_name' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:dlf/locallang.xml:tx_dlf_structures.index_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'required,nospace,alphanum_x,uniqueInPid',
            ],
        ],
        'oai_name' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:dlf/locallang.xml:tx_dlf_structures.oai_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'thumbnail' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'displayCond' => 'FIELD:toplevel:REQ:true',
            'label' => 'LLL:EXT:dlf/locallang.xml:tx_dlf_structures.thumbnail',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:dlf/locallang.xml:tx_dlf_structures.thumbnail.self', 0],
                ],
                'foreign_table' => 'tx_dlf_structures',
                'foreign_table_where' => 'AND tx_dlf_structures.pid=###CURRENT_PID### AND tx_dlf_structures.toplevel=0 AND tx_dlf_structures.sys_language_uid IN (-1,0) ORDER BY tx_dlf_structures.label',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'status' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:dlf/locallang.xml:tx_dlf_structures.status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:dlf/locallang.xml:tx_dlf_structures.status.default', 0],
                ],
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => '--div--;LLL:EXT:dlf/locallang.xml:tx_dlf_structures.tab1, toplevel;;;;1-1-1, label,--palette--;;1, thumbnail, --div--;LLL:EXT:dlf/locallang.xml:tx_dlf_structures.tab2, sys_language_uid;;;;1-1-1, l18n_parent, l18n_diffsource, --div--;LLL:EXT:dlf/locallang.xml:tx_dlf_structures.tab3, hidden;;;;1-1-1, status;;;;2-2-2'],
    ],
    'palettes' => [
        '1' => ['showitem' => 'index_name, --linebreak--, oai_name', 'canNotCollapse' => 1],
    ],
];

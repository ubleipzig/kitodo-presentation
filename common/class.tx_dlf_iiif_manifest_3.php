<?php

use Flow\JSONPath\JSONPath;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use const TYPO3\CMS\Core\Utility\GeneralUtility\SYSLOG_SEVERITY_ERROR;
use iiif\presentation\IiifHelper;
use iiif\presentation\v2\model\helper\IiifReader;
use iiif\presentation\v3\model\resources\AnnotationPage3;
use iiif\presentation\v3\model\resources\Canvas3;
use iiif\presentation\v3\model\resources\Collection3;
use iiif\presentation\v3\model\resources\ContentResource3;
use iiif\presentation\v3\model\resources\Manifest3;
use iiif\presentation\common\model\AbstractIiifEntity;
use iiif\presentation\v3\model\resources\AbstractIiifResource3;
use iiif\presentation\v3\model\resources\Range3;
use iiif\services\ImageInformation2;

class tx_dlf_iiif_manifest_3 extends tx_dlf_document
{
    protected $asJson = '';
    
    /**
     * 
     * @var Manifest3
     */
    protected $iiif;

    protected $hasFulltextSet = false;
    
    protected $fulltext = null;
    
    /**
     * This holds the original manifest's parsed metadata array with their corresponding structMap//div's ID as array key
     *
     * @var	array
     * @access protected
     */
    protected $originalMetadataArray = array ();
    
    /**
     * The extension key
     *
     * @var	string
     * @access public
     */
    public static $extKey = 'dlf';

    
    
    /**
     * {@inheritDoc}
     * @see tx_dlf_document::establishRecordId()
     */
    protected function establishRecordId()
    {
        
        if ($this->iiif !== null) {
            
            $this->recordId = $this->iiif->getId();
            
        }
            
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getDocument()
     */
    protected function getDocument()
    {
        
        return $this->iiif;
        
    }

    protected $useGrpsLoaded;
    protected $useGrps;
    
    protected function getUseGroups($use)
    {
        if (!$this->useGrpsLoaded) {
            
            // Get configured USE attributes.
            $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
            
            if (!empty($extConf['fileGrps'])) {
                
                $this->useGrps['fileGrps'] = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $extConf['fileGrps']);
                
            }
            
            if (!empty($extConf['fileGrpThumbs'])) {
                
                $this->useGrps['fileGrpThumbs'] = $extConf['fileGrpThumbs'];
                
            }
            
            if (!empty($extConf['fileGrpDownload'])) {
                
                $this->useGrps['fileGrpDownload'] = $extConf['fileGrpDownload'];
                
            }
            
            if (!empty($extConf['fileGrpFulltext'])) {
                
                $this->useGrps['fileGrpFulltext'] = $extConf['fileGrpFulltext'];
                
            }
            
            if (!empty($extConf['fileGrpAudio'])) {
                
                $this->useGrps['fileGrpAudio'] = $extConf['fileGrpAudio'];
                
            }
        }
        
        return array_key_exists($use, $this->useGrps) ? $this->useGrps[$use] : [];
    }
    
    protected function _getPhysicalStructure() {
        
        // Is there no physical structure array yet?
        if (!$this->physicalStructureLoaded) {
            
            if ($this->iiif == null || !($this->iiif instanceof Manifest3)) return null;
            
            if ($this->iiif->getItems() != null && sizeof($this->iiif->getItems()) > 0) {
                
                $pseudoSequenceId = $this->iiif->getId();
                
                $physSeq[0] = $pseudoSequenceId;
                
                $this->physicalStructureInfo[$physSeq[0]]['id'] = $pseudoSequenceId;
                
                $this->physicalStructureInfo[$physSeq[0]]['dmdId'] = $pseudoSequenceId;
                
                $this->physicalStructureInfo[$physSeq[0]]['label'] = $this->iiif->getLabelTranslated()[0];
                
                $this->physicalStructureInfo[$physSeq[0]]['orderlabel'] = $this->iiif->getLabelTranslated()[0];
                
                $this->physicalStructureInfo[$physSeq[0]]['type'] = 'phySequence';
                
                $this->physicalStructureInfo[$physSeq[0]]['contentIds'] = null;

                $canvasOrder = 0;
                
                $fileUseThumbs = $this->getUseGroups('fileGrpThumbs');
                
                $fileUses = $this->getUseGroups('fileGrps');
                
                $fileUseFulltext = $this->getUseGroups('fileGrpFulltext');
                
                $serviceProfileCache = [];
                
                foreach ($this->iiif->getItems() as $canvas) {
                    
                    $canvasOrder++;
                    
                    /* @var $canvas iiif\presentation\v3\model\resources\Canvas3 */
                    
                    $thumbnailUrl = IiifHelper::getThumbnailUrlForIiifResource($canvas, 100, null, $serviceProfileCache, GeneralUtility::class);
                    
                    // put thumbnails in thumbnail filegroup
                    if (isset($thumbnailUrl)) {
                        
                        $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseThumbs] = $thumbnailUrl;
                        
                    }
                    
                    $annoPage = $canvas->getItems()[0];
                    
                    $image = $annoPage->getItems()[0];
                    
                    /* @var $image iiif\presentation\v3\model\resources\Annotation3 */
                    
                    // put images in all non specific filegroups
                    if (isset($fileUses)) {
                        
                        foreach ($fileUses as $fileUse) {
                            
                            // $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUse] = $image->getResource()->getService()->getId();
                            
                            $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUse] = $image->getBody()->getId();
                            
                        }
                    }
                    
//                     $this->ensureHasFulltextIsSet();
                    
//                     if ($this->hasFulltext && isset($fileUseFulltext) && $canvas->getOtherContent() != null) {
                        
//                         foreach ($canvas->getOtherContent() as $annotationList) {
                            
//                             if ($annotationList->getResources() != null) {
                                
//                                 foreach ($annotationList->getResources() as $annotation) {
                                    
//                                     /* @var  $annotation \iiif\presentation\v2\model\resources\Annotation */
//                                     if ($annotation->getMotivation() == Motivation::PAINTING &&
//                                         $annotation->getBody() != null &&
//                                         $annotation->getBody()->getFormat() == "text/plain" &&
//                                         $annotation->getBody()->getChars() != null) {
                                            
//                                             $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseFulltext][] = $annotationList->getId();
                                            
//                                             $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUseFulltext][] = $annotationList->getId();
                                            
//                                             break;
                                            
//                                         }
                                        
//                                 }
                                
//                             }
                            
//                         }
                        
//                     }
                    
                    // populate structural metadata info
                    $elements[$canvasOrder] = $canvas->getId();
                    
                    $this->physicalStructureInfo[$elements[$canvasOrder]]['id']=$canvas->getId();
                    
                    // TODO check replacement
                    $this->physicalStructureInfo[$elements[$canvasOrder]]['dmdId']=null;
                    
                    $this->physicalStructureInfo[$elements[$canvasOrder]]['label']=$canvas->getLabelTranslated()[0];
                    
                    $this->physicalStructureInfo[$elements[$canvasOrder]]['orderlabel']=$canvas->getLabelTranslated()[0];
                    
                    // assume that a canvas always represents a page
                    $this->physicalStructureInfo[$elements[$canvasOrder]]['type']='page';
                    
                    $this->physicalStructureInfo[$elements[$canvasOrder]]['contentIds']=null;
                    
                    $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationLists'] = null;
                    
//                     if ($canvas->getOtherContent() != null && sizeof($canvas->getOtherContent())>0) {
                        
//                         $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationLists'] = array();
                        
//                         foreach ($canvas->getOtherContent() as $annotationList) {
                            
//                             $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationLists'][] = $annotationList->getId();
                            
//                         }
                        
//                     }
                    
                    if (isset($fileUses)) {
                        
                        foreach ($fileUses as $fileUse) {
                            
                            // $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUse] = $image->getResource()->getService()->getId();
                            
                            $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUse] = $image->getBody()->getId();
                            
                            
                        }
                    }
                    
                    if (isset($thumbnailUrl)) {
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUseThumbs] = $thumbnailUrl;
                        
                    }
                    
                    // TODO Check if it is possible to look for pdf downloads in the services and put found service urls in the download group
                    /*
                     *
                     * - should be contained in "rendering" property
                     * - format "application/pdf"
                     * - pdf for work might be contained in mainifest or default sequence; pdf for page might be in canvas or image resource
                     *
                     */
                    
                }
                
                $this->numPages = $canvasOrder;
                
                // Merge and re-index the array to get nice numeric indexes.
                $this->physicalStructure = array_merge($physSeq, $elements);
                
            }
            
            
            $this->physicalStructureLoaded = true;
            
        }
            
        return $this->physicalStructure;
        
    }

    protected function init() {
        
        // nothing to do
        
    }

    protected function ensureHasFulltextIsSet() {
        
    }
    
    protected static function &getIiif3Instance($uid, $pid = 0, $forceReload = FALSE) {
        
        if (!class_exists('\\iiif\\model\\resources\\IiifReader', false)) {
            
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/AccessHelper.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/JSONPath.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/JSONPathException.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/JSONPathLexer.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/JSONPathToken.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/Filters/AbstractFilter.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/Filters/IndexesFilter.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/Filters/IndexFilter.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/Filters/QueryMatchFilter.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/Filters/QueryResultFilter.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/Filters/RecursiveFilter.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/JSONPath/Flow/JSONPath/Filters/SliceFilter.php'));
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/php-iiif-manifest-reader/iiif/classloader.php'));
        }
        
        // Sanitize input.
        $pid = max(intval($pid), 0);
        
        // Create new instance...
        $instance = new self($uid, $pid);
        
        // Return new instance.
        return $instance;
    }

    public function getMetadata($id, $cPid = 0) {
        
        if (!empty($this->metadataArray[$id]) && $this->metadataArray[0] == $cPid) {
            
            return $this->metadataArray[$id];
            
        }
        
        // Initialize metadata array with empty values.
        // TODO initialize metadata in abstract class
        $metadata = array (
            'title' => array (),
            'title_sorting' => array (),
            'author' => array (),
            'place' => array (),
            'year' => array (),
            'prod_id' => array (),
            'record_id' => array (),
            'opac_id' => array (),
            'union_id' => array (),
            'urn' => array (),
            'purl' => array (),
            'type' => array (),
            'volume' => array (),
            'volume_sorting' => array (),
            'collection' => array (),
            'owner' => array (),
        );
        
        $metadata['document_format'][] = 'IIIF';
        
        $iiifResource = $this->iiif->getContainedResourceById($id);
        
//         $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
//             'tx_dlf_metadata.index_name AS index_name,tx_dlf_metadataformat.xpath AS xpath,tx_dlf_metadataformat.xpath_sorting AS xpath_sorting,tx_dlf_metadata.is_sortable AS is_sortable,tx_dlf_metadata.default_value AS default_value,tx_dlf_metadata.format AS format',
//             'tx_dlf_metadata,tx_dlf_metadataformat,tx_dlf_formats',
//             'tx_dlf_metadata.pid='.$cPid.' AND tx_dlf_metadataformat.pid='.$cPid.' AND ((tx_dlf_metadata.uid=tx_dlf_metadataformat.parent_id AND tx_dlf_metadataformat.encoded=tx_dlf_formats.uid AND tx_dlf_formats.type='.$GLOBALS['TYPO3_DB']->fullQuoteStr('IIIF3', 'tx_dlf_formats').') OR tx_dlf_metadata.format=0)'.tx_dlf_helper::whereClause('tx_dlf_metadata', TRUE).tx_dlf_helper::whereClause('tx_dlf_metadataformat').tx_dlf_helper::whereClause('tx_dlf_formats'),
//             '',
//             '',
//             ''
//             );
        
//         $iiifResource = $this->iiif->getContainedResourceById($id);
        
//         while ($resArray = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
            
//             // Set metadata field's value(s).
//             if ($resArray['format'] > 0 && !empty($resArray['xpath']) && ($values = $iiifResource->jsonPath($resArray['xpath'])) != null) {
                
//                 if (is_string($values)) {
                    
//                     $metadata[$resArray['index_name']] = array (trim((string) $values));
                    
//                 } elseif ($values instanceof JSONPath && is_array($values->data()) && count($values->data()>1 )) {
                    
//                     $metadata[$resArray['index_name']] = array ();
                    
//                     foreach ($values->data() as $value) {
                        
//                         $metadata[$resArray['index_name']][] = trim((string) $value);
                        
//                     }
                    
//                 }
                
//             }
            
//             // Set default value if applicable.
//             // '!empty($resArray['default_value'])' is not possible, because '0' is a valid default value.
//             // Setting an empty default value creates a lot of empty fields within the index.
//             // These empty fields are then shown within the search facets as 'empty'.
//             if (empty($metadata[$resArray['index_name']][0]) && strlen($resArray['default_value']) > 0) {
                
//                 $metadata[$resArray['index_name']] = array ($resArray['default_value']);
                
//             }
            
//             // Set sorting value if applicable.
//             if (!empty($metadata[$resArray['index_name']]) && $resArray['is_sortable']) {
                
//                 if ($resArray['format'] > 0 && !empty($resArray['xpath_sorting']) && ($values = $iiifResource->jsonPath($resArray['xpath_sorting']) != null)) {
                    
                    
//                     if ($values instanceof string) {
                        
//                         $metadata[$resArray['index_name'].'_sorting'][0] = array (trim((string) $values));
                        
//                     } elseif ($values instanceof JSONPath && is_array($values->data()) && count($values->data()>1 )) {
                        
//                         $metadata[$resArray['index_name']] = array ();
                        
//                         foreach ($values->data() as $value) {
                            
//                             $metadata[$resArray['index_name'].'_sorting'][0] = trim((string) $value);
                            
//                         }
                        
//                     }
                    
//                 }
                
//                 if (empty($metadata[$resArray['index_name'].'_sorting'][0])) {
                    
//                     $metadata[$resArray['index_name'].'_sorting'][0] = $metadata[$resArray['index_name']][0];
                    
//                 }
                
//             }
            
//         }
        
        if ($iiifResource != null) {

            // TODO for every metadatum: translation; multiple values; configuration

            $this->fillMetadataFromArray($metadata, 'title', $iiifResource->getLabelTranslated());

            $this->fillMetadataFromArray($metadata, 'author', $iiifResource->getMetadataForLabel('Author'));
            
            $this->fillMetadataFromArray($metadata, 'place', $iiifResource->getMetadataForLabel('Place'));
            
            $this->fillMetadataFromArray($metadata, 'place_sorting', $iiifResource->getMetadataForLabel('Place'));
            
            $this->fillMetadataFromArray($metadata, 'year', $iiifResource->getMetadataForLabel('Date'));
            
            $this->fillMetadataFromArray($metadata, 'year_sorting', $iiifResource->getMetadataForLabel('Date'));
            
            $this->fillMetadataFromArray($metadata, 'prod_id', $iiifResource->getMetadataForLabel('Kitodo'));
            
            $metadata['record_id'][] = $iiifResource->getId();
            
            $this->fillMetadataFromArray($metadata, 'union_id', $iiifResource->getMetadataForLabel('Source PPN (SWB)'));
            
//             $metadata['title'][] = $iiifResource->getLabelTranslated();
            
//             $metadata['author'][] = $iiifResource->getMetadataForLabel('Author');

//             $metadata['place'][] = $iiifResource->getMetadataForLabel('Place');

//             $metadata['place_sorting'][] = $iiifResource->getMetadataForLabel('Place', null, ', ');

//             $metadata['year'][] = $iiifResource->getMetadataForLabel('Date', null, ', ');

//             $metadata['year_sorting'][] = $iiifResource->getMetadataForLabel('Date', null, ', ');

//             $metadata['prod_id'][] = $iiifResource->getMetadataForLabel('Kitodo', null, ', ');

//             $metadata['record_id'][] = $iiifResource->getId();

//             $metadata['union_id'][] = $iiifResource->getMetadataForLabel('Source PPN (SWB)', null, ', ');

            // $metadata['collection'][] = $this->iiif->getMetadataForLabel('Collection');

            $metadata['owner'][] = $iiifResource->getMetadataForLabel('Owner', null, ', ');

            $type = $iiifResource->getMetadataForLabel('Manifest Type', null, '');
            
            if ($type==null) {
                
                $type = $iiifResource->getMetadataForLabel('metsType', null, '');
                
            }
            
            $metadata['type'][] = $type;
            
        }
        
        // TODO use configuration

        return $metadata;
        
    }
    
    private function fillMetadataFromArray(&$metadata, $key, $value) {
        
        if (is_array($value)) {
            
            foreach ($value as $v) {
                
                $metadata[$key][] = $v;
                
            }
            
        } else {
            
            $metadata[$key][] = $value;
            
        }
        
    }

    public function getFileMimeType($id){
        
        $fileResource = $this->iiif->getContainedResourceById($id);
        
        if ($fileResource instanceof Canvas3 || $fileResource instanceof ContentResource3 || $fileResource instanceof ImageInformation2) {
            
            $format = "application/vnd.kitodo.iiif";
            
        } else {
            
            // Assumptions: this can only be the thumbnail and the thumbnail is a jpeg - TODO determine mimetype
            $format = "image/jpeg";
            
        }
        
        return $format;
        
    }

    public function getLogicalStructure($id, $recursive = FALSE) {
        
        $details = array ();
        
        if (!$recursive && !empty($this->logicalUnits[$id])) {
            
            return $this->logicalUnits[$id];
            
        } elseif (!empty($id)) {
            
            $logUnits[] = $this->iiif->getContainedResourceById($id);
               
        } else {
            
            $logUnits[] = $this->iiif;
            
        }
        
        if (!empty($logUnits)) {
            
            if (!$recursive) {
                
                $details = $this->getLogicalStructureInfo($logUnits[0]);
                
            } else {
                
                foreach ($logUnits as $logUnit) {

                    $this->tableOfContents[] = $this->getLogicalStructureInfo($logUnit, TRUE);
                        
                }
                
            }
            
        }
        
        return $details;
    
    }

    protected function getLogicalStructureInfo(AbstractIiifEntity $resource, $recursive = false) {
        
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
        
        $details = array ();
        
        $details['id'] = $resource->getId();
        
        $details['dmdId'] = '';
        
        $details['label'] = $resource->getLabelTranslated() !== null ? $resource->getLabelTranslated()[0] : '';
        
        $details['orderlabel'] = $resource->getLabelTranslated() !== null ? $resource->getLabelTranslated()[0] : '';
        
        $details['contentIds'] = '';
        
        // TODO set volume information?
        $details['volume'] = '';
        
        $details['pagination'] = '';
        
        $cPid = ($this->cPid ? $this->cPid : $this->pid);
        
        if ($details['id'] == $this->_getToplevelId()) {
            
            $metadata = $this->getMetadata($details['id'], $cPid);
            
            if (!empty($metadata['type'][0])) {
                
                $details['type'] = $metadata['type'][0];
                
            }
            
        }
        
        $dummy = array();
        
        $details['thumbnailId'] = IiifHelper::getThumbnailUrlForIiifResource($resource, 100, null, $dummy, GeneralUtility::class);
        
        $details['points'] = '';
        
        // Load strucural mapping
        $this->_getSmLinks();
        
        // Load physical structure.
        $this-> _getPhysicalStructure();
        
        $canvases = array();
        
        $startCanvas = IiifHelper::getStartCanvasOrFirstCanvas($resource);
        
        if ($resource instanceof Manifest3) {
            
            $canvases = $resource->getItems();
            
        } elseif ($resource instanceof Range3) {
            
            $canvases = $resource->getAllCanvases();
            
        }
        
        if ($startCanvas != null) {
            
            $details['pagination'] = $startCanvas->getLabel()[0];
            
            $startCanvasIndex = array_search($startCanvas, $this->iiif->getItems());
            
            if ($startCanvasIndex!==false) {
                
                $details['points'] = $startCanvasIndex + 1;
                
            }
            
        }
        
        $useGroups = $this->getUseGroups('fileGrps');
        
        if (is_string($useGroups)) {
            
            $useGroups = array($useGroups);
            
        }
        
        if (isset($canvases) && sizeof($canvases)>0) {
            
            foreach ($canvases as $canvas) {
                
                foreach ($useGroups as $fileUse) {
                    
                    //                    $details['files'][$fileUse[]]  ;
                    
                }
                
            }
            
        }
        
        // Keep for later usage.
        $this->logicalUnits[$details['id']] = $details;
        
        // Walk the structure recursively? And are there any children of the current element?
        if ($recursive) {
            
            $details['children'] = array ();
            
            if ($resource instanceof Manifest3 && $resource->getStructures()!=null) {
                
                foreach ($resource->getStructures() as $range)
                {
                    
                    $details['children'][] = $this->getLogicalStructureInfo($range, TRUE);

                }
                
            } elseif ($resource instanceof Range3) {
                
                if ($resource->getItems() !== null && sizeof($resource->getItems())>0) {
                    
                    foreach ($resource->getItems() as $item) {
                        
                        if ($item instanceof Range3) {
                            
                            $details['children'][] = $this->getLogicalStructureInfo($item, TRUE);
                            
                        }
                        
                    }
                    
                }
                
            }
            
        }
        
        return $details;
        
    }
    
    protected function _getSmLinks() {
        
        if (!$this->smLinksLoaded && isset($this->iiif) && $this->iiif instanceof Manifest3) {
            
            if ($this->iiif->getItems()!==null && sizeof($this->iiif->getItems())>0) {
                
                $manifestCanvases = $this->iiif->getItems();
                
                foreach ($manifestCanvases as $canvas) {
                    
                    $this->smLinkCanvasToResource($canvas, $this->iiif);
                    
                }
                
            }
            
            if ($this->iiif->getStructures() !=null && sizeof($this->iiif->getStructures())>0) {
                
                foreach ($this->iiif->getStructures() as $range) {
                    
                    $this->smLinkRangeCanvasesRecursively($range);
                    
                }
                
            }
            
            $this->smLinksLoaded = true;
            
        }
        
        return $this->smLinks;
        
    }
    
    private function smLinkRangeCanvasesRecursively(Range3 $range) {
        
        // map range's canvases including all child ranges' canvases
        
        foreach ($range->getAllCanvases() as $canvas) {
            
            if ($canvas == null) {
                
                // just for breakpoint
                echo "soso";
                
            }
            
            $this->smLinkCanvasToResource($canvas, $range);
            
        }
        
        // iterate through members and map all member canvases, call self for all member ranges
        
        if ($range->getItems()!==null && sizeof($range->getItems())>0) {
            
            foreach ($range->getItems() as $item) {
                
                if ($item instanceof Canvas3) {
                    
                    $this->smLinkCanvasToResource($item, $range);
                    
                }
                
                if ($item instanceof Range3) {
                    
                    $this->smLinkRangeCanvasesRecursively($item);
                    
                }
                
                // TODO $item might be a specific resource
                
            }
            
        }
        
    }
    
    private function smLinkCanvasToResource(Canvas3 $canvas, AbstractIiifResource3 $resource)
    {
        
        $this->smLinks['l2p'][$resource->getId()][] = $canvas->getId();
        
        if (!is_array($this->smLinks['p2l'][$canvas->getId()]) || !in_array($resource->getId(), $this->smLinks['p2l'][$canvas->getId()])) {
            
            $this->smLinks['p2l'][$canvas->getId()][] = $resource->getId();
            
        }
        
    }
    
    
    protected function saveParentDocumentIfExists() {
        
        // TODO collection?
        
    }

    protected function prepareMetadataArray($cPid) {
        
        $id = $this->iiif->getId();
        
        $this->metadataArray[(string) $id] = $this->getMetadata((string) $id, $cPid);
    
    }

    public function getFileLocation($id) {
        
        if ($id == null) return null;
        
        $resource = $this->iiif != null ? $this->iiif->getContainedResourceById($id) : $this->iiif3->getContainedResourceById($id);
        
        if (isset($resource)) {
            
            if ($resource instanceof Canvas3) {
                
                return $resource->getImageAnnotationsForDisplay()[0]->getBody()->getService()->getId();
                
            } elseif ($resource instanceof ContentResource3) {
                
                return $resource->getService()[0]->getId();
                
            } elseif ($resource instanceof AnnotationPage3) {
                
                return $id;
                
            }
            
        } else {
            
            return $id;
            
        }
        
    }

    protected function loadLocation($location) {
        
        $content = GeneralUtility::getUrl($location);
        
        //$resource = IiifReader::getIiifResourceFromJsonString($content);
        $resource = IiifHelper::loadIiifResource($content);
        
        if ($resource != null ){
            
            if ($resource instanceof Manifest3 || $resource instanceof Collection3) {
                
                $this->iiif = $resource;
                
                return true;
                
            }
            
        } else {
            if (TYPO3_DLOG) {
                
                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_dlf_iiif_manifest->loadLocation('.$location.')] Could not load IIIF manifest from "'.$location.'"', self::$extKey, SYSLOG_SEVERITY_ERROR);
                
            }
        }
        
    }

    protected function _getToplevelId() {
        
        if (empty($this->toplevelId)) {
            
            if (isset($this->iiif)) {
                
                $this->toplevelId = $this->iiif->getId();
                
            }
    
        }
        
        return $this->toplevelId;

    }

    public function getRawText($id) {
        
        // TODO
        
    }
    

    protected function loadFormats() {
        
        // METS specific
        
    }

    
    
}


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

use Flow\JSONPath\JSONPath;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use const TYPO3\CMS\Core\Utility\GeneralUtility\SYSLOG_SEVERITY_ERROR;
use const TYPO3\CMS\Core\Utility\GeneralUtility\SYSLOG_SEVERITY_WARNING;
use iiif\presentation\IiifHelper;
use iiif\presentation\common\model\resources\AnnotationInterface;
use iiif\presentation\common\model\resources\CanvasInterface;
use iiif\presentation\common\model\resources\ContentResourceInterface;
use iiif\presentation\common\model\resources\IiifResourceInterface;
use iiif\presentation\common\model\resources\ManifestInterface;
use iiif\presentation\common\model\resources\RangeInterface;
use iiif\presentation\v2\model\resources\AnnotationList;
use iiif\presentation\v2\model\resources\Canvas;
use iiif\presentation\v2\model\resources\Collection;
use iiif\presentation\v2\model\vocabulary\Motivation;
use iiif\presentation\v2\model\vocabulary\Types;
use iiif\services\AbstractImageService;

class tx_dlf_iiif_manifest extends tx_dlf_document
{
    /**
     * This holds the manifest file as string for serialization purposes
     * @see __sleep() / __wakeup()
     *
     * @var	string
     * @access protected
     */
    protected $asJson = '';

    /**
     * 
     * @var ManifestInterface
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
    
    protected $mimeTypes = [];
    
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

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::_getPhysicalStructure()
     */
    protected function _getPhysicalStructure()
    {
        // Is there no physical structure array yet?
        if (!$this->physicalStructureLoaded) {
            
            if ($this->iiif == null || !($this->iiif instanceof ManifestInterface)) return null;
            
            if ($this->iiif != null) {
                
                $iiifId = $this->iiif->getId();
                
                $physSeq[0] = $iiifId;
                
                $this->physicalStructureInfo[$physSeq[0]]['id'] = $iiifId;
                
                $this->physicalStructureInfo[$physSeq[0]]['dmdId'] = $iiifId;
                
                // TODO translation?
                $this->physicalStructureInfo[$physSeq[0]]['label'] = $this->iiif->getLabelForDisplay();
                
                // TODO translation?
                $this->physicalStructureInfo[$physSeq[0]]['orderlabel'] = $this->iiif->getLabelForDisplay();
                
                $this->physicalStructureInfo[$physSeq[0]]['type'] = 'physSequence';
                
                // TODO check nescessity
                $this->physicalStructureInfo[$physSeq[0]]['contentIds'] = null;
                
                $fileUseDownload = $this->getUseGroups('fileGrpDownload');
                
                if (isset($fileUseDownload)) {
                    
                    $docPdfRendering = $this->iiif->getRenderingUrlsForFormat('application/pdf');
                    
                    if (!empty($docPdfRendering)) {
                        
                        $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseDownload] = $docPdfRendering[0];
                        
                    }
                    
                }
                
                if (!empty($this->iiif->getDefaultCanvases())) {
                    
                    // canvases have not order property, but the context defines canveses as @list with a specific order, so we can provide an alternative
                    $canvasOrder = 0;
                    
                    $fileUseThumbs = $this->getUseGroups('fileGrpThumbs');
                    
                    $fileUses = $this->getUseGroups('fileGrps');
                    
                    $fileUseFulltext = $this->getUseGroups('fileGrpFulltext');
                    
                    $serviceProfileCache = [];
                    
                    foreach ($this->iiif->getDefaultCanvases() as $canvas) {
                        
                        $canvasOrder++;
                        
                        $thumbnailUrl = $canvas->getThumbnailUrl();
                        
                        // put thumbnails in thumbnail filegroup
                        if (isset($thumbnailUrl)) {
                            
                            $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseThumbs] = $thumbnailUrl;
                            
                        }
                        
                        $image = $canvas->getImageAnnotations()[0];
                        
                        // put images in all non specific filegroups
                        if (isset($fileUses)) {
                            
                            foreach ($fileUses as $fileUse) {
                                
                                // $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUse] = $image->getResource()->getService()->getId();
                                
                                $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUse] = $image->getBody()->getId();
                                
                            }
                        }
                        
                        $this->ensureHasFulltextIsSet();

//                             if ($this->hasFulltext && isset($fileUseFulltext) && $canvas->getOtherContent() != null) {
                                
//                                 foreach ($canvas->getOtherContent() as $annotationList) {
                                    
//                                     if ($annotationList->getResources() != null) {
                                        
//                                         foreach ($annotationList->getResources() as $annotation) {
                                            
//                                             /* @var  $annotation \iiif\presentation\v2\model\resources\Annotation */
//                                             if ($annotation->getMotivation() == Motivation::PAINTING &&
//                                                 $annotation->getResource() != null &&
//                                                 $annotation->getResource()->getFormat() == "text/plain" &&
//                                                 $annotation->getResource()->getChars() != null) {
                                                    
//                                                     $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseFulltext][] = $annotationList->getId();
                                                    
//                                                     $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUseFulltext][] = $annotationList->getId();
                                                    
//                                                     break;
                                                    
//                                                 }
                                                
//                                         }
                                        
//                                     }
                                    
//                                 }
                                
//                             }
                            
                        // populate structural metadata info
                        $elements[$canvasOrder] = $canvas->getId();
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['id']=$canvas->getId();
                        
                        // TODO check replacement
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['dmdId']=null;
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['label']=$canvas->getLabelForDisplay();
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['orderlabel']=$canvas->getLabelForDisplay();
                        
                        // assume that a canvas always represents a page
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['type']='page';
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['contentIds']=null;
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationLists'] = null;
                        
                        // TODO abstraction for AnnotationList / AnnotationPage
                        if ($canvas instanceof Canvas && $canvas->getOtherContent() != null && sizeof($canvas->getOtherContent())>0) {
                            
                            $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationLists'] = array();
                            
                            foreach ($canvas->getOtherContent() as $annotationList) {
                                
                                $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationLists'][] = $annotationList->getId();
                                
                            }
                            
                        }
                        
                        if (isset($fileUseFulltext)) {
                            
                            $alto = $canvas->getSeeAlsoUrlsForFormat("application/alto+xml");
                            
                            if (empty($alto)) {
                                
                                $alto = $canvas->getSeeAlsoUrlsForProfile("http://www.loc.gov/standards/alto/", true);
                                
                            }
                            
                            if (!empty($alto)) {
                                
                                // FIXME use all possible alto files?
                                
                                $this->mimeTypes[$alto[0]] = "application/alto+xml";
                                
                                $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseFulltext] = $alto[0];
                                
                                $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUseFulltext] = $alto[0];
                                
                            }
                        
                        }
                        
                        
                        if (isset($fileUses)) {
                            
                            foreach ($fileUses as $fileUse) {
                                
                                // $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUse] = $image->getResource()->getService()->getId();
                                
                                $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUse] = $image->getBody()->getId();
                                
                            }
                        }
                        
                        if (isset($thumbnailUrl)) {
                            
                            $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUseThumbs] = $thumbnailUrl;
                            
                        }
                        
                        if (isSet($fileUseDownload)) {
                            
                            $pdfRenderingUrls = $canvas->getRenderingUrlsForFormat('application/pdf');
                            
                            if (!empty($pdfRenderingUrls)) {
                                
                                $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUseDownload] = $pdfRenderingUrls[0];

                            }
                            
                        }
                        
                    }
                    
                    $this->numPages = $canvasOrder;
                    
                    // Merge and re-index the array to get nice numeric indexes.
                    $this->physicalStructure = array_merge($physSeq, $elements);
                    
                }
                
            }
            
            $this->physicalStructureLoaded = TRUE;
            
        }

        return $this->physicalStructure;

    }

    public function getDownloadLocation($id) {
        
        $fileLocation = $this->getFileLocation($id);

        $resource = $this->iiif->getContainedResourceById($fileLocation);
         
        if ($resource instanceof AbstractImageService) {
            
            return $resource->getImageUrl();
            
        }
        
        return $fileLocation;
        
    }
    
    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getFileLocation()
     */
    public function getFileLocation($id)
    {
        
        if ($id == null) return null;
        
        $resource = $this->iiif->getContainedResourceById($id);
        
        if (isset($resource)) {
            
            if ($resource instanceof CanvasInterface) {
                
                return $resource->getImageAnnotations()[0]->getSingleService()->getId();
                
            } elseif ($resource instanceof ContentResourceInterface) {
                
                return $resource->getSingleService()->getId();
                
            } elseif ($resource instanceof AbstractImageService) {
                
                return $resource->getId();
                
            } elseif ($resource instanceof AnnotationList) {
                
                return $id;
                
            }
            
        } else {
            
            return $id;
            
        }
        
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getFileMimeType()
     */
    public function getFileMimeType($id)
    {
        $fileResource = $this->iiif->getContainedResourceById($id);
        
        if ($fileResource instanceof CanvasInterface) {
            
            // $format = $fileResource->getImages()[0]->getResource()->getFormat();
            
            $format = "application/vnd.kitodo.iiif";
            
        } elseif ($fileResource instanceof AnnotationInterface) {
            
            // $format = $fileResource->getResource()->getFormat();
            
            $format = "application/vnd.kitodo.iiif";
            
            
        } elseif ($fileResource instanceof ContentResourceInterface) {
            
            // $format = $fileResource->getFormat();
            
            $format = "application/vnd.kitodo.iiif";
            
        } elseif ($fileResource instanceof AbstractImageService) {
            
            // $format = $fileResource->getFormat();
            
            $format = "application/vnd.kitodo.iiif";
            
        } else {

            // Assumptions: this can only be the thumbnail and the thumbnail is a jpeg - TODO determine mimetype
            $format = "image/jpeg";
            
        }
        
        return $format;
    }
    
    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getLogicalStructure()
     */
    public function getLogicalStructure($id, $recursive = FALSE)
    {
        
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
                
                // cache the ranges - they might occure multiple times in the structures "tree" - with full data as well as referenced as id
                $processedStructures = array();
                
                foreach ($logUnits as $logUnit) {
                    
                    if (array_search($logUnit->getId(), $processedStructures) == false) {
                        
                        $this->tableOfContents[] = $this->getLogicalStructureInfo($logUnit, TRUE, $processedStructures);
                        
                    }
                    
                }
                
            }
            
        }
        
        return $details;
        
    }
    
    
    protected function getLogicalStructureInfo(IiifResourceInterface $resource, $recursive = false, &$processedStructures = array()) {
        
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
        
        $details = array ();
        
        $details['id'] = $resource->getId();
        
        $details['dmdId'] = '';
        
        $details['label'] = $resource->getLabelForDisplay() !== null ? $resource->getLabelForDisplay() : '';
        
        $details['orderlabel'] = $resource->getLabelForDisplay() !== null ? $resource->getLabelForDisplay() : '';

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
        
        $details['thumbnailId'] = $resource->getThumbnailUrl();
        
        $details['points'] = '';

        // Load strucural mapping
        $this->_getSmLinks();
        
        // Load physical structure.
        $this-> _getPhysicalStructure();
        
        $canvases = array();
        
        if ($resource instanceof ManifestInterface) {

            $startCanvas = $resource->getStartCanvasOrFirstCanvas();
            
            $canvases = $resource->getDefaultCanvases();
            
        } elseif ($resource instanceof RangeInterface) {
            
            $startCanvas = $resource->getStartCanvasOrFirstCanvas();
            
            $canvases = $resource->getAllCanvases();
            
        }
        
        if ($startCanvas != null) {
            
            $details['pagination'] = $startCanvas->getLabel();
            
            $startCanvasIndex = array_search($startCanvas, $this->iiif->getDefaultCanvases());
            
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
            
            $processedStructures[] = $resource->getId();
            
            $details['children'] = array ();
            
            if ($resource instanceof ManifestInterface && $resource->getRootRanges() != null) {

                $rangesToAdd = [];
                
                $rootRanges = [];
                
                if (sizeof($this->iiif->getRootRanges()) == 1 && $this->iiif->getRootRanges()[0]->isTopRange()) {
                    
                    $rangesToAdd = $this->iiif->getRootRanges()[0]->getMemberRangesAndRanges();
                    
                } else {
                    
                    $rangesToAdd = $this->iiif->getRootRanges();
                    
                }
                
                foreach ($rangesToAdd as $range) {
                    
                    $rootRanges[] = $range;
                    
                }
                
                foreach ($rootRanges as $range)
                {

                    if ((array_search($range->getId(), $processedStructures) == false)) {
                        
                        $details['children'][] = $this->getLogicalStructureInfo($range, TRUE, $processedStructures);
                        
                    }
                    
                }
                
            } elseif ($resource instanceof RangeInterface) {
                
                if (!empty($resource->getAllRanges())) {

                    foreach ($resource->getAllRanges() as $range) {
                        
                        if ((array_search($range->getId(), $processedStructures) == false)) {
                            
                            $details['children'][] = $this->getLogicalStructureInfo($range, TRUE, $processedStructures);
                            
                        }
                        
                    }
                    
                }
                
            }
            
        }
        
        return $details;
        
    }
    
    
    public function getManifestMetadata($id, $cPid = 0) {
        
        if (!empty($this->originalMetadataArray[$id])) {
            
            return $this->metadataArray[$id];
            
        }
        
        $iiifResource = $this->iiif->getContainedResourceById($id);
        
        $result = array();
        
        if ($iiifResource != null) {
            
            if ($iiifResource->getLabel()!=null && $iiifResource->getLabel() != "") {
                
                $result['Label'] = $iiifResource->getLabel();
                
            }
            
            if ($iiifResource->getMetadata() != null) {
                
                foreach ($iiifResource->getMetadata() as $metadatum)  {
                    
                    // TODO abstraction
                    $result[$metadatum['label']] = $iiifResource->getMetadataForLabel($metadatum['label']);
                    
                }
                
            }
            
        }
        
        return $result;
        
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getMetadata()
     */
    public function getMetadata($id, $cPid = 0)
    {
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

        $metadata['document_format'][] = 'IIIF2';
        
        $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
            'tx_dlf_metadata.index_name AS index_name,tx_dlf_metadataformat.xpath AS xpath,tx_dlf_metadataformat.xpath_sorting AS xpath_sorting,tx_dlf_metadata.is_sortable AS is_sortable,tx_dlf_metadata.default_value AS default_value,tx_dlf_metadata.format AS format',
            'tx_dlf_metadata,tx_dlf_metadataformat,tx_dlf_formats',
            'tx_dlf_metadata.pid='.$cPid.' AND tx_dlf_metadataformat.pid='.$cPid.' AND ((tx_dlf_metadata.uid=tx_dlf_metadataformat.parent_id AND tx_dlf_metadataformat.encoded=tx_dlf_formats.uid AND tx_dlf_formats.type='.$GLOBALS['TYPO3_DB']->fullQuoteStr('IIIF2', 'tx_dlf_formats').') OR tx_dlf_metadata.format=0)'.tx_dlf_helper::whereClause('tx_dlf_metadata', TRUE).tx_dlf_helper::whereClause('tx_dlf_metadataformat').tx_dlf_helper::whereClause('tx_dlf_formats'),
            '',
            '',
            ''
            );
        
        $iiifResource = $this->iiif->getContainedResourceById($id);

        while ($resArray = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
            
            // Set metadata field's value(s).
            if ($resArray['format'] > 0 && !empty($resArray['xpath']) && ($values = $iiifResource->jsonPath($resArray['xpath'])) != null) {
                
                if (is_string($values)) {
                    
                    $metadata[$resArray['index_name']] = array (trim((string) $values));
                    
                } elseif ($values instanceof JSONPath && is_array($values->data()) && count($values->data()>1 )) {
                    
                    $metadata[$resArray['index_name']] = array ();
                    
                    foreach ($values->data() as $value) {
                        
                        $metadata[$resArray['index_name']][] = trim((string) $value);
                        
                    }
                    
                }
                
            }
            
            // Set default value if applicable.
            // '!empty($resArray['default_value'])' is not possible, because '0' is a valid default value.
            // Setting an empty default value creates a lot of empty fields within the index.
            // These empty fields are then shown within the search facets as 'empty'.
            if (empty($metadata[$resArray['index_name']][0]) && strlen($resArray['default_value']) > 0) {
                
                $metadata[$resArray['index_name']] = array ($resArray['default_value']);
                
            }
            
            // Set sorting value if applicable.
            if (!empty($metadata[$resArray['index_name']]) && $resArray['is_sortable']) {
                
                if ($resArray['format'] > 0 && !empty($resArray['xpath_sorting']) && ($values = $iiifResource->jsonPath($resArray['xpath_sorting']) != null)) {
                    
                    
                    if ($values instanceof string) {
                        
                        $metadata[$resArray['index_name'].'_sorting'][0] = array (trim((string) $values));
                        
                    } elseif ($values instanceof JSONPath && is_array($values->data()) && count($values->data()>1 )) {
                        
                        $metadata[$resArray['index_name']] = array ();
                        
                        foreach ($values->data() as $value) {
                            
                            $metadata[$resArray['index_name'].'_sorting'][0] = trim((string) $value);
                            
                        }
                        
                    }
                    
                }
                
                if (empty($metadata[$resArray['index_name'].'_sorting'][0])) {
                    
                    $metadata[$resArray['index_name'].'_sorting'][0] = $metadata[$resArray['index_name']][0];
                    
                }
                
            }
            
        }
        
        
//         if ($this->iiif instanceof Manifest) {
            
//             // TODO for every metadatum: translation; multiple values; configuration 
            
//             $metadata['author'][] = $this->iiif->getMetadataForLabel('Author');
            
//             $metadata['place'][] = $this->iiif->getMetadataForLabel('Place of publication');
            
//             $metadata['place_sorting'][] = $this->iiif->getMetadataForLabel('Place of publication');
           
//             $metadata['year'][] = $this->iiif->getMetadataForLabel('Date of publication');
            
//             $metadata['year_sorting'][] = $this->iiif->getMetadataForLabel('Date of publication');
            
//             $metadata['prod_id'][] = $this->iiif->getMetadataForLabel('Kitodo');
            
//             $metadata['record_id'][] = $this->recordId;
            
//             $metadata['union_id'][] = $this->iiif->getMetadataForLabel('Source PPN (SWB)');
            
//             // $metadata['collection'][] = $this->iiif->getMetadataForLabel('Collection');
            
//             $metadata['owner'][] = $this->iiif->getMetadataForLabel('Owner');
            
//         }
        
        // TODO use configuration
        
        return $metadata;
        
    }
    
    protected function _getSmLinks() {
        
        if (!$this->smLinksLoaded && isset($this->iiif) && $this->iiif instanceof ManifestInterface) {
            
            if (!empty($this->iiif->getDefaultCanvases())) {
                
                foreach ($this->iiif->getDefaultCanvases() as $canvas) {
                    
                    $this->smLinkCanvasToResource($canvas, $this->iiif);
                    
                }
                
            }
            
            if (!empty($this->iiif->getStructures())) {
                
                foreach ($this->iiif->getStructures() as $range) {
                    
                    $this->smLinkRangeCanvasesRecursively($range);
                    
                }

            }
            
            $this->smLinksLoaded = true;
        
        }
        
        return $this->smLinks;
            
    }
    
    private function smLinkRangeCanvasesRecursively(RangeInterface $range) {
        
        // map range's canvases including all child ranges' canvases
        
        if (!$range->isTopRange()) {
            
            foreach ($range->getAllCanvasesRecursively() as $canvas) {
                
                $this->smLinkCanvasToResource($canvas, $range);
                
            }
            
        }
        
        // recursive call for all ranges
        
        if (!empty($range->getAllRanges())) {
            
            foreach ($range->getAllRanges() as $childRange) {
                
                $this->smLinkRangeCanvasesRecursively($childRange);
                
            }
                
        }
        
    }
    
    private function smLinkCanvasToResource(CanvasInterface $canvas, IiifResourceInterface $resource)
    {
        
        $this->smLinks['l2p'][$resource->getId()][] = $canvas->getId();

        if (!is_array($this->smLinks['p2l'][$canvas->getId()]) || !in_array($resource->getId(), $this->smLinks['p2l'][$canvas->getId()])) {
            
            $this->smLinks['p2l'][$canvas->getId()][] = $resource->getId();
            
        }

    }
        
    
    

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::loadFormats()
     */
    protected function loadFormats()
    {
        // do nothing - METS specific
        
    }
    
    protected function saveParentDocumentIfExists()
    {
        // Do nothing
        // TODO Check if Collection doc needs to be saved
    }

    public function getRawText($id) {
        
        $rawText = '';
        
        // Get text from raw text array if available.
        if (!empty($this->rawTextArray[$id])) {
            
            return $this->rawTextArray[$id];
            
        }
        
        $this->ensureHasFulltextIsSet();
        
        if ($this->hasFulltext) {
            
            // Load physical structure ...
            $this->_getPhysicalStructure();
            
            // ... and extension configuration.
            $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
            
            if (!empty($this->physicalStructureInfo[$id])) {
                
                // Get fulltext file.
                $annotationListIds = $this->physicalStructureInfo[$id]['files'][$extConf['fileGrpFulltext']];
                
                $annotationTexts = array();
                
                foreach ($annotationListIds as $annotationListId) {
                    
                    $annotationList = $this->iiif->getContainedResourceById($annotationListId);
                    
                    /* @var $annotationList AnnotationList */
                    foreach ($annotationList->getResources() as $annotation) {
                        
                        if ($annotation->getMotivation() == Motivation::PAINTING && $annotation->getResource()!=null &&
                        $annotation->getResource()->getType() == Types::CNT_CONTENTASTEXT && $annotation->getResource()->getChars()!=null) {
                            
                            $xywhFragment = $annotation->getOn();
                            
                            if ($id == null || $id == '' || ($xywhFragment != null && $xywhFragment->getTargetUri() == $id)) {
                                
                                $annotationTexts[] = $annotation->getResource()->getChars();
                                
                            }
                                
                        }
                            
                    }
                    
                }
                
                $rawText = implode(' ', $annotationTexts);
                
            } else {
                
                if (TYPO3_DLOG) {
                    
                    \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_dlf_document->getRawText('.$id.')] Invalid structure node @ID "'.$id.'"'. self::$extKey, SYSLOG_SEVERITY_WARNING);
                    
                }
                
                return $rawText;
                
            }
            
            $this->rawTextArray[$id] = $rawText;
            
        }
        
        return $rawText;
        
    }
    
    /**
     * {@inheritDoc}
     * @see tx_dlf_document::init()
     */
    protected function init()
    {
        // Nothing to do here, at the moment
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::loadLocation()
     */
    protected function loadLocation($location)
    {
        
        $content = GeneralUtility::getUrl($location);

        if (!class_exists('\\iiif\\presentation\\IiifHelper', false)) {
            
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/php-iiif-manifest-reader/iiif/classloader.php'));
            
        }
        
        $resource = IiifHelper::loadIiifResource($content);
        
        if ($resource != null ){
            
            if ($resource instanceof ManifestInterface || $resource instanceof Collection) {
                
                $this->iiif = $resource;
                
                return true;
                
            }
            
        } else {
            if (TYPO3_DLOG) {
                
                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_dlf_iiif_manifest->loadLocation('.$location.')] Could not load IIIF manifest from "'.$location.'"', self::$extKey, SYSLOG_SEVERITY_ERROR);
                
            }
        }
        
    }
    public function __sleep() {
        
        // TODO implement serializiation in IIIF library
        $jsonArray = $this->iiif->getOriginalJsonArray();

        $this->asJson = json_encode($jsonArray);
        
        return array ('uid', 'pid', 'recordId', 'parentId', 'asJson');
        
    }
    
    protected function prepareMetadataArray($cPid)
    {
        
        $id = $this->iiif->getId();
        
        $this->metadataArray[(string) $id] = $this->getMetadata((string) $id, $cPid);
        
    }
    
    protected function ensureHasFulltextIsSet()
    {
        /*
         *  TODO Check "seeAlso" of manifest and canvas for ALTO documents.
         *  See https://github.com/altoxml/schema/issues/40 and https://github.com/altoxml/board/wiki/The-'application-alto-xml'-Media-Type
         *  seeAlso.format = "application/alto+xml" (which is not a registered mime type) or seeAlso.profile = "http://www.loc.gov/standards/alto/v?"
         */ 
        
        /*
         *  TODO Check annotations and annotation lists of canvas for ALTO documents.
         *  Example:
         *  https://digi.ub.uni-heidelberg.de/diglit/iiif/hirsch_hamburg1933_04_25/manifest.json links
         *  https://digi.ub.uni-heidelberg.de/diglit/iiif/hirsch_hamburg1933_04_25/list/0001.json
         */
        
        if (true) return;
        
        // FIXME annotations for painting do not necessarily contain fulltext.
        if (!$this->hasFulltextSet && $this->iiif instanceof ManifestInterface) {
            
            $manifest = $this->iiif;
            
            /* @var $manifest \iiif\presentation\v2\model\resources\Manifest */
            
            $canvases = $manifest->getSequences()[0]->getCanvases();
            
            foreach ($canvases as $canvas) {
                
                if ($canvas->getOtherContent() != null) {
                    
                    foreach ($canvas->getOtherContent() as $annotationList) {
                        
                        if ($annotationList->getResources() != null) {
                            
                            foreach ($annotationList->getResources() as $annotation) {
                                
                                /* @var  $annotation \iiif\presentation\v2\model\resources\Annotation */
                                // Assume that a plain text annotation which is meant to be displayed to the user represents the full text
                                if ($annotation->getMotivation() == Motivation::PAINTING && 
                                    $annotation->getResource() != null &&
                                    $annotation->getResource()->getFormat() == "text/plain" && 
                                    $annotation->getResource()->getChars() != null) {
                                    
                                    $this->hasFulltextSet = true;
                                    
                                    $this->hasFulltext = true;
                                    
                                    return;
                                        
                                }
                            
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
            $this->hasFulltextSet = true;
            
        }
        
    }
    
    protected function _getToplevelId()
    {
        if (empty($this->toplevelId)) {
            
            if (isset($this->iiif)) {
                
                $this->toplevelId = $this->iiif->getId();
                
            }
            
        }
        
        return $this->toplevelId;
        
    }
     
    protected function setPreloadedDocument($preloadedDocument) {
        
        if ($preloadedDocument instanceof ManifestInterface) {
            
            $this->iiif = $preloadedDocument;
            
            return true;
            
        }
        
        return false;
        
    }
    
    /**
     * This magic method is executed after the object is deserialized
     * @see __sleep()
     *
     * @access	public
     *
     * @return	void
     */
    public function __wakeup() {
        
        $resource = IiifHelper::loadIiifResource($this->asJson);
        
        $this->asJson='';
        
        if ($resource != null) {
            
            if ($resource instanceof ManifestInterface || $resource instanceof Collection) {
                
                $this->iiif = $resource;
                
                return true;
            
            }
                
        }
            
        if (TYPO3_DLOG) {
            
            \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_dlf_iiif_manifest->__wakeup()] Could not load IIIF after deserialization', self::$extKey, SYSLOG_SEVERITY_ERROR);
            
        }
    
    }

    /**
     * @return IiifResourceInterface
     */
    public function getIiif()
    {
    
        return $this->iiif;

    }

}


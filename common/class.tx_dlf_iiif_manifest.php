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
use Ubl\Iiif\Presentation\Common\Model\Resources\AnnotationContainerInterface;
use Ubl\Iiif\Presentation\Common\Model\Resources\AnnotationInterface;
use Ubl\Iiif\Presentation\Common\Model\Resources\CanvasInterface;
use Ubl\Iiif\Presentation\Common\Model\Resources\ContentResourceInterface;
use Ubl\Iiif\Presentation\Common\Model\Resources\IiifResourceInterface;
use Ubl\Iiif\Presentation\Common\Model\Resources\ManifestInterface;
use Ubl\Iiif\Presentation\Common\Model\Resources\RangeInterface;
use Ubl\Iiif\Presentation\Common\Vocabulary\Motivation;
use Ubl\Iiif\Presentation\V1\Model\Resources\AbstractIiifResource1;
use Ubl\Iiif\Presentation\V2\Model\Resources\AbstractIiifResource2;
use Ubl\Iiif\Presentation\V3\Model\Resources\AbstractIiifResource3;
use Ubl\Iiif\Services\AbstractImageService;
use Ubl\Iiif\Services\Service;
use Ubl\Iiif\Tools\IiifHelper;

/**
 * Document class 'tx_dlf_iiif_manifest' for the 'dlf' extension. This class
 * represents a IIIF manifest in the conext of this TYPO3 extension.
 * 
 * @author Lutz Helm <helm@ub.uni-leipzig.de>
 *
 */
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
     * A PHP object representation of a IIIF manifest.
     * @var ManifestInterface
     */
    protected $iiif;

    /**
     * 'IIIF1', 'IIIF2' or 'IIIF3', depending on the API $this->iiif confrms to:
     * IIIF Metadata API 1, IIIF Presentation API 2 or 3
     * @var string
     */
    protected $iiifVersion;

    /**
     * Document has already been analyzed if it contains fulltext for the Solr index
     * @var boolean
     */
    protected $hasFulltextSet = false;
    
    /**
     * This holds the original manifest's parsed metadata array with their corresponding
     * resource (Manifest / Sequence / Range) ID as array key
     *
     * @var	array
     * @access protected
     */
    protected $originalMetadataArray = array ();
    
    /**
     * Holds the mime types of linked resources in the manifest (extreacted during parsing) for later use.
     * @var array
     */
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
    protected function establishRecordId($pid)
    {

        if ($this->iiif !== null) {
            
            /*
             *  FIXME This will not consistently work because we can not be sure to have the pid at hand. It may miss
             *  if the plugin that actually loads the manifest allows content from other pages.
             *  Up until now the cPid is only set after the document has been initialized. We need it before to
             *  check the configuration.
             *  TODO Saving / indexing should still work - check!
             */
            $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                'tx_dlf_metadataformat.xpath AS querypath',
                'tx_dlf_metadata,tx_dlf_metadataformat,tx_dlf_formats',
                'tx_dlf_metadata.pid='.$pid.' AND tx_dlf_metadataformat.pid='.$pid.' AND ((tx_dlf_metadata.uid=tx_dlf_metadataformat.parent_id AND tx_dlf_metadataformat.encoded=tx_dlf_formats.uid'
                .' AND tx_dlf_metadata.index_name="record_id" AND tx_dlf_formats.type='.$GLOBALS['TYPO3_DB']->fullQuoteStr($this->getIiifVersion(), 'tx_dlf_formats').') OR tx_dlf_metadata.format=0)'
                .tx_dlf_helper::whereClause('tx_dlf_metadata', TRUE).tx_dlf_helper::whereClause('tx_dlf_metadataformat').tx_dlf_helper::whereClause('tx_dlf_formats'),
                '',
                '',
                ''
                );
            
            if ($GLOBALS['TYPO3_DB']->sql_num_rows($result) > 0) {
                
                for ($i = 0, $j = $GLOBALS['TYPO3_DB']->sql_num_rows($result); $i < $j; $i++) {
                    
                    $resArray = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
                    
                    $recordIdPath = $resArray['querypath'];

                    if (!empty($recordIdPath)) {
                        
                        $this->recordId = $this->iiif->jsonPath($recordIdPath);
                        
                    }
                    
                }
                
            }
            
            // For now, it's a hardcoded ID, not only as a fallback
            if (!isset($this->recordId)) {
                
                $this->recordId = $this->iiif->getId();
                
            }
            
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
    
    /**
     * Returns a string representing the Metadata / Presentation API version which the IIIF resource
     * conforms to. This is used for example to extract metadata according to configured patterns.
     * 
     * @return string   'IIIF1' if the resource is a Metadata API 1 resource, 'IIIF2' / 'IIIF3' if
     *                  the resource is a Presentation API 2 / 3 resource
     */
    public function getIiifVersion() {
        
        if (!isset($this->iiifVersion)) {
            
            if ($this->iiif instanceof AbstractIiifResource1) {
                
                $this->iiifVersion = 'IIIF1';
                
            } elseif ($this->iiif instanceof AbstractIiifResource2) {
                
                $this->iiifVersion = 'IIIF2';
                
            } elseif ($this->iiif instanceof AbstractIiifResource3) {
                
                $this->iiifVersion = 'IIIF3';
                
            }
            
        }
        
        return $this->iiifVersion;
        
    }

    /**
     * True if getUseGroups() has been called and $this-useGrps is loaded
     * 
     * @var boolean 
     */
    protected $useGrpsLoaded;

    /**
     * Holds the configured useGrps as array.
     * 
     * @var array
     */
    protected $useGrps;

    /**
     * tx_dlf_iiif_manifest also populates the physical stucture array entries for matching
     * 'fileGrp's. To do that, the configuration has to be loaded; afterwards configured
     * 'fileGrp's for thumbnails, downloads, audio, fulltext and the 'fileGrp's for images
     * can be requested with this method.  
     * 
     * @param string $use
     * @return array|mixed
     */
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
                
                $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
                
                $iiifId = $this->iiif->getId();
                
                $physSeq[0] = $iiifId;
                
                $this->physicalStructureInfo[$physSeq[0]]['id'] = $iiifId;
                
                $this->physicalStructureInfo[$physSeq[0]]['dmdId'] = $iiifId;
                
                // TODO Translation? Or use language "@none" / null? 
                $this->physicalStructureInfo[$physSeq[0]]['label'] = $this->iiif->getLabelForDisplay();
                
                // TODO Translation? Or use language "@none" / null?
                $this->physicalStructureInfo[$physSeq[0]]['orderlabel'] = $this->iiif->getLabelForDisplay();
                
                $this->physicalStructureInfo[$physSeq[0]]['type'] = 'physSequence';
                
                $this->physicalStructureInfo[$physSeq[0]]['contentIds'] = null;
                
                $fileUseDownload = $this->getUseGroups('fileGrpDownload');
                
                $fileUseFulltext = $this->getUseGroups('fileGrpFulltext');
                
                $fileUseThumbs = $this->getUseGroups('fileGrpThumbs');
                
                $fileUses = $this->getUseGroups('fileGrps');
                
                if (isset($fileUseDownload)) {
                    
                    $docPdfRendering = $this->iiif->getRenderingUrlsForFormat('application/pdf');
                    
                    if (!empty($docPdfRendering)) {
                        
                        $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseDownload] = $docPdfRendering[0];
                        
                    }
                    
                }
                
                if (isset($fileUseFulltext)) {
                    
                    $iiifAlto = $this->iiif->getSeeAlsoUrlsForFormat("application/alto+xml");
                    
                    if (empty($iiifAlto)) {
                        
                        $iiifAlto = $this->iiif->getSeeAlsoUrlsForProfile("http://www.loc.gov/standards/alto/", true);
                        
                    }
                    
                    if (!empty($iiifAlto)) {
                        
                        // FIXME use multiple possible alto files?
                        
                        $this->mimeTypes[$alto[0]] = "application/alto+xml";
                        
                        $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseFulltext] = $iiifAlto[0];
                        
                        $this->hasFulltext = true;
                        
                        $this->hasFulltextSet = true;
                        
                    }
                    
                }
                
                if (!empty($this->iiif->getDefaultCanvases())) {
                    
                    // canvases have not order property, but the context defines canveses as @list with a specific order, so we can provide an alternative
                    $canvasOrder = 0;
                    
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

                                if ($image->getBody() != null && $image->getBody() instanceof ContentResourceInterface) {
                                    
                                    $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUse] = $image->getBody()->getId();
                                    
                                }
                                
                            }
                        }
                        
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
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationContainers'] = null;
                        
                        if (!empty($canvas->getPossibleTextAnnotationContainers())) {
                            
                            $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationContainers'] = array();
                            
                            $this->physicalStructureInfo[$physSeq[0]]['annotationContainers'] = array();
                            
                            foreach ($canvas->getPossibleTextAnnotationContainers() as $annotationContainer) {
                                
                                $this->physicalStructureInfo[$elements[$canvasOrder]]['annotationContainers'][] = $annotationContainer->getId();
                                
                                if ($extConf['indexAnnotations']) {
                                    
                                    $this->hasFulltext = true;
                                    
                                    $this->hasFulltextSet = true;
                                
                                }
                                
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
                                
                                $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUseFulltext] = $alto[0];
                                
                                $this->hasFulltext = true;
                                
                                $this->hasFulltextSet = true;
                            
                            }
                        
                        }
                        
                        
                        if (isset($fileUses)) {
                            
                            foreach ($fileUses as $fileUse) {
                                
                                if ($image->getBody() != null && $image->getBody() instanceof ContentResourceInterface) {
                                    
                                    $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUse] = $image->getBody()->getId();
                                    
                                }
                                
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

    /**
     * 
     * {@inheritDoc}
     * @see tx_dlf_document::getDownloadLocation()
     */
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
                
                return (!empty($resource->getImageAnnotations()) && $resource->getImageAnnotations()->getSingleService() != null) ? $resource->getImageAnnotations()[0]->getSingleService()->getId() : $id;
                
            } elseif ($resource instanceof ContentResourceInterface) {
                
                return $resource->getSingleService() != null && $resource->getSingleService() instanceof Service ? $resource->getSingleService()->getId() : $id;
                
            } elseif ($resource instanceof AbstractImageService) {
                
                return $resource->getId();
                
            } elseif ($resource instanceof AnnotationContainerInterface) {
                
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
            
            $format = "application/vnd.kitodo.iiif";
            
        } elseif ($fileResource instanceof AnnotationInterface) {
            
            $format = "application/vnd.kitodo.iiif";
            
            
        } elseif ($fileResource instanceof ContentResourceInterface) {
            
            if ($fileResource->isText() || $fileResource->isImage() && ($fileResource->getSingleService() == null || !($fileResource->getSingleService() instanceof AbstractImageService))) {
                
                // Support static images without an image service
                return $fileResource->getFormat();
                
            }
            
            $format = "application/vnd.kitodo.iiif";
            
        } elseif ($fileResource instanceof AbstractImageService) {
            
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
    
    /**
     * Returns metadata for IIIF resources with the ID $id in there original form in
     * the manifest, but prepared for display to the user.
     * 
     * @param string   $id: the ID of the IIIF resource
     * @param number   $cPid: the configuration folder's id
     * @param boolean  $withDescription: add description / summary to the return value
     * @param boolean  $withRights: add attribution and license / rights and requiredStatement to the return value
     * @param boolean  $withRelated: add related links / homepage to the return value
     * 
     * @return array
     */
    public function getManifestMetadata($id, $cPid = 0, $withDescription = true, $withRights = true, $withRelated = true) {
        
        if (!empty($this->originalMetadataArray[$id])) {
            
            return $this->originalMetadataArray[$id];
            
        }
        
        $iiifResource = $this->iiif->getContainedResourceById($id);
        
        $result = array();
        
        if ($iiifResource != null) {
            
            if ($iiifResource->getLabel()!=null && $iiifResource->getLabel() != "") {
                
                $result['label'] = $iiifResource->getLabel();
                
            }
            
            if (!empty($iiifResource->getMetadata())) {
                
                $result['metadata'] = [];

                foreach ($iiifResource->getMetadataForDisplay() as $metadata)  {
                    
                    $result['metadata'][$metadata['label']] = $metadata['value'];
                    
                }
                
            }
            
            if ($withDescription && !empty($iiifResource->getSummary())) {
                
                $result["description"] = $iiifResource->getSummaryForDisplay();
                
            }
            
            if ($withRights) {
                
                if (!empty($iiifResource->getRights())) {
                    
                    $result["rights"] = $iiifResource->getRights();
                    
                }

                if (!empty($iiifResource->getRequiredStatement())) {
                    
                    $result["requiredStatement"] = $iiifResource->getRequiredStatementForDisplay();

                }
                
            }
            
            if ($withRelated && !empty($iiifResource->getWeblinksForDisplay())) {
                
                $result["weblinks"] = [];
                
                foreach ($iiifResource->getWeblinksForDisplay() as $link) {
                    
                    $key = array_key_exists("label", $link) ? $link["label"] : $link["@id"];
                    
                    $result["weblinks"][$key] = $link["@id"]; 
                    
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

        $metadata['document_format'][] = 'IIIF';
        
        $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
            'tx_dlf_metadata.index_name AS index_name,tx_dlf_metadataformat.xpath AS xpath,tx_dlf_metadataformat.xpath_sorting AS xpath_sorting,tx_dlf_metadata.is_sortable AS is_sortable,tx_dlf_metadata.default_value AS default_value,tx_dlf_metadata.format AS format',
            'tx_dlf_metadata,tx_dlf_metadataformat,tx_dlf_formats',
            'tx_dlf_metadata.pid='.$cPid.' AND tx_dlf_metadataformat.pid='.$cPid.' AND ((tx_dlf_metadata.uid=tx_dlf_metadataformat.parent_id AND tx_dlf_metadataformat.encoded=tx_dlf_formats.uid AND tx_dlf_formats.type='.$GLOBALS['TYPO3_DB']->fullQuoteStr($this->getIiifVersion(), 'tx_dlf_formats').') OR tx_dlf_metadata.format=0)'.tx_dlf_helper::whereClause('tx_dlf_metadata', TRUE).tx_dlf_helper::whereClause('tx_dlf_metadataformat').tx_dlf_helper::whereClause('tx_dlf_formats'),
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
                    
                } elseif ($values instanceof JSONPath && is_array($values->data()) && count($values->data())>1 ) {
                    
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
        
        return $metadata;
        
    }

    /**
     * 
     * {@inheritDoc}
     * @see tx_dlf_document::_getSmLinks()
     */
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
     * Currently not supported for IIIF. Multivolume works _could_ be modelled
     * as IIIF Collections, but we can't tell them apart from actual collections.
     * 
     * @see tx_dlf_document::saveParentDocumentIfExists()
     */
    protected function saveParentDocumentIfExists()
    {
        // Do nothing.
    }

    /**
     * 
     * {@inheritDoc}
     * @see tx_dlf_document::getRawText()
     */
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
                
                if (!empty($this->physicalStructureInfo[$id]['files'][$extConf['fileGrpFulltext']])) {
                    
                    $rawText = parent::getRawTextFromXml($id);
                    
                }
                
                if ($extConf['indexAnnotations'] == 1) {
                    
                    $iiifResource = $this->iiif->getContainedResourceById($id);

                    // Get annotation containers
                    $annotationContainerIds = $this->physicalStructureInfo[$id]['annotationContainers'];
                    
                    if (!empty($annotationContainerIds)) {
                        
                        $annotationTexts = array();
                        
                        foreach ($annotationContainerIds as $annotationListId) {
                            
                            $annotationContainer = $this->iiif->getContainedResourceById($annotationListId);
                            
                            /* @var $annotationContainer \Ubl\Iiif\Presentation\Common\Model\Resources\AnnotationContainerInterface */
                            foreach ($annotationContainer->getTextAnnotations(Motivation::PAINTING) as $annotation) {
                                
                                if ($annotation->getTargetResourceId() == $iiifResource->getId() && 
                                    $annotation->getBody()!=null && $annotation->getBody()->getChars()!=null) {
                                    
                                        
                                    $annotationTexts[] = $annotation->getBody()->getChars();
                                    
                                }
                                
                            }
                            
                        }
                        
                        $rawText .= implode(' ', $annotationTexts);
                        
                    }
                    
                }
                
            } else {
                
                if (TYPO3_DLOG) {
                    
                    \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_iiif_manifest->getRawText('.$id.')] Invalid structure node @ID "'.$id.'"'. self::$extKey, SYSLOG_SEVERITY_WARNING);
                    
                }
                
                return $rawText;
                
            }
            
            $this->rawTextArray[$id] = $rawText;
            
        }
        
        return $rawText;
        
    }
    
    /**
     * @return IiifResourceInterface
     */
    public function getIiif()
    {
        
        return $this->iiif;
        
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

        if (!class_exists('\\iiif\\tools\\IiifHelper', false)) {
            
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/php-iiif-manifest-reader/Iiif/include.php'));
            
        }
        
        $conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
        
        IiifHelper::setUrlReader(tx_dlf_iiif_urlreader::getInstance());
        
        IiifHelper::setMaxThumbnailHeight($conf['iiifThumbnailHeight']);
        
        IiifHelper::setMaxThumbnailWidth($conf['iiifThumbnailWidth']);
        
        $resource = IiifHelper::loadIiifResource($content);
        
        if ($resource != null ){
            
            if ($resource instanceof ManifestInterface) {
                
                $this->iiif = $resource;
                
                return true;
                
            }
            
        } else {
            if (TYPO3_DLOG) {
                
                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_dlf_iiif_manifest->loadLocation('.$location.')] Could not load IIIF manifest from "'.$location.'"', self::$extKey, SYSLOG_SEVERITY_ERROR);
                
            }
        }
        
    }
    
    protected function prepareMetadataArray($cPid)
    {
        
        $id = $this->iiif->getId();
        
        $this->metadataArray[(string) $id] = $this->getMetadata((string) $id, $cPid);
        
    }

    /**
     *
     * {@inheritDoc}
     * @see tx_dlf_document::setPreloadedDocument()
     */
    protected function setPreloadedDocument($preloadedDocument) {
        
        if ($preloadedDocument instanceof ManifestInterface) {
            
            $this->iiif = $preloadedDocument;
            
            return true;
            
        }
        
        return false;
        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see tx_dlf_document::ensureHasFulltextIsSet()
     */
    protected function ensureHasFulltextIsSet()
    {
        /*
         *  TODO Check annotations and annotation lists of canvas for ALTO documents.
         *  Example:
         *  https://digi.ub.uni-heidelberg.de/diglit/iiif/hirsch_hamburg1933_04_25/manifest.json links
         *  https://digi.ub.uni-heidelberg.de/diglit/iiif/hirsch_hamburg1933_04_25/list/0001.json
         */
        
        if (true) return;
        
        if (!$this->hasFulltextSet && $this->iiif instanceof ManifestInterface) {
            
            $manifest = $this->iiif;
            
            $canvases = $manifest->getDefaultCanvases();
            
            foreach ($canvases as $canvas) {
                
                if (!empty($canvas->getSeeAlsoUrlsForFormat("application/alto+xml")) ||
                    !empty($canvas->getSeeAlsoUrlsForProfile("http://www.loc.gov/standards/alto/"))) {

                        $this->hasFulltextSet = true;
                        
                        $this->hasFulltext = true;
                        
                        return;

                }
                
                $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
                
                if ($extConf['indexAnnotations'] == 1 && !empty($canvas->getPossibleTextAnnotationContainers())) {
                    
                    foreach ($canvas->getPossibleTextAnnotationContainers() as $annotationContainer) {
                        
                        if (($textAnnotations = $annotationContainer->getTextAnnotations(Motivation::PAINTING)) != null) {
                            
                            foreach ($textAnnotations as $annotation) {
                                
                                if ($annotation->getBody() != null &&
                                    $annotation->getBody()->getFormat() == "text/plain" && 
                                    $annotation->getBody()->getChars() != null) {
                                    
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

    /**
     * This magic method is executed after the object is deserialized
     * @see __sleep()
     *
     * @access	public
     *
     * @return	void
     */
    public function __wakeup() {
        
        $conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
        
        IiifHelper::setUrlReader(tx_dlf_iiif_urlreader::getInstance());
        
        IiifHelper::setMaxThumbnailHeight($conf['iiifThumbnailHeight']);
        
        IiifHelper::setMaxThumbnailWidth($conf['iiifThumbnailWidth']);
        
        $resouce = IiifHelper::loadIiifResource($this->asJson);
        
        if ($resource != null && $resource instanceof ManifestInterface) {
                
            $this->asJson='';

            $this->iiif = $resource;

            $this->init();

        } else {

            if (TYPO3_DLOG) {
                
                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_dlf_iiif_manifest->__wakeup()] Could not load IIIF after deserialization', self::$extKey, SYSLOG_SEVERITY_ERROR);
                
            }

        }
            
    }

    /**
     * 
     * @return string[]
     */
    public function __sleep() {
        
        // TODO implement serializiation in IIIF library
        $jsonArray = $this->iiif->getOriginalJsonArray();
        
        $this->asJson = json_encode($jsonArray);
        
        return array ('uid', 'pid', 'recordId', 'parentId', 'asJson');
        
    }
    
}


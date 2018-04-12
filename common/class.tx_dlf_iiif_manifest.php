<?php

use TYPO3\CMS\Core\Utility\GeneralUtility;
use const TYPO3\CMS\Core\Utility\GeneralUtility\SYSLOG_SEVERITY_ERROR;
use iiif\model\helper\IiifReader;
use iiif\model\resources\AbstractIiifResource;
use iiif\model\resources\Annotation;
use iiif\model\resources\Canvas;
use iiif\model\resources\Collection;
use iiif\model\resources\ContentResource;
use iiif\model\resources\Manifest;
use iiif\model\resources\Range;

class tx_dlf_iiif_manifest extends tx_dlf_document
{
    /**
     * This holds the whole XML file as string for serialization purposes
     * @see __sleep() / __wakeup()
     *
     * @var	string
     * @access protected
     */
    protected $asJson = '';

    /**
     * 
     * @var AbstractIiifResource
     */
    protected $iiif;

    /**
     * The extension key
     *
     * @var	string
     * @access public
     */
    public static $extKey = 'dlf';
    /**
     * {@inheritDoc}
     * @see tx_dlf_document::__construct()
     */
    protected function __construct($uid, $pid)
    {
        // FIXME avoid code duplications - extract common parts withtx_dlf_mets_document
        
        // TODO 
        
        // Prepare to check database for the requested document.
        if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($uid)) {
            
            $whereClause = 'tx_dlf_documents.uid='.intval($uid).tx_dlf_helper::whereClause('tx_dlf_documents');
            
        } else {
            // Cast to string for safety reasons.
            $location = (string) $uid;
            
            // TODO The manifest or collection ID should be identical with the location and is the only resource close to a record identifier.
            
            // Try to load IIIF manifest.
            if (\TYPO3\CMS\Core\Utility\GeneralUtility::isValidUrl($location) && $this->load($location)) {
                
                if ($this->iiif !== null) {
                    
                    $this->recordId = $this->iiif->getId(); 
                    
                }

                // TODO check for possibly already stored recordId
                
            } else {
                
                // Loading failed.
                return;
                
            }
            
            if (!empty($this->recordId)) {
                
                $whereClause = 'tx_dlf_documents.record_id='.$GLOBALS['TYPO3_DB']->fullQuoteStr($this->recordId, 'tx_dlf_documents').tx_dlf_helper::whereClause('tx_dlf_documents');
                
            } else {
                
                // There is no record identifier and there should be no hit in the database.
                $whereClause = '1=-1';
                
            }
            
        }
        // Check for PID if needed.
        if ($pid) {
            
            $whereClause .= ' AND tx_dlf_documents.pid='.intval($pid);
            
        }
        
        // Get document PID and location from database.
        $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
            'tx_dlf_documents.uid AS uid,tx_dlf_documents.pid AS pid,tx_dlf_documents.record_id AS record_id,tx_dlf_documents.partof AS partof,tx_dlf_documents.thumbnail AS thumbnail,tx_dlf_documents.location AS location',
            'tx_dlf_documents',
            $whereClause,
            '',
            '',
            '1'
            );
        if ($GLOBALS['TYPO3_DB']->sql_num_rows($result) > 0) {
            
            list ($this->uid, $this->pid, $this->recordId, $this->parentId, $this->thumbnail, $this->location) = $GLOBALS['TYPO3_DB']->sql_fetch_row($result);
            
            $this->thumbnailLoaded = TRUE;
            
            // Load iiif resource file if necessary...
            if ($this->iiif === NULL) {
                
                $this->load($this->location);
                
            }
            
            // Do we have a IIIF resource object now?
            if ($this->iiif !== NULL) {
                
                // Set new location if necessary.
                if (!empty($location)) {
                    
                    $this->location = $location;
                    
                }
                
                // Document ready!
                $this->ready = TRUE;
                
            }
            
        } elseif ($this->iiif !== NULL) {
            
            // Set location as UID for documents not in database.
            $this->uid = $location;
            
            $this->location = $location;
            
            // Document ready!
            $this->ready = TRUE;
            
        } else {
            
            if (TYPO3_DLOG) {
                
                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_dlf_document->__construct('.$uid.', '.$pid.')] No document with UID "'.$uid.'" found or document not accessible', self::$extKey, SYSLOG_SEVERITY_ERROR);
                
            }
            
        }
        
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
            
            if ($this->iiif == null || !($this->iiif instanceof Manifest)) return null;
            
            if ($this->iiif->getSequences() !== null && is_array($this->iiif->getSequences()) && sizeof($this->iiif->getSequences())>0)
            {
                $sequence = $this->iiif->getSequences()[0];
                
                /* @var $sequence \iiif\model\resources\Sequence */
                $sequenceId = $this->iiif->getSequences()[0]->getId();
                
                $physSeq[0] = $sequenceId;
                
                $this->physicalStructureInfo[$physSeq[0]]['id'] = $sequenceId;
                
                $this->physicalStructureInfo[$physSeq[0]]['dmdId'] = $sequenceId;
                
                // TODO translation?
                $this->physicalStructureInfo[$physSeq[0]]['label'] = $sequence->getDefaultLabel();
                
                // TODO from configurable metadata; translation?
                $this->physicalStructureInfo[$physSeq[0]]['orderlabel'] = $sequence->getDefaultLabel();
                
                // TODO Replace with configurable metadata (tx_dlf_structures). Check if it can be read from the metadata. 
                $this->physicalStructureInfo[$physSeq[0]]['type'] = 'unknown';
                
                // TODO check nescessity
                $this->physicalStructureInfo[$physSeq[0]]['contentIds'] = null;
                
                // $this->physicalStructureInfo[$physSeq[0]['']] = ;

                if ($sequence->getCanvases() != null && sizeof($sequence->getCanvases() > 0)) {
                    
                    // canvases have not order property, but the context defines canveses as @list with a specific order, so we can provide an alternative 
                    $canvasOrder = 0;
                    
                    $fileUseThumbs = $this->getUseGroups('fileGrpThumbs');
                    
                    $fileUses = $this->getUseGroups('fileGrps');
                    
                    $serviceProfileCache = [];
                    
                    foreach ($sequence->getCanvases() as $canvas) {
                        
                        $canvasOrder++;
                        
                        /* @var $canvas Canvas */
                        
                        $thumbnailUrl = IiifReader::getThumbnailUrlForIiifResource($canvas, $serviceProfileCache, GeneralUtility::class);
                        
                        // put thumbnails in thumbnail filegroup
                        if (isset($thumbnailUrl)) {
                            
                            $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUseThumbs] = $thumbnailUrl;
                            
                        }
                        
                        $image = $canvas->getImages()[0];
                        
                        /* @var $image iiif\model\resources\Annotation */
                        
                        // put images in all non specific filegroups
                        if (isset($fileUses)) {
                            
                            foreach ($fileUses as $fileUse) {
                                
                                // $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUse] = $image->getResource()->getService()->getId();
                                
                                $this->physicalStructureInfo[$physSeq[0]]['files'][$fileUse] = $image->getResource()->getId();
                                
                            }
                        }
                        
                        // populate structural metadata info
                        $elements[$canvasOrder] = $canvas->getId();
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['id']=$canvas->getId();
                        
                        // TODO check replacement
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['dmdId']=null;
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['label']=$canvas->getDefaultLabel();
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['orderlabel']=$canvas->getDefaultLabel();
                        
                        // assume that a canvas always represents a page
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['type']='page';
                        
                        $this->physicalStructureInfo[$elements[$canvasOrder]]['contentIds']=null;
                        
                        if (isset($fileUses)) {
                            
                            foreach ($fileUses as $fileUse) {
                                
                                // $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUse] = $image->getResource()->getService()->getId();

                                $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUse] = $image->getResource()->getId();
                                
                                
                            }
                        }

                        if (isset($thumbnailUrl)) {
                            
                            $this->physicalStructureInfo[$elements[$canvasOrder]]['files'][$fileUseThumbs] = $thumbnailUrl;
                            
                        }
                        
                        // TODO Check if it is possible to look for pdf downloads in the services and put found service urls in the download group
                        
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
     * {@inheritDoc}
     * @see tx_dlf_document::getFileLocation()
     */
    public function getFileLocation($id)
    {
        
        $resource = $this->iiif->getContainedResourceById($id);
        
        if (isset($resource)) {
            
            if ($resource instanceof Canvas) {
                
                return $resource->getImages()[0]->getService()->getId();
                
            } elseif ($resource instanceof ContentResource) {
                
                return $resource->getService()->getId();
                
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
        
        if ($fileResource instanceof Canvas) {
            
            // $format = $fileResource->getImages()[0]->getResource()->getFormat();
            
            $format = "application/vnd.kitodo.iiif";
            
        } elseif ($fileResource instanceof Annotation) {
            
            // $format = $fileResource->getResource()->getFormat();
            
            $format = "application/vnd.kitodo.iiif";
            
            
        } elseif ($fileResource instanceof ContentResource) {
            
            // $format = $fileResource->getFormat();
            
            $format = "application/vnd.kitodo.iiif";
            
        } else {

            // Assumptions: this can only be the thumbnail and the thumbnail is a jpeg - TODO determine mimetype
            $format = "image/jpeg";
            
        }
        
        
        // TODO decide whether to use the given format (if existent) or 'application/vnd.kitodo.iiif'
        
        return $format;
    }
    
    protected static function &getIiifInstance($uid, $pid = 0, $forceReload = FALSE) {
        
        if (!class_exists('\\iiif\\model\\resources\\IiifReader', false)) {
            
            require_once(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:'.self::$extKey.'/lib/php-iiif-manifest-reader/iiif/classloader.php'));
            
        }
        
        // Sanitize input.
        $pid = max(intval($pid), 0);
        
        // Create new instance...
        $instance = new self($uid, $pid);
        
        // Return new instance.
        return $instance;
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
            
        } elseif (!empty($this->logicalUnits[$id])) {
            
            $logUnits[] = $this->logicalUnits[$id];
            
        } else {
            
            $logUnits[] = $this->iiif;
            
            if ($this->iiif instanceof Manifest && $this->iiif->getStructures()!=null) {
                
                $logUnits = array_merge($logUnits, $this->iiif->getStructures());
                
            }
        }

        if (!empty($logUnits)) {
            
            if (!$recursive) {
                
                $details = $this->getLogicalStructureInfo($logUnits[0]);
                
            } else {
                
                // cache the ranges - they might occure multiple times in the strucures "tree" - with full data as well as referenced as id
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
    
    
    protected function getLogicalStructureInfo(AbstractIiifResource $resource, $recursive = false, &$processedStructures = array()) {
        
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::$extKey]);
        
        $details = array ();
        
        $details['id'] = $resource->getId();
        
        $details['dmdId'] = '';
        
        $details['label'] = $resource->getDefaultLabel() !== null ? $resource->getDefaultLabel() : '';
        
        $details['orderlabel'] = $resource->getDefaultLabel() !== null ? $resource->getDefaultLabel() : '';

        $details['contentIds'] = '';
        
        $details['volume'] = '';
        
        // TODO set volume information?
        
        $details['pagination'] = '';
        
        // FIXME Document cannot be saved without type. 'unknown' ist just a workaround.
        $details['type'] = 'unknown';
        
        $dummy = array();
        
        $details['thumbnailId'] = IiifReader::getThumbnailUrlForIiifResource($resource, $dummy, GeneralUtility::class);
        
        $details['points'] = '';

        // Load strucural mapping
        $this->_getSmLinks();
        
        // Load physical structure.
        $this-> _getPhysicalStructure();
        
        $canvases = array();
        
        if ($resource instanceof Manifest) {

            $startCanvas = $resource->getSequences()[0]->getStartCanvasOrFirstCanvas();
            
            $canvases = $resource->getSequences()[0]->getCanvases();
            
        } elseif ($resource instanceof Range) {
            
            $startCanvas = $resource->getStartCanvasOrFirstCanvas();
            
            $canvases = $resource->getAllCanvases();
            
        }

        if ($startCanvas != null) {
            
            $startCanvasIndex = array_search($startCanvas, $this->iiif->getSequences()[0]->getCanvases());
            
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
            
            if ($resource instanceof Manifest && $resource->getStructures()!==null && sizeof($resource->getStructures())>0) {
                    
                foreach ($resource->getStructures() as $range) {
                    
                    if ((array_search($resource->getId(), $processedStructures) == false)) {
                        
                        $details['children'][] = $this->getLogicalStructureInfo($range, TRUE, $processedStructures);
                        
                    }
                    
                }
                
            } elseif ($resource instanceof Range) {
                
                if ($resource->getRanges() !== null && sizeof($resource->getRanges())>0) {

                    foreach ($resource->getRanges() as $range) {
                        
                        if ((array_search($resource->getId(), $processedStructures) == false)) {
                            
                            $details['children'][] = $this->getLogicalStructureInfo($range, TRUE, $processedStructures);
                            
                        }
                        
                    }
                    
                }
                
                if ($resource->getMembers() !== null && sizeof($resource->getMembers())>0) {
                    
                    foreach ($resource->getMembers() as $member) {
                        
                        if ($member instanceof Range && (array_search($resource->getId(), $processedStructures) == false)) {
                            
                            $details['children'][] = $this->getLogicalStructureInfo($member, TRUE, $processedStructures);
                            
                        }
                        
                    }
                    
                }
                
            }
            
        }
        
        return $details;
        
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getMetadata()
     */
    public function getMetadata($id, $cPid = 0)
    {
        // TODO load metadata configuration from
        
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

        if ($this->iiif instanceof Manifest || $this->iiif instanceof Collection) {
            
            // TODO multiple labels, translations; configurable
            $metadata['title'][] = $this->iiif->getDefaultLabel();
            
            $metadata['document_format'][] = 'IIIF';
            
        }
        
        // FIXME
        $metadata['type'][] = 'unknown';
        
        if ($this->iiif instanceof Manifest) {
            
            // TODO for every metadatum: translation; multiple values; configuration 
            
            $metadata['author'][] = $this->iiif->getMetadataForLabel('Author');
            
            $metadata['place'][] = $this->iiif->getMetadataForLabel('Place of publication');
            
            $metadata['place_sorting'][] = $this->iiif->getMetadataForLabel('Place of publication');
           
            $metadata['year'][] = $this->iiif->getMetadataForLabel('Date of publication');
            
            $metadata['year_sorting'][] = $this->iiif->getMetadataForLabel('Date of publication');
            
            $metadata['prod_id'][] = $this->iiif->getMetadataForLabel('Kitodo');
            
            $metadata['record_id'][] = $this->recordId;
            
            $metadata['union_id'][] = $this->iiif->getMetadataForLabel('Source PPN (SWB)');
            
            // $metadata['collection'][] = $this->iiif->getMetadataForLabel('Collection');
            
            $metadata['owner'][] = $this->iiif->getMetadataForLabel('Owner');
            
        }
        
        // TODO use configuration
        
        return $metadata;
        
    }
    
    protected function _getSmLinks() {
        
        if (!$this->smLinksLoaded && isset($this->iiif) && $this->iiif instanceof Manifest) {
            
            if ($this->iiif->getSequences()!==null && sizeof($this->iiif->getSequences())>0) {
                
                $sequenceCanvases = $this->iiif->getSequences()[0]->getCanvases();
                
                if ($sequenceCanvases != null && sizeof($sequenceCanvases) >0) {
                    
                    foreach ($this->iiif->getSequences()[0]->getCanvases() as $canvas) {
                        
                        $this->smLinkCanvasToResource($canvas, $this->iiif->getSequences()[0]);
                        
                    }
                        
                }
                
            }
            
            if ($this->iiif->getStructures() !=null && sizeof($this->iiif->getStructures())>0) {
                
                foreach ($this->iiif->getStructures() as $range) {
                    
                    $this->smLinkRangeCanvasesRecursively($range);
                    
                }

            }
            
            $this->smLinksLoaded = true;
        
        }
            
    }
    
    private function smLinkRangeCanvasesRecursively(Range $range) {
        
        // map range's canvases including all child ranges' canvases
        
        foreach ($range->getAllCanvases() as $canvas) {
            
            $this->smLinkCanvasToResource($canvas, $range);
            
        }
        
        // recursive call for all ranges
        
        if ($range->getRanges()!==null && sizeof($range->getRanges())) {
            
            foreach ($range->getRanges() as $childRange) {
                
                $this->smLinkRangeCanvasesRecursively($childRange);
                
            }
                
        }
        
        // iterate through members and map all member canvases, call self for all member ranges
        
        if ($range->getMembers()!==null && sizeof($range->getMembers())>0) {
            
            foreach ($range->getMembers() as $member) {
                
                if ($member instanceof Canvas) {
                    
                    $this->smLinkCanvasToResource($member, $range);
                    
                }
                
                if ($member instanceof Range) {
                    
                    $this->smLinkRangeCanvasesRecursively($member);
                    
                }
                
            }
            
        }
        
    }
    
    private function smLinkCanvasToResource(Canvas $canvas, AbstractIiifResource $resource)
    {
        
        $this->smLinks['l2p'][$resource->getId()][] = $canvas->getId();
        
        $this->smLinks['p2l'][$canvas->getId()][] = $resource->getId();
        
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

        $resource = IiifReader::getIiifResourceFromJsonString($content);
        
        if ($resource != null && ($resource instanceof Manifest || $resource instanceof Collection)) {
             $this->iiif = $resource;
             
             return true;
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
    
    protected function ensureHasFulltextIsLoaded()
    {
        // TODO implement
    }
    
    protected function _getToplevelId()
    {
        if (empty($this->toplevelId)) {
            if (isset($this->iiif))
            {
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
        
        $resource = IiifReader::getIiifResourceFromJsonString($this->asJson);
        
        $this->asJson='';
        
        if ($resource != null && ($resource instanceof Manifest || $resource instanceof Collection)) {
            $this->iiif = $resource;
            
            return true;
        } else {
            if (TYPO3_DLOG) {
                
                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('[tx_dlf_iiif_manifest->__wakeup()] Could not load IIIF after deserialization', self::$extKey, SYSLOG_SEVERITY_ERROR);
                
            }
        }
    
    }
        
    


}


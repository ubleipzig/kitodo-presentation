<?php

use iiif\model\resources\Annotation;
use iiif\model\resources\Canvas;
use iiif\model\resources\ContentResource;
use iiif\model\resources\Manifest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use iiif\model\helper\IiifReader;
use iiif\model\resources\Collection;
use iiif\model\resources\AbstractIiifResource;

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
        // Prepare to check database for the requested document.
        if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($uid)) {
            
            $whereClause = 'tx_dlf_documents.uid='.intval($uid).tx_dlf_helper::whereClause('tx_dlf_documents');
            
        } else {
            // Cast to string for safety reasons.
            $location = (string) $uid;
            
            // Try to load IIIF manifest.
            if (\TYPO3\CMS\Core\Utility\GeneralUtility::isValidUrl($location) && $this->load($location)) {

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
            
            // Load XML file if necessary...
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

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::_getPhysicalStructure()
     */
    protected function _getPhysicalStructure()
    {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getFileLocation()
     */
    public function getFileLocation($id)
    {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getFileMimeType()
     */
    public function getFileMimeType($id)
    {
        $fileResource = $this->manifest->getContainedResourceById($id);
        if ($fileResource instanceof Canvas) {
            $format = $fileResource->getImages()[0]->getResource()->getFormat();
        } elseif ($fileResource instanceof Annotation) {
            $format = $fileResource->getResource()->getFormat();
        } elseif ($fileResource instanceof ContentResource) {
            $format = $fileResource->getFormat();
        }
        // TODO decide whether to use the given format (if existent) or 'application/vnd.kitodo.iiif'
        
        return $format;
    }
    
    protected static function &getIiifInstance($uid, $pid = 0, $forceReload = FALSE) {
        
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
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::getMetadata()
     */
    public function getMetadata($id, $cPid = 0)
    {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see tx_dlf_document::loadFormats()
     */
    protected function loadFormats()
    {
        // do nothing
        
    }
    /**
     * {@inheritDoc}
     * @see tx_dlf_document::init()
     */
    protected function init()
    {
        // TODO Auto-generated method stub
        
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


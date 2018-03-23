<?php

use iiif\model\resources\Annotation;
use iiif\model\resources\Canvas;
use iiif\model\resources\ContentResource;
use iiif\model\resources\Manifest;

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
     * @var Manifest
     */
    protected $manifest;

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
        // TODO Auto-generated method stub
        return parent::__construct();
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
        // TODO Auto-generated method stub
        
    }


    


}


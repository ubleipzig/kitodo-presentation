<?php

use iiif\tools\UrlReaderInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class tx_dlf_iiif_urlreader implements UrlReaderInterface{
    
    protected static $instance;
    
    public function getContent($url) {
        
        return GeneralUtility::getUrl($url);
        
    }
    
    public static function getInstance() {
        
        if (!isset(self::$instance)) {
            
            self::$instance = new tx_dlf_iiif_urlreader();
            
        }

        return self::$instance;
        
    }
    
}


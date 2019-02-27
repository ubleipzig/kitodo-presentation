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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Ubl\Iiif\Tools\UrlReaderInterface;

/**
 * Implementation of Ubl\Iiif\Tools\UrlReaderInterface for the 'dlf' TYPO3 extension.
 * Allows the use of TYPO3 framework functions for loading remote documents in the
 * IIIF library.
 * 
 * @author Lutz Helm <helm@ub.uni-leipzig.de>
 *
 */
class tx_dlf_iiif_urlreader implements UrlReaderInterface {
    
    /**
     * Singleton instance of the class
     *
     * @var tx_dlf_iiif_urlreader
     */
    protected static $instance;
    
    /**
     * 
     * {@inheritDoc}
     * @see \Ubl\Iiif\Tools\UrlReaderInterface::getContent()
     */
    public function getContent($url) {
        
        return GeneralUtility::getUrl($url);
        
    }

    /**
     * Return a singleton instance.
     * 
     * @return tx_dlf_iiif_urlreader
     */
    public static function getInstance() {
        
        if (!isset(self::$instance)) {
            
            self::$instance = new tx_dlf_iiif_urlreader();
            
        }

        return self::$instance;
        
    }
    
}


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

class tx_dlf_iiif_urlreader implements UrlReaderInterface {
    
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


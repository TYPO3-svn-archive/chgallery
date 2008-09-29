<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Georg Ringer <http://www.ringer.it/>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(PATH_tslib.'class.tslib_pibase.php');
/**
 * Plugin 'Simple gallery' for the 'chgallery' extension.
 *
 * @author	Georg Ringer <http://www.ringer.it/>
 * @package	TYPO3
 * @subpackage	tx_chgallery
 */
class tx_chgallery_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_chgallery_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_chgallery_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'chgallery';	// The extension key.
	var $pi_checkCHash = true;
	
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content,$conf)	{
		$this->init($conf);

		// call the correct function to display LIST or single gallery
		if ($this->config['show']=='SINGLE') {
			$content = $this->getSingleView();
		} elseif ($this->config['show']=='LIST') {
			$content = ($this->piVars['dir']!=0) ? $this->getGalleryView() : $this->getCategoryView();
		} else {
			$content = $this->getGalleryView();
		}
	
		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * Get the single image view including all kind of information about the image
	 *
	 * @return	single image view
	 */	
	function getSingleView() {
		$template['total'] = $this->cObj->getSubpart($this->templateCode,'###TEMPLATE_SINGLE###');
		$dir = $this->piVars['dir'];
		$singleImage = $this->piVars['single'];

		// return empty string if no get var for the single image
		if ($singleImage==0) {
			return '';
		}

		// get the single image from CATEGORY view
		if ($dir > 0) {
			// get all dirs
			$dirList 		= $this->config['subfolders'][$dir-1];
			$imageList 	= $this->getImagesOfDir($dirList['path']);
			
			$imgPos = ($this->conf['gallery.']['excludeFirstImage'] == 0) ? $singleImage-1 : $singleImage;
			
			$markerArray = $this->getSingleImageSlice($imageList, $imgPos);

		// get the single image from GALLERY view
		} else {
			$imageList 		= $this->getImagesOfDir($this->config['path']); // get all imgs of the dir
			$markerArray 	= $this->getSingleImageSlice($imageList, $singleImage-1);
		}
		
		// count=0 means, that this is the LIST view which has no image to load, so hide everything 
		if(count($markerArray)==0) {
			$subpartArray['###SINGLE_IMAGE###'] = '';
		}

		// pagebrowser: PREV image
		$linkConf = array();
		$linkConf['parameter'] = $this->getLinkParameter();
		$linkConf['useCacheHash'] = 1;
		
		if ($singleImage > 1) {
			$override = $this->piVars;
			$override['single'] = $singleImage-1;

			// check if previous image is on the previous page
			if ( $override['single']/$this->config['pagebrowser'] <= $this->piVars['pointer']   ) {
				$override['pointer'] = $override['pointer']-1;
				if ($override['pointer']==0) $override['pointer'] = ''; // if value 0, set it to '' to avoid showing 0 in the url
			}
			
			// change param array to string
			foreach ($override as $key=>$value) {
				if ($key!='') $linkConf['additionalParams'].= '&tx_chgallery_pi1['.$key.']='.$value;
			}
			$linkConf['title'] = $this->pi_getLL('previousImage');

			$markerArray['###PREV###'] = $this->cObj->typolink($this->pi_getLL('previousImage'), $linkConf);
		} else {
			$markerArray['###PREV###'] = '';
		} 

		// pagebrowser: NEXT image
		if ($singleImage < count($imageList)) {
			$override = $this->piVars;
			$override['single'] = $singleImage+1;

			// check if next image is on the next page
			$pointer = ($this->piVars['pointer'] ==0) ? 1 : $this->piVars['pointer'];
			if (($override['single']/$this->config['pagebrowser']) > $pointer   ) {
				$override['pointer'] = $override['pointer']+1;
			}	
			
			// change param array to string
			foreach ($override as $key=>$value) {
				if ($key!='') $linkConf['additionalParams'].= '&tx_chgallery_pi1['.$key.']='.$value;
			}
			$linkConf['title'] = $this->pi_getLL('nextImage');	

			$markerArray['###NEXT###'] = $this->cObj->typolink($this->pi_getLL('nextImage'), $linkConf);
		} else {
			$markerArray['###NEXT###'] = '';
		}
		
		// hide exif
		if ($markerArray['###EXIF###']=='') {
			$subpartArray['###EXIF###'] = '';
		} 
		
		$content.= $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray, $subpartArray);	
		return $content;
	}

	/**
	 * Get the correct single image out of the correct directory by knowing its position in the dir
	 *
	 * @param	array		$imageList: List of images of the dir
	 * @param	int		$pos: Position of the img which is needed
	 * @param	boolean		$hideFirst: If first is used as category image, don't use it do count
	 * @return	array Every information about this image filled in markers
	 */
	function getSingleImageSlice($imageList, $pos, $hideFirst=false) {
		$finalImage = array_slice($imageList, $pos, 1); // get the only image
		$finalImage = array_values($finalImage); // get the value=path
		
		$marker = $this->getImageMarker($finalImage[0], $pos, 'single');
		
		return $marker;
	} 

	/**
	 * Get all information about a single image by reading its exif info, description,...
	 *
	 * @param	string		$path: Path of the image
	 * @param	int		$pos: Position of the image in the gallery
	 * @param	string		$view: Type of view to use the correct TS (gallery, single,...)
	 * @return	array Every information about this image filled in markers
	 */
	
	function getImageMarker($path, $pos, $view) {
		$marker = array();
		
		if (!is_file($path)) {
			return $marker;
		}

		$conf = $this->conf[$view.'.']; // shortcut to the TS configuration of the current view
	
		// single image TS configuration
		$singleImageConf = $conf['image.'];
		$singleImageConf['file'] = $path;
		$description = str_replace('"','\'',$this->getDescription($path, 'file')); 
		$singleImageConf['altText'] = $description;
		
		// Load some info into cObj->data to use it via TS later with field = ...
		$this->cObj->data['tx_chgalleryTitle']    = $description;
		$this->cObj->data['tx_chgalleryFile']     = $path;
		$this->cObj->data['tx_chgalleryFilename'] = basename($path);
		
		// fill the markers
		$marker['###IMAGE###'] = $this->cObj->IMAGE($singleImageConf);
		$marker['###DESCRIPTION###'] = $this->cObj->stdWrap($description, $conf['description.']);
		$marker['###DOWNLOAD###'] = $this->cObj->filelink($path, $conf['download.'] );
		$marker['###FILENAME###'] = $this->cObj->stdWrap(basename($path), $conf['file.']);
		$marker['###POSITION###'] = $this->cObj->stdWrap($pos, $conf['position.']);

		// load information from exif
		if ($this->conf['exif']==1 && t3lib_div::inArray( get_loaded_extensions(), 'exif' )) {
			$exif_array = @exif_read_data( $path, true, false ); // Load all EXIF informations from the original Pic in an Array
			$marker['###EXIF###'] = 'exif: '.t3lib_div::view_array($exif_array);
			$marker['###EXIF_SIZE###'] =  $this->cObj->stdWrap($exif_array['FileSize'], $conf['exif_size.']);
			$marker['###EXIF_TIME###'] =  $this->cObj->stdWrap($exif_array['FileDateTime'], $conf['exif_time.']);			
		} else {
			$marker['###EXIF###'] = '';
		}
		
		// language markers
		$tmpValues = array('description', 'download', 'exif_size', 'exif_time', 'filename');
		foreach($tmpValues as $key) {
			$marker['###LL_'.strtoupper($key).'###'] = $this->pi_getLL($key);
		}

		// ratings
		if (t3lib_extMgm::isLoaded('ratings') && $this->conf['ratings']==1) {
			$fileName = (strlen($path) < 244) ? $path : substr($path, -240);
	           	      
			$this->rate['conf']['ref'] = 'chgallery' . ($fileName);
			$marker['###RATINGS###'] = $this->ratecObj->cObjGetSingle('USER_INT', $this->rate['conf']);
		} else {
			$marker['###RATINGS###'] = '';
		}			
		
		return $marker;
	}


	/**
	 * Get a list of all subdirs including preview and link to single view
	 *
	 * @return	The list
	 */	
	function getCategoryView() {
		$template['total'] = $this->cObj->getSubpart($this->templateCode,'###TEMPLATE_LIST###');
		$template['item'] = $this->cObj->getSubpart($template['total'],'###ITEM###');

		foreach ($this->config['subfolders'] as $key=>$value) {
			// generall markers
			$markerList = array('size', 'description', 'path', 'title', 'name', 'date');
			foreach($markerList as $mKey) {
				$markerArray['###'.strtoupper($mKey).'###'] 		= $this->cObj->stdWrap($value[$mKey], $this->conf['category.'][$mKey.'.']);
				$markerArray['###LL_'.strtoupper($mKey).'###'] 	= $this->pi_getLL($mKey);
			}
			$markerArray['###ZEBRA###']		= ($key%2==0) ? 'odd' : 'even';
			
			// preview image 
			$imgageConf = $this->conf['category.']['image.'];
			$imgageConf['file'] = $this->getImagesOfDir($value['path'], true);
			$markerArray['###IMAGE###'] = $this->cObj->IMAGE($imgageConf);
			
			// create the link to the dir
			$linkConf = $this->conf['category.']['link.'];
			$linkConf['parameter'] = $this->getLinkParameter();
			$linkConf['additionalParams'] = '&tx_chgallery_pi1[dir]='.($key+1);
			$linkConf['title'] = $value['title'];
			$wrappedSubpartArray['###LINK_ITEM###'] = explode('|', $this->cObj->typolink('|', $linkConf));
			
			$content_item .= $this->cObj->substituteMarkerArrayCached($template['item'], $markerArray, $array, $wrappedSubpartArray);
  	}
  	
  	// put everything into the template
		$subpartArray['###CONTENT###'] = $content_item;	

		$content.= $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray, $subpartArray);	
		return $content;
	}

	
	/**
	 * Get all images of a directory or just the 1st 	 
	 *
	 * @param	string		$path: Path of the dir
	 * @param	boolean		$firstOnly: If true, return the 1st image
	 * @return	array/text of images
	 */	
	function getImagesOfDir($path, $firstOnly=false) {
		$imageList = t3lib_div::getFilesInDir($path, $this->conf['fileTypes'],1,1);
		if ($firstOnly) {
			return array_shift($imageList);
		}
		
		return $imageList;	
	}


	/**
	 * Get all subdirectories of a dir including information about the content
	 * Only dirs with images in it are taken	 
	 *
	 * @param	string		$path: Path of the dir
	 * @return	array with the images, the dir title/descriptin
	 */		
	function getFullDir($path) {
		$dir = t3lib_div::get_dirs($path);	
		$newdir = array();
		$titleList = explode(chr(10), $this->config['listTitle']);

		if(is_array($dir) && !empty($dir)) {
			array_multisort($dir, SORT_DESC, SORT_STRING);
		}

		if (is_array($dir)) {
			foreach ($dir as $key=>$value) {
				$size = $this->getImagesOfDir($path.$value.'/');
				
				// if exclude is set, empty means one image
				$empty = ($this->config['exclude1stImg']==1) ? 1 : 0;
				
				if (count($size)<=$empty) {
					unset($dir[$key]);
				} else {
					$newdir[$key]['path']				= $path.$value.'/';
					$newdir[$key]['size'] 			= ($this->config['exclude1stImg']==1) ? count($size)-1 : count($size);
					$newdir[$key]['title'] 			= $titleList[$key];
					$newdir[$key]['description']= $this->getDescription($path.$value.'/', 'dir');
					$newdir[$key]['name'] 			= $value;
					$newdir[$key]['date'] 			= filemtime($path.$value);
				}
			}
		}

		return $newdir;
	}
	
	
	/**
	 * Get a single gallery
	 *
	 * @return	Whole gallery
	 */	
	function getGalleryView() {		
			// if page browser needs to be used
		if (!isset($this->piVars['pointer'])) {
			$pb = 0 ;
		} else {
			$pb = intval($this->piVars['pointer']);
		}	
		
		// page browser 
		$begin 	= $pb * $this->config['pagebrowser'];
		$end 		= $begin + $this->config['pagebrowser'];

		$content = $this->getSingleGalleryPage($pb, $begin, $end);
		return $content;
	}
	

	/**
	 * Get a gallery page
	 *
	 * @param	int		$pb: Pointer of the pagebrowser
	 * @param	int		$begin: Begin of the pagebrowser
	 * @param	int		$end: End of the pagebrowser
	 * @param	boolean		$ajax: If ajax is used
	 * @return	array/text of images
	 */	
	function getSingleGalleryPage($pb, $begin, $end, $ajax=0) {
		// templates
		$ajaxTemplateSuffix = ($ajax==1) ? '_AJAX' : '';
		$template['total'] = $this->cObj->getSubpart($this->templateCode,'###TEMPLATE'.$ajaxTemplateSuffix.'###');
		$template['item'] = $this->cObj->getSubpart($template['total'],'###ITEM###');
	
		// get all infos we need
		// if LIST view, get the information about the category
		if ($this->config['show']=='LIST' && $this->piVars['dir']!=0) {
			$dirKey = $this->piVars['dir'];
			$linkToDir = '&tx_chgallery_pi1[dir]='.$dirKey;
			$dirKey--;
 			$subDir = $this->config['subfolders'][$dirKey];
			$path = $subDir['path'];
			
			foreach ($subDir as $key=>$value) {
				$markerArray['###DIR_'.strtoupper($key).'###'] = $this->cObj->stdWrap($subDir[$key], $this->conf['gallery.']['dir_'.$key.'.']);
				$markerArray['###LL_'.strtoupper($key).'###']  = $this->pi_getLL($key);			
			}

			$backLink = array();
			$backLink['parameter'] = $GLOBALS['TSFE']->id;
			$markerArray['###DIR_BACK###'] = $this->cObj->typolink($this->pi_getLL('dir_back'), $backLink);
			
		} else {
			$path =$this->config['path'];
			
			// hide the subdir part
			$subpartArray['###SUBDIR_NAVIGATION###'] = '';
		}

		// get all images of the dir
		$imageList = t3lib_div::getFilesInDir($path, $this->conf['fileTypes'],1,1);

		// exclude 1st image if set and if this is a detail view of LIST
		if ($this->config['exclude1stImg']==1 && $this->config['show']=='LIST' && $this->piVars['dir']!=0) {
			$firstEl = array_shift($imageList);
		}

		// error check
		if (count($imageList)==0) {
			return '';
		}
		
		// create the page browser and the links
		$count 	= count($imageList);
		$totalPages = ceil($count/$this->config['pagebrowser']);

		// get the markers of the pagebrowser
		$markerArray = $this->getPageBrowserMarkers($markerArray, count($imageList),$this->config['pagebrowser']);
		
		// if image of the single view should be passed through the page browsers link
		$singleImage = $this->piVars['single'];
		if ($singleImage>0 && $this->conf['single.']['pass']==1) {
			$linkToDir.= '&tx_chgallery_pi1[single]='.$singleImage;
		}
		
		
		// create the links for the pagebrowser
		$linkConf = $this->conf['link.'];
		$linkConf['parameter'] = $this->getLinkParameter();
		$linkConf['useCacheHash'] = 1;
 		$linkConf['additionalParams'] = $linkToDir;
		
		// first
		$linkConf['title'] = $this->pi_getLL('pi_list_browseresults_first');
		$markerArray['###FIRST###'] = $this->cObj->typolink($this->pi_getLL('pi_list_browseresults_first'), $linkConf);
		// last
		$linkConf['title'] = $this->pi_getLL('pi_list_browseresults_last');
		$linkConf['additionalParams'] = $linkToDir.'&tx_chgallery_pi1[pointer]='.($totalPages-1);
		$markerArray['###LAST###'] = $this->cObj->typolink($this->pi_getLL('pi_list_browseresults_last'), $linkConf);
		
		// next
		if ($pb+1 < $totalPages) {
			$linkConf['title'] = $this->pi_getLL('pi_list_browseresults_next');
			$linkConf['additionalParams'] = $linkToDir.'&tx_chgallery_pi1[pointer]='.($pb+1);
			$markerArray['###NEXT###'] = $this->cObj->typolink($this->pi_getLL('pi_list_browseresults_next'), $linkConf); 
		} else {
			$markerArray['###NEXT###'] = '';		
		}
		
		// prev
		$linkConf['title'] = $this->pi_getLL('pi_list_browseresults_prev');
		if ($pb>1) {
			$linkConf['additionalParams'] = $linkToDir.'&tx_chgallery_pi1[pointer]='.($pb-1);
			$markerArray['###PREV###'] = $this->cObj->typolink($this->pi_getLL('pi_list_browseresults_prev'), $linkConf); 
		} elseif ($pb==1) {
			$linkConf['additionalParams'] = $linkToDir.'';		
			$markerArray['###PREV###'] = $this->cObj->typolink($this->pi_getLL('pi_list_browseresults_prev'), $linkConf);
		} elseif ($this->conf['ajax']==1) {
			$linkConf['additionalParams'] = $linkToDir.'';	
			$linkConf['ATagParams'] = 'class="hide"';	
			$markerArray['###PREV###'] = '&nbsp;'.$this->cObj->typolink($this->pi_getLL('pi_list_browseresults_prev'), $linkConf);
		} else {
			$markerArray['###PREV###'] = '&nbsp;';
		}

		// max used pages
		$markerArray['###PAGEBROWSERPAGES###'] = $totalPages;

		// ajax url			
		$actionConf = array();
		$actionConf['parameter'] = $GLOBALS['TSFE']->id;
		$actionConf['additionalParams'] = $linkToDir.'&type=9712';
		$actionConf['returnLast'] = 'url';
		$markerArray['###AJAXURL###'] = $this->cObj->typolink('',$actionConf);
		// include ajax script
		$markerArray['###AJAXSCRIPT###'] = '<script  src="'.$GLOBALS['TSFE']->tmpl->getFileName($this->conf['ajaxScript']).'" type="text/javascript"></script>';
		
		$markerArray['###LINKSBEFORE###'] = '';
		$markerArray['###LINKSAFTER###'] = '';

		
		// merge image + description to be able to sort them and not loosing the relation between them
		$allList = array();
		$j=0;
		foreach ($imageList as $key=>$value) {
  		$allList[$j]['file'] = $value;
  		$j++;
  	}	
  	
  	// Random mode, get a randomized array
		// Use plugin.tx_chgallery_pi1 = USER_INT !
		if ($this->config['random']==1) {
		
			// hide the subdir part
			$subpartArray['###PAGEBROWSER###'] = '';
		
			$randomImageList = $this->getSlicedRandomArray($allList, $begin, $this->config['pagebrowser'] );
			$newImageList = $randomImageList['random'];
			
			// if all links should be renderd, all other links are after the existing images and 
			// need to be taken from the same function because of the randomizing
			if ($this->config['renderAllLinks']==1) {
				 $markerArray['###LINKSAFTER###'] = $this->getRenderAllLinks($randomImageList['after']);
			}
			
		} else {
			// just get the elements we need
			$newImageList = array_slice($allList, $begin,$this->config['pagebrowser'] );
		}
		
			// config of the single image & check for usage of link
		$imageConf = $this->conf['gallery.']['image.'];
		if ($this->config['link']!='') {
			$imageConf['stdWrap.']['typolink.']['parameter'] = $this->config['link'];
			unset($imageConf['imageLinkWrap']);
		} 
		
		// render the link before/after the current page
		// if random ==1, the links are rendered some lines before
		if ($this->config['renderAllLinks']==1 && $this->config['random']!=1) {
		// previous images, from 0 to begin
			$prevImgList = array_slice($allList, 0, $begin);

			$markerArray['###LINKSBEFORE###'].= $this->getRenderAllLinks($prevImgList);
		
			// after images, from current page + number of images at this page to the end
			$beginForAfterImg = ($begin + $this->config['pagebrowser']) ;
			$endForAfterImg = ($count - $beginForAfterImg); 

			$afterImgList = array_slice($allList, $beginForAfterImg, $endForAfterImg);
			$markerArray['###LINKSAFTER###'].= $this->getRenderAllLinks($afterImgList);
		} 		

	
		// create the gallery
		foreach ($newImageList as $key=>$singleImage) {
			// if single view, render a different link
			if ($this->config['single']==1 && $this->config['link']=='') {
				$id = ($key+1)+$begin;
				$imageConf['stdWrap.']['typolink.']['additionalParams'] = '&tx_chgallery_pi1[single]='.($id);
				if ($this->piVars['dir']>0) {
					$imageConf['stdWrap.']['typolink.']['additionalParams'].= '&tx_chgallery_pi1[dir]='.$this->piVars['dir'];
				}
				
				if ($pb>0) {
					$imageConf['stdWrap.']['typolink.']['additionalParams'].= '&tx_chgallery_pi1[pointer]='.$pb;
				}
				$imageConf['stdWrap.']['typolink.']['parameter'] = $GLOBALS['TSFE']->id;
				$imageConf['stdWrap.']['typolink.']['useCacheHash'] = 1;		
				unset($imageConf['imageLinkWrap']);
			}			

			$imageConf['file'] = $singleImage['file'];
			$description = str_replace('"','\'',$this->getDescription($singleImage['file'], 'file')); 
			$imageConf['altText'] = $description;
	
			$markerArrayImage = $this->getImageMarker($singleImage['file'], $key+1, 'gallery');
	  	$markerArrayImage['###IMAGE###'] = $this->cObj->IMAGE($imageConf);
	  	
	  	// hide exif
	  	if ($markerArrayImage['###EXIF###']=='') {
				$subpartArray['###EXIF###'] = '';
			}
	  	
	  	// get the current image 
	  	$currentImgId = ($this->piVars['pointer']*$this->config['pagebrowser']) + $key+1 ;
	  	if ($currentImgId==$this->piVars['single'] && $this->piVars['single'] > 1 ) {
				$markerArrayImage['###ACT###'] = ' act';
			} else {
				$markerArrayImage['###ACT###'] = '';
			}

			
			$content_item .= $this->cObj->substituteMarkerArrayCached($template['item'], $markerArrayImage, $subpartArray);
  	}
  	
  	// put everything into the template
		$subpartArray['###CONTENT###'] = $content_item;

		$content.= $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray, $subpartArray);
		return $content;
	}


	/**
	 * Get the correct parameter for the links
	 *
	 * @return either the ID or the anchor
	 */		
	function getLinkParameter() {
		$link = '';
		if ($this->conf['useAnchor']==1) {
			$link = ' #c'.$this->cObj->data['uid'];
		} else {
			$link = $GLOBALS['TSFE']->id;
		}

		return $link;
	}


	/**
	 * Render empty links to images from an image array
	 *
	 * @param	array  $imgList: Array with the images
	 * @return all links next to each other
	 */		
	function getRenderAllLinks($imgList) {
		$links = '';
		$imageConf = $this->conf['gallery.']['renderAllLinks.'];
		foreach ($imgList as $key=>$singleImage) {
			$this->cObj->data['tx_chgalleryImageLong'] = $singleImage['file'];
			$imageConf['altText'] = str_replace('"','\'',$singleImage['text']);
			$this->cObj->data['tx_chgalleryTitle'] = $imageConf['altText'];
			
			$links.= $this->cObj->typolink(' ', $imageConf);
		}
		return $links;
	}
		

	/**
	 * Get the content of a txt file which serves as description
	 * for directories and files
	 *
	 * @param	string		$path: Path of the dir
	 * @param	string		$type: Type of txt
	 * @return	the description
	 */	
	function getDescription($path, $type='') {
		$multilingual = ($GLOBALS['TSFE']->sys_language_uid > 0) ? '-'.$GLOBALS['TSFE']->sys_language_uid : '';

		if ($type=='dir') { // description of a directory
			$file = $path.'info'.$multilingual.'.txt';
		} else {	// description of a file
			$file = $path.$multilingual.'.txt';
		}
		
		if (is_file($file)) {
			$text = file_get_contents($file);
		}		
		return $text;
	}
	
	
	/**
	 * Get the internal page browser
	 *
	 * @param	arryay  $marker: existing markers
	 * @param	int  $count: Maximum count of elements
	 * @param	int  $limit: how many elements displayed
	 * @return markerarray with the pagebrowser
	 */	
	function getPageBrowserMarkers($marker, $count,$limit) {
		$this->internal['res_count']=$count;
		$this->internal['results_at_a_time']=$limit;
		$this->internal['maxPages']=18;
		$this->internal['dontLinkActivePage'] =0;
		$this->internal['showFirstLast']=0;
		$this->internal['pagefloat']='center';
		$this->internal['showRange']=0;
		$wrapArr = array(
			'browseBoxWrap' 	=> '|',
			'showResultsWrap' => '<span class="result">|</span>',
			'browseLinksWrap' => '<span class="links">|</span>',
			'showResultsNumbersWrap' => '|',
			'disabledLinkWrap' => '|',
			'inactiveLinkWrap' => '|',
			'activeLinkWrap' => '|'
		);
		
		$marker['###PAGEBROWSER###']=$this->pi_list_browseresults(0,'',$wrapArr, 'pointer', FALSE);
		$marker['###PAGEBROWSERTEXT###']=$this->pi_list_browseresults(2,'',$wrapArr, 'pointer', FALSE);       
		return $marker;
	}	

	/**
	 * Random view of an array and slice it afterwards, preserving the keys
	 *
	 * @param	array  $array: Array to modify
	 * @param	array  $offset: Where to start the slicing
	 * @param	array  $length: Length of the sliced array
	 * @return the randomized and sliced array
	 */
  function getSlicedRandomArray ($array,$offset,$length) {
    // shuffle
    $new_arr = array();
    while (count($array) > 0) {
      $val = array_rand($array);
      $new_arr[$val] = $array[$val];
      unset($array[$val]);
    }
    $result=$new_arr;
    
    // slice
    $result2 = array();
    $i = 0;
    if($offset < 0)
        $offset = count($result) + $offset;
    if($length > 0)
        $endOffset = $offset + $length;
    else if($length < 0)
        $endOffset = count($result) + $length;
    else
        $endOffset = count($result);
   
    // collect elements
    foreach($result as $key=>$value)
    {
        if($i >= $offset && $i < $endOffset) {
          $result2['random'][$key] = $value;
        } else {
          $result2['after'][$key] = $value;
        }
            
        $i++;
    }    return $result2;
  }       	
	

	/**
	 * The whole preconfiguration: Get the flexform values
	 *
	 * @param	array		$conf: The PlugIn configuration
	 * @return	void
	 */
	function init($conf) {
		$this->conf=$conf;		
		$this->pi_loadLL(); // Loading language-labels
		$this->pi_setPiVarDefaults(); // Set default piVars from TS
		$this->pi_initPIflexForm(); // Init FlexForm configuration for plugin
		
		// security check, pivars only need integers
		foreach($this->piVars as $ke=>$value) {
			$this->piVars[$key] = intval($value);
		}
		
		// add the flexform values
		$this->config['show']					= strtoupper($this->getFlexform('', 'show', 'mode'));
		$this->config['path']		 			= $this->checkPath($this->getFlexform('', 'path', 'path'));
		$this->config['description'] 	= $this->getFlexform('', 'description', 'description');
		$this->config['pagebrowser'] 	= $this->getFlexform('', 'pagebrowser', 'pagebrowser');
		$this->config['link'] 				= $this->getFlexform('', 'link', 'link');
		$this->config['random'] 			= $this->getFlexform('', 'random', 'random');
		$this->config['listTitle']		= $this->getFlexform('', 'title', 'title');
		$this->config['single']				= $this->getFlexform('', 'single', 'single');
		$this->config['renderAllLinks'] = $this->conf['gallery.']['renderAllLinks'];
		$this->config['exclude1stImg']	= ($this->conf['gallery.']['excludeFirstImage'] && $this->config['show']=='LIST' && $this->piVars['dir']!=0) ? 1: 0;		

		// create an array of subfolders
		$this->config['subfolders'] = $this->getFullDir($this->config['path']);
		
    // Template+  CSS file
		$this->templateCode = $this->cObj->fileResource($conf['templateFile']);
		$GLOBALS['TSFE']->additionalHeaderData['chgallery'] = (isset($this->conf['pathToCSS'])) ? '<link rel="stylesheet" href="'.$GLOBALS['TSFE']->tmpl->getFileName($this->conf['pathToCSS']).'" type="text/css" />' : '';
		
			// Ajax used? Embed js
		if ($this->conf['ajax']==1) {
				// include mootools library
			if (t3lib_extMgm::isLoaded('t3mootools'))    {
			 require_once(t3lib_extMgm::extPath('t3mootools').'class.tx_t3mootools.php');
			} 	 
			if (defined('T3MOOTOOLS')) {
				tx_t3mootools::addMooJS();
			} else {
			  $GLOBALS['TSFE']->additionalHeaderData['chgallery'].= $this->getPath($this->conf['pathToMootools']) ?  '<script src="'.$GLOBALS['TSFE']->tmpl->getFileName($this->conf['pathToMootools']).'" type="text/javascript"></script>' :'';
			}  
		}

		// configuration for ratings
		if (t3lib_extMgm::isLoaded('ratings') && $this->conf['ratings']==1) {
			require_once(t3lib_extMgm::extPath('ratings', 'class.tx_ratings_api.php'));

			$apiObj = t3lib_div::makeInstance('tx_ratings_api');			
			$ratingsTSConf = (is_array($this->conf['ratings.'])) ? $this->conf['ratings.'] : array();
			$this->rate['conf'] = t3lib_div::array_merge($apiObj->getDefaultConfig(), $ratingsTSConf);
			$this->rate['conf']['includeLibs'] = 'EXT:ratings/pi1/class.tx_ratings_pi1.php';
			$this->ratecObj = t3lib_div::makeInstance('tslib_cObj');
			$this->ratecObj->start(array());
		}
			
	}
	
	function checkPath($path) {
		$path = trim($path);
		if (!t3lib_div::validPathStr($path)) {
			return '';
		}
		
		if (substr($path,-1)!='/') {
      $path =  $path.'/';
		}

		return $path;
	}

	/**
	 * Get the value out of the flexforms and if empty, take if from TS
	 *
	 * @param	string		$sheet: The sheed of the flexforms
	 * @param	string		$key: the name of the flexform field
	 * @param	string		$confOverride: The value of TS for an override
	 * @return	string	The value of the locallang.xml
	 */
	function getFlexform ($sheet, $key, $confOverride='') {
		// Default sheet is sDEF
		$sheet = ($sheet=='') ? $sheet = 'sDEF' : $sheet;
		$flexform = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], $key, $sheet);
		
		// possible override through TS
		if ($confOverride=='') {
			return $flexform;
		} else {
			$value = $flexform ? $flexform : $this->conf[$confOverride];
			$value = $this->cObj->stdWrap($value,$this->conf[$confOverride.'.']);
			return $value;
		}
	} 
	

	/**
	 * The xml method of the PlugIn. Used for the ajax connection to get the gallery pages
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The single gallery page
	 */
	function xmlFunc($content,$conf)	{		
		$this->init($conf);

		if ($this->conf['ajax']==1) {
			$pb = intval(t3lib_div::GPvar('pb'));
	
				// page browser 
			$begin 	= $pb * $this->config['pagebrowser'];
			$end 		= $begin + $this->config['pagebrowser'];
			$this->piVars['pointer'] = $pb;
	
			$content = $this->getSingleGalleryPage($pb, $begin, $end, $ajax=1);
			
			$xml .= '<tab>'.$content.'</tab>';
		} else {
			$xml .= '<p><b>Ajax is not activated!</b></p>';
		}

		return $xml;
	}		
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/chgallery/pi1/class.tx_chgallery_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/chgallery/pi1/class.tx_chgallery_pi1.php']);
}

?>
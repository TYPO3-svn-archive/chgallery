<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Georg Ringer <g.ringer@cyberhouse.at>
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


    // DEFAULT initialization of a module [BEGIN]
$BACK_PATH = '../../../../typo3/';
define('TYPO3_MOD_PATH', '../typo3conf/ext/chgallery/wizard/');


// by RICC
if (!ereg('typo3conf', $_SERVER['PHP_SELF'])) {
  $BACK_PATH = '../../../';
  define('TYPO3_MOD_PATH', 'ext/chgallery/wizard/');
} else {
  $BACK_PATH = '../../../../typo3/';
  define('TYPO3_MOD_PATH', '../typo3conf/ext/chgallery/wizard/');
}
// end by RICC

$MCONF['name']='xMOD_tx_chgallery_wizard';
$MCONF['script']='index.php';

require_once ($BACK_PATH.'init.php');
require_once ($BACK_PATH.'template.php');
require_once (PATH_t3lib.'class.t3lib_scbase.php');
$LANG->includeLLFile('EXT:chgallery/wizard/locallang.xml');

    // ....(But no access check here...)
    // DEFAULT initialization of a module [END]



/**
 * chgallery module tx_chgallery_image_aassawiz0
 *
 * @author    Georg Ringer <g.ringer@cyberhouse.at>
 * @package    TYPO3
 * @subpackage    tx_chgallery
 */

class tx_chgallery_wizard extends t3lib_SCbase {

    /**
     * Main function of the wizard: Displays all images of a dir
     *
     * @return  string the wizards content
     */
    function moduleContent()    {
      global $LANG;
			$vars = t3lib_div::_GET('P');
			
			// error checks
			$error = array();
			// check if CE has been saved once!
			if (intval($vars['uid'])==0) {
				$error[] = $LANG->getLL('error-neversavesd');
			} else {
					// get the single record
				$where = 'uid='.intval($vars['uid']).' AND deleted=0';
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($vars['field'], $vars['table'], $where);
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				
					// read the flexform settings and transform it to array
				$flexformArray = t3lib_div::xml2array($row[$vars['field']]);
				$flexformArray = $flexformArray['data']['sDEF']['lDEF'];
				
					// get all the infos we need
				$path 						= trim($flexformArray['path']['vDEF']);
				$description 			= $flexformArray['description']['vDEF'];
				$descriptionList 	= explode(chr(10),$description);
				$pagebrowser 			= $flexformArray['pagebrowser']['vDEF'];
			}
			
			if ($path=='') {
				$error[] = $LANG->getLL('error-path');
			} 
			/*elseif(!is_dir($path)) {
				$error[] = $LANG->getLL('error-pathwrong');
			}*/
			
			// any error occured?
			if (count($error)>0) {
				foreach ($error as $single) {
    			$errors.= '<li>'.$single.'</li>';
    		}
				$content.= '<h2>'.$LANG->getLL('error-header').'</h2>
										<div style="padding:10px;margin:10px;border:1px solid darkorange;font-style:bold;">
											<ul>'.$errors.'</ul>
										</div>	
										<a href="javascript:close();">'.$LANG->getLL('close').'</a>
				'; 
			}
			
			 else {
	      
	      	// get all the images from the directory
				$fileTypes = 'jpg,gif,png';
				$imageList = t3lib_div::getFilesInDir(PATH_site.$path, $fileTypes,1,1);
				
					// oputput of the image list
				$content.= '<h2>'.sprintf($LANG->getLL('images'), count($descriptionList), $path).'</h2>';
				$content.= '<table cellpadding="1" cellspacing="1" class="bgColor4" width="100%" id="el">';
	
				$i=0;			
				foreach ($imageList as $key=>$singleImage) {
					$thumb = $this->getThumbNail($singleImage, 100);
					$fileName = basename($singleImage);
					$desc = $descriptionList[$i];
					
					$content.= '<tr class="'.($i++ % 2==0 ? 'bgColor3' : 'bgColor4').'">
												<td align="center">'.$thumb.'</td>
												<td>#'.$i.': <strong>'.$fileName.'</strong><br /><br />
														<input type="text" value="'.$desc.'" style="width:330px;" /></td>
											</tr>';
					
						// display a cutting line to show where a new page would begin
					if ($pagebrowser>0 && $i%$pagebrowser==0) {
					$content.= '<tr>
												<td colspan="2" align="center"><strong>- - - - - - - - - - - - &#9985; - - - - - - - - - - - - - - - - - - - &#9985; - - - - - - - - - - - -</strong></td>
											</tr>';
					}
	
				}
				$content.='</table>';
				
					// save the image titles, popup will be closes after submit
				$content.= '
					<form action="" onsubmit="save();" method="get" name="editform">
						<textarea id="all" style="width:440px;height:150px;">'.$description.'</textarea>
						<div id="send" style="margin:5px 10px;">
							<input type="submit" value="'.$LANG->getLL('save').'" />
						</div>
					</form>
				';
			}


				
				// return everything
			$this->content.=$this->doc->section('',$content,0,1);
    }


		/**
		 * Returns a Thumbnail with maximum dimension of 100pixels
		 *
		 * @param string	The file
		 * @return string	The image-tag
		 */
		function getThumbNail($file, $size=100) {
			global $BACK_PATH;
			return t3lib_BEfunc::getThumbNail($BACK_PATH.'thumbs.php', $file, '', $size);
		}
	

    /**
     * Adds items to the ->MOD_MENU array. Used for the function menu selector.
     *
     * @return    [type]        ...
     */
    function menuConfig()    {
        global $LANG;
        $this->MOD_MENU = Array (
            'function' => array (
                '1' => $LANG->getLL('function1'),
            )
        );
        parent::menuConfig();
    }

    /**
     * Main function of the module. Write the content to $this->content
     *
     * @return    [type]        ...
     */
    function main()    {
        global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		  	$P = $var = t3lib_div::_GP('P');
		  
            // Draw the header.
        $this->doc = t3lib_div::makeInstance('mediumDoc');
        $this->doc->backPath = $BACK_PATH;

            // JavaScript
        $this->doc->JScode = '
            <script language="javascript" type="text/javascript">
                script_ended = 0;
                function jumpToUrl(URL)    {
                    document.location = URL;
                }
            </script>
						<script type="text/javascript">
						/*<![CDATA[*/

						function save() {
							var form = document.getElementById("el");
							var p = document.getElementById("all");
							var elements = form.getElementsByTagName("input");
							 
							p.value = "";
							 
        			for(i=0;i<elements.length;i++) {
 								p.value +=  "\r"+elements[i].value;
 							}

							
							updateValue(p.value);
							return false;
						}
						function updateValue(fieldValue) {
							updateValueInMainForm(fieldValue);
							close();
						}
						
						function checkReference() {
							return window.opener.document.editform["data[tt_content]['.$P['uid'].'][pi_flexform][data][sDEF][lDEF][description][vDEF]"];
						
							if (window.opener && window.opener.document && window.opener.document.editform && window.opener.document.editform["data['.$P["table"].']['.$P["uid"].']['.$P["field"].']"] )	{
								//return window.opener.document.editform["data['.$P["table"].']['.$P["uid"].']['.$P["field"].']"];					
							} else {
								close();
							}
						}
						function updateValueInMainForm(fieldValue) {
							var field = checkReference();
							if (field)	{
								field.innerHTML = fieldValue;
							}
						}
					
						/*]]*/
						</script>
        ';

        $this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
        $access = is_array($this->pageinfo) ? 1 : 0;
        if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id) || ($BE_USER->user["uid"] && !$this->id))    {
            if ($BE_USER->user['admin'] && !$this->id)    {
                $this->pageinfo = array(
                        'title' => '[root-level]',
                        'uid'   => 0,
                        'pid'   => 0
                );
            }

            $headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath']).'<br />'
                    .$LANG->sL('LLL:EXT:lang/locallang_core.xml:labels.path').': '.t3lib_div::fixed_lgd_pre($this->pageinfo['_thePath'], 50);

            $this->content.=$this->doc->startPage($LANG->getLL('title'));



            // Render content:
            $this->moduleContent();

        }
        $this->content.=$this->doc->spacer(10);
    }

    /**
     * [Describe function...]
     *
     * @return    [type]        ...
     */
    function printContent()    {
        $this->content.=$this->doc->endPage();
        echo $this->content;
    }
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/chgallery/wizard/index.php'])    {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/chgallery/wizard/index.php']);
}

// Make instance:
$SOBE = t3lib_div::makeInstance('tx_chgallery_wizard');
$SOBE->init();


$SOBE->main();
$SOBE->printContent();

?>

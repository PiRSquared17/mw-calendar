<?php
# Confirm MW environment
if (defined('MEDIAWIKI')) {

$wgExtensionFunctions[] = "wfDateConverter";

function wfDateConverter() {
	global $wgParser;
	global $wgParser, $wgHooks;
	global $wgCalendarSidebarRef;
	
	$wgParser->setHook( "dateconverter", "convertCalendarDates" );
}

//CalendarEvents:NSC Interface Calendar/Public/1-1-2008 -Event 0
function convertCalendarDates( $paramstring, $params = array() ){

	$converter = new convertCalendarDates;
	
	if( !isset($params['limit'] ) ) $params['limit'] = 100000;
	if( !isset($params['newformat'] ) ) $params['newformat'] = 'YYYYMMDD';
	
	$ret = $converter->convert(	$params['newformat'],
								$params['pagename'],
								$params['calname'],
								isset($params['redirect']),
								$params['limit'] );
	
	return $ret;
}

class convertCalendarDates
{
	function convert($newFormat, $pageName, $calName, $redirect, $limit){
		$search = "$pageName/$calName";
		$pages = PrefixSearch::titleSearch( $search, $limit);
		$count=$redirectsIgnored=$erroredCount = 0;
		$errored = '';

		foreach($pages as $page) {
			$retval = false;
			$newPage = $this->convertToNewPage($page, $newFormat);			
			
			$article = new Article(Title::newFromText($page));
			if(!$article->exists()) $newPage = "";	
			
			if($newPage != ''){
				$fromTitle = Title::newFromText($page);
				$toTitle = Title::newFromText($newPage);
				
				if( !$article->isRedirect() ){
					$count +=1;
					$retval = $fromTitle->moveTo($toTitle, true, 'CalendarConversion', $redirect);
					
					if($retval != true) $errored .=  '&nbsp;&nbsp;' . $page . '<br>';
				}else{
					$redirectsIgnored +=1;
				}
			}else{
				$erroredCount +=1;
				$errored .=  '&nbsp;&nbsp;' . $page . '<br>';
			}
		}
		unset($pages);	
		
		$ret = $count + $redirectsIgnored + $erroredCount . " total events found in <b>$search</b><br><br>";
		$ret .= "<b>Successfully converted $count events!</b><br>";
		$ret .= "<b>Redirects Ignored $redirectsIgnored</b><br><br>";
		$ret .= "<b>The following $erroredCount pages were not converted:</b><br>$errored";
	
		
		return $ret;
	}
	
	function convertToNewPage($page, $newFormat){
		$arrPage= explode('/',$page);
		$dateStr = trim( array_pop($arrPage) ); //get last element
		
		$arrDateElements = explode('-',$dateStr); //arr[0]=month, arr[1]=day, arr[2]=year, arr[3]=eventid
		
		if(count($arrDateElements) != 4) return '';
		
		$eventID = array_pop($arrDateElements); 
		
		$newDate = $this->userDateFormat(	trim($arrDateElements[0]), 
											trim($arrDateElements[1]), 
											trim($arrDateElements[2]), 
											trim($newFormat) );
		
		$newDate .= " -$eventID";
		
		$arrPage[] = $newDate;
		$ret = implode('/', $arrPage);
		
		return $ret;
	}
	
	function userDateFormat($month, $day, $year, $format='') {

		if($format == '') $format = 'YYYYMMDD'; //default

		$format = str_ireplace('YYYY',$year,$format);
		$format = str_ireplace('MM', str_pad($month, 2, '0', STR_PAD_LEFT), $format);
		$format = str_ireplace('DD', str_pad($day, 2, '0', STR_PAD_LEFT), $format);
		
		if( stripos($format,'SM') !== false || stripos($format,'LM') !== false ){
			$format = str_ireplace('SM', Common::translate($month, 'month_short'), $format);
			$format = str_ireplace('LM', Common::translate($month, 'month'), $format);
		}else{
			$format = str_ireplace('M',$month,$format);
			$format = str_ireplace('D',$day,$format);
		}

		return $format;
	}
}

}//end mediawiki environment













//
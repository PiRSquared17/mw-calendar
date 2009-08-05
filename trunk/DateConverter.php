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
	$ret = $converter->convert($params['newformat'],$params['pagename'],$params['calname'],!$params['noredirect']);
	
	return $ret;
	
}

class convertCalendarDates
{
	function convert($newFormat, $pageName, $calName, $redirect){
		$search = "$pageName/$calName";
		$pages = PrefixSearch::titleSearch( $search, 10);
		$count=0;

		foreach($pages as $page) {
			$newPage = $this->convertToNewPage($page, $newFormat);			
			$temp .='<br>'.$page;
			
			$fromTitle = Title::newFromText($page);
			$toTitle = Title::newFromText($newPage);
			
			//$retval = $fromTitle->moveTo($toTitle, true, 'CalendarConversion', $redirect);
		}
		unset($pages);	
		
		return $temp;
	}
	
	function convertToNewPage($page, $newFormat){
		$arrPage= explode('/',$page);
		$dateStr = trim( array_pop($arrPage) ); //get last element
		
		$arrDateElements = explode('-',$dateStr); //arr[0]=month, arr[1]=day, arr[2]=year, arr[3]=eventid
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
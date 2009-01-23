<?php

# only none dependent functions here...basically reusable helper functions
# might make this a helper class later.
#

function checkForMagicWord($string){
	global $wgParser;
	
	$ret = $string;
	$string = str_replace("{{","",$string);
	$string = str_replace("}}","",$string);
	$string = strtolower($string);

	$string = $wgParser->getVariableValue($string);
	
	if(isset($string)) $ret = $string;
	
	return $ret;
}

function datemath($dayOffset, $month, $day, $year){

	$seconds = $dayOffset * 86400;
	$arr = getdate(mktime(12, 0, 0, $month, $day, $year) + $seconds);

	return $arr;
}

function cleanWiki($text){

	$text = swapWikiToHTML($text, "'''", "b");
	$text = swapWikiToHTML($text, "''", "i");
	$text = swapWikiToHTML($text, "<pre>", "");
	$text = swapWikiToHTML($text, "</pre>", "");

	return $text;
}

//basic tage changer for common wiki tags
function swapWikiToHTML($text, $tagWiki, $tagHTML){

	$ret = $text;

	$lenWiki = strlen($tagWiki);
	$pos = strpos($text, $tagWiki);
	if($pos !== false){
		if($tagHTML != ""){
			$ret = substr_replace($text, "<$tagHTML>", $pos, $lenWiki);
				$ret = str_replace($tagWiki, "</$tagHTML>", $ret);
		}
		else
			$ret = str_replace($tagWiki, "", $ret);
	}

	return $ret;
}	

function limitText($text,$max) { 
	if($max == "") return;
	
	$text = trim($text);
	
	if(strlen($text) > $max)
		$ret = substr($text, 0, $max) . "...";
	else
		$ret = $text;

	return $ret;
} 


function getDaysInMonth($month, $year) {
	
	// 't' = Number of days in the given month	
	return date('t', mktime(0, 0, 0, $month, 1, $year)); 
}

function getDateArr($month, $day, $year, $hour=0, $minutes=0, $seconds=0, $add_seconds=0){

	return getdate(mktime($hour, $minutes, $seconds, $month, $day, $year) + $add_seconds);
}

function getNextValidDate(&$month, &$day, &$year){

	$seconds = 86400; //1 day
	$arr = getdate(mktime(12, 0, 0, $month, $day, $year) + $seconds);
	
	$day = $arr['mday'];
	$month = $arr['mon'];
	$year = $arr['year'];
	
	return $arr;
}

function day_diff($date1, $date2){

	if(!isset($date2)) return 0;

	$start = mktime($date1['hours'], $date1['minutes'], $date1['seconds'], $date1['mon'], $date1['mday'], $date1['year']);
	$end = mktime($date2['hours'], $date2['minutes'], $date2['seconds'], $date2['mon'], $date2['mday'], $date2['year']);

	return ($end - $start) / 86400; //seconds
	
}

function wdayOffset($month, $year, $weekday){

	$timestamp = mktime(0, 0, 0, $month, 1, $year);
	$max_days = date('t', $timestamp) +7;	
	$the_first = getdate($timestamp);
	$wday = $the_first["wday"];	
	
	$offset = $weekday - $wday;
	$weeks = floor(($max_days - $offset)/7); 
	
	$arr['offset'] = $offset;
	$arr['maxdays'] = $max_days -7;
	$arr['weeks'] = $weeks;
	
	return $arr;
}



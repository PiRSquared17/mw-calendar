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
	return date('t', mktime(12, 0, 0, $month, 1, $year)); 
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

// get the offset info based on the 1st of the month
function wdayOffset($month, $year, $weekday){

	$timestamp = mktime(12, 0, 0, $month, 1, $year);
	$max_days = date('t', $timestamp);	
	$the_first = getdate($timestamp);
	$wday = $the_first["wday"];	
	
	$offset = ($weekday - $wday) +1; //relate $wday as a negative number
	$month_offset = (7 + $offset);
	
	$weeks = 4;
	
	// this $weekday is before the 1st
	if($offset <= 0 )
		if( ($month_offset + 28) <= $max_days)  $weeks = 5;
	
	// this $weekday is after the 1st
	if($offset > 0 )
		if( ($month_offset + 21) <= $max_days)  $weeks = 5;

	$arr['offset'] = $offset; // delta between the 1st and the $weekday parameter(0-sun, 1-mon, etc)
	$arr['maxdays'] = $max_days; //days in month
	$arr['weeks'] = $weeks; //max weeks this weekday has
	
	return $arr;
}



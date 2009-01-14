<?php

/* Calendar.php
 *
 ***** Verion 3.0 *****
 *
 * - Eric Fortin (12/1/2008) < kenyu73@gmail.com >
 *
 * - Original author(s):
 *   	Simson L. Garfinkel < simsong@acm.org >
 *   	Michael Walters < mcw6@aol.com > 
 * 		http://www.mediawiki.org/wiki/User:Hex2bit/Calendar
 *
 * See Readme file for full details
*/

// this is the "refresh" code that allows the calendar to switch time periods
if (isset($_POST["today"]) || isset($_POST["yearBack"]) || isset($_POST["yearForward"]) || isset($_POST["monthBack"])
	|| isset($_POST["monthForward"]) || isset($_POST["listmonth"]) || isset($_POST["yearSelect"])){

	$today = getdate();    	// today
	$temp = split("`", $_POST["calendar_info"]);	

	// set the initial values
	$month = $temp[0];
	$year = $temp[1];	
	$title =  $temp[2];
	$name =  $temp[3];
	$referrerURL = $temp[4];
	
	// the yearSelect and monthSelect must be on top... the onChange triggers  
	// whenever the other buttons are clicked
	if(isset($_POST["yearSelect"])) $year = $_POST["yearSelect"];	
	if(isset($_POST["monthSelect"])) $month = $_POST["monthSelect"];

	if(isset($_POST["yearBack"])) --$year;
	if(isset($_POST["yearForward"])) ++$year;	

	if(isset($_POST["today"])){
		$month = $today['mon'];
		$year = $today['year'];
	}	

	if(isset($_POST["monthBack"])){
		$year = ($month == 1 ? --$year : $year);	
		$month = ($month == 1 ? 12 : --$month);
	}
	
	if(isset($_POST["monthForward"])){
		$year = ($month == 12 ? ++$year : $year);		
		$month = ($month == 12 ? 1 : ++$month);
	}

	// generate the cookie name
	$cookie_name = 'calendar_' . str_replace(' ', '_', $title) . str_replace(' ', '_', $name);

	//save the calendar back into the session
	setcookie($cookie_name, $month . "`" . $year . "`" . $title . "`" . $name, 0, '/', '');
	
	// reload the calling page to refresh the cookies that were just set
	header("Location: " . $referrerURL);
}

# Confirm MW environment
if (defined('MEDIAWIKI')) {

# Credits	
$wgExtensionCredits['parserhook'][] = array(
    'name'=>'mwCalendar',
    'author'=>'Eric Fortin(contributor)',
    'url'=>'',
    'description'=>'MediaWiki Calendar',
    'version'=>'3.0'
);
	
$wgExtensionFunctions[] = "wfCalendarExtension";

// function adds the wiki extension
function wfCalendarExtension() {
    global $wgParser;
    $wgParser->setHook( "calendar", "displayCalendar" );
}

class Calendar
{  
    // [begin] set calendar parameter defaults
	var $title = "Calendar"; 
	var $name = "Public";
	var $enableTemplates = false;
	var $showAddEvent = true;    
	var $defaultEdit = false;	
	var $yearOffset= 5;
	var $trimCount = 20; // this is the line limit for listed events
	// [end] set calendar parameter defaults
	

	// setup calendar arrays
    var $daysInMonth = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);   
    var $dayNames   = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");	
    var $monthNames = array("January", "February", "March", "April", "May", "June",
                            "July", "August", "September", "October", "November", "December");


    function Calendar($wgCalendarPath) {
		
		// quick on/off for debug tags that may still be lurking around...
		$this->debugMode = true;
		
		$today = getdate();    	// set the calendar's date
		$this->month = $today['mon'];
		$this->year = $today['year'];
		$this->day = $today['mday'];
		
		// static date to reference (dont update these!)
		$this->monthStatic = $today['mon'];
		$this->yearStatic = $today['year'];
		$this->dayStatic = $today['mday'];
		
		// set paths	
		$extensionFolder = "extensions/Calendar/"; //default
		$wikiBasePath = str_replace("index.php", "", $_SERVER['SCRIPT_FILENAME']);
		$wikiBaseURL = str_replace("index.php", "", $_SERVER['SCRIPT_NAME']);
		
		$extensionPath = $wikiBasePath . $extensionFolder;
		$extensionURL = $wikiBaseURL . $extensionFolder;

		// allows manual override of the physical extension paths -todo
		if(strlen($wgCalendarPath > 0))
			$extensionPath = $wgCalendarPath;
		
		$this->html_template = file_get_contents($extensionPath . "calendar_template.html");

		$this->daysNormalHTML   = $this->html_week_array("<!-- %s %s -->");
		$this->daysSelectedHTML = $this->html_week_array("<!-- Selected %s %s -->");
		$this->daysMissingHTML  = $this->html_week_array("<!-- Missing %s %s -->");
    }
	
	// write debug info to specified folder (prob windows only?)
	function debug($e){
		if($this->debugMode){
			$fp = fopen('c:\\data.txt', 'at');
			fwrite($fp, $e . chr(10));
			fclose($fp);	
		}
	}
	
    function html_week_array($format){
		$ret = array();
		for($i=0;$i<7;$i++){
			$ret[$i] = $this->searchHTML($this->html_template,
						 sprintf($format,$this->dayNames[$i],"Start"),
						 sprintf($format,$this->dayNames[$i],"End"));
		}
		return $ret;
    }

    function getDaysInMonth($year,$month) {	// Leap year rule good through 3999
        if ($month < 1 || $month > 12) return 0;
        $d = $this->daysInMonth[$month - 1];
        if ($month == 2 && $year%4==0) {
			$d = 29;
			if ($year%100 == 0 && $year%400 != 0) $d = 28;
		}
        return $d;
    }

    function buildNewEvent($month, $day, $year) {
    	$articleName = "";    	// the name of the article to check for
    	$articleCount = 1;    	// the article count
	
		// Loop for articles
		$tempArticle = "CalendarEvents:" . $this->name . "_";
		$tempArticle .= "(" . $month . "-" . $day . "-" . $year . ")_-_Event_";
		$articleName = $tempArticle . $articleCount;

		$article = new Article(Title::newFromText($articleName));		
		for ($i = 0; $i <= 20; $i++) {
			// dont care about the articles here, just need to get the next event number
			while ($article->exists() && $articleCount < 20) {		
				// increment count
				$articleCount += 1;
				$articleName = $tempArticle . $articleCount;
				$article = new Article(Title::newFromText($articleName));
			}
		}
		$newURL = "<a href=\"" . $this->wikiRoot . urlencode($tempArticle . $articleCount) . "&action=edit\"><u>Add Event</u></a>";

		return $newURL;
	}
  
    // Generate the HTML for a given month
    // $day may be out of range; if so, give blank HTML
    function getHTMLForDay($month,$day,$year){
		if ($day <=0 || $day > $this->getDaysInMonth($year,$month)){
			return $this->daysMissingHTML[0];
		}

		$thedate = getdate(mktime(12, 0, 0, $month, $day, $year));
		$today = getdate();
		$wday  = $thedate['wday'];

		if ($thedate['mon'] == $today['mon']
			&& $thedate['year'] == $today['year']
			&& $thedate['mday'] == $today['mday']) {
			$tempString = $this->daysSelectedHTML[$wday];
		}
		else {
			$tempString = $this->daysNormalHTML[$wday];
		}
					
		// add event link value
		if($this->showAddEvent){
			$tag_addEvent = $this->buildNewEvent($month, $day,$year);
		}
		else {
			$tag_addEvent = "";
		}
		
		$tag_eventList = "<ul>";
		
		/* DISABLED the "virtual events" as they can't work for "day view" mode 
			because we need to build virtual events based on the begin date..."day mode"
			only pulls that one singe day, so the main event would be missed */			
		// search through the "virtual" memory array of multi-calendar events... this saves Wikidb space	
/*		for($z=0; $z<count($this->virtualArticle);$z++){
			$strDate = $month . "-" . $day . "-" . $year;
			if(strpos($this->virtualArticle[$z], $strDate) > 0){
				$tag_eventList .= "<li>" . $this->virtualArticle[$z] . "</li>\n";
			}
		}	
*/		
		//load templates if enabled (todo: maybe make this a memory array)
		if($this->enableTemplates){
			$eventArticle = $this->getTemplateEvents($month, $day, $year);
			if(strlen($eventArticle) > 0)
				$tag_eventList .= $eventArticle;
		}

		// event list tag
		$events = $this->getArticlesForDay($month, $day, $year);
		if (count($events) > 0) {
			for ($k = 0; $k < count($events); $k++){
				$summaries = $this->getSummariesForArticle($events[$k]);
				for($j = 0; $j < count($summaries); $j++){
					//$summaries[$j] = $this->addMultiDay($summaries[$j], $month, $day, $year, $j);
					if(strlen($summaries[$j]) > 0)
						$tag_eventList .= "<li>" . $summaries[$j] . "</li>\n";
				}
			}
		}	
		$tag_eventList .= "</ul>";
		
		// replace variable tags in the string
		if($this->dateParameter)
			$tempString = str_replace("[[Day]]", "", $tempString);
		else
			$tempString = str_replace("[[Day]]", $day, $tempString);
		
		$tempString = str_replace("[[AddEvent]]", $tag_addEvent, $tempString);
		$tempString = str_replace("[[EventList]]", $tag_eventList, $tempString);
		
		return $tempString;
    }

	function getTemplateEvents($month, $day, $year){
		$ret = "";

		$articleName = "CalendarEvents:" . $this->name . 
		"_(" . $this->month . "-" . $this->year . ")-Template";
	
		$article = new Article(Title::newFromText($articleName));

		if ($article->exists()) {	    // save name
			$displayText  = $article->fetchContent(0,false,false);
		}
		
		$arrAllEvents=split(chr(10),$displayText);
		if (count($arrAllEvents) > 0){
			for($i=0; $i<count($arrAllEvents); $i++){
				$arrEvent = split("#",$arrAllEvents[$i]);
				if(strlen($arrEvent[1]) > 0){
					$arrRepeat = split("-",$arrEvent[0]);
					if($arrRepeat[0] <= $day){
						if($arrRepeat[1] >= $day || $arrRepeat[0] == $day)
							$ret .= "<li>" . $this->buildTemplateEvent($this->month, $day, $this->year, $arrEvent[1]) . "</li>";
					}
				}
			}
		}
		return $ret;
	}
	
    function buildTemplateEvent($month, $day, $year, $articleName) {
	
		// Loop for articles
		$article = "CalendarEvents:";
		$article .= $this->name . "_";			
		$article .= "(" . $month . "-" . $year .")-Template";

		$newURL = "<a href=\"" . $this->wikiRoot . urlencode($article) . "&action=edit\">" . $articleName . "</a>";
		return $newURL;
	}

	/*
	function addMultiDay($eventSummary, $month, $day, $year, $index){
		$arrLink = split(">",$eventSummary);
		$arrEvent = split("!!",$arrLink[1]);
		if (count($arrEvent) > 1){

			for($i=1;$i<$arrEvent[0];$i++){
				$arrEvent[1] = str_replace("</a", "", $arrEvent[1]);
				$articleName = $this->name . "_";			
				$articleName .= "(" . $month . "-" . ($day + $i) . "-" . $year . ")_-_Event_Repeating" . $i;		
				$this->virtualArticle[] = "<!--" . $articleName . "-->" . $arrEvent[1] . " (r)";
			}
			$strValue = $arrEvent[0] . "!!";
			$normalURL = $arrLink[0] . ">" . str_replace($strValue, "", $arrLink[1]) . ">";
			
			return $normalURL;
		}	
		return $eventSummary;
	}
*/	
    function getHTMLForMonth() {   
       	
	    /***** Replacement tags *****/

	    $tag_monthSelect = "";         // the month select box [[MonthSelect]] 
	    $tag_previousMonthButton = ""; // the previous month button [[PreviousMonthButton]]
	    $tag_nextMonthButton = "";     // the next month button [[NextMonthButton]]
	    $tag_yearSelect = "";          // the year select box [[YearSelect]]
	    $tag_previousYearButton = "";  // the previous year button [[PreviousYearButton]]
	    $tag_nextYearButton = "";      // the next year button [[NextYearButton]]
	    $tag_calendarName = "";        // the calendar name [[CalendarName]]
	    $tag_calendarMonth = "";       // the calendar month [[CalendarMonth]]
	    $tag_calendarYear = "";        // the calendar year [[CalendarYear]]
	    $tag_day = "";                 // the calendar day [[Day]]
	    $tag_addEvent = "";            // the add event link [[AddEvent]]
	    $tag_eventList = "";           // the event list [[EventList]]
        
	    /***** Calendar parts (loaded from template) *****/

	    $html_calendar_start = "";     // calendar pieces
	    $html_calendar_end = "";
	    $html_header = "";             // the calendar header
	    $html_day_heading = "";        // the day heading
	    $html_week_start = "";         // the calendar week pieces
	    $html_week_end = "";
	    $html_footer = "";             // the calendar footer

	    /***** Other variables *****/

	    $ret = "";          // the string to return

	    // the date for the first day of the month
	    $firstDate = getdate(mktime(12, 0, 0, $this->month, 1, $this->year));

	    $first = $firstDate["wday"];   // the first day of the month

	    $today = getdate();    	// today's date
	    $isSelected = false;    	// if the day being processed is today
	    $isMissing = false;    	// if the calendar cell being processed is in the current month

	    // referrer (the page with the calendar currently displayed)
	    $referrerURL = $_SERVER['PHP_SELF'];
	    if ($_SERVER['QUERY_STRING'] != '') {
    		$referrerURL .= "?" . $_SERVER['QUERY_STRING'];
	    }

	    /***** Build the known tag elements (non-dynamic) *****/
	    // set the month's name tag
	    $tag_calendarName = str_replace('_', ' ', $this->name);
	    if ($tag_calendarName == "") {
    		$tag_calendarName = "Public";
	    }
    	
	    // set the month's mont and year tags
	    $tag_calendarMonth = $this->monthNames[$this->month - 1];
	    $tag_calendarYear = $this->year;
    	
	    // build the month select box
	    $tag_monthSelect = "<select name='monthSelect' method='post' onChange='javascript:this.form.submit()'>";
	    for ($i = 0; $i < count($this->monthNames); $i += 1) {
    		if ($i + 1 == $this->month) {
		    $tag_monthSelect .= "<option value=\"" . ($i + 1) . "\" selected=\"true\">" . 
			$this->monthNames[$i] . "</option>\n";
    		}
    		else {
		    $tag_monthSelect .= "<option value=\"" . ($i + 1) . "\">" . 
			$this->monthNames[$i] . "</option>\n";
    		}
	    }
	    $tag_monthSelect .= "</select>";
    	
	    // build the year select box, with +/- 5 years in relation to the currently selected year
	    $tag_yearSelect = "<select name='yearSelect' method='post' onChange='javascript:this.form.submit()'>";
		for ($i = ($this->year - $this->yearOffset); $i <= ($this->year + $this->yearOffset); $i += 1) {
    		if ($i == $this->year) {
				$tag_yearSelect .= "<option value=\"" . $i . "\" selected=\"true\">" . 
				$i . "</option>\n";
    		}
    		else {
				$tag_yearSelect .= "<option value=\"" . $i . "\">" . $i . "</option>\n";
    		}
	    }
	    $tag_yearSelect .= "</select>";
    	
		if($this->enableTemplates){
			// build the 'template' button
			$articleName = $this->wikiRoot . "CalendarEvents:" . $tag_calendarName .
				"_(" . $this->month . "-" . $this->year . ")-Template&action=edit". "';\">";
			
			$tag_templateButton = "<input type=\"button\" value= \"template load\" onClick=\"javascript:document.location='" . $articleName;
		}
	
		// build the 'today' button	
		$tag_HiddenData = "<input type='hidden' name='calendar_info' value='"
			. $this->month . "`"
			. $this->year . "`"
			. $this->title . "`"
			. $this->name . "`"
			. $referrerURL
			. "'>";
		
	    $tag_todayButton = "<input name='today' type='submit' value='today'>";
		$tag_previousMonthButton = "<input name='monthBack' type='submit' value='<<'>";
		$tag_nextMonthButton = "<input name='monthForward' type='submit' value='>>'>";
		$tag_previousYearButton = "<input name='yearBack' type='submit' value='<<'>";
		$tag_nextYearButton = "<input name='yearForward' type='submit' value='>>'>";

	    // grab the HTML for the calendar
	    // calendar pieces
	    $html_calendar_start = $this->searchHTML($this->html_template, 
						     "<!-- Calendar Start -->", "<!-- Header Start -->");
	    $html_calendar_end = $this->searchHTML($this->html_template,
						   "<!-- Footer End -->", "<!-- Calendar End -->");;
	    // the calendar header
	    $html_header = $this->searchHTML($this->html_template,
					     "<!-- Header Start -->", "<!-- Header End -->");
	    // the day heading
	    $html_day_heading = $this->searchHTML($this->html_template,
						  "<!-- Day Heading Start -->",
						  "<!-- Day Heading End -->");
	    // the calendar week pieces
	    $html_week_start = $this->searchHTML($this->html_template,
						 "<!-- Week Start -->", "<!-- Sunday Start -->");
	    $html_week_end = $this->searchHTML($this->html_template,
					       "<!-- Saturday End -->", "<!-- Week End -->");
	    // the individual day cells
        
	    // the calendar footer
	    $html_footer = $this->searchHTML($this->html_template,
					     "<!-- Footer Start -->", "<!-- Footer End -->");
    	
	    /***** Begin Building the Calendar (pre-week) *****/    	
	    // add the header to the calendar HTML code string
	    $ret .= $html_calendar_start;
	    $ret .= $html_header;
	    $ret .= $html_day_heading;
    	
	    /***** Search and replace variable tags at this point *****/
		$ret = str_replace("[[TodayData]]", $tag_hiddenData, $ret);
	
		$ret = str_replace("[[TemplateButton]]", $tag_templateButton, $ret);
		$ret = str_replace("[[TodayButton]]", $tag_todayButton, $ret);
	    $ret = str_replace("[[MonthSelect]]", $tag_monthSelect, $ret);
	    $ret = str_replace("[[PreviousMonthButton]]", $tag_previousMonthButton, $ret);
	    $ret = str_replace("[[NextMonthButton]]", $tag_nextMonthButton, $ret);
	    $ret = str_replace("[[YearSelect]]", $tag_yearSelect, $ret);
	    $ret = str_replace("[[PreviousYearButton]]", $tag_previousYearButton, $ret);
	    $ret = str_replace("[[NextYearButton]]", $tag_nextYearButton, $ret);
	    $ret = str_replace("[[CalendarName]]", $tag_calendarName, $ret);
	    $ret = str_replace("[[CalendarMonth]]", $tag_calendarMonth, $ret); 
	    $ret = str_replace("[[CalendarYear]]", $tag_calendarYear, $ret);    	
    	
	    /***** Begin building the calendar days *****/
	    // determine the starting day offset for the month
	    $dayOffset = -$first + 1;
	    
	    // determine the number of weeks in the month
	    $numWeeks = floor(($this->getDaysInMonth($this->year,$this->month) - $dayOffset + 7) / 7);  	

	    // begin writing out month weeks
	    for ($i = 0; $i < $numWeeks; $i += 1) {

		$ret .= $html_week_start;		// write out the week start code
  			
		// write out the days in the week
		for ($j = 0; $j < 7; $j += 1) {
		    $ret .= $this->getHTMLForDay($this->month,$dayOffset,$this->year);
		    $dayOffset += 1;
		}
		$ret .= $html_week_end; 		// add the week end code
	    }
  		
	    /***** Do footer *****/
	    $tempString = $html_footer;
  		
	    // replace potential variables in footer
		$tempString = str_replace("[[TodayData]]", $tag_HiddenData, $tempString);
	
		$tempString = str_replace("[[TemplateButton]]", $tag_templateButton, $tempString);
		$tempString = str_replace("[[TodayButton]]", $tag_todayButton, $tempString);
	    $tempString = str_replace("[[MonthSelect]]", $tag_monthSelect, $tempString);
	    $tempString = str_replace("[[PreviousMonthButton]]", $tag_previousMonthButton, $tempString);
	    $tempString = str_replace("[[NextMonthButton]]", $tag_nextMonthButton, $tempString);
	    $tempString = str_replace("[[YearSelect]]", $tag_yearSelect, $tempString);
	    $tempString = str_replace("[[PreviousYearButton]]", $tag_previousYearButton, $tempString);
	    $tempString = str_replace("[[NextYearButton]]", $tag_nextYearButton, $tempString);
	    $tempString = str_replace("[[CalendarName]]", $tag_calendarName, $tempString);
	    $tempString = str_replace("[[CalendarMonth]]", $tag_calendarMonth, $tempString);    	
	    $tempString = str_replace("[[CalendarYear]]", $tag_calendarYear, $tempString);
		
	    $ret .= $tempString;
  		
	    /***** Do calendar end code *****/
	    $ret .= $html_calendar_end;
 
	    // return the generated calendar code
	    return $this->stripLeadingSpace($ret);  	
	}

    // returns the HTML that appears between two search strings.
    // the returned results include the text between the search strings,
    // else an empty string will be returned if not found.
    function searchHTML($html, $beginString, $endString) {
    	$temp = split($beginString, $html);
    	if (count($temp) > 1) {
	    $temp = split($endString, $temp[1]);
	    return $temp[0];
    	}
    	return "";
    }
    
    // strips the leading spaces and tabs from lines of HTML (to prevent <pre> tags in Wiki)
    function stripLeadingSpace($html) {
    	$index = 0;
    	
    	$temp = split("\n", $html);
    	
    	$tempString = "";
    	while ($index < count($temp)) {
	    while (strlen($temp[$index]) > 0 
		   && (substr($temp[$index], 0, 1) == ' ' || substr($temp[$index], 0, 1) == '\t')) {
		$temp[$index] = substr($temp[$index], 1);
	    }
			$tempString .= $temp[$index];
			$index += 1;    		
		}
    	
    	return $tempString;	
    }	
	
    // returns an array of existing article names for a specific day
    function getArticlesForDay($month, $day, $year) {
    	$articleName = "";    	// the name of the article to check for
    	$articleCount = 0;    	// the article count
    	$articleArray = array();    	// the array of article names
		
		// Loop for articles that may have been deleted
		for ($i = 0; $i <= 20; $i++) {
			$articleName = "CalendarEvents:";
			$articleName .= $this->name . "_";			
			$articleName .= "(" . $month . "-" . $day . "-" . $year . ")_-_Event_" . $i;

			$article = new Article(Title::newFromText($articleName));
			if ($article->exists()) {	    // save name
				$articleArray[$articleCount] = $article;
				$articleCount += 1;
			}
		}	
		return $articleArray;
    }
    
    // returns the link for an article, along with summary in the title tag, given a name
    function articleLink($title,$text){
			
		if(strlen($text)==0) return "";//$text="Event";
		if(strlen($text) > $this->trimCount) {
			$text = substr($text,0,$this->trimCount) . "...";
		}
		if($this->defaultEdit)
			return "<a href='" . $this->wikiRoot . "CalendarEvents:" . htmlspecialchars($title->getText()) . "&action=edit'>" . htmlspecialchars($text) . "</a>";

		else
			return "<a href='" . $this->wikiRoot . "CalendarEvents:" . htmlspecialchars($title->getText()) . "'>" . htmlspecialchars($text) . "</a>";
    }
	
    function getSummariesForArticle($article) {
		/* $title = the title of the wiki article of the event.
		 * $displayText = what is displayed
		 */
		$redirectCount = 0;
		while($article->isRedirect() && $redirectCount < 10){
			$redirectedArticleTitle = Title::newFromRedirect($article->getContent());
			$article = new Article($redirectedArticleTitle);
			$redirectCount += 1;
		}
		
		$title        = $article->getTitle();
		$displayText  = $article->fetchContent(0,false,false);

		// $displayText is the text that is displayed for the article.
		// if it has any ==headings==, return an array of them.
		// otherwise return the first line.

		$ret = array();
		$lines = split("\n",$displayText);
		for($i=0;$i<count($lines);$i++){
			$line = $lines[$i];
			if(substr($line,0,2)=='=='){
				$head = split("==",$line);
				$ret[count($ret)] = $this->articleLink($title,$head[1]);
			}
		}
		if(count($ret)==0){
			$ret[0] = $this->articleLink($title,$lines[0]);
		}
		
		return $ret;
    }
	
    // Set/Get accessors
    function setMonth($month) { $this->month = $month; } /* currently displayed month */
    function getMonth() { return $this->month; }
	
    function setYear($year) { $this->year = $year; } /* currently displayed year */
    function getYear() { return $this->year; }
	
    function setTitle($title) { $this->title = str_replace(' ', '_', $title); }
    function getTitle() { return $this->title; }
	
    function setName($name) { $this->name = str_replace(' ', '_', $name); }
    function getName() { return $this->name; }
	
    function setYearsOffset($years) { $this->yearOffset= $years; }
}

// called to process <Calendar> tag.
function displayCalendar($paramstring = "", $params = array()) {
    global $wgParser;
	global $wgScript;
	global $wgCalendarPath;
    $wgParser->disableCache();
	
	//create the calendar class
	$calendar = null;	
	$calendar = new Calendar($wgCalendarPath);

    // grab the page title
    if (defined('MAG_PAGENAME')) {
		$title = $wgParser->getVariableValue(MAG_PAGENAME);
    }
    else {
		$title = $wgParser->getVariableValue("pagename");
    }
	
	// $wgScript == '/mediawiki/index.php'
	// depending on the server config, this 'wikiRoot' could be modified to adjust all links created in the codebase
	// apparently, this could be configured as "/mediawiki/Event1" instead of "/mediawiki/index.php?title=Event1"
	$calendar->wikiRoot = $wgScript . "?title=";

    // check for user set parameters
    if (isset($params["yearoffset"])) $calendar->setYearsOffset($params["yearoffset"]);
	if (isset($params["charlimit"])) $calendar->trimCount = ($params["charlimit"]);
	if (isset($params["name"])) $name = $params["name"];
    if (isset($params["noadd"])) $calendar->showAddEvent = !$params["noadd"];	
    if (isset($params["usetemplates"])) $calendar->enableTemplates = $params["usetemplates"];
    if (isset($params["defaultedit"])) $calendar->defaultEdit = $params["defaultedit"];		
	
	// need to validate the $name for the cookie
	if(strlen($name) == 0) $name = "Public";
	
    // generate the cookie name
    $cookie_name = 'calendar_' . str_replace(' ', '_', $title) . str_replace(' ', '_', $name);

    // check if this user has a calendar saved in their session	
    if (isset($_COOKIE[$cookie_name])) {
		$temp = split("`", $_COOKIE[$cookie_name]);
		$calendar->setMonth($temp[0]);
		$calendar->setYear($temp[1]);
		$calendar->setTitle($temp[2]);
		$calendar->setName($temp[3]);
	}
    else {
		$calendar->setTitle($title);
		$calendar->setName($name);
		// save the calendar back into the session
		setcookie($cookie_name, $calendar->getMonth() . "`" . $calendar->getYear() . "`" .
			  $calendar->getTitle() . "`" . $calendar->getName(), 0, "/", '');
    }
	
	if(strlen($params["date"]) == 0) $ret = "<html>" . $calendar->getHTMLForMonth() . "</html>";
	
    // check for date parameter, which does a calendar on a specific date.
    if (strlen($params["date"]) > 0) {
		$calendar->dateParameter = true;
		$calendar->trimCount = 100; //increase line limit since we have a large page
		if (($params["date"] == "today") || ($params["date"]=="tomorrow")){
			if ($params["date"] == "tomorrow" ){
				$calendar->day++;
				
				//let check for overlap to next month or next year...
				$daysMonth = $calendar->getDaysInMonth($calendar->year,$calendar->month);
				if($calendar->day > $daysMonth){
					$calendar->day = 1;
					$calendar->month++;
					if($calendar->month > 12){
						$calendar->month = 1;
						$calendar->year++;
					}
				}
			}
		}
		else {
			$useDash = split("-",$params["date"]);
			$useSlash = split("/",$params["date"]);
			$parseDate = (count($useDash) > 1 ? $useDash : $useSlash);
			if(count($parseDate) == 3){
				$calendar->month = $parseDate[0];
				$calendar->day = $parseDate[1] + 0; // converts to integer
				$calendar->year = $parseDate[2] + 0;
			}
			else //format error, return
				return "<html><h2>Invalid Date Parameter. Valid formats are (mm/dd/ccyy) or (mm-dd-ccyy)</h2></html>";
		}
		// build the "daily" view HTML if we have a good date
		$html = "<html><table width=\"100%\"><h4>" 
			. $calendar->monthNames[$calendar->month -1] . " "
			. $calendar->day . ", "
			. $calendar->year
			. "</h4>";
		$ret = cleanDayHTML($html. $calendar->getHTMLForDay($calendar->month,$calendar->day,$calendar->year) . "</table></html>");	
	}
	
    return $ret;
}
function cleanDayHTML($tempString){
	// kludge to clean classes from "day" only parameter; causes oddness if the main calendar
	// was displayed with a single day calendar on the same page... the class defines carried over...
	$tempString = str_replace("calendarTransparent", "", $tempString);
	$tempString = str_replace("calendarDayNumber", "", $tempString);
	$tempString = str_replace("calendarEventAdd", "", $tempString);	
	$tempString = str_replace("calendarEventList", "", $tempString);	
	
	$tempString = str_replace("calendarToday", "", $tempString);	
	$tempString = str_replace("calendarMonday", "", $tempString);
	$tempString = str_replace("calendarTuesday", "", $tempString);
	$tempString = str_replace("calendarWednesday", "", $tempString);
	$tempString = str_replace("calendarThursday", "", $tempString);	
	$tempString = str_replace("calendarFriday", "", $tempString);
	$tempString = str_replace("calendarSaturday", "", $tempString);	
	$tempString = str_replace("calendarSunday", "", $tempString);	
	
	return $tempString;
}
} //end define MEDIAWIKI
?>
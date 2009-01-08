<?php

/* Calendar.php
 *
 ***** Verion 3.2 *****
 *
 * - Eric Fortin (12/14/2008) < kenyu73@gmail.com >
 *
 * - Original author(s):
 *   	Simson L. Garfinkel < simsong@acm.org >
 *   	Michael Walters < mcw6@aol.com > 
 * 		http://www.mediawiki.org/wiki/User:Hex2bit/Calendar
 *
 * See Readme file for full details
 *
 **** debugging: 
 *		The debugging events are written below the calendar web page.
 *			<calendar debug /> - basically, user defined debugging
 *			<calendar debug=2 /> - writes all the $this->staticDebug data (all function calls included)
 * call the debugging by this means:   $this->debug(<data>);
 *
 * the debug log will show up on the calendar display page
 *
 *
 * // (* backwards compatibility only *) - 12/14/08 patch
 * // In the function 'getArticlesForDay', i have code in place to check for older style events (upgraded users only should care)
 * // must use the name parameter even in fullsubscribe mode: <calendar name="Team" fullsubscribe="Main Page/Team" />
 * // if you dont, you will not get the older style events in your calendar...
*/

// this is the "refresh" code that allows the calendar to switch time periods
if (isset($_POST["today"]) || isset($_POST["yearBack"]) || isset($_POST["yearForward"]) || isset($_POST["monthBack"])
	|| isset($_POST["monthForward"]) || isset($_POST["monthSelect"]) || isset($_POST["yearSelect"])){

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
    'version'=>'3.5'
);
	
$wgExtensionFunctions[] = "wfCalendarExtension";

// function adds the wiki extension
function wfCalendarExtension() {
    global $wgParser;
    $wgParser->setHook( "calendar", "displayCalendar" );
}

class Calendar
{  
	var $version = "v3.4 (1/3/2009)";
	
    // [begin] set calendar parameter defaults
	var $title = "Calendar"; 
	var $name = "Public";
	var $enableTemplates = false;
	var $enableStyles = false;
	var $showAddEvent = true;    
	var $defaultEdit = false;	
	var $yearOffset= 2;
	var $charLimit = 20; // this is the line char limit for listed events
	var $maxDailyEvents = 5; // max number of events per day; this directly effects performace
	// [end] set calendar parameter defaults
	
	var $debugLevel = 0;
	var $useMultiEvent = false;
	var $dateEnabled = false;
	var $useEventList = false;
	var $disableLinks = false;
	var $lockTemplates = false;
	var $arrAlerts = array();
	var $subscribedPages = array();
	var $arrTemplates = array();

	// setup calendar arrays
    var $daysInMonth = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);   
    var $dayNames   = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");	
    var $monthNames = array("January", "February", "March", "April", "May", "June",
                            "July", "August", "September", "October", "November", "December");

						
    function Calendar($wgCalendarPath) {
		$this->debugEnabled = true; //enable then disable to capture class debugging
		$this->startTime = $this->markTime = microtime(1);
		$this->debug("Calendar Constructor Started.");		
		
		// set the calendar's initial date to now
		$today = getdate();    	
		$this->month = $today['mon'];
		$this->year = $today['year'];
		$this->day = $today['mday'];
		
		// static date to reference (dont update these!)
		$this->monthStatic = $today['mon'];
		$this->yearStatic = $today['year'];
		$this->dayStatic = $today['mday'];
		
		// errors
		$this->errMultiday = "Multiple events/day are enabled. Please use the following format: == event ==.";
		$this->errStyles = "It appears you have an undefined or invalid style.";
		
		// set paths	
		$extensionFolder = "extensions/Calendar/"; //default
		$wikiBasePath = str_replace("index.php", "", $_SERVER['SCRIPT_FILENAME']);
		$wikiBaseURL = str_replace("index.php", "", $_SERVER['SCRIPT_NAME']);

		$extensionPath = $wikiBasePath . $extensionFolder;
		$extensionURL = $wikiBaseURL . $extensionFolder;
		
		// allows manual override of the physical extension paths -todo
		if(strlen($wgCalendarPath) > 0)
			$extensionPath = $wgCalendarPath;
		
		$extensionPath = $this->extensionPath = str_replace("\\", "/", $extensionPath);
			
		$this->html_template = file_get_contents($extensionPath . "calendar_template.html");

		$this->daysNormalHTML   = $this->html_week_array("<!-- %s %s -->");
		$this->daysSelectedHTML = $this->html_week_array("<!-- Selected %s %s -->");
		$this->daysMissingHTML  = $this->html_week_array("<!-- Missing %s %s -->");
		
		$this->debug("Calendar Constructor Ended.");
		$this->debugEnabled = false; //enable then disable to capture class debugging
    }
	
	function getAboutInfo(){
		
		$about = "<a href = 'http://www.mediawiki.org/wiki/Extension:Calendar_(Kenyu73)' target='new'>about...</a>";
		
		return $about;
		
	}
// ******* BEGIN DEBUGGING CODE ******************
	// write debug info to specified folder
	// make sure the calendar folder has write privs
	function debugToFile($e){
		if($this->debugEnabled){
			$wikiBasePath = $this->extensionPath . 'data.txt';
			$fp = fopen($wikiBasePath, 'at');
			fwrite($fp, $e . chr(10));
			fclose($fp);	
		}
	}
	
	// stand debug calls (use then erase kinda calls)
	function debug($e){
		if($this->debugEnabled){
			// recorded time in seconds
			$steptime = round(microtime(1) - $this->markTime,3);
			$totaltime = round(microtime(1) - $this->startTime,3);
			$this->debugData .= $e . " : " . $steptime . " (" . $totaltime . ")" .  chr(10) . "\n";
			$this->markTime = microtime(1);
		}
	}
	
	// set 'debug=2' in the calendar parameters to get 
	// advanced debugging... ie: calls to staticDebugs...	
	function staticDebug($e){
		if($this->debugLevel == 2)
			$this->debug($e);
	}
	
	function getDebugging() { 
		if($this->debugEnabled)
			return htmlspecialchars($this->debugData); 
	}	
// ******* END DEBUGGING CODE *****************
	
    function html_week_array($format){
		$this->staticDebug("function html_week_array");
		
		$ret = array();
		for($i=0;$i<7;$i++){
			$ret[$i] = $this->searchHTML($this->html_template,
						 sprintf($format,$this->dayNames[$i],"Start"),
						 sprintf($format,$this->dayNames[$i],"End"));
		}
		return $ret;
    }

    function getDaysInMonth($year,$month) {	// Leap year rule good through 3999
		$this->staticDebug("function getDaysInMonth");
	
        if ($month < 1 || $month > 12) return 0;
        $d = $this->daysInMonth[$month - 1];
        if ($month == 2 && $year%4==0) {
			$d = 29;
			if ($year%100 == 0 && $year%400 != 0) $d = 28;
		}
        return $d;
    }
	
	//find the number of current events and "build" the <add event> link
    function buildNewEvent($month, $day, $year) {
		$this->staticDebug("function buildNewEvent (wiki db lookup)");
		
    	$articleName = "";    	// the name of the article to check for
    	$articleCount = 1;    	// the article count
		$stop = false;
	
		$tempArticle = $this->calendarPageName . "/" . $month . "-" . $day . "-" . $year . " -Event ";
		$articleName = $tempArticle . $articleCount;
		$article = new Article(Title::newFromText($articleName));		
		
		// dont care about the articles here, just need to get next available article
		while ($article->exists() && !$stop) {
			$displayText  = $article->fetchContent(0,false,false);
			if(strlen($displayText) > 0){
				$articleCount += 1;				
				$articleName = $tempArticle . $articleCount;
				$article = new Article(Title::newFromText($articleName));
			}
			else $stop = true;
		}

		// reuse events (need to used the ==event1== ==event2== logic)
		if($this->useMultiEvent && $articleCount > 1) $articleCount -= 1;
		
		if($articleCount > $this->maxDailyEvents)
			$newURL = "<a title='add a new event' href=\"javascript:alert('Max daily events reached. Please use \'Multiple Events\' fomatting to add more.')\"><u>Add Event</u></a>";
		else
			$newURL = "<a title='add a new event' href='" . $this->wikiRoot . urlencode($tempArticle . $articleCount) . "&action=edit'><u>Add Event</u></a>";

		return $newURL;
	}
  
    // Generate the HTML for a given month
    // $day may be out of range; if so, give blank HTML
    function getHTMLForDay($month,$day,$year){
		$tag_eventList= "";
		
		$this->staticDebug("function getHTMLForDay");
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
			
		//load templates if enabled
		if($this->enableTemplates){
			$eventArticle = $this->getTemplateEvents($month, $day, $year);
			if(strlen($eventArticle) > 0)
				$tag_eventList .=  $eventArticle;
		}

		// event list tag
		$events = $this->getArticlesForDay($month, $day, $year);

		if (count($events) > 0) {
			for ($k = 0; $k < count($events); $k++){
				$summaries = $this->getSummariesForArticle($events[$k], $day, $month);
				for($j = 0; $j < count($summaries); $j++){
					if(strlen($summaries[$j]) > 0)
						$tag_eventList .= "<li>" . $summaries[$j] . "</li>\n";
				}
			}
		}	

		// replace variable tags in the string
		if($this->dateEnabled)
			$tempString = str_replace("[[Day]]", "", $tempString);
		else
			$tempString = str_replace("[[Day]]", $day, $tempString);
		
		if(strlen($tag_eventList) > 0 && $this->useEventList){
			$format = "<h4>" 
			. $this->monthNames[$month -1] . " "
			. $day . ", "
			. $year
			. "</h4>";
		
			$this->eventList .= $format . "<ul>" . $tag_eventList . "</ul>";
			
		}else{	
			$tag_alerts = $this->buildAlertLink($day, $month);

			$tempString = str_replace("[[AddEvent]]", $tag_addEvent, $tempString);
			$tempString = str_replace("[[EventList]]", "<ul>" . $tag_eventList . "</ul>", $tempString);
			$tempString = str_replace("[[Alert]]", $tag_alerts, $tempString);
		}

		return $tempString;
    }
	
	// when the calendar loads, we want to put all the template events into memory
	// so we dont have to read the wiki db for every day
	function buildTemplateInMemory($month, $year, $pagename){
		$displayText = "";
		$ret = "";
		$arrEvent = array();
		
		$this->staticDebug("function buildTemplateInMemory (wiki db lookup)");

		$articleName = $pagename . "/" . $month . "-" . $year . " -Template";
		$article = new Article(Title::newFromText($articleName));

		if ($article->exists()) {
			$displayText  = $article->fetchContent(0,false,false);
		}

		$arrAllEvents=split(chr(10),$displayText);
		if (count($arrAllEvents) > 0){
			for($i=0; $i<count($arrAllEvents); $i++){
				$arrEvent = split("#",$arrAllEvents[$i]);
				if(strlen($arrEvent[1]) > 0){
					$day = $arrEvent[0];
					$arrRepeat = split("-",$arrEvent[0]);
					if(count($arrRepeat) > 1){
						$day = $arrRepeat[0];
						while($day <= $arrRepeat[1]){
							$this->arrTemplates[] = $month . "`" . $day . "`" . $year . "`<li>" . $this->buildTemplateLink($month, $day, $year, $arrEvent[1], $pagename) . "</li>";
							$day++;
						}
					}else
						$this->arrTemplates[] = $month . "`" . $day . "`" . $year . "`<li>" . $this->buildTemplateLink($month, $day, $year, $arrEvent[1], $pagename) . "</li>";	
				}
			}
		}
		return $ret;		
	}
	
	// read events that are sitting in memory
	function getTemplateEvents($month, $day, $year){
		$this->staticDebug("function getTemplateEvents");
		
		$ret = "";
		$count = 0;
		
		while($count < count($this->arrTemplates)){
			$arrEvent = split("`", $this->arrTemplates[$count]);
			if(($month == $arrEvent[0]) && ($day == $arrEvent[1]) && ($year == $arrEvent[2])){
				$ret .= $arrEvent[3];
			}
			$count++;
		}

		return $ret;
	}

	function buildAlertLink($day, $month){
		$ret = "";
		$this->staticDebug("function buildAlertLink");
	
		$alerts = $this->arrAlerts;
		$alertList = "";
		for ($i=0; $i < count($alerts); $i++){
			$alert = split("-", $alerts[$i]);
			if(($alert[0] == $day) && ($alert[1] == $month))
				$alertList .= $alert[2];
		}
		
		if (strlen($alertList) > 0)
			$ret = "<a style='color:red' href=\"javascript:alert('" .$alertList . "')\"><i>alert!</i></a>";

		return $ret;
	}

    function getHTMLForMonth() {   
		
		$tag_templateButton = "";
		
		$this->staticDebug("function getHTMLForMonth");
       	
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
		$tag_eventStyleButton = "";		// event style buttonn [[EventStyleBtn]]
		$tag_templateButton = "";		// template button for multiple events [[TemplateButton]]
		$tag_todayButton = "";			// today button [[TodayButton]]
        
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
		$this->referrerURL = $referrerURL;
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
			$articleName = $this->wikiRoot . $this->calendarPageName . "/" . $this->month . "-" . $this->year . " -Template&action=edit" . "';\">";
			
			if($this->lockTemplates)
				$tag_templateButton = "<input type=\"button\" title=\"Create a bunch of events in one page (20-25# Vacation)\" disabled value= \"template load\" onClick=\"javascript:document.location='" . $articleName;
			else
				$tag_templateButton = "<input type=\"button\" title=\"Create a bunch of events in one page (20-25# Vacation)\" value= \"template load\" onClick=\"javascript:document.location='" . $articleName;
		}
	
		if($this->enableStyles){
			$articleStyle = $this->wikiRoot . $this->calendarPageName . "/style&action=edit" . "';\">";
			$tag_eventStyleButton = "<input type=\"button\" title=\"Set event colors based on trigger words (red::vacation)\" value= \"event styles\" onClick=\"javascript:document.location='" . $articleStyle;
		}
		
		// build the hidden calendar date info (used to offset the calendar via cookies)
		$tag_HiddenData = "<input type='hidden' name='calendar_info' value='"
			. $this->month . "`"
			. $this->year . "`"
			. $this->title . "`"
			. $this->name . "`"
			. $referrerURL
			. "'>";
		
		// build the 'today' button	
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
		$ret = str_replace("[[About]]", $this->getAboutInfo(), $ret);
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
		$tempString = str_replace("[[EventStyleBtn]]", $tag_eventStyleButton, $tempString);
		$tempString = str_replace("[[Version]]", $this->version, $tempString);
		
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
		$this->staticDebug("function searchHTML");
		
    	$temp = split($beginString, $html);
    	if (count($temp) > 1) {
			$temp = split($endString, $temp[1]);
			return $temp[0];
    	}
    	return "";
    }
    
    // strips the leading spaces and tabs from lines of HTML (to prevent <pre> tags in Wiki)
    function stripLeadingSpace($html) {
		$this->staticDebug("function stripLeadingSpace");
		
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
		$this->staticDebug("function getArticlesForDay (wiki db lookup x 3)");
    	$articleName = "";    	// the name of the article to check for
    	$articleCount = 0;    	// the article count
    	$articleArray = array();    	// the array of article names

		
		for ($i = 0; $i <= $this->maxDailyEvents; $i++) {
			$articleName = $this->calendarPageName . "/" . $month . "-" . $day . "-" . $year . " -Event " . $i;
			$article = new Article(Title::newFromText($articleName));

			if ($article->exists()) {
				$articleArray[$articleCount] = $article;
				$articleCount += 1;
			}
			
			// subscribed events
			for($s=0; $s < count($this->subscribedPages); $s++){
				$articleName = $this->subscribedPages[$s] . "/" .  $month . "-" . $day . "-" . $year . " -Event " . $i;
				$article = new Article(Title::newFromText($articleName));
				if ($article->exists()) {
					$articleArray[$articleCount] = $article;
					$articleCount += 1;
				}					
			}
			
			// (* backwards compatibility only *)
			// must use the name parameter even in fullsubscribe mode: <calendar name="Team" fullsubscribe="Main Page/Team" />
			// if you dont, you will not get the older style events in your calendar...
			$articleName = $this->calendarName . " (" . $month . "-" . $day . "-" . $year . ") - Event " . $i;
			$article = new Article(Title::newFromText($articleName));
			if ($article->exists()) {
				$articleArray[$articleCount] = $article;
				$articleCount += 1;
			}
		}

		return $articleArray;
    }

    // returns the link for an article, along with summary in the title tag, given a name
    function articleLink($title,$text,$displayText,$day,$month){
		$this->staticDebug("function articleLink");
			
		if(strlen($text)==0) return "";

		$arrText = $this->buildTextAndHTMLString($text,$day,$month);
		$style = $arrText[2];

		//locked links
		if($this->disableLinks)
			$ret = "<a>" . $arrText[1] . "</a>";
		else
			if($this->defaultEdit)
				$ret = "<a $style title='$arrText[0]' href='" . $this->wikiRoot  . htmlspecialchars($title) . "&action=edit'>$arrText[1]</a>";
			else
				$ret = "<a $style title='$arrText[0]' href='" . $this->wikiRoot . htmlspecialchars($title)  . "'>$arrText[1]</a>";

		return $ret;
    }
	
	// this builds the templates href link
    function buildTemplateLink($month, $day, $year, $text, $pagename) {
		$this->staticDebug("function buildTemplateLink");

		$newURL = "";	

		$article = $pagename . "/" . $month . "-" . $year ." -Template";
		
		$arrText = $this->buildTextAndHTMLString($text,$day,$month);
		$style = $arrText[2];
		
		if($this->lockTemplates)
			$newURL = "<a $style>" . $arrText[1] . "</a>";	
		else
			$newURL = "<a $style title='$arrText[0]' href='" . $this->wikiRoot . htmlspecialchars($article) . "&action=edit'>$arrText[1]</a>";

		return $newURL;
	}

	function buildTextAndHTMLString($string,$day,$month){
		$this->staticDebug("function buildTextAndHTMLString");

		$string = $this->cleanWiki($string);	
		$htmltext = $string;
		$plaintext = strip_tags($string);

		if(strlen($plaintext) > $this->charLimit) {
			$temp = substr($plaintext,0,$this->charLimit) . "..."; //plaintext
			$ret[0] = $plaintext; //full plain text
			$ret[1] = str_replace($plaintext, $temp, $htmltext); //html
		}
		else{
			$ret[0] = $plaintext; //full plain text
			$ret[1] = $htmltext;			
		}
		
		$arrStyle = $this->buildStyleBySearch($plaintext,$day,$month);
		$ret[0] = $arrStyle[0]; //text
		$ret[1] = str_replace($plaintext, $arrStyle[0], $htmltext); //remove :: for html render
		$ret[2] = $arrStyle[1]; //style
		
		return $ret;
	}


	function cleanWiki($text){

		$text = $this->swapWikiToHTML($text, "'''", "b");
		$text = $this->swapWikiToHTML($text, "''", "i");
		$text = $this->swapWikiToHTML($text, "<pre>", "");
		$text = $this->swapWikiToHTML($text, "</pre>", "");
	
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
	
	function buildStyleBySearch($text,$day,$month){
		$this->staticDebug("function buildStyleByPrefix");
		
		$ret[0] = $text;
		$ret[1] = "";
			
		for($i=0; $i < count($this->arrStyle); $i++){
			$arr = split("::", $this->arrStyle[$i]);
			$cnt = count($arr);
			
			if(stripos($text, $arr[0]) !== false)
				$ret[1] = "style='" . trim($arr[1]) . "' ";
		}

		return $ret;
	}
	
	function readStylepage(){
		$this->staticDebug("function getStyles");
		
		$articleName = $this->calendarPageName . "/" . "style";	
		$article = new Article(Title::newFromText($articleName));

		if ($article->exists()){
			$displayText  = $article->fetchContent(0,false,false);	
			$this->arrStyle = split(chr(10), $displayText);
		}
	}
	
    function getSummariesForArticle($article, $day, $month) {
		$this->staticDebug("function getSummariesForArticle");
		/* $title = the title of the wiki article of the event.
		 * $displayText = what is displayed
		 */
		$redirectCount = 0;
		while($article->isRedirect() && $redirectCount < 5){
			$redirectedArticleTitle = Title::newFromRedirect($article->getContent());
			$article = new Article($redirectedArticleTitle);
			$redirectCount += 1;
		}
		
		$title        = $article->getTitle()->getPrefixedText(); //full title with namespace,title,name and date
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
				$ret[count($ret)] = $this->articleLink($title,$head[1],$displayText,$day,$month);
			}
			elseif ($i == 0 && $this->useMultiEvent && strlen($line) > 0){
				$ret[count($ret)] = $this->articleLink($title,"error: (==event==)",$displayText,$day,$month);
				$this->createAlert($day, $month, $this->errMultiday);
			}
		}
		
		if(count($ret)==0){
			$ret[0] = $this->articleLink($title,$lines[0],$displayText,$day,$month);
		}

		return $ret;
	}		
	
	// Set/Get accessors
	function setMonth($month) { $this->month = $month; } /* currently displayed month */
	function setYear($year) { $this->year = $year; } /* currently displayed year */
	function setTitle($title) { $this->title = str_replace(' ', '_', $title); }
	function setName($name) { $this->name = str_replace(' ', '_', $name); }
	function setYearsOffset($years) { $this->yearOffset= $years; } 
	function createAlert($day, $month, $text){$this->arrAlerts[] = $day . "-" . $month . "-" . $text . "\\n";}
}

// called to process <Calendar> tag.
function displayCalendar($paramstring = "", $params = array()) {
    global $wgParser;
	global $wgScript;
	global $wgCalendarPath;
	global $wgTitle;
    $wgParser->disableCache();

	$calendar = null;	
	$calendar = new Calendar($wgCalendarPath);
	
	// grab the page title
	$title = $wgTitle->getPrefixedText();
	
	// $wgScript == '/mediawiki/index.php'
	// depending on the server config, this 'wikiRoot' could be modified to adjust all links created in the codebase
	// apparently, this could be configured as "/mediawiki/Event1" instead of "/mediawiki/index.php?title=Event1"
	$calendar->wikiRoot = $wgScript . "?title=";
	
	$name = "Public";	
	
    // check for user set parameters
	if(isset($params["debug"])) $calendar->debugEnabled = true;	
	if(isset($params["disableaddevent"])) $calendar->showAddEvent = false;	
    if(isset($params["usetemplates"])) $calendar->enableTemplates = true;
	
    if(isset($params["defaultedit"])) $calendar->defaultEdit = true;
	if(isset($params["disablelinks"])) $calendar->disableLinks = true;
	if(isset($params["usemultievent"])) $calendar->useMultiEvent = true; 
	if(isset($params["locktemplates"])) $calendar->lockTemplates = true; 
	if(isset($params["debug"])) if(strlen($params["debug"]) > 0) $calendar->debugLevel = ($params["debug"]);
	if(isset($params["yearoffset"])) if(strlen($params["yearoffset"]) > 0) $calendar->setYearsOffset($params["yearoffset"]);
	if(isset($params["charlimit"])) if(strlen($params["charlimit"]) > 0) $calendar->charLimit = ($params["charlimit"]);
	if(isset($params["name"])) if(strlen($params["name"]) > 0) $name = $params["name"];
	if(isset($params["maxdailyevents"])) if(strlen($params["maxdailyevents"]) > 0) $calendar->maxDailyEvents = $params["maxdailyevents"];
	
	// no need to pass a parameter here... isset check for the params name, thats it
	if(isset($params["lockdown"])){
		$calendar->showAddEvent = false;
		$calendar->disableLinks = true;
		$calendar->lockTemplates = true;
	}
	// normal calendar...
	$calendar->calendarPageName = htmlspecialchars($title . "/" . $name);
	
	// joint calendar...pulling data from our calendar and the subscribers...ie: "title/name" format
	if(isset($params["subscribe"])) 
		if(strlen($params["subscribe"]) > 0) $calendar->subscribedPages = split(",", $params["subscribe"]);

	// subscriber only calendar...basically, taking the subscribers identity fully...ie: "title/name" format
	if(isset($params["fullsubscribe"])) 
		if(strlen($params["fullsubscribe"]) > 0) $calendar->calendarPageName = htmlspecialchars($params["fullsubscribe"]);

	//calendar name itself (this is only for (backwards compatibility)
	$calendar->calendarName = htmlspecialchars("CalendarEvents:" .$name);
	
	// finished special conditions; set the $title and $name in the class
	$calendar->setTitle($title);
	$calendar->setName($name);

	if($params["styles"] != "disable"){
		$calendar->enableStyles = true;
		// load style page into memory
		$calendar->readStylepage();
	}	
	
    // read the cookie to pull last calendar data
    $cookie_name = 'calendar_' . str_replace(' ', '_', $title) . str_replace(' ', '_', $name);
    if (isset($_COOKIE[$cookie_name]) && !isset($params["date"]) && !isset($params["useeventlist"])){
		$temp = split("`", $_COOKIE[$cookie_name]);
		$calendar->setMonth($temp[0]);
		$calendar->setYear($temp[1]);
		$calendar->setTitle($temp[2]);
		$calendar->setName($temp[3]);
	}
	
	//this must go after the cookie checks because of the saved date in the cookie
	if($calendar->enableTemplates){
		$year = $calendar->year;
		$month = $calendar->month;
			
		// lets just grab the next 12 months...this load only takes about .01 second per subscribed calendar
		for($i=0; $i < 12; $i++){ // loop thru 12 months
			for($s=0;$s < count($calendar->subscribedPages);$s++) //loop thru $i month per subscribed calendar
				$calendar->buildTemplateInMemory($month, $year, ($calendar->subscribedPages[$s]));
			
			$calendar->buildTemplateInMemory($month, $year, ($calendar->calendarPageName));		
			$year = ($month == 12 ? ++$year : $year);
			$month = ($month == 12 ? 1 : ++$month);
		}
	}

	// normal month mode
	if(!isset($params["date"]) && !isset($params["useeventlist"])) 
			return "<html>" . $calendar->getHTMLForMonth() . "</html>" . $calendar->getDebugging();

	// event list mode
	if(strlen($params["useeventlist"]) > 0){
		$calendar->useEventList = true;
		$daysOut = ($params["useeventlist"] <= 120 ? $params["useeventlist"] : 120);
		
		$month = $calendar->month;
		$day = $calendar->day;
		$year = $calendar->year;
		$calendar->charLimit = 100;
		
		for($i=0; $i < $daysOut; $i++){
			$calendar->getHTMLForDay($month, $day, $year);
			$day++;
			//lets check for overlap to next month or next year...
			$daysMonth = $calendar->getDaysInMonth($year,$month);
			if($day > $daysMonth){
				$day = 1;
				$month++;
				if($month > 12){
					$month = 1;
					$year++;
				}
			}
		}
		if(strlen($calendar->eventList) == 0)
			//$calendar->eventList = "<h4>No Events for<br/>the next " . $daysOut . " days</h4>";
			$calendar->eventList = "<h4>No Events</h4>";
		return "<html>" . $calendar->eventList . "</html>" . $calendar->getDebugging();
	}
	
    // specific date mode
    if (strlen($params["date"]) > 0) {
		$calendar->dateEnabled = true;
		$calendar->charLimit = 100;
		if (($params["date"] == "today") || ($params["date"]=="tomorrow")){
			if ($params["date"] == "tomorrow" ){
				$calendar->day++;
				
				//lets check for overlap to next month or next year...
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
		$html = "<table width=\"100%\"><h4>" 
			. $calendar->monthNames[$calendar->month -1] . " "
			. $calendar->day . ", "
			. $calendar->year
			. "</h4>";
		return "<html>" . cleanDayHTML($html. $calendar->getHTMLForDay($calendar->month,$calendar->day,$calendar->year) 
		. "</table></html>" 
		. $calendar->getDebugging());	
	}

	return true;
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
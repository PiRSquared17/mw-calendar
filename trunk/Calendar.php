<?php

/* Calendar.php
 *
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
    'version'=>''
);
	
$wgExtensionFunctions[] = "wfCalendarExtension";


// function adds the wiki extension
function wfCalendarExtension() {
    global $wgParser;
    $wgParser->setHook( "calendar", "displayCalendar" );
}

include ("CalendarArticles.php");

class Calendar extends CalendarArticles
{  
	var $version = "v3.4.2 (1/6/2009)";
	
    // [begin] set calendar parameter defaults
	var $calendarMode = "normal";
	var $title = ""; 
	var $name = "Public";
	var $enableTemplates = false;
	var $enableStyles = false;
	var $showAddEvent = true;    
	var $defaultEdit = false;	
	var $yearOffset= 2;
	var $charLimit = 20; // this is the line char limit for listed events
	var $maxDailyEvents = 5; // max number of events per day; this directly effects performace
	var $summaryCharLimit = 0;
	
	var $debugLevel = 0;
	var $useMultiEvent = false;
	var $useEventList = false;
	var $disableLinks = false;
	var $lockTemplates = false;
	var $disableConfigLink = true;
	var $disableStyles = false;
	
	var $arrAlerts = array();
	var $subscribedPages = array();

	// setup calendar arrays
    var $daysInMonth = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);   
    var $dayNames   = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");	
    var $monthNames = array("January", "February", "March", "April", "May", "June",
                            "July", "August", "September", "October", "November", "December");

						
    function Calendar($wgCalendarPath, $wikiRoot, $debug) {

		$this->wikiRoot = $wikiRoot;
		$this->debugEnabled = $debug;
		
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
		
		$extensionPath = str_replace("\\", "/", $extensionPath);
		$this->html_template = file_get_contents($extensionPath . "calendar_template.html");

		$this->daysNormalHTML   = $this->html_week_array("<!-- %s %s -->");
		$this->daysSelectedHTML = $this->html_week_array("<!-- Selected %s %s -->");
		$this->daysMissingHTML  = $this->html_week_array("<!-- Missing %s %s -->");
		
		$this->debug("Calendar Constructor Ended.");
    }
	
	function getAboutInfo(){
		
		$about = "<a href = 'http://www.mediawiki.org/wiki/Extension:Calendar_(Kenyu73)' target='new'>about...</a>";
		
		return $about;
		
	}
// ******* BEGIN DEBUGGING CODE ******************
	function debug($e){
		if($this->debugEnabled){
			// recorded time in seconds
			$steptime = round(microtime(1) - $this->markTime,2);
			$totaltime = round(microtime(1) - $this->startTime,2);
			$this->debugData .= "<tr><td>$e</td><td align=center>$steptime</td><td align=center>$totaltime</td></tr>";
			$this->markTime = microtime(1);
		}
	}
	
	// writes all the debug data at the bottom of calendar page
	function getDebugging() { 
		if($this->debugEnabled)
			return "<table border=1 cellpadding=5 cellspacing=0 >
			<tr><th>DebugName</th><th>StepTime<br>(sec)</th><th>TotalTime<br>(sec)</th></tr>
			$this->debugData</table>";
	}	
// ******* END DEBUGGING CODE *****************
	
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
	 
    // Generate the HTML for a given month
    // $day may be out of range; if so, give blank HTML
    function getHTMLForDay($month,$day,$year){
		$tag_eventList= "";
		
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
			$tag_addEvent = $this->buildAddEventLink($month, $day, $year);
		}
		else {
			$tag_addEvent = "";
		}

		// build standard articles
		$this->getArticlesForDay($month, $day, $year);

		//build formatted event list
		$tag_eventList = $this->getArticleLinks($month, $day, $year, true);

		// replace variable tags in the string
		if($this->calendarMode == "date")
			$tempString = str_replace("[[Day]]", "", $tempString); // remove the day number (1, 2, 3, ..., 31)
		else
			$tempString = str_replace("[[Day]]", $day, $tempString);
		
		if(strlen($tag_eventList) > 0 && ($this->calendarMode == "eventlist")){
			$format = "<h4>" 
			. $this->monthNames[$month -1] . " "
			. $day . ", "
			. $year
			. "</h4>";
		
			$this->eventList .= $format . "<ul>" . $tag_eventList . "</ul>";
			
		}else{	
			$tag_alerts = $this->buildAlertLink($day, $month);
			
			//kludge... for some reason, the "\n" is removed in full calendar mode
			if($this->calendarMode == "normal")
				$tag_eventList = str_replace("\n", " ", $tag_eventList); 
				
			$tempString = str_replace("[[AddEvent]]", $tag_addEvent, $tempString);
			$tempString = str_replace("[[EventList]]", "<ul>" . $tag_eventList . "</ul>", $tempString);
			$tempString = str_replace("[[Alert]]", $tag_alerts, $tempString);
		}

		return $tempString;
    }

	function buildAlertLink($day, $month){
		$ret = "";
	
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
	
	// build the 'template' button	
	function buildTemplateLink(){	
	
		if(!$this->enableTemplates) return "";
		
		$articleName = $this->wikiRoot . $this->calendarPageName . "/" . $this->month . "-" . $this->year . " -Template&action=edit" . "';\">";
		
		if($this->lockTemplates)
			$ret = "<input type=\"button\" title=\"Create a bunch of events in one page (20-25# Vacation)\" disabled value= \"template load\" onClick=\"javascript:document.location='" . $articleName;
		else
			$ret = "<input type=\"button\" title=\"Create a bunch of events in one page (20-25# Vacation)\" value= \"template load\" onClick=\"javascript:document.location='" . $articleName;
		
		return $ret;			
	}
	
	// build the 'template' button	
	function buildConfigLink($bTextLink = false){	
		
		if($this->disableConfigLink) return "";
		
		if(!$bTextLink){
			$articleConfig = $this->wikiRoot . $this->configPageName . "&action=edit" . "';\">";
			$ret = "<input type='button' title='x' value='config' onClick=\"javascript:document.location='" . $articleConfig;
		}else
			$ret = "<a href='" . $this->wikiRoot . $this->configPageName . "&action=edit'>(config...)</a>";

		return $ret;			
	}
	
    function getHTMLForMonth() {   
		
		$tag_templateButton = "";
       	
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
		$tag_configButton = ""; 		// config page button
        
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
    	

		$tag_templateButton = $this->buildTemplateLink();
		$tag_configButton = $this->buildConfigLink(false);

		if(!$this->disableStyles){
			$articleStyle = $this->wikiRoot . $this->calendarPageName . "/style&action=edit" . "';\">";
			$tag_eventStyleButton = "<input type=\"button\" title=\"Set 'html/css' styles based on trigger words (vacation::color:red; font-style:italic)\" value= \"event styles\" onClick=\"javascript:document.location='" . $articleStyle;
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
		$tempString = str_replace("[[ConfigurationButton]]", $tag_configButton, $tempString);
		
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
	
		for ($i = 0; $i <= $this->maxDailyEvents; $i++) {
			$articleName = $this->calendarPageName . "/" . $month . "-" . $day . "-" . $year . " -Event " . $i;	
			$this->addArticle($month, $day, $year, $articleName, $this->summaryCharLimit);
		
			// subscribed events
			for($s=0; $s < count($this->subscribedPages); $s++){
				$articleName = $this->subscribedPages[$s] . "/" .  $month . "-" . $day . "-" . $year . " -Event " . $i;		
				$this->addArticle($month, $day, $year, $articleName, $this->summaryCharLimit);				
				
			}
			
			// (* backwards compatibility only *)
			// must use the name parameter even in fullsubscribe mode: <calendar name="Team" fullsubscribe="Main Page/Team" />
			// if you dont, you will not get the older style events in your calendar...
			$articleName = $this->calendarName . " (" . $month . "-" . $day . "-" . $year . ") - Event " . $i;
			$this->addArticle($month, $day, $year, $articleName, $this->summaryCharLimit);
		}
    }
	
	function readStylepage(){
		$articleName = $this->calendarPageName . "/" . "style";	
		$article = new Article(Title::newFromText($articleName));

		if ($article->exists()){
			$displayText  = $article->fetchContent(0,false,false);	
			$this->arrStyle = split(chr(10), $displayText);
		}
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
	
	$debug = "";
	
    $wgParser->disableCache();
	$wikiRoot = $wgScript . "?title=";
	
	// grab the page title
	$title = $wgTitle->getPrefixedText();
	$name = "Public";		
	
	if(isset($params["debug"])) $debug = true;
	if(isset($params["name"])) if($params["name"] != "name") $name = $params["name"];	
		
	$calendar = null;	
	$calendar = new Calendar($wgCalendarPath, $wikiRoot, $debug);
	
	// normal calendar...
	$calendar->calendarPageName = htmlspecialchars($title . "/" . $name);
	$calendar->configPageName = htmlspecialchars("$title/$name/config");
	
	if(isset($params["useconfigpage"])) {
		
		if($params["useconfigpage"] == "disablelink")
			$calendar->disableConfigLink = true;
		else
			$calendar->disableConfigLink = false;

		$arrParams = $calendar->getConfig("$title/$name");
		$cnt = count($arrParams);
		
		for($i=0; $i<$cnt; $i++){
			$arr = split("=", $arrParams[$i]);
			if(count($arr) < 2) $arr[1] = "";
			$arr[0] = trim($arr[0]); $arr[1] = trim($arr[1]);

			if($arr[0] == "disableaddevent") $calendar->showAddEvent = false;
			if($arr[0] == "usetemplates") $calendar->enableTemplates = true;
			if($arr[0] == "defaultedit") $calendar->defaultEdit = true;
			if($arr[0] == "disablelinks") $calendar->disableLinks = true;
			if($arr[0] == "usemultievent") $calendar->useMultiEvent = true;
			if($arr[0] == "locktemplates") $calendar->lockTemplates = true; 
			if($arr[0] == "disablestyles") $calendar->disableStyles = true; 
			if($arr[0] == "useeventlist") $eventListDays = $arr[1];
			if($arr[0] == "lockdown") $lockdown = true;
			if($arr[0] == "yearoffset") $calendar->setYearsOffset($arr[1]);
			if($arr[0] == "date") $dateValue = $arr[1];
			if($arr[0] == "charlimit") $calendar->charLimit = $arr[1];
			if($arr[0] == "enablesummary") $calendar->summaryCharLimit = $arr[1];			
			if($arr[0] == "maxdailyevents") $calendar->maxDailyEvents = $arr[1];
			if($arr[0] == "subscribe") $calendar->subscribedPages = split(",", $arr[1]);
			if($arr[0] == "fullsubscribe") $calendar->calendarPageName = htmlspecialchars($arr[1]);
		}
	}
	
    // check for user set parameters
	if(isset($params["disableaddevent"])) $calendar->showAddEvent = false;	
    if(isset($params["usetemplates"])) $calendar->enableTemplates = true;
    if(isset($params["defaultedit"])) $calendar->defaultEdit = true;
	if(isset($params["disablelinks"])) $calendar->disableLinks = true;
	if(isset($params["usemultievent"])) $calendar->useMultiEvent = true; 
	if(isset($params["locktemplates"])) $calendar->lockTemplates = true; 
	if(isset($params["disablestyles"])) $calendar->disableStyles = true; 
	
	if(isset($params["date"])) 
		if($params["date"] != "date") $dateValue = $params["date"];
	if(isset($params["useeventlist"])) 
		if($params["useeventlist"] != "useeventlist") $eventListDays = $params["useeventlist"];
	if(isset($params["yearoffset"])) 
		if($params["yearoffset"] != "yearoffset") $calendar->setYearsOffset($params["yearoffset"]);
	if(isset($params["charlimit"])) 
		if($params["charlimit"] != "charlimit") $calendar->charLimit = ($params["charlimit"]);		
	if(isset($params["maxdailyevents"])) 
		if($params["maxdailyevents"] != "maxdailyevents") $calendar->maxDailyEvents = $params["maxdailyevents"];
	if(isset($params["enablesummary"]))
		if($params["enablesummary"] != "enablesummary") $calendar->summaryCharLimit = $params["enablesummary"];

	// no need to pass a parameter here... isset check for the params name, thats it
	if(isset($params["lockdown"]) || isset($lockdown)){
		$calendar->showAddEvent = false;
		$calendar->disableLinks = true;
		$calendar->lockTemplates = true;
	}
	
	// joint calendar...pulling data from our calendar and the subscribers...ie: "title/name" format
	if(isset($params["subscribe"])) 
		if($params["subscribe"] != "subscribe") $calendar->subscribedPages = split(",", $params["subscribe"]);

	// subscriber only calendar...basically, taking the subscribers identity fully...ie: "title/name" format
	if(isset($params["fullsubscribe"])) 
		if($params["fullsubscribe"] != "fullsubscribe") $calendar->calendarPageName = htmlspecialchars($params["fullsubscribe"]);

	//calendar name itself (this is only for (backwards compatibility)
	$calendar->calendarName = htmlspecialchars("CalendarEvents:" .$name);
	
	// finished special conditions; set the $title and $name in the class
	$calendar->setTitle($title);
	$calendar->setName($name);

	$calendar->readStylepage();

    // read the cookie to pull last calendar data
    $cookie_name = 'calendar_' . str_replace(' ', '_', $title) . str_replace(' ', '_', $name);
    if (isset($_COOKIE[$cookie_name]) && !isset($eventListDays) && !isset($dateValue)){
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
				$calendar->addTemplate($month, $year, ($calendar->subscribedPages[$s]));
			
			$calendar->addTemplate($month, $year, ($calendar->calendarPageName));		
			$year = ($month == 12 ? ++$year : $year);
			$month = ($month == 12 ? 1 : ++$month);
		}
	}

	// normal month mode
	if(!isset($eventListDays)  && !isset($dateValue)){
		$calendar->calendarMode = "normal";
		$calendar->debug("End Calendar Normal/Full Mode");
		return "<html>" . $calendar->getHTMLForMonth() . "</html>" . $calendar->getDebugging();
		}

	// event list mode
	if(isset($eventListDays)){
		$calendar->calendarMode = "eventlist";
		$daysOut = ($eventListDays <= 120 ? $eventListDays : 120);
		
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
			$calendar->eventList = "<h4>No Events</h4>";
			
		$calendar->debug("End Calendar EventList Mode");
		return "<html><i> " . $calendar->buildConfigLink(true) . "</i>" .  $calendar->eventList . "</html>" . $calendar->getDebugging();
	}
	
    // specific date mode
    if (isset($dateValue)) {
		$calendar->calendarMode = "date";
		$calendar->charLimit = 100;
		if (($dateValue  == "today") || ($dateValue == "tomorrow")){
			if ($dateValue == "tomorrow" ){
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
			$useDash = split("-",$dateValue);
			$useSlash = split("/",$dateValue);
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
			. " <small><i>" . $calendar->buildConfigLink(true) . "</i></small></h4>" ;
		$calendar->debug("End Calendar Single Day Mode");
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
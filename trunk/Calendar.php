<?php

/* Calendar.php
 *
 * - Eric Fortin (1/2009) < kenyu73@gmail.com >
 *
 * - Original author(s):
 *   	Simson L. Garfinkel < simsong@acm.org >
 *   	Michael Walters < mcw6@aol.com > 
 * See Readme file for full details
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
	var $version = "3.4.3 (beta)";
	
	var $arrSettings = array();
	
    // [begin] set calendar parameter defaults
	var $calendarMode = "normal";
	var $title = ""; 
	
	var $disableConfigLink = true;

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
		//if($this->setting('debug')){
			// recorded time in seconds
			$steptime = round(microtime(1) - $this->markTime,2);
			$totaltime = round(microtime(1) - $this->startTime,2);
			$this->debugData .= "<tr><td>$e</td><td align=center>$steptime</td><td align=center>$totaltime</td></tr>";
			$this->markTime = microtime(1);
		//}
	}
	
	// writes all the debug data at the bottom of calendar page
	function getDebugging() {
		if($this->setting('debug'))
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
	 
	 // render the calendar
	 function displayCalendar(){
		$this->debug("displayCalendar Started");
		
		$this->readStylepage();
		$this->buildTemplateEvents();
	
		$year_pre = ($this->month==1 ? ($this->year-1) : $this->year);		
		$month_pre = ($this->month==1 ? 12 : $this->month-1);
		
		$this->debug("initalizeMonth Started");	
		
		//load all the month events into memory
		if($this->setting('enablerepeatevents')) 
			$this->initalizeMonth($month_pre, $year_pre); //grab last months events for overlapped repeating events

		$this->initalizeMonth($this->month, $this->year); //grab this months events
		$this->debug("initalizeMonth ENDED");	
		if($this->setting('useeventlist'))
			return $this->buildEventList() . $this->buildTrackTimeSummary();
			
		if($this->setting('date'))
			return $this->buildDateEvent() . $this->buildTrackTimeSummary();

		// if we made it here... there was an error in the previous modes 
		// or no mode was selected...display full calendar
		$this->calendarMode = "normal";
		$this->debug("displayCalendar Ended");	
		return "<html>" . $this->getHTMLForMonth() . "</html>" . $this->getDebugging(). $this->buildTrackTimeSummary();	
	 }
	 
	function initalizeMonth($month, $year){
		$dayOffset = -$first + 1;
	    
	    // build up the months events
	    $numWeeks = floor(($this->getDaysInMonth($year, $month) - $dayOffset + 7) / 7);  	
	    for ($i = 0; $i < $numWeeks; $i += 1) {
			
			// write out the days in the week
			for ($j = 0; $j < 7; $j += 1) {
				$this->buildArticlesForDay($month, $dayOffset, $year);
				$dayOffset += 1;
			}
		}	 
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
		if(!$this->setting('disableaddevent')){
			$tag_addEvent = $this->buildAddEventLink($month, $day, $year);
		}
		else {
			$tag_addEvent = "";
		}

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
		if(!$this->setting('usetemplates')) return "";
		
		$articleName = $this->wikiRoot . $this->calendarPageName . "/" . $this->month . "-" . $this->year . " -Template&action=edit" . "';\">";

		$month = strtolower($this->monthNames[$this->month-1]);
		if($this->setting('locktemplates'))
			$ret = "<input type='button' title='Create a bunch of events in one page (20-25# Vacation)' disabled value='$month events' onClick=\"javascript:document.location='" . $articleName;
		else
			$ret = "<input type='button' title='Create a bunch of events in one page (20-25# Vacation)' value='$month events' onClick=\"javascript:document.location='" . $articleName;
		
		return $ret;			
	}
/*	
	function initNewPage($title, $text){
		$mytitle = Title::newFromText($title);
		$article = new Article($mytitle);
		$res = $article->doEdit($text . "<!-- -->", '');
	}
*/
	// build the 'template' button	
	function buildConfigLink($bTextLink = false){	
		
		if(!$this->setting('useconfigpage')) return;
		
		if($this->setting('useconfigpage',false) == 'disablelinks') return "";
		
		if(!$bTextLink){
			$articleConfig = $this->wikiRoot . $this->configPageName . "&action=edit" . "';\">";
			$ret = "<input type='button' title='Add calendar parameters here' value='config' onClick=\"javascript:document.location='" . $articleConfig;
		}else
			$ret = "<a href='" . $this->wikiRoot . $this->configPageName . "&action=edit'>(config...)</a>";

		return $ret;			
	}
	
	function buildEventList(){
		$setting = $this->setting('useeventlist',false);
		
		if($setting == "") return "";
		
		if($setting > 0){
			$this->calendarMode = "eventlist";
			$daysOut = ($setting <= 120 ? $setting : 120);
			
			$month = $this->month;
			$day = $this->day;
			$year = $this->year;

			$this->updateSetting('charlimit',100);
			
			for($i=0; $i < $daysOut; $i++){
				$this->getHTMLForDay($month, $day, $year);
				$day++;
				//lets check for overlap to next month or next year...
				$daysMonth = $this->getDaysInMonth($year,$month);
				if($day > $daysMonth){
					$day = 1;
					$month++;
					if($month > 12){
						$month = 1;
						$year++;
					}
				}
			}
			if(strlen($this->eventList) == 0)
				$this->eventList = "<h4>No Events</h4>";
				
			$this->debug("End Calendar EventList Mode");
			
			return "<html><i> " . $this->buildConfigLink(true) . "</i>" .  $this->eventList . "</html>" . $this->getDebugging();
		}
	}

	function buildTemplateEvents(){	

		if($this->setting('usetemplates')){
			$year = $this->year;
			$month = 1;//$this->month;
			$additionMonths = $this->month + 12;
				
			// lets just grab the next 12 months...this load only takes about .01 second per subscribed calendar
			for($i=0; $i < $additionMonths; $i++){ // loop thru 12 months
				for($s=0;$s < count($this->subscribedPages);$s++) //loop thru $i month per subscribed calendar
					$this->addTemplate($month, $year, ($this->subscribedPages[$s]));
				
				$this->addTemplate($month, $year, ($this->calendarPageName));		
				$year = ($month == 12 ? ++$year : $year);
				$month = ($month == 12 ? 1 : ++$month);
			}
		}
	}
	
	// specific date mode
	function buildDateEvent(){
		$setting = $this->setting("date",false);
		
		if($setting == "") return "";
		
		$this->calendarMode = "date";
		$this->arrSettings['charlimit'] = 100;
		
		if (($setting == "today") || ($setting == "tomorrow")){
			if ($setting == "tomorrow" ){
				$this->day++;
				
				//lets check for overlap to next month or next year...
				$daysMonth = $this->getDaysInMonth($this->year,$this->month);
				if($this->day > $daysMonth){
					$this->day = 1;
					$this->month++;
					if($this->month > 12){
						$this->month = 1;
						$this->year++;
					}
				}
			}
		}
		else {
			$useDash = split("-",$setting);
			$useSlash = split("/",$setting);
			$parseDate = (count($useDash) > 1 ? $useDash : $useSlash);
			if(count($parseDate) == 3){
				$this->month = $parseDate[0];
				$this->day = $parseDate[1] + 0; // converts to integer
				$this->year = $parseDate[2] + 0;
			}
		}
		
		// build the "daily" view HTML if we have a good date
		$html = "<table width=\"100%\"><h4>" 
			. $this->monthNames[$this->month -1] . " "
			. $this->day . ", "
			. $this->year
			. " <small><i>" . $this->buildConfigLink(true) . "</i></small></h4>" ;
			
		$this->debug("End Calendar Single Day Mode");
		
		return "<html>" . $this->cleanDayHTML($html. $this->getHTMLForDay($this->month,$this->day,$this->year) 
		. "</table></html>" 
		. $this->getDebugging());	
		
	}

    function getHTMLForMonth() {   
		$this->debug("getHTMLForMonth Started");
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
    	$yearoffset = $this->setting('yearoffset',false);

	    // build the year select box, with +/- 5 years in relation to the currently selected year
	    $tag_yearSelect = "<select name='yearSelect' method='post' onChange='javascript:this.form.submit()'>";
		for ($i = ($this->year - $yearoffset); $i <= ($this->year + $yearoffset); $i += 1) {
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

		if(!isset($params["disablestyles"])){
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
		
		//$tag_timeTrackValues = $this->buildTrackTimeSummary();  	
		
	    /***** Do footer *****/
	    $tempString = $html_footer;

		// replace potential variables in footer
		$tempString = str_replace("[[TodayData]]", $tag_HiddenData, $tempString);
		$tempString = str_replace("[[TemplateButton]]", $tag_templateButton, $tempString);
		$tempString = str_replace("[[EventStyleBtn]]", $tag_eventStyleButton, $tempString);
		$tempString = str_replace("[[Version]]", $this->version, $tempString);
		$tempString = str_replace("[[ConfigurationButton]]", $tag_configButton, $tempString);
		$tempString = str_replace("[[TimeTrackValues]]", $tag_timeTrackValues, $tempString);
		
	    $ret .= $tempString;
  		
	    /***** Do calendar end code *****/
	    $ret .= $html_calendar_end;
 

		$this->debug("getHTMLForMonth Ended");
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

    // builds the day events into memory
    function buildArticlesForDay($month, $day, $year) {
    	$articleName = "";    	// the name of the article to check for

		$summaryLength = $this->setting('enablesummary',false);

		for ($i = 0; $i <= $this->setting('maxdailyevents',false); $i++) {
			$articleName = $this->calendarPageName . "/" . $month . "-" . $day . "-" . $year . " -Event " . $i;	
			$this->addArticle($month, $day, $year, $articleName, $summaryLength);
			
			// subscribed events
			for($s=0; $s < count($this->subscribedPages); $s++){
				$articleName = $this->subscribedPages[$s] . "/" .  $month . "-" . $day . "-" . $year . " -Event " . $i;		
				$this->addArticle($month, $day, $year, $articleName, $summaryLength);				
				
			}
			
			// (* backwards compatibility only *)
			// must use the name parameter even in fullsubscribe mode: <calendar name="Team" fullsubscribe="Main Page/Team" />
			// if you dont, you will not get the older style events in your calendar...
			$articleName = $this->calendarName . " (" . $month . "-" . $day . "-" . $year . ") - Event " . $i;
			$this->addArticle($month, $day, $year, $articleName, $summaryLength);
		}
		//$this->debug("buildArticlesForDay ENDED");	
    }

	//hopefully a catchall of most types of returns values
	function setting($param, $retBool=true){
	
		//not set; return bool false
		if(!isset($this->arrSettings[$param]) && $retBool) return false;
		if(!isset($this->arrSettings[$param]) && !$retBool) return "";
		
		//set, but no value; return bool true
		if($param == $this->arrSettings[$param] && $retBool) return true;
		if($param == $this->arrSettings[$param] && !$retBool) return "";
		
		// contains data; so lets return it
		return $this->arrSettings[$param];
	}
	
	function updateSetting($params, $value = null){
		$this->arrSettings[$params] = $value;
	}
	
	// Set/Get accessors	
	function setMonth($month) { $this->month = $month; } /* currently displayed month */
	function setYear($year) { $this->year = $year; } /* currently displayed year */
	function setTitle($title) { $this->title = str_replace(' ', '_', $title); }
	function setName($name) { $this->name = str_replace(' ', '_', $name); }
	function createAlert($day, $month, $text){$this->arrAlerts[] = $day . "-" . $month . "-" . $text . "\\n";}
}

// called to process <Calendar> tag.
// most $params[] values are passed right into the calendar as is...
function displayCalendar($paramstring = "", $params = array()) {
    global $wgParser;
	global $wgScript;
	global $wgCalendarPath;
	global $wgTitle;
	global $wgOut, $wgRequest;
	
	global $wgSitename;
	
	$debug = "";
	
    $wgParser->disableCache();
	$wikiRoot = $wgScript . "?title=";
	
	// grab the page title
	$title = $wgTitle->getPrefixedText();	
	
	$calendar = null;	
	$calendar = new Calendar($wgCalendarPath, $wikiRoot, isset($params["debug"]));

	if(!isset($params["name"])) $params["name"] = "Public";
	$name = checkForMagicWord($params["name"]);
		
	// normal calendar...
	$calendar->calendarPageName = htmlspecialchars($title . "/" . $name);
	$calendar->configPageName = htmlspecialchars("$title/$name/config");
		
	if(isset($params["useconfigpage"])) {	
		$configs = $calendar->getConfig("$title/$name");
		
		//merge the config page and the calendar tag params; tag params overwrite config file
		$params = array_merge($configs, $params);	
	}
	
	//set defaults that are required later in the code...
	if(!isset($params["timetrackhead"])) 	$params["timetrackhead"] = "Event, Value";
	if(!isset($params["maxdailyevents"])) 	$params["maxdailyevents"] = 5;
	if(!isset($params["yearoffset"])) 		$params["yearoffset"] = 2;
	if(!isset($params["charlimit"])) 		$params["charlimit"] = 20;
	
	// no need to pass a parameter here... isset check for the params name, thats it
	if(isset($params["lockdown"])){
		$params['disableaddevent'] = true;
		$params['disablelinks'] = true;
		$params['locktemplates'] = true;
	}
	
	// this needs to be last after all required $params are updated, changed, defaulted or whatever
	$calendar->arrSettings = $params;
	
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

    // read the cookie to pull last calendar data
    $cookie_name = 'calendar_' . str_replace(' ', '_', $title) . str_replace(' ', '_', $name);
    if (isset($_COOKIE[$cookie_name]) && !isset($params["useeventlist"]) && !isset($params["date"])){
		$temp = split("`", $_COOKIE[$cookie_name]);
		$calendar->setMonth($temp[0]);
		$calendar->setYear($temp[1]);
		$calendar->setTitle($temp[2]);
		$calendar->setName($temp[3]);
	}
	
	return $calendar->displayCalendar();
}

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
} //end define MEDIAWIKI
?>
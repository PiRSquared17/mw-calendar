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
 
//no clue why, but this needs to be out here for windows...
session_start();

// this is the "refresh" code that allows the calendar to switch time periods
if (isset($_POST["today"]) || isset($_POST["yearBack"]) || isset($_POST["yearForward"]) 
	|| isset($_POST["monthBack"]) || isset($_POST["monthForward"]) || isset($_POST["monthSelect"]) 
	|| isset($_POST["yearSelect"]) || isset($_POST["ical"]) || isset($_POST["mode"]) ){

	$today = getdate();    	// today
	$temp = split("`", $_POST["calendar_info"]); // calling calendar info (name,title, etc..)

	// set the initial values
	$month = $temp[0];
	$year = $temp[1];	
	$title =  $temp[2];
	$name =  $temp[3];
	
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
	
	$session_type = '';
	if(isset($_POST["mode"]))
		$session_type = $_POST["mode"]; //year, month, week, day, list
	
	$session_name = $title . "_" . $name;
	$session_value = $month . "`" . $year . "`" . $title . "`" . $name . "`" . $session_type . "`";
	
	//session_start();
	$_SESSION[$session_name] = $session_value;

	if(isset($_POST["ical"])){
		$path = "images/";
		$path = $path . basename( $_FILES['uploadedfile']['name']); 
		move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $path);
		
		$_SESSION['calendar_ical'] = $path;
	}
}

# Confirm MW environment
if (defined('MEDIAWIKI')) {

$gVersion = "3.6 (beta)";

# Credits	
$wgExtensionCredits['parserhook'][] = array(
    'name'=>'Calendar',
    'author'=>'Eric Fortin',
    'url'=>'http://www.mediawiki.org/wiki/Extension:Calendar_(Kenyu73)',
    'description'=>'MediaWiki Calendar',
    'version'=>$gVersion
);
	
$wgExtensionFunctions[] = "wfCalendarExtension";


// function adds the wiki extension
function wfCalendarExtension() {
    global $wgParser;
    $wgParser->setHook( "calendar", "displayCalendar" );
}

require_once ("common.php");
require_once ("CalendarArticles.php");
require_once ("ical.class.php");
require_once ("debug.class.php");

class Calendar extends CalendarArticles
{  
	var $debug; //debugger class
	
	var $arrSettings = array();
	
    // [begin] set calendar parameter defaults
	var $calendarMode = "normal";
	var $title = ""; 
	
	var $disableConfigLink = true;

	var $arrAlerts = array();
	var $subscribedPages = array();
	
	var $tag_year_view = "";
	var $tag_month_view = "";
	var $tag_day_view = "";
	var $tag_views = "";

	// setup calendar arrays
    var $daysInMonth = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);   
    var $dayNames   = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");	

    var $monthNames = array("January", "February", "March", "April", "May", "June",
                            "July", "August", "September", "October", "November", "December");
							
	var $monthNamesShort = array("Jan", "Feb", "Mar", "Apr", "May", "June",
		"July", "Aug", "Sept", "Oct", "Nov", "Dec");
					
    function Calendar($wikiRoot, $debug) {
		$this->wikiRoot = $wikiRoot;
		
		$this->debug = new debugger('html');
		$this->debug->enabled($debug);

		// set the calendar's initial date to now
		$today = getdate();    	
		$this->month = $today['mon'];
		$this->year = $today['year'];
		$this->day = $today['mday'];
		
		$this->debug->set("Calendar Constructor Ended.");
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
	
	 // render the calendar
	 function renderCalendar($userMode){

		$ret = "";
		
		$this->initalizeHTML();		
		$this->readStylepage();
		$this->buildTemplateEvents();	

		//grab last months events for overlapped repeating events
		if($this->setting('enablerepeatevents')) 
			$this->initalizeMonth($this->day +15, 0); // this checks 1/2 way into the previous month
		else
			$this->initalizeMonth($this->day, 0); // just go back to the 1st of the current month
		
		// what mode we going into
		if($userMode == 'year')
			$ret = $this->renderYear();		
			
		if($userMode == 'month')
			$ret = $this->renderMonth();

		if($userMode == 'week')
			$ret = $this->renderWeek($this->setting('5dayweek'));		
			
		if($userMode == 'day')
			$ret = $this->renderDate();
	
		if($userMode == 'events')
			$ret = $this->renderEventList();
			
		//tag on extra info at the end of whatever is displayed
		$ret .= $this->buildTrackTimeSummary();
		$ret .= $this->debug->get();
		
		return $ret;
	 }
	
	// build the months articles into memory
	// $back: days back from ($this->day)
	// $forward: days ahead from ($this->day)
	function initalizeMonth($back, $forward){
		$this->debug->set('initalizeMonth called');
		
		// just make sure we have a solid negitive here
		$back = -(abs($back));
		
		$cnt = abs($back) + $forward;
		
		$arr_start = datemath($back, $this->month, $this->day, $this->year);
		
		$month = $arr_start['mon'];
		$day = $arr_start['mday'];
		$year = $arr_start['year'];
		
		
	    for ($i = 1; $i <= $cnt; $i++) {
			$this->buildArticlesForDay($month, $day, $year);
			getNextValidDate($month, $day, $year);
		}	
	}
	
	function initalizeHTML(){
		
		// set paths			
		$extensionPath = $this->setting('path'); //dirname(__FILE__);
		$extensionPath = str_replace("\\", "/", $extensionPath);
		
		// build template
		$data_start = "<!-- Calendar Start -->";
		$css = $this->setting('css');		
		$html_data = file_get_contents($extensionPath . "/templates/calendar_template.html");
		$data_end = "<!-- Calendar End -->";	
		
		//check for valid css file
		if(file_exists($extensionPath . "/templates/$css"))
			$css_data = file_get_contents($extensionPath . "/templates/$css");	
		else
			$css_data = file_get_contents($extensionPath . "/templates/default.css");
			

		$this->html_template = $data_start . $css_data . $html_data . $data_end;
	
		$this->daysNormalHTML   = $this->html_week_array("<!-- %s %s -->");
		$this->daysSelectedHTML = $this->html_week_array("<!-- Selected %s %s -->");
		$this->daysMissingHTML  = $this->html_week_array("<!-- Missing %s %s -->");
		
		if(!$this->setting('disablemodes')){
			$this->tag_views  = "<input class='btn' name='mode' type='submit' value='year'>"
				. "<input class='btn' name='mode' type='submit' value='month'>"
				. "<input class='btn' name='mode' type='submit' value='week'>";
			}
					
		// build the hidden calendar date info (used to offset the calendar via sessions)
		$this->tag_HiddenData = "<input class='btn' type='hidden' name='calendar_info' value='"
			. $this->month . "`"
			. $this->year . "`"
			. $this->title . "`"
			. $this->name . "`"
			. "'>";			
	}
	
    // Generate the HTML for a given month
    // $day may be out of range; if so, give blank HTML
    function getHTMLForDay($month, $day, $year, $dateFormat='default', $mode='month'){
		$tag_eventList = $tag_dayCustom = "";

		$max = getDaysInMonth($month, $year);
		
		if ($day <=0 || $day > $max)		
			return $this->daysMissingHTML[0];

		$thedate = getdate(mktime(12, 0, 0, $month, $day, $year));
		$today = getdate();
		$wday  = $thedate['wday'];
		$weekday = $thedate['weekday'];

		$display_day = $day;
		if($dateFormat == 'long'){
			//$display_day = $this->monthNames[$month -1] . " $day, $year";
			$display_day = $weekday . ", " . $this->monthNamesShort[$month -1] . " $day";
		}
		
		if($dateFormat == 'none')
			$display_day = "";

		if ($thedate['mon'] == $today['mon']
			&& $thedate['year'] == $today['year']
			&& $thedate['mday'] == $today['mday']) {
			$tempString = $this->daysSelectedHTML[$wday];
		}
		else {
			$tempString = $this->daysNormalHTML[$wday];
		}
					

		$tag_addEvent = $this->buildAddEventLink($month, $day, $year);

		$tag_mode = 'monthMode';
		if($mode == 'events'){
			$tag_mode = 'eventsMode';
			$tag_dayCustom = "eventsDay";
		}
		if($mode=='week'){
			$tag_mode = 'weekMode';
		}
		if($mode == 'day'){
			$tag_mode = 'dayMode';
			$tag_dayCustom = "singleDay";
		}
		
		//build formatted event list
		$tag_eventList = $this->getArticleLinks($month, $day, $year, true);
		
		// no events, then return nothing!
		if((strlen($tag_eventList) == 0) && ($mode == 'events')) return "";
		
		$tempString = str_replace("[[Day]]", $display_day, $tempString);
		
		$tag_alerts = $this->buildAlertLink($day, $month);
		
		//kludge... for some reason, the "\n" is removed in full calendar mode
		if($mode == "monthMode")
			$tag_eventList = str_replace("\n", " ", $tag_eventList); 
			
		$tempString = str_replace("[[AddEvent]]", $tag_addEvent, $tempString);
		$tempString = str_replace("[[EventList]]", "<ul>" . $tag_eventList . "</ul>", $tempString);
		$tempString = str_replace("[[Alert]]", $tag_alerts, $tempString);
		$tempString = str_replace("[[mode]]", $tag_mode, $tempString);
		$tempString = str_replace("[[dayCustom]]", $tag_dayCustom, $tempString);
		
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

		$articleName = $this->wikiRoot . $this->calendarPageName . "/" . $this->month . "-" . $this->year . " -Template&action=edit" . "'\">";
		
		$month = strtolower($this->monthNames[$this->month-1]);
		if($this->setting('locktemplates'))
			$ret = "<input class='btn' type='button' title='Create a bunch of events in one page (20-25# Vacation)' disabled value='$month events' onClick=\"javascript:document.location='" . $articleName;
		else
			$ret = "<input class='btn' type='button' title='Create a bunch of events in one page (20-25# Vacation)' value='$month events' onClick=\"javascript:document.location='" . $articleName;
		
		return $ret;			
	}

	function loadiCalLink(){
		$refresh = $this->wikiRoot .  $this->title . "&action=purge";	
		
		$ret = "Please specify an ical format file (vcalendar).<br>"
			. "<input name='uploadedfile' type='file' title='Browse to file location...' size='50'><br>"	
			. "<input name='ical' class='btn' type='submit' title='load ical data into calendar' value='load'>&nbsp;&nbsp;"
			. "<input class='btn' type=button value='reload page' title='Click to reload/refresh this wiki page' onClick=\"javascript:document.location='" . $refresh ."'\">";
			
		return $ret;
	}
	
	// build the 'template' button	
	function buildConfigLink($bTextLink = false){	
		
		if(!$this->setting('useconfigpage')) return;
		
		if($this->setting('useconfigpage',false) == 'disablelinks') return "";
		
		if(!$bTextLink){
			$articleConfig = $this->wikiRoot . $this->configPageName . "&action=edit" . "';\">";
			$ret = "<input class='btn' type='button' title='Add calendar parameters here' value='config' onClick=\"javascript:document.location='" . $articleConfig;
		}else
			$ret = "<a href='" . $this->wikiRoot . $this->configPageName . "&action=edit'>(config...)</a>";

		return $ret;			
	}
	
	function renderEventList(){
		$events = "";
		
		$setting = $this->setting('useeventlist',false);

		if($setting == "") return "";
		
		if($setting > 0){
			$this->calendarMode = "eventlist";
			$daysOut = ($setting <= 120 ? $setting : 120);

			$month = $this->month;
			$day = $this->day;
			$year = $this->year;			
			
			$this->updateSetting('charlimit',100);
			
			//build the days out....
			$this->initalizeMonth(0, $daysOut);
			
			 $css = $this->searchHTML($this->html_template, 
						     "<!-- CSS Start -->", "<!-- CSS End -->");
			
			for($i=0; $i < $daysOut; $i++){	
				$events .= "<tr>" . $this->getHTMLForDay($month, $day, $year, 'long', 'events') . "</tr>";
				getNextValidDate($month,$day,$year);//bump the date up by 1
			}
		
			$this->debug->set("renderEventList Ended");
			
			$css = $this->stripLeadingSpace($css);
			$ret = "<html><i> " . $this->buildConfigLink(true) . "</i>" 
				. $css . $events . "</html>";

			return "<table>" . $ret . "</table>";	
		}
	}

	function buildTemplateEvents(){	
		if($this->setting('usetemplates')){
			$year = $this->year;
			$month = 1;//$this->month;
			$additionMonths = $this->month + 12;
			
			// lets just grab the next 12 months...this load only takes about .01 second per subscribed calendar
			for($i=0; $i < $additionMonths; $i++){ // loop thru 12 months
				for($s=0;$s < count($this->subscribedPages);$s++){ //loop thru $i month per subscribed calendar
					$this->addTemplate($month, $year, ($this->subscribedPages[$s]));
				}
				
				$this->addTemplate($month, $year, ($this->calendarPageName));		
				$year = ($month == 12 ? ++$year : $year);
				$month = ($month == 12 ? 1 : ++$month);
			}
		}
	}
	
	// used for 'date' mode only...technically, this can be any date
	function updateDate(){
		$this->calendarMode = "date";
		
		$setting = $this->setting("date",false);
		
		if($setting == "") return "";
		
		$this->arrSettings['charlimit'] = 100;
		
		if (($setting == "today") || ($setting == "tomorrow")){
			if ($setting == "tomorrow" ){	
				getNextValidDate($this->month, $this->day, $this->year);		
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
	}
	
	// specific date mode
	function renderDate(){
		
		$this->updateDate(); // update calendar date data
		
		$this->initalizeMonth(0,1);

		$css = $this->searchHTML($this->html_template, 
				 "<!-- CSS Start -->", "<!-- CSS End -->");
				 
		$css = $this->stripLeadingSpace($css);
		
		$ret = $this->buildConfigLink(true). $css
			. $this->getHTMLForDay($this->month, $this->day, $this->year, 'long', 'day');
			
		$this->debug->set("renderDate Ended");		
		return $ret;
		
	}

    function renderMonth() {   
		global $gVersion;
			
		$tag_templateButton = "";
		
		$this->calendarMode = "normal";
       	
	    /***** Replacement tags *****/
	    $tag_monthSelect = "";         	// the month select box [[MonthSelect]] 
	    $tag_previousMonthButton = ""; 	// the previous month button [[PreviousMonthButton]]
	    $tag_nextMonthButton = "";     	// the next month button [[NextMonthButton]]
	    $tag_yearSelect = "";          	// the year select box [[YearSelect]]
	    $tag_previousYearButton = "";  	// the previous year button [[PreviousYearButton]]
	    $tag_nextYearButton = "";      	// the next year button [[NextYearButton]]
	    $tag_calendarName = "";        	// the calendar name [[CalendarName]]
	    $tag_calendarMonth = "";       	// the calendar month [[CalendarMonth]]
	    $tag_calendarYear = "";        	// the calendar year [[CalendarYear]]
	    $tag_day = "";                 	// the calendar day [[Day]]
	    $tag_addEvent = "";            	// the add event link [[AddEvent]]
	    $tag_eventList = "";           	// the event list [[EventList]]
		$tag_eventStyleButton = "";		// event style buttonn [[EventStyleBtn]]
		$tag_templateButton = "";		// template button for multiple events [[TemplateButton]]
		$tag_todayButton = "";			// today button [[TodayButton]]
		$tag_configButton = ""; 		// config page button
		$tag_timeTrackValues = "";     	// summary of time tracked events
		$tag_loadiCalButton = "";
		$tag_about = "";
        
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
		
		//build events into memory for the remainder of the month
		//the previous days have already been loaded
		$this->initalizeMonth(0, (32 - $this->day));
		
	    // the date for the first day of the month
	    $firstDate = getdate(mktime(12, 0, 0, $this->month, 1, $this->year));
	    $first = $firstDate["wday"];   // the day of the week of the 1st of the month (ie: Sun:0, Mon:1, etc)

	    $today = getdate();    	// today's date
	    $isSelected = false;    	// if the day being processed is today
	    $isMissing = false;    	// if the calendar cell being processed is in the current month

	    // referrer (the page with the calendar currently displayed)
	    $referrerURL = $_SERVER['PHP_SELF'];
	    if ($_SERVER['QUERY_STRING'] != '') {
			$str = split("&",$_SERVER['QUERY_STRING']); //strip any &parameters
    		$referrerURL .= "?" . $str[0];
	    }
		
		$this->referrerURL = $referrerURL;		
		
	    /***** Build the known tag elements (non-dynamic) *****/
	    // set the month's name tag
	    $tag_calendarName = str_replace('_', ' ', $this->name);
	    if ($tag_calendarName == "") {
    		$tag_calendarName = "Public";
	    }
    	
		$tag_about = "<a title='Click here is learn more and get help' href='http://www.mediawiki.org/wiki/Extension:Calendar_(Kenyu73)' target='new'>about</a>...";
		
	    // set the month's mont and year tags
	    $tag_calendarMonth = $this->monthNames[$this->month - 1];
	    $tag_calendarYear = $this->year;
    	
	    // build the month select box
	    $tag_monthSelect = "<select name='monthSelect' method='post' onChange='javascript:this.form.submit()'>";
	    for ($i = 0; $i < count($this->monthNames); $i += 1) {
    		if ($i + 1 == $this->month) {
		    $tag_monthSelect .= "<option class='lst' value='" . ($i + 1) . "' selected='true'>" . 
			$this->monthNames[$i] . "</option>\n";
    		}
    		else {
		    $tag_monthSelect .= "<option class='lst' value='" . ($i + 1) . "'>" . 
			$this->monthNames[$i] . "</option>\n";
    		}
	    }
	    $tag_monthSelect .= "</select>";
    	$yearoffset = $this->setting('yearoffset',false);

	    // build the year select box, with +/- 5 years in relation to the currently selected year
	    $tag_yearSelect = "<select name='yearSelect' method='post' onChange='javascript:this.form.submit()'>";
		for ($i = ($this->year - $yearoffset); $i <= ($this->year + $yearoffset); $i += 1) {
    		if ($i == $this->year) {
				$tag_yearSelect .= "<option class='lst' value='$i' selected='true'>" . 
				$i . "</option>\n";
    		}
    		else {
				$tag_yearSelect .= "<option class='lst' value='$i'>$i</option>\n";
    		}
	    }
	    $tag_yearSelect .= "</select>";
    	
		$tag_templateButton = $this->buildTemplateLink();
		$tag_configButton = $this->buildConfigLink(false);

		if(!$this->setting("disablestyles")){
			$articleStyle = $this->wikiRoot . $this->calendarPageName . "/style&action=edit" . "';\">";
			$tag_eventStyleButton = "<input class='btn' type=\"button\" title=\"Set 'html/css' styles based on trigger words (vacation::color:red; font-style:italic)\" value= \"event styles\" onClick=\"javascript:document.location='" . $articleStyle;
		}
	
		// build the 'today' button	
	    $tag_todayButton = "<input class='btn' name='today' type='submit' value='today'>";
		$tag_previousMonthButton = "<input class='btn' name='monthBack' type='submit' value='<<'>";
		$tag_nextMonthButton = "<input class='btn' name='monthForward' type='submit' value='>>'>";
		$tag_previousYearButton = "<input class='btn' name='yearBack' type='submit' value='<<'>";
		$tag_nextYearButton = "<input class='btn' name='yearForward' type='submit' value='>>'>";
		
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
		$ret = str_replace("[[YearView]]", $this->tag_year_view, $ret);
		$ret = str_replace("[[MonthView]]", $this->tag_month_view, $ret);
		$ret = str_replace("[[DayView]]", $this->tag_views, $ret);
		
		
	    /***** Begin building the calendar days *****/
	    // determine the starting day offset for the month
	    $dayOffset = -$first + 1;
		
	    $maxDays = getDaysInMonth($this->month,$this->year);

	    // determine the number of weeks in the month
	    $numWeeks = floor(($maxDays - $dayOffset + 7) / 7);  	

	    // begin writing out month weeks
	    for ($i = 0; $i < $numWeeks; $i += 1) {
			$ret .= $html_week_start;		// write out the week start code	
			
			// write out the days in the week
			for ($j = 0; $j < 7; $j += 1) {
				$ret .= $this->getHTMLForDay($this->month, $dayOffset, $this->year);
				$dayOffset += 1;
			}
			$ret .= $html_week_end; 		// add the week end code
		}   
		
		//$tag_timeTrackValues = $this->buildTrackTimeSummary();  	
		
	    /***** Do footer *****/
	    $tempString = $html_footer;
		
		if($this->setting('ical'))
			$tag_loadiCalButton = $this->loadiCalLink();
		// replace potential variables in footer
		$tempString = str_replace("[[TodayData]]", $this->tag_HiddenData, $tempString);
		$tempString = str_replace("[[TemplateButton]]", $tag_templateButton, $tempString);
		$tempString = str_replace("[[EventStyleBtn]]", $tag_eventStyleButton, $tempString);
		$tempString = str_replace("[[Version]]", $gVersion, $tempString);
		$tempString = str_replace("[[ConfigurationButton]]", $tag_configButton, $tempString);
		//$tempString = str_replace("[[TimeTrackValues]]", $tag_timeTrackValues, $tempString);
		$tempString = str_replace("[[Load_iCal]]", $tag_loadiCalButton, $tempString);
		$tempString = str_replace("[[About]]", $tag_about, $tempString);
		
	    $ret .= $tempString;
  		
	    /***** Do calendar end code *****/
	    $ret .= $html_calendar_end;
 			
		$this->debug->set("renderMonth Ended");	
		$ret = "<html>" . $this->stripLeadingSpace($ret) . "</html>";
			
	    return $ret;	
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
			
			// check for legacy events (prior to 1/1/2009 or so...) format - "name (12-15-2008) - Event 1"
			// enabling causes additional load times
			if($this->setting('enablelegacy')){
			
				// initialize
				$current_timestamp = mktime(0,0,0,$month,$day,$year);				
				$legacy_timestamp = $current_timestamp +1;
				
				$legacy = split('-', $this->setting('enablelegacy', false));
				
				if(count($legacy) != 3)
					$legacy = split('/', $this->setting('enablelegacy', false));

				if(count($legacy) == 3)
					$legacy_timestamp = mktime(0,0,0,$legacy[0],$legacy[1],$legacy[2]);

				// if date is included with 'enablelegacy' then only pick up legacy events
				// events if viewing those months that included them...
				if($legacy_timestamp >= $current_timestamp){
				
					// with namespace...
					$articleName = $this->legacyName1 . " (" . $month . "-" . $day . "-" . $year . ") - Event " . $i;
					$this->addArticle($month, $day, $year, $articleName, $summaryLength);
					
					// without namespace...
					$articleName = $this->legacyName2 . " (" . $month . "-" . $day . "-" . $year . ") - Event " . $i;
					$this->addArticle($month, $day, $year, $articleName, $summaryLength);		
				}
			}
		}
    }	
	
	function buildSimpleCalendar($month, $year, $sixRow=false){

		$row = $todayStyle = "";

		$firstDate = getdate(mktime(0, 0, 0, $month, 1, $year));
	    $first = $firstDate["wday"];   // the day of the week of the 1st of the month (ie: Sun:0, Mon:1, etc)

		$today = getdate();    	// today's date
		
		$dayOffset = -$first + 1;
    
	    // determine the number of weeks in the month
		$maxDays = getDaysInMonth($month,$year);
	    $numWeeks = floor(($maxDays - $dayOffset + 7) / 7);  	

		if($sixRow) $numWeeks = 6;
		
		$monthname = $this->monthNames[$month - 1];
		//$monthname = "<a href=''>$monthname";

		$ret = "<tr><td class='yearTitle' colspan=7>" . $monthname . "</td></tr>";				
		$ret .= "
			<tr>
				<td class='yearHeading'>S</td>
				<td class='yearHeading'>M</td>
				<td class='yearHeading'>T</td>
				<td class='yearHeading'>W</td>
				<td class='yearHeading'>T</td>
				<td class='yearHeading'>F</td>
				<td class='yearHeading'>S</td>
			</tr>";
		
		for ($i = 0; $i < $numWeeks; $i++) {	
			for($j=0; $j < 7; $j++){
				if($year == $today['year'] && $month == $today['mon'] && $dayOffset == $today['mday'])
					$todayStyle = "style='background-color: #C0C0C0;font-weight:bold;'";
					
				if($dayOffset > 0 && $dayOffset <= $maxDays){
					$link = $this->buildAddEventLink($month, $dayOffset, $year, $text=$dayOffset);
					if($j==0 || $j==6)
						$row .= "<td class='yearWeekend $todayStyle'>$link</td>";
					else
						$row .= "<td class='yearWeekday' $todayStyle>$link</td>";
				}
				else{
					$row .= "<td>&nbsp;</td>";	
				}
				
				$todayStyle = "";
				$dayOffset++;
			}
			
			$ret .= "<tr>$row</tr>";
			$row = "";
		}
		
		return "<table class='yearCalendarMonth'>$ret</table>";
	}	

	function renderYear(){
	
		$tag_mini_cal_year = "";
		
		//$styleContainer = "style='width:100%; border:1px solid #CCCCCC; border-collapse:collapse;'";
		$styleTitle = "style='text-align:center; font-size:24px; font-weight:bold;'";
		
		$html_head = "<table class='yearCalendar'><form  method='post'>";
		$html_foot = "</table></form>";
		
		$ret = ""; $cal = "";
		$nextMon=1;
		$nextYear = $this->year;
		
		$title = "$this->year";
	
		$css = $this->searchHTML($this->html_template, 
			 "<!-- CSS Start -->", "<!-- CSS End -->");
				 
		$css = $this->stripLeadingSpace($css);
		
		$ret = "<tr><td></td><td $styleTitle colspan=2>$title</td><td align=right>$this->tag_views</td></tr>";

		for($m=0;$m <12; $m++){
			$cal .= "<td style='text-align:center; vertical-align:top;'>" . $this->buildSimpleCalendar($nextMon++, $nextYear, true) . "</td>";
			
			if($m==3 || $m==7 || $m==11){
				$ret .= "<tr>$cal</tr>";
				$cal = "";
			}	
		}	
		
		return $css . $html_head . $ret . $this->tag_HiddenData . $html_foot ;
	}
	
	function renderWeek($fiveDay=false){
		$this->initalizeMonth(0,8);
		
		//defaults
		$sunday = $saturday  = $ret = $week = ""; 
		$colspan = 3; 
		
		$styleTable = "style='border-collapse:collapse; width:100%;'";
		$styleTitle = "style='font-size: 24px;'";
		
		$html_head = "<form  method='post'><table $styleTable>";
		$html_foot = "</table></form>";
		
		$weekday = date('N', mktime(12, 0, 0, $this->month, $this->day, $this->year));
		$date = datemath(-($weekday), $this->month, $this->day, $this->year);

		$month = $date['mon'];
		$day = $date['mday'];
		$year = $date['year'];
		
		$title = $date['month'];
		
		$css = $this->searchHTML($this->html_template, 
				 "<!-- CSS Start -->", "<!-- CSS End -->");
				 
		$css = $this->stripLeadingSpace($css);
		
		if(!$fiveDay){
			$sunday = "<td class='calendarHeading'>Sunday</td>";
			$saturday = "<td class='calendarHeading'>Saturday</td>";
			$colspan = 5; //adjuct for mode buttons
		}
		
		//hide mode buttons if selected via parameter tag

		$ret .= "<tr><td colspan=2 $styleTitle>$title</td><td align=right colspan=$colspan>$this->tag_views</td></tr>";	
		$ret .= "<tr>";
		$ret .= $sunday;
		$ret .= "<td class='calendarHeading'>Monday</td>";
		$ret .= "<td class='calendarHeading'>Tuesday</td>";
		$ret .= "<td class='calendarHeading'>Wednesday</td>";
		$ret .= "<td class='calendarHeading'>Thursday</td>";
		$ret .= "<td class='calendarHeading'>Friday</td>";
		$ret .= $saturday;
		$ret .= "</tr>";
		
		if($fiveDay) getNextValidDate($month, $day, $year);
		
		for($i=0; $i<7; $i++){
			if($fiveDay && $i==0) $i=2;
			$week .= $this->getHTMLForDay($month, $day, $year, 'short', 'week');
			getNextValidDate($month, $day, $year);
		}

		$ret .= "<tr>" . $week . "</tr>";
		
		$this->debug->set("renderWeek Ended");	
		return $css . $html_head . $ret . $this->tag_HiddenData . $html_foot;
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
	
	// php has a defualt of 30sec to run a script, so it can timeout...
	function load_iCal(){
		$this->debug->set('load_iCal Started');
		
		$bMulti = false;
		$ical_data = $this->ical_data;		
		$iCal = new ical_calendar;

		//make sure we're good before we go further
		if(!$iCal->setFile($ical_data)) return;
		$arr = $iCal->getData();

		$ical_count = count($arr);
		
		set_time_limit(120); //increase the script timeout for this load to 2min
		
		for($i=0; $i<$ical_count; $i++){
			if(isset($arr[$i]['DTSTART'])){
				$date = $arr[$i]['DTSTART'];
				
				$date_diff = day_diff($arr[$i]['DTSTART'], $arr[$i]['DTEND']);
	
				$event = $arr[$i]['SUMMARY'];
				$description = $arr[$i]['DESCRIPTION'];	
				
				$date = $date['mon']."-".$date['mday']."-".$date['year'];
				
				$ical_mode = $this->setting('ical',false);
				if($ical_mode == 'usemultievent') $bMulti = true;
				
				$page = $this->getNextAvailableArticle($this->calendarPageName, $date, $bMulti);

				if($date_diff > 0){
					if($date_diff > 1)
						$event = ceil($date_diff) . "#" . $event;
					else
						$event = $event;
				}

				if($bMulti)
					$this->createNewMultiPage($page, $event, $description, "iCal Import");
				else
					$this->createNewPage($page, $event, $description, "iCal Import");

			}
		}
		set_time_limit(30);
		
		echo "<html><script>alert('Completed the import of $ical_count records. Please click on the reload page button to clear the page cache.')</script></html>";	

		// refresh the page
		echo "<script>window.onload=function() { document.forms['cal_frm'].submit(); }</script>";
		$this->debug->set('load_iCal Ended');
	}
	
	// Set/Get accessors	
	function setMonth($month) { $this->month = $month; } /* currently displayed month */
	function setYear($year) { $this->year = $year; } /* currently displayed year */
	function setTitle($title) { $this->title = $title; }
	function setName($name) { $this->name = $name; }
	function setMode($mode) { $this->mode = $mode; }
	function createAlert($day, $month, $text){$this->arrAlerts[] = $day . "-" . $month . "-" . $text . "\\n"; }
}

// called to process <Calendar> tag.
// most $params[] values are passed right into the calendar as is...
function displayCalendar($paramstring = "", $params = array()) {
    global $wgParser;
	global $wgScript;
	global $wgTitle;
	global $wgOut, $wgRequest;

    $wgParser->disableCache();
	$wikiRoot = $wgScript . "?title=";
	$userMode = 'month';

	// grab the page title
	//$title = $wgTitle->getPrefixedText();	
	$title = $wgParser->getVariableValue("fullpagename");
	
	$config_page = " ";

	$calendar = null;	
	$calendar = new Calendar($wikiRoot, isset($params["debug"]));

	if(!isset($params["name"])) $params["name"] = "Public";
	
	// set path		
	$params['path'] = str_replace("\\", "/", dirname(__FILE__));
		
	$name = checkForMagicWord($params["name"]);
		
	// normal calendar...
	$calendar->calendarPageName = htmlspecialchars($title . "/" . $name);
	$calendar->configPageName = htmlspecialchars("$title/$name/config");
	
	// disabling for now... causing wierd errors with mutiple calendars per page
	//(UNIQ249aadf6593f3f85-calendar-00000000-QINU}
	//$calendar->createNewPage("$title/$name/config", buildConfigString());	
	
	if(isset($params["useconfigpage"])) {	
		$configs = $calendar->getConfig("$title/$name");
		
		//merge the config page and the calendar tag params; tag params overwrite config file
		$params = array_merge($configs, $params);	
	}
	
	//set defaults that are required later in the code...
	if(!isset($params["timetrackhead"])) 	$params["timetrackhead"] = "Event, Value";
	if(!isset($params["maxdailyevents"])) 	$params["maxdailyevents"] = 5;
	if(!isset($params["yearoffset"])) 		$params["yearoffset"] = 2;
	if(!isset($params["charlimit"])) 		$params["charlimit"] = 25;
	if(!isset($params["css"])) 				$params["css"] = "default.css";

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
	$calendar->legacyName1 = "CalendarEvents:" .$name;
	$calendar->legacyName2 = $name;
	
	// finished special conditions; set the $title and $name in the class
	$calendar->setTitle($title);
	$calendar->setName($name);

	$session = $title . "_" . $name;

	//session_start();
	if(isset($_SESSION[$session])){
		$calendar->debug->set('session loaded');

		$arrSession = split("`", $_SESSION[$session]);
		$calendar->setMonth($arrSession[0]);
		$calendar->setYear($arrSession[1]);	
		$calendar->setTitle($arrSession[2]);				
		$calendar->setName($arrSession[3]);	

		if(strlen($arrSession[4]) > 0)
			$userMode = $arrSession[4];
	}
	else{
		// defaults from the <calendar /> parameters; must restart browser to enable
		if(isset($params['week'])) $userMode = 'week';
		if(isset($params['year'])) $userMode = 'year';
		if(isset($params['useeventlist'])) $userMode = 'events';
		if(isset($params['date'])) $userMode = 'day';		
	}
	
	if(isset($_SESSION['calendar_ical'])){
		$calendar->debug->set('ical session loaded');		
		$calendar->ical_data = $_SESSION['calendar_ical'];
		$calendar->load_iCal();

		@unlink($_SESSION['calendar_ical']); //delete ical file in "mediawiki/images" folder	
		unset($_SESSION['calendar_ical']);
	}

	
	return $calendar->renderCalendar($userMode);
}

// setup the config page with a listing of current parameters
function buildConfigString(){
	$string = "The following are the standard parameter options available. If you clear " .
		"the page, the defaults will return. Just remove the 'x' as needed.\n\n" .
		"x usetemplates\n" .
		"x locktemplates\n" .
		"x defaultedit\n" .
		"x disableaddevent\n" .
		"x yearoffset=5\n" .
		"x date=today\n" .
		"x useeventlist=90\n" .
		"x subscribe='page/calendar name1, page/calendar name2, ...'\n" .
		"x fullsubscribe='page/calendar name'\n" .
		"x disablelinks\n" .
		"x usemultievent\n" .
		"x maxdailyevents=3\n" .
		"x disablestyles\n" .
		"x css='olive.css'\n" .
		"x disabletimetrack\n" .
		"x enablerepeatevents\n" .
		"x enablelegacy\n" .
		"x lockdown\n";
		
	return $string;
}


} //end define MEDIAWIKI
?>
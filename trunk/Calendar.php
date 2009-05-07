<?php
/* Calendar.php
 *
 * - Eric Fortin < kenyu73@gmail.com >
 *
 * - Original author(s):
 *   	Simson L. Garfinkel < simsong@acm.org >
 *   	Michael Walters < mcw6@aol.com > 
 * See Readme file for full details
 */

// this is the "refresh" code that allows the calendar to switch time periods
if (isset($_POST["calendar_info"]) ){
	
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

	$mode = $_POST["viewSelect"];
	
	$cookie_name = str_replace(' ', '_', ($title . "_" . $name));
	$cookie_value = $month . "`" . $year . "`" . $title . "`" . $name . "`" . $mode . "`";
	setcookie($cookie_name, $cookie_value);
	
	if(isset($_POST["ical"])){
		$path = "images/";
		$path = $path . basename( $_FILES['uploadedfile']['name']); 
		move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $path);
		
		setcookie('calendar_ical', $path);
	}	
	
	// reload the page..clear any purge commands that may be in it from an ical load...
	$url = str_replace("&action=purge", "", $_SERVER['REQUEST_URI']);
	header("Location: " . $url);
}

# Confirm MW environment
if (defined('MEDIAWIKI')) {

$gCalendarVersion = "v3.7.6 (5/5/2009)";

# Credits	
$wgExtensionCredits['parserhook'][] = array(
    'name'=>'Calendar',
    'author'=>'Eric Fortin',
    'url'=>'http://www.mediawiki.org/wiki/Extension:Calendar_(Kenyu73)',
    'description'=>'MediaWiki Calendar',
    'version'=>$gCalendarVersion
);

$path = dirname( __FILE__ );

$wgExtensionFunctions[] = "wfCalendarExtension";
$wgExtensionMessagesFiles['wfCalendarExtension'] = "$path/calendar.i18n.php";


//$wgHooks['LanguageGetMagic'][]       = 'wfCalendarFunctions_Magic';

 // function adds the wiki extension
function wfCalendarExtension() {
	global $wgParser;
	global $wgParser, $wgHooks;
	global $wgCalendarSidebarRef;
	$wgParser->setHook( "calendar", "displayCalendar" );
	wfLoadExtensionMessages( 'wfCalendarExtension' ); 
 //   if ( isset($wgCalendarSidebarRef) ) $wgHooks['SkinTemplateOutputPageBeforeExec'][] = 
//		'wfCalendarSkinTemplateOutputPageBeforeExec';
}

// Hook to inject Calendar into sidebar
function wfCalendarSkinTemplateOutputPageBeforeExec( &$skin, &$tpl ) {
    global $wgCalendarSidebarRef;
    $html = displayCalendar('', array('simplemonth' => true, 'fullsubscribe' => $wgCalendarSidebarRef));
    $html .= displayCalendar('', array('date' => 'today', 'fullsubscribe' => $wgCalendarSidebarRef));
    $html .= displayCalendar('', array('date' => 'tomorrow', 'fullsubscribe' => $wgCalendarSidebarRef));
    if ( $html ) $tpl->data['sidebar']['calendar'] = $html;
    return true;
}

/*
// calendar conditionals, used to remove the commands from the page view
function calendar(){return "";}
*/

require_once ("$path/common.php");
require_once ("$path/CalendarArticles.php");
require_once ("$path/ical.class.php");
require_once ("$path/debug.class.php");

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
	
	var $tag_views = "";
	
	var $invalidateCache = false;
	var $isFullSubscribe = false;	
										
    function Calendar($wikiRoot, $debug) {
		$this->wikiRoot = $wikiRoot;

		$this->debug = new debugger('html');
		$this->debug->enabled($debug);

		// set the calendar's initial date
		$now = getdate();
		
		$this->month = $now['mon'];
		$this->year = $now['year'];
		$this->day = $now['mday'];
		
		$this->debug->set("Calendar Constructor Ended.");
    }

    function html_week_array($format){
		
		//search values for html template only, no need to Common::translate...
	
		$days = array("weekend","weekday","weekday",
			"weekday","weekday","weekday","weekend");			
		

		$ret = array();			 	
		for($i=0;$i<7;$i++){
			$ret[$i] = $this->searchHTML($this->html_template,
						 sprintf($format,$days[$i],"Start"),
						 sprintf($format,$days[$i],"End"));

		}
		return $ret;
    }
	
	 // render the calendar
	 function renderCalendar($userMode){

		$ret = "";
		$this->mode = $userMode;
		
		// need to change the running calendar date 
		// or the other functions wont set correctly
		if($userMode == 'day')
			$this->updateDate();

		$this->initalizeHTML();		
		$this->readStylepage();
		
		if($this->setting('usetemplates'))
			$this->buildTemplateEvents();
		
		if(!$this->setting('disablerecurrences'))
			$this->buildVCalEvents();
	
		if($this->paramstring != '')
			$this->buildTagEvents($this->paramstring);

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
			
		if($userMode == 'simplemonth')
			$ret = $this->renderSimpleMonth();

		if($userMode == 'week')
			$ret = $this->renderWeek($this->setting('5dayweek'));		
			
		if($userMode == 'day')
			$ret = $this->renderDate();
	
		if($userMode == 'events')
			$ret = $this->renderEventList();
			
		//tag on extra info at the end of whatever is displayed
		$ret .= $this->buildTrackTimeSummary();
		$ret .= $this->debug->get();
	
		if($this->setting('ical'))
			$ret .= $this->loadiCalLink();
	
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
		
		$arr_start = Common::datemath($back, $this->month, $this->day, $this->year);
		
		$month = $arr_start['mon'];
		$day = $arr_start['mday'];
		$year = $arr_start['year'];
		
		
	    for ($i = 1; $i <= $cnt; $i++) {
			$this->buildArticlesForDay($month, $day, $year);
			Common::getNextValidDate($month, $day, $year);
		}	
	}
	
	function initalizeHTML(){
		global $wgOut,$wgScriptPath, $wgVersion;

		$cssURL = $this->getURLRelativePath() . "/templates/";
		
		// set paths			
		$extensionPath = $this->setting('path');
		$extensionPath = str_replace("\\", "/", $extensionPath);
		
		// build template
		$data_start = "<!-- Calendar Start -->";
		
		$css = $this->setting('css');
		
		$css_data = file_get_contents($extensionPath . "/templates/$css");	//ugly method	
		$html_data = file_get_contents($extensionPath . "/templates/calendar_template.html");

		$data_end = "<!-- Calendar End -->";	
		
		//add css; this is set as 'default.css' or an override
		if($wgVersion >= '1.14')
			$wgOut->addStyle($cssURL . $css, 'screen'); //clean method
		else
			$wgOut->addHTML($css_data); //ugly method

		$this->html_template = $data_start . $html_data . $data_end;
	
		$this->daysNormalHTML   = $this->html_week_array("<!-- %s %s -->");
		$this->daysSelectedHTML = $this->html_week_array("<!-- Selected %s %s -->");
		$this->daysMissingHTML  = $this->html_week_array("<!-- Missing %s %s -->");
		
		$year = Common::translate('year');
		$month = Common::translate('month');
		$week = Common::translate('week');

		if(!$this->setting('disablemodes')){
			$selected = "selected='true'";
			$this->tag_views = "<select name='viewSelect' method='post' onChange='javascript:this.form.submit()'>";
			
			($this->mode == 'year') ?
				$this->tag_views .= "<option class='lst' value='year' $selected>$year</option>" :
				$this->tag_views .= "<option class='lst' value='year'>$year</option>";

			($this->mode == 'month') ?
				$this->tag_views .= "<option class='lst' value='month' $selected>$month</option>" :
				$this->tag_views .= "<option class='lst' value='month'>$month</option>";
			($this->mode == 'week') ?
				$this->tag_views .= "<option class='lst' value='week' $selected>$week</option>" :
				$this->tag_views .= "<option class='lst' value='week'>$week</option>";
	
			$this->tag_views .= "</select>&nbsp;&nbsp;";	
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

		$max = Common::getDaysInMonth($month, $year);
		
		if ($day <=0 || $day > $max)		
			return $this->daysMissingHTML[0];

		$thedate = getdate(mktime(12, 0, 0, $month, $day, $year));
		$wday  = $thedate['wday'];
		$weekday = Common::translate($wday+1, 'weekday');

		$display_day = $day;
		if($dateFormat == 'long'){
			$display_day = $weekday . ", " . Common::translate($month, 'month_short') . " $day";
		}
		
		if($dateFormat == 'none')
			$display_day = "";

		if ($thedate['mon'] == $this->month
			&& $thedate['year'] == $this->year
			&& $thedate['mday'] == $this->day ) {
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

		$articleName = $this->wikiRoot . wfUrlencode($this->calendarPageName) . "/" . $this->month . "-" . $this->year . " -Template&action=edit" . "'\">";
		
//		$value = strtolower(Common::translate($this->month,'month')) . " " . Common::translate('template_btn');
		$value = Common::translate('template_btn');
		$title = Common::translate('template_btn_tip');

		if($this->setting('locktemplates'))
			$ret = "<input class='btn' type='button' title='$title' disabled value=\"$value\" onClick=\"javascript:document.location='" . $articleName;
		else
			$ret = "<input class='btn' type='button' title='$title' value=\"$value\" onClick=\"javascript:document.location='" . $articleName;
		
		return $ret;			
	}

	function loadiCalLink(){
		$ical_value  = Common::translate('ical_btn');
		$ical_title = Common::translate('ical_btn_tip');
		$bws_title = Common::translate('ical_browse_tip');
		
		$note = "";
		$cookieName = str_replace(' ', '_', ($this->calendarPageName . "_ical_count"));
		if(isset($_COOKIE[$cookieName])){
			$cnt = $_COOKIE[$cookieName];
			$note = "<font color=red>Completed the import of <b>$cnt</b> record(s).</font>";
			setcookie($cookieName, "", time() -3600);
		}

		$ret = Common::translate('ical_inst') . "<br>"
			. "<input name='uploadedfile' type='file' title=\"$bws_title\" size='50'><br>"	
			. "<input name='ical' class='btn' type='submit' title=\"$ical_title\" value=\"$ical_value\">&nbsp;&nbsp;"
			. $note;
			
		return $ret;
	}
	
	// build the 'template' button	
	function buildConfigLink($bTextLink = false){	
		
		if(!$this->setting('useconfigpage')) return;
		
		if($this->setting('useconfigpage',false) == 'disablelinks') return "";
		
		$value = Common::translate('config_btn');
		$title = Common::translate('config_btn_tip');
		
		if(!$bTextLink){
			$articleConfig = $this->wikiRoot . wfUrlencode($this->configPageName) . "&action=edit" . "';\">";
			$ret = "<input class='btn' type='button' title='$title' value=\"$value\" onClick=\"javascript:document.location='" . $articleConfig;
		}else
			$ret = "<a href='" . $this->wikiRoot . wfUrlencode($this->configPageName) . "&action=edit'>($value...)</a>";

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
			
			for($i=0; $i < $daysOut; $i++){	
				$temp = $this->getHTMLForDay($month, $day, $year, 'long', 'events');
				if(strlen(trim($temp)) > 0 ){
					$events .= "<tr>" . $temp . "</tr>";
				}				
				Common::getNextValidDate($month,$day,$year);//bump the date up by 1
			}
		
			$this->debug->set("renderEventList Ended");
			
			$ret = "<i> " . $this->buildConfigLink(true) . "</i>" 
				. $events;

			return "<table width=100%>" . $ret . "</table>";	
		}
	}

	function buildTemplateEvents(){	

		$year = $this->year;
		$month = 1;//$this->month;
		$additionMonths = $this->month + 12;
		
		// lets just grab the next 12 months...this load only takes about .01 second per subscribed calendar
		for($i=0; $i < $additionMonths; $i++){ // loop thru 12 months
			foreach($this->subscribedPages as $page)
				$this->addTemplate($month, $year, $page);

			
			$this->addTemplate($month, $year, ($this->calendarPageName));		
			$year = ($month == 12 ? ++$year : $year);
			$month = ($month == 12 ? 1 : ++$month);
		}
	}
	
	// load ical RRULE (recurrence) events into memory
	function buildVCalEvents(){	
		$this->debug->set("buildVCalEvents started");
		
		$year = $this->year;
		$month = 1;//$this->month;
		$additionMonths = $this->month + 12;
		
		// lets just grab the next 12 months...this load only takes about .01 second per subscribed calendar
		for($i=0; $i < $additionMonths; $i++){ // loop thru 12 months
			foreach($this->subscribedPages as $page){
				$this->addVCalEvents($page, $year, $month);
			}
			
			$this->addVCalEvents($this->calendarPageName, $year, $month);
			$year = ($month == 12 ? ++$year : $year);
			$month = ($month == 12 ? 1 : ++$month);
		}
	}
	
	// used for 'date' mode only...technically, this can be any date
	function updateDate(){
		
		$this->calendarMode = "date";
		
		$setting = $this->setting("date",false);
		
		if($setting == "") return "";
		
		$this->arrSettings['charlimit'] = 100;
		
		if (($setting == "today") || ($setting == "tomorrow") || ($setting == "yesterday") ){
			if ($setting == "tomorrow" ){	
				Common::getNextValidDate($this->month, $this->day, $this->year);		
			}

			if ($setting == "yesterday" ){	
				$yesterday= Common::datemath(-1, $this->month, $this->day, $this->year);
				$this->month = $yesterday['mon'];
				$this->day = $yesterday['mday'];
				$this->year = $yesterday['year'];
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
		$this->debug->set("** updateDate Ended");	
	}
	
	// specific date mode
	function renderDate(){
		
		$this->initalizeMonth(0,1);
		
		$ret = $this->buildConfigLink(true)
			. $this->getHTMLForDay($this->month, $this->day, $this->year, 'long', 'day');
			
		$this->debug->set("renderDate Ended");		
		return "<table>$ret</table>";
	}

	function renderSimpleMonth(){
		 
		$ret = $this->buildSimpleCalendar($this->month, $this->year);
		 
		 return $ret;
	}
	
    function renderMonth() {   
		global $gCalendarVersion;
			
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

	    //$today = getdate();    	// today's date
	    $isSelected = false;    	// if the day being processed is today
	    $isMissing = false;    	// if the calendar cell being processed is in the current month
		
	    /***** Build the known tag elements (non-dynamic) *****/
	    // set the month's name tag
		if($this->name == 'Public')
			$tag_calendarName = Common::translate('default_title');
		else
			$tag_calendarName = $this->name;
			
		$tag_about = "<a title='Click here is learn more and get help' href='http://www.mediawiki.org/wiki/Extension:Calendar_(Kenyu73)' target='new'>about</a>...";
		
	    // set the month's mont and year tags
		$tag_calendarMonth = Common::translate($this->month, 'month');
	    $tag_calendarYear = $this->year;
    	
	    // build the month select box
	    $tag_monthSelect = "<select name='monthSelect' method='post' onChange='javascript:this.form.submit()'>";

		for ($i = 1; $i <= 12; $i++) {
    		if ($i == $this->month) {
				$tag_monthSelect .= "<option class='lst' value='" . ($i) . "' selected='true'>" . 
				Common::translate($i, 'month') . "</option>\n";
    		}
    		else {
				$tag_monthSelect .= "<option class='lst' value='" . ($i) . "'>" . 
				Common::translate($i, 'month') . "</option>\n";
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

		$style_value = Common::translate('styles_btn');
		$style_tip = Common::translate('styles_btn_tip');;
		
		if(!$this->setting("disablestyles")){
			$articleStyle = $this->wikiRoot . wfUrlencode($this->calendarPageName) . "/style&action=edit" . "';\">";
			$tag_eventStyleButton = "<input class='btn' type=\"button\" title=\"$style_tip\" value=\"$style_value\" onClick=\"javascript:document.location='" . $articleStyle;
		}
	
		// build the 'today' button	
		$btnToday = Common::translate('today');
	    $tag_todayButton = "<input class='btn' name='today' type='submit' value=\"$btnToday\">";
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
	    $html_week_start = "<tr>";
	    $html_week_end = "</tr>";
 
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
		$ret = str_replace("[[Views]]", $this->tag_views, $ret);
		
		if(!$this->setting('monday')){
			$ret = str_replace("[[day1]]", Common::translate(1,'weekday'), $ret);
			$ret = str_replace("[[day2]]", Common::translate(2,'weekday'), $ret);
			$ret = str_replace("[[day3]]", Common::translate(3,'weekday'), $ret);
			$ret = str_replace("[[day4]]", Common::translate(4,'weekday'), $ret);
			$ret = str_replace("[[day5]]", Common::translate(5,'weekday'), $ret);
			$ret = str_replace("[[day6]]", Common::translate(6,'weekday'), $ret);
			$ret = str_replace("[[day7]]", Common::translate(7,'weekday'), $ret);
		}
		else{
			$ret = str_replace("[[day1]]", Common::translate(2,'weekday'), $ret);
			$ret = str_replace("[[day2]]", Common::translate(3,'weekday'), $ret);
			$ret = str_replace("[[day3]]", Common::translate(4,'weekday'), $ret);
			$ret = str_replace("[[day4]]", Common::translate(5,'weekday'), $ret);
			$ret = str_replace("[[day5]]", Common::translate(6,'weekday'), $ret);
			$ret = str_replace("[[day6]]", Common::translate(7,'weekday'), $ret);
			$ret = str_replace("[[day7]]", Common::translate(1,'weekday'), $ret);		
		}
		
		
	    /***** Begin building the calendar days *****/
	    // determine the starting day offset for the month
		//$wday_info = Common::wdayOffset($this->month,$this->year,$first);
		//$dayOffset = $wday_info['offset'] +1;
	    
		$start=1;
		if($this->setting('monday')) $start=2;
		$dayOffset = -$first + $start;
		
	    $maxDays = Common::getDaysInMonth($this->month,$this->year);

	    // determine the number of weeks in the month
		//$numWeeks = $wday_info['weeks'] +1;
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
		
	    /***** Do footer *****/
	    $tempString = $html_footer;
		
		//if($this->setting('ical'))
			//$tag_loadiCalButton = $this->loadiCalLink();
			
		// replace potential variables in footer
		$tempString = str_replace("[[TodayData]]", $this->tag_HiddenData, $tempString);
		$tempString = str_replace("[[TemplateButton]]", $tag_templateButton, $tempString);
		$tempString = str_replace("[[EventStyleBtn]]", $tag_eventStyleButton, $tempString);
		$tempString = str_replace("[[Version]]", $gCalendarVersion, $tempString);
		$tempString = str_replace("[[ConfigurationButton]]", $tag_configButton, $tempString);
		$tempString = str_replace("[[TimeTrackValues]]", $tag_timeTrackValues, $tempString);
		//$tempString = str_replace("[[Load_iCal]]", $tag_loadiCalButton, $tempString);
		$tempString = str_replace("[[About]]", $tag_about, $tempString);
		
	    $ret .= $tempString;
  		
	    /***** Do calendar end code *****/
	    $ret .= $html_calendar_end;
 			
		$ret = $this->stripLeadingSpace($ret);
		
		$this->debug->set("renderMonth Ended");		
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
	// uses prefix seaching (NS:page/name/date)... anything after doesn't matter
    function buildArticlesForDay($month, $day, $year) {
	
		$date = "$month-$day-$year";

		$search = "$this->calendarPageName/$date";
		$pages = PrefixSearch::titleSearch( $search, '100');
		foreach($pages as $page) {
			$this->addArticle($month, $day, $year, $page);
		}
		unset ($pages);
		
		// subscribed events
		foreach($this->subscribedPages as $subscribedPage){
			$search = "$subscribedPage/$date";
			$pages = PrefixSearch::titleSearch( $search, '100' );
			foreach($pages as $page)
				$this->addArticle($month, $day, $year, $page);			
		}
	
		// depreciated (around 1/1/2009)
		// old format: ** name (12-15-2008) - Event 1 **
		if($this->setting('enablelegacy')){
			$name = $this->setting('name');
			$search = "$this->namespace:$name ($date)";
			$pages = PrefixSearch::titleSearch( $search, '100');
			
			foreach($pages as $page) {
				$this->addArticle($month, $day, $year, $page);
			}
			unset ($pages);
		}
	}

	function buildTagEvents($paramstring){
	
		$events = split( "\n", trim($paramstring) );
	
		foreach($events as $event) {
			$arr = split(':', $event);
			$date = array_shift($arr);
			$event = array_shift($arr);
			
			$body = implode(':',$arr);
			
			$arrDate = split('-',$date);
			
			// we must have a valid date to continue
			if(count($arrDate) < 3 ) 
				break;

			$month = $arrDate[0];
			$day = $arrDate[1];
			$year = $arrDate[2];

			$this->buildEvent($month, $day, $year, $event, $this->title);	
		}
	}
	
	function buildSimpleCalendar($month, $year, $sixRow=false){

		$row = $todayStyle = "";

		$firstDate = getdate(mktime(12, 0, 0, $month, 1, $year));
	    $first = $firstDate["wday"];   // the day of the week of the 1st of the month (ie: Sun:0, Mon:1, etc)

		$start=1;
		if($this->setting('monday')) $start=2;
		$dayOffset = -$first + $start;
    
	    // determine the number of weeks in the month
		$maxDays = Common::getDaysInMonth($month,$year);
	    $numWeeks = floor(($maxDays - $dayOffset + 7) / 7);  	

		if($sixRow) $numWeeks = 6;

		$monthname = Common::translate($month,'month');
		if ( $this->isFullSubscribe ) {
			$monthname = "<a title='$this->calendarPageName' href='" . $this->wikiRoot . substr($this->calendarPageName, 0, strrpos($this->calendarPageName, "/")) . "'>" . $monthname . "</a>";
		}
		//$monthname = "<a href=''>$monthname";

		$ret = "<tr><td class='yearTitle' colspan=7>" . $monthname . "</td></tr>";				
		if($this->setting('monday')){
			$ret .= "
				<tr>
					<td class='yearHeading'>" . substr(Common::translate(2,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(3,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(4,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(5,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(6,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(7,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(1,'weekday'),0,1) . "</td>
				</tr>";
		}
		else{
			$ret .= "
				<tr>
					<td class='yearHeading'>" . substr(Common::translate(1,'weekday'),0,1) . "</td>						
					<td class='yearHeading'>" . substr(Common::translate(2,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(3,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(4,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(5,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(6,'weekday'),0,1) . "</td>
					<td class='yearHeading'>" . substr(Common::translate(7,'weekday'),0,1) . "</td>
			
				</tr>";		
		}
		
		for ($i = 0; $i < $numWeeks; $i++) {	
			for($j=0; $j < 7; $j++){
				if($year == $this->year && $month == $this->month && $dayOffset == $this->day)
					$todayStyle = "style='background-color: #C0C0C0;font-weight:bold;'";
					
				if($dayOffset > 0 && $dayOffset <= $maxDays){
					$link = $this->buildAddEventLink($month, $dayOffset, $year, $dayOffset);
					if( (($j==0 || $j==6) && !$this->setting('monday')) || 
					($j > 4 && $this->setting('monday')) )
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
		
		$ret = "<tr><td>" . $this->buildConfigLink(true) . "</td><td $styleTitle colspan=2>$title</td><td align=right>$this->tag_views</td></tr>";

		for($m=0;$m <12; $m++){
			$cal .= "<td style='text-align:center; vertical-align:top;'>" . $this->buildSimpleCalendar($nextMon++, $nextYear, true) . "</td>";
			
			if($m==3 || $m==7 || $m==11){
				$ret .= "<tr>$cal</tr>";
				$cal = "";
			}	
		}	
		
		return $html_head . $ret . $this->tag_HiddenData . $html_foot ;
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
		
		if($this->setting('monday')) $weekday--;
		$date = Common::datemath(-($weekday), $this->month, $this->day, $this->year);

		$month = $date['mon'];
		$day = $date['mday'];
		$year = $date['year'];
		
		//$title = $date['month'];
		$title = Common::translate($month, 'month');
		
		if(!$fiveDay){
			$sunday = "<td class='calendarHeading'>" . Common::translate(1, 'weekday'). "</td>";
			$saturday = "<td class='calendarHeading'>" . Common::translate(7, 'weekday'). "</td>";
			$colspan = 5; //adjuct for mode buttons
		}
		
		//hide mode buttons if selected via parameter tag
		$ret .= "<tr><td $styleTitle>$title</td>" . "<td><i>". $this->buildConfigLink(true) . "</i></td>"
			. "<td align=right colspan=$colspan>$this->tag_views</td></tr>";	
		
		if($this->setting('monday')){
			$ret .= "<tr>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(2, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(3, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(4, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(5, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(6, 'weekday'). "</td>";
			$ret .= $saturday;
			$ret .= $sunday;
			$ret .= "</tr>";
		}
		else{
			$ret .= "<tr>";
			$ret .= $sunday;
			$ret .= "<td class='calendarHeading'>" . Common::translate(2, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(3, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(4, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(5, 'weekday'). "</td>";
			$ret .= "<td class='calendarHeading'>" . Common::translate(6, 'weekday'). "</td>";
			$ret .= $saturday;
			$ret .= "</tr>";
		}
		
		if($fiveDay && !$this->setting('monday')) 
			Common::getNextValidDate($month, $day, $year);
		
		for($i=0; $i<7; $i++){
			if($fiveDay && $i==0) $i=2;
			$week .= $this->getHTMLForDay($month, $day, $year, 'short', 'week');
			Common::getNextValidDate($month, $day, $year);
		}

		$ret .= "<tr>" . $week . "</tr>";
		
		$this->debug->set("renderWeek Ended");	
		return $html_head . $ret . $this->tag_HiddenData . $html_foot;
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
	function load_iCal($ical_data){
		$this->debug->set('load_iCal Started');
		
		$bMulti = false;
		$iCal = new ical_calendar;

		$bExpired = false;
		$bOverwrite = false;
		$description = "";
		$summary = "";
		
		//make sure we're good before we go further
		if(!$iCal->setFile($ical_data)) return;
		
		$arr = $iCal->getData();
	
		if($this->setting('ical', false) == 'overwrite')
			$bOverwrite = true;
	
		set_time_limit(120); //increase the script timeout for this load to 2min	
		$cnt = 0;
		foreach($arr as $event){
			$bExpired = false; //reset per loop
			
			if(isset($event['DTSTART'])){
				$start = $event['DTSTART'];
				
				if(isset($event['SUMMARY'])) 
					$summary = $event['SUMMARY'];
					
				if(isset($event['DESCRIPTION'])) 
					$description = $event['DESCRIPTION'];	
					
				if(!isset($event['DTEND'])) 
					$event['DTEND'] = $event['DTSTART'];
				
				$date_string = $start['mon']."-".$start['mday']."-".$start['year'];	
				$page = $this->getNextAvailableArticle($this->calendarPageName, $date_string, true);

				$date_diff = ceil(Common::day_diff($event['DTSTART'], $event['DTEND']));
				if($date_diff > 1)
					$summary = $date_diff . "#" . $summary; //multiple day events
				
				// add events
				if(!isset($event['RRULE'])){
					$this->createNewMultiPage($page, $summary, $description, "iCal Import");
					$cnt++;
				}
				else{
					$recurrence_page = "$this->calendarPageName/recurrence";
					
					//clean up the RRULE some to fit this calendars need...
					$byday = $bymonth = "";	
					if(stripos($event['RRULE'], "BYDAY") === false) $byday = ";DAY=" . $start['mday'];	
					if(stripos($event['RRULE'], "BYMONTH") === false) $bymonth = ";BYMONTH=" . $start['mon'];				
	
					$rrule = "RRULE:" . $event['RRULE']
						. $bymonth
						. $byday 
						. ";SUMMARY=" . $summary;

					$rules = $this->convertRRULEs($rrule);

					if(isset($rules[0]['UNTIL'])){
						$bExpired = $this->checkExpiredRRULE($rules[0]['UNTIL']);
					}
						
					if(!$bExpired){
						//add recurrences
						$cnt += $this->updateRecurrence($recurrence_page, $rrule, $summary, "iCal Import", $bOverwrite);
						$bOverwrite = false; //just need to hit the overwrite one time to delete the page...
					}
					
					unset($rules);
				}
			}
		}
		
		set_time_limit(30);
		
		$cookieName = str_replace(' ', '_', ($this->calendarPageName . "_ical_count"));
		setcookie($cookieName,$cnt);
				
		$this->debug->set('load_iCal Ended');
	}
	
	// get the extension short 'URL' path ex:( /mediawiki/extensions/calendar/ )
	// ... there has to be a better way then this!
	function getURLRelativePath(){
		global $wgScriptPath,$wgCalendarURLPath;
		
		//$path = str_ireplace('calendar.php', '', __FILE__);
		//$path = str_replace('\\', '/', $path);
		//$url = stristr($path, 'extensions');

		if($wgCalendarURLPath){
			return $wgCalendarURLPath;
		}else{
			return $wgScriptPath . "/extensions/Calendar";
		}
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
function displayCalendar($paramstring, $params = array()) {
    global $wgParser;
	global $wgScript, $wgScriptPath;
	global $wgTitle, $wgUser;
	global $wgRestrictCalendarTo, $wgCalendarDisableRedirects, $wgCalendarForceNamespace;

    $wgParser->disableCache();
	$wikiRoot = $wgScript . "?title=";
	$userMode = 'month';
	
	// grab the page title
	$title = $wgTitle->getPrefixedText();	
	
	$config_page = " ";

	$calendar = null;	
	$calendar = new Calendar($wikiRoot, isset($params["debug"]));

	//return $calendar->getURLRelativePath();
	
	$calendar->namespace = $wgTitle->getNsText();
	
	if(!isset($params["name"])) $params["name"] = "Public";
	
	$calendar->paramstring = $paramstring;
	
	// set path		
	$params['path'] = str_replace("\\", "/", dirname(__FILE__));
		
	$name = Common::checkForMagicWord($params["name"]);
		
	// normal calendar...
	$calendar->calendarPageName = "$title/$name";
	$calendar->configPageName = "$title/$name/config";
	
	// disabling for now... causing wierd errors with mutiple calendars per page
	//(UNIQ249aadf6593f3f85-calendar-00000000-QINU}
	//$calendar->createNewPage("$title/$name/config", buildConfigString());	
	
	if(isset($params["useconfigpage"])) {	
		$configs = $calendar->getConfig("$title/$name");
		
		//merge the config page and the calendar tag params; tag params overwrite config file
		$params = array_merge($configs, $params);	
	}
	
	// just in case i rename some preferences... we can make them backwards compatible here...
	legacyAliasChecks($params);
	
	// if the calendar isn't in a namespace specificed in $wgCalendarForceNamespace, return a warning
	if( ($wgCalendarForceNamespace != $calendar->namespace) 
				&& isset($wgCalendarForceNamespace) && !isset($params["fullsubscribe"]) ){
		
		return Common::translate('invalid_namespace') . '<b>'.$wgCalendarForceNamespace.'</b>';
	}
	
	//set defaults that are required later in the code...
	if(!isset($params["timetrackhead"])) 	$params["timetrackhead"] = "Event, Value";
	if(!isset($params["maxdailyevents"])) 	$params["maxdailyevents"] = 5;
	if(!isset($params["yearoffset"])) 		$params["yearoffset"] = 2;
	if(!isset($params["charlimit"])) 		$params["charlimit"] = 25;
	if(!isset($params["css"])) 				$params["css"] = "default.css";

	//set secure mode via $wgRestrictCalendarTo global
	// this global is set via LocalSetting.php (ex: $wgRestrictCalendarTo = 'sysop';
	if( isset($wgRestrictCalendarTo) ){
		$arrGroups = $wgUser->getGroups();
		if( !in_array($wgRestrictCalendarTo, $arrGroups) ){
			$calendar->debug->set($arrGroups[2]);
			$params["lockdown"] = true;
		}	
	}

	if (isset($wgCalendarDisableRedirects))
		$params['disableredirects'] = true;
	
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
	if( isset($params["fullsubscribe"]) ) {
		if($params["fullsubscribe"] != "fullsubscribe") {
			$arrString = explode('/', $params["fullsubscribe"]);
			array_pop($arrString);
			$string = implode('/', $arrString);
			$article = new Article(Title::newFromText( $string ));
			
			// if the fullsubscribe calendar doesn't exisit, return a warning...
			if(!$article->exists()) return "Invalid 'fullsubscribe' calendar page: <b><i>$string</i></b>";
			
			$calendar->calendarPageName = htmlspecialchars($params["fullsubscribe"]);
			$calendar->isFullSubscribe = true;
		}
	}
	
	// finished special conditions; set the $title and $name in the class
	$calendar->setTitle($title);
	$calendar->setName($name);

	$cookie_name = str_replace(' ', '_', ($title . "_" . $name));
	if(isset($_COOKIE[$cookie_name])){
		$calendar->debug->set('cookie loaded');

		$arrSession = split("`", $_COOKIE[$cookie_name]);
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
	}
	
	if(isset($params['useeventlist'])) $userMode = 'events';
	if(isset($params['date'])) $userMode = 'day';
	if(isset($params['simplemonth'])) $userMode = 'simplemonth';
	
	if(isset($_COOKIE['calendar_ical'])){
		$calendar->debug->set('ical cookie loaded');		

		$calendar->load_iCal($_COOKIE['calendar_ical']);
		
		//delete ical file in "mediawiki/images" folder	
		@unlink($_COOKIE['calendar_ical']); 
		
		// delete the ical path cookie
		setcookie('calendar_ical', "", time() -3600);

		// refresh the calendar's newly added events
		$calendar->invalidateCache=true;
	}
	
	$render = $calendar->renderCalendar($userMode);
	
	// purge main calendar before displaying the calendar
	if($calendar->invalidateCache){
		$article = new Article(Title::newFromText($title));
		$article->purge();
		header("Location: " . $wikiRoot . $title);
	}

	return $render;
}

// alias ugly/bad preferences to newer, hopefully better names
function legacyAliasChecks(&$params) {
	if( isset($params['usemultievent']) ) $params['usesectionevents'] = 'usesectionevents';
}

function wfCalendarFunctions_Magic( &$magicWords, $langCode ) {
    switch ( $langCode ) {
         default:
              $magicWords['calendar']         = array( 0, 'calendar' );
    }
    return true;
}
} //end define MEDIAWIKI



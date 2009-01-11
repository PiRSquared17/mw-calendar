<?php
/*
Class: 		CalendarArticle
Purpose: 	Stucture to hold article/event data and 
			then store into an array for future retrieval

*/
class CalendarArticle
{
	var $day = "";
	var $month = "";
	var $year = "";
	
	var $page = ""; //full wiki page name
	var $eventname = ""; //1st line of body
	var $body = "";
	var $htmllink = "";
	var $plaintext = "";
	var $style = "";
	
	function CalendarArticle($month, $day, $year){
		$this->month = $month;
		$this->day = $day;
		$this->year = $year;	
	}
}

/*
Class: 		CalendarArticles
Purpose: 	Contains most of the functions to retrieve article 
			information. It also is the primary container for
			the main array of class::CalendarArticle articles

*/
class CalendarArticles
{	
	private $arrArticles = array();
	public $wikiRoot = "";
	private $arrTimeTrack = array();
	private $arrStyle = array();
	
	public function addArticle($month, $day, $year, $page, $charlimit){
		$lines = array();
		$temp = "";		
		$head = array();

		$article = new Article(Title::newFromText($page));
		if(!$article->exists()) return "";

		$redirectCount = 0;
		 while($article->isRedirect() && $redirectCount < 10){
			 $redirectedArticleTitle = Title::newFromRedirect($article->getContent());
			 $article = new Article($redirectedArticleTitle);
			 $redirectCount += 1;
		 }

		$body = $article->fetchContent(0,false,false);
	
		if(strlen(trim($body)) == 0) return "";
		
		$lines = split("\n",$body);
		$cntLines = count($lines);
	
		for($i=0; $i<$cntLines; $i++){
			$line = $lines[$i];
			if(substr($line,0,2) == '=='){
				$arr = split("==",$line);
				$key = $arr[1];
				$head[$key] = ""; $temp = "";
			}
			else{
				if($i == 0){ // $i=0  means this is a one event page no (==event==) data
					$key = $line; //initalize the key
					$head[$key] = ""; 
				}
				else{
					$temp .= "$line\n";
					$head[$key] = $this->cleanWiki($temp);
				}
			}
		}
		
		while (list($event,$body) = each($head)){
			$this->buildEvent($month, $day, $year, $event, $page, $this->LimitText($body, $charlimit));
		}
	}
	
	private function buildEvent($month, $day, $year, $event, $page, $body, $isTemplate=false){	
	
		if(!$this->setting('enablerepeatevents')){
			$this->add($month, $day, $year, $event, $page, $body, $isTemplate);	
			return;
		}
		
		//check for repeating events
		$arrEvent = split("#",$event);
		if(isset($arrEvent[1]) && ($arrEvent[0] != 0)){
			$this->add($month, $day++, $year, $arrEvent[1], $page, $body); //add no arrow
			for($i=1; $i<$arrEvent[0]; $i++) {
				$this->add($month, $day, $year, '&larr;'.$arrEvent[1], $page, $body, $isTemplate); //add with arrow
				$this->getNextValidDate($month, $day, $year);
			}
		}else
			$this->add($month, $day, $year, $event, $page, $body, $isTemplate);	
	}
	
	function LimitText($text,$max) { 
		if($max == "") return;
		
		$text = trim($text);
		
		if(strlen($text) > $max)
			$ret = substr($text, 0, $max) . "...";
		else
			$ret = $text;
	
		return $ret;
	} 

	public function getArticleLinks($month, $day, $year){
		$cnt = count($this->arrArticles);
		$ret = "";

		for($i=0; $i<$cnt; $i++){
			$cArticle = $this->arrArticles[$i];
			if($cArticle->month == $month && $cArticle->day == $day && $cArticle->year == $year){
			//$this->debug($cArticle->eventname);
				$ret .= "<li>" . $this->articleLink($cArticle->page, $cArticle->eventname). "</li>\n$cArticle->body";
			}
		}
		
		return $ret;
	}
	
	// when the calendar loads, we want to put all the template events into memory
	// so we dont have to read the wiki db for every day
	public function addTemplate($month, $year, $pagename){
		$displayText = "";
		$arrEvent = array();
	
		$articleName = $pagename . "/" . $month . "-" . $year . " -Template";
		$article = new Article(Title::newFromText($articleName));

		if (!$article->exists()) return "";
		
		$displayText  = $article->fetchContent(0,false,false);
	
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
							$this->buildEvent($month, $day, $year,  $arrEvent[1], $articleName, "", true);
							$day++;
						}
					}else{
						$this->buildEvent($month, $day, $year, $arrEvent[1], $articleName, "", true);
					}
				}
			}
		}	
	}

	private function add($month, $day, $year, $eventname, $page, $body, $isTemplate=false){
		$cArticle = new CalendarArticle($month, $day, $year);

		$temp = $this->checkTimeTrack($month, $day, $year, $eventname, $isTemplate);
		
		$cArticle->month = $month;	
		$cArticle->day = $day;	
		$cArticle->year = $year;	
		$cArticle->page = $page;	
		$cArticle->eventname = $temp."<br/>";
		if(trim($body) != "")
			$cArticle->body = $body;

		$this->arrArticles[] = $cArticle;
	}
	
	// this function checks a template event for a time trackable value
	private function checkTimeTrack($month, $day, $year, $event, $isTemplate){
	
		if((stripos($event,"::") === false) || $this->setting('disabletimetrack'))
			return $event;
		
		$arrEvent = split("::", $event);
		
		$arrType = split(":",$arrEvent[1]);
		if(count($arrType) == 1)
			$arrType = split("-",$arrEvent[1]);
			
		$type = trim(strtolower($arrType[0]));

		// we only want the displayed calendar year totals
		if($this->year == $year){
			if($isTemplate)
				$this->arrTimeTrack[$type.' (y)'][] = $arrType[1];
			else
				$this->arrTimeTrack[$type.' (m)'][] = $arrType[1];
		}
		
		//piece together any prefixes that the code may have added - like (r) for repeat events
		$ret = $arrEvent[0] . $arrType[0]; 
		
		return $ret;
	}
	
	public function buildTrackTimeSummary(){
	
		if($this->setting('disabletimetrack')) return "";
	
		$ret = "";
		$cntValue = count($this->arrTimeTrack);
		$cntHead = split(",", $this->setting('timetrackhead',false));
		$linktitle = "Time summaries of time specific enties. Prefix events with :: to track time values.";
		
		$html_head = "<table title='$linktitle' width=15% border=1 cellpadding=0 cellspacing=0><th>$cntHead[0]</th><th>$cntHead[1]</th>";
		$html_foot = "</table><small>"
			. "(m) - total month only; doesn't add to year total <br/>"
			. "(y) - total year; must use monthly templates<br/></small>";

		if(count($this->arrTimeTrack) > 0){
			while (list($key,$val) = each($this->arrTimeTrack)) {
				$ret .= "<tr><td align='center'>$key</td><td align='center'>" . array_sum($this->arrTimeTrack[$key]) . "</td></tr>";
			}
			
			$ret = $html_head . $ret . $html_foot;
		}
	
		return $ret;
	}
	
	//find the number of current events and "build" the <add event> link
    public function buildAddEventLink($month, $day, $year) {
		
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
		if($this->setting('usemultievent') && $articleCount > 1) $articleCount -= 1;
		
		if($articleCount > $this->setting('maxdailyevents',false))
			$newURL = "<a title='add a new event' href=\"javascript:alert('Max daily events reached. Please use \'Multiple Events\' fomatting to add more.')\"><u>Add Event</u></a>";
		else
			$newURL = "<a title='add a new event' href='" . $this->wikiRoot . urlencode($tempArticle . $articleCount) . "&action=edit'><u>Add Event</u></a>";

		return $newURL;
	}
	
	function readStylepage(){
		$articleName = $this->calendarPageName . "/" . "style";	
		$article = new Article(Title::newFromText($articleName));

		if ($article->exists()){
			$displayText  = $article->fetchContent(0,false,false);	
			$this->arrStyle = split(chr(10), $displayText);
		}
	}	
	
	public function getConfig($pagename){
	
		$params = array();	
		
		$articleName = "$pagename/config";
		$article = new Article(Title::newFromText($articleName));
		
		if ($article->exists()){
			$body  = $article->fetchContent(0,false,false);
			$body = str_replace("\"", "", $body);	
			$arr = split("\n", $body);
			$cnt = count($arr);

			for($i=0; $i<$cnt; $i++){
				$arrParams = split("=", $arr[$i]);
				$key = trim($arrParams[0]);
				
				if($key != 'useconfigpage'){		// we dont want users to lock themselves out of the config page....		
					if(count($arrParams) == 2) 
						$params[$key] = trim($arrParams[1]); // we have both $key and $value
					else
						$params[$key] = $key; // init the value with itself if $value is null
				}
			}
		}
		return $params;
	}
	
    // returns the link for an article, along with summary in the title tag, given a name
    private function articleLink($title, $text){
			
		if(strlen($text)==0) return "";

		$arrText = $this->buildTextAndHTMLString($text);
		$style = $arrText[2];

		//locked links
		if($this->setting('disablelinks'))
			$ret = "<a $style>" . $arrText[1] . "</a>";
		else
			if($this->setting('defaultedit'))
				$ret = "<a $style title='$arrText[0]' href='" . $this->wikiRoot  . htmlspecialchars($title) . "&action=edit'>$arrText[1]</a>";
			else
				$ret = "<a $style title='$arrText[0]' href='" . $this->wikiRoot . htmlspecialchars($title)  . "'>$arrText[1]</a>";

		return $ret;
    }
	
	private function buildTextAndHTMLString($string){

		$string = $this->cleanWiki($string);	
		$htmltext = $string;
		$plaintext = strip_tags($string);
		$charlimit = $this->setting('charlimit',false);
		
		if(strlen($plaintext) > $charlimit) {
			$temp = substr($plaintext,0,$charlimit) . "..."; //plaintext
			$ret[0] = $plaintext; //full plain text
			$ret[1] = str_replace($plaintext, $temp, $htmltext); //html
			$ret[2] = ""; //styles
		}
		else{
			$ret[0] = $plaintext; //full plain text
			$ret[1] = $htmltext;	
			$ret[2] = ""; //styles
		}
		
		if(!$this->setting('disablestyles'))
			$ret[2] = $this->buildStyleBySearch($plaintext);
		
		return $ret;
	}	
	
	private function cleanWiki($text){

		$text = $this->swapWikiToHTML($text, "'''", "b");
		$text = $this->swapWikiToHTML($text, "''", "i");
		$text = $this->swapWikiToHTML($text, "<pre>", "");
		$text = $this->swapWikiToHTML($text, "</pre>", "");
	
		return $text;
	}
	
	//basic tage changer for common wiki tags
	private function swapWikiToHTML($text, $tagWiki, $tagHTML){

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
	
	private function buildStyleBySearch($text){

		$ret = "";
		for($i=0; $i < count($this->arrStyle); $i++){
			$arr = split("::", $this->arrStyle[$i]);
			$cnt = count($arr);
			
			if(stripos($text, $arr[0]) !== false)
				$ret = "style='" . trim($arr[1]) . "' ";
		}

		return $ret;
	}
}
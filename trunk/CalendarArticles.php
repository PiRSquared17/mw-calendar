<?php

class CalendarArticle
{
	var $day = "";
	var $month = "";
	var $year = "";
	
	var $pagetitle = ""; //full wiki page name
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

class CalendarArticles
{	
	private $arrArticles = array();
	public $wikiRoot = "";
	
	public function addArticle($month, $day, $year, $articleName, $charLimit){
		$arrBody = array();
		$lines = array();
		$body = array();
		$bMulti = false;
		$headCount = 0;
		$bodyLines =0;
		$temp = "";		
		$cntWords=0;
		
		$article = new Article(Title::newFromText($articleName));	
		if(!$article->exists()) return "";
		
		$body = $article->fetchContent(0,false,false);
		$page = $article->getTitle()->getPrefixedText();
		$cArticle->pagetitle = $article->getTitle()->getPrefixedText(); //full title with namespace,title,name and date
		
		if(strlen(trim($body)) == 0) return "";
		
		$lines = split("\n",$body);
		$cntLines = count($lines);
	
		for($i=0; $i<$cntLines; $i++){
			$line = $lines[$i];
			if(substr($line,0,2) == '=='){
				if($i > 0) $headCount++;
				$arr = split("==",$line);
				$head[$headCount] = $arr[1] . "<br/>";
				$temp = "";	$bodyLines = 0; $bMulti = true;	
			}
			else{
				if( $i != 0 && ($bodyLines < $charLimit)){
					$bodyLines++;
					$temp .= "$line\n";
					$arrBody[$headCount] = $this->cleanWiki($temp);
				}
			}	
		}

		if(!$bMulti)
			$this->add($month, $day, $year, $page, $lines[0] . "<br/>", $this->LimitText($arrBody[0], $charLimit));
		else
			for($i=0; $i<=$headCount; $i++)
				$this->add($month, $day, $year, $page, $head[$i], $this->LimitText($arrBody[$i], $charLimit));

	}
	
	function LimitText($text,$max) { 

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
			if($cArticle->month == $month && $cArticle->day == $day && $cArticle->year == $year)
				$ret .= "<li>" . $this->articleLink($cArticle->pagetitle, $cArticle->eventname). "</li>\n$cArticle->body";
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
							$arrEvent[1] = $this->checkTimeTrack($month, $day, $year, $arrEvent[1]);
							$this->add($month, $day, $year, $articleName, $arrEvent[1], "");
							$day++;
						}
					}else{
						$arrEvent[1] = $this->checkTimeTrack($month, $day, $year, $arrEvent[1]);
						$this->add($month, $day, $year, $articleName, $arrEvent[1], "");
					}
				}
			}
		}	
	}
	
	// this function checks a template event for a time trackable value
	private function checkTimeTrack($month, $day, $year, $event){
	
		if(stripos($event,"::") === false) return $event;
		
		$arrEvent = split("::", $event);
		
		$arrType = split(":",$arrEvent[1]);
		if(count($arrType) == 1)
			$arrType = split(" ",$arrEvent[1]); //because of this, the tracked event cannot be more then 1 word
			
		$type = trim(strtolower($arrType[0]));

		// we only want the displayed calendar year totals
		if($this->year == $year)
				$this->arrTimeTrack[$type][] = $arrType[1];
		
		return $event;	
	}
	
	public function buildTrackTimeSummary(){
		$ret = "";
		$cntValue = count($this->arrTimeTrack);
		$cntHead = split(",", $this->setting('timetrackhead',false));
		
		$html_head = "<table title='Year summary of time specific enties (1-5# ::sick)' width=50% border=1 cellpadding=0 cellspacing=0><th>$cntHead[0]</th><th>$cntHead[1]</th>";
		$html_foot = "</table>";

		while (list($key,$val) = each($this->arrTimeTrack)) 
			$ret .= "<tr><td align='center'>$key</td><td align='center'>" . array_sum($this->arrTimeTrack[$key]) . "</td></tr>";
	
		if($cntValue > 0)
			$ret = $html_head . $ret . $html_foot;
			
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
				$key = $arrParams[0];
				$value = $arrParams[1];
				
				if($key != 'useconfigpage'){		// we dont want users to lock themselves out of the config page....		
					if(count($arrParams) != 2) 
						$params[$key] = $key; // init the value with itself if $value is null
					else
						$params[$key] = $value; // we have both $key and $value
				}
			}
		}
		return $params;
	}
	
	private function add($month, $day, $year, $pagetitle, $eventname, $body){
		$cArticle = new CalendarArticle($month, $day, $year);
		
		$cArticle->month = $month;	
		$cArticle->day = $day;	
		$cArticle->year = $year;	
		$cArticle->pagetitle = $pagetitle;	
		$cArticle->eventname = str_replace("::", "", $eventname);	
		if(trim($body) != "")
			$cArticle->body = $body;
		
		$this->arrArticles[] = $cArticle;
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
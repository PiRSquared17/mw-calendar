<?php

if(isset($_POST['config'])){

	$ret = "";
	while (list($key,$val) = each($_POST)) {
		$ret .= "$key=$val\n";
	}
	
	//$cookie_name = "Main_Page/Public/config";
	//$cookie_value = $ret;
	//setcookie($cookie_name, $cookie_value);
	//session_start();
	$_SESSION['config_data'] = $ret;
	//updateConfig($ret, $_POST['configpage']);
	
	header("Location: " . $_SERVER['REQUEST_URI']);
}

require_once ("debug.class.php");

class config{

	private $arrSettings = array();
	private $debug;
	private $calendarPage;
	private $html;
	
	function config() {
		$this->debug = new debugger('html');
		$this->debug->enabled(true);

		$page = $this->page = $_SESSION['calendar_page_name'];
		
		$this->configPage = $page . "/config";
		$this->configToolPage = $page . "/configTool";
	$this->debug->set($_SESSION['config_data']);
		//session_start();
		if(isset($_SESSION['config_data'])){
			$this->updateConfig($_SESSION['config_data']);
			unset($_SESSION['config_data']);
		}
		
/*
		$cookie_name = str_replace(' ', '_', $this->configPage);
		if(isset($_COOKIE[$cookie_name])){
			$this->debug->set('cookie loaded');
	
			$string = $_COOKIE[$cookie_name];
			$this->updateConfig($string);
			setcookie($cookie_name, "", time() -3600);	
		}
*/		
		$input = $this->getdata($this->configPage);
		$tempArr = split("\n", $input);
		foreach($tempArr as $line){
			if(trim($line) != ""){
				$config= split("=", $line);
				$key = $config[0];
				
				if(count($config) == 2)
					$this->arrSettings[$key] = $config[1];
				else
					$this->arrSettings[$key] = $key;
			
			}
		}
	}

	public function buildHTML(){
	
		$name = $this->page;
		$subscribe = $this->setting('subscribe', false);
		$fullsubscribe = $this->setting('fullsubscribe', false);
		$yearoffset = $this->setting('yearoffset');			
		
		$usetemplates = ($this->setting('usetemplates') ? 'checked':'');
		$disableaddevent = ($this->setting('disableaddevent') ? 'checked':'');	
		$defaultedit = ($this->setting('defaultedit') ? 'checked':'');	
		$locktemplates = ($this->setting('locktemplates') ? 'checked':'');	
		$disablelinks = 'checked';//($this->setting('disablelinks') ? 'checked':'');	
		$usemultievent = ($this->setting('usemultievent') ? 'checked':'');	
		$disablestyles = ($this->setting('disablestyles') ? 'checked':'');	
		$lockdown = ($this->setting('lockdown') ? 'checked':'');
		$disabletimetrack = ($this->setting('disabletimetrack') ? 'checked':'');
		$enablerepeatevents = ($this->setting('enablerepeatevents') ? 'checked':'');	
		$disablemodes = ($this->setting('disablemodes') ? 'checked':'');	
		$dayweek5 = ($this->setting('5dayweek') ? 'checked':'');	
		$week  = ($this->setting('week') ? 'checked':'');	
		$year  = ($this->setting('year') ? 'checked':'');
	
		$path = str_replace("\\", "/", dirname(__FILE__));
		$html = file_get_contents($path . "/templates/config_template.html");
		
		$html = str_replace('[[name]]', $name, $html);
		$html = str_replace('[[subscribe]]', $subscribe, $html);
		$html = str_replace('[[fullsubscribe]]', $fullsubscribe, $html);
		$html = str_replace('[[usetemplates]]', $usetemplates, $html);
		$html = str_replace('[[yearoffset]]', $yearoffset, $html);
		$html = str_replace('[[disableaddevent]]', $disableaddevent, $html);
		$html = str_replace('[[defaultedit]]', $defaultedit, $html);
		$html = str_replace('[[locktemplates]]', $locktemplates, $html);
		$html = str_replace('[[disablelinks]]', $disablelinks, $html);
		$html = str_replace('[[usemultievent]]', $usemultievent, $html);
		$html = str_replace('[[disablestyles]]', $disablestyles, $html);
		$html = str_replace('[[lockdown]]', $lockdown, $html);
		$html = str_replace('[[disabletimetrack]]', $disabletimetrack, $html);
		$html = str_replace('[[enablerepeatevents]]', $enablerepeatevents, $html);
		$html = str_replace('[[disablemodes]]', $disablemodes, $html);
		$html = str_replace('[[dayweek5]]', $dayweek5, $html);
		$html = str_replace('[[week]]', $week, $html);
		$html = str_replace('[[year]]', $year, $html);
		$html = str_replace('[[configpage]]', $this->configPage, $html);

		
		return $html;
	
	}
	public function getData($page){
		$ret = "";
	
		$article = new Article(Title::newFromText($page));
		$bExists = $article->exists();
	
		if($bExists)
			$ret = $article->fetchContent();
		
		return $ret;
	
	}
	
	public function createScreen(){
	
		return $this->buildHTML() . $this->debug->get();
	}
	
	//hopefully a catch all of most types of returns values
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
	
	function updateConfig($parameters){
		global $wgScript;
		$root = $wgScript . "?title=";
		
		$article = new Article(Title::newFromText($this->configPage));
		$bExists = $article->exists();

		if($bExists)
			$article->doEdit($parameters, "", EDIT_UPDATE);
		else
			$article->doEdit($parameters, "", EDIT_NEW);

		header("Location: " . $root . $this->configToolPage . "&action=purge");
	}
}

?>
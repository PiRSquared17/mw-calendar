<?php

class ical_calendar{

	var $data = "";

	function ical_calendar(){	
	}
	
	public function getData(){return $this->parse();}
	
	public function setFile($file){	
		$bOK = file_exists($file);
		if($bOK){
			$this->data = file_get_contents($file);
			$bOK = $this->validate();
		}
		
		return $bOK;
	}
	
	private function validate(){
	
		$lines = split("\n", $this->data);
		if(stripos($lines[0],"BEGIN:VCALENDAR") !== false)
			return true;
		
		return false;
	}
	
	private function parse(){
		$arrSection = array();
		$arrEvents = array();

		$sections = split("BEGIN:VEVENT", $this->data);
	
		for($sec=0; $sec<count($sections); $sec++){
			$lines = split("\n", $sections[$sec]);
			if(strpos($lines[0],"BEGIN:VCALENDAR") === false)
			for($i=0; $i< count($lines); $i++){
				$line = $lines[$i];
				$event = split(":",$line);
				
				if(substr($line,0,7) == 'DTSTART'){
					$arrSection['DTSTART'] = trim(substr($event[1], 0, 8));
				}
				if(substr($line,0,5) == 'DTEND'){
					$arrSection['DTEND'] = trim(substr($event[1], 0, 8));
				}
				if(substr($line,0,7) == 'SUMMARY'){
					$arrSection['SUMMARY'] = trim($event[1]);
				}
				if(substr($line,0,11) == 'DESCRIPTION'){
					$arrSection['DESCRIPTION'] = trim($event[1]);
				}
			}	
			$arrEvents[] = $arrSection;
		}
		
		return $arrEvents;
	}
	
    private function getSections($data, $string) {
	
    	$temp = split($string, $data);
    	if (count($temp) > 1) {
			$temp = split($string2, $temp[1]);
			return $temp[0];
    	}
    	return "";
    } 
}

?>
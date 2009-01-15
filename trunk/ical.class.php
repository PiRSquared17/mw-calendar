<?php

class ical_calendar{

	var $data = "";

	// get an array of vcal elements
	public function getData(){return $this->parse();}
	
	//set and validate the vcal file
	public function setFile($file){	
		$bOK = file_exists($file);
		if($bOK){
			$this->data = file_get_contents($file);
			$bOK = $this->validate();
		}
		
		return $bOK;
	}
	
		//set and validate the vcal file
	public function setData($data){	
		$bOK = false;
		$this->data = $data;
		$bOK = $this->validate();
		
		return $bOK;
	}
	
//******************************* PRIVATE FUNCTIONS ********************************************************************

	//verify if this is truely a vcal file
	private function validate(){
	
		$lines = split("\n", $this->data);
		if(stripos($lines[0],"BEGIN:VCALENDAR") !== false)
			return true;
		
		return false;
	}
	
	//takes a successfully loaded file and returns a parsed file
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
					$arrSection['DTSTART'] = $this->convertToPHPDate(trim(substr($event[1], 0, 8)));
				}
				if(substr($line,0,5) == 'DTEND'){
					$arrSection['DTEND'] = $this->convertToPHPDate(trim(substr($event[1], 0, 8)));
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
	
	private function convertToPHPDate($date){
		$arr['year'] = substr($date,0,4);
		$arr['mon'] = substr($date,4,2) +0;
		$arr['mday'] = substr($date,6,4) +0;
		
		return $arr;
	}
}

?>
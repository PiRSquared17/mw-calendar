function addEvent(name, date) {

	var event = prompt ("Event Name:","");

	if(event == null || event == "") {
		return document.location = "";
	}
	
	name += "/" + event + "/" + date + "&action=edit";

	document.location = name;
}

//maybe create a custom prompt here later...
function promptX() { 
	myWindow = window.open('apage.html','windowName','width=100,height=100');

/*	promptbox = document.createElement('div'); 
	promptbox.setAttribute ('id' , 'prompt') 
		document.getElementsByTagName('body')[0].appendChild(promptbox) 
		promptbox = eval("document.getElementById('prompt').style") 
		promptbox.position = 'absolute' 
		  promptbox.top = 100 
		promptbox.left = 200 
		promptbox.width = 300 
		promptbox.border = 'outset 1 #bbbbbb' 
		document.getElementById('prompt').innerHTML = "<table cellspacing='0' cellpadding='0' border='0' width='100%'><tr valign='middle'><td width='22' height='22' style='text-indent:2;' class='titlebar'></td><td class='titlebar'>" + prompttitle + "</td></tr></table>" 
		document.getElementById('prompt').innerHTML = document.getElementById('prompt').innerHTML + "<table cellspacing='0' cellpadding='0' border='0' width='100%' class='promptbox'><tr><td>" + message + "</td></tr><tr><td><input type='text' value='" + dftvalue + "' id='promptbox' onblur='this.focus()' class='promptbox'></td></tr><tr><td align='right'><br><input type='button' class='prompt' value='OK' onMouseOver='this.style.border=\"1 outset #dddddd\"' onMouseOut='this.style.border=\"1 solid transparent\"' onClick='" + sendto + "(document.getElementById(\"promptbox\").value); document.getElementsByTagName(\"body\")[0].removeChild(document.getElementById(\"prompt\"))'> <input type='button' class='prompt' value='Cancel' onMouseOver='this.style.border=\"1 outset transparent\"' onMouseOut='this.style.border=\"1 solid transparent\"' onClick='" + sendto + "(\"\"); document.getElementsByTagName(\"body\")[0].removeChild(document.getElementById(\"prompt\"))'></td></tr></table>" 
		document.getElementById("promptbox").focus() 
*/
} 

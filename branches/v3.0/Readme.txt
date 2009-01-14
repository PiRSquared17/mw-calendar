<!-- 
* Readme.txt - 8/9/2008
* Place this data in a wiki page and link with your calendar
* Calendar Help information
* Contact: Eric Fortin: kenyu73@gmail.com
* MediaWiki: http://www.mediawiki.org/wiki/User:Kenyu73/Calendar
-->
__TOC__
<br/>
==Syntax example==
To add a calendar to your page, just follow the details in this '''Syntax''' section. Please remember, the calendar name is the unique identifer for all events. If you name your calendar "<u>Eric</u>" and someone else already has that named calendar, you both will ''share'' the same calendar events!
 <nowiki> <calendar name="Family Events" editdefault="1" usetemplates="1" /></nowiki>
{| border=1 cellpadding="5"
! Parameters
! Description
! Example
! Default
|-
|'''name'''
|Name of your calendar.<br/> ''Note'': Calendars with the same name share events.
|name="Family Events"
|Public
|-
|'''usetemplates'''
|Allows the use of one page to add events 
|usetemplates="1"
|0 - no templates
|-
|'''defaultedit'''
|Default events to "edit" mode when clicked
|defaultedit="1"
|1 - view mode
|-
|'''noadd'''
|Prevents the "Add Event" tag from being displayed
|noadd="1"
|0 - allow 'add event' link
|-
|'''yearoffset'''
|Sets the year dropdown +/- value
|yearoffset="3"
|5 - +/- years
|-
|'''date'''
|Show calendar for a certain date.<br />values can be: '''today''', '''tomorrow''' or '''datestamp'''
|date="tomorrow" or date="1-1-2010"
|normal month view
|-
|'''charlimit'''
|Sets the calendar event charactor limit length. The default is 20
|charlimit="30"
|20 charactors
|}

== Events ==
Events can be entered either by the "'''add event'''" link or via the "'''template load'''" button (if enabled). Both work together seemlessly, but clicking each of them will bring you back to the respective method of creation.  <br />


Events are listed on the calendar with the information on the first line of the page if created via "'''add event'''". <br /> 

In this example, '''Summer Picnic''' will appear on the calendar.
 Summer Picnic<br>
 Our department will be holding a summer picnic at the park.  Bring your families and your appetites!

=== Multiple Events ===
In this example, ''two'' calendar events are created using the same page. The '''== event ==''' can be used to create these mulitple events per page. However, you can still create new page events by clicking ''Add Event''.<br/>

In this example, '''Picnic''' and '''Party''' will show up on the same day.
 <nowiki>
==Picnic==
Bring food!
==Party==
Bring drinks
</nowiki>

=== Template Events ===
The template button (if enabled) allows users to add a bunch of events into one page. Only one template is created per month/year. This can be used along with all other event types.

The day and the event '''must''' be seperated by an '#' as shown in the example. You can also create duplicated days.

 <nowiki>1#Vacation
2#Holiday
7#Election Day
7#Office Closed
31#Half Day</nowiki>

=== Repeating Events ===
To scheduled a repeating event, you need to enable templates by adding the (usetemplates="1") parameter. You can then add a repeating event using the following syntax to schedule the 1st to the 10th as a vacation:
 1-10#Vacation

== Installation ==
The following are details of the administrator installation of this calendar extension.
 '''Folder Path:''' /extensions/Calendar
'''Localsettings.php:''' 
 require_once("$IP/extensions/Calendar/Calendar.php");<br/>
 $wgExtraNamespaces[100] = "CalendarEvents";
 $wgExtraNamespaces[101] = "CalendarEvents_talk";

 '''''optional overrides'''''
 $extensionPath = "/var/www/extensions/Calendar/";
        or
 $extensionPath = "c:\extensions\";

The additional namespaces move all the events outside the "main" group... should clean the mess up some. If you have custom namespaces installed already, make sure you change bump the [100][101] values up accordingly.
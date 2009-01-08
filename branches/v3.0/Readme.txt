<!-- 
* Readme.txt - 12/13/2008
* Place this data in a wiki page and link with your calendar
* Calendar Help information
* Contact: Eric Fortin: kenyu73@gmail.com
* MediaWiki: http://www.mediawiki.org/wiki/User:Kenyu73/Calendar
-->
__TOC__
==Setup==
# It's recommended to create a page in a "CalendarEvents" namespace, however its not required.
#* The easist way is to enter "CalendarEvents:My Calendar Name" in the search box to create the main base calendar page.
# Add a <nowiki><calendar /></nowiki> extension tag to the newly create page (or existing page)
# Add parameters as required (see below listing)

Note: You can have more then one calendar per page. It's fun to find unique combinations of how to use "full" calendar view and "day" only view. Don't forget, since this is a tagged extension, you always wrap the calendar in a table to scrink it down or justify it...


The calendar has many advanced features; below is a simple basic way to setup the calendar. This calendar will create a standard calendar named "Public" if no name parameter is given, but it's recommended that, at minimun, you create a 'name' parameter. This will give you a good all around full featured calendar. Read and use the advanced parameters at you own risk! :)
 
 <nowiki><calendar /> </nowiki>
 <nowiki><calendar name="Team Calendar" /> </nowiki>

<div style="color:red">'''Important:'''</div>
To gain the ability of Parent/Subpage linking, place the calender in a "CalendarEvents:" namespace. Create a wiki page as shown above and then add your calendar extension tag to that page. This will populate a "quick" shortcut link back to the calendar after the event is entered and saved. The calendar will still work fine if not added to the "CalendarEvents:" namespace, but you will not get a "quick link" back to the main calender page.



The following are examples of how the ('''Page/Name/EventDate''') event will look:

 <nowiki><calendar name="Sales" /></nowiki>
     '''CalendarEvents:Acme Company/Sales/12-1-2008 -Event 1'''

 <nowiki><calendar name="Support" /></nowiki>
     '''CalendarEvents:Acme Company/Support/12-1-2008 -Event 1'''

You can also "share" or subscribe to other calendars by using the "''subscribe''" or "''fullsubscribe''" parameter. This will create a calendar of your own, but you'll also have all the events listed from "Sales". Remember to use the full "wiki page/calendar name" format
 <nowiki><calendar name="Support" subscribe="Acme Company/Sales" /></nowiki>


 
=== ===
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
|usetemplates
|off - no templates 
|-
|'''defaultedit'''
|Default events to "edit mode" when clicked
|defaultedit
|off - "page mode" view
|-
|'''disableaddevent'''
|prevents the "Add Event" tag from being displayed
|disableaddevent (<s>noadd</s>)
| enabled
|-
|'''yearoffset'''
|Sets the year dropdown +/- value
|yearoffset="3"
|2 - +/- years
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
|-
|'''subscribe'''
|Allows the calendar to subscribe to existing events from the subscribed calendar; 'add events' go to your local calendar only
|subscribe="''Tech Group/Company Calendar''"
|not subscribed
|-
|'''fullsubscribe'''
|Your calendar only accesses the subscribed calendars events; 'add events' go to the subscribed calendar
|fullsubscribe="''Tech Group/Team Calendar''"
|off - 'add event' creates new events to the local calendar only; subscribed events link to the subscribed calendar
|-
|'''disablelinks'''
|removes the ability to click/edit an existing event
|disablelinks
|off - allow links/edits
|-
|'''usemultievent'''
|clicking 'add event' opens the last entered event; you must place each event title in ==event1==, ==event2== multiple event formatting as describle in this help. An "alert!" link (triggers js popup) will display for each day that need to be updated.
|usemultievent
|false - 'add event' creates new event pages
|-
|'''useeventlist'''
|Enabling this displays a vertical list of all events within a defined amount of days. This hits the db alot as it must search events for every single day in the amount of days defined... the code is limits the max to 120 days, but use the least days needed.
|useeventlist="30"
|off - 
|-
|'''lockdown'''
|this includes 'noadd' and 'disablelinks'... no parameter needed
|lockdown
|false - no lockdown
|}

== Events ==
Events can be entered either by the "'''add event'''" link or via the "'''template load'''" button (if enabled). Both work together seemlessly, but clicking each of them will bring you back to the respective method of creation. Once you save the event, you can easily go back to the calendar via the Subpage/Parent link right above the page body. <br />

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

The day and the event '''must''' be seperated by an '#' as shown in the example. You can also create duplicated days. The days do not have to be in order

 <nowiki>
1# Vacation
2# Holiday
7# Election Day
7# Office Closed
31# Half Day
19# Appointment
</nowiki>

=== Repeating Events ===
To scheduled a repeating event, you need to enable templates by adding the (usetemplates="1") parameter. You can then add a repeating event using the following syntax to schedule the 1st to the 10th as a vacation:
 1-10#Vacation

=== Colors ===
You can prefix events as shown in the examples to add custom colors to the calendar events: syntax: <textcolor::bg-color::EventName>
 Standard Event: red::Eric's Vacation
 Template Event: 1-10# red::Eric's Vacation
 Multi Event: 
    == red::Eric's Vacation ==
    == green::Christmas ==

You can also create text background as follows:
 Standard Event: red::green::Eric's Vacation
 Template Event: 1-10# ::green::Eric's Vacation (default text color)
 Multi Event: 
    == red::green::Eric's Vacation ==
    == ::green::Christmas == (default text color)

== Installation ==
The following are details of the administrator installation of this calendar extension. If you dont have any custom Namespaces, then 100 and 101 are fine, if you do have existing custom Namespaces, just bump the numbers up accordingly. See [http://www.mediawiki.org/wiki/Help:Namespaces Help:Namespaces] for more information. The $wgNamespacesWithSubpages values must match the values assigned to the $wgExtraNamespaces.
 '''Folder Path:''' /extensions/Calendar
'''Localsettings.php:''' 
 require_once("$IP/extensions/Calendar/Calendar.php");<br/>
 
 // Puts events into their own namesspace/group (not included in 'main' searches... etc)
 $wgExtraNamespaces[100] = "CalendarEvents";
 $wgExtraNamespaces[101] = "CalendarEvents_talk";
 
 // Puts the events into Subpages (allows a quick link back to primary calendar)
 $wgNamespacesWithSubpages[100] = true;
 $wgNamespacesWithSubpages[101] = true;
The additional namespaces move all the events outside the "main" group... should clean the mess up some. If you have custom namespaces installed already, make sure you change bump the [100][101] values up accordingly.

 '''''optional overrides'''''
 $extensionPath = "/var/www/extensions/Calendar/";
        or
 $extensionPath = "c:\extensions\";
<!-- 
* Readme.txt
* Place this data in a wiki page and link with your calendar
* Calendar Help information
* Contact: Eric Fortin: kenyu73@gmail.com
* MediaWiki: http://www.mediawiki.org/wiki/User:Kenyu73/Calendar
-->
__TOC__
==Setup (v3.7 Release) 1/27/2009==

* It's recommended to create a custom calendar type Namespace, like '''Calendars''', but can be whatever Namespaces defined in LocalSettings.php or standard MediaWiki namespaces (like user namespaces) however, it's not required. It is recommended though so seaches in the main wiki do not included calendar events.
** The easist way is to enter "Calendars:PageName" in the search box to create the main base calendar page.
* Add a <nowiki><calendar /></nowiki> extension tag to the newly create page (or existing page)
* Add parameters as required (see below listing)

Note: You can have more then one calendar per page. It's fun to find unique combinations of how to use "full" calendar view and "day" only view. Don't forget, since this is a tagged extension, you could always wrap the calendar in a table to shrink it down or justify it...


The calendar has many advanced features; below is a simple basic way to setup the calendar. This calendar will create a standard calendar named "Public" if no name parameter is given, but it's recommended that, at minimun, you create a 'name' parameter. This will give you a good all around full featured calendar. Read and use the advanced parameters at you own risk! :)
 
 <nowiki><calendar /> </nowiki>
 <nowiki><calendar name="Team Calendar" /> </nowiki>

<div style="color:red">'''Important:'''</div>
To gain the ability of Parent/Subpage linking, place the calender in an existing namespace ([[Help:Namespaces]]) or create a new one. Create a wiki page as shown above and then add your calendar extension tag to that page. This will populate a "quick" shortcut link back to the calendar after the event is entered and saved. The calendar will still work fine if not added to a namespace, but you will not get a "quick link" back to the main calender page.<br/>
[[image:namespaces example.gif]]



The following are examples of how an ('''Namespace:Page/Name/EventDate''') event will look:

 <nowiki><calendar name="Sales" /></nowiki>
     '''Calendars:Acme Company/Sales/12-1-2008 -Event 1'''

 <nowiki><calendar name="Support" /></nowiki>
     '''Calendars:Acme Company/Support/12-1-2008 -Event 1'''

=== Sharing Calendars ===
You can also "share" or subscribe to other calendars by using the "''subscribe''" or "''fullsubscribe''" parameter. This will create a calendar of your own, but you'll also have all the events listed from "Sales". Remember to use the full "wiki page/calendar name" format. Be sure to include ''usetemplates'' or other special parameters in your calendar if the subscibed calendar uses them.
 '''Namespace not used:'''
 <nowiki><calendar name="Support" subscribe="Acme Company/Sales" /></nowiki>

 '''Namespace is used:'''
 <nowiki><calendar name="Support" subscribe="Calendars:Acme Company/Sales" /></nowiki>

=== Parameters ===
Please use quotes for any parameter that may contain a space
{| border=1 cellpadding="5"
! Parameters
! Description
! Example
! Default
! Version
|-
|'''name=<"value">'''
|Name of your calendar
|name="Family Events"
|Public
|2.0.4
|-
|'''disableaddevent'''
|The "Add Event" link is removed from the calendar.
|disableaddevent
|enabled
|2.0.4
|-
|'''yearoffset=<value>'''
|Sets the year dropdown +/- value.
|yearoffset=3
|2  (+/- years)
|2.0.4
|-
|'''date=<value>'''
|Display a single day listing. <br />Values can be: '''today''', '''tomorrow''' or a '''datevalue'''
|date=tomorrow or date=1-1-2010
|off - normal month view
|2.0.4
|-
|'''defaultedit'''
|Whenever a user clicks an event, the event defaults to edit mode.
|defaultedit
|off - page view
|3.0
|-
|'''charlimit=<value>'''
|Sets the calendar event name max length
|charlimit=30
|20 charactors
|3.0
|-
|'''subscribe=<"value">'''
|Allows the calendar to subscribe to existing events from other calendar(s); subscribe to additional calendars delimited by a comma within the ; ''add events'' go to '''your''' calendar only
|subscribe="Main Page/Name, SomePage/Name"
|not subscribed
|3.2
|-
|'''usetemplates'''
|This allows the use of one page to add events by storing many events in one location. The templates are identifed by the button with the month name in the lower right section of the calendar.
|usetemplates
|disabled
|3.2
|-
|'''locktemplates'''
|This disables the template button and template links; template events remain visable
|locktemplates
|off
|3.2
|-
|'''fullsubscribe=<"value">'''
|Allows the calendar to subscribe impersonate another calendar; ''add events'' go to the subscribed calendar '''only'''; you can use ''subscribe'' mode if needed as well
|fullsubscribe="Tech Group/Team Calendar"
|not subscribed
|3.2
|-
|'''disablelinks'''
|This removes the ability to click/edit an existing event created via 'add event'; use 'locktemplates' to disable template created links
|disablelinks
|off - allow links/edits
|3.2
|-
|'''usemultievent'''
|Users clicking 'add event' opens the last entered event; you must place each event title in ==event1==, ==event2== multiple event formatting as describle later in this help.
|usemultievent
|disabled - 'add event' will create a new event pages
|3.2
|-
|'''maxdailyevents=<value>'''
|Set the limit of how many "add event" unique pages are created; this doesn't include ''template'' or ''==event=='' type entries. This in sense forces users to use ==event1==, ==event2== formatting
|maxdailyevents=5
|5 events
|3.2
|-
|'''disablestyles'''
|Disable the 'event style' button and disables keyword search styling; inline direct syles are not effected.
|disablestyles
|enabled, but does ''nothing'' until keyword styles are added
|3.2
|-
|'''lockdown'''
|Basically puts the calendar into a read-only state; this includes 'disableaddevent', 'disablelinks' and 'locktemplates'
|lockdown
|false - no lockdown
|3.2
|-
|'''enablesummary=<value>'''
|Enables event summaries to display below the eventname; value is max character length of the summary
|enablesummary=100
|disabled
|3.4.2
|-
|'''useeventlist=<value>'''
|Enabling this displays a vertical list of all events within a defined amount of days. This hits the db alot as it must search events for every single day in the amount of future days defined... I have the code limited to 120 days, but use the least days needed.
|useeventlist=30
|disabled
|3.4.2
|-
|'''useconfigpage'''
|Use alot of parameters? Use the config page instead. Enter each parameter followed by the enter key into the config page. The ''disablelink'' option removes the btn and text links to the config page. You can use the config page and <calendar /> options together, but the <calendar /> options overwrites the ''config page'' options.
|useconfigpage<br/>useconfigpage=disablelink
|off
|3.4.2
|-
|'''css=<value>'''
|Create your own css design based off the default.css file. Rename it and load it via this parameter. I may change the default.css page during releases, so use this as long as you dont mind re-writing your custom css after an upgrade... (=
|css="olive.css"
|default.css
|3.5
|-
|'''disabletimetrack'''
|Time tracking is enabled by default and looks for double colons (::vacation-8) or (::vacation:8). This will create a dynamic listing of trackable events below the calendar.
|disabletimetrack
|enabled
|3.5
|-
|'''enablerepeatevents'''
|Repeating events are created using using (5# Vacation) within normal events. The code looks up the previous months and applies carry-over events to the current month. It may increase the calendar load time as it looks back 15 days into the previous month for carry over repeating events.
|enablerepeatevents
|disabled
|3.5
|-
|'''enablelegacy=<"value">'''
|Load events from the older "Title (12-1-2008) - Event 1" format. These older events were used in some version of 3.2 and older. This may increase calendar load times as it much search for older style events and newer events. The optional parameter sets an effective (last) date on which to lookup the older events. 
|enablelegacy=12/15/2008
|disabled
|3.5.0.1<br>mod: 3.6
|-
|'''diablemodes'''
|This removes the 'year', 'month', 'week' buttons from the top of the respective pages.
|diablemodes
|enabled
|3.6
|-
|'''5dayweek'''
|This sets the 'week' mode calendar to not display weekends
|5dayweek
|full week
|3.6
|-
|'''week or year'''
|These parameters default the calendar into that requested mode
|year
|month mode
|3.6
|-
|'''ical=<value>'''
|This enables the iCalendar load tool in full calendar mode. Currently this will load 'DTSTART', 'DTEND', 'SUMMARY', 'DESCRIPTION' and most standard 'RRULE' logic. The optional value is 'overwrite'. Please see iCal readme for more info.
|ical
|disabled
|3.6
|-
|'''disablerecurrences'''
|This skips any RRULE recurrences stored in the 'page/name/recurrences' page. The page contains all imported ical repeating rules (RRULE).
|disablerecurrences
|disabled
|3.6.0.2
|-
|'''simplemonth'''
|Creates a simple month that displays only clickable numberic days. This would best be used wraped in a <table> tag to create "mini-calendar" views.
|simplemonth
|normal mode
|3.7
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

=== Repeating Events ===
Repeating events is not an easy task to handle via "wiki" page structure. However, I did come up with a way... The below example will create 5 repeating Vacation events in the caledar. You MUST enable the functionality by adding '''enablerepeatevents''' to your parameter tag or config page.
 5# Vacation

=== Recurrence Event ===
Recurrence type events are traditional repeating yearly events like holidays, birthdays, etc. To create a recurrence event, choose 'add event' and add the following trigger syntax:
 ##My Birthday

This will convert the event into an vCalendar RRULE event and store it into the 'page/name/recurrence' wiki page. This is also where any ical recurrence events are stored.

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
20-25# Hiking Trip <-- multiple day event
</nowiki>

=== Colors and Formatting ===
# The calendar supports most of the basic MediaWiki text/font properties including the 'ticks' for italic and bold.
#* <nowiki>'''<font color=red>vacation</font>'''</nowiki>
#* <span style="color:red;background:yellow">Vacation time!!</span> --> <nowiki><span style="color:red;background:yellow">Vacation time!!</span></nowiki>
# Setup the ''event style''' page by adding as many 'styles' as you wish. These styles are based on keyword matches, so be wary of what words you choose... The styles follow standard html/css style properties.
#* '''syntax:''' keyword:: style1; style2; etc
#** myStyle:: color:green; text-decoration:line-through; --> <s><span style="color:green">Whatever</span></s>
#** birthday:: color:red; font-style: bold; --> '''<font color=red>My Birthday</font>'''
#** sick:: color: green;background-color: yellow --> <span style="color:green;background-color:yellow">Out Sick today</span> <br/>
#** vacation:: color: red; font-style: italic --> ''<font color=red>Vacation to Florida!</font>''<br/>


I'm not sure how far and how many variation of the css and/or Wiki formatting will go, but I've tested a good portion of the standard text properties. (<nowiki><div></nowiki> is giving me an issue at this time though... but <nowiki><span></nowiki> works just fine!)
=== Time Tracker ===
You can keep simple time tracking of events by formatting the event as below. This will track any dynamically created event in a simple table below the calendar in full mode only.
 ::Vacation: 8 or ::Vacation -8
 ::Team Project 1 - 3
 ::Sick : 4

Note that events created using the 'add event' link only track time for that month. If you want to track a years total, you need to enable and use month templates ('''usetemplates''')



== vCalendar (iCal) Support ==
The calendar supports the basic importing of vCalendar formatted files. The import utility is enabled by adding '''ical''' or '''ical=overwrite''' to your parameter string or ''config'' file settings.<br>
<br>
The calendar excepts the following vCalendar formats
:DTSTART
:DTEND
:SUMMARY
:DESCRIPTION (not with RRULEs though)
:RRULE 

The RRULE evaluates basic calendar event logic only... nothing complex like "every 3rd Monday of every-other month". It does handle typical repeats like Thanksgiving, Mothers Day, etc that required logic like "the 4th Thursday of November" or "the last Monday of March" kinda logic. Basically, it should capture most repeating events like birthdays and holidays.


The RRULE (repeating) events are stored in a subpage called '''recurrence'''. Basically, in the following format ''page/calendarname/recurrence'''. You can manually edit or delete these as needed. If you use the ''ical=overwrite'' option, it deleted the data before writing in the new ical data. 


Imported events without the RRULE are created in the calendar as normal pages in the -Event 0 page for the respective day.

== Installation ==
The following are details of the administrator installation of this calendar extension. If you dont have any custom Namespaces, then 100 and 101 are fine, if you do have existing custom Namespaces, just bump the numbers up accordingly. See [http://www.mediawiki.org/wiki/Help:Namespaces Help:Namespaces] for more information. The $wgNamespacesWithSubpages values must match the values assigned to the $wgExtraNamespaces.
 '''Recommended Folder Path:''' /extensions/Calendar
'''Localsettings.php:'''<br/>
<br/>
''Simple'':
 require_once("$IP/extensions/Calendar/Calendar.php");<br/>
 
''Recommended'':
 require_once("$IP/extensions/Calendar/Calendar.php");<br/>
 
 // Puts events into their own namesspace/group (not included in 'main' searches... etc)
 $wgExtraNamespaces[100] = "Calendars";
 $wgExtraNamespaces[101] = "Calendars_talk";
 //''Note: 'Calendars' is an example, please feel free to use whatever name you wish''
 
 // Puts the events into Subpages (allows a quick link back to primary calendar)
 $wgNamespacesWithSubpages[100] = true;
 $wgNamespacesWithSubpages[101] = true;
The additional namespaces move all the events outside the "main" group... should clean the mess up some. If you have custom namespaces installed already, make sure you bump up the [100][101] values up accordingly.



[[Extension:Calendar (Kenyu73)/Readme/beta | Beta Readme]]

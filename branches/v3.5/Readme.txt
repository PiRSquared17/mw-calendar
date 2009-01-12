<!-- 
* Readme.txt - 1/6/2009
* Place this data in a wiki page and link with your calendar
* Calendar Help information
* Contact: Eric Fortin: kenyu73@gmail.com
* MediaWiki: http://www.mediawiki.org/wiki/User:Kenyu73/Calendar
-->
__TOC__
==Setup (v3.5.01 Release) 1/12/2009==

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
|-
|'''name=<"value">'''
|Name of your calendar
|name="Family Events"
|Public
|-
|'''useconfigpage'''
|Use alot of parameters? Use the config page instead. Enter each parameter followed by the enter key into the config page. The ''disablelink'' option removes the btn and text links to the config page. You can use the config page and <calendar /> options together, but the <calendar /> options overwrites the ''config page'' options.
|useconfigpage<br/>useconfigpage=disablelink
|off
|-
|'''usetemplates'''
|Allows the use of one page to add events 
|usetemplates
|disabled
|-
|'''locktemplates'''
|disables the template button and template links; template events remain visable
|locktemplates
|off
|-
|'''defaultedit'''
|Default events to "edit mode" when clicked
|defaultedit
|off - page view
|-
|'''disableaddevent'''
|prevents the "Add Event" tag from being displayed
|disableaddevent
|enabled
|-
|'''yearoffset=<value>'''
|Sets the year dropdown +/- value
|yearoffset=3
|2  (+/- years)
|-
|'''date=<value>'''
|Show calendar for a certain date.<br />Values can be: '''today''', '''tomorrow''' or a '''datevalue'''
|date="tomorrow" or date="1-1-2010"
|off - normal month view
|-
|'''useeventlist=<value>'''
|Enabling this displays a vertical list of all events within a defined amount of days. This hits the db alot as it must search events for every single day in the amount of days defined... I have the code limited to 120 days, but use the least days needed.
|useeventlist=30
|disabled
|-
|'''charlimit=<value>'''
|Sets the calendar eventname max length
|charlimit=30
|20 charactors
|-
|'''enablesummary=<value>'''
|Enables event summaries to display below the eventname; value is max character length
|enablesummary=100
|disabled
|-
|'''subscribe=<"value">'''
|Allows the calendar to subscribe to existing events from other calendar(s); subscribe to additional calendars delimited by a comma; ''add events'' go to '''your''' calendar only
|subscribe="Main Page/Company Calendar, Tech Group/Training Calendar"
|not subscribed
|-
|'''fullsubscribe=<"value">'''
|Allows the calendar to subscribe impersonate another calendar; ''add events'' go to the subscribed calendar '''only'''; you can use ''subscribe'' mode if needed as well
|fullsubscribe="Tech Group/Team Calendar"
|not subscribed
|-
|'''disablelinks'''
|removes the ability to click/edit an existing event; use 'locktemplates' to disable template created links
|disablelinks
|off - allow links/edits
|-
|'''usemultievent'''
|clicking 'add event' opens the last entered event; you must place each event title in ==event1==, ==event2== multiple event formatting as describle later in this help. An "''alert!''" link will display for each day that needs to be updated.
|usemultievent
|disabled - 'add event' creates new event pages
|-
|'''maxdailyevents=<value>'''
|Set the limit of how many "add event" unique pages are created; this doesn't include ''template'' or ''==event=='' type entries
|maxdailyevents=5
|5 events
|-
|'''disablestyles'''
|Disable the 'event style' button and disables keyword styling; inline syles are not effected
|disablestyles
|enabled, but does ''nothing'' until keyword styles are added
|-
|'''css=<value>'''
|Choose a new color scheme for your calendar. The css pages are located in the css sub-folder
|css="olive.css"
|default.css
|-
|'''disabletimetrack'''
|Time tracking is enabled by default and looks for double colons (::vacation-8)
|disabletimetrack
|enabled
|-
|'''enablerepeatevents'''
|Repeating events are created using using (5# Vacation) with normal events. The code looks up the previous months and applies carry-over events to the current month... very time intensive. It double the calendar load time of its owns events as well as subscribed calendars.
|enablerepeatevents
|disabled
|-
|'''enablelegacy'''
| Load events from the "CalanderEvents:Page/Title (12-1-2008) - Event 1" format. Effects performance some...
|enablelegacy
|disabled
|-
|'''lockdown'''
|Basically puts the calendar into a read-only state; this includes 'disableaddevent', 'disablelinks' and 'locktemplates'
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

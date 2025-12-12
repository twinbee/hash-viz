#! /usr/bin/python
#
#  genCal.py
#  
#
#  Created by Claude Winborn on 12/29/2008.
#  Revised by Claude Winborn on 12/02/2009.
#  Revised by Claude Winborn on 12/15/2010.
#  Revised by Claude Winborn on 12/07/2011.
#  Revised by Claude Winborn on 11/5/2012.
#  Revised by Claude Winborn on 10/15/2017.
#  Revised by Claude Winborn on 8/24/2018.
#  Revised by Claude Winborn on 10/25/2020.


week = 0
year = 2021
months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]


# FUNCTION TO TEST FOR LEAPYEAR
# returns true if leapyear
def isLeapYear(year):
  if year % 4 == 0 and year % 100 != 0 or year % 400 == 0:
    return 1
  return 0


# FUNCTION TO GENERATE A CELL FOR AN ARBITRARY DAY
def genDay(month_, day_, year_):
  return """
						<td class="day">
							<table class="inner" id="j%d%d">
								<tr>
									<td class="dom">%d</td>
								</tr>
								<tr>
									<td class="event"> 
										<?php fillIn(%d, %d, %d); ?>
									</td>
								</tr>
							</table>
						</td>
""" % (month_, day_, day_, month_+1, day_, year_)



# FUNCTION TO GENERATE BLANK CELLS
def genEmpty():
  return """						<td class="empty"></td>
"""
  

# FUNCTION TO GENERATE HTML FOR AN ARBITRARY MONTH
# month: january = 0, february = 1, etc.
# start: sunday = 0, monday = 1, etc.
# finish: last day of the month 
# week: week of the year 
def genMonth (month, start, finish):

  global week
  eventString = """ <img src="%s"/><br />

										open
"""
    
  prev = "$%02d-%d.php" % (month, year)
  if month == 0: prev = "../%d/$12-%s.php" % (year - 1, year - 1)

  next = "$%02d-%s.php" % (month + 2, year)
  if month == 11: next = "../%d/$01-%s.php" % (year + 1, year + 1)
  

  monthName = "month-%02d.png" % (month + 1)

  cyear = year % 100

  html = """<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
    <meta http-equiv="content-type" content="text/html;charset=utf-8" />
    <meta name="generator" content="Claudes Calendar Generator" />
    <link rel="apple-touch-icon" href="/dfwh3-152x152.png" />
    <title>%s Hash Events</title>
    <link href="calendar.css" rel="stylesheet" type="text/css" media="all" />

    <script language="JavaScript">
      // script to highlight todays date via style override
      var d = new Date();
      var id = "j" + d.getMonth() + d.getDate();
      if (d.getYear() %% 100 == %d) document.write('<style type="text/css" media="screen"><!-- table.inner#' + id + ' { 	background-image: url(throb.gif); }--> </style>');

			// script to open navagation window
			function openNav() {
			window.open("/calendar/Nav/index.html", "nav", "width=320, height=1040, top=0, left=0");
			}
		</script>

""" % (months[month], cyear)

  html = html + "<?php\n\t$year=%d; \n\t$month=%d;\n\tinclude 'php.php';\n?>" % (year, month+1)

  html = html + """
</head>

<body>
<map name="Map" id="Map">
	<area shape="rect" coords="0,0,150,91" href="%s" alt="Previous Month" />
	<area shape="rect" coords="957,0,807,91" href="%s" alt="Next Month" />
</map>

<div class=container>
	<table class="overall"  border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td>
				<table class="banner" border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td><img src="%s" alt=""  border="0" usemap="#Map"/></td>
					</tr>  
				</table>
			</td>
		</tr>
		<tr>
			<td>
				<table class="main"  border="0" cellspacing="0" cellpadding="0">
					<tr >
						<th>Sunday</th>
						<th>Monday</th>
						<th>Tuesday</th>
						<th>Wednesday</th>
						<th>Thursday</th>
						<th>Friday</th>
						<th>Saturday</th>
					</tr>

""" % (prev, next, monthName)


  startRow = """					<tr>
"""

  endRow = """					</tr>
"""

  # START WITH SUNDAY

  dayOfWeek = 0;
  
  # GENERATE BLANKS UNTIL THE FIRST DAY OF THE MONTH
  
  if dayOfWeek != start:
    html += startRow
  
  while dayOfWeek != start:
    html += genEmpty()
    dayOfWeek += 1
    
  day = 1
  sunday = 0
    
  # GENERATE DAYS TO MAKE THE CALENDAR
    
  while day <= finish:
    if dayOfWeek == 0 : html += startRow
	
    # SELECT THE EVENT FOR THIS PARTICULAR DAY
    
    if dayOfWeek == 0 : sunday += 1
    if dayOfWeek == 0 : week += 1
        
  # SET TODAYS EVENT      	  	
    html += genDay(month, day, year)
    if dayOfWeek == 6 : html += endRow
    dayOfWeek = (dayOfWeek + 1) % 7
    day += 1;

  # GENERATE BLANKS UNTIL THE LAST ROW IS COMPLETE

  if dayOfWeek != 0:
    while dayOfWeek != 0:
      html += genEmpty()
      dayOfWeek = (dayOfWeek + 1) % 7
    html += endRow
		
    
  tail =  """				</table>
			</td>
		</tr>
		<tr id="nav">
			<td>
			<div id="menu">
				<a href="/index.html">home</a>&nbsp;&nbsp;&nbsp;&nbsp; 
				calendar&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Our_Idiots/index.html">our idiots</a>&nbsp;&nbsp;&nbsp;&nbsp; 
				<a href="/Write-Ups/index.html">write-ups</a>&nbsp;&nbsp;&nbsp;&nbsp; 
				<a href="/Gallery/index.html">gallery</a>&nbsp;&nbsp;&nbsp;&nbsp; 
				<a href="/Links/index.html">links</a>&nbsp;&nbsp;&nbsp;&nbsp; 
				<a href="/Road_Trip/index.html">road trip</a>&nbsp;&nbsp;&nbsp;&nbsp; 
				<a href="planning.php">planning</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/mobile/index.php">mobile</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="#" onclick="openNav();">nav</a>&nbsp;&nbsp;&nbsp;&nbsp; 
		</div> <!-- menu -->
		</td>
		</tr>
	</table>
</div>
</body>
</html>
""" 
  html +=  tail
	
	
  filename =  "$%02d-%s.php" % (month + 1, year)
  f = open(filename, "w")
  f.write(html)
  f.close()

# END OF FUNCTION genMonth
####################################################


# MAIN PRORAM STARTS HERE

# FIGURE OUT ON WHAT DAY THIS YEAR STARTS
weekday = 0
y = 1995
while y != year:
  if isLeapYear(y): weekday = (weekday + 366) % 7
  else:  weekday = (weekday + 365) % 7
  y += 1

# JANUARY
genMonth(0, weekday, 31)
weekday = (weekday + 31) % 7

# FEBRUARY

if isLeapYear(year): 
  genMonth(1, weekday, 29)
  weekday = (weekday + 29) % 7
else:
  genMonth(1, weekday, 28)
  weekday = (weekday + 28) % 7
  
# MARCH
genMonth(2, weekday, 31)
weekday = (weekday + 31) % 7

# APRIL
genMonth(3, weekday, 30)
weekday = (weekday + 30) % 7

# MAY
genMonth(4, weekday, 31)
weekday = (weekday + 31) % 7

#JUNE
genMonth(5, weekday, 30)
weekday = (weekday + 30) % 7

# JULY
genMonth(6, weekday, 31)
weekday = (weekday + 31) % 7

# AUGUST
genMonth(7, weekday, 31)
weekday = (weekday + 31) % 7

# SEPTEMBER
genMonth(8, weekday, 30)
weekday = (weekday + 30) % 7

# OCTOBER
genMonth(9, weekday, 31)
weekday = (weekday + 31) % 7

# NOVEMBER
genMonth(10, weekday, 30)
weekday = (weekday + 30) % 7

# DECEMBER
genMonth(11, weekday, 31)
weekday = (weekday + 31) % 7
  

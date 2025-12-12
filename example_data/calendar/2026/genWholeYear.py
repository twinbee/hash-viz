#! /usr/bin/python
#
#  genCal.py
#  
#
#  Created by Claude Winborn on 12/29/2008.
#  Revised by Claude Winborn on 12/02/2009.
#  Revised by Claude Winborn on 12/15/2010.
#  Revised by Claude Winborn on 12/07/2011.
#  Revised by Claude Winborn on 10/25/2020.


week = 0
year = 2021
months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]

head = """<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtmltransitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="pragma" content="no-cache" />
	<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	
	<title>%s Planning Calendar</title>
	<link href="calendar.css" rel="stylesheet" type="text/css" media="all" />
	
	
	<?php
	$year=%s;
	include 'big.php';
	?>
	
</head>
	
<body>
	
<div class=container>
	<table class="overall"  border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td>
				<table class="banner" border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td><img src="planning.png" alt=""  border="0" usemap="#Map"/></td>
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
"""





# FUNCTION TO TEST FOR LEAPYEAR
# returns true if leapyear
def isLeapYear(year):
  if year % 4 == 0 and year % 100 != 0 or year % 400 == 0:
    return 1
    
  return 0

# FUNCTION TO GENERATE A CELL FOR AN ARBITRARY DAY
def genDay(day_, month_, event_, style_):
  return """						<td class="%s">
							<table class="inner">
								<tr>
									<td class="dom">%s</td>
								</tr>
								<tr>
								<td class="event"> %s</td>
								</tr>
								<tr>
									<td class="info"></td>
								</tr>
							</table>
						</td>
""" % (style_, day_, event_)
  
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
  print (start)

  global week
  eventString = """ <img src="%s"/><br />

										open
"""
  html = head % (year, year)
  
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
  print (dayOfWeek)
  day = 1
  sunday = 0
	
  if isLeapYear(year): mdays = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  else: mdays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]

	
  styles =["red", "blue", "red", "blue", "red", "blue", "red", "blue", "red", "blue", "red", "blue"]
   
  # GENERATE DAYS TO MAKE THE CALENDAR
    
  while day <= finish:
    min = 1
    max = 31
    month = 0

    while True:
      if day >= min and day <= max: break
      min += mdays[month]
      month += 1
      max += mdays[month]
    dom = day - min + 1

    if dayOfWeek == 0 : html += startRow
	
    # SELECT THE EVENT FOR THIS PARTICULAR DAY
    
    if dayOfWeek == 0 : sunday += 1
    if dayOfWeek == 0 : week += 1
    
	# INSERT STANDARD PHP CALL FOR THIS DAY
	
    event = "<?php fillIn(%s, %s, %s); ?>" % (month + 1, dom, year)


  # SET TODAYS EVENT
    monthDay = "%s  %d" % (months[month], dom)      	  	
    html += genDay(monthDay, month, event, styles[month])
    if dayOfWeek == 6 : html += endRow
    dayOfWeek = (dayOfWeek + 1) % 7
    day += 1;

  # GENERATE BLANKS UNTIL THE LAST ROW IS COMPLETE

  if dayOfWeek != 0:
    while dayOfWeek != 0:
      html += genEmpty()
      dayOfWeek = (dayOfWeek + 1) % 7
    html += endRow
    
  html += """				</table>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
"""

  filename =  "wholeYear.php"
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
if isLeapYear(year): genMonth(0, weekday, 366)
else: genMonth(0, weekday, 365)
print (weekday)




<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="pragma" content="no-cache" />
<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="generator" content="Martha's Calendar Generator" />
<link rel="apple-touch-icon" href="/dfwh3-152x152.png" />
<title>January, 2026 Hash Events</title>
<link href="calendar.css" rel="stylesheet" type="text/css" media="all" />

<script language="JavaScript">
// script to highlight todays date via style override
var d = new Date();
var id = "j" + d.getMonth() + d.getDate();
      if (d.getYear() % 100 == 26) document.write('<style type="text/css" media="screen"></style>');

			// script to open navagation window
			function openNav() {
			window.open("/calendar/Nav/index.html", "nav", "width=320, height=1040, top=0, left=0");
			}
		</script>

<?php
    $year=2026;
    $month=1;
    // Links are passed from Python for correct cross-year referencing
    $prev_link="../2025/$12-2025.php";
    $next_link="$02-2026.php";
    include 'php.php';
?>
</head>
<body>
<map name="Map" id="Map">
    <area shape="rect" coords="0,0,150,91" href="../2025/$12-2025.php" alt="Previous Month" />
    <!-- FIXED: Using coordinates 957,0,807,91 as requested for the Next Month button -->
    <area shape="rect" coords="957,0,807,91" href="$02-2026.php" alt="Next Month" />
</map>
<div class=container>
    <table class="overall"  border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <table class="banner" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><img src="month-01.png" alt="January"  border="0" usemap="#Map"/></td>
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
					<tr>
						<td class="empty"></td>
						<td class="empty"></td>
						<td class="empty"></td>
						<td class="empty"></td>
						<td class="day">
							<table class="inner" id="j01">
								<tr>
									<td class="holiday"><span class="tag">New Year's Day</span>1</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 1, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j02">
								<tr>
									<td class="dom">2</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 2, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j03">
								<tr>
									<td class="dom">3</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 3, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j04">
								<tr>
									<td class="dom">4</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 4, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j05">
								<tr>
									<td class="dom">5</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 5, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j06">
								<tr>
									<td class="dom">6</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 6, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j07">
								<tr>
									<td class="dom">7</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 7, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j08">
								<tr>
									<td class="dom">8</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 8, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j09">
								<tr>
									<td class="dom">9</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 9, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j010">
								<tr>
									<td class="dom">10</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 10, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j011">
								<tr>
									<td class="dom">11</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 11, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j012">
								<tr>
									<td class="dom">12</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 12, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j013">
								<tr>
									<td class="dom">13</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 13, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j014">
								<tr>
									<td class="dom">14</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 14, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j015">
								<tr>
									<td class="dom">15</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 15, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j016">
								<tr>
									<td class="dom">16</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 16, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j017">
								<tr>
									<td class="dom">17</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 17, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j018">
								<tr>
									<td class="dom">18</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 18, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j019">
								<tr>
									<td class="holiday"><span class="tag">MLK Jr. Day</span>19</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 19, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j020">
								<tr>
									<td class="dom">20</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 20, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j021">
								<tr>
									<td class="dom">21</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 21, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j022">
								<tr>
									<td class="dom">22</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 22, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j023">
								<tr>
									<td class="dom">23</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 23, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j024">
								<tr>
									<td class="dom">24</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 24, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j025">
								<tr>
									<td class="dom">25</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 25, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j026">
								<tr>
									<td class="dom">26</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 26, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j027">
								<tr>
									<td class="dom">27</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 27, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j028">
								<tr>
									<td class="dom">28</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 28, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j029">
								<tr>
									<td class="dom">29</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 29, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j030">
								<tr>
									<td class="dom">30</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 30, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j031">
								<tr>
									<td class="dom">31</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(1, 31, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>

                </table>
            </td>
        </tr>
    </table>
</div>

<tr id="nav">
			<td>
			<div id="menu">
				<a href="/index.html">home</a>&nbsp;&nbsp;&nbsp;&nbsp;
				calendar&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Events/index.html">events</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Maps/index.html">maps</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Our_Idiots/index.html">our idiots</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Write-Ups/index.html">write-ups</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/Road_Trip/index.html">road trip</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="planning.php">year</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="/mobile/index.php">mobile</a>&nbsp;&nbsp;&nbsp;&nbsp;
				<a href="#" onclick="openNav();">nav</a>&nbsp;&nbsp;&nbsp;&nbsp;
			</div>
			</td>
		</tr>
</body>
</html>


<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="pragma" content="no-cache" />
<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="generator" content="Martha's Calendar Generator" />
<link rel="apple-touch-icon" href="/dfwh3-152x152.png" />
<title>April, 2026 Hash Events</title>
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
    $month=4;
    // Links are passed from Python for correct cross-year referencing
    $prev_link="$03-2026.php";
    $next_link="$05-2026.php";
    include 'php.php';
?>
</head>
<body>
<map name="Map" id="Map">
    <area shape="rect" coords="0,0,150,91" href="$03-2026.php" alt="Previous Month" />
    <!-- FIXED: Using coordinates 957,0,807,91 as requested for the Next Month button -->
    <area shape="rect" coords="957,0,807,91" href="$05-2026.php" alt="Next Month" />
</map>
<div class=container>
    <table class="overall"  border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <table class="banner" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><img src="month-04.png" alt="April"  border="0" usemap="#Map"/></td>
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
						<td class="day">
							<table class="inner" id="j31">
								<tr>
									<td class="dom">1</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 1, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j32">
								<tr>
									<td class="dom">2</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 2, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j33">
								<tr>
									<td class="dom">3</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 3, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j34">
								<tr>
									<td class="dom">4</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 4, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j35">
								<tr>
									<td class="dom">5</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 5, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j36">
								<tr>
									<td class="dom">6</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 6, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j37">
								<tr>
									<td class="dom">7</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 7, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j38">
								<tr>
									<td class="dom">8</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 8, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j39">
								<tr>
									<td class="dom">9</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 9, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j310">
								<tr>
									<td class="dom">10</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 10, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j311">
								<tr>
									<td class="dom">11</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 11, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j312">
								<tr>
									<td class="dom">12</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 12, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j313">
								<tr>
									<td class="dom">13</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 13, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j314">
								<tr>
									<td class="dom">14</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 14, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j315">
								<tr>
									<td class="dom">15</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 15, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j316">
								<tr>
									<td class="dom">16</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 16, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j317">
								<tr>
									<td class="dom">17</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 17, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j318">
								<tr>
									<td class="dom">18</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 18, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j319">
								<tr>
									<td class="dom">19</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 19, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j320">
								<tr>
									<td class="dom">20</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 20, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j321">
								<tr>
									<td class="dom">21</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 21, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j322">
								<tr>
									<td class="dom">22</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 22, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j323">
								<tr>
									<td class="dom">23</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 23, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j324">
								<tr>
									<td class="dom">24</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 24, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j325">
								<tr>
									<td class="dom">25</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 25, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j326">
								<tr>
									<td class="dom">26</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 26, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j327">
								<tr>
									<td class="dom">27</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 27, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j328">
								<tr>
									<td class="dom">28</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 28, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j329">
								<tr>
									<td class="dom">29</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 29, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j330">
								<tr>
									<td class="dom">30</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(4, 30, 2026); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="empty"></td>
						<td class="empty"></td>
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

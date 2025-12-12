
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="pragma" content="no-cache" />
<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="generator" content="Martha's Calendar Generator" />
<link rel="apple-touch-icon" href="/dfwh3-152x152.png" />
<title>July, 2027 Hash Events</title>
<link href="calendar.css" rel="stylesheet" type="text/css" media="all" />

<script language="JavaScript">
// script to highlight todays date via style override
var d = new Date();
var id = "j" + d.getMonth() + d.getDate();
      if (d.getYear() % 100 == 27) document.write('<style type="text/css" media="screen"></style>');

			// script to open navagation window
			function openNav() {
			window.open("/calendar/Nav/index.html", "nav", "width=320, height=1040, top=0, left=0");
			}
		</script>

<?php
    $year=2027;
    $month=7;
    // Links are passed from Python for correct cross-year referencing
    $prev_link="$06-2027.php";
    $next_link="$08-2027.php";
    include 'php.php';
?>
</head>
<body>
<map name="Map" id="Map">
    <area shape="rect" coords="0,0,150,91" href="$06-2027.php" alt="Previous Month" />
    <!-- FIXED: Using coordinates 957,0,807,91 as requested for the Next Month button -->
    <area shape="rect" coords="957,0,807,91" href="$08-2027.php" alt="Next Month" />
</map>
<div class=container>
    <table class="overall"  border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <table class="banner" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><img src="month-07.png" alt="July"  border="0" usemap="#Map"/></td>
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
							<table class="inner" id="j61">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #11</span>1</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 1, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j62">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #12</span>2</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 2, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j63">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #13</span>3</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 3, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j64">
								<tr>
									<td class="holiday"><span class="tag">Independence Day</span>4</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 4, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j65">
								<tr>
									<td class="dom">5</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 5, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j66">
								<tr>
									<td class="dom">6</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 6, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j67">
								<tr>
									<td class="dom">7</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 7, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j68">
								<tr>
									<td class="dom">8</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 8, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j69">
								<tr>
									<td class="dom">9</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 9, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j610">
								<tr>
									<td class="dom">10</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 10, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j611">
								<tr>
									<td class="dom">11</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 11, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j612">
								<tr>
									<td class="dom">12</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 12, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j613">
								<tr>
									<td class="dom">13</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 13, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j614">
								<tr>
									<td class="dom">14</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 14, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j615">
								<tr>
									<td class="dom">15</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 15, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j616">
								<tr>
									<td class="dom">16</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 16, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j617">
								<tr>
									<td class="dom">17</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 17, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j618">
								<tr>
									<td class="dom">18</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 18, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j619">
								<tr>
									<td class="dom">19</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 19, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j620">
								<tr>
									<td class="dom">20</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 20, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j621">
								<tr>
									<td class="dom">21</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 21, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j622">
								<tr>
									<td class="dom">22</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 22, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j623">
								<tr>
									<td class="dom">23</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 23, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j624">
								<tr>
									<td class="dom">24</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 24, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j625">
								<tr>
									<td class="dom">25</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 25, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j626">
								<tr>
									<td class="dom">26</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 26, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j627">
								<tr>
									<td class="dom">27</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 27, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j628">
								<tr>
									<td class="dom">28</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 28, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j629">
								<tr>
									<td class="dom">29</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 29, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j630">
								<tr>
									<td class="dom">30</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 30, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j631">
								<tr>
									<td class="holiday"><span class="tag">Gispert's Birthday</span>31</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(7, 31, 2027); ?>
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


<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="pragma" content="no-cache" />
<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="generator" content="Martha's Calendar Generator" />
<link rel="apple-touch-icon" href="/dfwh3-152x152.png" />
<title>September, 2027 Hash Events</title>
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
    $month=9;
    // Links are passed from Python for correct cross-year referencing
    $prev_link="$08-2027.php";
    $next_link="$10-2027.php";
    include 'php.php';
?>
</head>
<body>
<map name="Map" id="Map">
    <area shape="rect" coords="0,0,150,91" href="$08-2027.php" alt="Previous Month" />
    <!-- FIXED: Using coordinates 957,0,807,91 as requested for the Next Month button -->
    <area shape="rect" coords="957,0,807,91" href="$10-2027.php" alt="Next Month" />
</map>
<div class=container>
    <table class="overall"  border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <table class="banner" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><img src="month-09.png" alt="September"  border="0" usemap="#Map"/></td>
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
							<table class="inner" id="j81">
								<tr>
									<td class="dom">1</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 1, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j82">
								<tr>
									<td class="dom">2</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 2, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j83">
								<tr>
									<td class="dom">3</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 3, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j84">
								<tr>
									<td class="dom">4</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 4, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j85">
								<tr>
									<td class="dom">5</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 5, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j86">
								<tr>
									<td class="holiday"><span class="tag">Labor Day</span>6</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 6, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j87">
								<tr>
									<td class="dom">7</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 7, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j88">
								<tr>
									<td class="dom">8</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 8, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j89">
								<tr>
									<td class="dom">9</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 9, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j810">
								<tr>
									<td class="dom">10</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 10, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j811">
								<tr>
									<td class="dom">11</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 11, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j812">
								<tr>
									<td class="dom">12</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 12, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j813">
								<tr>
									<td class="dom">13</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 13, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j814">
								<tr>
									<td class="dom">14</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 14, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j815">
								<tr>
									<td class="dom">15</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 15, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j816">
								<tr>
									<td class="dom">16</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 16, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j817">
								<tr>
									<td class="dom">17</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 17, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j818">
								<tr>
									<td class="dom">18</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 18, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j819">
								<tr>
									<td class="dom">19</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 19, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j820">
								<tr>
									<td class="dom">20</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 20, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j821">
								<tr>
									<td class="dom">21</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 21, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j822">
								<tr>
									<td class="dom">22</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 22, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j823">
								<tr>
									<td class="dom">23</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 23, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j824">
								<tr>
									<td class="dom">24</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 24, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j825">
								<tr>
									<td class="dom">25</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 25, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j826">
								<tr>
									<td class="dom">26</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 26, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j827">
								<tr>
									<td class="dom">27</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 27, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j828">
								<tr>
									<td class="dom">28</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 28, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j829">
								<tr>
									<td class="dom">29</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 29, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j830">
								<tr>
									<td class="dom">30</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(9, 30, 2027); ?>
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


<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="pragma" content="no-cache" />
<meta http-equiv="CACHE-CONTROL" content="NO-CACHE" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="generator" content="Martha's Calendar Generator" />
<link rel="apple-touch-icon" href="/dfwh3-152x152.png" />
<title>June, 2027 Hash Events</title>
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
    $month=6;
    // Links are passed from Python for correct cross-year referencing
    $prev_link="$05-2027.php";
    $next_link="$07-2027.php";
    include 'php.php';
?>
</head>
<body>
<map name="Map" id="Map">
    <area shape="rect" coords="0,0,150,91" href="$05-2027.php" alt="Previous Month" />
    <!-- FIXED: Using coordinates 957,0,807,91 as requested for the Next Month button -->
    <area shape="rect" coords="957,0,807,91" href="$07-2027.php" alt="Next Month" />
</map>
<div class=container>
    <table class="overall"  border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <table class="banner" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><img src="month-06.png" alt="June"  border="0" usemap="#Map"/></td>
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
						<td class="day">
							<table class="inner" id="j51">
								<tr>
									<td class="dom">1</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 1, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j52">
								<tr>
									<td class="dom">2</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 2, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j53">
								<tr>
									<td class="dom">3</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 3, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j54">
								<tr>
									<td class="dom">4</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 4, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j55">
								<tr>
									<td class="dom">5</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 5, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j56">
								<tr>
									<td class="dom">6</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 6, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j57">
								<tr>
									<td class="dom">7</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 7, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j58">
								<tr>
									<td class="dom">8</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 8, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j59">
								<tr>
									<td class="dom">9</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 9, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j510">
								<tr>
									<td class="dom">10</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 10, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j511">
								<tr>
									<td class="dom">11</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 11, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j512">
								<tr>
									<td class="dom">12</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 12, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j513">
								<tr>
									<td class="dom">13</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 13, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j514">
								<tr>
									<td class="dom">14</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 14, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j515">
								<tr>
									<td class="dom">15</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 15, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j516">
								<tr>
									<td class="dom">16</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 16, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j517">
								<tr>
									<td class="dom">17</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 17, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j518">
								<tr>
									<td class="dom">18</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 18, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j519">
								<tr>
									<td class="holiday"><span class="tag">Juneteenth</span>19</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 19, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j520">
								<tr>
									<td class="dom">20</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 20, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j521">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #1</span>21</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 21, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j522">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #2</span>22</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 22, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j523">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #3</span>23</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 23, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j524">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #4</span>24</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 24, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j525">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #5</span>25</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 25, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j526">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #6</span>26</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 26, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="day">
							<table class="inner" id="j527">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #7</span>27</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 27, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j528">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #8</span>28</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 28, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j529">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #9</span>29</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 29, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="day">
							<table class="inner" id="j530">
								<tr>
									<td class="blue_bar"><span class="tag">EOP #10</span>30</td>
								</tr>
								<tr>
									<td class="event">
										<?php fillIn(6, 30, 2027); ?>
									</td>
								</tr>
							</table>
						</td>
						<td class="empty"></td>
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

<?php
session_start();
//echo $_SERVER['HOST'];
//echo 'sddd';
//echo $_SERVER['HTTP_HOST'];
//echo $_SERVER[REQUEST_URI];
//echo 'sd';
//echo $_SERVER['REMOTE_ADDR'];
//echo $hostip;
//echo '<script type="text/javascript">alert("It works.");</script>';
//$sortBy ="";
// determine host and allow localhost/127.0.0.1 for development
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$localAccess = (strpos($host, 'localhost') !== false) || (strpos($host, '127.0.0.1') !== false);

if ( $localAccess
    || (isset($_SESSION['userid']))
    || $host == '192.168.0.6'
    || $host == '192.168.1.101'
    || $host == '192.168.4.26'
    || $host == '192.168.2.178'
    || $host == '192.168.1.4' ){
    $navbar = 1;
    $logindisplay = 0;
    $sortBy = "";
    $username = isset($_SESSION["username"]) ? $_SESSION["username"] : 'local';
    $Role = isset($_SESSION["Role"]) ? $_SESSION["Role"] : 'Developer';
    if (isset($_SESSION['userid'])) $userid = $_SESSION["userid"]; else $userid = 0;
    if (isset($_GET["filterName"])) $filterNameParam = $_GET["filterName"]; else  $filterNameParam = "";
    if (isset($_GET["mh"])) $mh = $_GET["mh"]; else  $mh = "";
    if (isset($_GET["bh"])) $bh = $_GET["bh"]; else  $bh = "";
    if (isset($_GET["ph"])) $ph = $_GET["ph"]; else  $ph = "";
    if (isset($_GET["stDt"])) $filterStDt = $_GET["stDt"]; else  $filterStDt = "";
    if (isset($_GET["edDt"])) $filterEdDt = $_GET["edDt"]; else  $filterEdDt = "";
    if (isset($_GET["sortBy"])) $sortBy = $_GET["sortBy"]; else  $sortBy = "";
}
else {
    header ('Location: login.php');
}

// Use local docker DB if running on localhost for development
// handle hosts like 'localhost:8000' by using $localAccess
if ($localAccess) {
    if (file_exists(__DIR__ . '/connect_local.php')) {
        include(__DIR__ . '/connect_local.php');
    } elseif (file_exists(__DIR__ . '/connect.php')) {
        include(__DIR__ . '/connect.php');
    } else {
        // no connect file found; set $conn to null to avoid include warnings
        $conn = null;
    }
} else {
    if (file_exists(__DIR__ . '/connect.php')) {
        include(__DIR__ . '/connect.php');
    } elseif (file_exists(__DIR__ . '/connect_local.php')) {
        include(__DIR__ . '/connect_local.php');
    } else {
        $conn = null;
    }
}

// disable mysqli exceptions so missing tables won't throw fatal errors during development
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

function safe_query($conn, $sql) {
    if (!$conn) return false;
    return @mysqli_query($conn, $sql);
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>


</head>
<style>
.button {
  display: inline-block;
  padding: 15px 45px;
  font-size: 20px;
  cursor: pointer;
  text-align: center;
  text-decoration: none;
  outline: none;
  color: #fff;
  background-color: #4CAF50;
  border: none;
  border-radius: 15px;
  box-shadow: 0 5px #999;
}

.button:hover {background-color: #3e8e41}

.button:active {
  background-color: #3e8e41;
  box-shadow: 0 5px #666;
  transform: translateY(4px);
}


</style>
<script>
function sortBy(sortColumn){
	var winLoc = window.location.href;
	var sortBy = '<?php echo $sortBy; ?>';
	//alert(sortBy);
	if (sortBy == sortColumn && winLoc.indexOf("DESC")<0) sortColumn += " DESC";
	//alert (sortColumn);
	if (winLoc.indexOf("sortBy")>0) winLoc = removeSortBy(winLoc);
	if (winLoc.indexOf("?") < 0) {winLoc += "?";}
	else {winLoc += "&";}
	//alert(winLoc);
	winLoc += "sortBy=" + sortColumn;
	window.location = winLoc;
}
function removeSortBy(winLoc){
	var sLoc = winLoc.indexOf("sortBy");
	var newWinLoc = winLoc.substring(0,sLoc-1);
	//alert(newWinLoc);
	var sELoc = winLoc.substr(sLoc).indexOf("&");
	//alert(sLoc);alert(sELoc);
	if (sELoc <0) sELoc = 1000;
	newWinLoc += winLoc.substring(sELoc);
	//alert(newWinLoc);
	return newWinLoc;
}
function OpenBookingDetails(eventID){
	window.location="Charges.php?eventID="+eventID;
}
function OpenPaymentDetails(eventID){
	window.location="Payments.php?eventID="+eventID;
}
function EditBooking(eventID){
	window.location="EditBooking.php?eventID="+eventID;
}
function filterBookings(){
	//alert("fbook");
	var filterName = document.getElementById("filterName").value;
	var stDt = document.getElementById("filterStDt").value;
	var edDt = document.getElementById("filterEdDt").value;
	var ph="N";
	var mh="N";
	var bh="N"; //hallLocation="";
        if (document.getElementById("phLoc").checked) ph="Y";//hallLocation="partyHall";
        if (document.getElementById("mahalLoc").checked) mh="Y";//hallLocation+="Mahal";
        if (document.getElementById("BHLoc").checked) bh="Y";//hallLocation+="BeachHouse";
	window.location="listBookingsv2.php?filterName="+filterName+"&stDt="+stDt+"&edDt="+edDt+"&ph="+ph+"&mh="+mh+"&bh="+bh;
}
function IMinit(){
}
</script>
<body onload="IMinit()" >
<h1  text-align: right;>Bookings  <a href='logout.php'>logout</a> <a href='salaries.php' class='button' style='margin-left:12px;padding:8px 12px;font-size:14px;'>Salaries</a></h1>
<?php

///////////////////////calendar Week View/////////////////////////
$dt = new DateTime;
$todayDt = new DateTime;
if (isset($_GET['year']) && isset($_GET['week'])) {
    $dt->setISODate($_GET['year'], $_GET['week']);
} else {
    $dt->setISODate($dt->format('o'), $dt->format('W'));
}
$year = $dt->format('o');
$week = $dt->format('W');
$hallColorCd = "#0000FF";
echo "<a href=". $_SERVER['PHP_SELF']."?week=".($week-1)."&year=".$year.">Pre Week</a>";
echo "<a href=". $_SERVER['PHP_SELF']."?week=".($week+1)."&year=".$year.">Next Week</a>";
echo "&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp";
echo "<font color='" . $hallColorCd . "'>Party Hall</font>&nbsp&nbsp";
echo "<font color='#008000'>Mahal</font>&nbsp&nbsp";
echo "<font color='#FF643A'>Beach House</font>";

echo "<table border ='2' width = '100%'>
<thead>
<br>
    <tr>       ";
    do {
     echo "<th";
     if ($todayDt->format('d M Y')==$dt->format('d M Y')) echo " bgcolor='#FFC300' ";
     echo ">" . $dt->format('l') . "<br>" . $dt->format('d M') . "</th>\n";
     $dt->modify('+1 day');
    } while ($week == $dt->format('W'));
    echo "</tr></thead>";
    $hallAbbr = "PH";
    $dt->modify('-7 day');
    echo "<tr>";
    do {
      $sql = "select concat(lower(DATE_FORMAT(EventDate,'%h%p')),'-',lower(DATE_FORMAT(end,'%h%p'))) EventHrs, b.* from Bookings b where ";
      if (($ph=='Y') or ($bh=='Y') or ($mh=='Y') ){
        $sql = $sql . " replace(hallLocation,' ','') in(''";

        if( $ph=='Y') $sql = $sql . ",'partyHall'";
        if( $mh=='Y') $sql = $sql . ",'Mahal'";
        if( $bh=='Y') $sql = $sql . ",'BeachHouse'";

        $sql = $sql . ") and ";
      }

      $sql = $sql . " ( DATE_FORMAT(EventDate,'%Y-%m-%d') = '" .    $dt->format('Y-m-d') ."' or '" . $dt->format('Y-m-d') ."' between EventDate and end) order by EventDate";
//echo $sql;
      $result = mysqli_query($conn,$sql);
      echo "<td"; if ($todayDt->format('d M Y')==$dt->format('d M Y')) echo " bgcolor='#FFC300' "; echo ">";
      while($row = mysqli_fetch_array($result)){
	if ($row['hallLocation']=="partyHall") $hallColorCd = "#0000FF";
	elseif ($row['hallLocation']=="Mahal") $hallColorCd = "#008000";
	else $hallColorCd = "#FF643A";

	if ($row['hallLocation']=="partyHall") $hallAbbr = "PH";
	else if ($row['hallLocation']=="BeachHouse") $hallAbbr = "BH";
	else if ($row['hallLocation']=="Mahal") $hallAbbr = "M";

        echo "<br><a href='EditBooking.php?eventID=".$row['EventID']."'  style='color: " . $hallColorCd . "'>" . $row['Name'] . ":" . 
		$hallAbbr . " " .$row['EventHrs']. "</a><br>";
	$sqlCh = "SELECT * FROM Charges c, ChargeList cl where c.ChargeNo=cl.ChargeNo and  eventID= " 
		. $row['EventID'] . " and cl.ChargeName not in ('Rent', 'Security Deposit')";
      	$resultCh = mysqli_query($conn,$sqlCh);
	echo "<font color='" . $hallColorCd . "'>";
      	while($rowCh = mysqli_fetch_array($resultCh)){
		echo $rowCh['ChargeName'] . " " . $rowCh['comments'] . ", ";
	}
	echo "<u>Comments</u>:".$row['comments'];
	echo "</font>";
      }
      echo "</td>";
      $dt->modify('+1 day');
    } while ($week == $dt->format('W'));



//echo "<tr><td>". $dt ."</td></tr>";
echo "</tr></table>";

///////////////////////calendar Week View/////////////////////////


$currdate = new DateTime();
$currdate->modify('-10 day');

//$sql = "SELECT EventID, LOWER(DATE_FORMAT(EventDate,'%d/%m %l:%i%p')) EvtDt, Name,LOWER(DATE_FORMAT(end,'%d/%m %l%p')) end, Type,phone,
$sql = "SELECT EventID, replace(LOWER(DATE_FORMAT(EventDate,'%d/%m %l%p')),'m','') EvtDt, Name, replace(LOWER(DATE_FORMAT(end,'%d/%m %l%p')),'m','') end, Type,phone,
	LOWER(DATE_FORMAT(dateOfBooking,'%d/%m')) dob,BookingClosed,
        IFNULL((select sum(amount) from Charges c where c.eventid = b.eventid), 0) charges,
	IFNULL((select sum(c.amount) from ChargeList cl,Charges c where c.ChargeNo=cl.ChargeNo and c.eventid = b.eventid and cl.ChargeName='Security Deposit'), 0) SD,
        datediff(EventDate, now()) inXdays,
	EventDate between now() and (now() + INTERVAL 10 DAY) inTenDays, dateOfBooking<(now() - INTERVAL 1 MONTH) notBkdRcntly,
	(select count(*) from hist_Bookings h where h.eventid= b.eventid) changed,
         IFNULL((select sum(amount) from Payments p where p.eventid = b.eventid), 0) payments,
	hallLocation, refundAmount, pax, concat(SUBSTR(comments,1,30) , IF(length(comments) > 30, '....',''))  comments,
	eventDate ed FROM Bookings b";
$whereSet = false;
if( trim( $filterNameParam)!=='') {
	$whereSet = true;
	$sql = $sql . " where Name like '".$filterNameParam."%'";
}
if( trim( $filterStDt)!=='') {
	$filterStdate = $filterStDt;
	$filterEddate = $filterEdDt;
}
else{
	$filterStdate = $currdate->format('Y-m-d');
	//$currdate->modify('+200 day');
	$currdate->modify('-1 day');
	$filterEddate = $currdate->format('Y-m-d');
	//$currdate->modify('-130 day');
	$currdate->modify('-1 day');
}
if (($ph=='Y') or ($bh=='Y') or ($mh=='Y') ){
	if ($whereSet)$sql = $sql . " and ";
	else $sql = $sql . " where ";
	$whereSet = true;
	$sql = $sql . " replace(hallLocation,' ','') in(''";

  	if( $ph=='Y') $sql = $sql . ",'partyHall'";
  	if( $mh=='Y') $sql = $sql . ",'Mahal'";
  	if( $bh=='Y') $sql = $sql . ",'BeachHouse'";

	$sql = $sql . ") ";
}
if ($whereSet)$sql = $sql . " and ";
else $sql = $sql . " where ";
$sql = $sql . " EventDate between '". $filterStdate. "' and '".$filterEddate."'";
//if ($Role !== "Admin") {
	//echo "tesdt";
//	$sql  = $sql . " and EventDate > Now() - INTERVAL 40 day ";
//}
if( trim( $sortBy)!=='') {
//	echo "test";
	$sql = $sql . " ORDER BY " . $sortBy;
//	echo $sql;
}
else {
	$sql = $sql. " order by ed";
}
//echo $filterStdate;
//echo $sql;
//echo $Role;
//echo "  <p2><iframe id='iframeLogs' onload='iframeclick()' name='myiframe' src='bookingsCalV.php' allowfullscreen='true' frameborder='0' height='200' width='100%'></iframe></p2>";

//echo $sql;
echo " <table><tr><td> <strong><table border='1'><tr><td>Name:<input type='text' id='filterName' value='".$filterNameParam."' size=5>";
echo "   PH<input type='checkbox' name='phloc' id = 'phLoc'";
if( trim( $ph)=="Y") echo " checked = true";
echo ">         MH   <input type='checkbox' name='mhloc' id = 'mahalLoc'";
if( trim( $mh)=="Y") echo " checked = true";
echo ">         BH   <input type='checkbox' name='bhloc' id = 'BHLoc'";
if( trim( $bh)=="Y") echo " checked = true";
//echo "></td></tr><tr> <td>Filter Dates: <input type='date' id='filterStDt' value='".$filterStdate."'><br>
//                 <input type='date' id='filterEdDt' value='".$filterEddate."'></td>
//  </tr></table></strong></td>
//<td>   <button class='button' onclick = 'filterBookings()' >Filter</button></td> ";

echo "></td></tr><tr> <td><table><tr><td>Filter Dates:</td> <td><input type='date' id='filterStDt' value='".$filterStdate."'></td></tr>
                 <tr><td></td><td><input type='date' id='filterEdDt' value='".$filterEddate."'></td></tr></table></td>
  </tr><tr><td>   <button class='button' onclick = 'filterBookings()' >Filter</button></td></tr> 
</table></strong></td>
";

//Decor Discussions to be done:
$sqldd = "SELECT b.eventid EventID, DATE_FORMAT(nxtDecDate,'%d/%m') nxtDecDate, CASE
    WHEN decFinSts = 'NotFinalized' THEN 'NF'
    WHEN decFinSts = 'Finalized' THEN 'F'
    WHEN decFinSts = 'OutsideDecor' THEN 'O'
    ELSE '??'
END decFinSts, DATE_FORMAT(EventDate,'%d/%m %h%p') evtDt, Name, CASE
    WHEN hallLocation = 'Mahal' THEN 'MH'
    WHEN hallLocation = 'partyHall' THEN 'PH'
    WHEN hallLocation = 'BeachHouse' THEN 'BH'
    WHEN hallLocation = 'MahalAK' THEN 'MH'
    WHEN hallLocation = 'MahalAKsmall' THEN 'MS'
    WHEN hallLocation = 'MahalAKbig' THEN 'MB'
    ELSE '??'
END loc FROM Bookings b, DecorDetails dd WHERE b.eventid = dd.eventid and nxtDecDate between now()- INTERVAL 1 DAY and now() + INTERVAL 1 MONTH ORDER BY nxtDecDate";
$result = mysqli_query($conn,$sqldd);
echo "<td> <table  bgcolor='#FF5733'><td>Decor Discussion Followup Dates:</td></table>
<table border='1'><tr><th>DiscDt</th><th>St</th><th>Event</th><th>Name</th><th>Loc</th></tr>";
while($row = mysqli_fetch_array($result)){
echo "<tr";
$due =  0;
if (($row["loc"] == "PH" and $due > 3000) or $due> 7000) echo "  bgcolor='#FF0000'";
//FF963A
echo ">";
echo "<td>".$row["nxtDecDate"]."</td>";
echo "<td>".$row["decFinSts"]."</td>";
echo "<td>".$row["evtDt"]."</td>";
$nm = $row["Name"];
if(strlen($nm)>11) $nm = substr($nm,0,9)."...";
echo "<td><a href='EditBooking.php?eventID=".$row['EventID']."'>".$nm."</td>";
echo "<td>" .$row["loc"]. "</td>";
echo "</tr>";
}
echo "</tr></table></td>";
//Payments past due:
$sqlpd = "select EventID, (ChAmt - PAmt) due, evtDt, Name, loc from ( SELECT b.EventID, DATE_FORMAT(EventDate,'%d/%m %h%p') evtDt, Name, 
(select sum(c.Amount) from ChargeList cl,Charges c where c.ChargeNo=cl.ChargeNo and b.eventid = c.eventid and cl.ChargeName <> 'Security Deposit') ChAmt, 
(select sum(p.Amount) from Payments p where b.eventid = p.eventid) PAmt, CASE
    WHEN hallLocation = 'Mahal' THEN 'MH'
    WHEN hallLocation = 'partyHall' THEN 'PH'
    WHEN hallLocation = 'BeachHouse' THEN 'BH'
    WHEN hallLocation = 'MahalAK' THEN 'MH'
    WHEN hallLocation = 'MahalAKsmall' THEN 'MS'
    WHEN hallLocation = 'MahalAKbig' THEN 'MB'
    ELSE '??'
END loc FROM Bookings b where eventDate between now() and now() + INTERVAL 7 DAY and hallLocation <> 'BeachHouse') bp
where ChAmt > PAmt
order by evtDt";
$result = mysqli_query($conn,$sqlpd);
echo "<td> <table  bgcolor='#FF0000'><td>Payments Past Due:</td></table><table border='1'><tr><th>Event</th><th>Amount</th><th>Name</th><th>Loc</th></tr>";
while($row = mysqli_fetch_array($result)){
echo "<tr";
$due =  $row["due"];
if (($row["loc"] == "PH" and $due > 3000) or $due> 7000) echo "  bgcolor='#FF0000'";
//FF963A
echo ">";
echo "<td>".$row["evtDt"]."</td>";
echo "<td  align='right'>&#8377;". number_format($due)."</td>";
$nm = $row["Name"];
if(strlen($nm)>11) $nm = substr($nm,0,9)."...";
echo "<td><a href='EditBooking.php?eventID=".$row['EventID']."'>".$nm."</td>";
echo "<td>" .$row["loc"]. "</td>";
echo "</tr>";
}
echo "</tr></table></td>";

//Payment Dues this week: <a href='EditBooking.php?eventID=".$row['EventID']."'
$sqlpd = "select DATE_FORMAT(EventDate,'%d/%m %h%p') evtDt, Name,
CASE
    WHEN hallLocation = 'Mahal' THEN 'MH'
    WHEN hallLocation = 'partyHall' THEN 'PH'
    WHEN hallLocation = 'BeachHouse' THEN 'BH'
    WHEN hallLocation = 'MahalAK' THEN 'MH'
    WHEN hallLocation = 'MahalAKsmall' THEN 'MS'
    WHEN hallLocation = 'MahalAKbig' THEN 'MB'
    ELSE '??'
END loc, pd.*, DATE_FORMAT(DueDate,'%d/%m') DueDt from PaymentDueDates pd, Bookings b 
where b.name not like 'Cancelled%' and pd.eventid = b.eventid and EventDate > now() + INTERVAL 10 DAY
 and DueDate < now() + INTERVAL 10 DAY and pd.paidFlag = 'N' order by DueDate
LIMIT 0,15 "; 
//DueDate between now() - INTERVAL 5 DAY and now() + INTERVAL 10 DAY
$result = mysqli_query($conn,$sqlpd);
echo "<td>  Scheduled Payments This week:<table border='1'><tr><th>DueDt</th><th>Amount</th><th>Event</th><th>Name</th><th>Loc</th></tr>";
while($row = mysqli_fetch_array($result)){
echo "<tr ";

if ($row["Amount"]>99000 ) echo " bgcolor='#FF0000'";
//" bgcolor='#FF963A' //Orange
//0000FF// blue
//FF0000// red
echo "><td>".$row["DueDt"]."</td>";
echo "<td  align='right'> &#8377;".number_format($row["Amount"])."</td>";
echo "<td>".$row["evtDt"]."</td>";
$nm = $row["Name"];
if(strlen($nm)>11) $nm = substr($nm,0,9)."...";
echo "<td><a href='EditBooking.php?eventID=".$row['EventID']."'>".$nm."</td>";
echo "<td>".$row["loc"]."</td>";
echo "</tr>";
}
echo "</tr></table></td>";

//main Query:
//echo $sql;
$result = safe_query($conn,$sql);

echo "<tr><br><table class='js-table' style='outline: thin solid' border='1'
> <thead>
<br>
<tr >
<th>Location</th>
<th><a href=\"javascript:sortBy('EventDate')\">Start</th>
<th>End</th>
<th><a href=\"javascript:sortBy('Name')\">Client Name</a></th>
<th>Phone</th>
<th><a href=\"javascript:sortBy('Type')\">Type</a></th>
<th>PAX</th>
<th><a href=\"javascript:sortBy('DateOfBooking')\">BkDt</a></th>
<th>Charges</th>
<th>Payments</th>
<th>Bal Due</th>
<th>SD</th>
<th>SDRef</th>
<th>Comments</th>
<th>Details</th>
</tr></thead><tbody>";
$cf = new DateTime();
// echo date( "Y-m-d H:i:s", $cf);
echo $cf->format("Y-m-d H:i:s");
if ($result) {
    $rowsToIterate = [];
    while($r = mysqli_fetch_array($result)) { $rowsToIterate[] = $r; }
} else {
    // Provide sample data when DB not available so UI can render for development
    $rowsToIterate = [];
    $sampleDate = new DateTime();
    $rowsToIterate[] = [
        'EventID' => 1,
        'hallLocation' => 'partyHall',
        'EvtDt' => $sampleDate->format('d/m') . ' 6pm',
        'end' => '10pm',
        'Name' => 'Sample Client A',
        'phone' => '9999000001',
        'Type' => 'Wedding',
        'pax' => 100,
        'dob' => $sampleDate->format('d/m'),
        'charges' => 20000,
        'payments' => 5000,
        'SD' => 2000,
        'refundAmount' => 0,
        'comments' => 'Sample booking A',
        'changed' => 0,
        'BookingClosed' => 0,
        'inXdays' => 2,
        'inTenDays' => 1,
        'notBkdRcntly' => 0,
        'eventDate' => $sampleDate->format('Y-m-d')
    ];
    $sampleDate2 = new DateTime(); $sampleDate2->modify('+5 day');
    $rowsToIterate[] = [
        'EventID' => 2,
        'hallLocation' => 'Mahal',
        'EvtDt' => $sampleDate2->format('d/m') . ' 4pm',
        'end' => '9pm',
        'Name' => 'Sample Client B',
        'phone' => '9999000002',
        'Type' => 'Conference',
        'pax' => 50,
        'dob' => $sampleDate2->format('d/m'),
        'charges' => 15000,
        'payments' => 15000,
        'SD' => 1500,
        'refundAmount' => 0,
        'comments' => 'Sample booking B',
        'changed' => 1,
        'BookingClosed' => 0,
        'inXdays' => 6,
        'inTenDays' => 0,
        'notBkdRcntly' => 0,
        'eventDate' => $sampleDate2->format('Y-m-d')
    ];
}

// iterate rowsToIterate instead of raw mysqli result
foreach ($rowsToIterate as $row) {
    // original loop body follows, unchanged except variable names
    echo "<tr";
    if (isset($row['BookingClosed']) && $row['BookingClosed']==1) echo " bgcolor='#808080'";//grey for closed bookings
    else if ((isset($row['charges'])?($row['charges']- $row['payments']):0)> 0){
        if ( isset($row['inTenDays']) && $row['inTenDays']==1 && isset($row['notBkdRcntly']) && $row['notBkdRcntly'] ==1) echo " bgcolor='#FF0000'";//delayed payments
        else if (isset($row['inXdays']) && $row['inXdays']>0 && $row['inXdays']<2) echo " bgcolor='#FF0000'";
        else if (isset($row['inXdays']) && $row['inXdays']>2 && $row['inXdays']<7) echo " bgcolor='#FF963A'";
    }
    else echo " bgcolor='#5AFF3A'";//green for no dues
    echo "><td ";
    if (isset($row['hallLocation']) && $row['hallLocation']=="Mahal") echo "bgcolor='#008000'";
    if (isset($row['hallLocation']) && $row['hallLocation']=="partyHall") echo "bgcolor='#0000FF'";
    if (isset($row['hallLocation']) && ($row['hallLocation']=="Beach House" || $row['hallLocation']=="BeachHouse")) echo "bgcolor='#FF643A'";
    echo ">" . (isset($row['hallLocation'])?$row['hallLocation']:'') . "</td>";
    echo "<td>" . (isset($row['EvtDt'])?$row['EvtDt']:'') . "</td>";
    echo "<td>" . (isset($row['end'])?$row['end']:'') . "</td>";
    echo "<td><a href='EditBooking.php?eventID=".(isset($row['EventID'])?$row['EventID']:'')."'>" . substr((isset($row['Name'])?$row['Name']:''),0,21) . "</a></td>";
    echo "<td>" . (isset($row['phone'])?$row['phone']:'') . "</td>";
    echo "<td>" . (isset($row['Type'])?$row['Type']:'') . "</td>";
    echo "<td>" . (isset($row['pax'])?$row['pax']:'') . "</td>";
    echo "<td>" . (isset($row['dob'])?$row['dob']:'') . "</td>";
    echo "<td  align='right'><a href='Charges.php?eventID=".(isset($row['EventID'])?$row['EventID']:'')."'> &#8377;" . number_format((isset($row['charges'])?$row['charges']:0)) . "</a></td>";
    echo "<td  align='right'><a href='Payments.php?eventID=".(isset($row['EventID'])?$row['EventID']:'')."'> &#8377;" .number_format((isset($row['payments'])?$row['payments']:0)) . "</a></td>";
    echo "<td  align='right'> &#8377;" . number_format(((isset($row['charges'])?$row['charges']:0)- (isset($row['payments'])?$row['payments']:0))) . "</td>";
    echo "<td  align='right'> &#8377;" . number_format((isset($row['SD'])?$row['SD']:0)) . "</td>";
    echo "<td  align='right'> &#8377;" . number_format((isset($row['refundAmount'])?$row['refundAmount']:0)) . "</td>";
    echo "<td>" . (isset($row['comments'])?$row['comments']:'') . "</td>";
    echo "<td><a href='details.php?eventID=".(isset($row['EventID'])?$row['EventID']:'')."' style='text-decoration:none;padding:6px 10px;background:#4CAF50;color:#fff;border-radius:6px;'>Post Entry</a></td>";
    if (isset($row['changed']) && $row['changed']>0)
           echo "<td><button onclick='window.location = `BookingHist.php?EventID=".(isset($row['EventID'])?$row['EventID']:'')."`'>Changes</button></td>";
    echo "</tr>";
}

echo "</table></table>";
mysqli_close($conn);
?>
<table style="width:100%">
  <tr>
    <td><button class="button" onclick = "window.location='EditBooking.php'" >New Booking</button></td>
  </tr>
</table>
<?php
echo "<br>User: ".$username;
?>

</body>
</html>


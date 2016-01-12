<?php
//Written by Craig Newbury.
//Code is quite messy but I am still learning.
//I am releasing this code as open source.
//if you can learn anything from it all the better.

//flights.pdf
require 'fpdf181/fpdf.php';

//check if a terminal paramater was provided to the script when requested, if not set $requestedTerminal to "NONE"
if (isset($_GET["terminal"]) == false) {
	$requestedTerminal = "NONE";
} else {
	$requestedTerminal = $_GET['terminal'];
}

//if a valid paramater was passed to the script then set $requestedTerminal.
if ($requestedTerminal == 'n' || $requestedTerminal == 'N') {
	$requestedTerminal = 'North';
} elseif ($requestedTerminal == 's' || $requestedTerminal == 'S') {
	$requestedTerminal = 'South';
}
// get HTML from Gatwick webpage via cUrl function
$HTML = getHTML();
//Extract table from curl response
$tableStartPos = strpos($HTML, '<tbody>');
$tableEndPos = strpos($HTML, '</tbody>');

//check if there is a 2nd table of flight data
if (strpos($HTML, '</tbody>', $tableEndPos + 8) !== FALSE) {

	//if so replace current tableEndPos with new tableEndPos
	$tableEndPos = strpos($HTML, '</tbody>', $tableEndPos + 8);
}

//set length of table(s) to extract html data
$tableLength = $tableEndPos - $tableStartPos;

//output table for next block to process
$flightTable = substr($HTML, $tableStartPos, $tableLength);

//creat empty array for flight data
$flightData = [];

//find position of date in 1st table
$firstTableDate = strpos($flightTable, '<th colspan=') + 16;
$firstTableDateEnd = strpos($flightTable, '</th>', $firstTableDate);
$firstTableDateLen = $firstTableDateEnd - $firstTableDate;

//insert date as first record in flight data array
$flightData[] = array(substr($flightTable, $firstTableDate, $firstTableDateLen), "", "", "");

//check if there is a row of data to be extracted
while (strpos($flightTable, '<tr data-flight-time=') != FALSE) {

	//Extract next row of flight data
	$nextFlightStartPos = strpos($flightTable, '<tr data-flight-time=');
	$nextFlightEndPos = strpos($flightTable, '</tr>', $nextFlightStartPos);
	$nextFlightTextRange = $nextFlightEndPos - $nextFlightStartPos;
	$currentFlightDetails = substr($flightTable, $nextFlightStartPos, $nextFlightTextRange);

	//$headerAndImageStartPos = strpos($flightTable, '<tr data-flight-time='); //probably not needed we already have start position
	$headerAndImageEndPos = strpos($currentFlightDetails, '</td>');
	$currentFlightDetails = substr($currentFlightDetails, $headerAndImageEndPos + 5);

	//remove unwanted HTML
	$currentFlightDetails = str_replace(' ', '', $currentFlightDetails);
	$currentFlightDetails = str_replace("\n", '', $currentFlightDetails);
	$currentFlightDetails = str_replace("<tdclass='notifiable'>&nbsp;</td>", '', $currentFlightDetails);

	//Extract time sting position
	$timeStartPos = strpos($currentFlightDetails, '<td>') + 4;
	$timeEndPos = strpos($currentFlightDetails, '</td>');

	//Extract destination airport sting position
	$airportStartPos = strpos($currentFlightDetails, '<td>', $timeEndPos) + 4;
	$airportEndPos = strpos($currentFlightDetails, '</td>', $airportStartPos);

	//Extract flight number sting position
	$flightNumberStartPos = strpos($currentFlightDetails, '<td>', $airportEndPos) + 4;
	$flightNumberEndPos = strpos($currentFlightDetails, '</td>', $flightNumberStartPos);

	//Extract flight status sting position, this is just for refrence for the next 2 lines so it can skip flight status
	$statusEndPos = strpos($currentFlightDetails, '</td>', $flightNumberEndPos + 5);

	//Extract terminal sting position
	$terminalStartPos = strpos($currentFlightDetails, '<td>', $statusEndPos) + 4;
	$terminalEndPos = strpos($currentFlightDetails, '</td>', $terminalStartPos);

	//add extracted data to array ready to pass to PDF generation
	$flightTime = substr($currentFlightDetails, $timeStartPos, $timeEndPos - $timeStartPos);//Time
	$flightDest = substr($currentFlightDetails, $airportStartPos, $airportEndPos - $airportStartPos);//Airport
	$flightNum = substr($currentFlightDetails, $flightNumberStartPos, $flightNumberEndPos - $flightNumberStartPos);//Flight Number
	$flightTerm = substr($currentFlightDetails, $terminalStartPos, $terminalEndPos - $terminalStartPos);//terminal

	//remove the flight data for the row we have just processed ready for next loop iteration
	$flightTable = substr($flightTable, $nextFlightEndPos + 5);

	//Add flight to array depending on script terminal paramater
	if ($flightTerm == $requestedTerminal || $requestedTerminal == "NONE") {
		//add flight data we have just parsed to the flightData array
		$flightData[] = array($flightTime, $flightDest, $flightNum, $flightTerm);
	}

	//check if there is another date in the source data
	if (strpos($flightTable, '<th colspan') !== FALSE){

		//find position of next flight data in tabe
		$closestFlight = strpos($flightTable, '<tr data-flight-time=');

		//find position of next date in table (16 added for later use as a string start position)
		$closestDateStart = strpos($flightTable, '<th colspan=') + 16;

		//find position of date end
		$closestDateEnd = strpos($flightTable, "</th>", $closestDateStart);

		//calculate lengeth of date in string
		$closestDateLen = $closestDateEnd - $closestDateStart;

		//is there a date or flight next in the table
		if ($closestDateStart < $closestFlight) {
				//if so extract date and add to array
				$secondTableDate = substr($flightTable, $closestDateStart, $closestDateLen);
				$flightData[] = array($secondTableDate, "", "", "");
		}
	}
}


class PDF extends FPDF {

	// Colored table
	function FancyTable($header, $data)
	{
    	// Colors, line width and bold font
    	$this->SetFillColor(255,0,0);
    	$this->SetTextColor(255);
    	$this->SetDrawColor(128,0,0);
    	$this->SetLineWidth(.3);
    	$this->SetFont('','B');
    	// Header
    	$w = array(48, 48, 48, 48);
    	for($i=0;$i<count($header);$i++)
        	$this->Cell($w[$i],7,$header[$i],1,0,'C',true);
    	$this->Ln();
    	// Color and font restoration
    	$this->SetFillColor(224,235,255);
    	$this->SetTextColor(0);
    	$this->SetFont('');
    	// Data
    	$fill = false;
    	foreach($data as $row) {
        	$this->Cell($w[0],6,$row[0],'LR',0,'C',$fill);
        	$this->Cell($w[1],6,$row[1],'LR',0,'C',$fill);
        	$this->Cell($w[2],6,$row[2],'LR',0,'C',$fill);
        	$this->Cell($w[3],6,$row[3],'LR',0,'C',$fill);
        	$this->Ln();
        	$fill = !$fill;
    	}
    	// Closing line
    	$this->Cell(array_sum($w),0,'','T');
	}
}

$pdf = new PDF();
// Column headings
$header = array('Time', 'Destination', 'Flight Number', 'Terminal');
// Data loading

//pass flight data to PDF library
$data = $flightData;

$pdf->SetFont('Arial','',14);
$pdf->AddPage();
$pdf->FancyTable($header,$data);
$pdf->Output();


//cUrl code placed in function to neaten up code
function getHTML() {
	// create curl resource
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, "http://www.gatwickairport.com/flights/?type=departures");

    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // $output contains the output string
    $output = curl_exec($ch);

	// close curl resource to free up system resources
    curl_close($ch);
	return $output;
}
?>

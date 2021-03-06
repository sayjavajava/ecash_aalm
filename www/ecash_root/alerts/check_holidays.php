<?php
try 
{

	$notify_list = "rebel75cell@gmail.com, brian.gillingham@gmail.com, randy.klepetko@sbcglobal.net";

	// If event is less than this time period, then mark it as critical
	$critical_period = strtotime("+3 days");

	require_once(dirname(__FILE__)."/../www/config.php");

	$st = ECash::getMasterDb();

	$critical = 0;
	$events   = array();

	$query = "
    	SELECT  es.application_id,
    	       	c.name_short as company,
                es.event_schedule_id,
                es.date_event,
                es.date_effective,
                es.amount_principal as principal,
                es.amount_non_principal as non_principal
        FROM    holiday h,
                event_schedule es,
                company c
        WHERE   es.event_status = 'scheduled'
        AND     c.company_id = es.company_id
        AND     h.holiday > curdate()
        AND     (h.holiday = es.date_event
        OR       h.holiday = es.date_effective)
        ORDER BY date_event
   ";
	$st = $db->query($query);

	while ($row = $st->fetch(PDO::FETCH_OBJ))
	{
		// I'm putting the rows in arrays in case I want to use
		// the information later on.
		if(strtotime($row->date_event) < $critical_period)
			$critical++;
		$events[] = $row;
	}

	$non_critical = (count($events) - $critical);

	// Critical errors exit with 2, other errors with 1
	if($critical > 0) 
	{
		$return_value = 2;
	} 
	else if ($non_critical > 0) 
	{
		$return_value = 1;
	}
	else 
	{
		// All quiet on the western front
		exit(0);
	}

	$subject = "eCash: Found events on holidays  {$critical} critical and {$non_critical} non-critical events.";
	echo $subject . "\n";
	Email_Report($notify_list, $subject, $events);
	exit($return_value);

} catch(Exception $e) {
	echo "check_events: Unknown error occurred.\n";
	exit(3);
}

function Email_Report($recipients, $body, $results)
{
	require_once(LIB_DIR . '/CsvFormat.class.php');

	$csv = CsvFormat::getFromArray(array(
		'Application ID',
		'Company',
		'Event ID',
		'Event Date',
		'Effective Date',
		'Principal',
		'Non-Principal'));

	$co = array(); //mantis:7727

	foreach ($results as $result)
	{
		$csv .= CsvFormat::getFromArray(array(
			$result->application_id,
			$result->company,
			$result->event_schedule_id,
			$result->date_event,
			$result->date_effective,
			$result->principal,
			$result->non_principal));

			//mantis:7727
		if(!in_array($result->company, $co))
			$co[] = $result->company;
	}

	$subject = 'Ecash Alert '. strtoupper(implode(",", $co)); //mantis:7727

	$attachments = array(
		array(
			'method' => 'ATTACH',
			'filename' => 'alert_holidays.csv',
			'mime_type' => 'text/plain',
			'file_data' => gzcompress($csv),
			'file_data_length' => strlen($csv)));

	require_once(LIB_DIR . '/Mail.class.php');
	return eCash_Mail::sendExceptionMessage($recipients, $body, $subject, array(), $attachments); //mantis:7727 - $subject
}

?>

<?php
/**
 * @package Reporting
 *
 * @copyright Copyright &copy; 2006 The Selling Source, Inc.
 *
 * @version $Revision: 1.1.2.1 $
 */

require_once("report_generic.class.php");

class Report extends Report_Generic
{
	private $search_query;

	public function Generate_Report()
	{
		// Generate_Report() expects the following from the request form:
		//
		// criteria start_date YYYYMMDD
		// criteria end_date   YYYYMMDD
		// company_id
		//
		try
		{
			$this->search_query = new Current_Weekly_Summary_Report_Query($this->server);
	
			$data = new stdClass();
	
			// Save the report criteria
			$data->search_criteria = array(
			  'start_date_MM'   => $this->request->start_date_month,
			  'start_date_DD'   => $this->request->start_date_day,
			  'start_date_YYYY' => $this->request->start_date_year,
              'end_date_MM'   	=> $this->request->end_date_month,
              'end_date_DD'   	=> $this->request->end_date_day,
              'end_date_YYYY' 	=> $this->request->end_date_year,
			  'company_id'      => $this->request->company_id
			);
	
			$_SESSION['reports']['current_weekly_summary']['report_data'] = new stdClass();
			$_SESSION['reports']['current_weekly_summary']['report_data']->search_criteria = $data->search_criteria;
			$_SESSION['reports']['current_weekly_summary']['url_data'] = array('name' => 'Weekly Summary Report', 'link' => '/?module=reporting&mode=current_weekly_summary');
	
			// Start date
			$start_date_YYYY = $this->request->start_date_year;
			$start_date_MM	 = $this->request->start_date_month;
			$start_date_DD	 = $this->request->start_date_day;
			if(!checkdate($start_date_MM, $start_date_DD, $start_date_YYYY))
			{
				//return with no data
				$data->search_message = "Start Date invalid or not specified.";
				ECash::getTransport()->Set_Data($data);
				ECash::getTransport()->Add_Levels("message");
				return;
			}
	
			$start_date_YYYYMMDD = 10000 * $start_date_YYYY	+ 100 * $start_date_MM + $start_date_DD;

            // End date
            $end_date_YYYY = $this->request->end_date_year;
            $end_date_MM   = $this->request->end_date_month;
            $end_date_DD   = $this->request->end_date_day;
            if(!checkdate($end_date_MM, $end_date_DD, $end_date_YYYY))
            {
                //return with no data
                $data->search_message = "End Date invalid or not specified.";
                ECash::getTransport()->Set_Data($data);
				ECash::getTransport()->Add_Levels("message");
                return;
            }

            $end_date_YYYYMMDD = 10000 * $end_date_YYYY + 100 * $end_date_MM + $end_date_DD;

	
			$data->search_results = $this->search_query->Fetch_Current_Weekly_Summary_Data( $start_date_YYYYMMDD,
																				   $end_date_YYYYMMDD,
				                                                                   $this->request->company_id);
		}
		catch (Exception $e)
		{
			$data->search_message = "Unable to execute report. Reporting server may be unavailable.";
			ECash::getTransport()->Set_Data($data);
			ECash::getTransport()->Add_Levels("message");
			return;
		}

		// we need to prevent client from displaying too large of a result set, otherwise
		// the PHP memory limit could be exceeded;
		$num_results = 0;
		foreach ($data->search_results as $company => $results)
		{
			$num_results += count($results);

			if ($num_results > $this->max_display_rows)
			{
				$data->search_message = "Your report would have more than " . $this->max_display_rows . " lines to display. Please narrow the date range.";
				ECash::getTransport()->Set_Data($data);
				ECash::getTransport()->Add_Levels("message");
				return;
			}			
		}

		// Sort if necessary
		$data = $this->Sort_Data($data);

		ECash::getTransport()->Add_Levels("report_results");
		ECash::getTransport()->Set_Data($data);
		$_SESSION['reports']['current_weekly_summary']['report_data'] = $data;
	}
}

class Current_Weekly_Summary_Report_Query extends Base_Report_Query
{
	private static $TIMER_NAME    = "Current Weekly Summary Report Query";

	public function __construct(Server $server)
	{
		parent::__construct($server);
	}

	/**
	 * Fetches data for the Current Lead Status Report
	 * @param   string $start_date YYYYmmdd
	 * @param   string $end_date   YYYYmmdd
	 * @param   mixed  $company_id array of company_ids or 1 company_id
	 * @returns array
	 */
	public function Fetch_Current_Weekly_Summary_Data($start_date, $end_date, $company_id)
	{
		$this->timer->startTimer(self::$TIMER_NAME);

		// Search from the beginning of start date to the end of end date
		$end_date   = "{$end_date}235959";
		$start_date = "{$start_date}000000";

		if(isset($_SESSION) && is_array($_SESSION['auth_company']['id']) && count($_SESSION['auth_company']['id']) > 0)
		{
			$auth_company_ids = $_SESSION['auth_company']['id'];
		}
		else
		{
			$auth_company_ids = array(-1);
		}

		if( $company_id > 0 )
			$company_list = "'{$company_id}'";
		else
			$company_list = "'" . implode("','", $auth_company_ids) . "'";


		// Here's the plan
		// get all status history that have been pending from start date to end date
		// join 
		$query = "
			-- eCash 3.0, File: " . __FILE__ . ", Method: " . __METHOD__ . ", Line: " . __LINE__ . "
			SELECT
				sh.company_id                                                                      AS company_id,
				UPPER(co.name_short)                                                               AS company,
				CONCAT(
					DATE_FORMAT(DATE(DATE_ADD((DATE_SUB(sh.date_created, INTERVAL (DAYOFWEEK(sh.date_created)) DAY)), INTERVAL 1 DAY)),'%m/%d'),
					'-',
					DATE_FORMAT(DATE(DATE_ADD(sh.date_created, INTERVAL (7 - DAYOFWEEK(sh.date_created)) DAY)), '%m/%d')
				)                                                                                  AS week_bought,
				COUNT(DISTINCT sh.application_id)                                                  AS num_bought,
				SUM(
					IF(tr.transaction_status='complete' AND amount < 0,
					   ABS(amount),
					   0)
				)                                                                                  AS deposits_amt,
				SUM(
					IF(tt.name_short LIKE '%disbursement'
					   AND  (	tr.transaction_status='complete'
							OR
								tr.transaction_status='pending'
							),
						tr.amount,
						0
					)
				)                                                                                  AS funded_amt,
				SUM(
					IF(
							/* Why am I doing this? because this report would count this stat multiple times */
							tt.name_short LIKE '%disbursement'
						AND
						(
							ass.level0 = 'customer'
						OR	
							ass.level1 = 'customer'
						OR
							ass.level2 = 'customer'
						OR
							ass.level3 = 'customer')
						,
						1,
						0
					)
				)                                                                                  AS num_funded,
				SUM(
					IF(tt.name_short LIKE '%disbursement'
					   AND (	tr.transaction_status='complete'
							OR
								tr.transaction_status='pending'
							),
						tr.amount,
						0
					)
				)                                                                                  AS disbursements_amt

				
			FROM
				status_history sh
			JOIN
				application app ON (app.application_id = sh.application_id)
			JOIN
				application_status_flat ass ON (ass.application_status_id = app.application_status_id)
			JOIN
				application_status hss ON (hss.application_status_id = sh.application_status_id)
			LEFT JOIN
				transaction_register tr ON (tr.application_id = app.application_id)
			LEFT JOIN
				transaction_type tt ON (tr.transaction_type_id = tt.transaction_type_id)
			JOIN
				company co ON (co.company_id = sh.company_id)
			WHERE
				hss.name='Pending'
			AND
				sh.company_id IN ({$company_list})
			AND
				sh.date_created BETWEEN '{$start_date}' AND '{$end_date}'
			GROUP BY
				sh.company_id, week_bought
		";

		$data = array();

		$fetch_result = $this->db->query($query);

		while( $row = $fetch_result->fetch(PDO::FETCH_ASSOC))
		{
			$data[$row['company']][] = $row;
		}


		$this->timer->stopTimer(self::$TIMER_NAME);

		return $data;
	}
}

?>

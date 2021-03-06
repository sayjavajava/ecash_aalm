<?php

require_once("dropdown.1.generic.php");
require_once("state_selection.2.php");
require_once("dropdown_dates.1.php");
require_once("layout.class.php");
require_once("crypt.3.php");
require_once("build_display.class.php");
require_once(LIB_DIR . "Payment_Card.class.php");
require_once(CLIENT_CODE_DIR . "display_parent.abst.php");
require_once(CLIENT_CODE_DIR . "display_utility.class.php");
require_once(CLIENT_CODE_DIR . 'FraudColumns.php');


class Display_View extends Display_Parent
{
	private $layouts;
	private $display_layers;
	private $display_modes;
	private $layout_map;
	private $refi_qual_ach_pmt_types;

	public function __construct(ECash_Transport $transport, $module_name, $mode)
	{
	   parent::__construct($transport, $module_name, $mode);

		$this->data->name = true; // Hack, allows both first and last name to use the same color/overlink stuff.
		$this->data->previous_module = "";
		$this->data->current_module = $module_name;
		$this->data->current_mode = $mode;
                $this->refi_qual_ach_pmt_types = array(28, 29, 296, 24, 25, 22, 23, 35, 12, 13, 6, 14, 15, 3, 38, 39, 30, 31, 2, 26, 27);
		$this->layouts = array();
		$this->display_layers = array();
		$this->display_modes = array();
		//Inquiry response object for parsing inquiry XML
		//display hack so don't have to change lib/data_validation.1.php mantis:9943
		$this->data->zip = strlen($this->data->zip) > 5 ? substr($this->data->zip,0,5) . '-' . substr($this->data->zip,5,4) : $this->data->zip;
		// We'll need the ACL object (which is in the session) in order to check permissions
		$this->acl = ECash::getTransport()->acl;

		// layout, group, layer, module_mode specific
		$this->layout_map = array("personal" => "0,0,0,{$mode}_buttons",
					  'application_flag' => "0,0,10,{$mode}_buttons",
					  'application_flag_edit' => "0,2,2,{$mode}_buttons",
					  "loan_actions" => "0,0,7,{$mode}_buttons",
					  'contact_information' => "0,0,8,{$mode}_buttons",
					  'contact_information_popup' => "0,2,1,{$mode}_buttons",
					  "personal_reference" => "0,0,6,{$mode}_buttons",
					  "payment_card" => "0,0,11,{$mode}_buttons",
					  "idv" => "0,0,1,{$mode}_buttons",
					  "employment" => "0,0,3,{$mode}_buttons",
					  "application_info" => "0,0,2,{$mode}_buttons",
					  "documents" => "0,0,4,{$mode}_buttons",
					  "conversion_info" => "0,0,5,{$mode}_buttons",
					  "general_info" => "0,1,0,{$mode}_buttons",
					  "send_documents" => "0,1,1,{$mode}_buttons",
					  "receive_documents" => "0,1,2,{$mode}_buttons",
					  "esig_documents" => "0,1,3,{$mode}_buttons",
					  "packaged_docs" => "0,1,4,{$mode}_buttons",
					  "upload_document" => "0,1,5,{$mode}_buttons",
					  "bank_info" => "0,3,0,{$mode}_buttons",
					  "comments" => "1,0,0,schedule_buttons",
					  "schedule" => "1,1,0,schedule_buttons",
					  "payment_arrangement" => "1,1,1,payment_arrangement_buttons",
					  "manual_payment" => "1,1,3,manual_payment_buttons",
					  "ad_hoc" => "1,1,4,ad_hoc_buttons",
					  "debt_consolidation" => "1,1,5,debt_consolidation_buttons",
					  "reactivation" => "1,1,6,{$mode}_buttons",
					  "next_payment_adjustment" => "1,1,7,next_payment_adjustment_buttons",
					  "reprocess" => "1,1,8,{$mode}_buttons",
					  "partial_payment" => "1,1,9,partial_payment_buttons",
					  "vehicle" => "0,2,0,{$mode}_buttons"
					  );

		// Set the display layers/modes lists
		// primary
		if (preg_match('/queue_status|queue_empty/', ECash::getTransport()->Get_Current_Level()) == 0)
		{
			$this->display_layers[] = ECash::getTransport()->Get_Next_Level();
			$this->display_modes[] =  ECash::getTransport()->Get_Next_Level();

			// secondary
			$this->display_layers[] = ECash::getTransport()->Get_Next_Level();
			$this->display_modes[] =  ECash::getTransport()->Get_Next_Level();
		}
	}

	public function Get_Header()
	{
		include_once(WWW_DIR . "include_js.php");
		$header = " 
			    <script type='text/javascript'>
					var serverdate = '".date('M j Y H:i:s')."';
				</script>
		        <link rel=\"stylesheet\" href=\"css/transactions.css\">
		        <link rel=\"stylesheet\" href=\"js/calendar/calendar-dp.css\">
		        <script type=\"text/javascript\" src=\"js/prototype-1.5.1.1.js\"></script>
				" . include_js() . "
		        <script type=\"text/javascript\" src=\"js/json.js\"></script>
		        <script type=\"text/javascript\">
		            // for layout
		            var modestring = '{$this->mode}';

		            //for overlib
		            var ol_textcolor = \"black\";
		            var ol_textfont = \"Arial, Helvetica, sans-serif\";
		            var ol_offsetx = 20;
		            var ol_offsety = 10;
		            var ol_background = \"image/standard/overlib_bg.png\";
		        </script>
";

		if ($this->mode == 'conversion')
		{
			$header .= "<script type=\"text/javascript\" src=\"js/transactions_conversion.js\"></script>";
		}
		return $header;
	}

	public function Get_Body_Tags()
	{
		$loadstr = "onload=\"SetupTransactions();SaveInitials();";
		$primary_layout = null;

		if (!isset($this->display_layers[0]))
		{
			$this->display_layers[0] = "personal";
			$this->display_layers[1] = "general_info";
			$this->display_layers[3] = "bank_info";

			$this->display_modes[0] = "view";
			$this->display_modes[1] = "view";
			$this->display_modes[2] = "view";
		}

		if (!empty($_GET['ecash_react']) && $_GET['ecash_react'] == 'true')
		{
			$this->display_layers[1] = "packaged_docs";
			$this->display_modes[1] = "edit";
		}

		foreach ($this->display_layers as $idx => $layername)
		{
			if ($layername == null) continue;
			$props = split(",", $this->layout_map[$layername]);
			if (!isset($primary_layout)) $primary_layout = $props[0];

			if ($props[0] == $primary_layout)
			{
				$loadstr .= "SetDisplay({$props[0]},{$props[1]},{$props[2]},";
				$loadstr .= "'{$this->display_modes[$idx]}','{$props[3]}');";
			}
		}
		//Used to recieve on load javascript from popups
		if (!empty($_SESSION['javascript_on_load']))
		{
			$this->data->javascript_on_load = $_SESSION['javascript_on_load'];
			unset($_SESSION['javascript_on_load']);
		}
		if (!empty($this->data->javascript_on_load)) $loadstr .= $this->data->javascript_on_load;

		$loadstr .= "\"";
		return $loadstr;
	}

	public function Get_Module_HTML()
	{
		switch( ECash::getTransport()->Get_Current_Level() )
		{
			case "queue_status": $html = $this->Queue_Status_HTML(); break;

			case "queue_empty": $html = $this->Queue_Empty_HTML(); break;

			default: $html = $this->Overview_HTML(); break;
		}
		return $html;
	}

	protected function Overview_HTML()
	{
		$application = ECash::getApplicationById($this->data->application_id);

		if(file_exists(CUSTOMER_LIB . 'get_links.class.php'))
		{
		    require_once(CUSTOMER_LIB . 'get_links.class.php');
		    $this->get_links = new Customer_Get_Links($this->data, $this->acl, $this->mode, $this->module_name);
		}
		else
		{
				require_once("get_links.class.php");
			$this->get_links = new Get_Links($this->data, $this->acl, $this->mode, $this->module_name);
		}

		$this->build_display = new Build_Display($this->data, $this->acl, $this->mode, $this->module_name, $this->data_format);
		$this->Set_Contact_Data();
			
		if(empty($this->data->fund_warning)) $this->data->fund_warning = ""; 


		$ceiling = round($this->data->schedule_status->posted_total, -2);
		if ($ceiling < 100)
		{
			$ceiling = 100;
		}
		// Had a problem with certain accounts not getting business rules so there were errors displaying.
		$list = array();
		if((count($this->data->business_rules) > 0) && (isset($this->data->business_rules['max_num_arr_payments'])))
		{
			$list = array_keys($this->data->business_rules['max_num_arr_payments']);
			rsort($list);
		}

		$max_ceiling = 0;
		if (isset($list[0]))
		{
			$max_ceiling = $list[0];
		}

		if ($ceiling > $max_ceiling)
		{
			$ceiling = $max_ceiling;
		}

		if(isset($this->data->business_rules['max_num_arr_payments'][$ceiling]))
		{
			$this->data->num_arranged_payments = $this->data->business_rules['max_num_arr_payments'][$ceiling];
		}
		else
		{
			$this->data->num_arranged_payments = 0;
		}

		if(isset($this->data->business_rules['max_num_arr_payment_failed'][$ceiling]))
		{
			$this->data->num_arranged_payments_failed = $this->data->business_rules['max_num_arr_payment_failed'][$ceiling];
		}
		else
		{
			$this->data->num_arranged_payments_failed = 0;
		}


		// Check whether any DD changes will require a re-sign
		// You can blame me for putting this here.
		$this->data->dd_requires_resign = "false";

		$status_chain = $this->data->status . (($this->data->level1) ? "::{$this->data->level1}" : "") . 
			(($this->data->level2) ? "::{$this->data->level2}" : "") .
			(($this->data->level3) ? "::{$this->data->level3}" : "") .
			(($this->data->level4) ? "::{$this->data->level4}" : "") .
			(($this->data->level5) ? "::{$this->data->level5}" : "");

		if (Status_Chain_Needs_Resigned($status_chain))
		{
			$this->data->dd_requires_resign = "true";
		}

		if($this->data->is_react == 'yes')
		{
			if(is_numeric($this->data->parent_application_id))
			{
				$parent_url = "<a href='/?action=show_applicant&amp;application_id={$this->data->parent_application_id}'>{$this->data->parent_application_id}</a>";
				$this->data->react_ind = "<span class=\"react_ind\">R:</span>&nbsp;{$parent_url}";
			}
			else
			{
				$this->data->react_ind = "<span class=\"react_ind\" onmouseover=\"return overlib('React', CENTER, ABOVE);\" onmouseout=\"return nd();\">R</span>";
			}
		}
		else
		{
			$this->data->react_ind = "";
		}
		
		if ($this->data->is_card_schedule)
		{
			$this->data->card_ind = "<span class=\"card_ind\">Card</span>&nbsp;";
		}
		else
		{
			$this->data->card_ind = "";
		}
		
		if ($this->data->is_ach_schedule)
		{
			$this->data->ach_ind = "<span class=\"ach_ind\">ACH</span>&nbsp;";
		}
		else
		{
			$this->data->ach_ind = "";
		}
		
		/*
		if ($this->data->has_followup)
		{
			//$this->data->followup_ind = "<span class=\"followup_ind\">FU</span>&nbsp;";
			$this->data->followup_ind = "<a href=\"#\" onClick=\"javascript:window.open('/?action=get_followup_info&application_id={$this->data->application_id}', 'followup_info', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,copyhistory=no,width=450,height=200,left=150,top=150,screenX=150,screenY=150');\"><span class=\"followup_ind\">F</span></a>";
		}
		else
		{
			$this->data->followup_ind = "";
		}
		*/
		
		if (!isset($this->data->followup))
		{
			$this->data->followup_ind = "";
		}
		else if ($this->data->followup->status == "pending")
		{
			if ($this->data->followup->category == "customer")
			{
				$this->data->followup_ind = "<a href=\"#\" onClick=\"javascript:window.open('/?action=get_followup_info&application_id={$this->data->application_id}', 'followup_info', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,copyhistory=no,width=450,height=200,left=150,top=150,screenX=150,screenY=150');\"><span class=\"followup_pending_ind\">FC</span></a>";
			}
			else //internal
			{
				$this->data->followup_ind = "<a href=\"#\" onClick=\"javascript:window.open('/?action=get_followup_info&application_id={$this->data->application_id}', 'followup_info', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,copyhistory=no,width=450,height=200,left=150,top=150,screenX=150,screenY=150');\"><span class=\"followup_pending_ind\">FI</span></a>";
			}
		}
		else if ($this->data->followup->status == "complete")
		{
			if ($this->data->followup->category == "customer")
			{
				$this->data->followup_ind = "<a href=\"#\" onClick=\"javascript:window.open('/?action=get_followup_info&application_id={$this->data->application_id}', 'followup_info', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,copyhistory=no,width=450,height=200,left=150,top=150,screenX=150,screenY=150');\"><span class=\"followup_complete_ind\">FC</span></a>";
			}
			else //internal
			{
				$this->data->followup_ind = "<a href=\"#\" onClick=\"javascript:window.open('/?action=get_followup_info&application_id={$this->data->application_id}', 'followup_info', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,copyhistory=no,width=450,height=200,left=150,top=150,screenX=150,screenY=150');\"><span class=\"followup_complete_ind\">FI</span></a>";
			}
		}
		else
		{
			$this->data->followup_ind = "";
		}
		
		$this->data->watch_ind = ($this->data->model->is_watched == 'yes') ? "<span class=\"watch_ind\" onmouseover=\"return overlib('Watch', CENTER, ABOVE);\" onmouseout=\"return nd();\">&nbsp;W&nbsp;</span>" : "";

		// This is all setup until "Set_Visible_Layers"
		$this->layouts[] = new Layout("layout0");
		$this->layouts[] = new Layout("layout1");

		// Layout 0, Groups 0-2
		$g00 = new Group(array ("position" => "absolute", "top" => "5px", "left" => "10px",
					"width" => "380px", "height" => "300px", "overflow" => "auto",
					));
		$g01 = new Group(array ("position" => "absolute", "top" => "5px", "left" => "401px",
					"width" => "380px", "overflow" => "auto", "height" => "500px",
					));
		$g02 = new Group(array ("position" => "absolute", "top" => "297px", "left" => "10px",
					"width" => "380px", "height" => "240px", "overflow" => "auto", 
					));
		$g03 = new Group(array ("position" => "absolute", "top" => "325px", "left" => "401px",
					"width" => "380px", "overflow" => "auto", "height" => "290px",
					));

		// Set up the layout 0 layers,  If conversion is for CLK, use the Conversion Notes, etc.
		// Layout 0, Group 0
		if(ECash::getConfig()->CONVERSION_MODE === 'CASHLINE')
		{
			$g00->Add_Layers(new Layer("conversion_info", 'view'));
		}
		if(isset($this->data->loan_type_model) && $this->data->loan_type_model === 'Title')
		{
			$g00->Add_Layers(new Layer("vehicle_data", 'both')); // layout0group0layer9
		}

		// Added payment_card #16679
		$g00->Add_Layers(new Layer("personal", 'both'),
			 	new Layer("idv", 'view'),
			 	new Layer("employment", 'both'),
			 	new Layer("application_info", 'both'),
			 	new Layer("documents", 'view'),
			 	new Layer("personal_reference", 'both'),
				new Layer('contact_information', 'view'),
				new Layer('application_flag','view'),
				new Layer("loan_actions", 'view'),
				new Layer("payment_card", "both"));


		// Layout 0, Group 1
		$g01->Add_Layers(new Layer("general_info", 'both'),
				 new Layer("send_documents", 'edit'),
				 new Layer("receive_documents", 'edit'),
				 new Layer("esig_documents", 'edit'),
				 new Layer("packaged_docs", 'edit'),
				 new Layer("upload_document", 'edit'));

		// Layout 0, Group 2
		$g02->Add_Layers(new Layer("comments", 'view'),
				new Layer('application_flag_edit', 'both'),
				new Layer('contact_information_popup', 'edit'));
		// Layout 0, Group 3
		$g03->Add_Layers(new Layer("bank_info", 'both'),
				new Layer("doc_blank", 'view'));
		$this->layouts[0]->Add_Groups($g00, $g01, $g02, $g03);

		/**
		 * These are the transactions related screens.  They should be rendered for all areas
		 * except for Funding, unless the loan type is a Title loan where we'll need to be able
		 * to add fees. 
		 */
//		if((($this->data->current_module === 'funding') && $this->data->loan_type_model === 'Title') ||
//		  	$this->data->current_module !== 'funding')
		// mantis 9964 now they want to see this in funding [richardb]
		if (1)
		{
			// Layout 1, Group 0
			$g10 = new Group(array ("position" => "absolute", "top" => "5px", "left" => "5px",
					"width" => "762px", "height" => "100px"));

			$g10->Add_Layers(new Layer("transaction_summary", 'view'),
					 new Layer("application_summary", 'view', 'application_summary_buttons'));

			// Layout 1, Group 1
			$g11 = new Group(array ("position" => "absolute", "top" => "122px", "left" => "5px",
						"width" => "758px", "height" => "304"));

			// These comprise the basic outline of the Transactions menu.  The schedule and the
			// summary.
			$g11->Add_Layers(new Layer('schedule', 'view'),
							 new Layer('application_list', 'view', 'application_summary_buttons'));

			// Don't render these unless we're in a module that isn't funding.
			if($this->data->current_module !== 'funding')
			{
				// Payment Arrangements
				$pal = new Layer('payment_arrangement', 'edit', 'payment_arrangement_buttons');
				$pal->content = $this->build_display->Build_Payment_Table('payment_arrangement');

				// Manual Payments
				$mpl = new Layer('manual_payment','edit','manual_payment_buttons');
				$mpl->content = $this->build_display->Build_Payment_Table('manual_payment');

				// Debt Consolidation
				if(ECash::getConfig()->USE_DEBT_CONSOLIDATION_PAYMENTS !== FALSE)
				{
					$dbp = new Layer('debt_consolidation', 'edit', 'debt_consolidation_buttons');
					$dbp->content = $this->build_display->Build_Debt_Consolidation_Table();
					$g11->Add_Layers($dbp);
				}

				// Ad Hoc Scheduling
				if(ECash::getConfig()->USE_ADHOC_PAYMENTS !== FALSE)
				{
					$adhoc = new Layer('ad_hoc', 'edit', 'ad_hoc_buttons');
					$adhoc->content = $this->build_display->Build_Payment_Table('ad_hoc');
					$g11->Add_Layers($adhoc);
				}

				$react = new Layer('reactivation', 'view');


				// next payment adjustment

				$next_adjustment = new Layer('next_payment_adjustment', 'edit', 'next_payment_adjustment_buttons');
				$next_adjustment->content = $this->build_display->Build_Payment_Table('next_payment_adjustment') . file_get_contents(CLIENT_VIEW_DIR . 'preview_schedule.html');
				
				$partial_payment = new Layer('partial_payment', 'edit', 'partial_payment_buttons');
				$partial_payment->content = $this->build_display->Build_Payment_Table('partial_payment');
				
				$g11->Add_Layers($pal, $mpl, $react, $next_adjustment, $partial_payment);
				
			
				
			}

			$this->layouts[1]->Add_Groups($g10, $g11);

		}

		//only render this if they have a reprocessing option
		//this may need to be moved to a new layer if it begins to interfere with
		//the code above (which currently is mutually exclusive) [JustinF]
		if($this->Get_Section_ID_by_Name($this->Get_Section_ID(), 'reprocess') !== -1)
		{
			// Layout 1, Group 1

			$g11 = new Group(array ("position" => "absolute", "top" => "90px", "left" => "5px",
					"width" => "758px", "height" => "304px"));
			$reprocess = new Layer('reprocess', 'view');
			// Removed layers that shouldn't exist for CFC [BrianR]
			//$g11->Add_Layers($pal, $mpl, $adhoc, $dbp, $reprocess);
			$g11->Add_Layers($reprocess);
			$this->layouts[1]->Add_Groups($g11);
			$this->createRulesList();
			$this->createTenureDrop();
		}
		
		if ($this->Get_Section_ID_by_Name($this->Get_Section_ID(), 'application') !== -1)
		{
			$this->createTenureDrop();
		}

		$layout_content = "";
		foreach($this->layouts as $layout)
		{
			$layout->Set_Defaults();
			$layout_content .= $layout->render();
		}
	
		$html = file_get_contents(CLIENT_VIEW_DIR . "application.html");

		$html = str_replace('%%%layouts%%%', $layout_content, $html);

		// Format data for display
		$format_list = array("ssn" => "social",
				     "phone_home"     => "phone",
				     "phone_work"     => "phone",
				     "phone_cell"     => "phone",
				     "name_first"     => "smart_case",
				     "name_last"      => "smart_case",
				     "street"         => "smart_case",
				     "city"	      	  => "smart_case",
				     "county"      	  => "smart_case",
				     "state"          => "upper case",
				     "customer_email" => "email",
				     "residence_start_date" => "date",
				     "banking_start_date"   => "date",
				     "bank_name"      => "smart_case",
				     "employer_name"  => "smart_case",
				     "job_title"      => "smart_case",
				     "zip_code"       => "zip",
				     "income_direct_deposit"	=> "smart_case",
				     "bank_account_type" => "smart_case");

		$this->data_format->Display_Many($format_list, $this->data);
		$this->data->date_hire = (!empty($this->data->date_hire)) ? date("m-d-Y", strtotime($this->data->date_hire)) : "";
		$this->data->conversion_info_button = '';
		$this->data->phone_cell_link = '';
		$this->data->phone_home_link = '';
		$this->data->phone_work_link = '';

		$current_section_id = $this->Get_Section_ID();
		$personal_id = $this->Get_Section_ID_by_Name($current_section_id, 'personal');

		// personal link info
		$this->data->personal_link = $this->get_links->Get_Personal_Link($personal_id);
		if ($personal_id > -1)
		{
			$this->data->edit_personal_details_link = $this->get_links->Get_Edit_Personal_Details_Link($personal_id);

			if ($this->data->edit_personal_details_link != '&nbsp;')
			{

				$paydate_url = $this->build_display->Build_Paydate_URL($this->data);
				$this->data->edit_paydate_wizard_link = "<a href=\"#\" onClick=\"javascript:window.open('/?action=paydate_wizard&application_id={$this->data->application_id}{$paydate_url}', 'pop_up_wizard', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=no,copyhistory=no,width=465,height=360,left=150,top=150,screenX=150,screenY=150');\">[Paydate Wizard]</a>";
				$this->data->edit_general_info_link = "<a href=\"#\" onClick=\"javascript:SetDisplay(0,1,0, 'edit', '{$this->mode}_buttons');\">Edit General Info</a>";
			}
			else
			{
				$this->data->edit_paydate_wizard_link = '&nbsp;';
				$this->data->edit_general_info_link = '&nbsp;';
			}

			if($this->module_name == 'funding' && $this->data->status == 'active') // mantis:3985
				$this->data->edit_paydate_wizard_link = '&nbsp;';

			$this->data->edit_personal_references_link = $this->get_links->Get_Edit_Personal_References_Link($personal_id);
			$this->get_links->Set_Personal_References_Phone_Links();
			$this->get_links->Set_General_Info_Phone_Links();
		}
		else
		{
			$this->data->edit_paydate_wizard_link = '&nbsp;';
			$this->data->edit_general_info_link = '&nbsp;';
			$this->data->edit_personal_details_link = '&nbsp;';
		}
		// bank link info
		$this->data->edit_bank_info_link = false;
		if ($personal_id > -1) {
			if ($this->acl->Acl_Check_For_Access(Array($this->data->current_module, $this->data->current_mode, 'personal', 'bank_info'))) {
	
				// ACH Authorization doc
				$doc_list_model = ECash::getFactory()->getModel('DocumentList');
				$doc_list_model->loadBy(array(
								'company_id' => $this->company_id,
								'active_status' => 'active',
								'name_short' => 'ACH Authorization',
				));
	
				$doc_model = ECash::getFactory()->getModel('Document');
				$ach_doc_array = $doc_model->loadAllBy(array(
									'application_id' => $application->application_id,
									'company_id' => $this->company_id,
									'document_event_type' => 'received',
									'document_list_id' => $doc_list_model->document_list_id,
				));
	
				// Bank statement document
				$doc_list_model->loadBy(array(
								'company_id' => $this->company_id,
								'active_status' => 'active',
								'name_short' => 'Bank Statement',
				));
	
				$bank_doc_array = $doc_model->loadAllBy(array(
									'application_id' => $application->application_id,
									'company_id' => $this->company_id,
									'document_event_type' => 'received',
									'document_list_id' => $doc_list_model->document_list_id,
				));
	
				// Voided check document
				$doc_list_model->loadBy(array(
								'company_id' => $this->company_id,
								'active_status' => 'active',
								'name_short' => 'Voided Check Letter',
				));
	
				$check_doc_array = $doc_model->loadAllBy(array(
									'application_id' => $application->application_id,
									'company_id' => $this->company_id,
									'document_event_type' => 'received',
									'document_list_id' => $doc_list_model->document_list_id,
				));
				$this->data->edit_bank_details_link = $this->get_links->Get_Edit_Bank_Details_Link($personal_id);
				if ($this->data->edit_bank_details_link != '&nbsp;') {
					$this->data->edit_bank_info_link = "<a href=\"#\" onClick=\"javascript:SetDisplay(0,3,0, 'edit', '{$this->mode}_buttons');\">Edit Bank Info</a>";
				} else 
				if ((count($ach_doc_array) > 0) && ((count($bank_doc_array) > 0) || (count($check_doc_array) > 0)))
				{
					$this->data->edit_bank_info_link = "<a href=\"#\" onClick=\"javascript:SetDisplay(0,3,0, 'edit', '{$this->mode}_buttons');\">Edit Bank Info</a>";
				}
			}
		}
		if (!$this->data->edit_bank_info_link) {
			$this->data->edit_bank_info_link = '&nbsp;';
		}
		
		// id and credit info
		$this->data->id_and_credit_link = $this->get_links->Get_ID_And_Credit_Link($current_section_id);
		if(isset($this->data->idv_trapped_error) && $this->data->idv_trapped_error == "")
		{
			$this->data->edit_id_and_credit_link = $this->get_links->Get_Edit_ID_And_Credit_Link($current_section_id);
		}
		else
		{
			$this->data->edit_id_and_credit_link = "";
		}
		
		// get application section id
		$app_id = $this->Get_Section_ID_by_Name($current_section_id, 'application');
		$this->data->application_link = $this->get_links->Get_Application_Link($app_id);
		if ($app_id > -1)
		{
			$this->data->edit_application_info_link = $this->get_links->Get_Edit_Application_Info_Link($app_id);
		}

		// If they're pre-fund (applicants) then we want to show that the dates in App Info are estimates
		if(($this->data->level1 == "applicant") || ($this->data->level2 == "applicant"))
		{
			$this->data->date_estimation_label = "<i>(est.)</i>";
		}
		else
		{
			$this->data->date_estimation_label = "";
		}
		if(empty($this->data->fund_amount))
		{
			$this->data->fund_amount = 0;
		}
		// get Name suffix dropdown
		$this->createSuffixDrop();
		
		// get employment and origin
		$this->data->employment_n_origin_link = $this->get_links->Get_Employment_N_Origin_Link($current_section_id);
		$this->data->edit_employment_n_origin_link = $this->get_links->Get_Edit_Employment_N_Origin_Link($current_section_id);

		// Get Payment Card Information #16679
		if ($this->acl->Acl_Check_For_Access(Array($this->data->current_module, $this->data->current_mode, 'personal', 'payment_card')))
		{
			$this->data->payment_card_information_link = $this->get_links->Get_Payment_Card_Information_Link($current_section_id);
			$this->data->edit_payment_card_information_link = $this->get_links->Get_Edit_Payment_Card_Information_Link($current_section_id);
		} else {
			$this->data->payment_card_information_link = '';
			$this->data->edit_payment_card_information_link = '';
		}

		
		// get documents
		$doc_id = $this->Get_Section_ID_by_Name($current_section_id, 'documents');
		$this->data->documents_link = $this->get_links->Get_Documents_Link($doc_id);

		// get transactionstransactions link
		$transaction_id = $this->Get_Section_ID_by_Name($current_section_id, 'transactions');
		$this->data->transaction_link = $this->get_links->Get_Transaction_Link($transaction_id);

		$this->data->loan_actions_link = $this->get_links->Get_Loan_Actions_Link($current_section_id);
		$is_transactions_overview_read_only = $this->Is_This_Read_Only($transaction_id, 'transactions_overview');

		// Used for transaction pop-ups.
		$_SESSION['Transactions_Read_Only'] = $is_transactions_overview_read_only;

		if ((ECash::getConfig()->INVESTOR_GROUP_TAGGING_ENABLED != NULL) && $this->data->is_react == 'yes')
		{
			$this->data->investor_groups = $this->BuildInvestorGroupDropdown();
		}
		else
		{
			$this->data->investor_groups = '';
		}
		
		if($this->data->current_module === 'funding')
		{
			$this->data->edit_vehicle_data_link = $this->get_links->Get_Edit_Vehicle_Data_Link($current_section_id);
		}
		else 
		{
			$this->data->edit_vehicle_data_link = '';
		}
		

		$this->Set_Read_Only_Fields();
		$this->Set_Dropdown_HTML($this->Is_Read_Only_Field('bank_account_type'));
		$this->Set_Button_HTML();
		$this->Check_Watch_Status();
		$this->build_display->Build_Personal_References_Table($this->get_links);
		$this->Trim_Fields();

		//mantis:4416
		if (in_array("ssn_last_four_digits", $this->data->read_only_fields))
		{
			$last_4_ssn = substr($this->data->ssn_trim, 7);
			$this->data->ssn_trim = 'XXX-XX-' . $last_4_ssn;
		}
		$this->Setup_Overlink_Data();
		$this->Set_Transactional_Data($is_transactions_overview_read_only);
		
		$this->Set_DNL_Flag(); //mantis:4648
		
		$this->Set_DNL_Info_Line(); //mantis:4360
		$this->Check_Hold_Status();
		$this->Check_Amortization_Status();

//echo '<pre>' . print_r($this->data->docs,true). '</pre>';
		$this->build_display->build_id_credit->build($this->data);
		$this->data->comments = $this->build_display->Build_Comments($this->data->comments);
		$this->data->documents = $this->build_display->Build_Documents($this->data->docs);
		$this->data->loan_actions = $this->build_display->Build_Loan_Actions($this->data->loan_action_list);

		if ($this->acl->Acl_Check_For_Access(Array($this->data->current_module, $this->data->current_mode, 'personal', 'personal_contacts')))
		{
			$this->data->contact_information = $this->build_display->Build_Contact_Information();
		}

		if($this->acl->Acl_Check_For_Access(Array($this->data->current_module, $this->data->current_mode, 'application', 'application_flag')))
		{
			$this->build_display->Build_Application_Flags();
		}

	
		$this->data->add_card_button = "";

		if ($this->acl->Acl_Check_For_Access(Array($this->data->current_module, $this->data->current_mode, 'personal', 'payment_card')))
		{
			$this->data->card_rows = $this->build_display->Build_Card_Information();

			// If they have read write access, allow them to add a card
			if ($this->acl->Acl_Check_For_Access(Array($this->data->current_module, $this->data->current_mode, 'personal', 'payment_card','payment_card_read_write')))
				$this->data->add_card_button = '<input type="button" value="Add" onclick="javascript:add_card();">';
			
			// If they have read write edit access, allow them to add a card
			if ($this->acl->Acl_Check_For_Access(Array($this->data->current_module, $this->data->current_mode, 'personal', 'payment_card','payment_card_read_write_edit')))
				$this->data->add_card_button = '<input type="button" value="Add" onclick="javascript:add_card();">';


		}

		$this->data->follow_up_message = (strtotime($this->data->date_next_contact) > time()) ? "Follow Up set for: ".date("m/d/Y H:i",strtotime($this->data->date_next_contact)) : "";
		if(ECash::getConfig()->USE_LOGIN !== FALSE && (! empty($this->data->crypt_password)))
		{
			$this->data->login_user_id  = $this->data->login;
			$password = crypt_3::Decrypt($this->data->crypt_password);
			$this->data->login_password = (!$password instanceof Error_2) ? $password : 'Not Available';
		}
		else
		{
			$this->data->login_user_id  = '';
			$this->data->login_password = '';
		}

		$this->build_display->Build_Document_Restrictions($this->data->send_doc_list);
		$this->data->send_document_list = $this->build_display->Build_Document_List($this->data->send_doc_list, 'send');
		$this->data->receive_document_list = $this->build_display->Build_Receive_Document_List($this->data->receive_doc_list, $this->data->docs);
		$this->data->upload_document_list = $this->build_display->Build_Receive_Document_List($this->data->receive_doc_list, $this->data->docs, true);
		$this->data->esig_document_list = $this->build_display->Build_Document_List($this->data->esig_doc_list, 'esig');
		$this->data->packaged_document_list = $this->build_display->Build_Packaged_Document_List($this->data->packaged_doc_list, 'both');
		$this->data->paydate_url = $this->build_display->Build_Paydate_URL($this->data);
		$this->data->application_list_rows = $this->build_display->Build_Application_List($this->data->application_list);

		$this->data->mode_class = $this->mode;

		// Ugly, but extensible
		if (file_exists(CUSTOMER_DIR . "legacy_customer_lib/Colors/Status_Colors.class.php"))
		{
			require_once(CUSTOMER_DIR . "legacy_customer_lib/Colors/Status_Colors.class.php");
			$colors = new Customer_Status_Colors();
		}
		else
		{
			require_once(LIB_DIR . "Colors/Status_Colors.class.php");
			$colors = new Status_Colors();
		}
	
		$this->data->status_background_color = $colors->getStatusColor($this->data->application_id, $this->data->application_status_id);

		$this->data->status_class = "app_status_" . $this->data->application_status_id;

	//	$this->data->tiff_message = eCash_Document::$message;

		if(isset($this->data->ext_collections_co) && $this->data->ext_collections_co != '')
		{
			$this->data->coll_company_title = 'Collection Co:';
			$this->data->coll_company = ucwords($this->data->ext_collections_co);
			$this->data->coll_company_phone_title = 'Collection Co Phone:';

			//mantis:7744
			if($this->data->ext_collections_co == 'final collections')
			{
				$this->data->coll_company_phone = ECash::getTransport()->final_collection_phone;

			}
			else if($this->data->ext_collections_co == 'recovery')
			{
				$this->data->coll_company_phone = ECash::getTransport()->recovery_phone;
			}
			else
			{
				$this->data->coll_company_phone = '';
			}
		}
		else
		{
			$this->data->coll_company_title = '';
			$this->data->coll_company = '';
			$this->data->coll_company_phone_title = '';
			$this->data->coll_company_phone = '';
		}
		//end mantis:3994

		//[#38368]
		$rate_calc = $application->getRateCalculator();

		$this->data->rate_title = 'Rate:';
		$this->data->rate_percent = $rate_calc->getPercent() . '%';
		$this->data->override_rate = '';
		$this->data->override_rate_input = '';
		$this->data->override_rate_title = '';
		$this->data->override_rate_min = '';
		$this->data->override_rate_max = '';
		
		if(($this->data->level1 == 'verification' ||
		   	$this->data->level1 == 'underwriting' ||
		   	$this->data->status == 'in_process') &&
		   ($this->acl->Acl_Check_For_Access(array($this->data->current_module, 'rate_override')) &&
		    isset($this->data->business_rules['rate_override']['min']) &&
			isset($this->data->business_rules['rate_override']['max'])))
		{
			$this->data->override_rate_title = 'Override Rate:';
			$this->data->override_rate_min = $this->data->business_rules['rate_override']['min'];
			$this->data->override_rate_max = $this->data->business_rules['rate_override']['max'];

			// The thing about this is that we're ALWAYS going to get a rate, calculated
			// or already set.  Not sure if that's going to be a problem.
			$rate = $application->getRate();
			$this->data->override_rate = !empty($rate) ? $rate : '';
			$this->data->override_rate_input = '<input type="text" size="6" maxlength="6" name="rate_override" id="rate_override" value="'.$this->data->override_rate.'" />';
		}		
		//end [#38368]
		
		/**
		 * Disable the antiquated Reprint URL's for newer companies
		 */
		if(ECash::getConfig()->CONDOR_DOC_URL !== NULL || ECash::getConfig()->DOC_REPRINT_URL !== NULL)
		{
			//mantis:4922
			if (in_array("disable_document_links", $this->data->read_only_fields))
			{
				$this->data->reprint_docs_title = '';
				$this->data->repr_doc_old_link = '';
				$this->data->repr_doc_new_link = '';
			}
			else
			{
				$this->data->reprint_docs_title = 'Reprint Docs';
				$this->data->repr_doc_old_link = '[Old]';
				$this->data->repr_doc_new_link = '[New]';
				$this->data->reprint_docs_title = '';
				$this->data->repr_doc_old_link = '';
				$this->data->repr_doc_new_link = '';
			}
	
			$this->data->condor_doc_url = ECash::getConfig()->CONDOR_DOC_URL . '&property_short='. ECash::getTransport()->company;
			$this->data->doc_reprint_url = ECash::getConfig()->DOC_REPRINT_URL . '?database=WEB_' . ECash::getTransport()->company;
			$this->data->doc_reprint_pw = md5($this->data->application_id . 'potato');
			
		}
		else
		{
			$this->data->reprint_docs_title = '';
			$this->data->repr_doc_old_link = '';
			$this->data->repr_doc_new_link = '';
			$this->data->condor_doc_url = '';
			$this->data->doc_reprint_url = '';
			$this->data->doc_reprint_pw = '';
		}
		
		$this->data->company = ECash::getCompany()->name_short;
		$this->data->module_name = $this->module_name;
		$this->data->mode_buttons = $this->build_display->Build_Button_Sets($this->layouts);

		$this->data->loan_type_abbreviation = "<span id=\"lt_abbrev\">{$this->data->loan_type_abbreviation}</span>";

		if(isset($_SESSION['error_message']))
		{
			$html .= '<script type="text/javascript"> alert("' . str_replace("\n",'\n',$_SESSION['error_message']) . '");</script>';
			unset($_SESSION['error_message']);
		}
	
		if(!empty($this->data->has_js))
		{
			$html .= $this->data->has_js;
			
		}
		

                // if application status = active, balance < 200, made ACH payment)
                $debit_achs = 0;
                if ($this->data->application_status_id == 20) {
                        if (round($this->data->schedule_status->posted_total, 2) < 200) {
                                foreach ($this->data->schedule_status->debits as $debit) {
                                        if ($debit->ach_id && !(array_search($debit->type_id, $this->refi_qual_ach_pmt_types) === false)) {
                                                $debit_achs++;
                                        }
                                }
                                if ($debit_achs) {
                                        $this->data->react_ind .= '<span style="background-color:green;padding:2px;color:white">REFI</span>';
                                }
                        }
                }

		return Display_Utility::Token_Replace($html, (array)$this->data);

	}

	protected function Set_Read_Only_Fields()
	{
		$this->data->fund_date = $this->data->date_fund_actual;

		$matches = array();
		preg_match('/(\d\d?)-(\d\d?)-(\d{4})/', $this->data->fund_date, $matches);
		if ($matches[0] != null) 
		{
			$this->data->fund_date_slashes = implode('/', array($matches[1], $matches[2], $matches[3]));
		}


		//mantis:4416
		$ssn_display = 'XXX-XX-' . substr($this->data->ssn, 7);

		$this->data->ssn_edit_field = '';

		//mantis:4993

		// If ssn_last_four_digits, only display last 4 digits.  The agent cannot edit.
		if ($this->Is_Read_Only_Field('ssn_last_four_digits'))
		{
			$this->data->ssn_edit_field = $ssn_display . '<input type="hidden" id="EditAppPersonalInfoCustSsn" name="ssn" value="' . $ssn_display . '" />';
		}
		// If ssn_last_four_digits is not set, but the field is read only, display the full SSN.  The agent cannot edit.
		else if (! $this->Is_Read_Only_Field('ssn_last_four_digits') && $this->Is_Read_Only_Field('ssn_read_only'))
		{
			$this->data->ssn_edit_field = $this->data->ssn . '<input type="hidden" id="ssn" name="ssn" value="' . $this->data->ssn . '" />';
		}
		// Full access to edit field
		else
		{
			$this->data->ssn_edit_field  = '<input class="input_text" id="ssn" type="text" id="ssn" name="ssn" value="' . $this->data->ssn . '" maxlength="11"  onblur = "return strip_all_but(this,keybNumeric,((window.event)?window.event:event),\'-\');" onkeypress="return editKeyBoard(this,keybNumeric,((window.event)?window.event:event));"  onkeyup="mask(this.value,this,\'3,6\',Array(\'-\',\'-\'),((window.event)?window.event:event));">';
			$arguments = "action=check_ssn&mode={$this->mode}&application_id={$this->data->application_id}&customer_id={$this->data->customer_id}";
			$this->data->ssn_edit_field .= "<input type=\"button\" value=\"Update SSN\" onClick=\"javascript:if (validate_personal()) OpenCustomerManagerWindow('$arguments', 'Customer Split/Merge', 'ssn');\">";
		}

		// If the field is marked read only, allow them to send the customer to review
		if($this->Is_Read_Only_Field('ssn_read_only'))
		{
			$this->data->ssn_edit_field .= "<input type=\"button\" value=\"Send to Review\" class=\"button\" onClick=\"javascript:Add_Comment('reason_comment', 'ssn_change_review');\">";
		}

		$this->data->bank_account_edit_field = '';
		if ($this->Is_Read_Only_Field('bank_account'))
		{
			$this->data->bank_account_edit_field .= $this->data->bank_account . '<input type="hidden" id="EditAppInfoCustBankAcctNum" name="bank_account" value="'
																. $this->data->bank_account . '" />';
		}
		else
		{
			$this->data->bank_account_edit_field .= '<input type="text" id="EditAppInfoCustBankAcctNum" name="bank_account" value="' . $this->data->bank_account
																. '" maxlength="30" class="input_text" onblur = "return strip_all_but(this,keybNumeric,((window.event)?window.event:event));" onkeypress="return editKeyBoard(this,keybNumeric,((window.event)?window.event:event));">';
		}

		$this->data->bank_name_edit_field = '';
		if ($this->Is_Read_Only_Field('bank_name'))
		{
			$this->data->bank_name_edit_field .= $this->data->bank_name .'<input type="hidden" id="EditAppInfoCustBankName" name="bank_name" value="'
															. $this->data->bank_name . '" />';
		}
		else
		{
			$this->data->bank_name_edit_field .= '<input type="text" id="EditAppInfoCustBankName" name="bank_name" value="' . $this->data->bank_name . '" maxlength="30" class="input_text" onblur = "return strip_all_but(this,keybAlphaNumeric,((window.event)?window.event:event));" onkeypress="return editKeyBoard(this,keybAlphaNumeric,((window.event)?window.event:event));">';
		}

		$this->data->bank_aba_edit_field = '';
		if ($this->Is_Read_Only_Field('bank_aba'))
		{
			$this->data->bank_aba_edit_field = $this->data->bank_aba . '<input type="hidden" id="EditAppInfoCustBankAba" name="bank_aba" value="'
															. $this->data->bank_aba . '" />';
		}
		else
		{
			$this->data->bank_aba_edit_field = '<input type="text" id="EditAppInfoCustBankAba" name="bank_aba" value="' . $this->data->bank_aba
															. '" maxlength="9" class="input_text" onblur = "return strip_all_but(this,keybNumeric,((window.event)?window.event:event));" onkeypress="return editKeyBoard(this,keybNumeric,((window.event)?window.event:event));">';
		}

		if (($this->data->level2 == 'applicant') || ($this->data->status == 'funding_failed') ||
			($this->data->status == 'active') || ($this->data->status == 'approved') ||
			($this->data->status == 'in_process'))
		{

			$loan_amount_allowed = !empty($data->loan_amount_allowed) ? $data->loan_amount_allowed : null;

			$this->data->save_application_button = '<input type="button" value="Save Changes" onClick="if (app_confirm_direct_deposit_change()) { ValidateDate('.$loan_amount_allowed.'); }">';

			if ($this->data->status != 'active')
			{
				$this->data->date_first_payment_popup = 
					'<a href="#" onClick="PopCalendar1(\'date_first_payment\', event, \''.$this->data->date_fund_actual.'\',false);">select</a>';

				$this->data->date_first_payment = 
					'<input id="date_first_payment" name="date_first_payment" type="text" size="10" value="'.str_replace('-','/',$this->data->date_first_payment). '" READONLY >';
			}
			else 
			{
				$this->data->date_first_payment_popup = '&nbsp;';

				$this->data->date_first_payment =  
					'<input type="hidden" name="date_first_payment" value="' . 
					str_replace('-','/',$this->data->date_first_payment). '" />' .
					$this->data->date_first_payment;
			}
		}
		else
		{
			$this->data->save_application_button = '<input type="submit" value="Save Changes" class="button" onClick="return app_confirm_direct_deposit_change();">';

			$this->data->date_first_payment_popup = '&nbsp;';

			if($this->data->date_first_payment != '')
			{
				$this->data->date_first_payment =  '<input type="hidden" name="date_first_payment" value="'
																. str_replace('-','/',$this->data->date_first_payment) . '" />'.$this->data->date_first_payment;
			}
			else
			{
				$this->data->date_first_payment = "&nbsp;";
			}
		}
	}

	protected function Is_Read_Only_Field($field_name)
	{
		if(isset($this->data->isReadOnly) && $this->data->isReadOnly) return true;
		if(in_array($field_name, $this->data->read_only_fields)) return true;
		return false;
	}

	protected function Is_This_Read_Only($parent_id, $child_name)
	{
		if(isset($this->data->isReadOnly) && $this->data->isReadOnly) return true;
		foreach($this->data->all_sections as $key => $value)
		{
			if ($value->section_parent_id == $parent_id
					&& $value->name == $child_name
					&& $value->read_only > 0)
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Find out if a section is read-only
	 *
	 * @param integer $section_id
	 * @return boolean
	 */
	protected function Is_This_Section_Read_Only ($section_id)
	{
		if($this->data->isReadOnly) return true;
		foreach($this->data->all_sections as $key => $value)
		{
			if($value->section_id = $section_id
				&& $value->read_only > 0)
				return true;
		}
		return false;
	}

	protected function Build_Conversion_Info($reasons)
	{
		static $Proper = array(
			'invalid_paydate_model' => 'Paydates',
			'other' => 'Other',
			'next_action' => 'Action'
		);

		$this->data->queue_reasons = "";
		$alt = "";
		foreach($reasons as $reason)
		{
			$a = $Proper[$reason->queue_reason];
			$b = $reason->comment;
		        $this->data->queue_reasons .= "
 <tr class=\"height\">
 <td class=\"align_left{$alt}_bold\">&nbsp;{$a}&nbsp;</td>
 <td class=\"align_left{$alt}\">{$b}</td>
 </tr>";

			$alt = ($alt == "") ? "_alt" : "";
		}
	}

	protected function Queue_Status_HTML()
	{
		$html = file_get_contents(CLIENT_VIEW_DIR . "queue_status.html");

		$this->data->mode_class = $this->mode;

		// "there is 1 application", "there are 4 applications"
		if( $this->data->queue_count == 1 )
		{
			$this->data->queue_are = "is";
			$this->data->queue_s   = "";
		}
		else
		{
			$this->data->queue_are = "are";
			$this->data->queue_s   = "s";
		}

		return Display_Utility::Token_Replace($html, (array)$this->data);
	}

	protected function Queue_Empty_HTML()
	{
		$html = file_get_contents(CLIENT_VIEW_DIR . "queue_empty.html");

		$this->data->mode_class = $this->mode;

		$html = str_replace("%%%mode_class%%%", $this->mode, $html);

		return $html;
	}
	
	protected function BuildInvestorGroupDropdown()
	{
		require_once(SQL_LIB_DIR.'tagging.lib.php');
		$investorGroups = Load_Tags();

		$options = array();

		foreach ($investorGroups as $group)
		{
			if (! empty(ECash::getConfig()->DEFAULT_INVESTOR_GROUP) && ECash::getConfig()->DEFAULT_INVESTOR_GROUP == $group->tag_name)
			{
				$default = 'selected="selected"';
			}
			else
			{
				$default = '';
			}
			$options[] = "<option value=\"{$group->tag_id}\" {$default}>{$group->name}</option>";
		}
		$options = implode("\n", $options);

		return "
			<form id=\"Investor_Group\" name=\"Investor_Group\">
			Assign Investor Group:<br>
			<select name=\"investor_group_list\" id=\"investor_group_list\">
				{$options}
			</select>
			</form>
		";
	}



	protected function Set_Dropdown_HTML($bank_account_type_is_read_only)
	{

		if ($bank_account_type_is_read_only)
		{
			$dropdowns = array('income_direct_deposit' => array("yes" => "Yes", "no" => "No"));

		$result = '';
			$this->data->bank_account_type_drop = $this->data->bank_account_type
															. '<input type="hidden" name="bank_account_type" value="'
															. strtolower($this->data->bank_account_type) . '" />';
		}
		else
		{
			$dropdowns = array('bank_account_type' => array("checking" => "Checking", "savings" => "Savings"),
					   'income_direct_deposit' => array("yes" => "Yes", "no" => "No"));
		}

		//die($this->data->level1);
		// Go to the initialize-then-overwrite method of defaulting.  The foreach ($dropdowns below overwrites this, it may not be obvious.
		$this->data->fund_amount_drop = $this->data->fund_amount .
			'<input type="hidden" id="fund_amount" name="fund_amount" value="'. $this->data->fund_amount .'">';
		if(($this->data->level1 == 'verification') ||
		   ($this->data->level1 == 'underwriting') ||
		   ($this->data->status == 'in_process'))
		{
			$fund_amount_array = empty($this->data->fund_amount_array)?array():$this->data->fund_amount_array;
			
			if (count($fund_amount_array) > 0) 
			{
				$dropdowns['fund_amount'] = array();
				foreach($fund_amount_array as $loan_amount)
				{
					$dropdowns['fund_amount']["{$loan_amount}.00"] = "{$loan_amount}.00";
				}
			}
		}		

		foreach($dropdowns as $name => $drop_array)
		{
			$drop = new Dropdown_Generic($drop_array);
			$drop->setUnselected(FALSE);
			$drop->setName($name);

			if( isset($this->data->{$name}) )
			{
				
				$drop->setSelected(strtolower($this->data->{$name}));
			}

			$drop_name = $name . "_drop";

			$this->data->{$drop_name} = $drop->display(TRUE);
		}


		// Create the state dropdown
		
		$l_state_dd = new State_Selection();
		$this->data->legal_id_state_drop = $l_state_dd->State_Pulldown ("legal_id_state", 0, 0, isset($this->data->saved_error_data->legal_id_state) ? $this->data->saved_error_data->legal_id_state: $this->data->legal_id_state, true, "", 0, false, false, NULL, NULL, NULL,'EditAppPersonalInfoCustLegalIdState');
		$state_dd = new State_Selection();
		$this->data->state_drop = $state_dd->State_Pulldown ("state", 0, 0, isset($this->data->saved_error_data->state) ? $this->data->saved_error_data->state: $this->data->state, true, "", 0, false, false, NULL, NULL, NULL,'state');
		$this->data->state_drop_personal_info = $state_dd->State_Pulldown ("state", 0, 0, isset($this->data->saved_error_data->state) ? $this->data->saved_error_data->state: $this->data->state, true, "", 0, false, false, NULL, NULL, NULL,'EditAppPersonalInfoCustState');
		
		$dob_drop = new Dropdown_Dates();
		$dob_drop->Set_Prefix("dob_");
		$dob_drop->Set_Day($this->data->dob_day);
		$dob_drop->Set_Month($this->data->dob_month);
		$dob_drop->Set_Year($this->data->dob_year);
		$dob_drop->Set_Range("years",(int)date("Y", strtotime("last year")),1905);
		$this->data->dob_drop = $dob_drop->Fetch_Drop_All();
		
		$dob_drop->Set_Prefix("EditAppPersonalInfoCustDob");
		$this->data->dob_drop_personal_details = $dob_drop->Fetch_Drop_All();
		
		return TRUE;

	}

	
	/**
	 * @desc:
	 *		This function takes the application status and the sent docs and reutrns
	 *		TRUE if the application status is 'pending' and one of the sent docs
	 *		is 'ecash30_Loan Document.
	 *
	 * @parm
	 *		$status -the application status
	 *		$docs - an array of sent documents
	 *
	 *	@return
	 *		$result - This is TRUE or FALSE
	 */
	protected function Are_Docs_Received_And_Status_Pending($status, $docs)
	{
		$result = FALSE;

		if ($status == 'pending')
		{
			foreach ($docs as $key => $value)
			{
				if ($value->getname() == 'ecash30_Loan Document')
				{
					$result = TRUE;
					break;
				}

			}
		}

		return $result;
	}

	protected function Set_Button_HTML()
	{
		/*
		* New global value cs_withdraw_disabled to control withdraw
		* button on Customer Service Screen [KeithM]
		* New global value cs_reverify_disabled to control verify
		* button on Customer Service Screen [KeithM]
		*/

		/**
		 * Helper for client_code_displayoverview_setbuttonhtml().  [BR]
		 */
		$application = ECash::getApplicationById($this->data->application_id);
		$this->data->application_status_string = $application->getStatus()->getApplicationStatus();

		/**
		 * This abhorrition is for Agean's PD/AT Reminder Queues.
		 */
		$isPD = false;
		$isAT = false;
		$qm = ECash::getFactory()->getQueueManager();

		if($qm->hasQueue('pd_reminder_queue'))
		{
			$isPD = $qm->getQueue('pd_reminder_queue')->entryExists($qm->getQueue('pd_reminder_queue')->getNewQueueItem($this->data->application_id));
		}

		if($qm->hasQueue('at_reminder_queue'))
		{
			$isAT = $qm->getQueue('at_reminder_queue')->entryExists($qm->getQueue('at_reminder_queue')->getNewQueueItem($this->data->application_id));	
		}

		if($isAT || $isPD)
		{
			$this->data->REMOVE_REMINDER_BUTTON = "<div class=\"app_button\"><input type=\"button\" name=\"submit_button\" value=\"Remove From Queue\" class=\"button2%%%reminder_queue_disabled%%%\" onClick=\"ReminderRemove();\"%%%reminder_queue_disabled%%%></div>";
		}
		else
		{
			$this->data->REMOVE_REMINDER_BUTTON = "";
		}

		// Only allow Fund available for confirmed statuses.
		if (($this->data->level2 == "applicant") ||
			($this->data->level1 == "applicant") ||
			($this->data->status == 'funding_failed') ||
		    (($this->data->level1 == "prospect") && ($this->data->status == "confirmed")) ||
		    ($this->Are_Docs_Received_And_Status_Pending($this->data->status, $this->data->docs))
		    )
		{
			if(empty($this->data->fund_warning))
			{
				$this->data->fund_warning = "";
				$this->data->fund_button_disabled 		= "";
			}
			else
			{
				$this->data->fund_button_disabled 		= "disabled";
			}
			$this->data->approve_button_disabled 	= "";
		}
		else
		{
			$this->data->fund_button_disabled 		= "disabled";
			$this->data->approve_button_disabled 	= "disabled";
		}

		//mantis:4648
		if (!empty($this->data->do_not_loan))
		{
			$this->data->approve_button_disabled 	= "disabled";
			$this->data->fund_button_disabled 		= "disabled";
		}

		if($this->Get_Section_ID_by_Name($this->Get_Section_ID(), 'reprocess'))
		{
			$this->data->reprocess_button_disabled = "";
		}
		else
		{
			$this->data->reprocess_button_disabled = "disabled";
		}

		//default fraud/risk release to disabled
		$this->data->release_verify_button_disabled			= "disabled";
		$this->data->release_underwriting_button_disabled	= "disabled";
		$this->data->af_override_button_disabled = "disabled";

		if($this->data->level1 == "fraud")
		{
			$this->data->release_verify_button_disabled = '';
		}
		if($this->data->level1 == "high_risk")
		{
			$this->data->release_underwriting_button_disabled = '';
		}

		// Default to allow
		$this->data->follow_up_disabled		= "";
		$this->data->cs_withdraw_disabled	= "";
		$this->data->cs_reverify_disabled	= "disabled";
		$this->data->deny_button_disabled	= "";
		$this->data->withdraw_disabled		= "";
		$this->data->send_to_cccs_disabled  = "";
		$this->data->send_to_second_tier_disabled = "";
		$this->data->bankruptcy_notification_disabled = "";
		$this->data->bankruptcy_verified_disabled = "";
		$this->data->inprocess_button_disabled = "disabled";
		$this->data->addl_button_disabled	= "";

		// Skip Trace Button conditional display
		if($this->data->status == "skip_trace")
		{
			$this->data->skip_trace_display = "Remove Skip Trace";
			$this->data->skip_trace_short = "Remove_Skip_Trace";
		}
		else
		{
			$this->data->skip_trace_display = "Skip Trace";
			$this->data->skip_trace_short = "Skip_Trace";
		}

		if($this->module_name == 'collections' && $this->data->status != 'denied')
			$this->data->follow_up_disabled = "";

		if($this->data->level1 == 'verification' && $this->data->status == 'follow_up')
		{
			$this->data->deny_button_disabled	= "";
			$this->data->withdraw_disabled		= "";
			$this->data->cs_withdraw_disabled	= "";
		}

		if ($this->data->level1 == 'external_collections' || $this->data->level1 == 'customer' || $this->data->level2 == 'customer' || $this->data->level3 == 'customer')
		{
			$this->data->cs_reverify_disabled = "disabled";
		}				

		if ($this->data->level1 == 'underwriting')
		{
			$this->data->cs_reverify_disabled = "";
		}				
		
		if(in_array($this->data->status,array('paid','recovered','') ))
		{
			$this->data->withdraw_disabled		= "disabled";
			$this->data->cs_withdraw_disabled	= "disabled";
		}

		$this->data->resig_disabled	= "disabled";		
		if($this->data->status == 'in_process')
		{
			$this->data->resig_disabled	= "";
		}

                $debit_achs = 0;
                if ($this->data->application_status_id == 20) {
                        if (round($this->data->schedule_status->posted_total, 2) < 200) {
                                foreach ($this->data->schedule_status->debits as $debit) {
                                        if ($debit->ach_id && !(array_search($debit->type_id, $this->refi_qual_ach_pmt_types) === false)) {
                                                $debit_achs++;
                                        }
                                }
                        }
                }
                $this->data->refi_button_disabled = ($debit_achs) ? "" : "disabled";

		// Run this AFTER all the fund button checks are determined		
		require_once(CUSTOMER_LIB."/client_code_displayoverview_setbuttonhtml.func.php");
		client_code_displayoverview_setbuttonhtml($this->data);

		// [#18090]
		if($this->data->status == 'paid')
		{
			$this->data->approve_button_disabled = "disabled";
			$this->data->deny_button_disabled = "disabled";
			$this->data->withdraw_disabled = "disabled";
			$this->data->send_confirmation_disabled = "disabled";
			//$this->data->follow_up_disabled = "disabled";
			$this->data->cs_reverify_disabled	= "disabled";
			$this->data->bankruptcy_notification_disabled = "disabled";
			$this->data->place_in_hold_disabled = "disabled";
			$this->data->amortization_disabled = "disabled";
			$this->data->bankruptcy_verified_disabled = "disabled";
			$this->data->send_to_second_tier_disabled = "disabled";
			$this->data->send_to_cccs_disabled = "disabled";
			$this->data->claim_app_button_disabled = "disabled";
		}
		
		
		// If the application does not have payment arrangements, it can be claimed.
		if($this->data->has_payment_arrangements === 0)
		{
			$disabled = isset($this->data->claim_app_button_disabled) ? $this->data->claim_app_button_disabled : '';
			$this->data->CLAIM_APP_BUTTON = "<td><input type=\"button\" {$disabled} id=\"AppActionClaimApp\"name=\"submit_button\" value=\"Claim App\" class=\"button2{$disabled}\" onClick=\"ClaimApp();\"></td>";
		}
		else
		{
			$this->data->CLAIM_APP_BUTTON = "";
		}
		
	}

	protected function Setup_Overlink_Data()
	{
		$newdata = (object) array();

		// Replace the edit text color and setup error code mouseovers
		foreach($this->data as $field => $value)
		{
			$field_color = $field . "_color";
			$field_overlink = $field . "_overlink";
			$field_endlink = $field . "_endlink";

			if(isset($this->data) && is_object($this->data)
				&& isset($this->data->validation_errors) &&  isset($this->data->validation_errors[$field]))
			{
				$newdata->{$field_color} = "error_text";
				$newdata->{$field_overlink} = "<a class=\"error_text\" href=\"javascript:void(0);\" onmouseover=\"return overlib('".$this->data->validation_errors[$field]."', RIGHT, ABOVE);\" onmouseout=\"return nd();\">";
				$newdata->{$field_endlink} = "</a>";
			}
			else
			{
				$newdata->{$field_color} = "std_text";
				$newdata->{$field_overlink} = "";
				$newdata->{$field_endlink} = "";
			}
		}
		$this->data = (object) array_merge((array) $this->data, (array) $newdata);

	}

	protected function Trim_Fields()
	{
		$keys = array_keys((array)$this->data);
		foreach($keys as $field)
		{
			$value = $this->data->{$field};
			//if(!is_array($value) && !is_object($value) && strlen($field) > 0)
			if (!is_array($value) && !is_object($value) && strlen($field) > 0)
			{
				if ((strlen($value) < 20) 	|| ($field == 'income_frequency') 
								|| ($field == 'customer_email')
								|| ($field == 'web_url')) //mantis:8156
				{
					switch($field)
					{

						case 'customer_email':
							$this->data->{$field . '_trim'} = substr($value, 0, 42);
							break;
						case 'legal_id_number':
						case 'unit':
							$this->data->{$field . '_trim'} = strtoupper($value);
							break;
						case 'phone_work_ext':
							$this->data->{$field . '_trim'} = !empty($value) ? "<span class=\"subtext\">x{$value}</span>" : '';
							break;
						default:
							$this->data->{$field . '_trim'} = stripslashes($value);
							break;
					}
				}
				else
				{
					$this->data->{$field . '_trim'} = stripslashes(substr($value, 0, 25));
				}
			}
		}
	}

	protected function Set_Contact_Data()
	{
		$contact_fields = array('job_title','employer_name','email','bank_name','street', 'customer_email', 'phone_home', 'phone_cell', 'phone_work', 'phone_work_ext', 'ref_phone_1', 'ref_phone_2', 'name', 'ssn', 'bank_account', 'zip_code', 'city');

		//mantis:4646
		//display precedence (ecash only displays one icon at a time)
		$attribute_precedence = array(
								 'bad_info' => 0,
								 'do_not_contact' => 1,
								 'best_contact' => 2,
								 'do_not_market' => 3,
								 'high_risk' => 4,
								 'fraud' => 5,
								 'do_not_loan' => 6,
								 );

		$attribute_icons = array(
			'bad_info' => '<img src="/image/standard/i_bad_info.gif" border="0" alt="Bad Info">',
			'do_not_contact' => '<img src="/image/standard/i_do_not_contact.gif" border="0" alt="Do Not Contact">',
			'best_contact' => '<img src="/image/standard/i_best_contact.gif" border="0" alt="Best Contact">',
			'do_not_market' => '<img src="/image/standard/i_do_not_market.gif" border="0" alt="Do Not Market">',
			'high_risk' => '<img src="/image/standard/high_risk.gif" border="0" alt="High Risk" onmouseover="return overlib(\'High Risk\', CENTER, ABOVE);" onmouseout="return nd();">',
			'fraud' => '<img src="/image/standard/fraud.gif" border="0" alt="Fraud" onmouseover="return overlib(\'Fraud\', CENTER, ABOVE);" onmouseout="return nd();">',
			'do_not_loan' => '<img src="/image/standard/i_do_not_loan.gif" border="0" alt="Do Not Loan">',
			);

		//for keeping track of what icon is currently set
		$precedence_cache = array();
		$fraud_precedence = 0;
		//to use to pass to the pop-up window
		$attribute_fields = array();
		$this->data->fraud_ind = '';
		$has_login_lock = FALSE;
		//echo '<pre>'.print_r($this->data->notifications,true).'</pre>';
		foreach($this->data->notifications as $attribute_row)
		{
			if(in_array($attribute_row->column_name, array('name_first', 'name_last', 'name_middle')))
			{
				//combine name fields
				$attribute_row->column_name = "name";
			}

			// GF #12043: Consolidate City and State fields due to flag indicator not showing for combined city, state field
			// I personally think this entire thing is pretty hackish causing this patch in the first place. [benb]
			if (in_array($attribute_row->column_name, array('city', 'state')))
			{
				$attribute_row->column_name = 'city';
			}

			$contact_name = "contact_" . $attribute_row->column_name;

			//only (over)write the icon if it's not set or the new one has a higher precedence than the current
			if(!isset($this->data->$contact_name) ||
			   $precedence_cache[$attribute_row->column_name] < $attribute_precedence[$attribute_row->field_name])
			{
				//set the icon for the field
				$this->data->$contact_name = $attribute_icons[$attribute_row->field_name] . "&nbsp;&nbsp;";

				//record the new precedence
				$precedence_cache[$attribute_row->column_name] = $attribute_precedence[$attribute_row->field_name];
			}
			
			//set the fraud indicator for fraud
			if(($attribute_row->field_name == 'high_risk' || $attribute_row->field_name == 'fraud') &&
			   ($attribute_precedence[$attribute_row->field_name] > $fraud_precedence))
			{
				$fraud_precedence = $attribute_precedence[$attribute_row->field_name];
				$this->data->fraud_ind = $attribute_icons[$attribute_row->field_name] . "&nbsp;";
			}

			if($attribute_row->field_name == 'login_lock')
			{
				$has_login_lock = TRUE;
			}

			$attribute_fields[$attribute_row->field_name][] = $attribute_row->column_name;
		}

		// Application flag stuff should be set here if relevant.
		$this->data->cust_no_ach_link = 'onClick="javascript:OpenDialog(\'/?action=display_application_flag_modify&amp;flag=cust_no_ach&amp;application_id=' 
			. $this->data->application_id . "&module=" . $this->data->current_module . "&mode=" . $this->data->current_mode . '\')"';

		$this->data->cust_no_ach_ind = '';
		if (isset($this->data->flags['cust_no_ach'])) 
		{
			$this->data->cust_no_ach_ind = "<span onmouseover=\"this.firstChild.style.visibility='visible'\" onmouseout=\"this.firstChild.style.visibility='hidden';\" class=\"cust_no_ach_ind\"><div class=\"flag_help_overlay\" onmouseout=\"this.style.visibility = 'hidden'\"><p><span>{$this->data->flags['cust_no_ach']}</span></p><button id=\"modify_ach_button\" {$this->data->cust_no_ach_link}>Modify This Application Flag</button></div>ACH</span>";
		}

		$this->data->cust_has_fatal_ach_ind = '';
		if (isset($this->data->flags['has_fatal_card_failure']) ) 
		{
			$this->data->cust_has_fatal_ach_ind = "<span class=\"cust_has_fatal_card_ind\">FATAL</span>";
		}
		if (isset($this->data->flags['has_fatal_ach_failure']) ) 
		{
			$this->data->cust_has_fatal_ach_ind = "<span class=\"cust_has_fatal_ach_ind\">FATAL</span>";
		}

		//do this seperate of the other attributes
		$this->data->login_lock_flag = '';
		if($has_login_lock)
		{
			$this->data->login_lock_flag = '<img src="/image/standard/login_lock.png" border="0" alt="Unlock Application Login" '.
				"onclick=\"javascript:OpenDialogSized('/?action=get_login_lock&amp;application_id={$this->data->application_id}".
				"&company_id={$this->data->company_id}', 400, 200);\" />";
		}
		
		//set the unset icon fields to blank
		foreach($contact_fields as $contact_field)
		{
			$contact_name = "contact_" . $contact_field;
			if(!isset($this->data->$contact_name)) $this->data->$contact_name = "&nbsp;";
		}

		//mantis:4646
		foreach($attribute_precedence as $attribute => $precedence)
		{
			$var_name = $attribute . "_link";
			$label = ucwords(str_replace("_", " ", $attribute));
			$this->data->{$attribute . '_disabled'} = '';
			if (in_array($attribute, $this->data->read_only_fields))
			{
				$this->data->$var_name = ''; //no link if disabled
				$this->data->{$attribute . '_disabled'} = 'disabled';
			}
			else
			{
				//build a checked_fields param for the popup
				$checked_fields = "";
				if(!empty($attribute_fields[$attribute]))
					$checked_fields = implode(',', $attribute_fields[$attribute]);
				//link is different for do_not_loan

				// GF #16644: Remove checking if read only for application attributes. [benb]
				if($attribute == 'do_not_loan')
				{
					$name = $this->data->name_first . " " . $this->data->name_last;
					$this->data->do_not_loan_link = "onClick=\"javascript:OpenDialogSized(\'/?action=get_dnl&amp;application_id=".$this->data->application_id."&name={$name}&ssn={$this->data->ssn}&company_id={$this->data->company_id}\', 400, 200);\"";
				}
				else
				{
					$this->data->$var_name =
						'onClick="javascript:OpenDialogSized(\'/contact_setting.php?&application_id=' .
						$this->data->application_id . '&setting='.$attribute.
						'&icon='.urlencode($attribute_icons[$attribute]).'&checked_field=' .
						$checked_fields . '\', \'350\', \'300\');"';
				}
			}
		}
		//end mantis:4646
		// breaking the app attribute buttons out into a seperate file
		$this->data->app_attribute_buttons = "";

		//[#29877]
		$this->data->clear_fatal_ach_disabled = 'disabled';
		if (isset($this->data->flags['has_fatal_ach_failure']) &&
			$this->acl->Acl_Check_For_Access(array($this->data->current_module, $this->data->current_mode, 'application_flags', 'clear_fatal_ach')))
		{
			$this->data->clear_fatal_ach_disabled = '';
		}		
		
		$this->data->clear_fatal_card_disabled = 'disabled';
		if (isset($this->data->flags['has_fatal_card_failure']) &&
			$this->acl->Acl_Check_For_Access(array($this->data->current_module, $this->data->current_mode, 'application_flags', 'clear_fatal_card')))
		{
			$this->data->clear_fatal_card_disabled = '';
		}		
		
		if( file_exists(CUSTOMER_LIB ."/view/app_attribute_buttons.html"))
		{
			$app_attribute_html = file_get_contents(CUSTOMER_LIB . "/view/app_attribute_buttons.html");
			$this->data->app_attribute_buttons = Display_Utility::Token_Replace($app_attribute_html, (array)$this->data);			
		}
		elseif ($app_attribute_html = file_get_contents(CLIENT_VIEW_DIR . "app_attribute_buttons.html"))
		{
			$this->data->app_attribute_buttons = Display_Utility::Token_Replace($app_attribute_html, (array)$this->data);
		}

	}

	//mantis:4360
	protected function Set_DNL_Flag()
	{
		//$this->data->contact_ssn = "";
		if($this->data->do_not_loan)
		{
			$this->data->contact_ssn = '<img src="/image/standard/i_do_not_loan.gif" border="0">';
		}

		if (in_array("do_not_loan", $this->data->read_only_fields))
			$this->data->do_not_loan_link = 'Do Not Loan';
		else
		{
			$total_dnl = 0;
			foreach($this->data->dnl_info as $dnl_record)
			{
				if($dnl_record->active_status == 'active')
					++$total_dnl;
			}

			$vsize = 0;
			if($total_dnl == 1)
				$vsize = 210;
			else if($total_dnl == 2)
				$vsize = 420;
			else if($total_dnl == 3)
				$vsize = 580;
			else if($total_dnl == 4)
				$vsize = 700;
			else if($total_dnl == 5)
				$vsize = 850;
			if(!$this->data->is_dnl_set_for_company)
				$vsize += 230;


			$name = $this->data->name_first . " " . $this->data->name_last;
			$this->data->do_not_loan_link = "onClick=\"javascript:OpenDialogSized(\'/?action=get_dnl&amp;application_id=".$this->data->application_id."&name={$name}&ssn={$this->data->ssn}&company_id={$this->data->company_id}\', 400, 200);\"";
			
		}
	}

	//mantis:4360
	protected function Set_DNL_Info_Line()
	{
		$explanation = '';
		$agent_name = '';
		$js_comment = '';
		$dnl_comment = '';
		$this->data->dnl_info_line = '';
		if((!empty($this->data->do_not_loan) || !empty($this->data->is_dnl_set)) && !empty($this->data->dnl_info))
		{
			$explanation = str_replace("'", "\'", $this->data->dnl_info[0]->explanation); //mantis:6054
			$explanation = str_replace('"', '', $explanation);			      //mantis:6054
			$agent_name = $this->data->dnl_info[0]->name_last . ', ' . $this->data->dnl_info[0]->name_first;

			$js_comment = "com=window.open('','comment','height=150,width=350,scrollbars=1,status=0,screenX=200,screenY=200;');";
			$js_comment .= "com.document.write('<html><head><title>DNL Info</title><style>body{font-family:arial,sans-serif;}</style></head><body>";
			$js_comment .= "<b>Category:</b>  <font size=-1>{$this->data->dnl_info[0]->name}</font>";
			$js_comment .= "<br><b>Explanation:</b><br><font size=-1>{$explanation}</font>";
			$js_comment .= "<br><b>Company:</b>  <font size=-1>{$this->data->dnl_info[0]->company_name}</font>";
			$js_comment .= "<br><b>DNL Set Date:</b>  <font size=-1>{$this->data->dnl_info[0]->date_created}</font>";
			$js_comment .= "<br><b>Agent:</b>  <font size=-1>{$agent_name}</font>";
			$js_comment .= "</body></html');com.document.close();com.focus();";

			$dnl_comment = substr($this->data->dnl_info[0]->explanation, 0, 30);
		}


		if(!empty($this->data->do_not_loan))
		{
			$this->data->dnl_info_line = '
							<tr class="height" bgcolor="red">
								<td style="text-align: left" width="10%"><a href="#" onClick="javascript:void(0); ' . $js_comment . '"; style="color:black; text-decoration: none;">DNL: </a></td>
								<td style="text-align: left" width="30%"><a href="#" onClick="javascript:void(0); ' . $js_comment . '"; style="color:black; text-decoration: none;">' . ucwords(str_replace('_', ' ', $this->data->dnl_info[0]->name)) . '</a></td>
								<td style="text-align: left; white-space: nowrap" width="60%"><a href="#" onClick="javascript:void(0); ' . $js_comment . '"; style="color:black; text-decoration: none;">' . $dnl_comment . '</a></td>
							</tr>
							';
		}
		else if(!empty($this->data->is_dnl_set))
		{
			$this->data->dnl_info_line = '
							<tr class="height" bgcolor="yellow">
								<td style="text-align: left" width="10%"><a href="#" onClick="javascript:void(0); ' . $js_comment . '"; style="color:black; text-decoration: none;">DNL: </a></td>
								<td style="text-align: left" width="30%"><a href="#" onClick="javascript:void(0); ' . $js_comment . '"; style="color:black; text-decoration: none;">' . ucwords(str_replace('_', ' ', $this->data->dnl_info[0]->name)) . '</a></td>
								<td style="text-align: left; white-space: nowrap" width="60%"><a href="#" onClick="javascript:void(0); ' . $js_comment . '"; style="color:black; text-decoration: none;">' . $dnl_comment . '</a></td>
							</tr>
							';
		}
		else
		{
			$this->data->dnl_info_line = '';
		}
	}


	protected function OLP_Phone_Fmt ($phone_number, $format='formatted')
	{
		$result = str_replace(array('(', ')', '-', ' '), array(''), $phone_number);

		if ( $format == 'formatted' && !empty($result) )
		{
			$result = substr($result, 0, 3) . '-' . substr($result, 3, 3) . '-' . substr($result, 6, 4);
		}

		return $result;
	}

	// Rewrite to properly implement... like the rest of eCash.
	protected function Set_Transactional_Data($is_transactional_data_read_only)
	{
		$this->data->transaction_display_row = "none";
		$this->data->document_display_row = "table-cell";
		$this->data->schedule_buttons = "schedule_buttons";
		for ($i = 1; $i < 12; $i++)
		{
			$cell = "tdr_{$i}";
			$this->data->$cell = "none";
		}

		switch (TRUE)
		{
//			case (preg_match('/underwriting|verification/', $this->mode) == 1 && $this->data->loan_type_model != 'Title'):
//				$this->data->transaction_display_row = "none";
//				break;
			case (preg_match('/underwriting|verification/', $this->mode) == 1 ):
				$this->data->transaction_display_row = "table-cell";
				break;
			case ($this->module_name == 'collections'):
				$this->data->transaction_display_row = "table-cell";
				$this->data->trans_submenu_left = "620";
				$this->data->tdr_1 = "inline";
				$this->data->tdr_2 = "inline";
				$this->data->tdr_4 = "inline";
				$this->data->tdr_8 = "inline";
				$this->data->tdr_12 = "inline";
				$this->data->tdr_menu_height = "auto";
				break;
			case ($this->mode == 'account_mgmt'):
				$this->data->transaction_display_row = "table-cell";
				$this->data->trans_submenu_left = "620";
				$this->data->tdr_1 = "inline";
				$this->data->tdr_2 = "inline";
				$this->data->tdr_3 = "inline";
				$this->data->tdr_4 = "inline";
				$this->data->tdr_5 = "inline";
				$this->data->tdr_6 = "inline";
				$this->data->tdr_8 = "inline";
				$this->data->tdr_9 = "inline";
				$this->data->tdr_10 = "inline";
				$this->data->tdr_12 = "inline";
				if ($this->data->schedule_status->reversible)
				{
					$this->data->tdr_11 = "inline";
					$this->data->tdr_menu_height = "168px";
				}
				else
				{
					$this->data->tdr_menu_height = "153px";
				}
				break;
			case ($this->module_name == 'conversion'):
				$this->data->schedule_buttons = "conversion_buttons";
				$this->data->document_display_row = "none";
				$this->data->transaction_display_row = "table-cell";
				$this->data->trans_submenu_left = "620";
				$this->data->tdr_1 = "inline";
				if ($this->data->schedule_status->posted_total > 0)
				{
					$this->data->tdr_2 = "inline";
					$this->data->tdr_3 = "inline";
					$this->data->tdr_4 = "inline";
					$this->data->tdr_5 = "inline";
					$this->data->tdr_6 = "inline";
					$this->data->tdr_8 = "inline";
					$this->data->tdr_menu_height = "106px";
				}
				else
				{
					$this->data->tdr_menu_height = "16px";
				}
				break;
			/**/
			case ($this->module_name == 'fraud'):
				$this->data->transaction_display_row = "table-cell";
				$this->data->trans_submenu_left = "620";
				$this->data->tdr_2 = "inline";
				$this->data->tdr_menu_height = "15px";
				break;
			/**/
			case ($this->module_name == 'watch'):
				$this->data->transaction_display_row = "table-cell";
				$this->data->trans_submenu_left = "620";
				$this->data->tdr_2 = "inline";
				$this->data->tdr_menu_height = "15px";
				break;
			/**/
			case ($this->module_name == 'watch'):
			case ($this->module_name == 'loan_servicing'):
				$this->data->transaction_display_row = "table-cell";
				$this->data->trans_submenu_left = "620";
				$this->data->tdr_menu_height = ""; // Height was too short, but displaying no height in the CSS menus allows the browser to size it to fit.
				$this->data->tdr_1 = "inline";
				break;
		}

		// If the menu isn't even visible, don't bother with the rest.
		if ($this->data->transaction_display_row == "none") return;

		// Do the scheduling table
		$list = array("posted_principal", "posted_total", "pending_principal",
			      "pending_total", "pending_fees", "posted_fees", "paid_fees", "paid_interest", 'paid_principal',
			      "pending_interest", "posted_interest", "running_interest",
			      "running_total", "running_principal", "posted_and_pending_interest");

		foreach ($list as $item)
		{
			if ($item != 'posted_principal' && $item != 'posted_total')
			{
				$this->data->$item = number_format(abs($this->data->schedule_status->$item), 2, '.','');
			}
			else 
			{
				$this->data->$item = number_format($this->data->schedule_status->$item, 2, '.','');
			}
		}
		$this->data->paid_fee_count = $this->data->schedule_status->paid_fee_count;
		$this->data->paid_interest_count = $this->data->schedule_status->paid_interest_count;
		$this->data->posted_pending_total = number_format($this->data->schedule_status->posted_and_pending_total, 2, '.', '');
		$this->data->posted_and_pending_fees = number_format($this->data->schedule_status->posted_and_pending_fees, 2, '.', '');
		$this->data->initial_principal = $this->data->fund_amount_trim;
		if($this->data->posted_principal <> 0)
		{
			$this->data->principal_repaid = number_format( ($this->data->schedule_status->initial_principal - $this->data->posted_principal), 2, '.','');
		}
		else
		{
			$this->data->principal_repaid = '0.00';
		}
		$this->data->posted_service_charge_count = $this->data->schedule_status->posted_service_charge_count;
		$this->data->posted_and_pending_principal = $this->data->schedule_status->posted_and_pending_principal;
		$this->data->posted_service_charge_total = number_format( (isset($this->data->schedule_status->posted_service_charge_total) ? $this->data->schedule_status->posted_service_charge_total : 0), 2, '.','');
		$this->data->current_daily_interest = number_format(isset($this->data->current_daily_interest) ? $this->data->current_daily_interest : 0, 2, '.', '');


		$this->data->schedule_rows = $this->Print_Schedule($is_transactional_data_read_only);

		$this->data->total_paid = number_format( abs( $this->data->schedule_status->total_paid ), 2, '.', '' );

		$this->data->last_service_charge_date = isset($this->data->schedule_status->last_service_charge_date) ? $this->data->schedule_status->last_service_charge_date : null;
		$this->data->last_pending_service_charge_date = isset($this->data->schedule_status->last_pending_service_charge_date) ? $this->data->schedule_status->last_pending_service_charge_date : null;
	}

	
	protected function Print_Schedule($is_transactional_data_read_only)
	{
		require_once('render_transactions_table.class.php');
		$trans_render = new Render_Transactions_Table();
		$formated_schedule = $trans_render->format_schedule($is_transactional_data_read_only, $this->data->schedule_status->posted_schedule,$this->data->schedule_status, $this->data->business_rules);
		return $trans_render->Build_Schedule($formated_schedule,$this->mode);
	
	}
	
	protected function Check_Hold_Status()
	{
		// Check to see if the user is in a hold state
		// If they are, display a Return to Active button
		if ($this->data->status == 'hold')
		{
				$this->data->hold_button_label = "Return to Active";
				$this->data->hold_button_action = "return_from_service_hold";
		}
		// They're not in a hold state so display a button to put them in a hold state
		else
		{
			$this->data->hold_button_label = "Place in Hold";
			$this->data->hold_button_action = "place_in_hold_status";
		}
	}

	/**
	 * Checks the status of the account and provides the appropriate buttons
	 * for Amortization.
	 */
	protected function Check_Amortization_Status()
	{
		if ($this->data->status == 'amortization')
		{
				$this->data->amortization_button_label = "Dissolve Amortization";
				$this->data->amortization_button_action = "dissolve_amortization";
		}
		else
		{
			$this->data->amortization_button_label = "Amortization";
			$this->data->amortization_button_action = "place_in_amortization";
		}
	}

	/**
	 * If we're in either the Loan Servicing or Fraud module, the applicant is in either a customer
	 * or an applicant status, and the agent has access to the Fraud module, then we're able to add 
	 * or remove the watch flag.  Otherwise, if the customer already has the watch flag, then we can remove 
	 * the watch flag regardless of the status or module.
	 */
	protected function Check_Watch_Status()
	{
		$this->data->watch_button = '<input id=\"AppActionWatch\" type="button" disabled name="submit_button" class="button2disabled" value="Add Watch Flag" >';
		$application_id = $this->data->application_id;

		if (($this->module_name === 'loan_servicing' || $this->module_name === 'fraud') 
		&& ($this->data->level2 === 'applicant' 
		||  $this->data->level2 === 'customer' 
		||  $this->data->level3 === 'customer' ))
		{
			if (in_array('fraud', ECash::getTransport()->user_acl))
			{
				
				if ($this->data->model->is_watched == 'no')
				{
					$this->data->watch_button = '<input id=\"AppActionWatch\" type="button" name="submit_button" class="button2autosize" value="Add Watch Flag" onClick="javascript:OpenDialog(\'/?module=fraud&amp;action=add_watch_status&amp;action_type=fetch&amp;mode=watch&amp;previous_module=' . $this->data->current_module . '&previous_mode=' . $this->mode . '&application_id=' . $application_id . '\');">';
				}
				else
				{
					$this->data->watch_button = '<input id=\"AppActionWatch\" type="button" name="submit_button" class="button2autosize" value="Remove Watch Flag" onClick="javascript:OpenDialog(\'/?module=fraud&amp;action=remove_watch_status&amp;action_type=fetch&amp;mode=watch&amp;previous_module=' . $this->data->current_module . '&previous_mode=' . $this->mode . '&application_id=' . $application_id . '\');">';
				}
			}
		}
		elseif ($this->data->model->is_watched == 'yes') // This is just a fix for test accounts that have no balance but have a watch status
		{
			$this->data->watch_button = '<input id=\"AppActionWatch\" type="button" name="submit_button" class="button2autosize" value="Remove Watch Flag" onClick="javascript:OpenDialog(\'/?module=fraud&amp;action=remove_watch_status&amp;action_type=fetch&amp;mode=watch&amp;previous_module=' . $this->data->current_module . '&application_id=' . $application_id . '\');">';
		}
	}

	private function createRulesList()
	{
		//we're going to make two dropdowns that interact with each other via javascript
		require_once CUSTOMER_LIB . 'reprocessor.class.php';
		$target = Reprocessor::getTarget();
		
		$skip_rules = array('CFCDUPE90', 'CFCDUPE180');
				
		$ruleset_list = $target->getRuleSetList(); // array(id => timestamp)

		$this->data->reprocess_ruleset_drop = "<select id='ruleset_id' name='ruleset_id' onChange='javascript:build_reprocess_steps();'>\n";
		$reprocess_rules_all = array();		
		foreach($ruleset_list as $id => $timestamp)
		{
			$i = 1;
			$this->data->reprocess_ruleset_drop .= "<option value='{$id}'>" . date('Y-m-d', $timestamp) . "</option>\n";
			$rules = $target->getRulesInSet($id);
			$reprocess_rules_all[$id] = array();
			foreach($rules as $step_id => $name_short)
			{
				if(!in_array($name_short, $skip_rules))
				{
					$reprocess_rules_all[$id][$step_id] = "step {$i}: " . Get_Loan_Action_Description_By_Name($name_short) . ' check';
					$i++;
				}
			}
		}

		$this->data->reprocess_ruleset_drop .= "</select>\n";
		$this->data->js_reprocess_rules_all = json_encode($reprocess_rules_all);
	}

	private function createTenureDrop()
	{
		$current_tenure = empty($this->data->job_tenure) ? 0 : $this->data->job_tenure;
		$tenures = array(
						0 => '',
						1 => '0 to 6 months',
						2 => '6 to 12 months',
						3 => '12+ months',
						4 => 'Not Presently Employed',
						5 => 'Retired');
		
		$drop = "<select name='job_tenure'>\n";
						
		foreach($tenures as $key => $value)
		{
			$selected = ($current_tenure == $key) ? ' selected' : '';
			$drop .= "<option value='{$key}'{$selected}>{$value}</option>\n";
		}

		$drop .= '</select>';
		$this->data->tenure_drop = $drop;
		
	/**
	 * 
	 * 		<option value="%%%job_tenure%%%">%%%employment_duration%%%</option>
		<option value='1'>0-6 months</option>
		<option value='2'>6-12 months</option>
		<option value='3'>12+ months</option>
		<option value='4'>Not Presently Employed</option>
		<option value='5'>Retired</option>
	 * 
	 */
						
	}
	private function createSuffixDrop()
	{
		$selected_suffix = $this->data->name_suffix;
		
		$suffixes = array('', 'Jr.','Sr.','II','III','IV');
		$drop = "<select name='name_suffix'>\n";

		foreach($suffixes as $value)
		{
			$selected = ($selected_suffix == $value) ? ' selected' : '';
			$drop .= "<option value='{$value}'{$selected}>{$value}</option>\n";
		}

		$drop .= '</select>';
		$this->data->suffix_drop = $drop;
	}


}


?>

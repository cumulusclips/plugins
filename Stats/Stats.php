<?php

class Stats extends PluginAbstract
{
	/**
	* @var string Name of plugin
	*/
	public $name = 'Stats';

	/**
	* @var string Description of plugin
	*/
	public $description = 'Traffic and system use analysis tools.';

	/**
	* @var string Name of plugin author
	*/
	public $author = 'Justin Henry';

	/**
	* @var string URL to plugin's website
	*/
	public $url = 'https://uvm.edu/~jhenry/';

	/**
	* @var string Current version of plugin
	*/
	public $version = '0.0.2';
	
	/**
	* The plugin's gateway into codebase. Place plugin hook attachments here.
	*/	
	public function load(){
			Plugin::attachEvent ( 'page.start' , array( __CLASS__ , 'setup_stats' ) );		
	}

	/**
	 * Load data and display libraries into the head element.
	 * 
	 */
	public function load_display_libraries(){
		$libraries = file_get_contents(dirname(__FILE__) . '/head.html');
		
		// Recycle current library assets for download buttons.
		$bootstrap_css = '<link rel="stylesheet" type="text/css" href="' . HOST . '/cc-admin/extras/bootstrap-3.3.4/css/bootstrap.min.css">';
		echo $bootstrap_css . "\n" . $libraries;
		
	
	}
	/**
	 * The view hook replaces the body content, so let's add that back in.
	 * Returns the page title and any GUI created content 
	 * so it can be placed at the top of the body.
	 * 
	 */
	public function get_page_content(){
		
		$page_mapper = new PageMapper();
		$stats_page = Settings::get('stats_page');
		
		$page = $page_mapper->getPageById($stats_page);
		$title = "<h1>" . $page->title . "</h1>";
		return $title . $page->content;
	}
	/**
	 * Insert the DOM element into the body for table population.
	 * 
	 */
	public function load_reports(){
		
		$page = Stats::get_page_content();
		
		$public_videos = Stats::public_video_report();
		$cumulative_uploads = Stats::report_cumulative_uploads_by_month();	
		$uploads_by_month = Stats::report_uploads_by_month();	
	
		$page_body = $page . $cumulative_uploads  . $uploads_by_month . $public_videos;
		return $page_body;
	}
	


	
	/**
	 * Set up rows for displaying public videos.
	 * 
	 * var string report_var_prefix A prefix to name Javascript and HTML elements/vars with. 
	 * 
	 */
	public function public_video_report($report_var_prefix = "public_videos"){

		$videoMapper = new VideoMapper();
		$videoService = new VideoService();
		$public_videos = $videoMapper->getMultipleVideosByCustom(array(
					'gated' => 0, 
					'private' => 0
					));
		// Build table array/JSON
		foreach( $public_videos as $video ) {
			$display['id'] = $video->videoId;
			$display['url'] = $videoService->getUrl($video);
			$display['title'] = $video->title;
			$display['published_by'] = $video->username;
			$display['published_on'] = $video->dateCreated;
			$display['views'] = $video->views;
			$rows[] = $display;
		}
		
		$title = "<hr><h2>Public Videos</h2>";
		$title .= "<p>Videos that are set as non-gated (no login required), and for which the user did not designate as private.</p>";
		$table = Stats::build_table_nav($report_var_prefix . "_table");
		$table .= '<div id="public_videos"></div>';
		$table_data = "<script>var " . $report_var_prefix . "_data = " . json_encode($rows) . "</script>";	
		$table_data .= '<script> var ' . $report_var_prefix . '_table = new Tabulator("#' . $report_var_prefix . '", { data:' . $report_var_prefix . '_data, autoColumns:true, height: 500 }); </script>';
		return $title . $table . $table_data; 
	}

	/**
	 * Build table navigation buttons for download, etc.
	 * 
	 */
	public function build_table_nav($id){
		$nav =  '<ul class="nav nav-tabs" >';
		$nav .=	'	<li><a data-tablename="' . $id . '" class="download-table-csv" href="#" id="' . $id . '-csv">Download CSV</a></li>';
		$nav .=	'	<li><a data-tablename="' . $id . '" class="download-table-xlsx" href="#" id="' . $id . '-xlsx">Download Excel (.xlsx)</a></li>';
		$nav .= '</ul>';
		
		return $nav;
		
	}
	
	/**
	 * Check location and access, and call the appropriate hooks.
	 * 
	 */
	public function setup_stats(){
		if( Stats::verify_access() ) {
			Plugin::attachEvent ( 'theme.head' , array( __CLASS__ , 'load_display_libraries' ) );		
			Plugin::attachFilter ( 'view.render_body' , array( __CLASS__ , 'load_reports' ) );		
		}
	}
	
	/**
	 * Uploads per month
	 * 
	 */
	public function report_uploads_by_month(){
		$query = 'SELECT COUNT(video_id) as uploads, DATE_FORMAT(date_created, "%Y-%m") as date from videos group by date;';
		$db = Registry::get('db');
		$results = $db->basicQuery($query);
		
		// Set chart type, title, and other options here.
		$chart['type'] = 'line';
		$chart['options']['title']['display']= true;
		$chart['options']['title']['text']= "Uploads by Month";
		
		// Build chart array/json
		$chart['data']['datasets'] = array();
		$labels = array();
		$data = array();
		foreach( $results as $result) {
			$labels[] = $result['date'];	
			$data[] = $result['uploads'];	
		}
		$chart['data']['labels'] = $labels;	
		$chart['data']['datasets'][0]['data'] = $data;	
		$chart['data']['datasets'][0]['label'] = 'Uploads';	

		return Stats::display_chart($chart, 'uploads_by_month');
	}

	
	/**
	 * Chart display DOM and Javascript elements.
	 * 
	 */
	public function display_chart($chart, $chart_var){
		$display_chart  = '<hr><canvas id="' . $chart_var . '_canvas" width="400" height="250"></canvas>';
		$display_chart .= "<script> var " . $chart_var . "_canvas = document.getElementById('" . $chart_var . "_canvas'); var " . $chart_var . "_chart = new Chart(" . $chart_var . "_canvas," . json_encode($chart) . "); " . $chart_var . "_chart.options.plugins.colorschemes.scheme = randomColorScheme(); " . $chart_var . "_chart.update();</script>";
		return $display_chart;
	}
	/**
	 * Running total of uploads per month, from beginning of time.
	 * 
	 */
	public function report_cumulative_uploads_by_month(){
		$query = 'SELECT d.date,
			@running_sum:=@running_sum + d.count AS uploads
				FROM (  SELECT DATE_FORMAT(date_created, "%Y-%m") as date, COUNT(videos.video_id) AS `count`
						FROM videos
						GROUP BY date
						ORDER BY date ) d
				JOIN (SELECT @running_sum := 0 AS dummy) dummy;';
		$db = Registry::get('db');
		$results = $db->basicQuery($query);
		
		// Set chart type, title, and other options here.
		$chart['type'] = 'line';
		$chart['options']['title']['display']= true;
		$chart['options']['title']['text']= "Cumulative Uploads by Month";
		
		// Build chart array/json
		$chart['data']['datasets'] = array();
		$labels = array();
		$data = array();
		foreach( $results as $result) {
			$labels[] = $result['date'];	
			$data[] = $result['uploads'];	
		}
		$chart['data']['labels'] = $labels;	
		$chart['data']['datasets'][0]['data'] = $data;	
		$chart['data']['datasets'][0]['label'] = 'Cumulative Uploads (Running Total)';	

		return Stats::display_chart($chart, 'cumulative_uploads_by_month');
	}

	/**
	 * Make sure they are in the right place with the correct permissions.
	 * 
	 */
	public function verify_access(){
		// Retrieve settings from database
		$stats_page = Settings::get('stats_page');
		
		// Get current page, auth status, and role
		$page = Stats::get_current_page();
		if(!$page){
			return false;
		}
		// If this is the correct page
		if ($stats_page == $page->pageId) {
			$userService = new UserService();
			if($userService->checkPermissions("admin_panel")) {
				return true;
			}
		}
		
		return false;
	}
	/**
	 * Get current page id.
	 * 
	 */
	public function get_current_page(){
		$router = new Router();
		$pageMapper = new PageMapper();
		if (!empty($_GET['preview']) && is_numeric($_GET['preview'])) {
			// Parse preview request
			$page = $pageMapper->getPageById($_GET['preview']);
		} else {
			// Parse the URI request
			$page = $pageMapper->getPageByCustom(array('slug' => trim($router->getRequestUri(), '/'), 'status' => 'published'));
		}
		return $page;
	}
	/**
	 * Outputs the settings page HTML and handles form posts on the plugin's
	 * settings page.
	 */
	public function settings(){
		$data = array();
		$errors = array();
		$message = null;

		// Retrieve settings from database
		$data['stats_page'] = Settings::get('stats_page');

		// Handle form if submitted
		if (isset($_POST['submitted'])) {
			// Validate form nonce token and submission speed
			$is_valid_form = Stats::_validate_form_nonce();

			if( $is_valid_form ){
				if( !empty($_POST['stats_page']) ) {
					$data['stats_page'] = $_POST['stats_page'];
				} else {
					$errors['stats_page'] = "Invalid page ID selected: " . $_POST['stats_page'] . ". ";
				}

			}
			else {
				$errors['session'] = 'Expired or invalid session';
			}

			// Error check and update data
			Stats::_handle_settings_form($data, $errors);

		}
		// Generate new form nonce
		$formNonce = md5(uniqid(rand(), true));
		$_SESSION['formNonce'] = $formNonce;
		$_SESSION['formTime'] = time();

		// Populate form data arrays
		$db = Registry::get('db');
		$pageMapper = new PageMapper();
		$query = "SELECT page_id FROM " . DB_PREFIX . "pages";
		$queryParams = array();
		$resultPages = $db->fetchAll ($query, $queryParams);
		$pages = $pageMapper->getPagesFromList(
		    Functions::arrayColumn($resultPages, 'page_id')
		);
		
		// Display form
		include(dirname(__FILE__) . '/settings_form.php');
	}

	/**
	 * Check for form errors and save settings
	 * 
	 */
	private function _handle_settings_form($data, $errors){
		if (empty($errors)) {
			foreach ($data as $key => $value) {
				Settings::set($key, $value);
			}
			$message = 'Settings have been updated.';
			$message_type = 'alert-success';
		} else {
			$message = 'The following errors were found. Please correct them and try again.';
			$message .= '<br /><br /> - ' . implode('<br /> - ', $errors);
			$message_type = 'alert-danger';
		}
	}

	/**
	 * Validate settings form nonce token and submission speed
	 * 
	 */
	private function _validate_form_nonce(){
		if (
				!empty($_POST['nonce'])
				&& !empty($_SESSION['formNonce'])
				&& !empty($_SESSION['formTime'])
				&& $_POST['nonce'] == $_SESSION['formNonce']
				&& time() - $_SESSION['formTime'] >= 2
		   ) {
			return true;

		} 
		else {
			return false;
		}

	}

}


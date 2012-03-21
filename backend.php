<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) . "/include");

	/* remove ill effects of magic quotes */

	if (get_magic_quotes_gpc()) {
		function stripslashes_deep($value) {
			$value = is_array($value) ?
				array_map('stripslashes_deep', $value) : stripslashes($value);
				return $value;
		}

		$_POST = array_map('stripslashes_deep', $_POST);
		$_GET = array_map('stripslashes_deep', $_GET);
		$_COOKIE = array_map('stripslashes_deep', $_COOKIE);
		$_REQUEST = array_map('stripslashes_deep', $_REQUEST);
	}

	$op = $_REQUEST["op"];
	@$method = $_REQUEST['subop'] ? $_REQUEST['subop'] : $_REQUEST["method"];

	if (!$method)
		$method = 'index';
	else
		$method = strtolower($method);

	/* Public calls compatibility shim */

	$public_calls = array("globalUpdateFeeds", "rss", "getUnread", "getProfiles", "share",
		"fbexport", "logout", "pubsub");

	if (array_search($op, $public_calls) !== false) {
		header("Location: public.php?" . $_SERVER['QUERY_STRING']);
		return;
	}

	@$csrf_token = $_REQUEST['csrf_token'];

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	no_cache_incantation();

	startup_gettext();

	$script_started = getmicrotime();

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;

	header("Content-Type: text/plain; charset=utf-8");

	if (ENABLE_GZIP_OUTPUT && function_exists("ob_gzhandler")) {
		ob_start("ob_gzhandler");
	}

	if (SINGLE_USER_MODE) {
		authenticate_user($link, "admin", null);
	}

	// TODO remove and handle within Handlers

	if (!($_SESSION["uid"] && validate_session($link))) {
		if ($op == 'pref-feeds' && $method == 'add') {
			header("Content-Type: text/html");
			login_sequence($link);
			render_login_form($link);
		} else {
			header("Content-Type: text/plain");
			print json_encode(array("error" => array("code" => 6)));
		}
		return;
	}

	$purge_intervals = array(
		0  => __("Use default"),
		-1 => __("Never purge"),
		5  => __("1 week old"),
		14 => __("2 weeks old"),
		31 => __("1 month old"),
		60 => __("2 months old"),
		90 => __("3 months old"));

	$update_intervals = array(
		0   => __("Default interval"),
		-1  => __("Disable updates"),
		15  => __("Every 15 minutes"),
		30  => __("Every 30 minutes"),
		60  => __("Hourly"),
		240 => __("Every 4 hours"),
		720 => __("Every 12 hours"),
		1440 => __("Daily"),
		10080 => __("Weekly"));

	$update_intervals_nodefault = array(
		-1  => __("Disable updates"),
		15  => __("Every 15 minutes"),
		30  => __("Every 30 minutes"),
		60  => __("Hourly"),
		240 => __("Every 4 hours"),
		720 => __("Every 12 hours"),
		1440 => __("Daily"),
		10080 => __("Weekly"));

	$update_methods = array(
		0   => __("Default"),
		1   => __("Magpie"),
		2   => __("SimplePie"),
		3   => __("Twitter OAuth"));

	if (DEFAULT_UPDATE_METHOD == "1") {
		$update_methods[0] .= ' (SimplePie)';
	} else {
		$update_methods[0] .= ' (Magpie)';
	}

	$access_level_names = array(
		0 => __("User"),
		5 => __("Power User"),
		10 => __("Administrator"));

	$error = sanity_check($link);

	if ($error['code'] != 0 && $op != "logout") {
		print json_encode(array("error" => $error));
		return;
	}

	function __autoload($class) {
		$file = "classes/".strtolower(basename($class)).".php";
		if (file_exists($file)) {
			require $file;
		}
	}

	$op = str_replace("-", "_", $op);

	if (class_exists($op)) {
		$handler = new $op($link, $_REQUEST);

		if ($handler) {
			if (validate_csrf($csrf_token) || $handler->csrf_ignore($method)) {
				if ($handler->before($method)) {
					if ($method && method_exists($handler, $method)) {
						$handler->$method();
					}
					$handler->after();
					return;
				}
			} else {
				header("Content-Type: text/plain");
				print json_encode(array("error" => array("code" => 6)));
				return;
			}
		}
	}

	header("Content-Type: text/plain");
	print json_encode(array("error" => array("code" => 7)));

	// We close the connection to database.
	db_close($link);
?>

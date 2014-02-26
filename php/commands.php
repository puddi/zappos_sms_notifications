<?php

	$username = 'MySQL username';
	$password = 'MySQL password';
	$hostname = 'MySQL hostname';
	$database = 'MySQL database';
	
	$key = 'Zappos API Key';		// zappos API key
	
	$debug = TRUE;
	
	$db = new PDO("mysql:dbname=$database;host=$hostname", $username, $password);		// make the DB link
		
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 	// Make any SQL syntax errors result in PHP errors.

	parse_str(file_get_contents('php://input'), $_POST);
	$action = $_SERVER['REQUEST_METHOD'];
	
	// generic query handling function.
	function query($sql, $params = null) {
		global $db;
		global $DEBUG;
		try {			
			$q = $db->prepare($sql);
			$q->execute($params);
			if (strpos($sql, "INSERT") !== false || strpos($sql, "DELETE") !== false) {
				return $db->lastInsertId();
			} else {
				return $q->fetchAll(PDO::FETCH_ASSOC);
			}
		} catch (PDOException $e) {
			if (TRUE) {
				http_error(500, "Internal Server Error", "There was a SQL error:\n\n" . $e->getMessage());
			} else {
				http_error(500, "Internal Server Error", "Something went wrong.");
			}	
		}
	}
	
	// http error handler.
	function http_error($code, $status, $message) {
		header("HTTP/1.1 $code $status");
		header("Content-type: text/plain");
		die($message);
	}
	
	// adds a number to the accounts table, with the specified pass and carrier.
	function addNumber($phonenumber, $carrier, $password) {
		query("INSERT INTO accounts (phonenumber, carrier, passHash)
				VALUES (:p, :c, :h)", array(
			':p' => $phonenumber,
			':c' => $carrier,
			':h' => hash("sha256", $password)
		));
	}
	
	// deletes a number from the account tables, and sends a message to the user that was deleted.
	function deleteNumber($phonenumber) {
		$rows = query("SELECT * FROM accounts
				WHERE phonenumber = :p", array(
			':p' => $phonenumber
		));
		if (count($rows)) {
			sendMessage($rows[0]['phonenumber'], $rows[0]['carrier'], 'You have been unsubscribed from Zappos discount notifications.', false);
			query("DELETE FROM accounts
					WHERE phonenumber = :p", array(
				':p' => $phonenumber
			));
		}
	}
	
	// adds a product/number tuple, representing that the number should get notified whenenver that product goes on sale.
	function addStyleForNumber($phonenumber, $styleID) {
		query("INSERT INTO styles (phonenumber, styleID)
				VALUES (:p, :s)", array(
			':p' => $phonenumber,
			':s' => $styleID
		));
	}
	
	// deleates the product/number tuple.
	function deleteStyleForNumber($phonenumber, $styleID) {
		query("DELETE FROM styles
				WHERE phonenumber = :p AND styleID = :s", array(
			':p' => $phonenumber,
			':s' => $styleID
		));
	}
	
	function deleteStyle($styleID) {
		query("DELETE FROM styles
				WHERE styleID = :s", array(
			':s' => $styleID
		));
	}
	
	// sends the given message to the number with a carrier.
	function sendMessage($number, $carrier, $message, $reminder) {
		switch ($carrier) {
				case 'AT&T':
					$to = $number . "@txt.att.net";
					break;
				
				case 'Verizon':
					$to = $number . "@vtext.com";
					break;
				
				case 'T-Mobile':
					$to = $number . "@tmomail.net";
					break;
				
				case 'Virgin': 
					$to = $number . "@vmobl.com";
					break;
				
				case 'Sprint':
					$to = $number . "@messaging.sprintpcs.com";
					break;
		}
		
		if ($to) {
			if ($reminder) {
				$message .= "\r\n\r\nUnsubscribe at URL";
			}
			$from = "reminders@staffanhellman.com";	// this doesn't matter to the end user, and can be spoofed (except on Verizon)
			$reply = "reminders@staffanhellman.com"; // likewise
			$subject = "Discounts";
			$header = 'From: ' . $from . "\r\nReply-To: " . $reply . "\r\nX-Mailer: PHP/" . phpversion();
			mail($to, $subject, $message, $header);	
		}
	}
	
	// $search is an array of styleIDs to a varible array of numbers. $results is a storage array with ["true"] and ["false"]. 
	// makes the API request, and puts all "true" values, or values on a 20% or greater discount, in the true results. puts it into false otherwise.
	function makeAPIRequestWithStyles($search, $results) {
		global $key;
		$searchKeys = array_keys($search);
		$terms = implode(",", $searchKeys);
		$json = json_decode(file_get_contents("http://api.zappos.com/Product?styleId=[$terms]&includes=[\"styles\"]&key=$key"), true);
		for ($i = 0; $i < count($searchKeys); $i++) {
			if (intval(trim($json["product"][$i]["styles"][0]["percentOff"], "%")) >= 20) {
				$description = $json["product"][$i]["productName"] . " by " . $json["product"][$i]["brandName"] . " is now " . $json["product"][$i]["styles"][0]["percentOff"] . " off, at " . $json["product"][$i]["styles"][0]["price"] . ".";	// short description that's used as the message
				$results["true"][$description] = $search[$searchKeys[$i]];
				deleteStyle($searchKeys[$i]);
			} else {
				array_push($results["false"], $searchKeys[$i]);		// we don't care about the number, we just care about the styleID (since we're not sending a message)
			}
		}
		return $results;
	}
	
	
	// on request, if there's an action (and thus, it's a request from script.js)
	if (isset($_REQUEST['action'])) {
		$action = $_REQUEST['action'];
		switch ($action) {	// perform the right action.
				case 'addNumber':
					if (!(isset($_REQUEST['phonenumber']) && isset($_REQUEST['carrier']) && isset($_REQUEST['passHash']))) {
						http_error(400, "Invalid Request", "All fields must be supplied.");
					}
					addNumber($_REQUEST['phonenumber'], $_REQUEST['carrier'], $_REQUEST['passHash']);
					break;
				
				case 'deleteNumber':
					if (!isset($_REQUEST['phonenumber'])) {
						http_error(400, "Invalid Request", "All fields must be supplied.");
					}
					deleteNumber($_REQUEST['phonenumber']);
					break;
				
				case 'addStyleForNumber':
					if (!(isset($_REQUEST['phonenumber']) && isset($_REQUEST['styleID']))) {
						http_error(400, "Invalid Request", "All fields must be supplied.");
					}
					$rows = query("SELECT * FROM styles
							WHERE phonenumber = :p AND styleID = :s", array(
						':p' => $_REQUEST['phonenumber'],
						':s' => $_REQUEST['styleID']
					));
					if (!count($rows)) {
						addStyleForNumber($_REQUEST['phonenumber'], $_REQUEST['styleID']);
					} else {
						echo ('already exists');
					}
					break;
				
				case 'deleteStyleForNumber':
					if (!(isset($_REQUEST['phonenumber']) && isset($_REQUEST['styleID']))) {
						http_error(400, "Invalid Request", "All fields must be supplied.");
					}
					deleteStyleForNumber($_REQUEST['phonenumber'], $_REQUEST['styleID']);
					break;
					
				case 'sendCode':
					if (!(isset($_REQUEST['phonenumber']) && isset($_REQUEST['carrier']))) {
						http_error(400, "Invalid Request", "All fields must be supplied.");
					}
					$rows = query("SELECT * FROM accounts
							WHERE phonenumber = :p", array(
							':p' => $_REQUEST['phonenumber']
					));
					if (!count($rows)) {
						$randInt = rand(1000, 9999);
						$message = "Your Zappos confirmation code is $randInt";
						query("DELETE FROM codes		
								WHERE phonenumber = :p", array(
								':p' => $_REQUEST['phonenumber']
						));		// we don't want two instances of the phone number in the codes table
						query("INSERT INTO codes (phonenumber, code)
								VALUES (:p, :c)", array(
								':p' => $_REQUEST['phonenumber'],
								':c' => $randInt
						));		// storage for verifyCode
						sendMessage($_REQUEST['phonenumber'], $_REQUEST['carrier'], $message, false);	
					} else {
						echo ('number already exists.');
					}
					break;
					
				case 'verifyCode':		// check if the code passed matches the one set.
					if (!(isset($_REQUEST['phonenumber']) && isset($_REQUEST['code']) && isset($_REQUEST['password']) && isset($_REQUEST['carrier']))) {
						http_error(400, "Invalid Request", "All fields must be supplied.");
					}
					$rows = query("SELECT * FROM codes
							WHERE phonenumber = :p AND code = :c", array(
						':p' => $_REQUEST['phonenumber'],
						':c' => $_REQUEST['code']
					));
					if (count($rows)) {		// if the code matched
						query("DELETE FROM codes
								WHERE phonenumber = :p AND code = :c", array(
							':p' => $_REQUEST['phonenumber'],
							':c' => $_REQUEST['code']
						));		// it's done its job
						addNumber($_REQUEST['phonenumber'], $_REQUEST['carrier'], $_REQUEST['password']);
						echo ('true');
					} else {
						echo ('false');
					}
					break;
					
				case 'verifyAccount':		// check if an account exists.
					if (!(isset($_REQUEST['phonenumber']) && isset($_REQUEST['password']))) {
						http_error(400, "Invalid Request", "All fields must be supplied.");
					}
					$rows = query("SELECT * FROM accounts
							WHERE phonenumber = :p AND passHash = :h", array(
						':p' => $_REQUEST['phonenumber'],
						':h' => hash("sha256", $_REQUEST['password'])
					));
					if (count($rows)) {
						echo ('true');
					} else {
						echo ('false');
					}
					break;
					
				case 'getStyles':		// returns styles notifications associated with a phone number
					if (!isset($_REQUEST['phonenumber'])) {
						http_error(400, "Invalid Request", "All fields must be supplied.");
					}
					$rows = query("SELECT styleID FROM styles
							WHERE phonenumber = :p", array(
						':p' => $_REQUEST['phonenumber']
					));
					$temp = array();
					foreach ($rows as $row) {
						array_push($temp, $row['styleID']);
					}
					$r = implode(',', $temp);
					echo ($r);
					break;
					
		}
	} else {		// otherwise, it's probably done by the cron. let's go over all of our notifications, check to see if they're discounted, and if they are, send those messages.
		$rows = query("SELECT styles.phonenumber, styles.styleID, accounts.carrier FROM styles JOIN accounts ON styles.phonenumber = accounts.phonenumber");
		while (!count($rows)) {
			usleep(2000000);
			$rows = query("SELECT styles.phonenumber, styles.styleID, accounts.carrier FROM styles JOIN accounts ON styles.phonenumber = accounts.phonenumber");		// ensure that there's some rwos going on.
		}
		$search = array();	// initiate storage array
		$results = array("true" => array(), "false" => array()); // likewise
		foreach ($rows as $row) {		// for each row in the notifications	
			if (!in_array($row['styleID'], array_keys($results["true"])) && !in_array($row['styleID'], $results["false"])) {	// if we haven't encounted the styleID before
				if (!isset($search[$row['styleID']])) {		// and if it's not in the search array
					$search[$row['styleID']] = array();		// throw it in there
				}
				$search[$row['styleID']][$row['phonenumber']] = $row['carrier'];		// and set the phone number/carrier.
				if (count($search) == 10) {		// then, if we have 10 different, unique styles
					$results = makeAPIRequestWithStyles($search, $results);		// make the request
					$search = array();		// and empty the array
				}
			} else if (in_array($row['styleID'], array_keys($results["true"]))) {		// otherwise, we know the result (if it's on sale or not)
				if (!in_array($row["phonenumber"], array_keys($results["true"][$row['styleID']]))) {	// if it is,
					$results["true"][$row['styleID']][$row["phonenumber"]] = $row['carrier'];		// say that we want to send the message
				}
			}
		}
		$results = makeAPIRequestWithStyles($search, $results);		// any leftover search terms
		foreach (array_keys($results["true"]) as $description) {
			foreach (array_keys($results["true"][$description]) as $number) {
				sendMessage($number, $results["true"][$description][$number], $description, true);		// send a message to all the numbers for the product
			}
		}
		print_r($results);
	}
?>
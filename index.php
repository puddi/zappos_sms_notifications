<?php
	$username = 'MySQL username';
	$password = 'MySQL password';
	$hostname = 'MySQL hostname';
	$database = 'MySQL database';
	
	$key = 'Zappos API Key';
	
	$debug = TRUE;
	
	$db = new PDO("mysql:dbname=$database;host=$hostname", $username, $password);
		
	// Make any SQL syntax errors result in PHP errors.
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	parse_str(file_get_contents('php://input'), $_POST);
	$action = $_SERVER['REQUEST_METHOD'];
	
	function query($sql, $params = null) {
		global $db;
		global $DEBUG;
		try {			
			$q = $db->prepare($sql);
			$q->execute($params);
			if (strpos($sql, "INSERT") !== false) {
				return $db->lastInsertId();
			} else {
				return $q->fetchAll(PDO::FETCH_ASSOC);
			}
		} catch (PDOException $e) {
			if ($DEBUG) {
				http_error(500, "Internal Server Error", "There was a SQL error:\n\n" . $e->getMessage());
			} else {
				http_error(500, "Internal Server Error", "Something went wrong.");
			}	
		}
	}
?>

<html>
	<head>
		<title>Zappos Discount Notifications</title>
		<link rel="stylesheet" type="text/css" href="css/styles.css">
		<script src="js/jquery-2.0.3.min.js"></script>
		<script src="js/script.js"></script>
		<script src="js/jquery.cookie.js"></script>
	</head>
	<body>
		<div class="nav">
			<ul>
				<li><a href="javascript:void(0)" class="navLi deactivate">deactivate</a></li>
				<?php 
					if (isset($_COOKIE['phonenumber'])) {
				?>
					<li><a href="javascript:void(0)" class="navLi logout">logout</a></li>
					<li>Logged in as: <strong><?= $_COOKIE['phonenumber'] ?></strong></li>
				</ul>				
				<?php 
					} else { 
				?>
					<li><a href="javascript:void(0)" class="navLi login">login</a></li>
				</ul>
				<div class="loginForm">
					<input type="text" class="loginNumberField" placeholder="number" max="10">
					<input type="password" class="loginPasswordField" placeholder="password">
					<p><a href="javascript:void(0)" class="submitLogin">Submit</a></p>
				</div>
				<?php
					}
				?>
				<div class="deactivateForm">
					<input type="text" class="deactivateNumberField" placeholder="number" max="10">
					<p><a href="javascript:void(0)" class="submitDeactivate">Submit</a></p>
				</div>
		</div>
		<p class="clear"></p>
		<div class="content">
			<h2>Zappos Discount Notifications</h2>
<?php
	if (!isset($_COOKIE['phonenumber'])) {
?>
			<p>We'll notify you via SMS whenever a product goes on sale beyond 20%.</p>
			<hr>
			<div class="signupForm">
				<p>Looks like you don't have an account set up. No problem.</p>
				<p>We'll need your number, the carrier it's on, and a new password for your account. (Don't worry - your information is safe with us. We won't spam you, either.)</p>
				<input type="text" class="numberField" placeholder="ten digits" max="10">	
				<input type="password" class="passwordField" placeholder="password">
				<div class="carrierField">
					<ul>
						<li><a href="javascript:void(0)">AT&T</a></li>
						<li><a href="javascript:void(0)">Verizon</a></li>
						<li><a href="javascript:void(0)">T-Mobile</a></li>
						<li><a href="javascript:void(0)">Sprint</a></li>
						<li><a href="javascript:void(0)">Virgin</a></li>
					</ul>
				</div>
				<p><a href="javascript:void(0)" class="submit submitNew">Submit</a></p>
			</div>
		
<?php
	} else {
?>
			<hr>
			<p>Your notifications:</p>
			<div class="notifications">
				
			</div>
			<hr class="clear" style="width:75%;">
			<div class="search">
				<div class="searchArea">
					<input type="text" class="searchField" placeholder="search for items" max="30">
					<p class="searchP"><a href="javascript:void(0)" class="searchButton">Search</a></p>
				</div>
				<div class="searchResults clear">
				</div>
			</div>
		
<?php
	}
?>
		</content>
	</body>
</html>
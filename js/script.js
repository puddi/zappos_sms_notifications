$(document).ready(function() {
	var number, carrier, password; // declaring at psuedo-global for ease of use, and not having to declare it every time.
	
	var key = 'Zappos API key';
	
	// if they're logged in, let's grab their notifications
	if ($.cookie('phonenumber') != null) { 
		getNotifications();
	}
	
	// handling events for firing searches
	$('.searchField').keyup(function(event) {
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if (keycode == '13') {	// enter
	  	  search($('.searchField').val(), 1);
		}
    });
    
    $('.searchButton').click(function() { 
    	search($('.searchField').val(), 1);
    });
    
    // fires a search
    function search(query, page) {
    	$.ajax('http://api.zappos.com/Search', {
    		data: {
    			term: query,
    			includes: '["Styles"]',
    			'key': key,
    			limit: 10,
    			'page': page
    		},
    		dataType: 'jsonp',
    		success: function(data) { 
    			loadResults(data, page);
    		},
    		error: ajaxError
    	});
    }
    
    // handles the data that comes back from the search
    function loadResults(data, page) {
    	$results = $('.searchResults');
    	var $result = $('<div>');
    	$results.fadeOut(200, function() { 
    		$results.empty();
    		if (data["totalResultCount"] != 0) {		// if we have results...
				$.each(data["results"], function(index, value) {
					var $item = $('<div>').addClass('result').addClass('clear')
						.append($('<div>').addClass('left')
							.append($('<img>').attr('src', value["thumbnailImageUrl"]).attr('alt', value["productName"]))
						).append($('<div>').addClass('right').addClass('result' + (index + 1))
							.append($('<h2>').html(value["productName"]))
							.append($('<h4>').html('by ' + value["brandName"]))
							.append($('<p>').addClass('normalPrice').text('Normal price: ' + value["originalPrice"]))
							.append($('<p>').addClass('currentPrice').html('Current price: <strong>' + value["price"] + '</strong> (' + value["percentOff"] + ' off)'))
							.append($('<p>')
								.append($('<a>').addClass('addNotification').attr('href', 'javascript:void(0)').data('styleID', value["styleId"]).data('parent', 'result' + (index + 1)).text('Add notification').click(function() {
								$.ajax('php/commands.php', {
										data: {
											action: 'addStyleForNumber',
											phonenumber: $.cookie('phonenumber'),
											styleID: $(this).data('styleID')
										},
										success: $.proxy(function(data) {
											if (data == 'already exists') {
												displayMessage('You are already signed up for notifications for this product.', $(this).data('parent'));
											} else {
												displayMessage('You have been signed up for notifications for this product.', $(this).data('parent'));
												getNotifications();
											}
										}, this),
										error: ajaxError
									});
								}))
							)
						).append($('<hr>').addClass('clear'));	
					$result.append($item);
				});
				// create the indexes
				$temp = $('<p>').addClass('indexes').addClass('clear');
				pageCount = Math.ceil(data["totalResultCount"] / 10.0);
				page = parseInt(page);
				for (var i = (page > 4 && pageCount > 8 ? page - 4 : 1); i < page; i++) {
					console.log(i);
					$temp.append($('<span>').html('<a href="javascript:void(0)">' + i + '</a> ').data('query', data["originalTerm"]).click(function() {
						search($(this).data('query'), $(this).find('a').text());
					}));
				}
				$temp.append($('<strong>').text(page));
				for (var i = page + 1; i < Math.min(pageCount, (page > 4 && pageCount > 9 ? page + 5 : 10)); i++) {
					console.log(i);
					$temp.append($('<span>').html(' <a href="javascript:void(0)">' + i + '</a>').data('query', data["originalTerm"]).click(function() {
						search($(this).data('query'), $(this).find('a').text());
					}));
				}
				$result.append($temp);
			} else {	// or if there's no results...
				$result = $('<p>').text('No results were found.');
			}
    		$results.append($result);
    	});
    	$results.fadeIn(200);
    }
	
	// grab the notifications that a number has.
	function getNotifications() {
		$.ajax('php/commands.php', {
			data: {
				action: 'getStyles',
				phonenumber: $.cookie('phonenumber')
			},
			success: function(data) {
				var styles = data.split(",");
				var temp = [];
				while (styles.length != 0) { 
					$.ajax('http://api.zappos.com/Product', {
						data: {
							styleId: '[' + styles.splice(0, 10).join(",") + ']',
							includes: '["styles"]',
							'key': key
						},
						dataType: 'jsonp',
						success: function(data) { 
							$.each(data["product"], function(index, value) { 
								var temp2 = [];
								temp2["styleID"] = value["styles"][0]["styleId"];
								temp2["description"] = value["productName"] + ' by ' + value["brandName"];
								temp2["image"] = (value["styles"][0]["imageUrl"] != null ? value["styles"][0]["imageUrl"] : value["defaultImageUrl"]);
								temp.push(temp2);
							});
							if (styles.length == 0) {		// nice handler for ensuring that we only load the notifications if it's the last call
								loadNotifications(temp);
							}
						},
						error: ajaxError
					});
				}
			},
			error: ajaxError
		});
	}
	
	// loads the notifications.
	function loadNotifications(styles) {
		$notifications = $('.notifications');
		$notifications.fadeOut(200, function() {
			$notifications.empty();
			if (styles.length != 0) {
				$.each(styles, function(index, value) {
					$('<div>').addClass('notification')
						.append($('<p>')
							.append($('<span>').text(value["description"]))
							.append($('<a>').attr('href', 'javascript:void(0)').addClass('deleteNotification').data('styleID', value["styleID"]).text('Delete').click(function() {
								$.ajax('php/commands.php', {
									data: {
										action: 'deleteStyleForNumber',
										phonenumber: $.cookie('phonenumber'),
										styleID: $(this).data('styleID')
									},
									success: $.proxy(function() { 
										$(this).parent().parent().fadeOut(200);
									}, this),
									error: ajaxError
								});
							}))
							.append($('<span>').addClass('clear'))
						).appendTo($notifications);
				});
			} else {
				$notifications.append($('<p>').text('No notifications set up.'));
			}
			$notifications.fadeIn(200);
		});
	}
	
	// handles the radio-button-esque effect of the carrier fields.
	$('.carrierField li').click(function() {
		$(this).siblings().removeClass('selected');
		$(this).addClass('selected');
	});
	
	// handles the creation of new accounts.
	$('.submitNew').click(function() {
		number = $('.numberField').val().split("-").join("").split(".").join("").split(" ").join("");	// in case of "444.444-4444"
		carrier = $('.carrierField .selected').text();
		password = $('.passwordField').val();
		if (number.length != 10) {	// generic checks
			displayMessage('Your phone number must be 10 digits long.', 'signupForm');
		} else if (isNaN(parseInt(number))) {
			displayMessage('Your phone number must be numeric.', 'signupForm');
		} else if (password.length == 0) {
			displayMessage('You must specify a password.', 'signupForm');
		} else if (carrier.length == 0) {
			displayMessage('You must select a carrier.', 'signupForm');
		} else {
			$.ajax('php/commands.php', {
				data: {
					action: 'sendCode',		// see commands.php
					phonenumber: number,
					'carrier': carrier
				},
				success: function(data) {
					$signups = $('.signupForm');
					if (data != 'number already exists.') {		// if it's not already in the database,
						$signups.fadeOut(200, function() {		// input the four digit code.
							$signups.empty();
							$('<p>').text('Please input the four-digit code that was sent to your device.').appendTo($signups);
							$('<input>').addClass('codeField').attr('type', 'text').appendTo($signups);
							$('<p>').html('<a href="javascript:void(0)" class="submit submitValidate">Submit</a>').appendTo($signups).click(function() {
								$.ajax('php/commands.php', {
									data: {
										action: 'verifyCode',			// verify that code
										code: $('.codeField').val(),
										phonenumber: number,
										'password': password,
										'carrier': carrier
									},
									success: function(data) {
										if (data == 'true') {			// if the code works...
											 $.cookie('phonenumber', number, { expires: 7 });
											 $signups.fadeOut(200, function() {
												$signups.empty();
												$('<p>').text('You are now registered with the phone number ' + number + '.').appendTo($signups);
												$('<p>').text('You will be redirected in 5 seconds...').appendTo($signups);
												$signups.fadeIn(200);
												setTimeout(function() { location.reload() } , 4800);
											 });
										} else {			// otherwise, say it's not working.
											displayMessage('Code not found.', 'signupForm');
										}
									},
									error: ajaxError
								});
							});
							$signups.fadeIn(200);
						});
					} else {		// if the number already exists
						$signups.fadeOut(200, function() {
							$signups.empty();
							$('<p>').text('That number is already in our database.').appendTo($signups);
							$signups.fadeIn(200);
							setTimeout(function() { location.reload() } , 3800);
						});
					}	
				},
				error: ajaxError
			});
		}
	});
	
	// on logout
	$('.logout').click(function() {
		 $.removeCookie('phonenumber');		// remove the cookie
		 location.reload();		// and reload the page.
	});
	
	// sees if there's any nav forms up, turning them off, and sliding the correct form down.
	$('.login').click(function() {
		$deactivateForm = $('.deactivateForm');
		if ($deactivateForm.css('display') == 'block') {
			$deactivateForm.slideToggle(400, function() {
				$('.loginForm').slideToggle();
			});
		} else {
			$('.loginForm').slideToggle();
		}
	});
	
	// sees if there's any nav forms up, turning them off, and sliding the correct form down.
	$('.deactivate').click(function() { 
		$loginForm = $('.loginForm');
		if ($loginForm.css('display') == 'block') {
			$loginForm.slideToggle(400, function() {
				$('.deactivateForm').slideToggle();
			});
		} else {
			$('.deactivateForm').slideToggle();
		}
	});
	
	// catches attempts to login.
	$('.submitLogin').click(login);
	
	$('.loginNumberField, .loginPasswordField').keyup(function(event) {
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if (keycode == '13') {		// enter
	  	  login();
		}
    });
    
    // handles logging in.
	function login() {
		number = $('.loginNumberField').val();
		password = $('.loginPasswordField').val();
		if (number.length != 10) {		// error checking
			displayMessage('Your phone number must be 10 digits long.', 'loginForm');
		} else if (isNaN(parseInt(number))) {
			displayMessage('Your phone number must be numeric.', 'loginForm');
		} else if (password.length == 0) {
			displayMessage('You must specify a password.', 'loginForm');
		} else { 
			$.ajax('php/commands.php', {
				data: {
					action: 'verifyAccount',		// see commands.php
					phonenumber: number,
					'password': password
				},
				success: function(data) {
					if (data == 'true') {		// if it works...
						$.cookie('phonenumber', number, { expires: 7 });		// set the cookie
						$loginForm = $('.loginForm');
						$loginForm.fadeOut(200, function() {
							$loginForm.empty();
							$('<p>').text('You are now logged in.').appendTo($loginForm);		// notify the user they're logged in
							$loginForm.fadeIn(200);
							setTimeout(function() { location.reload() } , 1800);		// and reload to get them to the home screen.
						});
					} else {		// otherwise...
						displayMessage('Account not found.', 'loginForm');		// the account wasn't found.
					}
				},
				error: ajaxError
			});
		}
	}
		
	// catches deactivation event triggers.
	$('.submitDeactivate').click(deactivate);
	
	$('.deactivateNumberField').keyup(function(event) {
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if (keycode == '13') {		// enter
	  	  loginHandler();
		}
    });
	
	// handles deactivation.
	function deactivate() {
		number = $('.deactivateNumberField').val();
		if (number.length != 10) {		// error checks
			displayMessage('The phone number must be 10 digits long.', 'deactivateForm');
		} else if (isNaN(parseInt(number))) {
			displayMessage('The phone number must be numeric.', 'deactivateForm');
		} else {
			$.ajax('php/commands.php', {
				data: {
					action: 'deleteNumber',
					phonenumber: number
				}, 
				success: function(data) {
					$('.deactivateNumberField').val('')
					displayMessage('Number deleted.', 'deactivateForm');		// this says the number was deleted, even if it wasn't. false fake security.
					if ($.cookie('phonenumber') == number) {		// if they were logged in, and deactivated themselves...
						$.removeCookie('phonenumber');			// remove that cookie
						setTimeout(function() { location.reload() } , 1800);		// and reload the page, to send them to the login screen.
					}
				},
				error: ajaxError
			});
		}
	}
	
	// displays an message message to the class attachTo.
	function displayMessage(message, attachTo) {
		$('<p>').addClass(attachTo + 'Error').text(message).css('display', 'none').appendTo($('.' + attachTo)).slideToggle(200, function(e) {
			setTimeout(function(e) {
				$('.' + attachTo + 'Error').slideToggle(200, function(e) { 
					$(this).remove();
				});
			}, 1600);
		});
	}
	
	// generic ajax error handler function.
	function ajaxError(jqxhr, type, error) {
		var msg = "An Ajax error occurred!\n\n";
		if (type == 'error') {
			if (jqxhr.readyState == 0) {
				// Request was never made - security block?
				msg += "Looks like the browser security-blocked the request.";
			} else {
				// Probably an HTTP error.
				msg += 'Error code: ' + jqxhr.status + "\n" + 
							 'Error text: ' + error + "\n" + 
							 'Full content of response: \n\n' + jqxhr.responseText;
			}
		} else {
			msg += 'Error type: ' + type;
			if (error != "") {
				msg += "\nError text: " + error;
			}
		}
		console.log(msg);
	}
});
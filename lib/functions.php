<?php

/**
 * Merges the application and the local config and returns it.
 */
function getConfig()
{
	$config = include dirname(__FILE__) . '/../config.php';
	if (file_exists(dirname(__FILE__) . '/../local.config.php')) {
		// Load the local config an merge
		$localConfig = include dirname(__FILE__) . '/../local.config.php';
		$config = array_merge($config, $localConfig);
	}

	return $config;
}

/**
 * Calculates the average of the given values
 */
function average($values) {
	$total = 0;
	$count = 0;
	foreach ($values as $temperature) {
		$total += $temperature;
		$count++;
	}

	return number_format(round($total / $count, 1), 1);
}

/**
 * @return the minimum value in an array of integers.
 */
function getMin($values) {
	$lowest = NULL;
	foreach ($values as $temperature) {
		$value = $temperature;
		if ($lowest === NULL || $value < $lowest) {
			$lowest = $value;
		}
	}
	return number_format($lowest, 1);
}

/**
 * @return the maximum value in an array of integers
 */
function getMax($values) {
	$highest = NULL;
	foreach ($values as $temperature) {
		$value = $temperature;
		if ($highest === NULL || $value > $highest) {
			$highest = $value;
		}
	}
	return number_format($highest, 1);
}

/**
 * Sends a notification email to the email address defined
 * in the configuration file.
 * If no email address is defined, this method files silently.
 * If displaying hostname is enabled in the configuration file,
 * the hostname is appended to the subject.
 * @param $temp The measured temperature
 */
function sendEmailNotification($temp)
{
	$config = getConfig();
	$notifiactionConfig = $config['notification'];

	// Cancel if emailing is disabled
	if (!$notifiactionConfig['enable_email']) return;

	// Put hostname into subject if enabled
	$subject = "PiTemp Notification";
	if ($notifiactionConfig['show_hostname']) {
		$subject .= " - ".gethostname();
	}
	$to = join(",", $notifiactionConfig['email_addresses']);
	$message = createNotificationText($temp);
	$headers = "From: PiTemp <notification@pitemp.local> \r\n";

	// Send the mail!
	mail($to, $subject, $message, $headers);
}

/**
 * Sends a notification over pushover.net to the user
 * that is defined in the configuration file.
 * This method uses a small script on our server to "hide" our
 * application key.
 * The script used on our server is also available on github if you
 * want to review it.
 * @todo Add Github URL to server script
 * @param $temp The measured temperature
 * @param $userKey The Pushover User key.
 */
function sendPushoverNotification($temp, $userKey)
{
	// Get the url to the PiTemp Server
	$config = getConfig();
	$pitempServer = $config['pi_temp_server'];

	// Initialize cURL for the request
	$curl = curl_init();

	// Set the options
	curl_setopt_array($curl, array(
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => $pitempServer . "?temp=" . $temp . "&userkey=" . $userKey
	));

	// Send the request
	$response = curl_exec($curl);

	// @todo Add some sort of log to log failed attempts.

	// Close the request
	curl_close($curl);
}

/**
 * Sends a notification via pushbullet.com to the device
 * that is defined in the configuration file.
 * @param $temp The measured temperature
 * @param $deviceid The Pushbullet device ID.
 * @param $apikey The Pushbullet API key.
 */
function sendPushbulletNotification($temp, $deviceid, $apikey)
// some code snippets from https://github.com/fodawim/PushBullet-API-PHP/blob/master/pushbullet.php
{
	$post_data = array(
                    'device_id' => $deviceid,
                    'type' => 'note',
                    'title' => 'PiTemp notification',
                    'body' => createNotificationText($temp)
	);

	$post_data_string = http_build_query($post_data);

	$ch = curl_init('https://www.pushbullet.com/api/pushes');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $apikey . ":");
	curl_setopt($ch, CURLOPT_POST, count($post_data));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_string);
	$response = curl_exec($ch);
	// Close the request
	curl_close($ch);
}

/**
 * Gets the local IP address of all broadcasting interfaces
 */
function getServerAddress()
{
	// Get IPs from hostname
	$ipList = trim(shell_exec('/bin/hostname -I'));
	if ( $ipList ) {
		$ipArr = explode(' ', $ipList);
		asort($ipArr);
		$ipList = implode(', ', $ipArr);
	}
	else {
		$ipList = 'n.a.';
	}
	return $ipList;
}

/**
 * Creates the notification text
 * @param float $temp The temperature in degree C.
 */
function createNotificationText($temp)
{
	$config = getConfig();
	$notifiactionConfig = $config['notification'];

	$message = "Your Raspberry Pi's temperature raised above your defined maximum temperature. \r\n\r\n";

	// Add Temp
	$message .= "Current temperature: " . ROUND($temp, 1) . " °C\r\n";

	// Add (optional) hostname
	if ($notifiactionConfig['show_hostname']) {
		$message .= "Hostname: " . gethostname() . "\r\n";
	}

	// Add (optional) IP address(es)
	if ($notifiactionConfig['show_ip_address']) {
		$message .= "IP Address: " . getServerAddress() . "\r\n";
	}

	return $message;
}

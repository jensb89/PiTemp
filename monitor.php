#!/usr/bin/php
<?php
include dirname(__FILE__) . '/lib/functions.php';

// Get current and limit temperature
$curTemp = exec('cat /sys/class/thermal/thermal_zone0/temp') / 1000;
$limTemp = exec('cat /sys/class/thermal/thermal_zone0/trip_point_0_temp') / 1000;

// Register current temperature to Plotly
$date = new DateTime();
$filePath = realpath(dirname(__FILE__));
$ret = exec($filePath.'/update_plotly.py \''.$date->format('Y-m-d H:i:s').'\' '.$curTemp);

// Log current temperature
$line = $date->format(DateTime::ISO8601) . ";" . $curTemp . "\n";
$filePath = realpath(dirname(__FILE__));
file_put_contents(dirname(__FILE__) . '/temperature.log', $line, FILE_APPEND);

// Check if we should send a notification
$config = getConfig();
$notificationConfig = $config['notification'];
$maxTemp = $notificationConfig['max_temp'];

// If temp is too high, send notification
if ((float)$curTemp >= (float)$maxTemp) {
	sendEmailNotification($curTemp);

	if ($notificationConfig['enable_pushover']) {
		$userkey = $notificationConfig['pushover_userkey'];
		sendPushoverNotification($curTemp, $userkey);
	}

	if ($notificationConfig['enable_pushbullet']) {
		$deviceid = $notificationConfig['pushbullet_deviceid'];
		$apikey = $notificationConfig['pushbullet_apikey'];
		sendPushbulletNotification($curTemp, $deviceid, $apikey);
	}
}

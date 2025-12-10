<?php
// dfwforecast.php - Weather forecast functions for DFW Hash events
// Uses Open-Meteo API for reliable weather data

// Get coordinates for a ZIP code (DFW area)
function getLatLonForZip($zip) {
	$coords = array(
		'75001' => array('lat' => 32.9618, 'lon' => -96.8353), // Addison
		'75023' => array('lat' => 33.0198, 'lon' => -96.6989), // Plano
		'75024' => array('lat' => 33.0790, 'lon' => -96.8003), // Plano
		'75025' => array('lat' => 33.0401, 'lon' => -96.7970), // Plano
		'75051' => array('lat' => 32.9273, 'lon' => -97.0978), // Grand Prairie
		'75052' => array('lat' => 32.7459, 'lon' => -97.0808), // Grand Prairie
		'75062' => array('lat' => 32.8279, 'lon' => -96.9700), // Irving
		'75063' => array('lat' => 32.9279, 'lon' => -96.9900), // Irving
		'75080' => array('lat' => 32.9756, 'lon' => -96.6800), // Richardson
		'75081' => array('lat' => 32.9484, 'lon' => -96.7298), // Richardson
		'75201' => array('lat' => 32.7876, 'lon' => -96.7984), // Dallas
		'75202' => array('lat' => 32.7767, 'lon' => -96.7970), // Dallas
		'75204' => array('lat' => 32.8029, 'lon' => -96.7836), // Dallas
		'75205' => array('lat' => 32.8168, 'lon' => -96.7836), // Dallas
		'75206' => array('lat' => 32.8401, 'lon' => -96.7733), // Dallas
		'75214' => array('lat' => 32.8412, 'lon' => -96.7490), // Dallas
		'75218' => array('lat' => 32.8451, 'lon' => -96.6989), // Dallas
		'75225' => array('lat' => 32.8651, 'lon' => -96.8067), // Dallas
		'75231' => array('lat' => 32.9012, 'lon' => -96.7700), // Dallas
		'75252' => array('lat' => 32.9537, 'lon' => -96.8215), // Dallas (North)
		'76051' => array('lat' => 32.9343, 'lon' => -97.0781), // Grapevine
		'76092' => array('lat' => 32.7357, 'lon' => -97.3083), // Southlake
	);
	
	if (isset($coords[$zip])) {
		return $coords[$zip];
	}
	
	// Default to Addison
	return array('lat' => 32.9618, 'lon' => -96.8353);
}

// Weather condition code to description
function getWeatherDescription($code) {
	// WMO Weather interpretation codes
	$descriptions = array(
		0 => 'Clear',
		1 => 'Mostly Clear',
		2 => 'Partly Cloudy',
		3 => 'Cloudy',
		45 => 'Foggy',
		48 => 'Foggy',
		51 => 'Light Drizzle',
		53 => 'Drizzle',
		55 => 'Heavy Drizzle',
		61 => 'Light Rain',
		63 => 'Rain',
		65 => 'Heavy Rain',
		71 => 'Light Snow',
		73 => 'Snow',
		75 => 'Heavy Snow',
		77 => 'Snow Grains',
		80 => 'Light Showers',
		81 => 'Showers',
		82 => 'Heavy Showers',
		85 => 'Light Snow Showers',
		86 => 'Snow Showers',
		95 => 'Thunderstorm',
		96 => 'Thunderstorm with Hail',
		99 => 'Severe Thunderstorm'
	);
	
	return isset($descriptions[$code]) ? $descriptions[$code] : 'Unknown';
}

// Get weather summary using Open-Meteo API
function getWeatherOneLiner($address, $eventDate) {
	// Parse the event date
	$eventTimestamp = strtotime($eventDate);
	$currentTimestamp = time();
	
	// Calculate days until event (compare dates only, not times)
	$eventDay = date('Y-m-d', $eventTimestamp);
	$currentDay = date('Y-m-d', $currentTimestamp);
	
	$eventDayTimestamp = strtotime($eventDay);
	$currentDayTimestamp = strtotime($currentDay);
	
	$daysUntil = floor(($eventDayTimestamp - $currentDayTimestamp) / 86400);
	
	// If event already passed
	if ($daysUntil < 0) {
		return "Event has passed";
	}
	
	// If event is more than 10 days away
	if ($daysUntil > 10) {
		return "Nothing yet";
	}
	
	// Extract ZIP code from address
	$weatherZip = '';
	$address = str_replace("<br />", " ", $address);
	$address = str_replace("<br/>", " ", $address);
	$address = str_replace("<br>", " ", $address);
	
	// Try to find ZIP code (5 digits)
	if (preg_match('/\b(\d{5})\b/', $address, $matches)) {
		$weatherZip = $matches[1];
	} else {
		$weatherZip = '75001'; // Default to Addison
	}
	
	// Get coordinates for ZIP
	$coords = getLatLonForZip($weatherZip);
	$lat = $coords['lat'];
	$lon = $coords['lon'];
	
	// Format date for API (YYYY-MM-DD)
	$forecastDate = date('Y-m-d', $eventTimestamp);
	
	// Open-Meteo API - free, no key required, reliable
	// Get daily forecast including temperature, precipitation, and weather code
	$url = "https://api.open-meteo.com/v1/forecast?" .
	       "latitude=" . $lat .
	       "&longitude=" . $lon .
	       "&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max,weathercode,windspeed_10m_max" .
	       "&temperature_unit=fahrenheit" .
	       "&windspeed_unit=mph" .
	       "&timezone=America/Chicago" .
	       "&start_date=" . $forecastDate .
	       "&end_date=" . $forecastDate;
	
	// Fetch with timeout
	$opts = array(
		'http' => array(
			'timeout' => 3,
			'user_agent' => 'DFW Hash Weather/1.0'
		)
	);
	$context = stream_context_create($opts);
	$response = @file_get_contents($url, false, $context);
	
	if ($response === false || empty($response)) {
		$weatherLink = 'https://forecast.weather.gov/MapClick.php?textField1=' . $weatherZip;
		return '<a href="' . $weatherLink . '" target="_blank">Check forecast</a>';
	}
	
	// Parse JSON (using json_decode, available in PHP 5.2+)
	$data = json_decode($response, true);
	
	if (!$data || !isset($data['daily'])) {
		$weatherLink = 'https://forecast.weather.gov/MapClick.php?textField1=' . $weatherZip;
		return '<a href="' . $weatherLink . '" target="_blank">Check forecast</a>';
	}
	
	// Extract weather data
	$daily = $data['daily'];
	$tempMax = isset($daily['temperature_2m_max'][0]) ? round($daily['temperature_2m_max'][0]) : '?';
	$tempMin = isset($daily['temperature_2m_min'][0]) ? round($daily['temperature_2m_min'][0]) : '?';
	$precipitation = isset($daily['precipitation_probability_max'][0]) ? $daily['precipitation_probability_max'][0] : 0;
	$weatherCode = isset($daily['weathercode'][0]) ? $daily['weathercode'][0] : 0;
	$windSpeed = isset($daily['windspeed_10m_max'][0]) ? round($daily['windspeed_10m_max'][0]) : '?';
	
	$condition = getWeatherDescription($weatherCode);
	
	// Build weather summary
	$summary = $tempMin . "-" . $tempMax . "°F, ";
	$summary .= $condition . ", ";
	$summary .= "Wind " . $windSpeed . " mph";
	
	if ($precipitation > 0) {
		$summary .= ", " . $precipitation . "% rain";
	}
	
	// Add advisory for extreme conditions
	if ($tempMax >= 100) {
		$summary .= " ⚠️ Extreme heat";
	} else if ($tempMax <= 32) {
		$summary .= " ⚠️ Freezing";
	}
	
	if ($windSpeed >= 25) {
		$summary .= " ⚠️ High winds";
	}
	
	if ($precipitation >= 70) {
		$summary .= " ⚠️ High rain";
	}
	
	return $summary;
}
?>
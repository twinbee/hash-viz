<?php
/**
 * Twilight Calculator for Dallas, TX area
 * Calculates civil twilight end time for a given date
 * 
 * Usage: 
 *   require_once('twilight.php');
 *   $twilightTime = getTwilightEnd($day, $month, $year);
 */

/**
 * Calculate end of civil twilight for Dallas, TX (75023)
 * 
 * @param int $day Day of month
 * @param int $month Month (1-12)
 * @param int $year Year (e.g., 2026)
 * @return string Formatted time (e.g., "5:21 PM")
 */
function getTwilightEnd($day, $month, $year) {
	// Dallas, TX coordinates (75023 - Plano area)
	$latitude = 33.0198;
	$longitude = -96.6989;
	
	// Create timestamp for the date
	$timestamp = mktime(12, 0, 0, $month, $day, $year);
	
	// Get civil twilight end time
	$sunInfo = date_sun_info($timestamp, $latitude, $longitude);
	
	if (isset($sunInfo['civil_twilight_end']) && $sunInfo['civil_twilight_end'] !== true && $sunInfo['civil_twilight_end'] !== false) {
		// Convert to local time (Central Time)
		// Determine if DST is in effect
		$dateTime = new DateTime("@" . $sunInfo['civil_twilight_end']);
		$dateTime->setTimezone(new DateTimeZone('America/Chicago'));
		
		return $dateTime->format('g:i A');
	}
	
	// Fallback: use approximation if date_sun_info fails
	return getTwilightEndApprox($day, $month, $year);
}

/**
 * Approximate twilight calculation (fallback)
 * Based on seasonal variation for DFW area
 * 
 * @param int $day Day of month
 * @param int $month Month (1-12)
 * @param int $year Year (e.g., 2026)
 * @return string Formatted time (e.g., "5:21 PM")
 */
function getTwilightEndApprox($day, $month, $year) {
	$timestamp = mktime(12, 0, 0, $month, $day, $year);
	$dayOfYear = date('z', $timestamp);
	
	// Approximate civil twilight end times for DFW (varies ~5:45 PM to 9:00 PM)
	// Winter solstice (~Dec 21, day 355) = earliest ~5:45 PM
	// Summer solstice (~Jun 21, day 172) = latest ~9:00 PM
	$minMinutes = 17 * 60 + 45; // 5:45 PM in minutes from midnight
	$maxMinutes = 21 * 60 + 0;  // 9:00 PM in minutes from midnight
	
	// Calculate based on day of year using cosine wave
	// Shifted so minimum is at winter solstice (day 355/Dec 21)
	$angle = ($dayOfYear - 172) * (2 * M_PI / 365);
	$twilightMinutes = $minMinutes + ($maxMinutes - $minMinutes) * (1 + cos($angle)) / 2;
	
	$hours = floor($twilightMinutes / 60);
	$minutes = round($twilightMinutes % 60);
	
	// Format as 12-hour time
	$ampm = $hours >= 12 ? 'PM' : 'AM';
	$hours12 = $hours > 12 ? $hours - 12 : $hours;
	if ($hours12 == 0) $hours12 = 12;
	
	return sprintf('%d:%02d %s', $hours12, $minutes, $ampm);
}
?>
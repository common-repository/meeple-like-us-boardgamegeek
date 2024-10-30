<?php
/**
 * Plugin Name: Meeple Like Us Boardgamegeek Plugin
 * Description: Meeple Like Us Boardgamegeek Plugin
 * Plugin URI: http://meeplelikeus.co.uk/meeple-like-us-plugin/
 * Author: Michael Heron
 * Author URI: http://michael.imaginary-realities.com
 * Version: 1.6.5
 * Licence: CC-BY 4.0
 * Licence URI: https://creativecommons.org/licenses/by/4.0/
 */

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

register_activation_hook (__FILE__, 'register_mlu_bgg_plugin');
register_deactivation_hook (__FILE__, 'deregister_mlu_bgg_plugin');

include 'bgg_options.php';

function mlu_bgg_query_database_table() {
	global $wpdb;

	$mlubggtab = "mlubgg";
	
	$table_name = $wpdb->prefix . $mlubggtab; 

	return $table_name;
}

function mlu_bgg_get_IP() {

	if (isset($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	}
	else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	
	return $ip;	
} 

function register_mlu_bgg_plugin() {
	$table_name = mlu_bgg_query_database_table();
	
	// Create the cache table - used to reduce the number of queries against the 
	// server.
	$query = "CREATE TABLE $table_name (
  	id INT NOT NULL AUTO_INCREMENT,
  	CacheCategory VARCHAR (1000),
  	CacheEntry VARCHAR (1000),
  	CacheContent LONGTEXT,
  	CacheTimestamp INT,
	  PRIMARY KEY  (id));";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	dbDelta( $query);

	// Set the default settings.
	
	update_option ("AverageWeight", 1);
	update_option ("BggRank", 1);
	update_option ("PlayCount", 1);
	update_option ("Designers", 1);
	update_option ("Artists", 1);
	update_option ("Publishers", 1);
	update_option ("Mechanisms", 1);
	update_option ("Affiliate", 0);
	update_option ("Image", 1);
}

function deregister_mlu_bgg_plugin() {
	global $wpdb;

	$table_name = mlu_bgg_query_database_table();

	// Get rid of the cache table
	$query = "DROP TABLE IF EXISTS $table_name;";
	
	$wpdb->query($query);
}

function mlu_bgg_get_cache_contents ($cat, $entry) {
	global $wpdb;

	$table_name = mlu_bgg_query_database_table();

	// Get the results from the cache.
	$results = $wpdb->get_results ("SELECT * FROM $table_name WHERE CacheCategory = '$cat' AND CacheEntry = '$entry'");
	
	foreach ($results as $result) {
		// Cache entries hang around for eight hours 
		if ($result->CacheTimestamp + (60 * 60 * 8) > time()) {
			return $result->CacheContent;
		}		
	}

	// This has timed out, so get rid of it.  
	$wpdb->delete ($table_name, array (
		"CacheCategory" => $cat,
		"CacheEntry" => $entry,
	));
		
	return null;
}

function mlu_bgg_set_cache_contents ($cat, $entry, $contents) {
	global $wpdb;

	$table_name = mlu_bgg_query_database_table();

	// Insert this into the cache database so that we don't need to query
	// the server so often.	
	$results = $wpdb->replace($table_name, 
		array (
			"CacheCategory" => $cat,
			"CacheEntry" => $entry,
			"CacheContent" => $contents,
			"CacheTimestamp" => time(),
		),
		array( 
			'%s', 
			'%s', 
			'%s', 
			'%d' 
		) 
	);
		
	return null;
}

function mlu_bgg_load_from_api ($url, $cat, $entry, $bypass = false, $dontsave = false) {
	// First, check to see if this is in the cache.
	if (!$bypass) {
		$contents = mlu_bgg_get_cache_contents ($cat, $entry);
	
		if ($contents != null) {
			// It is!  So return in.
			return $contents;
		}
	}
	
	// Oh god, now we've got to go to the server.  Let's hope it's working!
	$file = fopen ($url, "r");

	if ($file == null) {
		if ($contents != null) {			
			return $contents;
		}
		return "";
	}
	
	$contents = "";
	
	while (!feof($file)) {
		// Read in the XML.  Read it all in.
		$contents .= fread($file, 8192);
	}

 
	fclose($file);

	// That seems to have gone well enough, so let's store what we got in the cache.
	// Let's not tempt fate.
	if (!$bypass && !$dontsave) {
		mlu_bgg_set_cache_contents ($cat, $entry, $contents);
	}
	
	return $contents;
}

function mlu_bgg_add_row($table, $key, $value, $colourise = false) {
	
	// Add a row of the table.
	$table .= "<tr class = \"meeple_like_us_row\">";
	$table .= "<td class = \"meeple_like_us_value\"><b>" . $key . "</b></td>";

	if (!$colourise) {
		$table .= "<td class = \"meeple_like_us_key\" >" . $value. "</td>";
	}
	else {
		$value = strtoupper (trim ($value));
		$col = mlu_bgg_do_colour ($value);
		$style = "text-align: center; background: " . $col;
		$table .= "<td style = \"$style\" class = \"meeple_like_us_key\" ><b>" . $value. "</b></td>";

	}
	$table .= "</tr>";
	return $table;
	
}

// Check to see if this element should be included in the output.
function mlu_bgg_query_include_element ($attr, $element) {
	$element = strtolower ($element);
	
	
	if (!isset ($attr[$element])) {
		// If it hasn't been set locally, use the options.
		return get_option ($element);
	}
	
	// You can switch things off at the shortcode level.
	if ($attr[$element] == 0) {
		return false;
	}
	
	return true;
	
}

function mlu_bgg_get_custom_label ($var, $default) {
	return get_option ($var, $default);
}

function mlu_bgg_embed_bgg($attr) {
	$html = "";
	$note = null;
	$id = $attr["id"];
	
	if (isset ($attr["decimal"])) {
		$decimal = 	$attr["decimal"];
	}
	else {
		$decimal = 2;
	}

	if (isset ($attr["notes"])) {
		$note = $attr["notes"];
	}

	$brief = 0;
	$rev = 0;
	
	if (!$id) {
		return "";
	}	

	$ext = mlu_bgg_get_external();	

	// Are we using the teardown link or the review link?
	if (isset ($attr["review"])) {
		$rev = $attr["review"];
	}
	
	// This is the URL of the back-end API.  
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=findgame&id=$id";
		
	$contents = mlu_bgg_load_from_api ($url, "bgg", $id);
	
	if ($contents == false) {	
		return "";
	}
	$html .= "<div class = \"meeple_like_us_container\">";
	$html .= "<table cellpadding = \"0\" class = \"meeple_like_us_table\">";
	$html .= "<tr>";
	$html .= "<th style = \"text-align: center;\" colspan = \"2\">";
	$html .= "<a href = \"http://meeplelikeus.co.uk/meeple-like-us-plugin/\" $ext>Game Details</a>";
	$html .= "</th>";
		
	$html .= "</tr>";
	
	$xml = simplexml_load_string ($contents);

	// If no XML can be parsed or there's no entry in the server, just output nothing.
	if (!$xml || $xml->Error) {
		return "";
	}
	$url = "https://boardgamegeek.com/boardgame/$id/";

	// Check to see if there's an image wanted.
	if (mlu_bgg_query_include_element ($attr, "Image")) {
		if ($xml->Image) {
			// Yep, we'll output it.
			$img = $xml->Image;
			$html .= "<tr class \"meeple_like_us_table_header\">";
			$html .= "<td colspan=\"2\"><center><img src=\"$img\" height = \"" . get_option ("ImageSize") . "%\" width = \"" . get_option ("ImageSize") . "%\"/></center></td>";		
			$html .= "</tr>";
		}
	}	
	
	// Get the name and the year.
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("GameNameLabel", "Name"), "<a href = \"$url\" $ext>" . $xml->Name . "</a> (" . $xml->Year . ")");
	
	
	if (!empty ($xml->Teardown)) {
		// A teardown is present.
			if (!$rev) {
				// And that's what we're using.
				$url = $xml->Teardown;		
				if ($url != "") {
					$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("AccessibilityLabel", "Accessibility Report"), "<a href = \"$url\" $ext>Meeple Like Us</a>");
				}
			}
			else {
				// We want to use the review, so we will.
				$url = $xml->Review;		
				if ($url != "") {
					$html= mlu_bgg_add_row($html, "Review", "<a href = \"$url\" $ext>Meeple Like Us</a>");
				}
			}
	}


	if (mlu_bgg_query_include_element ($attr, "AverageWeight")) {
		// Include the weight of the game.
		$weight = $xml->AverageWeight;
	
		$weight = number_format((float)$weight, $decimal);
		
		// Convert the number into a descriptive label.
		if ($weight < 1.5) {
			$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("AverageWeightLabel", "Complexity"), 
				mlu_bgg_get_custom_label ("LightWeightLabel", "Light") . " [" .$weight."]");
		}
		else if ($weight >= 1.5 && $weight < 2.5) {
			$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("AverageWeightLabel", "Complexity"), 
				mlu_bgg_get_custom_label ("MediumLightWeightLabel", "Medium Light") . " [" .$weight."]");
		}		
		else if ($weight >= 2.5 && $weight < 3.5) {
			$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("AverageWeightLabel", "Complexity"), 
				mlu_bgg_get_custom_label ("MediumWeightLabel", "Medium") . " [" .$weight ."]");
		}		
		else if ($weight >= 3.5 && $weight < 4.5) {
			$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("AverageWeightLabel", "Complexity"), 
				mlu_bgg_get_custom_label ("MediumHeavyLabel", "Medium Heavy") . " [".  $weight ."]");
		}		
		else {
			$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("AverageWeightLabel", "Complexity"), 
				mlu_bgg_get_custom_label ("HeavyWeightLabel", "Heavy") . " [" . $weight ."]");
		}
	}
	
	if (mlu_bgg_query_include_element ($attr, "BggRank")) {
		// Put in the BGG rank.
		$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("BggRankLabel", "BGG Rank [User Rating]"), "<a href = \"https://boardgamegeek.com/browse/boardgame/page/" . (int)(($xml->BGGRank / 100) + 1) . "\" $ext>" . $xml->BGGRank . "</a> [" . number_format((float)$xml->UserAverage, $decimal) . "]");
	}
	
	
	if (mlu_bgg_query_include_element ($attr, "PlayCount")) {
		// Include player counts.
		if ((int)$xml->MinPlay != (int)$xml->MaxPlay) {
			$players = $xml->MinPlay . "-" . $xml->MaxPlay;
		}
		else {
			$players= "" . $xml->MinPlay;
		}
		
		// Player counts may differ from the recommendations for player count.  If they do, 
		// then add in the recommendations too.
		$recommend = $xml->Recommendations;
		 
		if (strcmp ($recommend, $players) == 0) {
			$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("PlayCountLabel", "Player Count"), $xml->Recommendations);
		}
		else {
			$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("PlayCountWithRecommendedLabel", "Player Count (recommended)"), $players . " (" . $xml->Recommendations . ")");
		}
	}
		
	if (mlu_bgg_query_include_element ($attr, "Designers")) {
		// Add the designers.
		$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("DesignersLabel", "Designer(s)"), $xml->Designers);
	}
	
	if (mlu_bgg_query_include_element ($attr, "Artists")) {
		// And the artists
		$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("ArtistsLabel", "Artists(s)"), $xml->Artists);
	}
	
	if (mlu_bgg_query_include_element ($attr, "Publishers")) {
		// And the publishers
		$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("PublishersLabel", "Publisher(s)"), $xml->Publishers);
	}
	
	if (mlu_bgg_query_include_element ($attr, "Mechanisms")) {
		// And the mechanisms.
		$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("MechanismsLabel", "Mechanism(s)"), $xml->Mechanics);
	}
	
	if (get_option ("Affiliate")) {
		// Are we using the MLU affiliate link?  Then do that.
			if ($xml->AmazonLink) {
				$url = $xml->AmazonLink;
				$html = mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("AffiliateLabel", "Buy it!"), 
					"<a href = \"$url\" $ext>Amazon Link</a>");
			}
	}
/*	else {
		$ip = mlu_bgg_get_IP();
		
		if ($ip) {
			$url = "http://imaginary-realities.com/bggapi/affiliate.php?ip=$ip";
				
			$contents = mlu_bgg_load_from_api ($url, "affiliateip", $ip . ":" . $xml->Name);
			
			$allxml = simplexml_load_String ($contents);
					
			if ($allxml->count() && !$allxml->Error) {
				
				$list = array();
				foreach ($allxml as $entry) {
					$url = $entry->site;
					$name= $entry->name;
					
					$url = str_replace ("XXX", $xml->Name, $url);
					
					array_push ($list, "<li><a href = \"$url\" $ext>$name</a></li>");										
				}
				
				$imp_list = implode ($list, "\n");
				
				$html = mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("SearchForLabel", "Search for this game"), "<ul>$imp_list</ul>");
			}
		}
	}	*/

	if ($note) {
		$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("NotesLabel", "Notes"), $note);
	
	}
	
	$html .= "</table>";
	$html .= "</div>";
	
	return $html;
}


// Create the table of accessibility grades, drawing in from the server.
function mlu_bgg_embed_mlu_table($attr) {
	
	$id = $attr["id"];
	$html = "";
	
	if (!$id) {
		return "";
	}	

	$ext = mlu_bgg_get_external();	
		
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility&id=$id";
		
	$contents = mlu_bgg_load_from_api ($url, "mlutable", $id);

	if ($contents == false) {	
		return "";
	}
	
	$xml = simplexml_load_String ($contents);

	if (!$xml || $xml->Error) {
		return "";
	}
			
	$name = "<a href = \"" . $xml->Teardown . "\" $ext>" . $xml->Name . "</a>";

	$html .= "<div class = \"meeple_like_us_accessibility_table\">";
	$html .= "<table cellpadding = \"0\" class = \"meeple_like_us_accessibility_table\">";
	$html .= "<caption align = \"bottom\" style = \"font-size:75%\">$name, <a href = \"http://meeplelikeus.co.uk\" $ext>Meeple Like Us</a>, [<a href = \"https://creativecommons.org/licenses/by/4.0/\" $ext>CC-BY 4.0</a>]</caption>";
	
	$html .= "<tr>";
	$html .= "<th>Category</th>";
	$html .= "<th>Grade</th>";
	$html .= "</tr>";
			
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("ColourBlindnessLabel", "Colour Blindness"), $xml->ColourBlindness, true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("VisualAccessibilityLabel", "Visual Accessibility"), $xml->VisualAccessibility, true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("FluidIntelligenceLabel", "Fluid Intelligence"), $xml->FluidIntelligence, true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("MemoryLabel", "Memory Accessibility"), $xml->Memory, true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("PhysicalAccessibilityLabel", "Physical Accessibility"), $xml->PhysicalAccessibility, true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("EmotionalAccessibilityLabel", "Emotional Accessibility"), $xml->EmotionalAccessibility, true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("SocioeconomicAccessibilityLabel", "Socioeconomic Accessibility"), $xml->SocioeconomicAccessibility, true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("CommunicationLabel", "Communication"), $xml->Communication, true);
	
	$html .= "</table>";
	$html .= "</div>";

	return $html;	
}

function mlu_bgg_do_lookup ($letter) {
	// Convert letters to numbers.
	$lookup = array();
	$lookup["f"] = 0;
	$lookup["e"] = 3;
	$lookup["d-"] = 4;
	$lookup["d"] = 5;
	$lookup["d+"] = 6;
	$lookup["c-"] = 7;
	$lookup["c"] = 8;
	$lookup["c+"] = 9;
	$lookup["b-"] = 10;
	$lookup["b"] = 11;
	$lookup["b+"] = 12;
	$lookup["a-"] = 13;
	$lookup["a"] = 14;
	$lookup["a+"] = 15;
	
	return $lookup[$letter];
}

function mlu_bgg_average_grade ($num) {
	
	$num = round ($num);
	
	$lookup = array();
	$lookup[0] = "F";
	$lookup[1] = "F";
	$lookup[2] = "F";
	$lookup[3] = "E";
	$lookup[4] = "D-";
	$lookup[5] = "D";
	$lookup[6] = "D+";
	$lookup[7] = "C-";
	$lookup[8] = "C";
	$lookup[9] = "C+";
	$lookup[10] = "B-";
	$lookup[11] = "B";
	$lookup[12] = "B+";
	$lookup[13] = "A-";
	$lookup[14] = "A";
	$lookup[15] = "A+";
	
	return $lookup[$num];
}

function mlu_bgg_create_radar($ext_att) {	
	$id = $ext_att["id"];
	$datasets = "";
	$labels = "";
	
	// This only works if the Wordpress Charts plugin in installed.  If it isn't, don't do 
	// anything.
	if (!function_exists ("wp_charts_shortcode")) {
		return "";
	}
	
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility&id=$id";
		
	$contents = mlu_bgg_load_from_api ($url, "mlutable", $id);

	if ($contents == false) {	
		return "";
	}
	
	$xml = simplexml_load_String ($contents);

	if (!$xml || $xml->Error) {
		return "";
	}

	$html = "<div class = \"mlu_radar_chart\">";	
	// Trim and turn the letter grades to lower case.
	$cb = strtolower (trim ($xml->ColourBlindness));
	$vi = strtolower (trim ($xml->VisualAccessibility));
	$fi = strtolower (trim ($xml->FluidIntelligence));
	$mi = strtolower (trim ($xml->Memory));
	$pi = strtolower (trim ($xml->PhysicalAccessibility));
	$ea = strtolower (trim ($xml->EmotionalAccessibility));
	$se = strtolower (trim ($xml->SocioeconomicAccessibility));
	$comm = strtolower (trim ($xml->Communication));
	
	$datasets = array();
	$labels = array();
	
	// Add numbers and labels
	array_push ($datasets, mlu_bgg_do_lookup($cb));
	array_push ($labels, mlu_bgg_get_custom_label ("ColourBlindnessLabel", "Colour Blindness"));
	
	array_push ($datasets, mlu_bgg_do_lookup($vi));
	array_push ($labels, mlu_bgg_get_custom_label ("VisualAccessibilityLabel", "Visual Accessibility"));

	array_push ($datasets, mlu_bgg_do_lookup($fi));
	array_push ($labels, mlu_bgg_get_custom_label ("FluidIntelligenceLabel", "Fluid Intelligence"));

	array_push ($datasets, mlu_bgg_do_lookup($mi));
	array_push ($labels, mlu_bgg_get_custom_label ("MemoryLabel", "Memory Accessibility"));

	array_push ($datasets, mlu_bgg_do_lookup($pi));
	array_push ($labels, mlu_bgg_get_custom_label ("PhysicalAccessibilityLabel", "Physical Accessibility"));

	array_push ($datasets, mlu_bgg_do_lookup($ea));
	array_push ($labels, mlu_bgg_get_custom_label ("EmotionalAccessibilityLabel", "Emotional Accessibility"));

	array_push ($datasets, mlu_bgg_do_lookup($se));
	array_push ($labels, mlu_bgg_get_custom_label ("SocioeconomicAccessibilityLabel", "Socioeconomic Accessibility"));

	array_push ($datasets, mlu_bgg_do_lookup($comm));
	array_push ($labels, mlu_bgg_get_custom_label ("CommunicationLabel", "Communication"));

	// Create the attributes for the Wordpress Charts plugin
	
	$attrs = array();
	$attrs["title"] = "radarchart";
	$attrs["type"] = "radar";
	$attrs["align"] = "aligncenter";
	$attrs["margin"] = "0px 0px 0px 0px";
	$attrs["datasets"] = implode ($datasets, ",");
	$attrs["labels"] = implode ($labels, ",");	
	$attrs["colors"] = "#444f60";
	$attrs["scalefontsize"] = "14";
	$attrs["scaleoverride"] = "true";
	$attrs["scalesteps"] = "15";
	$attrs["scalestepwidth"] = "1";
	$attrs["scalestartvalue"] = "0";
	$attrs["width"] = "100%";
	
	
	$teardown = $xml->Teardown;
	
	$ext = mlu_bgg_get_external();	
	// Give the HTML from the wordpress plugin chart.  

	$html = "<a href = \"$teardown\" $ext>" .wp_charts_shortcode ($attrs) . "</a>";
		
	return $html;
}

function mlu_bgg_get_external () {
	$val = get_option( 'ExternalLinks', 0 );
	
	if ($val == 1) {
		return "";
	}
	else {
		return " target = \"_blank\"";
	}
	
}

function mlu_bgg_create_toc($xml) {
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility&full=1";
	
	$contents = mlu_bgg_load_from_api ($url, "mlutoc", "full_toc");

	if ($contents == false) {	
		return "";
	}
	
	if (!$xml) {
		$allxml = simplexml_load_String ($contents);
	}
	else {
		$allxml = $xml;
	}
	$html = "";
	
	if (!$allxml || $allxml->Error) {
		return "";
	}

	// Create the full TOC from the site.
	$html .= "<div class = \"meeple_like_us_toc_table\">";
	$html .= "<table cellpadding = \"0\" class = \"meeple_like_us_toc_table sortable\">";
	$html .= "<caption align = \"bottom\" style = \"font-size:75%\">* - A review copy was provided in exchange for a fair and honest review</caption>";

	$html .= "<thead class = \"meeple_like_us_toc_table_head\">";
	$html .= "<th class = \"meeple_like_us_toc_table_head\">Name</th>";
	$html .= "<th class = \"meeple_like_us_toc_table_head\">Rating</th>";
	$html .= "<th class = \"meeple_like_us_toc_table_head\">Teardown</th>";		
	$html .= "<th class = \"sorttable_numeric meeple_like_us_toc_table_head\">BGG Rank</th>";		
	$html .= "</thead>";		

	$ext = mlu_bgg_get_external();	
	// Give the HTML from the wordpress plugin chart.  
	$html .= "<tbody>";

	foreach ($allxml->item as $xml) { 
		$counter += 1;			
		$url = $xml->AmazonLink;
		
		$html .= "<tr>";
		if (strcmp ($xml->ReviewCopy, "y") == 0) {
			$html .= "<td width = \"30%\"><a href = \"$url\" $ext>". $xml->Name . " *</a></td>";
		}
		else {
			$html .= "<td width = \"30%\"><a href = \"$url\" $ext>". $xml->Name . "</a></td>";
		}

		$url = $xml->Review;
		$html .= "<td width = \"10%\"><a href = \"$url\" $ext>".  $xml->Rating . "<a></td>";

		if (!empty($xml->Teardown)) {			
			$url = $xml->Teardown;
			$html .= "<td width = \"10%\"><a href = \"$url\" $ext>teardown</a></td>";
		}
		else {
			$html .= "<td width = \"10%\">TBA</td>";
		}
		
		$rank = $xml->BggRank;

		$id = $xml->ID;
		
		$html .= "<td width = \"10%\">";		
		
		if ($xml->BggRank != 0) {
			$html .= "<a href = \"https://boardgamegeek.com/boardgame/$id\">" . $xml->BggRank . "</a>";
		}
		else {
			$html .= "<div style = \"visibility: hidden\">99999999</div><a href = \"https://boardgamegeek.com/boardgame/$id\">N/A</a>";
		}
		$html .= "</td>";		

		$html .= "</tr>";		
		
	}
	$html .= "</tbody>";

	$html .= "</table>";	
	$html .= "</div>";	
	
	return $html;
}

function mlu_bgg_calculate_all_scorecards ($attr) {
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility&full=1";
	$publishers = array();
	$counter = 0;
		
	$contents = mlu_bgg_load_from_api ($url, "mlutoc", "allscorecards");

	if ($contents == false) {	
		return "";
	}
	
	$allxml = simplexml_load_String ($contents);

	if (!$allxml || $allxml->Error) {
		return "";
	}
	
	foreach ($allxml->item as $xml) { 
		if (in_array ($xml->Publisher->__toString(), $publishers)) {
			continue;
		}
		
		array_push ($publishers, $xml->Publisher->__toString());
	}
	
	$html = "";
	
//	print_r ($publishers);
	
	asort ($publishers);
	
	foreach ($publishers as $pub) {
		$arr = array ("publisher" => $pub);
		$html .= mlu_bgg_calculate_publisher_scorecard ($arr);
	}
	
	return $html;
}

function mlu_bgg_calculate_publisher_scorecard ($attr) {
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility&full=1";
	$ratings = array();
	$games = array();
	$counter = 0;

	$publisher = strtolower ($attr["publisher"]);
		
	$contents = mlu_bgg_load_from_api ($url, "mlutoc", "scorecard$publisher");

	if ($contents == false) {	
		return "";
	}
	
	$allxml = simplexml_load_String ($contents);

	if (!$allxml || $allxml->Error) {
		return "";
	}

	$ratings["cb"] = 0;
	$ratings["vi"] = 0;
	$ratings["fi"] = 0;
	$ratings["mi"] = 0;
	$ratings["pa"] = 0;
	$ratings["ea"] = 0;
	$ratings["sa"] = 0;
	$ratings["comm"] = 0;

	foreach ($allxml->item as $xml) { 
		if (strtolower ($xml->Publisher) != strtolower ($publisher) && strpos (strtolower ($xml->Publisher), strtolower ($publisher)) === false) {
			continue;
		}
		
		array_push ($games, "<a href = \"$xml->Teardown\">" . $xml->Name . "</a>");
		
		$ratings["cb"] += mlu_bgg_do_lookup (strtolower ($xml->ColourBlindness));
		$ratings["vi"] += mlu_bgg_do_lookup (strtolower ($xml->VisualAccessibility));
		$ratings["fi"] += mlu_bgg_do_lookup (strtolower ($xml->FluidIntelligence));
		$ratings["mi"] += mlu_bgg_do_lookup (strtolower ($xml->Memory));
		$ratings["pa"] += mlu_bgg_do_lookup (strtolower ($xml->PhysicalAccessibility));
		$ratings["ea"] += mlu_bgg_do_lookup (strtolower ($xml->EmotionalAccessibility));
		$ratings["sa"] += mlu_bgg_do_lookup (strtolower ($xml->SocioeconomicAccessibility));
		$ratings["comm"] += mlu_bgg_do_lookup (strtolower ($xml->Communication));
		
		$counter += 1;			

	}
	

	if ($counter == 0) {
		return "<p>No such scorecard as $publisher</p>";
	}
		
	$publisher = ucwords ($publisher);
	
	$html = "<div class = \"meeple_like_us_publisher_table\">";
	$html .= "<h1>Publisher $publisher</h1>";
	$html .= "<b>Scorecard based on $counter game(s) in the MLU database</b>";	
	
	$html .= "<ol>";	
	foreach ($games as $game) {
		$html .= "<li>$game</li>";	
	}
	$html .= "</ol>";	
	
	$html .= "<table cellpadding = \"0\" class = \"meeple_like_us_accessibility_table\">";
	

	$ratings["cb"] /= $counter;
	$ratings["vi"] /= $counter;
	$ratings["fi"] /= $counter;
	$ratings["mi"] /= $counter;
	$ratings["pa"] /= $counter;
	$ratings["ea"] /= $counter;
	$ratings["sa"] /= $counter;
	$ratings["comm"] /= $counter;
		
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("ColourBlindnessLabel", "Colour Blindness"), mlu_bgg_average_grade ($ratings["cb"]), true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("VisualAccessibilityLabel", "Visual Accessibility"), mlu_bgg_average_grade ($ratings["vi"]), true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("FluidIntelligenceLabel", "Fluid Intelligence"), mlu_bgg_average_grade ($ratings["fi"]), true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("MemoryLabel", "Memory"), mlu_bgg_average_grade ($ratings["mi"]), true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("PhysicalAccessbilityLabel", "Physical Accessibility"), mlu_bgg_average_grade ($ratings["pa"]), true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("EmotionalAccessibilityLabel", "Emotional Accessibility"), mlu_bgg_average_grade ($ratings["ea"]), true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("SocioeconomicAccessibilityLabel", "Socioeconomic Accessibility"), mlu_bgg_average_grade ($ratings["sa"]), true);
	$html= mlu_bgg_add_row($html, mlu_bgg_get_custom_label ("CommunicationLabel", "Communication"), mlu_bgg_average_grade ($ratings["comm"]), true);
	
	$html .= "</table>";
	$html .= "</div>";
		
	return $html;
}

function mlu_bgg_calculate_coverage() {
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility&full=1";
	$coverage10 = 0;
	$coverage100 = 0;
	$coverage250 = 0;
	$coverage500 = 0;
	$coverage1000 = 0;
	$counter = 0;
	
	$contents = mlu_bgg_load_from_api ($url, "mlutoc", "full");
	
	if ($contents == false) {	
		return "";
	}

	$allxml = simplexml_load_String ($contents);

	if (!$allxml || $allxml->Error) {
		return "";
	}


	foreach ($allxml->item as $xml) { 
		$counter += 1;			
		$rank = $xml->BggRank;

		if ($rank != 0) {
			if ($rank <= 10) {
				$coverage10 += 1;
			}

			if ($rank <= 100) {
				$coverage100 += 1;
			}

			if ($rank <= 250) {
				$coverage250 += 1;
			}

			if ($rank <= 500) {
				$coverage500 += 1;
			}

			if ($rank <= 1000) {
				$coverage1000 += 1;
			}
			

		}		
	}
	
	$coverage10 = number_format (($coverage10/10) * 100.0, 2);
	$coverage100 = number_format (($coverage100/100) * 100.0, 2);
	$coverage250 = number_format (($coverage250/250) * 100.0, 2);
	$coverage500 = number_format (($coverage500/500) * 100.0, 2);
	$coverage1000 = number_format (($coverage1000/1000) * 100.0, 2);
		
	
	$html = "<table class = \"mlu_stats_coverage meeple_like_us_table\">";
	$html .= "<tr>";
	$html .= "<th>Coverage</th>";
	$html .= "<th>Percentage</th>";
	$html .= "</tr>";
	$html .= "<tr>";
	$html .= "<td>Top Ten</th>";
	$html .= "<td>$coverage10%</td>";
	$html .= "</tr>";
	$html .= "<tr>";
	$html .= "<td>Top One Hundred</th>";
	$html .= "<td>$coverage100%</td>";
	$html .= "</tr>";
	$html .= "<tr>";
	$html .= "<td>Top Two Hundred and Fifty</th>";
	$html .= "<td>$coverage250%</td>";
	$html .= "</tr>";
	$html .= "<tr>";
	$html .= "<td>Top Five Hundred</th>";
	$html .= "<td>$coverage500%</td>";
	$html .= "</tr>";
	$html .= "<tr>";
	$html .= "<td>Top Thousand</th>";
	$html .= "<td>$coverage1000%</td>";
	$html .= "</tr>";
	$html .= "</table>";
	

	
	return $html;
}

function mlu_bgg_do_colour ($val) {
	$val = strtolower (trim ($val));

	$num = mlu_bgg_do_lookup ($val);
	
	if ($num < 3) {
		$col = "grey";
	}
	else if ($num == 3) {
		$col = "red";
	} 
	else if ($num < 7) {
		$col = "orange";
	} 
	else if ($num < 10) {
		$col = "yellow";
	} 
	else if ($num < 13) {
		$col = "lightgreen";
	} 
	else {
		$col = "green";
	} 

	return $col;	
}


function mlu_bgg_do_masterlist_entry ($val) {
	$col = mlu_bgg_do_colour ($val);
	$val = strtolower (trim ($val));
	
	$num = mlu_bgg_do_lookup ($val);
	
	$style = "background: " . $col;
	
	$val = strtoupper ($val);
	
	return "<td style = \"$style;\"><b><center>" . "<div style = \"visibility: hidden\"> " . $num. "</div> $val</b></center></td>";
}


function mlu_bgg_create_masterlist() {
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility";
		
	$contents = mlu_bgg_load_from_api ($url, "mlutoc", "masterlist");

	if ($contents == false) {	
		return "";
	}
	
	$allxml = simplexml_load_String ($contents);

	if (!$allxml || $allxml->Error) {
		return "";
	}

	// Create the full TOC from the site.
	$html .= "<div class = \"meeple_like_us_masterlist_table_div\">";
	$html .= "<table id = \"masterlist_table\" style = \"border: 0px; table-layout: fixed;\" cellpadding = \"0\" class = \"meeple_like_us_masterlist_table sortable\">";
	$html .= "<caption align = \"bottom\" style = \"font-size:75%\">* - A review copy was provided in exchange for a fair and honest review</caption>";

	$html .= "<thead>";
	$html .= "<tr>";
	$html .= "<td class = \"meeple_like_us_masterlist_tableheading\"width = \"35%\"><div><span><b>Game name</b></div></span></th>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Colour Blindness</b></div></span></td>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Visual Accessibility</b></div></span></td>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Fluid Intelligence</b></div></span></td>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Memory</b></div></span></td>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Physical Accessibility</b></div></span></td>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Emotional Accessibility</b></div></span></td>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Socioeconomic Accessibility</b></div></span></td>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Communication</b></div></span></td>";
	$html .= "<td class = \"sorttable_numeric meeple_like_us_masterlist_tableheading\"><div><span class = \"meeple_like_us_masterlist_tableheading_span\"><b>Rating</b></div></span></td>";
	$html .= "</tr>";		
	$html .= "</thead>";

	$html .= "<tbody>";

	foreach ($allxml->item as $xml) { 			
		$url = $xml->AmazonLink;
		
		$html .= "<tr>";
		
		$url = $xml->Teardown;
		$html .= "<td><a href = \"$url\">" . $xml->Name . "</a></td>";
		$html .= mlu_bgg_do_masterlist_entry ($xml->ColourBlindness);
		$html .= mlu_bgg_do_masterlist_entry ($xml->VisualAccessibility);
		$html .= mlu_bgg_do_masterlist_entry ($xml->FluidIntelligence);
		$html .= mlu_bgg_do_masterlist_entry ($xml->Memory);
		$html .= mlu_bgg_do_masterlist_entry ($xml->PhysicalAccessibility);
		$html .= mlu_bgg_do_masterlist_entry ($xml->EmotionalAccessibility);
		$html .= mlu_bgg_do_masterlist_entry ($xml->SocioeconomicAccessibility);
		$html .= mlu_bgg_do_masterlist_entry ($xml->Communication);
		
		$url = $xml->Review;
		$html .= "<td> <a href = \"$url\"><center>" . $xml->Rating. "</center></a></td>";


		$html .= "</tr>";		
		
	}
	
	$html .= "</tbody>";

		
	$html .= "</table>";
	$html .= "</div>";

	return $html;
}

function mlu_bgg_get_xml($attr) {
	$html = "";
	$id = $attr["id"];
		
	if (!$id) {
		return null;
	}	
	
	// This is the URL of the back-end API.  
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=findgame&id=$id";
		
	$contents = mlu_bgg_load_from_api ($url, "bgg", $id);
	
	if ($contents == false) {	
		return "";
	}

	$xml= simplexml_load_String ($contents);

	return $xml;
}

function mlu_bgg_embed_bgg_rank ($attr) {
	$xml = mlu_bgg_get_xml ($attr);

	if (!$xml|| $xml->Error) {
		return "";
	}
	
	return $xml->BGGRank;
}

function mlu_bgg_embed_bgg_weight($attr) {
	$xml = mlu_bgg_get_xml ($attr);

	if (!$xml|| $xml->Error) {
		return "";
	}

	if (isset ($attr["decimal"])) {
		$decimal = 	$attr["decimal"];
	}
	else {
		$decimal = 2;
	}
	
	$weight = number_format((float)$xml->AverageWeight, $decimal);
	
	return $weight;
}

function mlu_bgg_embed_bgg_image ($attr) {
	$xml = mlu_bgg_get_xml ($attr);

	if (!$xml|| $xml->Error) {
		return "";
	}
	
	return "<img src=\"" . $xml->Image . "\" alt = \"Image from BGG\"></img>";
}

function mlu_bgg_embed_bgg_complexity ($attr) {
	$xml = mlu_bgg_get_xml ($attr);
	$html = "";
		
	
	if (isset ($attr["decimal"])) {
		$decimal = 	$attr["decimal"];
	}
	else {
		$decimal = 2;
	}


	if (isset ($attr["use-class"])) {
		$useclass = true;
	}
	else {
		$useclass = false;
	}
	
	if (!$xml|| $xml->Error) {
		return "";
	}

	$rank = (float)$xml->AverageWeight;

	$val_to_display = number_format((float)$xml->AverageWeight, $decimal);

	if ($useclass) {	
		$rank_class = number_format ($rank, 2);
		$weight_class = floor ($rank);
		
		$html = "<div class = \"bggweight bggweight-$rank_class bggweight-$weight_class\">";
		$html .= "$val_to_display";
		$html .= "</div>";
	}
	else {
		$html .= "$val_to_display";
	}
	
	return $html;
	
}

function mlu_bgg_embed_bgg_rating ($attr) {
	$xml = mlu_bgg_get_xml ($attr);
	$html = "";
		
	
	if (isset ($attr["decimal"])) {
		$decimal = 	$attr["decimal"];
	}
	else {
		$decimal = 2;
	}


	if (isset ($attr["use-class"])) {
		$useclass = true;
	}
	else {
		$useclass = false;
	}
	
	if (!$xml|| $xml->Error) {
		return "";
	}

	$rank = (float)$xml->UserAverage;

	$val_to_display = number_format((float)$xml->UserAverage, $decimal);

	if ($useclass) {	
		$rank_class = number_format ($rank, 2);
		$weight_class = floor ($rank);
		
		$html = "<div class = \"bggrank bggrank-$rank_class bggrank-$weight_class\">";
		$html .= "$val_to_display";
		$html .= "</div>";
	}
	else {
		$html .= "$val_to_display";
	}
	
	return $html;
	
}

function mlu_bgg_embed_bgg_description($attr) {
	$xml = mlu_bgg_get_xml ($attr);

	if (!$xml|| $xml->Error) {
		return "";
	}
	
	return $xml->Description;
}

function mlu_bgg_mean ($arr) {
	$sum = 0;

  for ($i = 0; $i < sizeof ($arr); $i++) {
  	$sum += $arr[$i];
  }

	$mean = $sum/sizeof ($arr);

	return number_format ($mean, 2);
}

function mlu_bgg_median ($arr) {
	sort ($arr);	
	$mid = floor ((sizeof ($arr)-1)/2);
	
	if (sizeof ($arr) % 2 == 0) {
		$high = $arr[$mid+1];
		$low = $arr[$mid-1];
		
		return ($high + $low) / 2;
	}
	else {
		return $arr[$mid];
	}
}

function mlu_bgg_std_dev($arr) {
	$sum_sq = 0;
	$new_arr = array();

	$mean = mlu_bgg_mean ($arr);
	
  for ($i = 0; $i < sizeof ($arr); $i++) {
  	$calc = pow ($arr[$i] - $mean, 2);
		array_push ($new_arr, $calc);
  }
	$sum_sq = mlu_bgg_mean ($new_arr); 
		
	return number_format (sqrt ($sum_sq), 2);
	
}

function mlu_bgg_create_bar($ext_att) {	
	$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility";
	$arr = array();
	$sum = 0;
	$total = 0;
	$title = array();
	$each_game = array();
	
	$title["ColourBlindness"] = "Colour Blindness";
	$title["VisualAccessibility"] = "Visual Accessibility";
	$title["FluidIntelligence"] = "Fluid Intelligence";
	$title["Memory"] = "Memory";
	$title["PhysicalAccessibility"] = "Physical Accessibility";
	$title["EmotionalAccessibility"] = "Emotional Accessibility";
	$title["SocioeconomicAccessibility"] = "Socioeconomic Accessibility";
	$title["Communication"] = "Communication";
	
	
	$category = $ext_att["category"];

	if (!isset ($title[$category])) {
		return "No such category.";
	}
	
	$tit = $title[$category];
	
	$contents = mlu_bgg_load_from_api ($url, "mlutoc", "full");

	if ($contents == false) {	
		return "";
	}
	
	$allxml = simplexml_load_String ($contents);

	if (!$allxml || $allxml->Error) {
		return "";
	}


	foreach ($allxml->item as $xml) { 			
		$val = $xml->$category;
		array_push ($each_game, mlu_bgg_do_lookup (strtolower ($val)));
		$val = substr ($val, 0, 1);

		
		if (!isset ($arr[$val])) {
			$arr[$val] = 0;
		}
		
		$arr[$val] += 1;
		$total += 1;
	} 
	
	$keys = array_keys ($arr);
	sort ($keys);

	
	$datasets = array();
	$labels = array();
	
	for ($i = 0; $i < sizeof ($keys); $i++) {

		$key = $keys[$i];
		$val = $arr[$key];
		
		if (intval ($val) > $sum) {
			$sum = intval ($val);
		}
		
		array_push ($datasets, $val);
		array_push ($labels, $key . " ($val)");
		
	}
				
	$attrs = array();
	$attrs["title"] = "barchart".$category;
	$attrs["type"] = "bar";
	$attrs["align"] = "aligncenter";
	$attrs["margin"] = "0px 0px 0px 0px";
	$attrs["datasets"] = implode ($datasets, ",");
	$attrs["labels"] = implode ($labels, ",");	
	$attrs["colors"] = "#5f56c4";
	$attrs["scalefontsize"] = "12";
	$attrs["scaleoverride"] = "true";
	$attrs["scalesteps"] = $sum;
	$attrs["scalestepwidth"] = "1";
	$attrs["scalestartvalue"] = "0";
	$attrs["width"] = "75%";

	$mean = mlu_bgg_mean ($each_game);
	$std = mlu_bgg_std_dev ($each_game);
	$median = mlu_bgg_median ($each_game);
	$av_cat = mlu_bgg_average_grade ($mean);
	
	$html = "<div class = \"mlu_table_chart\">";	
	$html .= "<p><b>Average for Category is $av_cat</b></p>";	
	
	$html .= "<p>N=$total, drived only from public results on <a href = \"http://http://meeplelikeus.co.uk/list-of-reviews/\">the Meeple Like Us list of reviews</a>.  </p>";
	
	$html .= "<p>Mean of ratings = $mean, Median of ratings = $median, Std Dev of ratings = $std</p>";	


	
	
	$html .= wp_charts_shortcode ($attrs);

	$html .= "</div>";		
	return $html;

}


		
function mlu_bgg_create_collection($arr) {
	$username = $arr["username"];
	$html = "<div class = \"bgg_collection bgg_collection_$username\">";
	$url = "http://imaginary-realities.com/bggapi/collection.php?username=$username";

	if ($arr["owned"]) {
		$url .= "&own=1";
	}


	if (isset ($arr["title"])) {
		$html .= "<h1>" . $arr["title"] . "</h1>";
	}
	else {
		$html .= "<h1>Collection: $username</h1>";
	}
		
	$contents = mlu_bgg_load_from_api ($url, "collection", $username, false, true);

	if ($contents == false) {	
		return "";
	}
	
	$xml = simplexml_load_string ($contents);

	// If no XML can be parsed or there's no entry in the server, just output nothing.
	if (!$xml || $xml->Error) {
		return "<p>Error: " . $xml->Error . "</p>";
	}

	
	// We do this differently because otherwise it might save the 'wait for loading' text from BGG.
	mlu_bgg_set_cache_contents ("collection", $username, $contents);
	
	$html .= "<table id = \"mlu_bgg_collection\" class = \"mlu_bgg_collection sortable\">";
//	$html .= "<script type=\"text/javascript\" src = \"http://meeplelikeus.co.uk/meeple_tools.js\"></script>";
//	$html .= "<input class = \"mlu_search_field\" type=\"text\" id=\"myCollInput\" onkeyup=\"searchTable('myCollInput', 'mlu_bgg_collection')\" placeholder=\"Search collection..\">";
	$html .= "<thead>";
	$html .= "<tr>";
	$html .= "<th valign = \"top\" class = \"bgg_collection_table_name bgg_collection_column\">Name</th>";
	if (get_option ("CollectionIncludeYear")) {
		$html .= "<th class = \"bgg_collection_column\" valign = \"top\">Year</th>";
	}
	if (get_option ("CollectionIncludeStatus")) {
		$html .= "<th class = \"bgg_collection_column\" valign = \"top\">Status</th>";
	}
	
	if (get_option ("CollectionIncludeNumPlays")) {
		$html .= "<th class = \"bgg_collection_column\" valign = \"top\">Num Plays</th>";
	}
		
	if (get_option ("CollectionIncludeComments")) {
		$html .= "<th class = \"bgg_collection_column\" valign = \"top\">Comment</th>";
	}

	if (get_option ("CollectionIncludeRating")) {
		$html .= "<th class = \"bgg_collection_column\" valign = \"top\">My Rating</th>";
	}

	if (get_option ("CollectionIncludeUserRating")) {
		$html .= "<th class = \"bgg_collection_column\" valign = \"top\">Avg. Rating</th>";
	}

	if (get_option ("CollectionIncludeBggRank")) {
		$html .= "<th class = \"bgg_collection_column\" valign = \"top\">BGG Rank</th>";
	}
	
	$html .= "</tr>";
	$html .= "</thead>";
	
	$html .= "<tbody>";
	
	
	$ext = mlu_bgg_get_external();	

	
	foreach ($xml as $entry) {
		$params = Array();
		
		$html .= "<tr>";
		$num = $entry["objectid"];
		
		$status = $entry->status;
		
		if ($status["own"] == 1) {
			array_push ($params, "Owned");
		}

		if ($status["prev"] == 1) {
			array_push ($params, "Prev. Owned");
		}

		if ($status["fortrade"] == 1) {
			array_push ($params, "For Trade");
		}

		if ($status["want"] == 1) {
			array_push ($params, "Wanted");
		}

		if ($status["wanttoplay"] == 1) {
			array_push ($params, "Want to Play");
		}

		if ($status["wanttobuy"] == 1) {
			array_push ($params, "Want to Buy");
		}

		if ($status["wishlist"] == 1) {
			array_push ($params, "Wishlist");
		}

		if ($status["preordered"] == 1) {
			array_push ($params, "Preordered");
		}
			
		$html .= "<td valign = \"top\"><a href = \"https://boardgamegeek.com/boardgame/$num/\" $ext>" . $entry->name . "</a></td>";
		if (get_option ("CollectionIncludeYear")) {
			$html .= "<td valign = \"top\">" . $entry->yearpublished. "</td>";
		}
		
		if (get_option ("CollectionIncludeStatus")) {
			$html .= "<td valign = \"top\">" . implode ($params, ", ") . "</td>";
		}
		
		if (get_option ("CollectionIncludeNumPlays")) {		
			$html .= "<td valign = \"top\">" . $entry->numplays. "</td>";
		}
		
		if (get_option ("CollectionIncludeComments")) {
			$html .= "<td valign = \"top\">" . $entry->comment. "</td>";
		}

		if (get_option ("CollectionIncludeRating")) {
//			$url = "http://imaginary-realities.com/bggapi/bgg.php?action=findgame&id=" . $num;
		
//			$contents = mlu_bgg_load_from_api ($url, "bgg", $num);

//			$xml2 = simplexml_load_string ($contents);

//			// If no XML can be parsed or there's no entry in the server, just output nothing.
//			if (!$xml2 || $xml2->Error) {
				$html .= "<td valign = \"top\">" . $entry->stats->rating["value"] . "</td>";
//			}
//			else {
//				$html .= "<td valign = \"top\"><a href = \"" . $xml2->Review . "\">" . $entry->stats->rating["value"] . "</a></td>";
//			}
			
		}		

		if (get_option ("CollectionIncludeUserRating")) {
			$html .= "<td valign = \"top\">" . number_format ((float)$entry->stats->rating->average["value"], 2) . "</td>";
		}
	
		if (get_option ("CollectionIncludeBggRank")) {
			$html .= "<td valign = \"top\">" . $entry->stats->rating->ranks->rank[0]["value"] . "</td>";
		}


		$html .= "</tr>";
		
	}
	$html .= "</tbody>";

	$html .= "</table>";
	$html .= "</div>";
	
	return $html;
}


function mlu_bgg_create_scotlight ($arr) {
	$url = "http://imaginary-realities.com/bggapi/scotlight.php";

	$contents = mlu_bgg_load_from_api ($url, "scotlight", "main");

	if ($contents == false) {	
		return "";
	}
	
	$xml = simplexml_load_string ($contents);


	// If no XML can be parsed or there's no entry in the server, just output nothing.
	if (!$xml || $xml->Error) {
		return "<p>Error: " . $xml->Error . "</p>";
	}
	
	$html = "<div class = \"mlu_scotlight_div\">";
	$html .= "<h1>The Scotlight</h1>";
	$html .= "<script type=\"text/javascript\" src = \"http://meeplelikeus.co.uk/meeple_tools.js\"></script>";
	$html .= "<input class = \"mlu_search_field\" type=\"text\" id=\"myInput\" onkeyup=\"searchTable('myInput', 'mlu_scotlight')\" placeholder=\"Search for names..\" />";
	$html .= "<table id = \"mlu_scotlight\">";
	$html .= "<thead>";
	$html .= "<tr>";
	$html .= "<th valign>Name</th>";
	$html .= "<th valign>Type</th>";
	$html .= "<th valign>Location</th>";
	$html .= "<th valign>Social Media</th>";
	$html .= "</thead>";
	$html .= "</tr>";
	
	
	
	$ext = mlu_bgg_get_external();	

	$html .= "<tbody class = \"list\">";
	
	foreach ($xml as $entry) {
		$html .= "<tr>";
		
		if (!empty ($entry->URL)) {
			$url = $entry->URL;
			$html .= "<td valign = \"top\"><a href = \"$url\" $ext>" . $entry->Name . "</a></td>";
		}
		else {
			$html .= "<td valign = \"top\">" . $entry->Name. "</td>";
		}

		$html .= "<td valign = \"top\">" . $entry->Type . "</td>";
		$html .= "<td valign = \"top\">" . $entry->Location. "</td>";

		$html .= "<td>";

		if (!empty ($entry->Twitter)) {			
			$html .= "<a href = \"" . $entry->Twitter. "\"><i class=\"fa fa-twitter aria-hidden=\"true\"></i></a> ";
				
		}
		if (!empty ($entry->Facebook)) {			
			$html .= "<a href = \"" . $entry->Facebook . "\"><i class=\"fa fa-facebook aria-hidden=\"true\"></i></a> ";				
		}
		
		$html .= "</td>";

		$html .= "</tr>";
		
	}

	$html .= "</tbody>";
		

	$html .= "</table>";
//	$html .= "<caption><a href = \"http://meeplelikeus.co.uk/the-scotlight/\">The Scotlight</a></caption>";
	$html .= "</div>";
	
	return $html;
}


function mlu_bgg_rating ($arr) {
	$desc = null;
	$rating = null;
	
	if (isset ($arr["id"])) {
		$id = $arr["id"];
	}

	if (isset ($arr["rating"])) {
		$rating= $arr ["rating"];	
	}
	else 	if (!$id) {
		return "";
	}

	if (isset ($arr["nodesc"])) {
		$nodesc = $arr ["nodesc"];	
	}
	
	if (isset ($arr["desc"])) {
		$desc = $arr ["desc"];	
	}

 		
	if (isset ($arr["size"])) {
		$size= $arr ["size"];	
	}
	else {
		$size = 300;
	}	

	if (isset ($arr["noempty"])) {
		$noempty = 1;
	}
	
	if (!$rating) {	
		$url = "http://imaginary-realities.com/bggapi/bgg.php?action=accessibility&id=$id";
	
		$contents = mlu_bgg_load_from_api ($url, "rating", "$id");
	
		if ($contents == false) {	
			return "";
		}
	
		$xml = simplexml_load_string ($contents);
	
		// If no XML can be parsed or there's no entry in the server, just output nothing.
		if (!$xml || $xml->Error) {
			return "<p>Error: " . $xml->Error . "</p>";
		}
		
		$stars= $xml->Rating;
	}
	else {
		$stars = $rating;
	}
	
	$fixed = (int)$stars;	
	$html = "<div class = \"mlu_rating\">";

	if (!$nodesc) {
		if ($desc) {
			$txt = $desc;
		}
		else {
			switch ($stars) {
		    case "5.0":
		    case "5":
		      $txt = get_option ("Rating5");
		    break;    
		    case "4.5":
		      $txt = get_option ("Rating45");
		    break;    
		    case "4.0":
		    case "4":
		      $txt = get_option ("Rating4");
		    break;    
		    case "3.5":
		      $txt = get_option ("Rating35");
		    break;    
		    case "3.0":
		    case "3":
		      $txt = get_option ("Rating3");
		    break;    
		    case "2.5":
		      $txt = get_option ("Rating25");
		    break;    
		    case "2.0":
		    case "2":
		      $txt = get_option ("Rating2");
		    break;    
		    case "1.5":
		      $txt = get_option ("Rating15");
		    break;    
		    case "1.0":
		    case "1":
		      $txt = get_option ("Rating1");
		    break;   
			}      
		}
	}
	
	$css = "style=\"text-shadow: 0px 0px 3px #000; font-size: $size%; color: gold;\"";
	$css2 = "style=\"text-shadow: 0px 0px 3px #000; font-size: $size%; color: white;\"";
	for ($i = 0; $i < $fixed; $i++) {
		$html .= "<span $css class=\"fa fa-star\"></span> ";
	}

	if ($stars > $fixed) {
		$html .= "<span $css class=\"fa fa-star-half\"></span>";
		
		if (!$noempty) {
			$html .= "<span $css2 class=\"fa fa-star-half fa-flip-horizontal\"></span>";
		}
		$fixed += 1;
	}	
	
	
	if (!$noempty) {
		while ($fixed < 5) {
			$html .= "<span $css2 class=\"fa fa-star\"></span> ";
			$fixed += 1;
		}
	}
	if (!$nodesc && get_option ("IncludeTLDR")) {
		$html .= "<p><h3><b><i>TL;DR: $txt</i></b></h3></p>";
	}
	$html .= "</div>";
	
	return $html;
}

function mlu_bgg_recommendations_table ($attr) {
	
	$cat = $attr["cat"];
	$val = "b";
	
	$url = "http://imaginary-realities.com/bggapi/recommend.php?$cat=$val&rating=3.5&limit=10";
	
	$contents = mlu_bgg_load_from_api ($url, "rating", "$id");
	
	if($contents == false) {	
		return "";
	}

	
	$xml = simplexml_load_string ($contents);

	// If no XML can be parsed or there's no entry in the server, just output nothing.
	if (!$xml || $xml->Error) {
		return "<p>Error: " . $xml->Error . "</p>";
	}
	
	return mlu_bgg_create_toc ($xml);
				
}

function mlu_bgg_game_idea ($arr) {
	$seed = null;
	
	if (!empty ($_GET["seed"])) {
		$seed = $_GET["seed"];
	}
			
	if ($seed) {
		$url = "http://imaginary-realities.com/bggapi/random_design.php?seed=$seed";
	}
	else {
		$url = "http://imaginary-realities.com/bggapi/random_design.php";
	}		
  
	$contents = mlu_bgg_load_from_api ($url, "gameidea", "$seed", true);

	if ($contents == false) {	
		return "";
	}
		
	$xml = simplexml_load_string ($contents);

	// If no XML can be parsed or there's no entry in the server, just output nothing.
	if (!$xml || $xml->Error) {
		return "<p>Error: " . $xml->Error . "</p>";
	}
		
	$html = "<div class = \"mlu_game_idea\">";
	$html .= "<h1>Your game idea...</h1>";
	$html .= "<br/>";

	$html .= "<table>";
	
	$html .= "<tr>";
	$html .= "<th>Share this link</th>";

	$url = "http://meeplelikeus.co.uk/board-game-idea-generator/?seed=" . $xml->seed;
	
	$html .= "<td>";
	$html .= "<p><a href = \"$url\">$url</a></p>";			
	$html .= "</td>";
	$html .= "</tr>";

	
	$cultures= $xml->Cultures;

	$html .= "<tr>";
	$html .= "<th>Dominant cultural inspiration</th>";

	foreach ($cultures->Culture as $cult) {	
		$html .= "<td>";
		$html .= $cult;			
		$html .= "</td>";
	}
	$html .= "</tr>";

	$mechanisms = $xml->Mechanisms;

	$html .= "<tr>";
	$html .= "<th>Using these Mechanisms</th>";
	$html .= "<td>";
	$html .= "<ul>";
	
	foreach ($mechanisms->Mechanic as $mech) {	
		$html .= "<li>$mech</li>";			
	}

	$html .= "<ul>";
	$html .= "</td>";

	$html .= "</tr>";

	$themes= $xml->Themes;

	$html .= "<tr>";
	$html .= "<th>Drawing from the following themes</th>";
	$html .= "<td>";
	$html .= "<ul>";
	
	foreach ($themes->Theme as $the) {	
		$html .= "<li>$the</li>";			
	}

	$html .= "<ul>";
	$html .= "</td>";

	$html .= "</tr>";

	$needs = $xml->AccessibilityNeeds;

	$html .= "<tr>";
	$html .= "<th>Addressing the following accessibility needs</th>";
	$html .= "<td>";
	$html .= "<ul>";
	
	foreach ($needs->AccessibilityNeed as $ne) {	
		$html .= "<li>$ne</li>";			
	}

	$html .= "<ul>";
	$html .= "</td>";

	$html .= "</tr>";

	$html .= "</table>";
	$html .= "</div>";
	
	return $html;
}


function mlu_bgg_embed_label($attr) {
	$html = "";
	
	if (isset ($attr["title"])) {
		$title = $attr["title"];
		unset ($attr["title"]);
	}
	else {
		$title = "Label";
	}
	
	if (isset ($attr["keys"])) {
		$keys = explode ($attr["keys"], ",");
	}
	else {
		$keys = array_keys ($attr);
	}

	$html .= "<div class = \"meeple_like_us_container\">";
	$html .= "<table cellpadding = \"0\" class = \"meeple_like_us_table\">";
	$html .= "<tr>";
	$html .= "<th style = \"text-align: center;\" colspan = \"2\">";
	$html .= "<a href = \"http://meeplelikeus.co.uk/meeple-like-us-plugin/\" $ext>$title</a>";
	$html .= "</th>";
		
	$html .= "</tr>";
		
	for ($i = 0; $i < sizeof ($keys); $i++) {
		$key = $keys[$i];
		$value = $attr[$key];
		
		$html= mlu_bgg_add_row($html, ucwords($keys[$i]), $value);
	}
	
	$html .= "</table>";
	$html .= "</div>";
	
	return $html;
}

function mlu_bgg_hob_coc ($attrs) {
	if (array_key_exists ("version", $attrs)) {
		$version = $attrs["version"];
	}
	else {
		$version = "1.0";
	}
	
	if (array_key_exists ("deminimis", $attrs)) {
		$deminimis = $attrs["deminimis"];
	}
	
	if (array_key_exists ("association", $attrs)) {
		$association= $attrs["association"];
	}	

	if (array_key_exists ("reviewcopypolicy", $attrs)) {
		$reviewcopy= $attrs["reviewcopypolicy"];
	}	

	
	$contents = mlu_bgg_load_from_api ("https://imaginary-realities.com/bggapi/hobcoc.php?version=$version", "version", "$version", true);

	if ($contents == false) {	
		return "";
	}
		
	$xml = simplexml_load_string ($contents);

	// If no XML can be parsed or there's no entry in the server, just output nothing.
	if (!$xml || $xml->Error) {
		return "<p>Error: " . $xml->Error . "</p>";
	}

		
	$html .= "<p><b>Code of Ethics</b></p>";
	$html .= "<div class = \"hob_coc\">";
	$html .= "<p>This site conforms with the <a href = \"". $xml->url . "\">Hobbyist Media Code of Ethics V" . $xml->version . "</a>.</p>";
	$html .= "<p>This version of the Hobbyist Media Code of Ethics is released under a <a href = \"". $xml->licenceurl . "\">" . $xml->licence . "</a> licence</p>";
	if ($deminimis) {
		$html .= "<p>Our De Minimis threshold is $deminimis.</p>";
	}
	else {
		$html .= "<p>Our De Minimis threshold is unset.</p>";
	}

	if ($association) {
		$html .= "<p>Writers and editors for this site are prohibited from covering products or services from ex-employers for a period of $association.</p>";
	}
	else {
		$html .= "<p>We have not yet set the time period of $association for writers covering the products of ex-employers.</p>";
	}
	
	if ($reviewcopy) {
		$html .= "<p>Our <a href = \"$reviewcopy\">policy for review copies can be found here</a>.</p>";
	}
	else {
		$html .= "<p>We have not yet set the location of our review copy policy.</p>";
	}


	$html .= "</div>";
	
	return $html;


	
}

add_shortcode ("bgg", "mlu_bgg_embed_bgg");
add_shortcode ("mlu_label", "mlu_bgg_embed_label");
add_shortcode ("bgg_rating", "mlu_bgg_embed_bgg_rating");
add_shortcode ("bgg_complexity", "mlu_bgg_embed_bgg_complexity");
add_shortcode ("bgg_image", "mlu_bgg_embed_bgg_image");
add_shortcode ("bgg_rank", "mlu_bgg_embed_bgg_rank");
add_shortcode ("bgg_weight", "mlu_bgg_embed_bgg_weight");
add_shortcode ("bgg_description", "mlu_bgg_embed_bgg_description");
add_shortcode ("mlu_table", "mlu_bgg_embed_mlu_table");
add_shortcode ("mlu_radar", "mlu_bgg_create_radar");
add_shortcode ("mlu_bar", "mlu_bgg_create_bar");
add_shortcode ("mlu_toc", "mlu_bgg_create_toc");
add_shortcode ("mlu_master", "mlu_bgg_create_masterlist");
add_shortcode ("bgg_collection", "mlu_bgg_create_collection");
add_shortcode ("mlu_scotlight", "mlu_bgg_create_scotlight");
add_shortcode ("mlu_stats_coverage", "mlu_bgg_calculate_coverage");
add_shortcode ("mlu_stats_publisher", "mlu_bgg_calculate_publisher_scorecard");
add_shortcode ("mlu_stats_all_publishers", "mlu_bgg_calculate_all_scorecards");
add_shortcode ("mlu_rating", "mlu_bgg_rating");
add_shortcode ("mlu_recommendations", "mlu_bgg_recommendations_table");
add_shortcode ("mlu_game_idea", "mlu_bgg_game_idea");
add_shortcode ("hob_coc", "mlu_bgg_hob_coc");
?>
<?php
set_include_path ( get_include_path () . PATH_SEPARATOR . dirname ( __FILE__ ) );
date_default_timezone_set ( 'America/New_York' );

$PATH = "log/stats/";
$FIELD_WEIGHTS = array(
		"RECEIVED_FULL_REQUEST" => 10,
		"RECEIVED_TORRENT_REQUEST" => 10,
		"RECEIVED_CHUNK_REQUEST" => 10,
		"LAST_NUM_PORTS_DISCOVERED" => 2.5,
		"LAST_NUM_PORTS_USED" => 2.5,
		"RECEIVED_IAM_REQUEST" => 0,
		"RECEIVED_CHALLENGE_RESPONSE" => 5,
		"BEST_DOWNLOAD_DELAY" => 50,
		"LAST_DOWNLOAD_DELAY" => 20,
);

class StatsReader {
	private $filename = null;
	function __construct($id) {
		$this->filename = $id;
	}
	// reads a single json stat from the file
	function readStats() {
		$json = $this->fixInitTime ( json_decode ( file_get_contents ( $this->filename ) ) );
		$this->computeGrade($json);
		return $json;
	}
	// changes init time from long to date format
	function fixInitTime($json) {
		foreach ( $json->{"FILE_STATS"} as $stat ) {
			$stat->{"INIT_TIME"} = date ( "m-d-y H:i:s", $stat->{"INIT_TIME"} / 1000 );
		}
		return $json;
	}	
	// compute overall performance score based on json stats record
	static function computeGrade($json) {
		global $FIELD_WEIGHTS;
		$max_delay = 120;
		$min_delay = 12;
		$score = 0;
		$exp = 1;
		//var_dump($json);
		foreach ( $json->{"FILE_STATS"} as $stat ) {
			foreach($stat as $key => $val) {
				if(!array_key_exists($key, $FIELD_WEIGHTS) || $val==false) continue;
				foreach($FIELD_WEIGHTS as $field => $weight) {
					if($key==$field) {
						if($val == 1) {
							$score += $weight;
							//echo "incremented score by " . $weight . " for " . $key . " => " . $val."\n";
						}
      						if($val > 1 && $key!="BEST_DOWNLOAD_DELAY" && $key!="LAST_DOWNLOAD_DELAY") {
							$incr = min($val, $weight);
							$score += $incr;
							//echo "incremented score by " . $incr . " for " . $key . " => " . $val."\n";
						}
						if($key=="BEST_DOWNLOAD_DELAY" && $val > 0) {
							$incr = ($weight * pow((1-min(max($val-$min_delay,0), $max_delay)/$max_delay), $exp));
							$score += $incr;
							//echo "incremented score by " . $incr . " for " . $key . " => " . $val."\n";
						}
						if ($key=="LAST_DOWNLOAD_DELAY" && $val > 0) {
							$incr = ($weight * pow((1-min(max($val-$min_delay,0), $max_delay)/$max_delay), $exp));
							$score += $incr;
							//echo "incremented score by " . $incr . " for " . $key . " => " . $val."\n";
						}
					}
				}
			}
		}
		$json->{'SCORE'} = number_format($score, 1);
		//echo "-----total score = ". $json->{'SCORE'}."--------\n\n";
	}
}

class HtmlStats {
	// returns an array of filenames in the directory $path
	static function getFiles($path) {
		$n=0;
		$handle = opendir($path);
		while(($entry = readdir($handle)) != null) {
			if(preg_match('/\d+/', $entry)!=1) continue;
			$filenames[$n++] = $path . $entry;
		}
		return $filenames;
	}
	// returns json stats converted to an html row for $testfile 
	static function makeHtmlRow($json, $testfile = "redsox.jpg") {
		$row="";
		foreach ( $json->{"FILE_STATS"} as $stat ) {
			if($stat->FILENAME!=$testfile) continue;
			$row = $row . "  <tr>\n";
			$row = $row . "    <td>"."****".substr($json->TESTING_ID,-4)."</td>\n";
			foreach($stat as $key => $value) {
				$row = $row . "    <td>".$key."=".($value==false ? "false" : 
						($value=="true" ? "true" : $value))."</td>\n";
			}
			$row = $row . "    <td>".$json->SCORE."</td>\n";
			$row = $row . "    <td>".substr($json->TESTING_ID,-4)."</td>\n";
			$row = $row . "  </tr>";
		}
		return $row;
	}
	// returns an html table given rows
	static function makeHtmlTableFromRows($rows) {
		$table = '<table border="2">'."\n";
		foreach($rows as $row) {
			$table = $table . $row."\n";
		} 
		$table = $table . '</table>'."\n";
		return $table;
	}
	// returns html rows given an array of filenames
	static function getHtmlRows($filenames) {	
		$n=0;	
		foreach($filenames as $filename) {
			$statsReader = new StatsReader($filename);
			$row = HtmlStats::makeHtmlRow($statsReader->readStats());
			$rows[$n++] = $row;
		}
		return $rows;
	}
	// returns an html stats table given the directory path
	static function makeHtmlStatsTable($dirpath) {
		$filenames = HtmlStats::getFiles($dirpath); // read all file names
		$rows = HtmlStats::getHtmlRows($filenames); // get each file's contents as an html row
		$table = HtmlStats::makeHtmlTableFromRows($rows); // make a table from the rows above
		return $table;
	}
}

echo HtmlStats::makeHtmlStatsTable($PATH);

?>

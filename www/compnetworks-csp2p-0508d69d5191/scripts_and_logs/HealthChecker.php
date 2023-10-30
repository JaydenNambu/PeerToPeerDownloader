#!/usr/bin/php
<?php
date_default_timezone_set ( 'America/New_York' );

$tcpPort = 18765;
$udpPort = 19876;
$hosts = array (
		"plum.cs.umass.edu",
		"pear.cs.umass.edu",
		"date.cs.umass.edu"
		//"localhost"	
);
$filename = "health.html";
$nohup_filename = "nohup_hc.out";

// returns a socket connected to host and port
function connect($host, $port) {
	if (($sock = socket_create ( AF_INET, SOCK_STREAM, SOL_TCP )) === false) {
		echo "socket_create() failed: reason: " . socket_strerror ( socket_last_error () ) . "\n";
	}
	
	$result = socket_connect ( $sock, gethostbyname ( $host ), $port );
	if ($result === false) {
		echo "socket_connect() failed.\nReason: ($result) " . socket_strerror ( socket_last_error ( $sock ) ) . "\n";
		$sock = false;
	} else {
		echo "[connected to " . $host . ":" . $port . "]\n";
	}
	return $sock;
}
function getUDPSocket($host, $port) {
	if (($sock = socket_create ( AF_INET, SOCK_DGRAM, SOL_UDP )) === false) {
		echo "socket_create() failed: reason: " . socket_strerror ( socket_last_error () ) . "\n";
	}
	if (! socket_bind ( $sock, "0.0.0.0" )) {
		echo "socket_bind() failed.\nReason: ($result) " . socket_strerror ( socket_last_error ( $sock ) ) . "\n";
	} else {
		echo "[UDP socket created]\n";
	}
	return $sock;
}

// reads a single line. Using the default socket_read to read a line in PHP is buggy.
function read_line($sock) {
	$line = false;
	while ( socket_recv ( $sock, $char, 1, MSG_WAITALL ) == 1 ) {
		$line = $line . $char;
		if ($char == "\n")
			break;
	}
	return $line;
}

// read header lines (until an empty line)
function readHeader($sock) {
	$lines = false;
	$count = 0;
	do {
		$line = read_line ( $sock );
		if (! $line) {
			break;
		}
		$lines [$count ++] = $line;
	} while ( $line != "\n" );
	return ($lines);
}

// extracts the size of the body from the header
function readBodySize($header) {
	foreach ( $header as $line ) {
		if (strpos ( $line, "BODY_BYTE_LENGTH" ) !== false) {
			$tokens = preg_split ( "/:/", $line );
			$size = str_replace ( "\n", "", $tokens [1] );
			break;
		}
	}
	return $size;
}
function readBody($sock, $size) {
	if ($size == 0)
		return false;
	$numRead = socket_recv ( $sock, $read, $size, MSG_WAITALL );
	return $read;
}
function testTCPCommand($sock, $command) {
	// send command
	if (! socket_write ( $sock, $command )) {
		echo "!!!!!!!!!!!!!!!!!Error: " . socket_strerror ( socket_last_error ( $sock ) );
		return false;
	}
	// read header
	$header = readHeader ( $sock );
	if (! $header) {
		echo "!!!!!!!!!!!!!!!!Error: " . socket_strerror ( socket_last_error ( $sock ) );
		return false;
	}
	
	$body = false;
	if (strpos ( $command, "GETHDR" ) === false) { // header only
	                                               // read size of body
		$size = readBodySize ( $header );
		// read body
		$body = readBody ( $sock, $size );
	}
	if (! $body)
		$body = "[header only, no body]";
	else
		$body = substr ( $body, 0, 10 ) . "...[truncated received body of size " . $size . "]";
	$headerString = false;
	foreach ( $header as $line )
		$headerString .= $line;
	return $headerString . $body;
}
function testUDPCommand($sock, $host, $port, $udpCommand) {
	if (! socket_sendto ( $sock, $udpCommand, strlen ( $udpCommand ), 0, $host, $port )) {
		echo "!!!!!!!!!!!!!!!!!Error: " . socket_strerror ( socket_last_error ( $sock ) );
		return false;
	}
	socket_recv ( $sock, $response, 256, MSG_WAITALL );
	return $response;
}
function testServers($hosts, $tcpPort, $tcpCommands, $udpPort, $udpCommands) {
	foreach ( $hosts as $host ) {
		echo "\n------------Initiating test for " . $host . "--------------\n";
		$tcpSock = connect ( $host, $tcpPort );
		if (! $tcpSock) {
			echo "\n!!!!!!!!!!!!!!!!!!!! " . $host . ":" . $tcpPort . "!!!!!!!!!!!!!!!!!!\n";
			continue;
		}
		foreach ( $tcpCommands as $cmd ) {
			echo "\n" . date ( "D, d M Y H:i:s" ) . ": Testing " . $host . ":" . $tcpPort . " " . $cmd;
			echo testTCPCommand ( $tcpSock, $cmd ) . "\n";
			echo "\n--------------------------\n";
		}
		$udpSock = getUDPSocket ( $host, $udpPort );
		if (! $udpSock) {
			echo "\n!!!!!!!!!!!!!!!!!!!! " . $host . ":" . $udpPort . "!!!!!!!!!!!!!!!!!!\n";
			continue;
		}
		foreach ( $udpCommands as $cmd ) {
			echo "\n" . date ( "D, d M Y H:i:s" ) . ": Testing " . $host . ":" . $udpPort . " " . $cmd;
			$msg = testUDPCommand ( $udpSock, $host, $udpPort, $cmd ) . "\n";
			echo $msg;
			preg_match('/PORT1:.*/', $msg, $matches);
			$port = preg_replace('/PORT1:\s+/', '', $matches[0]);
			echo testTCPCommand ( $tcpSock1=connect ( $host, $port ), $tcpCommands[0] ) . "\n";
			echo "\n" . date ( "D, d M Y H:i:s" ) . "\n--------------------------\n";
			sleep ( 2 );
		}
		
		echo "\n------------Completed test for " . $host . "--------------\n";
		socket_close ( $tcpSock );
		socket_close ( $tcpSock1 );
		socket_close ( $udpSock );
	}
}
$tcpCommands = array (
		"GETHDR redsox.jpg\n",
		"GET redsox.jpg:*\n",
		"GET redsox.jpg:3\n",
		"GET test.jpg\n" 
);

$udpCommands = array (
		"GET redsox.jpg.torrent\n",
		//"GET test.jpg.torrent\n" 
);

while ( true ) {
	testServers ( $hosts, $tcpPort, $tcpCommands, $udpPort, $udpCommands );
	shell_exec ( "tail -n 182 " . $nohup_filename . ' | sed s/$/"<br>"/g > ' . $filename );
	sleep ( 60 );
}
?>


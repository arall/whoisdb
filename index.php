<?php

include("lib/database.php");

//DB
$db = new Database("localhost", "root", "", "whoisdb");

//Get the last record from DB
$currentIp = "";
$query = "SELECT to FROM ranges ORDER BY id DESC LIMIT 1";
if($db->Query($query)){
	if($db->getNumRows()){
		$row = $db->loadArray();
		$currentIp = long2ip($row['to']);
	}
}
//Start from the beginning
if(!$currentIp){
	$currentIp = "1.1.1.1";
}

//Start
whois($currentIp);

function whois($ip){
	global $db;
	echo " [+] Grabbing IP ".$ip."...";
	//Whois
	$result = shell_exec("whois ".$ip);
	$netname = trim(get_between($result, "netname:", "\n"));
	$descr = trim(get_between($result, "descr:", "\n"));
	$inetnum = trim(get_between($result, "inetnum:", "\n"));
	if($inetnum){
		echo $inetnum."\n";
		//Parsing data
		$range = explode(" - ", $inetnum);
		$from = ip2long($range[0]);
		$to = ip2long($range[1]);
		//Insert record
		$query = "INSERT INTO ranges (`from`, `to`, `netname`, `descr`, `date`) VALUES 
		('".$from."', '".$to."', 
		'".mysql_real_escape_string($netname)."', 
		'".mysql_real_escape_string($descr)."', NOW());";
		$db->query($query);
		$nextIp = long2ip($to+1);
	}else{
		echo "Rang not found!\n";
		$nextIp = long2ip(ip2long($ip+1));
	}
	whois($nextIp);
}

function get_between($string,$start,$end){
	$string = " ".$string;
	$ini = strpos($string, $start);
	if($ini==0) return "";
	$ini += strlen($start);
	$len = strpos($string, $end, $ini) - $ini;
	return substr($string, $ini, $len);
}

?>
<?php

include("lib/database.php");

//Delay after IP banned (in seconds)
define("BAN_SLEEP", 70);

//DB
$db = new Database("localhost", "root", "", "whoisdb");

//Get the last record from DB
$currentIp = "";
$query = "SELECT `to` FROM ranges ORDER BY `to` DESC LIMIT 1";
if($db->Query($query)){
	if($db->getNumRows()){
		$row = $db->fetcharray();
		$currentIp = long2ip($row['to']+1);
	}
}
//Start from the beginning
if(!$currentIp){
	$currentIp = "1.0.0.0";
}

//Start
whois($currentIp);

function whois($ip){
	global $db;
	//Reserved
	$ipn = ip2long($ip);
	if($ipn>=ip2long("10.0.0.0") && $ipn<=ip2long("10.255.255.255")) $ip = "11.0.0.0";
	if($ipn>=ip2long("100.64.0.0") && $ipn<=ip2long("100.127.255.255")) $ip = "100.128.0.0";
	if($ipn>=ip2long("127.0.0.0") && $ipn<=ip2long("127.255.255.255")) $ip = "128.0.0.0";
	if($ipn>=ip2long("169.254.0.0") && $ipn<=ip2long("169.254.254.255")) $ip = "169.254.255.0";
	if($ipn>=ip2long("172.16.0.0") && $ipn<=ip2long("172.31.255.255")) $ip = "172.32.0.0";
	if($ipn>=ip2long("192.0.0.0") && $ipn<=ip2long("192.0.0.7")) $ip = "192.0.0.8";
	if($ipn>=ip2long("192.0.2.0") && $ipn<=ip2long("192.0.2.255")) $ip = "192.0.3.0";
	if($ipn>=ip2long("192.88.99.0") && $ipn<=ip2long("192.88.99.255")) $ip = "192.88.100.0";
	if($ipn>=ip2long("192.168.0.0") && $ipn<=ip2long("192.168.255.255")) $ip = "192.169.0.0";
	if($ipn>=ip2long("198.18.0.0") && $ipn<=ip2long("198.19.255.255")) $ip = "198.20.0.0";
	if($ipn>=ip2long("198.51.100.0") && $ipn<=ip2long("198.51.100.255")) $ip = "198.51.101.0";
	if($ipn>=ip2long("203.0.113.0") && $ipn<=ip2long("203.0.113.255")) $ip = "203.0.114.0";
	if($ipn>=ip2long("224.0.0.0") && $ipn<=ip2long("239.255.255.255")) $ip = "234.0.0.0";
	if($ipn>=ip2long("240.0.0.0")) die("\nFinished!\n");
	//Crazy IP's that have crazy results...
	if($ip=="129.0.0.0") $ip = "130.0.0.0";
	if($ip=="137.63.0.0") $ip = "138.0.0.0";
	if($ip=="155.0.0.0") $ip = "156.0.0.0";
	if($ip=="132.220.0.0") $ip = "133.0.0.0";
	if($ip=="156.0.0.0") $ip = "157.0.0.0";
	if($ip=="160.0.0.0") $ip = "161.0.0.0";
	if($ip=="165.0.0.0") $ip = "166.0.0.0";
	if($ip=="169.0.0.0") $ip = "170.0.0.0";
	if($ip=="179.0.29.1") $ip = "180.0.0.0";
	if($ip=="181.39.0.1") $ip = "182.0.0.0";
	if($ip=="186.0.144.1") $ip = "186.1.0.0";
	//All fine here
	echo " [+] Grabbing IP ".$ip."\t";
	//Whois
	do{
		$result = shell_exec("whois ".$ip);
	}while(!$result);
	//Got result?
	if($result){
		//Banned
		if(strstr($result, "access denied for") || strstr($result, "Query rate limit exceeded")){
			echo "IP Banned!\n Sleeping for ".BAN_SLEEP." seconds...\n";
			sleep(BAN_SLEEP);
			whois($ip);
		//Record not found
		}elseif(strstr($result, "No match") || strstr($result, "Cannot currently process")){
			echo "No match found\n";
			$nextIp = long2ip(ip2long($ip)+65536);
			//Omiting...
			whois($nextIp);
		//Else
		}else{
			$netname = trim(get_between($result, "netname:", "\n"));
			$descr = trim(get_between($result, "descr:", "\n"));
			$inetnum = trim(get_between($result, "inetnum:", "\n"));
			//Strange Whois
			if(!$inetnum){
				//NetRange Fix
				if(strstr($result, "NetRange:")){
					$inetnum = trim(get_between($result, "NetRange:", "\n"));
				//Fucking Korea wierd output
				}elseif(strstr($result, "IPv4 Address")){
					$inetnum = trim(get_between($result, "IPv4 Address       : ", " ("));
				}
			}
		}
		//XXX.XXX/16
		if(strstr($inetnum, "/")){
			$tmp = explode("/", $inetnum);
			$i = str_replace("/".$tmp[1], str_repeat(".0", (3-(int)substr_count($tmp[0], "."))), $inetnum);
			$to = long2ip(ip2long($i)+pow(2,32-$tmp[1]));
			$inetnum = $i." - ".$to;
		}
		//Have InetNum?
		if($inetnum){
			//Parsing data
			$range = explode(" - ", $inetnum);
			echo trim($range[0])." - ".trim($range[1])."\t\t".$netname;
			$from = ip2long(trim($range[0]));
			$to = ip2long(trim($range[1]));
			$nextIp = long2ip($to+1);
			//Have From - To?
			if($from && $to){
				if($from>$to){
					die("Something wierd happend. You better check the log!");
				}
				//Insert record
				$res = insertRecord($from, $to, $netname, $descr);
				if($res) $res = "OK!"; else $res = ":(";
				echo "\t".$res."\n";
			//Don't have From - To
			}else{
				die("Strange Inetnum: ".$inetnum."\n");
			}
		//No inetnum
		}else{
			die("Strange whois record!\n".$result);
		}
		//Have nextIp?
		if($nextIp){
			whois($nextIp);
		}else{
			die("Dont have next IP\n");
		}
	//No result
	}else{
		die("Empty result!\n");
	}
}

function insertRecord($from, $to, $netname, $descr){
	global $db;
	$query = "INSERT INTO ranges (`from`, `to`, `netname`, `descr`, `date`) VALUES 
	('".$from."', '".$to."', 
	'".mysql_real_escape_string($netname)."', 
	'".mysql_real_escape_string($descr)."', NOW());";
	return $db->query($query);
}

function get_between($string,$start,$end){
	$string = " ".$string;
	$ini = strpos(strtoupper($string), strtoupper($start));
	if($ini==0) return "";
	$ini += strlen($start);
	$len = strpos(strtoupper($string), strtoupper($end), $ini) - $ini;
	return substr($string, $ini, $len);
}

?>

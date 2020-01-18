#!/usr/bin/php
<?php
//General Settings
$moxa_ip = "192.168.1.xx"; //MoxaBox_TCP_Server
$moxa_port = 20108; //Infini 1
//$moxa_port = 21109; //Infini 2

$moxa_timeout = 10;
$debug = 0;

// Get model,version and protocolID for infini_startup.php
$fp = @fsockopen($moxa_ip, $moxa_port, $errno, $errstr, $moxa_timeout);
//echo "Fehler $errno beim Verbindungsaufbau, $errstr \n";
fwrite($fp, "QMD".chr(0x0d)); //Device Model Inquiry 48byte
$byte=fgets($fp,51);
if(dechex(ord(substr($byte,0,1)))!="28")
{
	if($debug) echo "Problem rcv at START!!!\n";
	exit;
}
$model=substr($byte,1,46);
fwrite($fp, "QVFW".chr(0x0d)); //Device Model Inquiry 16Byte
$byte = fgets($fp,19);
$version = substr($byte,1,14);
echo "VERSION of INVERTER: $version\n";

//get date+time and set current time from server
fwrite($fp, "QT".chr(0x0d)); //QT Time inquiry 16byte
$byte=fgets($fp,19);
echo "Actual time of Inverter: ".substr($byte,1,14)."\n";
fclose($fp);

//Setup connection to Serial2ETH converter
$fp = @fsockopen($moxa_ip, $moxa_port, $errno, $errstr, $moxa_timeout);
if (!$fp)
	{
	echo "Error on connection setup: $errstr ($errno)\n";
	exit;
}
fwrite($fp, "QPIGS".chr(0x0d)); //QPIGS Device general status parameters inquiry 135 --desises
$byte=fgets($fp,137); //137
//if($debug) echo "QPIGS Antwort: $byte.\n";
if($byte === FALSE)     //Fehler beim Empfang -> z.B. Verbindung abgebrochen!
	{
	echo "Error: Receive of Byte $index: ".bin2hex($byte)."\n";
	exit;
	}
$battvolt = substr($byte,65,5);
echo "BATTERY VOLTAGE:".$battvolt." VOLT\n\n";

while(true)
	{
	//Main
	echo "Increse (+) battery voltage or decrease (-)";
	$handle = fopen ("php://stdin","r");
	$line = fgets($handle);

	if(trim($line) == '+'){
		$voltchange = "PLUS";
	} elseif (trim($line) == '-')
		{
		$voltchange = "MINUS";
        }
    	else {
		echo "Illegal input!\n\n";
		continue;
	}

	fwrite($fp, "OEEPB".chr(0x0d)); //OEEPB Battery calibration mode
	$byte=fgets($fp,8);
	if($debug) echo "OEEPB Reply: $byte\n";
	if(substr($byte,1,3)=="ACK"){
		if($debug) echo "Entered battery calibration mode!\n";
	}
	sleep(1);
	if($voltchange=="PLUS"){
		fwrite($fp, "BTVA+01".chr(0x0d)); //BTVA+01 Increase battery volation by 25mV
		$byte=fgets($fp,8);
		if($debug) echo "BTVA+01 Antwort: $byte\n";
		if(substr($byte,1,3)=="ACK")
			{
			echo "Voltage increased by 25mV\n";
		} else {
			echo "Voltage increase failed!\n";
		}
	}
        if($voltchange=="MINUS"){
		fwrite($fp, "BTVA-01".chr(0x0d)); //BTVA-01 decrease battery volation by 25mV
                $byte=fgets($fp,8);
                if($debug) echo "BTVA-01 Reply: $byte\n";
                if(substr($byte,1,3)=="ACK")
                        {
                        echo "Voltage decrease by 25mV\n";
		} else {
			echo "Voltage decrease failed!\n";
		}
	}

	sleep(1);
	fwrite($fp, "QPIGS".chr(0x0d)); //QPIGS Device general status parameters inquiry 135 --desises
	$byte=fgets($fp,137); //137
	if($debug) echo "QPIGS Reply: $byte.\n";
	if($byte === FALSE)
	{
		echo "Error at receive of byte $index: ".bin2hex($byte)."\n";
		exit;
	}
	$battvolt = substr($byte,65,5);
	echo "BATTERY VOLTAGE:".$battvolt." V\n\n";
}

// Help function
function hex2str($hex) {
    $str = '';
    for($i=0;$i<strlen($hex);$i+=2) $str .= chr(hexdec(substr($hex,$i,2)));
    return $str;
}
?>

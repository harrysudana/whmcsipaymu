<?php
/*
 * This is WHMCS module using IPAYMU gateway   
 * Author : Harry Sudana
 * URL : http://github.com/harrysudana/whmcsipaymu
 * Release Date: 2012.12.20
 * License : http://www.gnu.org/licenses/gpl.html
 */

/*
 * WHMCS - The Complete Client Management, Billing & Support Solution
 * Copyright (c) WHMCS Ltd. All Rights Reserved,
 * Email: info@whmcs.com
 * Website: http://www.whmcs.com
 */

/*
 * IPAYMU - Indonesian Payment Gateway
 * Website: https://ipaymu.com
 */

# Required File Includes
include("../../../dbconnect.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewaymodule = "ipaymu";

$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

$systemURL = ($CONFIG['SystemSSLURL'] ? $CONFIG['SystemSSLURL'] : $CONFIG['SystemURL']);

if($_GET['method']=="cancel"){
	$invoiceid = $_GET["id"];
	$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing
	header('HTTP/1.1 400 Bad Request');
	logTransaction($GATEWAY["name"],$_GET,__LINE__.":Cancelled"); # Save to Gateway Log: name, data array, status
	header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
	exit(__LINE__.': Transaksi dibatalkan');
}elseif($_GET['method']=="notify" || $_GET['method']=="return" ){
	if(isset($_POST['paypal_trx_id'])){
		$rate = $_POST["total"] / $_POST['paypal_trx_total'];
		//$status = $_POST["x_response_code"];
		$invoiceid = $_POST["product"];
		$transid = $_POST["paypal_trx_id"];
		$amount = $_POST["total"];
		$fee = $_POST["paypal_trx_fee"] * $rate;

	}else{
		//$status = $_POST["x_response_code"];
		$invoiceid = $_POST["product"];
		$transid = $_POST["trx_id"];
		$amount = $_POST["total"];
		$fee = 0;//$_POST["x_fee"];

	}
	
	$invoiceid = $_GET["id"];
	$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing
	checkCbTransID($transid); # Checks transaction number isn't already in the database and ends processing if it does

	if ($transid<>"") {
		$ipaymutrx = ipaymu_cektransaksi($params, $transid);
		print_r($ipaymutrx);exit();
		if(!$ipaymutrx){
			header('HTTP/1.1 200 OK');
			header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
			exit(__LINE__.': Curl Error!');	
		}elseif($ipaymutrx['status']<-1){
			logTransaction($GATEWAY["name"],array('return'=>$_POST, 'ipaymu'=>$ipaymutrx),__LINE__.":Invalid IPAYMU Transaksi"); # Save to Gateway Log: name, data array, status
			header('HTTP/1.1 200 OK');
			header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
			exit(__LINE__.': Tidak menemukan transaksi di IPAYMU');
		}elseif($ipaymutrx['status']==1){
			# Successful
			addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
			logTransaction($GATEWAY["name"],array('return'=>$_POST, 'ipaymu'=>$ipaymutrx), __LINE__.":Successful"); # Save to Gateway Log: name, data array, status
			header('HTTP/1.1 200 OK');
			header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
			exit(__LINE__.': Successful');
		}else{
			logTransaction($GATEWAY["name"],array('return'=>$_POST, 'ipaymu'=>$ipaymutrx), __LINE__.":Successful with payment pending"); # Save to Gateway Log: name, data array, status
			header('HTTP/1.1 200 OK');
			header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
			exit(__LINE__.":Successful with payment pending");
		}
	} else {
		header('HTTP/1.1 400 Bad Request');
		# Unsuccessful
		logTransaction($GATEWAY["name"],$_POST,__LINE__.":Unsuccessful"); # Save to Gateway Log: name, data array, status
		header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
		exit(__LINE__.': Tidak menemukan transaksi');
	}

}else{
	$invoiceid = $_GET["id"];
	header('HTTP/1.1 400 Bad Request');
	logTransaction($GATEWAY["name"],$_GET,__LINE__.":Returned");
	header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
	exit(__LINE__.': Transaksi dikembalikan');
}

?>

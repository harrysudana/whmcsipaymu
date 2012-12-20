<?php
/*
 * This is WHMCS module using IPAYMU payment gateway   
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

function ipaymu_config() {
	$configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"IPAYMU Module"),
     "ipaymu_username" => array("FriendlyName" => "Login ID", "Type" => "text", "Size" => "50", ),
"ipaymu_apikey" => array("FriendlyName" => "API Key", "Type" => "text", "Size" => "50", ),
"paypal_enabled" => array("FriendlyName" => "Module Paypal", "Type" => "yesno", "Description" => "Pilih untuk aktifkan. Anda harus aktifkan juga modul paypal di IPAYMU", ),
"paypal_email" => array("FriendlyName" => "Paypal Email", "Type" => "text", "Size" => "20", ),
"paypal_curconvert" => array("FriendlyName" => "Convertion rate 1USD = ? IDR", "Type" => "text", "Size" => "20", ),
	/*"transmethod" => array("FriendlyName" => "Transaction Method", "Type" => "dropdown", "Options" => "Option1,Value2,Method3", ),
	 "instructions" => array("FriendlyName" => "Payment Instructions", "Type" => "textarea", "Rows" => "5", "Description" => "Do this then do that etc...", ),
	 "testmode" => array("FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Tick this to test", ),*/
	);
	return $configarray;
}

function ipaymu_link($params) {
	# Gateway Specific Variables
	$gatewayipaymuusername = $params['ipaymu_username'];
	$gatewayipaymuapikey = $params['ipaymu_apikey'];
	$gatewaypaypalenabled = $params['paypal_enabled'];
	$gatewaypaypalemail = $params['paypal_email'];
	$gatewaypaypalcurconvert = $params['paypal_curconvert'];

	# Invoice Variables
	$invoiceid = $params['invoiceid'];
	$description = $params["description"];
	$amount = $params['amount']; # Format: ##.##
	$currency = $params['currency']; # Currency Code

	# Client Variables
	$firstname = $params['clientdetails']['firstname'];
	$lastname = $params['clientdetails']['lastname'];
	$email = $params['clientdetails']['email'];
	$address1 = $params['clientdetails']['address1'];
	$address2 = $params['clientdetails']['address2'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = $params['clientdetails']['postcode'];
	$country = $params['clientdetails']['country'];
	$phone = $params['clientdetails']['phonenumber'];

	# System Variables
	$companyname = $params['companyname'];
	$systemurl = $params['systemurl'];
	$currency = $params['currency'];

	# Enter your code submit to the gateway...
	$data = array(
		'api_key'=>$gatewayipaymuapikey,
		'product'=>'INVOICE #'.$invoiceid,
		'price'=>$amount,
		'comments'=>$description,
		'url_return'=>$systemurl.'/modules/gateways/callback/ipaymu.php?method=return&id='.$invoiceid,
		'url_notify'=>$systemurl.'/modules/gateways/callback/ipaymu.php?method=notify&id='.$invoiceid.'&apikey='.$gatewayipaymuapikey,
		'url_cancel'=>$systemurl.'/modules/gateways/callback/ipaymu.php?method=cancel&id='.$invoiceid,
		'paypal_enabled'=>$gatewaypaypalenabled,
		'paypal_email'=>$gatewaypaypalemail,
		'price_usd'=>$amount/$gatewaypaypalcurconvert,
		'invoice_id'=>$invoiceid,
	);
	$result = ipaymu_generateurl($data);
	if($result['status']==TRUE){
		if($gatewaypaypalenabled)
		$code = "<a href='".$result["rawdata"]."'><img src='https://my.ipaymu.com/images/buttons/shopcart/01.png' alt='Bayar Sekarang' title='Bayar Sekarang' ></a>";
		else
		$code = "<a href='".$result["rawdata"]."'><img src='https://my.ipaymu.com/images/buttons/shopcart/02.png' alt='Bayar Sekarang' title='Bayar Sekarang' ></a>";
	}else{
		$code = "<p>".$result['rawdata']."</p>";
	}
	
	return $code;
}


function ipaymu_generateurl($data){
	// URL Payment IPAYMU
	$url = 'https://my.ipaymu.com/payment.htm';

	// Prepare Parameters
	$parameters = array(
            'key'      => $data['api_key'], // API Key Merchant / Penjual
            'action'   => 'payment',
            'product'  => $data['product'],
            'price'    => $data['price'], // Total Harga
            'quantity' => 1,
            'comments' => $data['comments'], // Optional           
            'ureturn'  => $data['url_return'],
            'unotify'  => $data['url_notify'],
            'ucancel'  => $data['url_cancel'],
            'format'   => 'json' // Format: xml / json. Default: xml 
	);

	/* Jika menggunakan Opsi Paypal
	 * ----------------------------------------------- */
	if($data['paypal_enabled']){
		$parameters = array_merge($parameters, array(
            'paypal_email'   => $data['paypal_email'],
            'paypal_price'   => number_format($data['price_usd'], 2, ',',''), // Total harga dalam kurs USD
            'invoice_number' => $data['invoice_id'], // Optional
		));
	}
	/* ----------------------------------------------- */
//print_r($parameters);
	$request = ipaymu_curl($url, $parameters);

	if($request['status']){
		$result = json_decode($request['rawdata'], true);
		if( isset($result['url']) )
			return array('status'=>TRUE, 'rawdata'=>$result['url']);
		else
			return array('status'=>FALSE, 'rawdata'=>"Request Error ". $result['Status'] .": ". $result['Keterangan']);
	}else{
		return array('status'=>FALSE, 'rawdata'=>$request['rawdata']);
	}

}

function ipaymu_cektransaksi($params, $trx_id){
	$gatewayipaymuapikey = $params['ipaymu_apikey'];

	$url = 'https://my.ipaymu.com/api/CekTransaksi.php';
	$parameters = array(
	'key'=>$gatewayipaymuapikey,
	'id'=>$trx_id,
	'format'=>'json',
	);

	$request = ipaymu_curl($url, $parameters);

	if($request['status']){
		return json_decode($request['rawdata'], true);
	}else{
		return FALSE;
	}

}

function ipaymu_curl($url, $parameters){
	$params_string = http_build_query($parameters);
	//open connection
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, count($parameters));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

	//execute post
	$request = curl_exec($ch);

	if ( $request === false ) {
		$result = array('status'=>FALSE, 'rawdata'=> 'Curl Error: ' . curl_error($ch) );
	}else{
		$result = array('status'=>TRUE, 'rawdata'=> $request );
	}
	curl_close($ch);

	return $result;
}


/*
 * ipaymu_capture << ignored
 *
 */
function ipaymu_capture($params) {

	# Gateway Specific Variables
	$gatewayusername = $params['username'];
	$gatewaytestmode = $params['testmode'];

	# Invoice Variables
	$invoiceid = $params['invoiceid'];
	$amount = $params['amount']; # Format: ##.##
	$currency = $params['currency']; # Currency Code

	# Client Variables
	$firstname = $params['clientdetails']['firstname'];
	$lastname = $params['clientdetails']['lastname'];
	$email = $params['clientdetails']['email'];
	$address1 = $params['clientdetails']['address1'];
	$address2 = $params['clientdetails']['address2'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = $params['clientdetails']['postcode'];
	$country = $params['clientdetails']['country'];
	$phone = $params['clientdetails']['phonenumber'];

	# Card Details
	$cardtype = $params['cardtype'];
	$cardnumber = $params['cardnum'];
	$cardexpiry = $params['cardexp']; # Format: MMYY
	$cardstart = $params['cardstart']; # Format: MMYY
	$cardissuenum = $params['cardissuenum'];

	# Perform Transaction Here & Generate $results Array, eg:
	$results = array();
	$results["status"] = "success";
	$results["transid"] = "12345";

	# Return Results
	if ($results["status"]=="success") {
		return array("status"=>"success","transid"=>$results["transid"],"rawdata"=>$results);
	} elseif ($gatewayresult=="declined") {
		return array("status"=>"declined","rawdata"=>$results);
	} else {
		return array("status"=>"error","rawdata"=>$results);
	}

}

/*
 * ipaymu_refund << ignored
 *
 */
function ipaymu_refund($params) {

	# Gateway Specific Variables
	$gatewayusername = $params['username'];
	$gatewaytestmode = $params['testmode'];

	# Invoice Variables
	$transid = $params['transid']; # Transaction ID of Original Payment
	$amount = $params['amount']; # Format: ##.##
	$currency = $params['currency']; # Currency Code

	# Client Variables
	$firstname = $params['clientdetails']['firstname'];
	$lastname = $params['clientdetails']['lastname'];
	$email = $params['clientdetails']['email'];
	$address1 = $params['clientdetails']['address1'];
	$address2 = $params['clientdetails']['address2'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = $params['clientdetails']['postcode'];
	$country = $params['clientdetails']['country'];
	$phone = $params['clientdetails']['phonenumber'];

	# Card Details
	$cardtype = $params['cardtype'];
	$cardnumber = $params['cardnum'];
	$cardexpiry = $params['cardexp']; # Format: MMYY
	$cardstart = $params['cardstart']; # Format: MMYY
	$cardissuenum = $params['cardissuenum'];

	# Perform Refund Here & Generate $results Array, eg:
	$results = array();
	$results["status"] = "success";
	$results["transid"] = "12345";

	# Return Results
	if ($results["status"]=="success") {
		return array("status"=>"success","transid"=>$results["transid"],"rawdata"=>$results);
	} elseif ($gatewayresult=="declined") {
		return array("status"=>"declined","rawdata"=>$results);
	} else {
		return array("status"=>"error","rawdata"=>$results);
	}

}

?>

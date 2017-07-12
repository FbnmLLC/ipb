<?php

/**
 * Product Title:        123pay gateway
 * Product Version:      1.0
 * Author:               123pay development team
 * Website URL:          https://123pay.ir
 * Email:                plugins@123pay.ir
 */

if ( ! defined( 'GW_CORE_INIT' ) ) {
	print "You cannot access this module in this manner";
	exit();
}

class gatewayApi_ir123pay extends apiCore {

	const API_NAME = 'ir123pay';

	public $ALLOW_RECURRING = true;

	public $ALLOW_UPGRADES = true;

	public $FORBID_POSTBACK = true;

	public $item = array();

	function __construct( ipsRegistry $registry ) {
		parent::__construct( $registry );
	}

	function makeFields_normal_recurring( $items = array() ) {
		$this->item = $items;

		return $this->compileFields();
	}

	function makeFields_upgrade_recurring( $items = array() ) {
		$this->item = $items;

		return $this->compileFields();
	}

	function makeFields_normal( $items = array() ) {
		$this->item = $items;

		return $this->compileFields();
	}

	function makeFields_upgrade( $items = array() ) {
		$this->item = $items;

		return $this->compileFields();
	}

	function makePurchaseButton() {
		return '<input type="submit" class="input_submit" value="پرداخت" />';
	}

	function create( $merchant_id, $amount, $callback_url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/create/payment' );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&amount=$amount&callback_url=$callback_url" );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$response = curl_exec( $ch );
		curl_close( $ch );

		return $response;
	}

	function verify( $merchant_id, $RefNum ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/verify/payment' );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&RefNum=$RefNum" );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$response = curl_exec( $ch );
		curl_close( $ch );

		return $response;
	}

	function makeFormAction_normal() {
		$merchant_id = $this->item['vendor_id'];

		if ( ! $merchant_id ) {
			ipsRegistry::getClass( 'output' )->showError( 'ir123pay_novender', 'Novender_id', false, '', 403 );
		}

		$amount       = floor( $this->item['package_cost'] );
		$callback_url = urlencode( GW_URL_VALIDATE . "&tid=" . $this->item['transaction_id'] . "&verification=" . md5( $this->item['member_unique_id'] . $this->item['package_id'] . $this->settings['sql_pass'] ) );

		$title = $this->item['package_title'];

		$response = $this->create( $merchant_id, $amount, $callback_url );

		$result = json_decode( $response );
		if ( $result->status ) {
			@session_start();
			$_SESSION['RefNum'] = $result->RefNum;

			return $result->payment_url;
		}

		return false;
	}

	function makeFormAction_upgrade() {
		return $this->makeFormAction_normal();
	}

	function makeFormAction_normal_recurring() {
		return $this->makeFormAction_normal();
	}

	function makeFormAction_upgrade_recurring() {
		return $this->makeFormAction_normal();
	}

	function validatePayment( $sets = array() ) {
		$tid = intval( $this->request['tid'] );

		if ( ! $tid ) {
			return $this->showerror();
		}

		$merchant_id = $sets['vendor_id'];

		if ( ! $merchant_id ) {
			ipsRegistry::getClass( 'output' )->showError( 'ir123pay_novender', 'Novender_id', false, '', 403 );
		}

		$trans = $this->DB->buildAndFetch( array(
			'select' => '*',
			'from'   => 'subscription_trans',
			'where'  => "subtrans_id='{$tid}'"
		) );

		if ( ! $trans['subtrans_id'] ) {
			return $this->showerror();
		}

		$amount = floor( $trans['subtrans_to_pay'] - $trans['subtrans_paid'] );

		$State  = $_REQUEST['State'];
		$RefNum = $_REQUEST['RefNum'];

		$response = $this->verify( $merchant_id, $RefNum );
		$result   = json_decode( $response );

		@session_start();
		if ( $State == 'OK' ) {
			if ( $result->status && $_SESSION['RefNum'] == $RefNum ) {
				$return = array(
					'currency_code'       => trim( 'IRR' ),
					'payment_amount'      => $amount,
					'member_unique_id'    => $trans['subtrans_member_id'],
					'subtrans_id'         => $trans['subtrans_id'],
					'purchase_package_id' => $trans['subtrans_sub_id'],
					'current_package_id'  => '',
					'verified'            => true,
					'verification'        => $this->request['verification'],
					'subscription_id'     => '0-' . intval( $trans['subtrans_member_id'] ),
					'transaction_id'      => $tid,
					'renewing'            => 0,
					'payment_status'      => 'ONEOFF',
					'state'               => 'paid',
				);

				return $return;
			}
		}

		return $this->showerror();
	}

	public function showerror() {
		$this->error = 'not_valid';

		return array( 'verified' => false );
	}

	function acpReturnPackageData() {
		return array(
			'subextra_custom_1' => array( 'used' => 0, 'varname' => '' ),
			'subextra_custom_2' => array( 'used' => 0, 'varname' => '' ),
			'subextra_custom_3' => array( 'used' => 0, 'varname' => '' ),
			'subextra_custom_4' => array( 'used' => 0, 'varname' => '' ),
			'subextra_custom_5' => array( 'used' => 0, 'varname' => '' ),
		);
	}

	function acpReturnMethodData() {
		return array(
			'submethod_custom_1' => array( 'used' => 0, 'varname' => '' ),
			'submethod_custom_2' => array( 'used' => 0, 'varname' => '' ),
			'submethod_custom_3' => array( 'used' => 0, 'varname' => '' ),
			'submethod_custom_4' => array( 'used' => 0, 'varname' => '' ),
			'submethod_custom_5' => array( 'used' => 0, 'varname' => '' ),
		);
	}

	function acpInstallGateway() {
		$this->db_info = array(
			'human_title'         => '123pay',
			'human_desc'          => '123Pay Payment Gateway',
			'module_name'         => self::API_NAME,
			'allow_creditcards'   => 1,
			'allow_auto_validate' => 1,
			'default_currency'    => 'IRR'
		);

		$this->install_lang = array( 'ir123pay_novender' => 'Please report to admin' );
	}
}
<?php

App::uses('PaypalIpnSource', 'PaypalIpn.Model/Datasource');
App::uses('HttpSocket', 'Network/Http');

/**
 * @property PaypalIpnSource $PaypalIpn
 */
class PaypalIpnSourceTestCase extends CakeTestCase {

	public function setUp() {
		parent::setUp();
		$PaypalIpn = $this->getMock('PaypalIpnSource', array('log'));
		$PaypalIpn->Http = $this->getMock('HttpSocket', array('post'));
		$this->PaypalIpn = $PaypalIpn;

		if (PHP_SAPI === 'cli') {
			// on cli mode
			$_SERVER['SERVER_ADDR'] = '127.0.0.1';
			$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		}
	}

	public function tearDown() {
		unset($this->PaypalIpn);
		parent::tearDown();
		ob_flush();
	}

	public function testIsValidShouldBeFalse() {
		$this->assertFalse($this->PaypalIpn->isValid(array()));
	}

/**
 * @dataProvider isValidDataProvider
 */
	public function testIsValid($label, $preset, $expects) {
		$this->PaypalIpn->Http->expects($this->once())
			->method('post')
			->with($expects['postUrl'], $expects['postData'])
			->will($this->returnValue(['body' => $preset['postReturn']]));

		$this->assertSame($expects['result'], $this->PaypalIpn->isValid($preset['data'], $preset['test']));
	}

	public function isValidDataProvider() {
		return array(
			array(
				'production url',
				array(
					'data' => array('test' => 'string'),
					'test' => false,
					'postReturn' => 'VERIFIED',
				),
				array(
					'postUrl' => 'https://www.paypal.com/cgi-bin/webscr?cmd=_notify-validate',
					'postData' => array('test' => 'string'),
					'result' => true,
				),
			),
			array(
				'sandbox url',
				array(
					'data' => array('test' => 'string'),
					'test' => true,
					'postReturn' => 'VERIFIED',
				),
				array(
					'postUrl' => 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_notify-validate',
					'postData' => array('test' => 'string'),
					'result' => true,
				),
			),
			array(
				'illigal response',
				array(
					'data' => array('test' => 'string'),
					'test' => false,
					'postReturn' => 'FAILURE',
				),
				array(
					'postUrl' => 'https://www.paypal.com/cgi-bin/webscr?cmd=_notify-validate',
					'postData' => array('test' => 'string'),
					'result' => false,
				),
			),
			array(
				'response error',
				array(
					'data' => array('test' => 'string'),
					'test' => false,
					'postReturn' => false,
				),
				array(
					'postUrl' => 'https://www.paypal.com/cgi-bin/webscr?cmd=_notify-validate',
					'postData' => array('test' => 'string'),
					'result' => false,
				),
			),
		);
	}

	public function testParseRaw() {
		$response = 'mc_gross=19.95&protection_eligibility=Eligible&address_status=confirmed&payer_id=LPLWNMTBWMFAY&tax=0.00&address_street=1+Main+St&payment_date=20%3A12%3A59+Jan+13%2C+2009+PST&payment_status=Completed&charset=windows-1252&address_zip=95131&first_name=Test&mc_fee=0.88&address_country_code=US&address_name=Test+User&notify_version=2.6&custom=&payer_status=verified&address_country=United+States&address_city=San+Jose&quantity=1&verify_sign=AtkOfCXbDm2hu0ZELryHFjY-Vb7PAUvS6nMXgysbElEn9v-1XcmSoGtf&payer_email=gpmac_1231902590_per%40paypal.com&txn_id=61E67681CH3238416&payment_type=instant&last_name=User&address_state=CA&receiver_email=gpmac_1231902686_biz%40paypal.com&payment_fee=0.88&receiver_id=S8XGHLYDW9T3S&txn_type=express_checkout&item_name=&mc_currency=USD&item_number=&residence_country=US&test_ipn=1&handling_amount=0.00&transaction_subject=&payment_gross=19.95&shipping=0.00';
		$expects = array(
			'mc_gross' => '19.95',
			'protection_eligibility' => 'Eligible',
			'address_status' => 'confirmed',
			'payer_id' => 'LPLWNMTBWMFAY',
			'tax' => '0.00',
			'address_street' => '1 Main St',
			'payment_date' => '20:12:59 Jan 13, 2009 PST',
			'payment_status' => 'Completed',
			'charset' => 'windows-1252',
			'address_zip' => '95131',
			'first_name' => 'Test',
			'mc_fee' => '0.88',
			'address_country_code' => 'US',
			'address_name' => 'Test User',
			'notify_version' => '2.6',
			'custom' => '',
			'payer_status' => 'verified',
			'address_country' => 'United States',
			'address_city' => 'San Jose',
			'quantity' => '1',
			'verify_sign' => 'AtkOfCXbDm2hu0ZELryHFjY-Vb7PAUvS6nMXgysbElEn9v-1XcmSoGtf',
			'payer_email' => 'gpmac_1231902590_per@paypal.com',
			'txn_id' => '61E67681CH3238416',
			'payment_type' => 'instant',
			'last_name' => 'User',
			'address_state' => 'CA',
			'receiver_email' => 'gpmac_1231902686_biz@paypal.com',
			'payment_fee' => '0.88',
			'receiver_id' => 'S8XGHLYDW9T3S',
			'txn_type' => 'express_checkout',
			'item_name' => '',
			'mc_currency' => 'USD',
			'item_number' => '',
			'residence_country' => 'US',
			'test_ipn' => '1',
			'handling_amount' => '0.00',
			'transaction_subject' => '',
			'payment_gross' => '19.95',
			'shipping' => '0.00',
		);
		$result = $this->PaypalIpn->parseRaw($response);

		$this->assertSameSize($expects, $result);
	}

	public function testParseRawWithTransaction() {
		$response = 'transaction[0].id_for_sender_txn=redacted&transaction[0].receiver=redacted';
		$expects = array(
			'transaction' => array(
				0 => array(
					'id_for_sender_txn' => 'redacted',
					'receiver' => 'redacted',
				),
			),
		);
		$result = $this->PaypalIpn->parseRaw($response);

		$this->assertSameSize($expects, $result);
	}

}

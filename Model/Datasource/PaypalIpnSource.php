<?php

App::uses('DataSource', 'Model/Datasource');
App::uses('HttpSocket', 'Network/Http');

/**
 * @property HttpSocket $Http HttpSocket Object
 */
class PaypalIpnSource extends DataSource {

/**
 * constructer.  Load the HttpSocket into the Http var.
 */
	public function __construct($config = array()) {
		$this->Http = $this->_getHttpSocket();
	}

/**
 * get remote IP
 *
 * @return string or false
 */
	public static function getRemoteIp() {
		if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			return $_SERVER['HTTP_CF_CONNECTING_IP'];
		}

		if (!empty($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];
		}

		return false;
	}

/**
 * Strip slashes
 * @param string value
 * @return string
 */
	public static function clearSlash($value) {
		return get_magic_quotes_runtime() ? stripslashes($value) : $value;
	}

/**
 * verifies POST data given by the paypal instant payment notification
 * @param array $data Most likely directly $_POST given by the controller.
 * @return boolean true | false depending on if data received is actually valid from paypal and not from some script monkey
 */
	public function isValid($data, $test = false) {
		if (env('SERVER_ADDR') === self::getRemoteIp() ||
			preg_match('/paypal\.com$/', gethostbyaddr(self::getRemoteIp()))
		) {

			$server = $test ? 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_notify-validate' : 'https://www.paypal.com/cgi-bin/webscr?cmd=_notify-validate';

			$response = $this->Http->post($server, $data);

			if ($response === 'VERIFIED') {
				return true;
			}

			if (!$response) {
				$this->log('HTTP Error in PaypalIpnSource::isValid while posting back to PayPal', 'paypal');
			}
		} else {
			$this->log('IPN Notification comes from unknown IP: ' . self::getRemoteIp(), 'paypal');
		}

		return false;
	}

/**
 * return HttpSocket
 *
 * @param array $config
 * @return \HttpSocket
 */
	protected function _getHttpSocket($config = array()) {
		return new HttpSocket($config);
	}

}

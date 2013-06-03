<?php

App::uses('PaypalIpnSource', 'PaypalIpn.Model/Datasource');
App::uses('CakeEmail', 'Network/Email');
App::uses('PaypalIpnEmptyRawDataExpection', 'PaypalIpn.Lib/Error');
App::uses('PaypalIpnAppModel', 'PaypalIpn.Model');

/**
 * @property PaypalItem $PaypalItem Model hasMany
 */
class InstantPaymentNotification extends PaypalIpnAppModel {

	const EVENT_AFTER_PROCESS = 'PaypalIpn.afterProcess';

	public $name = 'InstantPaymentNotification';

	public $hasMany = array(
		'PaypalItem' => array(
			'className' => 'PaypalIpn.PaypalItem'
		)
	);

/**
 * verifies POST data given by the paypal instant payment notification
 *
 * @param string $raw post data
 * @return boolean true | false depending on if data received is actually valid from paypal and not from some script monkey
 */
	public function isValid($raw) {
		if (!empty($raw)) {
			$parse = $this->parseRaw($raw);
			return $this->_getPaypalIpnSource()->isValid($raw, !empty($parse['test_ipn']));
		}
		return false;
	}

/**
 * recive ipn response
 *
 * @param string $id InstantPaymentNotification.id
 * @return string 'Valid' or 'Invalid'
 * @throws PaypalIpnEmptyRawDataExpection
 */
	public function process($id = null) {
		$raw = $this->getRaw($id);
		if (empty($raw)) {
			throw new PaypalIpnEmptyRawDataExpection(__d('paypal_ipn', 'raw data is empty.'));
		}

		// create save data
		$data = $this->parseRaw($raw);
		$data['valid'] = $this->isValid($raw);
		$data['ip'] = PaypalIpnSource::getRemoteIp();
		$data['raw'] = $raw;

		// build associations data
		$saveData = $this->buildAssociationsFromIPN($data);

		// save
		$this->saveAll($saveData);

		// dispatch event
		$this->getEventManager()->dispatch(new CakeEvent(
			self::EVENT_AFTER_PROCESS, $this, array('id' => $id)
		));

		return $data['valid'] ? 'Valid' : 'Invalid';
	}

/**
 * Utility method to send basic emails based on a paypal IPN transaction.
 * This method is very basic, if you need something more complicated I suggest
 * creating your own method in the afterPaypalNotification function you build
 * in the AppController.php
 *
 * Example Usage: (InstantPaymentNotification = IPN)
 *   IPN->id = '4aeca923-4f4c-49ec-a3af-73d3405bef47';
 *   IPN->email('Thank you for your transaction!');
 *
 *   IPN->email(array(
 *     'id' => '4aeca923-4f4c-49ec-a3af-73d3405bef47',
 *     'subject' => 'Donation Complete!',
 *     'message' => 'Thank you for your donation!',
 *     'sendAs' => 'text'
 *   ));
 *
 *  Hint: use this in your afterPaypalNotification callback in your AppController.php
 *   function afterPaypalNotification($txnId){
 *     ClassRegistry::init('PaypalIpn.InstantPaymentNotification')->email(array(
 *       'id' => $txnId,
 *       'subject' => 'Thanks!',
 *       'message' => 'Thank you for the transaction!'
 *     ));
 *   }
 *
 * Options:
 *   id: id of instant payment notification to base email off of
 *   subject: subject of email (default: Thank you for your paypal transaction)
 *   sendAs: html | text (default: html)
 *   to: email address to send email to (default: ipn payer_email)
 *   from: from email address (default: ipn business)
 *   cc: array of email addresses to carbon copy to (default: array())
 *   bcc: array of email addresses to blind carbon copy to (default: array())
 *   layout: layout of email to send (default: default)
 *   template: template of email to send (default: null)
 *   log: boolean true | false if you'd like to log the email being sent. (default: true)
 *   message: actual body of message to be sent (default: null)
 *
 * @param array $options of the ipn to send
 *
 */
	public function email($options = array()) {
		if (!is_array($options)) {
			$message = $options;
			$options = array();
			$options['message'] = $message;
		}
		if (isset($options['id'])) {
			$this->id = $options['id'];
		}

		$this->read();
		$defaults = array(
			'subject' => __d('paypal_ipn', 'Thank you for your paypal transaction'),
			'sendAs' => 'html',
			'to' => $this->data[$this->alias]['payer_email'],
			'from' => $this->data[$this->alias]['business'],
			'cc' => array(),
			'bcc' => array(),
			'layout' => 'default',
			'template' => null,
			'log' => true,
			'message' => null,
			'config' => 'default'
		);
		$options = array_merge($defaults, $options);

		//debug($options);
		if ($options['log']) {
			$this->log(__d('paypal_ipn', "Emailing: %s through the PayPal IPN Plugin.", $options['to']), 'email');
		}
		$fullname = __d('paypal_ipn', '%s %s', $this->data[$this->alias]['first_name'], $this->data[$this->alias]['last_name']);

		$Email = $this->_getCakeEmail($options['config']);
		$Email->to($options['to'], $fullname)
			->from($options['from'])
			->subject($options['subject'])
			->emailFormat($options['sendAs'])
			->template($options['template'], $options['layout']);
		if (!empty($options['bcc']))
			$Email->bcc($options['bcc']);
		if (!empty($options['cc']))
			$Email->cc($options['cc']);
		//Send the message.
		if ($options['message']) {
			$Email->send($options['message']);
		} else {
			$Email->send();
		}
	}

/**
 * builds the associative array for paypalitems only if it was a cart upload
 *
 * @param raw post data sent back from paypal
 * @return array of cakePHP friendly association array.
 */
	public function buildAssociationsFromIPN($post) {
		$retval = array();
		$retval[$this->alias] = $post;
		if (isset($post['num_cart_items']) && $post['num_cart_items'] > 0) {
			$retval[$this->PaypalItem->alias] = array();
			for ($i = 1; $i <= $post['num_cart_items']; $i++) {
				$key = $i - 1;
				$retval[$this->PaypalItem->alias][$key]['item_name'] = $post["item_name$i"];
				$retval[$this->PaypalItem->alias][$key]['item_number'] = $post["item_number$i"];
				$retval[$this->PaypalItem->alias][$key]['item_number'] = $post["item_number$i"];
				$retval[$this->PaypalItem->alias][$key]['quantity'] = $post["quantity$i"];
				$retval[$this->PaypalItem->alias][$key]['mc_shipping'] = $post["mc_shipping$i"];
				$retval[$this->PaypalItem->alias][$key]['mc_handling'] = $post["mc_handling$i"];
				$retval[$this->PaypalItem->alias][$key]['mc_gross'] = $post["mc_gross_$i"];
				$retval[$this->PaypalItem->alias][$key]['tax'] = $post["tax$i"];
			}
		}
		return $retval;
	}

/**
 * get post data
 *
 * @param string $id InstantPaymentNotification.id
 * @return string
 * @deprecated
 */
	public function getRaw($id = null) {
		return $this->_getPaypalIpnSource()->getRawPostData();
	}

/**
 * parse raw data
 *
 * @param string $raw
 * @return array
 * @deprecated
 */
	public function parseRaw($raw) {
		return $this->_getPaypalIpnSource()->parseRaw($raw);
	}

/**
 * get PaypalIpnSource
 *
 * @return \PaypalIpnSource
 */
	protected function _getPaypalIpnSource() {
		static $paypal;
		if (!isset($paypal)) {
			$paypal = new PaypalIpnSource();
		}
		return $paypal;
	}

/**
 * get CakeEmail
 *
 * @param string $config
 * @return \CakeEmail
 */
	protected function _getCakeEmail($config = null) {
		return new CakeEmail($config);
	}

}

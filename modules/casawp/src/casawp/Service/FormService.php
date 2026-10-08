<?php
namespace casawp\Service;

class FormService{

	public $formSendHasAlreadyOccuredDuringThisRequest = false;

	public function __construct(){

	}

	private function sanitizeContactFormPost($post){
		$data = array();
		foreach ($post as $key => $value) {
			switch ($key) {
				case 'message':
					$data[$key] = sanitize_textarea_field($value);
					break;
				default:
				if(is_array($value)){
					$data[$key] = $value;
				} else {
					$data[$key] = sanitize_text_field($value);
				}
				break;
			}
		}
		return $data;
	}

	public function buildAndValidateContactForm($subjectItem, $formSetting = null, $directRecipientEmail = null, $propertyReference = null){
		if ($subjectItem instanceof Offer && $subjectItem->getAvailability() == 'reference') {
			return false;
		}
		$form = new \casawp\Form\ContactForm();
		if (!$formSetting) {
			$formSetting = new \casawp\Form\DefaultFormSetting();
		}

		$formSetting->setAdditionalFields($form);

		$customerid = get_option('casawp_customerid');
		$publisherid = get_option('casawp_publisherid');
		$email = get_option('casawp_email_fallback');
		$flatfoxRecipient = $this->getFlatfoxRemcatRecipient($subjectItem);

		if ($subjectItem instanceof Offer && $subjectItem->getFieldValue('seller_org_customerid', false)) {
			$customerid = $subjectItem->getFieldValue('seller_org_customerid', false);
		}
		if ($subjectItem instanceof Offer && $subjectItem->getFieldValue('seller_inquiry_person_email', false)) {
			$email = $subjectItem->getFieldValue('seller_inquiry_person_email', false);
		}

		if (get_option('casawp_inquiry_method') == 'casamail') {
			if (!$flatfoxRecipient && (!$customerid || !$publisherid)) {
				return '<p class="alert alert-danger">CASAMAIL MISCONFIGURED: please define a provider and publisher id <a href="/wp-admin/admin.php?page=casawp&tab=contactform">here</a></p>';
			}
		} else {
			if (!$email) {
				return '<p class="alert alert-danger">EMAIL MISCONFIGURED: please define a email address <a href="/wp-admin/admin.php?page=casawp&tab=contactform">here</a></p>';
			}
		}

		$form->get('form_id')->setValue($formSetting->getId());
		$sent = false;
		$filter = $form->getFilter();
		$form->setInputFilter($filter);
		$invalidCaptcha = false;
		$sendError = false;
		if ($_POST) {
			$id = (isset($_POST['form_id']) ? $_POST['form_id'] : false);
			if (!$id || $id === $formSetting->getId()) {
				$postdata = $this->sanitizeContactFormPost($_POST);
				if (isset($_FILES) && $_FILES) {
					$postdata = array_merge_recursive($postdata, $_FILES);
				}
				$form->setData($postdata);
				if ($form->isValid()) {
					if (!$this->formSendHasAlreadyOccuredDuringThisRequest) {
						$this->formSendHasAlreadyOccuredDuringThisRequest = true;
						$sent = true;
						$validatedData = $form->getData();
						if (!wp_verify_nonce( $_REQUEST['_wpnonce'], 'send-inquiry')) {
							echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
							print_r('NONCE ISSUE BITTE MELDEN');
							echo "</textarea>";
							//SPAM
						} else if (isset($postdata['email']) && $postdata['email']) {
							//SPAM
						} else {
							if (get_option('casawp_recaptcha')) {
								$validCaptcha = null;
								if (isset($_POST['g-recaptcha-response'])) {
									$validCaptcha = $this->verifyCaptcha($_POST['g-recaptcha-response']);
								}
								if ($validCaptcha &&  $validCaptcha === 'success') {
									do_action('casawp_before_inquirystore', array(
										'postdata' => $postdata,
										'offer' => $subjectItem, //legacy
										'subjectItem' => $subjectItem
									));
									do_action('casawp_before_inquirysend', array(
										'postdata' => $postdata,
										'offer' => $subjectItem, //legacy
										'subjectItem' => $subjectItem
									));
									if ($flatfoxRecipient) {
										$sent = $this->sendFlatfoxRemcatInquiry($flatfoxRecipient, $subjectItem, $validatedData);
										$sendError = !$sent;
									//send to casamail
									} elseif (get_option('casawp_inquiry_method') == 'casamail') {

										$data = array_merge($postdata, $validatedData);
										$data['email'] = $postdata['emailreal'];
										$data['provider'] = $customerid;
										$data['publisher'] = $publisherid;
										$data['lang'] = substr(get_bloginfo('language'), 0, 2);

										if (isset($postdata['legal_name'])) {
											$data['legal_name'] = $postdata['legal_name'];
										}

										if($directRecipientEmail){
											$data['direct_recipient_email'] = $directRecipientEmail;
										}

										if ($propertyReference) {
											$data['property_reference'] = $propertyReference;
										}

										if ($subjectItem instanceof Project) {
											$data['project_reference'] = $subjectItem->getFieldValue('visualReferenceId') . '..' . $subjectItem->getFieldValue('referenceId');
										} elseif ($subjectItem instanceof Offer) {
											if (substr_count($subjectItem->getFieldValue('referenceId'), '.') >= 2) {
												$data['property_reference'] = $subjectItem->getFieldValue('referenceId');
											} else {
												$data['property_reference'] = $subjectItem->getFieldValue('visualReferenceId') . '..' . $subjectItem->getFieldValue('referenceId');
											}
										}

										if ($subjectItem instanceof Offer) {
											$data['property_street'] = $subjectItem->getFieldValue('address_streetaddress');
											$data['property_postal_code'] = $subjectItem->getFieldValue('address_postalcode');
											$data['property_locality'] = $subjectItem->getFieldValue('address_locality');
											//$data['property_category'] = $subjectItem->getFieldValue('referenceId');
											$data['property_country'] = $subjectItem->getFieldValue('address_country');
											//$data['property_rooms'] = $subjectItem->getFieldValue('referenceId');
											//$data['property_type'] = $subjectItem->getFieldValue('referenceId');
											//$data['property_price'] = $subjectItem->getFieldValue('referenceId');
											//direct recipient emails
											if (get_option('casawp_casamail_direct_recipient') && $subjectItem->getFieldValue('seller_inquiry_person_email', false)) {
												$data['direct_recipient_email'] = $subjectItem->getFieldValue('seller_inquiry_person_email', false);
											} elseif (get_option('casawp_casamail_direct_recipient') && $subjectItem->getFieldValue('seller_view_person_email', false)) {
												$data['direct_recipient_email'] = $subjectItem->getFieldValue('seller_view_person_email', false);
											}
										}

										$data = $formSetting->preCasaMailFilter($data, $postdata, $validatedData);

										$data_string = json_encode($data);

										$ch = curl_init('https://message.casasoft.com/api/msg');
										curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
										curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
										curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
										curl_setopt($ch, CURLOPT_HTTPHEADER, array(
											'Content-Type: application/json',
											'Content-Length: ' . strlen($data_string))
										);

										curl_setopt($ch, CURLOPT_USERPWD,  "casawp:MQX-2C2-Hrh-zUu");

										$result = curl_exec($ch);
										$json = json_decode($result, true);
										if (isset($json['validation_messages'])) {
											wp_mail( 'dev@casasoft.ch', 'casawp casamail issue publisher ' . $data['publisher'], print_r($json['validation_messages'], true));
											return '<p class="alert alert-danger">'.print_r($json['validation_messages'], true).'</p>';
										}
									}

									if ($sent) {
										do_action('casawp_after_inquirysend', array(
											'postdata' => $postdata,
											'offer' => $subjectItem,
											'subjectItem' => $subjectItem
										));
									}
								} else {
									$sent = false;
									$invalidCaptcha = true;
								}
							} else {
								do_action('casawp_before_inquirystore', array(
									'postdata' => $postdata,
									'offer' => $subjectItem, //legacy
									'subjectItem' => $subjectItem
								));

								//add to WP for safekeeping
								// THIS HAS BEEN DISABLED DUE TO GDPR
								// $nameExtension = '';
								// if ($subjectItem instanceof Offer) {
								// 	$nameExtension = ': [' . ($subjectItem->getFieldValue('referenceId') ? $subjectItem->getFieldValue('referenceId') : $subjectItem->getFieldValue('casawp_id')) . '] ' . $subjectItem->getTitle();
								// }
								// $post_title = wp_strip_all_tags($form->get('firstname')->getValue() . ' ' . $form->get('lastname')->getValue());
								// $post = array(
								// 	'post_type' => 'casawp_inquiry',
								// 	'post_content' => ($form->get('message')->getValue() ? $form->get('message')->getValue() : 'NO MESSAGE'),
								// 	'post_title' => $post_title,
								// 	'post_status' => 'private',
								// 	'ping_status' => false
								// );
								// $inquiry_id = wp_insert_post($post);
								// foreach ($form->getElements() as $element) {
								// 	if (!in_array($element->getName(), array('message')) ) {
								// 		add_post_meta($inquiry_id, 'sender_' . $element->getName(), $element->getValue(), true );
								// 	}
								// }
								// if ($subjectItem instanceof Offer) {
								// 	add_post_meta($inquiry_id, 'casawp_id', $subjectItem->getFieldValue('casawp_id'), true );
								// 	add_post_meta($inquiry_id, 'referenceId', $subjectItem->getFieldValue('referenceId'), true );
								// }
								//        if ($subjectItem instanceof Project) {
								// 	add_post_meta($inquiry_id, 'casawp_id', $subjectItem->getFieldValue('casawp_id'), true );
								// 	add_post_meta($inquiry_id, 'referenceId', $subjectItem->getFieldValue('referenceId'), true );
								// }

								do_action('casawp_before_inquirysend', array(
									'postdata' => $postdata,
									'offer' => $subjectItem, //legacy
									'subjectItem' => $subjectItem
								));


								if ($flatfoxRecipient) {
									$sent = $this->sendFlatfoxRemcatInquiry($flatfoxRecipient, $subjectItem, $validatedData);
									$sendError = !$sent;
								//send to casamail
								} elseif (get_option('casawp_inquiry_method') == 'casamail') {

									$data = array_merge($postdata, $validatedData);
									$data['email'] = $postdata['emailreal'];
									$data['provider'] = $customerid;
									$data['publisher'] = $publisherid;
									$data['lang'] = substr(get_bloginfo('language'), 0, 2);

									if (isset($postdata['legal_name'])) {
										$data['legal_name'] = $postdata['legal_name'];
									}

									if($directRecipientEmail){
										$data['direct_recipient_email'] = $directRecipientEmail;
									}

									if ($propertyReference) {
										$data['property_reference'] = $propertyReference;
									}

									if ($subjectItem instanceof Project) {
										$data['project_reference'] = $subjectItem->getFieldValue('visualReferenceId') . '..' . $subjectItem->getFieldValue('referenceId');
									} elseif ($subjectItem instanceof Offer) {
										if (substr_count($subjectItem->getFieldValue('referenceId'), '.') >= 2) {
											$data['property_reference'] = $subjectItem->getFieldValue('referenceId');
										} else {
											$data['property_reference'] = $subjectItem->getFieldValue('visualReferenceId') . '..' . $subjectItem->getFieldValue('referenceId');
										}
									}

									if ($subjectItem instanceof Offer) {
										$data['property_street'] = $subjectItem->getFieldValue('address_streetaddress');
										$data['property_postal_code'] = $subjectItem->getFieldValue('address_postalcode');
										$data['property_locality'] = $subjectItem->getFieldValue('address_locality');
										//$data['property_category'] = $subjectItem->getFieldValue('referenceId');
										$data['property_country'] = $subjectItem->getFieldValue('address_country');
										//$data['property_rooms'] = $subjectItem->getFieldValue('referenceId');
										//$data['property_type'] = $subjectItem->getFieldValue('referenceId');
										//$data['property_price'] = $subjectItem->getFieldValue('referenceId');
										//direct recipient emails
										if (get_option('casawp_casamail_direct_recipient') && $subjectItem->getFieldValue('seller_inquiry_person_email', false)) {
											$data['direct_recipient_email'] = $subjectItem->getFieldValue('seller_inquiry_person_email', false);
										} elseif (get_option('casawp_casamail_direct_recipient') && $subjectItem->getFieldValue('seller_view_person_email', false)) {
											$data['direct_recipient_email'] = $subjectItem->getFieldValue('seller_view_person_email', false);
										}
									}

									$data = $formSetting->preCasaMailFilter($data, $postdata, $validatedData);

									$data_string = json_encode($data);

									$ch = curl_init('https://message.casasoft.com/api/msg');
									curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
									curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
									curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
									curl_setopt($ch, CURLOPT_HTTPHEADER, array(
										'Content-Type: application/json',
										'Content-Length: ' . strlen($data_string))
									);

									curl_setopt($ch, CURLOPT_USERPWD,  "casawp:MQX-2C2-Hrh-zUu");

									$result = curl_exec($ch);
									$json = json_decode($result, true);
									if (isset($json['validation_messages'])) {
										wp_mail( 'dev@casasoft.ch', 'casawp casamail issue publisher ' . $data['publisher'], print_r($json['validation_messages'], true));
										return '<p class="alert alert-danger">'.print_r($json['validation_messages'], true).'</p>';
									}
								}

								if ($sent) {
									do_action('casawp_after_inquirysend', array(
										'postdata' => $postdata,
										'offer' => $subjectItem,
										'subjectItem' => $subjectItem
									));
								}
							}

							
						}
					}
				} else {
					$messages = $form->getMessages();
				}
			}
		} else {
			if (!$form->get('message')->getValue()) {
				$form->get('message')->setValue(__('I am interested concerning this property. Please contact me.','casawp'));
			}
		}
		return array('form' => $form, 'sent' => $sent, 'invalidCaptcha' => $invalidCaptcha, 'sendError' => $sendError);
	}

	private function getFlatfoxRemcatRecipient($subjectItem){
		if (!($subjectItem instanceof Offer)) {
			return false;
		}

		$recipient = $subjectItem->getFieldValue('seller_inquiry_person_email', false);
		if (!$recipient) {
			$recipient = $subjectItem->getFieldValue('seller_view_person_email', false);
		}

		if (!is_string($recipient)) {
			return false;
		}

		$recipient = trim($recipient);
		if (!is_email($recipient)) {
			return false;
		}

		$parts = explode('@', $recipient);
		if (count($parts) !== 2) {
			return false;
		}

		$localPart = $parts[0];
		$domain = strtolower($parts[1]);
		if ($domain !== 'ads.flatfox.ch' || stripos($localPart, 'remcat.') !== 0 || strlen($localPart) <= strlen('remcat.')) {
			return false;
		}

		return $recipient;
	}

	private function sendFlatfoxRemcatInquiry($recipient, $subjectItem, $data){
		$reference = $subjectItem->getFieldValue('referenceId', false);
		if (!$reference) {
			return false;
		}

		$contactPrefix = $subjectItem->getFieldValue('seller_inquiry_person_email', false)
			? 'seller_inquiry_person_'
			: 'seller_view_person_';
		$agencyContact = trim(
			$subjectItem->getFieldValue($contactPrefix . 'givenname', '') . ' ' .
			$subjectItem->getFieldValue($contactPrefix . 'familyname', '')
		);

		$gender = isset($data['gender']) ? (string) $data['gender'] : '';
		$salutation = '';
		if ($gender === '1') {
			$salutation = 'm';
		} elseif ($gender === '2') {
			$salutation = 'f';
		}

		$fields = array(
			get_bloginfo('name'), // 1: portal or website name
			$subjectItem->getFieldValue('seller_org_legalname', ''), // 2: agency name
			$subjectItem->getFieldValue('seller_org_address_streetaddress', ''), // 3: agency street
			$subjectItem->getFieldValue('seller_org_address_postalcode', ''), // 4: agency zip code
			$subjectItem->getFieldValue('seller_org_address_locality', ''), // 5: agency city
			$agencyContact, // 6: agency contact
			$recipient, // 7: agency contact email
			$reference, // 8: complete property reference
			home_url('/'), // 9: source website
			trim($subjectItem->getFieldValue('address_streetaddress', '') . ' ' . $subjectItem->getFieldValue('address_streetnumber', '')), // 10: object address
			trim($subjectItem->getFieldValue('address_postalcode', '') . ' ' . $subjectItem->getFieldValue('address_locality', '')), // 11: object zip and city
			'', // 12: object type
			strtoupper(substr(get_bloginfo('language'), 0, 2)), // 13: language
			$salutation, // 14: salutation
			isset($data['firstname']) ? $data['firstname'] : '', // 15: applicant first name
			isset($data['lastname']) ? $data['lastname'] : '', // 16: applicant last name
			isset($data['legal_name']) ? $data['legal_name'] : '', // 17: applicant company
			isset($data['street']) ? $data['street'] : '', // 18: applicant address
			isset($data['postal_code']) ? $data['postal_code'] : '', // 19: applicant zip code
			isset($data['locality']) ? $data['locality'] : '', // 20: applicant city
			isset($data['phone']) ? $data['phone'] : '', // 21: applicant phone
			isset($data['mobile']) ? $data['mobile'] : '', // 22: applicant mobile phone
			'', // 23: applicant fax
			isset($data['emailreal']) ? $data['emailreal'] : '', // 24: applicant email
			isset($data['message']) ? $data['message'] : '', // 25: contact text
			'', // 26: preferred contact method
			'' // 27: requested documentation
		);

		foreach ($fields as $index => $field) {
			$fields[$index] = $this->sanitizeRemcatField($field, $index === 24); // Zero-based index 24 is REMCAT field 25.
		}

		$subject = 'CASAWP contact request ' . $this->sanitizeRemcatField($reference);
		$message = '#' . implode('#', $fields);
		$headers = array('Content-Type: text/plain; charset=UTF-8');

		return wp_mail($recipient, $subject, $message, $headers);
	}

	private function sanitizeRemcatField($value, $preserveLineBreaks = false){
		if (!is_scalar($value)) {
			return '';
		}

		$value = wp_strip_all_tags((string) $value);
		$value = str_replace('#', ' ', $value);

		if ($preserveLineBreaks) {
			$value = preg_replace("/\r\n|\r|\n/", '&CRLF', $value);
		} else {
			$value = preg_replace('/\s+/u', ' ', $value);
		}

		return trim($value);
	}

	private function render($view, $args = array()){
		global $casawp;
		return $casawp->render($view, $args);
	}

	private function verifyCaptcha($captchaResponse) {
		$is_recaptcha_v3 = false;
		if (get_option('casawp_recaptcha_v3')) {
			$is_recaptcha_v3 = true;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			'secret' => get_option('casawp_recaptcha_secret'),
			'response' => $captchaResponse,
			'remoteip' => $_SERVER['REMOTE_ADDR']
		]);

		$response = json_decode(curl_exec($ch));
		curl_close($ch);

		$this->addToLogFormStuff('V3?: ' . $is_recaptcha_v3 . ' Success: ' . $response->success . ' Score: ' . $response->score . ' Score defined: ' . get_option('casawp_recaptcha_v3_score'));

		if (empty($response->success) || ($is_recaptcha_v3 && $response->score <= get_option('casawp_recaptcha_v3_score', '0.4'))) {
			// Fail
			//throw new \Exception('Gah! CAPTCHA verification failed.', 1);
			return 'fail';
		} else {
			// Success
			return 'success';
		}
	}

	public function addToLogFormStuff($transcript){
		$dir = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/logsformstuff';
		if (!file_exists($dir)) {
			mkdir($dir, 0777, true);
		}
		file_put_contents($dir."/".get_date_from_gmt('', 'Ym').'.log', "\n".json_encode(array(get_date_from_gmt('', 'Y-m-d H:i') => $transcript)), FILE_APPEND);
	}

	public function renderContactForm($subjectItem = false, $viewfile = 'contact-form', $directRecipientEmail = null, $propertyReference = null){
		$formResult = $this->buildAndValidateContactForm($subjectItem, null, $directRecipientEmail, $propertyReference);
		return $this->render($viewfile, array(
			'form' => $formResult['form'],
			'sent' => $formResult['sent'],
			'invalidCaptcha' => $formResult['invalidCaptcha'],
			'sendError' => $formResult['sendError']
		));
	}
}

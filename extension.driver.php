<?php

require_once EXTENSIONS . '/balanced/data-sources/datasource.balanced.php';
require_once(EXTENSIONS . '/balanced/lib/class.balancedgeneral.php');

class Extension_Balanced extends Extension {

	private static $provides = array();

	public static function registerProviders() {
		self::$provides = array(
			'data-sources' => array(
				'BalancedDatasource' => BalancedDatasource::getName()
			)
		);

		return true;
	}

	public static function providerOf($type = null) {
		self::registerProviders();

		if(is_null($type)) return self::$provides;

		if(!isset(self::$provides[$type])) return array();

		return self::$provides[$type];
	}


	/*-------------------------------------------------------------------------
		Delegates:
	-------------------------------------------------------------------------*/

	public function getSubscribedDelegates() {
		return array(
			array(
				'page' => '/blueprints/events/',
				'delegate' => 'EventPreEdit',
				'callback' => 'actionEventPreEdit'
			),
			array(
				'page' => '/blueprints/events/new/',
				'delegate' => 'AppendEventFilter',
				'callback' => 'actionAppendEventFilter'
			),
			array(
				'page' => '/blueprints/events/edit/',
				'delegate' => 'AppendEventFilter',
				'callback' => 'actionAppendEventFilter'
			),
			array(
				'page' => '/blueprints/events/',
				'delegate' => 'AppendEventFilterDocumentation',
				'callback' => 'actionAppendEventFilterDocumentation'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'EventPreSaveFilter',
				'callback' => 'actionEventPreSaveFilter'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'EventPostSaveFilter',
				'callback' => 'actionEventPostSaveFilter'
			),
			array(
				'page' => '*',
				'delegate' => 'SE_PrepareFilter',
				'callback' => 'actionEventPreSaveFilter'
			),
			array(
				'page' => '*',
				'delegate' => 'SE_CommitFilter',
				'callback' => 'actionEventPostSaveFilter'
			),
			array(
				'page' => '/system/preferences/',
				'delegate' => 'AddCustomPreferenceFieldsets',
				'callback' => 'actionAddCustomPreferenceFieldsets'
			),
			array(
				'page' => '/system/preferences/',
				'delegate' => 'Save',
				'callback' => 'actionSave'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendParamsResolve',
				'callback' => 'actionAppendAppParams'
			)
		);
	}

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

	public function actionEventPreEdit($context) {
		// Your code goes here...
	}

	public function actionAppendEventFilter($context) {
		$filters = Balanced_General::getAllFilters();

		foreach ($filters as $key => $val) {
			if (is_array($context['selected'])) {
				$selected = in_array($key, $context['selected']);
				$context['options'][] = array($key, $selected, $val);
			}
		}
	}

	public function actionAppendEventFilterDocumentation($context) {
		// Todo not firing
		var_dump($context);
	}

	public function actionEventPreSaveFilter($context) {
		$filters = $context['event']->eParamFILTERS;
		if(!isset($filters)) {
			$filters = $context['filters'];
		}
		$proceed = false;

		foreach ($filters as $key => $val) {
			if (in_array($val, array_keys(Balanced_General::getAllFilters()))) {
				$proceed = true;
			}
		}

		if(!$proceed) return true;
		//print_r($_POST); die();

		//if(!isset($_SESSION['symphony-balanced'])) {

			$fields = $_POST['balanced'];
			if(!isset($fields)) {
				$fields = $context['fields']['balanced'];
			}

			if (isset($fields)) {
				// Convert handles if Symphony standard
				foreach ($fields as $key => $val) {
					$key = str_replace('-', '_', $key);
					$fields[$key] = $val;
				}
			}

			$debitFeeVariable = Symphony::Configuration()->get('debit-fee-variable', 'balanced');
			$debitFeeFixed = Symphony::Configuration()->get('debit-fee-fixed', 'balanced');
			$creditFeeVariable = Symphony::Configuration()->get('credit-fee-variable', 'balanced');
			$creditFeeFixed = Symphony::Configuration()->get('credit-fee-fixed', 'balanced');

			// Convert dollars into cents
			if (isset($fields['subamount'])) {
				$fields['subamount'] = Balanced_General::dollarsToCents($fields['subamount']);
				$subamount = $fields['subamount'];
			}
			if (isset($fields['fees'])) {
				$fields['fees'] = Balanced_General::dollarsToCents($fields['fees']);
				$fees = $fields['fees'];
			}
			if (isset($fields['amount'])) {
				$fields['amount'] = Balanced_General::dollarsToCents($fields['amount']);
			}
			if (isset($fields['amount_1'])) {
				$fields['amount_1'] = Balanced_General::dollarsToCents($fields['amount_1']);
			}
			if (isset($fields['amount_1'])) {
				$fields['amount_1'] = Balanced_General::dollarsToCents($fields['amount_1']);
			}

			foreach ($filters as $key => $val) {
				if (in_array($val, array_keys(Balanced_General::getAllFilters()))) {

					try {
						switch($val) {
							case 'Balanced_Customer-create':
								$balancedCustomer = new Balanced\Customer($fields);
								$balanced = $balancedCustomer->save();
								break;
							case 'Balanced_Customer-create-addCard':
								if( isset($fields['customer_uri']) ) {
									$balancedCustomer = Balanced\Customer::get($fields['customer_uri']);
								}
								else {
									$balancedCustomer = new Balanced\Customer($fields);
								}
								// card_uri generated by balanced.js
								$balancedCustomer->addCard($fields['card_uri']);
								$balanced = $balancedCustomer->save();
								break;
							case 'Balanced_Customer-create-addBankAccount':
								if( isset($fields['customer_uri']) ) {
									$balancedCustomer = Balanced\Customer::get($fields['customer_uri']);
								}
								else {
									$balancedCustomer = new Balanced\Customer($fields);
								}
								// bank_account_uri generated by balanced.js
								$balancedCustomer->addBankAccount($fields['bank_account_uri']);
								$balanced = $balancedCustomer->save();
								break;
							case 'Balanced_Customer-update':
								$balancedCustomer = Balanced\Customer::get($fields['customer_uri']);
								$balancedCustomer = Balanced_General::setBalancedFieldsToUpdate($balancedCustomer, $fields);
								$balanced = $balancedCustomer->save();
								break;
							case 'Balanced_Customer-delete':
								$balancedCustomer = Balanced\Customer::get($fields['customer_uri']);
								$balanced = $balancedCustomer->unstore();
								break;
							case 'Balanced_Customer-addCard':
								$balancedCustomer = Balanced\Customer::get($fields['customer_uri']);
								$balanced = $balancedCustomer->addCard($fields['card_uri']);
								break;
							case 'Balanced_Customer-addBankAccount':
								$balancedCustomer = Balanced\Customer::get($fields['customer_uri']);
								$balanced = $balancedCustomer->addBankAccount($fields['bank_account_uri']);
								$balancedClearBankAccountVerification = true;
								break;
							/*case 'Balanced_Customer-bankAccount-verification-create':
								$balanced = Balanced\Customer::get($fields['customer_uri']);
								$balanced = Balanced\BankAccount::get($balanced['bank_account_uri']);
								$balanced = $balanced->verify();
								break;*/
							case 'Balanced_BankAccount-verification-create':
								$balancedBankAccount = Balanced\BankAccount::get($fields['bank_account_uri']);
								$balanced = $balancedBankAccount->verify();
								$prefix = 'bank_account_verification_';
								break;
							case 'Balanced_BankAccountVerification-update':
								$balancedBankAccountVerification = Balanced\BankAccountVerification::get($fields['bank_account_verification_uri']);
								$balancedBankAccountVerification->amount_1 = $fields['amount_1'];
								$balancedBankAccountVerification->amount_2 = $fields['amount_2'];
								$balanced = $balancedBankAccountVerification->save();
								$prefix = 'bank_account_verification_';
								break;
							case 'Balanced_Debit-create':
								$customerURI = $fields['customer_uri'];
								$onBehalfOfURI = $fields['on_behalf_of_uri'];
								$sourceURI = $fields['source_uri'];

								$balancedCustomer = Balanced\Customer::get($customerURI);

								$appearsText = Symphony::Configuration()->get('appears-on-statement-as', 'balanced');
								if (isset($fields['appears_on_statement_as'])) {
									$appearsText = $fields['appears_on_statement_as'];
								}

								// Add fees if subamount is specified
								if (isset($fields['subamount'])) {
									if (!isset($fields['fees']) || ($fields['fees'] == '')) {
										$fees = Balanced_General::calculateFees($subamount, $debitFeeVariable, $debitFeeFixed);
									}
									$fields['amount'] = $subamount + $fees;
								}

								$balanced = $balancedCustomer->debit(
									$amount = $fields['amount'],
									$appears_on_statement_as = $appearsText,
									$meta = $fields['meta'],
									$description = $fields['description'],
									$source = $sourceURI,
									$on_behalf_of = $onBehalfOfURI
								);
								$prefix = 'debit_';
								$prefixDebit = true;
								break;
							case 'Balanced_Debit-refund':
								$balancedDebit = Balanced\Debit::get($fields['debit_uri']);
								$balanced = $balancedDebit->refund();
								$prefix = 'refund_';
								break;
							case 'Balanced_Credit-create':
								$balancedBankAccount = Balanced\BankAccount::get($fields['bank_account_uri']);

								$appearsText = Symphony::Configuration()->get('appears-on-statement-as', 'balanced');
								if (isset($fields['appears_on_statement_as'])) {
									$appearsText = $fields['appears_on_statement_as'];
								}

								// Add fees if subamount is specified
								if (isset($fields['subamount'])) {
									if (!isset($fields['fees']) || ($fields['fees'] == '')) {
										$fees = Balanced_General::calculateFees($subamount, $creditFeeVariable, $creditFeeFixed);
									}
									$fields['amount'] = $subamount - $fees;
								}

								$balanced = $balancedBankAccount->credit(
									$amount = $fields['amount'],
									$appears_on_statement_as = $appearsText,
									$meta = $fields['meta'],
									$description = $fields['description']
									);
								break;
						}
					} catch (Balanced\Errors\DuplicateAccountEmailAddress $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\InvalidAmount $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\InvalidRoutingNumber $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\InvalidBankAccountNumber $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\Declined $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\CannotAssociateMerchantWithAccount $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\AccountIsAlreadyAMerchant $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\NoFundingSource $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\NoFundingDestination $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\CardAlreadyAssociated $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\CannotAssociateCard $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\BankAccountAlreadyAssociated $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\AddressVerificationFailed $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\MarketplaceAlreadyCreated $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\IdentityVerificationFailed $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\InsufficientFunds $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\CannotHold $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\CannotCredit $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\CannotDebit $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\CannotRefund $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Balanced\Errors\BankAccountVerificationFailure $e) {
						$context['messages'][] = array('balanced', false, $e->response->body->description);
						Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						return $context;
					} catch (Exception $e) {
						//print_r(Balanced_General::convertObjectToArray($e->response)); die();
						//print_r($e); die();

						$errorMessage = $e->getMessage();
						if(isset($e->response)) {
							$errorMessage = $e->response->body->description;
							$errorType = 'balanced';
						}

						$context['messages'][] = array('balanced', false, $errorMessage);

						$errorMessage = $errorMessage . "\n\n" . json_encode($filters);

						if (!isset($errorType)) {
							Balanced_General::emailPrimaryDeveloper($errorMessage);
						}
						else {
							Balanced_General::emailPrimaryDeveloper($e->response->raw_body);
						}
						return $context;
					}
				}
			}

		/*} else {
			$balanced = unserialize($_SESSION['symphony-balanced']);

			// Ensure updated balanced[...] fields replace empty fields
			foreach($balanced as $key => $val) {
				if(empty($val) && isset($_POST['balanced'][$key])) {
					$balanced[$key] = $_POST['balanced'][$key];
				}
			}
		}*/

		if (!empty($balanced)) {
			// Convert balanced object to array so that it can be looped
			if(is_object($balanced)) {
				$balanced = Balanced_General::convertObjectToArray($balanced);

				foreach($balanced as $key => $val){
					if(is_object($val)) {
						$balanced[$key] = Balanced_General::convertObjectToArray($val);
					}
					// rename the underscore-first keys
					if ($key[0] === '_') {
						$newKey = substr($key, 1);
						$balanced[$newKey] = $val;
						unset($balanced[$key]);
					}
				}
			}

			// Convert cents back to dollars
			$balanced['amount'] = Balanced_General::centsToDollars($balanced['amount']);
			$balanced['amount_1'] = Balanced_General::centsToDollars($balanced['amount_1']);
			$balanced['amount_2'] = Balanced_General::centsToDollars($balanced['amount_2']);
			// Convert cents back to dollars and add back to array
			if (isset($subamount)) {
				$balanced['subamount'] = Balanced_General::centsToDollars($subamount);
			}
			else {
				$balanced['subamount'] = $balanced['amount'];
			}
			if (isset($fees)) {
				$balanced['fees'] = Balanced_General::centsToDollars($fees);
			}
			else {
				if (isset($fields['amount'])) {
					$balanced['fees'] = '0.0';
				}
			}

			// Remove the Balanced ID
			if (isset($balanced['id'])) {
				unset($balanced['id']);
			}

			// Prefix response, e.g., bank_verification
			if (isset($prefix)) {
				foreach ($balanced as $key => $value) {
					$balanced[$prefix . $key] = $value;
					unset($balanced[$key]);
				}
			}
			if (isset($prefixDebit) && ($prefixDebit === true)) {
				$balanced['customer_uri'] = $customerURI;
				//$balanced['source_uri'] = $balanced['source']['uri'];
				// Workaround to provide current on_behalf_of_uri
				$balanced['on_behalf_of_uri'] = $onBehalfOfURI;
				$balanced['source_uri'] = $sourceURI;
			}

			// Add values of response for Symphony event to process
			if(is_array($context['fields'])) {
				$context['fields'] = array_merge(Balanced_General::prepareFieldsForSymphony($balanced), $context['fields']);
			} else {
				$context['fields'] = Balanced_General::prepareFieldsForSymphony($balanced);
			}

			// Reset the Bank Account Verification fields if cleared by new Bank Account
			if (isset($balancedClearBankAccountVerification) && ($balancedClearBankAccountVerification === true)) {
				$context['fields']['bank-account-verification-type'] = '';
				$context['fields']['bank-account-verification-created-at'] = '';
				$context['fields']['bank-account-verification-uri'] = '';
				$context['fields']['bank-account-verification-updated-at'] = '';
				$context['fields']['bank-account-verification-state'] = '';
				$context['fields']['bank-account-verification-id'] = '';
				$context['fields']['bank-account-verification-attempts'] = '';
				$context['fields']['bank-account-verification-remaining-attempts'] = '';
			}

			// Create the post data cookie element
			if ( isset($context['post_values']) ) {
				General::array_to_xml($context['post_values'], $balanced, true);
			}
			else {
				$context['post_values'] = new XMLElement('post-values');
				General::array_to_xml($context['post_values'], $balanced, true);
			}

			// Add balanced response to session in case event fails
			$_SESSION['symphony-balanced'] = serialize($balanced);
		}

		return $context;
	}

	public function actionEventPostSaveFilter($context) {
		// Clear session saved response
		unset($_SESSION['symphony-balanced']);
	}

	public function actionAddCustomPreferenceFieldsets($context) {
		// If the Payment Gateway Interface extension is installed, don't
		// double display the preference, unless this function is called from
		// the `pgi-loader` context.
		if (in_array('pgi_loader', Symphony::ExtensionManager()->listInstalledHandles()) xor isset($context['pgi-loader'])) return;

		$fieldset = new XMLElement('fieldset');
		$fieldset->setAttribute('class', 'settings');
		$fieldset->appendChild(new XMLElement('legend', __('Balanced')));

		$div = new XMLElement('div', null);

		// Build the Gateway Mode
		$label = new XMLElement('label', __('Balanced Mode'));
		$options = array(
			array('test', Balanced_General::isTestMode(), __('Test')),
			array('live', !Balanced_General::isTestMode(), __('Live'))
		);

		$label->appendChild(Widget::Select('settings[balanced][gateway-mode]', $options));
		$div->appendChild($label);
		$fieldset->appendChild($div);

		// API Keys group div
		$group = new XMLElement('div', null, array('class' => 'group'));

		// Live Public API Key
		$label = new XMLElement('label', __('Live API key secret'));
		$label->appendChild(
			Widget::Input('settings[balanced][live-api-key]', Symphony::Configuration()->get('live-api-key', 'balanced'))
		);
		$group->appendChild($label);

		// Test Public API Key
		$label = new XMLElement('label', __('Test API key secret'));
		$label->appendChild(
			Widget::Input('settings[balanced][test-api-key]', Symphony::Configuration()->get('test-api-key', 'balanced'))
		);
		$group->appendChild($label);

		$fieldset->appendChild($group);

		// Marketplace URIs group div
		$group = new XMLElement('div', null, array('class' => 'group'));

		// Live Marketplace URI
		$label = new XMLElement('label', __('Live Marketplace URI'));
		$label->appendChild(
			Widget::Input('settings[balanced][live-marketplace-uri]', Symphony::Configuration()->get('live-marketplace-uri', 'balanced'))
		);
		$group->appendChild($label);

		// Test Marketplace URI
		$label = new XMLElement('label', __('Test Marketplace URI'));
		$label->appendChild(
			Widget::Input('settings[balanced][test-marketplace-uri]', Symphony::Configuration()->get('test-marketplace-uri', 'balanced'))
		);
		$group->appendChild($label);

		$fieldset->appendChild($group);

		// Appears On Statement As
		$div = new XMLElement('div', null);
		$label = new XMLElement('label', __('Appears On Statement As (18 characters max)'));
		$label->appendChild(
			Widget::Input('settings[balanced][appears-on-statement-as]', Symphony::Configuration()->get('appears-on-statement-as', 'balanced'))
		);
		$div->appendChild($label);
		$fieldset->appendChild($div);

		// Debit fees group div
		$group = new XMLElement('div', null, array('class' => 'group'));

		// Debit variable fee
		$label = new XMLElement('label', __('Debit variable fee (in percent, e.g., 5%)'));
		$label->appendChild(
			Widget::Input('settings[balanced][debit-fee-variable]', Symphony::Configuration()->get('debit-fee-variable', 'balanced'))
		);
		$group->appendChild($label);

		// Debit fixed fee
		$label = new XMLElement('label', __('Debit fixed fee (in dollars, e.g., 1.50)'));
		$label->appendChild(
			Widget::Input('settings[balanced][debit-fee-fixed]', Symphony::Configuration()->get('debit-fee-fixed', 'balanced'))
		);
		$group->appendChild($label);

		$fieldset->appendChild($group);

		// Credit fees group div
		$group = new XMLElement('div', null, array('class' => 'group'));

		// Credit variable fee
		$label = new XMLElement('label', __('Credit variable fee (in percent, e.g., 5%)'));
		$label->appendChild(
			Widget::Input('settings[balanced][credit-fee-variable]', Symphony::Configuration()->get('credit-fee-variable', 'balanced'))
		);
		$group->appendChild($label);

		// Credit fixed fee
		$label = new XMLElement('label', __('Credit fixed fee (in dollars, e.g., 1.50)'));
		$label->appendChild(
			Widget::Input('settings[balanced][credit-fee-fixed]', Symphony::Configuration()->get('credit-fee-fixed', 'balanced'))
		);
		$group->appendChild($label);

		$fieldset->appendChild($group);

		$context['wrapper']->appendChild($fieldset);
	}

	public function actionSave($context) {
		$settings = $context['settings'];

		Symphony::Configuration()->set('gateway-mode', $settings['balanced']['gateway-mode'], 'balanced');
		Symphony::Configuration()->set('live-api-key', $settings['balanced']['live-api-key'], 'balanced');
		Symphony::Configuration()->set('test-api-key', $settings['balanced']['test-api-key'], 'balanced');
		Symphony::Configuration()->set('live-marketplace-uri', $settings['balanced']['live-marketplace-uri'], 'balanced');
		Symphony::Configuration()->set('test-marketplace-uri', $settings['balanced']['test-marketplace-uri'], 'balanced');
		Symphony::Configuration()->set('appears-on-statement-as', $settings['balanced']['appears-on-statement-as'], 'balanced');
		Symphony::Configuration()->set('debit-fee-variable', $settings['balanced']['debit-ee-variable'], 'balanced');
		Symphony::Configuration()->set('debit-fee-fixed', $settings['balanced']['debit-fee-fixed'], 'balanced');
		Symphony::Configuration()->set('credit-fee-variable', $settings['balanced']['credit-ee-variable'], 'balanced');
		Symphony::Configuration()->set('credit-fee-fixed', $settings['balanced']['credit-fee-fixed'], 'balanced');

		return Symphony::Configuration()->write();
	}

	public function install() {
		// Create balanced_customer_uri field database:
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_fields_balanced_customer_uri` (
			 `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
			  `field_id` INT(11) unsigned NOT NULL,
			  `validator` VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
			  `disabled` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes',
			  PRIMARY KEY (`id`),
			  KEY `field_id` (`field_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
		");

		// Create balanced_customer_link field database:
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_fields_balanced_customer_link` (
			  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
			  `field_id` INT(11) unsigned NOT NULL,
			  `related_field_id` VARCHAR(255) NOT NULL,
			  `show_association` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes',
			  `disabled` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes',
			  PRIMARY KEY (`id`),
			  KEY `field_id` (`field_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
		");

		// Create balanced_resource_uri
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_fields_balanced_resource_uri` (
			 `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
			  `field_id` INT(11) unsigned NOT NULL,
			  `validator` VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
			  `disabled` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes',
			  PRIMARY KEY (`id`),
			  KEY `field_id` (`field_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
		");

		// Create balanced_resource_link field database:
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_fields_balanced_resource_link` (
			  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
			  `field_id` INT(11) unsigned NOT NULL,
			  `related_field_id` VARCHAR(255) NOT NULL,
			  `show_association` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes',
			  `disabled` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes',
			  PRIMARY KEY (`id`),
			  KEY `field_id` (`field_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
		");
	}

	public function uninstall() {
		// Drop field tables:
		Symphony::Database()->query("DROP TABLE `tbl_fields_balanced_customer_uri`");
		Symphony::Database()->query("DROP TABLE `tbl_fields_balanced_customer_link`");
		Symphony::Database()->query("DROP TABLE `tbl_fields_balanced_resource_uri`");
		Symphony::Database()->query("DROP TABLE `tbl_fields_balanced_resource_link`");

		// Clean configuration
		Symphony::Configuration()->remove('gateway-mode', 'balanced');
		Symphony::Configuration()->remove('live-api-key', 'balanced');
		Symphony::Configuration()->remove('test-api-key', 'balanced');
		Symphony::Configuration()->remove('live-marketplace-uri', 'balanced');
		Symphony::Configuration()->remove('test-marketplace-uri', 'balanced');
		Symphony::Configuration()->remove('appears-on-statement-as', 'balanced');
		Symphony::Configuration()->remove('debit-fee-variable', 'balanced');
		Symphony::Configuration()->remove('debit-fee-fixed', 'balanced');
		Symphony::Configuration()->remove('credit-fee-variable', 'balanced');
		Symphony::Configuration()->remove('credit-fee-fixed', 'balanced');

		return Symphony::Configuration()->write();
	}

	public function actionAppendAppParams($context) {
		// Add marketplace URI to Symphony page params.
		$context['params']['balanced-marketplace-uri'] = Balanced_General::getMarketplaceUri();
	}

}
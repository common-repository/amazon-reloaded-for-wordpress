<?php
/*
 Plugin Name: Amazon Reloaded for WordPress
 Author: Nick Ohrn of Plugin-Developer.com
 Author URI: http://plugin-developer.com
 Plugin URI: http://plugin-developer.com/portfolio/amazon-reloaded-for-wordpress/
 Description: Quickly and easily insert links to and images of products from Amazon.com using this plugin's intuitive search capabilities.
 Version: 5.0.8
 */

if (!class_exists('Amazon_Reloaded_For_WordPress')) {
	class Amazon_Reloaded_For_WordPress {

		var $settings = null;
		var $_optionName = 'Amazon Reloaded for WordPress Settings';
		var $locales = array('com', 'co.uk', 'ca', 'fr', 'de', 'jp');
		var $version = '5.0.8';

		function Amazon_Reloaded_For_WordPress() {
			$this->__construct();
		}

		function __construct() {
			$this->addActions();
			$this->addFilters();
		}

		function addActions() {
			add_action('admin_menu', array($this, 'addAdministrativeInterfaceItems'));
			add_action('admin_notices', array($this, 'addAdministrativeWarnings'));
			add_action('admin_init', array($this, 'checkForSettingsSave'));
			add_action('wp_ajax_arfw', array($this, 'handleAjaxSearchRequest'));
		}

		function addFilters() {

		}

		function addAdministrativeInterfaceItems() {
			add_options_page(__('Amazon Reloaded'), __('Amazon Reloaded'), 'manage_options', 'amazon-reloaded', array($this, 'displaySettingsPage'));
			add_meta_box('amazon-reloaded-for-wordpress', __('Amazon Reloaded'), array($this, 'displayMetaBox'), 'post');
			add_meta_box('amazon-reloaded-for-wordpress', __('Amazon Reloaded'), array($this, 'displayMetaBox'), 'page');

			if ($this->isPageOrPostInterface()) {
				wp_enqueue_script('arfw', plugins_url('resources/amazon-reloaded.js', __FILE__), array('jquery'), $this->version);
				wp_enqueue_style('arfw', plugins_url('resources/amazon-reloaded.css', __FILE__), array(), $this->version);
			}
		}

		function addAdministrativeWarnings() {
			if ($this->isPageOrPostInterface() && $this->credentialsAreEmpty()) {
				echo '<div id="amazon-credentials-setup" class="updated fade"><p>'.sprintf(__('You have not yet set up your Amazon credentials.  In order to search for products using the Amazon Reloaded for WordPress plugin, you should do so <a href="%1$s">now</a>.'), admin_url('options-general.php?page=amazon-reloaded')).'</p></div>';
			}
		}

		function isPageOrPostInterface() {
			global $pagenow;
			return (false !== strpos($pagenow, 'post') || false !== strpos($pagenow, 'page'));
		}

		function handleAjaxSearchRequest() {
			$data = $_POST;
			$terms = $data['terms'];
			$index = $data['index'];
			$url = $this->signUrl($this->getAmazonProductSearchRequestUrl($terms, $index));

			$response = wp_remote_get($url, array('timeout'=>30));
			if( is_wp_error($response) ) {
				$encodable = array('success'=>false);
			} else {
				$responseDocument = DOMDocument::loadXML($response['body']);

				$errorFields = $responseDocument->getElementsByTagName('Error');
				if ($errorFields->length == 0) {
					// Normal Case
					$encodable = array();
					$encodable['success'] = true;
					$items = array();
					foreach ($responseDocument->getElementsByTagName('Item') as $item) {
						$asin = $this->getFirstElementValueForTagName($item, 'ASIN');
						$name = $this->getFirstElementValueForTagName($item, 'Title');
						$detailPageUrl = $this->getFirstElementValueForTagName($item, 'DetailPageURL');

                                                $itemlinks = $item->getElementsByTagName('ItemLinks')->item(0);
                                                $itemlinks->parentNode->removeChild($itemlinks);
                                                
						$imageUrls = array();
						$images = $item->getElementsByTagName('URL');
						for ($imageNumber = 0; $imageNumber < $images->length; $imageNumber++) {
							$imageUrls[] = $images->item($imageNumber)->nodeValue;
						}
						$item = array('asin'=>$asin, 'name'=>$name, 'detailPageURL'=>$detailPageUrl, 'imageURLs'=>$imageUrls);
						$items[] = $item;
					}
					$encodable['items'] = $items;
				} else {
					$encodable = array('success'=>false);
				}
			}
			print json_encode($encodable);
			exit();
		}

		function getFirstElementValueForTagName($document, $tagName) {
			$list = $document->getElementsByTagName($tagName);
			if ($list->length == 0) {
				return '';
			} else {
				return $list->item(0)->nodeValue;
			}
		}

		function checkForSettingsSave() {
			if (isset($_POST['save-arfw-settings']) && current_user_can('manage_options') && check_admin_referer('save-arfw-settings')) {
				$this->saveSettingsFromPost();
				wp_redirect(admin_url('options-general.php?page=amazon-reloaded&updated=1'));
				exit();
			}

			if (isset($_POST['test-arfw-settings']) && current_user_can('manage_options') && check_admin_referer('test-arfw-settings','_wpnonce2')) {
				$this->saveSettingsFromPost();
					
				$redirectUrl = admin_url('options-general.php?page=amazon-reloaded');
				if ($this->credentialsAreValid()) {
					$redirectUrl = add_query_arg(array('credentials-success'=>1), $redirectUrl);
				} else {
					$redirectUrl = add_query_arg(array('credentials-failure'=>1), $redirectUrl);
				}
				wp_redirect($redirectUrl);
				exit();
			}
		}

		function saveSettingsFromPost() {
			$settings = $this->getSettings();

			$settings['amazon-secret-key'] = trim(stripslashes($_POST['amazon-secret-key']));
			$settings['amazon-api-key'] = trim(stripslashes($_POST['amazon-api-key']));
			$settings['amazon-associates-id'] = trim(stripslashes($_POST['amazon-associates-id']));
			$settings['amazon-locale'] = in_array($_POST['amazon-locale'], $this->locales) ? $_POST['amazon-locale'] : 'com';

			$this->saveSettings($settings);
		}

		/// DISPLAY

		function displayMetaBox() {
			include ('views/meta-box.php');
		}

		function displaySettingsPage() {
			include ('views/settings.php');
		}

		/// SETTINGS

		function getSettings() {
			if (null === $this->settings) {
				$this->settings = get_option($this->_optionName, array());
			}
			return $this->settings;
		}

		function saveSettings($settings) {
			if (!is_array($settings)) {
				return;
			}
			update_option($this->_optionName, $settings);
		}

		function deleteSettings() {
			delete_option($this->_optionName);
		}

		/// AMAZON

		// Thanks Brandon Checketts
		function signUrl($urlToSign) {
			$settings = $this->getSettings();
			$originalUrl = $urlToSign;

			// Decode anything already encoded
			$url = urldecode($urlToSign);

			// Parse the URL into $urlparts
			$urlparts = parse_url($url);

			// Build $params with each name/value pair

			foreach (explode('&', $urlparts['query']) as $part) {
				if (strpos($part, '=')) {
					list($name, $value) = explode('=', $part);
				} else {
					$name = $part;
					$value = '';
				}
				$params[$name] = $value;
			}

			// Include a timestamp if none was provided
			if ( empty($params['Timestamp'])) {
				$params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
			}

			// Sort the array by key
			ksort($params);

			// Build the canonical query string
			$canonical = '';
			foreach ($params as $key=>$val) {
				$canonical .= "{$key}=".rawurlencode($val).'&';
			}
			// Remove the trailing ampersand
			$canonical = preg_replace("/&$/", '', $canonical);

			// Some common replacements and ones that Amazon specifically mentions
			$canonical = str_replace(array(' ', '+', ',', ';'), array('%20', '%20', urlencode(','), urlencode(':')), $canonical);

			// Build the si
			$string_to_sign = "GET\n{$urlparts['host']}\n{$urlparts['path']}\n$canonical";

			// Calculate our actual signature and base64 encode it
			$signature = base64_encode(hash_hmac('sha256', $string_to_sign, $settings['amazon-secret-key'], true));

			// Finally re-build the URL with the proper string and include the Signature
			$url = "{$urlparts['scheme']}://{$urlparts['host']}{$urlparts['path']}?$canonical&Signature=".rawurlencode($signature);
			return $url;
		}

		function credentialsAreEmpty() {
			$settings = $this->getSettings();
			return ( empty($settings['amazon-api-key']) || empty($settings['amazon-secret-key']));
		}

		function credentialsAreValid() {
			if ($this->credentialsAreEmpty()) {
				return false;
			}
			$url = $this->getAmazonHelpRequestUrl();
			$url = $this->signUrl($url);
			$response = wp_remote_get($url);

			if( !is_wp_error($response) ) {
				$responseDocument = DOMDocument::loadXML($response['body']);
				$errorFields = $responseDocument->getElementsByTagName('Error');
				return $errorFields->length == 0;
			} else {
				return false;
			}
		}

		function getAmazonProductSearchRequestUrl($terms, $index) {
			$settings = $this->getSettings();
			$url = "http://webservices.amazon.{$settings['amazon-locale']}/onca/xml?";
			$url .= 'Service=AWSECommerceService&';
                        $url .= 'Version=2011-08-01&';
			$url .= 'AssociateTag=' . urlencode( $settings[ 'amazon-associates-id'] ) . '&';
			$url .= "AWSAccessKeyId={$settings['amazon-api-key']}&";
			$url .= 'Operation=ItemSearch&';
			$url .= 'Keywords='.urlencode(str_replace(' ', '%20', $terms)).'&';
			$url .= 'SearchIndex='.urlencode($index).'&';
			$url .= 'ResponseGroup=Small,Images';
			return $url;
		}

		function getAmazonHelpRequestUrl() {
			$settings = $this->getSettings();
			return 'http://webservices.amazon.'.$settings['amazon-locale'].'/onca/xml?Service=AWSECommerceService&AWSAccessKeyId='.$settings['amazon-api-key'].'&Operation=BrowseNodeLookup&BrowseNodeId=163357';
		}
	}

	$arfw = new Amazon_Reloaded_For_WordPress;
}

?>

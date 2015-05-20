<?php

GFForms::include_feed_addon_framework();

class GFBitrix24 extends GFFeedAddOn {

	protected $_version = GF_BITRIX24_VERSION;
	protected $_min_gravityforms_version = '1.9.9';
	protected $_slug = 'gravityformsbitrix24';
	protected $_path = 'gravityformsbitrix24/bitrix24.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms Bitrix24 Add-On';
	protected $_short_title = 'Bitrix24';

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_bitrix24', 'gravityforms_bitrix24_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_bitrix24';
	protected $_capabilities_form_settings = 'gravityforms_bitrix24';
	protected $_capabilities_uninstall = 'gravityforms_bitrix24_uninstall';
	protected $_enable_rg_autoupgrade = true;

	private static $api;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFBitrix24();
		}

		return self::$_instance;
	}

	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => __( 'Subscribe user to Bitrix24 only when payment is received.', 'gravityformsbitrix24' )
			)
		);

		add_filter( 'gform_addon_navigation', array( $this, 'maybe_create_menu' ) );

		if (isset($_GET['member_id'])) {
			$this->oauth_login();
		}
	}

	public function init_ajax() {
		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_bitrix24_menu', array( $this, 'ajax_dismiss_menu' ) );
	}

	protected function refresh_token()
	{
		return $this->oauth_login(true);
	}

	/**
	 * @param bool $need_refresh_token
	 *
	 * @return array|null
	 * @throws \OAuth\OAuth2\Service\Exception\MissingRefreshTokenException
	 */
	protected function oauth_login($need_refresh_token = false)
	{
		$settings = $this->get_plugin_settings();
		if (!$settings) $settings = $this->get_current_settings();


		$redirect_url = get_admin_url(null, 'admin.php?page=gf_settings&subview=gravityformsbitrix24');
		/** @var $serviceFactory \OAuth\ServiceFactory An OAuth service factory. */
		$serviceFactory = new \OAuth\ServiceFactory();
		// Session storage
		$storage = new \OAuth\Common\Storage\Memory();
		// Setup the credentials for the requests
		$credentials = new \OAuth\Common\Consumer\Credentials(
			$settings['clientId'],
			$settings['clientSecret'],
			$redirect_url
		);

		/** @var $provider \OAuth\OAuth2\Service\Bitrix24 */
		$provider = $serviceFactory->createService('Bitrix24', $credentials, $storage, array('crm'), new \OAuth\Common\Http\Uri\Uri('https://'.$settings['domain']));

		if ($need_refresh_token) {
			$token = new OAuth\OAuth2\Token\StdOAuth2Token();
			$token->setAccessToken($settings['authId']);
			$token->setRefreshToken($settings['refreshId']);
			$token = $provider->refreshAccessToken($token);

			$settings['authId'] = $token->getAccessToken();
			$settings['refreshId'] = $token->getRefreshToken();
			$settings['endOfLife'] = $token->getEndOfLife();

			$this->update_plugin_settings($settings);

			self::$api = null;

			return $settings;
		}

		if (!isset($_GET['code']) && empty($settings['oauthCode'])) {
			echo '<div class="error" style="padding: 10px; font-size: 12px;">' . __( 'Please enter the code', 'gravityformsbitrix24' ) . '</div>';
			echo '<script>window.open("'.$provider->getAuthorizationUri().'", "_blank", "width=300, height=200");
			</script>';

		} else {
			$code = isset($_GET['code']) ? $_GET['code'] : $settings['oauthCode'];
			try {
				$token = $provider->requestAccessToken($code);

				$extra_params = $token->getExtraParams();
				$settings['memberId'] = $extra_params['member_id'];
				$settings['authId'] = $token->getAccessToken();
				$settings['refreshId'] = $token->getRefreshToken();
				$settings['endOfLife'] = $token->getEndOfLife();
				unset($settings['oauthCode']);

				$this->update_plugin_settings($settings);

				if (isset($_GET['code'])) wp_redirect($redirect_url);
			}
			catch (\Exception $ex) {
				$markup = '<div class="error" style="padding: 10px; font-size: 12px;">' . __( 'Oauth code is not right.', 'gravityformsbitrix24' ) . '</div>';

				echo $markup;
			}
		}
	}

	protected function maybe_save_plugin_settings()
	{
		parent::maybe_save_plugin_settings();

		if( $this->is_save_postback() ) {
			$this->oauth_login();
		}
	}

	// ------- Plugin settings -------

	public function plugin_settings_fields() {
		$data = array(
			array(
				'title'       => __( 'Bitrix24 Account Information', 'gravityformsbitrix24' ),
				'description' => sprintf(
					__( 'Bitrix24 makes it easy create leads. Use Gravity Forms to collect customer information and automatically add them to your Bitrix24 leads list. If you don\'t have a Bitrix24 account, you can %1$s sign up for one here.%2$s',
						'gravityformsbitrix24' ),
					'<a href="http://www.bitrix24.com/" target="_blank">',
					'</a>'
				),
				'fields'      => array(
					array(
						'name'              => 'domain',
						'label'             => __( 'Bitrix24 domain', 'gravityformsbitrix24' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_settings' )
					),
					array(
						'name'              => 'clientId',
						'label'             => __( 'Bitrix24 app client id', 'gravityformsbitrix24' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_settings' )
					),
					array(
						'name'              => 'clientSecret',
						'label'             => __( 'Bitrix24 app client secret', 'gravityformsbitrix24' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_settings' )
					),
				)
			),
		);

		if ($this->is_save_postback()) {
			$data[0]['fields'][] = array(
				'name'              => 'oauthCode',
				'label'             => __( 'Bitrix24 oauth code', 'gravityformsbitrix24' ),
				'type'              => 'text',
				'class'             => 'medium',
				'feedback_callback' => array( $this, 'is_valid_settings' )
			);
		}

		return $data;
	}

	/**
	 * @return bool
	 */
	public function is_valid_settings() {
		$settings = $this->get_plugin_settings();

		return isset($settings['refreshId']);
	}

	public static function get_field_map_choices( $form_id, $field_type = null, $exclude_field_types = null ) {

		$fields = parent::get_field_map_choices( $form_id, $field_type, $exclude_field_types );

		// Adding default fields
		$fields[] = array( "value" => "geoip_country" , "label" => __("Country (from Geo IP)", "gravityforms") );
		$fields[] = array( "value" => "geoip_city" , "label" => __("City (from Geo IP)", "gravityforms") );

		return $fields;
	}

	public function feed_settings_fields() {
		return array(
			array(
				'title'       => __( 'Bitrix24 Feed Settings', 'gravityformsbitrix24' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => __( 'Name', 'gravityformsbitrix24' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => '<h6>' . __( 'Name',
								'gravityformsbitrix24' ) . '</h6>' . __( 'Enter a feed name to uniquely identify this setup.',
								'gravityformsbitrix24' )
					),
				)
			),
			array(
				'title'       => '',
				'description' => '',
				'fields'      => array(
					array(
						'name'      => 'mappedFields',
						'label'     => __( 'Map Fields', 'gravityformsbitrix24' ),
						'type'      => 'field_map',
						'field_map' => $this->merge_vars_field_map(),
						'tooltip'   => '<h6>' . __( 'Map Fields',
								'gravityformsbitrix24' ) . '</h6>' . __( 'Associate your Bitrix24 variables to the appropriate Gravity Form fields by selecting.',
								'gravityformsbitrix24' ),
					),
					array( 'type' => 'save' ),
				)
			),
		);
	}

	public function checkbox_input_double_optin( $choice, $attributes, $value, $tooltip ) {
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		if ( $value ) {
			$display = 'none';
		} else {
			$display = 'block-inline';
		}

		$markup .= '<span id="bitrix24_doubleoptin_warning" style="padding-left: 10px; font-size: 10px; display:' . $display . '">(' . __( 'Abusing this may cause your Bitrix24 account to be suspended.',
				'gravityformsbitrix24' ) . ')</span>';

		return $markup;
	}

	//-------- Form Settings ---------
	public function feed_edit_page( $form, $feed_id ) {
		// getting Bitrix24 API
		$api = $this->get_api();

		// ensures valid credentials were entered in the settings page
		if ( ! $api ) {
			?>
			<div><?php echo sprintf(
					__( 'We are unable to login to Bitrix24 with the provided credentials. Please make sure they are valid in the %sSettings Page%s',
						'gravityformsbitrix24' ),
					"<a href='" . $this->get_plugin_settings_url() . "'>",
					'</a>'
				); ?>
			</div>

			<?php
			return;
		}

		echo '<script type="text/javascript">var form = ' . GFCommon::json_encode( $form ) . ';</script>';

		parent::feed_edit_page( $form, $feed_id );
	}

	public function feed_list_columns() {
		return array(
			'feedName' => __( 'Name', 'gravityformsbitrix24' )
		);
	}

	/**
	 * @return array
	 */
	public function merge_vars_field_map() {
		$api = $this->get_api();

		try {
			$result = $api->fields();
		}
		catch (\Bitrix24\Bitrix24ApiException $ex) {
			$this->refresh_token();
			$api = $this->get_api();
			$result = $api->fields();
		}

		$fields = array();
		foreach ($result['result'] as $fieldKey => $field) {
			if ( $field['isReadOnly'] ) continue;

			$label = $fieldKey;
			if (isset($field['formLabel']) && $field['formLabel']) {
				$label = $field['formLabel'];
			}
			elseif (isset($field['placeholder']) && $field['placeholder']) {
				$label = $field['placeholder'];
			}

			$fields[] = array(
				'name'     => $fieldKey,
				'label'    => __( $label, 'gravityformsbitrix24' ),
				'required' => $field['isRequired'],
			);
		}

		return $fields;
	}

	//------ Core Functionality ------

	public function process_feed( $feed, $entry, $form ) {
		$geoip_data = array();

		$this->log_debug( 'Processing feed.' );

		// login to Bitrix24
		$api = $this->get_api();
		if ( ! is_object( $api ) ) {
			$this->log_error( 'Failed to set up the API' );

			return null;
		}

		// retrieve name => value pairs for all fields mapped in the 'mappedFields' field map
		$field_map = $this->get_field_map_fields( $feed, 'mappedFields' );

		$merge_vars = array();
		foreach ( $field_map as $name => $field_id ) {
			// $field_id can also be a string like 'date_created'
			switch ( strtolower( $field_id ) ) {
				case 'geoip_country':
				case 'geoip_city':
					if (function_exists('geoip_detect_get_info_from_ip')) {
						if (!$geoip_data) $geoip_data = geoip_detect_get_info_from_ip(@$_SERVER['REMOTE_ADDR']);
						$geoip_field_key = $field_id == 'geoip_country' ? 'country_name' : 'city';
						$merge_vars[ $name ] = $geoip_data->$geoip_field_key;
					}
					break;
				case 'form_title':
					$merge_vars[ $name ] = rgar( $form, 'title' );
					break;

				case 'date_created':
				case 'ip':
				case 'source_url':
					$merge_vars[ $name ] = rgar( $entry, strtolower( $field_id ) );
					break;

				default :
					$field       = RGFormsModel::get_field( $form, $field_id );
					$is_integer  = $field_id == intval( $field_id );
					$input_type  = RGFormsModel::get_input_type( $field );
					$field_value = rgar( $entry, $field_id );

					if ( in_array($name, array('EMAIL', 'PHONE', 'WEB', 'IM')) ) {
						$merge_vars[ $name ] = array(array('VALUE' => $field_value, 'VALUE_TYPE' => 'OTHER'));
					}
					// handling full address
					else if ( $is_integer && $input_type == 'address' ) {
						$merge_vars[ $name ] = $this->get_address( $entry, $field_id );
					} // handling full name
					else if ( $is_integer && $input_type == 'name' ) {
						$merge_vars[ $name ] = $this->get_name( $entry, $field_id );
					} // handling phone
					else if ( $is_integer && $input_type == 'phone' && $field['phoneFormat'] == 'standard' ) {
						// reformat phone to go to bitrix24 when standard format (US/CAN)
						// needs to be in the format NPA-NXX-LINE 404-555-1212 when US/CAN
						$phone = $field_value;
						if ( preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $phone, $matches ) ) {
							$phone = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
						}

						$merge_vars[ $name ] = $phone;
					} // send selected checkboxes as a concatenated string
					else if ( $is_integer && RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
						$selected = array();
						foreach ( $field['inputs'] as $input ) {
							$index = (string) $input['id'];
							if ( ! rgempty( $index, $entry ) ) {
								$selected[] = apply_filters( 'gform_bitrix24_field_value', rgar( $entry, $index ), $form['id'], $field_id, $entry );
							}
						}
						$merge_vars[ $name ] = join( ', ', $selected );
					} // handle all other bitrix24 fields
					else {
						$merge_vars[ $name ] = apply_filters( 'gform_bitrix24_field_value', $field_value, $form['id'], $field_id, $entry );
					}
			}
		}

		try {
			$response = $api->add($merge_vars);
		}
		catch (\Bitrix24\Bitrix24ApiException $ex) {
			$this->refresh_token();
			$api = $this->get_api();
			$response = $api->add($merge_vars);
		}

		return $response['result'] > 0;
	}

	//------- Helpers ----------------

	private function get_api() {
		if ( self::$api ) {
			return self::$api;
		}

		$settings = $this->get_plugin_settings();
		$api      = null;

		$require_vars = array('domain', 'clientId', 'clientSecret', 'memberId', 'authId', 'refreshId', 'endOfLife');

		foreach ($require_vars as $require_var) {
			if (empty($settings[$require_var])) {
				$this->oauth_login();
				return false;
			}
		}

		if ($settings['endOfLife'] < time()){
			$settings = $this->refresh_token();
		}

		if ( ! empty( $settings['domain'] ) && ! empty( $settings['clientId'] ) && ! empty( $settings['clientSecret'] ) ) {
			// init lib
			$obB24App = new \Bitrix24\Bitrix24();
			$obB24App->setApplicationScope( array( 'crm' ) );
			$obB24App->setApplicationId( $settings['clientId'] );
			$obB24App->setApplicationSecret( $settings['clientSecret'] );

			// set user-specific settings
			$obB24App->setDomain( $settings['domain'] );
			$obB24App->setMemberId( $settings['memberId'] );
			$obB24App->setAccessToken( $settings['authId'] );
			$obB24App->setRefreshToken( $settings['refreshId'] );

			$this->log_debug( 'Retrieving API Info' );

			try {
				$api = new \Bitrix24\CRM\Lead( $obB24App );
			} catch ( Exception $e ) {
				$this->log_error( 'Failed to set up the API' );
				$this->log_error( $e->getCode() . ' - ' . $e->getMessage() );

				$this->oauth_login();

				return null;
			}
		} else {
			$this->log_debug( 'API credentials not set' );

			return null;
		}

		if ( ! is_object( $api ) ) {
			$this->log_error( 'Failed to set up the API' );

			return null;
		}

		$this->log_debug( 'Successful API response received' );
		self::$api = $api;

		return self::$api;
	}

	private function get_address( $entry, $field_id ) {
		$street_value  = str_replace( '  ', ' ', trim( $entry[ $field_id . '.1' ] ) );
		$street2_value = str_replace( '  ', ' ', trim( $entry[ $field_id . '.2' ] ) );
		$city_value    = str_replace( '  ', ' ', trim( $entry[ $field_id . '.3' ] ) );
		$state_value   = str_replace( '  ', ' ', trim( $entry[ $field_id . '.4' ] ) );
		$zip_value     = trim( $entry[ $field_id . '.5' ] );
		$country_value = GFCommon::get_country_code( trim( $entry[ $field_id . '.6' ] ) );

		$address = $street_value;
		$address .= ! empty( $address ) && ! empty( $street2_value ) ? '  ' . $street2_value : $street2_value;
		$address .= ! empty( $address ) && ( ! empty( $city_value ) || ! empty( $state_value ) ) ? '  ' . $city_value : $city_value;
		$address .= ! empty( $address ) && ! empty( $city_value ) && ! empty( $state_value ) ? '  ' . $state_value : $state_value;
		$address .= ! empty( $address ) && ! empty( $zip_value ) ? '  ' . $zip_value : $zip_value;
		$address .= ! empty( $address ) && ! empty( $country_value ) ? '  ' . $country_value : $country_value;

		return $address;
	}

	private function get_name( $entry, $field_id ) {
		//If field is simple (one input), simply return full content
		$name = rgar( $entry, $field_id );
		if ( ! empty( $name ) ) {
			return $name;
		}

		//Complex field (multiple inputs). Join all pieces and create name
		$prefix = trim( rgar( $entry, $field_id . '.2' ) );
		$first  = trim( rgar( $entry, $field_id . '.3' ) );
		$last   = trim( rgar( $entry, $field_id . '.6' ) );
		$suffix = trim( rgar( $entry, $field_id . '.8' ) );

		$name = $prefix;
		$name .= ! empty( $name ) && ! empty( $first ) ? ' ' . $first : $first;
		$name .= ! empty( $name ) && ! empty( $last ) ? ' ' . $last : $last;
		$name .= ! empty( $name ) && ! empty( $suffix ) ? ' ' . $suffix : $suffix;

		return $name;
	}

	//------ Temporary Notice for Main Menu --------------------//

	public function maybe_create_menu( $menus ) {
		$current_user          = wp_get_current_user();
		$dismiss_bitrix24_menu = get_metadata( 'user', $current_user->ID, 'dismiss_bitrix24_menu', true );
		if ( $dismiss_bitrix24_menu != '1' ) {
			$menus[] = array(
				'name'       => $this->_slug,
				'label'      => $this->get_short_title(),
				'callback'   => array( $this, 'temporary_plugin_page' ),
				'permission' => $this->_capabilities_form_settings
			);
		}

		return $menus;
	}

	public function ajax_dismiss_menu() {

		$current_user = wp_get_current_user();
		update_metadata( 'user', $current_user->ID, 'dismiss_bitrix24_menu', '1' );
	}

	public function temporary_plugin_page() {
		$current_user = wp_get_current_user();
		?>
		<script type="text/javascript">
			function dismissMenu() {
				jQuery('#gf_spinner').show();
				jQuery.post(ajaxurl, {
						action: "gf_dismiss_bitrix24_menu"
					},
					function (response) {
						document.location.href = '?page=gf_edit_forms';
						jQuery('#gf_spinner').hide();
					}
				);

			}
		</script>

		<div class="wrap about-wrap">
			<h1><?php _e( 'Bitrix24 Add-On v3.0', 'gravityformsbitrix24' ) ?></h1>

			<div
				class="about-text"><?php _e( 'Thank you for updating! The new version of the Gravity Forms Bitrix24 Add-On makes changes to how you manage your Bitrix24 integration.',
					'gravityformsbitrix24' ) ?></div>
			<div class="changelog">
				<hr/>
				<div class="feature-section col two-col">
					<div class="col-1">
						<h3><?php _e( 'Manage Bitrix24 Contextually', 'gravityformsbitrix24' ) ?></h3>

						<p><?php _e( 'Bitrix24 Feeds are now accessed via the Bitrix24 sub-menu within the Form Settings for the Form with which you would like to integrate Bitrix24.',
								'gravityformsbitrix24' ) ?></p>
					</div>
					<div class="col-2 last-feature">
						<img src="http://gravityforms.s3.amazonaws.com/webimages/AddonNotice/NewBitrix243.png">
					</div>
				</div>

				<hr/>

				<form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
					<input type="checkbox" name="dismiss_bitrix24_menu" value="1" onclick="dismissMenu();">
					<label><?php _e( 'I understand this change, dismiss this message!', 'gravityformsbitrix24' ) ?></label>
					<img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif' ?>"
					     alt="<?php _e( 'Please wait...', 'gravityformsbitrix24' ) ?>" style="display:none;"/>
				</form>

			</div>
		</div>
	<?php
	}
}

<?php

class WP_AutoUpdate 
{
	/**
	 * The plugin current version
	 * @var string
	 */
	private $current_version;

	/**
	 * The plugin remote update path
	 * @var string
	 */
	private $update_path;

	/**
	 * Plugin Slug (plugin_directory/plugin_file.php)
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin name (plugin_file)
	 * @var string
	 */
	private $slug;

	/**
	 * License User
	 * @var string
	 */
	private $license_user;

	/**
	 * License Key 
	 * @var string
	 */
	private $license_key;

	/**
	 * Initialize a new instance of the WordPress Auto-Update class
	 * @param string $current_version
	 * @param string $update_path
	 * @param string $plugin_slug
	 */
	public function __construct( $current_version, $update_path, $plugin_slug, $license_user = '', $license_key = '' )
	{
		// Set the class public variables
		$this->current_version = $current_version;
		$this->update_path = $update_path;

		// Set the License
		$this->license_user = $license_user;
		$this->license_key = $license_key;

		// Set the Plugin Slug	
		$this->plugin_slug = $plugin_slug;
		list ($t1, $t2) = explode( '/', $plugin_slug );
		$this->slug = str_replace( '.php', '', $t2 );		

		// define the alternative API for updating checking
		add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'check_update' ) );

		// Define the alternative response for information checking
		add_filter( 'plugins_api', array( &$this, 'check_info' ), 10, 3 );
	}

	/**
	 * Add our self-hosted autoupdate plugin to the filter transient
	 *
	 * @param $transient
	 * @return object $ transient
	 */
	public function check_update( $transient )
	{
		if ( !is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		// Get the remote version
		$remote_version = $this->getRemote_version();
		if ( !$remote_version || ( isset( $transient->response ) && !is_array( $transient->response ) ) ) {
			return $transient;
		}

		// If a newer version is available, add the update
		if ( version_compare( $this->current_version, $remote_version->new_version, '<' ) ) {
			$obj = new stdClass();
			$obj->slug = $this->slug;
			$obj->new_version = $remote_version->new_version;
			$obj->url = $remote_version->url;
			$obj->plugin = $this->plugin_slug;
			$obj->package = $remote_version->package;
			$transient->response[$this->plugin_slug] = $obj;
		}
		return $transient;
	}

	/**
	 * Add our self-hosted description to the filter
	 *
	 * @param boolean $false
	 * @param array $action
	 * @param object $arg
	 * @return bool|object
	 */
	public function check_info($false, $action, $arg)
	{
		if ($action === 'plugin_information' && is_object($arg) && isset($arg->slug) && $arg->slug === $this->slug) {
			$information = $this->getRemote_information();
			return $information ?: $false;
		}
		return $false;
	}

	/**
	 * Return the remote version
	 * @return string $remote_version
	 */
	public function getRemote_version()
	{
		$version = $this->getRemote('version');
		return $this->isValidVersion($version) ? $version : false;
	}

	/**
	 * Get information about the remote version
	 * @return bool|object
	 */
	public function getRemote_information()
	{
		$information = $this->getRemote('info');
		if (!$this->isValidVersion($information) || !isset($information->name, $information->sections)
			|| !is_string($information->name) || trim($information->name) === '' || !is_array($information->sections)) {
			return false;
		}
		foreach ($information->sections as $section) {
			if (!is_string($section)) {
				return false;
			}
		}
		if (isset($information->slug) && $information->slug !== $this->slug) {
			return false;
		}
		if (isset($information->download_link) && !$this->isValidUrl($information->download_link)) {
			return false;
		}
		// WordPress passes these optional values to string and numeric functions.
		foreach (array('version', 'plugin_name', 'author', 'author_profile', 'requires', 'tested', 'requires_php',
			'last_updated', 'added', 'homepage', 'donate_link', 'short_description', 'description', 'upgrade_notice') as $field) {
			if (isset($information->$field) && !is_string($information->$field)) {
				return false;
			}
		}
		foreach (array('downloaded', 'active_installs', 'rating', 'num_ratings', 'support_threads', 'support_threads_resolved') as $field) {
			if (isset($information->$field) && !is_numeric($information->$field)) {
				return false;
			}
		}
		if (!empty($information->rating) && !isset($information->num_ratings)) {
			return false;
		}
		foreach (array('banners', 'banners_rtl', 'icons', 'requires_plugins') as $field) {
			if (isset($information->$field) && !$this->isStringArray($information->$field)) {
				return false;
			}
		}
		if (isset($information->ratings)) {
			if (!is_array($information->ratings) || (!empty($information->ratings) && !isset($information->num_ratings))) {
				return false;
			}
			foreach ($information->ratings as $stars => $count) {
				if (!is_numeric($stars) || !is_numeric($count)) {
					return false;
				}
			}
		}
		if (isset($information->contributors)) {
			if (!is_array($information->contributors)) {
				return false;
			}
			foreach ($information->contributors as $contributor) {
				if (!is_array($contributor) || !isset($contributor['display_name'], $contributor['profile'], $contributor['avatar'])
					|| !$this->isStringArray($contributor)) {
					return false;
				}
			}
		}
		// The legacy endpoint uses new_version; WordPress's details screen reads version and slug.
		$information->version = $information->version ?? $information->new_version;
		$information->slug = $information->slug ?? $this->slug;
		return $information;
	}

	/**
	 * Return the status of the plugin licensing
	 * @return boolean $remote_license
	 */
	public function getRemote_license()
	{
		return $this->getRemote('license');
	}

	private function getRemote($action)
	{
		$params = array(
			'timeout' => 10,
			'body' => array(
				'action' => $action,
				'license_user' => $this->license_user,
				'license_key' => $this->license_key,
			),
		);
		$request = wp_remote_post( $this->update_path, $params );
		if (is_wp_error($request) || (int) wp_remote_retrieve_response_code($request) !== 200) {
			return false;
		}
		$body = wp_remote_retrieve_body($request);
		if (!is_string($body) || $body === '') {
			return false;
		}

		// Keep the established serialized API, without instantiating remote application classes.
		$data = @unserialize($body, array('allowed_classes' => array('stdClass'), 'max_depth' => 32));
		if (($data instanceof stdClass || ($action === 'license' && is_bool($data))) && $this->isSafeData($data)) {
			return $data;
		}
		return false;
	}

	private function isSafeData($data, $depth = 0)
	{
		if ($depth > 32 || (is_object($data) && !($data instanceof stdClass))) {
			return false;
		}
		if (is_array($data) || is_object($data)) {
			foreach ($data as $value) {
				if (!$this->isSafeData($value, $depth + 1)) {
					return false;
				}
			}
		}
		return true;
	}

	private function isValidVersion($version)
	{
		return $version instanceof stdClass
			&& isset($version->new_version, $version->url, $version->package)
			&& (!isset($version->slug) || $version->slug === $this->slug)
			&& is_string($version->new_version)
			&& preg_match('/^[0-9][0-9A-Za-z.+_-]*$/D', $version->new_version)
			&& $this->isValidUrl($version->url) && $this->isValidUrl($version->package);
	}

	private function isStringArray($data)
	{
		if (!is_array($data)) {
			return false;
		}
		foreach ($data as $value) {
			if (!is_string($value)) {
				return false;
			}
		}
		return true;
	}

	private function isValidUrl($url)
	{
		if (!is_string($url)) {
			return false;
		}
		$parts = wp_parse_url($url);
		return is_array($parts) && !empty($parts['host']) && isset($parts['scheme'])
			&& in_array(strtolower($parts['scheme']), array('http', 'https'), true);
	}
}

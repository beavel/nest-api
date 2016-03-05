<?php
namespace Nest;

class Authentication{
	const login_url = 'https://home.nest.com/user/login';
	
	private $transport_url;
	private $access_token;
	private $user;
	private $userid;
	private $cookie_file;
	private $cache_file;
	private $cache_expiration;
	
	function __construct($username=null, $password=null) {
		if ($username === null && defined('USERNAME')) {
			$username = USERNAME;
		}
		if ($password === null && defined('PASSWORD')) {
			$password = PASSWORD;
		}
		if ($username === null || $password === null) {
			throw new InvalidArgumentException('Nest credentials were not provided.');
		}
		$this->username = $username;
		$this->password = $password;
	
		$this->cookie_file = sys_get_temp_dir() . '/nest_php_cookies_' . md5($username . $password);
		static::secure_touch($this->cookie_file);
	
		$this->cache_file = sys_get_temp_dir() . '/nest_php_cache_' . md5($username . $password);
	
		// Attempt to load the cache
		$this->loadCache();
		static::secure_touch($this->cache_file);
	
		// Log in, if needed
		$this->login();
	}
	
	public function login() {
		if ($this->use_cache()) {
			// No need to login; we'll use cached values for authentication.
			return;
		}
		
		$httpRequest = new BaseHttp();
		
		$httpResponse = $httpRequest->POST(self::login_url, array('username' => $this->username, 'password' => $this->password));
		
		$result = json_decode($httpResponse['response']);
		if (!isset($result->urls)) {
			die("Error: Response to login request doesn't contain required transport URL. Response: '" . var_export($result, TRUE) . "'\n");
		}
		$this->transport_url = $result->urls->transport_url;
		$this->access_token = $result->access_token;
		$this->userid = $result->userid;
		$this->user = $result->user;
		$this->cache_expiration = strtotime($result->expires_in);
		$this->saveCache();
	}
	
	public function getAccessToken(){
		return $this->access_token;
	}
	
	public function getCookieFile(){
		return $this->cookie_file;
	}
	
	public function getTransportUrl(){
		return $this->transport_url;
	}

	public function getUser(){
		return $this->user;
	}

	public function getUserId(){
		return $this->userid;
	}
	
	public function use_cache() {
		return file_exists($this->cookie_file) && file_exists($this->cache_file) && !empty($this->cache_expiration) && $this->cache_expiration > time();
	}
	
	public function loadCache() {
		if (!file_exists($this->cache_file)) {
			return;
		}
		$vars = @unserialize(file_get_contents($this->cache_file));
		if ($vars === false) {
			return;
		}
		$this->transport_url = $vars['transport_url'];
		$this->access_token = $vars['access_token'];
		$this->user = $vars['user'];
		$this->userid = $vars['userid'];
		$this->cache_expiration = $vars['cache_expiration'];
		$this->last_status = $vars['last_status'];
	}
	
	public function saveCache() {
		$vars = array(
				'transport_url' => $this->transport_url,
				'access_token' => $this->access_token,
				'user' => $this->user,
				'userid' => $this->userid,
				'cache_expiration' => $this->cache_expiration,
				'last_status' => @$this->last_status
		);
		file_put_contents($this->cache_file, serialize($vars));
	}
	
	private static function secure_touch($fname) {
		if (file_exists($fname)) {
			return;
		}
		$temp = tempnam(sys_get_temp_dir(), 'NEST');
		rename($temp, $fname);
	}
}

?>
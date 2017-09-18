<?php
namespace App\Http {
	/**
	 * 应用程序Http请求类。
	 */
	class Request extends \Phalcon\Http\Request {
		/**
		 * 是否是一个远程服务者代理请求。
		 * @return boolean
		 */
		public function isRemoteServicerProxy() {
			return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'RemoteServicerProxy';
		}
		
		/**
		 * @see \Phalcon\Http\Request::getHttpHost()
		 */
		public function getHttpHost() {
			return !empty($_SERVER['HTTP_X_REAL_HOST']) ? $_SERVER['HTTP_X_REAL_HOST'] : $_SERVER['HTTP_HOST'];
		}
		
		/**
		 * @see \Phalcon\Http\Request::getClientAddress()
		 */
		public function getClientAddress($trustForwardedHeader = null) {
			if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
				return $_SERVER['HTTP_X_REAL_IP'];
			}
			else {
				return parent::getClientAddress(true);
			}
		}
		
		/**
		 * 是否是一个移动端请求。
		 * @param boolean $isStrict 是否进行严格判断，当为true时，必须是真实的来自于移动端请求才算，否则只要请求URL以wap.或mobile.为前缀即算。
		 * @return boolean
		 */
		public function isMobile($isStrict = false) {
			$isMobile = false;
			if (!$isStrict && preg_match('#^(?:wap|mobile|m)\.#i', $this->getHttpHost())) {
				$isMobile = true;
			}
			else {
				$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
				static $mobiles = array(
					'iphone', 
					'ipad', 
					'android', 
					'mobile', 
					'phone'
				);
				foreach ($mobiles as $mobile) {
					if (strpos($userAgent, $mobile) !== false) {
						$isMobile = true;
						break;
					}
				}
			}
			return $isMobile;
		}
	}
}
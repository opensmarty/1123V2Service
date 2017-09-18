<?php
namespace App\Mvc {
	use App\System as Sys;

	/**
	 * 远程服务者代理类。
	 */
	class RemoteServicerProxy implements \ArrayAccess {
		/**
		 * 远程服务名称。
		 */
		protected $name = null;
		
		/**
		 * 远程服务地址。
		 */
		protected $url = null;
		
		/**
		 * 远程服务密钥。
		 */
		protected $key = null;
		
		/**
		 * CURL资源句柄。
		 */
		protected $curl = null;
		
		/**
		 * 临时缓存数据。
		 */
		protected $data = null;
		
		/**
		 * 构造函数。
		 * @param string $name 远程服务名称。
		 * @param string $url 远程服务地址。
		 * @param string $key 远程服务密钥。
		 * @return void
		 */
		public function __construct($name, $url, $key) {
			if (empty($name) || empty($url) || empty($key)) {
				Sys::throwException('远程服务名称、地址、密钥不能为空');
			}
			$this->name = $name;
			$this->url = $url;
			$this->key = $key;
			
			// 设置POST请求参数。
			$isCli = Sys::isCliMode();
			$curl = curl_init($this->url);
			curl_setopt_array($curl, array(
				CURLOPT_POST => true, 
				CURLOPT_RETURNTRANSFER => true, 
				CURLOPT_BINARYTRANSFER => true, 
				CURLOPT_SSL_VERIFYHOST => 2, 
				CURLOPT_SSL_VERIFYPEER => false, 
				CURLOPT_SSL_CIPHER_LIST => 'TLSv1', 
				CURLOPT_COOKIE => isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : null,  // Cookie不一定有。
				CURLOPT_HTTPHEADER => array(
					'X-Requested-With: RemoteServicerProxy', 
					'User-Agent:' . $_SERVER['HTTP_USER_AGENT']
				), 
				CURLOPT_HEADERFUNCTION => function ($curl, $header) use($isCli) {
					static $once = false;
					if (!$isCli && substr($header, 0, 11) == 'Set-Cookie:') {
						if (headers_sent($file, $line)) {
							if (!$once) {
								// 如果服务端的Cookie一次都不能发送成功就抛出异常，以提醒开发者注意检查程序逻辑。
								Sys::throwException("HTTP头部已经在文件 $file 的第 $line 行发出，从而导致不能发送携带数据的Cookie");
							}
						}
						else {
							header($header, false);
						}
						$once = true;
					}
					return strlen($header);
				}
			));
			$this->curl = $curl;
		}
		
		/**
		 * 析构函数。
		 * @return void
		 */
		public function __destruct() {
			if (!empty($this->data)) {
				foreach ($this->data as $offset => $value) {
					$this->__call('offsetSet', array(
						$offset, 
						$value
					));
				}
			}
			curl_close($this->curl);
		}
		
		/**
		 * 移除指定索引的数据。
		 * @param integer|string $offset 数据项索引。
		 * @return void
		 */
		public function offsetUnset($offset) {
			$this->__call('offsetUnset', func_get_args());
			unset($this->data[$offset]);
		}
		
		/**
		 * 设置指定索引的数据。
		 * @param integer|string $offset 数据项索引。
		 * @param value 数据项数据。
		 * @return void
		 */
		public function offsetSet($offset, $value) {
			$this->data[$offset] = $value;
		}
		
		/**
		 * 判断指定索引的数据是否存在。
		 * @param integer|string $offset 数据项索引。
		 * @return boolean
		 */
		public function offsetExists($offset) {
			return isset($this->data[$offset]) || $this->__call('offsetExists', func_get_args());
		}
		
		/**
		 * 获取指定索引的数据，返回引用型数据提供了通过多维数组的形式操作数据的功能，如：$data['key']['item']。
		 * @param integer|string $offset 数据项索引。
		 * @return mixed
		 */
		public function &offsetGet($offset) {
			if (!isset($this->data[$offset])) {
				$this->data[$offset] = $this->__call('offsetGet', func_get_args());
			}
			return $this->data[$offset];
		}
		
		/**
		 * 远程服务调用方法。
		 * @param string $name 方法名称。
		 * @param array $arguments 方法参数。
		 * @return mixed
		 */
		public function __call($name, array $arguments) {
			// 调用远程服务方法。
			try {
				$data = igbinary_serialize(array(
					'name' => $this->name, 
					'url' => $this->url, 
					'method' => $name, 
					'arguments' => $arguments
				));
			}
			catch (\Exception $e) {
				// 消息如：Serialization of 'Closure' is not allowed。 
				Sys::throwException($e->getMessage());
			}
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data . md5($data . $this->key));
			$rawOutput = curl_exec($this->curl);
			
			// 处理远程服务结果。
			$remoteService = '[地址=' . $this->url . ', 名称=' . $this->name . ', 方法=' . $name . ']';
			if ($rawOutput === false) {
				Sys::throwException("调用远程服务 $remoteService 时遇到了连接性的错误：" . curl_error($this->curl));
			}
			$realData = substr($rawOutput, 0, -32);
			$oldErrorLevel = error_reporting();
			error_reporting(0);
			$response = igbinary_unserialize($realData);
			error_reporting($oldErrorLevel);
			if ($response === null) {
				$line = str_pad('', 100, '=');
				Sys::throwException("调用远程服务 $remoteService 时收到的响应数据无效：\n$line\n$rawOutput\n$line\n");
			}
			else {
				$md5Sign = substr($rawOutput, -32);
				if (md5($realData . $this->key) != $md5Sign) {
					if (md5($realData) == $md5Sign) {
						Sys::throwException("调用远程服务 $remoteService 时收到了不期望的错误：" . $response[0]);
					}
					else {
						$line = str_pad('', 100, '=');
						Sys::throwException("调用远程服务 $remoteService 时收到的响应数据无效：\n$line\n$rawOutput\n$line\n");
					}
				}
			}
			return $response[0];
		}
		
		/**
		 * 当获取不存在的属性时调用。
		 * @param string $name
		 * @return mixed
		 */
		public function __get($name) {
			$class = get_class($this);
			Sys::throwException("属性 $class::$name 未被定义");
		}
	}
}
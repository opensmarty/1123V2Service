<?php
namespace App {
	/**
	 * 应用程序异常类。
	 */
	class Exception extends \Exception {
		/**
		 * 完整的异常消息，也就是添加了异常源文件及行号信息的异常消息。
		 */
		protected $fullMessage = null;
		
		/**
		 * 异常构造函数。
		 * @param string $message 异常消息。
		 * @param string $extraMessage 附加异常消息，它会与异常消息简单的拼接后作为完整的异常消息。
		 * @param integer $code 异常代码。
		 * @param Exception $previous 先前的异常。
		 */
		public function __construct($message, $extraMessage = null, $code = null, Exception $previous = null) {
			$this->fullMessage = $message . $extraMessage;
			parent::__construct($message, $code, $previous);
		}
		
		/**
		 * 获取完整的异常消息。
		 * @return string
		 */
		public function getFullMessage() {
			return $this->fullMessage;
		}
	}
}
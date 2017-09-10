<?php
namespace App {
	/**
	 * PHP同名函数的重写函数或相同风格函数的收集类，不过下划线书写格式已经改成了驼峰式书写格式。
	 */
	class PHPX {
		/**
		 * 把变量转换成字符串。
		 * @param mixed $var
		 * @return string
		 */
		public static function strval($var) {
			if (is_array($var)) {
				$ret = 'array';
			}
			elseif (is_object($var)) {
				$class = get_class($var);
				$ret = "object($class)";
			}
			elseif (is_null($var)) {
				$ret = 'null';
			}
			elseif (is_bool($var)) {
				if ($var) {
					$ret = 'true';
				}
				else {
					$ret = 'false';
				}
			}
			else {
				$ret = strval($var);
				if (is_resource($var)) {
					$ret = lcfirst($ret);
				}
			}
			return $ret;
		}
	}
}
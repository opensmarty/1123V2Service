<?php
namespace App\Session {
	/**
	 * 应用程序会话接口。
	 */
	interface AdapterInterface extends \Phalcon\Session\AdapterInterface, \ArrayAccess {
		/**
		 * 获取索引指定的会话信息，返回引用型数据提供了通过多维数组的形式操作会话数据的功能，如：$sessionData['key']['item']。
		 * @param string $index 索引。
		 * @param mixed $defaultValue 默认值。
		 * @return mixed
		 */
		public function &get($index, $defaultValue = null);
		
		/**
		 * 获取指定索引的会话数据，返回引用型数据提供了通过多维数组的形式操作会话数据的功能，如：$session['key']['item']。
		 * @param integer|string $offset 数据项索引。
		 * @return mixed
		 */
		public function &offsetGet($offset);
	}
}
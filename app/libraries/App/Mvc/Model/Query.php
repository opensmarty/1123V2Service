<?php
namespace App\Mvc\Model {
	use App\System as Sys;

	/**
	 * 应用程序PHQL语句表示类。
	 */
	class Query extends \Phalcon\Mvc\Model\Query {
		/**
		 * 构造函数。
		 * @param string $phql
		 */
		public function __construct($phql = null) {
			// 校验参数。
			$phql = trim($phql);
			if (strlen($phql) == 0) {
				Sys::throwException('PHQL语句不能为空');
			}
			
			// 构造实例。
			$di = Sys::getInstance()->getDI();
			$this->_manager = new \Phalcon\Mvc\Model\Manager();
			$this->_manager->setDI($di);
			$this->setDI($di);
			parent::__construct($phql, $di);
			
			// 设置语句类型。
			$str = strtolower(substr($phql, 0, 6));
			if ($str == 'update') {
				$this->_type = self::TYPE_UPDATE;
			}
			elseif ($str == 'delete') {
				$this->_type = self::TYPE_DELETE;
			}
			elseif ($str == 'insert') {
				$this->_type = self::TYPE_INSERT;
			}
			elseif ($str == 'select') {
				$this->_type = self::TYPE_SELECT;
			}
			else {
				Sys::throwException("无效的PHQL语句：$phql");
			}
		}
		
		/**
		 * 获取PHQL语句文本。
		 * @return string
		 */
		public function getPHQL() {
			return $this->_phql;
		}
		
		/**
		 * 设置语句类型(已被废弃)。
		 * @param integer $type
		 * @return void
		 */
		public function setType($type) {
			Sys::throwException('方法 setType() 已被废弃');
		}
		
		/**
		 * 给PHQL语句中的长命名空间注册一个较短的别名，如：registerNamespaceAlias('u', 'App\\User\\Models') 这样就可以用 u:模型名 
		 * 代替App\\User\\Models\\模型名了，示例语句：select * from u:User。但是注意：用了短命名空间别名后，PHQL语句中的字段名前
		 * 必须用表别名，如：select * from u:User as r where r.id = 10，还有短命名空间仅能用于Select语句。
		 * @param string $alias
		 * @param string $namespace
		 * @return void
		 */
		public function registerNamespaceAlias($alias, $namespace) {
			$this->_manager->registerNamespaceAlias($alias, $namespace);
		}
		
		/**
		 * 获取指定别名的命名空间。
		 * @param string $alias
		 * @return string
		 */
		public function getNamespaceAlias($alias) {
			return $this->_manager->getNamespaceAlias($alias);
		}
		
		/**
		 * 获取所有已注册的命名空间。
		 * @return array
		 */
		public function getNamespaceAliases() {
			return $this->_manager->getNamespaceAliases();
		}
	}
}

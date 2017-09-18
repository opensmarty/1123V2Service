<?php
namespace App\Mvc {
	use App\System as Sys;
	use App\Exception;
	use App\Mvc\Model\Query;
	use App\UI\PageLink;
	use App\Cache\Session;
	use Phalcon\Db;
	use Phalcon\Mvc\Model\Resultset;
	use Phalcon\Mvc\Model\Query\Builder;
	use Phalcon\Mvc\Model\Transaction;

	/**
	 * 应用程序高级模型基类。
	 * 
	 * 高级模型实例相当于一个微型的只有一个数据库的数据库管理系统，所以它不再与任何一个具体的物理表关联，反而会关联一组物理表，
	 * 它不但可以读写物理表数据，而且也可以创建并管理为了提高性能给这些物理表创建的逻辑索引，逻辑索引即原始数据的另一种便于查
	 * 询的存放形式，它存放在普通物理表中，它不同于给物理表创建的传统索引，传统索引在这叫做物理索引，如用户名到用户ID的映射数
	 * 据就是一种逻辑索引。
	 * 
	 * 使用情景：凡是需要跨表操作的时候就需要用到高级模型，一般从概念上讲高级模型代表的就只是一种信息，如用户信息、商品信息等。
	 * 虽然概念上是一种信息，但是实现的时候却用到了多个物理表，甚至这些物理表都可以跨服务器，但是不管怎么分散或分布，概念上它
	 * 们只是为了存取一种信息而服务。
	 * 
	 * 但请注意：再高级它也是一种模型，所以不要把业务逻辑放在模型里面，它里面放的都是操作或管理数据的且与任何业务都没有关系的
	 * 逻辑，你就把它当作是在开发另一种数据库管理系统，模型的方法就是你对外公开的专属于你的数据库管理系统的操作接口。
	 * 
	 * 建议：
	 * 
	 * (1) 高级模型名用复数形式，而普通模型名用单数形式，因为高级模型实例代表的是多个表格组成的整体，自然包含了多条记录，而普
	 * 通模型实例仅代表的是单条记录，所以前者用复数，后者用单数易于理解，如 Users 代表用户高级模型，而 User 代表用户普通模型。
	 * 
	 * (2) 派生类中方法使用格式：动作名称 + 附加描述 + By + 字段名(参数)，因为它直观易于理解，如：
	 * 
	 *  // 插入一条信息。
	 *	public function create或insert($info) {
	 *	}
	 *	
	 *	// 更新一条信息。
	 *	public function edit|update($info) {
	 *	}
	 *	 
	 *	// 查询信息。
	 *	public function read或select($ids) {
	 *	}
	 *	 
	 *	// 删除信息。
	 *	public function delete($id) {
	 *	}
	 *	public function deleteById($id) {
	 *	}
	 *	public function deleteBy(条件参数) {
	 *	}
	 *	public function delete短描述($id) {
	 *	}
	 *	public function delete短描述ById($id) {
	 *	}
	 *	public function delete短描述By(条件参数) {
	 *	}
	 * 		 		 
	 *	// 给 $id 对应的 FieldName 字段值加上 $value。
	 *	public function increaseFieldNameById($id, $value) {
	 *	}
	 *	 
	 *	// 把 $id 对应的 FieldName 字段值减去 $value。
	 *	public function decreaseFieldNameById($id, $value) {
	 *	}
	 *	 
	 *	// 设置 $id 对应的 FieldName 字段值。
	 *	public function setFieldNameById($id, $value) {
	 *	}
	 *	  
	 *	// 获取 $id 对应的 FieldName 字段值。
	 *	public function getFieldNameById($id) {
	 *	}
	 * 
	 *	// 给 参数 对应的 FieldName 字段值加上 $value。
	 *	public function increaseFieldNameBy($param1,..., $value) {
	 *	}
	 *	 
	 *	// 把 参数 对应的 FieldName 字段值减去 $value。
	 *	public function decreaseFieldNameBy($param1,...) {
	 *	}
	 *	 
	 *	// 设置 参数 对应的 FieldName 字段值。
	 *	public function setFieldNameBy($param1,..., $value) {
	 *	}
	 *	  
	 *	// 获取 参数 对应的 FieldName 字段值。
	 *	public function getFieldNameBy($param1,...) {
	 *	}
	 * 
	 * 当然您也可以根据您自己的喜好或其它开发规范进行方法命名，但不管怎么命名，强烈建议方法名称用统一格式。
	 */
	abstract class AdvancedModel {
		/**
		 * 模块目录。
		 */
		protected $moduleDir = null;
		
		/**
		 * 模块名称。
		 */
		protected $moduleName = null;
		
		/**
		 * 模块配置。
		 */
		protected $config = null;
		
		/**
		 * 依赖注入容器。
		 */
		protected $di = null;
		
		/**
		 * 数据库连接服务名称。
		 */
		protected $connectionService = null;
		
		/**
		 * 数据库读连接服务名称。
		 */
		protected $readConnectionService = null;
		
		/**
		 * 数据库写连接服务名称。
		 */
		protected $writeConnectionService = null;
		
		/**
		 * 事务实例。
		 */
		protected $transaction = null;
		
		/**
		 * 模型构造函数。
		 */
		final public function __construct() {
			// 初始化参数。
			$this->moduleDir = dirname(dirname((new \ReflectionObject($this))->getFileName()));
			if ($this->moduleDir != APP_ROOT) {
				$this->moduleName = substr($this->moduleDir, strrpos($this->moduleDir, DIRECTORY_SEPARATOR) + 1);
				$this->config = Sys::getConfig()->get($this->moduleName);
			}
			else {
				$this->config = Sys::getConfig()->global;
			}
			$this->di = Sys::getInstance()->getDI();
			
			// 设置模型数据库连接服务名称。
			$this->setConnectionService('db');
			$this->setReadConnectionService('rdb');
			$this->setWriteConnectionService('wdb');
			if (!empty($this->moduleName)) {
				$moduleNameLC = lcfirst($this->moduleName);
				$this->setConnectionService($moduleNameLC . 'Db');
				$this->setReadConnectionService($moduleNameLC . 'RDb');
				$this->setWriteConnectionService($moduleNameLC . 'WDb');
			}
			
			// 初始化模型。
			if (method_exists($this, 'initialize')) {
				$this->initialize();
				
				// 安装终止化方法。
				if (method_exists($this, 'finalize')) {
					register_shutdown_function(array(
						$this, 
						'finalize'
					));
				}
			}
		}
		
		/**
		 * 析构函数。
		 */
		final public function __destruct() {
		}
		
		/**
		 * 创建一个预备的SQL语句。
		 * @param string $sqlStatement 原生的SQL语句，其中可用 ? 号或者 :name 作为占位符。
		 * @return \PDOStatement
 		 */
		protected function createPrepareSQL($sqlStatement) {
			$sqlStatement = trim($sqlStatement);
			if (strlen($sqlStatement) == 0) {
				Sys::throwException('SQL语句不能为空');
			}
			$str = strtolower(substr($sqlStatement, 0, 6));
			$isSelect = ($str == 'select');
			$connection = $this->getConnection($isSelect);
			if (empty($connection)) {
				Sys::throwException('没有可用的数据库连接');
			}
			$prepareSQL = $connection->prepare($sqlStatement);
			$prepareSQL->connection = $connection;
			return $prepareSQL;
		}
		
		/**
		 * 执行一个预备的SQL语句。
		 * @param \PDOStatement
 		 * @param array $bindParams 绑定参数，对于 :name 格式的占位符可用 array('name' => 值) 来进行绑定。
 		 * @return mixed 对于select语句是一个行数组、对于insert语句是最后的插入id、对于update与delete语句则是影响的行数、其它语句则为true，
 		 * 失败时则会抛出异常。
		 */
		protected function executePrepareSQL(\PDOStatement $pdoStatement, array $bindParams = null) {
			// 设置连接内部的SQL语句。
			$hasSQLStatement = false;
			if (method_exists($pdoStatement->connection, 'setInnerSQLStatement')) {
				$hasSQLStatement = true;
				$pdoStatement->connection->setInnerSQLStatement($pdoStatement->queryString);
				
				// 派发beforeQuery事件。
				$eventsManager = $pdoStatement->connection->getEventsManager();
				if (!empty($eventsManager) && ($eventsManager->hasListeners('db') || $eventsManager->hasListeners('db:beforeQuery'))) {
					$eventsManager->fire('db:beforeQuery', $pdoStatement->connection, null, false);
				}
			}
			
			// 执行语句。
			try {
				$ret = $pdoStatement->execute($bindParams);
			}
			catch (\Exception $e) {
				// 一般是由语句的语法错误而导致。
				Sys::throwException($e->getMessage());
			}
			if (!$ret) {
				// 语法正确但执行失败。
				Sys::throwException($pdoStatement->errorInfo()[2]);
			}
			
			// 派发afterQuery事件。
			if ($hasSQLStatement) {
				$eventsManager = $pdoStatement->connection->getEventsManager();
				if (!empty($eventsManager) && ($eventsManager->hasListeners('db') || $eventsManager->hasListeners('db:afterQuery'))) {
					$eventsManager->fire('db:afterQuery', $pdoStatement->connection, null, false);
				}
			}
			
			// 获取执行结果。
			$str = strtolower(substr($pdoStatement->queryString, 0, 6));
			if ($str == 'select') {
				$pdoStatement->setFetchMode(\PDO::FETCH_ASSOC);
				$ret = $pdoStatement->fetchAll();
			}
			elseif ($str == 'insert') {
				$ret = $pdoStatement->connection->lastInsertId();
			}
			elseif ($str == 'delete' || $str == 'update') {
				$ret = $pdoStatement->rowCount();
			}
			return $ret;
		}
		
		/**
		 * 执行一个原生的SQL语句。
		 * @param string $sqlStatement 原生的SQL语句，其中可用 ? 号或者 :name 作为占位符。
		 * @param array $bindParams 绑定参数，对于 :name 格式的占位符可用 array('name' => 值) 来进行绑定。
		 * @return mixed 对于select语句是一个行数组、对于insert语句是最后的插入id、对于update与delete语句则是影响的行数、其它语句则为true，
		 * 失败时则会抛出异常。
		 */
		protected function executeRawSQL($sqlStatement, array $bindParams = null) {
			$sqlStatement = trim($sqlStatement);
			if (strlen($sqlStatement) == 0) {
				Sys::throwException('SQL语句不能为空');
			}
			
			// 获取数据库连接。
			$str = strtolower(substr($sqlStatement, 0, 6));
			$isSelect = ($str == 'select');
			$connection = $this->getConnection($isSelect);
			if (empty($connection)) {
				Sys::throwException('没有可用的数据库连接');
			}
			
			// 执行SQL语句。
			try {
				if ($isSelect) {
					$rs = $connection->query($sqlStatement, $bindParams);
					$rs->setFetchMode(Db::FETCH_ASSOC);
					$ret = $rs->fetchAll();
				}
				else {
					$ret = $connection->execute($sqlStatement, $bindParams);
					if ($str == 'insert') {
						$ret = $connection->lastInsertId();
					}
					elseif ($str == 'delete' || $str == 'update') {
						$ret = $connection->affectedRows();
					}
				}
			}
			catch (\Exception $e) {
				Sys::throwException($e->getMessage());
			}
			return $ret;
		}
		
		/**
		 * 建立一个使用面向对象的方式去构建PHQL语句的构建器。
		 * @return \Phalcon\Mvc\Model\Query\Builder
		 */
		protected function createPHQLBuilder() {
			return new Builder(null, $this->di);
		}
		
		/**
		 * 建立一个PHQL的Select语句。
		 * @param string $phqlStatement PHQL语句，其中可用 ?数字 或者 :name: 作为占位符，如：select * from user where id = ?0 and name=:name:。
		 * @param array $namespaceAliases PHQL语句中长命名空间的短别名，如使用 array('u' => 'App\\User\\Models') 后就可以用 u:User 代替
		 * App\User\Models\User，但是注意，用了短别名后，语句中的字段名前必须加表别名，如 select * from u:User as r where r.id = 10。
		 * @return App\Mvc\Model\Query
		 */
		protected function createPHQLSelect($phqlStatement, array $namespaceAliases = null) {
			try {
				$query = new Query($phqlStatement);
			}
			catch (\Exception $e) {
				Sys::throwException($e->getMessage());
			}
			if (is_array($namespaceAliases)) {
				foreach ($namespaceAliases as $alias => $namespace) {
					$query->registerNamespaceAlias($alias, $namespace);
				}
			}
			return $query;
		}
		
		/**
		 * 执行一个PHQL的Select语句，注意：仅能执行Select语句，因为PHQL的update、delete效率很低下，这又是因为它底层是先执行查询，再一个个的进行
		 * 更新或删除，所以当数据量一大时，与服务器的交互信息传输就会耗掉不少的时间，最后还有insert请使用更易用的使用了ORM技术的表模型来插入。
		 * @param App\Mvc\Model\Query|string $phqlStatement PHQL语句。
		 * @param array $bindParams 绑定参数，?数字 格式的占位符绑定方法为 array(数字 => 值)，:name: 格式的占位符绑定方法为 array('name' => 值)。
		 * @return array 行对象数组。
		 */
		protected function executePHQLSelect($phqlStatement, array $bindParams = null) {
			if (is_string($phqlStatement)) {
				$phqlStatement = new Query($phqlStatement);
			}
			if ($phqlStatement->getType() != Query::TYPE_SELECT) {
				Sys::throwException('此方法仅能执行PHQL的SELECT语句');
			}
			try {
				$rs = $phqlStatement->execute($bindParams);
				$rs->setHydrateMode(Resultset::HYDRATE_OBJECTS);
				$ret = array();
				foreach ($rs as $val) {
					$ret[] = $val;
				}
			}
			catch (\Exception $e) {
				Sys::throwException($e->getMessage());
			}
			return $ret;
		}
		
		/**
		 * 设置数据库连接服务名称。
		 * @param string $connectionService
		 * @return void
		 */
		public function setConnectionService($connectionService) {
			if ($connectionService == 'db' || $this->di->has($connectionService)) {
				$this->connectionService = $connectionService;
			}
		}
		
		/**
		 * 设置数据库读连接服务名称。
		 * @param string $connectionService
		 * @return void
		 */
		public function setReadConnectionService($connectionService) {
			if (empty($connectionService) || $this->di->has($connectionService)) {
				$this->readConnectionService = $connectionService;
			}
		}
		
		/**
		 * 设置数据库写连接服务名称。
		 * @param string $connectionService
		 * @return void
		 */
		public function setWriteConnectionService($connectionService) {
			if (empty($connectionService) || $this->di->has($connectionService)) {
				$this->writeConnectionService = $connectionService;
			}
		}
		
		/**
		 * 获取数据库连接服务名称。
		 * @return string
		 */
		public function getConnectionService() {
			return $this->connectionService;
		}
		
		/**
		 * 获取数据库读连接服务名称。
		 * @return string
		 */
		public function getReadConnectionService() {
			return $this->readConnectionService;
		}
		
		/**
		 * 获取数据库写连接服务名称。
		 * @return string
		 */
		public function getWriteConnectionService() {
			return $this->writeConnectionService;
		}
		
		/**
		 * 获取数据库连接。
		 * @param boolean $isSelect 是否是查询操作。
		 * @return \Phalcon\Db\Adapter\Pdo
		 */
		protected function getConnection($isSelect) {
			$connection = null;
			if (!empty($this->transaction)) {
				// 如果开启了事务就使用事务所使用的数据库连接。
				$connection = $this->transaction->getConnection();
			}
			else {
				if ($isSelect) {
					$connection = $this->getReadConnection();
				}
				else {
					$connection = $this->getWriteConnection();
				}
				if (empty($connection) && $this->di->has($this->connectionService)) {
					$connection = $this->di->get($this->connectionService);
				}
			}
			return $connection;
		}
		
		/**
		 * 获取数据库读连接。
		 * @return \Phalcon\Db\Adapter\Pdo
		 */
		protected function getReadConnection() {
			if (!empty($this->readConnectionService) && $this->di->has($this->readConnectionService)) {
				return $this->di->get($this->readConnectionService);
			}
		}
		
		/**
		 * 获取数据库写连接。
		 * @return \Phalcon\Db\Adapter\Pdo
		 */
		protected function getWriteConnection() {
			if (!empty($this->writeConnectionService) && $this->di->has($this->writeConnectionService)) {
				return $this->di->get($this->writeConnectionService);
			}
		}
		
		/**
		 * 查询数据并进行分页。
		 * @param array $queryOptions 查询选项，在此没有固定的选项，它是随具体的高级模型而有所不同的。
		 * @param integer $pageRows 每页行数。
		 * @param integer $nowPage 当前页码。
		 * @param string $baseURL 数据分页链接中使用的基准URL。
		 * @param integer $countLinks 数据分页链接中的页链接数目。
		 * @return array array('count' => 数据总数，'pages' => 数据页数，'pagen' => 当前页码, 'lists' => 数据列表, 'links' => 分页链接)。
		 */
		public function query(array $queryOptions = null, $pageRows = 10, $nowPage = 1, $baseURL = null, $countLinks = 11) {
			// 获取所有需要分页的信息ID。
			$objRef = $this;
			$callback = function () use($objRef, $queryOptions) {
				$ret = $objRef->queryIDSList($queryOptions);
				if (!is_array($ret)) {
					$ret = array();
				}
				return $ret;
			};
			$ids = Session::get('_' . $this->moduleName . 'QueryOptionsMd5', md5(igbinary_serialize($queryOptions)), $callback);
			
			// 获取当前页的信息ID。
			$pageRows = intval('0' . $pageRows);
			if (empty($ids)) {
				$totalRows = 0;
				$totalPages = 0;
				$nowPage = 0;
				$lists = array();
			}
			else {
				$totalRows = count($ids);
				if ($pageRows < 1) {
					$pageRows = 1;
				}
				$totalPages = ceil($totalRows / $pageRows);
				$nowPage = intval('0' . $nowPage);
				if ($nowPage < 1) {
					$nowPage = 1;
				}
				elseif ($nowPage > $totalPages) {
					$nowPage = $totalPages;
				}
				$begin = ($nowPage - 1) * $pageRows;
				$end = $begin + $pageRows;
				if ($end > $totalRows) {
					$end = $totalRows;
				}
				$queryOptions['ids'] = array_slice($ids, $begin, $end - $begin);
				$lists = $this->queryIDSData($queryOptions);
				if (!is_array($lists)) {
					$lists = array();
				}
			}
			
			// 构造并返回值。
			$ret = array(
				'count' => $totalRows, 
				'pages' => $totalPages, 
				'pagen' => $nowPage, 
				'lists' => $lists, 
				'links' => PageLink::makeStandard($totalRows, $pageRows, $nowPage, $baseURL, $countLinks)
			);
			return $ret;
		}
		
		/**
		 * 根据查询选项查询出所有信息的ID集合。
		 * @param array $queryOptions 查询选项。
		 * @return array
		 */
		protected function queryIDSList(array $queryOptions = null) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("被 App\Mvc\AdvancedModel::query 方法调用的抽象方法 $class::$funct 未被子类 $subClass 实现", 'query');
		}
		
		/**
		 * 根据查询选项查询出某页ID集合对应的数据。
		 * @param array $queryOptions 查询选项。
		 * @return array
		 */
		protected function queryIDSData(array $queryOptions = null) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("被 App\Mvc\AdvancedModel::query 方法调用的抽象方法 $class::$funct 未被子类 $subClass 实现", 'query');
		}
		
		/**
		 * 启动事务。
		 * @return void
		 */
		protected function startTransaction() {
			if (empty($this->transaction)) {
				if ($this->di->has($this->writeConnectionService)) {
					$dbService = $this->writeConnectionService;
				}
				else {
					$dbService = $this->connectionService;
				}
				$this->transaction = new Transaction($this->di, false, $dbService);
				$this->transaction->begin();
			}
		}
		
		/**
		 * 提交事务。
		 * @return void
		 */
		protected function commitTransaction() {
			if (!empty($this->transaction)) {
				$this->transaction->commit();
				$this->transaction = null;
			}
		}
		
		/**
		 * 回滚事务。
		 * @return void
		 */
		protected function rollbackTransaction() {
			if (!empty($this->transaction)) {
				try {
					$this->transaction->rollback();
				}
				catch (\Exception $e) {
					// 因其回滚操作引发的异常并不是因回滚失败而引起的，而是因为回滚操作本身就被视为一种异常，所以在此捕获了异常。
				}
				$this->transaction = null;
			}
		}
		
		/**
		 * 插入信息。
		 * @param array $info
		 * @return string 新插入信息的ID。
		 */
		public function insert(array $info) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("抽象方法 $class::$funct 未被子类 $subClass 实现");
		}
		
		/**
		 * 查询信息。
		 * @param string $id
		 * @return array
		 */
		public function select($id) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("抽象方法 $class::$funct 未被子类 $subClass 实现");
		}
		
		/**
		 * 查询多条信息。
		 * @param array $ids
		 * @return array
		 */
		public function selects(array $ids) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("抽象方法 $class::$funct 未被子类 $subClass 实现");
		}
		
		/**
		 * 更新信息。
		 * @param array $info 新的信息。此信息可以是完整的信息，也可以是部分信息，即只有个别需要更新的字段。
		 * @param array $oldInfo 旧的完整信息。有时候业务层更新信息的时候是先读取旧的完整信息，然后在旧的完整信息的基础上进行更改，更改后再传给 update 方法，而
		 * 刚好 update 方法有时却需要旧的完整信息，故此时业务层就可以把先前读取的旧的完整信息顺便传给 update 方法，这样 update 方法就可以直接拿来使用了，免去了
		 * 重复读取旧的完整信息的操作，从而提升了性能。
		 * @return void
		 */
		public function update(array $info, array $oldInfo = null) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("抽象方法 $class::$funct 未被子类 $subClass 实现");
		}
		
		/**
		 * 删除信息。
		 * @param string $id
		 * @return void
		 */
		public function delete($id) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("抽象方法 $class::$funct 未被子类 $subClass 实现");
		}
		
		/**
		 * 删除多条信息。
		 * @param array $ids
		 * @return void
		 */
		public function deletes(array $ids) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("抽象方法 $class::$funct 未被子类 $subClass 实现");
		}
		
		/**
		 * 创建信息(初始为insert方法的别名)。
		 * @param array $info
		 * @return string 新创建信息的ID。
		 */
		public function create(array $info) {
			return $this->insert($info);
		}
		
		/**
		 * 读取信息(初始为select方法的别名)。
		 * @param string $id
		 * @return array
		 */
		public function read($id) {
			return $this->select($id);
		}
		
		/**
		 * 读取多条信息(初始为selects方法的别名)。
		 * @param array $ids
		 * @return array
		 */
		public function reads(array $ids) {
			return $this->selects($ids);
		}
		
		/**
		 * 编辑信息(初始为update方法的别名)。
		 * @param array $info 新的信息。
		 * @param array $oldInfo 旧的完整信息。
		 * @return void
		 */
		public function edit(array $info, array $oldInfo = null) {
			$this->update($info);
		}
		
		/**
		 * 移除信息(初始为delete方法的别名)。
		 * @param string $id
		 * @return void
		 */
		public function remove($id) {
			$this->delete($id);
		}
		
		/**
		 * 移除多条信息(初始为deletes方法的别名)。
		 * @param array $ids
		 * @return void
		 */
		public function removes(array $ids) {
			$this->deletes($ids);
		}
		
		/**
		 * 查询信息。
		 * @param array $idx x代表多个字段，即这个id=(字段1,字段2...)多字段的组合。
		 * @return array
		 */
		public function selectEx(array $idx) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("抽象方法 $class::$funct 未被子类 $subClass 实现");
		}
		
		/**
		 * 读取信息(初始为selectEx方法的别名)。
		 * @param array $idx
		 * @return array
		 */
		public function readEx(array $idx) {
			return $this->selectEx($idx);
		}
		
		/**
		 * 删除信息。
		 * @param array $idx x代表多个字段，即这个id=(字段1,字段2...)多字段的组合。
		 * @return void
		 */
		public function deleteEx(array $idx) {
			$class = __CLASS__;
			$funct = __FUNCTION__;
			$subClass = get_class($this);
			Sys::throwException("抽象方法 $class::$funct 未被子类 $subClass 实现");
		}
		
		/**
		 * 删除信息(初始为deleteEx方法的别名)。
		 * @param array $idx
		 * @return void
		 */
		public function removeEx(array $idx) {
			$this->deleteEx($idx);
		}
		
		/**
		 * 当调用了不存在的方法时调用。
		 * @param string $name 方法名称。
		 * @param array $arguments 方法参数。
		 * @return mixed
		 */
		public function __call($name, array $arguments) {
			$class = get_class($this);
			Sys::throwException("方法 $class::$name() 未被定义");
		}
		
		/**
		 * 当获取不存在的属性时调用。
		 * @param $name
		 * @return mixed
		 */
		public function __get($name) {
			if (!$this->di->has($name)) {
				$class = get_class($this);
				Sys::throwException("属性 $class::$name 未被定义");
			}
			return $this->di->get($name);
		}
		
		/**
		 * 针对于逻辑上的一条信息被分为常用信息与不常用信息，而常用信息存储于传统的(Trad)固定结构表中，而不常用信息被二进制序列化后存储于另一个
		 * 表中的二进制大型对象(Blob)型字段中这种典型设计时的通用的信息插入方法。
		 * @param string $tradModelClass 传统的固定结构表模型类。
		 * @param string $blobModelClass 二进制大型对象表模型类。
		 * @param array $info 需插入的信息。
		 * @param callable $callback 在数据校验之后然实际插入数据之前调用的回调函数，函数原型为：void callback(array $info)。
		 * @return string 新插入信息的ID。
		 */
		protected function insertForTradBlob($tradModelClass, $blobModelClass, array $info, $callback = null) {
			// 预插入常用信息。
			$tradModel = new $tradModelClass();
			$tradModel->preCreate($info);
			
			// 预插入不常用信息。
			$blobInfo = array_diff_key($info, $tradModel->getReverseTableFields());
			if (!empty($blobInfo)) {
				$blobModel = new $blobModelClass();
				$blobField = $blobModel->getBlobField();
				if (empty($blobField)) {
					Sys::throwException("模型类 $blobModelClass 未设置二进制大型对象(又称二进制字符串)型字段名称");
				}
				$blobModel->preCreate(array(
					$blobField => $blobInfo
				));
			}
			
			// 调用回调函数。
			if (is_callable($callback)) {
				$callback($info);
			}
			
			// 实插入信息。
			try {
				// 实插入常用信息。
				$tradModel->create();
				$tradModelSuccess = true;
				
				// 实插入不常用信息。
				if (isset($blobModel)) {
					$blobModel->id = $tradModel->id;
					$blobModel->create();
					$blobModelSuccess = true;
				}
				else {
					$blobModelSuccess = false;
				}
				
				// 插入信息逻辑索引。
				$info['id'] = $tradModel->id;
				$this->insertIndex($info);
			}
			catch (\Exception $e) {
				if (isset($tradModelSuccess)) {
					if (isset($blobModelSuccess)) {
						$this->deleteIndex($info);
						if ($blobModelSuccess) {
							$blobModel->delete();
						}
					}
					$tradModel->delete();
				}
				throw $e;
			}
			
			return $tradModel->id;
		}
		
		/**
		 * 针对于逻辑上的一条信息被分为常用信息与不常用信息，而常用信息存储于传统的(Trad)固定结构表中，而不常用信息被二进制序列化后存储于另一个
		 * 表中的二进制大型对象(Blob)型字段中这种典型设计时的通用的信息查询方法。
		 * @param string $tradModelClass 传统的固定结构表模型类。
		 * @param string $blobModelClass 二进制大型对象表模型类。
		 * @param string $id 信息ID。
		 * @return array
		 */
		protected function selectForTradBlob($tradModelClass, $blobModelClass, $id) {
			$tradModel = $tradModelClass::findFirst(array(
				'conditions' => 'id=:id:', 
				'bind' => array(
					'id' => $id
				)
			));
			if (!$tradModel) {
				return array();
			}
			$blobModel = $blobModelClass::findFirst(array(
				'conditions' => 'id=:id:', 
				'bind' => array(
					'id' => $id
				)
			));
			if ($blobModel) {
				$blobField = $blobModel->getBlobField();
				if (empty($blobField)) {
					Sys::throwException("模型类 $blobModelClass 未设置二进制大型对象(又称二进制字符串)型字段名称");
				}
				$blobValue = igbinary_unserialize($blobModel->$blobField);
				if (!is_array($blobValue)) {
					$blobValue = array();
				}
				$ret = array_merge($tradModel->toArray(), $blobValue);
			}
			else {
				$ret = $tradModel->toArray();
			}
			return $ret;
		}
		
		/**
		 * 针对于逻辑上的一条信息被分为常用信息与不常用信息，而常用信息存储于传统的(Trad)固定结构表中，而不常用信息被二进制序列化后存储于另一个
		 * 表中的二进制大型对象(Blob)型字段中这种典型设计时的通用的信息更新方法。
		 * @param string $tradModelClass 传统的固定结构表模型类。
		 * @param string $blobModelClass 二进制大型对象表模型类。
		 * @param array $info 必须的新的部分信息。
		 * @param array $oldInfo 可选的旧的完整信息。
		 * @param callable $callback 在校验数据之后然实际更新数据之前调用的回调函数，函数原型为：void callback(array $oldInfo, array $newInfo)。
		 * @return void
		 */
		protected function updateForTradBlob($tradModelClass, $blobModelClass, array $info, array $oldInfo = null, $callback = null) {
			// 读取旧的信息。
			$tradModel = new $tradModelClass();
			$tradModelReverseFields = $tradModel->getReverseTableFields();
			$blobFieldData = array_diff_key($info, $tradModelReverseFields);
			if (empty($oldInfo)) {
				if ($blobFieldData) {
					$oldInfo = $this->select($info['id']);
				}
				else {
					$model = $tradModelClass::findFirst(array(
						'conditions' => 'id=:id:', 
						'bind' => array(
							'id' => $info['id']
						)
					));
					$oldInfo = $model->toArray();
				}
			}
			if (!$oldInfo) {
				return;
			}
			
			// 预更新常用信息。
			$tradModel->preUpdate($info);
			
			// 预更新不常用信息。
			$newInfo = array_replace($oldInfo, $info);
			if ($blobFieldData) {
				$blobModel = new $blobModelClass();
				$blobField = $blobModel->getBlobField();
				if (empty($blobField)) {
					Sys::throwException("模型类 $blobModelClass 未设置二进制大型对象(又称二进制字符串)型字段名称");
				}
				$realBlobField = '_' . $blobField; // 保存真实要更新的BLOB型字段数据的字段名称。
				$blobModel->preUpdate(array(
					'id' => $info['id'], 
					$blobField => $blobFieldData, 
					$realBlobField => array_diff_key($newInfo, $tradModelReverseFields)
				));
			}
			
			// 调用回调函数。
			if (is_callable($callback)) {
				$callback($oldInfo, $newInfo);
			}
			
			// 实更新信息。
			try {
				// 实更新常用信息。
				$tradModel->update();
				$tradModelSuccess = true;
				
				// 实更新不常用信息。
				if (isset($blobModel)) {
					$blobModel->update();
					$blobModelSuccess = true;
				}
				else {
					$blobModelSuccess = false;
				}
				
				// 更新信息逻辑索引。
				$this->updateIndex($oldInfo, $newInfo);
			}
			catch (\Exception $e) {
				if (isset($tradModelSuccess)) {
					// 回滚以前更新的数据。
					if (!isset($blobModelSuccess)) {
						$tradModel->update($oldInfo);
					}
					else {
						if (!$blobModelSuccess) {
							$oldInfo = array_replace($this->select($info['id']), $oldInfo);
							$newInfo = array_replace($oldInfo, $newInfo);
						}
						$this->deleteIndex($oldInfo);
						$this->deleteIndex($newInfo);
						$this->insertIndex($newInfo);
					}
				}
				throw $e;
			}
		}
		
		/**
		 * 针对于逻辑上的一条信息被分为常用信息与不常用信息，而常用信息存储于传统的(Trad)固定结构表中，而不常用信息被二进制序列化后存储于另一个
		 * 表中的二进制大型对象(Blob)型字段中这种典型设计时的通用的信息删除方法。
		 * @param string $tradModelClass 传统的固定结构表模型类。
		 * @param string $blobModelClass 二进制大型对象表模型类。
		 * @param string $id 信息ID。
		 * @param callable $callback 在查询出旧的数据之后然实际删除数据之前调用的回调函数，函数原型为：void callback(array $oldInfo)。
		 * @return void
		 */
		protected function deleteForTradBlob($tradModelClass, $blobModelClass, $id, $callback = null) {
			// 查询旧的信息。
			$oldInfo = $this->selectForTradBlob($tradModelClass, $blobModelClass, $id);
			if (!$oldInfo) {
				return;
			}
			
			// 调用回调函数。
			if (is_callable($callback)) {
				$callback($oldInfo);
			}
			
			// 删除信息索引。
			$this->deleteIndex($oldInfo);
			
			// 删除信息内容。
			$tradModel = new $tradModelClass();
			$tradModel->id = $id;
			$tradModel->delete();
			$blobModel = new $blobModelClass();
			$blobModel->id = $id;
			$blobModel->delete();
		}
		
		/**
		 * 插入信息逻辑索引。
		 * @param array $info 完整的信息。
		 * @param array $type 自定义类型。
		 * @return void
		 */
		protected function insertIndex(array $info, array $type = null) {
		}
		
		/**
		 * 更新信息逻辑索引。
		 * @param array $oldInfo 旧的完整或仅常用信息。
		 * @param array $newInfo 新的完整或仅常用信息。
		 * @param array $type 自定义类型。
		 * @return void
		 */
		protected function updateIndex(array $oldInfo, array $newInfo, array $type = null) {
		}
		
		/**
		 * 删除信息逻辑索引。
		 * @param array $info 完整的信息。
		 * @param array $type 自定义类型。
		 * @return void
		 */
		protected function deleteIndex(array $info, array $type = null) {
		}
	}
}

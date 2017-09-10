<?php
namespace App\Mvc {
	use App\System as Sys;
	use App\PHPX;
	use App\Exception;
	use Phalcon\Mvc\Model\Message;

	/**
	 * 应用程序模型基类。
	 * 
	 * 思想指导：此类模型映射或说代表的是表格、模型实例映射或说代表的是记录，因其实例具有更新、删除、插入自身数据的行为，就像一个有生命力
	 * 
	 * 的东西一样，所以在此把这种技术叫做ActiveRecord技术，为了使用面向对象化的操作方式来操作记录及字段，同时又使用了ORM技术，即对象与关系
	 * 
	 * 的映射技术。由于使用ActiveRecord技术对数据进行更新、删除的时候首先需要把数据查询出来，所以建议此模型一般只用于查询、插入的情况下，
	 * 
	 * 不要用于更新、删除，当然特少量的数据除外。
	 * 
	 * 建议：高级模型名用复数形式，而普通模型名用单数形式，因为高级模型实例代表的是多个表格组成的整体，自然包含了多条记录，而普通模型实例
	 * 仅代表的是单条记录，所以前者用复数，后者用单数易于理解，如 Users 代表用户高级模型，而 User 代表用户普通模型。
	 */
	abstract class Model extends \Phalcon\Mvc\Model {
		/**
		 * 校验规则在创建记录时生效，但对字段的校验不是必须的。
		 */
		const CREATE_NO_REQUIRED = 1;
		const CREATE_IS_REQUIRED = 2;
		
		/**
		 * 校验规则在更新记录时生效，但对字段的校验不是必须的。
		 */
		const UPDATE_NO_REQUIRED = 4;
		const UPDATE_IS_REQUIRED = 8;
		
		/**
		 * 校验规则生效时间的组合。
		 */
		const ALL_NO_REQUIRED = 5;
		const ALL_IS_REQUIRED = 10;
		
		/**
		 * 是否启用了列映射功能。
		 */
		protected static $enableColumnMap = false;
		
		/**
		 * 校验规则集。
		 */
		protected static $validationRules = null;
		
		/**
		 * 物理表字段集。
		 */
		protected static $tableFields = null;
		protected static $reverseTableFields = null;
		
		/**
		 * 二进制大型对象(又称二进制字符串)型字段。
		 */
		protected static $blobField = null;
		
		/**
		 * 数据库连接服务名称。
		 */
		protected static $connectionService = null;
		
		/**
		 * 数据库读连接服务名称。
		 */
		protected static $readConnectionService = null;
		
		/**
		 * 数据库写连接服务名称。
		 */
		protected static $writeConnectionService = null;
		
		/**
		 * 模块目录。
		 */
		protected static $moduleDirs = null;
		
		/**
		 * 模块名称。
		 */
		protected static $moduleNames = null;
		
		/**
		 * 初始标志。
		 */
		protected static $initialized = null;
		
		/**
		 * 模型的全局唯一标识符。
		 */
		protected $modelGUID = null;
		
		/**
		 * 是否禁用数据验证功能。
		 */
		protected $disableValidation = false;
		
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
		 * 启用还是禁用列映射功能，系统默认是禁用了列映射功能。
		 * @param boolean $value
		 * @return void
		 */
		public static function enableColumnMap($value = true) {
			self::$enableColumnMap = ($value == true);
			self::setup(array(
				'columnRenaming' => self::$enableColumnMap
			));
		}
		
		/**
		 * 析构函数。
		 */
		final public function __destruct() {
		}
		
		/**
		 * 初始化模型(注意：此方法原本只会被调用一次，因为它所作的本应属于类静态方法所作，但现在已被应用程序模型管理器改为了每次实例化后都会被调用)。
		 * @return void
		 */
		public function initialize() {
			// 初始化模型实例。
			$guid = $this->getModelGUID();
			if (!isset(self::$initialized[$guid])) {
				// 设置模块信息。
				$moduleDir = dirname(dirname((new \ReflectionObject($this))->getFileName()));
				$moduleName = null;
				if ($moduleDir != APP_ROOT) {
					$moduleName = substr($moduleDir, strrpos($moduleDir, DIRECTORY_SEPARATOR) + 1);
				}
				self::$moduleDirs[$guid] = $moduleDir;
				self::$moduleNames[$guid] = $moduleName;
				
				// 设置模型相关参数。
				self::setup(array(
					// 当为true时Phalcon会校验不为空的字段在插入/更新时是否设置了值，如果没有设置值或设置了一个空字符串将会引发校验失败。
					'notNullValidations' => false, 
					'columnRenaming' => self::$enableColumnMap, 
					'exceptionOnFailedSave' => true
				));
				
				// 设置模型数据库连接服务名称。
				$this->setConnectionService('db');
				$this->setReadConnectionService('rdb');
				$this->setWriteConnectionService('wdb');
				if (!empty(self::$moduleNames[$guid])) {
					$moduleNameLC = lcfirst(self::$moduleNames[$guid]);
					$this->setConnectionService($moduleNameLC . 'Db');
					$this->setReadConnectionService($moduleNameLC . 'RDb');
					$this->setWriteConnectionService($moduleNameLC . 'WDb');
				}
				
				// 设置模型仅只用修改过的字段来构建更新SQL语句，以减少应用程序与数据库间的传输量从而提高性能。
				$this->useDynamicUpdate(true);
				
				// 设置已初始化标志。
				self::$initialized[$guid] = true;
			}
			$this->moduleDir = self::$moduleDirs[$guid];
			$this->moduleName = self::$moduleNames[$guid];
			if (empty($this->moduleName)) {
				$this->config = Sys::getConfig()->global;
			}
			else {
				$this->config = Sys::getConfig()->get($this->moduleName);
			}
			$this->di = Sys::getInstance()->getDI();
			
			// 安装终止化方法。
			if (method_exists($this, 'finalize')) {
				register_shutdown_function(array(
					$this, 
					'finalize'
				));
			}
		}
		
		/**
		 * 获取模型的全局唯一标识符。
		 * @return string
		 */
		protected function getModelGUID() {
			if (empty($this->modelGUID)) {
				$this->modelGUID = get_class($this);
			}
			return $this->modelGUID;
		}
		
		/**
		 * 获取物理表字段集。
		 * @return array
		 */
		public function getTableFields() {
			$guid = $this->getModelGUID();
			if (!isset(self::$tableFields[$guid])) {
				self::$tableFields[$guid] = $this->getModelsMetaData()->getAttributes($this);
			}
			return self::$tableFields[$guid];
		}
		
		/**
		 * 获取反转后的物理表字段集。
		 * @return array
		 */
		public function getReverseTableFields() {
			$guid = $this->getModelGUID();
			if (!isset(self::$reverseTableFields[$guid])) {
				self::$reverseTableFields[$guid] = array_flip($this->getTableFields());
			}
			return self::$reverseTableFields[$guid];
		}
		
		/**
		 * 设置数据库连接服务名称。
		 * @param string $connectionService
		 * @return void
		 */
		public function setConnectionService($connectionService) {
			if ($this->getDI()->has($connectionService)) {
				parent::setConnectionService($connectionService);
				$guid = $this->getModelGUID();
				self::$connectionService[$guid] = $connectionService;
			}
		}
		
		/**
		 * 设置数据库读连接服务名称。
		 * @param string $connectionService
		 * @return void
		 */
		public function setReadConnectionService($connectionService) {
			if ($this->getDI()->has($connectionService)) {
				parent::setReadConnectionService($connectionService);
				$guid = $this->getModelGUID();
				self::$readConnectionService[$guid] = $connectionService;
			}
		}
		
		/**
		 * 设置数据库写连接服务名称。
		 * @param string $connectionService
		 * @return void
		 */
		public function setWriteConnectionService($connectionService) {
			if ($this->getDI()->has($connectionService)) {
				parent::setWriteConnectionService($connectionService);
				$guid = $this->getModelGUID();
				self::$writeConnectionService[$guid] = $connectionService;
			}
		}
		
		/**
		 * 获取数据库连接服务名称。
		 * @return string
		 */
		public function getConnectionService() {
			$guid = $this->getModelGUID();
			if (isset(self::$connectionService[$guid])) {
				return self::$connectionService[$guid];
			}
		}
		
		/**
		 * 获取数据库读连接服务名称。
		 * @return string
		 */
		public function getReadConnectionService() {
			$guid = $this->getModelGUID();
			if (isset(self::$readConnectionService[$guid])) {
				return self::$readConnectionService[$guid];
			}
		}
		
		/**
		 * 获取数据库写连接服务名称。
		 * @return string
		 */
		public function getWriteConnectionService() {
			$guid = $this->getModelGUID();
			if (isset(self::$writeConnectionService[$guid])) {
				return self::$writeConnectionService[$guid];
			}
		}
		
		/**
		 * 设置二进制大型对象(又称二进制字符串)型字段名称，以便于在保存数据前自动对其进行二进制序列化操作。
		 * @param string $name
		 * @return void
		 */
		protected function setBlobField($name) {
			if (!empty($name)) {
				$guid = $this->getModelGUID();
				self::$blobField[$guid] = $name;
			}
		}
		
		/**
		 * 获取二进制大型对象(又称二进制字符串)型字段名称。
		 * @return string
		 */
		public function getBlobField() {
			$guid = $this->getModelGUID();
			if (isset(self::$blobField[$guid])) {
				return self::$blobField[$guid];
			}
		}
		
		/**
		 * 处理数据中的BLOB型字段。
		 * @param array $data
		 * @return void
		 */
		protected function handleBlobField(array $data = null) {
			// 此处$data必须是个数组，但是参数说明中却又给了一个默认值null，即允许为null，仅仅是想把参数的校验全放在此函数中，以方便高层使用。
			if (!is_array($data)) {
				return;
			}
			$guid = $this->getModelGUID();
			if (isset(self::$blobField[$guid])) {
				$blobField = self::$blobField[$guid];
				if (isset($data[$blobField])) {
					// Phalcon框架不允许给不存在的属性设置一个数组，但可以给已存在的属性设置，所以先设置一个空属性，以便于以后给它设置数组值。
					$this->$blobField = null;
					$realBlobField = '_' . $blobField;
					if (isset($data[$realBlobField])) {
						$this->$realBlobField = null;
					}
				}
			}
		}
		
		/**
		 * 创建数据之前被调用的事件处理函数。
		 * @return void
		 */
		public function beforeCreate() {
			$this->serializeBlobField();
			$this->skipUndefinedFields();
		}
		
		/**
		 * 更新数据之前被调用的事件处理函数。
		 * @return void
		 */
		public function beforeUpdate() {
			$this->serializeBlobField();
			$this->skipUndefinedFields();
		}
		
		/**
		 * 序列化二进制大型对象型字段对应的数组。
		 * @return void
		 */
		protected function serializeBlobField() {
			$guid = $this->getModelGUID();
			if (isset(self::$blobField[$guid]) && property_exists($this, self::$blobField[$guid])) {
				$blobField = self::$blobField[$guid];
				$realBlobField = '_' . $blobField;
				if (property_exists($this, $realBlobField) && is_array($this->$realBlobField)) {
					$this->$blobField = igbinary_serialize($this->$realBlobField);
				}
				elseif (is_array($this->$blobField)) {
					$this->$blobField = igbinary_serialize($this->$blobField);
				}
				else {
					$this->$blobField = igbinary_serialize(array());
				}
			}
		}
		
		/**
		 * 跳过对未在当前模型中定义的字段的写入操作，仅用于实现部分字段的插入与更新。默认行为是：全部表字段都需要写入，只是未明确赋值的字段值是NULL。
		 * @return void
		 */
		protected function skipUndefinedFields() {
			$fields = array_keys(get_object_vars($this));
			if (self::$enableColumnMap) {
				$skipFields = array_keys(array_diff($this->columnMap(), $fields));
			}
			else {
				$skipFields = array_diff($this->getTableFields(), $fields);
			}
			
			// 参数true的意思是替换掉以前已跳过的字段，即本次跳过的字段与上次跳过的字段没有任何关系，这样同一模型的不同实例就可以跳过不同的字段了。
			$this->skipAttributes($skipFields, true);
		}
		
		/**
		 * 为创建一条数据做设置属性、校验字段的预处理工作。用于实现向多个表中创建数据时先对其所有数据进行校验而后进行仅创建的功能，以避免向一个表中创
		 * 建数据成功了，而向另一个表中创建数据时却因数据校验失败而终止，从而导致了对先前创建的数据需要回滚的麻烦。注意：在调用了此方法后调用create方
		 * 法时就不需要给它们传递参数了，如若传递了也并不会出错，但却浪费了处理时间。
		 * @param array $data
		 * @param array $whiteList 指明$data中哪些项是需要写入数据库的，通常用于$data是$_POST的情况下。
		 * @return void
		 */
		public function preCreate(array $data = null, array $whiteList = null) {
			$this->_operationMade = self::OP_CREATE;
			$this->preSave($data, $whiteList);
		}
		
		/**
		 * 为更新一条数据做设置属性、校验字段的预处理工作。用于实现向多个表中更新数据时先对其所有数据进行校验而后进行仅更新的功能，以避免向一个表中更
		 * 新数据成功了，而向另一个表中更新数据时却因数据校验失败而终止，从而导致了对先前更新的数据需要回滚的麻烦。注意：在调用了此方法后调用update方
		 * 法时就不需要给它们传递参数了，如若传递了也并不会出错，但却浪费了处理时间。
		 * @param array $data
		 * @param array $whiteList 指明$data中哪些项是需要写入数据库的，通常用于$data是$_POST的情况下。
		 * @return void
		 */
		public function preUpdate(array $data = null, array $whiteList = null) {
			$this->_operationMade = self::OP_UPDATE;
			$this->preSave($data, $whiteList);
		}
		
		/**
	     * 为保存一条数据做设置属性、校验字段的预处理工作。
		 * @param array $data
		 * @param array $whiteList
		 * @return void
		 */
		protected function preSave(array $data = null, array $whiteList = null) {
			if (is_array($data)) {
				$this->handleBlobField($data);
				if (!is_array($whiteList)) {
					$whiteList = array_keys($data);
				}
				foreach ($whiteList as $field) {
					if (array_key_exists($field, $data) && (is_scalar($data[$field]) || property_exists($this, $field))) {
						$this->$field = $data[$field];
					}
				}
			}
			$this->disableValidation = false;
			if (!$this->validation()) {
				Sys::throwException($this->_errorMessages[0]->getMessage(), 2);
			}
			$this->disableValidation = true;
		}
		
		/**
		 * 插入一条记录。
		 * @param array $data
		 * @param array $whiteList 指明$data中哪些项是需要写入数据库的，通常用于$data是$_POST的情况下。
		 * @return true 原返回值是boolean值，现在改为了创建失败时会抛出异常。
		 */
		public function create($data = null, $whiteList = null) {
			$this->handleBlobField($data);
			try {
				$ret = parent::create($data, $whiteList);
			}
			catch (\Exception $e) {
				if (!($e instanceof Exception)) {
					Sys::throwException($e->getMessage());
				}
				else {
					throw $e;
				}
			}
			if (!$ret) {
				Sys::throwException($this->_errorMessages[0]->getMessage());
			}
			return true;
		}
		
		/**
		 * 更新一条记录。
		 * @param array $data
		 * @param array $whiteList 指明$data中哪些项是需要写入数据库的，通常用于$data是$_POST的情况下。
		 * @return true 原返回值是boolean值，现在改为了更新失败时会抛出异常。
		 */
		public function update($data = null, $whiteList = null) {
			$this->handleBlobField($data);
			try {
				$ret = parent::update($data, $whiteList);
			}
			catch (\Exception $e) {
				if (!($e instanceof Exception)) {
					Sys::throwException($e->getMessage());
				}
				else {
					throw $e;
				}
			}
			if (!$ret) {
				Sys::throwException($this->_errorMessages[0]->getMessage());
			}
			return true;
		}
		
		/**
		 * 删除一条记录。
		 * @return true 原返回值是boolean值，现在改为了删除失败时会抛出异常。
		 */
		public function delete() {
			try {
				$ret = parent::delete();
			}
			catch (\Exception $e) {
				if (!($e instanceof Exception)) {
					Sys::throwException($e->getMessage());
				}
				else {
					throw $e;
				}
			}
			if (!$ret) {
				Sys::throwException($this->_errorMessages[0]->getMessage());
			}
			return true;
		}
		
		/**
		 * 校验数据(发生在 beforeValidation 与 afterValidation 事件之间，注意：有一个校验规则失败，就会停止后续所有校验)。
		 * @return boolean
		 */
		public function validation() {
			if ($this->disableValidation) {
				return true;
			}
			
			// 遍历校验规则对字段进行校验。
			$guid = $this->getModelGUID();
			if (!isset(self::$validationRules[$guid])) {
				return true;
			}
			$operation = $this->getOperationMade();
			foreach (self::$validationRules[$guid] as $field => &$rules) {
				foreach ($rules as &$rule) {
					// 判断是否需要对字段进行校验。
					$flag = true;
					if (preg_match('#^(\w+)\.(\w+)$#', $field, $match)) {
						// 处理如bin.field格式的字段，它代表的是bin[field]数组值。
						$firstField = $match[1];
						$secondField = $match[2];
						$flag1 = true; // 字段不存在。
						if (property_exists($this, $firstField) && array_key_exists($secondField, $this->$firstField)) {
							$firstValue = $this->$firstField;
							$this->$field = null; // Phalcon框架不允许给不存在的属性设置一个数组，但可以给已存在的属性设置，所以先设置一个空属性。
							$this->$field = $firstValue[$secondField];
							$flag1 = false;
						}
					}
					else {
						$flag1 = !property_exists($this, $field);
					}
					$flag2 = $flag1 && (($rule['required'] & self::ALL_NO_REQUIRED) == self::ALL_NO_REQUIRED);
					$flag3 = $flag1 && !$flag2 && ($operation == self::OP_CREATE && ($rule['required'] & self::CREATE_NO_REQUIRED));
					$flag4 = $flag1 && !$flag3 && ($operation == self::OP_UPDATE && ($rule['required'] & self::UPDATE_NO_REQUIRED));
					if ($flag1 && ($flag2 || $flag3 || $flag4)) {
						$flag = false;
					}
					if (!$flag) {
						continue;
					}
					
					// 校验字段是否已被设置。
					if ($flag1) {
						if (empty($rule['validator']) && is_string($rule['optionsOrMessage']) && !empty($rule['optionsOrMessage'])) {
							$message = $rule['optionsOrMessage'];
						}
						else {
							$message = "字段未被定义";
						}
						$this->_errorMessages = array(
							new Message($message . "(字段名={$field})")
						);
						return false;
					}
					if (empty($rule['validator'])) {
						continue;
					}
					
					// 使用可执行体对字段进行校验。
					if (is_callable($rule['validator']) && (is_string($rule['optionsOrMessage']) && !empty($rule['optionsOrMessage']) || isset($rule['optionsOrMessage']['message']) && !empty($rule['optionsOrMessage']['message']))) {
						$func = $rule['validator'];
						if (!$func($this->$field, $rule['optionsOrMessage'])) {
							$fieldValue = PHPX::strval($this->$field);
							if (is_string($rule['optionsOrMessage'])) {
								$message = $rule['optionsOrMessage'];
							}
							else {
								$message = $rule['optionsOrMessage']['message'];
							}
							$this->_errorMessages = array(
								new Message($message . "(字段名={$field}，字段值={$fieldValue})")
							);
							return false;
						}
						continue;
					}
					
					// 使用校验器类对字段进行校验(把此段放在最后是因为不想把数据类型名称尝试视为类进行加载，毕竟查找类需要耗费比较多的时间)。
					if (is_string($rule['validator']) && class_exists($rule['validator']) && isset($rule['optionsOrMessage']['message']) && !empty($rule['optionsOrMessage']['message'])) {
						if (empty($rule['validatorObject'])) {
							$rule['optionsOrMessage']['field'] = $field;
							$rule['validatorObject'] = new $rule['validator']($rule['optionsOrMessage']);
						}
						$this->validate($rule['validatorObject']);
						if ($this->validationHasFailed()) {
							$fieldValue = PHPX::strval($this->$field);
							$message = $rule['optionsOrMessage']['message'];
							$this->_errorMessages[0]->setMessage($message . "(字段名={$field}，字段值={$fieldValue})");
							return false;
						}
					}
					else {
						Sys::throwException("设置给字段的校验器或选项无效(字段名={$field})");
					}
				}
			}
			return true;
		}
		
		/**
		 * 添加校验规则，建议校验原则是：
		 * (1) 对于从模块外部来的有限定要求的数据都需要对其进行校验，因为从外部来的任何一项数据都有可能是无效数据，而除此之外的所有数据则都无需进行校验；
		 * (2) 对于方便交给数据库去进行校验的数据，就交给数据库去完成，这样我们就不用编写一大堆的代码去校验数据了，如对无符号数字、枚举类型字段的校验等；
		 * (3) 假定所有数据类型或结构都是正确的，而仅仅只校验数据的内容，因为当我们在使用这些数据的时候，如果数据类型或结构有误，系统底层会自动进行报错。
		 * @param string $field 要校验的字段名称，可用firstField.secondField格式引用数组中的字段。
		 * @param string|callable $validator 校验器全限定类名称(应为Phalcon\Mvc\Model\ValidatorInterface的实现)、或一个可执行体(用于一些特殊的校验时候，但具有通用
		 * 性的校验过程应封装成校验器类，函数原型为：boolean function(字段值, 选项值) 且返回true时代表校验成功、但当参数为空时代表仅校验字段的存在性。
		 * @param array|string $optionsOrMessage 校验器构造选项(要校验的字段名称默认会自动添加进去)或错误消息。
		 * @param integer $requied 是否必须校验这个字段，在不是必须的情况下，当字段不存在于模型中时就会跳过该校验，但是当它存在时一定会对其进行校验。
		 * 取值为Model::CREATE_*、Model:UPDATE_*、Model:ALL_*系列常量或其组合，默认所有校验规则都是必须的。
		 * @return void
		 */
		protected function addValidationRule($field, $validator = null, $optionsOrMessage = null, $requied = self::ALL_IS_REQUIRED) {
			if (empty($field)) {
				Sys::throwException('待校验字段名称不能为空');
			}
			$ruleOptions = array(
				'validator' => $validator, 
				'optionsOrMessage' => $optionsOrMessage, 
				'required' => $requied, 
				'validatorObject' => null
			);
			$guid = $this->getModelGUID();
			self::$validationRules[$guid][$field][] = $ruleOptions;
		}
		
		/**
		 * 当调用了不存在的方法时调用。
		 * @param string $name 方法名称。
		 * @param array $arguments 方法参数。
		 * @return mixed
		 */
		public function __call($name, $arguments = null) {
			$class = get_class($this);
			Sys::throwException("方法 $class::$name() 未被定义");
		}
		
		/**
		 * 当获取不存在的属性时调用。
		 * @param string $name
		 * @return mixed
		 */
		public function __get($name) {
			if (!$this->di->has($name)) {
				$oldErrorLevel = error_reporting();
				error_reporting(0);
				$ret = parent::__get($name);
				error_reporting($oldErrorLevel);
				if ($ret === null) {
					$class = get_class($this);
					Sys::throwException("属性 $class::$name 未被定义");
				}
			}
			else {
				$ret = $this->di->get($name);
			}
			return $ret;
		}
	}
}

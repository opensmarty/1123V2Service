<?php
namespace App\Db\Adapter\Pdo {
	use Phalcon\Events\Manager as EventsManager;
	use Phalcon\Logger\Adapter\File as FileLogger;
	use Phalcon\Logger;
	use App\System as Sys;
	use App\FileSystem as FS;

	/**
	 * 应用程序MySQL驱动类。
	 */
	class Mysql extends \Phalcon\Db\Adapter\Pdo\Mysql {
		/**
		 * 相对于应用程序根目录的默认SQL日志文件路径。
		 */
		const DEFAULT_SQL_LOG_FILE_PATH = 'runtime/logs/sql.log';
		
		/**
		 * 设置内部的SQL语句，仅用于应用程序框架内部，开发者不要调用它。
		 * @param string $sqlStatement
		 * @return void
		 */
		public function setInnerSQLStatement($sqlStatement) {
			$this->_sqlStatement = $sqlStatement;
		}
		
		/**
		 * 启动记录连接执行的SQL语句的功能，一般仅用于测试或调试目的。
		 * @param string $sqlLogFile SQL日志文件路径，可以是相对于应用程序根目录的路径。
		 * @return void
		 */
		public function startSQLLog($sqlLogFile = self::DEFAULT_SQL_LOG_FILE_PATH) {
			$sqlLogFile = FS::realpathEx($sqlLogFile);
			$sqlLogFileDir = dirname($sqlLogFile);
			if (!is_dir($sqlLogFileDir) && mkdir($sqlLogFileDir, 0700, true) === false) {
				Sys::throwException("创建SQL日志文件目录 $sqlLogFileDir 时失败");
			}
			
			$eventsManager = new EventsManager();
			$logger = new FileLogger($sqlLogFile);
			$eventsManager->attach('db', function ($event, $connection) use($logger) {
				if ($event->getType() == 'beforeQuery') {
					$logger->log($connection->getSQLStatement(), Logger::INFO);
				}
			});
			$this->_eventsManager = $eventsManager;
		}
		
		/**
		 * 停止记录连接执行的SQL语句的功能。
		 * @return void
		 */
		public function stopSQLLog() {
			$this->_eventsManager = null;
		}
	}
}
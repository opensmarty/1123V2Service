<?php
namespace App\Events {
	/**
	 * 应用程序事件管理器。
	 */
	class Manager extends \Phalcon\Events\Manager {
		/**
		 * 移除事件监听器。
	 	 * @param string $type 要移除的事件类型，当$type参数为null时，将移除所有的事件监听器。
	     * @param object $listener 要移除的事件监听器，当$listener参数为null时，将移除与$type关联的所有事件监听器。
		 * @return void
		 */
		public function detach($type = null, $listener = null) {
			if (empty($type) || empty($listener)) {
				parent::detachAll($type);
			}
			else {
				$listenerQueue = new \SplPriorityQueue();
				$this->_events[$type]->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
				foreach ($this->_events[$type] as $val) {
					if ($val['data'] != $listener) {
						$listenerQueue->insert($val['data'], $val['priority']);
					}
				}
				if (count($listenerQueue) == 0) {
					unset($this->_events[$type]);
				}
				else {
					$this->_events[$type] = $listenerQueue;
				}
			}
		}
	}
}
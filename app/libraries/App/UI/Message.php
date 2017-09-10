<?php
namespace App\UI {
	use App\System as Sys;

	/**
	 * 消息输出类。
	 */
	class Message {
		const GOTO_BACK = 'goto_back';
		const GOTO_REFERER = 'goto_referer';
		const CLOSE_WINDOW = 'close_window';
		
		/**
		 * 输出脚本消息。
		 */
		public static function outputScript($script) {
			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
			echo '<html><head>';
			echo '<script language="javascript">' . $script . '</script>';
			echo '</head><body></body></html>';
		}
		
		/**
		 * 打印提示消息。
		 */
		public static function printTips($tips, $action = self::GOTO_BACK) {
			$tips = str_replace('"', '\"', $tips);
			if ($action == self::GOTO_BACK) {
				self::outputScript('alert("' . $tips . '");history.back();');
			}
			elseif ($action == self::GOTO_REFERER) {
				self::outputScript('alert("' . $tips . '");location.href="' . $_SERVER['HTTP_REFERER'] . '";');
			}
			elseif ($action == self::CLOSE_WINDOW) {
				self::outputScript('alert("' . $tips . '");window.close();');
			}
			else {
				self::outputScript('alert("' . $tips . '");location.href="' . $action . '";');
			}
			exit();
		}
	}
}

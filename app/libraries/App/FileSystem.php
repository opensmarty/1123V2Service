<?php
namespace App {
	use App\System as Sys;
	use App\Cache\APC;

	/**
	 * 文件系统类。
	 */
	class FileSystem {
		/**
		 * 递归扫描目录并获取目录里面的所有子目录及文件。
		 * @param string $baseDir 需扫描的基本目录。
		 * @param boolean $stripBaseDir 是否剥掉基本目录前缀，默认为false。
		 * @return array dirs项为所有目录列表、files项为所有文件列表。
		 */
		public static function scandirEx($baseDir, $stripBaseDir = false) {
			static $nestLevel = 0;
			static $ret = null;
			if ($nestLevel == 0) {
				$ret = array(
					'dirs' => array(), 
					'files' => array()
				);
				$baseDir = rtrim($baseDir, '\\/');
			}
			$nestLevel++;
			if (is_dir($baseDir)) {
				$ret['dirs'][] = $baseDir;
				$dirs = scandir($baseDir);
				foreach ($dirs as $dir) {
					if ($dir != '.' && $dir != '..') {
						$path = $baseDir . DIRECTORY_SEPARATOR . $dir;
						if (is_dir($path)) {
							self::scandirEx($path);
						}
						else {
							$ret['files'][] = $path;
						}
					}
				}
			}
			$nestLevel--;
			if ($nestLevel == 0) {
				if ($stripBaseDir) {
					$baseDirLen = strlen($baseDir) + 1;
					unset($ret['dirs'][0]);
					foreach ($ret['dirs'] as &$dir) {
						$dir = substr($dir, $baseDirLen);
					}
					foreach ($ret['files'] as &$file) {
						$file = substr($file, $baseDirLen);
					}
				}
				return $ret;
			}
		}
		
		/**
		 * 校验目录下面是否有PHP文件。
		 * @return boolean
		 */
		public static function hasPHPFile($dir) {
			$ret = false;
			if (is_dir($dir)) {
				$ret = APC::get($dir, filemtime($dir), function () use($dir) {
					$has = false;
					$paths = scandir($dir);
					foreach ($paths as $path) {
						if (substr($path, -4) == '.php') {
							$has = true;
							break;
						}
					}
					return $has;
				});
			}
			else {
				APC::delete($dir);
			}
			return $ret;
		}
		
		/**
		 * 获取一个路径的规范化后的绝对路径，它与realpath函数不同的是，此方法可以处理相对于系统私有根目录的路径，并且不要求路径是否真的存在，
		 * 并且还会去掉路径中的所有trim函数中定义的空白符及末尾的路径分隔符。提示：当路径存在的话，此方法会直接用realpath函数对其进行规范化。
		 * @param string $path 需要规范化的路径。
		 * @return string 不管在任何情况下都会返回一个规范化后的绝对路径，但是当$path参数为空时返回的是系统私有根目录。
		 */
		public static function realpathEx($path) {
			// 如果路径存在就直接用内置函数进行规范化。
			if (file_exists($path)) {
				return realpath($path);
			}
			
			// 去掉路径中的所有trim函数中定义的空白符。
			$path = preg_replace('#[\r\n\t\x00\x0B\x20]#', '', $path);
			if (strlen($path) == 0) {
				return Sys::getPrivateRoot();
			}
			
			// 替换所有由\/组成的串为当前系统的目录分隔符、所有二个.以上的串为两个.。
			$path = rtrim(preg_replace(array(
				'#[\\\\/]+#', 
				'#\.{3,}#'
			), array(
				DIRECTORY_SEPARATOR, 
				'..'
			), $path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			
			// 把路径变成绝对路径。
			$prefix = '';
			$pos = strpos($path, DIRECTORY_SEPARATOR);
			$prefix = substr($path, 0, $pos);
			if (strpos($prefix, ':') !== false) {
				if (!preg_match('#^[a-z]:#i', $prefix)) {
					$path = substr($path, $pos);
				}
			}
			elseif ($prefix == '.' || $prefix == '..') {
				$path = realpath('.') . DIRECTORY_SEPARATOR . $path;
			}
			elseif ($prefix != '') {
				$path = Sys::getPrivateRoot() . DIRECTORY_SEPARATOR . $path;
			}
			
			// 处理路径中的/./与/../。
			$ds = DIRECTORY_SEPARATOR;
			preg_match_all("#\\$ds([^\\$ds]+)#", $path, $match);
			$stack = array();
			foreach ($match[1] as $str) {
				if ($str == '.') {
					continue;
				}
				if ($str == '..') {
					array_pop($stack);
					continue;
				}
				array_push($stack, $str);
			}
			
			// 得到最终的路径。
			$pos = strpos($path, DIRECTORY_SEPARATOR);
			$ret = substr($path, 0, $pos + 1) . implode(DIRECTORY_SEPARATOR, $stack);
			return $ret;
		}
	}
}
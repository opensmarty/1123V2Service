<?php
namespace App\Utils {
	/**
	 * PHTML模板文件压缩类(UTF-8编码版)。
	 */
	class PHtmlCompressor {
		/**
		 * 压缩JS、CSS、HTML、PHP可共存的混写模板。
		 * @param string $data
		 * @return string
		 */
		public static function compress($data) {
			$data = trim($data);
			if (empty($data)) {
				return $data;
			}
			$ret = $data;

			/********************************************************************************/
			// 压缩用回调函数。
			/********************************************************************************/
			$compressCallback = function ($match) {
				// 设置所需参数。
				$suffix = '""';
				$suffixLen = 2;

				/****************************************************************************/
				// 压缩HTML部份。
				/****************************************************************************/
				$html = trim($match['html'], "\r\n\t\x20");
				if ($html != '') {
					// 转换HTML中<textarea></textarea>、<pre></pre>中的内容。
					$textareaPattern = array(
						'# (<textarea .*? (?<!\?) >) (.*?) (</textarea>) #isx', 
						'# (<pre .*? (?<!\?) >) (.*?) (</pre>) #isx'
					);
					$html = preg_replace_callback($textareaPattern, function ($match) {
						return $match[1] . '"' . str_replace('"', "\xFF", $match[2]) . '"' . $match[3];
					}, $html);

					// 替换HTML里面的空白符。
					$stringPatternHtml = '#  (?P<nostr>.*?)  (?P<string> \x00[^\x00]+\x00 | \'.*?\' | ".*?" )  #sx';
					$html = preg_replace_callback($stringPatternHtml, function ($match) {
						// 剥掉/>、<、>、=字符前后的所有空白符。
						static $replacePattern1 = '# \s* (/>|[<>=]) \s* #x';
						$match['nostr'] = preg_replace($replacePattern1, '$1', $match['nostr']);

						// 把其余的多个空白字符变成一个空白符。
						static $replacePattern2 = "#\s{2,}+#";
						$match['nostr'] = preg_replace($replacePattern2, "\n", $match['nostr']);

						return $match['nostr'] . $match['string'];
					}, $html . $suffix);
					$html = substr($html, 0, -$suffixLen);

					// 恢复HTML中<textarea></textarea>、<pre></pre>中的内容。
					$html = preg_replace_callback($textareaPattern, function ($match) {
						return $match[1] . str_replace("\xFF", '"', substr($match[2], 1, -1)) . $match[3];
					}, $html);
				}
				
				/****************************************************************************/
				// 压缩JS部份(c为script中的第二个字符)。
				/****************************************************************************/
				$nohtml = $match['nohtml'];
				if (strtolower(substr($nohtml, 1, 6)) == 'script') {
					// 剥掉JS里面的//、/**/注释。
					$nohtml = substr($nohtml, 0, -9); // -9 指的是 </script> 的长度，此句是为了防止把</script>也当作//后的注释了。
					$nohtml = preg_replace('# (?<!\\\\) // .* $ #mx', '', $nohtml) . '</script>';
					$nohtml = preg_replace('# (?<!\\\\) /\* .*? \*/ #sx', '', $nohtml);
					
					// 替换JS里面的空白符。
					$stringPatternJS = '#  (?P<nostr>(?s).*?) (?P<string> \x00[^\x00]+\x00 | \'.*?(?<!\\\\)\' | ".*?(?<!\\\\)" | /.+?(?<!\\\\)/(?=[gim\)\}\.,;\s]) )  #x';
					$nohtml = preg_replace_callback($stringPatternJS, function ($match) {
						// 剥掉+-*/%=?:;,&|!^~<>[](){}字符前后的所有空白符。
						static $replacePattern1 = '# \s* ([\+\-\*/%=\?:;,&\|!\^~<>\[\]\(\)\{\}]) \s* #x';
						$match['nostr'] = preg_replace($replacePattern1, '$1', $match['nostr']);
						
						// 把其余的多个空白字符变成一个空白符。
						static $replacePattern2 = "#\s{2,}+#";
						$match['nostr'] = preg_replace($replacePattern2, "\n", $match['nostr']);
						
						return $match['nostr'] . $match['string'];
					}, $nohtml . $suffix);
					$nohtml = substr($nohtml, 0, -$suffixLen);
				}
				
				/****************************************************************************/
				// 压缩CSS部份。
				/****************************************************************************/
				else {
					// 剥掉CSS里面的/**/注释。
					$nohtml = preg_replace('# /\* .*? \*/ #sx', '', $nohtml);
					
					// 替换CSS里面的空白符。
					$stringPatternCSS = '#  (?P<nostr>(?s).*?)  (?P<string> \x00[^\x00]+\x00 | \'.*?\' | ".*?" )  #x';
					$nohtml = preg_replace_callback($stringPatternCSS, function ($match) {
						// 剥掉{}:;,>字符前后的所有空白符。
						static $replacePattern1 = '# \s* ([\{\}:;,>=~\+\^\$\*\|]) \s* #x';
						$match['nostr'] = preg_replace($replacePattern1, '$1', $match['nostr']);
						
						// 把其余的多个空白字符变成一个空白符。
						static $replacePattern2 = "#\s{2,}+#";
						$match['nostr'] = preg_replace($replacePattern2, "\n", $match['nostr']);
						
						return $match['nostr'] . $match['string'];
					}, $nohtml . $suffix);
					$nohtml = substr($nohtml, 0, -$suffixLen);
				}
				
				return $html . $nohtml;
			};
			
			/********************************************************************************/
			// 压缩预处理工作。
			/********************************************************************************/
			// 转换PHP代码。
			$ret = preg_replace_callback('# <\?(?:php)? \s .+? \?> #sx', function ($match) {
				static $searchs = array(
					"'", 
					'"'
				);
				static $replaces = array(
					"\x1F", 
					"\x7F"
				);
				return "\x00" . str_replace($searchs, $replaces, $match[0]) . "\x00";
			}, $ret);
			
			// 转换代码中的\\，以便于书写字符串匹配正则表达式。
			$ret = str_replace('\\\\', "\x01\xC0", $ret);
			
			// 转换字符串中的script、style，以便于以后能够正确的分解出JS、CSS、HTML代码。
			// 转换字符串中的//、/*、<!--，因为字符串里面的这些符号不能当作注释起始符。
			// 转换字符串中的<textarea、<pre，以便于以后能够正确的识别出<textarea>与<pre>标签。
			// =引号(?s).*?引号规则是针对html标签属性值的，可以允许有换行符在里面，但是它里面的\线没有转义符的功能。
			$stringPattern = '# \x00[^\x00]+\x00 | \'.*?(?<!\\\\)\' | ".*?(?<!\\\\)" | =\'[^\']*\' | ="[^"]*" #x';
			$ret = preg_replace_callback($stringPattern, function ($match) {
				static $searchs = array(
					'#<(script)#i', 
					'#<(style)#i', 
					'#//#', 
					'#/\*#', 
					'#<!--#', 
					'#<(textarea)#i', 
					'#<(pre)#i'
				);
				static $replaces = array(
					"\x02\$1\xC0", 
					"\x03\$1\xC0", 
					"\x04\xC0", 
					"\x05\xC0", 
					"\x06--\xC0", 
					"\x07\$1\xC0", 
					"\x08\$1\xC0"
				);
				return preg_replace($searchs, $replaces, $match[0]);
			}, $ret);
			
			// 剥掉代码中的<!--注释-->，因为HTML文件中它的优先级是最高的，但是要除过<!--[内容]-->格式的注解及浏览器常用的预处理条件语句。
			$ret = preg_replace('# <!-- (?!\[|<!|>) .*? (?<!\]) --> #sx', '', $ret);
			
			// 分离出JS、CSS、HTML代码。
			$suffix = '<style></style><script></script>';
			$htmlNohtmlPattern = '#(?P<html>.*?) (?P<nohtml> <style.*?>.*?</style> | <script.*?>.*?</script> )#isx';
			$ret = preg_replace_callback($htmlNohtmlPattern, $compressCallback, $ret . $suffix);
			$ret = substr($ret, 0, -strlen($suffix));
			
			// 恢复先前的所有转换。
			$searchs = array(
				"#\x01\xC0#", 
				"#\x02(script)\xC0#i", 
				"#\x03(style)\xC0#i", 
				"#\x04\xC0#", 
				"#\x05\xC0#", 
				"#\x06--\xC0#", 
				"#\x07(textarea)\xC0#i", 
				"#\x08(pre)\xC0#i", 
				"#\x1F#", 
				"#\x7F#", 
				'#\x00#'
			);
			$replaces = array(
				'\\\\\\\\', 
				'<$1', 
				'<$1', 
				'//', 
				'/*', 
				'<!--', 
				'<$1', 
				'<$1', 
				"'", 
				'"', 
				''
			);
			$ret = preg_replace($searchs, $replaces, $ret);
			
			return $ret;
		}
	}
}
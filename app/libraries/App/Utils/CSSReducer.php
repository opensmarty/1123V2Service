<?php
namespace App\Utils {
	use App\System as Sys;
	use Phalcon\Kernel;

	/**
	 * CSS精简器(即删除HTML文件中未用的样式)。
	 */
	class CSSReducer {
		/**
		 * 使用的标签索引。
		 */
		private $usedMarks = array();
		
		/**
		 * 使用的属性索引。
		 */
		private $usedAttrs = array();
		
		/**
		 * HASH到结点的映射。
		 */
		private $hashNodes = array();
		
		/**
		 * 全部的HASH码列表。
		 */
		private $fullHashes = array();
		
		/**
		 * 解析出的样式列表。
		 */
		private $cssList = array();
		
		/**
		 * 解析出的动画样式。
		 */
		private $cssKeyframes = null;
		
		/**
		 * 解析中的HTML文件。
		 */
		private $nowHtmlFile = null;
		
		/**
		 * 解析中的结点编号。
		 */
		private $nowNodeNum = 1;
		
		/**
		 * HTML文件解析器。
		 * @param array $htmlFiles 需要解析的HTML文件。
		 * @return void
		 */
		private function htmlParser(array $htmlFiles) {
			foreach ($htmlFiles as $file) {
				if (!preg_match('#.+\.htm[l]?$#', $file)) {
					continue;
				}
				
				$dom = new \DOMDocument('1.0', 'UTF-8');
				if (!@$dom->loadHTMLFile($file, LIBXML_COMPACT)) {
					Sys::throwException("加载HTML文件[$file]时失败", 'reduce');
				}
				
				$validFlag = false;
				if ($dom->documentElement instanceof \DOMElement && $dom->documentElement->nodeName == 'html') {
					$childNodes = $dom->documentElement->childNodes;
					$node = $childNodes->item(1);
					if ($node instanceof \DOMElement && $node->nodeName == 'body') {
						$dom->documentElement->removeChild($childNodes->item(0));
						$validFlag = true;
					}
				}
				if (!$validFlag) {
					Sys::throwException("HTML文件[$file]的结构无效", 'reduce');
				}
				
				$htmlNode = array();
				$htmlNode['parent'] = null;
				$this->nowHtmlFile = $file;
				$this->nowNodeNum = 1;
				$this->nodeParser($dom->documentElement, $htmlNode);
				unset($htmlNode);
			}
			$this->fullHashes = array_keys($this->hashNodes);
		}
		
		/**
		 * DOM结点解析器。
		 * @param \DOMNode $domNode 需要解析的DOM结点。
		 * @param array& $mapNode DOM结点对应的数组。
		 * @return void
		 */
		private function nodeParser(\DOMNode $domNode, array &$mapNode) {
			// 处理DOM结点对象。
			if (!($domNode instanceof \DOMElement)) {
				return;
			}
			do {
				$hash = 'h' . Kernel::preComputeHashKey32($this->nowHtmlFile . $this->nowNodeNum++);
			}
			while (isset($this->hashNodes[$hash]));
			$mapNode['hash'] = $hash;
			$this->hashNodes[$hash] = &$mapNode;
			$mark = $domNode->nodeName;
			$this->usedMarks[$mark]['_hash'][] = $hash;
			
			// 处理DOM元素属性。
			$attributes = $domNode->attributes;
			for ($i = 0; $i < $attributes->length; $i++) {
				$domAttr = $attributes->item($i);
				$attrName = $domAttr->name;
				$attrData = $domAttr->value;
				
				// 记录使用的属性。
				$this->usedMarks[$mark][$attrName]['_hash'][] = $hash;
				$this->usedAttrs[$attrName]['_hash'][] = $hash;
				if ($attrName == 'class') {
					$classes = array_flip(preg_split('#\x20+#', $attrData, null, PREG_SPLIT_NO_EMPTY));
					foreach ($classes as $class => $unused) {
						$class = '.' . $class;
						$this->usedMarks[$mark][$attrName]['_data'][$class][] = $hash;
						$this->usedAttrs[$attrName]['_data'][$class][] = $hash;
					}
				}
				else {
					$this->usedMarks[$mark][$attrName]['_data'][$attrData][] = $hash;
					$this->usedAttrs[$attrName]['_data'][$attrData][] = $hash;
				}
				
				// 判断标签的重复。
				if (preg_match('#^.+-repeat$#', $attrName) && !empty($attrData)) {
					$mapNode['repeat'] = true;
				}
			}
			
			// 处理DOM元素孩子。
			$nodes = array();
			$prevNode = null;
			$childNodes = $domNode->childNodes;
			for ($i = 0; $i < $childNodes->length; $i++) {
				$node = array();
				$currNode = $childNodes->item($i);
				$this->nodeParser($currNode, $node);
				if (isset($node['hash'])) {
					$node['parent'] = &$mapNode;
					if (!empty($prevNode)) {
						$node['prev'] = &$prevNode;
						$prevNode['next'] = &$node;
					}
					$prevNode = &$node;
				}
				$nodes[] = &$node;
				unset($node);
			}
			$mapNode['nodes'] = &$nodes;
			unset($nodes);
		}
		
		/**
		 * 结点比较器。
		 * @param array& $compare1Node 比较结点一。
		 * @param array& $compare2Node 比较结点二。
		 * @param string $relationChar 关系选择符。
		 * @return boolean
		 */
		private function nodeComparer(&$compare1Node, &$compare2Node, $relationChar) {
			$ret = false;
			if ($relationChar == '+' || $relationChar == '~') {
				if ($compare1Node == $compare2Node && isset($compare1Node['repeat'])) {
					// 是可重复结点。
					$ret = true;
				}
				elseif ($relationChar == '+') {
					// 判断紧邻兄弟。
					$ret = (isset($compare1Node['prev']) && $compare1Node['prev'] == $compare2Node || isset($compare1Node['next']) && $compare1Node['next'] == $compare2Node);
				}
				else {
					// 判断同辈兄弟。
					$ret = ($compare1Node['parent'] == $compare2Node['parent']);
				}
			}
			elseif ($relationChar == '>') {
				// 判断孩子结点。
				$ret = ($compare1Node == $compare2Node['parent']);
			}
			else {
				// 判断子代结点。
				$nodeRef = &$compare2Node;
				while (is_array($nodeRef['parent']) && $compare1Node != $nodeRef['parent']) {
					$nodeRef = &$nodeRef['parent'];
				}
				$ret = ($compare1Node == $nodeRef['parent']);
			}
			return $ret;
		}
		
		/**
		 * 样式解析器。
		 * @param string $minCSS
		 * @return void
		 */
		private function cssParser($minCSS) {
			preg_match_all('#(?<styleHead>[^\{\}]+) (?<styleBody>\{ (?: (?:[^\{\}]+ \{ [^\{\}]* \})+ | [^\{\}]* ) \})#x', $minCSS, $matches);
			foreach ($matches['styleHead'] as $num => $styleHead) {
				if (preg_match('#^@(?:font-face|media).*$#', $styleHead)) {
					$this->cssList[$num] = array(
						'styleHead' => array(
							$styleHead => true
						), 
						'styleBody' => $matches['styleBody'][$num]
					);
				}
				elseif (preg_match('#^@.*keyframes\s(?<name>.+)$#', $styleHead, $match)) {
					$name = $match['name'];
					$this->cssKeyframes[$name][] = $matches[0][$num];
				}
				else {
					// 依逗号分隔样式头部。
					$styles = preg_split('#,+#', $styleHead, null, PREG_SPLIT_NO_EMPTY);
					$this->cssList[$num] = array(
						'styleHead' => array_flip($styles), 
						'styleBody' => $matches['styleBody'][$num]
					);
					foreach ($styles as $style) {
						// 按关系选择符再拆分。
						$useFlag = true;
						$styles2 = preg_split('#[\x20>\+~](?!=)#', $style, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
						foreach ($styles2 as &$style2) {
							$style2[2] = $this->style2Parser($style2[0]);
							if (empty($style2[2])) {
								$useFlag = false;
								break;
							}
						}
						unset($style2);
						if ($useFlag) {
							// 处理样式关系选择符。
							$compare1Hashes = $styles2[0][2];
							unset($styles2[0]);
							$useFlag2 = true;
							foreach ($styles2 as $style2) {
								$relationChar = substr($style, $style2[1] - 1, 1);
								$compare2Hashes = $style2[2];
								
								$comparedHashes = array();
								foreach ($compare1Hashes as $compare1Hash) {
									$compare1Node = &$this->hashNodes[$compare1Hash];
									foreach ($compare2Hashes as $compare2Hash) {
										$compare2Node = &$this->hashNodes[$compare2Hash];
										if ($this->nodeComparer($compare1Node, $compare2Node, $relationChar)) {
											$comparedHashes[$compare2Hash] = true;
										}
									}
								}
								if (empty($comparedHashes)) {
									$useFlag2 = false;
									break;
								}
								
								$compare1Hashes = array_keys($comparedHashes);
							}
							
							// 说明此样式要被使用。
							if ($useFlag2) {
								$this->cssList[$num]['styleHead'][$style] = true;
							}
						}
					}
				}
			}
		}
		
		/**
		 * 样式二解析器。
		 * @param string $style2
		 * @return array 样式二匹配的元素HASH码列表。
		 */
		private function style2Parser($style2) {
			// 对样式进行再次拆分。
			$styles3 = explode("\x20", ltrim(preg_replace('#:+[a-zA-Z-]+(?:\([^\)]+\))? | \[[^\]]+\] | \.[a-zA-Z0-9-]+#x', "\x20" . '$0', $style2)));
			$useFlag2 = true;
			$hashes = array();
			$notHashes = null;
			$styles3 = array_flip($styles3);
			foreach ($styles3 as $style3 => $unused) {
				$firstChar = substr($style3, 0, 1);
				
				// 处理类型样式(最常见故优先处理)。
				if ($firstChar == '.') {
					if (!isset($this->usedAttrs['class']['_data'][$style3])) {
						$useFlag2 = false;
						break;
					}
					$hashes[] = $this->usedAttrs['class']['_data'][$style3];
				}
				
				// 处理标识样式。
				elseif ($firstChar == '#') {
					$id = substr($style3, 1);
					if (!isset($this->usedAttrs['id']['_data'][$id])) {
						$useFlag2 = false;
						break;
					}
					$hashes[] = $this->usedAttrs['id']['_data'][$id];
				}
				
				// 处理标签样式。
				elseif (preg_match('#^[a-zA-Z0-9-]+$#', $style3)) {
					if (!isset($this->usedMarks[$style3])) {
						$useFlag2 = false;
						break;
					}
					$hashes[] = $this->usedMarks[$style3]['_hash'];
				}
				
				// 处理属性样式。
				elseif (preg_match('#^\[ (?<attrName>[a-zA-Z0-9-]+) (?: (?<operator>\|=|\*=|\$=|\^=|~=|=) ("|)(?<attrData>.*?)\g{-2} )? \]$#x', $style3, $match)) {
					$attrName = $match['attrName'];
					if (!isset($this->usedAttrs[$attrName])) {
						$useFlag2 = false;
						break;
					}
					if (!isset($match['operator'])) {
						$hashes[] = $this->usedAttrs[$attrName]['_hash'];
					}
					else {
						$operator = $match['operator'];
						$attrData = $match['attrData'];
						if (empty($attrData) && $operator != '=' && $operator != '|=' && $operator != '~=') {
							break;
						}
						$hashes2 = array();
						switch ($operator) {
							case '*=':
								foreach ($this->usedAttrs[$attrName]['_data'] as $key => $val) {
									if (strpos($key, $attrData) !== false) {
										$hashes2 = array_merge($hashes2, $val);
									}
								}
								break;
							case '^=':
								$attrDataLen = strlen($attrData);
								foreach ($this->usedAttrs[$attrName]['_data'] as $key => $val) {
									if (substr($key, 0, $attrDataLen) == $attrData) {
										$hashes2 = array_merge($hashes2, $val);
									}
								}
								break;
							case '$=':
								$attrDataLen = strlen($attrData);
								foreach ($this->usedAttrs[$attrName]['_data'] as $key => $val) {
									if (substr($key, -$attrDataLen) == $attrData) {
										$hashes2 = array_merge($hashes2, $val);
									}
								}
								break;
							case '=':
								if (isset($this->usedAttrs[$attrName]['_data'][$attrData])) {
									$hashes2 = $this->usedAttrs[$attrName]['_data'][$attrData];
								}
								break;
							default:
								if ($operator == '|=') {
									$pattern = "-$attrData|$attrData-";
								}
								else {
									$pattern = "#\x20$attrData|$attrData\x20#";
								}
								foreach ($this->usedAttrs[$attrName]['_data'] as $key => $val) {
									if ($attrData == $key || preg_match($pattern, $key)) {
										$hashes2 = array_merge($hashes2, $val);
									}
								}
						}
						if (empty($hashes2)) {
							$useFlag2 = false;
							break;
						}
						$hashes[] = $hashes2;
					}
				}
				
				// 处理伪类样式。
				elseif (substr($style3, 0, 1) == ':') {
					$style3 = preg_replace('#:+#', ':', $style3);
					
					// 处理排除伪类样式。
					if (substr($style3, 1, 4) == 'not(') {
						$notHashes = $this->style2Parser(substr($style3, 5, -1));
					}
					
					// 处理为空伪类样式。
					elseif (substr($style3, 1, 5) == 'empty') {
						$hashes2 = array();
						if (substr($style2, 0, 1) == ':') {
							$hashes3 = $this->fullHashes;
						}
						else {
							$hashes3 = count($hashes) < 2 ? current($hashes) : call_user_func_array('array_intersect', $hashes);
						}
						foreach ($hashes3 as $hash) {
							$node = &$this->hashNodes[$hash];
							if (empty($node['nodes'])) {
								$hashes2[] = $hash;
							}
						}
						if (empty($hashes2)) {
							$useFlag2 = false;
							break;
						}
						$hashes[] = $hashes2;
					}
					
					// 通过其它伪类样式。
					else {
						continue;
					}
				}
				
				// 处理特殊样式。
				elseif ($style3 == '*') {
					continue;
				}
				
				// 报告错误提示。
				else {
					Sys::throwException("无法识别的样式[$style2]中的[$style3]", 'reduce');
				}
			}
			if (!$useFlag2) {
				$ret = array();
			}
			else {
				if (empty($hashes)) {
					// 样式匹配了所有标签。
					$ret = $this->fullHashes;
				}
				else {
					$hashes = count($hashes) < 2 ? current($hashes) : call_user_func_array('array_intersect', $hashes);
					if (!empty($notHashes)) {
						// 当前样式有排除样式。
						$ret = array_diff($hashes, $notHashes);
					}
					else {
						$ret = $hashes;
					}
				}
			}
			return $ret;
		}
		
		/**
		 * CSS精简方法。
		 * @param array $htmlFiles
		 * @param string $minCSS
		 * @return string
		 */
		private function reducing(array $htmlFiles, $minCSS) {
			// 解析HTML文件内容。
			$this->htmlParser($htmlFiles);
			
			// 解析框架中的样式。
			$this->cssParser($minCSS);
			
			// 剔除掉未用的样式。
			$usedCSS = array();
			foreach ($this->cssList as $css) {
				$styleHead = array();
				foreach ($css['styleHead'] as $style => $useFlag) {
					if ($useFlag === true) {
						$styleHead[] = $style;
					}
				}
				if (!empty($styleHead)) {
					$usedCSS[] = implode(',', $styleHead) . $css['styleBody'];
					if (preg_match_all('#animation:(?<names>[a-zA-Z0-9-]+)#', $css['styleBody'], $matches)) {
						// 找出需要的动画样式。
						$names = array_unique($matches['names']);
						foreach ($names as $name) {
							if (isset($this->cssKeyframes[$name])) {
								$usedCSS[] = implode('', $this->cssKeyframes[$name]);
							}
						}
					}
				}
			}
			return implode('', $usedCSS);
		}
		
		/**
		 * CSS精简方法(注意：所有名称均区分大小写，所以请规范化您的HTML/CSS书写)。
		 * @param array $htmlFiles 需要解析的HTML文件(HTML标签中可通过*-repeat结尾的属性来说明标签的重复，以便于解析器能正确处理+~关系选择符)。
		 * @param string $minCSS 剥掉注解及无用空白符的最小化CSS内容。
		 * @return string 精简后的CSS内容。
		 */
		public static function reduce(array $htmlFiles, $minCSS) {
			$reducer = new self();
			return $reducer->reducing($htmlFiles, $minCSS);
		}
	}
}
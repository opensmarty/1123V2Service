<?php
namespace App\UI {
	use App\System as Sys;

	/**
     * 用于生成多页导航链接的类。
     */
	class PageLink {
		/**
	     * 生成标准的多页导航链接。如总页数为20, 页索引数为10, 当前页为6, 则页索引结果为(共 20 页 | [1] [2] [3] [4] [5] 6 [7] [8] [9] [10] 下一页)，
	     * 即当前页总是放在页索引的中间。如总页数为20, 页索引数为10，当前页为12, 则页索引结果为(共 20 页 | 上一页 [7] [8] [9] [10] [11] 12 [13]
	     * [14] [15] [16] 下一页)。如总页数为11, 页索引数为8，当前页为9，则页索引结果为(共 11 页 | 上一页 [4] [5] [6] [7] [8] 9 [10] [11])。
	     * @param integer $totalRows 数据总行数。
	     * @param integer $pageRows 每页行数，默认为10。
	     * @param integer $nowPage 当前页码，默认为1。
	     * @param string $baseURL 导向不同页的链接中使用的基准URL。
    	 * @param integer $countLinks 导向不同页的链接数目，默认为11。
	     * @return string 多页导航链接。
	     */
		public static function makeStandard($totalRows, $pageRows = 10, $nowPage = 1, $baseURL = null, $countLinks = 11) {
			// 校验并设置参数。
			$totalRows = intval('0' . $totalRows);
			if ($totalRows <= 0) {
				return '<a class="totalPages">共&nbsp;<span>0</span>&nbsp;页</a>';
			}
			
			$pageRows = intval('0' . $pageRows);
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
			
			if (strpos($baseURL, '?') === false) {
				$baseURL = $baseURL . '?';
			}
			else {
				$baseURL = preg_replace('#&?page=\d+#', '', $baseURL);
			}
			
			$countLinks = intval('0' . $countLinks);
			if ($countLinks < 3) {
				$countLinks = 3;
			}
			
			// 生成多页导航链接。
			$links = '<a class="totalPages">共&nbsp;<span>' . $totalPages . '</span>&nbsp;页</a>&nbsp;<span class="dividingLine">|&nbsp;</span><a href="' . $baseURL . '&page=1" class="prevNext">首页</a>&nbsp;';
			$countLinksHalf = floor($countLinks / 2);
			if ($totalPages <= $countLinks || $nowPage <= $countLinksHalf + 1) {
				$begin = 1;
				$end = $nowPage - 1;
				for ($i = $begin; $i <= $end; $i++) {
					$links .= '<a href="' . $baseURL . '&page=' . $i . '" class="gotoPage"><span>[</span>' . $i . '<span>]</span></a>&nbsp;';
				}
				$links .= '<a class="currentPage">' . $nowPage . '</a>&nbsp;';
				$begin = $nowPage + 1;
				$end = $countLinks;
				if ($end > $totalPages) {
					$end = $totalPages;
				}
				for ($i = $begin; $i <= $end; $i++) {
					$links .= '<a href="' . $baseURL . '&page=' . $i . '" class="gotoPage"><span>[</span>' . $i . '<span>]</span></a>&nbsp;';
				}
			}
			else {
				$begin = $nowPage - $countLinksHalf;
				if (($totalPages - $countLinks + 1) < $begin) {
					// 设置起始页码为起始页码的上限值。
					$begin = $totalPages - $countLinks + 1;
				}
				$end = $nowPage - 1;
				$prevPage = $nowPage - 1;
				$links .= '<a href="' . $baseURL . '&page=' . $prevPage . '" class="prevNextGtLt">&lt;&lt;</a>&nbsp;';
				for ($i = $begin; $i <= $end; $i++) {
					$links .= '<a href="' . $baseURL . '&page=' . $i . '" class="gotoPage"><span>[</span>' . $i . '<span>]</span></a>&nbsp;';
				}
				$links .= '<a class="currentPage">' . $nowPage . '</a>&nbsp;';
				$begin = $nowPage + 1;
				$end = $nowPage + $countLinksHalf;
				if ($countLinks % 2 == 0) {
					$end--;
				}
				if ($end > $totalPages) {
					$end = $totalPages;
				}
				for ($i = $begin; $i <= $end; $i++) {
					$links .= '<a href="' . $baseURL . '&page=' . $i . '" class="gotoPage"><span>[</span>' . $i . '<span>]</span></a>&nbsp;';
				}
			}
			if ($nowPage < $totalPages) {
				$nextPage = $nowPage + 1;
				$links .= '<a href="' . $baseURL . '&page=' . $nextPage . '" class="prevNextGtLt">&gt;&gt;</a>&nbsp;';
			}
			$links .= '<a href="' . $baseURL . '&page=' . $totalPages . '" class="prevNext">尾页</a>';
			return $links;
		}
	}
}

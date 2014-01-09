<?php

/**
* 输入的字符串只能用utf-8编码
*
* @param  mixed 要输出的对象
* @param  bool 是否输出后直接退出
* @return void
* @author ys
**/
function e($s, $is_exit = false)
{
	echo "<pre>";

	if(is_object($s))
	{
		print_r($s);

		echo 'Function ';
		print_r(get_class_methods($s));
	}
	else
	{
		echo htmlspecialchars(print_r($s, true));
	}

	echo "</pre>";

	if($is_exit)
		exit;
}

// 全局变量，辅助测试性能。
$GLOBALS['DEBUG_TIME'] = microtime(true);
/**
 * 辅助测试程序性能
 *
 * @return void
 * @author ys
 **/
function log_time()
{
	$current = microtime(true);
	e(  ($current - $GLOBALS['DEBUG_TIME']) * 1000  );
	$GLOBALS['DEBUG_TIME'] = $current;
}

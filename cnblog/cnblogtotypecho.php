<?php
// 引入公共文件
include_once('../common/Http_client.php');
include_once('../common/simple_html_dom.php');
include_once('../common/debug_helper.php');
include_once('../common/class.MySQL.php');

/**
 * 抓取cnblog博客信息导入typfecho
 *
 * @package typecho
 * @author solo 
 **/
class cntotypecho
{

	private $host = "www.cnblogs.com";		// 博客园站点
	private $blogname = "younglaker";		// 用户名, 如http://www.cnblogs.com/younglaker/中的"younglaker"
	private $startpage = 1;					// 起始页
	private $endpage = 17;					// 终止页

	// 此处为数据库配置,需要根据自己的修改
	private $database = "typecho";			// typecho数据库名
	private $dbUser = "root";				// 数据库用户名
	private $dbPwd = "root";				// 数据库密码

	// 请求单篇文章的所在分类时所需要的参数（ajax）
	// 如 http://www.cnblogs.com/younglaker/archive/2012/12/30/3250881.html
	// 使用工具分析http请求中ajax请求可以得到
	private $blogId = '138234';
	private $blogUserGuid = 'd05374c3-404e-e211-aa8f-842b2b196315';

	/**
	 * 构造函数
	 *
	 * @return void
	 * @author solo
	 **/
	function __construct()
	{
	}


	/**
	 * 把cnblog文章采集下来带入typecho数据库
	 *
	 * @return void
	 * @author solo
	 **/
	function blog()
	{
		echo "-"; 
		// 初始化数据库
		$oMysql = new MySQL($this->database,$this->dbUser,$this->dbPwd);

		// 得到用户ID
		$User = $oMysql->Select('typecho_users');
		$Uid = $User['uid'];


		// 得到所有分类
		$catList = $this->getCatList();
		// 将分类写入数据库分类表
		foreach ($catList as $cat) {
			$newCat = array(
				'name'          =>  $cat,
				'slug'          =>  $cat,
				'type'          =>  'category'
			);

			$oMysql->Insert($newCat, 'typecho_metas');
		}

		$catList_sql = $oMysql->Select('typecho_metas');


		// 得到所有的文章的链接集合
  		$links = $this->getLinks();

		
      foreach ($links as $link) 
       {
       		echo "-"; 
	  		// 得到单篇文章数据
			$item =  $this->page($link);
			$newPost = array(
		                    'title'         =>  $item['title'],
		                    'created'       =>  $item["pubDate"],
		                    'modified'      =>  $item["pubDate"],
		                    'text'          =>  $item["description"],
		                    'authorId'      =>  $Uid,
		                    'allowComment'  =>  1,
		                    'allowFeed'     =>  1,
		                    'allowPing'     =>  1
					);

			// 插入文章,返回文章ID
			$cid = $oMysql->Insert($newPost, 'typecho_contents');

			// 得到文章所在的分类
			$cats = $this->cat( $link );
			foreach ($cats as $cat) 
			{		
					// 将文章和分类关联起来,文章id和分类id写入关系表
					$mid = $this->getIdByValue($catList_sql, $cat);
					$newRelation = array(
						'cid'          =>  $cid,
						'mid'          =>  $mid
					);

					$oMysql->Insert($newRelation, 'typecho_relationships');

					// 更新此分类的文章总数(+1)
					$oMysql->ExecuteSQL("update typecho_metas set count = count+1 where mid = '$mid'");
			}
      }
	}
/**
 * 返回value对应的key
 *
 * @return void
 * @author 
 **/
function getIdByValue($var_arr, $value)
{
	foreach($var_arr as $k=>$v)
	{
		if($value==$v['name'])
		{
			return $v['mid'];
		}
	}
	return $var_arr[0]['mid'];
}
	/**
	 * 得到所有分类
	 *
	 * @return void
	 * @author 
	 **/
	function getCatList()
	{

		$catList = array();

		$host = $this->host;
		$client = new HttpClient($host);

			// 禁止自动跳转
			$client->setHandleRedirects(false);

			// 伪造浏览器
			$client->setUserAgent('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.3a) Gecko/20021207');
			$client->referer = "http://".$host."/".$this->blogname."/";
				// ajax请求，得到所有分类
				$client->post(
					'/'.$this->blogname.'/mvc/blog/sidecolumn.aspx',
					array(
						'blogApp'           => $this->blogname
					)
				);

				$pageContents = $client->getContent();

				$dom = new simple_html_dom();
				$dom->load($pageContents, true, true);

				// 得到分类区域
				$catListPostCategory = $dom->find(".catListPostCategory",0)->children[1]->find("li");

				foreach ( $catListPostCategory as $catListPost ) 
				{
					// 过滤掉分类中的文章数目,如(22)
					$cat = preg_replace("/\(\d{1,2}\)/", "", $catListPost->plaintext);

					$catList[] = rtrim($cat, " ");
				}
				return $catList;
	}

	/**
	 * 得到所有文章的链接
	 *
	 * @return void
	 * @author solo
	 **/
	function getLinks()
	{
		$links = array();

		$host = $this->host;
		$client = new HttpClient($host);

			// 禁止自动跳转
			$client->setHandleRedirects(false);

			// 伪造浏览器
			$client->setUserAgent('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.3a) Gecko/20021207');
			$client->referer = "http://".$host."/";

			// 逐次抓取每个分页下的所有文章链接
			for ($i=$this->startpage; $i <= $this->endpage ; $i++) 
			{                   
					// 得到网站根目录
					if (!$client->get("/".$this->blogname."/default.html?page=".$i."&OnlyTitle=1"))
					{
						die('404');             // 返回错误代码,获取失败
					}
					$pageContents = $client->getContent();

					$dom = new simple_html_dom();
					$dom->load($pageContents, true, true);

					// 获取标题区域
					$day = $dom->find(".postTitle");

					foreach ($day as $d) {
						// 得到yrl中query段,如:/younglaker/archive/2012/12/30/3250881.html
						$url = parse_url($d->children[0]->href);
						$links[] = $url['path'];
					}
			}

			return $links;
	}

	/**
	 * 抓取单篇文章信息并分析
	 *
	 * @return void
	 * @author solo
	 **/
	function page( $url = "/younglaker/archive/2012/12/30/2840100.html" )
	{
		$host = $this->host;
		$client = new HttpClient($host);

			// 禁止自动跳转
			$client->setHandleRedirects(false);

			// 伪造浏览器
			$client->setUserAgent('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.3a) Gecko/20021207');
			$client->referer = "http://".$host."/";

			// 得到网站根目录
			if (!$client->get($url))
			{
				die('404');             // 返回错误代码,获取失败
			}
			$pageContents = $client->getContent();

			$dom = new simple_html_dom();
			$dom->load($pageContents, true, true);

				// 得到文章信息
				$item["title"] = $dom->find("#cb_post_title_url",0)->plaintext;			// 标题
				$item["description"] = $dom->find("#cnblogs_post_body",0)->innertext;	// 正文
				$item["pubDate"] = strtotime($dom->find("#post-date", 0)->innertext);				// 发布时间
	//          echo $author = $dom->find("#post-date")->next_sibling()->innertext;     // 作者

				return $item;
	}

	/**
	 * 得到某篇文章的分类
	 *
	 * @return void
	 * @author solo
	 **/
	function cat( $url = "/younglaker/archive/2012/12/30/3250881.html" )
	{
		$catList = array();

		$client = new HttpClient($this->host);

		// 禁止自动跳转
		$client->setHandleRedirects(false);

		// 伪造浏览器
		$client->setUserAgent('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.3a) Gecko/20021207');
		$client->referer = "http://".$this->host.$url;

		$url_params = explode("/", $url);
		$postId_params = explode(".", $url_params[count($url_params)-1]);
		$postId = $postId_params[0];
		
		// 发起ajax请求，得到文章的分类。参数使用http工具分析ajax请求得出
		$client->post(
			'/mvc/blog/BlogPostInfo.aspx',
			array(
				'blogApp'           => $this->blogname,
				'blogId'            => $this->blogId,
				'blogUserGuid'      => $this->blogUserGuid,
				'postId'            => $postId
			)
		);

		$content = $client->getContent();

		$dom = new simple_html_dom();
		$dom->load($content, true, true);

		// 得到具体文章所在的分类
		 $BlogPostCategory = $dom->find("#BlogPostCategory",0);

		 if ($BlogPostCategory->innertext) 
		 {
			foreach ($BlogPostCategory->children as $c) {
				$cat = $c->plaintext;
				$cat = rtrim($cat, " ");
				$catList[] = preg_replace("/>/", "&gt;", $cat);
				
			}
		 }
		 else
		 {
			$catList[] = "";
		 }

		return $catList;
	}

} // END class 

$ct = new cntotypecho();
$ct->blog();
//var_dump($ct->page());

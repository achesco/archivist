<?php

require('url_to_absolute.php');

class Resource {

	public $url;
	public $gzipped = false;
	
	protected $content = '';
	protected $linksList = array(
		'scripts' => array(),
		'styles' => array(),
		'images' => array(),
		'bg-urls' => array(),
		'filter-srcs' => array(),
	);

	public function __construct($params) {
		foreach ($params as $k => $v) {
			$this->$k = $v;
		}
	}

	public function load() {
		$this->content = $this->decode(file_get_contents($this->url));
	}
	
	protected function decode($content) {
		if($this->gzipped) {
			return gzdecode($content);
		}
		return $content;
	}
	
	public function getLinksList() {
		return $this->linksList;
	}
	
	public function saw() {
		// content parsing by resource type
	}

	public function sawScripts($saw) {
		foreach($saw->get('script')->toArray() as $link) {
			if(isset($link['src'])) {
				$link = new LinkInfo($this->url, $link['src']);
				$link->encloseOriginalChunk('src="', '"');
				$link->encloseReplaceChunk('src="', '"');
				$this->linksList['scripts'][] = $link;
			}
		}
	}
	
	public function sawStyles($saw) {
		foreach($saw->get('link[rel=stylesheet]')->toArray() as $link) {
			if(isset($link['href'])) {
				$link = new LinkInfo($this->url, $link['href']);
				$link->encloseOriginalChunk('href="', '"');
				$link->encloseReplaceChunk('href="', '"');
				$this->linksList['styles'][] = $link;
			}
		}
	}
	
	public function sawImages($saw) {
		foreach($saw->get('img')->toArray() as $link) {
			if(isset($link['src'])) {
				$link = new LinkInfo($this->url, $link['src']);
				$link->encloseOriginalChunk('src="', '"');
				$link->encloseReplaceChunk('src="', '"');
				$this->linksList['images'][] = $link;
			}
		}
	}
	
	public function sawBgUrls() {
		preg_match_all('/url\(([^\)]+)\)/', $this->content, $matches);
		if(isset($matches[1])) {
			for($i=0; $i < count($matches[1]); $i++) {
				$link = trim(trim(trim($matches[1][$i], '"'), "'"));
				if(!empty($link) && strpos($link, 'data:') !==0 ) {
					$link = new LinkInfo($this->url, $link);
					$link->originalChunk = $matches[0][$i];
					$link->encloseReplaceChunk("url('", "')");
					$this->linksList['bg-urls'][] = $link;
				}
			}
		}
	}
	
	public function sawFilterSrcs() {
		preg_match_all('/src=([^,]+,)/', $this->content, $matches);
		if(isset($matches[1])) {
			for($i=0; $i < count($matches[1]); $i++) {
				$link = trim(trim(trim(rtrim($matches[1][$i],','), '"'), "'"));
				if(!empty($link)) {
					$link = new LinkInfo($this->url, $link);
					$link->originalChunk = $matches[0][$i];
					$link->encloseReplaceChunk("src='", "'");
					$this->linksList['filter-srcs'][] = $link;
				}
			}
		}
	}
	
	public function rewrite() {
		$search = array();
		$replace = array();
		foreach($this->linksList as $type=>$list) {
			for($i=0; $i < count($list); $i++) {
				$search[] = $list[$i]->originalChunk;
				$replace[] = $list[$i]->replaceChunk;
			}
		}
		$this->content = str_replace($search, $replace, $this->content);
	}
	
	public function save($link) {
		if (!is_dir($link->savePath)) {
			mkdir($link->savePath, 0777, true);
		}
		file_put_contents($link->savePath . '/' . $link->saveFile, $this->content);
	}
}

class LinkInfo {

	public $downloadUrl;
	public $external = false;
	public $rootBased = false;

	public $savePath;
	public $saveFile;

	public $replacePath;

	public $replaceChunk;
	public $originalChunk;

	public function __construct($baseUrl, $link) {
		$this->external = (strpos($link, 'http://') === 0 || strpos($link, 'https://') === 0);
		$this->rootBased = (strpos($link, '/') === 0);
		$this->downloadUrl = url_to_absolute($baseUrl, $link);

		$urlInfo = parse_url($this->downloadUrl);
		$pathInfo = pathinfo($urlInfo['path']);
		
		$baseUrlInfo = parse_url($baseUrl);
		$basePathInfo = pathinfo($baseUrlInfo['path']);
		$level = substr_count(trim($baseUrlInfo['path'], '/'), '/');
		$level = $level < 0 ? 0 : $level;
		
		if($this->external) {
			$this->savePath = '_ext_';
			$this->saveFile = md5($link) . '-' . $pathInfo['basename'];
			$this->replacePath = $this->savePath . '/' . $this->saveFile;
			
			// support for freaking url('fonts/webfont.eot?#iefix')
			if(strpos($link, '?') > 0) {
				$this->replacePath .= '?';
			}
			if(isset($urlInfo['query'])) {
				$this->replacePath .= $urlInfo['query'];
			}
			if(strpos($link, '#') > 0) {
				$this->replacePath .= '#';
			}
			if(isset($urlInfo['fragment'])) {
				$this->replacePath .= $urlInfo['fragment'];
			}
		}
		else {
			$this->savePath = ltrim($pathInfo['dirname'], '/');
			// not a double slash, it's escaping
			if($this->savePath == '\\') {
				$this->savePath = '';
			}
			$this->saveFile = $pathInfo['basename'];

			if($this->rootBased) {
				if($basePathInfo['dirname'] == $pathInfo['dirname']) {
					$this->replacePath = $this->saveFile;
				}
				else {
					$this->replacePath = str_repeat('../', $level) . ltrim($link, '/');
				}
			}
			else {
				$this->replacePath = $link;
			}
			/*
			if(!empty($this->replacePath)) {
				$this->replacePath .= '/';
			}
			$this->replacePath .= $this->saveFile;
			*/
		}
		/*
		echo "<pre>--------------\n";
		$this->_baseUrl = $baseUrl;
		$this->_link = $link;
		print_r( $this );
		*/
		$this->originalChunk = $link;
		$this->replaceChunk = $this->replacePath;
	}
	
	public function encloseOriginalChunk($before='', $after='') {
		$this->originalChunk = $before . $this->originalChunk . $after;
	}
	
	public function encloseReplaceChunk($before='', $after='') {
		$this->replaceChunk = $before . $this->replacePath . $after;
	}
}

class Page extends Resource {
	
	public function __construct($params) {
		parent::__construct($params);
	}

	public function saw() {
		$saw = new nokogiri($this->content);
		$this->sawScripts($saw);
		$this->sawStyles($saw);
		$this->sawImages($saw);
		$this->sawBgUrls();
	}
}

class Style extends Resource {

	public function __construct($params) {
		parent::__construct($params);
	}

	public function saw() {
		$this->sawBgUrls();
		$this->sawFilterSrcs();
	}
}

class BinaryResource extends Resource {

	public function __construct($params) {
		parent::__construct($params);
	}

	protected function decode($content) {
		return $content;
	}
}
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
		'icons' => array(),
		'style-imports' => array(),
	);

	public function __construct($params) {
		foreach ($params as $k => $v) {
			$this->$k = $v;
		}
	}

	public function load() {
		echo "Loading: " . $this->url . "\n";
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

	public function sawScripts() {
		preg_match_all('/<script[^>]+src="([^"]+)"/', $this->content, $matches);
		if(isset($matches[1])) {
			for($i=0; $i < count($matches[1]); $i++) {
				$link = $matches[1][$i];
				if(!empty($link)) {
					$link = new LinkInfo($this->url, $link);
					$link->encloseOriginalChunk('src="', '"');
					$link->encloseReplaceChunk('src="', '"');
					$this->linksList['scripts'][] = $link;
				}
			}
		}
	}
	
	public function sawStyles() {
		preg_match_all('/<link[^>]+href="([^"]+)"[^>]+/', $this->content, $matches);
		if(isset($matches[1])) {
			for($i=0; $i < count($matches[1]); $i++) {
				$link = $matches[1][$i];
				if(!empty($link) && strpos($matches[0][$i], 'rel="stylesheet"') > 0) {
					$link = new LinkInfo($this->url, $link);
					$link->encloseOriginalChunk('href="', '"');
					$link->encloseReplaceChunk('href="', '"');
					$this->linksList['styles'][] = $link;
				}
			}
		}
	}
	
	public function sawImages() {
		preg_match_all('/<img[^>]+src="([^"]+)"/', $this->content, $matches);
		if(isset($matches[1])) {
			for($i=0; $i < count($matches[1]); $i++) {
				$link = $matches[1][$i];
				if(!empty($link)) {
					$link = new LinkInfo($this->url, $link);
					$link->encloseOriginalChunk('src="', '"');
					$link->encloseReplaceChunk('src="', '"');
					$this->linksList['images'][] = $link;
				}
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

	public function sawIcons() {
		preg_match_all('/<link[^>]+href="([^"]+)"[^>]+/', $this->content, $matches);
		if(isset($matches[1])) {
			for($i=0; $i < count($matches[1]); $i++) {
				$link = $matches[1][$i];
				if(!empty($link) && (
					strpos($matches[0][$i], 'rel="shortcut icon"') > 0 ||
					strpos($matches[0][$i], 'rel="icon"') > 0 ||
					strpos($matches[0][$i], 'rel="apple-touch-icon"') > 0 ||
					strpos($matches[0][$i], 'rel="apple-touch-icon-precomposed"') > 0
				)){
					$link = new LinkInfo($this->url, $link);
					$link->encloseOriginalChunk('href="', '"');
					$link->encloseReplaceChunk('href="', '"');
					$this->linksList['icons'][] = $link;
				}
			}
		}
	}

	public function sawStyleImports() {
		preg_match_all('/@import\s+(url\()?([^\)\s;]+)/', $this->content, $matches);
		if(isset($matches[2])) {
			for($i=0; $i < count($matches[2]); $i++) {
				$link = trim(trim(trim($matches[2][$i], '"'), "'"));
				if(!empty($link)) {
					$link = new LinkInfo($this->url, $link);
					$link->originalChunk = $matches[0][$i];
					$link->encloseReplaceChunk("@import url('", "')");
					$this->linksList['style-imports'][] = $link;
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
			$this->savePath =  '_ext_';
			$this->saveFile = md5($link) . '-' . $pathInfo['basename'];
			$this->replacePath = str_repeat('../', $level) . $this->savePath . '/' . $this->saveFile;
			
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

			if(array_key_exists('query', $urlInfo)) {
				if(array_key_exists('extension', $pathInfo)) {
						$this->saveFile = $pathInfo['filename'] . '__' . hash("crc32", $urlInfo['query']) . '.' . $pathInfo['extension'];
				}
				else {
					$this->saveFile = $pathInfo['basename'] . '__' . hash("crc32", $urlInfo['query']);
				}
			}

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
		}
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

	public $extension = null;
	
	public function __construct($params) {
		parent::__construct($params);
	}

	public function saw() {
		$this->sawScripts();
		$this->sawStyles();
		$this->sawImages();
		$this->sawBgUrls();
		$this->sawIcons();
	}
}

class Style extends Resource {

	public function __construct($params) {
		parent::__construct($params);
	}

	public function saw() {
		$this->sawBgUrls();
		$this->sawFilterSrcs();
		$this->sawStyleImports();
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

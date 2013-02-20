<?php
/**
 * NOTES:
 * CSS @import not supported.
 * 
 * @todo external resources from css - relative path failed
 * @todo script / style form root www folder?
 * @todo 404 handle
 * @todo logging
 * @todo archive, actually
 */

require('Resource.php');

class Archivist {
	
	public $rootUrl;
	public $pagesList = array();
	public $gzipped = false;

	protected $extensions = array('html', 'html', 'php', 'asp');
	
	protected $pages = array();
	protected $resources = array();

	public function __construct($params) {
		foreach ($params as $k => $v) {
			$this->$k = $v;
		}

		$this->rootUrl = rtrim($this->rootUrl, '/') . '/';

		print "Root url is: " . $this->rootUrl . "\n";

		foreach ($this->pagesList as $pageUri) {

			$extension = pathinfo($pageUri, PATHINFO_EXTENSION);
			if($pos = strpos($extension, '?')){
				$extension = substr($extension, 0, $pos);
			}
			$pageUri = ($pageUri == '/' ? '/' : '/' . trim($pageUri, '/') . '/');
			if(in_array($extension, $this->extensions)){
				$pageUri = rtrim($pageUri, '/');
			}
			$pageUrl = rtrim($this->rootUrl, '/') . $pageUri;
			print "Adding to process queue: " . $pageUrl . "\n";

			$this->_pages[$pageUri] = new Page(array(
				'url' => $pageUrl,
				'gzipped' => $this->gzipped,
				'extension' => $extension,
			));
		}
	}
	
	public function run() {
		foreach($this->_pages as $uri => $page) {
			$page->load();
			$page->saw();
			$page->rewrite();

			$link = new LinkInfo($this->rootUrl, $uri);

			if(empty($link->saveFile)) {
				$link->saveFile = 'index.html';
			}
			else {
				if(empty($page->extension)) {
					$link->saveFile .=  '.html';
				}
			}
			self::prefixSavePath($link);
			$page->save($link);
			
			$linksList = $page->getLinksList();
			$this->loadBinaries(array_merge($linksList['scripts'], $linksList['images'], $linksList['bg-urls']));

			$list = $linksList['styles'];
			for($i=0; $i < count($list); $i++) {
				$link = $list[$i];
				self::prefixSavePath($link);
				if(!file_exists($link->savePath . '/' . $link->saveFile)) {
					$page = new Style(array(
						'url' => $link->downloadUrl,
						'gzipped' => $this->gzipped,
					));
					$page->load();
					$page->saw();
					$page->rewrite();
					$page->save($link);
					$innerLinksList = $page->getLinksList();
					$this->loadBinaries(array_merge($innerLinksList['bg-urls'], $innerLinksList['filter-srcs']));
				}
			}

		}
	}
	
	public function loadBinaries($list) {
		for($i=0; $i < count($list); $i++) {
			$link = $list[$i];
			self::prefixSavePath($link);
			if(!file_exists($link->savePath . '/' . $link->saveFile)) {
				$page = new BinaryResource(array(
					'url' => $link->downloadUrl,
					'gzipped' => false,
				));
				$page->load();
				$page->save($link);
			}
		}
	}
	
	public function prefixSavePath($link) {
		if(empty($link->savePath)) {
			$link->savePath = 'out';
		}
		else {
			$link->savePath = 'out/' . $link->savePath;
		}
	}
}

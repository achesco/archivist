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

require('nokogiri.php');
require('Resource.php');

class Archivist {
	
	public $rootUrl;
	public $pagesList = array();
	public $gzipped = false;
	
	protected $pages = array();
	protected $resources = array();

	public function __construct($params) {
		foreach ($params as $k => $v) {
			$this->$k = $v;
		}

		$this->rootUrl = rtrim($this->rootUrl, '/') . '/';

		foreach ($this->pagesList as $pageUrl) {
			$pageUrl = rtrim($this->rootUrl, '/') . ($pageUrl == '/' ? '/' : '/' . trim($pageUrl, '/') . '/');
			$this->_pages[$pageUrl] = new Page(array(
				'url' => $pageUrl,
				'gzipped' => $this->gzipped,
			));
		}
	}
	
	public function run() {
		foreach($this->_pages as $url => $page) {
			$page->load();
			$page->saw();
			$page->rewrite();
			$link = new LinkInfo($url, '.');

			if(empty($link->saveFile)) {
				$link->saveFile = 'index.html';
			}
			else {
				$link->saveFile .=  '.html';
			}
			self::prefixSavePath($link);
			$page->save($link);
			
			$linksList = $page->getLinksList();
			$this->loadBinaries(array_merge($linksList['scripts'], $linksList['images'], $linksList['bg-urls']));
			/*
			echo "<pre>\n";
			print_r($link);
			*/

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

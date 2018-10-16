<?php


use SilverStripe\ORM;
use SilverStripe\Forms;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Control\Director;
use SilverStripe\ORM\Connect\MySQLSchemaManager;

class SiteTreeExtension extends ORM\DataExtension
{				
	private static $create_table_options = [
		MySQLSchemaManager::ID => 'ENGINE=MyISAM'
	];
	
	private static $indexes = [
		'SearchFields' => [
			'type' => 'fulltext',
			'columns' => [
				'Title',
				'Content'
			]
		]
	];
}
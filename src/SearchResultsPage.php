<?php

namespace IQnection\SearchResultsPage;


class SearchResultsPage extends \Page
{
	private static $table_name = 'SearchResultsPage';
	
	private static $db = [];	
	
	private static $defaults = [
		'ShowInMenus' => false,
		'ShowInSearch' => false
	];
	
}



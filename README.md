

Searches all pages and data objects (that have indexes)

for DataObjects, you must specify the indexs by using the following:

private static $indexes = [
	'SearchFields' => [
		'type' => 'fulltext',
		'columns' => ['Title', 'Content'],
	]
];

private static $create_table_options = [
	MySQLSchemaManager::ID => 'ENGINE=MyISAM'
];


see: https://docs.silverstripe.org/en/4/developer_guides/search/fulltextsearch/ for more information on full text search
# SilverStripe Search Page with Fulltext

Searches all pages and data objects (that have a proper index)

All DataObject subclasses that include a fulltext index named "SearchFields" will be searched
Specify the database columns you want to be searched as follows:
```
private static $indexes = [
	'SearchFields' => [
		'type' => 'fulltext',
		'columns' => ['MyField1', 'MyField2'],
	]
];
```

You'll also need to set the tabel engine as follows
```
private static $create_table_options = [
	MySQLSchemaManager::ID => 'ENGINE=MyISAM'
];
```

Build your search form setting the search input with the name "s", and post to the search page action "results"
```
<form method="get" action="{$SearchPage.Link(results)}">
    <input type="search" name="s" value="" />
    <input type="submit" value="Search" />
</form>
```

see: https://docs.silverstripe.org/en/4/developer_guides/search/fulltextsearch/ for more information on full text search
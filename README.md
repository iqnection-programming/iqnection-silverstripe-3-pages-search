

Searches all pages and data objects (that have indexes)

for DataObjects, you must specify the indexs by using the following:

private static $indexes = array(
	'SearchFields' => array(
		'type' => 'fulltext', 
		'value' => '"FullTitle","Description"'
	)
);


see: https://docs.silverstripe.org/en/3.3/developer_guides/model/indexes/ for more information on indexes
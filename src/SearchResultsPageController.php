<?php

namespace IQnection\SearchResultsPage;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\Search\FulltextSearchable;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\FieldType;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\PaginatedList;

class SearchResultsPage_Controller extends \PageController
{	
	private static $allowed_actions = array(
		"results"
	);
	
	private static function sortByScore(&$a, &$b)
	{
		return $a['score'] < $b['score'];
	}
	
	public function results()
	{
		// Page Classes
		$page_classes = array();
		
		$results = array(
			"pages" => array(),
			"objects" => array()
		);
		
		$search_query = trim($this->getRequest()->getVar("s"));
		
		// search pages
		$schema = \singleton(DataObjectSchema::class);
		$siteTree = \SilverStripe\CMS\Model\SiteTree::singleton();
		foreach ($siteTree->obj('ClassName')->enumValues() as $page_class_name)
		{
//			if ($page_class_name == SiteTree::class) { continue; }
			$select = "SiteTree_Live.ID, SiteTree_Live.ClassName, SiteTree_Live.Title, SiteTree_Live.Content, SiteTree_Live.ParentID";
			$match = "SiteTree_Live.Title, SiteTree_Live.MenuTitle, SiteTree_Live.Content, SiteTree_Live.ExtraMeta, SiteTree_Live.MetaDescription";
			$match_join = false;
			$from = "SiteTree_Live";
			$join = false;
			$where = "SiteTree_Live.ClassName = '".$page_class_name."' AND SiteTree_Live.ShowInSearch = 1";
			
			$instance = \singleton($page_class_name);
			$indexes = $schema->databaseIndexes($page_class_name,false);
			$tableName = $schema->tableName($page_class_name);
			if ( (isset($indexes['SearchFields']['type'])) && ($indexes['SearchFields']['type'] == "fulltext") )
			{
				$field_list = $tableName."_Live.".implode(", ".$tableName."_Live.", $indexes['SearchFields']['columns']);
				if ($page_class_name != SiteTree::class) 
				{
					$join = "INNER JOIN ".$tableName."_Live ON ".$tableName."_Live.ID = SiteTree_Live.ID";
				}
				$match_join = $field_list;
			}
			
			$query = "SELECT ".$select.", 
				(MATCH (".$match.") AGAINST ('".Convert::raw2sql($search_query)."' IN BOOLEAN MODE)".
					($match_join ? " + MATCH (".$match_join.") AGAINST ('".Convert::raw2sql($search_query)."') " : "").
				") AS 'score' FROM ".$from." ".($join ? " ".$join." " : "")."
				WHERE ".$where." AND (MATCH (".$match.") AGAINST ('".Convert::raw2sql($search_query)."' IN BOOLEAN MODE)".
					($match_join ? " OR MATCH (".$match_join.") AGAINST ('".Convert::raw2sql($search_query)."') " : "").
				") ORDER BY score DESC";
				
			foreach (DB::query($query) as $found_page)
			{
				$object = SiteTree::get()->byID($found_page['ID']);
				$found_page['Page'] = $object;
				$results['pages'][$found_page['ID']] = $found_page;
			}
			
			// Search for dataobjects that are part of the class:
			
			if ( ($instance->hasMethod('has_many')) && (count($instance->has_many())) )
			{
				foreach ($instance->has_many() as $field => $object_class)
				{
					$object_instance = \singleton($object_class);
					
					if ($object_instance->has_one($page_class_name))
					{
						$object_indexes = $object_instance->databaseIndexes();
						
						//if ($_SERVER['REMOTE_ADDR'] == "173.161.227.54") { print "<pre>$object_class: ".print_r($object_indexes, true)."</pre>\n"; }
						if ( (isset($object_indexes['SearchFields']['type'])) && ($object_indexes['SearchFields']['type'] == "fulltext") )
						{

							$objectTableName = $schema->tableName($page_class_name);
							$object_field_list = $objectTableName.".".implode(", ".$objectTableName.".", $object_indexes['SearchFields']['value']);
							$object_query = "
								SELECT ".$objectTableName.".ID, ".$object_field_list.", ".$objectTableName."ID,
									MATCH (".$object_field_list.") AGAINST ('".Convert::raw2sql($search_query)."') AS 'score' 
								FROM ".$object_class."
								WHERE MATCH (".$object_field_list.") AGAINST ('".Convert::raw2sql($search_query)."' IN BOOLEAN MODE)
								ORDER BY score DESC
							";								
							foreach (DB::query($object_query) as $found_object)
							{
								$parent_page = false;
								$query = "SELECT ID, ClassName, Title, Content, ParentID, ShowInSearch, 0 AS 'score'
									FROM SiteTree_Live
									WHERE ID = ".$found_object[$page_class_name.'ID']."
									AND ClassName = '".$page_class_name."'
								";
								foreach (DB::query($query) as $pp) $parent_page = $pp;
									
								if ($parent_page && $parent_page['ShowInSearch'] > 0)
								{
									if (method_exists($object_class, "Link"))
									{
										$object = $object_class::get()->byID($found_object['ID']);

										$found_object['Page'] = $object;
										$found_object['Title'] = $object->hasMethod("getTitle") ? $object->getTitle() : ($object->Title ? $object->Title : $object_class);
										$found_object['Content'] = $object->hasMethod("Content") ? $object->Content() : ($object->Content ? $object->Content : false);
	
										$results['objects'][$object_class."_".$found_object['ID']] = $found_object;
									}
									else
									{
										if (!$results['pages'][$found_object[$page_class_name.'ID']])
										{
											$parent_obj = SiteTree::get()->byID($parent_page['ID']);
											$parent_page['Page'] = $parent_obj;
											$results['pages'][$parent_page['ID']] = $parent_page;
										}

										$results['pages'][$found_object[$page_class_name.'ID']]['score'] += $found_object['score'];
									}
								}
							}
						}
					}
				}
			}
			
		}
		
		$all_results = array_merge($results['pages'], $results['objects']);
		uasort($all_results, array('self', 'sortByScore'));
			
		$resultSet = ArrayList::create();
		
		// strip all html tags from the result set			
		if( count($all_results) > 0 )
		{
			foreach( $all_results as $result )
			{
				// strip out images
				$content = preg_replace('/\<img[^\>]*>/','',$result['Content']);
				$post = ArrayData::create([
					'ResultTitle' => FieldType\DBField::create_field(FieldType\DBVarchar::class,$result['Title']),
					'Content' => FieldType\DBField::create_field(FieldType\DBHTMLText::class,$content)->setProcessShortCodes(true),
					'Page' => $result['Page']
				]);
				$resultSet->push($post);
			}
		}
		
		// finito
		$data['PaginatedResults'] = PaginatedList::create($resultSet,$this->getRequest());	
		$data['Query'] = $search_query;
	 
		return $this->customise($data)->renderWith(array("IQnection/SearchResultsPage/SearchResultsPage_results", "Page"));
	}
	
}



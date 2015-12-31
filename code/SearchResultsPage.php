<?php

	class SearchResultsPage extends Page
	{
	    private static $db = array(
		);	
		
	    private static $defaults = array(
			'ShowInMenus' => false,
			'ShowInSearch' => false
		);
				
	    public function getCMSFields()
	    {
	        $fields = parent::getCMSFields();

	        return $fields;
	    }
	}
	
	class SearchResultsPage_Controller extends Page_Controller
	{
	    private static $allowed_actions = array(
			"results"
		);
		
	    public function init()
	    {
	        parent::init();
	    }
		
	    public function PageCSS()
	    {
	        $files = array_merge(
				parent::PageCSS(),
				array(
					ViewableData::ThemeDir().'/css/forms.css'
				)
			);
	        return $files;
	    }
		
	    private static function sortByScore(&$a, &$b)
	    {
	        return $a['score'] < $b['score'];
	    }
		
	    public function results(&$request)
	    {
	        // Page Classes
			$page_classes = array();
			
	        foreach (DB::query("SELECT DISTINCT ClassName FROM SiteTree_Live WHERE ShowInSearch = 1") as $row) {
	            if (class_exists($row['ClassName'])) {
	                $vars = get_class_vars($row['ClassName']);
	                $class_config = $temp_instance->$search_config ? $temp_instance->$search_config : array();
				
	                if (!$class_config['ignore_in_search']) {
	                    $page_classes[$row['ClassName']] = $class_config;
	                }
	            }
	        }
			
	        $results = array(
				"pages" => array(),
				"objects" => array()
			);
			
	        $search_query = trim($request->getVar("Search"));
			
	        foreach ($page_classes as $class_name => $class_config) {
	            $vars = get_class_vars($class_name);
	            $temp_instance = new $class_name;
				//if ($_SERVER['REMOTE_ADDR'] == "173.161.227.54") { print "<pre>$class_name: ".print_r($vars, true)."</pre>\n"; }
				
				$select = "SiteTree_Live.ID, SiteTree_Live.ClassName, SiteTree_Live.Title, SiteTree_Live.Content, SiteTree_Live.ParentID";
	            $match = "SiteTree_Live.Title, SiteTree_Live.MenuTitle, SiteTree_Live.Content, SiteTree_Live.ExtraMeta, SiteTree_Live.MetaDescription";
	            $match_join = false;
	            $from = "SiteTree_Live";
	            $join = false;
	            $where = "SiteTree_Live.ClassName = '".$class_name."' AND SiteTree_Live.ShowInSearch = 1";
				
	            $instance = new $class_name;
	            $indexes = $instance->databaseIndexes();
				
	            if ($indexes['SearchFields'] && $indexes['SearchFields']['type'] == "fulltext") {
	                $index_fields = explode(",", preg_replace("/[\(\"']*/", "", $indexes['SearchFields']['value']));
	                $field_list = $class_name."_Live.".implode(", ".$class_name."_Live.", $index_fields);
	                $join = "INNER JOIN ".$class_name."_Live ON ".$class_name."_Live.ID = SiteTree_Live.ID";
	                $match_join = $field_list;
	            }
				
	            $query = "SELECT ".$select.", 
					(MATCH (".$match.") AGAINST ('".DB::getConn()->addslashes($search_query)."' IN BOOLEAN MODE)".
						($match_join ? " + MATCH (".$match_join.") AGAINST ('".DB::getConn()->addslashes($search_query)."') " : "").
					") AS 'score' FROM ".$from." ".($join ? " ".$join." " : "")."
					WHERE ".$where." AND (MATCH (".$match.") AGAINST ('".DB::getConn()->addslashes($search_query)."' IN BOOLEAN MODE)".
						($match_join ? " OR MATCH (".$match_join.") AGAINST ('".DB::getConn()->addslashes($search_query)."') " : "").
					") ORDER BY score DESC";
					
	            foreach (DB::query($query) as $found_page) {
	                $object = DataObject::get_by_id($class_name, $found_page['ID']);
					//$found_page['Object'] = $object;
					$found_page['Link'] = $object->Link();
	                $results['pages'][$found_page['ID']] = $found_page;
	            }
				
				// Search for dataobjects that are part of the class:
				
				if (count($temp_instance->has_many())) {
				    foreach ($temp_instance->has_many() as $field => $object_class) {
				        $object_instance = new $object_class;
						
				        if ($object_instance->has_one($class_name)) {
				            $object_indexes = $object_instance->databaseIndexes();
							
							//if ($_SERVER['REMOTE_ADDR'] == "173.161.227.54") { print "<pre>$object_class: ".print_r($object_indexes, true)."</pre>\n"; }
													
							if ($object_indexes['SearchFields'] && $object_indexes['SearchFields']['type'] == "fulltext") {
							    $object_index_fields = explode(",", preg_replace("/[\(\"']*/", "", $object_indexes['SearchFields']['value']));
							    $object_field_list = $object_class.".".implode(", ".$object_class.".", $object_index_fields);
								
							    $object_query = "
									SELECT ".$object_class.".ID, ".$object_field_list.", ".$class_name."ID,
										MATCH (".$object_field_list.") AGAINST ('".DB::getConn()->addslashes($search_query)."') AS 'score' 
									FROM ".$object_class."
									WHERE MATCH (".$object_field_list.") AGAINST ('".DB::getConn()->addslashes($search_query)."' IN BOOLEAN MODE)
									ORDER BY score DESC
								";
								
							    foreach (DB::query($object_query) as $found_object) {
							        $parent_page = false;
							        $query = "SELECT ID, ClassName, Title, Content, ParentID, ShowInSearch, 0 AS 'score'
										FROM SiteTree_Live
										WHERE ID = ".$found_object[$class_name.'ID']."
										AND ClassName = '".$class_name."'
									";
							        foreach (DB::query($query) as $pp) {
							            $parent_page = $pp;
							        }
										
							        if ($parent_page && $parent_page['ShowInSearch'] > 0) {
							            if (method_exists($object_class, "Link")) {
							                $object = DataObject::get_by_id($object_class, $found_object['ID']);
							                $found_object['Link'] = $object->Link();
							                $found_object['Title'] = method_exists($object_class, "Title") ? $object->Title() : ($object->Title ? $object->Title : $object_class);
							                $found_object['Content'] = method_exists($object_class, "Content") ? $object->Content() : ($object->Content ? $object->Content : false);
		
							                $results['objects'][$object_class."_".$found_object['ID']] = $found_object;
							            } else {
							                if (!$results['pages'][$found_object[$class_name.'ID']]) {
							                    $parent_obj = DataObject::get_by_id($class_name, $parent_page['ID']);
							                    $parent_page['Link'] = $parent_obj->Link();
							                    $results['pages'][$parent_page['ID']] = $parent_page;
							                }

							                $results['pages'][$found_object[$class_name.'ID']]['score'] += $found_object['score'];
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
				
	        $resultSet = new ArrayList();
			
			// strip all html tags from the result set			
			if (count($all_results) > 0) {
			    foreach ($all_results as $result) {
			        $post = new DataObject();
			        $post->ResultTitle = $result['Title'];
			        $post->Content = strip_tags($result['Content']);
			        $post->Link = $result['Link'];

			        $words = preg_split("/\s+/", $post->Content, 100);
			        array_pop($words);
			        $post->Content = implode(" ", $words);

			        $resultSet->push($post);
			    }
			}
			
			// finito
			$data['Results'] = $resultSet; 		
	        $data['Query'] = $search_query;
		 
	        return $this->customise($data)->renderWith(array("SearchResultsPage_results", "Page"));
	    }
	}
	
	
	class DataObjectSetExtension extends Extension
	{
	    public function Pagination()
	    {
	        $pageLimits = $this->owner->getPageLimits();
	        $items = $this->owner->toArray();
	        $items = array_slice($items, $pageLimits["pageStart"], $pageLimits["pageLength"]);
	        return new ArrayList($items);
	    }
	}

<?php

namespace IQnection\SearchResultsPage;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\FieldType;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\View\Requirements;

class SearchResultsPageController extends \PageController
{
	private static $allowed_actions = array(
		"results"
	);

    protected function init()
    {
        parent::init();
        Requirements::css('iqnection-pages/searchresultspage:client/css/SearchResultsPage.css');
    }

	public function results()
	{
        $db = new MySQLDatabase();
        $db->setConnector(DB::get_connector());
        $s1 = trim($this->getRequest()->requestVar("s"));
        $s2 = str_replace(' ','+',$s1);
        $search_query = $db->quoteString($s2);

        // collect all indexes named SearchFields
        $schema = DataObject::getSchema();


        $indexedTables = [];
        foreach($schema->getTableNames() as $tableName)
        {
            $class = $schema->tableClass($tableName);
            $tableIndexes = $schema->databaseIndexes($class, false);
            $singleton = singleton($class);
            if ( (array_key_exists('SearchFields', $tableIndexes)) && (method_exists($singleton,'getPage')) )
            {
                $indexedTables[] = [
                    'tableName' => $tableName,
                    'class' => $schema->tableClass($tableName),
                    'indexes' => $tableIndexes['SearchFields'],
                    'columns' => $schema->databaseFields($class),
                    'singleton' => $singleton
                ];
            }
        }

        $arrayList = ArrayList::create();
        // search each table on the index SearchFields
        foreach($indexedTables as $indexedTable)
        {
            $sql = new SQLSelect();
            $singleton = $indexedTable['singleton'];
            $tableName = $indexedTable['tableName'];
            $columns = $indexedTable['columns'];
            $class = $indexedTable['class'];

            // if the DataObject is versioned, search the _Live table instead
            if ($singleton->hasExtension(Versioned::class))
            {
                $tableName .= '_Live';
            }
            $sql->setFrom($tableName);
            $sql->selectField('(
                MATCH ("'.implode('","', $indexedTable['indexes']['columns']).'")
                AGAINST ('.$search_query.')
            )', 'score');
            if (array_key_exists('ShowInSearch', $columns))
            {
                $sql->addWhere('"ShowInSearch" = 1');
            }
            $sql->addHaving('score > 0');

            $sqlStatement = $sql->sql();

            $queryResults = $db->query($sqlStatement);
            foreach($queryResults as $queryResult)
            {
                $dbObject = $class::get_by_id($queryResult['ID']);
                $page = $dbObject;
                if (!($page instanceof SiteTree))
                {
                    $page = $dbObject->getPage();
                }
                if ($page->Content)
                {
                    $content = $page->dbObject('Content');
                }
                elseif ( (class_exists('\\DNADesign\\Elemental\\Extensions\\ElementalPageExtension')) && ($page->hasExtension(\DNADesign\Elemental\Extensions\ElementalPageExtension::class)) )
                {
                    $content = $page->ElementalArea()->forTemplate();
                }
                $plainContent = FieldType\DBField::create_field(FieldType\DBHTMLText::class, strip_tags($content->forTemplate()));
                $searchSummary = $plainContent->ContextSummary(300, $s1, true);
                $page->extend('updateSearchSummary', $content, $s1);
                $arrayList->push(ArrayData::create([
                    'Page' => $page,
                    'Summary' => $searchSummary,
                    'Score' => $queryResult['score'],
                ]));
            }
        }

        $arrayList = $arrayList->Sort('score', 'DESC');

        $data = [];
        $data['PaginatedResults'] = PaginatedList::create($arrayList,$this->getRequest());
        $data['Query'] = $s1;

        return $this->Customise($data);
    }
}



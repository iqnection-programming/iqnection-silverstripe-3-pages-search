<?php

	class Page_Search_Extension extends Extension
	{
		
	}
	
	class Page_Controller_Search_Extension extends Extension
	{
		function SiteSearchPage()
		{
			return DataObject::get_one('SearchResultsPage');
		}		
	}
	
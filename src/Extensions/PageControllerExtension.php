<?php

namespace IQnection\SearchResultsPage\Extensions;

use SilverStripe\Core\Extension;

class PageControllerExtension extends Extension
{
	public function SiteSearchPage()
	{
		return \IQnection\SearchResultsPage\SearchResultsPage::get()->First();
	}		
}

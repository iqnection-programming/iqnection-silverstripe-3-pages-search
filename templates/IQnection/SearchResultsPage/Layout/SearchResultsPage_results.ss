<!-- template: IQnection/SearchResultsPage/Layout/SearchResultsPage_results.ss -->

<h1>$Title</h1>
$Content
<% include IQnection/SearchResultsPage/Includes/SiteSearchForm %>

<% if $Query %>
	<p class="searchQuery"><strong>You searched for &quot;{$Query}&quot;</strong></p>
<% end_if %>

<% if $PaginatedResults.Count %>
	<p>$PaginatedResults.getTotalItems Results Found</p>
	<ul id="SearchResults">
		<% loop $PaginatedResults %>
			<li>
				<a class="searchResultHeader" href="$Link">
					$ResultTitle
				</a>
				<p>$Content.LimitWordCountXML</p>
				<a class="readMoreLink" href="$Link" 
					title="Read more about &quot;{$ResultTitle}&quot;"
					>Read more about &quot;{$ResultTitle}&quot;...</a>
			</li>
		<% end_loop %>
	</ul>
		
	<% if $PaginatedResults.MoreThanOnePage %>
		<p class="pagination">
			<% if $PaginatedResults.NotFirstPage %>
				<a class="prev" href="$PaginatedResults.PrevLink">&laquo; Prev</a>
			<% end_if %>
			<% loop $PaginatedResults.Pages %>
				<% if $CurrentBool %>
					$PageNum
				<% else %>
					<% if $Link %>
						<a href="$Link">$PageNum</a>
					<% else %>
						...
					<% end_if %>
				<% end_if %>
				<% end_loop %>
			<% if $PaginatedResults.NotLastPage %>
				<a class="next" href="$PaginatedResults.NextLink">Next &raquo;</a>
			<% end_if %>
		</p>
	<% end_if %>

<% else %>
	<p>Sorry, your search query did not return any results.</p>
<% end_if %>
	
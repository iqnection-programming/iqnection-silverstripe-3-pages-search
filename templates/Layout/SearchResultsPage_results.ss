<h1>$MenuTitle</h1>
$Content
<% include SiteSearchForm %>
<% if $Query %>
        <p class="searchQuery"><strong>You searched for &quot;{$Query}&quot;</strong></p>
    <% end_if %>

    <% if $Results %>
    <ul id="SearchResults">
        <% control $Results %>
        <li>
            <a class="searchResultHeader" href="$Link">
                $ResultTitle
            </a>
            <p>$Content.LimitWordCountXML</p>
            <a class="readMoreLink" href="$Link" 
                title="Read more about &quot;{$ResultTitle}&quot;"
                >Read more about &quot;{$ResultTitle}&quot;...</a>
        </li>
        <% end_control %>
    </ul>
    <% else %>
    <p>Sorry, your search query did not return any results.</p>
<% end_if %>
	
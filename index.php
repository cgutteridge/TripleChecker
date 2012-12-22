<?php

require_once( "arc/ARC2.php" );
require_once( "Graphite/Graphite.php" );
require_once( "template.php" );

if( !isset( $_GET['uri'] ) )
{
	render_header("front","RDF Triple-Checker");
	print "<h1>RDF Triple-Checker</h1>";
?>
<p>This tool helps find typos and common errors in RDF data.</p>

<p>Enter a URI or URL which will resolve to some RDF Triples.</p>
<form>
<table width='80%' style='margin:auto'>
<tr>
<td align='right'>URI/URL:</td><td width='100%'><input id='uri' name='uri' value='' style='width:100%' /></td></tr>
</table>
<div><input style='margin-top:0.5em' value='Check' type='submit' /></div>
</form>

<p>Drag this <a class="bookmarklet" href="javascript:window.location = &quot;http://graphite.ecs.soton.ac.uk/checker/?uri=&quot;+encodeURIComponent(window.location.href);">3-Check</a> bookmarklet to your bookmarks to create a quick button for sending your current URL to triple-checker.</p>


<?php
	render_footer();
	print "<script type='text/javascript'>document.getElementById('uri').focus()</script>";
	exit;
}
$check_uri = $_GET["uri"];

######################################################
# Load data
######################################################

$opts = array();
$opts['http_accept_header']= 'Accept: application/rdf+xml; q=0.9, text/turtle; q=0.8, */*; q=0.1';

$parser = ARC2::getRDFParser($opts);
# Don't try to load the same URI twice!

$parser->parse( $check_uri );

$errors = $parser->getErrors();
$parser->resetErrors();
render_header( "results", htmlspecialchars( $check_uri )." - RDF Triple-Checker");
print "<h1>RDF Triple-Checker</h1>";
print "<form>
<table width='80%' style='margin:auto'>
<tr>
<td align='right'>URI/URL:</td><td width='100%'><input id='uri' name='uri' value='".htmlspecialchars($check_uri)."' style='width:100%' /></td></tr>
</table>
<div><input style='margin-top:0.5em' value='Check Again' type='submit' /></div>
</form>";

if( sizeof($errors) )
{
	print "<div class='error'><h3>Error loading: ".htmlspecialchars($check_uri)."</h3><ul><li>".join( "</li><li>",$errors)."</li></ul></div>";
	render_footer();
	exit;
}

$triples = $parser->getTriples();
$n = sizeof( $triples );
print "<div class='message'>Loaded $n triples</div>";

######################################################
# Find Namespaces, Classes, Predicates
######################################################

$namespaces = array();
foreach( $triples as $t )
{
	if( $t["p"] == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type" )
	{
		list( $ns, $term ) = split_uri( $t["o"] );
		@$namespaces[$ns]["class"][$term]++;
	}
	list( $ns, $term ) = split_uri( $t["p"] );
	@$namespaces[$ns]["predicate"][$term]++;
}

print "<table class='results'>";
print "<tr>";
print "<th>Count</th>";
print "<th>Type</th>";
print "<th style='text-align:right'>Namespace</th>";
print "<th>Term</th>";
print "<th colspan='2'>Looks Legit?</th>";
print "</tr>";
foreach( $namespaces as $ns=>$terms )
{	
	$graph = new Graphite();
	$n = $graph->load( $ns );
	$loaded_ns = $n > 0;
	
	foreach( $terms as $type=>$list )
	{
		foreach( $list as $term=>$count )
		{
			if( !$loaded_ns ) 
			{
				print "<tr class='unknown'>";
			}
			elseif( sizeof( $graph->resource( $ns.$term )->relations() ) )
			{
				print "<tr class='good'>";
			}
			else
			{
				print "<tr class='bad'>";
			}
			print "<td class='count'>$count</td>";
			print "<td class='type'>$type</td>";
			print "<td class='namespace'>$ns</td>";
			print "<td class='term'>$term</td>";
			if( !$loaded_ns ) 
			{
				print "<td class='legit'>?</td>";
				print "<td class='comment'> - Namespace did not resolve.</td>";
			}
			elseif( sizeof( $graph->resource( $ns.$term )->relations() ) )
			{
				print "<td class='legit'>OK</td>";
				print "<td class='comment'> - Term is defined by namespace.</td>";
			}
			else
			{
				print "<td class='legit'>BAD</td>";
				print "<td class='comment'> - Term NOT found in namespace.</td>";
			}
				
			print "</tr>";
		}
	}
}
print "</table>";
print "<hr size='1' />";
render_footer();

exit;
function split_uri( $uri)
{
	$uri = preg_match( '/^(.*[#\/])([^#\/]*)$/', $uri, $parts );
	return array( $parts[1], $parts[2] );
}


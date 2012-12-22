<?php

require_once( "arc/ARC2.php" );
require_once( "Graphite/Graphite.php" );

print "<h1>Triple Checker</h1>";
if( !isset( $_GET['uri'] ) )
{
	render_header();
?>
<p>This tool helps find typos and common errors in RDF data.</p>

<p>Enter a URI or URL which will resolve to some RDF Triples.</p>
<form>
<table width='80%' style='margin:auto'>
<tr>
<td align='right'>URI/URL:</td><td width='100%'><input id='uri' name='uri' value='' style='width:100%' /></td></tr>
</table>
<div><input style='margin-top:0.5em' value='Convert' type='submit' /></div>
</form>



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
if( sizeof($errors) )
{
	show_error( "<h3>Error loading: ".htmlspecialchars($check_uri)."</h3><ul><li>".join( "</li><li>",$errors)."</li></ul>" );
	exit;
}
$triples = $parser->getTriples();
print "<h2>".htmlspecialchars( $check_uri )."</h2>";
$n = sizeof( $triples );
show_msg( "Loaded $n triples" );

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

print "<table>";
print "<tr>";
print "<th>Namespace</th>";
print "<th>Term</th>";
print "<th>Type</th>";
print "<th>Count</th>";
print "<th>Legit?</th>";
print "<th></th>";
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
			print "<tr>";
			print "<td style='text-align:right'>$ns</td>";
			print "<td>$term</td>";
			print "<td>$type</td>";
			print "<td style='text-align:right'>$count</td>";
			if( !$loaded_ns ) 
			{
				print "<td style='background-color: #999;'>?</td>";
				print "<td style='background-color: #999;'>Namespace did not resolve.</td>";
			}
			elseif( sizeof( $graph->resource( $ns.$term )->relations() ) )
			{
				print "<td style='background-color: #9f9;'>OK</td>";
				print "<td style='background-color: #9f9;'>term is defined by namespace.</td>";
			}
			else
			{
				print "<td style='background-color: #f99;'>X</td>";
				print "<td style='background-color: #f99;'>term NOT found in namespace.</td>";
			}
				
			print "</tr>";
		}
	}
}
print "</table>";


exit;

function show_error( $msg )
{
	print "<div style='border: solid 1px red; background-color:yellow; padding:0.5em;margin:1em 0 1em 0'>";
	print $msg;
	print "</div>";
}
function show_msg( $msg )
{
	print "<div style='border: solid 1px black; background-color:#ccc; padding:0.5em;margin:1em 0 1em 0'>";
	print $msg;
	print "</div>";
}
function split_uri( $uri)
{
	$uri = preg_match( '/^(.*[#\/])([^#\/]*)$/', $uri, $parts );
	return array( $parts[1], $parts[2] );
}

function render_header($title)
{
header( "Content-type: text/html; charset:utf-8" );
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
 "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
   <title><?php print $title; ?></title>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
   <style type='text/css'>
body {
	font-family: sans-serif;
}
.bookmarklet {
	border-top: solid 1px #c6c6c6;
	border-left: solid 1px #c6c6c6;
	border-bottom: solid 1px #969696;
	border-right: solid 1px #969696;
	background-color: #e9e9e9;
	padding: 2px;
	text-decoration: none;
	color: #000000;
	font-family: sans-serif;
	font-size: 90%;
	font-weight: bold;
}
a:hover { text-decoration: underline !important; }

body { text-align: center; }
</style>
<?php
}

function render_footer()
{
?>
<p style='font-size: 80%'>stuff2rdf is powered by <a href='http://arc.semsol.org/'>ARC2</a> and hosted by <a href='http://www.ecs.soton.ac.uk/'>ECS</a> at the <a href='http://www.soton.ac.uk/'>University of Southampton</a>.</p>
<table style='font-size: 80%; margin: auto; text-align:left'>
<tr><td><tt>
&lt;<a style='text-decoration: none; color: green; ' href='http://graphite.ecs.soton.ac.uk/stuff2rdf/'>http://graphite.ecs.soton.ac.uk/stuff2rdf/</a>&gt; 
rdfs:seeAlso
&lt;<a style='text-decoration: none; color: green; ' href='http://graphite.ecs.soton.ac.uk/sparqlbrowser/'>http://graphite.ecs.soton.ac.uk/sparqlbrowser/</a>&gt; 
</tt></td></tr>

</table>
</body></html>
<?php
}


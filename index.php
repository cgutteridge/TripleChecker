<?php
require_once( "TripleChecker.php" );

# calculate base path
$path = explode("/", __FILE__);
array_pop( $path ); # lose filename
$base_dir = join( "/", $path );

$prefs = array( 
	"cache"=>"$base_dir/cache",
	"namespaces"=>"$base_dir/etc/namespaces.tsv",
	"cacheUnknownNamespaces"=>false,
	"reloadCache"=>false,
	"cacheAge"=>120, # seconds,
);

require_once( "$base_dir/etc/template.php" );
#$_GET['uri'] = 'https://raw.github.com/structureddynamics/Bibliographic-Ontology-BIBO/1.3/bibo.xml.owl';
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
$tc = new TripleChecker($prefs);
$result = $tc->checkURI( $check_uri );


render_header( "results", htmlspecialchars( $result["uri"] )." - RDF Triple-Checker");
print "<h1>RDF Triple-Checker</h1>";
print "<form>
<table width='80%' style='margin:auto'>
<tr>
<td align='right'>URI/URL:</td><td width='100%'><input id='uri' name='uri' value='".htmlspecialchars($result["uri"])."' style='width:100%' /></td></tr>
</table>
<div><input style='margin-top:0.5em' value='Check Again' type='submit' /></div>
</form>";

if( !$result["loaded"] )
{
	print "<div class='error'><h3>Error loading: ".htmlspecialchars($result["uri"])."</h3><ul><li>".join( "</li><li>",$result["load_errors"])."</li></ul></div>";
	render_footer();
	exit;
}

print "<div class='message'>Loaded ".$result["n"]." triples</div>";
######################################################
# Compare namespaces to dictionary
######################################################

print "<h2>Namespaces</h2>";
print "<table class='results'>";
print "<tr>";
print "<th>Namespace</th>";
print "<th>Looks Legit?</th>";
print "</tr>";
foreach ( $result["namespaces"] as $ns=>$info )
{
	$nearest = $info["nearest"];
	$nscell = "<td><a href='$ns'>".htmlspecialchars( $ns )."</a></td>";
	if( $nearest["distance"] == 0 )
	{
		print "<tr class='good'>";
		print $nscell;
		print "<td>Matched common namespace. Prefix <strong>".$nearest["prefix"]."</strong></td>";
		print "</tr>";
	}
	elseif( $nearest["distance"] <= 3 )
	{
		print "<tr class='bad'>";
		print $nscell;
		print "<td>VERY close match to &lt;".$nearest["namespace"]."&gt; .. probable typo? <span class='diff'>[diff=".$nearest["distance"]."]</span></td>";
		print "</tr>";
	}
	elseif( $nearest["distance"] <= 8 )
	{
		print "<tr class='bad'>";
		print $nscell;
		print "<td>Somewhat similar to &lt;".$nearest["namespace"]."&gt; .. possible typo? <span class='diff'>[diff=".$nearest["distance"]."]</span></td>";
		print "</tr>";
	}
	else
	{
		print "<tr class='unknown'>";
		print $nscell;
		print "<td>No match to common namespaces</td>";
		print "</tr>";
	}
}
print "</table>";

	
	
######################################################
# Check terms in namespaces
######################################################

	

print "<h2>Terms</h2>";

print "<table class='results'>";
print "<tr>";
print "<th>Count</th>";
print "<th>Type</th>";
print "<th style='text-align:right'>Namespace</th>";
print "<th>Term</th>";
print "<th colspan='2'>Looks Legit?</th>";
print "</tr>";
$ranges = array();
foreach ( $result["namespaces"] as $ns=>$info )
{	
	foreach( $info["terms"] as $type=>$list )
	{
		foreach( $list as $term=>$count )
		{
			if( !$loaded_ns ) 
			{
				print "<tr class='unknown'>";
			}
			elseif( !isset( $ns_terms[$ns.$term] ) )
			{
				print "<tr class='bad'>";
			}
			elseif( !isset( $ns_terms[$ns.$term][$type] ) )
			{
				print "<tr class='bad'>";
			}
			else
			{
				print "<tr class='good'>";
			}
			print "<td class='count'>$count</td>";
			print "<td class='type'>$type</td>";
			print "<td class='namespace'>$ns</td>";
			print "<td class='term'><a href='$ns$term'>$term</a></td>";
			if( !$loaded_ns ) 
			{
				print "<td class='legit'>?</td>";
				print "<td class='comment'> - $ns_error.</td>";
			}
			elseif( !isset( $ns_terms[$ns.$term] ) )
			{
				print "<td class='legit'>BAD</td>";
				print "<td class='comment'> - Term is not defined by namespace.</td>";
			}
			elseif( !isset( $ns_terms[$ns.$term][$type] ) )
			{
				print "<td class='legit'>BAD</td>";
				print "<td class='comment'> - Term is incorrect type.</td>";
			}
			else
			{
				print "<td class='legit'>OK</td>";
				print "<td class='comment'> - Looks good.</td>";
			}
				
			print "</tr>";
		}
	}

}
print "</table>";
print "<pre style='text-align:left'>".htmlspecialchars( print_r( $result ,true))."</pre>";
exit;

#if( sizeof( $ranges ) )
#{
#	print "<h2>Datatypes</h2>";
#	$errors = 0;
#	foreach( $triples as $t )
#	{
#		# skip triple if the range didn't get defined
#		if( !isset( $ranges[ $t["p"] ] ) ) { continue; }
#		# skip test if the range isn't an XML literal
#		list( $ns, $term ) = split_uri( $ranges[ $t["p"] ] );
#		if( $ns != "http://www.w3.org/2001/XMLSchema#" ) { continue; }
#
#		if( $t["o_datatype"] != $ranges[ $t["p"] ] )	
#		{	
#			print "Expected: ".$ranges[ $t["p"] ]." in triple: ";
#			print_r( $t );
#			print "<hr size='1' />";
#			++$errors;
#		}
#	}
#	if( $errors == 0 )
#	{
#		print "<p>No obvious datatype issues.</p>";
#	}
#}

	
render_footer();

exit;

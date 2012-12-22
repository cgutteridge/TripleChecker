<?php

function render_header($class,$title)
{
header( "Content-type: text/html; charset:utf-8" );
?>
<!DOCTYPE HTML>
<html>
<head>
   <title><?php print $title; ?></title>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
   <link rel='stylesheet' href='checker.css' />
</head>
<body class='<?php print $class; ?>'>
<a href="https://github.com/cgutteridge/TripleChecker"><img style="position: absolute; top: 0; right: 0; border: 0;" src="forkme_right_green_007200.png" alt="Fork me on GitHub"></a>

<?php
}

function render_footer()
{
?>
<p style='font-size: 80%'>triple-checker is powered by 
<a href='http://graphite.ecs.soton.ac.uk/'>Graphite</a>
<a href='http://arc.semsol.org/'>ARC2</a>
 and hosted by <a href='http://www.ecs.soton.ac.uk/'>ECS</a> at the <a href='http://www.soton.ac.uk/'>University of Southampton</a>.</p>
<table style='font-size: 80%; margin: auto; text-align:left'>

<tr><td><tt>
&lt;<a style='text-decoration: none; color: green; ' href='http://graphite.ecs.soton.ac.uk/checker/'>http://graphite.ecs.soton.ac.uk/checker/</a>&gt; 
foaf:maker 
&lt;<a style='text-decoration: none; color: green; ' href='http://id.ecs.soton.ac.uk/person/1248'>http://id.ecs.soton.ac.uk/person/1248</a>&gt; .
</tt></td></tr>
<tr><td><tt>
&lt;<a style='text-decoration: none; color: green; ' href='http://id.ecs.soton.ac.uk/person/1248'>http://id.ecs.soton.ac.uk/person/1248</a>&gt; foaf:name "Christopher Gutteridge" .
</tt></td></tr>

<tr><td><tt>
&lt;<a style='text-decoration: none; color: green; ' href='http://graphite.ecs.soton.ac.uk/checker/'>http://graphite.ecs.soton.ac.uk/checker/</a>&gt; 
rdfs:seeAlso
&lt;<a style='text-decoration: none; color: green; ' href='http://graphite.ecs.soton.ac.uk/browser/'>http://graphite.ecs.soton.ac.uk/browser/</a>&gt; .
</tt></td></tr>

</table>



</body></html>
<?php
}


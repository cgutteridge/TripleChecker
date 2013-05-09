<?php

require_once( "arc2/ARC2.php" );
	
	
class TripleChecker {
	
	private $prefs;
	
	public function __construct( $prefs )
	{
		$this->prefs = $prefs;
	}

	public function checkURI( $uri )
	{
		$dictionary = file( $this->prefs["namespaces"] );
	
		$r = array( "uri"=>$uri );
	
		# Load data
	
		$opts = array();
		$opts['http_accept_header']= 'Accept: application/rdf+xml; q=0.9, text/turtle; q=0.8, */*; q=0.1';
		$parser = ARC2::getRDFParser($opts);
		$parser->parse( $uri );
		$r["load_errors"] = $parser->getErrors();
		$r["loaded"] = (sizeof($r["load_errors"]) == 0);
		$parser->resetErrors();
		if( ! $r["loaded"] ) { return $r; }
	
		$triples = $parser->getTriples();
		$r["n"] = sizeof( $triples );
		
		# Find Namespaces, Classes, Predicates
		
		$r["namespaces"] = array();
		foreach( $triples as $t )
		{
			if( $t["p"] == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type" )
			{
				list( $ns, $term ) = TripleChecker::splitURI( $t["o"] );
				@$r["namespaces"][$ns]["classes"][$term]["count"]++;
			}
			list( $ns, $term ) = TripleChecker::splitURI( $t["p"] );
			@$r["namespaces"][$ns]["properties"][$term]["count"]++;
		}
		ksort( $r["namespaces"] );
	
		# Compare namespaces to common namespaces from dictionary
		
		foreach( $r["namespaces"] as $ns=>$dummy )
		{
			$r["namespaces"][$ns]["nearest"]["prefix"] = null;
			$r["namespaces"][$ns]["nearest"]["namespace"] = null;
			$r["namespaces"][$ns]["nearest"]["distance"] = 9999999;
			foreach( $dictionary as $row )
			{
				if( substr( $row,0,1 ) == "#" ) { continue; }
				list( $a_prefix, $a_namespace ) = preg_split( "/\t/", chop( $row ) );
				$distance = levenshtein( $ns, $a_namespace );
				if( $r["namespaces"][$ns]["nearest"]["distance"] > $distance ) 
				{
					$r["namespaces"][$ns]["nearest"]["distance"] = $distance;
					$r["namespaces"][$ns]["nearest"]["prefix"] = $a_prefix;
					$r["namespaces"][$ns]["nearest"]["namespace"] = $a_namespace;
				}
				if( $distance == 0 ) { break; }
			}
	
			# do we cache this namespace?
				# can we load from the cache?
					# if yes, then load it from cache
	
			# if not loaded from cache
				# load  it from web.
				# if we chache this namespace
					# save cache
			
		#"reloadCache"=>false,
		#"cacheAge"=>-1, 
	
			$nsUsesCache = isset( $this->prefs["cache"] );
			if( $distance>0 && ! $this->prefs["cacheUnknownNamespaces"] )
			{
				# if it's not in the dictionary (distance>0) don't use the
				# cache for this namespace unless an option is explictly set
				$nsUsesCache = false;
			}
	
			if( !$nsUsesCache )
			{
				$ns_terms = $this->downloadNamespace( $r, $ns );
			}
			else
			{
				$cacheFile = $this->prefs["cache"]."/".md5( $ns ).".json";
	
				$loadCache = false;;
				if( file_exists( $cacheFile ) )
				{
					$loadCache = true;
					$age = time() - filemtime( $cacheFile );
					if( $age > $this->prefs["cacheAge"] )
					{
						$loadCache = false;
					}
					if( $this->prefs["reloadCache"] )
					{
						$loadCache = false;
					}
				}
	
				if( $loadCache )
				{
					$ns_terms = json_decode( file_get_contents( $cacheFile ) );
				}
				else
				{
					$ns_terms = $this->downloadNamespace( $r, $ns );
	
					if( $r["namespaces"][$ns]["loaded"] )
					{
						# write cache
						$fh = fopen( $cacheFile, "w" );
						fwrite( $fh, json_encode( $ns_terms ) );
						fclose( $fh );	
					}
				}
			}
	
	
			# if it's loaded OK with no terms, it's OK to cache that fact, but 
			# we don't consider it loaded for the next step's purposes	
			if( sizeof( $ns_terms ) == 0 )
			{
				$r["namespaces"][$ns]["loaded"] = false;
				$r["namespaces"][$ns]["status"] = "No vocab terms found in namespace";
				continue;
			}

print "<pre style='text-align:left'>".htmlspecialchars( print_r( $ns_terms ,true))."</pre>";
		}
	
		return $r;
	}
	
	
	private function downloadNamespace( &$r, $ns )
	{
		$opts = array();
		$opts['http_accept_header']= 'Accept: application/rdf+xml; q=0.9, text/turtle; q=0.8, */*; q=0.1';
		$parser = ARC2::getRDFParser($opts);
	
		$parser->parse( $ns );
		$r["namespaces"][$ns]["load_errors"] = $parser->getErrors();
		$r["namespaces"][$ns]["loaded"] = sizeof( $r["namespaces"][$ns]["load_errors"] ) == 0;

		if( ! $r["namespaces"][$ns]["loaded"] )
		{
			$r["namespaces"][$ns]["status"] = "Failed to load namespace";
			return;
		}
		
		$ns_triples = $parser->getTriples();
		if( sizeof( $ns_triples ) == 0 )
		{
			$r["namespaces"][$ns]["loaded"] = false;
			$r["namespaces"][$ns]["status"] = "Found no triples";
			return;
		}
		
		$termtypes = array(
			"http://www.w3.org/1999/02/22-rdf-syntax-ns#Property" => "properties",
			"http://www.w3.org/2000/01/rdf-schema#Class" => "classes",
			"http://www.w3.org/2002/07/owl#ObjectProperty" => "properties",
			"http://www.w3.org/2002/07/owl#DatatypeProperty" => "properties",
			"http://www.w3.org/2002/07/owl#AnnotationProperty" => "properties",
			"http://www.w3.org/2002/07/owl#OntologyProperty" => "properties",
			"http://www.w3.org/2002/07/owl#Class" => "classes",
			);

		$ns_terms = array();				
		foreach( $ns_triples as $t )
		{
			if( $t["p"] == "http://www.w3.org/2000/01/rdf-schema#range" )
			{
				$ns_terms["ranges"][ $t["s"] ] = $t["o"];
			}
			if( $t["p"] == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type" )
			{
				if( isset( $termtypes[ $t["o"] ] ) )
				{
					$ns_terms[ $termtypes[ $t["o"] ] ][ $t["s"] ] = true;
				}
			}
		}
		
		return $ns_terms;
	}

	
	private static function splitURI( $uri)
	{
		$uri = preg_match( '/^(.*[#\/])([^#\/]*)$/', $uri, $parts );
		return array( $parts[1], $parts[2] );
	}
	
	
}


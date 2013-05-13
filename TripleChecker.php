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
		unset( $r["load_errors"] );
	
		$triples = $parser->getTriples();
		$r["triples_count"] = sizeof( $triples );
		
		# Find Namespaces, Classes, Predicates
	
		# Assumption: terms are never both classes and properties
		# (a later version may check for this)	
		$r["namespaces"] = array();
		foreach( $triples as $t )
		{

			if( $t["p"] == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type" )
			{
				list( $ns, $term ) = TripleChecker::splitURI( $t["o"] );

				@$r["namespaces"][$ns]["terms"][$term]["count"]++;
				@$r["namespaces"][$ns]["terms"][$term]["type"] = "class";
			}
			list( $ns, $term ) = TripleChecker::splitURI( $t["p"] );
			@$r["namespaces"][$ns]["terms"][$term]["count"]++;
			@$r["namespaces"][$ns]["terms"][$term]["type"] = "property";
		}
		ksort( $r["namespaces"] );
	
		# Compare namespaces to common namespaces from dictionary
		
		foreach( $r["namespaces"] as $ns=>$dummy )
		{
			$r["namespaces"][$ns]["nearest"]["prefix"] = null;
			$r["namespaces"][$ns]["nearest"]["namespace"] = null;
			$r["namespaces"][$ns]["nearest"]["distance"] = 9999999;
			$r["namespaces"][$ns]["found"] = false;
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
				if( $distance == 0 ) 
				{ 
					$r["namespaces"][$ns]["found"] = true;
					$r["namespaces"][$ns]["prefix"] = $r["namespaces"][$ns]["nearest"]["prefix"];
					unset( $r["namespaces"][$ns]["nearest"] );
					
					break; 
				}
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
				$ns_docinfo = $this->downloadNamespace( $r, $ns );
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
					$ns_docinfo = json_decode( file_get_contents( $cacheFile ), true );
					$r["namespaces"][$ns]["loaded"] = true;
				}
				else
				{
					$ns_docinfo = $this->downloadNamespace( $r, $ns );
	
					if( $r["namespaces"][$ns]["loaded"] )
					{
						# write cache
						unlink( $cacheFile );
						$fh = fopen( $cacheFile, "w" );
						fwrite( $fh, json_encode( $ns_docinfo ) );
						fclose( $fh );	
					}
				}
			}
	
	
			# if it's loaded OK with no terms, it's OK to cache that fact, but 
			# we don't consider it loaded for the next step's purposes	
			if( sizeof( $ns_docinfo ) == 0 )
			{
				$r["namespaces"][$ns]["loaded"] = false;
				$r["namespaces"][$ns]["load_errors"] = array( "No vocab terms found in namespace" );
				continue;
			}
			foreach( $r["namespaces"][$ns]["terms"] as $term=>&$info )
			{
				# check through the terms in this namespace and compare 
				# distance 

				$info["nearest"]["term"] = null;
				$info["nearest"]["distance"] = 9999999;
				$info["found"] = false;
#print_( $ns_terms[$type]);
				foreach( $ns_docinfo["terms"] as $term_from_ns=>$type )
				{
					$distance = levenshtein( $ns.$term, $term_from_ns );
					if( $info["nearest"]["distance"] > $distance ) 
					{
						list( $near_ns, $near_term ) = TripleChecker::splitURI( $term_from_ns );
						$info["nearest"]["term"] = $near_term;
						$info["nearest"]["namespace"] = $near_ns;
						$info["nearest"]["type"] = $type;
						$info["nearest"]["distance"] = $distance;
					}
					if( $distance == 0 ) 
					{ 
						unset( $info["nearest"] );
						$info["found"] = true;
						break; 
					}
				}
#exit
			}
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
			return;
		}
		unset( $r["namespaces"][$ns]["load_errors"] );
		
		$ns_triples = $parser->getTriples();
		if( sizeof( $ns_triples ) == 0 )
		{
			$r["namespaces"][$ns]["loaded"] = false;
			$r["namespaces"][$ns]["load_errors"] = array( "Found no triples" );
			return;
		}
		
		$termtypes = array(
			"http://www.w3.org/1999/02/22-rdf-syntax-ns#Property" => "property",
			"http://www.w3.org/2000/01/rdf-schema#Class" => "class",
			"http://www.w3.org/2002/07/owl#ObjectProperty" => "property",
			"http://www.w3.org/2002/07/owl#DatatypeProperty" => "property",
			"http://www.w3.org/2002/07/owl#AnnotationProperty" => "property",
			"http://www.w3.org/2002/07/owl#OntologyProperty" => "property",
			"http://www.w3.org/2002/07/owl#Class" => "class",
			"http://www.daml.org/2001/03/daml+oil#Class" => "class",
			"http://www.daml.org/2001/03/daml+oil#DatatypeProperty" => "property",
			"http://www.daml.org/2001/03/daml+oil#ObjectProperty" => "property",
			"http://www.daml.org/2001/03/daml+oil#Property" => "property",
			
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
					$ns_terms["terms"][ $t["s"] ] = $termtypes[ $t["o"] ];
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


<?php
/**
 * Smallaxe templating engine
 * examples: code.smallaxesolutions.com/smallaxe-templating for more
 * source: github.com/sethadam1/smallaxe-templating 
 */
 
namespace Smallaxe;

class smallaxe_template { 
	
	public $tmpl_path;
	public $mc;
	public $ttl; 
	private $caching;
	private $cache_compiled; 
		
	function __construct($tmpl_path,$options=[]) {
		$this->tmpl_path  = $tmpl_path;
		if('/' != substr($this->tmpl_path, -1)) {
			$this->tmpl_path."/"; 
		}
		$this->mc = false; 
		$this->ttl = 0; 
		$this->caching = false; 
		$this->cache_compiled = false; 
		$this->current_template = false; 
		$this->default_fx = ['ucfirst','ucwords','strtoupper','strtolower','htmlspecialchars','trim','nl2br', 'number_format','stripslashes', 'strip_tags', 'md5','intval'];  
		$this->all_supported = ['addcslashes', 'addslashes', 'bin2hex', 'chop', 'chr', 'chunk_split', 'convert_cyr_string', 'convert_uudecode', 'convert_uuencode', 'count_chars', 'crc32', 'crypt', 'get_html_translation_table', 'hex2bin', 'html_entity_decode', 'htmlentities', 'htmlspecialchars_decode', 'lcfirst', 'ltrim', 'metaphone', 'money_format',  'ord', 'quotemeta', 'rtrim', 'sha1', 'soundex', 'str_rot13', 'str_word_count',  'stripcslashes', 'strlen', 'strrev', 'strtok','floatval','ceil','floor' ];
		$this->allow_fx = $this->default_fx; 
	}	
	
	/**
	* enable_cache()
	*
	* @param memcached - a memcache or memcached object
	* @param ttl - an integer, seconds to keep template in memory cache, default: 300/5 mins
	* @return void
	*/		
	public function enable_cache($memcached,$ttl=300) {
		if(is_object($memcached)) { 
			$this->mc = $memcached; 
			$this->ttl = $ttl;
			$this->caching = true; 
		}
	}
	
	/**
	* cache_compiled()
	*
	* @param cache_status - boolean informing whether compiled templates should be cached 
	* @return void
	*/		
	public function cache_compiled($cache_status=false) {
		$this->cache_compiled = (true===$cache_status) ? true : false;
		return;   
	}	
	
	/**
	* cache_create()
	*
	* @param $tmpl - template name
	* @param $text - template text to cache
	* @param ttl - an integer, seconds to keep template in memory cache, default: 86400/1 dsay
	* @return void
	*/		
	public function cache_create($tmpl,$text,$ttl=86400) {
		if($this->caching) {
			$this->mc->add(md5(".".$tmpl),$text,$ttl); 
		}
	}	

	/**
	* cache_date()
	*
	* @param $tmpl - template name
	* @param $text - template text to cache
	* @param ttl - an integer, seconds to keep template in memory cache, default: 86400/1 day
	* @return void
	*/		
	public function cache_update($tmpl,$text,$ttl=86400) {
		if($this->caching) {
			$this->mc->set(md5(".".$tmpl),$text,$ttl); 
		}
	}	

	/**
	* cache_destroy()
	*
	* @param $tmpl 
	* @return void
	*/		
	public function cache_detroy($tmpl) {
		if($this->caching) {
			$this->mc->delete(md5(".".$tmpl)); 
		}
	}	

	/**
	* cache_read()
	*
	* @param $tmpl - template name
	* @param $text - template text to cache
	* @param ttl - an integer, seconds to keep template in memory cache, default: 300/5 mins
	* @return string template text on success, or boolean false if no template is found 
	*/		
	public function cache_read($tmpl) {
		if($this->caching) {
			$ctmpl = $this->mc->get(md5(".".$tmpl)); 
			if($ctmpl) { return $ctmpl; }
		}
		return false; 
	}	
	
	/**
	* extend()
	*
	* add supported functions to template renderer
	* @return void
	*/		
	public function extend($functions=[]) {
		foreach($functions as $fx) { 
			if(!in_array(strtolower($fx),['exec','system','passthru','shell_exec'])) {
				$this->allow_fx[] = $fx; 
			}
		}
	}

	/**
	* uncache()
	*
	* delete a template from memory cache
	* @return void
	*/		
	public function uncache($tmpl) { 
		if($this->caching) {
			$this->mc->delete(md5($tmpl));
		}
	} 

	/**
	* unextend()
	*
	* reset allowed functions to only defaults
	* @return void
	*/		
	public function unextend() { 
		$this->allow_fx = $this->default_fx;
	}

	/**
	* load_supported_functions()
	*
	* loads all known supported functions 
	* @return void
	*/		
	public function load_supported_functions() { 
		$this->allow_fx = array_merge($this->all_supported, $this->default_fx);
	}

	/**
	* load_template()
	*
	* @param $tmpl   - template file
	* @return string - template contents
    */		
	function load_template($tmpl) {
		if($this->caching) { 
			$text = $this->mc->get(md5($tmpl)); 
			if($text) { return $text; }
		}
		if(file_exists($this->tmpl_path.$tmpl)) {
			$text = file_get_contents($this->tmpl_path.$tmpl);
			if($this->caching) { $this->mc->set(md5($tmpl), $text, $this->ttl); }
			return $text;   
		} elseif(file_exists($this->tmpl_path.$tmpl.".tmpl")) {
				$tmpl .= ".tmpl";
				$text = file_get_contents($this->tmpl_path.$tmpl);
				if($this->caching) { $this->mc->set(md5($tmpl), $text, $this->ttl); }
			return $text;   
		} elseif(file_exists($this->tmpl_path.$tmpl.".tpl")) {
			$tmpl .= ".tpl";
			$text = file_get_contents($this->tmpl_path.$tmpl);
			if($this->caching) { $this->mc->set(md5($tmpl), $text, $this->ttl); }
			return $text;   						
		} elseif(file_exists($tmpl)) {
			$text = file_get_contents($tmpl); 
			if($this->caching) { $this->mc->set(md5($tmpl), $text, $this->ttl); }
			return $text;			
		} else { 
			return false;  
		}
	}
	
	/**
	* render()
	*
	* @param $tmpl   - template file
	* @param $args   - Associative array of variables to pass to the template file.
	* @return string - Output of the template file. Likely HTML.
    */	
	function render($template,$args) {
		/* the next few lines will extract embedded templates  */ 
		preg_match_all('/(\{\{\@template file=)([A-Za-z0-9-_.,]+)(\}\})/U',$template,$template_matches); 
		if(is_array($template_matches)) { foreach($template_matches[2] as $key=>$tm) {
			$sub = $this->load_template($tm); 
			$sub_data = $args; 
			if(is_array($args[$tm])) { foreach($args[$tm] as $k=>$v) { $sub_data[$k] = $v; } }
			$repl = $this->render($sub,$sub_data);
			$template = str_replace($template_matches[0][$key],$repl,$template);			
		} }
		/* the above few lines will extract embedded templates  */ 
		/* loops  */ 
		unset($matches,$repl); 
		preg_match_all('/((\{\{\@loop data=)([A-Za-z0-9_= ]+)(\}\}))(.+)((\{\{)\/loop(\}\}))/Uis',$template,$matches);
		if(is_array($matches)) { 
			foreach($matches[0] as $key=>$pattern) {
				if(''==trim($pattern)) { continue; }
				if(''==$matches[3][$key]) { continue; }
				$start 	= $matches[1][$key];  	 
				$end 	= $matches[6][$key];
				$template_text = str_replace($start,'',$pattern); 
				$template_text = str_replace($end,'',$template_text); 
				$templ_data = $args[$matches[3][$key]];
				if(is_array($templ_data)) {		
					foreach($templ_data as $subdata) {
						$repl .= $this->render(trim($template_text),$subdata);
					}
					$template = str_replace($pattern,$repl,$template);
				}		
			} 
			unset($matches);
		}		
		/* end loop section */ 
		if(is_array($args)):
			foreach($args as $k=>$v):  
				preg_match_all(
					'/(\{\{)('.$k.')(\|)([A-Za-z0-9-_:|]+)(\}\})/U', $template, $matches
				); 
				if(is_array($matches)) {
					foreach($matches[0] as $key=>$pattern):
						if(!$pattern) { continue; }  
						$string 	= $v; 
						$functions	= explode("|",$matches[4][$key]); 
						foreach($functions as $fx):
							switch($fx): //the function name
								case 'upper': 
									$string = strtoupper($string); 
									break; 
								case 'lower': 
									$string = strtolower($string); 
									break; 
								case 'sup': 
									$string = "<sup>".$string."</sup>"; 
									break; 
								case 'sub': 
									$string = "<sub>".$string."</sub>"; 
									break; 
								case 'escape': 
								case 'e': 
									$string = htmlspecialchars($string,ENT_QUOTES); 
									break;
								default: 
									if(stristr($fx, ":")) {
										$fxparts = explode(":",$fx);   
										$fx2 = $fxparts[0]; 
										switch($fx2):
											case 'substr':
												$string = strtolower($string); 
												$start = $fxparts[1]; 
												$length = $fxparts[2]; 
												$string = substr($string, $start, $length); 
												break; 
											case 'ellipsis':
												$strlen = strlen($string);
												if($strlen>$fxparts[1]) {
													$string = substr($string, 0, $fxparts[1])."&#8230;"; 
												} 
												break; 												
											default: 
												break; 	 
										endswitch; 
									} 
									if(function_exists($fx)) {
										if(in_array($fx,$this->allow_fx)) {
											$string = $fx($string); 
										}
									}
									break; 
							endswitch; 
						endforeach; // end functions
						$template = str_replace($pattern,$string,$template); 
					endforeach; // end matches
					unset($matches,$var,$string,$pattern,$functions); 
				} 
				// date replacements
				$template = preg_replace_callback('/(\{\{date\|)([A-Za-z0-9-, |]+)(\}\})/U',function($matches) {
					return date($matches[2]); 
				},$template); 	
				// strip C style and curly star comments
				$template = preg_replace_callback('/([\/\{]\*)([A-Za-z0-9\s]+)(\*[\}|\/])/U',function($matches) {
					return '';
				},$template);	 
				// strip C# style single line comments
				$template = preg_replace_callback('/\/\/([A-Za-z0-9,\# ]+)/',function($matches) {
					return '';
				},$template);
				// dynamic placeholder replacement
				$repl = ['[[uniqid]]','[[year]]','[[timestamp]]','[[datetime]]','[[utcdatetime]]'];
				$with = [uniqid(), date("Y"), date("U"), date("Y-m-d G:i:s"), gmdate("Y-m-d G:i:s")];
				$template = str_replace($repl,$with,$template); 	
				// simple var replacement		
				$template = str_replace('{{'.$k.'}}',$v,$template); 
			endforeach; 
		endif; 
		/* 
		// this is where we will cache compiled template in the future
		if($this->cache_compiled) { $this->cache_update($template,$text,$ttl=86400)}
		*/
		return $template; 
	}
}


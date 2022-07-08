<?php

/****************************************************************************/
/***  #####_ #####, ###### ##,  #  ####_ ###### #####_ .####, ##      *******/
/***  ##  .# ##  .#   ##   ###, # ##   `   ##   ##  .# ##   # ##      *******/
/***  #####^ ####*    ##   ## #,# ##   _   ##   #####^ ###### ##      *******/
/***  ##     ##  ^# ###### ##  ##  ####` ###### ##     ##   # ######  *******/
/****************************************************************************
 *
 *	
 *
*/


class Principal {


	/*
	 *	Principal configuration:
	*/
	public static bool $use_attrs = true;	/* use attribute line? */
	public static bool $build_divs = true;	/* build divs with ()? */
	public static string $css_pre = "";		/* CSS preprocessor callback */
	public static $tx_style = array();		/* (o,c,t,a,x,) text formatting tags */
	
	/*
	 *	Recognized blocks:
	*/
	public static $blk_entire = array();	/* (m,seq,) whole block matches */
	public static $blk_regex = array();		/* (m,seq,) whole block regex */
	public static $blk_initial = array();	/* (m,fnc,) initial chars and format */
	public static $blk_function = array();	/* (m,fnc,) name and function name */
	public static $blk_inline = array();	/* (m,fnc,) string and function name */
	
	/*
	 *	Terms to find and replace in text blocks:
	*/
	public static $fr_links = array();		/* (m,url,) auto-links */
	public static $fr_abbr = array();		/* (m,title,) <abbr> tag wrap */
	public static $fr_acro = array();		/* (m,title,) <acronym> tag wrap */
	public static $fr_match = array();		/* (m,seq,) unconditional matches */
	public static $fr_terms = array();		/* (m,seq,) automatic wrapping */
	public static $fr_square = array();		/* (m,seq,) square bracket matches */

	/*
	 *	Settings for building tables:
	*/
	public static string $tab_colspan = '_';	/* indicates cell is part of colspan */
	public static string $tab_rowspan = '|';	/* indicates cell is part of rowspan */
	public static string $tab_empty = '-';		/* indicates cell is empty */
	public static string $tab_equals = '@'; 	/* wrap injected value: @ */
	public static $tab_infix = array();			/* (o,n,) infix operator callbacks */

	/*
	 *	Table environment
	*/
	private static $tables = array();			/* all tables recorded */
	private static $table_meta = array();		/* all table meta data */
	private static $this_table = array();		/* current working table */
    private static bool $recur = false;			/* should re-sweep table */
	
	
/****************************************************************************/
	
	
	/*
	 *	Standard Configuration...
	*/
	public static function std_config()
	{
		Principal::$tx_style = array(
			'*',	'*',	'b',	'',	true,
			'/',	'/',	'i',	'',	true,
			'_',	'_',	'u',	'',	true,
			'~',	'~',	's',	'',	true,
			'"',	'"',	'q',	'',	true,
			'^',	'^',	'em',	'',	true,
			'|',	'|',	'tt',	'',	true,
			'=',	'=',	'strong',	'',	true 
		);
	
		Principal::$fr_match = array(
			'--',	'&#8212;',
			'(c)',	'&copy;',
			'(r)',	'&reg;'
		);
		
		Principal::$blk_function = array(
			'table', 'Principal::table'
		);
	
    	Principal::$blk_regex = array(
    		'%^\-+$%', '<hr@/>'
    	);
	
		Principal::$tab_equals = '<b>@</b>';
	
	}
	
	
/****************************************************************************/
	
	
	public static function add(string $arr, array $data)
	{
		// add definitions to find / replace arrays:
		if(property_exists('Principal','fr_'.$arr)){
			Principal::${'fr_'.$arr} = array_merge(Principal::${'fr_'.$arr}, $data);
			return;
		}
		
		// add definitions to block arrays:
		if(property_exists('Principal','blk_'.$arr)){
			Principal::${'blk_'.$arr} = array_merge(Principal::${'blk_'.$arr}, $data);
			return;
		}
	
	}
	
	public static function add_file(string $arr, string $path)
	{
		// open file if it exists or halt:
		if(!file_exists($path)){ return; }
		$file = file($path);
		
		// add definitions to find / replace arrays:
		if(property_exists('Principal','fr_'.$arr)){
			for($i=0;$i<count($file);$i++){
				$line = explode("\t",rtrim($file[$i]));
				for($j=0;$j<count($line);$j++){
					 array_push(Principal::${'fr_'.$arr},$line[$j]);
				}
			}
			return;
		}
	}
	
	
/****************************************************************************/
	
	
	/*
	    array_push($PR_INLINE, '::', 'PRimage' );

    // load inline images in paragraphs				<img/>
    function PRimage($u){
    	if(substr($u,0,2)=='./'){ $u=substr($u,2); }
    	elseif(substr($u,0,4)=='www.'){ $u='http://'.$u; }
	return "<img src=\"$u\" />";
    }

	*/


/****************************************************************************/
/***  ###### ##   # ######  ####_ ##   # ###### ######  *********************/
/***  ##___   ###*  ##___  ##   ` ##   #   ##   ##___   *********************/
/***  ##^^^   _###  ##^^^  ##   _ ##   #   ##   ##^^^   *********************/
/***  ###### #   ## ######  ####` `####`   ##   ######  *********************/
/****************************************************************************/


	private static string $out = '';	/* complete output HTML */
	
	private static string $block;		/* current input block of text */
	private static $line = array();		/* current block broken into lines */
	private static int $linect;			/* how many lines? */
	private static string $attr = '';	/* attribute line on block */
	private static string $after = '';	/* content after current block */
	private static int $divs_open = 0;	/* number of divs currently open */


	// run principal on a string of input...
    public static function run(string $src) : string
    {
    	Principal::$out = "";			/* clear output from previous */
    	$file=Principal::blocks($src);	/* divide file into blocks */
        $ln=0; 							/* current line in file */

        // begin processing blocks...	
		for($ln=0;$ln<count($file);$ln++){
			Principal::$block=$file[$ln];
			$c=substr(Principal::$block,0,1);

			// Beginning with a < means print raw html:
		    if($c=="<"){ Principal::$out.=Principal::$block."\r\n\r\n"; continue; }
			// Contains only whitespace, ignore:
		    if(trim(Principal::$block," \r\n\t")==''){ continue; }
 
			// Fetch attribute line and process divs...
			if(Principal::$use_attrs){ Principal::attr_line(); }

			// Get lines and line count:
			Principal::$line=explode("\r\n",Principal::$block);
			Principal::$linect=count(Principal::$line);

			// check for hook functions:
			if(Principal::chk_hooks()){ continue; }

			// Check for empty body:
			if(count(Principal::$line)==1&&Principal::$line[0]==''){ continue; }

			// standard HTML identities:
			Principal::std_html();
			
			// add postfix block and line breaks:
			Principal::$out.=Principal::$after."\r\n\r\n";
			
		}
		return Principal::$out; 
	}


/***************************************************************************/


	// process attribute line before block...
	// also handles opening and closing of divs with ( )
	private static function attr_line()
	{
		Principal::$after='';
		Principal::$attr='';
		$divs_here=false;
		$th_attr='';
		$th_val='';
		$adv_to='';
		$c=substr(Principal::$block,0,1);
		$i=0;
		
	    if(Principal::ch('.#{()',$c)
	    && substr(Principal::$block,0,2)!='./'){
	    
        	// scan full attribute line...
        	for($i=0;$i<strlen(Principal::$block);$i++){
        		$ac=substr(Principal::$block,$i,1);
        		$th_val='';
        		if(substr(Principal::$block,$i,2)=="\r\n"){ $i+=2; break; }
        		if(Principal::ch("} \t",$ac)){ continue; }
        		
        		// processing divs?
				if(Principal::$build_divs){
        			// ending previously opened divs:
        			if($ac==')'){
        				Principal::$out.=" </div>\r\n";
        				Principal::$divs_open--;
        				$divs_here = true;
        				continue;
    				}
        			// create a new div with attributes and reset:
        			if($ac=='('){
        				Principal::$out.=' <div'.Principal::$attr.">\r\n";
        				Principal::$attr='';
						Principal::$divs_open++;
						$divs_here = true;
						continue;
					}
				}
				
				// apply a class, id or style to the next element:
				switch($ac){
        			case '.':
        				$th_attr='class';
        				$adv_to=" \t\r\n#|>\"'`{([";
        				break;
        			case '#':
        				$th_attr='id';
        				$adv_to=" \t\r\n#|>\"'`{([";
        				break;
        			case '{':
        				$th_attr='style';
        				$adv_to='}';
        				break;
        			// uncaught characters end attributes:
        			default:
        				$th_val=null;
        				break;
        		}
        		if($th_val===null){ break; }
        
        		// compile attribute value for later usage:
        		for($j=$i+1;$j<strlen(Principal::$block);$j++){
        			if(Principal::ch($adv_to,substr(Principal::$block,$j,1))){
        				$i=$j-1;
        				break;
        			}
					$th_val.=substr(Principal::$block,$j,1);
				}
				
				// additional processing of styles:
        		if($th_attr&&$th_val){
        			$th_val=trim($th_val);
        			if($th_attr=='style'){
        				if(Principal::$css_pre!=''){
							$th_val=call_user_func(Principal::$css_pre,$th_val);
						} 
					}
        			Principal::$attr.=" $th_attr=\"$th_val\"";
        			$th_val='';
        			$th_attr='';
    			}
        	}
        }
        
        // crop prefix line and close divs:
		if(Principal::$build_divs){
        	if($i>0){ 
        		Principal::$block = substr(Principal::$block,$i);
    		}
        	if($divs_here){
        		Principal::$out.="\r\n"; 
        		Principal::$block=trim(Principal::$block); 
	  			while(substr(Principal::$block,strlen(Principal::$block)-1,1)==')'){
            		Principal::$block=trim(substr(
            			Principal::$block,0,strlen(Principal::$block)-1));
	    			Principal::$after.="\r\n</div>";
	    			Principal::$divs_open--;
	    		}
	    	}
		}
	}


/****************************************************************************/


	// check for different types of hooks, return if found...
	private static function chk_hooks() : bool
	{
		// Hooks that are entire blocks:
		for($i=count(Principal::$blk_entire)-2;$i>-1;$i-=2){
			if(Principal::$block==Principal::$blk_entire[$i]){ 
				Principal::$out.=str_replace('@',
					Principal::$attr,Principal::$blk_entire[$i+1])."\r\n\r\n";
				return true;
			} 
		}

		// Hooks that are entire regex matches:
		for($i=count(Principal::$blk_regex)-2;$i>-1;$i-=2){
			if(preg_match(Principal::$blk_regex[$i],Principal::$block)){ 
				Principal::$out.=str_replace('@',
					Principal::$attr,Principal::$blk_regex[$i+1])."\r\n\r\n"; 
				return true;
			} 
		}

		// Hook functions: that start a block:
		for($i=count(Principal::$blk_function)-2;$i>-1;$i-=2){
			$open=substr(Principal::$block,0,strlen(Principal::$blk_function[$i])+1);
			if($open==Principal::$blk_function[$i].':'){
				$block=call_user_func(Principal::$blk_function[$i+1],
			    	substr(Principal::$block,strlen($open)),Principal::$attr);
				Principal::$out.=$block."\r\n\r\n";
				return true;
			} 
		}

		// Hook characters that start a block:
		for($i=count(Principal::$blk_initial)-3;$i>-1;$i-=3){
			$y=strlen(Principal::$blk_initial[$i]); 
			$v=substr(Principal::$block,0,$y);
			if($v==Principal::$blk_initial[$i]){ 
				Principal::$block=substr(Principal::$block,$y);
				$m=Principal::$blk_initial[$i+1];
				$x=strpos($m,'.'); 
				if($x!==false){
					$tg=substr($m,0,$x); 
					$m=$tg.' class="'.substr($m,$x+1).'"';
				} else {
					$x=strpos($m,' ');
				  	if($x===false){ $tg=$m; } 
				  	else { $tg=substr($m,0,$x); } 
			  	}
				if(Principal::$blk_initial[$i+2]){ 
					Principal::$block=Principal::$text(trim(Principal::$block)); 
				} else { 
					Principal::$block=Principal::$ents(trim(Principal::$block)); 
				}
				Principal::$out.="<$m".Principal::$attr.'>'.
					Principal::$block."</$tg>\r\n\r\n";
				return true;
			} 
		}
		return false;
	}


/****************************************************************************/


	// standard HTML tags produces by principal...
	private static function std_html()
	{
		$c=substr(Principal::$block,0,1);
		$block = '';
	
    	// Some type of header:  							<h1...>
    	if($c=='>'){ 
    		$wh=1; 
    		for($i=1;$i<strlen(Principal::$block);$i++){ 
          		if(substr(Principal::$block,$i,1)=='>'){ $wh++; }
	  			elseif(substr(Principal::$block,$i,1)==' '){ continue; }
          		else { break; } 
      		}
      		$block=Principal::text(substr(Principal::$block,$i));
        	Principal::$out.="<h$wh".Principal::$attr.">$block</h$wh>";
        	return;
    	}

    	// Unordered or definition list:  					<ul>  <dl>
        if(substr(Principal::$block,0,2)=='- '){
    		$tg='ul'; 
    		for($i=1;$i<count(Principal::$line);$i++){ 
    			if(substr(Principal::$line[$i],0,2)!='- '){ 
    				$tg='dl'; 
    				break;
    			} 
    		} 
    		// proceed with tag:
    		$block.="<$tg".Principal::$attr.">\r\n";
    		// definition lists:
    		if($tg=='dl'){ 
    			for($i=0;$i<count(Principal::$line);$i++){ 
    				if(substr(Principal::$line[$i],0,2)=='- '){ 
    					Principal::$line[$i]=substr(Principal::$line[$i],2);
    					$block.='<dt>'.
    						Principal::text(Principal::$line[$i])."</dt>\r\n"; 
					} else {
						$block.='<dd>'.
							Principal::text(Principal::$line[$i])."</dd>\r\n"; 
					}
				}
			// unordered bullet lists:
			} else { 
				for($i=0;$i<count(Principal::$line);$i++){ 
    				$block.='<li>'.Principal::text(
    					substr(Principal::$line[$i],2))."</li>\r\n"; 
				}
    		}
    		// close tag:
    		Principal::$out.="$block</$tg>";
        	return;
    	}
        
    	// Ordered list:  									<ol>
        if(Principal::ch('0123456789',$c)
        && substr(Principal::$block,1,1)=='.'){ 
        	$bk.='<ol'.Principal::$attr.'>';
        	for($i=0;$i<count(Principal::$line);$i++){ 
          		Principal::$line[$i] = ltrim(ltrim(
          			substr(Principal::$line[$i],2),"0123456789.")," \t");
    	  		$block.='<li>'.Principal::text(Principal::$line[$i]).'</li>';
        	} 
        	Principal::$out.="$block</ol>";
        	return;
        }

    	// Preformatted text tag:  							<pre>
        if($c==' '){ 
        	Principal::$block=trim(Principal::$block," \t\r\n");
        	Principal::$out.='<pre'.Principal::$attr.'>'.
        		Principal::ents(Principal::$block).'</pre>';
        	return;
    	}

    	// Blockquote tag:  								<blockquote>
        if($c=='"'){ 
        	Principal::$block=trim(Principal::$block,"\" \t\r\n"); 
        	Principal::$out.='<blockquote'.Principal::$attr.'>'.
        		Principal::text(Principal::$block).'</blockquote>'; 
        	return;
        }
        
    	// Ordinary Paragraph:  							<p>
        Principal::$out.='<p'.Principal::$attr.'>'.Principal::text(Principal::$block).'</p>';

	}


/****************************************************************************/





/****************************************************************************/
/***  ###### ###### ##   # ######     ###### ##   # ##,  #  ####_  **********/
/***    ##   ##___   ###*    ##       ##___  ##   # ###, # ##   `  **********/
/***    ##   ##^^^   _###    ##       ##^^^  ##   # ## #,# ##   _  **********/
/***    ##   ###### #   ##   ##       ##     `####` ##  ##  ####`  **********/
/****************************************************************************/


    // second string appears in first at position...
    private static function at(string $s, int $p, string $m) : bool
    { 
    	if(substr($s,$p,strlen($m))==$m){ return true; } 
        return false; 
    }
    
    
/****************************************************************************/


    // second string appears in first string...
    private static function in(string $s, string $m) : bool
    { 
    	for($i=0;$i<strlen($s);$i++){
			$c=substr($s,$i,strlen($m)); 
			if($c==$m){ return true; } 
		} 
		return false; 
	}
    
    
/****************************************************************************/


    // returns second string with only chars from first string...
    private static function oc(string $w, string $s) : string
    { 
    	$o=''; 
    	for($i=0;$i<strlen($s);$i++){ 
			$c=substr($s,$i,1); 
			if(Principal::ch($w,$c)){ $o.=$c; } 
		} 
		return $o; 
	}
    
    
/****************************************************************************/


    // second string only contains characters from first string...
    private static function ch(string $c, string $s) : bool
    { 
    	if($s==''){ return false; } 
		for($i=0;$i<strlen($s);$i++){
			if(!Principal::in($c,substr($s,$i,1))){ return false; } 
		} 
		return true; 
	}
    
    
/****************************************************************************/


    // normalize line breaks to \r\n...
    private static function norm(string $s) : string
    { 
    	$s=trim($s," \r\n\t"); 
    	$o='';
		for($i=0;$i<strlen($s);$i++){ 
			$c=substr($s,$i,1);
	  		if($c=="\r"){ 
	  			if(substr($s,$i+1,1)=="\n"){ $i++; } 
	  			$o.="\r\n"; 
	  		} elseif($c=="\n"){ 
	  			$o.="\r\n"; 
	  		} else { 
	  			$o.=$c; 
	  		} 
	  	} 
	  	return $o;
	}
    
    
/****************************************************************************/


    // Convert basic HTML entities < & >...
    private static function ents(string $e) : string
    { 
    	$o=''; 
		for($i=0;$i<strlen($e);$i++){ 
			$c=substr($e,$i,1);
        	if($c=='<'){ $c='&lt;'; } 
        	elseif($c=='>'){ $c='&gt;'; }
        	elseif($c=='&'){ $c='&amp;'; } 
        	$o.=$c; 
        } 
        return $o; 
    }
    
    
/****************************************************************************/

    	
    // Convert unconditional pair matches in a string...
    private static function pair(array $a, string $s) : string
    { 
    	$o='';
		for($i=0;$i<strlen($s);$i++){ 
			for($j=1;$j<count($a);$j+=2){
    	  		if(Principal::at($s,$i,$a[$j-1])){
    	  			$o.=$a[$j]; 
    	  			$i+=strlen($a[$j-1]); 
    	  			break; 
    	  		}
    		} 
    		$o.=substr($s,$i,1); 
    	} 
    	return $o; 
    }
    
    
/****************************************************************************/


    // break into paragraph blocks...
    private static function blocks(string $s) : array
    { 
    	$s=Principal::norm($s); 
    	$o=array(''); 
    	$x=0;
		for($i=0;$i<strlen($s);$i++){ 
	  		if(substr($s,$i,4)=="\r\n\r\n"){
	  			array_push($o,''); 
	  			$x++; 
	  			$i+=3;
	  			while(substr($s,$i+1,2)=="\r\n"){ $i+=2; }
			} else { 
				$o[$x].=substr($s,$i,1); 
			} 
		} 
		return $o; 
	}


/****************************************************************************/





/****************************************************************************/
/***  ###### ###### ##   # ######  ******************************************/
/***    ##   ##___   ###*    ##    ******************************************/
/***    ##   ##^^^   _###    ##    ******************************************/
/***    ##   ###### #   ##   ##    ******************************************/
/****************************************************************************/


    // Process all formatted text within normal text elements...
    public static function text(string $e, bool $lb=true) : string
    {
    	// upper and lower case letters
        $upr='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lwr='abcdefghijklmnopqrstuvwxyz';
	
		// iterators	attributes	previous char 	finished output
		$i=0; 	$j=0; 	$a='';		$b=''; 			$o='';
	
		// iterate source...
    	for($i=0;$i<strlen($e);$i++){ 	
    	
    	// current char				next char
    	$c=substr($e,$i,1); 		$d=substr($e,$i+1,1); 
    	
    	// tag name 	response or term variables 	url or other term
    	$t=''; 			$r=''; 		$n=''; 			$u='';
    		
    	// .............................................
    	
    	//	TYPOGRAPHICAL LITERAL MATCHES:

		for($j=1;$j<count(Principal::$fr_match);$j+=2){
		  	if(Principal::at($e,$i,Principal::$fr_match[$j-1])){
	    		$o.=Principal::$fr_match[$j];
    			$i+=strlen(Principal::$fr_match[$j-1])-1;
  				continue 2;
			} 
		}
 		
    	// .............................................
    	
    	//	ELEMENT STYLING FROM DEFINITIONS:
    		 
    	// auto-generate HTML text tags and convert entities:
    	for($j=count(Principal::$tx_style)-5;$j>-1;$j-=5){
    	  	if(Principal::at($e,$i,Principal::$tx_style[$j])){
				// check if style requires leading space:
			    if(Principal::$tx_style[$j+4]&&trim($b,"- \t\r\n")!=''){ break; }
				// get opener and next character after:
				$k=$i+strlen(Principal::$tx_style[$j+1]); 
				$d=substr($e,$k,1);
				// cannot be followed by a space:
				if($d==' '||$d=="\t"||$d==''){ break; }
		  	  	$u = Principal::$tx_style[$j+1]; // closer
				if($d=='('){ $k++; $u=')'; }
		      	$t = Principal::$tx_style[$j+2]; // tag name
		      	$a = Principal::$tx_style[$j+3]; // attributes
    		} 
    	}
    	// GREEDY: get all to next match. DOES NOT NEST...
    	if($t){ 
    		$o.="<$t$a>"; 
    		$n='';
	  		// get up to defined ender:
	  		for($i=$k;$i<strlen($e);$i++){ 
	  			$c=substr($e,$i,1);
	    	  	if(substr($e,$i,strlen($u))==$u){ break; } 
	    	  	$n.=$c; 
    	  	}
  			$n=Principal::pair(Principal::$fr_match,Principal::ents($n)); 
  			$o.="$n</$t>"; 
			continue; 
		}
    		
    	// .............................................
    	
    	//	ESCAPE HTML ENTITIES AND LINE BREAKS:
    	
    	// convert < & > entities in tags and normally			&ent;
    	elseif($c=='<'){ $c='&lt;'; $i-=3; } 
    	elseif($c=='>'){ $c='&gt;'; $i-=3; }
    	elseif($c=='&'){ $c='&amp;'; $i-=4; }
    	
    	// when converting line breaks:							<br/>
    	elseif($lb&&$c.$d=="\r\n"){ $c="<br/>\r\n";  $i-=5; }
    	elseif($lb&&$c=="\r"){ $c="<br/>\r\n"; $i-=6; }
    	elseif($lb&&$c=="\n"){ $c="<br/>\r\n"; $i-=6; }
    		
    	// .............................................
    	
    	//	ALL TAGS PRODUCED AUTOMATICALLY FROM PAIRS:

		elseif(!$b||!Principal::ch($upr.$lwr,$b)){
    	
			// check for all uppercase ACROs:					<acronym>
			if(Principal::ch($upr,$c)){
				for($j=count(Principal::$fr_acro)-2;$j>-1;$j-=2){
			  		$r = substr($e,$i+strlen(Principal::$fr_acro[$j]),1);
			  		if(Principal::at($e,$i,Principal::$fr_acro[$j])
			  		&&(!$r||!Principal::ch($upr.$lwr,$r))){
			    		$n = Principal::$fr_acro[$j+1]; 
			    		$c = Principal::$fr_acro[$j]; 
			    		$t = 'ACRO'; 
			    		$a = ' title="'.$n.'"'; 
			    		break; 
					} 
				} 
			}
			
			// check for abbreviation:							<abbr>
			elseif(Principal::ch($upr.$lwr,$c)){ 
				for($j=count(Principal::$fr_abbr)-2;$j>-1;$j-=2){
			  		if(Principal::at($e,$i,Principal::$fr_abbr[$j])
			  		&&!Principal::ch($upr.$lwr,substr($e,$i+
			  		strlen(Principal::$fr_abbr[$j]),1))){
			    		$n = Principal::$fr_abbr[$j+1]; 
			    		$c = Principal::$fr_abbr[$j]; 
			    		$t = 'abbr'; 
			    		$a = ' title="'.$n.'"'; 
			    		break; 
					} 
				} 
			}

			// TODO: user defined term matches:
			
			
			// inject previous tag produced by pair match:
			if($n){ $o.="<$t$a>$c</$t>"; $i+=strlen($c)-1; continue; } 
    	}
    		
    	// .............................................
    	
    	//	LINKS INCLUDED IN THE PARAGRAPH:
    	
    	// recognize syntax for links: (external links in new tab)
    	if(substr($e,$i,7)=='http://'){ $t='http://'; $r=' target="_BLANK"'; }
    	if(substr($e,$i,8)=='https://'){ $t='https://'; $r=' target="_BLANK"'; }
    	if(substr($e,$i,4)=='www.'){ $t='www.'; $r=' target="_BLANK"'; }
    	if(substr($e,$i,2)=='.#'){ $t='.#'; }
    	if(substr($e,$i,2)=='./'){ $t='./'; }
    	if(substr($e,$i,2)=='.//'){ $t='.//'; }
    	
    	// fetch URL associated with link:
    	if($t){ 
    		$u=''; 
    		for($j=$i+strlen($t);$j<strlen($e);$j++){ 
				$n=substr($e,$j,1); 
				if(Principal::ch(" \t\r\n(",$n)){ break; } 
				else { $u.=$n; } 
			} 
			$i=$j; 
			while(Principal::ch(" \t",substr($e,$i,1))){ $i++; }
    		switch($t){ 
				case 'www.': $t='http://www.'; break;
				case '.#': $t='#'; break;
				case './': $t='./'; break;
				case './/': $t='/'; break;
			} 
			$u=$t.$u;
    		// fetch link content and present:
    		if(substr($e,$i,1)=='('){ 
    			$t=''; 
    			for($j=$i+1;$j<strlen($e);$j++){ 
    				$n=substr($e,$j,1); 
    				if($n==')'){ break; } 
    				$t.=$n; 
				} 
			} else { 
				$t=$u; 
				$j--; 
			} 
			$o.="<a href=\"$u\"$r>$t</a>"; 
			$i=$j; 
			continue; 
		}
    		
    	// .............................................
    	
    	//	INLINE OBJECT HANDLES:
	
    	for($j=count(Principal::$blk_inline)-2;$j>-1;$j-=2){
    		$r=substr($e,$i,strlen(Principal::$blk_inline[$j]));
    		if($r==Principal::$blk_inline[$j]){ 
    			$u='';
	  			for($k=$i+strlen($r);$k<strlen($e);$k++){ 
	  				$n=substr($e,$k,1); 
	  				if(Principal::ch(" \t\r\n",$n)){ break; } 
	  				else { $u.=$n; } 
  				}
    	  		$o.=call_user_func(Principal::$blk_inline[$j+1],$u);
    	  		$i=$k-1; 
    	  		continue 2; 
	  		} 
  		}
    	
    	// .............................................
    		
    	// 	WRAP UP AND CONVERT PAIR MATCHES:
    	
    	$b=substr($c,-1); $o.=$c; $i+=strlen($c)-1;
    	}
    	return rtrim($o); 
    }
	
	
/****************************************************************************/
    




/****************************************************************************/
/***  ###### .####, #####, ##     ###### .####_  ****************************/
/***    ##   ##   # ##___# ##     ##___  ##__ ^  ****************************/
/***    ##   ###### ##^^^x ##     ##^^^  __^^##  ****************************/
/***    ##   ##   # #####* ###### ###### *####   ****************************/
/****************************************************************************/


    // Process data table and spill to HTML... 					<table>
    public static function table(string $s, string $a) : string
    {
    	$b="<table$a> ";
		// check for empty top left cell:
		$s=ltrim($s," "); 
		if(substr($s,0,1)=="\t"){ 
			$s=Principal::$tab_empty.$s; 
		}
		// check for empty top row:
		$i=0; 
		if(substr(trim($s," \t"),0,2)=="\r\n"){ 
			$s=Principal::$tab_empty.$s; 
			$i=1;
		}
		// process table then construct from array...
		$z = Principal::tabs($s);
        for(;$i<count($z);$i++){
			if($i==0){ $b.="<thead>\r\n"; }
			if($i==1){ $b.="<tbody>\r\n"; }
			// each row...
		    $b.='<tr> ';
		    for($j=0;$j<count($z[$i]);$j++){
				// determine rowspan, colspan:
				if($z[$i][$j]==Principal::$tab_colspan){ continue; }
				if($z[$i][$j]==Principal::$tab_rowspan){ continue; }
				$cs=1; $c='';
				$rs=1; $r='';
				for($k=$j+1;$k<count($z[$i]);$k++){
					if($z[$i][$k]==Principal::$tab_colspan){ $cs++; }
					else { break; }
				}
				for($k=$i+1;$k<count($z);$k++){
					if($j>=count($z[$k])){ continue; }
					if($z[$k][$j]==Principal::$tab_rowspan){ $rs++; }
					else { break; }
				}
				if($rs>1){ $r=' rowspan="'.$rs.'"'; }
				if($cs>1){ $c=' colspan="'.$cs.'"'; }
				// draw empty cell or contents:
				$t='td';
				if($i==0||$j==0){ $t='th'; }
				if($z[$i][$j]==Principal::$tab_empty){
					$b.="<$t$r$c>&nbsp;</$t> ";
				}
				elseif(substr($z[$i][$j],0,1)==' '){
					$b.="<$t$r$c><b>".substr($z[$i][$j],1)."</b></$t> "; 
				} else { 	
					$b.="<$t$r$c>".Principal::pair(
						Principal::$fr_match,
						Principal::ents($z[$i][$j])
					)."</$t> ";
				}
	    	}
	    	$b.="</tr>\r\n";
			if($i==0){ $b.="</thead> "; }
			if($i==count($z)-1){ $b.='</tbody> '; }
		}
		return $b.'</table>';
    }


/****************************************************************************/
    

    // break into table of lines and tabs...
    private static function data(string $s) : array
    { 
    	$l=explode("\r\n",Principal::norm($s)); 
    	$o=array();
		for($i=0;$i<count($l);$i++){
        	if(!array_key_exists($i,$o)){ $o[$i] = array(); }
        	$n=''; 
        	for($j=0;$j<strlen($l[$i]);$j++){ 
        		$c=substr($l[$i],$j,1); 
        		if($c=="\t"){ 
        			if($n!==''){ array_push($o[$i],$n); $n=''; } 
				} else { 
					$n.=$c; 
				} 
			} 
			array_push($o[$i],$n); 
		} 
		return $o; 
	}


/****************************************************************************/
    

    // Divide block by lines and columns, perform calculations...
    public static function tabs(string $src) : array
    { 
    	$a=Principal::data($src);

        // with the complete table in $a, groom for calculations:
        Principal::$recur=true;
        while(Principal::$recur){
        	Principal::$recur=false; 
        	for($i=0;$i<count($a);$i++){
        		for($j=0;$j<count($a[$i]);$j++){
        			$x=null; 
        			$y='';
        			// evaluate = expressions...
        			if(substr($a[$i][$j],0,1)=='='){
        				$a[$i][$j] = Principal::eq_expr($a,$i,$j);
					}
				}
			}
		}
		array_push(Principal::$tables,$a);
    	return $a; 
    }


/****************************************************************************/


	// resolve the value of cells beginning with equals...
	private static function eq_expr(array $a, int $i, int $j) : string
	{
		$cell=substr($a[$i][$j],1);
		$c=substr($cell,0,1);

		// evaluate with extra recursivity:
		if($c=='='){ 
			Principal::$recur=true;
			return $cell;
		}

		// check for output formats:
		if($c=='$'){ 
			$y='$';
			$cell=substr($cell,1); 
			$c=substr($cell,0,1); 
		}

		// tally < left as first operand:
		elseif($c=='^'){ 
			$x=0;
			for($k=$i-1;$k>0;$k--){
				if($a[$k][$j]==Principal::$tab_empty){ continue; }
				if($a[$k][$j]==Principal::$tab_colspan){ continue; }
				if($a[$k][$j]==Principal::$tab_rowspan){ continue; }
				if(substr($a[$k][$j],0,1)=='='){ continue; }
				$x+=floatval(Principal::oc('-'.'0123456789.',$a[$k][$j]));
			} 
			$cell=$x; 
		}

		// tally < left as first operand:
		elseif($c=='<'){ 
			$x=0;
			for($k=$j-1;$k>0;$k--){
				if($a[$i][$k]==Principal::$tab_empty){ continue; }
				if($a[$i][$k]==Principal::$tab_colspan){ continue; }
				if($a[$i][$k]==Principal::$tab_rowspan){ continue; }
				if(substr($a[$i][$k],0,1)=='='){ continue; }
				$x+=floatval(Principal::oc('-'.'0123456789.',$a[$i][$k]));
			}
			$cell=$x; 
		}

		// otherwise execute expression:
		elseif($x===null){ $x = Principal::expr($a,$i,$j); }

		if($x!==null){
			// select output format...
			switch($y){
			
				// money format:
				case '$': 
					$n=' $'; 
					if(substr($x,0,1)=='-'){ $n=' -$'; $x=substr($x,1); }
					$cell = $n.money_format('%i',floatval($x)); 
					break;

				// no format used:
				default: $cell = ' '.$x; 
			}
		}
		return $cell;
	}


/****************************************************************************/


    // resolve expressions with {} and [] cell references...
    public static function expr(array $a, int $r, int $c, string $s=null) : string
    { 
		$opr='`~!@#$%^&*_+=<>?/\\|\'";:';
		$x=array(); 
		$n='';
		if($s===null){ $s=$a[$r][$c]; }
		$i=0;
		if(substr($s,0,1)=='='){ $i++; }

		// build array of all literal terms for calulation...
        for(;$i<strlen($s);$i++){ 
        	$c=substr($s,$i,1);
          
	      	// compile floating point numbers:
		  	if(Principal::in('0123456789.',$c)){
				if($n!=''){ array_push($x,$n); } 
				$n=$c;
				for($i++;$i<strlen($s);$i++){ 
					$c=substr($s,$i,1);
					if(!Principal::in('0123456789.',$c)){ $i--; break; } 
					$n.=$c; 
				}
				if($n!=''){ array_push($x,$n); $n=''; }	
				array_push($x,floatval($n)); 
				continue; 
			}
		  
		  	// get curly bracket reference:
		  	if($c=='{'){
				if($n!=''){ array_push($x,$n); $n=''; }	
				for($i++;$i<strlen($s);$i++){ 
					$c=substr($s,$i,1);
					if($c=='}'){ break; } 
					$n.=$c; 
				}
				array_push($x,Principal::curly($a,$r,$n)); 
				continue; 
			}
		  
		  	// get square bracket reference:
		  	if($c=='['){ 
				if($n!=''){ array_push($x,$n); $n=''; }
				for($i++;$i<strlen($s);$i++){ 
					$c=substr($s,$i,1);
					if($c==']'){ break; } 
					$n.=$c; 
				}	
				array_push($x,Principal::square($a,$r,$n)); 
				continue; 
			}

		  	// solitary operators:
		    if(Principal::in('(,-)',$c)){ 
				if($n!=''){ array_push($x,$n); $n=''; }		
				array_push($x,$c); 
				continue; 
			}

		  	// compile operators or identifiers:
	      	if($n!='' && Principal::in($opr,$c)
	      	&& !Principal::in($opr,substr($n,0,1))){ 
				array_push($x,$n); $n=''; 
			}
		  	$n.=$c;
		  	
		}
		// calculate array of operands to single value: 
		return calc($x);
    }


/****************************************************************************/


    // get the value of a curly bracket reference...
    public static function curly($a,$r,$n)
    { 
    	$d=explode(',',$n);
		// get single reference...
		if(strpos(':',$n)===false){
			switch(count($d)){
				case 1: if($d[0]<0){ $d[0]+=count($a[$r]); }
						return $a[$r][$d[0]];
				case 2: if($d[0]<0){ $d[0]+=count($a); }
						if($d[1]<0){ $d[1]+=count($a[$d[0]]); }
						return $a[$d[0]][$d[1]];
				case 3: $t=Principal::$tables;
						if($d[0]<0){ $d[0]+=count($t); }
						if($d[1]<0){ $d[1]+=count($t[$d[0]]); }
						if($d[2]<0){ $d[2]+=count($t[$d[0]][$d[1]]); }
						return $t[$d[0]][$d[1]][$d[2]];
				default: return $n; 
			} 
		}
		// get array from range...
		/*
		for($i=0;$i<count($d);$i++){ $d[$i]=explode(',',$d[$i]);
		  for($j=0;$j<count($d[$i]);$j++){ $d[$i][$j]=trim($d[$i][$j]); 
		} } 
		*/
    }


/****************************************************************************/


    // get the value of a square bracket reference...
    public static function square($a,$r,$n)
    {

    }


/****************************************************************************/


    // Provided with array of floats and operator sequences...
    public static function calc(array $a) : float
    { 
		return (float) $a[0];
    }


/****************************************************************************/


}

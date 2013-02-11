<?php
/**
 * Implode an array with the key and value pair giving
 * a glue, a separator between pairs and the array
 * to implode.
 * @param string $glue The glue between key and value
 * @param string $separator Separator between pairs
 * @param array $array The array to implode
 * @return string The imploded array
 */
function array_implode( $glue, $separator, $array ) {
    if ( ! is_array( $array ) ) return $array;
    $string = array();
    foreach ( $array as $key => $val ) {
        if ( is_array( $val ) )
            $val = implode( ',', $val );
        $string[] = "{$key}{$glue}{$val}";
        
    }
    return implode( $separator, $string );
    
}

//http://stackoverflow.com/questions/1019076/how-to-search-by-key-value-in-a-multidimensional-array-in-php
function array_msearch($array, $key, $value)
{
    $results = array();

    if (is_array($array))
    {
        if (isset($array[$key]) && $array[$key] == $value)
            $results[] = $array;

        foreach ($array as $subarray)
            $results = array_merge($results, search($subarray, $key, $value));
    }

    return $results;
}


//return array with only the selected fields of the array set
//array or beans?->export()
function array_select($array, $selection) {
    $selected = array();
    foreach ( $array as $row ) {
        if ( $row instanceof RedBean_OODBBean  )
            $row = $row->export();
        
        $selected[] = array_intersect_key($row, array_flip($selection));;
    }
    return $selected;
}

function agent_random() {
    //http://www.zytrax.com/tech/web/browser_ids.htm
    $agents = array(
        'Mozilla/5.0 (Windows NT 6.1; rv:11.0) Gecko/20100101 Firefox/11.0',
        'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:10.0) Gecko/20100101 Firefox/10.0',
        'Mozilla/5.0 (Ubuntu; X11; Linux x86_64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1',
        'Mozilla/5.0 (Windows NT 5.1; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
        'Mozilla/5.0 (Windows NT 5.1; rv:8.0) Gecko/20100101 Firefox/8.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.79 Safari/535.11',
        'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.0 Safari/535.11',
        'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.77 Safari/535.7',
        'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.2 (KHTML, like Gecko) Chrome/15.0.874.121 Safari/535.2',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_3) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/13.0.782.220 Safari/535.1',
        'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.57 Safari/534.24',
        'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
        'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)',
        'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; GTB6.4; .NET CLR 1.1.4322; FDM; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30; .NET CLR 3.0.4506.2152; .NET CLR 3.5.30729)',
        'Opera/9.80 (Windows NT 6.1; U; en) Presto/2.10.229 Version/11.61',
        'Opera/9.80 (Macintosh; Intel Mac OS X; U; en) Presto/2.6.30 Version/10.61',
        'Opera/9.80 (Windows NT 5.1; U; en) Presto/2.5.22 Version/10.50',
        'Opera/9.80 (X11; Linux i686; U; nl) Presto/2.2.15 Version/10.00',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; en-us) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_4; en-us) AppleWebKit/533.17.8 (KHTML, like Gecko) Version/5.0.1 Safari/533.17.8',
        'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/531.21.8 (KHTML, like Gecko) Version/4.0.4 Safari/531.21.10',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_2; en-us) AppleWebKit/531.21.8 (KHTML, like Gecko) Version/4.0.4 Safari/531.21.10',
        'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.19 (KHTML, like Gecko) Version/3.1.2 Safari/525.21'
    );

    return  $agents[
                array_rand($agents)
            ];

}

//know issues:
//- precisa melhorar como trata os caracteres especiais dentro de strings ",;[]{}"
//- comments strip is not 100%, inside string
//* testar com retorno da scrape de nomes.json -> feminino
function json_clean( $jsonstr, $function=false ) {

    
    //take care of encoding
    $jsonstr = mb_convert_encoding($jsonstr, 'UTF-8', 'ASCII,UTF-8,ISO-8859-1');
    //Remove UTF-8 BOM if present, json_decode() does not like it.
    if(substr($jsonstr, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) $jsonstr = substr($jsonstr, 3);

    //evaluate escaped string operators
    $jsonstr = str_replace('\n', "\n", $jsonstr);
    
    $cleanstr = '';
    //clean-up unformatted json
    //todo: testar se flag //m da mesmo resultado que o foreach
    foreach ( preg_split('/\n/', $jsonstr) as $line ) {
        //comments - single line, unique
        $line = preg_replace('/^\s*\/\/.*/', '',$line);
        //space trim
        $line = preg_replace('/(^\s*|\s*$)/', '', $line);

        //space after :
        $line = preg_replace('/:\s+([\'\"\[\{\w])/', ':$1', $line);

        //escape double-quotes when they are inside single quote
        $line = preg_replace_callback('/(\'.*?)"(.*?\')/', function($item) {
            return preg_replace('/"/', '\"', $item[0]);
        }, $line);

        //incapsulate properties into quotes
        $line = preg_replace('/(^|,|\{)+\s*(\w+):/', '$1"$2":', $line);
        //incapsulate properties into quotes - updated to better match the end
        //$line = preg_replace('/(^|\s|,|\{)+(\w+)(:[\w\s\{\[\'"])/', '$1"$2"$3', $line); 

        //empty vars quotes
        $line = preg_replace("/''/", '""', $line); 
        $cleanstr .= $line;
    }
    //trailing commas/spaces (end of object) -- think on take it up to foreach
    $cleanstr = preg_replace('/,\s*([\}\]])/', '$1', $cleanstr);

    //incapsulate values into double quotes - nao usar escape dentro das strings!!
    $cleanstr = preg_replace("/([:,\[\{])\s*'([^']+)\'/", '$1"$2"$3', $cleanstr);
    
    //$content = preg_replace('@(/\*.*?\*/)@se', "remove_non_linebreaks('\\1',$step)", $content); //comments not tested!

    //$jsonstr = preg_replace('%((//)).*%', '',$jsonstr); //comments
    
    //$jsonstr = preg_replace('/([\{\,])(\w+)\s*:/', '$1"$2":', $jsonstr); //incapsulate props into quotes
    //$jsonstr = preg_replace('/([:,\{\[])\s*(\w+)\s*([:\{\]\}])/', '$1"$2"$3', $jsonstr); //incapsulate constants into quotes
    
    
    //$jsonstr = preg_replace('/([:,\{\[])\s*\'([^\']+)\'/', '$1"$2"$3', $jsonstr); //escape semicolons
    
    //$jsonstr = preg_replace('/([:,\{\[]\s*\"[^\"]*)\"(.*?"[,\}\]])/', '$1\'$2', $jsonstr); //replace values " for '
    
    //$jsonstr = preg_replace("/([\w\"'])[\s:]+'/", '$1:"', $jsonstr); //replace props first ' to " of vars
    //$jsonstr = preg_replace("/([\{\[,])[\s]*'/", '$1"', $jsonstr); //replace props first ' to " of vars
    //$jsonstr = preg_replace("/'\s*(,[\"\}\]])/", '"$1', $jsonstr); //replace props last ' to "
    //$jsonstr = preg_replace("/'([\}\]\w])/", '"$1', $jsonstr); //replace props last ' to "
    //$jsonstr = preg_replace("/\"'/", '""', $jsonstr); //replace props empty
    
    //$jsonstr = preg_replace("/(\"[\s,]*)'(.)/", '$1"$2', $jsonstr); //replace ' to "*/
        
    
    return $cleanstr;
}

//Clean JSON notation with functions in it (convert them to strings)
function json_clean_functions( $json, $clean=false ) {
    $qjson = ''; //quoted function json
    $fncptr = false; //point out when a function is beeing wraped
    $bcopn = 0; //counter for open braces
    $bccls = 0; //counter for close braces
    
    //evaluate escaped string operators
    $json = str_replace('\n', "\n", $json);
    
    //step line-by-line looking for functions
    foreach ( preg_split('/\n/', $json) as $line ) {
        $qstr = '';
        //find unquoted function
        if ( !$fncptr && preg_match('/(.*[^"\'])(function.*)/', $line, $mt) > 0 ) {
            $qstr = "$mt[1]\"$mt[2]";
            $fncptr = true;
            
            $line = $qstr;
        }
        
        if ($fncptr === true) { //wrapping a function
            //line clean-up
            //comments - single line, unique
            $line = preg_replace('/^\s*\/\/.*/', '',$line);
            //space trim
            $line = preg_replace('/(^\s*|\s*$)/', '', $line);
            //$line = json_clean($line, true);
            //$line = addcslashes($line, '"');
            
            //find function's braces and sum them
            if ( ($mtc = preg_match_all('/\{/', $line, $mt)) > 0 )
                $bcopn += $mtc; //sum to counter found braces
            if ( ($mtc = preg_match_all('/\}/', $line, $mt)) > 0 )
                $bccls += $mtc; //sum to counter found braces
            
            //when open braces counter is the same as the close brace
            //  we can close the function quote
            if ( $bcopn > 0 && $bcopn == $bccls ) {
                if ( preg_match('/(^.*\})(.*)/', $line, $mt) > 0 ) {
                    //final quote the last close brace
                    $qstr = "$mt[1]\"$mt[2]\n";
                    
                    //reset vars to continue
                    $fncptr = false;
                    $bcopn = 0; $bccls = 0;
                }
            }
            if ( empty($qstr) ) {
                //escape quotes inside function 
                $line = addcslashes($line, '"');
            }
        }
        
        //echo $qstr; exit;
        $qjson .= (empty($qstr)) ? "$line\n" : $qstr ;
    };

    if ($clean)
        return json_clean($qjson);
    else
        return $qjson;
}

# A static class of mine, has more conversions in it, yet these two are the relevent ones.
final class Convert {
    # Convert a stdClass to an Array.
    # http://www.php.net/manual/es/language.types.object.php#102735
    static public function object_to_array(stdClass $Class){
        # Typecast to (array) automatically converts stdClass -> array.
        $Class = (array)$Class;
        
        # Iterate through the former properties looking for any stdClass properties.
        # Recursively apply (array).
        foreach($Class as $key => $value){
            if(is_object($value)&&get_class($value)==='stdClass'){
                $Class[$key] = self::object_to_array($value);
            }
        }
        return $Class;
    }
    
    # Convert an Array to stdClass.
    # http://www.php.net/manual/es/language.types.object.php#102735
    static public function array_to_object(array $array){
        # Iterate through our array looking for array values.
        # If found recurvisely call itself.
        foreach($array as $key => $value){
            if(is_array($value)){
                $array[$key] = self::array_to_object($value);
            }
        }
        
        # Typecast to (object) will automatically convert array -> stdClass
        return (object)$array;
    }
    
    static public function text_to_slug( $text ) { 
        //sanitize
        self::sane_text( $text );
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // trim
        $text = trim($text);
        // lowercase
        $text = strtolower($text);
        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
    
        if (empty($text))
            return 'n-a';
    
        return $text;
    }
    
    //sanitize text strings
    static function sane_text( $text ) {
        $table = array(
            'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
            'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
            'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
            'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
            'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
        );
        $text = strtr($text, $table);
        
        return $text;
    }
    
    //todo:verificar
    //http://www.php.net/manual/en/function.addcslashes.php#92495
    function javascript_escaped($str) { 
        return addcslashes($str,"\\\'\"&\n\r<>"); 
    } 
    
}

Class Sanitize {
    //lookup the docs ->
    //taken from wordpress
    function utf8_uri_encode( $utf8_string, $length = 0 ) {
        $unicode = '';
        $values = array();
        $num_octets = 1;
        $unicode_length = 0;
    
        $string_length = strlen( $utf8_string );
        for ($i = 0; $i < $string_length; $i++ ) {
    
            $value = ord( $utf8_string[ $i ] );
    
            if ( $value < 128 ) {
                if ( $length && ( $unicode_length >= $length ) )
                    break;
                $unicode .= chr($value);
                $unicode_length++;
            } else {
                if ( count( $values ) == 0 ) $num_octets = ( $value < 224 ) ? 2 : 3;
    
                $values[] = $value;
    
                if ( $length && ( $unicode_length + ($num_octets * 3) ) > $length )
                    break;
                if ( count( $values ) == $num_octets ) {
                    if ($num_octets == 3) {
                        $unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]) . '%' . dechex($values[2]);
                        $unicode_length += 9;
                    } else {
                        $unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]);
                        $unicode_length += 6;
                    }
    
                    $values = array();
                    $num_octets = 1;
                }
            }
        }
    
        return $unicode;
    }
    
    //taken from wordpress
    function seems_utf8($str) {
        $length = strlen($str);
        for ($i=0; $i < $length; $i++) {
            $c = ord($str[$i]);
            if ($c < 0x80) $n = 0; # 0bbbbbbb
            elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
            elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
            elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
            elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
            elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
            else return false; # Does not match any model
            for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
                if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
                    return false;
            }
        }
        return true;
    }
    
    //function sanitize_title_with_dashes taken from wordpress
    function sanitize($title) {
        $title = strip_tags($title);
        // Preserve escaped octets.
        $title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
        // Remove percent signs that are not part of an octet.
        $title = str_replace('%', '', $title);
        // Restore octets.
        $title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);
    
        if (seems_utf8($title)) {
            if (function_exists('mb_strtolower')) {
                $title = mb_strtolower($title, 'UTF-8');
            }
            $title = utf8_uri_encode($title, 200);
        }
    
        $title = strtolower($title);
        $title = preg_replace('/&.+?;/', '', $title); // kill entities
        $title = str_replace('.', '-', $title);
        $title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
        $title = preg_replace('/\s+/', '-', $title);
        $title = preg_replace('|-+|', '-', $title);
        $title = trim($title, '-');
    
        return $title;
    }
}

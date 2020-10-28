<?php
/*
    Часть контроллера который отвечает за поиск синонимов, 
    подсказок и подсветок из Yandex и Google.
*/
namespace App\Http\Controllers;

use App\Serv\Stopkeys;
use App\Http\Controllers\ParseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use vladkolodka\phpMorphy\Morphy;
use Carbon\Carbon;

class LsiController extends Controller
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function create($param)
    {
        $stop_keys = Stopkeys::getStopKeysPredlog();

        $result_keys = array();

        $result_keys['dopkeys'] = $this->createCorrectKeys(explode("\n", $param['keys']), $stop_keys);

        if(!isset($param['region'])){
            $param['region'] = 29;
        }
        $podsvetki_yandex = $this->createKeysXmlYandex($param['keys'], $param['region']);
        $new_arr = array();
        $top10_arr = $podsvetki_yandex;

        foreach ($top10_arr as $top10) {
            unset($top10['podsvetki']);
            unset($top10['top10']);
            $new_arr[] = $top10;
        }
        $top10 = base64_encode(serialize($new_arr));

        $podsvetki_yandex = $this->createCorrectKeys($podsvetki_yandex[0]['podsvetki'], $stop_keys, $i = 1);
        $podskazki_yandex = $this->createKeysSuggestYandex($param['keys']);
        $podskazki_yandex = $this->createCorrectKeys($podskazki_yandex, $stop_keys, $i = 1);
        $podsvetki_google = $this->createCorrectKeys($podsvetki_google, $stop_keys, $i = 1);
        $podskazki_google = $this->createKeysGoogleSuggest($param['keys']);
        $podskazki_google = $this->createCorrectKeys($podskazki_google, $stop_keys, $i = 1);

        $arr_direct_keys = explode("\n", $param['keys']);
        if(count($arr_direct_keys) >= 10){
            $ten = 10;
        } else {
            $ten = count($arr_direct_keys);
        }
        $direct_keys = array();
        $dilute_keys = array();
        for($i = 0; $i < $ten; $i++){
            if($arr_direct_keys[$i]){
                if($i%2 == 0){
                    $direct_keys[] = $arr_direct_keys[$i];
                } else {
                    $dilute_keys[] = $arr_direct_keys[$i];
                }
            }
        }
        $result_keys['direct'] = $direct_keys;
        $result_keys['dilute'] = $dilute_keys;

        $create_lsi_y = array_merge($podsvetki_yandex, $podskazki_yandex);
        $create_lsi_y = array_unique($create_lsi_y);
        $create_lsi_y = array_diff($create_lsi_y, $result_keys['dopkeys']);

        $create_lsi_g = array_merge($podsvetki_google, $podskazki_google);
        $create_lsi_g = array_unique($create_lsi_g);
        $create_lsi_g = array_diff($create_lsi_g, $result_keys['dopkeys']);
        
        $create_lsi_gy = array_intersect($create_lsi_y, $create_lsi_g);
        
        $result_keys['lsi_gy'] = $create_lsi_gy;
        $create_lsi_g = array_diff($create_lsi_g,  $create_lsi_y);
        $result_keys['lsi_g'] = array_diff($create_lsi_g,  $create_lsi_gy);
        $result_keys['lsi'] = array_diff($create_lsi_y,  $create_lsi_gy);

        $keys = json_encode($result_keys);
        
        DB::table('tasks')
        ->where('id', $param['id'])
        ->where('user_id', $param['user_id'])
        ->update([
            'task' => $keys, 
            'status' => 7,
            'topten' => $top10,
        ]);

        return $keys;
    }

    public function validateKeys($keys)
    {

        $arr = explode("\n", $keys);
        $new_arr = array();
        if(count($arr) > 1 ){
            foreach($arr as $key){
                if(trim($key) != ''){
                    $new_key = preg_replace('/[~\<\`\!\@\"\'\#\¹\$\;\%\^\:\(\)\&\?\*\\\+\=\_\-\\/\,\<\>\{\}\[\]]/ui', ' ', $key);
                    $new_key = str_replace('   ', ' ', $new_key);
                    $new_key = str_replace('  ', ' ', $new_key);
                    $new_arr[] = trim($new_key);
                }
            }
            return implode("\n", $new_arr);
        } else {
            if(trim($arr[0]) != ''){
                $new_key = preg_replace('/[~\<\`\!\@\"\'\#\¹\$\;\%\^\:\(\)\&\?\*\\\+\=\_\-\\/\,\<\>\{\}\[\]]/ui', ' ', $arr[0]);
                $new_key = str_replace('   ', ' ', $new_key);
                $new_key = str_replace('  ', ' ', $new_key);
                $new_key .= "\n".$new_key;
            }
            return trim($new_key);
        }
    }

    public function createCorrectKeys($keys, $stop_keys, $i = 0)
    {
        $keys_string = implode(' ', (array) $keys);
        $new_keys = explode(' ', $keys_string);
        $new_keys = array_unique($new_keys);
        $key_lost = array();
        $key_morf = array();
        $morf_words = array();

        foreach($new_keys as $key){

            if(in_array($key, Stopkeys::getNotLemmKeys())){
                $key_lost[] = mb_strtolower($key);
            } elseif(intval($key)){
                $key_lost[] = mb_strtolower($key);
            } else {
                $article = new \Mystem\Article($key);

                if(count($article->words) == 0){
                    if(in_array($key, $stop_keys)) continue;
                    $key_morf[] = mb_strtolower($key);
                } else {
                    foreach ($article->words as $word) {
                        $morf = mb_strtolower($word); 
                    }
                    if(in_array($morf, $stop_keys)) {
                        continue;
                    } else {
                        $key_morf[] = $morf;
                    }
                }
            }       
        }

        $result = array_merge($key_morf, $key_lost);
        $result = array_diff($result, array('', NULL, false));
        
        $result = array_unique($result);
        return $result;        
    }

    public function createKeysSuggestYandex($keys)
    {

        $key_arr = explode("\n", $keys);
        $arr_keys = array();
        $i = 0;
        foreach($key_arr as $key){
            if($i == 500) break;
            $array['ct'] = 'text/html';
            $array['part'] = $key;
            $array['v'] = 4;

            $url = 'http://suggest.yandex.ru/suggest-ya.cgi?' . http_build_query($array);

            $curl_handle=curl_init();
            curl_setopt($curl_handle, CURLOPT_URL,$url);
            curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);

            $content = curl_exec($curl_handle);
            curl_close($curl_handle);
            $data = json_decode($content, true);

            foreach((array) $data[1] as $key => $val){
                if(!is_array($val) && $val != NULL)
                    $arr_keys[]  = (string) $val;
            }
            $i++;
        }

        return $arr_keys;
    }

    public function createKeysXmlYandex($keys, $region = 29)
    {
        $param = array();
        $key_arr = explode("\n", $keys);
        $options = DB::table('users')
        ->where('id', $this->user)
        ->get();
        $option = '';
        $stop_domains = NULL;
        
        foreach ($options as $key) {
            $option = $key->options;
            $stop_domains = $key->stopdomains;
        }
        
        $option = json_decode($option, true);
        $option['region'] = $region;
        
        if($stop_domains !== null){
            $option['stop_domains'] = explode("\n", $stop_domains);
        }
        
        $parsing = new ParseController($key_arr, $option);
        $xmllimit = $parsing->parseXmlForYandex();
        
        if($xmllimit == 'error') $parsing->parseXmlForXMLProxy();
        $parsing->compare_urls();
        $res = $parsing->parseUrl();

        return $res;
    }

    public function createKeysGoogleSuggest($keys)
    {
        $key_arr = explode("\n", $keys);
        $i = 0;
        $suggest = array();
        $data = array();
        foreach($key_arr as $key){
            $array['callback'] = '?';
            $array['hl'] = 'ru';
            $array['output'] = 'toolbar';
            $array['q'] = $key;

            $google = "http://suggestqueries.google.com/complete/search?" . http_build_query($array);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $google);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $xmlInput = curl_exec($ch);
            if(empty($xmlInput)) continue;
            $thisxml  = iconv('windows-1251', 'utf-8', $xmlInput);
            $DOMDo = new \DOMDocument();
            $DOMDo->loadxml($thisxml);
            $toplevel = $DOMDo->getElementsByTagName('toplevel');
            $suggest = $DOMDo->getElementsByTagName('suggestion');

            foreach ($suggest as $suggests) {
                $data[] = $suggests->getAttribute('data');
            }
            curl_close($ch);

            $i++;
            if($i == 50) break;
        }

        $suggest = array_unique($data);
        return $suggest;
    }

}

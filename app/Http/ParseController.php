<?php
/*
    Контроллер отвечающий за парсинг досок объявлений.
    Был написан как дополнение к существующему ф-ционалу темы https://codecanyon.net/item/laraclassified-geo-classified-ads-cms/16458425
*/
namespace App\Http\Controllers\Admin;

use App\Parse;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Models\Picture;
use App\Jobs\ParseTorontovka;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;
use Larapen\Admin\app\Http\Controllers\Controller;
use Prologue\Alerts\Facades\Alert;

class ParseController extends Controller
{

    public function index()
    {
        $data = ['asd'];
        $categories = Category::trans()->where('parent_id', 0)->with([
                'children' => function ($query) {
                    $query->trans();
                },
            ])->orderBy('lft')->get();
        $parse_task = Parse::all();

        $img = '';
        
        $subcategory = Category::where('id', 867)->select('name', 'parent_id')->get();
        $category = Category::where('id', $subcategory[0]->parent_id)->select('name', 'parent_id')->get();
        $puth_to_img = 'public/files/parse_picture/' . $category[0]->name . '/' . $subcategory[0]->name;

        $files = File::glob($puth_to_img . '/*.*');
        if(!empty($files)) {
            $file = array_rand($files);
            $img = $files[$file];
            $full_patch = '/public/' . $img;
        }
            
        return view('admin::parser', ['categories' => $categories, 'parse_task' => $parse_task, 'img' => $full_patch]);

    }

    public function createNewParseJob(Request $request)
    {
        $classes = [
            'top_ad_class' => 'top_cen',
            'ad_class' => 'classified',
            'h_class' => 'top_cen_text',
            'body_class_top' => 'add_text',
            'body_class_simple' => 'add_text_regular',
            'name_class' => 'ad_user',
            'tel_class' => 'ad_phone',
            'img_class' => 'ad_image',
            'date_class' => 'ad_info'
        ];
        
        $res = Parse::create([
            'name' => $request->parse_name,
            'category' => $request->parent_id,
            'subcategory' =>$request->category_id,
            'url_parse' =>$request->url,
            'month' =>$request->month,
            'classes' =>json_encode($classes)
            ]);

        return redirect()->back();
    }

    public function addNewParseJob($id)
    {
        Parse::whereId($id)->update(['stat' => 1, 'updated_at' => NOW()]);
        return redirect()->back();
    }

    public function stopParseJob($id)
    {
        Parse::whereId($id)->update(['stat' => 0]);
        return redirect()->back();
    }

    public function parsePages($id)
    {
        $parse_data = Parse::where('id', $id)->get()->toArray();
        $parse_data = $parse_data[0];

        $this->findAlladds($parse_data);
    }

    public function getCostAd($str)
    {
        $arr_regs = array('/([\$]\s?[0-9,]{1,10})/', '/([0-9,]{1,10}\s?([\$]|cda|долларов|у\.е\.|\sу\.е\.|баксов|уе))/U');
        $res = array();
        foreach($arr_regs as $reg){
            preg_match_all($reg, $str, $out);
            foreach($out[0] as $cost){
                $res[] = preg_replace('~\D+~','', $cost);
            }
        }

        return @max($res);
    }

    public function runParseJob($id)
    {
        Parse::whereId($id)->update(['updated_at' => NOW()]);
        $job = (new ParseToronto($id));
        $this->dispatch($job);
        return redirect()->back();
    }

    public function findAlladds($parse_data)
    {
        $parse_url_arr = parse_url($parse_data['url_parse']);
        $parse_url_arr_path = explode('/', $parse_url_arr['path']);
        $end_url = explode('.', end($parse_url_arr_path));
        array_pop($parse_url_arr_path);
        $resp = 0;
        $stop = Parse::whereId($parse_data['id'])->value('stop');
        if($stop == null) $stop = $end_url[0];
        for($i = $stop; $i < 9999; $i++){
            $correct_url = $parse_url_arr['scheme'] . '://' . $parse_url_arr['host'] . implode('/', $parse_url_arr_path) . '/' . $i . '.' . $end_url[1];
            if (filter_var($correct_url, FILTER_VALIDATE_URL)) {
                $content = $this->parseCurl($correct_url);

                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                
                if($content == '' || empty($content)) $content = 'Ошибка парсинга';
                $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES'));
                
                $ads = $this->parseContentForClasses($dom, $parse_data['classes'], $parse_data['month']);
                
                foreach ($ads as $ad) {
                    $user = $this->addUser($ad);
                    if($user != false) 
                        $resp = $this->addAd($ad, $user, $parse_data);

                    if(isset($ad['break_metka']) || count($ad) == 0 || $resp == 3) {
                        $break_metka = 1;
                        break;   
                    }
                }
                if(isset($break_metka)) break;
            }
        }

        return redirect()->back();
    }

    public function addAd($ad, $user_id, $parse_data)
    {
        $tor_id = Post::where('tor_id', $ad['id_tor'])->value('tor_id');
        $resp = 0;
        if(!isset($ad['ad_text'])) $resp = 1;

        if(isset($ad['title'])){
            $title = $ad['title'];
        }else{
            $des = explode(' ', $ad['ad_text']);
            $title = '';
            $i = 0;
            foreach ($des as $key) {
                $title .= $key . ' ';
                $i++;
                if($i == 8) break;
            }
            $title = trim($title);
        }

        if(isset($ad['ad_user'])) {
            $ad_user = $ad['ad_user'];
        }else{
            $ad_user = null;
        }

        $description = $this->checkDescription($ad['ad_text']);
        
        $cost = $this->getCostAd($title);
        if($cost == false) $cost = $this->getCostAd($ad['ad_text']);
        if($cost == false) $cost = NULL;
        if($cost == $ad['ad_phone']) $cost = NULL;

        if($tor_id != $ad['id_tor']){
            $resp = 2;
        } else {
            $resp = 3;
            return $resp;
        }

        $data = Post::create([
                'country_code' => 'CA',
                'user_id' => $user_id,
                'tor_id' => $ad['id_tor'],
                'category_id' => $parse_data['subcategory'],
                'post_type_id' => 3,
                'title' => $this->sanitize_title_with_translit($title),
                'slug' => $title,
                'description' => $description,
                'price' => $cost,
                'negotiable' => 1,
                'contact_name' => $ad_user,
                'phone' => '+1' . $ad['ad_phone'],
                'city_id' => 6167865,
                'verified_email' => 1,
                'verified_phone' => 1,
                'created_at' => $ad['publish_date']
                ]);

        if(isset($ad['ad_image']) && isset($data)){
            $this->addImg('https://site.com' . $ad['ad_image'], $data->id);
        } elseif(isset($data)) {
            $img = '';
            $subcategory = Category::where('id', $parse_data['subcategory'])->select('name', 'parent_id')->get();
            $category = Category::where('id', $subcategory[0]->parent_id)->select('name', 'parent_id')->get();

            $img = rand(1,5) . '.jpg';
            $puth_to_img = 'files/parse_picture/' . $category[0]->name . '/' . $subcategory[0]->name . '/' . $img;
            $self_puth_img = '/public/public/' . $puth_to_img;

            $this->addImg($self_puth_img, $data->id);
            
        }

        $this->addUrlToIndex($data->id, $title);                                                                                                                                              
        return $resp;
    }

    public function addUrlToIndex($id, $title)
    {
        $post['url'] = slugify($this->sanitize_title_with_translit($title)) . '-' . $id . '.html';
        $site_url = 'https://wpindu.site/api/addurl';
        $postvalue = json_encode($post);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $site_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postvalue);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($postvalue))                                                                       
        );                                                                                                                   
        $result = curl_exec($ch);
        curl_close($ch); 

        return $result; 
    }

    public function checkDescription($str)
    {
        $reg = '(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9]\.[^\s]{2,})';
        $out = preg_replace($reg, '', $str);
        $out = preg_replace('/\[email protected\]/', '', $out);

        return $out;
    }

    public function addImg($url, $post_id)
    {
        $name_img = explode('/', $url);
        $path_dir = 'files/ca/'. $post_id;
        $user_name = 'www-data';
        $img =  $path_dir . '/' . end($name_img);
        $url = preg_replace("/ /", "%20", $url);
        Storage::put($img, file_get_contents($url));

        $picture = new Picture([
            'post_id'  => $post_id,
            'filename' => $img,
            'position' => 1,
        ]);
        $picture->save();

    }

    public function addUser($ads)
    {
        $phone = null;
        $name = null;
        $publish_date = null;

        $chars = "qazxswedcvfrtgbnhyujmkiolp1234567890";
        $max = 6;
        $size = StrLen($chars)-1;
        $password = null;
        while($max--)
            $password .= $chars[rand(0,$size)];

        $hash_pass = Hash::make($password);

        if(isset($ads['ad_phone'])){
            if(isset($ads['ad_user'])) $name = $ads['ad_user'];
            $phone = '+1' . $ads['ad_phone'];
            $user = DB::table('users')->where('phone', $phone)->value('id');

            if($user == null){
                $data = User::create([
                'country_code' => 'CA',
                'language_code' => 'ru',
                'password' => $hash_pass,
                'temp_pass' => $password,
                'phone' => $phone,
                'name' => $name,
                'verified_email' => 1,
                'verified_phone' => 1,
                'created_at' => NOW()
                ]);
                return $data->id;
            } else {
                return $user;
            }
             
        } else {
            return false;
        }
            
    }

    public function parseCurl($url) 
    {
        sleep(rand(2, 5));
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 25);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 25);
        curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl_handle, CURLOPT_COOKIESESSION, 1);
        curl_setopt($curl_handle, CURLOPT_FAILONERROR, 1);
        curl_setopt($curl_handle, CURLOPT_HEADER, 1);
        curl_setopt($curl_handle, CURLOPT_VERBOSE, 1);
        curl_setopt($curl_handle, CURLOPT_COOKIEJAR, "/qwe.txt");
        $content = curl_exec($curl_handle);
        try {
            $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
        } catch (\ErrorException $e) {
            $content = $content;
        }
        
        curl_close($curl_handle);
        if ($content == '' || $content === false || empty($content))
            return false;

        return $content;
    }

    public function parseContentForClasses($dom, $class_arr, $month) 
    {
        $last_date = NOW()->subMonths($month)->format('Y/m/d');
        
        $res = [];
        $result = [];
        $class_arr = json_decode($class_arr, true);
        $ad_class = $class_arr['ad_class'];
        $top_ad_class = $class_arr['top_ad_class'];

        $xpath = new \DomXPath($dom);

        $expression = "//div[@class='$top_ad_class']";
        foreach ($xpath->query($expression) as $div) {
            $res[] = $div->getElementsByTagName('*');
        }

        $expression = "//div[@class='$ad_class']";
        foreach ($xpath->query($expression) as $div) {
            $res[] = $div->getElementsByTagName('*');
        }

        $expression = "//b";
        foreach ($xpath->query($expression) as $div) {
            $res[] = $div->getElementsByTagName('*');
        }

        foreach ($res as $elements) {
            $full_ad = [];
            $full_ad['publish_date'] = NOW();
            for($i = 0; $i < $elements->length; $i++) {
                if($elements->item($i)->nodeName == 'b'){
                    $full_ad['title'] = $elements->item($i)->nodeValue;
                }
                if($elements->item($i)->attributes->getNamedItem('class')){

                    if($elements->item($i)->attributes->getNamedItem('class')->nodeValue == $class_arr['name_class']) {
                        $full_ad[$class_arr['name_class']] = $elements->item($i)->nodeValue;
                    }
                    if($elements->item($i)->attributes->getNamedItem('class')->nodeValue == $class_arr['tel_class']) {
                        $tel = $elements->item($i)->nodeValue;
                        $full_ad[$class_arr['tel_class']] = preg_replace ("/[^0-9]/","",$tel);
                    } 
                    if($elements->item($i)->attributes->getNamedItem('class')->nodeValue == $class_arr['img_class']) {
                        $img = $elements->item($i);
                        $full_ad[$class_arr['img_class']] = $img->getElementsByTagName('a')->item(0)->getAttribute('href');
                    } 
                    if($elements->item($i)->attributes->getNamedItem('class')->nodeValue == $class_arr['body_class_top']) {
                        if(isset($elements->item($i)->nodeValue))
                        $full_ad['ad_text'] = $elements->item($i)->nodeValue;
                    }
                    if($elements->item($i)->attributes->getNamedItem('class')->nodeValue == $class_arr['body_class_simple']) {
                        if(isset($elements->item($i)->nodeValue))
                        $full_ad['ad_text'] = $elements->item($i)->nodeValue;
                    }
                    if($elements->item($i)->attributes->getNamedItem('class')->nodeValue == $class_arr['date_class']) { 
                        $c = $i - 1;
                        $date_ad_str = $elements->item($c)->nodeValue;
                        $date_parse = date_parse($date_ad_str);
                        if($date_parse['year'] == false){
                            $correct_date = NOW()->format('Y/m/d');
                            $date_to_db = NOW();
                        } else {
                            $correct_date = $date_parse['year'] . '/' . $date_parse['month'] . '/' . $date_parse['day'];
                            $date_to_db = $date_parse['year'] . '-' . $date_parse['month'] . '-' . $date_parse['day'];
                        }

                        $full_ad['publish_date'] = $date_to_db;
                        $full_ad['id_tor'] = (int) filter_var($elements->item($i)->nodeValue, FILTER_SANITIZE_NUMBER_INT);
                        
                        if(strtotime($correct_date) < strtotime($last_date)){
                            $break_metka = 1;
                            $full_ad['break_metka'] = $break_metka;
                            break;
                        } 
                    } 
                }
            }
            if(count($full_ad) > 0) $result[] = $full_ad;
            if(isset($break_metka)) break;
        }
        return $result;
    }

    public function sanitize_title_with_translit($title) 
    {
       $gost = array(
          "Є"=>"eh","І"=>"i","і"=>"i","№"=>"#","є"=>"eh",
          "А"=>"A","Б"=>"B","В"=>"V","Г"=>"G","Д"=>"D",
          "Е"=>"E","Ё"=>"JO","Ж"=>"ZH",
          "З"=>"Z","И"=>"I","Й"=>"JJ","К"=>"K","Л"=>"L",
          "М"=>"M","Н"=>"N","О"=>"O","П"=>"P","Р"=>"R",
          "С"=>"S","Т"=>"T","У"=>"U","Ф"=>"F","Х"=>"KH",
          "Ц"=>"C","Ч"=>"CH","Ш"=>"SH","Щ"=>"SHH","Ъ"=>"'",
          "Ы"=>"Y","Ь"=>"","Э"=>"EH","Ю"=>"YU","Я"=>"YA",
          "а"=>"a","б"=>"b","в"=>"v","г"=>"g","д"=>"d",
          "е"=>"e","ё"=>"jo","ж"=>"zh",
          "з"=>"z","и"=>"i","й"=>"jj","к"=>"k","л"=>"l",
          "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
          "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"kh",
          "ц"=>"c","ч"=>"ch","ш"=>"sh","щ"=>"shh","ъ"=>"",
          "ы"=>"y","ь"=>"","э"=>"eh","ю"=>"yu","я"=>"ya",
          "—"=>"-","«"=>"","»"=>"","…"=>""
         );

       $iso = array(
          "Є"=>"YE","І"=>"I","Ѓ"=>"G","і"=>"i","№"=>"#","є"=>"ye","ѓ"=>"g",
          "А"=>"A","Б"=>"B","В"=>"V","Г"=>"G","Д"=>"D",
          "Е"=>"E","Ё"=>"YO","Ж"=>"ZH",
          "З"=>"Z","И"=>"I","Й"=>"J","К"=>"K","Л"=>"L",
          "М"=>"M","Н"=>"N","О"=>"O","П"=>"P","Р"=>"R",
          "С"=>"S","Т"=>"T","У"=>"U","Ф"=>"F","Х"=>"X",
          "Ц"=>"C","Ч"=>"CH","Ш"=>"SH","Щ"=>"SHH","Ъ"=>"'",
          "Ы"=>"Y","Ь"=>"","Э"=>"E","Ю"=>"YU","Я"=>"YA",
          "а"=>"a","б"=>"b","в"=>"v","г"=>"g","д"=>"d",
          "е"=>"e","ё"=>"yo","ж"=>"zh",
          "з"=>"z","и"=>"i","й"=>"j","к"=>"k","л"=>"l",
          "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
          "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"x",
          "ц"=>"c","ч"=>"ch","ш"=>"sh","щ"=>"shh","ъ"=>"",
          "ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya",
          "—"=>"-","«"=>"","»"=>"","…"=>""
         );
        
        $result = strtr($title, $gost);
        $result = strtolower($result);
        $result = preg_replace('/[^a-zA-Z\s]/ui', '',$result );
        
        return $result;
    }

}

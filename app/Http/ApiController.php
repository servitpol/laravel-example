<?php
/*
    Контроллер отвечающий за логику работы серверной части 
    написаннной для Wordpress плагина.
*/
namespace App\Http\Controllers;

use App\Project;
use App\Connection;
use App\ConnectionLink;
use App\Stats;
use App\ConnectionLinksPlan;
use App\Jobs\ArticlesInProcessing;
use App\Jobs\ConnectionsInProcessing;
use App\Jobs\JobSetConnections;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class ApiController extends Controller
{
    public function checkConnect(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|exists:projects,token',
            'url' => 'required|url|exists:projects,url',
        ]);

        if ($errors = $this->_setResponse($validator)) {
            $errors->status = 422;
            return response()->json($errors, 422);
        }
        $project = Project::where('token', $request->token)
            ->where('url', $request->url)
            ->limit(1)
            ->get();

        if ($project->first()) {
            $project->first()->update(['token_verify' => 1]);

            $json = ['message' => 'Operation successful', 'status' => 200];
            return response()->json($json);
        } else {
            $json = array('message' => 'There is no project with such a token', 'status' => 301);
        }
        return response()->json($json, $json['status']);
    }

    public function setStats(Request $request)
    {
        if ($request->all() == null){
            die(json_encode(['message' => 'Data did not received']));
        } else {
            echo json_encode(['message' => 'Data was received'], JSON_UNESCAPED_UNICODE);
        }
        $data = $request->all();

        dispatch(new ArticlesInProcessing($data));
        return;
    }

    public function checkStatsStatus(Request $request)
    {
        if(!$request->has('token')){
            return json_encode(['message' => 'Token was not received!'], JSON_UNESCAPED_UNICODE);
        }
        $token = $request->token;
        $result = DB::table('stats_counter')
            ->where('project_token', $token)
            ->value('progress');
        if(!$result){
            return '0';
        }
        return json_encode($result);
    }

    public function deleteConnections($project)
    {
        if ($project !== null) {
            Stats::where('project_id', $project->id)
                ->update([
                    'total_count' => 0,
                    'check_conncetion' => 0,
                    'is_full' => 0,
                    'state' => 0
                ]);
            Connection::where('project_id', $project->id)->delete();
            ConnectionLink::where('project_id', $project->id)->delete();
            return true;
        } else {
            return false;
        }
    }

    public function setConnections(Request $request)
    {
        if ($request->all() == null || empty($request->all()) ){
            echo json_encode(['message' => 'Connections did not received'], JSON_UNESCAPED_UNICODE);
        }
        
        $id_request = $request->all()['id_request'];
        $connections = $request->all()['connections'];
        $all_count = $request->all()['all_count'];
        $current_request = $request->all()['current'];

        $rt = dispatch(new JobSetConnections( $id_request, $connections, $all_count, $current_request ));
        return;
    }

    public function ApiResponse($url, $body)
    {
        $client = new Client();
        try {
            $query =  $client->request('POST', $url, [
                'body'          => $body,
                'headers'        => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
            $response = $query->getBody()->getContents();
            $response = json_decode($response, true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = json_decode($e->getMessage(), true);
        }
        
        return $response;
    }


    public function _setResponse($validator)
    {
        try {
            if ($validator->fails()) {
                $errors = $validator->errors();
                $errors = json_decode($errors);
                return $errors;
            }
            return false;
        } catch (Exception $exception) {
            Log::error(request()->route()->getAction()['controller'] . ":" . $exception);
        }
    }

    public function executedConnections(Request $request)
    {
        $projectId = $request->input('0.project_id');
        foreach ($request->all() as $item) {
            if($item['status'] == 2){
                $success[] = $item;
            } elseif($item['status'] == 0){
                $unsuccess[] = $item;
            } else {
                continue;
            }
        }

        if (isset($success) && !empty($success)){
            ConnectionLinksPlan::
            where('project_id', $projectId)
                ->whereIn('id', array_column($success, 'connection_links_plans_id'))
                ->update(['status' => 2]);
            Connection::
            where('project_id', $projectId)
                ->whereIn('id', array_column($success, 'connections_id'))
                ->update(['state' => 2]);
        }
        if (isset($unsuccess) && !empty($unsuccess)){
            ConnectionLinksPlan::
            where('project_id', $projectId)
                ->whereIn('id', array_column($unsuccess, 'connection_links_plans_id'))
                ->update(['status' => 0]);
            Connection::
                where('project_id', $projectId)
                ->whereIn('id', array_column($unsuccess, 'connections_id'))
                ->update(['state' => 3]);
        }
        return response()->json(['response' => 'OKK!'], 200);
    }

    public function getUsersMail(Request $request)
    {
        $users = array('data data');
        if(isset($request->token)){
            $user_id = DB::table('users')
                ->where('email_token', '=', $request->token)
                ->value('id');
            if($user_id != 8) return json_encode('error');

            $emails = DB::table('users')
                ->where('role_id', '=', 1)
                ->get();
            foreach ($emails as $key) {
                $emails_users[$key->email] = $key->name;
            }
            return json_encode($emails_users);             
        }

    }

    public function getUsersRepl(Request $request)
    {
        $users = array('data data');
        if(isset($request->token)){
            $user_id = DB::table('users')
                ->where('email_token', '=', $request->token)
                ->value('id');
           
            if($user_id != 8) return json_encode('error');

            $emails = DB::table('balances')
                ->leftjoin('users', 'users.id', '=', 'balances.user_id')
                ->where('users.role_id', 1)
                ->select('users.email')
                ->get();

            foreach ($emails as $key) {
                $emails_users[] = $key->email;
            }
            return json_encode(array_unique($emails_users));             
        }

    }
}

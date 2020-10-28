<?php
/*
    Модель отвечающая за логику работы серверной части 
    написаннной для Wordpress плагина.
*/  
namespace App;

use App\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Connection extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $table = 'connections';
    protected $fillable = [
        'project_id',
        'input_article_id',
        'output_article_id',
        'start',
        'finish',
        'date_post',
        'context',
        'state',
        'text',
    ];

    public static function getAllConnectionsCollection($project_id, $article_id, $extraCondition = null)
    {
        if (is_null($extraCondition)) {
            $extraCondition = ['connections.id', '<>', null];
        }
            $connections = Connection::
            select('connections.*')
            ->where([
                ['connections.project_id', '=', $project_id],
                ['connections.input_article_id', '=', $article_id],
                $extraCondition
            ])
            ->orWhere([
                ['connections.project_id', '=', $project_id],
                ['connections.output_article_id', '=', $article_id],
                $extraCondition
            ]);
        return $connections;
    }

    public static function getAllConnections($project_id, $article_id)
    {
        return Connection::getAllConnectionsCollection($project_id, $article_id)->get();
    }

    public static function getAllConnectionsCount($project_id, $article_id)
    {

        $totalCount = Connection::getAllConnectionsCollection(
            $project_id,
            $article_id)
            ->count();
        $checkCount = Connection::getAllConnectionsCollection(
            $project_id,
            $article_id,
            ['connections.state', '>', 0])
            ->count();
        $result = array(
            'check_conncetion' => $checkCount,
            'total_count' => $totalCount,
            'is_full' => false
        );
        if ($result['check_conncetion'] == $result['total_count'])
            $result['is_full'] = true;
        return $result;
    }

    public static function getConnectionsCollection($project_id, $stats_id, $view)
    {

        $connectionsIds = ConnectionLink::getConnections($project_id, $stats_id, $view);
        $connections = Connection::getConnectionFromIds($connectionsIds, $project_id, $view);
        return $connections;
    }

    public static function getConnections($project_id, $stats_id, $view)
    {
        return Connection::getConnectionsCollection($project_id, $stats_id, $view);
    }

    public static function getConnectionFromIds($connectionsIds, $project_id, $view)
    {
        $source = Project::where('id', $project_id)->value('keywords');
        if($view == 1){
            $connections = Connection::
            join('stats as stats1', 'connections.output_article_id', '=', 'stats1.article_id')
                ->select('connections.*',
                    'stats1.title as output_article_title',
                    'stats1.url as output_article_url',
                    'stats1.id as origin_article_id'
                )
                ->where('stats1.project_id', $project_id)
                ->whereIn('connections.id', $connectionsIds)
                ->distinct()
                ->get();

        } elseif ($view == 2){
            $cons = Connection::
                where('project_id', $project_id)
                ->whereIn('connections.id', $connectionsIds)
                ->get()->toArray();
            $connections = [];
            foreach ($cons as $con) {

                if($source != 0){
                    $frequency =  Stats::
                    leftJoin('keywords', 'keywords.url', '=', 'stats.url')
                        ->select('stats.url', 'stats.title', 'keywords.id as key_id', 'keywords.frequency')
                        ->where([
                            'stats.project_id' => $con['project_id'],
                            'stats.article_id' => $con['output_article_id']
                        ])
                        ->get()->max('frequency');
                        
                    if (!is_null($frequency)) {
                        $articleOut = Stats::
                        leftJoin('keywords', 'keywords.url', '=', 'stats.url')
                            ->select('stats.url', 'stats.title', 'keywords.id as key_id')
                            ->where([
                                'keywords.source' => $source,
                                'stats.project_id' => $con['project_id'],
                                'stats.article_id' => $con['output_article_id'],
                                'keywords.frequency' => $frequency,
                            ])
                            ->first();
                    } else {
                        $articleOut = Stats::
                        leftJoin('keywords', 'keywords.url', '=', 'stats.url')
                            ->select('stats.url', 'stats.title', 'keywords.id as key_id')
                            ->where([
                                'keywords.source' => $source,
                                'stats.project_id' => $con['project_id'],
                            ])
                            ->first();
                    }
                } else {
                    $frequency = Stats::
                    leftJoin('keywords', 'keywords.url', '=', 'stats.url')
                        ->select('stats.url', 'stats.title', 'keywords.id as key_id', 'keywords.frequency')
                        ->where([
                            'stats.project_id' => $con['project_id'],
                            'stats.article_id' => $con['output_article_id']
                        ])
                        ->get()->max('frequency');

                    if (!is_null($frequency)) {
                        $articleOut = Stats::
                        leftJoin('keywords', 'keywords.url', '=', 'stats.url')
                            ->select('stats.url', 'stats.title', 'keywords.id as key_id')
                            ->where([
                                'stats.project_id' => $con['project_id'],
                                'stats.article_id' => $con['output_article_id'],
                                'keywords.frequency' => $frequency,
                            ])
                            ->first();
                    } else {
                        $articleOut = Stats::
                        leftJoin('keywords', 'keywords.url', '=', 'stats.url')
                            ->select('stats.url', 'stats.title', 'keywords.id as key_id')
                            ->where([
                                'stats.project_id' => $con['project_id'],
                                'stats.article_id' => $con['output_article_id']
                            ])
                            ->first();
                    }
                }
        
                $articleIn = Stats::
                leftJoin('relevants', function ($join) use ( $articleOut ){
                    $join->on('stats.url', '=', 'relevants.url')
                        ->where('relevants.keyword_id', $articleOut->key_id);
                })
                ->select('stats.url', 'stats.title', 'relevants.relevant', 'stats.id')
                ->where([
                    'stats.project_id' => $con['project_id'],
                    'stats.article_id' => $con['input_article_id']
                    ])
                    ->first();

                $con = array_add($con, 'output_article_title', $articleOut->title);
                $con = array_add($con, 'output_article_url',  $articleOut->url);
                $con = array_add($con, 'key_id',  $articleOut->key_id);
                $con = array_add($con, 'input_article_title', $articleIn->title);
                $con = array_add($con, 'input_article_url',  $articleIn->url);
                $con = array_add($con, 'origin_stats_input_id',  $articleIn->id);
                $con = array_add($con, 'relevant',  $articleIn->relevant);
                $connections[] = $con;
            }
        }
        return $connections;
    }

    public static function getTotalConnections($projectId)
    {
        return self::where('project_id', $projectId)->count();
    }

    public function statsInput()
    {
        return $this->belongsTo(Stats::class, 'input_article_id','article_id' )->where('project_id', $this->project_id);
    }

    public function statsOutput()
    {
        return $this->belongsTo(Stats::class,  'output_article_id','article_id')->where('project_id', $this->project_id);
    }
}

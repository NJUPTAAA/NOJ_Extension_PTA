<?php
namespace App\Babel\Extension\pta;

use App\Babel\Crawl\CrawlerBase;
use App\Models\ProblemModel;
use App\Models\OJModel;
use KubAT\PhpSimple\HtmlDomParser;
use Requests;
use Exception;

class Crawler extends CrawlerBase
{
    public $oid=null;
    public $prefix="PTA";
    private $con;
    private $imgi;
    /**
     * Initial
     *
     * @return Response
     */
    public function __construct($conf)
    {
        $action=isset($conf["action"])?$conf["action"]:'crawl_problem';
        $con=isset($conf["con"])?$conf["con"]:'all';
        $cached=isset($conf["cached"])?$conf["cached"]:false;
        $this->oid=OJModel::oid('pta');

        if(is_null($this->oid)) {
            throw new Exception("Online Judge Not Found");
        }

        if ($action=='judge_level') {
            $this->judge_level();
        } else {
            $this->crawl($con);
        }
    }

    public function judge_level()
    {
        // TODO
    }

    public function crawl($con)
    {
        $start=time();
        if ($conType=='all') {
            // Here is the script
            //
            // var a="";
            // document.querySelectorAll('a[href^="/problem-sets/"]').forEach(v=>{a+=v.href.split("/")[4]+","})
            // console.log(a);

            $conList=[12, 13, 14, 15, 16, 17, 434, 994805046380707840, 994805148990160896, 994805260223102976, 994805342720868352];
        } else {
            $conList=[intval($conType)];
        }

        foreach ($conList as $con) {
            $this->con=$con;
            $this->imgi=1;
            $problemModel=new ProblemModel();
            $res=Requests::get("https://pintia.cn/api/problem-sets/$con/exams", [
                "Accept"=>"application/json;charset=UTF-8",
                "Content-Type"=>"application/json"
            ]);

            if (strpos($res->body, 'PROBLEM_SET_NOT_FOUND')!==false) {
                header('HTTP/1.1 404 Not Found');
                die();
            } else {
                $generalDetails=json_decode($res->body, true);
                $compilerModel=new CompilerModel();
                $list=$compilerModel->list($this->oid);
                $compilers=[];
                foreach ($generalDetails['problemSet']['problemSetConfig']['compilers'] as $lcode) {
                    foreach ($list as $compiler) {
                        if ($compiler['lcode']==$lcode) {
                            array_push($compilers, $compiler['coid']);
                            break;
                        }
                    }
                }
                $this->pro['special_compiler']=join(',', $compilers);
            }

            $now=time()-$start;

            $probLists=json_decode(Requests::get(
                "https://pintia.cn/api/problem-sets/$con/problems?type=PROGRAMMING&exam_id=0",
                [
                    "Accept"=>"application/json;charset=UTF-8",
                    "Content-Type"=>"application/json"
                ]
            )->body, true)["problemSetProblems"];

            $now=time()-$start;

            foreach ($probLists as $prob) {
                $probDetails=json_decode(Requests::get(
                    "https://pintia.cn/api/problem-sets/$con/problems/{$prob["id"]}",
                    [
                        "Accept"=>"application/json;charset=UTF-8",
                        "Content-Type"=>"application/json"
                    ]
                )->body, true)["problemSetProblem"];

                $now=time()-$start;

                $this->pro['pcode']='PTA'.$prob["id"];
                $this->pro['OJ']=$this->oid;
                $this->pro['contest_id']=$con;
                $this->pro['index_id']=$prob["id"];
                $this->pro['origin']="https://pintia.cn/problem-sets/$con/problems/{$prob["id"]}";
                $this->pro['title']=$prob["title"];
                $this->pro['time_limit']=$probDetails["problemConfig"]["programmingProblemConfig"]["timeLimit"];
                $this->pro['memory_limit']=$probDetails["problemConfig"]["programmingProblemConfig"]["memoryLimit"];
                $this->pro['solved_count']=$prob["acceptCount"];
                $this->pro['input_type']='standard input';
                $this->pro['output_type']='standard output';

                $this->pro['description']=str_replace("](~/", "](https://images.ptausercontent.com/", $probDetails["content"]);
                $this->pro['description']=str_replace("$$", "$$$", $this->pro['description']);
                $this->pro['markdown']=1;
                $this->pro['tot_score']=$probDetails["score"];
                $this->pro["partial"]=1;
                $this->pro['input']=null;
                $this->pro['output']=null;
                $this->pro['note']=null;
                $this->pro['sample']=[];
                $this->pro['source']=$generalDetails["problemSet"]["name"];

                $problem=$problemModel->pid($this->pro['pcode']);

                if ($problem) {
                    $problemModel->clearTags($problem);
                    $new_pid=$this->update_problem($this->oid);
                } else {
                    $new_pid=$this->insert_problem($this->oid);
                }

                $now=time()-$start;

                usleep(400000); // PTA Restrictions 0.5s

                // $problemModel->addTags($new_pid, $tag);
            }
        }
    }
}

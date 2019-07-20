<?php
namespace App\Babel\Extension\pta;

use App\Babel\Crawl\CrawlerBase;
use App\Models\CompilerModel;
use App\Models\ProblemModel;
use App\Models\OJModel;
use KubAT\PhpSimple\HtmlDomParser;
use Requests;
use Exception;

class Crawler extends CrawlerBase
{
    public $oid = null;
    public $prefix = "PTA";
    private $con;
    /**
     * Initial
     *
     * @return Response
     */
    public function start($conf)
    {
        $action = isset($conf["action"]) ? $conf["action"] : 'crawl_problem';
        $con = isset($conf["con"]) ? $conf["con"] : 'all';
        $cached = isset($conf["cached"]) ? $conf["cached"] : false;
        $this->oid = OJModel::oid('pta');

        if (is_null($this->oid)) {
            throw new Exception("Online Judge Not Found");
        }

        if ($action == 'judge_level') {
            $this->judge_level();
        } else {
            $this->crawl($con, $action == 'update_problem');
        }
    }

    public function judge_level()
    {
        // TODO
    }

    public function crawl($con, $incremental)
    {
        if ($con == 'all') {
            // Here is the script
            //
            // var a="";
            // document.querySelectorAll('a[href^="/problem-sets/"]').forEach(v=>{a+=v.href.split("/")[4]+","})
            // console.log(a);

            $conList = [12, 13, 14, 15, 16, 17, 434, 994805046380707840, 994805148990160896, 994805260223102976, 994805342720868352];
        } else {
            $conList = [intval($conType)];
        }

        $problemModel = new ProblemModel();
        $updmsg = $incremental ? 'Updating' : 'Crawling';
        $donemsg = $incremental ? 'Updated' : 'Crawled';
        foreach ($conList as $con) {
            $this->con = $con;

            $this->line("<fg=yellow>{$updmsg} exam: </>$con");

            $res = Requests::get("https://pintia.cn/api/problem-sets/$con/exams", [
                "Accept" => "application/json;charset=UTF-8",
                "Content-Type" => "application/json"
            ]);

            if (strpos($res->body, 'PROBLEM_SET_NOT_FOUND') !== false) {
                $this->line("\n  <bg=red;fg=white> Exception </> : <fg=yellow>Failed fetching exam info.</>\n");
                continue;
            }
            $generalDetails = json_decode($res->body, true);
            $compilerModel = new CompilerModel();
            $list = $compilerModel->list($this->oid);
            $compilers = [];
            foreach ($generalDetails['problemSet']['problemSetConfig']['compilers'] as $lcode) {
                foreach ($list as $compiler) {
                    if ($compiler['lcode'] == $lcode) {
                        array_push($compilers, $compiler['coid']);
                        break;
                    }
                }
            }
            $this->pro['special_compiler'] = join(',', $compilers);

            $probLists = json_decode(Requests::get(
                "https://pintia.cn/api/problem-sets/$con/problem-list?problem_type=PROGRAMMING",
                [
                    "Accept" => "application/json;charset=UTF-8",
                    "Content-Type" => "application/json"
                ]
            )->body, true)["problemSetProblems"];

            foreach ($probLists as $prob) {
                if ($incremental && !empty($problemModel->basic($problemModel->pid('PTA' . $prob['id'])))) {
                    continue;
                }
                $this->line("<fg=yellow>{$updmsg}:   </>PTA$prob[id]");

                $probDetails = json_decode(Requests::get(
                    "https://pintia.cn/api/problem-sets/$con/problems/{$prob["id"]}",
                    [
                        "Accept" => "application/json;charset=UTF-8",
                        "Content-Type" => "application/json"
                    ]
                )->body, true)["problemSetProblem"];

                $this->pro['pcode'] = 'PTA' . $prob["id"];
                $this->pro['OJ'] = $this->oid;
                $this->pro['contest_id'] = $con;
                $this->pro['index_id'] = $prob["id"];
                $this->pro['origin'] = "https://pintia.cn/problem-sets/$con/problems/{$prob["id"]}";
                $this->pro['title'] = $prob["title"];
                $this->pro['time_limit'] = $probDetails["problemConfig"]["programmingProblemConfig"]["timeLimit"];
                $this->pro['memory_limit'] = $probDetails["problemConfig"]["programmingProblemConfig"]["memoryLimit"];
                $this->pro['solved_count'] = $prob["acceptCount"];
                $this->pro['input_type'] = 'standard input';
                $this->pro['output_type'] = 'standard output';

                $this->pro['description'] = str_replace("](~/", "](https://images.ptausercontent.com/", $probDetails["content"]);
                $this->pro['description'] = str_replace("$$", "$$$", $this->pro['description']);
                $this->pro['markdown'] = 1;
                $this->pro['tot_score'] = $probDetails["score"];
                $this->pro["partial"] = 1;
                $this->pro['input'] = null;
                $this->pro['output'] = null;
                $this->pro['note'] = null;
                $this->pro['sample'] = [];
                $this->pro['source'] = $generalDetails["problemSet"]["name"];

                $problem = $problemModel->pid($this->pro['pcode']);

                if ($problem) {
                    $problemModel->clearTags($problem);
                    $new_pid = $this->updateProblem($this->oid);
                } else {
                    $new_pid = $this->insertProblem($this->oid);
                }

                $this->line("<fg=green>$donemsg:    </>PTA$prob[id]");

                usleep(400000); // PTA Restrictions 0.5s

                // $problemModel->addTags($new_pid, $tag);
            }

            $this->line("<fg=green>$donemsg exam:  </>$con\n");
        }
    }
}

<?php
namespace App\Babel\Extension\pta;

use App\Babel\Submit\Curl;
use App\Models\CompilerModel;
use App\Models\JudgerModel;
use App\Models\OJModel;
use Illuminate\Support\Facades\Validator;
use Requests;

class Submitter extends Curl
{
    protected $sub;
    public $post_data = [];
    protected $oid;
    protected $selectedJudger;

    public function __construct(&$sub, $all_data)
    {
        $this->sub = &$sub;
        $this->post_data = $all_data;
        $judger = new JudgerModel();
        $this->oid = OJModel::oid('pta');
        if (is_null($this->oid)) {
            throw new Exception("Online Judge Not Found");
        }
        $judger_list = $judger->list($this->oid);
        $this->selectedJudger = $judger_list[array_rand($judger_list)];
    }

    private function _login()
    {
        // F**k capcha
    }

    private function _submit()
    {
        $pid = $this->post_data['iid'];

        sleep(1); // I forgot why
        $response = $this->grab_page([
            'site' => "https://pintia.cn/api/problem-sets/{$this->post_data['cid']}/exams",
            'oj' => 'pta',
            'headers' => ['Accept: application/json;charset=UTF-8'],
            'handle' => $this->selectedJudger['handle'],
        ]);

        if (strpos($response, 'PROBLEM_SET_NOT_FOUND') !== false) {
            header('HTTP/1.1 404 Not Found');
            die();
        }
        $generalDetails = json_decode($response, true);
        $examId = $generalDetails['exam']['id'];

        $params = [
            'details' => [
                [
                    'problemSetProblemId' => $this->post_data['iid'],
                    'programmingSubmissionDetail' => [
                        'compiler' => $this->post_data['lang'],
                        'program' => $this->post_data["solution"]
                    ]
                ]
            ],
            'problemType' => 'PROGRAMMING'
        ];

        $response = $this->post_data([
            'site' => "https://pintia.cn/api/exams/$examId/submissions",
            'data' => $params,
            'oj' => 'pta',
            'ret' => true,
            'returnHeader' => false,
            'postJson' => true,
            'extraHeaders' => ['Accept: application/json;charset=UTF-8'],
            'handle' => $this->selectedJudger['handle'],
        ]);
        $this->sub['jid'] = $this->selectedJudger['jid'];
        $ret = json_decode($response, true);
        if (isset($ret['submissionId'])) {
            $this->sub['remote_id'] = $ret['submissionId'];
        } else {
            $this->sub['verdict'] = 'Submission Error';
        }
    }

    public function submit()
    {
        $validator = Validator::make($this->post_data, [
            'pid' => 'required|integer',
            'cid' => 'required|integer',
            'coid' => 'required|integer',
            'iid' => 'required|integer',
            'solution' => 'required',
        ]);

        if ($validator->fails()) {
            $this->sub['verdict'] = "System Error";
            return;
        }

        $this->_login();
        $this->_submit();
    }
}

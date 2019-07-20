<?php
namespace App\Babel\Extension\pta;

use App\Babel\Submit\Curl;
use App\Models\ContestModel;
use App\Models\SubmissionModel;
use App\Models\JudgerModel;
use Requests;
use Exception;
use Log;

class Judger extends Curl
{

    public $verdict = [
        'ACCEPTED' => "Accepted",
        'COMPILE_ERROR' => "Compile Error",
        'FLOAT_POINT_EXCEPTION' => "Runtime Error",
        'INTERNAL_ERROR' => "Submission Error",
        "MEMORY_LIMIT_EXCEEDED" => "Memory Limit Exceed",
        'MULTIPLE_ERROR' => "Runtime Error",
        'NON_ZERO_EXIT_CODE' => "Runtime Error",
        'NO_ANSWER' => "Compile Error",
        'OUTPUT_LIMIT_EXCEEDED' => "Output Limit Exceeded",
        'OVERRIDDEN' => "Submission Error",
        'PARTIAL_ACCEPTED' => "Partially Accepted",
        "PRESENTATION_ERROR" => "Presentation Error",
        'RUNTIME_ERROR' => "Runtime Error",
        'SAMPLE_ERROR' => "Wrong Answer",
        'SEGMENTATION_FAULT' => "Runtime Error",
        'SKIPPED' => "Submission Error",
        'TIME_LIMIT_EXCEEDED' => "Time Limit Exceed",
        'WRONG_ANSWER' => "Wrong Answer",
    ];

    public function __construct()
    {
        $this->submissionModel = new SubmissionModel();
        $this->contestModel = new ContestModel();
        $this->judgerModel = new JudgerModel();
    }

    public function judge($row)
    {
        try {
            $sub = [];
            $response = $curl->grab_page([
                'site' => "https://pintia.cn/api/submissions/" . $row['remote_id'],
                'oj' => 'pta',
                'headers' => ['Accept: application/json;charset=UTF-8'],
                'handle' => $this->judgerModel->detail($row['jid'])['handle'],
            ]);
            $data = json_decode($response, true);
            if (!isset($this->verdict[$data['submission']['status']])) {
                return;
            }
            $sub['verdict'] = $this->verdict[$data['submission']['status']];
            if ($data['submission']['status'] == 'COMPILE_ERROR') {
                $sub['compile_info'] = $data['submission']['judgeResponseContents'][0]['programmingJudgeResponseContent']['compilationResult']['log'];
            }
            $isOI = $row['cid'] && $this->contestModel->rule($row['cid']) == 2;
            $sub['score'] = $data['submission']['score'];
            if (!$isOI) {
                if ($sub['verdict'] == "Partially Accepted") {
                    $sub['verdict'] = 'Wrong Answer';
                    $sub['score'] = 0;
                }
            }
            $sub['remote_id'] = $row['remote_id'];
            $sub['memory'] = $data['submission']['memory'] / 1024;
            $sub['time'] = $data['submission']['time'] * 1000;

            // $ret[$row['sid']]=[
            //     "verdict"=>$sub['verdict']
            // ];
            $this->submissionModel->updateSubmission($row['sid'], $sub);
        } catch (Exception $e) {
        }
    }
}

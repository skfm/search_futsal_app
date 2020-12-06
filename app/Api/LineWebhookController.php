<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use LINE\LINEBot\SignatureValidator;
use Illuminate\Support\Facades\Log;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\Constant\HTTPHeader;
use App\Services\LineWebhookServices;

class LineWebhookController extends Controller
{

    public function webhook (Request $request)
    {
        $lineAccessToken = env('LINE_ACCESS_TOKEN', "");
        $lineChannelSecret = env('LINE_CHANNEL_SECRET', "");

        $signature = $request->headers->get(HTTPHeader::LINE_SIGNATURE);
        if (!SignatureValidator::validateSignature($request->getContent(), $lineChannelSecret, $signature)) {
            return;
        }

        $httpClient = new CurlHTTPClient ($lineAccessToken);
        $lineBot = new LINEBot($httpClient, ['channelSecret' => $lineChannelSecret]);

        try {
            $events = LineWebhookServices::getEvents($lineBot, $request, $signature);

            foreach ($events as $event) {
                $lineWord = $event->getText();
                $lineWordArr = explode("\n", $lineWord);
                $lineWordArrCount = LineWebhookServices::getLineWordArrCount($lineWordArr);
                $lineWordArr[2] = LineWebhookServices::getFutsalCategory($lineWordArr);

                if ($lineWordArrCount === 3) {
                    $lineBot = LineWebhookServices::judgeReplyMessage($lineWordArr, $event, $lineBot);
                } else {
                    $message_text = "送信した内容にエラーがあります。\n以下のように3行だけで入力してください。\nex)\n20210101(開催日：いつからか)\n20210131(開催日：いつまでか)\nオープン(カテゴリレベル：f-channelに準拠)";
                    $lineBot = LineWebhookServices::getReplyMessage($message_text, $lineBot);
                }
            }

        } catch (Exception $e) {
            Log::debug($e);
        }

        return;
    }
}

<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use LINE\LINEBot\SignatureValidator;
use Illuminate\Support\Facades\Log;
use Weidner\Goutte\GoutteFacade as GoutteFacade;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use LINE\LINEBot\MessageBuilder\LocationMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\AudioMessage;
use LINE\LINEBot\Event\MessageEvent\ImageMessage;
use LINE\LINEBot\Event\MessageEvent\LocationMessage;
use LINE\LINEBot\Event\MessageEvent\StickerMessage;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Event\MessageEvent\UnknownMessage;
use LINE\LINEBot\Event\MessageEvent\VideoMessage;
use LINE\LINEBot\Event\UnfollowEvent;
use LINE\LINEBot\Event\FollowEvent;
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

                    if (preg_match("/20[0-9]{6}/", $lineWordArr[0]) && preg_match("/20[0-9]{6}/", $lineWordArr[1]) && isset($lineWordArr[2])) {
                        $goutte = GoutteFacade::request('GET', "https://www.f-channel.net/search/?q=&g=2&dateFrom=".$lineWordArr[0]."&dateTo=".$lineWordArr[1]."&pref=27&reservation=1&c=".$lineWordArr[2]."#searchResults");

                        $dates = [];
                        $goutte->filter('.topList__outer .topList__table tbody tr td:nth-child(1)')->each(function ($node) use (&$dates) {
                            $dates[] = $node->text();
                        });

                        $datesCount = count($dates);

                        switch ($datesCount){
                            case 0:
                                $message_text = "検索結果が0件でした。日程を変更して再検索して見てください。";
                                $sendMessage = new MultiMessageBuilder();
                                $textMessageBuilder = new TextMessageBuilder($message_text);
                                $sendMessage->add($textMessageBuilder);
                                $lineBot->replyMessage($event->getReplyToken(), $sendMessage);
                                break;

                            case $datesCount > 10:
                                $action = new UriTemplateActionBuilder("11件以上の検索結果", "https://www.f-channel.net/search/");

                                // カルーセルのカラムを作成する
                                $column = new CarouselColumnTemplateBuilder("11件以上の検索結果", "検索結果が全て表示できないです。エフチャンネルのHPより検索してください", null,[$action]);
                                $columns[] = $column;

                                // カラムの配列を組み合わせてカルーセルを作成する
                                $carousel = new CarouselTemplateBuilder($columns);
                                // カルーセルを追加してメッセージを作る
                                $carousel_message = new TemplateMessageBuilder("メッセージのタイトル", $carousel);
                                $lineBot->replyMessage($event->getReplyToken(), $carousel_message);
                                break;

                            default:
                                $venues = [];
                                $goutte->filter('.topList__outer .topList__table tbody tr td:nth-child(3) a')->each(function ($node) use (&$venues) {
                                    $venues[] = $node->text();
                                });

                                $costs = [];
                                $goutte->filter('.topList__outer .topList__table tbody tr td:nth-child(6)')->each(function ($node) use (&$costs) {
                                    $costs[] = $node->text();
                                });

                                $href = [];
                                $goutte->filter('.topList__outer .topList__table tbody tr td:nth-child(4) a')->each(function ($node) use (&$href) {
                                    $href[] = $node->attr("href");
                                });

                                $columns = [];
                                for($i = 0; $i < $datesCount; $i++) {
                                    // カルーセルに付与するボタンを作る
                                    $action = new UriTemplateActionBuilder("詳細を確認", "https://www.f-channel.net".$href[$i]);

                                    // カルーセルのカラムを作成する
                                    $column = new CarouselColumnTemplateBuilder($venues[$i], "開催日時：".$dates[$i]."\n費用：".$costs[$i], null,[$action]);
                                    $columns[] = $column;
                                }

                                // カラムの配列を組み合わせてカルーセルを作成する
                                $carousel = new CarouselTemplateBuilder($columns);
                                // カルーセルを追加してメッセージを作る
                                $carousel_message = new TemplateMessageBuilder("メッセージのタイトル", $carousel);
                                $lineBot->replyMessage($event->getReplyToken(), $carousel_message);
                        }
                      } else {
                        $message_text = "送信した内容にエラーがあります。\n以下のように3行だけで入力してください。\nex)\n20210101(開催日：いつからか)\n20210131(開催日：いつまでか)\nオープン(カテゴリレベル：f-channelに準拠)";
                        $sendMessage = new MultiMessageBuilder();
                        $textMessageBuilder = new TextMessageBuilder($message_text);
                        $sendMessage->add($textMessageBuilder);
                        $lineBot->replyMessage($event->getReplyToken(), $sendMessage);
                      }

                    // Log::debug($dates);

                } else {
                    $message_text = "送信した内容にエラーがあります。\n以下のように3行だけで入力してください。\nex)\n20210101(開催日：いつからか)\n20210131(開催日：いつまでか)\nオープン(カテゴリレベル：f-channelに準拠)";
                    $sendMessage = new MultiMessageBuilder();
                    $textMessageBuilder = new TextMessageBuilder($message_text);
                    $sendMessage->add($textMessageBuilder);
                    $lineBot->replyMessage($event->getReplyToken(), $sendMessage);
                }
            }

        } catch (Exception $e) {
            Log::debug($e);
        }

        return;
    }
}

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

class LineWebhookController extends Controller
{

    public function webhook (Request $request)
    {
        $lineAccessToken = env('LINE_ACCESS_TOKEN', "");
        $lineChannelSecret = env('LINE_CHANNEL_SECRET', "");

        // 署名のチェック
        $signature = $request->headers->get(HTTPHeader::LINE_SIGNATURE);
        if (!SignatureValidator::validateSignature($request->getContent(), $lineChannelSecret, $signature)) {
            // TODO 不正アクセス
            return;
        }

        $httpClient = new CurlHTTPClient ($lineAccessToken);
        $lineBot = new LINEBot($httpClient, ['channelSecret' => $lineChannelSecret]);

        try {
            // イベント取得
            $events = $lineBot->parseEventRequest($request->getContent(), $signature);

            foreach ($events as $event) {

                $lineWord = $event->getText();

                $lineWordArr = explode("\n", $lineWord);

                $lineWordArrCount = count($lineWordArr);

                switch ($lineWordArr[2]){
                    case "F1":
                        $lineWordArr[2] = "0114";
                        break;
                    case "F2":
                        $lineWordArr[2] = "0121";
                        break;
                    case "F3":
                        $lineWordArr[2] = "0122";
                        break;
                    case "F4":
                        $lineWordArr[2] = "0118";
                        break;
                    case "高校生":
                        $lineWordArr[2] = "0119";
                        break;
                    case "学生":
                        $lineWordArr[2] = "0104";
                        break;
                    case "一般":
                        $lineWordArr[2] = "0111";
                        break;
                    case "LADIES":
                        $lineWordArr[2] = "0105";
                        break;
                    case "男女MIX":
                        $lineWordArr[2] = "0106";
                        break;
                    case "オーバー25":
                        $lineWordArr[2] = "0107";
                        break;
                    case "オーバー30":
                        $lineWordArr[2] = "0108";
                        break;
                    case "オーバー35":
                        $lineWordArr[2] = "0113";
                        break;
                    case "オーバー40":
                        $lineWordArr[2] = "0110";
                        break;
                    case "オープン":
                        $lineWordArr[2] = "0120";
                        break;
                    case "エンジョイ":
                        $lineWordArr[2] = "0112";
                        break;
                    case "":
                        $lineWordArr[2] = null;
                        break;
                    default:
                        $lineWordArr[2] = null;
                        break;
                }

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
            // TODO 例外
            return;
        }

        return;
    }
}

<?php

namespace App\Services;

use Weidner\Goutte\GoutteFacade as GoutteFacade;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;

class LineWebhookServices
{
    public static function getEvents($lineBot, $request, $signature)
    {
        $events = $lineBot->parseEventRequest($request->getContent(), $signature);
        return $events;
    }

    public static function getLineWordArrCount($lineWordArr)
    {
        $lineWordArrCount = count($lineWordArr);
        return $lineWordArrCount;
    }

    public static function getFutsalCategory($lineWordArr)
    {
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
        return $lineWordArr[2];
    }

    public static function getReplyMessage($message_text, $lineBot, $event)
    {
        $sendMessage = new MultiMessageBuilder();
        $textMessageBuilder = new TextMessageBuilder($message_text);
        $sendMessage->add($textMessageBuilder);
        $lineBot->replyMessage($event->getReplyToken(), $sendMessage);
        return $lineBot;
    }

    public static function getCarouselReplyMessage($columns, $lineBot, $event)
    {
        $carousel = new CarouselTemplateBuilder($columns);
        $carousel_message = new TemplateMessageBuilder("メッセージのタイトル", $carousel);
        $lineBot->replyMessage($event->getReplyToken(), $carousel_message);
        return $lineBot;
    }

    public static function judgeReplyMessage($lineWordArr, $event, $lineBot)
    {
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
                    LineWebhookServices::getReplyMessage($message_text, $lineBot, $event);
                    break;

                case $datesCount > 10:
                    $action = new UriTemplateActionBuilder("11件以上の検索結果", "https://www.f-channel.net/search/");

                    $column = new CarouselColumnTemplateBuilder("11件以上の検索結果", "検索結果が全て表示できないです。エフチャンネルのHPより検索してください", null,[$action]);
                    $columns[] = $column;

                    LineWebhookServices::getCarouselReplyMessage($columns, $lineBot, $event);
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
                        $action = new UriTemplateActionBuilder("詳細を確認", "https://www.f-channel.net".$href[$i]);

                        $column = new CarouselColumnTemplateBuilder($venues[$i], "開催日時：".$dates[$i]."\n費用：".$costs[$i], null,[$action]);
                        $columns[] = $column;
                    }

                    LineWebhookServices::getCarouselReplyMessage($columns, $lineBot, $event);
                    break;
            }

        } else {
            $message_text = "送信した内容にエラーがあります。\n以下のように3行だけで入力してください。\nex)\n20210101(開催日：いつからか)\n20210131(開催日：いつまでか)\nオープン(カテゴリレベル：f-channelに準拠)";
            LineWebhookServices::getReplyMessage($message_text, $lineBot, $event);
        }
    }
}

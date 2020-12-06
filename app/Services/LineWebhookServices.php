<?php

namespace App\Services;


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
}

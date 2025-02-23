<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
use LogicException;
use Symfony\Component\BrowserKit\HttpBrowser;

class CrawlerService
{
    private $client;

    public function __construct(private string $url)
    {
        $this->client = new HttpBrowser();
    }

    public function build(): array
    {
        $this->client->setMaxRedirects(2);
        $baseUrl = $this->url;
        # $webPageJson= $this->client->request('GET', $baseUrl . 'data.php');
        $stream_context = stream_context_create([
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false
            ],
            "http" => [
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
            ]
        ]);  
        $json = file_get_contents($baseUrl . 'json.php', false, $stream_context);
        $matches = json_decode($json, true);

        # Build db parent level
        $inMemoryDatabase = [];
        $i = 0;
        foreach ($matches as $matchKey => $matchValue) {
            $title = sprintf('%s vs %s', $matchValue['home'], $matchValue['away']);
            $liveStreams = [];

            foreach ($matchValue['streams'] as $streams) {
                $liveStreams[] = [
                    'stream_url' => $baseUrl . 'plyz/2/' . $streams['ch'],
                    'flag' => 'https://www.shareicon.net/data/128x128/2015/08/08/82248_media_16x16.png',
                    'channel_name' => sprintf('%s vs %s', $streams['ch'], $streams['lang'])
                ];
            }

            $datetime = new DateTime();
            $datetime->setTimestamp($matchValue['time'] / 1000);
            $datetime->setTimezone(new DateTimeZone('Indian/Mauritius'));

            $inMemoryDatabase[$i] = [
                'id' => uniqid(),
                'title' => $title,
                'browser_uuid' => $baseUrl . 'live/foot/' . $matchValue['league'] . '/' . $title,
                'sport_name' => $matchValue['type'],
                'sport_flag' => 'https://img.icons8.com/?size=48&id=iU4rpa9QGXp0&format=png',
                'date_time' => $datetime->format('Y/m/d \à H:i \G\M\T'),
                'live' => $liveStreams
            ];
            $i++;
        }

        return $inMemoryDatabase;
    }
}

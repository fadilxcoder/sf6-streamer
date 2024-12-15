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
        $json = file_get_contents($baseUrl . 'data.php');
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

            $inMemoryDatabase[$i] = [
                'id' => uniqid(),
                'title' => $title,
                'browser_uuid' => $baseUrl . 'live/foot/' . $matchValue['league'] . '/' . $title,
                'sport_name' => $matchValue['type'],
                'sport_flag' => 'https://img.icons8.com/?size=48&id=iU4rpa9QGXp0&format=png',
                'date_time' => date('Y/m/d \à H:i', ($matchValue['time'] / 1000)),
                'live' => $liveStreams
            ];
            $i++;
        }

        return $inMemoryDatabase;
    }
}

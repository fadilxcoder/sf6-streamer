<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
use LogicException;
use Symfony\Component\BrowserKit\HttpBrowser;

class CrawlerService
{
    private $client;

    public function __construct()
    {
        $this->client = new HttpBrowser();
    }

    public function build(): array
    {
        $this->client->setMaxRedirects(2);
        $baseUrl = 'https://www.streamonsport.ru/';
        $webPageCrawler = $this->client->request('GET', $baseUrl);

        # Get all links
        $streamsUrls = $webPageCrawler->filter('a.game-name')->each(function ($node) {
            return $node->attr('href');
        });

        # Get all names
        $streamsNames = $webPageCrawler->filter('a.game-name span')->each(function ($node) {
            return $node->text(); // Get name
        });

        # Get all datetime
        $streamsDateTime = $webPageCrawler->filter('.details p.date time')->each(function ($node) {
            $originalTimestamp = $node->attr('data-time'); // Get date
            $originalDateTime = new DateTime($originalTimestamp, new DateTimeZone('Europe/Paris'));
            $originalDateTime->setTimezone(new DateTimeZone('Indian/Mauritius'));

            return $originalDateTime->format('Y/m/d à H:i');
        });

        # Build db parent level
        $inMemoryDatabase = [];
        for ($i = 0; $i < count($streamsUrls); $i++) {
            $inMemoryDatabase[$i] = [
                'id' => uniqid(),
                'title' => $streamsNames[$i],
                'browser_uuid' => $streamsUrls[$i],
                'date_time' => $streamsDateTime[$i],
            ];
        }

        foreach ($inMemoryDatabase as $key => $data) {
            try {
                $eachPagecrawler = $this->client->request('GET', $data['browser_uuid']);

                # Get all child links by parent
                $eachPagestreamUrls = $eachPagecrawler->filter('div#chanel_links a')->each(function ($node) {
                    return $node->attr('onclick');
                });

                 # Get all child flags by parent
                 $eachPagestreamFlags = $eachPagecrawler->filter('div#chanel_links a img')->each(function ($node) {
                     return $node->attr('src');
                 });

                 # Get all child channels name by parent
                 $eachPagestreamChannelNames = $eachPagecrawler->filter('div#chanel_links a')->each(function ($node) {
                     return $node->text();
                 });

            } catch (LogicException $e) {
                $eachPagestreamUrls = $eachPagestreamFlags = $eachPagestreamChannelNames = [];
            }

            $inMemoryDatabase[$key]['live'] = $this->buildStreams($baseUrl, $eachPagestreamUrls, $eachPagestreamFlags, $eachPagestreamChannelNames);
        }

        return $inMemoryDatabase;
    }

    private function buildStreams(string $baseUrl, array $streamUrls, array $streamsFlags, array $streamsChannels): array
    {
        $identifier = [];
        foreach ($streamUrls as $key => $url) :
            # domain filtering
            $explodeArr = explode("'", $url);
            foreach ($explodeArr as $array)  :
                if (preg_match('#/#', $array)) {
                    if (preg_match('#https#', $array))
                    {
                        $uriFormat = explode("id=", $array);
                        foreach ($uriFormat as $uri) {
                            if (str_contains($uri, 'https')) {
                                $identifier[$key]['stream_url'] = $uri;
                            }
                        }
                    } else {
                        $identifier[$key]['stream_url'] = $baseUrl.$array;
                    }
                    $identifier[$key]['flag'] =  $baseUrl.$streamsFlags[$key]; # flag mapping
                    $identifier[$key]['channel_name'] =  $streamsChannels[$key]; # channel mapping
                }
            endforeach;
        endforeach;

        return $identifier;
    }
}

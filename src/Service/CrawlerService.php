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
        $webPageCrawler = $this->client->request('GET', $baseUrl);

        # Get all links
        $streamsUrls = $webPageCrawler->filter('a.game-name')->each(function ($node) {
            return $node->attr('href');
        });

        # Get all names
        $streamsNames = $webPageCrawler->filter('a.game-name span')->each(function ($node) {
            return $node->text();
        });

        # Get all datetime
        $streamsDateTime = $webPageCrawler->filter('.details p.houre time')->each(function ($node) {
            $originalTimestamp = $node->attr('data-time'); // Get date
            $originalDateTime = new DateTime($originalTimestamp, new DateTimeZone('Europe/Paris'));
            $originalDateTime->setTimezone(new DateTimeZone('Indian/Mauritius'));

            return $originalDateTime->format('Y/m/d à H:i');
        });

        # Get all sports name
        $streamsSportsName = $webPageCrawler->filter('p.houre')->each(function ($node) {
            return $node->text();
        });

        # Get all sports flag
        $streamsSportsFlag = $webPageCrawler->filter('img.mascot')->each(function ($node) {
            return $node->attr('src');
        });

        # Build db parent level
        $inMemoryDatabase = [];
        for ($i = 0; $i < count($streamsUrls); $i++) {
            $inMemoryDatabase[$i] = [
                'id' => uniqid(),
                'title' => $streamsNames[$i],
                'browser_uuid' => $streamsUrls[$i],
                'sport_name' => $streamsSportsName[$i],
                'sport_flag' => '', //$streamsSportsFlag[$i],
                'date_time' => $streamsDateTime[$i],
            ];
        }

        foreach ($inMemoryDatabase as $key => $data) {
            try {
                $eachPagecrawler = $this->client->request('GET', $data['browser_uuid']);

                # Get all child links by parent
                $eachPagestreamUrls = $eachPagecrawler->filter('div#kakarotvideo div.servideo span.change-video')->each(function ($node) {
                    return $node->attr('data-embed');
                });

                # Get all child flags by parent
                $eachPagestreamFlags = $eachPagecrawler->filter('div#kakarotvideo div.servideo span.change-video img')->each(function ($node) {
                    return $node->attr('src');
                });

                # Get all child channels name by parent
                $eachPagestreamChannelNames = $eachPagecrawler->filter('div#kakarotvideo div.servideo span.change-video')->each(function ($node) {
                    return trim($node->text()); // Remove extra whitespace
                });

            } catch (LogicException $e) {
                $eachPagestreamUrls = $eachPagestreamFlags = $eachPagestreamChannelNames = [];
            }

            $inMemoryDatabase[$key]['live'] = $this->buildStreamChannels($baseUrl, $eachPagestreamUrls, $eachPagestreamFlags, $eachPagestreamChannelNames);
        }

        return $inMemoryDatabase;
    }

    private function buildStreamChannels(string $baseUrl, array $streamUrls, array $streamsFlags, array $streamsChannels): array
    {
        $identifier = [];
        foreach ($streamUrls as $key => $url) :
            if (empty($url)) continue;
            $fullUrl = str_starts_with($url, 'http') ? $url : $baseUrl . $url;
            $identifier[$key]['stream_url'] = $fullUrl;
            $identifier[$key]['flag'] = $streamsFlags[$key] ?? '';
            $identifier[$key]['channel_name'] = $streamsChannels[$key] ?? '';
        endforeach;

        return $identifier;
    }
}

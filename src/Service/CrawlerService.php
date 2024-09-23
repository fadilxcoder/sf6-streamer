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
        $matches = [];
        $webPageCrawler->filter('ul#events > li')->each(function ($node) use (&$matches) {
            $matchTitle = trim($node->filter('a')->first()->text());
            $streamUrls = $node->filter('ul li.subitem1 a')->each(function ($streamNode) {
                return $streamNode->attr('href');
            });
        
            $matches[$matchTitle] = $streamUrls;
        });

        # Build db parent level
        $inMemoryDatabase = [];
        $i = 0;
        foreach ($matches as $matchName => $matchUrl) {
            $inMemoryDatabase[$i] = [
                'id' => uniqid(),
                'title' => $matchName,
                'browser_uuid' => $matchUrl,
                'sport_name' => '-',
                'sport_flag' => 'https://img.icons8.com/?size=48&id=iU4rpa9QGXp0&format=png',
                'date_time' => "0000/00/00 à 00:00",
            ];
            $i++;
        }

        foreach ($inMemoryDatabase as $key => $data) {
            foreach ($data['browser_uuid'] as $linkKey => $linkValue) {
                $isoCode = substr($linkValue, -2);
                $eachPagecrawler = $this->client->request('GET', $linkValue);

                # Extract the <script> block that contains the "videos" variable
                $scriptTag = $eachPagecrawler->filter('script')->each(function ($node) {
                    if (strpos($node->text(), 'var videos =') !== false) {
                        return $node->text();
                    }
                });

                # Look for the script that contains the "videos" variable
                foreach ($scriptTag as $script) {
                    if ($script) {
                        $scriptContent = $script;
                        break;
                    }
                }

                # Check if we found the "videos" variable
                if ($scriptContent && preg_match('/var videos = (.*?);/', $scriptContent, $matches)) {

                    # Replace single quotes with double quotes
                    $jsonString = str_replace("'", '"', $matches[1]);

                    #  Remove trailing commas before the closing brackets using a regular expression
                    $jsonString = preg_replace('/,(\s*[}\]])/', '$1', $jsonString);
                    $videosArray = json_decode($jsonString, true);

                    if (isset($videosArray['SUB'])) {
                        foreach ($videosArray['SUB'] as $video) {
                            $inMemoryDatabase[$key]['live'][] = [
                                'stream_url' => $video['code'],
                                'flag' => 'https://www.shareicon.net/data/128x128/2015/08/08/82248_media_16x16.png',
                                'channel_name' => strtoupper($isoCode) . ' - ' . $video['server'],
                            ];
                        }

                    }
                }
            }

        }

        return $inMemoryDatabase;
    }
}

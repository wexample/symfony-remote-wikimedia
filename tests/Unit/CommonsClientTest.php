<?php

namespace Wexample\SymfonyRemoteWikimedia\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\SymfonyRemoteWikimedia\Service\CommonsClient;

class CommonsClientTest extends TestCase
{
    private function client(MockHttpClient $httpClient): CommonsClient
    {
        return new CommonsClient($httpClient, new Filesystem(), 'tests/1.0', 'https://commons.example/w/api.php', 2, 0);
    }

    /**
     * The API's answer for a file it has, under its own spelling, and one it does not.
     */
    private function answer(): MockResponse
    {
        return new MockResponse(json_encode(['batchcomplete' => true, 'query' => [
            'normalized' => [
                ['from' => 'File:Emmanuel_Macron_(cropped).jpg', 'to' => 'File:Emmanuel Macron (cropped).jpg'],
            ],
            'pages' => [
                ['ns' => 6, 'title' => 'File:Missing.jpg', 'missing' => true],
                ['ns' => 6, 'title' => 'File:Emmanuel Macron (cropped).jpg', 'imageinfo' => [[
                    'size' => 148114,
                    'width' => 521,
                    'height' => 745,
                    'thumburl' => 'https://upload.example/thumb/500px-Emmanuel_Macron.jpg?utm_source=commons.wikimedia.org&utm_content=thumbnail',
                    'thumbwidth' => 480,
                    'thumbheight' => 686,
                    'url' => 'https://upload.example/Emmanuel_Macron.jpg?utm_source=commons.wikimedia.org&utm_content=original',
                    'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Emmanuel_Macron_(cropped).jpg',
                    'mime' => 'image/jpeg',
                    'extmetadata' => [
                        'Artist' => ['value' => '<a rel="nofollow" href="https://www.flickr.com/people/1">Arno Mikkor,  EU2017EE</a>'],
                        'LicenseShortName' => ['value' => 'CC BY 2.0'],
                        'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by/2.0'],
                    ],
                ]]],
            ],
        ]]));
    }

    public function testFilesAreDescribedUnderTheNameAskedFor(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$url, $options['normalized_headers']['user-agent'][0] ?? null];

            return $this->answer();
        });

        $files = $this->client($httpClient)->getFiles(['Emmanuel_Macron_(cropped).jpg', 'Missing.jpg'], 480);

        $this->assertSame(['Emmanuel_Macron_(cropped).jpg'], array_keys($files));
        $file = $files['Emmanuel_Macron_(cropped).jpg'];
        $this->assertSame('Emmanuel Macron (cropped).jpg', $file->name);
        $this->assertSame('https://upload.example/Emmanuel_Macron.jpg', $file->url);
        $this->assertSame('https://upload.example/thumb/500px-Emmanuel_Macron.jpg', $file->thumbnailUrl);
        $this->assertSame('Arno Mikkor, EU2017EE', $file->author);
        $this->assertSame('CC BY 2.0', $file->license);
        $this->assertSame('https://creativecommons.org/licenses/by/2.0', $file->licenseUrl);
        $this->assertSame('User-Agent: tests/1.0', $requests[0][1]);
    }

    public function testFiftyFilesAtMostARequest(): void
    {
        $count = 0;
        $httpClient = new MockHttpClient(function () use (&$count): MockResponse {
            ++$count;

            return new MockResponse('{"query": {"pages": []}}');
        });

        $this->client($httpClient)->getFiles(array_map(static fn (int $index): string => 'File '.$index.'.jpg', range(1, 120)), 480);

        $this->assertSame(3, $count);
    }

    public function testAnErrorOfTheApiIsARemoteFailure(): void
    {
        $this->expectException(RemoteException::class);

        $this->client(new MockHttpClient(array_fill(0, 2, new MockResponse('{"error": {"code": "ratelimited", "info": "Slow down."}}'))))->getFiles(['A.jpg'], 480);
    }

    public function testADownloadIsTriedAgainThenFails(): void
    {
        $path = sys_get_temp_dir().'/commons-tests-'.bin2hex(random_bytes(4)).'/a.jpg';
        $client = $this->client(new MockHttpClient([new MockResponse('', ['http_code' => 503]), new MockResponse('jpeg')]));

        $client->download('https://upload.example/a.jpg', $path);
        $this->assertSame('jpeg', file_get_contents($path));
        (new Filesystem())->remove(dirname($path));

        $this->expectException(RemoteException::class);
        $this->client(new MockHttpClient(array_fill(0, 2, new MockResponse('', ['http_code' => 503]))))->download('https://upload.example/a.jpg', $path);
    }

    public function testTheNameOfAWikidataAddress(): void
    {
        $this->assertSame('Emmanuel Macron (cropped).jpg', CommonsClient::getFileName('http://commons.wikimedia.org/wiki/Special:FilePath/Emmanuel%20Macron%20%28cropped%29.jpg'));
    }

    public function testALimitedRateIsWaitedForAsWikimediaAsks(): void
    {
        $client = $this->client(new MockHttpClient([
            new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After' => '0']]),
            $this->answer(),
        ]));

        $this->assertCount(1, $client->getFiles(['Emmanuel_Macron_(cropped).jpg'], 480));
    }
}

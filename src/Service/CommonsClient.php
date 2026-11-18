<?php

namespace Wexample\SymfonyRemoteWikimedia\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Wexample\PhpRemote\Class\RemoteStatus;
use Wexample\PhpRemote\Exception\RemoteException;
use Wexample\PhpRemote\Interface\RemoteInterface;
use Wexample\SymfonyRemoteWikimedia\Class\CommonsFile;

/**
 * Wikimedia Commons, through its API: what files are — fifty a request, the most
 * it answers at once — and their download.
 *
 * Wikimedia turns away clients that do not say who they are: every request carries
 * the configured user agent. A request failing is tried again a few times — after
 * the delay Wikimedia asks for when it limits the rate —, then raised as a
 * RemoteException.
 */
class CommonsClient implements RemoteInterface
{
    public const string KEY = 'wikimedia_commons';

    public const string DEFAULT_API_URL = 'https://commons.wikimedia.org/w/api.php';

    /** The most titles the API reads in one request. */
    private const int BATCH = 50;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $filesystem,
        private readonly string $userAgent,
        private readonly string $apiUrl = self::DEFAULT_API_URL,
        private readonly int $attempts = 3,
        private readonly int $retryDelay = 5,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'Wikimedia Commons';
    }

    public function checkStatus(): RemoteStatus
    {
        $this->request(['action' => 'query', 'meta' => 'siteinfo']);

        return RemoteStatus::up();
    }

    /**
     * The file a Commons address names, as Wikidata gives one (`http://commons.wikimedia.org/wiki/Special:FilePath/Emmanuel%20Macron.jpg`).
     */
    public static function getFileName(string $url): string
    {
        return str_replace('_', ' ', rawurldecode(basename(parse_url($url, PHP_URL_PATH) ?? $url)));
    }

    /**
     * @param list<string> $names the files, without the `File:` namespace
     * @param int          $width the thumbnail's width
     *
     * @return array<string, CommonsFile> by the name asked for; a file Commons does not have is left out
     */
    public function getFiles(
        array $names,
        int $width
    ): array {
        $files = [];

        foreach (array_chunk(array_values(array_unique($names)), self::BATCH) as $batch) {
            $answer = $this->request([
                'action' => 'query',
                'titles' => implode('|', array_map(static fn (string $name): string => 'File:'.$name, $batch)),
                'prop' => 'imageinfo',
                'iiprop' => 'url|size|mime|extmetadata',
                'iiurlwidth' => $width,
                'iiextmetadatafilter' => 'Artist|LicenseShortName|LicenseUrl',
            ]);

            // The API answers under its own spelling of a title — spaces for
            // underscores, a capital first —: the name asked for is found again through it.
            $asked = array_combine(array_map(static fn (string $name): string => 'File:'.$name, $batch), $batch);

            foreach ($answer['query']['normalized'] ?? [] as $normalized) {
                $asked[$normalized['to']] = $asked[$normalized['from']] ?? $normalized['to'];
            }

            foreach ($answer['query']['pages'] ?? [] as $page) {
                $info = $page['imageinfo'][0] ?? null;

                if (null === $info || isset($page['missing'])) {
                    continue;
                }

                $name = $asked[$page['title']] ?? substr($page['title'], strlen('File:'));
                $files[$name] = $this->readFile(substr($page['title'], strlen('File:')), $info);
            }
        }

        return $files;
    }

    /**
     * Streamed to $path.
     */
    public function download(
        string $url,
        string $path
    ): void {
        $this->filesystem->mkdir(dirname($path));

        for ($attempt = 1; ; ++$attempt) {
            try {
                $response = $this->httpClient->request('GET', $url, ['headers' => ['User-Agent' => $this->userAgent], 'timeout' => 60]);
                $file = fopen($path, 'w');

                foreach ($this->httpClient->stream($response) as $chunk) {
                    fwrite($file, $chunk->getContent());
                }

                fclose($file);

                return;
            } catch (ExceptionInterface $exception) {
                $this->filesystem->remove($path);

                if ($attempt >= $this->attempts) {
                    throw new RemoteException(self::KEY, sprintf('Unable to download "%s": %s', $url, $exception->getMessage()), $exception);
                }

                sleep($this->getRetryDelay($exception, $attempt));
            }
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    private function readFile(
        string $name,
        array $info
    ): CommonsFile {
        $metadata = $info['extmetadata'] ?? [];
        $text = static fn (string $key): ?string => '' !== ($value = trim(html_entity_decode(strip_tags((string) ($metadata[$key]['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            ? preg_replace('/\s+/u', ' ', $value)
            : null;

        return new CommonsFile(
            $name,
            $this->withoutTracking($info['url']),
            isset($info['thumburl']) && ($info['thumbwidth'] ?? 0) < ($info['width'] ?? 0) ? $this->withoutTracking($info['thumburl']) : null,
            (int) ($info['width'] ?? 0),
            (int) ($info['height'] ?? 0),
            (string) ($info['mime'] ?? 'application/octet-stream'),
            $info['descriptionurl'],
            $text('Artist'),
            $text('LicenseShortName'),
            $text('LicenseUrl'),
        );
    }

    /**
     * The addresses come with `utm_*` parameters, for Wikimedia's statistics: nothing a download needs.
     */
    private function withoutTracking(string $url): string
    {
        return preg_replace('/\?utm_[^#]*$/', '', $url);
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    private function request(array $query): array
    {
        for ($attempt = 1; ; ++$attempt) {
            try {
                $answer = $this->httpClient->request('GET', $this->apiUrl, [
                    'query' => $query + ['format' => 'json', 'formatversion' => 2],
                    'headers' => ['User-Agent' => $this->userAgent],
                    'timeout' => 60,
                ])->toArray();

                if (isset($answer['error'])) {
                    throw new RemoteException(self::KEY, sprintf('Commons answered an error: %s', $answer['error']['info'] ?? $answer['error']['code'] ?? 'unknown'));
                }

                return $answer;
            } catch (ExceptionInterface|RemoteException $exception) {
                if ($attempt >= $this->attempts) {
                    throw $exception instanceof RemoteException ? $exception : new RemoteException(self::KEY, $exception->getMessage(), $exception);
                }

                sleep($this->getRetryDelay($exception, $attempt));
            }
        }
    }

    /**
     * How long to wait before asking again: what Wikimedia says when it limits the
     * rate (429, `Retry-After`), a minute at most; the configured delay otherwise.
     */
    private function getRetryDelay(
        ExceptionInterface|RemoteException $exception,
        int $attempt
    ): int {
        if ($exception instanceof HttpExceptionInterface && 429 === $exception->getResponse()->getStatusCode()) {
            $retryAfter = $exception->getResponse()->getHeaders(false)['retry-after'][0] ?? null;

            return min(60, is_numeric($retryAfter) ? (int) $retryAfter : $attempt * max(1, $this->retryDelay) * 3);
        }

        return $attempt * $this->retryDelay;
    }
}

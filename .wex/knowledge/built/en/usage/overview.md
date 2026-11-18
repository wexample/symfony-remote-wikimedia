## Describing files

```php
public function __construct(private CommonsClient $commons) {}

// The names Wikidata gives as addresses: `http://commons.wikimedia.org/wiki/Special:FilePath/Emmanuel%20Macron.jpg`.
$name = CommonsClient::getFileName($row['image']);

$files = $this->commons->getFiles([$name, …], 480);   // fifty a request, as many requests as it takes
$file = $files[$name] ?? null;                       // a file Commons does not have is left out

$file->url;            // the original
$file->thumbnailUrl;   // 480 pixels wide, or null when the original is not wider
$file->author;         // plain text: « Arno Mikkor, EU2017EE Estonian Presidency »
$file->license;        // « CC BY 2.0 »
$file->licenseUrl;
$file->descriptionUrl; // its page, the source to credit
```

The files come back under the names asked for, whatever spelling the API answers with (spaces for underscores, a capital first). The addresses lose the `utm_*` parameters Wikimedia adds for its statistics.

## Downloading

```php
$this->commons->download($file->thumbnailUrl ?? $file->url, $path);   // streamed to disk
```

## Configuration

```yaml
wexample_symfony_remote_wikimedia:
  user_agent: 'my-app/1.0'
  commons:
    api_url: 'https://commons.wikimedia.org/w/api.php'
    attempts: 3
    retry_delay: 5      # seconds, times the attempt number
```

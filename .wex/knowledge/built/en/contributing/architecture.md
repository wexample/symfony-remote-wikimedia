## Architecture

Under `Wexample\SymfonyRemoteWikimedia\` (PSR-4 on `src/`):

- src/Service/CommonsClient.php, the remote: `getFiles()` asks the API's `imageinfo` (`url|size|mime|extmetadata`, the thumbnail through `iiurlwidth`) by batches of fifty titles, the API's limit, and finds each answer back under the name asked for through the API's `normalized` list. The author and the license come from `extmetadata` (`Artist`, `LicenseShortName`, `LicenseUrl`), HTML stripped. `download()` streams a file to disk. Every request carries the configured user agent; transport errors, error statuses and an API `error` are tried again — after the `Retry-After` Wikimedia sends with a 429, a minute at most —, then raised as `RemoteException`.
- src/Class/CommonsFile.php, the readonly value of a file.

### Tests

Unit tests on Symfony's `MockHttpClient`, with no delay between attempts: a file described under the name asked for and one missing, the batches, an API error, a limited rate waited for, a download retried then failing, a Wikidata address read as a name.

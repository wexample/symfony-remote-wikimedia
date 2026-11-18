# symfony-remote-wikimedia

Version: 2.0.0

Wikimedia Commons as a symfony-remote: what a file is — its address, a thumbnail, its author and license — and its download

## Table of Contents

- [Installation](#installation)
- [Introduction](#introduction)

## Installation

```php
// config/bundles.php
Wexample\SymfonyRemote\WexampleSymfonyRemoteBundle::class => ['all' => true],
Wexample\SymfonyRemoteWikimedia\WexampleSymfonyRemoteWikimediaBundle::class => ['all' => true],
```

Name the app in `user_agent`: Wikimedia turns away clients that do not say who they are.

```yaml
# config/packages/wexample_symfony_remote_wikimedia.yaml
wexample_symfony_remote_wikimedia:
  user_agent: 'my-app/1.0'
```

`symfony-remote-wikimedia` reads Wikimedia Commons from a Symfony app: what a file is — its original, a thumbnail of the width asked for, its size and type, its page — and what is owed to whoever made it — the author, the license and its address —, then its download. The images Wikidata gives (P18, a portrait; P154, a logo) are Commons files: this is how an app takes them in, credit included.

`CommonsClient` is a `symfony-remote`, so `remote:status` checks the API. A request failing is tried again a few times, then raised as php-remote's `RemoteException`: run the calls through `RemoteGuard` where the app must go on without them.

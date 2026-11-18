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

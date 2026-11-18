<?php

namespace Wexample\SymfonyRemoteWikimedia\Class;

/**
 * A file of Wikimedia Commons, as its page describes it: where it is, a
 * thumbnail of the width asked for, and what is owed to whoever made it.
 */
final readonly class CommonsFile
{
    /**
     * @param string      $name           its name, without the `File:` namespace: `Emmanuel Macron (cropped).jpg`
     * @param string      $url            the original
     * @param string|null $thumbnailUrl   at the width asked for, when the file is wider; null otherwise
     * @param string      $descriptionUrl its page, the source to credit
     * @param string|null $author         as plain text, the markup Commons gives it in removed
     * @param string|null $license        its short name: `CC BY 2.0`, `Public domain`
     */
    public function __construct(
        public string $name,
        public string $url,
        public ?string $thumbnailUrl,
        public int $width,
        public int $height,
        public string $mimeType,
        public string $descriptionUrl,
        public ?string $author,
        public ?string $license,
        public ?string $licenseUrl,
    ) {
    }
}

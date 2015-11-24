Elgg Filestore Proxy
====================
![Elgg 1.11](https://img.shields.io/badge/Elgg-1.11.x-orange.svg?style=flat-square)
![Elgg 1.12](https://img.shields.io/badge/Elgg-1.12.x-orange.svg?style=flat-square)

Proxy for serving files from Elgg's filestore

## Features

 * API for serving files from Elgg's filestore
 * Minimal engine boot and caching
 * HMAC-based security layer


## Install

```sh
composer require hypejunction/proxy
```

## Usage


### Generate a download link

```php

// Get a link to download a file
// By default, link's validity is limited to 2 hours and restricted to current user session

$file = get_entity($file_guid);
$download_link = elgg_get_download_url($file);
```

### Display an image/thumb file

```php

// Get a link to display an icon
// By default, link's validity is limited to 1 year and can be reused outside of the current user session

$icon = new ElggFile();
$icon->owner_guid = $owner_guid;
$icon->setFilename("path/to/icon.jpg");

$icon_link = elgg_get_inline_url($icon);
```


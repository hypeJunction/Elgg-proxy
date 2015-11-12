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

// Get a download link valid for 1 hour for current user only
$file = get_entity($file_guid);
$download_link = elgg_proxy_get_url($file, 3600, 'attachment', false);
```

### Display an image/thumb file

```php

// Get a link to display an icon that does not expire and will persist accross sessions
$icon = new ElggFile();
$icon->owner_guid = $owner_guid;
$icon->setFilename("path/to/icon.jpg");

$icon_link = elgg_proxy_get_url($icon, 0, 'inline', true);
```


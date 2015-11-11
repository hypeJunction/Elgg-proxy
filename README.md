Elgg Filestore Proxy
====================
![Elgg 1.11](https://img.shields.io/badge/Elgg-1.11.x-orange.svg?style=flat-square)
![Elgg 1.12](https://img.shields.io/badge/Elgg-1.12.x-orange.svg?style=flat-square)

Proxy for serving files from Elgg's filestore

## Features

 * API for serving files from Elgg's filestore
 * Server that does not boot the engine
 * HMAC-based security layer


## Install

```sh
composer require hypejunction/proxy
```

## Usage


### Generate a download link

```php

$file = get_entity($file_guid);
$download_link = elgg_proxy_get_url($file, 0, 'attachment');
```

### Display an image/thumb file

```php

$icon = new ElggFile();
$icon->owner_guid = $owner_guid;
$icon->setFilename("path/to/icon.jpg");

$icon_link = elgg_proxy_get_url($icon, 1000, 'inline');
```


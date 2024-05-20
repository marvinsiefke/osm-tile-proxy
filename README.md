# osm-tile-proxy
osm-tile-proxy is a smart and lightweight proxy with cache for OpenStreetMap tileservers.

## Usage
This script runs even on shared hosters. It does not have any specific requirements apart any newer PHP version is needed. The rewrite rules in the .htaccess file adapt the typical url schema for tileservers on this proxy: https://proxy.domain.com/{z}/{x}/{y}.png

## Features
- Simple file caching
- IP and session based rate limiting
- Privacy friendly handling
- Error logging (if PHP Error logs are enabled)

## Configuration

According to the Terms of Use of the OpenStreetMap.org Tileserver it is strongly recommended to provide a email address of the administrator in the config.php. You can also define trusted hosts.

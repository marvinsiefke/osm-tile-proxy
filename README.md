# pepper-osmproxy
pepper-osmproxy is a smart and lightweight proxy with caching for OpenStreetMap tile servers.

## Usage
This script runs even on shared hosting environments. It does not have any specific requirements other than needing a newer PHP version. The rewrite rules in the .htaccess file adapt the typical URL schema for tile servers on this proxy: https://proxy.domain.com/{z}/{x}/{y}.png

## Features
- Simple file caching
- IP and session based rate limiting
- Privacy friendly handling
- Error logging (if PHP Error logs are enabled)

## Configuration

According to the ToS of the openstreetmap.org tile server, it is strongly recommended to provide the email address of the administrator in the config.php. You can also define trusted hosts.

### tileProxy Class Options
- **`$operator` Required according to the ToS of the openstreetmap.org Tileserver:** The email address of the administrator.
- `$trustedHosts` An array of domains that are allowed as referrers. Default: empty (all referrers allowed).
- `$serverTtl` The time to live of the tiles in the file cache of the web server. Default: 86400 * 31 seconds (31 days).
- `$browserTtl` The time to live of the tiles in the browser cache. Default: 86400 * 7 sec. (7 days).
- `$tileserver` The url of the tiles in this format: `https://tile.openstreetmap.org/{z}/{x}/{y}.png` (also default).
- `$storage` The directory of the file cache. Default: `cache/`

### rateLimiter Class Options
- `$durationInterval` The interval during which the hits are counted. Default: 60 seconds.
- `$durationHardBan` The duration for any hard bans, e.g., in case of too many soft bans or invalid arguments. Default: 21600 seconds (6 hours).
- `$maxHits` The allowed number of provided tiles per interval. Default: 500 hits.
- `$maxSoftBans` The allowed number of soft bans. Default: 20 soft bans.

## Planned features
- Vector tile support when the tileservers are ready :) According to the official osm blog it's already the year of the OpenStreetMap vector maps, so let´s see...

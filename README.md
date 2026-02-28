Open Source Bike Share
============
*The world's first low-cost and open source bike sharing system.*

Bottom-up bicycle sharing system suitable for smaller communities or areas such as campuses, companies etc.

Website: [OpenSourceBikeShare.com](https://whitebikes.info/)

Features
----------
* Web app (mobile-friendly)
* Optional SMS system to rent and return bicycles
* Optional QR code system to rent and return bicycles
* Web map with geolocation and availability of the bicycle stands
* Optional credit system for paid rentals
* Registration form for the new users
* Admin to edit users, create stands or change bicycle availability
* Google Analytics enabled for stats on web and bike usage
* Connector system to support any provider of SMS Gateway / API
* ~~Easy web install to launch system~~

Where is it working?
---------
Bikesharing system works in main city Bratislava.
Previously worked in city Kezmarok Slovakia.
~~3 bike sharing systems in 2 cities.~~

### Pilot project
Currently running with about 80 bicycles in Bratislava, the capital of Slovakia.

The bicycles in the bike share (featuring four digit code U-locks)
![The bicycles in the bike share](http://whitebikes.info/img/u-lock.png "Bicycles")
One of the stands
![One of the stand of the open source bicycle sharing system](http://whitebikes.info/stands/MAINSQ.jpg "The bicycles at one of the stands")
Another stand with some bicycles
![One of the stand of the free bicycle sharing system](http://whitebikes.info/stands/OLDMARKET.jpg "Another stand with the bicycles")

### Cycling Faculty
A faculty of a local university provides 8 bicycles for their students with three stands - the university and two different student housing locations in the city.

Play video to see it in action:
[![Video of Cycling Faculty](https://cloud.githubusercontent.com/assets/8550349/5429137/281c4e54-83e1-11e4-8f7d-8780eb1a59c6.jpg)](http://youtu.be/WDCRNr_xXTY?t=40s)

![Web of the university bicycle share for students](https://cloud.githubusercontent.com/assets/8550349/5425915/ee90a994-832e-11e4-806e-a7e17242594d.png "Cycling Faculty student bicycle share")

Demo
---------
[Whitebikes](http://whitebikes.info) - do not share your location if you are out of Bratislava, otherwise map will jump out of the bike share area.

How does it work?
---------
* No special bicycles required, any usable (mid-sized frame) bicycles will do
* No fixed stands required, stand positions are just marked for visibility
* Checks and balances included to prevent system abuse
* Free for all or charge users for rental time

Launch your own bike sharing system!
---------
**If you need help to set up your own bike sharing system** including the real world part (the stands, bicycles, locks etc.), **we are available for consultation**.

We will talk to you about the expectations, situation, bicycle theft, potential users and **provide you with help to launch your own successful bike sharing system**.

First consultation is free, **get in touch**: [consult@whitebikes.info](mailto:consult@whitebikes.info)

Follow us on Twitter: [@OpenBikeShare](https://twitter.com/OpenBikeshare)

API v1
---------
The project now exposes versioned API endpoints under `/api/v1` with:

* JWT access tokens + refresh tokens (`/api/v1/auth/token`, `/api/v1/auth/refresh`)
* unified success envelopes: `{ "data": ..., "meta": ... }`
* unified error format: `application/problem+json`
* JWT key rotation support via `API_JWT_ACTIVE_KID` + `API_JWT_KEYS`

An OpenAPI contract is available in `openapi.yaml`.

JWT key rotation (`v1` -> `v2`)
---------
If `API_JWT_KEYS={}`, the service uses `APP_SECRET` for the active `kid`.

1. Generate a new secret (at least 32 bytes).
2. Deploy config that starts signing with `v2` but still verifies `v1`.

```
API_JWT_ACTIVE_KID=v2
API_JWT_KEYS={"v1":"old-secret","v2":"new-secret"}
```

3. Wait at least `API_JWT_REFRESH_TTL` so old refresh tokens expire.
4. Remove `v1` from verification keys.

```
API_JWT_ACTIVE_KID=v2
API_JWT_KEYS={"v2":"new-secret"}
```

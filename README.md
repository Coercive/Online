Coercive Online Utility
=======================

Get websine online information by url.

Get
---
```
composer require coercive/online
```

Usage
-----

```php
use Coercive\Utility\Online;


$sUrl = 'my.web.site.com';

Online::create($sUrl);

$oIsOnline = Online::get($sUrl);
if($oIsOnline) {
	echo "ip : " . $oIsOnline->ip() . "<br />";
	echo "isRedirect : " .$oIsOnline->isRedirect() . "<br />";
	echo "redirectUrl : " .$oIsOnline->redirectUrl() . "<br />";
	echo "time : " .$oIsOnline->time() . "<br />";
	echo "url : " .$oIsOnline->url() . "<br />";
}

```

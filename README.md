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

# Website list to test
$aWebSites = [
	'my.web.site-1.com',
	'my.web.site-2.com',
	'my.web.site-3.com',
	'my.web.site-4.com'
];

# Add your urls to Online list
foreach($aWebSites as $iKey = $sUrl) {
	# Url can be processed ('/' and space trim)
	$aWebSites[$iKey] = Online::create($sUrl);
}

# Run all curl requests
Online::run();

# Get datas
foreach($aWebSites as $sUrl) {
	$oOnline = Online::get($sUrl);
	if($oOnline) {
		echo "ip : " . $oOnline->ip() . "<br />";
		echo "isRedirect : " .$oOnline->isRedirect() . "<br />";
		echo "redirectUrl : " .$oOnline->redirectUrl() . "<br />";
		echo "time : " .$oOnline->time() . "<br />";
		echo "url : " .$oOnline->url() . "<br />";
	}
}


```

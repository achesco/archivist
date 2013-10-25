Archivist here
==============
This is old and simple (yet still works), single-threaded tool for predefined pages set collection. This is NOT crawler. Primary usage purpose - creating local working copy of web-pages (and pass it to customers).

Example `www/params.php`
=========

```php
  $params = array(
    'rootUrl' => 'http://dev.localhost/',
      'pagesList' => array(
        '/',
        '404.html',
        'contact.html',
        '/page/url/',
      ),
    'gzipped' => false,
  );
```

`> php archivist.php`

Warning: some complex external scripts like google analytics, facebook widgets, etc. will not work with local copy. This isn't bug, that's just the way it is.

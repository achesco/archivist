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
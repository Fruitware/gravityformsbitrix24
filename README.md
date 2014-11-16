gravityformsbitrix24
====================

Installation
------------

1. Edit your `composer.json` and add:

```json
{
    "require": {
        "lusitanian/oauth": "~0.3"
    }
}
```

2. Install dependencies:

```bash
$ curl -sS https://getcomposer.org/installer | php
$ php composer.phar install
```

3. Add into function.php in your WP theme

```php
	add_action('init', 'vendor_autoload', 1);

	function vendor_autoload()
	{
		require_once get_home_path().'/vendor/autoload.php';
	}
```

4. Activate plugin in admin panel

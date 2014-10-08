yii2-cart
=========

Yii2 component for abandoned shopping carts

Installation
------------

add to composer.json in require sections
```
"vesna/yii2-cart": "*"
```

and to repositories section 
```
{
    "type": "git",
    "url": "git@gitlab.vesna.kz:vesna/yii2-config.git"
}
```
to the require section of your `composer.json` file.


Configure application to use this module

```php
return [
	'modules' => [
		'config' => [
			'class' => 'vesna\config\Module',
		]
		...
	],
];
```
Run migrations 

```
yii migrate --migrationPath=@vesna/config/migrations
```


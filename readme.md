## elFinder Flysystem Driver
> This package adds a VolumeDriver for elFinder to use Flysystem as a root in your system. You need to have elFinder 2.1 installed.
> You can download the source or nightlies from https://github.com/Studio-42/elFinder or use the Laravel version: https://github.com/barryvdh/laravel-elfinder

Require this package in your composer.json and update composer. 

    composer require barryvdh/elfinder-flysystem-driver
    
This will require Flysystem, but you might need additional adapters to fit your purpose. 
See https://github.com/thephpleague/flysystem for more information.

### Basic usage

You can use the driver by setting the connector config to Flysystem.

    'roots' => [
        [
            'driver' => 'Flysystem', 
            'path' => 'images',
            'URL' => '/images', 
            'filesystem' => new Filesystem(new LocalAdapter('/path/to/public_html'))
        ],
        [
            'driver' => 'Flysystem',
            'URL' => 'http://mydomain.com/content',
            'alias' => 'Mydomain.com',
            'filesystem' => new Filesystem(new FtpAdapter(
                    [
                        'host' => 'mydomain.com',
                        'username' => 'user',
                        'password' => '****',
                        'root' => '/domains/mydomain.com/public_html/content',
                    ]
                )),
        ],
        [
            'driver' => 'Flysystem',
            'adapter' => new DropboxAdapter(new Dropbox\Client($token, $appName))
        ],
    ];

The `path` and `URL` options are optional. The path defaults to '/', the URL is only possible when the file is visible through an URL.

### Displaying thumbnails/images through Glide

If you require [Glide](https://github.com/thephpleague/glide), you can show thumbnails for your images and generate secure urls.

    [
        'driver' => 'Flysystem', 
        'filesystem' => $fs,
        'glideURL' => 'http://domain.com/glideserver',
        'glideKey' => 'your-sign-key',
    ],

You can still use the tmbSize and tmbCrop options from the [configuration options](https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options-2.1#root-options)

This will require you to setup a basic server with Glide, see http://glide.thephpleague.com/
A signKey is optional, but can help secure your images against changing parameters.

> Note: When securing image, you need to remove the `_` parameter from your Request object:
> `$request->query->remove('_');`
> Otherwise the signature will fail. The `_` parameter is used to disable caching.    

### License

This elFinder driver is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

## elFinder Flysystem Driver

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

### License

This elFinder driver is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

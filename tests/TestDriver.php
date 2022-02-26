<?php

namespace Barryvdh\elFinderFlysystemDriver\Tests;


use Barryvdh\elFinderFlysystemDriver\Driver;

class TestDriver extends Driver
{
    public function __construct()
    {

        // define accept constant of server commands path
        !defined('ELFINDER_TAR_PATH') && define('ELFINDER_TAR_PATH', 'tar');
        !defined('ELFINDER_GZIP_PATH') && define('ELFINDER_GZIP_PATH', 'gzip');
        !defined('ELFINDER_BZIP2_PATH') && define('ELFINDER_BZIP2_PATH', 'bzip2');
        !defined('ELFINDER_XZ_PATH') && define('ELFINDER_XZ_PATH', 'xz');
        !defined('ELFINDER_ZIP_PATH') && define('ELFINDER_ZIP_PATH', 'zip');
        !defined('ELFINDER_UNZIP_PATH') && define('ELFINDER_UNZIP_PATH', 'unzip');
        !defined('ELFINDER_RAR_PATH') && define('ELFINDER_RAR_PATH', 'rar');
        // Create archive in RAR4 format even when using RAR5 library (true or false)
        !defined('ELFINDER_RAR_MA4') && define('ELFINDER_RAR_MA4', false);
        !defined('ELFINDER_UNRAR_PATH') && define('ELFINDER_UNRAR_PATH', 'unrar');
        !defined('ELFINDER_7Z_PATH') && define('ELFINDER_7Z_PATH', (substr(PHP_OS, 0, 3) === 'WIN') ? '7z' : '7za');
        !defined('ELFINDER_CONVERT_PATH') && define('ELFINDER_CONVERT_PATH', 'convert');
        !defined('ELFINDER_IDENTIFY_PATH') && define('ELFINDER_IDENTIFY_PATH', 'identify');
        !defined('ELFINDER_EXIFTRAN_PATH') && define('ELFINDER_EXIFTRAN_PATH', 'exiftran');
        !defined('ELFINDER_JPEGTRAN_PATH') && define('ELFINDER_JPEGTRAN_PATH', 'jpegtran');
        !defined('ELFINDER_FFMPEG_PATH') && define('ELFINDER_FFMPEG_PATH', 'ffmpeg');

        !defined('ELFINDER_DISABLE_ZIPEDITOR') && define('ELFINDER_DISABLE_ZIPEDITOR', false);

        // enable(true)/disable(false) handling postscript on ImageMagick
        // Should be `false` as long as there is a Ghostscript vulnerability
        // see https://artifex.com/news/ghostscript-security-resolved/
        !defined('ELFINDER_IMAGEMAGICK_PS') && define('ELFINDER_IMAGEMAGICK_PS', false);


        parent::__construct();

        $this->setSession(new \elFinderSession([]));
    }

    public function encode($path) {
        return parent::encode($path);
    }
}
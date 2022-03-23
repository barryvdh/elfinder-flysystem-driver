<?php

namespace Barryvdh\elFinderFlysystemDriver;

use elFinderVolumeDriver;
use Intervention\Image\ImageManager;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\CacheInterface;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Util;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\WhitespacePathNormalizer;
use League\Glide\Urls\UrlBuilderFactory;
/**
 * elFinder driver for Flysytem (https://github.com/thephpleague/flysystem)
 *
 * @author Barry vd. Heuvel
 * */
class Driver extends elFinderVolumeDriver
{
    /**
     * Driver id
     * Must be started from letter and contains [a-z0-9]
     * Used as part of volume id
     *
     * @var string
     **/
    protected $driverId = 'fls';

    /** @var FilesystemOperator $fs */
    protected $fs;

    /** @var UrlBuilder $urlBuilder */
    protected $urlBuilder = null;

    /** @var ImageManager $imageManager */
    protected $imageManager = null;

    /** @var StorageAttributes $attributes */
    protected $attributeCache = [];

    /**
     * Constructor
     * Extend options with required fields
     *
     **/
    public function __construct()
    {
        $opts = array(
            'filesystem' => null,
            'URLCallback' => null,
            'glideURL' => null,
            'glideKey' => null,
            'imageManager' => null,
            'cache' => 'session',   // 'session', 'memory' or false
            'checkSubfolders' => false, // Disable for performance
        );

        $this->options = array_merge($this->options, $opts);
    }

    protected function clearcache()
    {
        parent::clearcache();

        // clear cached attributes
        $this->attributeCache = [];
    }

    public function mount(array $opts)
    {
        // If path is not set, use the root
        if (!isset($opts['path']) || $opts['path'] === '') {
            $opts['path'] = '/';
        }

        return parent::mount($opts);
    }

    /**
     * Return the icon
     *
     * @return string
     */
    protected function getIcon()
    {
        $icon = 'volume_icon_ftp.png';

        $parentUrl = defined('ELFINDER_IMG_PARENT_URL') ? (rtrim(ELFINDER_IMG_PARENT_URL, '/') . '/') : '';
        return $parentUrl . 'img/' . $icon;
    }

    /**
     * Prepare driver before mount volume.
     * Return true if volume is ready.
     *
     * @return bool
     **/
    protected function init()
    {
        $this->fs = $this->options['filesystem'];
        if (!($this->fs instanceof FilesystemOperator)) {
            return $this->setError('A FilesystemOperator instance is required');
        }

        $this->options['icon'] = $this->options['icon'] ?: (empty($this->options['rootCssClass'])? $this->getIcon() : '');
        $this->root = $this->options['path'];

        if ($this->options['glideURL']) {
            $this->urlBuilder = UrlBuilderFactory::create($this->options['glideURL'], $this->options['glideKey']);
        }

        if ($this->options['imageManager']) {
            $this->imageManager = $this->options['imageManager'];
        } else {
            $this->imageManager = new ImageManager();
        }

        // enable command archive
        $this->options['useRemoteArchive'] = true;

        return true;
    }


    /**
     * Return parent directory path
     *
     * @param  string $path file path
     * @return string
     **/
    protected function _dirname($path)
    {
        $dirname = dirname($path);
        return  $dirname === '.' ? '/' : $dirname;
    }

    /**
     * Return normalized path
     *
     * @param  string $path path
     * @return string
     **/
    protected function _normpath($path)
    {
        return $path;
    }

    /**
     * Check if the directory exists in the parent directory. Needed because not all drives handle directories correctly.
     *
     * @param  string $path path
     * @return boolean
     **/
    protected function _dirExists($path)
    {
        $dir = $this->_dirname($path);
        $basename = basename($path);

        /** @var StorageAttributes $meta */
        foreach ($this->listContents($dir) as $attribute) {
            if ($attribute->isDir() && $this->_basename($attribute->path()) == $basename) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return stat for given path.
     * Stat contains following fields:
     * - (int)    size    file size in b. required
     * - (int)    ts      file modification time in unix time. required
     * - (string) mime    mimetype. required for folders, others - optionally
     * - (bool)   read    read permissions. required
     * - (bool)   write   write permissions. required
     * - (bool)   locked  is object locked. optionally
     * - (bool)   hidden  is object hidden. optionally
     * - (string) alias   for symlinks - link target path relative to root path. optionally
     * - (string) target  for symlinks - link target path. optionally
     *
     * If file does not exists - returns empty array or false.
     *
     * @param  string $path file path
     * @return array|false
     **/
    protected function _stat($path)
    {
        $stat = array(
            'size' => 0,
            'ts' => time(),
            'read' => true,
            'write' => true,
            'locked' => false,
            'hidden' => false,
            'mime' => 'directory',
        );

        // If root, just return from above
        if ($this->root == $path) {
            $stat['name'] = $this->root;
            return $stat;
        }

        if (isset($this->attributeCache[$path])) {
            /** @var StorageAttributes $attributes */
            $attributes = $this->attributeCache[$path];

            $meta = [
                'mimetype' => $attributes->type(),
                'extension' => null,
                'size' => null,
                'timestamp' => $attributes->lastModified(),
                'type' => $attributes->isFile() ? 'file' : 'dir',
            ];

            if ($attributes instanceof FileAttributes) {
                $meta['mimetype'] = $attributes->mimeType();
                $meta['size'] = $attributes->fileSize();
            }
        } else {
            // If not exists, return empty
            if (!$this->fs->has($path)) {

                // Check if the parent doesn't have this path
                if ($this->_dirExists($path)) {
                    return $stat;
                }

                // Neither a file or directory exist, return empty
                return array();
            }

            try {
                $meta = [
                    'mimetype' => null,
                    'extension' => null,
                    'size' => null,
                    'type' => $this->fs->fileExists($path) ? 'file' : 'dir',
                ];

                if ($meta['type'] === 'file') {
                    $meta['mimetype'] = $this->fs->mimeType($path);
                    $meta['timestamp'] = $this->fs->lastModified($path);
                    $meta['size'] = $this->fs->fileSize($path);
                }
            } catch (\Exception $e) {
                return array();
            }
        }

        if(false === $meta) {
            return $stat;
        }

        // Set item filename.extension to `name` if exists
        if (isset($meta['filename']) && isset($meta['extension'])) {
            $stat['name'] = $meta['filename'];
            if ($meta['extension'] !== '') {
                $stat['name'] .= '.' . $meta['extension'];
            }
        }

        // Get timestamp/size if available
        if (isset($meta['timestamp'])) {
            $stat['ts'] = $meta['timestamp'];
        }
        if (isset($meta['size'])) {
            $stat['size'] = $meta['size'];
        }

        // Check if file, if so, check mimetype when available
        if ($meta['type'] == 'file') {
            if(isset($meta['mimetype'])) {
                $stat['mime'] = $meta['mimetype'];
            } else {
                $stat['mime'] = null;
            }

            $imgMimes = ['image/jpeg', 'image/png', 'image/gif'];
            if ($this->urlBuilder && in_array($stat['mime'], $imgMimes)) {
                $stat['url'] = $this->urlBuilder->getUrl($path, [
                    'ts' => $stat['ts']
                ]);
                $stat['tmb'] = $this->urlBuilder->getUrl($path, [
                    'ts' => $stat['ts'],
                    'w' => $this->tmbSize,
                    'h' => $this->tmbSize,
                    'fit' => $this->options['tmbCrop'] ? 'crop' : 'contain',
                ]);
            }
        }

        if ($this->options['URLCallback'] && is_callable($this->options['URLCallback'])) {
            $stat['url'] = $this->options['URLCallback']($path);
        }

        return $stat;
    }

    /**
     * @param $path
     * @return array|StorageAttributes[]
     * @throws \League\Flysystem\FilesystemException
     */
    protected function listContents($path): array
    {
        $contents = $this->fs->listContents($path)->toArray();

        /** @var StorageAttributes $item */
        foreach ($contents as $item) {
            $this->attributeCache[$item['path']] = $item;
        }

        return $contents;
    }

    /***************** file stat ********************/

    /**
     * Return true if path is dir and has at least one childs directory
     *
     * @param  string $path dir path
     * @return bool
     **/
    protected function _subdirs($path)
    {
        $contents = array_filter($this->listContents($path), function (StorageAttributes $item) {
            return $item->isDir();
        });

        return !empty($contents);
    }

    /**
     * Return object width and height
     * Usually used for images, but can be realize for video etc...
     *
     * @param  string $path file path
     * @param  string $mime file mime type
     * @return string
     **/
    protected function _dimensions($path, $mime)
    {
        $ret = false;
        if ($imgsize = $this->getImageSize($path, $mime)) {
            $ret = $imgsize['dimensions'];
        }
        return $ret;
    }

    /******************** file/dir content *********************/

    /**
     * Return files list in directory
     *
     * @param  string $path dir path
     * @return array
     **/
    protected function _scandir($path)
    {
        $paths = array();

        foreach ($this->listContents($path, false) as $object) {
            if ($object) {
                $paths[] = $object['path'];
            }
        }
        return $paths;
    }

    /**
     * Open file and return file pointer
     *
     * @param  string $path file path
     * @param  string $mode
     * @return resource|false
     **/
    protected function _fopen($path, $mode = "rb")
    {
        return $this->fs->readStream($path);
    }

    /**
     * Close opened file
     *
     * @param  resource $fp file pointer
     * @param  string $path file path
     * @return bool
     **/
    protected function _fclose($fp, $path = '')
    {
        return @fclose($fp);
    }

    /********************  file/dir manipulations *************************/

    /**
     * Create dir and return created dir path or false on failed
     *
     * @param  string $path parent dir path
     * @param  string $name new directory name
     * @return string|bool
     **/
    protected function _mkdir($path, $name)
    {
        $path = $this->_joinPath($path, $name);

        try {
            $this->fs->createDirectory($path);
        } catch (UnableToCreateDirectory $e) {
            return false;
        }

        return $path;
    }

    /**
     * Create file and return it's path or false on failed
     *
     * @param  string $path parent dir path
     * @param string $name new file name
     * @return string|bool
     **/
    protected function _mkfile($path, $name)
    {
        $path = $this->_joinPath($path, $name);

        try {
            $this->fs->write($path, '');
        } catch (UnableToWriteFile $e) {
            return false;
        }

        return $path;
    }

    /**
     * Copy file into another file
     *
     * @param  string $source source file path
     * @param  string $target target directory path
     * @param  string $name new file name
     * @return string|bool
     **/
    protected function _copy($source, $target, $name)
    {
        $path = $this->_joinPath($target, $name);

        try {
            $this->fs->copy($source, $path);
        } catch (UnableToCopyFile $e) {
            return false;
        }

        return $path;
    }

    /**
     * Move file into another parent dir.
     * Return new file path or false.
     *
     * @param  string $source source file path
     * @param  string $target target dir path
     * @param  string $name file name
     * @return string|bool
     **/
    protected function _move($source, $target, $name)
    {
        $path = $this->_joinPath($target, $name);

        try {
            $this->fs->move($source, $path);
        } catch (UnableToMoveFile $e) {
            return false;
        }

        return $path;
    }

    /**
     * Remove file
     *
     * @param  string $path file path
     * @return bool
     **/
    protected function _unlink($path)
    {
        try {
            $this->fs->delete($path);
        } catch (UnableToDeleteFile $e) {
            return false;
        }

        return true;
    }

    /**
     * Remove dir
     *
     * @param  string $path dir path
     * @return bool
     **/
    protected function _rmdir($path)
    {
        try {
            $this->fs->deleteDirectory($path);
        } catch (UnableToDeleteDirectory $e) {
            return false;
        }

        return true;
    }

    /**
     * Create new file and write into it from file pointer.
     * Return new file path or false on error.
     *
     * @param  resource $fp file pointer
     * @param  string $dir target dir path
     * @param  string $name file name
     * @param  array $stat file stat (required by some virtual fs)
     * @return bool|string
     **/
    protected function _save($fp, $dir, $name, $stat)
    {
        $path = $this->_joinPath($dir, $name);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $config = [];
        if (isset(self::$mimetypes[$ext])) {
            $config['mimetype'] = self::$mimetypes[$ext];
        }

        if (isset($this->options['visibility'])) {
            $config['visibility'] = $this->options['visibility'];
        }

        try {
            $this->fs->writeStream($path, $fp, $config);
        } catch (UnableToWriteFile $e) {
            return false;
        }

        return $path;
    }

    /**
     * Get file contents
     *
     * @param  string $path file path
     * @return string|false
     **/
    protected function _getContents($path)
    {
        return $this->fs->read($path);
    }

    /**
     * Write a string to a file
     *
     * @param  string $path file path
     * @param  string $content new file content
     * @return bool
     **/
    protected function _filePutContents($path, $content)
    {
        try {
            $this->fs->write($path, $content);
        } catch (UnableToWriteFile $e) {
            return false;
        }
        return true;
    }

    /*********************** paths/urls *************************/


    /**
     * Return file name
     *
     * @param  string $path file path
     * @return string
     * @author Dmitry (dio) Levashov
     **/
    protected function _basename($path)
    {
        return basename($path);
    }

    /**
     * Join dir name and file name and return full path
     *
     * @param  string $dir
     * @param  string $name
     * @return string
     * @author Dmitry (dio) Levashov
     **/
    protected function _joinPath($dir, $name)
    {
        return (new WhitespacePathNormalizer())->normalizePath($dir . $this->separator . $name);
    }

    /**
     * Return file path related to root dir
     *
     * @param  string $path file path
     * @return string
     **/
    protected function _relpath($path)
    {
        return $path;
    }

    /**
     * Convert path related to root dir into real path
     *
     * @param  string $path file path
     * @return string
     **/
    protected function _abspath($path)
    {
        return $path;
    }

    /**
     * Return fake path started from root dir
     *
     * @param  string $path file path
     * @return string
     **/
    protected function _path($path)
    {
        return $this->rootName . $this->separator . $path;
    }

    /**
     * Return true if $path is children of $parent
     *
     * @param  string $path path to check
     * @param  string $parent parent path
     * @return bool
     * @author Dmitry (dio) Levashov
     **/
    protected function _inpath($path, $parent)
    {
        return $path == $parent || strpos($path, $parent . '/') === 0;
    }

    /**
     * Create symlink
     *
     * @param  string $source file to link to
     * @param  string $targetDir folder to create link in
     * @param  string $name symlink name
     * @return bool
     **/
    protected function _symlink($source, $targetDir, $name)
    {
        return false;
    }

    /**
     * Extract files from archive
     *
     * @param  string $path file path
     * @param  array $arc archiver options
     * @return bool
     **/
    protected function _extract($path, $arc)
    {
        return false;
    }

    /**
     * Create archive and return its path
     *
     * @param  string $dir target dir
     * @param  array $files files names list
     * @param  string $name archive name
     * @param  array $arc archiver options
     * @return string|bool
     **/
    protected function _archive($dir, $files, $name, $arc)
    {
        return false;
    }

    /**
     * Detect available archivers
     *
     * @return void
     **/
    protected function _checkArchivers()
    {
        return;
    }

    /**
     * chmod implementation
     *
     * @return bool
     **/
    protected function _chmod($path, $mode)
    {
        return false;
    }

    /**
     * Resize image
     *
     * @param  string $hash image file
     * @param  int $width new width
     * @param  int $height new height
     * @param  int $x X start poistion for crop
     * @param  int $y Y start poistion for crop
     * @param  string $mode action how to mainpulate image
     * @param  string $bg background color
     * @param  int $degree rotete degree
     * @param  int $jpgQuality JEPG quality (1-100)
     * @return array|false
     * @author Dmitry (dio) Levashov
     * @author Alexey Sukhotin
     * @author nao-pon
     * @author Troex Nevelin
     **/
    public function resize($hash, $width, $height, $x, $y, $mode = 'resize', $bg = '', $degree = 0, $jpgQuality = null)
    {
        if ($this->commandDisabled('resize')) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }

        if (($file = $this->file($hash)) == false) {
            return $this->setError(elFinder::ERROR_FILE_NOT_FOUND);
        }

        if (!$file['write'] || !$file['read']) {
            return $this->setError(elFinder::ERROR_PERM_DENIED);
        }

        $path = $this->decode($hash);
        if (!$this->canResize($path, $file)) {
            return $this->setError(elFinder::ERROR_UNSUPPORT_TYPE);
        }

        if (!$image = $this->imageManager->make($this->_getContents($path))) {
            return false;
        }

        switch ($mode) {
            case 'propresize':
                $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                });
                break;

            case 'crop':
                $image->crop($width, $height, $x, $y);
                break;

            case 'fitsquare':
                $image->fit($width, $height, null, 'center');
                break;

            case 'rotate':
                $image->rotate($degree);
                break;

            default:
                $image->resize($width, $height);
                break;
        }

        if ($jpgQuality && $image->mime() === 'image/jpeg') {
            $result = (string)$image->encode('jpg', $jpgQuality);
        } else {
            $result = (string)$image->encode();
        }
        if ($result && $this->_filePutContents($path, $result)) {
            $this->rmTmb($file);
            $this->clearstatcache();
            $stat = $this->stat($path);
            $stat['width'] = $image->width();
            $stat['height'] = $image->height();
            return $stat;
        }

        return false;
    }

    public function getImageSize($path, $mime = '')
    {
        $size = false;
        if ($mime === '' || strtolower(substr($mime, 0, 5)) === 'image') {
            if ($data = $this->_getContents($path)) {
                if ($size = @getimagesizefromstring($data)) {
                    $size['dimensions'] = $size[0] . 'x' . $size[1];
                }
            }
        }
        return $size;
    }

    /**
     * Return content URL
     *
     * @param string $hash file hash
     * @param array $options options
     * @return string
     **/
    public function getContentUrl($hash, $options = array())
    {
        if (! empty($options['onetime']) && $this->options['onetimeUrl']) {
            // use parent method to make onetime URL
            return parent::getContentUrl($hash, $options);
        }
        if (!empty($options['temporary'])) {
            // try make temporary file
            $url = parent::getContentUrl($hash, $options);
            if ($url) {
                return $url;
            }
        }

        if (($file = $this->file($hash)) == false || !isset($file['url']) || !$file['url'] || $file['url'] == 1) {
            if ($file && !empty($file['url']) && !empty($options['temporary'])) {
                return parent::getContentUrl($hash, $options);
            }
            $path = $this->decode($hash);

            if ($this->options['URLCallback'] && is_callable($this->options['URLCallback'])) {
                return $this->options['URLCallback']($path);
            }

            return parent::getContentUrl($hash, $options);
        }
        return $file['url'];
    }
}
